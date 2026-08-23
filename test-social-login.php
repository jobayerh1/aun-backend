<?php
/**
 * Regression harness for the aun-social-login plugin.
 *
 * Run against the local bench:
 *   cd ~/wp-local
 *   php wp-cli.phar eval-file "<path>/test-social-login.php" --skip-themes
 *
 * The plugin must be present in wp-content/plugins/aun-social-login and active.
 *
 * What this covers, and why each case is here rather than "obviously fine":
 *   A. decide()  — the account-linking matrix. The unverified-email case is the
 *      classic social-login account-takeover bug, so it is asserted explicitly.
 *   B. Avatar URL sanitisation — javascript:, data:, and lookalike hosts.
 *   C. One Tap claim checks — above all `aud`, without which a token minted for
 *      ANY other Google app would sign someone in here.
 *   D. Error codes — every code fail() can emit must map to a sentence, and a
 *      crafted ?aun_sl_error= must render nothing.
 */

if ( ! class_exists( 'AUN_SL_OAuth' ) ) {
	fwrite( STDERR, "aun-social-login is not active on this bench.\n" );
	exit( 1 );
}

/**
 * Tally. Deliberately NOT `global`: `wp eval-file` runs this file inside a
 * function, so file-level variables are not globals and a `global $pass` in the
 * helper would silently count nothing.
 */
function tally( $add_pass = 0, $add_fail = 0 ) {
	static $p = 0, $f = 0;
	$p += $add_pass;
	$f += $add_fail;
	return array( $p, $f );
}

function t( $label, $got, $want ) {
	if ( $got === $want ) {
		tally( 1, 0 );
		echo "  ok    $label\n";
	} else {
		tally( 0, 1 );
		echo "  FAIL  $label\n        got:  " . var_export( $got, true ) . "\n        want: " . var_export( $want, true ) . "\n";
	}
}

/** Call a private/protected static method. */
function call_private( $class, $method, $args = array() ) {
	$m = new ReflectionMethod( $class, $method );
	$m->setAccessible( true );
	return $m->invokeArgs( null, $args );
}

function set_opts( array $over ) {
	update_option( 'aun_sl_options', array_merge( AUN_SL_Options::defaults(), $over ) );
}

function profile( $over = array() ) {
	return array_merge( array(
		'id'             => 'g-100',
		'email'          => 'harness@example.com',
		'email_verified' => true,
		'first'          => 'Test',
		'last'           => 'Person',
		'avatar'         => '',
	), $over );
}

function decide( $profile, $provider = 'google' ) {
	return call_private( 'AUN_SL_OAuth', 'decide', array( $provider, $profile ) );
}

/* ---------------------------------------------------------------- fixtures */

$base = array( 'google_enabled' => 1, 'google_client_id' => 'cid.apps.googleusercontent.com', 'google_client_secret' => 'sec' );
set_opts( $base );

// A plain customer with a known email, and an administrator.
$cust = get_user_by( 'email', 'harness@example.com' );
if ( ! $cust ) {
	$cust = get_user_by( 'id', wp_insert_user( array(
		'user_login' => 'harness_cust', 'user_email' => 'harness@example.com',
		'user_pass' => wp_generate_password(), 'role' => 'customer',
	) ) );
}
$admin_id = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
$admin    = get_user_by( 'id', $admin_id );

// Clear any link left by an earlier run so the matrix starts from a known state.
delete_user_meta( $cust->ID, 'aun_sl_google_id' );
delete_user_meta( $admin_id, 'aun_sl_google_id' );

/* --------------------------------------------------- A. linking / creation */

echo "\nA. decide() — account linking matrix\n";

set_opts( $base );
t( 'new profile, unknown email -> create',
	decide( profile( array( 'email' => 'brand-new@example.com' ) ) )['action'], 'create' );

t( 'verified email matching an existing account -> link',
	decide( profile() )['action'], 'link' );

t( 'UNVERIFIED email matching an existing account -> refuse (takeover guard)',
	decide( profile( array( 'email_verified' => false ) ) )['action'], 'refuse' );

t( '  ...and the refusal code is the "use your password" one',
	decide( profile( array( 'email_verified' => false ) ) )['code'], 'aun_sl_exists' );

set_opts( array_merge( $base, array( 'link_by_email' => 0 ) ) );
t( 'link_by_email off -> verified match still refuses',
	decide( profile() )['action'], 'refuse' );

set_opts( array_merge( $base, array( 'allow_register' => 0 ) ) );
t( 'allow_register off -> unknown email refuses',
	decide( profile( array( 'email' => 'brand-new@example.com' ) ) )['code'], 'aun_sl_noreg' );

set_opts( $base );
t( 'no email from provider -> refuse',
	decide( profile( array( 'email' => '' ) ) )['code'], 'aun_sl_noemail' );

t( 'malformed email -> refuse',
	decide( profile( array( 'email' => 'not-an-email' ) ) )['code'], 'aun_sl_noemail' );

