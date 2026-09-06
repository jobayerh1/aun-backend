<?php
/**
 * Standalone test harness for Kohthai Product Blocks.
 *
 * Run:  php test-kohthai-product-blocks.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$SC  = array();
$OPT = array();

function add_shortcode( $t, $c ) { $GLOBALS['SC'][ $t ] = $c; }
function add_action() {}
function add_filter() {}
function register_setting() {}
function settings_fields() {}
function submit_button() {}
function checked() {}
function add_options_page() {}
function current_user_can() { return true; }
function wp_register_style() {}
function wp_enqueue_style() {}
function wp_add_inline_style() {}
function woocommerce_wp_text_input() {}
function get_post_meta( $id = 0, $key = '', $single = true ) {
	return isset( $GLOBALS['META'][ $key ] ) ? $GLOBALS['META'][ $key ] : '';
}
function update_post_meta() {}
function delete_post_meta() {}
function wp_unslash( $s ) { return $s; }
function __( $s, $d = '' ) { return $s; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { return $u; }
function esc_url_raw( $u ) { $u = trim( (string) $u ); return preg_match( '#^https?://#i', $u ) ? $u : ''; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $s ) ) ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['OPT'] ) ? $GLOBALS['OPT'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['OPT'][ $k ] = $v; return true; }
function absint( $v ) { return abs( (int) $v ); }
function wp_kses_post( $s ) { return $s; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function apply_filters( $t, $v ) { return $v; }
function selected() {}
function has_shortcode( $c, $t ) { return strpos( (string) $c, '[' . $t ) !== false; }
function remove_action() { $GLOBALS['REMOVED'][] = func_get_args(); }
function is_product() { return isset( $GLOBALS['IS_PRODUCT'] ) ? $GLOBALS['IS_PRODUCT'] : true; }
$META = array();
function do_shortcode( $s ) { return $s; }
function wp_specialchars_decode( $s ) { return html_entity_decode( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = '' ) { return $s; }
$ATTACH = array( 1234 => 'a.jpg', 1235 => 'b.jpg', 1236 => 'c.jpg' );
function wp_get_attachment_image( $id, $size = 'medium', $icon = false, $attr = array() ) {
	return isset( $GLOBALS['ATTACH'][ $id ] ) ? '<img src="' . $GLOBALS['ATTACH'][ $id ] . '" alt="">' : '';
}
function shortcode_atts( $pairs, $atts, $sc = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $n => $d ) { $out[ $n ] = array_key_exists( $n, $atts ) ? $atts[ $n ] : $d; }
	return $out;
}

class WC_Product {
	private $bo; private $in; public $woo_html;
	public function __construct( $bo = false, $in = true, $woo_html = '' ) {
		$this->bo = $bo; $this->in = $in; $this->woo_html = $woo_html;
	}
	public $desc = '';
	public function get_description() { return $this->desc; }
	public function is_on_backorder() { return $this->bo; }
	public function is_in_stock() { return $this->in; }
	public function get_id() { return 1; }
}
function wc_get_stock_html( $p ) { return isset( $p->woo_html ) ? $p->woo_html : ''; }
function stock_line() {
	ob_start();
	Kohthai_Product_Blocks::render_stock_line();
	return ob_get_clean();
}
function set_product( $bo, $in = true, $woo = '' ) { $GLOBALS['product'] = new WC_Product( $bo, $in, $woo ); }
function clear_product() { unset( $GLOBALS['product'] ); }

require_once __DIR__ . '/kohthai-product-blocks.php';

$passed = 0; $failed = 0;
function ok( $label, $got, $want ) {
	global $passed, $failed;
	if ( $got === $want ) { $passed++; return; }
	$failed++;
	echo "FAIL  {$label}\n      want: " . var_export( $want, true ) . "\n      got : " . var_export( $got, true ) . "\n";
}
function render( $atts ) { return call_user_func( $GLOBALS['SC']['kt_features'], $atts ); }
function trust() { return call_user_func( $GLOBALS['SC']['kt_trust'] ); }
function labels( $html ) { preg_match_all( '#<span>(.*?)</span>#', $html, $m ); return $m[1]; }
function count_icons( $html ) { return substr_count( $html, '<svg' ); }
function reset_opts() { update_option( 'kt_blocks_options', array() ); }

// =========================================================================
echo "--- feature row ---\n";
ok( 'kt_features registered', isset( $GLOBALS['SC']['kt_features'] ), true );
ok( 'kt_trust registered', isset( $GLOBALS['SC']['kt_trust'] ), true );

$five = render( array( 'items' => 'adjustable-strap, inner-pocket, outer-pocket, optional-strap, zipper' ) );
ok( 'five icons render', count_icons( $five ), 5 );
ok( 'labels come from the icon table', labels( $five ),
	array( 'Adjustable Strap', 'Inner Pocket', 'Outer Pocket', 'Optional Strap', 'Zipper' ) );
ok( 'one li per icon', substr_count( $five, '<li>' ), 5 );
ok( 'whitespace tolerated', count_icons( render( array( 'items' => '  zipper ,  inner-pocket ' ) ) ), 2 );

// The three faults this replaces.
ok( 'emits NO headings', preg_match( '#<h[1-6]#i', $five ), 0 );
ok( 'emits NO paragraphs', preg_match( '#<p[ >]#i', $five ), 0 );
ok( 'emits NO <br>', preg_match( '#<br#i', $five ), 0 );
ok( 'emits NO <img>', preg_match( '#<img#i', $five ), 0 );

// Bad input.
ok( 'empty items renders nothing', render( array( 'items' => '' ) ), '' );
ok( 'unknown key is skipped', count_icons( render( array( 'items' => 'not-real' ) ) ), 0 );
ok( 'all-unknown renders nothing', render( array( 'items' => 'nope, also-nope' ) ), '' );
ok( 'unknown drops out of a valid list', count_icons( render( array( 'items' => 'zipper, bogus, inner-pocket' ) ) ), 2 );
ok( 'trailing comma ignored', count_icons( render( array( 'items' => 'zipper,' ) ) ), 1 );
ok( 'uppercase key resolves', count_icons( render( array( 'items' => 'ZIPPER' ) ) ), 1 );
ok( 'markup in a key cannot inject', strpos( render( array( 'items' => '<b>zipper</b>' ) ), '<b>' ), false );

// Custom labels.
ok( 'pipe overrides the label', labels( render( array( 'items' => 'adjustable-strap|Extra Long Strap, zipper' ) ) ),
	array( 'Extra Long Strap', 'Zipper' ) );
ok( 'custom label is escaped', strpos( render( array( 'items' => 'zipper|<script>alert(1)</script>' ) ), '<script>' ), false );

// Align.
ok( 'default centred, no override', strpos( $five, 'justify-content' ), false );
ok( 'align=left overrides', strpos( render( array( 'items' => 'zipper', 'align' => 'left' ) ), 'justify-content:flex-start' ) !== false, true );
ok( 'invalid align falls back', strpos( render( array( 'items' => 'zipper', 'align' => 'sideways' ) ), 'justify-content' ), false );

// =========================================================================
echo "--- icon system ---\n";
$icons = Kohthai_Product_Blocks::icons();
// A bare count only catches an accidental deletion. Naming them also documents the set and
// fails loudly if a key is ever renamed out from under the products that reference it.
ok( 'every icon key is present', array_keys( $icons ), array(
	'adjustable-strap', 'optional-strap', 'inner-pocket', 'outer-pocket', 'top-handle', 'card-slot',
	'zipper', 'magnetic-flap', 'chain-strap', 'water-resistant', 'expandable', 'lightweight',
) );
foreach ( $icons as $key => $icon ) {
	ok( "{$key} has a label", is_string( $icon[0] ) && '' !== $icon[0], true );
	ok( "{$key} is path data only", strpos( $icon[1], '<' ), 0 );
	ok( "{$key} carries no <svg>", strpos( $icon[1], '<svg' ), false );
	ok( "{$key} sets no fill", strpos( $icon[1], 'fill=' ), false );
	ok( "{$key} sets no stroke-width", strpos( $icon[1], 'stroke-width' ), false );
}
ok( 'inner-pocket is dashed', strpos( $icons['inner-pocket'][1], 'stroke-dasharray' ) !== false, true );
ok( 'outer-pocket is solid', strpos( $icons['outer-pocket'][1], 'stroke-dasharray' ), false );
ok( 'both share one body path',
	strpos( $icons['inner-pocket'][1], 'M4.6 8.6h14.8' ) !== false
	&& strpos( $icons['outer-pocket'][1], 'M4.6 8.6h14.8' ) !== false, true );

// =========================================================================
echo "--- trust row ---\n";
reset_opts();
$t = trust();
ok( 'four tiles by default', substr_count( $t, 'kt-trust__i' ), 4 );
ok( 'wrapped once', substr_count( $t, '<div class="kt-trust">' ), 1 );
ok( 'returns tile is the only link', substr_count( $t, '<a class="kt-trust__i"' ), 1 );
ok( 'link points at the policy', strpos( $t, 'href="/returns_refund/"' ) !== false, true );

// The honest-wording rule: every one of these is false against the real returns policy.
foreach ( array( 'no questions asked', 'hassle-free', 'free return', 'warranty', 'guarantee' ) as $banned ) {
	ok( "default copy avoids \"{$banned}\"", stripos( $t, $banned ), false );
}
ok( 'return tile is qualified', strpos( $t, 'If damaged or not as described' ) !== false, true );

update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'trust_3_title' => '' ) ) );
ok( 'empty title hides that tile', substr_count( trust(), 'kt-trust__i' ), 3 );

update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'returns_url' => '' ) ) );
ok( 'no url means no link', substr_count( trust(), '<a ' ), 0 );
reset_opts();

$s1 = Kohthai_Product_Blocks::sanitize( array( 'trust_1_title' => '<script>x</script>' ) );
ok( 'a script tag in a tile is stripped', strpos( $s1['trust_1_title'], '<' ), false );
ok( 'javascript: url rejected', Kohthai_Product_Blocks::sanitize( array( 'returns_url' => 'javascript:alert(1)' ) )['returns_url'], '' );
ok( 'relative path allowed', Kohthai_Product_Blocks::sanitize( array( 'returns_url' => '/returns_refund/' ) )['returns_url'], '/returns_refund/' );
ok( 'sanitize of a non-array returns defaults', Kohthai_Product_Blocks::sanitize( 'junk' ), Kohthai_Product_Blocks::defaults() );

// =========================================================================
echo "--- stock-aware trust row ---
";
reset_opts();

// A shortcode used off a product page must not fatal.
clear_product();
ok( 'renders with no product in scope', substr_count( trust(), 'kt-trust__i' ), 4 );
ok( 'no product means in-stock wording', strpos( trust(), 'Ships from Dhaka' ) !== false, true );

set_product( false );
$in = trust();
ok( 'in stock: Ships from Dhaka', strpos( $in, 'Ships from Dhaka' ) !== false, true );
ok( 'in stock: Not a pre-order', strpos( $in, 'Not a pre-order' ) !== false, true );

set_product( true );
$bo = trust();
// The contradiction that started this: the delivery plugin says "backorder, 1-2 weeks" on the
// same screen, so claiming "Not a pre-order" made the page argue with itself.
ok( 'backorder drops Ships from Dhaka', strpos( $bo, 'Ships from Dhaka' ), false );
ok( 'backorder drops Not a pre-order', strpos( $bo, 'Not a pre-order' ), false );
ok( 'backorder says 1-2 weeks', strpos( $bo, 'Ships in 1-2 weeks' ) !== false, true );
ok( 'backorder still shows four tiles', substr_count( $bo, 'kt-trust__i' ), 4 );

// Claims true either way must NOT change.
foreach ( array( 'Cash on Delivery', '7-Day Return', '100% Genuine', 'If damaged or not as described' ) as $keep ) {
	ok( "backorder keeps {$keep}", strpos( $bo, $keep ) !== false, true );
}
ok( 'backorder still links the policy', strpos( $bo, 'href="/returns_refund/"' ) !== false, true );

// An empty backorder title means this tile has nothing stock-dependent to say.
update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'trust_4_bo_title' => '' ) ) );
ok( 'empty backorder title keeps normal wording', strpos( trust(), 'Ships from Dhaka' ) !== false, true );

// A tile hidden normally stays hidden on backorder.
update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'trust_1_title' => '' ) ) );
ok( 'a hidden tile stays hidden on backorder', substr_count( trust(), 'kt-trust__i' ), 3 );

// Backorder copy must clear the same honesty bar as the defaults.
reset_opts();
foreach ( array( 'no questions asked', 'hassle-free', 'free return', 'warranty', 'guarantee' ) as $banned ) {
	ok( "backorder copy avoids {$banned}", stripos( trust(), $banned ), false );
}
clear_product();

// =========================================================================
echo "--- absorbed css ---\n";
reset_opts();
$css = Kohthai_Product_Blocks::css();
// Strip CSS comments before asserting on RULES — the comments deliberately quote the old broken
// declarations while explaining them, and a comment must not fail a test about what is applied.
$rules = preg_replace( '#/\*.*?\*/#s', '', $css );

