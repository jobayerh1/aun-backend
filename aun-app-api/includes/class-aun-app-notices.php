<?php
/**
 * In-app notification centre.
 *
 * Notices are either PERSONAL (user_id > 0: maintenance reminders, repair
 * decisions) or BROADCAST (user_id = 0, optionally targeted at one product
 * model: new firmware/manual/video/tip). Read/dismiss state is per user in
 * aun_app_notice_state.
 *
 * Maintenance (dust-filter) reminders mirror the ERP MaintenanceSmsService:
 * products flagged product_custom_field1 = 'MAINT_SMS' get reminders at
 * +30/+60/+90 days after purchase, same three messages. They are materialised
 * ON DEMAND per user+serial+offset (dedup_key), with created_at set to the
 * ORIGINAL due date — so a customer who installs the app two months after
 * buying immediately sees the past months' reminders in the panel.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Notices {

	const FEED_WINDOW_DAYS = 180;
	const FEED_LIMIT       = 50;

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aun_app_notices';
	}

	public static function state_table() {
		global $wpdb;
		return $wpdb->prefix . 'aun_app_notice_state';
	}

	/* --------------------------------------------------------------------- *
	 * Creation
	 * --------------------------------------------------------------------- */

	/** Whether the last create() call actually inserted (vs deduped). */
	public static $last_was_new = false;

	/**
	 * Create a notice (idempotent when dedup_key is given).
	 *
	 * @param array $args user_id, model_id, type, title, title_bn, body,
	 *                    body_bn, data (array), dedup_key, created_at.
	 * @return int Notice id (existing one when deduped), 0 on failure.
	 */
	public static function create( $args ) {
		global $wpdb;
		$t = self::table();

		self::$last_was_new = false;

		$dedup = trim( (string) ( $args['dedup_key'] ?? '' ) );
		if ( '' !== $dedup ) {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE dedup_key = %s", $dedup ) );
			if ( $existing ) {
				return (int) $existing;
			}
		}

		$ok = $wpdb->insert( $t, array(
			'user_id'    => (int) ( $args['user_id'] ?? 0 ),
			'model_id'   => (int) ( $args['model_id'] ?? 0 ),
			'type'       => sanitize_key( (string) ( $args['type'] ?? 'general' ) ),
			'title'      => substr( sanitize_text_field( (string) ( $args['title'] ?? '' ) ), 0, 191 ),
			'title_bn'   => substr( sanitize_text_field( (string) ( $args['title_bn'] ?? '' ) ), 0, 191 ),
			'body'       => sanitize_textarea_field( (string) ( $args['body'] ?? '' ) ),
			'body_bn'    => sanitize_textarea_field( (string) ( $args['body_bn'] ?? '' ) ),
			'data'       => wp_json_encode( (array) ( $args['data'] ?? array() ) ),
			'dedup_key'  => '' !== $dedup ? $dedup : null,
			'created_at' => (string) ( $args['created_at'] ?? current_time( 'mysql' ) ),
		) );

		self::$last_was_new = (bool) $ok;
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Broadcast notice when new content is published for a model.
	 *
	 * @param int    $content_id Content row id.
	 * @param string $type       firmware|manual|video|tip.
	 * @param int    $model_id   0 = all models.
	 * @param string $title      Content title.
	 * @param string $model_name Resolved model name ('' when all models).
	 */
	public static function content_published( $content_id, $type, $model_id, $title, $model_name ) {
		$labels = array(
			'firmware' => array( 'New firmware update', 'নতুন ফার্মওয়্যার আপডেট' ),
			'manual'   => array( 'New user manual', 'নতুন ইউজার ম্যানুয়াল' ),
			'video'    => array( 'New video guide', 'নতুন ভিডিও গাইড' ),
			'tip'      => array( 'New tip for you', 'আপনার জন্য নতুন টিপস' ),
		);
		$label = $labels[ $type ] ?? array( 'New in the app', 'অ্যাপে নতুন' );

		$suffix    = '' !== $model_name ? ' — ' . $model_name : '';
		$suffix_bn = '' !== $model_name ? ' — ' . $model_name : '';

		$id = self::create( array(
			'user_id'   => 0,
			'model_id'  => (int) $model_id,
			'type'      => $type,
			'title'     => $label[0] . $suffix,
			'title_bn'  => $label[1] . $suffix_bn,
			'body'      => (string) $title,
			'body_bn'   => (string) $title,
			'data'      => array( 'content_id' => (int) $content_id, 'content_type' => $type, 'model_id' => (int) $model_id ),
			'dedup_key' => 'content:' . (int) $content_id,
		) );

		// Push to the owners of this model only (all app users for model 0).
		if ( $id && self::$last_was_new && AUN_App_Push::configured() ) {
			AUN_App_Push::push_to_users(
				AUN_App_Warranty::available()
					? AUN_App_Warranty::user_ids_for_model( (int) $model_id )
					: array(),
				array(
					'title'    => $label[0] . $suffix,
					'title_bn' => $label[1] . $suffix_bn,
					'body'     => (string) $title,
					'body_bn'  => (string) $title,
				),
				// content_id + model_id let a tap on the notification open the
				// actual firmware/manual/video/tip screen in the app.
				array(
					'notice_id'  => $id,
					'type'       => $type,
					'content_id' => (int) $content_id,
					'model_id'   => (int) $model_id,
				)
			);
		}
	}

	/**
	 * Personal notice when a repair request is approved or rejected.
	 *
	 * @param int    $user_id  Customer.
	 * @param string $ref      RP- reference.
	 * @param bool   $approved Approved (vs rejected).
	 * @param string $note     Admin note.
	 */
	public static function repair_decision( $user_id, $ref, $approved, $note = '' ) {
		if ( $user_id < 1 ) {
			return;
		}
		$title    = $approved
			? "Repair $ref approved — please send the projector"
			: "Repair $ref could not be accepted";
		$title_bn = $approved
			? "মেরামত $ref অনুমোদিত — প্রজেক্টরটি পাঠান"
			: "মেরামত $ref গ্রহণ করা যায়নি";

		$id = self::create( array(
			'user_id'   => (int) $user_id,
			'type'      => 'repair',
			'title'     => $title,
			'title_bn'  => $title_bn,
			'body'      => (string) $note,
			'body_bn'   => (string) $note,
			'data'      => array( 'ref' => (string) $ref ),
			'dedup_key' => 'repair:' . $ref . ':' . ( $approved ? 'approved' : 'rejected' ),
		) );

		if ( $id && self::$last_was_new && AUN_App_Push::configured() ) {
			AUN_App_Push::push_to_users(
				array( (int) $user_id ),
				array(
					'title'    => $title,
					'title_bn' => $title_bn,
					'body'     => (string) $note,
					'body_bn'  => (string) $note,
				),
				array( 'notice_id' => $id, 'type' => 'repair', 'ref' => (string) $ref )
			);
		}
	}

	/**
	 * Personal notice + push when the ERP repair status changes (e.g. Received →
	 * Under Repair → Ready → Delivered). The status label comes straight from the
	 * ERP (the business's own wording); we show it in both languages. Deduped per
	 * (ref, status) so the same status never notifies twice.
	 *
	 * @param int    $user_id Customer.
	 * @param string $ref     RP- reference.
	 * @param string $status  ERP status label.
	 */
	public static function repair_status_changed( $user_id, $ref, $status ) {
		$user_id = (int) $user_id;
		$status  = trim( (string) $status );
		if ( $user_id < 1 || '' === $status ) {
			return;
		}
		$title    = "Repair update — $ref";
		$title_bn = "মেরামত আপডেট — $ref";

		$id = self::create( array(
			'user_id'   => $user_id,
			'type'      => 'repair',
			'title'     => $title,
			'title_bn'  => $title_bn,
			'body'      => 'Status: ' . $status,
			'body_bn'   => 'স্ট্যাটাস: ' . $status,
			'data'      => array( 'ref' => (string) $ref ),
			// One notice per distinct status for this repair.
			'dedup_key' => 'repair_status:' . $ref . ':' . md5( strtolower( $status ) ),
		) );

		if ( $id && self::$last_was_new && AUN_App_Push::configured() ) {
			AUN_App_Push::push_to_users(
				array( $user_id ),
				array(
					'title'    => $title,
					'title_bn' => $title_bn,
					'body'     => 'Status: ' . $status,
					'body_bn'  => 'স্ট্যাটাস: ' . $status,
				),
				array( 'notice_id' => $id, 'type' => 'repair', 'ref' => (string) $ref )
			);
		}
	}

	/* --------------------------------------------------------------------- *
	 * Maintenance (dust-filter) reminders — mirrors MaintenanceSmsService
	 * --------------------------------------------------------------------- */

	/**
	 * Same three messages the ERP sends by SMS (EN), with Bangla companions.
	 *
	 * @return array<int,array{title:string,title_bn:string,body:string,body_bn:string}>
	 */
	public static function maintenance_messages() {
		return array(
			30 => array(
				'title'    => 'AUN Care Tip',
				'title_bn' => 'AUN যত্ন টিপস',
				'body'     => "Please clean your projector's dust filter regularly to ensure proper airflow and smooth performance. This helps extend product life.",
				'body_bn'  => 'সঠিক বাতাস চলাচল ও ভালো পারফরম্যান্সের জন্য প্রজেক্টরের ডাস্ট ফিল্টারটি নিয়মিত পরিষ্কার করুন। এতে প্রজেক্টরের আয়ু বাড়ে।',
			),
			60 => array(
				'title'    => 'AUN Reminder',
				'title_bn' => 'AUN রিমাইন্ডার',
				'body'     => "Dust buildup can block airflow and cause overheating. Clean the dust filter regularly to protect your projector's internal components.",
				'body_bn'  => 'ধুলা জমে বাতাস চলাচল বন্ধ হয়ে প্রজেক্টর অতিরিক্ত গরম হতে পারে। ভেতরের যন্ত্রাংশ রক্ষায় ডাস্ট ফিল্টার নিয়মিত পরিষ্কার করুন।',
			),
			90 => array(
				'title'    => 'AUN Important Notice',
				'title_bn' => 'AUN জরুরি নোটিশ',
				'body'     => 'Damage from overheating due to blocked ventilation or dust buildup is not covered under warranty. Regular filter cleaning is essential.',
				'body_bn'  => 'ধুলা জমে বা বাতাস চলাচল বন্ধ হয়ে অতিরিক্ত গরমে হওয়া ক্ষতি ওয়ারেন্টির আওতায় পড়ে না। ফিল্টার নিয়মিত পরিষ্কার করা অত্যাবশ্যক।',
			),
		);
	}

	/**
	 * Materialise due maintenance reminders for one user's devices.
	 * created_at = the original due date (purchase + offset, 10:00) so late
	 * app installers still see the earlier months' reminders. Throttled to
	 * once per 6 h per user; force with $force (tests, first login).
	 *
	 * @param int    $user_id   User id.
	 * @param string $canonical Phone 8801XXXXXXXXX.
	 * @param bool   $force     Skip the 6-h throttle.
	 * @param bool   $push      Also push each NEWLY created reminder. The
	 *                          in-app feed path leaves this false (the customer
	 *                          is already inside the app); the daily cron sets
	 *                          it so due reminders arrive as real push.
	 */
	public static function materialize_maintenance( $user_id, $canonical, $force = false, $push = false ) {
		if ( $user_id < 1
			|| ! class_exists( 'AUN_App_Warranty' )
			|| ! AUN_App_Warranty::available()
			|| ! AUN_App_ERP::configured() ) {
			return;
		}

		$throttle = 'aun_app_maint_chk_' . (int) $user_id;
		if ( ! $force && get_transient( $throttle ) ) {
			return;
		}
		set_transient( $throttle, 1, 6 * HOUR_IN_SECONDS );

		$now_ts   = current_time( 'timestamp' );
		$messages = self::maintenance_messages();

		foreach ( (array) AUN_App_Warranty::get_devices( $canonical, $user_id ) as $device ) {
			$serial = (string) ( $device['serial'] ?? '' );
			if ( '' === $serial ) {
				continue;
			}

			// Same eligibility rule as the ERP SMS service.
			$sale = AUN_App_ERP::lookup_serial( $serial );
			if ( ! is_array( $sale ) || empty( $sale['maintenance'] ) ) {
				continue;
			}

			$start = (string) ( $device['purchase_date'] ?? '' );
			if ( '' === $start && ! empty( $device['warranty']['start'] ) ) {
				$start = (string) $device['warranty']['start'];
			}
			if ( '' === $start ) {
				continue;
			}

			foreach ( $messages as $offset => $msg ) {
				$due_ts = strtotime( $start . " +{$offset} days" );
				if ( false === $due_ts || $due_ts > $now_ts ) {
					continue; // not due yet
				}
				$id = self::create( array(
					'user_id'    => (int) $user_id,
					'type'       => 'maintenance',
					'title'      => $msg['title'],
					'title_bn'   => $msg['title_bn'],
					'body'       => $msg['body'],
					'body_bn'    => $msg['body_bn'],
					'data'       => array(
						'serial' => $serial,
						'model'  => (string) ( $device['model'] ?? '' ),
						'offset' => (int) $offset,
					),
					'dedup_key'  => 'maint:' . (int) $user_id . ':' . md5( $serial ) . ':' . (int) $offset,
					'created_at' => date( 'Y-m-d 10:00:00', $due_ts ),
				) );

				if ( $push && $id && self::$last_was_new && AUN_App_Push::configured() ) {
					AUN_App_Push::push_to_users(
						array( (int) $user_id ),
						$msg, // carries title/title_bn/body/body_bn already
						array( 'notice_id' => $id, 'type' => 'maintenance' )
					);
				}
			}
		}
	}

	/**
	 * Daily cron: materialise due maintenance reminders for every user who has
	 * a live app session, WITH push — so "clean your dust filter" month 2/3
	 * arrives even if the app never gets opened. Dedup keys make this safe to
	 * run any number of times.
	 */
	public static function daily_maintenance_run() {
		global $wpdb;

		$t        = aun_app_api_tokens_table();
		$user_ids = (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT user_id FROM $t WHERE expires_at > %s",
			current_time( 'mysql' )
		) );

		foreach ( $user_ids as $uid ) {
			$canonical = AUN_App_Phone::user_phone( (int) $uid );
			if ( ! $canonical ) {
				continue;
			}
			// force=true: the cron must not be blocked by an app-open earlier
			// today — dedup keys already prevent double reminders.
			self::materialize_maintenance( (int) $uid, $canonical, true, true );
		}
	}

	/**
	 * Drop every maintenance reminder for one user+serial — used when a device
	 * leaves the account (returned to us, sale deleted, or removed by the
	 * customer), so nobody is told to clean a filter they no longer own.
	 * Their read/dismiss state goes with them.
	 *
	 * @param int    $user_id User id.
	 * @param string $serial  Device serial.
	 */
	public static function delete_for_serial( $user_id, $serial ) {
		global $wpdb;
		$user_id = (int) $user_id;
		$serial  = (string) $serial;
		if ( $user_id < 1 || '' === $serial ) {
			return;
		}

		$t      = self::table();
		$prefix = 'maint:' . $user_id . ':' . md5( $serial ) . ':';
		$ids    = (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM $t WHERE user_id = %d AND dedup_key LIKE %s",
			$user_id,
			$wpdb->esc_like( $prefix ) . '%'
		) );

		foreach ( $ids as $id ) {
			$wpdb->delete( self::state_table(), array( 'notice_id' => (int) $id ), array( '%d' ) );
			$wpdb->delete( $t, array( 'id' => (int) $id ), array( '%d' ) );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Feed / state
	 * --------------------------------------------------------------------- */

	/**
	 * The user's notification feed (newest first) + unread count.
	 *
	 * @param int    $user_id   User id.
	 * @param string $canonical Phone.
	 * @return array{items:array[],unread:int}
	 */
	public static function feed( $user_id, $canonical ) {
		global $wpdb;

		self::materialize_maintenance( $user_id, $canonical );

		$model_ids = ( class_exists( 'AUN_App_Warranty' ) && AUN_App_Warranty::available() && '' !== $canonical )
			? AUN_App_Warranty::user_model_ids( $canonical, $user_id )
			: array();
		$model_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $model_ids ) ) ) );

		$t  = self::table();
		$ts = self::state_table();

		$model_sql = 'n.model_id = 0';
		$params    = array( (int) $user_id, (int) $user_id );
		if ( $model_ids ) {
			$ph        = implode( ',', array_fill( 0, count( $model_ids ), '%d' ) );
			$model_sql = "(n.model_id = 0 OR n.model_id IN ($ph))";
		}

		$since = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - self::FEED_WINDOW_DAYS * DAY_IN_SECONDS );

		$sql = "SELECT n.*, s.read_at, s.dismissed
			 FROM $t n
			 LEFT JOIN $ts s ON s.notice_id = n.id AND s.user_id = %d
			 WHERE (n.user_id = %d OR (n.user_id = 0 AND $model_sql))
			   AND n.created_at >= %s
			 ORDER BY n.created_at DESC, n.id DESC
			 LIMIT " . self::FEED_LIMIT;

		$params   = array_merge( $params, $model_ids, array( $since ) );
		$rows     = (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		$items    = array();
		$unread   = 0;

		foreach ( $rows as $r ) {
			if ( ! empty( $r->dismissed ) ) {
				continue;
			}
			$read = ! empty( $r->read_at );
			if ( ! $read ) {
				$unread++;
			}
			$items[] = array(
				'id'         => (int) $r->id,
				'type'       => (string) $r->type,
				'title'      => (string) $r->title,
				'title_bn'   => (string) $r->title_bn,
				'body'       => (string) $r->body,
				'body_bn'    => (string) $r->body_bn,
				'data'       => json_decode( (string) $r->data, true ) ?: array(),
				'created_at' => (string) $r->created_at,
				'read'       => $read,
			);
		}

		return array(
			'items'  => $items,
			'unread' => $unread,
		);
	}

	/** Upsert one user's state row for a notice. */
	private static function upsert_state( $user_id, $notice_id, $fields ) {
		global $wpdb;
		$ts = self::state_table();

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $ts WHERE user_id = %d AND notice_id = %d",
			(int) $user_id, (int) $notice_id
		) );
		if ( $existing ) {
			$wpdb->update( $ts, $fields, array( 'id' => (int) $existing ) );
			return;
		}
		$wpdb->insert( $ts, array_merge( array(
			'user_id'   => (int) $user_id,
			'notice_id' => (int) $notice_id,
		), $fields ) );
	}

	/**
	 * Mark notices read. $ids = null → everything currently in the feed
	 * (opening the panel resets the counter).
	 *
	 * @param int        $user_id   User.
	 * @param string     $canonical Phone.
	 * @param int[]|null $ids       Specific notice ids, or null for all.
	 */
	public static function mark_read( $user_id, $canonical, $ids = null ) {
		$now = current_time( 'mysql' );

		if ( null === $ids ) {
			$feed = self::feed( $user_id, $canonical );
			$ids  = array();
			foreach ( $feed['items'] as $item ) {
				if ( empty( $item['read'] ) ) {
					$ids[] = (int) $item['id'];
				}
			}
		}

		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			if ( $id > 0 ) {
				self::upsert_state( $user_id, $id, array( 'read_at' => $now ) );
			}
		}
	}

	/**
	 * Dismiss (swipe away) one notice for this user.
	 *
	 * @param int $user_id   User.
	 * @param int $notice_id Notice.
	 */
	public static function dismiss( $user_id, $notice_id ) {
		self::upsert_state( $user_id, (int) $notice_id, array(
			'dismissed' => 1,
			'read_at'   => current_time( 'mysql' ),
		) );
	}
}
