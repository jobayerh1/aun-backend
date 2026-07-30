<?php
/**
 * WP File Download Addon
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;

// Prohibit direct script loading
defined('ABSPATH') || die('No direct script access allowed!');
register_activation_hook(WPFDA_PLUGIN_FILE, 'wpfdAddon_install');
register_uninstall_hook(WPFDA_PLUGIN_FILE, 'wpfdAddon_uninstall');

/**
 * Activate the addon plugin
 *
 * @return void
 */
function wpfdAddon_install()
{
    add_option('wpfdAddon_version', WPFDA_VERSION);

    // Set permissions for editors and admins so they can do stuff with WPFD
    $app = Application::getInstance('WpfdAddon', WPFDA_PLUGIN_FILE);
    $path_wpfdaddongoogle = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
    $path_wpfdaddongoogle .= DIRECTORY_SEPARATOR . 'WpfdAddonGoogle.php';
    require_once $path_wpfdaddongoogle;

    $pathwpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
    $pathwpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
    require_once $pathwpfdhelper;

    $current_user_id = get_current_user_id();
    $google_config = WpfdAddonHelper::getAllCloudConfigs();
    if ($google_config) {
        $google = new WpfdAddonGoogleDrive();
        $checkAuth = $google->checkAuth();
        if (!$checkAuth) {
            Application::getInstance('WpfdAddon', WPFDA_PLUGIN_FILE);
            $cloudCategory = Model::getInstance('cloudcategory');
            if ($cloudCategory) {
                $cloudCategory->deleteCategoryWhenDisconnect();
            }
            $google_config['googleBaseFolder'] = '';
            $google_config['googleCredentials'] = '';
            WpfdAddonHelper::saveCloudConfigs($google_config);
        }

        // Set google connected by user
        if (!isset($google_config['googleConnectedBy'])) {
            $google_config['googleConnectedBy'] = $current_user_id;
            WpfdAddonHelper::saveCloudConfigs($google_config);
        }
    }

    // Set dropbox connected by user
    $dropbox_config = WpfdAddonHelper::getAllDropboxConfigs();
    if (!isset($dropbox_config['dropboxConnectedBy'])) {
        $dropbox_config['dropboxConnectedBy'] = $current_user_id;
        WpfdAddonHelper::saveDropboxConfigs($dropbox_config);
    }

    // Set onedrive connected by user
    $onedrive_config = WpfdAddonHelper::getAllOneDriveConfigs();
    if (!isset($onedrive_config['onedriveConnectedBy'])) {
        $onedrive_config['onedriveConnectedBy'] = $current_user_id;
        WpfdAddonHelper::saveOneDriveConfigs($onedrive_config);
    }
}

/**
 * Deactivate the addon plugin
 *
 * @return void
 */
function wpfdAddon_uninstall()
{
    delete_option('wpfdAddon_version');
}
