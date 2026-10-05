<?php
/**
 * AUN Spare Parts 0.47.2 — "not found on the first search, found on the second".
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * Reproduced live with 01719151023: 1st search not found (0.7 s), 2nd found 2
 * purchases. query_erp() turned every ERP failure into "no purchase". These tests
 * feed the lookup each kind of ERP answer and check what the customer is told.
 */

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}
if ( ! class_exists( 'AUN_SP_Lookup' ) ) { echo "spare parts not active\n"; exit( 1 ); }
if ( ! defined( 'AUN_SP_ERP_API_KEY' ) ) { define( 'AUN_SP_ERP_API_KEY', 'BENCH-KEY' ); }

/* ── a scripted ERP: each call takes the next answer off the queue ── */
$GLOBALS['erp_script'] = array();
$GLOBALS['erp_calls']  = 0;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false === strpos( (string) $url, 'sales-lookup' ) ) { return $pre; }
	$GLOBALS['erp_calls']++;
	$next = array_shift( $GLOBALS['erp_script'] );
	if ( null === $next )            { $next = array( 200, '{"success":true,"count":0,"data":[]}' ); }
	if ( $next instanceof WP_Error ) { return $next; }
	return array( 'response' => array( 'code' => $next[0], 'message' => '' ), 'body' => $next[1], 'headers' => array(), 'cookies' => array() );
}, 1, 3 );

$SALE = '{"success":true,"count":1,"data":[{"invoice_no":"SL-2026-0917","product_name":"AUN U002","sale_date":"2026-05-02 10:00:00","customer_name":"Bench Customer","mobile":"01719151023","address":"House 1, Road 2, Mirpur, Dhaka"}]}';
$FAIL500 = array( 500, '{"success":false,"message":"Server error."}' );
$GATE    = array( 200, "<!DOCTYPE html><html><head><title>Checking your browser&hellip;</title></head><body>…</body></html>" );
$EMPTY   = array( 200, '{"success":true,"count":0,"data":[]}' );

function run( $script ) {
	$GLOBALS['erp_script'] = $script;
	$GLOBALS['erp_calls']  = 0;
	return AUN_SP_Lookup::find_all( 'mobile', '01719151023' );
}

echo "\n=== 1. The live bug: the first ERP call fails, the next one works ===\n";
$r = run( array( $FAIL500, array( 200, $SALE ) ) );
ok( ! empty( $r['found'] ), 'the purchase is FOUND on the first search (retried inside the lookup)' );
ok( 2 === $GLOBALS['erp_calls'], 'by asking the ERP a second time (' . $GLOBALS['erp_calls'] . ' calls)' );
ok( empty( $r['erp_unavailable'] ), 'and it is not reported as an outage' );
ok( 'SL-2026-0917' === ( $r['matches'][0]['order_number'] ?? '' ), 'with the right purchase' );

echo "\n=== 2. The same with the other ways a call fails ===\n";
foreach ( array(
	'the gate\'s HTML page'   => $GATE,
	'a dropped connection'   => new WP_Error( 'http_request_failed', 'cURL error 52: Empty reply from server' ),
	'HTTP 503'               => array( 503, 'Service Unavailable' ),
	'success=false with 200' => array( 200, '{"success":false,"message":"busy"}' ),
) as $label => $failure ) {
	$r = run( array( $failure, array( 200, $SALE ) ) );
	ok( ! empty( $r['found'] ), "$label, then success: found" );
}

echo "\n=== 3. A genuine \"no purchase\" is still \"not found\" — and costs one call ===\n";
$r = run( array( $EMPTY ) );
ok( empty( $r['found'] ) && empty( $r['erp_unavailable'] ), 'the ERP said "none": not found, not an outage' );
ok( 1 === $GLOBALS['erp_calls'], 'a real answer is not asked twice (' . $GLOBALS['erp_calls'] . ' call)' );

echo "\n=== 4. The ERP is really down: say so, do not say \"you bought nothing\" ===\n";
$r = run( array( $FAIL500, $FAIL500 ) );
ok( empty( $r['found'] ), 'nothing found' );
ok( ! empty( $r['erp_unavailable'] ), 'and it is flagged as an outage' );
ok( 2 === $GLOBALS['erp_calls'], 'after exactly one retry' );

echo "\n=== 5. A wrong API key is not retried (it cannot start working) ===\n";
$r = run( array( array( 401, '{"success":false,"message":"Unauthorized."}' ) ) );
ok( 1 === $GLOBALS['erp_calls'], '401: one call only' );
ok( ! empty( $r['erp_unavailable'] ), 'and flagged, so the customer is not told they bought nothing' );

echo "\n=== 6. What the customer actually sees ===\n";
class SP_Done extends Exception {}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () { return function () { throw new SP_Done(); }; }, 99 );
$form = null;
foreach ( $GLOBALS['wp_filter']['wp_ajax_nopriv_aun_sp_find']->callbacks as $cbs ) {
	foreach ( $cbs as $cb ) { if ( is_array( $cb['function'] ) ) { $form = $cb['function'][0]; } }
}
function customer_search( $form, $script ) {
	$GLOBALS['erp_script'] = $script;
	$_POST = array( 'action' => 'aun_sp_find', '_nonce' => wp_create_nonce( 'aun_sp_public' ), 'search_by' => 'mobile', 'query' => '01719151023', 'lang' => 'en' );
	$_REQUEST = $_POST;
	ob_start();
	try { $form->ajax_find(); } catch ( SP_Done $e ) {}
	return json_decode( ob_get_clean(), true );
}
$j = customer_search( $form, array( $FAIL500, array( 200, $SALE ) ) );
ok( ! empty( $j['success'] ) && 1 === count( $j['data']['matches'] ?? array() ), 'one hiccup: the customer sees their purchase first time' );
$j = customer_search( $form, array( $FAIL500, $FAIL500 ) );
$m = $j['data']['message'] ?? '';
ok( false !== stripos( $m, 'try again' ) && false === stripos( $m, "couldn't find that purchase" ), 'ERP down: "try again", not "not found" (' . $m . ')' );
$j = customer_search( $form, array( $EMPTY ) );
ok( false !== stripos( $j['data']['message'] ?? '', "couldn't find that purchase" ), 'genuinely none: still "not found"' );

echo "\n=== 7. The failure is written to the log with what the ERP sent ===\n";
$log = tempnam( sys_get_temp_dir(), 'splog' );
$prev = ini_set( 'error_log', $log );
run( array( $GATE, array( 200, $SALE ) ) );
ini_set( 'error_log', (string) $prev );
$logged = (string) file_get_contents( $log );
@unlink( $log );
ok( false !== strpos( $logged, 'attempt 1/2 failed' ), 'the failed attempt is logged' );
ok( false !== strpos( $logged, 'not JSON' ) && false !== strpos( $logged, 'Checking your browser' ), 'naming what came back instead ("' . trim( substr( $logged, strpos( $logged, 'AUN SP' ), 110 ) ) . '…")' );

$_POST = array(); $_REQUEST = array();
printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