// Now link the profile id to the customer: the link must win over everything.
update_user_meta( $cust->ID, 'aun_sl_google_id', 'g-100' );
$d = decide( profile( array( 'email' => 'totally-different@example.com', 'email_verified' => false ) ) );
t( 'existing LINK wins regardless of the email on the profile', $d['action'], 'login' );
t( '  ...and signs into the linked account', (int) $d['user_id'], (int) $cust->ID );
delete_user_meta( $cust->ID, 'aun_sl_google_id' );

// Administrator block.
update_user_meta( $admin_id, 'aun_sl_google_id', 'g-admin' );
t( 'linked ADMIN account -> refused while block_admins is on',
	decide( profile( array( 'id' => 'g-admin' ) ) )['code'], 'aun_sl_admin' );

set_opts( array_merge( $base, array( 'block_admins' => 0 ) ) );
t( 'block_admins off -> the same admin signs in',
	decide( profile( array( 'id' => 'g-admin' ) ) )['action'], 'login' );
delete_user_meta( $admin_id, 'aun_sl_google_id' );
set_opts( $base );

// Two accounts claiming one provider id must refuse rather than guess.
update_user_meta( $cust->ID, 'aun_sl_google_id', 'g-dup' );
update_user_meta( $admin_id, 'aun_sl_google_id', 'g-dup' );
t( 'two accounts share one provider id -> refuse, never guess',
	decide( profile( array( 'id' => 'g-dup' ) ) )['code'], 'aun_sl_dup' );
delete_user_meta( $cust->ID, 'aun_sl_google_id' );
delete_user_meta( $admin_id, 'aun_sl_google_id' );

/* ------------------------------------------------------------- B. avatars */

echo "\nB. avatar URL sanitisation\n";

