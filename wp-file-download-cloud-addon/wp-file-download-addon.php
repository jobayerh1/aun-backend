<?php
/*
* Plugin Name: WP File Download Addon
* Plugin URI: http://www.joomunited.com/wordpress-products/wp-file-download-addon
* Description: WP File Download Addon: connect Dropbox, Google Drive and add a social locker.
* Author: Joomunited
* Version: 4.8.7
* Text Domain: wpfdAddon
* Domain Path: /app/languages
* Author URI: http://www.joomunited.com
* Update URI: https://www.dloadme.com/repo/plugins/joomunited/wp-file-download-addon/update-checker.json
*/

// Prohibit direct script loading
defined('ABSPATH') || die('No direct script access allowed!');

/********** START CODE **********/
if ( ! defined( 'JOOMUNITED_WPFD_GPLKEY' ) ) {
	//define( 'JOOMUNITED_WPFD_GPLKEY', strrev('179ac400fe318e595954e83c54419954') );
	define( 'JOOMUNITED_WPFD_GPLKEY', 'wpfd_premium_' . md5(get_option('siteurl') . 'wpfd_enabled') );
}
if(empty(get_option('ju_user_token'))){
	update_option('ju_user_token', JOOMUNITED_WPFD_GPLKEY);
}
/********** END CODE **********/

/**
 * Define WP File Download Cloud Addons current version
 */
define('WPFDA_VERSION', '4.8.7');
if (!defined('WPFDA_PLUGIN_DIR_PATH')) {
    define('WPFDA_PLUGIN_DIR_PATH', trailingslashit(realpath(dirname(__FILE__))));
}

