<?php
/**
 * After-sales services for the app:
 *
 *  - Spare-parts requests written natively into the AUN Spare Parts plugin's
 *    own tables (aun_sp_requests / request_items / attachments / events) with
 *    channel "app", so the existing admin workflow, SP- refs, quotes and SMS
 *    tracking all keep working unchanged.
 *
 *  - Send-in repair requests (customer ships the projector to us) in this
 *    plugin's own aun_app_repairs table, managed from AUN App → Repairs.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Services {

	const REPAIR_STATUSES = array(
		'submitted' => 'Submitted — waiting for approval',
		'approved'  => 'Approved — please send the projector',
		'received'  => 'Projector received',
		'repairing' => 'Repair in progress',
		'ready'     => 'Repair done — returning soon',
		'returned'  => 'Shipped back to customer',
		'closed'    => 'Closed',
		'rejected'  => 'Rejected',
	);

	/**
	 * One admin-editable repair SMS, with its placeholders filled.
	 *
	 * Kept beside the repair code rather than in a messages class of its own:
	 * there are three of them and they all belong to this one flow. If a fourth
	 * arrives, move them out.
	 *
	 * @param string $which received|approved|rejected
	 * @param array  $vars  ref, model, status, note
	 */
	public static function repair_sms( $which, $vars = array() ) {
		$opts = aun_app_api_get_options();
		$key  = 'repair_sms_' . $which;

		$tpl = trim( (string) ( $opts[ $key ] ?? '' ) );
		if ( '' === $tpl ) {
			// An admin who empties the box means "send nothing" for that event,
			// which is a legitimate choice — the app still shows the change in
			// its notification centre either way.
			return '';
		}

		$out = strtr( $tpl, array(
			'{ref}'    => (string) ( $vars['ref'] ?? '' ),
			'{model}'  => (string) ( $vars['model'] ?? '' ),
			'{status}' => (string) ( $vars['status'] ?? '' ),
			'{note}'   => (string) ( $vars['note'] ?? '' ),
		) );

		// An unused {note} leaves a double space and a dangling gap before the
		// full stop; collapse it so the message reads properly either way.
		return trim( preg_replace( '/\s+/u', ' ', $out ) );
	}

	public static function repairs_table() {
		global $wpdb;
		return $wpdb->prefix . 'aun_app_repairs';
	}

	/** Whether the AUN Spare Parts plugin is active on this site. */
	public static function parts_available() {
		return class_exists( 'AUN_SP_Install' ) && class_exists( 'AUN_SP_Parts' );
	}

	/* --------------------------------------------------------------------- *
	 * Spare parts
	 * --------------------------------------------------------------------- */

	/**
	 * Bilingual parts catalog for the app's picker.
	 *
	 * @return array[]
	 */
	public static function parts_catalog() {
		if ( ! self::parts_available() ) {
			return array();
		}
		$out = array();
		foreach ( (array) AUN_SP_Parts::active() as $p ) {
			$p     = (array) $p;
			$out[] = array(
				'key'       => (string) ( $p['key'] ?? '' ),
				'label_en'  => (string) ( $p['label_en'] ?? '' ),
				'label_bn'  => (string) ( $p['label_bn'] ?? '' ),
				'proof_en'  => (string) ( $p['proof_en'] ?? '' ),
				'proof_bn'  => (string) ( $p['proof_bn'] ?? '' ),
				'photo'     => (string) ( $p['photo'] ?? 'none' ),   // required | optional | none
				'ref_image' => (string) ( $p['ref_image'] ?? '' ),   // sample photo from the catalogue
			);
		}
		return $out;
	}

	/**
	 * Largest quantity a customer may order per part.
	 *
	 * Read from the spare-parts plugin itself (AUN_SP_Form::max_qty(), which
	 * honours the `aun_sp_max_qty` filter) so the app and the website request
	 * form can never disagree. Falls back to the plugin's own default of 5 if
	 * that class isn't loaded.
	 *
	 * @return int
	 */
	public static function parts_max_qty() {
		if ( class_exists( 'AUN_SP_Form' ) && method_exists( 'AUN_SP_Form', 'max_qty' ) ) {
			return max( 1, (int) AUN_SP_Form::max_qty() );
		}
		return max( 1, (int) apply_filters( 'aun_sp_max_qty', 5 ) );
	}

	/**
	 * Find — and optionally bin — the empty order shells left by failed
	 * payment attempts.
	 *
	 * These appear as ৳0 "Pending payment" orders with no customer name: a
	 * fatal between `wc_create_order()` (which makes the shell) and the code
	 * that fills it in leaves the shell behind. The cause is fixed, but the
	 * debris from before the fix is still sitting in the orders list.
	 *
	 * The test is deliberately narrow — pending, unpaid, ৳0, no items, no
	 * billing name, no billing email, no spare-parts ref. A genuine order
	 * cannot satisfy all seven. And they are TRASHED, not destroyed, so a
	 * mistake is recoverable from the orders screen.
	 *
	 * @param bool $dry_run True = report only.
	 * @return array{count:int,ids:int[],trashed:int}
	 */
	public static function purge_empty_orders( $dry_run = true ) {
		$out = array( 'count' => 0, 'ids' => array(), 'trashed' => 0 );
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $out;
		}

		$orders = wc_get_orders( array(
			'status'  => array( 'wc-pending' ),
			'limit'   => 200,
			'orderby' => 'date',
			'order'   => 'DESC',
		) );

		foreach ( (array) $orders as $o ) {
			if ( ! is_a( $o, 'WC_Order' )
				|| $o->is_paid()
				|| (float) $o->get_total() > 0
				|| count( $o->get_items() ) > 0
				|| '' !== trim( (string) $o->get_billing_first_name() )
				|| '' !== trim( (string) $o->get_billing_email() )
				|| '' !== (string) $o->get_meta( '_aun_sp_ref' ) ) {
				continue;
			}
			$out['count']++;
			$out['ids'][] = $o->get_id();
			if ( ! $dry_run ) {
				$o->update_status( 'trash', 'Empty payment shell from a failed attempt (AUN app cleanup).' );
				$out['trashed']++;
			}
		}
		return $out;
	}

	/**
	 * Give WooCommerce the front-end context it expects before we create an
	 * order from a REST request.
	 *
	 * WooCommerce deliberately does NOT start a session for REST calls —
	 * `WooCommerce::is_request('frontend')` returns false when
	 * `is_rest_api_request()` is true, and `init_session()` only runs for
	 * front-end requests. So inside our endpoint `WC()->session` and
	 * `WC()->customer` are null.
	 *
	 * That is fine for WooCommerce itself, and fine for the spare-parts plugin.
	 * It is NOT fine for the other plugins hooked onto order creation: one line
	 * of `WC()->session->get( … )` in any of them is a fatal error, and the
	 * customer sees "There has been a critical error on this website" instead
	 * of a payment page. That is exactly what happened on the live site with
	 * `connect-yeamazing` (YEAMCO_WcHooks.php:119 — "Call to a member function
	 * get() on null").
	 *
	 * It also explains why the WEBSITE never hit this: its "Pay online" button
	 * goes through admin-ajax, which IS a front-end request, so the session is
	 * already there. Only the app took the REST path.
	 *
	 * Creating the session here costs one lightweight object and makes our
	 * request look like the one every other plugin was written against.
	 *
	 * @return void
	 */
	public static function ensure_wc_context() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		$wc = WC();

		if ( empty( $wc->session ) ) {
			// Honour any session handler another plugin has swapped in, rather
			// than hardcoding WC_Session_Handler.
			$handler = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
			if ( class_exists( $handler ) ) {
				$wc->session = new $handler();
				$wc->session->init();
			}
		}

		if ( empty( $wc->customer ) && class_exists( 'WC_Customer' ) ) {
			try {
				// The logged-in app customer, so anything reading billing
				// details off WC()->customer sees the right person.
				$wc->customer = new WC_Customer( get_current_user_id(), true );
			} catch ( Exception $e ) {
				// A missing customer is survivable; a fatal is not.
				$wc->customer = null;
			}
		}

		// Some hooks reach for the cart even on an order that never had one.
		if ( empty( $wc->cart ) && method_exists( $wc, 'initialize_cart' ) ) {
			$wc->initialize_cart();
		}
	}

	/**
	 * The payment position on one spare-parts request, or null.
	 *
	 * Delegates entirely to `AUN_SP_Woo::customer_summary()` — the same method
	 * the website tracker renders from — so the app can never show a different
	 * amount, a different paid state or a stale pay link. Guarded by
	 * method_exists because the two plugins ship separately: online payment
	 * arrived in spare-parts 0.29.0, and against an older copy this must return
	 * null rather than fatal.
	 *
	 * @param int $request_id Spare-parts request id.
	 * @return array|null
	 */
	/**
	 * The courier's public tracking page for a stored consignment value.
	 *
	 * ⚠️ DELEGATES to the spare-parts plugin (0.38.0+). Do not reimplement this.
	 *
	 * The plugin's `courier_url()` does considerably more than build a URL: it
	 * pulls the ID out of a pasted tracking link, tolerates spaces and dashes in
	 * a hand-typed one, and — the part that matters — returns '' for anything
	 * that is not plausibly a consignment number. A note like "i will add later"
	 * collapses to `iwilladdlater`, a respectable 13 characters, which an
	 * app-side length check would happily turn into a link to a Pathao page that
	 * finds nothing.
	 *
	 * The admin badge, the website tracker and this all call the same helper, so
	 * staff can never see a working chip where the customer sees a dead one.
	 *
	 * The fallback below is only for a plugin older than 0.38.0, and deliberately
	 * matches what the app shipped in 1.98.0 rather than the older plugin's
	 * behaviour — the old one wrapped pasted URLs and produced dead links.
	 */
	public static function courier_tracking_url( $tracking_no ) {
		$no = trim( (string) $tracking_no );
		if ( '' === $no ) {
			return '';
		}
		if ( method_exists( 'AUN_SP_Requests', 'courier_url' ) ) {
			return (string) AUN_SP_Requests::courier_url( $no );
		}
		if ( preg_match( '~^https?://~i', $no ) ) {
			return esc_url_raw( $no );
		}
		return 'https://merchant.pathao.com/public-tracking?consignment_id=' . rawurlencode( $no );
	}

	public static function payment_summary( $request_id ) {
		if ( ! class_exists( 'AUN_SP_Woo' )
			|| ! method_exists( 'AUN_SP_Woo', 'customer_summary' ) ) {
			return null;
		}
		$s = AUN_SP_Woo::customer_summary( (int) $request_id );
		if ( ! is_array( $s ) ) {
			return null;
		}
		return array(
			'number'        => (string) ( $s['number'] ?? '' ),
			// Strings from WooCommerce's own formatter — kept as strings so the
			// app shows exactly what the website shows, thousands separator and
			// all, instead of re-formatting and drifting.
			'total'         => (string) ( $s['total'] ?? '' ),
			'paid'          => ! empty( $s['paid'] ),
			'method'        => (string) ( $s['method'] ?? '' ),
			'pay_url'       => (string) ( $s['pay_url'] ?? '' ),
			'refunded'      => ! empty( $s['refunded'] ),
			'refund_amount' => (string) ( $s['refund_amount'] ?? '' ),
			'refund_date'   => (string) ( $s['refund_date'] ?? '' ),
			'refund_due'    => ! empty( $s['refund_due'] ),
		);
	}

	/** Same unambiguous ref alphabet the spare-parts plugin uses. */
	/**
	 * Trim free text to something a TEXT column can hold, on a CHARACTER
	 * boundary.
	 *
	 * ⚠️ Every length check in this file was a MINIMUM. There was no maximum
	 * anywhere, so the only thing between a pasted document and the database
	 * was the database: a TEXT column stops at 65,535 BYTES, and with MySQL in
	 * strict mode the INSERT fails outright. The customer had filled in the
	 * form, uploaded proof photos, and got "Something went wrong."
	 *
	 * ⚠️ mb_substr, never substr. A Bangla character is THREE bytes in UTF-8,
	 * so a byte-wise cut can land in the middle of one and produce a string
	 * that is no longer valid UTF-8 — which MySQL rejects and json_encode turns
	 * into null. Cutting by characters cannot do that.
	 *
	 * The app caps the same fields as you type (maxLength on the field), so
	 * this only ever fires for a caller that is not the app. It must still
	 * exist: an API that trusts its client to enforce a limit has no limit.
	 *
	 * @param string $text  Already-sanitised text.
	 * @param int    $chars Maximum characters to keep.
	 * @return string
	 */
	private static function cap( $text, $chars ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $chars, 'UTF-8' );
		}
		// ⚠️ The fallback must NOT be plain substr(). mbstring is normally
		// present (WordPress recommends it, and this very bench turned out to
		// be built without it), but a fallback that reintroduces the exact bug
		// this method exists to prevent is worse than no fallback at all.
		// preg's /u modifier matches whole UTF-8 characters, so `.{0,N}` cuts
		// on a character boundary with no mbstring at all.
		if ( preg_match( '/^.{0,' . (int) $chars . '}/us', $text, $m ) ) {
			return $m[0];
		}
		return $text;
	}

	private static function generate_ref( $table, $prefix ) {
		global $wpdb;
		$alpha = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
		$year  = current_time( 'Y' );
		for ( $tries = 0; $tries < 12; $tries++ ) {
			$code = '';
			for ( $i = 0; $i < 6; $i++ ) {
				$code .= $alpha[ random_int( 0, strlen( $alpha ) - 1 ) ];
			}
			$ref = $prefix . '-' . $year . '-' . $code;
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE ref = %s", $ref ) ) ) {
				return $ref;
			}
		}
		return $prefix . '-' . $year . '-' . strtoupper( substr( md5( uniqid() ), 0, 6 ) );
	}

	/**
	 * Create a spare-parts request from the app.
	 *
	 * @param array $args {
	 *     user_id, customer_name, phone (canonical), address, model,
	 *     purchase_date (Y-m-d|''), serial (''), note (''),
	 *     parts    string[] part keys,
	 *     photos   array<part_key, $_FILES entry> proof uploads.
	 * }
	 * @return array{ok:bool,code:string,message:string,ref?:string}
	 */
	public static function create_part_request( $args ) {
		global $wpdb;

		if ( ! self::parts_available() ) {
			return array(
				'ok'      => false,
				'code'    => 'parts_unavailable',
				'message' => 'Spare parts service is temporarily unavailable.',
			);
		}

		$catalog = array();
		foreach ( self::parts_catalog() as $p ) {
			$catalog[ $p['key'] ] = $p;
		}

		$selected = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $args['parts'] ) ) ) );
		$selected = array_values( array_intersect( $selected, array_keys( $catalog ) ) );
		if ( empty( $selected ) ) {
			return array(
				'ok'      => false,
				'code'    => 'no_parts',
				'message' => 'Please choose at least one part.',
			);
		}

		$model = sanitize_text_field( (string) $args['model'] );
		if ( '' === $model ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_model',
				'message' => 'Please tell us your projector model.',
			);
		}

		$address = self::cap( sanitize_textarea_field( (string) $args['address'] ), 300 );
		if ( strlen( $address ) < 8 ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_address',
				'message' => 'Please give the full delivery address.',
			);
		}

		$pdate = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['purchase_date'] ) ? $args['purchase_date'] : null;

		$months      = max( 1, (int) get_option( 'aun_sp_warranty_months', 12 ) );
		$grace       = max( 0, (int) get_option( 'aun_sp_warranty_grace_days', 4 ) );
		$warranty_in = 0;
		if ( $pdate ) {
			$warranty_in = ( strtotime( $pdate . " +{$months} months +{$grace} days" ) >= strtotime( date( 'Y-m-d' ) ) ) ? 1 : 0;
		}

		$t_req  = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );
		$now    = current_time( 'mysql' );
		$phone  = (string) $args['phone'];

		// ⚠️ The WEBSITE form has always guarded against this (AUN_SP_Form's
		// open_requests() + a "you already have a request in progress" dialog).
		// The APP had no such check, so it was the one route that could pile up
		// duplicate requests for the same projector — the protection existed and
		// the app simply walked past it.
		//
		// Unlike the website, which warns and lets the customer continue, this
		// REFUSES and hands back the existing ref: the app can show them the
		// live progress of the request they already have, which is what they
		// actually wanted. `confirm` lets a genuinely different need through.
		if ( empty( $args['confirm_duplicate'] ) ) {
			$open = self::open_parts_request( $phone, (string) $args['serial'] );
			if ( $open ) {
				return array(
					'ok'      => false,
					'code'    => 'already_open',
					'ref'     => (string) $open->ref,
					'status'  => (string) $open->overall_status,
					'message' => 'You already have a spare-parts request in progress for this projector.',
				);
			}
		}

		$ok = $wpdb->insert( $t_req, array(
			'ref'             => '',
			'source_type'     => 'app',
			'source_order'    => sanitize_text_field( (string) $args['serial'] ),
			'model'           => $model,
			'purchase_date'   => $pdate,
			'warranty_in'     => $warranty_in,
			'customer_name'   => sanitize_text_field( (string) $args['customer_name'] ),
			'phone_current'   => $phone,
			'address_current' => $address,
			'phone_onfile'    => $phone,
			'address_onfile'  => '',
			'channel'         => 'app',
			'overall_status'  => 'submitted',
			'created_at'      => $now,
			'updated_at'      => $now,
		) );

		if ( ! $ok ) {
			return array(
				'ok'      => false,
				'code'    => 'db_error',
				'message' => 'Could not save the request. Please try again.',
			);
		}
		$request_id = (int) $wpdb->insert_id;

		$ref = self::generate_ref( $t_req, 'SP' );
		$wpdb->update( $t_req, array( 'ref' => $ref ), array( 'id' => $request_id ) );

		$note = self::cap( sanitize_textarea_field( (string) $args['note'] ), 1000 );

		// Quantities, clamped to the website's own 1..max_qty range. Anything
		// missing or junk becomes 1, which is exactly the old behaviour.
		$max_qty  = self::parts_max_qty();
		$qty_in   = (array) ( $args['qty'] ?? array() );
		$qty      = array();
		foreach ( $selected as $key ) {
			$n            = isset( $qty_in[ $key ] ) ? (int) $qty_in[ $key ] : 1;
			$qty[ $key ]  = max( 1, min( $max_qty, $n ) );
		}

		$has_photo = false;
		foreach ( $selected as $key ) {
			$wpdb->insert( $t_item, array(
				'request_id'  => $request_id,
				'part_type'   => $key,
				'part_label'  => $catalog[ $key ]['label_en'],
				'qty'         => $qty[ $key ],
				'line_status' => 'pending',
				'note'        => '' !== $note ? $note : null,
				'created_at'  => $now,
				'updated_at'  => $now,
			) );
			$item_id = (int) $wpdb->insert_id;

			// Proof photo for this part, when supplied.
			if ( ! empty( $args['photos'][ $key ] ) ) {
				$url = self::store_upload( $args['photos'][ $key ], 'aun-spare-parts' );
				if ( $url ) {
					$wpdb->insert( AUN_SP_Install::table( 'attachments' ), array(
						'request_id'   => $request_id,
						'item_id'      => $item_id,
						'kind'         => 'proof',
						'file_url'     => $url,
						'bytes_before' => self::stored_size( $url ),
						'created_at'   => $now,
					) );
					$has_photo = true;
				}
			}
		}

		// ⚠️ Ask the spare-parts plugin to compress these photos, exactly as its
		// own form does after an upload.
		//
		// The app used to write the rows and stop there, so every photo sent
		// from a phone stayed on disk at full size — spare parts 0.43.0 had to
		// add an hourly sweep specifically to find them. The sweep is only a
		// safety net: it takes 10 an hour and ignores anything under 10 minutes
		// old. Queuing here compresses them straight after this response, the
		// same as a website upload.
		if ( ! empty( $has_photo ) && method_exists( 'AUN_SP_Image', 'queue' ) ) {
			AUN_SP_Image::queue( $request_id );
		}

		// Spell the order out in the event log the way the website does, so a
		// staff member reading the request sees the quantities at a glance.
		$lines = array();
		foreach ( $selected as $key ) {
			$lines[] = $catalog[ $key ]['label_en'] . ( $qty[ $key ] > 1 ? ' ×' . $qty[ $key ] : '' );
		}
		$wpdb->insert( AUN_SP_Install::table( 'events' ), array(
			'request_id' => $request_id,
			'type'       => 'created',
			'message'    => 'Request submitted via Android app (user #' . (int) $args['user_id'] . '): ' . implode( ', ', $lines ),
			'by_user'    => 'app',
			'created_at' => $now,
		) );

		// ⚠️ The spare-parts plugin ALREADY owns this message
		// (Spare Parts -> Messages -> "Request received"), and the website form
		// sends exactly that. The app used to send its own hardcoded sentence,
		// so one event produced two different texts depending on which door the
		// customer came through, and editing the admin template changed only
		// half of them. Use the plugin's template; fall back only if an older
		// copy of it is installed.
		if ( class_exists( 'AUN_SP_Messages' ) ) {
			$msg = AUN_SP_Messages::fill(
				AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_RECEIVED ),
				array(
					'ref'   => $ref,
					'model' => $model,
					'track' => AUN_SP_Messages::track_link( $ref ),
				)
			);
		} else {
			$msg = 'AUN: we received your spare-parts request ' . $ref . '. We will send a price quote by SMS soon.';
		}
		AUN_App_SMS::send( $phone, $msg );

		return array(
			'ok'      => true,
			'code'    => 'created',
			'message' => 'Request received.',
			'ref'     => $ref,
		);
	}

	/* --------------------------------------------------------------------- *
	 * Repairs (send the projector to us)
	 * --------------------------------------------------------------------- */

	/**
	 * Create a send-in repair request.
	 *
	 * @param array $args {user_id, customer_name, phone, address, serial, model, issue, photos $_FILES[]}
	 * @return array{ok:bool,code:string,message:string,ref?:string}
	 */
	/**
	 * An unfinished spare-parts request for this projector, if any.
	 *
	 * Matches the plugin's own definition of "open" by reading
	 * AUN_SP_Requests::TERMINAL_STATES rather than restating the list — the
	 * plugin has added statuses twice already (declined, then expired), and a
	 * second copy of that list here would have silently gone out of date both
	 * times.
	 *
	 * Phone matching is format-tolerant for the same reason the plugin's is: the
	 * app stores 8801XXXXXXXXX and the web form 01XXXXXXXXX.
	 */
	public static function open_parts_request( $phone, $serial = '' ) {
		global $wpdb;
		if ( ! self::parts_available() || '' === trim( (string) $phone ) ) {
			return null;
		}

		$terminal = class_exists( 'AUN_SP_Requests' ) && defined( 'AUN_SP_Requests::TERMINAL_STATES' )
			? AUN_SP_Requests::TERMINAL_STATES
			: array( 'closed', 'rejected', 'declined', 'expired' );

		$t        = AUN_SP_Install::table( 'requests' );
		$variants = AUN_App_Phone::variants( AUN_App_Phone::normalize( (string) $phone ) );
		$ph_ph    = implode( ',', array_fill( 0, count( $variants ), '%s' ) );
		$ph_t     = implode( ',', array_fill( 0, count( $terminal ), '%s' ) );

		$params = array_merge( $variants, $variants, $terminal );
		$sql    = "SELECT id, ref, overall_status, source_order FROM $t
		           WHERE ( phone_current IN ($ph_ph) OR phone_onfile IN ($ph_ph) )
		             AND overall_status NOT IN ($ph_t)";

		// Scoped to the DEVICE when we know which one: a customer with two
		// projectors may legitimately need parts for both at once. `source_order`
		// is where the app stores the serial it was raised against.
		$serial = trim( (string) $serial );
		if ( '' !== $serial ) {
			$sql     .= ' AND source_order = %s';
			$params[] = $serial;
		}
		$sql .= ' ORDER BY created_at DESC LIMIT 1';

		return $wpdb->get_row( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * The repair this customer already has open for this projector, if any.
	 *
	 * ⚠️ Without this, one customer could file the same repair ten times: every
	 * submission created a fresh row, our Repairs list filled with duplicates of
	 * one physical projector, and each duplicate independently tried to adopt
	 * the ERP job sheet. The customer was not being difficult — the app gave
	 * them no way to see they had already asked, so asking again was the only
	 * sensible thing to do.
	 *
	 * Matched on SERIAL, not on the customer: someone with three projectors may
	 * legitimately have three repairs open at once. It is the same *device*
	 * twice that is the mistake.
	 *
	 * @return object|null The open row, newest first.
	 */
	public static function open_repair_for( $user_id, $serial, $model = '' ) {
		global $wpdb;
		$serial  = trim( (string) $serial );
		$user_id = (int) $user_id;
		if ( $user_id < 1 ) {
			return null;
		}

		$t  = self::repairs_table();
		$ph = implode( ',', array_fill( 0, count( self::REPAIR_FINAL ), '%s' ) );

		// ⚠️ A blank serial used to return null, which meant NO GUARD AT ALL for
		// any request whose serial was left empty — the exact case a customer
		// hits when their projector's label is worn off, and the one where they
		// are most likely to submit twice. Found by the bench, not by review.
		//
		// With no serial we cannot tell two of the customer's projectors apart,
		// so we fall back to the MODEL, which a repair request always carries.
		// Slightly over-strict for someone sending two identical models at once
		// — rare, and they get the existing ref and can ask us — where the
		// alternative is no protection at all.
		if ( '' === $serial ) {
			$model = trim( (string) $model );
			$sql   = "SELECT * FROM $t
			          WHERE user_id = %d AND ( serial = '' OR serial IS NULL ) AND status NOT IN ($ph)";
			$args  = array_merge( array( $user_id ), self::REPAIR_FINAL );
			if ( '' !== $model ) {
				$sql   .= ' AND model = %s';
				$args[] = $model;
			}
			$sql .= ' ORDER BY created_at DESC LIMIT 1';
			return $wpdb->get_row( $wpdb->prepare( $sql, $args ) );
		}

		$sql = "SELECT * FROM $t
		        WHERE user_id = %d AND serial = %s AND status NOT IN ($ph)
		        ORDER BY created_at DESC LIMIT 1";
		return $wpdb->get_row( $wpdb->prepare( $sql, array_merge( array( $user_id, $serial ), self::REPAIR_FINAL ) ) );
	}

	/**
	 * Record the courier tracking number the customer sends us.
	 *
	 * Kept ON THE REPAIR ROW so it shows next to the request in admin. The old
	 * route was a pre-filled WhatsApp message, which put the number in a
	 * different system from the request it belongs to — findable only by
	 * scrolling a chat, and invisible to whoever is actually receiving parcels.
	 */
	public static function set_repair_tracking( $user_id, $ref, $courier, $tracking ) {
		global $wpdb;
		$t   = self::repairs_table();
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, status FROM $t WHERE ref = %s AND user_id = %d LIMIT 1",
			strtoupper( trim( (string) $ref ) ),
			(int) $user_id
		) );
		if ( ! $row ) {
			return array( 'ok' => false, 'code' => 'not_found', 'message' => 'That repair was not found on your account.' );
		}

		// ⚠️ A finished repair must not accept one. The projector is already
		// back with the customer, so a number arriving now is either a mistake
		// or a stale retry — and it would OVERWRITE the number that actually
		// tracked the parcel we received, destroying the only record of it.
		if ( in_array( (string) $row->status, self::REPAIR_FINAL, true ) ) {
			return array(
				'ok'      => false,
				'code'    => 'already_finished',
				'message' => 'This repair is already finished, so we are no longer expecting a parcel for it.',
			);
		}

		$tracking = trim( sanitize_text_field( (string) $tracking ) );
		if ( strlen( $tracking ) < 4 ) {
			return array( 'ok' => false, 'code' => 'invalid', 'message' => 'Please enter the tracking number from your courier receipt.' );
		}

		$wpdb->update(
			$t,
			array(
				'courier_name'     => substr( sanitize_text_field( (string) $courier ), 0, 60 ),
				'courier_tracking' => substr( $tracking, 0, 80 ),
				'courier_at'       => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => (int) $row->id )
		);

		// Tell the admin: a parcel is now in transit and somebody should expect
		// it. Without this the number sits in a database column nobody opens.
		$to = trim( (string) get_option( 'admin_email' ) );
		if ( '' !== $to ) {
			wp_mail(
				$to,
				'[AUN app] Projector on its way — ' . $ref,
				"The customer has sent their projector for repair {$ref}.\n\n"
					. "Courier: " . ( $courier !== '' ? $courier : '(not stated)' ) . "\n"
					. "Tracking: {$tracking}\n\n"
					. admin_url( 'admin.php?page=aun-app-repairs' ) . "\n"
			);
		}

		return array( 'ok' => true, 'code' => 'saved' );
	}

	public static function create_repair( $args ) {
		global $wpdb;

		// ⚠️ One open repair per PROJECTOR. See open_repair_for().
		$existing = self::open_repair_for(
			(int) $args['user_id'],
			(string) ( $args['serial'] ?? '' ),
			(string) ( $args['model'] ?? '' )
		);
		if ( $existing ) {
			return array(
				'ok'      => false,
				'code'    => 'already_open',
				'ref'     => (string) $existing->ref,
				'status'  => (string) $existing->status,
				'message' => 'You already have a repair in progress for this projector.',
			);
		}

		$issue = self::cap( sanitize_textarea_field( (string) $args['issue'] ), 2000 );
		if ( strlen( $issue ) < 10 ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_issue',
				'message' => 'Please describe the problem in a few words.',
			);
		}
		$model = sanitize_text_field( (string) $args['model'] );
		if ( '' === $model ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_model',
				'message' => 'Please tell us your projector model.',
			);
		}

		$photo_urls = array();
		foreach ( (array) ( $args['photos'] ?? array() ) as $file ) {
			$url = self::store_upload( $file, 'aun-repairs' );
			if ( $url ) {
				$photo_urls[] = $url;
			}
			if ( count( $photo_urls ) >= 4 ) {
				break;
			}
		}

		$table = self::repairs_table();
		$now   = current_time( 'mysql' );

		$ok = $wpdb->insert( $table, array(
			'ref'           => '',
			'user_id'       => (int) $args['user_id'],
			'customer_name' => sanitize_text_field( (string) $args['customer_name'] ),
			'phone'         => (string) $args['phone'],
			'address'       => self::cap( sanitize_textarea_field( (string) $args['address'] ), 300 ),
			'serial'        => AUN_App_Warranty::sanitize_serial( (string) $args['serial'] ),
			'model'         => $model,
			'issue'         => $issue,
			'photos'        => wp_json_encode( $photo_urls ),
			'status'        => 'submitted',
			'created_at'    => $now,
			'updated_at'    => $now,
		) );

		if ( ! $ok ) {
			return array(
				'ok'      => false,
				'code'    => 'db_error',
				'message' => 'Could not save the request. Please try again.',
			);
		}
		$id  = (int) $wpdb->insert_id;
		$ref = self::generate_ref( $table, 'RP' );
		$wpdb->update( $table, array( 'ref' => $ref ), array( 'id' => $id ) );

		// The customer has been told to wait for our answer before shipping, so
		// surface this in the admin bar immediately rather than up to a minute late.
		delete_transient( 'aun_app_pending_repairs_count' );

		// ⚠️ An emptied template means "send nothing for this event", which is a
		// legitimate choice. Handing a blank body to the gateway is a wasted
		// send and, on some gateways, a billed one.
		$sms = self::repair_sms( 'received', array( 'ref' => $ref, 'model' => $model ) );
		if ( '' !== $sms ) {
			AUN_App_SMS::send( (string) $args['phone'], $sms );
		}

		return array(
			'ok'      => true,
			'code'    => 'created',
			'message' => 'Repair request received.',
			'ref'     => $ref,
		);
	}

	/* --------------------------------------------------------------------- *
	 * ERP job-sheet linking
	 *
	 * The app request is only a PICKUP request. The moment the service centre
	 * receives the projector they create a job sheet in the UltimatePOS Repair
	 * module exactly as they always have (that module sends its own SMS). We
	 * auto-link the app request to that job sheet by phone + serial, and from
	 * then on the app shows the live ERP status — one reference (the job sheet
	 * number), one SMS stream, zero double entry.
	 * --------------------------------------------------------------------- */

	/** App statuses where a job sheet may appear (pre-service pipeline). */
	const REPAIR_LINKABLE = array( 'submitted', 'approved', 'received', 'repairing', 'ready' );

	/**
	 * Ensure an app repair row is linked to its ERP job sheet when one exists,
	 * and return the live ERP job sheet for a linked row.
	 *
	 * @param object $row       aun_app_repairs row.
	 * @param string $canonical Customer phone 8801XXXXXXXXX.
	 * @return array|null Normalised ERP job sheet, or null when none/unavailable.
	 */
	/** App statuses that are final — no more ERP syncing for these rows. */
	const REPAIR_FINAL = array( 'closed', 'rejected', 'returned' );

	public static function repair_erp_sync( $row, $canonical, $fetch_final = true ) {
		global $wpdb;

		if ( ! AUN_App_ERP::repair_configured() ) {
			return null;
		}

		// Already linked → fetch the live status (2-min cached). List views
		// pass $fetch_final=false so long-finished rows don't trigger a lookup
		// on every page load; the detail screen still fetches the final
		// timeline.
		if ( '' !== (string) $row->job_sheet_no ) {
			if ( ! $fetch_final && in_array( (string) $row->status, self::REPAIR_FINAL, true ) ) {
				return null;
			}
			$erp = AUN_App_ERP::repair_by_job_sheet( (string) $row->job_sheet_no );
			if ( is_array( $erp ) ) {
				self::maybe_close( $row, $erp );
			}
			return is_array( $erp ) ? $erp : null;
		}

		if ( '' === (string) $row->serial || '' === $canonical
			|| ! in_array( (string) $row->status, self::REPAIR_LINKABLE, true ) ) {
			return null;
		}

		// Phone + serial first; then serial alone — the job sheet may have been
		// created under a different POS customer contact, but the serial always
		// identifies the physical unit (and this row's serial is a device the
		// caller verifiably owns). Each candidate must individually pass:
		//  * the date guard — only a job sheet created around/after this
		//    request may link (the same device can have older job sheets from
		//    past repairs);
		//  * the completed guard — a job sheet already in a business-defined
		//    terminal status (repair_statuses.is_completed_status) that predates
		//    this request is a FINISHED past repair, never this one. Without it
		//    every new request re-adopts the customer's last delivered job.
		$req_ts     = strtotime( (string) $row->created_at );
		$candidates = array(
			AUN_App_ERP::repair_by_phone( $canonical, (string) $row->serial ),
			AUN_App_ERP::repair_by_serial( (string) $row->serial ),
		);
		$erp = null;
		foreach ( $candidates as $candidate ) {
			if ( ! is_array( $candidate ) || '' === $candidate['job_sheet_no'] ) {
				continue;
			}
			$job_ts = strtotime( (string) $candidate['created_at'] );
			if ( $job_ts && $req_ts && $job_ts < $req_ts - 3 * DAY_IN_SECONDS ) {
				continue; // an earlier, unrelated repair of the same device
			}
			if ( ! empty( $candidate['completed'] ) && $job_ts && $req_ts && $job_ts < $req_ts ) {
				continue; // finished BEFORE this request existed — a past repair
			}
			$erp = $candidate;
			break;
		}
		if ( null === $erp ) {
			return null;
		}

		$update = array(
			'job_sheet_no' => $erp['job_sheet_no'],
			'updated_at'   => current_time( 'mysql' ),
		);
		// The projector is physically at the service centre now — or, if the
		// job sheet is already in a terminal status, the whole cycle is done.
		if ( in_array( (string) $row->status, array( 'submitted', 'approved' ), true ) ) {
			$update['status'] = empty( $erp['completed'] ) ? 'received' : 'closed';
			$row->status      = $update['status'];
		}
		$wpdb->update( self::repairs_table(), $update, array( 'id' => (int) $row->id ) );
		$row->job_sheet_no = $erp['job_sheet_no'];

		return $erp;
	}

	/** How long a job sheet must read as "definitely not there" before we
	 *  accept that it is gone. Polls run every 10 minutes, so this is ~144
	 *  consecutive misses — deliberately patient, because an admin who deletes
	 *  and immediately recreates a job sheet should cause no churn at all. */
	const ERP_MISSING_GRACE = 24 * HOUR_IN_SECONDS;

	/**
	 * The linked job sheet came back as a definite "no such record".
	 *
	 * Start a clock on the first miss; only after [ERP_MISSING_GRACE] of
	 * UNBROKEN misses do we accept the deletion and unlink.
	 *
	 * ⚠️ Unlinking clears `job_sheet_no` but deliberately does NOT touch
	 * `status`. Every status a linked repair can be in is already in
	 * REPAIR_LINKABLE, so an empty job_sheet_no is all link_pending_repairs()
	 * needs to adopt a replacement — and winding the customer's status back
	 * from "ready" to "received" would be a visible lie about where their
	 * projector is.
	 *
	 * Being wrong here is cheap and self-correcting: if we unlink and the sheet
	 * turns out to exist after all, the next linking run matches it again by
	 * phone + serial. Being wrong the OTHER way — never noticing — is what we
	 * had.
	 *
	 * @param object $row aun_app_repairs row (mutated in place).
	 */
	private static function handle_missing_job_sheet( $row ) {
		global $wpdb;
		$table = self::repairs_table();
		$now   = current_time( 'timestamp' );

		if ( empty( $row->erp_missing_since ) ) {
			$wpdb->update(
				$table,
				array( 'erp_missing_since' => current_time( 'mysql' ) ),
				array( 'id' => (int) $row->id )
			);
			$row->erp_missing_since = current_time( 'mysql' );
			return;
		}

		if ( ( $now - strtotime( (string) $row->erp_missing_since ) ) < self::ERP_MISSING_GRACE ) {
			return; // still inside the grace window
		}

		// Captured BEFORE the update below blanks them — the email is the only
		// record anyone will have of what was lost.
		$lost_job    = (string) $row->job_sheet_no;
		$last_status = (string) $row->last_erp_status;

		$wpdb->update( $table, array(
			'job_sheet_no'      => '',
			'last_erp_status'   => '',
			'erp_missing_since' => null,
			'updated_at'        => current_time( 'mysql' ),
		), array( 'id' => (int) $row->id ) );
		$row->job_sheet_no      = '';
		$row->last_erp_status   = '';
		$row->erp_missing_since = null;

		// STAFF, not the customer. "Our records lost your repair" is not a
		// message a customer can act on, and the app keeps showing them the
		// last real status while somebody sorts it out.
		$to = get_option( 'admin_email' );
		if ( $to ) {
			wp_mail(
				$to,
				sprintf( '[AUN App] Job sheet %s has disappeared from the ERP', $lost_job ),
				sprintf(
					"Repair request: %s\nCustomer phone: %s\nSerial: %s\nModel: %s\nLast known ERP status: %s\n\n"
					. "Job sheet %s returned \"no such record\" continuously for over 24 hours, so the app has "
					. "unlinked it. The request is NOT closed and the customer still sees their last known status.\n\n"
					. "If you create a new job sheet for this phone + serial the app will adopt it automatically "
					. "within 10 minutes. If the repair is genuinely finished, close it in AUN App -> Repairs.\n",
					(string) $row->ref,
					(string) $row->phone,
					(string) $row->serial,
					(string) $row->model,
					$last_status,
					$lost_job
				)
			);
		}
	}

	/**
	 * Auto-close a linked request once its job sheet reaches a terminal ERP
	 * status ("Delivered / Collected" etc. — whatever the business flags as
	 * completed in Repair Settings).
	 *
	 * @param object $row aun_app_repairs row (mutated in place).
	 * @param array  $erp Normalised ERP job sheet.
	 */
	private static function maybe_close( $row, $erp ) {
		global $wpdb;
		if ( empty( $erp['completed'] )
			|| in_array( (string) $row->status, self::REPAIR_FINAL, true ) ) {
			return;
		}
		$wpdb->update( self::repairs_table(), array(
			'status'     => 'closed',
			'updated_at' => current_time( 'mysql' ),
		), array( 'id' => (int) $row->id ) );
		$row->status = 'closed';
	}

	/**
	 * Cron: check every active linked repair's live ERP status and push the
	 * owner a notification when it changes (Received → Under Repair → Ready →
	 * Delivered, whatever the service centre sets). The ERP has no webhooks, so
	 * this poll is how a status change reaches the customer while the app is
	 * closed.
	 *
	 * Safe + cheap: only repairs that have a job sheet and aren't already in a
	 * terminal app status are checked; an ERP outage for a row is skipped (never
	 * touches its state); the FIRST time a repair is seen we record its status as
	 * a baseline WITHOUT pushing (so we don't announce a status the customer has
	 * already been looking at). `last_erp_status` (DB v11) is the memory.
	 *
	 * @param int $limit Max repairs per run.
	 * @return array{checked:int,changed:int,skipped:int}
	 */
	/**
	 * Cron half of job-sheet linking: find app requests that are waiting for a
	 * job sheet and adopt one if the service centre has created it.
	 *
	 * Reuses repair_erp_sync() so the matching rules (phone + serial, the date
	 * guard, the completed guard) stay in exactly one place — this must never
	 * become a second, subtly different matcher.
	 *
	 * When a link is made the customer is told, because "we have received your
	 * projector" is the single most reassuring message in the whole flow and it
	 * was previously swallowed: the status poll treats its FIRST observation as
	 * a silent baseline, so the very moment the job sheet appeared produced no
	 * notification at all.
	 *
	 * @param int $limit Max rows per run.
	 * @return int How many were linked.
	 */
	public static function link_pending_repairs( $limit = 40 ) {
		global $wpdb;
		if ( ! AUN_App_ERP::repair_configured() ) {
			return 0;
		}

		$table       = self::repairs_table();
		$linkable    = self::REPAIR_LINKABLE;
		$linkable_ph = implode( ',', array_fill( 0, count( $linkable ), '%s' ) );

		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table
			  WHERE job_sheet_no = '' AND serial != '' AND status IN ($linkable_ph)
			  ORDER BY updated_at ASC LIMIT %d",
			array_merge( $linkable, array( (int) $limit ) )
		) );

		$linked = 0;
		foreach ( $rows as $row ) {
			$canonical = AUN_App_Phone::normalize( (string) $row->phone );
			if ( '' === $canonical ) {
				continue;
			}

			$erp = self::repair_erp_sync( $row, $canonical );
			if ( ! is_array( $erp ) || '' === (string) $erp['job_sheet_no'] ) {
				continue; // no job sheet yet — try again next run
			}
			$linked++;

			$uid = (int) $row->user_id;
			if ( $uid < 1 ) {
				foreach ( AUN_App_Phone::find_users( $canonical ) as $u ) {
					$uid = (int) $u->ID;
					break;
				}
			}
			if ( $uid < 1 ) {
				continue;
			}

			$status = trim( (string) ( $erp['status'] ?? '' ) );
			if ( '' === $status ) {
				continue;
			}

			AUN_App_Notices::repair_received( $uid, (string) $row->ref, (string) $erp['job_sheet_no'], $status );

			// Record the baseline NOW so the status poll doesn't immediately
			// announce this same status a second time.
			$wpdb->update( $table, array( 'last_erp_status' => $status ), array( 'id' => (int) $row->id ) );
		}

		return $linked;
	}

	public static function poll_repair_statuses( $limit = 40 ) {
		global $wpdb;
		$stats = array( 'checked' => 0, 'changed' => 0, 'skipped' => 0 );
		if ( ! AUN_App_ERP::repair_configured() ) {
			return $stats;
		}

		$table    = self::repairs_table();
		$final    = self::REPAIR_FINAL;
		$final_ph = implode( ',', array_fill( 0, count( $final ), '%s' ) );

		// STEP 1 — adopt job sheets for requests that don't have one yet.
		//
		// Linking used to happen ONLY inside repair_erp_sync(), which runs when
		// the customer opens the app. So a customer who shipped their projector
		// and then waited quietly was never linked, never polled, and never
		// notified — the pipeline looked like a dead end precisely for the
		// people being most patient. Doing it here means "we received it" lands
		// whether or not they open the app.
		self::link_pending_repairs( $limit );

		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table
			  WHERE job_sheet_no != '' AND status NOT IN ($final_ph)
			  ORDER BY updated_at ASC LIMIT %d",
			array_merge( $final, array( (int) $limit ) )
		) );

		// Rows whose job sheet answered "no such record" this run. Nothing is
		// decided about them until the loop ends — see the note after it.
		$missing = array();

		foreach ( $rows as $row ) {
			$erp = AUN_App_ERP::repair_by_job_sheet( (string) $row->job_sheet_no );

			// ⚠️ THESE TWO FAILURES ARE NOT THE SAME, and treating them alike
			// was a real hole: `! is_array( $erp )` collapsed them, so a job
			// sheet DELETED in the ERP looked exactly like the ERP being down.
			// The row was skipped for ever — the customer's repair froze on
			// "Projector received" with a "see the progress" button that could
			// never progress, and because link_pending_repairs() only considers
			// rows with an EMPTY job_sheet_no, a replacement job sheet could
			// never be adopted either. Nobody would have found out.
			//
			// repair_get() already tells them apart and we were discarding it:
			//   WP_Error → transport/config failure  → the ERP is unreachable
			//   false    → HTTP 404 / success:false  → there is no such record
			if ( is_wp_error( $erp ) ) {
				$stats['skipped']++;
				continue; // outage — touch nothing, not even the missing clock
			}
			if ( false === $erp ) {
				// ⚠️ DEFERRED, not acted on here. A 404 only means "deleted" if
				// the ERP is actually answering — and we cannot know that until
				// the whole run is done. Held until after the loop; see below.
				$missing[] = $row;
				$stats['skipped']++;
				continue;
			}
			if ( ! is_array( $erp ) ) {
				$stats['skipped']++;
				continue; // unexpected shape — stay conservative
			}

			// Found. Clear any missing-clock a previous run started.
			if ( ! empty( $row->erp_missing_since ) ) {
				$wpdb->update(
					$table,
					array( 'erp_missing_since' => null ),
					array( 'id' => (int) $row->id )
				);
				$row->erp_missing_since = null;
			}
			$stats['checked']++;

			// Keep the app-side status in sync and auto-close finished jobs.
			self::maybe_close( $row, $erp );

			$status = trim( (string) ( $erp['status'] ?? '' ) );
			if ( '' === $status ) {
				continue;
			}
			$prev = trim( (string) ( $row->last_erp_status ?? '' ) );
			if ( 0 === strcasecmp( $prev, $status ) ) {
				continue; // unchanged
			}

			// A real change (and not the first baseline observation) → notify.
			if ( '' !== $prev ) {
				$uid = (int) $row->user_id;
				if ( $uid < 1 && '' !== (string) $row->phone ) {
					$canon = AUN_App_Phone::normalize( (string) $row->phone );
					if ( $canon ) {
						foreach ( AUN_App_Phone::find_users( $canon ) as $u ) {
							$uid = (int) $u->ID;
							break;
						}
					}
				}
				if ( $uid > 0 && class_exists( 'AUN_App_Notices' ) ) {
					AUN_App_Notices::repair_status_changed( $uid, (string) $row->ref, $status );
					$stats['changed']++;
				}
			}

			$wpdb->update( $table, array( 'last_erp_status' => $status ), array( 'id' => (int) $row->id ) );
		}

		// ── Only now do we judge the 404s ────────────────────────────────────
		//
		// ⚠️ A 404 is only evidence of DELETION if the ERP is demonstrably
		// answering. `$stats['checked'] > 0` means at least one other job sheet
		// resolved in this same run, so the service is up and a "no such
		// record" means what it says.
		//
		// If NOTHING resolved, the ERP is having a bad day — a broken deploy,
		// a half-restored database, a proxy answering 404 for everything — and
		// its 404s carry no information. We do not even start the clock, so a
		// long outage cannot age a healthy repair into being unlinked.
		//
		// The owner's own words on this: job sheets are essentially never
		// deleted. So the cost of being slow here is nil, and the cost of being
		// wrong is a customer's live repair silently detached from its job
		// sheet. Bias hard towards doing nothing.
		//
		// Consequence worth knowing: if the ONLY active repair is the one whose
		// sheet was deleted, nothing else can prove the ERP is up, so it is
		// never unlinked and no email goes out. That is the safe direction to
		// fail, and it is why this feature is a safety net rather than a
		// guarantee.
		if ( $missing && $stats['checked'] > 0 ) {
			foreach ( $missing as $gone ) {
				self::handle_missing_job_sheet( $gone );
			}
		}

		return $stats;
	}

	/**
	 * Full detail for one repair, by app ref (RP-…) or job sheet no (2025/0001).
	 *
	 * @param string $ref       App ref or job sheet number.
	 * @param string $canonical Customer phone.
	 * @param int    $user_id   Customer user id.
	 * @return array|null {request:array|null, erp:array|null} or null when not found / not theirs.
	 */
	public static function repair_detail( $ref, $canonical, $user_id ) {
		global $wpdb;

		$ref = trim( (string) $ref );
		if ( '' === $ref ) {
			return null; // would otherwise match every unlinked row (job_sheet_no = '').
		}
		$table = self::repairs_table();

		// App request row (guarded to the caller's account).
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE (ref = %s OR job_sheet_no = %s) AND (user_id = %d OR phone = %s) LIMIT 1",
			$ref, $ref, (int) $user_id, (string) $canonical
		) );

		if ( $row ) {
			$erp = self::repair_erp_sync( $row, $canonical );
			return array(
				'request' => self::repair_entry( $row, $erp ),
				'erp'     => $erp,
			);
		}

		// No app row — a walk-in job sheet surfaced from the ERP. Verify it
		// really belongs to this phone before returning it.
		if ( false === strpos( $ref, 'RP-' ) && AUN_App_ERP::repair_configured() && '' !== $canonical ) {
			$erp = AUN_App_ERP::repair_by_job_sheet( $ref );
			if ( is_array( $erp ) ) {
				$mine = AUN_App_ERP::repair_by_phone( $canonical, $erp['serial_number'] );
				if ( is_array( $mine ) && $mine['job_sheet_no'] === $erp['job_sheet_no'] ) {
					return array(
						'request' => null,
						'erp'     => $erp,
					);
				}
			}
		}

		return null;
	}

	/**
	 * One repairs-list entry (app row + optional live ERP summary).
	 *
	 * @param object     $r   aun_app_repairs row.
	 * @param array|null $erp Normalised ERP job sheet.
	 * @return array
	 */
	private static function repair_entry( $r, $erp = null ) {
		return array(
			'ref'          => (string) $r->ref,
			'source'       => 'app',
			'serial'       => (string) $r->serial,
			'model'        => (string) $r->model,
			'issue'        => (string) $r->issue,
			'status'       => (string) $r->status,
			'note'         => (string) $r->admin_note,
			'created_at'   => substr( (string) $r->created_at, 0, 10 ),
			'job_sheet_no' => (string) $r->job_sheet_no,
			// ⚠️ The app needs this to know it has ALREADY had the tracking
			// number. Without it the "send us the tracking number" button came
			// back every time the screen was reopened, and the customer could
			// send it over and over — each one overwriting the last.
			'tracking'     => (string) ( $r->courier_tracking ?? '' ),
			'courier'      => (string) ( $r->courier_name ?? '' ),
			'erp_status'   => is_array( $erp ) ? (string) $erp['status'] : '',
			// ⚠️ The ERP's OWN is_completed_status flag, not a guess.
			//
			// We used to send only the status LABEL, so the app decided whether
			// a repair was finished by matching English words in it — and
			// "Ready for Delivery / Collection" contains both "deliver" and
			// "collect", so the customer's Home card vanished at the exact
			// moment their projector was ready to be picked up. A human-edited
			// label is not an API. This is.
			'erp_completed' => is_array( $erp ) && ! empty( $erp['completed'] ),
			'erp_cost'     => is_array( $erp ) ? (string) $erp['estimated_cost'] : '',
		);
	}

	/* --------------------------------------------------------------------- *
	 * "My service requests" (both kinds, newest first)
	 * --------------------------------------------------------------------- */

	/**
	 * Notify the app when the spare-parts plugin changes a request's status.
	 *
	 * Hooked to `aun_sp_status_changed`, which the spare-parts plugin fires the
	 * moment a quote is sent or the customer answers it — the two points where
	 * waiting for the customer to next open the app would be too late.
	 *
	 * @param int    $request_id Spare-parts request id.
	 * @param string $status     New overall status.
	 */
	public static function on_parts_status_changed( $request_id, $status ) {
		if ( ! self::parts_available() || ! class_exists( 'AUN_App_Phone' ) ) {
			return;
		}

		global $wpdb;
		$t_req = AUN_SP_Install::table( 'requests' );
		$r     = $wpdb->get_row( $wpdb->prepare(
			"SELECT ref, phone_current, quote_total, quoted_at FROM $t_req WHERE id = %d",
			(int) $request_id
		) );
		if ( ! $r || '' === (string) $r->phone_current ) {
			return;
		}

		// A parts request can be made without an app account (the web form), so
		// no matching user is the normal case, not an error — they get the SMS.
		$users = AUN_App_Phone::find_users( AUN_App_Phone::normalize( (string) $r->phone_current ) );
		if ( empty( $users ) ) {
			return;
		}

		$labels = class_exists( 'AUN_SP_Requests' ) ? AUN_SP_Requests::overall_statuses() : array();
		AUN_App_Notices::parts_status_changed(
			(int) $users[0]->ID,
			(string) $r->ref,
			(string) $status,
			(string) ( $labels[ $status ] ?? $status ),
			(float) $r->quote_total,
			(string) ( $r->quoted_at ?? '' )
		);
	}

	/**
	 * Mirror a spare-parts quote reminder into the app (spare parts 0.31.0).
	 *
	 * Hooked to `aun_sp_quote_reminder`, fired by the plugin's daily chase at the
	 * same moment it texts the customer. The SMS points at the tracking page; the
	 * app can take the answer with one tap, so the nudge belongs in both.
	 *
	 * @param int $request_id Spare-parts request id.
	 * @param int $which      1 = first reminder, 2 = final one.
	 */
	public static function on_parts_quote_reminder( $request_id, $which ) {
		if ( ! self::parts_available() || ! class_exists( 'AUN_App_Phone' ) ) {
			return;
		}

		global $wpdb;
		$t_req = AUN_SP_Install::table( 'requests' );
		$r     = $wpdb->get_row( $wpdb->prepare(
			"SELECT ref, phone_current, quote_total, quoted_at, quote_expires_at FROM $t_req WHERE id = %d",
			(int) $request_id
		) );
		if ( ! $r || '' === (string) $r->phone_current ) {
			return;
		}

		$users = AUN_App_Phone::find_users( AUN_App_Phone::normalize( (string) $r->phone_current ) );
		if ( empty( $users ) ) {
			return;
		}

		AUN_App_Notices::parts_quote_reminder(
			(int) $users[0]->ID,
			(string) $r->ref,
			(int) $which,
			(float) $r->quote_total,
			! empty( $r->quote_expires_at )
				? date_i18n( get_option( 'date_format' ), strtotime( (string) $r->quote_expires_at ) )
				: '',
			(string) ( $r->quoted_at ?? '' )
		);
	}

	/** How long a recoverable dead end stays on the Home strip. */
	const PARTS_RECOVERY_DAYS = 10;

	/**
	 * Should Home still be showing this spare-parts request?
	 *
	 * Three answers, not two:
	 *  - live work (anything not terminal) → yes, always;
	 *  - a real ending (completed, rejected) → no, immediately. There is
	 *    nothing left for the customer to do and Home is for what is live;
	 *  - expired or declined → yes, but only for PARTS_RECOVERY_DAYS. Both are
	 *    undoable in one tap, and the days right after are when someone
	 *    realises they do want the part. Leaving them for ever would turn the
	 *    strip into a graveyard; dropping them instantly would hide the one
	 *    action that rescues the request.
	 *
	 * @param object $r A row from the requests table.
	 */
	public static function parts_is_active( $r ) {
		$status = (string) $r->overall_status;

		$terminal = class_exists( 'AUN_SP_Requests' ) && defined( 'AUN_SP_Requests::TERMINAL_STATES' )
			? AUN_SP_Requests::TERMINAL_STATES
			: array( 'closed', 'rejected', 'declined', 'expired' );
		if ( ! in_array( $status, $terminal, true ) ) {
			return true;
		}

		if ( ! in_array( $status, array( 'expired', 'declined' ), true ) ) {
			return false;
		}

		// No timestamp is not a reason to hide something recoverable.
		$touched = strtotime( (string) ( $r->updated_at ?: $r->created_at ) );
		if ( ! $touched ) {
			return true;
		}
		return ( current_time( 'timestamp' ) - $touched ) < ( self::PARTS_RECOVERY_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * @param string $canonical User's canonical phone.
	 * @param int    $user_id   User id (repairs are linked by id too).
	 * @return array{spare_parts:array,repairs:array}
	 */
	/**
	 * @param string|null $lang 'bn'|'en' — only the re-worded money lines in the
	 *                          progress history are localised (every other entry is
	 *                          the plugin's stored English note, same as the website
	 *                          tracker shows). null lets the plugin decide.
	 */
	public static function my_requests( $canonical, $user_id, $lang = null ) {
		global $wpdb;

		$spare = array();
		if ( self::parts_available() ) {
			$variants     = AUN_App_Phone::variants( $canonical );
			$placeholders = implode( ',', array_fill( 0, count( $variants ), '%s' ) );
			$t_req        = AUN_SP_Install::table( 'requests' );
			$t_item       = AUN_SP_Install::table( 'request_items' );
			$t_event      = AUN_SP_Install::table( 'events' );

			// Human labels straight from the spare-parts plugin, so the app shows
			// EXACTLY what the website tracker shows.
			$ov  = class_exists( 'AUN_SP_Requests' ) ? AUN_SP_Requests::overall_statuses() : array();
			$ist = class_exists( 'AUN_SP_Requests' ) ? AUN_SP_Requests::item_statuses() : array();

			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $t_req WHERE phone_current IN ($placeholders) OR phone_onfile IN ($placeholders)
				 ORDER BY created_at DESC LIMIT 20",
				array_merge( $variants, $variants )
			) );

			// The customer's own photos for every request listed — one query.
			$photos = self::customer_photos( wp_list_pluck( (array) $rows, 'id' ) );

			foreach ( (array) $rows as $r ) {
				// Safety net for statuses the admin sets directly (ready, closed,
				// rejected, waiting_customer) which don't fire aun_sp_status_changed.
				// Deduped per (ref, status), so syncing repeatedly never re-notifies.
				// Bounded to recently-touched requests so deploying this doesn't
				// notify everyone about months-old requests they've long moved on from.
				$touched = strtotime( (string) ( $r->updated_at ?: $r->created_at ) );
				if ( $user_id > 0 && $touched && $touched > strtotime( '-14 days', current_time( 'timestamp' ) ) ) {
					AUN_App_Notices::parts_status_changed(
						(int) $user_id,
						(string) $r->ref,
						(string) $r->overall_status,
						(string) ( $ov[ $r->overall_status ] ?? $r->overall_status ),
						(float) $r->quote_total,
						(string) ( $r->quoted_at ?? '' )
					);
				}

				$items = $wpdb->get_results(
					$wpdb->prepare( "SELECT id, part_label, qty, line_status, eta, unit_price, tracking_no FROM $t_item WHERE request_id = %d", $r->id )
				);
				$req_photos = $photos[ (int) $r->id ] ?? array();

				// Status-history timeline — same event filter as the website
				// tracker (skip raw SMS logs and contact edits).
				// 'wc_order' is excluded as well as sms/contact_changed. Those
				// are the plumbing of the payment order — "order #9292 created",
				// "order #9292 refreshed — now ৳15.00" — written every time the
				// order is rebuilt. True, and none of the customer's business:
				// they tapped Pay twice and got three lines of accounting for
				// it. The money events they DO need ('payment', 'refund') carry
				// their own types and still come through.
				//
				// 'quote_reminder' is excluded for a different reason, and the
				// website tracker excludes it too: it records that WE chased
				// THEM. On the customer's own progress list that reads as
				// nagging, and it says nothing about their parts. The expiry
				// and their re-quote request are real events and do show.
				// ⚠️ ALLOW-LIST, from the plugin (spare parts 0.37.0) — never a
				// deny-list, and never a copy of the list.
				//
				// This WAS a deny-list, and it behaved the way deny-lists always do:
				// every event type added to the plugin afterwards leaked to the
				// customer by default. Two were leaking here —
				//   refund_due:          "REFUND DUE - Tk 3,400 was paid online and
				//                          the request is now declined"
				//   duplicate_confirmed: "Customer was warned this overlaps SP-0042
				//                          and chose to submit anyway"
				// — internal instructions to staff, shown to the customer as if they
				// were progress. The plugin's PUBLIC_EVENTS is the one list; reading
				// it means the next new type is private here too, automatically.
				$allowed = ( class_exists( 'AUN_SP_Requests' ) && defined( 'AUN_SP_Requests::PUBLIC_EVENTS' ) )
					? AUN_SP_Requests::PUBLIC_EVENTS
					: array( 'created', 'status_change', 'qty_change', 'quote_sent', 'approved',
						'declined', 'quote_expired', 'quote_revived', 'rejected', 'photo_request',
						'reupload', 'payment', 'refund' );
				$ph_ev    = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
				$events   = $wpdb->get_results( $wpdb->prepare(
					"SELECT type, message, created_at FROM $t_event
					 WHERE request_id = %d AND type IN ($ph_ev)
					 ORDER BY id ASC LIMIT 40",
					array_merge( array( $r->id ), $allowed )
				) );
				$timeline = array();
				foreach ( (array) $events as $e ) {
					// The two money events are RE-WORDED, exactly as the website
					// tracker does. Their stored note is internal and names the
					// WooCommerce order ("Online payment received for order #9275"),
					// which exposes shop order numbers and reads like our plumbing.
					$text = (string) $e->message;
					if ( 'payment' === $e->type || 'refund' === $e->type ) {
						$is_pay = ( 'payment' === $e->type );
						$amount = 0.0;
						if ( $is_pay ) {
							$ord    = ( class_exists( 'AUN_SP_Woo' ) && AUN_SP_Woo::is_active() )
								? AUN_SP_Woo::order_for( (int) $r->id ) : null;
							$amount = $ord ? (float) $ord->get_total() : 0.0;
						} else {
							$amount = isset( $r->refund_amount ) ? (float) $r->refund_amount : 0.0;
						}
						$slug = $is_pay
							? ( $amount > 0 ? 'tl_payment' : 'tl_payment_plain' )
							: ( $amount > 0 ? 'tl_refund' : 'tl_refund_plain' );
						$text = class_exists( 'AUN_SP_I18N' )
							? AUN_SP_I18N::msg( $slug, array( 'amount' => number_format( $amount, 2 ) ), $lang )
							// Plugin too old for these strings: say the plain fact
							// rather than the internal note.
							: ( $is_pay ? 'Payment received. Thank you.' : 'A refund has been issued to you.' );
					}
					$timeline[] = array(
						'date' => $e->created_at ? date_i18n( 'j M Y, g:i a', strtotime( $e->created_at ) ) : '',
						'text' => $text,
					);
				}

				// Delivery is charged on the WooCommerce order, so the app has to
				// show it too — otherwise the parts list totals ৳3,400 while the
				// Pay button says ৳3,520 and the customer stops trusting both.
				$delivery = isset( $r->delivery_charge ) ? (float) $r->delivery_charge : 0.0;

				// ── What is actually owed, asked of the PLUGIN (spare parts 0.36.0) ──
				//
				// ⚠️ Never recompute this here. The plugin owns two rules the app kept
				// getting wrong by restating them:
				//   • a part marked Unavailable or Not-going-ahead is NOT billed, so a
				//     request where one of three parts fell through must not charge for
				//     three;
				//   • once every priced part is with the courier or delivered, online
				//     payment must STOP — on cash on delivery the courier is collecting
				//     the money, and a Pay button there asks for it twice.
				//
				// `quote_total` is a stored column that only refreshes when an admin
				// saves the request, so it can lag behind a part marked unavailable.
				// payable_state() reads the lines.
				$pay_state = method_exists( 'AUN_SP_Requests', 'payable_state' )
					? AUN_SP_Requests::payable_state( (int) $r->id )
					: array( 'money' => (float) $r->quote_total, 'open' => 1, 'can_pay' => true );
				$owed = (float) $pay_state['money'];

				$spare[] = array(
					'ref'          => (string) $r->ref,
					// Latest photo re-sent after we asked for a clearer one. Re-uploads
					// are not tied to one part (item_id 0), so it belongs to the request.
					'resent_photo' => (string) ( $req_photos[0] ?? '' ),
					'model'        => (string) $r->model,
					// The projector this was raised against. The app needs it to
					// tell the customer "you already have a request for THIS
					// one" before they fill the form in, rather than after.
					'serial'       => (string) ( $r->source_order ?? '' ),
					'status'       => (string) $r->overall_status,
					'status_label' => (string) ( $ov[ $r->overall_status ] ?? $r->overall_status ),
					'warranty_in'  => (bool) $r->warranty_in,
					'quote_total'  => (float) $r->quote_total,
					'quote_note'   => (string) $r->quote_note,
					'created_at'   => substr( (string) $r->created_at, 0, 10 ),
					'delivery'     => $delivery,
					// What the Pay button will actually charge — the live figure from
					// the plugin, NOT the stored quote_total, which goes stale the
					// moment a part is marked unavailable.
					'payable'      => round( $owed + $delivery, 2 ),
					// ── The quote deadline (spare parts 0.31.0) ──
					//
					// An unanswered quote now lapses, and the customer is told
					// so by SMS. The app must show the SAME deadline next to
					// the Approve button, or the one place they can answer in
					// a single tap is the one place that never mentions the
					// clock. Sent as an ISO date and formatted per locale on
					// the phone; `days_left` is computed HERE because the
					// server owns the deadline and a phone with a wrong clock
					// must not be able to move it.
					//
					// null (not 0) when there is no deadline: quotes never
					// expiring is a supported setting, and 0 would render
					// "expires today" on a quote that never will.
					'expires'      => isset( $r->quote_expires_at ) && ! empty( $r->quote_expires_at )
						&& 'quote_sent' === $r->overall_status
						? substr( (string) $r->quote_expires_at, 0, 10 ) : '',
					'days_left'    => isset( $r->quote_expires_at ) && ! empty( $r->quote_expires_at )
						&& 'quote_sent' === $r->overall_status
						? max( 0, (int) ceil( ( strtotime( (string) $r->quote_expires_at ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS ) )
						: null,
					// Expired means they never answered — NOT that they said
					// no. The difference is the whole reason the status exists,
					// and it is why the app offers "I still want this part"
					// rather than treating the request as closed.
					'expired'      => ( 'expired' === $r->overall_status ),
					// Spare parts 0.32.0 lets a DECLINED quote be revived too:
					// Decline is a single tap on a phone and a mis-tap is
					// otherwise silent and unrecoverable. Same endpoint, and
					// the plugin's own claim decides — so the app never has to
					// know which statuses qualify.
					'can_revive'   => ( in_array( $r->overall_status, array( 'expired', 'declined' ), true )
						&& method_exists( 'AUN_SP_Requests', 'revive_quote' ) ),
					// Whether Home should still be showing this at all.
					//
					// Decided HERE rather than in the app, which used to sniff
					// the human status LABEL for words like "cancel" — a rule
					// that missed 'declined' and 'expired' entirely (neither
					// label contains any of the words it looked for), so those
					// sat on the customer's Home strip for ever.
					//
					// Expired and declined stay for a WINDOW rather than
					// vanishing: both are recoverable in one tap, and the days
					// right after are exactly when someone realises they still
					// want the part. After that they are old news and Home
					// belongs to what is live.
					'is_active'    => self::parts_is_active( $r ),
					// Live order + payment state, or null when they have not
					// chosen to pay online — cash on delivery creates no order.
					'payment'      => self::payment_summary( (int) $r->id ),
					// Whether to OFFER online payment at all.
					//
					// Deliberately STRICTER than the website tracker, which lets
					// someone pay straight from a quote (it is reached from an
					// SMS link, where paying IS the answer). In the app the
					// customer is looking at Approve and Decline buttons, and
					// putting a Pay button beside them asks the same question
					// twice with three answers. The flow is: decide first, then
					// pay if you feel like it — cash on delivery otherwise.
					//
					// So 'quote_sent' is excluded here as well as the closed
					// statuses. Approving flips this to true on the next load.
					//
					// 'expired' is excluded for a stronger reason than the
					// others: that price is no longer promised. Taking money
					// against a lapsed quote would commit us to a figure we
					// deliberately let go stale.
					// ⚠️ The plugin's own gate FIRST, then the app's extra strictness.
					//
					// The plugin's payable_state() is what the pay endpoint enforces, so
					// disagreeing with it here only ever produces a button that fails.
					// It closes two holes the app had on its own: a COMPLETED request
					// still offered Pay online (on cash on delivery the customer had
					// already paid the courier — this invited a SECOND payment), and so
					// did one whose parts were all already dispatched.
					'can_pay'      => (
						(bool) $pay_state['can_pay']
						&& ( $owed + $delivery ) > 0
						&& class_exists( 'AUN_SP_Woo' ) && AUN_SP_Woo::is_active()
						&& ! in_array(
							$r->overall_status,
							array( 'quote_sent', 'rejected', 'declined', 'expired' ),
							true
						)
					),
					'timeline'     => $timeline,
					'items'        => array_map( function ( $i ) use ( $ist, $req_photos ) {
						// `price` is PER PIECE (the website quotes it that way),
						// so the app is also given the line total — otherwise a
						// customer ordering 3 sees one piece's price next to a
						// quote total three times larger.
						$qty  = max( 1, (int) ( $i->qty ?? 1 ) );
						$unit = null !== $i->unit_price ? (float) $i->unit_price : null;
						// Unavailable / not-going-ahead lines keep their price in the
						// history but are not billed. The app must SHOW them (the
						// customer asked for that part and deserves to know what
						// happened to it) while making clear it costs nothing —
						// silently dropping the row reads as us losing the request.
						$chargeable = method_exists( 'AUN_SP_Requests', 'is_chargeable_line' )
							? (bool) AUN_SP_Requests::is_chargeable_line( $i->line_status )
							: true;
						return array(
							'label'        => (string) $i->part_label,
							'qty'          => $qty,
							'chargeable'   => $chargeable,
							'status'       => (string) $i->line_status,
							'status_label' => (string) ( $ist[ $i->line_status ] ?? $i->line_status ),
							'eta'          => (string) ( $i->eta && '0000-00-00' !== $i->eta ? $i->eta : '' ),
							'price'        => $unit,
							'line_total'   => null !== $unit ? round( $unit * $qty, 2 ) : null,
							'tracking'     => (string) $i->tracking_no,
							// The courier page for this consignment, so the number in
							// the app is tappable exactly like the website's link.
							'tracking_url' => self::courier_tracking_url( (string) $i->tracking_no ),
							// The photo the customer sent for this part ('' if none).
							'photo'        => (string) ( $req_photos[ (int) $i->id ] ?? '' ),
						);
					}, (array) $items ),
				);
			}
		}

		$repairs = array();
		$table   = self::repairs_table();
		$rows    = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE user_id = %d OR phone = %s ORDER BY created_at DESC LIMIT 20",
			(int) $user_id,
			(string) $canonical
		) );
		$linked_jobs = array();
		foreach ( (array) $rows as $r ) {
			// $fetch_final=false: finished rows show their stored state without
			// hitting the ERP on every list view.
			$erp = self::repair_erp_sync( $r, $canonical, false );
			if ( '' !== (string) $r->job_sheet_no ) {
				$linked_jobs[] = (string) $r->job_sheet_no;
			}
			$repairs[] = self::repair_entry( $r, $erp );
		}

		// Walk-in repairs: a job sheet with no app request (customer brought
		// the projector straight to the service centre) still shows up in the
		// app — found by the account phone AND by each registered device's
		// serial (the job sheet may sit under a different POS contact).
		// Job sheets already in a terminal status are past repairs, not shown.
		if ( '' !== $canonical && AUN_App_ERP::repair_configured() ) {
			$walkins = array();

			$erp = AUN_App_ERP::repair_by_phone( $canonical );
			if ( is_array( $erp ) && '' !== $erp['job_sheet_no'] ) {
				$walkins[ $erp['job_sheet_no'] ] = $erp;
			}
			if ( class_exists( 'AUN_App_Warranty' ) && AUN_App_Warranty::available() ) {
				foreach ( (array) AUN_App_Warranty::get_devices( $canonical, $user_id ) as $device ) {
					$serial = (string) ( $device['serial'] ?? '' );
					if ( '' === $serial ) {
						continue;
					}
					$erp = AUN_App_ERP::repair_by_serial( $serial );
					if ( is_array( $erp ) && '' !== $erp['job_sheet_no'] ) {
						$walkins[ $erp['job_sheet_no'] ] = $erp;
					}
				}
			}

			foreach ( $walkins as $job_no => $erp ) {
				if ( in_array( $job_no, $linked_jobs, true ) || ! empty( $erp['completed'] ) ) {
					continue;
				}
				array_unshift( $repairs, array(
					'ref'          => (string) $job_no,
					'source'       => 'erp',
					'serial'       => $erp['serial_number'],
					'model'        => $erp['device_model'],
					'issue'        => implode( ', ', $erp['problems'] ),
					'status'       => 'received',
					'note'         => '',
					'created_at'   => substr( $erp['created_at'], 0, 10 ),
					'job_sheet_no' => (string) $job_no,
					'erp_status'   => $erp['status'],
					'erp_cost'     => $erp['estimated_cost'],
				) );
			}
		}

		return array(
			'spare_parts' => $spare,
			'repairs'     => $repairs,
		);
	}

	/* --------------------------------------------------------------------- *
	 * Shared upload helper
	 * --------------------------------------------------------------------- */

	/**
	 * Store one uploaded photo under uploads/{$subdir}/Y/m. Returns URL or ''.
	 *
	 * @param array  $file   $_FILES entry.
	 * @param string $subdir Uploads subfolder.
	 * @return string
	 */
	/**
	 * Public image upload (avatars). Returns URL or ''.
	 *
	 * @param array  $file   $_FILES entry.
	 * @param string $subdir Uploads subfolder.
	 * @return string
	 */
	public static function store_public_upload( $file, $subdir ) {
		return self::store_upload( $file, $subdir );
	}

	/**
	 * The customer's own photos: request_id => [ item_id => url ].
	 * Key 0 holds the latest re-sent photo (re-uploads are not tied to one part).
	 *
	 * ⚠️ The SAME rule as the website tracker (AUN_SP_Tracking::customer_photos,
	 * spare parts 0.43.0), which is private there so it is restated here: only
	 * URLs that resolve to a REAL file inside uploads/aun-spare-parts/ are
	 * returned. A photo that was removed then shows no broken thumbnail, and
	 * nothing else can ever be put in front of the customer, whatever the
	 * column holds. Keeping the rule identical means the app and the website
	 * can never disagree about which photos a customer sees.
	 *
	 * @param int[] $request_ids
	 * @return array<int,array<int,string>>
	 */
	private static function customer_photos( $request_ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', (array) $request_ids ) ) );
		if ( ! $ids || ! method_exists( 'AUN_SP_Image', 'url_to_path' ) ) {
			return array();
		}
		$t    = AUN_SP_Install::table( 'attachments' );
		// Integers only (intval above), so they are safe to inline.
		$rows = $wpdb->get_results( "SELECT request_id, item_id, file_url FROM $t WHERE request_id IN (" . implode( ',', $ids ) . ') ORDER BY id ASC' );
		$dir  = DIRECTORY_SEPARATOR . 'aun-spare-parts' . DIRECTORY_SEPARATOR;
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$path = AUN_SP_Image::url_to_path( $row->file_url );
			if ( '' === $path || false === strpos( $path, $dir ) ) {
				continue;
			}
			// Oldest first, so a later photo for the same slot wins.
			$out[ (int) $row->request_id ][ (int) $row->item_id ] = esc_url_raw( $row->file_url );
		}
		return $out;
	}

	/**
	 * Size on disk of a file we just stored, from its URL (0 if unknown).
	 *
	 * Recorded as bytes_before so the spare-parts Settings page can report what
	 * compression saved on app photos too, the same as website ones.
	 */
	private static function stored_size( $url ) {
		if ( ! method_exists( 'AUN_SP_Image', 'url_to_path' ) ) {
			return 0;
		}
		$path = AUN_SP_Image::url_to_path( $url );
		return ( '' !== $path && is_file( $path ) ) ? (int) filesize( $path ) : 0;
	}

	private static function store_upload( $file, $subdir ) {
		if ( empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return '';
		}
		if ( (int) $file['size'] > 8 * 1024 * 1024 ) {
			return '';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$filter = function ( $dirs ) use ( $subdir ) {
			$sub            = '/' . $subdir . '/' . date( 'Y/m' );
			$dirs['path']   = $dirs['basedir'] . $sub;
			$dirs['url']    = $dirs['baseurl'] . $sub;
			$dirs['subdir'] = $sub;
			return $dirs;
		};

		add_filter( 'upload_dir', $filter );
		$result = wp_handle_sideload( $file, array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'webp'     => 'image/webp',
			),
		) );
		remove_filter( 'upload_dir', $filter );

		return empty( $result['error'] ) && ! empty( $result['url'] ) ? $result['url'] : '';
	}
}
