<?php
/**
 * Standalone test harness for Kohthai Shorts Showcase.
 *
 * Run:  php test-kohthai-shorts-showcase.php
 *
 * No WordPress required. Covers:
 *   - the ratio map and the settings screen (the reason this is not a straight rename)
 *   - the original-aspect poster fallback
 *   - YouTube id validation, which must never regress: the id is interpolated into a
 *     CSS url(), so an unvalidated value is a style-injection vector
 *   - a whole-file scan for leftover wording from the site this engine came from
 */

define( 'ABSPATH', __DIR__ . '/' );

$SC  = array();
$OPT = array();

function add_shortcode( $t, $c ) { $GLOBALS['SC'][ $t ] = $c; }
function add_action() {}
function add_filter() {}
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return $s; }
function esc_url_raw( $s ) { return $s; }
function esc_js( $s ) { return $s; }
function wp_kses_post( $s ) { return $s; }
function wp_get_attachment_url( $i ) { return false; }
function wp_register_style() {}
function wp_enqueue_style() {}
function wp_add_inline_style() {}
function do_shortcode( $s ) { return $s; }
function wpautop( $s ) { return $s; }
function sanitize_text_field( $s ) { return $s; }
function wp_json_encode( $s ) { return json_encode( $s ); }
function admin_url( $p = '' ) { return '/wp-admin/' . $p; }
function current_user_can() { return true; }
function add_menu_page() {}
function add_submenu_page() {}
function add_options_page() {}
function register_setting() {}
function settings_fields() {}
function submit_button() {}
function selected() {}
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['OPT'] ) ? $GLOBALS['OPT'][ $k ] : $d;
}
function update_option( $k, $v ) { $GLOBALS['OPT'][ $k ] = $v; return true; }
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}

require_once __DIR__ . '/kohthai-shorts-showcase.php';
Kohthai_Shorts_Showcase::init();

$passed = 0;
$failed = 0;
function ok( $label, $got, $want ) {
	global $passed, $failed;
	if ( $got === $want ) { $passed++; return; }
	$failed++;
	echo "FAIL  {$label}\n      want: " . var_export( $want, true ) . "\n      got : " . var_export( $got, true ) . "\n";
}
function render( $atts ) { return call_user_func( $GLOBALS['SC']['kohthai_shorts'], $atts ); }
function ratio_of( $out ) {
	preg_match( '/--kts-w:(\d+)px;--kts-ar:([^;]+);--kts-arn:([^;"]+)/', $out, $m );
	return array( $m[1] ?? '?', trim( $m[2] ?? '?' ), trim( $m[3] ?? '?' ) );
}
function card_of( $out ) {
	preg_match( '/class="kts-short-card"[^>]*style="width:(\d+)px;aspect-ratio:([^;]+);/', $out, $m );
	return array( $m[1] ?? '?', trim( $m[2] ?? '?' ) );
}

$VALID = 'tUnExtJfYdQ'; // a real Kohthai product video

// =========================================================================
echo "--- registration ---\n";
ok( 'kohthai_shorts registered', isset( $GLOBALS['SC']['kohthai_shorts'] ), true );
ok( 'kohthai_video registered', isset( $GLOBALS['SC']['kohthai_video'] ), true );
ok( 'old aun_shorts NOT registered', isset( $GLOBALS['SC']['aun_shorts'] ), false );
ok( 'old aun_video NOT registered', isset( $GLOBALS['SC']['aun_video'] ), false );

// =========================================================================
// Ratio. Both measured Kohthai product videos are 720x720 (1:1), not 9:16 --
// forcing 9:16 on them would pillarbox exactly like the player this replaces.
echo "--- ratio ---\n";
ok( '9:16 wrapper vars', ratio_of( render( array( 'ids' => $VALID, 'ratio' => '9:16' ) ) ), array( '280', '9 / 16', '0.5625' ) );
ok( '4:5 wrapper vars',  ratio_of( render( array( 'ids' => $VALID, 'ratio' => '4:5' ) ) ),  array( '340', '4 / 5', '0.8' ) );
ok( '1:1 wrapper vars',  ratio_of( render( array( 'ids' => $VALID, 'ratio' => '1:1' ) ) ),  array( '380', '1 / 1', '1' ) );
ok( '9:16 card box', card_of( render( array( 'ids' => $VALID, 'ratio' => '9:16' ) ) ), array( '280', '9 / 16' ) );
ok( '1:1 card box',  card_of( render( array( 'ids' => $VALID, 'ratio' => '1:1' ) ) ),  array( '380', '1 / 1' ) );
ok( 'unknown ratio falls back', ratio_of( render( array( 'ids' => $VALID, 'ratio' => 'nonsense' ) ) ), array( '280', '9 / 16', '0.5625' ) );
ok( 'no hardcoded 498px card', strpos( render( array( 'ids' => $VALID, 'ratio' => '1:1' ) ), 'height:498px' ), false );

