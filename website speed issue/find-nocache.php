<?php
/**
 * Finds every plugin that defines DONOTCACHEPAGE (or sends nocache headers),
 * and reports which ones fire on a normal front-end page.
 * Put in web root, visit with ?key=...  DELETE WHEN DONE.
 */
$KEY = 'aun-diag-2026';   // change me
if ( ! isset( $_GET['key'] ) || $_GET['key'] !== $KEY ) {
	http_response_code( 404 );
	exit;
}

require_once __DIR__ . '/wp-load.php';
header( 'Content-Type: text/plain; charset=utf-8' );

echo "========= WHO IS BLOCKING THE CACHE? =========\n\n";

/* ---------- 1. Static scan of active plugins ---------- */
echo "--- Source scan: DONOTCACHEPAGE in active plugins ---\n\n";

$active  = (array) get_option( 'active_plugins' );
$targets = array();
foreach ( $active as $p ) {
	$targets[ dirname( $p ) ] = WP_PLUGIN_DIR . '/' . dirname( $p );
}
$targets['(mu-plugins)'] = WPMU_PLUGIN_DIR;
$targets['(theme)']      = get_stylesheet_directory();

$found = 0;
foreach ( $targets as $name => $dir ) {
	if ( ! is_dir( $dir ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ( $it as $f ) {
		if ( ! $f->isFile() || 'php' !== strtolower( $f->getExtension() ) ) {
			continue;
		}
		if ( $f->getSize() > 2000000 ) {
			continue;
		}
		$lines = @file( $f->getPathname() );
		if ( ! $lines ) {
			continue;
		}
		foreach ( $lines as $i => $line ) {
			if ( false === stripos( $line, 'DONOTCACHEPAGE' ) ) {
				continue;
			}
			if ( false === stripos( $line, 'define' ) ) {
				continue;   // skip mere reads of the constant
			}
			$found++;
			$rel = str_replace( WP_CONTENT_DIR, '', $f->getPathname() );
			echo "  PLUGIN : $name\n";
			echo "  FILE   : $rel : " . ( $i + 1 ) . "\n";
			for ( $j = max( 0, $i - 4 ); $j <= min( count( $lines ) - 1, $i + 1 ); $j++ ) {
				printf( "    %s%4d| %s", ( $j === $i ? '>' : ' ' ), $j + 1, rtrim( $lines[ $j ] ) . "\n" );
			}
			echo "\n";
		}
	}
}
echo $found ? "  ($found definition site(s) found)\n" : "  none found in plugin source\n";

/* ---------- 2. Runtime check on a real front-end request ---------- */
echo "\n--- Runtime: simulate a normal homepage request ---\n\n";

$url  = home_url( '/' );
$resp = wp_remote_get(
	add_query_arg( 'nocache_probe', time(), $url ),
	array(
		'timeout'   => 30,
		'sslverify' => false,
		'headers'   => array( 'User-Agent' => 'Mozilla/5.0 (cache-probe)' ),
	)
);

if ( is_wp_error( $resp ) ) {
	echo "  request failed: " . $resp->get_error_message() . "\n";
} else {
	foreach ( wp_remote_retrieve_headers( $resp )->getAll() as $k => $v ) {
		if ( preg_match( '/cache|cookie|vary|pragma|expires/i', $k ) ) {
			printf( "  %-24s %s\n", $k . ':', is_array( $v ) ? implode( ' || ', $v ) : $v );
		}
	}
}

echo "\n--- This request's own state ---\n";
printf( "  %-24s %s\n", 'DONOTCACHEPAGE:', defined( 'DONOTCACHEPAGE' ) ? var_export( DONOTCACHEPAGE, true ) : 'not defined' );
printf( "  %-24s %s\n", 'is_admin():', var_export( is_admin(), true ) );
printf( "  %-24s %s\n", 'active plugins:', count( $active ) );

echo "\n========= END =========\n";
