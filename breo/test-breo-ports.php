<?php
/**
 * Bench checks for Breo Smart Delivery + Breo Live Tracking.
 * Needs breo-bench-setup.php to have run. Restores what it changes.
 */
function breo_t( $label, $ok = null, $extra = '' ) {
	static $tally = array( 'pass' => 0, 'fail' => 0 );
	if ( null === $label ) {
		return $tally;
	}
	$tally[ $ok ? 'pass' : 'fail' ]++;
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( '' !== $extra ? '  → ' . $extra : '' ) . "\n";
}
/** Run an AJAX handler; wp_die is turned into an exception so the script survives. */
function breo_ajax( $cb, $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	ob_start();
	try {
		call_user_func( $cb );
	} catch ( Exception $e ) { // phpcs:ignore
	}
	return json_decode( ob_get_clean(), true );
}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () {
	return function () {
		throw new Exception( 'die' );
	};
} );
$now = function ( $mod = '' ) {
	$d = new DateTimeImmutable( 'now', wp_timezone() );
	return $mod ? $d->modify( $mod ) : $d;
};

/* =================================================================== Smart Delivery */
$sd = null;
foreach ( $GLOBALS['wp_filter']['woocommerce_package_rates']->callbacks as $cbs ) {
	foreach ( $cbs as $cb ) {
		if ( is_array( $cb['function'] ) && $cb['function'][0] instanceof Breo_Smart_Delivery ) {
			$sd = $cb['function'][0];
		}
	}
}
breo_t( 'SD: instance hooked on woocommerce_package_rates', (bool) $sd );
$R      = new ReflectionClass( 'Breo_Smart_Delivery' );
$call   = function ( $m, ...$a ) use ( $R, $sd ) {
	$x = $R->getMethod( $m );
	$x->setAccessible( true );
	return $x->invoke( $sd, ...$a );
};
$reset  = function () use ( $R ) {
	foreach ( array( 'rules', 'zones' ) as $p ) {
		$pp = $R->getProperty( $p );
		$pp->setAccessible( true );
		$pp->setValue( null, null );
	}
};
$rules0 = get_option( 'breo_delivery_rules' );
$put    = function ( $extra ) use ( $rules0, $reset ) {
	update_option( 'breo_delivery_rules', array_merge( $rules0, $extra ) );
	$reset();
};

breo_t( 'SD: label 7–14 → 1–2 weeks', '1–2 weeks' === $call( 'days_label', 7, 14 ) );
breo_t( 'SD: label 3–5 → 3–5 days', '3–5 days' === $call( 'days_label', 3, 5 ) );
breo_t( 'SD: label 7–7 → 1 week', '1 week' === $call( 'days_label', 7, 7 ) );
breo_t( 'SD: label 0–1 → 1 day', '1 day' === $call( 'days_label', 0, 1 ) );

$put( array( '99:99' => array( 'min_days' => '', 'max_days' => '' ), '98:98' => array( 'min_days' => '', 'max_days' => 3 ) ) );
breo_t( 'SD: blank days → no rule (AUN showed "today")', null === $call( 'rule', 99, 99 ) );
$r = $call( 'rule', 98, 98 );
breo_t( 'SD: only max set → min = max', $r && 3 === $r['min_days'] && 3 === $r['max_days'] );
$put( array() );

$fri = array();
for ( $d = 0; $d <= 12; $d++ ) {
	$txt = $call( 'when', $d, $d );
	if ( false !== strpos( $txt, 'Fri,' ) ) {
		$fri[] = $d . ':' . $txt;
	}
}
breo_t( 'SD: no estimate lands on a Friday (0–12 days)', ! $fri, implode( ' | ', $fri ) );
$put( array( 'holidays' => array( array( 'start' => $now( '+1 day' )->format( 'Y-m-d' ), 'end' => $now( '+3 day' )->format( 'Y-m-d' ) ) ) ) );
$first = $call( 'when', 1, 1 );
$off   = array();
for ( $i = 1; $i <= 3; $i++ ) {
	$off[] = preg_quote( wp_date( 'D, M j', $now( "+{$i} day" )->getTimestamp() ), '/' );
}
breo_t( 'SD: 1-day estimate skips a 3-day holiday', ! preg_match( '/' . implode( '|', $off ) . '/', $first ), $first );
$put( array() );
echo '      today (' . wp_date( 'D, M j H:i' ) . '): 0d="' . $call( 'when', 0, 0, '23:59' ) . '"  1–2d="' . $call( 'when', 1, 2, '17:00' ) . '"  2–4d="' . $call( 'when', 2, 4 ) . "\"\n";

