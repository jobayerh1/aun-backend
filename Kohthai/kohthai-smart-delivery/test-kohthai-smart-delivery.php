<?php
/**
 * Standalone test harness for Kohthai Smart Delivery v14.1.
 *
 * Run:  php test-kohthai-smart-delivery.php
 *
 * Covers only what v14.1 changed — the settings sanitiser and the source-level guarantees.
 * The estimate maths, cart splitting and email output were not touched and are not retested here.
 */

define( 'ABSPATH', __DIR__ . '/' );

// --- minimal shims ---------------------------------------------------------
function add_action() {}
function add_filter() {}
function register_setting() {}
function add_options_page() {}
function get_option( $k, $d = false ) { return $d; }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $s ) ) ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_js( $s ) { return $s; }
function esc_url( $s ) { return $s; }
function __( $s, $d = '' ) { return $s; }
function wp_timezone() { return new DateTimeZone( 'Asia/Dhaka' ); }
function wp_send_json_error() {}
function wp_send_json_success() {}
function wp_unslash( $s ) { return $s; }
function absint( $v ) { return abs( (int) $v ); }
function admin_url( $p = '' ) { return '/wp-admin/' . $p; }
function submit_button() {}
function settings_fields() {}
function checked() {}
function is_product() { return false; }
function has_term() { return false; }
function get_time_format() { return 'g:ia'; }
class WC_Shipping_Zone { public function __construct( $id = 0 ) {} public function get_zone_name() { return 'Zone'; } public function get_data() { return array(); } public function get_formatted_location() { return ''; } public function get_shipping_methods() { return array(); } }
class WC_Shipping_Zones { public static function get_zones() { return array( 1 => array(), 2 => array() ); } }

require_once __DIR__ . '/kohthai-smart-delivery-plugin.php';

$passed = 0; $failed = 0;
function ok( $label, $got, $want ) {
	global $passed, $failed;
	if ( $got === $want ) { $passed++; return; }
	$failed++;
	echo "FAIL  {$label}\n      want: " . var_export( $want, true ) . "\n      got : " . var_export( $got, true ) . "\n";
}

$d = new Kohthai_Smart_Delivery_Plugin_V14_0();

// =========================================================================
echo "--- sanitiser: rules ---\n";
$clean = $d->sanitize_rules( array(
	'1:5' => array( 'min_days' => '2', 'max_days' => '4', 'cutoff' => '17:00', 'pickup' => '1', 'pickup_message' => 'Ready' ),
) );
ok( 'min_days becomes an int', $clean['1:5']['min_days'], 2 );
ok( 'max_days becomes an int', $clean['1:5']['max_days'], 4 );
ok( 'a valid cutoff survives', $clean['1:5']['cutoff'], '17:00' );
ok( 'pickup normalises to a flag', $clean['1:5']['pickup'], '1' );

$bad = $d->sanitize_rules( array(
	'1:5' => array( 'min_days' => '-9', 'max_days' => 'abc', 'cutoff' => 'not a time', 'pickup' => '', 'pickup_message' => '<script>x</script>' ),
) );
ok( 'a negative day count is clamped to 0', $bad['1:5']['min_days'], 0 );
ok( 'a non-numeric day count becomes 0', $bad['1:5']['max_days'], 0 );
ok( 'a malformed cutoff is discarded', $bad['1:5']['cutoff'], '' );
ok( 'pickup off normalises to 0', $bad['1:5']['pickup'], '0' );
ok( 'script tags are stripped from pickup text', strpos( $bad['1:5']['pickup_message'], '<' ), false );

ok( 'a non-array rule is dropped', $d->sanitize_rules( array( '1:5' => 'nope' ) ), array() );
ok( 'a non-array input returns an empty array', $d->sanitize_rules( 'garbage' ), array() );

// =========================================================================
echo "--- sanitiser: messages ---\n";
$m = $d->sanitize_rules( array(
	'default_message'        => '  1-3 business days.  ',
	'backorder_message'      => '<b>Backorder</b> 2 weeks',
	'backorder_cart_message' => "ships\nsoon",
) );
ok( 'default message is trimmed', $m['default_message'], '1-3 business days.' );
ok( 'markup is stripped from the backorder message', $m['backorder_message'], 'Backorder 2 weeks' );
ok( 'newlines are flattened', $m['backorder_cart_message'], 'ships soon' );

// =========================================================================
echo "--- sanitiser: holidays ---\n";
$h = $d->sanitize_rules( array( 'holidays' => array(
	array( 'start' => '2026-09-01', 'end' => '2026-09-05' ),  // valid
	array( 'start' => '', 'end' => '' ),                       // empty row, dropped
	array( 'start' => '2026-13-45', 'end' => 'garbage' ),      // impossible date
	array( 'start' => '01/09/2026', 'end' => '' ),             // wrong format
	array( 'start' => '2026-02-30', 'end' => '' ),             // does not exist
) ) );
ok( 'a valid period survives', $h['holidays'][0], array( 'start' => '2026-09-01', 'end' => '2026-09-05' ) );
ok( 'empty rows are dropped', count( $h['holidays'] ), 1 );

