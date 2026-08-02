<?php
/**
 * Plugin Name:       AUN App API
 * Plugin URI:        https://aun-projector.com.bd/
 * Description:       REST API backend for the AUN Care Bangladesh Android customer app: phone+OTP login, device registration & warranty (reads the SLB Warranty plugin tables), firmware/manual/video/tip content per model, and app configuration. Companion to AUN Warranty Registration and AUN Alpha SMS OTP Login.
 * Version:           1.42.0
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

define( 'AUN_APP_API_VERSION', '1.42.0' );
// v14 = adds aun_app_notice_state.completed_at/snoozed_until (actionable
// maintenance reminders — mark done / remind me later).
// v13 = adds aun_app_content.app_downloadable (per-file "downloadable in app").
// v12 = adds aun_app_feedback.image_url (bug-report screenshot).
// v11 = aun_app_repairs.last_erp_status (repair-status change push).
// v10 = the aun_app_dismissed ledger. Bumping re-runs activation so existing
// installs get new columns + crons.
define( 'AUN_APP_API_DB_VERSION', '14' );
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
require_once AUN_APP_API_PATH . 'includes/class-aun-app-erp.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-profile.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-warranty.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-content.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-services.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-notices.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-account.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-push.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-greeting.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-help.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-tickets.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-watch.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-projectors.php';
require_once AUN_APP_API_PATH . 'includes/class-aun-app-rest.php';

if ( is_admin() ) {
	require_once AUN_APP_API_PATH . 'admin/class-aun-app-admin.php';
}

// "What to watch" rebuilds always happen in the background (single event fired
// when the store goes stale, plus a twice-daily warm), never in a customer
// request — see AUN_App_Watch::picks().
add_action( 'aun_app_watch_refresh', array( 'AUN_App_Watch', 'refresh_cron' ) );
add_action( 'aun_app_watch_warm', array( 'AUN_App_Watch', 'refresh_cron' ) );

/**
 * Default plugin settings.
 *
 * @return array
 */
function aun_app_api_default_options() {
	return array(
		'whatsapp_number'     => '',          // e.g. 8801XXXXXXXXX (digits only, used in wa.me link).
		'support_phone'       => '',          // Tap-to-call number shown in the app.
		'support_hours'       => 'Sat–Thu, 10am–8pm',
		'facebook_url'        => '',
		'website_url'         => '',          // Blank = home_url().
		'announcement'        => '',          // Short notice shown on the app home screen.
		'banners'             => '',          // One per line: image_url | optional_link_url
		'latest_version_code' => 1,           // Newest APK build number.
		'latest_version_name' => '1.0.0',
		'apk_url'             => '',          // Direct APK download (pre-Play-Store distribution).
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
		'youtube_api_key'     => '',          // Free YouTube Data API key → real video upload dates.
		// OUR OWN OneDrive/SharePoint app registration, so firmware downloads do
		// not depend on the WP File Download plugin. Optional: with these blank
		// we fall back to that plugin's token (if installed), then to the public
		// "Anyone with the link" share URL.
		'onedrive_client_id'     => '',
		'onedrive_client_secret' => '',
		'onedrive_tenant'        => '',       // Directory (tenant) ID, or 'common'.
		'onedrive_refresh_token' => '',       // Delegated grant (optional; blank = app-only).
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

	// Repair-status poll: the ERP has no webhooks, so every 10 minutes we check
	// active job sheets for a status change and push it to the owner. No-op
	// until the ERP repair API is configured.
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
	foreach ( array( 'aun_app_verify_devices', 'aun_app_daily_notices', 'aun_app_tickets_poll', 'aun_app_repair_poll' ) as $hook ) {
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

/** Poll the ERP for repair-status changes → notification centre + push. */
function aun_app_api_repair_poll_cron() {
	AUN_App_Services::poll_repair_statuses();
}
add_action( 'aun_app_repair_poll', 'aun_app_api_repair_poll_cron' );

// Spare-parts requests live in their own plugin and only ever spoke to the
// customer by SMS. This bridges its status changes into the app's notification
// centre + push — above all "quote sent", which the customer must answer.
add_action( 'aun_sp_status_changed', array( 'AUN_App_Services', 'on_parts_status_changed' ), 10, 2 );

// The planner catalogue is cached for hours, so editing a projector's throw
// ratio would otherwise not reach the app until the cache expired — long enough
// to look broken while you're correcting a figure.
add_action( 'woocommerce_update_product', array( 'AUN_App_Projectors', 'flush' ) );
add_action( 'woocommerce_new_product', array( 'AUN_App_Projectors', 'flush' ) );

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
}
add_action( 'init', 'aun_app_api_ensure_crons' );

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