$zones = $call( 'zones_with_rules' );
breo_t( 'SD: zones with rules', count( $zones ) >= 2, implode( ', ', $zones ) );
ob_start();
$sd->product_card( wc_get_product( 1098 ) );
$html = ob_get_clean();
breo_t( 'SD: product card = message + area picker', false !== strpos( $html, 'data-breo-dlv' ) && false !== strpos( $html, 'Choose your area' ), trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) ) );
ob_start();
$sd->product_card( wc_get_product( 1098 ) );
breo_t( 'SD: card prints once per product', '' === ob_get_clean() );

// Backorder product (No.7, #1105) — restored at the end.
$bo = wc_get_product( 1105 );
$bo->set_stock_status( 'onbackorder' );
$bo->save();
ob_start();
$sd->product_card( wc_get_product( 1105 ) );
$html = ob_get_clean();
breo_t( 'SD: backorder card', false !== strpos( $html, 'is-backorder' ) && false !== strpos( $html, 'Ships in 1–2 weeks' ), trim( wp_strip_all_tags( $html ) ) );
update_post_meta( 1105, '_breo_backorder_min_days', 3 );
update_post_meta( 1105, '_breo_backorder_max_days', 5 );
$lt = $call( 'leadtime', wc_get_product( 1105 ) );
breo_t( 'SD: per-product override 3–5 days', '3–5 days' === $lt['label'], $lt['label'] );
update_post_meta( 1105, '_breo_backorder_countdown', '1' );
update_post_meta( 1105, '_breo_backorder_anchor', $now( '-2 day' )->format( 'Y-m-d' ) );
$lt = $call( 'leadtime', wc_get_product( 1105 ) );
breo_t( 'SD: countdown, 2 days in → 1–3 days', '1–3 days' === $lt['label'], $lt['label'] );
update_post_meta( 1105, '_breo_backorder_anchor', $now( '-9 day' )->format( 'Y-m-d' ) );
$lt = $call( 'leadtime', wc_get_product( 1105 ) );
breo_t( 'SD: countdown elapsed → "any day now"', $lt['imminent'] && 'any day now' === $lt['label'] );

// Packages.
$in   = wc_get_product( 1098 );
$bo   = wc_get_product( 1105 );
$dest = array( 'country' => 'BD', 'state' => 'BD-13', 'postcode' => '', 'city' => 'Dhaka', 'address' => '', 'address_1' => '', 'address_2' => '' );
$pk   = array( array(
	'contents'        => array(
		'a' => array( 'data' => $in, 'quantity' => 1, 'line_total' => 5490 ),
		'b' => array( 'data' => $bo, 'quantity' => 1, 'line_total' => 7990 ),
	),
	'contents_cost'   => 13480,
	'applied_coupons' => array(),
	'user'            => array( 'ID' => 0 ),
	'destination'     => $dest,
	'cart_subtotal'   => 13480,
) );
$out = $sd->prepare_packages( $pk );
breo_t( 'SD: mixed cart → ready + backorder packages', 2 === count( $out ) && 'in_stock' === $out[0]['package_type'] && 'backorder' === $out[1]['package_type'] );
breo_t( 'SD: split keeps WooCommerce keys', isset( $out[1]['cart_subtotal'], $out[1]['destination']['state'], $out[1]['applied_coupons'] ) );
breo_t( 'SD: split contents_cost', 5490 == $out[0]['contents_cost'] && 7990 == $out[1]['contents_cost'] ); // phpcs:ignore
breo_t( 'SD: package names', 'Ready to ship' === $sd->package_name( 'Shipping', 0, $out[0] ) && 0 === strpos( $sd->package_name( 'Shipping', 1, $out[1] ), 'On backorder' ) );
$put( array( 'split' => 'no' ) );
$one = $sd->prepare_packages( $pk );
breo_t( 'SD: split off → one backorder package, name untouched', 1 === count( $one ) && 'backorder' === $one[0]['package_type'] && 'Shipping' === $sd->package_name( 'Shipping', 0, $one[0] ) );
$put( array() );
$solo = $sd->prepare_packages( array( array_merge( $pk[0], array( 'contents' => array( 'a' => $pk[0]['contents']['a'] ) ) ) ) );
breo_t( 'SD: in-stock only → 1 package + hourly stamp', 1 === count( $solo ) && 'in_stock' === $solo[0]['package_type'] && ! empty( $solo[0]['breo_eta'] ) );

