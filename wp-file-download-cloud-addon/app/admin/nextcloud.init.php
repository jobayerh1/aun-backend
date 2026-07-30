<?php
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Utilities;

/**
 * Nextcloud hooks
 */
add_action('admin_menu', 'wpfdNextcloudAddon_menu');
add_action('wp_ajax_wpfdAddonConnectNextcloud', 'wpfdAddonConnectNextcloud_callBack');

add_action('wp_ajax_wpfdAddonAddNextcloudCategory', 'wpfdAddonAddNextcloudCategory_callBack');
add_action('wp_ajax_wpfdAddonDeleteNextcloudCategory', 'wpfdAddonDeleteNextcloudCategory_callback');
add_action('wp_ajax_wpfdAddonDuplicateNextcloudCategory', 'wpfdAddonDuplicateNextcloudCategory_callBack');

add_filter('wpfdAddonUploadNextcloudVersion', 'wpfdAddonUploadNextcloudVersion', 10, 2);
add_filter('wpfdAddonNextcloudListVersions', 'wpfdAddonNextcloudGetListVersions', 10, 2);
add_filter('wpfdAddonNextcloudDownloadVersion', 'wpfdAddonNextcloudDownloadVersion', 10, 3);
add_filter('wpfdAddonNextcloudDeleteVersion', 'wpfdAddonNextcloudDeleteVersion', 10, 2);

add_action('wp_ajax_nextcloudSync', 'wpfdAddonNextcloudSync_callback');
add_action('wp_ajax_nopriv_nextcloudSync', 'wpfdAddonNextcloudSync_callback');

add_action('wpfd_addon_auto_sync', 'wpfdAddonNextcloudSync', 50);
add_action('wpfd_addon_dropdown', 'displayCreateDropDown', 70);

add_filter('wpfdAddonNextcloudChangeOrder', 'wpfdAddonNextcloudChangeOrder', 10, 2);
add_action('wpfdAddonMoveFileNextcloud', 'wpfdAddonMoveFileNextcloud', 10, 2);

/**
 * Registry menu Nextcloud
 *
 * @return void
 */
function wpfdNextcloudAddon_menu()
{
    add_submenu_page(
        'options.php',
        'WPFD Nextcloud Addon page',
        'WPFD Nextcloud Addon Submenu Page',
        'manage_options',
        'wpfdAddon-nextcloud',
        'wpfdAddon_call_controller'
    );
}

/**
 * Nextcloud Connect callback
 *
 * @return void
 */
function wpfdAddonConnectNextcloud_callBack()
{
    wpfdAddon_call('nextcloud.connect');
}

/**
 * Nextcloud category callBack
 *
 * @return void
 */
function wpfdAddonAddNextcloudCategory_callBack()
{
    wpfdAddon_call('category.addNextcloudCategory');
}

/**
 * Delete Nextcloud category callback
 *
 * @return void
 */
function wpfdAddonDeleteNextcloudCategory_callback()
{
    wpfdAddon_call('category.deleteNextcloudCategory');
}

/**
 * Duplicate Nextcloud category callback
 *
 * @return void
 */
function wpfdAddonDuplicateNextcloudCategory_callBack()
{
    wpfdAddon_call('category.duplicateNextcloudCategory');
}

/**
 * Set category Nextcloud folder title
 *
 * @param integer $termId Term id
 * @param string  $title  Title
 *
 * @return boolean true if success
 *                 false if error
 */
function wpfdAddonSetCategoryNextcloudTitle($termId, $title)
{
    Application::getInstance('WpfdAddon');
    $nextcloudCategory = Model::getInstance('nextcloudcategory');
    return $nextcloudCategory->changeNextcloudCategoryName(WpfdAddonHelper::getNextcloudPathByTermId($termId), $title, $termId);
}

/**
 * Upload Nextcloud file
 *
 * @param array   $file     File info
 * @param string  $filePath File path
 * @param integer $termId   Term id
 *
 * @return void
 */
