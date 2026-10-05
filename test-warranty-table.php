<?php
/**
 * AUN Warranty & Registration v2.9.1 — "I rejected it, why does it still say Mismatch?"
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * No code path turns a rejected row back into a mismatch under the same id (the
 * form deletes-and-recreates, the app does too, the reconciler never touches a
 * rejected row). So the screen was either (a) reporting a reject that never
 * saved, or (b) hiding the evidence of one. Both were true of 2.9.0:
 *
 *   1. The update result was ignored: a failed write still texted the customer
 *      "rejected" and still showed "Registration #N rejected".
 *   2. The [DECISION] stamps were written to notes and displayed nowhere.
 *   3. A mismatch showed only the customer's claim — never what the ERP says.
 *   4. "Duplicate" texted the customer on one stray click.
 */

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

if ( ! function_exists( 'slb_handle_registration_action' ) ) { echo "warranty plugin not active\n"; exit( 1 ); }

global $wpdb;
wp_set_current_user( 1 );
if ( ! current_user_can( 'manage_options' ) ) {
	$u = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	if ( $u ) { wp_set_current_user( $u[0]->ID ); }
}
ok( current_user_can( 'manage_options' ), 'running as an admin' );

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
slb_create_tables();

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
foreach ( array( 'approved', 'rejected', 'duplicate' ) as $k ) {
	$opts['sms_templates'][ $k ]   = 'AUN: {serial} ' . $k . '.';
	$opts['email_templates'][ $k ] = $k . ' {serial}';
}
update_option( 'slb_warranty_opts', $opts );

class SLB_T_Redirected extends Exception {}
add_filter( 'wp_redirect', function ( $loc ) { $GLOBALS['last_redirect'] = $loc; throw new SLB_T_Redirected(); }, 1 );

function t_click( $action, $id, $extra = array() ) {
	$GLOBALS['last_redirect'] = '';
	$_GET = array_merge( array(
		'page' => 'slb-warranty-registrations', 'action' => $action, 'id' => $id,
		'slb_reg_nonce' => wp_create_nonce( 'slb_reg_action' ),
	), $extra );
	$_REQUEST = $_GET;
	try { slb_handle_registration_action(); } catch ( SLB_T_Redirected $e ) {}
	return $GLOBALS['last_redirect'];
}
function t_screen( $get ) {
	$_GET = array_merge( array( 'page' => 'slb-warranty-registrations' ), $get );
	$_REQUEST = $_GET;
	ob_start(); slb_admin_registrations(); return ob_get_clean();
}
function t_status( $id ) {
	global $wpdb;
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}slb_registrations WHERE id=%d", $id ) );
}

/* ── fixtures: an ERP serial sold via "Computer Village" as "AUN A45 Pro" ── */
$t_regs = $wpdb->prefix . 'slb_registrations';
$t_ser  = $wpdb->prefix . 'slb_serials';
$t_dist = $wpdb->prefix . 'slb_distributors';
$t_prod = $wpdb->prefix . 'slb_products';

$wpdb->query( "DELETE FROM $t_regs WHERE customer_name LIKE 'Table Test%'" );
$wpdb->query( "DELETE FROM $t_ser WHERE serial LIKE 'TBLT%'" );
$wpdb->insert( $t_dist, array( 'name' => 'Computer Village TT' ) ); $dist_id = (int) $wpdb->insert_id;
$wpdb->insert( $t_prod, array( 'name' => 'AUN A45 Pro TT' ) );     $prod_id = (int) $wpdb->insert_id;

function mk( $status, $serial, $extra = array() ) {
	global $wpdb;
	$wpdb->insert( $wpdb->prefix . 'slb_registrations', array_merge( array(
		'serial' => $serial, 'customer_name' => 'Table Test ' . $status, 'phone' => '01714776848',
		'email' => 'tt@example.test', 'status' => $status, 'purchase_date' => '2026-04-07',
		'invoice_no' => 'INV-TT-' . wp_rand( 100, 999 ), 'created_at' => current_time( 'mysql' ),
	), $extra ) );
	$id = (int) $wpdb->insert_id;
	if ( ! $id ) { echo '  !! fixture failed: ' . $wpdb->last_error . "\n"; ok( false, 'fixture' ); }
	return $id;
}