// Rates → delivery_time (what the checkout block prints).
$zone = WC_Shipping_Zones::get_zone_matching_package( $out[0] );
$m    = current( $zone->get_shipping_methods( true ) );
$rate = function ( $meth ) {
	return new WC_Shipping_Rate( 'flat_rate:' . $meth->get_instance_id(), 'Home delivery', 60, array(), 'flat_rate', $meth->get_instance_id() );
};
$r1 = $rate( $m );
$sd->rate_estimates( array( $r1->get_id() => $r1 ), $out[0] );
breo_t( 'SD: Inside Dhaka rate gets delivery_time', 0 === strpos( $r1->get_delivery_time(), 'Arrives' ), $zone->get_zone_name() . ': ' . $r1->get_delivery_time() );
$r2 = $rate( $m );
$sd->rate_estimates( array( $r2->get_id() => $r2 ), $out[1] );
breo_t( 'SD: backorder package rate text', '' !== $r2->get_delivery_time(), $r2->get_delivery_time() );
$od                         = $out[0];
$od['destination']['state'] = '';
$z2                         = WC_Shipping_Zones::get_zone_matching_package( $od );
$r3                         = $rate( current( $z2->get_shipping_methods( true ) ) );
$sd->rate_estimates( array( $r3->get_id() => $r3 ), $od );
breo_t( 'SD: Outside Dhaka rate', 'Outside Dhaka' === $z2->get_zone_name() && 0 === strpos( $r3->get_delivery_time(), 'Arrives' ), $r3->get_delivery_time() );
ob_start();
$sd->classic_rate_estimate( $r1 );
breo_t( 'SD: classic template line', false !== strpos( ob_get_clean(), 'breo-dlv-rate' ) );

// AJAX (product-page area picker).
$res = breo_ajax( array( $sd, 'ajax_estimate' ), array( 'zone_id' => $zone->get_id(), 'nonce' => wp_create_nonce( 'breo_delivery_nonce' ) ) );
breo_t( 'SD: ajax estimate for a zone', ! empty( $res['success'] ) && false !== strpos( $res['data'], 'breo-dlv__list' ), isset( $res['data'] ) && is_string( $res['data'] ) ? html_entity_decode( wp_strip_all_tags( $res['data'] ) ) : '' );
$res = breo_ajax( array( $sd, 'ajax_estimate' ), array( 'zone_id' => $zone->get_id(), 'nonce' => 'stale' ) );
breo_t( 'SD: stale nonce → bad_nonce', isset( $res['data']['code'] ) && 'bad_nonce' === $res['data']['code'] );
$res = breo_ajax( array( $sd, 'ajax_nonce' ), array() );
breo_t( 'SD: nonce mint', ! empty( $res['data']['nonce'] ) );
$res = breo_ajax( array( $sd, 'ajax_estimate' ), array( 'zone_id' => 777, 'nonce' => wp_create_nonce( 'breo_delivery_nonce' ) ) );
breo_t( 'SD: unknown zone refused', empty( $res['success'] ) );