function wpfdAddonUploadNextcloudFile($file, $filePath, $termId)
{
    $nextcloud = new WpfdAddonNextcloud();
    $pic = array();
    $pic['error'] = 0;
    $pic['name']  = stripslashes($file['title']) . '.' . $file['ext'];
    $pic['size']  = $file['size'];

    $params = array(
        'file_path'     => $filePath,
        'file_basename' => stripslashes($file['title']) . '.' . $file['ext'],
        'termId'        => $termId
    );
    $result = $nextcloud->uploadFile($params);

    if (empty(!$result)) {
        return;
    }
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
            $categoryModel     = Model::getInstance('category');
            $categoryNextcloud = $categoryModel->getCategory($termId);
            if (wpfd_user_is_owner_of_category($categoryNextcloud)) {
                $nextcloudCategoryInfo = get_term($termId, 'wpfd-category');
                if (!empty($nextcloudCategoryInfo) && !is_wp_error($nextcloudCategoryInfo)) {
                    if ($nextcloudCategoryInfo->description !== 'null' && $nextcloudCategoryInfo->description !== '') {
                        $cateParams = json_decode($nextcloudCategoryInfo->description, true);
                        if (isset($cateParams['category_own'])
                            && (int)$cateParams['category_own'] === (int)$categoryNextcloud->params['category_own']) {
                            $publishFile = true;
                        }
                    }
                }
            }
        }
    }

    // Hold upload folders and files
    if (isset($result['id']) && isset($result['nextcloud_path'])) {
        $uploadedFileData                   = array();
        $uploadedFileData['file_id']        = $result['id'];
        $uploadedFileData['file_path']      = isset($file['file_path'])? $file['file_path'] : '' ;
        $uploadedFileData['nextcloud_path'] = $result['nextcloud_path'];
        $uploadedFileData['category_id']    = $termId;

        update_option('_wpfd_nextcloud_upload_folders_and_files_' . $result['id'], $uploadedFileData);
    }

    if (isset($result['id']) && $uploadFrom === 'front' && $publishFile === false) {
        // Save values to set pending files
        $fileId                  = $result['id'];
        $pendingFiles            = get_option('_wpfdAddon_upload_pending_files') ? get_option('_wpfdAddon_upload_pending_files') : array();
        $pendingFiles[$fileId]   = get_current_user_id();
        update_option('_wpfdAddon_upload_pending_files', $pendingFiles, false);
    }

    add_filter('wpfd_addon_nextcloud_uploaded_result', function () use ($result) {
        return $result;
    });

    /**
     * Action fire after Nextcloud file uploaded
     *
     * @param integer|object|mixed The file ID on success. The value 0 or WP_Error on failure.
     */
    if (isset($result['id'])) {
        do_action('wpfd_file_uploaded', $result['id'], $termId, array('source' => 'nextcloud'));
    }
}

/**
 * WpfdAddonNextcloudPendingUploadFiles
 *
 * @param integer|string $categoryId   Category id
 * @param array          $pendingFiles Upload file list
 *
 * @throws Exception                        Fire message if errors
 *
 * @return void
 */
function wpfdAddonNextcloudPendingUploadFiles($categoryId, $pendingFiles)
{
    Application::getInstance('WpfdAddon');
    $nextcloudFile   = Model::getInstance('Nextcloudfiles');
    $uploadedUsers  = get_option('_wpfdAddon_nextcloud_file_user_upload') ? get_option('_wpfdAddon_nextcloud_file_user_upload') : array();
    if (!empty($pendingFiles)) {
        foreach ($pendingFiles as $key => $value) {
            $fileId     = $key;
            $fileInfo   = $nextcloudFile->getNextcloudFileInfo($fileId, $categoryId);
            if (!empty($fileInfo)) {
                $fileInfo['state'] = '0';
                $nextcloudFile->saveNextcloudFileInfo($fileInfo, $categoryId);
            }
            $uploadedUsers[$fileId] = get_current_user_id();
        }

        delete_option('_wpfdAddon_upload_pending_files');
        update_option('_wpfdAddon_nextcloud_file_user_upload', $uploadedUsers, false);
    }

    wp_send_json(array('success' => true, 'data' => $pendingFiles));
}

