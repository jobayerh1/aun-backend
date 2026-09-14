<?php
/**
 * SLB Warranty — mail doctor #2. Read-only. Nothing is changed, nothing is sent.
 *
 * Doctor #1 proved the templates are fine and CF7 is configured correctly, and it
 * surfaced two new leads. This one chases both:
 *
 *   A. WHY ARE RECENT REGISTRATIONS STORING NO CUSTOMER EMAIL?
 *      #50 and #51 have a blank email; #49 (18 Aug) had one. The plugin reads the
 *      address by FIELD NAME from the CF7 submission, so if a field was renamed
 *      the address silently vanishes — and no customer email can ever be sent.
 *
 *   B. WHY IS NOTHING LEAVING THE SERVER?
 *      Mail goes out through WPO365 / Microsoft Graph, and WP Mail Logging has
 *      recorded every attempt and error. That log is the answer.
 *
 * USE:
 *   1. Upload to the WordPress ROOT (next to wp-load.php).
 *   2. Visit https://aun-projector.com.bd/slb-mail-doctor-2.php?key=b7f3e91c4a2d8065f1c3
 *   3. Send me the output, then DELETE the file.
 */

const DOCTOR2_KEY = 'b7f3e91c4a2d8065f1c3';

if ( ! isset( $_GET['key'] ) || ! hash_equals( DOCTOR2_KEY, (string) $_GET['key'] ) ) {
	header( 'HTTP/1.1 404 Not Found' );
	exit;
}

define( 'DONOTCACHEPAGE', true );
require_once __DIR__ . '/wp-load.php';
header( 'Content-Type: text/plain; charset=utf-8' );

echo "SLB WARRANTY — MAIL DOCTOR #2   " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
echo str_repeat( '=', 78 ) . "\n";

global $wpdb;
$opts = get_option( 'slb_warranty_opts', array() );

/* ═══ A1. field mapping vs the real form ═══ */
echo "\n-- A1. PLUGIN FIELD MAPPING vs THE ACTUAL CF7 FORM --\n";
$map = array();
foreach ( (array) $opts as $k => $v ) {
	if ( 0 === strpos( $k, 'field_' ) ) { $map[ $k ] = (string) $v; }
}
$form_id = (int) ( $opts['cf7_form_id'] ?? 0 );
echo "  cf7_form_id setting: " . ( $form_id ?: '0 (not set)' ) . "\n\n";

$tags = array();
if ( class_exists( 'WPCF7_ContactForm' ) ) {
	// Prefer the configured form; fall back to the one named like the warranty form.
	$cf = $form_id ? WPCF7_ContactForm::get_instance( $form_id ) : null;
	if ( ! $cf ) {
		foreach ( get_posts( array( 'post_type' => 'wpcf7_contact_form', 'numberposts' => 30, 'post_status' => 'any' ) ) as $p ) {
			if ( false !== stripos( $p->post_title, 'warrant' ) ) { $cf = WPCF7_ContactForm::get_instance( $p->ID ); break; }
		}
	}
	if ( $cf ) {
		echo "  Reading form: #" . $cf->id() . " \"" . $cf->title() . "\"\n";
		foreach ( (array) $cf->scan_form_tags() as $tg ) {
			if ( '' !== $tg->name ) { $tags[ $tg->name ] = $tg->basetype; }
		}
		echo "  Fields actually in the form:\n";
		foreach ( $tags as $n => $bt ) { printf( "    %-26s (%s)\n", $n, $bt ); }
	} else {
		echo "  !! Could not load a warranty CF7 form.\n";
	}
} else {
	echo "  Contact Form 7 not active.\n";
}

echo "\n  Mapping check (this is the one that matters):\n";
foreach ( $map as $k => $v ) {
	$hit = isset( $tags[ $v ] );
	printf( "    %-16s -> %-24s %s\n", $k, $v === '' ? '(blank)' : $v,
		$tags ? ( $hit ? 'OK' : '*** NOT IN THE FORM ***' ) : '(form unknown)' );
}
echo "\n  Any form field that looks like an email but is not mapped:\n";
$unmapped = 0;
foreach ( $tags as $n => $bt ) {
	if ( ( 'email' === $bt || false !== stripos( $n, 'mail' ) ) && ! in_array( $n, $map, true ) ) {
		echo "    -> \"$n\" ($bt)   <= the address is probably arriving under THIS name\n";
		$unmapped++;
	}
}
if ( ! $unmapped ) { echo "    (none)\n"; }

