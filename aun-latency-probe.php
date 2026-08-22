<?php
/**
 * AUN — probe v2: is the Cloudflare hop worth skipping?
 *
 * RUN IT THE SAME WAY AS BEFORE:
 *  A. Terminal:  wp eval-file aun-latency-probe.php
 *  B. Browser:   upload next to wp-config.php, be logged in to wp-admin,
 *                visit https://aun-projector.com.bd/aun-latency-probe.php
 *                then DELETE the file.
 *
 * Read-only. Writes nothing. Prints no secrets.
 *
 * v1 answered the first question: the calls DO leave the server for Cloudflare,
 * but DNS + TCP is only ~15 ms of a 190–350 ms call, so the handshake is not
 * the problem. v1 could not measure the alternative, because the origin serves
 * a Cloudflare Origin Certificate and curl refused it.
 *
 * v2 measures it: same calls pinned to this machine, certificate checking off
 * for those runs only, three samples each so a cold cache cannot masquerade as
 * a result.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	if ( ! defined( 'ABSPATH' ) ) {
		$dir = __DIR__;
		for ( $i = 0; $i < 4; $i++ ) {
			if ( file_exists( $dir . '/wp-load.php' ) ) { require_once $dir . '/wp-load.php'; break; }
			$dir = dirname( $dir );
		}
	}
	if ( ! function_exists( 'current_user_can' ) ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		exit( "Could not find wp-load.php. Put this file beside wp-config.php.\n" );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		http_response_code( 403 );
		exit( "Administrators only. Log in to wp-admin in this browser, then reload.\n" );
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
}

/*
 * How long WordPress itself took to get here.
 *
 * REQUEST_TIME_FLOAT is stamped by PHP when the request arrived, so this is
 * everything before our code ran: WordPress core, every active plugin, the
 * theme. It is the number that dwarfs all the networking below.
 */
$AUN_BOOT_MS = isset( $_SERVER['REQUEST_TIME_FLOAT'] )
	? ( microtime( true ) - (float) $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000
	: -1;


function pl( $label, $value ) { printf( "  %-20s %s\n", $label, $value ); }

/**
 * curl one URL THREE times; report the best run.
 *
 * Three because the first call to anything warms caches, and a single sample of
 * a warm-up is not a measurement. The best time is the fair one to compare
 * across two routes.
 *
 * $insecure is used ONLY for the pinned runs. Certificate verification proves
 * you reached the right machine; when the IP you dialled IS this machine, there
 * is no one in the middle who could be impersonating anything.
 *
 * @return float|null best total seconds, or null if it failed.
 */
function probe( $label, $url, $headers = array(), $resolve = '', $insecure = false ) {
	echo "\n-- $label\n";
	if ( '' !== $resolve ) { pl( 'pinned to', $resolve ); }

	$best = null;
	$all  = array();
	for ( $n = 0; $n < 3; $n++ ) {
		$ch = curl_init( $url );
		curl_setopt_array( $ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 20,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_USERAGENT      => 'AUN-Probe/1.0',
			CURLOPT_FRESH_CONNECT  => true,
		) );
		if ( '' !== $resolve ) { curl_setopt( $ch, CURLOPT_RESOLVE, array( $resolve ) ); }
		if ( $insecure ) {
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		}
		$body = curl_exec( $ch );
		if ( false === $body ) {
			pl( 'RESULT', 'FAILED - ' . curl_error( $ch ) );
			curl_close( $ch );
			return null;
		}
		$i = curl_getinfo( $ch );
		curl_close( $ch );
		$all[] = sprintf( '%.0f', $i['total_time'] * 1000 );
		if ( null === $best || $i['total_time'] < $best['total_time'] ) { $best = $i; }
	}

	$ms = function ( $k ) use ( $best ) { return sprintf( '%7.1f ms', ( $best[ $k ] ?? 0 ) * 1000 ); };
	pl( 'http status', (string) $best['http_code'] );
	pl( 'connected to', (string) ( $best['primary_ip'] ?? '?' ) );
	pl( 'dns lookup', $ms( 'namelookup_time' ) );
	pl( 'tcp connect', $ms( 'connect_time' ) );
	pl( 'tls done', $ms( 'appconnect_time' ) );
	pl( 'first byte', $ms( 'starttransfer_time' ) );
	pl( 'BEST TOTAL', $ms( 'total_time' ) );
	pl( 'all 3 runs', implode( ' / ', $all ) . ' ms' );
	return (float) $best['total_time'];
}

