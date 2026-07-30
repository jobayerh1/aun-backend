<?php
/**
 * WP File Download Addon
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Utilities;
use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Form;

defined('ABSPATH') || die();

/**
 * Config page
 */
// New ux
add_filter('wpfd_admin_ui_configuration_menu_get_items', function ($items) {
    $items['cloud-config'] = array(
        esc_html__('Cloud connection', 'wpfdAddon'),
        '',
        51,
        plugins_url('assets/images/icon-cloud-config.svg', __FILE__)
    );
    $items['social-locker'] = array(
        esc_html__('Social locker', 'wpfdAddon'),
        '',
        52,
        plugins_url('assets/images/icon-social-locker.svg', __FILE__)
    );

    return $items;
});

// Add cloud config content
add_action('wpfd_admin_ui_configuration_content', function () {
    $name = 'cloud-config';
//    $html = '<h2>' . esc_html__('Cloud connection', 'wpfdAddon') . '</h2>';
    $html = '';
    ob_start();
    displayGoogleDriveConfigurationContent();
    $googleHtml = ob_get_contents();
    ob_end_clean();

    ob_start();
    displayGoogleTeamDriveConfigurationContent();
    $googleTeamDriveHtml = ob_get_contents();
    ob_end_clean();

    ob_start();
    displayDropboxConfigurationContent();
    $dropboxHtml = ob_get_contents();
    ob_end_clean();

    ob_start();
    displayOneDriveConfigurationContent();
    $onedriveHtml = ob_get_contents();
    ob_end_clean();
    $tabs = array(
        'google_drive' => $googleHtml,
        'google_team_drive' => $googleTeamDriveHtml,
        'dropbox' => $dropboxHtml,
        'onedrive_personal' => $onedriveHtml,
    );
    $tabs = apply_filters('wpfda_after_cloud_configuration_content', $tabs);
    $tabs = apply_filters('wpfda_aws_configuration_content', $tabs);
    $tabs = apply_filters('wpfda_nextcloud_configuration_content', $tabs);
    //$tabs = apply_filters('wpfda_google_team_drive_configuration_content', $tabs);
    $html .= wpfd_admin_ui_configuration_build_tabs($tabs);
//    ob_start();
//    displayGoogleDriveConfigurationContent();
//    displayDropboxConfigurationContent();
//    displayOneDriveConfigurationContent();
//    do_action('wpfda_after_cloud_configuration_content');
//    $html .= ob_get_contents();
//    ob_end_clean();

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- print html content
    echo wpfd_admin_ui_configuration_build_content($name, $html);

    // Social locker
    $name = 'social-locker';
    $html = '<h2>' . esc_html__('Social locker', 'wpfdAddon') . '</h2>';
    ob_start();
    wpfd_addon_display_social_content();
    $html .= ob_get_contents();
    ob_end_clean();

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- print html content
    echo wpfd_admin_ui_configuration_build_content($name, $html);
}, 20);

// TODO: DELETE THIS LATE
add_action('wpfd_addon_configuration_tab', 'wpfd_addon_display_cloud_configuration_tab');
add_action('wpfd_addon_configuration_content', 'displayGoogleDriveConfigurationContent', 10);
add_action('wpfd_addon_configuration_content', 'displayDropboxConfigurationContent', 20);
add_action('wpfd_addon_configuration_content', 'displayOneDriveConfigurationContent', 30);

add_action('wpfdAddonAfterTap', 'wpfd_addon_display_social_tab');
add_action('wpfdAddonAfterContent', 'wpfd_addon_display_social_content');
// END DELETE THIS

/**
 * Cloud global hooks
 */

add_action('admin_menu', 'wpfdAddon_menu', 100);
add_action('admin_init', 'wpfdAddonInit');
add_filter('wpfdAddonCategoryFrom', 'wpfdAddonCategoryFrom', 10, 1);
add_filter('wpfdAddon_check_cloud_exist', 'check_cloud_exist');
add_action('wp_ajax_add_synchronize', 'wpfd_addon_add_synchronize');
add_action('wp_ajax_nopriv_add_synchronize', 'wpfd_addon_add_synchronize');

/**
 * Google Drive hooks
 */
add_filter('wpfd_addon_google_drive_connected', 'wpfda_google_drive_connected');
add_action('wpfd_addon_dropdown', 'displayDropDownCreateCloud', 10);

add_action('wp_ajax_googleSync', 'wpfdAddonGoogleSync_callback');
add_action('wp_ajax_nopriv_googleSync', 'wpfdAddonGoogleSync_callback');
// Push notifications ajax
add_action('wp_ajax_googleWatchChanges', 'wpfdAddonGoogleWatchChanges_callback');
add_action('wp_ajax_nopriv_googleWatchChanges', 'wpfdAddonGoogleWatchChanges_callback');

add_action('wp_ajax_googlePushListener', 'wpfdAddonGooglePushListener_callback');
add_action('wp_ajax_nopriv_googlePushListener', 'wpfdAddonGooglePushListener_callback');

add_action('wp_ajax_wpfdAddonDeleteCategory', 'wpfdAddonDeleteCategory_callback');
add_action('wp_ajax_wpfdAddonAddCategory', 'wpfdAddonAddCategory_callBack');
add_action('wp_ajax_wpfdAddonDuplicateCategory', 'wpfdAddonDuplicateCategory_callBack');

add_action('wpfd_addon_auto_sync', 'wpfdAddonGoogleDriveSync', 10);

add_filter('wpfdAddonGoogleDriveUpload', 'wpfdAddonGoogleDriveUpload', 10, 2);
add_filter('wpfdAddonGoogleDriveChangeOrder', 'wpfdAddonGoogleDriveChangeOrder', 10, 1);
add_filter('wpfdAddonDownloadGoogleDriveInfo', 'wpfdAddonDownloadGoogleDriveInfo', 10, 2);
add_filter('wpfdAddonDownloadGoogleDriveData', 'wpfdAddonDownloadGoogleDriveData', 10, 2);

add_action('wpfdAddonCopyGoogleGoogle', 'wpfdAddonCopyGoogleGoogle', 10, 2);
add_action('wpfdAddonMoveGoogleGoogle', 'wpfdAddonMoveGoogleGoogle', 10, 2);

add_filter('wpfdAddonGetListVersions', 'wpfdAddonGetListVersions', 10, 2);
add_filter('wpfdAddonUploadVersion', 'wpfdAddonUploadVersion', 10, 2);
add_filter('wpfdAddonDeleteVersion', 'wpfdAddonDeleteVersion', 10, 3);
add_filter('wpfd_level_category', 'wpfdAddonLevelCategory', 9, 1);

/**
 * Google Team Drive hooks
 */
add_filter('wpfd_addon_google_team_drive_connected', 'wpfd_addon_google_team_drive_connected');
add_action('wpfd_addon_dropdown', 'displayDropDownCreateCloudTeamDrive', 10);
add_action('wp_ajax_wpfdAddonAddGoogleTeamDriveCategory', 'wpfdAddonAddGoogleTeamDriveCategory_callBack');
add_action('wp_ajax_wpfdAddonDuplicateGoogleTeamDriveCategory', 'wpfdAddonDuplicateGoogleTeamDriveCategory_callBack');
add_action('wp_ajax_wpfdAddonDeleteGoogleTeamDriveCategory', 'wpfdAddonDeleteGoogleTeamDriveCategory_callback');
add_action('wpfdAddonMoveGoogleTeamDrives', 'wpfdAddonMoveGoogleTeamDrives', 10, 2);
add_action('wpfdAddonCopyGoogleTeamDrives', 'wpfdAddonCopyGoogleTeamDrives', 10, 2);

add_filter('wpfdAddonGoogleTeamDriveChangeOrder', 'wpfdAddonGoogleTeamDriveChangeOrder', 10, 1);
add_filter('wpfdAddonGoogleTeamDriveGetListVersions', 'wpfdAddonGoogleTeamDriveGetListVersions', 10, 2);
add_filter('wpfdAddonUploadGoogleTeamDriveVersion', 'wpfdAddonUploadGoogleTeamDriveVersion', 10, 2);
add_filter('wpfdAddonGoogleTeamDriveDeleteVersion', 'wpfdAddonGoogleTeamDriveDeleteVersion', 10, 3);
add_filter('wpfdAddonDownloadGoogleTeamDriveInfo', 'wpfdAddonDownloadGoogleTeamDriveInfo', 10, 2);
add_filter('wpfd_level_category_google_team_drive', 'wpfdAddonLevelCategoryGoogleTeamDrive', 9, 1);

add_action('wp_ajax_googleTeamDriveSync', 'wpfdAddonGoogleTeamDriveSync_callback');
add_action('wp_ajax_nopriv_googleTeamDriveSync', 'wpfdAddonGoogleTeamDriveSync_callback');
add_action('wpfd_addon_auto_sync', 'wpfdAddonGoogleTeamDriveAutoSync', 20);

/**
 * Testing support
 */
if (defined('WPFD_TESTING') && WPFD_TESTING) {
    add_action('wp_ajax_wpfdGoogleClearAll', 'wpfdAddonGoogleDeleteAll_callback');
    add_action('wp_ajax_wpfdOneDriveClearAll', 'wpfdAddonOnedriveDeleteAll_callback');
    add_action('wp_ajax_wpfdOneDriveBusinessClearAll', 'wpfdAddonOnedriveBusinessDeleteAll_callback');
    add_action('wp_ajax_wpfdDropboxClearAll', 'wpfdAddonDropboxDeleteAll_callback');
}

/**
 * Dropbox hooks
 */
add_filter('wpfd_addon_dropbox_connected', 'wpfda_dropbox_connected');
add_action('admin_menu', 'wpfdDropboxAddon_menu'); // Not in use
add_action('wpfd_addon_dropdown', 'displayDropDownCreateDropbox', 20);
add_action('wp_ajax_dropboxSync', 'wpfdAddonDropboxSync_callback');
add_action('wp_ajax_nopriv_dropboxSync', 'wpfdAddonDropboxSync_callback');
add_action('wp_ajax_wpfdAddonDeleteDropboxCategory', 'wpfdAddonDeleteDropboxCategory_callback');
add_action('wp_ajax_wpfdAddonDuplicateDropboxCategory', 'wpfdAddonDuplicateDropboxCategory_callBack');
add_action('wp_ajax_wpfdAddonAddDropCategory', 'wpfdAddonAddDropCategory_callBack');

add_action('wpfd_addon_auto_sync', 'wpfdAddonDropboxAutoSync', 30);

// Dropbox auto sync handle callback
if (defined('WPFD_DROPBOX_AUTO_SYNC') && WPFD_DROPBOX_AUTO_SYNC) {
    wpfdAddonDropboxAutoSync();
}

add_filter('wpfdAddonDropboxUpload', 'wpfdAddonDropboxUpload', 10, 2);
add_filter('wpfdAddonDropboxChangeOrder', 'wpfdAddonDropboxChangeOrder', 10, 2);
add_filter('wpfdAddonDownloadDropboxInfo', 'wpfdAddonDownloadDropboxInfo', 10, 1);

add_action('wpfdAddonCopyDropboxDropbox', 'wpfdAddonCopyDropboxDropbox', 10, 2);
add_action('wpfdAddonMoveDropboxDropbox', 'wpfdAddonMoveDropboxDropbox', 10, 2);

add_filter('wpfdAddonUploadDropboxVersion', 'wpfdAddonUploadDropboxVersion', 10, 2);
add_filter('wpfdAddonDropboxVersionInfo', 'wpfdAddonDropboxVersionInfo', 10, 2);
add_filter('wpfd_level_category_dropbox', 'wpfdAddonLevelCategoryDropbox', 9, 1);

// Dropbox push notifications
add_action('wp_ajax_dropboxWatchChanges', 'wpfdAddonDropboxWatchChanges_callback');
add_action('wp_ajax_nopriv_dropboxWatchChanges', 'wpfdAddonDropboxWatchChanges_callback');
add_action('wp_ajax_dropboxPushListener', 'wpfdAddonDropboxPushListener_callback');
add_action('wp_ajax_nopriv_dropboxPushListener', 'wpfdAddonDropboxPushListener_callback');

/**
 * Onedrive hooks
 */
add_filter('wpfd_addon_onedrive_connected', 'wpfda_onedrive_connected');
add_action('admin_menu', 'wpfdOneDriveAddon_menu');  // Not in use
add_action('wpfd_addon_dropdown', 'displayDropDownCreateOneDrive', 30);
add_action('wp_ajax_wpfdAddonAddOneDriveCategory', 'wpfdAddonAddOneDriveCategory_callBack');
add_action('wp_ajax_wpfdAddonDeleteOneDriveCategory', 'wpfdAddonDeleteOneDriveCategory_callback');
add_action('wp_ajax_wpfdAddonDuplicateOneDriveCategory', 'wpfdAddonDuplicateOneDriveCategory_callBack');
add_action('wp_ajax_onedriveSync', 'wpfdAddonOneDriveSync_callback');
add_action('wp_ajax_nopriv_onedriveSync', 'wpfdAddonOneDriveSync_callback');
add_action('wpfd_addon_auto_sync', 'wpfdAddonOneDriveSync', 40);
add_filter('wpfdAddonOneDriveUpload', 'wpfdAddonOneDriveUpload', 10, 2);
add_filter('wpfdAddonOneDriveChangeOrder', 'wpfdAddonOneDriveChangeOrder', 10, 1);
add_filter('wpfdAddonDownloadOneDriveInfo', 'wpfdAddonDownloadOneDriveInfo', 10, 1);
add_action('wpfdAddonCopyOneDrive', 'wpfdAddonCopyOneDrive', 10, 2);
add_action('wpfdAddonMoveFileOneDriver', 'wpfdAddonMoveFileOneDriver', 10, 2);
add_filter('wpfdAddonUploadOneDriveVersion', 'wpfdAddonUploadOneDriveVersion', 10, 2);
add_filter('wpfd_level_category_onedrive', 'wpfdAddonLevelCategoryOneDrive', 9, 1);
add_filter('wpfdAddonOneDriveListVersions', 'wpfdAddonOneDriveGetListVersions', 10, 2);
add_action('admin_init', function () {
    $state = Utilities::getInput('state', 'GET', 'string');
    if ($state === 'wpfd-onedrive') {
        $onedrive = new WpfdAddonOneDrive();
        $onedrive->authenticate();
    }
});

// Improve Hooks
/**
 * Single folder synchronize callback
 *
 * @return void
 */
function wpfd_addon_add_synchronize()
{
    $type = Utilities::getInput('type', 'POST', 'string');

    if (!in_array($type, wpfd_get_support_cloud())) {
        wp_send_json_error(false);
        die();
    }
    $wpfdTasks = new \Joomunited\BackgroundTasks\WpfdTasks;

    switch ($type) {
        case 'googleDrive':
            $wpfdTasks->addGoogleDriveSync();
            break;
        case 'dropbox':
            $wpfdTasks->addDropBoxSync();
            break;
        case 'onedrive':
            $wpfdTasks->addOneDriveSync();
            break;
        case 'onedrive_business':
            $wpfdTasks->addOneDriveBusinessSync();
            break;
        case 'aws':
            $wpfdTasks->addAwsSync();
            break;
        case 'nextcloud':
            $wpfdTasks->addNextcloudSync();
            break;
    }

    wp_send_json_success(true);
}

add_filter('wpfd_addon_restore_version', 'wpfd_addon_restore_version', 10, 3);
/**
 * Restore version
 *
 * @param string $id_file      File id
 * @param string $vid          Version id
 * @param string $categoryFrom Category from
 *
 * @return boolean true on success
 *                 false on error
 */
function wpfd_addon_restore_version($id_file, $vid, $categoryFrom)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            Application::getInstance('WpfdAddon');
            $google = Model::getInstance('cloudfiles');
            return $google->updateRevision($id_file, $vid);
        case 'dropbox':
            Application::getInstance('WpfdAddon');
            $dropFile = Model::getInstance('dropboxfiles');
            return $dropFile->restoreVersion($id_file, $vid);
        case 'onedrive':
            Application::getInstance('WpfdAddon');
            $onedrive = Model::getInstance('onedrivefiles');

            return $onedrive->restoreVersion($id_file, $vid);
        case 'onedrive_business':
            Application::getInstance('WpfdAddon');
            $onedriveBusiness = Model::getInstance('onedrivebusinessfiles');

            return $onedriveBusiness->restoreVersion($id_file, $vid);
        case 'aws':
            Application::getInstance('WpfdAddon');
            $aws = Model::getInstance('awsfiles');

            return $aws->restoreVersion($id_file, $vid);
        case 'nextcloud':
            Application::getInstance('WpfdAddon');
            $nextcloud = Model::getInstance('nextcloudfiles');

            return $nextcloud->restoreVersion($id_file, $vid);
        default:
            return false;
    }
}
add_filter('wpfd_addon_download_version', 'wpfd_addon_download_version', 10, 3);
/**
 * Download version
 *
 * @param string $id_file      File id
 * @param string $vid          Version id
 * @param string $categoryFrom Category from
 *
 * @return boolean false on error
 *                 download file on success
 */
function wpfd_addon_download_version($id_file, $vid, $categoryFrom)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            Application::getInstance('WpfdAddon');
            $google = Model::getInstance('cloudfiles');
            return $google->downloadRevision($id_file, $vid);
        case 'dropbox':
            Application::getInstance('WpfdAddon');
            $dropFile = Model::getInstance('dropboxfiles');
            return $dropFile->downloadVersion($id_file, $vid);
        case 'onedrive':
            // todo: made download version for onedrive too
        default:
            return false;
    }
}

add_filter('wpfd_addon_save_file_info', 'wpfd_addon_save_file_info', 10, 3);
/**
 * Save addon file info callback
 *
 * @param array          $data         File data
 * @param string|boolean $categoryFrom Category from
 * @param integer        $categoryId   Category term id
 *
 * @return boolean|string|array
 */
function wpfd_addon_save_file_info($data, $categoryFrom, $categoryId)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            return wpfdAddonSaveFileInfo($data);
        case 'googleTeamDrive':
            return wpfdAddonSaveGoogleTeamDriveFileInfo($data);
        case 'dropbox':
            return wpfdAddonSaveDropboxFileInfo($data, $categoryId);
        case 'onedrive':
            return wpfdAddonSaveOneDriveFileInfo($data, $categoryId);
        case 'onedrive_business':
            return wpfdAddonSaveOneDriveBusinessFileInfo($data, $categoryId);
        case 'aws':
            return wpfdAddonSaveAwsFileInfo($data, $categoryId);
        case 'nextcloud':
            return wpfdAddonSaveNextcloudFileInfo($data, $categoryId);
        default:
            return false;
    }
}

add_filter('wpfd_addon_set_file_multi_categories', 'wpfd_addon_set_file_multi_categories', 10, 4);

/**
 * Set file multi categories
 *
 * @param string|integer $id_file             File id
 * @param string|array   $file_multi_category Category id list
 * @param string         $categoryFrom        Category type
 * @param string|integer $idCategory          Category id
 *
 * @return boolean|string|void
 */
function wpfd_addon_set_file_multi_categories($id_file, $file_multi_category, $categoryFrom, $idCategory)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            return wpfdAddonSetFileMultiCategories($id_file, $file_multi_category, $idCategory);
        case 'googleTeamDrive':
            return wpfdAddonSetGoogleTeamDriveFileMultiCategories($id_file, $file_multi_category, $idCategory);
        case 'dropbox':
            return wpfdAddonSetDropboxFileMultiCategories($id_file, $file_multi_category, $idCategory);
        case 'onedrive':
            return wpfdAddonSetOneDriveFileMultiCategories($id_file, $file_multi_category, $idCategory);
        case 'onedrive_business':
            return wpfdAddonSetOneDriveBusinessFileMultiCategories($id_file, $file_multi_category, $idCategory);
        case 'aws':
            return wpfdAddonSetAwsFileMultiCategories($id_file, $file_multi_category, $idCategory);
        case 'nextcloud':
            return wpfdAddonSetNextcloudFileMultiCategories($id_file, $file_multi_category, $idCategory);
        default:
            return false;
    }
}