// Carried over on purpose: 17 of 21 products still use [ux_video].
ok( 'video gap fix present', strpos( $css, '.video-fit > .rll-youtube-player' ) !== false, true );
ok( 'swatch name rule present', strpos( $css, 'content:attr(data-name)' ) !== false, true );

// The arrow bug: padding-left:0 let the title run under Flatsome's 47px absolute toggle.
ok( 'no rule sets padding-left:0', strpos( $rules, 'padding-left:0' ), false );
ok( 'accordion clears the toggle', strpos( $css, 'padding-left:36px' ) !== false, true );
ok( 'toggle moved to the right', strpos( $css, 'left:auto !important;right:0 !important' ) !== false, true );

// Dead code not carried: zero products use .kt-feat markup any more.
ok( 'dead .kt-feat block is gone', preg_match( '/\.kt-feat\{/', $rules ), 0 );

// Duplicates resolved — only the winning values survive.
// Counted as a BARE selector at the start of a line. The original bug was two unscoped
// .kt-h rules with different font-sizes, the second silently winning. A scoped override like
// `.kt-about .kt-h` is a different thing and is allowed - but a second bare rule still fails,
// and the companion assertion below pins the property that actually broke.
ok( 'kt-h declared once as a bare rule', preg_match_all( '/(^|\n)\.kt-h\{/', $css ), 1 );
ok( 'no scoped override touches kt-h font-size',
	preg_match( '/\.[a-z0-9_-]+ \.kt-h\{[^}]*font-size/i', $css ), 0 );
