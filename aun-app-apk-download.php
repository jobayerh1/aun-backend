<?php
/**
 * Plugin Name: AUN App APK Downloader
 * Description: Streams the AUN Care APK from a URL that does NOT end in .apk, so host/CDN/WAF rules that block the .apk extension can no longer 404 or reject it. Serves the real file at /get-aun-care-app/.
 *
 * INSTALL (cPanel File Manager, no wp-admin needed):
 *  1. Rename the uploaded file on the server:
 *       wp-content/uploads/app/aun-care.apk  ->  wp-content/uploads/app/aun-care.apk.pkg
 *     (any extension other than .apk works — .pkg is just a label, it is never shown to visitors)
 *  2. Create the folder wp-content/mu-plugins/ if it does not already exist.
 *  3. Upload THIS file into wp-content/mu-plugins/  (mu-plugins auto-run, nothing to activate)
 *  4. Point the footer download button at:  https://aun-projector.com.bd/get-aun-care-app/
 *  5. In WP Rocket -> Settings -> Cache -> "Never Cache URL(s)", add:  /get-aun-care-app/
 */

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

add_action( 'init', function () {
	$path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );

	if ( $path !== '/get-aun-care-app/' && $path !== '/get-aun-care-app' ) {
		return;
	}

	// Actual file on disk — deliberately NOT named *.apk so extension-based blocking can't match it.
	$file = WP_CONTENT_DIR . '/uploads/app/aun-care.apk.pkg';

	if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		exit( 'App file not found. Contact AUN support.' );
	}

	nocache_headers();
	@set_time_limit( 0 );

	header( 'Content-Description: File Transfer' );
	header( 'Content-Type: application/vnd.android.package-archive' );
	header( 'Content-Disposition: attachment; filename="aun-care.apk"' );
	header( 'Content-Transfer-Encoding: binary' );
	header( 'Content-Length: ' . filesize( $file ) );
	header( 'X-Content-Type-Options: nosniff' );

	while ( ob_get_level() ) {
		ob_end_clean();
	}

	$handle = fopen( $file, 'rb' );
	if ( $handle ) {
		while ( ! feof( $handle ) ) {
			echo fread( $handle, 8192 );
			flush();
		}
		fclose( $handle );
	}

	exit;
} );