/**
 * Save google file multi categories info
 *
 * @param integer|string $id_file             File id
 * @param array          $file_multi_category File multi categories
 * @param integer        $idCategory          Category term id
 *
 * @return boolean|string|mixed
 */
function wpfdAddonSetFileMultiCategories($id_file, $file_multi_category, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $cloudFile = Model::getInstance('cloudfiles');
    $result = array(
        'success' => false,
        'file_multi_category_old' => array()
    );

    if (!isset($id_file) || (int) $id_file === 0 || $id_file === '') {
        return false;
    }

    $googleFileInfo = $cloudFile->getFileInfo($id_file, $idCategory);
    $fileMultiCategoryOld = isset($googleFileInfo['file_multi_category_old']) ? explode(',', $googleFileInfo['file_multi_category_old']) : array();
    $googleFileInfo['file_multi_category'] = $file_multi_category;
    $googleFileInfo['file_multi_category_old'] = implode(',', $file_multi_category);

    if ($cloudFile->saveFileInfo($googleFileInfo)) {
        $result['success'] = true;
        $result['file_multi_category_old'] = $fileMultiCategoryOld;
    }

    return $result;
}

/**
 * Save google team drive file multiple categories info
 *
 * @param integer|string $id_file             File id
 * @param array          $file_multi_category File multi categories
 * @param integer        $idCategory          Category term id
 *
 * @return boolean|string|mixed
 */
function wpfdAddonSetGoogleTeamDriveFileMultiCategories($id_file, $file_multi_category, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $cloudTeamDriveFile = Model::getInstance('cloudteamdrivefiles');
    $result = array(
        'success'                 => false,
        'file_multi_category_old' => array()
    );

    if (!isset($id_file) || (int) $id_file === 0 || $id_file === '') {
        return false;
    }

    $googleTeamDriveFileInfo = $cloudTeamDriveFile->getFileInfo($id_file, $idCategory);
    $fileMultiCategoryOld = isset($googleTeamDriveFileInfo['file_multi_category_old']) ? explode(',', $googleTeamDriveFileInfo['file_multi_category_old']) : array();
    $googleTeamDriveFileInfo['file_multi_category'] = $file_multi_category;
    $googleTeamDriveFileInfo['file_multi_category_old'] = implode(',', $file_multi_category);

    if ($cloudTeamDriveFile->saveFileInfo($googleTeamDriveFileInfo)) {
        $result['success']                 = true;
        $result['file_multi_category_old'] = $fileMultiCategoryOld;
    }

    return $result;
}

/**
 * Save dropbox file multi categories info
 *
 * @param integer|string $id_file             File id
 * @param array          $file_multi_category File multi categories
 * @param integer        $idCategory          Category term id
 *
 * @return boolean|string|mixed
 */
function wpfdAddonSetDropboxFileMultiCategories($id_file, $file_multi_category, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $dropboxFile = Model::getInstance('dropboxfiles');

    $result = array(
        'success' => false,
        'file_multi_category_old' => array()
    );

    if (!isset($id_file)) {
        return false;
    }

    $dropboxFileInfo = WpfdAddonHelper::getDropboxFileInfo($id_file, $idCategory);
    $fileMultiCategoryOld = isset($dropboxFileInfo['file_multi_category_old']) ? explode(',', $dropboxFileInfo['file_multi_category_old']) : array();
    $dropboxFileInfo['file_multi_category'] = $file_multi_category;
    $dropboxFileInfo['file_multi_category_old'] = implode(',', $file_multi_category);

    if ($dropboxFile->saveDropboxFileInfo($dropboxFileInfo, $idCategory)) {
        $result['success'] = true;
        $result['file_multi_category_old'] = $fileMultiCategoryOld;
    }

    return $result;
}

/**
 * Save onedrive file multi categories info
 *
 * @param integer|string $id_file             File id
 * @param array          $file_multi_category File multi categories
 * @param integer        $idCategory          Category term id
 *
 * @return boolean|string|mixed
 */
function wpfdAddonSetOneDriveFileMultiCategories($id_file, $file_multi_category, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $onedriveFile = Model::getInstance('onedrivefiles');
    $result = array(
        'success' => false,
        'file_multi_category_old' => array()
    );

    if (!isset($id_file)) {
        return false;
    }

    $onedriveFileInfo = $onedriveFile->getOneDriveFileInfo($id_file, $idCategory);
    $fileMultiCategoryOld = isset($onedriveFileInfo['file_multi_category_old']) ? explode(',', $onedriveFileInfo['file_multi_category_old']) : array();
    $onedriveFileInfo['file_multi_category'] = $file_multi_category;
    $onedriveFileInfo['file_multi_category_old'] = implode(',', $file_multi_category);

    if ($onedriveFile->saveOneDriveFileInfo($onedriveFileInfo, $idCategory)) {
        $result['success'] = true;
        $result['file_multi_category_old'] = $fileMultiCategoryOld;
    }

    return $result;
}

/**
 * Save onedrive business file multi categories info
 *
 * @param integer|string $id_file             File id
 * @param array          $file_multi_category File multi categories
 * @param integer        $idCategory          Category term id
 *
 * @return boolean|string|mixed
 */
function wpfdAddonSetOneDriveBusinessFileMultiCategories($id_file, $file_multi_category, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $onedriveBusiness = Model::getInstance('onedrivebusinessfiles');
    $result = array(
        'success' => false,
        'file_multi_category_old' => array()
    );

    if (!isset($id_file)) {
        return false;
    }

    $onedriveBusinessInfo = $onedriveBusiness->getOneDriveBusinessFileInfo($id_file, $idCategory);
    $fileMultiCategoryOld = isset($onedriveBusinessInfo['file_multi_category_old']) ? explode(',', $onedriveBusinessInfo['file_multi_category_old']) : array();
    $onedriveBusinessInfo['file_multi_category'] = $file_multi_category;
    $onedriveBusinessInfo['file_multi_category_old'] = implode(',', $file_multi_category);

    if ($onedriveBusiness->saveOneDriveBusinessFileInfo($onedriveBusinessInfo, $idCategory)) {
        $result['success'] = true;
        $result['file_multi_category_old'] = $fileMultiCategoryOld;
    }

    return $result;
}

add_filter('wpfd_addon_get_file_info', 'wpfd_addon_get_file_info', 10, 3);
/**
 * Get cloud file info callback
 *
 * @param string  $fileId       File id
 * @param integer $categoryId   Category term id
 * @param string  $categoryFrom Category from
 *
 * @return array|boolean
 */
function wpfd_addon_get_file_info($fileId, $categoryId, $categoryFrom)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            return wpfdAddonGetFileInfo($fileId, $categoryId);
        case 'googleTeamDrive':
            return wpfdAddonGoogleTeamDriveGetFileInfo($fileId, $categoryId);
        case 'dropbox':
            return wpfdAddonDropboxGetFileInfo($fileId, $categoryId);
        case 'onedrive':
            return wpfdAddonOneDriveGetFileInfo($fileId, $categoryId);
        case 'onedrive_business':
            return wpfdAddonOneDriveBusinessGetFileInfo($fileId, $categoryId);
        case 'aws':
            return wpfdAddonAwsGetFileInfo($fileId, $categoryId);
        case 'nextcloud':
            return wpfdAddonNextcloudGetFileInfo($fileId, $categoryId);
        default:
            return false;
    }
}

add_action('wpfd_update_category_name', 'wpfd_addon_update_category_name', 10, 2);
/**
 * Update addon category name callback
 *
 * @param integer $categoryId Category term id
 * @param string  $name       New category name
 *
 * @return void
 */
function wpfd_addon_update_category_name($categoryId, $name)
{
    $categoryFrom = wpfdAddonCategoryFrom($categoryId);
    switch ($categoryFrom) {
        case 'googleDrive':
            wpfdAddonSetCategoryGoogleDriveTitle($categoryId, $name);
            break;
        case 'googleTeamDrive':
            wpfdAddonSetCategoryGoogleTeamDriveTitle($categoryId, $name);
            break;
        case 'dropbox':
            wpfdAddonSetCategoryDropboxTitle($categoryId, $name);
            break;
        case 'onedrive':
            wpfdAddonSetCategoryOneDriveTitle($categoryId, $name);
            break;
        case 'onedrive_business':
            wpfdAddonSetCategoryOneDriveBusinessTitle($categoryId, $name);
            break;
        case 'aws':
            wpfdAddonSetCategoryAwsTitle($categoryId, $name);
            break;
        case 'nextcloud':
            wpfdAddonSetCategoryNextcloudTitle($categoryId, $name);
            break;
        default:
            break;
    }
}

add_filter('wpfd_addon_delete_file', 'wpfd_addon_delete_file', 10, 3);
/**
 * Delete addon file callback
 *
 * @param integer $categoryId   Category term id
 * @param string  $fileId       Cloud file id
 * @param string  $categoryFrom Category from
 *
 * @return boolean
 */
function wpfd_addon_delete_file($categoryId, $fileId, $categoryFrom)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            return wpfdAddonDeleteGoogleCloudFiles($categoryId, $fileId);
        case 'googleTeamDrive':
            return wpfdAddonDeleteGoogleTeamDriveFiles($categoryId, $fileId);
        case 'dropbox':
            return wpfdAddonDeleteDropboxFiles($categoryId, $fileId);
        case 'onedrive':
            return wpfdAddonDeleteOneDriveFiles($categoryId, $fileId);
        case 'onedrive_business':
            return wpfdAddonDeleteOneDriveBusinessFiles($categoryId, $fileId);
        case 'aws':
            return wpfdAddonDeleteAwsFiles($categoryId, $fileId);
        case 'nextcloud':
            return wpfdAddonDeleteNextcloudFiles($categoryId, $fileId);
        default:
            return false;
    }
}

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
function wpfd_addon_get_files($categoryId, $categoryFrom, $list_id_files)
{
    if (!isset($list_id_files)) {
        $list_id_files = false;
    }

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
        case 'nextcloud':
            return wpfdAddonDisplayNextcloudCategories($categoryId, $list_id_files);
        default:
            return false;
    }
}

add_filter('wpfd_addon_pending_upload_files', 'wpfd_addon_pending_upload_files', 10, 2);
/**
 * Set pending for upload files
 *
 * @param integer $categoryId   Category term id
 * @param string  $categoryFrom Category from
 *
 * @throws Exception                    Fire if errors
 *
 * @return void
 */
function wpfd_addon_pending_upload_files($categoryId, $categoryFrom)
{
    $pendingFiles   = get_option('_wpfdAddon_upload_pending_files') ? get_option('_wpfdAddon_upload_pending_files') : array();
    switch ($categoryFrom) {
        case 'googleDrive':
            wpfdAddonGooglePendingUploadFiles($categoryId, $pendingFiles);
            break;
        case 'dropbox':
            wpfdAddonDropboxPendingUploadFiles($categoryId, $pendingFiles);
            break;
        case 'onedrive':
            wpfdAddonOnedrivePendingUploadFiles($categoryId, $pendingFiles);
            break;
        case 'onedrive_business':
            wpfdAddonOnedriveBusinessPendingUploadFiles($categoryId, $pendingFiles);
            break;
        case 'aws':
            wpfdAddonAwsPendingUploadFiles($categoryId, $pendingFiles);
            break;
        case 'nextcloud':
            wpfdAddonNextcloudPendingUploadFiles($categoryId, $pendingFiles);
            break;
        default:
            break;
    }
}

add_action('wpfd_addon_upload_file', 'wpfd_addon_upload_file', 10, 4);
/**
 * Upload file to cloud callback
 *
 * @param array   $item         File object
 * @param string  $file_current File name
 * @param integer $id_category  Category term id
 * @param string  $categoryFrom Category from
 *
 * @throws Exception            Fire message if errors
 *
 * @return void
 */
function wpfd_addon_upload_file($item, $file_current, $id_category, $categoryFrom)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            wpfdAddonUploadGoogleFile($item, $file_current, $id_category);
            break;
        case 'googleTeamDrive':
            wpfdAddonUploadGoogleTeamDriveFile($item, $file_current, $id_category);
            break;
        case 'dropbox':
            wpfdAddonUploadDropboxFile($item, $file_current, $id_category);
            break;
        case 'onedrive':
            wpfdAddonUploadOneDriveFile($item, $file_current, $id_category);
            break;
        case 'onedrive_business':
            wpfdAddonUploadOneDriveBusinessFile($item, $file_current, $id_category);
            break;
        case 'aws':
            wpfdAddonUploadAwsFile($item, $file_current, $id_category);
            break;
        case 'nextcloud':
            wpfdAddonUploadNextcloudFile($item, $file_current, $id_category);
            break;
        default:
            break;
    }
}

add_action('wpfd_addon_upload_folders_and_files', 'wpfd_addon_upload_folders_and_files', 10, 2);
/**
 * Upload folders and files to cloud callback
 *
 * @param integer $id_category  Category term id
 * @param string  $categoryFrom Category from
 *
 * @throws Exception            Fire message if errors
 *
 * @return void
 */
function wpfd_addon_upload_folders_and_files($id_category, $categoryFrom)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            wpfdAddonUploadGoogleFoldersAndFiles($id_category);
            break;
        case 'dropbox':
            wpfdAddonUploadDropboxFoldersAndFiles($id_category);
            break;
        case 'onedrive':
            wpfdAddonUploadOneDriveFoldersAndFiles($id_category);
            break;
        case 'onedrive_business':
            wpfdAddonUploadOneDriveBusinessFoldersAndFiles($id_category);
            break;
        case 'aws':
            wpfdAddonUploadAwsFoldersAndFiles($id_category);
            break;
        default:
            break;
    }
}

add_action('wpfd_addon_publish_file', 'wpfd_addon_publish_file', 10, 3);

/**
 * Publish file to cloud callback
 *
 * @param array   $fileIds      File id list
 * @param integer $categoryId   Category term id
 * @param string  $categoryFrom Category from
 *
 * @throws Exception            Fire if errors
 *
 * @return void
 */
function wpfd_addon_publish_file($fileIds, $categoryId, $categoryFrom)
{
    if (empty($fileIds)) {
        return;
    }

    if (!$categoryId) {
        return;
    }

    switch ($categoryFrom) {
        case 'googleDrive':
            wpfdAddonGoolePublishFile($fileIds, $categoryId);
            break;
        case 'googleTeamDrive':
            wpfdAddonGoogleTeamDrivePublishFile($fileIds, $categoryId);
            break;
        case 'dropbox':
            wpfdAddonDropboxPublishFile($fileIds, $categoryId);
            break;
        case 'onedrive':
            wpfdAddonOnedrivePublishFile($fileIds, $categoryId);
            break;
        case 'onedrive_business':
            wpfdAddonOnedriveBusinessPublishFile($fileIds, $categoryId);
            break;
        case 'aws':
            wpfdAddonAwsPublishFile($fileIds, $categoryId);
            break;
        case 'nextcloud':
            wpfdAddonNextcloudPublishFile($fileIds, $categoryId);
            break;
        default:
            break;
    }
}

add_action('wpfd_addon_unpublish_file', 'wpfd_addon_unpublish_file', 10, 3);

/**
 * Unpublish file to cloud callback
 *
 * @param array   $fileIds      File id list
 * @param integer $categoryId   Category term id
 * @param string  $categoryFrom Category from
 *
 * @throws Exception            Fire if errors
 *
 * @return void
 */
function wpfd_addon_unpublish_file($fileIds, $categoryId, $categoryFrom)
{
    if (!$fileIds || empty($fileIds)) {
        return;
    }
    if (!$categoryId) {
        return;
    }

    switch ($categoryFrom) {
        case 'googleDrive':
            wpfdAddonGoogleUnpublishFile($fileIds, $categoryId);
            break;
        case 'googleTeamDrive':
            wpfdAddonGoogleTeamDriveUnpublishedFile($fileIds, $categoryId);
            break;
        case 'dropbox':
            wpfdAddonDropboxUnpublishFile($fileIds, $categoryId);
            break;
        case 'onedrive':
            wpfdAddonOnedriveUnpublishFile($fileIds, $categoryId);
            break;
        case 'onedrive_business':
            wpfdAddonOnedriveBusinessUnpublishFile($fileIds, $categoryId);
            break;
        case 'aws':
            wpfdAddonAwsUnpublishFile($fileIds, $categoryId);
            break;
        case 'nextcloud':
            wpfdAddonNextcloudUnpublishFile($fileIds, $categoryId);
            break;
        default:
            break;
    }
}

add_action('wpfd_addon_update_version_description', 'wpfd_addon_update_version_description', 10, 4);

/**
 * Update version and description on copy
 *
 * @param string  $version      Version
 * @param string  $description  Description
 * @param string  $categoryFrom Category from
 * @param integer $categoryId   Category term id
 *
 * @return void
 */
function wpfd_addon_update_version_description($version, $description, $categoryFrom, $categoryId)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            wpfdAddonGoogleUpdateVersionDescription($version, $description);
            break;
        case 'googleTeamDrive':
            wpfdAddonGoogleTeamDriveUpdateVersionDescription($version, $description);
            break;
        case 'dropbox':
            wpfdAddonDropboxUpdateVersionDescription($version, $description, $categoryId);
            break;
        case 'onedrive':
            wpfdAddonOnedriveUpdateVersionDescription($version, $description, $categoryId);
            break;
        case 'onedrive_business':
            wpfdAddonOnedriveBusinessUpdateVersionDescription($version, $description, $categoryId);
            break;
        case 'aws':
            wpfdAddonAwsUpdateVersionDescription($version, $description, $categoryId);
            break;
        default:
            break;
    }
}

add_filter('wpfd_addon_add_category', 'wpfd_addon_add_category', 10, 6);
/**
 * Create cloud folder
 *
 * @param string|integer $createdId    Category created id
 * @param string|mixed   $categoryFrom Category type
 * @param string|mixed   $categoryName Category name
 * @param integer|string $parentTermId Parent term id
 * @param integer|string $position     Category position
 * @param boolean|mixed  $duplicate    Enable duplicate
 *
 * @return mixed
 */
function wpfd_addon_add_category($createdId, $categoryFrom, $categoryName, $parentTermId, $position, $duplicate)
{
    $newCategoryCreated = false;
    switch ($categoryFrom) {
        case 'googleDrive':
            Application::getInstance('WpfdAddon');
            $categoryModel = Model::getInstance('cloudcategory');

            $createdId = $categoryModel->addCategory($categoryName, $parentTermId, $position, $duplicate);
            $newCategoryCreated = true;
            break;
        case 'googleTeamDrive':
            Application::getInstance('WpfdAddon');
            $categoryModel      = Model::getInstance('cloudteamdrivecategory');
            $createdId          = $categoryModel->addCategory($categoryName, $parentTermId, $position, $duplicate);
            $newCategoryCreated = true;
            break;
        case 'dropbox':
            Application::getInstance('WpfdAddon');
            $categoryModel = Model::getInstance('dropboxcategory');

            $createdId = $categoryModel->addDropCategory($categoryName, $parentTermId, $position, $duplicate);
            $newCategoryCreated = true;
            break;
        case 'onedrive':
            Application::getInstance('WpfdAddon');
            $categoryModel = Model::getInstance('onedrivecategory');

            $createdId = $categoryModel->addOneDriveCategory($categoryName, $parentTermId, $position, $duplicate);
            $newCategoryCreated = true;
            break;
        case 'onedrive_business':
            Application::getInstance('WpfdAddon');
            $categoryModel = Model::getInstance('onedrivebusinesscategory');

            $createdId = $categoryModel->addCategory($categoryName, $parentTermId, $position, $duplicate);
            $newCategoryCreated = true;
            break;
        case 'aws':
            Application::getInstance('WpfdAddon');
            $categoryModel = Model::getInstance('awscategory');

            $createdId = $categoryModel->addAwsCategory($categoryName, $parentTermId, $position, $duplicate);
            $newCategoryCreated = true;
            break;
        case 'nextcloud':
            Application::getInstance('WpfdAddon');
            $categoryModel = Model::getInstance('nextcloudcategory');

            $createdId = $categoryModel->addNextcloudCategory($categoryName, $parentTermId, $position, $duplicate);
            $newCategoryCreated = true;
            break;
        default:
            break;
    }

    if ($newCategoryCreated) {
        assign_user_categories($createdId);
    }

    return $createdId;
}
/**
 * WpfdAddon Init
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
    $path_wpfda_class = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdCloudCache.php';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonGoogle.php';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonGoogleTeamDrive.php';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonDropbox.php';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonOneDrive.php';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonOneDriveBusiness.php';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonOneDriveBusinessAdmin.php';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonAws.php';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonAwsAdmin.php';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonNextcloud.php';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonWoocommerce.php';
    require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonWatermark.php';
    new WpfdAddonWoocommerce();
    new WpfdAddonWatermark();
    new WpfdAddonOneDriveBusinessAdmin();
    new WpfdAddonAwsAdmin();
    new WpfdAddonNextcloud();
    add_action('admin_enqueue_scripts', 'wpfdAddonScriptsAndStyles');
}

/**
 * Register Addons Scripts and Styles
 *
 * @return void
 */
