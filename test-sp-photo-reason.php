<?php
/**
 * AUN Spare Parts v0.45.0 — "we sent your photo back, and here is why".
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * The thing under test is not the upload — that already worked. It is whether the
 * REASON survives the whole journey: admin dropdown -> database -> the customer's
 * tracking page in their own language -> the SMS -> and then cleanly out of the way
 * once they send a new photo. A reason that gets lost at any hop leaves the customer
 * with exactly what they had before: "send a better photo", and no idea what to fix.
 */

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

if ( ! class_exists( 'AUN_SP_Requests' ) ) {
	echo "aun-spare-parts is not active on the bench\n"; exit( 1 );
}

global $wpdb;

// handle_post() refuses to act for anyone without manage_options. Without this the
// whole suite silently tests nothing and still reports passes.
wp_set_current_user( 1 );
if ( ! current_user_can( 'manage_options' ) ) {
	$u = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	if ( $u ) { wp_set_current_user( $u[0]->ID ); }
}

$t_req  = AUN_SP_Install::table( 'requests' );
$t_item = AUN_SP_Install::table( 'request_items' );
$t_att  = AUN_SP_Install::table( 'attachments' );
$t_ev   = AUN_SP_Install::table( 'events' );

echo "=== 0. Schema ===\n";
AUN_SP_Install::activate();
$cols = array();
foreach ( (array) $wpdb->get_results( "SHOW COLUMNS FROM $t_req" ) as $c ) { $cols[] = $c->Field; }
ok( in_array( 'photo_reason', $cols, true ),  'requests.photo_reason exists' );
ok( in_array( 'photo_note', $cols, true ),    'requests.photo_note exists' );
ok( in_array( 'photo_item_id', $cols, true ), 'requests.photo_item_id exists' );
ok( '12' === (string) get_option( 'aun_sp_db_version' ), 'db version is 12' );

/* ── a request with two parts, so "which one?" is a real question ── */
$ref = 'SP-TEST' . wp_rand( 1000, 9999 );
$wpdb->insert( $t_req, array(
	'ref' => $ref, 'model' => 'AUN M18', 'customer_name' => 'Photo Test',
	'phone_current' => '01799000777', 'overall_status' => 'in_progress',
	'created_at' => current_time( 'mysql' ),
) );
$rid = (int) $wpdb->insert_id;
$wpdb->insert( $t_item, array( 'request_id' => $rid, 'part_type' => 'lcd', 'part_label' => 'LCD panel', 'qty' => 1, 'line_status' => 'pending', 'created_at' => current_time( 'mysql' ) ) );
$item_lcd = (int) $wpdb->insert_id;
$wpdb->insert( $t_item, array( 'request_id' => $rid, 'part_type' => 'psu', 'part_label' => 'Power board', 'qty' => 1, 'line_status' => 'pending', 'created_at' => current_time( 'mysql' ) ) );
$item_psu = (int) $wpdb->insert_id;

// A second request, to prove one customer's part id can't be pinned to another's.
$wpdb->insert( $t_req, array( 'ref' => $ref . 'X', 'overall_status' => 'in_progress', 'created_at' => current_time( 'mysql' ) ) );
$rid_other  = (int) $wpdb->insert_id;
$wpdb->insert( $t_item, array( 'request_id' => $rid_other, 'part_label' => 'Someone else lens', 'created_at' => current_time( 'mysql' ) ) );
$item_other = (int) $wpdb->insert_id;

// These ids are compared against later. If an insert silently failed they would all
// be 0, and every "the right part was stored" assertion would pass against nothing.
ok( $rid > 0 && $item_lcd > 0 && $item_psu > 0 && $item_other > 0, 'fixtures really exist (no 0-id false passes)' );
ok( $item_psu !== $item_other, 'the two part ids are distinct' );
ok( current_user_can( 'manage_options' ), 'running as an admin, so handle_post() will act' );

echo "\n=== 1. The reason catalogue ===\n";
$reasons = AUN_SP_Requests::photo_reasons();
ok( isset( $reasons['wrong_part'] ), '"wrong part" is offered at all — the case a "clearer photo" never fixes' );
ok( count( $reasons ) >= 5, 'several distinct reasons (' . count( $reasons ) . ')' );
$en = AUN_SP_Requests::photo_reason_text( 'wrong_part', 'en' );
$bn = AUN_SP_Requests::photo_reason_text( 'wrong_part', 'bn' );
ok( '' !== $en['what'] && '' !== $en['how'], 'English: both what-is-wrong and what-to-do' );
ok( '' !== $bn['what'] && $bn['what'] !== $en['what'], 'Bangla is really translated, not the English string' );
ok( preg_match( '/[\x{0980}-\x{09FF}]/u', $bn['what'] ), 'Bangla text is actually in Bangla script' );
$none = AUN_SP_Requests::photo_reason_text( 'not_a_real_reason' );
ok( '' === $none['what'], 'an unknown key returns nothing rather than inventing a reason' );

