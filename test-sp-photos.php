<?php
/**
 * AUN Spare Parts v0.42.0 — customer photos on the tracking page, the photo
 * compression engine, re-upload validation, and the rate-limit IP fix.
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * Hunts for holes rather than confirming the happy path: hostile attachment
 * rows, a spoofed Cloudflare header rotated on every request, a photo that gets
 * BIGGER when re-encoded, a sideways phone photo, and the empty-string argument
 * WordPress hands to cron callbacks.
 */

if ( ! class_exists( 'AUN_SP_Image' ) || ! class_exists( 'AUN_SP_Tracking' ) ) {
	echo "AUN Spare Parts not active on this bench.\n";
	exit( 1 );
}

// eval-file runs inside a FUNCTION: top-level vars are not globals, so the
// counters live in $GLOBALS explicitly.
$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;

function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

class AunSpPhotoDie extends Exception {}

/* ── helpers ──────────────────────────────────────────────────────── */

/** High-entropy image (random 8x8 blocks) so JPEG/PNG sizes are realistic. */
function sp_noise( $w, $h ) {
	mt_srand( $w * 7 + $h );
	$im = imagecreatetruecolor( $w, $h );
	for ( $y = 0; $y < $h; $y += 8 ) {
		for ( $x = 0; $x < $w; $x += 8 ) {
			$c = imagecolorallocate( $im, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) );
			imagefilledrectangle( $im, $x, $y, $x + 7, $y + 7, $c );
		}
	}
	return $im;
}
function sp_photo( $w, $h ) {
	mt_srand( $w * 13 + $h );
	$sw = max( 8, intdiv( $w, 4 ) );
	$sh = max( 8, intdiv( $h, 4 ) );
	$small = imagecreatetruecolor( $sw, $sh );
	$cl = function ( $v ) { return max( 0, min( 255, (int) $v ) ); };
	for ( $y = 0; $y < $sh; $y++ ) {
		for ( $x = 0; $x < $sw; $x++ ) {
			$r = $cl( 90 + 120 * $x / $sw + mt_rand( -28, 28 ) );
			$g = $cl( 70 + 100 * $y / $sh + mt_rand( -28, 28 ) );
			$b = $cl( 140 - 60 * $x / $sw + mt_rand( -28, 28 ) );
			imagesetpixel( $small, $x, $y, ( $r << 16 ) | ( $g << 8 ) | $b );
		}
	}
	$im = imagecreatetruecolor( $w, $h );
	imagecopyresampled( $im, $small, 0, 0, 0, 0, $w, $h, $sw, $sh );
	imagedestroy( $small );
	for ( $i = 0, $n = (int) ( $w * $h / 6 ); $i < $n; $i++ ) { // sensor grain
		$x = mt_rand( 0, $w - 1 );
		$y = mt_rand( 0, $h - 1 );
		$c = imagecolorat( $im, $x, $y );
		$d = mt_rand( -20, 20 );
		imagesetpixel( $im, $x, $y, ( $cl( ( ( $c >> 16 ) & 255 ) + $d ) << 16 ) | ( $cl( ( ( $c >> 8 ) & 255 ) + $d ) << 8 ) | $cl( ( $c & 255 ) + $d ) );
	}
	return $im;
}
function sp_photo_jpeg( $path, $w, $h, $q = 92 ) { $im = sp_photo( $w, $h ); imagejpeg( $im, $path, $q ); imagedestroy( $im ); return $path; }
function sp_photo_png( $path, $w, $h ) { $im = sp_photo( $w, $h ); imagepng( $im, $path, 6 ); imagedestroy( $im ); return $path; }
function sp_jpeg( $path, $w, $h, $q = 92 ) { $im = sp_noise( $w, $h ); imagejpeg( $im, $path, $q ); imagedestroy( $im ); return $path; }
function sp_png( $path, $w, $h ) { $im = sp_noise( $w, $h ); imagepng( $im, $path, 6 ); imagedestroy( $im ); return $path; }

