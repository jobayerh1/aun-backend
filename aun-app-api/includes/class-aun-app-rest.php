<?php
/**
 * REST routes for the Android app (namespace aun-app/v1).
 *
 * Auth model: the app logs in with phone + OTP and receives a long-lived bearer
 * token. Authenticated calls send it as `Authorization: Bearer <token>` AND as
 * `X-AUN-Token: <token>` — some shared hosts strip the Authorization header, so
 * the custom header is checked first.
 *
 * Every response has the same envelope:
 *   success  { "success": true,  "data": ... }
 *   failure  { "success": false, "error": { "code": "...", "message": "..." } }
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_REST {

	/**
	 * Token row for the current authenticated request.
	 *
	 * @var object|null
	 */
	private $token_row = null;

	/* --------------------------------------------------------------------- *
	 * Route registration
	 * --------------------------------------------------------------------- */

	public function register_routes() {
		$ns = AUN_APP_API_NS;

		// ── Public ──
		register_rest_route( $ns, '/ping', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'ping' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/config', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_config' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/auth/request-otp', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'auth_request_otp' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/auth/verify-otp', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'auth_verify_otp' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/models', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_models' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/dealers', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_dealers' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/content', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_content' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/models/(?P<id>\d+)/content', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_model_content' ),
			'permission_callback' => '__return_true',
		) );

		// "What to watch tonight" picks for the home screen (public, cached).
		register_rest_route( $ns, '/watch', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'watch_picks' ),
			'permission_callback' => '__return_true',
		) );

		// v1.12: one content item by id — the deep link behind a tapped
		// "new firmware/manual/video/tip" push notification.
		register_rest_route( $ns, '/content/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_content_item' ),
			'permission_callback' => '__return_true',
		) );

		// v13: firmware/manual DOWNLOAD redirect. The app downloads from this
		// stable URL; the server resolves the real (authenticated OneDrive or
		// direct) URL at request time and 302s to it. Public — firmware/manuals
		// are the same downloads offered on the public website.
		register_rest_route( $ns, '/content/(?P<id>\d+)/file', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'content_file' ),
			'permission_callback' => '__return_true',
		) );

		// v1.7: real HTML page hosting the YouTube embed. YouTube refuses
		// iframe embeds from anonymous WebViews ("Video unavailable, 152-4");
		// served from this domain the player sees a genuine website embed —
		// the same as a video embedded on any page of the site.
		register_rest_route( $ns, '/embed', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'video_embed' ),
			'permission_callback' => '__return_true',
		) );

		// v1.10: repair job-sheet photo proxy. Same pattern (and the same
		// guards) as the website tracker's slb/v1/repair-image: the app talks
		// only to this site, the ERP host stays private, and only real images
		// are ever forwarded. Public like the website's, because an
		// Image.network request carries no bearer token.
		register_rest_route( $ns, '/repair-image', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'repair_image' ),
			'permission_callback' => '__return_true',
		) );

		// ── Authenticated ──
		$auth = array( $this, 'auth_required' );

		register_rest_route( $ns, '/auth/logout', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'auth_logout' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/me', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_me' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_me' ),
				'permission_callback' => $auth,
			),
		) );

		// Account deletion (required by Google Play for any app with accounts).
		// POST, not DELETE, so it travels through every proxy/WAF unchanged.
		register_rest_route( $ns, '/me/delete', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'delete_me' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/me/devices', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_my_devices' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/devices/register', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'register_device' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/devices/remove', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'remove_device' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/me/avatar', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_avatar' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/warranty/check', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'warranty_check' ),
			'permission_callback' => $auth,
		) );

		// ── v1.1: the resolve "brain", purchase lookup, after-sales services ──

		register_rest_route( $ns, '/device/resolve', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'device_resolve' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/devices/by-purchase-phone', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'devices_by_purchase_phone' ),
			'permission_callback' => $auth,
		) );

		// PUBLIC on purpose: the projector planner is the one feature a customer
		// uses BEFORE they own anything, so requiring a login would gate the
		// only part of the app that can win a sale.
		register_rest_route( $ns, '/projectors', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'projectors' ),
			// Public, but signs the caller in when they DO have a token so the
			// planner can open on the projector they already own.
			'permission_callback' => array( $this, 'auth_optional' ),
		) );

		register_rest_route( $ns, '/parts/catalog', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'parts_catalog' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/parts/request', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'parts_request' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/me/referral', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'referral_summary' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/me/referral/claim', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'referral_claim' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/parts/pay', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'parts_pay' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/parts/decision', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'parts_decision' ),
			'permission_callback' => $auth,
		) );

		// The projector finder. Open to everyone — this is the one feature in the
		// app aimed at someone who has not bought anything yet, and putting a
		// login in front of "help me choose" would ask for a phone number before
		// giving any reason to trust us with it.
		register_rest_route( $ns, '/finder', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'finder' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $ns, '/parts/revive', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'parts_revive' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/repairs/request', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'repairs_request' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/me/service-requests', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'my_service_requests' ),
			'permission_callback' => $auth,
		) );

		// v1.5: live repair detail (app ref RP-… or ERP job sheet 2025/0001,
		// passed as ?ref= because job sheet numbers contain a slash).
		register_rest_route( $ns, '/me/repair-detail', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'repair_detail' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/me/content', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'my_content' ),
			'permission_callback' => $auth,
		) );

		// ── v1.9: in-app notification centre ──
		register_rest_route( $ns, '/me/notifications', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'notifications_feed' ),
			'permission_callback' => $auth,
		) );
		register_rest_route( $ns, '/me/notifications/read', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'notifications_read' ),
			'permission_callback' => $auth,
		) );
		register_rest_route( $ns, '/me/notifications/dismiss', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'notifications_dismiss' ),
			'permission_callback' => $auth,
		) );
		// v1.37: maintenance reminders become actionable, like a task rather
		// than a message — "I cleaned it" / "remind me later".
		register_rest_route( $ns, '/me/notifications/complete', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'notifications_complete' ),
			'permission_callback' => $auth,
		) );
		register_rest_route( $ns, '/me/notifications/snooze', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'notifications_snooze' ),
			'permission_callback' => $auth,
		) );

		// ── v1.9: bug reports ──
		register_rest_route( $ns, '/feedback', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'submit_feedback' ),
			'permission_callback' => $auth,
		) );

		// ── v1.11: push token registration + model-aware help center ──
		register_rest_route( $ns, '/me/fcm-token', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'save_fcm_token' ),
			'permission_callback' => $auth,
		) );
		register_rest_route( $ns, '/help-center', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'help_center' ),
			'permission_callback' => '__return_true',
		) );

		// v1.13: osTicket support tickets (proxied through the bridge — the
		// app never talks to the support host directly).
		register_rest_route( $ns, '/tickets/topics', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'tickets_topics' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/me/tickets', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'tickets_list' ),
				'permission_callback' => $auth,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'tickets_create' ),
				'permission_callback' => $auth,
			),
		) );

		register_rest_route( $ns, '/me/tickets/thread', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'tickets_thread' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/me/tickets/reply', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'tickets_reply' ),
			'permission_callback' => $auth,
		) );

		register_rest_route( $ns, '/me/tickets/attachment', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'tickets_attachment' ),
			'permission_callback' => $auth,
		) );
	}

	/**
	 * Store the FCM device token ON the current login-token row, so push
	 * targeting lives and dies with the session (logout/expiry = no more push
	 * to that phone). Language rides along for bilingual notifications.
	 */
	public function save_fcm_token( $request ) {
		global $wpdb;

		if ( ! $this->token_row ) {
			return $this->err( 'unauthorized', 'Login required.', 401 );
		}

		$fcm = trim( (string) $request->get_param( 'token' ) );
		if ( '' === $fcm || strlen( $fcm ) > 255 || ! preg_match( '/^[A-Za-z0-9_\-:.]+$/', $fcm ) ) {
			return $this->err( 'invalid_token', 'Invalid device token.', 400 );
		}
		$lang = 'en' === (string) $request->get_param( 'lang' ) ? 'en' : 'bn';

		// Which app build this token belongs to.
		//
		// Not bookkeeping — it decides whether a push is DELIVERED. Android
		// refuses to display a notification naming a channel the app has not
		// created, and the channel ids changed when the brand sound shipped
		// (see MainActivity.kt). Without knowing the build, the server names
		// the new channels at a phone that only has the old ones, and every
		// push vanishes in silence: no error, no log, nothing on screen.
		$build = max( 0, (int) $request->get_param( 'build' ) );

		$wpdb->update( aun_app_api_tokens_table(), array(
			'fcm_token' => $fcm,
			'fcm_lang'  => $lang,
			'fcm_build' => $build,
		), array( 'id' => (int) $this->token_row->id ) );

		// One physical phone = one FCM token: clear it from any OTHER login
		// row (old sessions, other accounts on the same phone) so nobody gets
		// pushes for an account they logged out of.
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . aun_app_api_tokens_table() . " SET fcm_token = '' WHERE fcm_token = %s AND id != %d",
			$fcm,
			(int) $this->token_row->id
		) );

		return $this->ok( array( 'saved' => true ) );
	}

	public function help_center( $request ) {
		return $this->ok( AUN_App_Help::bundle( (int) $request->get_param( 'model_id' ) ) );
	}

	/* --------------------------------------------------------------------- *
	 * Support tickets (osTicket bridge)
	 * --------------------------------------------------------------------- */

	/** Shared WP_Error → REST error mapping for bridge calls. */
	private function tickets_err( $error ) {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) && 404 === (int) $data['status'] ? 404 : 502;
		return $this->err( $error->get_error_code(), $error->get_error_message(), $status );
	}

	public function tickets_topics() {
		if ( ! AUN_App_Tickets::configured() ) {
			return $this->err( 'tickets_disabled', 'Support tickets are not available right now.', 503 );
		}
		$topics = AUN_App_Tickets::topics();
		if ( is_wp_error( $topics ) ) {
			return $this->tickets_err( $topics );
		}
		// The app only needs id + name; forms are mapped server-side.
		$out = array();
		foreach ( $topics as $topic ) {
			$out[] = array(
				'id'   => (int) ( $topic['id'] ?? 0 ),
				'name' => (string) ( $topic['name'] ?? '' ),
			);
		}
		return $this->ok( $out );
	}

	public function tickets_list() {
		if ( ! AUN_App_Tickets::configured() ) {
			return $this->ok( array( 'enabled' => false, 'tickets' => array() ) );
		}
		$me   = $this->identity();
		$list = AUN_App_Tickets::list_for( $me );
		if ( is_wp_error( $list ) ) {
			return $this->tickets_err( $list );
		}
		return $this->ok( array( 'enabled' => true, 'tickets' => $list ) );
	}

	public function tickets_create( $request ) {
		if ( ! AUN_App_Tickets::configured() ) {
			return $this->err( 'tickets_disabled', 'Support tickets are not available right now.', 503 );
		}
		$me      = $this->identity();
		$subject = sanitize_text_field( (string) $request->get_param( 'subject' ) );
		$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		if ( strlen( trim( $subject ) ) < 3 ) {
			return $this->err( 'invalid_subject', 'Please write a short subject.', 400 );
		}
		if ( strlen( trim( $message ) ) < 10 ) {
			return $this->err( 'invalid_message', 'Please describe your issue in a few words.', 400 );
		}

		$files = AUN_App_Tickets::sanitize_uploads( $request->get_param( 'attachments' ) );
		if ( is_wp_error( $files ) ) {
			return $this->err( $files->get_error_code(), $files->get_error_message(), 400 );
		}

		$result = AUN_App_Tickets::create(
			$me,
			(int) $request->get_param( 'topic_id' ),
			$subject,
			$message,
			sanitize_text_field( (string) $request->get_param( 'serial' ) ),
			substr( sanitize_text_field( (string) $request->get_param( 'app_version' ) ), 0, 30 ),
			$files
		);
		if ( is_wp_error( $result ) ) {
			return $this->tickets_err( $result );
		}
		return $this->ok( $result, 201 );
	}

	public function tickets_thread( $request ) {
		if ( ! AUN_App_Tickets::configured() ) {
			return $this->err( 'tickets_disabled', 'Support tickets are not available right now.', 503 );
		}
		$me     = $this->identity();
		$thread = AUN_App_Tickets::thread( $me, sanitize_text_field( (string) $request->get_param( 'number' ) ) );
		if ( is_wp_error( $thread ) ) {
			return $this->tickets_err( $thread );
		}
		return $this->ok( $thread );
	}

	public function tickets_reply( $request ) {
		if ( ! AUN_App_Tickets::configured() ) {
			return $this->err( 'tickets_disabled', 'Support tickets are not available right now.', 503 );
		}
		$me      = $this->identity();
		$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		if ( '' === trim( $message ) ) {
			return $this->err( 'invalid_message', 'Please write a message.', 400 );
		}
		$files = AUN_App_Tickets::sanitize_uploads( $request->get_param( 'attachments' ) );
		if ( is_wp_error( $files ) ) {
			return $this->err( $files->get_error_code(), $files->get_error_message(), 400 );
		}

		$result = AUN_App_Tickets::reply(
			$me,
			sanitize_text_field( (string) $request->get_param( 'number' ) ),
			$message,
			$files
		);
		if ( is_wp_error( $result ) ) {
			return $this->tickets_err( $result );
		}
		return $this->ok( $result, 201 );
	}

	/**
	 * Stream one ticket attachment to the app. Raw bytes (echo + exit), like
	 * the repair-image proxy — but AUTH-gated (private customer data): the
	 * permission callback already required a valid token, and the bridge
	 * re-checks that the attachment belongs to a ticket this customer owns.
	 */
	public function tickets_attachment( $request ) {
		if ( ! AUN_App_Tickets::configured() ) {
			return $this->err( 'tickets_disabled', 'Support tickets are not available right now.', 503 );
		}
		$me     = $this->identity();
		$result = AUN_App_Tickets::fetch_attachment(
			$me,
			sanitize_text_field( (string) $request->get_param( 'number' ) ),
			(int) $request->get_param( 'ref' )
		);
		if ( is_wp_error( $result ) ) {
			return $this->tickets_err( $result );
		}

		$name = '' !== $result['name'] ? $result['name'] : ( 'attachment-' . (int) $request->get_param( 'ref' ) );
		if ( ! headers_sent() ) {
			header( 'Content-Type: ' . $result['type'] );
			header( 'Content-Disposition: attachment; filename="' . rawurlencode( $name ) . '"' );
			header( 'Content-Length: ' . strlen( $result['body'] ) );
			header( 'X-Content-Type-Options: nosniff' );
		}
		echo $result['body']; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public function notifications_feed() {
		$me = $this->identity();
		// Opportunistically pull staff ticket replies so the notification centre
		// (and push to the customer's other devices) stays fresh even when the
		// site's WP-Cron is idle between visits. Globally throttled so it never
		// hammers the bridge — the 10-minute cron remains the primary driver.
		if ( class_exists( 'AUN_App_Tickets' ) && AUN_App_Tickets::configured()
			&& ! get_transient( 'aun_app_tickets_poll_lock' ) ) {
			set_transient( 'aun_app_tickets_poll_lock', 1, 3 * MINUTE_IN_SECONDS );
			AUN_App_Tickets::poll_replies();
		}
		return $this->ok( AUN_App_Notices::feed( $me['user_id'], $me['phone'] ) );
	}

	public function notifications_read( $request ) {
		$me  = $this->identity();
		$ids = $request->get_param( 'ids' );
		AUN_App_Notices::mark_read(
			$me['user_id'],
			$me['phone'],
			is_array( $ids ) ? array_map( 'intval', $ids ) : null // null = all
		);
		$feed = AUN_App_Notices::feed( $me['user_id'], $me['phone'] );
		return $this->ok( array( 'unread' => $feed['unread'] ) );
	}

	public function notifications_dismiss( $request ) {
		$me = $this->identity();
		$id = (int) $request->get_param( 'id' );
		if ( $id < 1 ) {
			return $this->err( 'invalid_id', 'Invalid notification.', 400 );
		}
		AUN_App_Notices::dismiss( $me['user_id'], $id );
		return $this->ok( array( 'dismissed' => true ) );
	}

	/** "Yes, I cleaned it" on a maintenance reminder. */
	public function notifications_complete( $request ) {
		$me = $this->identity();
		$id = (int) $request->get_param( 'id' );
		if ( $id < 1 ) {
			return $this->err( 'invalid_id', 'Invalid notification.', 400 );
		}
		AUN_App_Notices::complete( $me['user_id'], $id );
		$feed = AUN_App_Notices::feed( $me['user_id'], $me['phone'] );
		return $this->ok( array( 'completed' => true, 'unread' => $feed['unread'] ) );
	}

	/** "Remind me later" on a maintenance reminder. */
	public function notifications_snooze( $request ) {
		$me   = $this->identity();
		$id   = (int) $request->get_param( 'id' );
		$days = (int) $request->get_param( 'days' );
		if ( $id < 1 ) {
			return $this->err( 'invalid_id', 'Invalid notification.', 400 );
		}
		if ( $days < 1 ) {
			$days = 3; // sensible default if the app ever omits it
		}
		AUN_App_Notices::snooze( $me['user_id'], $id, $days );
		$feed = AUN_App_Notices::feed( $me['user_id'], $me['phone'] );
		return $this->ok( array( 'snoozed' => true, 'unread' => $feed['unread'] ) );
	}

	public function submit_feedback( $request ) {
		global $wpdb;
		$me = $this->identity();

		$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		if ( strlen( trim( $message ) ) < 10 ) {
			return $this->err( 'invalid_message', 'Please describe the problem in a few words.', 400 );
		}

		// Optional single screenshot (base64 image only). Stored in the media
		// library; a bad/oversized image is skipped rather than failing the report.
		$image_url = AUN_App_Content::store_feedback_image( $request->get_param( 'image' ) );

		$ok = $wpdb->insert( $wpdb->prefix . 'aun_app_feedback', array(
			'user_id'       => $me['user_id'],
			'customer_name' => $me['customer_name'],
			'phone'         => $me['phone'],
			'app_version'   => substr( sanitize_text_field( (string) $request->get_param( 'app_version' ) ), 0, 30 ),
			'device_info'   => substr( sanitize_text_field( (string) $request->get_param( 'device_info' ) ), 0, 191 ),
			'message'       => $message,
			'status'        => 'new',
			'image_url'     => $image_url,
			'created_at'    => current_time( 'mysql' ),
		) );

		if ( ! $ok ) {
			return $this->err( 'db_error', 'Could not save the report. Please try again.', 500 );
		}
		delete_transient( 'aun_app_new_feedback_count' ); // refresh the admin-bar badge
		return $this->ok( array( 'received' => true ), 201 );
	}

	public function my_content( $request ) {
		$me   = $this->identity();
		$type = sanitize_key( (string) $request->get_param( 'type' ) );

		$model_ids = AUN_App_Warranty::available()
			? AUN_App_Warranty::user_model_ids( $me['phone'], $me['user_id'] )
			: array();

		return $this->ok( AUN_App_Content::for_models( $model_ids, $type ) );
	}

	/** Common identity bundle for the service endpoints (app profile, not WP account). */
	private function identity() {
		$user      = wp_get_current_user();
		$canonical = AUN_App_Phone::user_phone( $user->ID );
		return array(
			'user_id'       => (int) $user->ID,
			'customer_name' => AUN_App_Profile::get_name( $user->ID ),
			'phone'         => $canonical ? $canonical : '',
			'email'         => AUN_App_Profile::get_email( $user->ID ),
		);
	}

	public function device_resolve( $request ) {
		if ( ! AUN_App_Warranty::available() ) {
			return $this->err( 'warranty_unavailable', 'Warranty service is temporarily unavailable.', 503 );
		}

		$me = $this->identity();
		if ( '' === $me['phone'] ) {
			return $this->err( 'no_phone', 'Your account has no phone number.', 400 );
		}

		// Same anti-scraping cap as warranty/check.
		$key   = 'aun_app_wchk_' . $me['user_id'];
		$count = (int) get_transient( $key );
		if ( $count >= 30 ) {
			return $this->err( 'rate_limited', 'Too many lookups today. Please try again tomorrow.', 429 );
		}
		set_transient( $key, $count + 1, DAY_IN_SECONDS );

		$result = AUN_App_Warranty::resolve_serial( (string) $request->get_param( 'serial' ), $me );

		if ( 'invalid' === ( $result['state'] ?? '' ) ) {
			return $this->err( 'invalid_serial', 'Please enter a valid serial number.', 400 );
		}

		return $this->ok( $result );
	}

	public function devices_by_purchase_phone( $request ) {
		$me = $this->identity();

		$key   = 'aun_app_pbp_' . $me['user_id'];
		$count = (int) get_transient( $key );
		if ( $count >= 10 ) {
			return $this->err( 'rate_limited', 'Too many lookups today. Please try again tomorrow.', 429 );
		}
		set_transient( $key, $count + 1, DAY_IN_SECONDS );

		$result = AUN_App_Warranty::purchases_by_phone(
			(string) $request->get_param( 'phone' ),
			array( 'user_id' => $me['user_id'], 'phone' => $me['phone'] )
		);

		if ( is_wp_error( $result ) ) {
			$code   = $result->get_error_code();
			$status = 'invalid_phone' === $code ? 400 : 503;
			return $this->err( $code, $result->get_error_message(), $status );
		}

		return $this->ok( $result );
	}

	/**
	 * Projector catalogue + optics for the in-app planner.
	 *
	 * No auth: a prospective buyer has no account yet. When the caller IS
	 * logged in we additionally say which catalogue entry matches each of their
	 * registered projectors, so the planner can open on the one they own rather
	 * than making an existing customer pick their own model out of a list.
	 */
	public function projectors( $request ) {
		$models = AUN_App_Projectors::catalogue();

		$mine = array();
		// identity() is safe to call unauthenticated — it returns an empty
		// phone/user for an anonymous caller, which is the normal case here.
		$me = $this->identity();
		if ( ! empty( $me['user_id'] ) && class_exists( 'AUN_App_Warranty' ) && AUN_App_Warranty::available() ) {
			foreach ( (array) AUN_App_Warranty::get_devices( $me['phone'], (int) $me['user_id'] ) as $device ) {
				// By ERP id, not by name — see AUN_App_Projectors::product_for_device().
				$product_id = AUN_App_Projectors::product_for_device( $device );
				if ( $product_id > 0 ) {
					$mine[] = array(
						'serial'     => (string) ( $device['serial'] ?? '' ),
						'model'      => (string) ( $device['model'] ?? '' ),
						'product_id' => $product_id,
					);
				}
			}
		}

		return $this->ok( array(
			'models' => $models,
			'mine'   => $mine,
		) );
	}

	/** This customer's referral code, stats and earned rewards. */
	public function referral_summary() {
		$me = $this->identity();
		return $this->ok( AUN_App_Referrals::summary( (int) $me['user_id'] ) );
	}

	/**
	 * Claim a friend's referral code.
	 *
	 * Every fraud check lives in AUN_App_Referrals::claim(); this only shapes
	 * the reply. The rejection REASON is passed straight through, because
	 * "referral codes are for first-time customers" is a fair answer that
	 * saves a support message, whereas a generic failure would not.
	 */
	public function referral_claim( $request ) {
		$me   = $this->identity();
		$code = (string) $request->get_param( 'code' );

		$result = AUN_App_Referrals::claim( (int) $me['user_id'], $code );
		if ( empty( $result['ok'] ) ) {
			return $this->err(
				(string) $result['code'],
				(string) $result['message'],
				'unavailable' === $result['code'] ? 503 : 400
			);
		}
		return $this->ok( array(
			'coupon'  => (string) ( $result['coupon'] ?? '' ),
			'message' => (string) $result['message'],
			// The number this coupon is locked to. Told here, at the moment of
			// success, rather than discovered at checkout when it fails.
			'phone'   => (string) ( $result['phone'] ?? '' ),
		) );
	}

	public function parts_catalog() {
		return $this->ok( array(
			'available' => AUN_App_Services::parts_available(),
			'parts'     => AUN_App_Services::parts_catalog(),
			// The website's own per-part limit (filter aun_sp_max_qty), so the
			// app never hardcodes a number that can drift from the site.
			'max_qty'   => AUN_App_Services::parts_max_qty(),
		) );
	}

	public function parts_request( $request ) {
		$me = $this->identity();
		if ( '' === $me['phone'] ) {
			return $this->err( 'no_phone', 'Your account has no phone number.', 400 );
		}

		// Spare parts are for existing customers only: the request must be tied
		// to a projector the customer actually owns (direct purchase or their
		// registration), so the model can never be free-typed.
		$serial = (string) $request->get_param( 'serial' );
		if ( '' === $serial || ! AUN_App_Warranty::owns_serial( $me['user_id'], $me['phone'], $serial ) ) {
			return $this->err( 'not_your_device', 'Please choose one of your projectors.', 400 );
		}

		// Proof photos arrive as photo_<partkey> multipart fields.
		$photos = array();
		foreach ( (array) $request->get_file_params() as $field => $file ) {
			if ( 0 === strpos( $field, 'photo_' ) ) {
				$photos[ sanitize_key( substr( $field, 6 ) ) ] = $file;
			}
		}

		$parts_raw = (string) $request->get_param( 'parts' ); // comma-separated keys

		// Quantities arrive as "key:n,key:n" alongside the parts list — same
		// multipart-friendly shape as `parts`. Missing/!valid entries fall back
		// to 1, so an older app build keeps working exactly as before.
		$qty     = array();
		$qty_raw = (string) $request->get_param( 'qty' );
		if ( '' !== $qty_raw ) {
			foreach ( explode( ',', $qty_raw ) as $pair ) {
				$bits = explode( ':', $pair, 2 );
				if ( 2 === count( $bits ) ) {
					$qty[ sanitize_key( $bits[0] ) ] = (int) $bits[1];
				}
			}
		}

		$result = AUN_App_Services::create_part_request( array(
			'user_id'       => $me['user_id'],
			'customer_name' => '' !== (string) $request->get_param( 'name' ) ? (string) $request->get_param( 'name' ) : $me['customer_name'],
			'phone'         => $me['phone'],
			'address'       => (string) $request->get_param( 'address' ),
			'model'         => (string) $request->get_param( 'model' ),
			'purchase_date' => (string) $request->get_param( 'purchase_date' ),
			'serial'        => (string) $request->get_param( 'serial' ),
			'note'          => (string) $request->get_param( 'note' ),
			'parts'         => array_filter( explode( ',', $parts_raw ) ),
			'qty'           => $qty,
			'photos'        => $photos,
		) );

		if ( ! $result['ok'] ) {
			$status = 'db_error' === $result['code'] ? 500 : ( 'parts_unavailable' === $result['code'] ? 503 : 400 );
			return $this->err( $result['code'], $result['message'], $status );
		}

		return $this->ok( array( 'ref' => $result['ref'] ), 201 );
	}

	public function repairs_request( $request ) {
		$me = $this->identity();
		if ( '' === $me['phone'] ) {
			return $this->err( 'no_phone', 'Your account has no phone number.', 400 );
		}

		// Send-in repair is a customer service too — tie it to an owned device.
		$serial = (string) $request->get_param( 'serial' );
		if ( '' === $serial || ! AUN_App_Warranty::owns_serial( $me['user_id'], $me['phone'], $serial ) ) {
			return $this->err( 'not_your_device', 'Please choose one of your projectors.', 400 );
		}

		$photos = array();
		foreach ( (array) $request->get_file_params() as $field => $file ) {
			if ( 0 === strpos( $field, 'photo' ) ) {
				$photos[] = $file;
			}
		}

		$result = AUN_App_Services::create_repair( array(
			'user_id'       => $me['user_id'],
			'customer_name' => '' !== (string) $request->get_param( 'name' ) ? (string) $request->get_param( 'name' ) : $me['customer_name'],
			'phone'         => $me['phone'],
			'address'       => (string) $request->get_param( 'address' ),
			'serial'        => (string) $request->get_param( 'serial' ),
			'model'         => (string) $request->get_param( 'model' ),
			'issue'         => (string) $request->get_param( 'issue' ),
			'photos'        => $photos,
		) );

		if ( ! $result['ok'] ) {
			$status = 'db_error' === $result['code'] ? 500 : 400;
			return $this->err( $result['code'], $result['message'], $status );
		}

		return $this->ok( array( 'ref' => $result['ref'] ), 201 );
	}

	public function repair_detail( $request ) {
		$me = $this->identity();
		if ( '' === $me['phone'] ) {
			return $this->err( 'no_phone', 'Your account has no phone number.', 400 );
		}

		$detail = AUN_App_Services::repair_detail(
			(string) $request->get_param( 'ref' ),
			$me['phone'],
			$me['user_id']
		);
		if ( null === $detail ) {
			return $this->err( 'not_found', 'That repair was not found on your account.', 404 );
		}
		return $this->ok( $detail );
	}

	/**
	 * Customer approves or declines a spare-parts quote from inside the app.
	 *
	 * The decision itself is the spare-parts plugin's job (one shared
	 * implementation with the public tracking page — see
	 * AUN_SP_Requests::customer_decision), so all this does is authorise.
	 *
	 * Authorisation matters here in a way it doesn't on the web tracker: that
	 * page is reached by an SMS link and identifies a request by its ref alone,
	 * but every app caller is a known account, so we must confirm the request
	 * actually belongs to THEIR phone. Without this check any logged-in user
	 * could approve someone else's quote by guessing a sequential SP- ref.
	 */
	/**
	 * Start an online payment for a spare-parts request.
	 *
	 * Mints the WooCommerce order on demand and hands back its checkout URL —
	 * exactly what the website tracker's own "Pay online" button does, calling
	 * the same two plugin methods. Cash on delivery needs no order, which is
	 * why nothing is created until this is called.
	 *
	 * **The app never talks to SSLCommerz itself.** The gateway is configured
	 * once in WooCommerce and the customer pays on the site, so the merchant
	 * credentials stay on the server: an APK can be unzipped by anyone, and a
	 * store ID and password shipped inside one are simply published. Paying on
	 * the site also means the gateway's own callback marks the order paid, which
	 * is what drives the plugin's "payment received" SMS, the activity log and
	 * the refund trail. A payment taken inside the app would settle at the bank
	 * and leave every one of those records empty.
	 */
	public function parts_pay( $request ) {
		$me  = $this->identity();
		$ref = strtoupper( trim( (string) $request->get_param( 'ref' ) ) );

		if ( '' === $ref ) {
			return $this->err( 'invalid', 'Invalid request.', 400 );
		}
		// Online payment arrived in AUN Spare Parts 0.29.0 and the two plugins
		// are updated separately — fail cleanly against an older copy.
		if ( ! AUN_App_Services::parts_available()
			|| ! class_exists( 'AUN_SP_Woo' )
			|| ! method_exists( 'AUN_SP_Woo', 'create_order' ) ) {
			return $this->err( 'unavailable', 'Online payment is not available right now. You can still pay cash on delivery.', 503 );
		}
		if ( ! AUN_SP_Woo::is_active() ) {
			return $this->err( 'unavailable', 'Online payment is not available right now. You can still pay cash on delivery.', 503 );
		}
		if ( '' === $me['phone'] ) {
			return $this->err( 'no_phone', 'No phone number on your account.', 403 );
		}

		// The request must belong to THIS account. The website identifies a
		// request by ref alone because it is reached from an SMS link, but every
		// app caller is a known account — without this check anyone could mint
		// an order against someone else's request by guessing a ref.
		global $wpdb;
		$t_req    = AUN_SP_Install::table( 'requests' );
		$variants = AUN_App_Phone::variants( $me['phone'] );
		$ph       = implode( ',', array_fill( 0, count( $variants ), '%s' ) );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, overall_status, quote_total, delivery_charge FROM $t_req
			 WHERE ref = %s AND ( phone_current IN ($ph) OR phone_onfile IN ($ph) ) LIMIT 1",
			array_merge( array( $ref ), $variants, $variants )
		) );
		if ( ! $row ) {
			return $this->err( 'not_found', 'That request was not found on your account.', 404 );
		}
		if ( in_array( $row->overall_status, array( 'rejected', 'declined' ), true ) ) {
			return $this->err( 'unavailable', 'This request is closed, so there is nothing to pay.', 400 );
		}
		// A lapsed quote is a price we stopped standing behind. Taking money
		// against it would commit us to a figure we deliberately let expire —
		// and it would also silently approve it, since paying counts as a yes
		// further down. They ask for a fresh quote instead.
		if ( 'expired' === $row->overall_status ) {
			return $this->err( 'expired', 'This quote has expired. Ask us for a new one and we will re-check the price.', 409 );
		}

		// Reuse an order that is already correct.
		//
		// create_order() REBUILDS an unpaid order from current prices and logs
		// "order #9292 refreshed" every time — so a customer who tapped Pay,
		// changed their mind, and tapped again collected a line of accounting
		// per tap in their own status history. Rebuilding is the right thing
		// when the price has moved; it is pure noise when nothing has changed.
		//
		// Compared against the CURRENT payable, never assumed: if the admin has
		// edited a quantity or a price since, the totals differ and we fall
		// through to the rebuild, which is exactly when it earns its keep.
		$payable = (float) $row->quote_total
			+ ( isset( $row->delivery_charge ) ? (float) $row->delivery_charge : 0.0 );

		$existing = AUN_App_Services::payment_summary( (int) $row->id );
		if ( ! empty( $existing['pay_url'] ) && empty( $existing['paid'] ) ) {
			$same = abs( (float) str_replace( ',', '', (string) $existing['total'] ) - $payable ) < 0.01;
			if ( $same ) {
				$order = AUN_SP_Woo::order_for( (int) $row->id );
				return $this->ok(
					$this->pay_payload( $order ? $order->get_id() : 0, $ref, $existing )
				);
			}
		}

		// Paying is a stronger "yes" than pressing Approve, so a customer who
		// goes straight to payment approves the quote implicitly — otherwise the
		// request stays stuck awaiting a decision even after the money arrives.
		// Same rule as the website; the audit log records 'payment' as the
		// channel either way.
		if ( 'quote_sent' === $row->overall_status
			&& method_exists( 'AUN_SP_Requests', 'customer_decision' ) ) {
			AUN_SP_Requests::customer_decision( (int) $row->id, 'approve', 'payment' );
		}

		// Order creation reaches deep into WooCommerce — products, taxes,
		// shipping, whatever gateway plugins have hooked in. A fatal anywhere in
		// there used to take the whole request down and hand the app a WordPress
		// error PAGE, which it then showed the customer as raw HTML. Catch it:
		// the customer gets a sentence, the admin gets the real cause in the
		// error log, and cash on delivery still works.
		// Other plugins hook order creation and assume a front-end request.
		// WooCommerce does not start a session for REST calls, so without this
		// a single `WC()->session->get()` in any of them is a fatal error.
		AUN_App_Services::ensure_wc_context();

		// Watch what gets created while we are inside create_order().
		//
		// wc_create_order() makes an EMPTY order first and the items, billing
		// name and total are added afterwards. If anything fatals in between —
		// which is exactly what a third-party hook was doing — the empty shell
		// survives as a ৳0 "Pending payment" order with no customer name. A
		// handful of those in the orders list is confusing; a year of them is a
		// mess nobody can safely clean up later. So we remember what we made
		// and take it back if the attempt did not finish.
		$made = array();
		$watch = static function ( $new_id ) use ( &$made ) {
			$made[] = (int) $new_id;
		};
		add_action( 'woocommerce_new_order', $watch, 1 );

		try {
			$order_id = AUN_SP_Woo::create_order( (int) $row->id );
		} catch ( Throwable $e ) {
			remove_action( 'woocommerce_new_order', $watch, 1 );
			self::discard_empty_orders( $made );
			error_log( sprintf(
				'AUN APP API: spare-parts order creation failed for %s (request %d) — %s in %s:%d',
				$ref,
				(int) $row->id,
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			) );
			// Also kept where the shop owner can actually read it. Most people
			// running a WordPress site cannot get at the PHP error log, and
			// "there has been a critical error" is not a bug report.
			update_option( 'aun_app_last_pay_error', array(
				'time'    => current_time( 'mysql' ),
				'ref'     => $ref,
				'message' => $e->getMessage(),
				'where'   => $e->getFile() . ':' . $e->getLine(),
			), false );

			// The customer gets a sentence, never the internals.
			return $this->err(
				'order_failed',
				'Could not start the payment just now. Please try again in a moment, or pay cash on delivery.',
				503
			);
		}

		remove_action( 'woocommerce_new_order', $watch, 1 );

		if ( ! $order_id ) {
			self::discard_empty_orders( $made );
			// Nothing threw, but no order came back. This is the QUIET failure
			// mode and it needs recording just as much as a fatal: WooCommerce's
			// own `wc_create_order()` catches Exception internally and returns a
			// WP_Error, so a third-party hook that throws an Exception (rather
			// than an Error, as connect-yeamazing did) lands here instead of in
			// the catch above — with nothing to show the admin unless we write
			// it down ourselves.
			update_option( 'aun_app_last_pay_error', array(
				'time'    => current_time( 'mysql' ),
				'ref'     => $ref,
				'message' => 'WooCommerce returned no order. Something hooked to order creation refused or failed silently — check the PHP error log around this time.',
				'where'   => 'AUN_SP_Woo::create_order()',
			), false );

			return $this->err( 'unavailable', 'Could not start the payment. Please try again, or pay cash on delivery.', 503 );
		}

		$summary = AUN_App_Services::payment_summary( (int) $row->id );
		if ( empty( $summary['pay_url'] ) ) {
			// Nothing left to pay — treat as success so the app can just refresh
			// and show the paid state rather than an error the customer would
			// read as a failure.
			return $this->err( 'already_paid', 'This request is already paid — thank you.', 409 );
		}

		return $this->ok( $this->pay_payload( (int) $order_id, $ref, $summary ) );
	}

	/**
	 * Delete order shells left behind by an attempt that did not finish.
	 *
	 * Conservative on purpose. An order is only removed when it is ALL of:
	 * unpaid and pending, worth nothing, holding no items, carrying no customer
	 * name and no spare-parts reference. A real order cannot be all five, and
	 * anything that fails even one test is left exactly where it is — an
	 * over-eager cleanup of someone's orders would be far worse than the mess
	 * it tidies.
	 *
	 * @param int[] $ids Order ids created during a failed attempt.
	 * @return void
	 */
	private static function discard_empty_orders( $ids ) {
		foreach ( array_unique( array_map( 'intval', (array) $ids ) ) as $id ) {
			if ( $id < 1 || ! function_exists( 'wc_get_order' ) ) {
				continue;
			}
			$o = wc_get_order( $id );
			if ( ! $o
				|| $o->is_paid()
				|| 'pending' !== $o->get_status()
				|| (float) $o->get_total() > 0
				|| count( $o->get_items() ) > 0
				|| '' !== trim( (string) $o->get_billing_first_name() )
				|| '' !== (string) $o->get_meta( '_aun_sp_ref' ) ) {
				continue;
			}
			$o->delete( true );
		}
	}

	/**
	 * The URL the app should open to pay this order, and how it was obtained.
	 *
	 * Prefers a DIRECT SSLCommerz session: the customer lands on the payment
	 * screen itself, with no AUN page in between — no method chooser, no terms
	 * checkbox, no intermediate redirect, and therefore nothing to hide behind
	 * an overlay. That is how a payment inside an app is supposed to feel, and
	 * it removes four fragile moving parts rather than adding any.
	 *
	 * Falls back to WooCommerce's own checkout page whenever a session cannot
	 * be created — credentials not found, gateway unreachable, session refused.
	 * The fallback is the flow that has already taken real money on this site,
	 * so a bad day at the gateway's API costs a plainer screen, not a sale.
	 *
	 * @param int    $order_id WooCommerce order.
	 * @param string $ref      Spare-parts reference.
	 * @param array  $summary  Payment summary (for the fallback URL + totals).
	 * @return array
	 */
	private function pay_payload( $order_id, $ref, $summary ) {
		$out = array(
			'total'  => (string) ( $summary['total'] ?? '' ),
			'number' => (string) ( $summary['number'] ?? '' ),
		);

		$order = $order_id ? wc_get_order( $order_id ) : null;

		if ( $order && class_exists( 'AUN_App_SSLCommerz' ) && AUN_App_SSLCommerz::available() ) {
			$url = AUN_App_SSLCommerz::create_session( $order, $ref );
			if ( ! is_wp_error( $url ) ) {
				$out['pay_url'] = $url;
				$out['direct']  = true;
				return $out;
			}
			// Worth recording: a gateway that has started refusing sessions is
			// invisible otherwise, because the fallback quietly keeps working.
			error_log( 'AUN APP API: SSLCommerz session failed, falling back to checkout — ' . $url->get_error_message() );
			update_option( 'aun_app_last_pay_error', array(
				'time'    => current_time( 'mysql' ),
				'ref'     => $ref,
				'message' => 'Direct gateway session failed (used the website checkout instead): ' . $url->get_error_message(),
				'where'   => 'AUN_App_SSLCommerz::create_session()',
			), false );
		}

		// `aun_app=1` renders that checkout without the theme's header, footer
		// and menus — see aun_app_api_checkout_chrome().
		$out['pay_url'] = add_query_arg( 'aun_app', '1', (string) ( $summary['pay_url'] ?? '' ) );
		$out['direct']  = false;
		return $out;
	}

	public function parts_decision( $request ) {
		$me       = $this->identity();
		$ref      = strtoupper( trim( (string) $request->get_param( 'ref' ) ) );
		$decision = (string) $request->get_param( 'decision' );

		if ( '' === $ref || ! in_array( $decision, array( 'approve', 'decline' ), true ) ) {
			return $this->err( 'invalid', 'Invalid request.', 400 );
		}
		// method_exists as well as parts_available(): customer_decision() ships in
		// AUN Spare Parts 0.22.0, and the two plugins are updated separately. If
		// this one is newer, fail cleanly instead of fataling on a missing method.
		if ( ! AUN_App_Services::parts_available()
			|| ! method_exists( 'AUN_SP_Requests', 'customer_decision' ) ) {
			return $this->err( 'unavailable', 'Spare parts service is temporarily unavailable.', 503 );
		}
		if ( '' === $me['phone'] ) {
			return $this->err( 'no_phone', 'No phone number on your account.', 403 );
		}

		global $wpdb;
		$t_req    = AUN_SP_Install::table( 'requests' );
		$variants = AUN_App_Phone::variants( $me['phone'] );
		$ph       = implode( ',', array_fill( 0, count( $variants ), '%s' ) );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, overall_status FROM $t_req
			 WHERE ref = %s AND ( phone_current IN ($ph) OR phone_onfile IN ($ph) ) LIMIT 1",
			array_merge( array( $ref ), $variants, $variants )
		) );
		if ( ! $row ) {
			return $this->err( 'not_found', 'That request was not found on your account.', 404 );
		}

		$result = AUN_SP_Requests::customer_decision( (int) $row->id, $decision, 'app' );
		if ( empty( $result['ok'] ) ) {
			// already_answered is the common one: they answered by SMS link first.
			return $this->err(
				(string) $result['code'],
				(string) $result['message'],
				'already_answered' === $result['code'] ? 409 : 400
			);
		}

		return $this->ok( array(
			'ref'    => $ref,
			'status' => (string) $result['code'],
		) );
	}

	/**
	 * A product's price as PLAIN TEXT, fit to drop straight into the app.
	 *
	 * ⚠️ Do not go back to `wp_strip_all_tags( get_price_html() )`. Two things
	 * went wrong with it, both visible on the customer's screen:
	 *
	 *  1. **Stripping tags does not decode entities.** WooCommerce writes the
	 *     taka sign as `&#2547;` and its spacing as `&nbsp;`, so the app —
	 *     which renders plain text, not HTML — displayed the literal
	 *     "&#2547;&nbsp;14,500". A browser hid this bug; a Text widget cannot.
	 *  2. **`get_price_html()` is a filtered free-for-all.** The EMI plugin
	 *     appends "0% EMIs from ৳2,417/month" to it, so a field the app treats
	 *     as one price arrived as a paragraph of someone else's marketing.
	 *
	 * Built from WooCommerce's own primitives instead: `wc_price()` for the
	 * formatting (currency symbol, thousands separator and decimals all follow
	 * the store's settings, so the app still never invents a format), and the
	 * variable-product range handled explicitly. Then decoded, once, here.
	 */
	private static function price_text( $p ) {
		if ( ! function_exists( 'wc_price' ) ) {
			return '';
		}

		if ( $p->is_type( 'variable' ) ) {
			$min = (float) $p->get_variation_price( 'min', true );
			$max = (float) $p->get_variation_price( 'max', true );
			$txt = ( $min < $max )
				? wc_price( $min ) . ' – ' . wc_price( $max )
				: wc_price( $min );
		} else {
			$price = $p->get_price();
			if ( '' === $price || null === $price ) {
				return '';
			}
			$txt = wc_price( (float) $price );
		}

		return self::plain( $txt );
	}

	/** The struck-through original, or '' when the product is not on sale. */
	private static function sale_before_text( $p ) {
		if ( ! function_exists( 'wc_price' ) || ! $p->is_on_sale() ) {
			return '';
		}
		$regular = $p->is_type( 'variable' )
			? (float) $p->get_variation_regular_price( 'max', true )
			: (float) $p->get_regular_price();

		return $regular > 0 ? self::plain( wc_price( $regular ) ) : '';
	}

	/**
	 * HTML → the plain text a Flutter `Text` widget can actually render.
	 *
	 * Tags out, entities decoded, `&nbsp;` turned into a real space (it decodes
	 * to U+00A0, which is invisible but not a normal space and breaks wrapping
	 * in odd places), and runs of whitespace collapsed.
	 */
	private static function plain( $html ) {
		$txt = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		$txt = str_replace( "\xC2\xA0", ' ', $txt );
		return trim( preg_replace( '/\s+/u', ' ', $txt ) );
	}

	/**
	 * Projector finder: five answers in, the two best matches out.
	 *
	 * ⚠️ The scoring is NOT reimplemented here. It calls the website's own
	 * `AUN_Projector_Wizard::recommend()`, which reads the thresholds an admin
	 * set in wp-admin (brightness cut-offs, budget bands, the portable weight
	 * limit). A second copy in the app would drift the first time one of those
	 * numbers moved, and the app and the website would be recommending
	 * different projectors to the same customer with nobody watching.
	 *
	 * What this method owns is the SHAPE: the plugin's ajax handler answers in
	 * HTML full of Font Awesome markup, which is useless to a Flutter client.
	 */
	public function finder( $request ) {
		if ( ! class_exists( 'AUN_Projector_Wizard' )
			|| ! method_exists( 'AUN_Projector_Wizard', 'recommend' ) ) {
			return $this->err( 'unavailable', 'The projector finder is not available right now.', 503 );
		}

		$answers = $request->get_param( 'answers' );
		$answers = is_array( $answers ) ? $answers : array();

		$keys  = array( 'usage', 'lighting', 'size', 'throw', 'budget' );
		$clean = array();
		foreach ( $keys as $k ) {
			$clean[ $k ] = sanitize_text_field( (string) ( $answers[ $k ] ?? '' ) );
		}
		// Every answer is required. A partial run scores against blanks and
		// returns a confident-looking recommendation based on nothing.
		foreach ( $clean as $k => $v ) {
			if ( '' === $v ) {
				return $this->err( 'incomplete', 'Please answer all five questions.', 400 );
			}
		}

		$res = AUN_Projector_Wizard::recommend( $clean );
		if ( empty( $res['ok'] ) ) {
			return $this->ok( array(
				'matches'      => array(),
				'physics_warn' => false,
			) );
		}

		$ctx     = $res['context'] ?? array();
		$matches = array();

		foreach ( (array) $res['matches'] as $i => $m ) {
			$p = $m['product'];

			// The reason list is the whole point of the finder: a bare product
			// card is a shop, and a shop does not answer "will this work in my
			// room?". Rendered as data (pass/fail + text) so the app styles it
			// natively instead of parsing the website's markup.
			$reasons = array();
			if ( method_exists( 'AUN_Projector_Wizard', 'generate_reasons' ) ) {
				foreach ( (array) AUN_Projector_Wizard::generate_reasons(
					$ctx['usage'], $ctx['lighting'], $ctx['size'], $ctx['throw'],
					$m['ansi'], $m['has_4k'], $m['is_short_throw'], $m['is_android_tv'],
					$m['ram'], $m['weight'], $ctx['port_wt'],
					$ctx['ansi_dim'], $ctx['ansi_bright'],
					$m['price'], $ctx['budget'], $ctx['bgt_entry'], $ctx['bgt_mid']
				) as $r ) {
					$reasons[] = array(
						'ok'   => ( 'pass' === ( $r[0] ?? '' ) ),
						'text' => self::plain( (string) ( $r[2] ?? '' ) ),
					);
				}
			}

			$summary = method_exists( 'AUN_Projector_Wizard', 'generate_ai_summary' )
				? self::plain( (string) AUN_Projector_Wizard::generate_ai_summary(
					$ctx['usage'], $ctx['lighting'], $ctx['size'], $ctx['throw'],
					$m['ansi'], $m['has_4k'], $m['is_short_throw'], $m['is_android_tv'],
					$m['ram'], $m['weight'], $ctx['port_wt']
				) )
				: '';

			// ⚠️ Brightness travels as a CHIP, never as a number. The store's
			// public copy is deliberately qualitative (wizard 3.4.0) because the
			// figures in the descriptions are not measured ANSI — shipping the
			// raw number to the app would republish exactly the claim the site
			// stopped making. See the ANSI-sync decision.
			$chips = array();
			if ( $m['ansi'] >= $ctx['ansi_bright'] ) { $chips[] = 'High brightness'; }
			if ( $m['has_4k'] )        { $chips[] = '4K UHD'; }
			if ( $m['is_short_throw'] ){ $chips[] = 'Short throw'; }
			if ( $m['is_android_tv'] ) { $chips[] = 'Android TV'; }
			elseif ( $m['is_smart'] )  { $chips[] = 'Android Smart'; }
			if ( $m['ram'] > 0 )       { $chips[] = $m['ram'] . 'GB RAM'; }
			if ( $m['weight'] > 0 )    { $chips[] = $m['weight'] . ' kg'; }

			$img = wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' );

			$matches[] = array(
				'id'         => $p->get_id(),
				// Product titles carry entities too — an "&amp;" in a name
				// reaches a Text widget as the literal five characters.
				'name'       => self::plain( $p->get_name() ),
				'image'      => (string) ( $img ?: wc_placeholder_img_src() ),
				'url'        => $p->get_permalink(),
				'price'      => self::price_text( $p ),
				// Only when it is genuinely on sale — the app strikes it out.
				'price_before' => self::sale_before_text( $p ),
				'match_pct'  => (int) AUN_Projector_Wizard::score_to_pct( $m['score'] ),
				'best'       => ( 0 === $i ),
				'summary'    => $summary,
				'reasons'    => $reasons,
				'chips'      => $chips,
				'backorder'  => ( 'onbackorder' === ( $m['stock_status'] ?? '' ) ),
			);
		}

		return $this->ok( array(
			'matches'      => $matches,
			'physics_warn' => (bool) $res['physics_warn'],
		) );
	}

	/**
	 * "I still want this part" on an expired quote.
	 *
	 * Mirrors the website's `aun_sp_revive` ajax action, with the authorisation
	 * the website does not need: the ref must belong to the caller's phone. The
	 * website reaches this from a link tied to one ref; here a guessed ref would
	 * otherwise let anyone reopen a stranger's request and put our staff to work
	 * re-pricing it.
	 *
	 * Everything else — the state claim, the price reset, the admin email — is
	 * the plugin's, so the app and the website can never disagree about what
	 * reviving means.
	 */
	public function parts_revive( $request ) {
		$me  = $this->identity();
		$ref = strtoupper( trim( (string) $request->get_param( 'ref' ) ) );

		if ( '' === $ref ) {
			return $this->err( 'invalid', 'Invalid request.', 400 );
		}
		// revive_quote() ships in AUN Spare Parts 0.31.0; the two plugins are
		// updated separately, so fail cleanly rather than fataling.
		if ( ! AUN_App_Services::parts_available()
			|| ! method_exists( 'AUN_SP_Requests', 'revive_quote' ) ) {
			return $this->err( 'unavailable', 'Spare parts service is temporarily unavailable.', 503 );
		}
		if ( '' === $me['phone'] ) {
			return $this->err( 'no_phone', 'No phone number on your account.', 403 );
		}

		global $wpdb;
		$t_req    = AUN_SP_Install::table( 'requests' );
		$variants = AUN_App_Phone::variants( $me['phone'] );
		$ph       = implode( ',', array_fill( 0, count( $variants ), '%s' ) );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM $t_req
			 WHERE ref = %s AND ( phone_current IN ($ph) OR phone_onfile IN ($ph) ) LIMIT 1",
			array_merge( array( $ref ), $variants, $variants )
		) );
		if ( ! $row ) {
			return $this->err( 'not_found', 'That request was not found on your account.', 404 );
		}

		$result = AUN_SP_Requests::revive_quote( (int) $row->id, 'app' );
		if ( empty( $result['ok'] ) ) {
			// not_expired is the common one: they already tapped this, or the
			// admin re-quoted first. Neither is a failure worth alarming them
			// about, so it carries its own code for the app to soften.
			return $this->err(
				(string) $result['code'],
				(string) $result['message'],
				'not_expired' === $result['code'] ? 409 : 400
			);
		}

		return $this->ok( array(
			'ref'     => $ref,
			'status'  => 'submitted',
			'message' => (string) $result['message'],
		) );
	}

	public function my_service_requests() {
		$me = $this->identity();
		if ( '' === $me['phone'] ) {
			return $this->ok( array(
				'spare_parts' => array(),
				'repairs'     => array(),
			) );
		}
		return $this->ok( AUN_App_Services::my_requests( $me['phone'], $me['user_id'] ) );
	}

	/* --------------------------------------------------------------------- *
	 * Envelope + auth helpers
	 * --------------------------------------------------------------------- */

	private function ok( $data, $status = 200 ) {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	private function err( $code, $message, $status = 400, $extra = array() ) {
		$error = array_merge(
			array(
				'code'    => $code,
				'message' => $message,
			),
			$extra
		);
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => $error,
			),
			$status
		);
	}

	/**
	 * Extract the raw bearer token from the request headers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function bearer( $request ) {
		$custom = trim( (string) $request->get_header( 'x-aun-token' ) );
		if ( '' !== $custom ) {
			return $custom;
		}

		$auth = (string) $request->get_header( 'authorization' );
		if ( 0 === stripos( $auth, 'Bearer ' ) ) {
			return trim( substr( $auth, 7 ) );
		}

		return '';
	}

	/**
	 * permission_callback for authenticated routes. Returns an explicit 401 so
	 * the app can always distinguish "log in again" from other failures.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function auth_required( $request ) {
		$unauthorized = new WP_Error(
			'unauthorized',
			'Login required.',
			array( 'status' => 401 )
		);

		$raw = $this->bearer( $request );
		if ( '' === $raw ) {
			return $unauthorized;
		}

		$row = AUN_App_Tokens::validate( $raw );
		if ( ! $row ) {
			return $unauthorized;
		}

		$user = get_user_by( 'id', (int) $row->user_id );
		if ( ! $user ) {
			return $unauthorized;
		}

		wp_set_current_user( $user->ID );
		$this->token_row = $row;

		return true;
	}

	/**
	 * Permission callback for routes that are PUBLIC but better when signed in.
	 *
	 * Always allows the request, but authenticates first when a valid token
	 * happens to be present. Needed because wp_set_current_user() only runs
	 * inside auth_required(): a route using '__return_true' would see every
	 * caller as anonymous even with a perfectly good token, so any
	 * personalisation on such a route would silently never happen.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true Always.
	 */
	public function auth_optional( $request ) {
		$raw = $this->bearer( $request );
		if ( '' !== $raw ) {
			$row = AUN_App_Tokens::validate( $raw );
			if ( $row ) {
				$user = get_user_by( 'id', (int) $row->user_id );
				if ( $user ) {
					wp_set_current_user( $user->ID );
					$this->token_row = $row;
				}
			}
		}
		return true;
	}

	private function user_payload( $user, $extra = array() ) {
		return AUN_App_Profile::payload( $user, $extra );
	}

	/* --------------------------------------------------------------------- *
	 * Public handlers
	 * --------------------------------------------------------------------- */

	public function ping() {
		return $this->ok( array(
			'pong'    => true,
			'time'    => time(),
			'version' => AUN_APP_API_VERSION,
		) );
	}

	public function get_config() {
		$opts = aun_app_api_get_options();

		$banners = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $opts['banners'] ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( ! empty( $parts[0] ) ) {
				$banners[] = array(
					'image' => esc_url_raw( $parts[0] ),
					'link'  => isset( $parts[1] ) ? esc_url_raw( $parts[1] ) : '',
				);
			}
		}

		return $this->ok( array(
			'support'       => array(
				'whatsapp' => preg_replace( '/\D+/', '', (string) $opts['whatsapp_number'] ),
				'phone'    => (string) $opts['support_phone'],
				'hours'    => (string) $opts['support_hours'],
			),
			'links'         => array(
				'website'  => '' !== $opts['website_url'] ? $opts['website_url'] : home_url( '/' ),
				'facebook' => (string) $opts['facebook_url'],
			),
			// Where to post a projector once a repair is approved. The app
			// shows the "how to send it" card only when there is an address
			// here — a card that says "send it to (blank)" is worse than no
			// card, and the customer would post it nowhere.
			'repair_ship'   => array(
				'name'    => (string) ( $opts['repair_ship_name'] ?? '' ),
				'phone'   => (string) ( $opts['repair_ship_phone'] ?? '' ),
				'address' => (string) ( $opts['repair_ship_address'] ?? '' ),
				'note'    => (string) ( $opts['repair_ship_note'] ?? '' ),
				// The three dropdown answers on Pathao's booking form.
				'city'    => (string) ( $opts['repair_ship_city'] ?? '' ),
				'zone'    => (string) ( $opts['repair_ship_zone'] ?? '' ),
				'area'    => (string) ( $opts['repair_ship_area'] ?? '' ),
			),
			'announcement'  => (string) $opts['announcement'],
			'discount_note' => (string) $opts['discount_note'],
			'banners'       => $banners,
			'login_video'   => (string) $opts['login_video_url'],
			// Weather / holiday / weekend context for the smart greeting.
			'greeting'      => AUN_App_Greeting::context(),
			'app'           => array(
				'latest_version_code' => (int) $opts['latest_version_code'],
				'latest_version_name' => (string) $opts['latest_version_name'],
				// What changed, for the update sheet. A version number is a
				// fact about us; this is a reason for the customer.
				'release_notes'       => (string) ( $opts['release_notes'] ?? '' ),
				'min_version_code'    => (int) $opts['min_version_code'],
				'apk_url'             => (string) $opts['apk_url'],
			),
			'warranty_ready' => AUN_App_Warranty::available(),
			// The Support tab shows the tickets section only when the
			// osTicket bridge is configured.
			'tickets_enabled' => AUN_App_Tickets::configured(),
		) );
	}

	public function auth_request_otp( $request ) {
		$canonical = AUN_App_Phone::normalize( (string) $request->get_param( 'phone' ) );
		if ( ! $canonical ) {
			return $this->err( 'invalid_phone', 'Please enter a valid Bangladeshi mobile number.', 400 );
		}

		$result = AUN_App_OTP::request( $canonical, (string) $request->get_param( 'app_hash' ) );
		if ( ! $result['ok'] ) {
			$status = 'rate_limited' === $result['code'] || 'resend_wait' === $result['code'] ? 429 : 502;
			$extra  = isset( $result['resend_wait'] ) ? array( 'resend_wait' => $result['resend_wait'] ) : array();
			return $this->err( $result['code'], $result['message'], $status, $extra );
		}

		$data = array(
			'masked_phone' => AUN_App_Phone::mask( $canonical ),
			'expires_in'   => $result['expires_in'],
			'resend_wait'  => (int) aun_app_api_otp_settings()['resend_wait'],
			// The app renders exactly this many OTP digits + its instructions,
			// so a 4- or 6-digit SMS never disagrees with the screen.
			'otp_length'   => (int) aun_app_api_otp_settings()['otp_length'],
		);
		if ( isset( $result['dev_otp'] ) ) {
			$data['dev_otp'] = $result['dev_otp']; // AUN_APP_DEV_OTP test sites only.
		}

		return $this->ok( $data );
	}

	public function auth_verify_otp( $request ) {
		$canonical = AUN_App_Phone::normalize( (string) $request->get_param( 'phone' ) );
		if ( ! $canonical ) {
			return $this->err( 'invalid_phone', 'Please enter a valid Bangladeshi mobile number.', 400 );
		}

		$otp = preg_replace( '/\D+/', '', (string) $request->get_param( 'otp' ) );
		if ( '' === $otp ) {
			return $this->err( 'invalid_otp', 'Please enter the code from the SMS.', 400 );
		}

		$check = AUN_App_OTP::verify( $canonical, $otp );
		if ( ! $check['ok'] ) {
			return $this->err( $check['code'], $check['message'], 401 );
		}

		$name  = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$users = AUN_App_Phone::find_users( $canonical );

		$is_new = false;
		if ( ! empty( $users ) ) {
			$user = $users[0];
		} else {
			$user = $this->create_user( $canonical );
			if ( is_wp_error( $user ) ) {
				return $this->err( 'signup_failed', 'Could not create your account. Please contact support.', 500 );
			}
			$is_new = true;
		}

		// App profile lives in user meta, NOT the WordPress account — so a phone
		// that matches a shared/admin account never shows that account's name.
		// A name typed on the login screen wins; otherwise seed it from the ERP
		// sales record so returning customers see their real name automatically.
		if ( '' !== $name ) {
			AUN_App_Profile::set_name( $user->ID, $name );
		} else {
			AUN_App_Profile::seed_from_erp( $user->ID, $canonical );
		}

		// Stamp the FIRST app login, once, for every account — not just ones the
		// app created. The referral claim window is measured from this, and
		// measuring it from the WordPress account instead refused a genuinely
		// new app customer whose website account happened to be two years old.
		// Set here rather than at signup so pre-existing accounts get a date the
		// first time they actually turn up in the app.
		if ( '' === (string) get_user_meta( $user->ID, 'aun_app_signup', true ) ) {
			update_user_meta( $user->ID, 'aun_app_signup', current_time( 'mysql' ) );
		}

		$device_name = sanitize_text_field( (string) $request->get_param( 'device_name' ) );
		$token       = AUN_App_Tokens::issue( $user->ID, $device_name );

		// Immediately pull in any retail purchases already on this phone so a
		// returning customer sees their projector on the very first home screen.
		if ( AUN_App_Warranty::available() ) {
			AUN_App_Warranty::sync_own_purchases( $user->ID, $canonical, true );
		}

		return $this->ok( array(
			'token' => $token,
			'user'  => $this->user_payload( $user, array( 'is_new' => $is_new ) ),
		) );
	}

	/**
	 * Create a customer account keyed to a phone number. The display name is a
	 * placeholder only — the app's shown name comes from the profile meta.
	 *
	 * @param string $canonical 8801XXXXXXXXX
	 * @return WP_User|WP_Error
	 */
	private function create_user( $canonical ) {
		$local = substr( $canonical, 2 ); // 01XXXXXXXXX
		$login = $local;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$i++;
			$login = $local . '-' . $i;
		}

		$user_id = wp_insert_user( array(
			'user_login'   => $login,
			'user_pass'    => wp_generate_password( 24 ),
			'display_name' => $local,
			'role'         => get_role( 'customer' ) ? 'customer' : get_option( 'default_role', 'subscriber' ),
		) );

		if ( is_wp_error( $user_id ) ) {
			error_log( 'AUN APP API: user creation failed — ' . $user_id->get_error_message() );
			return $user_id;
		}

		update_user_meta( $user_id, 'billing_phone', $canonical );
		update_user_meta( $user_id, 'mobile_phone', $canonical );
		update_user_meta( $user_id, 'aun_app_signup', current_time( 'mysql' ) );

		return get_user_by( 'id', $user_id );
	}

	public function get_models() {
		if ( ! AUN_App_Warranty::available() ) {
			return $this->ok( array() );
		}
		return $this->ok( AUN_App_Warranty::models() );
	}

	public function get_dealers() {
		if ( ! AUN_App_Warranty::available() ) {
			return $this->ok( array() );
		}
		return $this->ok( AUN_App_Warranty::dealers() );
	}

	/**
	 * The embed page markup (public so the bench tests can inspect it —
	 * the route callback echoes + exits, which would kill an eval-file run).
	 *
	 * @param string $video_id Validated YouTube id.
	 * @return string
	 */
	public static function embed_html( $video_id ) {
		$src = 'https://www.youtube.com/embed/' . rawurlencode( $video_id )
			. '?autoplay=1&playsinline=1&rel=0&modestbranding=1&fs=1';

		return '<!doctype html><html><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">'
			. '<title>AUN Video</title>'
			. '<style>html,body{margin:0;padding:0;background:#000;height:100%;overflow:hidden}'
			. 'iframe{position:fixed;top:0;left:0;width:100%;height:100%;border:0}</style>'
			. '</head><body>'
			. '<iframe src="' . esc_url( $src ) . '" '
			. 'allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>'
			. '</body></html>';
	}

	public function repair_image( $request ) {
		// media_filename() enforces basename + an image extension, so a
		// traversal attempt or a PDF never reaches the ERP call.
		$file = AUN_App_ERP::media_filename( (string) $request->get_param( 'file' ) );
		if ( '' === $file ) {
			return $this->err( 'invalid_file', 'Invalid image.', 400 );
		}

		$result = AUN_App_ERP::fetch_media( $file );
		if ( empty( $result['ok'] ) ) {
			return $this->err( 'not_found', 'Image not available.', (int) ( $result['status'] ?? 404 ) );
		}

		header( 'Content-Type: ' . $result['type'] );
		header( 'Cache-Control: public, max-age=86400' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo $result['body']; // phpcs:ignore WordPress.Security.EscapeOutput -- binary image.
		exit;
	}

	public function video_embed( $request ) {
		$v = (string) $request->get_param( 'v' );
		if ( ! preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $v ) ) {
			return $this->err( 'invalid_video', 'Invalid video id.', 400 );
		}

		// Plain HTML response (not the JSON envelope) — this is a page the
		// app's WebView loads, same pattern as the repair tracker image proxy.
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo self::embed_html( $v );
		exit;
	}

	public function get_content( $request ) {
		$type     = sanitize_key( (string) $request->get_param( 'type' ) );
		$model_id = (int) $request->get_param( 'model_id' );
		return $this->ok( AUN_App_Content::get_list( $type, $model_id ) );
	}

	public function get_model_content( $request ) {
		return $this->ok( AUN_App_Content::bundle( (int) $request['id'] ) );
	}

	public function watch_picks() {
		return $this->ok( AUN_App_Watch::picks() );
	}

	public function get_content_item( $request ) {
		$item = AUN_App_Content::get_public( (int) $request['id'] );
		if ( ! $item ) {
			return $this->err( 'not_found', 'This content is no longer available.', 404 );
		}
		return $this->ok( $item );
	}

	/**
	 * Firmware/manual download redirect. Resolves the real download URL (an
	 * authenticated OneDrive URL via WP File Download's connector, else a direct
	 * link) and 302s to it — so the app downloads from a stable URL and cloud
	 * quirks are fixed server-side. Not the JSON envelope: it's a file redirect.
	 */
	public function content_file( $request ) {
		$row = AUN_App_Content::download_row( (int) $request['id'] );
		if ( ! $row || 1 !== (int) $row->active || 'video' === $row->type ) {
			status_header( 404 );
			exit;
		}
		if ( isset( $row->app_downloadable ) && 1 !== (int) $row->app_downloadable ) {
			status_header( 403 );
			exit;
		}
		$dl = AUN_App_Content::resolve_download_url( (string) $row->url );
		if ( '' === $dl ) {
			status_header( 404 );
			exit;
		}
		nocache_headers();
		wp_redirect( $dl, 302 );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Authenticated handlers
	 * --------------------------------------------------------------------- */

	public function auth_logout( $request ) {
		AUN_App_Tokens::revoke( $this->bearer( $request ) );
		return $this->ok( array( 'logged_out' => true ) );
	}

	public function get_me() {
		return $this->ok( $this->user_payload( wp_get_current_user() ) );
	}

	/**
	 * Delete the caller's app account.
	 *
	 * Requires `confirm=DELETE` in the body: the app already asks twice, and
	 * this makes an accidental or replayed request impossible to mistake for a
	 * deliberate one. See AUN_App_Account for exactly what goes and what stays.
	 */
	public function delete_me( $request ) {
		$me = $this->identity();

		if ( 'DELETE' !== strtoupper( (string) $request->get_param( 'confirm' ) ) ) {
			return $this->err( 'not_confirmed', 'Deletion was not confirmed.', 400 );
		}

		$result = AUN_App_Account::delete( $me['user_id'], $me['phone'] );
		if ( empty( $result['ok'] ) ) {
			return $this->err( 'delete_failed', 'Could not delete the account. Please contact support.', 500 );
		}

		return $this->ok( array( 'deleted' => true ) );
	}

	public function update_me( $request ) {
		$user = wp_get_current_user();

		// Profile is stored in app meta — never mutate the WordPress account
		// (it may be shared with the store's own login).
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' !== $name ) {
			AUN_App_Profile::set_name( $user->ID, $name );
		}

		$email = (string) $request->get_param( 'email' );
		if ( '' !== $email ) {
			if ( ! is_email( $email ) ) {
				return $this->err( 'invalid_email', 'Please enter a valid email address.', 400 );
			}
			AUN_App_Profile::set_email( $user->ID, $email );
		}

		// Optional emoji avatar (empty string clears it).
		$emoji = (string) $request->get_param( 'avatar_emoji' );
		if ( null !== $request->get_param( 'avatar_emoji' ) ) {
			AUN_App_Profile::set_avatar( $user->ID, '' !== $emoji ? 'emoji:' . mb_substr( $emoji, 0, 4 ) : '' );
		}

		return $this->ok( $this->user_payload( get_user_by( 'id', $user->ID ) ) );
	}

	public function update_avatar( $request ) {
		$user  = wp_get_current_user();
		$files = $request->get_file_params();
		if ( empty( $files['photo'] ) ) {
			return $this->err( 'no_file', 'No photo was uploaded.', 400 );
		}

		$url = AUN_App_Services::store_public_upload( $files['photo'], 'aun-avatars' );
		if ( '' === $url ) {
			return $this->err( 'upload_failed', 'Could not upload the photo. Try a smaller image.', 400 );
		}
		AUN_App_Profile::set_avatar( $user->ID, $url );

		return $this->ok( $this->user_payload( get_user_by( 'id', $user->ID ) ) );
	}

	public function remove_device( $request ) {
		if ( ! AUN_App_Warranty::available() ) {
			return $this->err( 'warranty_unavailable', 'Service is temporarily unavailable.', 503 );
		}
		$me     = $this->identity();
		$source = sanitize_key( (string) $request->get_param( 'source' ) );
		$id     = (int) $request->get_param( 'id' );

		$ok = AUN_App_Warranty::remove_device( $me['user_id'], $me['phone'], $source, $id );
		if ( ! $ok ) {
			return $this->err( 'not_found', 'That device is not on your account.', 404 );
		}
		return $this->ok( array( 'removed' => true ) );
	}

	public function get_my_devices( $request ) {
		if ( ! AUN_App_Warranty::available() ) {
			return $this->err( 'warranty_unavailable', 'Warranty service is temporarily unavailable.', 503 );
		}

		$uid       = get_current_user_id();
		$canonical = AUN_App_Phone::user_phone( $uid );
		if ( ! $canonical ) {
			// Even with no phone on file, direct-purchase links are keyed by user.
			return $this->ok( AUN_App_Warranty::get_devices( '', $uid ) );
		}

		// Auto-link any retail (non-Dealer) ERP purchases on this phone first,
		// so they appear with no scanning. Throttled; ?refresh=1 (pull-to-
		// refresh) forces a fresh ERP check.
		$force = '1' === (string) $request->get_param( 'refresh' );
		AUN_App_Warranty::sync_own_purchases( $uid, $canonical, $force );

		return $this->ok( AUN_App_Warranty::get_devices( $canonical, $uid ) );
	}

	public function warranty_check( $request ) {
		if ( ! AUN_App_Warranty::available() ) {
			return $this->err( 'warranty_unavailable', 'Warranty service is temporarily unavailable.', 503 );
		}

		// Light anti-scraping cap: 30 lookups per user per day.
		$key   = 'aun_app_wchk_' . get_current_user_id();
		$count = (int) get_transient( $key );
		if ( $count >= 30 ) {
			return $this->err( 'rate_limited', 'Too many lookups today. Please try again tomorrow.', 429 );
		}
		set_transient( $key, $count + 1, DAY_IN_SECONDS );

		$canonical = AUN_App_Phone::user_phone( get_current_user_id() );
		$result    = AUN_App_Warranty::check_serial( (string) $request->get_param( 'serial' ), $canonical ? $canonical : '' );

		if ( 'invalid' === $result['state'] ) {
			return $this->err( 'invalid_serial', 'Please enter a valid serial number.', 400 );
		}

		return $this->ok( $result );
	}

	public function register_device( $request ) {
		if ( ! AUN_App_Warranty::available() ) {
			return $this->err( 'warranty_unavailable', 'Warranty service is temporarily unavailable.', 503 );
		}

		$user      = wp_get_current_user();
		$canonical = AUN_App_Phone::user_phone( $user->ID );
		if ( ! $canonical ) {
			return $this->err( 'no_phone', 'Your account has no phone number.', 400 );
		}

		// Invoice photo (dealer registrations require it — enforced in
		// AUN_App_Warranty::register_device alongside the other field checks).
		$invoice_url = '';
		$files       = $request->get_file_params();
		if ( ! empty( $files['invoice_file'] ) && UPLOAD_ERR_NO_FILE !== (int) $files['invoice_file']['error'] ) {
			$upload = $this->handle_invoice_upload( $files['invoice_file'] );
			if ( is_wp_error( $upload ) ) {
				return $this->err( 'upload_failed', $upload->get_error_message(), 400 );
			}
			$invoice_url = $upload;
		}

		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			$name = AUN_App_Profile::get_name( $user->ID );
		}
		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		if ( '' === $email ) {
			$email = AUN_App_Profile::get_email( $user->ID );
		}

		$result = AUN_App_Warranty::register_device( array(
			'user_id'        => $user->ID,
			'customer_name'  => $name,
			'phone'          => $canonical,
			'email'          => $email,
			'serial'         => (string) $request->get_param( 'serial' ),
			'model'          => (string) $request->get_param( 'model' ),
			'distributor_id' => (int) $request->get_param( 'distributor_id' ),
			'dealer_name'    => (string) $request->get_param( 'dealer_name' ),
			'purchase_date'  => (string) $request->get_param( 'purchase_date' ),
			'invoice_no'     => (string) $request->get_param( 'invoice_no' ),
			'invoice_file'   => $invoice_url,
		) );

		if ( ! $result['ok'] ) {
			$status = 'already_registered' === $result['code'] ? 409 : ( 'db_error' === $result['code'] ? 500 : 400 );
			$extra  = isset( $result['owned_by_you'] ) ? array( 'owned_by_you' => $result['owned_by_you'] ) : array();
			return $this->err( $result['code'], $result['message'], $status, $extra );
		}

		return $this->ok( array(
			'status'  => $result['code'],
			'message' => $result['message'],
			'device'  => $result['device'],
		), 201 );
	}

	/**
	 * Store an uploaded invoice in uploads/warranty-invoices/YYYY/MM — the same
	 * place the website registration form keeps them.
	 *
	 * @param array $file One $_FILES-style entry.
	 * @return string|WP_Error Public URL of the stored file.
	 */
	private function handle_invoice_upload( $file ) {
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'upload_error', 'The file upload failed. Please try again.' );
		}
		if ( (int) $file['size'] > 8 * 1024 * 1024 ) {
			return new WP_Error( 'too_large', 'The file is too large (max 8 MB).' );
		}

		$mimes = array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
			'pdf'      => 'application/pdf',
		);

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$subdir_filter = function ( $dirs ) {
			$sub            = '/warranty-invoices/' . date( 'Y/m' );
			$dirs['path']   = $dirs['basedir'] . $sub;
			$dirs['url']    = $dirs['baseurl'] . $sub;
			$dirs['subdir'] = $sub;
			return $dirs;
		};

		add_filter( 'upload_dir', $subdir_filter );
		$result = wp_handle_sideload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $mimes,
			)
		);
		remove_filter( 'upload_dir', $subdir_filter );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'upload_rejected', 'Only JPG, PNG, WEBP or PDF files are allowed.' );
		}

		return $result['url'];
	}
}
