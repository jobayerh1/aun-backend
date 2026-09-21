<?php
/**
 * AUN Smart Delivery v20.2.0 — the delivery-date engine.
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * The defect Jobayer's team fixed in the Breo port was here too: the "Today" branch
 * tested the RULE (0-0 days, before cutoff) and never looked at the date the engine
 * had just computed. The weekend/holiday skip ran, moved the date forward, and was
 * then ignored — so the shop promised same-day delivery on days it was closed.
 *
 * Holidays are used as the deterministic stand-in for "a day we are closed": the
 * plugin's own is_day_off() treats a declared holiday exactly like a Friday, so
 * these run the same branch on any weekday.
 */

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

if ( ! class_exists( 'AUN_Smart_Delivery' ) ) { echo "AUN Smart Delivery not active\n"; exit( 1 ); }

update_option( 'timezone_string', 'Asia/Dhaka' );
$tz    = wp_timezone();
$now   = new DateTime( 'now', $tz );
$today = $now->format( 'Y-m-d' );
echo "bench now: " . $now->format( 'Y-m-d H:i' ) . ' (' . $now->format( 'l' ) . ")\n";

// The plugin instantiates itself once at load, and its CONSTRUCTOR registers every
// hook. Building a second instance here (only so private methods can be reflected
// into) therefore double-registers them -- the already-live instance would split the
// cart first and this one would merely pass the result through, hiding the very
// setting section 9 exists to test. Unhook the duplicate.
$sd = new AUN_Smart_Delivery();
remove_filter( 'woocommerce_cart_shipping_packages', array( $sd, 'split_cart_by_stock_status' ) );
remove_filter( 'woocommerce_shipping_package_name',  array( $sd, 'rename_shipping_packages' ), 10 );

$calc = new ReflectionMethod( 'AUN_Smart_Delivery', 'calculate_estimate_text' );
$calc->setAccessible( true );
$cut  = new ReflectionMethod( 'AUN_Smart_Delivery', 'parse_cutoff_time' );
$cut->setAccessible( true );

function set_holidays( $h ) {
	update_option( 'aun_delivery_rules', array( 'holidays' => $h ) );
	$c = new ReflectionProperty( 'AUN_Smart_Delivery', 'rules_cache' );
	$c->setAccessible( true );
	$c->setValue( null, null );
}
function est( $calc, $sd, $rule ) {
	return trim( wp_strip_all_tags( $calc->invoke( $sd, $rule ) ) );
}

$same_day = array( 'min_days' => 0, 'max_days' => 0, 'cutoff' => '23:59' );

echo "\n=== 1. An open day still says Today ===\n";
set_holidays( array() );
$out = est( $calc, $sd, $same_day );
echo "    $out\n";
$open_today = ! in_array( (int) $now->format( 'w' ), array( 5 ), true ); // Friday = closed
if ( $open_today ) {
	ok( false !== stripos( $out, 'today' ), 'same-day delivery on an open day is still promised' );
} else {
	ok( false === stripos( $out, 'today' ), 'bench happens to be a Friday: correctly NOT today' );
}

echo "\n=== 2. A day the shop is CLOSED must not say Today ===\n";
set_holidays( array( array( 'start' => $today, 'end' => $today ) ) );
$out = est( $calc, $sd, $same_day );
echo "    $out\n";
ok( false === stripos( $out, 'today' ), 'no same-day promise on a closed day' );
ok( '' !== $out, 'it still gives the customer a date' );

echo "\n=== 3. Closed for several days running ===\n";
$d3 = ( clone $now )->modify( '+3 day' )->format( 'Y-m-d' );
set_holidays( array( array( 'start' => $today, 'end' => $d3 ) ) );
$out = est( $calc, $sd, $same_day );
echo "    $out\n";
ok( false === stripos( $out, 'today' ), 'still not today' );
// The quoted date must fall AFTER the holiday block ends.
if ( preg_match( '/(January|February|March|April|May|June|July|August|September|October|November|December) (\d{1,2})/', $out, $m ) ) {
	$quoted = DateTime::createFromFormat( 'Y F j H:i:s', $now->format( 'Y' ) . ' ' . $m[1] . ' ' . $m[2] . ' 00:00:00', wp_timezone() );
	ok( $quoted && $quoted->format( 'Y-m-d' ) > $d3, 'the date quoted is after the shutdown ends (' . ( $quoted ? $quoted->format( 'Y-m-d' ) : '?' ) . ' > ' . $d3 . ')' );
} else {
	ok( false, 'could not read a date out of: ' . $out );
}