/** Inject a minimal EXIF APP1 segment carrying an Orientation tag, like a phone camera does. */
function sp_add_orientation( $path, $orientation ) {
	$d    = file_get_contents( $path );
	$tiff = "MM\x00\x2A\x00\x00\x00\x08"                  // big-endian TIFF, IFD at 8
		. "\x00\x01"                                        // 1 entry
		. "\x01\x12\x00\x03\x00\x00\x00\x01" . pack( 'n', $orientation ) . "\x00\x00" // Orientation SHORT
		. "\x00\x00\x00\x00";                               // no next IFD
	$payload = "Exif\x00\x00" . $tiff;
	file_put_contents( $path, substr( $d, 0, 2 ) . "\xFF\xE1" . pack( 'n', strlen( $payload ) + 2 ) . $payload . substr( $d, 2 ) );
}

function sp_dims( $path ) { $i = @getimagesize( $path ); return $i ? array( (int) $i[0], (int) $i[1], $i['mime'] ) : array( 0, 0, '' ); }

/** Run an admin-ajax handler and return its decoded JSON. */
function sp_ajax( $callable ) {
	ob_start();
	try { call_user_func( $callable ); } catch ( AunSpPhotoDie $e ) {}
	return json_decode( (string) ob_get_clean(), true );
}

add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () {
	return function ( $msg = '' ) { throw new AunSpPhotoDie( is_string( $msg ) ? $msg : '' ); };
} );

global $wpdb;
$t_req  = AUN_SP_Install::table( 'requests' );
$t_item = AUN_SP_Install::table( 'request_items' );
$t_att  = AUN_SP_Install::table( 'attachments' );
$up     = wp_upload_dir();
$dir    = $up['basedir'] . '/aun-spare-parts/phototest';
$url    = $up['baseurl'] . '/aun-spare-parts/phototest';
wp_mkdir_p( $dir );

$made_requests = array();
function sp_mk_request( $status, $phone = '01799000111' ) {
	global $wpdb;
	$ref = 'SP-2026-PT' . strtoupper( wp_generate_password( 4, false, false ) );
	$wpdb->insert( AUN_SP_Install::table( 'requests' ), array(
		'ref'            => $ref,
		'customer_name'  => 'Photo Test',
		'phone_current'  => $phone,
		'model'          => 'AUN A45 Pro',
		'overall_status' => $status,
		'created_at'     => current_time( 'mysql' ),
		'updated_at'     => current_time( 'mysql' ),
	) );
	$id = (int) $wpdb->insert_id;
	$GLOBALS['made_requests'][] = $id;
	return array( $id, $ref );
}
function sp_mk_item( $rid, $type, $label ) {
	global $wpdb;
	$wpdb->insert( AUN_SP_Install::table( 'request_items' ), array(
		'request_id' => $rid, 'part_type' => $type, 'part_label' => $label,
		'qty' => 1, 'line_status' => 'pending',
		'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ),
	) );
	return (int) $wpdb->insert_id;
}
function sp_mk_att( $rid, $item, $kind, $file_url, $created = null, $before = 0 ) {
	global $wpdb;
	$wpdb->insert( AUN_SP_Install::table( 'attachments' ), array(
		'request_id' => $rid, 'item_id' => $item, 'kind' => $kind, 'file_url' => $file_url,
		'bytes_before' => $before, 'bytes_after' => 0,
		'created_at' => $created ? $created : current_time( 'mysql' ),
	) );
	return (int) $wpdb->insert_id;
}
function sp_att( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AUN_SP_Install::table( 'attachments' ) . ' WHERE id = %d', $id ) );
}
$hour_ago = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - HOUR_IN_SECONDS );

/* ════════════════════════════════════════════════════════════════════ */
echo "\n=== 1. Client IP — the Cloudflare header is believed only from Cloudflare ===\n";

