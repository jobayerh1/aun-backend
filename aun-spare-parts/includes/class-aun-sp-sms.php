<?php
/**
 * Customer SMS via Alpha SMS (sms.net.bd).
 *
 * Reuses the credentials configured for the Alpha SMS plugin (option `alpha_sms`).
 * If the aun-alpha-otp-login helper is active we delegate to its tested sender;
 * otherwise we call the documented endpoint directly with the same stored key — so
 * the store owner never enters the API key twice.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_SMS {

	const API_URL      = 'https://api.sms.net.bd/sendsms';
	const ALPHA_OPTION = 'alpha_sms';

	public static function is_configured() {
		if ( class_exists( 'AUN_Alpha_OTP_SMS' ) ) {
			return AUN_Alpha_OTP_SMS::is_configured();
		}
		$o = get_option( self::ALPHA_OPTION, array() );
		return is_array( $o ) && ! empty( $o['api_key'] );
	}

	/**
	 * Send an SMS. $phone may be in any local form (01…, +880…); we canonicalise.
	 *
	 * @return array{success:bool,message:string}
	 */
	public static function send( $phone, $message ) {
		$to = self::to_intl( $phone );
		if ( $to === '' ) {
			return array( 'success' => false, 'message' => 'No valid phone number.' );
		}
		$message = self::gsm_safe( $message );

		// Prefer the OTP helper's sender (already battle-tested) when available.
		if ( class_exists( 'AUN_Alpha_OTP_SMS' ) ) {
			return AUN_Alpha_OTP_SMS::send( $to, $message );
		}

		$o   = get_option( self::ALPHA_OPTION, array() );
		$key = ( is_array( $o ) && ! empty( $o['api_key'] ) ) ? trim( (string) $o['api_key'] ) : '';
		if ( $key === '' ) {
			return array( 'success' => false, 'message' => 'Alpha SMS API key is not configured.' );
		}

		$fields = array( 'api_key' => $key, 'to' => $to, 'msg' => $message );
		if ( ! empty( $o['sender_id'] ) ) {
			$fields['sender_id'] = trim( (string) $o['sender_id'] );
		}

		$resp = wp_remote_post( self::API_URL, array( 'timeout' => 30, 'body' => $fields ) );
		if ( is_wp_error( $resp ) ) {
			error_log( 'AUN SP SMS: ' . $resp->get_error_message() );
			return array( 'success' => false, 'message' => $resp->get_error_message() );
		}
		$json = json_decode( wp_remote_retrieve_body( $resp ) );
		$ok   = ( 200 === (int) wp_remote_retrieve_response_code( $resp ) && is_object( $json ) && isset( $json->error ) && 0 === (int) $json->error );
		return array(
			'success' => $ok,
			'message' => ( is_object( $json ) && isset( $json->msg ) ) ? (string) $json->msg : '',
		);
	}

	/**
	 * Swap typographic characters for their plain ASCII equivalents.
	 *
	 * An English SMS is 160 characters per part ONLY while every character is in the
	 * GSM-7 alphabet. A single em dash or curly quote — the sort of thing that lands
	 * in a message from a status label or a pasted note — silently drops the whole
	 * message to UCS-2, i.e. 70 characters per part, so a one-part SMS becomes three.
	 * Bangla is UCS-2 either way and is left untouched.
	 */
	public static function gsm_safe( $message ) {
		$message = strtr( (string) $message, array(
			'—' => '-', '–' => '-', '‑' => '-', '…' => '...',
			'‘' => "'", '’' => "'", '“' => '"', '”' => '"',
			'·' => '-', '→' => '->',
		) );
		// ৳ and × are not GSM-7 either, but they read naturally in a Bangla message —
		// which is UCS-2 regardless, so swapping them there would cost nothing and gain
		// nothing. Only drop them when they are the ONLY thing standing between this
		// message and the 160-character alphabet.
		$stripped = strtr( $message, array( '৳' => '', '×' => '' ) );
		if ( ! preg_match( '/[^\x20-\x7E\r\n]/', $stripped ) ) {
			$message = strtr( $message, array( '৳' => 'Tk ', '×' => 'x' ) );
		}
		return $message;
	}

	/* ------------------------------------------------------- Tracked send + background retry */

	const MAX_ATTEMPTS = 3;

	/** Wait before each retry: 5 min after the 1st failure, 30 min after the 2nd (rides out an Alpha busy spell). */
	private static function retry_delay( $attempt ) {
		return ( 1 === $attempt ? 5 : 30 ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Send a customer SMS tied to a request. Every attempt is logged into the
	 * request's activity, and a failed send is retried in the background
	 * (Alpha SMS has had busy spells where a single synchronous attempt
	 * silently lost the message). After the final failure the admin is emailed.
	 *
	 * @return array{success:bool,message:string}
	 */
	public static function send_tracked( $request_id, $phone, $message, $label = 'notification', $attempt = 1 ) {
		$res = self::send( $phone, $message );
		$to  = self::to_intl( $phone );

		if ( $res['success'] ) {
			self::log_event( $request_id, 'SMS sent (' . $label . ') to ' . $to . ( $attempt > 1 ? ' on retry #' . $attempt : '' ) . ': ' . $message );
			return $res;
		}

		$err = $res['message'] !== '' ? $res['message'] : 'no response from Alpha SMS';
		if ( $attempt < self::MAX_ATTEMPTS ) {
			$delay = self::retry_delay( $attempt );
			$args  = array( (int) $request_id, (string) $phone, (string) $message, (string) $label, $attempt + 1 );
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + $delay, 'aun_sp_sms_retry', $args, 'aun-sp' );
			} else {
				wp_schedule_single_event( time() + $delay, 'aun_sp_sms_retry', $args );
			}
			self::log_event( $request_id, 'SMS FAILED (' . $label . ', attempt ' . $attempt . '/' . self::MAX_ATTEMPTS . '): ' . $err . ' — retrying in ' . round( $delay / 60 ) . ' min' );
		} else {
			self::log_event( $request_id, 'SMS FAILED permanently (' . $label . ') after ' . self::MAX_ATTEMPTS . ' attempts: ' . $err . ' — contact the customer manually. Message was: ' . $message );
			$admin = get_option( 'aun_sp_alert_email', get_option( 'admin_email' ) );
			if ( $admin ) {
				wp_mail(
					$admin,
					'[AUN Spare Parts] SMS could not be delivered',
					'The ' . $label . ' SMS for request #' . (int) $request_id . ' could not be delivered after ' . self::MAX_ATTEMPTS . " attempts.\n"
					. 'Last error: ' . $err . "\nPhone: " . $to . "\n\nMessage:\n" . $message . "\n\nPlease contact the customer manually (call/WhatsApp)."
				);
			}
		}
		return $res;
	}

	/** Background retry handler (hooked to aun_sp_sms_retry in the main plugin file). */
	public static function retry( $request_id, $phone, $message, $label, $attempt ) {
		self::send_tracked( (int) $request_id, (string) $phone, (string) $message, (string) $label, (int) $attempt );
	}

	/** Append a line to the request's activity (type 'sms' — shown to the admin, hidden from public tracking). */
	private static function log_event( $request_id, $message ) {
		global $wpdb;
		if ( ! $request_id ) {
			return;
		}
		$wpdb->insert( AUN_SP_Install::table( 'events' ), array(
			'request_id' => (int) $request_id,
			'item_id'    => 0,
			'type'       => 'sms',
			'message'    => $message,
			'by_user'    => 'system',
			'created_at' => current_time( 'mysql' ),
		) );
	}

	/** 01XXXXXXXXX -> 8801XXXXXXXXX (Alpha expects the country code). */
	public static function to_intl( $phone ) {
		$d = preg_replace( '/\D+/', '', (string) $phone );
		if ( $d === '' ) {
			return '';
		}
		if ( strpos( $d, '880' ) === 0 ) {
			return $d;
		}
		if ( strlen( $d ) === 11 && $d[0] === '0' ) {
			return '88' . $d;
		}
		if ( strlen( $d ) === 10 && $d[0] === '1' ) {
			return '880' . $d;
		}
		return $d;
	}
}
