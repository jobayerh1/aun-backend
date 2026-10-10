<?php
/**
 * AUN Spare Parts 0.47.3 — the browser's "Network error" report reaches the log.
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * When a customer sees "Network error — please try again", sp-form.js / sp-track.js
 * now POST one report to admin-ajax (action aun_sp_client_error). This drives the
 * real handler and reads the line it writes to the PHP error log.
 */
$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}
if ( ! class_exists( 'AUN_SP_Form' ) ) { echo "spare parts not active\n"; exit( 1 ); }
ok( '0.47.3' === AUN_SP_VERSION, 'bench runs 0.47.3 (' . AUN_SP_VERSION . ')' );
ok( has_action( 'wp_ajax_nopriv_aun_sp_client_error' ) && has_action( 'wp_ajax_aun_sp_client_error' ), 'the report endpoint is registered for guests and logged-in customers' );

class CE_Done extends Exception {}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () { return function () { throw new CE_Done(); }; }, 99 );
$form = null;
foreach ( $GLOBALS['wp_filter']['wp_ajax_nopriv_aun_sp_client_error']->callbacks as $cbs ) {
	foreach ( $cbs as $cb ) { if ( is_array( $cb['function'] ) ) { $form = $cb['function'][0]; } }
}
$GLOBALS['ce_form'] = $form;

// Each report runs from a fresh "IP" so the rate limit only bites where tested.
function report( $post, $ip = null ) {
	$_SERVER['REMOTE_ADDR']     = $ip ?: '10.9.' . wp_rand( 0, 255 ) . '.' . wp_rand( 1, 254 );
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 13; SM-A145F) AppleWebKit/537.36 Chrome/129 Mobile';
	$_POST = array_merge( array( 'action' => 'aun_sp_client_error' ), $post );
	$_REQUEST = $_POST;
	$log  = tempnam( sys_get_temp_dir(), 'celog' );
	$prev = ini_set( 'error_log', $log );
	ob_start();
	try { $GLOBALS['ce_form']->ajax_client_error(); } catch ( CE_Done $e ) {}
	$out = ob_get_clean();
	ini_set( 'error_log', (string) $prev );
	$logged = (string) file_get_contents( $log );
	@unlink( $log );
	return array( json_decode( $out, true ), $logged );
}

echo "\n=== 1. The host's \"Checking your browser\" page instead of an answer ===\n";
list( $j, $l ) = report( array( 'step' => 'submit', 'status' => '200', 'detail' => '<!DOCTYPE html><html><head><title>Checking your browser&hellip;</title></head><body>Please wait</body></html>', 'bytes' => (string) ( 9 * 1048576 ), 'ms' => '41250', 'online' => '1' ) );
ok( ! empty( $j['success'] ), 'the browser gets a plain "ok" back' );
ok( false !== strpos( $l, 'customer saw "Network error" at submit' ), 'one line in the log, naming the step' );
ok( false !== strpos( $l, 'HTTP 200 after 41.3s, upload 9 MB' ), 'with the HTTP status, how long it took and the upload size' );
ok( false !== strpos( $l, 'got: Checking your browser' ) && false === strpos( $l, '<title>' ), 'and what came back instead, as text (no HTML tags)' );
ok( false !== strpos( $l, 'Android 13' ), 'plus the phone/browser' );
ok( false === strpos( $l, '&hellip;' ), 'HTML entities are decoded so the line reads plainly' );
echo '        ' . trim( substr( $l, strpos( $l, 'AUN SP' ) ) ) . "\n";

echo "\n=== 2. The upload never got an answer (dropped mobile connection) ===\n";
list( $j, $l ) = report( array( 'step' => 'submit', 'status' => '0', 'detail' => 'Failed to fetch', 'bytes' => (string) ( 23 * 1048576 ), 'ms' => '95000', 'online' => '0' ) );
ok( false !== strpos( $l, 'HTTP 0 (no response) after 95s, upload 23 MB, phone OFFLINE' ), 'status 0 reads "no response", and the phone said it was offline' );
ok( false !== strpos( $l, 'got: Failed to fetch' ), 'with the browser\'s own error text' );

list( $j, $l ) = report( array( 'step' => 'pay', 'status' => '0', 'detail' => 'Load failed' ) );
ok( false !== strpos( $l, 'at pay — HTTP 0 (no response) after ?,' ), 'a step the page does not time says "after ?", not a misleading 0s' );

echo "\n=== 3. A phone number in the reply is never written to the log ===\n";
list( $j, $l ) = report( array( 'step' => 'find', 'status' => 'answer-without-message', 'detail' => '{"success":false,"data":{"phone":"01719151023","alt":"+8801719151023"}}' ) );
ok( '' !== $l && false === strpos( $l, '01719151023' ) && false === strpos( $l, '1719151023' ), 'both forms of the number are masked' );

echo "\n=== 4. Junk is refused or trimmed ===\n";
list( $j, $l ) = report( array( 'step' => 'drop_tables', 'status' => '500', 'detail' => 'x' ) );
ok( ! empty( $j['success'] ) && '' === $l, 'an unknown step is ignored (nothing logged)' );
list( $j, $l ) = report( array( 'step' => 'track', 'status' => '502', 'detail' => str_repeat( 'A', 5000 ) ) );
ok( '' !== $l && strlen( $l ) < 600, 'a huge reply is cut short (' . strlen( $l ) . ' bytes logged)' );
list( $j, $l ) = report( array( 'step' => 'reupload', 'status' => "0\nAUN SP: fake line", 'detail' => "a\nAUN SP: forged entry" ) );
ok( '' !== $l && 0 === substr_count( trim( $l ), "\n" ), 'newlines cannot forge extra log lines (it stays ONE line)' );

echo "\n=== 5. One phone cannot flood the log ===\n";
$ip = '10.77.1.' . wp_rand( 1, 254 );
$lines = 0;
for ( $i = 0; $i < 9; $i++ ) {
	list( $j, $l ) = report( array( 'step' => 'find', 'status' => '0', 'detail' => 'Failed to fetch' ), $ip );
	if ( '' !== $l ) { $lines++; }
}
ok( 6 === $lines, "9 reports in a minute from one address, $lines logged (limit 6)" );

echo "\n=== 6. Both pages send it, everywhere they can show \"Network error\" ===\n";
$dir = WP_PLUGIN_DIR . '/aun-spare-parts/assets/';
$f = file_get_contents( $dir . 'sp-form.js' );
$t = file_get_contents( $dir . 'sp-track.js' );
ok( 0 === substr_count( $f . $t, 'r.json()' ), 'no reply is parsed with r.json() any more (it hid what came back)' );
ok( 3 === substr_count( $f, '.then( readJSON )' ) && 3 === substr_count( $t, '.then( readJSON )' ), 'all 6 requests read through readJSON' );
foreach ( array( "'find'" => $f, "'submit'" => $f, "'track'" => $t, "'reupload'" => $t, "'revive'" => $t, "'pay'" => $t, "'approve'" => $t ) as $step => $src ) {
	ok( false !== strpos( $src, 'reportFailure( ' . $step ), "reports at $step" );
}
ok( 0 === preg_match( '/catch\( function \(\) \{[^}]*net_err/', $f . $t ), 'no "Network error" catch is left that stays silent' );

$_POST = array(); $_REQUEST = array();
printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
