<?php
/**
 * What the customer is asked to PAY, and when they can pay it at all.
 *
 * Run:  php -c php.ini wp-cli.phar --path=site eval-file <this file>
 *
 * Spare parts 0.36.0 introduced two rules the app used to restate (badly):
 *   • a part marked Unavailable or Not-going-ahead is NOT billed;
 *   • once every priced part is with the courier, online payment must stop —
 *     on cash on delivery the courier is collecting the money, so a Pay button
 *     there asks for it a second time.
 *
 * This leans hardest on the second one. Charging twice is the kind of bug a
 * customer notices before we do.
 */

if ( ! class_exists( 'AUN_App_Services' ) ) {
	echo "AUN App API not active on this bench.\n";
	exit( 1 );
}
if ( ! method_exists( 'AUN_SP_Requests', 'payable_state' ) ) {
	echo "Spare-parts plugin is older than 0.36.0 on this bench — nothing to test.\n";
	exit( 1 );
}

global $wpdb;

// ⚠️ `wp eval-file` runs this inside a FUNCTION scope: top-level vars are not
// globals. Use $GLOBALS explicitly or the counters silently read zero and a
// green run means nothing.
$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;

function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

function a_real_id_still_links() { return 'a real consignment ID still produces a link'; }
function a_pasted_pathao_link_is_not_double_wrapped() { return 'a pasted Pathao link is reduced to its ID, not double-wrapped'; }

/* ── fixtures ─────────────────────────────────────────────────────── */

$phone = '8801711000077';
$t_req = AUN_SP_Install::table( 'requests' );
$t_itm = AUN_SP_Install::table( 'request_items' );

