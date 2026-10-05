<?php
/**
 * Plugin Name: Breo BD Store
 * Description: The Breo Bangladesh storefront: a Breo-style header and mega menu, a full-screen homepage and product pages, policy pages, and a one-click importer for the launch products. All media is self-hosted.
 * Version: 2.18.0
 * Author: Breo Bangladesh
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Text Domain: breo-bd
 */

defined( 'ABSPATH' ) || exit;

define( 'BREO_BD_VERSION', '2.18.0' );
define( 'BREO_BD_URL', plugin_dir_url( __FILE__ ) );
define( 'BREO_BD_DIR', plugin_dir_path( __FILE__ ) );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	require BREO_BD_DIR . 'includes/helpers.php';
	require BREO_BD_DIR . 'includes/layout.php';
	require BREO_BD_DIR . 'includes/render-product.php';
	require BREO_BD_DIR . 'includes/render-home.php';
	require BREO_BD_DIR . 'includes/render-pages.php';
	require BREO_BD_DIR . 'includes/emails.php';
	require BREO_BD_DIR . 'includes/seo.php';
	require BREO_BD_DIR . 'includes/manuals.php';
	require BREO_BD_DIR . 'includes/maps.php';
	require BREO_BD_DIR . 'includes/videos.php';
	require BREO_BD_DIR . 'includes/tracking.php';
	require BREO_BD_DIR . 'includes/analytics.php';
	if ( is_admin() ) {
		require BREO_BD_DIR . 'includes/admin.php';
	}
} );
