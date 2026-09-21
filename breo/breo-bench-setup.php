<?php
/**
 * Bench setup for the Breo plugin ports (idempotent):
 * shipping zones + delivery rules, Messenger / OTP / social test options,
 * and two test orders + a cached Pathao response for tracking.
 */

// 1. Shipping zones with a flat-rate "Home delivery" each, and their delivery rules.
$want     = array(
	'Inside Dhaka'  => array( array( array( 'code' => 'BD:BD-13', 'type' => 'state' ) ), 60, array( 1, 2, '17:00' ) ),
	'Outside Dhaka' => array( array( array( 'code' => 'BD', 'type' => 'country' ) ), 120, array( 2, 4, '' ) ),
);
$existing = array();
foreach ( WC_Shipping_Zones::get_zones() as $z ) {
	$existing[ $z['zone_name'] ] = $z['id'];
}
$rules = get_option( 'breo_delivery_rules', array() );
$rules = is_array( $rules ) ? $rules : array();
$pos   = 0;
foreach ( $want as $name => $w ) {
	if ( isset( $existing[ $name ] ) ) {
		$zone = new WC_Shipping_Zone( $existing[ $name ] );
	} else {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( $name );
		$zone->set_zone_order( $pos );
		$zone->set_locations( $w[0] );
		$zone->save();
		$iid = $zone->add_shipping_method( 'flat_rate' );
		update_option( 'woocommerce_flat_rate_' . $iid . '_settings', array( 'title' => 'Home delivery', 'tax_status' => 'none', 'cost' => (string) $w[1] ) );
	}
	$pos++;
	foreach ( $zone->get_shipping_methods() as $m ) {
		$rules[ $zone->get_id() . ':' . $m->get_instance_id() ] = array( 'min_days' => $w[2][0], 'max_days' => $w[2][1], 'cutoff' => $w[2][2], 'pickup' => '0', 'pickup_message' => '' );
	}
	echo 'zone ' . $name . ' #' . $zone->get_id() . "\n";
}
$rules['split'] = 'yes';
update_option( 'breo_delivery_rules', $rules );
WC_Cache_Helper::get_transient_version( 'shipping', true );

// 2. Messenger button (breo-bd-store).
$s              = (array) get_option( 'breo_bd_settings', array() );
$s['messenger'] = 'breobd';
update_option( 'breo_bd_settings', $s );

// 3. OTP login with our own (fake) sms.net.bd key; the bench mu-plugin fakes the API.
$o             = (array) get_option( BREO_ALPHA_OTP_OPTION, array() );
$o['enabled']  = 'yes';
$o['api_key']  = 'bench-fake-key';
update_option( BREO_ALPHA_OTP_OPTION, $o );

// 4. Social login with dummy credentials so the buttons render.
$sl = get_option( 'breo_sl_options', array() );
$sl = array_merge( is_array( $sl ) ? $sl : array(), array(
	'google_enabled'       => 1,
	'google_client_id'     => '1234567890-bench.apps.googleusercontent.com',
	'google_client_secret' => 'bench',
	'facebook_enabled'     => 1,
	'facebook_app_id'      => '123456789012345',
	'facebook_app_secret'  => 'bench',
) );
update_option( 'breo_sl_options', $sl );

// 5. Test orders for tracking: A has a Pathao consignment, B has none.
$ids = get_option( 'breo_bench_orders' );
if ( ! is_array( $ids ) || ! wc_get_order( $ids['a'] ) || ! wc_get_order( $ids['b'] ) ) {
	$make = function ( $first, $last, $phone, $tid ) {
		$o = wc_create_order();
		$o->add_product( wc_get_product( 1098 ), 1 );
		$o->set_billing_first_name( $first );
		$o->set_billing_last_name( $last );
		$o->set_billing_phone( $phone );
		$o->set_billing_city( 'Dhaka' );
		$o->set_billing_state( 'BD-13' );
		$o->set_billing_country( 'BD' );
		$o->set_shipping_city( 'Mirpur, Dhaka' );
		$o->set_payment_method( 'cod' );
		$o->set_payment_method_title( 'Cash on delivery' );
		$o->calculate_totals();
		if ( $tid ) {
			$o->update_meta_data( '_pathao_consignment_id', $tid );
		}
		$o->set_status( 'processing' );
		$o->save();
		return $o->get_id();
	};
	$ids = array(
		'a' => $make( 'Rahim', 'Uddin', '+880 1711-223344', 'DT150926TEST' ),
		'b' => $make( 'Karim', 'Hossain', '01811556677', '' ),
	);
	update_option( 'breo_bench_orders', $ids );
}
set_transient( 'breo_pathao_' . md5( 'DT150926TEST' ), array(
	'order_status'            => 'In_Transit',
	'order_created_at'        => '2026-09-14 11:05:00',
	'order_status_updated_at' => '2026-09-15 09:40:00',
), DAY_IN_SECONDS );
echo 'orders: A #' . $ids['a'] . ' (Pathao DT150926TEST), B #' . $ids['b'] . " (no tracking)\n";
echo 'rules: ' . wp_json_encode( get_option( 'breo_delivery_rules' ) ) . "\n";
