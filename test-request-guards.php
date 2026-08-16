<?php
/**
 * The duplicate guards on repair and spare-parts requests, and the courier
 * tracking recorder.
 *
 * Run:  php -c php.ini wp-cli.phar --path=site eval-file <this file>
 *
 * These exist because the guards are the only thing standing between one
 * customer and twenty identical rows in the Repairs list. They are also brand
 * new, so this hunts for holes rather than merely confirming the happy path.
 */

if ( ! class_exists( 'AUN_App_Services' ) ) {
	echo "AUN App API not active on this bench.\n";
	exit( 1 );
}

global $wpdb;

// ⚠️ `wp eval-file` runs this file inside a FUNCTION scope, so variables
// declared at the top are NOT globals, and `global $pass` inside a helper binds
// to a different, empty variable. The first run printed a screen of PASS lines
// and then "0 passed, 0 failed". $GLOBALS is explicit and works either way.
$GLOBALS['g_pass']  = 0;
$GLOBALS['g_fail']  = 0;
$GLOBALS['g_holes'] = array();

function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}
function hole( $label ) {
	$GLOBALS['g_holes'][] = $label;
	echo "  !! HOLE  $label\n";
}

/* ── fixtures ─────────────────────────────────────────────────────── */

$phone = '8801711000001';
$uid   = username_exists( 'guardtest' );
if ( ! $uid ) {
	$uid = wp_create_user( 'guardtest', wp_generate_password(), 'guardtest@example.test' );
}
$uid = (int) $uid;

$t_rep = AUN_App_Services::repairs_table();
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_rep WHERE user_id = %d", $uid ) );

if ( AUN_App_Services::parts_available() ) {
	$t_sp = AUN_SP_Install::table( 'requests' );
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t_sp WHERE phone_current = %s", $phone ) );
}

function mk_repair( $uid, $phone, $serial, $status = 'submitted' ) {
	global $wpdb;
	$t = AUN_App_Services::repairs_table();
	$wpdb->insert( $t, array(
		'ref'           => 'RP-T' . wp_rand( 1000, 9999 ) . wp_rand( 10, 99 ),
		'user_id'       => $uid,
		'customer_name' => 'Guard Test',
		'phone'         => $phone,
		'address'       => 'Test address, Dhaka',
		'serial'        => $serial,
		'model'         => 'AUN A45 Pro',
		'issue'         => 'No display at all, tested another cable.',
		'status'        => $status,
		'created_at'    => current_time( 'mysql' ),
		'updated_at'    => current_time( 'mysql' ),
	) );
	return (int) $wpdb->insert_id;
}

echo "\n=== REPAIR duplicate guard ===\n";

mk_repair( $uid, $phone, 'SN-AAA-111' );

ok( null !== AUN_App_Services::open_repair_for( $uid, 'SN-AAA-111' ),
	'an open repair for this device is found' );

ok( null === AUN_App_Services::open_repair_for( $uid, 'SN-BBB-222' ),
	'a DIFFERENT projector is not blocked (two devices, two repairs)' );

ok( null === AUN_App_Services::open_repair_for( $uid + 99999, 'SN-AAA-111' ),
	'another customer with the same serial is unaffected' );

// Terminal statuses must release the lock, or a customer whose repair finished
// last year could never send the same projector again.
foreach ( array( 'closed', 'rejected', 'returned' ) as $done ) {
	$wpdb->query( $wpdb->prepare( "UPDATE $t_rep SET status = %s WHERE user_id = %d AND serial = 'SN-AAA-111'", $done, $uid ) );
	ok( null === AUN_App_Services::open_repair_for( $uid, 'SN-AAA-111' ),
		"a '$done' repair no longer blocks a new one" );
}

// …and every in-flight status must still hold it.
foreach ( array( 'submitted', 'approved', 'received', 'repairing', 'ready' ) as $live ) {
	$wpdb->query( $wpdb->prepare( "UPDATE $t_rep SET status = %s WHERE user_id = %d AND serial = 'SN-AAA-111'", $live, $uid ) );
	ok( null !== AUN_App_Services::open_repair_for( $uid, 'SN-AAA-111' ),
		"a '$live' repair still blocks a second request" );
}

// ── The hole hunt ──
// Regression: a blank serial used to mean NO GUARD AT ALL — and a worn-off
// serial label is exactly when a customer submits twice.
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_rep WHERE user_id = %d", $uid ) );
mk_repair( $uid, $phone, '' );  // a request with NO serial
ok( null !== AUN_App_Services::open_repair_for( $uid, '', 'AUN A45 Pro' ),
	'a repair with NO SERIAL is still guarded, by model' );
