<?php
/**
 * Warranty 2.9.2 + app-api 1.115.1 — a WRONG serial must not become a warranty.
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * Found auditing registration #12 (serial "wrong", shop "Star Technology" that is
 * not in the dropdown). Measured on the REAL intake before the fix:
 *   A. a shop that is not in the list was accepted and saved
 *   B. same phone, same wrong serial, trying shops -> auto-APPROVED on the third,
 *      serial marked registered to that person, earlier mismatch wiped from notes
 *   C. shop left blank -> shop check skipped -> auto-APPROVED
 * The app let a customer "correct" their own mismatch into an auto-approval too.
 */

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

if ( ! class_exists( 'WPCF7_Submission' ) ) {
	class WPCF7_Submission {
		public static $posted = array();
		public static function get_instance() { return new self(); }
		public function get_posted_data() { return self::$posted; }
		public function uploaded_files() { return array(); }
	}
}
if ( ! class_exists( 'WPCF7_FormTag' ) ) {
	class WPCF7_FormTag {
		public $name; private $req;
		public function __construct( $t ) { $this->name = is_array( $t ) ? $t['name'] : $t->name; $this->req = is_array( $t ) ? ! empty( $t['req'] ) : false; }
		public function is_required() { return $this->req; }
	}
}
class WS_Result { public $errors = array(); public function invalidate( $tag, $msg ) { $this->errors[] = $msg; } }
class WS_Form   { public function id() { return 0; } }

global $wpdb;
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
slb_create_tables();
$t_regs = $wpdb->prefix . 'slb_registrations';
$t_ser  = $wpdb->prefix . 'slb_serials';
$t_dist = $wpdb->prefix . 'slb_distributors';
$t_prod = $wpdb->prefix . 'slb_products';

$GLOBALS['sms'] = array();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== strpos( (string) $url, 'sms.net.bd' ) ) { $GLOBALS['sms'][] = $url; }
	return array( 'body' => '{"error":0}', 'response' => array( 'code' => 200 ) );
}, 1, 3 );
add_filter( 'pre_wp_mail', '__return_true', 1 );

$opts = get_option( 'slb_warranty_opts', array() );
$opts['cf7_form_id'] = 0;
$opts['auto_approve_rule'] = 'match_serial_and_distributor';
$opts['alpha_api_key'] = 'BENCH';
$opts['sms_templates']['approved'] = 'AUN: {serial} approved.';
update_option( 'slb_warranty_opts', $opts );

function ws_clean() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->prefix}slb_registrations WHERE serial LIKE 'WSER%'" );
	$wpdb->query( "DELETE FROM {$wpdb->prefix}slb_serials WHERE serial LIKE 'WSER%'" );
	$wpdb->query( "DELETE FROM {$wpdb->prefix}slb_distributors WHERE name LIKE '% WS'" );
	$wpdb->query( "DELETE FROM {$wpdb->prefix}slb_products WHERE name LIKE '% WS'" );
	delete_transient( 'slb_manual_counts' );
}
ws_clean();

$wpdb->insert( $t_dist, array( 'name' => 'Islam Computers WS' ) );   $islam   = (int) $wpdb->insert_id;
$wpdb->insert( $t_dist, array( 'name' => 'Computer Village WS' ) );  $village = (int) $wpdb->insert_id;
$wpdb->insert( $t_prod, array( 'name' => 'AUN U002 WS' ) );          $u002    = (int) $wpdb->insert_id;
$shipped = date( 'Y-m-d', current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS );
foreach ( array( 'WSER0001', 'WSER0002', 'WSER0003', 'WSER0004', 'WSER0005', 'WSER0006', 'WSER0007' ) as $sn ) {
	$wpdb->insert( $t_ser, array( 'serial' => $sn, 'product_id' => $u002, 'distributor_id' => $islam, 'shipped_date' => $shipped ) );
}
ok( $islam && $village && $u002, 'fixtures exist' );

