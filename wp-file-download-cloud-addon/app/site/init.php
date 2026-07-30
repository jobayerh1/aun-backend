<?php
/**
 * WP File Download Addon
 *
 * @package WP File Download Addon
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;

add_action('init', 'wpfdAddonInit');

/**
 * Init Addon
 *
 * @return void
 */
function wpfdAddonInit()
{
    $app = Application::getInstance('WpfdAddon');
    load_plugin_textdomain('wpfdAddon', null, $app->getPath(true) . DIRECTORY_SEPARATOR . 'languages');
    $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
    $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
    require_once $path_wpfdhelper;

    $wpfdClassPath = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR;

    require_once $wpfdClassPath . 'WpfdCloudCache.php';
//    require_once $wpfdClassPath . 'WpfdAddonGoogle.php';
//    require_once $wpfdClassPath . 'WpfdAddonDropbox.php';
//    require_once $wpfdClassPath . 'WpfdAddonOneDrive.php';
//    require_once $wpfdClassPath . 'WpfdAddonOneDriveBusiness.php';
    require_once $wpfdClassPath . 'WpfdAddonWoocommerce.php';
    require_once $wpfdClassPath . 'WpfdAddonWatermark.php';
    new WpfdAddonWatermark();
    new WpfdAddonWoocommerce();

    include_once 'google.init.php';
    include_once 'google_team_drive.init.php';
    include_once 'dropbox.init.php';
    include_once 'onedrive.init.php';
    include_once 'onedrive_business.init.php';
    include_once 'aws.init.php';
    include_once 'nextcloud.init.php';
}

add_action('wpfd_file_download', 'wpfdAddonIncreaseHit', 10, 2);
add_filter('wpfd_addon_get_files', 'wpfd_addon_get_files', 10, 3);
/**
 * Get addon files list from category id callback
 *
 * @param integer $categoryId    Category term id
 * @param string  $categoryFrom  Category from
 * @param boolean $list_id_files List id files
 *
 * @return array|boolean
 */
function wpfd_addon_get_files($categoryId, $categoryFrom, $list_id_files = false)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            return wpfdAddonDisplayGoogleDriveCategories($categoryId, $list_id_files);
        case 'googleTeamDrive':
            return wpfdAddonDisplayGoogleTeamDriveCategories($categoryId, $list_id_files);
        case 'dropbox':
            return wpfdAddonDisplayDropboxCategories($categoryId, $list_id_files);
        case 'onedrive':
            return wpfdAddonDisplayOneDriveCategories($categoryId, $list_id_files);
        case 'onedrive_business':
            return wpfdAddonDisplayOneDriveBusinessCategories($categoryId, $list_id_files);
        case 'aws':
            return wpfdAddonDisplayAwsCategories($categoryId, $list_id_files);
        default:
            return false;
    }
}
/**
 * Display google drive category
 *
 * @param integer $termId      Term Id
 * @param boolean $listIdFlies List id files
 *
 * @return boolean|array
 */
function wpfdAddonDisplayGoogleDriveCategories($termId, $listIdFlies = false)
{
    if (WpfdAddonHelper::getGoogleDriveIdByTermId($termId)) {
        Application::getInstance('WpfdAddon');
        $cloudFile = Model::getInstance('cloudfiles');
        return $cloudFile->getListGoogleDriveFiles($termId, $listIdFlies);
    }
    return false;
}

/**
 * Display google team drive category
 *
 * @param integer $termId      Term Id
 * @param boolean $listIdFlies List id files
 *
 * @return boolean|array
 */
function wpfdAddonDisplayGoogleTeamDriveCategories($termId, $listIdFlies = false)
{
    if (WpfdAddonHelper::getGoogleTeamDriveIdByTermId($termId)) {
        Application::getInstance('WpfdAddon');
        $cloudTeamDriveFile = Model::getInstance('cloudteamdrivefiles');
        return $cloudTeamDriveFile->getListGoogleDriveFiles($termId, $listIdFlies);
    }
    return false;
}

/**
 * Get list file from dropbox
 *
 * @param integer $termId      Term id
 * @param boolean $listIdFlies List id files
 *
 * @return array|boolean
 */
