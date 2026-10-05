<?php
/**
 * SLB Warranty — forensic audit of registration #12 (serial 326648367020).
 * READ-ONLY. Nothing is changed, nothing is sent.
 *
 * Answers, from your real data:
 *   1. Did a reject EVER reach this row? (updated_at + the mail log)
 *   2. Where did the shop "Star Technology" come from, when it is not in the list?
 *   3. Which serial did the customer probably mean ("the serial was wrong")?
 *   4. Has the wrong-serial loophole already been used on any OTHER registration?
 *
 * USE:
 *   1. Upload to the WordPress ROOT (same folder as wp-load.php).
 *   2. Visit https://aun-projector.com.bd/slb-audit-12.php?key=b5c8fe627631fefaa8e49366
 *   3. Copy everything on the page and send it to me.
 *   4. DELETE THIS FILE from the server — it prints customer names and phones.
 */

const AUDIT_KEY    = 'b5c8fe627631fefaa8e49366';
const AUDIT_SERIAL = '326648367020';
const AUDIT_ID     = 12;

if ( ! isset( $_GET['key'] ) || ! hash_equals( AUDIT_KEY, (string) $_GET['key'] ) ) {
	header( 'HTTP/1.1 404 Not Found' );
	exit;
}

define( 'DONOTCACHEPAGE', true );
require_once __DIR__ . '/wp-load.php';
nocache_headers();
header( 'X-Robots-Tag: noindex, nofollow' );
header( 'Content-Type: text/plain; charset=utf-8' );

global $wpdb;
$t_regs = $wpdb->prefix . 'slb_registrations';
$t_ser  = $wpdb->prefix . 'slb_serials';
$t_dist = $wpdb->prefix . 'slb_distributors';
$t_prod = $wpdb->prefix . 'slb_products';
$opts   = get_option( 'slb_warranty_opts', array() );

function h( $t ) { echo "\n" . str_repeat( '=', 78 ) . "\n" . $t . "\n" . str_repeat( '=', 78 ) . "\n"; }
function kv( $k, $v ) { printf( "  %-26s %s\n", $k . ':', ( null === $v || '' === $v ) ? '(empty)' : $v ); }
function has_table( $t ) { global $wpdb; return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t; }

echo "SLB WARRANTY — FORENSIC AUDIT OF #" . AUDIT_ID . "   " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";

/* ─────────────────────────────────────────────────────────────── 0 */
h( '0. VERSIONS' );
if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
foreach ( get_plugins() as $file => $p ) {
	if ( preg_match( '/warranty|aun-app-api|contact-form-7|flamingo|wp-mail-logging/i', $file . ' ' . $p['Name'] ) ) {
		kv( $p['Name'], $p['Version'] . ( is_plugin_active( $file ) ? '' : '  (inactive)' ) );
	}
}
kv( 'auto-approve rule', $opts['auto_approve_rule'] ?? 'match_serial_and_distributor (default)' );
kv( 'site timezone', wp_timezone_string() );

/* ─────────────────────────────────────────────────────────────── 1 */
h( '1. THE REGISTRATION ITSELF' );
$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_regs WHERE id=%d OR serial=%s ORDER BY id LIMIT 1", AUDIT_ID, AUDIT_SERIAL ) );
if ( ! $r ) {
	echo "  !! No registration with id " . AUDIT_ID . " or serial " . AUDIT_SERIAL . " — it has been deleted or replaced.\n";
} else {
	foreach ( (array) $r as $k => $v ) { kv( $k, is_string( $v ) ? $v : var_export( $v, true ) ); }
	echo "\n  READING IT:\n";
	if ( ! empty( $r->created_at ) && ! empty( $r->updated_at ) ) {
		if ( $r->created_at === $r->updated_at ) {
			echo "  -> updated_at == created_at: this row has NEVER been changed since it was created.\n";
			echo "     No reject, no re-submission, no reconciler write ever reached it.\n";
		} else {
			echo "  -> Last changed " . $r->updated_at . " (created " . $r->created_at . ").\n";
			echo "     Something wrote to it after creation — compare that time with section 5 (mail log).\n";
		}
	}
	echo '  -> [DECISION] stamp in notes: ' . ( false !== strpos( (string) $r->notes, '[DECISION]' ) ? 'YES — a decision saved at least once' : 'none — no v2.9.0+ decision ever saved' ) . "\n";
	echo '  -> App registration: ' . ( false !== stripos( (string) $r->notes, 'app' ) ? 'notes mention the app' : 'no app marker in notes — most likely the website form' ) . "\n";
}

