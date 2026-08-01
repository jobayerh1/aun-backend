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

	/**
	 * Personal notice + push the moment a repair request is matched to its
	 * UltimatePOS job sheet — i.e. the projector physically arrived and the
	 * service centre booked it in.
	 *
	 * This is separate from repair_status_changed() on purpose. The status poll
	 * deliberately treats its first observation of a job sheet as a silent
	 * baseline (so it doesn't announce a status the customer has already been
	 * looking at), which meant the arrival itself — the one moment a customer
	 * who has posted their projector actually wants confirmed — was never
	 * announced at all. Deduped per ref, so it can only ever fire once.
	 *
	 * @param int    $user_id      Customer.
	 * @param string $ref          RP- reference.
	 * @param string $job_sheet_no ERP job sheet number.
	 * @param string $status       ERP status label at booking-in time.
	 */
	public static function repair_received( $user_id, $ref, $job_sheet_no, $status ) {
		$user_id = (int) $user_id;
		$ref     = (string) $ref;
		if ( $user_id < 1 || '' === $ref ) {
			return;
		}

		$title    = "We have received your projector — $ref";
		$title_bn = "আপনার প্রজেক্টর আমরা পেয়েছি — $ref";
		$body     = 'Job sheet ' . $job_sheet_no . ' is open. Status: ' . $status . '. We will keep you posted here.';
		$body_bn  = 'জব শিট ' . $job_sheet_no . ' খোলা হয়েছে। স্ট্যাটাস: ' . $status . '। আমরা এখানেই আপডেট জানাব।';

		$id = self::create( array(
			'user_id'   => $user_id,
			'type'      => 'repair',
			'title'     => $title,
			'title_bn'  => $title_bn,
			'body'      => $body,
			'body_bn'   => $body_bn,
			'data'      => array( 'ref' => $ref, 'job_sheet_no' => (string) $job_sheet_no ),
			'dedup_key' => 'repair_received:' . $ref,
		) );

		if ( $id && self::$last_was_new && class_exists( 'AUN_App_Push' ) && AUN_App_Push::configured() ) {
			AUN_App_Push::push_to_users(
				array( $user_id ),
				array(
					'title'    => $title,
					'title_bn' => $title_bn,
					'body'     => $body,
					'body_bn'  => $body_bn,
				),
				array( 'notice_id' => $id, 'type' => 'repair', 'ref' => $ref )
			);
		}
	}

	/**
	 * Personal notice + push when a spare-parts request changes status.
	 *
	 * The spare-parts plugin only ever talked to the customer by SMS, so the app
	 * showed a new quote silently — no notification at all. This mirrors the
	 * repair flow: one notice per (ref, status), so the same status never
	 * notifies twice no matter how often we sync.
	 *
	 * 'quote_sent' is the one that MATTERS: it is the only status the customer
	 * has to answer, and its body tells them they can answer right in the app.
	 *
	 * @param int    $user_id     Customer.
	 * @param string $ref         SP- reference.
	 * @param string $status_key  Machine status (quote_sent, approved, …).
	 * @param string $label       Human status label from the plugin.
	 * @param float  $quote_total Quote total, for the quote_sent wording.
	 */
	public static function parts_status_changed( $user_id, $ref, $status_key, $label, $quote_total = 0 ) {
		$user_id = (int) $user_id;
		$ref     = (string) $ref;
		if ( $user_id < 1 || '' === $ref || '' === $status_key ) {
			return;
		}

		// Statuses worth interrupting someone for. Internal churn (submitted,
		// in_progress) is visible in the app but never pushed — a notification
		// per bookkeeping step is how people learn to ignore your app.
		$total = number_format_i18n( (float) $quote_total, 0 );
		$copy  = array(
			'quote_sent'       => array(
				"Quote ready for $ref — ৳$total",
				"$ref-এর কোটেশন প্রস্তুত — ৳$total",
				'Tap to approve or decline right here in the app.',
				'অ্যাপ থেকেই অনুমোদন বা বাতিল করতে ট্যাপ করুন।',
			),
			'approved'          => array(
				"Approved — sourcing parts for $ref",
				"অনুমোদিত — $ref-এর পার্টস সংগ্রহ চলছে",
				'We have started sourcing your parts.',
				'আমরা আপনার পার্টস সংগ্রহ শুরু করেছি।',
			),
			'waiting_customer' => array(
				"We need one more photo for $ref",
				"$ref-এর জন্য আরেকটি ছবি প্রয়োজন",
				'Open the request to upload a clearer photo.',
				'পরিষ্কার ছবি আপলোড করতে অনুরোধটি খুলুন।',
			),
			'ready'            => array(
				"Your parts for $ref are ready",
				"$ref-এর পার্টস প্রস্তুত",
				'They are ready to dispatch.',
				'পাঠানোর জন্য প্রস্তুত।',
			),
			'closed'           => array(
				"$ref completed",
				"$ref সম্পন্ন",
				'Thank you for choosing AUN Care.',
				'AUN Care বেছে নেওয়ার জন্য ধন্যবাদ।',
			),
			'rejected'         => array(
				"$ref could not be accepted",
				"$ref গ্রহণ করা যায়নি",
				(string) $label,
				(string) $label,
			),
		);

		if ( ! isset( $copy[ $status_key ] ) ) {
			return;
		}
		list( $title, $title_bn, $body, $body_bn ) = $copy[ $status_key ];

		$id = self::create( array(
			'user_id'   => $user_id,
			'type'      => 'parts',
			'title'     => $title,
			'title_bn'  => $title_bn,
			'body'      => $body,
			'body_bn'   => $body_bn,
			'data'      => array( 'ref' => $ref, 'status' => (string) $status_key ),
			// One notice per distinct status for this request.
			'dedup_key' => 'parts_status:' . $ref . ':' . $status_key,
		) );

		if ( $id && self::$last_was_new && class_exists( 'AUN_App_Push' ) && AUN_App_Push::configured() ) {
			AUN_App_Push::push_to_users(
				array( $user_id ),
				array(
					'title'    => $title,
					'title_bn' => $title_bn,
					'body'     => $body,
					'body_bn'  => $body_bn,
				),
				array( 'notice_id' => $id, 'type' => 'parts', 'ref' => $ref )
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
						// `serial` so a tap on the SYSTEM notification opens the
						// projector the reminder is about. Without it the tray tap
						// could only reach the notification centre while an in-app
						// tap went straight to the device — same notice, two
						// different destinations depending on where you tapped.
						array( 'notice_id' => $id, 'type' => 'maintenance', 'serial' => $serial )
					);
				}
			}
		}
	}

	/**
	 * Admin-only: send ONE real maintenance reminder to a phone number right
	 * now, bypassing the purchase-date/eligibility check entirely.
	 *
	 * The real pipeline can't be tested on demand — the first reminder is 30
	 * days after a real ERP sale — so this exists purely so the app can be
	 * verified end to end (feed → Home card → push → mark done / snooze)
	 * without waiting a month. Every call inserts a FRESH row (dedup_key
	 * includes the current timestamp) so it can be sent repeatedly, and it
	 * never touches or resembles the real `maint:` dedup keys the daily cron
	 * uses, so a test send can never suppress or collide with a real one.
	 *
	 * @param string $phone  Any phone number the app recognises (need not own
	 *                       a device — this is a pure UI/pipeline test).
	 * @param int    $offset 30, 60 or 90 — which of the three messages.
	 * @return array{ok:bool,message:string}
	 */
	public static function send_test_maintenance( $phone, $offset ) {
		if ( ! class_exists( 'AUN_App_Phone' ) ) {
			return array( 'ok' => false, 'message' => 'Phone helper unavailable.' );
		}
		$canonical = AUN_App_Phone::normalize( (string) $phone );
		if ( '' === $canonical ) {
			return array( 'ok' => false, 'message' => 'Enter a valid mobile number.' );
		}
		// Same resolution path the app's own login/warranty matching uses —
		// so "does this number have an account" means the same thing here as
		// it does everywhere else in the plugin.
		$users = AUN_App_Phone::find_users( $canonical );
		if ( empty( $users ) ) {
			return array( 'ok' => false, 'message' => 'No app account is registered to that number yet — the customer must log into the app at least once.' );
		}
		$user_id = (int) $users[0]->ID;

		$messages = self::maintenance_messages();
		if ( ! isset( $messages[ (int) $offset ] ) ) {
			$offset = 30;
		}
		$msg = $messages[ (int) $offset ];

		$id = self::create( array(
			'user_id'   => (int) $user_id,
			'type'      => 'maintenance',
			'title'     => $msg['title'],
			'title_bn'  => $msg['title_bn'],
			'body'      => $msg['body'],
			'body_bn'   => $msg['body_bn'],
			'data'      => array( 'serial' => 'TEST', 'model' => '', 'offset' => (int) $offset ),
			// ⚠️ `time()` alone has 1-second resolution — two test sends fired
			// within the same second got the SAME dedup key, and the second one
			// was silently swallowed as a "duplicate" (caught by the bench
			// test). uniqid() with the extra-entropy flag makes every call
			// unique regardless of timing.
			'dedup_key' => 'admintest:' . (int) $user_id . ':' . uniqid( '', true ),
		) );

		if ( ! $id ) {
			return array( 'ok' => false, 'message' => 'Could not create the notice — check the database.' );
		}

		$pushed = 0;
		if ( class_exists( 'AUN_App_Push' ) && AUN_App_Push::configured() ) {
			// Count of tokens actually pushed to (0 = configured but no live
			// session on that account, e.g. logged out everywhere).
			$pushed = (int) AUN_App_Push::push_to_users(
				array( (int) $user_id ),
				$msg,
				array( 'notice_id' => $id, 'type' => 'maintenance' )
			);
		}

		return array(
			'ok'      => true,
			'message' => 'Sent to user #' . (int) $user_id . '. It will appear in the notification panel and as a task card on Home the next time the app talks to the server.'
				. ( $pushed > 0 ? ' A push notification was also sent.' : ' (No push delivered — the account may be logged out everywhere, or push is unconfigured. The in-app card will still appear on next refresh.)' ),
		);
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

		$sql = "SELECT n.*, s.read_at, s.dismissed, s.completed_at, s.snoozed_until
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
		$now      = current_time( 'timestamp' );

		foreach ( $rows as $r ) {
			if ( ! empty( $r->dismissed ) ) {
				continue;
			}
			$read = ! empty( $r->read_at );
			if ( ! $read ) {
				$unread++;
			}
			// Snoozed-but-past-due counts as active again — "remind me later"
			// is a delay, not a second dismiss.
			$snoozed_until = (string) ( $r->snoozed_until ?? '' );
			$snoozing      = '' !== $snoozed_until && strtotime( $snoozed_until ) > $now;

			$items[] = array(
				'id'            => (int) $r->id,
				'type'          => (string) $r->type,
				'title'         => (string) $r->title,
				'title_bn'      => (string) $r->title_bn,
				'body'          => (string) $r->body,
				'body_bn'       => (string) $r->body_bn,
				'data'          => json_decode( (string) $r->data, true ) ?: array(),
				'created_at'    => (string) $r->created_at,
				'read'          => $read,
				// Task-style state — meaningful for type='maintenance' today,
				// harmless (always false/null) for every other notice type.
				'completed'     => ! empty( $r->completed_at ),
				'snoozed_until' => $snoozing ? $snoozed_until : null,
			);
		}

		return array(
			'items'  => $items,
			'unread' => $unread,
		);
	}

	/**
	 * Mark a maintenance reminder done — "yes, I cleaned it". Idempotent.
	 *
	 * @param int $user_id
	 * @param int $notice_id
	 */
	public static function complete( $user_id, $notice_id ) {
		self::upsert_state( $user_id, $notice_id, array(
			'completed_at'  => current_time( 'mysql' ),
			'snoozed_until' => null, // "done" beats any pending snooze
		) );
	}

	/**
	 * Push a reminder's due-again date forward — "remind me later". Clears
	 * any earlier completion, so re-snoozing after marking done (rare, but a
	 * mis-tap should be recoverable) is not a dead end.
	 *
	 * @param int $user_id
	 * @param int $notice_id
	 * @param int $days      1–14, enforced server-side regardless of what the
	 *                       app sends.
	 */
	public static function snooze( $user_id, $notice_id, $days ) {
		$days = max( 1, min( 14, (int) $days ) );
		self::upsert_state( $user_id, $notice_id, array(
			// Site-local time, matching every other timestamp in this class
			// (created_at, read_at, completed_at) — mixing UTC in here would
			// make snoozed_until compare wrong against current_time('timestamp')
			// in feed() by the site's UTC offset.
			'snoozed_until' => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $days * DAY_IN_SECONDS ),
			'completed_at'  => null,
		) );
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
