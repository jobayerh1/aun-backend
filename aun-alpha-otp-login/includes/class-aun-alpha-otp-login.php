<?php
/**
 * Front-end orchestration: enqueue assets, inject the OTP UI into the WooCommerce
 * login form, and handle the AJAX send / verify / resend / account-select actions.
 *
 * @package AUN_Alpha_OTP_Login
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class AUN_Alpha_OTP_Login {

	const NONCE = 'aun_alpha_otp';

	/**
	 * Resolved plugin options.
	 *
	 * @var array
	 */
	private $opts;

	/**
	 * Session/OTP helper.
	 *
	 * @var AUN_Alpha_OTP_Session
	 */
	private $session;

	public function __construct() {
		$this->opts    = aun_alpha_otp_get_options();
		$this->session = new AUN_Alpha_OTP_Session();
	}

	/**
	 * Whether the feature should run at all.
	 *
	 * @return bool
	 */
	private function is_active() {
		return 'yes' === $this->opts['enabled'] && AUN_Alpha_OTP_SMS::is_configured();
	}

	/**
	 * Register hooks. AJAX handlers are always registered; UI only when active.
	 */
	public function init() {
		// AJAX (logged-out visitors use the nopriv variants).
		add_action( 'wp_ajax_nopriv_aun_alpha_otp_send', array( $this, 'ajax_send' ) );
		add_action( 'wp_ajax_aun_alpha_otp_send', array( $this, 'ajax_send' ) );
		add_action( 'wp_ajax_nopriv_aun_alpha_otp_verify', array( $this, 'ajax_verify' ) );
		add_action( 'wp_ajax_aun_alpha_otp_verify', array( $this, 'ajax_verify' ) );
		add_action( 'wp_ajax_nopriv_aun_alpha_otp_resend', array( $this, 'ajax_resend' ) );
		add_action( 'wp_ajax_aun_alpha_otp_resend', array( $this, 'ajax_resend' ) );
		add_action( 'wp_ajax_nopriv_aun_alpha_otp_select', array( $this, 'ajax_select' ) );
		add_action( 'wp_ajax_aun_alpha_otp_select', array( $this, 'ajax_select' ) );

		if ( ! $this->is_active() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'woocommerce_login_form_end', array( $this, 'render_login_ui' ), 20 );
	}

	/* --------------------------------------------------------------------- *
	 * Assets
	 * --------------------------------------------------------------------- */

	/**
	 * Enqueue CSS + JS for logged-out visitors (the login form can appear in the
	 * Flatsome header modal on any page).
	 */
	public function enqueue() {
		if ( is_user_logged_in() ) {
			return;
		}

		wp_enqueue_style(
			'aun-alpha-otp',
			AUN_ALPHA_OTP_URL . 'assets/css/aun-otp-login.css',
			array(),
			AUN_ALPHA_OTP_VERSION
		);

		wp_enqueue_script(
			'aun-alpha-otp',
			AUN_ALPHA_OTP_URL . 'assets/js/aun-otp-login.js',
			array( 'jquery' ),
			AUN_ALPHA_OTP_VERSION,
			true
		);

		wp_localize_script(
			'aun-alpha-otp',
			'aunAlphaOtp',
			array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( self::NONCE ),
				'otpLength'  => (int) $this->opts['otp_length'],
				'otpFirst'   => 'yes' === $this->opts['otp_first'],
				'hideEmail'  => 'yes' === $this->opts['hide_email_login'],
				'i18n'       => array(
					'sending'      => __( 'Sending…', 'aun-alpha-otp-login' ),
					'verifying'    => __( 'Verifying…', 'aun-alpha-otp-login' ),
					'sentTo'       => __( 'We sent a code to', 'aun-alpha-otp-login' ),
					'change'       => __( 'Change', 'aun-alpha-otp-login' ),
					'verifyLogin'  => __( 'Verify & Login', 'aun-alpha-otp-login' ),
					'resend'       => __( 'Resend code', 'aun-alpha-otp-login' ),
					'enterCode'    => __( 'Please enter the full code.', 'aun-alpha-otp-login' ),
					'connError'    => __( 'Connection error. Please try again.', 'aun-alpha-otp-login' ),
					'chooseAcct'   => __( 'Choose an account to continue', 'aun-alpha-otp-login' ),
					'linkedTo'     => __( 'These accounts use', 'aun-alpha-otp-login' ),
					'loggingIn'    => __( 'Logging in…', 'aun-alpha-otp-login' ),
					'orders'       => __( 'orders', 'aun-alpha-otp-login' ),
				),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Render
	 * --------------------------------------------------------------------- */

	/**
	 * Output the OTP login UI inside the WooCommerce login form.
	 * Hidden by default; the JS reveals and arranges it (so no-JS visitors keep the
	 * normal email/password form).
	 */
	public function render_login_ui() {
		$length = max( 4, min( 8, (int) $this->opts['otp_length'] ) );
		?>
		<div class="aun-otp-wrap" style="display:none;">

			<div class="aun-otp-phone" style="display:none;">
				<p class="aun-otp-field form-row form-row-wide">
					<label class="aun-otp-label"><?php esc_html_e( 'Phone', 'aun-alpha-otp-login' ); ?>&nbsp;<span class="required">*</span></label>
					<input type="tel" inputmode="numeric" autocomplete="tel" class="aun-otp-phone-input input-text" placeholder="017xxxxxxxx" />
				</p>
				<div class="aun-otp-notice" style="display:none;"></div>
				<button type="button" class="aun-otp-send button"><?php echo esc_html( $this->opts['btn_otp_label'] ); ?></button>
			</div>

			<div class="aun-otp-code" style="display:none;">
				<div class="aun-otp-sent">
					<span class="aun-otp-sent-txt"></span>
					<a class="aun-otp-change" role="button" tabindex="0"><?php esc_html_e( 'Change', 'aun-alpha-otp-login' ); ?></a>
				</div>
				<div class="aun-otp-notice" style="display:none;"></div>
				<div class="aun-otp-digits">
					<?php for ( $i = 0; $i < $length; $i++ ) : ?>
						<input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off" class="aun-otp-digit" />
					<?php endfor; ?>
				</div>
				<button type="button" class="aun-otp-verify button"><?php esc_html_e( 'Verify &amp; Login', 'aun-alpha-otp-login' ); ?></button>
				<div class="aun-otp-resend-row">
					<a class="aun-otp-resend" role="button" tabindex="0"><?php esc_html_e( 'Resend code', 'aun-alpha-otp-login' ); ?></a>
					<span class="aun-otp-timer"></span>
				</div>
			</div>

			<div class="aun-otp-toggle">
				<button type="button" class="aun-otp-to-otp button"><?php echo esc_html( $this->opts['btn_otp_label'] ); ?></button>
				<?php if ( 'yes' !== $this->opts['hide_email_login'] ) : ?>
					<button type="button" class="aun-otp-to-pwd button"><?php echo esc_html( $this->opts['btn_pwd_label'] ); ?></button>
				<?php endif; ?>
			</div>

		</div>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * AJAX
	 * --------------------------------------------------------------------- */

	/**
	 * Guard shared by every AJAX handler.
	 */
	private function preflight() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page.', 'aun-alpha-otp-login' ) ), 403 );
		}
		if ( ! $this->is_active() ) {
			wp_send_json_error( array( 'message' => __( 'OTP login is currently unavailable.', 'aun-alpha-otp-login' ) ), 503 );
		}
		if ( is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You are already logged in.', 'aun-alpha-otp-login' ) ), 400 );
		}
	}

	/**
	 * Step 1 — phone in, OTP out.
	 */
	public function ajax_send() {
		$this->preflight();

		$canonical = AUN_Alpha_OTP_Session::normalize_phone(
			isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : ''
		);

		if ( ! $canonical ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid Bangladeshi mobile number.', 'aun-alpha-otp-login' ) ), 400 );
		}

		if ( $this->session->is_rate_limited( $canonical, $this->opts['max_per_day'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many OTP requests. Please try again later.', 'aun-alpha-otp-login' ) ), 429 );
		}

		$users = AUN_Alpha_OTP_Session::find_users_by_phone( $canonical );
		if ( empty( $users ) ) {
			wp_send_json_error( array( 'message' => __( 'No account found with this phone number. Please register first.', 'aun-alpha-otp-login' ) ), 404 );
		}

		$user_ids = wp_list_pluck( $users, 'ID' );
		$otp      = AUN_Alpha_OTP_Session::generate_otp( $this->opts['otp_length'] );

		$sms = AUN_Alpha_OTP_SMS::send( $canonical, $this->build_message( $otp ) );
		if ( ! $sms['success'] ) {
			// Surface a friendly message; keep the gateway detail in the log only.
			error_log( 'AUN Alpha OTP send failed: ' . $sms['message'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			wp_send_json_error( array( 'message' => __( 'Could not send the OTP right now. Please try again in a moment.', 'aun-alpha-otp-login' ) ), 502 );
		}

		$this->session->store_otp( $canonical, $otp, $user_ids, $this->opts['otp_expiry'], $this->opts['resend_wait'] );
		$this->session->record_request( $canonical );

		wp_send_json_success(
			array(
				'masked'     => AUN_Alpha_OTP_Session::mask_phone( $canonical ),
				'resendWait' => (int) $this->opts['resend_wait'],
				'message'    => __( 'A one-time code has been sent to your phone.', 'aun-alpha-otp-login' ),
			)
		);
	}

	/**
	 * Step 2 — verify the OTP and log in (or offer an account picker).
	 */
	public function ajax_verify() {
		$this->preflight();

		$otp = isset( $_POST['otp'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['otp'] ) ) : '';
		if ( '' === $otp ) {
			wp_send_json_error( array( 'message' => __( 'Please enter the code.', 'aun-alpha-otp-login' ) ), 400 );
		}

		$result = $this->session->verify_otp( $otp, $this->opts['max_attempts'] );
		if ( ! $result['ok'] ) {
			$messages = array(
				'expired'  => __( 'This code has expired. Please request a new one.', 'aun-alpha-otp-login' ),
				'locked'   => __( 'Too many incorrect attempts. Please request a new code.', 'aun-alpha-otp-login' ),
				'mismatch' => __( 'Incorrect code. Please try again.', 'aun-alpha-otp-login' ),
				'none'     => __( 'Your session expired. Please request a new code.', 'aun-alpha-otp-login' ),
			);
			$reset = in_array( $result['reason'], array( 'expired', 'locked', 'none' ), true );
			wp_send_json_error(
				array(
					'message' => isset( $messages[ $result['reason'] ] ) ? $messages[ $result['reason'] ] : $messages['mismatch'],
					'reset'   => $reset, // tells the UI to go back to the phone step
				),
				400
			);
		}

		$data     = $this->session->get_data();
		$user_ids = isset( $data['user_ids'] ) ? array_map( 'intval', $data['user_ids'] ) : array();

		if ( count( $user_ids ) === 1 ) {
			$this->login_user( $user_ids[0] );
			$this->session->clear_data();
			wp_send_json_success( array( 'redirect' => $this->redirect_url() ) );
		}

		if ( count( $user_ids ) > 1 ) {
			// A phone is not a unique key in WooCommerce, so in rare cases more than one
			// account shares it. By default we just log into the primary account (the one
			// with the most orders, newest as a tiebreak) — the behaviour shoppers expect.
			// Stores that genuinely want the customer to choose can switch on the picker.
			if ( 'picker' === $this->opts['multi_account'] ) {
				wp_send_json_success(
					array(
						'multipleAccounts' => true,
						'accounts'         => $this->describe_accounts( $user_ids ),
					)
				);
			}

			$best = $this->resolve_primary_user( $user_ids );
			if ( $best ) {
				$this->login_user( $best );
				$this->session->clear_data();
				wp_send_json_success( array( 'redirect' => $this->redirect_url() ) );
			}
		}

		wp_send_json_error( array( 'message' => __( 'Your session expired. Please request a new code.', 'aun-alpha-otp-login' ), 'reset' => true ), 400 );
	}

	/**
	 * Resend the OTP, respecting the cooldown and daily cap.
	 */
	public function ajax_resend() {
		$this->preflight();

		$data = $this->session->get_data();
		if ( empty( $data['phone'] ) || empty( $data['user_ids'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please start again.', 'aun-alpha-otp-login' ), 'reset' => true ), 400 );
		}

		$wait = $this->session->resend_cooldown_remaining();
		if ( $wait > 0 ) {
			/* translators: %d: seconds to wait. */
			wp_send_json_error( array( 'message' => sprintf( __( 'Please wait %d seconds before requesting another code.', 'aun-alpha-otp-login' ), $wait ) ), 429 );
		}

		$canonical = $data['phone'];
		if ( $this->session->is_rate_limited( $canonical, $this->opts['max_per_day'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many OTP requests. Please try again later.', 'aun-alpha-otp-login' ) ), 429 );
		}

		$otp = AUN_Alpha_OTP_Session::generate_otp( $this->opts['otp_length'] );
		$sms = AUN_Alpha_OTP_SMS::send( $canonical, $this->build_message( $otp ) );
		if ( ! $sms['success'] ) {
			error_log( 'AUN Alpha OTP resend failed: ' . $sms['message'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			wp_send_json_error( array( 'message' => __( 'Could not send the OTP right now. Please try again in a moment.', 'aun-alpha-otp-login' ) ), 502 );
		}

		$this->session->store_otp( $canonical, $otp, $data['user_ids'], $this->opts['otp_expiry'], $this->opts['resend_wait'] );
		$this->session->record_request( $canonical );

		wp_send_json_success(
			array(
				'resendWait' => (int) $this->opts['resend_wait'],
				'message'    => __( 'A new code has been sent.', 'aun-alpha-otp-login' ),
			)
		);
	}

	/**
	 * Account picker selection (shared phone) — log into the chosen account.
	 */
	public function ajax_select() {
		$this->preflight();

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$data    = $this->session->get_data();

		if ( empty( $data['otp_verified'] ) || empty( $data['user_ids'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please start again.', 'aun-alpha-otp-login' ), 'reset' => true ), 400 );
		}

		if ( ! $user_id || ! in_array( $user_id, array_map( 'intval', $data['user_ids'] ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid account selection.', 'aun-alpha-otp-login' ) ), 400 );
		}

		$this->login_user( $user_id );
		$this->session->clear_data();
		wp_send_json_success( array( 'redirect' => $this->redirect_url() ) );
	}

	/* --------------------------------------------------------------------- *
	 * Helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Build the SMS body from the template.
	 *
	 * @param string $otp Plain OTP.
	 * @return string
	 */
	private function build_message( $otp ) {
		$minutes  = max( 1, (int) round( ( (int) $this->opts['otp_expiry'] ) / 60 ) );
		$template = (string) $this->opts['sms_template'];
		if ( '' === trim( $template ) ) {
			$template = 'Your OTP for [site] login is [otp]. Valid for [min] minutes.';
		}

		return str_replace(
			array( '[otp]', '[site]', '[min]' ),
			array( $otp, wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $minutes ),
			$template
		);
	}

	/**
	 * Authenticate a user id (sets the WP auth cookie).
	 *
	 * @param int $user_id User ID.
	 */
	private function login_user( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Account could not be loaded.', 'aun-alpha-otp-login' ) ), 400 );
		}
		wp_set_current_user( $user_id, $user->user_login );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'wp_login', $user->user_login, $user );
	}

	/**
	 * Resolve the post-login redirect URL (validated to this host).
	 *
	 * @return string
	 */
	private function redirect_url() {
		$target = trim( (string) $this->opts['redirect'] );
		if ( '' === $target && function_exists( 'wc_get_page_permalink' ) ) {
			$target = wc_get_page_permalink( 'myaccount' );
		}
		if ( '' === $target ) {
			$target = home_url( '/' );
		}
		return wp_validate_redirect( $target, home_url( '/' ) );
	}

	/**
	 * Pick the primary account when several share a phone: most orders wins, with the
	 * newest registration as a tiebreak.
	 *
	 * @param int[] $user_ids User IDs.
	 * @return int Best user ID (0 if none resolvable).
	 */
	private function resolve_primary_user( $user_ids ) {
		$best        = 0;
		$best_orders = -1;
		$best_reg    = 0;

		foreach ( $user_ids as $uid ) {
			$user = get_user_by( 'id', $uid );
			if ( ! $user ) {
				continue;
			}
			$orders = function_exists( 'wc_get_customer_order_count' ) ? (int) wc_get_customer_order_count( $uid ) : 0;
			$reg    = strtotime( $user->user_registered );

			if ( $orders > $best_orders || ( $orders === $best_orders && $reg > $best_reg ) ) {
				$best        = (int) $uid;
				$best_orders = $orders;
				$best_reg    = $reg;
			}
		}

		return $best;
	}

	/**
	 * Build the account-picker payload for a set of users sharing a phone.
	 *
	 * @param int[] $user_ids User IDs.
	 * @return array[]
	 */
	private function describe_accounts( $user_ids ) {
		$accounts = array();
		foreach ( $user_ids as $uid ) {
			$user = get_user_by( 'id', $uid );
			if ( ! $user ) {
				continue;
			}
			$orders = function_exists( 'wc_get_customer_order_count' ) ? (int) wc_get_customer_order_count( $uid ) : 0;
			$accounts[] = array(
				'id'      => (int) $uid,
				'email'   => $this->mask_email( $user->user_email ),
				'name'    => $user->display_name ? $user->display_name : $user->user_login,
				'orders'  => $orders,
			);
		}
		return $accounts;
	}

	/**
	 * Mask an email for the account picker (j***@gmail.com).
	 *
	 * @param string $email Email.
	 * @return string
	 */
	private function mask_email( $email ) {
		if ( ! is_string( $email ) || false === strpos( $email, '@' ) ) {
			return '';
		}
		list( $name, $domain ) = explode( '@', $email, 2 );
		$visible = substr( $name, 0, 1 );
		return $visible . str_repeat( '*', max( 1, strlen( $name ) - 1 ) ) . '@' . $domain;
	}
}
