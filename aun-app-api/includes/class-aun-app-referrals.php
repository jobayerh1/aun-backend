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
			'monthly_cap'      => max( 0, (int) ( $o['referral_monthly_cap'] ?? 5 ) ),
			'claim_window_days' => max( 0, (int) ( $o['referral_claim_days'] ?? 30 ) ),
			'coupon_expiry_days' => max( 1, (int) ( $o['referral_expiry_days'] ?? 90 ) ),
		);
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
		return self::has_purchase_history( (int) $user_id, $phone );
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
			$email = self::customer_email( $user_id );
			if ( '' !== $email ) {
				$coupon->set_email_restrictions( array( $email ) );
			}
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
	public static function on_coupon_is_valid( $valid, $coupon ) {
		if ( ! $valid ) {
			return $valid;
		}
		$locked = self::coupon_phone( $coupon );
		if ( '' === $locked ) {
			return $valid;
		}

		$typed = '';
		if ( function_exists( 'WC' ) && WC()->customer ) {
			$typed = (string) WC()->customer->get_billing_phone();
		}
		// Nothing to judge yet — let them apply it and decide at placement.
		if ( '' === trim( $typed ) ) {
			return $valid;
		}

		if ( ! self::phone_matches( $locked, $typed ) ) {
			throw new Exception( self::lock_message( $locked ) );
		}
		return $valid;
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

		$typed = isset( $data['billing_phone'] ) ? (string) $data['billing_phone'] : '';

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
			if ( '' === trim( $typed ) ) {
				$errors->add( 'aun_referral_phone', self::lock_message( $locked, true ) );
				return;
			}
			if ( ! self::phone_matches( $locked, $typed ) ) {
				$errors->add( 'aun_referral_phone', self::lock_message( $locked ) );
				return;
			}
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
				array( 'status' => self::STATUS_REVOKED, 'order_id' => (int) $order->get_id() ),
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
		// counts, since the referral did its job of bringing them in. Matched
		// by the order's billing phone as well as by account, because app
		// customers are known by number and may check out as a guest — keyed
		// on the account alone, the referrer simply never got paid.
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
			// Only when we have one — see create_friend_coupon(). An app-only
			// account has no user_email, and locking to '' made the reward
			// unspendable.
			$email = self::customer_email( $user_id );
			if ( '' !== $email ) {
				$coupon->set_email_restrictions( array( $email ) );
			}
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

		$invited  = 0;
		$rewarded = 0;
		$earned   = 0.0;
		$coupons  = array();
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
						if ( $cid > 0 ) {
							$rc = new WC_Coupon( $cid );
							self::heal_coupon_restrictions( $rc );
							$value = (float) $rc->get_amount();
							$spent = (int) $rc->get_usage_count() > 0;
						}
					}
					$earned   += $value;
					$coupons[] = array(
						'code'  => (string) $r->reward_coupon,
						'date'  => substr( (string) $r->rewarded_at, 0, 10 ),
						'value' => $value,
						'used'  => $spent,
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
		$my_coupon       = '';
		$my_coupon_phone = '';
		if ( $mine && ! empty( $mine->friend_coupon )
			&& self::STATUS_REVOKED !== $mine->status ) {
			// Only offer it while it can still be spent.
			$cid = function_exists( 'wc_get_coupon_id_by_code' )
				? (int) wc_get_coupon_id_by_code( (string) $mine->friend_coupon ) : 0;
			if ( $cid > 0 ) {
				$c = new WC_Coupon( $cid );
				self::heal_coupon_restrictions( $c );
				if ( 0 === (int) $c->get_usage_count() ) {
					$my_coupon = (string) $mine->friend_coupon;
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
			'my_coupon'       => $my_coupon,
			'my_coupon_phone' => $my_coupon_phone,
			'friend_type'     => $s['friend_type'],
			'friend_amount'   => $s['friend_amount'],
			'referrer_amount' => $s['referrer_amount'],
			'invited'         => $invited,
			'rewarded'        => $rewarded,
			// Total value of every reward earned, so the referrer can see what
			// the programme has actually been worth to them.
			'earned'          => round( $earned, 2 ),
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
