<?php
/**
 * Admin screens: dashboard, app content manager and settings.
 *
 * @package AUN_App_API
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_App_Admin {

	const CAP = 'manage_options';

	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		// Inline "Test download" check (no page reload).
		add_action( 'wp_ajax_aun_app_test_download', array( $this, 'ajax_test_download' ) );
		add_action( 'wp_ajax_aun_app_tmdb_search', array( $this, 'ajax_tmdb_search' ) );
	}

	/**
	 * AJAX: verify one content file is really downloadable by the app, so the
	 * admin gets the answer inline (no page reload, no notice at the top).
	 */
	public function ajax_test_download() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}
		check_ajax_referer( 'aun_app_dltest', 'nonce' );

		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['url'] ) ) : '';
		// Prefer the URL typed in the form (test before saving); fall back to the
		// stored row.
		if ( '' === $url && $id > 0 ) {
			$row = AUN_App_Content::get( $id );
			$url = $row ? (string) $row->url : '';
		}
		$result = AUN_App_Content::verify_download( $url );
		wp_send_json_success( $result );
	}

	/**
	 * Exactly what the in-app Projector Planner can see, and where each figure
	 * came from.
	 *
	 * Without this the planner fails silently in a way that looks like broken
	 * code but is actually missing product data: every projector with no stated
	 * throw ratio falls back to the same 1.35 default, so every model behaves
	 * identically and the picker appears to do nothing. This makes that visible
	 * and says which product to fix.
	 */
	private function planner_diagnostics() {
		if ( ! class_exists( 'AUN_App_Projectors' ) ) {
			return;
		}

		echo '<div class="aun-card">';
		echo '<h2><span class="dashicons dashicons-visibility"></span> Projector Planner</h2>';

		if ( ! AUN_App_Projectors::available() ) {
			echo '<p class="description">WooCommerce is not active, so the planner has no catalogue to show.</p></div>';
			return;
		}

		$cats      = implode( ', ', AUN_App_Projectors::categories() );
		$catalogue = AUN_App_Projectors::catalogue( true );

		echo '<p class="description" style="max-width:820px">These are the ONLY products the app offers in the planner: category <code>'
			. esc_html( $cats ) . '</code>, minus anything marked discontinued. '
			. 'A projector with no stated throw ratio silently falls back to 1.35, which makes it behave exactly like every other guessed model — fill those in on the product page.</p>';

		if ( empty( $catalogue ) ) {
			echo '<p style="color:#b32d2e;font-weight:600;margin-top:12px">No products matched. '
				. 'Check that the category slug above is right — the projector catalogue is at /projector-price/, so the slug should be <code>projector-price</code>.</p></div>';
			return;
		}

		$guessed  = 0;
		$unmapped = 0;
		echo '<table class="widefat striped" style="margin-top:12px"><thead><tr>'
			. '<th>Projector</th><th>SKU</th><th>Throw ratio</th><th>Source</th><th>Min / Max screen</th><th>Aspect</th>'
			. '</tr></thead><tbody>';

		foreach ( $catalogue as $p ) {
			$source = (string) ( $p['sources']['throw_ratio'] ?? 'default' );
			$is_guess = ! $p['exact'];
			if ( $is_guess ) {
				$guessed++;
			}
			$label = array(
				'meta'      => '<span style="color:#00794c;font-weight:600">Set on product</span>',
				'extracted' => '<span style="color:#996800;font-weight:600">Read from description</span>',
				'default'   => '<span style="color:#b32d2e;font-weight:700">GUESSED — please set</span>',
			);
			// The SKU is what links a registered device to this product. An ERP
			// product ID is only a manual override for anything the SKU cannot
			// reach, so a product with a SKU is fully mapped.
			$sku = trim( (string) ( $p['sku'] ?? '' ) );
			$erp = (int) ( $p['erp_id'] ?? 0 );
			if ( '' === $sku && $erp < 1 ) {
				$unmapped++;
			}

			echo '<tr>'
				. '<td><strong>' . esc_html( $p['name'] ) . '</strong></td>'
				. '<td>' . ( '' !== $sku
					? '<code>' . esc_html( $sku ) . '</code>'
					: ( $erp > 0
						? 'ERP #' . (int) $erp
						: '<span style="color:#b32d2e;font-weight:700">no SKU</span>' ) ) . '</td>'
				. '<td>' . esc_html( number_format( (float) $p['throw_ratio'], 2 ) ) . ':1</td>'
				. '<td>' . ( $label[ $source ] ?? esc_html( $source ) ) . '</td>'
				. '<td>' . ( $p['min_screen'] ? (int) $p['min_screen'] . '"' : '—' ) . ' / '
					. ( $p['max_screen'] ? (int) $p['max_screen'] . '"' : '—' ) . '</td>'
				. '<td>' . esc_html( $p['aspect'] ) . '</td>'
				. '</tr>';
		}
		echo '</tbody></table>';

		echo '<p class="description" style="margin-top:10px">'
			. esc_html( count( $catalogue ) ) . ' projector(s) in the planner';
		if ( $guessed > 0 ) {
			echo ' — <strong style="color:#b32d2e">' . (int) $guessed
				. ' still using a guessed throw ratio.</strong> Until those are set, those models all behave the same in the app.';
		} else {
			echo ' — every one has real optics data.';
		}
		echo '</p>';

		if ( $unmapped > 0 ) {
			echo '<p class="description" style="margin-top:6px;color:#b32d2e;font-weight:600">'
				. (int) $unmapped . ' projector(s) have no SKU. '
				. 'The SKU is how a registered projector is matched to its product: the dealer-stock sync '
				. 'records each serial&rsquo;s SKU, so the two line up with no extra work. Without one the planner '
				. 'falls back to guessing from the title, which picks the wrong variant as soon as two models '
				. 'share a prefix (A005 vs A005 Pro). Add the SKU on the product page, or set an ERP product ID '
				. 'in the same box as the throw ratio.</p>';
		} else {
			echo '<p class="description" style="margin-top:6px">Every projector has a SKU, so registered devices '
				. 'match their product exactly — no title guessing.</p>';
		}

		echo '<p class="description" style="margin-top:6px">Missing a max screen size caps the planner at its default 15 ft. Set “Optimal Max Screen” on the product to tighten it per model.</p>';
		echo '</div>';
	}

	/**
	 * How the referral programme is actually performing, as a settings row.
	 *
	 * Worth having in front of you whenever you change the numbers: a
	 * programme with many claims and no completed orders is being farmed, and
	 * a programme with no claims at all means nobody can find it.
	 */
	private function referral_stats() {
		global $wpdb;
		if ( ! class_exists( 'AUN_App_Referrals' ) ) {
			return;
		}
		$t = AUN_App_Referrals::claims_table();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
			return;
		}

		$rows = (array) $wpdb->get_results(
			"SELECT status, COUNT(*) n FROM $t GROUP BY status", ARRAY_A
		);
		$by = array();
		foreach ( $rows as $r ) {
			$by[ (string) $r['status'] ] = (int) $r['n'];
		}
		$pending  = $by[ AUN_App_Referrals::STATUS_PENDING ] ?? 0;
		$rewarded = $by[ AUN_App_Referrals::STATUS_REWARDED ] ?? 0;
		$revoked  = $by[ AUN_App_Referrals::STATUS_REVOKED ] ?? 0;

		if ( 0 === $pending + $rewarded + $revoked ) {
			echo '<p class="description">No referral codes have been used yet.</p>';
			return;
		}

		echo '<div class="aun-stats" style="margin:0">';
		echo '<div class="aun-stat"><span class="dashicons dashicons-yes-alt"></span>'
			. '<div class="num">' . (int) $rewarded . '</div><div class="lbl">Completed &amp; paid</div></div>';
		echo '<div class="aun-stat"><span class="dashicons dashicons-clock"></span>'
			. '<div class="num">' . (int) $pending . '</div><div class="lbl">Waiting on a first order</div></div>';
		if ( $revoked > 0 ) {
			echo '<div class="aun-stat"><span class="dashicons dashicons-dismiss"></span>'
				. '<div class="num">' . (int) $revoked . '</div><div class="lbl">Revoked (refunded or blocked)</div></div>';
		}
		echo '</div>';

		if ( $pending > 10 && 0 === $rewarded ) {
			echo '<p style="color:#b32d2e;font-weight:600;margin-top:14px">Many claims but no completed orders — worth checking whether the codes are being shared for the discount alone.</p>';
		}
	}

	/**
	 * Work out, for one real customer, whether the app will show them the
	 * referral card — and if not, exactly which condition is responsible.
	 *
	 * This mirrors the phone's own decision (ReferralSummary.face in
	 * models.dart) rather than describing it, so the two cannot drift: given
	 * the same payload it reaches the same verdict the app reaches.
	 *
	 * @param string $raw_phone Whatever the admin typed.
	 * @return array
	 */
	private function referral_diagnose( $raw_phone ) {
		$out = array( 'phone' => trim( (string) $raw_phone ), 'error' => '', 'rows' => array(), 'verdict' => '', 'why' => '' );

		if ( '' === $out['phone'] ) {
			$out['error'] = 'Enter the customer\'s mobile number.';
			return $out;
		}
		if ( ! class_exists( 'AUN_App_Phone' ) ) {
			$out['error'] = 'Phone helper missing.';
			return $out;
		}

		$canonical = AUN_App_Phone::normalize( $out['phone'] );
		if ( ! $canonical ) {
			$out['error'] = 'That is not a valid Bangladeshi mobile number.';
			return $out;
		}
		$out['phone'] = AUN_App_Referrals::display_phone( $canonical );

		$users = AUN_App_Phone::find_users( $canonical );
		if ( empty( $users ) ) {
			$out['error'] = 'No account on this site uses that number. The customer must log into the app at least once first.';
			return $out;
		}
		$uid = (int) $users[0]->ID;

		$s = AUN_App_Referrals::summary( $uid );

		// The phone's logic, applied to this payload.
		$enabled         = ! empty( $s['enabled'] );
		$can_invite      = ! empty( $s['can_invite'] );
		$can_claim       = ! empty( $s['can_claim'] );
		$code            = (string) ( $s['code'] ?? '' );
		$my_coupon       = (string) ( $s['my_coupon'] ?? '' );
		$can_show_invite = $enabled && $can_invite && '' !== $code;

		if ( ! $enabled ) {
			$face = 'hidden';
			$why  = 'The programme is not running: either it is switched off above, or the friend\'s discount is still 0, or WooCommerce is inactive.';
		} elseif ( $can_show_invite ) {
			$face = 'invite';
			$why  = 'They own a projector, so they see the invite card with their own code.';
		} elseif ( $can_claim ) {
			$face = 'claim';
			$why  = 'No projector yet, so they cannot invite — but they CAN redeem a friend\'s code, and that is what the card offers them.';
		} elseif ( '' !== $my_coupon ) {
			$face = 'coupon';
			$why  = 'They have already redeemed a code; the card shows them the coupon they were given.';
		} else {
			$face = 'hidden';
			$why  = 'Nothing to offer: they cannot invite (no projector registered), cannot claim '
				. '(this number has already used a code), and hold no unspent coupon — so there is '
				. 'genuinely nothing to put on the card. This is correct behaviour, not a fault. '
				. 'It resolves itself the moment they register a projector, which turns them into '
				. 'an inviter. If this is your own test account, claim history is per NUMBER and '
				. 'permanent by design, so testing the redeem flow again needs a different SIM.';
		}

		$out['rows'] = array(
			'Account'                  => '#' . $uid . ' — ' . $users[0]->user_login,
			'enabled (programme live)' => $enabled ? 'true' : 'false',
			'can_invite'               => $can_invite ? 'true' : 'false',
			'can_claim'                => $can_claim ? 'true' : 'false',
			'code'                     => '' !== $code ? $code : '(none)',
			'my_coupon'                => '' !== $my_coupon ? $my_coupon : '(none)',
			'Has bought before'        => AUN_App_Referrals::has_purchase_history( $uid, $canonical ) ? 'yes' : 'no',
			'Has used a code before'   => AUN_App_Referrals::has_claimed( $uid ) ? 'yes' : 'no',
		);

		// "Already claimed, but no coupon" is the one combination that used to
		// be a dead end and is still worth explaining: the customer is refused
		// a second code AND has nothing to show, which from their side looks
		// like the feature is simply broken.
		$claim = AUN_App_Referrals::claim_row( $uid, $canonical );
		if ( $claim ) {
			$out['rows']['Claim on file'] = sprintf(
				'%s — status %s, coupon %s',
				substr( (string) $claim->created_at, 0, 10 ),
				(string) $claim->status,
				'' !== (string) $claim->friend_coupon ? (string) $claim->friend_coupon : '(never issued)'
			);
			if ( (int) $claim->referred_user_id !== $uid ) {
				$out['rows']['⚠ Claim account'] = 'claimed under account #' . (int) $claim->referred_user_id
					. ' with the same number';
			}
			$cc = (string) $claim->friend_coupon;
			if ( '' !== $cc && function_exists( 'wc_get_coupon_id_by_code' ) ) {
				$cid = (int) wc_get_coupon_id_by_code( $cc );
				$out['rows']['Coupon state'] = $cid < 1
					? 'the coupon no longer exists (deleted)'
					: ( (int) ( new WC_Coupon( $cid ) )->get_usage_count() > 0
						? 'already spent'
						: 'unspent and valid' );
			}
		}
		$out['verdict'] = $face;
		$out['why']     = $why;
		return $out;
	}

	public function menu() {
		// First argument is the PAGE title (the browser tab), second is the MENU
		// label. They deliberately differ: a page titled just "Dashboard" gave a
		// browser tab identical to WordPress's own Dashboard, so the two were
		// impossible to tell apart when several tabs were open. Menu labels stay
		// short because the sidebar has little room; tab titles are qualified.
		add_menu_page( 'AUN App', 'AUN App', self::CAP, 'aun-app', array( $this, 'page_dashboard' ), 'dashicons-smartphone', 57 );
		add_submenu_page( 'aun-app', 'AUN App Dashboard', 'Dashboard', self::CAP, 'aun-app', array( $this, 'page_dashboard' ) );
		add_submenu_page( 'aun-app', 'AUN App Content', 'App Content', self::CAP, 'aun-app-content', array( $this, 'page_content' ) );
		add_submenu_page( 'aun-app', 'AUN App Repairs', 'Repairs', self::CAP, 'aun-app-repairs', array( $this, 'page_repairs' ) );
		add_submenu_page( 'aun-app', 'AUN App Bug Reports', 'Bug Reports', self::CAP, 'aun-app-feedback', array( $this, 'page_feedback' ) );
		add_submenu_page( 'aun-app', 'AUN App Messages', 'Messages', self::CAP, 'aun-app-messages', array( $this, 'page_messages' ) );
		add_submenu_page( 'aun-app', 'AUN App Usage', 'Usage', self::CAP, 'aun-app-usage', array( $this, 'page_usage' ) );
		add_submenu_page( 'aun-app', 'AUN App Settings', 'Settings', self::CAP, 'aun-app-settings', array( $this, 'page_settings' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Bug reports from the app
	 * --------------------------------------------------------------------- */

	public function page_feedback() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'No permission' );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'aun_app_feedback';

		if ( isset( $_POST['aun_fb_nonce'] ) && wp_verify_nonce( $_POST['aun_fb_nonce'], 'aun_fb_save' ) ) {
			$id     = (int) ( $_POST['fb_id'] ?? 0 );
			$action = sanitize_key( $_POST['fb_action'] ?? '' );
			if ( 'delete' === $action ) {
				$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
				echo '<div class="notice notice-success is-dismissible"><p>Report deleted.</p></div>';
			} elseif ( in_array( $action, array( 'new', 'in_progress', 'resolved' ), true ) ) {
				$wpdb->update( $table, array( 'status' => $action ), array( 'id' => $id ) );
				echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
			}
		}

		$rows = (array) $wpdb->get_results(
			"SELECT * FROM $table ORDER BY (status = 'new') DESC, created_at DESC LIMIT 200"
		);

		$badge = array(
			'new'         => 'aun-badge-amber',
			'in_progress' => 'aun-badge-blue',
			'resolved'    => 'aun-badge-green',
		);

		$this->header( 'Bug Reports', 'Problems customers reported from inside the Android app.' );
		?>
		<table class="widefat striped">
			<thead><tr>
				<th style="width:170px">Customer</th><th>Report</th>
				<th style="width:130px">App / Device</th><th style="width:210px">Status</th>
			</tr></thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="4" style="text-align:center;color:#888;padding:20px">No bug reports yet. 🎉</td></tr>
			<?php else : foreach ( $rows as $r ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $r->customer_name ); ?></strong><br>
						<a href="tel:<?php echo esc_attr( $r->phone ); ?>"><?php echo esc_html( $r->phone ); ?></a><br>
						<span style="color:#646970;font-size:12px"><?php echo esc_html( substr( $r->created_at, 0, 16 ) ); ?></span>
					</td>
					<td style="max-width:480px">
						<?php echo nl2br( esc_html( $r->message ) ); ?>
						<?php if ( ! empty( $r->image_url ) ) : ?>
							<div style="margin-top:8px">
								<a href="<?php echo esc_url( $r->image_url ); ?>" target="_blank" rel="noopener">
									<img src="<?php echo esc_url( $r->image_url ); ?>" alt="screenshot" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid #dcdcde;object-fit:cover" />
								</a>
							</div>
						<?php endif; ?>
					</td>
					<td style="font-size:12px;color:#646970">
						v<?php echo esc_html( $r->app_version ); ?><br>
						<?php echo esc_html( $r->device_info ); ?>
					</td>
					<td>
						<span class="aun-badge <?php echo esc_attr( $badge[ $r->status ] ?? 'aun-badge-grey' ); ?>">
							<?php echo esc_html( str_replace( '_', ' ', $r->status ) ); ?>
						</span>
						<form method="post" style="margin-top:6px">
							<?php wp_nonce_field( 'aun_fb_save', 'aun_fb_nonce' ); ?>
							<input type="hidden" name="fb_id" value="<?php echo (int) $r->id; ?>" />
							<?php if ( 'new' === $r->status ) : ?>
								<button class="button button-small" name="fb_action" value="in_progress">Start</button>
							<?php endif; ?>
							<?php if ( 'resolved' !== $r->status ) : ?>
								<button class="button button-primary button-small" name="fb_action" value="resolved">Resolve</button>
							<?php else : ?>
								<button class="button button-small" name="fb_action" value="new">Reopen</button>
							<?php endif; ?>
							<button class="button button-small" name="fb_action" value="delete" onclick="return confirm('Delete this report?')">Delete</button>
						</form>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * Send-in repair requests
	 * --------------------------------------------------------------------- */

	public function page_repairs() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'No permission' );
		}
		global $wpdb;
		$table = AUN_App_Services::repairs_table();

		// Admin actions: approve / reject the PICKUP request, or save a note.
		// There is NO manual status dropdown anymore — once the projector
		// arrives and gets a job sheet in UltimatePOS, the live ERP status is
		// the single source of truth (auto-linked by phone + serial).
		if ( isset( $_POST['aun_repair_nonce'] ) && wp_verify_nonce( $_POST['aun_repair_nonce'], 'aun_repair_save' ) ) {
			$id     = (int) ( $_POST['repair_id'] ?? 0 );
			$action = sanitize_key( $_POST['repair_action'] ?? 'note' );
			$note   = sanitize_textarea_field( $_POST['admin_note'] ?? '' );

			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
			if ( $row ) {
				$update = array(
					'admin_note' => $note,
					'updated_at' => current_time( 'mysql' ),
				);
				$sms = '';
				if ( 'approve' === $action && 'submitted' === $row->status ) {
					$update['status'] = 'approved';
					$sms = AUN_App_Services::repair_sms( 'approved', array(
						'ref'    => $row->ref,
						'model'  => $row->model,
						'status' => AUN_App_Services::REPAIR_STATUSES['approved'],
						'note'   => $note,
					) );
					AUN_App_Notices::repair_decision( (int) $row->user_id, (string) $row->ref, true, $note );
				} elseif ( 'reject' === $action && in_array( $row->status, array( 'submitted', 'approved' ), true ) ) {
					$update['status'] = 'rejected';
					// Refusing a NEW request and cancelling one we already
					// approved are different events for the customer, so they
					// get different words. See repair_sms_cancelled.
					$sms = AUN_App_Services::repair_sms(
						'approved' === $row->status ? 'cancelled' : 'rejected',
						array(
							'ref'    => $row->ref,
							'model'  => $row->model,
							'status' => AUN_App_Services::REPAIR_STATUSES['rejected'],
							'note'   => $note,
						)
					);
					AUN_App_Notices::repair_decision( (int) $row->user_id, (string) $row->ref, false, $note );
				}
				$wpdb->update( $table, $update, array( 'id' => $id ) );

				// Deciding a request takes it out of the pending queue — clear the
				// badge now so it doesn't keep showing a request you just handled.
				delete_transient( 'aun_app_pending_repairs_count' );

				if ( '' !== $sms && ! empty( $_POST['notify_sms'] ) && '' !== $row->phone ) {
					AUN_App_SMS::send( $row->phone, $sms );
					echo '<div class="notice notice-success is-dismissible"><p>Saved. SMS sent to ' . esc_html( $row->phone ) . '.</p></div>';
				} else {
					echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
				}
			}
		}

		$rows = (array) $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC LIMIT 100" );

		// Auto-link to UltimatePOS job sheets right on this page (the same
		// 2-min-cached lookup the app uses), so the job sheet number and the
		// LIVE ERP repair status appear here without any manual step.
		$erp_live = array();
		if ( AUN_App_ERP::repair_configured() ) {
			foreach ( $rows as $r ) {
				if ( '' !== (string) $r->job_sheet_no
					|| in_array( (string) $r->status, AUN_App_Services::REPAIR_LINKABLE, true ) ) {
					// $fetch_final=false: long-closed rows render from the DB
					// instead of one ERP lookup each on every page view. Active
					// rows auto-close here the first time their job sheet hits
					// a business-flagged completed status.
					$erp = AUN_App_Services::repair_erp_sync( $r, (string) $r->phone, false );
					if ( is_array( $erp ) ) {
						$erp_live[ (int) $r->id ] = $erp;
					}
				}
			}
		}

		$this->header( 'Repair Requests', 'Send-in repairs submitted from the Android app.' );
		?>
		<div class="aun-card" style="max-width:860px">
			<h2><span class="dashicons dashicons-info"></span> How this works with UltimatePOS</h2>
			<p class="description" style="max-width:800px;margin:0">
				<strong>1.</strong> Approve the request here (SMS tells the customer to send the projector).
				<strong>2.</strong> When it arrives, create the <strong>job sheet in the ERP Repair module as usual</strong> — that sends its own SMS.
				<strong>3.</strong> The app auto-links the request to the job sheet by phone + serial and shows the customer the
				<strong>live ERP repair status</strong> from then on — no need to update the status here anymore.
			</p>
		</div>
		<?php ?>
		<table class="widefat striped">
			<thead><tr>
				<th style="width:120px">Ref</th><th>Customer</th><th>Device</th><th>Problem</th>
				<th style="width:330px">Status / Note</th>
			</tr></thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="5" style="text-align:center;color:#888;padding:20px">No repair requests yet.</td></tr>
			<?php else : foreach ( $rows as $r ) :
				$photos = json_decode( (string) $r->photos, true );
			?>
				<tr>
					<td>
						<strong><?php echo esc_html( $r->ref ); ?></strong><br>
						<span style="color:#646970;font-size:12px"><?php echo esc_html( substr( $r->created_at, 0, 10 ) ); ?></span>
						<?php if ( '' !== (string) $r->job_sheet_no ) : ?>
							<br><span class="aun-badge aun-badge-green" title="Linked to the ERP job sheet — the app shows the live ERP status">JS <?php echo esc_html( $r->job_sheet_no ); ?></span>
						<?php endif; ?>
						<?php
						// The customer's own courier tracking number, sent from the
						// app once they posted the projector. Shown right here
						// because the person watching this list is the person
						// waiting for the parcel.
						if ( ! empty( $r->courier_tracking ) ) :
							?>
							<br><span class="aun-badge" style="background:#e7f5ff;color:#0b6bcb;border:1px solid #b6dcff" title="Sent by the customer from the app<?php echo ! empty( $r->courier_at ) ? ' on ' . esc_attr( substr( (string) $r->courier_at, 0, 16 ) ) : ''; ?>">
								📦 <?php echo esc_html( trim( (string) $r->courier_name . ' ' . (string) $r->courier_tracking ) ); ?>
							</span>
						<?php endif; ?>
					</td>
					<td>
						<?php echo esc_html( $r->customer_name ); ?><br>
						<a href="tel:<?php echo esc_attr( $r->phone ); ?>"><?php echo esc_html( $r->phone ); ?></a>
						<p style="color:#646970;font-size:12px;margin:4px 0 0"><?php echo esc_html( $r->address ); ?></p>
					</td>
					<td>
						<strong><?php echo esc_html( $r->model ); ?></strong><br>
						<span style="font-size:12px"><?php echo esc_html( $r->serial ); ?></span>
					</td>
					<td>
						<?php echo esc_html( $r->issue ); ?>
						<?php if ( is_array( $photos ) && $photos ) : ?>
							<p style="margin:6px 0 0">
							<?php foreach ( $photos as $i => $url ) : ?>
								<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button button-small">Photo <?php echo (int) $i + 1; ?></a>
							<?php endforeach; ?>
							</p>
						<?php endif; ?>
					</td>
					<td>
						<?php $erp = $erp_live[ (int) $r->id ] ?? null; ?>
						<form method="post">
							<?php wp_nonce_field( 'aun_repair_save', 'aun_repair_nonce' ); ?>
							<input type="hidden" name="repair_id" value="<?php echo (int) $r->id; ?>" />

							<?php if ( $erp ) : ?>
								<span class="aun-badge aun-badge-blue"><?php echo esc_html( $erp['status'] ); ?></span>
								<?php if ( '' !== (string) $erp['estimated_cost'] && 0 < (float) $erp['estimated_cost'] ) : ?>
									<span class="aun-badge aun-badge-green">৳<?php echo esc_html( number_format( (float) $erp['estimated_cost'] ) ); ?></span>
								<?php endif; ?>
								<?php if ( 'closed' === (string) $r->status ) : ?>
									<span class="aun-badge aun-badge-grey">Completed</span>
								<?php endif; ?>
								<p class="description" style="margin:6px 0 0">
									Live from job sheet <strong><?php echo esc_html( $r->job_sheet_no ); ?></strong> —
									update the status in UltimatePOS → Repair; the app and this page follow automatically.
								</p>
								<textarea name="admin_note" rows="2" style="width:100%;margin-top:6px" placeholder="Optional note shown to the customer in the app"><?php echo esc_textarea( $r->admin_note ); ?></textarea>
								<button class="button button-small" name="repair_action" value="note">Save note</button>

							<?php elseif ( 'submitted' === $r->status ) : ?>
								<span class="aun-badge aun-badge-amber">New request</span>
								<textarea name="admin_note" rows="2" style="width:100%;margin-top:6px" placeholder="Note shown to the customer in the app / in the SMS"><?php echo esc_textarea( $r->admin_note ); ?></textarea>
								<label style="display:block;margin:6px 0"><input type="checkbox" name="notify_sms" value="1" checked /> SMS the customer</label>
								<button class="button button-primary button-small" name="repair_action" value="approve">Approve — ask to send it</button>
								<button class="button button-small" name="repair_action" value="reject" onclick="return confirm('Reject this repair request?')">Reject</button>

							<?php elseif ( 'approved' === $r->status ) : ?>
								<span class="aun-badge aun-badge-purple">Approved — waiting for the projector</span>
								<p class="description" style="margin:6px 0 0">
									When it arrives, create the job sheet in UltimatePOS with serial
									<strong><?php echo esc_html( $r->serial ); ?></strong> — it links here automatically.
								</p>
								<?php
								// ⚠️ If they have sent us their courier tracking number, a
								// projector is physically in transit to us RIGHT NOW.
								// Cancelling at this point is sometimes still the right
								// call, but it must never be a one-click action taken
								// without knowing that.
								$in_transit = ! empty( $r->courier_tracking );
								if ( $in_transit ) :
									?>
									<p style="margin:8px 0 0;padding:8px 10px;border-radius:6px;background:#FEF3C7;color:#7C2D12;font-size:12px;line-height:1.5">
										<strong>The customer has already posted it.</strong>
										<?php echo esc_html( trim( (string) $r->courier_name . ' ' . (string) $r->courier_tracking ) ); ?><?php
										echo ! empty( $r->courier_at ) ? ' &middot; sent ' . esc_html( substr( (string) $r->courier_at, 0, 10 ) ) : ''; ?>.
										Call them before cancelling.
									</p>
								<?php endif; ?>
								<textarea name="admin_note" rows="2" style="width:100%;margin-top:6px" placeholder="Why? This is shown to the customer, in the app and the SMS"><?php echo esc_textarea( $r->admin_note ); ?></textarea>
								<label style="display:block;margin:6px 0"><input type="checkbox" name="notify_sms" value="1" checked /> SMS the customer (on cancel)</label>
								<button class="button button-small" name="repair_action" value="note">Save note</button>
								<button class="button button-small" name="repair_action" value="reject" style="color:#b32d2e" onclick="return confirm(<?php echo $in_transit
									? esc_attr( wp_json_encode( "This customer has already posted their projector (tracking " . trim( (string) $r->courier_name . ' ' . (string) $r->courier_tracking ) . ").\n\nCancel anyway? Please make sure someone has spoken to them first." ) )
									: esc_attr( wp_json_encode( "Cancel this repair after approving it?\n\nThe customer has already been asked to send the projector, so they will get an apology SMS, not a rejection." ) ); ?>)">Cancel this repair</button>

							<?php else : ?>
								<span class="aun-badge <?php echo 'rejected' === $r->status ? 'aun-badge-grey' : 'aun-badge-green'; ?>">
									<?php echo esc_html( AUN_App_Services::REPAIR_STATUSES[ $r->status ] ?? $r->status ); ?>
								</span>
								<textarea name="admin_note" rows="2" style="width:100%;margin-top:6px" placeholder="Optional note shown to the customer in the app"><?php echo esc_textarea( $r->admin_note ); ?></textarea>
								<button class="button button-small" name="repair_action" value="note">Save note</button>
							<?php endif; ?>
						</form>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
		</div>
		<?php
	}

	public function assets( $hook ) {
		if ( false === strpos( $hook, 'aun-app' ) ) {
			return;
		}
		wp_enqueue_media();
	}

	private function css() {
		// Same visual language as the SLB Warranty admin.
		return <<<'CSS'
		.aun-wrap{max-width:1100px;margin:20px 20px 40px 0}
		.aun-wrap h1.aun-title{font-size:23px;font-weight:600;display:flex;align-items:center;gap:10px;margin-bottom:4px}
		.aun-wrap h1.aun-title .dashicons{color:#0188fe;font-size:28px;width:28px;height:28px}
		.aun-subtitle{color:#646970;margin:0 0 22px;font-size:14px}
		.aun-card{background:#fff;border:1px solid #e2e4e7;border-radius:10px;padding:22px 24px;margin-bottom:20px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
		.aun-card h2{margin-top:0;font-size:16px;font-weight:600;color:#1d2327;display:flex;align-items:center;gap:8px}
		.aun-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:24px}
		.aun-stat{background:#fff;border:1px solid #e2e4e7;border-radius:10px;padding:20px 22px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
		.aun-stat .num{font-size:30px;font-weight:700;line-height:1.1;color:#1d2327}
		.aun-stat .lbl{font-size:12px;color:#646970;margin-top:6px;text-transform:uppercase;letter-spacing:.4px;font-weight:600}
		.aun-stat .dashicons{font-size:22px;width:22px;height:22px;margin-bottom:6px;color:#0188fe}
		.aun-wrap .widefat{border:1px solid #e2e4e7;border-radius:10px;overflow:hidden}
		.aun-wrap .widefat thead th{background:#f6f7f7;font-weight:600;color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.4px;padding:11px 14px}
		.aun-wrap .widefat td{padding:11px 14px;vertical-align:middle}
		.aun-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;line-height:1.5}
		.aun-badge-blue{background:#dbeafe;color:#1e40af}
		.aun-badge-green{background:#dcfce7;color:#166534}
		.aun-badge-amber{background:#fef3c7;color:#92400e}
		.aun-badge-purple{background:#ede9fe;color:#5b21b6}
		.aun-badge-grey{background:#f3f4f6;color:#6b7280}
		.aun-endpoints code{display:block;padding:2px 0;color:#1d2327}

		/* ── Settings: a sidebar of sections instead of one endless column ──
		   Seven groups of unrelated settings stacked vertically is why things
		   ended up filed under whatever heading happened to be last. */
		.aun-settings{display:grid;grid-template-columns:232px minmax(0,1fr);gap:24px;align-items:start}
		.aun-nav{position:sticky;top:46px;background:#fff;border:1px solid #e2e4e7;border-radius:10px;padding:6px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
		.aun-nav a{display:flex;align-items:center;gap:9px;padding:9px 11px;border-radius:7px;color:#3c434a;text-decoration:none;font-weight:500;font-size:13.5px;line-height:1.3}
		.aun-nav a:hover{background:#f0f6fc;color:#0188fe}
		.aun-nav a.active{background:#0188fe;color:#fff}
		.aun-nav a.active .dashicons{color:#fff}
		.aun-nav .dashicons{font-size:18px;width:18px;height:18px;color:#8c8f94;flex:none}
		.aun-panel{display:none}
		.aun-panel.active{display:block}
		.aun-card .aun-hint{color:#646970;font-size:13px;line-height:1.6;margin:-4px 0 18px;max-width:820px}
		.aun-card h3{font-size:13px;font-weight:700;color:#50575e;text-transform:uppercase;letter-spacing:.5px;margin:26px 0 6px;padding-top:18px;border-top:1px solid #f0f0f1}
		.aun-card h3:first-of-type{margin-top:4px;padding-top:0;border-top:0}
		.aun-wrap .form-table th{width:190px;font-weight:600;color:#1d2327}
		.aun-save{position:sticky;bottom:0;background:#fff;border:1px solid #e2e4e7;border-radius:10px;padding:12px 18px;margin-top:6px;display:flex;align-items:center;gap:14px;box-shadow:0 -2px 10px rgba(0,0,0,.06);z-index:5}
		.aun-save .description{margin:0}
		.aun-status-ok{color:#166534;font-weight:700}
		.aun-status-warn{color:#92400e;font-weight:700}
		.aun-status-bad{color:#b91c1c;font-weight:700}
		@media (max-width:960px){.aun-settings{grid-template-columns:1fr}.aun-nav{position:static}}
		CSS;
	}

	private function header( $title, $subtitle ) {
		echo '<style data-no-optimize="1">' . $this->css() . '</style>';
		echo '<div class="wrap aun-wrap">';
		echo '<h1 class="aun-title"><span class="dashicons dashicons-smartphone"></span> ' . esc_html( $title ) . '</h1>';
		// The running plugin version, stated plainly. "Is the new backend
		// actually live?" is otherwise unanswerable from inside wp-admin, and
		// a stale upload looks exactly like a bug in the app.
		echo '<p class="aun-subtitle">' . esc_html( $subtitle )
			. ' <span class="aun-badge aun-badge-grey" style="margin-left:6px">plugin v'
			. esc_html( AUN_APP_API_VERSION ) . '</span></p>';
	}

	/* --------------------------------------------------------------------- *
	 * Dashboard
	 * --------------------------------------------------------------------- */

	public function page_dashboard() {
		global $wpdb;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'No permission' );
		}

		AUN_App_Tokens::purge_expired();

		// "Run sweep now" — the same batch the hourly cron runs, on demand.
		$sweep_notice = '';
		if ( isset( $_POST['aun_sweep_nonce'] ) && wp_verify_nonce( $_POST['aun_sweep_nonce'], 'aun_app_run_sweep' ) ) {
			$stats           = AUN_App_Warranty::verify_devices_batch();
			$stats['ran_at'] = current_time( 'mysql' );
			$stats['manual'] = 1;
			update_option( 'aun_app_last_sweep', $stats, false );
			$sweep_notice = sprintf(
				'Sweep finished: %d checked, %d unlinked, %d strike(s) recorded, %d skipped (ERP unreachable).',
				(int) $stats['checked'], (int) $stats['unlinked'], (int) $stats['strikes'], (int) $stats['skipped']
			);
		}

		$tokens   = AUN_App_Tokens::stats();
		$contents = AUN_App_Content::counts();
		$app_regs = AUN_App_Warranty::app_registrations_count();
		$slb_ok   = AUN_App_Warranty::available();

		$last_sweep = get_option( 'aun_app_last_sweep', array() );
		$next_sweep = wp_next_scheduled( 'aun_app_verify_devices' );
		$t_dev      = aun_app_api_devices_table();
		$striked    = (array) $wpdb->get_results( "SELECT serial, verify_misses, verified_at FROM $t_dev WHERE verify_misses > 0" );
		$dev_total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_dev" );

		$this->header( 'AUN App — Dashboard', 'Backend for the AUN Projector Android app.' );
		if ( '' !== $sweep_notice ) {
			echo '<div class="notice notice-success"><p><strong>' . esc_html( $sweep_notice ) . '</strong></p></div>';
		}
		?>
		<div class="aun-stats">
			<div class="aun-stat"><span class="dashicons dashicons-groups"></span><div class="num"><?php echo number_format( $tokens['users'] ); ?></div><div class="lbl">App Users</div></div>
			<div class="aun-stat"><span class="dashicons dashicons-smartphone"></span><div class="num"><?php echo number_format( $tokens['tokens'] ); ?></div><div class="lbl">Logged-in Devices</div></div>
			<div class="aun-stat"><span class="dashicons dashicons-shield"></span><div class="num"><?php echo number_format( $app_regs ); ?></div><div class="lbl">App Registrations</div></div>
			<div class="aun-stat"><span class="dashicons dashicons-download"></span><div class="num"><?php echo number_format( $contents['firmware'] + $contents['manual'] ); ?></div><div class="lbl">Files Published</div></div>
			<div class="aun-stat"><span class="dashicons dashicons-video-alt3"></span><div class="num"><?php echo number_format( $contents['video'] ); ?></div><div class="lbl">Video Guides</div></div>
		</div>

		<?php if ( ! $slb_ok ) : ?>
			<div class="notice notice-warning"><p><strong>SLB Warranty plugin tables not found.</strong> Device registration and warranty lookups will be unavailable until the AUN Warranty Registration plugin is active.</p></div>
		<?php endif; ?>

		<div class="aun-card">
			<h2><span class="dashicons dashicons-update"></span> Device Verification Sweep</h2>
			<p class="description" style="margin:0 0 12px">
				Direct-purchase device links are re-checked against the ERP about once a day (hourly cron, small batches).
				A <strong>returned</strong> sale unlinks the device at the next check (within ~24 h).
				A <strong>deleted</strong> sale needs <strong>2 strikes at least 24 h apart</strong> before unlinking
				(so an ERP hiccup can never wipe a real customer's device) — expect <strong>1–2 days</strong>.
				Dealer registrations are never swept.
			</p>
			<table class="widefat" style="max-width:640px;margin-bottom:14px">
				<tbody>
					<tr>
						<td><strong>Tracked direct links</strong></td>
						<td><?php echo number_format( $dev_total ); ?></td>
					</tr>
					<tr>
						<td><strong>Last sweep</strong></td>
						<td>
							<?php if ( ! empty( $last_sweep['ran_at'] ) ) : ?>
								<?php echo esc_html( $last_sweep['ran_at'] ); ?>
								<?php echo ! empty( $last_sweep['manual'] ) ? '<span class="aun-badge aun-badge-grey">manual</span>' : ''; ?>
								— <?php echo (int) ( $last_sweep['checked'] ?? 0 ); ?> checked,
								<?php echo (int) ( $last_sweep['unlinked'] ?? 0 ); ?> unlinked,
								<?php echo (int) ( $last_sweep['strikes'] ?? 0 ); ?> strikes,
								<?php echo (int) ( $last_sweep['skipped'] ?? 0 ); ?> skipped
								<?php if ( (int) ( $last_sweep['skipped'] ?? 0 ) > 0 ) : ?>
									<span class="aun-badge aun-badge-amber">ERP was unreachable</span>
								<?php endif; ?>
							<?php else : ?>
								<span class="aun-badge aun-badge-grey">never recorded yet</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong>Next scheduled run</strong></td>
						<td>
							<?php if ( $next_sweep ) : ?>
								in <?php echo esc_html( human_time_diff( time(), $next_sweep ) ); ?>
								(WP-Cron fires on site visits)
							<?php else : ?>
								<span class="aun-badge aun-badge-amber">NOT SCHEDULED</span>
								— deactivate and reactivate the AUN App API plugin to restore it.
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
			<?php if ( $striked ) : ?>
				<p style="margin:0 0 8px"><strong>Pending strikes</strong> (sale missing once — will unlink at the next daily check if still missing):</p>
				<table class="widefat" style="max-width:640px;margin-bottom:14px">
					<thead><tr><th>Serial</th><th>Strikes</th><th>Last checked</th></tr></thead>
					<tbody>
						<?php foreach ( $striked as $srow ) : ?>
							<tr>
								<td><code><?php echo esc_html( $srow->serial ); ?></code></td>
								<td><?php echo (int) $srow->verify_misses; ?> / 2</td>
								<td><?php echo esc_html( (string) $srow->verified_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<form method="post" style="margin:0">
				<?php wp_nonce_field( 'aun_app_run_sweep', 'aun_sweep_nonce' ); ?>
				<button type="submit" class="button button-secondary">Run verification sweep now</button>
			</form>
		</div>

		<?php $this->planner_diagnostics(); ?>

		<div class="aun-card aun-endpoints">
			<h2><span class="dashicons dashicons-rest-api"></span> API Base URL</h2>
			<code><?php echo esc_html( rest_url( AUN_APP_API_NS ) ); ?></code>
			<p class="description" style="margin-top:10px">
				The Android app talks to this address. Health check:
				<a href="<?php echo esc_url( rest_url( AUN_APP_API_NS . '/ping' ) ); ?>" target="_blank">/ping</a> ·
				Public config: <a href="<?php echo esc_url( rest_url( AUN_APP_API_NS . '/config' ) ); ?>" target="_blank">/config</a>
			</p>
		</div>
		</div>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * Content manager
	 * --------------------------------------------------------------------- */

	public function page_content() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'No permission' );
		}

		$base_url = admin_url( 'admin.php?page=aun-app-content' );

		// Current filter state (also used to preserve context across redirects).
		$f_type   = sanitize_key( $_GET['ctype'] ?? '' );
		$f_type   = in_array( $f_type, AUN_App_Content::TYPES, true ) ? $f_type : '';
		$f_model  = ( isset( $_GET['cmodel'] ) && '' !== $_GET['cmodel'] ) ? (int) $_GET['cmodel'] : -1; // -1 = all
		$f_search = sanitize_text_field( wp_unslash( $_GET['csearch'] ?? '' ) );
		$filter_args = array_filter(
			array( 'ctype' => $f_type, 'cmodel' => $f_model >= 0 ? $f_model : '', 'csearch' => $f_search ),
			function ( $v ) { return '' !== (string) $v; }
		);
		$filtered_url = add_query_arg( $filter_args, $base_url );

		// Delete.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] && check_admin_referer( 'aun_app_content_del', 'aun_nonce' ) ) {
			AUN_App_Content::delete( (int) $_GET['id'] );
			wp_safe_redirect( add_query_arg( 'aun_msg', 'deleted', $filtered_url ) );
			exit;
		}

		// Inline show/hide toggle.
		if ( isset( $_GET['action'], $_GET['id'] ) && 'toggle' === $_GET['action'] && check_admin_referer( 'aun_app_content_toggle', 'aun_nonce' ) ) {
			$item = AUN_App_Content::get( (int) $_GET['id'] );
			if ( $item ) {
				AUN_App_Content::set_active( (int) $item->id, ! ( (int) $item->active ) );
			}
			wp_safe_redirect( add_query_arg( 'aun_msg', 'toggled', $filtered_url ) );
			exit;
		}

		// Save (add or edit).
		if ( isset( $_POST['aun_content_nonce'] ) && wp_verify_nonce( $_POST['aun_content_nonce'], 'aun_app_content_save' ) ) {
			$id    = isset( $_POST['content_id'] ) ? (int) $_POST['content_id'] : 0;
			$saved = AUN_App_Content::save(
				array(
					'type'        => sanitize_key( $_POST['type'] ?? 'firmware' ),
					'model_id'    => (int) ( $_POST['model_id'] ?? 0 ),
					'title'       => (string) ( $_POST['title'] ?? '' ),
					'description' => (string) ( $_POST['description'] ?? '' ),
					'changelog'   => (string) ( $_POST['changelog'] ?? '' ),
					'url'         => (string) ( $_POST['url'] ?? '' ),
					'version'     => (string) ( $_POST['version'] ?? '' ),
					'file_size'   => (string) ( $_POST['file_size'] ?? '' ),
					'sort'        => (int) ( $_POST['sort'] ?? 0 ),
					'active'      => ! empty( $_POST['active'] ),
					'app_downloadable' => ! empty( $_POST['app_downloadable'] ) ? 1 : 0,
				),
				$id
			);
			wp_safe_redirect( add_query_arg( 'aun_msg', $saved ? 'saved' : 'error', $filtered_url ) );
			exit;
		}

		$msg     = sanitize_text_field( $_GET['aun_msg'] ?? '' );
		$msg_map = array(
			'saved'   => array( 'notice-success', 'Content saved.' ),
			'deleted' => array( 'notice-success', 'Content deleted.' ),
			'toggled' => array( 'notice-success', 'Visibility updated.' ),
			'error'   => array( 'notice-error', 'Could not save the content item.' ),
		);

		$editing = null;
		if ( ! empty( $_GET['edit_id'] ) ) {
			$editing = AUN_App_Content::get( (int) $_GET['edit_id'] );
		}

		$models = AUN_App_Warranty::available() ? AUN_App_Warranty::models() : array();
		$rows   = AUN_App_Content::all_for_admin();

		$model_names = array( 0 => 'General (all models)' );
		foreach ( $models as $m ) {
			$model_names[ (int) $m['id'] ] = $m['name'];
		}

		// Type presentation (badge colour class + dashicon).
		$type_meta = array(
			'firmware' => array( 'Firmware', 'aun-badge-blue', 'dashicons-download' ),
			'manual'   => array( 'Manual', 'aun-badge-green', 'dashicons-media-document' ),
			'video'    => array( 'Video', 'aun-badge-purple', 'dashicons-video-alt3' ),
		);

		// Per-type counts across ALL content (for the filter chips).
		$counts = array( 'firmware' => 0, 'manual' => 0, 'video' => 0 );
		foreach ( $rows as $r ) {
			if ( isset( $counts[ $r->type ] ) ) {
				$counts[ $r->type ]++;
			}
		}
		$total = count( $rows );

		// Apply filters.
		$filtered = array_values( array_filter( $rows, function ( $r ) use ( $f_type, $f_model, $f_search ) {
			if ( '' !== $f_type && $r->type !== $f_type ) {
				return false;
			}
			if ( $f_model >= 0 && (int) $r->model_id !== $f_model ) {
				return false;
			}
			if ( '' !== $f_search && false === stripos( (string) $r->title, $f_search ) ) {
				return false;
			}
			return true;
		} ) );

		// Group the filtered rows by model (device-wise view).
		$groups = array();
		foreach ( $filtered as $r ) {
			$groups[ (int) $r->model_id ][] = $r;
		}
		// Ordered model ids: device models (in Products order) first, then any
		// other model ids present in the data (e.g. a model since removed from
		// Products — its content must still be visible/manageable, never hidden),
		// then General (all models) last.
		$ordered_ids = array();
		foreach ( $models as $m ) {
			if ( isset( $groups[ (int) $m['id'] ] ) ) {
				$ordered_ids[] = (int) $m['id'];
			}
		}
		foreach ( array_keys( $groups ) as $gid ) {
			$gid = (int) $gid;
			if ( 0 !== $gid && ! in_array( $gid, $ordered_ids, true ) ) {
				$ordered_ids[] = $gid;
			}
		}
		if ( isset( $groups[0] ) ) {
			$ordered_ids[] = 0;
		}

		$v = function ( $field, $default = '' ) use ( $editing ) {
			return esc_attr( $editing->$field ?? $default );
		};

		// Add-form defaults from the active filter (ease when adding many for one
		// device / one type).
		$default_model = ( ! $editing && $f_model >= 0 ) ? $f_model : ( $editing ? (int) $editing->model_id : 0 );
		$default_type  = $editing ? (string) $editing->type : ( '' !== $f_type ? $f_type : 'firmware' );

		$this->header( 'App Content', 'Firmware, manuals, video guides and tips shown inside the Android app.' );

		if ( isset( $msg_map[ $msg ] ) ) {
			echo '<div class="notice ' . esc_attr( $msg_map[ $msg ][0] ) . ' is-dismissible"><p>' . esc_html( $msg_map[ $msg ][1] ) . '</p></div>';
		}

		// (The "Test download" check runs inline via AJAX — see #aun-dltest-btn.)

		// A little styling for the toolbar / chips / grouped list.
		?>
		<style data-no-optimize="1">
			.aun-cbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:6px 0 14px}
			.aun-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;background:#fff;border:1px solid #dcdcde;color:#1d2327;text-decoration:none;font-weight:600;font-size:13px;line-height:1}
			.aun-chip .n{background:#f0f0f1;border-radius:999px;padding:1px 8px;font-size:11px;color:#50575e}
			.aun-chip.on{background:#0188fe;border-color:#0188fe;color:#fff}
			.aun-chip.on .n{background:rgba(255,255,255,.25);color:#fff}
			.aun-cfilters{display:flex;flex-wrap:wrap;gap:10px;align-items:end;background:#fff;border:1px solid #e2e4e7;border-radius:12px;padding:12px 14px}
			.aun-cfilters label{display:block;font-size:11px;font-weight:700;color:#646970;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px}
			.aun-cgroup{background:#fff;border:1px solid #e2e4e7;border-radius:12px;margin:14px 0;overflow:hidden}
			.aun-cgroup>summary{cursor:pointer;list-style:none;padding:12px 16px;font-size:15px;font-weight:800;display:flex;align-items:center;gap:10px;background:#f6f8fb}
			.aun-cgroup>summary::-webkit-details-marker{display:none}
			.aun-cgroup>summary .n{background:#e7edf6;color:#0b5cad;border-radius:999px;padding:1px 9px;font-size:12px;font-weight:700}
			.aun-cgroup table{margin:0;border:0}
			.aun-cgroup td,.aun-cgroup th{border-bottom:1px solid #f0f0f1}
			.aun-addwrap{background:#fff;border:1px solid #e2e4e7;border-radius:12px;margin-bottom:8px}
			.aun-addwrap>summary{cursor:pointer;list-style:none;padding:14px 16px;font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px}
			.aun-addwrap>summary::-webkit-details-marker{display:none}
			.aun-addwrap[open]>summary{border-bottom:1px solid #eef0f2}
			.aun-addbody{padding:4px 16px 12px}
			.aun-tgl{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;text-decoration:none}
			.aun-tgl.live{background:#e6f6ec;color:#137333}
			.aun-tgl.off{background:#fdecea;color:#b3261e}
		</style>

		<?php // ── Type quick-filter chips (counts) ── ?>
		<div class="aun-cbar">
			<a class="aun-chip <?php echo '' === $f_type ? 'on' : ''; ?>" href="<?php echo esc_url( remove_query_arg( 'ctype', $filtered_url ) ); ?>">All <span class="n"><?php echo (int) $total; ?></span></a>
			<?php foreach ( $type_meta as $tk => $tm ) : ?>
				<a class="aun-chip <?php echo $f_type === $tk ? 'on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'ctype', $tk, $filtered_url ) ); ?>">
					<span class="dashicons <?php echo esc_attr( $tm[2] ); ?>" style="font-size:16px;width:16px;height:16px"></span>
					<?php echo esc_html( $tm[0] ); ?> <span class="n"><?php echo (int) $counts[ $tk ]; ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<?php // ── Model + search filter (GET form; preserves the type chip) ── ?>
		<form method="get" class="aun-cfilters">
			<input type="hidden" name="page" value="aun-app-content" />
			<?php if ( '' !== $f_type ) : ?><input type="hidden" name="ctype" value="<?php echo esc_attr( $f_type ); ?>" /><?php endif; ?>
			<div>
				<label for="cmodel">Device / model</label>
				<select name="cmodel" id="cmodel">
					<option value="">All devices</option>
					<option value="0" <?php selected( $f_model, 0 ); ?>>General (all models)</option>
					<?php foreach ( $models as $m ) : ?>
						<option value="<?php echo (int) $m['id']; ?>" <?php selected( $f_model, (int) $m['id'] ); ?>><?php echo esc_html( $m['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="csearch">Search title</label>
				<input type="search" name="csearch" id="csearch" value="<?php echo esc_attr( $f_search ); ?>" placeholder="Type to search…" style="min-width:220px" />
			</div>
			<div>
				<button class="button button-primary">Filter</button>
				<?php if ( ! empty( $filter_args ) ) : ?><a class="button" href="<?php echo esc_url( $base_url ); ?>">Reset</a><?php endif; ?>
			</div>
		</form>

		<?php // ── Add / Edit form (collapsible; opens automatically when editing) ── ?>
		<details class="aun-addwrap" <?php echo $editing ? 'open' : ''; ?> id="aun-addform">
			<summary>
				<span class="dashicons dashicons-<?php echo $editing ? 'edit' : 'plus-alt'; ?>"></span>
				<?php echo $editing ? 'Edit content #' . (int) $editing->id : 'Add new content'; ?>
			</summary>
			<div class="aun-addbody">
			<form method="post">
				<?php wp_nonce_field( 'aun_app_content_save', 'aun_content_nonce' ); ?>
				<input type="hidden" name="content_id" value="<?php echo $editing ? (int) $editing->id : 0; ?>" />
				<table class="form-table">
					<tr><th>Type</th><td>
						<select name="type" id="aun-type">
							<?php foreach ( AUN_App_Content::TYPES as $t ) : ?>
								<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $default_type, $t ); ?>><?php echo esc_html( ucfirst( $t ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th>Product model</th><td>
						<select name="model_id">
							<option value="0" <?php selected( $default_model, 0 ); ?>>General (all models)</option>
							<?php foreach ( $models as $m ) : ?>
								<option value="<?php echo (int) $m['id']; ?>" <?php selected( $default_model, (int) $m['id'] ); ?>><?php echo esc_html( $m['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Model list comes from SLB Warranty → Products.<?php echo ( ! $editing && $f_model > 0 ) ? ' <strong>Pre-selected from your filter.</strong>' : ''; ?></p>
					</td></tr>
					<tr><th id="aun-title-label">Title</th><td><input name="title" id="aun-title" required style="width:100%" value="<?php echo $v( 'title' ); ?>" placeholder="e.g. Firmware v2.1 — fixes HDMI sound" /></td></tr>
					<tr class="aun-crow" data-types="firmware"><th>What&rsquo;s new</th><td>
					<p class="description" style="margin-top:0;max-width:780px">
						What changed in this release &mdash; the reason a customer should bother installing
						it. Shown <strong>above</strong> the installation steps, in its own highlighted block,
						and the first line becomes the body of the &ldquo;new firmware&rdquo; notification.
						<br />
						Write it as short bullets in the customer&rsquo;s language: <em>&ldquo;Fixes no sound
						over HDMI on some TVs&rdquo;</em>, not <em>&ldquo;patched audio driver&rdquo;</em>.
						Leave blank and the block simply does not appear.
					</p>
				<?php
				wp_editor(
					$editing->changelog ?? '',
					'aun_content_changelog',
					array(
						'textarea_name' => 'changelog',
						'textarea_rows' => 5,
						'media_buttons' => false,
						'teeny'         => true,
						'quicktags'     => true,
					)
				);
				?>
				</td></tr>
					<tr class="aun-crow" data-types="firmware"><th id="aun-desc-label">Installation steps</th><td>
					<p class="description" id="aun-desc-help" style="margin-top:0"><strong>How</strong> to install it — the steps the customer follows. (What CHANGED goes in
					&ldquo;What&rsquo;s new&rdquo; above.) Use the toolbar for <b>bold</b>, lists and headings; paste a
					YouTube link on its own line and it plays inside the app.</p>
				<?php
				wp_editor(
					$editing->description ?? '',
					'aun_content_desc',
					array(
						'textarea_name' => 'description',
						'textarea_rows' => 8,
						'media_buttons' => false,
						'teeny'         => true,
						'quicktags'     => true,
					)
				);
				?>
				</td></tr>
					<tr class="aun-crow" data-types="firmware,manual,video"><th id="aun-url-label">URL / File</th><td>
						<input name="url" id="aun-url" style="width:78%" value="<?php echo $v( 'url' ); ?>" placeholder="File URL or YouTube link" />
						<button type="button" class="button" id="aun-media-btn">Choose file</button>
						<p class="description" id="aun-url-help">Firmware/manual: pick an uploaded file. Video: paste a YouTube link.</p>
						<p class="description aun-crow" data-types="firmware" style="max-width:780px;background:#f0f6fc;border-left:3px solid #0188fe;padding:10px 14px;border-radius:6px;margin-top:8px">
							<strong>Over-the-air (OTA) update? Leave this blank.</strong>
							Some models fetch the update themselves over Wi-Fi, so there is no zip to publish.
							Leave the file empty and the app replaces the download button with an
							<strong>&ldquo;updates over Wi-Fi&rdquo;</strong> card showing your instructions below
							&mdash; and any YouTube link in the installation note plays inside the app.
							Everything else works normally: it still appears under the model, still carries a
							version number, and still sends the &ldquo;new firmware&rdquo; notification.
						</p>
					</td></tr>
					<tr class="aun-crow" data-types="firmware"><th>Version</th><td><input name="version" value="<?php echo $v( 'version' ); ?>" placeholder="e.g. 2.1.0" /></td></tr>
					<tr class="aun-crow" data-types="firmware,manual"><th>File size</th><td><input name="file_size" id="aun-file-size" value="<?php echo $v( 'file_size' ); ?>" placeholder="e.g. 48 MB" /></td></tr>
					<tr><th>Sort order</th><td><input name="sort" type="number" value="<?php echo $v( 'sort', '0' ); ?>" style="width:90px" /> <span class="description">Lower shows first within a device.</span></td></tr>
					<tr class="aun-crow" data-types="firmware,manual"><th>App download</th><td>
						<label><input type="checkbox" name="app_downloadable" <?php checked( $editing ? (int) ( isset( $editing->app_downloadable ) ? $editing->app_downloadable : 1 ) : 1, 1 ); ?> /> Downloadable in the app</label>
						<p class="description">Uncheck to hide the download button in the app for this file (e.g. a link the app can't fetch).</p>
						<p style="margin:10px 0 0">
							<button type="button" class="button" id="aun-dltest-btn"
								data-id="<?php echo (int) ( $editing ? $editing->id : 0 ); ?>"
								data-nonce="<?php echo esc_attr( wp_create_nonce( 'aun_app_dltest' ) ); ?>">Test download from server</button>
							<span class="description" style="margin-left:8px">Checks the app will get a real file — the URL above is tested, even before you save.</span>
						</p>
						<div id="aun-dltest-out" style="display:none;margin-top:10px;padding:10px 12px;border-radius:6px;border-left:4px solid #ccc;background:#fff"></div>
					</td></tr>
					<tr><th>Active</th><td><label><input type="checkbox" name="active" <?php checked( $editing ? (int) $editing->active : 1, 1 ); ?> /> Visible in the app</label></td></tr>
				</table>
				<p>
					<input type="submit" class="button button-primary" value="<?php echo $editing ? 'Save Changes' : 'Add Content'; ?>" />
					<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( $filtered_url ); ?>">Cancel</a><?php endif; ?>
				</p>
			</form>
			</div>
		</details>

		<?php // ── Grouped, device-wise content list ── ?>
		<?php if ( empty( $filtered ) ) : ?>
			<div class="aun-cgroup"><p style="text-align:center;color:#787c82;padding:26px 16px;margin:0">
				<?php echo empty( $rows ) ? 'Nothing published yet — use “Add new content” above.' : 'No content matches these filters.'; ?>
			</p></div>
		<?php else : foreach ( $ordered_ids as $mid ) :
			$grp = $groups[ $mid ];
			// Keep each device's items in the app's own order (sort, then newest).
			usort( $grp, function ( $a, $b ) {
				return array( (int) $a->sort, -(int) $a->id ) <=> array( (int) $b->sort, -(int) $b->id );
			} );
		?>
			<details class="aun-cgroup" open>
				<summary>
					<span class="dashicons dashicons-<?php echo 0 === $mid ? 'admin-site-alt3' : 'desktop'; ?>"></span>
					<?php echo esc_html( $model_names[ $mid ] ?? ( 'Model #' . $mid ) ); ?>
					<span class="n"><?php echo count( $grp ); ?></span>
				</summary>
				<table class="widefat striped">
					<thead><tr>
						<th style="width:104px">Type</th><th>Title</th>
						<th style="width:90px">Version</th><th style="width:56px">Sort</th>
						<th style="width:92px">Status</th><th style="width:150px">Actions</th>
					</tr></thead>
					<tbody>
					<?php foreach ( $grp as $r ) :
						$tm     = $type_meta[ $r->type ] ?? array( ucfirst( $r->type ), 'aun-badge-grey', 'dashicons-media-default' );
						$edit   = add_query_arg( array_merge( array( 'page' => 'aun-app-content', 'edit_id' => $r->id ), $filter_args ), admin_url( 'admin.php' ) ) . '#aun-addform';
						$del    = wp_nonce_url( add_query_arg( array_merge( array( 'page' => 'aun-app-content', 'action' => 'delete', 'id' => $r->id ), $filter_args ), admin_url( 'admin.php' ) ), 'aun_app_content_del', 'aun_nonce' );
						$toggle = wp_nonce_url( add_query_arg( array_merge( array( 'page' => 'aun-app-content', 'action' => 'toggle', 'id' => $r->id ), $filter_args ), admin_url( 'admin.php' ) ), 'aun_app_content_toggle', 'aun_nonce' );
					?>
						<tr>
							<td><span class="aun-badge <?php echo esc_attr( $tm[1] ); ?>"><?php echo esc_html( $tm[0] ); ?></span></td>
							<td><strong><?php echo esc_html( $r->title ); ?></strong></td>
							<td><?php echo esc_html( $r->version ); ?></td>
							<td><?php echo (int) $r->sort; ?></td>
							<td>
								<a class="aun-tgl <?php echo $r->active ? 'live' : 'off'; ?>" href="<?php echo esc_url( $toggle ); ?>" title="Click to <?php echo $r->active ? 'hide' : 'show'; ?>">
									<?php echo $r->active ? '● Live' : '○ Hidden'; ?>
								</a>
							</td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( $edit ); ?>">Edit</a>
								<a class="button button-small" href="<?php echo esc_url( $del ); ?>" onclick="return confirm('Delete this item?')">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		<?php endforeach; endif; ?>
		</div>

		<script data-no-optimize="1">
		jQuery(function($){
			$('#aun-media-btn').on('click', function(e){
				e.preventDefault();
				var frame = wp.media({ title: 'Select file', multiple: false });
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$('#aun-url').val(att.url);
					if (att.filesizeHumanReadable && !$('#aun-file-size').val()) {
						$('#aun-file-size').val(att.filesizeHumanReadable);
					}
				});
				frame.open();
			});

			// Dynamic Add-Content form: each content type shows ONLY the fields it
			// uses, with its own title/URL/description wording. Plain DOM so it always
			// runs regardless of jQuery / TinyMCE timing.
			var PRESET = {
				firmware: { title: 'e.g. Firmware v2.1 — fixes HDMI sound', urlLabel: 'Firmware file', urlHelp: 'Choose the uploaded firmware file (.zip / .bin).', urlPh: 'Firmware file URL', descLabel: 'Installation steps', media: true },
				manual:   { title: 'e.g. A005 User Manual (English)', urlLabel: 'Manual file (PDF)', urlHelp: 'Choose the uploaded PDF manual.', urlPh: 'PDF file URL', descLabel: 'Description (optional)', media: true },
				video:    { title: 'e.g. How to connect your projector to WiFi', urlLabel: 'YouTube / video link', urlHelp: 'Paste the YouTube link (or a direct .mp4 / .m3u8 URL).', urlPh: 'https://youtu.be/…', descLabel: 'Description (optional)', media: false }
			};
			var typeSel = document.getElementById('aun-type');
			function setText(id, t){ var el = document.getElementById(id); if (el) { el.textContent = t; } }
			function setPh(id, t){ var el = document.getElementById(id); if (el) { el.setAttribute('placeholder', t); } }
			function applyType(){
				var t = typeSel.value;
				var p = PRESET[t] || PRESET.firmware;
				var rows = document.querySelectorAll('.aun-crow');
				for (var i = 0; i < rows.length; i++) {
					var types = (rows[i].getAttribute('data-types') || '').split(',');
					rows[i].style.display = (types.indexOf(t) !== -1) ? '' : 'none';
				}
				setPh('aun-title', p.title);
				setPh('aun-url', p.urlPh);
				setText('aun-url-label', p.urlLabel);
				setText('aun-url-help', p.urlHelp);
				setText('aun-desc-label', p.descLabel);
				var mb = document.getElementById('aun-media-btn');
				if (mb) { mb.style.display = p.media ? '' : 'none'; }
			}
			if (typeSel) { typeSel.addEventListener('change', applyType); applyType(); }

			// "Test download from server" — inline check, no page reload. Tests the
			// URL currently in the form, so a link can be verified before saving.
			var dlBtn = document.getElementById('aun-dltest-btn');
			var dlOut = document.getElementById('aun-dltest-out');
			if (dlBtn && dlOut) {
				dlBtn.addEventListener('click', function () {
					var urlEl = document.getElementById('aun-url');
					var url = urlEl ? urlEl.value : '';
					dlBtn.disabled = true;
					var label = dlBtn.textContent;
					dlBtn.textContent = 'Testing…';
					dlOut.style.display = 'block';
					dlOut.style.borderLeftColor = '#2271b1';
					dlOut.innerHTML = '<span class="spinner is-active" style="float:none;margin:0 6px 0 0"></span>Checking the file the app would receive…';
					var body = new URLSearchParams();
					body.append('action', 'aun_app_test_download');
					body.append('nonce', dlBtn.getAttribute('data-nonce'));
					body.append('id', dlBtn.getAttribute('data-id'));
					body.append('url', url);
					fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
						.then(function (r) { return r.json(); })
						.then(function (res) {
							var d = (res && res.data) ? res.data : {};
							var ok = !!d.ok;
							dlOut.style.borderLeftColor = ok ? '#00a32a' : '#d63638';
							dlOut.innerHTML = '<strong>' + (ok ? 'Downloadable ✓' : 'Not downloadable ✗') + '</strong><br>' +
								(d.message ? String(d.message).replace(/</g, '&lt;') : '') +
								(d.via ? '<br><em>Route: ' + String(d.via).replace(/</g, '&lt;') + '</em>' : '');
						})
						.catch(function () {
							dlOut.style.borderLeftColor = '#d63638';
							dlOut.textContent = 'Could not run the test (network/permission error).';
						})
						.then(function () { dlBtn.disabled = false; dlBtn.textContent = label; });
				});
			}
		});
		</script>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * Settings
	 * --------------------------------------------------------------------- */

	/**
	 * Turn the repeatable "What to watch → Local picks" form rows into the
	 * stored line format `Title | Platform | URL | Poster | EndDate`. Rows
	 * without a title or URL are dropped; empty trailing columns are trimmed so
	 * old-style 3-column lines are produced when poster/end aren't used.
	 *
	 * @param mixed $rows $_POST['watch_pick'] (array of associative arrays).
	 * @return string One pick per line.
	 */
	/**
	 * Title search behind the "Search TMDB" button on a local pick.
	 *
	 * Admin-only and nonce-checked: it spends the site's TMDB quota, so it is
	 * not something any logged-in subscriber should be able to run in a loop.
	 */
	public function ajax_tmdb_search() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(), 403 );
		}
		check_ajax_referer( 'aun_app_tmdb_search', 'nonce' );

		if ( ! class_exists( 'AUN_App_Watch' ) || ! method_exists( 'AUN_App_Watch', 'search' ) ) {
			wp_send_json_error( array(), 503 );
		}
		wp_send_json_success(
			AUN_App_Watch::search( wp_unslash( $_POST['q'] ?? '' ) )
		);
	}

	private static function collect_local_picks( $rows ) {
		if ( ! is_array( $rows ) ) {
			return '';
		}
		$lines = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$title    = trim( sanitize_text_field( wp_unslash( $row['title'] ?? '' ) ) );
			$platform = trim( sanitize_text_field( wp_unslash( $row['platform'] ?? '' ) ) );
			$url      = trim( esc_url_raw( wp_unslash( $row['url'] ?? '' ) ) );
			$poster   = trim( esc_url_raw( wp_unslash( $row['poster'] ?? '' ) ) );
			$end      = trim( sanitize_text_field( wp_unslash( $row['end'] ?? '' ) ) );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
				$end = '';
			}
			// Link to a TMDB title: "movie:1044789" / "tv:12345". Anything
			// else is dropped rather than stored, so a typo cannot become a
			// silently dead pick that still LOOKS linked in the table.
			$tmdb = trim( sanitize_text_field( wp_unslash( $row['tmdb'] ?? '' ) ) );
			if ( ! preg_match( '/^(movie|tv):\d+$/', $tmdb ) ) {
				$tmdb = preg_match( '/^\d+$/', $tmdb ) ? 'movie:' . $tmdb : '';
			}
			// A pick needs at least a title and a URL to be useful.
			if ( '' === $title || '' === $url ) {
				continue;
			}
			// The '|' is the field separator — never let it leak in from a value.
			$cols = array_map(
				function ( $v ) { return str_replace( '|', '/', $v ); },
				array( $title, $platform, $url, $poster, $end, $tmdb )
			);
			// Trim empty trailing columns so we don't store "a|b|c||".
			while ( count( $cols ) > 3 && '' === end( $cols ) ) {
				array_pop( $cols );
			}
			$lines[] = implode( ' | ', $cols );
		}
		return implode( "\n", $lines );
	}

	/**
	 * What people actually do in the app.
	 *
	 * Deliberately a short page. The point of these numbers is to answer a few
	 * standing questions — which services justify their upkeep, does the finder
	 * work, do customers answer quotes in the app — and a dashboard with fifty
	 * charts is one nobody opens twice.
	 */
	/**
	 * Every SMS this plugin sends the customer, in one place.
	 *
	 * ⚠️ Two reasons this is its own page rather than a section of Settings.
	 * The sibling plugin already puts its fourteen templates under
	 * **Spare Parts -> Messages**, so an owner looking for wording looks for a
	 * Messages page and does not find one here. And Settings is twelve cards
	 * long — a message buried in the middle of it is, in practice, not editable
	 * at all: the owner reported exactly that.
	 */
	public function page_messages() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Nope.' );
		}

		$notice = '';
		if ( isset( $_POST['aun_msg_nonce'] ) && wp_verify_nonce( $_POST['aun_msg_nonce'], 'aun_app_messages' ) ) {
			$opts = aun_app_api_get_options();

			$otp = sanitize_textarea_field( wp_unslash( $_POST['sms_template'] ?? '' ) );
			// ⚠️ The login code is the one message that CANNOT be blank: without
			// [otp] in it the customer receives a text with no code in it and
			// simply cannot sign in. Refuse rather than save something broken.
			if ( '' === trim( $otp ) || false === strpos( $otp, '[otp]' ) ) {
				$notice = '<div class="notice notice-error"><p><strong>Login code SMS not saved.</strong> '
					. 'It must contain <code>[otp]</code>, or the customer gets a message with no code in it '
					. 'and cannot sign in. Everything else was saved.</p></div>';
			} else {
				$opts['sms_template'] = $otp;
			}

			foreach ( array( 'received', 'approved', 'rejected', 'cancelled' ) as $ev ) {
				$opts[ 'repair_sms_' . $ev ] = sanitize_textarea_field( wp_unslash( $_POST[ 'repair_sms_' . $ev ] ?? '' ) );
			}

			update_option( AUN_APP_API_OPTION, $opts );
			if ( '' === $notice ) {
				$notice = '<div class="notice notice-success is-dismissible"><p>Messages saved.</p></div>';
			}
		}

		$opts = aun_app_api_get_options();
		$sp   = admin_url( 'admin.php?page=aun-sp-messages' );

		echo '<div class="wrap"><h1>Customer messages</h1>';
		echo $notice;
		echo '<p style="max-width:820px;color:#646970">Every SMS this app sends. Write them in Bangla if you '
			. 'prefer &mdash; the placeholders work either way. Spare-parts messages (quotes, reminders, '
			. 'payments) are separate: <a href="' . esc_url( $sp ) . '">Spare Parts &rarr; Messages</a>.</p>';

		echo '<form method="post">';
		wp_nonce_field( 'aun_app_messages', 'aun_msg_nonce' );
		echo '<table class="form-table">';

		// ── Login ──
		echo '<tr><th colspan="2"><h3 style="margin:0">Signing in</h3></th></tr>';
		echo '<tr><th>Login code SMS</th><td>'
			. '<textarea name="sms_template" rows="2" style="width:100%;max-width:780px">'
			. esc_textarea( (string) ( $opts['sms_template'] ?? '' ) ) . '</textarea>'
			. '<p class="description" style="max-width:780px">Sent every time someone signs in &mdash; '
			. '<strong>the most-sent message in the system</strong>. Placeholders: <code>[otp]</code> the code, '
			. '<code>[site]</code> your site name, <code>[min]</code> how many minutes it lasts. '
			. '<strong><code>[otp]</code> is required.</strong><br />'
			. 'Keep it short: one SMS is 160 characters, and Bangla text costs far fewer characters per part.</p>'
			. '</td></tr>';

		// ── Repairs ──
		$repairs = array(
			'received'  => array( 'Repair request received', 'Sent the moment a customer submits a repair request from the app. Tell them <strong>not</strong> to ship yet.' ),
			'approved'  => array( 'Repair approved', 'Sent when you press <strong>Approve</strong> on the Repairs screen, if &ldquo;SMS the customer&rdquo; is ticked.' ),
			'rejected'  => array( 'Repair refused', 'Sent when you refuse a <strong>new</strong> request, before the customer has been told to send anything.' ),
			'cancelled' => array( 'Repair cancelled after approval', 'A different message on purpose: by now they have been told &ldquo;please send it&rdquo; and may have posted it already, so this apologises instead of refusing.' ),
		);
		echo '<tr><th colspan="2"><h3 style="margin:0">Repairs</h3></th></tr>';
		echo '<tr><th colspan="2"><p class="description" style="font-weight:400;margin:0 0 8px;max-width:820px">'
			. 'Placeholders: <code>{ref}</code> <code>{model}</code> <code>{status}</code> '
			. '<code>{note}</code> (your note to the customer, when you write one). '
			. '<strong>Leave a box empty to send no SMS for that event</strong> &mdash; the change still '
			. 'appears in the customer&rsquo;s app either way.</p></th></tr>';

		foreach ( $repairs as $key => $meta ) {
			echo '<tr><th>' . esc_html( $meta[0] ) . '</th><td>'
				. '<textarea name="repair_sms_' . esc_attr( $key ) . '" rows="3" style="width:100%;max-width:780px">'
				. esc_textarea( (string) ( $opts[ 'repair_sms_' . $key ] ?? '' ) ) . '</textarea>'
				. '<p class="description" style="max-width:780px">' . $meta[1] . '</p>'
				. '</td></tr>';
		}

		echo '</table>';
		echo '<p><button type="submit" class="button button-primary">Save messages</button></p>';
		echo '</form></div>';
	}


	public function page_usage() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Nope.' );
		}
		$days = isset( $_GET['days'] ) ? max( 1, min( 180, (int) $_GET['days'] ) ) : 30;

		echo '<div class="wrap"><h1>App usage</h1>';

		if ( ! AUN_App_Events::enabled() ) {
			echo '<div class="notice notice-warning"><p><strong>Collection is switched off.</strong> '
				. 'Turn on <em>App analytics</em> in Settings to start recording again. '
				. 'Anything already recorded is still shown below.</p></div>';
		}

		echo '<p style="max-width:820px;color:#646970">'
			. 'These counts are recorded by the app on <strong>our own server</strong> &mdash; no Google '
			. 'Analytics, no third-party tracking SDK, which is what the app&rsquo;s privacy policy '
			. 'promises customers. No phone numbers, names or request references are ever stored here: '
			. 'only counts and categories, deleted automatically after '
			. (int) AUN_App_Events::RETENTION_DAYS . ' days.'
			. '</p>';

		echo '<p>';
		foreach ( array( 7, 30, 90 ) as $d ) {
			$url = admin_url( 'admin.php?page=aun-app-usage&days=' . $d );
			echo $d === $days
				? '<strong style="margin-right:12px">Last ' . $d . ' days</strong>'
				: '<a href="' . esc_url( $url ) . '" style="margin-right:12px">Last ' . $d . ' days</a>';
		}
		echo '</p>';

		$totals = AUN_App_Events::totals( $days );
		if ( empty( $totals ) ) {
			echo '<div class="notice notice-info"><p>Nothing recorded yet. Events arrive in small '
				. 'batches while people use the app, so give it a day after releasing a build.</p></div></div>';
			return;
		}

		$by = array();
		foreach ( $totals as $r ) {
			$by[ $r->event ] = $r;
		}
		$hits = function ( $name ) use ( $by ) {
			return isset( $by[ $name ] ) ? (int) $by[ $name ]->hits : 0;
		};

		// ── The standing questions, answered in words ──
		echo '<h2>The questions we built this to answer</h2>';
		echo '<table class="widefat striped" style="max-width:920px"><tbody>';

		$fs = $hits( 'finder_started' );
		$fr = $hits( 'finder_result' );
		$fp = $hits( 'finder_product_opened' );
		echo '<tr><td style="width:38%"><strong>Does the projector finder get used?</strong></td><td>'
			. $fs . ' opened &middot; ' . $fr . ' reached a result &middot; <strong>' . $fp . '</strong> opened a product'
			. ( $fs > 0 && $fp === 0 ? ' &mdash; <span style="color:#b32d2e">people finish it but never open a projector</span>' : '' )
			. ( 0 === $fs ? ' &mdash; <span style="color:#b32d2e">nobody is finding it</span>' : '' )
			. '</td></tr>';

		$po = $hits( 'parts_form_opened' );
		$ps = $hits( 'parts_submitted' );
		echo '<tr><td><strong>Do people finish the parts request form?</strong></td><td>'
			. $po . ' opened &rarr; <strong>' . $ps . '</strong> submitted'
			. ( $po > 0 ? ' (' . round( $ps / $po * 100 ) . '%)' : '' )
			. '</td></tr>';

		$qa = $hits( 'quote_answered' );
		echo '<tr><td><strong>Are quotes answered in the app?</strong></td><td>'
			. '<strong>' . $qa . '</strong> answered in-app over this period. '
			. '<span style="color:#646970">Compare with the total decisions in Spare Parts &rarr; Requests: '
			. 'the difference is how many still reply by SMS link.</span></td></tr>';

		echo '<tr><td><strong>Does Home&rsquo;s status strip get tapped?</strong></td><td>'
			. $hits( 'home_strip_tap' ) . ' taps</td></tr>';

		echo '<tr><td><strong>Is published content read?</strong></td><td>'
			. $hits( 'content_opened' ) . ' opened</td></tr>';

		echo '<tr><td><strong>Does &ldquo;what to watch&rdquo; earn its place?</strong></td><td>'
			. $hits( 'watch_opened' ) . ' opened</td></tr>';

		$np = AUN_App_Events::breakdown( 'notifications_prompted', 'granted', $days );
		$yes = 0; $no = 0;
		foreach ( $np as $row ) {
			if ( '1' === (string) $row['value'] ) { $yes = (int) $row['hits']; }
			elseif ( '0' === (string) $row['value'] ) { $no = (int) $row['hits']; }
		}
		echo '<tr><td><strong>Do people allow notifications?</strong></td><td>'
			. ( $yes + $no > 0
				? '<strong>' . round( $yes / max( 1, $yes + $no ) * 100 ) . '%</strong> allowed (' . $yes . ' of ' . ( $yes + $no ) . ')'
					. ' <span style="color:#646970">&mdash; this is the ceiling on every push feature.</span>'
				: 'no one has been asked yet' )
			. '</td></tr>';
		echo '</tbody></table>';

		// ── Breakdowns worth having ──
		$cuts = array(
			array( 'service_opened', 'service', 'Which service people open' ),
			array( 'quote_answered', 'decision', 'Quote decisions' ),
			array( 'content_opened', 'type', 'Which content type' ),
			array( 'home_strip_tap', 'kind', 'Home strip taps' ),
			array( 'finder_step', 'step', 'Finder: how far people get' ),
			array( 'app_problem', 'where', 'Handled failures' ),
		);
		echo '<h2>Breakdowns</h2><div style="display:flex;flex-wrap:wrap;gap:18px">';
		foreach ( $cuts as $c ) {
			$rows = AUN_App_Events::breakdown( $c[0], $c[1], $days );
			if ( empty( $rows ) ) {
				continue;
			}
			echo '<table class="widefat striped" style="width:300px"><thead><tr><th colspan="2">'
				. esc_html( $c[2] ) . '</th></tr></thead><tbody>';
			foreach ( array_slice( $rows, 0, 8 ) as $r ) {
				echo '<tr><td>' . esc_html( $r['value'] ) . '</td><td style="text-align:right"><strong>'
					. (int) $r['hits'] . '</strong></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';

		// ── Everything, for completeness ──
		echo '<h2>All events</h2>';
		echo '<table class="widefat striped" style="max-width:620px"><thead><tr>'
			. '<th>Event</th><th style="text-align:right">Times</th><th style="text-align:right">People</th>'
			. '</tr></thead><tbody>';
		foreach ( $totals as $r ) {
			echo '<tr><td><code>' . esc_html( $r->event ) . '</code></td>'
				. '<td style="text-align:right">' . (int) $r->hits . '</td>'
				. '<td style="text-align:right">' . (int) $r->people . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<p class="description" style="max-width:820px;margin-top:14px">'
			. '<strong>People</strong> counts logged-in accounts only, so it is always lower than '
			. '<strong>Times</strong> &mdash; the finder and the content library work without logging in, '
			. 'and those visits are counted but cannot be attributed to anyone.</p>';

		echo '</div>';
	}

	public function page_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'No permission' );
		}

		$opts = aun_app_api_get_options();

		// Seed / refresh the admin-bar ticket queue while an admin is already
		// here. The 10-minute cron owns it normally; this just means the
		// indicator works immediately after setup instead of after the first
		// cron run. Only on THIS page — never on a general page load.
		if ( class_exists( 'AUN_App_Tickets' ) && AUN_App_Tickets::configured() ) {
			$snap = AUN_App_Tickets::queue();
			if ( empty( $snap ) || ( time() - (int) ( $snap['checked'] ?? 0 ) ) > 10 * MINUTE_IN_SECONDS ) {
				AUN_App_Tickets::refresh_queue();
			}
		}

		// "Send test ticket": a REAL end-to-end create through the bridge from
		// this server — shows the exact outcome so no error log is needed.
		if ( isset( $_POST['aun_tickets_test_nonce'] ) && wp_verify_nonce( $_POST['aun_tickets_test_nonce'], 'aun_app_tickets_test' ) ) {
			$me     = array(
				'user_id'       => 0,
				'customer_name' => 'Bridge Test',
				'phone'         => '8801700000000',
				'email'         => '',
			);
			$result = AUN_App_Tickets::create(
				$me,
				0, // let the bridge pick the default public topic
				'AUN App bridge test — please delete',
				'Automatic connectivity test sent from AUN App - Settings. Safe to delete this ticket.',
				'',
				'admin-test'
			);
			if ( is_wp_error( $result ) ) {
				// The app sees only a friendly message; here (admin only) show the
				// real technical cause carried in the error data / logs.
				$data     = $result->get_error_data();
				$detail   = is_array( $data ) && ! empty( $data['detail'] ) ? (string) $data['detail'] : $result->get_error_message();
				echo '<div class="notice notice-error"><p><strong>Test ticket FAILED:</strong> [' . esc_html( $result->get_error_code() ) . '] '
					. esc_html( $detail ) . '</p><p class="description">This technical detail is shown to admins only — customers only ever see a friendly "try again" message.</p></div>';
			} else {
				echo '<div class="notice notice-success"><p><strong>Test ticket created ✓</strong> — osTicket number <code>'
					. esc_html( (string) $result['number'] ) . '</code>. The pipeline works end-to-end; delete the ticket in the Agent Panel.</p></div>';
			}
		}

		// "Send test reminder": a REAL maintenance notice + push, right now,
		// to any phone that has an app account — the only way to see one
		// without waiting on the real 30/60/90-day schedule.
		if ( isset( $_POST['aun_maint_test_nonce'] ) && wp_verify_nonce( $_POST['aun_maint_test_nonce'], 'aun_app_maint_test' ) ) {
			$result = AUN_App_Notices::send_test_maintenance(
				(string) ( $_POST['maint_test_phone'] ?? '' ),
				(int) ( $_POST['maint_test_offset'] ?? 30 )
			);
			$class  = ! empty( $result['ok'] ) ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . '"><p><strong>Maintenance reminder test:</strong> '
				. esc_html( (string) $result['message'] ) . '</p></div>';
		}

		// "Test TMDB": a live check of the "what to watch" key.
		if ( isset( $_POST['aun_tmdb_test_nonce'] ) && wp_verify_nonce( $_POST['aun_tmdb_test_nonce'], 'aun_app_tmdb_test' ) ) {
			$diag  = AUN_App_Watch::diagnostic();
			$class = ! empty( $diag['ok'] ) ? 'notice-success' : 'notice-warning';
			echo '<div class="notice ' . esc_attr( $class ) . '"><p><strong>TMDB test:</strong> ' . esc_html( (string) $diag['message'] )
				. '</p><p class="description">Detected credential: <code>' . esc_html( (string) $diag['key_type'] ) . '</code>.</p></div>';
		}

		// "Test Chorki now": pull their list live and report, title by title,
		// what was found and what TMDB agreed to. This is the screen somebody
		// opens when the rail looks wrong, so it shows the WORKING, not a
		// verdict — which titles matched, which fell back to Chorki's own data,
		// and which extraction strategy each page yielded.
		if ( isset( $_POST['aun_chorki_test_nonce'] ) && wp_verify_nonce( $_POST['aun_chorki_test_nonce'], 'aun_app_chorki_test' ) ) {
			$diag  = AUN_App_Chorki::diagnostic();
			$class = ! empty( $diag['ok'] ) ? 'notice-success' : 'notice-warning';
			echo '<div class="notice ' . esc_attr( $class ) . '"><p><strong>Chorki test:</strong> '
				. esc_html( (string) $diag['message'] ) . '</p>';
			if ( ! empty( $diag['rows'] ) ) {
				echo '<table class="widefat striped" style="max-width:900px;margin:8px 0 12px">'
					. '<thead><tr><th>Title</th><th>Chorki page</th><th>Read via</th><th>TMDB</th></tr></thead><tbody>';
				foreach ( (array) $diag['rows'] as $r ) {
					echo '<tr><td>' . esc_html( (string) ( $r['title'] ?: '—' ) ) . '</td>'
						. '<td><code>' . esc_html( (string) $r['path'] ) . '</code></td>'
						. '<td>' . esc_html( (string) ( $r['via'] ?? '—' ) ) . '</td>'
						. '<td>' . ( ! empty( $r['matched'] )
							? '<code>' . esc_html( (string) $r['matched'] ) . '</code>'
							: '<span style="color:#646970">' . esc_html( (string) $r['note'] ) . '</span>' )
						. '</td></tr>';
				}
				echo '</tbody></table>';
				echo '<p class="description">"Read via" should say <code>json-ld</code> for every row. '
					. 'If it starts saying <code>slug</code>, Chorki changed their page and only the '
					. 'title survives — the rail still works, but it is time to look.</p>';
			}
			echo '</div>';
		}

		// "Rebuild picks now": throw away the stored list and fetch a fresh one
		// in this request. Needed after a plugin upgrade adds fields to a pick,
		// and whenever the admin just wants the rail refreshed immediately
		// instead of waiting for the twice-daily cron.
		if ( isset( $_POST['aun_watch_rebuild_nonce'] ) && wp_verify_nonce( $_POST['aun_watch_rebuild_nonce'], 'aun_app_watch_rebuild' ) ) {
			delete_option( AUN_App_Watch::STORE_KEY );
			delete_transient( AUN_App_Watch::CACHE_KEY );
			$fresh = AUN_App_Watch::picks( true );
			$rich  = 0;
			foreach ( (array) $fresh as $p ) {
				if ( ! empty( $p['genres'] ) || ! empty( $p['cast'] ) || '' !== (string) ( $p['runtime'] ?? '' ) ) {
					$rich++;
				}
			}
			echo '<div class="notice notice-success"><p><strong>What to watch rebuilt:</strong> '
				. esc_html( (string) count( $fresh ) ) . ' titles, ' . esc_html( (string) $rich )
				. ' with full details (genre / runtime / cast).</p>'
				. ( 0 === $rich && count( $fresh ) > 0
					? '<p class="description">No details came back — check the TMDB key with the button above.</p>'
					: '' )
				. '</div>';
		}

		// "Why can't this customer see it?" — answers the question with the
		// actual payload rather than a theory. Added after the referral card was
		// reported missing three times: two of those rounds were spent guessing
		// because nobody could see what the customer's own account returns.
		$diag = null;
		if ( isset( $_POST['aun_referral_diag_nonce'] ) && wp_verify_nonce( $_POST['aun_referral_diag_nonce'], 'aun_app_referral_diag' ) ) {
			$diag = $this->referral_diagnose( (string) ( $_POST['referral_diag_phone'] ?? '' ) );
		}

		if ( isset( $_POST['aun_purge_orders_nonce'] ) && wp_verify_nonce( $_POST['aun_purge_orders_nonce'], 'aun_app_purge_orders' ) ) {
			$purged = AUN_App_Services::purge_empty_orders( false );
			echo '<div class="notice notice-success is-dismissible"><p>Moved ' . (int) $purged['trashed']
				. ' empty order(s) to Trash. They can be restored from WooCommerce &rarr; Orders &rarr; Trash.</p></div>';
		}

		if ( isset( $_POST['aun_pay_error_clear_nonce'] ) && wp_verify_nonce( $_POST['aun_pay_error_clear_nonce'], 'aun_app_pay_error_clear' ) ) {
			delete_option( 'aun_app_last_pay_error' );
			echo '<div class="notice notice-success is-dismissible"><p>Cleared.</p></div>';
		}

		// Test reset. TWO steps by design: the first click only ever reports
		// what exists, and deleting requires a second, explicit confirmation.
		// This destroys real coupons, so a single mistyped digit must not be
		// able to wipe a genuine customer's referral history.
		$reset = null;
		if ( isset( $_POST['aun_referral_reset_nonce'] ) && wp_verify_nonce( $_POST['aun_referral_reset_nonce'], 'aun_app_referral_reset' ) ) {
			$reset_phone = (string) ( $_POST['referral_reset_phone'] ?? '' );
			$confirmed   = ! empty( $_POST['referral_reset_confirm'] );
			$reset       = AUN_App_Referrals::reset_for_phone( $reset_phone, ! $confirmed );
			$reset['confirmed'] = $confirmed;

			if ( $confirmed && ! empty( $reset['ok'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( (string) $reset['message'] ) . '</p></div>';
			} elseif ( empty( $reset['ok'] ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( (string) $reset['message'] ) . '</p></div>';
			}
		}

		// "Release the phone lock": the support override for a customer who is
		// checking out under a number other than the one they verified.
		if ( isset( $_POST['aun_referral_unlock_nonce'] ) && wp_verify_nonce( $_POST['aun_referral_unlock_nonce'], 'aun_app_referral_unlock' ) ) {
			$res   = AUN_App_Referrals::unlock_coupon( (string) ( $_POST['referral_unlock_code'] ?? '' ) );
			$class = ! empty( $res['ok'] ) ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) $res['message'] ) . '</p></div>';
		}

		if ( isset( $_POST['aun_settings_nonce'] ) && wp_verify_nonce( $_POST['aun_settings_nonce'], 'aun_app_settings_save' ) ) {
			$opts['whatsapp_number']     = preg_replace( '/[^\d+]/', '', (string) ( $_POST['whatsapp_number'] ?? '' ) );
			$opts['support_phone']       = sanitize_text_field( $_POST['support_phone'] ?? '' );
			$opts['support_hours']       = sanitize_text_field( $_POST['support_hours'] ?? '' );
			$opts['analytics_enabled']   = ! empty( $_POST['analytics_enabled'] ) ? 1 : 0;
			$opts['repair_ship_name']    = sanitize_text_field( $_POST['repair_ship_name'] ?? '' );
			$opts['repair_ship_phone']   = sanitize_text_field( $_POST['repair_ship_phone'] ?? '' );
			$opts['repair_ship_address'] = sanitize_textarea_field( wp_unslash( $_POST['repair_ship_address'] ?? '' ) );
			$opts['repair_ship_note']    = sanitize_textarea_field( wp_unslash( $_POST['repair_ship_note'] ?? '' ) );
			$opts['repair_ship_city']    = sanitize_text_field( $_POST['repair_ship_city'] ?? '' );
			$opts['repair_ship_zone']    = sanitize_text_field( $_POST['repair_ship_zone'] ?? '' );
			$opts['repair_ship_area']    = sanitize_text_field( $_POST['repair_ship_area'] ?? '' );
			$opts['facebook_url']        = esc_url_raw( $_POST['facebook_url'] ?? '' );
			$opts['website_url']         = esc_url_raw( $_POST['website_url'] ?? '' );
			$opts['announcement']        = sanitize_textarea_field( $_POST['announcement'] ?? '' );
			$opts['banners']             = sanitize_textarea_field( $_POST['banners'] ?? '' );
			$opts['discount_note']       = sanitize_text_field( $_POST['discount_note'] ?? '' );
			$opts['latest_version_code'] = max( 1, (int) ( $_POST['latest_version_code'] ?? 1 ) );
			$opts['latest_version_name'] = sanitize_text_field( $_POST['latest_version_name'] ?? '1.0.0' );
			$opts['min_version_code']    = max( 1, (int) ( $_POST['min_version_code'] ?? 1 ) );
			$opts['apk_url']             = esc_url_raw( $_POST['apk_url'] ?? '' );
			$opts['release_notes']       = sanitize_textarea_field( wp_unslash( $_POST['release_notes'] ?? '' ) );
			$opts['token_days']          = max( 1, min( 730, (int) ( $_POST['token_days'] ?? 180 ) ) );
			$opts['login_video_url']     = esc_url_raw( $_POST['login_video_url'] ?? '' );
			$opts['repair_api_key']      = sanitize_text_field( $_POST['repair_api_key'] ?? '' );
			// Raw JSON — sanitizing would corrupt the private key. Validated on read.
			$opts['fcm_service_account'] = trim( (string) wp_unslash( $_POST['fcm_service_account'] ?? '' ) );
			$opts['tickets_base_url']    = esc_url_raw( $_POST['tickets_base_url'] ?? '' );
			$opts['tickets_secret']      = trim( sanitize_text_field( $_POST['tickets_secret'] ?? '' ) );
			$opts['tickets_field_map']   = sanitize_textarea_field( wp_unslash( $_POST['tickets_field_map'] ?? '' ) );
			$opts['tmdb_api_key']        = trim( sanitize_text_field( $_POST['tmdb_api_key'] ?? '' ) );
			$opts['watch_local_picks']   = self::collect_local_picks( $_POST['watch_pick'] ?? array() );
			// Correcting a wrong TMDB id must show the right film NOW, not when
			// a week-long cache expires.
			if ( method_exists( 'AUN_App_Watch', 'flush_local_meta' ) ) {
				AUN_App_Watch::flush_local_meta();
			}
			$opts['watch_limit']         = max( 1, min( AUN_App_Watch::MAX_LIMIT, (int) ( $_POST['watch_limit'] ?? AUN_App_Watch::GLOBAL_LIMIT ) ) );
			$chorki_was                  = ! empty( $opts['chorki_enabled'] );
			$opts['chorki_enabled']      = empty( $_POST['chorki_enabled'] ) ? 0 : 1;
			$opts['chorki_limit']        = max( 1, min( AUN_App_Chorki::MAX_LIMIT, (int) ( $_POST['chorki_limit'] ?? AUN_App_Chorki::LIMIT ) ) );
			$opts['chorki_list_url']     = esc_url_raw( trim( (string) ( $_POST['chorki_list_url'] ?? '' ) ) );
			// Switching it on should show something without the admin hunting for a
			// second button; the build itself still happens on cron, off this
			// request, because it is five-plus HTTP calls to another site.
			if ( ! $chorki_was && ! empty( $opts['chorki_enabled'] ) ) {
				delete_option( AUN_App_Chorki::STORE_KEY );
			}
			$opts['youtube_api_key']     = trim( sanitize_text_field( $_POST['youtube_api_key'] ?? '' ) );
			// Our own OneDrive app registration (makes firmware downloads
			// independent of the WP File Download plugin).
			$opts['onedrive_client_id']     = trim( sanitize_text_field( $_POST['onedrive_client_id'] ?? '' ) );
			$opts['onedrive_client_secret'] = trim( sanitize_text_field( $_POST['onedrive_client_secret'] ?? '' ) );
			$opts['onedrive_tenant']        = trim( sanitize_text_field( $_POST['onedrive_tenant'] ?? '' ) );
			$opts['onedrive_refresh_token'] = trim( sanitize_text_field( $_POST['onedrive_refresh_token'] ?? '' ) );

			// Referral programme. Amounts are clamped rather than trusted: a
			// mistyped 1000% discount would give the store away, and there is no
			// legitimate reason for any of these to exceed their ceiling.
			$opts['referral_enabled']         = empty( $_POST['referral_enabled'] ) ? 0 : 1;
			$opts['referral_friend_type']     = in_array( ( $_POST['referral_friend_type'] ?? 'percent' ), array( 'percent', 'fixed' ), true )
				? $_POST['referral_friend_type'] : 'percent';
			$friend_max = 'percent' === $opts['referral_friend_type'] ? 50 : 100000;
			$opts['referral_friend_amount']   = max( 0, min( $friend_max, (float) ( $_POST['referral_friend_amount'] ?? 0 ) ) );
			$opts['referral_referrer_type']   = in_array( ( $_POST['referral_referrer_type'] ?? 'fixed' ), array( 'percent', 'fixed' ), true )
				? $_POST['referral_referrer_type'] : 'fixed';
			$referrer_max = 'percent' === $opts['referral_referrer_type'] ? 50 : 100000;
			$opts['referral_referrer_amount'] = max( 0, min( $referrer_max, (float) ( $_POST['referral_referrer_amount'] ?? 0 ) ) );
			$opts['referral_require_customer'] = empty( $_POST['referral_require_customer'] ) ? 0 : 1;
			$opts['referral_min_order']       = max( 0, (float) ( $_POST['referral_min_order'] ?? 0 ) );
			$opts['referral_monthly_cap']     = max( 0, min( 100, (int) ( $_POST['referral_monthly_cap'] ?? 5 ) ) );
			$opts['referral_claim_days']      = max( 0, min( 365, (int) ( $_POST['referral_claim_days'] ?? 30 ) ) );
			$opts['referral_expiry_days']     = max( 1, min( 730, (int) ( $_POST['referral_expiry_days'] ?? 90 ) ) );
			$opts['referral_test_phones']     = sanitize_textarea_field( wp_unslash( $_POST['referral_test_phones'] ?? '' ) );
			$opts['sslc_store_id']            = trim( sanitize_text_field( $_POST['sslc_store_id'] ?? '' ) );
			$opts['sslc_store_pass']          = trim( sanitize_text_field( $_POST['sslc_store_pass'] ?? '' ) );
			$opts['sslc_sandbox']             = empty( $_POST['sslc_sandbox'] ) ? 0 : 1;
			$opts['referral_reward_status']   = AUN_App_Referrals::clean_status( $_POST['referral_reward_status'] ?? 'completed' );
			// 0 is meaningful here: the referrer's earned reward never expires.
			$opts['referral_reward_expiry_days'] = max( 0, min( 3650, (int) ( $_POST['referral_reward_expiry_days'] ?? 365 ) ) );
			$opts['referral_reward_also_completed'] = empty( $_POST['referral_reward_also_completed'] ) ? 0 : 1;
			delete_transient( 'aun_app_od_token' ); // re-mint with the new settings
			update_option( AUN_APP_API_OPTION, $opts );
			delete_transient( AUN_App_Tickets::TOPICS_CACHE );
			delete_transient( AUN_App_Watch::CACHE_KEY ); // fresh picks on save
			echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
		}

		$this->header( 'App Settings', 'Everything the Android app reads from this site — grouped by what it affects.' );

		// One form across every section, so a single Save covers the lot and
		// switching sections can never lose a half-typed change. The section
		// buttons show and hide panels; they do not navigate.
		$sections = array(
			'support'  => array( 'sos', 'Support &amp; contact' ),
			'screens'  => array( 'smartphone', 'App screens' ),
			'notify'   => array( 'bell', 'Notifications' ),
			'connect'  => array( 'admin-plugins', 'Integrations' ),
			'watch'    => array( 'video-alt2', 'What to watch' ),
			'referral' => array( 'groups', 'Referrals' ),
			'release'  => array( 'update', 'App release' ),
		);
		?>
		<div class="aun-settings">
			<nav class="aun-nav" id="aun-settings-nav">
				<?php foreach ( $sections as $key => $s ) : ?>
					<a href="#<?php echo esc_attr( $key ); ?>" data-panel="<?php echo esc_attr( $key ); ?>">
						<span class="dashicons dashicons-<?php echo esc_attr( $s[0] ); ?>"></span>
						<?php echo wp_kses_post( $s[1] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div>
		<form method="post">
			<?php wp_nonce_field( 'aun_app_settings_save', 'aun_settings_nonce' ); ?>

			<!-- ── Support & contact ─────────────────────────────────────── -->
			<div class="aun-panel" id="panel-support">
			<div class="aun-card">
				<h2><span class="dashicons dashicons-sos"></span> Support &amp; contact</h2>
				<p class="aun-hint">How customers reach you from inside the app — the Support tab and the
					tap-to-call and chat buttons.</p>
				<table class="form-table">
					<tr><th>WhatsApp number</th><td><input name="whatsapp_number" value="<?php echo esc_attr( $opts['whatsapp_number'] ); ?>" placeholder="8801XXXXXXXXX" /><p class="description">Digits only, international format — used for the wa.me chat button.</p></td></tr>
					<tr><th>Support phone</th><td><input name="support_phone" value="<?php echo esc_attr( $opts['support_phone'] ); ?>" placeholder="09XXXXXXXX" /><p class="description">Tap-to-call number.</p></td></tr>
					<tr><th>Support hours</th><td><input name="support_hours" style="width:320px" value="<?php echo esc_attr( $opts['support_hours'] ); ?>" /></td></tr>
					<tr><th>App analytics</th><td>
						<label><input type="checkbox" name="analytics_enabled" <?php checked( ! isset( $opts['analytics_enabled'] ) || (int) $opts['analytics_enabled'] === 1, true ); ?> /> Collect anonymous usage &amp; crash reports</label>
						<p class="description" style="max-width:780px">
							Firebase Analytics + Crashlytics. <strong>Crash reports are the important half</strong>
							&mdash; without them an app that crashes on a customer's phone tells us nothing at all.
							Usage events are counts and categories only: <strong>no phone numbers, names,
							addresses, serials or request references are ever sent</strong>.
							<br />
							Unticking this stops collection inside the app itself on the next launch, with no new
							APK. If you turn it off, update the Play Store <em>Data safety</em> declaration to match.
						</p>
					</td></tr>
					<tr><th colspan="2" style="padding-top:18px"><h3 style="margin:0">Customer messages</h3></th></tr>
					<tr><th>SMS wording</th><td>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aun-app-messages' ) ); ?>">Edit customer messages</a>
						<p class="description">Login code and repair SMS live on their own page &mdash; <strong>AUN App &rarr; Messages</strong>.</p>
					</td></tr>
					<tr><th colspan="2" style="padding-top:18px"><h3 style="margin:0">Where customers post a projector for repair</h3></th></tr>
					<tr><th>Send-to name</th><td><input name="repair_ship_name" style="width:320px" value="<?php echo esc_attr( (string) ( $opts['repair_ship_name'] ?? '' ) ); ?>" placeholder="AUN Care Service Centre" /></td></tr>
					<tr><th>Send-to phone</th><td><input name="repair_ship_phone" style="width:220px" value="<?php echo esc_attr( (string) ( $opts['repair_ship_phone'] ?? '' ) ); ?>" placeholder="01XXXXXXXXX" /> <span class="description">every courier form asks for a receiver's number</span></td></tr>
					<tr><th>Send-to address</th><td>
						<textarea name="repair_ship_address" rows="3" style="width:100%;max-width:640px" placeholder="House 00, Road 00, Area, Dhaka 1200"><?php echo esc_textarea( (string) ( $opts['repair_ship_address'] ?? '' ) ); ?></textarea>
						<p class="description" style="max-width:640px">
							Shown in the app the moment you <strong>approve</strong> a repair request, with a
							Copy button and step-by-step instructions. <strong>Leave this blank and the card
							does not appear at all</strong> — the app then tells the customer we will message
							them the address, which is the truth. It never invents one.
						</p>
					</td></tr>
					<tr><th>Pathao City / Zone / Area</th><td>
						<input name="repair_ship_city" style="width:180px" value="<?php echo esc_attr( (string) ( $opts['repair_ship_city'] ?? '' ) ); ?>" placeholder="Dhaka" />
						<input name="repair_ship_zone" style="width:200px" value="<?php echo esc_attr( (string) ( $opts['repair_ship_zone'] ?? '' ) ); ?>" placeholder="Mazar road" />
						<input name="repair_ship_area" style="width:260px" value="<?php echo esc_attr( (string) ( $opts['repair_ship_area'] ?? '' ) ); ?>" placeholder="Mirpur Buddhijibi Koborsthan" />
						<p class="description" style="max-width:640px">
							Pathao's booking form asks for these three as <strong>dropdowns</strong>, and
							choosing the wrong Zone or Area is the commonest way a parcel reaches the wrong
							hub. Type them <strong>exactly as they appear in Pathao's own list</strong> — the
							app shows each one as a separate tap-to-copy row, mirroring the block already on
							the website so a customer sees the same thing in both places.
						</p>
						<p class="description" style="max-width:640px">Leave blank if you do not use Pathao; the address above is enough for every counter-based courier.</p>
					</td></tr>
					<tr><th>Extra instructions</th><td>
						<textarea name="repair_ship_note" rows="2" style="width:100%;max-width:640px" placeholder="Counter open 10am–8pm, closed Friday."><?php echo esc_textarea( (string) ( $opts['repair_ship_note'] ?? '' ) ); ?></textarea>
						<p class="description">Optional. Anything specific to your counter — opening hours, a landmark, which gate.</p>
					</td></tr>
					<tr><th>Facebook page</th><td><input name="facebook_url" style="width:100%" value="<?php echo esc_attr( $opts['facebook_url'] ); ?>" placeholder="https://facebook.com/..." /></td></tr>
					<tr><th>Website URL</th><td><input name="website_url" style="width:100%" value="<?php echo esc_attr( $opts['website_url'] ); ?>" placeholder="Blank = this site" /></td></tr>
				</table>
			</div>

			</div><!-- /panel-support -->

			<!-- ── App screens ───────────────────────────────────────────── -->
			<div class="aun-panel" id="panel-screens">
			<div class="aun-card">
				<h2><span class="dashicons dashicons-smartphone"></span> App screens</h2>
				<p class="aun-hint">What customers see when they open the app. Every one of these is live the
					moment you save — none of them needs an app rebuild.</p>

				<h3>Home screen</h3>
				<table class="form-table">
					<tr><th>Announcement</th><td><textarea name="announcement" rows="2" style="width:100%" placeholder="Short notice shown at the top of the app home screen. Bangla is fine."><?php echo esc_textarea( $opts['announcement'] ); ?></textarea></td></tr>
					<tr><th>Banners</th><td><textarea name="banners" rows="3" style="width:100%" placeholder="https://site/banner1.jpg | https://site/offer-page&#10;https://site/banner2.jpg"><?php echo esc_textarea( $opts['banners'] ); ?></textarea><p class="description">One per line: <code>image_url | optional_link</code></p></td></tr>
					<tr><th>Discount note</th><td><input name="discount_note" style="width:100%" value="<?php echo esc_attr( $opts['discount_note'] ); ?>" placeholder="e.g. অ্যাপ থেকে অর্ডারে বিশেষ ছাড়!" /></td></tr>
				</table>

				<h3>Login screen</h3>
				<table class="form-table">
					<tr><th>Background video</th><td>
						<input name="login_video_url" id="aun-video-url" style="width:70%" value="<?php echo esc_attr( $opts['login_video_url'] ); ?>" placeholder="https://.../login-video.mp4" />
						<button type="button" class="button" id="aun-video-btn">Choose video</button>
						<p class="description">MP4, ~720p, keep it a few MB — plays muted in a loop behind the app login screen. The app downloads it once and re-uses it. Change or clear anytime; no app rebuild needed.</p>
					</td></tr>
				</table>
			</div>
			</div><!-- /panel-screens -->

			<!-- ── Notifications ─────────────────────────────────────────── -->
			<div class="aun-panel" id="panel-notify">
			<div class="aun-card">
				<h2><span class="dashicons dashicons-bell"></span> Push notifications (Firebase)</h2>
				<p class="aun-hint">Without this, notifications still appear inside the app — they just cannot
					reach a phone that is not open.</p>
				<table class="form-table">
					<tr><th>Service-account JSON</th><td>
						<textarea name="fcm_service_account" rows="4" style="width:100%;font-family:monospace;font-size:11px" placeholder='Paste the whole file: {"type":"service_account","project_id":"aun-projector",...}'><?php echo esc_textarea( $opts['fcm_service_account'] ); ?></textarea>
						<p class="description">
							Firebase Console → Project settings → Service accounts → <em>Generate new private key</em> —
							paste the downloaded file's full contents here. Stored in the database, never as a public file.<br>
							Status: <?php echo AUN_App_Push::configured()
								? '<span class="aun-status-ok">✓ configured</span> — new content, repair decisions and maintenance reminders are pushed to phones.'
								: '<span class="aun-status-warn">not configured</span> — notifications appear in-app only.'; ?>
						</p>
					</td></tr>
				</table>
			</div>

			<div class="aun-card">
				<h2><span class="dashicons dashicons-heart"></span> Notification health</h2>
				<p class="aun-hint">
					Notifications arrive by three different routes and they fail in different ways, so
					"notifications don't work" is never one problem. This shows each route separately.
				</p>
				<?php
				global $wpdb;
				$t_tok = aun_app_api_tokens_table();
				$t_not = $wpdb->prefix . 'aun_app_notices';

				$tokens_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t_tok WHERE fcm_token != '' AND expires_at > '" . esc_sql( current_time( 'mysql' ) ) . "'" );
				$tokens_old   = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM $t_tok WHERE fcm_token != '' AND expires_at > %s AND fcm_build < %d",
					current_time( 'mysql' ),
					AUN_App_Push::CHANNELS_V2_BUILD
				) );

				$crons = array(
					'aun_app_tickets_poll'   => 'Support-ticket replies (every 10 min)',
					'aun_app_repair_poll'    => 'Repair status from the ERP (every 10 min)',
					'aun_app_daily_notices'  => 'Maintenance reminders (daily)',
					'aun_app_verify_devices' => 'Device verification sweep (hourly)',
				);
				?>
				<table class="form-table">
					<tr><th>Push (Firebase)</th><td>
						<?php if ( AUN_App_Push::configured() ) : ?>
							<span class="aun-status-ok">✓ configured</span>
						<?php else : ?>
							<span class="aun-status-bad">not configured</span> — nothing can be pushed to a
							phone. In-app notifications still appear when the app is opened.
						<?php endif; ?>
					</td></tr>
					<tr><th>Phones registered</th><td>
						<strong><?php echo (int) $tokens_total; ?></strong> active device token(s).
						<?php if ( 0 === $tokens_total ) : ?>
							<br><span class="aun-status-warn">None.</span> A phone registers when the app is
							opened while logged in — if this is 0, no push can reach anyone.
						<?php elseif ( $tokens_old > 0 ) : ?>
							<br><span class="aun-status-warn"><?php echo (int) $tokens_old; ?> on an app build
							older than <?php echo (int) AUN_App_Push::CHANNELS_V2_BUILD; ?></span> — they are
							sent the older notification channels on purpose, because Android silently discards
							a notification naming a channel the app does not have. They will move over on their
							next update.
						<?php endif; ?>
					</td></tr>
					<tr><th>Background jobs</th><td>
						<?php
						$broken = 0;
						echo '<table class="widefat" style="max-width:640px"><tbody>';
						foreach ( $crons as $hook => $label ) {
							$next = wp_next_scheduled( $hook );
							if ( ! $next ) {
								$broken++;
							}
							echo '<tr><td style="width:58%">' . esc_html( $label ) . '</td><td>'
								. ( $next
									? '<span class="aun-status-ok">next ' . esc_html( human_time_diff( time(), $next ) ) . ( $next < time() ? ' ago (overdue)' : '' ) . '</span>'
									: '<span class="aun-status-bad">NOT SCHEDULED</span>' )
								. '</td></tr>';
						}
						echo '</tbody></table>';
						?>
						<p class="description" style="max-width:780px">
							<?php if ( $broken > 0 ) : ?>
								<span class="aun-status-bad">Some jobs are not scheduled</span> — deactivate and
								reactivate this plugin to restore them.
							<?php endif; ?>
							<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
								<br><span class="aun-status-warn">WP-Cron is disabled in wp-config.php.</span>
								A real server cron must be calling <code>wp-cron.php</code>, or these never run
								and ticket, repair and reminder notifications simply stop.
							<?php else : ?>
								<br>WordPress runs these on site visits. On a quiet site they can drift late;
								a real server cron every 5 minutes makes them punctual.
							<?php endif; ?>
						</p>
					</td></tr>
					<tr><th>Sent in the last 7 days</th><td>
						<?php
						$rows = (array) $wpdb->get_results( $wpdb->prepare(
							"SELECT type, COUNT(*) n FROM $t_not WHERE created_at > %s GROUP BY type ORDER BY n DESC",
							gmdate( 'Y-m-d H:i:s', strtotime( '-7 days', current_time( 'timestamp' ) ) )
						) );
						if ( empty( $rows ) ) {
							echo '<span class="aun-status-warn">Nothing at all.</span> If you expected some, the '
								. 'trigger never fired — that is a different problem from push not arriving.';
						} else {
							echo '<code>';
							foreach ( $rows as $r ) {
								echo esc_html( $r->type ) . ': ' . (int) $r->n . '&nbsp;&nbsp; ';
							}
							echo '</code><br><span class="description">These were CREATED. Whether each reached '
								. 'a phone depends on the two rows above.</span>';
						}
						?>
					</td></tr>
				</table>
			</div>

			<div class="aun-card">
				<h2><span class="dashicons dashicons-clock"></span> Maintenance reminder test</h2>
				<p class="aun-hint">
					Real dust-filter reminders arrive 30/60/90 days after an eligible purchase, so there is
					normally no way to see one sooner. This sends a REAL reminder right now to any number that
					has logged into the app at least once — notification panel, Home task card, and a push if
					Firebase is set up above. Test sends use their own dedup namespace, so they never collide
					with the real schedule and can be repeated.
				</p>
				<p style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0">
					<input type="text" name="maint_test_phone" form="aun-maint-test-form" placeholder="e.g. 01700000000" style="width:200px" />
					<select name="maint_test_offset" form="aun-maint-test-form">
						<option value="30">Day 30 — first reminder</option>
						<option value="60">Day 60 — second reminder</option>
						<option value="90">Day 90 — final notice</option>
					</select>
					<button type="submit" form="aun-maint-test-form" class="button button-secondary">Send test reminder</button>
				</p>
			</div>
			</div><!-- /panel-notify -->

			<!-- ── Integrations ──────────────────────────────────────────── -->
			<div class="aun-panel" id="panel-connect">
			<div class="aun-card">
				<h2><span class="dashicons dashicons-hammer"></span> Repair tracking (UltimatePOS)</h2>
				<table class="form-table">
					<tr><th>Repair status API key</th><td>
						<input name="repair_api_key" style="width:60%" value="<?php echo esc_attr( $opts['repair_api_key'] ); ?>" placeholder="Blank = use the SLB_ERP_API_KEY constant from wp-config.php" autocomplete="off" />
						<p class="description">
							Key for the ERP <code>/api/repair-status</code> endpoint (<code>REPAIR_TRACK_API_KEY</code> in the ERP <code>.env</code>) —
							the same one the website repair tracker uses. Leave blank to reuse the <code>SLB_ERP_API_KEY</code> constant.<br>
							Status: <?php echo AUN_App_ERP::repair_configured()
								? '<span class="aun-status-ok">✓ configured</span> — app repair requests auto-link to UltimatePOS job sheets and show the live repair status.'
								: '<span class="aun-status-warn">not configured</span> — the app will show only its own request statuses.'; ?>
						</p>
					</td></tr>
				</table>
			</div>

			<div class="aun-card">
				<h2><span class="dashicons dashicons-tickets-alt"></span> Support tickets (osTicket)</h2>
				<table class="form-table">
					<tr><th>osTicket site URL</th><td>
						<input name="tickets_base_url" style="width:60%" value="<?php echo esc_attr( $opts['tickets_base_url'] ); ?>" placeholder="https://support.smartliving.com.bd" />
						<p class="description">Root URL of the osTicket install (the <code>aun-app-bridge</code> folder must be uploaded there).</p>
					</td></tr>
					<tr><th>Bridge secret</th><td>
						<input name="tickets_secret" style="width:60%" value="<?php echo esc_attr( $opts['tickets_secret'] ); ?>" placeholder="Same long secret as bridge-config.php" autocomplete="off" />
						<p class="description">
							Must match <code>AUN_BRIDGE_SECRET</code> in <code>aun-app-bridge/bridge-config.php</code> on the support server (40+ random characters).<br>
							Status: <?php
							$ts_base   = rtrim( (string) $opts['tickets_base_url'], '/' );
							$ts_secret = (string) $opts['tickets_secret'];
							if ( '' === $ts_base && strlen( $ts_secret ) < 20 ) {
								echo '<span class="aun-status-warn">not configured</span> — fill in BOTH the site URL and the bridge secret above, then click <em>Save settings</em> at the bottom of this page.';
							} elseif ( '' === $ts_base ) {
								echo '<span class="aun-status-warn">the osTicket site URL is empty.</span> Enter <code>https://support.smartliving.com.bd</code> in the field above and click <em>Save settings</em>. (Secret looks set: ' . (int) strlen( $ts_secret ) . ' characters stored.)';
							} elseif ( strlen( $ts_secret ) < 20 ) {
								echo '<span class="aun-status-warn">the bridge secret is too short</span> — 20+ characters needed, but only ' . (int) strlen( $ts_secret ) . ' were stored. Re-paste it and click <em>Save settings</em>. (Site URL is set: <code>' . esc_html( $ts_base ) . '</code>.)';
							} else {
								$ping = AUN_App_Tickets::call( 'ping' );
								echo is_wp_error( $ping )
									? '<span class="aun-status-bad">✗ bridge unreachable:</span> ' . esc_html( $ping->get_error_message() )
									: '<span class="aun-status-ok">✓ connected</span> — osTicket v' . esc_html( (string) ( $ping['version'] ?? '?' ) ) . '. Tickets, replies and push alerts are live.';
							}
							?>
						</p>
					</td></tr>
					<tr><th>Custom form values</th><td>
						<textarea name="tickets_field_map" rows="3" style="width:100%;font-family:monospace;font-size:12px"><?php echo esc_textarea( $opts['tickets_field_map'] ); ?></textarea>
						<p class="description">
							Values sent into each help topic's custom form fields, one <code>variable = value</code> per line
							(variables are shown in osTicket → Manage → Forms). Tokens: <code>{invoice}</code> <code>{serial}</code>
							<code>{model}</code> <code>{phone}</code> <code>{name}</code>; <code>{a|b}</code> = first non-empty.
							Lines that resolve empty are skipped. Unknown variables are ignored by osTicket — safe for topics
							without that field.
						</p>
					</td></tr>
				</table>
				<?php if ( AUN_App_Tickets::configured() ) : ?>
				<p style="margin:14px 0 0;padding-top:16px;border-top:1px solid #f0f0f1">
					<button type="submit" form="aun-tickets-test-form" class="button button-secondary">Send test ticket</button>
					<span class="description" style="display:block;margin-top:6px;max-width:720px">
						Creates one REAL ticket through the bridge from this server and shows the exact result —
						the fastest way to diagnose "something went wrong" without reading an error log.
						Delete the test ticket in the Agent Panel afterwards.
					</span>
				</p>
				<?php endif; ?>
			</div>

			<?php
			// Surfaced here rather than left in the PHP error log, which most
			// site owners cannot reach. "There has been a critical error on
			// this website" is not a bug report; this is.
			$pay_err = get_option( 'aun_app_last_pay_error' );
			if ( is_array( $pay_err ) && ! empty( $pay_err['message'] ) ) : ?>
			<div class="aun-card" style="border-color:#f0b4b4">
				<h2><span class="dashicons dashicons-warning"></span> Last spare-parts payment failure</h2>
				<p class="aun-hint">
					A customer tapped <strong>Pay online</strong> and WooCommerce could not build the order.
					They were told to try again or pay cash on delivery — nothing was charged.
				</p>
				<table class="widefat" style="max-width:760px">
					<tbody>
						<tr><td style="width:120px;color:#50575e">When</td><td><?php echo esc_html( (string) $pay_err['time'] ); ?></td></tr>
						<tr><td style="color:#50575e">Request</td><td><code><?php echo esc_html( (string) $pay_err['ref'] ); ?></code></td></tr>
						<tr><td style="color:#50575e">Error</td><td><code><?php echo esc_html( (string) $pay_err['message'] ); ?></code></td></tr>
						<tr><td style="color:#50575e">Where</td><td><code style="font-size:11px"><?php echo esc_html( (string) $pay_err['where'] ); ?></code></td></tr>
					</tbody>
				</table>
				<p style="margin-top:12px">
					<button type="submit" form="aun-pay-error-clear-form" class="button">Clear this</button>
				</p>
			</div>
			<?php endif; ?>

			<div class="aun-card">
				<h2><span class="dashicons dashicons-money-alt"></span> In-app payments (SSLCommerz)</h2>
				<p class="aun-hint">
					When the app can create a payment session directly, the customer lands on the SSLCommerz
					screen itself — no checkout page, no terms box, no website in between. If it cannot, the
					app falls back to WooCommerce's own checkout, which still works. Either way the store
					credentials stay on this server and never go near the APK.
				</p>
				<?php
				$sslc = class_exists( 'AUN_App_SSLCommerz' ) ? AUN_App_SSLCommerz::credentials() : array( 'store_id' => '', 'source' => '', 'sandbox' => false );
				?>
				<table class="form-table">
					<tr><th>Status</th><td>
						<?php if ( '' !== $sslc['store_id'] ) : ?>
							<span class="aun-status-ok">✓ direct payment ready</span>
							— store <code><?php echo esc_html( $sslc['store_id'] ); ?></code>,
							read from <code><?php echo esc_html( $sslc['source'] ); ?></code>.
							<?php if ( ! empty( $sslc['sandbox'] ) ) : ?>
								<br><span class="aun-status-warn">SANDBOX mode — no real money will move.</span>
							<?php endif; ?>
						<?php else : ?>
							<span class="aun-status-warn">not found</span> — the app will use the website
							checkout instead. That works, but the customer passes through a page or two of
							the site. Fill the fields below to fix it.
						<?php endif; ?>
					</td></tr>
					<tr><th>Store ID</th><td>
						<input name="sslc_store_id" style="width:40%" value="<?php echo esc_attr( (string) ( $opts['sslc_store_id'] ?? '' ) ); ?>" placeholder="Leave blank to use the WooCommerce gateway's" autocomplete="off" />
					</td></tr>
					<tr><th>Store password</th><td>
						<input name="sslc_store_pass" type="password" style="width:40%" value="<?php echo esc_attr( (string) ( $opts['sslc_store_pass'] ?? '' ) ); ?>" placeholder="Leave blank to use the WooCommerce gateway's" autocomplete="new-password" />
						<p class="description" style="max-width:760px">
							Only needed if the status above says "not found". Two copies of a credential drift
							apart, and the one nobody remembers updating is the one that breaks at midnight.
						</p>
					</td></tr>
					<tr><th>Sandbox</th><td>
						<label><input type="checkbox" name="sslc_sandbox" value="1" <?php checked( ! empty( $opts['sslc_sandbox'] ) ); ?> />
							Use the SSLCommerz sandbox for these fields</label>
						<p class="description">Ignored when the credentials come from the WooCommerce gateway — its own sandbox setting is used.</p>
					</td></tr>
				</table>

				<?php
				// Debris from failed payment attempts. Shown only when there is
				// some — an empty tool is just clutter.
				$ghosts = class_exists( 'AUN_App_Services' ) ? AUN_App_Services::purge_empty_orders( true ) : array( 'count' => 0, 'ids' => array() );
				if ( $ghosts['count'] > 0 ) : ?>
				<h3>Empty orders to clean up</h3>
				<p class="description" style="max-width:780px">
					<strong><?php echo (int) $ghosts['count']; ?></strong> ৳0 &ldquo;Pending payment&rdquo;
					order(s) with no customer and no items:
					<code><?php echo esc_html( implode( ', #', array_map( 'strval', array_slice( $ghosts['ids'], 0, 12 ) ) ) ); ?></code><?php
					echo count( $ghosts['ids'] ) > 12 ? ' …' : ''; ?>.
					<br><br>
					WooCommerce creates an order shell first and fills it in a moment later. When something
					crashed in between — as a third-party plugin was doing before the session fix — the shell
					survived. New attempts now clean up after themselves; this clears what is already there.
					They are moved to <strong>Trash</strong>, not destroyed.
				</p>
				<p style="margin:0">
					<button type="submit" form="aun-purge-orders-form" class="button">Move these to Trash</button>
				</p>
				<?php endif; ?>
			</div>

			<div class="aun-card">
				<h2><span class="dashicons dashicons-cloud"></span> OneDrive / SharePoint (firmware &amp; manuals)</h2>
				<p class="aun-hint">
					Where the app fetches large firmware files and PDF manuals from. Nothing to do with the
					home-screen picks — this was previously filed under "What to watch", which is why it was
					impossible to find.
				</p>
				<table class="form-table">
					<tr><th>Connection</th><td>
						<?php
						$od_own  = '' !== trim( (string) $opts['onedrive_client_id'] ) && '' !== trim( (string) $opts['onedrive_client_secret'] );
						$od_wpfd = '' !== AUN_App_Content::wpfd_onedrive_token();
						$od_state = $od_own
							? array( '#0f7b3f', 'Using this plugin\'s own OneDrive connection — independent of any other plugin.' )
							: ( $od_wpfd
								? array( '#8a6d00', 'Currently borrowing the WP File Download plugin\'s OneDrive connection. It works, but downloads would fall back to public share links if you remove that plugin. Fill the fields below to be fully independent.' )
								: array( '#6b7280', 'Not connected. Public "Anyone with the link" OneDrive shares still download fine; fill these in only if you want authenticated access (e.g. org-restricted files).' ) );
						?>
						<p style="margin:0 0 10px;color:<?php echo esc_attr( $od_state[0] ); ?>"><strong><?php echo esc_html( $od_state[1] ); ?></strong></p>
					</td></tr>
					<tr><th>Client ID</th><td>
						<input name="onedrive_client_id" style="width:60%" value="<?php echo esc_attr( $opts['onedrive_client_id'] ); ?>" placeholder="Application (client) ID" autocomplete="off" />
					</td></tr>
					<tr><th>Client secret</th><td>
						<input name="onedrive_client_secret" type="password" style="width:60%" value="<?php echo esc_attr( $opts['onedrive_client_secret'] ); ?>" placeholder="Client secret VALUE" autocomplete="new-password" />
					</td></tr>
					<tr><th>Tenant ID</th><td>
						<input name="onedrive_tenant" style="width:60%" value="<?php echo esc_attr( $opts['onedrive_tenant'] ); ?>" placeholder="Directory (tenant) ID — or leave blank for 'common'" autocomplete="off" />
					</td></tr>
					<tr><th>Refresh token</th><td>
						<input name="onedrive_refresh_token" type="password" style="width:60%" value="<?php echo esc_attr( $opts['onedrive_refresh_token'] ); ?>" placeholder="Optional — only for a delegated (user) connection" autocomplete="new-password" />
						<p class="description" style="max-width:760px">
							Optional. Azure Portal → App registrations → your app: copy the <strong>Client ID</strong>
							and <strong>Tenant ID</strong>, then Certificates &amp; secrets → new client secret (copy the
							<em>Value</em>). Grant the <strong>Files.Read.All</strong> application permission and click
							“Grant admin consent”. Leave the refresh token blank for that app-only setup.
							Use <strong>App Content → any file → “Test download”</strong> to verify.
						</p>
					</td></tr>
				</table>
			</div>

			<div class="aun-card">
				<h2><span class="dashicons dashicons-video-alt3"></span> YouTube (video guides)</h2>
				<p class="aun-hint">
					For the per-model video guides in the app — also previously filed under "What to watch",
					which it has nothing to do with.
				</p>
				<table class="form-table">
					<tr><th>YouTube Data API key</th><td>
						<input name="youtube_api_key" style="width:60%" value="<?php echo esc_attr( $opts['youtube_api_key'] ); ?>" placeholder="Free key from Google Cloud → YouTube Data API v3" autocomplete="off" />
						<p class="description">
							Shows each <strong>video guide's real YouTube upload date</strong> in the app
							(instead of the date you added it here) — works for videos on <strong>any</strong>
							channel. Free: Google Cloud Console → enable “YouTube Data API v3” → create a key.
							<strong>Leave blank to reuse the key already saved in the AUN Tutorials plugin.</strong>
						</p>
					</td></tr>
				</table>
			</div>
			</div><!-- /panel-connect -->

			<!-- ── What to watch ─────────────────────────────────────────── -->
			<div class="aun-panel" id="panel-watch">
			<div class="aun-card">
				<h2><span class="dashicons dashicons-video-alt2"></span> What to watch (home screen picks)</h2>
				<p class="aun-hint">
					The film and series rail on the app home screen — what a projector owner might actually
					put on tonight. Trending titles come from TMDB; local platforms have no public API, so
					those are curated by you below.
				</p>
				<table class="form-table">
					<tr><th>TMDB API key</th><td>
						<input name="tmdb_api_key" style="width:60%" value="<?php echo esc_attr( $opts['tmdb_api_key'] ); ?>" placeholder="Free key from themoviedb.org/settings/api" autocomplete="off" />
						<p class="description">
							Enables this week's <strong>globally trending</strong> movies/series on the app home
							screen — filtered to titles actually streaming on an OTT platform (cinema-only
							releases are skipped). Free key: create a TMDB account → Settings → API.
							<strong>Either</strong> the "API Key (v3)" <strong>or</strong> the "API Read Access
							Token (v4)" works — we auto-detect which you paste. Blank = global picks off.
						</p>
						<?php // Save the key first, then use this to verify it live. ?>
						<button type="submit" form="aun-tmdb-test-form" class="button button-secondary" style="margin-top:4px">Test TMDB key</button>
						<button type="submit" form="aun-watch-rebuild-form" class="button button-secondary" style="margin-top:4px">Rebuild picks now</button>
						<span class="description" style="display:block;margin-top:6px">
							The picks list is cached and rebuilt twice a day. Use <strong>Rebuild picks now</strong>
							after updating this plugin, so newly added details (genre, runtime, cast) appear in the
							app straight away instead of after the next scheduled rebuild.
						</span>
					</td></tr>
					<tr><th>How many titles</th><td>
						<input name="watch_limit" type="number" min="1" max="<?php echo (int) AUN_App_Watch::MAX_LIMIT; ?>" style="width:90px"
							value="<?php echo (int) ( $opts['watch_limit'] ?: AUN_App_Watch::GLOBAL_LIMIT ); ?>" />
						<p class="description">
							Number of trending titles shown in the app's "What to watch" rail
							(max <?php echo (int) AUN_App_Watch::MAX_LIMIT; ?>). We walk several TMDB pages to fill it,
							skipping anything not streaming. Your local picks are shown first and are always in addition
							to this. Higher numbers make the twice-daily refresh a little slower.
						</p>
					</td></tr>

					<tr><th>Chorki auto-picks</th><td>
						<label>
							<input type="checkbox" name="chorki_enabled" value="1" <?php checked( ! empty( $opts['chorki_enabled'] ) ); ?> />
							<strong>Keep Chorki titles up to date automatically</strong>
						</label>
						<p class="description">
							Reads Chorki's own published list every few hours and shows the newest titles in the
							app's rail — so it stays current without anyone typing them in below. Each title keeps
							Chorki's poster and synopsis; where the film is also on TMDB we add cast, trailer and
							rating on top. <strong>Bangladeshi films and short films are usually not on TMDB at all,
							and that is fine</strong> — they still appear, using Chorki's own details.
						</p>
						<p style="margin:10px 0 4px">
							<label>Show
							<input name="chorki_limit" type="number" min="1" max="<?php echo (int) AUN_App_Chorki::MAX_LIMIT; ?>" style="width:70px"
								value="<?php echo (int) ( $opts['chorki_limit'] ?: AUN_App_Chorki::LIMIT ); ?>" />
							of Chorki's newest titles</label>
							<span class="description">(max <?php echo (int) AUN_App_Chorki::MAX_LIMIT; ?> — each one costs a page fetch when the list refreshes)</span>
						</p>
						<p style="margin:10px 0 4px">
							<label style="display:block;margin-bottom:4px">Source list</label>
							<input name="chorki_list_url" type="url" style="width:70%" value="<?php echo esc_attr( (string) ( $opts['chorki_list_url'] ?? '' ) ); ?>"
								placeholder="<?php echo esc_attr( AUN_App_Chorki::LIST_URL ); ?>" />
						</p>
						<p class="description">
							Blank uses <code><?php echo esc_html( AUN_App_Chorki::LIST_URL ); ?></code>. It is a setting so you
							can point at a different Chorki list — or at a new address if they move this one —
							without waiting for a plugin update.
						</p>
						<button type="submit" form="aun-chorki-test-form" class="button button-secondary" style="margin-top:6px">Test Chorki now</button>
						<span class="description" style="display:block;margin-top:6px">
							Save first, then use this to see exactly which titles came back and which ones TMDB
							recognised. If Chorki is ever unreachable the app keeps showing the last good list
							rather than an empty rail.
						</span>
					</td></tr>

					<tr><th>Local picks (Chorki/Bioscope…)</th><td>
						<?php
						// Parse the stored lines back into rows for the form.
						$saved_rows = array();
						foreach ( preg_split( '/\r\n|\r|\n/', (string) $opts['watch_local_picks'] ) as $line ) {
							$line = trim( $line );
							if ( '' === $line ) {
								continue;
							}
							$p            = array_map( 'trim', explode( '|', $line ) );
							$saved_rows[] = array(
								'title'    => $p[0] ?? '',
								'platform' => $p[1] ?? '',
								'url'      => $p[2] ?? '',
								'poster'   => $p[3] ?? '',
								'end'      => $p[4] ?? '',
								'tmdb'     => $p[5] ?? '',
							);
						}
						$saved_rows[] = array( 'title' => '', 'platform' => '', 'url' => '', 'poster' => '', 'end' => '', 'tmdb' => '' ); // one blank row
						?>
						<style>
						/* ⚠️ This was a 7-column <table> and it could not work.
						 * `<input type="date">` and the Search button have hard
						 * minimum widths that a table cannot shrink, so under
						 * auto layout they took the space and the free-text
						 * columns collapsed to about one visible character.
						 * Percentage widths on <th> are only hints and lose to
						 * unshrinkable content every time.
						 *
						 * A card per pick with a wrapping grid cannot collapse:
						 * each field declares a minimum it will never go below,
						 * and the grid moves it to the next line instead. */
						.aun-watch-card{border:1px solid #dcdcde;border-radius:8px;padding:14px 14px 6px;margin-bottom:12px;background:#fff;position:relative}
						.aun-watch-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px 16px}
						.aun-watch-f{display:flex;flex-direction:column;min-width:0}
						.aun-watch-f label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;color:#646970;margin-bottom:4px}
						.aun-watch-f input{width:100%;box-sizing:border-box}
						.aun-watch-f .opt{font-weight:400;text-transform:none;letter-spacing:0;color:#8c8f94}
						.aun-watch-wide{grid-column:1/-1}
						.aun-watch-del{position:absolute;top:8px;right:10px;color:#b32d2e;text-decoration:none;font-size:20px;line-height:1;cursor:pointer}
						.aun-watch-tmdb-row{display:flex;gap:8px;align-items:center}
						.aun-watch-tmdb-row input{flex:1;min-width:0}
						</style>
						<div id="aun-watch-body" style="max-width:940px">
							<?php foreach ( $saved_rows as $i => $r ) : ?>
							<div class="aun-watch-card aun-watch-row">
								<button type="button" class="button-link aun-watch-del" title="Remove this pick">&times;</button>
								<div class="aun-watch-grid">
									<div class="aun-watch-f">
										<label>Title</label>
										<input type="text" name="watch_pick[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( $r['title'] ); ?>" placeholder="Hawa" />
									</div>
									<div class="aun-watch-f">
										<label>Platform</label>
										<input type="text" name="watch_pick[<?php echo (int) $i; ?>][platform]" value="<?php echo esc_attr( $r['platform'] ); ?>" placeholder="Chorki" />
									</div>
									<div class="aun-watch-f">
										<label>Show until <span class="opt">(optional)</span></label>
										<input type="date" name="watch_pick[<?php echo (int) $i; ?>][end]" value="<?php echo esc_attr( $r['end'] ); ?>" />
									</div>
									<div class="aun-watch-f aun-watch-wide">
										<label>Link the customer opens</label>
										<input type="url" name="watch_pick[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $r['url'] ); ?>" placeholder="https://www.chorki.com/..." />
									</div>
									<div class="aun-watch-f">
										<label>TMDB title <span class="opt">(recommended)</span></label>
										<div class="aun-watch-tmdb-row">
											<input type="text" class="aun-watch-tmdb" name="watch_pick[<?php echo (int) $i; ?>][tmdb]" value="<?php echo esc_attr( $r['tmdb'] ); ?>" placeholder="movie:1044789" />
											<button type="button" class="button aun-watch-find">Search</button>
										</div>
									</div>
									<div class="aun-watch-f">
										<label>Poster URL <span class="opt">(optional)</span></label>
										<input type="url" name="watch_pick[<?php echo (int) $i; ?>][poster]" value="<?php echo esc_attr( $r['poster'] ); ?>" placeholder="leave blank to use TMDB's" />
									</div>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
						<p><button type="button" class="button" id="aun-watch-add">+ Add a pick</button></p>
						<p class="description">
							Fill in a pick and press <strong>Save Settings</strong>. Local platforms (Chorki,
							Bioscope, Hoichoi…) have no public APIs, so these are curated by you and show
							FIRST in the app. <strong>Title</strong> and <strong>URL</strong> are required;
							poster and end date are optional. A pick past its <strong>Show until</strong>
							date disappears automatically. Leave every row blank (and no TMDB key) = the
							whole card is hidden in the app.
						</p>
						<p class="description" style="max-width:900px;background:#f0f6fc;border-left:3px solid #0188fe;padding:10px 14px;border-radius:6px">
							<strong>TMDB title — do this for every pick you can.</strong>
							Press <strong>Search</strong> beside it, type the film's name, click the right one
							&mdash; the id is filled in for you, so it can never be mistyped.
							Bangladeshi films <em>are</em> on TMDB (Hawa, Poran, Surongo, Priyotoma…) —
							they just never reach the global trending list, which is why you curate them
							here. Linking one gives it the <strong>same in-app screen</strong> as a global
							pick: synopsis, cast with photos, trailer, runtime and rating — instead of
							throwing the customer out to a browser.
							<br />
							Your <strong>Title</strong>, <strong>Platform</strong> and <strong>URL</strong>
							always win over TMDB's, so a Bangla title stays Bangla and the button still
							says &ldquo;Watch on Chorki&rdquo;. Your poster wins too when you set one.
							<br />
							Nothing on TMDB? Leave it blank — the pick works exactly as it does today.
						</p>
						<div id="aun-tmdb-modal" style="display:none;position:fixed;inset:0;z-index:100050;background:rgba(0,0,0,.55)">
							<div style="background:#fff;max-width:620px;margin:6vh auto;border-radius:10px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.3)">
								<div style="padding:14px 18px;border-bottom:1px solid #dcdcde;display:flex;gap:10px;align-items:center">
									<strong style="flex:1">Find the title on TMDB</strong>
									<button type="button" class="button-link" id="aun-tmdb-close" style="font-size:20px;text-decoration:none;color:#646970">&times;</button>
								</div>
								<div style="padding:14px 18px">
									<input type="text" id="aun-tmdb-q" class="regular-text" style="width:100%" placeholder="e.g. Hawa, Surongo, Priyotoma" />
									<p class="description" style="margin:6px 0 0">Try the English spelling too &mdash; many Bangladeshi films are filed under it.</p>
									<div id="aun-tmdb-results" style="margin-top:12px;max-height:46vh;overflow:auto"></div>
								</div>
							</div>
						</div>
						<script>
						( function () {
							var body = document.getElementById( 'aun-watch-body' );
							var addBtn = document.getElementById( 'aun-watch-add' );
							if ( ! body || ! addBtn ) { return; }

							/* ── TMDB picker ──────────────────────────────────
							 * Nobody should have to leave WordPress, find the
							 * film on themoviedb.org and copy a number out of
							 * its URL. The only hard part of that errand is not
							 * mistyping the id, so the id is never typed here:
							 * clicking a result writes it.
							 */
							var modal   = document.getElementById( 'aun-tmdb-modal' );
							var qInput  = document.getElementById( 'aun-tmdb-q' );
							var results = document.getElementById( 'aun-tmdb-results' );
							var target  = null;   // the row being filled
							var timer   = null;

							function closeModal() { modal.style.display = 'none'; target = null; }
							document.getElementById( 'aun-tmdb-close' ).addEventListener( 'click', closeModal );
							modal.addEventListener( 'click', function ( e ) { if ( e.target === modal ) { closeModal(); } } );
							document.addEventListener( 'keydown', function ( e ) {
								if ( 'Escape' === e.key && 'none' !== modal.style.display ) { closeModal(); }
							} );

							function search() {
								var q = qInput.value.trim();
								if ( q.length < 2 ) { results.innerHTML = ''; return; }
								results.innerHTML = '<p style="color:#646970">Searching…</p>';
								var fd = new FormData();
								fd.append( 'action', 'aun_app_tmdb_search' );
								fd.append( 'nonce', '<?php echo esc_js( wp_create_nonce( 'aun_app_tmdb_search' ) ); ?>' );
								fd.append( 'q', q );
								fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } )
									.then( function ( r ) { return r.json(); } )
									.then( function ( r ) {
										var rows = ( r && r.data ) || [];
										if ( ! rows.length ) {
											results.innerHTML = '<p style="color:#646970">Nothing found. Check the spelling, or leave the field blank &mdash; the pick still works without it.</p>';
											return;
										}
										results.innerHTML = '';
										rows.forEach( function ( m ) {
											var el = document.createElement( 'div' );
											el.style.cssText = 'display:flex;gap:10px;padding:8px;border-bottom:1px solid #f0f0f1;cursor:pointer;align-items:flex-start';
											el.innerHTML =
												( m.poster ? '<img src="' + m.poster + '" style="width:46px;border-radius:4px;flex-shrink:0" />' : '<div style="width:46px;height:69px;background:#f0f0f1;border-radius:4px;flex-shrink:0"></div>' ) +
												'<div style="min-width:0"><strong>' + m.title + '</strong>' +
												( m.year ? ' <span style="color:#646970">(' + m.year + ')</span>' : '' ) +
												' <span style="color:#787c82;font-size:11px;text-transform:uppercase">' + ( 'tv' === m.type ? 'series' : 'movie' ) + '</span>' +
												( m.original && m.original !== m.title ? '<div style="color:#646970;font-size:12px">' + m.original + '</div>' : '' ) +
												'<div style="color:#646970;font-size:12px;margin-top:2px">' + ( m.overview || '' ).slice( 0, 140 ) + '</div></div>';
											el.addEventListener( 'click', function () {
												if ( ! target ) { return; }
												target.querySelector( '.aun-watch-tmdb' ).value = m.type + ':' + m.id;
												// Only fill what is still empty — an admin's own
												// Bangla title and local poster must never be
												// overwritten by TMDB's English ones.
												var t = target.querySelector( 'input[name$="[title]"]' );
												if ( t && ! t.value.trim() ) { t.value = m.title; }
												var p = target.querySelector( 'input[name$="[poster]"]' );
												if ( p && ! p.value.trim() && m.poster ) { p.value = m.poster; }
												closeModal();
											} );
											results.appendChild( el );
										} );
									} )
									.catch( function () {
										results.innerHTML = '<p style="color:#b32d2e">Search failed. Check the TMDB API key above.</p>';
									} );
							}
							qInput.addEventListener( 'input', function () {
								clearTimeout( timer );
								timer = setTimeout( search, 350 );
							} );

							body.addEventListener( 'click', function ( e ) {
								if ( ! e.target.classList.contains( 'aun-watch-find' ) ) { return; }
								target = e.target.closest( '.aun-watch-row' );
								var t = target.querySelector( 'input[name$="[title]"]' );
								qInput.value = t ? t.value.trim() : '';
								results.innerHTML = '';
								modal.style.display = 'block';
								qInput.focus();
								if ( qInput.value.length > 1 ) { search(); }
							} );
							var seq = body.querySelectorAll( '.aun-watch-row' ).length;
							addBtn.addEventListener( 'click', function () {
								var tpl = body.querySelector( '.aun-watch-row' );
								var row = tpl.cloneNode( true );
								row.querySelectorAll( 'input' ).forEach( function ( inp ) {
									inp.value = '';
									inp.name = inp.name.replace( /watch_pick\[\d+\]/, 'watch_pick[' + seq + ']' );
								} );
								body.appendChild( row );
								seq++;
							} );
							body.addEventListener( 'click', function ( e ) {
								if ( ! e.target.classList.contains( 'aun-watch-del' ) ) { return; }
								var rows = body.querySelectorAll( '.aun-watch-row' );
								var row = e.target.closest( '.aun-watch-row' );
								if ( rows.length > 1 ) {
									row.remove();
								} else {
									row.querySelectorAll( 'input' ).forEach( function ( inp ) { inp.value = ''; } );
								}
							} );
						} )();
						</script>
					</td></tr>
				</table>
			</div>

			</div><!-- /panel-watch -->

			<!-- ── Referrals ─────────────────────────────────────────────── -->
			<div class="aun-panel" id="panel-referral">
			<div class="aun-card">
				<h2><span class="dashicons dashicons-groups"></span> Referral programme</h2>
					<p class="aun-hint">
						Existing customers invite friends from the app. The <strong>friend</strong> gets a discount on their
						first order; the <strong>referrer</strong> is paid only once that order is <strong>completed</strong>
						— and the reward is taken back automatically if the order is later refunded or cancelled.
						Paying any earlier is what makes referral schemes farmable.
					</p>
					<table class="form-table">
					<tr><th>Enable</th><td>
						<label><input type="checkbox" name="referral_enabled" value="1"
							<?php checked( ! empty( $opts['referral_enabled'] ) ); ?> /> Run the referral programme</label>
						<p class="description">Off by default. Nothing can be claimed until this is on AND the friend's discount is above zero.</p>
					</td></tr>
					<tr><th>Friend gets</th><td>
						<input name="referral_friend_amount" type="number" step="1" min="0" style="width:110px"
							value="<?php echo esc_attr( (string) ( $opts['referral_friend_amount'] ?? 0 ) ); ?>" />
						<select name="referral_friend_type">
							<option value="percent" <?php selected( ( $opts['referral_friend_type'] ?? 'percent' ), 'percent' ); ?>>% off</option>
							<option value="fixed" <?php selected( ( $opts['referral_friend_type'] ?? 'percent' ), 'fixed' ); ?>>৳ off the cart</option>
						</select>
						<p class="description">
							Their first order only. The coupon is generated per customer, usable once, and locked to
							the mobile number they verified in the app — so it cannot be forwarded or reused.
							Percentages are capped at 50%.
						</p>
					</td></tr>
					<tr><th>Referrer gets</th><td>
						<input name="referral_referrer_amount" type="number" step="1" min="0" style="width:110px"
							value="<?php echo esc_attr( (string) ( $opts['referral_referrer_amount'] ?? 0 ) ); ?>" />
						<select name="referral_referrer_type">
							<option value="fixed" <?php selected( ( $opts['referral_referrer_type'] ?? 'fixed' ), 'fixed' ); ?>>৳ fixed</option>
							<option value="percent" <?php selected( ( $opts['referral_referrer_type'] ?? 'fixed' ), 'percent' ); ?>>% of their friend's order</option>
						</select>
						<p class="description">
							Paid as a coupon on their own account once the friend's order completes. A percentage is
							worked out from that order and issued as a FIXED taka amount — a percentage would be
							meaningless against the referrer's own, unrelated next cart. Set to 0 for a one-sided
							programme (friend only); they still get told their friend ordered.
						</p>
					</td></tr>
					<tr><th>Who can invite</th><td>
						<label><input type="checkbox" name="referral_require_customer" value="1"
							<?php checked( ! isset( $opts['referral_require_customer'] ) || ! empty( $opts['referral_require_customer'] ) ); ?> />
							Only customers who own an AUN projector</label>
						<p class="description">
							Strongly recommended. A referral from someone who has never bought anything is not a
							recommendation — and without this, anyone can register and mint discount codes for the world.
						</p>
					</td></tr>
					<tr><th>Minimum order</th><td>
						৳ <input name="referral_min_order" type="number" step="1" min="0" style="width:110px"
							value="<?php echo esc_attr( (string) ( $opts['referral_min_order'] ?? 0 ) ); ?>" />
						<p class="description">
							Checked against what was actually PAID, so a heavily discounted order cannot earn a reward
							worth more than its margin. Stops a ৳300 cable purchase triggering a payout.
						</p>
					</td></tr>
					<tr><th>Rewards per referrer</th><td>
						<input name="referral_monthly_cap" type="number" min="0" max="100" style="width:90px"
							value="<?php echo (int) ( $opts['referral_monthly_cap'] ?? 5 ); ?>" /> per 30 days
						<p class="description">0 = unlimited. A cap blunts industrial farming even if every other check is somehow passed.</p>
					</td></tr>
					<tr><th>Claim window</th><td>
						<input name="referral_claim_days" type="number" min="0" max="365" style="width:90px"
							value="<?php echo (int) ( $opts['referral_claim_days'] ?? 30 ); ?>" /> days after signup
						<p class="description">
							0 = no limit. Without a window somebody can shop for months and then apply a code retroactively.
						</p>
					</td></tr>
					<tr><th>Pay the reward when</th><td>
						<?php
						$cur_status = AUN_App_Referrals::clean_status( $opts['referral_reward_status'] ?? 'completed' );
						$statuses   = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array( 'wc-completed' => 'Completed' );
						?>
						<select name="referral_reward_status" style="min-width:230px">
							<?php foreach ( $statuses as $slug => $label ) :
								$bare = AUN_App_Referrals::clean_status( $slug );
								// Statuses that mean the sale did NOT stick can never be the
								// trigger — offering them would be offering a foot-gun.
								if ( in_array( $bare, array( 'refunded', 'cancelled', 'failed', 'pending' ), true ) ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( $bare ); ?>" <?php selected( $cur_status, $bare ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description" style="max-width:780px">
							The order status at which the referrer is actually paid, and the status at which the
							friend becomes able to invite people themselves.
							<strong>If you use a shipment plugin with a "Delivered" status, choose it.</strong>
							Paying on <em>Processing</em> or <em>Shipped</em> means a refused cash-on-delivery
							parcel pays a reward for goods that came straight back.
						</p>
						<?php
						$pay_now = class_exists( 'AUN_App_Referrals' ) ? AUN_App_Referrals::payout_statuses() : array();
						?>
						<p style="margin:8px 0 0">
							<label>
								<input type="checkbox" name="referral_reward_also_completed" value="1"
									<?php checked( ! empty( $opts['referral_reward_also_completed'] ) ); ?> />
								Also pay when the order reaches <strong>Completed</strong>
							</label>
						</p>
						<p class="description" style="max-width:780px">
							Leave this OFF unless you know you want it. <strong>AST Pro and similar plugins often
							mark an order Completed at the moment it is marked Shipped</strong> — with this ticked,
							the reward goes out at dispatch and the status you chose above stops mattering.
						</p>
						<p style="margin:10px 0 0">
							<strong>Paying right now on:</strong>
							<?php foreach ( $pay_now as $ps ) : ?>
								<span class="aun-badge aun-badge-green"><?php echo esc_html( $ps ); ?></span>
							<?php endforeach; ?>
							<span class="description" style="display:block;margin-top:4px">
								If a status you did not expect is listed here, that is why rewards are going out early.
							</span>
						</p>
					</td></tr>
					<tr><th>Friend's coupon expiry</th><td>
						<input name="referral_expiry_days" type="number" min="1" max="730" style="width:90px"
							value="<?php echo (int) ( $opts['referral_expiry_days'] ?? 90 ); ?>" /> days
						<p class="description">A promotional offer, so a deadline is fair and creates urgency.</p>
					</td></tr>
					<tr><th>Referrer's reward expiry</th><td>
						<input name="referral_reward_expiry_days" type="number" min="0" max="3650" style="width:90px"
							value="<?php echo (int) ( $opts['referral_reward_expiry_days'] ?? 365 ); ?>" /> days
						<p class="description" style="max-width:780px">
							<strong>0 = never expires.</strong> This is different from the friend's coupon: it is
							not an offer, it is money the referrer earned by bringing us a customer. A short
							deadline on earned rewards is what makes people stop trusting a programme —
							the big platforms give a year or no expiry at all.
							<strong>365 days is the recommended setting; 0 if you want it to feel generous.</strong>
						</p>
					</td></tr>
					<tr><th>Built-in protection</th><td>
						<p class="description" style="max-width:760px">
							Always on, regardless of the settings above: no self-referral (checked by phone, not just
							account); one claim per phone number ever, so deleting an account buys nothing;
							<strong>first-time customers only</strong> — anyone with a past order or a registered
							projector is refused; the coupon only works for the number it was issued to; and rewards
							are revoked if the order is refunded, cancelled or fails.
						</p>
					</td></tr>
					</table>
			</div>

			<div class="aun-card">
				<h2><span class="dashicons dashicons-chart-bar"></span> How the programme is doing</h2>
				<?php $this->referral_stats(); ?>
			</div>

			<div class="aun-card">
				<h2><span class="dashicons dashicons-hammer"></span> Testing with your own number</h2>
				<p class="aun-hint">
					The programme is built so a number can claim <strong>once, ever</strong> — which is exactly
					right in production and exactly wrong when you have two SIMs and a flow to test. These two
					tools let you walk the whole journey as many times as you need.
				</p>

				<h3>Test lines</h3>
				<table class="form-table">
					<tr><th>Numbers</th><td>
						<textarea name="referral_test_phones" rows="2" style="width:340px;font-family:monospace"
							placeholder="01712345678&#10;01812345678"><?php echo esc_textarea( (string) ( $opts['referral_test_phones'] ?? '' ) ); ?></textarea>
						<p class="description" style="max-width:760px">
							One per line, any format. These numbers may redeem a code <strong>even though they are
							already customers</strong> — your own SIMs have order history and registered
							projectors, so without this you can only ever test the inviting half.
							<br><br>
							It bypasses that <strong>one</strong> rule. Self-referral is still blocked, the reward
							is still only paid when the order completes, the refund clawback still applies, and the
							coupon is still locked to the number — because those are the parts worth testing.
							<strong>Clear this box before you finish for the day.</strong>
							<?php
							$tp = AUN_App_Referrals::test_phones();
							if ( ! empty( $tp ) ) {
								echo '<br><br><span class="aun-status-warn">Active test lines: '
									. esc_html( implode( ', ', array_map( array( 'AUN_App_Referrals', 'display_phone' ), $tp ) ) )
									. '</span>';
							}
							?>
						</p>
					</td></tr>
				</table>

				<h3>Reset a number's referral history</h3>
				<p class="description" style="max-width:760px;margin-bottom:12px">
					Removes the claim, the invitations sent, the coupons issued and the invite code — so this
					number can go through the whole programme again from scratch.
					<strong>Orders, devices, warranties and the account itself are never touched.</strong>
					You will see exactly what is about to go before anything is deleted.
				</p>
				<p style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0">
					<input type="text" name="referral_reset_phone" form="aun-referral-reset-form"
						value="<?php echo esc_attr( $reset['phone'] ?? '' ); ?>"
						placeholder="01700000000" style="width:200px" />
					<button type="submit" form="aun-referral-reset-form" class="button button-secondary">Show me what would be deleted</button>
				</p>

				<?php if ( is_array( $reset ) && ! empty( $reset['ok'] ) && empty( $reset['confirmed'] ) ) : ?>
					<?php $nothing = 0 === $reset['claims'] + $reset['invites'] && empty( $reset['coupons'] ); ?>
					<div style="margin-top:16px;padding:14px 16px;border:1px solid #e2e4e7;border-radius:10px;background:#f6f7f7">
						<?php if ( $nothing ) : ?>
							<p style="margin:0"><strong><?php echo esc_html( $reset['phone'] ); ?></strong> has no referral
								history — there is nothing to reset. It can already go through the programme.</p>
						<?php else : ?>
							<p style="margin:0 0 8px"><strong>About to delete for <?php echo esc_html( $reset['phone'] ); ?>:</strong></p>
							<ul style="margin:0 0 12px 18px;list-style:disc">
								<li><?php echo (int) $reset['claims']; ?> claim(s) where they redeemed someone's code</li>
								<li><?php echo (int) $reset['invites']; ?> invitation(s) where they were the referrer</li>
								<li><?php echo count( $reset['coupons'] ); ?> coupon(s)<?php
									echo empty( $reset['coupons'] ) ? '' : ': <code>' . esc_html( implode( '</code>, <code>', $reset['coupons'] ) ) . '</code>'; ?></li>
								<li>their invite code (a fresh one is minted next time)</li>
							</ul>
							<form method="post" style="margin:0">
								<?php wp_nonce_field( 'aun_app_referral_reset', 'aun_referral_reset_nonce' ); ?>
								<input type="hidden" name="referral_reset_phone" value="<?php echo esc_attr( $reset['phone'] ); ?>" />
								<input type="hidden" name="referral_reset_confirm" value="1" />
								<button type="submit" class="button button-primary">Yes, delete this referral history</button>
								<span class="description" style="margin-left:8px">This cannot be undone.</span>
							</form>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="aun-card">
				<h2><span class="dashicons dashicons-search"></span> Why can't a customer see the referral card?</h2>
				<p class="aun-hint">
					Enter the customer's mobile number to see exactly what their account returns and what the
					app does with it. This runs the phone's own decision, so it gives the same verdict the app
					gives — no guessing.
				</p>
				<p style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 4px">
					<input type="text" name="referral_diag_phone" form="aun-referral-diag-form"
						value="<?php echo esc_attr( $diag['phone'] ?? '' ); ?>"
						placeholder="01700000000" style="width:200px" />
					<button type="submit" form="aun-referral-diag-form" class="button button-secondary">Check this customer</button>
				</p>
				<?php if ( is_array( $diag ) ) : ?>
					<?php if ( '' !== $diag['error'] ) : ?>
						<p class="aun-status-warn" style="margin-top:14px"><?php echo esc_html( $diag['error'] ); ?></p>
					<?php else : ?>
						<table class="widefat" style="max-width:640px;margin-top:14px">
							<tbody>
							<?php foreach ( $diag['rows'] as $k => $v ) : ?>
								<tr>
									<td style="width:230px;color:#50575e"><?php echo esc_html( $k ); ?></td>
									<td><code><?php echo esc_html( $v ); ?></code></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<p style="margin-top:14px">
							<strong>The app shows:</strong>
							<?php
							$labels = array(
								'invite' => array( 'aun-badge-green', 'the INVITE card' ),
								'claim'  => array( 'aun-badge-blue', 'the REDEEM card' ),
								'coupon' => array( 'aun-badge-purple', 'their COUPON' ),
								'hidden' => array( 'aun-badge-grey', 'NOTHING' ),
							);
							$lb = $labels[ $diag['verdict'] ] ?? $labels['hidden'];
							?>
							<span class="aun-badge <?php echo esc_attr( $lb[0] ); ?>"><?php echo esc_html( $lb[1] ); ?></span>
						</p>
						<p class="description" style="max-width:760px"><?php echo esc_html( $diag['why'] ); ?></p>
						<?php if ( 'hidden' !== $diag['verdict'] ) : ?>
							<p class="description" style="max-width:760px">
								If the customer still sees nothing, their <strong>app is older than 1.60</strong> —
								earlier builds hid the card from anyone without a projector. Check
								<strong>Settings → App version</strong> on their phone.
							</p>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="aun-card">
				<h2><span class="dashicons dashicons-unlock"></span> Release a coupon's phone lock</h2>
				<p class="aun-hint">
					A referral coupon only works for the mobile number the customer verified in the app. That is
					right nearly always — and occasionally wrong: someone checks out under a spouse's or a
					relative's number and is refused correctly by the rule but unfairly in fact. Enter the
					coupon code the customer is holding and this releases that one coupon.
					<br><br>
					Everything else about it stays in force: still single-use, still expiring, still subject to
					the minimum order. There is deliberately no way to re-point a coupon at a <em>different</em>
					number — the only number we can vouch for is the one that passed OTP.
				</p>
				<p style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0">
					<input type="text" name="referral_unlock_code" form="aun-referral-unlock-form"
						placeholder="WELCOME-K3P7QA" style="width:230px;font-family:monospace" />
					<button type="submit" form="aun-referral-unlock-form" class="button button-secondary">Release the lock</button>
				</p>
			</div>
			</div><!-- /panel-referral -->

			<!-- ── App release ───────────────────────────────────────────── -->
			<div class="aun-panel" id="panel-release">
			<div class="aun-card">
				<h2><span class="dashicons dashicons-update"></span> App version (APK distribution)</h2>
				<p class="aun-hint">The version gates the app checks on launch. Raise these only after the new
					APK is actually uploaded and reachable — a phone told to update to a file that is not there
					has nowhere to go.</p>
				<table class="form-table">
					<tr><th>Latest version code</th><td><input name="latest_version_code" type="number" min="1" value="<?php echo (int) $opts['latest_version_code']; ?>" style="width:110px" /> <span class="description">Build number of the newest APK.</span></td></tr>
					<tr><th>Latest version name</th><td><input name="latest_version_name" value="<?php echo esc_attr( $opts['latest_version_name'] ); ?>" style="width:110px" /></td></tr>
					<tr><th>Minimum version code</th><td><input name="min_version_code" type="number" min="1" value="<?php echo (int) $opts['min_version_code']; ?>" style="width:110px" /> <span class="description">Older builds are forced to update before use.</span></td></tr>
					<tr><th>APK download URL</th><td><input name="apk_url" id="aun-apk-url" style="width:78%" value="<?php echo esc_attr( $opts['apk_url'] ); ?>" placeholder="https://.../aun-app.apk" /> <button type="button" class="button" id="aun-apk-btn">Choose file</button></td></tr>
					<tr><th>What's new</th><td>
						<textarea name="release_notes" rows="3" style="width:100%" placeholder="Faster checkout. Spare-part payments now work inside the app."><?php echo esc_textarea( (string) ( $opts['release_notes'] ?? '' ) ); ?></textarea>
						<p class="description" style="max-width:780px">
							Shown in the app's update sheet, once per version. A version number tells the
							customer nothing — one line about what actually improved is what makes someone
							bother to update. Leave blank to show just the version.
						</p>
					</td></tr>
					<tr><th>Login token lifetime</th><td><input name="token_days" type="number" min="1" max="730" value="<?php echo (int) $opts['token_days']; ?>" style="width:90px" /> days</td></tr>
				</table>
			</div>

			<div class="aun-card">
				<h2><span class="dashicons dashicons-info"></span> Testing note</h2>
				<p class="description" style="max-width:760px">
					On a <strong>test site only</strong>, add <code>define( 'AUN_APP_DEV_OTP', true );</code> to
					<code>wp-config.php</code> to receive login OTPs in the API response instead of by SMS.
					Never enable this on the live site.
				</p>
			</div>
			</div><!-- /panel-release -->

			<div class="aun-save">
				<input type="submit" class="button button-primary" value="Save settings" />
				<span class="description">Saves every section, not just the one you are looking at.</span>
			</div>
		</form>
			</div><!-- /column -->
		</div><!-- /aun-settings -->

		<?php
		// Standalone forms for the action buttons. They live OUTSIDE the settings
		// form because HTML forbids nesting one form in another; the buttons and
		// inputs above reach them by `form="..."`, which is what lets each tool
		// sit with the feature it belongs to instead of in a heap at the bottom.
		?>
		<form id="aun-tmdb-test-form" method="post"><?php wp_nonce_field( 'aun_app_tmdb_test', 'aun_tmdb_test_nonce' ); ?></form>
		<form id="aun-watch-rebuild-form" method="post"><?php wp_nonce_field( 'aun_app_watch_rebuild', 'aun_watch_rebuild_nonce' ); ?></form>
		<form id="aun-chorki-test-form" method="post"><?php wp_nonce_field( 'aun_app_chorki_test', 'aun_chorki_test_nonce' ); ?></form>
		<form id="aun-maint-test-form" method="post"><?php wp_nonce_field( 'aun_app_maint_test', 'aun_maint_test_nonce' ); ?></form>
		<form id="aun-referral-unlock-form" method="post"><?php wp_nonce_field( 'aun_app_referral_unlock', 'aun_referral_unlock_nonce' ); ?></form>
		<form id="aun-referral-diag-form" method="post"><?php wp_nonce_field( 'aun_app_referral_diag', 'aun_referral_diag_nonce' ); ?></form>
		<form id="aun-referral-reset-form" method="post"><?php wp_nonce_field( 'aun_app_referral_reset', 'aun_referral_reset_nonce' ); ?></form>
		<form id="aun-purge-orders-form" method="post"><?php wp_nonce_field( 'aun_app_purge_orders', 'aun_purge_orders_nonce' ); ?></form>
		<form id="aun-pay-error-clear-form" method="post"><?php wp_nonce_field( 'aun_app_pay_error_clear', 'aun_pay_error_clear_nonce' ); ?></form>
		<?php if ( AUN_App_Tickets::configured() ) : ?>
		<form id="aun-tickets-test-form" method="post"><?php wp_nonce_field( 'aun_app_tickets_test', 'aun_tickets_test_nonce' ); ?></form>
		<?php endif; ?>
		</div>

		<script data-no-optimize="1">
		( function () {
			var nav = document.getElementById( 'aun-settings-nav' );
			if ( ! nav ) { return; }
			var links = nav.querySelectorAll( 'a[data-panel]' );
			var KEY = 'aunAppSettingsPanel';

			function show( name, remember ) {
				var found = false;
				links.forEach( function ( a ) {
					var on = a.getAttribute( 'data-panel' ) === name;
					a.classList.toggle( 'active', on );
					var panel = document.getElementById( 'panel-' + a.getAttribute( 'data-panel' ) );
					if ( panel ) { panel.classList.toggle( 'active', on ); }
					if ( on ) { found = true; }
				} );
				if ( ! found ) { return false; }
				if ( remember ) {
					try { window.localStorage.setItem( KEY, name ); } catch ( e ) {}
				}
				return true;
			}

			links.forEach( function ( a ) {
				a.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					show( a.getAttribute( 'data-panel' ), true );
					// Keep the section in the URL so a browser refresh — which is
					// what a settings save is — comes back to the same place.
					if ( window.history && window.history.replaceState ) {
						window.history.replaceState( null, '', '#' + a.getAttribute( 'data-panel' ) );
					}
				} );
			} );

			var start = ( window.location.hash || '' ).replace( '#', '' );
			if ( ! start ) {
				try { start = window.localStorage.getItem( KEY ) || ''; } catch ( e ) {}
			}
			// Falls back to the first section when the stored one no longer
			// exists — a renamed section must never leave a blank page.
			if ( ! start || ! show( start, false ) ) {
				show( links[0].getAttribute( 'data-panel' ), false );
			}
		} )();
		</script>

		<script data-no-optimize="1">
		jQuery(function($){
			$('#aun-apk-btn').on('click', function(e){
				e.preventDefault();
				var frame = wp.media({ title: 'Select APK', multiple: false });
				frame.on('select', function(){
					$('#aun-apk-url').val(frame.state().get('selection').first().toJSON().url);
				});
				frame.open();
			});
			$('#aun-video-btn').on('click', function(e){
				e.preventDefault();
				var frame = wp.media({ title: 'Select login video', multiple: false, library: { type: 'video' } });
				frame.on('select', function(){
					$('#aun-video-url').val(frame.state().get('selection').first().toJSON().url);
				});
				frame.open();
			});
		});
		</script>
		<?php
	}
}
