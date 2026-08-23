<?php
/**
 * Front-end buttons.
 *
 * Renders a title + a row of brand icon buttons under the WooCommerce login,
 * register and checkout forms — the placement the old Super Socializer used.
 *
 * The markup is deliberately static (no nonce, no inline JS) so it survives WP
 * Rocket page caching: the one-time CSRF `state` is minted server-side when the
 * button is clicked, never baked into cached HTML.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SL_UI {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		$o = AUN_SL_Options::all();

		// NOTE on hook choice: 'woocommerce_login_form' / 'woocommerce_register_form'
		// fire INSIDE the form just after the password field and BEFORE the submit
		// button — the exact spot Super Socializer used. The '..._form_end' variants
		// would push the icons below the Login / Register button instead.
		if ( ! empty( $o['at_wc_login'] ) ) {
			add_action( 'woocommerce_login_form', array( __CLASS__, 'render' ) );
		}
		// Outside and above the login form. Useful when another plugin hides the
		// email/password form (e.g. the phone-OTP login), which would otherwise
		// hide anything rendered inside it.
		if ( ! empty( $o['at_wc_login_before'] ) ) {
			add_action( 'woocommerce_before_customer_login_form', array( __CLASS__, 'render' ) );
		}
		if ( ! empty( $o['at_wc_register'] ) ) {
			add_action( 'woocommerce_register_form', array( __CLASS__, 'render' ) );
		}
		if ( ! empty( $o['at_wc_checkout'] ) ) {
			add_action( 'woocommerce_checkout_before_customer_details', array( __CLASS__, 'render' ) );
		}
		// The cart is the highest-intent page a logged-out shopper can be on: they
		// have already chosen. 'woocommerce_before_cart' puts the icons above the
		// cart table, ahead of the "Proceed to checkout" decision. render() bails on
		// its own for anyone already signed in, so an empty hook costs nothing.
		if ( ! empty( $o['at_wc_cart'] ) ) {
			add_action( 'woocommerce_before_cart', array( __CLASS__, 'render' ) );
		}
		if ( ! empty( $o['at_wp_login'] ) ) {
			add_action( 'login_form', array( __CLASS__, 'render' ) );
			add_action( 'register_form', array( __CLASS__, 'render' ) );
			add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		}

		// Show the reason when a sign-in attempt was refused.
		add_action( 'woocommerce_before_customer_login_form', array( __CLASS__, 'maybe_error' ) );
		add_shortcode( 'aun_social_login', array( __CLASS__, 'shortcode' ) );
	}

	public static function enqueue() {
		if ( ! AUN_SL_Options::active_providers() ) {
			return;
		}
		wp_register_style( 'aun-social-login', AUN_SL_URL . 'assets/aun-social-login.css', array(), AUN_SL_VERSION );
		wp_enqueue_style( 'aun-social-login' );

		$o    = AUN_SL_Options::all();
		$size = (int) $o['size'];
		$radius = '50%';
		if ( 'rounded' === $o['shape'] ) { $radius = '10px'; }
		if ( 'square' === $o['shape'] )  { $radius = '2px'; }

		// Only numbers/keywords reach the stylesheet, so nothing here is injectable.
		$css = ':root{--aun-sl-size:' . $size . 'px;--aun-sl-radius:' . $radius . ';}';
		wp_add_inline_style( 'aun-social-login', $css );
	}

	/** Never renders for someone who is already signed in. */
	public static function render() {
		if ( is_user_logged_in() ) {
			return;
		}
		$providers = AUN_SL_Options::active_providers();
		if ( ! $providers ) {
			return;
		}

		$o     = AUN_SL_Options::all();
		$label = ! empty( $o['show_label'] );
		$here  = self::current_url();

		$brands = array(
			'google'   => array( 'Google', 'Continue with Google' ),
			'facebook' => array( 'Facebook', 'Continue with Facebook' ),
		);

		echo '<div class="aun-sl aun-sl-align-' . esc_attr( $o['align'] ) . ( $label ? ' aun-sl-labelled' : '' ) . '">';

		if ( trim( (string) $o['title'] ) !== '' ) {
			echo '<div class="aun-sl-title"><span>' . esc_html( $o['title'] ) . '</span></div>';
		}

		echo '<div class="aun-sl-buttons">';
		foreach ( $providers as $p ) {
			$name = $brands[ $p ][0];
			$text = $brands[ $p ][1];
			printf(
				'<a class="aun-sl-btn aun-sl-%1$s" href="%2$s" rel="nofollow noopener" aria-label="%3$s" title="%3$s">%4$s%5$s</a>',
				esc_attr( $p ),
				esc_url( AUN_SL_OAuth::start_url( $p, $here ) ),
				esc_attr( $text ),
				self::icon( $p ),
				$label ? '<span class="aun-sl-btn-text">' . esc_html( $text ) . '</span>' : ''
			);
		}
		echo '</div></div>';
	}

	/** Shortcode form, e.g. [aun_social_login] on a custom page. */
	public static function shortcode() {
		ob_start();
		self::render();
		return ob_get_clean();
	}

	/** Inline brand marks (no external requests, no icon font dependency). */
	private static function icon( $provider ) {
		if ( 'google' === $provider ) {
			return '<svg class="aun-sl-ico" viewBox="0 0 48 48" aria-hidden="true" focusable="false">'
				. '<path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.9z"/>'
				. '<path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>'
				. '<path fill="#4CAF50" d="M24 44c5.2 0 9.8-2 13.3-5.2l-6.2-5.2C29.1 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.6 39.6 16.2 44 24 44z"/>'
				. '<path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.2 5.6l6.2 5.2C36.9 40.2 44 35 44 24c0-1.3-.1-2.6-.4-3.9z"/>'
				. '</svg>';
		}
		return '<svg class="aun-sl-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
			. '<path fill="#fff" d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.52 1.5-3.91 3.77-3.91 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.78-1.63 1.57v1.89h2.78l-.45 2.91h-2.33V22c4.78-.79 8.44-4.94 8.44-9.94z"/>'
			. '</svg>';
	}

	/**
	 * The page the buttons are being printed on, so the visitor lands back where
	 * they started rather than at My Account.
	 *
	 * Built from REQUEST_URI but rehosted through home_url(), which pins the host:
	 * the path can never turn the button into a link off our own domain. The
	 * receiving end revalidates with wp_validate_redirect() as well.
	 *
	 * Page-cache safe: WP Rocket caches per URL, so the REQUEST_URI baked into a
	 * cached page is that page's own address for every visitor who sees it.
	 */
	private static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( $uri === '' || $uri[0] !== '/' ) {
			// No usable path (CLI, odd proxy). Fall back to the account page.
			return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		}
		// Don't carry a previous refusal notice back onto the next page.
		return esc_url_raw( remove_query_arg( 'aun_sl_error', home_url( $uri ) ) );
	}

	/** Render the ?aun_sl_error=... message as a WooCommerce-style notice. */
	public static function maybe_error() {
		if ( empty( $_GET['aun_sl_error'] ) ) {
			return;
		}
		// The URL carries a CODE, not a sentence. Anything we do not recognise
		// renders nothing at all, so a crafted link cannot put words of its own
		// choosing inside our error box — see AUN_SL_OAuth::messages().
		$code = sanitize_key( wp_unslash( $_GET['aun_sl_error'] ) );
		$msg  = AUN_SL_OAuth::message( $code );
		if ( $msg === '' ) {
			return;
		}
		echo '<div class="woocommerce-error aun-sl-error" role="alert">' . esc_html( $msg ) . '</div>';
	}
}
