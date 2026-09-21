<?php
/**
 * Front-end orchestration: enqueue assets, inject the phone sign-in UI into the
 * WooCommerce login form, and handle the AJAX send / verify / resend /
 * account-select / register actions.
 *
 * 1.1: a number without an account can sign up in the same flow. Once its code
 * is verified the customer gives a name (email optional) and the account is
 * created and signed in: the one-box pattern Daraz, Pathao and foodpanda use.
 *
 * @package Breo_Alpha_OTP_Login
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Breo_Alpha_OTP_Login {

	const NONCE = 'breo_alpha_otp';

	/** Seconds a verified new number has to finish the name step. */
	const PROFILE_WINDOW = 600;

	/**
	 * Resolved plugin options.
	 *
	 * @var array
	 */
	private $opts;

	/**
	 * Session/OTP helper.
	 *
	 * @var Breo_Alpha_OTP_Session
	 */
	private $session;

	public function __construct() {
		$this->opts    = breo_alpha_otp_get_options();
		$this->session = new Breo_Alpha_OTP_Session();
	}

	/**
	 * Whether the feature should run at all.
	 *
	 * @return bool
	 */
	private function is_active() {
		return 'yes' === $this->opts['enabled'] && Breo_Alpha_OTP_SMS::is_configured();
	}

	/** Whether a number without an account may sign up. */
	private function allows_register() {
		return 'yes' === $this->opts['allow_register'];
	}

	/**
	 * Register hooks. AJAX handlers are always registered; UI only when active.
	 */
	public function init() {
		// AJAX (logged-out visitors use the nopriv variants).
		foreach ( array( 'send', 'verify', 'resend', 'select', 'register', 'nonce' ) as $action ) {
			add_action( 'wp_ajax_nopriv_breo_alpha_otp_' . $action, array( $this, 'ajax_' . $action ) );
			add_action( 'wp_ajax_breo_alpha_otp_' . $action, array( $this, 'ajax_' . $action ) );
		}

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
	 * Enqueue CSS + JS for logged-out visitors.
	 */
	public function enqueue() {
		if ( is_user_logged_in() ) {
			return;
		}

		wp_enqueue_style(
			'breo-alpha-otp',
			BREO_ALPHA_OTP_URL . 'assets/css/breo-otp-login.css',
			array(),
			BREO_ALPHA_OTP_VERSION
		);

		wp_enqueue_script(
			'breo-alpha-otp',
			BREO_ALPHA_OTP_URL . 'assets/js/breo-otp-login.js',
			array( 'jquery' ),
			BREO_ALPHA_OTP_VERSION,
			true
		);

		wp_localize_script(
			'breo-alpha-otp',
			'breoAlphaOtp',
			array(
				'ajaxurl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE ),
				'otpLength' => (int) $this->opts['otp_length'],
				'otpFirst'  => 'yes' === $this->opts['otp_first'],
				'hideEmail' => 'yes' === $this->opts['hide_email_login'],
				'i18n'      => array(
					'sending'    => __( 'Sending…', 'breo-alpha-otp-login' ),
					'verifying'  => __( 'Checking…', 'breo-alpha-otp-login' ),
					'sentTo'     => __( 'Enter the code we sent to', 'breo-alpha-otp-login' ),
					'enterPhone' => __( 'Please enter your mobile number.', 'breo-alpha-otp-login' ),
					'enterCode'  => __( 'Please enter the full code.', 'breo-alpha-otp-login' ),
					'enterName'  => __( 'Please tell us your name.', 'breo-alpha-otp-login' ),
					'creating'   => __( 'Creating your account…', 'breo-alpha-otp-login' ),
					'connError'  => __( 'Connection error. Please try again.', 'breo-alpha-otp-login' ),
					'chooseAcct' => __( 'Choose an account to continue', 'breo-alpha-otp-login' ),
					'loggingIn'  => __( 'Signing you in…', 'breo-alpha-otp-login' ),
					'orders'     => __( 'orders', 'breo-alpha-otp-login' ),
				),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Render
	 * --------------------------------------------------------------------- */

	/**
	 * Output the phone sign-in UI inside the WooCommerce login form.
	 * Hidden by default; the JS reveals and arranges it (so no-JS visitors keep the
	 * normal email/password form).
	 */
	public function render_login_ui() {
		static $n = 0;
		$n++;
		$id     = 'breo-otp-' . $n;
		$length = max( 4, min( 8, (int) $this->opts['otp_length'] ) );
		?>
		<div class="breo-otp-wrap" style="display:none;">

			<div class="breo-otp-phone" style="display:none;">
				<p class="breo-otp-field form-row form-row-wide">
					<label class="breo-otp-label" for="<?php echo esc_attr( $id ); ?>-tel"><?php esc_html_e( 'Mobile number', 'breo-alpha-otp-login' ); ?></label>
					<span class="breo-otp-tel">
						<span class="breo-otp-cc" aria-hidden="true">+880</span>
						<input id="<?php echo esc_attr( $id ); ?>-tel" type="tel" inputmode="numeric" autocomplete="tel-national" class="breo-otp-phone-input input-text" placeholder="01XXXXXXXXX" />
					</span>
				</p>
				<div class="breo-otp-notice" role="alert" style="display:none;"></div>
				<button type="button" class="breo-otp-send button"><?php echo esc_html( $this->opts['btn_otp_label'] ); ?></button>
			</div>

			<div class="breo-otp-code" style="display:none;">
				<div class="breo-otp-sent">
					<span class="breo-otp-sent-txt"></span>
					<a class="breo-otp-change" role="button" tabindex="0"><?php esc_html_e( 'Change', 'breo-alpha-otp-login' ); ?></a>
				</div>
				<div class="breo-otp-notice" role="alert" style="display:none;"></div>
				<div class="breo-otp-digits" role="group" aria-label="<?php esc_attr_e( 'One-time code', 'breo-alpha-otp-login' ); ?>">
					<?php for ( $i = 0; $i < $length; $i++ ) : ?>
						<input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="<?php echo 0 === $i ? 'one-time-code' : 'off'; ?>" class="breo-otp-digit" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: digit number */ __( 'Digit %d', 'breo-alpha-otp-login' ), $i + 1 ) ); ?>" />
					<?php endfor; ?>
				</div>
				<button type="button" class="breo-otp-verify button"><?php esc_html_e( 'Continue', 'breo-alpha-otp-login' ); ?></button>
				<div class="breo-otp-resend-row">
					<a class="breo-otp-resend" role="button" tabindex="0"><?php esc_html_e( 'Resend code', 'breo-alpha-otp-login' ); ?></a>
					<span class="breo-otp-timer"></span>
				</div>
			</div>

			<?php if ( $this->allows_register() ) : ?>
				<div class="breo-otp-profile" style="display:none;">
					<p class="breo-otp-profile-h"><strong><?php esc_html_e( 'Welcome to Breo!', 'breo-alpha-otp-login' ); ?></strong> <?php esc_html_e( 'Your number is verified. Just tell us your name.', 'breo-alpha-otp-login' ); ?></p>
					<div class="breo-otp-notice" role="alert" style="display:none;"></div>
					<p class="breo-otp-field form-row form-row-wide">
						<label for="<?php echo esc_attr( $id ); ?>-name"><?php esc_html_e( 'Your name', 'breo-alpha-otp-login' ); ?></label>
						<input id="<?php echo esc_attr( $id ); ?>-name" type="text" class="breo-otp-name input-text" autocomplete="name" maxlength="60" />
					</p>
					<p class="breo-otp-field form-row form-row-wide">
						<label for="<?php echo esc_attr( $id ); ?>-email"><?php esc_html_e( 'Email', 'breo-alpha-otp-login' ); ?> <span class="breo-otp-opt"><?php esc_html_e( '(optional, for order updates)', 'breo-alpha-otp-login' ); ?></span></label>
						<input id="<?php echo esc_attr( $id ); ?>-email" type="email" class="breo-otp-email input-text" autocomplete="email" maxlength="100" />
					</p>
					<button type="button" class="breo-otp-create button"><?php esc_html_e( 'Create my account', 'breo-alpha-otp-login' ); ?></button>
				</div>
			<?php endif; ?>

			<div class="breo-otp-toggle">
				<button type="button" class="breo-otp-to-otp button"><?php esc_html_e( 'Use your mobile number instead', 'breo-alpha-otp-login' ); ?></button>
				<?php if ( 'yes' !== $this->opts['hide_email_login'] ) : ?>
					<button type="button" class="breo-otp-to-pwd button"><?php echo esc_html( $this->opts['btn_pwd_label'] ); ?></button>
				<?php endif; ?>
			</div>

		</div>
		<?php
	}

	/* --------------------------------------------------------------------- *
	 * AJAX
	 * --------------------------------------------------------------------- */

	/**
	 * Hand out a freshly minted nonce.
	 *
	 * WP ROCKET: a cached page can outlive the nonce printed into it, after which
	 * every request from that page fails the security check for a real customer.
	 * This endpoint lets the browser mint a fresh one at the moment of use and
	 * retry. Deliberately not behind preflight(): requiring a valid nonce to obtain
	 * a nonce would defeat the purpose. It reveals nothing a page load does not
	 * already reveal, and every action it protects is rate-limited on its own.
	 */
	public function ajax_nonce() {
		wp_send_json_success( array( 'nonce' => wp_create_nonce( self::NONCE ) ) );
	}

	/**
	 * Guard shared by every AJAX handler.
	 */
	private function preflight() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			// 'code' lets the browser retry once with a freshly minted nonce.
			wp_send_json_error(
				array(
					'code'    => 'bad_nonce',
					'message' => __( 'Security check failed. Please reload the page.', 'breo-alpha-otp-login' ),
				),
				403
			);
		}
		if ( ! $this->is_active() ) {
			wp_send_json_error( array( 'message' => __( 'Phone sign-in is currently unavailable.', 'breo-alpha-otp-login' ) ), 503 );
		}
		if ( is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You are already signed in.', 'breo-alpha-otp-login' ) ), 400 );
		}
	}

	/**
	 * Step 1: phone in, code out. The reply is the same for a new and a known
	 * number, so this endpoint does not reveal who has an account.
	 */
	public function ajax_send() {
		$this->preflight();

		$canonical = Breo_Alpha_OTP_Session::normalize_phone(
			isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : ''
		);

		if ( ! $canonical ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid Bangladeshi mobile number.', 'breo-alpha-otp-login' ) ), 400 );
		}

		if ( $this->session->is_rate_limited( $canonical, $this->opts['max_per_day'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many code requests. Please try again later.', 'breo-alpha-otp-login' ) ), 429 );
		}

		$users  = Breo_Alpha_OTP_Session::find_users_by_phone( $canonical );
		$is_new = empty( $users );
		if ( $is_new && ! $this->allows_register() ) {
			wp_send_json_error( array( 'message' => __( 'No account uses this number yet. Sign in with email and password, or create an account first.', 'breo-alpha-otp-login' ) ), 404 );
		}

		$otp = Breo_Alpha_OTP_Session::generate_otp( $this->opts['otp_length'] );
		$sms = Breo_Alpha_OTP_SMS::send( $canonical, $this->build_message( $otp ) );
		if ( ! $sms['success'] ) {
			// Surface a friendly message; keep the gateway detail in the log only.
			error_log( 'Breo Alpha OTP send failed: ' . $sms['message'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			wp_send_json_error( array( 'message' => __( 'Could not send the code right now. Please try again in a moment.', 'breo-alpha-otp-login' ) ), 502 );
		}

		$this->session->store_otp( $canonical, $otp, wp_list_pluck( $users, 'ID' ), $this->opts['otp_expiry'], $this->opts['resend_wait'], array( 'new' => $is_new ) );
		$this->session->record_request( $canonical );

		wp_send_json_success(
			array(
				'masked'     => Breo_Alpha_OTP_Session::mask_phone( $canonical ),
				'resendWait' => (int) $this->opts['resend_wait'],
				'message'    => __( 'A one-time code has been sent to your phone.', 'breo-alpha-otp-login' ),
			)
		);
	}

	/**
	 * Step 2: verify the code, then sign in, offer an account picker, or (new
	 * number) open the name step.
	 */
	public function ajax_verify() {
		$this->preflight();

		$otp = isset( $_POST['otp'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['otp'] ) ) : '';
		if ( '' === $otp ) {
			wp_send_json_error( array( 'message' => __( 'Please enter the code.', 'breo-alpha-otp-login' ) ), 400 );
		}

		$result = $this->session->verify_otp( $otp, $this->opts['max_attempts'] );
		if ( ! $result['ok'] ) {
			$messages = array(
				'expired'  => __( 'This code has expired. Please request a new one.', 'breo-alpha-otp-login' ),
				'locked'   => __( 'Too many incorrect attempts. Please request a new code.', 'breo-alpha-otp-login' ),
				'mismatch' => __( 'Incorrect code. Please try again.', 'breo-alpha-otp-login' ),
				'none'     => __( 'Your session expired. Please request a new code.', 'breo-alpha-otp-login' ),
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

		if ( ! $user_ids && ! empty( $data['new'] ) && $this->allows_register() ) {
			// Verified number, no account yet: give the customer time to type a name.
			$data['profile_until'] = time() + self::PROFILE_WINDOW;
			$this->session->set_data( $data, self::PROFILE_WINDOW );
			wp_send_json_success( array( 'needsProfile' => true ) );
		}

		if ( count( $user_ids ) === 1 ) {
			$this->login_user( $user_ids[0] );
			$this->session->clear_data();
			wp_send_json_success( array( 'redirect' => $this->redirect_url() ) );
		}

		if ( count( $user_ids ) > 1 ) {
			// A phone is not a unique key in WooCommerce, so in rare cases more than one
			// account shares it. By default we log into the primary account (the one
			// with the most orders, newest as a tiebreak). Stores that want the
			// customer to choose can switch on the picker.
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

		wp_send_json_error( array( 'message' => __( 'Your session expired. Please request a new code.', 'breo-alpha-otp-login' ), 'reset' => true ), 400 );
	}

	/**
	 * Step 3 (new numbers only): create the account for the verified number and sign in.
	 */
	public function ajax_register() {
		$this->preflight();

		$data = $this->session->get_data();
		if ( ! $this->allows_register() || empty( $data['otp_verified'] ) || empty( $data['new'] ) || empty( $data['phone'] )
			|| empty( $data['profile_until'] ) || time() > (int) $data['profile_until'] ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please start again.', 'breo-alpha-otp-login' ), 'reset' => true ), 400 );
		}
		$canonical = $data['phone'];

		// An account may have been created for this number meanwhile. The number is
		// verified, so this is an ordinary sign-in.
		$existing = Breo_Alpha_OTP_Session::find_users_by_phone( $canonical );
		if ( $existing ) {
			$ids = array_map( 'intval', wp_list_pluck( $existing, 'ID' ) );
			$uid = count( $ids ) > 1 ? $this->resolve_primary_user( $ids ) : $ids[0];
			$this->login_user( $uid );
			$this->session->clear_data();
			wp_send_json_success( array( 'redirect' => $this->redirect_url() ) );
		}

		$name = isset( $_POST['name'] ) ? trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) ) : '';
		if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 60 ) {
			wp_send_json_error( array( 'message' => __( 'Please tell us your name.', 'breo-alpha-otp-login' ) ), 400 );
		}

		$typed = isset( $_POST['email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['email'] ) ) ) : '';
		$email = '';
		if ( '' !== $typed ) {
			$email = sanitize_email( $typed );
			if ( ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'That email address does not look right. Check it, or leave it empty.', 'breo-alpha-otp-login' ) ), 400 );
			}
			if ( email_exists( $email ) ) {
				// Verifying a phone proves nothing about who owns an email account.
				wp_send_json_error( array( 'message' => __( 'This email already has an account. Sign in with it instead, or leave the email empty.', 'breo-alpha-otp-login' ) ), 409 );
			}
		}

		$user_id = $this->create_customer( $canonical, $name, $email );
		if ( is_wp_error( $user_id ) ) {
			error_log( 'Breo Alpha OTP sign-up failed: ' . $user_id->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			wp_send_json_error( array( 'message' => __( 'We could not create your account right now. Please try again.', 'breo-alpha-otp-login' ) ), 500 );
		}

		$this->login_user( $user_id );
		$this->session->clear_data();
		wp_send_json_success( array( 'redirect' => $this->redirect_url() ) );
	}

	/**
	 * Resend the OTP, respecting the cooldown and daily cap.
	 */
	public function ajax_resend() {
		$this->preflight();

		$data = $this->session->get_data();
		if ( empty( $data['phone'] ) || ( empty( $data['user_ids'] ) && empty( $data['new'] ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please start again.', 'breo-alpha-otp-login' ), 'reset' => true ), 400 );
		}

		$wait = $this->session->resend_cooldown_remaining();
		if ( $wait > 0 ) {
			/* translators: %d: seconds to wait. */
			wp_send_json_error( array( 'message' => sprintf( __( 'Please wait %d seconds before requesting another code.', 'breo-alpha-otp-login' ), $wait ) ), 429 );
		}

		$canonical = $data['phone'];
		if ( $this->session->is_rate_limited( $canonical, $this->opts['max_per_day'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many code requests. Please try again later.', 'breo-alpha-otp-login' ) ), 429 );
		}

		$otp = Breo_Alpha_OTP_Session::generate_otp( $this->opts['otp_length'] );
		$sms = Breo_Alpha_OTP_SMS::send( $canonical, $this->build_message( $otp ) );
		if ( ! $sms['success'] ) {
			error_log( 'Breo Alpha OTP resend failed: ' . $sms['message'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			wp_send_json_error( array( 'message' => __( 'Could not send the code right now. Please try again in a moment.', 'breo-alpha-otp-login' ) ), 502 );
		}

		$this->session->store_otp( $canonical, $otp, isset( $data['user_ids'] ) ? $data['user_ids'] : array(), $this->opts['otp_expiry'], $this->opts['resend_wait'], array( 'new' => ! empty( $data['new'] ) ) );
		$this->session->record_request( $canonical );

		wp_send_json_success(
			array(
				'resendWait' => (int) $this->opts['resend_wait'],
				'message'    => __( 'A new code has been sent.', 'breo-alpha-otp-login' ),
			)
		);
	}

	/**
	 * Account picker selection (shared phone): log into the chosen account.
	 */
	public function ajax_select() {
		$this->preflight();

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$data    = $this->session->get_data();

		if ( empty( $data['otp_verified'] ) || empty( $data['user_ids'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Please start again.', 'breo-alpha-otp-login' ), 'reset' => true ), 400 );
		}

		if ( ! $user_id || ! in_array( $user_id, array_map( 'intval', $data['user_ids'] ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid account selection.', 'breo-alpha-otp-login' ) ), 400 );
		}

		$this->login_user( $user_id );
		$this->session->clear_data();
		wp_send_json_success( array( 'redirect' => $this->redirect_url() ) );
	}

	/* --------------------------------------------------------------------- *
	 * Helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Create a WooCommerce customer for a verified number.
	 *
	 * The username is internal (bd01XXXXXXXXX); customers see their name. The
	 * password is random and never shown: phone sign-in is the way in, and
	 * "Lost your password?" works once an email is added.
	 *
	 * @param string $canonical 8801XXXXXXXXX.
	 * @param string $name      Full name.
	 * @param string $email     Valid, unused email or ''.
	 * @return int|WP_Error
	 */
	private function create_customer( $canonical, $name, $email ) {
		$parts = explode( ' ', $name, 2 );
		$first = $parts[0];
		$last  = isset( $parts[1] ) ? $parts[1] : '';
		$local = substr( $canonical, 2 ); // 01XXXXXXXXX

		$login = 'bd' . $local;
		for ( $i = 2; username_exists( $login ); $i++ ) {
			$login = 'bd' . $local . '-' . $i;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $email,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => $name,
				'nickname'     => $first,
				'role'         => 'customer',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, 'billing_phone', $local );
		update_user_meta( $user_id, 'billing_first_name', $first );
		update_user_meta( $user_id, 'billing_last_name', $last );
		update_user_meta( $user_id, 'billing_country', 'BD' );
		if ( '' !== $email ) {
			update_user_meta( $user_id, 'billing_email', $email );
		}

		// WooCommerce's own "customer created" hook: its welcome email goes out when
		// there is an email address, and other plugins can react as usual.
		do_action( 'woocommerce_created_customer', $user_id, array( 'user_login' => $login, 'user_email' => $email ), true );
		do_action( 'breo_alpha_otp_customer_created', $user_id, $canonical );

		return $user_id;
	}

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
			$template = 'Your [site] code is [otp]. It expires in [min] minutes.';
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
			wp_send_json_error( array( 'message' => __( 'Account could not be loaded.', 'breo-alpha-otp-login' ) ), 400 );
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
			$orders     = function_exists( 'wc_get_customer_order_count' ) ? (int) wc_get_customer_order_count( $uid ) : 0;
			$accounts[] = array(
				'id'     => (int) $uid,
				'email'  => $this->mask_email( $user->user_email ),
				'name'   => $user->display_name ? $user->display_name : $user->user_login,
				'orders' => $orders,
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