$ip_cases = array(
	array( '45.117.62.10',        '203.0.113.9',   '45.117.62.10',     'direct-to-origin client: spoofed CF header IGNORED' ),
	array( '172.68.10.20',        '103.143.139.172', '103.143.139.172', 'via a Cloudflare edge: the real visitor IP is used' ),
	array( '127.0.0.1',           '103.143.139.172', '103.143.139.172', 'local proxy on the host: header trusted' ),
	array( '10.0.0.5',            '103.143.139.172', '103.143.139.172', 'private-network proxy: header trusted' ),
	array( '::ffff:172.68.10.20', '103.143.139.172', '103.143.139.172', 'IPv4 written as IPv6 still recognised as Cloudflare' ),
	array( '2606:4700:10::1',     '2400:1a00:b010::1', '2400:1a00:b010::1', 'IPv6 Cloudflare edge' ),
	array( '172.68.10.20',        'not-an-ip',     '172.68.10.20',     'garbage CF header ignored even from Cloudflare' ),
	array( '45.117.62.10',        '',              '45.117.62.10',     'no header at all' ),
);
foreach ( $ip_cases as $c ) {
	$_SERVER['REMOTE_ADDR']           = $c[0];
	$_SERVER['HTTP_CF_CONNECTING_IP'] = $c[1];
	$got = aun_sp_client_ip();
	ok( $got === $c[2], $c[3] . " (got $got)" );
}

ok( aun_sp_ip_in_cidr( '173.245.63.255', '173.245.48.0/20' ), 'CIDR: last address of a /20 is inside' );
ok( ! aun_sp_ip_in_cidr( '173.245.64.0', '173.245.48.0/20' ), 'CIDR: first address after a /20 is outside' );
ok( aun_sp_ip_in_cidr( '2a06:98c7:ffff::1', '2a06:98c0::/29' ), 'CIDR: IPv6 /29 upper edge inside' );
ok( ! aun_sp_ip_in_cidr( '2a06:98c8::1', '2a06:98c0::/29' ), 'CIDR: IPv6 just past a /29 is outside' );
ok( ! aun_sp_ip_in_cidr( '172.68.10.20', '2606:4700::/32' ), 'CIDR: v4 never matches a v6 range' );

// The actual attack: rotate the spoofed header on every request from one IP.
$rate = new ReflectionMethod( 'AUN_SP_Tracking', 'rate_ok' );
$rate->setAccessible( true );
$trk  = new AUN_SP_Tracking();
$act  = 'iptest' . wp_rand( 1000, 9999 );
$_SERVER['REMOTE_ADDR'] = '45.117.62.10';
$allowed = 0;
for ( $i = 1; $i <= 8; $i++ ) {
	$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.18.0.' . $i; // different "visitor" every time
	if ( $rate->invoke( $trk, $act, 5 ) ) { $allowed++; }
}
ok( 5 === $allowed, "rotating the CF header no longer escapes the limit ($allowed of 8 allowed, limit 5)" );

$frate = new ReflectionMethod( 'AUN_SP_Form', 'rate_ok' );
$frate->setAccessible( true );
$frm   = new AUN_SP_Form();
$allowed = 0;
for ( $i = 1; $i <= 8; $i++ ) {
	$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.18.1.' . $i;
	if ( $frate->invoke( $frm, $act . 'f', 5 ) ) { $allowed++; }
}
ok( 5 === $allowed, "the request form's limiter is fixed too ($allowed of 8 allowed)" );

// Real visitors behind Cloudflare must still each get their own bucket.
$_SERVER['REMOTE_ADDR'] = '172.68.10.20';
$distinct = 0;
for ( $i = 1; $i <= 8; $i++ ) {
	$_SERVER['HTTP_CF_CONNECTING_IP'] = '103.143.139.' . $i;
	if ( $rate->invoke( $trk, $act . 'cf', 1 ) ) { $distinct++; }
}
ok( 8 === $distinct, "8 real visitors sharing one Cloudflare edge are NOT lumped together ($distinct of 8)" );
unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
$_SERVER['REMOTE_ADDR'] = '103.143.139.172';

/* ════════════════════════════════════════════════════════════════════ */
echo "\n=== 2. Compression engine ===\n";

update_option( 'aun_sp_img_preset', 'balanced' );
update_option( 'aun_sp_img_format', 'jpeg' );
$e = $dir . '/engine';
wp_mkdir_p( $e );
array_map( 'unlink', glob( $e . '/*' ) ?: array() );

