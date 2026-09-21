<?php
/**
 * Plugin Name:       Breo Alpha SMS OTP Login
 * Plugin URI:        https://breo.bd/
 * Description:       Passwordless phone sign-in (and sign-up) for the WooCommerce account form: the customer enters a mobile number, receives a code through the sms.net.bd (Alpha SMS) gateway, and is signed in. A number without an account can create one in the same flow.
 * Version:           1.1.0
 * Author:            Breo Bangladesh
 * Author URI:        https://breo.bd/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       breo-alpha-otp-login
 * Domain Path:       /languages
 * Requires PHP:      7.4
 *
 * @package Breo_Alpha_OTP_Login
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'BREO_ALPHA_OTP_VERSION', '1.1.0' );
define( 'BREO_ALPHA_OTP_FILE', __FILE__ );
define( 'BREO_ALPHA_OTP_PATH', plugin_dir_path( __FILE__ ) );
define( 'BREO_ALPHA_OTP_URL', plugin_dir_url( __FILE__ ) );

/** Our own settings option. */
define( 'BREO_ALPHA_OTP_OPTION', 'breo_alpha_otp' );

/** The Alpha SMS plugin's option (we read its API key + sender id from here). */
define( 'BREO_ALPHA_OTP_ALPHA_OPTION', 'alpha_sms' );

require_once BREO_ALPHA_OTP_PATH . 'includes/class-breo-alpha-otp-sms.php';
require_once BREO_ALPHA_OTP_PATH . 'includes/class-breo-alpha-otp-session.php';
require_once BREO_ALPHA_OTP_PATH . 'includes/class-breo-alpha-otp-login.php';

if ( is_admin() ) {
	require_once BREO_ALPHA_OTP_PATH . 'admin/class-breo-alpha-otp-admin.php';
}

/**
 * Default settings, used on activation and as a fallback when reading options.
 *
 * @return array
 */
function breo_alpha_otp_default_options() {
	return array(
		'enabled'          => 'yes',
		'otp_first'        => 'yes',   // Show the phone form before the email/password form.
		'hide_email_login' => 'no',    // Remove the "Use email and password instead" link (phone-only).
		'allow_register'   => 'yes',   // A number without an account can sign up after verifying its code.
		'otp_length'       => 6,
		'otp_expiry'       => 120,     // Seconds the OTP stays valid.
		'resend_wait'      => 30,      // Seconds before a resend is allowed.
		'max_per_day'      => 10,      // Max OTP requests per phone and per IP, per day.
		'max_attempts'     => 5,       // Wrong-OTP tries before the code is invalidated.
		'multi_account'    => 'auto',  // When a phone matches >1 account: 'auto' (log into primary) or 'picker'.
		'sms_template'     => 'Your [site] code is [otp]. It expires in [min] minutes. Never share it with anyone.',
		'redirect'         => '',      // Post-login redirect; blank = My Account.
		'btn_otp_label'    => 'Continue',
		'btn_pwd_label'    => 'Use email and password instead',
		// Own sms.net.bd credentials — used only when the Alpha SMS plugin is not
		// installed (or has no key). The Alpha SMS plugin's key always wins.
		'api_key'          => '',
		'sender_id'        => '',
	);
}

/**
 * Merge stored options over the defaults so a missing key never throws.
 *
 * @return array
 */
function breo_alpha_otp_get_options() {
	$stored = get_option( BREO_ALPHA_OTP_OPTION, array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	return array_merge( breo_alpha_otp_default_options(), $stored );
}

/**
 * Activation: seed default options if none exist yet.
 */
function breo_alpha_otp_activate() {
	if ( false === get_option( BREO_ALPHA_OTP_OPTION, false ) ) {
		add_option( BREO_ALPHA_OTP_OPTION, array_merge( breo_alpha_otp_default_options(), array( 'db' => 110 ) ) );
	}
}
register_activation_hook( __FILE__, 'breo_alpha_otp_activate' );

/**
 * 1.1 wording. Sites that still carry the old stock labels / SMS text get the new
 * ones; anything the owner typed themselves is left alone. Runs once.
 */
function breo_alpha_otp_maybe_upgrade() {
	$o = get_option( BREO_ALPHA_OTP_OPTION );
	if ( ! is_array( $o ) || ( isset( $o['db'] ) && (int) $o['db'] >= 110 ) ) {
		return;
	}
	$new = breo_alpha_otp_default_options();
	$old = array(
		'btn_otp_label' => 'Login with OTP',
		'btn_pwd_label' => 'Login with Email & Password',
		'sms_template'  => 'Your OTP for [site] login is [otp]. Valid for [min] minutes. Do not share this code.',
	);
	foreach ( $old as $key => $stock ) {
		if ( ! isset( $o[ $key ] ) || $stock === $o[ $key ] ) {
			$o[ $key ] = $new[ $key ];
		}
	}
	if ( ! isset( $o['allow_register'] ) ) {
		$o['allow_register'] = 'yes';
	}
	$o['db'] = 110;
	update_option( BREO_ALPHA_OTP_OPTION, $o );
}

/**
 * Boot the plugin after all plugins are loaded so WooCommerce + Alpha SMS are available.
 */
function breo_alpha_otp_boot() {
	// WooCommerce is required for the account login form hooks.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	breo_alpha_otp_maybe_upgrade();

	$front = new Breo_Alpha_OTP_Login();
	$front->init();

	if ( is_admin() ) {
		$admin = new Breo_Alpha_OTP_Admin();
		$admin->init();
	}
}
add_action( 'plugins_loaded', 'breo_alpha_otp_boot', 20 );
