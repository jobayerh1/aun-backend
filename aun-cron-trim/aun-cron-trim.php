<?php
/**
 * Plugin Name: AUN Cron Trim
 * Version: 1.0.0
 * Description: Stops background jobs that cost more server time than they return. Currently: WP File Download preview generation.
 * Author: AUN
 *
 * INSTALL: upload to wp-content/mu-plugins/
 * REVERT:  delete the file, then visit any page once so the schedules re-register.
 *
 * Measured cost of wpfd_generate_preview_queue_tasks: ~450s of PHP per day
 * (12-13 runs averaging 16s, peaking at 25s), generating document thumbnails.
 * Existing previews are untouched; only new generation is skipped.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AUN_Cron_Trim {

	/** Cron hooks to keep unscheduled. */
	private static $blocked = array(
		'wpfd_generate_preview_queue_tasks',
		'wpfda_renew_subscriptions_tasks',
	);

	public static function boot() {
		// Refuse new schedules for these hooks.
		add_filter( 'schedule_event', array( __CLASS__, 'block_schedule' ), 10, 1 );

		// Clear anything already queued, at most once an hour.
		add_action( 'init', array( __CLASS__, 'clear_existing' ), 99 );
	}

	public static function block_schedule( $event ) {
		if ( is_object( $event ) && in_array( $event->hook, self::$blocked, true ) ) {
			return false;
		}

		return $event;
	}

	public static function clear_existing() {
		if ( get_transient( 'aun_cron_trim_done' ) ) {
			return;
		}

		set_transient( 'aun_cron_trim_done', 1, HOUR_IN_SECONDS );

		foreach ( self::$blocked as $hook ) {
			$timestamp = wp_next_scheduled( $hook );

			while ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
				$timestamp = wp_next_scheduled( $hook );
			}
		}
	}
}

AUN_Cron_Trim::boot();
