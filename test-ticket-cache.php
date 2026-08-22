<?php
/**
 * The ticket-thread cache: it must speed things up WITHOUT ever showing one
 * customer another customer's conversation.
 *
 * Run:  php -c php.ini wp-cli.phar --path=site eval-file test-ticket-cache.php
 *
 * A support thread is the most private thing in the app — names, addresses,
 * complaints, sometimes invoice photos. A cache key that collides here is not a
 * performance bug, it is a disclosure. That is what this leans on.
 */

if ( ! class_exists( 'AUN_App_Tickets' ) ) {
	echo "AUN App API not active on this bench.\n";
	exit( 1 );
}

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

// thread_key() is private on purpose — reach it for the test only.
$m = new ReflectionMethod( 'AUN_App_Tickets', 'thread_key' );
$m->setAccessible( true );
$key = function ( $number, $emails ) use ( $m ) {
	return $m->invoke( null, $number, $emails );
};

echo "\n== Keys ==\n";

$a = $key( '12345', array( 'aminul@example.test' ) );
$b = $key( '12345', array( 'rahim@example.test' ) );
$c = $key( '99999', array( 'aminul@example.test' ) );

ok( $a !== $b, 'SAME ticket, DIFFERENT customer -> different cache entry' );
ok( $a !== $c, 'same customer, different ticket -> different cache entry' );
ok( $a === $key( '12345', array( 'aminul@example.test' ) ), 'the same pair is stable across calls' );
ok(
	$key( '12345', array( 'a@x.test', 'b@x.test' ) ) !== $key( '12345', array( 'a@x.test' ) ),
	'an extra address on the account is a different view, so a different entry'
);

echo "\n== Storing and forgetting ==\n";

set_transient( $a, array( 'ticket' => null, 'entries' => array( 'AMINUL SECRET' ) ), 60 );
set_transient( $b, array( 'ticket' => null, 'entries' => array( 'RAHIM SECRET' ) ), 60 );

$got = get_transient( $a );
ok( is_array( $got ) && 'AMINUL SECRET' === $got['entries'][0], 'a cached thread comes back' );

// The reply path calls this. It must clear ONLY the customer who replied.
AUN_App_Tickets::forget_thread( '12345', array( 'aminul@example.test' ) );

ok( false === get_transient( $a ), 'replying clears that customer\'s cached thread' );
$other = get_transient( $b );
ok(
	is_array( $other ) && 'RAHIM SECRET' === $other['entries'][0],
	'and leaves the OTHER customer\'s copy untouched'
);

// Tidy up.
delete_transient( $b );

echo "\n== TTL ==\n";
ok( AUN_App_Tickets::THREAD_CACHE_TTL > 0, 'the cache is actually enabled' );
ok(
	AUN_App_Tickets::THREAD_CACHE_TTL <= 60,
	'and stays under a minute — a staff reply must not sit hidden behind it'
);

echo "\n" . $GLOBALS['g_pass'] . ' passed, ' . $GLOBALS['g_fail'] . " failed\n";
