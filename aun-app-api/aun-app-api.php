<?php
/**
 * Plugin Name:       AUN App API
 * Plugin URI:        https://aun-projector.com.bd/
 * Description:       REST API backend for the AUN Care Bangladesh Android customer app: phone+OTP login, device registration & warranty (reads the SLB Warranty plugin tables), firmware/manual/video/tip content per model, and app configuration. Companion to AUN Warranty Registration and AUN Alpha SMS OTP Login.
 * Version:           1.106.0
 * Author:            AUN / Smart Living Bangladesh
 * Author URI:        https://aun-projector.com.bd/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       aun-app-api
 * Requires PHP:      7.4
 *
 * @package AUN_App_API
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'AUN_APP_API_VERSION', '1.106.0' );
// v15 = referral programme tables (aun_app_referrals + _referral_claims).
// v14 = adds aun_app_notice_state.completed_at/snoozed_until (actionable
// maintenance reminders — mark done / remind me later).
// v13 = adds aun_app_content.app_downloadable (per-file "downloadable in app").
// v12 = adds aun_app_feedback.image_url (bug-report screenshot).
// v11 = aun_app_repairs.last_erp_status (repair-status change push).
// v10 = the aun_app_dismissed ledger. Bumping re-runs activation so existing
// installs get new columns + crons.
// v16 = aun_app_referral_claims.revoke_reason (a reversed order can be undone;
// an ineligible buyer cannot).
// v17 = aun_app_tokens.fcm_build (which notification channels that
// phone's app actually has — Android drops pushes naming unknown ones).
// v18 = aun_app_content.changelog ("what's new" in a firmware release —
// the reason to install it, kept apart from the steps that say how).
// v19 = aun_app_events. FIRST-PARTY product analytics: the app's privacy
// policy promises no third-party analytics SDK, so the counts live on our
// own server instead of Firebase.
// v20 = aun_app_repairs.courier_name/courier_tracking/courier_at — the
// customer tells us the parcel is on its way from inside the app, instead of
// through a WhatsApp message nobody can attach to the request.
// v21 = aun_app_repairs.erp_missing_since — when the linked ERP job sheet
// first came back as a definite "no such record". A DELETED job sheet used to
// be indistinguishable from an ERP outage (both just skipped the row), so the
// repair froze on the customer's screen for ever and could never adopt a
// replacement sheet.
define( 'AUN_APP_API_DB_VERSION', '21' );
define( 'AUN_APP_API_FILE', __FILE__ );
define( 'AUN_APP_API_PATH', plugin_dir_path( __FILE__ ) );
define( 'AUN_APP_API_URL', plugin_dir_url( __FILE__ ) );

/** REST namespace used by the Android app. */
define( 'AUN_APP_API_NS', 'aun-app/v1' );

/** Our settings option. */
define( 'AUN_APP_API_OPTION', 'aun_app_api' );

/** The Alpha SMS plugin option (api_key + sender_id live there). */
if ( ! defined( 'AUN_ALPHA_OTP_ALPHA_OPTION' ) ) {
	define( 'AUN_APP_ALPHA_OPTION', 'alpha_sms' );
} else {
	define( 'AUN_APP_ALPHA_OPTION', AUN_ALPHA_OTP_ALPHA_OPTION );
}

/** The AUN Alpha OTP Login plugin option — OTP length/expiry/limits are shared with it. */
define( 'AUN_APP_OTP_OPTION', 'aun_alpha_otp' );

/** The SLB Warranty plugin option — SMS/email templates + auto-approve rule live there. */
define( 'AUN_APP_SLB_OPTION', 'slb_warranty_opts' );

require_once AUN_APP_API_PATH . 'includes/class-aun-app-phone.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-sms.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-otp.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-tokens.php';
// Loaded BEFORE the ERP and ticket clients — both ask it whether a call can
// stay on this machine instead of going out to Cloudflare and back.
require_once AUN_APP_API_PATH . 'includes/class-aun-app-local-route.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-erp.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-profile.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-warranty.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-content.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-events.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-services.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-notices.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-account.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-push.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-greeting.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-help.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-tickets.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-watch.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-chorki.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-projectors.php';
// After projectors: AUN_App_Filters reuses its model normaliser.
require_once AUN_APP_API_PATH . 'includes/class-aun-app-filters.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-referrals.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-sslcommerz.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-rest.php';

if ( is_admin() ) {
	require_once AUN_APP_API_PATH . 'admin/class-aun-app-admin.php';
}

// "What to watch" rebuilds always happen in the background (single event fired
// when the store goes stale, plus a twice-daily warm), never in a customer
// request — see AUN_App_Watch::picks().
add_action( 'aun_app_watch_refresh', array( 'AUN_App_Watch', 'refresh_cron' ) );
// Chorki's "hot and fresh" list → the local half of the same rail. Its own
// event because it is a different, slower source: one HTTP call per title
// against chorki.net, which must never share a worker with the TMDB rebuild.
add_action( 'aun_app_chorki_refresh', array( 'AUN_App_Chorki', 'refresh_cron' ) );
add_action( 'aun_app_watch_warm', array( 'AUN_App_Watch', 'refresh_cron' ) );
add_action( 'aun_app_chorki_warm', array( 'AUN_App_Chorki', 'refresh_cron' ) );

/**
 * Default plugin settings.
 *
 * @return array
 */
