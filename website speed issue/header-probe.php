<?php
/**
 * Shows what headers the ORIGIN actually sends for an app REST route,
 * bypassing Cloudflare by going over loopback. Web root, ?key=...  DELETE AFTER.
 */
$KEY = 'aun-diag-2026';   // change me
if ( ! isset( $_GET['key'] ) || $_GET['key'] !== $KEY ) {
	http_response_code( 404 );
	exit;
}

require_once __DIR__ . '/wp-load.php';
header( 'Content-Type: text/plain; charset=utf-8' );

$url  = home_url( '/wp-json/aun-app/v1/models' );
$host = wp_parse_url( $url, PHP_URL_HOST );

echo "========= ORIGIN HEADER PROBE =========\n\n";
echo "URL: $url\n\n";

foreach ( array( 'via loopback (no Cloudflare)' => '127.0.0.1', 'via public DNS' => '' ) as $label => $ip ) {
	echo "--- $label ---\n";

	$ch = curl_init( $url );
	curl_setopt_array( $ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER         => true,
		CURLOPT_NOBODY         => true,
		CURLOPT_TIMEOUT        => 15,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => 0,
	) );

	if ( $ip ) {
		curl_setopt( $ch, CURLOPT_RESOLVE, array( "$host:443:$ip" ) );
	}

	$raw = curl_exec( $ch );
	$err = curl_error( $ch );
	curl_close( $ch );

	if ( $err ) {
		echo "  ERROR: $err\n\n";
		continue;
	}

	foreach ( preg_split( '/\r?\n/', trim( (string) $raw ) ) as $line ) {
		if ( '' !== trim( $line ) ) {
			echo '  ' . $line . "\n";
		}
	}
	echo "\n";
}

/* What PHP believes it is sending, from inside a REST request. */
echo "--- headers PHP has queued for THIS request ---\n";
foreach ( headers_list() as $h ) {
	echo "  $h\n";
}

echo "\n--- environment ---\n";
printf( "  %-26s %s\n", 'SERVER_SOFTWARE:', $_SERVER['SERVER_SOFTWARE'] ?? '?' );
printf( "  %-26s %s\n", 'aun-edge-cache loaded:', class_exists( 'AUN_Edge_Cache' ) ? 'YES' : '*** NO ***' );
printf( "  %-26s %s\n", 'api-lean active:', function_exists( 'wp_get_active_and_valid_plugins' ) ? 'n/a' : 'n/a' );

echo "\n========= END =========\n";