$old = (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t_req WHERE phone_current = %s", $phone ) );
foreach ( $old as $oid ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t_itm WHERE request_id = %d", (int) $oid ) );
}
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_req WHERE phone_current = %s", $phone ) );

/** A request with three priced parts, each line status given. */
function mk_request( $phone, $statuses, $overall = 'approved' ) {
	global $wpdb;
	$t_req = AUN_SP_Install::table( 'requests' );
	$t_itm = AUN_SP_Install::table( 'request_items' );
	$wpdb->insert( $t_req, array(
		'ref'            => 'SP-T' . wp_rand( 10000, 99999 ),
		'phone_current'  => $phone,
		'phone_onfile'   => $phone,
		'model'          => 'A005',
		'overall_status' => $overall,
		'quote_total'    => 3000,
		'created_at'     => current_time( 'mysql' ),
		'updated_at'     => current_time( 'mysql' ),
	) );
	$id = (int) $wpdb->insert_id;
	foreach ( $statuses as $i => $st ) {
		$wpdb->insert( $t_itm, array(
			'request_id'  => $id,
			'part_type'   => 'lcd',
			'part_label'  => 'Part ' . ( $i + 1 ),
			'qty'         => 1,
			'unit_price'  => 1000,
			'line_status' => $st,
		) );
	}
	return $id;
}

echo "\n== What is owed ==\n";

$id = mk_request( $phone, array( 'quoted', 'quoted', 'quoted' ) );
$s  = AUN_SP_Requests::payable_state( $id );
ok( abs( $s['money'] - 3000 ) < 0.01, 'three priced parts come to 3000' );
ok( $s['can_pay'], 'and they can be paid for online' );

$id = mk_request( $phone, array( 'quoted', 'unavailable', 'quoted' ) );
$s  = AUN_SP_Requests::payable_state( $id );
ok( abs( $s['money'] - 2000 ) < 0.01, 'a part we CANNOT SUPPLY drops out of the total' );

$id = mk_request( $phone, array( 'quoted', 'cancelled', 'cancelled' ) );
$s  = AUN_SP_Requests::payable_state( $id );
ok( abs( $s['money'] - 1000 ) < 0.01, 'parts NOT GOING AHEAD drop out of the total' );

$id = mk_request( $phone, array( 'unavailable', 'cancelled' ) );
$s  = AUN_SP_Requests::payable_state( $id );
ok( abs( $s['money'] ) < 0.01, 'nothing suppliable = nothing owed' );
ok( ! $s['can_pay'], 'and nothing to pay online' );

echo "\n== When online payment must STOP (the double-charge cases) ==\n";

$id = mk_request( $phone, array( 'dispatched', 'dispatched' ) );
$s  = AUN_SP_Requests::payable_state( $id );
ok( ! $s['can_pay'], 'ALL parts with the courier -> no online payment (COD collects it)' );
ok( abs( $s['money'] - 2000 ) < 0.01, '...but the money is still owed — to the courier, not to us' );

$id = mk_request( $phone, array( 'delivered', 'delivered' ) );
ok( ! AUN_SP_Requests::payable_state( $id )['can_pay'], 'ALL parts delivered -> no online payment' );

$id = mk_request( $phone, array( 'dispatched', 'quoted' ) );
ok( AUN_SP_Requests::payable_state( $id )['can_pay'], 'SOME still here -> payment stays open' );

$id = mk_request( $phone, array( 'quoted', 'quoted' ), 'closed' );
ok( ! AUN_SP_Requests::payable_state( $id )['can_pay'], 'a COMPLETED request cannot be paid online' );

$id = mk_request( $phone, array( 'quoted' ), 'declined' );
ok( ! AUN_SP_Requests::payable_state( $id )['can_pay'], 'a declined request cannot be paid online' );

echo "\n== What the APP is told ==\n";

$id  = mk_request( $phone, array( 'quoted', 'unavailable', 'quoted' ) );
$ref = (string) $wpdb->get_var( $wpdb->prepare( "SELECT ref FROM $t_req WHERE id = %d", $id ) );

$out   = AUN_App_Services::my_requests( $phone, 0 );
$found = null;
foreach ( (array) ( $out['spare_parts'] ?? array() ) as $row ) {
	if ( $row['ref'] === $ref ) { $found = $row; }
}

ok( is_array( $found ), 'the request reaches the app payload' );
if ( is_array( $found ) ) {
	ok(
		abs( (float) $found['payable'] - ( 2000 + (float) $found['delivery'] ) ) < 0.01,
		'payable EXCLUDES the unsuppliable part (not the stale quote_total of 3000)'
	);
	$flags = array_map( function ( $i ) { return $i['chargeable']; }, $found['items'] );
	ok( count( $flags ) === 3, 'all three parts are still SHOWN — none silently vanishes' );
	ok( false === $flags[1], 'the unsuppliable one is flagged not-chargeable' );
	ok( true === $flags[0] && true === $flags[2], 'the other two are chargeable' );
}

// A delivered request: the app must not offer payment either.
$id2  = mk_request( $phone, array( 'delivered' ), 'closed' );
$ref2 = (string) $wpdb->get_var( $wpdb->prepare( "SELECT ref FROM $t_req WHERE id = %d", $id2 ) );
$out2 = AUN_App_Services::my_requests( $phone, 0 );
foreach ( (array) ( $out2['spare_parts'] ?? array() ) as $row ) {
	if ( $row['ref'] === $ref2 ) {
		ok( false === $row['can_pay'], 'a delivered request offers NO Pay button in the app' );
	}
}

echo "
== Progress history: what the customer must NEVER read ==
";

$t_ev = AUN_SP_Install::table( 'events' );
$id3  = mk_request( $phone, array( 'quoted' ) );
$ref3 = (string) $wpdb->get_var( $wpdb->prepare( "SELECT ref FROM $t_req WHERE id = %d", $id3 ) );

// One public event, and the internal notes that used to leak beside it.
foreach ( array(
	array( 'status_change', 'Parts ordered from the factory' ),
	array( 'refund_due', 'REFUND DUE - 3,400.00 was paid online and the request is now declined.' ),
	array( 'duplicate_confirmed', 'Customer was warned this overlaps SP-0042 and chose to submit anyway' ),
	array( 'wc_order', 'Online payment order #9275 refreshed - now 16.00' ),
	array( 'sms', 'Quote SMS sent to 8801711000077' ),
	array( 'payment', 'Online payment received for order #9275 via SSLCommerz' ),
) as $e ) {
	$wpdb->insert( $t_ev, array(
		'request_id' => $id3,
		'type'       => $e[0],
		'message'    => $e[1],
		'created_at' => current_time( 'mysql' ),
	) );
}

$out3   = AUN_App_Services::my_requests( $phone, 0, 'en' );
$found3 = null;
foreach ( (array) ( $out3['spare_parts'] ?? array() ) as $row ) {
	if ( $row['ref'] === $ref3 ) { $found3 = $row; }
}
ok( is_array( $found3 ), 'the request with events reaches the app payload' );

if ( is_array( $found3 ) ) {
	$all = '';
	foreach ( (array) $found3['timeline'] as $t ) { $all .= ' ' . $t['text']; }

	ok( false !== strpos( $all, 'Parts ordered' ), 'a real progress event IS shown' );
	ok( false === strpos( $all, 'REFUND DUE' ), 'the internal REFUND DUE note is NOT shown' );
	ok( false === strpos( $all, 'SP-0042' ), 'the duplicate-overlap note is NOT shown' );
	ok( false === strpos( $all, '#9275' ), 'no WooCommerce order number reaches the customer' );
	ok( false === strpos( $all, '8801711000077' ), 'the SMS log is NOT shown' );
	ok( false !== stripos( $all, 'payment received' ), 'the payment event IS shown, re-worded' );
}

echo "
== The tracking number is a link ==
";

$id4 = mk_request( $phone, array( 'dispatched' ) );
$wpdb->query( $wpdb->prepare( "UPDATE $t_itm SET tracking_no = %s WHERE request_id = %d", 'DA210517XYZ', $id4 ) );
$ref4 = (string) $wpdb->get_var( $wpdb->prepare( "SELECT ref FROM $t_req WHERE id = %d", $id4 ) );

$out4 = AUN_App_Services::my_requests( $phone, 0 );
foreach ( (array) ( $out4['spare_parts'] ?? array() ) as $row ) {
	if ( $row['ref'] === $ref4 ) {
		$url = (string) $row['items'][0]['tracking_url'];
		ok( false !== strpos( $url, 'DA210517XYZ' ), 'the consignment number is in the tracking URL' );
		ok( 0 === strpos( $url, 'https://' ), 'and it is a real https URL' );
	}
}

// Staff paste whole links, and they type notes into the field. Spare parts
// 0.38.0 owns all of this in AUN_SP_Requests::courier_url(); the app must
// DELEGATE, so the admin badge, the website chip and the app agree exactly.
ok(
	'https://steadfast.com.bd/t/ABC123' === AUN_App_Services::courier_tracking_url( 'https://steadfast.com.bd/t/ABC123' ),
	'a pasted URL is used as-is, not wrapped in the Pathao pattern'
);
ok( '' === AUN_App_Services::courier_tracking_url( '' ), 'no tracking number = no link at all' );

// ⚠️ The one an app-side length check would get wrong: 'iwilladdlater' is 13
// characters and looks fine until it opens a Pathao page that finds nothing.
ok(
	'' === AUN_App_Services::courier_tracking_url( 'i will add later' ),
	'a NOTE typed into the tracking box produces no link at all'
);
ok(
	false !== strpos( AUN_App_Services::courier_tracking_url( 'DA240626FDJC6N' ), 'DA240626FDJC6N' ),
	a_real_id_still_links()
);
// A pasted Pathao link is reduced to its ID and rebuilt, not double-wrapped.
$from_link = AUN_App_Services::courier_tracking_url(
	'https://merchant.pathao.com/public-tracking?consignment_id=DA240626FDJC6N'
);
ok(
	false === strpos( $from_link, 'consignment_id=https' ),
	a_pasted_pathao_link_is_not_double_wrapped()
);

// The app and the plugin must return the SAME string for the same input —
// this is the assertion that fails the day someone reimplements it here.
foreach ( array( 'DA240626FDJC6N', 'i will add later', '', 'https://steadfast.com.bd/t/ABC123', 'da24 0626-FDJC6N' ) as $probe ) {
	ok(
		AUN_App_Services::courier_tracking_url( $probe ) === AUN_SP_Requests::courier_url( $probe ),
		'app and plugin agree on: ' . ( '' === $probe ? '(empty)' : $probe )
	);
}

echo "\n" . $GLOBALS['g_pass'] . ' passed, ' . $GLOBALS['g_fail'] . " failed\n";
