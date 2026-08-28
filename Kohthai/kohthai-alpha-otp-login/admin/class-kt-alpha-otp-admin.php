<?php
/**
 * Admin settings page (Settings → Alpha OTP Login) and dependency notices.
 *
 * @package KT_Alpha_OTP_Login
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class KT_Alpha_OTP_Admin {

	const PAGE  = 'kohthai-alpha-otp-login';
	const GROUP = 'kt_alpha_otp_group';

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KT_ALPHA_OTP_FILE ), array( $this, 'action_links' ) );
	}

	public function add_menu() {
		add_options_page(
			__( 'Alpha OTP Login', 'kohthai-alpha-otp-login' ),
			__( 'Alpha OTP Login', 'kohthai-alpha-otp-login' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'kohthai-alpha-otp-login' ) . '</a>' );
		return $links;
	}

	public function register_settings() {
		register_setting(
			self::GROUP,
			KT_ALPHA_OTP_OPTION,
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
		$defaults = kt_alpha_otp_default_options();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		foreach ( array( 'enabled', 'otp_first', 'hide_email_login' ) as $key ) {
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

		return $out;
	}

	/**
	 * Warn if the Alpha SMS API key is missing (the feature can't send without it).
	 */
	public function dependency_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( KT_Alpha_OTP_SMS::is_configured() ) {
			return;
		}

		$screen = get_current_screen();
		// Only nag on our own page and the plugins list to avoid noise.
		if ( $screen && ! in_array( $screen->id, array( 'settings_page_' . self::PAGE, 'plugins' ), true ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Kohthai Alpha OTP Login', 'kohthai-alpha-otp-login' ) . ':</strong> ' .
			esc_html__( 'No Alpha SMS API key found. Install and configure the Alpha SMS plugin (enter your sms.net.bd API key) so OTP codes can be sent.', 'kohthai-alpha-otp-login' ) .
			'</p></div>';
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o          = kt_alpha_otp_get_options();
		$configured = KT_Alpha_OTP_SMS::is_configured();
		$name       = KT_ALPHA_OTP_OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Alpha SMS OTP Login', 'kohthai-alpha-otp-login' ); ?></h1>
			<p>
				<?php esc_html_e( 'Passwordless phone-number login for your WooCommerce account form, powered by your existing Alpha SMS gateway.', 'kohthai-alpha-otp-login' ); ?>
			</p>

			<p>
				<strong><?php esc_html_e( 'Alpha SMS API key:', 'kohthai-alpha-otp-login' ); ?></strong>
				<?php if ( $configured ) : ?>
					<span style="color:#1a7f37;">&#10004; <?php esc_html_e( 'detected', 'kohthai-alpha-otp-login' ); ?></span>
				<?php else : ?>
					<span style="color:#b32d2e;">&#10008; <?php esc_html_e( 'not found — configure the Alpha SMS plugin first', 'kohthai-alpha-otp-login' ); ?></span>
				<?php endif; ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable OTP login', 'kohthai-alpha-otp-login' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="yes" <?php checked( $o['enabled'], 'yes' ); ?> />
							<?php esc_html_e( 'Add "Login with OTP" to the WooCommerce login form.', 'kohthai-alpha-otp-login' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show OTP form first', 'kohthai-alpha-otp-login' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[otp_first]" value="yes" <?php checked( $o['otp_first'], 'yes' ); ?> />
							<?php esc_html_e( 'Display the phone/OTP login before the email & password form.', 'kohthai-alpha-otp-login' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Phone-only login', 'kohthai-alpha-otp-login' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hide_email_login]" value="yes" <?php checked( $o['hide_email_login'], 'yes' ); ?> />
							<?php esc_html_e( 'Hide the "Login with Email & Password" button (OTP only).', 'kohthai-alpha-otp-login' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="kt-otp-length"><?php esc_html_e( 'OTP length', 'kohthai-alpha-otp-login' ); ?></label></th>
						<td><input id="kt-otp-length" type="number" min="4" max="8" name="<?php echo esc_attr( $name ); ?>[otp_length]" value="<?php echo esc_attr( $o['otp_length'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Number of digits (4–8).', 'kohthai-alpha-otp-login' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="kt-otp-expiry"><?php esc_html_e( 'OTP validity (seconds)', 'kohthai-alpha-otp-login' ); ?></label></th>
						<td><input id="kt-otp-expiry" type="number" min="30" max="900" name="<?php echo esc_attr( $name ); ?>[otp_expiry]" value="<?php echo esc_attr( $o['otp_expiry'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="kt-otp-resend"><?php esc_html_e( 'Resend wait (seconds)', 'kohthai-alpha-otp-login' ); ?></label></th>
						<td><input id="kt-otp-resend" type="number" min="0" max="600" name="<?php echo esc_attr( $name ); ?>[resend_wait]" value="<?php echo esc_attr( $o['resend_wait'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="kt-otp-max-day"><?php esc_html_e( 'Max OTPs per day', 'kohthai-alpha-otp-login' ); ?></label></th>
						<td><input id="kt-otp-max-day" type="number" min="1" max="100" name="<?php echo esc_attr( $name ); ?>[max_per_day]" value="<?php echo esc_attr( $o['max_per_day'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Per phone number and per IP address (anti-abuse).', 'kohthai-alpha-otp-login' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="kt-otp-max-attempts"><?php esc_html_e( 'Max wrong attempts', 'kohthai-alpha-otp-login' ); ?></label></th>
						<td><input id="kt-otp-max-attempts" type="number" min="1" max="20" name="<?php echo esc_attr( $name ); ?>[max_attempts]" value="<?php echo esc_attr( $o['max_attempts'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Wrong codes before the OTP is invalidated.', 'kohthai-alpha-otp-login' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="kt-otp-multi"><?php esc_html_e( 'Shared phone number', 'kohthai-alpha-otp-login' ); ?></label></th>
						<td>
							<select id="kt-otp-multi" name="<?php echo esc_attr( $name ); ?>[multi_account]">
								<option value="auto" <?php selected( $o['multi_account'], 'auto' ); ?>><?php esc_html_e( 'Log into the primary account automatically (recommended)', 'kohthai-alpha-otp-login' ); ?></option>
								<option value="picker" <?php selected( $o['multi_account'], 'picker' ); ?>><?php esc_html_e( 'Let the customer choose', 'kohthai-alpha-otp-login' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'If the same number is on more than one account, "primary" = the one with the most orders (newest as a tiebreak).', 'kohthai-alpha-otp-login' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="kt-otp-sms"><?php esc_html_e( 'SMS text', 'kohthai-alpha-otp-login' ); ?></label></th>
						<td>
							<textarea id="kt-otp-sms" rows="3" cols="60" name="<?php echo esc_attr( $name ); ?>[sms_template]"><?php echo esc_textarea( $o['sms_template'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Tokens: [otp] = code, [site] = site name, [min] = validity in minutes.', 'kohthai-alpha-otp-login' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="kt-otp-redirect"><?php esc_html_e( 'Redirect after login', 'kohthai-alpha-otp-login' ); ?></label></th>
						<td><input id="kt-otp-redirect" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[redirect]" value="<?php echo esc_attr( $o['redirect'] ); ?>" placeholder="<?php echo esc_attr( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to send users to My Account.', 'kohthai-alpha-otp-login' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="kt-otp-btn1"><?php esc_html_e( 'OTP button label', 'kohthai-alpha-otp-login' ); ?></label></th>
						<td><input id="kt-otp-btn1" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[btn_otp_label]" value="<?php echo esc_attr( $o['btn_otp_label'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="kt-otp-btn2"><?php esc_html_e( 'Email login button label', 'kohthai-alpha-otp-login' ); ?></label></th>
						<td><input id="kt-otp-btn2" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[btn_pwd_label]" value="<?php echo esc_attr( $o['btn_pwd_label'] ); ?>" /></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
