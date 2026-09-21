<?php
/**
 * AUN Smart Delivery v20.3.0 — the product-page "Delivery options" widget.
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * The widget listed every ENABLED method in the zone. WooCommerce's Free Shipping
 * can be set to "requires a valid free shipping coupon", and the widget knew nothing
 * about that — so every shopper was shown a free delivery option they could not
 * actually have. It also showed no prices at all.
 *
 * The fix asks each method for its rates (get_rates_for_package). An unavailable
 * method returns none and drops off the list; an available one carries its real cost.
 */

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

if ( ! class_exists( 'AUN_Smart_Delivery' ) || ! class_exists( 'WooCommerce' ) ) {
	echo "need WooCommerce + AUN Smart Delivery\n"; exit( 1 );
}
if ( ! did_action( 'woocommerce_init' ) ) { wc_load_cart(); }
wc_load_cart();

/* ── a zone with a paid method and a coupon-gated free one ── */
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Widget Test Zone' );
$zone->add_location( 'BD', 'country' );
$zone->save();
$zid = $zone->get_id();

$flat_id = $zone->add_shipping_method( 'flat_rate' );
$free_id = $zone->add_shipping_method( 'free_shipping' );
update_option( 'woocommerce_flat_rate_' . $flat_id . '_settings', array( 'title' => 'Home delivery', 'cost' => '60' ) );
update_option( 'woocommerce_free_shipping_' . $free_id . '_settings', array( 'title' => 'Free delivery', 'requires' => 'coupon' ) );

// Both methods need a delivery rule or the widget skips them by design.
update_option( 'aun_delivery_rules', array(
	$zid . ':' . $flat_id => array( 'min_days' => 1, 'max_days' => 2, 'cutoff' => '', 'pickup' => '0' ),
	$zid . ':' . $free_id => array( 'min_days' => 2, 'max_days' => 4, 'cutoff' => '', 'pickup' => '0' ),
) );
$rc = new ReflectionProperty( 'AUN_Smart_Delivery', 'rules_cache' );
$rc->setAccessible( true ); $rc->setValue( null, null );

$p = new WC_Product_Simple();
$p->set_name( 'Widget Test Projector' ); $p->set_regular_price( 5000 );
$p->set_stock_status( 'instock' );
$pid = $p->save();

echo "zone #$zid  flat #$flat_id  free #$free_id  product #$pid\n";

/**
 * Render the widget exactly as the browser asks for it.
 *
 * ajax_get_product_page_estimate() ends in wp_send_json(), which calls wp_die() --
 * that would end the whole suite on the first call. Making wp_doing_ajax() true
 * lets the AJAX die-handler filter apply, and that handler throws instead of dying,
 * so the JSON can be read back and the run continues.
 */
class Widget_Done extends Exception {}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () {
	return function () { throw new Widget_Done(); };
}, 99 );

// One instance, unhooked, reused for every call (the constructor registers hooks).
$GLOBALS['sd_widget'] = new AUN_Smart_Delivery();
remove_filter( 'woocommerce_cart_shipping_packages', array( $GLOBALS['sd_widget'], 'split_cart_by_stock_status' ) );
remove_filter( 'woocommerce_shipping_package_name',  array( $GLOBALS['sd_widget'], 'rename_shipping_packages' ), 10 );

function widget( $zid, $pid ) {
	$_POST = array(
		'action'     => 'get_product_page_delivery_estimate',
		'zone_id'    => $zid,
		'product_id' => $pid,
		'nonce'      => wp_create_nonce( 'aun_delivery_nonce' ),
	);
	$_REQUEST = $_POST;

	ob_start();
	try {
		$GLOBALS['sd_widget']->ajax_get_product_page_estimate();
	} catch ( Widget_Done $e ) { /* wp_send_json finished */ }
	$raw = ob_get_clean();

	$j = json_decode( $raw, true );
	if ( ! is_array( $j ) ) { echo "  !! not JSON: " . substr( $raw, 0, 200 ) . "\n"; return ''; }
	if ( empty( $j['success'] ) ) { echo "  !! error response: " . wp_json_encode( $j['data'] ?? null ) . "\n"; return ''; }
	return (string) ( $j['data'] ?? '' );
}

echo "\n=== 1. Free shipping that needs a coupon must NOT be offered ===\n";
$html = widget( $zid, $pid );
echo "    --- rendered ---\n" . preg_replace( '/^/m', '    ', trim( wp_strip_all_tags( $html ) ) ) . "\n";
ok( false !== strpos( $html, 'Home delivery' ), 'the paid method is listed' );
ok( false === strpos( $html, 'Free delivery' ), 'the coupon-gated free method is NOT listed' );

echo "\n=== 2. The delivery charge is shown ===\n";
ok( false !== strpos( $html, 'aun-db-opt-cost' ), 'a price element is rendered' );
ok( false !== strpos( $html, '60' ), 'it shows the flat rate cost (60)' );
ok( false !== strpos( $html, 'Get it' ), 'and still shows when it arrives' );