ok( 'kt-h uses the winning 16px', strpos( $css, '.kt-h{font-size:16px' ) !== false, true );
ok( 'spec value uses the winning 17px', strpos( $css, '.kt-spec b{display:block;font-size:17px' ) !== false, true );
ok( 'superseded 19px is gone', strpos( $css, 'font-size:19px' ), false );

// The one-row guarantee must survive the refactor.
ok( 'zero flex basis kept', strpos( $css, 'flex:1 1 0' ) !== false, true );
ok( 'forced li margin kept', strpos( $css, 'margin:0 !important' ) !== false, true );
ok( 'old 76px basis still gone', strpos( $css, 'flex:0 0 76px' ), false );

// Toggles remove only their own block.
update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'css_theme_fixes' => 0 ) ) );
$off = Kohthai_Product_Blocks::css();
ok( 'theme fixes off removes the video fix', strpos( $off, '.video-fit' ), false );
ok( 'theme fixes off keeps the feature row', strpos( $off, '.kt-features{' ) !== false, true );
ok( 'theme fixes off keeps the trust row', strpos( $off, '.kt-trust{' ) !== false, true );

update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'css_product_details' => 0 ) ) );
$off2 = Kohthai_Product_Blocks::css();
ok( 'details off removes kt-lead', strpos( $off2, '.kt-lead' ), false );
ok( 'details off keeps the feature row', strpos( $off2, '.kt-features{' ) !== false, true );
reset_opts();

echo "--- kt_lead ---\n";
function lead( $c ) { return call_user_func( $GLOBALS['SC']['kt_lead'], array(), $c ); }
ok( 'wraps prose in the lead class', lead( 'Hello there' ), '<p class="kt-lead">Hello there</p>' );
ok( 'trims whitespace', lead( '   Hello   ' ), '<p class="kt-lead">Hello</p>' );
ok( 'empty content renders nothing', lead( '' ), '' );
ok( 'whitespace only renders nothing', lead( "  \n  " ), '' );

// =========================================================================
echo "--- kt_details ---\n";
function details( $a ) { return call_user_func( $GLOBALS['SC']['kt_details'], $a ); }

