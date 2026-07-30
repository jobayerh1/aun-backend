<?php
/**
 * Schema installer for AUN Spare Parts.
 *
 * Five tables, all owned by this plugin in the WordPress database:
 *   - legacy_sales   : one row per inFlow order (2019 -> Nov 2025), read-only archive
 *   - requests       : one spare-parts request (no device received)
 *   - request_items  : one row per requested part, each with its own status
 *   - attachments    : uploaded proof photos (serials / boards), compressed
 *   - events         : audit log (status changes, notes, notifications)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Install {

	const DB_VERSION = '6';

	/** Fully-qualified table name for a given short key. */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'aun_sp_' . $name;
	}

	public static function activate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$legacy   = self::table( 'legacy_sales' );
		$requests = self::table( 'requests' );
		$items    = self::table( 'request_items' );
		$attach   = self::table( 'attachments' );
		$events   = self::table( 'events' );

		$tables = array();

		$tables[] = "CREATE TABLE $legacy (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_number VARCHAR(64) NOT NULL DEFAULT '',
			customer_name VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(32) NOT NULL DEFAULT '',
			phone_raw VARCHAR(64) NOT NULL DEFAULT '',
			email VARCHAR(191) NOT NULL DEFAULT '',
			address TEXT NULL,
			city VARCHAR(96) NOT NULL DEFAULT '',
			order_date DATE NULL,
			status VARCHAR(32) NOT NULL DEFAULT '',
			primary_model VARCHAR(191) NOT NULL DEFAULT '',
			models TEXT NULL,
			total DECIMAL(12,2) NOT NULL DEFAULT 0,
			source VARCHAR(16) NOT NULL DEFAULT 'inflow',
			imported_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_number (order_number),
			KEY phone (phone),
			KEY order_date (order_date)
		) $charset;";

		$tables[] = "CREATE TABLE $requests (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ref VARCHAR(32) NOT NULL DEFAULT '',
			source_type VARCHAR(16) NOT NULL DEFAULT 'manual',
			source_order VARCHAR(64) NOT NULL DEFAULT '',
			model VARCHAR(191) NOT NULL DEFAULT '',
			purchase_date DATE NULL,
			warranty_in TINYINT(1) NOT NULL DEFAULT 0,
			customer_name VARCHAR(191) NOT NULL DEFAULT '',
			phone_current VARCHAR(32) NOT NULL DEFAULT '',
			address_current TEXT NULL,
			phone_onfile VARCHAR(32) NOT NULL DEFAULT '',
			address_onfile TEXT NULL,
			channel VARCHAR(32) NOT NULL DEFAULT 'web',
			overall_status VARCHAR(32) NOT NULL DEFAULT 'submitted',
			admin_note TEXT NULL,
			quote_total DECIMAL(12,2) NOT NULL DEFAULT 0,
			quote_note TEXT NULL,
			quoted_at DATETIME NULL,
			approved_at DATETIME NULL,
			created_at DATETIME NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ref (ref),
			KEY phone_current (phone_current),
			KEY overall_status (overall_status),
			KEY created_at (created_at)
		) $charset;";

		$tables[] = "CREATE TABLE $items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			part_type VARCHAR(48) NOT NULL DEFAULT '',
			part_label VARCHAR(96) NOT NULL DEFAULT '',
			qty SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			line_status VARCHAR(32) NOT NULL DEFAULT 'pending',
			factory_po VARCHAR(64) NOT NULL DEFAULT '',
			eta DATE NULL,
			unit_price DECIMAL(12,2) NULL,
			note TEXT NULL,
			tracking_no VARCHAR(64) NOT NULL DEFAULT '',
			created_at DATETIME NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY request_id (request_id),
			KEY line_status (line_status)
		) $charset;";

		$tables[] = "CREATE TABLE $attach (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			kind VARCHAR(32) NOT NULL DEFAULT '',
			file_url TEXT NULL,
			bytes_before BIGINT UNSIGNED NOT NULL DEFAULT 0,
			bytes_after BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY request_id (request_id)
		) $charset;";

		$tables[] = "CREATE TABLE $events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			type VARCHAR(32) NOT NULL DEFAULT '',
			message TEXT NULL,
			old_value VARCHAR(64) NOT NULL DEFAULT '',
			new_value VARCHAR(64) NOT NULL DEFAULT '',
			by_user VARCHAR(96) NOT NULL DEFAULT '',
			created_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY request_id (request_id)
		) $charset;";

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		// Defaults (add_option is a no-op if the option already exists).
		add_option( 'aun_sp_warranty_months', 12 );
		add_option( 'aun_sp_warranty_grace_days', 4 );
		add_option( 'aun_sp_alert_email', get_option( 'admin_email' ) );

		if ( class_exists( 'AUN_SP_Parts' ) ) {
			AUN_SP_Parts::seed();
		}
		if ( class_exists( 'AUN_SP_Messages' ) ) {
			AUN_SP_Messages::seed();
			// Push newer default reject reasons to sites that already have a saved set.
			AUN_SP_Messages::ensure_reject_template( 'Duplicate request' );
		}

		if ( ! wp_next_scheduled( 'aun_sp_daily_digest' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aun_sp_daily_digest' );
		}

		update_option( 'aun_sp_db_version', self::DB_VERSION );
	}
}