echo "\n=== 2. Admin sends the photo back ===\n";
$reqs = new AUN_SP_Requests();
$m = new ReflectionMethod( 'AUN_SP_Requests', 'handle_post' );
$m->setAccessible( true );

$_POST = array(
	'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ),
	'photo_reason'       => 'wrong_part',
	'photo_item_id'      => $item_psu,
	'photo_note'         => 'This shows the lens, but we need the power board behind it.',
);
$notice = $m->invoke( $reqs, $rid );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid ) );
ok( 'waiting_customer' === $row->overall_status, 'request moved to Waiting on customer' );
ok( 'wrong_part' === $row->photo_reason,         'reason stored' );
ok( (int) $row->photo_item_id === $item_psu,     'the specific part is stored' );
ok( false !== strpos( (string) $row->photo_note, 'power board' ), 'the free-text note is stored' );
ok( false !== strpos( $notice, 'Wrong part' ),   'the admin is shown what the customer was told' );

$ev = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_ev WHERE request_id = %d AND type = 'photo_request' ORDER BY id DESC LIMIT 1", $rid ) );
ok( $ev && false !== strpos( $ev->message, 'Wrong part' ),   'audit log records the reason' );
ok( $ev && false !== strpos( $ev->message, 'Power board' ),  'audit log names the part' );
ok( $ev && (int) $ev->item_id === $item_psu,                 'audit log is filed against that part' );

echo "\n=== 3. A part id from a DIFFERENT customer is refused ===\n";
$_POST = array(
	'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ),
	'photo_reason'       => 'blurry',
	'photo_item_id'      => $item_other,   // belongs to rid_other
);
$m->invoke( $reqs, $rid );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid ) );
ok( 0 === (int) $row->photo_item_id, "another request's part is ignored, not shown to this customer" );

echo "\n=== 4. A junk reason is REFUSED, not guessed ===\n";
// v0.45 quietly substituted "unclear" for anything it did not recognise, so a
// malformed POST still sent a real customer a real SMS saying something we were
// never actually told. It now refuses outright and changes nothing.
$before = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid ) );
$_POST = array(
	'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ),
	'photo_reason'       => 'haha<script>',
	'photo_item_id'      => $item_psu,
);
$notice = $m->invoke( $reqs, $rid );
$after  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid ) );
ok( false !== strpos( $notice, 'notice-error' ), 'the admin is told to choose a reason' );
ok( $before->photo_reason === $after->photo_reason, 'the stored reason is untouched' );
ok( $before->overall_status === $after->overall_status, 'the status is untouched' );

echo "\n=== 5. What the customer's page is handed ===\n";
// Put it back into the real state we care about.
$_POST = array(
	'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ),
	'photo_reason'       => 'wrong_part',
	'photo_item_id'      => $item_psu,
	'photo_note'         => 'This shows the lens, but we need the power board behind it.',
);
$m->invoke( $reqs, $rid );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid ) );

$pa = new ReflectionMethod( 'AUN_SP_Tracking', 'photo_ask' );
$pa->setAccessible( true );
$ask = $pa->invoke( null, $row );
ok( is_array( $ask ), 'the page gets a reason object' );
ok( $ask && '' !== $ask['what'], 'it says what is wrong: "' . ( $ask['what'] ?? '' ) . '"' );
ok( $ask && '' !== $ask['how'],  'it says what to do about it' );
ok( $ask && (int) $ask['item_id'] === $item_psu, 'it names the part' );
ok( $ask && false !== strpos( $ask['note'], 'power board' ), 'the note reaches the customer verbatim' );

// An older request that was parked by hand has no reason: the page must fall back,
// not render an empty accusation box.
$legacy = clone $row;
$legacy->photo_reason = '';
ok( null === $pa->invoke( null, $legacy ), 'no reason recorded -> null, so the page keeps its old generic wording' );

echo "\n=== 6. Every reason still fits in ONE SMS ===\n";
// This plugin runs on a shared host for a shop that counts bytes; an SMS that spills
// past 160 characters is charged as two. The tracking page carries the full sentence,
// so the SMS only needs the short form -- but "only needs" has to be proved for every
// reason, with a REAL tracking link, not a short fake one.
// sms() returns the SAVED option, which on a bench that has been activated before
// still holds an older default. Clear it so this measures the wording we actually
// ship, then let seed() put it back.
delete_option( AUN_SP_Messages::OPT_SMS_PHOTO );
AUN_SP_Messages::seed();
$tpl = AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_PHOTO );
echo "    template: $tpl\n";
ok( false !== strpos( $tpl, '{reason}' ), 'the default template has a {reason} slot' );