echo "\n=== 3. Free shipping with NO condition IS offered, as Free ===\n";
update_option( 'woocommerce_free_shipping_' . $free_id . '_settings', array( 'title' => 'Free delivery', 'requires' => '' ) );
$html2 = widget( $zid, $pid );
ok( false !== strpos( $html2, 'Free delivery' ), 'an unconditional free method is listed' );
ok( false !== strpos( $html2, 'aun-db-opt-cost--free' ), 'and is labelled Free, not 0.00' );
ok( false !== strpos( $html2, 'Home delivery' ), 'the paid method is still there beside it' );

echo "\n=== 4. Free shipping with a minimum spend ===\n";
// WC_Shipping_Free_Shipping::is_available() reads the LIVE CART subtotal
// (get_displayed_subtotal()), not the package handed to it. So on a product page
// with an empty cart it is correctly unavailable however expensive the product is —
// nothing has been added yet. That is the conservative answer and the right one
// here: the whole point of this change is to stop advertising a free option the
// customer cannot actually have.
update_option( 'woocommerce_free_shipping_' . $free_id . '_settings',
	array( 'title' => 'Free delivery', 'requires' => 'min_amount', 'min_amount' => '100' ) );

WC()->cart->empty_cart();
$html3 = widget( $zid, $pid );
ok( false === strpos( $html3, 'Free delivery' ), 'empty cart: not promised, even though the product alone exceeds the minimum' );
ok( false !== strpos( $html3, 'Home delivery' ), 'and the payable option is still offered' );

// Once the cart really does qualify, it appears — no stale "not available".
WC()->cart->add_to_cart( $pid, 1 );
WC()->cart->calculate_totals();
$html4 = widget( $zid, $pid );
ok( false !== strpos( $html4, 'Free delivery' ), 'cart over the minimum: now offered' );
ok( false !== strpos( $html4, 'aun-db-opt-cost--free' ), 'and shown as Free' );
WC()->cart->empty_cart();

echo "\n=== 5. Nothing available says so, rather than rendering an empty list ===\n";
update_option( 'woocommerce_free_shipping_' . $free_id . '_settings', array( 'title' => 'Free delivery', 'requires' => 'coupon' ) );
update_option( 'aun_delivery_rules', array() );   // no rules = no rows
$rc->setValue( null, null );
$html5 = widget( $zid, $pid );
ok( false === strpos( $html5, '<ul' ), 'no empty list element' );
ok( false !== stripos( $html5, 'checkout' ), 'a human sentence instead: ' . trim( wp_strip_all_tags( $html5 ) ) );

echo "\n=== 6. The markup is safe and self-consistent ===\n";
update_option( 'aun_delivery_rules', array(
	$zid . ':' . $flat_id => array( 'min_days' => 1, 'max_days' => 2, 'cutoff' => '', 'pickup' => '0' ),
) );
$rc->setValue( null, null );
$html6 = widget( $zid, $pid );
ok( substr_count( $html6, '<li' ) === substr_count( $html6, '</li>' ), '<li> tags balance' );
ok( substr_count( $html6, '<ul' ) === substr_count( $html6, '</ul>' ), '<ul> tags balance' );
ok( false === strpos( $html6, '<script' ), 'no script injected' );

echo "\n=== 7. The price sits with the method name, not with the estimate ===\n";
// Vertically centring the price against the whole row put it level with the middle
// of a two-line estimate on a phone, so it read as part of the wrapped sentence:
//   "Get it between September 23 and  <price> / September 24".
// Name and price share one line; the estimate flows full-width beneath it.
$html_l = widget( $zid, $pid );
ok( false !== strpos( $html_l, 'aun-db-opt-top' ), 'the name/price line exists' );
$top_at  = strpos( $html_l, 'aun-db-opt-top' );
$name_at = strpos( $html_l, 'aun-db-opt-name' );
$cost_at = strpos( $html_l, 'aun-db-opt-cost' );
$when_at = strpos( $html_l, 'aun-db-opt-when' );
ok( false !== $name_at && $name_at > $top_at, 'the name is inside that line' );
ok( false !== $cost_at && $cost_at > $name_at, 'the price follows it on the same line' );
ok( false !== $when_at && $when_at > $cost_at, 'the estimate comes after both, so it spans the full width below' );

echo "\n=== 8. A pickup method keeps its own wording ===\n";
update_option( 'aun_delivery_rules', array(
	$zid . ':' . $flat_id => array( 'min_days' => 0, 'max_days' => 1, 'cutoff' => '',
		'pickup' => '1', 'pickup_message' => 'Collect from our Dhaka showroom' ),
) );
$rc->setValue( null, null );
$html7 = widget( $zid, $pid );
ok( false !== strpos( $html7, 'Collect from our Dhaka showroom' ), 'the pickup message is used' );
ok( false !== strpos( $html7, 'fa-store' ), 'and it gets the shop icon, not the truck' );

/* ── cleanup ── */
$_POST = array(); $_REQUEST = array();
delete_option( 'aun_delivery_rules' );
delete_option( 'woocommerce_flat_rate_' . $flat_id . '_settings' );
delete_option( 'woocommerce_free_shipping_' . $free_id . '_settings' );
$zone->delete( true );
wp_delete_post( $pid, true );

printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