$full = details( array(
	'length' => '28 cm', 'height' => '19 cm', 'width' => '11 cm', 'weight' => '650 g',
	'notes'  => 'Fits a phone, wallet, makeup and your daily essentials | Size may vary',
	'fits'   => 'phone',
	'material' => 'PU leather', 'care' => 'Wipe with a damp cloth',
	'avoid' => 'Direct sunlight', 'storage' => 'Keep in the dust bag',
) );
ok( 'renders both columns', substr_count( $full, '<div><h4 class="kt-h">' ), 2 );
ok( 'four dimensions', substr_count( $full, '<div class="kt-spec">' ), 1 );
ok( 'each dimension has an icon', substr_count( $full, '<svg' ) >= 4, true );
ok( 'dimension value shown', strpos( $full, '<b>28 cm</b>' ) !== false, true );
ok( 'dimension label shown', strpos( $full, '<em>Length</em>' ) !== false, true );

// Notes split on a PIPE. Comma-splitting would have cut the first note into three.
ok( 'notes split on the pipe, not commas', substr_count( $full, '<li>' ), 2 );
ok( 'a comma inside a note survives',
	strpos( $full, '<li>Fits a phone, wallet, makeup and your daily essentials</li>' ) !== false, true );

// fits="phone" means phone yes, the others no.
ok( 'fit row rendered', substr_count( $full, 'class="kt-fits"' ), 1 );
ok( 'one tick', substr_count( $full, '#2f6b42' ), 1 );
ok( 'two crosses', substr_count( $full, '#9a3b30' ), 2 );
ok( 'tick and cross carry a text label too', substr_count( $full, 'screen-reader-text' ), 3 );

// Columns collapse when empty.
$only_care = details( array( 'material' => 'PU leather' ) );
ok( 'no dimensions means one column', substr_count( $only_care, '<div><h4 class="kt-h">' ), 1 );
ok( 'the remaining column is Materials', strpos( $only_care, 'Materials' ) !== false, true );
ok( 'no empty spec block', strpos( $only_care, 'kt-spec' ), false );

$only_size = details( array( 'length' => '28 cm' ) );
ok( 'no materials means one column', substr_count( $only_size, '<div><h4 class="kt-h">' ), 1 );
ok( 'no empty care block', strpos( $only_size, 'kt-care' ), false );

ok( 'nothing at all renders nothing', details( array() ), '' );
ok( 'fits empty hides the device row', strpos( details( array( 'length' => '1 cm' ) ), 'kt-fits' ), false );
ok( 'an unknown device is skipped',
	substr_count( details( array( 'length' => '1', 'fits' => 'phone', 'devices' => 'phone,submarine' ) ), 'kt-fits' ), 1 );

// Escaping.
ok( 'a value cannot inject markup',
	strpos( details( array( 'length' => '<script>x</script>' ) ), '<script>' ), false );
ok( 'a note cannot inject markup',
	strpos( details( array( 'notes' => '<script>x</script>' ) ), '<script>' ), false );

// Custom headings.
ok( 'headings can be renamed',
	strpos( details( array( 'length' => '1', 'size_title' => 'Dimensions' ) ), 'Dimensions' ) !== false, true );

// =========================================================================
echo "--- kt_frames ---\n";
function frames( $a ) { return call_user_func( $GLOBALS['SC']['kt_frames'], $a ); }

$f = frames( array( 'ids' => '1234, 1235, 1236' ) );
ok( 'three photos render', substr_count( $f, '<img' ), 3 );
ok( 'has the strip wrapper', substr_count( $f, 'kt-frames__row' ), 1 );
ok( 'default heading', strpos( $f, 'In Frame' ) !== false, true );
ok( 'default sub-line', strpos( $f, 'judge the size' ) !== false, true );

ok( 'a missing attachment is skipped, not left as a gap',
	substr_count( frames( array( 'ids' => '1234, 999999, 1235' ) ), '<img' ), 2 );
ok( 'all-missing renders nothing', frames( array( 'ids' => '999998, 999999' ) ), '' );
ok( 'no ids renders nothing', frames( array( 'ids' => '' ) ), '' );
ok( 'non-numeric ids render nothing', frames( array( 'ids' => 'abc, def' ) ), '' );
ok( 'heading can be emptied', strpos( frames( array( 'ids' => '1234', 'title' => '' ) ), '<h4' ), false );
ok( 'title is escaped',
	strpos( frames( array( 'ids' => '1234', 'title' => '<script>x</script>' ) ), '<script>' ), false );

// =========================================================================
echo "--- registration ---\n";
foreach ( array( 'kt_lead', 'kt_details', 'kt_frames' ) as $sc ) {
	ok( "{$sc} registered", isset( $GLOBALS['SC'][ $sc ] ), true );
}
$fcss = Kohthai_Product_Blocks::css();
ok( 'frames css present', strpos( $fcss, '.kt-frames__row' ) !== false, true );
ok( 'screen-reader helper present', strpos( $fcss, 'screen-reader-text' ) !== false, true );


// =========================================================================
echo "--- plugin harmony (Smart Delivery restyle) ---
";
reset_opts();
$h = Kohthai_Product_Blocks::css();
// Comments quote the OLD colours while explaining them — assert against rules only.
$hr = preg_replace( '#/\*.*?\*/#s', '', $h );

ok( 'targets the delivery container', strpos( $h, '.kohthai-delivery-estimate-container{' ) !== false, true );
ok( 'warm tile, not cool grey', strpos( $h, 'background:#faf7f3 !important' ) !== false, true );
ok( 'the old cool grey is gone', strpos( $hr, '#f7f7f7' ), false );

// The plugin's <i> carries an inline style="margin-right:.4em", so the reset must be !important.
ok( 'icon margin forced past the inline style', strpos( $h, 'margin:1px 0 0 0 !important' ) !== false, true );
ok( 'glyph hidden, icon from a background', strpos( $h, 'font-size:0 !important' ) !== false, true );
ok( 'truck is an inline svg data uri', strpos( $h, 'data:image/svg+xml' ) !== false, true );
ok( 'icon uses brand brown', strpos( $h, '%23654321' ) !== false, true );

