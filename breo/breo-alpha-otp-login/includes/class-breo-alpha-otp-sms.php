<?php
/**
 * SMS sender — talks to the Alpha SMS (sms.net.bd) HTTP API.
 *
 * Credentials are read from the Alpha SMS plugin's own option so the store owner
 * configures the API key in one place only. This class deliberately does NOT depend
 * on the Alpha SMS plugin's PHP classes (which may change between versions); it just
 * borrows the stored key + sender id and calls the documented endpoint directly.
 *
 * @package Breo_Alpha_OTP_Login
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Breo_Alpha_OTP_SMS {

	const API_URL = 'https://api.sms.net.bd/sendsms';

	/**
	 * Read [api_key, sender_id] from the Alpha SMS plugin option.
	 *
	 * @return array{api_key:string,sender_id:string}
	 */
	public static function get_credentials() {
		$opts = get_option( BREO_ALPHA_OTP_ALPHA_OPTION, array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		$key = isset( $opts['api_key'] ) ? trim( (string) $opts['api_key'] ) : '';
		$sid = isset( $opts['sender_id'] ) ? trim( (string) $opts['sender_id'] ) : '';

		// breo.bd may run without the Alpha SMS plugin: fall back to our own key.
		if ( '' === $key ) {
			$own = breo_alpha_otp_get_options();
			$key = trim( (string) $own['api_key'] );
			$sid = trim( (string) $own['sender_id'] );
		}

		return array(
			'api_key'   => $key,
			'sender_id' => $sid,
		);
	}

	/**
	 * Where the key comes from: 'alpha_sms' (the Alpha SMS plugin), 'own' (saved in
	 * our settings) or '' (none).
	 *
	 * @return string
	 */
	public static function key_source() {
		$opts = get_option( BREO_ALPHA_OTP_ALPHA_OPTION, array() );
		if ( is_array( $opts ) && ! empty( $opts['api_key'] ) && '' !== trim( (string) $opts['api_key'] ) ) {
			return 'alpha_sms';
		}
		$own = breo_alpha_otp_get_options();
		return '' !== trim( (string) $own['api_key'] ) ? 'own' : '';
	}

	/**
	 * Whether an Alpha SMS API key is present.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$creds = self::get_credentials();
		return '' !== $creds['api_key'];
	}

	/**
	 * Send an SMS.
	 *
	 * @param string $to   Recipient number (canonical 8801XXXXXXXXX form).
	 * @param string $body Message text.
	 *
	 * @return array{success:bool,message:string} success flag + provider/error message.
	 */
	public static function send( $to, $body ) {
		$creds = self::get_credentials();

		if ( '' === $creds['api_key'] ) {
			return array(
				'success' => false,
				'message' => 'Alpha SMS API key is not configured.',
			);
		}

		$fields = array(
			'api_key' => $creds['api_key'],
			'to'      => $to,
			'msg'     => $body,
		);

		if ( '' !== $creds['sender_id'] ) {
			$fields['sender_id'] = $creds['sender_id'];
		}

		$response = wp_remote_post(
			self::API_URL,
			array(
				'method'    => 'POST',
				'timeout'   => 30,
				'sslverify' => true,
				'body'      => $fields,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$json = json_decode( $raw );

		// sms.net.bd returns {"error":0,"msg":"...","data":{...}} on success.
		if ( 200 === $code && is_object( $json ) && isset( $json->error ) && 0 === (int) $json->error ) {
			return array(
				'success' => true,
				'message' => isset( $json->msg ) ? (string) $json->msg : 'OK',
			);
		}

		$message = 'SMS gateway error.';
		if ( is_object( $json ) && isset( $json->msg ) ) {
			$message = (string) $json->msg;
		} elseif ( 200 !== $code ) {
			$message = 'SMS gateway HTTP ' . $code;
		}

		return array(
			'success' => false,
			'message' => $message,
		);
	}
}
