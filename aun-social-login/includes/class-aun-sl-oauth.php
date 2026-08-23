<?php
/**
 * OAuth 2.0 / OpenID Connect engine — the security-critical half of the plugin.
 *
 * Flow (authorization-code, server-to-server):
 *   1. /?aun_sl=start&p=google      -> mint single-use state, redirect to provider
 *   2. /?aun_sl=callback&p=google   -> verify state, swap code for a token over TLS,
 *                                      read the profile, then link/create + sign in.
 *
 * The browser never holds a secret, and nothing coming back from the browser is
 * trusted except the opaque `state`, which we minted and stored ourselves.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SL_OAuth {

	/** Transient prefix for the one-time CSRF state. */
	const STATE_PREFIX = 'aun_sl_st_';
	const STATE_TTL    = 600;   // 10 minutes to complete the round-trip
	const HTTP_TIMEOUT = 15;

	public static function init() {
		// 'init' runs before any output, so we can still redirect and set cookies.
		add_action( 'init', array( __CLASS__, 'maybe_handle' ), 1 );
	}

	/** The exact redirect URI to register in the Google / Facebook console. */
	public static function callback_url( $provider ) {
		return add_query_arg(
			array( 'aun_sl' => 'callback', 'p' => $provider ),
			home_url( '/' )
		);
	}

	/** Link the buttons point at. No nonce on purpose — see the header of the main file. */
	public static function start_url( $provider, $redirect = '' ) {
		$args = array( 'aun_sl' => 'start', 'p' => $provider );
		if ( $redirect !== '' ) {
			$args['redirect_to'] = rawurlencode( $redirect );
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Admin-only "run the whole flow and tell me what happened" link.
	 * A nonce is safe here (unlike on the public buttons): it is printed on the
	 * settings screen, which WP Rocket never caches, so it cannot go stale.
	 */
	public static function test_url( $provider ) {
		return wp_nonce_url(
			add_query_arg( array( 'aun_sl' => 'start', 'p' => $provider, 'test' => '1' ), home_url( '/' ) ),
			'aun_sl_test_' . $provider
		);
	}

	/* ------------------------------------------------------------------ Router */

	public static function maybe_handle() {
		if ( empty( $_GET['aun_sl'] ) ) {
			return;
		}
		$action   = sanitize_key( wp_unslash( $_GET['aun_sl'] ) );
		$provider = isset( $_GET['p'] ) ? sanitize_key( wp_unslash( $_GET['p'] ) ) : '';

		if ( ! in_array( $provider, array( 'google', 'facebook' ), true ) ) {
			return; // not ours / malformed - let WordPress carry on normally
		}
		if ( ! AUN_SL_Options::provider_ready( $provider ) ) {
			self::fail( 'This sign-in method is not available right now.' );
		}
		if ( ! self::rate_ok() ) {
			self::fail( 'Too many sign-in attempts. Please wait a minute and try again.' );
		}

		if ( 'start' === $action ) {
			$is_test = ! empty( $_GET['test'] );
			if ( $is_test ) {
				// Diagnostic run: administrators only, and the link must be signed.
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
				if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, 'aun_sl_test_' . $provider ) ) {
					wp_die( 'You are not allowed to run this test.', '', array( 'response' => 403 ) );
				}
			} elseif ( is_user_logged_in() ) {
				wp_safe_redirect( self::default_redirect() );
				exit;
			}
			self::start( $provider, $is_test );
		} elseif ( 'callback' === $action ) {
			// Whether this is a test is read from OUR stored state, never from the URL.
			self::callback( $provider );
		}
	}

	/* ------------------------------------------------------------------ Step 1 */

	private static function start( $provider, $is_test = false ) {
		$state = bin2hex( random_bytes( 32 ) );

		$redirect = '';
		if ( ! empty( $_GET['redirect_to'] ) ) {
			$redirect = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$redirect = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}
		// wp_validate_redirect() keeps us on this host, so ?redirect_to= can never
		// be turned into an open redirect to an attacker site.
		$redirect = wp_validate_redirect( $redirect, self::default_redirect() );

		set_transient(
			self::STATE_PREFIX . hash( 'sha256', $state ),
			array( 'p' => $provider, 'r' => $redirect, 't' => $is_test ? 1 : 0 ),
			self::STATE_TTL
		);

		wp_redirect( self::authorize_url( $provider, $state ) );
		exit;
	}

	private static function authorize_url( $provider, $state ) {
		if ( 'google' === $provider ) {
			return add_query_arg(
				array(
					'client_id'     => rawurlencode( AUN_SL_Options::get( 'google_client_id' ) ),
					'redirect_uri'  => rawurlencode( self::callback_url( 'google' ) ),
					'response_type' => 'code',
					'scope'         => rawurlencode( 'openid email profile' ),
					'state'         => $state,
					'prompt'        => 'select_account',
				),
				'https://accounts.google.com/o/oauth2/v2/auth'
			);
		}
		return add_query_arg(
			array(
				'client_id'     => rawurlencode( AUN_SL_Options::get( 'facebook_app_id' ) ),
				'redirect_uri'  => rawurlencode( self::callback_url( 'facebook' ) ),
				'response_type' => 'code',
				'scope'         => rawurlencode( 'email,public_profile' ),
				'state'         => $state,
			),
			'https://www.facebook.com/v21.0/dialog/oauth'
		);
	}

	/* ------------------------------------------------------------------ Step 2 */

	private static function callback( $provider ) {
		// The user pressed "Cancel" on the provider screen.
		if ( ! empty( $_GET['error'] ) ) {
			self::fail( 'Sign-in was cancelled.' );
		}

		$state = isset( $_GET['state'] ) ? (string) wp_unslash( $_GET['state'] ) : '';
		$code  = isset( $_GET['code'] ) ? (string) wp_unslash( $_GET['code'] ) : '';
		if ( $state === '' || $code === '' ) {
			self::fail( 'Sign-in could not be completed. Please try again.' );
		}

		// --- CSRF: the state must be one we minted, and it dies on first use. ---
		$key   = self::STATE_PREFIX . hash( 'sha256', $state );
		$saved = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $saved ) || ! isset( $saved['p'] ) || ! hash_equals( (string) $saved['p'], $provider ) ) {
			self::fail( 'This sign-in link has expired. Please try again.' );
		}

		$is_test = ! empty( $saved['t'] );
		if ( $is_test && ! current_user_can( 'manage_options' ) ) {
			self::fail( 'That test link is no longer valid.' );
		}

		$token = self::exchange_code( $provider, $code );
		if ( ! $token ) {
			if ( $is_test ) {
				self::finish_test( $provider, false, 'Could not exchange the code for a token. Usually the Client ID / secret is wrong, or the Redirect URL does not match the one registered with the provider.' );
			}
			self::fail( 'We could not verify your account with the provider. Please try again.' );
		}

		$profile = self::fetch_profile( $provider, $token );
		if ( ! $profile || empty( $profile['id'] ) ) {
			if ( $is_test ) {
				self::finish_test( $provider, false, 'The token worked, but the profile could not be read. Check that the email / public_profile permission is granted to the app.' );
			}
			self::fail( 'We could not read your profile from the provider.' );
		}

		// Diagnostic run stops here: nothing is created, linked or signed in.
		if ( $is_test ) {
			self::finish_test( $provider, true, '', $profile );
		}

		$user_id = self::authenticate( $provider, $profile );
		if ( is_wp_error( $user_id ) ) {
			self::fail( $user_id->get_error_message() );
		}

		$redirect = isset( $saved['r'] ) ? $saved['r'] : self::default_redirect();
		wp_safe_redirect( wp_validate_redirect( $redirect, self::default_redirect() ) );
		exit;
	}

	/**
	 * THE one way into an account for every entry point (redirect buttons and One
	 * Tap alike): apply the account rules, refresh the avatar, set the cookie.
	 * Anything that authenticates a visitor must come through here, otherwise a
	 * new entry point could quietly bypass the linking and admin guards.
	 *
	 * @param string $provider google|facebook
	 * @param array  $profile  id, email, email_verified, first, last, avatar
	 * @return int|WP_Error user id on success
	 */
	public static function authenticate( $provider, $profile ) {
		$user_id = self::login_or_register( $provider, $profile );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		// Refresh the profile picture on every sign-in: provider CDN links rotate.
		if ( AUN_SL_Options::get( 'use_avatar' ) && ! empty( $profile['avatar'] ) ) {
			AUN_SL_Avatar::store( $user_id, $profile['avatar'] );
		}
		self::sign_in( $user_id );
		return $user_id;
	}

	/** Swap the one-time code for an access token. Server-to-server, over TLS. */
	private static function exchange_code( $provider, $code ) {
		if ( 'google' === $provider ) {
			$url  = 'https://oauth2.googleapis.com/token';
			$body = array(
				'code'          => $code,
				'client_id'     => AUN_SL_Options::get( 'google_client_id' ),
				'client_secret' => AUN_SL_Options::get( 'google_client_secret' ),
				'redirect_uri'  => self::callback_url( 'google' ),
				'grant_type'    => 'authorization_code',
			);
		} else {
			$url  = 'https://graph.facebook.com/v21.0/oauth/access_token';
			$body = array(
				'code'          => $code,
				'client_id'     => AUN_SL_Options::get( 'facebook_app_id' ),
				'client_secret' => AUN_SL_Options::get( 'facebook_app_secret' ),
				'redirect_uri'  => self::callback_url( 'facebook' ),
			);
		}

		$res = wp_remote_post( $url, array( 'timeout' => self::HTTP_TIMEOUT, 'body' => $body ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			self::log( $provider . ' token exchange failed with status ' . ( is_wp_error( $res ) ? 'WP_Error' : wp_remote_retrieve_response_code( $res ) ) );
			return '';
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		return ( is_array( $data ) && ! empty( $data['access_token'] ) ) ? (string) $data['access_token'] : '';
	}

	/**
	 * Read the profile with the token we just obtained.
	 * Returns array{id,email,email_verified,first,last} or null.
	 */
	private static function fetch_profile( $provider, $token ) {
		if ( 'google' === $provider ) {
			$res = wp_remote_get( 'https://openidconnect.googleapis.com/v1/userinfo', array(
				'timeout' => self::HTTP_TIMEOUT,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			) );
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				return null;
			}
			$d = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( ! is_array( $d ) || empty( $d['sub'] ) ) {
				return null;
			}
			return array(
				'id'             => (string) $d['sub'],
				'avatar'         => isset( $d['picture'] ) ? (string) $d['picture'] : '',
				'email'          => isset( $d['email'] ) ? sanitize_email( $d['email'] ) : '',
				// Google states explicitly whether it verified the address. We only
				// ever trust a true here when matching an existing account.
				'email_verified' => ! empty( $d['email_verified'] ),
				'first'          => isset( $d['given_name'] ) ? sanitize_text_field( $d['given_name'] ) : '',
				'last'           => isset( $d['family_name'] ) ? sanitize_text_field( $d['family_name'] ) : '',
			);
		}

		// Facebook: appsecret_proof ties the call to OUR app, so a token stolen from
		// another app cannot be replayed against this site.
		$secret = AUN_SL_Options::get( 'facebook_app_secret' );
		$res    = wp_remote_get( add_query_arg( array(
			'fields'          => rawurlencode( 'id,first_name,last_name,email,picture.width(200).height(200)' ),
			'access_token'    => rawurlencode( $token ),
			'appsecret_proof' => hash_hmac( 'sha256', $token, $secret ),
		), 'https://graph.facebook.com/v21.0/me' ), array( 'timeout' => self::HTTP_TIMEOUT ) );

		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}
		$d = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $d ) || empty( $d['id'] ) ) {
			return null;
		}
		$email  = isset( $d['email'] ) ? sanitize_email( $d['email'] ) : '';
		$avatar = isset( $d['picture']['data']['url'] ) ? (string) $d['picture']['data']['url'] : '';
		return array(
			'id'             => (string) $d['id'],
			'avatar'         => $avatar,
			'email'          => $email,
			// Facebook only releases an address it has confirmed for the account.
			'email_verified' => ( $email !== '' ),
			'first'          => isset( $d['first_name'] ) ? sanitize_text_field( $d['first_name'] ) : '',
			'last'           => isset( $d['last_name'] ) ? sanitize_text_field( $d['last_name'] ) : '',
		);
	}

	/**
	 * End a diagnostic run: stash the result for the admin who started it and send
	 * them back to the settings screen. The result goes in a transient, never in the
	 * URL, so no profile data ends up in browser history or server logs.
	 */
	private static function finish_test( $provider, $ok, $error = '', $profile = array() ) {
		$result = array(
			'provider' => $provider,
			'ok'       => (bool) $ok,
			'error'    => $error,
			'when'     => time(),
		);
		if ( $ok && $profile ) {
			$result['profile'] = array(
				'id'       => $profile['id'],
				'email'    => $profile['email'],
				'verified' => ! empty( $profile['email_verified'] ),
				'name'     => trim( $profile['first'] . ' ' . $profile['last'] ),
				'avatar'   => ! empty( $profile['avatar'] ) ? AUN_SL_Avatar::sanitize_url( $profile['avatar'] ) : '',
			);
			$result['outcome'] = self::decide( $provider, $profile );
		}
		set_transient( 'aun_sl_test_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'options-general.php?page=aun-social-login&aun_sl_test=1' ) );
		exit;
	}

	/* ------------------------------------------------- Account linking / creation */

	/**
	 * Resolve the social profile to a WordPress user.
	 *
	 * Order matters:
	 *   1. an existing LINK (provider id stored on the user) - always safe;
	 *   2. a provider-VERIFIED email that matches an existing account;
	 *   3. otherwise create a new account.
	 * An unverified email never matches step 2 — that would let anyone who can set
	 * an arbitrary address on a social profile seize a customer account.
	 */
	private static function login_or_register( $provider, $profile ) {
		$d        = self::decide( $provider, $profile );
		$meta_key = 'aun_sl_' . $provider . '_id';

		if ( 'refuse' === $d['action'] ) {
			return new WP_Error( $d['code'], $d['message'] );
		}
		if ( 'login' === $d['action'] ) {
			return (int) $d['user_id'];
		}
		if ( 'link' === $d['action'] ) {
			update_user_meta( (int) $d['user_id'], $meta_key, $profile['id'] );
			return (int) $d['user_id'];
		}
		return self::create_user( $provider, $profile, $meta_key );
	}

	/**
	 * PURE decision: what should happen for this social profile? No writes, so the
	 * admin test can preview the exact outcome a real customer would get without
	 * touching a single account. login_or_register() is the only thing that acts on
	 * it, which keeps ONE source of truth for the security rules.
	 *
	 * @return array{action:string,user_id:int,code:string,message:string}
	 */
	private static function decide( $provider, $profile ) {
		$meta_key = 'aun_sl_' . $provider . '_id';

		$linked = get_users( array(
			'meta_key'    => $meta_key,
			'meta_value'  => $profile['id'],
			'number'      => 2,
			'fields'      => 'ID',
			'count_total' => false,
		) );
		if ( count( $linked ) > 1 ) {
			// Two accounts claim the same provider id: refuse rather than guess.
			self::log( 'duplicate link for ' . $provider . ' id ' . $profile['id'] );
			return self::refuse( 'aun_sl_dup', 'We found more than one account for this profile. Please contact support.' );
		}
		if ( count( $linked ) === 1 ) {
			$uid = (int) $linked[0];
			if ( self::is_blocked_admin( $uid ) ) {
				return self::refuse( 'aun_sl_admin', 'For security, staff accounts must sign in with a password rather than a social account.' );
			}
			return array( 'action' => 'login', 'user_id' => $uid, 'code' => '', 'message' => 'Signs in to the already-linked account.' );
		}

		$email = $profile['email'];
		if ( $email === '' || ! is_email( $email ) ) {
			return self::refuse( 'aun_sl_noemail', 'Your social profile did not share an email address, so we cannot create your account. Please register with your email or phone number instead.' );
		}

		$existing = get_user_by( 'email', $email );
		if ( $existing ) {
			if ( ! AUN_SL_Options::get( 'link_by_email' ) || empty( $profile['email_verified'] ) ) {
				return self::refuse( 'aun_sl_exists', 'An account already exists with this email address. Please sign in with your password to continue.' );
			}
			if ( self::is_blocked_admin( (int) $existing->ID ) ) {
				return self::refuse( 'aun_sl_admin', 'For security, staff accounts must sign in with a password rather than a social account.' );
			}
			return array( 'action' => 'link', 'user_id' => (int) $existing->ID, 'code' => '', 'message' => 'Links this profile to the existing account with the same verified email, then signs in.' );
		}

		if ( ! AUN_SL_Options::get( 'allow_register' ) ) {
			return self::refuse( 'aun_sl_noreg', 'New account creation is currently disabled. Please contact support.' );
		}
		return array( 'action' => 'create', 'user_id' => 0, 'code' => '', 'message' => 'Creates a new customer account and signs in.' );
	}

	private static function refuse( $code, $msg ) {
		return array( 'action' => 'refuse', 'user_id' => 0, 'code' => $code, 'message' => $msg );
	}

	private static function is_blocked_admin( $user_id ) {
		return AUN_SL_Options::get( 'block_admins' ) && user_can( $user_id, 'edit_others_posts' );
	}

	private static function create_user( $provider, $profile, $meta_key ) {
		$email = $profile['email'];
		$parts = explode( '@', $email );
		$base  = sanitize_user( $parts[0], true );
		if ( $base === '' ) { $base = 'user'; }

		$login = $base;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$i++;
			$login = $base . '-' . $i;
			if ( $i > 500 ) { // pathological; fall back to something certainly unique
				$login = $base . '-' . wp_generate_password( 6, false, false );
				break;
			}
		}

		$display = trim( $profile['first'] . ' ' . $profile['last'] );
		if ( $display === '' ) { $display = $login; }

		$user_id = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'first_name'   => $profile['first'],
			'last_name'    => $profile['last'],
			'display_name' => $display,
			'role'         => class_exists( 'WooCommerce' ) ? 'customer' : 'subscriber',
		) );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, $meta_key, $profile['id'] );
		update_user_meta( $user_id, 'aun_sl_registered_via', $provider );
		if ( class_exists( 'WooCommerce' ) ) {
			update_user_meta( $user_id, 'billing_email', $email );
			if ( $profile['first'] !== '' ) { update_user_meta( $user_id, 'billing_first_name', $profile['first'] ); }
			if ( $profile['last'] !== '' )  { update_user_meta( $user_id, 'billing_last_name', $profile['last'] ); }
		}

		if ( AUN_SL_Options::get( 'notify_admin' ) ) {
			wp_new_user_notification( $user_id, null, 'admin' );
		}
		return $user_id;
	}

	private static function sign_in( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			self::fail( 'Sign-in failed. Please try again.' );
		}
		wp_set_current_user( $user_id, $user->user_login );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'wp_login', $user->user_login, $user );
	}

	/* ------------------------------------------------------------------ Helpers */

	private static function default_redirect() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'myaccount' );
			if ( $url ) { return $url; }
		}
		return home_url( '/' );
	}

	/** Send the visitor back to the account page with a readable message. */
	private static function fail( $message ) {
		$url = add_query_arg( 'aun_sl_error', rawurlencode( $message ), self::default_redirect() );
		wp_safe_redirect( $url );
		exit;
	}

	/** Simple per-IP throttle so the callback cannot be hammered. */
	private static function rate_ok() {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = (string) wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = (string) wp_unslash( $_SERVER['REMOTE_ADDR'] );
		}
		$key = 'aun_sl_rl_' . hash( 'sha256', $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 20 ) {
			return false;
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		return true;
	}

	private static function log( $msg ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'AUN Social Login: ' . $msg );  // never contains tokens
		}
	}
}