ok( null !== AUN_App_Services::open_repair_for( $uid, '' ),
	'…and with no model either, by customer' );
ok( null === AUN_App_Services::open_repair_for( $uid, '', 'AUN U002 Pro' ),
	'a DIFFERENT model with no serial is not blocked' );
ok( null === AUN_App_Services::open_repair_for( $uid, 'SN-REAL-1' ),
	'a serial-less repair does not block a request that HAS a serial' );

// Case / whitespace: the app sends what the customer's device row holds, but
// an ERP-sourced serial and a typed one can differ in case.
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_rep WHERE user_id = %d", $uid ) );
mk_repair( $uid, $phone, 'sn-ccc-333' );
$mixed = AUN_App_Services::open_repair_for( $uid, 'SN-CCC-333' );
if ( null === $mixed ) {
	hole( 'serial matching is CASE-SENSITIVE — "sn-ccc-333" and "SN-CCC-333" count as two devices' );
} else {
	ok( true, 'serial matching survives a case difference' );
}
$spaced = AUN_App_Services::open_repair_for( $uid, ' sn-ccc-333 ' );
ok( null !== $spaced, 'surrounding whitespace does not defeat the guard' );

echo "\n=== SPARE PARTS duplicate guard ===\n";

if ( ! AUN_App_Services::parts_available() ) {
	echo "  (spare-parts plugin inactive — skipped)\n";
} else {
	$t_sp = AUN_SP_Install::table( 'requests' );
	$mk   = function ( $serial, $status = 'submitted' ) use ( $wpdb, $t_sp, $phone ) {
		$wpdb->insert( $t_sp, array(
			'ref'             => 'SP-T' . wp_rand( 1000, 9999 ),
			'source_type'     => 'app',
			'source_order'    => $serial,
			'model'           => 'AUN A45 Pro',
			'customer_name'   => 'Guard Test',
			'phone_current'   => $phone,
			'phone_onfile'    => $phone,
			'address_current' => 'Test address',
			'channel'         => 'app',
			'overall_status'  => $status,
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	};

	$mk( 'SN-AAA-111' );

	ok( null !== AUN_App_Services::open_parts_request( $phone, 'SN-AAA-111' ),
		'an open parts request for this device is found' );

	ok( null === AUN_App_Services::open_parts_request( $phone, 'SN-BBB-222' ),
		'a different projector may have its own parts request' );

	ok( null === AUN_App_Services::open_parts_request( '8801799999999', 'SN-AAA-111' ),
		'another customer is unaffected' );

	// The plugin's own terminal list must be honoured — including the two it
	// gained after this guard was written elsewhere.
	foreach ( AUN_SP_Requests::TERMINAL_STATES as $done ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $t_sp SET overall_status = %s WHERE phone_current = %s", $done, $phone ) );
		ok( null === AUN_App_Services::open_parts_request( $phone, 'SN-AAA-111' ),
			"a '$done' request no longer blocks a new one" );
	}
	foreach ( array( 'submitted', 'in_progress', 'quote_sent', 'approved', 'waiting_customer', 'ready' ) as $live ) {
		$wpdb->query( $wpdb->prepare( "UPDATE $t_sp SET overall_status = %s WHERE phone_current = %s", $live, $phone ) );
		ok( null !== AUN_App_Services::open_parts_request( $phone, 'SN-AAA-111' ),
			"a '$live' request still blocks a second" );
	}

	// Phone format: the app writes 8801…, the website 01…. A guard that only
	// understands one of them is no guard at all.
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t_sp WHERE phone_current = %s", $phone ) );
	$wpdb->insert( $t_sp, array(
		'ref' => 'SP-TWEB1', 'source_type' => 'web', 'source_order' => 'SN-AAA-111',
		'model' => 'AUN A45 Pro', 'customer_name' => 'Guard Test',
		'phone_current' => '01711000001', 'phone_onfile' => '01711000001',
		'address_current' => 'x', 'channel' => 'web', 'overall_status' => 'submitted',
		'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ),
	) );
	$cross = AUN_App_Services::open_parts_request( $phone, 'SN-AAA-111' );
	if ( null === $cross ) {
		hole( 'a request filed on the WEBSITE (01…) is invisible to the app guard (8801…) — the customer can duplicate across channels' );
	} else {
		ok( true, 'a website-filed request is seen by the app guard (phone formats reconciled)' );
	}
	$wpdb->query( "DELETE FROM $t_sp WHERE ref = 'SP-TWEB1'" );

	// No serial at all.
	$mk( '' );
	$noser = AUN_App_Services::open_parts_request( $phone, '' );
	ok( null !== $noser, 'with no serial the guard falls back to "any open request for this phone"' );
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t_sp WHERE phone_current = %s", $phone ) );
}

echo "\n=== COURIER TRACKING ===\n";

$wpdb->query( $wpdb->prepare( "DELETE FROM $t_rep WHERE user_id = %d", $uid ) );
$rid = mk_repair( $uid, $phone, 'SN-TRK-777', 'approved' );
$ref = (string) $wpdb->get_var( $wpdb->prepare( "SELECT ref FROM $t_rep WHERE id = %d", $rid ) );

$r = AUN_App_Services::set_repair_tracking( $uid, $ref, 'Pathao', 'PTH123456789' );
ok( ! empty( $r['ok'] ), 'a valid tracking number is accepted' );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_rep WHERE id = %d", $rid ) );
ok( 'PTH123456789' === (string) $row->courier_tracking, 'it is stored on the repair row' );
ok( 'Pathao' === (string) $row->courier_name, 'the courier name is stored' );
ok( ! empty( $row->courier_at ), 'the time it arrived is recorded' );

$r = AUN_App_Services::set_repair_tracking( $uid, $ref, '', '12' );
ok( empty( $r['ok'] ) && 'invalid' === $r['code'], 'a too-short number is refused' );

$r = AUN_App_Services::set_repair_tracking( $uid + 99999, $ref, 'Pathao', 'PTH999' );
ok( empty( $r['ok'] ) && 'not_found' === $r['code'],
	'ANOTHER customer cannot attach a tracking number to this repair' );

$r = AUN_App_Services::set_repair_tracking( $uid, 'RP-NOPE-0000', 'Pathao', 'PTH999' );
ok( empty( $r['ok'] ) && 'not_found' === $r['code'], 'an unknown ref is refused' );

// Should a finished repair still accept one?
// ⚠️ Owner-reported: the app let the same customer send a tracking number over
// and over. The app fix is to trust the SERVER's copy rather than local state,
// so the payload must actually carry it back.
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_rep WHERE id = %d", $rid ) );
$m   = new ReflectionMethod( 'AUN_App_Services', 'repair_entry' );
$m->setAccessible( true );
$payload = $m->invoke( null, $row, null );
ok( isset( $payload['tracking'] ) && 'PTH123456789' === $payload['tracking'],
	'the payload carries the tracking number back, so the app can hide the button' );
ok( isset( $payload['courier'] ) && 'Pathao' === $payload['courier'],
	'and the courier name' );

$fresh = mk_repair( $uid, $phone, 'SN-TRK-778', 'approved' );
$row2  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_rep WHERE id = %d", $fresh ) );
$p2    = $m->invoke( null, $row2, null );
ok( '' === (string) $p2['tracking'],
	'a repair with no tracking yet reports an empty string, so the button still shows' );
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_rep WHERE id = %d", $fresh ) );

// Regression: a finished repair used to accept one, overwriting the number
// that actually tracked the parcel we received.
foreach ( array( 'closed', 'returned', 'rejected' ) as $done ) {
	$wpdb->query( $wpdb->prepare( "UPDATE $t_rep SET status = %s WHERE id = %d", $done, $rid ) );
	$r = AUN_App_Services::set_repair_tracking( $uid, $ref, 'Pathao', 'PTH-AFTER-' . $done );
	ok( empty( $r['ok'] ) && 'already_finished' === $r['code'],
		"a '$done' repair refuses a new tracking number" );
}
$row = $wpdb->get_row( $wpdb->prepare( "SELECT courier_tracking FROM $t_rep WHERE id = %d", $rid ) );
ok( 'PTH123456789' === (string) $row->courier_tracking,
	'the original tracking number was NOT overwritten' );

/* ── cleanup ──────────────────────────────────────────────────────── */
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_rep WHERE user_id = %d", $uid ) );
if ( AUN_App_Services::parts_available() ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM " . AUN_SP_Install::table( 'requests' ) . " WHERE phone_current = %s", $phone ) );
}

echo "\n{$GLOBALS['g_pass']} passed, {$GLOBALS['g_fail']} failed\n";
if ( $GLOBALS['g_holes'] ) {
	echo "\n" . count( $GLOBALS['g_holes'] ) . " HOLE(S) STILL OPEN:\n";
	foreach ( $GLOBALS['g_holes'] as $h ) {
		echo "  - $h\n";
	}
}
exit( ( $GLOBALS['g_fail'] > 0 || $GLOBALS['g_holes'] ) ? 1 : 0 );
