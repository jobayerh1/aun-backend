<?php
/**
 * Palltheme bootstrap.
 *
 * The theme holds presentation only (templates, styles, design settings,
 * Elementor/WooCommerce integration). Business data (services, solutions,
 * case studies, contact details, statistics...) lives in the companion
 * plugin "Palltheme Core" so switching themes never loses content.
 *
 * @package Palltheme
 * @author  Engr. Nazim U Ahmed
 */

defined( 'ABSPATH' ) || exit;

define( 'PALLTHEME_VERSION', '1.0.0' );
define( 'PALLTHEME_DIR', get_template_directory() );
define( 'PALLTHEME_URI', get_template_directory_uri() );

$palltheme_includes = array(
	'inc/helpers.php',
	'inc/setup.php',
	'inc/customizer.php',
	'inc/enqueue.php',
	'inc/template-tags.php',
	'inc/menus.php',
	'inc/seo.php',
	'inc/elementor.php',
	'inc/admin.php',
);

foreach ( $palltheme_includes as $palltheme_file ) {
	require_once PALLTHEME_DIR . '/' . $palltheme_file;
}

if ( class_exists( 'WooCommerce' ) ) {
	require_once PALLTHEME_DIR . '/inc/woocommerce.php';
}
