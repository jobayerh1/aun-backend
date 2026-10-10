<?php
/**
 * Newsletter sign-up.
 *
 * Priority: a newsletter plugin form placed in the "Footer Newsletter" widget
 * area → a newsletter plugin shortcode set in Business Settings → this
 * lightweight built-in form. The built-in form stores addresses privately
 * (Theme Settings → Subscribers) with a CSV export for importing into any
 * email-marketing service. No provider is hard-coded.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Private subscriber list.
 */
add_action(
	'init',
	static function () {
		register_post_type(
			'pall_subscriber',
			array(
				'labels'              => array(
					'name'          => __( 'Subscribers', 'palltheme-core' ),
					'singular_name' => __( 'Subscriber', 'palltheme-core' ),
					'menu_name'     => __( 'Subscribers', 'palltheme-core' ),
					'all_items'     => __( 'Subscribers', 'palltheme-core' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'pallcore',
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title' ),
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
			)
		);
	}
);

/**
 * Form markup (or the configured plugin shortcode).
 */
function pallcore_newsletter_form(): string {
	if ( ! pallcore_setting( 'newsletter_enabled' ) ) {
		return '';
	}
	$shortcode = trim( (string) pallcore_setting( 'newsletter_shortcode' ) );
	if ( $shortcode && preg_match( '/^\[[a-zA-Z0-9_\-]+[^\]]*\]$/', $shortcode ) ) {
		return do_shortcode( $shortcode );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$state  = isset( $_GET['pall_subscribed'] ) ? sanitize_key( wp_unslash( $_GET['pall_subscribed'] ) ) : '';
	$notice = '';
	if ( '1' === $state ) {
		$notice = '<p class="pt-newsletter__msg" role="status">' . esc_html__( 'Thanks — you are subscribed.', 'palltheme-core' ) . '</p>';
	} elseif ( '0' === $state ) {
		$notice = '<p class="pt-newsletter__msg is-error" role="alert">' . esc_html__( 'Please enter a valid email address.', 'palltheme-core' ) . '</p>';
	}
	$privacy = get_privacy_policy_url();
	$text    = (string) pallcore_setting( 'newsletter_text' );
	// Return to the current page. $wp->request is relative to home, so this also works in a sub-folder install.
	global $wp;
	$current = home_url( user_trailingslashit( isset( $wp->request ) ? (string) $wp->request : '' ) );

	return '<form class="pt-newsletter" id="pt-newsletter" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
		. ( $text ? '<p class="pt-newsletter__text">' . esc_html( $text ) . '</p>' : '' )
		. '<input type="hidden" name="action" value="pallcore_subscribe">'
		. '<input type="hidden" name="pall_return" value="' . esc_url( $current ) . '">'
		. '<input type="hidden" name="pall_ts" value="' . esc_attr( (string) time() ) . '">'
		. '<span class="pt-form__hp" aria-hidden="true"><input type="text" name="pall_website" tabindex="-1" autocomplete="off"></span>'
		. '<div class="pt-newsletter__row"><label class="screen-reader-text" for="pt-newsletter-email">' . esc_html__( 'Email address', 'palltheme-core' ) . '</label>'
		. '<input id="pt-newsletter-email" type="email" name="pall_email" required autocomplete="email" placeholder="' . esc_attr__( 'Your work email', 'palltheme-core' ) . '">'
		. '<button type="submit" class="pt-btn pt-btn--accent">' . esc_html__( 'Subscribe', 'palltheme-core' ) . '</button></div>'
		. '<p class="pt-newsletter__note">' . ( $privacy
			/* translators: %s: privacy policy link. */
			? sprintf( wp_kses( __( 'No spam. Unsubscribe anytime. See our <a href="%s">privacy policy</a>.', 'palltheme-core' ), array( 'a' => array( 'href' => true ) ) ), esc_url( $privacy ) )
			: esc_html__( 'No spam. Unsubscribe anytime.', 'palltheme-core' ) ) . '</p>'
		. $notice
		. '</form>';
}

/**
 * Handle a sign-up (guest-friendly; cache-safe like the contact form).
 */
function pallcore_handle_subscribe(): void {
	$return = isset( $_POST['pall_return'] ) ? esc_url_raw( wp_unslash( $_POST['pall_return'] ) ) : home_url( '/' );
	$return = remove_query_arg( 'pall_subscribed', wp_validate_redirect( $return, home_url( '/' ) ) );
	$done   = static function ( string $state ) use ( $return ) {
		wp_safe_redirect( add_query_arg( 'pall_subscribed', $state, $return ) . '#pt-newsletter' );
		exit;
	};

	$ts = isset( $_POST['pall_ts'] ) ? (int) $_POST['pall_ts'] : 0;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public form; protected by honeypot, timing and rate limit.
	if ( ! empty( $_POST['pall_website'] ) || ( time() - $ts ) < 2 ) {
		$done( '1' );
	}
	$ip_key = 'pallcore_nl_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		$done( '0' );
	}
	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$email = isset( $_POST['pall_email'] ) ? sanitize_email( wp_unslash( $_POST['pall_email'] ) ) : '';
	if ( ! is_email( $email ) ) {
		$done( '0' );
	}
	$existing = get_posts(
		array(
			'post_type'      => 'pall_subscriber',
			'post_status'    => 'any',
			'title'          => $email,
			'fields'         => 'ids',
			'posts_per_page' => 1,
		)
	);
	if ( ! $existing ) {
		wp_insert_post(
			array(
				'post_type'   => 'pall_subscriber',
				'post_title'  => $email,
				'post_status' => 'private',
			)
		);
		do_action( 'pallcore_newsletter_subscribed', $email );
	}
	$done( '1' );
}
add_action( 'admin_post_pallcore_subscribe', 'pallcore_handle_subscribe' );
add_action( 'admin_post_nopriv_pallcore_subscribe', 'pallcore_handle_subscribe' );

/**
 * "Export CSV" button on the Subscribers screen.
 */
add_filter(
	'views_edit-pall_subscriber',
	static function ( $views ) {
		$url                 = wp_nonce_url( admin_url( 'admin-post.php?action=pallcore_export_subscribers' ), 'pallcore_export_subscribers' );
		$views['pall_export'] = '<a href="' . esc_url( $url ) . '" class="button" style="margin-inline-start:8px">' . esc_html__( 'Export CSV', 'palltheme-core' ) . '</a>';
		return $views;
	}
);

add_action(
	'admin_post_pallcore_export_subscribers',
	static function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'pallcore_export_subscribers' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'palltheme-core' ) );
		}
		$rows = get_posts( array( 'post_type' => 'pall_subscriber', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'ASC' ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'email', 'subscribed_at' ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, array( $row->post_title, get_the_date( 'c', $row ) ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
);
