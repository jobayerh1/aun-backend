<?php
/**
 * Bench checks for Breo Alpha OTP Login 1.1 (phone sign-in + sign-up).
 * Needs the bench mu-plugin breo-bench-fake-sms.php (fakes sms.net.bd and keeps
 * the last message in option breo_bench_last_sms). Restores what it changes.
 *
 * Run: php -c php.ini wp-cli.phar --path=site eval-file <this file>
 */
function botp_t( $label, $ok = null, $extra = '' ) {
	static $tally = array( 'pass' => 0, 'fail' => 0 );
	if ( null === $label ) {
		return $tally;
	}
	$tally[ $ok ? 'pass' : 'fail' ]++;
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( '' !== $extra ? '  → ' . $extra : '' ) . "\n";
}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function () {
	return function () {
		throw new Exception( 'die' );
	};
} );

$opt0 = get_option( BREO_ALPHA_OTP_OPTION );
$fresh = function ( $extra = array() ) use ( $opt0 ) {
	update_option( BREO_ALPHA_OTP_OPTION, array_merge( (array) $opt0, array( 'enabled' => 'yes', 'api_key' => 'bench-fake-key', 'allow_register' => 'yes', 'db' => 110 ), $extra ) );
	unset( $_COOKIE[ Breo_Alpha_OTP_Session::COOKIE ] ); // new visitor session
	wp_set_current_user( 0 );
	return new Breo_Alpha_OTP_Login();
};
$call = function ( $obj, $action, $post = array() ) {
	wp_set_current_user( get_current_user_id() ); // keep whoever is signed in
	$post['nonce'] = wp_create_nonce( 'breo_alpha_otp' );
	$_POST         = $post;
	$_REQUEST      = $post;
	ob_start();
	try {
		$obj->{'ajax_' . $action}();
	} catch ( Exception $e ) { // phpcs:ignore
	}
	return json_decode( ob_get_clean(), true );
};
$code = function () {
	$s = get_option( 'breo_bench_last_sms' );
	return ( is_array( $s ) && preg_match( '/\b(\d{6})\b/', (string) $s['body']['msg'], $m ) ) ? $m[1] : '';
};
$cleanup = function () {
	foreach ( array( '01912345678', '01812345679', '01612345670' ) as $p ) {
		foreach ( Breo_Alpha_OTP_Session::find_users_by_phone( '88' . $p ) as $u ) {
			if ( 0 === strpos( $u->user_login, 'bd' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $u->ID );
			}
		}
		delete_transient( Breo_Alpha_OTP_Session::RL_PHONE . md5( '88' . $p ) );
	}
	delete_transient( Breo_Alpha_OTP_Session::RL_IP . md5( Breo_Alpha_OTP_Session::client_ip() ) );
};
$cleanup();

/* ------------------------------------------------ wording upgrade */
update_option( BREO_ALPHA_OTP_OPTION, array_merge( (array) $opt0, array(
	'btn_otp_label' => 'Send code',                                   // owner's own wording: kept
	'btn_pwd_label' => 'Login with Email & Password',                 // stock 1.0 wording: replaced
	'sms_template'  => 'Your OTP for [site] login is [otp]. Valid for [min] minutes. Do not share this code.',
) ) );
$o = get_option( BREO_ALPHA_OTP_OPTION );
unset( $o['db'], $o['allow_register'] );
update_option( BREO_ALPHA_OTP_OPTION, $o );
breo_alpha_otp_maybe_upgrade();
$o = get_option( BREO_ALPHA_OTP_OPTION );
botp_t( 'upgrade: custom label kept', 'Send code' === $o['btn_otp_label'] );
botp_t( 'upgrade: stock labels/SMS replaced', 'Use email and password instead' === $o['btn_pwd_label'] && 0 === strpos( $o['sms_template'], 'Your [site] code is' ) );
botp_t( 'upgrade: sign-up on, runs once', 'yes' === $o['allow_register'] && 110 === (int) $o['db'] );

