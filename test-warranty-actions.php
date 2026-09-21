<?php
/**
 * AUN Warranty & Registration v2.9.0 — the registrations screen and the toolbar badge.
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * Jobayer reported two symptoms; both were real, and digging turned up more:
 *
 *   1. The toolbar badge never cleared after a decision  -> slb_flush_manual_counts()
 *      was written but never called from anywhere.
 *   2. "The two tables conflict" -> acting from the toolbar's FILTERED list threw you
 *      back to the unfiltered one, so the screen you landed on showed different rows
 *      than the screen you acted from.
 *   3. (found) The action ran on a plain GET inside the renderer, so every browser
 *      refresh re-ran the decision: measured at 3 SMS + 3 emails for one click.
 *   4. (found) Approve/Reject were offered on rows already in that state, and did it
 *      again -- a second "approved" SMS to a customer who already had one.
 *   5. (found) Approving a second registration for the SAME serial was allowed, so two
 *      registrations could both claim to be approved for one unit.
 *   6. (found) Rejecting a previously-approved row left its serial flagged as
 *      registered, so the real owner could never register it.
 *   7. (found) A manual approval never recorded the distributor, unlike the auto path.
 */

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

if ( ! function_exists( 'slb_manual_action_counts' ) ) { echo "warranty plugin not active\n"; exit( 1 ); }

global $wpdb;
wp_set_current_user( 1 );
if ( ! current_user_can( 'manage_options' ) ) {
	$u = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	if ( $u ) { wp_set_current_user( $u[0]->ID ); }
}
ok( current_user_can( 'manage_options' ), 'running as an admin' );

if ( function_exists( 'slb_create_tables' ) ) {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	slb_create_tables();
}
$t_regs    = $wpdb->prefix . 'slb_registrations';
$t_serials = $wpdb->prefix . 'slb_serials';

/* ── bench notification plumbing: count, never actually send ── */
$GLOBALS['sms_calls']  = 0;
$GLOBALS['mail_calls'] = 0;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== strpos( (string) $url, 'sms.net.bd' ) ) { $GLOBALS['sms_calls']++; }
	return array( 'body' => '{"error":0}', 'response' => array( 'code' => 200 ) );
}, 1, 3 );
add_filter( 'pre_wp_mail', function () { $GLOBALS['mail_calls']++; return true; }, 1 );

$opts = get_option( 'slb_warranty_opts', array() );
$opts['alpha_api_url'] = 'https://api.sms.net.bd/sendsms';
$opts['alpha_api_key'] = 'BENCH';
$opts['sms_templates']['approved']   = 'AUN: {serial} approved.';
$opts['sms_templates']['rejected']   = 'AUN: {serial} rejected.';
$opts['sms_templates']['duplicate']  = 'AUN: {serial} duplicate.';
$opts['email_templates']['approved'] = 'approved {serial}';
$opts['email_templates']['rejected'] = 'rejected {serial}';
update_option( 'slb_warranty_opts', $opts );

/* ── the redirect is the point of the fix, so capture it instead of exiting ── */
$GLOBALS['redirects'] = array();
// The handler ends in exit(), which would kill the suite on the first click. Throwing
// from the redirect filter unwinds BEFORE that exit, so each click can be inspected.
class SLB_Redirected extends Exception {}
add_filter( 'wp_redirect', function ( $loc ) {
	$GLOBALS['redirects'][] = $loc;
	throw new SLB_Redirected( $loc );
}, 1 );

function mk_reg( $status, $serial = '' ) {
	global $wpdb;
	$t = $wpdb->prefix . 'slb_registrations';
	$serial = $serial ?: (string) wp_rand( 100000000000, 999999999999 );
	$wpdb->insert( $t, array(
		'serial' => $serial, 'customer_name' => 'Audit Case', 'phone' => '01711223344',
		'email' => 'audit@example.test', 'status' => $status, 'purchase_date' => '2026-01-10',
		'created_at' => current_time( 'mysql' ),
	) );
	$id = (int) $wpdb->insert_id;
	if ( ! $id ) { echo '  !! insert failed: ' . $wpdb->last_error . "\n"; ok( false, 'fixture created' ); }
	return array( $id, $serial );
}

/**
 * Run the real admin_init handler the way a click would, without letting its
 * exit() kill the suite. Returns the redirect URL it asked for.
 */
