<?php
/**
 * My Account sign-in: Breo's version of WooCommerce's myaccount/form-login.php
 * (based on WooCommerce 9.9.0). Every WooCommerce hook is kept, so plugins still
 * attach where they expect.
 *
 * One card instead of two columns: phone first (Breo Alpha OTP Login, which also
 * signs new customers up), Google/Facebook (Breo Social Login), email + password
 * one tap away, and email sign-up tucked under it. Beside it, a photo and the
 * reasons to have an account.
 */

defined( 'ABSPATH' ) || exit;

$breo_reg    = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
$breo_otp_on = function_exists( 'breo_alpha_otp_get_options' ) && class_exists( 'Breo_Alpha_OTP_SMS' )
	&& 'yes' === breo_alpha_otp_get_options()['enabled'] && Breo_Alpha_OTP_SMS::is_configured();
$breo_join   = $breo_otp_on && 'yes' === breo_alpha_otp_get_options()['allow_register'];
$breo_social = class_exists( 'BREO_SL_Options' ) && BREO_SL_Options::active_providers();
$breo_btn    = wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '';

if ( $breo_join ) {
	$breo_lead = __( "Enter your mobile number and we'll text you a code. New to Breo? Your account is created in the same step.", 'breo-bd' );
} elseif ( $breo_otp_on ) {
	$breo_lead = __( "Enter your mobile number and we'll text you a code to sign in.", 'breo-bd' );
} elseif ( $breo_social ) {
	$breo_lead = __( 'Sign in with your email and password, or continue with Google or Facebook.', 'breo-bd' );
} else {
	$breo_lead = __( 'Sign in with your email and password.', 'breo-bd' );
}
$breo_title = ( $breo_join || $breo_reg ) ? __( 'Sign in or create your account', 'breo-bd' ) : __( 'Sign in to your account', 'breo-bd' );
$breo_w     = function_exists( 'breo_bd_warranty_period' ) ? breo_bd_warranty_period() : '';
$breo_perks = array(
	array( 'truck', __( 'Track every order', 'breo-bd' ), __( 'Live delivery updates and your full order history.', 'breo-bd' ) ),
	/* translators: %s: warranty length, e.g. "1-year". */
	array( 'badge', $breo_w ? sprintf( __( 'Your %s warranty, on file', 'breo-bd' ), $breo_w ) : __( 'Your warranty, on file', 'breo-bd' ), __( 'Your purchase record is here whenever you need service.', 'breo-bd' ) ),
	array( 'cart', __( 'Faster checkout', 'breo-bd' ), __( 'Your address and phone are saved for next time.', 'breo-bd' ) ),
	array( 'chat', __( 'Real help, locally', 'breo-bd' ), __( 'Questions about your device, answered by the Breo Bangladesh team.', 'breo-bd' ) ),
);

do_action( 'woocommerce_before_customer_login_form' ); ?>

