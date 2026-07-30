<?php
/**
 * Plugin Name:       AUN Alpha SMS OTP Login
 * Plugin URI:        https://aun-projector.com.bd/
 * Description:       Adds passwordless phone-number Login with OTP to the WooCommerce account login form, sending the code through the Alpha SMS (sms.net.bd) gateway. Reuses the Alpha SMS API key — no extra credentials needed. Helper for the Alpha SMS plugin.
 * Version:           1.0.1
 * Author:            AUN / Smart Living Bangladesh
 * Author URI:        https://aun-projector.com.bd/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       aun-alpha-otp-login
 * Domain Path:       /languages
 * Requires PHP:      7.4
 *
 * @package AUN_Alpha_OTP_Login
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'AUN_ALPHA_OTP_VERSION', '1.0.1' );
define( 'AUN_ALPHA_OTP_FILE', __FILE__ );
define( 'AUN_ALPHA_OTP_PATH', plugin_dir_path( __FILE__ ) );
define( 'AUN_ALPHA_OTP_URL', plugin_dir_url( __FILE__ ) );

/** Our own settings option. */
define( 'AUN_ALPHA_OTP_OPTION', 'aun_alpha_otp' );

/** The Alpha SMS plugin's option (we read its API key + sender id from here). */
define( 'AUN_ALPHA_OTP_ALPHA_OPTION', 'alpha_sms' );

require_once AUN_ALPHA_OTP_PATH . 'includes/class-aun-alpha-otp-sms.php';
require_once AUN_ALPHA_OTP_PATH . 'includes/class-aun-alpha-otp-session.php';
require_once AUN_ALPHA_OTP_PATH . 'includes/class-aun-alpha-otp-login.php';

if ( is_admin() ) {
	require_once AUN_ALPHA_OTP_PATH . 'admin/class-aun-alpha-otp-admin.php';
}

/**
 * Default settings, used on activation and as a fallback when reading options.
 *
 * @return array
 */
function aun_alpha_otp_default_options() {
	return array(
		'enabled'          => 'yes',
		'otp_first'        => 'yes',   // Show the OTP login form before the email/password form.
		'hide_email_login' => 'no',    // Remove the "Login with Email & Password" toggle (phone-only).
		'otp_length'       => 6,
		'otp_expiry'       => 120,     // Seconds the OTP stays valid.
		'resend_wait'      => 30,      // Seconds before a resend is allowed.
		'max_per_day'      => 10,      // Max OTP requests per phone and per IP, per day.
		'max_attempts'     => 5,       // Wrong-OTP tries before the code is invalidated.
		'multi_account'    => 'auto',  // When a phone matches >1 account: 'auto' (log into primary) or 'picker'.
		'sms_template'     => 'Your OTP for [site] login is [otp]. Valid for [min] minutes. Do not share this code.',
		'redirect'         => '',      // Post-login redirect; blank = My Account.
		'btn_otp_label'    => 'Login with OTP',
		'btn_pwd_label'    => 'Login with Email & Password',
	);
}

/**
 * Merge stored options over the defaults so a missing key never throws.
 *
 * @return array
 */
function aun_alpha_otp_get_options() {
	$stored = get_option( AUN_ALPHA_OTP_OPTION, array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	return array_merge( aun_alpha_otp_default_options(), $stored );
}

/**
 * Activation: seed default options if none exist yet.
 */
function aun_alpha_otp_activate() {
	if ( false === get_option( AUN_ALPHA_OTP_OPTION, false ) ) {
		add_option( AUN_ALPHA_OTP_OPTION, aun_alpha_otp_default_options() );
	}
}
register_activation_hook( __FILE__, 'aun_alpha_otp_activate' );

/**
 * Boot the plugin after all plugins are loaded so WooCommerce + Alpha SMS are available.
 */
function aun_alpha_otp_boot() {
	// WooCommerce is required for the account login form hooks.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$front = new AUN_Alpha_OTP_Login();
	$front->init();

	if ( is_admin() ) {
		$admin = new AUN_Alpha_OTP_Admin();
		$admin->init();
	}
}
add_action( 'plugins_loaded', 'aun_alpha_otp_boot', 20 );