// Backorder: amber, not the old alarm red.
ok( 'backorder is amber', strpos( $h, 'background:#fdf6e7 !important' ) !== false, true );
ok( 'alarm red is gone', strpos( $hr, '#b30000' ), false );
ok( 'pink alarm background is gone', strpos( $hr, '#fff0f0' ), false );
ok( 'backorder icon gets its own colour', strpos( $h, '%238a6110' ) !== false, true );

// The toggle removes only this block.
update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'css_plugin_harmony' => 0 ) ) );
$noh = Kohthai_Product_Blocks::css();
ok( 'harmony off removes the delivery restyle', strpos( $noh, '.kohthai-delivery-estimate-container' ), false );
ok( 'harmony off keeps the trust row', strpos( $noh, '.kt-trust{' ) !== false, true );
ok( 'harmony off keeps the feature row', strpos( $noh, '.kt-features{' ) !== false, true );
reset_opts();

// =========================================================================
echo "--- stock line ---
";
reset_opts();

// The gap it exists to fill: WooCommerce prints nothing for a plainly in-stock product.
set_product( false, true, '' );
$sl = stock_line();
ok( 'fills the silence when Woo says nothing', strpos( $sl, 'kt-stock' ) !== false, true );
ok( 'uses the configured wording', strpos( $sl, 'In stock - ready to ship' ) !== false, true );
ok( 'renders a status dot', strpos( $sl, 'kt-stock__dot' ) !== false, true );

// Where WooCommerce DOES speak, stay silent — its line is variation aware and ours is not.
set_product( false, true, '<p class="stock in-stock">Only 2 left in stock</p>' );
ok( 'silent when WooCommerce already prints a line', stock_line(), '' );

// Backorder is covered by the delivery estimate directly underneath.
set_product( true, true, '' );
ok( 'silent on backorder', stock_line(), '' );

// Out of stock: the button is already disabled and labelled.
set_product( false, false, '' );
ok( 'silent when out of stock', stock_line(), '' );

// Guards.
clear_product();
ok( 'silent with no product in scope', stock_line(), '' );

set_product( false, true, '' );
update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'enable_stock_line' => 0 ) ) );
ok( 'the toggle switches it off', stock_line(), '' );

update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'stock_text' => '   ' ) ) );
ok( 'blank wording renders nothing', stock_line(), '' );

update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'stock_text' => '<script>x</script>In stock' ) ) );
ok( 'wording is escaped', strpos( stock_line(), '<script>' ), false );
reset_opts();
clear_product();

// Styling: ours and WooCommerce's own line must match.
$scss = Kohthai_Product_Blocks::css();
ok( 'stock css present', strpos( $scss, '.kt-stock{' ) !== false, true );
ok( 'woo stock line is styled to match', strpos( $scss, 'div.product p.stock' ) !== false, true );
ok( 'in-stock is green', strpos( $scss, 'p.stock.in-stock{color:#2f6b42}' ) !== false, true );
ok( 'backorder is amber', strpos( $scss, 'p.stock.available-on-backorder{color:#8a6110}' ) !== false, true );

// =========================================================================
echo "\n--- key features ---\n";

function kf( $m = '', $sz = '', $cl = '' ) {
	$GLOBALS['META'] = array( '_kt_kf_material' => $m, '_kt_kf_size' => $sz, '_kt_kf_closure' => $cl );
	ob_start();
	Kohthai_Product_Blocks::render_key_features();
	return ob_get_clean();
}

set_product( false );
$k = kf( 'PU Leather', '28 x 19 x 11 cm', 'Magnetic Flap' );
ok( 'three cards render', substr_count( $k, '<div><em>' ), 3 );
ok( 'labels are fixed and in order', array_values( Kohthai_Product_Blocks::kf_fields() ),
	array( 'Material', 'Size', 'Closure' ) );
ok( 'value appears', strpos( $k, '<b>PU Leather</b>' ) !== false, true );
ok( 'wrapper present', strpos( $k, '<div class="kt-kf">' ) !== false, true );

// A partly filled product must not leave an empty card - three cards of which one is blank
// reads as missing data, which is worse than two cards.
ok( 'blank field is dropped', substr_count( kf( 'PU Leather', '', 'Zipper' ), '<div><em>' ), 2 );
ok( 'all blank renders nothing', kf( '', '', '' ), '' );
ok( 'whitespace-only counts as blank', kf( '   ', '   ', '   ' ), '' );

ok( 'values are escaped', strpos( kf( '<script>x</script>', '', '' ), '<script>' ), false );

// The row emits none of the tags that caused the empty-markup flood.
$k3 = kf( 'a', 'b', 'c' );
ok( 'emits no headings', preg_match( '#<h[1-6]#i', $k3 ), 0 );
ok( 'emits no paragraphs', preg_match( '#<p[ >]#i', $k3 ), 0 );
ok( 'emits no img', preg_match( '#<img#i', $k3 ), 0 );

update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'enable_keyfeatures' => 0 ) ) );
ok( 'switch off renders nothing', kf( 'PU Leather', 'x', 'y' ), '' );
reset_opts();

clear_product();
ok( 'no product renders nothing', kf( 'PU Leather', 'x', 'y' ), '' );
set_product( false );

// The switch must be stored as 0/1, not as the literal posted string.
$san = Kohthai_Product_Blocks::sanitize( array_merge( Kohthai_Product_Blocks::defaults(), array( 'enable_keyfeatures' => 'on' ) ) );
ok( 'enable_ sanitizes to 1', $san['enable_keyfeatures'], 1 );
$san = Kohthai_Product_Blocks::sanitize( array_merge( Kohthai_Product_Blocks::defaults(), array( 'enable_about_move' => '' ) ) );
ok( 'enable_ sanitizes to 0', $san['enable_about_move'], 0 );