echo "===== AUN latency probe v2 =====\n\n";

$erp_base = $erp_secret = $tk_base = $tk_secret = '';
if ( class_exists( 'AUN_App_ERP' ) ) {
	$s = AUN_App_ERP::settings();
	$erp_base = (string) $s['base']; $erp_secret = (string) $s['secret'];
}
if ( class_exists( 'AUN_App_Tickets' ) ) {
	$s = AUN_App_Tickets::settings();
	$tk_base = (string) $s['base']; $tk_secret = (string) $s['secret'];
}
$self = (string) ( $_SERVER['SERVER_ADDR'] ?? '' );
pl( 'this server IP', $self !== '' ? $self : '?' );

$calls = array();
if ( '' !== $erp_base ) {
	$calls['ERP'] = array( $erp_base, '/api/app-lookup/phone?phone=1700000000',
		array( 'X-Warranty-Secret: ' . $erp_secret ) );
}
if ( '' !== $tk_base ) {
	$calls['bridge'] = array( $tk_base, '/aun-app-bridge/api.php?action=ping',
		array( 'X-AUN-Bridge-Secret: ' . $tk_secret ) );
}

$normal = $local = array();

echo "\n===== A. Normal route, through Cloudflare =====\n";
foreach ( $calls as $name => $c ) {
	$normal[ $name ] = probe( $name, $c[0] . $c[1], $c[2] );
}

echo "\n===== B. Pinned to this machine, Cloudflare skipped =====\n";
echo "  Certificate checking is off for these runs only — the origin serves a\n";
echo "  Cloudflare Origin Certificate, which is deliberately not publicly\n";
echo "  trusted. That is what failed in v1, not the connection itself.\n";
foreach ( $calls as $name => $c ) {
	$host = (string) wp_parse_url( $c[0], PHP_URL_HOST );
	$port = 'https' === wp_parse_url( $c[0], PHP_URL_SCHEME ) ? 443 : 80;
	foreach ( array_unique( array_filter( array( '127.0.0.1', $self ) ) ) as $ip ) {
		$t = probe( "$name via $ip", $c[0] . $c[1], $c[2], "$host:$port:$ip", true );
		if ( null !== $t && ( ! isset( $local[ $name ] ) || $t < $local[ $name ] ) ) {
			$local[ $name ] = $t;
		}
	}
}

echo "\n===== VERDICT =====\n";
foreach ( $calls as $name => $c ) {
	if ( ! isset( $normal[ $name ] ) ) { continue; }
	if ( ! isset( $local[ $name ] ) ) {
		printf( "  %-8s local route unavailable — leave it on the normal route.\n", $name );
		continue;
	}
	$a = $normal[ $name ] * 1000;
	$b = $local[ $name ] * 1000;
	printf( "  %-8s Cloudflare %6.1f ms   local %6.1f ms   saving %6.1f ms (%.0f%%)\n",
		$name, $a, $b, $a - $b, $a > 0 ? ( ( $a - $b ) / $a * 100 ) : 0 );
}
echo "\n  Under ~30 ms saved is not worth the extra moving part.\n";
echo "  Over ~100 ms is worth wiring in.\n";

echo "\n===== C. Is the PLUGIN actually using the shortcut? =====\n";
echo "  A and B test the ROUTES. This tests the PLUGIN: it times a real call\n";
echo "  through AUN_App_ERP, which takes the local route when armed. Match B\n";
echo "  and it is using it; match A and it is not, and why is printed below.\n\n";

