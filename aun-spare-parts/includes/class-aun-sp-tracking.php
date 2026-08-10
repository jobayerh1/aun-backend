<?php
/**
 * Customer tracking — shortcode [aun_spare_parts_tracking].
 *
 * Track by SP- reference or phone number, see each part's status, and (if we've
 * asked for a better photo, i.e. the request is "waiting on customer") re-upload a
 * corrected photo right from the page. Shows status only — no address/PII.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Tracking {

	public function __construct() {
		add_shortcode( 'aun_spare_parts_tracking', array( $this, 'render' ) );
		add_action( 'wp_ajax_aun_sp_track',           array( $this, 'ajax_track' ) );
		add_action( 'wp_ajax_nopriv_aun_sp_track',     array( $this, 'ajax_track' ) );
		add_action( 'wp_ajax_aun_sp_reupload',         array( $this, 'ajax_reupload' ) );
		add_action( 'wp_ajax_nopriv_aun_sp_reupload',  array( $this, 'ajax_reupload' ) );
		add_action( 'wp_ajax_aun_sp_approve',          array( $this, 'ajax_approve' ) );
		add_action( 'wp_ajax_nopriv_aun_sp_approve',   array( $this, 'ajax_approve' ) );
		// "Pay online" — mints the WooCommerce order on demand and hands back its pay
		// page. Not choosing this simply leaves the request as cash on delivery.
		add_action( 'wp_ajax_aun_sp_pay',             array( $this, 'ajax_pay' ) );
		add_action( 'wp_ajax_nopriv_aun_sp_pay',      array( $this, 'ajax_pay' ) );
		// "I still want this part" on an expired quote — asks us for a fresh price.
		add_action( 'wp_ajax_aun_sp_revive',          array( $this, 'ajax_revive' ) );
		add_action( 'wp_ajax_nopriv_aun_sp_revive',   array( $this, 'ajax_revive' ) );
	}

	/**
	 * The customer asks us to re-quote an expired quote.
	 *
	 * Rate-limited and state-claimed inside revive_quote(), so repeat taps can't spam
	 * the admin mailbox or reopen anything that isn't actually expired.
	 */
	public function ajax_revive() {
		$this->check_nonce();
		if ( ! $this->rate_ok( 'revive', 10 ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_busy' ) ), 429 );
		}

		global $wpdb;
		$ref = strtoupper( sanitize_text_field( wp_unslash( $_POST['ref'] ?? '' ) ) );
		$t   = AUN_SP_Install::table( 'requests' );
		$id  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE ref = %s LIMIT 1", $ref ) );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_ru_notfound' ) ) );
		}

		$res = AUN_SP_Requests::revive_quote( $id, 'web' );
		if ( empty( $res['ok'] ) ) {
			wp_send_json_error( array( 'message' => $res['message'], 'code' => $res['code'] ) );
		}
		wp_send_json_success( array( 'message' => $res['message'] ) );
	}

	/** Render one string in the language the SITE is showing (TranslatePress-aware —
	 *  see AUN_SP_I18N::current_lang()). Both languages stay editable in Translations. */
	private static function t( $en, $bn ) {
		$p = AUN_SP_I18N::php_pair( $en, $bn );
		return esc_html( $p[ AUN_SP_I18N::current_lang() ] );
	}

	public function render( $atts ) {
		wp_enqueue_style( 'aun-sp-form' );
		wp_enqueue_script( 'aun-sp-track' );

		$ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
		$nonce = wp_create_nonce( 'aun_sp_public' );
		$lang  = AUN_SP_I18N::current_lang();

		ob_start();
		?>
		<?php /* data-no-translation: we already emit the active language, so TranslatePress
		         must leave this subtree alone (no double-translation, no string bloat). */ ?>
		<div class="aun-sp aun-sp-track lang-<?php echo esc_attr( $lang ); ?>" data-no-translation data-lang="<?php echo esc_attr( $lang ); ?>" data-ajax="<?php echo $ajax; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-i18n="<?php echo esc_attr( wp_json_encode( AUN_SP_I18N::js_track_payload() ) ); ?>">
			<div class="aun-sp-find-step">
				<h3 class="aun-sp-h"><?php echo self::t( 'Track your spare-part request', 'আপনার পার্টস অনুরোধ ট্র্যাক করুন' ); ?></h3>
				<div class="aun-sp-tabs">
					<button type="button" class="aun-sp-ttab is-active" data-tb="ref"><?php echo self::t( 'Reference (SP-…)', 'রেফারেন্স (SP-…)' ); ?></button>
					<button type="button" class="aun-sp-ttab" data-tb="phone"><?php echo self::t( 'Phone number', 'ফোন নম্বর' ); ?></button>
				</div>
				<div class="aun-sp-find-row">
					<input type="text" class="aun-sp-tq" autocomplete="off" placeholder="SP-2026-0001">
					<button type="button" class="aun-sp-btn aun-sp-track-btn"><?php echo self::t( 'Track', 'ট্র্যাক' ); ?></button>
				</div>
				<p class="aun-sp-msg aun-sp-track-msg" role="status"></p>
			</div>
			<div class="aun-sp-track-results"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* -------------------------------------------------------------------- Endpoints */

	public function ajax_track() {
		$this->check_nonce();
		if ( ! $this->rate_ok( 'track', 30 ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_rate_limited' ) ), 429 );
		}

		global $wpdb;
		$by    = sanitize_text_field( wp_unslash( $_POST['by'] ?? 'ref' ) );
		$query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
		if ( $query === '' ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_track_enter' ) ) );
		}

		$t_req  = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );

		if ( 'phone' === $by ) {
			// Match any stored format — app-created requests hold 8801XXXXXXXXX while
			// the web form stores 01XXXXXXXXX (see aun_sp_phone_where()).
			$reqs = $wpdb->get_results(
				"SELECT * FROM $t_req WHERE " . aun_sp_phone_where( 'phone_current', $query ) . " ORDER BY created_at DESC LIMIT 20"
			);
		} else {
			$reqs = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $t_req WHERE ref = %s LIMIT 1",
				strtoupper( trim( $query ) )
			) );
		}

		if ( empty( $reqs ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_track_none' ) ) );
		}

		$t_event = AUN_SP_Install::table( 'events' );
		$ov      = AUN_SP_Requests::overall_statuses();
		$ist     = AUN_SP_Requests::item_statuses();
		$out     = array();

		foreach ( $reqs as $r ) {
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t_item WHERE request_id = %d ORDER BY id ASC", $r->id ) );
			$parts = array();
			$money = 0.0; // Σ unit × qty across the request
			foreach ( (array) $items as $it ) {
				$cat   = AUN_SP_Parts::get( $it->part_type );
				$qty   = max( 1, (int) ( $it->qty ?? 1 ) );
				$unit  = (float) $it->unit_price;
				$money += $unit * $qty;
				$parts[] = array(
					'label'     => $it->part_label,
					'label_bn'  => ( $cat && ! empty( $cat['label_bn'] ) ) ? $cat['label_bn'] : $it->part_label,
					'qty'       => $qty,
					'status'    => $ist[ $it->line_status ] ?? $it->line_status,
					'key'       => $it->line_status,
					'eta'       => ( $it->eta && $it->eta !== '0000-00-00' ) ? date_i18n( 'j M Y', strtotime( $it->eta ) ) : '',
					'price'     => number_format( $unit, 2 ),              // unit price
					'line_total' => number_format( $unit * $qty, 2 ),      // unit × qty
					'ref_image' => ( $cat && ! empty( $cat['ref_image'] ) ) ? $cat['ref_image'] : '',
					'tracking_no'  => isset( $it->tracking_no ) ? $it->tracking_no : '',
					'tracking_url' => ( ! empty( $it->tracking_no ) ) ? 'https://merchant.pathao.com/public-tracking?consignment_id=' . rawurlencode( $it->tracking_no ) : '',
				);
			}
			// 'quote_reminder' is excluded like 'sms': it records that WE chased THEM,
			// which reads as nagging on the customer's own progress list (and tells them
			// nothing about the parts). The expiry and the re-quote request do show.
			$ev       = $wpdb->get_results( $wpdb->prepare( "SELECT type, message, created_at FROM $t_event WHERE request_id = %d AND type NOT IN ('sms','contact_changed','quote_reminder') ORDER BY id ASC LIMIT 40", $r->id ) );
			$timeline = array();
			foreach ( (array) $ev as $e ) {
				$timeline[] = array(
					'date' => $e->created_at ? date_i18n( 'j M Y, g:i a', strtotime( $e->created_at ) ) : '',
					'text' => $e->message,
				);
			}

			$out[] = array(
				'ref'          => $r->ref,
				'model'        => $r->model,
				'status'       => $ov[ $r->overall_status ] ?? $r->overall_status,
				'status_key'   => $r->overall_status,
				'created'      => $r->created_at ? date_i18n( 'j M Y', strtotime( $r->created_at ) ) : '',
				'waiting'      => ( 'waiting_customer' === $r->overall_status ),
				'rejected'     => ( 'rejected' === $r->overall_status ),
				'reason'       => 'rejected' === $r->overall_status ? (string) $r->admin_note : '',
				// Money the customer owes. Shown whenever ANY part carries a price —
				// not only while a quote awaits approval, which used to mean the amount
				// (and the payment instructions) disappeared the moment they approved,
				// and never appeared at all if the admin priced the parts and skipped
				// the approval step. Approve / Decline still only appear when we are
				// actually waiting on their decision ('awaiting').
				// Total is summed from the same lines shown above, so it can never
				// disagree with them. A ৳0 request (in warranty / free) shows nothing.
				// Live WooCommerce order, only if they already chose to pay online.
				'order'        => AUN_SP_Woo::customer_summary( (int) $r->id ),
				// Whether to offer "Pay online" at all: something is owed, the request
				// is live, and WooCommerce is available. Cash on delivery is simply
				// what happens when they don't take this option.
				'can_pay'      => ( $money > 0 && AUN_SP_Woo::is_active()
					&& ! in_array( $r->overall_status, array( 'rejected', 'declined', 'expired' ), true ) ),
				// An unanswered quote carries a deadline: the customer needs to see it
				// next to the Approve button, not only in the SMS they may have lost.
				'expires'      => ( 'quote_sent' === $r->overall_status && ! empty( $r->quote_expires_at ) )
					? date_i18n( 'j M Y', strtotime( (string) $r->quote_expires_at ) ) : '',
				'days_left'    => ( 'quote_sent' === $r->overall_status && ! empty( $r->quote_expires_at ) )
					? max( 0, (int) ceil( ( strtotime( (string) $r->quote_expires_at ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS ) ) : null,
				// Expired = they never answered (NOT declined). One tap puts it back in
				// front of us for a fresh price, which is the whole point of expiring
				// rather than rejecting.
				'expired'      => ( 'expired' === $r->overall_status ),
				// Declined = they said no. Also recoverable, because Decline is one tap
				// away from Approve on a phone and was previously irreversible.
				'declined'     => ( 'declined' === $r->overall_status ),
				// Delivery is charged on the order, so it must appear here too —
				// otherwise the block totals ৳3,400 while the Pay button says ৳3,520.
				'delivery'     => ( isset( $r->delivery_charge ) && (float) $r->delivery_charge > 0 ) ? number_format( (float) $r->delivery_charge, 2 ) : '',
				'quote'        => ( $money > 0 ) ? array(
					'total'    => number_format( $money + ( isset( $r->delivery_charge ) ? (float) $r->delivery_charge : 0 ), 2 ),
					'note'     => (string) $r->quote_note,
					'pay'      => in_array( $r->overall_status, array( 'declined', 'rejected', 'closed', 'expired' ), true ) ? '' : AUN_SP_Messages::pay_info(),
					'awaiting' => ( 'quote_sent' === $r->overall_status ),
				) : null,
				'timeline'     => $timeline,
				'parts'        => $parts,
			);
		}

		wp_send_json_success( array( 'requests' => $out ) );
	}

	public function ajax_reupload() {
		$this->check_nonce();
		if ( ! $this->rate_ok( 'reupload', 10 ) ) {
			wp_send_json_error( array( 'message' => 'Too many uploads. Please wait a moment.' ), 429 );
		}

		global $wpdb;
		$ref = strtoupper( sanitize_text_field( wp_unslash( $_POST['ref'] ?? '' ) ) );
		if ( $ref === '' || empty( $_FILES['photo']['name'] ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_ru_choose' ) ) );
		}

		$t_req = AUN_SP_Install::table( 'requests' );
		$req   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE ref = %s LIMIT 1", $ref ) );
		if ( ! $req ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_ru_notfound' ) ) );
		}
		if ( (int) $_FILES['photo']['size'] > 15 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_ru_large' ) ) );
		}

		// Anti-abuse: only accept a re-upload when the request is actually waiting for one,
		// and claim that state atomically. This caps the customer to ONE upload per "ask for
		// a better photo" cycle — they can't keep uploading again and again (or upload to a
		// closed/rejected request), because this very first upload flips it out of "waiting"
		// and any further call finds nothing to claim. Race-safe via the conditional UPDATE.
		$claimed = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $t_req SET overall_status = 'in_progress', updated_at = %s WHERE id = %d AND overall_status = 'waiting_customer'",
			current_time( 'mysql' ), (int) $req->id
		) );
		if ( ! $claimed ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_ru_notwaiting' ) ) );
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$allowed    = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' );
		$dir_filter = function ( $dirs ) {
			$dirs['subdir'] = '/aun-spare-parts' . $dirs['subdir'];
			$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
			$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
			return $dirs;
		};
		add_filter( 'upload_dir', $dir_filter );
		$moved = wp_handle_upload( $_FILES['photo'], array( 'test_form' => false, 'mimes' => $allowed ) );
		remove_filter( 'upload_dir', $dir_filter );

		if ( empty( $moved['url'] ) || ! empty( $moved['error'] ) ) {
			// Upload failed — release the claim so the customer can try again with a valid file.
			$wpdb->update( $t_req, array( 'overall_status' => 'waiting_customer', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $req->id ) );
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_ru_badtype' ) ) );
		}

		// Status was already moved to "in progress" by the atomic claim above.
		$wpdb->insert( AUN_SP_Install::table( 'attachments' ), array(
			'request_id'   => (int) $req->id,
			'item_id'      => 0,
			'kind'         => 'reupload',
			'file_url'     => esc_url_raw( $moved['url'] ),
			'bytes_before' => isset( $moved['file'] ) && is_file( $moved['file'] ) ? (int) filesize( $moved['file'] ) : 0,
			'created_at'   => current_time( 'mysql' ),
		) );

		$wpdb->insert( AUN_SP_Install::table( 'events' ), array(
			'request_id' => (int) $req->id,
			'type'       => 'reupload',
			'message'    => 'Customer re-uploaded a photo',
			'by_user'    => 'customer',
			'created_at' => current_time( 'mysql' ),
		) );

		AUN_SP_Image::queue( (int) $req->id );

		$to = get_option( 'aun_sp_alert_email', get_option( 'admin_email' ) );
		if ( is_email( $to ) ) {
			wp_mail( $to, 'New photo on ' . $ref, $req->customer_name . " re-uploaded a photo for {$ref}.\n\n" . admin_url( 'admin.php?page=aun-sp&request=' . (int) $req->id ) );
		}

		wp_send_json_success( array( 'message' => AUN_SP_I18N::msg( 'srv_ru_ok' ) ) );
	}

	/** Customer approves or declines a quote from the tracking page. */
	public function ajax_approve() {
		$this->check_nonce();
		if ( ! $this->rate_ok( 'approve', 15 ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_rate_limited' ) ), 429 );
		}

		global $wpdb;
		$ref      = strtoupper( sanitize_text_field( wp_unslash( $_POST['ref'] ?? '' ) ) );
		$decision = sanitize_text_field( wp_unslash( $_POST['decision'] ?? '' ) );
		if ( $ref === '' || ! in_array( $decision, array( 'approve', 'decline' ), true ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_invalid' ) ) );
		}

		$t_req = AUN_SP_Install::table( 'requests' );
		$req   = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $t_req WHERE ref = %s LIMIT 1", $ref ) );
		if ( ! $req ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_ru_notfound' ) ) );
		}

		// The whole decision — atomic claim, SMS, audit log, admin email — lives in
		// AUN_SP_Requests::customer_decision() so this page and the AUN Care app
		// behave identically. See that method for why the UPDATE is conditional.
		$result = AUN_SP_Requests::customer_decision( (int) $req->id, $decision, 'web' );

		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
		// Approving offers the online-payment choice straight away (no order is
		// created until they take it — otherwise the request is cash on delivery).
		wp_send_json_success( array(
			'message'  => $result['code'],
			'can_pay'  => ( 'approved' === $result['code'] && AUN_SP_Woo::is_active() ),
		) );
	}

	/**
	 * Customer chose "Pay online". Builds (or refreshes) the WooCommerce order at
	 * THIS moment — so the amount always reflects the current parts, quantities and
	 * prices — and returns its payment page. Cash on delivery needs no order, which
	 * is why nothing is created until this is called.
	 */
	public function ajax_pay() {
		$this->check_nonce();
		if ( ! $this->rate_ok( 'pay', 15 ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_rate_limited' ) ), 429 );
		}
		if ( ! AUN_SP_Woo::is_active() ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_pay_unavailable' ) ) );
		}

		global $wpdb;
		$ref = strtoupper( sanitize_text_field( wp_unslash( $_POST['ref'] ?? '' ) ) );
		if ( $ref === '' ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_invalid' ) ) );
		}
		$t_req = AUN_SP_Install::table( 'requests' );
		$req   = $wpdb->get_row( $wpdb->prepare( "SELECT id, overall_status FROM $t_req WHERE ref = %s LIMIT 1", $ref ) );
		if ( ! $req ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_ru_notfound' ) ) );
		}
		// Nothing to pay on a request we've closed off — including an EXPIRED quote,
		// which was missing here: the pay button is hidden for it, but the customer
		// still holds the pay link we texted, and this endpoint is the real gate.
		if ( AUN_SP_Requests::is_cancelled( $req->overall_status ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_pay_unavailable' ) ) );
		}

		// Paying is a stronger "yes" than pressing Approve, so a customer who goes
		// straight to payment (e.g. from the pay link we texted) approves the quote
		// implicitly — otherwise the request would stay stuck awaiting a decision
		// even after their money arrived.
		if ( 'quote_sent' === $req->overall_status ) {
			AUN_SP_Requests::customer_decision( (int) $req->id, 'approve', 'payment' );
		}

		$order_id = AUN_SP_Woo::create_order( (int) $req->id );
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_pay_unavailable' ) ) );
		}
		$summary = AUN_SP_Woo::customer_summary( (int) $req->id );
		if ( empty( $summary['pay_url'] ) ) {
			// Already settled.
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_already_paid' ) ) );
		}
		wp_send_json_success( array( 'pay_url' => $summary['pay_url'], 'total' => $summary['total'] ) );
	}

	/* --------------------------------------------------------------------- Helpers */

	private function check_nonce() {
		if ( ! wp_verify_nonce( $_POST['_nonce'] ?? '', 'aun_sp_public' ) ) {
			// code:bad_nonce tells the JS to fetch a fresh nonce and retry — the page
			// (and its embedded nonce) may be served from a long-lived WP Rocket cache.
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_session' ), 'code' => 'bad_nonce' ), 403 );
		}
	}

	private function rate_ok( $action, $max ) {
		$ip     = ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '' ) . '|' . ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
		$bucket = floor( time() / 60 );
		$key    = 'aun_sp_rl_' . $action . '_' . md5( $ip . '_' . $bucket );
		$count  = (int) get_transient( $key );
		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, 65 );
		return true;
	}
}