function ws_submit( $serial, $dealer, $model = 'AUN U002 WS', $phone = '01714776848' ) {
	WPCF7_Submission::$posted = array(
		'serial-number' => $serial, 'full-name' => 'Wrong Serial Test', 'phone' => $phone,
		'email' => 'ws@example.test', 'model' => $model, 'invoice-number' => 'INV-WS-1',
		'purchase-date' => date( 'Y-m-d', current_time( 'timestamp' ) - 5 * DAY_IN_SECONDS ),
		'dealer-name' => $dealer,
	);
	slb_cf7_process_warranty_registration_v2( new WS_Form() );
}
function ws_row( $serial ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}slb_registrations WHERE serial=%s", $serial ) );
}
function ws_registered( $serial ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT registered FROM {$wpdb->prefix}slb_serials WHERE serial=%s", $serial ) );
}

echo "\n=== 0. A genuine customer who gets it right is still approved instantly ===\n";
ws_submit( 'WSER0007', 'Islam Computers WS' );
$r = ws_row( 'WSER0007' );
ok( $r && 'approved' === $r->status, 'correct shop + model on the first try: approved (' . ( $r ? $r->status : '-' ) . ')' );
ok( 1 === ws_registered( 'WSER0007' ), 'and the serial is registered to them' );

echo "\n=== 1. The dropdown can only send shops that are on the list ===\n";
$res = slb_cf7_validate_distributor_select( new WS_Result(), array( 'name' => 'dealer-name', 'req' => true ) );
$_POST['dealer-name'] = 'Star Technology';
$res = slb_cf7_validate_distributor_select( new WS_Result(), array( 'name' => 'dealer-name', 'req' => true ) );
ok( 1 === count( $res->errors ), 'a shop that is not on the list is refused: "' . ( $res->errors[0] ?? '' ) . '"' );
$_POST['dealer-name'] = 'Islam Computers WS';
$res = slb_cf7_validate_distributor_select( new WS_Result(), array( 'name' => 'dealer-name', 'req' => true ) );
ok( 0 === count( $res->errors ), 'a shop on the list is accepted' );
$_POST['model'] = 'AUN Imaginary 9000';
$res = slb_cf7_validate_product_select( new WS_Result(), array( 'name' => 'model', 'req' => true ) );
ok( 1 === count( $res->errors ), 'a model that is not on the list is refused' );
unset( $_POST['dealer-name'], $_POST['model'] );

echo "\n=== 2. ...and if one still arrives (cached page, edited request), a person sees it ===\n";
ws_submit( 'WSER0001', 'Star Technology' );
$r = ws_row( 'WSER0001' );
ok( $r && 'approved' !== $r->status, 'not approved (' . ( $r ? $r->status : '-' ) . ')' );
ok( $r && false !== strpos( $r->notes, '[HELD FOR REVIEW]' ), 'carries the review marker, so the nightly re-check leaves it alone' );
ok( $r && false !== strpos( $r->notes, 'not in the shop list' ), 'and says why: the shop is not on the list' );

echo "\n=== 3. A wrong serial cannot become a warranty by trying shops ===\n";
ws_submit( 'WSER0002', 'Computer Village WS' );         // wrong shop -> mismatch
$first = ws_row( 'WSER0002' );
ok( $first && 'mismatch' === $first->status, 'first try: mismatch' );
$GLOBALS['sms'] = array();
ws_submit( 'WSER0002', 'Islam Computers WS' );          // "guess" the right shop
$r = ws_row( 'WSER0002' );
ok( 'approved' !== $r->status, 'the corrected try is NOT auto-approved (' . $r->status . ')' );
ok( 0 === ws_registered( 'WSER0002' ), 'the serial is NOT registered to them' );
ok( false !== strpos( $r->notes, '[HELD FOR REVIEW]' ), 'it is held for a person' );
ok( false !== strpos( $r->notes, 'Re-submitted after a mismatch' ), 'with the reason stated' );
ok( false !== strpos( $r->notes, 'Computer Village WS' ) && false !== strpos( $r->notes, 'Islam Computers WS' ), 'naming both the shop they first gave and the one they switched to' );
ok( false !== strpos( $r->notes, 'SHOP MISMATCH' ), 'and the ORIGINAL mismatch is still in the record, not wiped' );
ok( false === strpos( implode( ' ', $GLOBALS['sms'] ), 'approved' ), 'no approval SMS went out' );