/* ------------------------------------------------ new number → sign-up */
$otp = $fresh();
$r   = $call( $otp, 'send', array( 'phone' => '+880 1912-345678' ) );
botp_t( 'new number: code sent (same reply as a known number)', ! empty( $r['success'] ) && '019******78' === $r['data']['masked'], wp_json_encode( $r['data'] ) );
$c = $code();
botp_t( 'SMS uses the new wording', false !== strpos( (string) get_option( 'breo_bench_last_sms' )['body']['msg'], 'code is ' . $c ) );
$r = $call( $otp, 'verify', array( 'otp' => '000000' === $c ? '111111' : '000000' ) );
botp_t( 'wrong code refused', empty( $r['success'] ) && empty( $r['data']['reset'] ) );
$r = $call( $otp, 'verify', array( 'otp' => $c ) );
botp_t( 'right code → name step', ! empty( $r['data']['needsProfile'] ) );
$r = $call( $otp, 'register', array( 'name' => 'A' ) );
botp_t( 'one-letter name refused', empty( $r['success'] ) );
$taken = get_userdata( 1 )->user_email;
$r     = $call( $otp, 'register', array( 'name' => 'Nadia Rahman', 'email' => $taken ) );
botp_t( 'an email that already has an account is refused', empty( $r['success'] ) && false !== strpos( $r['data']['message'], 'already has an account' ) );
$r = $call( $otp, 'register', array( 'name' => 'Nadia Rahman', 'email' => 'not-an-email' ) );
botp_t( 'a malformed email is refused, not silently dropped', empty( $r['success'] ) );
$r    = $call( $otp, 'register', array( 'name' => '  Nadia   Rahman ', 'email' => '' ) );
$uid  = get_current_user_id();
$user = get_userdata( $uid );
botp_t( 'account created and signed in', ! empty( $r['data']['redirect'] ) && $uid > 0 );
botp_t( 'account details', $user && 'bd01912345678' === $user->user_login && 'Nadia Rahman' === $user->display_name && 'Nadia' === $user->first_name && 'Rahman' === $user->last_name && in_array( 'customer', (array) $user->roles, true ), $user ? $user->user_login . ' / ' . $user->display_name : 'no user' );
botp_t( 'billing phone saved (found by phone next time)', '01912345678' === get_user_meta( $uid, 'billing_phone', true ) );
$new_id = $uid;

/* ------------------------------------------------ same number again → plain sign-in */
$otp = $fresh();
$r   = $call( $otp, 'send', array( 'phone' => '01912345678' ) );
$r   = $call( $otp, 'verify', array( 'otp' => $code() ) );
botp_t( 'known number signs straight in', ! empty( $r['data']['redirect'] ) && get_current_user_id() === $new_id );
botp_t( 'no duplicate account', 1 === count( Breo_Alpha_OTP_Session::find_users_by_phone( '8801912345678' ) ) );

/* ------------------------------------------------ with email */
$otp = $fresh();
$call( $otp, 'send', array( 'phone' => '01612345670' ) );
$call( $otp, 'verify', array( 'otp' => $code() ) );
$r = $call( $otp, 'register', array( 'name' => 'Karim', 'email' => 'karim.bench@example.com' ) );
$u = get_userdata( get_current_user_id() );
botp_t( 'sign-up with email: email + billing email saved', $u && 'karim.bench@example.com' === $u->user_email && 'karim.bench@example.com' === get_user_meta( $u->ID, 'billing_email', true ) );

/* ------------------------------------------------ abuse paths */
$otp = $fresh();
$r   = $call( $otp, 'register', array( 'name' => 'Mallory' ) );
botp_t( 'sign-up without a verified code is refused', empty( $r['success'] ) && ! empty( $r['data']['reset'] ) && 0 === get_current_user_id() );

$otp = $fresh();
$call( $otp, 'send', array( 'phone' => '01812345679' ) );
$call( $otp, 'verify', array( 'otp' => $code() ) );
$sess = new Breo_Alpha_OTP_Session();
$d    = $sess->get_data();
$d['profile_until'] = time() - 1;
$sess->set_data( $d, 300 );
$r = $call( $otp, 'register', array( 'name' => 'Late Person' ) );
botp_t( 'name step times out (10 min)', empty( $r['success'] ) && ! empty( $r['data']['reset'] ) && ! Breo_Alpha_OTP_Session::find_users_by_phone( '8801812345679' ) );

$otp = $fresh( array( 'allow_register' => 'no' ) );
$r   = $call( $otp, 'send', array( 'phone' => '01812345679' ) );
botp_t( 'sign-up switched off: new number gets the old "no account" reply', empty( $r['success'] ) && false !== strpos( $r['data']['message'], 'No account' ) );

/* ------------------------------------------------ restore */
$cleanup();
update_option( BREO_ALPHA_OTP_OPTION, $opt0 );
wp_set_current_user( 0 );

$t = botp_t( null );
echo "\n" . $t['pass'] . ' passed, ' . $t['fail'] . " failed\n";