// 2a. resize + shrink, in place
$p  = sp_jpeg( "$e/big.jpg", 2400, 1800 );
$b0 = filesize( $p );
$r  = AUN_SP_Image::compress_image_file( $p );
clearstatcache();
list( $w, $h ) = sp_dims( $r );
ok( $r === $p, 'same-format photo is replaced in place (URL stays valid)' );
ok( max( $w, $h ) === 1600, "long edge capped at 1600 by Balanced (got {$w}x{$h})" );
ok( filesize( $r ) < $b0, 'file got smaller (' . size_format( $b0 ) . ' -> ' . size_format( filesize( $r ) ) . ')' );
ok( ! glob( "$e/*-aunsp-tmp*" ), 'no temp file left behind' );

// 2b. sideways phone photo
$p = sp_jpeg( "$e/phone.jpg", 2400, 1200 ); // pixels stored landscape…
sp_add_orientation( $p, 6 );                // …camera says "rotate 90° to view"
$ex = function_exists( 'exif_read_data' ) ? @exif_read_data( $p ) : array();
ok( 6 === (int) ( $ex['Orientation'] ?? 0 ), 'fixture: EXIF orientation 6 written' );
$r = AUN_SP_Image::compress_image_file( $p );
clearstatcache();
list( $w, $h ) = sp_dims( $r );
ok( $h > $w, "portrait phone photo comes out UPRIGHT, not sideways (got {$w}x{$h})" );

// 2c. a re-encode that would make it BIGGER keeps the original
$p  = sp_photo_jpeg( "$e/tight.jpg", 1600, 1200, 30 );
$b0 = filesize( $p );
$m0 = md5_file( $p );
ok( $b0 > 80 * 1024, 'fixture: tight JPEG is over the 80 KB skip threshold (' . size_format( $b0 ) . ')' );
$r = AUN_SP_Image::compress_image_file( $p );
clearstatcache();
ok( $r === $p && md5_file( $p ) === $m0, 'already-lean photo left byte-for-byte untouched (bigger is not better)' );

// 2c-bis. grid-aligned flat blocks: a re-encode really IS bigger here.
$p  = sp_png( "$e/flat.png", 1000, 800 );
$m0 = md5_file( $p );
$r  = AUN_SP_Image::compress_image_file( $p );
clearstatcache();
ok( $r === $p && is_file( $p ) && md5_file( $p ) === $m0, 'flat-colour PNG that JPEG would enlarge is kept as PNG' );

// 2d. PNG -> JPEG
$p = sp_photo_png( "$e/shot.png", 1000, 800 );
$r = AUN_SP_Image::compress_image_file( $p );
ok( '.jpg' === substr( $r, -4 ) && is_file( $r ), 'PNG converted to JPEG' );
ok( ! is_file( $p ), 'original PNG removed' );

// 2e. WebP output
update_option( 'aun_sp_img_format', 'webp' );
ok( 'webp' === AUN_SP_Image::format(), 'WebP selected and supported here' );
$p = sp_png( "$e/w1.png", 1000, 800 );
$r = AUN_SP_Image::compress_image_file( $p );
list( , , $mime ) = sp_dims( $r );
ok( '.webp' === substr( $r, -5 ) && 'image/webp' === $mime, 'PNG -> real WebP file' );
ok( ! is_file( $p ), 'original removed after WebP conversion' );
$p  = sp_photo_jpeg( "$e/w2.jpg", 2000, 1500 );
$jb = filesize( $p );
$r  = AUN_SP_Image::compress_image_file( $p );
clearstatcache();
ok( '.webp' === substr( $r, -5 ) && filesize( $r ) < $jb, 'JPEG -> smaller WebP' );

// 2f. WebP chosen but the server can't write it -> JPEG, never a failure
$no_editors = function () { return array(); };
add_filter( 'wp_image_editors', $no_editors );
ok( ! AUN_SP_Image::webp_supported() && 'jpeg' === AUN_SP_Image::format(), 'no WebP support -> falls back to JPEG' );
remove_filter( 'wp_image_editors', $no_editors );

