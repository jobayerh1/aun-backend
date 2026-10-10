<?php
/**
 * Front-end features: assets, built-in contact form, floating chat
 * buttons, maintenance mode, recently-viewed tracking hook.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Small front-end script (map click-to-load + form niceties).
 * The styles for plugin markup ship with the theme; this file adds only
 * the floating-chat styles so they work with any theme.
 */
function pallcore_frontend_assets(): void {
	wp_enqueue_script( 'pallcore-front', PALLCORE_URL . 'assets/js/front.js', array(), PALLCORE_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	if ( pallcore_chat_buttons() ) {
		wp_enqueue_style( 'pallcore-chat', PALLCORE_URL . 'assets/css/chat.css', array(), PALLCORE_VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'pallcore_frontend_assets' );

/* ======================================================================
 * Built-in contact form (used when no form plugin shortcode is set)
 * ==================================================================== */

/**
 * Render the form.
 */
function pallcore_builtin_form(): string {
	$status = '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$sent = isset( $_GET['pall_sent'] ) ? sanitize_key( wp_unslash( $_GET['pall_sent'] ) ) : '';
	if ( '1' === $sent ) {
		$status = '<p class="pt-notice pt-notice--success" role="status">' . esc_html( (string) pallcore_setting( 'form_success' ) ) . '</p>';
	} elseif ( '0' === $sent ) {
		$status = '<p class="pt-notice pt-notice--error" role="alert">' . esc_html__( 'Sorry, your message could not be sent. Please check the fields and try again, or email us directly.', 'palltheme-core' ) . '</p>';
	} elseif ( 'limit' === $sent ) {
		$email  = (string) pallcore_setting( 'email' );
		$phone  = (string) pallcore_setting( 'phone' );
		$status = '<p class="pt-notice pt-notice--error" role="alert">' . esc_html__( 'You have sent several messages in a short time, so this one was not sent. Please try again in an hour, or contact us directly:', 'palltheme-core' )
			. ( $email ? ' <a href="mailto:' . esc_attr( antispambot( $email ) ) . '">' . esc_html( antispambot( $email ) ) . '</a>' : '' )
			. ( $phone ? ' · <a href="tel:' . esc_attr( pallcore_phone_digits( $phone ) ) . '">' . esc_html( $phone ) . '</a>' : '' ) . '</p>';
	}

	$privacy = '';
	if ( pallcore_setting( 'form_privacy' ) ) {
		$policy  = get_privacy_policy_url();
		$label   = $policy
			/* translators: %s: privacy policy link. */
			? sprintf( __( 'I agree to the %s.', 'palltheme-core' ), '<a href="' . esc_url( $policy ) . '" target="_blank">' . esc_html__( 'privacy policy', 'palltheme-core' ) . '</a>' )
			: esc_html__( 'I agree that my details are used to answer my enquiry.', 'palltheme-core' );
		$privacy = '<label style="display:flex;gap:10px;align-items:flex-start;font-weight:400"><input type="checkbox" name="pall_consent" value="1" required style="width:auto;min-height:0;margin-top:5px"> <span>' . wp_kses( $label, array( 'a' => array( 'href' => true, 'target' => true ) ) ) . '</span></label>';
	}

	$services = get_posts(
		array(
			'post_type'      => 'pall_service',
			'posts_per_page' => 30,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$options = '<option value="">' . esc_html__( 'General enquiry', 'palltheme-core' ) . '</option>';
	foreach ( $services as $sid ) {
		$options .= '<option>' . esc_html( get_the_title( $sid ) ) . '</option>';
	}

	ob_start();
	?>
	<?php echo $status; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
	<form class="pt-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="pallcore_contact">
		<input type="hidden" name="pall_return" value="<?php echo esc_url( get_permalink() ?: home_url( '/' ) ); ?>">
		<?php wp_nonce_field( 'pallcore_contact', 'pallcore_contact_nonce' ); ?>
		<div class="pt-form__hp" aria-hidden="true"><label>Website <input type="text" name="pall_website" tabindex="-1" autocomplete="off"></label></div>
		<input type="hidden" name="pall_ts" value="<?php echo esc_attr( (string) time() ); ?>">
		<div class="pt-form__row">
			<p><label for="pall-name"><?php esc_html_e( 'Full name', 'palltheme-core' ); ?> *</label><input id="pall-name" type="text" name="pall_name" required autocomplete="name" maxlength="120"></p>
			<p><label for="pall-company"><?php esc_html_e( 'Company', 'palltheme-core' ); ?></label><input id="pall-company" type="text" name="pall_company" autocomplete="organization" maxlength="120"></p>
		</div>
		<div class="pt-form__row">
			<p><label for="pall-email"><?php esc_html_e( 'Work email', 'palltheme-core' ); ?> *</label><input id="pall-email" type="email" name="pall_email" required autocomplete="email" maxlength="160"></p>
			<p><label for="pall-phone"><?php esc_html_e( 'Phone', 'palltheme-core' ); ?></label><input id="pall-phone" type="tel" name="pall_phone" autocomplete="tel" maxlength="40"></p>
		</div>
		<p><label for="pall-topic"><?php esc_html_e( 'I’m interested in', 'palltheme-core' ); ?></label><select id="pall-topic" name="pall_topic"><?php echo $options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></p>
		<p><label for="pall-message"><?php esc_html_e( 'How can we help?', 'palltheme-core' ); ?> *</label><textarea id="pall-message" name="pall_message" required maxlength="5000"></textarea></p>
		<?php echo $privacy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<p><button type="submit" class="pt-btn pt-btn--primary"><?php esc_html_e( 'Send message', 'palltheme-core' ); ?></button></p>
	</form>
	<?php
	return (string) ob_get_clean();
}

/**
 * Handle form submission (admin-post, logged in or not).
 */
function pallcore_handle_contact(): void {
	$return = isset( $_POST['pall_return'] ) ? esc_url_raw( wp_unslash( $_POST['pall_return'] ) ) : home_url( '/' );
	$return = wp_validate_redirect( $return, home_url( '/' ) );
	$fail   = add_query_arg( 'pall_sent', '0', $return ) . '#pall-name';

	// Nonces are enforced for logged-in users. For visitors, page caching can
	// serve an expired nonce (a "random" failure), so guests are protected by
	// the honeypot, minimum fill time and per-IP rate limit below instead.
	$nonce = isset( $_POST['pallcore_contact_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pallcore_contact_nonce'] ) ) : '';
	if ( is_user_logged_in() && ! wp_verify_nonce( $nonce, 'pallcore_contact' ) ) {
		wp_safe_redirect( $fail );
		exit;
	}

	// Spam guards: honeypot + minimum fill time + simple rate limit per IP.
	$ts = isset( $_POST['pall_ts'] ) ? (int) $_POST['pall_ts'] : 0;
	if ( ! empty( $_POST['pall_website'] ) || ( time() - $ts ) < 3 ) {
		wp_safe_redirect( add_query_arg( 'pall_sent', '1', $return ) ); // Pretend success to bots.
		exit;
	}
	$ip_key = 'pallcore_cf_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		wp_safe_redirect( add_query_arg( 'pall_sent', 'limit', $return ) . '#pall-name' );
		exit;
	}
	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

	$name    = isset( $_POST['pall_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pall_name'] ) ) : '';
	$email   = isset( $_POST['pall_email'] ) ? sanitize_email( wp_unslash( $_POST['pall_email'] ) ) : '';
	$company = isset( $_POST['pall_company'] ) ? sanitize_text_field( wp_unslash( $_POST['pall_company'] ) ) : '';
	$phone   = isset( $_POST['pall_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['pall_phone'] ) ) : '';
	$topic   = isset( $_POST['pall_topic'] ) ? sanitize_text_field( wp_unslash( $_POST['pall_topic'] ) ) : '';
	$message = isset( $_POST['pall_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pall_message'] ) ) : '';

	if ( ! $name || ! is_email( $email ) || ! $message || ( pallcore_setting( 'form_privacy' ) && empty( $_POST['pall_consent'] ) ) ) {
		wp_safe_redirect( $fail );
		exit;
	}

	$to = (string) pallcore_setting( 'form_recipient' ) ?: ( (string) pallcore_setting( 'email' ) ?: get_option( 'admin_email' ) );
	/* translators: 1: site name, 2: sender name. */
	$subject = sprintf( __( '[%1$s] New enquiry from %2$s', 'palltheme-core' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $name );
	$body    = implode(
		"\n",
		array(
			__( 'Name', 'palltheme-core' ) . ': ' . $name,
			__( 'Company', 'palltheme-core' ) . ': ' . $company,
			__( 'Email', 'palltheme-core' ) . ': ' . $email,
			__( 'Phone', 'palltheme-core' ) . ': ' . $phone,
			__( 'Topic', 'palltheme-core' ) . ': ' . $topic,
			'',
			$message,
			'',
			'— ' . esc_url_raw( $return ),
		)
	);
	$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );

	$ok = wp_mail( $to, $subject, $body, $headers );
	do_action( 'pallcore_contact_submitted', compact( 'name', 'email', 'company', 'phone', 'topic', 'message' ), $ok );

	wp_safe_redirect( add_query_arg( 'pall_sent', $ok ? '1' : '0', $return ) . '#pall-name' );
	exit;
}
add_action( 'admin_post_pallcore_contact', 'pallcore_handle_contact' );
add_action( 'admin_post_nopriv_pallcore_contact', 'pallcore_handle_contact' );

/* ======================================================================
 * Floating chat buttons
 * ==================================================================== */

/**
 * Enabled chat buttons: [network, url, label].
 *
 * @return array<int,array{0:string,1:string,2:string}>
 */
function pallcore_chat_buttons(): array {
	$buttons = array();
	$wa      = (string) pallcore_setting( 'whatsapp' );
	if ( pallcore_setting( 'chat_whatsapp' ) && $wa ) {
		$buttons[] = array( 'whatsapp', 'https://wa.me/' . pallcore_phone_digits( $wa, false ) . '?text=' . rawurlencode( (string) pallcore_setting( 'chat_message' ) ), 'WhatsApp' );
	}
	$tg = (string) pallcore_setting( 'telegram' );
	if ( pallcore_setting( 'chat_telegram' ) && $tg ) {
		$buttons[] = array( 'telegram', 'https://t.me/' . rawurlencode( ltrim( $tg, '@' ) ), 'Telegram' );
	}
	$ms = (string) pallcore_setting( 'messenger' );
	if ( pallcore_setting( 'chat_messenger' ) && $ms ) {
		$buttons[] = array( 'messenger', 'https://m.me/' . rawurlencode( $ms ), 'Messenger' );
	}
	$phone = (string) pallcore_setting( 'phone' );
	if ( pallcore_setting( 'chat_phone' ) && $phone ) {
		$buttons[] = array( 'phone', 'tel:' . pallcore_phone_digits( $phone ), __( 'Call', 'palltheme-core' ) );
	}
	$live = (string) pallcore_setting( 'chat_live_url' );
	if ( $live ) {
		$buttons[] = array( 'live', $live, __( 'Live chat', 'palltheme-core' ) );
	}
	return $buttons;
}

/**
 * Output the floating buttons.
 */
function pallcore_render_chat_buttons(): void {
	$buttons = pallcore_chat_buttons();
	if ( ! $buttons || is_admin() ) {
		return;
	}
	$icons = array(
		'whatsapp'  => '<path fill="currentColor" stroke="none" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.8 14.2c-.2.7-1.4 1.3-2 1.4-.5.1-1.2.1-1.9-.1-.4-.1-1-.3-1.7-.6-3-1.3-5-4.3-5.1-4.5-.2-.2-1.2-1.6-1.2-3s.8-2.2 1-2.5c.3-.3.6-.4.8-.4h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .5l-.3.5-.4.5c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.7-.1l2 1c.3.1.5.2.5.3.1.2.1.8-.2 1.5Z"/>',
		'telegram'  => '<path fill="currentColor" stroke="none" d="m21.5 3.6-3.2 15.6c-.2 1-.9 1.3-1.8.8l-4.8-3.6-2.3 2.2c-.3.3-.5.5-1 .5l.3-5 9.1-8.2c.4-.4-.1-.6-.6-.2L6 12.7l-4.8-1.5c-1-.3-1.1-1 .2-1.6L20 2.5c.9-.3 1.7.2 1.5 1.1Z"/>',
		'messenger' => '<path fill="currentColor" stroke="none" d="M12 2C6.4 2 2 6.1 2 11.6c0 2.9 1.2 5.4 3.2 7.1V22l3-1.7c1.2.3 2.5.5 3.8.5 5.6 0 10-4.1 10-9.6S17.6 2 12 2Zm1 12.9-2.6-2.7-5 2.7 5.5-5.8 2.6 2.7 4.9-2.7-5.4 5.8Z"/>',
		'phone'     => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
		'live'      => '<path d="M21 12a8 8 0 0 1-11.7 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/>',
	);
	$label = (string) pallcore_setting( 'chat_label' );
	echo '<div class="pt-float-chat" role="complementary" aria-label="' . esc_attr( $label ?: __( 'Contact options', 'palltheme-core' ) ) . '">';
	foreach ( $buttons as $btn ) {
		printf(
			'<a class="pt-float-chat__btn pt-float-chat__btn--%1$s" href="%2$s" %3$s aria-label="%4$s"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%5$s</svg><span class="pt-float-chat__tip">%4$s</span></a>',
			esc_attr( $btn[0] ),
			esc_url( $btn[1], array( 'https', 'http', 'tel' ) ),
			'phone' === $btn[0] ? '' : 'target="_blank" rel="noopener"',
			esc_attr( $btn[2] ),
			$icons[ $btn[0] ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		);
	}
	echo '</div>';
}
add_action( 'wp_footer', 'pallcore_render_chat_buttons', 20 );
add_filter( 'body_class', static fn( $c ) => pallcore_chat_buttons() ? array_merge( $c, array( 'has-float-chat' ) ) : $c );

/* ======================================================================
 * Maintenance mode
 * ==================================================================== */

/**
 * Serve the maintenance page to visitors.
 */
function pallcore_maintenance(): void {
	if ( ! pallcore_setting( 'maint_enabled' ) || current_user_can( 'edit_theme_options' ) || is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
	if ( str_contains( $path, 'wp-login.php' ) || str_contains( $path, 'wp-admin' ) ) {
		return;
	}
	status_header( 503 );
	header( 'Retry-After: 3600' );
	nocache_headers();
	include PALLCORE_DIR . 'templates/maintenance.php';
	exit;
}
add_action( 'template_redirect', 'pallcore_maintenance', 0 );

/* ======================================================================
 * Admin bar reminder when maintenance mode is on
 * ==================================================================== */
add_action(
	'admin_bar_menu',
	static function ( WP_Admin_Bar $bar ) {
		if ( pallcore_setting( 'maint_enabled' ) && current_user_can( 'manage_options' ) ) {
			$bar->add_node(
				array(
					'id'    => 'pallcore-maint',
					'title' => '<span style="color:#f59e0b">● ' . esc_html__( 'Maintenance mode ON', 'palltheme-core' ) . '</span>',
					'href'  => admin_url( 'admin.php?page=pallcore-settings&tab=maintenance' ),
				)
			);
		}
	},
	100
);
