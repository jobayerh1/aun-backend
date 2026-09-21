<?php
/**
 * AUN Spare Parts v0.46.0 — the review pass over the photo-reason feature.
 *
 * Run:  cd ~/wp-local && php -c php.ini wp-cli.phar --path=site eval-file <this file> --skip-themes
 *
 * Six defects were found reading the v0.45.0 code back. Each one below is the test
 * that would have caught it:
 *
 *   1. The customer's progress history printed the INTERNAL note verbatim, in
 *      English, including the admin's dropdown label and their private note.
 *   2. The reason dropdown pre-selected "Wrong part photographed", so one stray
 *      click accused the customer of something and sent a real SMS.
 *   3. "Waiting on customer" is sticky, so a mistaken ask froze the request for ever.
 *   4. A one-part request never got the side-by-side comparison.
 *   5. A targeted re-upload rendered the same image twice on the card.
 *   6. The re-upload history line called the customer "Customer", in the third person.
 */

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

if ( ! class_exists( 'AUN_SP_Requests' ) ) { echo "plugin not active\n"; exit( 1 ); }

global $wpdb;
wp_set_current_user( 1 );
if ( ! current_user_can( 'manage_options' ) ) {
	$u = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	if ( $u ) { wp_set_current_user( $u[0]->ID ); }
}
ok( current_user_can( 'manage_options' ), 'running as an admin' );

$t_req  = AUN_SP_Install::table( 'requests' );
$t_item = AUN_SP_Install::table( 'request_items' );
$t_ev   = AUN_SP_Install::table( 'events' );

$reqs = new AUN_SP_Requests();
$hp   = new ReflectionMethod( 'AUN_SP_Requests', 'handle_post' );
$hp->setAccessible( true );

function mk_request( $nparts ) {
	global $wpdb;
	// wp eval-file runs this file inside a FUNCTION, so the file-level $t_req /
	// $t_item are NOT globals. A `global $t_req` here binds to nothing and every
	// insert silently goes to table '' -- which reads back as "no rows" and turns
	// half the suite into false passes. Recompute them.
	$t_req  = AUN_SP_Install::table( 'requests' );
	$t_item = AUN_SP_Install::table( 'request_items' );
	$ref = 'SP-RV' . wp_rand( 10000, 99999 );
	$wpdb->insert( $t_req, array(
		'ref' => $ref, 'model' => 'AUN M18', 'customer_name' => 'Review Test',
		'phone_current' => '01799000888', 'overall_status' => 'in_progress',
		'created_at' => current_time( 'mysql' ),
	) );
	$rid = (int) $wpdb->insert_id;
	$ids = array();
	for ( $i = 0; $i < $nparts; $i++ ) {
		$wpdb->insert( $t_item, array(
			'request_id' => $rid, 'part_type' => 'p' . $i, 'part_label' => 'Part ' . ( $i + 1 ),
			'qty' => 1, 'line_status' => 'pending', 'created_at' => current_time( 'mysql' ),
		) );
		$ids[] = (int) $wpdb->insert_id;
	}
	if ( ! $rid || count( $ids ) !== $nparts || in_array( 0, $ids, true ) ) {
		echo "  !! fixture insert failed: " . $wpdb->last_error . "
";
		ok( false, 'fixture request created' );
	}
	return array( $rid, $ids, $ref );
}
$made = array();

echo "\n=== 1. The customer's history must not leak our internal note ===\n";
list( $rid, $ids ) = mk_request( 2 );
$made[] = $rid;
$secret = 'INTERNAL-ONLY-SUPPLIER-NOTE';
$_POST  = array(
	'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ),
	'photo_ask'          => '1',
	'photo_reason'       => 'wrong_part',
	'photo_item_id'      => $ids[1],
	'photo_note'         => $secret,
);
$hp->invoke( $reqs, $rid );

$ev = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_ev WHERE request_id = %d AND type='photo_request' ORDER BY id DESC LIMIT 1", $rid ) );
ok( $ev && false !== strpos( $ev->message, $secret ), 'the ADMIN log still keeps the full detail' );
ok( $ev && 'wrong_part' === (string) $ev->new_value, 'the reason KEY is stored on the event (so it can be translated later)' );

// Build the timeline the way the tracking endpoint does.
$mk = new ReflectionMethod( 'AUN_SP_Tracking', 'photo_ask' );  // sanity: class loads
ok( in_array( 'photo_request', AUN_SP_Requests::PUBLIC_EVENTS, true ), 'photo_request IS customer-visible (so wording matters)' );
ok( ! in_array( 'photo_cancel', AUN_SP_Requests::PUBLIC_EVENTS, true ), 'photo_cancel is NOT customer-visible' );

