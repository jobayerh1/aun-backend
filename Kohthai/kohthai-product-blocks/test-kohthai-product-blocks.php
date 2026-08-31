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
function get_post_meta() { return ''; }
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
ok( 'ten icons', count( $icons ), 10 );
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
ok( 'kt-h declared once', substr_count( $css, '.kt-h{' ), 1 );
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
echo "\n" . str_repeat( '=', 46 ) . "\n";
printf( "  %d passed, %d failed\n", $passed, $failed );
echo str_repeat( '=', 46 ) . "\n";
exit( $failed > 0 ? 1 : 0 );
