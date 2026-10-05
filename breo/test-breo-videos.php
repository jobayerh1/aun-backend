<?php
/**
 * Bench test for product videos (includes/videos.php). Uses its own throwaway
 * product and attachments, so it never collides with real uploads. Run:
 *   php -c ~/wp-local/php.ini ~/wp-local/wp-cli.phar --path=~/wp-local/site eval-file Breo/test-breo-videos.php
 */
$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
function t( $ok, $label ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, "\n";
}

// file-name parsing
$p = breo_bd_video_file_parts( '2026/09/TESTVID1-film-720-1.mp4' );
t( $p && 'TESTVID1' === $p['sku'] && 'film' === $p['slug'] && '720' === $p['role'], 'parses role 720 with a WordPress "-1" duplicate suffix' );
$p = breo_bd_video_file_parts( 'TESTVID1-film-1.mp4' );
t( $p && 'main' === $p['role'], 'a bare "-1" duplicate stays the main file' );
$p = breo_bd_video_file_parts( 'TESTVID1-film-poster.webp' );
t( $p && 'poster' === $p['role'], 'poster role' );
t( null === breo_bd_video_file_parts( 'random-holiday-video.mov' ), 'an unrelated file is ignored' );
t( null === breo_bd_video_file_parts( 'TESTVID1 film.mp4' ), 'names with spaces are ignored' );

// a throwaway product and attachments
$prod = new WC_Product_Simple();
$prod->set_name( 'Video test product' );
$prod->set_sku( 'TESTVID1' );
$prod->set_regular_price( '100' );
$pid = $prod->save();
$prod = wc_get_product( $pid );
$made = array();
$att  = function ( $file, $mime, $meta = array() ) use ( &$made ) {
	$id = wp_insert_attachment( array( 'post_mime_type' => $mime, 'post_title' => $file, 'post_status' => 'inherit' ), '2026/09/' . $file );
	update_post_meta( $id, '_wp_attached_file', '2026/09/' . $file );
	if ( $meta ) {
		wp_update_attachment_metadata( $id, $meta );
	}
	$made[] = $id;
	return $id;
};
$att( 'TESTVID1-reel.mp4', 'video/mp4', array( 'width' => 1080, 'height' => 1920, 'length' => 58 ) );
$att( 'TESTVID1-reel-720.mp4', 'video/mp4', array( 'width' => 720, 'height' => 1280, 'length' => 58 ) );
$att( 'TESTVID1-reel-poster.webp', 'image/webp' );
$att( 'TESTVID1-film.mp4', 'video/mp4', array( 'width' => 1280, 'height' => 720, 'length' => 75 ) );
$att( 'TESTVID1-film-loop.mp4', 'video/mp4', array( 'width' => 1280, 'height' => 720, 'length' => 9 ) );
$att( 'TESTVID1-film-poster.webp', 'image/webp' );
$att( 'TESTVID1-film-poster.mp4', 'video/mp4', array( 'width' => 10, 'height' => 10 ) ); // a "poster" that is a video: ignored
$att( 'TESTVID10-film.mp4', 'video/mp4', array( 'width' => 1920, 'height' => 1080 ) );   // another SKU that merely starts the same

$v = breo_bd_product_videos( $prod );
t( 2 === count( $v ), 'two videos grouped from seven files (got ' . count( $v ) . ')' );
t( ! $v[0]['vertical'] && 'TESTVID1-film' === $v[0]['stem'], 'the landscape film leads' );
t( $v[1]['vertical'] && '' !== $v[1]['url720'] && '' !== $v[1]['poster'], 'portrait video keeps its phone version and poster' );
t( '1:15' === $v[0]['length'] && '0:58' === $v[1]['length'], 'lengths formatted (1:15, 0:58)' );
t( false !== strpos( $v[0]['loop'], 'TESTVID1-film-loop.mp4' ), 'loop matched' );
t( false === strpos( $v[0]['poster'], '.mp4' ), 'a video named -poster is not used as the poster' );
t( false !== strpos( breo_bd_video_ambient_src( $v[0] ), '-loop.mp4' ), 'autoplay uses the loop when there is one' );
t( false !== strpos( breo_bd_video_ambient_src( $v[1] ), '-720.mp4' ), 'otherwise the phone version, muted' );

// rendering
$d    = array( 'short_name' => 'Test', 'tagline' => 'Test tagline', 'accent' => '#000' );
$wide = breo_bd_sec_video( $prod, $d );
t( false !== strpos( $wide, 'breo-vid is-wide' ) && false !== strpos( $wide, 'Watch the film' ), 'landscape renders the cinematic band' );
t( false !== strpos( $wide, 'preload="none"' ) && false !== strpos( $wide, ' muted ' ), 'the band loop is muted and loads nothing up front' );
t( false !== strpos( $wide, 'data-breo-play=' ) && false !== strpos( $wide, 'TESTVID1-film.mp4' ), 'play button carries the full film' );
$hero = breo_bd_film_button( $prod );
t( false !== strpos( $hero, 'Watch the film' ) && false !== strpos( $hero, '1:15' ), 'hero button with length' );

// schema
$GLOBALS['wp_the_query']->query( array( 'p' => $pid, 'post_type' => 'product' ) );
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
$data = apply_filters( 'rank_math/json_ld', array(), null );
t( isset( $data['breoVideo'] ) && 'VideoObject' === $data['breoVideo']['@type'] && 'PT1M15S' === $data['breoVideo']['duration'], 'VideoObject with ISO duration PT1M15S' );

// the owner can hide them
$prod->update_meta_data( '_breo_videos_hide', 'yes' );
$prod->save_meta_data();
$static_reset = wc_get_product( $pid ); // new object, but the per-request cache is keyed by ID
t( 'yes' === $static_reset->get_meta( '_breo_videos_hide' ), 'hide flag saved' );

// tidy up
foreach ( $made as $id ) {
	wp_delete_post( $id, true );
}
wp_delete_post( $pid, true );
echo "\n", $GLOBALS['pass'], ' passed, ', $GLOBALS['fail'], " failed\n";
