<?php
/**
 * Harness for Kohthai Review Photos.
 *
 *   cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file "<abs path>" --skip-themes
 *
 * The behaviour that matters happens in the browser and is tested there against
 * the live product page. This checks the PHP side: that it loads, hooks where it
 * should, prints only on product pages, and never loses the guards.
 */

$GLOBALS['ktrp_pass'] = 0;
$GLOBALS['ktrp_fail'] = 0;

function ktrp_true( $label, $cond ) {
	$GLOBALS[ $cond ? 'ktrp_pass' : 'ktrp_fail' ]++;
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . "\n";
}

// ⚠️ NOT dirname( __FILE__ ): eval-file runs this through eval().
$file = 'C:/Users/Jobayer Hossain/Downloads/Claude session/Kohthai/kohthai-review-photos/kohthai-review-photos.php';
if ( ! class_exists( 'Kohthai_Review_Photos' ) ) {
	require_once $file;
}

echo "\n--- loading ---\n";
ktrp_true( 'the class loads', class_exists( 'Kohthai_Review_Photos' ) );
ktrp_true( 'it prints in the footer', false !== has_action( 'wp_footer', array( 'Kohthai_Review_Photos', 'print_script' ) ) );
ktrp_true( 'it asks WP Rocket not to delay it',
	false !== has_filter( 'rocket_delay_js_exclusions', array( 'Kohthai_Review_Photos', 'exclude_from_rocket_delay' ) ) );

$ex = Kohthai_Review_Photos::exclude_from_rocket_delay( array( 'something-else' ) );
ktrp_true( 'the exclusion keeps what was already there', in_array( 'something-else', $ex, true ) );
ktrp_true( 'and adds its own marker', in_array( 'ktReviewPhotos', $ex, true ) );
ktrp_true( 'a non-array from another plugin does not break it',
	array( 'ktReviewPhotos' ) === Kohthai_Review_Photos::exclude_from_rocket_delay( '' ) );

echo "\n--- where it prints ---\n";
ob_start();
Kohthai_Review_Photos::print_script();
$off = ob_get_clean();
ktrp_true( 'nothing is printed off a product page', '' === $off );

echo "\n--- the script ---\n";
$tag = Kohthai_Review_Photos::script_tag();
// Standing rule: an inline script on this site always carries these.
foreach ( array( 'data-no-optimize="1"', 'data-no-minify="1"', 'data-no-defer="1"', 'data-cfasync="false"' ) as $g ) {
	ktrp_true( "guard present: $g", false !== strpos( $tag, $g ) );
}
ktrp_true( 'the marker WP Rocket matches on is inside the script', false !== strpos( $tag, 'window.ktReviewPhotos = true;' ) );
ktrp_true( 'it targets the review plugin\'s upload action', false !== strpos( $tag, '"cr_upload_local_images_frontend"' ) );
ktrp_true( 'it frees the gallery', false !== strpos( $tag, 'removeAttribute("capture")' ) );
ktrp_true( 'it shrinks to JPEG', false !== strpos( $tag, '"image/jpeg", QUALITY' ) );
ktrp_true( 'it runs before the review plugin\'s own handler (capture phase)', false !== strpos( $tag, '}, true);' ) );
ktrp_true( 'it adds the error handler the plugin never had', false !== strpos( $tag, 'opts.error = function(xhr)' ) );
ktrp_true( 'it catches 200-with-no-attachment instead of letting it crash',
	false !== strpos( $tag, 'var saved = code === 200 && resp.attachment && resp.attachment.id;' ) );

// php -l cannot see inside a heredoc. Count brackets, skipping strings and comments.
$js = substr( $tag, strpos( $tag, '>' ) + 1, -9 );
$depth = array( '{' => 0, '(' => 0, '[' => 0 );
$pair  = array( '}' => '{', ')' => '(', ']' => '[' );
$n = strlen( $js ); $q = null;
for ( $i = 0; $i < $n; $i++ ) {
	$c = $js[ $i ];
	if ( $q ) {
		if ( '\\' === $c ) { $i++; continue; }
		if ( $c === $q ) { $q = null; }
		continue;
	}
	if ( '"' === $c || "'" === $c ) { $q = $c; continue; }
	if ( '/' === $c && $i + 1 < $n && '/' === $js[ $i + 1 ] ) { $e = strpos( $js, "\n", $i ); $i = false === $e ? $n : $e; continue; }
	if ( '/' === $c && $i + 1 < $n && '*' === $js[ $i + 1 ] ) { $e = strpos( $js, '*/', $i + 2 ); $i = false === $e ? $n : $e + 1; continue; }
	if ( isset( $depth[ $c ] ) ) { $depth[ $c ]++; }
	elseif ( isset( $pair[ $c ] ) ) { $depth[ $pair[ $c ] ]--; }
}
ktrp_true( 'the script\'s brackets balance', array( '{' => 0, '(' => 0, '[' => 0 ) === $depth );

echo "\n================================\n";
printf( "  %d passed / %d failed\n", $GLOBALS['ktrp_pass'], $GLOBALS['ktrp_fail'] );
echo "================================\n";
