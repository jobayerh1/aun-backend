<?php
/**
 * OTP storage, verification and rate limiting for the app login.
 *
 * The website OTP plugin keys its state to a browser cookie; an app has no
 * cookies, so here the state is keyed to the (canonical) phone number itself.
 * Codes are stored hashed (HMAC-SHA256 with the auth salt) in transients, with
 * the same attempt/resend/daily-cap rules as the website login.
 *
 * Define AUN_APP_DEV_OTP as true in wp-config.php on a TEST site only to skip
 * the SMS gateway and receive the OTP in the API response (never in production).
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_OTP {

	const STORE    = 'aun_app_otp_';      // + md5(phone)
	const RL_PHONE = 'aun_app_otp_rlp_';  // + md5(phone)
	const RL_IP    = 'aun_app_otp_rli_';  // + md5(ip)

	/**
	 * Best-effort client IP, Cloudflare-aware.
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
			$value = trim( explode( ',', $value )[0] );
			$ip    = filter_var( $value, FILTER_VALIDATE_IP );
			if ( $ip ) {
				return $ip;
			}
		}
		return '0.0.0.0';
	}

	/**
	 * Whether dev mode (OTP echoed in response, SMS skipped) is on.
	 *
	 * @return bool
	 */
	public static function dev_mode() {
		return defined( 'AUN_APP_DEV_OTP' ) && AUN_APP_DEV_OTP;
	}

	/**
	 * SMS Retriever: append the app signature hash on its own line at the END
	 * of the message, so Android routes the SMS to the app for OTP auto-fill.
	 * Invalid/missing hashes leave the message untouched (website OTPs never
	 * send one).
	 *
	 * @param string $body     SMS text.
	 * @param string $app_hash 11-char base64 signature hash from the app.
	 * @return string
	 */
	public static function append_app_hash( $body, $app_hash ) {
		$app_hash = trim( (string) $app_hash );
		if ( '' !== $app_hash && preg_match( '/^[A-Za-z0-9+\/=]{8,17}$/', $app_hash ) ) {
			$body .= "\n" . $app_hash;
		}
		return $body;
	}

	private static function hash_otp( $otp ) {
		return hash_hmac( 'sha256', (string) $otp, wp_salt( 'auth' ) );
	}

	private static function generate( $length ) {
		$length = max( 4, min( 8, (int) $length ) );
		$otp    = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$otp .= (string) wp_rand( 0, 9 );
		}
		return $otp;
	}

	/**
	 * Request an OTP for a canonical phone: rate-limit, generate, store, send.
	 *
	 * @param string $canonical 8801XXXXXXXXX
	 * @param string $app_hash  Optional Google SMS-Retriever app signature hash
	 *                          (11 base64 chars). When present it is appended to
	 *                          the SMS so Android hands the message straight to
	 *                          the app — the OTP fills in by itself, no typing.
	 *                          Only the APP sends this; website OTPs unchanged.
	 * @return array{ok:bool,code:string,message:string,resend_wait?:int,expires_in?:int,dev_otp?:string}
	 */
	public static function request( $canonical, $app_hash = '' ) {
		$cfg = aun_app_api_otp_settings();

		// Resend cooldown.
		$data = get_transient( self::STORE . md5( $canonical ) );
		if ( is_array( $data ) && ! empty( $data['last_sent'] ) ) {
			$remaining = ( (int) $data['last_sent'] + (int) $cfg['resend_wait'] ) - time();
			if ( $remaining > 0 ) {
				return array(
					'ok'          => false,
					'code'        => 'resend_wait',
					'message'     => 'Please wait before requesting another code.',
					'resend_wait' => $remaining,
				);
			}
		}

		// Daily caps per phone and per IP.
		$phone_key = self::RL_PHONE . md5( $canonical );
		$ip_key    = self::RL_IP . md5( self::client_ip() );
		$max       = (int) $cfg['max_per_day'];
		if ( $max > 0 ) {
			if ( (int) get_transient( $phone_key ) >= $max || (int) get_transient( $ip_key ) >= $max ) {
				return array(
					'ok'      => false,
					'code'    => 'rate_limited',
					'message' => 'Daily OTP limit reached. Please try again tomorrow.',
				);
			}
		}

		$otp = self::generate( $cfg['otp_length'] );

		set_transient(
			self::STORE . md5( $canonical ),
			array(
				'otp_hash'  => self::hash_otp( $otp ),
				'expires'   => time() + (int) $cfg['otp_expiry'],
				'attempts'  => 0,
				'last_sent' => time(),
			),
			(int) $cfg['otp_expiry'] + 60
		);

		set_transient( $phone_key, ( (int) get_transient( $phone_key ) ) + 1, DAY_IN_SECONDS );
		set_transient( $ip_key, ( (int) get_transient( $ip_key ) ) + 1, DAY_IN_SECONDS );

		if ( self::dev_mode() ) {
			return array(
				'ok'         => true,
				'code'       => 'sent',
				'message'    => 'DEV MODE: OTP not sent by SMS.',
				'expires_in' => (int) $cfg['otp_expiry'],
				'dev_otp'    => $otp,
			);
		}

		$minutes = max( 1, (int) round( (int) $cfg['otp_expiry'] / 60 ) );
		$body    = str_replace(
			array( '[site]', '[otp]', '[min]' ),
			array( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $otp, (string) $minutes ),
			(string) $cfg['sms_template']
		);

		$body = self::append_app_hash( $body, $app_hash );

		$sent = AUN_App_SMS::send( $canonical, $body );
		if ( ! $sent['success'] ) {
			delete_transient( self::STORE . md5( $canonical ) );
			return array(
				'ok'      => false,
				'code'    => 'sms_failed',
				'message' => 'Could not send the SMS. Please try again.',
			);
		}

		return array(
			'ok'         => true,
			'code'       => 'sent',
			'message'    => 'OTP sent.',
			'expires_in' => (int) $cfg['otp_expiry'],
		);
	}

	/**
	 * Verify a submitted OTP for a canonical phone.
	 *
	 * @param string $canonical 8801XXXXXXXXX
	 * @param string $otp       Submitted code.
	 * @return array{ok:bool,code:string,message:string}
	 */
	public static function verify( $canonical, $otp ) {
		$cfg = aun_app_api_otp_settings();
		$key = self::STORE . md5( $canonical );

		$data = get_transient( $key );
		if ( ! is_array( $data ) || empty( $data['otp_hash'] ) ) {
			return array(
				'ok'      => false,
				'code'    => 'no_otp',
				'message' => 'No active code. Please request a new one.',
			);
		}

		if ( time() > (int) $data['expires'] ) {
			delete_transient( $key );
			return array(
				'ok'      => false,
				'code'    => 'expired',
				'message' => 'The code has expired. Please request a new one.',
			);
		}

		if ( hash_equals( (string) $data['otp_hash'], self::hash_otp( $otp ) ) ) {
			delete_transient( $key );
			return array(
				'ok'      => true,
				'code'    => 'verified',
				'message' => 'OK',
			);
		}

		$data['attempts'] = (int) $data['attempts'] + 1;
		if ( $data['attempts'] >= (int) $cfg['max_attempts'] ) {
			delete_transient( $key );
			return array(
				'ok'      => false,
				'code'    => 'locked',
				'message' => 'Too many wrong tries. Please request a new code.',
			);
		}
		set_transient( $key, $data, max( 60, (int) $data['expires'] - time() ) );

		return array(
			'ok'      => false,
			'code'    => 'mismatch',
			'message' => 'Wrong code. Please try again.',
		);
	}
}
