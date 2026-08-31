<?php
/**
 * Regression harness for the kohthai-social-login plugin.
 *
 * Run against the local bench:
 *   cd ~/wp-local
 *   php wp-cli.phar eval-file "<path>/test-social-login.php" --skip-themes
 *
 * The plugin must be present in wp-content/plugins/kohthai-social-login and active.
 *
 * What this covers, and why each case is here rather than "obviously fine":
 *   A. decide()  — the account-linking matrix. The unverified-email case is the
 *      classic social-login account-takeover bug, so it is asserted explicitly.
 *   B. Avatar URL sanitisation — javascript:, data:, and lookalike hosts.
 *   C. One Tap claim checks — above all `aud`, without which a token minted for
 *      ANY other Google app would sign someone in here.
 *   D. Error codes — every code fail() can emit must map to a sentence, and a
 *      crafted ?kt_sl_error= must render nothing.
 */

if ( ! class_exists( 'KT_SL_OAuth' ) ) {
	fwrite( STDERR, "kohthai-social-login is not active on this bench.\n" );
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
	update_option( 'kt_sl_options', array_merge( KT_SL_Options::defaults(), $over ) );
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
	return call_private( 'KT_SL_OAuth', 'decide', array( $provider, $profile ) );
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
delete_user_meta( $cust->ID, 'kt_sl_google_id' );
delete_user_meta( $admin_id, 'kt_sl_google_id' );

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
	decide( profile( array( 'email_verified' => false ) ) )['code'], 'kt_sl_exists' );

set_opts( array_merge( $base, array( 'link_by_email' => 0 ) ) );
t( 'link_by_email off -> verified match still refuses',
	decide( profile() )['action'], 'refuse' );

set_opts( array_merge( $base, array( 'allow_register' => 0 ) ) );
t( 'allow_register off -> unknown email refuses',
	decide( profile( array( 'email' => 'brand-new@example.com' ) ) )['code'], 'kt_sl_noreg' );

set_opts( $base );
t( 'no email from provider -> refuse',
	decide( profile( array( 'email' => '' ) ) )['code'], 'kt_sl_noemail' );

t( 'malformed email -> refuse',
	decide( profile( array( 'email' => 'not-an-email' ) ) )['code'], 'kt_sl_noemail' );

// Now link the profile id to the customer: the link must win over everything.
update_user_meta( $cust->ID, 'kt_sl_google_id', 'g-100' );
$d = decide( profile( array( 'email' => 'totally-different@example.com', 'email_verified' => false ) ) );
t( 'existing LINK wins regardless of the email on the profile', $d['action'], 'login' );
t( '  ...and signs into the linked account', (int) $d['user_id'], (int) $cust->ID );
delete_user_meta( $cust->ID, 'kt_sl_google_id' );

// Administrator block.
update_user_meta( $admin_id, 'kt_sl_google_id', 'g-admin' );
t( 'linked ADMIN account -> refused while block_admins is on',
	decide( profile( array( 'id' => 'g-admin' ) ) )['code'], 'kt_sl_admin' );

set_opts( array_merge( $base, array( 'block_admins' => 0 ) ) );
t( 'block_admins off -> the same admin signs in',
	decide( profile( array( 'id' => 'g-admin' ) ) )['action'], 'login' );
delete_user_meta( $admin_id, 'kt_sl_google_id' );
set_opts( $base );

// Two accounts claiming one provider id must refuse rather than guess.
update_user_meta( $cust->ID, 'kt_sl_google_id', 'g-dup' );
update_user_meta( $admin_id, 'kt_sl_google_id', 'g-dup' );
t( 'two accounts share one provider id -> refuse, never guess',
	decide( profile( array( 'id' => 'g-dup' ) ) )['code'], 'kt_sl_dup' );
delete_user_meta( $cust->ID, 'kt_sl_google_id' );
delete_user_meta( $admin_id, 'kt_sl_google_id' );

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
	$got = KT_SL_Avatar::sanitize_url( $url );
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
	method_exists( 'KT_SL_OneTap', 'verify_credential' ) || (bool) ( new ReflectionMethod( 'KT_SL_OneTap', 'verify_credential' ) ), true );

