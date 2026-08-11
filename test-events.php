<?php
/**
 * First-party analytics: what gets stored, and what must never be.
 *
 * The privacy claims in the app's policy are only as true as clean_params(),
 * so that is what this leans on hardest.
 */
define( 'WPINC', 'wp-includes' );
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function aun_app_api_get_options() { return array( 'analytics_enabled' => 1 ); }
require_once 'C:/Users/Jobayer Hossain/Downloads/Claude session/aun-app-api/includes/class-aun-app-events.php';

$pass = 0; $fail = 0;
function ok( $c, $l ) { global $pass,$fail; if ($c){$pass++;echo "  PASS  $l\n";} else {$fail++;echo "  FAIL  $l\n";} }

$m = new ReflectionMethod( 'AUN_App_Events', 'clean_params' );
$m->setAccessible( true );
$clean = function ( $p ) use ( $m ) { return $m->invoke( null, $p ); };

echo "first-party analytics\n";

// ── The allow-list is the privacy policy in code ──
ok( in_array( 'quote_answered', AUN_App_Events::ALLOWED, true ),
	'a known event is allowed' );
ok( ! in_array( 'user_phone_seen', AUN_App_Events::ALLOWED, true ),
	'an unknown event name is not silently accepted' );
ok( count( AUN_App_Events::ALLOWED ) === count( array_unique( AUN_App_Events::ALLOWED ) ),
	'no duplicate event names' );

// ── The parameter scrubber ──
$out = $clean( array( 'service' => 'finder', 'step' => 3, 'ok' => true ) );
ok( $out === array( 'service' => 'finder', 'step' => 3, 'ok' => 1 ),
	'categories, counts and booleans are kept' );

ok( array() === $clean( array( 'phone' => '01711561441' ) ),
	'a bare phone number is DROPPED, never stored' );
ok( array() === $clean( array( 'p' => '+8801711561441' ) ),
	'the +88 form is dropped too' );
ok( array() === $clean( array( 'who' => 'someone@example.com' ) ),
	'an email address is dropped' );
ok( array() === $clean( array( 'note' => str_repeat( 'x', 60 ) ) ),
	'a long string is prose, not a category, and is dropped' );

$many = array();
for ( $i = 0; $i < 20; $i++ ) { $many[ "k$i" ] = 'v'; }
ok( count( $clean( $many ) ) <= 8,
	'a runaway parameter list is capped' );

ok( array() === $clean( 'not an array' ) && array() === $clean( null ),
	'a malformed params blob degrades to empty' );

// A reference like SP-2026-0042 is short and has no phone/@ — it would pass the
// scrubber, which is exactly why the APP must never send one. Documented here
// so the limitation is visible rather than assumed away.
ok( array( 'ref' => 'SP-2026-0042' ) === $clean( array( 'ref' => 'SP-2026-0042' ) ),
	'NOTE: a short ref would pass — the app is the first lock, not this' );

ok( AUN_App_Events::RETENTION_DAYS <= 365 && AUN_App_Events::RETENTION_DAYS >= 30,
	'retention is bounded (analytics kept for ever is a liability)' );
ok( AUN_App_Events::MAX_BATCH <= 100,
	'one request cannot dump an unbounded batch' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
