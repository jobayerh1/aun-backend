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
	const TERMINAL_STATES = array( 'closed', 'rejected', 'declined' );

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
			'unavailable' => 'Unavailable',
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
			'rejected'         => 'Rejected',
		);
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
		$tabs = array( 'open' => 'Open', 'waiting_customer' => 'Waiting on customer', 'ready' => 'Ready', 'rejected' => 'Rejected', 'declined' => 'Declined', 'all' => 'All' );
		echo '<ul class="subsubsub">';
		$i = 0;
		foreach ( $tabs as $key => $label ) {
			$url = admin_url( 'admin.php?page=aun-sp&status=' . $key );
			$cur = $filter === $key ? ' class="current"' : '';
			echo ( $i++ ? ' | ' : '' ) . '<li><a href="' . esc_url( $url ) . '"' . $cur . '>' . esc_html( $label ) . '</a></li>';
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
			echo '<td>' . $this->status_badge( $r->overall_status ) . '</td>';
			echo '<td>' . esc_html( $age ) . 'd</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p style="color:#646970;max-width:820px;">The <strong>Request status</strong> column is the whole request in one word &mdash; every stage between ordering and arrival reads as &ldquo;In progress&rdquo;, by design. <strong>Parts &amp; where they are</strong> is the detail: each part with the exact stage it has reached.</p>';
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
		$colors = array(
			'pending'     => '#646970', 'quoted'     => '#8250df', 'applied'   => '#bf6a02',
			'at_factory'  => '#bf6a02', 'shipped'    => '#2271b1', 'arrived'   => '#1a7f37',
			'dispatched'  => '#1a7f37', 'delivered'  => '#1a7f37', 'unavailable' => '#b32d2e',
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
			$locked     = ( $paid_order && $paid_order->is_paid() );
		}
		$lock_attr = $locked ? ' readonly disabled style="background:#f0f0f1;color:#646970;"' : '';
		foreach ( (array) $items as $it ) {
			$iqty  = max( 1, (int) ( $it->qty ?? 1 ) );
			$line  = round( (float) $it->unit_price * $iqty, 2 );
			$grand += $line;
			echo '<tr>';
			echo '<td><strong>' . esc_html( $it->part_label ) . '</strong></td>';
			echo '<td><input type="number" min="1" step="1" name="item_qty[' . $it->id . ']" value="' . esc_attr( $iqty ) . '" class="small-text" style="width:58px;"' . $lock_attr . '></td>';
			echo '<td>' . $this->select( 'item_status[' . $it->id . ']', self::item_statuses(), $it->line_status ) . '</td>';
			echo '<td><input type="text" name="item_po[' . $it->id . ']" value="' . esc_attr( $it->factory_po ) . '" class="small-text"></td>';
			echo '<td><input type="date" name="item_eta[' . $it->id . ']" value="' . esc_attr( $it->eta ) . '"></td>';
			echo '<td><input type="text" name="item_note[' . $it->id . ']" value="' . esc_attr( $it->note ) . '" class="regular-text" style="width:120px;"></td>';
			echo '<td><input type="number" step="0.01" min="0" name="item_price[' . $it->id . ']" value="' . esc_attr( $it->unit_price ) . '" class="small-text" style="width:90px;"' . $lock_attr . '></td>';
			echo '<td style="white-space:nowrap;">' . ( $line > 0 ? '৳' . esc_html( number_format_i18n( $line, 2 ) ) : '<span style="color:#646970;">—</span>' ) . '</td>';
			$tno = isset( $it->tracking_no ) ? $it->tracking_no : '';
			echo '<td><input type="text" name="item_track[' . $it->id . ']" value="' . esc_attr( $tno ) . '" class="small-text" placeholder="Pathao ID / URL" style="width:120px;">';
			if ( $tno !== '' ) {
				echo ' <a href="' . esc_url( 'https://merchant.pathao.com/public-tracking?consignment_id=' . rawurlencode( $tno ) ) . '" target="_blank" rel="noopener" title="Open Pathao tracking" style="text-decoration:none;">&#8599;</a>';
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
			echo '<tr><td colspan="7" style="text-align:right;font-weight:600;">Quote total (qty × unit price)</td>'
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
			} elseif ( 'quote_sent' === $r->overall_status ) {
				$state = '<span style="color:#8250df;font-weight:600;">awaiting the customer&rsquo;s decision</span>';
			} else {
				$state = '<span style="color:#646970;font-weight:600;">no longer awaiting approval &mdash; request is &ldquo;'
					. esc_html( self::overall_statuses()[ $r->overall_status ] ?? $r->overall_status ) . '&rdquo;</span>';
			}
			echo '<p>Quote total: <strong>৳' . esc_html( number_format_i18n( (float) $r->quote_total, 2 ) ) . '</strong> · sent ' . esc_html( $r->quoted_at ) . ' · ' . $state . '</p>';
		}

		// Label the button for what it will actually do right now.
		if ( 'declined' === $r->overall_status ) {
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
		echo '<form method="post" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;max-width:820px;margin-top:16px;">';
		wp_nonce_field( 'aun_sp_photo', 'aun_sp_photo_nonce' );
		echo '<h2>Need a clearer photo?</h2>';
		echo '<p style="color:#646970;">Sets this request to <strong>Waiting on customer</strong> and texts them a link to re-upload. It flips back to <em>In progress</em> automatically once they send a new photo.</p>';
		echo '<p><button class="button">Ask customer for a better photo</button></p>';
		echo '</form>';

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

		if ( $order->is_paid() ) {
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
			if ( $owed > 0 && ! in_array( $r->overall_status, array( 'rejected', 'declined' ), true ) ) {
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
		$state = $order->is_paid()
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
				$money_locked = ( $paid_order && $paid_order->is_paid() );
			}
			$moves    = array(); // new line_status => the parts that moved to it
			$became_quoted = false; // a part was moved to "Quoted" in this save

			foreach ( $statuses as $iid => $new ) {
				$iid = (int) $iid;
				$new = array_key_exists( $new, $labels ) ? $new : 'pending';
				$cur = $wpdb->get_row( $wpdb->prepare( "SELECT line_status, part_label, qty, unit_price FROM $t_item WHERE id = %d AND request_id = %d", $iid, $id ) );
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
				// Pathao consignment: accept a raw ID or a pasted tracking URL, keep alphanumerics only.
				$track = trim( (string) wp_unslash( $tracks[ $iid ] ?? '' ) );
				if ( preg_match( '/consignment_id=([A-Za-z0-9]+)/i', $track, $tm ) ) {
					$track = $tm[1];
				}
				$track = preg_replace( '/[^A-Za-z0-9]/', '', $track );
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
				if ( $total <= 0 ) {
					return '<div class="notice notice-error is-dismissible"><p><strong>No quote was sent.</strong> Enter a price for at least one part first — a quote of ৳0 has nothing for the customer to approve.</p></div>';
				}
				return $this->do_send_quote( $id, $total, $quote_note, $auto_quote );
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
			if ( ! empty( $_POST['notify'] ) ) {
				if ( ! empty( $changes ) ) {
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
			$wpdb->update(
				$t_req,
				array( 'overall_status' => 'waiting_customer', 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $id )
			);
			$this->log( $id, 0, 'photo_request', 'Asked the customer for a better photo' );
			$extra = $this->sms_customer( $id, 'photo', 'waiting_customer', '' );
			return '<div class="notice notice-success is-dismissible"><p>Set to &ldquo;Waiting on customer&rdquo;.' . $extra . '</p></div>';
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
	private function quote_total( $id ) {
		global $wpdb;
		$t_item = AUN_SP_Install::table( 'request_items' );

		$suppress = $wpdb->suppress_errors( true );
		$sum      = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(unit_price * (CASE WHEN qty < 1 OR qty IS NULL THEN 1 ELSE qty END)),0) FROM $t_item WHERE request_id = %d",
			$id
		) );
		$wpdb->suppress_errors( $suppress );

		if ( null === $sum ) {
			$sum = $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(unit_price),0) FROM $t_item WHERE request_id = %d", $id ) );
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

		$wpdb->update(
			$t_req,
			array(
				'overall_status' => 'quote_sent',
				'quote_total'    => $total,
				'quote_note'     => $quote_note,
				'quoted_at'      => current_time( 'mysql' ),
				'approved_at'    => null,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		// Reflect the quote on the parts themselves: every priced part still sitting
		// at "Pending" moves to "Quoted" (free / in-warranty parts at ৳0 are left
		// alone). Without this the line items kept showing "Pending" after a quote.
		$quoted = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $t_item SET line_status = 'quoted', updated_at = %s
			 WHERE request_id = %d AND line_status = 'pending' AND unit_price > 0",
			current_time( 'mysql' ), $id
		) );

		$this->log( $id, 0, 'quote_sent', 'Quote sent — total ৳' . number_format_i18n( $total, 2 ) . ( $quoted ? ' (' . $quoted . ' part(s) marked Quoted)' : '' ) );
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
		$r     = $wpdb->get_row( $wpdb->prepare( "SELECT ref, phone_current, model, quote_total FROM $t_req WHERE id = %d", $id ) );

		if ( ! $r || $r->phone_current === '' ) {
			return ' (no phone on file — SMS skipped)';
		}
		if ( ! AUN_SP_SMS::is_configured() ) {
			return ' (Alpha SMS not configured — SMS skipped)';
		}

		$vars = array( 'ref' => $r->ref, 'model' => $r->model, 'track' => AUN_SP_Messages::track_link( $r->ref ) );

		if ( 'rejected' === $type ) {
			$code           = trim( (string) get_option( 'aun_sp_goodwill_coupon', '' ) );
			$vars['reason'] = $reason;
			$vars['coupon'] = $code !== '' ? ( 'As an apology, use code ' . $code . ' for a discount on an upgrade.' ) : '';
			$msg            = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_REJECT ), $vars );
		} elseif ( 'photo' === $type ) {
			$msg = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_PHOTO ), $vars );
		} elseif ( 'quote' === $type ) {
			$vars['total'] = number_format_i18n( (float) $r->quote_total, 2 );
			$msg           = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_QUOTE ), $vars );
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

		$labels = array( 'rejected' => 'rejection', 'photo' => 'better-photo request', 'quote' => 'quote', 'parts' => 'parts update' );
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

		if ( $action === 0 && empty( $stale ) && empty( $late ) && empty( $unpaid ) ) {
			return; // nothing needs the admin today — no email
		}

		$to = get_option( 'aun_sp_alert_email', get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			return;
		}

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

		if ( in_array( $current, array( 'quote_sent', 'waiting_customer', 'declined', 'rejected' ), true ) ) {
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
		foreach ( $line_statuses as $s ) {
			if ( in_array( $s, array( 'delivered', 'unavailable' ), true ) ) {
				$done++;
			}
			if ( 'delivered' === $s ) {
				$delivered++;
			}
			if ( in_array( $s, array( 'arrived', 'dispatched', 'delivered', 'unavailable' ), true ) ) {
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

	private function status_badge( $status ) {
		$colors = array(
			'submitted' => '#2271b1', 'in_progress' => '#2271b1', 'quote_sent' => '#8250df',
			'approved' => '#1a7f37', 'waiting_customer' => '#bf6a02', 'ready' => '#1a7f37',
			'closed' => '#646970', 'declined' => '#b32d2e', 'rejected' => '#b32d2e',
		);
		$labels = self::overall_statuses();
		$c      = $colors[ $status ] ?? '#646970';
		$l      = $labels[ $status ] ?? $status;
		return '<span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;background:' . esc_attr( $c ) . '1a;color:' . esc_attr( $c ) . ';font-weight:600;">' . esc_html( $l ) . '</span>';
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

	private function log( $request_id, $item_id, $type, $message ) {
		global $wpdb;
		$user = wp_get_current_user();
		$wpdb->insert( AUN_SP_Install::table( 'events' ), array(
			'request_id' => $request_id,
			'item_id'    => $item_id,
			'type'       => $type,
			'message'    => $message,
			'by_user'    => $user && $user->display_name ? $user->display_name : 'admin',
			'created_at' => current_time( 'mysql' ),
		) );
	}
}
