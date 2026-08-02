<?php
/**
 * App referral programme: existing customers invite friends, both are rewarded.
 *
 * TWO-SIDED and PAID ON COMPLETION, which is the shape every serious referral
 * programme uses and the reason this one is hard to game:
 *
 *   • the FRIEND gets a discount on their first order;
 *   • the REFERRER is paid only once that order is actually completed and has
 *     not been refunded.
 *
 * Rewarding at sign-up (or even at checkout) is what makes referral schemes
 * farmable: someone registers ten numbers, collects ten rewards and never
 * buys. Here the fraud has to survive a real delivery to a real address before
 * a single taka is issued — and is clawed back if the order is later refunded.
 *
 * Every threshold is an admin setting. Nothing about the discount is hardcoded.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Referrals {

	/** Claim has been made, the friend has not ordered yet. */
	const STATUS_PENDING = 'pending';

	/** Friend's order completed; the referrer has been paid. */
	const STATUS_REWARDED = 'rewarded';

	/** Order refunded/cancelled after payment, or the claim failed a check. */
	const STATUS_REVOKED = 'revoked';

	/** Order meta key holding the referral code that was used. */
	const ORDER_META_CODE = '_aun_referral_code';

	/**
	 * Code alphabet — no O/0, I/1, S/5. Codes get read aloud over the phone and
	 * retyped from a WhatsApp message; ambiguous characters turn a working code
	 * into a support ticket.
	 */
	const ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aun_app_referrals';
	}

	public static function claims_table() {
		global $wpdb;
		return $wpdb->prefix . 'aun_app_referral_claims';
	}

	/* --------------------------------------------------------------------- *
	 * Settings
	 * --------------------------------------------------------------------- */

	/**
	 * Programme settings, merged over safe defaults.
	 *
	 * Defaults are deliberately CONSERVATIVE: the programme ships switched off,
	 * so nothing can be claimed until an admin has chosen the numbers.
	 *
	 * @return array
	 */
	public static function settings() {
		$o = aun_app_api_get_options();
		return array(
			'enabled'          => ! empty( $o['referral_enabled'] ),
			// 'percent' | 'fixed'
			'friend_type'      => in_array( ( $o['referral_friend_type'] ?? 'percent' ), array( 'percent', 'fixed' ), true )
				? $o['referral_friend_type'] : 'percent',
			'friend_amount'    => max( 0, (float) ( $o['referral_friend_amount'] ?? 0 ) ),
			'referrer_amount'  => max( 0, (float) ( $o['referral_referrer_amount'] ?? 0 ) ),
			'min_order_total'  => max( 0, (float) ( $o['referral_min_order'] ?? 0 ) ),
			'monthly_cap'      => max( 0, (int) ( $o['referral_monthly_cap'] ?? 5 ) ),
			'claim_window_days' => max( 0, (int) ( $o['referral_claim_days'] ?? 30 ) ),
			'coupon_expiry_days' => max( 1, (int) ( $o['referral_expiry_days'] ?? 90 ) ),
		);
	}

	/** Whether the programme can actually run right now. */
	public static function available() {
		$s = self::settings();
		return $s['enabled']
			&& function_exists( 'wc_get_orders' )
			&& $s['friend_amount'] > 0;
	}

	/* --------------------------------------------------------------------- *
	 * Codes
	 * --------------------------------------------------------------------- */

	/**
	 * This customer's referral code, created on first request.
	 *
	 * Derived from their NAME plus random characters, because a code someone
	 * has to dictate over WhatsApp is far likelier to be used when it looks
	 * like it belongs to them.
	 *
	 * @param int $user_id User.
	 * @return string Code, or '' when unavailable.
	 */
	public static function code_for( $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( $user_id < 1 ) {
			return '';
		}

		$t = self::table();
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM $t WHERE user_id = %d", $user_id ) );
		if ( $existing ) {
			return (string) $existing;
		}

		// Up to a few attempts, in case of a collision on the random part.
		for ( $i = 0; $i < 6; $i++ ) {
			$code = self::generate_code( $user_id );
			$ok   = $wpdb->query( $wpdb->prepare(
				"INSERT INTO $t (user_id, code, created_at) VALUES (%d, %s, %s)",
				$user_id,
				$code,
				current_time( 'mysql' )
			) );
			if ( $ok ) {
				return $code;
			}
			// A concurrent request may have created one first.
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM $t WHERE user_id = %d", $user_id ) );
			if ( $existing ) {
				return (string) $existing;
			}
		}
		return '';
	}

	/**
	 * NAME-XXXX, uppercase, unambiguous characters only.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	private static function generate_code( $user_id ) {
		$name = '';
		if ( class_exists( 'AUN_App_Profile' ) ) {
			$name = (string) AUN_App_Profile::get_name( $user_id );
		}
		// Customers who signed up by phone may have no app profile name yet;
		// the WordPress display name is the next best thing, and a code that
		// looks like the person sharing it gets used more.
		if ( '' === trim( $name ) ) {
			$user = get_userdata( $user_id );
			$name = $user ? (string) $user->display_name : '';
		}
		// First word, letters only, 3–8 characters.
		$name = strtoupper( preg_replace( '/[^A-Za-z]/', '', strtok( trim( $name ), ' ' ) ) );
		if ( strlen( $name ) < 3 ) {
			$name = 'AUN';
		}
		$name = substr( $name, 0, 8 );

		$suffix = '';
		$len    = strlen( self::ALPHABET );
		for ( $i = 0; $i < 4; $i++ ) {
			$suffix .= self::ALPHABET[ random_int( 0, $len - 1 ) ];
		}
		return $name . '-' . $suffix;
	}

	/** Owner of a code, or 0. */
	public static function owner_of( $code ) {
		global $wpdb;
		$code = strtoupper( trim( (string) $code ) );
		if ( '' === $code ) {
			return 0;
		}
		$t = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM $t WHERE code = %s", $code ) );
	}

	/* --------------------------------------------------------------------- *
	 * Claiming — every fraud check lives here
	 * --------------------------------------------------------------------- */

	/**
	 * A new customer claims a friend's code.
	 *
	 * Returns the WooCommerce coupon the friend can spend, or an error. The
	 * coupon is restricted to THEIR OWN email and limited to a single use, so
	 * forwarding it to somebody else achieves nothing.
	 *
	 * @param int    $user_id Claiming customer.
	 * @param string $code    Referral code.
	 * @return array{ok:bool,code:string,message:string,coupon?:string}
	 */
	public static function claim( $user_id, $code ) {
		global $wpdb;

		$user_id = (int) $user_id;
		$code    = strtoupper( trim( (string) $code ) );

		if ( ! self::available() ) {
			return self::fail( 'unavailable', 'The referral programme is not running at the moment.' );
		}
		if ( $user_id < 1 || '' === $code ) {
			return self::fail( 'invalid', 'Please enter a valid code.' );
		}

		$s        = self::settings();
		$referrer = self::owner_of( $code );

		if ( $referrer < 1 ) {
			return self::fail( 'unknown_code', 'That code was not recognised. Please check and try again.' );
		}

		// ── 1. No self-referral, by account OR by phone ──────────────────
		// The phone check matters more than the id: the same person can create
		// a second account, but not with the same number.
		if ( $referrer === $user_id ) {
			return self::fail( 'self', 'You cannot use your own referral code.' );
		}
		$my_phone       = self::phone_of( $user_id );
		$referrer_phone = self::phone_of( $referrer );
		if ( '' !== $my_phone && $my_phone === $referrer_phone ) {
			return self::fail( 'self', 'You cannot use your own referral code.' );
		}
		if ( '' === $my_phone ) {
			return self::fail( 'no_phone', 'Add your mobile number to your account first.' );
		}

		// ── 2. One claim per phone, ever ─────────────────────────────────
		// Keyed on the PHONE, not the user id, so deleting and recreating an
		// account cannot buy a second discount.
		// REVOKED claims count too. Excluding them opened a real hole: claim,
		// order at a discount, refund the order (which revokes the claim), then
		// claim again — repeatable indefinitely. One phone, one claim, ever,
		// whatever became of it. The unique index enforces the same rule at the
		// database level.
		$claims = self::claims_table();
		$already = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $claims WHERE referred_phone = %s",
			$my_phone
		) );
		if ( $already > 0 ) {
			return self::fail( 'already_claimed', 'This number has already used a referral code.' );
		}

		// ── 3. New customers only ────────────────────────────────────────
		// The single most important rule. Without it the programme is just a
		// discount existing customers hand each other before every purchase.
		if ( self::has_purchase_history( $user_id, $my_phone ) ) {
			return self::fail(
				'existing_customer',
				'Referral codes are for first-time customers. Thank you for being with us already!'
			);
		}

		// ── 4. Claim window ──────────────────────────────────────────────
		// A code must be entered while the account is new. Otherwise someone
		// can shop for months, then apply a code retroactively.
		if ( $s['claim_window_days'] > 0 ) {
			$registered = get_userdata( $user_id );
			$age_days   = $registered
				? ( current_time( 'timestamp' ) - strtotime( $registered->user_registered ) ) / DAY_IN_SECONDS
				: 0;
			if ( $age_days > $s['claim_window_days'] ) {
				return self::fail(
					'too_late',
					sprintf(
						'Referral codes can only be used in your first %d days.',
						(int) $s['claim_window_days']
					)
				);
			}
		}

		// ── 5. The referrer's monthly cap ────────────────────────────────
		// Blunts industrial-scale farming even if every other check is passed.
		if ( $s['monthly_cap'] > 0 ) {
			$this_month = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $claims
				  WHERE referrer_user_id = %d AND status != %s AND created_at >= %s",
				$referrer,
				self::STATUS_REVOKED,
				date( 'Y-m-d H:i:s', strtotime( '-30 days', current_time( 'timestamp' ) ) )
			) );
			if ( $this_month >= $s['monthly_cap'] ) {
				return self::fail(
					'referrer_capped',
					'This code has reached its limit for now. Please try again later.'
				);
			}
		}

		// ── Issue the friend's coupon ────────────────────────────────────
		$coupon = self::create_friend_coupon( $user_id, $s );
		if ( '' === $coupon ) {
			return self::fail( 'coupon_failed', 'Could not create your discount. Please contact support.' );
		}

		$wpdb->insert( $claims, array(
			'referrer_user_id' => $referrer,
			'referred_user_id' => $user_id,
			'referred_phone'   => $my_phone,
			'code'             => $code,
			'friend_coupon'    => $coupon,
			'status'           => self::STATUS_PENDING,
			'created_at'       => current_time( 'mysql' ),
		) );

		return array(
			'ok'      => true,
			'code'    => 'claimed',
			'message' => 'Your discount is ready — it will be waiting at checkout.',
			'coupon'  => $coupon,
		);
	}

	/**
	 * Has this person bought from us before, under any identity we can see?
	 *
	 * Checks WooCommerce orders by customer id AND by billing phone, plus any
	 * registered device — someone who registered a warranty is unmistakably an
	 * existing customer even if they bought offline.
	 *
	 * @param int    $user_id User.
	 * @param string $phone   Canonical phone.
	 * @return bool
	 */
	public static function has_purchase_history( $user_id, $phone ) {
		if ( function_exists( 'wc_get_orders' ) ) {
			$by_user = wc_get_orders( array(
				'customer_id' => (int) $user_id,
				'limit'       => 1,
				'return'      => 'ids',
				'status'      => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
			) );
			if ( ! empty( $by_user ) ) {
				return true;
			}

			// Same number, different (or guest) account.
			if ( '' !== $phone && class_exists( 'AUN_App_Phone' ) ) {
				foreach ( AUN_App_Phone::variants( $phone ) as $variant ) {
					$by_phone = wc_get_orders( array(
						'billing_phone' => $variant,
						'limit'         => 1,
						'return'        => 'ids',
						'status'        => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
					) );
					if ( ! empty( $by_phone ) ) {
						return true;
					}
				}
			}
		}

		// A registered projector means they already own one of ours.
		if ( class_exists( 'AUN_App_Warranty' ) && AUN_App_Warranty::available() ) {
			$devices = AUN_App_Warranty::get_devices( $phone, (int) $user_id );
			if ( ! empty( $devices ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A single-use coupon locked to this customer's email.
	 *
	 * Both restrictions matter: single use stops one code discounting a whole
	 * household's orders, and the email lock stops it being forwarded at all.
	 *
	 * @param int   $user_id Friend.
	 * @param array $s       Settings.
	 * @return string Coupon code, or ''.
	 */
	private static function create_friend_coupon( $user_id, $s ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return '';
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}

		try {
			$code = 'WELCOME-' . strtoupper( wp_generate_password( 6, false, false ) );

			$coupon = new WC_Coupon();
			$coupon->set_code( $code );
			$coupon->set_discount_type( 'percent' === $s['friend_type'] ? 'percent' : 'fixed_cart' );
			$coupon->set_amount( (float) $s['friend_amount'] );
			$coupon->set_individual_use( true );
			$coupon->set_usage_limit( 1 );
			$coupon->set_usage_limit_per_user( 1 );
			$coupon->set_email_restrictions( array( $user->user_email ) );
			$coupon->set_date_expires(
				date( 'Y-m-d', strtotime( '+' . (int) $s['coupon_expiry_days'] . ' days', current_time( 'timestamp' ) ) )
			);
			if ( $s['min_order_total'] > 0 ) {
				$coupon->set_minimum_amount( (float) $s['min_order_total'] );
			}
			$coupon->set_description( 'AUN Care app referral — welcome discount' );
			$coupon->save();

			return $code;
		} catch ( Exception $e ) {
			return '';
		}
	}

	/* --------------------------------------------------------------------- *
	 * Qualifying and paying the referrer
	 * --------------------------------------------------------------------- */

	/**
	 * An order completed — pay the referrer if this was a referred first order.
	 *
	 * Hooked to `woocommerce_order_status_completed` ONLY. Not "processing",
	 * not "paid": completion is the point at which the goods have actually
	 * gone out, which is what makes a fake referral expensive rather than free.
	 *
	 * @param int $order_id Order.
	 */
	public static function on_order_completed( $order_id ) {
		global $wpdb;

		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return;
		}

		$claim = self::claim_for_order( $order );
		if ( ! $claim || self::STATUS_PENDING !== $claim->status ) {
			return;
		}

		$s = self::settings();

		// Minimum spend, checked against what was actually PAID rather than the
		// cart total — otherwise a big order that was mostly discounted away
		// could still trigger a reward worth more than the margin.
		if ( $s['min_order_total'] > 0 && (float) $order->get_total() < $s['min_order_total'] ) {
			return; // stays pending: a later, larger order can still qualify
		}

		$reward = '';
		if ( $s['referrer_amount'] > 0 ) {
			$reward = self::create_referrer_reward( (int) $claim->referrer_user_id, $s );
			if ( '' === $reward ) {
				return; // leave pending and try again rather than silently losing it
			}
		}

		$wpdb->update(
			self::claims_table(),
			array(
				'status'         => self::STATUS_REWARDED,
				'order_id'       => (int) $order->get_id(),
				'reward_coupon'  => $reward,
				'rewarded_at'    => current_time( 'mysql' ),
			),
			array( 'id' => (int) $claim->id )
		);

		self::notify_referrer( (int) $claim->referrer_user_id, $reward, $s );
	}

	/**
	 * The order was refunded or cancelled after the referrer was paid — take
	 * the reward back.
	 *
	 * Without this, "order, collect, refund" is a free money machine. The
	 * coupon is deleted rather than merely marked used, so it stops working
	 * even if the customer already has the code.
	 *
	 * @param int $order_id Order.
	 */
	public static function on_order_reversed( $order_id ) {
		global $wpdb;

		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return;
		}
		$claim = self::claim_for_order( $order );
		if ( ! $claim || self::STATUS_REWARDED !== $claim->status ) {
			return;
		}

		if ( ! empty( $claim->reward_coupon ) ) {
			$code      = (string) $claim->reward_coupon;
			$coupon_id = wc_get_coupon_id_by_code( $code );
			if ( $coupon_id ) {
				wp_delete_post( $coupon_id, true );
				// WooCommerce caches code -> id lookups, so without this the
				// destroyed coupon can still resolve and keep working.
				wp_cache_delete( WC_Cache_Helper::get_cache_prefix( 'coupons' ) . 'coupon_id_from_code_' . $code, 'coupons' );
				WC_Cache_Helper::get_transient_version( 'coupons', true );
			}
		}

		$wpdb->update(
			self::claims_table(),
			array( 'status' => self::STATUS_REVOKED ),
			array( 'id' => (int) $claim->id )
		);
	}

	/**
	 * Find the referral claim an order belongs to.
	 *
	 * Matched by the CLAIM's own coupon appearing on the order, so a referred
	 * customer's second order cannot trigger a second reward.
	 *
	 * @param WC_Order $order Order.
	 * @return object|null
	 */
	private static function claim_for_order( $order ) {
		global $wpdb;
		$claims = self::claims_table();

		$used = array_map( 'strtoupper', (array) $order->get_coupon_codes() );
		if ( $used ) {
			$ph  = implode( ',', array_fill( 0, count( $used ), '%s' ) );
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $claims WHERE UPPER(friend_coupon) IN ($ph) LIMIT 1",
				$used
			) );
			if ( $row ) {
				return $row;
			}
		}

		// Fall back to the buyer: an order placed without the coupon still
		// counts, since the referral did its job of bringing them in.
		$customer_id = (int) $order->get_customer_id();
		if ( $customer_id > 0 ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $claims WHERE referred_user_id = %d AND status = %s LIMIT 1",
				$customer_id,
				self::STATUS_PENDING
			) );
		}
		return null;
	}

	/**
	 * The referrer's reward: a fixed-cart coupon for their own account.
	 *
	 * @param int   $user_id Referrer.
	 * @param array $s       Settings.
	 * @return string Coupon code, or ''.
	 */
	private static function create_referrer_reward( $user_id, $s ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return '';
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}
		try {
			$code   = 'THANKS-' . strtoupper( wp_generate_password( 6, false, false ) );
			$coupon = new WC_Coupon();
			$coupon->set_code( $code );
			$coupon->set_discount_type( 'fixed_cart' );
			$coupon->set_amount( (float) $s['referrer_amount'] );
			$coupon->set_usage_limit( 1 );
			$coupon->set_usage_limit_per_user( 1 );
			$coupon->set_email_restrictions( array( $user->user_email ) );
			$coupon->set_date_expires(
				date( 'Y-m-d', strtotime( '+' . (int) $s['coupon_expiry_days'] . ' days', current_time( 'timestamp' ) ) )
			);
			// The same minimum as the friend's coupon. Without it a ৳500 reward
			// could be spent on a ৳600 cable — a ~83% discount on an accessory,
			// which is not what "৳500 off" was meant to mean.
			if ( $s['min_order_total'] > 0 ) {
				$coupon->set_minimum_amount( (float) $s['min_order_total'] );
			}
			$coupon->set_description( 'AUN Care app referral — thank-you reward' );
			$coupon->save();
			return $code;
		} catch ( Exception $e ) {
			return '';
		}
	}

	/** Tell the referrer they have been paid. */
	private static function notify_referrer( $user_id, $reward, $s ) {
		if ( ! class_exists( 'AUN_App_Notices' ) || '' === $reward ) {
			return;
		}
		$amount = number_format_i18n( (float) $s['referrer_amount'], 0 );

		AUN_App_Notices::create( array(
			'user_id'   => (int) $user_id,
			'type'      => 'referral',
			'title'     => 'Your friend ordered — ৳' . $amount . ' is yours',
			'title_bn'  => 'আপনার বন্ধু অর্ডার করেছেন — ৳' . $amount . ' আপনার',
			'body'      => 'Thank you for recommending us. Use code ' . $reward . ' on your next order.',
			'body_bn'   => 'আমাদের সুপারিশ করার জন্য ধন্যবাদ। পরের অর্ডারে ' . $reward . ' কোডটি ব্যবহার করুন।',
			'data'      => array( 'coupon' => $reward ),
			'dedup_key' => 'referral_reward:' . $reward,
		) );

		if ( class_exists( 'AUN_App_Push' ) && AUN_App_Push::configured() && AUN_App_Notices::$last_was_new ) {
			AUN_App_Push::push_to_users(
				array( (int) $user_id ),
				array(
					'title'    => 'Your friend ordered — ৳' . $amount . ' is yours',
					'title_bn' => 'আপনার বন্ধু অর্ডার করেছেন — ৳' . $amount . ' আপনার',
					'body'     => 'Tap to see your reward code.',
					'body_bn'  => 'রিওয়ার্ড কোড দেখতে ট্যাপ করুন।',
				),
				array( 'type' => 'referral', 'coupon' => $reward )
			);
		}
	}

	/* --------------------------------------------------------------------- *
	 * Reporting
	 * --------------------------------------------------------------------- */

	/**
	 * What the app shows on the referral screen.
	 *
	 * @param int $user_id User.
	 * @return array
	 */
	public static function summary( $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;
		$claims  = self::claims_table();
		$s       = self::settings();

		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT status, reward_coupon, rewarded_at FROM $claims
			  WHERE referrer_user_id = %d ORDER BY id DESC LIMIT 50",
			$user_id
		) );

		$invited  = 0;
		$rewarded = 0;
		$coupons  = array();
		foreach ( $rows as $r ) {
			if ( self::STATUS_REVOKED === $r->status ) {
				continue;
			}
			$invited++;
			if ( self::STATUS_REWARDED === $r->status ) {
				$rewarded++;
				if ( ! empty( $r->reward_coupon ) ) {
					$coupons[] = array(
						'code' => (string) $r->reward_coupon,
						'date' => substr( (string) $r->rewarded_at, 0, 10 ),
					);
				}
			}
		}

		// The customer's OWN welcome coupon, if they claimed someone's code.
		// Without this it was shown once in a snackbar and then lost — they had
		// no way to find the code again when they reached checkout.
		$mine = $wpdb->get_row( $wpdb->prepare(
			"SELECT friend_coupon, status FROM $claims WHERE referred_user_id = %d LIMIT 1",
			$user_id
		) );
		$my_coupon = '';
		if ( $mine && ! empty( $mine->friend_coupon )
			&& self::STATUS_REVOKED !== $mine->status ) {
			// Only offer it while it can still be spent.
			$cid = function_exists( 'wc_get_coupon_id_by_code' )
				? (int) wc_get_coupon_id_by_code( (string) $mine->friend_coupon ) : 0;
			if ( $cid > 0 ) {
				$c = new WC_Coupon( $cid );
				if ( 0 === (int) $c->get_usage_count() ) {
					$my_coupon = (string) $mine->friend_coupon;
				}
			}
		}

		return array(
			'enabled'         => self::available(),
			'code'            => self::available() ? self::code_for( $user_id ) : '',
			'my_coupon'       => $my_coupon,
			'friend_type'     => $s['friend_type'],
			'friend_amount'   => $s['friend_amount'],
			'referrer_amount' => $s['referrer_amount'],
			'invited'         => $invited,
			'rewarded'        => $rewarded,
			'rewards'         => $coupons,
			// Whether THIS user may still claim someone else's code, so the app
			// can hide an entry field that would only ever fail.
			'can_claim'       => self::available() && ! self::has_claimed( $user_id ),
		);
	}

	/** Has this user already used somebody's code? */
	public static function has_claimed( $user_id ) {
		global $wpdb;
		$phone = self::phone_of( (int) $user_id );
		if ( '' === $phone ) {
			return false;
		}
		// Includes revoked claims — see the matching note in claim().
		$claims = self::claims_table();
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $claims WHERE referred_phone = %s",
			$phone
		) ) > 0;
	}

	/* --------------------------------------------------------------------- *
	 * Helpers
	 * --------------------------------------------------------------------- */

	private static function phone_of( $user_id ) {
		if ( ! class_exists( 'AUN_App_Phone' ) ) {
			return '';
		}
		$p = AUN_App_Phone::user_phone( (int) $user_id );
		return $p ? (string) $p : '';
	}

	private static function fail( $code, $message ) {
		return array( 'ok' => false, 'code' => $code, 'message' => $message );
	}
}
