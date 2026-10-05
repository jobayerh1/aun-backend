<?php
/**
 * AUN Rewards — the ceiling: earned rewards together never take more than a
 * set share (default 10%) off one order. And nothing earned is ever stranded
 * by it: what doesn't fit goes back to the customer's Rewards.
 *
 * Earned credit is a referrer's THANKS- rewards and the owner reward (NEXT-).
 * They may be combined with each other (AUN_App_Referrals::allow_reward_stacking)
 * and with nothing else; the ceiling is what stops "combine" becoming "give
 * away". 10% is half the ~20% margin — the same as a new customer's first
 * order (friend 5% + inviter 5%). See AUN-REWARDS-PLAN.md §1 and §6.
 *
 * USE WHAT FITS, KEEP THE REST. An invite reward is a fixed amount (5% of the
 * friend's order — ৳3,000 from a ৳60,000 projector). Refusing it outright
 * whenever it is bigger than the room would lock a customer out of money they
 * earned on every smaller order. So, like a wallet balance:
 *   • invite rewards give way first, the latest applied first: the part that
 *     fits stays on the order, the rest is split off into a NEW code with the
 *     same expiry and the same phone lock, back in their Rewards;
 *   • the owner reward is a percentage of one projector and cannot be split —
 *     it is taken off only when it cannot fit even on its own;
 *   • value is preserved at every moment — an abandoned cart or a cancelled
 *     order leaves the customer holding both parts, which add up to the whole.
 *
 * Same shape as the phone lock (AUN_App_Referrals::on_applied_coupon):
 *   - APPLYING: fitted on the spot, with the numbers said out loud.
 *   - PLACING the order: fitted again, because the cart can shrink after a
 *     reward was applied; the customer is told and presses Place order again.
 *   - The block checkout / Store API never runs the classic hook; it gets a
 *     plain report through its own cart-validation hook.
 *
 * Deliberately NOT `woocommerce_coupon_is_valid`: that runs on every cart
 * recalculation, and a refusal there is sticky and silent — see the lesson
 * recorded on AUN_App_Referrals::on_applied_coupon.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Rewards_Ceiling {

	/** Coupon meta on a balance split off an invite reward: the code it came from. */
	const META_SPLIT_OF = '_aun_referral_split_of';

	/** The ceiling in % of the items (0 = no ceiling). */
	public static function percent() {
		return class_exists( 'AUN_App_Owner_Rewards' ) ? max( 0, (int) AUN_App_Owner_Rewards::ceiling_percent() ) : 0;
	}

	/**
	 * The most rewards may take off an order of this subtotal.
	 *
	 * @param float $subtotal Items, before discounts.
	 * @return float INF when there is no ceiling.
	 */
	public static function cap( $subtotal ) {
		$p = self::percent();
		return $p > 0 ? round( (float) $subtotal * $p / 100, 2 ) : INF;
	}

	/** The smallest money step the store prices in (৳1 at 0 decimals). */
	public static function unit() {
		$d = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		return pow( 10, -max( 0, min( 4, $d ) ) );
	}

	/**
	 * Rounding slack, so an exact 10% is never refused: each percentage reward
	 * can round up by half a step.
	 */
	private static function slack( $n ) {
		return 0.5 * self::unit() * max( 1, (int) $n ) + 0.0001;
	}

	/**
	 * Can this reward give way by splitting? An invite reward (a fixed amount).
	 * The owner reward and anything else earned cannot.
	 */
	public static function is_flexible( $coupon ) {
		if ( is_string( $coupon ) ) {
			$id = function_exists( 'wc_get_coupon_id_by_code' ) ? (int) wc_get_coupon_id_by_code( $coupon ) : 0;
			if ( $id < 1 ) {
				return false;
			}
			$coupon = new WC_Coupon( $id );
		}
		return is_a( $coupon, 'WC_Coupon' ) && 'fixed_cart' === $coupon->get_discount_type()
			&& AUN_App_Referrals::is_reward_coupon( $coupon );
	}

	/**
	 * What the earned rewards in a cart come to. Totals must be current.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return array{subtotal:float,cap:float,total:float,codes:array<string,float>}
	 *         `codes` in the order they were applied.
	 */
	public static function measure( $cart ) {
		$subtotal = (float) $cart->get_subtotal();
		$by_code  = (array) $cart->get_coupon_discount_totals();
		$codes    = array();
		$total    = 0.0;
		foreach ( (array) $cart->get_applied_coupons() as $code ) {
			$code = (string) $code;
			if ( ! AUN_App_Referrals::is_earned_coupon( $code ) ) {
				continue;
			}
			$amount         = (float) ( $by_code[ $code ] ?? 0 );
			$codes[ $code ] = $amount;
			$total         += $amount;
		}
		return array(
			'subtotal' => $subtotal,
			'cap'      => self::cap( $subtotal ),
			'total'    => $total,
			'codes'    => $codes,
		);
	}

	/** Past the ceiling (beyond rounding)? */
	public static function is_over( $m ) {
		if ( ! is_finite( $m['cap'] ) ) {
			return false;
		}
		return $m['total'] - $m['cap'] > self::slack( count( $m['codes'] ) );
	}

	/**
	 * The decision, as a pure function — the website cart and the showroom
	 * screen both use it, so the two counters can never disagree.
	 *
	 * @param float $cap   The most rewards may take off.
	 * @param array $items In the order they were applied:
	 *                     [ [ 'code' => string, 'amount' => float (what it takes off now), 'flexible' => bool ], … ].
	 * @return array{remove:string[],shrink:array<string,float>,fits:bool}
	 *         `remove`: rewards to take off whole (they stay in the wallet untouched);
	 *         `shrink`: code => how much of it goes back to the wallet as a new code.
	 */
	public static function plan( $cap, $items ) {
		$out   = array( 'remove' => array(), 'shrink' => array(), 'fits' => true );
		$total = 0.0;
		foreach ( $items as $it ) {
			$total += (float) $it['amount'];
		}
		if ( ! is_finite( (float) $cap ) ) {
			return $out;
		}
		$slack  = self::slack( count( $items ) );
		$excess = $total - (float) $cap;
		if ( $excess <= $slack ) {
			return $out;
		}
		$unit = self::unit();

		$flex_total = 0.0;
		foreach ( $items as $it ) {
			if ( ! empty( $it['flexible'] ) ) {
				$flex_total += (float) $it['amount'];
			}
		}

		// 1. A percentage reward that could not fit even with every invite
		//    reward gone comes off whole — the latest applied first.
		foreach ( array_reverse( $items ) as $it ) {
			if ( $excess - $flex_total <= $slack ) {
				break;
			}
			if ( empty( $it['flexible'] ) ) {
				$out['remove'][] = (string) $it['code'];
				$excess         -= (float) $it['amount'];
			}
		}

		// 2. Invite rewards give way, the latest applied first: part of one
		//    goes back to the wallet, or the whole of it when too little would
		//    be left to be worth a code.
		foreach ( array_reverse( $items ) as $it ) {
			if ( $excess <= $slack ) {
				break;
			}
			if ( empty( $it['flexible'] ) ) {
				continue;
			}
			$amount = (float) $it['amount'];
			$take   = min( $amount, ceil( round( $excess / $unit, 6 ) ) * $unit );
			if ( $amount - $take < $unit ) {
				$out['remove'][] = (string) $it['code'];
				$excess         -= $amount;
			} else {
				$out['shrink'][ (string) $it['code'] ] = round( $take, 4 );
				$excess                               -= $take;
			}
		}
		$out['fits'] = $excess <= $slack;
		return $out;
	}

	/**
	 * Split `$take` off an invite reward into a new code: same value rules,
	 * same expiry, same phone lock, marked as its balance. The original keeps
	 * its code (it is the one in the cart) and is reduced by `$take`.
	 *
	 * The new code is made FIRST and the original reduced only once it exists,
	 * so a failure part-way can never lose the customer money.
	 *
	 * @return string The new code, or '' when nothing was changed.
	 */
	public static function split_reward( $code, $take ) {
		$id = function_exists( 'wc_get_coupon_id_by_code' ) ? (int) wc_get_coupon_id_by_code( (string) $code ) : 0;
		if ( $id < 1 ) {
			return '';
		}
		$from = new WC_Coupon( $id );
		$take = round( (float) $take, 4 );
		if ( $take <= 0 || $take >= (float) $from->get_amount() || (int) $from->get_usage_count() > 0 || ! self::is_flexible( $from ) ) {
			return '';
		}
		$was = (float) $from->get_amount();
		try {
			$new_code = AUN_App_Owner_Rewards::new_code( 'THANKS-' );
			$c        = new WC_Coupon();
			$c->set_code( $new_code );
			$c->set_discount_type( 'fixed_cart' );
			$c->set_amount( $take );
			$c->set_usage_limit( 1 );
			$c->set_usage_limit_per_user( 1 );
			$c->set_individual_use( (bool) $from->get_individual_use() );
			$c->set_minimum_amount( $from->get_minimum_amount() );
			$c->set_maximum_amount( $from->get_maximum_amount() );
			$c->set_product_ids( $from->get_product_ids() );
			$c->set_excluded_product_ids( $from->get_excluded_product_ids() );
			$c->set_product_categories( $from->get_product_categories() );
			$c->set_excluded_product_categories( $from->get_excluded_product_categories() );
			$c->set_exclude_sale_items( (bool) $from->get_exclude_sale_items() );
			$c->set_email_restrictions( $from->get_email_restrictions() );
			$c->set_date_expires( $from->get_date_expires() );
			$c->update_meta_data( AUN_App_Referrals::COUPON_META_PHONE, (string) $from->get_meta( AUN_App_Referrals::COUPON_META_PHONE ) );
			$c->update_meta_data( AUN_App_Referrals::COUPON_META_REWARD, '1' );
			$c->update_meta_data( self::META_SPLIT_OF, strtoupper( (string) $from->get_code() ) );
			$c->set_description( 'AUN Care app referral — balance of ' . strtoupper( (string) $from->get_code() ) );
			$c->save();
		} catch ( Exception $e ) {
			return '';
		}
		if ( $c->get_id() < 1 ) {
			return '';
		}
		// Only now reduce the original. If that fails, the new code goes again:
		// the customer is left exactly as they were.
		try {
			$from->set_amount( round( $was - $take, 4 ) );
			$from->save();
		} catch ( Exception $e ) {
			wp_delete_post( $c->get_id(), true );
			return '';
		}
		return $new_code;
	}

	/**
	 * Make the earned rewards in a cart fit.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return array{removed:string[],split:array<string,array{kept:float,moved:float,code:string}>,cap:float,total:float,fits:bool}
	 */
	public static function fit_cart( $cart ) {
		$cart->calculate_totals();
		$m   = self::measure( $cart );
		$out = array( 'removed' => array(), 'split' => array(), 'cap' => $m['cap'], 'total' => $m['total'], 'fits' => true );
		if ( ! self::is_over( $m ) ) {
			return $out;
		}
		$items = array();
		foreach ( $m['codes'] as $code => $amount ) {
			$items[] = array( 'code' => $code, 'amount' => $amount, 'flexible' => self::is_flexible( (string) $code ) );
		}
		$plan = self::plan( $m['cap'], $items );

		foreach ( $plan['shrink'] as $code => $take ) {
			$new = self::split_reward( $code, $take );
			if ( '' === $new ) {
				// Could not split (should not happen): keep it whole instead.
				$plan['remove'][] = $code;
				continue;
			}
			$out['split'][ $code ] = array(
				'kept'  => round( (float) $m['codes'][ $code ] - $take, 4 ),
				'moved' => $take,
				'code'  => $new,
			);
		}
		foreach ( array_unique( $plan['remove'] ) as $code ) {
			$cart->remove_coupon( $code );
			$out['removed'][] = (string) $code;
		}
		$cart->calculate_totals();
		$after        = self::measure( $cart );
		$out['total'] = $after['total'];
		$out['fits']  = ! self::is_over( $after );
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * 1. Applying a reward
	 * --------------------------------------------------------------------- */

	/**
	 * A reward was just applied: fit the rewards under the ceiling, and say
	 * exactly what happened.
	 *
	 * On `woocommerce_applied_coupon` at priority 5 — before the phone-lock
	 * notice (priority 10), which then says nothing about a code no longer
	 * in the cart.
	 *
	 * @param string $code Coupon code just applied.
	 */
	public static function on_applied_coupon( $code ) {
		if ( self::percent() < 1 || ! function_exists( 'WC' ) || ! WC()->cart || ! class_exists( 'WC_Coupon' ) ) {
			return;
		}
		$code = (string) $code;
		if ( ! AUN_App_Referrals::is_earned_coupon( $code ) ) {
			return;
		}
		$r = self::fit_cart( WC()->cart );
		if ( empty( $r['removed'] ) && empty( $r['split'] ) ) {
			return;
		}

		$limit = self::money( $r['cap'] ) . ' (' . self::percent() . '%)';
		if ( in_array( $code, $r['removed'], true ) ) {
			// WooCommerce has already said "Coupon code applied successfully."
			self::drop_success_notice( $code );
			$others_in_cart = array_filter( (array) WC()->cart->get_applied_coupons(), array( 'AUN_App_Referrals', 'is_earned_coupon' ) );
			wc_add_notice(
				sprintf(
					$others_in_cart
						/* translators: 1: reward code, 2: the most rewards may take off, e.g. "৳2,500 (10%)". */
						? __( 'Rewards can take up to %2$s off this order, and your other rewards already use it — so %1$s has not been used. It stays in your Rewards for your next order.', 'aun-app-api' )
						/* translators: 1: reward code, 2: the most rewards may take off, e.g. "৳2,500 (10%)". */
						: __( 'Rewards can take up to %2$s off this order, so %1$s has not been used. It stays in your Rewards for a bigger order.', 'aun-app-api' ),
					strtoupper( $code ),
					$limit
				),
				'error'
			);
		} elseif ( isset( $r['split'][ $code ] ) ) {
			self::drop_success_notice( $code );
			$s = $r['split'][ $code ];
			wc_add_notice(
				sprintf(
					/* translators: 1: reward code, 2: amount used now, 3: the limit, e.g. "৳2,500 (10%)", 4: amount kept, 5: the new code holding it. */
					__( '%1$s applied: %2$s off — the most rewards can take off this order is %3$s. The other %4$s of this reward is saved for next time as %5$s (in the AUN Care app under Rewards).', 'aun-app-api' ),
					strtoupper( $code ),
					self::money( $s['kept'] ),
					$limit,
					self::money( $s['moved'] ),
					$s['code']
				),
				'success'
			);
		}
		// Rewards that made room for the one just applied.
		$others = self::describe_others( $r, $code );
		if ( '' !== $others ) {
			wc_add_notice(
				sprintf(
					/* translators: 1: the limit, e.g. "৳2,500 (10%)", 2: what went back to the wallet. */
					__( 'To keep rewards within %1$s of this order, %2$s — nothing is lost.', 'aun-app-api' ),
					$limit,
					$others
				),
				'notice'
			);
		}
	}

	/** "THANKS-A was taken off and stays in your Rewards; ৳500 of THANKS-B went back as THANKS-C". */
	private static function describe_others( $r, $except = '' ) {
		$parts = array();
		foreach ( $r['removed'] as $c ) {
			if ( $c !== $except ) {
				/* translators: %s: reward code. */
				$parts[] = sprintf( __( '%s was taken off and stays in your Rewards', 'aun-app-api' ), strtoupper( $c ) );
			}
		}
		foreach ( $r['split'] as $c => $s ) {
			if ( $c !== $except ) {
				/* translators: 1: amount, 2: reward code, 3: new code. */
				$parts[] = sprintf( __( '%1$s of %2$s went back to your Rewards as %3$s', 'aun-app-api' ), self::money( $s['moved'] ), strtoupper( $c ), $s['code'] );
			}
		}
		return implode( '; ', $parts );
	}

	/* --------------------------------------------------------------------- *
	 * 2. Placing the order
	 * --------------------------------------------------------------------- */

	/**
	 * Classic checkout: the cart may have shrunk since a reward was applied.
	 * Fit again; if anything changed, stop so the customer sees the new total
	 * before paying it — the next press goes through.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Errors.
	 */
	public static function on_checkout_validation( $data, $errors ) {
		if ( self::percent() < 1 || ! function_exists( 'WC' ) || ! WC()->cart || ! is_object( $errors ) ) {
			return;
		}
		$r = self::fit_cart( WC()->cart );
		if ( empty( $r['removed'] ) && empty( $r['split'] ) ) {
			return;
		}
		$errors->add(
			'aun_rewards_ceiling',
			sprintf(
				/* translators: 1: the limit, e.g. "৳2,500 (10%)", 2: what went back to the wallet. */
				__( 'Rewards can take up to %1$s off this order, and your cart has changed since you applied them. So %2$s — nothing is lost. Check the new total and place your order again.', 'aun-app-api' ),
				self::money( $r['cap'] ) . ' (' . self::percent() . '%)',
				self::describe_others( $r )
			)
		);
	}

	/**
	 * Block checkout / Store API: the same rule through its own cart check.
	 * Only reports — the customer removes a reward themselves in that UI.
	 *
	 * @param WP_Error $errors Errors.
	 * @param WC_Cart  $cart   Cart.
	 */
	public static function on_store_api_cart_errors( $errors, $cart ) {
		if ( self::percent() < 1 || ! is_object( $errors ) || ! is_a( $cart, 'WC_Cart' ) ) {
			return;
		}
		$m = self::measure( $cart );
		if ( self::is_over( $m ) ) {
			$errors->add(
				'aun_rewards_ceiling',
				sprintf(
					/* translators: 1: the most rewards may take off, 2: the ceiling %, 3: what they come to. */
					__( 'Rewards can take up to %1$s (%2$s%%) off this order, and yours come to %3$s. Remove one to continue — it stays in your Rewards for next time.', 'aun-app-api' ),
					self::money( $m['cap'] ),
					self::percent(),
					self::money( $m['total'] )
				)
			);
		}
	}

	/* --------------------------------------------------------------------- *
	 * Helpers
	 * --------------------------------------------------------------------- */

	public static function money( $amount ) {
		return function_exists( 'wc_price' )
			? trim( wp_strip_all_tags( html_entity_decode( wc_price( (float) $amount ), ENT_QUOTES, 'UTF-8' ) ) )
			: number_format( (float) $amount, 2 );
	}

	/** Remove WooCommerce's "applied successfully" notice for this coupon. */
	private static function drop_success_notice( $code ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$all = WC()->session->get( 'wc_notices', array() );
		if ( empty( $all['success'] ) || ! is_array( $all['success'] ) ) {
			return;
		}
		$text  = ( new WC_Coupon( $code ) )->get_coupon_message( WC_Coupon::WC_COUPON_SUCCESS );
		$match = array( (string) $text, (string) apply_filters( 'woocommerce_add_success', $text ) );
		$kept  = array();
		$done  = false;
		foreach ( $all['success'] as $n ) {
			$msg = is_array( $n ) ? (string) ( $n['notice'] ?? '' ) : (string) $n;
			if ( ! $done && in_array( $msg, $match, true ) ) {
				$done = true;
				continue;
			}
			$kept[] = $n;
		}
		if ( $kept ) {
			$all['success'] = $kept;
		} else {
			unset( $all['success'] );
		}
		WC()->session->set( 'wc_notices', $all );
	}
}
