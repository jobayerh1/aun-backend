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
	 * Coupon meta marking a coupon as a referrer's earned REWARD.
	 *
	 * Needed because rewards are allowed to stack with each other while still
	 * refusing to stack with anything else in the shop — see
	 * `allow_reward_stacking()`. Identity by meta, not by code prefix, so
	 * renaming the prefix cannot quietly change who may combine with whom.
	 */
	const COUPON_META_REWARD = '_aun_referral_reward';

	/**
	 * Coupon meta: the canonical phone the coupon was issued to.
	 *
	 * A referral coupon is otherwise a bearer token — whoever types the code
	 * first gets the discount, because WooCommerce has no "belongs to this
	 * account" restriction and the app's identity is a phone the website
	 * checkout knows nothing about. This meta is what ties the two together.
	 */
	const COUPON_META_PHONE = '_aun_referral_phone';

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
			'referrer_type'    => in_array( ( $o['referral_referrer_type'] ?? 'fixed' ), array( 'percent', 'fixed' ), true )
				? $o['referral_referrer_type'] : 'fixed',
			// Only customers may invite. Someone who has never bought anything
			// can otherwise mint discount codes for the world.
			'require_customer' => ! isset( $o['referral_require_customer'] ) || ! empty( $o['referral_require_customer'] ),
			'min_order_total'  => max( 0, (float) ( $o['referral_min_order'] ?? 0 ) ),
			// Slug WITHOUT the wc- prefix, e.g. 'completed' or 'delivered'.
			'reward_status'    => self::clean_status( $o['referral_reward_status'] ?? 'completed' ),
			// 0 = the referrer's reward never expires.
			'reward_expiry_days' => max( 0, (int) ( $o['referral_reward_expiry_days'] ?? 365 ) ),
			// Also pay when the order reaches Completed, on top of the status
			// chosen above. OFF by default — see payout_statuses().
			'reward_also_completed' => ! empty( $o['referral_reward_also_completed'] ),
			'monthly_cap'      => max( 0, (int) ( $o['referral_monthly_cap'] ?? 5 ) ),
			'claim_window_days' => max( 0, (int) ( $o['referral_claim_days'] ?? 30 ) ),
			'coupon_expiry_days' => max( 1, (int) ( $o['referral_expiry_days'] ?? 90 ) ),
		);
	}

	/**
	 * When this customer joined the APP, as a timestamp.
	 *
	 * `aun_app_signup` is stamped on first app login (see AUN_App_REST), which
	 * is the date that actually matters here. The WordPress `user_registered`
	 * date is only a fallback for a row somehow missing the meta — using it as
	 * the primary measure refused people who had made a website account years
	 * ago, never bought anything, and were installing the app for the first
	 * time: exactly the new customers the programme exists to attract.
	 *
	 * @param int $user_id User.
	 * @return int Unix timestamp, or 0 when unknown.
	 */
	public static function joined_at( $user_id ) {
		$stamp = (string) get_user_meta( (int) $user_id, 'aun_app_signup', true );
		if ( '' !== $stamp ) {
			return (int) strtotime( $stamp );
		}
		$u = get_userdata( (int) $user_id );
		return $u ? (int) strtotime( $u->user_registered ) : 0;
	}

	/** How many whole days since they joined the app. */
	public static function days_since_joined( $user_id ) {
		$joined = self::joined_at( $user_id );
		if ( $joined < 1 ) {
			return 0;
		}
		return ( current_time( 'timestamp' ) - $joined ) / DAY_IN_SECONDS;
	}

	/**
	 * The last date this customer may redeem a code ('YYYY-MM-DD'), or '' when
	 * there is no window at all.
	 *
	 * Sent to the app so the deadline can be STATED rather than discovered the
	 * day it passes.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	public static function claim_deadline( $user_id ) {
		$s = self::settings();
		if ( $s['claim_window_days'] < 1 ) {
			return '';
		}
		$joined = self::joined_at( $user_id );
		if ( $joined < 1 ) {
			return '';
		}
		return date( 'Y-m-d', $joined + ( (int) $s['claim_window_days'] * DAY_IN_SECONDS ) );
	}

	/** Normalise an order status to a bare slug ('wc-delivered' → 'delivered'). */
	public static function clean_status( $status ) {
		$status = sanitize_key( (string) $status );
		return '' === $status ? 'completed' : preg_replace( '/^wc-/', '', $status );
	}

	/**
	 * Order statuses that count as "this money is real".
	 *
	 * The configured payout status, plus anything beyond it in the shop's own
	 * ordering is NOT assumed — a store can define statuses in any order, so
	 * only the exact configured status pays. `completed` is always accepted as
	 * well, because a shop that later marks a delivered order complete must
	 * not have the reward silently stop working.
	 *
	 * @return string[] Bare slugs.
	 */
	public static function payout_statuses() {
		$s   = self::settings();
		$out = array( $s['reward_status'] );

		// `completed` used to be added here ALWAYS, "so an order you later mark
		// complete is never stranded". That was wrong, and it quietly defeated
		// the setting above.
		//
		// Shipment plugins — AST Pro on this store — commonly flip an order to
		// Completed at the moment it is marked Shipped. With `completed` always
		// paying, choosing "Delivered" changed nothing: the reward went out at
		// dispatch, before the parcel had been accepted, which is precisely the
		// refused-cash-on-delivery hole the setting exists to close.
		//
		// So the admin's choice is now honoured exactly. A shop that genuinely
		// wants both can tick the box; the default is off, because a payout
		// rule that fires on a status you did not choose is worse than one that
		// occasionally needs a second click.
		if ( ! empty( $s['reward_also_completed'] ) && ! in_array( 'completed', $out, true ) ) {
			$out[] = 'completed';
		}
		return $out;
	}

	/**
	 * May this customer invite anyone?
	 *
	 * By default only people who have actually bought from us can. A referral
	 * from someone who has never owned a projector is not a recommendation —
	 * and without this rule anyone can register, mint a code and hand
	 * discounts to the world.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function can_invite( $user_id ) {
		$s = self::settings();
		if ( ! $s['require_customer'] ) {
			return true;
		}
		$phone = self::phone_of( (int) $user_id );
		// NOT has_purchase_history(): see is_established_customer(). Inviting
		// requires a purchase that actually stuck.
		return self::is_established_customer( (int) $user_id, $phone );
	}

	/**
	 * Has this person really bought from us — money settled, goods received?
	 *
	 * Deliberately STRICTER than has_purchase_history(), and the distinction
	 * matters because the two are used in opposite risk directions:
	 *
	 *   • has_purchase_history() decides who is REFUSED a welcome discount.
	 *     It should be broad — a pending order is still enough to say "you are
	 *     not a new customer", and being generous there would hand discounts
	 *     to people who already shop with us.
	 *
	 *   • this decides who is ALLOWED to mint invite codes. It must be narrow.
	 *     Sharing one function meant a cash-on-delivery order became an invite
	 *     licence the moment it was placed: order at 10am, mint a code, invite
	 *     the neighbourhood, refuse the parcel at the door. Nothing was ever
	 *     paid and nothing was ever received, but the codes stayed live.
	 *
	 * A registered projector still counts on its own — that is a serial number
	 * we sold, verified independently of any order.
	 *
	 * @param int    $user_id User.
	 * @param string $phone   Canonical phone.
	 * @return bool
	 */
	public static function is_established_customer( $user_id, $phone ) {
		if ( function_exists( 'wc_get_orders' ) ) {
			$want     = self::payout_statuses();
			$statuses = array_map(
				function ( $s ) {
					return 'wc-' . $s;
				},
				$want
			);

			// Every result is re-checked against its OWN status, and that is
			// not paranoia. `wc_get_orders()` quietly ignores a status filter
			// naming a status WooCommerce does not have registered — so if the
			// shipment plugin providing "delivered" is ever deactivated, or the
			// setting names a status that no longer exists, the filter becomes
			// "any order at all" and every customer looks established. A
			// silent widening is the worst kind of failure in a rule that
			// decides who may mint discount codes.
			$verify = static function ( $orders ) use ( $want ) {
				foreach ( (array) $orders as $o ) {
					if ( is_a( $o, 'WC_Order' ) && in_array( $o->get_status(), $want, true ) ) {
						return true;
					}
				}
				return false;
			};

			if ( $verify( wc_get_orders( array(
				'customer_id' => (int) $user_id,
				'limit'       => 5,
				'status'      => $statuses,
			) ) ) ) {
				return true;
			}

			if ( '' !== $phone && class_exists( 'AUN_App_Phone' ) ) {
				foreach ( AUN_App_Phone::variants( $phone ) as $variant ) {
					if ( $verify( wc_get_orders( array(
						'billing_phone' => $variant,
						'limit'         => 5,
						'status'        => $statuses,
					) ) ) ) {
						return true;
					}
				}
			}
		}

		// A registered projector is a serial we sold. That is proof enough on
		// its own, and is how offline buyers become inviters at all.
		if ( class_exists( 'AUN_App_Warranty' ) && AUN_App_Warranty::available() ) {
			$devices = AUN_App_Warranty::get_devices( $phone, (int) $user_id );
			if ( ! empty( $devices ) ) {
				return true;
			}
		}

		return false;
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

		// ── 0. The REFERRER must be allowed to invite ────────────────────
		// Enforced here, not just hidden in the app: the endpoint is the real
		// boundary, and a code shared before the setting changed must stop
		// working the moment it does.
		if ( ! self::can_invite( $referrer ) ) {
			return self::fail(
				'referrer_not_customer',
				'That code is not active. Invite codes come from AUN projector owners.'
			);
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
		//
		// A designated TEST line skips this one rule, and only this one: the
		// shop's own SIMs have order history, so otherwise the friend side of
		// the programme can never be walked end to end. Everything below and
		// above still applies to them.
		if ( ! self::is_test_phone( $my_phone ) && self::has_purchase_history( $user_id, $my_phone ) ) {
			return self::fail(
				'existing_customer',
				'Referral codes are for first-time customers. Thank you for being with us already!'
			);
		}

		// ── 4. Claim window ──────────────────────────────────────────────
		// A code must be entered while the account is new. Otherwise someone
		// can shop for months, then apply a code retroactively.
		if ( $s['claim_window_days'] > 0 ) {
			$age_days = self::days_since_joined( $user_id );
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
		// Locked to the number that just passed OTP — see COUPON_META_PHONE.
		$coupon = self::create_friend_coupon( $user_id, $s, $my_phone );
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

		// Immediate feedback to the referrer. Waiting until the friend's order
		// completes leaves them with no sign the code ever worked, which is
		// exactly when people conclude the programme is broken.
		self::notify_claimed( $referrer );

		return array(
			'ok'      => true,
			'code'    => 'claimed',
			'message' => 'Your discount is ready — it will be waiting at checkout.',
			'coupon'  => $coupon,
			// So the app can say, at the moment it matters most, which number
			// this coupon will work with. Finding that out at checkout instead
			// is how a good discount becomes a support message.
			'phone'   => self::display_phone( $my_phone ),
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
	 * The best email we actually have for an app customer.
	 *
	 * App accounts are created from a PHONE — `create_user()` never passes a
	 * `user_email`, so `$user->user_email` is an empty string for anyone who
	 * has only ever used the app, and the real address (when they have given
	 * one) lives in app profile meta, deliberately away from the WP account.
	 *
	 * @param int $user_id User.
	 * @return string Email, or '' when we genuinely have none.
	 */
	private static function customer_email( $user_id ) {
		$email = '';
		if ( class_exists( 'AUN_App_Profile' ) ) {
			$email = AUN_App_Profile::get_email( (int) $user_id );
		}
		if ( '' === $email ) {
			$user  = get_userdata( (int) $user_id );
			$email = $user ? (string) $user->user_email : '';
		}
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Strip an unmatchable email restriction from an already-issued coupon.
	 *
	 * Coupons created before customer_email() existed are locked to '' and can
	 * never be spent. They are live customers' real rewards, so they are healed
	 * where they are read rather than written off — no migration to run, and a
	 * coupon that was already valid is left exactly as it was.
	 *
	 * @param WC_Coupon $coupon Coupon.
	 * @return void
	 */
	private static function heal_coupon_restrictions( $coupon ) {
		$current = (array) $coupon->get_email_restrictions();
		$valid   = array_values( array_filter(
			array_map( 'trim', $current ),
			'is_email'
		) );
		if ( count( $valid ) !== count( $current ) ) {
			$coupon->set_email_restrictions( $valid );
			$coupon->save();
		}
	}

	/**
	 * A single-use coupon for this customer.
	 *
	 * Single use stops one code discounting a whole household's orders. The
	 * email lock stops it being forwarded — but ONLY when we have an email to
	 * lock it to.
	 *
	 * That distinction was a programme-breaking bug: app accounts are created
	 * from a phone number and carry NO `user_email`, so this locked every app
	 * customer's coupon to the empty string. WooCommerce treats a non-empty
	 * restriction array as a real restriction and compares it against the
	 * billing email at checkout, which nothing can ever match — so every
	 * referral coupon the app issued was rejected at the till. The friend
	 * redeemed a code, was told their discount was ready, and then could not
	 * spend it. Restrict only when the restriction can succeed.
	 *
	 * @param int   $user_id Friend.
	 * @param array $s       Settings.
	 * @return string Coupon code, or ''.
	 */
	private static function create_friend_coupon( $user_id, $s, $phone = '' ) {
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
			// The PHONE is the binding — one lock, OTP-verified, and the same
			// one on both coupons. The old email restriction bound nothing on
			// an app account (no user_email) and bound the wrong thing when it
			// did, since that address is not what they type at checkout.
			$coupon->set_date_expires(
				date( 'Y-m-d', strtotime( '+' . (int) $s['coupon_expiry_days'] . ' days', current_time( 'timestamp' ) ) )
			);
			if ( $s['min_order_total'] > 0 ) {
				$coupon->set_minimum_amount( (float) $s['min_order_total'] );
			}
			$coupon->set_description( 'AUN Care app referral — welcome discount' );

			// The lock. Set BEFORE save so the coupon is never briefly loose.
			$phone = (string) $phone;
			if ( '' !== $phone ) {
				$coupon->update_meta_data( self::COUPON_META_PHONE, $phone );
			}

			$coupon->save();

			return $code;
		} catch ( Exception $e ) {
			return '';
		}
	}

	/* --------------------------------------------------------------------- *
	 * The phone lock: a referral coupon belongs to ONE number
	 * --------------------------------------------------------------------- */

	/**
	 * The phone a coupon is locked to, or '' when it carries no lock.
	 *
	 * Coupons issued before the lock existed have no meta and stay unlocked
	 * on purpose: retro-locking a code someone is already holding would break
	 * a promise we already made.
	 *
	 * @param WC_Coupon $coupon Coupon.
	 * @return string Canonical 8801XXXXXXXXX, or ''.
	 */
	/**
	 * A canonical number as a Bangladeshi customer writes it: 01XXXXXXXXX.
	 *
	 * Formatting lives here, not in the app, so the number the app promises
	 * and the number the checkout compares can never drift apart.
	 *
	 * @param string $canonical 8801XXXXXXXXX.
	 * @return string
	 */
	public static function display_phone( $canonical ) {
		$canonical = (string) $canonical;
		return 0 === strpos( $canonical, '880' ) ? substr( $canonical, 2 ) : $canonical;
	}

	/**
	 * A coupon's expiry as a plain ISO date, or '' when it never expires.
	 *
	 * The app formats it for the customer's own language, so the wire format
	 * stays machine-readable and the phone's clock is never asked to interpret
	 * a pre-rendered string.
	 *
	 * @param WC_Coupon $coupon Coupon.
	 * @return string 'YYYY-MM-DD' or ''.
	 */
	public static function coupon_expiry( $coupon ) {
		if ( ! is_object( $coupon ) || ! method_exists( $coupon, 'get_date_expires' ) ) {
			return '';
		}
		$d = $coupon->get_date_expires();
		return $d ? $d->date( 'Y-m-d' ) : '';
	}

	public static function coupon_phone( $coupon ) {
		if ( ! is_object( $coupon ) || ! method_exists( $coupon, 'get_meta' ) ) {
			return '';
		}
		return (string) $coupon->get_meta( self::COUPON_META_PHONE );
	}

	/**
	 * Does what the customer typed at checkout mean the same number?
	 *
	 * This is the whole point of canonicalising. `+880 1712-345678`,
	 * `01712345678`, `1712345678` and `8801712345678` are one number written
	 * five ways, and a customer who writes it differently from how they typed
	 * it into the app has done nothing wrong.
	 *
	 * @param string $canonical Stored 8801XXXXXXXXX.
	 * @param string $typed     Whatever was entered at checkout.
	 * @return bool
	 */
	public static function phone_matches( $canonical, $typed ) {
		if ( ! class_exists( 'AUN_App_Phone' ) || '' === (string) $canonical ) {
			return false;
		}

		// Strip an international dialling prefix before canonicalising.
		// AUN_App_Phone::normalize() understands +880/880/01/1 but not the
		// 00880 form, and that IS how some people write it. Cleaned here
		// rather than in the shared phone class, which OTP login depends on.
		$typed = preg_replace( '/^\s*00/', '', (string) $typed );

		$typed = AUN_App_Phone::normalize( $typed );
		return $typed && (string) $typed === (string) $canonical;
	}

	/**
	 * The refusal message. Says WHY, and shows enough of the number to be
	 * recognised by its owner without handing a stranger the whole thing.
	 *
	 * @param string $canonical Locked number.
	 * @param bool   $missing   True when no phone was entered at all.
	 * @return string
	 */
	private static function lock_message( $canonical, $missing = false ) {
		$masked = class_exists( 'AUN_App_Phone' )
			? AUN_App_Phone::mask( $canonical ) : '';

		if ( $missing ) {
			return sprintf(
				/* translators: %s: masked phone number. */
				__( 'This referral discount belongs to the mobile number it was issued to (%s). Please enter that number in the Phone field to use it.', 'aun-app-api' ),
				$masked
			);
		}

		return sprintf(
			/* translators: %s: masked phone number. */
			__( 'This referral discount can only be used by the person it was issued to — the mobile number %s, which was verified in the AUN Care app. Please check out with that number, or remove the coupon to continue.', 'aun-app-api' ),
			$masked
		);
	}

	/* --------------------------------------------------------------------- *
	 * Testing the programme with a real number
	 * --------------------------------------------------------------------- */

	/**
	 * Numbers the admin has marked as test lines.
	 *
	 * These bypass ONE rule: "first-time customers only". Nothing else. The
	 * shop owner's own two SIMs have order history and registered projectors,
	 * so without this they can never reach the friend side of the programme
	 * and can only test half of it.
	 *
	 * Self-referral, the reward rules, the payout-on-completion rule and the
	 * refund clawback all still apply to a test number, because those are the
	 * parts worth testing.
	 *
	 * @return string[] Canonical numbers.
	 */
	public static function test_phones() {
		$o   = aun_app_api_get_options();
		$out = array();
		foreach ( preg_split( '/[\r\n,]+/', (string) ( $o['referral_test_phones'] ?? '' ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || ! class_exists( 'AUN_App_Phone' ) ) {
				continue;
			}
			$c = AUN_App_Phone::normalize( $line );
			if ( $c ) {
				$out[] = $c;
			}
		}
		return array_unique( $out );
	}

	/** Is this a designated test line? */
	public static function is_test_phone( $phone ) {
		$phone = (string) $phone;
		return '' !== $phone && in_array( $phone, self::test_phones(), true );
	}

	/**
	 * Wipe every trace of the referral programme for one number, so the whole
	 * flow can be walked again from the start.
	 *
	 * Deliberately narrow. It touches referral tables and the coupons this
	 * programme issued — **never** orders, devices, warranties, tickets or the
	 * user account. A test-reset tool that could delete a real customer's
	 * purchase history would be far more dangerous than the inconvenience it
	 * saves.
	 *
	 * @param string $raw_phone Any format.
	 * @param bool   $dry_run   True = report only, change nothing.
	 * @return array {ok, message, claims, invites, coupons[], phone}
	 */
	public static function reset_for_phone( $raw_phone, $dry_run = true ) {
		global $wpdb;

		$out = array( 'ok' => false, 'message' => '', 'claims' => 0, 'invites' => 0, 'coupons' => array(), 'phone' => '' );

		if ( ! class_exists( 'AUN_App_Phone' ) ) {
			$out['message'] = 'Phone helper missing.';
			return $out;
		}
		$phone = AUN_App_Phone::normalize( $raw_phone );
		if ( ! $phone ) {
			$out['message'] = 'That is not a valid Bangladeshi mobile number.';
			return $out;
		}
		$out['phone'] = self::display_phone( $phone );

		// Every account that answers to this number — a number can have picked
		// up more than one over time, and a reset that missed one would leave
		// the tester blocked by a row they cannot see.
		$uids = array();
		foreach ( AUN_App_Phone::find_users( $phone ) as $u ) {
			$uids[] = (int) $u->ID;
		}

		$claims = self::claims_table();
		$codes  = self::table();

		// Their claim as a FRIEND (keyed by phone, matching has_claimed).
		$as_friend = (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT id, friend_coupon, reward_coupon FROM $claims WHERE referred_phone = %s",
			$phone
		) );

		// Their claims as a REFERRER, so the inviting side can be replayed too.
		$as_referrer = array();
		if ( ! empty( $uids ) ) {
			$in          = implode( ',', array_fill( 0, count( $uids ), '%d' ) );
			$as_referrer = (array) $wpdb->get_results( $wpdb->prepare(
				"SELECT id, friend_coupon, reward_coupon FROM $claims WHERE referrer_user_id IN ($in)",
				$uids
			) );
		}

		$out['claims']  = count( $as_friend );
		$out['invites'] = count( $as_referrer );

		$coupon_codes = array();
		foreach ( array_merge( $as_friend, $as_referrer ) as $row ) {
			foreach ( array( $row->friend_coupon, $row->reward_coupon ) as $c ) {
				if ( '' !== (string) $c ) {
					$coupon_codes[] = (string) $c;
				}
			}
		}
		$coupon_codes    = array_values( array_unique( $coupon_codes ) );
		$out['coupons']  = $coupon_codes;

		if ( $dry_run ) {
			$out['ok'] = true;
			return $out;
		}

		// ── Delete for real ──────────────────────────────────────────────
		$ids = array();
		foreach ( array_merge( $as_friend, $as_referrer ) as $row ) {
			$ids[] = (int) $row->id;
		}
		$ids = array_values( array_unique( $ids ) );
		if ( ! empty( $ids ) ) {
			$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM $claims WHERE id IN ($in)", $ids ) );
		}

		foreach ( $coupon_codes as $code ) {
			if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
				break;
			}
			$cid = (int) wc_get_coupon_id_by_code( $code );
			if ( $cid > 0 ) {
				wp_delete_post( $cid, true );
				// WooCommerce caches code -> id, so a destroyed coupon can
				// otherwise still resolve and keep working.
				wp_cache_delete(
					WC_Cache_Helper::get_cache_prefix( 'coupons' ) . 'coupon_id_from_code_' . $code,
					'coupons'
				);
			}
		}
		if ( ! empty( $coupon_codes ) && class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::get_transient_version( 'coupons', true );
		}

		// Their own invite code, so the next test mints a fresh one.
		if ( ! empty( $uids ) ) {
			$in = implode( ',', array_fill( 0, count( $uids ), '%d' ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM $codes WHERE user_id IN ($in)", $uids ) );
		}

		$out['ok']      = true;
		$out['message'] = sprintf(
			'Reset %s: removed %d claim(s) as a friend, %d as a referrer, %d coupon(s), and their invite code. Orders, devices and warranties were not touched.',
			$out['phone'],
			count( $as_friend ),
			count( $as_referrer ),
			count( $coupon_codes )
		);
		return $out;
	}

	/**
	 * Admin override: release the phone lock on one coupon.
	 *
	 * The lock is right almost always and wrong occasionally — a customer who
	 * checks out under a spouse's or a relative's number is refused correctly
	 * by the rule and unfairly in fact. Support needs a way to say "this one is
	 * fine" that does not involve weakening the rule for everyone, and does not
	 * involve editing post meta by hand.
	 *
	 * Releases only. There is deliberately no "lock it to a different number":
	 * the only number we can vouch for is the one that passed OTP, and letting
	 * an admin type a new one would turn a verified fact into a typo.
	 *
	 * @param string $code Coupon code.
	 * @return array {ok:bool, message:string}
	 */
	public static function unlock_coupon( $code ) {
		$code = trim( (string) $code );
		if ( '' === $code ) {
			return array( 'ok' => false, 'message' => 'Enter a coupon code first.' );
		}
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return array( 'ok' => false, 'message' => 'WooCommerce is not active.' );
		}

		$id = (int) wc_get_coupon_id_by_code( $code );
		if ( $id < 1 ) {
			return array(
				'ok'      => false,
				'message' => sprintf( 'No coupon called "%s" exists.', $code ),
			);
		}

		$coupon = new WC_Coupon( $id );
		$locked = self::coupon_phone( $coupon );
		if ( '' === $locked ) {
			return array(
				'ok'      => true,
				'message' => sprintf( 'Coupon %s was not locked to a phone number — it already works with any number.', $coupon->get_code() ),
			);
		}

		$coupon->delete_meta_data( self::COUPON_META_PHONE );
		$coupon->save();

		return array(
			'ok'      => true,
			'message' => sprintf(
				'Released. Coupon %s was locked to %s and can now be used with any phone number. Its single-use limit, expiry and minimum order are unchanged.',
				$coupon->get_code(),
				self::display_phone( $locked )
			),
		);
	}

	/**
	 * What a coupon's lock currently is, for the admin to look at before
	 * deciding. Returns '' for "no such coupon" and '—' for "no lock", which
	 * the caller distinguishes; a missing coupon and a free one are different
	 * answers and collapsing them is how support ends up releasing a lock on
	 * a coupon that never existed.
	 *
	 * @param string $code Coupon code.
	 * @return array {found:bool, locked:string}
	 */
	public static function coupon_lock_status( $code ) {
		$code = trim( (string) $code );
		if ( '' === $code || ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return array( 'found' => false, 'locked' => '' );
		}
		$id = (int) wc_get_coupon_id_by_code( $code );
		if ( $id < 1 ) {
			return array( 'found' => false, 'locked' => '' );
		}
		$locked = self::coupon_phone( new WC_Coupon( $id ) );
		return array(
			'found'  => true,
			'locked' => '' !== $locked ? self::display_phone( $locked ) : '',
		);
	}

	/**
	 * Cart/checkout validation, the LENIENT half.
	 *
	 * Runs whenever WooCommerce validates a coupon. It deliberately allows a
	 * coupon through when no phone is known yet: people apply the code before
	 * they fill the checkout form, and refusing it there would look like the
	 * code is broken. The strict gate is at order placement below.
	 *
	 * @param bool      $valid  Current verdict.
	 * @param WC_Coupon $coupon Coupon.
	 * @return bool
	 * @throws Exception When the known phone does not match — WooCommerce turns
	 *                   the message into the notice the customer sees.
	 */
	/**
	 * Warn when applied rewards are worth more than this cart can use.
	 *
	 * Measured behaviour, not theory: a ৳500 reward on a ৳300 cart is accepted,
	 * discounts ৳300, and the remaining ৳200 is gone — the coupon is single-use
	 * and is marked used. Add a second ৳500 reward and the discount stays ৳300
	 * while BOTH coupons are consumed. Two rewards, ৳700 destroyed, and nothing
	 * on screen said a word.
	 *
	 * We warn rather than block. Blocking would mean refusing a customer their
	 * own money at the moment they try to spend it, and they might genuinely
	 * not care. But silently burning it is indefensible, so the choice is put
	 * in front of them with the number attached.
	 */
	public static function excess_reward_notice() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! function_exists( 'wc_add_notice' ) ) {
			return;
		}

		$face = 0.0;
		$n    = 0;
		foreach ( (array) WC()->cart->get_applied_coupons() as $code ) {
			$id = (int) wc_get_coupon_id_by_code( $code );
			if ( $id < 1 ) {
				continue;
			}
			$c = new WC_Coupon( $id );
			if ( ! self::is_reward_coupon( $c ) || 'fixed_cart' !== $c->get_discount_type() ) {
				continue;
			}
			$face += (float) $c->get_amount();
			$n++;
		}
		if ( $n < 1 ) {
			return;
		}

		$usable = (float) WC()->cart->get_subtotal();
		$excess = $face - $usable;
		if ( $excess < 0.01 ) {
			return;
		}

		wc_add_notice(
			sprintf(
				/* translators: 1: wasted amount, 2: order amount. */
				__( 'Your rewards add up to more than this order. About %1$s of them will not be used, and a used reward cannot be recovered — this order is only %2$s. Remove a reward to keep it for next time.', 'aun-app-api' ),
				wp_strip_all_tags( wc_price( $excess ) ),
				wp_strip_all_tags( wc_price( $usable ) )
			),
			'notice'
		);
	}

	/** Is this coupon a referrer's earned reward? */
	public static function is_reward_coupon( $coupon ) {
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
		if ( ! is_a( $coupon, 'WC_Coupon' ) ) {
			return false;
		}
		if ( '' !== (string) $coupon->get_meta( self::COUPON_META_REWARD ) ) {
			return true;
		}
		// Rewards issued before the meta existed. Prefix only as a fallback —
		// never as the primary test, or renaming it changes stacking rules.
		return 0 === strpos( strtoupper( $coupon->get_code() ), 'THANKS-' );
	}

	/**
	 * Let a referrer spend SEVERAL earned rewards on one order — but still
	 * never alongside a shop coupon.
	 *
	 * Rewards are issued one per friend, and each is `individual_use`. Left
	 * alone that means a referrer who has brought five customers holds five
	 * ৳500 coupons and can spend exactly one per order: ৳2,500 earned, five
	 * separate orders to collect it. Nobody reads that as generous.
	 *
	 * WooCommerce provides the two filters this needs, so the rule stays
	 * narrow: rewards combine with rewards, and with nothing else. A seasonal
	 * sale coupon still cannot ride along, which was the point of making them
	 * individual-use in the first place.
	 *
	 * @param array     $keep   Coupons WooCommerce plans to keep/remove.
	 * @param WC_Coupon $coupon The individual-use coupon being applied.
	 * @return array
	 */
	public static function keep_rewards_together( $keep, $coupon ) {
		if ( ! self::is_reward_coupon( $coupon ) ) {
			return $keep;
		}
		// Applying a reward: keep any other rewards already in the cart.
		$keep = (array) $keep;
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( (array) WC()->cart->get_applied_coupons() as $code ) {
				if ( self::is_reward_coupon( (string) $code ) && ! in_array( $code, $keep, true ) ) {
					$keep[] = $code;
				}
			}
		}
		return $keep;
	}

	/**
	 * May this coupon be applied while an individual-use coupon sits in the
	 * cart? Yes, when both sides are earned rewards.
	 *
	 * @param bool      $apply       WooCommerce's verdict.
	 * @param WC_Coupon $coupon      Coupon being applied.
	 * @param WC_Coupon $ind_coupon  The individual-use coupon already applied.
	 * @return bool
	 */
	public static function allow_reward_stacking( $apply, $coupon, $ind_coupon ) {
		if ( self::is_reward_coupon( $coupon ) && self::is_reward_coupon( $ind_coupon ) ) {
			return true;
		}
		return $apply;
	}

	/**
	 * Applying a locked coupon: say which number it belongs to, and let it in.
	 *
	 * The phone is checked ONCE, at order placement. It is deliberately not
	 * checked here, and the previous version — which hooked
	 * `woocommerce_coupon_is_valid` — was wrong in three ways at once:
	 *
	 *  1. That filter runs on EVERY cart recalculation, so an ownership check
	 *     that reads the session, the form and the account ran over and over
	 *     for a fact that changes at most once per checkout. Slow, for nothing.
	 *  2. It is consulted at two different moments with different data. Apply
	 *     the coupon before typing a phone and it passed; recalculate after
	 *     typing a wrong one and it failed — producing "Coupon applied
	 *     successfully" sitting above a ৳0 discount. A customer cannot be
	 *     expected to make sense of that, and they are right not to.
	 *  3. A refusal there is sticky in a way nothing tells the customer about:
	 *     WooCommerce had cached the wrong number, so the coupon then failed
	 *     for ever, through refreshes, with the right number on screen.
	 *
	 * So: applying is instant and always succeeds. The customer is TOLD the
	 * condition at the moment they apply, and it is enforced once, at the end,
	 * where the number they are actually ordering with is finally known.
	 *
	 * @param string $code Coupon code just applied.
	 */
	public static function on_applied_coupon( $code ) {
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) || ! function_exists( 'wc_add_notice' ) ) {
			return;
		}
		$id = (int) wc_get_coupon_id_by_code( $code );
		if ( $id < 1 ) {
			return;
		}
		$locked = self::coupon_phone( new WC_Coupon( $id ) );
		if ( '' === $locked ) {
			return;
		}

		// Already using the right number? Then there is nothing to warn about,
		// and a warning nobody needs is just noise on a checkout page.
		if ( self::any_phone_matches( $locked, self::known_phones() ) ) {
			return;
		}

		wc_add_notice(
			sprintf(
				/* translators: %s: masked phone number. */
				__( 'Discount applied. It is linked to the mobile number %s — please use that number in the Phone field, or the order cannot be placed with this discount.', 'aun-app-api' ),
				class_exists( 'AUN_App_Phone' ) ? AUN_App_Phone::mask( $locked ) : ''
			),
			'notice'
		);
	}

	/**
	 * Locked coupons in the cart that this shopper cannot use.
	 *
	 * One place, so the checkout gate and the order-creation backstop cannot
	 * reach different conclusions about the same cart.
	 *
	 * @param string $typed Billing phone being submitted, if any.
	 * @return array<int,array{code:string,locked:string,missing:bool}>
	 */
	public static function blocked_coupons( $typed = '' ) {
		$out = array();
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $out;
		}

		$candidates = array_values( array_filter(
			array_merge( array( (string) $typed ), self::known_phones() )
		) );

		foreach ( (array) WC()->cart->get_applied_coupons() as $code ) {
			$id = function_exists( 'wc_get_coupon_id_by_code' )
				? (int) wc_get_coupon_id_by_code( $code ) : 0;
			if ( $id < 1 ) {
				continue;
			}
			$locked = self::coupon_phone( new WC_Coupon( $id ) );
			if ( '' === $locked ) {
				continue;
			}
			if ( self::any_phone_matches( $locked, $candidates ) ) {
				continue;
			}
			$out[] = array(
				'code'    => (string) $code,
				'locked'  => $locked,
				'missing' => empty( $candidates ),
			);
		}
		return $out;
	}

	/**
	 * Every phone number we can currently attribute to this shopper.
	 *
	 * Reading ONE source was the bug. The original version asked only
	 * `WC()->customer->get_billing_phone()`, which is the SESSION copy — and a
	 * session remembers. A customer who mistyped their number once had that
	 * wrong number cached, so the coupon was refused for ever afterwards: with
	 * the right number typed, with the field emptied, after a page refresh. The
	 * form said one thing and the session said another, and only the session
	 * was being asked.
	 *
	 * So: collect everything, in order of how much it is worth trusting, and
	 * let the caller accept if ANY of them matches. Being generous here is
	 * safe — the strict gate at order placement still checks the number the
	 * order will actually carry.
	 *
	 * @return string[] Raw, unnormalised.
	 */
	public static function known_phones() {
		$out = array();

		// 1. The account. For an app customer this is the OTP-VERIFIED number,
		// which is the same fact the coupon was locked to — the strongest
		// evidence available, and it cannot be typed wrong.
		if ( is_user_logged_in() ) {
			$uid = get_current_user_id();
			foreach ( array( 'billing_phone', 'mobile_phone' ) as $key ) {
				$v = (string) get_user_meta( $uid, $key, true );
				if ( '' !== trim( $v ) ) {
					$out[] = $v;
				}
			}
		}

		// 2. What is in the checkout form RIGHT NOW. WooCommerce sends the
		// whole form as `post_data` with its ajax calls (applying a coupon,
		// refreshing the order review), and that is fresher than the session.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- reading only, no action taken on it.
		if ( ! empty( $_POST['billing_phone'] ) ) {
			$out[] = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) );
		}
		if ( ! empty( $_POST['post_data'] ) ) {
			parse_str( wp_unslash( $_POST['post_data'] ), $form ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! empty( $form['billing_phone'] ) ) {
				$out[] = sanitize_text_field( $form['billing_phone'] );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// 3. The session. Last, because it is the one that goes stale.
		if ( function_exists( 'WC' ) && WC()->customer ) {
			$v = (string) WC()->customer->get_billing_phone();
			if ( '' !== trim( $v ) ) {
				$out[] = $v;
			}
		}

		return array_values( array_unique( array_filter( array_map( 'trim', $out ) ) ) );
	}

	/** Does any of these numbers mean the locked one? */
	public static function any_phone_matches( $locked, $phones ) {
		foreach ( (array) $phones as $p ) {
			if ( self::phone_matches( $locked, $p ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Order placement, the STRICT half.
	 *
	 * By this point the billing phone has actually been submitted, so a blank
	 * one is a real answer rather than "not filled in yet". This is the gate
	 * that stops the order.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Errors.
	 */
	public static function on_checkout_validation( $data, $errors ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! is_object( $errors ) ) {
			return;
		}

		$typed   = isset( $data['billing_phone'] ) ? (string) $data['billing_phone'] : '';
		$blocked = self::blocked_coupons( $typed );

		foreach ( $blocked as $b ) {
			// Remove it, so the order can be placed at full price on the next
			// press rather than the customer being stuck against a wall they
			// cannot see. They keep the code — it works on the right number,
			// and nothing about the coupon itself has been used up.
			WC()->cart->remove_coupon( $b['code'] );

			$errors->add(
				'aun_referral_phone',
				self::lock_message( $b['locked'], $b['missing'] )
					. ' ' . __( 'The discount has been removed so you can continue — re-apply it once the number matches.', 'aun-app-api' )
			);
		}

		if ( ! empty( $blocked ) ) {
			WC()->cart->calculate_totals();
		}
	}

	/**
	 * Last line of defence, at the moment the order object is built.
	 *
	 * `woocommerce_after_checkout_validation` only runs on the classic
	 * checkout. This hook runs for the block checkout and the Store API too, so
	 * a discount cannot be taken by any route that skips the form. It should
	 * never fire in normal use — the gate above has already removed anything
	 * blocked — which is exactly what a backstop is for.
	 *
	 * @param WC_Order $order Order being created.
	 * @param array    $data  Posted checkout data.
	 * @throws Exception When a locked coupon does not belong to this buyer.
	 */
	public static function on_create_order( $order, $data = array() ) {
		$typed = '';
		if ( is_array( $data ) && isset( $data['billing_phone'] ) ) {
			$typed = (string) $data['billing_phone'];
		} elseif ( is_a( $order, 'WC_Order' ) ) {
			$typed = (string) $order->get_billing_phone();
		}

		$blocked = self::blocked_coupons( $typed );
		if ( empty( $blocked ) ) {
			return;
		}

		$first = $blocked[0];
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( $blocked as $b ) {
				WC()->cart->remove_coupon( $b['code'] );
			}
			WC()->cart->calculate_totals();
		}
		throw new Exception( esc_html( self::lock_message( $first['locked'], $first['missing'] ) ) );
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
		if ( ! $claim ) {
			return;
		}

		// An admin mis-click is not a refund.
		//
		// Setting an order to Cancelled revokes the claim and destroys the
		// coupon. Putting the correct status back used to leave the claim
		// REVOKED for ever, so a two-second slip in wp-admin permanently cost
		// the referrer a reward they had earned — invisibly, with no way back
		// short of editing the database.
		//
		// Only a claim revoked BY A REVERSAL OF THIS ORDER is reinstated. One
		// revoked because the buyer failed a rule stays revoked, because that
		// judgement had nothing to do with the order's status.
		if ( self::STATUS_REVOKED === $claim->status ) {
			$undoable = 'reversed' === (string) ( $claim->revoke_reason ?? '' )
				&& (int) $claim->order_id === (int) $order->get_id();
			if ( ! $undoable ) {
				return;
			}
			$wpdb->update(
				self::claims_table(),
				array( 'status' => self::STATUS_PENDING, 'revoke_reason' => '', 'reward_coupon' => '' ),
				array( 'id' => (int) $claim->id )
			);
			$claim->status        = self::STATUS_PENDING;
			$claim->reward_coupon = '';
		}

		if ( self::STATUS_PENDING !== $claim->status ) {
			return;
		}

		$s = self::settings();

		// Minimum spend, checked against what was actually PAID rather than the
		// cart total — otherwise a big order that was mostly discounted away
		// could still trigger a reward worth more than the margin.
		if ( $s['min_order_total'] > 0 && (float) $order->get_total() < $s['min_order_total'] ) {
			return; // stays pending: a later, larger order can still qualify
		}

		// Re-check the BUYER at completion, not just the claimer.
		//
		// The "first-time customer" test runs when the code is claimed, against
		// the phone on the account. Nothing stopped the actual order being
		// placed under a DIFFERENT number that belongs to an existing customer
		// — which would quietly discount a repeat buyer and pay a reward for
		// someone we already had. Checking the order's own billing phone closes
		// that gap.
		$buyer_phone = (string) $order->get_billing_phone();
		if ( '' !== $buyer_phone && self::phone_has_other_orders( $buyer_phone, (int) $order->get_id() ) ) {
			$wpdb->update(
				self::claims_table(),
				array(
					'status'        => self::STATUS_REVOKED,
					'order_id'      => (int) $order->get_id(),
					// A judgement about the BUYER, not about the order status —
					// so re-saving the order must never undo it.
					'revoke_reason' => 'ineligible',
				),
				array( 'id' => (int) $claim->id )
			);
			return;
		}

		$reward = '';
		if ( $s['referrer_amount'] > 0 ) {
			$reward = self::create_referrer_reward( (int) $claim->referrer_user_id, $s, (float) $order->get_total() );
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

		self::notify_referrer( (int) $claim->referrer_user_id, $reward, $s, (float) $order->get_total() );
	}

	/**
	 * Money was refunded WITHOUT the order changing status.
	 *
	 * Judged on what the customer actually kept, not on the fact that a refund
	 * happened. A ৳200 goodwill refund on a ৳60,000 projector is not a returned
	 * sale, and treating it as one would punish the referrer for our own
	 * customer-service gesture. A refund that drops the kept amount below the
	 * qualifying minimum is a different matter — that order no longer meets the
	 * bar the reward was paid for.
	 *
	 * With no minimum configured there is nothing to fall below, so a partial
	 * refund never revokes; a full refund arrives as a status change instead
	 * and is handled there.
	 *
	 * @param int $order_id  Order.
	 * @param int $refund_id Refund (unused).
	 */
	public static function on_partial_refund( $order_id, $refund_id = 0 ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return;
		}
		$s   = self::settings();
		$min = (float) $s['min_order_total'];
		if ( $min <= 0 ) {
			return;
		}
		$kept = (float) $order->get_total() - (float) $order->get_total_refunded();
		if ( $kept < $min ) {
			self::on_order_reversed( $order_id );
		}
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
			array( 'status' => self::STATUS_REVOKED, 'revoke_reason' => 'reversed' ),
			array( 'id' => (int) $claim->id )
		);

		// Tell BOTH people, and say why.
		//
		// Taking a reward back silently is how a loyal customer decides they
		// were cheated: they saw a coupon in the app, they told someone about
		// it, and one day it was gone. The order really was returned, we can
		// say so plainly, and an explanation costs nothing.
		//
		// The friend needs telling too. Their side of the programme also ended
		// — and they are the one who returned the order, so they will be
		// wondering what became of the discount they used.
		self::notify_reward_revoked( (int) $claim->referrer_user_id, (string) $claim->reward_coupon );
		self::notify_friend_reversed( (int) $claim->referred_user_id, (string) $claim->referred_phone );
	}

	/**
	 * Tell the friend their referral ended because the order came back.
	 *
	 * Deliberately not an apology and not a telling-off: the order was
	 * cancelled or returned, which is a normal thing that happens, and the only
	 * useful facts are that the referral no longer counts and that their friend
	 * has been told the same. Silence here reads as us quietly taking something
	 * away.
	 *
	 * @param int    $user_id Friend's account.
	 * @param string $phone   Their number, to find the account if the id is stale.
	 */
	private static function notify_friend_reversed( $user_id, $phone ) {
		if ( ! class_exists( 'AUN_App_Notices' ) ) {
			return;
		}

		$user_id = (int) $user_id;
		if ( $user_id < 1 && '' !== $phone && class_exists( 'AUN_App_Phone' ) ) {
			$users   = AUN_App_Phone::find_users( $phone );
			$user_id = ! empty( $users ) ? (int) $users[0]->ID : 0;
		}
		if ( $user_id < 1 ) {
			return;
		}

		$title    = 'Your referral discount has ended';
		$title_bn = 'আপনার রেফারেল ছাড়টি বাতিল হয়েছে';
		$body     = 'The order you used it on was cancelled or returned, so the referral no longer '
			. 'counts and the friend who invited you has not been rewarded. Nothing else on your '
			. 'account is affected.';
		$body_bn  = 'যে অর্ডারে এটি ব্যবহার করেছিলেন সেটি বাতিল বা ফেরত হয়েছে, তাই রেফারেলটি আর গণ্য '
			. 'হচ্ছে না এবং যিনি আপনাকে আমন্ত্রণ করেছিলেন তিনিও পুরস্কার পাননি। আপনার অ্যাকাউন্টের আর '
			. 'কিছুতে প্রভাব পড়েনি।';

		$id = AUN_App_Notices::create( array(
			'user_id'   => $user_id,
			'type'      => 'referral',
			'title'     => $title,
			'title_bn'  => $title_bn,
			'body'      => $body,
			'body_bn'   => $body_bn,
			'data'      => array(),
			'dedup_key' => 'referral_friend_reversed:' . $user_id . ':' . ( '' !== $phone ? $phone : uniqid( '', true ) ),
		) );

		if ( $id && AUN_App_Notices::$last_was_new
			&& class_exists( 'AUN_App_Push' ) && AUN_App_Push::configured() ) {
			AUN_App_Push::push_to_users(
				array( $user_id ),
				array( 'title' => $title, 'title_bn' => $title_bn, 'body' => $body, 'body_bn' => $body_bn ),
				array( 'type' => 'referral', 'notice_id' => $id )
			);
		}
	}

	/**
	 * The referrer's reward has been withdrawn because the order came back.
	 *
	 * @param int    $user_id Referrer.
	 * @param string $coupon  The coupon that no longer works.
	 */
	private static function notify_reward_revoked( $user_id, $coupon ) {
		if ( ! class_exists( 'AUN_App_Notices' ) || (int) $user_id < 1 ) {
			return;
		}

		$title    = 'Your referral reward has been withdrawn';
		$title_bn = 'আপনার রেফারেল পুরস্কার বাতিল হয়েছে';
		$body     = 'Your friend\'s order was cancelled or returned, so the reward for it '
			. 'has been withdrawn. Nothing else on your account is affected, and you keep '
			. 'every other reward you have earned. Invite another friend any time.';
		$body_bn  = 'আপনার বন্ধুর অর্ডারটি বাতিল বা ফেরত হয়েছে, তাই এর পুরস্কারটি বাতিল করা হয়েছে। '
			. 'আপনার অ্যাকাউন্টের আর কিছুতে প্রভাব পড়েনি, আগের সব পুরস্কার আপনার কাছেই থাকছে। '
			. 'যেকোনো সময় আবার বন্ধুকে আমন্ত্রণ করতে পারেন।';

		$id = AUN_App_Notices::create( array(
			'user_id'   => (int) $user_id,
			'type'      => 'referral',
			'title'     => $title,
			'title_bn'  => $title_bn,
			'body'      => $body,
			'body_bn'   => $body_bn,
			'data'      => array(),
			// Per coupon, so the same withdrawal is never announced twice —
			// and a later, different one still is.
			'dedup_key' => 'referral_revoked:' . ( '' !== $coupon ? $coupon : (int) $user_id . ':' . uniqid( '', true ) ),
		) );

		if ( $id && AUN_App_Notices::$last_was_new
			&& class_exists( 'AUN_App_Push' ) && AUN_App_Push::configured() ) {
			AUN_App_Push::push_to_users(
				array( (int) $user_id ),
				array( 'title' => $title, 'title_bn' => $title_bn, 'body' => $body, 'body_bn' => $body_bn ),
				array( 'type' => 'referral', 'notice_id' => $id )
			);
		}
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

		// The claim this order ALREADY paid, whatever its status now.
		//
		// order_id is written when the reward is issued, so this is the exact
		// link — and it must not be filtered by status, or a reward could
		// never be clawed back. That was the asymmetry: an order placed
		// without the coupon was matched by the pending-buyer fallback below
		// and paid out, then on refund the same fallback found nothing
		// (the claim was no longer pending) and the reward survived a return.
		$by_order = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $claims WHERE order_id = %d LIMIT 1",
			(int) $order->get_id()
		) );
		if ( $by_order ) {
			return $by_order;
		}

		// Fall back to the buyer: an order placed without the coupon still
		// counts, since the referral did its job of bringing them in. Matched
		// by the order's billing phone as well as by account, because app
		// customers are known by number and may check out as a guest — keyed
		// on the account alone, the referrer simply never got paid.
		//
		// Restricted to PENDING on purpose. Without it, a customer's LATER,
		// unrelated order being refunded would reach back and revoke a reward
		// that a completely different order had already earned.
		$customer_id  = (int) $order->get_customer_id();
		$order_phone  = class_exists( 'AUN_App_Phone' )
			? AUN_App_Phone::normalize( (string) $order->get_billing_phone() ) : false;

		if ( $customer_id > 0 || $order_phone ) {
			return self::claim_row(
				$customer_id,
				$order_phone ? $order_phone : '',
				self::STATUS_PENDING
			);
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
	private static function create_referrer_reward( $user_id, $s, $order_total = 0 ) {
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
			// Always issued as a fixed amount, even when the admin expressed it
			// as a percentage: a percentage of the FRIEND's order has no meaning
			// on the referrer's next, unrelated cart.
			$coupon->set_discount_type( 'fixed_cart' );
			$coupon->set_amount( self::reward_value( $s, (float) $order_total ) );
			$coupon->set_usage_limit( 1 );
			$coupon->set_usage_limit_per_user( 1 );

			// Never stackable. Without this the referrer can put their reward
			// on top of a seasonal sale coupon and take both discounts off the
			// same cart — which is not what either was priced for.
			$coupon->set_individual_use( true );

			// Bound to the REFERRER'S PHONE, exactly like the friend's coupon.
			//
			// This used to be an email restriction, which was the wrong lock in
			// two ways: app accounts have no email, so it usually bound nothing
			// at all, and where it did bind it bound an address the customer
			// never uses at checkout. The phone is the identity this whole
			// system is built on, and it is OTP-verified.
			$phone = self::phone_of( $user_id );
			if ( '' !== $phone ) {
				$coupon->update_meta_data( self::COUPON_META_PHONE, $phone );
			}

			// Marks this as an earned reward, which is what lets several of
			// them be spent on one order — see allow_reward_stacking().
			$coupon->update_meta_data( self::COUPON_META_REWARD, '1' );

			// An EARNED reward is not a promotion. The friend's welcome
			// discount is a marketing offer with a deadline; this is money the
			// referrer worked for by bringing us a customer, so it gets its own
			// much longer setting — and 0 means it never expires.
			if ( (int) $s['reward_expiry_days'] > 0 ) {
				$coupon->set_date_expires(
					date( 'Y-m-d', strtotime( '+' . (int) $s['reward_expiry_days'] . ' days', current_time( 'timestamp' ) ) )
				);
			}
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

	/**
	 * Somebody just used this customer's code.
	 *
	 * Sent at CLAIM time, long before any order. Without it the referrer has no
	 * evidence their code worked until (and unless) the friend buys — which is
	 * exactly when people decide the programme is broken and stop sharing.
	 */
	private static function notify_claimed( $referrer_id ) {
		if ( ! class_exists( 'AUN_App_Notices' ) || (int) $referrer_id < 1 ) {
			return;
		}
		$title    = 'A friend used your invite code';
		$title_bn = 'একজন বন্ধু আপনার কোড ব্যবহার করেছেন';
		$body     = 'They have their discount. Once their order is delivered, your reward is unlocked.';
		$body_bn  = 'তিনি ছাড় পেয়েছেন। তাঁর অর্ডার ডেলিভারি হলেই আপনার পুরস্কার আসবে।';

		$id = AUN_App_Notices::create( array(
			'user_id'   => (int) $referrer_id,
			'type'      => 'referral',
			'title'     => $title,
			'title_bn'  => $title_bn,
			'body'      => $body,
			'body_bn'   => $body_bn,
			'data'      => array(),
			// One per claim, so three friends produce three notices.
			'dedup_key' => 'referral_claimed:' . (int) $referrer_id . ':' . uniqid( '', true ),
		) );

		if ( $id && AUN_App_Notices::$last_was_new
			&& class_exists( 'AUN_App_Push' ) && AUN_App_Push::configured() ) {
			AUN_App_Push::push_to_users(
				array( (int) $referrer_id ),
				array( 'title' => $title, 'title_bn' => $title_bn, 'body' => $body, 'body_bn' => $body_bn ),
				array( 'type' => 'referral', 'notice_id' => $id )
			);
		}
	}

	/**
	 * The friend's order completed.
	 *
	 * Sent even when there is no coupon — a one-sided programme (referrer
	 * amount 0) still owes the referrer the news that their recommendation
	 * turned into a real sale. The earlier version returned silently whenever
	 * the reward was empty, so the referrer saw a count go up and nothing else.
	 */
	private static function notify_referrer( $user_id, $reward, $s, $order_total = 0 ) {
		if ( ! class_exists( 'AUN_App_Notices' ) || (int) $user_id < 1 ) {
			return;
		}

		$paid = self::reward_value( $s, (float) $order_total );

		if ( '' !== $reward ) {
			$amount   = number_format_i18n( $paid, 0 );
			$title    = 'Your friend ordered — ৳' . $amount . ' is yours';
			$title_bn = 'আপনার বন্ধু অর্ডার করেছেন — ৳' . $amount . ' আপনার';
			$body     = 'Thank you for recommending us. Use code ' . $reward . ' on your next order.';
			$body_bn  = 'আমাদের সুপারিশ করার জন্য ধন্যবাদ। পরের অর্ডারে ' . $reward . ' কোডটি ব্যবহার করুন।';
			$dedup    = 'referral_reward:' . $reward;
		} else {
			$title    = 'Your friend\'s order was delivered';
			$title_bn = 'আপনার বন্ধুর অর্ডার ডেলিভারি হয়েছে';
			$body     = 'Thank you for recommending us.';
			$body_bn  = 'আমাদের সুপারিশ করার জন্য ধন্যবাদ।';
			$dedup    = 'referral_done:' . (int) $user_id . ':' . uniqid( '', true );
		}

		$id = AUN_App_Notices::create( array(
			'user_id'   => (int) $user_id,
			'type'      => 'referral',
			'title'     => $title,
			'title_bn'  => $title_bn,
			'body'      => $body,
			'body_bn'   => $body_bn,
			'data'      => array( 'coupon' => $reward ),
			'dedup_key' => $dedup,
		) );

		if ( $id && AUN_App_Notices::$last_was_new
			&& class_exists( 'AUN_App_Push' ) && AUN_App_Push::configured() ) {
			AUN_App_Push::push_to_users(
				array( (int) $user_id ),
				array( 'title' => $title, 'title_bn' => $title_bn, 'body' => $body, 'body_bn' => $body_bn ),
				array( 'type' => 'referral', 'coupon' => $reward, 'notice_id' => $id )
			);
		}
	}

	/**
	 * What the referrer's reward is worth in taka.
	 *
	 * A percentage reward is taken off the ORDER that earned it, so it has to
	 * be resolved to a real amount before it can be issued as a fixed-cart
	 * coupon or shown in a message.
	 *
	 * @param array $s           Settings.
	 * @param float $order_total Order that earned it.
	 * @return float
	 */
	public static function reward_value( $s, $order_total ) {
		if ( 'percent' === ( $s['referrer_type'] ?? 'fixed' ) ) {
			return round( (float) $order_total * (float) $s['referrer_amount'] / 100, 2 );
		}
		return (float) $s['referrer_amount'];
	}

	/**
	 * Does this phone number have orders OTHER than the given one?
	 *
	 * Used at completion to catch a welcome discount being spent by somebody
	 * who is already a customer under a different number.
	 *
	 * @param string $phone    Billing phone from the order.
	 * @param int    $order_id The order to ignore.
	 * @return bool
	 */
	private static function phone_has_other_orders( $phone, $order_id ) {
		if ( ! function_exists( 'wc_get_orders' ) || ! class_exists( 'AUN_App_Phone' ) ) {
			return false;
		}
		$canonical = AUN_App_Phone::normalize( $phone );
		if ( '' === $canonical ) {
			return false;
		}
		foreach ( AUN_App_Phone::variants( $canonical ) as $variant ) {
			$ids = wc_get_orders( array(
				'billing_phone' => $variant,
				'limit'         => 5,
				'return'        => 'ids',
				'status'        => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
			) );
			foreach ( (array) $ids as $id ) {
				if ( (int) $id !== (int) $order_id ) {
					return true;
				}
			}
		}
		return false;
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

		$invited   = 0;
		$rewarded  = 0;
		$earned    = 0.0;
		$available = 0.0;
		$coupons   = array();
		foreach ( $rows as $r ) {
			if ( self::STATUS_REVOKED === $r->status ) {
				continue;
			}
			$invited++;
			if ( self::STATUS_REWARDED === $r->status ) {
				$rewarded++;
				if ( ! empty( $r->reward_coupon ) ) {
					// The coupon's OWN amount, not today's setting — a reward
					// earned last month is worth what it was worth then.
					$value = 0.0;
					$spent = false;
					if ( function_exists( 'wc_get_coupon_id_by_code' ) ) {
						$cid = (int) wc_get_coupon_id_by_code( (string) $r->reward_coupon );
						$expires = '';
						if ( $cid > 0 ) {
							$rc = new WC_Coupon( $cid );
							self::heal_coupon_restrictions( $rc );
							$value   = (float) $rc->get_amount();
							$spent   = (int) $rc->get_usage_count() > 0;
							$expires = self::coupon_expiry( $rc );
						}
					}
					$earned += $value;
					// What they can actually spend today, as opposed to what
					// they have earned in total. A referrer reading "৳2,500
					// earned" wants to know how much of it is still there — a
					// lifetime figure on its own invites "so where is it?".
					if ( ! $spent ) {
						$available += $value;
					}
					$coupons[] = array(
						'code'    => (string) $r->reward_coupon,
						'date'    => substr( (string) $r->rewarded_at, 0, 10 ),
						'value'   => $value,
						'used'    => $spent,
						// '' = never expires. Read from the COUPON itself, not
						// recomputed from today's setting: changing the setting
						// must not appear to move the deadline on a reward that
						// was already issued.
						'expires' => $expires,
					);
				}
			}
		}

		// The customer's OWN welcome coupon, if they claimed someone's code.
		// Without this it was shown once in a snackbar and then lost — they had
		// no way to find the code again when they reached checkout.
		// By phone as well as by id — see claim_row(). Looking this up by id
		// alone was how a customer ended up "already claimed" with no coupon
		// to show for it.
		$mine            = self::claim_row( $user_id );
		$my_coupon        = '';
		$my_coupon_phone  = '';
		$my_coupon_expiry = '';
		if ( $mine && ! empty( $mine->friend_coupon )
			&& self::STATUS_REVOKED !== $mine->status ) {
			// Only offer it while it can still be spent.
			$cid = function_exists( 'wc_get_coupon_id_by_code' )
				? (int) wc_get_coupon_id_by_code( (string) $mine->friend_coupon ) : 0;
			if ( $cid > 0 ) {
				$c = new WC_Coupon( $cid );
				self::heal_coupon_restrictions( $c );
				if ( 0 === (int) $c->get_usage_count() ) {
					$my_coupon        = (string) $mine->friend_coupon;
					$my_coupon_expiry = self::coupon_expiry( $c );
					// The coupon's OWN lock, not the claim row's phone: the
					// coupon is what checkout will actually judge, and an older
					// coupon may carry no lock at all — in which case the app
					// must not promise a restriction that is not there.
					$locked          = self::coupon_phone( $c );
					$my_coupon_phone = '' !== $locked ? self::display_phone( $locked ) : '';
				}
			}
		}

		return array(
			'enabled'         => self::available(),
			'code'            => self::available() ? self::code_for( $user_id ) : '',
			'my_coupon'         => $my_coupon,
			'my_coupon_phone'   => $my_coupon_phone,
			'my_coupon_expires' => $my_coupon_expiry,
			// So the app can state the deadline BEFORE a code is redeemed —
			// "your discount will be valid for 90 days" is part of the offer,
			// and both figures follow the admin settings with no app release.
			'friend_expiry_days' => (int) $s['coupon_expiry_days'],
			'reward_expiry_days' => (int) $s['reward_expiry_days'],
			// The redeem deadline, so a new customer is TOLD how long they have
			// instead of finding out on the day it lapses. Both follow the
			// admin setting, so the app never states a number we do not enforce.
			'claim_window_days'  => (int) $s['claim_window_days'],
			'claim_deadline'     => self::claim_deadline( $user_id ),
			'friend_type'     => $s['friend_type'],
			'friend_amount'   => $s['friend_amount'],
			'referrer_amount' => $s['referrer_amount'],
			'invited'         => $invited,
			'rewarded'        => $rewarded,
			// Total value of every reward earned, so the referrer can see what
			// the programme has actually been worth to them.
			'earned'          => round( $earned, 2 ),
			// Unspent, still-valid rewards: the balance, not the history.
			'available'       => round( $available, 2 ),
			'referrer_type'   => $s['referrer_type'],
			'can_invite'      => self::available() && self::can_invite( $user_id ),
			'rewards'         => $coupons,
			// Whether THIS user may still claim someone else's code, so the app
			// never offers an entry field that would only ever fail.
			//
			// Must mirror EVERY rule claim() enforces, not just some of them.
			// It previously omitted the purchase-history test, so an existing
			// customer was shown "I have a code" and then refused with
			// "referral codes are for first-time customers" — an offer we
			// already knew we would not honour.
			'can_claim'       => '' === self::claim_blocked_reason( $user_id ),
			// Why claiming is unavailable, so the app can say which it is
			// rather than leaving a blank where a reason belongs.
			'claim_blocked'   => self::claim_blocked_reason( $user_id ),
		);
	}

	/**
	 * This customer's own claim row — the one that says they redeemed a code.
	 *
	 * Matched by PHONE as well as by account id, and that is the whole point.
	 * The programme's identity of record is the phone: `has_claimed()` and the
	 * one-claim-per-person rule both key on `referred_phone`, deliberately, so
	 * that deleting and recreating an account cannot buy a second discount.
	 *
	 * Reading the claim back by `referred_user_id` alone therefore disagreed
	 * with the rule that wrote it. A customer whose account id had changed —
	 * recreated account, or a claim made while matched to a different WP user
	 * with the same number — was told "you have already used a code" (found by
	 * phone) AND shown no coupon (not found by id). Both doors shut: they
	 * could not claim again, and could not reach the coupon they had been
	 * given. One identity, one lookup.
	 *
	 * @param int         $user_id User.
	 * @param string|null $phone   Canonical phone; resolved when null.
	 * @param string|null $status  Restrict to this status, or null for any.
	 * @return object|null Newest matching claim.
	 */
	public static function claim_row( $user_id, $phone = null, $status = null ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( null === $phone ) {
			$phone = self::phone_of( $user_id );
		}
		$phone  = (string) $phone;
		$claims = self::claims_table();

		$where  = array();
		$params = array();
		if ( $user_id > 0 ) {
			$where[]  = 'referred_user_id = %d';
			$params[] = $user_id;
		}
		if ( '' !== $phone ) {
			$where[]  = 'referred_phone = %s';
			$params[] = $phone;
		}
		if ( empty( $where ) ) {
			return null;
		}

		$sql = 'SELECT * FROM ' . $claims . ' WHERE (' . implode( ' OR ', $where ) . ')';
		if ( null !== $status ) {
			$sql     .= ' AND status = %s';
			$params[] = $status;
		}
		// Newest first: if a number somehow carries more than one row, the
		// current one is the one that matters.
		$sql .= ' ORDER BY id DESC LIMIT 1';

		return $wpdb->get_row( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Why this customer cannot redeem a code — a machine-readable reason the
	 * app turns into a sentence.
	 *
	 * Returned even when they CAN claim (as ''), because the app should be
	 * able to state a customer's standing without inferring it from a
	 * combination of booleans. Inference is how the app ended up showing
	 * nothing at all and looking broken.
	 *
	 * @param int $user_id User.
	 * @return string '' | 'off' | 'used' | 'existing_customer'
	 */
	public static function claim_blocked_reason( $user_id ) {
		if ( ! self::available() ) {
			return 'off';
		}
		if ( self::has_claimed( (int) $user_id ) ) {
			return 'used';
		}
		$phone = self::phone_of( (int) $user_id );
		// Mirrors the same exemption claim() makes, or the app would say
		// "not available" about a claim that would actually succeed.
		if ( ! self::is_test_phone( $phone )
			&& self::has_purchase_history( (int) $user_id, $phone ) ) {
			return 'existing_customer';
		}
		// Mirrors rule 4 in claim(). Without it the app offered a redeem box
		// to someone whose window had closed, and only the refusal told them.
		$s = self::settings();
		if ( $s['claim_window_days'] > 0
			&& self::days_since_joined( (int) $user_id ) > $s['claim_window_days'] ) {
			return 'too_late';
		}
		return '';
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