if ( ! class_exists( 'AUN_App_Local_Route' ) ) {
	echo "  AUN_App_Local_Route is MISSING - the deployed plugin predates 1.95.0.\n";
} else {
	pl( 'local route', AUN_App_Local_Route::status() );
	pl( 'stored server ip', (string) get_option( 'aun_app_local_ip', '(none yet)' ) );

	if ( class_exists( 'AUN_App_ERP' ) && AUN_App_ERP::configured() ) {
		// lookup_phone() goes through AUN_App_ERP::get(), which is NOT cached,
		// so this times a genuine round trip rather than a transient.
		$times = array();
		for ( $n = 0; $n < 3; $n++ ) {
			$t0 = microtime( true );
			AUN_App_ERP::lookup_phone( '8801700000000' );
			$times[] = ( microtime( true ) - $t0 ) * 1000;
		}
		sort( $times );
		$fmt = array();
		foreach ( $times as $t ) { $fmt[] = sprintf( '%.0f', $t ); }
		pl( 'real ERP call', sprintf( '%.0f ms  (3 runs: %s)', $times[0], implode( ' / ', $fmt ) ) );
		echo "\n";
		if ( $times[0] < 60 ) {
			echo "  => FAST. The plugin IS taking the local route.\n";
		} else {
			echo "  => SLOW. The plugin is still going out through Cloudflare.\n";
			echo "     The 'local route' line above says why.\n";
		}
	} else {
		echo "  ERP not configured here, so the real-call test was skipped.\n";
	}
}

echo "\n===== D. The elephant: WordPress bootstrap =====\n";
if ( $AUN_BOOT_MS >= 0 ) {
	pl( 'boot to this line', sprintf( '%.0f ms', $AUN_BOOT_MS ) );
	echo "  (WordPress core + every active plugin, before any of our code ran)\n";
} else {
	pl( 'boot to this line', 'unavailable (no REQUEST_TIME_FLOAT)' );
}

$active = (array) get_option( 'active_plugins', array() );
if ( is_multisite() ) {
	$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
}
pl( 'active plugins', (string) count( $active ) );
pl( 'php version', PHP_VERSION );
pl( 'opcache', ( function_exists( 'opcache_get_status' ) && @opcache_get_status( false ) ) ? 'ON' : 'OFF or unavailable' );
$obj = wp_using_ext_object_cache() ? 'YES' : 'NO (database-backed only)';
pl( 'object cache', $obj );
pl( 'memcached ext', extension_loaded( 'memcached' ) || extension_loaded( 'memcache' ) ? 'available' : 'not installed' );
pl( 'redis ext', extension_loaded( 'redis' ) ? 'available' : 'not installed' );

echo "\n  Active plugin list (this is what I need to tell you what to trim):\n";
sort( $active );
foreach ( $active as $plug ) {
	echo "    - " . $plug . "\n";
}

echo "
===== E. Autoloaded options (loaded on EVERY request) =====
";

/*
 * WordPress reads every option marked autoload=yes on every single request,
 * unserialises it, and holds it in memory - before any routing happens. With
 * 68 plugins this quietly grows for years: plugins add settings, get deleted,
 * and leave their autoloaded rows behind for ever.
 *
 * Anything over ~800 KB is worth attacking. It needs no extension, no host
 * support and no new software - just deleting rows nothing reads any more.
 */
global $wpdb;
$total = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM $wpdb->options WHERE autoload = 'yes'" );
$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options WHERE autoload = 'yes'" );
pl( 'autoloaded rows', (string) $count );
pl( 'autoloaded size', sprintf( '%.0f KB', $total / 1024 ) );
echo "  (under 300 KB is healthy; over 800 KB is a real cost on every request)
";

echo "
  The 15 biggest, with the plugin they most likely belong to:
";
$rows = $wpdb->get_results( "SELECT option_name, LENGTH(option_value) AS sz FROM $wpdb->options WHERE autoload = 'yes' ORDER BY sz DESC LIMIT 15" );
foreach ( (array) $rows as $r ) {
	printf( "    %8.0f KB  %s
", $r->sz / 1024, $r->option_name );
}

// APCu is the only object-cache backend likely to exist on this host.
pl( 'apcu extension', extension_loaded( 'apcu' ) ? 'AVAILABLE' : 'not installed' );
if ( extension_loaded( 'apcu' ) && function_exists( 'apcu_enabled' ) ) {
	pl( 'apcu enabled', apcu_enabled() ? 'yes' : 'no (cli only?)' );
}

echo "\n===== Done. Send this whole output to Claude. =====\n";
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { echo "\n*** Now DELETE this file. ***\n"; }
