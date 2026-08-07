<?php
/**
 * Direct SSLCommerz hosted checkout for the app.
 *
 * ## Why this exists
 *
 * The first version sent the app to WooCommerce's own "Pay for order" page and
 * auto-submitted it. That worked, but the customer passed through three pages
 * of website — a method chooser, a terms checkbox, an intermediate redirect —
 * and we ended up hiding them behind an overlay, ticking a checkbox on the
 * customer's behalf, and depending on the theme calling `wp_body_open`. Four
 * moving parts to conceal a page nobody wanted to see.
 *
 * SSLCommerz, like every serious gateway, offers a **hosted session**: the
 * server posts the order to `gwprocess/v4/api.php` and receives a
 * `GatewayPageURL` that leads straight to the payment screen. The app opens
 * that. No AUN pages in between, nothing to hide, no overlay.
 *
 * ## What this deliberately does NOT do
 *
 * It does not replace the WooCommerce order. The order stays the single record
 * of the money: the spare-parts plugin reads it for the receipt SMS, the
 * activity log, the admin's "paid" badge and the refund trail. Only the
 * checkout PAGE is bypassed. Building a payment system "separate from
 * WooCommerce" would mean two ledgers that have to be reconciled by hand
 * forever, and the first refund would prove why that is a bad trade.
 *
 * It also does not put credentials in the app. They are read here, on the
 * server, from the WooCommerce SSLCommerz gateway that is already configured.
 *
 * ## The rule that matters
 *
 * A redirect back from a gateway is a claim by the customer's browser, not a
 * receipt. Nothing is EVER marked paid on a redirect. The order is settled
 * only after `validate()` confirms the transaction server-to-server AND the
 * amount and currency match what we asked for.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_SSLCommerz {

	const LIVE_SESSION  = 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';
	const LIVE_VALIDATE = 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';
	const TEST_SESSION  = 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php';
	const TEST_VALIDATE = 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php';

	/** Order meta: our transaction id, so a callback can find its order. */
	const META_TRAN = '_aun_app_sslc_tran';

	/* --------------------------------------------------------------------- *
	 * Credentials
	 * --------------------------------------------------------------------- */

	/**
	 * Store credentials, taken from the WooCommerce SSLCommerz gateway.
	 *
	 * Read from whatever gateway is already installed rather than asking the
	 * admin to type them a second time: two copies of a credential drift, and
	 * the copy nobody remembers updating is the one that breaks at midnight.
	 * An explicit override exists in AUN App settings for the case where the
	 * gateway stores them somewhere we cannot guess.
	 *
	 * @return array{store_id:string,store_pass:string,sandbox:bool,source:string}
	 */
	public static function credentials() {
		$o = aun_app_api_get_options();

		// 1. Explicit override, if the admin set one.
		$id   = trim( (string) ( $o['sslc_store_id'] ?? '' ) );
		$pass = trim( (string) ( $o['sslc_store_pass'] ?? '' ) );
		if ( '' !== $id && '' !== $pass ) {
			return array(
				'store_id'   => $id,
				'store_pass' => $pass,
				'sandbox'    => ! empty( $o['sslc_sandbox'] ),
				'source'     => 'AUN App settings',
			);
		}

		// 2. The installed WooCommerce gateway. Gateway ids vary between the
		// official plugin and the several community forks, so match on the
		// option NAME rather than hardcoding one id.
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT option_name, option_value FROM $wpdb->options
			  WHERE option_name LIKE 'woocommerce_%_settings'
			    AND option_name LIKE '%sslcommerz%'"
		);
		foreach ( (array) $rows as $row ) {
			$s = maybe_unserialize( $row->option_value );
			if ( ! is_array( $s ) ) {
				continue;
			}
			$found_id   = self::pick( $s, array( 'store_id', 'storeid', 'store_ID', 'sslc_store_id' ) );
			$found_pass = self::pick( $s, array( 'store_passwd', 'store_password', 'storepassword', 'store_pass', 'sslc_store_password' ) );
			if ( '' !== $found_id && '' !== $found_pass ) {
				$sandbox = false;
				foreach ( array( 'sandbox', 'is_sandbox', 'test_mode', 'testmode' ) as $k ) {
					if ( isset( $s[ $k ] ) ) {
						$sandbox = in_array( $s[ $k ], array( 'yes', '1', 1, true ), true );
						break;
					}
				}
				return array(
					'store_id'   => $found_id,
					'store_pass' => $found_pass,
					'sandbox'    => $sandbox,
					'source'     => $row->option_name,
				);
			}
		}

		return array( 'store_id' => '', 'store_pass' => '', 'sandbox' => false, 'source' => '' );
	}

	/** First non-empty value among candidate keys. */
	private static function pick( $arr, $keys ) {
		foreach ( $keys as $k ) {
			if ( ! empty( $arr[ $k ] ) && is_string( $arr[ $k ] ) ) {
				return trim( $arr[ $k ] );
			}
		}
		return '';
	}

	/** Whether a direct gateway session can be created at all. */
	public static function available() {
		$c = self::credentials();
		return '' !== $c['store_id'] && '' !== $c['store_pass'];
	}

	/* --------------------------------------------------------------------- *
	 * Starting a payment
	 * --------------------------------------------------------------------- */

	/**
	 * Ask SSLCommerz for a hosted payment page for this order.
	 *
	 * @param WC_Order $order Order to be paid.
	 * @param string   $ref   Spare-parts reference, for the customer's statement.
	 * @return string|WP_Error GatewayPageURL.
	 */
	public static function create_session( $order, $ref = '' ) {
		$c = self::credentials();
		if ( ! self::available() ) {
			return new WP_Error( 'no_credentials', 'SSLCommerz is not configured.' );
		}

		// A transaction id that is unique per ATTEMPT, not per order. A customer
		// who abandons a payment and starts again must not reuse the id of the
		// abandoned one, or the gateway rejects the second attempt as a
		// duplicate — a real and very confusing failure.
		$tran_id = 'AUN' . $order->get_id() . 'X' . strtoupper( wp_generate_password( 6, false, false ) );
		$order->update_meta_data( self::META_TRAN, $tran_id );
		$order->save();

		$args = array(
			'store_id'    => $c['store_id'],
			'store_passwd' => $c['store_pass'],
			'total_amount' => number_format( (float) $order->get_total(), 2, '.', '' ),
			'currency'    => $order->get_currency() ? $order->get_currency() : 'BDT',
			'tran_id'     => $tran_id,

			// Where the GATEWAY sends the customer's browser afterwards. These
			// are ours, and they only ever trigger a validation — they are never
			// treated as proof of payment.
			'success_url' => self::callback_url( 'success' ),
			'fail_url'    => self::callback_url( 'fail' ),
			'cancel_url'  => self::callback_url( 'cancel' ),
			// Server-to-server notification, which arrives even if the customer
			// closes the app mid-payment. This is the one that really matters.
			'ipn_url'     => self::callback_url( 'ipn' ),

			'cus_name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'cus_email'   => $order->get_billing_email() ? $order->get_billing_email() : 'app@' . wp_parse_url( home_url(), PHP_URL_HOST ),
			'cus_phone'   => $order->get_billing_phone(),
			'cus_add1'    => $order->get_billing_address_1() ? $order->get_billing_address_1() : 'N/A',
			'cus_city'    => $order->get_billing_city() ? $order->get_billing_city() : 'Dhaka',
			'cus_country' => 'Bangladesh',

			'shipping_method' => 'NO',
			'product_name'    => '' !== $ref ? 'Spare parts ' . $ref : 'AUN spare parts',
			'product_category' => 'Spare parts',
			'product_profile' => 'physical-goods',

			// Our own breadcrumb, echoed back on every callback.
			'value_a'     => (string) $order->get_id(),
			'value_b'     => (string) $ref,
		);

		$res = wp_remote_post(
			$c['sandbox'] ? self::TEST_SESSION : self::LIVE_SESSION,
			array( 'timeout' => 30, 'body' => $args )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'bad_response', 'The payment gateway returned something unreadable.' );
		}
		if ( empty( $body['GatewayPageURL'] ) ) {
			return new WP_Error(
				'session_failed',
				(string) ( $body['failedreason'] ?? 'The payment gateway refused to start a session.' )
			);
		}

		return (string) $body['GatewayPageURL'];
	}

	/** Public callback URL for one gateway outcome. */
	public static function callback_url( $what ) {
		return add_query_arg( 'aun_sslc', $what, home_url( '/' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Finishing a payment
	 * --------------------------------------------------------------------- */

	/**
	 * Confirm a transaction with SSLCommerz, server to server.
	 *
	 * @param string $val_id Validation id supplied by the gateway.
	 * @return array|WP_Error Validation payload.
	 */
	public static function validate( $val_id ) {
		$c = self::credentials();
		if ( ! self::available() || '' === $val_id ) {
			return new WP_Error( 'no_credentials', 'Cannot validate this transaction.' );
		}

		$url = add_query_arg(
			array(
				'val_id'       => $val_id,
				'store_id'     => $c['store_id'],
				'store_passwd' => $c['store_pass'],
				'format'       => 'json',
			),
			$c['sandbox'] ? self::TEST_VALIDATE : self::LIVE_VALIDATE
		);

		$res = wp_remote_get( $url, array( 'timeout' => 30 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'bad_response', 'The gateway returned something unreadable.' );
		}
		return $body;
	}

	/**
	 * Settle an order from a validated transaction.
	 *
	 * Every check here exists because skipping it is a known way to be robbed:
	 *
	 *  • status must be VALID or VALIDATED — anything else is not a payment;
	 *  • the AMOUNT must match the order, or a customer can pay ৳1 for a
	 *    ৳15,000 order by editing the request on the way to the gateway;
	 *  • the CURRENCY must match, for the same reason with a different unit;
	 *  • the transaction id must be the one we issued for THIS order;
	 *  • and an order already paid is left alone, so a replayed callback or a
	 *    duplicate IPN cannot double-count.
	 *
	 * @param array $data Validation payload from validate().
	 * @return true|WP_Error
	 */
	public static function settle( $data ) {
		$status = strtoupper( (string) ( $data['status'] ?? '' ) );
		if ( ! in_array( $status, array( 'VALID', 'VALIDATED' ), true ) ) {
			return new WP_Error( 'not_valid', 'Transaction status is ' . $status );
		}

		$order_id = (int) ( $data['value_a'] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return new WP_Error( 'no_order', 'No order for this transaction.' );
		}

		if ( $order->is_paid() ) {
			return true; // Already settled — a repeat callback is not an error.
		}

		$tran_id = (string) ( $data['tran_id'] ?? '' );
		$ours    = (string) $order->get_meta( self::META_TRAN );
		if ( '' === $tran_id || $tran_id !== $ours ) {
			return new WP_Error( 'tran_mismatch', 'Transaction id does not belong to this order.' );
		}

		$paid     = (float) ( $data['amount'] ?? 0 );
		$expected = (float) $order->get_total();
		if ( abs( $paid - $expected ) > 0.01 ) {
			$order->add_order_note( sprintf(
				'AUN app: SSLCommerz reported %s but this order is %s. NOT marked paid.',
				wc_price( $paid ),
				wc_price( $expected )
			) );
			return new WP_Error( 'amount_mismatch', 'Amount does not match the order.' );
		}

		$currency = strtoupper( (string) ( $data['currency'] ?? 'BDT' ) );
		if ( $currency !== strtoupper( $order->get_currency() ) ) {
			return new WP_Error( 'currency_mismatch', 'Currency does not match the order.' );
		}

		// Everything checks out. payment_complete() is what the rest of the
		// system listens to — the spare-parts plugin's receipt SMS, the
		// activity log and the admin badge all hang off it.
		$order->set_payment_method( 'sslcommerz' );
		$order->set_payment_method_title( (string) ( $data['card_issuer'] ?? 'SSLCommerz' ) );
		$order->payment_complete( $tran_id );
		$order->add_order_note( sprintf(
			'AUN app: paid via SSLCommerz (%s), transaction %s.',
			(string) ( $data['card_type'] ?? '' ),
			$tran_id
		) );

		return true;
	}
}