/* ═══ A2. when did the address stop being captured? ═══ */
echo "\n-- A2. RECENT REGISTRATIONS: WHICH FIELDS ARRIVED? --\n";
$t = $wpdb->prefix . 'slb_registrations';
$rows = $wpdb->get_results( "SELECT id, serial, status, customer_name, phone, email, product_model, dealer_name, invoice_no, purchase_date, created_at FROM $t ORDER BY id DESC LIMIT 12" );
printf( "  %-5s %-13s %-11s %-6s %-6s %-6s %-6s %-6s %s\n", 'id', 'serial', 'status', 'name', 'phone', 'email', 'model', 'dealer', 'created' );
foreach ( (array) $rows as $r ) {
	printf( "  #%-4d %-13s %-11s %-6s %-6s %-6s %-6s %-6s %s\n",
		$r->id, $r->serial, $r->status,
		'' === trim( (string) $r->customer_name ) ? 'BLANK' : 'ok',
		'' === trim( (string) $r->phone ) ? 'BLANK' : 'ok',
		'' === trim( (string) $r->email ) ? 'BLANK' : 'ok',
		'' === trim( (string) $r->product_model ) ? 'BLANK' : 'ok',
		'' === trim( (string) $r->dealer_name ) ? 'BLANK' : 'ok',
		$r->created_at
	);
}
$first_blank = $wpdb->get_row( "SELECT id, created_at FROM $t WHERE (email IS NULL OR email = '') ORDER BY id ASC LIMIT 1" );
$last_good   = $wpdb->get_row( "SELECT id, created_at FROM $t WHERE email <> '' ORDER BY id DESC LIMIT 1" );
echo "\n  First registration with NO email : " . ( $first_blank ? '#' . $first_blank->id . ' on ' . $first_blank->created_at : 'none' ) . "\n";
echo "  Most recent WITH an email        : " . ( $last_good ? '#' . $last_good->id . ' on ' . $last_good->created_at : 'none' ) . "\n";
echo "  (the changeover date is when the form field was renamed)\n";

/* ═══ B1. is wp_mail even being attempted? ═══ */
echo "\n-- B1. WP MAIL LOGGING — what actually happened --\n";
$log = $wpdb->prefix . 'wpml_mails';
if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $log ) ) !== $log ) {
	echo "  Log table not found ($log).\n";
} else {
	$cols = array();
	foreach ( (array) $wpdb->get_results( "SHOW COLUMNS FROM $log" ) as $c ) { $cols[] = $c->Field; }
	echo "  columns: " . implode( ', ', $cols ) . "\n";
	$pick = array_values( array_intersect( array( 'mail_id', 'timestamp', 'receiver', 'subject', 'error' ), $cols ) );
	$sel  = $pick ? implode( ', ', $pick ) : '*';
	$order = in_array( 'mail_id', $cols, true ) ? 'mail_id' : $cols[0];
	echo "  Total logged: " . (int) $wpdb->get_var( "SELECT COUNT(*) FROM $log" ) . "\n\n";
	foreach ( (array) $wpdb->get_results( "SELECT $sel FROM $log ORDER BY $order DESC LIMIT 12", ARRAY_A ) as $row ) {
		$bits = array();
		foreach ( $row as $k => $v ) {
			$v = trim( preg_replace( '/\s+/', ' ', (string) $v ) );
			if ( 'error' === $k ) { $v = ( '' === $v ? '-' : substr( $v, 0, 300 ) ); }
			else { $v = substr( $v, 0, 60 ); }
			$bits[] = "$k=$v";
		}
		echo "    " . implode( ' | ', $bits ) . "\n";
	}
	echo "\n  >> Entries after 18 Aug with an 'error' value tell you exactly why nothing sends.\n";
	echo "  >> No entries at all after 18 Aug would mean wp_mail() is never even called.\n";
}

/* ═══ B2. our own code on the wp_mail hook ═══ */
echo "\n-- B2. WHAT OUR SEO PLUGIN DOES ON THE wp_mail HOOK --\n";
global $wp_filter;
$shown = 0;
if ( ! empty( $wp_filter['wp_mail'] ) ) {
	foreach ( $wp_filter['wp_mail']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$f = $cb['function'];
			if ( ! ( $f instanceof Closure ) ) { continue; }
			$r    = new ReflectionFunction( $f );
			$file = (string) $r->getFileName();
			printf( "  Closure at priority %s — %s:%d-%d\n", $prio, basename( $file ), $r->getStartLine(), $r->getEndLine() );
			if ( is_readable( $file ) ) {
				$src = file( $file );
				for ( $i = max( 0, $r->getStartLine() - 4 ); $i < min( count( $src ), $r->getEndLine() + 2 ); $i++ ) {
					echo '    ' . ( $i + 1 ) . ': ' . rtrim( $src[ $i ] ) . "\n";
				}
			}
			$shown++;
		}
	}
}
if ( ! $shown ) { echo "  (no closures on wp_mail)\n"; }

/* ═══ B3. WPO365 Graph mailer — status only, never secrets ═══ */
echo "\n-- B3. WPO365 / MICROSOFT GRAPH MAILER --\n";
$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wpo365%' ORDER BY option_name" );
echo "  " . count( (array) $names ) . " wpo365 option(s). Values are NOT printed (they hold secrets);\n";
echo "  only whether each is set, plus anything that looks like a status/error:\n";
foreach ( (array) $names as $n ) {
	$v   = get_option( $n );
	$len = is_scalar( $v ) ? strlen( (string) $v ) : ( is_array( $v ) ? count( $v ) . ' keys' : gettype( $v ) );
	$flag = preg_match( '/error|expir|status|health|last|fail/i', $n );
	if ( $flag && is_scalar( $v ) ) {
		printf( "    %-52s = %s\n", $n, substr( (string) $v, 0, 140 ) );
	} else {
		printf( "    %-52s (%s)\n", $n, '' === $v || null === $v ? 'EMPTY' : $len );
	}
}

echo "\n" . str_repeat( '=', 78 ) . "\nEND — delete this file from the server now.\n";