/**
 * Move file
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonMoveFileNextcloud($id_file, $id_category)
{
    $nextcloud = new WpfdAddonNextcloud;
    $nextcloud->moveFile($id_file, WpfdAddonHelper::getNextcloudPathByTermId($id_category));
}

/**
 * Publish Nextcloud files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonNextcloudPublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $nextcloudFile   = Model::getInstance('Nextcloudfiles');
    $publishedFileIds       = array();
    foreach ($fileIds as $fileId) {
        $fileInfo   = $nextcloudFile->getNextcloudFileInfo($fileId, $categoryId);
        if (!empty($fileInfo)) {
            $fileInfo['state'] = '1';
            $nextcloudFile->saveNextcloudFileInfo($fileInfo, $categoryId);
        }
        // Remove pending status
        $uploadUsers = get_option('_wpfdAddon_nextcloud_file_user_upload');
        if (is_array($uploadUsers) && !empty($uploadUsers)) {
            if (array_key_exists((string) $fileId, $uploadUsers)) {
                update_option('_wpfdAddon_nextcloud_file_user_upload', $uploadUsers, false);
                unset($uploadUsers[$fileId]);
            }
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
 * Unpublish Nextcloud files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonNextcloudUnpublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $nextcloudFile     = Model::getInstance('Nextcloudfiles');
    $unpublishedFileIds       = array();
    foreach ($fileIds as $fileId) {
        $fileInfo   = $nextcloudFile->getNextcloudFileInfo($fileId, $categoryId);
        if (!empty($fileInfo)) {
            $fileInfo['state'] = '0';
            $nextcloudFile->saveNextcloudFileInfo($fileInfo, $categoryId);
        }

        $unpublishedFileIds[] = $fileId;
    }

    if (!empty($unpublishedFileIds)) {
        wp_send_json(array('success' => true, 'data' => $unpublishedFileIds));
    } else {
        wp_send_json(array('success' => false, 'data' => array()));
    }
}

/**
 * Get Nextcloud file info
 *
 * @param string  $idFile     File id
 * @param integer $idCategory Term id
 *
 * @return boolean|array array File if success
 *                       false if error
 */
function wpfdAddonNextcloudGetFileInfo($idFile, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $nextcloudFile = Model::getInstance('Nextcloudfiles');
    /* @var $nextcloudFile wpfdAddonModelNextcloudFiles */
    return $nextcloudFile->getNextcloudFileInfo($idFile, $idCategory);
}

/**
 * Save Nextcloud file Info
 *
 * @param array   $data       File data
 * @param integer $idCategory Term id
 *
 * @return string File id
 */
function wpfdAddonSaveNextcloudFileInfo($data, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $nextcloudFile = Model::getInstance('Nextcloudfiles');
    return $nextcloudFile->saveNextcloudFileInfo($data, $idCategory);
}

/**
 * Save Nextcloud file multi categories info
 *
 * @param integer|string $id_file             File id
 * @param array          $file_multi_category File multi categories
 * @param integer        $idCategory          Category term id
 *
 * @return boolean|string|mixed
 */
function wpfdAddonSetNextcloudFileMultiCategories($id_file, $file_multi_category, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $nextcloud = Model::getInstance('Nextcloudfiles');
    $result = array(
        'success' => false,
        'file_multi_category_old' => array()
    );

    if (!isset($id_file)) {
        return false;
    }

    $nextcloudInfo = $nextcloud->getNextcloudFileInfo($id_file, $idCategory);

    $fileMultiCategoryOld = isset($nextcloudInfo['file_multi_category_old']) ? explode(',', $nextcloudInfo['file_multi_category_old']) : array();
    $nextcloudInfo['file_multi_category'] = $file_multi_category;
    $nextcloudInfo['file_multi_category_old'] = implode(',', $file_multi_category);

    if ($nextcloud->saveNextcloudFileInfo($nextcloudInfo, $idCategory)) {
        $result['success'] = true;
        $result['file_multi_category_old'] = $fileMultiCategoryOld;
    }

    return $result;
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
function wpfdAddonDeleteNextcloudFiles($idCategory, $idFile)
{
    Application::getInstance('WpfdAddon');
    $nextcloudFile = Model::getInstance('Nextcloudfiles');
    return $nextcloudFile->deleteNextcloudFile($idCategory, $idFile);
}

/**
 * Uplaod Nextcloud version
 *
 * @param array   $data   File data
 * @param integer $termId Term id
 *
 * @return boolean
 */
function wpfdAddonUploadNextcloudVersion($data, $termId)
{
    Application::getInstance('WpfdAddon');
    $nextcloudFile = Model::getInstance('Nextcloudfiles');
    return $nextcloudFile->uploadNextcloudVersion($data, $termId);
}

/**
 * Get Nextcloud versions
 *
 * @param string  $fileId File id
 * @param integer $termId Term id
 *
 * @return mixed
 */
function wpfdAddonNextcloudGetListVersions($fileId, $termId)
{
    Application::getInstance('WpfdAddon');
    $nextcloudFile = Model::getInstance('Nextcloudfiles');
    $results = $nextcloudFile->getListVersions($fileId, $termId);

    if (is_array($results) && count($results)) {
        $revs = array();
        foreach ($results as $version) {
            $rev                 = array();
            $rev['id']           = rawurldecode($fileId);
            $rev['ext']          = '';
            $rev['meta_id']      = $version['etag'];
            $rev['catid']        = $termId;
            $rev['size']         = $version['content_length'];
            $rev['created_time'] = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($version['last_modified'])));
            $revs[]              = $rev;
        }

        return $revs;
    }

    return array();
}

