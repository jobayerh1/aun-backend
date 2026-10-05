<?php
/**
 * Native WooCommerce signals for tracking plugins (Meta Pixel / Conversions
 * API via Facebook for WooCommerce, Google tags, GTM4WP …).
 *
 * The Breo canvas renders product and shop pages itself, so the template
 * hooks those plugins listen for never ran:
 *   woocommerce_after_single_product  -> ViewContent / view_item
 *   woocommerce_after_shop_loop       -> ViewCategory / view_item_list
 * They are fired here at the same point a standard theme would.
 *
 * Add to cart: both our "Buy now" (?breo-buy) and the buy-panel form redirect
 * straight after adding, which drops the browser half of AddToCart. Facebook
 * for WooCommerce only carries it over to the next page when WooCommerce's
 * "redirect after add to cart" option is on, and reads that option while its
 * own handler (priority 40) runs. So the option is switched on for exactly
 * that moment, and the carried-over events are printed on the next page.
 *
 * Page cache: carried-over events belong to one shopper, so a page printing
 * them is marked "do not cache" (WP Rocket would otherwise save it and replay
 * that shopper's AddToCart for every visitor). "Add to cart" also lands on a
 * URL WP Rocket never serves from cache (?breo-added=1, see layout.php), so the
 * event is printed on that very page instead of whenever PHP next runs.
 */
defined( 'ABSPATH' ) || exit;

/** Runs a WooCommerce hook and returns what listeners printed, for our string-built pages. */
function breo_bd_capture_hook( $hook ) {
	if ( ! has_action( $hook ) ) {
		return '';
	}
	ob_start();
	do_action( $hook );
	return (string) ob_get_clean();
}

/* ---------- add to cart that redirects: keep the browser event ---------- */

function breo_bd_force_redirect_option() {
	return 'yes';
}

add_action( 'woocommerce_add_to_cart', function () {
	if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return; // AJAX and block (Store API) adds are tracked by the plugin already
	}
	add_filter( 'pre_option_woocommerce_cart_redirect_after_add', 'breo_bd_force_redirect_option' );
}, 39 );

add_action( 'woocommerce_add_to_cart', function () {
	remove_filter( 'pre_option_woocommerce_cart_redirect_after_add', 'breo_bd_force_redirect_option' );
}, 41 );

// Store the carried-over events at the end of the request, print them on the next page.
add_action( 'init', function () {
	if ( ! class_exists( 'WC_Facebookcommerce_Utils' ) || 'yes' === get_option( 'woocommerce_cart_redirect_after_add', 'no' ) ) {
		return; // plugin absent, or it already does this itself
	}
	if ( method_exists( 'WC_Facebookcommerce_Utils', 'print_deferred_events' ) ) {
		add_action( 'wp_head', 'breo_bd_print_fb_held_events' );
	}
	if ( method_exists( 'WC_Facebookcommerce_Utils', 'save_deferred_events' ) ) {
		add_action( 'shutdown', array( 'WC_Facebookcommerce_Utils', 'save_deferred_events' ) );
	}
}, 20 );

/** Prints Facebook's carried-over events, keeping the page out of the page cache when there are any. */
function breo_bd_print_fb_held_events() {
	// Checked first: printing empties the store. (The key is the plugin's own, see WC_Facebookcommerce_Utils.)
	$held = function_exists( 'WC' ) && is_object( WC()->session )
		&& get_transient( 'facebook_for_woocommerce_async_events_' . md5( (string) WC()->session->get_customer_id() ) );
	ob_start();
	WC_Facebookcommerce_Utils::print_deferred_events();
	$out = (string) ob_get_clean();
	if ( $held || '' !== trim( $out ) ) {
		breo_bd_no_page_cache();
	}
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput -- the plugin's own tracking script
}

/** This response is for one visitor only: page caches must not keep it. */
function breo_bd_no_page_cache() {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // WP Rocket, W3TC, WP Super Cache, LiteSpeed all honour it
	}
	if ( ! headers_sent() ) {
		nocache_headers();
	}
}