/* ─────────────────────────────────────────────────────────────── 2 */
h( '2. "STAR TECHNOLOGY" — WHERE DID IT COME FROM?' );
if ( $r ) {
	kv( 'shop as submitted', $r->dealer_name );
	kv( 'distributor_id on row', $r->distributor_id );
	if ( $r->distributor_id ) {
		$d = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_dist WHERE id=%d", $r->distributor_id ) );
		kv( '  -> that id now is', $d ? $d->name . ' (' . $d->address . ')' : 'MISSING — the distributor was deleted' );
	}
	$exact = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t_dist WHERE name=%s", $r->dealer_name ) );
	kv( 'exact name in list now', $exact ? 'yes (#' . $exact . ')' : 'NO' );
}
echo "\n  Every shop currently in the list (what the dropdown offers):\n";
foreach ( (array) $wpdb->get_results( "SELECT id, name, address FROM $t_dist ORDER BY name" ) as $d ) {
	printf( "    #%-4d %s%s\n", $d->id, $d->name, $d->address ? '  (' . $d->address . ')' : '' );
}
echo "\n  Anything resembling 'star' or 'tech' in the list:\n";
$like = $wpdb->get_results( "SELECT id, name FROM $t_dist WHERE name LIKE '%star%' OR name LIKE '%tech%'" );
if ( $like ) { foreach ( $like as $d ) { echo "    #{$d->id} {$d->name}\n"; } } else { echo "    (none)\n"; }

// How the live form's shop + model fields are really defined.
echo "\n  The live registration form:\n";
kv( '  CF7 form id (settings)', $opts['cf7_form_id'] ?? '0 (any form)' );
$map = array( 'shop' => $opts['field_dealer'] ?? 'dealer-name', 'model' => $opts['field_model'] ?? 'model', 'serial' => $opts['field_serial'] ?? 'serial-number' );
foreach ( $map as $k => $v ) { kv( '  field for ' . $k, $v ); }
if ( class_exists( 'WPCF7_ContactForm' ) ) {
	$fid = (int) ( $opts['cf7_form_id'] ?? 0 );
	$forms = $fid ? array( WPCF7_ContactForm::get_instance( $fid ) ) : array_map( function ( $p ) { return WPCF7_ContactForm::get_instance( $p->ID ); },
		get_posts( array( 'post_type' => 'wpcf7_contact_form', 'numberposts' => 30, 'post_status' => 'any' ) ) );
	foreach ( array_filter( $forms ) as $cf ) {
		$tags = array();
		foreach ( (array) $cf->scan_form_tags() as $tg ) { if ( $tg->name ) { $tags[ $tg->name ] = $tg; } }
		if ( ! isset( $tags[ $map['serial'] ] ) ) { continue; } // not the warranty form
		echo "    Form #" . $cf->id() . ' "' . $cf->title() . "\":\n";
		foreach ( array( 'shop', 'model' ) as $k ) {
			$tg = $tags[ $map[ $k ] ] ?? null;
			printf( "      %-6s field \"%s\": %s\n", $k, $map[ $k ],
				$tg ? ( 'type=' . $tg->type . ( $tg->is_required() ? ', REQUIRED' : ', NOT required' ) ) : 'NOT IN THE FORM' );
		}
	}
} else {
	echo "    Contact Form 7 is not active.\n";
}