// The DEFAULT is WebP: a site that has never opened Settings must still get the
// smaller format, and an explicit JPEG choice must still win over it.
delete_option( 'aun_sp_img_format' );
ok( 'webp' === AUN_SP_Image::format(), 'a site that never touched Settings defaults to WebP' );
update_option( 'aun_sp_img_format', 'jpeg' );
ok( 'jpeg' === AUN_SP_Image::format(), 'an explicit JPEG choice still overrides the default' );

// 2g. presets
update_option( 'aun_sp_img_preset', 'smallest' );
$p = sp_jpeg( "$e/small.jpg", 2400, 1800 );
$r = AUN_SP_Image::compress_image_file( $p );
clearstatcache();
list( $w, $h ) = sp_dims( $r );
ok( 1200 === max( $w, $h ), "Smallest preset caps the long edge at 1200 (got {$w}x{$h})" );
update_option( 'aun_sp_img_preset', 'nonsense' );
ok( 'balanced' === AUN_SP_Image::preset(), 'unknown preset falls back to Balanced' );
update_option( 'aun_sp_img_preset', 'balanced' );

/* ════════════════════════════════════════════════════════════════════ */
echo "\n=== 3. Photos are compressed ONCE, and app photos are caught up ===\n";

// Drain whatever the bench already has pending, so the checks below are isolated.
for ( $i = 0; $i < 300 && AUN_SP_Image::pending_count() > 0; $i++ ) {
	$rows = $wpdb->get_results( "SELECT id FROM $t_att WHERE bytes_after = 0" );
	foreach ( $rows as $row ) { // make everything old enough for the sweep
		$wpdb->update( $t_att, array( 'created_at' => $hour_ago ), array( 'id' => (int) $row->id ) );
	}
	AUN_SP_Image::sweep( 50, 30 );
}
ok( 0 === AUN_SP_Image::pending_count(), 'bench backlog drained (and missing-file rows did not loop forever)' );

list( $rid ) = sp_mk_request( 'in_progress' );
$f = sp_jpeg( "$dir/once.jpg", 2400, 1800 );
$a = sp_mk_att( $rid, 0, 'lcd', "$url/once.jpg" );
AUN_SP_Image::compress_request( $rid );
$row1 = sp_att( $a );
clearstatcache();
$m1 = md5_file( $f );
ok( (int) $row1->bytes_after > 0, 'first pass processes the photo' );
AUN_SP_Image::compress_request( $rid ); // e.g. the customer re-uploads another photo later
clearstatcache();
ok( md5_file( $f ) === $m1, 'second pass leaves the already-compressed photo alone (no quality loss per re-upload)' );

// App-style row: no bytes_before, never queued.
$app = sp_mk_att( $rid, 0, 'proof', "$url/" . basename( sp_jpeg( "$dir/app.jpg", 3000, 2000 ) ), $hour_ago );
$young = sp_mk_att( $rid, 0, 'proof', "$url/" . basename( sp_jpeg( "$dir/young.jpg", 2400, 1800 ) ) ); // just uploaded
$gone  = sp_mk_att( $rid, 0, 'proof', "$url/was-here.jpg", $hour_ago, 5000 );
$res = AUN_SP_Image::sweep( 10, 20 );
$ra  = sp_att( $app );
ok( (int) $ra->bytes_after > 0 && (int) $ra->bytes_before > (int) $ra->bytes_after, 'app photo compressed by the sweep (' . size_format( $ra->bytes_before ) . ' -> ' . size_format( $ra->bytes_after ) . ')' );
ok( 0 === (int) sp_att( $young )->bytes_after, 'a photo uploaded minutes ago is left to the normal pipeline (no double work)' );
ok( 5000 === (int) sp_att( $gone )->bytes_after, 'missing file marked done (after = before = 0 saved), not retried hourly' );
$wpdb->update( $t_att, array( 'created_at' => $hour_ago ), array( 'id' => $young ) );

