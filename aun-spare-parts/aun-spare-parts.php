<?php
/**
 * Plugin Name: AUN Spare Parts
 * Description: Spare-parts request intake + per-part tracking for AUN Projector. Reads sales/warranty from the UltimatePOS ERP and a legacy inFlow sales archive; lets customers request parts (no device sent in) and track each part. Phase 1: legacy import + phone/order/serial lookup + warranty calc + image compression.
 * Version: 0.47.1
 * Author: Smart Living Bangladesh
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'AUN_SP_VERSION', '0.47.1' );
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

/**
 * SQL condition matching a phone column against a number in ANY stored format.
 *
 * Requests can be created by more than one writer: the web form stores the
 * canonical 01XXXXXXXXX, while the AUN Care Android app stores the international
 * 8801XXXXXXXXX. An exact `phone_current = '01…'` comparison silently missed every
 * app-created request — customers tracking by mobile got "No request found".
 *
 * So we match on the last 10 digits (the part that is identical in every format),
 * ignoring spaces, + and dashes. The exact-equality test is kept first so the
 * indexed lookup still short-circuits for normal rows.
 *
 * @return string prepared SQL fragment, already escaped; '1=0' if unusable.
 */
function aun_sp_phone_where( $column, $raw ) {
	global $wpdb;
	$digits = preg_replace( '/\D+/', '', (string) $raw );
	$last10 = substr( $digits, -10 );
	if ( strlen( $last10 ) < 9 ) {
		return '1=0'; // too short to identify anyone — match nothing
	}
	return $wpdb->prepare(
		"( $column = %s OR REPLACE(REPLACE(REPLACE($column,' ',''),'+',''),'-','') LIKE %s )",
		aun_sp_normalize_phone( $raw ),
		'%' . $wpdb->esc_like( $last10 )
	);
}

/**
 * The visitor's IP, for rate limiting.
 *
 * Behind Cloudflare, REMOTE_ADDR is a Cloudflare edge that thousands of visitors
 * share, so the real address has to come from CF-Connecting-IP. But that header is
 * only TRUE when the request actually arrived from Cloudflare: anyone who reaches
 * the origin directly can put any value in it. Mixing it into the rate-limit key
 * (as this plugin used to) let such a client get a fresh bucket on every request
 * simply by changing the header — switching off the limits on approve, decline,
 * pay and re-upload.
 *
 * So the header is believed only when REMOTE_ADDR is a Cloudflare edge, or a
 * private/loopback address (a proxy on the host itself, which an outside client
 * cannot impersonate). Otherwise REMOTE_ADDR is used as it is.
 */
function aun_sp_client_ip() {
	$remote = aun_sp_unmap_ip( isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '' );
	$cf     = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? trim( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] ) : '';

	if ( '' !== $cf && filter_var( $cf, FILTER_VALIDATE_IP ) && aun_sp_is_trusted_proxy( $remote ) ) {
		return $cf;
	}
	return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : 'unknown';
}

/** "::ffff:1.2.3.4" (IPv4 written as IPv6, which some servers report) -> "1.2.3.4". */
function aun_sp_unmap_ip( $ip ) {
	if ( 0 === stripos( (string) $ip, '::ffff:' ) && filter_var( substr( $ip, 7 ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		return substr( $ip, 7 );
	}
	return (string) $ip;
}

/** Whether REMOTE_ADDR is a proxy whose forwarded-IP header may be believed. */
function aun_sp_is_trusted_proxy( $ip ) {
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return false;
	}
	// Private / loopback / reserved = a proxy on the host's own network.
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		return true;
	}
	// Cloudflare's published edge ranges (cloudflare.com/ips). They change rarely;
	// filterable so they can be updated without editing the plugin.
	$ranges = apply_filters( 'aun_sp_trusted_proxies', array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
		'141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
		'197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
		'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
		'2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
	) );
	foreach ( (array) $ranges as $cidr ) {
		if ( aun_sp_ip_in_cidr( $ip, $cidr ) ) {
			return true;
		}
	}
	return false;
}

/** IPv4 / IPv6 CIDR membership, compared byte by byte (no GMP/BCMath needed). */
function aun_sp_ip_in_cidr( $ip, $cidr ) {
	$parts = explode( '/', (string) $cidr, 2 );
	$ipb   = @inet_pton( (string) $ip );
	$netb  = @inet_pton( $parts[0] );
	if ( false === $ipb || false === $netb || strlen( $ipb ) !== strlen( $netb ) ) {
		return false;
	}
	$bits  = isset( $parts[1] ) ? (int) $parts[1] : strlen( $ipb ) * 8;
	$whole = intdiv( $bits, 8 );
	if ( substr( $ipb, 0, $whole ) !== substr( $netb, 0, $whole ) ) {
		return false;
	}
	$rest = $bits % 8;
	if ( 0 === $rest ) {
		return true;
	}
	$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
	return ( ord( $ipb[ $whole ] ) & $mask ) === ( ord( $netb[ $whole ] ) & $mask );
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
require_once AUN_SP_DIR . 'includes/class-aun-sp-woo.php';

register_activation_hook( __FILE__, array( 'AUN_SP_Install', 'activate' ) );

// Daily digest email to the admin (open / waiting / overdue).
add_action( 'aun_sp_daily_digest', array( 'AUN_SP_Requests', 'send_digest' ) );

// Daily: chase unanswered quotes, then expire them. Runs BEFORE the digest is
// composed (priority 5) so today's expiries are reflected in today's email.
add_action( 'aun_sp_daily_digest', array( 'AUN_SP_Requests', 'process_quotes' ), 5 );

// Hourly: remove unpaid orders the customer never completed, so the shop's Orders
// list only keeps real sales (the window is set in Spare Parts -> Settings).
add_action( 'aun_sp_hourly_tidy', array( 'AUN_SP_Woo', 'cancel_abandoned' ) );

// Hourly: compress any photo the upload path never queued — chiefly those sent
// from the AUN Care app, which were stored at full phone size. Bounded, so a
// shared server never spends long on it (see AUN_SP_Image::sweep).
add_action( 'aun_sp_hourly_tidy', array( 'AUN_SP_Image', 'sweep_hourly' ) );

// Hourly: make sure each of our cron hooks has exactly ONE scheduled run. A second
// entry makes every hook fire twice — which is how the daily digest email started
// arriving twice, minutes apart. Cheap: it counts, and only acts if something is
// wrong (see AUN_SP_Install::ensure_single_schedule).
add_action( 'aun_sp_hourly_tidy', array( 'AUN_SP_Install', 'ensure_single_schedule' ) );

// Background retry for failed customer SMS (Alpha busy/down) — scheduled by AUN_SP_SMS::send_tracked.
add_action( 'aun_sp_sms_retry', array( 'AUN_SP_SMS', 'retry' ), 10, 5 );
register_deactivation_hook( __FILE__, function () {
	foreach ( array( 'aun_sp_daily_digest', 'aun_sp_hourly_tidy' ) as $hook ) {
		// wp_clear_scheduled_hook(), NOT wp_next_scheduled() + wp_unschedule_event():
		// the latter removes only the NEXT run, so if a hook ever ended up scheduled
		// twice, deactivating left one behind — and re-activating added another. That
		// is how a hook accumulates duplicates and starts firing twice a day.
		wp_clear_scheduled_hook( $hook );
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

// WooCommerce payment bridge (approved quote -> real order -> SSLCommerz / COD).
AUN_SP_Woo::init();

if ( is_admin() ) {
	require_once AUN_SP_DIR . 'includes/class-aun-sp-admin.php';
	new AUN_SP_Admin();
}