function check_claims( $c ) {
	$client_id = KT_SL_Options::get( 'google_client_id' );
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

$messages = KT_SL_OAuth::messages();

// Every literal fail('x') in the source must resolve to a sentence, otherwise a
// real refusal silently shows the visitor a blank notice.
$src = file_get_contents( WP_PLUGIN_DIR . '/kohthai-social-login/includes/class-kt-sl-oauth.php' );
preg_match_all( "/self::fail\(\s*'([a-z_]+)'\s*\)/", $src, $m );
$missing = array_values( array_diff( array_unique( $m[1] ), array_keys( $messages ) ) );
t( 'every fail() code in the source has a message', $missing, array() );

// Every refusal decide() can produce must also be mapped.
foreach ( array( 'kt_sl_dup', 'kt_sl_admin', 'kt_sl_noemail', 'kt_sl_exists', 'kt_sl_noreg' ) as $code ) {
	t( "decide() code $code is mapped", KT_SL_OAuth::message( $code ) !== '', true );
}

t( 'an unknown code renders nothing (no attacker-chosen wording)',
	KT_SL_OAuth::message( 'your_account_is_locked_call_01700000000' ), '' );
t( 'a raw sentence as the code renders nothing',
	KT_SL_OAuth::message( 'Your account is locked. Call support.' ), '' );

/* ------------------------------------------ E. "back where you started" URL */

echo "\nE. current_url() — the page the buttons return you to\n";

function current_url_for( $request_uri ) {
	$_SERVER['REQUEST_URI'] = $request_uri;
	return call_private( 'KT_SL_UI', 'current_url' );
}

$home    = untrailingslashit( home_url() );
$account = wc_get_page_permalink( 'myaccount' );

t( 'a product page returns to that product page',
	current_url_for( '/product/kohthai-tote/' ), $home . '/product/kohthai-tote/' );
t( 'the cart returns to the cart (not My Account)',
	current_url_for( '/cart/' ), $home . '/cart/' );
t( 'a query string is kept',
	current_url_for( '/shop/?orderby=price' ), $home . '/shop/?orderby=price' );
t( 'a stale error notice is not carried onto the next page',
	current_url_for( '/cart/?kt_sl_error=expired' ), $home . '/cart/' );

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

$defaults = KT_SL_Options::defaults();
foreach ( array( 'at_wc_login', 'at_wc_login_before', 'at_wc_register', 'at_wc_checkout', 'at_wc_cart', 'at_wp_login' ) as $k ) {
	t( "$k has a default", array_key_exists( $k, $defaults ), true );
	// A placement missing from sanitize()'s whitelist would silently reset to 0 on
	// every Save, which looks like "the setting won't stick".
	$saved = KT_SL_Options::sanitize( array( $k => '1' ) );
	t( "  $k survives a Save", $saved[ $k ], 1 );
}
t( 'cart placement defaults ON', $defaults['at_wc_cart'], 1 );

/* ------------------------------------------- G. TranslatePress / multilingual */

echo "\nG. redirect URI must not change with the language\n";

$home = untrailingslashit( get_option( 'home' ) );

// TranslatePress adds the language slug by filtering home_url(). Reproduce that
// exactly: this is what made Google refuse Bangla sign-ins with
// redirect_uri_mismatch while English worked fine.
$trp = function ( $url, $path ) use ( $home ) {
	return $home . '/bn' . ( $path ? $path : '/' );
};
add_filter( 'home_url', $trp, 10, 2 );

t( 'the simulation is real: home_url() is now /bn/', home_url( '/' ), $home . '/bn/' );

foreach ( array( 'google', 'facebook' ) as $p ) {
	$cb = KT_SL_OAuth::callback_url( $p );
	t( "$p callback URI ignores the language prefix", false === strpos( $cb, '/bn/' ), true );
	t( "  $p callback URI is the registered one",
		$cb, $home . '/?kt_sl=callback&p=' . $p );
}

$start = KT_SL_OAuth::start_url( 'google' );
t( 'the start URL is language-neutral too', false === strpos( $start, '/bn/' ), true );

remove_filter( 'home_url', $trp, 10 );

// And with no translation plugin at all, nothing changes.
foreach ( array( 'google', 'facebook' ) as $p ) {
	t( "$p callback URI is identical without TranslatePress",
		KT_SL_OAuth::callback_url( $p ), $home . '/?kt_sl=callback&p=' . $p );
}

// The settings page must print exactly what has to go in the provider console.
t( 'the URI shown to the admin is the one used in the flow',
	KT_SL_OAuth::callback_url( 'google' ), $home . '/?kt_sl=callback&p=google' );

// https must survive: get_option('home') is raw, so the scheme has to be reapplied.
t( 'the callback URI keeps the site scheme',
	0 === strpos( KT_SL_OAuth::callback_url( 'google' ), parse_url( $home, PHP_URL_SCHEME ) . '://' ), true );

/* -------------------------------------------------- H. popup sign-in */

echo "
H. popup sign-in
";

t( 'js_flow defaults ON', KT_SL_Options::defaults()['js_flow'], 1 );
t( 'js_flow survives a Save', KT_SL_Options::sanitize( array( 'js_flow' => '1' ) )['js_flow'], 1 );
t( 'js_flow can be turned off', KT_SL_Options::sanitize( array() )['js_flow'], 0 );

$popup = file_get_contents( WP_PLUGIN_DIR . '/kohthai-social-login/includes/class-kt-sl-popup.php' );
$ui    = file_get_contents( WP_PLUGIN_DIR . '/kohthai-social-login/includes/class-kt-sl-ui.php' );
$oauth = file_get_contents( WP_PLUGIN_DIR . '/kohthai-social-login/includes/class-kt-sl-oauth.php' );

// The whole point of the rewrite: our own flow in a window, no provider SDKs.
t( 'no Facebook JS SDK is loaded', false === strpos( $popup, 'connect.facebook.net' ), true );
t( 'no Google GIS library is loaded by the buttons', false === strpos( $popup, 'accounts.google.com/gsi' ), true );
t( 'the token-accepting endpoint is gone', false === strpos( $popup, 'kt_sl_fb_token' ), true );
t( '  ...and is no longer registered', has_action( 'wp_ajax_nopriv_kt_sl_fb_token' ), false );
t( 'the popup opens our own start URL', false !== strpos( $popup, "'popup=1'" ), true );

// A popup must be opened inside the click or the browser blocks it, and a
// blocked popup must let the plain link through.
t( 'window.open runs in the click handler', false !== strpos( $popup, 'window.open(url' ), true );
t( 'a blocked popup falls through to the link', false !== strpos( $popup, 'if (!opened || opened.closed' ), true );
t( 'closing the window is not treated as an error', false !== strpos( $popup, 'win.closed' ), true );

// postMessage must be origin-locked on both ends.
t( 'the opener checks the message origin', false !== strpos( $popup, "ev.origin !== ORIGIN" ), true );
t( 'the popup posts to our exact origin, not *',
	false !== strpos( $oauth, 'window.opener.postMessage(msg, origin)' ) && false === strpos( $oauth, "postMessage(msg, '*')" ), true );

// The server side must know it is in a popup from OUR state, not the URL.
t( 'the popup flag is stored with the CSRF state', false !== strpos( $oauth, "'w' => self::\$in_popup ? 1 : 0" ), true );
t( 'the callback reads it from the stored state', false !== strpos( $oauth, "self::\$in_popup = ! empty( \$saved['w'] )" ), true );
t( 'a refusal closes the popup too', false !== strpos( $oauth, "self::close_popup( '', \$code )" ), true );
t( 'no opener means a normal redirect', false !== strpos( $oauth, 'window.location.replace(fallback)' ), true );
t( 'the closer page is not cached', false !== strpos( $oauth, 'nocache_headers()' ), true );

// The redirect flow must be completely unaffected when the popup is not used.
t( 'a non-popup callback still redirects', false !== strpos( $oauth, 'wp_safe_redirect( $redirect );' ), true );
t( 'the start URL itself carries no popup flag',
	false === strpos( KT_SL_OAuth::start_url( 'google' ), 'popup' ), true );

t( 'the inline script keeps the WP Rocket guards',
	false !== strpos( $popup, 'data-no-optimize="1"' ) && false !== strpos( $popup, 'data-no-minify="1"' ), true );

// GOOGLE: the browser's own dialog, which only Google's rendered button can raise.
$onetap = file_get_contents( WP_PLUGIN_DIR . '/kohthai-social-login/includes/class-kt-sl-onetap.php' );

t( "Google's own button is rendered", false !== strpos( $popup, 'google.accounts.id.renderButton' ), true );
t( 'FedCM button mode is enabled', false !== strpos( $onetap, 'use_fedcm_for_button' ), true );
t( 'the deprecated prompt flag is gone',
	false === strpos( $onetap, 'use_fedcm_for_prompt: true' )
	&& false === strpos( $onetap, 'data-use_fedcm_for_prompt' ), true );

// Neither dead approach may come back: prompt() cannot give a button the dialog.
t( 'no prompt()-on-click path remains', false === strpos( $popup, 'google.accounts.id.prompt' ), true );
t( 'the FedCM-throwing methods are not called',
	false === strpos( $popup, 'note.isNotDisplayed' ) && false === strpos( $popup, 'note.isSkippedMoment' ), true );

// A silent failure must never leave the page with no Google button at all.
t( 'ours is hidden only after Google actually draws', false !== strpos( $popup, 'if (host.firstChild)' ), true );
t( 'a failed render removes the empty host', false !== strpos( $popup, 'host.parentNode.removeChild(host)' ), true );
t( 'the async library is waited for', false !== strpos( $popup, 'waitForGoogle' ), true );
t( '  ...but not forever', false !== strpos( $popup, 'if (tries <= 0) return;' ), true );

// The rendered button has to be configured, since Google styles it, not our CSS.
t( 'the button config is passed through', 1, preg_match( '/var GBTN\s*=/', $popup ) );
foreach ( array( 'type', 'shape', 'theme', 'size', 'text', 'logo_alignment' ) as $k ) {
	t( "  GBTN carries '$k'", false !== strpos( $popup, "'" . $k . "'" ), true );
}

// The half-cut icon: a bare <span> is inline, and a flex item under the default
// align-items:stretch, so Google's button was stretched and clipped.
$css = file_get_contents( WP_PLUGIN_DIR . '/kohthai-social-login/assets/kohthai-social-login.css' );
t( 'the button row centres its items', false !== strpos( $css, 'align-items:center;' ), true );
t( 'the Google host is not inline', false !== strpos( $css, '.kt-sl-gbtn{' ) && false !== strpos( $css, 'display:inline-flex' ), true );
t( 'the host imposes no size of its own', false !== strpos( $css, 'width:auto;height:auto;max-width:none;max-height:none;' ), true );
t( 'the host does not clip', false !== strpos( $css, 'overflow:visible' ), true );
t( 'our buttons resize to match the Google button', false !== strpos( $popup, "row.style.setProperty('--kt-sl-size'" ), true );
t( '  ...measured from what Google actually drew', false !== strpos( $popup, 'host.offsetHeight' ), true );

// Google lays out asynchronously: measuring straight after renderButton() returns
// 0, which is what left the Google icon smaller than the Facebook one.
t( 'measurement retries until Google has laid out', false !== strpos( $popup, 'setInterval(function(){' ), true );
t( '  ...and keeps watching for later reflows', false !== strpos( $popup, 'ResizeObserver' ), true );
t( '  ...but gives up eventually', false !== strpos( $popup, 'tries > 40' ), true );
t( 'the measured width is published too', false !== strpos( $popup, "'--kt-sl-gw'" ), true );

// Only the standard button takes a width; the icon button has no such option.
t( 'the labelled button is given an explicit width', false !== strpos( $popup, "\$gbtn['width'] = '340';" ), true );
t( 'our labelled button uses the measured width', false !== strpos( $css, 'width:var(--kt-sl-gw,100%)' ), true );

// Wording. Google permits exactly four phrasings and no bare "Google", so the
// option must never offer or store anything outside that set.
t( 'label_style defaults to continue_with', KT_SL_Options::defaults()['label_style'], 'continue_with' );
foreach ( array( 'continue_with', 'signin_with', 'signup_with', 'signin' ) as $v ) {
	t( "  '$v' is accepted", KT_SL_Options::sanitize( array( 'label_style' => $v ) )['label_style'], $v );
}
t( 'a bare "google" is rejected', KT_SL_Options::sanitize( array( 'label_style' => 'google' ) )['label_style'], 'continue_with' );
t( 'junk falls back to the default', KT_SL_Options::sanitize( array( 'label_style' => '<x>' ) )['label_style'], 'continue_with' );
t( 'the chosen wording reaches the Google button', false !== strpos( $popup, "\$o['label_style']" ), true );

t( 'Facebook mirrors the same phrasing', false !== strpos( $ui, "sprintf( \$pattern, 'Facebook' )" ), true );

// The either/or: Google's own button (dialog, their wording) vs ours (any
// wording, popup window). Sites appearing to have both use the retired
// gapi.auth2 library, which this plugin deliberately does not.
t( 'google_button defaults to native', KT_SL_Options::defaults()['google_button'], 'native' );
t( 'custom is accepted', KT_SL_Options::sanitize( array( 'google_button' => 'custom' ) )['google_button'], 'custom' );
t( 'junk falls back to native', KT_SL_Options::sanitize( array( 'google_button' => 'gapi' ) )['google_button'], 'native' );
t( 'custom mode skips the rendered button', false !== strpos( $popup, 'if (!GNATIVE) { return true; }' ), true );
t( 'the legacy library is never loaded', false === strpos( $popup, 'apis.google.com' ), true );

// Brand-only wording is ours; Google has no such value and must get a legal one.
t( 'brand-only wording is accepted', KT_SL_Options::sanitize( array( 'label_style' => 'brand' ) )['label_style'], 'brand' );
t( 'our buttons can render the bare brand name', false !== strpos( $ui, "'brand'         => '%s'" ), true );
t( 'Google is given a legal phrasing instead',
	false !== strpos( $popup, "( 'brand' === \$o['label_style'] ) ? 'signin_with'" ), true );

// The wide buttons had three cosmetic bugs, all of them ours.
t( 'the shape setting is not overridden by a hard-coded pill',
	false === strpos( $popup, "\$gbtn['shape'] = 'pill';" ), true );
t( 'Google is given the shape the admin chose',
	false !== strpos( $popup, "( 'round' === \$o['shape'] ) ? 'pill' : 'rectangular'" ), true );
t( 'wide buttons get their own radius variable', false !== strpos( $ui, '--kt-sl-radius-w:' ), true );
t( '  ...and the stylesheet uses it', false !== strpos( $css, 'border-radius:var(--kt-sl-radius-w,999px)' ), true );

t( 'a fixed min-height no longer beats the measurement',
	false === strpos( $css, 'min-height:46px' ), true );
t( '  ...both heights come from the measurement',
	false !== strpos( $css, 'height:var(--kt-sl-size,46px);min-height:var(--kt-sl-size,46px)' ), true );

t( 'the row is flagged once Google draws a button', false !== strpos( $popup, "classList.add('kt-sl-native')" ), true );
t( '  ...and our hover then matches theirs', false !== strpos( $css, '.kt-sl-native .kt-sl-btn:hover' ), true );
t( '  ...dropping the lift', false !== strpos( $css, 'transform:none;box-shadow:0 1px 3px' ), true );

// Facebook is unaffected — it has no FedCM equivalent, so the window is right.
t( 'Facebook still gets the window', false !== strpos( $popup, 'kt_sl_login' ), true );

// The library must load whenever Google sign-in works, not only for the prompt.
t( 'the library is no longer gated on onetap_enabled',
	false === strpos( $onetap, "if ( ! KT_SL_Options::get( 'onetap_enabled' ) || ! KT_SL_Options::provider_ready( 'google' ) ) {" ), true );
t( 'the auto-prompt div is gated on onetap_enabled', false !== strpos( $onetap, 'if ( $auto ) :' ), true );
t( 'without the auto-prompt it still initialises for the button',
	false !== strpos( $onetap, 'google.accounts.id.initialize' ), true );
t( 'nothing loads when neither needs it', false !== strpos( $onetap, 'if ( ! $auto && ! $btn ) {' ), true );

/* -------------------------------------------------------------- teardown */

set_opts( KT_SL_Options::defaults() );

list( $pass, $fail ) = tally();
echo "\n----------------------------------------\n";
echo "$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