function wpfdAddonDisplayDropboxCategories($termId, $listIdFlies = false)
{
    if (WpfdAddonHelper::getDropboxIdByTermId($termId)) {
        Application::getInstance('WpfdAddon');
        $dropFile = Model::getInstance('dropboxfiles');
        return $dropFile->getListDropboxFiles($termId, $listIdFlies);
    }
}
/**
 * Get list file from onedriver
 *
 * @param integer $termId      Term Id
 * @param boolean $listIdFlies List id Files?
 *
 * @return array
 */
function wpfdAddonDisplayOneDriveCategories($termId, $listIdFlies = false)
{
    if (WpfdAddonHelper::getOneDriveIdByTermId($termId)) {
        Application::getInstance('WpfdAddon');
        $onedriveFile = Model::getInstance('onedrivefiles');
        return $onedriveFile->getListOneDriveFiles($termId, $listIdFlies);
    }
}

/**
 * Get level category google drive
 *
 * @param WP_Term $category Term
 *
 * @return WP_Term
 */
function wpfdAddonLevelCategory($category)
{
    $cloud_id = WpfdAddonHelper::getGoogleDriveIdByTermId($category->term_id);
    if ($cloud_id) {
        $category->wp_term_id = $category->term_id;
        $category->wp_parent = $category->parent;
        $category->term_id = $cloud_id;
        $category->parent = WpfdAddonHelper::getGoogleDriveIdByTermId($category->parent);
    }
    return $category;
}

/**
 * Get level category google team drive
 *
 * @param WP_Term $category Term
 *
 * @return WP_Term
 */
function wpfdAddonLevelCategoryGoogleTeamDrive($category)
{
    $cloud_id = WpfdAddonHelper::getGoogleTeamDriveIdByTermId($category->term_id);
    if ($cloud_id) {
        $category->wp_term_id = $category->term_id;
        $category->wp_parent  = $category->parent;
        $category->term_id    = $cloud_id;
        $category->parent     = WpfdAddonHelper::getGoogleTeamDriveIdByTermId($category->parent);
    }
    return $category;
}

/**
 * Get category from
 *
 * @param integer $termId Term id
 *
 * @return boolean|string
 */
function wpfdAddonCategoryFrom($termId)
{
    if (WpfdAddonHelper::getGoogleDriveIdByTermId($termId)) {
        return 'googleDrive';
    } elseif (WpfdAddonHelper::getGoogleTeamDriveIdByTermId($termId)) {
        return 'googleTeamDrive';
    } elseif (WpfdAddonHelper::getDropboxIdByTermId($termId)) {
        return 'dropbox';
    } elseif (WpfdAddonHelper::getOneDriveIdByTermId($termId)) {
        return 'onedrive';
    } elseif (WpfdAddonHelper::getOneDriveBusinessIdByTermId($termId)) {
        return 'onedrive_business';
    } elseif (WpfdAddonHelper::getAwsIdByTermId($termId)) {
        return 'aws';
    } elseif (WpfdAddonHelper::getNextCloudPathByTermId($termId)) {
        return 'nextcloud';
    } else {
        return false;
    }
}

/**
 * Increase Hit for cloud file download
 *
 * @param string $fileId File id
 * @param array  $params Additional data
 *
 * @return void
 */
function wpfdAddonIncreaseHit($fileId, $params)
{
    if (isset($params['catid']) && isset($params['source'])) {
        switch ($params['source']) {
            case 'onedrive':
                $onedriveCate = new WpfdAddonOneDrive();
                $onedriveCate->hits($fileId, $params['catid']);
                break;
            case 'onedrive_business':
                $onedriveCate = new WpfdAddonOneDriveBusiness();
                $onedriveCate->hits($fileId, $params['catid']);
                break;
            case 'aws':
                $aws = new WpfdAddonAws();
                $aws->hits($fileId, $params['catid']);
                break;
            case 'nextcloud':
                $nextcloud = new WpfdAddonNextcloud();
                $nextcloud->hits($fileId, $params['catid']);
                break;
            default:
                break;
        }
    }
}

include_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/Woocommerce/WpfdWCDownloadHandler.php';
WpfdWCDownloadHandler::init();
