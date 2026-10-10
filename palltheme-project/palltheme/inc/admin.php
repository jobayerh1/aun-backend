<?php
/**
 * Admin: recommended-plugin notice (dismissible, no bundled installer library).
 *
 * @package Palltheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Recommended plugins: slug => [name, class/function/constant that proves it is active, required?].
 *
 * @return array<string,array{0:string,1:string,2:bool}>
 */
function palltheme_recommended_plugins(): array {
	return array(
		'palltheme-core'          => array( 'Palltheme Core (bundled in the theme package)', 'pallcore_setting', true ),
		'elementor'               => array( 'Elementor', 'ELEMENTOR_VERSION', true ),
		'woocommerce'             => array( 'WooCommerce', 'WC_VERSION', true ),
		'seo-by-rank-math'        => array( 'Rank Math SEO (or Yoast SEO)', 'RANK_MATH_VERSION', false ),
		'fluentform'              => array( 'Fluent Forms (or WPForms)', 'FLUENTFORM', false ),
		'yith-woocommerce-wishlist' => array( 'YITH WooCommerce Wishlist', 'YITH_WCWL', false ),
		'yith-woocommerce-compare'  => array( 'YITH WooCommerce Compare', 'YITH_WOOCOMPARE', false ),
		'litespeed-cache'         => array( 'LiteSpeed Cache (or WP Rocket)', 'LSCWP_V', false ),
		'fluent-smtp'             => array( 'FluentSMTP (or WP Mail SMTP)', 'FLUENTMAIL', false ),
		'updraftplus'             => array( 'UpdraftPlus', 'UPDRAFTPLUS_DIR', false ),
		'wordfence'               => array( 'Wordfence (or Solid Security)', 'WORDFENCE_VERSION', false ),
	);
}

/**
 * Is a plugin active according to its marker?
 */
function palltheme_marker_active( string $marker ): bool {
	return defined( $marker ) || function_exists( $marker ) || class_exists( $marker );
}

/**
 * Admin notice listing missing plugins.
 */
function palltheme_plugins_notice(): void {
	if ( ! current_user_can( 'install_plugins' ) || get_user_meta( get_current_user_id(), 'palltheme_dismiss_plugins', true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && in_array( $screen->id, array( 'update', 'plugin-install' ), true ) ) {
		return;
	}

	$missing_required = array();
	$missing_optional = array();
	foreach ( palltheme_recommended_plugins() as $slug => $plugin ) {
		if ( palltheme_marker_active( $plugin[1] ) ) {
			continue;
		}
		$link = 'palltheme-core' === $slug
			? esc_html( $plugin[0] )
			: sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'plugin-install.php?s=' . rawurlencode( $slug ) . '&tab=search&type=term' ) ), esc_html( $plugin[0] ) );
		if ( $plugin[2] ) {
			$missing_required[] = $link;
		} else {
			$missing_optional[] = $link;
		}
	}
	if ( ! $missing_required && ! $missing_optional ) {
		return;
	}
	$dismiss = wp_nonce_url( add_query_arg( 'palltheme_dismiss_plugins', '1' ), 'palltheme_dismiss_plugins' );
	?>
	<div class="notice notice-info">
		<p><strong><?php esc_html_e( 'Palltheme setup', 'palltheme' ); ?></strong></p>
		<?php if ( $missing_required ) : ?>
			<p><?php esc_html_e( 'Required for the complete website:', 'palltheme' ); ?> <?php echo wp_kses_post( implode( ', ', $missing_required ) ); ?></p>
		<?php endif; ?>
		<?php if ( $missing_optional ) : ?>
			<p><?php esc_html_e( 'Recommended:', 'palltheme' ); ?> <?php echo wp_kses_post( implode( ', ', $missing_optional ) ); ?></p>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'palltheme' ); ?></a></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'palltheme_plugins_notice' );

/**
 * Handle notice dismissal.
 */
function palltheme_dismiss_plugins_notice(): void {
	if ( isset( $_GET['palltheme_dismiss_plugins'] ) && check_admin_referer( 'palltheme_dismiss_plugins' ) ) {
		update_user_meta( get_current_user_id(), 'palltheme_dismiss_plugins', 1 );
		wp_safe_redirect( remove_query_arg( array( 'palltheme_dismiss_plugins', '_wpnonce' ) ) );
		exit;
	}
}
add_action( 'admin_init', 'palltheme_dismiss_plugins_notice' );
