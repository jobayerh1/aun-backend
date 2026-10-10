<?php
/**
 * [dserver_contact] — a lightweight contact / quote form (works without a form plugin).
 * Use it in Elementor's Shortcode widget. Messages go to the Customizer email address.
 *
 * @package DServer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the form.
 */
function dserver_contact_form(): string {
	$sent   = isset( $_GET['ds_sent'] ) ? sanitize_key( wp_unslash( $_GET['ds_sent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$plan   = isset( $_GET['plan'] ) ? sanitize_text_field( wp_unslash( $_GET['plan'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$topics = array( 'VPS hosting', 'Dedicated server', 'Cloud & managed services', 'Colocation', 'Backup & disaster recovery', 'Something else' );
	$notice = '';
	if ( '1' === $sent ) {
		$notice = '<p class="ds-form__notice ds-form__notice--ok" role="status">' . esc_html__( 'Thank you — your message has been sent. Our team replies within one business hour.', 'dserver' ) . '</p>';
	} elseif ( '0' === $sent ) {
		$notice = '<p class="ds-form__notice ds-form__notice--err" role="alert">' . esc_html__( 'Sorry, the message could not be sent. Please check the fields or email us directly.', 'dserver' ) . '</p>';
	}
	ob_start();
	?>
	<form class="ds-form" id="ds-contact" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input type="hidden" name="action" value="dserver_contact">
		<input type="hidden" name="ds_return" value="<?php echo esc_url( get_permalink() ?: home_url( '/' ) ); ?>">
		<input type="hidden" name="ds_ts" value="<?php echo esc_attr( (string) time() ); ?>">
		<div class="ds-form__hp" aria-hidden="true"><label>Website <input type="text" name="ds_website" tabindex="-1" autocomplete="off"></label></div>
		<div class="ds-form__row">
			<p><label for="ds-name"><?php esc_html_e( 'Name', 'dserver' ); ?> *</label><input id="ds-name" name="ds_name" type="text" required autocomplete="name" maxlength="120"></p>
			<p><label for="ds-email"><?php esc_html_e( 'Email', 'dserver' ); ?> *</label><input id="ds-email" name="ds_email" type="email" required autocomplete="email" maxlength="160"></p>
		</div>
		<div class="ds-form__row">
			<p><label for="ds-company"><?php esc_html_e( 'Company', 'dserver' ); ?></label><input id="ds-company" name="ds_company" type="text" autocomplete="organization" maxlength="120"></p>
			<p><label for="ds-topic"><?php esc_html_e( 'I’m interested in', 'dserver' ); ?></label><select id="ds-topic" name="ds_topic">
				<?php foreach ( $topics as $t ) : ?>
					<option <?php selected( $plan && 'VPS hosting' === $t ); ?>><?php echo esc_html( $t ); ?></option>
				<?php endforeach; ?>
			</select></p>
		</div>
		<p><label for="ds-message"><?php esc_html_e( 'Message', 'dserver' ); ?> *</label><textarea id="ds-message" name="ds_message" rows="5" required maxlength="5000"><?php echo $plan ? esc_textarea( sprintf( /* translators: %s: plan */ __( 'I would like to order the %s plan.', 'dserver' ), strtoupper( str_replace( '-', ' ', $plan ) ) ) ) : ''; ?></textarea></p>
		<p class="ds-form__consent"><label><input type="checkbox" name="ds_consent" value="1" required> <?php esc_html_e( 'I agree that DServer Technology may use these details to reply to my enquiry.', 'dserver' ); ?></label></p>
		<p><button type="submit" class="ds-button"><?php esc_html_e( 'Send message', 'dserver' ); ?></button></p>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'dserver_contact', 'dserver_contact_form' );

/**
 * Handle a submission (guests; honeypot, minimum fill time and per-IP rate limit).
 */
function dserver_handle_contact(): void {
	$return = wp_validate_redirect( isset( $_POST['ds_return'] ) ? esc_url_raw( wp_unslash( $_POST['ds_return'] ) ) : home_url( '/' ), home_url( '/' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$go     = static function ( string $state ) use ( $return ) {
		wp_safe_redirect( add_query_arg( 'ds_sent', $state, remove_query_arg( 'ds_sent', $return ) ) . '#ds-contact' );
		exit;
	};
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- public form, cache-safe; protected below.
	$ts = isset( $_POST['ds_ts'] ) ? (int) $_POST['ds_ts'] : 0;
	if ( ! empty( $_POST['ds_website'] ) || time() - $ts < 3 ) {
		$go( '1' ); // Pretend success to bots.
	}
	$key   = 'ds_cf_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	$count = (int) get_transient( $key );
	if ( $count >= 5 ) {
		$go( '0' );
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	$name    = sanitize_text_field( wp_unslash( $_POST['ds_name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['ds_email'] ?? '' ) );
	$company = sanitize_text_field( wp_unslash( $_POST['ds_company'] ?? '' ) );
	$topic   = sanitize_text_field( wp_unslash( $_POST['ds_topic'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['ds_message'] ?? '' ) );
	$consent = ! empty( $_POST['ds_consent'] );
	// phpcs:enable
	if ( ! $name || ! is_email( $email ) || ! $message || ! $consent ) {
		$go( '0' );
	}
	$to   = (string) dserver_opt( 'email' );
	$to   = is_email( $to ) ? $to : (string) get_option( 'admin_email' );
	$body = "Name: {$name}\nEmail: {$email}\nCompany: {$company}\nTopic: {$topic}\n\n{$message}\n";
	$ok   = wp_mail( $to, sprintf( '[%s] %s — %s', get_bloginfo( 'name' ), $topic, $name ), $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );
	$go( $ok ? '1' : '0' );
}
add_action( 'admin_post_nopriv_dserver_contact', 'dserver_handle_contact' );
add_action( 'admin_post_dserver_contact', 'dserver_handle_contact' );