function wpfdAddonScriptsAndStyles()
{
    wp_enqueue_style(
        'wpfdAddon-style',
        plugins_url('assets/css/wpfdAddonMainStyle.css', __FILE__),
        array(),
        WPFDA_VERSION
    );
    wp_enqueue_style(
        'wpfdAddon-modal',
        plugins_url('assets/css/leanmodal.css', __FILE__),
        array(),
        WPFDA_VERSION
    );
    wp_enqueue_script(
        'wpfdAddon-modal',
        plugins_url('assets/js/jquery.leanModal.min.js', __FILE__),
        array('jquery'),
        WPFDA_VERSION
    );

    if (defined('WPFD_PLUGIN_URL') && WPFD_PLUGIN_URL) {
        if (file_exists(WPFD_PLUGIN_URL . 'app/admin/assets/ui/css/jquery.qtip.css')) {
            wp_enqueue_style(
                'wpfdAddon-wooCommerce-jquery-qtip-style',
                WPFD_PLUGIN_URL . 'app/admin/assets/ui/css/jquery.qtip.css',
                array(),
                WPFDA_VERSION,
                false
            );
        }

        if (file_exists(WPFD_PLUGIN_URL . 'app/admin/assets/ui/js/jquery.qtip.min.js')) {
            wp_enqueue_script(
                'wpfdAddon-wooCommerce-jquery-qtip-script',
                WPFD_PLUGIN_URL . 'app/admin/assets/ui/js/jquery.qtip.min.js',
                array('jquery'),
                WPFDA_VERSION
            );
        }
    }

    wp_enqueue_script(
        'wpfdAddon-script',
        plugins_url('assets/js/wpFileDownloadJsMain.js', __FILE__),
        array(),
        WPFDA_VERSION
    );
}

/**
 * Delete Google Drive category callback
 *
 * @return void
 */
function wpfdAddonDeleteCategory_callback()
{
    wpfdAddon_call('category.deleteCategory');
}

/**
 * Duplicate Google Drive categories structure
 *
 * @return void
 */
function wpfdAddonDuplicateCategory_callBack()
{
    wpfdAddon_call('category.duplicateCategory');
}

/**
 * Add Google Drive category callBack
 *
 * @return void
 */
function wpfdAddonAddCategory_callBack()
{
    wpfdAddon_call('category.addCategory');
}

/**
 * Add Dropbox category callBack
 *
 * @return void
 */
function wpfdAddonAddDropCategory_callBack()
{
    wpfdAddon_call('category.addDropCategory');
}

/**
 * Google drive auto sync callback
 *
 * @return void
 */
function wpfdAddonGoogleDriveSync()
{
    $cloudConfig = WpfdAddonHelper::getAllCloudConfigs();
    $sync_time = (int) $cloudConfig['googleSyncTime'];
    if (defined('WPFDADDON_GOOGLE_AUTO_SYNC_TIME_CUSTOM') && WPFDADDON_GOOGLE_AUTO_SYNC_TIME_CUSTOM && defined('WPFDADDON_GOOGLE_AUTO_SYNC_TIME')) {
        $sync_time = WPFDADDON_GOOGLE_AUTO_SYNC_TIME;
    }
    $sync_method = (string) $cloudConfig['googleSyncMethod'];
    if (isset($cloudConfig['googleCredentials']) && !empty($cloudConfig['googleCredentials'])) {
        if ($sync_time > 0 && $sync_method !== 'setup_on_server') {
            $curSyncInterval = WpfdAddonHelper::curSyncInterval();
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl') {
                wp_remote_get(
                    admin_url('admin-ajax.php') . '?action=googleSync',
                    array(
                        'timeout' => 1,
                        'blocking' => false,
                        'sslverify' => false,
                    )
                );
            }
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl_ajax') {
                wpfdAddonGoogleSync_callback();
            }
        }
    }
}

/**
 * Google team drive auto sync callback
 *
 * @return void
 */
function wpfdAddonGoogleTeamDriveAutoSync()
{
    $cloudConfig = WpfdAddonHelper::getAllCloudTeamDriveConfigs();
    $sync_time   = (int) $cloudConfig['googleTeamDriveSyncTime'];
    if (defined('WPFDADDON_GOOGLE_TEAM_DRIVE_AUTO_SYNC_TIME_CUSTOM') && WPFDADDON_GOOGLE_TEAM_DRIVE_AUTO_SYNC_TIME_CUSTOM
        && defined('WPFDADDON_GOOGLE_TEAM_DRIVE_AUTO_SYNC_TIME')) {
        $sync_time = WPFDADDON_GOOGLE_TEAM_DRIVE_AUTO_SYNC_TIME;
    }

    $sync_method = (string) $cloudConfig['googleTeamDriveSyncMethod'];
    if (isset($cloudConfig['googleTeamDriveCredentials']) && !empty($cloudConfig['googleTeamDriveCredentials'])) {
        if ($sync_time > 0 && $sync_method !== 'setup_on_server') {
            $curSyncInterval = WpfdAddonHelper::curGoogleTeamDriveSyncInterval();
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl') {
                wp_remote_get(
                    admin_url('admin-ajax.php') . '?action=googleTeamDriveSync',
                    array(
                        'timeout'   => 1,
                        'blocking'  => false,
                        'sslverify' => false,
                    )
                );
            }

            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl_ajax') {
                wpfdAddonGoogleTeamDriveSync_callback();
            }
        }
    }
}

/**
 * Function googleSync callback
 *
 * @return void
 */
function wpfdAddonGoogleSync_callback()
{
    wpfdAddon_call('googledrive.googlesync');
}

/**
 * Function google team drive sync callback
 *
 * @return void
 */
function wpfdAddonGoogleTeamDriveSync_callback()
{
    wpfdAddon_call('googleteamdrive.googleteamdrivesync');
}

/**
 * Toggle watch changes
 *
 * @return void
 */
function wpfdAddonGoogleWatchChanges_callback()
{
    wpfdAddon_call('googledrive.googleStopWatchChanges');
}

/**
 * Webhook to listen changes form Google Drive
 *
 * @return void
 */
function wpfdAddonGooglePushListener_callback()
{
    wpfdAddon_call('googledrive.listener');
}

/**
 * Toggle dropbox watch changes
 *
 * @return void
 */
function wpfdAddonDropboxWatchChanges_callback()
{
    wpfdAddon_call('dropbox.dropboxStopWatchChanges');
}

/**
 * Webhook to listen changes form Dropbox
 *
 * @return void
 */
function wpfdAddonDropboxPushListener_callback()
{
    wpfdAddon_call('dropbox.listener');
}

/**
 * Delete all folders
 *
 * @return void
 */
function wpfdAddonGoogleDeleteAll_callback()
{
    wpfdAddon_call('googledrive.deleteAllFolder');
}
/**
 * Delete all folders
 *
 * @return void
 */
function wpfdAddonOneDriveDeleteAll_callback()
{
    wpfdAddon_call('onedrive.deleteAllFolder');
}
/**
 * Delete all folders
 *
 * @return void
 */
function wpfdAddonOneDriveBusinessDeleteAll_callback()
{
    wpfdAddon_call('onedrivebusiness.deleteAllFolder');
}
/**
 * Delete all dropbox folders
 *
 * @return void
 */
function wpfdAddonDropboxDeleteAll_callback()
{
    wpfdAddon_call('dropbox.deleteAllFolder');
}
/**
 * Check where category come from
 *
 * @param integer $termId Term Id
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
    } elseif (WpfdAddonHelper::getAwsPathByTermId($termId)) {
        return 'aws';
    } elseif (WpfdAddonHelper::getNextCloudPathByTermId($termId)) {
        return 'nextcloud';
    } else {
        return false;
    }
}

/**
 * Gooogle drive change order callback
 *
 * @param integer $pk Pk
 *
 * @return void
 */
function wpfdAddonGoogleDriveChangeOrder($pk)
{
    Application::getInstance('WpfdAddon');
    $cloudCategory = Model::getInstance('cloudcategory');
    $cloudCategory->changeOrder($pk);
}

/**
 * Google team drive change order callback
 *
 * @param integer $pk Pk
 *
 * @return void
 */
function wpfdAddonGoogleTeamDriveChangeOrder($pk)
{
    Application::getInstance('WpfdAddon');
    $cloudTeamDriveCategory = Model::getInstance('cloudteamdrivecategory');
    $cloudTeamDriveCategory->changeOrder($pk);
}

/**
 * Delete version
 *
 * @param integer $termId    Term Id
 * @param string  $fileId    File id
 * @param string  $versionId Version Id
 *
 * @return boolean
 */
function wpfdAddonDeleteVersion($termId, $fileId, $versionId)
{
    Application::getInstance('WpfdAddon');
    $cloudFile = Model::getInstance('cloudfiles');
    return $cloudFile->deleteVersion(WpfdAddonHelper::getGoogleDriveIdByTermId($termId), $fileId, $versionId);
}

/**
 * Delete version
 *
 * @param integer $termId    Term Id
 * @param string  $fileId    File id
 * @param string  $versionId Version Id
 *
 * @return boolean
 */
function wpfdAddonGoogleTeamDriveDeleteVersion($termId, $fileId, $versionId)
{
    Application::getInstance('WpfdAddon');
    $cloudTeamDriveFile = Model::getInstance('cloudteamdrivefiles');
    return $cloudTeamDriveFile->deleteVersion(WpfdAddonHelper::getGoogleTeamDriveIdByTermId($termId), $fileId, $versionId);
}

/**
 * Upload version
 *
 * @param array   $data   File Data
 * @param integer $termId Term id
 *
 * @return boolean
 */
function wpfdAddonUploadVersion($data, $termId)
{
    Application::getInstance('WpfdAddon');
    $cloudFile = Model::getInstance('cloudfiles');
    return $cloudFile->uploadVersion($data, WpfdAddonHelper::getGoogleDriveIdByTermId($termId));
}

/**
 * Upload google team drive version
 *
 * @param array   $data   File data
 * @param integer $termId Term id
 *
 * @return boolean
 */
function wpfdAddonUploadGoogleTeamDriveVersion($data, $termId)
{
    Application::getInstance('WpfdAddon');
    $cloudTeamDriveFile = Model::getInstance('cloudteamdrivefiles');
    return $cloudTeamDriveFile->uploadVersion($data, WpfdAddonHelper::getGoogleTeamDriveIdByTermId($termId));
}

/**
 * Save file info google drive
 *
 * @param array $data File data
 *
 * @return boolean
 */