echo "\n  Other registrations whose shop is NOT in the list (scale of the problem):\n";
$orph = $wpdb->get_results( "SELECT r.id, r.serial, r.status, r.dealer_name, r.created_at FROM $t_regs r
	LEFT JOIN $t_dist d ON d.name = r.dealer_name
	WHERE r.dealer_name <> '' AND r.dealer_name IS NOT NULL AND d.id IS NULL ORDER BY r.id" );
if ( $orph ) {
	foreach ( $orph as $o ) { printf( "    #%-5d %-14s %-10s %-28s %s\n", $o->id, $o->serial, $o->status, '"' . $o->dealer_name . '"', $o->created_at ); }
} else { echo "    (none)\n"; }

/* ─────────────────────────────────────────────────────────────── 3 */
h( '3. THE SERIAL — AND THE ONE THEY PROBABLY MEANT' );
$s = $wpdb->get_row( $wpdb->prepare(
	"SELECT s.*, d.name AS dist, p.name AS prod FROM $t_ser s LEFT JOIN $t_dist d ON s.distributor_id=d.id LEFT JOIN $t_prod p ON s.product_id=p.id WHERE s.serial=%s", AUDIT_SERIAL ) );
if ( $s ) {
	kv( 'serial', $s->serial );
	kv( 'ERP model', $s->prod );
	kv( 'ERP shop (sold to)', $s->dist );
	kv( 'shipped_date', $s->shipped_date );
	kv( 'source', $s->source );
	kv( 'added to list', $s->created_at );
	kv( 'registered flag', $s->registered . ( $s->registration_id ? '  (to registration #' . $s->registration_id . ')' : '' ) );
} else {
	echo "  Serial " . AUDIT_SERIAL . " is NOT in the serial list.\n";
}

echo "\n  Serials that differ from " . AUDIT_SERIAL . " by 1–2 characters (typo candidates):\n";
$len  = strlen( AUDIT_SERIAL );
$cand = $wpdb->get_results( $wpdb->prepare(
	"SELECT s.serial, s.shipped_date, s.registered, d.name AS dist, p.name AS prod FROM $t_ser s
	 LEFT JOIN $t_dist d ON s.distributor_id=d.id LEFT JOIN $t_prod p ON s.product_id=p.id
	 WHERE CHAR_LENGTH(s.serial) BETWEEN %d AND %d AND s.serial <> %s", $len - 1, $len + 1, AUDIT_SERIAL ) );
$hits = 0;
foreach ( (array) $cand as $c ) {
	$dist = levenshtein( AUDIT_SERIAL, (string) $c->serial );
	if ( $dist <= 2 ) {
		$hits++;
		printf( "    %-16s distance %d | %-16s | %-24s | shipped %s | %s\n", $c->serial, $dist, $c->prod ?: '?', $c->dist ?: '?', $c->shipped_date ?: '?',
			$c->registered ? 'ALREADY REGISTERED' : 'free' );
	}
}
if ( ! $hits ) { echo "    (none within 2 characters)\n"; }

/* ─────────────────────────────────────────────────────────────── 4 */
h( '4. HAS THE WRONG-SERIAL LOOPHOLE BEEN USED BEFORE?' );
echo "  Before 2.9.2, a registration that failed the shop/model check could be re-submitted\n";
echo "  with a different shop until it matched, and was then AUTO-approved — with the earlier\n";
echo "  mismatch erased from its notes. The mail log is the only trace left. Rows below got a\n";
echo "  'mismatch' email and are NOW approved: each deserves a look at its invoice.\n\n";
$ml = $wpdb->prefix . 'wpml_mails';
if ( has_table( $ml ) ) {
	$cols = $wpdb->get_col( "SHOW COLUMNS FROM $ml" );
	$body = in_array( 'message', $cols, true ) ? 'message' : ( in_array( 'body', $cols, true ) ? 'body' : '' );
	$when = in_array( 'timestamp', $cols, true ) ? 'timestamp' : ( in_array( 'time', $cols, true ) ? 'time' : $cols[0] );
	if ( $body ) {
		$rows = $wpdb->get_results( "SELECT `$when` AS t, receiver, `$body` AS m FROM $ml
			WHERE `$body` LIKE '%different shop%' OR `$body` LIKE '%different model%' OR `$body` LIKE '%do not match%' OR `$body` LIKE '%mismatch%'" );
		$seen = array();
		foreach ( (array) $rows as $mrow ) {
			if ( preg_match_all( '/\b(\d{8,16})\b/', wp_strip_all_tags( (string) $mrow->m ), $mm ) ) {
				foreach ( $mm[1] as $sn ) { $seen[ $sn ] = $mrow->t; }
			}
		}
		$flag = 0;
		foreach ( $seen as $sn => $t ) {
			$reg = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, customer_name, phone, dealer_name, updated_at FROM $t_regs WHERE serial=%s", $sn ) );
			if ( $reg && 'approved' === $reg->status ) {
				$flag++;
				printf( "    #%-5d %-14s mismatch mail %s -> APPROVED (last change %s)  %s, %s, shop \"%s\"\n",
					$reg->id, $sn, $t, $reg->updated_at, $reg->customer_name, $reg->phone, $reg->dealer_name );
			}
		}
		echo $flag ? "\n  " . $flag . " to check.\n" : "  None: no registration that was told 'mismatch' is approved now.\n";
		echo "  (" . count( $seen ) . " distinct serials received a mismatch email in total.)\n";
	} else {
		echo "  Mail log has no message column — cannot check.\n";
	}
} else {
	echo "  WP Mail Logging table not found ($ml) — cannot check from the mail log.\n";
}
echo "\n  App registrations that were corrected by the customer and are now approved:\n";
$app = $wpdb->get_results( "SELECT id, serial, customer_name, phone, updated_at FROM $t_regs WHERE status='approved' AND notes LIKE '%Corrected by the customer%'" );
if ( $app ) { foreach ( $app as $a ) { printf( "    #%-5d %-14s %s, %s (%s)\n", $a->id, $a->serial, $a->customer_name, $a->phone, $a->updated_at ); } }
else { echo "    (none)\n"; }

/* ─────────────────────────────────────────────────────────────── 5 */
h( '5. EVERY EMAIL ABOUT THIS REGISTRATION' );
if ( has_table( $ml ) && ! empty( $body ) ) {
	$email = $r ? (string) $r->email : '';
	$mails = $wpdb->get_results( $wpdb->prepare(
		"SELECT `$when` AS t, receiver, subject, `$body` AS m" . ( in_array( 'error', $cols, true ) ? ', error' : '' ) . " FROM $ml
		 WHERE `$body` LIKE %s" . ( $email ? ' OR receiver LIKE %s' : '' ) . " ORDER BY `$when`",
		...array_filter( array( '%' . $wpdb->esc_like( AUDIT_SERIAL ) . '%', $email ? '%' . $wpdb->esc_like( $email ) . '%' : null ) ) ) );
	if ( $mails ) {
		foreach ( $mails as $m ) {
			$first = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $m->m ) ) );
			printf( "  %s  to %s\n    subject: %s\n    text:    %s\n%s\n", $m->t, $m->receiver, $m->subject, mb_substr( $first, 0, 180 ),
				! empty( $m->error ) ? '    ERROR:   ' . $m->error . "\n" : '' );
		}
		echo "  -> A 'rejected' email here means a Reject DID run. If the row is still Mismatch,\n";
		echo "     that reject hit the 2.9.0 bug: the customer was told, the row was not changed.\n";
	} else {
		echo "  No email mentions this serial or address.\n";
	}
} else {
	echo "  Mail log not available.\n";
}

/* ─────────────────────────────────────────────────────────────── 6 */
h( '6. EXACTLY WHAT WAS SUBMITTED (Flamingo, if installed)' );
if ( post_type_exists( 'flamingo_inbound' ) ) {
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_value = %s", AUDIT_SERIAL ) );
	if ( $ids ) {
		foreach ( $ids as $pid ) {
			$p = get_post( $pid );
			if ( ! $p || 'flamingo_inbound' !== $p->post_type ) { continue; }
			echo "  Submission " . $p->post_date . ":\n";
			foreach ( get_post_meta( $pid ) as $mk => $mv ) {
				if ( 0 === strpos( $mk, '_field_' ) ) { printf( "    %-22s %s\n", substr( $mk, 7 ), is_array( $mv ) ? implode( ', ', $mv ) : $mv ); }
			}
		}
	} else {
		echo "  Flamingo has no stored submission containing this serial.\n";
	}
} else {
	echo "  Flamingo is not installed — form submissions are not stored anywhere else.\n";
}

echo "\n" . str_repeat( '=', 78 ) . "\nEND — now DELETE slb-audit-12.php from the server.\n";