// The cron trap: WordPress calls no-arg hooks with '' — must still do a full batch.
$extra = array();
for ( $i = 0; $i < 3; $i++ ) {
	$extra[] = sp_mk_att( $rid, 0, 'proof', "$url/" . basename( sp_jpeg( "$dir/cron$i.jpg", 2000, 1500 ) ), $hour_ago );
}
do_action( 'aun_sp_hourly_tidy' );
$done = 0;
foreach ( array_merge( array( $young ), $extra ) as $id ) {
	if ( (int) sp_att( $id )->bytes_after > 0 ) { $done++; }
}
ok( 4 === $done, "hourly hook processes a real batch, not 1 photo an hour ($done of 4)" );

$st = AUN_SP_Image::stats();
ok( is_int( $st['saved'] ) && $st['saved'] > 0 && $st['pending'] === AUN_SP_Image::pending_count(), 'stats() adds up (saved ' . size_format( $st['saved'] ) . ', pending ' . $st['pending'] . ')' );

/* ════════════════════════════════════════════════════════════════════ */
echo "\n=== 4. Tracking page shows the customer's own photos — and nothing else ===\n";

$phone = '0179900' . wp_rand( 1000, 9999 );
list( $rid, $ref ) = sp_mk_request( 'in_progress', $phone );
$i1 = sp_mk_item( $rid, 'lcd', 'LCD screen' );
$i2 = sp_mk_item( $rid, 'board', 'Main board' );
sp_jpeg( "$dir/web-lcd.jpg", 300, 200 );
sp_jpeg( "$dir/app-board.jpg", 300, 200 );
sp_jpeg( "$dir/resent.jpg", 300, 200 );
sp_mk_att( $rid, $i1, 'lcd',      "$url/web-lcd.jpg" );   // web form
sp_mk_att( $rid, $i2, 'proof',    "$url/app-board.jpg" ); // AUN Care app
sp_mk_att( $rid, 0,   'reupload', "$url/resent.jpg" );    // "send a clearer photo"
// Hostile / broken rows, inserted LATER so they would win if not filtered:
sp_mk_att( $rid, $i2, 'proof', 'https://evil.example/x.jpg' );
sp_jpeg( $up['basedir'] . '/outside-our-folder.jpg', 50, 50 );
sp_mk_att( $rid, $i1, 'lcd', $up['baseurl'] . '/outside-our-folder.jpg' );
sp_mk_att( $rid, 0, 'reupload', "$url/deleted-since.jpg" );
sp_mk_att( $rid, $i1, 'lcd', "$url/../../../wp-config.php" );

$_POST = array( '_nonce' => wp_create_nonce( 'aun_sp_public' ), 'by' => 'ref', 'query' => $ref, 'lang' => 'en' );
$j = sp_ajax( array( $trk, 'ajax_track' ) );
$R = $j['data']['requests'][0] ?? array();
$parts = array();
foreach ( (array) ( $R['parts'] ?? array() ) as $pp ) { $parts[ $pp['label'] ] = $pp['photo'] ?? null; }
ok( ! empty( $j['success'] ), 'track by reference succeeds' );
ok( ( $parts['LCD screen'] ?? '' ) === "$url/web-lcd.jpg", 'web-form photo shown on its part' );
ok( ( $parts['Main board'] ?? '' ) === "$url/app-board.jpg", 'app photo shown on its part (external URL ignored)' );
ok( ( $R['resent_photo'] ?? '' ) === "$url/resent.jpg", 're-sent photo shown (deleted file ignored)' );
$blob = wp_json_encode( $j );
ok( false === strpos( $blob, 'evil.example' ), 'an external URL is never sent to the page' );
ok( false === strpos( $blob, 'outside-our-folder' ), 'a real upload OUTSIDE aun-spare-parts/ is never sent' );
ok( false === strpos( $blob, 'wp-config' ), 'a path-traversal URL is never sent' );

$_POST = array( '_nonce' => wp_create_nonce( 'aun_sp_public' ), 'by' => 'phone', 'query' => $phone, 'lang' => 'en' );
$j2 = sp_ajax( array( $trk, 'ajax_track' ) );
ok( ( $j2['data']['requests'][0]['resent_photo'] ?? '' ) === "$url/resent.jpg", 'same photos when tracking by phone' );

