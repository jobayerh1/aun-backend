<?php
/**
 * AUN Rewards — the showroom counter (wp-admin → AUN App → Rewards).
 *
 * Walk-in sales are entered in the ERP, not the website, and staff type any
 * discount into the ERP by hand. This screen is how a reward earned anywhere
 * is honoured at the counter, and how a showroom purchase earns one:
 *
 *   1. Staff type the customer's phone. Their ERP purchases are shown, and an
 *      eligible projector sale ISSUES its owner reward right there — the same
 *      idempotent sync the app runs on sign-in, so walk-in buyers without the
 *      app are rewarded too.
 *   2. To SPEND rewards, a code is sent to the customer's phone and they read
 *      it out. Nobody can spend someone else's rewards by typing a number.
 *   3. Staff pick the rewards and type the items total (and the projector's
 *      price, for an owner reward). The discount is worked out with exactly
 *      the website's rules — AUN_App_Rewards_Ceiling::plan() — and shown as
 *      one number to enter in the ERP sale.
 *   4. The ERP invoice number, then Redeem: recomputed on the server, refused
 *      if anything changed, one redemption per invoice. The same coupons are
 *      marked used, so the website refuses them afterwards.
 *
 * A friend's welcome discount can be spent here too; the inviter is paid once
 * the return window has passed and the ERP still shows the sale
 * (AUN_App_Referrals::pay_showroom_claims).
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Rewards_Showroom {

	/** Shop managers run the counter; administrators have this too. */
	const CAP          = 'manage_woocommerce';
	const SLUG         = 'aun-app-rewards';
	const OTP_PURPOSE  = 'showroom';
	const VERIFIED_TTL = 1200; // the customer's code opens the screen for 20 minutes
	const NONCE        = 'aun_rw_showroom';

	/** A redeem that failed validation — the page re-renders it with the reason. */
	private static $redeem_error = '';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aun_app_reward_redemptions';
	}

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
	}

	public static function menu() {
		$hook = add_submenu_page( 'aun-app', 'AUN Rewards — Showroom', 'Rewards', self::CAP, self::SLUG, array( __CLASS__, 'page' ) );
		if ( $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'handle_post' ) );
		}
	}

	/* --------------------------------------------------------------------- *
	 * State
	 * --------------------------------------------------------------------- */

	private static function verified_key( $phone ) {
		return 'aun_rw_sr_' . get_current_user_id() . '_' . md5( (string) $phone );
	}

	/** Has the customer read out their code to THIS staff member, recently? */
	public static function is_verified( $phone ) {
		return '' !== (string) $phone && (bool) get_transient( self::verified_key( $phone ) );
	}

	/**
	 * Check the code the customer read out; on success the screen opens their
	 * rewards to this staff member for 20 minutes.
	 *
	 * @return array{ok:bool,code:string,message:string}
	 */
	public static function confirm_code( $phone, $otp ) {
		$r = AUN_App_OTP::verify( (string) $phone, preg_replace( '/\D+/', '', (string) $otp ), self::OTP_PURPOSE );
		if ( ! empty( $r['ok'] ) ) {
			set_transient( self::verified_key( $phone ), 1, self::VERIFIED_TTL );
		}
		return $r;
	}

	private static function flash( $type, $msg ) {
		$key = 'aun_rw_flash_' . get_current_user_id();
		$all = get_transient( $key );
		// NOT (array) get_transient(): a missing transient is `false`, and
		// (array) false is [ false ] — a bogus first message on every page.
		$all   = is_array( $all ) ? $all : array();
		$all[] = array( $type, (string) $msg );
		set_transient( $key, $all, 120 );
	}

	private static function take_flash() {
		$key = 'aun_rw_flash_' . get_current_user_id();
		$all = get_transient( $key );
		delete_transient( $key );
		return is_array( $all ) ? $all : array();
	}

	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/** 8801XXXXXXXXX → 01XXXXXXXXX (the canonical form is 88 + the local number). */
	private static function local( $phone ) {
		return substr( (string) $phone, 2 );
	}

	private static function money( $n ) {
		return AUN_App_Rewards_Ceiling::money( $n );
	}

	/** Round to the store's money step (৳1 at 0 decimals). */
	private static function r( $x ) {
		$u = AUN_App_Rewards_Ceiling::unit();
		return round( round( (float) $x / $u ) * $u, 4 );
	}

	private static function number( $raw ) {
		$raw = preg_replace( '/[^0-9.]/', '', str_replace( ',', '', (string) $raw ) );
		return '' === $raw ? 0.0 : round( (float) $raw, 2 );
	}

	private static function clean_invoice( $raw ) {
		return substr( strtoupper( trim( preg_replace( '/[^A-Za-z0-9\-\/_. ]/', '', (string) $raw ) ) ), 0, 64 );
	}

	private static function date( $ts ) {
		return $ts ? date_i18n( 'j M Y', (int) $ts + (int) ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) ) : 'never';
	}

	/* --------------------------------------------------------------------- *
	 * What the customer has
	 * --------------------------------------------------------------------- */

	/**
	 * Every reward this phone can spend right now.
	 *
	 * @param string $phone Canonical phone.
	 * @return array{owner:?array,invite:array[],welcome:?array}
	 */
	public static function wallet( $phone ) {
		$out = array( 'owner' => null, 'invite' => array(), 'welcome' => null );
		if ( '' === (string) $phone || ! class_exists( 'WC_Coupon' ) ) {
			return $out;
		}

		// The owner reward — shown even if the programme was switched off
		// later: a reward already given keeps its promise.
		if ( class_exists( 'AUN_App_Owner_Rewards' ) ) {
			$row = AUN_App_Owner_Rewards::active_row( $phone );
			if ( $row && '' !== (string) $row->coupon ) {
				$cid = (int) wc_get_coupon_id_by_code( (string) $row->coupon );
				$c   = $cid > 0 ? new WC_Coupon( $cid ) : null;
				if ( $c && (int) $c->get_usage_count() < max( 1, (int) $c->get_usage_limit() ) ) {
					$out['owner'] = array(
						'id'      => (int) $row->id,
						'code'    => strtoupper( (string) $row->coupon ),
						'percent' => (float) $row->percent,
						'expires' => (int) strtotime( get_gmt_from_date( (string) $row->expires_at ) . ' UTC' ),
					);
				}
			}
		}

		// Invite rewards — every THANKS code locked to this phone, including
		// balances split off by the ceiling. Soonest-ending first: those are
		// the ones to spend first.
		$ids = get_posts( array(
			'post_type'   => 'shop_coupon',
			'post_status' => 'publish',
			'numberposts' => 100,
			'fields'      => 'ids',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'AND',
				array( 'key' => AUN_App_Referrals::COUPON_META_PHONE, 'value' => (string) $phone ),
				array( 'key' => AUN_App_Referrals::COUPON_META_REWARD, 'value' => '1' ),
			),
		) );
		foreach ( (array) $ids as $id ) {
			$c = new WC_Coupon( (int) $id );
			if ( 'fixed_cart' !== $c->get_discount_type() || (int) $c->get_usage_count() >= max( 1, (int) $c->get_usage_limit() ) ) {
				continue;
			}
			$exp = $c->get_date_expires();
			if ( $exp && $exp->getTimestamp() < time() ) {
				continue;
			}
			$out['invite'][] = array(
				'code'    => strtoupper( (string) $c->get_code() ),
				'amount'  => (float) $c->get_amount(),
				'expires' => $exp ? $exp->getTimestamp() : 0,
				'min'     => (float) $c->get_minimum_amount(),
			);
		}
		usort( $out['invite'], function ( $a, $b ) {
			$ea = $a['expires'] ? $a['expires'] : PHP_INT_MAX;
			$eb = $b['expires'] ? $b['expires'] : PHP_INT_MAX;
			return $ea === $eb ? strcmp( $a['code'], $b['code'] ) : ( $ea < $eb ? -1 : 1 );
		} );

		$out['welcome'] = AUN_App_Referrals::welcome_for_phone( $phone );
		return $out;
	}

	public static function wallet_is_empty( $w ) {
		return ! $w['owner'] && empty( $w['invite'] ) && ! $w['welcome'];
	}

	/**
	 * The customer's ERP purchases — and, while we are here, the owner rewards
	 * they have earned (idempotent: a sale is never credited twice).
	 *
	 * @return array{error:string,sales:array,issued:int}
	 */
	public static function erp_purchases( $phone ) {
		if ( ! class_exists( 'AUN_App_ERP' ) || ! AUN_App_ERP::configured() ) {
			return array( 'error' => 'The ERP connection is not set up (AUN App → Settings).', 'sales' => array(), 'issued' => 0 );
		}
		$sales = AUN_App_ERP::lookup_phone( $phone );
		if ( is_wp_error( $sales ) ) {
			return array( 'error' => 'The ERP did not answer — ' . $sales->get_error_message() . ' Rewards already issued are still listed below.', 'sales' => array(), 'issued' => 0 );
		}
		$issued = 0;
		if ( class_exists( 'AUN_App_Owner_Rewards' ) && AUN_App_Owner_Rewards::enabled() ) {
			$issued = (int) AUN_App_Owner_Rewards::sync_erp_sales( $phone, $sales );
		}
		return array( 'error' => '', 'sales' => (array) $sales, 'issued' => $issued );
	}

	/* --------------------------------------------------------------------- *
	 * The sum
	 * --------------------------------------------------------------------- */

	/**
	 * What picking these rewards takes off this sale — the website's rules.
	 *
	 * @param string $phone Canonical phone.
	 * @param array  $in    picked (codes), items_total, projector_price, full_price.
	 * @return array
	 */
	public static function quote( $phone, $in ) {
		$w      = self::wallet( $phone );
		$picked = array_values( array_unique( array_map( 'strtoupper', (array) ( $in['picked'] ?? array() ) ) ) );
		$items  = max( 0, (float) ( $in['items_total'] ?? 0 ) );
		$proj   = max( 0, (float) ( $in['projector_price'] ?? 0 ) );
		$full   = ! empty( $in['full_price'] );
		$out    = array(
			'ok'          => false,
			'error'       => '',
			'lines'       => array(),
			'discount'    => 0.0,
			'pay'         => $items,
			'cap'         => 0.0,
			'items_total' => $items,
			'fingerprint' => '',
		);

		if ( empty( $picked ) ) {
			$out['error'] = 'Tick at least one reward to use.';
			return $out;
		}
		if ( $items <= 0 ) {
			$out['error'] = 'Type the items total of this sale (before any discount).';
			return $out;
		}

		$welcome = $w['welcome'] && in_array( $w['welcome']['code'], $picked, true ) ? $w['welcome'] : null;
		if ( $welcome ) {
			if ( count( $picked ) > 1 ) {
				$out['error'] = 'The welcome discount is used on its own — it cannot be combined with other rewards.';
				return $out;
			}
			if ( $welcome['min'] > 0 && $items < $welcome['min'] ) {
				$out['error'] = 'The welcome discount needs a sale of at least ' . self::money( $welcome['min'] ) . '.';
				return $out;
			}
			$use = 'percent' === $welcome['type']
				? self::r( $items * $welcome['amount'] / 100 )
				: min( $welcome['amount'], $items );
			$out['lines'][] = array(
				'kind'  => 'welcome',
				'code'  => $welcome['code'],
				'ref'   => $welcome['claim_id'],
				'label' => 'Welcome discount (invited by a friend) — ' . ( 'percent' === $welcome['type'] ? self::num( $welcome['amount'] ) . '% of the sale' : self::money( $welcome['amount'] ) ),
				'use'   => $use,
				'keep'  => 0.0,
				'note'  => 'Their inviter is rewarded once the return window has passed.',
			);
			$out['cap'] = $items;
			return self::finish( $out, $phone, $picked, $in );
		}

		$plan_items = array();
		$by_code    = array();

		if ( $w['owner'] && in_array( $w['owner']['code'], $picked, true ) ) {
			if ( $proj <= 0 ) {
				$out['error'] = 'Type the price of the projector the owner reward is for.';
				return $out;
			}
			if ( $proj > $items + 0.001 ) {
				$out['error'] = 'The projector\'s price cannot be more than the items total.';
				return $out;
			}
			if ( ! $full ) {
				$out['error'] = 'The owner reward is for a projector at its full price. Tick the box to confirm it is not already on a campaign or sale price — if it is, the owner reward cannot be used on it.';
				return $out;
			}
			$amt = self::r( $proj * $w['owner']['percent'] / 100 );
			$by_code[ $w['owner']['code'] ] = array(
				'kind'  => 'owner',
				'code'  => $w['owner']['code'],
				'ref'   => $w['owner']['id'],
				'label' => 'Owner reward — ' . self::num( $w['owner']['percent'] ) . '% off one projector (' . self::money( $proj ) . ')',
				'face'  => $amt,
			);
			$plan_items[] = array( 'code' => $w['owner']['code'], 'amount' => $amt, 'flexible' => false );
		}

		foreach ( $w['invite'] as $inv ) {
			if ( ! in_array( $inv['code'], $picked, true ) ) {
				continue;
			}
			$line = array(
				'kind'  => 'invite',
				'code'  => $inv['code'],
				'ref'   => 0,
				'label' => 'Invite reward — ' . self::money( $inv['amount'] ),
				'face'  => $inv['amount'],
			);
			if ( $inv['min'] > 0 && $items < $inv['min'] ) {
				$line['use']  = 0.0;
				$line['keep'] = $inv['amount'];
				$line['note'] = 'Needs a sale of at least ' . self::money( $inv['min'] ) . ' — not used, stays in their Rewards.';
				$out['lines'][] = $line;
				continue;
			}
			$by_code[ $inv['code'] ] = $line;
			$plan_items[]            = array( 'code' => $inv['code'], 'amount' => min( $inv['amount'], $items ), 'flexible' => true );
		}

		if ( empty( $plan_items ) && empty( $out['lines'] ) ) {
			$out['error'] = 'Those rewards are not available any more — look the customer up again.';
			return $out;
		}

		$cap        = AUN_App_Rewards_Ceiling::cap( $items );
		$cap        = is_finite( $cap ) ? min( $cap, $items ) : $items;
		$out['cap'] = $cap;
		$plan       = AUN_App_Rewards_Ceiling::plan( $cap, $plan_items );
		$limit      = self::money( $cap ) . ( AUN_App_Rewards_Ceiling::percent() > 0 ? ' (' . AUN_App_Rewards_Ceiling::percent() . '%)' : '' );

		foreach ( $plan_items as $pi ) {
			$line = $by_code[ $pi['code'] ];
			$amt  = (float) $pi['amount'];
			if ( in_array( $pi['code'], $plan['remove'], true ) ) {
				$line['use']  = 0.0;
				$line['keep'] = (float) $line['face'];
				$line['note'] = 'Not used — rewards can take up to ' . $limit . ' off this sale. Stays in their Rewards.';
			} elseif ( isset( $plan['shrink'][ $pi['code'] ] ) ) {
				$take         = (float) $plan['shrink'][ $pi['code'] ];
				$line['use']  = round( $amt - $take, 4 );
				$line['keep'] = round( (float) $line['face'] - $line['use'], 4 );
				$line['note'] = 'The most rewards can take off this sale is ' . $limit . '. The other ' . self::money( $line['keep'] ) . ' stays in their Rewards as a new code.';
			} else {
				$line['use']  = $amt;
				$line['keep'] = round( (float) $line['face'] - $amt, 4 );
				$line['note'] = $line['keep'] > 0 ? 'The other ' . self::money( $line['keep'] ) . ' stays in their Rewards as a new code.' : '';
			}
			unset( $line['face'] );
			$out['lines'][] = $line;
		}
		return self::finish( $out, $phone, $picked, $in );
	}

	private static function finish( $out, $phone, $picked, $in ) {
		$d = 0.0;
		foreach ( $out['lines'] as $l ) {
			$d += (float) $l['use'];
		}
		$out['discount'] = round( $d, 2 );
		$out['pay']      = round( $out['items_total'] - $d, 2 );
		$out['ok']       = $d > 0;
		if ( ! $out['ok'] && '' === $out['error'] ) {
			$out['error'] = 'None of the ticked rewards can be used on this sale.';
		}
		sort( $picked );
		$sig = array( $phone, $picked, round( (float) $out['items_total'], 2 ), round( (float) ( $in['projector_price'] ?? 0 ), 2 ), ! empty( $in['full_price'] ) );
		foreach ( $out['lines'] as $l ) {
			$sig[] = array( $l['code'], round( (float) $l['use'], 2 ), round( (float) $l['keep'], 2 ) );
		}
		$out['fingerprint'] = md5( wp_json_encode( $sig ) );
		return $out;
	}

	private static function num( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 2, '.', '' ), '0' ), '.' );
	}

	/* --------------------------------------------------------------------- *
	 * Redeeming, and undoing
	 * --------------------------------------------------------------------- */

	public static function invoice_used( $invoice ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . self::table() . " WHERE UPPER(invoice) = %s AND status = 'done' LIMIT 1",
			strtoupper( (string) $invoice )
		) );
	}

	/**
	 * Spend the quoted rewards on an ERP sale.
	 *
	 * Order matters, so a failure part-way never leaves money half-spent:
	 *   1. the owner reward (the only step that can refuse for a business
	 *      reason — "earned by this same sale");
	 *   2. setting aside what does not fit (a split only preserves value);
	 *   3. marking the invite rewards / welcome discount used;
	 *   4. the record.
	 *
	 * @return array{ok:bool,message:string,id?:int}
	 */
	public static function redeem( $phone, $in, $invoice, $fingerprint, $staff_id = 0 ) {
		global $wpdb;
		if ( ! self::is_verified( $phone ) ) {
			return array( 'ok' => false, 'message' => 'The customer\'s code has expired (20 minutes). Send a new one.' );
		}
		$invoice = self::clean_invoice( $invoice );
		if ( '' === $invoice ) {
			return array( 'ok' => false, 'message' => 'Type the ERP invoice number of this sale.' );
		}
		if ( self::invoice_used( $invoice ) ) {
			return array( 'ok' => false, 'message' => 'Rewards were already redeemed on invoice ' . $invoice . '. Undo that one first if it was a mistake.' );
		}
		$q = self::quote( $phone, $in );
		if ( ! $q['ok'] ) {
			return array( 'ok' => false, 'message' => $q['error'] );
		}
		if ( ! hash_equals( $q['fingerprint'], (string) $fingerprint ) ) {
			return array( 'ok' => false, 'message' => 'Something changed since you pressed Calculate — check the figures below and press Redeem again.' );
		}

		$users   = class_exists( 'AUN_App_Phone' ) ? AUN_App_Phone::find_users( $phone ) : array();
		$used_by = $users ? (int) $users[0]->ID : '';
		$lines   = $q['lines'];

		// 1. The owner reward.
		$owner_done = null;
		foreach ( $lines as $l ) {
			if ( 'owner' === $l['kind'] && $l['use'] > 0 ) {
				$r = AUN_App_Owner_Rewards::redeem_at_showroom( (int) $l['ref'], $invoice, $l['use'] );
				if ( empty( $r['ok'] ) ) {
					return array( 'ok' => false, 'message' => $r['message'] );
				}
				$owner_done = $l;
			}
		}

		// 2. Set aside what does not fit.
		foreach ( $lines as $i => $l ) {
			if ( 'invite' === $l['kind'] && $l['use'] > 0 && $l['keep'] > 0 ) {
				$new = AUN_App_Rewards_Ceiling::split_reward( $l['code'], $l['keep'] );
				if ( '' === $new ) {
					if ( $owner_done ) {
						AUN_App_Owner_Rewards::undo_showroom( (int) $owner_done['ref'], $invoice );
					}
					return array( 'ok' => false, 'message' => 'Could not set aside the rest of ' . $l['code'] . ' — nothing was redeemed. Please try again.' );
				}
				$lines[ $i ]['new_code'] = $new;
			}
		}

		// 3. Spend.
		foreach ( $lines as $i => $l ) {
			if ( $l['use'] <= 0 ) {
				continue;
			}
			if ( 'invite' === $l['kind'] ) {
				$cid = (int) wc_get_coupon_id_by_code( $l['code'] );
				if ( $cid > 0 ) {
					$c = new WC_Coupon( $cid );
					$c->increase_usage_count( $used_by );
					$c->update_meta_data( '_aun_showroom_invoice', $invoice );
					$c->save_meta_data();
				}
			} elseif ( 'welcome' === $l['kind'] ) {
				$r = AUN_App_Referrals::redeem_welcome_at_showroom( (int) $l['ref'], $invoice, $q['pay'] );
				if ( empty( $r['ok'] ) ) {
					return array( 'ok' => false, 'message' => $r['message'] );
				}
			}
		}

		// 4. The record.
		$wpdb->insert( self::table(), array(
			'phone'       => (string) $phone,
			'invoice'     => $invoice,
			'items_total' => round( (float) $q['items_total'], 2 ),
			'discount'    => round( (float) $q['discount'], 2 ),
			'detail'      => wp_json_encode( array( 'used_by' => $used_by, 'lines' => $lines ) ),
			'staff_id'    => (int) $staff_id,
			'status'      => 'done',
			'created_at'  => current_time( 'mysql' ),
		) );
		return array( 'ok' => true, 'message' => 'Redeemed.', 'id' => (int) $wpdb->insert_id );
	}

	/**
	 * Undo a redemption: every reward it spent comes back. What was set aside
	 * as a new code stays set aside — the customer holds the same value either
	 * way. A welcome discount can only come back while its inviter has not yet
	 * been rewarded for the sale.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public static function undo( $id, $staff_id = 0 ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) );
		if ( ! $row || 'done' !== $row->status ) {
			return array( 'ok' => false, 'message' => 'That redemption is not there to undo.' );
		}
		$data    = json_decode( (string) $row->detail, true );
		$lines   = (array) ( $data['lines'] ?? array() );
		$used_by = $data['used_by'] ?? '';

		// The one that can refuse goes first, before anything else is touched.
		foreach ( $lines as $l ) {
			if ( 'welcome' === ( $l['kind'] ?? '' ) && (float) $l['use'] > 0 ) {
				$r = AUN_App_Referrals::undo_welcome_at_showroom( (int) $l['ref'] );
				if ( empty( $r['ok'] ) ) {
					return array( 'ok' => false, 'message' => $r['message'] );
				}
			}
		}
		foreach ( $lines as $l ) {
			if ( (float) ( $l['use'] ?? 0 ) <= 0 ) {
				continue;
			}
			if ( 'owner' === $l['kind'] ) {
				AUN_App_Owner_Rewards::undo_showroom( (int) $l['ref'], (string) $row->invoice );
			} elseif ( 'invite' === $l['kind'] ) {
				$cid = (int) wc_get_coupon_id_by_code( (string) $l['code'] );
				if ( $cid > 0 ) {
					$c = new WC_Coupon( $cid );
					if ( (int) $c->get_usage_count() > 0 ) {
						$c->decrease_usage_count( $used_by );
					}
					$c->delete_meta_data( '_aun_showroom_invoice' );
					$c->save_meta_data();
				}
			}
		}
		$wpdb->update( self::table(), array(
			'status'    => 'undone',
			'undone_at' => current_time( 'mysql' ),
			'undone_by' => (int) $staff_id,
		), array( 'id' => (int) $row->id ) );
		return array( 'ok' => true, 'message' => 'Undone — the rewards are back with the customer.' );
	}

	public static function recent( $phone = '', $limit = 10 ) {
		global $wpdb;
		$t = self::table();
		if ( '' !== (string) $phone ) {
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE phone = %s ORDER BY id DESC LIMIT %d", $phone, (int) $limit ) );
		}
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", (int) $limit ) );
	}

	/* --------------------------------------------------------------------- *
	 * The screen: actions (before any output, so they can redirect)
	 * --------------------------------------------------------------------- */

	private static function posted_quote_input() {
		// phpcs:disable WordPress.Security.NonceVerification -- verified by the caller.
		$picked = isset( $_POST['picked'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['picked'] ) ) : array();
		return array(
			'picked'          => array_map( 'strtoupper', $picked ),
			'items_total'     => self::number( wp_unslash( $_POST['items_total'] ?? '' ) ),
			'projector_price' => self::number( wp_unslash( $_POST['projector_price'] ?? '' ) ),
			'full_price'      => ! empty( $_POST['full_price'] ),
		);
		// phpcs:enable
	}

	public static function handle_post() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['aun_rw_action'] ) ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'You are not allowed to do that.' );
		}
		check_admin_referer( self::NONCE, '_aun_rw' );
		$action = sanitize_key( wp_unslash( $_POST['aun_rw_action'] ) );
		$phone  = AUN_App_Phone::normalize( wp_unslash( $_POST['phone'] ?? '' ) );
		$phone  = $phone ? $phone : '';

		switch ( $action ) {
			case 'send_otp':
				if ( '' === $phone ) {
					break;
				}
				$r = AUN_App_OTP::request( $phone, '', self::OTP_PURPOSE );
				if ( ! empty( $r['ok'] ) ) {
					$msg = 'Code sent to ' . AUN_App_Phone::mask( $phone ) . '. Ask the customer to read it out.';
					if ( ! empty( $r['dev_otp'] ) ) {
						$msg .= ' (DEV MODE — no SMS sent; the code is ' . $r['dev_otp'] . '.)';
					}
					self::flash( 'success', $msg );
				} else {
					self::flash( 'error', (string) $r['message'] );
				}
				wp_safe_redirect( self::url( array( 'phone' => self::local( $phone ), 'code' => 1 ) ) );
				exit;

			case 'verify':
				if ( '' === $phone ) {
					break;
				}
				$r = self::confirm_code( $phone, wp_unslash( $_POST['otp'] ?? '' ) );
				if ( ! empty( $r['ok'] ) ) {
					self::flash( 'success', 'Code confirmed — you can use their rewards for the next 20 minutes.' );
					wp_safe_redirect( self::url( array( 'phone' => self::local( $phone ) ) ) );
				} else {
					self::flash( 'error', (string) $r['message'] );
					wp_safe_redirect( self::url( array( 'phone' => self::local( $phone ), 'code' => 1 ) ) );
				}
				exit;

			case 'redeem':
				if ( '' === $phone ) {
					break;
				}
				$r = self::redeem(
					$phone,
					self::posted_quote_input(),
					wp_unslash( $_POST['invoice'] ?? '' ),
					sanitize_text_field( wp_unslash( $_POST['fingerprint'] ?? '' ) ),
					get_current_user_id()
				);
				if ( ! empty( $r['ok'] ) ) {
					wp_safe_redirect( self::url( array( 'phone' => self::local( $phone ), 'done' => (int) $r['id'] ) ) );
					exit;
				}
				// Rendered again with the same figures and the reason.
				self::$redeem_error = (string) $r['message'];
				return;

			case 'undo':
				$r = self::undo( (int) ( $_POST['id'] ?? 0 ), get_current_user_id() );
				self::flash( ! empty( $r['ok'] ) ? 'success' : 'error', (string) $r['message'] );
				wp_safe_redirect( self::url( '' !== $phone ? array( 'phone' => self::local( $phone ) ) : array() ) );
				exit;
		}
	}

	/* --------------------------------------------------------------------- *
	 * The screen
	 * --------------------------------------------------------------------- */

	private static function nonce_fields( $action, $phone ) {
		wp_nonce_field( self::NONCE, '_aun_rw' );
		echo '<input type="hidden" name="aun_rw_action" value="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="phone" value="' . esc_attr( self::local( $phone ) ) . '">';
	}

	private static function styles() {
		?>
<style id="aun-rw-admin" data-no-optimize="1" data-no-minify="1">
.aun-rw{max-width:760px}
.aun-rw .card{max-width:none;padding:16px 18px;margin:14px 0;border-radius:8px}
.aun-rw h2{margin:0 0 10px;font-size:16px}
.aun-rw .aun-rw-row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.aun-rw input[type=text],.aun-rw input[type=tel],.aun-rw input[type=number]{font-size:16px;padding:6px 10px;min-height:40px;width:100%;max-width:280px}
.aun-rw .button{min-height:40px;line-height:38px;padding:0 16px}
.aun-rw .aun-rw-reward{display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-top:1px solid #eee}
.aun-rw .aun-rw-reward:first-of-type{border-top:0}
.aun-rw .aun-rw-reward input{margin-top:3px}
.aun-rw .aun-rw-muted{color:#646970}
.aun-rw .aun-rw-code{font-family:Consolas,Monaco,monospace;background:#f0f6fc;padding:1px 6px;border-radius:4px}
.aun-rw .aun-rw-big{font-size:28px;font-weight:700;color:#0188fe;margin:6px 0}
.aun-rw .aun-rw-ok{color:#008a20;font-weight:600}
.aun-rw .aun-rw-no{color:#8a2424}
.aun-rw table.widefat td,.aun-rw table.widefat th{vertical-align:top}
.aun-rw label.aun-rw-field{display:block;margin:10px 0 4px;font-weight:600}
@media (max-width:600px){.aun-rw .card{padding:14px}.aun-rw input[type=text],.aun-rw input[type=tel],.aun-rw input[type=number]{max-width:none}.aun-rw table.widefat thead{display:none}.aun-rw table.widefat td{display:block;border:0;padding:2px 8px}.aun-rw table.widefat td[data-label]::before{content:attr(data-label) ": ";font-weight:600;color:#50575e}.aun-rw table.widefat tr{display:block;border-bottom:1px solid #eee;padding:6px 0}}
</style>
		<?php
	}

	private static function reason_text( $why ) {
		$map = array(
			'returned'      => 'Returned',
			'dealer'        => 'Sold to a dealer — dealer sales do not earn',
			'not_projector' => 'Not a projector',
			'web_order'     => 'A website order\'s ERP record — it earns when the website order is delivered',
			'unmatched'     => 'Not matched to a website product — check its SKU in the ERP and on the website',
			'no_date'       => 'The ERP has no sale date for it',
			'before_launch' => 'Bought before Owner Rewards started',
			'too_old'       => 'Bought too long ago — its reward would already have ended',
			'off'           => 'Owner Rewards is switched off',
		);
		return $map[ $why ] ?? $why;
	}

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'You are not allowed to see this page.' );
		}
		// phpcs:disable WordPress.Security.NonceVerification -- read-only GET parameters; every POST is verified.
		$raw   = isset( $_GET['phone'] ) ? sanitize_text_field( wp_unslash( $_GET['phone'] ) ) : '';
		$phone = '' !== $raw ? AUN_App_Phone::normalize( $raw ) : '';
		$phone = $phone ? $phone : '';
		$done  = isset( $_GET['done'] ) ? (int) $_GET['done'] : 0;
		$ask   = ! empty( $_GET['code'] );
		// phpcs:enable

		self::styles();
		echo '<div class="wrap aun-rw"><h1>AUN Rewards — Showroom</h1>';
		foreach ( self::take_flash() as $f ) {
			if ( ! is_array( $f ) || ! isset( $f[0], $f[1] ) ) {
				continue;
			}
			echo '<div class="notice notice-' . esc_attr( 'error' === $f[0] ? 'error' : 'success' ) . '"><p>' . esc_html( $f[1] ) . '</p></div>';
		}
		if ( class_exists( 'AUN_App_Owner_Rewards' ) && ! AUN_App_Owner_Rewards::showroom_enabled() ) {
			echo '<div class="notice notice-warning"><p>The showroom counter is switched off (AUN App → Settings → AUN Rewards).</p></div></div>';
			return;
		}

		// ── find the customer ──
		echo '<div class="card"><h2>Customer\'s mobile number</h2>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="aun-rw-row">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
		echo '<input type="tel" name="phone" inputmode="tel" autocomplete="off" placeholder="01XXXXXXXXX" value="' . esc_attr( '' !== $phone ? self::local( $phone ) : $raw ) . '" required>';
		echo '<button class="button button-primary">Look up</button>';
		if ( '' !== $phone ) {
			echo ' <a class="button" href="' . esc_url( self::url() ) . '">Next customer</a>';
		}
		echo '</form>';
		if ( '' !== $raw && '' === $phone ) {
			echo '<p class="aun-rw-no">That is not a valid Bangladeshi mobile number.</p>';
		}
		echo '</div>';

		if ( '' === $phone ) {
			echo '<div class="card"><h2>How it works</h2><ol>'
				. '<li><strong>Look up</strong> the customer. Their projector purchases from the ERP are checked, and any owner reward they have earned is issued on the spot (they get an SMS).</li>'
				. '<li>To <strong>use</strong> rewards, send a code to their phone and ask them to read it out.</li>'
				. '<li>Tick the rewards, type the sale\'s items total → <strong>Calculate</strong>. Enter that discount in the ERP sale.</li>'
				. '<li>Type the ERP invoice number → <strong>Redeem</strong>. The rewards are marked used everywhere, including the website.</li>'
				. '</ol></div>';
			self::render_recent( '' );
			echo '</div>';
			return;
		}

		// ── the done receipt ──
		if ( $done ) {
			self::render_done( $done, $phone );
		}

		$erp = self::erp_purchases( $phone );
		$w   = self::wallet( $phone );

		$name = '';
		foreach ( $erp['sales'] as $s ) {
			if ( '' !== trim( (string) ( $s['contact_name'] ?? '' ) ) ) {
				$name = trim( (string) $s['contact_name'] );
				break;
			}
		}
		if ( '' === $name ) {
			$users = AUN_App_Phone::find_users( $phone );
			$name  = $users ? (string) $users[0]->display_name : '';
		}

		echo '<div class="card"><h2>' . esc_html( '' !== $name ? $name : 'Customer' ) . ' · ' . esc_html( self::local( $phone ) ) . '</h2>';
		if ( $erp['issued'] > 0 ) {
			echo '<p class="aun-rw-ok">✓ ' . esc_html( $erp['issued'] . ' new owner reward' . ( $erp['issued'] > 1 ? 's' : '' ) . ' issued from their purchases — the customer gets an SMS.' ) . '</p>';
		}
		if ( '' !== $erp['error'] ) {
			echo '<p class="aun-rw-no">' . esc_html( $erp['error'] ) . '</p>';
		}
		echo '<h3 style="margin:12px 0 6px">Purchases from AUN (ERP)</h3>';
		if ( empty( $erp['sales'] ) && '' === $erp['error'] ) {
			echo '<p class="aun-rw-muted">No purchases found for this number in the ERP. A projector sale only appears here when the ERP sale is under this mobile number AND has the serial number on its own line in the product note.</p>';
		} elseif ( ! empty( $erp['sales'] ) ) {
			echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Invoice</th><th>Product</th><th>Owner reward</th></tr></thead><tbody>';
			foreach ( $erp['sales'] as $s ) {
				$st = class_exists( 'AUN_App_Owner_Rewards' ) ? AUN_App_Owner_Rewards::sale_status( $s ) : array( 'state' => 'off', 'reason' => '', 'coupon' => '' );
				if ( 'earned' === $st['state'] ) {
					$cell = '<span class="aun-rw-ok">✓ Earned</span> <span class="aun-rw-code">' . esc_html( $st['coupon'] ) . '</span> <span class="aun-rw-muted">(' . esc_html( $st['reason'] ) . ')</span>';
				} elseif ( 'off' === $st['state'] ) {
					$cell = '<span class="aun-rw-muted">—</span>';
				} else {
					$cell = '<span class="aun-rw-muted">' . esc_html( self::reason_text( $st['reason'] ) ) . '</span>';
				}
				echo '<tr><td data-label="Date">' . esc_html( substr( (string) ( $s['sale_date'] ?? '' ), 0, 10 ) ) . '</td><td data-label="Invoice">' . esc_html( (string) ( $s['invoice_no'] ?? '' ) ) . '</td><td>'
					. esc_html( (string) ( $s['product_name'] ?? '' ) ) . ( ! empty( $s['returned'] ) ? ' <span class="aun-rw-no">(returned)</span>' : '' )
					. '</td><td data-label="Owner reward">' . $cell . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $cell escaped above.
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		// ── rewards ──
		echo '<div class="card"><h2>Rewards they can use</h2>';
		if ( self::wallet_is_empty( $w ) ) {
			echo '<p class="aun-rw-muted">No rewards to use right now.</p></div>';
			self::render_recent( $phone );
			echo '</div>';
			return;
		}
		$verified = self::is_verified( $phone );
		if ( ! $verified ) {
			self::render_wallet_list( $w );
			echo '<hr><p><strong>To use them</strong>, send a code to the customer\'s phone and ask them to read it out.</p>';
			echo '<form method="post" class="aun-rw-row">';
			self::nonce_fields( 'send_otp', $phone );
			echo '<button class="button' . ( $ask ? '' : ' button-primary' ) . '">' . ( $ask ? 'Send the code again' : 'Send code to ' . esc_html( AUN_App_Phone::mask( $phone ) ) ) . '</button></form>';
			if ( $ask ) {
				echo '<form method="post" class="aun-rw-row" style="margin-top:12px">';
				self::nonce_fields( 'verify', $phone );
				echo '<input type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" placeholder="Code" required style="max-width:160px">';
				echo '<button class="button button-primary">Confirm</button></form>';
			}
			echo '</div>';
			self::render_recent( $phone );
			echo '</div>';
			return;
		}

		// ── verified: pick, calculate, redeem ──
		// phpcs:disable WordPress.Security.NonceVerification -- checked just below.
		$posting = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! empty( $_POST['aun_rw_action'] )
			&& in_array( $_POST['aun_rw_action'], array( 'calculate', 'redeem' ), true );
		// phpcs:enable
		if ( $posting ) {
			check_admin_referer( self::NONCE, '_aun_rw' );
		}
		$in = $posting ? self::posted_quote_input() : array(
			'picked'          => self::default_picks( $w ),
			'items_total'     => 0,
			'projector_price' => 0,
			'full_price'      => false,
		);

		echo '<p class="aun-rw-ok">✓ Customer confirmed with their code.</p>';
		echo '<form method="post">';
		self::nonce_fields( 'calculate', $phone );
		self::render_wallet_list( $w, $in['picked'] );
		echo '<label class="aun-rw-field" for="aun-rw-items">Items total of this sale (before any discount)</label>';
		echo '<input id="aun-rw-items" type="text" inputmode="decimal" name="items_total" value="' . esc_attr( $in['items_total'] > 0 ? self::num( $in['items_total'] ) : '' ) . '" placeholder="e.g. 45000" required>';
		if ( $w['owner'] ) {
			echo '<label class="aun-rw-field" for="aun-rw-proj">Price of the projector the owner reward is for</label>';
			echo '<input id="aun-rw-proj" type="text" inputmode="decimal" name="projector_price" value="' . esc_attr( $in['projector_price'] > 0 ? self::num( $in['projector_price'] ) : '' ) . '" placeholder="e.g. 40000">';
			echo '<p><label><input type="checkbox" name="full_price" value="1"' . checked( $in['full_price'], true, false ) . '> This projector is at its <strong>full price</strong> — not on a campaign or sale price.</label></p>';
		}
		echo '<p><button class="button button-primary">Calculate</button></p></form>';

		if ( $posting ) {
			$q = self::quote( $phone, $in );
			self::render_quote( $q, $phone, $in );
		}
		echo '</div>';
		self::render_recent( $phone );
		echo '</div>';
	}

	/** Everything ticked, except a welcome discount alongside other rewards. */
	private static function default_picks( $w ) {
		$out = array();
		if ( $w['owner'] ) {
			$out[] = $w['owner']['code'];
		}
		foreach ( $w['invite'] as $i ) {
			$out[] = $i['code'];
		}
		if ( empty( $out ) && $w['welcome'] ) {
			$out[] = $w['welcome']['code'];
		}
		return $out;
	}

	private static function render_wallet_list( $w, $picked = null ) {
		$box = function ( $code ) use ( $picked ) {
			if ( null === $picked ) {
				return '';
			}
			return '<input type="checkbox" name="picked[]" value="' . esc_attr( $code ) . '"' . checked( in_array( $code, (array) $picked, true ), true, false ) . '>';
		};
		$row = function ( $code, $title, $sub ) use ( $box ) {
			echo '<label class="aun-rw-reward">' . $box( $code ) . '<span><strong>' . esc_html( $title ) . '</strong><br><span class="aun-rw-muted">' // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
				. esc_html( $sub ) . ' · <span class="aun-rw-code">' . esc_html( $code ) . '</span></span></span></label>';
		};
		if ( $w['owner'] ) {
			$row( $w['owner']['code'], 'Owner reward — ' . self::num( $w['owner']['percent'] ) . '% off one full-price projector', 'Ends ' . self::date( $w['owner']['expires'] ) );
		}
		foreach ( $w['invite'] as $i ) {
			$row( $i['code'], 'Invite reward — ' . self::money( $i['amount'] ), ( $i['expires'] ? 'Ends ' . self::date( $i['expires'] ) : 'Never ends' ) . ( $i['min'] > 0 ? ' · on a sale of ' . self::money( $i['min'] ) . '+' : '' ) );
		}
		if ( $w['welcome'] ) {
			$v = 'percent' === $w['welcome']['type'] ? self::num( $w['welcome']['amount'] ) . '% off' : self::money( $w['welcome']['amount'] ) . ' off';
			$row( $w['welcome']['code'], 'Welcome discount — ' . $v . ' their first purchase (used on its own)', 'Ends ' . self::date( $w['welcome']['expires'] ) );
		}
	}

	private static function render_quote( $q, $phone, $in ) {
		echo '<hr>';
		if ( '' !== self::$redeem_error ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( self::$redeem_error ) . '</p></div>';
		}
		if ( ! $q['ok'] ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $q['error'] ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Reward</th><th>Used now</th><th>Stays in their Rewards</th></tr></thead><tbody>';
		foreach ( $q['lines'] as $l ) {
			echo '<tr><td>' . esc_html( $l['label'] ) . '<br><span class="aun-rw-code">' . esc_html( $l['code'] ) . '</span>'
				. ( '' !== (string) ( $l['note'] ?? '' ) ? '<br><span class="aun-rw-muted">' . esc_html( $l['note'] ) . '</span>' : '' )
				. '</td><td data-label="Used now">' . esc_html( self::money( $l['use'] ) ) . '</td><td data-label="Stays in their Rewards">' . esc_html( $l['keep'] > 0 ? self::money( $l['keep'] ) : '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p style="margin-top:14px">Enter this as the discount in the ERP sale:</p>';
		echo '<div class="aun-rw-big">' . esc_html( self::money( $q['discount'] ) ) . '</div>';
		echo '<p class="aun-rw-muted">Items ' . esc_html( self::money( $q['items_total'] ) ) . ' → the customer pays <strong>' . esc_html( self::money( $q['pay'] ) ) . '</strong>.</p>';

		echo '<form method="post" onsubmit="return confirm(\'Mark these rewards as used on this invoice?\');">';
		self::nonce_fields( 'redeem', $phone );
		foreach ( (array) $in['picked'] as $p ) {
			echo '<input type="hidden" name="picked[]" value="' . esc_attr( $p ) . '">';
		}
		echo '<input type="hidden" name="items_total" value="' . esc_attr( self::num( $in['items_total'] ) ) . '">';
		echo '<input type="hidden" name="projector_price" value="' . esc_attr( self::num( $in['projector_price'] ) ) . '">';
		if ( $in['full_price'] ) {
			echo '<input type="hidden" name="full_price" value="1">';
		}
		echo '<input type="hidden" name="fingerprint" value="' . esc_attr( $q['fingerprint'] ) . '">';
		echo '<label class="aun-rw-field" for="aun-rw-inv">ERP invoice number of this sale</label>';
		echo '<div class="aun-rw-row"><input id="aun-rw-inv" type="text" name="invoice" autocomplete="off" required placeholder="e.g. INV-2026-0123" value="' // phpcs:ignore WordPress.Security.NonceVerification
			. esc_attr( isset( $_POST['invoice'] ) ? self::clean_invoice( wp_unslash( $_POST['invoice'] ) ) : '' ) . '">'; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<button class="button button-primary">Redeem</button></div></form>';
	}

	private static function render_done( $id, $phone ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d AND phone = %s', (int) $id, $phone ) );
		if ( ! $row ) {
			return;
		}
		$data = json_decode( (string) $row->detail, true );
		echo '<div class="card" style="border-left:4px solid #00a32a"><h2>✓ Redeemed on invoice ' . esc_html( $row->invoice ) . '</h2>';
		echo '<div class="aun-rw-big">' . esc_html( self::money( $row->discount ) ) . ' off</div>';
		echo '<p>Make sure the ERP sale shows this discount. The rewards are now marked used on the website too.</p>';
		// The inviter of a friend's welcome discount, and the reward the new
		// projector earns, both depend on the ERP finding this invoice under
		// this number, with the serial in the line note. Say so NOW, while the
		// ERP sale is still open on the other screen.
		if ( class_exists( 'AUN_App_ERP' ) && AUN_App_ERP::configured() ) {
			$sales = AUN_App_ERP::lookup_phone( $phone );
			if ( is_array( $sales ) ) {
				$found = false;
				foreach ( $sales as $s ) {
					if ( strtoupper( trim( (string) ( $s['invoice_no'] ?? '' ) ) ) === strtoupper( (string) $row->invoice ) ) {
						$found = true;
					}
				}
				if ( ! $found ) {
					echo '<p class="aun-rw-no"><strong>Invoice ' . esc_html( $row->invoice ) . ' is not in the ERP under this number yet.</strong> '
						. 'Check the ERP sale is saved under ' . esc_html( self::local( $phone ) ) . ' with the projector\'s serial on its own line in the product note — '
						. 'until it is, the new projector earns no reward and a friend\'s inviter is not paid.</p>';
				}
			}
		}
		echo '<ul>';
		foreach ( (array) ( $data['lines'] ?? array() ) as $l ) {
			if ( (float) $l['use'] <= 0 ) {
				continue;
			}
			echo '<li>' . esc_html( $l['code'] . ': ' . self::money( $l['use'] ) . ' used' . ( ! empty( $l['new_code'] ) ? ' — the other ' . self::money( $l['keep'] ) . ' is now ' . $l['new_code'] : '' ) ) . '</li>';
		}
		echo '</ul></div>';
	}

	private static function render_recent( $phone ) {
		$rows = self::recent( $phone );
		if ( empty( $rows ) ) {
			return;
		}
		echo '<div class="card"><h2>' . ( '' !== $phone ? 'This customer\'s showroom redemptions' : 'Recent showroom redemptions' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>When</th>' . ( '' === $phone ? '<th>Customer</th>' : '' ) . '<th>Invoice</th><th>Discount</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td data-label="When">' . esc_html( substr( (string) $r->created_at, 0, 16 ) ) . '</td>';
			if ( '' === $phone ) {
				echo '<td data-label="Customer"><a href="' . esc_url( self::url( array( 'phone' => self::local( $r->phone ) ) ) ) . '">' . esc_html( self::local( $r->phone ) ) . '</a></td>';
			}
			echo '<td data-label="Invoice">' . esc_html( $r->invoice ) . '</td><td data-label="Discount">' . esc_html( self::money( $r->discount ) ) . '</td><td>';
			if ( 'done' === $r->status ) {
				echo '<form method="post" onsubmit="return confirm(\'Undo this redemption? The rewards go back to the customer. Remove the discount from the ERP sale too.\');">';
				self::nonce_fields( 'undo', '' !== $phone ? $phone : (string) $r->phone );
				echo '<input type="hidden" name="id" value="' . (int) $r->id . '"><button class="button button-small">Undo</button></form>';
			} else {
				echo '<span class="aun-rw-muted">Undone ' . esc_html( substr( (string) $r->undone_at, 0, 10 ) ) . '</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
