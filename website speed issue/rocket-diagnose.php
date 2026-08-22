<?php
/**
 * WP Rocket cache diagnostic. Standalone — put in web root, visit with ?key=...
 * DELETE THIS FILE when finished.
 */
$KEY = 'aun-diag-2026';   // change me
if ( ! isset( $_GET['key'] ) || $_GET['key'] !== $KEY ) {
	http_response_code( 404 );
	exit;
}

require_once __DIR__ . '/wp-load.php';

header( 'Content-Type: text/plain; charset=utf-8' );

function row( $label, $value ) {
	printf( "%-38s %s\n", $label, $value );
}
function yn( $bool ) {
	return $bool ? 'YES' : '*** NO ***';
}

echo "================ WP ROCKET DIAGNOSTIC ================\n\n";

echo "--- 1. WP_CACHE constant ---\n";
row( 'WP_CACHE defined', yn( defined( 'WP_CACHE' ) ) );
row( 'WP_CACHE value', defined( 'WP_CACHE' ) ? var_export( WP_CACHE, true ) : 'n/a' );
$cfg = file_exists( ABSPATH . 'wp-config.php' ) ? ABSPATH . 'wp-config.php' : dirname( ABSPATH ) . '/wp-config.php';
row( 'wp-config.php path', $cfg );
row( 'wp-config.php writable', yn( is_writable( $cfg ) ) );
if ( is_readable( $cfg ) ) {
	$has = preg_grep( "/WP_CACHE/", file( $cfg ) );
	row( 'WP_CACHE line in file', $has ? trim( reset( $has ) ) : '*** NOT PRESENT ***' );
}

echo "\n--- 2. advanced-cache.php drop-in ---\n";
$ac = WP_CONTENT_DIR . '/advanced-cache.php';
row( 'exists', yn( file_exists( $ac ) ) );
if ( file_exists( $ac ) ) {
	row( 'size', filesize( $ac ) . ' bytes' );
	row( 'mentions Rocket', yn( stripos( file_get_contents( $ac ), 'rocket' ) !== false ) );
	row( 'permissions', substr( sprintf( '%o', fileperms( $ac ) ), -4 ) );
}
row( 'object-cache.php exists', yn( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) );

echo "\n--- 3. Directories & permissions ---\n";
foreach ( array(
	'wp-content'              => WP_CONTENT_DIR,
	'wp-content/cache'        => WP_CONTENT_DIR . '/cache',
	'cache/wp-rocket'         => WP_CONTENT_DIR . '/cache/wp-rocket',
) as $label => $path ) {
	row(
		$label,
		( file_exists( $path ) ? 'exists' : 'MISSING' ) . '  perms=' .
		( file_exists( $path ) ? substr( sprintf( '%o', fileperms( $path ) ), -4 ) : '----' ) .
		'  writable=' . ( is_writable( $path ) ? 'yes' : '*** NO ***' )
	);
}
$probe = WP_CONTENT_DIR . '/cache/wp-rocket/_diag_probe.txt';
$wrote = @file_put_contents( $probe, 'probe' );
row( 'write test in cache dir', $wrote !== false ? 'OK' : '*** FAILED ***' );
@unlink( $probe );

echo "\n--- 4. .htaccess ---\n";
$ht = ABSPATH . '.htaccess';
row( 'exists', yn( file_exists( $ht ) ) );
row( 'writable', yn( is_writable( $ht ) ) );
if ( is_readable( $ht ) ) {
	$h = file_get_contents( $ht );
	row( 'has "BEGIN WP Rocket"', yn( strpos( $h, 'BEGIN WP Rocket' ) !== false ) );
	row( 'has LiteSpeed rules', yn( stripos( $h, 'litespeed' ) !== false ) );
	row( 'sets Vary header', yn( stripos( $h, 'Vary' ) !== false ) );
	row( 'size', strlen( $h ) . ' bytes' );
}

echo "\n--- 5. Server ---\n";
row( 'SERVER_SOFTWARE', $_SERVER['SERVER_SOFTWARE'] ?? '?' );
row( 'PHP version', PHP_VERSION );
row( 'PHP SAPI', php_sapi_name() );
row( 'LiteSpeed detected', yn( stripos( $_SERVER['SERVER_SOFTWARE'] ?? '', 'litespeed' ) !== false ) );
row( 'apc.shm_size', ini_get( 'apc.shm_size' ) ?: 'not set' );
row( 'apcu enabled', yn( function_exists( 'apcu_enabled' ) && apcu_enabled() ) );

echo "\n--- 6. WP Rocket state ---\n";
row( 'WP_ROCKET_VERSION', defined( 'WP_ROCKET_VERSION' ) ? WP_ROCKET_VERSION : '*** not loaded ***' );
if ( function_exists( 'get_rocket_option' ) ) {
	foreach ( array( 'cache_mobile', 'do_caching_mobile_files', 'cache_logged_user', 'cache_ssl' ) as $o ) {
		row( "  option $o", var_export( get_rocket_option( $o ), true ) );
	}
	$rej = get_rocket_option( 'cache_reject_uri' );
	row( '  cache_reject_uri', $rej ? implode( ' | ', (array) $rej ) : '(empty)' );
	$cook = get_rocket_option( 'cache_reject_cookies' );
	row( '  cache_reject_cookies', $cook ? implode( ' | ', (array) $cook ) : '(empty)' );
} else {
	row( 'get_rocket_option()', '*** unavailable ***' );
}
row( 'DONOTCACHEPAGE', defined( 'DONOTCACHEPAGE' ) ? var_export( DONOTCACHEPAGE, true ) : 'not defined' );

echo "\n--- 7. Cached files on disk ---\n";
$dir = WP_CONTENT_DIR . '/cache/wp-rocket';
$n   = 0;
if ( is_dir( $dir ) ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() ) {
			$n++;
		}
	}
}
row( 'files under cache/wp-rocket', $n );

echo "\n--- 8. Active plugins (caching / optimisation) ---\n";
foreach ( (array) get_option( 'active_plugins' ) as $p ) {
	if ( preg_match( '/cache|rocket|speed|optimi|cloudflare|litespeed|autoptimi|perfmatters/i', $p ) ) {
		echo "  $p\n";
	}
}
row( 'total active plugins', count( (array) get_option( 'active_plugins' ) ) );

echo "\n================ END ================\n";
