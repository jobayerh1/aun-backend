<?php
/**
 * Customer-facing spare-parts request form.
 *
 * Shortcode [aun_spare_parts] renders the tool (drop it on a page at /spare-parts/).
 * Two admin-ajax endpoints back it:
 *   - aun_sp_find   : look a purchase up by phone / order / serial (ERP -> legacy)
 *   - aun_sp_submit : create the request, save proof photos, queue compression,
 *                     email the admin, and return the SP- reference.
 *
 * Assets are external files (no inline <style>/<script>), and JS reads its config
 * from data-* attributes — so nothing trips WP Rocket's inline optimisation.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AUN_SP_Form {

	public function __construct() {
		add_shortcode( 'aun_spare_parts', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_ajax_aun_sp_find',          array( $this, 'ajax_find' ) );
		add_action( 'wp_ajax_nopriv_aun_sp_find',    array( $this, 'ajax_find' ) );
		add_action( 'wp_ajax_aun_sp_submit',         array( $this, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_aun_sp_submit',  array( $this, 'ajax_submit' ) );
		// Fresh-nonce endpoint: the page (and its embedded nonce) is served from the
		// WP Rocket page cache, which can outlive the nonce's 12-24h lifetime. When a
		// call fails with bad_nonce, the JS fetches a live nonce here and retries once,
		// so a long-cached page keeps working instead of erroring "session expired".
		add_action( 'wp_ajax_aun_sp_nonce',          array( $this, 'ajax_nonce' ) );
		add_action( 'wp_ajax_nopriv_aun_sp_nonce',   array( $this, 'ajax_nonce' ) );
	}

	public function ajax_nonce() {
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'aun_sp_public' ) ) );
	}

	/**
	 * The part catalogue: which proof each part needs. `photo` is required|optional|none.
	 * Single source of truth — used to render the form AND validate the submission.
	 */
	public static function parts() {
		$out = array();
		foreach ( AUN_SP_Parts::active() as $p ) {
			$out[ $p['key'] ] = array(
				'label'     => $p['label_en'],
				'label_bn'  => isset( $p['label_bn'] ) ? $p['label_bn'] : '',
				'proof'     => $p['proof_en'],
				'proof_bn'  => isset( $p['proof_bn'] ) ? $p['proof_bn'] : '',
				'photo'     => $p['photo'],
				'ref_image' => isset( $p['ref_image'] ) ? $p['ref_image'] : '',
			);
		}
		return $out;
	}

	public function register_assets() {
		wp_register_style( 'aun-sp-form', AUN_SP_URL . 'assets/sp-form.css', array(), AUN_SP_VERSION );
		wp_register_script( 'aun-sp-form', AUN_SP_URL . 'assets/sp-form.js', array(), AUN_SP_VERSION, true );
		wp_register_script( 'aun-sp-track', AUN_SP_URL . 'assets/sp-track.js', array(), AUN_SP_VERSION, true );
	}

	/**
	 * Render one string in the language the SITE is currently showing (TranslatePress
	 * /bn/ URL, its switcher, or the WP locale — see AUN_SP_I18N::current_lang()).
	 * There is no in-plugin language toggle any more: the plugin follows the page.
	 * Both languages stay editable in Spare Parts → Translations.
	 */
	private static function t( $en, $bn ) {
		$p = AUN_SP_I18N::php_pair( $en, $bn );
		return esc_html( $p[ AUN_SP_I18N::current_lang() ] );
	}

	/** Like t(), but a {ref} token becomes the reference slot the JS fills in. */
	private static function t_ref( $en, $bn ) {
		$p = AUN_SP_I18N::php_pair( $en, $bn );
		return str_replace( '{ref}', '<strong class="aun-sp-ref"></strong>', esc_html( $p[ AUN_SP_I18N::current_lang() ] ) );
	}

	/**
	 * Largest quantity a customer may pick per part. Kept small on purpose: in
	 * practice almost every request is 1 (occasionally 2), so a short dropdown beats
	 * a free-number field. Anyone needing more is a dealer conversation — and the
	 * admin side has no ceiling, so you can always set a bigger number by hand.
	 */
	public static function max_qty() {
		return max( 1, (int) apply_filters( 'aun_sp_max_qty', 5 ) );
	}

	/** Where the "Send my projector for service" choice goes (the courier guide page). */
	private static function service_url() {
		$u = trim( (string) get_option( 'aun_sp_service_url', '' ) );
		return $u !== '' ? $u : home_url( '/send-projector/' );
	}

	/** The customer tracking page (the "Track this request" button on the done screen). */
	private static function tracking_url() {
		$u = trim( (string) get_option( 'aun_sp_tracking_url', '' ) );
		return $u !== '' ? $u : home_url( '/spare-parts-status/' );
	}

	/* ----------------------------------------------------------------- Rendering */

	public function render( $atts ) {
		wp_enqueue_style( 'aun-sp-form' );
		wp_enqueue_script( 'aun-sp-form' );

		$ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
		$nonce = wp_create_nonce( 'aun_sp_public' );
		$lang  = AUN_SP_I18N::current_lang();

		ob_start();
		?>
		<?php /* data-no-translation: the plugin already renders the active language itself,
		         so TranslatePress must not re-translate (or re-index) this subtree. */ ?>
		<div class="aun-sp lang-<?php echo esc_attr( $lang ); ?>" data-no-translation data-lang="<?php echo esc_attr( $lang ); ?>" data-ajax="<?php echo $ajax; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-track-url="<?php echo esc_url( self::tracking_url() ); ?>" data-i18n="<?php echo esc_attr( wp_json_encode( AUN_SP_I18N::js_form_payload() ) ); ?>">

			<div class="aun-sp-step aun-sp-find-step">
				<h3 class="aun-sp-h"><?php echo self::t( 'Find your purchase', 'আপনার ক্রয় খুঁজুন' ); ?></h3>
				<div class="aun-sp-tabs" role="tablist">
					<button type="button" class="aun-sp-tab is-active" data-sb="mobile"><?php echo self::t( 'Phone number', 'ফোন নম্বর' ); ?></button>
					<button type="button" class="aun-sp-tab" data-sb="order"><?php echo self::t( 'Order number', 'অর্ডার নম্বর' ); ?></button>
					<button type="button" class="aun-sp-tab" data-sb="serial"><?php echo self::t( 'Serial number', 'সিরিয়াল নম্বর' ); ?></button>
				</div>
				<div class="aun-sp-find-row">
					<input type="text" class="aun-sp-q" inputmode="tel" autocomplete="off" placeholder="01XXXXXXXXX">
					<button type="button" class="aun-sp-btn aun-sp-find-btn"><?php echo self::t( 'Find', 'খুঁজুন' ); ?></button>
				</div>
				<p class="aun-sp-msg aun-sp-find-msg" role="status"></p>
				<p class="aun-sp-escape"><a href="<?php echo esc_url( self::service_url() ); ?>"><?php echo self::t( 'Projector won’t turn on, or can’t find your order? Send it in for service →', 'প্রজেক্টর চালু হচ্ছে না, বা অর্ডার খুঁজে পাচ্ছেন না? সার্ভিসে পাঠান →' ); ?></a></p>
			</div>

			<form class="aun-sp-found" hidden>
				<div class="aun-sp-chooser" hidden>
					<span class="aun-sp-lbl"><?php echo self::t( 'You have more than one purchase — which device needs the part?', 'আপনার একাধিক ক্রয় আছে — কোন ডিভাইসের জন্য পার্টস দরকার?' ); ?></span>
					<select class="aun-sp-device"></select>
				</div>
				<div class="aun-sp-summary">
					<div class="aun-sp-found-head">
						<span class="aun-sp-check">&#10003;</span> <?php echo self::t( 'We found your purchase', 'আপনার ক্রয় পাওয়া গেছে' ); ?>
						<span class="aun-sp-source"></span>
					</div>
					<div class="aun-sp-kv">
						<div><span class="aun-sp-lbl"><?php echo self::t( 'Device model', 'ডিভাইস মডেল' ); ?></span><span class="aun-sp-v" data-f="model">—</span></div>
						<div><span class="aun-sp-lbl"><?php echo self::t( 'Purchase date', 'ক্রয়ের তারিখ' ); ?></span><span class="aun-sp-v" data-f="purchase_date">—</span></div>
						<div><span class="aun-sp-lbl"><?php echo self::t( 'Warranty', 'ওয়ারেন্টি' ); ?></span><span class="aun-sp-warranty" data-f="warranty">—</span></div>
					</div>
				</div>

				<?php /* Filled by JS when this customer already has open request(s):
				         they see it BEFORE choosing, which prevents most duplicates. */ ?>
				<div class="aun-sp-ongoing" hidden></div>

				<div class="aun-sp-fork">
					<div class="aun-sp-fork-h"><?php echo self::t( 'What would you like to do?', 'আপনি কী করতে চান?' ); ?></div>
					<div class="aun-sp-fork-cards">
						<button type="button" class="aun-sp-fork-card aun-sp-choose-parts">
							<span class="aun-sp-fork-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="7" width="10" height="10" rx="1"></rect><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"></path></svg></span>
							<span class="aun-sp-fork-title"><?php echo self::t( 'I need a spare part', 'আমার একটি পার্টস দরকার' ); ?></span>
							<span class="aun-sp-fork-sub"><?php echo self::t( 'Keep your projector — we ship you the part.', 'প্রজেক্টর আপনার কাছেই থাকবে — আমরা পার্টস পাঠিয়ে দেব।' ); ?></span>
						</button>
						<a class="aun-sp-fork-card aun-sp-choose-service" href="<?php echo esc_url( self::service_url() ); ?>">
							<span class="aun-sp-fork-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v9H3z"></path><path d="M14 9h4l3 3v3h-7"></path><circle cx="7" cy="18" r="1.6"></circle><circle cx="17" cy="18" r="1.6"></circle></svg></span>
							<span class="aun-sp-fork-title"><?php echo self::t( 'Send my projector for service', 'প্রজেক্টর সার্ভিসে পাঠান' ); ?></span>
							<span class="aun-sp-fork-sub"><?php echo self::t( 'Not sure what’s wrong? We diagnose and repair it.', 'সমস্যা বুঝতে পারছেন না? আমরা পরীক্ষা করে মেরামত করে দেব।' ); ?></span>
						</a>
					</div>
				</div>

				<div class="aun-sp-parts-panel" hidden>
				<fieldset class="aun-sp-fieldset">
					<legend><?php echo self::t( 'Delivery details', 'ডেলিভারি তথ্য' ); ?></legend>
					<div class="aun-sp-onfile">
						<div class="aun-sp-onfile-info">
							<span class="aun-sp-pin" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"></path><circle cx="12" cy="10" r="2.5"></circle></svg></span>
							<div class="aun-sp-onfile-text">
								<div class="aun-sp-onfile-title"><?php echo self::t( 'Deliver to your details on file', 'আপনার সংরক্ষিত তথ্যে ডেলিভারি হবে' ); ?></div>
								<div class="aun-sp-onfile-val"><span data-f="masked_phone">—</span> &middot; <span data-f="masked_address">—</span></div>
							</div>
						</div>
						<button type="button" class="aun-sp-change"><?php echo self::t( 'Change', 'পরিবর্তন' ); ?></button>
					</div>
					<div class="aun-sp-edit" hidden>
						<label class="aun-sp-field">
							<span class="aun-sp-lbl"><?php echo self::t( 'New phone number (leave blank to keep your current one)', 'নতুন ফোন নম্বর (বর্তমানটি রাখতে খালি রাখুন)' ); ?></span>
							<input type="text" name="current_phone" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX">
						</label>
						<label class="aun-sp-field">
							<span class="aun-sp-lbl"><?php echo self::t( 'New delivery address (leave blank to keep your current one)', 'নতুন ডেলিভারি ঠিকানা (বর্তমানটি রাখতে খালি রাখুন)' ); ?></span>
							<textarea name="current_address" rows="3" placeholder="House / road / area, city"></textarea>
						</label>
						<button type="button" class="aun-sp-cancel"><?php echo self::t( 'Use my details on file instead', 'সংরক্ষিত তথ্য ব্যবহার করুন' ); ?></button>
					</div>
					<input type="hidden" name="contact_changed" value="0">
				</fieldset>

				<fieldset class="aun-sp-fieldset">
					<legend><?php echo self::t( 'What do you need?', 'আপনার কী প্রয়োজন?' ); ?></legend>
					<div class="aun-sp-parts">
						<?php foreach ( self::parts() as $key => $p ) : ?>
							<?php
							$lbl_bn   = $p['label_bn'] !== '' ? $p['label_bn'] : $p['label'];
							$proof_bn = $p['proof_bn'] !== '' ? $p['proof_bn'] : $p['proof'];
							?>
							<div class="aun-sp-part-block">
								<?php /* The part name and its quantity share ONE row: quantity costs no
								         extra vertical space, and stays invisible until the part is ticked.
								         The <select> is a SIBLING of the checkbox's <label> (not inside it),
								         otherwise opening the dropdown would toggle the checkbox. Its own
								         wrapping <label> associates the "Qty" text with the select. */ ?>
								<div class="aun-sp-part-row">
									<label class="aun-sp-part">
										<input type="checkbox" name="parts[]" value="<?php echo esc_attr( $key ); ?>">
										<span><?php echo self::t( $p['label'], $lbl_bn ); ?></span>
									</label>
									<label class="aun-sp-qty" data-part="<?php echo esc_attr( $key ); ?>" hidden>
										<span class="aun-sp-qty-lbl"><?php echo self::t( 'Qty', 'সংখ্যা' ); ?></span>
										<select class="aun-sp-qty-in" name="qty[<?php echo esc_attr( $key ); ?>]">
											<?php for ( $n = 1; $n <= (int) self::max_qty(); $n++ ) : ?>
												<option value="<?php echo (int) $n; ?>"><?php echo (int) $n; ?></option>
											<?php endfor; ?>
										</select>
									</label>
								</div>
								<?php if ( 'none' !== $p['photo'] ) : ?>
									<div class="aun-sp-upload" data-part="<?php echo esc_attr( $key ); ?>" data-required="<?php echo 'required' === $p['photo'] ? '1' : '0'; ?>" hidden>
										<div class="aun-sp-proof-row">
											<?php if ( ! empty( $p['ref_image'] ) ) : ?>
												<a class="aun-sp-refimg" href="<?php echo esc_url( $p['ref_image'] ); ?>" data-full="<?php echo esc_url( $p['ref_image'] ); ?>" title="Example">
													<img src="<?php echo esc_url( $p['ref_image'] ); ?>" alt="example" loading="lazy">
													<span><?php echo self::t( 'See example', 'উদাহরণ দেখুন' ); ?></span>
												</a>
											<?php endif; ?>
											<span class="aun-sp-proof">
												<?php echo self::t( $p['proof'], $proof_bn ); ?>
												<?php echo 'required' === $p['photo']
													? '<em class="aun-sp-req">' . self::t( 'required', 'আবশ্যক' ) . '</em>'
													: '<em class="aun-sp-opt">' . self::t( 'optional', 'ঐচ্ছিক' ) . '</em>'; ?>
											</span>
										</div>
										<input type="file" name="photo_<?php echo esc_attr( $key ); ?>" accept="image/*">
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<button type="submit" class="aun-sp-btn aun-sp-submit-btn"><?php echo self::t( 'Submit request', 'অনুরোধ জমা দিন' ); ?></button>
				<p class="aun-sp-msg aun-sp-submit-msg" role="status"></p>
				</div><!-- /.aun-sp-parts-panel -->
			</form>

			<div class="aun-sp-done" hidden>
				<div class="aun-sp-done-check">&#10003;</div>
				<h3 class="aun-sp-h"><?php echo self::t( 'Request received', 'অনুরোধ গৃহীত হয়েছে' ); ?></h3>
				<p><?php echo self::t_ref( 'Your reference number is {ref} — please keep it.', 'আপনার রেফারেন্স নম্বর {ref} — সংরক্ষণ করুন।' ); ?></p>
				<p class="aun-sp-hint"><?php echo self::t(
					"We'll verify the details and start sourcing your part. Track it any time with your reference number — parts not in stock usually take 3–4 weeks.",
					'আমরা তথ্য যাচাই করে পার্টস সংগ্রহ শুরু করব। যেকোনো সময় রেফারেন্স নম্বর দিয়ে ট্র্যাক করতে পারবেন। স্টকে না থাকলে সাধারণত ৩–৪ সপ্তাহ লাগে।'
				); ?></p>
				<p><a class="aun-sp-btn aun-sp-track-link" href="<?php echo esc_url( self::tracking_url() ); ?>"><?php echo self::t( 'Track this request', 'এই অনুরোধ ট্র্যাক করুন' ); ?></a></p>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}

	/* -------------------------------------------------------------------- Endpoints */

	public function ajax_find() {
		$this->check_nonce();
		if ( ! $this->rate_ok( 'find', 20 ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_rate_limited' ) ), 429 );
		}

		$search_by = sanitize_text_field( wp_unslash( $_POST['search_by'] ?? 'mobile' ) );
		$query     = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
		if ( $query === '' ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_enter_query' ) ) );
		}

		$r = AUN_SP_Lookup::find_all( $search_by, $query );
		if ( empty( $r['found'] ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_not_found' ) ) );
		}

		// Privacy: never send the customer's full phone/address to the browser — a
		// masked hint is enough to confirm identity. The full values stay server-side
		// and are used on submit only if the customer doesn't change them.
		$matches = array();
		foreach ( $r['matches'] as $m ) {
			$matches[] = array(
				'source'         => $m['source'],
				'order_number'   => $m['order_number'],
				'model'          => $m['model'],
				'purchase_date'  => $this->pretty_date( $m['purchase_date'] ),
				'warranty'       => $m['warranty'],
				'masked_phone'   => $this->mask_phone( $m['phone'] ),
				'masked_address' => $this->mask_address( $m['address'] ),
				'has_address'    => $this->address_complete( $m['address'] ),
			);
		}

		// Surface anything this customer already has running, so the form can say
		// "you already have SP-… in progress" BEFORE they file another one. Same data
		// the tracking page already returns for a phone number — no new exposure.
		$ongoing = $this->open_requests( aun_sp_normalize_phone( $r['matches'][0]['phone'] ?? '' ) );

		wp_send_json_success( array( 'matches' => $matches, 'ongoing' => $ongoing ) );
	}

	/**
	 * Open (non-terminal) requests for a phone, newest first, as safe display rows.
	 * Used for the "already in progress" notice and the duplicate check on submit.
	 *
	 * @param string $phone      normalised 01XXXXXXXXX
	 * @param array  $only_parts optional part_type filter — non-empty means "return
	 *                           only requests containing at least one of these parts"
	 */
	private function open_requests( $phone, $only_parts = array() ) {
		global $wpdb;
		if ( $phone === '' ) {
			return array();
		}
		$t_req  = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );
		$open   = "overall_status NOT IN ('" . implode( "','", AUN_SP_Requests::TERMINAL_STATES ) . "')";

		// Format-tolerant match: requests filed from the Android app store the phone
		// as 8801XXXXXXXXX, the web form as 01XXXXXXXXX.
		$rows = $wpdb->get_results(
			"SELECT id, ref, overall_status, model, created_at FROM $t_req
			 WHERE " . aun_sp_phone_where( 'phone_current', $phone ) . " AND $open ORDER BY created_at DESC LIMIT 10"
		);
		if ( empty( $rows ) ) {
			return array();
		}

		$lang    = AUN_SP_I18N::req_lang();
		$catalog = self::parts();
		$out     = array();

		foreach ( $rows as $row ) {
			$items = $wpdb->get_results( $wpdb->prepare(
				"SELECT part_type, part_label, qty FROM $t_item WHERE request_id = %d ORDER BY id ASC",
				(int) $row->id
			) );

			$types  = array();
			$labels = array();
			foreach ( (array) $items as $it ) {
				$types[] = $it->part_type;
				$lbl     = $it->part_label;
				if ( 'bn' === $lang && isset( $catalog[ $it->part_type ]['label_bn'] ) && $catalog[ $it->part_type ]['label_bn'] !== '' ) {
					$lbl = $catalog[ $it->part_type ]['label_bn'];
				}
				$labels[] = $lbl . ( (int) $it->qty > 1 ? ' ×' . (int) $it->qty : '' );
			}

			if ( ! empty( $only_parts ) && ! array_intersect( $types, $only_parts ) ) {
				continue; // this open request doesn't overlap what they're asking for now
			}

			$out[] = array(
				'ref'     => $row->ref,
				'status'  => AUN_SP_I18N::ov_label( $row->overall_status, $lang ),
				'key'     => $row->overall_status,
				'parts'   => implode( ', ', $labels ),
				'types'   => $types,
				'created' => $row->created_at ? date_i18n( 'j M Y', strtotime( $row->created_at ) ) : '',
				'url'     => AUN_SP_Messages::track_link( $row->ref ),
			);
		}
		return $out;
	}

	public function ajax_submit() {
		$this->check_nonce();
		if ( ! $this->rate_ok( 'submit', 8 ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_rate_limited' ) ), 429 );
		}

		// Re-verify the purchase server-side — never trust model/warranty from the client.
		// The customer may have several purchases, so match the one they picked.
		$search_by = sanitize_text_field( wp_unslash( $_POST['search_by'] ?? '' ) );
		$query     = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
		$sel_src   = sanitize_text_field( wp_unslash( $_POST['selected_source'] ?? '' ) );
		$sel_order = sanitize_text_field( wp_unslash( $_POST['selected_order'] ?? '' ) );

		$all = AUN_SP_Lookup::find_all( $search_by, $query );
		if ( empty( $all['found'] ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_lookup_again' ) ) );
		}

		$sale = null;
		foreach ( $all['matches'] as $m ) {
			if ( $m['source'] === $sel_src && (string) $m['order_number'] === (string) $sel_order ) {
				$sale = $m;
				break;
			}
		}
		if ( null === $sale ) {
			$sale = $all['matches'][0]; // fall back to the most recent purchase
		}
		$sale['found'] = true;

		$catalog  = self::parts();
		$selected = array();
		foreach ( (array) ( $_POST['parts'] ?? array() ) as $p ) {
			$p = sanitize_key( $p );
			if ( isset( $catalog[ $p ] ) && ! in_array( $p, $selected, true ) ) {
				$selected[] = $p;
			}
		}
		if ( empty( $selected ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_choose_part' ) ) );
		}

		// Quantities: clamp to 1..max server-side — the browser's min/max is a hint,
		// not a guarantee (a hand-crafted POST could ask for 9999 LCD panels).
		$max_qty = self::max_qty();
		$posted  = (array) ( $_POST['qty'] ?? array() );
		$qty     = array();
		foreach ( $selected as $p ) {
			$n           = isset( $posted[ $p ] ) ? (int) $posted[ $p ] : 1;
			$qty[ $p ]   = max( 1, min( $max_qty, $n ) );
		}

		// Required-photo check.
		foreach ( $selected as $p ) {
			if ( 'required' === $catalog[ $p ]['photo'] && empty( $_FILES[ 'photo_' . $p ]['name'] ) ) {
				wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_photo_required', array( 'part' => $catalog[ $p ]['label'] ) ) ) );
			}
		}

		// Validate every provided photo BEFORE creating the request. Previously an
		// oversized or wrong-type file was silently skipped during upload, so a
		// "photo required" request could be created with no photo at all.
		$allowed_mimes = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' );
		foreach ( $selected as $p ) {
			$field = 'photo_' . $p;
			if ( empty( $_FILES[ $field ]['name'] ) ) {
				continue;
			}
			$err = isset( $_FILES[ $field ]['error'] ) ? (int) $_FILES[ $field ]['error'] : 0;
			if ( UPLOAD_ERR_OK !== $err || empty( $_FILES[ $field ]['tmp_name'] ) ) {
				wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_photo_failed', array( 'part' => $catalog[ $p ]['label'] ) ) ) );
			}
			if ( (int) $_FILES[ $field ]['size'] > 15 * 1024 * 1024 ) {
				wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_photo_large', array( 'part' => $catalog[ $p ]['label'] ) ) ) );
			}
			$check = wp_check_filetype_and_ext( $_FILES[ $field ]['tmp_name'], $_FILES[ $field ]['name'], $allowed_mimes );
			if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
				wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_photo_type', array( 'part' => $catalog[ $p ]['label'] ) ) ) );
			}
		}

		// Smart fill: take whatever the customer typed, and fall back to the on-file
		// value (kept server-side) for any field they left blank — so changing just the
		// address keeps their existing phone, and vice versa.
		$phone_input = trim( (string) wp_unslash( $_POST['current_phone'] ?? '' ) );
		$phone       = aun_sp_normalize_phone( $phone_input );
		// A typed phone must be a real BD mobile — otherwise the confirmation SMS and
		// every status update silently go nowhere. Blank = keep the on-file number.
		if ( $phone_input !== '' && ! preg_match( '/^01[3-9]\d{8}$/', $phone ) ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_bad_phone' ) ) );
		}
		$address = sanitize_textarea_field( wp_unslash( $_POST['current_address'] ?? '' ) );
		if ( $phone === '' ) {
			$phone = aun_sp_normalize_phone( $sale['phone'] );
		}
		if ( $address === '' ) {
			$address = (string) $sale['address'];
		}
		if ( $phone === '' || $address === '' ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_need_contact' ) ) );
		}

		// ── Duplicate guard ────────────────────────────────────────────────────────
		// Everything else is valid, so this is the last gate: if the same phone already
		// has an OPEN request containing one of these parts, don't create a second one
		// silently. Answer with the offending request(s) and let the browser ask the
		// customer to confirm. The check is server-side and authoritative — a client
		// that skips the dialog still can't create the duplicate without the flag.
		$confirmed = ! empty( $_POST['confirm_duplicate'] );
		$dupes     = $this->open_requests( $phone, $selected );
		if ( $dupes && ! $confirmed ) {
			wp_send_json_error( array(
				'code'       => 'duplicate',
				'duplicates' => $dupes,
				'message'    => AUN_SP_I18N::msg( 'srv_dup_short' ),
			) );
		}

		$request_id = $this->create_request( $sale, $phone, $address, $selected, $qty );
		if ( ! $request_id ) {
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_save_error' ) ) );
		}

		// Leave a trail when the customer knowingly filed a second request for a part
		// they already have open — the admin sees this in Activity next to the
		// "same phone" banner, and knows it was a deliberate choice, not a mis-click.
		if ( $dupes ) {
			$refs = array();
			foreach ( $dupes as $d ) {
				$refs[] = $d['ref'];
			}
			$this->log( $request_id, 'duplicate_confirmed', 'Customer was warned this overlaps ' . implode( ', ', $refs ) . ' and chose to submit anyway' );
		}

		$this->handle_uploads( $request_id, $selected );
		AUN_SP_Image::queue( $request_id );

		$ref = $this->ref_for( $request_id );
		$this->notify_admin( $request_id, $ref, $sale, $phone, $selected, $qty, $dupes );

		// Confirmation SMS to the customer with their reference + permanent link, so
		// they never lose it (and can also track by phone if they do).
		if ( AUN_SP_SMS::is_configured() && $phone !== '' ) {
			$msg = AUN_SP_Messages::fill(
				AUN_SP_Messages::sms( AUN_SP_Messages::OPT_SMS_RECEIVED ),
				array( 'ref' => $ref, 'track' => AUN_SP_Messages::track_link( $ref ) )
			);
			AUN_SP_SMS::send_tracked( $request_id, $phone, $msg, 'confirmation' );
		}

		wp_send_json_success( array( 'ref' => $ref ) );
	}

	/* --------------------------------------------------------------------- Internals */

	private function create_request( $sale, $phone, $address, $selected, $qty = array() ) {
		global $wpdb;
		$t_req  = AUN_SP_Install::table( 'requests' );
		$t_item = AUN_SP_Install::table( 'request_items' );
		$now    = current_time( 'mysql' );
		$w      = $sale['warranty'];

		// Keep the purchase-record contact details alongside the delivery ones, so the
		// admin can SEE when the customer supplied a different phone/address.
		$phone_onfile   = aun_sp_normalize_phone( (string) $sale['phone'] );
		$address_onfile = trim( (string) $sale['address'] );

		$ok = $wpdb->insert( $t_req, array(
			'ref'             => '',
			'source_type'     => $sale['source'],
			'source_order'    => $sale['order_number'],
			'model'           => $sale['model'],
			'purchase_date'   => ( $sale['purchase_date'] && $sale['purchase_date'] !== '0000-00-00' ) ? $sale['purchase_date'] : null,
			'warranty_in'     => ! empty( $w['in_warranty'] ) ? 1 : 0,
			'customer_name'   => $sale['customer_name'],
			'phone_current'   => $phone,
			'address_current' => $address,
			'phone_onfile'    => $phone_onfile,
			'address_onfile'  => $address_onfile,
			'channel'         => 'web',
			'overall_status'  => 'submitted',
			'created_at'      => $now,
			'updated_at'      => $now,
		) );
		if ( ! $ok ) {
			return 0;
		}
		$request_id = (int) $wpdb->insert_id;

		// Flag any contact change in the activity log (compared server-side — the
		// client's "changed" checkbox is never trusted).
		$changed = array();
		if ( $phone_onfile !== '' && $phone !== $phone_onfile ) {
			$changed[] = 'phone (on file: ' . $phone_onfile . ')';
		}
		if ( $address_onfile !== '' && trim( $address ) !== $address_onfile ) {
			$changed[] = 'delivery address';
		}
		if ( $changed ) {
			$this->log( $request_id, 'contact_changed', 'Customer supplied a different ' . implode( ' and ', $changed ) . ' than the purchase record' );
		}

		$ref = $this->generate_ref();
		$wpdb->update( $t_req, array( 'ref' => $ref ), array( 'id' => $request_id ) );

		$catalog = self::parts();
		foreach ( $selected as $p ) {
			$wpdb->insert( $t_item, array(
				'request_id'  => $request_id,
				'part_type'   => $p,
				'part_label'  => $catalog[ $p ]['label'],
				'qty'         => isset( $qty[ $p ] ) ? max( 1, (int) $qty[ $p ] ) : 1,
				'line_status' => 'pending',
				'created_at'  => $now,
				'updated_at'  => $now,
			) );
		}

		$this->log( $request_id, 'created', 'Request submitted via web form' );
		return $request_id;
	}

	/** Save each part's proof photo into uploads/aun-spare-parts/. */
	private function handle_uploads( $request_id, $selected ) {
		global $wpdb;
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$t_attach    = AUN_SP_Install::table( 'attachments' );
		$t_item      = AUN_SP_Install::table( 'request_items' );
		$allowed     = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' );
		$dir_filter  = function ( $dirs ) {
			$dirs['subdir'] = '/aun-spare-parts' . $dirs['subdir'];
			$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
			$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
			return $dirs;
		};

		foreach ( $selected as $p ) {
			$field = 'photo_' . $p;
			if ( empty( $_FILES[ $field ]['name'] ) ) {
				continue;
			}
			if ( (int) $_FILES[ $field ]['size'] > 15 * 1024 * 1024 ) {
				continue; // skip absurdly large files; the form also limits client-side
			}

			add_filter( 'upload_dir', $dir_filter );
			$moved = wp_handle_upload( $_FILES[ $field ], array( 'test_form' => false, 'mimes' => $allowed ) );
			remove_filter( 'upload_dir', $dir_filter );

			if ( empty( $moved['url'] ) || ! empty( $moved['error'] ) ) {
				continue;
			}

			$item_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $t_item WHERE request_id = %d AND part_type = %s LIMIT 1",
				$request_id, $p
			) );

			$wpdb->insert( $t_attach, array(
				'request_id'   => $request_id,
				'item_id'      => $item_id,
				'kind'         => $p,
				'file_url'     => esc_url_raw( $moved['url'] ),
				'bytes_before' => isset( $moved['file'] ) && is_file( $moved['file'] ) ? (int) filesize( $moved['file'] ) : 0,
				'created_at'   => current_time( 'mysql' ),
			) );
		}
	}

	private function notify_admin( $request_id, $ref, $sale, $phone, $selected, $qty = array(), $dupes = array() ) {
		$to = get_option( 'aun_sp_alert_email', get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			return;
		}
		$catalog = self::parts();
		$labels  = array();
		foreach ( $selected as $p ) {
			$n        = isset( $qty[ $p ] ) ? (int) $qty[ $p ] : 1;
			$labels[] = $catalog[ $p ]['label'] . ( $n > 1 ? ' ×' . $n : '' );
		}
		$w_label = ! empty( $sale['warranty']['in_warranty'] ) ? 'IN warranty' : 'OUT of warranty';

		$lines   = array();
		$lines[] = 'New spare-parts request: ' . $ref;
		$lines[] = '';
		$lines[] = 'Customer : ' . $sale['customer_name'];
		$lines[] = 'Phone    : ' . $phone;
		$lines[] = 'Model    : ' . $sale['model'];
		$lines[] = 'Purchase : ' . $sale['purchase_date'] . ' (' . $w_label . ')';
		$lines[] = 'Parts    : ' . implode( ', ', $labels );
		$lines[] = 'Source   : ' . strtoupper( $sale['source'] );
		if ( $dupes ) {
			$refs = array();
			foreach ( $dupes as $d ) {
				$refs[] = $d['ref'] . ' (' . $d['status'] . ')';
			}
			$lines[] = '';
			$lines[] = '!! POSSIBLE DUPLICATE — the customer was warned and continued.';
			$lines[] = '   Overlaps: ' . implode( ', ', $refs );
		}
		$lines[] = '';
		$lines[] = 'Open it: ' . admin_url( 'admin.php?page=aun-sp&request=' . (int) $request_id );

		$subject = ( $dupes ? '[possible duplicate] ' : '' ) . 'New parts request ' . $ref . ' — ' . implode( ', ', $labels );
		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	private function log( $request_id, $type, $message ) {
		global $wpdb;
		$wpdb->insert( AUN_SP_Install::table( 'events' ), array(
			'request_id' => $request_id,
			'type'       => $type,
			'message'    => $message,
			'by_user'    => 'customer',
			'created_at' => current_time( 'mysql' ),
		) );
	}

	/** A request's reference, read from storage. */
	private function ref_for( $id ) {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT ref FROM " . AUN_SP_Install::table( 'requests' ) . " WHERE id = %d", (int) $id ) );
	}

	/**
	 * A random, hard-to-guess reference: SP-YYYY-XXXXXX (6 chars from an unambiguous
	 * alphabet, no 0/O/1/I/L). Sequential numbers would let anyone enumerate other
	 * people's requests on the tracking page, so we avoid them. Retries on the (tiny)
	 * chance of a collision against the UNIQUE ref column.
	 */
	private function generate_ref() {
		global $wpdb;
		$t     = AUN_SP_Install::table( 'requests' );
		$alpha = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
		$year  = current_time( 'Y' );
		$len   = strlen( $alpha );
		for ( $tries = 0; $tries < 12; $tries++ ) {
			$code = '';
			for ( $i = 0; $i < 6; $i++ ) {
				$code .= $alpha[ random_int( 0, $len - 1 ) ];
			}
			$ref = 'SP-' . $year . '-' . $code;
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE ref = %s", $ref ) ) ) {
				return $ref;
			}
		}
		return 'SP-' . $year . '-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 6 ) );
	}

	private function pretty_date( $ymd ) {
		$ymd = trim( (string) $ymd );
		$ts  = $ymd ? strtotime( $ymd ) : false;
		return $ts ? date_i18n( 'j M Y', $ts ) : $ymd;
	}

	/** 01711561441 -> 017****1441 (keep first 3 + last 4). */
	private function mask_phone( $p ) {
		$p   = preg_replace( '/\D+/', '', (string) $p );
		$len = strlen( $p );
		if ( $len >= 7 ) {
			return substr( $p, 0, 3 ) . str_repeat( '*', $len - 7 ) . substr( $p, -4 );
		}
		return $p !== '' ? str_repeat( '*', $len ) : '';
	}

	/** Show only the area + city, hiding house/road specifics. */
	private function mask_address( $a ) {
		$a = trim( (string) $a );
		if ( $a === '' ) {
			return '';
		}
		$parts = array_values( array_filter( array_map( 'trim', explode( ',', $a ) ), 'strlen' ) );
		$n     = count( $parts );
		if ( $n <= 2 ) {
			return $a;
		}
		return '…, ' . $parts[ $n - 2 ] . ', ' . $parts[ $n - 1 ];
	}

	/**
	 * Is the on-file address complete enough to ship to? We treat anything with
	 * fewer than three parts (e.g. just "City, Country") as incomplete, so the form
	 * opens the editable fields and asks the customer for a full delivery address.
	 */
	private function address_complete( $a ) {
		$parts = array_filter( array_map( 'trim', explode( ',', (string) $a ) ), 'strlen' );
		return count( $parts ) >= 3;
	}

	private function check_nonce() {
		if ( ! wp_verify_nonce( $_POST['_nonce'] ?? '', 'aun_sp_public' ) ) {
			// code:bad_nonce tells the JS to fetch a fresh nonce and retry (see ajax_nonce).
			wp_send_json_error( array( 'message' => AUN_SP_I18N::msg( 'srv_session' ), 'code' => 'bad_nonce' ), 403 );
		}
	}

	/**
	 * Fixed-bucket rate limit per IP (mirrors the repair tracker). Keyed on the
	 * CF header AND REMOTE_ADDR together, so a direct-to-origin client can't rotate
	 * buckets by spoofing X-CF-Connecting-IP.
	 */
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