<div class="breo-auth">
	<div class="breo-auth__card">
		<p class="breo-eyebrow"><?php esc_html_e( 'Breo account', 'breo-bd' ); ?></p>
		<h2 class="breo-auth__title"><?php echo esc_html( $breo_title ); ?></h2>
		<p class="breo-auth__lead"><?php echo esc_html( $breo_lead ); ?></p>

		<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>

			<?php do_action( 'woocommerce_login_form_start' ); ?>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="username"><?php esc_html_e( 'Username or email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore ?>
			</p>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
				<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
			</p>

			<?php do_action( 'woocommerce_login_form' ); ?>

			<p class="form-row">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
				</label>
				<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
				<button type="submit" class="woocommerce-button button woocommerce-form-login__submit<?php echo esc_attr( $breo_btn ); ?>" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>"><?php esc_html_e( 'Sign in', 'breo-bd' ); ?></button>
			</p>
			<p class="woocommerce-LostPassword lost_password">
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
			</p>

			<?php do_action( 'woocommerce_login_form_end' ); ?>

		</form>

		<?php if ( $breo_reg ) : ?>
			<details class="breo-auth__register"<?php echo ! empty( $_POST['register'] ) ? ' open' : ''; // phpcs:ignore ?>>
				<summary><?php esc_html_e( 'Prefer email?', 'breo-bd' ); ?> <strong><?php esc_html_e( 'Create an account with email', 'breo-bd' ); ?></strong></summary>

				<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >

					<?php do_action( 'woocommerce_register_form_start' ); ?>

					<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
						<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
							<label for="reg_username"><?php esc_html_e( 'Username', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
							<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore ?>
						</p>
					<?php endif; ?>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
						<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore ?>
					</p>

					<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
						<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
							<label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
							<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
						</p>
					<?php else : ?>
						<p class="breo-auth__note"><?php esc_html_e( 'A link to set a new password will be sent to your email address.', 'woocommerce' ); ?></p>
					<?php endif; ?>

					<?php
					// The Google/Facebook buttons already sit under the sign-in form above,
					// so they are not repeated inside this one.
					$breo_sl_prio = class_exists( 'BREO_SL_UI' ) ? has_action( 'woocommerce_register_form', array( 'BREO_SL_UI', 'render' ) ) : false;
					if ( false !== $breo_sl_prio ) {
						remove_action( 'woocommerce_register_form', array( 'BREO_SL_UI', 'render' ), $breo_sl_prio );
					}
					do_action( 'woocommerce_register_form' );
					if ( false !== $breo_sl_prio ) {
						add_action( 'woocommerce_register_form', array( 'BREO_SL_UI', 'render' ), $breo_sl_prio );
					}
					?>

					<p class="woocommerce-form-row form-row">
						<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
						<button type="submit" class="woocommerce-Button woocommerce-button button<?php echo esc_attr( $breo_btn ); ?> woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php esc_html_e( 'Create account', 'breo-bd' ); ?></button>
					</p>

					<?php do_action( 'woocommerce_register_form_end' ); ?>

				</form>
			</details>
		<?php endif; ?>

		<p class="breo-auth__legal">
			<?php
			printf(
				/* translators: 1: Terms link, 2: Privacy Policy link. */
				esc_html__( 'By continuing you agree to our %1$s and %2$s.', 'breo-bd' ),
				'<a href="' . esc_url( breo_bd_page_url( 'terms-conditions' ) ) . '">' . esc_html__( 'Terms & Conditions', 'breo-bd' ) . '</a>',
				'<a href="' . esc_url( breo_bd_page_url( 'privacy-policy' ) ) . '">' . esc_html__( 'Privacy Policy', 'breo-bd' ) . '</a>'
			);
			?>
		</p>
	</div>

	<aside class="breo-auth__aside" aria-label="<?php esc_attr_e( 'Why have a Breo account', 'breo-bd' ); ?>">
		<?php $breo_img = breo_bd_img_ref( 'N990000631:desk', array( 'size' => 'large', 'sizes' => '(max-width: 860px) 1px, 520px' ) ); ?>
		<?php if ( $breo_img ) : ?>
			<div class="breo-auth__photo">
				<?php echo $breo_img; // phpcs:ignore ?>
				<p class="breo-auth__caption"><span><?php esc_html_e( 'Breo Bangladesh', 'breo-bd' ); ?></span><?php esc_html_e( 'Your Breo, looked after.', 'breo-bd' ); ?></p>
			</div>
		<?php endif; ?>
		<ul class="breo-auth__perks">
			<?php foreach ( $breo_perks as $breo_p ) : ?>
				<li>
					<span class="breo-auth__ico"><?php echo breo_bd_icon( $breo_p[0], 20 ); // phpcs:ignore ?></span>
					<span><strong><?php echo esc_html( $breo_p[1] ); ?></strong><?php echo esc_html( $breo_p[2] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</aside>
</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
