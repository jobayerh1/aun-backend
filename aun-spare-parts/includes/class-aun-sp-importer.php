<?php
/**
 * inFlow Inventory sales importer.
 *
 * Loads inFlow_SalesOrder.csv (6 years, ~5.4k orders) into the legacy_sales
 * archive. One CSV has several rows per order (one per line item) and multi-line
 * quoted product descriptions, so we MUST use a real CSV reader (fgetcsv), group
 * by OrderNumber, and only keep real sales (Paid / Invoiced — never Quote).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Importer {

	/** Order statuses that represent a genuine sale worth archiving. */
	const KEEP_STATUSES = array( 'Paid', 'Invoiced' );

	/**
	 * Import a CSV from an absolute path. Returns a stats array or WP_Error.
	 *
	 * @param string $path Absolute path to the inFlow CSV.
	 * @return array|WP_Error
	 */
	public static function import_csv( $path ) {
		global $wpdb;

		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'no_file', 'CSV file not found or not readable.' );
		}

		$fh = fopen( $path, 'r' );
		if ( ! $fh ) {
			return new WP_Error( 'open_fail', 'Could not open the CSV file.' );
		}

		// Consume a UTF-8 BOM if present. inFlow exports it before the first quote,
		// which otherwise stops fgetcsv recognising the opening quote — the first
		// header would arrive as the literal `"OrderNumber"` and never match.
		if ( fread( $fh, 3 ) !== "\xEF\xBB\xBF" ) {
			rewind( $fh );
		}

		$header = fgetcsv( $fh );
		if ( ! is_array( $header ) ) {
			fclose( $fh );
			return new WP_Error( 'empty', 'The CSV appears to be empty.' );
		}
		$idx = array_flip( array_map( 'trim', $header ) );

		foreach ( array( 'OrderNumber', 'OrderStatus', 'OrderDate', 'Phone', 'ItemName' ) as $col ) {
			if ( ! isset( $idx[ $col ] ) ) {
				fclose( $fh );
				return new WP_Error( 'bad_header', 'Missing expected column: ' . $col );
			}
		}

		$get = function ( $row, $col ) use ( $idx ) {
			return ( isset( $idx[ $col ] ) && isset( $row[ $idx[ $col ] ] ) ) ? trim( (string) $row[ $idx[ $col ] ] ) : '';
		};

		$orders  = array();
		$skipped = 0;

		while ( ( $row = fgetcsv( $fh ) ) !== false ) {
			$status = $get( $row, 'OrderStatus' );
			if ( ! in_array( $status, self::KEEP_STATUSES, true ) ) {
				$skipped++;
				continue;
			}
			$ono = $get( $row, 'OrderNumber' );
			if ( $ono === '' ) {
				$skipped++;
				continue;
			}

			if ( ! isset( $orders[ $ono ] ) ) {
				$address = trim( implode( ', ', array_filter( array(
					$get( $row, 'BillingAddress1' ),
					$get( $row, 'BillingAddress2' ),
					$get( $row, 'BillingCity' ),
					$get( $row, 'BillingState' ),
				) ) ) );

				$name = $get( $row, 'ContactName' );
				if ( $name === '' ) {
					$name = $get( $row, 'Customer' );
				}

				$orders[ $ono ] = array(
					'order_number'  => $ono,
					'customer_name' => $name,
					'phone_raw'     => $get( $row, 'Phone' ),
					'phone'         => aun_sp_normalize_phone( $get( $row, 'Phone' ) ),
					'email'         => $get( $row, 'Email' ),
					'address'       => $address,
					'city'          => $get( $row, 'BillingCity' ),
					'order_date'    => self::parse_date( $get( $row, 'OrderDate' ) ),
					'status'        => $status,
					'items'         => array(),
					'total'         => 0.0,
				);
			}

			// Keep only AUN products whose description carries a warranty — i.e. the
			// projectors/devices we actually service. This drops accessories (bags,
			// cables) and the unrelated non-AUN items present in the export.
			$item = $get( $row, 'ItemName' );
			$desc = $get( $row, 'ItemDescription' );
			if ( $item !== '' && false !== stripos( $item, 'AUN' ) && false !== stripos( $desc, 'warranty' ) ) {
				$orders[ $ono ]['items'][] = $item;
				$orders[ $ono ]['total']  += (float) preg_replace( '/[^0-9.\-]/', '', $get( $row, 'ItemSubtotal' ) );
			}
		}
		fclose( $fh );

		// Insert / update. One-time bulk job, so lift the time limit defensively.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		$table    = AUN_SP_Install::table( 'legacy_sales' );
		$now      = current_time( 'mysql' );
		$imported = 0;
		$dropped  = 0;

		foreach ( $orders as $o ) {
			$models = array_values( array_unique( array_filter( $o['items'] ) ) );

			// No qualifying AUN-with-warranty line in this order — not ours, skip it.
			if ( empty( $models ) ) {
				$dropped++;
				continue;
			}
			$primary = self::primary_model( $models );

			$ok = $wpdb->query( $wpdb->prepare(
				"INSERT INTO $table
					(order_number, customer_name, phone, phone_raw, email, address, city, order_date, status, primary_model, models, total, source, imported_at)
				 VALUES (%s,%s,%s,%s,%s,%s,%s, NULLIF(%s,''), %s,%s,%s,%f,'inflow',%s)
				 ON DUPLICATE KEY UPDATE
					customer_name=VALUES(customer_name), phone=VALUES(phone), phone_raw=VALUES(phone_raw),
					email=VALUES(email), address=VALUES(address), city=VALUES(city), order_date=VALUES(order_date),
					status=VALUES(status), primary_model=VALUES(primary_model), models=VALUES(models),
					total=VALUES(total), imported_at=VALUES(imported_at)",
				$o['order_number'], $o['customer_name'], $o['phone'], $o['phone_raw'], $o['email'],
				$o['address'], $o['city'], $o['order_date'], $o['status'],
				$primary, implode( ', ', $models ), $o['total'], $now
			) );

			if ( false !== $ok ) {
				$imported++;
			}
		}

		return array(
			'orders'   => count( $orders ),
			'imported' => $imported,
			'dropped'  => $dropped,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Parse an inFlow date ("5/5/2019 12:00:00 AM") into Y-m-d, or '' if unknown.
	 * inFlow exports in US M/D/Y order; try explicit formats first, then strtotime.
	 */
	public static function parse_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( $raw === '' ) {
			return '';
		}
		foreach ( array( 'n/j/Y g:i:s A', 'n/j/Y', 'm/d/Y g:i:s A', 'm/d/Y', 'Y-m-d' ) as $fmt ) {
			$dt = DateTime::createFromFormat( $fmt, $raw );
			if ( $dt instanceof DateTime ) {
				return $dt->format( 'Y-m-d' );
			}
		}
		$ts = strtotime( $raw );
		return $ts ? date( 'Y-m-d', $ts ) : '';
	}

	/**
	 * Pick the projector (not a bag/screen/remote/cable) as the order's primary
	 * model, so the warranty + parts flow is keyed to the actual device.
	 */
	public static function primary_model( array $models ) {
		if ( empty( $models ) ) {
			return '';
		}
		$accessory = array( 'bag', 'screen', 'remote', 'stand', 'cable', 'adapter', 'mount', 'tripod', 'cap', 'warranty' );
		foreach ( $models as $m ) {
			$lm  = strtolower( $m );
			$acc = false;
			foreach ( $accessory as $needle ) {
				if ( strpos( $lm, $needle ) !== false ) {
					$acc = true;
					break;
				}
			}
			if ( ! $acc ) {
				return $m;
			}
		}
		return $models[0];
	}
}
