<?php
// Zip Breo plugins with forward-slash paths (Linux hosts mis-extract backslashes).
// Usage: php -c <wp-local>/php.ini build-zips.php <slug> [<slug> ...]   (folders next to this file)
$base = __DIR__ . '/';
foreach ( array_slice( $argv, 1 ) as $slug ) {
	$src = $base . $slug;
	$out = $base . $slug . '.zip';
	if ( ! is_dir( $src ) ) {
		echo "skip $slug (no folder)\n";
		continue;
	}
	@unlink( $out );
	$z = new ZipArchive();
	$z->open( $out, ZipArchive::CREATE );
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );
	$n  = 0;
	foreach ( $it as $f ) {
		$rel = $slug . '/' . ltrim( str_replace( '\\', '/', substr( $f->getPathname(), strlen( $src ) ) ), '/' );
		$z->addFile( $f->getPathname(), $rel );
		$n++;
	}
	$z->close();

	// Verify what a host will see: no backslash paths, main plugin file at the top level.
	$z->open( $out );
	$bad  = 0;
	$main = false;
	for ( $i = 0; $i < $z->numFiles; $i++ ) {
		$name = $z->getNameIndex( $i );
		$bad += false !== strpos( $name, '\\' ) ? 1 : 0;
		$main = $main || $name === $slug . '/' . $slug . '.php';
	}
	$z->close();
	printf( "%-22s %3d files %9d bytes  backslash paths: %d  main file: %s\n", $slug, $n, filesize( $out ), $bad, $main ? 'yes' : 'MISSING' );
}
