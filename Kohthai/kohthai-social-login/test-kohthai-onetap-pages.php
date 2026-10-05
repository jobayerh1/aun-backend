<?php
/**
 * Bench test: where the automatic Google One Tap card appears (kohthai-social-login 1.10).
 * Saves the plugin's settings first and puts them back at the end. Run:
 *   php -c ~/wp-local/php.ini ~/wp-local/wp-cli.phar --path=~/wp-local/site eval-file Kohthai/kohthai-social-login/test-kohthai-onetap-pages.php --skip-themes
 */
$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;
function t( $ok, $label ) {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS ' : 'FAIL ' ), $label, "\n";
}
function kt_t_render() {
	ob_start();
	KT_SL_OneTap::render();
	$h = ob_get_clean();
	return array(
		'card'   => false !== strpos( $h, 'id="g_id_onload"' ) && false !== strpos( $h, 'data-auto_prompt="true"' ),
		'button' => false !== strpos( $h, 'google.accounts.id.initialize' ),
		'lib'    => false !== strpos( $h, 'accounts.google.com/gsi/client' ),
	);
}
function kt_t_page( $page ) {
	foreach ( array( 'woocommerce_is_checkout', 'woocommerce_is_account_page', 'woocommerce_is_cart' ) as $f ) {
		remove_all_filters( $f );
	}
	unset( $GLOBALS['wp']->query_vars['order-received'] );
	if ( 'checkout' === $page || 'thankyou' === $page ) {
		add_filter( 'woocommerce_is_checkout', '__return_true' );
	}
	if ( 'thankyou' === $page ) {
		$GLOBALS['wp']->query_vars['order-received'] = 123;
	}
	if ( 'account' === $page ) {
		add_filter( 'woocommerce_is_account_page', '__return_true' );
	}
	if ( 'cart' === $page ) {
		add_filter( 'woocommerce_is_cart', '__return_true' );
	}
}

$saved = get_option( KT_SL_Options::OPTION, null );
wp_set_current_user( 0 );
$base = array_merge( KT_SL_Options::defaults(), array(
	'google_enabled' => 1, 'google_client_id' => 'bench.apps.googleusercontent.com', 'google_client_secret' => 'x',
	'onetap_enabled' => 1, 'js_flow' => 1,
) );

// new install / update: the defaults
update_option( KT_SL_Options::OPTION, $base );
$want = array( 'checkout' => true, 'account' => true, 'cart' => false, 'other' => false, 'thankyou' => false );
foreach ( $want as $page => $card ) {
	kt_t_page( $page );
	$r = kt_t_render();
	t( $card === $r['card'], "defaults: $page " . ( $card ? 'shows' : 'does not show' ) . ' the card' );
	t( $card || $r['button'], "defaults: $page keeps the Google button working" );
}

// every box ticked = the old behaviour (card everywhere except the thank-you page)
update_option( KT_SL_Options::OPTION, array_merge( $base, array( 'onetap_on_cart' => 1, 'onetap_on_other' => 1 ) ) );
foreach ( array( 'checkout', 'account', 'cart', 'other' ) as $page ) {
	kt_t_page( $page );
	t( kt_t_render()['card'], "all ticked: $page shows the card" );
}

// One Tap switched off: never a card, buttons still work
update_option( KT_SL_Options::OPTION, array_merge( $base, array( 'onetap_enabled' => 0 ) ) );
kt_t_page( 'checkout' );
$r = kt_t_render();
t( ! $r['card'] && $r['button'], 'One Tap off: no card at checkout, button still initialised' );

// neither the card nor in-page buttons: Google's library is not loaded at all
update_option( KT_SL_Options::OPTION, array_merge( $base, array( 'js_flow' => 0 ) ) );
kt_t_page( 'other' );
t( ! kt_t_render()['lib'], 'no card and no in-page buttons on this page: Google library not loaded' );
kt_t_page( 'checkout' );
t( kt_t_render()['card'], '...but at checkout the card still loads it' );

// signed-in customers never see it
update_option( KT_SL_Options::OPTION, $base );
$admin = get_users( array( 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admin[0] );
t( ! kt_t_render()['lib'], 'signed-in: nothing rendered' );
wp_set_current_user( 0 );

// the settings form saves the new boxes (and only as 0/1)
$clean = KT_SL_Options::sanitize( array( 'onetap_on_checkout' => '1', 'onetap_on_cart' => 'yes', 'onetap_on_other' => '' ) );
t( 1 === $clean['onetap_on_checkout'] && 1 === $clean['onetap_on_cart'] && 0 === $clean['onetap_on_other'] && 0 === $clean['onetap_on_account'], 'settings save the page boxes as 0/1' );

// restore
kt_t_page( 'none' );
if ( null === $saved ) {
	delete_option( KT_SL_Options::OPTION );
} else {
	update_option( KT_SL_Options::OPTION, $saved );
}
t( get_option( KT_SL_Options::OPTION, null ) == $saved, 'bench settings restored' ); // phpcs:ignore
echo "\n{$GLOBALS['pass']} passed, {$GLOBALS['fail']} failed\n";