$kcss = Kohthai_Product_Blocks::css();
ok( 'key features css present', strpos( $kcss, '.kt-kf{' ) !== false, true );
// repeat(3,1fr), never auto-fit: the row exists to be compared side by side, so a 2+1
// reflow would defeat it.
ok( 'cards never reflow to two rows', strpos( $kcss, 'grid-template-columns:repeat(3,1fr)' ) !== false, true );

// =========================================================================
echo "\n--- about (short description moved below the button) ---\n";

class KT_Post { public $post_excerpt = ''; public function __construct( $e ) { $this->post_excerpt = $e; } }
function about( $excerpt ) {
	$GLOBALS['post'] = new KT_Post( $excerpt );
	ob_start();
	Kohthai_Product_Blocks::render_about();
	return ob_get_clean();
}

$a = about( '<p>A roomy crossbody bag.</p>' );
ok( 'wrapper renders', strpos( $a, '<div class="kt-about">' ) !== false, true );
ok( 'heading renders', strpos( $a, 'About this bag' ) !== false, true );
ok( 'clamped by default', strpos( $a, 'kt-about__text kt-clamp' ) !== false, true );
ok( 'body text survives intact', strpos( $a, '<p>A roomy crossbody bag.</p>' ) !== false, true );
ok( 'toggle is present', strpos( $a, 'kt-about__more' ) !== false, true );
// Hidden until the script confirms the text actually overflows, so a two-line description
// does not get a pointless "View more" underneath it.
ok( 'toggle starts hidden', strpos( $a, 'hidden data-more=' ) !== false, true );

ok( 'empty excerpt renders nothing', about( '' ), '' );
ok( 'whitespace excerpt renders nothing', about( '   ' ), '' );
ok( 'markup with no words renders nothing', about( '<p></p>' ), '' );

update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'about_title' => '' ) ) );
ok( 'blank title drops the heading', strpos( about( '<p>x</p>' ), '<h4' ), false );
reset_opts();

update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'about_title' => '<script>x</script>' ) ) );
ok( 'title is escaped', strpos( about( '<p>x</p>' ), '<script>' ), false );
reset_opts();

// The relocation must REMOVE WooCommerce's own excerpt hook. If it only added ours, the
// description would render twice - once above the button and once below.
$GLOBALS['REMOVED'] = array();
$GLOBALS['IS_PRODUCT'] = true;
Kohthai_Product_Blocks::relocate_short_description();
$removed = array_map( function ( $c ) { return $c[1]; }, $GLOBALS['REMOVED'] );
ok( 'woo excerpt hook is removed', in_array( 'woocommerce_template_single_excerpt', $removed, true ), true );

$GLOBALS['REMOVED'] = array();
update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'enable_about_move' => 0 ) ) );
Kohthai_Product_Blocks::relocate_short_description();
ok( 'switch off leaves woo alone', $GLOBALS['REMOVED'], array() );
reset_opts();

$GLOBALS['REMOVED'] = array();
$GLOBALS['IS_PRODUCT'] = false;
Kohthai_Product_Blocks::relocate_short_description();
ok( 'nothing removed off a product page', $GLOBALS['REMOVED'], array() );
$GLOBALS['IS_PRODUCT'] = true;

// WP Rocket delays and minifies JS on this site. Without the guards the toggle is dead
// until the visitor interacts - exactly the bug that left the chat buttons pointing at '#'.
ob_start();
Kohthai_Product_Blocks::about_script();
$js = ob_get_clean();
ok( 'script carries the WP Rocket guards',
	strpos( $js, '<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">' ) !== false, true );
ok( 'script uses no jQuery', strpos( $js, 'jQuery' ), false );

update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'enable_about_move' => 0 ) ) );
ob_start();
Kohthai_Product_Blocks::about_script();
ok( 'no script when switched off', ob_get_clean(), '' );
reset_opts();

$acss = Kohthai_Product_Blocks::css();
// Strip comments first: the explanatory comments quote values the assertions look for, and
// that false positive has bitten this harness twice already.
$acss_r = preg_replace( '#/\*.*?\*/#s', '', $acss );
ok( 'about css present', strpos( $acss_r, '.kt-about__text{' ) !== false, true );
ok( 'clamp is three lines', strpos( $acss_r, '-webkit-line-clamp:3' ) !== false, true );
// Visual clamp only - the full text stays in the DOM so Google still indexes it.
ok( 'clamp does not hide the text', strpos( $acss_r, '.kt-about__text.kt-clamp{display:none' ), false );
ok( 'toggle overrides the theme button style', strpos( $acss_r, '.kt-about__more{' ) !== false, true );

clear_product();

// =========================================================================
echo "\n--- tab order ---\n";

// WooCommerce's real defaults.
function woo_tabs() {
	return array(
		'description'   => array( 'title' => 'Description', 'priority' => 10 ),
		'reviews'       => array( 'title' => 'Reviews (1)', 'priority' => 30 ),
		'ux_global_tab' => array( 'title' => 'Delivery & Returns', 'priority' => 50 ),
	);
}
function tab_order( $tabs ) {
	uasort( $tabs, function ( $a, $b ) { return $a['priority'] <=> $b['priority']; } );
	return array_keys( $tabs );
}

ok( 'before: terms sit below the reviews', tab_order( woo_tabs() ),
	array( 'description', 'reviews', 'ux_global_tab' ) );

$ordered = Kohthai_Product_Blocks::order_tabs( woo_tabs() );
// The reviews list grows every time a customer leaves one, so anything below it drifts
// further down forever. Terms must not be that thing.
ok( 'after: terms sit above the reviews', tab_order( $ordered ),
	array( 'description', 'ux_global_tab', 'reviews' ) );