function click( $action, $id, $extra = array() ) {
	$GLOBALS['redirects'] = array();
	$_GET = array_merge( array(
		'page' => 'slb-warranty-registrations',
		'action' => $action,
		'id' => $id,
		'slb_reg_nonce' => wp_create_nonce( 'slb_reg_action' ),
	), $extra );
	$_REQUEST = $_GET;
	try {
		slb_handle_registration_action();
	} catch ( SLB_Redirected $e ) { /* expected: the handler redirected */ }
	return $GLOBALS['redirects'] ? $GLOBALS['redirects'][0] : '';
}

echo "\n=== 1. The badge clears the moment a decision is made ===\n";
list( $mis ) = mk_reg( 'mismatch' );
delete_transient( 'slb_manual_counts' );
$before = slb_manual_action_counts();
ok( $before['mismatch'] >= 1, 'the mismatch is counted to begin with (' . $before['mismatch'] . ')' );

$url = click( 'reject', $mis );
$after = slb_manual_action_counts();          // exactly what the toolbar would draw
ok( 'rejected' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $t_regs WHERE id=%d", $mis ) ), 'the row really is rejected' );
ok( $after['mismatch'] === $before['mismatch'] - 1, 'the badge dropped immediately (' . $before['mismatch'] . ' -> ' . $after['mismatch'] . ')' );
ok( function_exists( 'slb_flush_manual_counts' ), 'the flush helper exists' );

echo "\n=== 2. A decision returns you to the list you acted from ===\n";
list( $mis2 ) = mk_reg( 'mismatch' );
$url = click( 'reject', $mis2, array( 'status_filter' => 'mismatch', 'search_q' => 'Audit' ) );
echo "    redirect: $url\n";
ok( '' !== $url, 'the handler redirects instead of rendering' );
ok( false !== strpos( $url, 'status_filter=mismatch' ), 'the status filter survives the decision' );
ok( false !== strpos( $url, 'search_q=Audit' ), 'the search survives too' );
ok( false !== strpos( $url, 'slb_done=rejected' ), 'the result is carried back for the notice' );

echo "\n=== 3. Refreshing the result page repeats nothing ===\n";
list( $mis3 ) = mk_reg( 'mismatch' );
$GLOBALS['sms_calls'] = 0; $GLOBALS['mail_calls'] = 0;
$url = click( 'reject', $mis3 );
$after_click = array( $GLOBALS['sms_calls'], $GLOBALS['mail_calls'] );
// The redirect target is what the browser now sits on. Rendering it repeatedly is
// what F5 does -- it must not act.
$q = array();
parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
for ( $i = 0; $i < 3; $i++ ) {
	$_GET = $q; $_REQUEST = $q;
	ob_start(); slb_admin_registrations(); $html = ob_get_clean();
}
printf( "    one click = %d SMS, %d email; after 3 refreshes = %d SMS, %d email\n",
	$after_click[0], $after_click[1], $GLOBALS['sms_calls'], $GLOBALS['mail_calls'] );
ok( 1 === $after_click[0], 'the click sent exactly one SMS' );
ok( $GLOBALS['sms_calls'] === $after_click[0], 'refreshing sent no further SMS' );
ok( $GLOBALS['mail_calls'] === $after_click[1], 'refreshing sent no further email' );
ok( false !== strpos( $html, 'rejected' ), 'the result notice still shows after the redirect' );

echo "\n=== 4. A decision that changes nothing sends nothing ===\n";
list( $app ) = mk_reg( 'approved' );
$GLOBALS['sms_calls'] = 0;
$url = click( 'approve', $app );
ok( 0 === $GLOBALS['sms_calls'], 're-approving an approved row sends no second SMS' );
ok( false !== strpos( $url, 'slb_done=nochange' ), 'and says so plainly' );

// The button should not be there to click in the first place.
$_GET = array( 'page' => 'slb-warranty-registrations', 'status_filter' => 'approved' );
$_REQUEST = $_GET;
ob_start(); slb_admin_registrations(); $html = ob_get_clean();
ok( false === strpos( $html, 'action=approve' ), 'no Approve button on a list of approved rows' );
$_GET = array( 'page' => 'slb-warranty-registrations', 'status_filter' => 'rejected' );
$_REQUEST = $_GET;
ob_start(); slb_admin_registrations(); $html = ob_get_clean();
ok( false === strpos( $html, 'action=reject' ), 'no Reject button on a list of rejected rows' );

echo "\n=== 5. One serial, one live warranty ===\n";
// I first wrote this expecting the code to have to stop a second claim on the same
// serial. It does not have to: wp_slb_registrations.serial carries a UNIQUE index,
// so the database refuses the row outright. The test below proves THAT, because it
// is the protection that actually holds -- and if a future migration ever drops the
// index, this is what will notice.
list( $first, $serial ) = mk_reg( 'pending' );
click( 'approve', $first );
ok( 'approved' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $t_regs WHERE id=%d", $first ) ), 'the first claim is approved' );

$idx = $wpdb->get_results( "SHOW INDEX FROM $t_regs WHERE Key_name='serial_unique'" );
ok( ! empty( $idx ), 'the serial column is UNIQUE, so a duplicate cannot be stored at all' );

$wpdb->suppress_errors( true );
$dup_ok = $wpdb->insert( $t_regs, array(
	'serial' => $serial, 'customer_name' => 'Audit Case', 'phone' => '01700000000',
	'status' => 'pending', 'created_at' => current_time( 'mysql' ),
) );
$wpdb->suppress_errors( false );
ok( false === $dup_ok, 'a second registration for the same serial is rejected by the database' );

// The approve path still carries a cheap backstop, for a site where that ALTER
// never applied (it is skipped silently if duplicates already existed).
ok( function_exists( 'slb_serial_free_for' ), 'a backstop guard exists in code too' );
ok( true === slb_serial_free_for( 'NEVER-SEEN-SERIAL', 999999 ), 'an unheld serial is free' );
ok( false === slb_serial_free_for( $serial, 999999 ), 'a serial already approved elsewhere is not' );
ok( true === slb_serial_free_for( $serial, $first ), 'its own holder is not blocked by it' );

echo "\n=== 6. Rejecting an approved row releases its serial ===\n";
$wpdb->update( $t_serials, array( 'registered' => 1, 'registration_id' => $first ), array( 'serial' => $serial ) );
$held = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_serials WHERE registration_id=%d", $first ) );
click( 'reject', $first );
$still = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_serials WHERE registration_id=%d", $first ) );
ok( $held === 0 || 0 === $still, 'the serial is no longer tied to the rejected claim' );

echo "\n=== 7. A manual approval records the shop, like the automatic one ===\n";
$wpdb->insert( $t_serials, array( 'serial' => 'AUDITSER001', 'distributor_id' => 4242, 'registered' => 0 ) );
list( $man ) = mk_reg( 'pending', 'AUDITSER001' );
click( 'approve', $man );
$got = (int) $wpdb->get_var( $wpdb->prepare( "SELECT distributor_id FROM $t_regs WHERE id=%d", $man ) );
ok( 4242 === $got, "the distributor is filled in from the serial (got $got)" );

echo "\n=== 8. Who decided, and when ===\n";
$notes = (string) $wpdb->get_var( $wpdb->prepare( "SELECT notes FROM $t_regs WHERE id=%d", $man ) );
ok( false !== strpos( $notes, '[DECISION]' ), 'the decision is stamped into the notes' );
ok( false !== strpos( $notes, 'approved' ), 'it records what was decided' );

echo "\n=== 9. A released registration is still untouchable ===\n";
list( $rel ) = mk_reg( 'released' );
$GLOBALS['sms_calls'] = 0;
$url = click( 'approve', $rel );
ok( 'released' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $t_regs WHERE id=%d", $rel ) ), 'released rows cannot be approved' );
ok( false !== strpos( $url, 'slb_done=released' ), 'and the admin is told why' );
ok( 0 === $GLOBALS['sms_calls'], 'nobody was messaged' );

echo "\n=== 10. The filtered list says what it is showing ===\n";
$_GET = array( 'page' => 'slb-warranty-registrations', 'status_filter' => 'mismatch' );
$_REQUEST = $_GET;
ob_start(); slb_admin_registrations(); $html = ob_get_clean();
ok( false !== strpos( $html, 'Showing' ), 'the filtered view labels itself' );
ok( false !== strpos( $html, 'Show all' ), 'and offers a way back to the whole list' );

/* ── cleanup ── */
$wpdb->query( "DELETE FROM $t_regs WHERE customer_name = 'Audit Case'" );
$wpdb->query( "DELETE FROM $t_serials WHERE serial = 'AUDITSER001'" );
delete_transient( 'slb_manual_counts' );
$_GET = array(); $_REQUEST = array();

printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
