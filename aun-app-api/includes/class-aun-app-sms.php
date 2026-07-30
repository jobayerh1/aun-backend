<?php
/**
 * SMS sender — Alpha SMS (sms.net.bd) HTTP API.
 *
 * By default borrows the API key + sender id stored by the Alpha SMS plugin
 * (option `alpha_sms`) so credentials are configured in one place. Callers may
 * pass explicit credentials instead (the warranty notifications use the SLB
 * Warranty plugin's own key).
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_SMS {

	const API_URL = 'https://api.sms.net.bd/sendsms';

	/**
	 * Read [api_key, sender_id] from the Alpha SMS plugin option.
	 *
	 * @return array{api_key:string,sender_id:string}
	 */
	public static function get_credentials() {
		$opts = get_option( AUN_APP_ALPHA_OPTION, array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}

		return array(
			'api_key'   => isset( $opts['api_key'] ) ? trim( (string) $opts['api_key'] ) : '',
			'sender_id' => isset( $opts['sender_id'] ) ? trim( (string) $opts['sender_id'] ) : '',
		);
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
	 * @param string     $to    Recipient (canonical 8801XXXXXXXXX form).
	 * @param string     $body  Message text.
	 * @param array|null $creds Optional override: [api_key, sender_id, api_url].
	 *
	 * @return array{success:bool,message:string}
	 */
	public static function send( $to, $body, $creds = null ) {
		if ( ! is_array( $creds ) || empty( $creds['api_key'] ) ) {
			$creds = self::get_credentials();
		}

		if ( empty( $creds['api_key'] ) ) {
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
		if ( ! empty( $creds['sender_id'] ) ) {
			$fields['sender_id'] = $creds['sender_id'];
		}

		$url = ! empty( $creds['api_url'] ) ? $creds['api_url'] : self::API_URL;

		$response = wp_remote_post(
			$url,
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
