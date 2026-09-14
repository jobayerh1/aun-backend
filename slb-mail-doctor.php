<?php
/**
 * SLB Warranty — mail doctor. One-off diagnostic, read-only by default.
 *
 * Answers, with evidence rather than guesswork:
 *   1. Are the plugin's email templates still there? (a blank one stops mail SILENTLY)
 *   2. Does wp_mail() actually work on this server right now?
 *   3. Which plugin is handling SMTP, and is anything hijacking wp_mail?
 *   4. Is Contact Form 7 still set to email you when someone registers?
 *   5. Are registrations still arriving, and how many need manual action?
 *
 * USE:
 *   1. Upload to the WordPress ROOT (same folder as wp-load.php).
 *   2. Visit https://aun-projector.com.bd/slb-mail-doctor.php?key=f5ca1f49657c72dd6e43f581
 *   3. Send me the output.
 *   4. DELETE THE FILE afterwards.
 *
 * Nothing is changed. The ONLY action it can take is sending one test email to
 * your own admin address, and only if YOU add &send=1 to the URL.
 */

const DOCTOR_KEY = 'f5ca1f49657c72dd6e43f581';

if ( ! isset( $_GET['key'] ) || ! hash_equals( DOCTOR_KEY, (string) $_GET['key'] ) ) {
	header( 'HTTP/1.1 404 Not Found' );
	exit;
}

define( 'DONOTCACHEPAGE', true );
require_once __DIR__ . '/wp-load.php';
header( 'Content-Type: text/plain; charset=utf-8' );

function out( $l, $v ) {
	if ( is_bool( $v ) ) { $v = $v ? 'YES' : 'NO'; }
	if ( null === $v )   { $v = 'NULL'; }
	if ( '' === $v )     { $v = '(EMPTY)'; }
	printf( "%-42s %s\n", $l . ':', $v );
}

echo "SLB WARRANTY — MAIL DOCTOR   " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
echo str_repeat( '=', 74 ) . "\n";

/* ---------- 1. the plugin's own email templates ---------- */
echo "\n-- 1. EMAIL TEMPLATES (a blank body stops that email silently) --\n";
$opts = get_option( 'slb_warranty_opts', array() );
$tpl  = isset( $opts['email_templates'] ) ? (array) $opts['email_templates'] : array();
$sms  = isset( $opts['sms_templates'] ) ? (array) $opts['sms_templates'] : array();
$keys = array( 'received', 'approved', 'rejected', 'duplicate', 'not_found', 'mismatch', 'model_mismatch', 'shop_mismatch' );
$blank = 0;
foreach ( $keys as $k ) {
	$body = trim( (string) ( $tpl[ $k ] ?? '' ) );
	$subj = trim( (string) ( $tpl[ $k . '_subject' ] ?? '' ) );
	if ( '' === $body ) { $blank++; }
	printf( "  %-16s body:%-9s subject:%-9s sms:%s\n",
		$k,
		'' === $body ? 'EMPTY!' : strlen( $body ) . ' ch',
		'' === $subj ? 'empty' : strlen( $subj ) . ' ch',
		'' === trim( (string) ( $sms[ $k ] ?? '' ) ) ? 'empty' : 'ok'
	);
}
echo $blank
	? "\n  >> $blank email template(s) are EMPTY — those emails are skipped without any error.\n"
	: "\n  >> All email templates present.\n";