function wpfdAddonSaveFileInfo($data)
{
    Application::getInstance('WpfdAddon');
    $cloudFile = Model::getInstance('cloudfiles');
    return $cloudFile->saveFileInfo($data);
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
 * Get list version
 *
 * @param string  $fileId File Id
 * @param integer $termId Term Id
 *
 * @return boolean|array
 */
function wpfdAddonGetListVersions($fileId, $termId)
{
    Application::getInstance('WpfdAddon');
    $cloudFile = Model::getInstance('cloudfiles');
    $result = $cloudFile->getListVersions($fileId, WpfdAddonHelper::getGoogleDriveIdByTermId($termId), $termId);
    return $result;
}

/**
 * Get list google team drive version
 *
 * @param string  $fileId File Id
 * @param integer $termId Term Id
 *
 * @return boolean|array
 */
function wpfdAddonGoogleTeamDriveGetListVersions($fileId, $termId)
{
    Application::getInstance('WpfdAddon');
    $cloudTeamDriveFile = Model::getInstance('cloudteamdrivefiles');
    return $cloudTeamDriveFile->getListVersions($fileId, WpfdAddonHelper::getGoogleTeamDriveIdByTermId($termId), $termId);
}

/**
 * Delete google cloud files
 *
 * @param integer $idCategory Term Id
 * @param string  $idFile     File Id
 *
 * @return boolean
 */
function wpfdAddonDeleteGoogleCloudFiles($idCategory, $idFile)
{
    Application::getInstance('WpfdAddon');
    $cloudFile = Model::getInstance('cloudfiles');
    return $cloudFile->deleteGoogleCloudFile($idCategory, $idFile);
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
 * Get a file google drive info
 *
 * @param string  $fileId     File Id
 * @param integer $categoryId Category term id
 *
 * @return boolean|array
 */
function wpfdAddonGetFileInfo($fileId, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $cloudFile = Model::getInstance('cloudfiles');
    return $cloudFile->getFileInfo($fileId, $categoryId);
}

/**
 * Upload google drive
 *
 * @param integer $termId Term id
 * @param array   $file   Upload file info
 *
 * @return array
 */
function wpfdAddonGoogleDriveUpload($termId, $file)
{
    Application::getInstance('WpfdAddon');
    $cloudFile = Model::getInstance('cloudfiles');
    return $cloudFile->googleDriveUpload($termId, $file);
}

/**
 * Set title category google drive
 *
 * @param integer $termId Term id
 * @param string  $title  Title
 *
 * @return boolean
 */
function wpfdAddonSetCategoryGoogleDriveTitle($termId, $title)
{
    Application::getInstance('WpfdAddon');
    $cloudCategory = Model::getInstance('cloudcategory');
    return $cloudCategory->changeCategoryName(WpfdAddonHelper::getGoogleDriveIdByTermId($termId), $title);
}

/**
 * Get all config google drive
 *
 * @return array
 */
function getAlldata()
{
    return WpfdAddonHelper::getAllCloudConfigs();
}

/**
 * Display drop down create google drive
 *
 * @return void
 */
function displayDropDownCreateCloud()
{
    if (wpfd_google_drive_connected()) {
        echo '<li>
                <a href="#" class="button googleCreate">
                <i class="googledrive-icon wpfd-folder wpfd-liga">google_drive</i> '
            . esc_html__('Google Drive', 'wpfdAddon') . '</a>
             </li>';
    }
}

/**
 * Display drop down create google team drive
 *
 * @return void
 */
function displayDropDownCreateCloudTeamDrive()
{
    if (wpfd_google_team_drive_connected()) {
        echo '<li>
                <a href="#" class="button googleTeamDriveCreate">
                <i class="googledrive-icon wpfd-folder wpfd-liga">google_drive</i> '
            . esc_html__('Google Team Drive', 'wpfdAddon') . '</a>
             </li>';
    }
}


/**
 * Display Cloud taps
 *
 * @return void
 */
function wpfd_addon_display_cloud_configuration_tab()
{
    echo '<a id="tab-cloudconnection" class="nav-tab" data-tab-id="cloudconnection" href="#cloudconnection">'
        . esc_html__('Cloud connection', 'wpfdAddon') .
        '</a>';
}

/**
 * Display Social taps
 *
 * @return void
 */
function wpfd_addon_display_social_tab()
{
    echo '<a id="tab-social" class="nav-tab" data-tab-id="social" href="#social">'
        . esc_html__('Social locker', 'wpfdAddon') . '</a>';
}

/**
 * Print social locker content
 *
 * @return void
 */
function wpfd_addon_display_social_content()
{
    Application::getInstance('WpfdAddon');
    $modelConf = Model::getInstance('config');
    $file_form = new Form();
    $formFile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
    $formFile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'config.xml';
    $configform = null;
    if ($file_form->load($formFile, $modelConf->getSocialConfig())) {
        $configform = $file_form->render();
    }
    echo '<div id="wpfd-social-config" class="tab-pane ">';
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form render we can not use esc here
    echo $configform;
    echo '</div>';
}

/**
 * Display cloud tap content
 *
 * @return void
 */
function displayGoogleDriveConfigurationContent()
{
    $data = getAlldata();
    $cloudConfigform = new Form();
    $formFile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
    $formFile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'cloud_config.xml';
    if ($cloudConfigform->load($formFile, $data)) {
        $cloudConfigform = $cloudConfigform->render();
    }
    echo '<div id="wpfd-theme-cloud" class="">';
    echo '<div id="wpfd-btnconnect-ggd">';
    do_action('cloudconnector_display_ggd_settings');
    showButtonConnectToCloud();
    echo '</div>';
    wpfdShowPushNotification();
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form render we can not use esc here
    echo $cloudConfigform;
    echo '<a class="ju-button orange-outline-button ju-button-inline ggd-documentation" 
            href="https://www.joomunited.com/wordpress-documentation/wp-file-download/296-wp-file-download-addon-google-drive-integration" target="_blank">
            <span>' . esc_html__('Read the online support documentation', 'wpfdAddon') . '</span>
            </a>
            <div style="clear:both">&nbsp</div>
            ';
    echo '</div>';
}

/**
 * Display cloud tap content
 *
 * @return void
 */
function displayGoogleTeamDriveConfigurationContent()
{
    $cloudTeamDriveData = getAllGoogleTeamDrivedata();
    $cloudTeamDriveConfigform = new Form();
    $formFile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
    $formFile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'cloud_team_drive_config.xml';

    if ($cloudTeamDriveConfigform->load($formFile, $cloudTeamDriveData)) {
        $cloudTeamDriveConfigform = $cloudTeamDriveConfigform->render();
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form render we can not use esc here
    $googleTeamDriveContent = '<div id="wpfd-theme-cloud-team-drive" class="">';
    do_action('cloudconnector_display_google_team_drive_settings');
    showButtonConnectToCloudTeamDrive();
    $googleTeamDriveContent .= $cloudTeamDriveConfigform;
    $googleTeamDriveContent .= '<a class="ju-button orange-outline-button ju-button-inline google-team-drive-documentation" href="https://www.joomunited.com/wordpress-documentation/wp-file-download/678-wp-file-download-addon-google-team-drive-integration" target="_blank">';
    $googleTeamDriveContent .= '<span>' . esc_html__('Read the online support documentation', 'wpfdAddon') . '</span>';
    $googleTeamDriveContent .= '</a><div style="clear:both">&nbsp</div>';
    $googleTeamDriveContent .= '</div>';
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form render we can not use esc here
    echo $googleTeamDriveContent;
}

/**
 * Display button connect to cloud when exist cloud config data
 *
 * @return boolean
 */
function hasButton()
{
    $dataGoogle = WpfdAddonHelper::getDataConfigBySeverName('google');
    $displayButton = true;
    if (isset($dataGoogle) && (!empty($dataGoogle))) {
        if ((empty($dataGoogle['googleClientId'])) || (empty($dataGoogle['googleClientSecret']))) {
            $displayButton = false;
        }
    } else {
        $displayButton = false;
    }
    return $displayButton;
}

/**
 * Display button connect to cloud when exist cloud config data
 *
 * @return boolean
 */
function hasGoogleTeamDriveButton()
{
    $dataGoogle = WpfdAddonHelper::getDataConfigByGoogleTeamDriveSever('googleTeamDrive');
    $displayButton = true;
    if (isset($dataGoogle) && (!empty($dataGoogle))) {
        if ((empty($dataGoogle['googleTeamDriveClientId'])) || (empty($dataGoogle['googleTeamDriveClientSecret']))) {
            $displayButton = false;
        }
    } else {
        $displayButton = false;
    }
    return $displayButton;
}

/**
 * Parse origin url
 *
 * @param string $url Input url
 *
 * @return string
 */
function wpfd_parse_origin_url($url)
{
    $raw_url = parse_url($url);
    $scheme  = isset($raw_url['scheme']) ? $raw_url['scheme'] . '://' : '';
    $host    = isset($raw_url['host']) ? $raw_url['host'] : '';
    $port    = isset($raw_url['port']) ? ':' . $raw_url['port'] : '';
    $user    = isset($raw_url['user']) ? $raw_url['user'] : '';
    $pass    = isset($raw_url['pass']) ? ':' . $raw_url['pass'] : '';
    $pass    = ($user || $pass) ? $pass . '@' : '';

    return esc_url($scheme . $user . $pass . $host . $port);
}

/**
 * Display button connect google drive
 *
 * @return void
 */
function showButtonConnectToCloud()
{
    $app = Application::getInstance('WpfdAddon');
    $path_wpfdaddongoogle = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
    $path_wpfdaddongoogle .= DIRECTORY_SEPARATOR . 'WpfdAddonGoogle.php';
    require_once $path_wpfdaddongoogle;
    $googleDrive = new WpfdAddonGoogleDrive();
    $javascript_origins = wpfd_parse_origin_url(site_url());
    if (hasButton()) {
        if (!$googleDrive->checkAuth()) {
            $url = $googleDrive->getAuthorisationUrl();
            ?>
            <a id="ggconnect" class="ju-button orange-outline-button" href="#"
               onclick="window.open('<?php echo esc_url($url); ?>','foo','width=600,height=600');return false;">
                <img src="<?php echo esc_url(plugins_url('assets/images/drive-icon-colored.png', __FILE__)); ?>" alt=""/>
                <label class="cloud-connect-label"><?php esc_html_e('Connect Google Drive', 'wpfdAddon') ?></label>
            </a>
            <?php
        } else { ?>
            <a id="gg_disconnect" class="ju-button orange-outline-button" href="admin.php?page=wpfdAddon-cloud&task=googledrive.logout">
                <img style="vertical-align: middle;"
                     src="<?php echo esc_url(plugins_url('assets/images/drive-icon-colored.png', __FILE__)); ?>" alt=""/>
                <label class="cloud-connect-label"><?php esc_html_e('Disconnect Google Drive', 'wpfdAddon') ?></label>
            </a>
            <?php
        }
    }
    ?>
    <div id="gg_setup">
        <div class="ju-settings-option full-width">
            <label class="ju-setting-label" for=""><?php esc_html_e('JavaScript origins', 'wpfdAddon') ?></label>
            <input title="" class="ju-input ju-large-text" type="text" readonly="true"
                   value="<?php echo esc_url($javascript_origins); ?>"/>
        </div>
        <div class="ju-settings-option full-width">
            <label class="ju-setting-label" for=""><?php esc_html_e('Redirect URIs', 'wpfdAddon') ?></label>
            <?php
            $url_ggd_authenticate = site_url() . '/wp-admin/admin.php?page=wpfdAddon-cloud';
            $url_ggd_authenticate .= '&task=googledrive.authenticate';
            ?>
            <input title="" class="ju-input ju-large-text" type="text" readonly="true"
                   value="<?php echo esc_url($url_ggd_authenticate); ?>"/>
        </div>
    </div>
    <?php
}

/**
 * Display button connect google team drive
 *
 * @return void
 */
function showButtonConnectToCloudTeamDrive()
{
    $app = Application::getInstance('WpfdAddon');
    $wpfdAddonGoogleTeamDrivePath = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
    $wpfdAddonGoogleTeamDrivePath .= DIRECTORY_SEPARATOR . 'WpfdAddonGoogleTeamDrive.php';
    require_once $wpfdAddonGoogleTeamDrivePath;
    $googleTeamDrive = new WpfdAddonGoogleTeamDrive();
    $javascript_origins = wpfd_parse_origin_url(site_url()); ?>
    <div id="wpfd-btnconnect-googleteamdrive" style="margin-bottom: -35px">
        <?php
        $googleTeamDriveConnected = false;
        if (hasGoogleTeamDriveButton()) {
            if (!$googleTeamDrive->checkAuth()) {
                $url = $googleTeamDrive->getAuthorisationUrl();
                ?>
                <a id="ggtdconnect" class="ju-button" href="#"
                   onclick="window.open('<?php echo esc_url($url); ?>','foo','width=600,height=600');return false;">
                    <img style="vertical-align: middle;" src="<?php echo esc_url(plugins_url('assets/images/drive-icon-colored.png', __FILE__)); ?>" alt=""/>
                    <?php esc_html_e('Connect Google Team Drive', 'wpfdAddon') ?>
                </a>
                <?php
            } else { ?>
                <?php $googleTeamDriveConnected = true; ?>
                <a id="ggtd_disconnect" class="ju-button" href="admin.php?page=wpfdAddon-cloud&task=googleteamdrive.logout">
                    <img style="vertical-align: middle;"
                         src="<?php echo esc_url(plugins_url('assets/images/drive-icon-colored.png', __FILE__)); ?>" alt=""/>
                    <?php esc_html_e('Disconnect Google Team Drive', 'wpfdAddon') ?>
                </a>
                <?php
            }
        }
        ?>
    </div>
    <div id="ggtd_setup">
        <?php if ($googleTeamDriveConnected) : ?>
            <?php
            $drives = $googleTeamDrive->getAllRootDrives();
            $configParams = $googleTeamDrive->getDataConfigByGoogleTeamDriveSever('googleTeamDrive');
            $baseFolderConfigId = $configParams['googleTeamDriveBaseFolder'];
            ?>
            <div class="ju-settings-option full-width googleTeamDriveBaseFolder-setup"><br/>
                <label class="ju-setting-label" for=""><?php esc_html_e('Select root drive', 'wpfdAddon') ?></label>
                <?php
                $content = '';
                $content .= '<select class="ju-input ju-large-text wpfd_googleTeamDriveBaseFolderId" name="rootGoogleTeamDriveBaseFolderId">';
                $content .= '<option value ="" disabled="disabled">— ' . esc_html__('Select root drive', 'wpfdAddon') . ' —</option >';
                if (!empty($drives)) {
                    foreach ($drives as $drive) {
                        if ($baseFolderConfigId === $drive['id']) {
                            $content .= '<option selected ="selected" value = ' . esc_attr($drive['id']) . '   > ' . esc_html($drive['name']);
                            $content .= '</option >';
                        } else {
                            $content .= '<option value = ' . esc_attr($drive['id']) . ' > ' . esc_html($drive['name']) . '</option >';
                        }
                    }
                    $content .= '</select >';
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escape above
                    echo $content;
                }
                ?>
                <div class="ju-settings-help">
                    <?php
                    $msg_wpfdaddon = 'Google Team Drive root drive selection for synchronization. Make sure to select the'
                        . ' right one as the synchronization may reflect the file changes on your live website!';
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- keep for short length of line
                    esc_html_e($msg_wpfdaddon, 'wpfdAddon');
                    ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="ju-settings-option links full-width">
            <label class="ju-setting-label" for=""><?php esc_html_e('JavaScript origins', 'wpfdAddon') ?></label>
            <input title="" class="ju-input ju-large-text" type="text" readonly="true"
                   value="<?php echo esc_url($javascript_origins); ?>"/>
        </div>
        <div class="ju-settings-option links full-width">
            <label class="ju-setting-label" for=""><?php esc_html_e('Redirect URIs', 'wpfdAddon') ?></label>
            <?php
            $url_ggd_authenticate = site_url() . '/wp-admin/admin.php?page=wpfdAddon-cloud';
            $url_ggd_authenticate .= '&task=googleteamdrive.authenticate';
            ?>
            <input title="" class="ju-input ju-large-text" type="text" readonly="true"
                   value="<?php echo esc_url($url_ggd_authenticate); ?>"/>
        </div>
    </div>
    <?php
}

/**
 * Show push notification elements in config tab
 *
 * @return void
 */
function wpfdShowPushNotification()
{
    if (!class_exists('WpfdAddonGoogleDrive')) {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdaddongoogle = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddongoogle .= DIRECTORY_SEPARATOR . 'WpfdAddonGoogle.php';
        require_once $path_wpfdaddongoogle;
    }

    $googleDrive = new WpfdAddonGoogleDrive();
    if (!$googleDrive->checkAuth()) {
        return;
    }

    $automatic_mode = apply_filters('cloudconnector_ggd_filter_connect_mode', false);
    if ($automatic_mode) {
        return;
    }

    // Display error
    $googlePush = get_option('_wpfd_google_watch_changes', 0);
    $watchData = get_option('_wpfd_google_watch_data', '');
    $errorMessage = '';
    if ($watchData !== '') {
        $watchData = json_decode($watchData, true);
        if (is_array($watchData) && isset($watchData['error'])) {
            if ((int) $watchData['error'] === 401) {
                // Unauthorized domain
                $errorMessage = esc_html__('Google push notifications are activated but are not authorized on this domain, please check that this domain is validated on the Google Search console and the Google Cloud Platform', 'wpfdAddon');
            } else {
                // Site not used https
                $errorMessage = esc_html($watchData['message']);
            }
        }
    }
    $errorBgColor = '';
    if ($errorMessage !== '') {
        $errorBgColor = ' error';
    }

    if ($googlePush) {
        $text = esc_html__('Stop watching changes from Google Drive', 'wpfdAddon');
    } else {
        $text = esc_html__('Watch changes from Google Drive', 'wpfdAddon');
    }

    // Nonce field for ajax request
    $security = wp_create_nonce('wpfd-google-push');
    ?>
    <button id="wpfd-btnpush-ggd"
            data-security="<?php echo esc_attr($security); ?>"
            class="ju-button orange-outline-button<?php echo $errorBgColor; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This place dont need to escaped?>"
            style="display: inline-block;float: right;"
            title="<?php esc_html_e('Enable/Disable the push notification for file synchronization. Allows you to make instant file synchronization (require domain validation on the Google Search Console)', 'wpfdAddon'); ?>"
    >
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above
        echo $text;
        ?>
    </button>
    <?php
    $displayError = get_option('_wpfd_google_display_push_error', 1);
    if ($errorMessage !== '' && $displayError) : ?>
        <div class="wpfd-float-message wpfd-ggd-watch-change-message">
            <div class="wpfd-alert-message"><strong>Warning: </strong><?php echo $errorMessage; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above ?></div>
        </div>
    <?php endif;
}

/**
 * Show dropbox push notification elements in configuration
 *
 * @return void
 */
function wpfdDropboxShowPushNotification()
{
    $dropData = getDropboxData();

    if (empty($dropData['dropboxAccessToken'])) {
        return;
    }

    $automatic_mode = get_option('joom_cloudconnector_dropbox_connect_mode', 'manual');
    if ($automatic_mode === 'automatic') {
        return;
    }

    // Display error
    $dropboxPush  = get_option('_wpfd_dropbox_watch_changes', 0);
    $watchData    = get_option('_wpfd_dropbox_watch_change_information', '');
    $errorMessage = '';

    if ($watchData !== '' && is_string($watchData)) {
        $watchData = json_decode($watchData, true);
        if (is_array($watchData) && isset($watchData['error'])) {
            if ((int) $watchData['error'] === 401) {
                // Unauthorized domain
                $errorMessage = esc_html__('Dropbox push notifications are activated but are not authorized on this domain, please check that this domain is validated on the Dropbox Platform', 'wpfdAddon');
            } else {
                // Site not used https
                $errorMessage = esc_html($watchData['message']);
            }
        }
    }

    $errorBgColor = '';
    if ($errorMessage !== '') {
        $errorBgColor = ' error';
    }

    if ($dropboxPush) {
        $text = esc_html__('Stop watching changes from Dropbox', 'wpfdAddon');
    } else {
        $text = esc_html__('Watch changes from Dropbox', 'wpfdAddon');
    }

    // Nonce field for ajax request
    $security = wp_create_nonce('wpfd-dropbox-push');
    ?>
    <button id="wpfd-btnpush-dropbox"
            data-security="<?php echo esc_attr($security); ?>"
            class="ju-button orange-outline-button<?php echo $errorBgColor; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This place dont need to escaped?>"
            style="display: inline-block; float: right;"
            title="<?php esc_html_e('Enable/Disable the push notification for file synchronization. Allows you to make instant file synchronization (require domain validation on the Dropbox)', 'wpfdAddon'); ?>"
    >
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above
        echo $text;
        ?>
    </button>
    <?php
    $webhookUrl = admin_url('admin-ajax.php') . '?action=dropboxPushListener';
    if (!$dropboxPush) : ?>
        <div class="wpfd-float-message wpfd-dropbox-watch-change-message">
            <div class="wpfd-alert-message"><strong>Warning: </strong><?php echo esc_html_e('WebHook callback must be HTTPS: ', 'wpfdAddon') . esc_url($webhookUrl); ?></div>
        </div>
    <?php endif;
}
/**
 * Registry menu WPFD addon
 *
 * @return void
 */
function wpfdAddon_menu()
{
    add_submenu_page(
        'options.php',
        'WPFD Addon page',
        'WPFD Addon Submenu Page',
        'manage_options',
        'wpfdAddon-cloud',
        'wpfdAddon_call_controller'
    );
}

/**
 * Call controller
 *
 * @return void
 */
function wpfdAddon_call_controller()
{
    $task = Utilities::getInput('task');
    wpfdAddon_call($task);
}


/**
 * Wpfd addon call
 *
 * @param string $default_task Task defaulr
 *
 * @return void
 */
function wpfdAddon_call($default_task = 'wpfdAddon.display')
{
    $application = Application::getInstance('WpfdAddon');
    wpfdAddonInit();
    $application->execute($default_task);
}


/**
 * Get Dropbox configs
 *
 * @return array
 */
function getDropboxData()
{
    return WpfdAddonHelper::getAllDropboxConfigs();
}

/**
 * Display dropbox configuration tab
 *
 * @return void
 */
function displayDropboxConfigurationTab()
{
    echo '<a id="tab-dropbox" class="nav-tab" data-tab-id="dropbox" href="#dropbox">'
        . esc_html__('Dropbox', 'wpfdAddon') .
        '</a>';
}

/**
 * Display content configuration Dropbox
 *
 * @return void
 */
function displayDropboxConfigurationContent()
{
    $data = getDropboxData();
    $dropboxConfigform = new Form();
    $formFile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
    $formFile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'dropbox_config.xml';
    if ($dropboxConfigform->load($formFile, $data)) {
        $dropboxConfigform = $dropboxConfigform->render();
    }
    echo '<div id="wpfd-theme-dropbox">';
    echo '<div id="wpfd-btnconnect-dropbox" style="padding: 0 0 0 15px;">';
    do_action('cloudconnector_display_dropbox_settings');
    showButtonConnectToDropbox();
    echo '</div>';
    wpfdDropboxShowPushNotification();
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form render we can not use esc here
    echo $dropboxConfigform;
    echo '<a class="ju-button orange-outline-button ju-button-inline dropbox-documentation" href="https://www.joomunited.com/wordpress-documentation/wp-file-download/297-wp-file-download-addon-dropbox-integration"';
    echo ' target="_blank">' . esc_html__('Read the online support documentation', 'wpfdAddon') . '</span></a>';
    echo '<div style="clear:both">&nbsp</div>';
    echo '</div>';
}

/**
 * Registry menu dropbox
 *
 * @return void
 */
function wpfdDropboxAddon_menu()
{
    add_submenu_page(
        'options.php',
        'WPFD Dropbox Addon page',
        'WPFD Dropbox Addon Submenu Page',
        'manage_options',
        'wpfdAddon-dropbox',
        'wpfdAddon_call_controller'
    );
}

/**
 * Display button connect to cloud when exist cloud config data
 *
 * @return boolean
 */
function hasDropboxButton()
{
    $dataDropbox = WpfdAddonHelper::getDataConfigByDropbox('dropbox');
    $displayButton = true;
    if (isset($dataDropbox) && (!empty($dataDropbox))) {
        if ((empty($dataDropbox['dropboxKey'])) || (empty($dataDropbox['dropboxSecret']))) {
            $displayButton = false;
        }
    } else {
        $displayButton = false;
    }
    return $displayButton;
}

/**
 * Show button connect dropbox if isset App key && App Secret
 *
 * @return void
 */
function showButtonConnectToDropbox()
{
    $app = Application::getInstance('WpfdAddon');
    $path_wpfdaddondropbox = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
    $path_wpfdaddondropbox .= DIRECTORY_SEPARATOR . 'WpfdAddonDropbox.php';
    require_once $path_wpfdaddondropbox;
    $Dropbox = new WpfdAddonDropbox();
    if (hasDropboxButton()) {
        if ($Dropbox->checkAuth()) {
            $url = $Dropbox->getAuthorizeDropboxUrl();
            ?>
            <a id="drop_connect" class="ju-button" href="#"
               onclick="window.open('<?php echo esc_url($url); ?>','foo','width=600,height=600');return false;">
                <img style="vertical-align: middle;"
                     src="<?php echo esc_url(plugins_url('assets/images/dropbox_icon_colored.png', __FILE__)); ?>" alt=""/>
                <?php esc_html_e('Connect Dropbox', 'wpfdAddon') ?></a>
            <?php
        } else { ?>
            <a id="drop_disconnect" class="ju-button" href="admin.php?page=wpfdAddon-dropbox&task=dropbox.logout">
                <img style="vertical-align: middle;"
                     src="<?php echo esc_url(plugins_url('assets/images/dropbox_icon_colored.png', __FILE__)); ?>" alt=""/>
                <?php esc_html_e('Disconnect Dropbox', 'wpfdAddon') ?></a>
            <?php
            $listBaseFolders = $Dropbox->listChildrenFolders();
            $dropboxConfig = WpfdAddonHelper::getDataConfigByDropbox('dropbox');
            $baseFolderConfigId = isset($dropboxConfig['dropboxBaseFolderId']) ?
                $dropboxConfig['dropboxBaseFolderId'] : '';
            ?>
            <div class="ju-settings-option full-width dropboxBaseFolder-setup"><br/>
                <label class="ju-setting-label" for=""><?php esc_html_e('Select root folder', 'wpfdAddon') ?></label>
                <?php
                $content = '';
                $content .= '<select class="ju-input ju-large-text wpfd_dropboxBaseFolderId">';

                $content .= '<option value ="">— ' . esc_html__('Select root folder', 'wpfdAddon') . '—</option >';
                if (!empty($listBaseFolders)) {
                    foreach ($listBaseFolders as $fid => $f) {
                        if ($baseFolderConfigId === $f['id']) {
                            $content .= '<option selected ="selected" value = ' . esc_attr($f['id']) . '   > ' . esc_html($f['name']);
                            $content .= '</option >';
                        } else {
                            $content .= '<option value = ' . esc_attr($f['id']) . ' > ' . esc_html($f['name']) . '</option >';
                        }
                    }
                    $content .= '</select >';
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escape above
                    echo $content;
                }
                ?>
                <div class="ju-settings-help">
                    <?php
                    $msg_wpfdaddon = 'Dropbox root folder selection for synchronization. Make sure to select the'
                        . ' right one as the synchronization may reflect the file changes on your live website!';
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- keep for short length of line
                    esc_html_e($msg_wpfdaddon, 'wpfdAddon');
                    ?>
                </div>
            </div>
            <?php
        }
    } ?>
    <div id="dropbox_setup">
        <div class="ju-settings-option full-width">
            <label class="ju-setting-label" for=""><?php esc_html_e('Redirect URIs Server', 'wpfdAddon') ?></label>

            <input title="" class="ju-input ju-large-text" style="width:100%" type="text" readonly="true"
                   value="<?php echo esc_url(get_admin_url()); ?>admin.php"/>
        </div>
        <div class="ju-settings-option full-width">
            <label class="ju-setting-label" for=""><?php esc_html_e('Redirect URIs', 'wpfdAddon') ?></label>
            <?php
            $url_dropboxAuth = $Dropbox->getRedirectUrl();
            ?>
            <input title="" class="ju-input ju-large-text" style="width:100%" type="text" readonly="true"
                   value="<?php echo esc_url($url_dropboxAuth); ?>"/>
        </div>

    </div>
    <?php
}

/**
 * Dropbox delete category
 *
 * @return void
 */
function wpfdAddonDeleteDropboxCategory_callback()
{
    wpfdAddon_call('category.deleteDropboxCategory');
}

/**
 * Dropbox duplicate category structure
 *
 * @return void
 */
function wpfdAddonDuplicateDropboxCategory_callBack()
{
    wpfdAddon_call('category.duplicateDropboxCategory');
}

/**
 * Drop down create dropbox
 *
 * @return void
 */
function displayDropDownCreateDropbox()
{
    $data = getDropboxData();
    $dropbox = new WpfdAddonDropbox();
    $checkAuth = $dropbox->checkAuth();
    if (isset($data['dropboxAccessToken']) && (!empty($data['dropboxAccessToken'])) && !$checkAuth) {
        ?>
        <li>
            <a href="#" class="button dropboxCreate">
                <i class="dropbox-icon wpfd-folder wpfd-liga">dropbox</i>
                <?php esc_html_e('Dropbox', 'wpfdAddon'); ?>
            </a>
        </li>
        <?php
    }
}


/**
 * Set category dropbox title
 *
 * @param integer $termId Term id
 * @param string  $title  Title
 *
 * @return boolean
 */
function wpfdAddonSetCategoryDropboxTitle($termId, $title)
{
    Application::getInstance('WpfdAddon');
    $dropboxCategory = Model::getInstance('dropboxcategory');
    return $dropboxCategory->changeDropboxCategoryName(WpfdAddonHelper::getDropboxPathByTermId($termId), $title, $termId);
}

/**
 * Dropbox upload file
 *
 * @param integer $termId Term Id
 * @param array   $file   File info
 *
 * @return boolean
 */
function wpfdAddonDropboxUpload($termId, $file)
{
    Application::getInstance('WpfdAddon');
    $dropboxFile = Model::getInstance('dropboxfiles');
    return $dropboxFile->dropboxUpload($termId, $file);
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
 * Delete file from folder
 *
 * @param integer $idCategory Term id
 * @param string  $idFile     File id
 *
 * @return boolean
 */
function wpfdAddonDeleteDropboxFiles($idCategory, $idFile)
{
    Application::getInstance('WpfdAddon');
    $dropFile = Model::getInstance('dropboxfiles');
    return $dropFile->deleteDropboxFile($idCategory, $idFile);
}

/**
 * Get file info dropbox
 *
 * @param string  $idFile     File id
 * @param integer $idCategory Term id
 *
 * @return array
 */
function wpfdAddonDropboxGetFileInfo($idFile, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $dropFile = Model::getInstance('dropboxfiles');
    return $dropFile->getDropboxFileInfo($idFile, $idCategory);
}

/**
 * Save dropbox file info
 *
 * @param aray    $data       File data
 * @param integer $idCategory Term id
 *
 * @return string File id
 */
function wpfdAddonSaveDropboxFileInfo($data, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $dropFile = Model::getInstance('dropboxfiles');
    return $dropFile->saveDropboxFileInfo($data, $idCategory);
}


/**
 * Upload dropbox version
 *
 * @param array   $data   Version data
 * @param integer $termId Term id
 *
 * @return boolean
 */
function wpfdAddonUploadDropboxVersion($data, $termId)
{
    Application::getInstance('WpfdAddon');
    $dropFile = Model::getInstance('dropboxfiles');
    return $dropFile->uploadDropboxVersion($data, $termId);
}

/**
 * Dropbox version info
 *
 * @param string  $idFile     File id
 * @param integer $idCategory Term id
 *
 * @return array|boolean array when success
 *                       false when throw error
 */
function wpfdAddonDropboxVersionInfo($idFile, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $dropFile = Model::getInstance('dropboxfiles');
    return $dropFile->displayDropboxVersionInfo($idFile, $idCategory);
}


/**
 * Change order dropbox
 *
 * @param integer $pk  Term id
 * @param integer $ref Ref term id
 *
 * @return void
 */
function wpfdAddonDropboxChangeOrder($pk, $ref)
{
    Application::getInstance('WpfdAddon');
    $dropCategory = Model::getInstance('dropboxcategory');
    $dropCategory->changeDropboxOrder($pk, $ref);
}

/**
 * Dropbox sync callback
 *
 * @return void
 */
function wpfdAddonDropboxSync_callback()
{
    wpfdAddon_call('dropbox.dropboxsync');
}

/**
 * Dropbox auto sync callback
 *
 * @return void
 */
function wpfdAddonDropboxAutoSync()
{
    $app = Application::getInstance('WpfdAddon');
    $path_wpfdaddondropbox = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
    $path_wpfdaddondropbox .= DIRECTORY_SEPARATOR . 'WpfdAddonDropbox.php';
    require_once $path_wpfdaddondropbox;

    // Include helper
    if (!class_exists('WpfdAddonHelper')) {
        $helperPath = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $helperPath .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $helperPath;
    }

    $dropbox = new WpfdAddonDropbox();
    if (!$dropbox->checkAuth()) {
        $cloudConfig = WpfdAddonHelper::getAllDropboxConfigs();
        $sync_time = (int) $cloudConfig['dropboxSyncTime'];
        $sync_method = (string) $cloudConfig['dropboxSyncMethod'];
        if ($sync_time > 0 && $sync_method !== 'setup_on_server') {
            $curSyncInterval = WpfdAddonHelper::curSyncIntervalDropbox();
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl') {
                wp_remote_get(
                    admin_url('admin-ajax.php') . '?action=dropboxSync',
                    array(
                        'timeout' => 1,
                        'blocking' => false,
                        'sslverify' => false,
                    )
                );
            }
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl_ajax') {
                wpfdAddonDropboxSync_callback();
            }
        }
    }
}

/**
 * Check cloud exist
 *
 * @return boolean
 */
function check_cloud_exist()
{
    return (wpfda_dropbox_connected() || wpfda_google_drive_connected() || wpfd_addon_google_team_drive_connected() || wpfda_onedrive_connected() || wpfda_onedrive_business_connected() || wpfda_aws_connected() || wpfda_nextcloud_connected()) ? true : false;
}

/**
 * Check Dropbox connected
 *
 * @return boolean
 */
function wpfda_dropbox_connected()
{
    $dropData = getDropboxData();

    return !empty($dropData['dropboxAccessToken']);
}

/**
 * Check GoogleDrive connected
 *
 * @return boolean
 */
function wpfda_google_drive_connected()
{
    $googleData = getAlldata();

    return !empty($googleData['googleCredentials']);
}

/**
 * Check google team drive connection
 *
 * @return boolean
 */
function wpfd_addon_google_team_drive_connected()
{
    $googleTeamDriveData = getAllGoogleTeamDrivedata();

    return !empty($googleTeamDriveData['googleTeamDriveCredentials']);
}

/**
 * Check Onedrive connected
 *
 * @return boolean
 */
function wpfda_onedrive_connected()
{
    $oneDrive = getOneDriveData();
    $onedriveConnected = isset($oneDrive['onedriveConnected']) ? $oneDrive['onedriveConnected'] : 0;

    return $onedriveConnected ? true : false;
}

/**
 * Check Onedrive Business connected
 *
 * @return boolean
 */
function wpfda_onedrive_business_connected()
{

    $onedriveBusiness = get_option('_wpfdAddon_onedrive_business_config', '');
    $onedriveBusinessConnected = isset($onedriveBusiness['connected']) ? $onedriveBusiness['connected'] : 0;

    return $onedriveBusinessConnected ? true : false;
}

/**
 * Check AWS connected
 *
 * @return boolean
 */
function wpfda_aws_connected()
{
    $aws = get_option('_wpfdAddon_aws_config', '');
    $awsConnected = isset($aws['awsConnected']) ? $aws['awsConnected'] : 0;

    return $awsConnected ? true : false;
}

/**
 * Check Nextcloud connected
 *
 * @return boolean
 */
function wpfda_nextcloud_connected()
{
    $nextcloud = get_option('_wpfdAddon_nextcloud_config', '');
    $nextcloudState = isset($nextcloud['nextcloudState']) ? $nextcloud['nextcloudState'] : 0;

    return $nextcloudState ? true : false;
}

/**
 * Download Google Drive Info
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return array|boolean
 */
function wpfdAddonDownloadGoogleDriveInfo($id_file, $id_category)
{
    $googleCate = new WpfdAddonGoogleDrive;
    $file = $googleCate->download($id_file, WpfdAddonHelper::getGoogleDriveIdByTermId($id_category), false, 0, false);
    $uploadDir = wp_upload_dir();
    $uploadDirTmp = $uploadDir['basedir'] . DIRECTORY_SEPARATOR . 'wpfd' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
    if (!file_exists($uploadDirTmp)) {
        mkdir($uploadDirTmp, 0777, true);
        $data = '<html><body bgcolor="#FFFFFF"></body></html>';
        $tmpFile = fopen($uploadDirTmp . 'index.html', 'w');
        fwrite($tmpFile, $data);
        fclose($tmpFile);
        $data = 'deny from all';
        $tmpFile = fopen($uploadDirTmp . '.htaccess', 'w');
        fwrite($tmpFile, $data);
        fclose($tmpFile);
    }

    $tmpFilePath = $uploadDirTmp . md5(time()) . '.tmp';
    $googleCate->downloadLargeFile($file, $file->mimeType, $tmpFilePath);
    if (!file_exists($tmpFilePath)) {
        return false;
    }
    // todo: not working
    return array($googleCate, $file, $tmpFilePath);
}

/**
 * Download google team drive information
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return array|boolean
 */
function wpfdAddonDownloadGoogleTeamDriveInfo($id_file, $id_category)
{
    $googleTeamDriveCategory = new WpfdAddonGoogleTeamDrive();
    $file                    = $googleTeamDriveCategory->download($id_file, WpfdAddonHelper::getGoogleTeamDriveIdByTermId($id_category), false, 0, false);
    $uploadDir               = wp_upload_dir();
    $uploadDirTmp            = $uploadDir['basedir'] . DIRECTORY_SEPARATOR . 'wpfd' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;

    if (!file_exists($uploadDirTmp)) {
        mkdir($uploadDirTmp, 0777, true);
        $data = '<html><body bgcolor="#FFFFFF"></body></html>';
        $tmpFile = fopen($uploadDirTmp . 'index.html', 'w');
        fwrite($tmpFile, $data);
        fclose($tmpFile);
        $data = 'deny from all';
        $tmpFile = fopen($uploadDirTmp . '.htaccess', 'w');
        fwrite($tmpFile, $data);
        fclose($tmpFile);
    }

    $tmpFilePath = $uploadDirTmp . md5(time()) . '.tmp';
    $googleTeamDriveCategory->downloadLargeFile($file, $file->mimeType, $tmpFilePath);
    if (!file_exists($tmpFilePath)) {
        return false;
    }
    // todo: not working
    return array($googleTeamDriveCategory, $file, $tmpFilePath);
}

/**
 * Upload google file
 *
 * @param array   $file         File info
 * @param string  $file_current File path
 * @param integer $id_category  Term id
 *
 * @throws Exception            Fire if errors
 *
 * @return void
 */
function wpfdAddonUploadGoogleFile($file, $file_current, $id_category)
{
    $google = new WpfdAddonGoogleDrive;
    $fileSize = filesize($file_current);
    $fileName = $file['title'] . '.' . $file['ext'];
    $fileMime = WpfdHelperFile::mimeType($file['ext']);
    $fileParentCloudId = WpfdAddonHelper::getGoogleDriveIdByTermId($id_category);

    if ($fileSize > 10 * 1024 * 1024) {
        $result = $google->uploadLargeFile($fileName, $file_current, $fileMime, $fileParentCloudId);
    } else {
        $result = $google->uploadFile(
            $file['title'] . '.' . $file['ext'],
            file_get_contents($file_current),
            WpfdHelperFile::mimeType($file['ext']),
            WpfdAddonHelper::getGoogleDriveIdByTermId($id_category)
        );
    }

    // Check pending uploaded file
    if (!function_exists('wpfd_can_edit_own_category')
        || !function_exists('wpfd_user_is_owner_of_category')) {
        $app = Application::getInstance('Wpfd');
        $path_wpfdfunctions = $app->getPath() . DIRECTORY_SEPARATOR . 'functions.php';
        require_once $path_wpfdfunctions;
    }

    $uploadFrom  = Utilities::getInput('upload_from') ? Utilities::getInput('upload_from') : '';
    $publishFile = false;

    if ((int)get_current_user_id() !== 0) {
        if (wpfd_can_edit_category()) {
            $publishFile = true;
        }

        if (wpfd_can_edit_own_category()) {
            Application::getInstance('Wpfd');
            $categoryModel  = Model::getInstance('category');
            $categoryGoogle = $categoryModel->getCategory($id_category);
            if (wpfd_user_is_owner_of_category($categoryGoogle)) {
                $categoryInfo = get_term($id_category, 'wpfd-category');
                if (!empty($categoryInfo) && !is_wp_error($categoryInfo)) {
                    if ($categoryInfo->description !== 'null' && $categoryInfo->description !== '') {
                        $cateParams = json_decode($categoryInfo->description, true);
                        if (isset($cateParams['category_own'])
                            && (int)$cateParams['category_own'] === (int)$categoryGoogle->params['category_own']) {
                            $publishFile = true;
                        }
                    }
                }
            }
        }
    }

    if (isset($result->id) && $uploadFrom === 'front' && $publishFile === false) {
        wpfdAddonGoogleWaitingForApprovedFiles($result->id);
    }

    // Hold folders and files
    if (isset($result->id) && isset($file['file_path'])) {
        $uploadedFileData                = array();
        $uploadedFileData['file_id']     = $result->id;
        $uploadedFileData['file_path']   = isset($file['file_path'])? $file['file_path'] : '' ;
        $uploadedFileData['category_id'] = $id_category;

        update_option('_wpfd_google_upload_folders_and_files_' . $result->id, $uploadedFileData);
    }

    add_filter('wpfd_addon_googledrive_uploaded_result', function () use ($result) {
        return $result;
    });

    /**
     * Action fire after file uploaded
     *
     * @param integer|object|mixed The file ID on success.
     * @param integer|object|mixed The term ID.
     * @param integer|object|mixed The source category file.
     *
     * @return void
     *
     * @ignore Hook already documented
     */
    do_action('wpfd_file_uploaded', $result->id, $id_category, array('source' => 'googleDrive'));
}

/**
 * Upload google team drive file
 *
 * @param array   $file         File info
 * @param string  $file_current File path
 * @param integer $id_category  Term id
 *
 * @throws Exception            Fire if errors
 *
 * @return void
 */
function wpfdAddonUploadGoogleTeamDriveFile($file, $file_current, $id_category)
{
    $app = Application::getInstance('WpfdAddon');
    if (!class_exists('WpfdAddonGoogleTeamDrive')) {
        $googleTeamDrivePath = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
        $googleTeamDrivePath .= DIRECTORY_SEPARATOR . 'WpfdAddonGoogleTeamDrive.php';
        require_once $googleTeamDrivePath;
    }
    $googleTeamDrive   = new WpfdAddonGoogleTeamDrive();
    $fileSize          = filesize($file_current);
    $fileName          = $file['title'] . '.' . $file['ext'];
    $fileMime          = WpfdHelperFile::mimeType($file['ext']);
    $fileParentCloudId = WpfdAddonHelper::getGoogleTeamDriveIdByTermId($id_category);

    if ($fileSize > 10 * 1024 * 1024) {
        $result = $googleTeamDrive->uploadLargeFile($fileName, $file_current, $fileMime, $fileParentCloudId);
    } else {
        $result = $googleTeamDrive->uploadFile(
            $file['title'] . '.' . $file['ext'],
            file_get_contents($file_current),
            WpfdHelperFile::mimeType($file['ext']),
            WpfdAddonHelper::getGoogleTeamDriveIdByTermId($id_category),
            $file_current
        );
    }

    // Check pending uploaded file
    if (!function_exists('wpfd_can_edit_own_category')
        || !function_exists('wpfd_user_is_owner_of_category')) {
        $app = Application::getInstance('Wpfd');
        $path_wpfdfunctions = $app->getPath() . DIRECTORY_SEPARATOR . 'functions.php';
        require_once $path_wpfdfunctions;
    }

    $uploadFrom  = Utilities::getInput('upload_from') ? Utilities::getInput('upload_from') : '';
    $publishFile = false;

    if ((int)get_current_user_id() !== 0) {
        if (wpfd_can_edit_category()) {
            $publishFile = true;
        }

        if (wpfd_can_edit_own_category()) {
            Application::getInstance('Wpfd');
            $categoryModel  = Model::getInstance('category');
            $categoryGoogleTeamDrive = $categoryModel->getCategory($id_category);
            if (wpfd_user_is_owner_of_category($categoryGoogleTeamDrive)) {
                $categoryInfo = get_term($id_category, 'wpfd-category');
                if (!empty($categoryInfo) && !is_wp_error($categoryInfo)) {
                    if ($categoryInfo->description !== 'null' && $categoryInfo->description !== '') {
                        $cateParams = json_decode($categoryInfo->description, true);
                        if (isset($cateParams['category_own'])
                            && (int)$cateParams['category_own'] === (int)$categoryGoogleTeamDrive->params['category_own']) {
                            $publishFile = true;
                        }
                    }
                }
            }
        }
    }

    if (isset($result->id) && $uploadFrom === 'front' && $publishFile === false) {
        wpfdAddonGoogleTeamDriveWaitingForApprovedFiles($result->id);
    }

    // Hold folders and files
    if (isset($result->id) && isset($file['file_path'])) {
        $uploadedFileData                = array();
        $uploadedFileData['file_id']     = $result->id;
        $uploadedFileData['file_path']   = isset($file['file_path'])? $file['file_path'] : '' ;
        $uploadedFileData['category_id'] = $id_category;

        update_option('_wpfd_google_team_drive_upload_folders_and_files_' . $result->id, $uploadedFileData);
    }

    add_filter('wpfd_addon_google_team_drive_uploaded_result', function () use ($result) {
        return $result;
    });

    /**
     * Action fire after google team drive file uploaded
     *
     * @param integer|object|mixed The file ID on success.
     * @param integer|object|mixed The term ID.
     * @param integer|object|mixed The source category file.
     *
     * @return void
     *
     * @ignore Hook already documented
     */
    do_action('wpfd_file_uploaded', $result->id, $id_category, array('source' => 'googleTeamDrive'));
}

/**
 * Publish google files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonGoolePublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $google          = Model::getInstance('cloudfiles');
    $pulishedFileIds = array();
    foreach ($fileIds as $fileId) {
        // Publish google files
        $googleFileInfo = $google->getFileInfo($fileId, $categoryId);
        if (!empty($googleFileInfo)) {
            $googleFileInfo['state'] = '1';
            $google->saveFileInfo($googleFileInfo);
        }

        $pulishedFileIds[] = $fileId;
    }

    if (!empty($pulishedFileIds)) {
        wp_send_json(array('success' => true, 'data' => $pulishedFileIds));
    } else {
        wp_send_json(array('success' => false, 'data' => array()));
    }
}

/**
 * Publish selected google team drive files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception          Fire if errors
 *
 * @return void
 */
function wpfdAddonGoogleTeamDrivePublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $googleTeamDrive  = Model::getInstance('cloudteamdrivefiles');
    $publishedFileIds = array();
    foreach ($fileIds as $fileId) {
        // Publish google team drive files
        $googleTeamDriveFileInfo = $googleTeamDrive->getFileInfo($fileId, $categoryId);
        if (!empty($googleTeamDriveFileInfo)) {
            $googleTeamDriveFileInfo['state'] = '1';
            $googleTeamDrive->saveFileInfo($googleTeamDriveFileInfo);
        }

        $publishedFileIds[] = $fileId;
    }

    if (!empty($publishedFileIds)) {
        wp_send_json(array('success' => true, 'data' => $publishedFileIds));
    } else {
        wp_send_json(array('success' => false, 'data' => array()));
    }
}

/**
 * Unpublish google files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonGoogleUnpublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $google            = Model::getInstance('cloudfiles');
    $unpulishedFileIds = array();
    foreach ($fileIds as $fileId) {
        // Unpublish google files
        $googleFileInfo = $google->getFileInfo($fileId, $categoryId);
        if (!empty($googleFileInfo)) {
            $googleFileInfo['state'] = '0';
            $google->saveFileInfo($googleFileInfo);
        }

        $unpulishedFileIds[] = $fileId;
    }

    if (!empty($unpulishedFileIds)) {
        wp_send_json(array('success' => true, 'data' => $unpulishedFileIds));
    } else {
        wp_send_json(array('success' => false, 'data' => array()));
    }
}

/**
 * Unpublished selected google team drive files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception          Fire if errors
 *
 * @return void
 */
function wpfdAddonGoogleTeamDriveUnpublishedFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $googleTeamDrive   = Model::getInstance('cloudteamdrivefiles');
    $unpulishedFileIds = array();
    foreach ($fileIds as $fileId) {
        // Unpublish google team drive files
        $googleTeamDriveFileInfo = $googleTeamDrive->getFileInfo($fileId, $categoryId);
        if (!empty($googleTeamDriveFileInfo)) {
            $googleTeamDriveFileInfo['state'] = '0';
            $googleTeamDrive->saveFileInfo($googleTeamDriveFileInfo);
        }

        $unpulishedFileIds[] = $fileId;
    }

    if (!empty($unpulishedFileIds)) {
        wp_send_json(array('success' => true, 'data' => $unpulishedFileIds));
    } else {
        wp_send_json(array('success' => false, 'data' => array()));
    }
}