/**
 * Download version
 *
 * @param string $id_file    File id
 * @param string $vid        Version id
 * @param string $categoryId Category id
 *
 * @return boolean false on error
 *                 download file on success
 */
function wpfdAddonNextcloudDownloadVersion($id_file, $vid, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $nextcloudFile = Model::getInstance('Nextcloudfiles');
    return $nextcloudFile->downloadVersion($id_file, $vid, $categoryId);
}

/**
 * Delete version
 *
 * @param string $id_file File id
 * @param string $vid     Version Id
 *
 * @return boolean
 */
function wpfdAddonNextcloudDeleteVersion($id_file, $vid)
{
    Application::getInstance('WpfdAddon');
    $nextcloudFile = Model::getInstance('nextcloudfiles');
    return $nextcloudFile->deleteVersion($id_file, $vid);
}

/**
 * Nextcloud Sync callback
 *
 * @return void
 */
function wpfdAddonNextcloudSync_callback()
{
    wpfdAddon_call('nextcloud.nextcloudSync');
}

/**
 * Nextcloud auto sync callback
 *
 * @return void
 */
function wpfdAddonNextcloudSync()
{
    $nextcloud = new WpfdAddonNextcloud();
    if ($nextcloud->checkConnectNextcloud()) {
        $nextcloudConfig = WpfdAddonHelper::getAllNextcloudConfigs();
        $sync_time   = isset($nextcloudConfig['nextcloudSyncTime']) ? (int)$nextcloudConfig['nextcloudSyncTime'] : 0;
        $sync_method = isset($nextcloudConfig['nextcloudSyncMethod']) ? (string) $nextcloudConfig['nextcloudSyncMethod'] : 'sync_page_curl_ajax';

        if ($sync_time > 0 && $sync_method !== 'setup_on_server') {
            $curSyncInterval = WpfdAddonHelper::curSyncIntervalNextcloud();
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl') {
                wp_remote_get(
                    admin_url('admin-ajax.php') . '?action=nextcloudSync',
                    array(
                        'timeout' => 1,
                        'blocking' => false,
                        'sslverify' => false,
                    )
                );
            }
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl_ajax') {
                wpfdAddonNextcloudSync_callback();
            }
        }
    }
}

/**
 * Display drop down create Nextcloud
 *
 * @return void
 */
function displayCreateDropDown()
{
    if (wpfd_nextcloud_connected()) {
        echo '<li><a href="#" class="button nextcloudCreate">  
                <span class="wpfd-folder wpfd-liga wpfd-nextcloud-icon"></span> '
                . esc_html__('Nextcloud', 'wpfdAddon') . '</a>
            </li>';
    }
}

/**
 * Nextcloud change order
 *
 * @param integer $pk  Term move order id
 * @param integer $ref Ref term id
 *
 * @return void
 */
function wpfdAddonNextcloudChangeOrder($pk, $ref)
{
    Application::getInstance('WpfdAddon');
    $nextcloudCat = Model::getInstance('nextcloudcategory');
    $nextcloudCat->changeNextcloudOrder($pk, $ref);
}