$h2 = $d->sanitize_rules( array( 'holidays' => array( array( 'start' => '2026-09-01', 'end' => 'rubbish' ) ) ) );
ok( 'an invalid end date is blanked, start kept', $h2['holidays'][0], array( 'start' => '2026-09-01', 'end' => '' ) );
ok( 'holidays is always an array', is_array( $d->sanitize_rules( array( 'holidays' => 'nope' ) )['holidays'] ), true );

// =========================================================================
echo "--- source guarantees ---\n";
$src = file_get_contents( __DIR__ . '/kohthai-smart-delivery-plugin.php' );
// Comments quote the old code while explaining it, so assert on code only.
$code = preg_replace( '#/\*.*?\*/#s', '', $src );

ok( 'the raw echo is gone', strpos( $code, 'echo $backorder_message; ?>' ), false );
ok( 'the backorder message is escaped', strpos( $code, 'echo esc_html($backorder_message)' ) !== false, true );
ok( 'register_setting has a sanitize callback', strpos( $code, "'sanitize_callback' => array(\$this, 'sanitize_rules')" ) !== false, true );

// zone_id must be an int checked against the real zone list, not a free string.
ok( 'zone_id is cast to an int', strpos( $code, 'absint(wp_unslash($_POST[\'zone_id\']))' ) !== false, true );
ok( 'zone_id is checked against real zones', strpos( $code, 'in_array($zone_id, $allowed, true)' ) !== false, true );
ok( 'sanitize_text_field no longer guards zone_id', strpos( $code, "sanitize_text_field(\$_POST['zone_id'])" ), false );

// WP Rocket guards on the inline assets.
ok( 'inline style carries a no-optimize guard', strpos( $code, '<style data-no-optimize="1" data-no-minify="1">' ) !== false, true );
ok( 'inline script carries a no-optimize guard', strpos( $code, '<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">' ) !== false, true );

// The nonce omission is deliberate — a nonce baked into a cached product page expires.
ok( 'no nonce was added to the ajax endpoint', strpos( $code, 'check_ajax_referer' ), false );
ok( 'the reason is documented in the header', stripos( $src, 'DELIBERATELY NOT ADDED: a nonce' ) !== false, true );

// Wording can no longer drift between the three places it appears.
ok( 'no hardcoded backorder string left in code',
	substr_count( $code, "'This is a backorder item. Estimated delivery time is 1-2 weeks.'" ), 1 ); // the constant only
ok( 'cart wording reads from settings', strpos( $code, 'backorder_cart_message' ) !== false, true );

// =========================================================================
echo "--- v14.2 own styling ---
";
$m = new ReflectionMethod( 'Kohthai_Smart_Delivery_Plugin_V14_0', 'design_css' );
$m->setAccessible( true );
$css = $m->invoke( null );
// Comments quote the OLD colours while explaining them, so assert on rules only.
$rules = preg_replace( '#/\*.*?\*/#s', '', $css );

ok( 'warm surface', strpos( $rules, 'background:#faf7f3' ) !== false, true );
ok( 'warm line', strpos( $rules, '#e8e2d9' ) !== false, true );
ok( 'the old cool grey surface is gone', strpos( $rules, '#f7f7f7' ), false );
ok( 'the old cool grey border is gone', strpos( $rules, '#e0e0e0' ), false );
ok( '3px radius, not 5px', strpos( $rules, 'border-radius:3px' ) !== false, true );

// Backorder: amber, not alarm.
ok( 'backorder amber surface', strpos( $rules, '#fdf6e7' ) !== false, true );
ok( 'alarm red is gone everywhere', strpos( $rules, '#b30000' ), false );
ok( 'pink alarm surface is gone', strpos( $rules, '#fff0f0' ), false );

// Blue accents on cart/checkout become brand brown.
ok( 'cart blue accent gone', strpos( $rules, '#005a8d' ), false );
ok( 'in-stock blue icon gone', strpos( $rules, '#1e85be' ), false );
ok( 'brand brown used instead', strpos( $rules, '#654321' ) !== false, true );

// The icon: glyph hidden, inline SVG background, colour substituted BEFORE encoding.
ok( 'glyph hidden', strpos( $rules, 'font-size:0' ) !== false, true );
ok( 'icon is a data uri', strpos( $rules, 'data:image/svg+xml' ) !== false, true );
ok( 'brand truck encoded correctly', strpos( $rules, '%23654321' ) !== false, true );
ok( 'amber truck encoded correctly', strpos( $rules, '%238a6110' ) !== false, true );
ok( 'no unsubstituted placeholder', strpos( $rules, 'COLOR' ), false );

// Cart shipping rows keep their FontAwesome glyphs — different selector, must not be hidden.
ok( 'cart shipping icons are not blanked',
	strpos( $rules, '.kohthai-shipping-rate-estimate i{margin-right:6px}' ) !== false, true );

// =========================================================================
echo "\n" . str_repeat( '=', 46 ) . "\n";
printf( "  %d passed, %d failed\n", $passed, $failed );
echo str_repeat( '=', 46 ) . "\n";
exit( $failed > 0 ? 1 : 0 );
