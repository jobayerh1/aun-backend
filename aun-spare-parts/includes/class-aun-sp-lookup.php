<?php
/**
 * Sales lookup + warranty calculation.
 *
 * A customer may have bought several times — some sales in the live ERP (Nov 2025
 * onward), some in the legacy inFlow archive (2019 -> Nov 2025). find_all() searches
 * BOTH, merges and de-duplicates, and returns every matching purchase newest-first
 * so the form can show a "which device?" chooser. Each match carries its computed
 * warranty.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Lookup {

	/**
	 * @return array { found:bool, matches:array<int, match> }
	 *   match = { source, order_number, model, purchase_date, customer_name, phone, address, warranty }
	 */
	public static function find_all( $search_by, $query ) {
		$search_by = in_array( $search_by, array( 'mobile', 'order', 'serial' ), true ) ? $search_by : 'mobile';
		$query     = trim( (string) $query );
		if ( $query === '' ) {
			return array( 'found' => false, 'matches' => array() );
		}

		if ( 'mobile' === $search_by ) {
			$q = aun_sp_normalize_phone( $query );
		} elseif ( 'serial' === $search_by ) {
			// Serials are alphanumeric; keep letters (stripping them corrupted real
			// serials) and require at least 4 characters — a 1-2 char "serial" would
			// LIKE-match half the ERP and let anyone enumerate customer purchases.
			$q = preg_replace( '/[^A-Za-z0-9]/', '', $query );
			if ( strlen( $q ) < 4 ) {
				return array( 'found' => false, 'matches' => array() );
			}
		} else {
			$q = $query;
		}
		if ( $q === '' ) {
			return array( 'found' => false, 'matches' => array() );
		}

		// ERP first (authoritative for recent sales), then legacy.
		$sales = array_merge( self::query_erp( $search_by, $q ), self::query_legacy( $search_by, $q ) );

		// De-duplicate by order number (a sale in the Nov-2025 overlap could appear in
		// both sources); the ERP copy wins because it was added first.
		$seen    = array();
		$matches = array();
		foreach ( $sales as $sale ) {
			$dedupe = strtolower( trim( (string) ( $sale['order_number'] ?? '' ) ) );
			if ( $dedupe !== '' ) {
				if ( isset( $seen[ $dedupe ] ) ) {
					continue;
				}
				$seen[ $dedupe ] = true;
			}
			$matches[] = self::decorate( $sale );
		}

		// Newest purchase first.
		usort( $matches, function ( $a, $b ) {
			return strcmp( (string) $b['purchase_date'], (string) $a['purchase_date'] );
		} );

		return array( 'found' => ! empty( $matches ), 'matches' => $matches );
	}

	/** Convenience: the single best (most recent) match, or { found:false }. */
	public static function find( $search_by, $query ) {
		$r = self::find_all( $search_by, $query );
		if ( empty( $r['found'] ) ) {
			return array( 'found' => false );
		}
		$best          = $r['matches'][0];
		$best['found'] = true;
		return $best;
	}

	/** Legacy inFlow archive. Mobile can return many rows; order returns one. */
	protected static function query_legacy( $search_by, $q ) {
		global $wpdb;
		$t = AUN_SP_Install::table( 'legacy_sales' );

		if ( 'order' === $search_by ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE order_number = %s", $q ) );
		} elseif ( 'mobile' === $search_by ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE phone = %s ORDER BY order_date DESC", $q ) );
		} else {
			return array(); // inFlow archive has no serials
		}

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'source'        => 'legacy',
				'order_number'  => $row->order_number,
				'model'         => $row->primary_model !== '' ? $row->primary_model : (string) $row->models,
				'purchase_date' => (string) $row->order_date,
				'customer_name' => $row->customer_name,
				'phone'         => $row->phone,
				'address'       => (string) $row->address,
			);
		}
		return $out;
	}

	/**
	 * Live ERP via /api/sales-lookup. Runs server-side, so the secret never reaches
	 * the browser. Returns array() if the endpoint isn't deployed / key unset.
	 */
	protected static function query_erp( $search_by, $q ) {
		$key = defined( 'AUN_SP_ERP_API_KEY' ) ? AUN_SP_ERP_API_KEY : '';
		if ( $key === '' ) {
			return array();
		}

		$url  = trailingslashit( AUN_SP_ERP_URL ) . 'sales-lookup';
		$resp = wp_remote_get(
			add_query_arg( array( 'search_by' => $search_by, 'query' => $q ), $url ),
			array(
				'timeout' => 12,
				'headers' => array( 'X-API-KEY' => $key ),
			)
		);

		if ( is_wp_error( $resp ) ) {
			error_log( 'AUN SP: ERP lookup error: ' . $resp->get_error_message() );
			return array();
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['success'] ) || empty( $body['data'] ) ) {
			return array();
		}

		// The endpoint returns a list of sales (a customer may have several).
		$rows = $body['data'];
		if ( isset( $rows['order_number'] ) || isset( $rows['invoice_no'] ) ) {
			$rows = array( $rows ); // tolerate a single-object response too
		}

		$out = array();
		foreach ( (array) $rows as $d ) {
			$out[] = array(
				'source'        => 'erp',
				'order_number'  => $d['invoice_no'] ?? ( $d['order_number'] ?? '' ),
				'model'         => $d['product_name'] ?? ( $d['model'] ?? '' ),
				'purchase_date' => isset( $d['sale_date'] ) ? substr( (string) $d['sale_date'], 0, 10 ) : ( $d['purchase_date'] ?? '' ),
				'customer_name' => $d['customer_name'] ?? '',
				'phone'         => $d['mobile'] ?? '',
				'address'       => $d['address'] ?? '',
			);
		}
		return $out;
	}

	/** Attach the computed warranty to a raw sale row. */
	protected static function decorate( array $sale ) {
		return array(
			'source'        => $sale['source'] ?? 'legacy',
			'order_number'  => $sale['order_number'] ?? '',
			'model'         => $sale['model'] ?? '',
			'purchase_date' => $sale['purchase_date'] ?? '',
			'customer_name' => $sale['customer_name'] ?? '',
			'phone'         => $sale['phone'] ?? '',
			'address'       => $sale['address'] ?? '',
			'warranty'      => self::warranty( $sale['purchase_date'] ?? '' ),
		);
	}

	/**
	 * Warranty = purchase date + N months + grace days (grace covers the 2-3 day
	 * online-delivery gap). Both N and grace are admin settings.
	 *
	 * @return array { known:bool, in_warranty:bool, ends:Y-m-d, label }
	 */
	public static function warranty( $purchase_date ) {
		$purchase_date = trim( (string) $purchase_date );
		$months        = (int) get_option( 'aun_sp_warranty_months', 12 );
		$grace         = (int) get_option( 'aun_sp_warranty_grace_days', 4 );

		if ( $purchase_date === '' || $purchase_date === '0000-00-00' ) {
			return array( 'known' => false, 'in_warranty' => false, 'ends' => '', 'label' => 'Purchase date unknown' );
		}

		try {
			$end = new DateTime( $purchase_date );
		} catch ( \Exception $e ) {
			return array( 'known' => false, 'in_warranty' => false, 'ends' => '', 'label' => 'Purchase date unknown' );
		}

		$end->modify( '+' . $months . ' months' )->modify( '+' . $grace . ' days' );
		$today = new DateTime( current_time( 'Y-m-d' ) );
		$in    = ( $today <= $end );

		return array(
			'known'       => true,
			'in_warranty' => $in,
			'ends'        => $end->format( 'Y-m-d' ),
			'label'       => $in ? 'Yes — in warranty' : 'No — out of warranty',
		);
	}
}
