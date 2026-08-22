<?php
/**
 * A third-party plugin exploding inside a WooCommerce order hook must not take
 * the Spare Parts admin screen down with it.
 *
 * Run:  php -c php.ini wp-cli.phar --path=site eval-file test-order-crash-guard.php
 *
 * This reproduces the live incident of 2026-08-21: saving a delivery charge on
 * request 17 called wc_create_order(), "Connect for Yeamazing" threw
 * `get() on null` inside woocommerce_new_order, and the throw travelled up
 * through AUN_SP_Woo::create_order() and killed the whole page.
 *
 * The assertion that matters is simply THAT THIS FILE REACHES ITS LAST LINE.
 * If the guard regresses, the run dies mid-way with a fatal instead of printing
 * a FAIL — so the summary line is itself part of the test.
 */

if ( ! class_exists( 'AUN_SP_Woo' ) ) {
	echo "Spare-parts plugin not active on this bench.\n";
	exit( 1 );
}
if ( ! AUN_SP_Woo::is_active() ) {
	echo "WooCommerce not available on this bench - cannot test order creation.\n";
	exit( 1 );
}

global $wpdb;

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

/* ── a request with something chargeable on it ──────────────────────── */

$t_req = AUN_SP_Install::table( 'requests' );
$t_itm = AUN_SP_Install::table( 'request_items' );
$phone = '8801711000099';

$old = (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t_req WHERE phone_current = %s", $phone ) );
foreach ( $old as $oid ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t_itm WHERE request_id = %d", (int) $oid ) );
}
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_req WHERE phone_current = %s", $phone ) );

$wpdb->insert( $t_req, array(
	'ref'             => 'SP-CRASH' . wp_rand( 100, 999 ),
	'phone_current'   => $phone,
	'phone_onfile'    => $phone,
	'customer_name'   => 'Crash Guard',
	'model'           => 'A005',
	'overall_status'  => 'approved',
	'quote_total'     => 1500,
	'delivery_charge' => 100,
	'created_at'      => current_time( 'mysql' ),
	'updated_at'      => current_time( 'mysql' ),
) );
$id = (int) $wpdb->insert_id;
$wpdb->insert( $t_itm, array(
	'request_id'  => $id,
	'part_type'   => 'lcd',
	'part_label'  => 'LCD screen',
	'qty'         => 1,
	'unit_price'  => 1500,
	'line_status' => 'quoted',
) );

echo "\n== Without a misbehaving plugin ==\n";

$order_id = AUN_SP_Woo::create_order( $id );
ok( $order_id > 0, 'a normal order is built' );

echo "\n== With a plugin that fatals inside the order hook ==\n";

// Exactly the shape of the live failure: an Error (not an Exception) thrown
// from a hook WooCommerce fires while saving the order.
$boom = function () {
	throw new Error( 'simulated: Call to a member function get() on null' );
};
add_action( 'woocommerce_new_order', $boom, 1 );
add_action( 'woocommerce_before_order_object_save', $boom, 1 );

// Force a fresh build so wc_create_order() really runs.
$wpdb->update( $t_req, array( 'wc_order_id' => 0 ), array( 'id' => $id ) );

$result = AUN_SP_Woo::create_order( $id );
ok( 0 === $result, 'create_order returns 0 instead of killing the request' );

echo "\n== And the admin is told the truth ==\n";

$synced = AUN_SP_Woo::sync_delivery_charge( $id, 150 );
ok(
	'updated' !== $synced,
	'sync_delivery_charge does NOT claim success when the rebuild failed'
);

remove_action( 'woocommerce_new_order', $boom, 1 );
remove_action( 'woocommerce_before_order_object_save', $boom, 1 );

echo "\n== Recovery ==\n";
$wpdb->update( $t_req, array( 'wc_order_id' => 0 ), array( 'id' => $id ) );
ok( AUN_SP_Woo::create_order( $id ) > 0, 'once the bad plugin is gone, orders build again' );

// Tidy up.
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_itm WHERE request_id = %d", $id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_req WHERE id = %d", $id ) );

echo "\n" . $GLOBALS['g_pass'] . ' passed, ' . $GLOBALS['g_fail'] . " failed\n";
echo "(reaching this line at all is the point - a regression fatals instead)\n";