/**
 * Publish dropbox files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonDropboxPublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $dropFile   = Model::getInstance('dropboxfiles');
    $dropboxPulishedFileIds = array();
    foreach ($fileIds as $fileId) {
        // Publish dropbox file
        $FileInfo   = $dropFile->getDropboxFileInfo($fileId, $categoryId);
        if (!empty($FileInfo)) {
            $FileInfo['state'] = '1';
            $dropFile->saveDropboxFileInfo($FileInfo, $categoryId);
        }
        // Remove pending status
        $userUploaded = get_option('_wpfdAddon_dropbox_file_user_upload');
        if (is_array($userUploaded)) {
            if (array_key_exists($fileId, $userUploaded)) {
                unset($userUploaded[$fileId]);
                update_option('_wpfdAddon_dropbox_file_user_upload', $userUploaded);
            }
        }

        $dropboxPulishedFileIds[] = $fileId;
    }

    if (!empty($dropboxPulishedFileIds)) {
        wp_send_json(array('success' => true, 'data' => $dropboxPulishedFileIds));
    } else {
        wp_send_json(array('success' => false, 'data' => array()));
    }
}

/**
 * Unpublish dropbox files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonDropboxUnpublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $dropFile                   = Model::getInstance('dropboxfiles');
    $dropboxUnpulishedFileIds   = array();
    foreach ($fileIds as $fileId) {
        $FileInfo   = $dropFile->getDropboxFileInfo($fileId, $categoryId);
        if (!empty($FileInfo)) {
            $FileInfo['state'] = '0';
            $dropFile->saveDropboxFileInfo($FileInfo, $categoryId);
        }

        $dropboxUnpulishedFileIds[] = $fileId;
    }

    if (!empty($dropboxUnpulishedFileIds)) {
        wp_send_json(array('success' => true, 'data' => $dropboxUnpulishedFileIds));
    } else {
        wp_send_json(array('success' => false, 'data' => array()));
    }
}

/**
 * Publish onedrive files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonOnedrivePublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $onedriveFile   = Model::getInstance('onedrivefiles');
    $onedrivePulishedFileIds = array();
    foreach ($fileIds as $fileId) {
        // Publish onedrive file
        $fileInfo = $onedriveFile->getOneDriveFileInfo($fileId, $categoryId);
        if (!empty($fileInfo)) {
            $fileInfo['state'] = '1';
            $onedriveFile->saveOneDriveFileInfo($fileInfo, $categoryId);
        }

        // Remove pending status
        $userUploaded = get_option('_wpfdAddon_onedrive_file_user_upload');
        if (is_array($userUploaded)) {
            if (array_key_exists($fileId, $userUploaded)) {
                unset($userUploaded[$fileId]);
                update_option('_wpfdAddon_onedrive_file_user_upload', $userUploaded);
            }
        }

        $onedrivePulishedFileIds[] = $fileId;
    }

    if (!empty($onedrivePulishedFileIds)) {
        wp_send_json(array('success' => true, 'data' => $onedrivePulishedFileIds));
    } else {
        wp_send_json(array('success' => false, 'data' => array()));
    }
}

/**
 * Unpublish onedrive files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonOnedriveUnpublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $onedriveUnpulishedFileIds  = array();
    $onedriveFile               = Model::getInstance('onedrivefiles');
    foreach ($fileIds as $fileId) {
        // Unpublish onedrive file
        $fileInfo = $onedriveFile->getOneDriveFileInfo($fileId, $categoryId);
        if (!empty($fileInfo)) {
            $fileInfo['state'] = '0';
            $onedriveFile->saveOneDriveFileInfo($fileInfo, $categoryId);
        }

        $onedriveUnpulishedFileIds[] = $fileId;
    }

    if (!empty($onedriveUnpulishedFileIds)) {
        wp_send_json(array('success' => true, 'data' => $onedriveUnpulishedFileIds));
    } else {
        wp_send_json(array('success' => false, 'data' => array()));
    }
}

/**
 * Update Version and Description of Google File on copy
 *
 * @param string $version     Version
 * @param string $description Description
 *
 * @return void
 */