echo "\n=== 4. Friday is a day off all year round ===\n";
// Walk forward to the next Friday and make every day until then a holiday, so the
// engine must land exactly on that Friday -- and must then skip it.
set_holidays( array() );
$friday = clone $now;
while ( (int) $friday->format( 'w' ) !== 5 ) { $friday->modify( '+1 day' ); }
$gap = (int) $now->diff( $friday )->days;
set_holidays( array( array( 'start' => $today, 'end' => ( clone $friday )->modify( '-1 day' )->format( 'Y-m-d' ) ) ) );
$out = est( $calc, $sd, $same_day );
echo "    next Friday is " . $friday->format( 'Y-m-d' ) . "; estimate: $out\n";
ok( false === stripos( $out, 'today' ), 'not today' );
ok( false === strpos( $out, $friday->format( 'F j' ) ), 'and not the Friday either — it is skipped' );

echo "\n=== 5. A cutoff that is not a real time is ignored, not obeyed ===\n";
ok( null === $cut->invoke( $sd, '25:99' ),  '"25:99" is rejected' );
ok( null === $cut->invoke( $sd, '6pm' ),    '"6pm" is rejected' );
ok( null === $cut->invoke( $sd, '' ),       'empty is rejected' );
$v = $cut->invoke( $sd, '18:30' );
ok( is_array( $v ) && 18 === $v['h'] && 30 === $v['m'], '"18:30" parses correctly' );

// A junk cutoff used to become 00:00, which is in the past all day -> every estimate
// silently pushed to tomorrow. It must now behave as though no cutoff were set.
set_holidays( array() );
$junk = array( 'min_days' => 0, 'max_days' => 0, 'cutoff' => '6pm' );
$none = array( 'min_days' => 0, 'max_days' => 0, 'cutoff' => '' );
ok( est( $calc, $sd, $junk ) === est( $calc, $sd, $none ), 'a junk cutoff behaves exactly like no cutoff' );

echo "\n=== 6. After the cutoff, it is not today ===\n";
$past = ( clone $now )->modify( '-2 hour' )->format( 'H:i' );
$out  = est( $calc, $sd, array( 'min_days' => 0, 'max_days' => 0, 'cutoff' => $past ) );
echo "    cutoff $past -> $out\n";
ok( false === stripos( $out, 'today' ), 'ordering after the cutoff does not promise today' );

echo "\n=== 7. Multi-day ranges were always right, and still are ===\n";
set_holidays( array() );
$out = est( $calc, $sd, array( 'min_days' => 1, 'max_days' => 3, 'cutoff' => '23:59' ) );
echo "    $out\n";
ok( false !== stripos( $out, 'between' ) || false !== stripos( $out, 'by ' ), 'a range still renders' );
ok( false === stripos( $out, 'today' ), 'a 1-3 day rule never says today' );

echo "\n=== 8. A pathological holiday table cannot hang the page ===\n";
// start AFTER end: the range matches nothing, but a careless loop could still spin.
set_holidays( array( array( 'start' => $today, 'end' => '2020-01-01' ) ) );
$t0  = microtime( true );
$out = est( $calc, $sd, $same_day );
$ms  = ( microtime( true ) - $t0 ) * 1000;
printf( "    inverted range resolved in %.1f ms -> %s\n", $ms, $out );
ok( $ms < 500, 'returns promptly' );

// Ten years of continuous holiday: capped, must not spin for ever.
set_holidays( array( array( 'start' => $today, 'end' => ( clone $now )->modify( '+10 year' )->format( 'Y-m-d' ) ) ) );
$t0  = microtime( true );
$out = est( $calc, $sd, $same_day );
$ms  = ( microtime( true ) - $t0 ) * 1000;
printf( "    10-year shutdown resolved in %.1f ms -> %s\n", $ms, $out );
ok( $ms < 500, 'the walk forward is bounded' );
ok( false === stripos( $out, 'today' ), 'and it certainly is not today' );