list( $rid3, $ref3 ) = sp_mk_request( 'in_progress' );
sp_mk_item( $rid3, 'lcd', 'LCD screen' );
$_POST = array( '_nonce' => wp_create_nonce( 'aun_sp_public' ), 'by' => 'ref', 'query' => $ref3, 'lang' => 'en' );
$j3 = sp_ajax( array( $trk, 'ajax_track' ) );
ok( '' === ( $j3['data']['requests'][0]['parts'][0]['photo'] ?? 'x' ) && '' === ( $j3['data']['requests'][0]['resent_photo'] ?? 'x' ), 'no photo -> empty values, no thumbnail' );

/* ════════════════════════════════════════════════════════════════════ */
echo "\n=== 5. Re-upload is validated BEFORE the request is claimed ===\n";

list( $rw, $refw ) = sp_mk_request( 'waiting_customer' );
$status = function () use ( $rw ) {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( 'SELECT overall_status FROM ' . AUN_SP_Install::table( 'requests' ) . ' WHERE id = %d', $rw ) );
};

// Bigger than the host's upload_max_filesize: PHP keeps the name, zeroes the size.
$_SERVER['REMOTE_ADDR'] = '103.143.139.' . wp_rand( 10, 250 );
$_POST  = array( '_nonce' => wp_create_nonce( 'aun_sp_public' ), 'ref' => $refw, 'lang' => 'en' );
$_FILES = array( 'photo' => array( 'name' => 'huge.jpg', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0 ) );
$j = sp_ajax( array( $trk, 'ajax_reupload' ) );
ok( ( $j['data']['message'] ?? '' ) === AUN_SP_I18N::msg( 'srv_ru_large' ), 'over the host limit -> "too large" (was reported as a wrong file type)' );
ok( 'waiting_customer' === $status(), '…and the request was never flipped to "in progress"' );

$txt = wp_tempnam( 'fake.jpg' );
file_put_contents( $txt, "<?php echo 'not a photo';" );
$_POST  = array( '_nonce' => wp_create_nonce( 'aun_sp_public' ), 'ref' => $refw, 'lang' => 'en' );
$_FILES = array( 'photo' => array( 'name' => 'fake.jpg', 'type' => 'image/jpeg', 'tmp_name' => $txt, 'error' => UPLOAD_ERR_OK, 'size' => filesize( $txt ) ) );
$j = sp_ajax( array( $trk, 'ajax_reupload' ) );
ok( ( $j['data']['message'] ?? '' ) === AUN_SP_I18N::msg( 'srv_ru_badtype' ), 'a script renamed .jpg is refused by content, not by name' );
ok( 'waiting_customer' === $status(), '…still waiting, customer can try again' );
@unlink( $txt );

// Rate-limited reply is translated now (it was the one hardcoded English string).
$_SERVER['REMOTE_ADDR'] = '103.143.140.' . wp_rand( 10, 250 );
for ( $i = 0; $i < 10; $i++ ) { $rate->invoke( $trk, 'reupload', 10 ); }
$_POST = array( '_nonce' => wp_create_nonce( 'aun_sp_public' ), 'ref' => $refw, 'lang' => 'bn' );
$j = sp_ajax( array( $trk, 'ajax_reupload' ) );
ok( ( $j['data']['message'] ?? '' ) === AUN_SP_I18N::msg( 'srv_rate_limited' ) && false === strpos( (string) ( $j['data']['message'] ?? '' ), 'Too many uploads' ), 'rate-limit reply comes from Translations, not hardcoded English' );
$_FILES = array();

/* ════════════════════════════════════════════════════════════════════ */
echo "\n=== 6. Settings page: save, storage box, compress-now ===\n";

if ( ! class_exists( 'AUN_SP_Admin' ) ) {
	require_once AUN_SP_DIR . 'includes/class-aun-sp-admin.php';
}
wp_set_current_user( 1 );
$admin = new AUN_SP_Admin();

