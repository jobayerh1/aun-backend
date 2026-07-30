<?php
/**
 * Plugin Name: SMS Extra Integration
 * Plugin URI: http://www.greenweb.com.bd/
 * Description: This plugin will add woocommerce and others plugins integrational support
 * Version: 30.0.0
 * Author: Greenweb BD
 * Author URI: http://www.greenweb.com.bd/
 * Text Domain: greenwebsms-pro
 */

if (file_exists(dirname(__FILE__).'/greenweb.php')) { 
require_once dirname(__FILE__).'/greenweb.php';
} else {
require_once dirname(__FILE__).'/greenweb_tmp.php';	
}
require_once __DIR__ . '/greenweb_int.php';
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

// Graceful check: if the free Greenweb WP SMS plugin is not active, show a notice instead of crashing
// Uses is_plugin_active() instead of class_exists() because the pro plugin may load before the free plugin
if ( ! in_array( 'greenwebsms/wp-sms.php', apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) && ! ( is_multisite() && in_array( 'greenwebsms/wp-sms.php', array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) ) ) ) {
	add_action( 'admin_notices', function() {
		$class = 'notice notice-warning is-dismissible';
		$message = __( '<strong>SMS Extra Integration</strong> requires <strong>Greenweb WP SMS</strong> to be installed and activated first.', 'greenwebsms-pro' );
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses_post( $message ) );
	} );
	return;
}

/*
 * Load Defines
 */
 require_once 'includes/defines.php';
// Get options
$wpsms_pro_option = get_option( 'wps_pp_settings' );

/*
 * Load Plugin
 */
include_once 'includes/class-greenweb-sms-pro.php';

/*
 * Load Fake Order Blocker (local copy — greenwebsms no longer loads this, see wp-sms.php)
 */
require_once __DIR__ . '/includes/admin/fake-order-blocker/class-greenweb-sms-fake-order-blocker.php';
$fake_blocker = new \WP_SMS\Fake_Order_Blocker();

define( 'GWEB_VERSION', '30.0.0' );
define( 'GWEB_DOMAIN', 'gwebstock' );
define( 'GWEB_FILE_PATH', __FILE__.'/gwebstock/' );
define( 'GWEB_PATH', plugin_dir_path( __FILE__ ).'/gwebstock/' );
define( 'GWEB_URL_PATH', plugin_dir_url( __FILE__ ).'/gwebstock/' );
define( 'GWEB_FONTAWESOME_URL', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/fontawesome.min.css' );
define( 'GWEB_SUPPORT_URL', 'https://otp.li/help' );


require_once( GWEB_PATH . 'includes/gweb-loader.php' );

define("G_WEB_PATH",plugin_dir_path(__FILE__).'/otp/');
define("G_WEB_URL",plugins_url('',__FILE__).'/otp/');
define("G_WEB_PLUGIN_BASENAME",plugin_basename( __FILE__ ));
define("G_WEB_VERSION","30.0.0");
define("G_WEB_LITE",true);

// External Review API Integration
define("GWEB_REVIEW_API_VERSION", "4.0.0");
define("GWEB_REVIEW_API_PATH", plugin_dir_path(__FILE__).'/includes/');


function g_web_init(){
	

	do_action('g_web_before_plugin_activation');

	if ( ! class_exists( 'G_Web' ) ) {
		require G_WEB_PATH.'includes/class-g-web.php';
	}
	
g_web_review_api_init();
	
	g_web();

}

function g_web_review_api_init() {
	// Load External Review API
	if (file_exists(GWEB_REVIEW_API_PATH . 'review_api.php')) {
		require_once GWEB_REVIEW_API_PATH . 'review_api.php';
		new Greenweb_External_Review_API();
	}
}

add_action( 'plugins_loaded','g_web_init', 15 );

function g_web(){
	return G_Web::get_instance();
}


add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'gweblinks');
function gweblinks( $links ) {
	$links[] = '<a href="' .
		admin_url( 'admin.php?page=greenweb-sms-extra' ) .
		'">' . __('Settings') . '</a>';
		$links[] = '<a href="' .
		admin_url( 'admin.php?page=greenweb-sms-extra&tab=wc' ) .
		'">' . __('WooCommerce Settings') . '</a>';
		$links[] = '<a href="' .
		admin_url( 'admin.php?page=gweb-stock-notify&tab=settings' ) .
		'">' . __('Stock Settings') . '</a>';
	return $links;
}
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
    }
} );
// Define shared gateway initialization function with safety guard
// Allows either plugin to define it first without conflict
use WP_SMS\Gateway;
if ( ! function_exists( 'wp_sms_initial_gateway' ) ) {
function wp_sms_initial_gateway() {
	require_once WP_PLUGIN_DIR . '/greenwebsms/includes/class-greenweb-sms-option.php';

	return Gateway::initial();
}
}

new WP_SMS\Pro();