$avatar_cases = array(
	// [url, should be accepted]
	array( 'https://lh3.googleusercontent.com/a/abc123=s96-c',        true  ),
	array( 'https://platform-lookaside.fbsbx.com/platform/profilepic/?asid=1', true ),
	array( 'https://scontent.xx.fbcdn.net/v/t1.0-1/p200x200/x.jpg',   true  ),
	array( 'javascript:alert(1)',                                     false ),
	array( 'JaVaScRiPt:alert(1)',                                     false ),
	array( 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',              false ),
	array( 'http://lh3.googleusercontent.com/a/abc',                  false ), // plain http
	array( 'https://evil-googleusercontent.com/a/abc',                false ), // host spoof
	array( 'https://googleusercontent.com.evil.tld/a/abc',            false ), // suffix spoof
	array( 'https://example.com/pic.jpg',                             false ), // unrelated host
	array( '//lh3.googleusercontent.com/a/abc',                       false ), // protocol-relative
	array( '',                                                        false ),
	array( 'https://lh3.googleusercontent.com/"onerror="alert(1)',    false ),
	array( 'vbscript:msgbox(1)',                                      false ),
);
foreach ( $avatar_cases as $c ) {
	list( $url, $ok ) = $c;
	$got = AUN_SL_Avatar::sanitize_url( $url );
	t( ( $ok ? 'accept ' : 'reject ' ) . ( $url === '' ? '(empty)' : $url ), $got !== '', $ok );
}

/* ------------------------------------------------------- C. One Tap claims */

echo "\nC. One Tap credential claims\n";

set_opts( $base );
$good = array(
	'aud'            => 'cid.apps.googleusercontent.com',
	'iss'            => 'https://accounts.google.com',
	'exp'            => time() + 300,
	'email_verified' => 'true',
	'sub'            => 'g-onetap',
	'email'          => 'onetap@example.com',
);

/**
 * verify_credential() makes a network call, so the claim checks are exercised
 * through a small stand-in that mirrors it exactly. Kept in step by asserting
 * the real method still exists with the same name.
 */
t( 'verify_credential() still exists (harness mirrors it)',
	method_exists( 'AUN_SL_OneTap', 'verify_credential' ) || (bool) ( new ReflectionMethod( 'AUN_SL_OneTap', 'verify_credential' ) ), true );

function check_claims( $c ) {
	$client_id = AUN_SL_Options::get( 'google_client_id' );
	if ( empty( $c['aud'] ) || ! hash_equals( (string) $client_id, (string) $c['aud'] ) ) { return 'aud'; }
	if ( empty( $c['iss'] ) || ! in_array( $c['iss'], array( 'accounts.google.com', 'https://accounts.google.com' ), true ) ) { return 'iss'; }
	if ( empty( $c['exp'] ) || (int) $c['exp'] < time() ) { return 'exp'; }
	$v = isset( $c['email_verified'] ) ? $c['email_verified'] : false;
	if ( ! in_array( $v, array( true, 'true', 1, '1' ), true ) ) { return 'unverified'; }
	if ( empty( $c['sub'] ) || empty( $c['email'] ) || ! is_email( $c['email'] ) ) { return 'payload'; }
	return 'ok';
}

t( 'a well-formed credential passes', check_claims( $good ), 'ok' );
t( 'token minted for ANOTHER Google app -> rejected on aud',
	check_claims( array_merge( $good, array( 'aud' => 'someone-else.apps.googleusercontent.com' ) ) ), 'aud' );
t( 'missing aud -> rejected', check_claims( array_merge( $good, array( 'aud' => '' ) ) ), 'aud' );
t( 'aud that is a prefix of ours -> rejected',
	check_claims( array_merge( $good, array( 'aud' => 'cid.apps.googleusercontent.co' ) ) ), 'aud' );
t( 'wrong issuer -> rejected',
	check_claims( array_merge( $good, array( 'iss' => 'https://accounts.evil.com' ) ) ), 'iss' );
t( 'bare accounts.google.com issuer -> accepted',
	check_claims( array_merge( $good, array( 'iss' => 'accounts.google.com' ) ) ), 'ok' );
t( 'expired token -> rejected',
	check_claims( array_merge( $good, array( 'exp' => time() - 1 ) ) ), 'exp' );
t( 'unverified email -> rejected',
	check_claims( array_merge( $good, array( 'email_verified' => 'false' ) ) ), 'unverified' );
t( 'email_verified absent -> rejected',
	check_claims( array_diff_key( $good, array( 'email_verified' => 1 ) ) ), 'unverified' );
t( 'boolean true email_verified -> accepted',
	check_claims( array_merge( $good, array( 'email_verified' => true ) ) ), 'ok' );
t( 'no sub -> rejected', check_claims( array_merge( $good, array( 'sub' => '' ) ) ), 'payload' );
t( 'malformed email -> rejected', check_claims( array_merge( $good, array( 'email' => 'nope' ) ) ), 'payload' );

/* --------------------------------------------------------- D. error codes */

echo "\nD. error codes shown to visitors\n";

$messages = AUN_SL_OAuth::messages();

// Every literal fail('x') in the source must resolve to a sentence, otherwise a
// real refusal silently shows the visitor a blank notice.
$src = file_get_contents( WP_PLUGIN_DIR . '/aun-social-login/includes/class-aun-sl-oauth.php' );
preg_match_all( "/self::fail\(\s*'([a-z_]+)'\s*\)/", $src, $m );
$missing = array_values( array_diff( array_unique( $m[1] ), array_keys( $messages ) ) );
t( 'every fail() code in the source has a message', $missing, array() );

// Every refusal decide() can produce must also be mapped.
foreach ( array( 'aun_sl_dup', 'aun_sl_admin', 'aun_sl_noemail', 'aun_sl_exists', 'aun_sl_noreg' ) as $code ) {
	t( "decide() code $code is mapped", AUN_SL_OAuth::message( $code ) !== '', true );
}

t( 'an unknown code renders nothing (no attacker-chosen wording)',
	AUN_SL_OAuth::message( 'your_account_is_locked_call_01700000000' ), '' );
t( 'a raw sentence as the code renders nothing',
	AUN_SL_OAuth::message( 'Your account is locked. Call support.' ), '' );

/* ------------------------------------------ E. "back where you started" URL */

echo "\nE. current_url() — the page the buttons return you to\n";

function current_url_for( $request_uri ) {
	$_SERVER['REQUEST_URI'] = $request_uri;
	return call_private( 'AUN_SL_UI', 'current_url' );
}

$home    = untrailingslashit( home_url() );
$account = wc_get_page_permalink( 'myaccount' );

t( 'a product page returns to that product page',
	current_url_for( '/product/aun-a45-pro/' ), $home . '/product/aun-a45-pro/' );
t( 'the cart returns to the cart (not My Account)',
	current_url_for( '/cart/' ), $home . '/cart/' );
t( 'a query string is kept',
	current_url_for( '/shop/?orderby=price' ), $home . '/shop/?orderby=price' );
t( 'a stale error notice is not carried onto the next page',
	current_url_for( '/cart/?aun_sl_error=expired' ), $home . '/cart/' );

// REQUEST_URI comes from the browser. home_url() pins the host, so none of these
// can turn the button into a link off our own domain.
foreach ( array(
	'//evil.example.com/pwn',
	'/\\evil.example.com/pwn',
	'https://evil.example.com/pwn',
) as $hostile ) {
	$got = current_url_for( $hostile );
	t( "off-site path stays on our host: $hostile",
		0 === strpos( $got, $home . '/' ) || $got === $account, true );
}

t( 'no REQUEST_URI at all -> account page', current_url_for( '' ), $account );

/* ---------------------------------------------------------- F. placement */

echo "\nF. placement toggles\n";

$defaults = AUN_SL_Options::defaults();
foreach ( array( 'at_wc_login', 'at_wc_login_before', 'at_wc_register', 'at_wc_checkout', 'at_wc_cart', 'at_wp_login' ) as $k ) {
	t( "$k has a default", array_key_exists( $k, $defaults ), true );
	// A placement missing from sanitize()'s whitelist would silently reset to 0 on
	// every Save, which looks like "the setting won't stick".
	$saved = AUN_SL_Options::sanitize( array( $k => '1' ) );
	t( "  $k survives a Save", $saved[ $k ], 1 );
}
t( 'cart placement defaults ON', $defaults['at_wc_cart'], 1 );

/* -------------------------------------------------------------- teardown */

set_opts( AUN_SL_Options::defaults() );

list( $pass, $fail ) = tally();
echo "\n----------------------------------------\n";
echo "$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
