<?php
/**
 * AUN — spare-parts log viewer (read-only).
 *
 * Upload to the WordPress root (next to wp-config.php), then open:
 *   https://aun-projector.com.bd/aun-sp-logs.php?key=d022bb1cfa9c9f4b2cb70475
 *
 * It finds the PHP error log(s) on this hosting account and shows:
 *   1. every "AUN SP:" line (ERP lookup failures, and from 0.47.3 every
 *      "customer saw Network error" report sent by a customer's browser);
 *   2. the most recent PHP fatal errors, from any plugin.
 * It changes nothing and deliberately does NOT load WordPress, so it still works
 * when the site itself is broken.
 *
 * DELETE THIS FILE when you have copied what you need.
 */

const AUN_LOGS_KEY = 'd022bb1cfa9c9f4b2cb70475';

header( 'Cache-Control: no-store, max-age=0' );
header( 'X-Robots-Tag: noindex, nofollow' );
header( 'Content-Type: text/html; charset=utf-8' );

if ( ! isset( $_GET['key'] ) || ! is_string( $_GET['key'] ) || ! hash_equals( AUN_LOGS_KEY, $_GET['key'] ) ) {
	http_response_code( 404 );
	exit( 'Not found.' );
}

$root = __DIR__;
$tail = 6 * 1024 * 1024; // read at most the last 6 MB of each log
$show = isset( $_GET['n'] ) ? max( 20, min( 1000, (int) $_GET['n'] ) ) : 200;

/* ── where can PHP errors land on cPanel? ──
 * cPanel's default is "error_log" in the folder of the script that ran: front-end
 * pages and cron -> <root>/error_log, but every admin-ajax call (the spare-parts
 * form, tracking page, these reports) -> <root>/wp-admin/error_log. */
$cands = array();
$ini   = (string) ini_get( 'error_log' );
if ( '' !== $ini && 'syslog' !== $ini ) {
	$cands[] = ( '/' === $ini[0] || preg_match( '/^[A-Za-z]:[\\\\\/]/', $ini ) ) ? $ini : $root . '/' . $ini;
	$cands[] = $root . '/wp-admin/' . basename( $ini );
}
foreach ( array( '/error_log', '/wp-admin/error_log', '/wp-content/debug.log', '/wp-content/error_log', '/wp-includes/error_log' ) as $rel ) {
	$cands[] = $root . $rel;
}
foreach ( array( '/*/error_log', '/*/*/error_log', '/wp-content/plugins/*/error_log', '/../logs/*error*' ) as $pat ) {
	foreach ( (array) glob( $root . $pat ) as $g ) { $cands[] = $g; }
}
$logs = array();
foreach ( $cands as $c ) {
	$r = realpath( $c );
	if ( $r && is_file( $r ) && is_readable( $r ) ) { $logs[ $r ] = filesize( $r ); }
}

function aun_logs_tail( $file, $bytes ) {
	$size = filesize( $file );
	$fh   = fopen( $file, 'rb' );
	if ( ! $fh ) { return array(); }
	if ( $size > $bytes ) { fseek( $fh, -$bytes, SEEK_END ); }
	$data = (string) stream_get_contents( $fh );
	fclose( $fh );
	$lines = preg_split( '/\r?\n/', $data );
	if ( $size > $bytes ) { array_shift( $lines ); } // first line is cut in half
	return $lines;
}
function aun_logs_mask( $s ) {
	return preg_replace( '/(?:\+?88)?01\d{9}/', '01*********', $s ); // no customer phone numbers on screen
}

$net = array(); $sp = array(); $fatal = array();
foreach ( $logs as $file => $size ) {
	$short = 0 === strpos( $file, $root ) ? substr( $file, strlen( $root ) ) : basename( $file );
	foreach ( aun_logs_tail( $file, $tail ) as $line ) {
		if ( '' === trim( $line ) ) { continue; }
		if ( false !== strpos( $line, 'customer saw "Network error"' ) ) {
			$net[] = $line . '   [' . $short . ']';
		} elseif ( false !== strpos( $line, 'AUN SP' ) || false !== strpos( $line, 'aun-spare-parts' ) ) {
			$sp[] = $line . '   [' . $short . ']';
		}
		if ( false !== stripos( $line, 'PHP Fatal error' ) || false !== stripos( $line, 'Uncaught' ) ) {
			$fatal[] = $line . '   [' . $short . ']';
		}
	}
}
// newest first, so the latest event is the first thing on screen
$net   = array_reverse( array_slice( $net, -$show ) );
$sp    = array_reverse( array_slice( $sp, -$show ) );
$fatal = array_reverse( array_slice( $fatal, -40 ) );

