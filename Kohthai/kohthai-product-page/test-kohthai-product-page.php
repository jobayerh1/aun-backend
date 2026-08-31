<?php
/**
 * Standalone test harness for Kohthai Product Page v1.0.0.
 *
 * Run:  php test-kohthai-product-page.php
 *
 * No WordPress required — the handful of WP/Woo functions the tested methods
 * touch are shimmed below. Covers the three places with real logic:
 *   strip_empty_markup()          — the empty-heading/paragraph stripper
 *   sort_related_in_stock_first() — related-product ordering
 *   sanitize()                    — the settings whitelist
 */

// ---------------------------------------------------------------- WP shims --

define( 'ABSPATH', __DIR__ . '/' );

function plugin_dir_path( $f ) {
	return dirname( $f ) . '/';
}
function plugin_dir_url( $f ) {
	return 'https://example.test/wp-content/plugins/' . basename( dirname( $f ) ) . '/';
}
function add_action() {}
function add_filter() {}

function wp_strip_all_tags( $string, $remove_breaks = false ) {
	$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
	$string = strip_tags( $string );
	if ( $remove_breaks ) {
		$string = preg_replace( '/[\r\n\t ]+/', ' ', $string );
	}
	return trim( $string );
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function sanitize_text_field( $s ) {
	return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $s ) ) );
}
function esc_url_raw( $u ) {
	$u = trim( (string) $u );
	return preg_match( '#^https?://#i', $u ) ? $u : '';
}
function get_option( $k, $d = false ) {
	return $d;
}

// Fake WooCommerce products for the related-sort test.
class WC_Product {
	private $id;
	private $in_stock;
	public function __construct( $id, $in_stock = true ) {
		$this->id       = $id;
		$this->in_stock = $in_stock;
	}
	public function is_in_stock() {
		return $this->in_stock;
	}
	public function get_id() {
		return $this->id;
	}
}

$GLOBALS['kt_test_stock'] = array();
function wc_get_product( $id ) {
	if ( ! isset( $GLOBALS['kt_test_stock'][ $id ] ) ) {
		return false;
	}
	return new WC_Product( $id, $GLOBALS['kt_test_stock'][ $id ] );
}

require_once __DIR__ . '/kohthai-product-page.php';

// --------------------------------------------------------------- test rig --

$passed = 0;
$failed = 0;

function ok( $label, $got, $want ) {
	global $passed, $failed;

	if ( $got === $want ) {
		$passed++;
		return;
	}

	$failed++;
	echo "FAIL  {$label}\n";
	echo '      want: ' . var_export( $want, true ) . "\n";
	echo '      got : ' . var_export( $got, true ) . "\n";
}

$kt = KT_Product_Page::instance();

// =========================================================================
// strip_empty_markup()
// =========================================================================

echo "--- strip_empty_markup ---\n";

ok(
	'empty h5 is removed',
	$kt->strip_empty_markup( '<h5></h5>' ),
	''
);

ok(
	'h5 with text survives',
	$kt->strip_empty_markup( '<h5>Adjustable Strap</h5>' ),
	'<h5>Adjustable Strap</h5>'
);

ok(
	'h5 holding only &nbsp; is removed',
	$kt->strip_empty_markup( '<h5>&nbsp;</h5>' ),
	''
);

ok(
	'h5 holding a real non-breaking space byte is removed',
	$kt->strip_empty_markup( "<h5>\xc2\xa0</h5>" ),
	''
);

ok(
	'h5 with a class attribute is still matched',
	$kt->strip_empty_markup( '<h5 class="uppercase"></h5>' ),
	''
);

ok(
	'empty p is removed',
	$kt->strip_empty_markup( '<p></p>' ),
	''
);

ok(
	'p with whitespace only is removed',
	$kt->strip_empty_markup( "<p>\n  \t </p>" ),
	''
);

// The exact shape measured on the live site: Flatsome wraps title in an h5,
// and the author's title contained its own h5, so an empty one is emitted.
ok(
	'the real featured_box pattern keeps only the real heading',
	$kt->strip_empty_markup(
		'<div class="icon-box-text last-reset"><h5 class="uppercase"><p></p> </h5><h5>Adjustable Strap</h5> <p>&nbsp;</p> <p>&nbsp;</p></div>'
	),
	'<div class="icon-box-text last-reset"><h5>Adjustable Strap</h5>  </div>'
);

// Media must keep its wrapper alive — an icon-only heading is not "empty".
ok(
	'h5 wrapping an img survives',
	$kt->strip_empty_markup( '<h5><img src="x.png"></h5>' ),
	'<h5><img src="x.png"></h5>'
);

ok(
	'p wrapping an iframe survives',
	$kt->strip_empty_markup( '<p><iframe src="x"></iframe></p>' ),
	'<p><iframe src="x"></iframe></p>'
);

ok(
	'p wrapping a link survives even with no text',
	$kt->strip_empty_markup( '<p><a href="/x"></a></p>' ),
	'<p><a href="/x"></a></p>'
);

ok(
	'divs are never touched',
	$kt->strip_empty_markup( '<div class="gap-element"></div>' ),
	'<div class="gap-element"></div>'
);