/* ---------- 2. is anything hijacking wp_mail? ---------- */
echo "\n-- 2. WHO IS HANDLING MAIL --\n";
global $wp_filter;
foreach ( array( 'pre_wp_mail', 'wp_mail', 'wp_mail_from', 'wp_mail_from_name', 'phpmailer_init', 'wp_mail_failed' ) as $h ) {
	$names = array();
	if ( ! empty( $wp_filter[ $h ] ) ) {
		foreach ( $wp_filter[ $h ]->callbacks as $prio => $cbs ) {
			foreach ( $cbs as $cb ) {
				$f = $cb['function'];
				if ( is_array( $f ) ) {
					$names[] = ( is_object( $f[0] ) ? get_class( $f[0] ) : (string) $f[0] ) . '->' . $f[1];
				} elseif ( $f instanceof Closure ) {
					$r = new ReflectionFunction( $f );
					$names[] = 'Closure@' . basename( (string) $r->getFileName() ) . ':' . $r->getStartLine();
				} else {
					$names[] = (string) $f;
				}
			}
		}
	}
	out( $h, $names ? implode( ', ', $names ) : 'nothing attached' );
}
echo "\n  Active plugins that look mail-related:\n";
$found_smtp = false;
foreach ( (array) get_option( 'active_plugins', array() ) as $p ) {
	if ( preg_match( '~(smtp|mail|postman|sendgrid|mailgun|brevo|sendinblue|ses|fluent)~i', $p ) ) {
		echo "    - $p\n";
		$found_smtp = true;
	}
}
if ( ! $found_smtp ) {
	echo "    (none — WordPress is using the server's own PHP mail(), which often\n";
	echo "     fails silently and never appears in any mailbox 'Sent' folder)\n";
}

/* ---------- 3. Contact Form 7: the admin notification ---------- */
echo "\n-- 3. CONTACT FORM 7 — where your admin notification comes from --\n";
if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
	echo "  Contact Form 7 is NOT active.\n";
} else {
	$forms = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'numberposts' => 20, 'post_status' => 'any' ) );
	if ( ! $forms ) { echo "  No CF7 forms found.\n"; }
	foreach ( $forms as $f ) {
		$cf   = WPCF7_ContactForm::get_instance( $f->ID );
		if ( ! $cf ) { continue; }
		$mail = $cf->prop( 'mail' );
		$on   = ! empty( $mail['active'] ) || ! isset( $mail['active'] );
		printf( "  #%-5d %-34s mail:%-9s to:%s\n",
			$f->ID,
			mb_substr( $f->post_title, 0, 34 ),
			$on ? 'ON' : 'OFF !!',
			isset( $mail['recipient'] ) ? $mail['recipient'] : '?'
		);
	}
	echo "  (The registration form's 'mail' must be ON, with your address as recipient —\n";
	echo "   this, not the warranty plugin, is what used to email you on each submission.)\n";
}

/* ---------- 4. registrations still arriving? ---------- */
echo "\n-- 4. REGISTRATIONS --\n";
global $wpdb;
$t = $wpdb->prefix . 'slb_registrations';
if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
	echo "  Table $t not found.\n";
} else {
	foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) n FROM $t GROUP BY status ORDER BY n DESC" ) as $r ) {
		printf( "  %-16s %d\n", $r->status, $r->n );
	}
	echo "\n  Last 10:\n";
	foreach ( (array) $wpdb->get_results( "SELECT id, serial, status, email, created_at FROM $t ORDER BY id DESC LIMIT 10" ) as $r ) {
		printf( "    #%-5d %-14s %-13s %-28s %s\n", $r->id, $r->serial, $r->status, $r->email, $r->created_at );
	}
}

/* ---------- 5. the decisive test (opt-in) ---------- */
echo "\n-- 5. LIVE wp_mail() TEST --\n";
$admin = get_option( 'admin_email' );
out( 'admin_email', $admin );
if ( empty( $_GET['send'] ) ) {
	echo "  Not sent. To actually try it, add &send=1 to this URL — it will email\n";
    echo "  ONE test message to the address above and report the exact failure.\n";
} else {
	$err = '';
	add_action( 'wp_mail_failed', function ( $e ) use ( &$err ) { $err = $e->get_error_message(); } );
	$okm = wp_mail( $admin, 'SLB mail doctor test ' . gmdate( 'H:i:s' ), "If you can read this, wp_mail() works.\n" );
	out( 'wp_mail() returned', $okm );
	out( 'error reported', '' === $err ? '(none)' : $err );
	echo $okm
		? "  >> Handed to the mail server OK. If it never arrives, the problem is\n     delivery/spam or the SMTP account, not WordPress.\n"
		: "  >> wp_mail() FAILED — this is why nothing is being sent at all.\n";
}

echo "\n" . str_repeat( '=', 74 ) . "\nEND — delete this file from the server now.\n";
