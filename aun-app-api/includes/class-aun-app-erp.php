<?php
/**
 * Live UltimatePOS lookups for the app.
 *
 * Uses the SAME connection settings the hourly serial sync already uses
 * (option `slb_sync_settings` from the SLB ERP Sync plugin: erp_base_url,
 * erp_secret, dealer_group_id) — configure once, works everywhere.
 *
 * Requires AppLookupController.php installed on the ERP (see the ERP folder).
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_ERP {

	const CACHE_TTL = 600; // 10 min — box barcodes do not change often.

	/**
	 * Connection settings from the SLB ERP Sync plugin.
	 *
	 * @return array{base:string,secret:string,dealer_group_id:int}
	 */
	public static function settings() {
		$opts = get_option( 'slb_sync_settings', array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		return array(
			'base'            => untrailingslashit( trim( (string) ( $opts['erp_base_url'] ?? '' ) ) ),
			'secret'          => trim( (string) ( $opts['erp_secret'] ?? '' ) ),
			'dealer_group_id' => (int) ( $opts['dealer_group_id'] ?? 0 ),
		);
	}

	public static function configured() {
		$s = self::settings();
		return '' !== $s['base'] && '' !== $s['secret'];
	}

	/**
	 * GET an ERP app-lookup endpoint. Returns decoded array or WP_Error.
	 *
	 * @param string $path  e.g. '/api/app-lookup/serial'.
	 * @param array  $query Query args.
	 * @return array|WP_Error
	 */
	private static function get( $path, $query ) {
		$s = self::settings();
		if ( ! self::configured() ) {
			return new WP_Error( 'erp_not_configured', 'ERP connection is not configured.' );
		}

		$url = add_query_arg( array_map( 'rawurlencode', $query ), $s['base'] . $path );

		$args = array(
			'timeout' => 15,
			'headers' => array( 'X-Warranty-Secret' => $s['secret'] ),
		);

		// Keep the call on this machine when we can — the ERP lives here, and
		// going out to Cloudflare and back cost ~100 ms per call (and up to
		// 300 ms on a bad day). See AUN_App_Local_Route.
		$pinned   = class_exists( 'AUN_App_Local_Route' ) && AUN_App_Local_Route::arm( $url );
		$response = wp_remote_get( $url, $args );
		if ( $pinned ) {
			AUN_App_Local_Route::disarm();
		}

		// ⚠️ A pinned call that fails must NEVER be the customer's answer. The
		// shortcut is an optimisation; the public route is the contract. Back
		// off for ten minutes and ask again the normal way.
		if ( $pinned && is_wp_error( $response ) ) {
			AUN_App_Local_Route::note_failure( 'erp: ' . $response->get_error_message() );
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) || empty( $body['success'] ) ) {
			return new WP_Error( 'erp_error', 'ERP lookup failed (HTTP ' . $code . ').' );
		}

		return $body;
	}

	/**
	 * Look up one serial across ALL finalised sales (dealers + direct customers).
	 *
	 * @param string $serial Serial / 12-digit box barcode.
	 * @return array|false|WP_Error Sale row, false when not found, WP_Error on failure.
	 */
	public static function lookup_serial( $serial ) {
		$serial = strtoupper( trim( (string) $serial ) );
		if ( '' === $serial ) {
			return false;
		}

		$cache_key = 'aun_app_erp_s_' . md5( $serial );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return empty( $cached['__miss'] ) ? $cached : false;
		}

		$body = self::get( '/api/app-lookup/serial', array( 'serial' => $serial ) );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if ( empty( $body['found'] ) || empty( $body['data'] ) ) {
			set_transient( $cache_key, array( '__miss' => 1 ), self::CACHE_TTL );
			return false;
		}

		$row = self::sale_row( (array) $body['data'], $serial );

		set_transient( $cache_key, $row, self::CACHE_TTL );
		return $row;
	}

	/**
	 * Normalise one ERP sale row (serial + phone lookups share this shape).
	 *
	 * @param array  $d      Raw row from AppLookupController.
	 * @param string $serial Fallback serial.
	 * @return array
	 */
	private static function sale_row( $d, $serial = '' ) {
		return array(
			'serial'            => (string) ( $d['serial'] ?? $serial ),
			'sale_date'         => substr( (string) ( $d['sale_date'] ?? '' ), 0, 10 ),
			'invoice_no'        => (string) ( $d['invoice_no'] ?? '' ),
			'contact_id'        => (int) ( $d['contact_id'] ?? 0 ),
			'contact_name'      => (string) ( $d['contact_name'] ?? '' ),
			'contact_mobile'    => (string) ( $d['contact_mobile'] ?? '' ),
			'customer_group_id' => (int) ( $d['customer_group_id'] ?? 0 ),
			'product_id'        => (int) ( $d['product_id'] ?? 0 ),
			'product_name'      => (string) ( $d['product_name'] ?? '' ),
			'product_sku'       => (string) ( $d['product_sku'] ?? '' ),
			// Dust-filter maintenance eligibility (product_custom_field1 ===
			// 'MAINT_SMS', same rule as the ERP maintenance SMS service).
			'maintenance'       => ! empty( $d['maintenance'] ),
			// Product warranty exactly as the sales invoice prints it.
			'warranty_duration' => isset( $d['warranty_duration'] ) && null !== $d['warranty_duration']
				? (int) $d['warranty_duration'] : null,
			'warranty_unit'     => (string) ( $d['warranty_unit'] ?? '' ),
			// The unit was given back (ERP sell return, full line). The sale row
			// still exists — a return never unfinalises it — so this flag is the
			// only way to know. Older controllers omit it → false → old
			// behaviour, never a wrong unlink.
			'returned'          => ! empty( $d['returned'] ),
		);
	}

	/**
	 * Whether an ERP sale row belongs to the Dealers customer group.
	 *
	 * @param array $sale Row from lookup_serial().
	 * @return bool
	 */
	public static function is_dealer_sale( $sale ) {
		$s = self::settings();
		return $s['dealer_group_id'] > 0
			&& (int) $sale['customer_group_id'] === $s['dealer_group_id'];
	}

	/* --------------------------------------------------------------------- *
	 * Repair job-sheet tracking (UltimatePOS Repair module)
	 *
	 * Uses the SAME /api/repair-status endpoint the website repair tracker
	 * (slb-repair-tracker.php) already calls, so job sheets created by the
	 * service centre appear in the app automatically — no double entry.
	 * --------------------------------------------------------------------- */

	const REPAIR_CACHE_TTL = 120; // 2 min, matches the website tracker.

	/** Attachment types the app gallery can render. */
	const MEDIA_EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'webp', 'gif' );

	/**
	 * Safe media filename from an ERP document URL — '' when it is not an
	 * image we can show. basename() also blocks any path trickery.
	 *
	 * @param string $url ERP /uploads/media/... URL.
	 * @return string
	 */
	public static function media_filename( $url ) {
		// "?:" not "??" — parse_url() returns FALSE on a malformed URL.
		$path = parse_url( trim( (string) $url ), PHP_URL_PATH ) ?: '';
		$file = basename( (string) $path );
		if ( '' === $file ) {
			return '';
		}
		$ext = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );
		return in_array( $ext, self::MEDIA_EXTENSIONS, true ) ? $file : '';
	}

	/** Our own image proxy URL for one ERP media file. */
	public static function media_proxy_url( $file ) {
		// add_query_arg, not string concat: on sites without pretty permalinks
		// rest_url() already carries a "?rest_route=" query.
		return add_query_arg( 'file', rawurlencode( $file ), rest_url( AUN_APP_API_NS . '/repair-image' ) );
	}

	/**
	 * Fetch one ERP media file for the proxy.
	 *
	 * @param string $file Bare filename (already validated).
	 * @return array{ok:bool,type?:string,body?:string,status?:int}
	 */
	public static function fetch_media( $file ) {
		$s = self::settings();
		if ( '' === $s['base'] ) {
			return array( 'ok' => false, 'status' => 503 );
		}

		$url  = $s['base'] . '/uploads/media/' . rawurlencode( $file );
		$args = array( 'timeout' => 15 );

		// Keep it on this machine, exactly like get() and repair_get().
		//
		// ⚠️ This was MISSED when the local route was wired in, and it is the
		// call where it matters most: a repair screen fetches every job-sheet
		// photo through here, so one screen paid the Cloudflare round trip six
		// or seven times over - and these are the biggest payloads in the app,
		// so the connection setup is repeated on the slowest transfers.
		$pinned   = class_exists( 'AUN_App_Local_Route' ) && AUN_App_Local_Route::arm( $url );
		$response = wp_remote_get( $url, $args );
		if ( $pinned ) {
			AUN_App_Local_Route::disarm();
		}
		if ( $pinned && is_wp_error( $response ) ) {
			AUN_App_Local_Route::note_failure( 'media: ' . $response->get_error_message() );
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'status' => 502 );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array( 'ok' => false, 'status' => 404 );
		}

		// Only ever forward real images — never an HTML error page.
		$type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
		if ( ! in_array( $type, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
			return array( 'ok' => false, 'status' => 415 );
		}

		return array(
			'ok'   => true,
			'type' => $type,
			'body' => wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * API key for /api/repair-status (ERP env REPAIR_TRACK_API_KEY).
	 * Settings field first; falls back to the SLB_ERP_API_KEY constant the
	 * website tracker already defines in wp-config.php.
	 *
	 * @return string
	 */
	public static function repair_api_key() {
		$opts = aun_app_api_get_options();
		$key  = trim( (string) ( $opts['repair_api_key'] ?? '' ) );
		if ( '' === $key && defined( 'SLB_ERP_API_KEY' ) ) {
			$key = trim( (string) SLB_ERP_API_KEY );
		}
		return $key;
	}

	public static function repair_configured() {
		$s = self::settings();
		return '' !== $s['base'] && '' !== self::repair_api_key();
	}

	/**
	 * Fetch the latest repair job sheet from the ERP.
	 *
	 * @param array $query e.g. ['search_by'=>'mobile','query'=>'01XXXXXXXXX','serial'=>'...']
	 * @return array|false|WP_Error Normalised job sheet, false when none found.
	 */
	/**
	 * A courier consignment ID sitting inside an engineer's free-text note.
	 *
	 * The service centre types things like "Sent by Pathao DA200826WQJCJ5" into
	 * the job sheet. The website's repair tracker turns that into a tappable
	 * chip (slb-repair-tracker.php), and the app should do the same rather than
	 * showing a number the customer has to copy out by hand.
	 *
	 * ⚠️ The PATTERN mirrors the tracker plugin's JavaScript and must be kept
	 * in step with it: 2-3 letters, 6-8 digits, then 4-10 alphanumerics. If the
	 * two drift, the website and the app will disagree about whether the same
	 * note contains a trackable parcel - which is exactly the class of bug this
	 * codebase keeps finding.
	 *
	 * The URL itself is NOT built here: that belongs to
	 * AUN_App_Services::courier_tracking_url(), which the spare-parts plugin
	 * owns. Only the "find it in prose" step is new.
	 *
	 * @param string $note Free text.
	 * @return string The consignment ID, or '' when the note has none.
	 */
	public static function consignment_in( $note ) {
		$note = trim( (string) $note );
		if ( '' === $note ) {
			return '';
		}
		// A pasted tracking link wins: its ID is explicit, so there is nothing
		// to infer.
		if ( preg_match( '~consignment_id=([A-Za-z0-9]+)~i', $note, $m ) ) {
			return strtoupper( $m[1] );
		}
		// A bare ID typed into the sentence.
		if ( preg_match( '/\b([A-Za-z]{2,3}\d{6,8}[A-Za-z0-9]{4,10})\b/', $note, $m ) ) {
			return strtoupper( $m[1] );
		}
		return '';
	}

	private static function repair_get( $query ) {
		if ( ! self::repair_configured() ) {
			return new WP_Error( 'erp_not_configured', 'Repair tracking is not configured.' );
		}
		$s = self::settings();

		$cache_key = 'aun_app_erp_rp_' . md5( wp_json_encode( $query ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return empty( $cached['__miss'] ) ? $cached : false;
		}

		$url = add_query_arg( array_map( 'rawurlencode', $query ), $s['base'] . '/api/repair-status' );

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'X-API-KEY' => self::repair_api_key(),
				'Accept'    => 'application/json',
			),
		);

		// Same local shortcut as get() — this is the repair-status endpoint the
		// app hits every time somebody opens a repair.
		$pinned   = class_exists( 'AUN_App_Local_Route' ) && AUN_App_Local_Route::arm( $url );
		$response = wp_remote_get( $url, $args );
		if ( $pinned ) {
			AUN_App_Local_Route::disarm();
		}
		if ( $pinned && is_wp_error( $response ) ) {
			AUN_App_Local_Route::note_failure( 'repair: ' . $response->get_error_message() );
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 404 === $code || ( is_array( $body ) && empty( $body['success'] ) && 200 !== $code ) ) {
			// "No record found" — cache the miss briefly so list views stay fast.
			set_transient( $cache_key, array( '__miss' => 1 ), self::REPAIR_CACHE_TTL );
			return false;
		}
		if ( 200 !== $code || ! is_array( $body ) || empty( $body['success'] ) || empty( $body['data'] ) ) {
			return new WP_Error( 'erp_error', 'Repair lookup failed (HTTP ' . $code . ').' );
		}

		$d          = (array) $body['data'];
		$activities = array();
		foreach ( (array) ( $d['activities'] ?? array() ) as $a ) {
			$a            = (array) $a;
			$note         = (string) ( $a['note'] ?? '' );
			$consignment  = self::consignment_in( $note );
			$activities[] = array(
				'date'   => (string) ( $a['date'] ?? '' ),
				'action' => (string) ( $a['action'] ?? '' ),
				'by'     => (string) ( $a['by'] ?? '' ),
				'note'   => $note,
				// A courier consignment the engineer typed into the note, pulled
				// out so the app can offer a tap instead of a number to copy.
				// Empty for the overwhelming majority of notes.
				'tracking'     => $consignment,
				'tracking_url' => '' === $consignment
					? ''
					: AUN_App_Services::courier_tracking_url( $consignment ),
			);
		}

		$problems = array();
		foreach ( (array) ( $d['problem_reported'] ?? array() ) as $p ) {
			if ( is_string( $p ) && '' !== trim( $p ) ) {
				$problems[] = trim( $p );
			}
		}

		// Job-sheet attachments. The ERP hands back raw /uploads/media/ URLs on
		// the portal domain; we hand the app OUR proxy instead — the ERP host
		// is never exposed to the phone, and non-image attachments (PDFs and
		// the like live in the same media table) are dropped so the gallery
		// never shows a broken tile.
		$documents = array();
		foreach ( (array) ( $d['documents'] ?? array() ) as $doc ) {
			$file = self::media_filename( (string) $doc );
			if ( '' !== $file ) {
				$documents[] = self::media_proxy_url( $file );
			}
		}

		$row = array(
			'job_sheet_no'   => (string) ( $d['job_sheet_no'] ?? '' ),
			'status'         => (string) ( $d['status'] ?? '' ),
			// The business's own "this status means finished" flag
			// (repair_statuses.is_completed_status) — never inferred from names.
			'completed'      => ! empty( $d['status_completed'] ),
			'serial_number'  => (string) ( $d['serial_number'] ?? '' ),
			'device_model'   => (string) ( $d['device_model'] ?? '' ),
			'customer_name'  => (string) ( $d['customer_name'] ?? '' ),
			'estimated_cost' => (string) ( $d['estimated_cost'] ?? '' ),
			'warranty'       => (string) ( $d['warranty'] ?? '' ),
			'created_at'     => (string) ( $d['created_at'] ?? '' ),
			'delivery_date'  => (string) ( $d['delivery_date'] ?? '' ),
			'problems'       => $problems,
			'documents'      => $documents,
			'activities'     => $activities,
		);

		set_transient( $cache_key, $row, self::REPAIR_CACHE_TTL );
		return $row;
	}

	/**
	 * Latest job sheet for a phone (optionally narrowed to one serial).
	 *
	 * @param string $canonical 8801XXXXXXXXX (converted to the local 01… form
	 *                          the ERP contact records use).
	 * @param string $serial    Optional exact serial filter.
	 * @return array|false|WP_Error
	 */
	public static function repair_by_phone( $canonical, $serial = '' ) {
		$local = '0' . substr( (string) $canonical, 3 ); // 8801X… → 01X…
		if ( ! preg_match( '/^01\d{9}$/', $local ) ) {
			return false;
		}
		$query = array(
			'search_by' => 'mobile',
			'query'     => $local,
		);
		if ( '' !== $serial ) {
			$query['serial'] = $serial;
		}
		return self::repair_get( $query );
	}

	/**
	 * Latest job sheet for a device serial — the strongest link: the serial is
	 * unique per unit and always entered on the job sheet, so it matches even
	 * when the job sheet was created under a different customer contact.
	 * Requires search_by=serial support in RepairStatusApiController (v1.7+).
	 *
	 * @param string $serial Device serial / box barcode.
	 * @return array|false|WP_Error
	 */
	public static function repair_by_serial( $serial ) {
		$serial = trim( (string) $serial );
		if ( '' === $serial ) {
			return false;
		}
		return self::repair_get( array(
			'search_by' => 'serial',
			'query'     => $serial,
		) );
	}

	/**
	 * One job sheet by its number (e.g. 2025/0031).
	 *
	 * @param string $job_no Job sheet number.
	 * @return array|false|WP_Error
	 */
	public static function repair_by_job_sheet( $job_no ) {
		$job_no = trim( (string) $job_no );
		if ( '' === $job_no ) {
			return false;
		}
		return self::repair_get( array(
			'search_by' => 'job_sheet',
			'query'     => $job_no,
		) );
	}

	/**
	 * Every serial sold to a customer phone number (direct purchases).
	 *
	 * @param string $canonical 8801XXXXXXXXX.
	 * @return array[]|WP_Error List of sale rows (same shape as lookup_serial).
	 */
	public static function lookup_phone( $canonical ) {
		$local10 = substr( (string) $canonical, 3 ); // 1XXXXXXXXX — matches any stored format.

		$body = self::get( '/api/app-lookup/phone', array( 'phone' => $local10 ) );
		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$out = array();
		foreach ( (array) ( $body['data'] ?? array() ) as $d ) {
			$out[] = self::sale_row( (array) $d );
		}

		return $out;
	}
}
