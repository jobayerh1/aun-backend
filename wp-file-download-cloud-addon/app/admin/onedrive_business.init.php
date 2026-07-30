<?php
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Utilities;

/**
 * OneDrive Business
 */
add_filter('wpfdAddonDownloadOneDriveBusinessInfo', 'wpfdAddonDownloadOneDriveBusinessInfo', 10, 1);
add_action('wpfdAddonCopyOneDriveBusiness', 'wpfdAddonCopyOneDriveBusiness', 10, 2);
add_action('wpfdAddonMoveOneDriveBusiness', 'wpfdAddonMoveOneDriveBusiness', 10, 2);
add_filter('wpfdAddonOneDriveBusinessChangeOrder', 'wpfdAddonOneDriveBusinessChangeOrder', 10, 1);
add_action('wp_ajax_onedriveBusinessSync', 'wpfdAddonOneDriveBusinessSync');
add_action('wp_ajax_nopriv_onedriveBusinessSync', 'wpfdAddonOneDriveBusinessSync');
add_action('wpfd_addon_auto_sync', 'wpfdAddonOneDriveBusinessSyncAuto', 50);
add_filter('wpfdAddonUploadOneDriveBusinessVersion', 'wpfdAddonUploadOneDriveBusinessVersion', 10, 2);
add_filter('wpfdAddonOneDriveBusinessListVersions', 'wpfdAddonOneDriveBusinessGetListVersions', 10, 2);

// Push notifications ajax
add_action('wp_ajax_onedriveBusinessWatchChanges', 'wpfdAddonOnedriveBusinessWatchChanges_callback');
add_action('wp_ajax_nopriv_onedriveBusinessWatchChanges', 'wpfdAddonOnedriveBusinessWatchChanges_callback');

add_action('wp_ajax_onedriveBusinessPushListener', 'wpfdAddonOnedriveBusinessPushListener_callback');
add_action('wp_ajax_nopriv_onedriveBusinessPushListener', 'wpfdAddonOnedriveBusinessPushListener_callback');


add_action('admin_init', function () {
    $state = Utilities::getInput('state', 'GET', 'string');
    if ($state === 'wpfd-onedrive-business') {
        $onedrive    = new WpfdAddonOneDriveBusiness();
        $onedrive->authenticate();
    }
});

/**
 * Get list file from onedriver
 *
 * @param integer $termId      Term Id
 * @param boolean $listIdFlies List id Files?
 *
 * @return array
 */
//function wpfdAddonDisplayOneDriveBusinessCategories($termId, $listIdFlies = false)
//{
//    if (WpfdAddonHelper::getOneDriveBusinessIdByTermId($termId)) {
//        Application::getInstance('WpfdAddon');
//        $onedriveFile = Model::getInstance('onedrivebusinessfiles');
//
//        return $onedriveFile->getListOneDriveBusinessFiles($termId, $listIdFlies);
//    }
//}

/**
 * Get OneDrive business file info
 *
 * @param string  $idFile     File id
 * @param integer $idCategory Term id
 *
 * @return boolean|array array File if success
 *                       false if error
 */
function wpfdAddonOneDriveBusinessGetFileInfo($idFile, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $onedriveFile = Model::getInstance('onedrivebusinessfiles');
    /* @var $onedriveFile wpfdAddonModelOnedriveBusinessFiles */
    return $onedriveFile->getOneDriveBusinessFileInfo($idFile, $idCategory);
}
/**
 * Save OneDrive file Info
 *
 * @param array   $data       File data
 * @param integer $idCategory Term id
 *
 * @return string File id
 */