echo "\n=== 9. Mixed cart: who decides whether delivery is charged twice ===\n";
// WooCommerce charges shipping PER PACKAGE. Splitting a mixed cart therefore quotes
// the delivery fee twice. That is right when the parcels really do travel apart and
// wrong when they are held and sent together -- so it must be a decision, not a
// permanent assumption. The DEFAULT has to stay exactly as this site behaves today.
if ( ! class_exists( 'WooCommerce' ) ) {
	ok( false, 'WooCommerce not active on the bench' );
} else {
	if ( ! did_action( 'woocommerce_init' ) ) { wc_load_cart(); }
	wc_load_cart();

	$mk = function ( $name, $backorder ) {
		$p = new WC_Product_Simple();
		$p->set_name( $name ); $p->set_regular_price( 1000 ); $p->set_manage_stock( true );
		if ( $backorder ) { $p->set_stock_quantity( 0 ); $p->set_backorders( 'yes' ); $p->set_stock_status( 'onbackorder' ); }
		else { $p->set_stock_quantity( 50 ); $p->set_backorders( 'no' ); $p->set_stock_status( 'instock' ); }
		return $p->save();
	};
	// Another delivery plugin hooked to the same filter will split the cart after
	// this one declines, and section 9 then "fails" for a reason that has nothing to
	// do with AUN. (The Breo port was active on the bench and did exactly that.)
	global $wp_filter;
	$splitters = array();
	if ( ! empty( $wp_filter['woocommerce_cart_shipping_packages'] ) ) {
		foreach ( $wp_filter['woocommerce_cart_shipping_packages']->callbacks as $cbs ) {
			foreach ( $cbs as $cb ) {
				$f = $cb['function'];
				if ( is_array( $f ) && is_object( $f[0] ) ) { $splitters[] = get_class( $f[0] ); }
			}
		}
	}
	ok( 1 === count( array_unique( $splitters ) ),
		'exactly one delivery plugin owns the shipping packages (' . implode( ', ', array_unique( $splitters ) ) . ')' );

	$pin = $mk( 'SD Test In Stock', false );
	$pbo = $mk( 'SD Test Backorder', true );

	WC()->customer->set_shipping_country( 'BD' );
	WC()->customer->set_shipping_state( 'BD-13' );
	WC()->customer->set_shipping_postcode( '1200' );

	$measure = function () use ( $sd ) {
		$c = new ReflectionProperty( 'AUN_Smart_Delivery', 'rules_cache' );
		$c->setAccessible( true ); $c->setValue( null, null );
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
		return array( count( WC()->shipping()->get_packages() ), (float) WC()->cart->get_shipping_total() );
	};

	// -- default (setting absent): must behave exactly as before this version --
	update_option( 'aun_delivery_rules', array() );
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $pin, 1 );
	WC()->cart->add_to_cart( $pbo, 1 );
	list( $np, $ship ) = $measure();
	printf( "    default        : %d package(s), shipping %.2f\n", $np, $ship );
	ok( 2 === $np, 'with no setting saved, a mixed cart still splits (unchanged behaviour)' );
	$split_cost = $ship;

	// -- explicitly on --
	update_option( 'aun_delivery_rules', array( 'split_backorders' => 'yes' ) );
	list( $np2, $ship2 ) = $measure();
	printf( "    split = yes    : %d package(s), shipping %.2f\n", $np2, $ship2 );
	ok( 2 === $np2 && abs( $ship2 - $split_cost ) < 0.01, '"yes" matches the default' );

	// -- off: one parcel, delivery charged once --
	update_option( 'aun_delivery_rules', array( 'split_backorders' => 'no' ) );
	list( $np3, $ship3 ) = $measure();
	printf( "    split = no     : %d package(s), shipping %.2f\n", $np3, $ship3 );
	ok( 1 === $np3, 'turning it off keeps the cart as one parcel' );
	ok( $ship3 < $split_cost, sprintf( 'and the customer is charged once, not twice (%.2f vs %.2f)', $ship3, $split_cost ) );

	// -- an all-in-stock cart must be untouched either way --
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $pin, 2 );
	update_option( 'aun_delivery_rules', array() );
	list( $np4 ) = $measure();
	ok( 1 === $np4, 'a cart with no backorders is a single package regardless' );

	WC()->cart->empty_cart();
	wp_delete_post( $pin, true );
	wp_delete_post( $pbo, true );
}

/* cleanup */
delete_option( 'aun_delivery_rules' );
printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
