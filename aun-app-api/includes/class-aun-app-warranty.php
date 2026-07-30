<?php
/**
 * Warranty bridge — reads/writes the SLB Warranty Registration plugin's tables
 * (wp_slb_serials, wp_slb_registrations, wp_slb_distributors, wp_slb_products)
 * and mirrors its exact business rules:
 *
 *  - Warranty runs 12 months from the start date.
 *  - Registered device  → start = purchase_date (or registration day if blank).
 *  - Unregistered serial found in dealer records → start = shipped_date
 *    (the dealer sale date imported hourly from the ERP).
 *  - Auto-approve rule, status vocabulary and SMS/email templates come from the
 *    SLB plugin's own settings so app and website registrations behave the same.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Warranty {

	const WARRANTY_MONTHS = 12;
	const APP_NOTE        = 'Registered via Android app';

	/* --------------------------------------------------------------------- *
	 * Table helpers
	 * --------------------------------------------------------------------- */

	public static function t_serials() {
		global $wpdb;
		return $wpdb->prefix . 'slb_serials';
	}

	public static function t_regs() {
		global $wpdb;
		return $wpdb->prefix . 'slb_registrations';
	}

	public static function t_dist() {
		global $wpdb;
		return $wpdb->prefix . 'slb_distributors';
	}

	public static function t_prods() {
		global $wpdb;
		return $wpdb->prefix . 'slb_products';
	}

	/** Ledger of purchases a customer deliberately removed (see remove_device). */
	public static function t_dismissed() {
		return aun_app_api_dismissed_table();
	}

	/**
	 * Whether the SLB Warranty plugin's tables exist on this site.
	 *
	 * @return bool
	 */
	public static function available() {
		global $wpdb;
		$t = self::t_regs();
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
	}

	/**
	 * SLB Warranty plugin options.
	 *
	 * @return array
	 */
	public static function slb_opts() {
		$opts = get_option( AUN_APP_SLB_OPTION, array() );
		return is_array( $opts ) ? $opts : array();
	}

	/* --------------------------------------------------------------------- *
	 * Warranty math
	 * --------------------------------------------------------------------- */

	/**
	 * Compute the warranty window from a start date.
	 *
	 * Duration comes from the ERP product (products.warranty_id → warranties
	 * table — exactly what the sales invoice prints) whenever available; the
	 * legacy 12 months is only a fallback for serials the ERP can't resolve.
	 *
	 * Day counting is WHOLE-DAY in the site timezone: the remaining-days
	 * counter flips at midnight, never at the hour of purchase.
	 *
	 * @param string $start    Y-m-d.
	 * @param int    $duration ERP warranty duration (0 = fallback).
	 * @param string $unit     days|months|years.
	 * @return array{start:string,end:string,months:int,days_left:int,active:bool}
	 */
	public static function warranty_from( $start, $duration = 0, $unit = '' ) {
		$start_ts = strtotime( $start );
		if ( false === $start_ts ) {
			$start    = current_time( 'Y-m-d' );
			$start_ts = strtotime( $start );
		}
		$start = date( 'Y-m-d', $start_ts );

		$duration = (int) $duration;
		$unit     = strtolower( trim( (string) $unit ) );
		if ( $duration < 1 || ! in_array( $unit, array( 'days', 'months', 'years' ), true ) ) {
			$duration = self::WARRANTY_MONTHS;
			$unit     = 'months';
		}
		$end = date( 'Y-m-d', strtotime( $start . ' +' . $duration . ' ' . $unit ) );

		// Date-only midnight arithmetic — current_time() uses the site timezone.
		$today     = current_time( 'Y-m-d' );
		$days_left = (int) round( ( strtotime( $end ) - strtotime( $today ) ) / DAY_IN_SECONDS );

		$months = 'months' === $unit
			? $duration
			: ( 'years' === $unit ? $duration * 12 : max( 1, (int) round( $duration / 30 ) ) );

		return array(
			'start'     => $start,
			'end'       => $end,
			'months'    => $months,
			'days_left' => max( 0, $days_left ),
			'active'    => $days_left >= 0,
		);
	}

	/**
	 * The ERP-defined warranty for a serial: array(duration, unit) or
	 * array(0, '') when the ERP can't resolve it (→ 12-month fallback).
	 * Backed by the 10-min serial-lookup cache, so device lists stay fast.
	 *
	 * @param string $serial Serial / box barcode.
	 * @return array{0:int,1:string}
	 */
	public static function erp_warranty_for_serial( $serial ) {
		if ( '' === (string) $serial || ! AUN_App_ERP::configured() ) {
			return array( 0, '' );
		}
		$sale = AUN_App_ERP::lookup_serial( $serial );
		if ( is_array( $sale ) && null !== ( $sale['warranty_duration'] ?? null ) ) {
			return array( (int) $sale['warranty_duration'], (string) $sale['warranty_unit'] );
		}
		return array( 0, '' );
	}

	/* --------------------------------------------------------------------- *
	 * Reads
	 * --------------------------------------------------------------------- */

	/**
	 * Model name → id map (cached per request) so device payloads can link
	 * straight to their model's content.
	 *
	 * @return array<string,int>
	 */
	private static function model_map() {
		static $map = null;
		if ( null === $map ) {
			global $wpdb;
			$map = array();
			$t   = self::t_prods();
			foreach ( (array) $wpdb->get_results( "SELECT id, name FROM $t" ) as $p ) {
				$map[ strtolower( (string) $p->name ) ] = (int) $p->id;
			}
		}
		return $map;
	}

	/**
	 * Resolve a model NAME to a WP product id by EXACT (case-insensitive) match
	 * only. Deliberately NOT fuzzy — "A005" and "A005 Pro" are different products
	 * and must never share firmware/manuals. Used only as a last-resort fallback;
	 * the authoritative path is the ERP product id (see wp_product_id_from_erp).
	 *
	 * @param string $name Device model name.
	 * @return int
	 */
	public static function resolve_model_id( $name ) {
		$name = trim( strtolower( (string) $name ) );
		if ( '' === $name ) {
			return 0;
		}
		$map = self::model_map();
		return isset( $map[ $name ] ) ? $map[ $name ] : 0;
	}

	/**
	 * WP product id (slb_products.id) for an ERP product id, via the exact
	 * `slb_products.erp_product_id` link the warranty plugin + hourly sync use.
	 *
	 * @param int $erp_product_id ERP product id.
	 * @return int WP product id, or 0.
	 */
	public static function wp_product_id_from_erp( $erp_product_id ) {
		$erp_product_id = (int) $erp_product_id;
		if ( $erp_product_id <= 0 ) {
			return 0;
		}
		global $wpdb;
		$t = self::t_prods();
		// Membership test (SHOW COLUMNS ... LIKE is unreliable on SQLite).
		if ( ! in_array( 'erp_product_id', (array) $wpdb->get_col( "SHOW COLUMNS FROM $t" ), true ) ) {
			return 0; // very old install without the column
		}
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $t WHERE erp_product_id = %d LIMIT 1",
			$erp_product_id
		) );
	}

	/**
	 * WP product id for a serial, from the hourly-synced dealer stock. The sync
	 * already resolved each serial's ERP product to slb_products.id, so this is
	 * exact and authoritative for any synced serial.
	 *
	 * @param string $serial Serial.
	 * @return int WP product id, or 0.
	 */
	public static function wp_product_id_from_serial( $serial ) {
		$serial = self::sanitize_serial( $serial );
		if ( '' === $serial ) {
			return 0;
		}
		global $wpdb;
		$t = self::t_serials();
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT product_id FROM $t WHERE serial = %s AND product_id IS NOT NULL LIMIT 1",
			$serial
		) );
	}

	private static function product_name_by_id( $wp_id ) {
		$wp_id = (int) $wp_id;
		if ( $wp_id <= 0 ) {
			return '';
		}
		global $wpdb;
		$t = self::t_prods();
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $t WHERE id = %d", $wp_id ) );
	}

	/**
	 * Resolve an ERP product (by its ERP product id) to the WP model — EXACTLY,
	 * via slb_products.erp_product_id. Never guesses by name.
	 *
	 * @param int    $erp_product_id ERP product id.
	 * @param string $erp_name       ERP product name (display fallback only).
	 * @return array{id:int,name:string,matched:bool}
	 */
	private static function product_from_erp( $erp_product_id, $erp_name = '' ) {
		$wp_id = self::wp_product_id_from_erp( $erp_product_id );
		if ( $wp_id > 0 ) {
			return array( 'id' => $wp_id, 'name' => self::product_name_by_id( $wp_id ), 'matched' => true );
		}
		return array( 'id' => 0, 'name' => substr( trim( (string) $erp_name ), 0, 191 ), 'matched' => false );
	}

	/**
	 * The distinct content model ids for a customer's devices (0 excluded).
	 *
	 * @param string $canonical Canonical phone.
	 * @param int    $user_id   User id.
	 * @return int[]
	 */
	public static function user_model_ids( $canonical, $user_id ) {
		$ids = array();
		foreach ( self::get_devices( $canonical, $user_id ) as $d ) {
			if ( (int) $d['model_id'] > 0 ) {
				$ids[] = (int) $d['model_id'];
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Shape one registration row for the app.
	 *
	 * @param object $r Registration row joined with distributor name.
	 * @return array
	 */
	private static function device_payload( $r ) {
		$start = ! empty( $r->purchase_date ) && '0000-00-00' !== $r->purchase_date
			? $r->purchase_date
			: date( 'Y-m-d', strtotime( $r->created_at ) );
		// Duration from the ERP product of this serial (cached lookup).
		list( $wd, $wu ) = self::erp_warranty_for_serial( (string) $r->serial );
		$warranty        = self::warranty_from( $start, $wd, $wu );

		return array(
			'id'            => (int) $r->id,
			'source'        => 'registration',      // dealer purchase the customer registered
			'removable'     => true,
			'serial'        => (string) $r->serial,
			'model'         => (string) $r->product_model,
			// Authoritative: the serial's real product from the hourly sync;
			// only if unsynced do we fall back to the exact selected model name.
			'model_id'      => self::wp_product_id_from_serial( $r->serial )
				?: self::resolve_model_id( (string) $r->product_model ),
			'status'        => (string) $r->status,
			'purchase_date' => ! empty( $r->purchase_date ) && '0000-00-00' !== $r->purchase_date ? $r->purchase_date : '',
			'dealer'        => (string) ( ! empty( $r->distributor_name ) ? $r->distributor_name : $r->dealer_name ),
			'invoice_no'    => (string) $r->invoice_no,
			'registered_at' => date( 'Y-m-d', strtotime( $r->created_at ) ),
			// Warranty only counts once the registration is approved.
			'warranty'      => 'approved' === $r->status ? $warranty : null,
		);
	}

	/**
	 * Shape a direct-purchase link row (aun_app_devices) for the app. A direct
	 * purchase is always "covered" from its ERP sale date — no approval step.
	 *
	 * @param object $a Row from aun_app_devices.
	 * @return array
	 */
	private static function direct_payload( $a ) {
		$start = ! empty( $a->purchase_date ) && '0000-00-00' !== $a->purchase_date
			? $a->purchase_date
			: date( 'Y-m-d', strtotime( $a->created_at ) );

		// Duration captured at link time; live (cached) lookup covers links
		// created before v1.9.
		$wd = (int) ( $a->warranty_duration ?? 0 );
		$wu = (string) ( $a->warranty_unit ?? '' );
		if ( $wd < 1 ) {
			list( $wd, $wu ) = self::erp_warranty_for_serial( (string) $a->serial );
		}

		return array(
			'id'            => (int) $a->id,
			'source'        => 'direct',            // bought directly from AUN — no registration
			'removable'     => true,
			'serial'        => (string) $a->serial,
			'model'         => (string) $a->model,
			// Authoritative: the ERP product id captured when the device was
			// linked; exact model name only as a fallback.
			'model_id'      => self::wp_product_id_from_erp( $a->erp_product_id ?? 0 )
				?: self::resolve_model_id( (string) $a->model ),
			'status'        => 'direct',
			'purchase_date' => $start,
			'dealer'        => 'AUN (direct purchase)',
			'invoice_no'    => (string) $a->invoice_no,
			'registered_at' => date( 'Y-m-d', strtotime( $a->created_at ) ),
			'warranty'      => self::warranty_from( $start, $wd, $wu ),
		);
	}

	/* --------------------------------------------------------------------- *
	 * Direct-purchase links (aun_app_devices)
	 * --------------------------------------------------------------------- */

	/**
	 * Link a direct-sale device to a user (idempotent on serial). Never sends
	 * any notification and never touches the warranty-registration tables.
	 *
	 * @param array $args {user_id, phone, serial, model, purchase_date, invoice_no, erp_contact_id, erp_product_id}
	 * @return array{ok:bool,code:string,device?:array}
	 */
	public static function link_direct( $args ) {
		global $wpdb;
		$table  = aun_app_api_devices_table();
		$serial = self::sanitize_serial( $args['serial'] );
		if ( '' === $serial ) {
			return array( 'ok' => false, 'code' => 'invalid_serial' );
		}

		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE serial = %s", $serial ) );
		if ( $existing ) {
			// Already linked. If it's this user's, just return it; else it's taken.
			if ( (int) $existing->user_id === (int) $args['user_id'] ) {
				return array( 'ok' => true, 'code' => 'exists', 'device' => self::direct_payload( $existing ) );
			}
			return array( 'ok' => false, 'code' => 'owned_by_other' );
		}

		// The "brain" for removed devices:
		//  - AUTO path (login/refresh sync): if the customer previously removed
		//    THIS purchase, don't silently re-add it. A genuinely new purchase of
		//    the same unit (different invoice) is not "dismissed" — link it and
		//    forget the old dismissal.
		//  - MANUAL path (scan / find-by-phone / add-device): the customer is
		//    explicitly asking for it, so any dismissal is cleared and it links.
		$auto = ! empty( $args['auto'] );
		if ( $auto ) {
			if ( self::is_purchase_dismissed( (int) $args['user_id'], $serial, $args ) ) {
				return array( 'ok' => false, 'code' => 'dismissed' );
			}
		}
		// Either it's a manual add, or the auto sale differs from what was
		// dismissed — clear any stale dismissal so it can be re-linked cleanly.
		self::clear_dismissal( (int) $args['user_id'], $serial );

		$pdate = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $args['purchase_date'] ?? '' ) ) ? $args['purchase_date'] : null;

		$ok = $wpdb->insert( $table, array(
			'user_id'           => (int) $args['user_id'],
			'phone'             => (string) $args['phone'],
			'serial'            => $serial,
			'model'             => sanitize_text_field( (string) ( $args['model'] ?? '' ) ),
			'purchase_date'     => $pdate,
			'invoice_no'        => sanitize_text_field( (string) ( $args['invoice_no'] ?? '' ) ),
			'erp_contact_id'    => (int) ( $args['erp_contact_id'] ?? 0 ),
			'erp_product_id'    => (int) ( $args['erp_product_id'] ?? 0 ),
			// The invoice-printed warranty, captured at link time.
			'warranty_duration' => isset( $args['warranty_duration'] ) && null !== $args['warranty_duration']
				? (int) $args['warranty_duration'] : null,
			'warranty_unit'     => sanitize_text_field( (string) ( $args['warranty_unit'] ?? '' ) ),
			'source'            => 'direct',
			'created_at'        => current_time( 'mysql' ),
			// Just confirmed against the ERP — the daily sweep can skip it for
			// a day.
			'verified_at'       => current_time( 'mysql' ),
			'verify_misses'     => 0,
		) );

		if ( false === $ok ) {
			return array( 'ok' => false, 'code' => 'db_error' );
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $wpdb->insert_id ) );
		return array( 'ok' => true, 'code' => 'linked', 'device' => self::direct_payload( $row ) );
	}

	/* --------------------------------------------------------------------- *
	 * Dismissed-purchases ledger — remembers auto-linked devices the customer
	 * deliberately removed, so the ERP auto-sync doesn't resurrect them.
	 * --------------------------------------------------------------------- */

	/**
	 * Record that a customer removed an auto-linked purchase, so the login/
	 * refresh sync won't add it back. Idempotent per (user, serial).
	 *
	 * @param int    $user_id    Owner.
	 * @param string $serial     Serial being dismissed.
	 * @param string $invoice_no ERP invoice of the dismissed sale (may be '').
	 * @param string $sale_date  Purchase date Y-m-d (may be '').
	 */
	public static function record_dismissal( $user_id, $serial, $invoice_no = '', $sale_date = '' ) {
		global $wpdb;
		$serial = self::sanitize_serial( $serial );
		if ( (int) $user_id < 1 || '' === $serial ) {
			return;
		}
		$t     = self::t_dismissed();
		$pdate = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $sale_date ) ? $sale_date : null;
		$data  = array(
			'invoice_no'   => sanitize_text_field( (string) $invoice_no ),
			'sale_date'    => $pdate,
			'dismissed_at' => current_time( 'mysql' ),
		);

		// One row per (user, serial): update in place if it already exists.
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $t WHERE user_id = %d AND serial = %s",
			(int) $user_id, $serial
		) );
		if ( $id ) {
			$wpdb->update( $t, $data, array( 'id' => (int) $id ) );
			return;
		}
		$wpdb->insert( $t, array_merge( $data, array(
			'user_id' => (int) $user_id,
			'serial'  => $serial,
		) ) );
	}

	/** Forget a dismissal (on manual re-add, or a genuinely new purchase). */
	public static function clear_dismissal( $user_id, $serial ) {
		global $wpdb;
		$serial = self::sanitize_serial( $serial );
		if ( (int) $user_id < 1 || '' === $serial ) {
			return;
		}
		$wpdb->delete( self::t_dismissed(), array( 'user_id' => (int) $user_id, 'serial' => $serial ), array( '%d', '%s' ) );
	}

	/**
	 * Is this ERP sale the same purchase the customer previously dismissed?
	 *
	 * A dismissal suppresses THAT purchase, not the unit forever. The match:
	 *  - both have an invoice → same iff the invoice matches (the strongest key);
	 *  - otherwise fall back to the sale date → same iff the date matches;
	 *  - if there's nothing to compare, treat it as the same (suppress) — with no
	 *    distinguishing info, re-adding would just reopen the loop the customer
	 *    complained about; they can always add it back manually.
	 *
	 * A different invoice (or a different date when invoices are absent) means a
	 * genuinely new purchase of the same unit → NOT dismissed.
	 *
	 * @param int    $user_id User.
	 * @param string $serial  Serial.
	 * @param array  $sale    Candidate sale: invoice_no / purchase_date.
	 * @return bool
	 */
	public static function is_purchase_dismissed( $user_id, $serial, $sale ) {
		global $wpdb;
		$serial = self::sanitize_serial( $serial );
		if ( (int) $user_id < 1 || '' === $serial ) {
			return false;
		}
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . self::t_dismissed() . " WHERE user_id = %d AND serial = %s",
			(int) $user_id, $serial
		) );
		if ( ! $row ) {
			return false;
		}

		$d_inv  = trim( (string) $row->invoice_no );
		$s_inv  = trim( (string) ( $sale['invoice_no'] ?? '' ) );
		if ( '' !== $d_inv && '' !== $s_inv ) {
			return strcasecmp( $d_inv, $s_inv ) === 0;
		}

		$d_date = (string) ( $row->sale_date ?? '' );
		$s_date = (string) ( $sale['purchase_date'] ?? '' );
		if ( '' !== $d_date && '' !== $s_date ) {
			return substr( $d_date, 0, 10 ) === substr( $s_date, 0, 10 );
		}

		// No invoice and no date to distinguish by → keep it suppressed.
		return true;
	}

	/**
	 * User ids that should hear about content for a model (push targeting).
	 * model_id 0 = general content → every user with a live app session.
	 *
	 * @param int $model_id WP product id (slb_products.id) or 0.
	 * @return int[]
	 */
	public static function user_ids_for_model( $model_id ) {
		global $wpdb;
		$model_id = (int) $model_id;
		$ids      = array();

		if ( $model_id <= 0 ) {
			// General content: every account that can actually receive it.
			$t = aun_app_api_tokens_table();
			return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT user_id FROM $t WHERE expires_at > %s",
				current_time( 'mysql' )
			) ) );
		}

		// Direct links: resolve each row's model the same way direct_payload
		// does (exact ERP product id first, exact name only as fallback).
		$t_dev = aun_app_api_devices_table();
		foreach ( (array) $wpdb->get_results( "SELECT user_id, model, erp_product_id FROM $t_dev" ) as $row ) {
			$mid = self::wp_product_id_from_erp( (int) ( $row->erp_product_id ?? 0 ) )
				?: self::resolve_model_id( (string) $row->model );
			if ( $mid === $model_id ) {
				$ids[] = (int) $row->user_id;
			}
		}

		// Registrations: serial → synced product id (authoritative), else the
		// registered model name; then phone → app account(s).
		$t_regs = self::t_regs();
		foreach ( (array) $wpdb->get_results( "SELECT phone, serial, product_model FROM $t_regs" ) as $row ) {
			$mid = self::wp_product_id_from_serial( (string) $row->serial )
				?: self::resolve_model_id( (string) $row->product_model );
			if ( $mid !== $model_id ) {
				continue;
			}
			$canonical = AUN_App_Phone::normalize( (string) $row->phone );
			if ( ! $canonical ) {
				continue;
			}
			foreach ( AUN_App_Phone::find_users( $canonical ) as $user ) {
				$ids[] = (int) $user->ID;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/* --------------------------------------------------------------------- *
	 * Re-verification of direct links against the ERP
	 *
	 * A direct link exists for exactly one reason: the ERP says this customer
	 * bought this unit. If that stops being true — the sale was RETURNED (the
	 * ERP writes quantity_returned on the sell line; the sale itself stays
	 * final, so nothing else reveals it) or the sale record was DELETED — the
	 * link must go: the device leaves My Devices, its warranty and maintenance
	 * reminders stop, and the serial is freed so the next buyer can claim it.
	 *
	 * Dealer REGISTRATIONS are deliberately untouched: those are the customer's
	 * own proof of purchase from a dealer, approved by a human in the warranty
	 * plugin, and the ERP sale behind them belongs to the dealer, not to them.
	 * --------------------------------------------------------------------- */

	/** A "sale not found" must repeat this many times before we unlink. */
	const VERIFY_MISS_LIMIT = 2;

	/** Re-check each device about once a day. */
	const VERIFY_INTERVAL = DAY_IN_SECONDS;

	/** Devices per sweep (hourly cron) — bounds the ERP calls per run. */
	const VERIFY_BATCH = 40;

	/**
	 * Verify ONE direct link against the ERP.
	 *
	 * @param object $row aun_app_devices row.
	 * @return string 'ok' | 'returned' | 'missing' | 'unavailable'
	 */
	public static function verify_direct_device( $row ) {
		if ( ! AUN_App_ERP::configured() ) {
			return 'unavailable';
		}

		$sale = AUN_App_ERP::lookup_serial( (string) $row->serial );

		// Transport/HTTP failure — the ERP is simply unreachable. NEVER treat
		// an outage as "the customer never bought it".
		if ( is_wp_error( $sale ) ) {
			return 'unavailable';
		}
		if ( is_array( $sale ) ) {
			return ! empty( $sale['returned'] ) ? 'returned' : 'ok';
		}
		return 'missing'; // definitive found:false from the ERP
	}

	/**
	 * Apply a verification result to a device row.
	 *
	 * @param object $row    aun_app_devices row.
	 * @param string $result From verify_direct_device().
	 * @return string Action taken: 'kept' | 'unlinked' | 'strike' | 'skipped'.
	 */
	public static function apply_verification( $row, $result ) {
		global $wpdb;
		$table = aun_app_api_devices_table();
		$now   = current_time( 'mysql' );

		// ERP unreachable: leave everything exactly as it is and try later.
		if ( 'unavailable' === $result ) {
			return 'skipped';
		}

		// A return is a definitive business event — unlink at once.
		if ( 'returned' === $result ) {
			self::unlink_verified( $row, 'returned' );
			return 'unlinked';
		}

		if ( 'missing' === $result ) {
			$misses = (int) ( $row->verify_misses ?? 0 ) + 1;
			if ( $misses >= self::VERIFY_MISS_LIMIT ) {
				self::unlink_verified( $row, 'sale_deleted' );
				return 'unlinked';
			}
			$wpdb->update( $table, array(
				'verify_misses' => $misses,
				'verified_at'   => $now,
			), array( 'id' => (int) $row->id ) );
			return 'strike';
		}

		// Still a valid sale — clear any earlier strike.
		$wpdb->update( $table, array(
			'verify_misses' => 0,
			'verified_at'   => $now,
		), array( 'id' => (int) $row->id ) );
		return 'kept';
	}

	/**
	 * Remove a link the ERP no longer backs, and clean up what hangs off it.
	 *
	 * @param object $row    aun_app_devices row.
	 * @param string $reason 'returned' | 'sale_deleted'.
	 */
	private static function unlink_verified( $row, $reason ) {
		global $wpdb;

		$wpdb->delete( aun_app_api_devices_table(), array( 'id' => (int) $row->id ), array( '%d' ) );

		// Dust-filter reminders for a projector they no longer own would be
		// nonsense — drop this serial's reminders for this user.
		if ( class_exists( 'AUN_App_Notices' ) ) {
			AUN_App_Notices::delete_for_serial( (int) $row->user_id, (string) $row->serial );
		}

		error_log( sprintf(
			'AUN APP API: unlinked device %s from user #%d (%s).',
			(string) $row->serial,
			(int) $row->user_id,
			$reason
		) );
	}

	/**
	 * Hourly sweep: verify the direct links that are due (never checked, or
	 * checked more than VERIFY_INTERVAL ago), oldest first.
	 *
	 * @return array{checked:int,unlinked:int,strikes:int,skipped:int}
	 */
	public static function verify_devices_batch() {
		global $wpdb;
		$stats = array( 'checked' => 0, 'unlinked' => 0, 'strikes' => 0, 'skipped' => 0 );

		if ( ! AUN_App_ERP::configured() ) {
			return $stats;
		}

		$table = aun_app_api_devices_table();
		$due   = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - self::VERIFY_INTERVAL );

		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table
			 WHERE verified_at IS NULL OR verified_at <= %s
			 ORDER BY verified_at IS NULL DESC, verified_at ASC
			 LIMIT %d",
			$due,
			self::VERIFY_BATCH
		) );

		foreach ( $rows as $row ) {
			$action = self::apply_verification( $row, self::verify_direct_device( $row ) );
			$stats['checked']++;
			if ( 'unlinked' === $action ) {
				$stats['unlinked']++;
			} elseif ( 'strike' === $action ) {
				$stats['strikes']++;
			} elseif ( 'skipped' === $action ) {
				$stats['skipped']++;
				break; // ERP is down — stop hammering it, resume next hour.
			}
		}

		return $stats;
	}

	/**
	 * Remove a device the user owns, freeing its serial for re-registration.
	 *
	 * @param int    $user_id   Requesting user.
	 * @param string $canonical Their canonical phone.
	 * @param string $source    'direct' | 'registration'.
	 * @param int    $id        Row id in the matching table.
	 * @return bool
	 */
	public static function remove_device( $user_id, $canonical, $source, $id ) {
		global $wpdb;
		$id = (int) $id;

		if ( 'direct' === $source ) {
			$table = aun_app_api_devices_table();
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
			if ( ! $row || (int) $row->user_id !== (int) $user_id ) {
				return false;
			}
			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
			// Remember the customer chose to remove this ERP purchase, so the
			// login/refresh auto-sync won't silently link it back. The serial
			// itself is freed (row deleted) for another buyer; this ledger is
			// per-user only.
			self::record_dismissal(
				(int) $user_id,
				(string) $row->serial,
				(string) ( $row->invoice_no ?? '' ),
				(string) ( $row->purchase_date ?? '' )
			);
			// The projector is off the account — its filter reminders go too.
			if ( class_exists( 'AUN_App_Notices' ) ) {
				AUN_App_Notices::delete_for_serial( (int) $user_id, (string) $row->serial );
			}
			return true;
		}

		// registration: only the owner (phone match) may remove it.
		$t_regs   = self::t_regs();
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_regs WHERE id = %d", $id ) );
		$variants = AUN_App_Phone::variants( $canonical );
		if ( ! $row || ! in_array( (string) $row->phone, $variants, true ) ) {
			return false;
		}
		$wpdb->delete( $t_regs, array( 'id' => $id ), array( '%d' ) );
		if ( class_exists( 'AUN_App_Notices' ) ) {
			AUN_App_Notices::delete_for_serial( (int) $user_id, (string) $row->serial );
		}
		// Free the dealer serial so it (or the customer) can register again.
		$wpdb->update( self::t_serials(), array( 'registered' => 0, 'registration_id' => null ), array( 'registration_id' => $id ) );
		return true;
	}

	/**
	 * All registrations belonging to a phone number (any stored format).
	 *
	 * @param string $canonical 8801XXXXXXXXX
	 * @return array[]
	 */
	/**
	 * Auto-discover the customer's own retail (non-Dealer) purchases from the
	 * ERP by their login phone and link them — so a device shows up in "My
	 * Devices" the moment a retail sale is recorded, with NO scanning or manual
	 * step. Dealer-group sales are deliberately left for manual registration.
	 *
	 * Throttled per user so opening the app doesn't hammer the ERP; a fresh
	 * login forces it. Because it runs on every (throttled) devices load, a
	 * sale recorded AFTER the customer installed the app is picked up on the
	 * next open too — past or future, it just appears.
	 *
	 * @param int    $user_id   User id.
	 * @param string $canonical Login phone 8801XXXXXXXXX.
	 * @param bool   $force     Skip the throttle (used on login).
	 */
	public static function sync_own_purchases( $user_id, $canonical, $force = false ) {
		$user_id = (int) $user_id;
		if ( $user_id < 1 || '' === (string) $canonical || ! AUN_App_ERP::configured() ) {
			return;
		}

		$throttle = 'aun_app_own_sync_' . $user_id;
		if ( ! $force && get_transient( $throttle ) ) {
			return;
		}

		// purchases_by_phone() links retail sales and flags dealer ones; we only
		// need the side effect (the linking) here.
		$result = self::purchases_by_phone( $canonical, array(
			'user_id' => $user_id,
			'phone'   => $canonical,
			// Passive background sync — respect devices the customer removed.
			'auto'    => true,
		) );

		// Only start the cooldown when the ERP actually answered — an outage
		// should retry on the next load, not stay silent for minutes.
		if ( ! is_wp_error( $result ) ) {
			set_transient( $throttle, 1, 5 * MINUTE_IN_SECONDS );
		}
	}

	public static function get_devices( $canonical, $user_id = 0 ) {
		global $wpdb;

		$out  = array();
		$seen = array();

		// 1. Direct purchases (bought from AUN — no registration needed).
		if ( $user_id > 0 ) {
			$table = aun_app_api_devices_table();
			$rows  = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
				(int) $user_id
			) );
			foreach ( (array) $rows as $a ) {
				$out[]              = self::direct_payload( $a );
				$seen[ $a->serial ] = true;
			}
		}

		// 2. Dealer purchases the customer registered (matched by phone).
		if ( '' !== (string) $canonical ) {
			$variants     = AUN_App_Phone::variants( $canonical );
			$placeholders = implode( ',', array_fill( 0, count( $variants ), '%s' ) );
			$t_regs       = self::t_regs();
			$t_dist       = self::t_dist();

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.*, d.name AS distributor_name
					 FROM $t_regs r
					 LEFT JOIN $t_dist d ON r.distributor_id = d.id
					 WHERE r.phone IN ($placeholders)
					 ORDER BY r.created_at DESC",
					$variants
				)
			);
			foreach ( (array) $rows as $r ) {
				if ( isset( $seen[ $r->serial ] ) ) {
					continue;
				}
				$out[] = self::device_payload( $r );
			}
		}

		return $out;
	}

	/**
	 * Look up one serial: registered? in dealer records? unknown?
	 *
	 * @param string $serial    Serial as typed.
	 * @param string $canonical Requesting user's canonical phone.
	 * @return array
	 */
	public static function check_serial( $serial, $canonical ) {
		global $wpdb;

		$serial = self::sanitize_serial( $serial );
		if ( '' === $serial ) {
			return array( 'state' => 'invalid' );
		}

		$t_regs    = self::t_regs();
		$t_dist    = self::t_dist();
		$t_serials = self::t_serials();

		$reg = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.*, d.name AS distributor_name
				 FROM $t_regs r
				 LEFT JOIN $t_dist d ON r.distributor_id = d.id
				 WHERE r.serial = %s",
				$serial
			)
		);

		if ( $reg ) {
			$variants = AUN_App_Phone::variants( $canonical );
			if ( in_array( (string) $reg->phone, $variants, true ) ) {
				return array(
					'state'  => 'registered_own',
					'device' => self::device_payload( $reg ),
				);
			}
			$reg_phone = AUN_App_Phone::normalize( $reg->phone );
			return array(
				'state'        => 'registered_other',
				'masked_phone' => $reg_phone ? AUN_App_Phone::mask( $reg_phone ) : '',
			);
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.*, d.name AS dealer_name
				 FROM $t_serials s
				 LEFT JOIN $t_dist d ON s.distributor_id = d.id
				 WHERE s.serial = %s",
				$serial
			)
		);

		if ( $row ) {
			$shipped  = ! empty( $row->shipped_date ) && '0000-00-00' !== $row->shipped_date
				? $row->shipped_date
				: date( 'Y-m-d', strtotime( $row->created_at ) );
			return array(
				'state'           => 'in_dealer_records',
				'dealer'          => (string) $row->dealer_name,
				'shipped_date'    => $shipped,
				// If never registered, warranty counts from the dealer sale date.
				'warranty'        => self::warranty_from( $shipped, ...self::erp_warranty_for_serial( $serial ) ),
				'prompt_register' => true,
			);
		}

		return array(
			'state'           => 'unknown',
			'prompt_register' => true,
		);
	}

	/**
	 * Distributor list for the registration form dropdown.
	 *
	 * @return array[]
	 */
	public static function dealers() {
		global $wpdb;
		$t    = self::t_dist();
		$rows = $wpdb->get_results( "SELECT id, name, address FROM $t ORDER BY name ASC" );
		$out  = array();
		foreach ( (array) $rows as $d ) {
			$out[] = array(
				'id'      => (int) $d->id,
				'name'    => (string) $d->name,
				'address' => (string) $d->address,
			);
		}
		return $out;
	}

	/**
	 * Product model list.
	 *
	 * @return array[]
	 */
	public static function models() {
		global $wpdb;
		$t    = self::t_prods();
		$rows = $wpdb->get_results( "SELECT id, name FROM $t ORDER BY name ASC" );
		$out  = array();
		foreach ( (array) $rows as $p ) {
			$out[] = array(
				'id'   => (int) $p->id,
				'name' => (string) $p->name,
			);
		}
		return $out;
	}

	/**
	 * How many registrations came from the app (admin stat).
	 *
	 * @return int
	 */
	public static function app_registrations_count() {
		global $wpdb;
		if ( ! self::available() ) {
			return 0;
		}
		$t = self::t_regs();
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE notes LIKE %s", self::APP_NOTE . '%' )
		);
	}

	/* --------------------------------------------------------------------- *
	 * Register a device (mirrors the CF7 flow of the SLB plugin)
	 * --------------------------------------------------------------------- */

	public static function sanitize_serial( $serial ) {
		$serial = strtoupper( trim( (string) $serial ) );
		if ( ! preg_match( '/^[A-Z0-9\-_\/]{3,60}$/', $serial ) ) {
			return '';
		}
		return $serial;
	}

	/**
	 * Create a registration with the same auto-approve rules as the website form.
	 *
	 * @param array $args {
	 *     user_id, customer_name, phone (canonical), email, serial, model,
	 *     distributor_id, dealer_name, purchase_date (Y-m-d), invoice_no, invoice_file (URL)
	 * }
	 * @return array{ok:bool,code:string,message:string,device?:array,owned_by_you?:bool}
	 */
	public static function register_device( $args ) {
		global $wpdb;

		$t_regs    = self::t_regs();
		$t_serials = self::t_serials();
		$t_dist    = self::t_dist();
		$t_prods   = self::t_prods();

		$serial = self::sanitize_serial( isset( $args['serial'] ) ? $args['serial'] : '' );
		if ( '' === $serial ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_serial',
				'message' => 'Please enter a valid serial number.',
			);
		}

		// Duplicate? (serial is UNIQUE in slb_registrations)
		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, phone FROM $t_regs WHERE serial = %s", $serial )
		);
		if ( $existing ) {
			$variants = AUN_App_Phone::variants( $args['phone'] );
			return array(
				'ok'           => false,
				'code'         => 'already_registered',
				'message'      => 'This serial number is already registered.',
				'owned_by_you' => in_array( (string) $existing->phone, $variants, true ),
			);
		}

		// Model must be one of the configured product models (same list as the website select).
		$model = sanitize_text_field( isset( $args['model'] ) ? $args['model'] : '' );
		$model_row = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, name FROM $t_prods WHERE LOWER(name) = LOWER(%s)", $model )
		);
		$has_models = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_prods" ) > 0;
		if ( $has_models && ! $model_row ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_model',
				'message' => 'Please choose a valid product model.',
			);
		}
		if ( $model_row ) {
			$model = (string) $model_row->name;
		}

		// Purchase date: Y-m-d, not in the future, not absurdly old.
		$pdate = isset( $args['purchase_date'] ) ? trim( (string) $args['purchase_date'] ) : '';
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $pdate, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_date',
				'message' => 'Please enter a valid purchase date.',
			);
		}
		$pdate_ts = strtotime( $pdate . ' 00:00:00' );
		if ( $pdate_ts > strtotime( date( 'Y-m-d 23:59:59' ) ) || $pdate_ts < strtotime( '2015-01-01' ) ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_date',
				'message' => 'The purchase date cannot be in the future.',
			);
		}

		// Dealer: a known distributor id, or free text for "somewhere else".
		$distributor_id = isset( $args['distributor_id'] ) ? (int) $args['distributor_id'] : 0;
		$dealer_name    = sanitize_text_field( isset( $args['dealer_name'] ) ? $args['dealer_name'] : '' );
		if ( $distributor_id > 0 ) {
			$dist = $wpdb->get_row( $wpdb->prepare( "SELECT id, name FROM $t_dist WHERE id = %d", $distributor_id ) );
			if ( ! $dist ) {
				return array(
					'ok'      => false,
					'code'    => 'invalid_dealer',
					'message' => 'Please choose a valid dealer.',
				);
			}
			$dealer_name = (string) $dist->name;
		} elseif ( '' === $dealer_name ) {
			return array(
				'ok'      => false,
				'code'    => 'invalid_dealer',
				'message' => 'Please tell us where you bought the projector.',
			);
		}

		// Proof is mandatory: invoice/order number + a photo of the invoice.
		$invoice_no   = sanitize_text_field( isset( $args['invoice_no'] ) ? $args['invoice_no'] : '' );
		$invoice_file = esc_url_raw( isset( $args['invoice_file'] ) ? $args['invoice_file'] : '' );
		if ( '' === $invoice_no ) {
			return array(
				'ok'      => false,
				'code'    => 'invoice_required',
				'message' => 'Please enter the invoice / order number.',
			);
		}
		if ( '' === $invoice_file ) {
			return array(
				'ok'      => false,
				'code'    => 'invoice_photo_required',
				'message' => 'Please add a photo of your invoice.',
			);
		}

		// ── Auto-approve decision (same vocabulary as the SLB plugin) ──
		$serial_row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $t_serials WHERE serial = %s", $serial )
		);

		$slb  = self::slb_opts();
		$rule = isset( $slb['auto_approve_rule'] ) ? $slb['auto_approve_rule'] : 'match_serial_and_distributor';

		if ( 'off' === $rule ) {
			$status = 'pending';
		} elseif ( ! $serial_row ) {
			$status = 'not_found';
		} elseif ( 'match_serial' === $rule ) {
			$status = 'approved';
		} else { // match_serial_and_distributor
			$status = ( $distributor_id > 0 && (int) $serial_row->distributor_id === $distributor_id )
				? 'approved'
				: 'mismatch';
		}

		$customer_name = sanitize_text_field( isset( $args['customer_name'] ) ? $args['customer_name'] : '' );
		$email         = sanitize_email( isset( $args['email'] ) ? $args['email'] : '' );
		// $invoice_no and $invoice_file were validated + sanitised above.

		$ok = $wpdb->insert(
			$t_regs,
			array(
				'serial'         => $serial,
				'distributor_id' => $distributor_id > 0 ? $distributor_id : null,
				'customer_name'  => $customer_name,
				'phone'          => (string) $args['phone'],
				'email'          => $email,
				'product_model'  => $model,
				'invoice_no'     => $invoice_no,
				'purchase_date'  => $pdate,
				'dealer_name'    => $dealer_name,
				'invoice_file'   => $invoice_file,
				'product_photo'  => '',
				'status'         => $status,
				'notes'          => self::APP_NOTE . ' (user #' . (int) $args['user_id'] . ')',
			)
		);

		if ( false === $ok ) {
			error_log( 'AUN APP API: registration insert failed for ' . $serial . ' — ' . $wpdb->last_error );
			return array(
				'ok'      => false,
				'code'    => 'db_error',
				'message' => 'Could not save the registration. Please try again.',
			);
		}

		$reg_id = (int) $wpdb->insert_id;

		if ( 'approved' === $status && $serial_row ) {
			$wpdb->update(
				$t_serials,
				array(
					'registered'      => 1,
					'registration_id' => $reg_id,
				),
				array( 'id' => $serial_row->id )
			);
		}

		// Reuse the SLB plugin's image-compression queue when it is active.
		if ( $reg_id && '' !== $invoice_file && function_exists( 'slb_queue_compression' ) ) {
			slb_queue_compression( $reg_id );
		}

		self::notify( $status, array(
			'serial'  => $serial,
			'start'   => $pdate,
			'end'     => self::warranty_from( $pdate, ...self::erp_warranty_for_serial( $serial ) )['end'],
			'invoice' => $invoice_no,
			'name'    => $customer_name,
			'phone'   => (string) $args['phone'],
		), (string) $args['phone'], $email );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT r.*, d.name AS distributor_name FROM ' . $t_regs . ' r
				 LEFT JOIN ' . $t_dist . ' d ON r.distributor_id = d.id WHERE r.id = %d',
				$reg_id
			)
		);

		return array(
			'ok'      => true,
			'code'    => $status,
			'message' => 'approved' === $status
				? 'Registration approved — your warranty is now active.'
				: 'Registration received — we will verify and confirm by SMS.',
			'device'  => $row ? self::device_payload( $row ) : null,
		);
	}

	/* --------------------------------------------------------------------- *
	 * Notifications — same templates & credentials as the SLB plugin settings
	 * --------------------------------------------------------------------- */

	/**
	 * Send the registration status SMS/email by delegating to the AUN Warranty
	 * plugin's OWN sender functions. Those set the correct HTML email header and
	 * use the store's configured credentials, so an app registration produces the
	 * exact same message the website sends — no separately-built (and previously
	 * broken) email. If the warranty plugin is inactive, nothing is sent rather
	 * than sending a malformed message.
	 *
	 * @param string $status Status key (approved/pending/not_found/mismatch/...).
	 * @param array  $vars   Template variables.
	 * @param string $phone  Canonical phone.
	 * @param string $email  Email (may be empty).
	 */
	private static function notify( $status, $vars, $phone, $email ) {
		$slb = self::slb_opts();

		$sms_templates = isset( $slb['sms_templates'] ) && is_array( $slb['sms_templates'] ) ? $slb['sms_templates'] : array();
		$tpl_sms       = $sms_templates[ $status ] ?? ( $sms_templates['received'] ?? '' );
		if ( function_exists( 'slb_send_templated_sms' ) && '' !== $tpl_sms && '' !== $phone ) {
			slb_send_templated_sms( $phone, $tpl_sms, $vars );
		}

		$email_templates = isset( $slb['email_templates'] ) && is_array( $slb['email_templates'] ) ? $slb['email_templates'] : array();
		$tpl_email       = $email_templates[ $status ] ?? '';
		if ( function_exists( 'slb_send_templated_email' ) && '' !== $tpl_email && '' !== $email && is_email( $email ) ) {
			slb_send_templated_email( $email, $tpl_email, $vars, $status );
		}
	}

	/* --------------------------------------------------------------------- *
	 * The "brain": resolve any box barcode / serial to the right outcome
	 * --------------------------------------------------------------------- */


	/**
	 * Does this user own the given serial (direct link OR their registration)?
	 * Used to gate after-sales services to actual customers.
	 *
	 * ⚠️ Only a CONFIRMED device counts: a direct ERP link (always verified) or a
	 * registration whose status is 'approved'. A registration still pending
	 * review — or one that was rejected / didn't match — must NOT unlock spare
	 * parts or repair bookings, otherwise anyone could type a random serial and
	 * immediately use after-sales services. (Content — firmware, manuals, videos
	 * — stays open; it's public on the website anyway.)
	 *
	 * @param int    $user_id   User id.
	 * @param string $canonical Their canonical phone.
	 * @param string $serial    Serial.
	 * @return bool
	 */
	public static function owns_serial( $user_id, $canonical, $serial ) {
		global $wpdb;
		$serial = self::sanitize_serial( $serial );
		if ( '' === $serial ) {
			return false;
		}

		$t = aun_app_api_devices_table();
		if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE serial = %s AND user_id = %d", $serial, (int) $user_id ) ) ) {
			return true;
		}

		if ( '' !== (string) $canonical ) {
			$variants     = AUN_App_Phone::variants( $canonical );
			$placeholders = implode( ',', array_fill( 0, count( $variants ), '%s' ) );
			$t_regs       = self::t_regs();
			$params       = array_merge( array( $serial ), $variants );
			if ( (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $t_regs WHERE serial = %s AND phone IN ($placeholders) AND status = 'approved'",
				$params
			) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a serial is already linked as someone's direct purchase.
	 *
	 * @param string $serial    Clean serial.
	 * @param string $canonical Requesting user's phone.
	 * @param int    $user_id   Requesting user id.
	 * @return array|null registered_own/registered_other payload, or null.
	 */
	private static function check_direct_link( $serial, $canonical, $user_id ) {
		global $wpdb;
		$table = aun_app_api_devices_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE serial = %s", $serial ) );
		if ( ! $row ) {
			return null;
		}
		if ( (int) $row->user_id === (int) $user_id ) {
			return array( 'state' => 'registered_own', 'device' => self::direct_payload( $row ) );
		}
		$other = AUN_App_Phone::normalize( $row->phone );
		return array(
			'state'        => 'registered_other',
			'masked_phone' => $other ? AUN_App_Phone::mask( $other ) : '',
		);
	}

	/**
	 * Decide what a scanned/typed serial means for THIS user, mirroring the
	 * website warranty logic exactly:
	 *
	 *  registered_own    → already on their account (device included)
	 *  registered_other  → claimed by another customer (masked phone) [security]
	 *  direct_sale       → bought directly from AUN → warranty from the ERP sale
	 *                      date, linked to the account. NO registration, NO
	 *                      SMS/email (the ERP sale is the proof).
	 *  dealer_stock      → belongs to the "Dealers" customer group → the
	 *                      customer must register (warranty starts at THEIR
	 *                      purchase date, which the ERP does not know). The
	 *                      website warranty plugin then sends the SMS/email.
	 *  unknown           → nowhere on record → manual-review registration
	 *
	 * @param string $serial    Raw serial/barcode.
	 * @param array  $user_args {user_id, customer_name, phone, email}
	 * @return array
	 */
	public static function resolve_serial( $serial, $user_args ) {
		$clean = self::sanitize_serial( $serial );
		if ( '' === $clean ) {
			return array( 'state' => 'invalid' );
		}

		// 1. Already linked as a direct purchase?
		$direct = self::check_direct_link( $clean, $user_args['phone'], (int) $user_args['user_id'] );
		if ( $direct ) {
			return $direct;
		}

		// 2. Already registered on the website/app (dealer registration)?
		$existing = self::check_serial( $clean, $user_args['phone'] );
		if ( 'registered_own' === $existing['state'] || 'registered_other' === $existing['state'] ) {
			return $existing;
		}

		// 3. Ask the ERP (authoritative for the dealer-vs-direct distinction).
		$sale = AUN_App_ERP::configured() ? AUN_App_ERP::lookup_serial( $clean ) : false;

		// A returned unit is not a purchase: never link it, never grant its
		// warranty. Falls through to the local records / manual registration,
		// so a resold unit reaches its new owner through the normal review.
		if ( is_array( $sale ) && ! empty( $sale['returned'] ) ) {
			$sale = false;
		}

		if ( is_array( $sale ) ) {
			if ( AUN_App_ERP::is_dealer_sale( $sale ) ) {
				// Dealer stock → the customer registers (they set the real
				// purchase date). Notifications are the warranty plugin's job.
				$product = self::product_from_erp( $sale['product_id'], $sale['product_name'] );
				return array(
					'state'           => 'dealer_stock',
					'dealer'          => $sale['contact_name'],
					'shipped_date'    => $sale['sale_date'],
					'suggested_model' => $product['matched'] ? $product['name'] : '',
					'warranty'        => self::warranty_from(
						$sale['sale_date'],
						(int) ( $sale['warranty_duration'] ?? 0 ),
						(string) ( $sale['warranty_unit'] ?? '' )
					),
					'prompt_register' => true,
				);
			}

			// Direct sale → link it, warranty from the ERP sale date. No form,
			// no notification. The customer "doesn't need to do anything".
			$product = self::product_from_erp( $sale['product_id'], $sale['product_name'] );
			$link    = self::link_direct( array(
				'user_id'           => $user_args['user_id'],
				'phone'             => $user_args['phone'],
				'serial'            => $clean,
				'model'             => $product['name'],
				'purchase_date'     => $sale['sale_date'],
				'invoice_no'        => $sale['invoice_no'],
				'erp_contact_id'    => $sale['contact_id'],
				'erp_product_id'    => $sale['product_id'],
				'warranty_duration' => $sale['warranty_duration'] ?? null,
				'warranty_unit'     => $sale['warranty_unit'] ?? '',
			) );

			if ( $link['ok'] ) {
				return array(
					'state'  => 'direct_sale',
					'device' => $link['device'],
				);
			}
			if ( 'owned_by_other' === $link['code'] ) {
				return array( 'state' => 'registered_other', 'masked_phone' => '' );
			}
			// Fall through on db error.
		}

		// 4. ERP unreachable or no hit → local synced dealer stock, else unknown.
		return $existing; // in_dealer_records (dealer form) or unknown.
	}

	/**
	 * ERP purchases on a phone number. Direct purchases are LINKED to the
	 * account automatically (they need no registration); dealer purchases —
	 * which normally do not appear under a customer's own phone — are flagged
	 * for registration if they somehow do.
	 *
	 * @param string $purchase_phone Raw phone the customer used when buying.
	 * @param array  $user_args      {user_id, phone}
	 * @return array|WP_Error
	 */
	public static function purchases_by_phone( $purchase_phone, $user_args ) {
		$canonical = AUN_App_Phone::normalize( $purchase_phone );
		if ( ! $canonical ) {
			return new WP_Error( 'invalid_phone', 'Invalid phone number.' );
		}
		if ( ! AUN_App_ERP::configured() ) {
			return new WP_Error( 'erp_not_configured', 'Purchase lookup is not available right now.' );
		}

		$sales = AUN_App_ERP::lookup_phone( $canonical );
		if ( is_wp_error( $sales ) ) {
			return $sales;
		}

		$out = array();
		foreach ( $sales as $sale ) {
			$serial = self::sanitize_serial( $sale['serial'] );
			if ( '' === $serial ) {
				continue;
			}
			// Returned units are not purchases (the ERP already omits them from
			// this list; this is belt and braces for older controllers).
			if ( ! empty( $sale['returned'] ) ) {
				continue;
			}
			$product = self::product_from_erp( $sale['product_id'], $sale['product_name'] );

			if ( AUN_App_ERP::is_dealer_sale( $sale ) ) {
				$out[] = array(
					'serial'     => $serial,
					'product'    => $product['name'],
					'sale_date'  => $sale['sale_date'],
					'invoice_no' => $sale['invoice_no'],
					'is_dealer'  => true,
					'linked'     => false,
					'needs_registration' => true,
				);
				continue;
			}

			// Direct purchase → link to this account. On the auto path (login/
			// refresh sync) honour the customer's earlier removal; a manual
			// find-by-phone clears any dismissal and re-links.
			$link = self::link_direct( array(
				'user_id'           => $user_args['user_id'],
				'phone'             => $user_args['phone'],
				'serial'            => $serial,
				'model'             => $product['name'],
				'purchase_date'     => $sale['sale_date'],
				'invoice_no'        => $sale['invoice_no'],
				'erp_contact_id'    => $sale['contact_id'],
				'erp_product_id'    => $sale['product_id'],
				'warranty_duration' => $sale['warranty_duration'] ?? null,
				'warranty_unit'     => $sale['warranty_unit'] ?? '',
				'auto'              => ! empty( $user_args['auto'] ),
			) );

			$out[] = array(
				'serial'     => $serial,
				'product'    => $product['name'],
				'sale_date'  => $sale['sale_date'],
				'invoice_no' => $sale['invoice_no'],
				'is_dealer'  => false,
				'linked'     => in_array( $link['code'], array( 'linked', 'exists' ), true ),
				'taken'      => 'owned_by_other' === $link['code'],
				'dismissed'  => 'dismissed' === $link['code'],
			);
		}

		return $out;
	}
}
