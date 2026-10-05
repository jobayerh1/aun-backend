<?php
/**
 * Bench test for breo-bd-store Google Analytics events (includes/analytics.php)
 * and the page-cache guard (includes/tracking.php). Site Kit is not on the
 * bench, so its constant is faked; a throwaway product is made and removed.
 * Two runs, because "do not cache" can only be switched on once per request:
 *   eval-file Breo/test-breo-analytics.php          (events)
 *   eval-file Breo/test-breo-analytics.php cache    (cache guard)
 */
$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
function t( $ok, $label ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, "\n";
}
function breo_t_footer() {
	ob_start();
	do_action( 'wp_footer' );
	$html = ob_get_clean();
	return preg_match( '/<script data-no-optimize="1"[^>]*>\(function\(q\).*?\}\)\((.*?)\);<\/script>/s', $html, $m ) ? json_decode( $m[1], true ) : array();
}
function breo_t_names( $events ) {
	return array_map( function ( $e ) { return $e[0]; }, (array) $events );
}

if ( ! defined( 'GOOGLESITEKIT_VERSION' ) ) {
	define( 'GOOGLESITEKIT_VERSION', 'bench' );
}
wc_load_cart();
WC()->cart->get_cart(); // load it from the session now, as a real request does at wp_loaded (a later first read would wipe test adds)
WC()->cart->empty_cart();
WC()->session->set( 'breo_ga_held', null );
$mode = isset( $args[0] ) ? $args[0] : 'events';

// fixture
$term = wp_insert_term( 'Breo GA test ' . wp_generate_password( 6, false ), 'product_cat' );
$tid  = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
$p    = new WC_Product_Simple();
$p->set_name( 'Breo GA test pillow' );
$p->set_status( 'publish' );
$p->set_regular_price( '9950' );
$p->set_category_ids( array( $tid ) );
$pid = $p->save();
t( $pid > 0 && $tid > 0, 'fixture: product in its own category' );
$product = wc_get_product( $pid );