// Order: saved on the shipping line, shown on the order page and in emails.
$item = new WC_Order_Item_Shipping();
$item->set_method_id( 'flat_rate' );
$item->set_instance_id( $m->get_instance_id() );
$item->set_method_title( 'Home delivery' );
$pkg          = $out[0];
$pkg['rates'] = array( $r1->get_id() => $r1 );
$sd->save_to_shipping_item( $item, 0, $pkg, null );
breo_t( 'SD: estimate saved on the shipping line', $item->get_meta( '_breo_delivery_estimate' ) === $r1->get_delivery_time() && false !== strpos( implode( ',', $item->get_meta( '_breo_package_contents' ) ), '× 1' ) );
$o = wc_create_order();
$o->add_product( $in, 1 );
$o->add_item( $item );
$o->set_status( 'processing' );
$o->save();
ob_start();
$sd->order_estimates( $o );
$h = ob_get_clean();
breo_t( 'SD: order page card', false !== strpos( $h, 'breo-dlv-order' ) && false !== strpos( $h, $r1->get_delivery_time() ) );
ob_start();
$sd->email_estimates( $o, false, true );
breo_t( 'SD: plain-text email block', false !== strpos( ob_get_clean(), 'DELIVERY ESTIMATE' ) );
$o->set_status( 'completed' );
$o->save();
ob_start();
$sd->order_estimates( $o );
breo_t( 'SD: hidden once the order is completed', '' === ob_get_clean() );
$o->delete( true );

// Settings sanitizer.
$s = $sd->sanitize( array(
	'3:4'      => array( 'min_days' => '', 'max_days' => '2', 'cutoff' => '25:00' ),
	'holidays' => array( array( 'start' => '2026-10-05', 'end' => '2026-10-01' ), array( 'start' => 'x' ) ),
	'split'    => 'yes',
	'bogus'    => array( 'min_days' => 1 ),
) );
breo_t( 'SD: sanitize: blank stays blank, bad cut-off dropped', '' === $s['3:4']['min_days'] && 2 === $s['3:4']['max_days'] && '' === $s['3:4']['cutoff'] );
breo_t( 'SD: sanitize: reversed holiday swapped, junk dropped', 1 === count( $s['holidays'] ) && '2026-10-01' === $s['holidays'][0]['start'] && '2026-10-05' === $s['holidays'][0]['end'] );
breo_t( 'SD: sanitize: non-zone keys dropped', ! isset( $s['bogus'] ) );
$reset();

// Restock clears the one-time per-product ETA.
update_post_meta( 1105, '_breo_backorder_last_status', 'onbackorder' );
$bo = wc_get_product( 1105 );
$bo->set_stock_status( 'instock' );
$bo->save();
breo_t( 'SD: restock clears the per-product ETA', '' === get_post_meta( 1105, '_breo_backorder_min_days', true ) && '' === get_post_meta( 1105, '_breo_backorder_countdown', true ) );
foreach ( array( 'min_days', 'max_days', 'label', 'countdown', 'anchor', 'last_status' ) as $k ) {
	delete_post_meta( 1105, '_breo_backorder_' . $k );
}

/* =================================================================== Live Tracking */
$ids   = get_option( 'breo_bench_orders' );
$T     = new ReflectionClass( 'Breo_Live_Tracking' );
$tcall = function ( $m, ...$a ) use ( $T ) {
	$x = $T->getMethod( $m );
	$x->setAccessible( true );
	return $x->invoke( null, ...$a );
};
$A     = wc_get_order( $ids['a'] );
$B     = wc_get_order( $ids['b'] );
$f     = $tcall( 'find_order', (string) $A->get_id() );
breo_t( 'TRK: find by order number', $f && $f->get_id() === $A->get_id() );
$f = $tcall( 'find_order', '#' . $A->get_id() );
breo_t( 'TRK: find by #number', $f && $f->get_id() === $A->get_id() );
$f = $tcall( 'find_order', '01711223344' );
breo_t( 'TRK: find by phone (stored "+880 1711-223344")', $f && $f->get_id() === $A->get_id() );
$f = $tcall( 'find_order', '+8801811-556677' );
breo_t( 'TRK: find by phone typed with country code', $f && $f->get_id() === $B->get_id() );
breo_t( 'TRK: short unknown input → nothing', null === $tcall( 'find_order', '12345' ) );

