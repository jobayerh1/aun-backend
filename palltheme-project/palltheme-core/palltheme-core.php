<?php
/**
 * Plugin Name:       Palltheme Core (Technology Theme Core)
 * Plugin URI:        https://example.com/palltheme
 * Description:       Companion plugin for Palltheme. Adds Services, Solutions, Case Studies, Team, Testimonials, Clients, Technologies, Pricing Plans, FAQs and Job Openings, their custom fields, Business Settings (contact, social, statistics), Elementor widgets, shortcodes, AJAX search, WooCommerce product specifications/downloads/quick view/filters, floating chat buttons, maintenance mode and a one-click demo importer. Your content stays safe if you ever change themes.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Engr. Nazim U Ahmed
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       palltheme-core
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   10.2
 *
 * Copyright (c) 2026 Engr. Nazim U Ahmed. All rights reserved.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

define( 'PALLCORE_VERSION', '1.0.0' );
define( 'PALLCORE_FILE', __FILE__ );
define( 'PALLCORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PALLCORE_URL', plugin_dir_url( __FILE__ ) );

require_once PALLCORE_DIR . 'includes/helpers/functions.php';
require_once PALLCORE_DIR . 'includes/helpers/icons.php';
require_once PALLCORE_DIR . 'includes/post-types/post-types.php';
require_once PALLCORE_DIR . 'includes/taxonomies/taxonomies.php';
require_once PALLCORE_DIR . 'includes/fields/schema.php';
require_once PALLCORE_DIR . 'includes/fields/class-pallcore-meta-boxes.php';
require_once PALLCORE_DIR . 'includes/admin/settings.php';
require_once PALLCORE_DIR . 'includes/admin/admin.php';
require_once PALLCORE_DIR . 'includes/render/cards.php';
require_once PALLCORE_DIR . 'includes/render/sections.php';
require_once PALLCORE_DIR . 'includes/render/sections-extra.php';
require_once PALLCORE_DIR . 'includes/render/shortcodes.php';
require_once PALLCORE_DIR . 'includes/ajax/rest.php';
require_once PALLCORE_DIR . 'includes/frontend/frontend.php';
require_once PALLCORE_DIR . 'includes/frontend/newsletter.php';
require_once PALLCORE_DIR . 'includes/demo/importer.php';

/**
 * Optional integrations.
 */
add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'palltheme-core', false, dirname( plugin_basename( PALLCORE_FILE ) ) . '/languages' );

		if ( class_exists( 'WooCommerce' ) ) {
			require_once PALLCORE_DIR . 'includes/woocommerce/product.php';
			require_once PALLCORE_DIR . 'includes/woocommerce/filters.php';
		}
	}
);

add_action(
	'elementor/init',
	static function () {
		require_once PALLCORE_DIR . 'includes/elementor/elementor.php';
	}
);

/**
 * Declare WooCommerce HPOS + block checkout compatibility.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PALLCORE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', PALLCORE_FILE, true );
		}
	}
);

/**
 * Activation: register types then flush permalinks once.
 */
register_activation_hook(
	__FILE__,
	static function () {
		pallcore_register_post_types();
		pallcore_register_taxonomies();
		flush_rewrite_rules();
	}
);
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