function aun_app_api_default_options() {
	return array(
		'whatsapp_number'     => '',          // e.g. 8801XXXXXXXXX (digits only, used in wa.me link).
		'support_phone'       => '',          // Tap-to-call number shown in the app.
		// Where a customer posts a projector once we approve their repair
		// request. Until these are filled the app says "we will message you
		// the address" rather than inventing one.
		// Kill switch for in-app analytics + crash reporting. ON by default;
		// turning it off stops collection in the SDK itself, not merely our
		// calls, and takes effect on the next config load with no APK rebuild.
		'analytics_enabled'   => 1,
		// Customer SMS for the repair flow. Editable in AUN App -> Settings.
		//
		// ⚠️ These were hardcoded in PHP until 1.84.0, which meant the owner had
		// to ask a developer to change a full stop. Every other customer-facing
		// message in the system is admin-editable; these were the exception, and
		// they also said "SmartLiving:" while the spare-parts plugin says "AUN:"
		// — the same company introducing itself two different ways.
		'repair_sms_received' => 'AUN: we received your repair request {ref} for {model}. We will confirm by SMS before you send the projector. Please do NOT ship it yet.',
		'repair_sms_approved' => 'AUN: your repair {ref} is approved. Please send the projector to us — the address is in the AUN Care app. {note}',
		'repair_sms_rejected' => 'AUN: we could not accept repair request {ref}. {note}',
		// ⚠️ Cancelling AFTER approval is NOT the same event as refusing a new
		// request, and must not borrow its words. By this point we have already
		// told the customer "approved, please send it" — they may be holding a
		// courier receipt. "We could not accept your request" would read as if
		// they had done something wrong.
		'repair_sms_cancelled' => 'AUN: we are very sorry — we have had to cancel repair {ref} after approving it. {note} If you have already posted the projector, please contact us and we will sort it out.',
		'repair_ship_name'    => '',
		'repair_ship_phone'   => '',
		'repair_ship_address' => '',
		'repair_ship_note'    => '',          // Anything specific to your counter/hours.
		// Pathao's booking form asks for City / Zone / Area as DROPDOWNS, and
		// picking the wrong one is the single most common way a parcel goes to
		// the wrong hub. These are the exact option names to choose, mirroring
		// the same block already on the website.
		'repair_ship_city'    => '',
		'repair_ship_zone'    => '',
		'repair_ship_area'    => '',
		'support_hours'       => 'Sat–Thu, 10am–8pm',
		'facebook_url'        => '',
		'website_url'         => '',          // Blank = home_url().
		'announcement'        => '',          // Short notice shown on the app home screen.
		'banners'             => '',          // One per line: image_url | optional_link_url
		'latest_version_code' => 1,           // Newest APK build number.
		'latest_version_name' => '1.0.0',
		'apk_url'             => '',          // Direct APK download (pre-Play-Store distribution).
		'release_notes'       => '',          // "What's new" shown in the app's update sheet.
		'min_version_code'    => 1,           // Builds older than this are forced to update.
		'discount_note'       => '',          // e.g. "App-only discount: use code APP5"
		'token_days'          => 180,         // Login token lifetime in days.
		'login_video_url'     => '',          // Background video on the app login screen (MP4, ~720p, a few MB).
		'repair_api_key'      => '',          // ERP repair-status API key. Blank = the SLB_ERP_API_KEY constant (same key the website repair tracker uses).
		'fcm_service_account' => '',          // Firebase service-account JSON (paste the whole file) — enables push notifications.
		'tickets_base_url'    => '',          // osTicket site root, e.g. https://support.smartliving.com.bd
		'tickets_secret'      => '',          // Must equal AUN_BRIDGE_SECRET in the bridge's bridge-config.php.
		// Custom form values sent with app tickets, one `variable = value` per
		// line. {a|b|c} = first non-empty token. Tokens: invoice, serial,
		// model, phone, name. Empty-resolving lines are skipped.
		'tickets_field_map'   => "ordernumber = {invoice|serial|phone}\npurchasechannel = AUN Projector App",
		'tmdb_api_key'        => '',          // Free TMDB key → "what to watch" global trending on Home.
		'watch_local_picks'   => '',          // One per line: Title | Platform | URL | optional poster URL
		'watch_limit'         => 30,          // How many global "what to watch" titles to serve (max 100).
		// Chorki auto-picks: pull their published list into the app's rail instead
		// of hand-typing local picks that go stale. Off until switched on.
		'chorki_enabled'      => 0,
		'chorki_limit'        => 5,           // How many of Chorki's newest titles to show (max 12).
		'chorki_list_url'     => '',          // Blank = AUN_App_Chorki::LIST_URL (hot-and-fresh).
		'youtube_api_key'     => '',          // Free YouTube Data API key → real video upload dates.
		// OUR OWN OneDrive/SharePoint app registration, so firmware downloads do
		// not depend on the WP File Download plugin. Optional: with these blank
		// we fall back to that plugin's token (if installed), then to the public
		// "Anyone with the link" share URL.
		'onedrive_client_id'     => '',
		'onedrive_client_secret' => '',
		'onedrive_tenant'        => '',       // Directory (tenant) ID, or 'common'.
		'onedrive_refresh_token' => '',       // Delegated grant (optional; blank = app-only).
		// Referral programme. Ships OFF with zero amounts, so nothing can be
		// claimed until an admin has deliberately chosen the numbers.
		'referral_enabled'         => 0,
		'referral_friend_type'     => 'percent', // percent | fixed
		'referral_friend_amount'   => 0,         // the friend's first-order discount
		'referral_referrer_amount' => 0,         // thank-you credit, in Tk
		'referral_min_order'       => 0,         // minimum spend to qualify
		'referral_monthly_cap'     => 5,         // rewards per referrer per 30 days
		'referral_claim_days'      => 30,        // how long a new account may claim
		'referral_expiry_days'     => 90,        // the FRIEND's welcome coupon lifetime
		// The referrer's EARNED reward lives much longer than a promotional
		// discount: they worked for it. 0 = never expires.
		'referral_reward_expiry_days' => 365,
		// Which order status pays the reward. Stores using a shipment plugin
		// (AST Pro adds wc-shipped / wc-delivered) should point this at
		// "Delivered", so a refused cash-on-delivery parcel never pays out.
		'referral_reward_status'   => 'completed',
		// Pay on Completed AS WELL as the status above. Off: a shipment
		// plugin that auto-completes at dispatch would otherwise pay early.
		'referral_reward_also_completed' => 0,
		// SSLCommerz, for the app's DIRECT payment session. Normally blank:
		// the credentials are read from the WooCommerce gateway that is
		// already configured. Fill these only if that lookup cannot find
		// them — two copies of a credential drift apart.
		'sslc_store_id'   => '',
		'sslc_store_pass' => '',
		'sslc_sandbox'    => 0,
		// Test lines: numbers allowed to redeem a code even though they are
		// existing customers. Bypasses THAT rule only. Blank on a normal site.
		'referral_test_phones'     => '',
	);
}

/**
 * Stored options merged over defaults so a missing key never throws.
 *
 * @return array
 */
