<?php
/**
 * First-party product analytics.
 *
 * ⚠️ **Why this exists instead of Firebase Analytics.** The app's published
 * privacy policy promises "no third-party analytics or tracking SDKs". That is
 * a real commitment to customers, and shipping Firebase Analytics would have
 * quietly made it untrue. The same questions are answerable from our own
 * server, so the events come here: no third party sees them, nothing to declare
 * as data sharing, and the promise stands.
 *
 * (Crash reporting is a separate matter and DOES use Crashlytics — a stack
 * trace is diagnostics, not tracking, and there is no realistic first-party
 * equivalent. It is disclosed in the policy on those terms.)
 *
 * What this deliberately is NOT: a user-tracking system. There are no
 * profiles, no funnels per person, no cross-app identifiers, no device
 * fingerprints. It counts things.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Events {

	/** Events older than this are deleted by cron. */
	const RETENTION_DAYS = 180;

	/** Most events one request may carry. */
	const MAX_BATCH = 50;

	/**
	 * The complete list of events the app may record.
	 *
	 * ⚠️ An allow-list, not a suggestion: anything not named here is dropped.
	 * A future app version cannot invent event names that quietly fill the
	 * table, and this list is the single readable answer to "what does the app
	 * actually collect?" — which is exactly what a privacy policy has to state.
	 */
	const ALLOWED = array(
		'app_open',
		'login',
		'service_opened',
		'home_strip_tap',
		'finder_started',
		'finder_step',
		'finder_result',
		'finder_product_opened',
		'parts_form_opened',
		'parts_submitted',
		'quote_answered',
		'quote_revived',
		// Whether a photo we sent back is answered IN the app or still through
		// the SMS link — the whole justification for building this here.
		'parts_photo_sent',
		'pay_started',
		'pay_finished',
		'content_opened',
		'watch_opened',
		'notifications_prompted',
		'notification_opened',
		'app_problem',
	);

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aun_app_events';
	}

	/** Whether collection is switched on (same admin toggle as crash reports). */
	public static function enabled() {
		$opts = aun_app_api_get_options();
		return ! isset( $opts['analytics_enabled'] ) || (int) $opts['analytics_enabled'] === 1;
	}

	/**
	 * Record a batch from one app.
	 *
	 * @param array  $events  [{name, params{}, at}] from the app.
	 * @param int    $user_id 0 for a logged-out session.
	 * @param string $version App version name, e.g. "1.88.0".
	 * @return int Rows stored.
	 */
	public static function record( $events, $user_id = 0, $version = '' ) {
		if ( ! self::enabled() || ! is_array( $events ) ) {
			return 0;
		}

		global $wpdb;
		$t       = self::table();
		$version = substr( sanitize_text_field( (string) $version ), 0, 20 );
		$now     = current_time( 'mysql' );
		$stored  = 0;

		foreach ( array_slice( $events, 0, self::MAX_BATCH ) as $e ) {
			if ( ! is_array( $e ) ) {
				continue;
			}
			$name = sanitize_key( (string) ( $e['name'] ?? '' ) );
			if ( ! in_array( $name, self::ALLOWED, true ) ) {
				continue;
			}

			$wpdb->insert(
				$t,
				array(
					// The customer's own account id on our own system. Used to
					// count PEOPLE rather than taps ("40 opens" from one person
					// is a different fact from 40 people), never to build a
					// profile of anyone.
					'user_id'    => max( 0, (int) $user_id ),
					'event'      => $name,
					'params'     => wp_json_encode( self::clean_params( $e['params'] ?? array() ) ),
					'app_version'=> $version,
					'created_at' => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);
			$stored++;
		}

		return $stored;
	}

	/**
	 * Strip anything that could identify a person, whatever the app sent.
	 *
	 * The app is written not to send it; this is the second lock. Values are
	 * short scalars only — a category, a count, a yes/no. Anything that looks
	 * like a phone number, an email or a long free-text string is dropped
	 * rather than stored.
	 */
	private static function clean_params( $params ) {
		if ( ! is_array( $params ) ) {
			return array();
		}
		$out = array();
		$i   = 0;
		foreach ( $params as $k => $v ) {
			if ( $i++ >= 8 ) {
				break;
			}
			$key = sanitize_key( (string) $k );
			if ( '' === $key ) {
				continue;
			}
			if ( is_bool( $v ) ) {
				$out[ $key ] = $v ? 1 : 0;
				continue;
			}
			if ( is_int( $v ) || is_float( $v ) ) {
				$out[ $key ] = $v + 0;
				continue;
			}
			$s = trim( (string) $v );
			if ( '' === $s || strlen( $s ) > 40 ) {
				continue; // a long string is prose, and prose is never a category
			}
			if ( preg_match( '/(?:\+?88)?01\d{9}/', $s ) || false !== strpos( $s, '@' ) ) {
				continue;
			}
			$out[ $key ] = sanitize_text_field( $s );
		}
		return $out;
	}

	/**
	 * Counts per event over a window, for the admin dashboard.
	 *
	 * @param int $days Days back from today.
	 * @return array[] {event, hits, people}
	 */
	public static function totals( $days = 30 ) {
		global $wpdb;
		$t    = self::table();
		$days = max( 1, min( 365, (int) $days ) );

		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT event,
			        COUNT(*) AS hits,
			        COUNT(DISTINCT NULLIF(user_id,0)) AS people
			 FROM $t
			 WHERE created_at >= DATE_SUB( %s, INTERVAL %d DAY )
			 GROUP BY event
			 ORDER BY hits DESC",
			current_time( 'mysql' ),
			$days
		) );
	}

	/**
	 * The breakdown for one event ("which service?", "approve or decline?").
	 *
	 * @return array[] {value, hits}
	 */
	public static function breakdown( $event, $key, $days = 30 ) {
		global $wpdb;
		$t     = self::table();
		$event = sanitize_key( $event );
		$key   = sanitize_key( $key );
		$days  = max( 1, min( 365, (int) $days ) );

		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT params FROM $t
			 WHERE event = %s AND created_at >= DATE_SUB( %s, INTERVAL %d DAY )",
			$event,
			current_time( 'mysql' ),
			$days
		) );

		// Counted in PHP rather than with a JSON path so this works the same on
		// MySQL 5.6 (still common on shared hosting here) as on 8.0.
		$tally = array();
		foreach ( $rows as $r ) {
			$p = json_decode( (string) $r->params, true );
			$v = is_array( $p ) && isset( $p[ $key ] ) ? (string) $p[ $key ] : '(none)';
			$tally[ $v ] = ( $tally[ $v ] ?? 0 ) + 1;
		}
		arsort( $tally );

		$out = array();
		foreach ( $tally as $value => $hits ) {
			$out[] = array( 'value' => $value, 'hits' => $hits );
		}
		return $out;
	}

	/** Active people per day, for the sparkline. */
	public static function daily( $days = 30 ) {
		global $wpdb;
		$t    = self::table();
		$days = max( 1, min( 365, (int) $days ) );

		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(created_at) AS day,
			        COUNT(DISTINCT NULLIF(user_id,0)) AS people,
			        COUNT(*) AS hits
			 FROM $t
			 WHERE created_at >= DATE_SUB( %s, INTERVAL %d DAY )
			 GROUP BY DATE(created_at)
			 ORDER BY day ASC",
			current_time( 'mysql' ),
			$days
		) );
	}

	/**
	 * Delete anything past the retention window.
	 *
	 * Analytics that is never deleted stops being analytics and becomes a
	 * liability: the questions here are all about the last month, so keeping
	 * three years of rows buys nothing and only widens what a breach exposes.
	 */
	public static function purge() {
		global $wpdb;
		$t = self::table();
		return (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM $t WHERE created_at < DATE_SUB( %s, INTERVAL %d DAY )",
			current_time( 'mysql' ),
			self::RETENTION_DAYS
		) );
	}

	/** Everything this account has generated — erased with the account. */
	public static function delete_for_user( $user_id ) {
		global $wpdb;
		return (int) $wpdb->delete( self::table(), array( 'user_id' => (int) $user_id ), array( '%d' ) );
	}
}