// =========================================================================
echo "--- poster ---\n";
$out = render( array( 'ids' => $VALID ) );
preg_match_all( "#url\('https://i\.ytimg\.com/vi/[^/]+/([a-z0-9]+)\.jpg'\)#", $out, $m );
ok( 'poster layers, true aspect first', $m[1], array( 'oar2', 'hqdefault' ) );

// =========================================================================
echo "--- security ---\n";
$bad = array(
	'css injection'  => "');background:url(//evil.tld/x.jpg",
	'path traversal' => '../../etc/passwd',
	'script tag'     => '<script>alert(1)</script>',
	'quote break'    => "abc'\"def",
	'too short'      => 'short',
	'too long'       => 'AAAAAAAAAAAA',
	'empty segment'  => ',',
);
foreach ( $bad as $label => $id ) {
	$o = render( array( 'ids' => $id ) );
	ok( "rejected: {$label} (no card)", substr_count( $o, 'class="kts-short-card"' ), 0 );
	ok( "rejected: {$label} (no leak)",
		( strpos( $o, 'evil.tld' ) !== false || strpos( $o, 'passwd' ) !== false || strpos( $o, '<script>alert' ) !== false ),
		false );
}
ok( 'valid id renders one card', substr_count( render( array( 'ids' => $VALID ) ), 'class="kts-short-card"' ), 1 );
ok( 'two valid ids render two cards', substr_count( render( array( 'ids' => $VALID . ',bUSme6zuVwQ' ) ), 'class="kts-short-card"' ), 2 );
ok( 'empty ids renders nothing', render( array( 'ids' => '' ) ), '' );

// =========================================================================
// Whole-file wording scan. Case-insensitive on purpose: the first rename pass
// was case-sensitive and missed __AUN_INSTANCE__ plus a set of camelCase JS
// globals (aunYtPlayers, aunToggleMute, _aunInit, ...).
echo "--- wording ---\n";
$src = file_get_contents( __DIR__ . '/kohthai-shorts-showcase.php' );
$forbidden = array(
	'aun', 'projector', 'cinema', 'lumen', '1080p', 'android tv', 'throw distance',
	'kohthai_head', 'dQw4w9WgXcQ', 'WATCH IT IN ACTION', 'Experience the Clarity',
	'#0188fe', '#00c6ff',
);
foreach ( $forbidden as $needle ) {
	ok( "no \"{$needle}\" anywhere in the file", stripos( $src, $needle ), false );
}
ok( 'brand brown present', strpos( $src, '#654321' ) !== false, true );

// Renamed JS globals must all be present, or the player silently breaks.
foreach ( array( 'ktsYtPlayers', 'ktsToggleMute', 'ktsYtApiReady', '_ktsInit', 'ktsSwipePulse', '__KTS_INSTANCE__' ) as $g ) {
	ok( "js token {$g} present", substr_count( $src, $g ) > 0, true );
}
ok( 'swipe animation matches its keyframe',
	strpos( $src, 'animation: ktsSwipePulse' ) !== false && strpos( $src, '@keyframes ktsSwipePulse' ) !== false,
	true );

// =========================================================================
echo "--- settings ---\n";
ok( 'default option is 9:16', Kohthai_Shorts_Showcase::defaults(), array( 'default_ratio' => '9:16' ) );
ok( 'three shapes offered', array_keys( Kohthai_Shorts_Showcase::ratio_map() ), array( '9:16', '4:5', '1:1' ) );

update_option( 'kts_options', array( 'default_ratio' => '1:1' ) );
ok( 'saved default is read back', Kohthai_Shorts_Showcase::default_ratio(), '1:1' );
ok( 'shortcode with no ratio uses the saved default', ratio_of( render( array( 'ids' => $VALID ) ) ), array( '380', '1 / 1', '1' ) );
ok( 'explicit ratio overrides the setting', ratio_of( render( array( 'ids' => $VALID, 'ratio' => '9:16' ) ) ), array( '280', '9 / 16', '0.5625' ) );

update_option( 'kts_options', array( 'default_ratio' => 'garbage' ) );
ok( 'a corrupt saved value falls back to 9:16', Kohthai_Shorts_Showcase::default_ratio(), '9:16' );

ok( 'sanitize keeps a valid shape', Kohthai_Shorts_Showcase::sanitize( array( 'default_ratio' => '4:5' ) ), array( 'default_ratio' => '4:5' ) );
ok( 'sanitize rejects an invalid shape', Kohthai_Shorts_Showcase::sanitize( array( 'default_ratio' => '16:9' ) ), array( 'default_ratio' => '9:16' ) );
ok( 'sanitize rejects a non-array', Kohthai_Shorts_Showcase::sanitize( 'nope' ), array( 'default_ratio' => '9:16' ) );
ok( 'sanitize drops unknown keys', Kohthai_Shorts_Showcase::sanitize( array( 'evil' => 1 ) ), array( 'default_ratio' => '9:16' ) );

update_option( 'kts_options', array() );

// =========================================================================
echo "\n" . str_repeat( '=', 46 ) . "\n";
printf( "  %d passed, %d failed\n", $passed, $failed );
echo str_repeat( '=', 46 ) . "\n";
exit( $failed > 0 ? 1 : 0 );
