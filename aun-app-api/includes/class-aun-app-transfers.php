<?php
/**
 * Who owns a projector, and how ownership moves from one customer to another.
 *
 * The rule, the same one Apple, Samsung and Dyson use: **the warranty belongs
 * to the product; the registration belongs to a person.**
 *
 *  - The warranty is fixed ONCE — from the first purchase — and never resets.
 *    A second owner takes over whatever time is left.
 *  - Removing a device RELEASES it. The record stays, with its original date,
 *    and the next person to add it takes the remaining warranty over at once.
 *  - A device still owned by someone can only move with that owner's consent:
 *    the new owner asks, the current owner gets an SMS link (and an in-app
 *    notice if they use the app), and only their tap on Accept moves it.
 *
 * ⚠️ Loopholes this closes — each was reachable before:
 *  1. Anyone who typed an unclaimed direct purchase's serial got it (the
 *     serial is printed on the box). Now it links only to the ERP buyer's
 *     number; anyone else must ask the buyer.
 *  2. Remove-and-re-register restarted the warranty. A release keeps the
 *     original date, so a takeover never extends it.
 *  3. Nobody could take over a device whose owner simply never removed it —
 *     they needed staff. Now the owner decides, with no staff in the loop.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Transfers {

	/** A request the owner has not answered in this many days expires. */
	const TTL_DAYS = 7;

	/** After a decline, the same person may not ask again for this long. */
	const DECLINE_COOLDOWN_DAYS = 14;

	/** Requests one person may start per day (any serials). */
	const MAX_REQUESTS_PER_DAY = 3;

	/** Transfer SMS one OWNER may receive per day — so nobody can spam them. */
	const MAX_OWNER_SMS_PER_DAY = 3;

	/** Query parameter carrying the owner's secret link token. */
	const QV = 'aun_wt';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aun_app_transfers';
	}

	public static function releases_table() {
		global $wpdb;
		return $wpdb->prefix . 'aun_app_releases';
	}

	/* ===================================================================== *
	 * Ownership
	 * ===================================================================== */

	/**
	 * The current owner of a serial, from the app's and the warranty plugin's
	 * own records (no ERP call).
	 *
	 * kind: direct | registration | released_registration | released_direct | none | invalid
	 *
	 * @param string $serial Raw serial.
	 * @return array{kind:string,serial:string,phone:string,user_id:int,row:object|null}
	 */
	public static function owner_of( $serial ) {
		global $wpdb;
		$serial = AUN_App_Warranty::sanitize_serial( $serial );
		$none   = array( 'kind' => 'none', 'serial' => $serial, 'phone' => '', 'user_id' => 0, 'row' => null );
		if ( '' === $serial ) {
			$none['kind'] = 'invalid';
			return $none;
		}

		$dev = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . aun_app_api_devices_table() . ' WHERE serial = %s', $serial ) );
		if ( $dev ) {
			return array(
				'kind'    => 'direct',
				'serial'  => $serial,
				'phone'   => self::canon( $dev->phone ),
				'user_id' => (int) $dev->user_id,
				'row'     => $dev,
			);
		}

		if ( AUN_App_Warranty::available() ) {
			$reg = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AUN_App_Warranty::t_regs() . ' WHERE serial = %s', $serial ) );
			if ( $reg ) {
				return array(
					'kind'    => 'released' === $reg->status ? 'released_registration' : 'registration',
					'serial'  => $serial,
					'phone'   => self::canon( $reg->phone ),
					'user_id' => 0,
					'row'     => $reg,
				);
			}
		}

		$rel = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::releases_table() . ' WHERE serial = %s', $serial ) );
		if ( $rel ) {
			return array( 'kind' => 'released_direct', 'serial' => $serial, 'phone' => '', 'user_id' => 0, 'row' => $rel );
		}

		return $none;
	}

	/**
	 * The owner a transfer request would go to — or why none can be made.
	 *
	 * @param string $serial Serial.
	 * @return array|WP_Error owner_of() shape (plus 'sale' for erp_direct), or an error.
	 */
	public static function transferable_owner( $serial ) {
		$o = self::owner_of( $serial );

		switch ( $o['kind'] ) {
			case 'invalid':
				return new WP_Error( 'invalid_serial', 'Please enter a valid serial number.' );

			case 'direct':
				return $o;

			case 'registration':
				$st = (string) $o['row']->status;
				if ( 'approved' === $st ) {
					return $o;
				}
				if ( 'rejected' === $st ) {
					// Staff turned that claim down — it owns nothing. The new
					// person registers instead (their submission goes to review).
					return new WP_Error( 'not_registered', 'This projector is not registered to anyone yet. You can register it.' );
				}
				return new WP_Error( 'being_verified', 'Someone has already submitted this projector and it is being verified. Please contact AUN support.' );

			case 'released_registration':
			case 'released_direct':
				return new WP_Error( 'released', 'This projector has been released by its previous owner — you can add it straight away.' );
		}

		// Nobody holds it in our records: an unclaimed direct purchase belongs
		// to the ERP buyer; dealer stock belongs to nobody until registered.
		$sale = AUN_App_ERP::configured() ? AUN_App_ERP::lookup_serial( $o['serial'] ) : false;
		if ( is_array( $sale ) && empty( $sale['returned'] ) && ! AUN_App_ERP::is_dealer_sale( $sale ) ) {
			$buyer = self::canon( $sale['contact_mobile'] ?? '' );
			if ( '' === $buyer ) {
				return new WP_Error( 'no_contact', 'We cannot reach the buyer of this projector. Please contact AUN support.' );
			}
			return array( 'kind' => 'erp_direct', 'serial' => $o['serial'], 'phone' => $buyer, 'user_id' => 0, 'row' => null, 'sale' => $sale );
		}

		return new WP_Error( 'not_registered', 'This projector is not registered to anyone yet. You can register it.' );
	}

	/* ===================================================================== *
	 * Release (remove) and take-over
	 * ===================================================================== */

	/**
	 * Remember a released DIRECT purchase, with its original warranty data, so
	 * the next person to add it takes over the remainder without anyone's
	 * approval — the owner already let it go.
	 *
	 * @param object $row aun_app_devices row being removed.
	 */
	public static function release_direct( $row ) {
		global $wpdb;
		$wpdb->replace( self::releases_table(), array(
			'serial'            => (string) $row->serial,
			'from_user_id'      => (int) $row->user_id,
			'from_phone'        => self::canon( $row->phone ),
			'model'             => (string) ( $row->model ?? '' ),
			'purchase_date'     => ! empty( $row->purchase_date ) && '0000-00-00' !== $row->purchase_date ? $row->purchase_date : null,
			'invoice_no'        => (string) ( $row->invoice_no ?? '' ),
			'erp_contact_id'    => (int) ( $row->erp_contact_id ?? 0 ),
			'erp_product_id'    => (int) ( $row->erp_product_id ?? 0 ),
			'warranty_duration' => isset( $row->warranty_duration ) && null !== $row->warranty_duration ? (int) $row->warranty_duration : null,
			'warranty_unit'     => (string) ( $row->warranty_unit ?? '' ),
			'released_at'       => current_time( 'mysql' ),
		) );
		self::cancel_pending( (string) $row->serial, 'owner released the projector' );
	}

	/** Forget a release marker (the serial has a new owner). */
	public static function clear_release( $serial ) {
		global $wpdb;
		$wpdb->delete( self::releases_table(), array( 'serial' => (string) $serial ) );
	}

	/**
	 * Take over a released projector. The warranty carries on from the
	 * ORIGINAL purchase date; nothing about it is reset.
	 *
	 * @param string $serial Serial.
	 * @param array  $me     identity(): user_id, phone (canonical), customer_name, email.
	 * @return array|WP_Error {device}
	 */
	public static function claim_released( $serial, $me ) {
		global $wpdb;
		$o = self::owner_of( $serial );

		if ( 'released_registration' === $o['kind'] ) {
			$reg  = $o['row'];
			$note = sprintf(
				' | Taken over on %s by %s (%s) after the previous owner %s released it. Warranty continues from %s.',
				current_time( 'Y-m-d' ),
				$me['phone'],
				(int) $me['user_id'] ? 'app user #' . (int) $me['user_id'] : 'website form',
				(string) $reg->phone,
				(string) $reg->purchase_date
			);
			// Conditional on still being released: two people tapping at once
			// cannot both win.
			$ok = $wpdb->query( $wpdb->prepare(
				'UPDATE ' . AUN_App_Warranty::t_regs() . "
				 SET phone = %s, customer_name = %s, email = %s, status = 'approved', notes = CONCAT(COALESCE(notes,''), %s)
				 WHERE id = %d AND status = 'released'",
				$me['phone'],
				(string) $me['customer_name'],
				(string) $me['email'],
				$note,
				(int) $reg->id
			) );
			if ( 1 !== (int) $ok ) {
				return new WP_Error( 'not_released', 'Someone else has just taken this projector over.' );
			}
			$wpdb->update( AUN_App_Warranty::t_serials(), array( 'registered' => 1, 'registration_id' => (int) $reg->id ), array( 'serial' => (string) $reg->serial ) );
			$fresh = $wpdb->get_row( $wpdb->prepare(
				'SELECT r.*, d.name AS distributor_name FROM ' . AUN_App_Warranty::t_regs() . ' r LEFT JOIN ' . AUN_App_Warranty::t_dist() . ' d ON r.distributor_id = d.id WHERE r.id = %d',
				(int) $reg->id
			) );
			return array( 'device' => AUN_App_Warranty::device_payload( $fresh ) );
		}

		if ( 'released_direct' === $o['kind'] ) {
			$rel  = $o['row'];
			$link = AUN_App_Warranty::link_direct( array(
				'user_id'           => (int) $me['user_id'],
				'phone'             => $me['phone'],
				'serial'            => (string) $rel->serial,
				'model'             => (string) $rel->model,
				'purchase_date'     => (string) $rel->purchase_date,
				'invoice_no'        => (string) $rel->invoice_no,
				'erp_contact_id'    => (int) $rel->erp_contact_id,
				'erp_product_id'    => (int) $rel->erp_product_id,
				'warranty_duration' => null !== $rel->warranty_duration ? (int) $rel->warranty_duration : null,
				'warranty_unit'     => (string) $rel->warranty_unit,
			) );
			if ( empty( $link['ok'] ) ) {
				return new WP_Error( 'not_released', 'Someone else has just taken this projector over.' );
			}
			return array( 'device' => $link['device'] );
		}

		return new WP_Error( 'not_released', 'This projector has not been released by its owner.' );
	}

	/**
	 * Take over a released projector from the WEBSITE form, where there is no
	 * app account — only the number typed on the form. Same rule as the app:
	 * the warranty carries on from the ORIGINAL purchase date.
	 *
	 * @return array|WP_Error {warranty_end}
	 */
	public static function claim_released_for_web( $serial, $phone, $name, $email ) {
		$canon = self::canon( $phone );
		if ( '' === $canon ) {
			return new WP_Error( 'no_phone', 'A valid mobile number is needed.' );
		}
		$o = self::owner_of( $serial );

		if ( 'released_registration' === $o['kind'] ) {
			$res = self::claim_released( $serial, array( 'user_id' => 0, 'phone' => $canon, 'customer_name' => (string) $name, 'email' => (string) $email ) );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			return array( 'warranty_end' => self::warranty_end_for( $o['serial'], (string) $o['row']->purchase_date ) );
		}

		if ( 'released_direct' === $o['kind'] ) {
			$rel  = $o['row'];
			$made = self::registration_for(
				$canon,
				(object) array( 'serial' => $o['serial'], 'to_name' => (string) $name, 'to_email' => (string) $email ),
				(string) $rel->purchase_date,
				(string) $rel->invoice_no,
				(string) $rel->model,
				sprintf( 'Taken over via the website on %s by %s after the previous owner released it. Warranty continues from %s.', current_time( 'Y-m-d' ), $canon, (string) $rel->purchase_date )
			);
			if ( is_wp_error( $made ) ) {
				return $made;
			}
			self::clear_release( $o['serial'] );
			return array( 'warranty_end' => self::direct_end( $o['serial'], (string) $rel->purchase_date, $rel->warranty_duration, (string) $rel->warranty_unit ) );
		}

		return new WP_Error( 'not_released', 'This projector has not been released by its owner.' );
	}

	/** Warranty end for a serial counted from a given start date (ERP duration). */
	public static function warranty_end_for( $serial, $start ) {
		return self::direct_end( (string) $serial, (string) $start, null, '' );
	}

	/* ===================================================================== *
	 * Requests
	 * ===================================================================== */

	/**
	 * Ask the current owner to hand a projector over.
	 *
	 * @param string $serial Serial.
	 * @param array  $req    {user_id (0 for the website), phone (canonical), name, email, source 'app'|'web'}
	 * @return array|WP_Error Request payload (outgoing view).
	 */
	public static function request( $serial, $req ) {
		global $wpdb;
		$t   = self::table();
		$me  = self::canon( $req['phone'] ?? '' );
		if ( '' === $me ) {
			return new WP_Error( 'no_phone', 'A valid mobile number is needed to ask for a transfer.' );
		}

		$o = self::transferable_owner( $serial );
		if ( is_wp_error( $o ) ) {
			return $o;
		}
		if ( self::same_phone( $me, $o['phone'] ) || ( $o['user_id'] && (int) $o['user_id'] === (int) ( $req['user_id'] ?? 0 ) ) ) {
			return new WP_Error( 'already_yours', 'This projector is already registered to you.' );
		}

		self::expire_stale();

		// One live request per projector. The same person asking again gets
		// their existing request back — never a second SMS to the owner.
		$live = self::live_for_serial( $o['serial'] );
		if ( $live ) {
			if ( self::same_phone( $me, $live->to_phone ) ) {
				return self::payload( $live, 'outgoing' );
			}
			return new WP_Error( 'transfer_in_progress', 'Someone else has already asked for this projector. Please try again later.' );
		}

		$declined = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $t WHERE serial = %s AND to_phone = %s AND status = 'declined' AND decided_at > %s",
			$o['serial'],
			$me,
			wp_date( 'Y-m-d H:i:s', time() - self::DECLINE_COOLDOWN_DAYS * DAY_IN_SECONDS )
		) );
		if ( $declined ) {
			return new WP_Error( 'recently_declined', 'The owner declined your earlier request. You can ask again after 14 days.' );
		}

		$today = wp_date( 'Y-m-d 00:00:00' );
		if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE to_phone = %s AND created_at >= %s", $me, $today ) ) >= self::MAX_REQUESTS_PER_DAY
			|| (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE from_phone = %s AND created_at >= %s", $o['phone'], $today ) ) >= self::MAX_OWNER_SMS_PER_DAY ) {
			return new WP_Error( 'rate_limited', 'Too many transfer requests today. Please try again tomorrow.' );
		}

		$token = bin2hex( random_bytes( 24 ) );
		$model = self::model_of( $o );
		$ok    = $wpdb->insert( $t, array(
			'serial'       => $o['serial'],
			'kind'         => $o['kind'],
			'ref_id'       => $o['row'] ? (int) $o['row']->id : 0,
			'from_phone'   => $o['phone'],
			'from_user_id' => (int) $o['user_id'],
			'to_phone'     => $me,
			'to_user_id'   => (int) ( $req['user_id'] ?? 0 ),
			// Cleaned at the door: this name goes into an SMS sent under AUN's
			// sender ID, into the owner's page and into the app (see safe_name).
			'to_name'      => self::safe_name( (string) ( $req['name'] ?? '' ) ),
			'to_email'     => sanitize_email( (string) ( $req['email'] ?? '' ) ),
			'model'        => substr( $model, 0, 191 ),
			'token_hash'   => self::hash_token( $token ),
			'status'       => 'pending',
			'source'       => 'web' === ( $req['source'] ?? '' ) ? 'web' : 'app',
			'created_at'   => current_time( 'mysql' ),
			'expires_at'   => wp_date( 'Y-m-d H:i:s', time() + self::TTL_DAYS * DAY_IN_SECONDS ),
		) );
		if ( ! $ok ) {
			return new WP_Error( 'db_error', 'Could not create the request. Please try again.' );
		}
		$row = self::get( (int) $wpdb->insert_id );

		// Tell the owner — SMS always (they may not use the app), plus the app.
		$who = ( '' !== $row->to_name ? $row->to_name . ' ' : '' ) . '(' . AUN_App_Phone::mask( $me ) . ')';
		$sent = self::sms( $row->from_phone, sprintf(
			'AUN: %s asked to take over your projector %s (serial ending %s). Accept or decline here: %s - ignore if you do not know them. Expires in %d days.',
			$who,
			$model,
			substr( $row->serial, -4 ),
			self::link( $token ),
			self::TTL_DAYS
		) );
		// ⚠️ An owner nobody can reach is not "asked". The SMS failed and they
		// have no app account to see it in — recording the request anyway left
		// the requester waiting seven days for an answer that could never come.
		$owner_apps = self::owner_user_ids( $row );
		if ( ! $sent && empty( $owner_apps ) ) {
			$wpdb->update( $t, array( 'status' => 'failed', 'note' => 'could not reach the owner by SMS' ), array( 'id' => (int) $row->id ) );
			return new WP_Error( 'owner_unreachable', 'We could not reach the owner just now. Please try again later.' );
		}
		self::notify_app( $owner_apps, $row, 'owner_asked', $who );

		return self::payload( $row, 'outgoing' );
	}

	/** The requester withdraws their own pending request. */
	public static function cancel( $id, $me ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row || ! self::same_phone( $me['phone'], $row->to_phone ) ) {
			return new WP_Error( 'not_found', 'Request not found.' );
		}
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table() . " SET status = 'cancelled', decided_at = %s, decided_via = 'requester' WHERE id = %d AND status = 'pending'",
			current_time( 'mysql' ),
			(int) $row->id
		) );
		return self::payload( self::get( $id ), 'outgoing' );
	}

	/* ===================================================================== *
	 * Decisions
	 * ===================================================================== */

	/** The owner decides from the app. */
	public static function decide_by_owner( $id, $me, $decision ) {
		$row = self::get( $id );
		if ( ! $row
			|| ! ( self::same_phone( $me['phone'], $row->from_phone )
				|| ( (int) $row->from_user_id > 0 && (int) $row->from_user_id === (int) $me['user_id'] ) ) ) {
			return new WP_Error( 'not_found', 'Request not found.' );
		}
		return self::decide( $row, $decision, 'app' );
	}

	/** The owner decides from the SMS link. */
	public static function decide_by_token( $token, $decision ) {
		$row = self::find_by_token( $token );
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'This link is not valid.' );
		}
		return self::decide( $row, $decision, 'web' );
	}

	/**
	 * Accept or decline — the only place ownership moves.
	 *
	 * ⚠️ The status is claimed with a CONDITIONAL update (pending -> accepted)
	 * before anything moves, so a double tap, the app and the SMS link used at
	 * once, or two browser tabs can never run the move twice. Then the owner is
	 * checked AGAIN: if the projector changed hands since the request was made,
	 * this request is void and nothing moves.
	 */
	private static function decide( $row, $decision, $via ) {
		global $wpdb;
		$t = self::table();

		if ( 'pending' !== $row->status ) {
			return new WP_Error( 'already_decided', 'This request has already been answered.', array( 'status_now' => $row->status ) );
		}
		if ( strtotime( $row->expires_at ) < current_time( 'timestamp' ) ) {
			self::expire_stale();
			return new WP_Error( 'expired', 'This request has expired.' );
		}

		$new = 'accept' === $decision ? 'accepted' : 'declined';
		$won = $wpdb->query( $wpdb->prepare(
			"UPDATE $t SET status = %s, decided_at = %s, decided_via = %s WHERE id = %d AND status = 'pending'",
			$new,
			current_time( 'mysql' ),
			$via,
			(int) $row->id
		) );
		if ( 1 !== (int) $won ) {
			$now = self::get( (int) $row->id );
			return new WP_Error( 'already_decided', 'This request has already been answered.', array( 'status_now' => $now ? $now->status : '' ) );
		}

		if ( 'declined' === $new ) {
			self::sms( $row->to_phone, sprintf( 'AUN: The owner declined your request for projector %s (serial ending %s).', $row->model, substr( $row->serial, -4 ) ) );
			self::notify_app( self::requester_user_ids( $row ), $row, 'requester_declined' );
			return self::payload( self::get( (int) $row->id ), 'incoming' );
		}

		// ⚠️ For a purchase nobody has claimed yet, the owner check below asks the
		// ERP. If the ERP cannot be reached at this moment, that is NOT a change
		// of owner: put the request back to pending so the owner can simply try
		// again. Voiding it would make them start over for a network blip.
		if ( 'erp_direct' === $row->kind && AUN_App_ERP::configured() && is_wp_error( AUN_App_ERP::lookup_serial( $row->serial ) ) ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'pending', decided_at = NULL, decided_via = '' WHERE id = %d", (int) $row->id ) );
			return new WP_Error( 'try_again', 'We could not confirm the owner just now. Please try again in a few minutes.' );
		}

		// Re-check the owner right now.
		$o = self::transferable_owner( $row->serial );
		if ( is_wp_error( $o ) || ! self::same_phone( $o['phone'], $row->from_phone ) || $o['kind'] !== $row->kind ) {
			$wpdb->update( $t, array( 'status' => 'cancelled', 'note' => 'owner changed before the request was accepted' ), array( 'id' => (int) $row->id ) );
			return new WP_Error( 'owner_changed', 'This projector has changed hands since the request was made, so nothing was moved.' );
		}

		$moved = self::move( $row, $o );
		if ( is_wp_error( $moved ) ) {
			$wpdb->update( $t, array( 'status' => 'failed', 'note' => substr( $moved->get_error_message(), 0, 250 ) ), array( 'id' => (int) $row->id ) );
			return $moved;
		}

		$end = (string) ( $moved['warranty_end'] ?? '' );
		self::sms( $row->to_phone, sprintf(
			'AUN: The owner accepted. Projector %s (serial ending %s) is now yours%s.',
			$row->model,
			substr( $row->serial, -4 ),
			'' !== $end ? ' - warranty until ' . $end : ''
		) );
		self::sms( $row->from_phone, sprintf(
			'AUN: Projector %s (serial ending %s) has been transferred to %s. If this was not you, contact AUN support.',
			$row->model,
			substr( $row->serial, -4 ),
			AUN_App_Phone::mask( $row->to_phone )
		) );
		self::notify_app( self::requester_user_ids( $row ), $row, 'requester_accepted', '', $end );

		return self::payload( self::get( (int) $row->id ), 'incoming' );
	}

	/**
	 * Move the projector to the requester. The purchase date is never touched:
	 * the new owner takes over exactly the warranty that was left.
	 *
	 * @return array|WP_Error {warranty_end}
	 */
	private static function move( $row, $o ) {
		global $wpdb;
		$to_phone = (string) $row->to_phone;
		$to_user  = (int) $row->to_user_id;
		if ( ! $to_user ) {
			// A website requester who also has an app account on that number.
			$found   = AUN_App_Phone::find_users( $to_phone );
			$to_user = $found ? (int) $found[0]->ID : 0;
		}
		$note = sprintf( ' | Transferred on %s from %s to %s — approved by the owner (request #%d).', current_time( 'Y-m-d' ), $row->from_phone, $to_phone, (int) $row->id );

		foreach ( self::owner_user_ids( $row ) as $uid ) {
			if ( class_exists( 'AUN_App_Notices' ) ) {
				AUN_App_Notices::delete_for_serial( (int) $uid, (string) $row->serial );
			}
		}

		switch ( $o['kind'] ) {
			case 'registration':
				$ok = $wpdb->query( $wpdb->prepare(
					'UPDATE ' . AUN_App_Warranty::t_regs() . " SET phone = %s, customer_name = %s, email = %s, notes = CONCAT(COALESCE(notes,''), %s)
					 WHERE id = %d AND status = 'approved'",
					$to_phone,
					(string) $row->to_name,
					(string) $row->to_email,
					$note,
					(int) $o['row']->id
				) );
				if ( 1 !== (int) $ok ) {
					return new WP_Error( 'move_failed', 'Could not move the registration.' );
				}
				$reg = $o['row'];
				list( $wd, $wu ) = AUN_App_Warranty::erp_warranty_for_serial( (string) $reg->serial );
				return array( 'warranty_end' => AUN_App_Warranty::warranty_from( (string) $reg->purchase_date, $wd, $wu )['end'] ?? '' );

			case 'direct':
				$dev = $o['row'];
				if ( $to_user ) {
					$ok = $wpdb->query( $wpdb->prepare(
						'UPDATE ' . aun_app_api_devices_table() . ' SET user_id = %d, phone = %s WHERE id = %d AND user_id = %d',
						$to_user,
						$to_phone,
						(int) $dev->id,
						(int) $dev->user_id
					) );
					if ( 1 !== (int) $ok ) {
						return new WP_Error( 'move_failed', 'Could not move the device.' );
					}
				} else {
					$made = self::registration_for( $to_phone, $row, (string) $dev->purchase_date, (string) $dev->invoice_no, (string) $dev->model, $note );
					if ( is_wp_error( $made ) ) {
						return $made;
					}
					$wpdb->delete( aun_app_api_devices_table(), array( 'id' => (int) $dev->id ) );
				}
				// The old owner's login sync must never pull it back.
				AUN_App_Warranty::record_dismissal( (int) $dev->user_id, (string) $dev->serial, (string) $dev->invoice_no, (string) $dev->purchase_date );
				return array( 'warranty_end' => self::direct_end( $dev->serial, (string) $dev->purchase_date, $dev->warranty_duration ?? null, (string) ( $dev->warranty_unit ?? '' ) ) );

			case 'erp_direct':
				$sale = $o['sale'];
				if ( $to_user ) {
					$link = AUN_App_Warranty::link_direct( array(
						'user_id'           => $to_user,
						'phone'             => $to_phone,
						'serial'            => (string) $row->serial,
						'model'             => (string) $row->model,
						'purchase_date'     => (string) $sale['sale_date'],
						'invoice_no'        => (string) $sale['invoice_no'],
						'erp_contact_id'    => (int) $sale['contact_id'],
						'erp_product_id'    => (int) $sale['product_id'],
						'warranty_duration' => $sale['warranty_duration'] ?? null,
						'warranty_unit'     => (string) ( $sale['warranty_unit'] ?? '' ),
					) );
					if ( empty( $link['ok'] ) ) {
						return new WP_Error( 'move_failed', 'Could not link the projector.' );
					}
				} else {
					$made = self::registration_for( $to_phone, $row, (string) $sale['sale_date'], (string) $sale['invoice_no'], (string) $row->model, $note );
					if ( is_wp_error( $made ) ) {
						return $made;
					}
				}
				foreach ( AUN_App_Phone::find_users( $row->from_phone ) as $u ) {
					AUN_App_Warranty::record_dismissal( (int) $u->ID, (string) $row->serial, (string) $sale['invoice_no'], (string) $sale['sale_date'] );
				}
				return array( 'warranty_end' => self::direct_end( $row->serial, (string) $sale['sale_date'], $sale['warranty_duration'] ?? null, (string) ( $sale['warranty_unit'] ?? '' ) ) );
		}

		return new WP_Error( 'move_failed', 'This projector cannot be transferred.' );
	}

	/**
	 * A direct purchase handed to someone with no app account becomes an
	 * approved registration on their number — so they see it the moment they
	 * sign in with that number, and the website knows it too.
	 */
	private static function registration_for( $to_phone, $row, $pdate, $invoice, $model, $note ) {
		global $wpdb;
		$ok = $wpdb->insert( AUN_App_Warranty::t_regs(), array(
			'serial'        => (string) $row->serial,
			'customer_name' => (string) $row->to_name,
			'phone'         => $to_phone,
			'email'         => (string) $row->to_email,
			'product_model' => $model,
			'invoice_no'    => $invoice,
			'purchase_date' => $pdate ? $pdate : null,
			'dealer_name'   => 'AUN (direct sale)',
			'invoice_file'  => '',
			'product_photo' => '',
			'status'        => 'approved',
			'notes'         => ltrim( $note, ' |' ),
		) );
		return $ok ? true : new WP_Error( 'move_failed', 'Could not record the new owner.' );
	}

	/* ===================================================================== *
	 * Expiry
	 * ===================================================================== */

	/** Mark overdue requests expired and tell their requesters. */
	public static function expire_stale() {
		global $wpdb;
		$t    = self::table();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $t WHERE status = 'pending' AND expires_at < %s LIMIT 50",
			current_time( 'mysql' )
		) );
		foreach ( (array) $rows as $row ) {
			$won = $wpdb->query( $wpdb->prepare( "UPDATE $t SET status = 'expired', decided_at = %s WHERE id = %d AND status = 'pending'", current_time( 'mysql' ), (int) $row->id ) );
			if ( 1 === (int) $won ) {
				self::sms( $row->to_phone, sprintf( 'AUN: Your request for projector %s (serial ending %s) expired - the owner did not answer.', $row->model, substr( $row->serial, -4 ) ) );
				self::notify_app( self::requester_user_ids( $row ), $row, 'requester_expired' );
			}
		}
	}

	/** Hourly cron. */
	public static function expire_cron() {
		self::expire_stale();
	}

	/** Void any live request for a serial (the owner released it, etc.). */
	public static function cancel_pending( $serial, $why ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table() . " SET status = 'cancelled', decided_at = %s, note = %s WHERE serial = %s AND status = 'pending'",
			current_time( 'mysql' ),
			substr( (string) $why, 0, 250 ),
			(string) $serial
		) );
	}

	/* ===================================================================== *
	 * Reads
	 * ===================================================================== */

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) );
	}

	public static function find_by_token( $token ) {
		global $wpdb;
		$token = strtolower( preg_replace( '/[^a-fA-F0-9]/', '', (string) $token ) );
		if ( 48 !== strlen( $token ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token_hash = %s', self::hash_token( $token ) ) );
	}

	private static function live_for_serial( $serial ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . " WHERE serial = %s AND status = 'pending' AND expires_at >= %s ORDER BY id DESC LIMIT 1",
			(string) $serial,
			current_time( 'mysql' )
		) );
	}

	/**
	 * What the app should show for a serial someone else holds.
	 *
	 * @return array {can_transfer, block, block_message, my_request}
	 */
	public static function status_for( $serial, $me ) {
		$o   = self::transferable_owner( $serial );
		$out = array( 'can_transfer' => false, 'block' => '', 'block_message' => '', 'my_request' => null );
		if ( is_wp_error( $o ) ) {
			$out['block']         = $o->get_error_code();
			$out['block_message'] = $o->get_error_message();
			return $out;
		}
		if ( self::same_phone( $me['phone'], $o['phone'] )
			|| ( $o['user_id'] && (int) $o['user_id'] === (int) ( $me['user_id'] ?? 0 ) ) ) {
			return $out;
		}
		// An unclaimed direct purchase "belongs" to whoever the ERP says bought
		// it — very often the SAME customer on another of their numbers. The app
		// then offers "that's my other number" (confirm it with a code) as well
		// as asking.
		$out['owner_kind'] = 'erp_direct' === $o['kind'] ? 'erp_buyer' : 'owner';
		$live = self::live_for_serial( $o['serial'] );
		if ( $live && self::same_phone( $me['phone'], $live->to_phone ) ) {
			$out['my_request'] = self::payload( $live, 'outgoing' );
			return $out;
		}
		if ( $live ) {
			$out['block']         = 'transfer_in_progress';
			$out['block_message'] = 'Someone else has already asked for this projector. Please try again later.';
			return $out;
		}
		$out['can_transfer'] = true;
		return $out;
	}

	/**
	 * Requests involving this customer, for the app.
	 *
	 * @return array {incoming:[], outgoing:[]}
	 */
	public static function for_user( $me ) {
		global $wpdb;
		self::expire_stale();
		$t    = self::table();
		$vars = AUN_App_Phone::variants( $me['phone'] );
		$ph   = implode( ',', array_fill( 0, count( $vars ), '%s' ) );
		$since = wp_date( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		$in = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $t WHERE ( from_phone IN ($ph) OR ( from_user_id > 0 AND from_user_id = %d ) ) AND ( status = 'pending' OR created_at >= %s ) ORDER BY id DESC LIMIT 20",
			array_merge( $vars, array( (int) $me['user_id'], $since ) )
		) );
		$out = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $t WHERE to_phone IN ($ph) AND ( status = 'pending' OR created_at >= %s ) ORDER BY id DESC LIMIT 20",
			array_merge( $vars, array( $since ) )
		) );

		return array(
			'incoming' => array_map( function ( $r ) { return self::payload( $r, 'incoming' ); }, (array) $in ),
			'outgoing' => array_map( function ( $r ) { return self::payload( $r, 'outgoing' ); }, (array) $out ),
		);
	}

	/**
	 * One request, shaped for the side looking at it. Each side sees only a
	 * MASKED number for the other — never the full phone.
	 */
	public static function payload( $row, $view ) {
		if ( ! $row ) {
			return null;
		}
		$status = (string) $row->status;
		if ( 'pending' === $status && strtotime( $row->expires_at ) < current_time( 'timestamp' ) ) {
			$status = 'expired';
		}
		$out = array(
			'id'          => (int) $row->id,
			'serial_tail' => substr( (string) $row->serial, -4 ),
			'model'       => (string) $row->model,
			'status'      => $status,
			'created_at'  => (string) $row->created_at,
			'expires_at'  => (string) $row->expires_at,
			'decided_at'  => (string) ( $row->decided_at ?? '' ),
		);
		if ( 'incoming' === $view ) {
			$out['requester_name']  = (string) $row->to_name;
			$out['requester_phone'] = AUN_App_Phone::mask( (string) $row->to_phone );
		} else {
			$out['owner_phone'] = AUN_App_Phone::mask( (string) $row->from_phone );
		}
		return $out;
	}

	/* ===================================================================== *
	 * The owner's page (SMS link)
	 * ===================================================================== */

	/** Public URL the owner opens from the SMS. */
	public static function link( $token ) {
		return add_query_arg( self::QV, $token, home_url( '/' ) );
	}

	/**
	 * Render the accept/decline page and stop — hooked on template_redirect.
	 *
	 * ⚠️ Opening the link changes NOTHING. WhatsApp, Messenger and SMS apps
	 * fetch links on their own to build previews; if a visit counted, a preview
	 * bot could accept a transfer before the owner ever saw it. Only a POST from
	 * the page's own buttons acts — and Accept also needs the owner to tick
	 * that they sold or gave the projector to this person.
	 *
	 * The page is never cached (a cached copy would serve one owner's request
	 * to another) and never indexed.
	 */
	public static function maybe_render_page() {
		if ( empty( $_GET[ self::QV ] ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( 'Content-Type: text/html; charset=UTF-8', true );

		$token = (string) wp_unslash( $_GET[ self::QV ] );
		$row   = self::find_by_token( $token );
		$flash = '';

		if ( $row && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			$decision = sanitize_key( (string) wp_unslash( $_POST['decision'] ?? '' ) );
			if ( 'accept' === $decision && empty( $_POST['confirm'] ) ) {
				$flash = 'confirm';
			} elseif ( in_array( $decision, array( 'accept', 'decline' ), true ) ) {
				$res = self::decide_by_token( $token, $decision );
				if ( is_wp_error( $res ) ) {
					$flash = $res->get_error_code();
				}
				$row = self::find_by_token( $token );
			}
		}

		self::expire_stale();
		if ( $row ) {
			$row = self::get( (int) $row->id );
		}

		echo self::page_html( $row, $flash ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	private static function page_html( $row, $flash ) {
		$e  = 'esc_html';
		$st = $row ? (string) $row->status : 'invalid';
		if ( $row && 'pending' === $st && strtotime( $row->expires_at ) < current_time( 'timestamp' ) ) {
			$st = 'expired';
		}

		$h  = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		$h .= '<meta name="robots" content="noindex,nofollow"><title>AUN — Projector transfer</title>';
		$h .= '<style data-no-optimize="1" data-no-minify="1" data-cfasync="false">'
			. 'body{margin:0;background:#f3f7fb;font:16px/1.55 -apple-system,Segoe UI,Roboto,"Noto Sans Bengali",sans-serif;color:#1f2937}'
			. '.w{max-width:460px;margin:0 auto;padding:28px 18px}.c{background:#fff;border-radius:16px;padding:22px;box-shadow:0 1px 3px rgba(15,23,42,.08)}'
			. 'h1{font-size:20px;margin:0 0 6px}.bn{color:#475569;font-size:15px}.t{color:#64748b;font-size:14px;margin:14px 0 0}'
			. 'dl{margin:16px 0;display:grid;grid-template-columns:auto 1fr;gap:6px 14px}dt{color:#64748b}dd{margin:0;font-weight:600}'
			. '.warn{background:#fff7ed;color:#9a3412;border-radius:10px;padding:12px;font-size:14px;margin:14px 0}'
			. 'label{display:flex;gap:10px;align-items:flex-start;font-size:14px;margin:12px 0}input[type=checkbox]{margin-top:4px;width:18px;height:18px}'
			. 'button{width:100%;border:0;border-radius:12px;padding:14px;font-size:16px;font-weight:700;cursor:pointer;margin-top:10px}'
			. '.a{background:#0188fe;color:#fff}.d{background:#eef2f7;color:#334155}.ok{color:#166534}.no{color:#991b1b}'
			. '.logo{font-weight:800;color:#0188fe;letter-spacing:.5px;margin-bottom:14px}'
			. '</style></head><body><div class="w"><div class="logo">AUN</div><div class="c">';

		$done = array(
			'accepted'  => array( 'ok', 'Transfer complete', 'হস্তান্তর সম্পন্ন হয়েছে', 'The projector has left your account. Its remaining warranty went with it.', 'প্রজেক্টরটি আপনার অ্যাকাউন্ট থেকে সরানো হয়েছে। বাকি ওয়ারেন্টিও সাথে গেছে।' ),
			'declined'  => array( 'no', 'You declined', 'আপনি প্রত্যাখ্যান করেছেন', 'Nothing has changed. The projector is still yours.', 'কিছুই পরিবর্তন হয়নি। প্রজেক্টরটি এখনও আপনার।' ),
			'expired'   => array( 'no', 'This request has expired', 'অনুরোধের মেয়াদ শেষ', 'Nothing has changed. The projector is still yours.', 'কিছুই পরিবর্তন হয়নি। প্রজেক্টরটি এখনও আপনার।' ),
			'cancelled' => array( 'no', 'This request was withdrawn', 'অনুরোধটি বাতিল করা হয়েছে', 'Nothing has changed.', 'কিছুই পরিবর্তন হয়নি।' ),
			'failed'    => array( 'no', 'The transfer could not be completed', 'হস্তান্তর সম্পন্ন করা যায়নি', 'Nothing has changed. Please contact AUN support.', 'কিছুই পরিবর্তন হয়নি। অনুগ্রহ করে AUN সাপোর্টে যোগাযোগ করুন।' ),
			'invalid'   => array( 'no', 'This link is not valid', 'এই লিংকটি সঠিক নয়', 'Please use the link from the SMS exactly as it arrived.', 'অনুগ্রহ করে SMS-এ আসা লিংকটি হুবহু ব্যবহার করুন।' ),
		);

		if ( isset( $done[ $st ] ) ) {
			list( $cls, $t1, $t2, $b1, $b2 ) = $done[ $st ];
			if ( 'cancelled' === $st && $row && '' !== (string) $row->note ) {
				$b1 = 'The projector changed hands before this request was answered, so nothing was moved.';
			}
			$h .= '<h1 class="' . $cls . '">' . $e( $t1 ) . '</h1><div class="bn">' . $e( $t2 ) . '</div>';
			$h .= '<p class="t">' . $e( $b1 ) . '<br>' . $e( $b2 ) . '</p>';
		} else {
			$who = ( '' !== (string) $row->to_name ? $row->to_name . ' · ' : '' ) . AUN_App_Phone::mask( (string) $row->to_phone );
			$h  .= '<h1>Transfer request</h1><div class="bn">হস্তান্তরের অনুরোধ</div>';
			$h  .= '<p class="t">Someone has asked to take over your AUN projector.<br>কেউ আপনার AUN প্রজেক্টরটি নিজের নামে নিতে চেয়েছেন।</p>';
			$h  .= '<dl><dt>Projector</dt><dd>' . $e( $row->model ) . '</dd><dt>Serial</dt><dd>•••• ' . $e( substr( $row->serial, -4 ) ) . '</dd>'
				. '<dt>Requested by</dt><dd>' . $e( $who ) . '</dd><dt>Answer by</dt><dd>' . $e( wp_date( 'j M Y', strtotime( $row->expires_at ) ) ) . '</dd></dl>';
			$h  .= '<div class="warn">Accept only if you sold or gave this projector to this person. It will leave your account and its remaining warranty goes with it.<br>'
				. 'শুধুমাত্র এই ব্যক্তির কাছে প্রজেক্টরটি বিক্রি বা হস্তান্তর করে থাকলে গ্রহণ করুন। এটি আপনার অ্যাকাউন্ট থেকে সরে যাবে এবং বাকি ওয়ারেন্টিও সাথে যাবে।</div>';
			if ( 'confirm' === $flash ) {
				$h .= '<p class="no" style="font-size:14px">Please tick the box to confirm. · অনুগ্রহ করে বক্সে টিক দিন।</p>';
			} elseif ( '' !== $flash ) {
				$h .= '<p class="no" style="font-size:14px">Something went wrong. Please try again. · আবার চেষ্টা করুন।</p>';
			}
			$action = esc_url( self::link( (string) wp_unslash( $_GET[ self::QV ] ?? '' ) ) );
			$h     .= '<form method="post" action="' . $action . '"><input type="hidden" name="decision" value="accept">'
				. '<label><input type="checkbox" name="confirm" value="1" required><span>I sold or gave this projector to this person.<br>আমি প্রজেক্টরটি এই ব্যক্তির কাছে বিক্রি বা হস্তান্তর করেছি।</span></label>'
				. '<button class="a" type="submit">Accept — transfer it · গ্রহণ করুন</button></form>';
			$h     .= '<form method="post" action="' . $action . '"><input type="hidden" name="decision" value="decline">'
				. '<button class="d" type="submit">Decline · প্রত্যাখ্যান করুন</button></form>';
		}

		$h .= '</div><p class="t" style="text-align:center">AUN Projector Bangladesh</p></div></body></html>';
		return $h;
	}

	/* ===================================================================== *
	 * Helpers
	 * ===================================================================== */

	/**
	 * A requester's name as it may appear in an SMS from AUN's sender ID.
	 *
	 * ⚠️ The name is typed by the requester — on the website form, or in their
	 * app profile — and goes out under OUR name. Unfiltered, "Visit bit.ly/x to
	 * keep your warranty" is a perfectly valid "name", and the owner receives a
	 * phishing text signed by AUN. Only letters (any script), spaces and . ' -
	 * survive; anything that still reads like a web address is dropped; 30
	 * characters at most.
	 */
	public static function safe_name( $name ) {
		$n = preg_replace( '/[^\p{L}\p{M} .\'-]+/u', ' ', (string) $name );
		$n = trim( (string) preg_replace( '/\s+/u', ' ', (string) $n ) );
		if ( preg_match( '/(www|https?|\.(com|net|org|xyz|info|top|bd|io|me|co|ly|link)\b)/i', $n ) ) {
			return '';
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $n, 0, 30 ) : substr( $n, 0, 30 );
	}

	private static function canon( $phone ) {
		$c = AUN_App_Phone::normalize( (string) $phone );
		return $c ? $c : '';
	}

	private static function same_phone( $a, $b ) {
		$a = self::canon( $a );
		$b = self::canon( $b );
		return '' !== $a && $a === $b;
	}

	private static function hash_token( $token ) {
		return hash_hmac( 'sha256', strtolower( (string) $token ), wp_salt( 'auth' ) );
	}

	private static function model_of( $o ) {
		if ( 'erp_direct' === $o['kind'] ) {
			return (string) ( $o['sale']['product_name'] ?? '' );
		}
		if ( 'registration' === $o['kind'] ) {
			return (string) $o['row']->product_model;
		}
		return (string) ( $o['row']->model ?? '' );
	}

	private static function direct_end( $serial, $pdate, $duration, $unit ) {
		if ( null === $duration || '' === (string) $unit ) {
			list( $duration, $unit ) = AUN_App_Warranty::erp_warranty_for_serial( (string) $serial );
		}
		return (string) ( AUN_App_Warranty::warranty_from( (string) $pdate, (int) $duration, (string) $unit )['end'] ?? '' );
	}

	/** App accounts belonging to the current owner. */
	private static function owner_user_ids( $row ) {
		$ids = array();
		if ( (int) $row->from_user_id > 0 ) {
			$ids[] = (int) $row->from_user_id;
		}
		foreach ( AUN_App_Phone::find_users( (string) $row->from_phone ) as $u ) {
			$ids[] = (int) $u->ID;
		}
		return array_values( array_unique( $ids ) );
	}

	/** App accounts belonging to the requester. */
	private static function requester_user_ids( $row ) {
		$ids = array();
		if ( (int) $row->to_user_id > 0 ) {
			$ids[] = (int) $row->to_user_id;
		}
		foreach ( AUN_App_Phone::find_users( (string) $row->to_phone ) as $u ) {
			$ids[] = (int) $u->ID;
		}
		return array_values( array_unique( $ids ) );
	}

	/** Send an SMS; true when the gateway accepted it. */
	private static function sms( $to, $body ) {
		if ( '' === (string) $to || ! class_exists( 'AUN_App_SMS' ) ) {
			return false;
		}
		$r = AUN_App_SMS::send( (string) $to, (string) $body );
		return ! empty( $r['success'] );
	}

	/** In-app notice + push, bilingual. */
	private static function notify_app( $user_ids, $row, $what, $who = '', $end = '' ) {
		if ( empty( $user_ids ) || ! class_exists( 'AUN_App_Notices' ) ) {
			return;
		}
		$tail = substr( (string) $row->serial, -4 );
		$m    = (string) $row->model;
		$copy = array(
			'owner_asked'        => array(
				'Transfer request for your projector',
				'আপনার প্রজেক্টর হস্তান্তরের অনুরোধ',
				sprintf( '%s asked to take over your %s (serial ending %s). Only accept if you sold or gave it to them.', $who, $m, $tail ),
				sprintf( '%s আপনার %s (সিরিয়াল শেষে %s) নিজের নামে নিতে চেয়েছেন। বিক্রি বা হস্তান্তর করে থাকলেই গ্রহণ করুন।', $who, $m, $tail ),
			),
			'requester_accepted' => array(
				'Transfer accepted',
				'হস্তান্তর গৃহীত হয়েছে',
				sprintf( 'Your %s (serial ending %s) is now on your account%s.', $m, $tail, '' !== $end ? ' — warranty until ' . $end : '' ),
				sprintf( 'আপনার %s (সিরিয়াল শেষে %s) এখন আপনার অ্যাকাউন্টে%s।', $m, $tail, '' !== $end ? ' — ওয়ারেন্টি ' . $end . ' পর্যন্ত' : '' ),
			),
			'requester_declined' => array(
				'Transfer declined',
				'হস্তান্তর প্রত্যাখ্যাত',
				sprintf( 'The owner declined your request for the %s (serial ending %s).', $m, $tail ),
				sprintf( 'মালিক %s (সিরিয়াল শেষে %s)-এর জন্য আপনার অনুরোধ প্রত্যাখ্যান করেছেন।', $m, $tail ),
			),
			'requester_expired'  => array(
				'Transfer request expired',
				'হস্তান্তরের অনুরোধের মেয়াদ শেষ',
				sprintf( 'The owner did not answer your request for the %s (serial ending %s).', $m, $tail ),
				sprintf( 'মালিক %s (সিরিয়াল শেষে %s)-এর অনুরোধের উত্তর দেননি।', $m, $tail ),
			),
		);
		if ( ! isset( $copy[ $what ] ) ) {
			return;
		}
		list( $t, $tb, $b, $bb ) = $copy[ $what ];
		$data = array( 'transfer_id' => (int) $row->id, 'role' => 0 === strpos( $what, 'owner' ) ? 'owner' : 'requester', 'event' => $what );
		foreach ( $user_ids as $uid ) {
			AUN_App_Notices::create( array(
				'user_id'   => (int) $uid,
				'type'      => 'transfer',
				'title'     => $t,
				'title_bn'  => $tb,
				'body'      => $b,
				'body_bn'   => $bb,
				'data'      => $data,
				'dedup_key' => 'transfer:' . (int) $row->id . ':' . $what . ':' . (int) $uid,
			) );
		}
		if ( class_exists( 'AUN_App_Push' ) ) {
			AUN_App_Push::push_to_users( $user_ids, array( 'title' => $t, 'title_bn' => $tb, 'body' => $b, 'body_bn' => $bb ), array_merge( array( 'type' => 'transfer' ), array_map( 'strval', $data ) ) );
		}
	}
}