if ( 'cache' === $mode ) {
	t( ! defined( 'DONOTCACHEPAGE' ), 'start: page cacheable' );
	breo_t_footer();
	t( ! defined( 'DONOTCACHEPAGE' ), 'a page with no held events stays cacheable' );

	// Facebook's carried-over events (the plugin is not on the bench: a stand-in with the same method)
	if ( ! class_exists( 'WC_Facebookcommerce_Utils' ) ) {
		eval( 'class WC_Facebookcommerce_Utils { public static function print_deferred_events() { $k = "facebook_for_woocommerce_async_events_" . md5( (string) WC()->session->get_customer_id() ); if ( get_transient( $k ) ) { delete_transient( $k ); echo "<script>fbq(\'track\',\'AddToCart\')</script>"; } } }' ); // phpcs:ignore
	}
	ob_start();
	breo_bd_print_fb_held_events();
	t( '' === ob_get_clean() && ! defined( 'DONOTCACHEPAGE' ), 'no Facebook events: nothing printed, page stays cacheable' );
	set_transient( 'facebook_for_woocommerce_async_events_' . md5( (string) WC()->session->get_customer_id() ), array( 'x' ), 60 );
	ob_start();
	breo_bd_print_fb_held_events();
	$out = ob_get_clean();
	t( false !== strpos( $out, 'AddToCart' ), 'held Facebook AddToCart printed' );
	t( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE, 'page carrying a shopper\'s events is marked do-not-cache' );
} else {
	// item + payload shape
	$item = breo_bd_ga_item( $product, 2 );
	t( $pid === $item['item_id'] && 'Breo GA test pillow' === $item['item_name'] && 'Breo' === $item['item_brand'], 'item: id, name, brand' );
	t( 9950.0 === $item['price'] && 2 === $item['quantity'], 'item: price as a number, quantity' );
	t( isset( $item['item_category'] ) && 0 === strpos( $item['item_category'], 'Breo GA test' ), 'item: category' );
	$pay = breo_bd_ga_payload( array( $item ) );
	t( 'BDT' === $pay['currency'] || get_woocommerce_currency() === $pay['currency'], 'payload: store currency' );
	t( 19900.0 === (float) $pay['value'], 'payload: value = price x quantity' );

	// held events from an earlier request are printed once, then forgotten
	WC()->session->set( 'breo_ga_held', array( array( 'add_to_cart', $pay ) ) );
	$ev = breo_t_footer();
	t( array( 'add_to_cart' ) === breo_t_names( $ev ), 'next page: held add_to_cart printed' );
	t( isset( $ev[0][1]['items'][0]['item_id'] ) && $pid === $ev[0][1]['items'][0]['item_id'], 'next page: with its item' );
	t( ! WC()->session->get( 'breo_ga_held' ), 'held events forgotten after printing' );
	t( array() === breo_t_names( breo_t_footer() ), 'and not printed a second time' );

	// a redirecting add (form / Buy now / ?breo-buy) is held for the next page
	$before = count( (array) WC()->session->get( 'breo_ga_held', array() ) );
	WC()->cart->add_to_cart( $pid, 3 );
	$held = (array) WC()->session->get( 'breo_ga_held', array() );
	t( $before + 1 === count( $held ) && 'add_to_cart' === $held[0][0] && 3 === $held[0][1]['items'][0]['quantity'], 'add: held with quantity 3' );
	t( 29850.0 === (float) $held[0][1]['value'], 'add: value = 3 x price' );

	// ...but if this same request renders a page, Site Kit reports it itself: not twice
	t( ! in_array( 'add_to_cart', breo_t_names( breo_t_footer() ), true ), 'same-request page: add left to Site Kit' );
	t( ! WC()->session->get( 'breo_ga_held' ), 'and dropped from the session' );

	// AJAX adds are Site Kit's
	add_filter( 'wp_doing_ajax', '__return_true' );
	WC()->cart->add_to_cart( $pid, 1 );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	t( ! WC()->session->get( 'breo_ga_held' ), 'AJAX add: not held' );

	// product page: view_item
	$q = new WP_Query( array( 'post_type' => 'product', 'p' => $pid ) );
	$orig_query = $GLOBALS['wp_the_query'];
	$GLOBALS['wp_query'] = $q; // phpcs:ignore
	$GLOBALS['wp_the_query'] = $q; // phpcs:ignore
	$ev = breo_t_footer();
	t( array( 'view_item' ) === breo_t_names( $ev ), 'product page: view_item' );
	t( isset( $ev[0][1]['value'] ) && 9950.0 === (float) $ev[0][1]['value'], 'view_item: value = price' );
	$_GET['breo-added'] = '1';
	t( ! in_array( 'view_item', breo_t_names( breo_t_footer() ), true ), 'page shown right after Add to cart: no extra view_item' );
	unset( $_GET['breo-added'] );
	$GLOBALS['wp_the_query'] = $orig_query; // phpcs:ignore
	wp_reset_query();
	t( ! is_product(), 'fixture: back off the product page' );

	// checkout: begin_checkout with the cart
	add_filter( 'woocommerce_is_checkout', '__return_true' );
	$ev = breo_t_footer();
	t( array( 'begin_checkout' ) === breo_t_names( $ev ), 'checkout: begin_checkout (got: ' . implode( ',', breo_t_names( $ev ) ) . ')' );
	t( isset( $ev[0][1]['items'][0]['quantity'] ) && 4 === $ev[0][1]['items'][0]['quantity'] && 39800.0 === (float) $ev[0][1]['value'], 'begin_checkout: cart line (4 x) and value' );
	WC()->cart->empty_cart();
	t( array() === breo_t_names( breo_t_footer() ), 'empty cart: no begin_checkout' );
	remove_filter( 'woocommerce_is_checkout', '__return_true' );

	// the "Add to cart" redirect lands on the uncached URL
	$_REQUEST['breo_atc'] = '1';
	$url = apply_filters( 'woocommerce_add_to_cart_redirect', '', $product );
	unset( $_REQUEST['breo_atc'] );
	t( false !== strpos( $url, 'breo-added=1' ) && '#buy' === substr( $url, -4 ), 'Add to cart redirect: ?breo-added=1#buy' );
}

// clean up
WC()->cart->empty_cart();
WC()->session->set( 'breo_ga_held', null );
wp_delete_post( $pid, true );
if ( $tid ) {
	wp_delete_term( $tid, 'product_cat' );
}
t( ! get_post( $pid ) && ! term_exists( $tid, 'product_cat' ), 'fixtures removed' );
echo "\n{$GLOBALS['pass']} passed, {$GLOBALS['fail']} failed\n";
