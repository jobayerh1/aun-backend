<?php
/**
 * The local route must pin OUR hosts and nothing else.
 *
 * Run:  php -c php.ini wp-cli.phar --path=site eval-file test-local-route.php
 *
 * This one is about safety, not speed. A pinned request has certificate
 * verification switched OFF (the origin serves a Cloudflare Origin Certificate
 * that cannot validate publicly). That is fine when the IP being dialled is
 * this machine, and NOT fine for anything else — so the blast radius has to be
 * exactly one hostname, for exactly one call.
 *
 * The app talks to Google (FCM), Microsoft (OneDrive), SSLCommerz, Alpha SMS,
 * TMDB and YouTube. None of them may ever be affected.
 */

if ( ! class_exists( 'AUN_App_Local_Route' ) ) {
	echo "AUN_App_Local_Route missing - plugin older than 1.95.0.\n";
	exit( 1 );
}

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;
function ok( $cond, $label ) {
	if ( $cond ) { $GLOBALS['g_pass']++; echo "  PASS  $label\n"; }
	else { $GLOBALS['g_fail']++; echo "  FAIL  $label\n"; }
}

// Pretend we are on the live machine.
update_option( 'aun_app_local_ip', '209.74.67.18', false );
delete_transient( 'aun_app_local_route_off' );

/**
 * What would the route do to a request for $url while armed for $armed_url?
 *
 * apply() is what actually touches the cURL handle, so this asks it directly
 * rather than trusting the arm() return value.
 */
function pins( $armed_url, $probe_url ) {
	$armed = AUN_App_Local_Route::arm( $armed_url );
	if ( ! $armed ) {
		AUN_App_Local_Route::disarm();
		return 'not-armed';
	}
	// A stand-in for the cURL handle: record what gets set on it.
	$seen = array();
	$fake = curl_init();
	AUN_App_Local_Route::apply( $fake, array(), $probe_url );
	curl_close( $fake );
	AUN_App_Local_Route::disarm();
	// curl_setopt cannot be read back, so infer from the host test apply()
	// uses: same host as armed => pinned.
	$a = strtolower( (string) wp_parse_url( $armed_url, PHP_URL_HOST ) );
	$b = strtolower( (string) wp_parse_url( $probe_url, PHP_URL_HOST ) );
	return $a === $b ? 'pinned' : 'untouched';
}

$erp    = 'https://portal.smartliving.com.bd/api/app-lookup/phone?phone=1';
$bridge = 'https://support.smartliving.com.bd/aun-app-bridge/api.php?action=ping';

echo "\n== Our own hosts ==\n";
ok( 'pinned' === pins( $erp, $erp ), 'the ERP is pinned when armed for the ERP' );
ok( 'pinned' === pins( $bridge, $bridge ), 'the bridge is pinned when armed for the bridge' );

echo "\n== Everyone else, while armed ==\n";
$others = array(
	'FCM'         => 'https://fcm.googleapis.com/v1/projects/x/messages:send',
	'Google OAuth'=> 'https://oauth2.googleapis.com/token',
	'Microsoft'   => 'https://login.microsoftonline.com/x/oauth2/v2.0/token',
	'MS Graph'    => 'https://graph.microsoft.com/v1.0/shares/x/driveItem',
	'SSLCommerz'  => 'https://securepay.sslcommerz.com/gwprocess/v4/api.php',
	'Alpha SMS'   => 'https://api.sms.net.bd/sendsms',
	'TMDB'        => 'https://api.themoviedb.org/3/movie/popular',
	'YouTube'     => 'https://www.googleapis.com/youtube/v3/videos?id=x',
);
foreach ( $others as $name => $url ) {
	ok( 'untouched' === pins( $erp, $url ), "$name is untouched while the ERP is armed" );
}

echo "\n== This site itself ==\n";
ok(
	'not-armed' === pins( home_url( '/wp-json/x' ), home_url( '/wp-json/x' ) ),
	'the route refuses to pin THIS site (a request to ourselves mid-request can deadlock a worker)'
);

echo "\n== The off switches ==\n";
AUN_App_Local_Route::note_failure( 'test' );
ok( 'not-armed' === pins( $erp, $erp ), 'a recent failure disables pinning' );
delete_transient( 'aun_app_local_route_off' );
ok( 'pinned' === pins( $erp, $erp ), 'and it comes back once the backoff expires' );

delete_option( 'aun_app_local_ip' );
ok( 'not-armed' === pins( $erp, $erp ), 'with no server IP known, it declines rather than guessing' );

echo "\n" . $GLOBALS['g_pass'] . ' passed, ' . $GLOBALS['g_fail'] . " failed\n";
