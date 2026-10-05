<?php
/**
 * Google Analytics (GA4) shop events, sent through Site Kit's Google tag.
 *
 * Site Kit reports add_to_cart and purchase for WooCommerce, but it only sees
 * AJAX "add to cart" buttons and adds that render a page in the same request.
 * Our Buy now / Add to cart buttons redirect, so Google never heard of them;
 * nor does Site Kit send view_item or begin_checkout. This fills those gaps:
 *
 *   view_item       product page
 *   add_to_cart     the page after the add (held in the shopper's session)
 *   begin_checkout  checkout page
 *   purchase        left to Site Kit (it already reports it)
 *
 * Items use the product ID, as Site Kit's purchase does, so GA can join the
 * steps into one funnel. Nothing runs if the "Google Analytics for
 * WooCommerce" add-on is installed: it reports all of these itself.
 */
defined( 'ABSPATH' ) || exit;

function breo_bd_ga_on() {
	return defined( 'GOOGLESITEKIT_VERSION' ) && ! class_exists( 'WC_Google_Analytics_Integration' );
}

/** A GA4 item for a product. */
function breo_bd_ga_item( WC_Product $product, $qty = 1 ) {
	$id   = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
	$item = array(
		'item_id'    => $id,
		'item_name'  => wp_strip_all_tags( $product->get_name() ),
		'item_brand' => 'Breo',
		'price'      => (float) $product->get_price(),
		'quantity'   => max( 1, (int) $qty ),
	);
	$n = 1;
	foreach ( (array) wc_get_product_terms( $id, 'product_cat', array( 'number' => 5 ) ) as $term ) {
		$item[ 1 === $n ? 'item_category' : 'item_category' . $n ] = $term->name;
		$n++;
	}
	return $item;
}

/** A GA4 e-commerce payload for some items. */
function breo_bd_ga_payload( array $items ) {
	$value = 0;
	foreach ( $items as $it ) {
		$value += $it['price'] * $it['quantity'];
	}
	return array(
		'currency' => get_woocommerce_currency(),
		'value'    => round( $value, wc_get_price_decimals() ),
		'items'    => $items,
	);
}

/* ---------- add_to_cart: hold it for the page the shopper lands on ---------- */

/** How many held events this request added (those Site Kit may report itself). */
function breo_bd_ga_added_now( $add = 0 ) {
	static $n = 0;
	return $n += $add;
}

add_action( 'woocommerce_add_to_cart', function ( $key, $product_id, $qty, $variation_id = 0 ) {
	if ( ! breo_bd_ga_on() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return; // AJAX and block (Store API) adds are Site Kit's own
	}
	if ( ! function_exists( 'WC' ) || ! is_object( WC()->session ) ) {
		return;
	}
	$product = wc_get_product( $variation_id ? $variation_id : $product_id );
	if ( ! $product ) {
		return;
	}
	$held   = (array) WC()->session->get( 'breo_ga_held', array() );
	$held[] = array( 'add_to_cart', breo_bd_ga_payload( array( breo_bd_ga_item( $product, $qty ) ) ) );
	WC()->session->set( 'breo_ga_held', array_slice( $held, -10 ) );
	breo_bd_ga_added_now( 1 );
}, 10, 4 );

/** The held events to print on this page, and forgets them. */
function breo_bd_ga_take_held() {
	if ( ! function_exists( 'WC' ) || ! is_object( WC()->session ) ) {
		return array();
	}
	$held = (array) WC()->session->get( 'breo_ga_held', array() );
	if ( ! $held ) {
		return array();
	}
	WC()->session->set( 'breo_ga_held', null );
	// An add made while rendering this very page (no redirect) is one Site Kit reports itself.
	return array_slice( $held, 0, max( 0, count( $held ) - breo_bd_ga_added_now() ) );
}

/* ---------- print the page's events ---------- */

add_action( 'wp_footer', function () {
	if ( ! breo_bd_ga_on() || is_admin() ) {
		return;
	}
	$events = breo_bd_ga_take_held();
	if ( $events && function_exists( 'breo_bd_no_page_cache' ) ) {
		breo_bd_no_page_cache(); // one shopper's events: this page must not become the cached copy
	}

	if ( function_exists( 'is_product' ) && is_product() && empty( $_GET['breo-added'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- the page shown after Add to cart is not a new view
		$product = wc_get_product( get_queried_object_id() );
		if ( $product ) {
			$events[] = array( 'view_item', breo_bd_ga_payload( array( breo_bd_ga_item( $product ) ) ) );
		}
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' ) && WC()->cart && ! WC()->cart->is_empty() ) {
		$items = array();
		foreach ( WC()->cart->get_cart() as $line ) {
			if ( ! empty( $line['data'] ) && $line['data'] instanceof WC_Product ) {
				$items[] = breo_bd_ga_item( $line['data'], $line['quantity'] );
			}
		}
		if ( $items ) {
			$payload = breo_bd_ga_payload( $items );
			$coupons = WC()->cart->get_applied_coupons();
			if ( $coupons ) {
				$payload['coupon'] = implode( ',', $coupons );
			}
			$events[] = array( 'begin_checkout', $payload );
		}
	}

	if ( ! $events ) {
		return;
	}
	// Sent through Site Kit's gtagEvent (plain gtag as a fallback); retried briefly in case the tag loads late.
	echo '<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">(function(q){'
		. 'function send(){var s=window._googlesitekit,f=s&&s.gtagEvent?function(n,d){s.gtagEvent(n,d);}:(typeof window.gtag==="function"?function(n,d){window.gtag("event",n,d);}:null);'
		. 'if(!f)return false;q.forEach(function(e){f(e[0],e[1]);});return true;}'
		. 'if(!send()){var n=0,t=setInterval(function(){if(send()||++n>20)clearInterval(t);},500);}'
		. '})(' . wp_json_encode( $events, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ');</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}, 99 );
