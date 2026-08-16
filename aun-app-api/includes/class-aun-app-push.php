<?php
/**
 * FCM push notifications (HTTP v1 API, service-account auth — no SDK).
 *
 * The Firebase service-account JSON is pasted into AUN App → Settings and
 * lives in the options table (never a web-readable file). Access tokens are
 * minted with a locally signed RS256 JWT and cached ~50 minutes.
 *
 * Device tokens live ON the login-token rows (aun_app_tokens.fcm_token), so a
 * push target dies together with the session it belongs to: logout or token
 * expiry silently stops push for that phone. FCM "UNREGISTERED" responses
 * prune stale tokens.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Push {

	const TOKEN_CACHE = 'aun_app_fcm_oauth';

	/** Decoded service account, or null. */
	private static function service_account() {
		$raw = (string) ( aun_app_api_get_options()['fcm_service_account'] ?? '' );
		if ( '' === trim( $raw ) ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data )
			|| empty( $data['client_email'] )
			|| empty( $data['private_key'] )
			|| empty( $data['project_id'] ) ) {
			return null;
		}
		return $data;
	}

	public static function configured() {
		return null !== self::service_account() && function_exists( 'openssl_sign' );
	}

	/** Base64url without padding (JWT alphabet). */
	private static function b64url( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * OAuth2 access token for the FCM scope (cached ~50 min; Google issues
	 * 60-min tokens).
	 *
	 * @return string '' on failure.
	 */
	public static function access_token() {
		$cached = get_transient( self::TOKEN_CACHE );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$sa = self::service_account();
		if ( ! $sa ) {
			return '';
		}

		$now    = time();
		$header = self::b64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claims = self::b64url( wp_json_encode( array(
			'iss'   => $sa['client_email'],
			'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		) ) );

		$signature = '';
		$key       = openssl_pkey_get_private( (string) $sa['private_key'] );
		if ( ! $key || ! openssl_sign( "$header.$claims", $signature, $key, 'sha256WithRSAEncryption' ) ) {
			error_log( 'AUN APP API: FCM JWT signing failed (check the service-account private key).' );
			return '';
		}
		$jwt = "$header.$claims." . self::b64url( $signature );

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout' => 15,
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			),
		) );
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$token = (string) ( $body['access_token'] ?? '' );
		if ( '' !== $token ) {
			set_transient( self::TOKEN_CACHE, $token, 50 * MINUTE_IN_SECONDS );
		}
		return $token;
	}

	/**
	 * Send one push message to one device token.
	 *
	 * @param string $fcm_token Device token.
	 * @param string $title     Notification title.
	 * @param string $body      Notification body.
	 * @param array  $data      String-map payload (notice id/type for the app).
	 * @return string 'sent' | 'unregistered' | 'failed'
	 */
	/**
	 * Map a notification type to the Android channel the app created for it.
	 *
	 * @param string $type Notice type (ticket/repair/content/maintenance/…).
	 * @return string Channel id.
	 */
	/**
	 * The app build in which the `_v2` notification channels first existed.
	 *
	 * Android will not display a notification whose channel the app has never
	 * created — silently, with no error anywhere. So a push must never name a
	 * channel newer than the build it is going to.
	 */
	const CHANNELS_V2_BUILD = 69;

	/**
	 * Channel id for a notice type, for a SPECIFIC app build.
	 *
	 * @param string $type  Notice type.
	 * @param int    $build App build number (0 = unknown).
	 * @return string
	 */
	public static function channel_for_build( $type, $build ) {
		$id = self::channel_for_type( $type );

		// Unknown build (a token registered before the app started reporting
		// it) is treated as OLD. Guessing "new" would lose the notification
		// entirely; guessing "old" at worst costs the brand sound on a phone
		// that would have played it, and the next login corrects the record.
		if ( (int) $build < self::CHANNELS_V2_BUILD ) {
			return preg_replace( '/_v2$/', '', $id );
		}
		return $id;
	}

	public static function channel_for_type( $type ) {
		// ⚠️ The _v2 suffix MUST match MainActivity.kt exactly. Android freezes
		// a channel's sound the first time it is created, so shipping the brand
		// chime required brand-new ids; a push naming a channel the app has not
		// created falls back to the device default and loses both the sound and
		// the user's per-type controls. Change these two lists together.
		switch ( $type ) {
			case 'ticket':
				return 'aun_support_v2';
			case 'repair':
				return 'aun_repairs_v2';
			case 'parts':
				return 'aun_parts_v2';
			case 'referral':
				return 'aun_referral_v2';
			case 'maintenance':
				return 'aun_reminders_v2';
			case 'firmware':
			case 'manual':
			case 'video':
			case 'tip':
			case 'content':
				return 'aun_content_v2';
			default:
				return 'aun_default_v2';
		}
	}

	public static function send_to_token( $fcm_token, $title, $body, $data = array(), $build = 0 ) {
		$sa     = self::service_account();
		$bearer = self::access_token();
		if ( ! $sa || '' === $bearer ) {
			return 'failed';
		}

		// FCM data values must all be strings.
		$data_map = array();
		foreach ( (array) $data as $k => $v ) {
			$data_map[ (string) $k ] = (string) $v;
		}

		// Route each push to the right Android notification channel so users get
		// per-category controls (created by the app — see MainActivity.kt).
		// Chosen for THIS phone's build: naming a channel the app does not have
		// makes Android drop the notification without a word.
		$channel = self::channel_for_build( (string) ( $data_map['type'] ?? '' ), (int) $build );

		$response = wp_remote_post(
			'https://fcm.googleapis.com/v1/projects/' . rawurlencode( $sa['project_id'] ) . '/messages:send',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $bearer,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'message' => array(
						'token'        => (string) $fcm_token,
						'notification' => array(
							'title' => (string) $title,
							'body'  => (string) $body,
						),
						'data'         => $data_map,
						'android'      => array(
							'priority'     => 'high',
							'notification' => array(
								'channel_id' => $channel,
								// Status-bar icon. Android keeps only the ALPHA
								// channel here and paints it white, so the
								// full-bleed square launcher icon we used to name
								// arrived as a white square. ic_stat_aun is a
								// transparent-background projector silhouette.
								//
								// Safe for older installs: an icon name the APK
								// does not have falls back to the launcher icon
								// (this is NOT the channel case, where an unknown
								// id makes the notification vanish silently).
								'icon'       => 'ic_stat_aun',
								'color'      => '#0188FE',
							),
						),
					),
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'failed';
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			return 'sent';
		}

		$body_raw = (string) wp_remote_retrieve_body( $response );
		if ( 404 === $code || false !== strpos( $body_raw, 'UNREGISTERED' )
			|| false !== strpos( $body_raw, 'INVALID_ARGUMENT' ) ) {
			return 'unregistered'; // app uninstalled / token rotated → prune
		}
		return 'failed';
	}

	/**
	 * Push a (possibly bilingual) message to every logged-in device of a set
	 * of users. Each device gets the language it registered with.
	 *
	 * @param int[] $user_ids Users.
	 * @param array $msg      {title, body, title_bn, body_bn}
	 * @param array $data     Data payload.
	 * @return int Devices reached.
	 */
	public static function push_to_users( $user_ids, $msg, $data = array() ) {
		global $wpdb;

		$user_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $user_ids ) ) ) );
		if ( empty( $user_ids ) || ! self::configured() ) {
			return 0;
		}

		$t  = aun_app_api_tokens_table();
		$ph = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT id, fcm_token, fcm_lang, fcm_build FROM $t
			 WHERE user_id IN ($ph) AND fcm_token != '' AND expires_at > %s",
			array_merge( $user_ids, array( current_time( 'mysql' ) ) )
		) );

		// One phone can appear under several login rows — de-dupe by token.
		$sent = 0;
		$seen = array();
		foreach ( $rows as $row ) {
			$fcm = (string) $row->fcm_token;
			if ( isset( $seen[ $fcm ] ) ) {
				continue;
			}
			$seen[ $fcm ] = true;

			$bn     = 'bn' === (string) $row->fcm_lang;
			$title  = $bn && '' !== (string) ( $msg['title_bn'] ?? '' ) ? $msg['title_bn'] : ( $msg['title'] ?? '' );
			$body   = $bn && '' !== (string) ( $msg['body_bn'] ?? '' ) ? $msg['body_bn'] : ( $msg['body'] ?? '' );
			$result = self::send_to_token( $fcm, $title, $body, $data, (int) $row->fcm_build );

			if ( 'sent' === $result ) {
				$sent++;
			} elseif ( 'unregistered' === $result ) {
				$wpdb->update( $t, array( 'fcm_token' => '' ), array( 'id' => (int) $row->id ) );
			}
		}
		return $sent;
	}
}
