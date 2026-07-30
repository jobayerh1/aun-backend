<?php
/**
 * Bangladeshi phone-number helpers.
 *
 * Same canonicalisation rules as the AUN Alpha SMS OTP Login plugin so both login
 * surfaces (website + app) match the same accounts. Kept as an independent copy so
 * this plugin works even if that plugin is deactivated.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Phone {

	/**
	 * Normalize any Bangladeshi mobile input to the canonical 8801XXXXXXXXX form,
	 * or false if it is not a valid BD mobile.
	 *
	 * Accepts: 01712345678, +8801712345678, 8801712345678, 1712345678, with spaces/dashes.
	 *
	 * @param string $raw Raw user input.
	 * @return string|false
	 */
	public static function normalize( $raw ) {
		$digits = preg_replace( '/\D+/', '', (string) $raw );
		if ( '' === $digits ) {
			return false;
		}

		if ( 0 === strpos( $digits, '880' ) ) {
			$local = '0' . substr( $digits, 3 );
		} elseif ( 0 === strpos( $digits, '88' ) && strlen( $digits ) >= 13 ) {
			$local = substr( $digits, 2 );
		} elseif ( 0 === strpos( $digits, '0' ) ) {
			$local = $digits;
		} elseif ( 10 === strlen( $digits ) && '1' === $digits[0] ) {
			$local = '0' . $digits;
		} else {
			$local = $digits;
		}

		if ( 11 !== strlen( $local ) || '01' !== substr( $local, 0, 2 ) ) {
			return false;
		}

		$operator_prefixes = array( '013', '014', '015', '016', '017', '018', '019' );
		if ( ! in_array( substr( $local, 0, 3 ), $operator_prefixes, true ) ) {
			return false;
		}

		return '88' . $local;
	}

	/**
	 * All stored-format variants of a canonical number, for matching data saved by
	 * different plugins/eras (Alpha saves 13-digit, CF7 warranty forms saved 11-digit, ...).
	 *
	 * @param string $canonical 8801XXXXXXXXX
	 * @return string[]
	 */
	public static function variants( $canonical ) {
		$local11 = substr( $canonical, 2 );
		$local10 = substr( $local11, 1 );

		return array_values(
			array_unique(
				array(
					$canonical,
					'+' . $canonical,
					$local11,
					$local10,
				)
			)
		);
	}

	/**
	 * Mask a canonical number for display, e.g. 017******78.
	 *
	 * @param string $canonical 8801XXXXXXXXX
	 * @return string
	 */
	public static function mask( $canonical ) {
		$local = substr( (string) $canonical, 2 );
		if ( strlen( $local ) < 5 ) {
			return $local;
		}
		return substr( $local, 0, 3 ) . str_repeat( '*', strlen( $local ) - 5 ) . substr( $local, -2 );
	}

	/**
	 * Find customer accounts whose billing_phone or mobile_phone matches the number.
	 *
	 * @param string $canonical 8801XXXXXXXXX
	 * @return WP_User[]
	 */
	public static function find_users( $canonical ) {
		$variants   = self::variants( $canonical );
		$meta_query = array( 'relation' => 'OR' );

		foreach ( array( 'billing_phone', 'mobile_phone' ) as $key ) {
			foreach ( $variants as $value ) {
				$meta_query[] = array(
					'key'     => $key,
					'value'   => $value,
					'compare' => '=',
				);
			}
		}

		$query = new WP_User_Query(
			array(
				'meta_query'  => $meta_query,
				'number'      => 10,
				'count_total' => false,
				'fields'      => 'all',
			)
		);

		$seen   = array();
		$unique = array();
		foreach ( $query->get_results() as $user ) {
			if ( ! in_array( (int) $user->ID, $seen, true ) ) {
				$seen[]   = (int) $user->ID;
				$unique[] = $user;
			}
		}

		return $unique;
	}

	/**
	 * Canonical phone of a WP user (from billing_phone, falling back to mobile_phone).
	 *
	 * @param int $user_id User ID.
	 * @return string|false
	 */
	public static function user_phone( $user_id ) {
		foreach ( array( 'billing_phone', 'mobile_phone' ) as $key ) {
			$raw = get_user_meta( $user_id, $key, true );
			if ( '' !== $raw ) {
				$canonical = self::normalize( $raw );
				if ( $canonical ) {
					return $canonical;
				}
			}
		}
		return false;
	}
}