$wpdb->insert( $t_ser, array( 'serial' => 'TBLT0001', 'product_id' => $prod_id, 'distributor_id' => $dist_id, 'shipped_date' => '2026-03-01' ) );
$mis = mk( 'mismatch', 'TBLT0001', array(
	'product_model' => 'AUN U002', 'dealer_name' => 'Star Technology',
	'notes' => 'SHOP MISMATCH: selected "Star Technology" but serial TBLT0001 was sold by "Computer Village TT".',
) );
ok( $mis > 0 && $dist_id > 0 && $prod_id > 0, 'fixtures exist (no 0-id false passes)' );

echo "\n=== 1. A mismatch says WHAT mismatched, both sides ===\n";
$html = t_screen( array( 'status_filter' => 'mismatch' ) );
ok( false !== strpos( $html, 'Star Technology' ), 'the shop they chose is shown' );
ok( false !== strpos( $html, 'Computer Village TT' ), 'and the shop the ERP sold it via' );
ok( false !== strpos( $html, 'AUN A45 Pro TT' ), 'and the model the ERP sold it as' );
ok( 1 === preg_match( '/Model: they chose.*AUN U002.*ERP sold it as.*AUN A45 Pro TT/s', $html ), 'the model disagreement reads as one sentence' );

echo "\n=== 2. A decision that fails to save is reported as a failure ===\n";
// Make the next registrations UPDATE touch nothing while still "succeeding" --
// the case where update() returns 0 rather than false. Only re-reading the status
// can catch it.
$GLOBALS['break_update'] = true;
add_filter( 'query', function ( $q ) {
	if ( ! empty( $GLOBALS['break_update'] ) && 0 === stripos( ltrim( $q ), 'UPDATE' ) && false !== strpos( $q, 'slb_registrations' ) ) {
		return preg_replace( '/WHERE\s.*$/is', 'WHERE 1=0', $q );
	}
	return $q;
} );
$GLOBALS['sms_calls'] = 0; $GLOBALS['mail_calls'] = 0;
$url = t_click( 'reject', $mis );
$GLOBALS['break_update'] = false;

ok( 'mismatch' === t_status( $mis ), 'the row really is unchanged' );
ok( false !== strpos( $url, 'slb_done=dberror' ), 'the admin is told it did NOT save (' . ( preg_match( '/slb_done=([a-z]+)/', $url, $m ) ? $m[1] : '?' ) . ')' );
ok( false === strpos( $url, 'slb_done=rejected' ), 'no false "rejected" notice' );
ok( 0 === $GLOBALS['sms_calls'], 'the customer was NOT texted about a decision that did not happen' );
ok( 0 === $GLOBALS['mail_calls'], 'nor emailed' );

$q = array(); parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
$notice = t_screen( $q );
ok( false !== strpos( $notice, 'was NOT changed' ), 'the notice says so in plain words' );

echo "\n=== 3. A decision that saves is recorded where you can see it ===\n";
$GLOBALS['sms_calls'] = 0;
$url = t_click( 'reject', $mis, array( 'status_filter' => 'mismatch' ) );
ok( 'rejected' === t_status( $mis ), 'the row is rejected' );
ok( 1 === $GLOBALS['sms_calls'], 'the customer is texted exactly once' );
$html = t_screen( array( 'status_filter' => 'rejected', 'search_q' => 'TBLT0001' ) );
ok( false !== strpos( $html, 'slb-hist' ), 'the row has a History disclosure' );
ok( 1 === preg_match( '/is-decision[^>]*>\s*rejected by /', $html ), 'History shows the reject, who did it and when' );
ok( 1 === preg_match( '/History \(\d+\)\s*<span class="slb-sub">&middot; rejected by/', $html ), 'and the latest decision is visible without opening it' );