if (!defined('WPFDA_PLUGIN_DIR_URL')) {
    define('WPFDA_PLUGIN_DIR_URL', plugin_dir_url(__FILE__));
}
// Check plugin requirements
if (version_compare(PHP_VERSION, '5.6', '<')) {
    if (!function_exists('wpfdAddonDisablePlugin')) {
        /**
         * Deactivate plugin
         *
         * @return void
         */
        function wpfdAddonDisablePlugin()
        {
            if (current_user_can('activate_plugins') && is_plugin_active(plugin_basename(__FILE__))) {
                deactivate_plugins(__FILE__);
                unset($_GET['activate']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This ok
            }
        }
    }

    if (!function_exists('wpfdAddonShowError')) {
        /**
         * Show notice
         *
         * @return void
         */
        function wpfdAddonShowError()
        {
            echo '<div class="error"><p>';
            echo '<strong>WP File Download Cloud Addon</strong>';
            echo ' needs at least PHP 5.6 version, please update php before installing the plugin.</p></div>';
        }
    }

    // Add actions
    add_action('admin_init', 'wpfdAddonDisablePlugin');
    add_action('admin_notices', 'wpfdAddonShowError');

    // Do not load anything more
    return;
}

/* Load Updater */
if (is_admin()) {
    // config section
    if (!defined('JU_BASE')) {
        define('JU_BASE', 'https://www.joomunited.com/');
    }
    $remote_updateinfo = JU_BASE . 'juupdater_files/wp-file-download-addon.json';
    // End config
    require 'juupdater/juupdater.php';
    $UpdateChecker = Jufactory::buildUpdateChecker(
        'https://www.dloadme.com/repo/plugins/joomunited/wp-file-download-addon/update-checker.json',
        __FILE__
    );

    require_once 'cloud-connector/CloudConnector.php';
    $cloud_automatic = call_user_func(
        '\Joomunited\Cloud\WPFD\CloudConnector::getInstance',
        __FILE__,
        'wpfd',
        'WP File Download',
        'wpfdAddon'
    );
    $cloud_automatic->init();
}

// Plugin auto update
if (!function_exists('wpfdAddonPluginCheckForUpdates')) {
    /**
     * Plugin check for updates
     *
     * @param object $update      Update
     * @param array  $plugin_data Plugin data
     * @param string $plugin_file Plugin file
     *
     * @return array|boolean|object
     */
    function wpfdAddonPluginCheckForUpdates($update, $plugin_data, $plugin_file)
    {
        if ($plugin_file !== 'wp-file-download-cloud-addon/wp-file-download-addon.php') {
            return $update;
        }

        if (empty($plugin_data['UpdateURI']) || !empty($update)) {
            return $update;
        }

        $response = wp_remote_get($plugin_data['UpdateURI']);

        if (is_wp_error($response) || empty($response['body'])) {
            return $update;
        }

        $custom_plugins_data = json_decode($response['body'], true);

        $package = null;
        $token = get_site_option('wpfd_license_token');
        if (!empty($token)) {
            $package = $custom_plugins_data['download_url'] . '&token=' . $token . '&siteurl=' . get_option('siteurl');
        }

        return array(
            'version' => $custom_plugins_data['version'],
            'package' => $package
        );
    }
    add_filter('update_plugins_www.joomunited.com', 'wpfdAddonPluginCheckForUpdates', 10, 3);
}

/**
 * Get addon path for requirement check
 *
 * @return string
 */
function wpfdCloudAddons_getPath()
{
    if (!function_exists('plugin_basename')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    return plugin_basename(__FILE__);
}

/**
 * Implement translate for addon
 *
 * @param array $addons Addons
 *
 * @return array
 */
function wpfdAddonTranslate($addons)
{
    $addon                          = new StdClass();
    $addon->main_plugin_file        = __FILE__;
    $addon->extension_name          = 'WP File Download Addon';
    $addon->extension_slug          = 'wpfd-addon';
    $addon->text_domain             = 'wpfdAddon';
    $path_wpfdaddon_en_us           = plugin_dir_path(__FILE__) . 'app' . DIRECTORY_SEPARATOR . 'languages';
    $path_wpfdaddon_en_us           .= DIRECTORY_SEPARATOR . 'wpfdAddon-en_US.mo';
    $addon->language_file           = $path_wpfdaddon_en_us;
    $addons[$addon->extension_slug] = $addon;

    return $addons;
}
// JUtranslation
add_filter('wpfd_get_addons', 'wpfdAddonTranslate');

if (!defined('WPFDA_PLUGIN_FILE')) {
    define('WPFDA_PLUGIN_FILE', __FILE__);
}
if (!defined('WPFDA_PLUGIN_URL')) {
    define('WPFDA_PLUGIN_URL', plugin_dir_url(__FILE__));
}
// Load Framework and install scripts
include_once('framework' . DIRECTORY_SEPARATOR . 'ju-libraries.php');
include_once('app' . DIRECTORY_SEPARATOR . 'autoload.php');
include_once('app' . DIRECTORY_SEPARATOR . 'install.php');
/**
 * Run this addon
 *
 * @return void
 */
function wpfdCloudAddonsInit()
{
    add_action('init', function () {
        if (!class_exists('\Joomunited\WPFileDownloadCloudAddons\JUCheckRequirements')) {
            include_once('app/requirements.php');
        }

        if (class_exists('\Joomunited\WPFileDownloadCloudAddons\JUCheckRequirements')) {
            // Plugins name for translate
            $args           = array(
                'plugin_name'       => esc_html__('WP File Download Addon', 'wpfdAddon'),
                'plugin_path'       => wpfdCloudAddons_getPath(),
                'plugin_textdomain' => 'wpfdAddon',
                'plugin_version'    => WPFDA_VERSION,
                'requirements'      => array(
                    'plugins'     => array(
                        array(
                            'name' => 'WP File Download',
                            'path' => 'wp-file-download/wp-file-download.php',
                            'requireVersion' => '6.0'
                        )
                    ),
                    'php_version' => '7.4',
                    'php_modules' => array(
                        'xml' => 'error'
                    )
                ),
            );

            $wpfdAddonCheck = call_user_func('\Joomunited\WPFileDownloadCloudAddons\JUCheckRequirements::init', $args);
            if (!$wpfdAddonCheck['success']) {
                // Do not load anything more
                unset($_GET['activate']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This ok

                return;
            }
        }
    });

    // Facebook App Id for social locker
    define('WPFDA_FACEBOOK_API', '946797582092490');

    // Load Plugin Files
    include_once('app' . DIRECTORY_SEPARATOR . 'functions.php');
    include_once(
        'app' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'social' . DIRECTORY_SEPARATOR . 'assets.php'
    );
    include_once(
        'app' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'social' . DIRECTORY_SEPARATOR . 'factory.php'
    );
    include_once(
        'app' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'social' . DIRECTORY_SEPARATOR . 'factoryshortcode.php'
    );
    include_once(
        'app' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'social' . DIRECTORY_SEPARATOR . 'shortcode.php'
    );
    include_once(
        'app' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'social' . DIRECTORY_SEPARATOR . 'manager.php'
    );
    include_once(
        'app' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'social' . DIRECTORY_SEPARATOR . 'init.php'
    );

    // Initialise the application
    $app = call_user_func('Joomunited\WPFramework\v1_0_6\Application::getInstance', 'WpfdAddon', WPFDA_PLUGIN_FILE);
    $app->init();

    add_action('init', function () {
        // Register schedules
        wpfda_register_schedules();

        /**
         * Using Background task runner for synchronization
         *
         * @since 4.5.0
         */
        if (class_exists('\Joomunited\BackgroundTasks\WpfdTasks')) {
            new \Joomunited\BackgroundTasks\WpfdTasks;
        }
    });
}