$ver = '?';
$pf  = $root . '/wp-content/plugins/aun-spare-parts/aun-spare-parts.php';
if ( is_readable( $pf ) && preg_match( '/^\s*\*\s*Version:\s*(\S+)/mi', (string) file_get_contents( $pf, false, null, 0, 4096 ), $m ) ) {
	$ver = $m[1];
}
$h = function ( $s ) { return htmlspecialchars( aun_logs_mask( (string) $s ), ENT_QUOTES, 'UTF-8' ); };
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>AUN SP logs</title>
<style data-no-optimize="1" data-no-minify="1" data-no-defer="1">
body{font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:16px;background:#f5f7fa;color:#1d2733}
h1{font-size:20px;margin:0 0 4px}h2{font-size:16px;margin:24px 0 8px}
.card{background:#fff;border:1px solid #dde3ea;border-radius:10px;padding:14px 16px;margin:0 0 14px;max-width:1100px}
.warn{background:#fff6e5;border-color:#f3c46b}
table{border-collapse:collapse;width:100%}td,th{text-align:left;padding:4px 8px;border-bottom:1px solid #eef1f5;font-size:13px}
pre{white-space:pre-wrap;word-break:break-word;font:12px/1.55 ui-monospace,Consolas,monospace;background:#0f1720;color:#dce6f0;padding:12px;border-radius:8px;max-height:70vh;overflow:auto;margin:0}
.muted{color:#66768a}.b{color:#0188fe;font-weight:600}
</style></head><body>
<div class="card warn"><strong>Read-only.</strong> When you've copied what you need, <strong>delete <code>aun-sp-logs.php</code></strong> from the server.</div>
<div class="card">
	<h1>Spare-parts log</h1>
	<div class="muted">Plugin version on the server: <span class="b"><?php echo $h( $ver ); ?></span> · server time <?php echo $h( date( 'Y-m-d H:i:s T' ) ); ?> · PHP error_log setting: <code><?php echo $h( '' === $ini ? '(not set)' : $ini ); ?></code></div>
</div>

<div class="card">
	<h2 style="margin-top:0">Log files found</h2>
	<?php if ( ! $logs ) : ?>
		<p>No readable PHP error log was found. Ask the host to enable "log_errors" (cPanel → MultiPHP INI Editor), or turn on WP_DEBUG_LOG.</p>
	<?php else : ?>
		<table><tr><th>File</th><th>Size</th><th>Last written</th></tr>
		<?php foreach ( $logs as $file => $size ) : ?>
			<tr><td><code><?php echo $h( str_replace( $root, '', $file ) ?: $file ); ?></code></td><td><?php echo $h( number_format( $size / 1024, 1 ) ); ?> KB</td><td><?php echo $h( date( 'Y-m-d H:i', filemtime( $file ) ) ); ?></td></tr>
		<?php endforeach; ?>
		</table>
	<?php endif; ?>
</div>

<div class="card">
	<h2 style="margin-top:0">1 · Customers who saw "Network error" <span class="muted">(newest first)</span></h2>
	<p class="muted">Sent by the customer's own browser (from 0.47.3). Read it as: <b>at</b> which step · <b>HTTP</b> what came back (0 = no answer at all) · <b>after</b> how long · <b>upload</b> size of their photos · <b>got:</b> the start of what arrived instead of an answer · their phone/browser.</p>
	<?php if ( $net ) : ?><pre><?php echo $h( implode( "\n", $net ) ); ?></pre>
	<?php else : ?><p>None yet. Reports only arrive after 0.47.3 is installed <strong>and</strong> the WP Rocket cache is cleared (cached pages keep the old script).</p><?php endif; ?>
</div>

<div class="card">
	<h2 style="margin-top:0">2 · Other spare-parts lines <span class="muted">(newest first)</span></h2>
	<p class="muted">"ERP lookup attempt 1/2 failed" = the sales-records server didn't answer properly and the plugin asked again. "attempt 2/2 failed" = it failed twice; the customer was told to try again in a moment.</p>
	<?php if ( $sp ) : ?><pre><?php echo $h( implode( "\n", $sp ) ); ?></pre>
	<?php else : ?><p>None.</p><?php endif; ?>
</div>

<div class="card">
	<h2 style="margin-top:0">3 · Recent PHP fatal errors <span class="muted">(any plugin, newest first, last 40)</span></h2>
	<?php if ( $fatal ) : ?><pre><?php echo $h( implode( "\n", $fatal ) ); ?></pre>
	<?php else : ?><p>None in the readable part of the logs.</p><?php endif; ?>
</div>
<p class="muted">Show more spare-parts lines: add <code>&amp;n=1000</code> to the address.</p>
</body></html>