function wpfdAddonGoogleUpdateVersionDescription($version, $description)
{
    $uploadedFile = array();
    /**
     * Filter to get uploaded google drive file
     *
     * @param array|Google_Service_Drive_DriveFile
     */
    $uploadedFile = apply_filters('wpfd_addon_googledrive_uploaded_result', $uploadedFile);

    if (!($uploadedFile instanceof Google_Service_Drive_DriveFile)) {
        return;
    }

    // Update file version
    $google = new WpfdAddonGoogleDrive;
    $google->saveVersionNumber($uploadedFile->getId(), $version);
    // Update file description
    $google->updateDescription($uploadedFile->getId(), $description);
}

/**
 * Update Version and Description of Google Team Drive File on copy/cut
 *
 * @param string $version     Version
 * @param string $description Description
 *
 * @return void
 */
function wpfdAddonGoogleTeamDriveUpdateVersionDescription($version, $description)
{
    $uploadedFile = array();
    /**
     * Filter to get uploaded google team drive file
     *
     * @param array|Google_Service_Drive_DriveFile
     */
    $uploadedFile = apply_filters('wpfd_addon_google_team_drive_uploaded_result', $uploadedFile);

    if (!($uploadedFile instanceof Google_Service_Drive_DriveFile)) {
        return;
    }

    // Update file version
    $googleTeamDrive = new WpfdAddonGoogleTeamDrive();
    $googleTeamDrive->saveVersionNumber($uploadedFile->getId(), $version);

    // Update file description
    $googleTeamDrive->updateDescription($uploadedFile->getId(), $description);
}

/**
 * Update Version and Description of Dropbox on copy
 *
 * @param string  $version     Version
 * @param string  $description Description
 * @param integer $catId       Category term id
 *
 * @return void
 */
function wpfdAddonDropboxUpdateVersionDescription($version, $description, $catId)
{
    $uploadedFile = array();
    /**
     * Filter to get uploaded dropbox file params
     *
     * @param array
     */
    $uploadedFile = apply_filters('wpfd_addon_dropbox_uploaded_result', $uploadedFile);

    if (empty($uploadedFile) || !isset($uploadedFile['id'])) {
        return;
    }

    $new_file_id = $uploadedFile['id'];
    // Update file version and description
    $fileInfos = WpfdAddonHelper::getDropboxFileInfos();

    if (!empty($fileInfos)) {
        if (isset($fileInfos[$catId]) && isset($fileInfos[$catId][$new_file_id])) {
            $fileInfos[$catId][$new_file_id]['version'] = isset($version) ? $version : '';
            $fileInfos[$catId][$new_file_id]['description'] = isset($description) ? $description : '';
        } else {
            $fileInfos[$catId] = array(
                $new_file_id => array(
                    'version'     => isset($version) ? $version : '',
                    'description' => isset($description) ? $description : ''
                )
            );
        }
    }
    WpfdAddonHelper::setDropboxFileInfos($fileInfos);
}

/**
 * Update Version and Description of Onedrive on copy
 *
 * @param string  $version     Version
 * @param string  $description Description
 * @param integer $catId       Category term id
 *
 * @return void
 */
function wpfdAddonOnedriveUpdateVersionDescription($version, $description, $catId)
{
    $uploadedFile = array();
    /**
     * Filter to get uploaded dropbox file params
     *
     * @param array
     */
    $uploadedFile = apply_filters('wpfd_addon_onedrive_uploaded_result', $uploadedFile);

    if (empty($uploadedFile) || !isset($uploadedFile['id'])) {
        return;
    }

    $new_file_id = $uploadedFile['id'];
    // Update file version and description
    $fileInfos = WpfdAddonHelper::getOnedriveFileInfos();

    if (!empty($fileInfos)) {
        if (isset($fileInfos[$catId]) && isset($fileInfos[$catId][$new_file_id])) {
            $fileInfos[$catId][$new_file_id]['version'] = isset($version) ? $version : '';
            $fileInfos[$catId][$new_file_id]['description'] = isset($description) ? $description : '';
        } else {
            $fileInfos[$catId] = array(
                $new_file_id => array(
                    'version'     => isset($version) ? $version : '',
                    'description' => isset($description) ? $description : ''
                )
            );
        }
    }

    WpfdAddonHelper::setOneDriveFileInfos($fileInfos);
}

/**
 * Copy google to google category
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonCopyGoogleGoogle($id_file, $id_category)
{
    $google = new WpfdAddonGoogleDrive;
    $google->copyFile($id_file, WpfdAddonHelper::getGoogleDriveIdByTermId($id_category));
}

/**
 * Copy google team drive to google team drive category
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonCopyGoogleTeamDrives($id_file, $id_category)
{
    $googleTeamDrive = new WpfdAddonGoogleTeamDrive();
    $googleTeamDrive->copyFile($id_file, WpfdAddonHelper::getGoogleTeamDriveIdByTermId($id_category));
}

/**
 * Move file google in google drive
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonMoveGoogleGoogle($id_file, $id_category)
{
    $google = new WpfdAddonGoogleDrive;
    $google->moveFile($id_file, WpfdAddonHelper::getGoogleDriveIdByTermId($id_category));
}

/**
 * Move file in google team drive
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonMoveGoogleTeamDrives($id_file, $id_category)
{
    $googleTeamDrive = new WpfdAddonGoogleTeamDrive();
    $googleTeamDrive->moveFile($id_file, WpfdAddonHelper::getGoogleTeamDriveIdByTermId($id_category));
}

/**
 * Download dropbox info
 *
 * @param string $id_file File id
 *
 * @return array
 */
function wpfdAddonDownloadDropboxInfo($id_file)
{
    $dropbox = new WpfdAddonDropbox();
    return $dropbox->downloadDropbox($id_file);
}

/**
 * Upload dropbox file
 *
 * @param array   $file         File info
 * @param string  $file_current File path
 * @param integer $id_category  Term id
 *
 * @throws Exception            Fire if errors
 *
 * @return void
 */
function wpfdAddonUploadDropboxFile($file, $file_current, $id_category)
{
    $dropbox = new WpfdAddonDropbox();

    $result = $dropbox->uploadFile(
        $file['title'] . '.' . $file['ext'],
        $file_current,
        filesize($file_current),
        WpfdAddonHelper::getDropboxPathByTermId($id_category)
    );

    // Fix conflict file name for dropbox
    $i = 1;
    while ($result === false) {
        $newFileTitle = $file['title'] . ' (' . $i . ')';
        $result = $dropbox->uploadFile(
            $newFileTitle . '.' . $file['ext'],
            $file_current,
            filesize($file_current),
            WpfdAddonHelper::getDropboxPathByTermId($id_category)
        );
        $i++;
    }

    if (!function_exists('wpfd_user_is_owner_of_category')
        || !function_exists('wpfd_can_edit_own_category')) {
        $app = Application::getInstance('Wpfd');
        $path_wpfdfunctions = $app->getPath() . DIRECTORY_SEPARATOR . 'functions.php';
        require_once $path_wpfdfunctions;
    }

    $uploadFrom  = Utilities::getInput('upload_from') ? Utilities::getInput('upload_from') : '';
    $publishFile = false;

    if ((int)get_current_user_id() !== 0) {
        if (wpfd_can_edit_category()) {
            $publishFile = true;
        }

        if (wpfd_can_edit_own_category()) {
            Application::getInstance('Wpfd');
            $categoryModel   = Model::getInstance('category');
            $categoryDropbox = $categoryModel->getCategory($id_category);
            if (wpfd_user_is_owner_of_category($categoryDropbox)) {
                $dropboxCategoryInfo = get_term($id_category, 'wpfd-category');
                if (!empty($dropboxCategoryInfo) && !is_wp_error($dropboxCategoryInfo)) {
                    if ($dropboxCategoryInfo->description !== 'null' && $dropboxCategoryInfo->description !== '') {
                        $cateParams = json_decode($dropboxCategoryInfo->description, true);
                        if (isset($cateParams['category_own'])
                            && (int)$cateParams['category_own'] === (int)$categoryDropbox->params['category_own']) {
                            $publishFile = true;
                        }
                    }
                }
            }
        }
    }

    if ($result !== false && isset($result['id']) && $uploadFrom === 'front' && $publishFile === false) {
        wpfdAddonDropboxWaitingForApprovedFiles($result['id']);
    }

    // Hold upload folders and files
    if ($result !== false && isset($result['id'])) {
        $uploadedFileData                = array();
        $uploadedFileData['file_id']     = $result['id'];
        $uploadedFileData['file_path']   = isset($file['file_path'])? $file['file_path'] : '' ;
        $uploadedFileData['category_id'] = $id_category;

        update_option('_wpfd_dropbox_upload_folders_and_files_' . $result['id'], $uploadedFileData);
    }

    add_filter('wpfd_addon_dropbox_uploaded_result', function () use ($result) {
        return $result;
    });

    /**
     * Action fire after file uploaded
     *
     * @param integer|object|mixed The file ID on success.
     * @param integer|object|mixed The term ID.
     * @param integer|object|mixed The source category file.
     *
     * @return void
     *
     * @ignore Hook already documented
     */
    do_action('wpfd_file_uploaded', $result['id'], $id_category, array('source' => 'dropbox'));
}

/**
 * Copy file in dropbox
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term Id
 *
 * @return void
 */
function wpfdAddonCopyDropboxDropbox($id_file, $id_category)
{
    $dropbox = new WpfdAddonDropbox();
    $file = $dropbox->getDropboxFile($id_file);
    $newFileName = strtolower($file['name']);
    $result = false;
    $i = 1;
    // Fix conflict file name for dropbox
    while (!$result) {
        $result = $dropbox->copyFileDropbox(
            $file['path_lower'],
            WpfdAddonHelper::getDropboxPathByTermId($id_category) . '/' . $newFileName
        );
        if (false === $result) {
            $newFileName = '(' . $i . ')' . strtolower($file['name']);
            $i++;
        }
    }

    add_filter('wpfd_addon_dropbox_uploaded_result', function () use ($result) {
        return $result;
    });
}

/**
 * Move file in dropbox
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonMoveDropboxDropbox($id_file, $id_category)
{
    $dropbox = new WpfdAddonDropbox();
    $file = $dropbox->getDropboxFile($id_file);
    $dropbox->moveFile(
        $file['path_lower'],
        WpfdAddonHelper::getDropboxPathByTermId($id_category) . '/' . strtolower($file['name'])
    );
}


/**
 * Display One Drive tap content
 *
 * @return void
 */
function wpfdOneDriveAddon_menu()
{
    add_submenu_page(
        'options.php',
        'WPFD OneDrive Addon page',
        'WPFD OneDrive Addon Submenu Page',
        'manage_options',
        'wpfdAddon-onedrive',
        'wpfdAddon_call_controller'
    );
}

/**
 * OneDrive configuration content
 *
 * @return void
 */
function displayOneDriveConfigurationContent()
{
    $data = getOneDriveData();
    $oneDriveConfigform = new Form();
    $formPath = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
    $formPath .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR;
    $formFile = $formPath . 'onedrive_config.xml';

    echo '<div id="wpfd-theme-onedrive" class="">';
    echo '<div id="wpfd-btnconnect-onedrive">';
    do_action('cloudconnector_display_onedrive_settings');
    showButtonConnectToOneDrive();
    echo '</div>';
    if ($oneDriveConfigform->load($formFile, $data)) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form render we can not use esc here
        echo $oneDriveConfigform->render();
    }
    echo '</div><div style="clear:both">&nbsp;</div>';
}

/**
 * Get OneDrive configs
 *
 * @return array
 */
function getOneDriveData()
{
    return WpfdAddonHelper::getAllOneDriveConfigs();
}

/**
 *  Display OneDrive taps
 *
 * @return void
 */
function displayOneDriveConfigurationTab()
{
    echo '<a id="tab-onedrive" class="nav-tab" data-tab-id="onedrive" href="#onedrive">'
        . esc_html__('One Drive', 'wpfdAddon')
        . '</a>';
}

/**
 * Show button connect OneDrive if isset App key && App Secret
 *
 * @return void
 */
function showButtonConnectToOneDrive()
{
    $app = Application::getInstance('WpfdAddon');
    $path_wpfdaddononedrive = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
    $path_wpfdaddononedrive .= DIRECTORY_SEPARATOR . 'WpfdAddonOneDrive.php';
    require_once $path_wpfdaddononedrive;
    $onedrive = new WpfdAddonOneDrive();
    if (hasOneDriveButton()) {
        if (!checkConnectOnedrive()) {
            $url = $onedrive->getAuthorisationUrl();
            ?>
            <a id="onedrive_connect" class="ju-button" href="#"
               onclick="window.open('<?php echo esc_url($url); ?>','foo','width=600,height=600');return false;">
                <img style="vertical-align: middle;"
                     src="<?php echo esc_url(plugins_url('assets/images/onedrive_white.png', __FILE__)); ?>" alt=""/>
                <?php esc_html_e('Connect OneDrive', 'wpfdAddon') ?></a>
            <?php
        } else { ?>
            <a id="onedrive_disconnect" class="ju-button" href="admin.php?page=wpfdAddon-onedrive&task=onedrive.logout">
                <img style="vertical-align: middle;"
                     src="<?php echo esc_url(plugins_url('assets/images/onedrive.png', __FILE__)); ?>" alt=""/>
                <?php esc_html_e('Disconnect OneDrive', 'wpfdAddon') ?></a>
            <?php
        }
    } ?>
    <div id="onedriver_setup">
        <div class="ju-settings-option full-width">
            <label class="ju-setting-label" for=""><?php esc_html_e('Redirect URIs Server', 'wpfdAddon') ?></label>

            <input title="" class="ju-input ju-large-text" style="width:100%" type="text" readonly="true"
                   value="<?php echo esc_url(get_admin_url()); ?>admin.php"/>
        </div>
        <div class="ju-settings-option full-width">
            <label class="ju-setting-label" for=""><?php esc_html_e('Redirect URIs', 'wpfdAddon') ?></label>

            <?php
            $url_onedriveAuth = '/wp-admin/admin.php?page=wpfdAddon-onedrive&task=onedrive.authenticate';
            ?>
            <input title="" class="ju-input ju-large-text" style="width:100%" type="text" readonly="true"
                   value="<?php echo esc_url(site_url() . $url_onedriveAuth); ?>"/>
        </div>
        <a class="ju-button orange-outline-button ju-button-inline onedrive-documentation" href="https://www.joomunited.com/wordpress-documentation/wp-file-download/298-wp-file-download-addon-onedrive-onedrive-business-integration"
           target="_blank"><?php esc_html_e('Read the online support documentation', 'wpfdAddon') ?></span></a>

    </div>
    <?php
}

