<?php
/**
 * Bench test for WebP importing (includes/admin.php). Run:
 *   php -c ~/wp-local/php.ini ~/wp-local/wp-cli.phar --path=~/wp-local/site eval-file Breo/test-breo-webp.php
 */
$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
function t( $ok, $label ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, "\n";
}
require_once ABSPATH . 'wp-admin/includes/image.php';
if ( ! function_exists( 'breo_bd_import_media_item' ) ) {
	require_once BREO_BD_DIR . 'includes/admin.php'; // only loaded in wp-admin normally
}

// a local source file so the test never depends on the network
$src = wp_upload_dir()['basedir'] . '/breo-webp-source.jpg';
$im  = imagecreatetruecolor( 1200, 800 );
for ( $i = 0; $i < 1200; $i += 4 ) {
	imagefilledrectangle( $im, $i, 0, $i + 3, 800, imagecolorallocate( $im, $i % 255, ( $i * 3 ) % 255, 120 ) );
}
imagejpeg( $im, $src, 92 );
imagedestroy( $im );
$plugin_copy = BREO_BD_DIR . 'assets/test-source.jpg';
copy( $src, $plugin_copy );

t( breo_bd_webp_on(), 'server can write WebP and the setting is on' );

$spec = array( 'local' => 'assets/test-source.jpg', 'alt' => 'Breo test image' );
$pid  = wc_get_product_id_by_sku( 'N990000631' );

// 1. fresh import -> webp
$id = breo_bd_import_media_item( $pid, 'TEST', 'unit', $spec, 1 );
t( ! is_wp_error( $id ) && $id > 0, 'import returned an attachment' );
$file = get_attached_file( $id );
t( 'webp' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ), 'file stored as .webp' );
t( 'image/webp' === get_post_mime_type( $id ), 'mime type is image/webp' );
$meta = wp_get_attachment_metadata( $id );
$sizes_webp = array_filter( (array) ( $meta['sizes'] ?? array() ), function ( $s ) {
	return 'image/webp' !== ( $s['mime-type'] ?? '' );
} );
t( ! empty( $meta['sizes'] ) && ! $sizes_webp, 'generated thumbnail sizes are WebP too (' . count( $meta['sizes'] ?? array() ) . ')' );
t( 'Breo test image' === get_post_meta( $id, '_wp_attachment_image_alt', true ), 'alt text kept' );
$jpg_bytes  = filesize( $src );
$webp_bytes = filesize( $file );
t( $webp_bytes < $jpg_bytes, sprintf( 'smaller than the JPEG source (%dKB -> %dKB)', $jpg_bytes / 1024, $webp_bytes / 1024 ) );

// 2. second import reuses it, no duplicate
$again = breo_bd_import_media_item( $pid, 'TEST', 'unit', $spec, 1 );
t( (int) $again === (int) $id, 'existing import is reused, not downloaded twice' );

// 3. an old JPEG import is replaced only when the switch is on
$old = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'old jpeg', 'post_status' => 'inherit' ), $src );
update_post_meta( $old, '_breo_src', 'local:assets/test-source.jpg' );
update_post_meta( $old, '_wp_attachment_image_alt', 'Old alt text' );
unset( $GLOBALS['breo_bd_webp_force'] );
$keep = breo_bd_import_media_item( $pid, 'TEST', 'old', array( 'local' => 'assets/test-source.jpg' ), 1 );
t( (int) $keep === (int) $old, 'without the switch, the old JPEG is left alone' );

$GLOBALS['breo_bd_webp_force'] = true;
$new = breo_bd_import_media_item( $pid, 'TEST', 'old', array( 'local' => 'assets/test-source.jpg' ), 1 );
t( (int) $new !== (int) $old, 'with the switch, the JPEG is re-imported' );
t( 'webp' === strtolower( pathinfo( get_attached_file( $new ), PATHINFO_EXTENSION ) ), 'replacement is WebP' );
t( null === get_post( $old ), 'old attachment deleted' );
t( ! file_exists( $src ), 'old file removed from disk' );
t( 'Old alt text' === get_post_meta( $new, '_wp_attachment_image_alt', true ), 'owner-written alt text carried over' );

// 4. switch off -> stays JPEG
$saved            = get_option( 'breo_bd_settings', array() );
$off              = is_array( $saved ) ? $saved : array();
$off['media_webp'] = 'no';
update_option( 'breo_bd_settings', $off );
t( ! breo_bd_webp_on(), 'setting off disables conversion' );
// its own source file: the importer keys on the source, so reusing one would just return the earlier import
$plain_copy = BREO_BD_DIR . 'assets/test-source-2.jpg';
copy( $plugin_copy, $plain_copy );
unset( $GLOBALS['breo_bd_webp_force'] );
$plain = breo_bd_import_media_item( $pid, 'TEST', 'plain', array( 'local' => 'assets/test-source-2.jpg' ), 1 );
t( ! is_wp_error( $plain ) && 'jpg' === strtolower( pathinfo( get_attached_file( $plain ), PATHINFO_EXTENSION ) ), 'with it off, the original format is kept' );
update_option( 'breo_bd_settings', $saved );

foreach ( array( $id, $new, $plain ) as $x ) {
	if ( $x && ! is_wp_error( $x ) ) {
		wp_delete_attachment( $x, true );
	}
}
unlink( $plugin_copy );
unlink( $plain_copy );
unset( $GLOBALS['breo_bd_webp_force'] );
echo "\n", $GLOBALS['pass'], ' passed, ', $GLOBALS['fail'], " failed\n";