echo "\n=== 4. A blank shop or model is not a pass ===\n";
ws_submit( 'WSER0003', '' );
$r = ws_row( 'WSER0003' );
ok( $r && 'approved' !== $r->status && false !== strpos( $r->notes, 'No shop was given' ), 'blank shop: held, reason stated' );
ws_submit( 'WSER0004', 'Islam Computers WS', '' );
$r = ws_row( 'WSER0004' );
ok( $r && 'approved' !== $r->status && false !== strpos( $r->notes, 'No model was given' ), 'blank model: held, reason stated' );

echo "\n=== 5. The nightly re-check does not undo any of these holds ===\n";
$approved_now = slb_reconcile_pending_registrations( null, 200 );
foreach ( array( 'WSER0001', 'WSER0002', 'WSER0003', 'WSER0004' ) as $sn ) {
	$r = ws_row( $sn );
	ok( 'approved' !== $r->status, "$sn still not approved after the reconciler ran ({$r->status})" );
}

echo "\n=== 6. The app: correcting your own mismatch goes to a person too ===\n";
if ( class_exists( 'AUN_App_Warranty' ) ) {
	$app = function ( $dist ) use ( $u002 ) {
		return AUN_App_Warranty::register_device( array(
			'user_id' => 0, 'serial' => 'WSER0005', 'customer_name' => 'App WS',
			'phone' => '8801799000777', 'account_phone' => '8801799000777', 'email' => '',
			'model' => 'AUN U002 WS', 'purchase_date' => date( 'Y-m-d', current_time( 'timestamp' ) - 5 * DAY_IN_SECONDS ),
			'distributor_id' => $dist, 'dealer_name' => '',
			'invoice_no' => 'APP-WS-1', 'invoice_file' => 'https://aun-projector.com.bd/wp-content/uploads/bench-invoice.jpg',
		) );
	};
	$a1 = $app( $village );
	$r1 = ws_row( 'WSER0005' );
	ok( $r1 && 'mismatch' === $r1->status, 'app, wrong shop: mismatch (' . ( $r1 ? $r1->status : wp_json_encode( $a1 ) ) . ')' );
	$a2 = $app( $islam );
	$r2 = ws_row( 'WSER0005' );
	ok( $r2 && 'approved' !== $r2->status, 'app, "corrected" to the right shop: NOT approved (' . ( $r2 ? $r2->status : wp_json_encode( $a2 ) ) . ')' );
	ok( $r2 && false !== strpos( $r2->notes, '[HELD FOR REVIEW] Corrected after a mismatch' ), 'held, with the reason' );
	ok( 0 === ws_registered( 'WSER0005' ), 'serial not registered' );

	// A genuine app customer who picks the right shop first time is unaffected.
	$ok = AUN_App_Warranty::register_device( array(
		'user_id' => 0, 'serial' => 'WSER0006', 'customer_name' => 'App WS ok',
		'phone' => '8801799000778', 'account_phone' => '8801799000778', 'email' => '',
		'model' => 'AUN U002 WS', 'purchase_date' => date( 'Y-m-d', current_time( 'timestamp' ) - 5 * DAY_IN_SECONDS ),
		'distributor_id' => $islam, 'dealer_name' => '',
		'invoice_no' => 'APP-WS-2', 'invoice_file' => 'https://aun-projector.com.bd/wp-content/uploads/bench-invoice.jpg',
	) );
	$r3 = ws_row( 'WSER0006' );
	ok( $r3 && 'approved' === $r3->status, 'app, right shop first time: approved (' . ( $r3 ? $r3->status : wp_json_encode( $ok ) ) . ')' );
} else {
	ok( false, 'AUN App API not active on the bench' );
}

ws_clean();
printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
