<?php
/**
 * Admin request management: the list + the per-request detail with per-part status,
 * factory PO/ETA, and the rejection flow (with reason templates + a hard-to-source
 * age flag). This is where Jobayer actually works each request.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Requests {

	/**
	 * States that END a request. 'declined' was missing from the old ad-hoc
	 * NOT IN ('closed','rejected') filters, so a declined quote counted as "open"
	 * forever — it kept the daily digest firing and sat in the Open list for good.
	 */
	/**
	 * 'expired' is terminal in the same sense as 'declined' — it leaves your active
	 * list and stops the digest — but it is NOT the same thing and must never be
	 * recorded as one: declined means the customer said no, expired means they never
	 * answered. The customer can revive an expired quote in one tap (ajax_revive).
	 */
	const TERMINAL_STATES = array( 'closed', 'rejected', 'declined', 'expired' );

	/**
	 * States where the deal is OFF: no money may be taken, no payment link offered,
	 * and any money already taken is owed back.
	 *
	 * This exists as one constant because it kept being written out by hand — and
	 * every time a new state joined the family it was missed somewhere. 'expired'
	 * was absent from three of these lists (the pay endpoint, the admin's "send
	 * payment link" button, and the refund-due check), which meant a customer
	 * holding an old pay link could still pay a lapsed price. Add a state here, not
	 * to an array literal somewhere.
	 */
	const CANCELLED_STATES = array( 'rejected', 'declined', 'expired' );

	/** True when no payment should be possible on this request. */
	public static function is_cancelled( $status ) {
		return in_array( (string) $status, self::CANCELLED_STATES, true );
	}

	/**
	 * Part statuses that must NOT be charged for.
	 *
	 * A part we cannot supply ('unavailable') or that the customer isn't taking
	 * ('cancelled') has to drop out of the amount owed — otherwise a request where
	 * one of three parts turned out to be unavailable still bills for all three.
	 */
	const NOT_CHARGEABLE = array( 'unavailable', 'cancelled' );

	/**
	 * Part statuses meaning the goods have already left us.
	 *
	 * Once a part is with the courier or in the customer's hands, "Pay online" must
	 * disappear: on a cash-on-delivery request the courier is collecting the money,
	 * so paying online too is a double payment; on a delivered one there is nothing
	 * left to pay for online.
	 */
	const HANDED_OVER = array( 'dispatched', 'delivered' );

	public static function is_chargeable_line( $line_status ) {
		return ! in_array( (string) $line_status, self::NOT_CHARGEABLE, true );
	}

	/**
	 * Reduce whatever was typed in the courier field to a bare consignment ID.
	 *
	 * The field invites a pasted link, but only ONE link shape used to survive:
	 * `?consignment_id=XXX`. Anything else was run through "strip everything that
	 * isn't a letter or digit", so `…/public-tracking/DA240626FDJC6N` was stored as
	 * `httpsmerchantpathaocompublictrackingDA240626FDJC6N`, and a note to self like
	 * "i will add later" became `iwilladdlater`. Both were saved without a murmur and
	 * then shown to the CUSTOMER as a live tracking chip that opens a Pathao page
	 * finding nothing.
	 *
	 * So: pull the ID out of a query parameter or the last path segment, and if what
	 * is left doesn't look like a consignment ID, return '' rather than a plausible-
	 * looking mess. '' means "reject it and tell the admin", never "store garbage".
	 *
	 * @return string the ID, or '' if this isn't one.
	 */
	public static function clean_consignment( $raw ) {
		$s = trim( (string) $raw );
		if ( '' === $s ) {
			return '';
		}
		// Any URL carrying ?consignment_id= / &consignment_id=
		if ( preg_match( '/consignment_id=([A-Za-z0-9]+)/i', $s, $m ) ) {
			return $m[1];
		}
		// A link with the ID as the last path segment (…/public-tracking/DA24…).
		if ( preg_match( '~^https?://~i', $s ) ) {
			$path = (string) wp_parse_url( $s, PHP_URL_PATH );
			$last = basename( rtrim( $path, '/' ) );
			return self::looks_like_consignment( $last ) ? $last : '';
		}
		// Typed by hand — tolerate spaces and dashes inside the ID.
		$s = preg_replace( '/[^A-Za-z0-9]/', '', $s );
		return self::looks_like_consignment( $s ) ? $s : '';
	}

	/**
	 * Is this string plausibly a consignment number?
	 *
	 * Length alone is not enough: a note to self like "i will add later" collapses to
	 * `iwilladdlater`, which is a perfectly respectable 13 characters and would sail
	 * through — then hand the customer a tracking chip that finds nothing. A real
	 * consignment number always carries digits (Pathao's look like DA240626FDJC6N:
	 * a prefix, the date, then a random tail), so requiring at least one digit is
	 * what separates an ID from a sentence.
	 *
	 * Filterable, in case a courier ever issues something shaped differently.
	 */
	private static function looks_like_consignment( $s ) {
		$pattern = (string) apply_filters( 'aun_sp_consignment_pattern', '/^(?=.*\d)[A-Za-z0-9]{8,32}$/' );
		return (bool) preg_match( $pattern, (string) $s );
	}

	/**
	 * The public Pathao tracking URL for a stored value — or '' when it cannot make
	 * a working link.
	 *
	 * Used by BOTH the admin badge and the customer's tracker, so neither can render
	 * a link the other wouldn't. It also copes with a value written by something
	 * other than the admin form (the app, an import, a direct DB edit): a full URL is
	 * passed through as-is instead of being pushed into `?consignment_id=`.
	 */
	public static function courier_url( $stored ) {
		$s = trim( (string) $stored );
		if ( '' === $s ) {
			return '';
		}
		if ( preg_match( '~^https?://~i', $s ) ) {
			return esc_url_raw( $s ); // already a link — don't wrap it in another one
		}
		$id = self::clean_consignment( $s );
		return '' === $id ? '' : 'https://merchant.pathao.com/public-tracking?consignment_id=' . rawurlencode( $id );
	}

	/**
	 * Event types the CUSTOMER may see in "Progress history".
	 *
	 * This is an allow-list on purpose. It used to be a deny-list, and the result was
	 * exactly what a deny-list always produces: every event type added later leaked
	 * by default. Customers ended up reading our own bookkeeping —
	 * "Online payment order #9275 refreshed — now ৳16.00" — which exposes shop order
	 * numbers and reads as if the price keeps changing.
	 *
	 * Anything not listed here is internal and stays on the admin's Activity log:
	 * sms, wc_order, contact_changed, quote_reminder, duplicate_confirmed, refund_due.
	 */
	const PUBLIC_EVENTS = array(
		'created', 'status_change', 'qty_change', 'quote_sent', 'approved', 'declined',
		'quote_expired', 'quote_revived', 'rejected', 'photo_request', 'reupload',
		'payment', 'refund',
	);

	/**
	 * Can this request still be paid for ONLINE, and how much is owed?
	 *
	 * The single rule behind both the customer's "Pay online" button and the endpoint
	 * that mints the order — they must agree, or a stale page (or a crafted POST) can
	 * do what the UI says is impossible.
	 *
	 * @return array{money:float,open:int,can_pay:bool}
	 */
	public static function payable_state( $id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT line_status, qty, unit_price FROM ' . AUN_SP_Install::table( 'request_items' ) . ' WHERE request_id = %d',
			(int) $id
		) );
		$status = (string) $wpdb->get_var( $wpdb->prepare(
			'SELECT overall_status FROM ' . AUN_SP_Install::table( 'requests' ) . ' WHERE id = %d',
			(int) $id
		) );

		$money = 0.0;
		$open  = 0;
		foreach ( (array) $rows as $r ) {
			if ( ! self::is_chargeable_line( $r->line_status ) ) {
				continue;
			}
			$unit  = (float) $r->unit_price;
			$money += $unit * max( 1, (int) $r->qty );
			if ( $unit > 0 && ! in_array( $r->line_status, self::HANDED_OVER, true ) ) {
				$open++;
			}
		}

		// ⚠️ THE PAID CHECK, which this used to lack entirely.
		//
		// `$open > 0` was standing in for "they have not paid yet", inferred from
		// no priced part having been dispatched or delivered. That inference is
		// wrong in both directions:
		//
		//   - Paying ONLINE normally happens BEFORE dispatch, so straight after a
		//     successful payment every line is still open and this returned true —
		//     the Pay button stayed on screen and only a late `already_paid` check
		//     stopped a second charge.
		//   - Move a delivered line back to dispatched (or any admin correction
		//     that reopens a line) and the button returns on a settled request.
		//
		// Whether the money arrived is a fact about the ORDER, so ask the order.
		$settled = false;
		if ( class_exists( 'AUN_SP_Woo' ) && AUN_SP_Woo::is_active() ) {
			$settled = AUN_SP_Woo::has_been_paid( AUN_SP_Woo::order_for( (int) $id ) );
		}

		return array(
			'money'   => round( $money, 2 ),
			'open'    => $open,
			'paid'    => $settled,
			'can_pay' => ( $money > 0 && $open > 0 && ! $settled
				&& ! self::is_cancelled( $status ) && 'closed' !== $status ),
		);
	}

	/** SQL fragment matching open (non-terminal) requests. $col e.g. 'r.overall_status'. */
	private static function open_sql( $col = 'overall_status' ) {
		return $col . " NOT IN ('" . implode( "','", self::TERMINAL_STATES ) . "')";
	}

	/**
	 * Counts for the admin-bar badge + stat cards, in one grouped query.
	 *   action  = needs YOUR move now (new to review + approved-to-order + ready-to-dispatch)
	 *   open    = every non-terminal request
	 * Cheap enough to run un-cached on each admin-bar render for a solo-operator site.
	 */
	public static function nav_counts() {
		global $wpdb;
		$t   = AUN_SP_Install::table( 'requests' );
		$raw = array();
		foreach ( (array) $wpdb->get_results( "SELECT overall_status s, COUNT(*) n FROM $t GROUP BY overall_status" ) as $row ) {
			$raw[ $row->s ] = (int) $row->n;
		}
		$g    = function ( $k ) use ( $raw ) { return isset( $raw[ $k ] ) ? $raw[ $k ] : 0; };
		$open = 0;
		foreach ( $raw as $k => $n ) {
			if ( ! in_array( $k, self::TERMINAL_STATES, true ) ) {
				$open += $n;
			}
		}
		return array(
			'open'      => $open,
			'submitted' => $g( 'submitted' ),
			'approved'  => $g( 'approved' ),
			'ready'     => $g( 'ready' ),
			'waiting'   => $g( 'waiting_customer' ),
			'quote'     => $g( 'quote_sent' ),
			'action'    => $g( 'submitted' ) + $g( 'approved' ) + $g( 'ready' ),
		);
	}

	/**
	 * The working labels for YOUR dropdown. They spell out where the part physically
	 * is, because "Shipped" and "Dispatched" read identically otherwise — the first
	 * is the factory sending it to Bangladesh, the second is us sending it to the
	 * customer. What the CUSTOMER is shown for the same keys is separate and
	 * translatable: AUN_SP_I18N::it_label() / it_help().
	 */
	public static function item_statuses() {
		return array(
			'pending'     => 'Pending review',
			'quoted'      => 'Quoted (price sent)',
			'applied'     => 'Ordered from factory',
			'at_factory'  => 'At factory — being prepared',
			'shipped'     => 'Shipped by factory → to Bangladesh',
			'arrived'     => 'Arrived at AUN (Dhaka)',
			'dispatched'  => 'Dispatched to customer (courier)',
			'delivered'   => 'Delivered to customer',
			'unavailable' => 'Unavailable (we cannot supply it)',
			'cancelled'   => 'Not going ahead (declined / expired)',
		);
	}

	public static function overall_statuses() {
		return array(
			'submitted'        => 'Submitted',
			'in_progress'      => 'In progress',
			'quote_sent'       => 'Quote sent — awaiting approval',
			'approved'         => 'Approved — sourcing parts',
			'waiting_customer' => 'Waiting on customer',
			'ready'            => 'Ready to dispatch',
			'closed'           => 'Completed',
			'declined'         => 'Quote declined',
			'expired'          => 'Quote expired — no reply',
			'rejected'         => 'Rejected',
		);
	}

	/**
	 * Why a photo is being sent back, admin-side.
	 *
	 * A generic "please send a clearer photo" only ever fixes ONE of the two things
	 * that go wrong. If the photo is blurry, asking for a clearer one works. If the
	 * customer photographed the WRONG part, asking for a clearer one gets you a
	 * beautifully sharp photo of the same wrong part — so the reason has to travel
	 * with the request, all the way to the customer's screen and their SMS.
	 *
	 * The keys are stored in requests.photo_reason; the customer-facing wording lives
	 * in the translations catalogue (pr_* = what is wrong, prh_* = what to do about
	 * it) so both languages are editable without touching code.
	 *
	 * @return array key => short admin label for the dropdown
	 */
	public static function photo_reasons() {
		return array(
			'wrong_part' => 'Wrong part photographed',
			'model_mismatch' => "Right part, but doesn't match this model",
			'blurry'     => 'Too blurry to read',
			'dark'       => 'Too dark / glare on the label',
			'serial'     => 'Serial or model number not readable',
			'cropped'    => 'Part is cut off at the edge',
			'unclear'    => 'Cannot tell what the photo shows',
			'other'      => 'Something else (write it below)',
		);
	}

	/** The customer-facing pair for a reason key: what is wrong + what to do. */
	public static function photo_reason_text( $key, $lang = null ) {
		$key = (string) $key;
		if ( '' === $key || ! isset( self::photo_reasons()[ $key ] ) ) {
			return array( 'what' => '', 'how' => '', 'sms' => '' );
		}
		return array(
			'what' => AUN_SP_I18N::msg( 'pr_' . $key, array(), $lang ),
			'how'  => AUN_SP_I18N::msg( 'prh_' . $key, array(), $lang ),
			// The SMS form. The tracking page can afford a full sentence; an SMS
			// cannot — one character past 160 splits it in two and doubles the cost.
			'sms'  => AUN_SP_I18N::msg( 'prs_' . $key, array(), $lang ),
		);
	}

	/** How long a quote stays valid, in days. 0 = never expires (reminders only). */
	public static function quote_valid_days() {
		return max( 0, (int) get_option( 'aun_sp_quote_valid_days', 7 ) );
	}

	/**
	 * When the two reminders go out, in days after the quote was sent.
	 *
	 * Derived from the validity period rather than configured separately, so the
	 * ladder always fits inside the window: one nudge around the middle, one final
	 * one the day before it lapses. Three touches total (quote + 2) is the point at
	 * which chasing stops working and starts annoying — and each one costs money.
	 */
	public static function reminder_days() {
		$days = self::quote_valid_days();
		if ( $days < 1 ) {
			return array( 3, 7 ); // no expiry: a fixed, still-finite ladder
		}
		if ( $days < 4 ) {
			return array( 1, max( 2, $days - 1 ) );
		}
		$first  = max( 1, (int) round( $days * 0.45 ) );
		$second = max( $first + 1, $days - 1 );
		return array( $first, $second );
	}

	/** Entry point from the menu callback. Routes list vs detail and handles POST. */
	public function screen() {
		$id = isset( $_GET['request'] ) ? (int) $_GET['request'] : 0;

		$notice = '';
		if ( $id && ! empty( $_POST ) ) {
			$notice = $this->handle_post( $id );
		}

		echo '<div class="wrap">';
		if ( $id ) {
			$this->detail( $id, $notice );
		} else {
			$this->list_view();
		}
		echo '</div>';
	}

	/* ----------------------------------------------------------------------- List */

	private function list_view() {
		global $wpdb;
		$t_req  = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );

		$filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'open';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$where = '1=1';
		if ( 'open' === $filter ) {
			$where = self::open_sql( 'r.overall_status' );
		} elseif ( array_key_exists( $filter, self::overall_statuses() ) ) {
			$where = $wpdb->prepare( 'r.overall_status = %s', $filter );
		}
		if ( $search !== '' ) {
			// Search matches ref, name, source order number, or the phone in ANY stored
			// format (web = 01XXXXXXXXX, Android app = 8801XXXXXXXXX).
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$parts = array();
			$parts[] = $wpdb->prepare( 'r.ref LIKE %s', $like );
			$parts[] = $wpdb->prepare( 'r.customer_name LIKE %s', $like );
			$parts[] = $wpdb->prepare( 'r.source_order LIKE %s', $like );
			$digits  = preg_replace( '/\D+/', '', $search );
			if ( strlen( $digits ) >= 9 ) {
				$parts[] = aun_sp_phone_where( 'r.phone_current', $search );
			} else {
				$parts[] = $wpdb->prepare( 'r.phone_current LIKE %s', $like );
			}
			$where .= ' AND (' . implode( ' OR ', $parts ) . ')';
		}

		// Self-heal BEFORE filtering. The stored overall_status is only recomputed when
		// a request is opened and saved, so a row's badge could lag its actual parts.
		// Healing only the rows the filter already returned was too late: a request
		// whose stored status was stale was matched (or missed) by the WHERE clause on
		// the stale value, so it could sit in the wrong tab and be counted in the wrong
		// stat card — which reads exactly like "the dashboard is out of sync".
		$this->sync_rows( $wpdb->get_results(
			"SELECT id, overall_status, approved_at FROM $t_req WHERE " . self::open_sql() . ' LIMIT 500'
		) );

		$rows = $wpdb->get_results(
			"SELECT r.* FROM $t_req r WHERE $where ORDER BY r.created_at DESC LIMIT 200"
		);
		// Per-part detail for the Progress column. (This replaces a GROUP_CONCAT(…
		// SEPARATOR …) subquery, which also happened to be the one query the SQLite
		// test bench cannot parse.)
		$parts_by = $this->parts_for( wp_list_pluck( (array) $rows, 'id' ) );

		// Stat cards.
		$open    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_req WHERE " . self::open_sql() );
		$waiting = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_req WHERE overall_status = %s", 'waiting_customer' ) );
		$ready   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t_req WHERE overall_status = %s", 'ready' ) );
		$factory = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT request_id) FROM $t_item WHERE line_status IN ('applied','at_factory','shipped')" );

		echo '<h1>Spare parts requests</h1>';
		echo '<div style="display:flex;gap:14px;flex-wrap:wrap;margin:14px 0 18px;">';
		$this->card( 'Open', $open );
		$this->card( 'Waiting on customer', $waiting );
		$this->card( 'At factory', $factory );
		$this->card( 'Ready to dispatch', $ready );
		echo '</div>';

		// Filter tabs.
		// Every status a request can be in is reachable from here. "Completed" was
		// missing entirely, so finished work could only be found via All.
		$tabs = array(
			'open'             => 'Open',
			'submitted'        => 'New',
			'quote_sent'       => 'Awaiting reply',
			'approved'         => 'Approved',
			'in_progress'      => 'In progress',
			'ready'            => 'Ready',
			'waiting_customer' => 'Waiting on customer',
			'closed'           => 'Completed',
			'expired'          => 'Expired',
			'declined'         => 'Declined',
			'rejected'         => 'Rejected',
			'all'              => 'All',
		);
		// Counts on the tabs, so you can see where the work is without clicking.
		$tab_counts = array();
		foreach ( (array) $wpdb->get_results( "SELECT overall_status s, COUNT(*) n FROM $t_req GROUP BY overall_status" ) as $c ) {
			$tab_counts[ $c->s ] = (int) $c->n;
		}
		$tab_counts['all']  = array_sum( $tab_counts );
		$tab_counts['open'] = $open;
		echo '<ul class="subsubsub">';
		$i = 0;
		foreach ( $tabs as $key => $label ) {
			$url = admin_url( 'admin.php?page=aun-sp&status=' . $key );
			$cur = $filter === $key ? ' class="current"' : '';
			$n   = isset( $tab_counts[ $key ] ) ? (int) $tab_counts[ $key ] : 0;
			echo ( $i++ ? ' | ' : '' ) . '<li><a href="' . esc_url( $url ) . '"' . $cur . '>' . esc_html( $label )
				. ' <span class="count">(' . (int) $n . ')</span></a></li>';
		}
		echo '</ul>';

		// Quick search (ref / phone / name / order number).
		echo '<form method="get" style="float:right;margin:-2px 0 8px;">';
		echo '<input type="hidden" name="page" value="aun-sp">';
		echo '<input type="hidden" name="status" value="' . esc_attr( $filter ) . '">';
		echo '<p class="search-box" style="margin:0;">';
		echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="SP-…, phone or name"> ';
		echo '<button class="button">Search</button>';
		if ( $search !== '' ) {
			echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=aun-sp&status=' . $filter ) ) . '">Clear</a>';
		}
		echo '</p></form>';

		echo '<table class="wp-list-table widefat striped"><thead><tr>';
		echo '<th style="width:120px;">Ref</th><th>Customer</th><th style="width:120px;">Phone</th>'
			. '<th style="width:38%;">Parts &amp; where they are</th><th style="width:90px;">Warranty</th>'
			. '<th style="width:150px;">Request status</th><th style="width:60px;">Age</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="7">No requests in this view.</td></tr>';
		}
		foreach ( (array) $rows as $r ) {
			$url   = admin_url( 'admin.php?page=aun-sp&request=' . (int) $r->id );
			$age   = $this->age_days( $r->created_at );
			$wlbl  = $r->warranty_in ? 'In warranty' : 'Out';
			echo '<tr>';
			echo '<td><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $r->ref ) . '</strong></a></td>';
			echo '<td>' . esc_html( $r->customer_name ) . '</td>';
			echo '<td>' . esc_html( $r->phone_current ) . '</td>';
			echo '<td>' . $this->parts_progress( $parts_by[ (int) $r->id ] ?? array() ) . '</td>';
			echo '<td>' . esc_html( $wlbl ) . '</td>';
			echo '<td>' . $this->status_badge( $r->overall_status );
			// An unanswered quote is the one state where the WAIT is the information:
			// how long the customer has been sitting on it, and when it lapses.
			if ( 'quote_sent' === $r->overall_status && ! empty( $r->quoted_at ) ) {
				$waited = $this->age_days( $r->quoted_at );
				$left   = ! empty( $r->quote_expires_at )
					? (int) ceil( ( strtotime( (string) $r->quote_expires_at ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS )
					: null;
				$col    = ( null !== $left && $left <= 1 ) ? '#b32d2e' : '#646970';
				echo '<div style="font-size:11px;color:' . esc_attr( $col ) . ';margin-top:3px;">waiting ' . (int) $waited . 'd'
					. ( null !== $left ? ( $left > 0 ? ' · expires in ' . $left . 'd' : ' · expiring' ) : '' )
					. ( (int) $r->quote_reminders > 0 ? ' · ' . (int) $r->quote_reminders . ' reminder(s) sent' : '' )
					. '</div>';
			}
			echo '</td>';
			echo '<td>' . esc_html( $age ) . 'd</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p style="color:#646970;max-width:900px;margin-top:14px;">The <strong>Request status</strong> column is the whole request in one word &mdash; every stage between ordering and arrival reads as &ldquo;In progress&rdquo;, by design. <strong>Parts &amp; where they are</strong> is the detail: each part with the exact stage it has reached. A <strong>solid</strong> status pill means the next move is yours.</p>';

		// Legend: what each colour means, and what the two easily-missed part statuses
		// are for. Cheap to render, and it stops "what does 'Not going ahead' mean?".
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 16px;max-width:900px;margin-top:10px;">';
		echo '<strong style="display:block;margin-bottom:8px;">Colour key</strong>';
		echo '<div style="display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;">';
		foreach ( array_keys( self::overall_statuses() ) as $k ) {
			echo $this->status_badge( $k );
		}
		echo '</div>';
		echo '<p style="color:#646970;margin:10px 0 0;"><strong>Unavailable</strong> = we cannot supply that part (it is not charged for). '
			. '<strong>Not going ahead</strong> = the part was priced but the customer declined the quote, or the quote expired with no reply &mdash; also not charged for.</p>';
		echo '</div>';
	}

	/** part rows for a set of request ids, keyed by request id (one query). */
	private function parts_for( $ids ) {
		global $wpdb;
		$ids = array_map( 'intval', (array) $ids );
		$out = array();
		if ( empty( $ids ) ) {
			return $out;
		}
		$t_item = AUN_SP_Install::table( 'request_items' );
		$rows   = $wpdb->get_results(
			"SELECT request_id, part_label, line_status, qty FROM $t_item WHERE request_id IN (" . implode( ',', $ids ) . ') ORDER BY id ASC'
		);
		foreach ( (array) $rows as $p ) {
			$out[ (int) $p->request_id ][] = $p;
		}
		return $out;
	}

	/**
	 * The list's Progress cell: every part with the stage it has actually reached.
	 *
	 * The overall status alone cannot answer "which of these is still at the factory
	 * and which is already on its way to the customer?" — applied / at factory /
	 * shipped / arrived all collapse into "In progress", so a whole dashboard of
	 * genuinely different requests looked identical.
	 */
	private function parts_progress( $parts ) {
		if ( empty( $parts ) ) {
			return '<span style="color:#646970;">—</span>';
		}
		// One colour per stage — applied/at_factory and arrived/dispatched/delivered
		// used to share one, so a glance down the column couldn't tell "ordered" from
		// "being made", or "in our office" from "already with the customer".
		$colors = array(
			'pending'     => '#646970', // grey    — not started
			'quoted'      => '#8250df', // purple  — with the customer
			'applied'     => '#bf6a02', // orange  — ordered
			'at_factory'  => '#8a6d3b', // brown   — being made
			'shipped'     => '#2271b1', // blue    — in transit to BD
			'arrived'     => '#00838f', // teal    — at AUN
			'dispatched'  => '#b02a8f', // magenta — with the courier
			'delivered'   => '#1a7f37', // green   — done
			'unavailable' => '#b32d2e', // red     — can't supply
			'cancelled'   => '#8c8f94', // pale    — not going ahead
		);
		$labels = self::item_statuses();
		$out    = '';
		foreach ( (array) $parts as $p ) {
			$qty = max( 1, (int) ( $p->qty ?? 1 ) );
			$c   = $colors[ $p->line_status ] ?? '#646970';
			$out .= '<div style="margin:2px 0;line-height:1.5;">'
				. '<span style="color:#1d2327;">' . esc_html( $p->part_label ) . ( $qty > 1 ? ' ×' . $qty : '' ) . '</span> '
				. '<span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;background:' . esc_attr( $c ) . '1a;color:' . esc_attr( $c ) . ';">'
				. esc_html( $labels[ $p->line_status ] ?? $p->line_status ) . '</span></div>';
		}
		return $out;
	}

	/**
	 * Recompute + persist the overall status of a batch of already-fetched request
	 * rows from their parts, mutating each row's overall_status in place. One extra
	 * query total (all parts for the visible rows), and it only writes rows that
	 * actually drifted — so a synced list is read-only on subsequent loads.
	 */
	private function sync_rows( $rows ) {
		global $wpdb;
		if ( empty( $rows ) ) {
			return;
		}
		$t_req  = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );

		$ids = array();
		foreach ( $rows as $r ) {
			$ids[] = (int) $r->id;
		}
		$in       = implode( ',', $ids );
		$parts_by = array();
		foreach ( (array) $wpdb->get_results( "SELECT request_id, line_status FROM $t_item WHERE request_id IN ($in)" ) as $p ) {
			$parts_by[ (int) $p->request_id ][] = $p->line_status;
		}

		foreach ( $rows as $r ) {
			$want = self::compute_overall(
				$parts_by[ (int) $r->id ] ?? array(),
				$r->overall_status,
				! empty( $r->approved_at )
			);
			if ( $want !== $r->overall_status ) {
				$wpdb->update( $t_req, array( 'overall_status' => $want ), array( 'id' => (int) $r->id ) );
				$r->overall_status = $want; // reflect immediately in this render
			}
		}
	}

	/* --------------------------------------------------------------------- Detail */

	private function detail( $id, $notice ) {
		global $wpdb;
		$t_req    = AUN_SP_Install::table( 'requests' );
		$t_item   = AUN_SP_Install::table( 'request_items' );
		$t_attach = AUN_SP_Install::table( 'attachments' );
		$t_event  = AUN_SP_Install::table( 'events' );

		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d", $id ) );
		if ( ! $r ) {
			echo '<h1>Request not found</h1>';
			return;
		}
		// Keep the heading badge in step with the parts on load too (matches the list).
		$this->sync_rows( array( $r ) );
		$items   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t_item WHERE request_id = %d ORDER BY id ASC", $id ) );
		$photos  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t_attach WHERE request_id = %d", $id ) );
		$events  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t_event WHERE request_id = %d ORDER BY id DESC LIMIT 50", $id ) );

		$by_item = array();
		foreach ( (array) $photos as $p ) {
			$by_item[ (int) $p->item_id ][] = $p->file_url;
		}

		$warranty = AUN_SP_Lookup::warranty( $r->purchase_date );
		$age_yrs  = $this->age_years( $r->purchase_date );
		$hard_yrs = (int) get_option( 'aun_sp_hard_source_years', 3 );

		echo '<h1>' . esc_html( $r->ref ) . ' ' . $this->status_badge( $r->overall_status ) . '</h1>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=aun-sp' ) ) . '">&larr; All requests</a></p>';
		echo $notice;

		// Money first: whether this request has been paid for (and whether a refund is
		// owed) is the thing most likely to be missed further down the page.
		$this->payment_banner( $r );

		// Possible-duplicate flag: other requests from the same phone (customers
		// sometimes submit twice). Shown so you can reject the duplicate in a click.
		$this->duplicate_notice( $r );

		// Parts marked "Quoted" but the request never entered the approval flow —
		// the customer has NO Approve/Decline buttons and never got the quote SMS.
		// (Requests saved before this was fixed sit in exactly this state.)
		$has_quoted = false;
		foreach ( (array) $items as $it ) {
			if ( 'quoted' === $it->line_status ) {
				$has_quoted = true;
				break;
			}
		}
		if ( $has_quoted && ! in_array( $r->overall_status, array( 'quote_sent', 'approved', 'declined', 'closed', 'rejected' ), true ) ) {
			echo '<div class="notice notice-warning inline" style="max-width:820px;"><p><strong>This quote has not been sent.</strong> Parts are marked &ldquo;Quoted&rdquo;, but the customer has no Approve / Decline buttons and has not been texted the price. Click <strong>Send quote for approval</strong> below to send it.</p></div>';
		}

		// Hard-to-source flag.
		if ( $age_yrs !== null && $age_yrs >= $hard_yrs ) {
			echo '<div class="notice notice-warning inline"><p><strong>Heads up:</strong> this device is about ' . (int) $age_yrs . ' years old — parts may be hard or impossible to source from the factory.</p></div>';
		}

		// Sale summary.
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;max-width:820px;margin:12px 0;">';
		echo '<table class="widefat" style="border:0;"><tbody>';
		$this->kv( 'Customer', esc_html( $r->customer_name ) );
		$phone_html = esc_html( $r->phone_current );
		if ( $r->phone_current !== '' ) {
			$intl       = AUN_SP_SMS::to_intl( $r->phone_current );
			$phone_html = '<a href="tel:' . esc_attr( '+' . $intl ) . '">' . esc_html( $r->phone_current ) . '</a>'
				. ' &middot; <a href="' . esc_url( 'https://wa.me/' . $intl ) . '" target="_blank" rel="noopener">WhatsApp</a>';
		}
		// Compare delivery details against the purchase record and flag any change,
		// so a changed phone/address is obvious (fraud check + avoids mis-shipping).
		$badge          = '<span style="display:inline-block;margin-left:8px;padding:2px 9px;border-radius:999px;background:#fcf0e4;border:1px solid #e8a33d;color:#8a5300;font-size:11px;font-weight:600;">CHANGED by customer</span>';
		$onfile_note    = function ( $v ) { return '<div style="color:#646970;font-size:12px;margin-top:3px;">On purchase record: ' . esc_html( $v ) . '</div>'; };
		$phone_onfile   = isset( $r->phone_onfile ) ? (string) $r->phone_onfile : '';
		$address_onfile = isset( $r->address_onfile ) ? trim( (string) $r->address_onfile ) : '';
		// Compare NORMALISED numbers: an app-created row may hold 8801… against an
		// on-file 01…, which is the same number and must not read as "changed".
		if ( $phone_onfile !== '' && aun_sp_normalize_phone( $r->phone_current ) !== aun_sp_normalize_phone( $phone_onfile ) ) {
			$phone_html .= $badge . $onfile_note( $phone_onfile );
		}
		$address_html = esc_html( $r->address_current );
		if ( $address_onfile !== '' && trim( (string) $r->address_current ) !== $address_onfile ) {
			$address_html .= $badge . $onfile_note( $address_onfile );
		}
		$this->kv( 'Phone (current)', $phone_html );
		$this->kv( 'Delivery address', $address_html );
		$this->kv( 'Model', esc_html( $r->model ) );
		$this->kv( 'Purchase date', esc_html( $r->purchase_date ) . ( $age_yrs !== null ? ' (' . (int) $age_yrs . ' yrs)' : '' ) );
		$this->kv( 'Warranty', esc_html( $warranty['label'] ) );
		$this->kv( 'Source', esc_html( strtoupper( $r->source_type ) ) . ' · order ' . esc_html( $r->source_order ) );
		echo '</tbody></table></div>';

		// Per-part status form.
		echo '<form method="post" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;max-width:820px;">';
		wp_nonce_field( 'aun_sp_update', 'aun_sp_update_nonce' );
		echo '<h2>Parts</h2>';
		echo '<p style="color:#646970;margin-top:0;">Update each part&rsquo;s <strong>status</strong> as it moves. Set a <strong>price</strong> on any out-of-warranty part.</p>';
		// The ETA used to be described as "just for your own records" — it is NOT. It is
		// printed on the customer's tracking page next to the part, so a rough internal
		// guess typed here reads to them as a promised date.
		echo '<p style="color:#646970;margin-top:0;"><span style="color:#1a7f37;">&#9679;</span> <strong>The customer sees:</strong> status, quantity, price and <strong>ETA</strong>. '
			. '<span style="color:#646970;">&#9679;</span> <strong>Only you see:</strong> Factory PO and Note. '
			. '<em>Leave the ETA blank unless you are willing to have that date quoted back to you.</em></p>';
		echo '<p style="color:#646970;margin-top:0;"><span style="color:#8250df;">&#9679;</span> Setting a part to <strong>&ldquo;Quoted (price sent)&rdquo;</strong> and saving <strong>sends the price to the customer for approval</strong> (same as the button below) &mdash; they get an SMS and Approve / Decline buttons. Every other status texts them a progress update <em>naming that part and its new stage</em>.</p>';
		// The journey in one line, so the two easily-confused steps ("shipped" = the
		// factory sending it here, "dispatched" = us sending it to the customer) can't
		// be mixed up. The customer sees a fuller wording of the same stages.
		echo '<p style="color:#646970;margin-top:0;background:#f6f7f7;border-left:3px solid #c3c4c7;padding:8px 12px;max-width:780px;">'
			. '<strong>The journey:</strong> Ordered from factory &rarr; At factory (being prepared) &rarr; <strong>Shipped</strong> <em>by the factory, heading to Bangladesh</em> &rarr; Arrived at AUN Dhaka &rarr; <strong>Dispatched</strong> <em>by us to the customer, by courier</em> &rarr; Delivered.</p>';
		echo '<div style="overflow-x:auto;"><table class="wp-list-table widefat striped" style="min-width:820px;"><thead><tr><th>Part</th><th style="width:64px;">Qty</th><th>Status</th><th>Factory PO <span style="font-weight:400;color:#646970;">(internal)</span></th><th>ETA <span style="font-weight:400;color:#1a7f37;">(customer sees)</span></th><th>Note <span style="font-weight:400;color:#646970;">(internal)</span></th><th>Unit price (৳)</th><th style="width:90px;">Line total</th><th>Courier tracking</th><th>Photo</th></tr></thead><tbody>';
		$grand = 0.0;
		// Once the customer has paid, quantities and prices are frozen: editing them
		// would silently disagree with the money already taken. Enforced server-side
		// too — see handle_post().
		$locked = false;
		if ( AUN_SP_Woo::is_active() ) {
			$paid_order = AUN_SP_Woo::order_for( (int) $r->id );
			$locked     = AUN_SP_Woo::has_been_paid( $paid_order );
		}
		$lock_attr = $locked ? ' readonly disabled style="background:#f0f0f1;color:#646970;"' : '';
		$excluded = 0;
		foreach ( (array) $items as $it ) {
			$iqty  = max( 1, (int) ( $it->qty ?? 1 ) );
			$line  = round( (float) $it->unit_price * $iqty, 2 );
			// A part we can't supply, or that isn't going ahead, is NOT billed — so it
			// must not be in the total shown here either, or this page and the
			// customer's own screen would quote two different numbers.
			$bill  = self::is_chargeable_line( $it->line_status );
			if ( $bill ) {
				$grand += $line;
			} elseif ( $line > 0 ) {
				$excluded++;
			}
			echo '<tr>';
			echo '<td><strong>' . esc_html( $it->part_label ) . '</strong></td>';
			echo '<td><input type="number" min="1" step="1" name="item_qty[' . $it->id . ']" value="' . esc_attr( $iqty ) . '" class="small-text" style="width:58px;"' . $lock_attr . '></td>';
			echo '<td>' . $this->select( 'item_status[' . $it->id . ']', self::item_statuses(), $it->line_status ) . '</td>';
			echo '<td><input type="text" name="item_po[' . $it->id . ']" value="' . esc_attr( $it->factory_po ) . '" class="small-text"></td>';
			echo '<td><input type="date" name="item_eta[' . $it->id . ']" value="' . esc_attr( $it->eta ) . '"></td>';
			echo '<td><input type="text" name="item_note[' . $it->id . ']" value="' . esc_attr( $it->note ) . '" class="regular-text" style="width:120px;"></td>';
			echo '<td><input type="number" step="0.01" min="0" name="item_price[' . $it->id . ']" value="' . esc_attr( $it->unit_price ) . '" class="small-text" style="width:90px;"' . $lock_attr . '></td>';
			if ( $line > 0 && ! $bill ) {
				echo '<td style="white-space:nowrap;color:#646970;"><s>৳' . esc_html( number_format_i18n( $line, 2 ) ) . '</s><br><span style="font-size:11px;">not charged</span></td>';
			} else {
				echo '<td style="white-space:nowrap;">' . ( $line > 0 ? '৳' . esc_html( number_format_i18n( $line, 2 ) ) : '<span style="color:#646970;">—</span>' ) . '</td>';
			}
			$tno = isset( $it->tracking_no ) ? $it->tracking_no : '';
			echo '<td><input type="text" name="item_track[' . $it->id . ']" value="' . esc_attr( $tno ) . '" class="small-text" placeholder="DA240626FDJC6N" title="Pathao consignment ID — pasting the whole tracking link works too" style="width:120px;">';
			// One helper builds this link and the customer's, so the admin can never
			// see a working chip where the customer sees a dead one (or the reverse).
			$track_url = self::courier_url( $tno );
			if ( '' !== $track_url ) {
				echo ' <a href="' . esc_url( $track_url ) . '" target="_blank" rel="noopener" title="Open Pathao tracking" style="text-decoration:none;">&#8599;</a>';
			} elseif ( '' !== $tno ) {
				echo ' <span title="This is not a usable Pathao consignment ID, so no tracking link is shown to the customer." style="color:#b32d2e;cursor:help;">&#9888;</span>';
			}
			echo '</td>';
			$pics = $by_item[ (int) $it->id ] ?? array();
			echo '<td>';
			foreach ( $pics as $url ) {
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">view</a> ';
			}
			echo '</td>';
			echo '</tr>';
		}
		if ( $grand > 0 ) {
			echo '<tr><td colspan="7" style="text-align:right;font-weight:600;">Quote total (qty × unit price)'
				. ( $excluded ? '<br><span style="font-weight:400;color:#646970;font-size:12px;">' . (int) $excluded . ' part(s) excluded — unavailable or not going ahead</span>' : '' )
				. '</td>'
				. '<td style="font-weight:700;white-space:nowrap;">৳' . esc_html( number_format_i18n( $grand, 2 ) ) . '</td><td colspan="2"></td></tr>';
		}
		echo '</tbody></table></div>';

		echo '<p style="margin-top:14px;color:#646970;">The overall status updates automatically as you change the parts above. Currently: <strong>' . esc_html( self::overall_statuses()[ $r->overall_status ] ?? $r->overall_status ) . '</strong></p>';
		echo '<p><label><input type="checkbox" name="notify" value="1" checked> Text the customer this update by SMS</label></p>';
		echo '<p><button class="button button-primary" name="save_updates" value="1">Save updates</button></p>';

		echo '<hr style="margin:18px 0;"><h3>Send a price quote <span style="font-weight:400;color:#646970;">(out-of-warranty)</span></h3>';
		echo '<p style="color:#646970;">The customer gets an SMS with the price and approves (or declines) it on their tracking page. You only order from the factory after they approve. There are two equivalent ways to send it:</p>';
		echo '<ul style="color:#646970;list-style:disc;margin:0 0 10px 18px;">';
		echo '<li>set a part&rsquo;s <strong>Status</strong> to &ldquo;Quoted&rdquo; (with a price) and press <strong>Save updates</strong>, or</li>';
		echo '<li>press <strong>Send quote for approval</strong> below.</li>';
		echo '</ul>';
		echo '<p style="color:#646970;">Either way the total is <strong>qty × unit price</strong> for every priced part. Use the button again to re-send a revised quote.</p>';
		echo '<p><label>Note to the customer (optional)<br><textarea name="quote_note" rows="2" class="large-text" style="max-width:620px;">' . esc_textarea( (string) $r->quote_note ) . '</textarea></label></p>';
		if ( AUN_SP_Woo::is_active() ) {
			$dc = isset( $r->delivery_charge ) ? (float) $r->delivery_charge : 0;
			echo '<p><label>Delivery charge (৳) <input type="number" step="0.01" min="0" name="delivery_charge" value="' . esc_attr( $dc ) . '" class="small-text" style="width:100px;"></label>'
				. ' <span style="color:#646970;">added to the order the customer pays. Leave 0 for free delivery.</span></p>';
		}
		// What happened to the quote we sent. This used to read the approval timestamp
		// only, so a DECLINED quote (which never gets one) still showed "awaiting
		// approval" — contradicting the red "Quote declined" badge at the top.
		if ( $r->quoted_at ) {
			if ( 'declined' === $r->overall_status ) {
				$state = '<span style="color:#b32d2e;font-weight:600;">declined by the customer</span>';
			} elseif ( $r->approved_at ) {
				$state = '<span style="color:#1a7f37;font-weight:600;">approved ' . esc_html( $r->approved_at ) . '</span>';
			} elseif ( 'expired' === $r->overall_status ) {
				$state = '<span style="color:#bf6a02;font-weight:600;">expired &mdash; the customer never answered</span>';
			} elseif ( 'quote_sent' === $r->overall_status ) {
				$waited = $this->age_days( $r->quoted_at );
				$state  = '<span style="color:#8250df;font-weight:600;">awaiting the customer&rsquo;s decision</span>'
					. ' <span style="color:#646970;">(' . (int) $waited . ' day(s) so far'
					. ( (int) $r->quote_reminders > 0 ? ', ' . (int) $r->quote_reminders . ' reminder(s) sent' : '' )
					. ( ! empty( $r->quote_expires_at ) ? ', expires ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( (string) $r->quote_expires_at ) ) ) : '' )
					. ')</span>';
			} else {
				$state = '<span style="color:#646970;font-weight:600;">no longer awaiting approval &mdash; request is &ldquo;'
					. esc_html( self::overall_statuses()[ $r->overall_status ] ?? $r->overall_status ) . '&rdquo;</span>';
			}
			echo '<p>Quote total: <strong>৳' . esc_html( number_format_i18n( (float) $r->quote_total, 2 ) ) . '</strong> · sent ' . esc_html( $r->quoted_at ) . ' · ' . $state . '</p>';
		}

		// An unanswered quote: the automatic ladder is running, and a personal message
		// converts far better than a fourth automated text — so make that one click.
		if ( in_array( $r->overall_status, array( 'quote_sent', 'expired' ), true ) && $r->phone_current !== '' ) {
			$days = self::quote_valid_days();
			list( $rd1, $rd2 ) = self::reminder_days();
			// Editable like every other customer-facing message (Messages → WhatsApp
			// chase), rather than a sentence baked into this file.
			$wa_text = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_WA_CHASE ), array(
				'name'  => $r->customer_name,
				'ref'   => $r->ref,
				'total' => number_format_i18n( (float) $r->quote_total, 2 ),
				'track' => AUN_SP_Messages::track_link( $r->ref ),
			) );
			echo '<div style="background:#f6f7f7;border-left:3px solid #25D366;border-radius:6px;padding:10px 14px;margin:12px 0;max-width:780px;">';
			echo '<p style="margin:0 0 6px;"><strong>Chasing this quote:</strong> ';
			echo 'the customer is texted a reminder on day ' . (int) $rd1 . ' and day ' . (int) $rd2
				. ( $days > 0 ? ', then the quote expires on day ' . (int) $days . '.' : ' (this quote never expires — see Settings).' );
			echo '</p>';
			echo '<p style="margin:0;"><a class="button" target="_blank" rel="noopener" href="'
				. esc_url( 'https://wa.me/' . AUN_SP_SMS::to_intl( $r->phone_current ) . '?text=' . rawurlencode( $wa_text ) ) . '">Chase on WhatsApp</a> '
				. '<span style="color:#646970;">a personal message converts far better than another automated text.</span></p>';
			echo '</div>';
		}

		// Label the button for what it will actually do right now.
		if ( 'expired' === $r->overall_status ) {
			$btn  = 'Send a fresh quote';
			$hint = 'The quote lapsed with no reply. Check the prices are still right, then send it again — the deadline and reminders restart.';
		} elseif ( 'declined' === $r->overall_status ) {
			$btn  = 'Send a revised quote';
			$hint = 'The customer declined. Change the prices above, then send a new quote to ask again.';
		} elseif ( $r->quoted_at ) {
			$btn  = 'Re-send quote for approval';
			$hint = 'Sends the current total again and re-opens Approve / Decline for the customer.';
		} else {
			$btn  = 'Send quote for approval';
			$hint = '';
		}
		echo '<p><button class="button" name="send_quote" value="1">' . esc_html( $btn ) . '</button>'
			. ( $hint ? ' <span style="color:#646970;">' . esc_html( $hint ) . '</span>' : '' ) . '</p>';
		echo '</form>';

		// WooCommerce order + payment state, when one exists.
		$this->order_panel( $r );

		// Customer-added photos (re-uploads from the tracking page).
		if ( ! empty( $by_item[0] ) ) {
			echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;max-width:820px;margin-top:14px;"><h2>Customer-added photos</h2>';
			foreach ( $by_item[0] as $url ) {
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" style="margin-right:12px;">view photo</a>';
			}
			echo '</div>';
		}

		// Ask for a better photo — re-opens the customer's re-upload box on tracking.
		//
		// The REASON is the whole point of this panel. "Please send a clearer photo"
		// only fixes a blurry photo; if they photographed the wrong part it just buys
		// you a sharper photo of the wrong part. So the reason (and optionally WHICH
		// part it concerns) travels to their screen and into the SMS.
		echo '<form method="post" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;max-width:820px;margin-top:16px;">';
		wp_nonce_field( 'aun_sp_photo', 'aun_sp_photo_nonce' );
		echo '<h2>Send the photo back</h2>';
		echo '<p style="color:#646970;">Sets this request to <strong>Waiting on customer</strong> and texts them a link to re-upload. It flips back to <em>In progress</em> automatically once they send a new photo.</p>';

		// NO reason is pre-selected. The first entry used to be "Wrong part
		// photographed", so one absent-minded click accused the customer of sending
		// the wrong part — the most annoying of the seven to receive wrongly.
		$cur_reason = (string) ( $r->photo_reason ?? '' );
		echo '<p><label><strong>What is wrong with it?</strong><br><select name="photo_reason" style="min-width:340px;" required>';
		echo '<option value="">&mdash; choose a reason &mdash;</option>';
		foreach ( self::photo_reasons() as $rk => $rlabel ) {
			echo '<option value="' . esc_attr( $rk ) . '"' . selected( $cur_reason, $rk, false ) . '>' . esc_html( $rlabel ) . '</option>';
		}
		echo '</select></label></p>';

		// Which part's photo. With a single part there is nothing to choose — say which
		// one it is and let the handler target it, so a one-part request still gets the
		// "your photo vs the example" comparison instead of the generic gallery.
		$item_list = array_values( (array) $items );
		if ( count( $item_list ) > 1 ) {
			echo '<p><label><strong>Which part\'s photo?</strong><br><select name="photo_item_id" style="min-width:340px;">';
			echo '<option value="0">All of them / not sure</option>';
			foreach ( $item_list as $it ) {
				echo '<option value="' . (int) $it->id . '"' . selected( (int) ( $r->photo_item_id ?? 0 ), (int) $it->id, false ) . '>'
					. esc_html( $it->part_label ) . '</option>';
			}
			echo '</select></label></p>';
		} elseif ( 1 === count( $item_list ) ) {
			echo '<p style="color:#646970;">This is about <strong>' . esc_html( $item_list[0]->part_label )
				. '</strong> — they will see their photo next to the example.</p>';
		}

		echo '<p><label><strong>Anything to add?</strong> <span style="color:#646970;font-weight:400;">optional — the customer reads this word for word, in whichever language they are browsing in</span><br>';
		echo '<textarea name="photo_note" rows="2" class="large-text" maxlength="300" placeholder="e.g. The photo shows the power board, but we need the LCD panel behind it.">'
			. esc_textarea( (string) ( $r->photo_note ?? '' ) ) . '</textarea></label></p>';
		echo '<p><button class="button button-primary" name="photo_ask" value="1">Ask customer for a new photo</button>';

		// Waiting on the customer is a STICKY status (compute_overall never moves it),
		// so without this an ask sent by mistake — or one answered on WhatsApp instead
		// — left the request frozen on "Waiting on you" with no way back.
		if ( 'waiting_customer' === $r->overall_status ) {
			echo ' <button class="button" name="photo_cancel" value="1">Cancel this request</button>'
				. ' <span style="color:#646970;">stop waiting and put it back to In progress (no SMS)</span>';
		}
		echo '</p></form>';

		// Reject panel.
		$tpls = $this->reject_templates( $r->model, $r->purchase_date, $items, $age_yrs );
		echo '<form method="post" style="background:#fff;border:1px solid #f0c8c8;border-radius:8px;padding:14px 18px;max-width:820px;margin-top:16px;">';
		wp_nonce_field( 'aun_sp_reject', 'aun_sp_reject_nonce' );
		echo '<h2 style="color:#b32d2e;">Reject this request</h2>';
		echo '<p><label>Reason template ';
		echo '<select id="aun-sp-reject-tpl">';
		echo '<option value="">— choose a template —</option>';
		foreach ( $tpls as $key => $text ) {
			echo '<option data-text="' . esc_attr( $text ) . '">' . esc_html( $key ) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><textarea id="aun-sp-reject-text" name="reject_reason" rows="4" class="large-text" placeholder="Explain why we can\'t supply the part…"></textarea></p>';
		echo '<p><label><input type="checkbox" name="notify" value="1" checked> Text the customer this reason by SMS</label></p>';
		echo '<p><button class="button" style="color:#b32d2e;border-color:#b32d2e;">Reject request</button></p>';
		echo '</form>';
		echo '<script data-no-optimize="1" data-no-minify="1">document.getElementById("aun-sp-reject-tpl").addEventListener("change",function(){var t=this.options[this.selectedIndex].getAttribute("data-text")||"";if(t){document.getElementById("aun-sp-reject-text").value=t;}});</script>';

		// Activity log.
		echo '<h2 style="margin-top:20px;">Activity</h2><ul style="max-width:820px;">';
		foreach ( (array) $events as $e ) {
			echo '<li style="padding:4px 0;border-bottom:1px solid #f0f0f1;"><span style="color:#646970;">' . esc_html( $e->created_at ) . '</span> — ' . esc_html( $e->message ) . ' <em style="color:#646970;">(' . esc_html( $e->by_user ) . ')</em></li>';
		}
		echo '</ul>';
	}

	/**
	 * A loud, top-of-page statement of the money position — paid, refund owed, or
	 * refunded. The quiet line further down was too easy to scroll past.
	 */
	private function payment_banner( $r ) {
		if ( ! AUN_SP_Woo::is_active() ) {
			return;
		}
		$order = AUN_SP_Woo::order_for( (int) $r->id );
		if ( ! $order ) {
			return; // cash on delivery — nothing collected yet
		}
		$total = '৳' . number_format_i18n( (float) $order->get_total(), 2 );
		$link  = ' <a href="' . esc_url( $order->get_edit_order_url() ) . '">order #' . esc_html( $order->get_order_number() ) . '</a>';

		if ( ! empty( $r->refunded_at ) ) {
			echo '<div style="background:#f0f6ff;border:1px solid #2271b1;border-left-width:6px;border-radius:6px;padding:12px 16px;max-width:820px;margin:12px 0;">'
				. '<strong style="color:#2271b1;font-size:15px;">↩ REFUNDED ৳' . esc_html( number_format_i18n( (float) $r->refund_amount, 2 ) ) . '</strong> '
				. '<span style="color:#646970;">on ' . esc_html( substr( (string) $r->refunded_at, 0, 10 ) )
				. ( $r->refund_ref ? ' · ref ' . esc_html( $r->refund_ref ) : '' ) . ' · ' . $link . '</span></div>';
			return;
		}

		if ( AUN_SP_Woo::refund_due( (int) $r->id ) ) {
			echo '<div style="background:#fcf0f1;border:1px solid #b32d2e;border-left-width:6px;border-radius:6px;padding:12px 16px;max-width:820px;margin:12px 0;">'
				. '<strong style="color:#b32d2e;font-size:15px;">⚠ REFUND DUE ' . esc_html( $total ) . '</strong> '
				. '<span style="color:#646970;">— the customer paid online and this request is now &ldquo;'
				. esc_html( self::overall_statuses()[ $r->overall_status ] ?? $r->overall_status ) . '&rdquo;.' . $link . '</span>'
				. '<div style="margin-top:8px;">' . $this->refund_form( $order ) . '</div></div>';
			return;
		}

		// Admin banner: the same historical fact, so a delivered request does not
		// stop saying PAID and quietly unlock its prices.
		if ( AUN_SP_Woo::has_been_paid( $order ) ) {
			echo '<div style="background:#edfaef;border:1px solid #1a7f37;border-left-width:6px;border-radius:6px;padding:12px 16px;max-width:820px;margin:12px 0;">'
				. '<strong style="color:#1a7f37;font-size:15px;">✓ PAID ONLINE ' . esc_html( $total ) . '</strong> '
				. '<span style="color:#646970;">' . esc_html( $order->get_payment_method_title() ) . ' · ' . $link
				. ' · quantities and prices are locked below.</span></div>';
			return;
		}

		echo '<div style="background:#fdf6e7;border:1px solid #bf6a02;border-left-width:6px;border-radius:6px;padding:12px 16px;max-width:820px;margin:12px 0;">'
			. '<strong style="color:#8a5300;font-size:15px;">● ONLINE PAYMENT STARTED, NOT COMPLETED</strong> '
			. '<span style="color:#646970;">' . esc_html( $total ) . ' · ' . $link
			. ' — treat as cash on delivery unless it completes.</span></div>';
	}

	/** The "record a manual refund" form (their gateway can't refund via WooCommerce). */
	private function refund_form( $order ) {
		ob_start();
		echo '<form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
		wp_nonce_field( 'aun_sp_refund', 'aun_sp_refund_nonce' );
		echo '<label>Amount refunded ৳ <input type="number" step="0.01" min="0" name="refund_amount" value="' . esc_attr( (float) $order->get_total() ) . '" class="small-text" style="width:100px;"></label>';
		echo '<label>Reference <input type="text" name="refund_ref" class="regular-text" style="width:180px;" placeholder="bKash TrxID / bank ref"></label>';
		echo '<button class="button button-primary">Mark as refunded</button>';
		echo '</form>';
		echo '<p style="margin:6px 0 0;color:#646970;font-size:12px;">Send the money by hand first (bKash/bank), then record it here. The customer is texted and sees it on their tracking page.</p>';
		return ob_get_clean();
	}

	/**
	 * The WooCommerce order behind this request: what the customer owes, how they
	 * chose to pay, and whether the money has actually arrived. Cash on delivery
	 * reaches "processing" WITHOUT payment, so that case is spelled out rather than
	 * being reported as paid.
	 */
	private function order_panel( $r ) {
		if ( ! AUN_SP_Woo::is_active() ) {
			return;
		}
		$order = AUN_SP_Woo::order_for( (int) $r->id );
		if ( ! $order ) {
			// Approved and chargeable but no order — either it was approved before the
			// payment bridge existed, or creation failed. Offer to raise it by hand
			// rather than leaving the customer with nothing to pay.
			$owed = $this->quote_total( (int) $r->id );
			// No payment link for a finished request — the parts are with the customer
			// and, on cash on delivery, the money came with them.
			if ( $owed > 0 && ! self::is_cancelled( $r->overall_status ) && 'closed' !== $r->overall_status ) {
				echo '<form method="post" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;max-width:820px;margin-top:14px;">';
				wp_nonce_field( 'aun_sp_mkorder', 'aun_sp_mkorder_nonce' );
				echo '<h2 style="margin-top:0;">Payment</h2>';
				echo '<p style="color:#646970;margin-top:0;">This request is <strong>cash on delivery</strong> &mdash; collect ৳'
					. esc_html( number_format_i18n( $owed, 2 ) ) . ' plus any delivery charge when you hand the parts over. Nothing further is needed.</p>';
				echo '<p style="color:#646970;">If the customer would rather pay online, send them a payment link &mdash; that creates the WooCommerce order and the amount is taken from the parts as priced right now.</p>';
				echo '<p><button class="button button-primary">Send online payment link</button></p>';
				echo '</form>';
			}
			return;
		}

		// An order exists only because the customer chose to pay online, so there is
		// no cash-on-delivery case here — COD requests simply have no order.
		$state = AUN_SP_Woo::has_been_paid( $order )
			? '<span style="color:#1a7f37;font-weight:600;">paid online</span>'
			: '<span style="color:#8250df;font-weight:600;">started online payment — not completed</span>';

		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;max-width:820px;margin-top:14px;">';
		echo '<h2 style="margin-top:0;">Payment &mdash; online</h2>';
		echo '<p style="margin:0 0 6px;">Order <a href="' . esc_url( $order->get_edit_order_url() ) . '"><strong>#' . esc_html( $order->get_order_number() ) . '</strong></a>'
			. ' · <strong>৳' . esc_html( number_format_i18n( (float) $order->get_total(), 2 ) ) . '</strong>'
			. ' · ' . esc_html( wc_get_order_status_name( $order->get_status() ) )
			. ' · ' . $state . '</p>';
		if ( $order->get_payment_method_title() ) {
			echo '<p style="margin:0 0 6px;color:#646970;">Method: ' . esc_html( $order->get_payment_method_title() ) . '</p>';
		}
		if ( $order->needs_payment() ) {
			echo '<p style="margin:0;color:#646970;">Customer pay link: <a href="' . esc_url( $order->get_checkout_payment_url() ) . '" target="_blank" rel="noopener">open</a>'
				. ' <span>— if they never complete it, treat the request as cash on delivery; the order stays unpaid and is cancelled automatically if you reject the request.</span></p>';
		}
		echo '</div>';
	}

	/**
	 * Warn when the same phone has other requests — a customer sending the same
	 * request twice is common. Lists the siblings (newest first) with their status
	 * and a link, so a genuine duplicate can be opened and rejected quickly.
	 */
	private function duplicate_notice( $r ) {
		global $wpdb;
		if ( empty( $r->phone_current ) ) {
			return;
		}
		$t_req = AUN_SP_Install::table( 'requests' );
		// Format-tolerant, so a web request and an app request from the same customer
		// are recognised as related even though they store the number differently.
		$dupes = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, ref, overall_status, model, created_at
			 FROM $t_req
			 WHERE " . aun_sp_phone_where( 'phone_current', $r->phone_current ) . " AND id <> %d
			 ORDER BY created_at DESC LIMIT 10",
			(int) $r->id
		) );
		if ( empty( $dupes ) ) {
			return;
		}

		// Highlight likely-duplicate siblings: still open (not a finished past repair).
		$open_dupes = 0;
		foreach ( $dupes as $d ) {
			if ( ! in_array( $d->overall_status, self::TERMINAL_STATES, true ) ) {
				$open_dupes++;
			}
		}
		$class = $open_dupes ? 'notice-warning' : 'notice-info';
		echo '<div class="notice ' . $class . ' inline" style="max-width:820px;"><p style="margin-bottom:6px;"><strong>Same phone on ' . (int) count( $dupes ) . ' other request' . ( count( $dupes ) > 1 ? 's' : '' ) . '</strong>'
			. ( $open_dupes ? ' — ' . (int) $open_dupes . ' still open. If this is a duplicate submission, reject it below with the “Duplicate request” reason.' : ' (all completed/closed — likely earlier repairs).' ) . '</p><ul style="margin:0 0 2px 2px;list-style:disc;padding-left:18px;">';
		foreach ( $dupes as $d ) {
			$url = admin_url( 'admin.php?page=aun-sp&request=' . (int) $d->id );
			echo '<li style="margin:2px 0;"><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $d->ref ) . '</strong></a> — '
				. $this->status_badge( $d->overall_status ) . ' <span style="color:#646970;">' . esc_html( $d->model ) . ' · ' . esc_html( substr( (string) $d->created_at, 0, 10 ) ) . '</span></li>';
		}
		echo '</ul></div>';
	}

	/* -------------------------------------------------------------------- Handlers */

	private function handle_post( $id ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		$t_req  = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );

		// Save per-part updates.
		if ( isset( $_POST['aun_sp_update_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_update_nonce'], 'aun_sp_update' ) ) {
			$statuses = (array) ( $_POST['item_status'] ?? array() );
			$pos      = (array) ( $_POST['item_po'] ?? array() );
			$etas     = (array) ( $_POST['item_eta'] ?? array() );
			$notes    = (array) ( $_POST['item_note'] ?? array() );
			$prices   = (array) ( $_POST['item_price'] ?? array() );
			$tracks   = (array) ( $_POST['item_track'] ?? array() );
			$qtys     = (array) ( $_POST['item_qty'] ?? array() );
			$labels   = self::item_statuses();
			// Money already taken? Then quantities and prices are frozen. The inputs are
			// disabled in the UI, but never trust that — a stale tab or a crafted POST
			// must not be able to change what the customer was charged.
			$money_locked = false;
			if ( AUN_SP_Woo::is_active() ) {
				$paid_order   = AUN_SP_Woo::order_for( $id );
				$money_locked = AUN_SP_Woo::has_been_paid( $paid_order );
			}
			$moves    = array(); // new line_status => the parts that moved to it
			$bad_tracking = array(); // parts where the courier field was refused
			$became_quoted = false; // a part was moved to "Quoted" in this save

			foreach ( $statuses as $iid => $new ) {
				$iid = (int) $iid;
				$new = array_key_exists( $new, $labels ) ? $new : 'pending';
				$cur = $wpdb->get_row( $wpdb->prepare( "SELECT line_status, part_label, qty, unit_price, tracking_no FROM $t_item WHERE id = %d AND request_id = %d", $iid, $id ) );
				if ( ! $cur ) {
					continue;
				}
				// Qty: at least 1, and never silently blank out an existing value.
				$new_qty = isset( $qtys[ $iid ] ) ? max( 1, (int) $qtys[ $iid ] ) : max( 1, (int) $cur->qty );
				if ( $money_locked ) {
					$new_qty = max( 1, (int) $cur->qty ); // frozen after payment
				}
				$eta = sanitize_text_field( wp_unslash( $etas[ $iid ] ?? '' ) );
				// Only a real Y-m-d reaches the DATE column — a malformed value would
				// make MySQL reject the whole row update silently.
				if ( $eta !== '' && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $eta ) ) {
					$eta = '';
				}
				// Pathao consignment: a bare ID, or any pasted tracking link.
				// Anything that ISN'T one is refused and the old value kept — storing a
				// mangled string used to hand the customer a dead tracking chip.
				$typed = trim( (string) wp_unslash( $tracks[ $iid ] ?? '' ) );
				$track = self::clean_consignment( $typed );
				if ( '' === $track && '' !== $typed ) {
					$track          = (string) ( $cur->tracking_no ?? '' ); // keep what was there
					$bad_tracking[] = $cur->part_label;
				}
				$wpdb->update(
					$t_item,
					array(
						'line_status' => $new,
						'qty'         => $new_qty,
						'factory_po'  => sanitize_text_field( wp_unslash( $pos[ $iid ] ?? '' ) ),
						'eta'         => $eta !== '' ? $eta : null,
						'note'        => sanitize_text_field( wp_unslash( $notes[ $iid ] ?? '' ) ),
						'unit_price'  => $money_locked ? (float) $cur->unit_price : round( (float) ( $prices[ $iid ] ?? 0 ), 2 ),
						'tracking_no' => $track,
						'updated_at'  => current_time( 'mysql' ),
					),
					array( 'id' => $iid )
				);
				$qty_sfx = $new_qty > 1 ? ' ×' . $new_qty : '';
				if ( $cur->line_status !== $new ) {
					$this->log( $id, $iid, 'status_change', $cur->part_label . $qty_sfx . ': ' . $labels[ $cur->line_status ] . ' → ' . $labels[ $new ] );
					// A part moving INTO "Quoted" is the admin saying "quote this" — it
					// triggers the real quote flow below instead of a plain parts SMS.
					if ( 'quoted' === $new ) {
						$became_quoted = true;
					} else {
						$moves[ $new ][] = $cur->part_label . $qty_sfx;
					}
				}
				if ( (int) $cur->qty !== $new_qty ) {
					$this->log( $id, $iid, 'qty_change', $cur->part_label . ': quantity ' . (int) $cur->qty . ' → ' . $new_qty );
				}
			}

			// Describe the moves in the CUSTOMER's words (never the internal dropdown
			// label), grouping the parts that landed on the same stage — "LCD screen,
			// Power board: Ordered from the factory" is one SMS segment where two
			// separate sentences would have been two.
			$changes = array();
			$detail  = array();
			foreach ( $moves as $status_key => $part_names ) {
				$names     = implode( ', ', $part_names );
				$label     = AUN_SP_I18N::it_label( $status_key );
				$help      = AUN_SP_I18N::it_help( $status_key );
				$changes[] = $names . ': ' . $label;
				$detail[]  = $names . ' - ' . $label . ( $help !== '' ? '. ' . $help : '.' );
			}

			$quote_note = sanitize_textarea_field( wp_unslash( $_POST['quote_note'] ?? '' ) );
			$delivery_notice = '';
			if ( isset( $_POST['delivery_charge'] ) && ! $money_locked ) {
				$charge = max( 0, round( (float) $_POST['delivery_charge'], 2 ) );
				$wpdb->update( $t_req, array( 'delivery_charge' => $charge ), array( 'id' => $id ) );
				// The order is built at approval, so a later edit has to be pushed onto
				// it — otherwise the customer keeps paying the old total.
				if ( AUN_SP_Woo::is_active() ) {
					$synced = AUN_SP_Woo::sync_delivery_charge( $id, $charge );
					if ( 'updated' === $synced ) {
						$delivery_notice = ' Delivery charge updated on the customer\'s order.';
					} elseif ( 'failed' === $synced ) {
						// The charge IS saved on the request; only the WooCommerce order
						// could not be rebuilt. Say exactly that, or the admin walks away
						// believing the customer's payment link carries the new total.
						$delivery_notice = ' <strong>The delivery charge was saved, but the payment order could NOT be updated</strong>'
							. ' — another plugin failed while WooCommerce was saving it. See the activity log below, and check the order before sending a payment link.';
					} elseif ( 'paid' === $synced ) {
						$delivery_notice = ' <strong>Note:</strong> that order is already paid, so its delivery charge was left unchanged.';
					}
				}
			}
			$total      = $this->quote_total( $id );
			$current    = (string) $wpdb->get_var( $wpdb->prepare( "SELECT overall_status FROM $t_req WHERE id = %d", $id ) );

			// Sending a quote has TWO entry points and they must behave identically:
			//   - the explicit "Send quote for approval" button, and
			//   - simply setting a part's status to "Quoted" and saving, which is what
			//     the workflow reads like. That second path used to only save the row
			//     (no quote, no approve/decline for the customer) while still firing a
			//     generic parts-update SMS — so it LOOKED like the quote had gone out.
			// Already quote_sent? Don't auto-resend on every save; the button re-sends.
			$auto_quote = ( $became_quoted && 'quote_sent' !== $current );
			if ( isset( $_POST['send_quote'] ) || $auto_quote ) {
				// Price it in QUOTE scope: on a declined or expired request every line
				// is 'cancelled', which billing (rightly) counts as ৳0 — using that here
				// would make the plugin refuse to send its own revised quote.
				$quote_sum = $this->quote_total( $id, 'quote' );
				if ( $quote_sum <= 0 ) {
					return '<div class="notice notice-error is-dismissible"><p><strong>No quote was sent.</strong> Enter a price for at least one part first — a quote of ৳0 has nothing for the customer to approve.</p></div>';
				}
				return $this->do_send_quote( $id, $quote_sum, $quote_note, $auto_quote );
			}

			// Plain save: keep the total current and let the overall status follow the
			// parts automatically (it never overrides a state that waits on the customer).
			$overall = $this->derive_overall( $current, $id );
			$wpdb->update( $t_req, array( 'overall_status' => $overall, 'quote_total' => $total, 'quote_note' => $quote_note, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
			// The request is the master record — push its state onto any payment order
			// (Completed -> order completed; never the other way round).
			if ( AUN_SP_Woo::is_active() && $overall !== $current ) {
				AUN_SP_Woo::sync_from_request( $id, $overall );
			}

			$extra = '';
			// Finishing the job is its own message, not a progress update: a request
			// that has just been fully delivered used to text "update on your request —
			// LCD screen: Delivered to you. Track it: <link>", which reads like there is
			// more to come and links to a page with nothing left on it.
			$just_completed = ( 'closed' === $overall && 'closed' !== $current );
			// Never text the customer about a request that was ALREADY finished before
			// this save. Tidying the parts on a declined, rejected, expired or
			// completed request is bookkeeping — texting "LCD screen: Delivered to you"
			// to someone who declined weeks ago (the notify box is checked by default)
			// is the kind of message that makes people distrust every other one.
			// A request that BECOMES terminal in this save still texts: that transition
			// is the news.
			$was_finished = in_array( $current, self::TERMINAL_STATES, true );
			if ( $was_finished && ! empty( $_POST['notify'] ) ) {
				$extra = ' This request is already ' . esc_html( self::overall_statuses()[ $current ] ?? $current )
					. ', so the customer was not texted.';
			} elseif ( ! empty( $_POST['notify'] ) ) {
				if ( $just_completed ) {
					$extra = $this->sms_customer( $id, 'done', $overall, implode( ', ', $changes ) );
				} elseif ( ! empty( $changes ) ) {
					// THE PART YOU MOVED IS THE NEWS. The overall status is a coarse
					// internal bucket — six different part stages (ordered / at factory /
					// shipped / arrived / dispatched / delivered-in-part) all collapse into
					// "In progress". Routing on the overall meant that setting a part to
					// "Ordered from factory" right after an approval texted the customer
					// "your request is now In progress", which tells them nothing and
					// contradicts what was actually done. Whenever a part moved, the SMS
					// now names the part and its new status ({changes} / {detail}); the
					// coarse status is still available to the template as {status}.
					$extra = $this->sms_customer( $id, 'parts', $overall, implode( ', ', $changes ), $detail );
				} elseif ( $overall !== $current ) {
					// No part moved but the request as a whole did (e.g. it re-derived
					// after a customer action): the overall status IS the news here.
					$extra = $this->sms_customer( $id, 'status', $overall, '' );
				}
			}
			// A refused courier value must never pass unnoticed: the customer would be
			// looking at a tracking chip that opens a Pathao page finding nothing.
			if ( ! empty( $bad_tracking ) ) {
				$extra .= ' <strong>Courier tracking not saved for ' . esc_html( implode( ', ', $bad_tracking ) )
					. '</strong> — that didn&rsquo;t look like a Pathao consignment ID. Paste the ID (e.g. <code>DA240626FDJC6N</code>) or the full tracking link.';
			}
			$moved = ( $overall !== $current ) ? ' Status is now &ldquo;' . esc_html( self::overall_statuses()[ $overall ] ?? $overall ) . '&rdquo;.' : '';
			if ( ! empty( $_POST['notify'] ) && $extra === '' && empty( $changes ) && $overall === $current ) {
				$extra = ' No status changed, so no SMS was sent.';
			}
			return '<div class="notice notice-success is-dismissible"><p>Saved.' . $moved . $extra . $delivery_notice . '</p></div>';
		}

		// Record a refund the admin paid out by hand (the gateway can't refund via
		// WooCommerce, so this is a record + status change, not an API call).
		if ( isset( $_POST['aun_sp_refund_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_refund_nonce'], 'aun_sp_refund' ) ) {
			if ( ! AUN_SP_Woo::is_active() ) {
				return '<div class="notice notice-error is-dismissible"><p>WooCommerce isn&rsquo;t active.</p></div>';
			}
			$amount = round( (float) ( $_POST['refund_amount'] ?? 0 ), 2 );
			$ref    = sanitize_text_field( wp_unslash( $_POST['refund_ref'] ?? '' ) );
			AUN_SP_Woo::record_refund( $id, $amount, $ref );
			return '<div class="notice notice-success is-dismissible"><p>Refund of ৳' . esc_html( number_format_i18n( $amount, 2 ) )
				. ' recorded. The order is marked refunded, the customer has been texted, and it now shows on their tracking page.</p></div>';
		}

		// Create the WooCommerce payment order by hand (recovery path).
		if ( isset( $_POST['aun_sp_mkorder_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_mkorder_nonce'], 'aun_sp_mkorder' ) ) {
			if ( ! AUN_SP_Woo::is_active() ) {
				return '<div class="notice notice-error is-dismissible"><p>WooCommerce isn&rsquo;t active, so no payment order can be created.</p></div>';
			}
			$oid = AUN_SP_Woo::create_order( $id );
			if ( ! $oid ) {
				return '<div class="notice notice-error is-dismissible"><p>Could not create the order &mdash; check that at least one part has a price above ৳0.</p></div>';
			}
			$order = wc_get_order( $oid );
			$sent  = '';
			// Text the customer their pay link, same as an automatic approval would.
			$req = $wpdb->get_row( $wpdb->prepare( "SELECT ref, phone_current FROM $t_req WHERE id = %d", $id ) );
			if ( $req && AUN_SP_SMS::is_configured() && $req->phone_current !== '' && $order->needs_payment() ) {
				$msg = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_PAY ), array(
					'ref'   => $req->ref,
					'link'  => $order->get_checkout_payment_url(),
					'total' => number_format_i18n( (float) $order->get_total(), 2 ),
					'track' => AUN_SP_Messages::track_link( $req->ref ),
				) );
				AUN_SP_SMS::send_tracked( $id, $req->phone_current, $msg, 'payment link' );
				$sent = ' The customer has been texted the payment link.';
			}
			return '<div class="notice notice-success is-dismissible"><p>Payment order #' . esc_html( $order->get_order_number() )
				. ' created for ৳' . esc_html( number_format_i18n( (float) $order->get_total(), 2 ) ) . '.' . $sent . '</p></div>';
		}

		// Reject.
		if ( isset( $_POST['aun_sp_reject_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_reject_nonce'], 'aun_sp_reject' ) ) {
			$reason = sanitize_textarea_field( wp_unslash( $_POST['reject_reason'] ?? '' ) );
			if ( $reason === '' ) {
				return '<div class="notice notice-error is-dismissible"><p>Please enter a rejection reason.</p></div>';
			}
			$wpdb->update(
				$t_req,
				array( 'overall_status' => 'rejected', 'admin_note' => $reason, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $id )
			);
			// Keep the parts consistent with the rejected request: a rejection means we
			// can't supply, so every not-yet-delivered part becomes Unavailable (anything
			// already Delivered is preserved as history).
			$marked = (int) $wpdb->query( $wpdb->prepare(
				"UPDATE $t_item SET line_status = 'unavailable', updated_at = %s
				 WHERE request_id = %d AND line_status <> 'delivered'",
				current_time( 'mysql' ), $id
			) );
			$this->log( $id, 0, 'rejected', 'Rejected: ' . $reason );
			if ( AUN_SP_Woo::is_active() ) {
				AUN_SP_Woo::sync_from_request( $id, 'rejected' );
			}
			$extra = ! empty( $_POST['notify'] ) ? $this->sms_customer( $id, 'rejected', 'rejected', $reason ) : '';
			return '<div class="notice notice-success is-dismissible"><p>Request rejected.' . ( $marked ? ' ' . $marked . ' part(s) marked &ldquo;Unavailable&rdquo;.' : '' ) . $extra . '</p></div>';
		}

		// Ask the customer for a better photo — sets "Waiting on customer" so the
		// tracking page shows the re-upload box, and texts them the link.
		if ( isset( $_POST['aun_sp_photo_nonce'] ) && wp_verify_nonce( $_POST['aun_sp_photo_nonce'], 'aun_sp_photo' ) ) {

			// Stop waiting, without telling the customer anything: usually the photo
			// arrived on WhatsApp, or the ask was a slip.
			if ( ! empty( $_POST['photo_cancel'] ) ) {
				$wpdb->update(
					$t_req,
					array(
						'overall_status' => 'in_progress',
						'photo_reason'   => '',
						'photo_note'     => '',
						'photo_item_id'  => 0,
						'updated_at'     => current_time( 'mysql' ),
					),
					array( 'id' => $id, 'overall_status' => 'waiting_customer' )
				);
				// 'photo_cancel' is deliberately NOT in PUBLIC_EVENTS: the customer has
				// no use for "we changed our mind about the photo".
				$this->log( $id, 0, 'photo_cancel', 'Cancelled the photo request' );
				return '<div class="notice notice-success is-dismissible"><p>No longer waiting on the customer. No SMS was sent.</p></div>';
			}

			$reasons = self::photo_reasons();
			$reason  = sanitize_key( wp_unslash( $_POST['photo_reason'] ?? '' ) );
			if ( ! isset( $reasons[ $reason ] ) ) {
				// Refuse rather than guess. Guessing here sends a real SMS to a real
				// customer telling them something we were never told.
				return '<div class="notice notice-error is-dismissible"><p>Choose what is wrong with the photo first — nothing was sent.</p></div>';
			}
			$note = sanitize_textarea_field( wp_unslash( $_POST['photo_note'] ?? '' ) );
			if ( function_exists( 'mb_substr' ) ) {
				$note = mb_substr( $note, 0, 300 );
			}
			// "Something else" is the one reason that carries no explanation of its own —
			// its instruction to the customer is literally "read our note below". Sent
			// without a note it says nothing at all, and costs an SMS to say it.
			if ( 'other' === $reason && '' === trim( $note ) ) {
				return '<div class="notice notice-error is-dismissible"><p>&ldquo;Something else&rdquo; needs a note &mdash; that note is the only thing the customer will be told. Nothing was sent.</p></div>';
			}
			// Only accept an item that really belongs to THIS request — a posted id from
			// somewhere else would name another customer's part on the tracking page.
			$item_id = (int) ( $_POST['photo_item_id'] ?? 0 );
			if ( $item_id > 0 ) {
				$owns = (int) $wpdb->get_var( $wpdb->prepare(
					'SELECT COUNT(*) FROM ' . AUN_SP_Install::table( 'request_items' ) . ' WHERE id = %d AND request_id = %d',
					$item_id, $id
				) );
				if ( ! $owns ) {
					$item_id = 0;
				}
			}
			if ( 0 === $item_id ) {
				// One part means there is nothing to disambiguate — target it anyway, so
				// a single-part request (most of them) still gets the side-by-side
				// comparison rather than the generic "here are the examples" gallery.
				$only = $wpdb->get_col( $wpdb->prepare(
					'SELECT id FROM ' . AUN_SP_Install::table( 'request_items' ) . ' WHERE request_id = %d', $id
				) );
				if ( 1 === count( (array) $only ) ) {
					$item_id = (int) $only[0];
				}
			}

			$wpdb->update(
				$t_req,
				array(
					'overall_status' => 'waiting_customer',
					'photo_reason'   => $reason,
					'photo_note'     => $note,
					'photo_item_id'  => $item_id,
					'updated_at'     => current_time( 'mysql' ),
				),
				array( 'id' => $id )
			);

			// The audit line records WHY, so a request that bounced twice reads as a
			// story rather than two identical "asked for a better photo" entries.
			$part_label = '';
			if ( $item_id > 0 ) {
				$part_label = (string) $wpdb->get_var( $wpdb->prepare(
					'SELECT part_label FROM ' . AUN_SP_Install::table( 'request_items' ) . ' WHERE id = %d', $item_id
				) );
			}
			$logged = 'Asked for a new photo — ' . $reasons[ $reason ]
				. ( '' !== $part_label ? ' (' . $part_label . ')' : '' )
				. ( '' !== $note ? ': ' . $note : '' );
			$this->log( $id, $item_id, 'photo_request', $logged, $reason );

			$extra = $this->sms_customer( $id, 'photo', 'waiting_customer', '' );
			return '<div class="notice notice-success is-dismissible"><p>Set to &ldquo;Waiting on customer&rdquo;. They have been told: <em>'
				. esc_html( $reasons[ $reason ] ) . '</em>' . $extra . '</p></div>';
		}

		return '';
	}

	/**
	 * The quote total = Σ (unit price × qty). unit_price is PER PIECE, so the qty
	 * multiplier is what stops a 3-piece order being charged as one.
	 *
	 * Defensive fallback: if the qty column is missing (a site that hasn't run the
	 * v0.17 migration yet) the multiplied query errors and returns NULL, which would
	 * silently make every quote ৳0 and refuse to send. In that case fall back to the
	 * un-multiplied sum rather than pricing the job at nothing.
	 */
	/**
	 * @param string $scope 'billing' — what the customer owes RIGHT NOW: parts we
	 *                      can't supply and parts that aren't going ahead are out.
	 *                      'quote'   — what a NEW quote would come to: only the
	 *                      unsuppliable parts are out.
	 *
	 * The two scopes exist because a declined or expired request has every line
	 * marked 'cancelled'. Billing must ignore those (nothing is owed), but re-quoting
	 * must not: with one scope, sending a revised quote after a decline computed a
	 * total of ৳0 and refused itself — the customer could never be re-offered.
	 */
	private function quote_total( $id, $scope = 'billing' ) {
		global $wpdb;
		$t_item = AUN_SP_Install::table( 'request_items' );

		$exclude  = ( 'quote' === $scope ) ? array( 'unavailable' ) : self::NOT_CHARGEABLE;
		$skip     = "'" . implode( "','", $exclude ) . "'";
		$suppress = $wpdb->suppress_errors( true );
		$sum      = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(unit_price * (CASE WHEN qty < 1 OR qty IS NULL THEN 1 ELSE qty END)),0)
			 FROM $t_item WHERE request_id = %d AND line_status NOT IN ($skip)",
			$id
		) );
		$wpdb->suppress_errors( $suppress );

		if ( null === $sum ) {
			$sum = $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(unit_price),0) FROM $t_item WHERE request_id = %d AND line_status NOT IN ($skip)",
				$id
			) );
		}
		return round( (float) $sum, 2 );
	}

	/**
	 * Put the request into "quote sent — awaiting approval": this is what actually
	 * gives the customer the Approve / Decline buttons on the tracking page and texts
	 * them the quote. Shared by the button and by marking a part "Quoted".
	 */
	private function do_send_quote( $id, $total, $quote_note, $was_auto ) {
		global $wpdb;
		$t_req  = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );

		// The clock starts now — including on a re-send, which is a NEW offer and
		// therefore a new deadline and a fresh reminder ladder.
		$days    = self::quote_valid_days();
		$expires = $days > 0 ? date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $days * DAY_IN_SECONDS ) : null;

		$wpdb->update(
			$t_req,
			array(
				'overall_status'   => 'quote_sent',
				'quote_total'      => $total,
				'quote_note'       => $quote_note,
				'quoted_at'        => current_time( 'mysql' ),
				'quote_expires_at' => $expires,
				'quote_reminders'  => 0,
				'approved_at'      => null,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		// Reflect the quote on the parts themselves: every priced part still sitting
		// at "Pending" moves to "Quoted" (free / in-warranty parts at ৳0 are left
		// alone). Without this the line items kept showing "Pending" after a quote.
		// 'cancelled' is included so a FRESH quote after a decline or an expiry brings
		// those lines back to life — otherwise the re-quote would leave every part
		// reading "Not going ahead" while the request sits at "awaiting approval".
		$quoted = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $t_item SET line_status = 'quoted', updated_at = %s
			 WHERE request_id = %d AND line_status IN ('pending','cancelled') AND unit_price > 0",
			current_time( 'mysql' ), $id
		) );

		// Recompute now that the lines have moved to 'quoted': the stored total is the
		// billing figure, and after the flip both scopes agree.
		$total = $this->quote_total( $id );
		$wpdb->update( $t_req, array( 'quote_total' => $total ), array( 'id' => $id ) );

		$this->log( $id, 0, 'quote_sent', 'Quote sent — total ৳' . number_format_i18n( $total, 2 )
			. ( $expires ? ', valid until ' . date_i18n( get_option( 'date_format' ), strtotime( $expires ) ) : '' )
			. ( $quoted ? ' (' . $quoted . ' part(s) marked Quoted)' : '' ) );
		$extra = $this->sms_customer( $id, 'quote', 'quote_sent', '' );

		// Real-time push to the app: this is the one status the customer has to ACT
		// on, so it must not wait for them to happen to open the app. The AUN App
		// API listens and sends an "approve or decline" notification.
		do_action( 'aun_sp_status_changed', $id, 'quote_sent', '' );

		$lead = $was_auto
			? 'Part marked <strong>Quoted</strong>, so the quote was sent for approval (৳' . esc_html( number_format_i18n( $total, 2 ) ) . ').'
			: 'Quote sent for approval (৳' . esc_html( number_format_i18n( $total, 2 ) ) . ').';

		return '<div class="notice notice-success is-dismissible"><p>' . $lead
			. ( $quoted ? ' ' . $quoted . ' other part(s) marked &ldquo;Quoted&rdquo;.' : '' )
			. ' The customer can now Approve or Decline on their tracking page.' . $extra . '</p></div>';
	}

	/* ------------------------------------------------- Quote chasing & expiry */

	/**
	 * Daily: nudge customers sitting on an unanswered quote, then expire it.
	 *
	 * A quote with no deadline and no follow-up is the one place this system used to
	 * simply stall — the request sat at "awaiting approval" for ever, and the most
	 * likely reason (the customer not realising a reply was needed at all) was never
	 * addressed. Three touches, then stop:
	 *
	 *   quote sent  →  reminder 1  →  reminder 2 (final)  →  expired
	 *
	 * Every reminder says the thing the original quote did not: nothing has been
	 * ordered and nothing will happen until they answer. Expiry is not a rejection —
	 * the customer can revive it in one tap, which is why we say so in the same text.
	 *
	 * Safe to run repeatedly: the reminder counter and the conditional UPDATE mean a
	 * double cron run (or a manual "run now") cannot double-text anyone.
	 */
	public static function process_quotes() {
		global $wpdb;
		$t_req = AUN_SP_Install::table( 'requests' );
		$days  = self::quote_valid_days();
		list( $r1, $r2 ) = self::reminder_days();

		$rows = $wpdb->get_results(
			"SELECT id, ref, quoted_at, quote_expires_at, quote_reminders, phone_current
			 FROM $t_req WHERE overall_status = 'quote_sent' AND quoted_at IS NOT NULL"
		);

		$now      = current_time( 'timestamp' );
		$reminded = 0;
		$expired  = 0;

		foreach ( (array) $rows as $r ) {
			$sent_ts = strtotime( (string) $r->quoted_at );
			if ( ! $sent_ts ) {
				continue;
			}
			$age  = (int) floor( ( $now - $sent_ts ) / DAY_IN_SECONDS );
			$done = (int) $r->quote_reminders;

			// 1. Expire first — a quote past its date must not also be nudged today.
			if ( $days > 0 ) {
				$exp_ts = $r->quote_expires_at ? strtotime( (string) $r->quote_expires_at ) : ( $sent_ts + $days * DAY_IN_SECONDS );
				if ( $exp_ts && $now >= $exp_ts ) {
					if ( self::expire_quote( (int) $r->id ) ) {
						$expired++;
					}
					continue;
				}
			}

			// 2. Otherwise, is a nudge due? Only ever one per run, never a repeat.
			$due = 0;
			if ( $done < 1 && $age >= $r1 ) {
				$due = 1;
			} elseif ( $done < 2 && $age >= $r2 ) {
				$due = 2;
			}
			if ( $due ) {
				$claimed = (int) $wpdb->query( $wpdb->prepare(
					"UPDATE $t_req SET quote_reminders = %d WHERE id = %d AND overall_status = 'quote_sent' AND quote_reminders = %d",
					$due, (int) $r->id, $done
				) );
				if ( $claimed ) {
					self::send_quote_reminder( (int) $r->id, $due );
					$reminded++;
				}
			}
		}

		return array( 'reminded' => $reminded, 'expired' => $expired );
	}

	/**
	 * Move ONE unanswered quote to 'expired'. Conditional on it still being
	 * 'quote_sent', so a customer who approves in the same minute always wins.
	 */
	public static function expire_quote( $id ) {
		global $wpdb;
		$t_req  = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );

		$claimed = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $t_req SET overall_status = 'expired', updated_at = %s WHERE id = %d AND overall_status = 'quote_sent'",
			current_time( 'mysql' ), (int) $id
		) );
		if ( ! $claimed ) {
			return false;
		}

		// Same reasoning as a decline: the parts must not keep telling the customer
		// "we order this once you approve" after the offer has lapsed.
		$wpdb->query( $wpdb->prepare(
			"UPDATE $t_item SET line_status = 'cancelled', updated_at = %s
			 WHERE request_id = %d AND line_status <> 'delivered'",
			current_time( 'mysql' ), (int) $id
		) );

		$inst = new self();
		$inst->log( $id, 0, 'quote_expired', 'Quote expired — no reply from the customer. Nothing was ordered.' );
		$inst->sms_customer( $id, 'expired', 'expired', '' );

		// An unpaid order can exist if they opened the pay page and walked away.
		if ( AUN_SP_Woo::is_active() ) {
			AUN_SP_Woo::sync_from_request( $id, 'expired' );
		}
		do_action( 'aun_sp_status_changed', $id, 'expired', '' );
		return true;
	}

	/** The nudge itself. $which is 1 (first) or 2 (final). */
	private static function send_quote_reminder( $id, $which ) {
		global $wpdb;
		// Re-read between claiming the reminder slot and actually sending: a customer
		// who approves in that window must not receive "we have NOT ordered your part
		// yet" seconds after being told we had started.
		$still = (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT overall_status FROM " . AUN_SP_Install::table( 'requests' ) . " WHERE id = %d",
			(int) $id
		) );
		if ( 'quote_sent' !== $still ) {
			return;
		}

		$inst = new self();
		$inst->log( $id, 0, 'quote_reminder', ( 2 === (int) $which ? 'Final reminder' : 'Reminder' ) . ' sent — quote still unanswered' );
		$inst->sms_customer( $id, ( 2 === (int) $which ? 'remind_final' : 'remind' ), 'quote_sent', '' );
		do_action( 'aun_sp_quote_reminder', $id, $which );
	}

	/**
	 * The customer asks us to quote again — "I still want this part" on an expired
	 * quote, or "I changed my mind" on one they declined.
	 *
	 * Puts the request back in front of the admin as if it were new work (prices are
	 * cleared back to Pending, because the whole point of a deadline is that the old
	 * price is no longer promised).
	 *
	 * DECLINED is accepted as well as EXPIRED, and the reason is a real one: Decline
	 * is a single tap on a phone next to Approve, it is instant and irreversible, and
	 * until now the customer had no way back at all — they had to find another
	 * channel and hope. 'rejected' is NOT accepted: that is OUR decision (we cannot
	 * supply the part), and a customer must not be able to overturn it.
	 *
	 * @return array{ok:bool,code:string,message:string}
	 */
	public static function revive_quote( $id, $source = 'web' ) {
		global $wpdb;
		$t_req  = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );
		$id     = (int) $id;

		$was = (string) $wpdb->get_var( $wpdb->prepare( "SELECT overall_status FROM $t_req WHERE id = %d", $id ) );

		$claimed = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $t_req SET overall_status = 'submitted', quoted_at = NULL, quote_expires_at = NULL,
			 quote_reminders = 0, updated_at = %s WHERE id = %d AND overall_status IN ('expired','declined')",
			current_time( 'mysql' ), $id
		) );
		if ( ! $claimed ) {
			return array( 'ok' => false, 'code' => 'not_expired', 'message' => AUN_SP_I18N::msg( 'srv_revive_no' ) );
		}

		// Back to Pending: the old figure was a quote, not a standing price.
		$wpdb->query( $wpdb->prepare(
			"UPDATE $t_item SET line_status = 'pending', updated_at = %s
			 WHERE request_id = %d AND line_status IN ('quoted','cancelled')",
			current_time( 'mysql' ), $id
		) );

		$why  = ( 'declined' === $was ) ? 'after declining it' : 'after the quote expired';
		$inst = new self();
		$inst->log( $id, 0, 'quote_revived', 'Customer asked us to re-quote ' . $why . ' (' . $source . ')' );

		$r = $wpdb->get_row( $wpdb->prepare( "SELECT ref, customer_name, phone_current FROM $t_req WHERE id = %d", $id ) );
		$to = trim( (string) get_option( 'aun_sp_alert_email', get_option( 'admin_email' ) ) );
		if ( $to !== '' && $r ) {
			wp_mail(
				$to,
				'[AUN spare parts] Re-quote requested — ' . $r->ref,
				$r->customer_name . ' (' . $r->phone_current . ") still wants the parts on request " . $r->ref
					. ', ' . $why . ".\n\nRe-price the parts and send a new quote:\n"
					. admin_url( 'admin.php?page=aun-sp&request=' . $id ) . "\n"
			);
		}
		do_action( 'aun_sp_status_changed', $id, 'submitted', '' );

		return array( 'ok' => true, 'code' => 'revived', 'message' => AUN_SP_I18N::msg( 'srv_revive_ok' ) );
	}

	/* ------------------------------------------------------- Customer decision */

	/**
	 * Customer approves or declines a quote.
	 *
	 * ONE implementation shared by every channel the customer can answer on: the
	 * public tracking page (SMS link) and the AUN Care app. Keeping it here means
	 * the atomic claim, the confirmation SMS, the audit log and the admin email
	 * can never drift apart between the two — a customer who taps Approve in the
	 * app gets exactly what a customer who taps Approve on the web gets.
	 *
	 * The UPDATE is conditional on the request still being 'quote_sent', which is
	 * what makes this safe against a double-tap, two devices, or the customer
	 * answering by web and app at the same moment: only the first call claims it.
	 *
	 * @param int    $request_id Request row id.
	 * @param string $decision   'approve' | 'decline'.
	 * @param string $source     Where it came from, for the audit log ('web'|'app').
	 * @return array{ok:bool,code:string,message:string}
	 */
	public static function customer_decision( $request_id, $decision, $source = 'web' ) {
		global $wpdb;

		$request_id = (int) $request_id;
		if ( ! in_array( $decision, array( 'approve', 'decline' ), true ) ) {
			return array( 'ok' => false, 'code' => 'invalid', 'message' => AUN_SP_I18N::msg( 'srv_invalid' ) );
		}

		$t_req = AUN_SP_Install::table( 'requests' );
		$req   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE id = %d LIMIT 1", $request_id ) );
		if ( ! $req ) {
			return array( 'ok' => false, 'code' => 'not_found', 'message' => AUN_SP_I18N::msg( 'srv_ru_notfound' ) );
		}

		$approved = ( 'approve' === $decision );
		$status   = $approved ? 'approved' : 'declined';

		$fields = $approved
			? array( 'overall_status' => 'approved', 'approved_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) )
			: array( 'overall_status' => 'declined', 'updated_at' => current_time( 'mysql' ) );

		$set    = array();
		$values = array();
		foreach ( $fields as $col => $val ) {
			$set[]    = "$col = %s";
			$values[] = $val;
		}
		$values[] = $request_id;

		$claimed = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $t_req SET " . implode( ', ', $set ) . " WHERE id = %d AND overall_status = 'quote_sent'",
			$values
		) );
		if ( ! $claimed ) {
			// Someone (or the customer's other device) already answered this quote.
			return array( 'ok' => false, 'code' => 'already_answered', 'message' => AUN_SP_I18N::msg( 'srv_quote_gone' ) );
		}

		// Keep the PARTS consistent with the answer. A declined request used to leave
		// every line at "Price quoted", whose customer-facing explanation reads "we
		// order the part once you approve it" — directly contradicting the decline
		// they just made, on the same screen. (Same class of bug as the rejection
		// flow had before v0.8.0.)
		if ( ! $approved ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE " . AUN_SP_Install::table( 'request_items' ) . "
				 SET line_status = 'cancelled', updated_at = %s
				 WHERE request_id = %d AND line_status <> 'delivered'",
				current_time( 'mysql' ), $request_id
			) );
		}

		$wpdb->insert( AUN_SP_Install::table( 'events' ), array(
			'request_id' => $request_id,
			'type'       => $approved ? 'approved' : 'declined',
			'message'    => 'Customer ' . ( $approved ? 'approved' : 'declined' ) . ' the quote'
				. ( 'app' === $source ? ' (in the app)' : '' ),
			'by_user'    => 'customer',
			'created_at' => current_time( 'mysql' ),
		) );

		// Approving IS the commitment to buy, so raise the WooCommerce order now — the
		// customer then pays online (SSLCommerz) or picks cash on delivery on Woo's own
		// pay page. Doing it here rather than in the tracking page means the AUN Care
		// app gets exactly the same behaviour. Degrades silently to the manual payment
		// instructions when WooCommerce is absent or nothing is chargeable.
		// NOTE: approving deliberately does NOT create a WooCommerce order. Cash on
		// delivery is the default and needs no order at all; one is minted only if the
		// customer actively chooses to pay online (AUN_SP_Tracking::ajax_pay).
		if ( $approved && AUN_SP_SMS::is_configured() && $req->phone_current !== '' ) {
			$msg = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_APPROVED ), array(
				'ref'   => $req->ref,
				'pay'   => AUN_SP_Messages::pay_info(),
				'total' => number_format_i18n( (float) $req->quote_total, 2 ),
				'track' => AUN_SP_Messages::track_link( $req->ref ),
			) );
			AUN_SP_SMS::send_tracked( $request_id, $req->phone_current, $msg, 'approval confirmation' );
		}

		// A declined quote must not leave a payable order behind.
		if ( ! $approved && AUN_SP_Woo::is_active() ) {
			AUN_SP_Woo::sync_from_request( $request_id, 'declined' );
		}

		// Acknowledge a decline in writing. Approving was already confirmed by SMS;
		// declining sent NOTHING, so a customer who mis-tapped on a phone had no
		// record that it happened and no way back — the request simply went quiet.
		if ( ! $approved && AUN_SP_SMS::is_configured() && $req->phone_current !== '' ) {
			$msg = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_DECLINED ), array(
				'ref'   => $req->ref,
				'total' => number_format_i18n( (float) $req->quote_total, 2 ),
				'track' => AUN_SP_Messages::track_link( $req->ref ),
			) );
			AUN_SP_SMS::send_tracked( $request_id, $req->phone_current, $msg, 'decline confirmation' );
		}

		$to = get_option( 'aun_sp_alert_email', get_option( 'admin_email' ) );
		if ( is_email( $to ) ) {
			$subject = $approved ? 'Quote APPROVED: ' . $req->ref : 'Quote declined: ' . $req->ref;
			$body    = $req->customer_name . ' ' . ( $approved ? 'approved' : 'declined' ) . " the quote for {$req->ref}"
				. ( $approved ? ' (Tk ' . number_format( (float) $req->quote_total, 2 ) . ')' : '' )
				. ' via ' . ( 'app' === $source ? 'the AUN Care app' : 'the tracking page' ) . ".\n\n"
				. admin_url( 'admin.php?page=aun-sp&request=' . $request_id );
			wp_mail( $to, $subject, $body );
		}

		/**
		 * Lets other plugins react to a customer's answer — the AUN App API uses
		 * this to push an in-app notification confirming the decision.
		 */
		do_action( 'aun_sp_status_changed', $request_id, $status, 'quote_sent' );

		// Hand the payment link back to the caller so the page (or the app) can offer
		// "Pay now" immediately. Without this the customer approves, the buttons
		// vanish, and nothing to pay with appears until they reload the page.
		return array( 'ok' => true, 'code' => $status, 'message' => $status );
	}

	/* --------------------------------------------------------------- Notifications */

	/**
	 * SMS the customer about a status change or a rejection (Alpha SMS).
	 *
	 * @param string   $reason  rejection text, or the {changes} list for a parts update
	 * @param string[] $detail  optional full-sentence version of $reason, as {detail}
	 */
	private function sms_customer( $id, $type, $status_key, $reason, $detail = array() ) {
		global $wpdb;
		$t_req = AUN_SP_Install::table( 'requests' );
		$r     = $wpdb->get_row( $wpdb->prepare( "SELECT ref, phone_current, model, quote_total, quote_expires_at, photo_reason, photo_note FROM $t_req WHERE id = %d", $id ) );

		if ( ! $r || $r->phone_current === '' ) {
			return ' (no phone on file — SMS skipped)';
		}
		if ( ! AUN_SP_SMS::is_configured() ) {
			return ' (Alpha SMS not configured — SMS skipped)';
		}

		$vars = array( 'ref' => $r->ref, 'model' => $r->model, 'track' => AUN_SP_Messages::track_link( $r->ref ) );
		// Available to every quote-related template.
		$vars['expires'] = ! empty( $r->quote_expires_at )
			? date_i18n( get_option( 'date_format' ), strtotime( (string) $r->quote_expires_at ) )
			: '';

		if ( 'rejected' === $type ) {
			$code           = trim( (string) get_option( 'aun_sp_goodwill_coupon', '' ) );
			$vars['reason'] = $reason;
			// The goodwill line is its own editable message (it used to be an English
			// sentence baked into this file, so it could never be reworded or written
			// in Bangla). No coupon code configured = no line at all.
			$vars['coupon'] = ( '' !== $code )
				? AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_COUPON ), array( 'code' => $code ) )
				: '';
			$msg            = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_REJECT ), $vars );
		} elseif ( 'photo' === $type ) {
			// {reason} is deliberately resolved in ENGLISH, not the customer's language.
			// One Bangla character flips the whole message from GSM-7 to UCS-2, which
			// cuts a single SMS from 160 characters to 70 — so a translated reason would
			// silently double or triple the cost of every one of these. The full reason,
			// in their own language, is waiting on the tracking page the link points at.
			$pr             = self::photo_reason_text( (string) $r->photo_reason, 'en' );
			$vars['reason'] = $pr['sms'];
			$vars['note']   = (string) $r->photo_note;
			$body           = AUN_SP_Messages::drop_empty_brackets( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_PHOTO ), $vars );
			$msg            = AUN_SP_Messages::fill( $body, $vars );
		} elseif ( 'done' === $type ) {
			// Completion. {track} is available if the admin wants it, but the default
			// deliberately omits it; {phone} is the shop's contact number so the
			// customer has somewhere to go if the part is faulty.
			$vars['changes'] = $reason;
			$vars['phone']   = trim( (string) get_option( 'aun_sp_contact_phone', '' ) );
			$body            = AUN_SP_Messages::drop_empty_brackets( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_DONE ), $vars );
			$msg             = AUN_SP_Messages::fill( $body, $vars );
		} elseif ( 'quote' === $type || 'remind' === $type || 'remind_final' === $type || 'expired' === $type ) {
			$vars['total'] = number_format_i18n( (float) $r->quote_total, 2 );
			$tpl_map       = array(
				'quote'        => AUN_SP_Messages::OPT_SMS_QUOTE,
				'remind'       => AUN_SP_Messages::OPT_SMS_REMIND,
				'remind_final' => AUN_SP_Messages::OPT_SMS_REMIND2,
				'expired'      => AUN_SP_Messages::OPT_SMS_EXPIRED,
			);
			$key = $tpl_map[ $type ];
			// With no deadline configured there is no "last chance before it expires",
			// so the final reminder falls back to the ordinary reminder wording — whose
			// only mention of the date sits inside brackets we can safely drop. Sending
			// the expiry-flavoured text would read "expires on  and your part…".
			if ( 'remind_final' === $type && '' === $vars['expires'] ) {
				$key = AUN_SP_Messages::OPT_SMS_REMIND;
			}
			// Then drop any bracketed "(valid until …)" style aside around an empty
			// placeholder — matched on the brackets, not on English words, so it keeps
			// working after the admin rewords or translates the template.
			$body = AUN_SP_Messages::drop_empty_brackets( AUN_SP_Messages::sms( $key ), $vars );
			$msg  = AUN_SP_Messages::fill( $body, $vars );
		} elseif ( 'parts' === $type ) {
			// Per-part progress — the normal update. {changes} is the short form
			// ("LCD screen: Arrived at AUN, Dhaka"); {detail} adds the explanation
			// sentence for admins who prefer a fuller (longer, pricier) SMS.
			$vars['changes'] = $reason;
			$vars['detail']  = implode( ' ', (array) $detail );
			$vars['status']  = AUN_SP_I18N::ov_label( $status_key );
			$msg             = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_PARTS ), $vars );
		} else {
			$vars['status']  = AUN_SP_I18N::ov_label( $status_key );
			$vars['changes'] = $reason; // available if the admin adds {changes} to the template
			$msg             = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_STATUS ), $vars );
		}

		$labels = array(
			'rejected' => 'rejection', 'photo' => 'better-photo request', 'quote' => 'quote',
			'parts' => 'parts update', 'remind' => 'quote reminder', 'remind_final' => 'final quote reminder',
			'expired' => 'quote expiry', 'done' => 'delivery confirmation',
		);
		$label  = isset( $labels[ $type ] ) ? $labels[ $type ] : 'status update';
		$res    = AUN_SP_SMS::send_tracked( $id, $r->phone_current, $msg, $label );
		return $res['success']
			? ' SMS sent to the customer.'
			: ' (SMS not sent: ' . esc_html( $res['message'] ) . ' — an automatic retry is scheduled; see Activity below.)';
	}

	/**
	 * Daily cron: email the admin — but ONLY when something actually needs him.
	 *
	 * The old digest mailed every day whenever ANY request was non-closed, which
	 * meant weeks of daily noise while parts were legitimately at the factory
	 * (3-4 week lead time) and it flagged "older than 14 days" by CREATION date
	 * even if the request was updated yesterday. Now:
	 *   - "Needs your action" = submitted (unreviewed) / approved (order from
	 *     factory) / ready (dispatch) — these are YOUR next moves.
	 *   - in_progress and the waiting-on-customer states are shown as FYI counts
	 *     only and never trigger the email by themselves.
	 *   - "Stale" = open but no activity (updated_at) for 14+ days — catches both
	 *     forgotten requests AND customers who never answered a quote/photo ask.
	 *   - "Past ETA" = a part whose ETA has passed but still isn't Arrived —
	 *     time to chase the factory.
	 * No action + nothing stale + nothing late = NO email that day.
	 */
	public static function send_digest() {
		global $wpdb;
		$t      = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );
		$open   = self::open_sql();

		// Per-status counts of open requests.
		$counts = array();
		foreach ( (array) $wpdb->get_results( "SELECT overall_status s, COUNT(*) n FROM $t WHERE $open GROUP BY overall_status" ) as $row ) {
			$counts[ $row->s ] = (int) $row->n;
		}
		$submitted = $counts['submitted'] ?? 0;
		$approved  = $counts['approved'] ?? 0;
		$ready     = $counts['ready'] ?? 0;
		$moving    = $counts['in_progress'] ?? 0;
		$waiting   = ( $counts['quote_sent'] ?? 0 ) + ( $counts['waiting_customer'] ?? 0 );
		$action    = $submitted + $approved + $ready;

		// Timestamps are stored in site-local time (current_time) — compare in the same clock.
		$cut   = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 14 * DAY_IN_SECONDS );
		$stale = $wpdb->get_results( $wpdb->prepare(
			"SELECT ref, customer_name, overall_status, COALESCE(updated_at, created_at) AS last_touch
			 FROM $t WHERE $open AND COALESCE(updated_at, created_at) < %s
			 ORDER BY last_touch ASC LIMIT 30",
			$cut
		) );

		$late = $wpdb->get_results( $wpdb->prepare(
			"SELECT i.part_label, i.eta, r.ref FROM $t_item i
			 JOIN $t r ON r.id = i.request_id
			 WHERE " . self::open_sql( 'r.overall_status' ) . "
			   AND i.eta IS NOT NULL AND i.eta < %s
			   AND i.line_status IN ('applied','at_factory','shipped')
			 ORDER BY i.eta ASC LIMIT 30",
			current_time( 'Y-m-d' )
		) );

		// Approved but the money never arrived. With no advance required, a customer
		// can approve, never pay, and we'd still source the part — so surface it
		// rather than letting it sit silently. COD orders sit at "processing" (payment
		// is collected on delivery) and needs_payment() is false, so they're excluded.
		$unpaid = array();
		if ( AUN_SP_Woo::is_active() ) {
			$rows = $wpdb->get_results( "SELECT id, ref, customer_name, wc_order_id FROM $t WHERE wc_order_id > 0 AND $open" );
			foreach ( (array) $rows as $row ) {
				$o = wc_get_order( (int) $row->wc_order_id );
				if ( $o && $o->needs_payment() ) {
					$unpaid[] = array(
						'ref'   => $row->ref,
						'who'   => $row->customer_name,
						'num'   => $o->get_order_number(),
						'total' => number_format_i18n( (float) $o->get_total(), 2 ),
					);
				}
			}
		}

		// Quotes the customer hasn't answered. The automatic reminders handle the
		// routine chase; this list is here so a quote about to lapse can still get a
		// human WhatsApp message — which converts far better — before it does.
		$pending_quotes = $wpdb->get_results(
			"SELECT ref, customer_name, quote_total, quoted_at, quote_expires_at, quote_reminders
			 FROM $t WHERE overall_status = 'quote_sent' ORDER BY quoted_at ASC LIMIT 30"
		);
		// …but they must not TRIGGER a mail on their own, or the digest is back to
		// arriving every single day while a quote sits there being chased perfectly
		// well by the automation — exactly the noise the v0.15 rewrite removed. Only
		// a quote about to lapse (≤2 days, or already fully reminded) earns the email.
		$quotes_urgent = 0;
		foreach ( (array) $pending_quotes as $q ) {
			$left = ! empty( $q->quote_expires_at )
				? ( strtotime( (string) $q->quote_expires_at ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS
				: null;
			if ( ( null !== $left && $left <= 2 ) || (int) $q->quote_reminders >= 2 ) {
				$quotes_urgent++;
			}
		}

		if ( $action === 0 && empty( $stale ) && empty( $late ) && empty( $unpaid ) && 0 === $quotes_urgent ) {
			return; // nothing needs the admin today — no email
		}

		$to = get_option( 'aun_sp_alert_email', get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			return;
		}

		// ONE digest per day, whatever happens upstream.
		//
		// This hook can fire more than once a day: a duplicate entry in the cron
		// array, a host cron hitting wp-cron.php while WordPress's own loopback is
		// already running it, or a manual run. Nothing here is harmful when repeated
		// — but the admin got the same summary email twice, minutes apart, which is
		// just noise. The claim is written BEFORE the mail goes out, so a second run
		// finds the day already taken and stops.
		//
		// Deliberately NOT claimed on a "nothing to report" day (we return above
		// without ever reaching this), so if something does need attention later the
		// same day, that run can still send.
		$today = current_time( 'Y-m-d' );
		if ( (string) get_option( 'aun_sp_digest_sent_on', '' ) === $today ) {
			return;
		}
		update_option( 'aun_sp_digest_sent_on', $today, false );

		$ov      = self::overall_statuses();
		$lines   = array();
		$lines[] = 'Spare parts — daily summary';
		$lines[] = '';
		$lines[] = 'NEEDS YOUR ACTION (' . $action . ')';
		$lines[] = '  New, not yet reviewed:          ' . $submitted;
		$lines[] = '  Approved — order from factory:  ' . $approved;
		$lines[] = '  Ready to dispatch:              ' . $ready;
		$lines[] = '';
		$lines[] = 'In motion, no action needed: ' . $moving . ' at factory/in progress, ' . $waiting . ' waiting on customer.';

		if ( $late ) {
			$lines[] = '';
			$lines[] = 'PAST ETA — chase the factory:';
			foreach ( $late as $l ) {
				$lines[] = '  - ' . $l->part_label . ' (' . $l->ref . ') — expected ' . $l->eta;
			}
		}
		if ( $pending_quotes ) {
			$lines[] = '';
			$lines[] = 'QUOTES AWAITING A REPLY:';
			foreach ( $pending_quotes as $q ) {
				$waited = $q->quoted_at ? (int) floor( ( current_time( 'timestamp' ) - strtotime( (string) $q->quoted_at ) ) / DAY_IN_SECONDS ) : 0;
				$left   = ! empty( $q->quote_expires_at )
					? (int) ceil( ( strtotime( (string) $q->quote_expires_at ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS )
					: null;
				$lines[] = '  - ' . $q->ref . ' (' . $q->customer_name . ') — Tk ' . number_format_i18n( (float) $q->quote_total, 2 )
					. ', waiting ' . $waited . 'd'
					. ( (int) $q->quote_reminders > 0 ? ', ' . (int) $q->quote_reminders . ' reminder(s) sent' : '' )
					. ( null !== $left ? ( $left > 0 ? ', expires in ' . $left . 'd' : ', EXPIRING TODAY' ) : '' );
			}
			$lines[] = '  (reminders are automatic — a WhatsApp message converts better if one is about to lapse)';
		}
		if ( $unpaid ) {
			$lines[] = '';
			$lines[] = 'APPROVED BUT NOT PAID:';
			foreach ( $unpaid as $u ) {
				$lines[] = '  - ' . $u['ref'] . ' (' . $u['who'] . ') — order #' . $u['num'] . ', Tk ' . $u['total'] . ' outstanding';
			}
		}
		if ( $stale ) {
			$lines[] = '';
			$lines[] = 'NO ACTIVITY FOR 14+ DAYS:';
			foreach ( $stale as $o ) {
				$lines[] = '  - ' . $o->ref . ' (' . $o->customer_name . ') — ' . ( $ov[ $o->overall_status ] ?? $o->overall_status ) . ', last update ' . substr( (string) $o->last_touch, 0, 10 );
			}
		}
		$lines[] = '';
		$lines[] = 'Open the dashboard: ' . admin_url( 'admin.php?page=aun-sp' );

		$subject = 'AUN spare parts: ' . $action . ' need action';
		if ( $late ) {
			$subject .= ', ' . count( (array) $late ) . ' past ETA';
		}
		if ( $stale ) {
			$subject .= ', ' . count( (array) $stale ) . ' stale';
		}
		if ( $unpaid ) {
			$subject .= ', ' . count( $unpaid ) . ' unpaid';
		}
		if ( $quotes_urgent ) {
			$subject .= ', ' . $quotes_urgent . ' quote(s) about to lapse';
		}
		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	/* --------------------------------------------------------------------- Helpers */

	/**
	 * PURE overall-status calculation from a request's part statuses. No DB — takes
	 * the list of line_status strings, the current overall, and whether the request
	 * was ever approved. Shared by derive_overall() (single request, on save) and the
	 * dashboard's batch self-heal, so the list badge, the detail heading and the
	 * stored value can never disagree about how the same parts map to an overall.
	 *
	 * States that wait on the customer or are final are left untouched (they move
	 * only via their own actions: quote, approval, photo, reject/decline).
	 */
	public static function compute_overall( array $line_statuses, $current, $has_approved ) {
		$started_states = array( 'applied', 'at_factory', 'shipped', 'arrived', 'dispatched', 'delivered' );

		if ( in_array( $current, array( 'quote_sent', 'waiting_customer', 'declined', 'rejected', 'expired' ), true ) ) {
			// One exception to "a state waiting on the customer is sticky": once a part
			// has actually been ordered, the admin has decided to proceed regardless of
			// the quote, so let the status follow the work. Without this a request whose
			// customer never answers stays "awaiting approval" for ever, even after the
			// parts ship. Declined/rejected/waiting-on-photo stay sticky.
			if ( 'quote_sent' !== $current || ! array_intersect( $line_statuses, $started_states ) ) {
				return $current;
			}
		}
		$all = count( $line_statuses );
		if ( $all === 0 ) {
			return $current;
		}

		$done = 0;
		$delivered = 0;
		$at_least_arrived = 0;
		$started = 0;
		// 'cancelled' counts exactly like 'unavailable': the part is resolved and will
		// never move again, we simply aren't supplying it. Leaving it out would strand
		// a mixed request (one part delivered, one cancelled) at "In progress" for
		// ever, because it could never reach "all parts resolved".
		foreach ( $line_statuses as $s ) {
			if ( in_array( $s, array( 'delivered', 'unavailable', 'cancelled' ), true ) ) {
				$done++;
			}
			if ( 'delivered' === $s ) {
				$delivered++;
			}
			if ( in_array( $s, array( 'arrived', 'dispatched', 'delivered', 'unavailable', 'cancelled' ), true ) ) {
				$at_least_arrived++;
			}
			if ( in_array( $s, array( 'applied', 'at_factory', 'shipped', 'arrived', 'dispatched', 'delivered' ), true ) ) {
				$started++;
			}
		}

		if ( $done === $all ) {
			// Every part unavailable and NOTHING delivered is a rejection, not a
			// completion — the customer used to see "Completed" for a request where
			// they received nothing. (Prefer the Reject panel, which also records a
			// reason; this is the safety net for the manual-dropdown path.)
			return $delivered > 0 ? 'closed' : 'rejected';
		}
		if ( $at_least_arrived === $all ) {
			return 'ready';
		}
		if ( $started > 0 ) {
			return 'in_progress';
		}
		if ( $has_approved ) {
			return 'approved';
		}
		// Never downgrade in_progress back to "Submitted" (e.g. after a customer
		// re-upload set in_progress while all parts are still Pending) — the customer
		// would get a nonsensical "your request is now Submitted" SMS.
		return ( 'in_progress' === $current ) ? 'in_progress' : 'submitted';
	}

	/** Work out the overall status for ONE request from its parts (DB wrapper). */
	private function derive_overall( $current, $id ) {
		global $wpdb;
		$t_item   = AUN_SP_Install::table( 'request_items' );
		$statuses = $wpdb->get_col( $wpdb->prepare( "SELECT line_status FROM $t_item WHERE request_id = %d", $id ) );
		$approved = $wpdb->get_var( $wpdb->prepare( "SELECT approved_at FROM " . AUN_SP_Install::table( 'requests' ) . " WHERE id = %d", $id ) );
		return self::compute_overall( (array) $statuses, $current, ! empty( $approved ) );
	}

	private function reject_templates( $model, $purchase_date, $items, $age_yrs ) {
		$parts = array();
		foreach ( (array) $items as $it ) {
			$parts[] = $it->part_label;
		}
		$vars = array(
			'model' => $model ?: 'your projector',
			'parts' => $parts ? strtolower( implode( ', ', $parts ) ) : 'part',
			'date'  => $purchase_date,
			'age'   => $age_yrs !== null ? (int) $age_yrs : 'several',
		);

		$out = array();
		foreach ( AUN_SP_Messages::reject_templates() as $tpl ) {
			$name          = isset( $tpl['name'] ) ? $tpl['name'] : 'Reason';
			$out[ $name ]  = AUN_SP_Messages::fill( isset( $tpl['text'] ) ? $tpl['text'] : '', $vars );
		}
		return $out;
	}

	private function select( $name, $options, $current ) {
		$out = '<select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $val => $label ) {
			$out .= '<option value="' . esc_attr( $val ) . '"' . selected( $current, $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $out . '</select>';
	}

	/**
	 * One distinct colour per status — they used to collide in three places
	 * (submitted = in_progress, approved = ready, declined = rejected, and later
	 * waiting = expired), which defeats the point of colouring them at all.
	 *
	 * Colour is not the only signal: the three statuses that need YOUR move are
	 * drawn SOLID (white text on a filled pill) and everything else is tinted, so
	 * the list is readable at a glance and still works if two hues look alike on a
	 * particular screen.
	 */
	public static function status_style( $status ) {
		$map = array(
			'submitted'        => array( '#2271b1', true  ), // blue, solid   — review it
			'quote_sent'       => array( '#8250df', false ), // purple        — with the customer
			'approved'         => array( '#1a7f37', true  ), // green, solid  — order from the factory
			'in_progress'      => array( '#00838f', false ), // teal          — moving
			'ready'            => array( '#b02a8f', true  ), // magenta, solid— dispatch it
			'waiting_customer' => array( '#bf6a02', false ), // orange        — with the customer
			'expired'          => array( '#8a6d3b', false ), // brown         — lapsed, no reply
			'declined'         => array( '#b32d2e', false ), // red           — they said no
			'rejected'         => array( '#7d1b1b', false ), // dark red      — we said no
			'closed'           => array( '#646970', false ), // grey          — done
		);
		return $map[ $status ] ?? array( '#646970', false );
	}

	private function status_badge( $status ) {
		$labels        = self::overall_statuses();
		list( $c, $solid ) = self::status_style( $status );
		$l             = $labels[ $status ] ?? $status;
		$css = $solid
			? 'background:' . esc_attr( $c ) . ';color:#fff;'
			: 'background:' . esc_attr( $c ) . '1a;color:' . esc_attr( $c ) . ';';
		return '<span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap;' . $css . '">' . esc_html( $l ) . '</span>';
	}

	private function card( $label, $value ) {
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;min-width:150px;">';
		echo '<div style="font-size:13px;color:#646970;">' . esc_html( $label ) . '</div>';
		echo '<div style="font-size:24px;font-weight:600;">' . esc_html( number_format_i18n( $value ) ) . '</div></div>';
	}

	private function kv( $k, $v ) {
		echo '<tr><td style="width:150px;color:#646970;">' . esc_html( $k ) . '</td><td>' . $v . '</td></tr>';
	}

	private function age_days( $datetime ) {
		$ts = strtotime( (string) $datetime );
		return $ts ? max( 0, (int) floor( ( time() - $ts ) / DAY_IN_SECONDS ) ) : 0;
	}

	private function age_years( $date ) {
		$date = trim( (string) $date );
		if ( $date === '' || $date === '0000-00-00' ) {
			return null;
		}
		$ts = strtotime( $date );
		return $ts ? floor( ( time() - $ts ) / ( 365.25 * DAY_IN_SECONDS ) ) : null;
	}

	/**
	 * @param string $new_value optional machine-readable value for the event, kept
	 *                          alongside the human message. The customer-facing
	 *                          timeline re-words some events in their own language,
	 *                          and it can only do that from a KEY — the message is
	 *                          internal English and may name a part or quote a note.
	 */
	private function log( $request_id, $item_id, $type, $message, $new_value = '' ) {
		global $wpdb;
		$user = wp_get_current_user();
		$wpdb->insert( AUN_SP_Install::table( 'events' ), array(
			'request_id' => $request_id,
			'item_id'    => $item_id,
			'type'       => $type,
			'message'    => $message,
			'new_value'  => (string) $new_value,
			'by_user'    => $user && $user->display_name ? $user->display_name : 'admin',
			'created_at' => current_time( 'mysql' ),
		) );
	}
}