$link  = AUN_SP_Messages::track_link( 'SP-2026-0917' );
$worst = 0;
$worst_key = '';
foreach ( array_keys( $reasons ) as $rk ) {
	$pr   = AUN_SP_Requests::photo_reason_text( $rk, 'en' );
	$vars = array( 'ref' => 'SP-2026-0917', 'track' => $link, 'reason' => $pr['sms'], 'note' => '' );
	$body = AUN_SP_Messages::fill( AUN_SP_Messages::drop_empty_brackets( $tpl, $vars ), $vars );
	$len  = strlen( $body );
	if ( $len > $worst ) { $worst = $len; $worst_key = $rk; }
	printf( "    %-11s %3d chars  %s\n", $rk, $len, $len <= 160 ? '1 part' : '*** 2 PARTS ***' );
	ok( false === strpos( $body, '{' ), "$rk: no placeholder left unfilled" );
	// One non-GSM character cuts a single SMS from 160 characters to 70.
	ok( 0 === preg_match( '/[^\x00-\x7F]/', $body ), "$rk: stays GSM-7 (Bangla here would cost 3x)" );
	ok( $len <= 160, "$rk: fits one SMS ($len chars)" );
}
echo "    worst case: $worst chars ($worst_key), link alone is " . strlen( $link ) . "\n";


// Headroom: the tracking-page URL is a SETTING, so it can grow. Re-run the worst
// reason against a link 20 characters longer than today's — it must still be one
// SMS, or the first person to tidy that setting quietly doubles the bill.
$long  = $link . str_repeat( 'x', 20 );
$pr    = AUN_SP_Requests::photo_reason_text( $worst_key, 'en' );
$vars  = array( 'ref' => 'SP-2026-0917', 'track' => $long, 'reason' => $pr['sms'], 'note' => '' );
$body  = AUN_SP_Messages::fill( AUN_SP_Messages::drop_empty_brackets( $tpl, $vars ), $vars );
ok( strlen( $body ) <= 160, 'still one SMS with a 20-char-longer tracking URL (' . strlen( $body ) . ' chars)' );
// The full sentence is what the customer READS; it just must not be what we pay for.
$full = AUN_SP_Requests::photo_reason_text( 'wrong_part', 'en' );
ok( strlen( $full['what'] ) > strlen( $full['sms'] ), 'the page gets the long form, the SMS the short one' );
ok( false !== stripos( $full['sms'], 'wrong part' ), 'the short form still says what was actually wrong' );

echo "\n=== 7. The customer sends a new photo ===\n";
// Simulate what ajax_reupload does around the atomic claim.
$target  = (int) $row->photo_item_id;
$claimed = (int) $wpdb->query( $wpdb->prepare(
	"UPDATE $t_req SET overall_status = 'in_progress', photo_reason = '', photo_note = '', photo_item_id = 0, updated_at = %s
	 WHERE id = %d AND overall_status = 'waiting_customer'",
	current_time( 'mysql' ), $rid
) );
ok( 1 === $claimed, 'the waiting state is claimed exactly once' );
$again = (int) $wpdb->query( $wpdb->prepare(
	"UPDATE $t_req SET overall_status = 'in_progress' WHERE id = %d AND overall_status = 'waiting_customer'",
	$rid
) );
ok( 0 === $again, 'a second upload finds nothing to claim (still one photo per ask)' );

$wpdb->insert( $t_att, array(
	'request_id' => $rid, 'item_id' => $target, 'kind' => 'reupload',
	'file_url' => 'https://example.test/wp-content/uploads/aun-spare-parts/new.webp',
	'created_at' => current_time( 'mysql' ),
) );
$att = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_att WHERE request_id = %d ORDER BY id DESC LIMIT 1", $rid ) );
ok( (int) $att->item_id === $item_psu, 'the new photo is filed against the part it was asked for' );

$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid ) );
ok( '' === (string) $row->photo_reason && 0 === (int) $row->photo_item_id, 'the ask is cleared' );
ok( null === $pa->invoke( null, $row ), 'so the page stops showing a reason that is no longer true' );

/* ── cleanup ── */
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_att WHERE request_id = %d", $rid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_ev  WHERE request_id = %d", $rid ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_item WHERE request_id IN (%d,%d)", $rid, $rid_other ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM $t_req WHERE id IN (%d,%d)", $rid, $rid_other ) );
$_POST = array();

printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
