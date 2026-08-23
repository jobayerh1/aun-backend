<?php
/**
 * Regression harness for the aun-care-promo install bar.
 *
 *   cd ~/wp-local
 *   php -c php.ini wp-cli.phar --path=site eval-file "<path>/test-care-promo.php" --skip-themes
 *
 * Focus is the *suppression* rules, because every one of them is a case where the
 * bar covering the wrong thing costs money: the sticky Add-to-cart button on a
 * product page, or a checkout in progress.
 */

if ( ! function_exists( 'aun_care_promo_defaults' ) ) {
	fwrite( STDERR, "aun-care-promo is not active on this bench.\n" );
	exit( 1 );
}

function tally( $p = 0, $f = 0 ) {
	static $P = 0, $F = 0;
	$P += $p; $F += $f;
	return array( $P, $F );
}
function t( $label, $got, $want ) {
	if ( $got === $want ) { tally( 1, 0 ); echo "  ok    $label\n"; }
	else { tally( 0, 1 ); echo "  FAIL  $label\n        got:  " . var_export( $got, true ) . "\n        want: " . var_export( $want, true ) . "\n"; }
}

/** Render the banner for a given request and pull the JS config back out. */
function render_bar( $request_uri ) {
	$_SERVER['REQUEST_URI'] = $request_uri;
	ob_start();
	aun_app_banner_render();
	return ob_get_clean();
}
function bar_cfg( $html ) {
	if ( ! preg_match( '/var CFG = (\{.*?\});/s', $html, $m ) ) {
		return null;
	}
	return json_decode( $m[1], true );
}

/* ------------------------------------------------------------- A. settings */

echo "\nA. settings\n";

$d = aun_care_promo_defaults();
t( 'skip_products exists and defaults ON', isset( $d['skip_products'] ) ? $d['skip_products'] : null, 1 );

// A key missing from sanitize()'s whitelist is silently dropped on every Save,
// which presents to the admin as "the checkbox won't stay ticked".
foreach ( array( 'banner_enabled', 'avoid_onetap', 'skip_products' ) as $k ) {
	$on  = aun_care_promo_sanitize( array( $k => '1' ) );
	$off = aun_care_promo_sanitize( array() );
	t( "$k survives a Save when ticked", $on[ $k ], 1 );
	t( "$k can be turned off", $off[ $k ], 0 );
}

// Negatives must clamp to the minimum, not flip sign into a plausible value.
$s = aun_care_promo_sanitize( array( 'mute_dismiss' => '-5', 'min_views' => '999', 'scroll_pct' => '-1' ) );
t( 'mute_dismiss -5 clamps to 1 (not 5)', $s['mute_dismiss'], 1 );
t( 'min_views 999 clamps to 10', $s['min_views'], 10 );
t( 'scroll_pct -1 clamps to 0', $s['scroll_pct'], 0 );

/* ---------------------------------------------------------- B. suppression */

echo "\nB. where the bar is suppressed\n";

update_option( AUN_CARE_OPT, array_merge( $d, array( 'banner_enabled' => 1 ) ) );

foreach ( array( '/cart/', '/checkout/', '/my-account/', '/aun-care-app/', '/get-aun-care-app/' ) as $path ) {
	t( "no markup at all on $path", trim( render_bar( $path ) ), '' );
}
t( 'markup IS rendered on an ordinary page', render_bar( '/about-us/' ) !== '', true );

update_option( AUN_CARE_OPT, array_merge( $d, array( 'banner_enabled' => 0 ) ) );
t( 'master switch off -> nothing rendered', trim( render_bar( '/about-us/' ) ), '' );
update_option( AUN_CARE_OPT, $d );

/* ------------------------------------------------------- C. emitted config */

echo "\nC. the config handed to the browser\n";

$cfg = bar_cfg( render_bar( '/about-us/' ) );
t( 'the CFG block is valid JSON', is_array( $cfg ), true );

foreach ( array( 'minViews', 'delay', 'scrollPct', 'muteDismiss', 'muteTap', 'avoidOneTap', 'skipProduct', 'isProduct' ) as $k ) {
	t( "  CFG.$k is present and numeric", isset( $cfg[ $k ] ) && is_int( $cfg[ $k ] ), true );
}
t( 'CFG.isProduct is 0 on a non-product page', $cfg['isProduct'], 0 );
t( 'CFG.skipProduct mirrors the setting', $cfg['skipProduct'], 1 );

// The whole point of the fix: on a product page the browser must be told to hold
// back, because Flatsome's sticky Add to cart bar owns the bottom edge there.
// Make the harness self-sufficient: the bench may only hold the private product
// left behind by the spare-parts tests, which is_product() would not treat as a
// normal shop page.
$product_id = wc_get_products( array( 'limit' => 1, 'return' => 'ids', 'status' => 'publish' ) );
$product_id = $product_id ? $product_id[0] : 0;
if ( ! $product_id ) {
	$p = new WC_Product_Simple();
	$p->set_name( 'Harness Test Projector' );
	$p->set_regular_price( 1000 );
	$p->set_status( 'publish' );
	$product_id = $p->save();
	echo "  note  created a published product ($product_id) for this test\n";
}
if ( $product_id ) {
	$GLOBALS['post'] = get_post( $product_id );
	setup_postdata( $GLOBALS['post'] );
	// is_product() reads the main query, so point it at the product.
	$q = new WP_Query( array( 'p' => $product_id, 'post_type' => 'product' ) );
	$GLOBALS['wp_query'] = $q;
	$q->is_single = true;
	$q->is_singular = true;

	$cfg_p = bar_cfg( render_bar( get_permalink( $product_id ) ) );
	t( 'CFG.isProduct is 1 on a single product page', $cfg_p['isProduct'], 1 );

	wp_reset_postdata();
} else {
	echo "  skip  no product on the bench to test isProduct=1\n";
}

/* -------------------------------------------------- D. the inline JS itself */

echo "\nD. inline script sanity\n";

$html = render_bar( '/about-us/' );

// House rule: bare inline scripts must carry the WP Rocket guards or the
// optimiser will rewrite them.
t( 'inline script carries data-no-optimize', false !== strpos( $html, 'data-no-optimize="1"' ), true );
t( 'inline script carries data-no-minify', false !== strpos( $html, 'data-no-minify="1"' ), true );

// The suppression must sit AFTER the pageview counter, otherwise product-page
// views would not count and on a projector shop the bar would never show at all.
$pos_count = strpos( $html, 'sessionStorage.setItem(VIEWS' );
$pos_skip  = strpos( $html, 'CFG.skipProduct && CFG.isProduct' );
t( 'product skip runs after the pageview is counted',
	( $pos_count !== false && $pos_skip !== false && $pos_skip > $pos_count ), true );

t( 'bottomBusy() guard is present', false !== strpos( $html, 'function bottomBusy' ), true );
t( 'the retry is bounded (impression not spent forever)', false !== strpos( $html, 'tries >= 5' ), true );

// Balanced braces/parens is a weak but real check that the block isn't truncated.
if ( preg_match( '/<script data-no-optimize[^>]*>(.*?)<\/script>/s', $html, $m ) ) {
	$js = $m[1];
	t( 'braces balance in the inline script', substr_count( $js, '{' ), substr_count( $js, '}' ) );
	t( 'parens balance in the inline script', substr_count( $js, '(' ), substr_count( $js, ')' ) );
} else {
	t( 'inline script block found', false, true );
}

list( $pass, $fail ) = tally();
echo "\n----------------------------------------\n";
echo "$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
