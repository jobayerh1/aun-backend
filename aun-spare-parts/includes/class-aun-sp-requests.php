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

	public static function item_statuses() {
		return array(
			'pending'     => 'Pending',
			'quoted'      => 'Quoted',
			'applied'     => 'Applied to factory',
			'at_factory'  => 'At factory',
			'shipped'     => 'Shipped',
			'arrived'     => 'Arrived',
			'dispatched'  => 'Dispatched',
			'delivered'   => 'Delivered',
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
			// Search matches ref, phone (any format), name or source order number.
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$plike = '%' . $wpdb->esc_like( aun_sp_normalize_phone( $search ) ) . '%';
			$where .= $wpdb->prepare(
				' AND (r.ref LIKE %s OR r.customer_name LIKE %s OR r.source_order LIKE %s OR r.phone_current LIKE %s)',
				$like, $like, $like, aun_sp_normalize_phone( $search ) !== '' ? $plike : $like
			);
		}

		$rows = $wpdb->get_results(
			"SELECT r.*,
				(SELECT GROUP_CONCAT(part_label SEPARATOR ', ') FROM $t_item WHERE request_id = r.id) AS parts
			 FROM $t_req r
			 WHERE $where
			 ORDER BY r.created_at DESC
			 LIMIT 200"
		);

		// Self-heal: the stored overall_status is only recomputed when a request is
		// opened and saved, so a row's badge could lag its actual parts (e.g. legacy
		// rows, or parts advanced under older logic). Recompute each visible row from
		// its parts and, if it drifted, persist + show the corrected value — so the
		// list badge always matches the detail page and the stat cards below.
		$this->sync_rows( $rows );

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

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th>Ref</th><th>Customer</th><th>Phone</th><th>Parts</th><th>Warranty</th><th>Status</th><th>Age</th>';
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
			echo '<td>' . esc_html( $r->parts ) . '</td>';
			echo '<td>' . esc_html( $wlbl ) . '</td>';
			echo '<td>' . $this->status_badge( $r->overall_status ) . '</td>';
			echo '<td>' . esc_html( $age ) . 'd</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
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
		if ( $phone_onfile !== '' && $r->phone_current !== $phone_onfile ) {
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
		echo '<p style="color:#646970;margin-top:0;">Update each part&rsquo;s <strong>status</strong> as it moves &mdash; the customer sees this on their tracking page. Set a <strong>price</strong> on any out-of-warranty part. <em>Factory PO, ETA and Note are optional</em>, just for your own records.</p>';
		echo '<div style="overflow-x:auto;"><table class="wp-list-table widefat striped" style="min-width:820px;"><thead><tr><th>Part</th><th style="width:64px;">Qty</th><th>Status</th><th>Factory PO</th><th>ETA</th><th>Note</th><th>Unit price (৳)</th><th style="width:90px;">Line total</th><th>Courier tracking</th><th>Photo</th></tr></thead><tbody>';
		$grand = 0.0;
		foreach ( (array) $items as $it ) {
			$iqty  = max( 1, (int) ( $it->qty ?? 1 ) );
			$line  = round( (float) $it->unit_price * $iqty, 2 );
			$grand += $line;
			echo '<tr>';
			echo '<td><strong>' . esc_html( $it->part_label ) . '</strong></td>';
			echo '<td><input type="number" min="1" step="1" name="item_qty[' . $it->id . ']" value="' . esc_attr( $iqty ) . '" class="small-text" style="width:58px;"></td>';
			echo '<td>' . $this->select( 'item_status[' . $it->id . ']', self::item_statuses(), $it->line_status ) . '</td>';
			echo '<td><input type="text" name="item_po[' . $it->id . ']" value="' . esc_attr( $it->factory_po ) . '" class="small-text"></td>';
			echo '<td><input type="date" name="item_eta[' . $it->id . ']" value="' . esc_attr( $it->eta ) . '"></td>';
			echo '<td><input type="text" name="item_note[' . $it->id . ']" value="' . esc_attr( $it->note ) . '" class="regular-text" style="width:120px;"></td>';
			echo '<td><input type="number" step="0.01" min="0" name="item_price[' . $it->id . ']" value="' . esc_attr( $it->unit_price ) . '" class="small-text" style="width:90px;"></td>';
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
		if ( $r->quoted_at ) {
			$state = $r->approved_at
				? '<span style="color:#1a7f37;font-weight:600;">approved ' . esc_html( $r->approved_at ) . '</span>'
				: '<span style="color:#8250df;font-weight:600;">awaiting approval</span>';
			echo '<p>Quote total: <strong>৳' . esc_html( number_format_i18n( (float) $r->quote_total, 2 ) ) . '</strong> · sent ' . esc_html( $r->quoted_at ) . ' · ' . $state . '</p>';
		}
		echo '<p><button class="button" name="send_quote" value="1">Send quote for approval</button></p>';
		echo '</form>';

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
		$dupes = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, ref, overall_status, model, created_at
			 FROM $t_req
			 WHERE phone_current = %s AND id <> %d
			 ORDER BY created_at DESC LIMIT 10",
			$r->phone_current, (int) $r->id
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
			$changes  = array(); // human-readable per-part status changes, for the SMS
			$became_quoted = false; // a part was moved to "Quoted" in this save

			foreach ( $statuses as $iid => $new ) {
				$iid = (int) $iid;
				$new = array_key_exists( $new, $labels ) ? $new : 'pending';
				$cur = $wpdb->get_row( $wpdb->prepare( "SELECT line_status, part_label, qty FROM $t_item WHERE id = %d AND request_id = %d", $iid, $id ) );
				if ( ! $cur ) {
					continue;
				}
				// Qty: at least 1, and never silently blank out an existing value.
				$new_qty = isset( $qtys[ $iid ] ) ? max( 1, (int) $qtys[ $iid ] ) : max( 1, (int) $cur->qty );
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
						'unit_price'  => round( (float) ( $prices[ $iid ] ?? 0 ), 2 ),
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
						$changes[] = $cur->part_label . $qty_sfx . ': ' . $labels[ $new ];
					}
				}
				if ( (int) $cur->qty !== $new_qty ) {
					$this->log( $id, $iid, 'qty_change', $cur->part_label . ': quantity ' . (int) $cur->qty . ' → ' . $new_qty );
				}
			}

			$quote_note = sanitize_textarea_field( wp_unslash( $_POST['quote_note'] ?? '' ) );
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

			$extra = '';
			if ( ! empty( $_POST['notify'] ) ) {
				if ( $overall !== $current ) {
					// Overall moved (e.g. submitted → In progress): one status SMS. The
					// per-part changes are passed along so {changes} works here too.
					$extra = $this->sms_customer( $id, 'status', $overall, implode( ', ', $changes ) );
				} elseif ( ! empty( $changes ) ) {
					// Overall stayed the same (typically "In progress" for weeks) but
					// individual parts DID move — previously NO SMS went out at all here,
					// silently, despite the ticked "Text the customer" box. Now the
					// customer gets a parts-update SMS listing what changed.
					$extra = $this->sms_customer( $id, 'parts', $overall, implode( ', ', $changes ) );
				}
			}
			$moved = ( $overall !== $current ) ? ' Status is now &ldquo;' . esc_html( self::overall_statuses()[ $overall ] ?? $overall ) . '&rdquo;.' : '';
			if ( ! empty( $_POST['notify'] ) && $extra === '' && empty( $changes ) && $overall === $current ) {
				$extra = ' No status changed, so no SMS was sent.';
			}
			return '<div class="notice notice-success is-dismissible"><p>Saved.' . $moved . $extra . '</p></div>';
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

		$lead = $was_auto
			? 'Part marked <strong>Quoted</strong>, so the quote was sent for approval (৳' . esc_html( number_format_i18n( $total, 2 ) ) . ').'
			: 'Quote sent for approval (৳' . esc_html( number_format_i18n( $total, 2 ) ) . ').';

		return '<div class="notice notice-success is-dismissible"><p>' . $lead
			. ( $quoted ? ' ' . $quoted . ' other part(s) marked &ldquo;Quoted&rdquo;.' : '' )
			. ' The customer can now Approve or Decline on their tracking page.' . $extra . '</p></div>';
	}

	/* --------------------------------------------------------------- Notifications */

	/** SMS the customer about a status change or a rejection (Alpha SMS). */
	private function sms_customer( $id, $type, $status_key, $reason ) {
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
			// Per-part progress while the overall status stays put — {changes} lists
			// e.g. "LCD screen: Arrived, Motherboard: Shipped".
			$vars['changes'] = $reason;
			$vars['status']  = self::overall_statuses()[ $status_key ] ?? $status_key;
			$msg             = AUN_SP_Messages::fill( AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_PARTS ), $vars );
		} else {
			$vars['status']  = self::overall_statuses()[ $status_key ] ?? $status_key;
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

		if ( $action === 0 && empty( $stale ) && empty( $late ) ) {
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
