<?php
/**
 * Plugin Name: AUN Store Core
 * Description: Everything aunstore.com needs on top of Flatsome + WooCommerce: the AUN page blocks,
 *              the Smart Projector Finder, Projector Compare and Screen Size Calculator, product tabs
 *              (Specifications / Screen Size Calculator / Warranty), the designed homepage and footer,
 *              store contact details, and one-click builders for the products (Tools → AUN Product Import)
 *              and the pages and menus (Tools → AUN Site Setup).
 * Version:     1.11.1
 * Author:      AUN
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AUNSTORE_CORE_VERSION', '1.11.1' );
define( 'AUNSTORE_CORE_DIR', plugin_dir_path( __FILE__ ) );

// Store details + shared styles (other modules use aunstore_projectors_url()).
require_once AUNSTORE_CORE_DIR . 'includes/store-details.php';

/*
 * Page blocks and buying tools shared with the AUN Bangladesh site (ported by tools/port-bd-tools.py).
 * If the same module is ALSO active as its own plugin, that copy has already declared the
 * class/function — loading ours would be a fatal "cannot redeclare", so we skip it and say so.
 */
$aunstore_modules = array(
	'includes/section-head.php'          => 'aun_head_shortcode',
	'includes/shorts-showcase.php'       => 'AUN_Shorts_Showcase',
	'includes/smart-adjustments.php'     => 'aun_sa_shortcode',
	'includes/tutorials.php'             => 'aun_tut_shortcode',
	'includes/tools/compare.php'         => 'AUN\\Compare\\Plugin',
	'includes/tools/throw-calculator.php' => 'AUN_Throw_Calculator',
	'includes/tools/projector-wizard.php' => 'AUN_Projector_Wizard',
);
$GLOBALS['aunstore_skipped_modules'] = array();
foreach ( $aunstore_modules as $aunstore_file => $aunstore_symbol ) {
	if ( class_exists( $aunstore_symbol, false ) || function_exists( $aunstore_symbol ) ) {
		$GLOBALS['aunstore_skipped_modules'][] = basename( $aunstore_file, '.php' );
		continue;
	}
	require_once AUNSTORE_CORE_DIR . $aunstore_file;
}
unset( $aunstore_modules, $aunstore_file, $aunstore_symbol );

add_action( 'admin_notices', function () {
	if ( empty( $GLOBALS['aunstore_skipped_modules'] ) || ! current_user_can( 'activate_plugins' ) ) return;
	echo '<div class="notice notice-warning"><p><strong>AUN Store Core:</strong> these modules are also installed as separate plugins, so that copy is being used instead of the built-in one: <code>'
		. esc_html( implode( ', ', $GLOBALS['aunstore_skipped_modules'] ) )
		. '</code>. Deactivate the separate plugins so the built-in (aunstore) versions run.</p></div>';
} );

// Product tabs.
require_once AUNSTORE_CORE_DIR . 'includes/product-tabs.php';

// The designed homepage ([aunstore_home]) and site footer.
require_once AUNSTORE_CORE_DIR . 'includes/design/home.php';
require_once AUNSTORE_CORE_DIR . 'includes/design/pages.php';
require_once AUNSTORE_CORE_DIR . 'includes/design/footer.php';

if ( is_admin() ) {
	require_once AUNSTORE_CORE_DIR . 'includes/importer.php';
	require_once AUNSTORE_CORE_DIR . 'includes/site-setup.php';
}

/**
 * Font Awesome 6 — every AUN block and the product content use `fa-solid fa-…` icons,
 * which Flatsome does not ship. Return false from the filter if another plugin already loads FA6.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! apply_filters( 'aunstore_load_font_awesome', true ) ) return;
	wp_enqueue_style(
		'aunstore-fa6',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
		array(),
		'6.5.2'
	);
} );