ok(
	'multiline empty p is removed',
	$kt->strip_empty_markup( "<p>\n</p>" ),
	''
);

ok(
	'h1..h6 are all handled',
	$kt->strip_empty_markup( '<h1></h1><h2></h2><h3></h3><h4></h4><h5></h5><h6></h6>' ),
	''
);

ok(
	'mixed run keeps only what has content',
	$kt->strip_empty_markup( '<p></p><p>Real copy.</p><p>&nbsp;</p><h5></h5><h5>Zipper</h5>' ),
	'<p>Real copy.</p><h5>Zipper</h5>'
);

ok(
	'empty string passes through',
	$kt->strip_empty_markup( '' ),
	''
);

ok(
	'non-string input is returned untouched',
	$kt->strip_empty_markup( null ),
	null
);

ok(
	'array input is returned untouched',
	$kt->strip_empty_markup( array( 'x' ) ),
	array( 'x' )
);

ok(
	'content with no headings at all is unchanged',
	$kt->strip_empty_markup( 'Just some bare text.' ),
	'Just some bare text.'
);

// Guards against over-matching: a <p> inside a kept <p> is malformed anyway,
// but the stripper must not eat a heading that merely LOOKS empty because a
// nested tag carries the text.
ok(
	'h5 whose text sits in a nested span survives',
	$kt->strip_empty_markup( '<h5><span>Inner Pocket</span></h5>' ),
	'<h5><span>Inner Pocket</span></h5>'
);

ok(
	'uppercase tag names are matched (case-insensitive)',
	$kt->strip_empty_markup( '<H5></H5>' ),
	''
);

// =========================================================================
// sort_related_in_stock_first()
// =========================================================================

echo "--- sort_related_in_stock_first ---\n";

$GLOBALS['kt_test_stock'] = array(
	101 => false, // out of stock — this is the one that was leading the row
	102 => true,
	103 => true,
	104 => false,
	105 => true,
);

ok(
	'out-of-stock products move to the end, order otherwise preserved',
	$kt->sort_related_in_stock_first( array( 101, 102, 103, 104, 105 ) ),
	array( 102, 103, 105, 101, 104 )
);

ok(
	'an all-in-stock list is left alone',
	$kt->sort_related_in_stock_first( array( 102, 103, 105 ) ),
	array( 102, 103, 105 )
);

ok(
	'an all-out-of-stock list is left alone',
	$kt->sort_related_in_stock_first( array( 101, 104 ) ),
	array( 101, 104 )
);

ok(
	'a single id is returned untouched',
	$kt->sort_related_in_stock_first( array( 101 ) ),
	array( 101 )
);

ok(
	'an empty list is returned untouched',
	$kt->sort_related_in_stock_first( array() ),
	array()
);

ok(
	'a non-array is returned untouched',
	$kt->sort_related_in_stock_first( 'nope' ),
	'nope'
);

ok(
	'unknown product ids are treated as in stock rather than dropped',
	$kt->sort_related_in_stock_first( array( 999, 101 ) ),
	array( 999, 101 )
);

// =========================================================================
// sanitize()
// =========================================================================

echo "--- sanitize ---\n";

$clean = $kt->sanitize(
	array(
		'enable_video_fix' => '1',
		'enable_trust'     => '',
		'whatsapp'         => '+880 963-807 8888',
		'messenger'        => 'https://m.me/kohthai',
		'returns_url'      => '/returns_refund/',
		'trust_1_title'    => '  Cash on Delivery  ',
		'evil_key'         => 'should not survive',
	)
);

ok( 'checked toggle becomes 1', $clean['enable_video_fix'], 1 );
ok( 'unchecked toggle becomes 0', $clean['enable_trust'], 0 );
ok( 'missing toggle defaults to 0', $clean['enable_sticky'], 0 );
ok( 'whatsapp is reduced to digits', $clean['whatsapp'], '8809638078888' );
ok( 'messenger url survives', $clean['messenger'], 'https://m.me/kohthai' );
ok( 'relative returns path is allowed', $clean['returns_url'], '/returns_refund/' );
ok( 'text fields are trimmed', $clean['trust_1_title'], 'Cash on Delivery' );
ok( 'unknown keys are dropped', isset( $clean['evil_key'] ), false );
ok( 'every default key is present', count( array_diff_key( KT_Product_Page::defaults(), $clean ) ), 0 );

$clean2 = $kt->sanitize(
	array(
		'messenger'   => 'javascript:alert(1)',
		'returns_url' => 'javascript:alert(1)',
	)
);
ok( 'javascript: messenger is rejected', $clean2['messenger'], '' );
ok( 'javascript: returns url is rejected', $clean2['returns_url'], '' );

ok( 'a non-array input falls back to defaults', $kt->sanitize( 'garbage' ), KT_Product_Page::defaults() );

// ------------------------------------------------------------------ report --

echo "\n";
echo str_repeat( '=', 46 ) . "\n";
printf( "  %d passed, %d failed\n", $passed, $failed );
echo str_repeat( '=', 46 ) . "\n";

exit( $failed > 0 ? 1 : 0 );
