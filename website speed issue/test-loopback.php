<?php
/**
 * Tests whether WordPress can reach the ERP and osTicket over loopback
 * instead of going out through Cloudflare and back.
 *
 * Read-only: makes requests, changes nothing. Web root, ?key=...  DELETE AFTER.
 */
$KEY = 'aun-diag-2026';   // change me
if ( ! isset( $_GET['key'] ) || $_GET['key'] !== $KEY ) {
	http_response_code( 404 );
	exit;
}

require_once __DIR__ . '/wp-load.php';
header( 'Content-Type: text/plain; charset=utf-8' );

$hosts = array( 'portal.smartliving.com.bd', 'support.smartliving.com.bd' );

/* Candidate origins to try instead of the public DNS answer. */
$candidates = array(
	'loopback v4' => '127.0.0.1',
	'loopback v6' => '[::1]',
	'server IP'   => $_SERVER['SERVER_ADDR'] ?? '',
);

echo "======== LOOPBACK REACHABILITY TEST ========\n\n";
echo 'SERVER_ADDR : ' . ( $_SERVER['SERVER_ADDR'] ?? 'unknown' ) . "\n";
echo 'PHP curl    : ' . ( function_exists( 'curl_version' ) ? curl_version()['version'] : 'MISSING' ) . "\n\n";

function try_fetch( $url, $host = '', $ip = '' ) {
	$ch = curl_init( $url );
	curl_setopt_array( $ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_NOBODY         => true,
		CURLOPT_TIMEOUT        => 10,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => 0,
	) );

	if ( $host && $ip ) {
		$port = 443;
		curl_setopt( $ch, CURLOPT_RESOLVE, array( "$host:$port:$ip" ) );
	}

	$t0 = microtime( true );
	curl_exec( $ch );
	$elapsed = microtime( true ) - $t0;

	$out = array(
		'code'    => (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ),
		'connect' => curl_getinfo( $ch, CURLINFO_CONNECT_TIME ),
		'tls'     => curl_getinfo( $ch, CURLINFO_APPCONNECT_TIME ),
		'total'   => $elapsed,
		'err'     => curl_error( $ch ),
		'ip'      => curl_getinfo( $ch, CURLINFO_PRIMARY_IP ),
	);
	curl_close( $ch );

	return $out;
}

foreach ( $hosts as $host ) {
	$url = "https://$host/";
	echo "--- $host ---\n";

	$base = try_fetch( $url );
	printf(
		"  %-12s http=%-3d ip=%-15s connect=%.3fs tls=%.3fs total=%.3fs %s\n",
		'via DNS',
		$base['code'],
		$base['ip'],
		$base['connect'],
		$base['tls'],
		$base['total'],
		$base['err'] ? 'ERR: ' . $base['err'] : ''
	);

	foreach ( $candidates as $label => $ip ) {
		if ( '' === $ip ) {
			continue;
		}

		$r = try_fetch( $url, $host, $ip );
		$verdict = ( $r['code'] >= 200 && $r['code'] < 500 && ! $r['err'] ) ? 'WORKS' : 'fails';

		printf(
			"  %-12s http=%-3d ip=%-15s connect=%.3fs tls=%.3fs total=%.3fs  %-5s %s\n",
			$label,
			$r['code'],
			$r['ip'],
			$r['connect'],
			$r['tls'],
			$r['total'],
			$verdict,
			$r['err'] ? 'ERR: ' . $r['err'] : ''
		);

		if ( 'WORKS' === $verdict && $base['total'] > 0 ) {
			printf( "  %-12s saving vs DNS: %.3fs (%d%%)\n", '', $base['total'] - $r['total'], round( 100 * ( 1 - $r['total'] / $base['total'] ) ) );
		}
	}
	echo "\n";
}

echo "======== END ========\n";