/**
 * Display button connect to cloud when exist cloud config data
 *
 * @return boolean
 */
function hasOneDriveButton()
{
    $dataOneDrive = WpfdAddonHelper::getDataConfigByOneDrive('onedrive');
    $displayButton = true;
    if (isset($dataOneDrive) && (!empty($dataOneDrive))) {
        if ((empty($dataOneDrive['onedriveKey'])) || (empty($dataOneDrive['onedriveSecret']))) {
            $displayButton = false;
        }
    } else {
        $displayButton = false;
    }
    return $displayButton;
}

/**
 * Check connect onedrive
 *
 * @return boolean
 */
function checkConnectOnedrive()
{
    $dataOneDrive = WpfdAddonHelper::getAllOneDriveConfigs();
    $displayButton = true;
    if (isset($dataOneDrive) && (!empty($dataOneDrive))) {
        if ((empty($dataOneDrive['onedriveConnected'])) || (empty($dataOneDrive['onedriveConnected']))) {
            $displayButton = false;
        }
    } else {
        $displayButton = false;
    }
    return $displayButton;
}


/**
 * Display drop down create onedrive
 *
 * @return void
 */
function displayDropDownCreateOneDrive()
{
    $data = getOneDriveData();
    $onedriveConnected = isset($data['onedriveConnected']) ? $data['onedriveConnected'] : 0;
    if ($onedriveConnected) {
        echo '<li><a href="#" class="button onedriveCreate">
                <span class="onedrive-icon wpfd-folder wpfd-liga" style="line-height: inherit;">onedrive</span> '
            . esc_html__('OneDrive', 'wpfdAddon') . '</a>
              </li>';
    }
}

/**
 * OneDrive category callBack
 *
 * @return void
 */
function wpfdAddonAddOneDriveCategory_callBack()
{
    wpfdAddon_call('category.addOneDriveCategory');
}

/**
 * Delete OneDrive category callback
 *
 * @return void
 */
function wpfdAddonDeleteOneDriveCategory_callback()
{
    wpfdAddon_call('category.deleteOneDriveCategory');
}

/**
 * Duplicate OneDrive category callback
 *
 * @return void
 */
function wpfdAddonDuplicateOneDriveCategory_callBack()
{
    wpfdAddon_call('category.duplicateOneDriveCategory');
}

/**
 * Set category oneDrive title
 *
 * @param integer $termId Term id
 * @param string  $title  Title
 *
 * @return boolean true if success
 *                 false if error
 */
function wpfdAddonSetCategoryOneDriveTitle($termId, $title)
{
    Application::getInstance('WpfdAddon');
    $onedriveCategory = Model::getInstance('onedrivecategory');
    return $onedriveCategory->changeCategoryName(WpfdAddonHelper::getOneDriveIdByTermId($termId), $title);
}

/**
 * OneDrive sync
 *
 * @return void
 */
function wpfdAddonOneDriveSync()
{
    $onedriveConfig = WpfdAddonHelper::getAllOneDriveConfigs();
    $sync_time = (int)$onedriveConfig['onedriveSyncTime'];
    $sync_method = (string) $onedriveConfig['onedriveSyncMethod'];

    // Check if onedrive is connected
    if (isset($onedriveConfig['onedriveConnected']) && $onedriveConfig['onedriveConnected'] === 1) {
        if ($sync_time > 0 && $sync_method !== 'setup_on_server') {
            $curSyncInterval = WpfdAddonHelper::curSyncIntervalOneDrive();
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl') {
                wp_remote_get(
                    admin_url('admin-ajax.php') . '?action=onedriveSync',
                    array(
                        'timeout' => 1,
                        'blocking' => false,
                        'sslverify' => false,
                    )
                );
            }
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl_ajax') {
                wpfdAddonOneDriveSync_callback();
            }
        }
    }
}


/**
 * OneDrive Sync callback
 *
 * @return void
 */
function wpfdAddonOneDriveSync_callback()
{
    wpfdAddon_call('onedrive.onedrivesync');
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
 * Save OneDrive file Info
 *
 * @param array   $data       File data
 * @param integer $idCategory Term id
 *
 * @return string File id
 */
function wpfdAddonSaveOneDriveFileInfo($data, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $onedriveFile = Model::getInstance('onedrivefiles');
    return $onedriveFile->saveOneDriveFileInfo($data, $idCategory);
}

/**
 * Get OneDrive file info
 *
 * @param string  $idFile     File id
 * @param integer $idCategory Term id
 *
 * @return boolean|array array File if success false if error
 */
function wpfdAddonOneDriveGetFileInfo($idFile, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $onedriveFile = Model::getInstance('onedrivefiles');
    /* @var $onedriveFile wpfdAddonModelOnedriveFiles */
    return $onedriveFile->getOneDriveFileInfo($idFile, $idCategory);
}

/**
 * OneDrive upload file
 *
 * @param integer $termId Term id
 * @param array   $file   File data
 *
 * @return array
 */
function wpfdAddonOneDriveUpload($termId, $file)
{
    Application::getInstance('WpfdAddon');
    $onedriveFile = Model::getInstance('onedrivefiles');
    return $onedriveFile->oneDriveUpload($termId, $file);
}

/**
 * Delete file from folder
 *
 * @param integer $idCategory Term id
 * @param string  $idFile     File id
 *
 * @return boolean true if success
 *                 false on error
 */
function wpfdAddonDeleteOneDriveFiles($idCategory, $idFile)
{
    Application::getInstance('WpfdAddon');
    $onedriveFile = Model::getInstance('onedrivefiles');
    $del = $onedriveFile->deleteOneDriveFile($idCategory, $idFile);

    if ($del === true) {
        $onedriveFileInfos = get_option('_wpfdAddon_onedrive_fileInfo');
        if (is_array($onedriveFileInfos) && !empty($onedriveFileInfos) && isset($onedriveFileInfos[$idCategory][$idFile])) {
            unset($onedriveFileInfos[$idCategory][$idFile]);
            update_option('_wpfdAddon_onedrive_fileInfo', $onedriveFileInfos);
        }
    }

    return $del;
}

/**
 * Download OneDrive info
 *
 * @param string $id_file File id
 *
 * @return boolean|stdClass|WP_Error false on response code other than 200
 *                             stdClass File info when success
 *                             WP_Error on Exception
 */
function wpfdAddonDownloadOneDriveInfo($id_file)
{
    $onedriveFile = new WpfdAddonOneDrive();
    return $onedriveFile->downloadFile($id_file);
}

/**
 * Upload OneDrive file
 *
 * @param array   $file         File info
 * @param string  $file_current File path
 * @param integer $id_category  Term id
 *
 * @return void
 */
function wpfdAddonUploadOneDriveFile($file, $file_current, $id_category)
{
    $onedrive = new WpfdAddonOneDrive();
    $pic = array();
    $pic['error'] = 0;
    $pic['name'] = $file['title'] . '.' . $file['ext'];
    $pic['type'] = '';
    $pic['size'] = $file['size'];

    $result = $onedrive->uploadFile(
        $file['title'] . '.' . $file['ext'],
        $pic,
        $file_current,
        WpfdAddonHelper::getOneDriveIdByTermId($id_category)
    );

    // Add pending uploaded file
    if (!function_exists('wpfd_can_edit_own_category')
        || !function_exists('wpfd_user_is_owner_of_category')) {
        $app = Application::getInstance('Wpfd');
        $path_wpfdfunctions = $app->getPath() . DIRECTORY_SEPARATOR . 'functions.php';
        require_once $path_wpfdfunctions;
    }

    $uploadFrom  = Utilities::getInput('upload_from') ? Utilities::getInput('upload_from') : '';
    $publishFile = false;

    if ((int)get_current_user_id() !== 0) {
        if (wpfd_can_edit_category()) {
            $publishFile = true;
        }

        if (wpfd_can_edit_own_category()) {
            Application::getInstance('Wpfd');
            $categoryModel      = Model::getInstance('category');
            $categoryOnedrive   = $categoryModel->getCategory($id_category);
            if (wpfd_user_is_owner_of_category($categoryOnedrive)) {
                $onedriveCategoryInfo = get_term($id_category, 'wpfd-category');
                if (!empty($onedriveCategoryInfo) && !is_wp_error($onedriveCategoryInfo)) {
                    if ($onedriveCategoryInfo->description !== 'null' && $onedriveCategoryInfo->description !== '') {
                        $cateParams = json_decode($onedriveCategoryInfo->description, true);
                        if (isset($cateParams['category_own'])
                            && (int)$cateParams['category_own'] === (int)$categoryOnedrive->params['category_own']) {
                            $publishFile = true;
                        }
                    }
                }
            }
        }
    }

    // Hold upload folders and files
    if (isset($result['file']['id'])) {
        $uploadedFileData                = array();
        $uploadedFileData['file_id']     = $result['file']['id'];
        if (isset($file['file_path'])) {
            $uploadedFileData['file_path']   = $file['file_path'];
        }
        $uploadedFileData['category_id'] = $id_category;

        update_option('_wpfd_onedrive_upload_folders_and_files_' . $result['file']['id'], $uploadedFileData);
    }

    if (isset($result['file']['id']) && $uploadFrom === 'front' && $publishFile === false) {
        wpfdAddonOnedriveWaitingForApprovedFiles($result['file']['id']);
    }

    add_filter('wpfd_addon_onedrive_uploaded_result', function () use ($result) {
        return $result['file'];
    });

    /**
     * Action fire after file uploaded
     *
     * @param integer|object|mixed The file ID on success.
     * @param integer|object|mixed The term ID.
     * @param integer|object|mixed The source category file.
     *
     * @return void
     *
     * @ignore Hook already documented
     */
    do_action('wpfd_file_uploaded', $result['file']['id'], $id_category, array('source' => 'onedrive'));
}

/**
 * Copy onedrive
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonCopyOneDrive($id_file, $id_category)
{
    $oneDrive = new WpfdAddonOneDrive();
    $result = $oneDrive->copyFile($id_file, WpfdAddonHelper::getOneDriveIdByTermId($id_category));

    add_filter('wpfd_addon_onedrive_uploaded_result', function () use ($result) {
        return $result;
    });
}

/**
 * Move file onedrive
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonMoveFileOneDriver($id_file, $id_category)
{
    $oneDrive      = new WpfdAddonOneDrive();
    $newCategoryId = WpfdAddonHelper::getOneDriveIdByTermId($id_category);
    $oldCategoryId = WpfdAddonHelper::getOneDriveTermIdByCloudId($id_file);
    $success       = $oneDrive->moveFile($id_file, $newCategoryId);
    if (!is_wp_error($success)) {
        // Move File params to new category
        $fileInfos = WpfdAddonHelper::getOneDriveFileInfos();
        $newFileInfos = array();
        foreach ($fileInfos as $catId => $fileInfo) {
            if ((int) $catId === (int) $oldCategoryId) {
                foreach ($fileInfo as $fileId => $fileParam) {
                    if ((string) $fileId === (string) $id_file) {
                        $newFileInfos[$id_category][$fileId] = $fileParam;
                    } else {
                        $newFileInfos[$catId][$fileId] = $fileParam;
                    }
                }
            } else {
                $newFileInfos[$catId] = $fileInfo;
            }
        }
        WpfdAddonHelper::setOneDriveFileInfos($newFileInfos);
    }
}

/**
 * OndDrive change order
 *
 * @param integer $pk Term move order
 *
 * @return void
 */
function wpfdAddonOneDriveChangeOrder($pk)
{
    Application::getInstance('WpfdAddon');
    $OnedriveCat = Model::getInstance('onedrivecategory');
    $OnedriveCat->changeOrder($pk);
}

/**
 * Uplaod onedrive version
 *
 * @param array   $data   File data
 * @param integer $termId Term id
 *
 * @return boolean
 */
function wpfdAddonUploadOneDriveVersion($data, $termId)
{
    Application::getInstance('WpfdAddon');
    $dropFile = Model::getInstance('onedrivefiles');
    return $dropFile->uploadOneDriveVersion($data, $termId);
}

/**
 * Set pending status to google uploaded files
 *
 * @param integer|string $fileId File id
 *
 * @throws Exception                    Fire message if errors
 *
 * @return void
 */
function wpfdAddonGoogleWaitingForApprovedFiles($fileId)
{
    // Save values to unpublish file
    $pendingFiles            = get_option('_wpfdAddon_upload_pending_files') ? get_option('_wpfdAddon_upload_pending_files') : array();
    $pendingFiles[$fileId]   = get_current_user_id();
    update_option('_wpfdAddon_upload_pending_files', $pendingFiles, false);
}

/**
 * Set pending status to google team drive uploaded files
 *
 * @param integer|string $fileId File id
 *
 * @throws Exception    Fire message if errors
 *
 * @return void
 */
function wpfdAddonGoogleTeamDriveWaitingForApprovedFiles($fileId)
{
    // Save none published file
    $pendingFiles          = get_option('_wpfdAddon_upload_pending_files') ? get_option('_wpfdAddon_upload_pending_files') : array();
    $pendingFiles[$fileId] = get_current_user_id();
    update_option('_wpfdAddon_upload_pending_files', $pendingFiles, false);
}

/**
 * Set pending status to uploaded files
 *
 * @param object $fileId File id
 *
 * @throws Exception            Fire message if errors
 *
 * @return void
 */
function wpfdAddonDropboxWaitingForApprovedFiles($fileId)
{
    // Save values to unpublish file
    $pendingFiles            = get_option('_wpfdAddon_upload_pending_files') ? get_option('_wpfdAddon_upload_pending_files') : array();
    $pendingFiles[$fileId]   = get_current_user_id();
    update_option('_wpfdAddon_upload_pending_files', $pendingFiles, false);

    // Add pending status
    $uploadedUsers          = get_option('_wpfdAddon_dropbox_file_user_upload') ? get_option('_wpfdAddon_dropbox_file_user_upload') : array();
    $uploadedUsers[$fileId] = get_current_user_id();
    update_option('_wpfdAddon_dropbox_file_user_upload', $uploadedUsers, false);
}

/**
 * Set pending status to Onedrive uploaded files
 *
 * @param object $fileId File id
 *
 * @throws Exception         Fire message if errors
 *
 * @return void
 */
function wpfdAddonOnedriveWaitingForApprovedFiles($fileId)
{
    // Save values to unpublish file
    $pendingFiles            = get_option('_wpfdAddon_upload_pending_files') ? get_option('_wpfdAddon_upload_pending_files') : array();
    $pendingFiles[$fileId]   = get_current_user_id();
    update_option('_wpfdAddon_upload_pending_files', $pendingFiles, false);
}

/**
 * WpfdAddonGooglePendingUploadFiles
 *
 * @param integer|string $categoryId   Category id
 * @param array          $pendingFiles Upload file list
 *
 * @throws Exception                        Fire message if errors
 *
 * @return void
 */
function wpfdAddonGooglePendingUploadFiles($categoryId, $pendingFiles)
{
    Application::getInstance('WpfdAddon');
    $google         = Model::getInstance('cloudfiles');
    $uploadedUsers  = get_option('_wpfdAddon_google_file_user_upload') ? get_option('_wpfdAddon_google_file_user_upload') : array();
    if (!empty($pendingFiles)) {
        foreach ($pendingFiles as $key => $value) {
            $fileId = $key;
            $googleFileInfo = $google->getFileInfo($fileId, $categoryId);
            if (!empty($googleFileInfo)) {
                $googleFileInfo['state'] = '0';
                $google->saveFileInfo($googleFileInfo);
                $uploadedUsers[$fileId] = get_current_user_id();
            }
        }

        delete_option('_wpfdAddon_upload_pending_files');
        update_option('_wpfdAddon_google_file_user_upload', $uploadedUsers, false);
    }

    wp_send_json(array('success' => true, 'data' => $pendingFiles));
}

/**
 * WpfdAddonDropboxPendingUploadFiles
 *
 * @param integer|string $categoryId   Category id
 * @param array          $pendingFiles Upload file list
 *
 * @throws Exception                        Fire message if errors
 *
 * @return void
 */
function wpfdAddonDropboxPendingUploadFiles($categoryId, $pendingFiles)
{
    Application::getInstance('WpfdAddon');
    $dropFile   = Model::getInstance('dropboxfiles');
    if (!empty($pendingFiles)) {
        foreach ($pendingFiles as $key => $value) {
            $fileId     = $key;
            $FileInfo   = $dropFile->getDropboxFileInfo($fileId, $categoryId);
            if (!empty($FileInfo)) {
                $FileInfo['state'] = '0';
                $dropFile->saveDropboxFileInfo($FileInfo, $categoryId);
            }
        }

        delete_option('_wpfdAddon_upload_pending_files');
    }

    wp_send_json(array('success' => true, 'data' => $pendingFiles));
}

/**
 * WpfdAddonOnedrivePendingUploadFiles
 *
 * @param integer|string $categoryId   Category id
 * @param array          $pendingFiles Upload file list
 *
 * @throws Exception                        Fire message if errors
 *
 * @return void
 */
function wpfdAddonOnedrivePendingUploadFiles($categoryId, $pendingFiles)
{
    Application::getInstance('WpfdAddon');
    $onedriveFile   = Model::getInstance('onedrivefiles');
    $uploadedUsers  = get_option('_wpfdAddon_onedrive_file_user_upload') ? get_option('_wpfdAddon_onedrive_file_user_upload') : array();
    if (!empty($pendingFiles)) {
        foreach ($pendingFiles as $key => $value) {
            $fileId     = $key;
            $fileInfo   = $onedriveFile->getOneDriveFileInfo($fileId, $categoryId);
            if (!empty($fileInfo)) {
                $fileInfo['state'] = '0';
                $onedriveFile->saveOneDriveFileInfo($fileInfo, $categoryId);
            }
            $uploadedUsers[$fileId] = get_current_user_id();
        }

        delete_option('_wpfdAddon_upload_pending_files');
        update_option('_wpfdAddon_onedrive_file_user_upload', $uploadedUsers, false);
    }

    wp_send_json(array('success' => true, 'data' => $pendingFiles));
}

/**
 * WpfdAddonCountVersions
 *
 * @param integer|string $count        Number of count
 * @param mixed          $fileId       File id
 * @param mixed          $categoryFrom Cloud category
 *
 * @throws Exception                    Fire message if errors
 *
 * @return void|mixed
 */
function wpfdAddonCountVersions($count, $fileId, $categoryFrom)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            $google = new WpfdAddonGoogleDrive();

            return $google->getVersionsCount($fileId);
        case 'dropbox':
            $dropbox = new WpfdAddonDropbox();

            return $dropbox->getVersionsCount($fileId);
        case 'onedrive':
            $onedrive = new WpfdAddonOneDrive();

            return $onedrive->getVersionsCount($fileId);
        case 'onedrive_business':
            $onedriveBusiness = new WpfdAddonOneDriveBusiness();

            return $onedriveBusiness->getVersionsCount($fileId);
        case 'aws':
            $aws = new WpfdAddonAws();

            return $aws->getVersionsCount($fileId);
        default:
            return 0;
    }
}
add_filter('wpfd_addon_count_version', 'wpfdAddonCountVersions', 10, 3);