function aun_app_api_get_options() {
	$stored = get_option( AUN_APP_API_OPTION, array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	return array_merge( aun_app_api_default_options(), $stored );
}

/**
 * OTP behaviour settings — read from the AUN Alpha OTP Login plugin when present so
 * both login surfaces behave identically; safe defaults otherwise.
 *
 * @return array
 */
function aun_app_api_otp_settings() {
	$defaults = array(
		'otp_length'   => 6,
		'otp_expiry'   => 180,
		'resend_wait'  => 30,
		'max_per_day'  => 10,
		'max_attempts' => 5,
		'sms_template' => 'Your OTP for [site] login is [otp]. Valid for [min] minutes. Do not share this code.',
	);

	$stored = get_option( AUN_APP_OTP_OPTION, array() );
	if ( ! is_array( $stored ) ) {
		return $defaults;
	}

	$out = $defaults;
	foreach ( array( 'otp_length', 'otp_expiry', 'resend_wait', 'max_per_day', 'max_attempts' ) as $key ) {
		if ( isset( $stored[ $key ] ) && (int) $stored[ $key ] > 0 ) {
			$out[ $key ] = (int) $stored[ $key ];
		}
	}
	if ( ! empty( $stored['sms_template'] ) && is_string( $stored['sms_template'] ) ) {
		$out['sms_template'] = $stored['sms_template'];
	}

	return $out;
}

/**
 * Table name helpers.
 */
function aun_app_api_tokens_table() {
	global $wpdb;
	return $wpdb->prefix . 'aun_app_tokens';
}

function aun_app_api_content_table() {
	global $wpdb;
	return $wpdb->prefix . 'aun_app_content';
}

function aun_app_api_devices_table() {
	global $wpdb;
	return $wpdb->prefix . 'aun_app_devices';
}

/**
 * Ledger of auto-linked purchases the customer has DELIBERATELY removed, so the
 * ERP auto-sync never silently re-adds a device the customer chose to drop. One
 * row per (user, serial); the invoice/date let the "brain" tell that exact
 * purchase apart from a genuinely new purchase of the same unit (new invoice).
 */
function aun_app_api_dismissed_table() {
	global $wpdb;
	return $wpdb->prefix . 'aun_app_dismissed';
}

/**
 * Activation: create tables and seed default options.
 */
function aun_app_api_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$tokens  = aun_app_api_tokens_table();
	$content = aun_app_api_content_table();

	dbDelta( "CREATE TABLE IF NOT EXISTS $tokens (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		token_hash char(64) NOT NULL,
		device_name varchar(120) DEFAULT '',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		last_used datetime DEFAULT CURRENT_TIMESTAMP,
		expires_at datetime NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY token_hash_idx (token_hash),
		KEY user_idx (user_id)
	) $charset;" );

	dbDelta( "CREATE TABLE IF NOT EXISTS $content (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		type varchar(20) NOT NULL,
		model_id bigint(20) unsigned DEFAULT 0,
		title varchar(191) NOT NULL,
		description text,
		changelog text,
		url varchar(500) DEFAULT '',
		version varchar(50) DEFAULT '',
		file_size varchar(30) DEFAULT '',
		sort int DEFAULT 0,
		active tinyint(1) DEFAULT 1,
		app_downloadable tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY type_idx (type),
		KEY model_idx (model_id)
	) $charset;" );

	// v3: direct-purchase device links. A projector bought directly from AUN
	// (not via a dealer) needs no warranty registration — the ERP sale is the
	// proof. We store a lightweight link here so the device persists in "My
	// Devices", is removable, and its serial can't be claimed by anyone else.
	// Warranty is computed from purchase_date; NO SMS/email is ever sent.
	$devices = $wpdb->prefix . 'aun_app_devices';
	dbDelta( "CREATE TABLE IF NOT EXISTS $devices (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		phone varchar(50) DEFAULT '',
		serial varchar(191) NOT NULL,
		model varchar(191) DEFAULT '',
		purchase_date date DEFAULT NULL,
		invoice_no varchar(100) DEFAULT '',
		erp_contact_id bigint(20) DEFAULT 0,
		erp_product_id bigint(20) DEFAULT 0,
		source varchar(20) DEFAULT 'direct',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY serial_idx (serial),
		KEY user_idx (user_id),
		KEY phone_idx (phone)
	) $charset;" );

	// v4: add erp_product_id to existing installs. dbDelta silently skips new
	// columns when the CREATE uses "IF NOT EXISTS", so add it explicitly.
	// NOTE: check membership in the full column list — "SHOW COLUMNS ... LIKE"
	// is not honoured by the SQLite dev bench (returns all columns).
	$device_cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM $devices" );
	if ( ! in_array( 'erp_product_id', $device_cols, true ) ) {
		// No "AFTER" — SQLite rejects positional ADD COLUMN.
		$wpdb->query( "ALTER TABLE $devices ADD COLUMN erp_product_id bigint(20) DEFAULT 0" );
	}

	// v10: "dismissed purchases" ledger. When a customer removes an auto-linked
	// (direct) device, the ERP still shows the sale, so the login/refresh sync
	// would re-add it forever. We remember the removal here (one row per
	// user+serial) and the auto-sync skips that exact purchase. A genuinely new
	// purchase of the same unit (different invoice) clears the dismissal and
	// links normally; a manual re-add (scan / find-by-phone) clears it too.
	$dismissed = $wpdb->prefix . 'aun_app_dismissed';
	dbDelta( "CREATE TABLE IF NOT EXISTS $dismissed (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		serial varchar(191) NOT NULL,
		invoice_no varchar(100) DEFAULT '',
		sale_date date DEFAULT NULL,
		dismissed_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY user_serial_idx (user_id, serial),
		KEY user_idx (user_id),
		KEY serial_idx (serial)
	) $charset;" );

	// v2: send-in repair requests.
	$repairs = $wpdb->prefix . 'aun_app_repairs';
	dbDelta( "CREATE TABLE IF NOT EXISTS $repairs (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ref varchar(32) NOT NULL DEFAULT '',
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		customer_name varchar(191) DEFAULT '',
		phone varchar(50) DEFAULT '',
		address text,
		serial varchar(191) DEFAULT '',
		model varchar(191) DEFAULT '',
		issue text,
		photos text,
		status varchar(30) DEFAULT 'submitted',
		job_sheet_no varchar(30) NOT NULL DEFAULT '',
		courier_name varchar(60) NOT NULL DEFAULT '',
		courier_tracking varchar(80) NOT NULL DEFAULT '',
		courier_at datetime NULL,
		erp_missing_since datetime NULL,
		admin_note text,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY ref_idx (ref),
		KEY user_idx (user_id),
		KEY status_idx (status)
	) $charset;" );

	// v8: push notifications — the FCM device token travels with the login
	// token, so it is revoked/expired together with the session it belongs to.
	$token_cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM $tokens" );
	if ( ! in_array( 'fcm_token', $token_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $tokens ADD COLUMN fcm_token varchar(255) NOT NULL DEFAULT ''" );
	}
	if ( ! in_array( 'fcm_lang', $token_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $tokens ADD COLUMN fcm_lang varchar(5) NOT NULL DEFAULT 'bn'" );
	}

	// v6: in-app notification centre. Notices are either personal (user_id>0)
	// or broadcast (user_id=0, optionally targeted to one model). Read/dismiss
	// state is per user. dedup_key stops double-materialisation (maintenance
	// reminders are generated on demand per user+serial+offset).
	$notices = $wpdb->prefix . 'aun_app_notices';
	dbDelta( "CREATE TABLE IF NOT EXISTS $notices (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		model_id bigint(20) unsigned NOT NULL DEFAULT 0,
		type varchar(20) NOT NULL DEFAULT 'general',
		title varchar(191) NOT NULL DEFAULT '',
		title_bn varchar(191) NOT NULL DEFAULT '',
		body text,
		body_bn text,
		data text,
		dedup_key varchar(64) DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY dedup_idx (dedup_key),
		KEY user_idx (user_id),
		KEY model_idx (model_id),
		KEY created_idx (created_at)
	) $charset;" );

	$notice_state = $wpdb->prefix . 'aun_app_notice_state';
	dbDelta( "CREATE TABLE IF NOT EXISTS $notice_state (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		notice_id bigint(20) unsigned NOT NULL,
		read_at datetime DEFAULT NULL,
		dismissed tinyint(1) NOT NULL DEFAULT 0,
		completed_at datetime DEFAULT NULL,
		snoozed_until datetime DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY user_notice_idx (user_id, notice_id),
		KEY user_idx (user_id)
	) $charset;" );

	// v15: referral programme. Two tables: one code per customer, and one row
	// per claim so a reward can be traced back to the exact order that earned it
	// (and revoked if that order is later refunded).
	$referrals = $wpdb->prefix . 'aun_app_referrals';
	dbDelta( "CREATE TABLE IF NOT EXISTS $referrals (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		code varchar(32) NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY user_idx (user_id),
		UNIQUE KEY code_idx (code)
	) $charset;" );

	$referral_claims = $wpdb->prefix . 'aun_app_referral_claims';
	dbDelta( "CREATE TABLE IF NOT EXISTS $referral_claims (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		referrer_user_id bigint(20) unsigned NOT NULL,
		referred_user_id bigint(20) unsigned NOT NULL,
		referred_phone varchar(32) NOT NULL,
		code varchar(32) NOT NULL,
		friend_coupon varchar(64) DEFAULT NULL,
		reward_coupon varchar(64) DEFAULT NULL,
		order_id bigint(20) unsigned NOT NULL DEFAULT 0,
		status varchar(16) NOT NULL DEFAULT 'pending',
		/* WHY a claim was revoked: 'reversed' (the order was refunded or
		   cancelled) or 'ineligible' (the buyer failed a rule). Only the first
		   can be undone: an admin who mis-clicks Cancelled and then corrects it
		   must not cost the referrer a reward they earned. */
		revoke_reason varchar(20) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		rewarded_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY referrer_idx (referrer_user_id),
		KEY status_idx (status),
		/* One claim per phone number, EVER. Deleting and recreating an account
		   must not buy a second welcome discount. */
		UNIQUE KEY phone_idx (referred_phone)
	) $charset;" );

	// v6: bug reports from the app.
	$feedback = $wpdb->prefix . 'aun_app_feedback';
	dbDelta( "CREATE TABLE IF NOT EXISTS $feedback (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		customer_name varchar(191) DEFAULT '',
		phone varchar(50) DEFAULT '',
		app_version varchar(30) DEFAULT '',
		device_info varchar(191) DEFAULT '',
		message text,
		status varchar(20) NOT NULL DEFAULT 'new',
		image_url varchar(500) NOT NULL DEFAULT '',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY status_idx (status),
		KEY user_idx (user_id)
	) $charset;" );
	// v12: optional screenshot on a bug report.
	$fb_cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM $feedback" );
	if ( ! in_array( 'image_url', $fb_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $feedback ADD COLUMN image_url varchar(500) NOT NULL DEFAULT ''" );
	}

	// v14: maintenance reminders become actionable — "mark as done" / "remind
	// me later" — instead of a notification-centre item you can only dismiss.
	$notice_state_cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM $notice_state" );
	if ( ! in_array( 'completed_at', $notice_state_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $notice_state ADD COLUMN completed_at datetime DEFAULT NULL" );
	}
	if ( ! in_array( 'snoozed_until', $notice_state_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $notice_state ADD COLUMN snoozed_until datetime DEFAULT NULL" );
	}

	// v17: the app build behind each FCM token. Android silently refuses to
	// display a notification whose channel the app never created, so a push must
	// not name a channel newer than the build receiving it.
	$tok_cols_v17 = (array) $wpdb->get_col( "SHOW COLUMNS FROM $tokens" );
	if ( ! in_array( 'fcm_build', $tok_cols_v17, true ) ) {
		$wpdb->query( "ALTER TABLE $tokens ADD COLUMN fcm_build int NOT NULL DEFAULT 0" );
	}

	// v16: why a referral claim was revoked, so an admin's mis-click on
	// "Cancelled" can be undone while a genuine rule failure cannot.
	// NOTE: no AFTER clause — the SQLite bench rejects it.
	$claim_cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM $referral_claims" );
	if ( ! in_array( 'revoke_reason', $claim_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $referral_claims ADD COLUMN revoke_reason varchar(20) NOT NULL DEFAULT ''" );
	}

	// v6: warranty duration exactly as the ERP product defines it (what the
	// sales invoice prints). NULL duration → legacy 12-month fallback.
	$device_cols_v6 = (array) $wpdb->get_col( "SHOW COLUMNS FROM $devices" );
	if ( ! in_array( 'warranty_duration', $device_cols_v6, true ) ) {
		$wpdb->query( "ALTER TABLE $devices ADD COLUMN warranty_duration int DEFAULT NULL" );
	}
	if ( ! in_array( 'warranty_unit', $device_cols_v6, true ) ) {
		$wpdb->query( "ALTER TABLE $devices ADD COLUMN warranty_unit varchar(10) DEFAULT ''" );
	}

	// v7: daily re-verification against the ERP. A direct link exists ONLY
	// because the ERP says the customer bought that unit — so when the sale is
	// returned or deleted the link must go too (see AUN_App_Warranty::verify_*).
	// verify_misses gives "sale not found" a 2-strike grace so a transient ERP
	// hiccup can never wipe a real customer's device.
	if ( ! in_array( 'verified_at', $device_cols_v6, true ) ) {
		$wpdb->query( "ALTER TABLE $devices ADD COLUMN verified_at datetime DEFAULT NULL" );
	}
	if ( ! in_array( 'verify_misses', $device_cols_v6, true ) ) {
		$wpdb->query( "ALTER TABLE $devices ADD COLUMN verify_misses int NOT NULL DEFAULT 0" );
	}

	// v5: link an app repair request to the UltimatePOS repair job sheet the
	// service centre creates when the projector physically arrives. Once linked
	// the app shows the live ERP repair status (same source as the website
	// tracker) instead of the app-side status.
	// NOTE: membership check against the full column list — "SHOW COLUMNS ...
	// LIKE" is not honoured by the SQLite dev bench; no "AFTER" either.
	$repair_cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM $repairs" );
	if ( ! in_array( 'job_sheet_no', $repair_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $repairs ADD COLUMN job_sheet_no varchar(30) NOT NULL DEFAULT ''" );
	}
	// v11: last ERP repair status we notified the customer about, so the repair
	// status poll only pushes on a real change (not every run).
	if ( ! in_array( 'last_erp_status', $repair_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $repairs ADD COLUMN last_erp_status varchar(60) NOT NULL DEFAULT ''" );
	}

	// v13: per-item "downloadable in app" flag (admin can hide a file whose cloud
	// link the app can't fetch, without deleting the content row).
	$content_cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM $content" );
	if ( ! in_array( 'app_downloadable', $content_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $content ADD COLUMN app_downloadable tinyint(1) NOT NULL DEFAULT 1" );
	}

	// v19: first-party analytics. Small, append-only, purged after 180 days.
	// `event_day_idx` is what makes the dashboard's GROUP BY cheap on a table
	// that only ever grows between purges.
	$events = $wpdb->prefix . 'aun_app_events';
	dbDelta( "CREATE TABLE $events (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		event varchar(40) NOT NULL DEFAULT '',
		params text,
		app_version varchar(20) NOT NULL DEFAULT '',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY event_day_idx (event, created_at),
		KEY created_idx (created_at)
	) $charset;" );

	// v20: the customer's own courier tracking number. Three columns rather
	// than one free-text note, because the number is the thing staff will
	// search for when a parcel goes missing and a note cannot be searched.
	$repair_cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM $repairs" );
	if ( ! in_array( 'courier_tracking', $repair_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $repairs ADD COLUMN courier_name varchar(60) NOT NULL DEFAULT ''" );
		$wpdb->query( "ALTER TABLE $repairs ADD COLUMN courier_tracking varchar(80) NOT NULL DEFAULT ''" );
		$wpdb->query( "ALTER TABLE $repairs ADD COLUMN courier_at datetime NULL" );
	}

	// v21: when the linked job sheet first read as a definite "not found".
	// ⚠️ Column added separately from the v20 block — the v20 guard checks for
	// courier_tracking, so a site already on v20 would skip this one entirely
	// if it were bundled in there.
	if ( ! in_array( 'erp_missing_since', $repair_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $repairs ADD COLUMN erp_missing_since datetime NULL" );
	}

	// v18: "What's new" for a firmware release. Separate from `description`
	// (the installation steps) because they answer different questions asked at
	// different moments — "should I install this?" comes before "how?", and
	// burying the reason under the instructions is why nobody reads it.
	if ( ! in_array( 'changelog', $content_cols, true ) ) {
		$wpdb->query( "ALTER TABLE $content ADD COLUMN changelog text NULL AFTER description" );
	}

	if ( false === get_option( AUN_APP_API_OPTION, false ) ) {
		add_option( AUN_APP_API_OPTION, aun_app_api_default_options() );
	}

	// Device re-verification sweep (see aun_app_api_verify_devices_cron).
	if ( ! wp_next_scheduled( 'aun_app_verify_devices' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'aun_app_verify_devices' );
	}

	// Daily notice run: materialises due dust-filter reminders for every app
	// user and pushes the new ones — so the month-2 reminder arrives as a
	// PUSH on its due date even if the customer never opens the app.
	if ( ! wp_next_scheduled( 'aun_app_daily_notices' ) ) {
		wp_schedule_event( time() + 600, 'daily', 'aun_app_daily_notices' );
	}

	// Support-ticket staff-reply poll (osTicket has no webhooks) — every 10
	// minutes a reply becomes an in-app notice + push. No-op until the
	// bridge is configured in Settings.
	if ( ! wp_next_scheduled( 'aun_app_tickets_poll' ) ) {
		wp_schedule_event( time() + 300, 'aun_app_ten_minutes', 'aun_app_tickets_poll' );
	}

	// Keep the "what to watch" store warm OFF the request path — building it
	// costs ~2 TMDB calls per title, far too slow for a customer's request.
	if ( ! wp_next_scheduled( 'aun_app_watch_warm' ) ) {
		wp_schedule_event( time() + 120, 'twicedaily', 'aun_app_watch_warm' );
	}

	// Same idea for the Chorki feed, and it is the SAFETY NET rather than the
	// main mechanism: AUN_App_Chorki::picks() already queues a rebuild as soon
	// as its 6-hour TTL lapses, so on a site with any traffic the list is never
	// more than ~6 h behind Chorki. This twice-daily run only covers the case
	// where nobody opens the app at all.
	if ( ! wp_next_scheduled( 'aun_app_chorki_warm' ) ) {
		wp_schedule_event( time() + 180, 'twicedaily', 'aun_app_chorki_warm' );
	}

	// Repair-status poll: the ERP has no webhooks, so every 10 minutes we check
	// active job sheets for a status change and push it to the owner. No-op
	// until the ERP repair API is configured.
	// Analytics never outlives its usefulness: the questions are all about the
	// last month, so rows past the retention window are deleted daily.
	if ( ! wp_next_scheduled( 'aun_app_events_purge' ) ) {
		wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', 'aun_app_events_purge' );
	}

	if ( ! wp_next_scheduled( 'aun_app_repair_poll' ) ) {
		wp_schedule_event( time() + 420, 'aun_app_ten_minutes', 'aun_app_repair_poll' );
	}

	update_option( 'aun_app_api_db', AUN_APP_API_DB_VERSION );
}
register_activation_hook( __FILE__, 'aun_app_api_activate' );

/**
 * Deactivation: stop the sweep (re-scheduled on activation).
 */
function aun_app_api_deactivate() {
	foreach ( array( 'aun_app_verify_devices', 'aun_app_daily_notices', 'aun_app_tickets_poll', 'aun_app_repair_poll', 'aun_app_chorki_warm' ) as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}
}
register_deactivation_hook( __FILE__, 'aun_app_api_deactivate' );

/**
 * Hourly sweep: re-check a small batch of direct-purchase links against the
 * ERP, so each device is verified about once a day. Returned/deleted sales
 * unlink the device and free its serial.
 */
function aun_app_api_verify_devices_cron() {
	$stats           = AUN_App_Warranty::verify_devices_batch();
	$stats['ran_at'] = current_time( 'mysql' );
	$stats['manual'] = 0;
	// Last-run stats are shown on the AUN App dashboard so the sweep's health
	// is visible (was completely silent before).
	update_option( 'aun_app_last_sweep', $stats, false );
}
add_action( 'aun_app_verify_devices', 'aun_app_api_verify_devices_cron' );

/**
 * Daily: materialise due maintenance reminders for every active app user,
 * WITH push (the in-app on-demand path materialises silently — the customer
 * is already looking at the app there).
 */
function aun_app_api_daily_notices_cron() {
	AUN_App_Notices::daily_maintenance_run();
}
add_action( 'aun_app_daily_notices', 'aun_app_api_daily_notices_cron' );

/** Ten-minute interval for the support-ticket reply poll. */
function aun_app_api_cron_schedules( $schedules ) {
	$schedules['aun_app_ten_minutes'] = array(
		'interval' => 10 * MINUTE_IN_SECONDS,
		'display'  => 'Every 10 minutes (AUN App)',
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'aun_app_api_cron_schedules' );

/** Poll osTicket for staff replies → notification centre + push. */
function aun_app_api_tickets_poll_cron() {
	AUN_App_Tickets::poll_replies();
	// Same trip out to the bridge refreshes the staff queue snapshot the admin
	// bar renders from, so that indicator costs wp-admin nothing.
	AUN_App_Tickets::refresh_queue();
}
add_action( 'aun_app_tickets_poll', 'aun_app_api_tickets_poll_cron' );

/*
 * The same poll, on its own hook, so opening the notification centre can ask
 * for a fresh check WITHOUT waiting for it.
 *
 * ⚠️ It must be a DIFFERENT hook name from the recurring one: with identical
 * hook + args, wp_schedule_single_event() treats anything already scheduled
 * within 10 minutes as a duplicate and silently drops the request — which for
 * a 10-minute recurring event is almost always.
 */
add_action( 'aun_app_tickets_poll_now', 'aun_app_api_tickets_poll_cron' );

/** Poll the ERP for repair-status changes → notification centre + push. */
function aun_app_api_repair_poll_cron() {
	AUN_App_Services::poll_repair_statuses();
}
add_action( 'aun_app_repair_poll', 'aun_app_api_repair_poll_cron' );

add_action( 'aun_app_events_purge', array( 'AUN_App_Events', 'purge' ) );

// Deleting an account erases what it recorded too — otherwise "delete my
// account" would leave a trail of that person's behaviour behind.
add_action( 'aun_app_account_deleted', function ( $user_id ) {
	if ( class_exists( 'AUN_App_Events' ) ) {
		AUN_App_Events::delete_for_user( $user_id );
	}
}, 10, 1 );

// Spare-parts requests live in their own plugin and only ever spoke to the
// customer by SMS. This bridges its status changes into the app's notification
// centre + push — above all "quote sent", which the customer must answer.
add_action( 'aun_sp_status_changed', array( 'AUN_App_Services', 'on_parts_status_changed' ), 10, 2 );

// Spare parts 0.31.0 chases an unanswered quote by SMS on two days before it
// lapses. The app is where the customer can answer with one tap, so each nudge
// is mirrored there too — and NOT on any other day, or the app would be adding
// a chase the ladder was designed not to have.
add_action( 'aun_sp_quote_reminder', array( 'AUN_App_Services', 'on_parts_quote_reminder' ), 10, 2 );

// The planner catalogue is cached for hours, so editing a projector's throw
// ratio would otherwise not reach the app until the cache expired — long enough
// to look broken while you're correcting a figure.
add_action( 'woocommerce_update_product', array( 'AUN_App_Projectors', 'flush' ) );
add_action( 'woocommerce_new_product', array( 'AUN_App_Projectors', 'flush' ) );

// Referral rewards are paid ONLY on completion — the point at which goods have
// actually shipped — and clawed back if the order is later reversed. Rewarding
// any earlier is what makes referral schemes farmable.
// A referral coupon belongs to the OTP-verified number it was issued to.
// Without this it is a bearer token: the code is single-use and expiring, but
// whoever types it first gets the discount, including someone the friend
// passed it on to. Two hooks by design — the filter judges only when a phone
// is already known (so applying the code early still works), and checkout
// validation is the gate that actually stops the order.
// Applying stays INSTANT. The ownership check is not a validity rule — it was
// hooked to `woocommerce_coupon_is_valid` at first, which runs on every cart
// recalculation and is consulted at two moments with different data. That is
// what produced "Coupon applied successfully" above a ৳0 discount, and a
// refusal that stuck through refreshes. Applying now only ANNOUNCES the
// condition; it is enforced once, at placement, where the number is known.
add_action( 'woocommerce_applied_coupon', array( 'AUN_App_Referrals', 'on_applied_coupon' ), 10, 1 );

// Earned rewards stack with EACH OTHER, and with nothing else. Rewards are
// issued one per friend and are individual-use; without these two filters a
// referrer who brought five customers would need five separate orders to spend
// what they earned.
add_filter( 'woocommerce_apply_individual_use_coupon', array( 'AUN_App_Referrals', 'keep_rewards_together' ), 10, 2 );
add_filter( 'woocommerce_apply_with_individual_use_coupon', array( 'AUN_App_Referrals', 'allow_reward_stacking' ), 10, 3 );

// A reward worth more than the cart is accepted and the remainder is destroyed
// — WooCommerce caps the discount at the subtotal and still marks the coupon
// used. Say so, with the amount, and let the customer decide.
add_action( 'woocommerce_before_cart', array( 'AUN_App_Referrals', 'excess_reward_notice' ) );
add_action( 'woocommerce_before_checkout_form', array( 'AUN_App_Referrals', 'excess_reward_notice' ) );
add_action( 'woocommerce_after_checkout_validation', array( 'AUN_App_Referrals', 'on_checkout_validation' ), 10, 2 );
// Backstop for the block checkout / Store API, which never fire the hook above.
add_action( 'woocommerce_checkout_create_order', array( 'AUN_App_Referrals', 'on_create_order' ), 10, 2 );

// Not `woocommerce_order_status_completed` any more: a store with a shipment
// plugin (AST Pro adds wc-shipped / wc-delivered) needs to pay on DELIVERED,
// or a refused cash-on-delivery parcel pays a reward for goods that came
// straight back. The paying status is an admin setting, so this listens to
// every transition and asks whether the new one is the one that pays.
add_action( 'woocommerce_order_status_changed', 'aun_app_api_referral_status_changed', 10, 3 );

/**
 * Route an order-status change to the referral programme.
 *
 * @param int    $order_id Order.
 * @param string $from     Previous status (bare slug).
 * @param string $to       New status (bare slug).
 */
function aun_app_api_referral_status_changed( $order_id, $from, $to ) {
	$to = AUN_App_Referrals::clean_status( $to );

	if ( in_array( $to, AUN_App_Referrals::payout_statuses(), true ) ) {
		AUN_App_Referrals::on_order_completed( $order_id );
		return;
	}

	if ( in_array( $to, array( 'refunded', 'cancelled', 'failed' ), true ) ) {
		AUN_App_Referrals::on_order_reversed( $order_id );
	}
}

// A partial refund never changes the order status, so it would otherwise be
// invisible here. It is NOT treated as a reversal on its own: a small goodwill
// refund must not cost the referrer a reward they earned. Only a refund that
// drags what was actually kept below the qualifying minimum counts.
add_action( 'woocommerce_order_refunded', array( 'AUN_App_Referrals', 'on_partial_refund' ), 10, 2 );

/**
 * Upgrade path for sites where the plugin was activated before v1.1
 * (activation hooks do not re-run on plugin file updates).
 */
function aun_app_api_maybe_upgrade() {
	if ( get_option( 'aun_app_api_db', '0' ) !== AUN_APP_API_DB_VERSION ) {
		aun_app_api_activate();
	}
}
add_action( 'plugins_loaded', 'aun_app_api_maybe_upgrade', 24 );

/**
 * Boot: REST routes always; admin screens in wp-admin.
 */
function aun_app_api_boot() {
	$rest = new AUN_App_REST();
	add_action( 'rest_api_init', array( $rest, 'register_routes' ) );
	// Declare which public routes an edge cache may keep, and for how long.
	// Without this WordPress sends max-age=0 and a Cloudflare rule set to
	// "respect origin" caches nothing — see public_cache_headers().
	add_filter( 'rest_post_dispatch', array( $rest, 'public_cache_headers' ), 10, 3 );

	if ( is_admin() ) {
		$admin = new AUN_App_Admin();
		$admin->init();
	}
}
add_action( 'plugins_loaded', 'aun_app_api_boot', 25 );

/**
 * Self-heal the support-ticket reply poll schedule. Activation schedules it,
 * but an install where activation didn't re-run (or WP-Cron lost the event)
 * would silently stop turning staff replies into notifications + push. Cheap to
 * re-check on init; only schedules when tickets are actually configured.
 */
/**
 * Live bug-report counter in the WordPress admin bar (front-end AND wp-admin),
 * so an admin instantly notices new in-app reports. Links to the Bug Reports
 * page. Count cached 60s; the cache is cleared the moment a report arrives.
 *
 * @param WP_Admin_Bar $bar Admin bar.
 */
function aun_app_api_admin_bar_counter( $bar ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$count = get_transient( 'aun_app_new_feedback_count' );
	if ( false === $count ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aun_app_feedback';
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'new'" );
		set_transient( 'aun_app_new_feedback_count', $count, MINUTE_IN_SECONDS );
	}
	if ( (int) $count < 1 ) {
		return;
	}
	$bar->add_node( array(
		'id'    => 'aun-app-bugs',
		'title' => '<span class="ab-icon dashicons dashicons-warning" style="top:2px;"></span>'
			. '<span class="ab-label">' . (int) $count . '</span>',
		'href'  => admin_url( 'admin.php?page=aun-app-feedback' ),
		'meta'  => array( 'title' => (int) $count . ' new bug report(s) from the app' ),
	) );
}
add_action( 'admin_bar_menu', 'aun_app_api_admin_bar_counter', 90 );

/**
 * Live counter for repair requests still waiting on YOUR decision.
 *
 * A "send for repair" from the app sits at `submitted` until an admin approves
 * or rejects it, and until then the customer is waiting — they've been told to
 * expect an answer before shipping anything. Nothing surfaced that anywhere in
 * wp-admin, so a request could sit unseen for days. Same treatment the bug
 * reports get: a persistent indicator on every WP page that disappears by
 * itself once the queue is empty.
 *
 * Count cached 60s, and the cache is cleared the moment a request arrives or is
 * decided (see AUN_App_Services + the Repairs screen).
 *
 * @param WP_Admin_Bar $bar Admin bar.
 */
function aun_app_api_admin_bar_repairs( $bar ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$count = get_transient( 'aun_app_pending_repairs_count' );
	if ( false === $count ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aun_app_repairs';
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", 'submitted' ) );
		set_transient( 'aun_app_pending_repairs_count', $count, MINUTE_IN_SECONDS );
	}
	if ( (int) $count < 1 ) {
		return;
	}
	$bar->add_node( array(
		'id'    => 'aun-app-repairs',
		'title' => '<span class="ab-icon dashicons dashicons-hammer" style="top:2px;"></span>'
			. '<span class="ab-label">' . (int) $count . '</span>',
		'href'  => admin_url( 'admin.php?page=aun-app-repairs' ),
		'meta'  => array(
			'title' => sprintf(
				/* translators: %d: number of repair requests awaiting approval. */
				_n( '%d repair request awaiting your approval', '%d repair requests awaiting your approval', (int) $count, 'aun-app' ),
				(int) $count
			),
		),
	) );
}
add_action( 'admin_bar_menu', 'aun_app_api_admin_bar_repairs', 90 );

/**
 * Support-ticket queue in the admin bar.
 *
 * Nobody logs into the support centre every day, so a customer reply can sit
 * for days unnoticed. This shows a persistent indicator on every WP page:
 *   • RED + pulse   — customers are waiting for a reply from us
 *   • NEUTRAL       — tickets are open but the ball is in the customer's court
 *   • nothing       — no open tickets
 * It disappears by itself the moment an agent replies (osTicket flips
 * `isanswered`), which is exactly the behaviour asked for.
 *
 * Renders ONLY from the cached snapshot — never calls the bridge inline, so it
 * cannot slow down or hang wp-admin. See AUN_App_Tickets::refresh_queue().
 */
function aun_app_api_admin_bar_tickets( $bar ) {
	if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'AUN_App_Tickets' )
		|| ! AUN_App_Tickets::configured() ) {
		return;
	}

	$q       = AUN_App_Tickets::queue();
	$open    = (int) ( $q['open'] ?? 0 );
	$waiting = (int) ( $q['waiting'] ?? 0 );
	$stale   = ( time() - (int) ( $q['checked'] ?? 0 ) ) > 30 * MINUTE_IN_SECONDS;

	if ( $open < 1 && ! $stale ) {
		return; // nothing open, and we know it's current
	}
	if ( empty( $q ) ) {
		return; // never fetched yet — the cron will fill it within 10 minutes
	}

	$waiting_mode = $waiting > 0;
	$label        = $waiting_mode ? (string) $waiting : (string) $open;
	$title        = $waiting_mode
		? sprintf( _n( '%d customer waiting for a reply', '%d customers waiting for a reply', $waiting, 'aun-app' ), $waiting )
		: sprintf( _n( '%d open ticket', '%d open tickets', $open, 'aun-app' ), $open );

	if ( ! empty( $q['error'] ) ) {
		$title .= ' — last check failed, showing the previous state';
	}

	$bar->add_node( array(
		'id'    => 'aun-app-tickets',
		'title' => '<span class="ab-icon dashicons dashicons-format-chat" style="top:2px;"></span>'
			. '<span class="ab-label">' . esc_html( $label ) . '</span>',
		'href'  => AUN_App_Tickets::agent_url(),
		'meta'  => array(
			'title'  => $title,
			'target' => '_blank',
			'class'  => $waiting_mode ? 'aun-tickets-waiting' : 'aun-tickets-idle',
		),
	) );

	// Sub-menu: who is actually waiting, oldest first, straight into the agent
	// panel. Waiting tickets are listed before answered ones by the bridge.
	$rows = (array) ( $q['tickets'] ?? array() );
	$n    = 0;
	foreach ( $rows as $t ) {
		if ( $n >= 8 ) {
			break;
		}
		$subject = (string) ( $t['subject'] ?? '' );
		if ( '' === $subject ) {
			$subject = '#' . (string) ( $t['number'] ?? '' );
		}
		if ( function_exists( 'mb_strimwidth' ) ) {
			$subject = mb_strimwidth( $subject, 0, 46, '…', 'UTF-8' );
		}
		$ago = '';
		if ( ! empty( $t['updated'] ) ) {
			$ts = strtotime( (string) $t['updated'] . ' UTC' );
			if ( $ts ) {
				$ago = ' · ' . human_time_diff( $ts ) . ' ago';
			}
		}
		$bar->add_node( array(
			'id'     => 'aun-app-ticket-' . (int) ( $t['ticket_id'] ?? $n ),
			'parent' => 'aun-app-tickets',
			'title'  => ( ! empty( $t['waiting'] ) ? '<span style="color:#ff8b8b">●</span> ' : '<span style="color:#68de7c">●</span> ' )
				. esc_html( $subject ) . '<span style="opacity:.65">' . esc_html( $ago ) . '</span>',
			'href'   => AUN_App_Tickets::agent_url( (int) ( $t['ticket_id'] ?? 0 ) ),
			'meta'   => array( 'target' => '_blank' ),
		) );
		$n++;
	}

	$bar->add_node( array(
		'id'     => 'aun-app-tickets-all',
		'parent' => 'aun-app-tickets',
		'title'  => '↗ ' . esc_html__( 'Open the support centre', 'aun-app' ),
		'href'   => AUN_App_Tickets::agent_url(),
		'meta'   => array( 'target' => '_blank' ),
	) );
}
add_action( 'admin_bar_menu', 'aun_app_api_admin_bar_tickets', 91 );

/** Red pulse for "someone is waiting on us". Front end and wp-admin. */
function aun_app_api_admin_bar_styles() {
	if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// data-no-optimize/no-minify: WP Rocket must leave this alone.
	echo '<style data-no-optimize="1" data-no-minify="1">
	#wpadminbar .aun-tickets-waiting > .ab-item { background:#8b1b1b !important; }
	#wpadminbar .aun-tickets-waiting .ab-icon:before { color:#ffb3b3 !important; }
	#wpadminbar .aun-tickets-waiting .ab-label { color:#fff !important; font-weight:700; }
	#wpadminbar .aun-tickets-waiting > .ab-item:before { animation:aunTicketPulse 2s ease-in-out infinite; }
	@keyframes aunTicketPulse { 0%,100% { opacity:1 } 50% { opacity:.45 } }
	@media (prefers-reduced-motion: reduce) {
		#wpadminbar .aun-tickets-waiting > .ab-item:before { animation:none; }
	}
	#wpadminbar #wp-admin-bar-aun-app-tickets-default { min-width:290px; }
	</style>';
}
/**
 * Render checkout as an app screen when the app is the one showing it.
 *
 * The app opens WooCommerce's own order-pay page inside a WebView, so the
 * theme's header, menu and footer would appear inside the payment screen —
 * ugly, and worse, a way to wander off mid-transaction into the shop.
 *
 * Triggered by `?aun_app=1`, which `/parts/pay` appends. A cookie carries it
 * across the gateway round trip, because the customer comes BACK from
 * SSLCommerz to a fresh page load that has no query string of ours.
 *
 * Presentation only: nothing here touches prices, the order, or the gateway.
 */
function aun_app_api_checkout_chrome() {
	$flagged = ! empty( $_GET['aun_app'] );
	if ( $flagged && ! headers_sent() ) {
		setcookie( 'aun_app_checkout', '1', time() + HOUR_IN_SECONDS, '/' );
	}
	if ( ! $flagged && empty( $_COOKIE['aun_app_checkout'] ) ) {
		return;
	}
	// Only ever on the pages the payment flow actually passes through.
	if ( function_exists( 'is_checkout' ) && ! is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}

	// The overlay is printed at the START of <body> (see below) rather than
	// after load, so the customer never sees the checkout form, the terms
	// checkbox or WooCommerce's intermediate redirect page flash past. Those
	// pages are real and must keep working — they are simply not something the
	// customer asked to look at. Hidden behind an opaque layer, not removed.
	$GLOBALS['aun_app_checkout_overlay'] = function_exists( 'is_wc_endpoint_url' )
		&& is_wc_endpoint_url( 'order-pay' );

	echo '<style data-no-optimize="1" id="aun-app-checkout">
	header, .header, #masthead, .header-wrapper, #top-bar, .top-bar,
	footer, .footer, #footer, .footer-wrapper, .absolute-footer,
	.mobile-nav, #main-menu, .off-canvas, .breadcrumbs, .page-title,
	#wpadminbar, .back-to-top { display: none !important; }
	body { padding-top: 0 !important; background: #fff !important; }
	.page-wrapper, #main, .container { padding-top: 0 !important; margin-top: 0 !important; }
	#aun-app-redirect { position: fixed; inset: 0; z-index: 99999; background: #fff;
	  display: flex; align-items: center; justify-content: center; flex-direction: column;
	  gap: 14px; font: 400 15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
	  color: #4b5563; }
	#aun-app-redirect .sp { width: 30px; height: 30px; border: 3px solid #e5e7eb;
	  border-top-color: #0188fe; border-radius: 50%; animation: aunspin .8s linear infinite; }
	@keyframes aunspin { to { transform: rotate(360deg); } }
	</style>';

	// Straight to the gateway when there is only one.
	//
	// WooCommerce's "Pay for order" page is a method chooser, and the customer
	// has already chosen — they tapped "Pay online securely" in the app. With a
	// single method left (the spare-parts plugin removes cash-on-delivery from
	// its own orders) that page is a pointless extra tap on a screen that looks
	// like the website they were trying not to visit.
	//
	// Only auto-submits when there is EXACTLY ONE method. With two or more the
	// chooser is correct and stays, because picking for them would be guessing.
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
		echo '<script data-no-optimize="1">
		(function () {
		  var KEY = "aunAutoPayTried";

		  function go() {
		    var form = document.querySelector("form#order_review");
		    if (!form) { return; }

		    // ── Loop guard: auto-submit AT MOST ONCE per order, ever. ──
		    // Without this, anything that makes the submit fail — an unticked
		    // terms box, a declined card, a gateway timeout — reloads the page,
		    // which re-runs this script, which submits again. The customer sees
		    // the checkout flashing forever and can never read the error that
		    // would tell them what is wrong.
		    var key = location.pathname;
		    try {
		      if (sessionStorage.getItem(KEY) === key) { return; }
		      sessionStorage.setItem(KEY, key);
		    } catch (e) { return; }  // no sessionStorage = no safe retry guard

		    // If WooCommerce is already complaining, the customer must SEE it.
		    if (document.querySelector(".woocommerce-error, .woocommerce-NoticeGroup, .wc-block-components-notice-banner.is-error")) { return; }

		    var methods = form.querySelectorAll("input[name=payment_method]");
		    if (methods.length !== 1) { return; }   // let them choose
		    methods[0].checked = true;

		    // The terms checkbox. WooCommerce refuses the order without it, and
		    // an auto-submitted form has no one to tick it — which is what put
		    // the page in a reload loop. The app states this consent on the Pay
		    // button before we ever get here.
		    var terms = form.querySelector("input#terms");
		    if (terms && !terms.checked) {
		      terms.checked = true;
		      terms.dispatchEvent(new Event("change", { bubbles: true }));
		    }

		    // The overlay is already on screen (printed at the top of <body>),
		    // so there is nothing to show here — only something to take away if
		    // this goes wrong.

		    // If the gateway has not taken over within a few seconds, the submit
		    // failed. Drop the overlay so the customer can see the page rather
		    // than staring at a spinner that will never finish.
		    setTimeout(function () {
		      var el = document.getElementById("aun-app-redirect");
		      if (el) { el.remove(); }
		    }, 8000);

		    // A tick, so any gateway script that binds to the form is ready.
		    setTimeout(function () {
		      var btn = form.querySelector("#place_order, button[type=submit]");
		      if (btn) { btn.click(); } else { form.submit(); }
		    }, 350);
		  }

		  if (document.readyState === "complete") { go(); }
		  else { window.addEventListener("load", go); }
		})();
		</script>';
	}
}
add_action( 'wp_head', 'aun_app_api_checkout_chrome', 99 );

/**
 * The "Opening secure payment…" cover, printed as the FIRST thing in <body>.
 *
 * Position matters. Appending it after `load` meant the customer watched the
 * checkout form, the terms checkbox and WooCommerce's intermediate redirect
 * page appear and disappear — three flashes of a website they were trying not
 * to visit. Printed here it is on screen before anything else paints, and it
 * stays up across each redirect because every one of those pages runs this too.
 *
 * It only ever COVERS the page. Nothing underneath is removed or disabled, and
 * the script above takes the cover away after 8 seconds if the submit failed,
 * so a customer is never trapped behind a spinner.
 */
function aun_app_api_checkout_cover() {
	if ( empty( $GLOBALS['aun_app_checkout_overlay'] ) ) {
		return;
	}
	echo '<div id="aun-app-redirect"><div class="sp"></div><div>'
		. esc_html__( 'Opening secure payment…', 'aun-app-api' )
		. '</div></div>';
}
add_action( 'wp_body_open', 'aun_app_api_checkout_cover' );

/**
 * Where SSLCommerz sends the customer (and its own server) when a payment
 * finishes, fails, is cancelled, or is confirmed out of band.
 *
 * Hooked on `template_redirect` at the front of the site rather than as a REST
 * route, because the gateway POSTs a form here from the customer's browser and
 * a plain URL is the least that can go wrong.
 *
 * **Nothing is trusted.** A redirect only tells us the customer's browser came
 * back; the money is confirmed by calling the gateway ourselves. The page shown
 * afterwards is a bare marker the app's WebView watches for — it never claims
 * an outcome the server has not verified.
 */
function aun_app_api_sslcommerz_callback() {
	$what = isset( $_GET['aun_sslc'] ) ? sanitize_key( wp_unslash( $_GET['aun_sslc'] ) ) : '';
	if ( '' === $what ) {
		return;
	}

	$paid = false;

	// The second landing, reached by the redirect at the bottom of this page.
	//
	// The gateway returns the customer by POSTing a form, and Android's WebView
	// does not report POST navigations to the app at all. So after validating,
	// this page bounces itself to the same URL as a plain GET — a navigation
	// every WebView reports, on every Android version, without the app needing
	// to catch the subtler signals. `aun_final` says "already validated, just
	// show the page", so the bounce cannot re-run the check without a val_id
	// and turn a successful payment into a failure page.
	$final = ! empty( $_GET['aun_final'] );
	if ( $final ) {
		$paid = ( 'success' === $what );
	}

	if ( ! $final && in_array( $what, array( 'success', 'ipn' ), true ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the gateway posts here; authenticity comes from validate(), not a nonce.
		$val_id = isset( $_POST['val_id'] ) ? sanitize_text_field( wp_unslash( $_POST['val_id'] ) ) : '';
		if ( '' === $val_id && isset( $_GET['val_id'] ) ) {
			$val_id = sanitize_text_field( wp_unslash( $_GET['val_id'] ) );
		}

		$data = AUN_App_SSLCommerz::validate( $val_id );
		if ( is_wp_error( $data ) ) {
			error_log( 'AUN APP API: SSLCommerz validation failed — ' . $data->get_error_message() );
		} else {
			$ok = AUN_App_SSLCommerz::settle( $data );
			if ( is_wp_error( $ok ) ) {
				error_log( 'AUN APP API: SSLCommerz settle refused — ' . $ok->get_error_message() );
			} else {
				$paid = true;
			}
		}
	}

	// The IPN is a server-to-server call with nobody watching: answer plainly
	// and stop, or the gateway retries against a page it cannot parse.
	if ( 'ipn' === $what ) {
		status_header( 200 );
		echo $paid ? 'OK' : 'IGNORED';
		exit;
	}

	// A tiny page for the app's WebView. The marker in the URL is what the app
	// matches on; the words are for the half-second a human might see them.
	$state = $paid ? 'success' : ( 'cancel' === $what ? 'cancel' : 'fail' );
	$msg   = $paid
		? __( 'Payment received. Returning to the app…', 'aun-app-api' )
		: ( 'cancel' === $what
			? __( 'Payment cancelled. Returning to the app…', 'aun-app-api' )
			: __( 'The payment did not go through. Returning to the app…', 'aun-app-api' ) );

	status_header( 200 );
	nocache_headers();
	echo aun_app_api_payment_page( $state, $msg, $final ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped below.
	exit;
}

/**
 * The little page the app's WebView lands on, as a string.
 *
 * Separate from the handler purely so it can be tested: the handler must
 * `exit` (it is a front-end response), and a function that exits cannot be
 * asserted about.
 *
 * @param string $state 'success' | 'fail' | 'cancel'.
 * @param string $msg   Sentence for the human.
 * @param bool   $final True on the second landing — do not bounce again.
 * @return string
 */
function aun_app_api_payment_page( $state, $msg, $final ) {
	// Bounce once, as a GET, so the app sees a navigation it can act on.
	// Skipped on the second pass, or the WebView would loop for ever.
	$bounce = $final ? '' : add_query_arg(
		array( 'aun_sslc' => $state, 'aun_final' => '1' ),
		home_url( '/' )
	);

	return '<!doctype html><html><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>aun-app-payment-' . esc_attr( $state ) . '</title>'
		. ( '' !== $bounce
			? '<meta http-equiv="refresh" content="0;url=' . esc_url( $bounce ) . '">'
			: '' )
		. '</head>'
		. '<body style="margin:0;display:flex;align-items:center;justify-content:center;'
		. 'height:100vh;font:15px/1.6 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;'
		. 'color:#4b5563;text-align:center;padding:24px">'
		. '<div>' . esc_html( $msg ) . '</div>'
		. ( '' !== $bounce
			? '<script>location.replace(' . wp_json_encode( $bounce ) . ');</script>'
			: '' )
		. '</body></html>';
}
add_action( 'template_redirect', 'aun_app_api_sslcommerz_callback', 1 );

add_action( 'wp_head', 'aun_app_api_admin_bar_styles' );
add_action( 'admin_head', 'aun_app_api_admin_bar_styles' );

function aun_app_api_ensure_crons() {
	if ( class_exists( 'AUN_App_Tickets' ) && AUN_App_Tickets::configured()
		&& ! wp_next_scheduled( 'aun_app_tickets_poll' ) ) {
		wp_schedule_event( time() + 60, 'aun_app_ten_minutes', 'aun_app_tickets_poll' );
	}
	if ( ! wp_next_scheduled( 'aun_app_repair_poll' ) ) {
		wp_schedule_event( time() + 120, 'aun_app_ten_minutes', 'aun_app_repair_poll' );
	}
	// ⚠️ Here, and NOT only in the activation hook. Uploading a new plugin
	// zip over a running install never fires activation, so a cron registered
	// there alone does not exist on any site that UPGRADED rather than freshly
	// activated — which is every real site. That is exactly how the Chorki warm
	// job went missing on the live site after 1.99.0.
	if ( class_exists( 'AUN_App_Chorki' ) && AUN_App_Chorki::enabled()
		&& ! wp_next_scheduled( 'aun_app_chorki_warm' ) ) {
		wp_schedule_event( time() + 180, 'twicedaily', 'aun_app_chorki_warm' );
	}
}
add_action( 'init', 'aun_app_api_ensure_crons' );

/*
 * Learn this machine's own IP from ordinary web traffic.
 *
 * SERVER_ADDR does not exist under WP-CLI or cron — which is exactly when the
 * repair poll and the ticket poll run — so it is captured here, during normal
 * requests, and read back from the option when there is no web request to ask.
 */
add_action( 'init', array( 'AUN_App_Local_Route', 'remember_ip' ), 1 );

/**
 * TEST BENCH ONLY: when AUN_APP_DEV_OTP is defined the REST API also sends
 * permissive CORS headers so the app's web-preview build can call it from a
 * different port. Never define that constant on the live site.
 */
if ( defined( 'AUN_APP_DEV_OTP' ) && AUN_APP_DEV_OTP ) {
	add_action( 'rest_api_init', function () {
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		add_filter( 'rest_pre_serve_request', function ( $value ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type, X-AUN-Token, Authorization' );
			return $value;
		} );
	}, 15 );
}
