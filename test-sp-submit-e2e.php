<?php
/**
 * AUN Spare Parts — end-to-end SUBMIT through the real ajax_submit(), checking the
 * browser would get JSON back (anything else shows "Network error — please try again").
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * Uses a part that needs no photo (CLI cannot fake an HTTP file upload), so this
 * exercises everything except moving the file: nonce, rate limit, the ERP re-check
 * changed in 0.47.2, purchase matching, saving, notifications.
 */
// Counters live in $GLOBALS: wp eval-file runs this file inside a function, so `global $p` would count nothing.
$GLOBALS['g_pass'] = 0; $GLOBALS['g_fail'] = 0;
function ok( $c, $l ) { if ( $c ) { $GLOBALS['g_pass']++; echo "  PASS  $l\n"; } else { $GLOBALS['g_fail']++; echo "  FAIL  $l\n"; } }
if ( ! class_exists( 'AUN_SP_Form' ) ) { echo "spare parts not active\n"; exit( 1 ); }
if ( ! defined( 'AUN_SP_ERP_API_KEY' ) ) { define( 'AUN_SP_ERP_API_KEY', 'BENCH-KEY' ); }

$GLOBALS['erp_script'] = array();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== strpos( (string) $url, 'sales-lookup' ) ) {
		$n = array_shift( $GLOBALS['erp_script'] );
		if ( ! $n ) { $n = array( 200, '{"success":true,"count":0,"data":[]}' ); }
		return array( 'response' => array( 'code' => $n[0], 'message' => '' ), 'body' => $n[1], 'headers' => array(), 'cookies' => array() );
	}
	return array( 'response' => array( 'code' => 200, 'message' => '' ), 'body' => '{"error":0}', 'headers' => array(), 'cookies' => array() ); // SMS etc.
}, 1, 3 );
add_filter( 'pre_wp_mail', '__return_true', 1 );

$SALE = '{"success":true,"count":1,"data":[{"invoice_no":"SL-E2E-1","product_name":"AUN U002","sale_date":"2026-06-02 10:00:00","customer_name":"E2E Customer","mobile":"01799111222","address":"House 4, Road 7, Mirpur 10, Dhaka"}]}';

// Choose a part that needs no photo.
$nophoto = '';
foreach ( AUN_SP_Parts::active() as $pt ) { if ( 'none' === $pt['photo'] ) { $nophoto = $pt['key']; break; } }
ok( '' !== $nophoto, "a no-photo part exists to test with ($nophoto)" );
$GLOBALS['e2e_part'] = $nophoto;

class E2E_Done extends Exception {}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () { return function () { throw new E2E_Done(); }; }, 99 );
$form = null;
foreach ( $GLOBALS['wp_filter']['wp_ajax_nopriv_aun_sp_submit']->callbacks as $cbs ) { foreach ( $cbs as $cb ) { if ( is_array( $cb['function'] ) ) { $form = $cb['function'][0]; } } }

function submit_once( $form, $script, $confirm = '0' ) {
	// NOT "global $nophoto": wp eval-file runs this file inside a function, so it would be empty.
	$nophoto = $GLOBALS['e2e_part'];
	$GLOBALS['erp_script'] = $script;
	$_POST = array(
		'action' => 'aun_sp_submit', '_nonce' => wp_create_nonce( 'aun_sp_public' ), 'lang' => 'en',
		'search_by' => 'mobile', 'query' => '01799111222',
		'selected_source' => 'erp', 'selected_order' => 'SL-E2E-1',
		'parts' => array( $nophoto ), 'qty' => array( $nophoto => 1 ),
		'current_phone' => '', 'current_address' => '', 'confirm_duplicate' => $confirm,
	);
	$_REQUEST = $_POST; $_FILES = array();
	$lvl = error_reporting( E_ALL );
	ob_start();
	try { $form->ajax_submit(); } catch ( E2E_Done $e ) {}
	$raw = ob_get_clean();
	error_reporting( $lvl );
	return $raw;
}

echo "\n=== 1. A normal submission returns JSON with a reference ===\n";
global $wpdb;
$wpdb->query( "DELETE FROM " . AUN_SP_Install::table( 'requests' ) . " WHERE phone_current IN ('01799111222')" );
$raw = submit_once( $form, array( array( 200, $SALE ) ) );
$j = json_decode( $raw, true );
ok( is_array( $j ), 'the browser gets JSON (not an error page)' . ( is_array( $j ) ? '' : ' — got: ' . substr( trim( wp_strip_all_tags( $raw ) ), 0, 160 ) ) );
ok( ! empty( $j['success'] ) && ! empty( $j['data']['ref'] ), 'and a request reference (' . ( $j['data']['ref'] ?? wp_json_encode( $j['data'] ?? null ) ) . ')' );
ok( false === stripos( $raw, 'Warning' ) && false === stripos( $raw, 'Notice' ) && false === stripos( $raw, 'Fatal' ), 'no PHP warnings leaked into the response' );

echo "\n=== 2. Every failure still returns JSON WITH a message (never the bare fallback) ===\n";
foreach ( array(
	'ERP down at submit'      => array( array( 500, '{"success":false}' ), array( 500, '{"success":false}' ) ),
	'purchase no longer found' => array( array( 200, '{"success":true,"count":0,"data":[]}' ) ),
) as $label => $script ) {
	$j = json_decode( submit_once( $form, $script, '1' ), true );
	ok( is_array( $j ) && empty( $j['success'] ) && ! empty( $j['data']['message'] ), "$label: JSON + message (\"" . ( $j['data']['message'] ?? '-' ) . '")' );
}

$wpdb->query( "DELETE FROM " . AUN_SP_Install::table( 'requests' ) . " WHERE phone_current IN ('01799111222')" );
$_POST = array(); $_REQUEST = array();
printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