$h = $tcall( 'result_html', $A );
breo_t( 'TRK: live timeline from (cached) Pathao data', false !== strpos( $h, 'In Transit' ) && false !== strpos( $h, 'On the way' ) );
breo_t( 'TRK: steps done/current/todo = 2/1/2', 2 === substr_count( $h, 'class="is-done"' ) && 1 === substr_count( $h, 'class="is-current"' ) && 2 === substr_count( $h, 'class="is-todo"' ) );
breo_t( 'TRK: phone masked as 017•••••344', false !== strpos( $h, '017•••••344' ) && false === strpos( $h, '1711223344' ) );
breo_t( 'TRK: name masked as "Rahim U."', false !== strpos( $h, 'Rahim U.' ) && false === strpos( $h, 'Uddin' ) );
breo_t( 'TRK: public Pathao link', false !== strpos( $h, 'public-tracking?consignment_id=DT150926TEST' ) );
breo_t( 'TRK: no FontAwesome', false === strpos( $h, 'fa-' ) );
$h = $tcall( 'result_html', $B );
breo_t( 'TRK: no consignment → "Preparing your order"', false !== strpos( $h, 'Preparing your order' ) );

$tkey = 'breo_pathao_' . md5( 'DT150926TEST' );
$orig = get_transient( $tkey );
$exp  = array( // status => [done, current, bad, todo]
	'Delivered'        => array( 5, 0, 0, 0 ),
	'Delivery_Failed'  => array( 3, 0, 1, 1 ),
	'Pickup_Cancelled' => array( 1, 0, 1, 0 ),
	'Return'           => array( 4, 0, 1, 0 ),
	'On_Hold'          => array( 0, 1, 0, 4 ),
);
foreach ( $exp as $st => $e ) {
	set_transient( $tkey, array_merge( $orig, array( 'order_status' => $st ) ), 600 );
	$h   = $tcall( 'result_html', $A );
	$got = array( substr_count( $h, 'class="is-done"' ), substr_count( $h, 'class="is-current"' ), substr_count( $h, 'class="is-bad"' ), substr_count( $h, 'class="is-todo"' ) );
	breo_t( "TRK: status $st → steps " . implode( '/', $e ), $got === $e, implode( '/', $got ) );
}
set_transient( $tkey, $orig, DAY_IN_SECONDS );

$res = breo_ajax( array( 'Breo_Live_Tracking', 'ajax_track' ), array( 'search' => '01711223344', 'nonce' => 'stale' ) );
breo_t( 'TRK: stale nonce → bad_nonce', isset( $res['data']['code'] ) && 'bad_nonce' === $res['data']['code'] );
$res = breo_ajax( array( 'Breo_Live_Tracking', 'mint_nonce' ), array() );
breo_t( 'TRK: nonce mint', ! empty( $res['data']['nonce'] ) );
$res = breo_ajax( array( 'Breo_Live_Tracking', 'ajax_track' ), array( 'search' => '01711223344', 'nonce' => wp_create_nonce( 'breo_tracking_nonce' ) ) );
breo_t( 'TRK: ajax lookup by phone', ! empty( $res['success'] ) && false !== strpos( $res['data']['html'], 'breo-trk-res' ) );
$res = breo_ajax( array( 'Breo_Live_Tracking', 'ajax_track' ), array( 'search' => '01999999999', 'nonce' => wp_create_nonce( 'breo_tracking_nonce' ) ) );
breo_t( 'TRK: unknown phone → friendly not-found', empty( $res['success'] ) && is_string( $res['data'] ), is_string( $res['data'] ) ? $res['data'] : '' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$rl                     = 'breo_trk_rl_' . md5( '203.0.113.9_' . floor( time() / ( 5 * MINUTE_IN_SECONDS ) ) );
set_transient( $rl, 20, 300 );
$res = breo_ajax( array( 'Breo_Live_Tracking', 'ajax_track' ), array( 'search' => '01711223344', 'nonce' => wp_create_nonce( 'breo_tracking_nonce' ) ) );
breo_t( 'TRK: 21st lookup in 5 min is refused', empty( $res['success'] ) && false !== strpos( (string) $res['data'], 'Too many' ) );
$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7';
$res = breo_ajax( array( 'Breo_Live_Tracking', 'ajax_track' ), array( 'search' => '01711223344', 'nonce' => wp_create_nonce( 'breo_tracking_nonce' ) ) );
breo_t( 'TRK: a spoofed CF-Connecting-IP header does not reset the limit', empty( $res['success'] ) );
delete_transient( $rl );

$t = breo_t( null );
echo "\n" . $t['pass'] . ' passed, ' . $t['fail'] . " failed\n";
