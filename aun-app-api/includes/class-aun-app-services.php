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

	/** Same unambiguous ref alphabet the spare-parts plugin uses. */
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

		$address = sanitize_textarea_field( (string) $args['address'] );
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

		$note = sanitize_textarea_field( (string) $args['note'] );

		// Quantities, clamped to the website's own 1..max_qty range. Anything
		// missing or junk becomes 1, which is exactly the old behaviour.
		$max_qty  = self::parts_max_qty();
		$qty_in   = (array) ( $args['qty'] ?? array() );
		$qty      = array();
		foreach ( $selected as $key ) {
			$n            = isset( $qty_in[ $key ] ) ? (int) $qty_in[ $key ] : 1;
			$qty[ $key ]  = max( 1, min( $max_qty, $n ) );
		}

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
						'request_id' => $request_id,
						'item_id'    => $item_id,
						'kind'       => 'proof',
						'file_url'   => $url,
						'created_at' => $now,
					) );
				}
			}
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

		AUN_App_SMS::send(
			$phone,
			"SmartLiving: We received your spare parts request {$ref} for {$model}. We will send a price quote by SMS soon."
		);

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
	public static function create_repair( $args ) {
		global $wpdb;

		$issue = sanitize_textarea_field( (string) $args['issue'] );
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
			'address'       => sanitize_textarea_field( (string) $args['address'] ),
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

		AUN_App_SMS::send(
			(string) $args['phone'],
			"SmartLiving: We received your repair request {$ref} for {$model}. We will confirm by SMS before you send the projector. Do NOT ship it yet."
		);

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
	public static function poll_repair_statuses( $limit = 40 ) {
		global $wpdb;
		$stats = array( 'checked' => 0, 'changed' => 0, 'skipped' => 0 );
		if ( ! AUN_App_ERP::repair_configured() ) {
			return $stats;
		}

		$table    = self::repairs_table();
		$final    = self::REPAIR_FINAL;
		$final_ph = implode( ',', array_fill( 0, count( $final ), '%s' ) );
		$rows     = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table
			  WHERE job_sheet_no != '' AND status NOT IN ($final_ph)
			  ORDER BY updated_at ASC LIMIT %d",
			array_merge( $final, array( (int) $limit ) )
		) );

		foreach ( $rows as $row ) {
			$erp = AUN_App_ERP::repair_by_job_sheet( (string) $row->job_sheet_no );
			if ( ! is_array( $erp ) ) {
				$stats['skipped']++;
				continue; // ERP down / job not found — retry next run, no state change
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
			'erp_status'   => is_array( $erp ) ? (string) $erp['status'] : '',
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
			"SELECT ref, phone_current, quote_total FROM $t_req WHERE id = %d",
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
			(float) $r->quote_total
		);
	}

	/**
	 * @param string $canonical User's canonical phone.
	 * @param int    $user_id   User id (repairs are linked by id too).
	 * @return array{spare_parts:array,repairs:array}
	 */
	public static function my_requests( $canonical, $user_id ) {
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
						(float) $r->quote_total
					);
				}

				$items = $wpdb->get_results(
					$wpdb->prepare( "SELECT part_label, qty, line_status, eta, unit_price, tracking_no FROM $t_item WHERE request_id = %d", $r->id )
				);

				// Status-history timeline — same event filter as the website
				// tracker (skip raw SMS logs and contact edits).
				$events   = $wpdb->get_results( $wpdb->prepare(
					"SELECT message, created_at FROM $t_event
					 WHERE request_id = %d AND type NOT IN ('sms','contact_changed')
					 ORDER BY id ASC LIMIT 40",
					$r->id
				) );
				$timeline = array();
				foreach ( (array) $events as $e ) {
					$timeline[] = array(
						'date' => $e->created_at ? date_i18n( 'j M Y, g:i a', strtotime( $e->created_at ) ) : '',
						'text' => (string) $e->message,
					);
				}

				$spare[] = array(
					'ref'          => (string) $r->ref,
					'model'        => (string) $r->model,
					'status'       => (string) $r->overall_status,
					'status_label' => (string) ( $ov[ $r->overall_status ] ?? $r->overall_status ),
					'warranty_in'  => (bool) $r->warranty_in,
					'quote_total'  => (float) $r->quote_total,
					'quote_note'   => (string) $r->quote_note,
					'created_at'   => substr( (string) $r->created_at, 0, 10 ),
					'timeline'     => $timeline,
					'items'        => array_map( function ( $i ) use ( $ist ) {
						// `price` is PER PIECE (the website quotes it that way),
						// so the app is also given the line total — otherwise a
						// customer ordering 3 sees one piece's price next to a
						// quote total three times larger.
						$qty  = max( 1, (int) ( $i->qty ?? 1 ) );
						$unit = null !== $i->unit_price ? (float) $i->unit_price : null;
						return array(
							'label'        => (string) $i->part_label,
							'qty'          => $qty,
							'status'       => (string) $i->line_status,
							'status_label' => (string) ( $ist[ $i->line_status ] ?? $i->line_status ),
							'eta'          => (string) ( $i->eta && '0000-00-00' !== $i->eta ? $i->eta : '' ),
							'price'        => $unit,
							'line_total'   => null !== $unit ? round( $unit * $qty, 2 ) : null,
							'tracking'     => (string) $i->tracking_no,
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
