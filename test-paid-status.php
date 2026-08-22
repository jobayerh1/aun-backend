<?php
/**
 * Reproduces the live report: a PAID spare-parts request showing "Amount to
 * pay" again after the admin changed statuses.
 *
 * The mechanism is a custom order status. sync_from_request() moves a delivered
 * request's order to whatever aun_sp_order_status_delivered names; on a shop
 * with Advanced Shipment Tracking that is a custom status, and WC_Order::is_paid()
 * only counts processing/completed — so it flips to false and the whole system
 * concludes the money never arrived.
 */
if ( ! class_exists( 'WC_Order' ) ) { echo "WooCommerce not active\n"; return; }

// Register a custom "Delivered" status, exactly as a shipment-tracking plugin would.
register_post_status( 'wc-delivered', array( 'label' => 'Delivered', 'public' => true, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true ) );
add_filter( 'wc_order_statuses', function ( $s ) { $s['wc-delivered'] = 'Delivered'; return $s; } );

$pass = 0; $fail = 0;
$check = function ( $label, $got, $want ) use ( &$pass, &$fail ) {
	$ok = ( $got === $want ); $ok ? $pass++ : $fail++;
	printf( "%-5s %s%s\n", $ok ? 'PASS' : 'FAIL', $label,
		$ok ? '' : '   got ' . var_export( $got, true ) . ' want ' . var_export( $want, true ) );
};

$order = wc_create_order( array( 'status' => 'pending' ) );
$order->set_total( 10 );
$order->save();

echo "=== 1. money arrives ===\n";
$order->payment_complete( 'TXN-TEST-001' );
$order = wc_get_order( $order->get_id() );
$check( 'WooCommerce says paid', $order->is_paid(), true );
$check( 'has_been_paid agrees', AUN_SP_Woo::has_been_paid( $order ), true );

echo "\n=== 2. the request is delivered -> order moves to a CUSTOM status ===\n";
$order->update_status( 'delivered', 'Parts delivered.' );
$order = wc_get_order( $order->get_id() );
$check( 'order is now wc-delivered', $order->get_status(), 'delivered' );
$check( 'is_paid() has flipped to FALSE  <-- the bug', $order->is_paid(), false );
$check( 'date_paid survives the status change', (bool) $order->get_date_paid(), true );
$check( 'has_been_paid STILL true  <-- the fix', AUN_SP_Woo::has_been_paid( $order ), true );

echo "\n=== 3. what the customer's app would be told ===\n";
// customer_summary()'s 'paid' is what drives the app's green "Payment received"
// card; false sends it to the "Amount to pay" branch.
$before = $order->is_paid();
$after  = AUN_SP_Woo::has_been_paid( $order );
$check( 'old behaviour would show "Amount to pay"', $before, false );
$check( 'new behaviour shows "Payment received"', $after, true );

echo "\n=== 4. refunded orders are still 'has been paid' ===\n";
$order->update_status( 'refunded' );
$order = wc_get_order( $order->get_id() );
$check( 'refunded order still counts as paid', AUN_SP_Woo::has_been_paid( $order ), true );

echo "\n=== 5. an order that never took money is NOT paid ===\n";
$never = wc_create_order( array( 'status' => 'pending' ) );
$never->set_total( 10 );
$never->save();
$check( 'pending order is not paid', AUN_SP_Woo::has_been_paid( $never ), false );
$check( 'null is not paid', AUN_SP_Woo::has_been_paid( null ), false );

$order->delete( true );
$never->delete( true );
printf( "\n%d passed, %d failed\n", $pass, $fail );
