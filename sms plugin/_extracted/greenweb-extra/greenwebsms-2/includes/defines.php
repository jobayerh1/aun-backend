<?php
if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}
define( 'GREENWEB_SMS_PRO_URL', plugin_dir_url( dirname( __FILE__ ) ) );
define( 'GREENWEB_SMS_PRO_DIR', plugin_dir_path( dirname( __FILE__ ) ) );
define( 'GREENWEB_SMS_PRO_VERSION', '30.0.0' );