ok( 'global tab is priority 25', $ordered['ux_global_tab']['priority'], 25 );

// Everything else must be left exactly as found.
ok( 'description priority untouched', $ordered['description']['priority'], 10 );
ok( 'reviews priority untouched', $ordered['reviews']['priority'], 30 );
ok( 'titles untouched', $ordered['ux_global_tab']['title'], 'Delivery & Returns' );
ok( 'no tab added or dropped', count( $ordered ), 3 );

// The tab only exists when the Customizer field is filled in; the filter must cope.
$none = Kohthai_Product_Blocks::order_tabs( array(
	'description' => array( 'title' => 'Description', 'priority' => 10 ),
) );
ok( 'absent global tab is not invented', array_keys( $none ), array( 'description' ) );
ok( 'empty tab list survives', Kohthai_Product_Blocks::order_tabs( array() ), array() );

// =========================================================================
echo "\n--- accordion: more than one section open ---\n";

function acc_js() {
	ob_start();
	Kohthai_Product_Blocks::accordion_script();
	return ob_get_clean();
}

$aj = acc_js();
ok( 'script renders', $aj !== '', true );
// WP Rocket delays and minifies JS here; without the guards the accordion would be dead until
// the visitor interacts, which is the bug that left the chat buttons pointing at '#'.
ok( 'carries the WP Rocket guards',
	strpos( $aj, '<script data-no-optimize="1" data-no-minify="1" data-cfasync="false">' ) !== false, true );

// Capture phase is the whole mechanism. Flatsome binds its handler to each .accordion-title,
// and a capture listener on an ancestor is the only thing guaranteed to run first - which is
// also why nothing has to be unbound, so a theme rebind cannot undo it.
ok( 'listens in the capture phase', substr_count( $aj, '},true);' ), 1 );
ok( 'listens on the container, not the title', strpos( $aj, 'acc.addEventListener("click"' ) !== false, true );
ok( 'stops the theme handler', strpos( $aj, 'e.stopPropagation();' ) !== false, true );
ok( 'does not unbind anything', strpos( $aj, '.off(' ), false );

// It must TOGGLE the clicked section only. Any sign of a sweep over siblings would be the
// exact behaviour being removed.
ok( 'toggles the clicked section', strpos( $aj, 't.classList.toggle("active",!open)' ) !== false, true );
ok( 'never closes siblings', strpos( $aj, 'querySelectorAll(".accordion-title")' ), false );
ok( 'keeps aria in step', strpos( $aj, 'aria-expanded' ) !== false, true );

// Sliders inside a panel (the video card) open at zero width unless something tells them to
// re-measure. Flatsome's own handler did this; ours has to as well.
ok( 'nudges sliders to re-layout', strpos( $aj, 'new Event("resize")' ) !== false, true );
ok( 'works without jQuery too', strpos( $aj, 'p.style.display=open?"none":"block"' ) !== false, true );

update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'enable_accordion_multi' => 0 ) ) );
ok( 'switch off renders nothing', acc_js(), '' );
reset_opts();

$GLOBALS['IS_PRODUCT'] = false;
ok( 'nothing off a product page', acc_js(), '' );
$GLOBALS['IS_PRODUCT'] = true;

$san = Kohthai_Product_Blocks::sanitize( array_merge( Kohthai_Product_Blocks::defaults(), array( 'enable_accordion_multi' => 'on' ) ) );
ok( 'switch sanitizes to 1', $san['enable_accordion_multi'], 1 );

// =========================================================================
echo "\n--- feature icons: field placement ---\n";

function feat_field( $where, $items = 'zipper, inner-pocket', $desc = '' ) {
	$GLOBALS['META'] = array( '_kt_features' => $items );
	$GLOBALS['product'] = new WC_Product( false );
	$GLOBALS['product']->desc = $desc;
	return Kohthai_Product_Blocks::product_features_html( $where );
}

// Default is the details block. Under the button the row would be the fourth stacked block
// in a 360px column, below the trust row, delivery estimate and chat buttons.
ok( 'default position is the details block', Kohthai_Product_Blocks::defaults()['features_position'], 'details' );
ok( 'renders in the details block', substr_count( feat_field( 'details' ), '<li>' ), 2 );
ok( 'renders nothing in the summary', feat_field( 'summary' ), '' );

update_option( 'kt_blocks_options', array_merge( Kohthai_Product_Blocks::defaults(), array( 'features_position' => 'summary' ) ) );
ok( 'switched: renders in the summary', substr_count( feat_field( 'summary' ), '<li>' ), 2 );
ok( 'switched: nothing in the details block', feat_field( 'details' ), '' );
reset_opts();

// The row must never appear twice. Before 1.6.3 the field and the shortcode rendered in two
// different places, so using both produced two rows and the editor could not see why.
ok( 'shortcode in the description suppresses the field',
	feat_field( 'details', 'zipper', 'Some copy [kt_features items="zipper"] more copy' ), '' );
ok( 'unrelated shortcodes do not suppress it',
	substr_count( feat_field( 'details', 'zipper', '[kt_details length="1 cm"]' ), '<li>' ), 1 );

ok( 'empty field renders nothing', feat_field( 'details', '' ), '' );
$GLOBALS['product'] = null; unset( $GLOBALS['product'] );
ok( 'no product renders nothing', Kohthai_Product_Blocks::product_features_html( 'details' ), '' );

