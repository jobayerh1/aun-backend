<?php
/**
 * OTP session, storage, phone handling and rate limiting.
 *
 * No custom DB tables: per-visitor state lives in a transient keyed by an httpOnly
 * cookie, and rate-limit counters live in short-lived transients. OTP codes are stored
 * hashed (HMAC) so a leaked object cache never exposes a live code.
 *
 * @package KT_Alpha_OTP_Login
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class KT_Alpha_OTP_Session {

	const COOKIE       = 'kt_alpha_otp_sess';
	const TRANSIENT    = 'kt_alpha_otp_';      // + token
	const RL_PHONE     = 'kt_alpha_otp_rlp_';  // + md5(phone)
	const RL_IP        = 'kt_alpha_otp_rli_';  // + md5(ip)

	/**
	 * Cached session token for this request.
	 *
	 * @var string|null
	 */
	private $token = null;

	/* --------------------------------------------------------------------- *
	 * Phone helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Normalize any Bangladeshi mobile input to the canonical 8801XXXXXXXXX form
	 * (the same shape the Alpha SMS plugin sends), or false if it is not a valid BD mobile.
	 *
	 * Accepts: 01712345678, +8801712345678, 8801712345678, 1712345678, with spaces/dashes.
	 *
	 * @param string $raw Raw user input.
	 * @return string|false Canonical number or false.
	 */
	public static function normalize_phone( $raw ) {
		$digits = preg_replace( '/\D+/', '', (string) $raw ); // drop +, spaces, dashes, etc.
		if ( '' === $digits ) {
			return false;
		}

		// Reduce to the local 11-digit 01XXXXXXXXX form first.
		if ( 0 === strpos( $digits, '880' ) ) {
			$local = '0' . substr( $digits, 3 );          // 880 + 1XXXXXXXXX
		} elseif ( 0 === strpos( $digits, '88' ) && strlen( $digits ) >= 13 ) {
			$local = substr( $digits, 2 );                // 88 + 01XXXXXXXXX
		} elseif ( 0 === strpos( $digits, '0' ) ) {
			$local = $digits;                             // already 01XXXXXXXXX
		} elseif ( 10 === strlen( $digits ) && '1' === $digits[0] ) {
			$local = '0' . $digits;                       // 1XXXXXXXXX
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

		return '88' . $local; // 8801XXXXXXXXX
	}

	/**
	 * All stored-format variants of a canonical number, for matching user meta that may
	 * have been saved by different plugins/eras (Alpha saves 13-digit; legacy may be 11-digit).
	 *
	 * @param string $canonical 8801XXXXXXXXX
	 * @return string[]
	 */
	public static function phone_variants( $canonical ) {
		$local11 = substr( $canonical, 2 );        // 01XXXXXXXXX
		$local10 = substr( $local11, 1 );          // 1XXXXXXXXX

		return array_values(
			array_unique(
				array(
					$canonical,          // 8801XXXXXXXXX
					'+' . $canonical,    // +8801XXXXXXXXX
					$local11,            // 01XXXXXXXXX
					$local10,            // 1XXXXXXXXX
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
	public static function mask_phone( $canonical ) {
		$local = substr( $canonical, 2 ); // 01XXXXXXXXX (11)
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
	public static function find_users_by_phone( $canonical ) {
		$variants = self::phone_variants( $canonical );

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

		// A user storing the number in BOTH billing_phone and mobile_phone can match
		// more than one meta row; on some DB engines that yields the same WP_User twice.
		// De-duplicate by ID so a single account is never mistaken for several.
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

	/* --------------------------------------------------------------------- *
	 * Session token + transient store
	 * --------------------------------------------------------------------- */

	/**
	 * Resolve (creating if needed) the per-visitor session token, stored in an httpOnly cookie.
	 *
	 * @return string
	 */
	public function get_token() {
		if ( null !== $this->token ) {
			return $this->token;
		}

		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$candidate = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
			if ( '' !== $candidate ) {
				$this->token = $candidate;
				return $this->token;
			}
		}

		$this->token = sanitize_key( wp_generate_password( 32, false, false ) );

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE,
				$this->token,
				time() + HOUR_IN_SECONDS,
				defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
				defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				is_ssl(),
				true // httpOnly
			);
		}
		$_COOKIE[ self::COOKIE ] = $this->token;

		return $this->token;
	}

	/**
	 * Read the current OTP payload.
	 *
	 * @return array
	 */
	public function get_data() {
		$data = get_transient( self::TRANSIENT . $this->get_token() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Persist the OTP payload.
	 *
	 * @param array $data    Payload.
	 * @param int   $seconds TTL.
	 * @return bool
	 */
	public function set_data( array $data, $seconds ) {
		$seconds = max( 60, (int) $seconds );
		return set_transient( self::TRANSIENT . $this->get_token(), $data, $seconds );
	}

	/**
	 * Clear the OTP payload.
	 */
	public function clear_data() {
		delete_transient( self::TRANSIENT . $this->get_token() );
	}

	/* --------------------------------------------------------------------- *
	 * OTP generation / verification
	 * --------------------------------------------------------------------- */

	/**
	 * Generate a numeric OTP of the given length.
	 *
	 * @param int $length Digits.
	 * @return string
	 */
	public static function generate_otp( $length ) {
		$length = max( 4, min( 8, (int) $length ) );
		$otp    = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$otp .= (string) wp_rand( 0, 9 );
		}
		return $otp;
	}

	/**
	 * Hash an OTP for storage/comparison.
	 *
	 * @param string $otp Plain OTP.
	 * @return string
	 */
	public static function hash_otp( $otp ) {
		return hash_hmac( 'sha256', (string) $otp, wp_salt( 'auth' ) );
	}

	/**
	 * Store a freshly generated+sent OTP against the candidate users.
	 *
	 * @param string $canonical Canonical phone.
	 * @param string $otp       Plain OTP.
	 * @param int[]  $user_ids  Candidate user IDs.
	 * @param int    $expiry    Validity in seconds.
	 * @param int    $resend_wait Seconds before resend.
	 */
	public function store_otp( $canonical, $otp, $user_ids, $expiry, $resend_wait ) {
		$now      = time();
		$existing = $this->get_data();
		$resends  = isset( $existing['resends'] ) && $existing['phone'] === $canonical ? (int) $existing['resends'] : 0;

		$this->set_data(
			array(
				'phone'        => $canonical,
				'otp_hash'     => self::hash_otp( $otp ),
				'expires'      => $now + (int) $expiry,
				'attempts'     => 0,
				'resends'      => $resends,
				'last_sent'    => $now,
				'resend_wait'  => (int) $resend_wait,
				'user_ids'     => array_map( 'intval', (array) $user_ids ),
				'otp_verified' => false,
			),
			(int) $expiry + 60
		);
	}

	/**
	 * Verify a submitted OTP.
	 *
	 * @param string $otp         Submitted code.
	 * @param int    $max_attempts Max wrong tries before invalidation.
	 * @return array{ok:bool,reason:string} reason ∈ '', 'expired', 'mismatch', 'locked', 'none'
	 */
	public function verify_otp( $otp, $max_attempts ) {
		$data = $this->get_data();

		if ( empty( $data['otp_hash'] ) ) {
			return array(
				'ok'     => false,
				'reason' => 'none',
			);
		}

		if ( time() > (int) $data['expires'] ) {
			$this->clear_data();
			return array(
				'ok'     => false,
				'reason' => 'expired',
			);
		}

		if ( (int) $data['attempts'] >= (int) $max_attempts ) {
			$this->clear_data();
			return array(
				'ok'     => false,
				'reason' => 'locked',
			);
		}

		if ( hash_equals( (string) $data['otp_hash'], self::hash_otp( $otp ) ) ) {
			$data['otp_verified'] = true;
			$this->set_data( $data, max( 60, (int) $data['expires'] - time() ) );
			return array(
				'ok'     => true,
				'reason' => '',
			);
		}

		// Wrong code: record the attempt.
		$data['attempts'] = (int) $data['attempts'] + 1;
		$this->set_data( $data, max( 60, (int) $data['expires'] - time() ) );

		if ( (int) $data['attempts'] >= (int) $max_attempts ) {
			$this->clear_data();
			return array(
				'ok'     => false,
				'reason' => 'locked',
			);
		}

		return array(
			'ok'     => false,
			'reason' => 'mismatch',
		);
	}

	/* --------------------------------------------------------------------- *
	 * Rate limiting
	 * --------------------------------------------------------------------- */

	/**
	 * Best-effort client IP, Cloudflare-aware (the site is behind Cloudflare).
	 *
	 * @return string
	 */
	public static function client_ip() {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $candidates as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			$value = trim( explode( ',', $value )[0] ); // X-Forwarded-For may be a list.
			$ip    = filter_var( $value, FILTER_VALIDATE_IP );
			if ( $ip ) {
				return $ip;
			}
		}
		return '0.0.0.0';
	}

	/**
	 * Whether the phone OR the IP has hit the daily OTP-request cap.
	 *
	 * @param string $canonical Canonical phone.
	 * @param int    $max_per_day Cap.
	 * @return bool
	 */
	public function is_rate_limited( $canonical, $max_per_day ) {
		$max_per_day = (int) $max_per_day;
		if ( $max_per_day <= 0 ) {
			return false;
		}

		$phone_count = (int) get_transient( self::RL_PHONE . md5( $canonical ) );
		$ip_count    = (int) get_transient( self::RL_IP . md5( self::client_ip() ) );

		return ( $phone_count >= $max_per_day ) || ( $ip_count >= $max_per_day );
	}

	/**
	 * Record one OTP request against the phone + IP daily counters.
	 *
	 * @param string $canonical Canonical phone.
	 */
	public function record_request( $canonical ) {
		$phone_key = self::RL_PHONE . md5( $canonical );
		$ip_key    = self::RL_IP . md5( self::client_ip() );

		set_transient( $phone_key, ( (int) get_transient( $phone_key ) ) + 1, DAY_IN_SECONDS );
		set_transient( $ip_key, ( (int) get_transient( $ip_key ) ) + 1, DAY_IN_SECONDS );
	}

	/**
	 * Seconds the visitor must still wait before a resend is allowed (0 = allowed now).
	 *
	 * @return int
	 */
	public function resend_cooldown_remaining() {
		$data = $this->get_data();
		if ( empty( $data['last_sent'] ) ) {
			return 0;
		}
		$wait      = isset( $data['resend_wait'] ) ? (int) $data['resend_wait'] : 30;
		$remaining = ( (int) $data['last_sent'] + $wait ) - time();
		return $remaining > 0 ? $remaining : 0;
	}
}
