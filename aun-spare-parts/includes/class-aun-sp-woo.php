<?php
/**
 * WooCommerce bridge — ONLINE PAYMENT ONLY.
 *
 * FLOW (revised 2026-08-06)
 * ------------------------
 * Approving a quote does NOT create an order any more. The request simply becomes
 * "approved" and is worked exactly as it always was — cash on delivery is the
 * default and needs no WooCommerce involvement at all.
 *
 * A WooCommerce order is minted only when the customer actively chooses to pay
 * online, and it is created fresh at that moment so the amount can never be stale.
 * Cash on delivery is therefore removed from the gateway list on those orders:
 * an order here means "this customer is paying online".
 *
 * STATUS OWNERSHIP
 * ----------------
 * The spare-parts request is the master record; the order follows it, never the
 * other way round. Payment moves the order to `processing`; the request reaching
 * "Completed" moves it to `completed`; rejecting/declining cancels an unpaid one.
 * The admin never has to touch WooCommerce order statuses.
 *
 * THIRD-PARTY SAFETY
 * ------------------
 * Line items are backed by one hidden placeholder product. Product-less line items
 * are legal in WooCommerce but crash gateway/shipping plugins that assume
 * `$item->get_product()` returns an object — which is what broke the SSLCommerz
 * return URL. The placeholder is private, catalogue-hidden and stock-free, and each
 * line still carries the real part name and price.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Woo {

	const CREATED_VIA  = 'aun-spare-parts';
	const META_REF     = '_aun_sp_ref';
	const META_REQ     = '_aun_sp_request_id';
	const OPT_PRODUCT  = 'aun_sp_placeholder_product';

	public static function init() {
		add_action( 'before_woocommerce_init', function () {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', AUN_SP_FILE, true );
			}
		} );

		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 4 );
		// Piggy-backs the existing daily cron.
		add_action( 'aun_sp_daily_digest', array( __CLASS__, 'cancel_abandoned' ) );

		// Paying an AUN order always lands on "processing" — never "completed" and
		// never a custom status another plugin might prefer. Only the spare-parts
		// request marking itself Completed may complete the order.
		add_filter( 'woocommerce_payment_complete_order_status', array( __CLASS__, 'force_processing' ), 99, 3 );

		// Cash on delivery is not a choice here: an order exists precisely because
		// the customer chose to pay online.
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'filter_gateways' ), 99 );

		if ( is_admin() ) {
			add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'order_column' ) );
			add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'order_column_value' ), 10, 2 );
			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'order_column' ) );
			add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'order_column_value' ), 10, 2 );
		}
	}

	public static function is_active() {
		return function_exists( 'wc_create_order' ) && function_exists( 'wc_get_order' );
	}

	/** True when this order was raised by us. */
	private static function is_ours( $order ) {
		return ( $order instanceof WC_Order ) && (int) $order->get_meta( self::META_REQ ) > 0;
	}

	/* ------------------------------------------------------- Gateways / statuses */

	public static function force_processing( $status, $order_id, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		return self::is_ours( $order ) ? 'processing' : $status;
	}

	/** Strip cash on delivery from the pay page of an AUN order. */
	public static function filter_gateways( $gateways ) {
		if ( is_admin() || empty( $gateways ) ) {
			return $gateways;
		}
		$order_id = 0;
		if ( isset( $GLOBALS['wp']->query_vars['order-pay'] ) ) {
			$order_id = absint( $GLOBALS['wp']->query_vars['order-pay'] );
		}
		if ( ! $order_id ) {
			return $gateways;
		}
		$order = wc_get_order( $order_id );
		if ( self::is_ours( $order ) ) {
			unset( $gateways['cod'] );
		}
		return $gateways;
	}

	/* ------------------------------------------------------------------ Creating */

	/**
	 * The single hidden product that backs every spare-part line item, so
	 * third-party gateway/shipping code never meets a null product.
	 * Private + catalogue-hidden + no stock: it never appears in the shop.
	 */
	private static function placeholder_product_id() {
		$id = (int) get_option( self::OPT_PRODUCT, 0 );
		if ( $id && ( $p = wc_get_product( $id ) ) && $p->get_status() !== 'trash' ) {
			return $id;
		}
		$product = new WC_Product_Simple();
		$product->set_name( 'AUN spare part' );
		$product->set_status( 'private' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_manage_stock( false );
		$product->set_sold_individually( false );
		$product->set_regular_price( 0 );
		$product->set_description( 'Internal placeholder used by the AUN Spare Parts plugin. Not for sale — do not delete.' );
		$id = $product->save();
		update_option( self::OPT_PRODUCT, $id );
		return (int) $id;
	}

	/**
	 * Create — or refresh — the payment order for a request.
	 *
	 * Called when the customer chooses to pay online. An existing UNPAID order is
	 * rebuilt from the current parts/prices rather than reused as-is, so editing a
	 * quantity or price in the admin can never leave the customer paying a stale
	 * amount. A paid order is never touched.
	 *
	 * @return int order id, or 0.
	 */
	public static function create_order( $request_id ) {
		global $wpdb;
		if ( ! self::is_active() ) {
			return 0;
		}
		$t_req  = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );
		$r      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", (int) $request_id ) );
		if ( ! $r ) {
			return 0;
		}

		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT part_label, qty, unit_price FROM $t_item WHERE request_id = %d AND unit_price > 0 ORDER BY id ASC",
			(int) $request_id
		) );
		if ( empty( $items ) ) {
			return 0; // nothing chargeable
		}

		$order    = null;
		$existing = isset( $r->wc_order_id ) ? (int) $r->wc_order_id : 0;
		if ( $existing ) {
			$o = wc_get_order( $existing );
			if ( $o && $o->is_paid() ) {
				return $existing; // already settled — never rebuild
			}
			if ( $o && ! in_array( $o->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) ) {
				$order = $o; // reuse the shell, refresh its contents below
			}
		}
		if ( ! $order ) {
			$order = wc_create_order( array( 'status' => 'pending' ) );
			if ( is_wp_error( $order ) || ! $order ) {
				error_log( 'AUN SP: could not create WooCommerce order for request ' . (int) $request_id );
				return 0;
			}
		}

		// Wipe any previous lines so the rebuild is authoritative.
		foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $item_id => $item ) {
			$order->remove_item( $item_id );
		}

		$name  = trim( (string) $r->customer_name );
		$space = strpos( $name, ' ' );
		$addr  = array(
			'first_name' => false === $space ? $name : substr( $name, 0, $space ),
			'last_name'  => false === $space ? '' : trim( substr( $name, $space ) ),
			'address_1'  => (string) $r->address_current,
			'city'       => '',
			'country'    => 'BD',
			'phone'      => (string) $r->phone_current,
		);
		$order->set_address( $addr, 'billing' );
		$order->set_address( $addr, 'shipping' );

		$product_id = self::placeholder_product_id();
		foreach ( $items as $it ) {
			$qty  = max( 1, (int) $it->qty );
			$line = round( (float) $it->unit_price * $qty, 2 );

			$item = new WC_Order_Item_Product();
			$item->set_product_id( $product_id ); // real product => gateway plugins are safe
			$item->set_name( $it->part_label );   // but the customer sees the real part
			$item->set_quantity( $qty );
			$item->set_subtotal( $line );
			$item->set_total( $line );
			$item->add_meta_data( 'Spare part for', (string) $r->model, true );
			$order->add_item( $item );
		}

		$charge = isset( $r->delivery_charge ) ? round( (float) $r->delivery_charge, 2 ) : 0.0;
		if ( $charge > 0 ) {
			$ship = new WC_Order_Item_Shipping();
			$ship->set_method_title( 'Delivery' );
			$ship->set_total( $charge );
			$order->add_item( $ship );
		}

		$order->update_meta_data( self::META_REF, $r->ref );
		$order->update_meta_data( self::META_REQ, (int) $request_id );
		$order->set_created_via( self::CREATED_VIA );
		$order->calculate_totals( false ); // no tax on an already-approved figure

		// PRIVATE note. A customer-facing note here was being picked up by the SMS
		// plugin and sent as a second, unwanted message — every customer SMS must
		// come from this plugin so it lands in the request's activity log.
		$order->add_order_note( 'Spare-parts request ' . $r->ref . ' — online payment. Lead time: in-stock parts ship quickly, factory orders take 3-4 weeks.' );
		$order->save();

		$order_id = $order->get_id();
		if ( (int) $existing !== (int) $order_id ) {
			$wpdb->update( $t_req, array( 'wc_order_id' => $order_id, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $request_id ) );
			self::log( $request_id, 'wc_order', 'Online payment order #' . $order->get_order_number() . ' created for ৳' . number_format_i18n( (float) $order->get_total(), 2 ) );
		} else {
			self::log( $request_id, 'wc_order', 'Online payment order #' . $order->get_order_number() . ' refreshed — now ৳' . number_format_i18n( (float) $order->get_total(), 2 ) );
		}
		return $order_id;
	}

	/** Keep an unpaid order's delivery charge in step with the admin's edit. */
	public static function sync_delivery_charge( $request_id, $charge ) {
		$order = self::order_for( $request_id );
		if ( ! $order ) {
			return '';
		}
		if ( $order->is_paid() ) {
			return 'paid';
		}
		self::create_order( $request_id ); // full rebuild keeps everything consistent
		return 'updated';
	}

	/** The order attached to a request, or null. */
	public static function order_for( $request_id ) {
		global $wpdb;
		if ( ! self::is_active() ) {
			return null;
		}
		$id = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT wc_order_id FROM ' . AUN_SP_Install::table( 'requests' ) . ' WHERE id = %d',
			(int) $request_id
		) );
		if ( ! $id ) {
			return null;
		}
		$o = wc_get_order( $id );
		return $o ? $o : null;
	}

	/** Safe summary for the customer's tracking page. */
	public static function customer_summary( $request_id ) {
		$order = self::order_for( $request_id );
		if ( ! $order ) {
			return null;
		}
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT refunded_at, refund_amount FROM ' . AUN_SP_Install::table( 'requests' ) . ' WHERE id = %d',
			(int) $request_id
		) );
		$refunded = ( $r && ! empty( $r->refunded_at ) );
		return array(
			'number'  => $order->get_order_number(),
			'total'   => number_format( (float) $order->get_total(), 2 ),
			'paid'    => (bool) $order->is_paid(),
			'method'  => $order->get_payment_method_title(),
			'pay_url' => $order->needs_payment() ? $order->get_checkout_payment_url() : '',
			// Refund state, so a customer whose request was cancelled after paying can
			// see the money coming back instead of having to chase us for it.
			'refunded'      => $refunded,
			'refund_amount' => $refunded ? number_format( (float) $r->refund_amount, 2 ) : '',
			'refund_date'   => $refunded ? date_i18n( 'j M Y', strtotime( $r->refunded_at ) ) : '',
			'refund_due'    => self::refund_due( $request_id ),
		);
	}

	/* ------------------------------------------------------- Request -> order sync */

	/**
	 * The request is the master record, so push ITS state onto the order.
	 *   Completed  -> order completed (money already taken online)
	 *   Rejected / declined -> cancel the order if it was never paid
	 */
	public static function sync_from_request( $request_id, $sp_status ) {
		$order = self::order_for( $request_id );
		if ( ! $order ) {
			return;
		}
		if ( 'closed' === $sp_status && $order->is_paid() && ! $order->has_status( 'completed' ) ) {
			$order->update_status( 'completed', 'Spare-parts request marked Completed.' );
			self::log( $request_id, 'wc_order', 'Order #' . $order->get_order_number() . ' marked Completed (request delivered)' );
		} elseif ( in_array( $sp_status, array( 'rejected', 'declined' ), true ) ) {
			if ( $order->is_paid() ) {
				// Money already taken — cancelling here would hide that a refund is owed.
				self::log( $request_id, 'refund', 'REFUND DUE — ৳' . number_format_i18n( (float) $order->get_total(), 2 )
					. ' was paid online and the request is now ' . $sp_status . '.' );
			} elseif ( ! $order->has_status( array( 'cancelled', 'refunded' ) ) ) {
				$order->update_status( 'cancelled', 'Spare-parts request ' . $sp_status . '.' );
				self::log( $request_id, 'wc_order', 'Unpaid order #' . $order->get_order_number() . ' cancelled (request ' . $sp_status . ')' );
			}
		}
	}

	/**
	 * Is money owed back to this customer? True once a PAID request is rejected or
	 * declined and no refund has been recorded yet.
	 *
	 * Their gateway cannot refund through WooCommerce's API, so refunds are made by
	 * hand (bKash/bank) and recorded here — the record is what the customer sees, and
	 * what stops a cancelled request looking like it swallowed their money.
	 */
	public static function refund_due( $request_id ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT overall_status, refunded_at FROM ' . AUN_SP_Install::table( 'requests' ) . ' WHERE id = %d',
			(int) $request_id
		) );
		if ( ! $r || ! empty( $r->refunded_at ) ) {
			return false;
		}
		if ( ! in_array( $r->overall_status, array( 'rejected', 'declined' ), true ) ) {
			return false;
		}
		$order = self::order_for( $request_id );
		return ( $order && $order->is_paid() );
	}

	/**
	 * Record a refund the admin has already paid out by hand. Sets the WooCommerce
	 * order to refunded (status only — no gateway call, which this gateway cannot do),
	 * writes it to the activity trail the customer reads, and texts them.
	 */
	public static function record_refund( $request_id, $amount, $reference = '' ) {
		global $wpdb;
		$t_req  = AUN_SP_Install::table( 'requests' );
		$order  = self::order_for( $request_id );
		$amount = round( (float) $amount, 2 );
		if ( $amount <= 0 && $order ) {
			$amount = (float) $order->get_total();
		}

		$wpdb->update( $t_req, array(
			'refunded_at'   => current_time( 'mysql' ),
			'refund_amount' => $amount,
			'refund_ref'    => sanitize_text_field( $reference ),
			'updated_at'    => current_time( 'mysql' ),
		), array( 'id' => (int) $request_id ) );

		if ( $order && ! $order->has_status( 'refunded' ) ) {
			$order->update_status( 'refunded', 'Refunded manually outside the gateway' . ( $reference ? ' (ref ' . $reference . ')' : '' ) . '.' );
		}

		self::log( $request_id, 'refund', 'Refund of ৳' . number_format_i18n( $amount, 2 ) . ' issued to the customer'
			. ( $reference ? ' (reference ' . $reference . ')' : '' ) );

		$r = $wpdb->get_row( $wpdb->prepare( "SELECT ref, phone_current FROM $t_req WHERE id = %d", (int) $request_id ) );
		if ( $r && $r->phone_current !== '' && AUN_SP_SMS::is_configured() ) {
			$msg = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_REFUND ), array(
				'ref'   => $r->ref,
				'total' => number_format_i18n( $amount, 2 ),
				'track' => AUN_SP_Messages::track_link( $r->ref ),
			) );
			AUN_SP_SMS::send_tracked( (int) $request_id, $r->phone_current, $msg, 'refund confirmation' );
		}
		return true;
	}

	/**
	 * Daily tidy-up: cancel our own pending orders that were opened and abandoned.
	 * WooCommerce's built-in unpaid-order cron only touches orders created via
	 * checkout, so ours would otherwise sit "pending payment" for ever. Cancelling is
	 * harmless — pressing "Pay online" again simply builds a fresh order.
	 */
	public static function cancel_abandoned() {
		global $wpdb;
		if ( ! self::is_active() ) {
			return;
		}
		$hours = max( 1, (int) apply_filters( 'aun_sp_abandoned_pay_hours', 72 ) );
		$cut   = time() - $hours * HOUR_IN_SECONDS;
		$rows  = $wpdb->get_results( 'SELECT id, wc_order_id FROM ' . AUN_SP_Install::table( 'requests' ) . ' WHERE wc_order_id > 0' );
		foreach ( (array) $rows as $row ) {
			$order = wc_get_order( (int) $row->wc_order_id );
			if ( ! $order || ! $order->has_status( 'pending' ) ) {
				continue;
			}
			$modified = $order->get_date_modified();
			if ( $modified && $modified->getTimestamp() < $cut ) {
				$order->update_status( 'cancelled', 'Online payment not completed within ' . $hours . ' hours.' );
				self::log( (int) $row->id, 'wc_order', 'Abandoned payment order #' . $order->get_order_number() . ' cancelled — the customer can start payment again any time.' );
			}
		}
	}

	/* ------------------------------------------------------------------ Payment */

	public static function on_status_changed( $order_id, $from, $to, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! self::is_ours( $order ) ) {
			return;
		}
		$request_id = (int) $order->get_meta( self::META_REQ );

		if ( in_array( $to, array( 'processing', 'completed' ), true ) && $order->is_paid() ) {
			// Only announce the payment once, however many status hops happen.
			if ( $order->get_meta( '_aun_sp_paid_announced' ) ) {
				return;
			}
			// Meta only — a full save() inside the order's own status transition can
			// re-enter WooCommerce's save pipeline (and gateway callbacks are exactly
			// where that bites).
			$order->update_meta_data( '_aun_sp_paid_announced', 1 );
			$order->save_meta_data();

			$total = number_format_i18n( (float) $order->get_total(), 2 );
			self::log( $request_id, 'payment', 'Online payment received for order #' . $order->get_order_number()
				. ' (' . $order->get_payment_method_title() . ') — ৳' . $total );
			self::notify_customer_paid( $request_id, $order, $total );
			self::notify_admin( $order, 'Online payment received — ৳' . $total );
		} elseif ( in_array( $to, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
			self::log( $request_id, 'payment', 'Order #' . $order->get_order_number() . ' is now ' . $to );
		}
	}

	/**
	 * Payment confirmation SMS — sent from THIS plugin (not WooCommerce) so it is
	 * worded by us, retried on failure, and recorded in the request's activity log.
	 */
	private static function notify_customer_paid( $request_id, $order, $total ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			'SELECT ref, phone_current FROM ' . AUN_SP_Install::table( 'requests' ) . ' WHERE id = %d',
			(int) $request_id
		) );
		if ( ! $r || $r->phone_current === '' || ! AUN_SP_SMS::is_configured() ) {
			return;
		}
		$msg = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_PAID ), array(
			'ref'   => $r->ref,
			'total' => $total,
			'track' => AUN_SP_Messages::track_link( $r->ref ),
		) );
		AUN_SP_SMS::send_tracked( (int) $request_id, $r->phone_current, $msg, 'payment confirmation' );
	}

	private static function notify_admin( $order, $msg ) {
		$to = get_option( 'aun_sp_alert_email', get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			return;
		}
		$ref = (string) $order->get_meta( self::META_REF );
		wp_mail( $to, 'Spare parts ' . $ref . ' — payment received', $msg . "\n\n" . $order->get_edit_order_url() );
	}

	/* -------------------------------------------------------------------- Admin */

	public static function order_column( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$out['aun_sp'] = 'Spare part';
			}
		}
		if ( ! isset( $out['aun_sp'] ) ) {
			$out['aun_sp'] = 'Spare part';
		}
		return $out;
	}

	public static function order_column_value( $column, $order_or_id ) {
		if ( 'aun_sp' !== $column ) {
			return;
		}
		$order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );
		if ( ! $order ) {
			return;
		}
		$ref = (string) $order->get_meta( self::META_REF );
		$rid = (int) $order->get_meta( self::META_REQ );
		if ( '' === $ref ) {
			echo '<span style="color:#c3c4c7;">—</span>';
			return;
		}
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=aun-sp&request=' . $rid ) ) . '"><strong>' . esc_html( $ref ) . '</strong></a>';
	}

	private static function log( $request_id, $type, $message ) {
		global $wpdb;
		$wpdb->insert( AUN_SP_Install::table( 'events' ), array(
			'request_id' => (int) $request_id,
			'type'       => $type,
			'message'    => $message,
			'by_user'    => 'system',
			'created_at' => current_time( 'mysql' ),
		) );
	}
}