$pr_en = AUN_SP_Requests::photo_reason_text( (string) $ev->new_value, 'en' );
$line_en = AUN_SP_I18N::msg( 'tl_photo_ask', array( 'reason' => $pr_en['what'] ), 'en' );
$pr_bn = AUN_SP_Requests::photo_reason_text( (string) $ev->new_value, 'bn' );
$line_bn = AUN_SP_I18N::msg( 'tl_photo_ask', array( 'reason' => $pr_bn['what'] ), 'bn' );
echo "    EN: $line_en\n    BN: $line_bn\n";
ok( false === strpos( $line_en, $secret ), 'the customer line does NOT contain our private note' );
ok( false === strpos( $line_en, 'Wrong part photographed' ), 'nor the internal dropdown label' );
ok( false === strpos( $line_bn, $secret ), 'same in Bangla' );
ok( preg_match( '/[\x{0980}-\x{09FF}]/u', $line_bn ), 'the Bangla line is actually Bangla' );
// An ask recorded before this version has no key: it must still say something sane.
$plain = AUN_SP_I18N::msg( 'tl_photo_ask_plain', array(), 'en' );
ok( '' !== $plain && false === strpos( $plain, '{' ), 'a pre-0.46 ask falls back to a clean sentence' );
$sent = AUN_SP_I18N::msg( 'tl_photo_sent', array(), 'en' );
ok( false === stripos( $sent, 'customer' ), 're-upload line speaks TO the customer, not about them ("' . $sent . '")' );

echo "\n=== 2. No reason chosen -> nothing is sent ===\n";
list( $rid2, $ids2 ) = mk_request( 2 );
$made[] = $rid2;
$_POST = array( 'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ), 'photo_ask' => '1', 'photo_reason' => '' );
$notice = $hp->invoke( $reqs, $rid2 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid2 ) );
ok( false !== strpos( $notice, 'notice-error' ), 'the admin is told to choose a reason' );
ok( 'in_progress' === $row->overall_status, 'the request was NOT moved to waiting' );
ok( '' === (string) $row->photo_reason, 'no reason was invented' );
$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_ev WHERE request_id=%d AND type='photo_request'", $rid2 ) );
ok( 0 === $n, 'nothing was logged, so no SMS went out' );

echo "\n=== 3. A mistaken ask can be cancelled ===\n";
$_POST = array( 'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ), 'photo_ask' => '1', 'photo_reason' => 'blurry' );
$hp->invoke( $reqs, $rid2 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid2 ) );
ok( 'waiting_customer' === $row->overall_status, 'asked: now waiting on the customer' );
// Sticky status: prove it does NOT free itself, which is why Cancel has to exist.
$derived = AUN_SP_Requests::compute_overall( array( 'pending', 'pending' ), 'waiting_customer', false );
ok( 'waiting_customer' === $derived, 'waiting_customer is sticky — it never clears itself' );

$_POST = array( 'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ), 'photo_cancel' => '1' );
$notice = $hp->invoke( $reqs, $rid2 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid2 ) );
ok( 'in_progress' === $row->overall_status, 'cancel puts it back to In progress' );
ok( '' === (string) $row->photo_reason && 0 === (int) $row->photo_item_id, 'cancel clears the ask' );
ok( false !== strpos( $notice, 'No SMS' ), 'the admin is told no SMS was sent' );
$c = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_ev WHERE request_id=%d AND type='photo_cancel'", $rid2 ) );
ok( 1 === $c, 'the cancellation is recorded for the admin' );

// Cancel must not resurrect a request that is finished or rejected.
$wpdb->update( $t_req, array( 'overall_status' => 'rejected' ), array( 'id' => $rid2 ) );
$_POST = array( 'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ), 'photo_cancel' => '1' );
$hp->invoke( $reqs, $rid2 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid2 ) );
ok( 'rejected' === $row->overall_status, 'cancel does nothing to a request that is not waiting' );

echo "\n=== 4. A one-part request still gets the comparison ===\n";
list( $rid3, $ids3 ) = mk_request( 1 );
$made[] = $rid3;
$_POST = array(
	'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ),
	'photo_ask' => '1', 'photo_reason' => 'blurry',
	// No photo_item_id at all: with one part the form doesn't render the dropdown.
);
$hp->invoke( $reqs, $rid3 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid3 ) );
ok( (int) $row->photo_item_id === $ids3[0], 'the only part is targeted automatically' );

// With several parts and no choice, it must NOT guess.
list( $rid4, $ids4 ) = mk_request( 3 );
$made[] = $rid4;
$_POST = array( 'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ), 'photo_ask' => '1', 'photo_reason' => 'blurry' );
$hp->invoke( $reqs, $rid4 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid4 ) );
ok( 0 === (int) $row->photo_item_id, 'with 3 parts and no choice, no part is guessed' );

echo "\n=== 5. The re-sent photo is not shown twice ===\n";
$rp = new ReflectionMethod( 'AUN_SP_Tracking', 'resent_photo' );
$rp->setAccessible( true );
$url   = 'https://x.test/wp-content/uploads/aun-spare-parts/new.webp';
$parts_with    = array( array( 'photo' => $url ), array( 'photo' => '' ) );
$parts_without = array( array( 'photo' => 'https://x.test/other.webp' ) );
ok( '' === $rp->invoke( null, array( 'resent' => $url ), $parts_with ), 'hidden when the part already shows it' );
ok( $url === $rp->invoke( null, array( 'resent' => $url ), $parts_without ), 'still shown when it is not on a part' );
ok( $url === $rp->invoke( null, array( 0 => $url ), $parts_without ), 'legacy untargeted re-upload still shown' );
ok( '' === $rp->invoke( null, array(), $parts_without ), 'nothing to show -> empty string' );

