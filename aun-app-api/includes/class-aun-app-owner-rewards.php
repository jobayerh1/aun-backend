<?php
/**
 * Owner Rewards — "5% off your next projector".
 *
 * The retention half of AUN Rewards (the acquisition half is the Invite
 * programme, class-aun-app-referrals.php). Every projector a customer buys FROM
 * AUN — delivered from the website, or sold at the showroom and recorded in the
 * ERP — gives them one reward: a percentage off their NEXT projector.
 *
 * The rules, and why (AUN-REWARDS-PLAN.md §1):
 *   • Personal: a WooCommerce coupon locked to the buyer's phone with the same
 *     lock the referral coupons use, so it cannot be shared or posted.
 *   • One projector, projectors only, not on items already on sale: margin is
 *     ~20% and no sale may give away more than half of it.
 *   • ONE AT A TIME: buying again while a reward is unused EXTENDS it rather than
 *     adding a second. Twice-a-year buyers are rewarded twice a year; nobody
 *     builds a pile.
 *   • Only purchases from AUN: dealer-sold projectors never earn, and ERP sales
 *     to the Dealers customer group never earn.
 *   • From launch only: no backfill. `owner_rewards_launch` is stamped the first
 *     time the programme is switched on.
 *   • Clawback: the earning order refunded/cancelled, or the ERP sale returned →
 *     an unused reward shortens to the previous purchase, or is withdrawn.
 *
 * The ledger (`aun_app_owner_rewards`) is the record of every reward's life.
 * The coupon is what the website checkout honours; the showroom screen marks the
 * same coupon used, so a reward can never be spent twice across the two counters.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Owner_Rewards {

	const COUPON_META = '_aun_owner_reward'; // the ledger row id
	const PREFIX      = 'NEXT-';

	const STATUS_ACTIVE  = 'active';
	const STATUS_USED    = 'used';
	const STATUS_EXPIRED = 'expired';
	const STATUS_REVOKED = 'revoked';

	/** Order statuses in which a coupon counts as spent on the website. */
	const LIVE_STATUSES = array( 'processing', 'on-hold', 'completed', 'shipped', 'delivered', 'partial-shipped' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aun_app_owner_rewards';
	}

	/* --------------------------------------------------------------------- *
	 * Settings
	 * --------------------------------------------------------------------- */

	public static function settings() {
		$o = aun_app_api_get_options();
		$cats = array_values( array_filter( array_map( 'intval', (array) ( $o['owner_reward_categories'] ?? array() ) ) ) );
		return array(
			'enabled'        => ! empty( $o['owner_rewards_enabled'] ),
			'percent'        => max( 0, min( 50, (float) ( $o['owner_reward_percent'] ?? 5 ) ) ),
			'valid_months'   => max( 1, min( 36, (int) ( $o['owner_reward_months'] ?? 12 ) ) ),
			'categories'     => $cats,
			'exclude_sale'   => ! isset( $o['owner_reward_exclude_sale'] ) || ! empty( $o['owner_reward_exclude_sale'] ),
			'sms'            => ! isset( $o['owner_reward_sms'] ) || ! empty( $o['owner_reward_sms'] ),
			'sms_text'       => trim( (string) ( $o['owner_reward_sms_text'] ?? '' ) ),
			'remind_sms'     => ! isset( $o['owner_reward_remind_sms'] ) || ! empty( $o['owner_reward_remind_sms'] ),
			'ceiling'        => max( 0, min( 100, (int) ( $o['rewards_ceiling'] ?? 10 ) ) ),
			'showroom'       => ! isset( $o['rewards_showroom'] ) || ! empty( $o['rewards_showroom'] ),
			'hold_days'      => max( 0, min( 60, (int) ( $o['rewards_showroom_hold_days'] ?? 7 ) ) ),
			'launch'         => (string) ( $o['owner_rewards_launch'] ?? '' ),
		);
	}

	/**
	 * Running, and able to run: switched on, launched, a percentage, and at
	 * least one category that says what a "projector" is. Without categories
	 * every product would count — a cable would earn 5% off a projector.
	 */
	public static function enabled() {
		$s = self::settings();
		return $s['enabled'] && '' !== $s['launch'] && $s['percent'] > 0
			&& ! empty( $s['categories'] ) && class_exists( 'WC_Coupon' );
	}

	public static function showroom_enabled() {
		return self::settings()['showroom'];
	}

	/**
	 * The most rewards may take off one order, in %. 0 = no ceiling.
	 * Applies to the Invite programme's rewards as well as this one.
	 */
	public static function ceiling_percent() {
		return (int) self::settings()['ceiling'];
	}

	/** The launch moment as a UTC timestamp (0 when never launched). */
	public static function launch_ts() {
		$l = self::settings()['launch'];
		return '' === $l ? 0 : (int) strtotime( get_gmt_from_date( $l ) . ' UTC' );
	}

	/**
	 * Categories that obviously ARE projectors — exact slugs only.
	 *
	 * Deliberately not "anything containing projector": this store also has
	 * Projector Screen and Projector Accessories, and matching those would make
	 * every screen and cable earn money off a projector.
	 *
	 * @return int[]
	 */
	public static function detect_projector_categories() {
		$out = array();
		foreach ( array( 'projector', 'projectors' ) as $slug ) {
			$t = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $t && ! is_wp_error( $t ) ) {
				$out[] = (int) $t->term_id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/* --------------------------------------------------------------------- *
	 * Coupons
	 * --------------------------------------------------------------------- */

	/** Is this one of ours? Accepts a WC_Coupon or a code. */
	public static function is_owner_coupon( $coupon ) {
		if ( is_string( $coupon ) ) {
			if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
				return false;
			}
			$id = (int) wc_get_coupon_id_by_code( $coupon );
			if ( $id < 1 ) {
				return false;
			}
			$coupon = new WC_Coupon( $id );
		}
		return is_a( $coupon, 'WC_Coupon' ) && '' !== (string) $coupon->get_meta( self::COUPON_META );
	}

	/**
	 * Say why an owner reward was refused, in words a customer can act on.
	 *
	 * On `woocommerce_coupon_error`. WooCommerce's own wording ("not applicable
	 * to selected products") reads like a fault. Only this programme's coupons
	 * are touched, and only for the refusals that have a plain answer — anything
	 * else (e.g. a checkout still holding the code) keeps WooCommerce's message.
	 *
	 * @param string    $err      WooCommerce's message (already escaped).
	 * @param int       $err_code WC_Coupon::E_WC_COUPON_*.
	 * @param WC_Coupon $coupon   The coupon.
	 * @return string
	 */
	public static function explain_refusal( $err, $err_code, $coupon ) {
		if ( ! class_exists( 'WC_Coupon' ) || ! is_a( $coupon, 'WC_Coupon' ) ) {
			return $err;
		}
		$typed = strtoupper( trim( (string) $coupon->get_code() ) );
		$code  = esc_html( $typed );

		// A withdrawn code no longer exists as a coupon — "does not exist" would
		// be true but useless. The ledger still knows what happened to it.
		if ( (int) WC_Coupon::E_WC_COUPON_NOT_EXIST === (int) $err_code ) {
			$row = 0 === strpos( $typed, self::PREFIX ) ? self::row_by_coupon( $typed ) : null;
			if ( $row && self::STATUS_EXPIRED === $row->status ) {
				return sprintf(
					/* translators: 1: reward code, 2: date. */
					esc_html__( 'Your owner reward %1$s ended on %2$s. Every projector you buy from AUN earns a new one.', 'aun-app-api' ),
					$code,
					esc_html( date_i18n( 'j M Y', strtotime( (string) $row->expires_at ) ) )
				);
			}
			if ( ! $row || self::STATUS_REVOKED !== $row->status ) {
				return $err;
			}
			if ( 0 === strpos( (string) $row->revoked_reason, 'merged:' ) ) {
				return sprintf(
					/* translators: %s: reward code. */
					esc_html__( 'Your owner reward %s was combined into your current one. Find its code in the AUN Care app under Rewards.', 'aun-app-api' ),
					$code
				);
			}
			return sprintf(
				/* translators: %s: reward code. */
				esc_html__( 'Your owner reward %s was withdrawn because the purchase that earned it was returned or refunded.', 'aun-app-api' ),
				$code
			);
		}

		if ( ! self::is_owner_coupon( $coupon ) ) {
			return $err;
		}
		switch ( (int) $err_code ) {
			case WC_Coupon::E_WC_COUPON_NOT_APPLICABLE:
			case WC_Coupon::E_WC_COUPON_NOT_VALID_SALE_ITEMS:
				return sprintf(
					/* translators: 1: reward code, 2: percentage. */
					esc_html__( '%1$s is your AUN owner reward: %2$s off one full-price projector. It can\'t be used on accessories, or on a projector that is already on sale.', 'aun-app-api' ),
					$code,
					esc_html( self::num( $coupon->get_amount() ) . '%' )
				);
			case WC_Coupon::E_WC_COUPON_USAGE_LIMIT_REACHED:
			case WC_Coupon::E_WC_COUPON_USAGE_LIMIT_COUPON_STUCK:
			case WC_Coupon::E_WC_COUPON_USAGE_LIMIT_COUPON_STUCK_GUEST:
				// WooCommerce GUESSES between "used" and "held by an unfinished
				// checkout" (and on MySQL usually guesses held). The ledger knows:
				// only a reward truly spent gets this answer; a held one keeps
				// WooCommerce's "finish or cancel that order" advice, which is right.
				$row = self::row_by_coupon( $typed );
				if ( ! $row || self::STATUS_USED !== $row->status ) {
					return $err;
				}
				return sprintf(
					/* translators: %s: reward code. */
					esc_html__( 'Your owner reward %s has already been used. Every projector you buy from AUN earns a new one once it is delivered.', 'aun-app-api' ),
					$code
				);
			case WC_Coupon::E_WC_COUPON_EXPIRED:
				$exp = $coupon->get_date_expires();
				return sprintf(
					/* translators: 1: reward code, 2: date. */
					esc_html__( 'Your owner reward %1$s ended on %2$s. Every projector you buy from AUN earns a new one.', 'aun-app-api' ),
					$code,
					esc_html( $exp ? date_i18n( 'j M Y', $exp->getOffsetTimestamp() ) : '' )
				);
		}
		return $err;
	}

	/** End of the day $months after $ts, in site time: 'Y-m-d 23:59:59'. */
	private static function expiry_from( $ts, $months ) {
		$local = (int) $ts + (int) ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
		$day   = gmdate( 'Y-m-d', strtotime( '+' . (int) $months . ' months', $local ) );
		return $day . ' 23:59:59';
	}

	/** A site-time 'Y-m-d H:i:s' as a UTC timestamp. */
	private static function ts( $local ) {
		return '' === (string) $local ? 0 : (int) strtotime( get_gmt_from_date( (string) $local ) . ' UTC' );
	}

	/**
	 * A new, unused coupon code: PREFIX + 6 characters from the referral
	 * alphabet, which has no O/0, I/1 or S/5. These codes are read out of an
	 * SMS and typed at a checkout; a look-alike character is a support call.
	 */
	public static function new_code( $prefix ) {
		$alphabet = defined( 'AUN_App_Referrals::ALPHABET' ) ? AUN_App_Referrals::ALPHABET : 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';
		$len      = strlen( $alphabet );
		for ( $try = 0; $try < 8; $try++ ) {
			$code = $prefix;
			for ( $i = 0; $i < 6; $i++ ) {
				$code .= $alphabet[ random_int( 0, $len - 1 ) ];
			}
			if ( ! function_exists( 'wc_get_coupon_id_by_code' ) || (int) wc_get_coupon_id_by_code( $code ) < 1 ) {
				return $code;
			}
		}
		return $prefix . strtoupper( wp_generate_password( 8, false, false ) );
	}

	private static function create_coupon( $phone, $row_id, $expires_local, $s ) {
		try {
			$code   = self::new_code( self::PREFIX );
			$coupon = new WC_Coupon();
			$coupon->set_code( $code );
			$coupon->set_discount_type( 'percent' );
			$coupon->set_amount( (float) $s['percent'] );
			// A "next projector" offer — never a store-wide one.
			$coupon->set_product_categories( $s['categories'] );
			$coupon->set_limit_usage_to_x_items( 1 );
			$coupon->set_exclude_sale_items( (bool) $s['exclude_sale'] );
			$coupon->set_usage_limit( 1 );
			$coupon->set_usage_limit_per_user( 1 );
			// On its own, EXCEPT alongside the customer's other earned rewards
			// (see AUN_App_Referrals::keep_rewards_together). A seasonal sale
			// code never rides along.
			$coupon->set_individual_use( true );
			$coupon->set_date_expires( self::ts( $expires_local ) );
			$coupon->set_description( 'AUN owner reward — ' . self::num( $s['percent'] ) . '% off the next projector' );
			// The SAME phone lock as the referral coupons, so the checkout gate
			// that already enforces it covers this coupon without new code.
			if ( class_exists( 'AUN_App_Referrals' ) ) {
				$coupon->update_meta_data( AUN_App_Referrals::COUPON_META_PHONE, (string) $phone );
			}
			$coupon->update_meta_data( self::COUPON_META, (string) (int) $row_id );
			$coupon->save();
			return $code;
		} catch ( Exception $e ) {
			return '';
		}
	}

	private static function delete_coupon( $code ) {
		if ( '' === (string) $code || ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return;
		}
		$id = (int) wc_get_coupon_id_by_code( $code );
		if ( $id > 0 ) {
			wp_delete_post( $id, true );
			if ( class_exists( 'WC_Cache_Helper' ) ) {
				WC_Cache_Helper::invalidate_cache_group( 'coupons' );
			}
		}
	}

	private static function coupon_for( $row ) {
		if ( ! $row || '' === (string) $row->coupon || ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return null;
		}
		$id = (int) wc_get_coupon_id_by_code( (string) $row->coupon );
		return $id > 0 ? new WC_Coupon( $id ) : null;
	}

	private static function num( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 2, '.', '' ), '0' ), '.' );
	}

	/* --------------------------------------------------------------------- *
	 * The ledger
	 * --------------------------------------------------------------------- */

	/** The row that owns this coupon code, any status. */
	private static function row_by_coupon( $code ) {
		global $wpdb;
		if ( '' === (string) $code ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE UPPER(coupon) = %s ORDER BY id DESC LIMIT 1',
			strtoupper( (string) $code )
		) );
	}

	/** The row whose sources include this purchase, any status. */
	private static function row_with_source( $key ) {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $t WHERE source_keys LIKE %s ORDER BY id DESC LIMIT 1",
			'%,' . $wpdb->esc_like( $key ) . ',%'
		) );
	}

	/** The customer's live, unspent reward, if any. */
	public static function active_row( $phone ) {
		global $wpdb;
		$t = self::table();
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $t WHERE phone = %s AND status = %s ORDER BY id DESC LIMIT 1",
			(string) $phone,
			self::STATUS_ACTIVE
		) );
		if ( $row && self::ts( $row->expires_at ) < time() ) {
			self::set_status( $row, self::STATUS_EXPIRED );
			return null;
		}
		return $row;
	}

	private static function set_status( $row, $status, $extra = array() ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array_merge( array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ), $extra ),
			array( 'id' => (int) $row->id )
		);
		$row->status = $status;
		foreach ( $extra as $k => $v ) {
			$row->$k = $v;
		}
	}

	private static function sources( $row ) {
		$s = json_decode( (string) ( $row->sources ?? '' ), true );
		return is_array( $s ) ? $s : array();
	}

	private static function source_keys( $sources ) {
		$keys = array();
		foreach ( $sources as $src ) {
			$keys[] = (string) $src['key'];
		}
		return $keys ? ',' . implode( ',', $keys ) . ',' : '';
	}

	/* --------------------------------------------------------------------- *
	 * Earning
	 * --------------------------------------------------------------------- */

	/**
	 * Credit one purchase. Idempotent: the same purchase is never credited
	 * twice, however many times its status changes.
	 *
	 * @param string $phone   Canonical buyer phone.
	 * @param string $channel 'web' | 'erp'.
	 * @param string $ref     Order id / ERP invoice.
	 * @param int    $at      When they bought (UTC timestamp).
	 * @param string $product What they bought (for the record and the app).
	 * @return object|null The ledger row, or null when nothing was credited.
	 */
	public static function earn( $phone, $channel, $ref, $at, $product = '', $serial = '' ) {
		global $wpdb;
		if ( ! self::enabled() || '' === (string) $phone || '' === (string) $ref ) {
			return null;
		}
		$s = self::settings();
		// From launch only — no backfill.
		if ( (int) $at < self::launch_ts() ) {
			return null;
		}
		$key = $channel . ':' . $ref;
		if ( $existing = self::row_with_source( $key ) ) {
			return $existing;
		}
		$expires = self::expiry_from( (int) $at, $s['valid_months'] );
		if ( self::ts( $expires ) < time() ) {
			return null; // bought so long ago the reward would already be over
		}
		$source = array(
			'key'     => $key,
			'channel' => $channel,
			'ref'     => (string) $ref,
			'at'      => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $at ) ),
			'product' => substr( (string) $product, 0, 120 ),
		);
		// An ERP purchase keeps its serial: the ERP's phone lookup hides a
		// returned sale entirely, so the serial lookup is the only way to see
		// the return later (check_erp_returns).
		if ( '' !== (string) $serial ) {
			$source['serial'] = substr( (string) $serial, 0, 64 );
		}

		$active = self::active_row( $phone );
		if ( $active ) {
			// ONE AT A TIME: extend the reward they hold instead of a second one.
			$sources   = self::sources( $active );
			$sources[] = $source;
			$new_exp   = self::ts( $expires ) > self::ts( $active->expires_at ) ? $expires : (string) $active->expires_at;
			$wpdb->update( self::table(), array(
				'sources'     => wp_json_encode( $sources ),
				'source_keys' => self::source_keys( $sources ),
				'expires_at'  => $new_exp,
				'reminded_at' => null,
				'updated_at'  => current_time( 'mysql' ),
			), array( 'id' => (int) $active->id ) );
			if ( $c = self::coupon_for( $active ) ) {
				$c->set_date_expires( self::ts( $new_exp ) );
				$c->save();
			}
			$active->expires_at = $new_exp;
			$active->sources    = wp_json_encode( $sources );
			self::notify( $active, 'extended' );
			return $active;
		}

		$wpdb->insert( self::table(), array(
			'phone'       => (string) $phone,
			'user_id'     => self::user_for( $phone ),
			'coupon'      => '',
			'status'      => self::STATUS_ACTIVE,
			'percent'     => (float) $s['percent'],
			'sources'     => wp_json_encode( array( $source ) ),
			'source_keys' => self::source_keys( array( $source ) ),
			'issued_at'   => current_time( 'mysql' ),
			'expires_at'  => $expires,
			'updated_at'  => current_time( 'mysql' ),
		) );
		$id = (int) $wpdb->insert_id;
		if ( $id < 1 ) {
			return null;
		}
		$code = self::create_coupon( $phone, $id, $expires, $s );
		if ( '' === $code ) {
			// Never leave a reward the customer is told about but cannot spend.
			$wpdb->delete( self::table(), array( 'id' => $id ) );
			return null;
		}
		$wpdb->update( self::table(), array( 'coupon' => $code ), array( 'id' => $id ) );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
		self::notify( $row, 'issued' );
		return $row;
	}

	/**
	 * A purchase that earned a reward came back (refund, cancellation, return).
	 *
	 * An UNUSED reward shortens to the latest remaining purchase, or is withdrawn
	 * when none remains. A reward already SPENT cannot be taken back — that is
	 * recorded, never silently reversed on the customer.
	 */
	public static function unearn( $key, $reason = 'source_reversed' ) {
		global $wpdb;
		$row = self::row_with_source( $key );
		if ( ! $row ) {
			return;
		}
		$sources = array_values( array_filter( self::sources( $row ), function ( $src ) use ( $key ) {
			return $key !== (string) $src['key'];
		} ) );
		$fields = array(
			'sources'     => wp_json_encode( $sources ),
			'source_keys' => self::source_keys( $sources ),
			'updated_at'  => current_time( 'mysql' ),
		);
		if ( self::STATUS_ACTIVE !== $row->status ) {
			$wpdb->update( self::table(), $fields, array( 'id' => (int) $row->id ) );
			return;
		}
		if ( empty( $sources ) ) {
			self::delete_coupon( (string) $row->coupon );
			$wpdb->update( self::table(), array_merge( $fields, array(
				'status'         => self::STATUS_REVOKED,
				'revoked_reason' => substr( (string) $reason, 0, 40 ),
			) ), array( 'id' => (int) $row->id ) );
			$row->status = self::STATUS_REVOKED;
			self::notify( $row, 'revoked' );
			return;
		}
		// Shorten to the latest purchase still standing.
		$latest = 0;
		foreach ( $sources as $src ) {
			$latest = max( $latest, self::ts( (string) $src['at'] ) );
		}
		$expires = self::expiry_from( $latest, self::settings()['valid_months'] );
		$fields['expires_at'] = $expires;
		$wpdb->update( self::table(), $fields, array( 'id' => (int) $row->id ) );
		if ( $c = self::coupon_for( $row ) ) {
			$c->set_date_expires( self::ts( $expires ) );
			$c->save();
		}
		if ( self::ts( $expires ) < time() ) {
			$row->expires_at = $expires;
			self::delete_coupon( (string) $row->coupon );
			self::set_status( $row, self::STATUS_EXPIRED );
		}
	}

	/** Projector lines of an order, net of refunds: [ product name, … ]. */
	public static function projector_lines( $order ) {
		$cats = self::settings()['categories'];
		$out  = array();
		if ( ! $order || empty( $cats ) ) {
			return $out;
		}
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			$pid  = (int) $item->get_product_id(); // the parent for a variation
			$kept = (int) $item->get_quantity() + (int) $order->get_qty_refunded_for_item( $item_id );
			if ( $pid < 1 || $kept < 1 ) {
				continue;
			}
			if ( array_intersect( $cats, wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'ids' ) ) ) ) {
				$out[] = (string) $item->get_name();
			}
		}
		return $out;
	}

	/** Website: an order reached the paying status (Delivered/Completed). */
	public static function on_order_paid_out( $order_id ) {
		if ( ! self::enabled() ) {
			return;
		}
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order || ! class_exists( 'AUN_App_Phone' ) ) {
			return;
		}
		$lines = self::projector_lines( $order );
		$phone = AUN_App_Phone::normalize( (string) $order->get_billing_phone() );
		if ( empty( $lines ) || ! $phone ) {
			return;
		}
		$created = $order->get_date_created();
		self::earn( $phone, 'web', (string) $order->get_id(), $created ? $created->getTimestamp() : time(), $lines[0] );
	}

	/** Website: the order was refunded / cancelled / failed. */
	public static function on_order_reversed( $order_id ) {
		self::unearn( 'web:' . (int) $order_id, 'order_reversed' );
	}

	/**
	 * Website: money refunded without a status change. Only when NO projector
	 * is left on the order does the reward go — a goodwill refund on a projector
	 * the customer kept must not cost them their reward.
	 */
	public static function on_partial_refund( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( $order && empty( self::projector_lines( $order ) ) ) {
			self::unearn( 'web:' . (int) $order_id, 'order_refunded' );
		}
	}

	/** The ledger key for an ERP sale. */
	public static function erp_key( $sale ) {
		$ref = trim( (string) ( $sale['invoice_no'] ?? '' ) );
		if ( '' === $ref ) {
			$ref = 'SN' . trim( (string) ( $sale['serial'] ?? '' ) );
		}
		return 'erp:' . $ref;
	}

	/**
	 * Why an ERP sale does or doesn't earn — '' when it does.
	 *
	 * @param array $sale Normalised ERP sale row (AUN_App_ERP::lookup_phone).
	 * @return string
	 */
	public static function erp_ineligible( $sale ) {
		if ( ! self::enabled() ) {
			return 'off';
		}
		// Normalised rows always carry these; a hand-made one may not.
		$sale = array_merge( array( 'returned' => false, 'customer_group_id' => 0, 'sale_date' => '' ), (array) $sale );
		if ( ! empty( $sale['returned'] ) ) {
			return 'returned';
		}
		if ( class_exists( 'AUN_App_ERP' ) && AUN_App_ERP::is_dealer_sale( $sale ) ) {
			return 'dealer';
		}
		// A PROJECTOR, by the same rule as a website order: the product it was
		// is in the owner-reward categories. The ERP sells screens, mounts and
		// cables too, and without this a screen earned "5% off your next
		// projector". Unmatched → no reward, and the counter screen says why.
		$product = self::wc_product_for_sale( $sale );
		if ( $product < 1 ) {
			return 'unmatched';
		}
		$cats = function_exists( 'wc_get_product_term_ids' ) ? array_map( 'intval', (array) wc_get_product_term_ids( $product, 'product_cat' ) ) : array();
		if ( ! array_intersect( $cats, self::settings()['categories'] ) ) {
			return 'not_projector';
		}
		// The shop enters ONLINE sales into the ERP too (stock, warranty), under
		// the customer's number. That record is the same projector the website
		// order already rewards when it is delivered — crediting it again would
		// reward one purchase twice, and keep a reward standing after the
		// website order is refunded.
		if ( self::is_web_order_record( $sale, $product ) ) {
			return 'web_order';
		}
		$day = (string) ( $sale['sale_date'] ?? '' );
		if ( '' === $day ) {
			return 'no_date';
		}
		$launch = (string) self::settings()['launch'];
		if ( substr( $day, 0, 10 ) < substr( $launch, 0, 10 ) ) {
			return 'before_launch';
		}
		if ( self::ts( self::expiry_from( self::ts( $day . ' 12:00:00' ), self::settings()['valid_months'] ) ) < time() ) {
			return 'too_old';
		}
		return '';
	}

	/**
	 * Is this ERP sale the shop's own record of a WEBSITE order?
	 *
	 * The ERP has no link to WooCommerce, so it is recognised the way a person
	 * would: a website order from the same phone, for the same projector, placed
	 * up to 45 days before the ERP sale (dispatch) or a week after it. A real
	 * second purchase of the same model within that window is the price of
	 * never paying twice — and the counter screen says why it did not earn.
	 *
	 * @param array $sale       Normalised ERP sale row (needs contact_mobile).
	 * @param int   $product_id The website product it matched (0 = look it up).
	 * @return bool
	 */
	public static function is_web_order_record( $sale, $product_id = 0 ) {
		if ( ! function_exists( 'wc_get_orders' ) || ! class_exists( 'AUN_App_Phone' ) ) {
			return false;
		}
		$phone = AUN_App_Phone::normalize( (string) ( $sale['contact_mobile'] ?? '' ) );
		$day   = substr( (string) ( $sale['sale_date'] ?? '' ), 0, 10 );
		$pid   = (int) $product_id > 0 ? (int) $product_id : self::wc_product_for_sale( $sale );
		if ( ! $phone || '' === $day || $pid < 1 ) {
			return false;
		}
		$cache = 'aun_or_webrec_' . md5( $phone . '|' . self::erp_key( $sale ) );
		$hit   = get_transient( $cache );
		if ( false !== $hit ) {
			return '1' === $hit;
		}
		$at    = self::ts( $day . ' 12:00:00' );
		$range = ( $at - 45 * DAY_IN_SECONDS ) . '...' . ( $at + 7 * DAY_IN_SECONDS );
		$found = false;
		foreach ( AUN_App_Phone::variants( $phone ) as $variant ) {
			foreach ( (array) wc_get_orders( array(
				'billing_phone' => $variant,
				'date_created'  => $range,
				'limit'         => 10,
			) ) as $order ) {
				if ( ! is_a( $order, 'WC_Order' ) ) {
					continue;
				}
				foreach ( $order->get_items() as $item ) {
					if ( is_a( $item, 'WC_Order_Item_Product' ) && (int) $item->get_product_id() === $pid ) {
						$found = true;
						break 3;
					}
				}
			}
		}
		set_transient( $cache, $found ? '1' : '0', 6 * HOUR_IN_SECONDS );
		return $found;
	}

	/**
	 * ERP returns take a reward back — found by SERIAL.
	 *
	 * The ERP's phone lookup drops a returned sale altogether, so a returned
	 * showroom projector simply vanishes from it; "gone" cannot be told apart
	 * from "further down than the lookup reaches". The serial lookup does report
	 * the return, so each recent ERP purchase behind a LIVE reward is checked by
	 * its serial. Only a return of THAT invoice counts.
	 *
	 * @param string   $phone Only this customer's reward ('' = every live reward).
	 * @param string[] $skip  Purchase keys already seen as sold (not returned).
	 * @param int      $limit ERP calls at most.
	 * @return int Rewards taken back or shortened.
	 */
	public static function check_erp_returns( $phone = '', $skip = array(), $limit = 40 ) {
		global $wpdb;
		if ( ! class_exists( 'AUN_App_ERP' ) || ! AUN_App_ERP::configured() ) {
			return 0;
		}
		$t    = self::table();
		$rows = '' !== (string) $phone
			? (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE phone = %s AND status = %s AND source_keys LIKE %s", (string) $phone, self::STATUS_ACTIVE, '%,erp:%' ) )
			: (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = %s AND source_keys LIKE %s ORDER BY id DESC LIMIT 200", self::STATUS_ACTIVE, '%,erp:%' ) );
		$calls = 0;
		$n     = 0;
		foreach ( $rows as $row ) {
			foreach ( self::sources( $row ) as $src ) {
				if ( 'erp' !== (string) ( $src['channel'] ?? '' ) || '' === (string) ( $src['serial'] ?? '' )
					|| in_array( (string) $src['key'], (array) $skip, true ) ) {
					continue;
				}
				// Returns happen early; a two-month-old sale is settled.
				if ( self::ts( (string) $src['at'] ) < time() - 60 * DAY_IN_SECONDS ) {
					continue;
				}
				if ( $calls++ >= $limit ) {
					return $n;
				}
				$sale = AUN_App_ERP::lookup_serial( (string) $src['serial'] );
				if ( ! is_array( $sale ) || empty( $sale['returned'] ) ) {
					continue;
				}
				if ( 'erp:' . trim( (string) ( $sale['invoice_no'] ?? '' ) ) !== (string) $src['key']
					&& 'erp:SN' . trim( (string) $src['serial'] ) !== (string) $src['key'] ) {
					continue; // a different sale of that serial
				}
				self::unearn( (string) $src['key'], 'erp_returned' );
				$n++;
			}
		}
		return $n;
	}

	/**
	 * The website product an ERP sale was for — exactly, never by name.
	 *
	 * The sale's SKU first (the link the dealer-stock sync already runs on; a
	 * variation's SKU resolves to its parent, where the categories live), then
	 * the serial's SKU as the sync recorded it, then a product carrying the
	 * ERP product id (`_aun_erp_product_id`). Name matching is deliberately
	 * absent: "A005" and "A005 Pro" are different products, and "projector
	 * screen" contains the word projector.
	 *
	 * @param array $sale Normalised ERP sale row.
	 * @return int WooCommerce (parent) product id, or 0.
	 */
	public static function wc_product_for_sale( $sale ) {
		if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
			return 0;
		}
		$skus = array( trim( (string) ( $sale['product_sku'] ?? '' ) ) );
		if ( class_exists( 'AUN_App_Projectors' ) && '' !== trim( (string) ( $sale['serial'] ?? '' ) ) ) {
			$skus[] = trim( (string) AUN_App_Projectors::sku_for_serial( (string) $sale['serial'] ) );
		}
		foreach ( array_unique( array_filter( $skus ) ) as $sku ) {
			$id = (int) wc_get_product_id_by_sku( $sku );
			if ( $id > 0 ) {
				$p = wc_get_product( $id );
				return ( $p && $p->get_parent_id() ) ? (int) $p->get_parent_id() : $id;
			}
		}
		$erp = (int) ( $sale['product_id'] ?? 0 );
		if ( $erp > 0 ) {
			$ids = get_posts( array(
				'post_type'   => 'product',
				'post_status' => array( 'publish', 'private', 'draft' ),
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_key'    => '_aun_erp_product_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => (string) $erp,         // phpcs:ignore WordPress.DB.SlowDBQuery
			) );
			if ( $ids ) {
				return (int) $ids[0];
			}
		}
		return 0;
	}

	/**
	 * Showroom: credit (or take back) a customer's ERP projector sales.
	 *
	 * Called with the rows the ERP returned for THAT customer's number — by the
	 * app when it links their showroom purchases, and by the staff screen.
	 * Idempotent, so running it on every sign-in is harmless.
	 *
	 * @param string $phone Canonical phone the sales were made with.
	 * @param array  $sales Rows from AUN_App_ERP::lookup_phone().
	 * @return int How many sales earned something new.
	 */
	public static function sync_erp_sales( $phone, $sales ) {
		$n    = 0;
		$seen = array();
		foreach ( (array) $sales as $sale ) {
			$sale = (array) $sale;
			if ( '' === trim( (string) ( $sale['contact_mobile'] ?? '' ) ) ) {
				$sale['contact_mobile'] = (string) $phone; // they were looked up by it
			}
			$key = self::erp_key( $sale );
			if ( ! empty( $sale['returned'] ) ) {
				self::unearn( $key, 'erp_returned' );
				continue;
			}
			$seen[] = $key;
			if ( '' !== self::erp_ineligible( $sale ) || self::row_with_source( $key ) ) {
				continue;
			}
			$at = self::ts( substr( (string) $sale['sale_date'], 0, 10 ) . ' 12:00:00' );
			if ( self::earn( (string) $phone, 'erp', substr( $key, 4 ), $at, (string) ( $sale['product_name'] ?? '' ), (string) ( $sale['serial'] ?? '' ) ) ) {
				$n++;
			}
		}
		// A purchase that earned a live reward and is no longer in the phone
		// lookup may have been RETURNED (the lookup hides returns) — ask by serial.
		self::check_erp_returns( (string) $phone, $seen, 10 );
		return $n;
	}

	/* --------------------------------------------------------------------- *
	 * Spending
	 * --------------------------------------------------------------------- */

	/**
	 * Website: an order carrying one of our coupons changed status.
	 *
	 * Placed → the reward is spent. Cancelled, failed or refunded → it is given
	 * BACK, because the customer never received the discount's value. WooCommerce
	 * already restores the usage count on cancellation, but not on a refund, so
	 * that one is restored here.
	 */
	public static function on_order_status_for_use( $order_id, $to ) {
		global $wpdb;
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return;
		}
		foreach ( (array) $order->get_coupon_codes() as $code ) {
			$code = strtoupper( (string) $code );
			if ( 0 !== strpos( $code, self::PREFIX ) ) {
				continue;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE UPPER(coupon) = %s LIMIT 1', $code ) );
			if ( ! $row ) {
				continue;
			}
			if ( in_array( $to, self::live_statuses(), true ) && self::STATUS_ACTIVE === $row->status ) {
				$amount = 0.0;
				foreach ( $order->get_items( 'coupon' ) as $ci ) {
					if ( strtoupper( (string) $ci->get_code() ) === $code ) {
						$amount = (float) $ci->get_discount();
					}
				}
				self::set_status( $row, self::STATUS_USED, array(
					'used_at'      => current_time( 'mysql' ),
					'used_channel' => 'web',
					'used_ref'     => (string) $order->get_id(),
					'used_amount'  => $amount,
				) );
				// A REFUNDED order set back to live (a mis-click undone): WooCommerce
				// still believes it holds its coupon use, so it records nothing — and
				// the use given back on the refund would stay spendable twice. Read
				// from the database, not the meta cache: WooCommerce's own counter
				// (which runs before this hook) writes around the cache.
				$c = self::coupon_for( $row );
				if ( $c && $c->get_id() ) {
					$count = (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'usage_count' AND post_id = %d LIMIT 1",
						$c->get_id()
					) );
					if ( $count < 1 ) {
						$c->increase_usage_count( (int) $order->get_user_id() > 0 ? (int) $order->get_user_id() : (string) $order->get_billing_email() );
					}
				}
			} elseif ( in_array( $to, array( 'cancelled', 'failed', 'refunded' ), true )
				&& self::STATUS_USED === $row->status && (string) $order->get_id() === (string) $row->used_ref ) {
				$back  = self::ts( $row->expires_at ) >= time() ? self::STATUS_ACTIVE : self::STATUS_EXPIRED;
				$clear = array( 'used_at' => null, 'used_channel' => '', 'used_ref' => '', 'used_amount' => 0 );

				// ONE AT A TIME, even here: if they have earned a newer reward since,
				// the one coming back folds into it rather than leaving two.
				$held = self::STATUS_ACTIVE === $back ? self::active_row( (string) $row->phone ) : null;
				if ( $held && (int) $held->id !== (int) $row->id ) {
					self::fold_into( $row, $held );
					continue;
				}

				// WooCommerce restores a coupon itself on a CANCELLATION, but not on a
				// refund. Restoring only the count is not enough: the coupon is "once
				// per customer", and WooCommerce also remembers WHO used it — left in
				// place, the same customer is refused the reward they were given back.
				if ( 'refunded' === $to && ( $c = self::coupon_for( $row ) ) && $c->get_usage_count() > 0 ) {
					$used_by = (int) $order->get_user_id() > 0 ? (int) $order->get_user_id() : (string) $order->get_billing_email();
					$c->decrease_usage_count( $used_by );
				}
				self::set_status( $row, $back, $clear );
			}
		}
	}

	/**
	 * A reward that came back joins the one the customer holds.
	 *
	 * Its purchases move across (so a later refund of one of them still shortens
	 * the right reward) and the later end date wins. The returned one is closed
	 * as merged and its code withdrawn — it was on a cancelled or refunded order,
	 * so no live order carries it.
	 */
	private static function fold_into( $from, $into ) {
		global $wpdb;
		$sources = array_merge( self::sources( $into ), self::sources( $from ) );
		$expires = self::ts( (string) $from->expires_at ) > self::ts( (string) $into->expires_at )
			? (string) $from->expires_at : (string) $into->expires_at;
		$wpdb->update( self::table(), array(
			'sources'     => wp_json_encode( $sources ),
			'source_keys' => self::source_keys( $sources ),
			'expires_at'  => $expires,
			'reminded_at' => $expires !== (string) $into->expires_at ? null : $into->reminded_at,
			'updated_at'  => current_time( 'mysql' ),
		), array( 'id' => (int) $into->id ) );
		if ( $expires !== (string) $into->expires_at && ( $c = self::coupon_for( $into ) ) ) {
			$c->set_date_expires( self::ts( $expires ) );
			$c->save();
		}
		self::delete_coupon( (string) $from->coupon );
		self::set_status( $from, self::STATUS_REVOKED, array(
			'sources'        => wp_json_encode( array() ),
			'source_keys'    => '',
			'used_at'        => null,
			'used_channel'   => '',
			'used_ref'       => '',
			'used_amount'    => 0,
			'revoked_reason' => 'merged:' . (int) $into->id,
		) );
	}

	private static function live_statuses() {
		$out = self::LIVE_STATUSES;
		if ( class_exists( 'AUN_App_Referrals' ) ) {
			$out = array_merge( $out, AUN_App_Referrals::payout_statuses() );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Showroom: spend the reward on an ERP sale.
	 *
	 * Marks the SAME coupon used, so the website refuses it afterwards — one
	 * reward, spent once, whichever counter it was spent at.
	 *
	 * @return array{ok:bool,code:string,message:string}
	 */
	public static function redeem_at_showroom( $row_id, $invoice, $amount ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $row_id ) );
		if ( ! $row || self::STATUS_ACTIVE !== $row->status ) {
			return array( 'ok' => false, 'code' => 'not_active', 'message' => 'This reward is not available any more.' );
		}
		if ( self::ts( $row->expires_at ) < time() ) {
			self::set_status( $row, self::STATUS_EXPIRED );
			return array( 'ok' => false, 'code' => 'expired', 'message' => 'This reward has expired.' );
		}
		$c = self::coupon_for( $row );
		if ( ! $c || $c->get_usage_count() >= max( 1, (int) $c->get_usage_limit() ) ) {
			return array( 'ok' => false, 'code' => 'spent', 'message' => 'This reward has already been used.' );
		}

		// The sale it is being spent on may ALREADY be on this reward's record:
		// if the ERP sale was synced (the app on sign-in, or the counter screen)
		// before Redeem was pressed, the new projector EXTENDED the reward now
		// being spent — and its own reward would vanish with this one.
		$inv     = strtoupper( trim( (string) $invoice ) );
		$same    = null;
		$sources = self::sources( $row );
		foreach ( $sources as $src ) {
			if ( 'erp' === (string) ( $src['channel'] ?? '' ) && strtoupper( trim( (string) ( $src['ref'] ?? '' ) ) ) === $inv ) {
				$same = $src;
			}
		}
		if ( $same && 1 === count( $sources ) ) {
			return array(
				'ok'      => false,
				'code'    => 'same_sale',
				'message' => 'This reward was earned by this same sale (invoice ' . $inv . '). It is for the customer\'s NEXT projector.',
			);
		}

		$c->increase_usage_count();
		self::set_status( $row, self::STATUS_USED, array(
			'used_at'      => current_time( 'mysql' ),
			'used_channel' => 'showroom',
			'used_ref'     => substr( (string) $invoice, 0, 64 ),
			'used_amount'  => round( (float) $amount, 2 ),
		) );

		// …so that purchase leaves the spent reward and earns its own.
		if ( $same ) {
			self::unearn( (string) $same['key'], 'spent_on_it' ); // a used row: the record only
			self::earn( (string) $row->phone, 'erp', (string) $same['ref'], self::ts( (string) $same['at'] ), (string) ( $same['product'] ?? '' ), (string) ( $same['serial'] ?? '' ) );
		}
		return array( 'ok' => true, 'code' => 'redeemed', 'message' => 'Reward redeemed.' );
	}

	/**
	 * Showroom: undo a redemption (a mistake at the counter, or the sale did
	 * not go ahead). The reward comes back — folded into the one they hold now,
	 * if they have earned another since: still one at a time.
	 *
	 * @return array{ok:bool,code:string,message:string}
	 */
	public static function undo_showroom( $row_id, $invoice ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $row_id ) );
		if ( ! $row || self::STATUS_USED !== $row->status || 'showroom' !== (string) $row->used_channel
			|| strtoupper( trim( (string) $row->used_ref ) ) !== strtoupper( trim( (string) $invoice ) ) ) {
			return array( 'ok' => false, 'code' => 'not_spent_here', 'message' => 'This owner reward was not spent on that invoice.' );
		}
		$back = self::ts( $row->expires_at ) >= time() ? self::STATUS_ACTIVE : self::STATUS_EXPIRED;
		$held = self::STATUS_ACTIVE === $back ? self::active_row( (string) $row->phone ) : null;
		if ( $held && (int) $held->id !== (int) $row->id ) {
			self::fold_into( $row, $held );
			return array( 'ok' => true, 'code' => 'merged', 'message' => 'Given back — combined into the reward they hold now.' );
		}
		if ( ( $c = self::coupon_for( $row ) ) && $c->get_usage_count() > 0 ) {
			$c->decrease_usage_count();
		}
		self::set_status( $row, $back, array( 'used_at' => null, 'used_channel' => '', 'used_ref' => '', 'used_amount' => 0 ) );
		return array( 'ok' => true, 'code' => self::STATUS_ACTIVE === $back ? 'restored' : 'expired', 'message' => self::STATUS_ACTIVE === $back ? 'Given back.' : 'Given back, but it has since expired.' );
	}

	/**
	 * What an ERP sale means for the owner programme, for the counter screen.
	 *
	 * @param array $sale Normalised ERP sale row.
	 * @return array{state:string,reason:string,coupon:string}
	 *         state: earned | not_eligible | off
	 */
	public static function sale_status( $sale ) {
		if ( ! self::enabled() ) {
			return array( 'state' => 'off', 'reason' => '', 'coupon' => '' );
		}
		$row = self::row_with_source( self::erp_key( $sale ) );
		if ( $row ) {
			return array( 'state' => 'earned', 'reason' => (string) $row->status, 'coupon' => (string) $row->coupon );
		}
		return array( 'state' => 'not_eligible', 'reason' => self::erp_ineligible( $sale ), 'coupon' => '' );
	}

	/* --------------------------------------------------------------------- *
	 * Daily: expiry and reminders
	 * --------------------------------------------------------------------- */

	public static function daily() {
		global $wpdb;
		if ( ! self::enabled() ) {
			return;
		}
		// Returned showroom sales, which the phone lookup never shows.
		self::check_erp_returns( '', array(), 40 );
		$t   = self::table();
		$now = current_time( 'mysql' );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $t WHERE status = %s AND expires_at < %s LIMIT 500", self::STATUS_ACTIVE, $now
		) ) as $row ) {
			self::set_status( $row, self::STATUS_EXPIRED );
		}
		// One reminder, 30 days before it ends.
		$soon = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + 30 * DAY_IN_SECONDS );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $t WHERE status = %s AND expires_at <= %s AND reminded_at IS NULL LIMIT 200", self::STATUS_ACTIVE, $soon
		) ) as $row ) {
			$wpdb->update( $t, array( 'reminded_at' => $now ), array( 'id' => (int) $row->id ) );
			self::notify( $row, 'reminder' );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Telling the customer
	 * --------------------------------------------------------------------- */

	private static function user_for( $phone ) {
		if ( ! class_exists( 'AUN_App_Phone' ) ) {
			return 0;
		}
		$users = AUN_App_Phone::find_users( (string) $phone );
		return ! empty( $users ) ? (int) $users[0]->ID : 0;
	}

	/**
	 * Push + in-app notice for app users; SMS when the reward is issued (and
	 * once before it ends) for everyone, so a customer who never installed the
	 * app still learns they have it.
	 *
	 * @param object $row  Ledger row.
	 * @param string $kind issued | extended | reminder | revoked.
	 */
	private static function notify( $row, $kind ) {
		if ( ! $row ) {
			return;
		}
		$s     = self::settings();
		$pct   = self::num( (float) $row->percent ) . '%';
		$date  = date_i18n( 'j M Y', self::ts( (string) $row->expires_at ) + (int) ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
		$copy  = array(
			'issued'   => array(
				"$pct off your next projector",
				"পরের প্রজেক্টরে $pct ছাড়",
				"Thank you for buying from AUN. Your reward is waiting in Rewards — valid until $date.",
				"AUN থেকে কেনার জন্য ধন্যবাদ। আপনার রিওয়ার্ড অ্যাপের রিওয়ার্ড অংশে আছে — $date পর্যন্ত।",
			),
			'extended' => array(
				'Your reward is renewed',
				'আপনার রিওয়ার্ডের মেয়াদ বেড়েছে',
				"$pct off your next projector, now valid until $date.",
				"পরের প্রজেক্টরে $pct ছাড়, এখন $date পর্যন্ত।",
			),
			'reminder' => array(
				"Your $pct reward ends soon",
				"আপনার $pct রিওয়ার্ডের মেয়াদ শীঘ্রই শেষ",
				"Use it on your next projector before $date.",
				"$date-এর আগে পরের প্রজেক্টরে ব্যবহার করুন।",
			),
			'revoked'  => array(
				'Reward withdrawn',
				'রিওয়ার্ড তুলে নেওয়া হয়েছে',
				'The order that earned it was cancelled or returned, so this reward has been withdrawn.',
				'যে অর্ডারে এটি পেয়েছিলেন তা বাতিল বা ফেরত হয়েছে, তাই রিওয়ার্ডটি তুলে নেওয়া হয়েছে।',
			),
		);
		if ( ! isset( $copy[ $kind ] ) ) {
			return;
		}
		list( $title, $title_bn, $body, $body_bn ) = $copy[ $kind ];

		$uid = (int) ( $row->user_id ?? 0 ) > 0 ? (int) $row->user_id : self::user_for( (string) $row->phone );
		if ( $uid > 0 && class_exists( 'AUN_App_Notices' ) ) {
			$id = AUN_App_Notices::create( array(
				'user_id'   => $uid,
				'type'      => 'rewards',
				'title'     => $title,
				'title_bn'  => $title_bn,
				'body'      => $body,
				'body_bn'   => $body_bn,
				'data'      => array( 'coupon' => (string) $row->coupon ),
				// One per reward, per event, per expiry — so a renewal to a new
				// date notifies, and a re-sync of the same one never does.
				'dedup_key' => 'owner:' . (int) $row->id . ':' . $kind . ':' . substr( (string) $row->expires_at, 0, 10 ),
			) );
			if ( $id && AUN_App_Notices::$last_was_new && class_exists( 'AUN_App_Push' ) && AUN_App_Push::configured() ) {
				AUN_App_Push::push_to_users(
					array( $uid ),
					array( 'title' => $title, 'title_bn' => $title_bn, 'body' => $body, 'body_bn' => $body_bn ),
					array( 'type' => 'rewards', 'notice_id' => $id )
				);
			}
		}

		// SMS: English on purpose — one Bangla character halves an SMS.
		$sms_on = ( 'issued' === $kind && $s['sms'] ) || ( 'reminder' === $kind && $s['remind_sms'] );
		if ( $sms_on && class_exists( 'AUN_App_SMS' ) && '' !== (string) $row->coupon ) {
			$local = class_exists( 'AUN_App_Referrals' ) ? AUN_App_Referrals::display_phone( (string) $row->phone ) : (string) $row->phone;
			$text  = 'issued' === $kind
				? ( '' !== $s['sms_text'] ? $s['sms_text'] : 'AUN: Thank you! Your next AUN projector is {percent} off. Code {code} (for {phone}), valid till {date}.' )
				: 'AUN: Your {percent} off your next AUN projector (code {code}) ends on {date}.';
			$text = strtr( $text, array( '{percent}' => $pct, '{code}' => (string) $row->coupon, '{phone}' => $local, '{date}' => $date ) );
			AUN_App_SMS::send( (string) $row->phone, $text );
		}
	}

	/* --------------------------------------------------------------------- *
	 * What the app is told
	 * --------------------------------------------------------------------- */

	/** The programme's terms, for the app and the guide page. */
	public static function program() {
		$s = self::settings();
		return array(
			'enabled'      => self::enabled(),
			'percent'      => (float) $s['percent'],
			'valid_months' => (int) $s['valid_months'],
			'exclude_sale' => (bool) $s['exclude_sale'],
			'showroom'     => (bool) $s['showroom'],
			'ceiling'      => (int) $s['ceiling'],
		);
	}

	/**
	 * The customer's live reward, or null.
	 *
	 * Reads the coupon too: if it was spent on the website and the status hook
	 * somehow missed it, the app must not keep offering a spent code.
	 */
	public static function for_phone( $phone ) {
		if ( '' === (string) $phone ) {
			return null;
		}
		$row = self::active_row( (string) $phone );
		if ( ! $row ) {
			return null;
		}
		$c = self::coupon_for( $row );
		if ( ! $c ) {
			return null;
		}
		if ( $c->get_usage_count() >= max( 1, (int) $c->get_usage_limit() ) ) {
			self::set_status( $row, self::STATUS_USED, array( 'used_at' => current_time( 'mysql' ), 'used_channel' => 'web' ) );
			return null;
		}
		$sources = self::sources( $row );
		$last    = end( $sources );
		return array(
			'code'         => (string) $row->coupon,
			'percent'      => (float) $row->percent,
			'expires'      => substr( (string) $row->expires_at, 0, 10 ),
			'phone'        => class_exists( 'AUN_App_Referrals' ) ? AUN_App_Referrals::display_phone( (string) $row->phone ) : '',
			'earned_from'  => $last ? (string) ( $last['product'] ?? '' ) : '',
			'earned_at'    => $last ? substr( (string) ( $last['at'] ?? '' ), 0, 10 ) : '',
			'purchases'    => count( $sources ),
		);
	}

	/* --------------------------------------------------------------------- *
	 * Admin report
	 * --------------------------------------------------------------------- */

	/** Totals for the Rewards report. */
	public static function report() {
		global $wpdb;
		$t   = self::table();
		$out = array( 'active' => 0, 'used' => 0, 'expired' => 0, 'revoked' => 0, 'given' => 0.0, 'web' => 0, 'showroom' => 0 );
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) n, SUM(used_amount) amt FROM $t GROUP BY status" ) as $r ) {
			if ( isset( $out[ $r->status ] ) ) {
				$out[ $r->status ] = (int) $r->n;
			}
			if ( self::STATUS_USED === $r->status ) {
				$out['given'] = (float) $r->amt;
			}
		}
		foreach ( (array) $wpdb->get_results( "SELECT used_channel c, COUNT(*) n FROM $t WHERE status = 'used' GROUP BY used_channel" ) as $r ) {
			if ( isset( $out[ $r->c ] ) ) {
				$out[ $r->c ] = (int) $r->n;
			}
		}
		return $out;
	}
}