/**
 * WpfdAddonSaveLatestVersionIncrementNumber
 *
 * @param integer|string $latestVersionNumber Number of latest version
 * @param mixed          $file                File id
 * @param mixed          $categoryFrom        Cloud category
 *
 * @throws Exception                          Fire message if errors
 *
 * @return void|mixed
 */
function wpfdAddonSaveLatestVersionIncrementNumber($latestVersionNumber, $file, $categoryFrom)
{
    switch ($categoryFrom) {
        case 'googleDrive':
            $google = new WpfdAddonGoogleDrive();

            return $google->saveLatestVersionNumber($file, $latestVersionNumber);
        case 'dropbox':
            $dropbox = new WpfdAddonDropbox();

            return $dropbox->saveLatestVersionNumber($file, $latestVersionNumber);
        case 'onedrive':
            $onedrive = new WpfdAddonOnedrive();

            return $onedrive->saveLatestVersionNumber($file, $latestVersionNumber);
        case 'onedrive_business':
            $onedriveBusiness = new WpfdAddonOneDriveBusiness();

            return $onedriveBusiness->saveLatestVersionNumber($file, $latestVersionNumber);
        case 'aws':
            $aws = new WpfdAddonAws();

            return $aws->saveLatestVersionNumber($file, $latestVersionNumber);
        default:
            return false;
    }
}
add_action('wpfd_addon_save_latest_automatic_revision_increment_number', 'wpfdAddonSaveLatestVersionIncrementNumber', 10, 3);

/**
 * WpfdAddonUploadGoogleFoldersAndFiles
 *
 * @param string|integer $id_Category Category id
 *
 * @throws Exception                  Fire message if errors
 *
 * @return void|mixed
 */
function wpfdAddonUploadGoogleFoldersAndFiles($id_Category)
{
    $app = Application::getInstance('WpfdAddon');
    $cloudCategory = Model::getInstance('cloudcategory');
    $google = new WpfdAddonGoogleDrive();
    $path_wpfda_model = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'models';
    require_once $path_wpfda_model . DIRECTORY_SEPARATOR . 'cloudcategory.php';
    if (!$cloudCategory) {
        $cloudCategory = new WpfdAddonModelCloudcategory();
    }
    $newCategoryId      = WpfdAddonHelper::getGoogleDriveIdByTermId($id_Category);
    $googleFiles        = $google->listFiles($newCategoryId, 'ordering', 'asc');
    $uploads            = array();
    $createdCategories  = array();
    $processedDirects   = array();
    $processedIds       = array();

    $configModel = Model::getInstance('config');
    if (!$configModel) {
        $position = 'end';
    }

    $config = $configModel->getConfig();
    $position = isset($config['new_category_position']) ? $config['new_category_position'] : 'end';

    if (!empty($googleFiles)) {
        foreach ($googleFiles as $googleFile) {
            $fileId = $googleFile->id;
            $fileUploadInfos = get_option('_wpfd_google_upload_folders_and_files_' . $fileId, array());
            if (!empty($fileUploadInfos)) {
                $newFile    = new stdClass;
                $newFile->file_id = $fileId;
                $newFile->file_title = $googleFile->title;
                $newFile->file_type = $googleFile->ext;
                $newFile->file_path = $fileUploadInfos['file_path'];
                $uploads[$newCategoryId][] = $newFile;

                // Remove unused data
                delete_option('_wpfd_google_upload_folders_and_files_' . $fileId);
            }
        }
    }

    if (!empty($uploads)) {
        foreach ($uploads as $cateId => $fileInfos) {
            foreach ($fileInfos as $fileKey => $fileInfo) {
                $processedDirect = '';

                if (!is_object($fileInfo)) {
                    continue;
                }

                $filePath = $fileInfo->file_path;
                $separatePath = explode('/', $filePath);
                $count = count($separatePath);

                if ($count === 1) {
                    continue;
                }

                // Insert google categories
                foreach ($separatePath as $key => $direct) {
                    if ($key === $count - 1) {
                        continue;
                    }

                    $processedDirect = $processedDirect . $direct . '/';
                    $replace = str_replace('/', '*****', $processedDirect);

                    if (array_key_exists($replace, $createdCategories)) {
                        $processedIds[$fileKey][$key] = $createdCategories[$replace]['category_id'];
                        continue;
                    }

                    $categoryName = $direct;

                    if ($key === 0) {
                        $newCateId = $cloudCategory->addCategory($categoryName, $id_Category, $position);
                        if ($newCateId) {
                            $user_id = get_current_user_id();
                            if ($user_id) {
                                $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                                if (is_array($user_categories)) {
                                    if (!in_array($newCateId, $user_categories)) {
                                        $user_categories[] = $newCateId;
                                    }
                                } else {
                                    $user_categories = array($newCateId);
                                }
                                update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                            }

                            $createdCategories[$replace] = array('category_id' => $newCateId, 'category_name' => $categoryName);
                            $processedDirects[] = $processedDirect;
                            $processedIds[$fileKey][$key] = $newCateId;
                        }
                    } else {
                        $targetCate = isset($processedIds[$fileKey][$key - 1]) ? $processedIds[$fileKey][$key - 1] : $id_Category;
                        $newCateId = $cloudCategory->addCategory($categoryName, $targetCate, $position);
                        if ($newCateId) {
                            $createdCategories[$replace] = array('category_id' => $newCateId, 'category_name' => $categoryName);
                            $processedDirects[] = $processedDirect;
                            $processedIds[$fileKey][$key] = $newCateId;
                        }
                    }
                }

                // Move google files
                if (!$fileInfo->file_id) {
                    continue;
                }

                $newFilePath = str_replace($fileInfo->file_title . '.' . $fileInfo->file_type, '', $fileInfo->file_path);
                $newFilePath = str_replace('/', '*****', $newFilePath);
                $targetCategory = isset($createdCategories[$newFilePath]['category_id']) ? $createdCategories[$newFilePath]['category_id'] : $id_Category;

                if ((int) $targetCategory === (int) $id_Category) {
                    continue;
                }

                wpfdAddonMoveGoogleGoogle($fileInfo->file_id, $targetCategory);
            }
        }
    }
}

/**
 * WpfdAddonUploadDropboxFoldersAndFiles
 *
 * @param string|integer $id_Category Category id
 *
 * @throws Exception                  Fire message if errors
 *
 * @return void|mixed
 */
function wpfdAddonUploadDropboxFoldersAndFiles($id_Category)
{
    $app                = Application::getInstance('WpfdAddon');
    $dropboxCategory    = Model::getInstance('dropboxcategory');
    $dropbox            = new WpfdAddonDropbox();
    $uploads            = array();
    $createdCategories  = array();
    $processedDirects   = array();
    $processedIds       = array();
    $newCategoryId      = WpfdAddonHelper::getDropboxPathByTermId($id_Category);
    $dropboxFiles       = $dropbox->listDropboxFiles($newCategoryId, $id_Category, 'ordering', 'asc');

    $configModel = Model::getInstance('config');
    if (!$configModel) {
        $position = 'end';
    }

    $config = $configModel->getConfig();
    $position = isset($config['new_category_position']) ? $config['new_category_position'] : 'end';

    if (!empty($dropboxFiles)) {
        foreach ($dropboxFiles as $dropboxFile) {
            $fileId = $dropboxFile->id;
            $fileUploadInfos = get_option('_wpfd_dropbox_upload_folders_and_files_' . $fileId, array());
            if (!empty($fileUploadInfos)) {
                $newFile = new stdClass;
                $newFile->file_id = $fileId;
                $newFile->file_title = $dropboxFile->title;
                $newFile->file_type = $dropboxFile->ext;
                $newFile->file_path = $fileUploadInfos['file_path'];
                $uploads[$newCategoryId][] = $newFile;

                // Remove unused data
                delete_option('_wpfd_dropbox_upload_folders_and_files_' . $fileId);
            }
        }
    }

    if (!empty($uploads)) {
        foreach ($uploads as $cateId => $fileInfos) {
            foreach ($fileInfos as $fileKey => $fileInfo) {
                $processedDirect = '';

                if (!is_object($fileInfo)) {
                    continue;
                }

                $filePath = $fileInfo->file_path;
                $separatePath = explode('/', $filePath);
                $count = count($separatePath);

                if ($count === 1) {
                    continue;
                }

                // Insert dropbox categories
                foreach ($separatePath as $key => $direct) {
                    if ($key === $count - 1) {
                        continue;
                    }

                    $processedDirect = $processedDirect . $direct . '/';
                    $replace = str_replace('/', '*****', $processedDirect);

                    if (array_key_exists($replace, $createdCategories)) {
                        $processedIds[$fileKey][$key] = $createdCategories[$replace]['category_id'];
                        continue;
                    }

                    $categoryName = $direct;

                    if ($key === 0) {
                        $newCateId = $dropboxCategory->addDropCategory($categoryName, $id_Category, $position);
                        if ($newCateId) {
                            $user_id = get_current_user_id();
                            if ($user_id) {
                                $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                                if (is_array($user_categories)) {
                                    if (!in_array($newCateId, $user_categories)) {
                                        $user_categories[] = $newCateId;
                                    }
                                } else {
                                    $user_categories = array($newCateId);
                                }
                                update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                            }
                            $createdCategories[$replace] = array('category_id' => $newCateId, 'category_name' => $categoryName);
                            $processedDirects[] = $processedDirect;
                            $processedIds[$fileKey][$key] = $newCateId;
                        }
                    } else {
                        $targetCate = isset($processedIds[$fileKey][$key - 1]) ? $processedIds[$fileKey][$key - 1] : $id_Category;
                        $newCateId = $dropboxCategory->addDropCategory($categoryName, $targetCate, $position);
                        if ($newCateId) {
                            $user_id = get_current_user_id();
                            if ($user_id) {
                                $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                                if (is_array($user_categories)) {
                                    if (!in_array($newCateId, $user_categories)) {
                                        $user_categories[] = $newCateId;
                                    }
                                } else {
                                    $user_categories = array($newCateId);
                                }
                                update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                            }
                            $createdCategories[$replace] = array('category_id' => $newCateId, 'category_name' => $categoryName);
                            $processedDirects[] = $processedDirect;
                            $processedIds[$fileKey][$key] = $newCateId;
                        }
                    }
                }

                // Move dropbox files
                if (!$fileInfo->file_id) {
                    continue;
                }

                $newFilePath = str_replace($fileInfo->file_title . '.' . $fileInfo->file_type, '', $fileInfo->file_path);
                $newFilePath = str_replace('/', '*****', $newFilePath);
                $targetCategory = isset($createdCategories[$newFilePath]['category_id']) ? $createdCategories[$newFilePath]['category_id'] : $id_Category;

                if ((int) $targetCategory === (int) $id_Category) {
                    continue;
                }

                wpfdAddonMoveDropboxDropbox($fileInfo->file_id, $targetCategory);
            }
        }
    }
}

/**
 * WpfdAddonUploadOneDriveFoldersAndFiles
 *
 * @param string|integer $id_Category Category id
 *
 * @throws Exception                  Fire message if errors
 *
 * @return void|mixed
 */
function wpfdAddonUploadOneDriveFoldersAndFiles($id_Category)
{
    $app                = Application::getInstance('WpfdAddon');
    $onedriveCategory   = Model::getInstance('onedrivecategory');
    $onedrive           = new WpfdAddonOneDrive();
    $uploads            = array();
    $createdCategories  = array();
    $processedDirects   = array();
    $processedIds       = array();
    $newCategoryId      = WpfdAddonHelper::getOneDriveIdByTermId($id_Category);
    $onedriveFiles      = $onedrive->listFiles($newCategoryId, $id_Category, 'ordering', 'asc');

    if (!empty($onedriveFiles)) {
        foreach ($onedriveFiles as $odFile) {
            $fileId = $odFile->id;
            $fileUploadInfos = get_option('_wpfd_onedrive_upload_folders_and_files_' . $fileId, array());
            if (!empty($fileUploadInfos)) {
                $newFile = new stdClass;
                $newFile->file_id = $fileId;
                $newFile->file_title = $odFile->title;
                $newFile->file_type = $odFile->ext;
                $newFile->file_path = $fileUploadInfos['file_path'];
                $uploads[$newCategoryId][] = $newFile;

                // Remove unused data
                delete_option('_wpfd_onedrive_upload_folders_and_files_' . $fileId);
            }
        }
    }

    if (!empty($uploads)) {
        foreach ($uploads as $cateId => $fileInfos) {
            foreach ($fileInfos as $fileKey => $fileInfo) {
                $processedDirect = '';

                if (!is_object($fileInfo)) {
                    continue;
                }

                $filePath = $fileInfo->file_path;
                $separatePath = explode('/', $filePath);
                $count = count($separatePath);

                if ($count === 1) {
                    continue;
                }

                // Insert onedrive categories
                foreach ($separatePath as $key => $direct) {
                    if ($key === $count - 1) {
                        continue;
                    }

                    $processedDirect = $processedDirect . $direct . '/';
                    $replace = str_replace('/', '*****', $processedDirect);

                    if (array_key_exists($replace, $createdCategories)) {
                        $processedIds[$fileKey][$key] = $createdCategories[$replace]['category_id'];
                        continue;
                    }

                    $categoryName = $direct;

                    if ($key === 0) {
                        $return = $onedriveCategory->addOneDriveCategory($categoryName, $id_Category, 'end');

                        if (is_array($return)) {
                            if ($return['success']) {
                                $user_id = get_current_user_id();
                                if ($user_id) {
                                    $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                                    if (is_array($user_categories)) {
                                        if (!in_array($return['id'], $user_categories)) {
                                            $user_categories[] = $return['id'];
                                        }
                                    } else {
                                        $user_categories = array($return['id']);
                                    }
                                    update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                                }

                                $newCateId = $return['id'];
                                $createdCategories[$replace] = array('category_id' => $newCateId, 'category_name' => $categoryName);
                                $processedDirects[] = $processedDirect;
                                $processedIds[$fileKey][$key] = $newCateId;
                            }
                        }
                    } else {
                        $targetCate = isset($processedIds[$fileKey][$key - 1]) ? $processedIds[$fileKey][$key - 1] : $id_Category;
                        $return     = $onedriveCategory->addOneDriveCategory($categoryName, $targetCate, 'end');

                        if (is_array($return)) {
                            if ($return['success']) {
                                $user_id = get_current_user_id();
                                if ($user_id) {
                                    $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                                    if (is_array($user_categories)) {
                                        if (!in_array($return['id'], $user_categories)) {
                                            $user_categories[] = $return['id'];
                                        }
                                    } else {
                                        $user_categories = array($return['id']);
                                    }
                                    update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                                }

                                $newCateId = $return['id'];
                                $createdCategories[$replace] = array('category_id' => $newCateId, 'category_name' => $categoryName);
                                $processedDirects[] = $processedDirect;
                                $processedIds[$fileKey][$key] = $newCateId;
                            }
                        }
                    }
                }

                // Move onedrive files
                if (!$fileInfo->file_id) {
                    continue;
                }

                $newFilePath = str_replace($fileInfo->file_title . '.' . $fileInfo->file_type, '', $fileInfo->file_path);
                $newFilePath = str_replace('/', '*****', $newFilePath);
                $targetCategory = isset($createdCategories[$newFilePath]['category_id']) ? $createdCategories[$newFilePath]['category_id'] : $id_Category;

                if ((int) $targetCategory === (int) $id_Category) {
                    continue;
                }

                wpfdAddonMoveFileOneDriver($fileInfo->file_id, $targetCategory);
            }
        }
    }
}

/**
 * Display AWS3 tap content
 *
 * @return void
 */
function wpfdAwsAddon_menu()
{
    add_submenu_page(
        'options.php',
        'WPFD AWS Addon page',
        'WPFD AWS Addon Submenu Page',
        'manage_options',
        'wpfdAddon-aws',
        'wpfdAddon_call_controller'
    );
}

/**
 * Aws category callBack
 *
 * @return void
 */
function wpfdAddonAddAwsCategory_callBack()
{
    wpfdAddon_call('category.addAwsCategory');
}

/**
 * Delete Aws category callback
 *
 * @return void
 */
function wpfdAddonDeleteAwsCategory_callback()
{
    wpfdAddon_call('category.deleteAwsCategory');
}

/**
 * Duplicate Aws category callback
 *
 * @return void
 */
function wpfdAddonDuplicateAwsCategory_callBack()
{
    wpfdAddon_call('category.duplicateAwsCategory');
}

/**
 * Delete Google Team Drive category callback
 *
 * @return void
 */
function wpfdAddonDeleteGoogleTeamDriveCategory_callback()
{
    wpfdAddon_call('category.deleteGoogleTeamDriveCategory');
}

/**
 * Duplicate Google Team Drive categories structure
 *
 * @return void
 */
function wpfdAddonDuplicateGoogleTeamDriveCategory_callBack()
{
    wpfdAddon_call('category.duplicateGoogleTeamDriveCategory');
}

/**
 * Add Google Team Drive category callBack
 *
 * @return void
 */
function wpfdAddonAddGoogleTeamDriveCategory_callBack()
{
    wpfdAddon_call('category.addGoogleTeamDriveCategory');
}

/**
 * Save file info google team drive
 *
 * @param array $data File data
 *
 * @return boolean
 */
function wpfdAddonSaveGoogleTeamDriveFileInfo($data)
{
    Application::getInstance('WpfdAddon');
    $cloudTeamDriveFile = Model::getInstance('cloudteamdrivefiles');
    return $cloudTeamDriveFile->saveFileInfo($data);
}

/**
 * Delete google team drive files
 *
 * @param integer $idCategory Term Id
 * @param string  $idFile     File Id
 *
 * @return boolean
 */
function wpfdAddonDeleteGoogleTeamDriveFiles($idCategory, $idFile)
{
    Application::getInstance('WpfdAddon');
    $cloudTeamDriveFile = Model::getInstance('cloudteamdrivefiles');
    return $cloudTeamDriveFile->deleteGoogleTeamDriveFile($idCategory, $idFile);
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
 * Get a google team drive file info
 *
 * @param string  $fileId     File Id
 * @param integer $categoryId Category term id
 *
 * @return boolean|array
 */
function wpfdAddonGoogleTeamDriveGetFileInfo($fileId, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $cloudTeamDriveFile = Model::getInstance('cloudteamdrivefiles');
    return $cloudTeamDriveFile->getFileInfo($fileId, $categoryId);
}

/**
 * Set title category google team drive
 *
 * @param integer $termId Term id
 * @param string  $title  Title
 *
 * @return boolean
 */
function wpfdAddonSetCategoryGoogleTeamDriveTitle($termId, $title)
{
    Application::getInstance('WpfdAddon');
    $cloudTeamDriveCategory = Model::getInstance('cloudteamdrivecategory');
    return $cloudTeamDriveCategory->changeCategoryName(WpfdAddonHelper::getGoogleTeamDriveIdByTermId($termId), $title);
}

/**
 * Get all config google team drive
 *
 * @return array
 */
function getAllGoogleTeamDrivedata()
{
    return WpfdAddonHelper::getAllCloudTeamDriveConfigs();
}

include_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/onedrive_business.init.php';
include_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/aws.init.php';
include_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/nextcloud.init.php';
include_once WPFDA_PLUGIN_DIR_PATH . 'app/site/onedrive_business.init.php';
include_once WPFDA_PLUGIN_DIR_PATH . 'app/site/onedrive.init.php';
include_once WPFDA_PLUGIN_DIR_PATH . 'app/site/google.init.php';
include_once WPFDA_PLUGIN_DIR_PATH . 'app/site/google_team_drive.init.php';
include_once WPFDA_PLUGIN_DIR_PATH . 'app/site/dropbox.init.php';
include_once WPFDA_PLUGIN_DIR_PATH . 'app/site/aws.init.php';
include_once WPFDA_PLUGIN_DIR_PATH . 'app/site/nextcloud.init.php';