// Post back every existing value, so only the two new fields change.
$_POST = array(
	'aun_sp_settings_nonce'   => wp_create_nonce( 'aun_sp_settings' ),
	'warranty_months'         => get_option( 'aun_sp_warranty_months', 12 ),
	'grace_days'              => get_option( 'aun_sp_warranty_grace_days', 4 ),
	'alert_email'             => get_option( 'aun_sp_alert_email', get_option( 'admin_email' ) ),
	'hard_source_years'       => get_option( 'aun_sp_hard_source_years', 3 ),
	'tracking_url'            => get_option( 'aun_sp_tracking_url', '' ),
	'service_url'             => get_option( 'aun_sp_service_url', '' ),
	'goodwill_coupon'         => get_option( 'aun_sp_goodwill_coupon', '' ),
	'contact_phone'           => get_option( 'aun_sp_contact_phone', '' ),
	'abandoned_pay_hours'     => get_option( 'aun_sp_abandoned_pay_hours', 6 ),
	'order_status_dispatched' => get_option( 'aun_sp_order_status_dispatched', '' ),
	'order_status_delivered'  => get_option( 'aun_sp_order_status_delivered', 'completed' ),
	'quote_valid_days'        => get_option( 'aun_sp_quote_valid_days', 7 ),
	'img_preset'              => 'smaller',
	'img_format'              => 'webp',
);
if ( get_option( 'aun_sp_mute_foreign_sms', 1 ) ) { $_POST['mute_foreign_sms'] = '1'; }

$warnings = array();
set_error_handler( function ( $no, $str, $file, $line ) use ( &$warnings ) {
	if ( false !== strpos( $file, 'aun-spare-parts' ) ) { $warnings[] = "$str @ " . basename( $file ) . ":$line"; }
	return false;
} );
ob_start();
$admin->page_settings();
$html = ob_get_clean();
ok( 'smaller' === get_option( 'aun_sp_img_preset' ) && 'webp' === get_option( 'aun_sp_img_format' ), 'compression settings save' );
ok( false !== strpos( $html, 'Photo compression' ) && false !== strpos( $html, 'Photo storage' ), 'settings rows + storage box render' );
ok( false !== strpos( $html, 'value="smaller" selected' ), 'saved level is pre-selected' );

$_POST['img_preset'] = '"><script>alert(1)</script>';
$_POST['img_format'] = 'gif';
ob_start(); $admin->page_settings(); ob_end_clean();
ok( 'balanced' === get_option( 'aun_sp_img_preset' ) && 'jpeg' === get_option( 'aun_sp_img_format' ), 'hostile values fall back to Balanced / JPEG' );

// Compress-now button, with something waiting.
$w1 = sp_mk_att( $rid, 0, 'proof', "$url/" . basename( sp_jpeg( "$dir/now.jpg", 2000, 1500 ) ), $hour_ago );
$_POST = array( 'aun_sp_img_now_nonce' => wp_create_nonce( 'aun_sp_img_now' ) );
ob_start(); $admin->page_settings(); $html = ob_get_clean();
ok( false !== strpos( $html, 'Compressed' ) && (int) sp_att( $w1 )->bytes_after > 0, '"Compress waiting photos now" does the work and says so' );
restore_error_handler();
ok( ! $warnings, 'no PHP warnings/notices from the plugin while rendering' . ( $warnings ? ': ' . implode( ' | ', array_slice( $warnings, 0, 3 ) ) : '' ) );
$_POST = array();

/* ── cleanup ──────────────────────────────────────────────────────── */
foreach ( $GLOBALS['made_requests'] as $id ) {
	$wpdb->delete( $t_req, array( 'id' => $id ) );
	$wpdb->delete( $t_item, array( 'request_id' => $id ) );
	$wpdb->delete( $t_att, array( 'request_id' => $id ) );
}
foreach ( glob( "$dir/engine/*" ) ?: array() as $f ) { @unlink( $f ); }
@rmdir( "$dir/engine" );
foreach ( glob( "$dir/*" ) ?: array() as $f ) { @unlink( $f ); }
@rmdir( $dir );
@unlink( $up['basedir'] . '/outside-our-folder.jpg' );
update_option( 'aun_sp_img_preset', 'balanced' );
delete_option( 'aun_sp_img_format' ); // back to the shipped default (WebP)

printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
