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
			$reqs = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $t_req WHERE phone_current = %s ORDER BY created_at DESC LIMIT 20",
				aun_sp_normalize_phone( $query )
			) );
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
			foreach ( (array) $items as $it ) {
				$cat   = AUN_SP_Parts::get( $it->part_type );
				$qty   = max( 1, (int) ( $it->qty ?? 1 ) );
				$unit  = (float) $it->unit_price;
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
			$ev       = $wpdb->get_results( $wpdb->prepare( "SELECT type, message, created_at FROM $t_event WHERE request_id = %d AND type NOT IN ('sms','contact_changed') ORDER BY id ASC LIMIT 40", $r->id ) );
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
				'quote'        => ( 'quote_sent' === $r->overall_status ) ? array(
					'total' => number_format( (float) $r->quote_total, 2 ),
					'note'  => (string) $r->quote_note,
					'pay'   => AUN_SP_Messages::pay_info(),
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
		$req   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t_req WHERE ref = %s LIMIT 1", $ref ) );
		if ( ! $req ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_ru_notfound' ) ) );
		}

		$to = get_option( 'aun_sp_alert_email', get_option( 'admin_email' ) );

		if ( 'approve' === $decision ) {
			// Conditional UPDATE = atomic claim: a double-tap (or two devices) can't
			// approve twice and trigger duplicate SMS/emails.
			$claimed = (int) $wpdb->query( $wpdb->prepare(
				"UPDATE $t_req SET overall_status = 'approved', approved_at = %s, updated_at = %s WHERE id = %d AND overall_status = 'quote_sent'",
				current_time( 'mysql' ), current_time( 'mysql' ), (int) $req->id
			) );
			if ( ! $claimed ) {
				wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_quote_gone' ) ) );
			}
			$this->log( (int) $req->id, 'approved', 'Customer approved the quote' );

			if ( AUN_SP_SMS::is_configured() && $req->phone_current !== '' ) {
				$msg = AUN_SP_Messages::fill(
					AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_APPROVED ),
					array( 'ref' => $req->ref, 'pay' => AUN_SP_Messages::pay_info(), 'track' => AUN_SP_Messages::track_link( $req->ref ) )
				);
				AUN_SP_SMS::send_tracked( (int) $req->id, $req->phone_current, $msg, 'approval confirmation' );
			}
			if ( is_email( $to ) ) {
				wp_mail( $to, 'Quote APPROVED: ' . $req->ref, $req->customer_name . " approved the quote for {$req->ref} (Tk " . number_format( (float) $req->quote_total, 2 ) . ").\n\n" . admin_url( 'admin.php?page=aun-sp&request=' . (int) $req->id ) );
			}
			wp_send_json_success( array( 'message' => 'approved' ) );
		}

		// Decline (same atomic claim as approve).
		$claimed = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $t_req SET overall_status = 'declined', updated_at = %s WHERE id = %d AND overall_status = 'quote_sent'",
			current_time( 'mysql' ), (int) $req->id
		) );
		if ( ! $claimed ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_quote_gone' ) ) );
		}
		$this->log( (int) $req->id, 'declined', 'Customer declined the quote' );
		if ( is_email( $to ) ) {
			wp_mail( $to, 'Quote declined: ' . $req->ref, $req->customer_name . " declined the quote for {$req->ref}.\n\n" . admin_url( 'admin.php?page=aun-sp&request=' . (int) $req->id ) );
		}
		wp_send_json_success( array( 'message' => 'declined' ) );
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

	private function log( $request_id, $type, $message ) {
		global $wpdb;
		$wpdb->insert( AUN_SP_Install::table( 'events' ), array(
			'request_id' => (int) $request_id,
			'type'       => $type,
			'message'    => $message,
			'by_user'    => 'customer',
			'created_at' => current_time( 'mysql' ),
		) );
	}
}
