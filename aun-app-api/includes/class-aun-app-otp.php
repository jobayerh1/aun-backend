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
	 * The visitor's IP, for rate limiting — Cloudflare-aware, and not spoofable.
	 *
	 * ⚠️ This used to take the first of CF-Connecting-IP, X-Forwarded-For and
	 * REMOTE_ADDR that parsed. Both headers are plain text any client can send,
	 * so anyone reaching the server directly (not through Cloudflare) could
	 * put a new value in them on every request and get a fresh per-IP bucket
	 * each time. Here that bucket is the daily OTP cap — the one limit standing
	 * between a script and a bill for thousands of SMS.
	 *
	 * Same rule as aun_sp_client_ip() in AUN Spare Parts 0.43.0: the Cloudflare
	 * header is believed only when REMOTE_ADDR really is a Cloudflare edge (or a
	 * private/loopback proxy on the host itself). X-Forwarded-For is never used —
	 * behind Cloudflare it adds nothing CF-Connecting-IP does not already give.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$remote = self::unmap_ip( isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '' );
		$cf     = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? trim( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] ) : '';

		if ( '' !== $cf && filter_var( $cf, FILTER_VALIDATE_IP ) && self::is_trusted_proxy( $remote ) ) {
			return $cf;
		}
		return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
	}

	/** "::ffff:1.2.3.4" (IPv4 written as IPv6, which some servers report) -> "1.2.3.4". */
	private static function unmap_ip( $ip ) {
		if ( 0 === stripos( (string) $ip, '::ffff:' ) && filter_var( substr( $ip, 7 ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return substr( $ip, 7 );
		}
		return (string) $ip;
	}

	/**
	 * Whether REMOTE_ADDR is a proxy whose forwarded-IP header may be believed.
	 *
	 * ⚠️ The list must stay complete. The OTP cap is PER DAY, so a Cloudflare
	 * edge missing from it would pool every visitor arriving through that edge
	 * into one bucket and lock them all out of login until tomorrow. It matched
	 * cloudflare.com/ips-v4 and ips-v6 exactly (22 ranges) on 2026-09-10, and
	 * is identical to the spare-parts plugin's list. Filterable, so a new range
	 * can be added without a release.
	 */
	private static function is_trusted_proxy( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		// Private / loopback / reserved = a proxy on the host's own network.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return true;
		}
		$ranges = apply_filters( 'aun_app_trusted_proxies', array(
			'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
			'141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
			'197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
			'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
			'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
			'2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
		) );
		foreach ( (array) $ranges as $cidr ) {
			if ( self::ip_in_cidr( $ip, $cidr ) ) {
				return true;
			}
		}
		return false;
	}

	/** IPv4 / IPv6 CIDR membership, compared byte by byte (no GMP/BCMath needed). */
	private static function ip_in_cidr( $ip, $cidr ) {
		$parts = explode( '/', (string) $cidr, 2 );
		$ipb   = @inet_pton( (string) $ip );
		$netb  = @inet_pton( $parts[0] );
		if ( false === $ipb || false === $netb || strlen( $ipb ) !== strlen( $netb ) ) {
			return false;
		}
		$bits  = isset( $parts[1] ) ? (int) $parts[1] : strlen( $ipb ) * 8;
		$whole = intdiv( $bits, 8 );
		if ( substr( $ipb, 0, $whole ) !== substr( $netb, 0, $whole ) ) {
			return false;
		}
		$rest = $bits % 8;
		if ( 0 === $rest ) {
			return true;
		}
		$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
		return ( ord( $ipb[ $whole ] ) & $mask ) === ( ord( $netb[ $whole ] ) & $mask );
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

	/** Where a code is kept: login codes and "confirm this number" codes never share a slot. */
	private static function store_key( $canonical, $purpose = '' ) {
		$purpose = sanitize_key( (string) $purpose );
		return self::STORE . ( '' !== $purpose ? $purpose . '_' : '' ) . md5( $canonical );
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
	public static function request( $canonical, $app_hash = '', $purpose = '' ) {
		// $purpose keeps a code for one job from ever being accepted for
		// another: a "confirm this number" code (purpose 'purchase') lives in
		// its own store, so it can never be used to LOG IN, and vice versa.
		$store = self::store_key( $canonical, $purpose );
		$cfg = aun_app_api_otp_settings();

		// Resend cooldown.
		$data = get_transient( $store );
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
			$store,
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

		$minutes  = max( 1, (int) round( (int) $cfg['otp_expiry'] / 60 ) );
		$template = 'purchase' === $purpose
			? '[otp] is your AUN Care code to confirm this number and find your purchases. Valid [min] min. Do not share it.'
			: (string) $cfg['sms_template'];
		$body     = str_replace(
			array( '[site]', '[otp]', '[min]' ),
			array( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $otp, (string) $minutes ),
			$template
		);

		$body = self::append_app_hash( $body, $app_hash );

		$sent = AUN_App_SMS::send( $canonical, $body );
		if ( ! $sent['success'] ) {
			delete_transient( $store );
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
	public static function verify( $canonical, $otp, $purpose = '' ) {
		$cfg = aun_app_api_otp_settings();
		$key = self::store_key( $canonical, $purpose );

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
