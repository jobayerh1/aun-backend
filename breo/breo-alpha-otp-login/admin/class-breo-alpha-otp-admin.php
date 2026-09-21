<?php
/**
 * Admin settings page (Settings → Alpha OTP Login) and dependency notices.
 *
 * @package Breo_Alpha_OTP_Login
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Breo_Alpha_OTP_Admin {

	const PAGE  = 'breo-alpha-otp-login';
	const GROUP = 'breo_alpha_otp_group';

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BREO_ALPHA_OTP_FILE ), array( $this, 'action_links' ) );
	}

	public function add_menu() {
		add_options_page(
			__( 'Alpha OTP Login', 'breo-alpha-otp-login' ),
			__( 'Alpha OTP Login', 'breo-alpha-otp-login' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'breo-alpha-otp-login' ) . '</a>' );
		return $links;
	}

	public function register_settings() {
		register_setting(
			self::GROUP,
			BREO_ALPHA_OTP_OPTION,
			array( $this, 'sanitize' )
		);
	}

	/**
	 * Sanitize the whole option array.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = breo_alpha_otp_default_options();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		foreach ( array( 'enabled', 'otp_first', 'hide_email_login', 'allow_register' ) as $key ) {
			$out[ $key ] = ( isset( $input[ $key ] ) && 'yes' === $input[ $key ] ) ? 'yes' : 'no';
		}

		$out['otp_length']   = min( 8, max( 4, isset( $input['otp_length'] ) ? (int) $input['otp_length'] : $defaults['otp_length'] ) );
		$out['otp_expiry']   = min( 900, max( 30, isset( $input['otp_expiry'] ) ? (int) $input['otp_expiry'] : $defaults['otp_expiry'] ) );
		$out['resend_wait']  = min( 600, max( 0, isset( $input['resend_wait'] ) ? (int) $input['resend_wait'] : $defaults['resend_wait'] ) );
		$out['max_per_day']  = min( 100, max( 1, isset( $input['max_per_day'] ) ? (int) $input['max_per_day'] : $defaults['max_per_day'] ) );
		$out['max_attempts'] = min( 20, max( 1, isset( $input['max_attempts'] ) ? (int) $input['max_attempts'] : $defaults['max_attempts'] ) );

		$out['multi_account'] = ( isset( $input['multi_account'] ) && 'picker' === $input['multi_account'] ) ? 'picker' : 'auto';

		$out['sms_template'] = isset( $input['sms_template'] ) ? sanitize_textarea_field( $input['sms_template'] ) : $defaults['sms_template'];
		if ( '' === trim( $out['sms_template'] ) ) {
			$out['sms_template'] = $defaults['sms_template'];
		}

		$out['redirect']      = isset( $input['redirect'] ) ? esc_url_raw( trim( $input['redirect'] ) ) : '';
		$out['btn_otp_label'] = isset( $input['btn_otp_label'] ) ? sanitize_text_field( $input['btn_otp_label'] ) : $defaults['btn_otp_label'];
		$out['btn_pwd_label'] = isset( $input['btn_pwd_label'] ) ? sanitize_text_field( $input['btn_pwd_label'] ) : $defaults['btn_pwd_label'];

		if ( '' === $out['btn_otp_label'] ) {
			$out['btn_otp_label'] = $defaults['btn_otp_label'];
		}
		if ( '' === $out['btn_pwd_label'] ) {
			$out['btn_pwd_label'] = $defaults['btn_pwd_label'];
		}

		// Own gateway key: a blank field keeps the saved key (it is never printed back),
		// the "remove" box clears it.
		$current          = breo_alpha_otp_get_options();
		$typed            = isset( $input['api_key'] ) ? trim( sanitize_text_field( $input['api_key'] ) ) : '';
		$out['api_key']   = '' !== $typed ? $typed : (string) $current['api_key'];
		if ( ! empty( $input['api_key_clear'] ) ) {
			$out['api_key'] = '';
		}
		$out['sender_id'] = isset( $input['sender_id'] ) ? sanitize_text_field( trim( $input['sender_id'] ) ) : '';

		// Saved from the 1.1 screen, so already in the 1.1 wording (see breo_alpha_otp_maybe_upgrade()).
		$out['db'] = 110;

		return $out;
	}

	/**
	 * Warn if the Alpha SMS API key is missing (the feature can't send without it).
	 */
	public function dependency_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( Breo_Alpha_OTP_SMS::is_configured() ) {
			return;
		}

		$screen = get_current_screen();
		// Only nag on our own page and the plugins list to avoid noise.
		if ( $screen && ! in_array( $screen->id, array( 'settings_page_' . self::PAGE, 'plugins' ), true ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Breo Alpha OTP Login', 'breo-alpha-otp-login' ) . ':</strong> ' .
			esc_html__( 'No sms.net.bd API key yet. Enter it under Settings → Alpha OTP Login (or configure the Alpha SMS plugin) so OTP codes can be sent.', 'breo-alpha-otp-login' ) .
			'</p></div>';
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o    = breo_alpha_otp_get_options();
		$name = BREO_ALPHA_OTP_OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Alpha SMS OTP Login', 'breo-alpha-otp-login' ); ?></h1>
			<p>
				<?php esc_html_e( 'Passwordless phone-number login for your WooCommerce account form, powered by your existing Alpha SMS gateway.', 'breo-alpha-otp-login' ); ?>
			</p>

			<?php $source = Breo_Alpha_OTP_SMS::key_source(); ?>
			<p>
				<strong><?php esc_html_e( 'SMS gateway (sms.net.bd):', 'breo-alpha-otp-login' ); ?></strong>
				<?php if ( 'alpha_sms' === $source ) : ?>
					<span style="color:#1a7f37;">&#10004; <?php esc_html_e( 'using the Alpha SMS plugin’s API key', 'breo-alpha-otp-login' ); ?></span>
				<?php elseif ( 'own' === $source ) : ?>
					<span style="color:#1a7f37;">&#10004; <?php esc_html_e( 'using the API key saved below', 'breo-alpha-otp-login' ); ?></span>
				<?php else : ?>
					<span style="color:#b32d2e;">&#10008; <?php esc_html_e( 'no API key yet — enter it below', 'breo-alpha-otp-login' ); ?></span>
				<?php endif; ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'SMS gateway', 'breo-alpha-otp-login' ); ?></h2>
				<p class="description" style="max-width:52em"><?php esc_html_e( 'If the Alpha SMS plugin is installed with a key, that key is used automatically and these fields are ignored. Otherwise enter your sms.net.bd API key here.', 'breo-alpha-otp-login' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="breo-otp-api"><?php esc_html_e( 'sms.net.bd API key', 'breo-alpha-otp-login' ); ?></label></th>
						<td>
							<input id="breo-otp-api" type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $name ); ?>[api_key]" value="" placeholder="<?php echo esc_attr( '' !== $o['api_key'] ? '•••••••• ' . __( 'saved — leave blank to keep', 'breo-alpha-otp-login' ) : '' ); ?>" />
							<?php if ( '' !== $o['api_key'] ) : ?>
								<p><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[api_key_clear]" value="1" /> <?php esc_html_e( 'Remove the saved key', 'breo-alpha-otp-login' ); ?></label></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="breo-otp-sid"><?php esc_html_e( 'Sender ID (optional)', 'breo-alpha-otp-login' ); ?></label></th>
						<td><input id="breo-otp-sid" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[sender_id]" value="<?php echo esc_attr( $o['sender_id'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Only if sms.net.bd approved a sender ID (masking) for your account.', 'breo-alpha-otp-login' ); ?></p></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Login behaviour', 'breo-alpha-otp-login' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable OTP login', 'breo-alpha-otp-login' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="yes" <?php checked( $o['enabled'], 'yes' ); ?> />
							<?php esc_html_e( 'Add "Login with OTP" to the WooCommerce login form.', 'breo-alpha-otp-login' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show OTP form first', 'breo-alpha-otp-login' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[otp_first]" value="yes" <?php checked( $o['otp_first'], 'yes' ); ?> />
							<?php esc_html_e( 'Display the phone/OTP login before the email & password form.', 'breo-alpha-otp-login' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Phone-only login', 'breo-alpha-otp-login' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hide_email_login]" value="yes" <?php checked( $o['hide_email_login'], 'yes' ); ?> />
							<?php esc_html_e( 'Hide the "Use email and password instead" link (phone only).', 'breo-alpha-otp-login' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'New customers', 'breo-alpha-otp-login' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[allow_register]" value="yes" <?php checked( $o['allow_register'], 'yes' ); ?> />
							<?php esc_html_e( 'Let a number without an account sign up: after the code, the customer enters a name (email optional) and the account is created.', 'breo-alpha-otp-login' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="breo-otp-length"><?php esc_html_e( 'OTP length', 'breo-alpha-otp-login' ); ?></label></th>
						<td><input id="breo-otp-length" type="number" min="4" max="8" name="<?php echo esc_attr( $name ); ?>[otp_length]" value="<?php echo esc_attr( $o['otp_length'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Number of digits (4–8).', 'breo-alpha-otp-login' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="breo-otp-expiry"><?php esc_html_e( 'OTP validity (seconds)', 'breo-alpha-otp-login' ); ?></label></th>
						<td><input id="breo-otp-expiry" type="number" min="30" max="900" name="<?php echo esc_attr( $name ); ?>[otp_expiry]" value="<?php echo esc_attr( $o['otp_expiry'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="breo-otp-resend"><?php esc_html_e( 'Resend wait (seconds)', 'breo-alpha-otp-login' ); ?></label></th>
						<td><input id="breo-otp-resend" type="number" min="0" max="600" name="<?php echo esc_attr( $name ); ?>[resend_wait]" value="<?php echo esc_attr( $o['resend_wait'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="breo-otp-max-day"><?php esc_html_e( 'Max OTPs per day', 'breo-alpha-otp-login' ); ?></label></th>
						<td><input id="breo-otp-max-day" type="number" min="1" max="100" name="<?php echo esc_attr( $name ); ?>[max_per_day]" value="<?php echo esc_attr( $o['max_per_day'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Per phone number and per IP address (anti-abuse).', 'breo-alpha-otp-login' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="breo-otp-max-attempts"><?php esc_html_e( 'Max wrong attempts', 'breo-alpha-otp-login' ); ?></label></th>
						<td><input id="breo-otp-max-attempts" type="number" min="1" max="20" name="<?php echo esc_attr( $name ); ?>[max_attempts]" value="<?php echo esc_attr( $o['max_attempts'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Wrong codes before the OTP is invalidated.', 'breo-alpha-otp-login' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="breo-otp-multi"><?php esc_html_e( 'Shared phone number', 'breo-alpha-otp-login' ); ?></label></th>
						<td>
							<select id="breo-otp-multi" name="<?php echo esc_attr( $name ); ?>[multi_account]">
								<option value="auto" <?php selected( $o['multi_account'], 'auto' ); ?>><?php esc_html_e( 'Log into the primary account automatically (recommended)', 'breo-alpha-otp-login' ); ?></option>
								<option value="picker" <?php selected( $o['multi_account'], 'picker' ); ?>><?php esc_html_e( 'Let the customer choose', 'breo-alpha-otp-login' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'If the same number is on more than one account, "primary" = the one with the most orders (newest as a tiebreak).', 'breo-alpha-otp-login' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="breo-otp-sms"><?php esc_html_e( 'SMS text', 'breo-alpha-otp-login' ); ?></label></th>
						<td>
							<textarea id="breo-otp-sms" rows="3" cols="60" name="<?php echo esc_attr( $name ); ?>[sms_template]"><?php echo esc_textarea( $o['sms_template'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Tokens: [otp] = code, [site] = site name, [min] = validity in minutes.', 'breo-alpha-otp-login' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="breo-otp-redirect"><?php esc_html_e( 'Redirect after login', 'breo-alpha-otp-login' ); ?></label></th>
						<td><input id="breo-otp-redirect" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[redirect]" value="<?php echo esc_attr( $o['redirect'] ); ?>" placeholder="<?php echo esc_attr( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to send users to My Account.', 'breo-alpha-otp-login' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="breo-otp-btn1"><?php esc_html_e( 'OTP button label', 'breo-alpha-otp-login' ); ?></label></th>
						<td><input id="breo-otp-btn1" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[btn_otp_label]" value="<?php echo esc_attr( $o['btn_otp_label'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="breo-otp-btn2"><?php esc_html_e( 'Email login button label', 'breo-alpha-otp-login' ); ?></label></th>
						<td><input id="breo-otp-btn2" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[btn_pwd_label]" value="<?php echo esc_attr( $o['btn_pwd_label'] ); ?>" /></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
