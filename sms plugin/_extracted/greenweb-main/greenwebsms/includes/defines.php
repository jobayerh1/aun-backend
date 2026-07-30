<?php
if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

// Set Plugin path and url defines.
define( 'GREENWEB_SMS_URL', plugin_dir_url( dirname( __FILE__ ) ) );
define( 'GREENWEB_SMS_DIR', plugin_dir_path( dirname( __FILE__ ) ) );

// Get plugin Data.

function greenweb_main_web(){
	$plugin_data = get_plugin_data( GREENWEB_SMS_DIR . 'wp-sms.php' );
	// Set another useful Plugin defines.

}

add_action( 'init','greenweb_main_web', 15 );
define( 'GREENWEB_SMS_VERSION', '99.0.0' );
define( 'GREENWEB_SMS_ADMIN_URL', get_admin_url() );
define( 'GREENWEB_SMS_SITE', 'https://sms.greenweb.com.bd' );
define( 'GREENWEB_SMS_MOBILE_REGEX', '/^[\+|\(|\)|\d|\- ]*$/' );
define( 'GREENWEB_SMS_CURRENT_DATE', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) );