echo "\n=== 4. Nothing that texts a customer fires on a single click ===\n";
$pen  = mk( 'pending', 'TBLT0002' );
$html = t_screen( array( 'search_q' => 'TBLT0002' ) );
ok( 1 === preg_match( '/action=duplicate[^"]*"[^>]*onclick="return confirm/', $html ), 'Duplicate asks first' );
ok( 1 === preg_match( '/action=reject[^"]*"[^>]*onclick="return confirm/', $html ), 'Reject asks first' );
ok( 1 === preg_match( '/action=delete[^"]*"[^>]*onclick="return confirm/', $html ), 'Delete asks first' );
ok( 1 === preg_match( '/action=approve[^"]*">Approve/', $html ), 'a plain pending Approve stays one click (it is the common case)' );

$mis2 = mk( 'mismatch', 'TBLT0003', array( 'product_model' => 'X', 'dealer_name' => 'Y' ) );
$html = t_screen( array( 'search_q' => 'TBLT0003' ) );
ok( 1 === preg_match( '/action=approve[^"]*"[^>]*onclick="return confirm\([^)]*mismatch/', $html ), 'approving a MISMATCH names what it overrides' );

echo "\n=== 5. Search finds rows by shop and model ===\n";
$html = t_screen( array( 'search_q' => 'Star Technology' ) );
ok( false !== strpos( $html, 'TBLT0001' ), 'searching the shop name finds the row' );
$html = t_screen( array( 'search_q' => 'AUN U002' ) );
ok( false !== strpos( $html, 'TBLT0001' ), 'searching the model finds it too' );

echo "\n=== 6. A bogus filter does not masquerade as an empty table ===\n";
$html = t_screen( array( 'status_filter' => 'lol<script>' ) );
ok( false === strpos( $html, 'Nothing matches this filter' ), 'an unknown status is ignored, not run as a filter' );
ok( false === strpos( $html, 'lol' ), 'and is not echoed back' );

echo "\n=== 7. The table is one consistent shape ===\n";
$html = t_screen( array() );
preg_match( '/<thead><tr>(.*?)<\/tr><\/thead>/s', $html, $h );
$cols = substr_count( $h[1] ?? '', '<th' );
ok( 8 === $cols, "8 columns ($cols)" );
$empty = t_screen( array( 'search_q' => 'no-such-thing-zzz' ) );
ok( false !== strpos( $empty, 'colspan="8"' ), 'the empty row spans exactly those 8' );
ok( substr_count( $html, '<tr' ) === substr_count( $html, '</tr>' ), '<tr> tags balance' );
ok( substr_count( $html, '<details' ) === substr_count( $html, '</details>' ), '<details> tags balance' );

echo "\n=== 8. Delete never orphans a live row from its files ===\n";
$GLOBALS['break_delete'] = true;
add_filter( 'query', function ( $q ) {
	if ( ! empty( $GLOBALS['break_delete'] ) && 0 === stripos( ltrim( $q ), 'DELETE' ) && false !== strpos( $q, 'slb_registrations' ) ) {
		return preg_replace( '/WHERE\s.*$/is', 'WHERE 1=0', $q );
	}
	return $q;
} );
$url = t_click( 'delete', $pen );
$GLOBALS['break_delete'] = false;
ok( 'pending' === t_status( $pen ), 'a failed delete leaves the row' );
ok( false !== strpos( $url, 'slb_done=dberror' ), 'and says it failed instead of "deleted"' );

/* ── cleanup ── */
$wpdb->query( "DELETE FROM $t_regs WHERE customer_name LIKE 'Table Test%'" );
$wpdb->query( "DELETE FROM $t_ser WHERE serial LIKE 'TBLT%'" );
$wpdb->delete( $t_dist, array( 'id' => $dist_id ) );
$wpdb->delete( $t_prod, array( 'id' => $prod_id ) );
delete_transient( 'slb_manual_counts' );
$_GET = array(); $_REQUEST = array();

printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
