<?php
/**
 * Plugin Name: AUN - lean app API requests
 * Description: Stops plugins that exist for rendering web pages from loading on the Android app's REST calls.
 *
 * INSTALL: upload to wp-content/mu-plugins/ (create that folder if it does not
 * exist). Files there load automatically - nothing to activate, and nothing
 * appears in the Plugins list.
 *
 * REMOVE: delete the file. Or switch it off without deleting, by adding this to
 * wp-config.php:   define( 'AUN_API_LEAN_OFF', true );
 *
 * -- Why -----------------------------------------------------------------
 *
 * Measured on the live site, 2026-08-19: 628 ms of WordPress bootstrap before a
 * single line of app code ran, with 68 active plugins. The app's REST calls
 * were loading a five-plugin slider suite, three SEO plugins, Google Site Kit,
 * Facebook for WooCommerce (which was setting a tracking cookie on the app's
 * JSON responses), a form builder, a table-of-contents plugin and a file
 * manager - none of which can affect a JSON response the app asked for.
 *
 * This does NOT make WordPress faster. It stops it loading what the app never
 * uses.
 *
 * -- The rule ------------------------------------------------------------
 *
 * A DENY-list, not an allow-list, and deliberately so. An allow-list is faster
 * but fails in the worst way: forget one dependency and an endpoint breaks in
 * production with no clue why. A deny-list can only ever remove what is named
 * below, so the blast radius is exactly what you can read.
 *
 * Only the app's own REST namespace is affected. The website, wp-admin, cron
 * and WooCommerce's own REST API are untouched.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'option_active_plugins', function ( $plugins ) {

	if ( defined( 'AUN_API_LEAN_OFF' ) && AUN_API_LEAN_OFF ) {
		return $plugins;
	}
	if ( ! is_array( $plugins ) || is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		return $plugins;
	}

	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	if ( false === strpos( $uri, '/wp-json/aun-app/' )
		&& false === strpos( $uri, 'rest_route=/aun-app/' )
		&& false === strpos( $uri, 'rest_route=%2Faun-app%2F' ) ) {
		return $plugins;
	}

	/*
	 * KEPT, and why - every one of these is reachable from the app API:
	 *
	 *   woocommerce ............... orders, coupons, payment (WC, wc_get_order)
	 *   wc-sslcommerz ............. the gateway an app order is paid through
	 *   woocommerce-smart-coupons . referral coupons live in WooCommerce
	 *   aun-spare-parts ........... AUN_SP_Requests / _Woo / _I18N
	 *   AUN Warranty .............. slb_send_templated_sms/email, device tables
	 *   slb-erp-sync .............. ERP credentials
	 *   slb-repair-tracker ........ repair-status API key
	 *   AUN-Projector-Wizard ...... /finder calls AUN_Projector_Wizard::recommend()
	 *   AUN Throw Distance ........ AUN_Throw_Calculator
	 *   aun-help-center ........... aun_hc_data() / aun_hc_models()
	 *   alpha-sms + otp-login ..... OTP and every customer SMS
	 *   aun-maintenance-sms ....... maintenance reminders
	 *   wpo365-msgraphmailer ...... wp_mail(); staff email would vanish silently
	 *   wordfence ................. SECURITY. The API is the most exposed
	 *                               surface we have; never unload the firewall.
	 */
	$drop = array(
		// Sliders - five plugins, and none of them can render into JSON.
		'revslider/revslider.php',
		'revslider-beforeafter-addon/revslider-beforeafter-addon.php',
		'revslider-mousetrap-addon/revslider-mousetrap-addon.php',
		'revslider-particles-addon/revslider-particles-addon.php',
		'revslider-transitionpack-addon/revslider-transitionpack-addon.php',
		// SEO - no search engine reads the app's JSON.
		'seo-by-rank-math/rank-math.php',
		'seo-by-rank-math-pro/rank-math-pro.php',
		'AUN Rank Math SEO Customizations/AUN-Rank-Math-SEO-Customizations.php',
		// Marketing and analytics. facebook-for-woocommerce is the one that was
		// setting a _fbp tracking cookie on the app's API responses.
		'facebook-for-woocommerce/facebook-for-woocommerce.php',
		'google-site-kit/google-site-kit.php',
		'google-listings-and-ads/google-listings-and-ads.php',
		'omnisend-connect/omnisend-woocommerce.php',
		'super-socializer/super_socializer.php',
		'customer-reviews-woocommerce/ivole.php',
		// Editor and page furniture.
		'contact-form-7/wp-contact-form-7.php',
		'easy-table-of-contents/easy-table-of-contents.php',
		'classic-editor/classic-editor.php',
		'browser-theme-color/browser-theme-color.php',
		'wp-file-download/wp-file-download.php',
		'wp-file-download-cloud-addon/wp-file-download-addon.php',
		// Admin conveniences that no front-end request needs.
		'akismet/akismet.php',
		'health-check/health-check.php',
		'loco-translate/loco.php',
		'wp-rollback/wp-rollback.php',
		'wp-mail-logging/wp-mail-logging.php',
		'wps-hide-login/wps-hide-login.php',
		// Page caching and the CF helper: a REST response is not page-cached.
		'wp-rocket/wp-rocket.php',
		'cloudflare/cloudflare.php',
		// AUN website furniture - shortcodes, banners, page layout.
		'AUN About Counter/aun-about-counters.php',
		'AUN Shorts Showcase/aun-shorts-showcase.php',
		'AUN Smart Bundler/aun-smart-bundler.php',
		'AUN-EMI-Table/aun-emi-table.php',
		'AUN-Google-One-Tap/aun-google-one-tap.php',
		'aun-campaign-bar/aun-campaign-bar.php',
		'aun-care-promo/aun-care-promo.php',
		'aun-chat-order/aun-chat-order.php',
		'aun-compare-plugin/aun-compare-plugin.php',
		// /dealers reads the WARRANTY tables, not this plugin - checked.
		'aun-dealer-locator-v1.1/aun-dealer-locator.php',
		'aun-live-tracking/aun-live-tracking.php',
		'aun-logos-marquee-classic-3.1.0/aun-logos-marquee-classic.php',
		'aun-section-head/aun-section-head.php',
		'aun-smart-adjustment/aun-smart-adjustments.php',
		'aun-tutorial-video/aun-tutorial-video/aun-tutorials.php',
		// WooCommerce add-ons that only shape the SHOP PAGES.
		'ast-pro/ast-pro.php',
		'connect-yeamazing/connect-yeamazing.php',
		'discontinued-products/woocommerce-discontinued-products.php',
		'wb-custom-product-tabs-for-woocommerce/wb-custom-product-tabs-for-woocommerce.php',
		'woocommerce-extra-price-fields/woocommerce-extra-price-fields.php',
		'courier-woocommerce-plugin-main/pathao-courier.php',
	);

	/*
	 * TranslatePress is NOT dropped, and that is deliberate.
	 *
	 * It is heavy and tempting. But AUN_SP_I18N::req_lang() falls back to the
	 * language TranslatePress reports, and the spare-parts plugin uses that for
	 * customer-facing strings. Dropping it here would make Bangla text quietly
	 * come back in English on some responses - a bug nobody would trace to a
	 * performance change made weeks earlier.
	 *
	 * Revisit only after confirming every AUN_SP_I18N::msg() call passes an
	 * explicit language.
	 */

	$lean = array_values( array_diff( $plugins, $drop ) );

	// Never return an empty list. If these paths ever stop matching (a folder
	// renamed, a different install layout), fall back to loading everything
	// rather than serving an API with no plugins at all.
	return empty( $lean ) ? $plugins : $lean;
}, 1 );
