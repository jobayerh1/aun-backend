<?php
/**
 * Plugin Name: AUN Spare Parts
 * Description: Spare-parts request intake + per-part tracking for AUN Projector. Reads sales/warranty from the UltimatePOS ERP and a legacy inFlow sales archive; lets customers request parts (no device sent in) and track each part. Phase 1: legacy import + phone/order/serial lookup + warranty calc + image compression.
 * Version: 0.18.0
 * Author: Smart Living Bangladesh
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'AUN_SP_VERSION', '0.18.0' );
define( 'AUN_SP_FILE', __FILE__ );
define( 'AUN_SP_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUN_SP_URL', plugin_dir_url( __FILE__ ) );

/*
 * ERP API base + key. The key must match WARRANTY_API_SECRET / the sales-lookup
 * secret in the ERP's .env. Define it in wp-config.php:
 *     define( 'AUN_SP_ERP_API_KEY', 'long-random-secret' );
 * The legacy archive works WITHOUT this; the key only enables live (post-Nov-2025)
 * ERP lookups once the /api/sales-lookup endpoint is deployed.
 */
if ( ! defined( 'AUN_SP_ERP_URL' ) ) {
	define( 'AUN_SP_ERP_URL', 'https://portal.smartliving.com.bd/api' );
}

/**
 * Canonicalise a Bangladeshi mobile number to the 11-digit 01XXXXXXXXX form.
 * Strips spaces, +88 / 880 country codes, and other noise so a customer is found
 * whether their record was typed "01711561441", "+8801711561441" or "1711561441".
 * Shared by the importer (on write) and the lookup (on read) so both sides match.
 */
function aun_sp_normalize_phone( $raw ) {
	$d = preg_replace( '/\D+/', '', (string) $raw );
	if ( $d === '' ) {
		return '';
	}
	// Drop a leading 880 country code (e.g. 8801711561441 -> 1711561441).
	if ( strpos( $d, '880' ) === 0 && strlen( $d ) >= 12 ) {
		$d = substr( $d, 3 );
	}
	// Local form without the leading zero (1711561441 -> 01711561441).
	if ( strlen( $d ) === 10 && $d[0] === '1' ) {
		$d = '0' . $d;
	}
	// Trim anything stuck on the end of an otherwise-valid 01XXXXXXXXX.
	if ( strlen( $d ) > 11 && substr( $d, 0, 2 ) === '01' ) {
		$d = substr( $d, 0, 11 );
	}
	return $d;
}

require_once AUN_SP_DIR . 'includes/class-aun-sp-install.php';
require_once AUN_SP_DIR . 'includes/class-aun-sp-parts.php';
require_once AUN_SP_DIR . 'includes/class-aun-sp-i18n.php';
require_once AUN_SP_DIR . 'includes/class-aun-sp-messages.php';
require_once AUN_SP_DIR . 'includes/class-aun-sp-image.php';
require_once AUN_SP_DIR . 'includes/class-aun-sp-importer.php';
require_once AUN_SP_DIR . 'includes/class-aun-sp-lookup.php';
require_once AUN_SP_DIR . 'includes/class-aun-sp-sms.php';
require_once AUN_SP_DIR . 'includes/class-aun-sp-requests.php';
require_once AUN_SP_DIR . 'includes/class-aun-sp-form.php';
require_once AUN_SP_DIR . 'includes/class-aun-sp-tracking.php';
require_once AUN_SP_DIR . 'includes/class-aun-sp-adminbar.php';

register_activation_hook( __FILE__, array( 'AUN_SP_Install', 'activate' ) );

// Daily digest email to the admin (open / waiting / overdue).
add_action( 'aun_sp_daily_digest', array( 'AUN_SP_Requests', 'send_digest' ) );

// Background retry for failed customer SMS (Alpha busy/down) — scheduled by AUN_SP_SMS::send_tracked.
add_action( 'aun_sp_sms_retry', array( 'AUN_SP_SMS', 'retry' ), 10, 5 );
register_deactivation_hook( __FILE__, function () {
	$ts = wp_next_scheduled( 'aun_sp_daily_digest' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'aun_sp_daily_digest' );
	}
} );

// Keep the schema current after a plugin update without needing a re-activation.
add_action( 'plugins_loaded', function () {
	if ( get_option( 'aun_sp_db_version' ) !== AUN_SP_Install::DB_VERSION ) {
		AUN_SP_Install::activate();
	}
} );

// Image compression infrastructure (post-response flush + WP-Cron fallback).
AUN_SP_Image::init();

// Customer-facing: request form + tracking page (shortcodes + ajax endpoints).
new AUN_SP_Form();
new AUN_SP_Tracking();

// Toolbar badge (front end + admin) so pending requests are never forgotten.
AUN_SP_Admin_Bar::init();

if ( is_admin() ) {
	require_once AUN_SP_DIR . 'includes/class-aun-sp-admin.php';
	new AUN_SP_Admin();
}