echo "\n=== 6. \"Right part, wrong model\" ===\n";
// A customer asks for a remote and photographs a remote -- just not HIS remote.
// "Wrong part photographed" reads as "a different part", so he looks at his photo,
// sees a remote, and sends the same one back. This reason exists for that case.
$rs = AUN_SP_Requests::photo_reasons();
ok( isset( $rs['model_mismatch'] ), 'the reason exists' );
$mm_en = AUN_SP_Requests::photo_reason_text( 'model_mismatch', 'en' );
$mm_bn = AUN_SP_Requests::photo_reason_text( 'model_mismatch', 'bn' );
echo "    what: " . $mm_en['what'] . "\n    how : " . $mm_en['how'] . "\n";
ok( '' !== $mm_en['what'] && '' !== $mm_en['how'] && '' !== $mm_en['sms'], 'all three forms present' );
ok( preg_match( '/[\x{0980}-\x{09FF}]/u', $mm_bn['how'] ), 'Bangla instructions are really Bangla' );

// THE DOMAIN RULE (Jobayer, 2026-09-20): a spare part CANNOT be identified from the
// projector's serial label. Every proof note in the catalogue asks for a number
// printed on the PART itself. Copy that sends the customer to the projector's label
// wastes a round trip, so assert it can never drift back.
ok( false !== stripos( $mm_en['how'], 'part' ), 'the fix asks for the PART' );
ok( false === stripos( $mm_en['how'], 'serial label' ), 'it does NOT send them to the projector serial label' );
foreach ( array_keys( $rs ) as $rk ) {
	$h = AUN_SP_Requests::photo_reason_text( $rk, 'en' );
	ok( false === stripos( $h['how'], "projector's label" ) && false === stripos( $h['how'], 'serial label' ),
		"$rk: never points the customer at the projector label" );
}

echo "\n=== 7. Every reason must be true for EVERY part ===\n";
// The catalogue runs from an LCD ribbon with a serial etched on it to a remote
// control with no number anywhere. Wording that assumes a label, a printed number,
// or an example photo is simply false for some parts -- and the customer it is false
// for is the one who cannot act on it.
//
//   - a remote has NO label and NO printed number
//   - 'ref_image' is optional per part, so there may be no example photo to point at
//   - the note is optional, so "read our note below" can point at nothing
//
// 'serial' is exempt: the admin only picks it for a part that HAS a printed number.
$all = AUN_SP_Requests::photo_reasons();
foreach ( array_keys( $all ) as $rk ) {
	$txt = AUN_SP_Requests::photo_reason_text( $rk, 'en' );
	$both = $txt['what'] . ' ' . $txt['how'];
	if ( 'serial' !== $rk ) {
		ok( false === stripos( $both, 'label' ), "$rk: assumes no label (a remote has none)" );
		ok( false === stripos( $both, 'printed on' ) && false === stripos( $both, 'number printed' ),
			"$rk: assumes no printed number" );
	}
	ok( false === stripos( $both, 'example photo' ), "$rk: does not promise an example photo that may not exist" );
	if ( 'other' !== $rk ) {
		ok( false === stripos( $both, 'note below' ), "$rk: does not promise a note that may not exist" );
	}
	ok( '' !== trim( $txt['how'] ), "$rk: actually tells them what to do" );
}

echo "\n=== 8. \"Something else\" without a note is refused ===\n";
list( $rid5, $ids5 ) = mk_request( 1 );
$made[] = $rid5;
$_POST = array( 'aun_sp_photo_nonce' => wp_create_nonce( 'aun_sp_photo' ), 'photo_ask' => '1',
	'photo_reason' => 'other', 'photo_note' => '   ' );
$notice = $hp->invoke( $reqs, $rid5 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid5 ) );
ok( false !== strpos( $notice, 'notice-error' ), 'refused: "other" says only what the note says' );
ok( 'in_progress' === $row->overall_status, 'no SMS, status untouched' );

$_POST['photo_note'] = 'The photo is of the box, not the remote inside it.';
$notice = $hp->invoke( $reqs, $rid5 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $rid5 ) );
ok( 'waiting_customer' === $row->overall_status, 'accepted once a note is written' );

/* ── cleanup ── */
foreach ( $made as $id ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t_ev   WHERE request_id = %d", $id ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t_item WHERE request_id = %d", $id ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM $t_req  WHERE id = %d", $id ) );
}
$_POST = array();

printf( "\n%d passed, %d failed\n", $GLOBALS['g_pass'], $GLOBALS['g_fail'] );
exit( $GLOBALS['g_fail'] ? 1 : 0 );
