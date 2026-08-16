<?php
/**
 * The three repair SMS templates.
 *
 * Run:  php -c php.ini wp-cli.phar --path=site eval-file <this file>
 *
 * These were hardcoded in PHP until 1.84.0. The point of moving them into
 * options is that the owner can change a word without a developer, so what
 * matters here is that the option is genuinely honoured — including the
 * awkward cases: an emptied box, a missing placeholder, Bangla text.
 */

if ( ! class_exists( 'AUN_App_Services' ) ) {
	echo "AUN App API not active on this bench.\n";
	exit( 1 );
}

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;

function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

$opts_key = AUN_APP_API_OPTION;
$original = get_option( $opts_key );

function set_tpl( $which, $text ) {
	$o = aun_app_api_get_options();
	$o[ 'repair_sms_' . $which ] = $text;
	update_option( AUN_APP_API_OPTION, $o );
	wp_cache_delete( AUN_APP_API_OPTION, 'options' );
}

echo "\n=== repair SMS templates ===\n";

// ── The admin's text is what goes out ──
set_tpl( 'approved', 'AUN: repair {ref} approved for your {model}. {note}' );
$out = AUN_App_Services::repair_sms( 'approved', array(
	'ref'   => 'RP-2026-0042',
	'model' => 'AUN A45 Pro',
	'note'  => 'Please pack the remote too.',
) );
ok( false !== strpos( $out, 'RP-2026-0042' ), '{ref} is filled' );
ok( false !== strpos( $out, 'AUN A45 Pro' ), '{model} is filled' );
ok( false !== strpos( $out, 'Please pack the remote too.' ), '{note} is filled' );
ok( false === strpos( $out, '{' ), 'no placeholder is left showing' );

// ── The awkward one: an admin who writes no note ──
$out = AUN_App_Services::repair_sms( 'approved', array(
	'ref'   => 'RP-2026-0042',
	'model' => 'AUN A45 Pro',
	'note'  => '',
) );
ok( false === strpos( $out, '  ' ), 'an empty {note} leaves no double space' );
ok( substr( $out, -1 ) !== ' ', 'and no trailing space' );

// ── Emptying the box means "send nothing" ──
set_tpl( 'rejected', '' );
ok( '' === AUN_App_Services::repair_sms( 'rejected', array( 'ref' => 'RP-1' ) ),
	'an emptied template sends NOTHING rather than falling back to a default' );

// ⚠️ The caller must honour that. A blank body handed to the SMS gateway is a
// wasted send and, on some gateways, a billed one.
$blank = AUN_App_Services::repair_sms( 'rejected', array( 'ref' => 'RP-1' ) );
ok( '' === trim( $blank ), 'the blank result is genuinely empty, not whitespace' );

// ── Bangla ──
set_tpl( 'received', 'AUN: আপনার {model} মেরামতের অনুরোধ {ref} পেয়েছি। এখনই পাঠাবেন না।' );
$bn = AUN_App_Services::repair_sms( 'received', array(
	'ref'   => 'RP-2026-0043',
	'model' => 'AUN U002 Pro',
) );
ok( false !== strpos( $bn, 'RP-2026-0043' ), 'placeholders work inside Bangla text' );
ok( false !== strpos( $bn, 'মেরামতের' ), 'Bangla characters survive intact' );

// ── A template that uses no placeholders at all is still valid ──
set_tpl( 'received', 'AUN: we have your repair request. Please wait for our SMS.' );
ok( 'AUN: we have your repair request. Please wait for our SMS.'
	=== AUN_App_Services::repair_sms( 'received', array( 'ref' => 'RP-9' ) ),
	'a template with no placeholders is passed through unchanged' );

// ── Refusing a NEW request and cancelling an APPROVED one must not share
//    words. By the time we cancel, the customer has been told to ship and may
//    be holding a courier receipt; "we could not accept your request" would
//    read as if they had done something wrong. ──
set_tpl( 'rejected',  'AUN: we could not accept repair request {ref}. {note}' );
set_tpl( 'cancelled', 'AUN: we are very sorry - we have had to cancel repair {ref} after approving it. {note}' );

$refused   = AUN_App_Services::repair_sms( 'rejected',  array( 'ref' => 'RP-1', 'note' => '' ) );
$cancelled = AUN_App_Services::repair_sms( 'cancelled', array( 'ref' => 'RP-1', 'note' => '' ) );

ok( $refused !== $cancelled, 'refusing and cancelling produce DIFFERENT messages' );
ok( false !== strpos( $cancelled, 'sorry' ), 'the cancellation apologises' );
ok( false === strpos( $cancelled, 'could not accept' ),
    'the cancellation does not blame the customer for a request we already approved' );

// ── An unknown event name must not explode ──
ok( '' === AUN_App_Services::repair_sms( 'nonsense', array() ),
	'an unknown event returns empty instead of erroring' );

/* ── restore ── */
update_option( $opts_key, $original );

echo "\n{$GLOBALS['g_pass']} passed, {$GLOBALS['g_fail']} failed\n";
exit( $GLOBALS['g_fail'] > 0 ? 1 : 0 );
