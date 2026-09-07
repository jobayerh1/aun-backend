<?php
/**
 * Plugin Name: AUN App APK Downloader
 * Version: 1.3.0
 * Description: Streams the AUN Care APK from a URL that does NOT end in .apk, so host/CDN/WAF rules that block the .apk extension can no longer 404 or reject it. Serves the real file at /get-aun-care-app/.
 *
 * INSTALL (cPanel File Manager, no wp-admin needed):
 *  1. Rename the uploaded file on the server:
 *       wp-content/uploads/app/aun-care.apk  ->  wp-content/uploads/app/aun-care.apk.pkg
 *     (any *.apk / *.pkg file in that folder is picked up; newest wins)
 *     (any extension other than .apk works — .pkg is just a label, it is never shown to visitors)
 *  2. Create the folder wp-content/mu-plugins/ if it does not already exist.
 *  3. Upload THIS file into wp-content/mu-plugins/  (mu-plugins auto-run, nothing to activate)
 *  4. Point the footer download button at:  https://aun-projector.com.bd/get-aun-care-app/
 *  5. Nothing to do in WP Rocket: this file sets DONOTCACHEPAGE for the download URL only.
 */

/**
 * Is the current request the APK download?
 *
 * The site runs TranslatePress, which serves translated pages under a language
 * prefix — Bengali is /bn/get-aun-care-app/. An exact match on
 * '/get-aun-care-app/' therefore missed every non-English visitor and let the
 * request fall through to a 404.
 *
 * So: match the slug as the last path segment, optionally preceded by ONE
 * language-code segment. That covers /get-aun-care-app/, /bn/get-aun-care-app/,
 * and any language added later, without matching unrelated deeper URLs.
 */
function aun_apk_is_download_request() {

	$path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );

	$segments = array_values( array_filter( explode( '/', (string) $path ), 'strlen' ) );

	if ( ! $segments || end( $segments ) !== 'get-aun-care-app' ) {
		return false;
	}

	// Bare /get-aun-care-app
	if ( 1 === count( $segments ) ) {
		return true;
	}

	// /<lang>/get-aun-care-app  — e.g. bn, en, bn-bd, pt_BR
	return 2 === count( $segments )
		&& (bool) preg_match( '~^[a-z]{2,3}([_-][a-z]{2,4})?$~i', $segments[0] );
}

/*
 * Only the APK download URL must bypass the page cache. This define used to sit
 * at the top level, which marked EVERY page on the site as uncacheable, because
 * mu-plugins load on every request. Scope it to the download path.
 */
if ( aun_apk_is_download_request() && ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

add_action( 'init', function () {

	if ( ! aun_apk_is_download_request() ) {
		return;
	}

	/*
	 * Actual file on disk — deliberately NOT named *.apk so extension-based
	 * blocking can't match it. Try the canonical name first, then fall back to
	 * anything APK-shaped in the folder, so a re-upload under a slightly
	 * different name does not take the download offline.
	 */
	$dir  = WP_CONTENT_DIR . '/uploads/app';
	$file = '';

	foreach ( array( 'aun-care.apk.pkg', 'aun-care.apk' ) as $candidate ) {
		if ( is_readable( "$dir/$candidate" ) ) {
			$file = "$dir/$candidate";
			break;
		}
	}

	if ( ! $file ) {
		foreach ( array( "$dir/*.apk.pkg", "$dir/*.apk", "$dir/*.pkg" ) as $pattern ) {
			$hits = glob( $pattern );
			if ( $hits ) {
				// Newest build wins if several are present.
				usort( $hits, function ( $a, $b ) {
					return filemtime( $b ) <=> filemtime( $a );
				} );
				$file = $hits[0];
				break;
			}
		}
	}

	if ( ! $file || ! is_readable( $file ) ) {
		error_log( sprintf( 'AUN APK download: no readable app file in %s', $dir ) );
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );

		if ( current_user_can( 'manage_options' ) ) {
			exit( "App file not found.

Admin detail: looked in $dir for aun-care.apk.pkg, aun-care.apk, then *.apk.pkg / *.apk / *.pkg." );
		}

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
