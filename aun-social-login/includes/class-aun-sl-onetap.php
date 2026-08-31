<?php
/**
 * Google One Tap — the floating "Sign in as …" prompt.
 *
 * Merged in from the standalone aun-google-one-tap plugin. The important change is
 * that One Tap no longer has its own account logic: it normalises the Google token
 * into the same profile shape the redirect flow uses and hands it to
 * AUN_SL_OAuth::authenticate(), so it inherits *every* guard — provider-id linking,
 * the verified-email rule, the administrator block, allow-register, avatars.
 *
 * Token trust: the credential is a Google-signed JWT. Rather than verify the
 * signature locally (which needs JWKS handling and a crypto dependency), it is
 * checked with Google's tokeninfo endpoint over TLS and then every claim that
 * matters is re-checked here: aud (this site), iss (Google), exp (not expired) and
 * email_verified. Without the aud check, a token minted for ANY other Google app
 * would be accepted — that is the classic One Tap mistake.
 *
 * FedCM flags, from Google's own reference:
 *   use_fedcm_for_prompt  is DEPRECATED and ignored — it was doing nothing here.
 *   use_fedcm_for_button  enables the browser's account-chooser dialog for a BUTTON
 *                         press (Chrome desktop M125+, Android M128+).
 * The dialog can only be raised by Google's OWN rendered button — see
 * class-aun-sl-popup.php. A custom anchor calling prompt() cannot produce it.
 *
 * Caching: the CSRF nonce is fetched fresh over AJAX at click time, never printed
 * into the page. A nonce baked into WP-Rocket-cached HTML goes stale within a day
 * and One Tap would silently stop working for real visitors.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SL_OneTap {

	/**
	 * Two separate things, deliberately decoupled:
	 *   - the endpoints and the Google library are needed whenever Google sign-in
	 *     works at all, because the BUTTON can open the same browser dialog;
	 *   - the automatic prompt on page load is what 'onetap_enabled' controls.
	 * Tying the library to the automatic prompt meant the button could only show
	 * the native dialog for sites that also wanted the prompt appearing by itself.
	 */
	public static function init() {
		if ( ! AUN_SL_Options::provider_ready( 'google' ) ) {
			return;
		}
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 99 );
		add_action( 'wp_ajax_nopriv_aun_sl_onetap', array( __CLASS__, 'ajax_login' ) );
		add_action( 'wp_ajax_aun_sl_onetap', array( __CLASS__, 'ajax_login' ) );
		add_action( 'wp_ajax_nopriv_aun_sl_nonce', array( __CLASS__, 'ajax_nonce' ) );
		add_action( 'wp_ajax_aun_sl_nonce', array( __CLASS__, 'ajax_nonce' ) );
	}

	/** Hands out a fresh nonce so cached HTML never carries a stale one. */
	public static function ajax_nonce() {
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'aun_sl_onetap' ) ) );
	}

	public static function render() {
		if ( is_user_logged_in() ) {
			return;
		}
		$client_id = AUN_SL_Options::get( 'google_client_id' );
		if ( $client_id === '' ) {
			return;
		}

		$auto = (bool) AUN_SL_Options::get( 'onetap_enabled' );
		$btn  = (bool) AUN_SL_Options::get( 'js_flow' );
		if ( ! $auto && ! $btn ) {
			return;   // nothing on this page needs the Google library
		}
		$ajax = admin_url( 'admin-ajax.php' );
		?>
		<?php if ( $auto ) : ?>
		<div id="g_id_onload"
			data-client_id="<?php echo esc_attr( $client_id ); ?>"
			data-callback="aunSlOneTap"
			data-auto_prompt="true"
			data-cancel_on_tap_outside="false"
			data-context="signin"
			data-itp_support="true"
			data-use_fedcm_for_button="true"></div>
		<?php else : ?>
		<?php /* No automatic prompt: initialise only, so the button can open the
		         same browser dialog on demand. */ ?>
		<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">
		window.addEventListener('load', function(){
			if (!(window.google && google.accounts && google.accounts.id)) { return; }
			try {
				google.accounts.id.initialize({
					client_id: <?php echo wp_json_encode( $client_id ); ?>,
					callback: aunSlOneTap,
					use_fedcm_for_button: true,
					itp_support: true,
					cancel_on_tap_outside: false
				});
			} catch (e) {}
		});
		</script>
		<?php endif; ?>
		<script src="https://accounts.google.com/gsi/client" async defer></script>
		<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">
		/* One Tap -> our own endpoint. The nonce is fetched at the moment of use
		   (not printed here) so this block stays safe to page-cache. */
		function aunSlOneTap( response ) {
			if ( ! response || ! response.credential ) { return; }
			var ajax = <?php echo wp_json_encode( $ajax ); ?>;
			fetch( ajax + '?action=aun_sl_nonce', { credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( n ) {
					var body = new URLSearchParams();
					body.append( 'action', 'aun_sl_onetap' );
					body.append( 'credential', response.credential );
					body.append( 'security', ( n && n.data && n.data.nonce ) ? n.data.nonce : '' );
					/* Where the visitor actually is. The sign-in itself runs over
					   admin-ajax, where is_checkout() is always false, so the server
					   cannot work this out on its own — it would send someone who was
					   halfway through checkout back to My Account. Revalidated
					   server-side against this host before it is used. */
					body.append( 'here', window.location.href );
					return fetch( ajax, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: body.toString()
					} );
				} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( d ) {
					if ( d && d.success && d.data && d.data.redirect ) {
						window.location.assign( d.data.redirect );
					}
					/* On refusal we stay silent: One Tap is opportunistic, and the
					   visitor still has the normal buttons and the password form. */
				} )
				.catch( function () {} );
		}
		</script>
		<?php
	}

	public static function ajax_login() {
		if ( is_user_logged_in() ) {
			wp_send_json_success( array( 'redirect' => '', 'message' => 'Already signed in.' ) );
		}
		if ( ! check_ajax_referer( 'aun_sl_onetap', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Your session expired. Please reload the page.' ), 403 );
		}
		// Same per-IP ceiling the redirect flow uses. Without it this endpoint is an
		// unauthenticated way to make the site call Google's tokeninfo on demand.
		if ( ! AUN_SL_OAuth::rate_ok() ) {
			wp_send_json_error( array( 'message' => 'Too many sign-in attempts. Please wait a minute and try again.' ), 429 );
		}

		$credential = isset( $_POST['credential'] ) ? (string) wp_unslash( $_POST['credential'] ) : '';
		// Shape check first: three base64url segments. Rejects junk before we call out.
		if ( ! preg_match( '#^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$#', $credential ) ) {
			wp_send_json_error( array( 'message' => 'Invalid credential.' ), 400 );
		}

		$claims = self::verify_credential( $credential );
		if ( is_wp_error( $claims ) ) {
			wp_send_json_error( array( 'message' => $claims->get_error_message() ), 400 );
		}

		// Same profile shape the redirect flow produces, so the shared account
		// rules apply identically no matter which door the customer came through.
		$profile = array(
			'id'             => (string) $claims['sub'],
			'email'          => sanitize_email( $claims['email'] ),
			'email_verified' => true, // enforced in verify_credential()
			'first'          => isset( $claims['given_name'] ) ? sanitize_text_field( $claims['given_name'] ) : '',
			'last'           => isset( $claims['family_name'] ) ? sanitize_text_field( $claims['family_name'] ) : '',
			'avatar'         => isset( $claims['picture'] ) ? (string) $claims['picture'] : '',
		);

		$user_id = AUN_SL_OAuth::authenticate( 'google', $profile );
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ), 403 );
		}

		wp_send_json_success( array( 'redirect' => self::redirect_url() ) );
	}

	/**
	 * Verify the One Tap credential with Google and re-check every claim.
	 *
	 * @return array|WP_Error validated claims
	 */
	private static function verify_credential( $credential ) {
		$res = wp_remote_get(
			'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode( $credential ),
			array( 'timeout' => 10 )
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'aun_sl_net', 'Could not reach Google to verify the sign-in.' );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return new WP_Error( 'aun_sl_bad_token', 'Google rejected the sign-in. Please try again.' );
		}
		$c = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $c ) ) {
			return new WP_Error( 'aun_sl_bad_token', 'Unreadable response from Google.' );
		}

		// 1. Audience: the token must be for THIS site, not any other Google app.
		$client_id = AUN_SL_Options::get( 'google_client_id' );
		if ( empty( $c['aud'] ) || ! hash_equals( (string) $client_id, (string) $c['aud'] ) ) {
			return new WP_Error( 'aun_sl_aud', 'This sign-in was not issued for this website.' );
		}
		// 2. Issuer must be Google.
		if ( empty( $c['iss'] ) || ! in_array( $c['iss'], array( 'accounts.google.com', 'https://accounts.google.com' ), true ) ) {
			return new WP_Error( 'aun_sl_iss', 'Unexpected token issuer.' );
		}
		// 3. Not expired.
		if ( empty( $c['exp'] ) || (int) $c['exp'] < time() ) {
			return new WP_Error( 'aun_sl_exp', 'That sign-in has expired. Please try again.' );
		}
		// 4. Verified email only — tokeninfo returns strings, so accept "true"/true/1.
		$verified = isset( $c['email_verified'] ) ? $c['email_verified'] : false;
		if ( ! in_array( $verified, array( true, 'true', 1, '1' ), true ) ) {
			return new WP_Error( 'aun_sl_unverified', 'Your Google email address is not verified.' );
		}
		if ( empty( $c['sub'] ) || empty( $c['email'] ) || ! is_email( $c['email'] ) ) {
			return new WP_Error( 'aun_sl_payload', 'Incomplete Google profile.' );
		}
		return $c;
	}

	/**
	 * Send the visitor back to the page they signed in from — checkout, a product,
	 * wherever. `here` comes from the browser, so it is put through
	 * wp_validate_redirect(): anything off-host falls back to the account page.
	 */
	private static function redirect_url() {
		$here = isset( $_POST['here'] ) ? esc_url_raw( wp_unslash( $_POST['here'] ) ) : '';
		return wp_validate_redirect( $here, self::default_redirect() );
	}

	private static function default_redirect() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'myaccount' );
			if ( $url ) { return $url; }
		}
		return home_url( '/' );
	}
}
