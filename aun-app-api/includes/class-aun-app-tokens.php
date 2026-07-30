<?php
/**
 * Bearer tokens for the app: issue on OTP verification, validate on every
 * authenticated request. Only the HMAC-SHA256 hash of a token is stored, so a
 * leaked database never exposes usable tokens.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Tokens {

	private static function hash_token( $raw ) {
		return hash_hmac( 'sha256', (string) $raw, wp_salt( 'auth' ) );
	}

	/**
	 * Issue a new token for a user.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $device_name Optional device label ("Samsung Galaxy A15").
	 * @return string Raw token (only time it ever exists in plain form).
	 */
	public static function issue( $user_id, $device_name = '' ) {
		global $wpdb;

		$raw  = bin2hex( random_bytes( 32 ) );
		$opts = aun_app_api_get_options();
		$days = max( 1, (int) $opts['token_days'] );

		$wpdb->insert(
			aun_app_api_tokens_table(),
			array(
				'user_id'     => (int) $user_id,
				'token_hash'  => self::hash_token( $raw ),
				'device_name' => substr( sanitize_text_field( $device_name ), 0, 120 ),
				'created_at'  => current_time( 'mysql' ),
				'last_used'   => current_time( 'mysql' ),
				'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $raw;
	}

	/**
	 * Validate a raw token. Returns the row (object) or false.
	 * Refreshes last_used at most once per hour to keep writes cheap.
	 *
	 * @param string $raw Raw token from the Authorization header.
	 * @return object|false
	 */
	public static function validate( $raw ) {
		global $wpdb;

		$raw = trim( (string) $raw );
		if ( strlen( $raw ) < 32 ) {
			return false;
		}

		$table = aun_app_api_tokens_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE token_hash = %s", self::hash_token( $raw ) )
		);

		if ( ! $row ) {
			return false;
		}

		if ( strtotime( $row->expires_at . ' UTC' ) < time() ) {
			$wpdb->delete( $table, array( 'id' => $row->id ), array( '%d' ) );
			return false;
		}

		if ( strtotime( $row->last_used ) < time() - HOUR_IN_SECONDS ) {
			$wpdb->update(
				$table,
				array( 'last_used' => current_time( 'mysql' ) ),
				array( 'id' => $row->id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return $row;
	}

	/**
	 * Revoke one token by its raw value.
	 *
	 * @param string $raw Raw token.
	 * @return bool
	 */
	public static function revoke( $raw ) {
		global $wpdb;
		return (bool) $wpdb->delete(
			aun_app_api_tokens_table(),
			array( 'token_hash' => self::hash_token( $raw ) ),
			array( '%s' )
		);
	}

	/**
	 * Revoke every token of a user (log out everywhere).
	 *
	 * @param int $user_id User ID.
	 * @return int Rows deleted.
	 */
	public static function revoke_all( $user_id ) {
		global $wpdb;
		return (int) $wpdb->delete(
			aun_app_api_tokens_table(),
			array( 'user_id' => (int) $user_id ),
			array( '%d' )
		);
	}

	/**
	 * Delete expired rows (called opportunistically from the admin dashboard).
	 */
	public static function purge_expired() {
		global $wpdb;
		$table = aun_app_api_tokens_table();
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM $table WHERE expires_at < %s", gmdate( 'Y-m-d H:i:s' ) )
		);
	}

	/**
	 * Simple stats for the admin dashboard.
	 *
	 * @return array{tokens:int,users:int}
	 */
	public static function stats() {
		global $wpdb;
		$table = aun_app_api_tokens_table();
		return array(
			'tokens' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ),
			'users'  => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM $table" ),
		);
	}
}
