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

	const DB_VERSION = '11';

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
			wc_order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			delivery_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
			refunded_at DATETIME NULL,
			refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			refund_ref VARCHAR(96) NOT NULL DEFAULT '',
			quote_expires_at DATETIME NULL,
			quote_reminders TINYINT UNSIGNED NOT NULL DEFAULT 0,
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
			// The quote SMS now states that nothing is ordered until they approve, and
			// carries the validity date — the two things whose absence let a quote go
			// unanswered. Only sites still on the old wording are updated.
			AUN_SP_Messages::upgrade_default(
				AUN_SP_Messages::OPT_SMS_QUOTE,
				'AUN: your spare-parts quote for {ref} is ready - total Tk {total}. Please review and approve it here: {track}'
			);
		}

		self::backfill_phones();
		// Quotes sent before this version have no deadline: give each one a date
		// counted from the day it was sent, so an old unanswered quote is chased and
		// expired on the same clock as a new one instead of hanging for ever.
		self::backfill_quote_expiry();

		// Exactly one scheduled run per hook. (Tidying unpaid orders used to hang off
		// the DAILY digest, so "remove after 6 hours" could take a day and a half —
		// it has its own hourly run now.)
		self::ensure_single_schedule();

		update_option( 'aun_sp_db_version', self::DB_VERSION );
	}

	/**
	 * Exactly ONE scheduled run per hook — no more, no less.
	 *
	 * `wp_schedule_event()` behind a `! wp_next_scheduled()` check looks safe but is
	 * not. Two requests arriving together during an upgrade can both pass the check,
	 * and the deactivation hook used to remove only the NEXT instance — so a
	 * deactivate/reactivate cycle left an orphan behind and then added a fresh one.
	 * Every extra entry fires the hook again on its own clock, which is exactly why
	 * the daily digest email arrived twice, a few minutes apart.
	 *
	 * Counting first means the normal case (one entry) changes nothing. Also hooked
	 * to the hourly tidy, so a site that already has duplicates heals itself without
	 * needing a re-activation.
	 *
	 * @return int hooks that had to be corrected.
	 */
	public static function ensure_single_schedule() {
		$wanted = array(
			'aun_sp_daily_digest' => array( 'daily', HOUR_IN_SECONDS ),
			'aun_sp_hourly_tidy'  => array( 'hourly', 300 ),
		);

		$fixed = 0;
		foreach ( $wanted as $hook => $spec ) {
			$found = 0;
			foreach ( (array) _get_cron_array() as $events ) {
				if ( isset( $events[ $hook ] ) ) {
					$found += count( (array) $events[ $hook ] );
				}
			}
			if ( 1 === $found ) {
				continue; // healthy — leave its next run time alone
			}
			wp_clear_scheduled_hook( $hook ); // removes EVERY instance, not just the next
			wp_schedule_event( time() + $spec[1], $spec[0], $hook );
			$fixed++;
		}
		return $fixed;
	}

	/**
	 * Give every already-sent, still-unanswered quote a deadline.
	 *
	 * Idempotent: only rows with no deadline are touched. A quote whose backfilled
	 * date is already in the past is deliberately NOT expired here — the daily cron
	 * does that, so the customer gets the "your quote has expired" text rather than
	 * silently finding it dead the next time they open the link.
	 */
	public static function backfill_quote_expiry() {
		global $wpdb;
		$t    = self::table( 'requests' );
		$days = (int) get_option( 'aun_sp_quote_valid_days', 7 );
		if ( $days < 1 ) {
			return 0;
		}
		$rows = $wpdb->get_results(
			"SELECT id, quoted_at FROM $t
			 WHERE overall_status = 'quote_sent' AND quoted_at IS NOT NULL AND quote_expires_at IS NULL"
		);
		$n = 0;
		foreach ( (array) $rows as $r ) {
			$ts = strtotime( (string) $r->quoted_at );
			if ( ! $ts ) {
				continue;
			}
			$wpdb->update( $t, array( 'quote_expires_at' => date( 'Y-m-d H:i:s', $ts + $days * DAY_IN_SECONDS ) ), array( 'id' => (int) $r->id ) );
			$n++;
		}
		return $n;
	}

	/**
	 * Give every still-unanswered quote a deadline of N days FROM NOW.
	 *
	 * Used when the validity setting changes (including 0 → N). Counting from the
	 * original send date would be retroactive: quotes sent under "no deadline" terms
	 * would all lapse on the next cron run and text every one of those customers.
	 *
	 * @return int rows re-dated
	 */
	public static function date_open_quotes( $days ) {
		global $wpdb;
		$days = (int) $days;
		if ( $days < 1 ) {
			return 0;
		}
		$t = self::table( 'requests' );
		return (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $t SET quote_expires_at = %s WHERE overall_status = 'quote_sent'",
			date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $days * DAY_IN_SECONDS )
		) );
	}

	/**
	 * Bring every stored phone to the canonical 01XXXXXXXXX form.
	 *
	 * Requests created by the AUN Care Android app arrive as 8801XXXXXXXXX, so the
	 * table ends up holding two formats. Lookups are format-tolerant now
	 * (aun_sp_phone_where), but normalising the data keeps everything downstream —
	 * admin search, duplicate detection, the "changed contact" badge — comparing
	 * like with like. Idempotent: rows already canonical are skipped.
	 *
	 * @return int rows changed
	 */
	public static function backfill_phones() {
		global $wpdb;
		$t = self::table( 'requests' );

		$rows = $wpdb->get_results( "SELECT id, phone_current, phone_onfile FROM $t" );
		if ( empty( $rows ) ) {
			return 0;
		}

		$changed = 0;
		foreach ( $rows as $row ) {
			$update = array();

			$pc = aun_sp_normalize_phone( $row->phone_current );
			if ( $pc !== '' && $pc !== $row->phone_current ) {
				$update['phone_current'] = $pc;
			}
			// phone_onfile only exists from DB v4 onward.
			if ( isset( $row->phone_onfile ) ) {
				$po = aun_sp_normalize_phone( $row->phone_onfile );
				if ( $po !== '' && $po !== $row->phone_onfile ) {
					$update['phone_onfile'] = $po;
				}
			}

			if ( $update ) {
				$wpdb->update( $t, $update, array( 'id' => (int) $row->id ) );
				$changed++;
			}
		}
		return $changed;
	}
}