// The tab wrapper must PRINT FIRST and then run WooCommerce's own callback unchanged.
$GLOBALS['META'] = array( '_kt_features' => 'zipper' );
$GLOBALS['product'] = new WC_Product( false );
$tabs = Kohthai_Product_Blocks::features_into_description( array(
	'description' => array( 'title' => 'Description', 'priority' => 10,
		'callback' => function () { echo 'ORIGINAL-DESCRIPTION'; } ),
) );
ob_start();
call_user_func( $tabs['description']['callback'] );
$out = ob_get_clean();
ok( 'icon row is printed', strpos( $out, 'kt-features' ) !== false, true );
ok( 'original description still runs', strpos( $out, 'ORIGINAL-DESCRIPTION' ) !== false, true );
ok( 'icons come BEFORE the description',
	strpos( $out, 'kt-features' ) < strpos( $out, 'ORIGINAL-DESCRIPTION' ), true );

// A tab set without a description tab must pass through untouched, not fatal.
$only = Kohthai_Product_Blocks::features_into_description( array( 'reviews' => array( 'title' => 'Reviews' ) ) );
ok( 'tabs without a description survive', array_keys( $only ), array( 'reviews' ) );
ok( 'empty tab list survives the wrapper', Kohthai_Product_Blocks::features_into_description( array() ), array() );

// Free text must not reach the option.
$san = Kohthai_Product_Blocks::sanitize( array_merge( Kohthai_Product_Blocks::defaults(), array( 'features_position' => 'nonsense' ) ) );
ok( 'unknown position falls back to details', $san['features_position'], 'details' );
$san = Kohthai_Product_Blocks::sanitize( array_merge( Kohthai_Product_Blocks::defaults(), array( 'features_position' => 'summary' ) ) );
ok( 'summary is accepted', $san['features_position'], 'summary' );

clear_product();

// =========================================================================
echo "\n--- swatch labels and spec icons ---\n";

$css166 = Kohthai_Product_Blocks::css();
// Strip comments first - they quote the old values while explaining the change, and that
// false positive has bitten this harness twice.
$r166 = preg_replace( '#/\*.*?\*/#s', '', $css166 );

// The label is absolutely positioned, so it cannot push neighbours apart - it overlaps them.
// The ONLY thing preventing overlap is label width < swatch pitch. Assert the arithmetic, not
// just the presence of the declarations, so a future tweak to either number has to keep them
// consistent.
// Must be the DOUBLED class. Flatsome sets gap on a plain .ux-swatches at the same
// specificity and loads later, so a single-class selector loses the cascade silently - that
// is the 1.6.6 bug, and this assertion is what would have caught it.
// X-Large is a Customizer setting, and it changes the CLASS. Naming only --large means the
// colour names disappear the moment that setting is changed - which is a silent failure, so
// it gets its own assertion.
ok( 'colour names survive the X-Large setting',
	strpos( $r166, '.ux-swatches--x-large .ux-swatch::after' ) !== false, true );
ok( 'X-Large gets its own gap', 
	preg_match( '/\.ux-swatches\.ux-swatches--x-large\{column-gap:\d+px\}/', $r166 ), 1 );
// The swatch size belongs to the Customizer, not to this plugin.
ok( 'plugin does not override the theme swatch size', strpos( $r166, '--swatch-size' ), false );
ok( 'gap rule outranks the theme',
	preg_match( '/\.ux-swatches\.ux-swatches--large\{column-gap:\d+px\}/', $r166 ), 1 );
ok( 'gap rule is not a bare single-class selector',
	preg_match( '/(^|\})\.ux-swatches\{column-gap/', $r166 ), 0 );
preg_match( '/\.ux-swatches\.ux-swatches--large\{column-gap:(\d+)px\}/', $r166, $g );
preg_match( '/\.ux-swatch::after\{[^}]*width:(\d+)px/', $r166, $w );
$gap = isset( $g[1] ) ? (int) $g[1] : 0;
$labelw = isset( $w[1] ) ? (int) $w[1] : 0;
$pitch = 45 + $gap; // 45px is Flatsome's large swatch, measured on the live page
ok( 'swatch column-gap is set', $gap > 0, true );
ok( 'swatch label width is set', $labelw > 0, true );
ok( 'label is narrower than the pitch, so labels cannot overlap', $labelw < $pitch, true );

// nowrap was the other half of the bug: a long name ignored the width entirely and painted
// over the swatch beside it.
ok( 'label wraps instead of running over its neighbour',
	preg_match( '/\.ux-swatch::after\{[^}]*white-space:normal/', $r166 ), 1 );
ok( 'label no longer uses nowrap',
	preg_match( '/\.ux-swatch::after\{[^}]*white-space:nowrap/', $r166 ), 0 );

$icons166 = Kohthai_Product_Blocks::icons();
// top-handle must not go back to the round drawing - it read as a padlock on a square handbag.
ok( 'top-handle is not the round version', strpos( $icons166['top-handle'][1], '<circle' ), false );
ok( 'top-handle keeps its arch', strpos( $icons166['top-handle'][1], 'a4 4 0 0 1 8 0' ) !== false, true );

// The width icon must differ IN KIND from length and height, or it reads as a second length.
$specs = Kohthai_Product_Blocks::css(); // spec icons are emitted through the shortcode
$det = call_user_func( $GLOBALS['SC']['kt_details'], array( 'length' => '1 cm', 'width' => '2 cm', 'height' => '3 cm' ) );
ok( 'width icon is a 3D box', strpos( $det, 'M15 7.5 20 4.5v11l-5 3' ) !== false, true );
ok( 'width icon is no longer a horizontal arrow', strpos( $det, 'M17 5.5 20 12l-3 6.5' ), false );
ok( 'length is still a horizontal rule', strpos( $det, 'M3 12h18' ) !== false, true );
ok( 'height is still a vertical rule', strpos( $det, 'M12 3v18' ) !== false, true );

// =========================================================================
echo "\n" . str_repeat( '=', 46 ) . "\n";
printf( "  %d passed, %d failed\n", $passed, $failed );
echo str_repeat( '=', 46 ) . "\n";
exit( $failed > 0 ? 1 : 0 );
