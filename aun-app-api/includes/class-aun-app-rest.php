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

		register_rest_route( $ns, '/parts/decision', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'parts_decision' ),
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

		$wpdb->update( aun_app_api_tokens_table(), array(
			'fcm_token' => $fcm,
			'fcm_lang'  => $lang,
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
				$product_id = AUN_App_Projectors::match_model( (string) ( $device['model'] ?? '' ) );
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
			'announcement'  => (string) $opts['announcement'],
			'discount_note' => (string) $opts['discount_note'],
			'banners'       => $banners,
			'login_video'   => (string) $opts['login_video_url'],
			// Weather / holiday / weekend context for the smart greeting.
			'greeting'      => AUN_App_Greeting::context(),
			'app'           => array(
				'latest_version_code' => (int) $opts['latest_version_code'],
				'latest_version_name' => (string) $opts['latest_version_name'],
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