function wpfdAddonSaveOneDriveBusinessFileInfo($data, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $onedriveFile = Model::getInstance('onedrivebusinessfiles');
    return $onedriveFile->saveOneDriveBusinessFileInfo($data, $idCategory);
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
function wpfdAddonUploadOneDriveBusinessFile($file, $file_current, $id_category)
{
    $onedrive = new WpfdAddonOneDriveBusiness();
    $pic = array();
    $pic['error'] = 0;
    $pic['name'] = $file['title'] . '.' . $file['ext'];
    $pic['type'] = '';
    $pic['size'] = $file['size'];
    if ($file['size'] > 10 * 1024 * 1024) {
        $result = $onedrive->uploadLargeFile(
            $file['title'] . '.' . $file['ext'],
            $file_current,
            WpfdAddonHelper::getOneDriveBusinessIdByTermId($id_category)
        );
    } else {
        $result = $onedrive->uploadFile(
            $file['title'] . '.' . $file['ext'],
            $pic,
            $file_current,
            WpfdAddonHelper::getOneDriveBusinessIdByTermId($id_category)
        );
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
            $categoryModel                  = Model::getInstance('category');
            $categoryOnedriveBusiness       = $categoryModel->getCategory($id_category);
            if (wpfd_user_is_owner_of_category($categoryOnedriveBusiness)) {
                $onedriveBusinessCategoryInfo = get_term($id_category, 'wpfd-category');
                if (!empty($onedriveBusinessCategoryInfo) && !is_wp_error($onedriveBusinessCategoryInfo)) {
                    if ($onedriveBusinessCategoryInfo->description !== 'null' && $onedriveBusinessCategoryInfo->description !== '') {
                        $cateParams = json_decode($onedriveBusinessCategoryInfo->description, true);
                        if (isset($cateParams['category_own'])
                            && (int)$cateParams['category_own'] === (int)$categoryOnedriveBusiness->params['category_own']) {
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
        $uploadedFileData['file_path']   = isset($file['file_path'])? $file['file_path'] : '';
        $uploadedFileData['category_id'] = $id_category;

        update_option('_wpfd_onedrive_business_upload_folders_and_files_' . $result['file']['id'], $uploadedFileData);
    }

    if (isset($result['file']['id']) && $uploadFrom === 'front' && $publishFile === false) {
        // Save values to set pending files
        $fileId                  = $result['file']['id'];
        $pendingFiles            = get_option('_wpfdAddon_upload_pending_files') ? get_option('_wpfdAddon_upload_pending_files') : array();
        $pendingFiles[$fileId]   = get_current_user_id();
        update_option('_wpfdAddon_upload_pending_files', $pendingFiles, false);
    }

    add_filter('wpfd_addon_onedrive_business_uploaded_result', function () use ($result) {
        return $result['file'];
    });

    /**
     * Action fire after onedrive business file uploaded
     *
     * @param integer|object|mixed The file ID on success. The value 0 or WP_Error on failure.
     */
    do_action('wpfd_file_uploaded', $result['file']['id'], $id_category, array('source' => 'onedrive_business'));
}
/**
 * Copy onedrive
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonCopyOneDriveBusiness($id_file, $id_category)
{
    $oneDrive = new WpfdAddonOneDriveBusiness();
    $result = $oneDrive->copyFile($id_file, WpfdAddonHelper::getOneDriveBusinessIdByTermId($id_category));

    add_filter('wpfd_addon_onedrive_business_uploaded_result', function () use ($result) {
        return $result;
    });
}

/**
 * Copy onedrive
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonMoveOneDriveBusiness($id_file, $id_category)
{
    $oneDrive = new WpfdAddonOneDriveBusiness();
    $result = $oneDrive->moveFile($id_file, WpfdAddonHelper::getOneDriveBusinessIdByTermId($id_category));

    add_filter('wpfd_addon_onedrive_business_uploaded_result', function () use ($result) {
        return $result;
    });
}

/**
 * Update Version and Description of Onedrive Business on copy
 *
 * @param string  $version     Version
 * @param string  $description Description
 * @param integer $catId       Category term id
 *
 * @return void
 */
function wpfdAddonOnedriveBusinessUpdateVersionDescription($version, $description, $catId)
{
    $uploadedFile = array();
    /**
     * Filter to get uploaded dropbox file params
     *
     * @param array
     */
    $uploadedFile = apply_filters('wpfd_addon_onedrive_business_uploaded_result', $uploadedFile);

    if (empty($uploadedFile) || !isset($uploadedFile['id'])) {
        return;
    }

    $new_file_id = $uploadedFile['id'];
    // Update file version and description
    $fileInfos = WpfdAddonHelper::getOnedriveBusinessFileInfos();

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

    WpfdAddonHelper::setOneDriveBusinessFileInfos($fileInfos);
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
function wpfdAddonDeleteOneDriveBusinessFiles($idCategory, $idFile)
{
    Application::getInstance('WpfdAddon');
    $onedriveFile = Model::getInstance('onedrivebusinessfiles');
    $del = $onedriveFile->deleteOneDriveBusinessFile($idCategory, $idFile);

    if ($del === true) {
        $onedriveBusinessFileInfos = get_option('_wpfdAddon_onedrive_business_fileInfo');
        if (is_array($onedriveBusinessFileInfos) && !empty($onedriveBusinessFileInfos) && isset($onedriveBusinessFileInfos[$idCategory][$idFile])) {
            unset($onedriveBusinessFileInfos[$idCategory][$idFile]);
            update_option('_wpfdAddon_onedrive_business_fileInfo', $onedriveBusinessFileInfos);
        }
    }

    return $del;
}

/**
 * Download OneDrive business info
 *
 * @param string $id_file File id
 *
 * @return boolean|stdClass|WP_Error false on response code other than 200
 *                             stdClass File info when success
 *                             WP_Error on Exception
 */
function wpfdAddonDownloadOneDriveBusinessInfo($id_file)
{
    $onedriveFile = new WpfdAddonOneDriveBusiness();
    return $onedriveFile->downloadFile($id_file);
}

/**
 * OndDrive change order
 *
 * @param integer $pk Term move order
 *
 * @return void
 */
function wpfdAddonOneDriveBusinessChangeOrder($pk)
{
    Application::getInstance('WpfdAddon');
    $OnedriveCat = Model::getInstance('onedrivebusinesscategory');
    $OnedriveCat->changeOrder($pk);
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
function wpfdAddonSetCategoryOneDriveBusinessTitle($termId, $title)
{
    Application::getInstance('WpfdAddon');
    $onedriveCategory = Model::getInstance('onedrivebusinesscategory');
    return $onedriveCategory->changeCategoryName(WpfdAddonHelper::getOneDriveBusinessIdByTermId($termId), $title);
}

/**
 * OneDrive Sync callback
 *
 * @return void
 */
function wpfdAddonOneDriveBusinessSync()
{
    wpfdAddon_call('onedrivebusiness.onedrivebusinesssync');
}

/**
 * OneDrive business auto sync
 *
 * @return void|mixed
 */
function wpfdAddonOneDriveBusinessSyncAuto()
{
    wpfdAddon_call('onedrivebusiness.oneDriveBusinessSyncAuto');
}

/**
 * Uplaod onedrive version
 *
 * @param array   $data   File data
 * @param integer $termId Term id
 *
 * @return boolean
 */
function wpfdAddonUploadOneDriveBusinessVersion($data, $termId)
{
    Application::getInstance('WpfdAddon');
    $dropFile = Model::getInstance('onedrivebusinessfiles');
    return $dropFile->uploadOneDriveBusinessVersion($data, $termId);
}
/**
 * Toggle watch changes
 *
 * @return void
 */
function wpfdAddonOnedriveBusinessWatchChanges_callback()
{
    wpfdAddon_call('onedrivebusiness.onedriveBusinessStopWatchChanges');
}

/**
 * Webhook to listen changes form Google Drive
 *
 * @return void
 */
function wpfdAddonOnedriveBusinessPushListener_callback()
{
    wpfdAddon_call('onedrivebusiness.listener');
}

/**
 * WpfdAddonOnedriveBusinessPendingUploadFiles
 *
 * @param integer|string $categoryId   Category id
 * @param array          $pendingFiles Upload file list
 *
 * @throws Exception                        Fire message if errors
 *
 * @return void
 */
function wpfdAddonOnedriveBusinessPendingUploadFiles($categoryId, $pendingFiles)
{
    Application::getInstance('WpfdAddon');
    $onedriveFile   = Model::getInstance('onedrivebusinessfiles');
    $uploadedUsers  = get_option('_wpfdAddon_onedrive_business_file_user_upload') ? get_option('_wpfdAddon_onedrive_business_file_user_upload') : array();
    if (!empty($pendingFiles)) {
        foreach ($pendingFiles as $key => $value) {
            $fileId     = $key;
            $fileInfo   = $onedriveFile->getOneDriveBusinessFileInfo($fileId, $categoryId);
            if (!empty($fileInfo)) {
                $fileInfo['state'] = '0';
                $onedriveFile->saveOneDriveBusinessFileInfo($fileInfo, $categoryId);
            }
            $uploadedUsers[$fileId] = get_current_user_id();
        }

        delete_option('_wpfdAddon_upload_pending_files');
        update_option('_wpfdAddon_onedrive_business_file_user_upload', $uploadedUsers, false);
    }

    wp_send_json(array('success' => true, 'data' => $pendingFiles));
}

/**
 * Publish onedrivebusiness files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonOnedriveBusinessPublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $onedrivebusinessFile   = Model::getInstance('onedrivebusinessfiles');
    $publishedFileIds       = array();
    foreach ($fileIds as $fileId) {
        $fileInfo   = $onedrivebusinessFile->getOneDriveBusinessFileInfo($fileId, $categoryId);
        if (!empty($fileInfo)) {
            $fileInfo['state'] = '1';
            $onedrivebusinessFile->saveOneDriveBusinessFileInfo($fileInfo, $categoryId);
        }
        // Remove pending status
        $uploadUsers = get_option('_wpfdAddon_onedrive_business_file_user_upload');
        if (is_array($uploadUsers) && !empty($uploadUsers)) {
            if (array_key_exists((string) $fileId, $uploadUsers)) {
                update_option('_wpfdAddon_onedrive_business_file_user_upload', $uploadUsers, false);
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
 * Unpublish onedrivebusiness files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonOnedriveBusinessUnpublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $onedrivebusinessFile     = Model::getInstance('onedrivebusinessfiles');
    $unpublishedFileIds       = array();
    foreach ($fileIds as $fileId) {
        $fileInfo   = $onedrivebusinessFile->getOneDriveBusinessFileInfo($fileId, $categoryId);
        if (!empty($fileInfo)) {
            $fileInfo['state'] = '0';
            $onedrivebusinessFile->saveOneDriveBusinessFileInfo($fileInfo, $categoryId);
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
 * Get onedrive versions
 *
 * @param string  $fileId File id
 * @param integer $termId Term id
 *
 * @return mixed
 */
function wpfdAddonOneDriveBusinessGetListVersions($fileId, $termId)
{
    Application::getInstance('WpfdAddon');
    $cloudFile = Model::getInstance('onedrivebusinessfiles');
    $results = $cloudFile->getListVersions($fileId, $termId);

    if (is_array($results) && count($results)) {
        $revs = array();
        /* @var \Microsoft\Graph\Model\DriveItemVersion $version */
        foreach ($results as $version) {
            $rev                 = array();
            $rev['id']           = $fileId;
            $rev['ext']          = '';
            $rev['meta_id']      = $version->getId();
            $rev['catid']        = $termId;
            $rev['size']         = $version->getSize();
            $rev['created_time'] = $version->getLastModifiedDateTime()->format('Y-m-d H:i:s');
            $revs[]              = $rev;
        }

        return $revs;
    }

    return array();
}

/**
 * WpfdAddonUploadOneDriveBusinessFoldersAndFiles
 *
 * @param string|integer $id_Category Category id
 *
 * @throws Exception                  Fire message if errors
 *
 * @return void|mixed
 */
function wpfdAddonUploadOneDriveBusinessFoldersAndFiles($id_Category)
{
    $app                = Application::getInstance('WpfdAddon');
    $obCategory         = Model::getInstance('onedrivebusinesscategory');
    $oneDriveBusiness   = new WpfdAddonOneDriveBusiness();
    $uploads            = array();
    $createdCategories  = array();
    $processedDirects   = array();
    $processedIds       = array();
    $newCategoryId      = WpfdAddonHelper::getOneDriveBusinessIdByTermId($id_Category);
    $odbFiles           = $oneDriveBusiness->listFiles($newCategoryId, $id_Category, 'ordering', 'asc');

    if (!empty($odbFiles)) {
        foreach ($odbFiles as $odbFile) {
            $fileId = $odbFile->id;
            $fileUploadInfos = get_option('_wpfd_onedrive_business_upload_folders_and_files_' . $fileId, array());
            if (!empty($fileUploadInfos)) {
                $newFile = new stdClass;
                $newFile->file_id = $fileId;
                $newFile->file_title = $odbFile->title;
                $newFile->file_type = $odbFile->ext;
                $newFile->file_path = $fileUploadInfos['file_path'];
                $uploads[$newCategoryId][] = $newFile;

                // Remove unused data
                delete_option('_wpfd_onedrive_business_upload_folders_and_files_' . $fileId);
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

                // Insert onedrive business categories
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
                        $return = $obCategory->addCategory($categoryName, $id_Category, 'end');

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
                        $return = $obCategory->addCategory($categoryName, $targetCate, 'end');

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

                // Move onedrive business files
                if (!$fileInfo->file_id) {
                    continue;
                }

                $newFilePath = str_replace($fileInfo->file_title . '.' . $fileInfo->file_type, '', $fileInfo->file_path);
                $newFilePath = str_replace('/', '*****', $newFilePath);
                $targetCategory = isset($createdCategories[$newFilePath]['category_id']) ? $createdCategories[$newFilePath]['category_id'] : $id_Category;

                if ((int) $targetCategory === (int) $id_Category) {
                    continue;
                }

                wpfdAddonMoveOneDriveBusiness($fileInfo->file_id, $targetCategory);
            }
        }
    }
}
