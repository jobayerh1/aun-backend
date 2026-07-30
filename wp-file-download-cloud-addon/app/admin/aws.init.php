<?php
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Utilities;

/**
 * AWS hooks
 */
add_action('admin_menu', 'wpfdAwsAddon_menu');

add_action('wp_ajax_wpfdAddonConnectAws', 'wpfdAddonConnectAws_callBack');
add_action('wp_ajax_wpfdAddonSelectBucket', 'wpfdAddonSelectBucket_callBack');
add_action('wp_ajax_wpfdAddonCreateBucket', 'wpfdAddonCreateBucket_callBack');
add_action('wp_ajax_wpfdAddonDeleteBucket', 'wpfdAddonDeleteBucket_callBack');
add_action('wp_ajax_wpfdAddonGetListBucket', 'wpfdAddonGetListBucket_callBack');

add_action('wp_ajax_wpfdAddonAddAwsCategory', 'wpfdAddonAddAwsCategory_callBack');
add_action('wp_ajax_wpfdAddonDeleteAwsCategory', 'wpfdAddonDeleteAwsCategory_callback');
add_action('wp_ajax_wpfdAddonDuplicateAwsCategory', 'wpfdAddonDuplicateAwsCategory_callBack');

add_action('wp_ajax_awsSync', 'wpfdAddonAwsSync_callback');
add_action('wp_ajax_nopriv_awsSync', 'wpfdAddonAwsSync_callback');

add_action('wpfd_addon_auto_sync', 'wpfdAddonAwsSync', 60);

add_filter('wpfdAddonUploadAwsVersion', 'wpfdAddonUploadAwsVersion', 10, 2);
add_filter('wpfdAddonAwsListVersions', 'wpfdAddonAwsGetListVersions', 10, 2);
add_filter('wpfdAddonAwsDownloadVersion', 'wpfdAddonAwsDownloadVersion', 10, 3);
add_filter('wpfdAddonAwsDeleteVersion', 'wpfdAddonAwsDeleteVersion', 10, 2);

// add_filter('wpfdAddonAwsUpload', 'wpfdAddonAwsUpload', 10, 2);
add_filter('wpfdAddonAwsChangeOrder', 'wpfdAddonAwsChangeOrder', 10, 2);
// add_filter('wpfdAddonDownloadAwsInfo', 'wpfdAddonDownloadAwsInfo', 10, 1);

/**
 * AWS Connect callback
 *
 * @return void
 */
function wpfdAddonConnectAws_callBack()
{
    wpfdAddon_call('aws.connect');
}

/**
 * AWS select bucket callback
 *
 * @return array|void
 */
function wpfdAddonSelectBucket_callBack()
{
    $bucketName = Utilities::getInput('bucket', 'POST', 'string');
    $aws = new WpfdAddonAws();
    $result = $aws->selectBucket($bucketName);
    if ($result['success']) {
        wpfdAddon_call('aws.clearAwsCategory');
    }

    return wp_send_json($result);
}

/**
 * AWS create bucket callback
 *
 * @return void
 */
function wpfdAddonCreateBucket_callBack()
{
    $bucketName = Utilities::getInput('bucket', 'POST', 'string');
    $region = Utilities::getInput('region', 'POST', 'string');
    $aws = new WpfdAddonAws();
    $bucketExists = $aws->doesBucketExist($bucketName);
    if (!$bucketExists) {
        $res = $aws->createBucket($bucketName, $region);
        if ($res) {
            wp_send_json(array('success' => true));
        } else {
            wp_send_json(array('success' => false, 'msg' => $res));
        }
    } else {
        wp_send_json(array('success' => false, 'msg' => esc_html__('Bucket name already exists', 'wpfdAddon')));
    }
}

/**
 * AWS get list bucket callback
 *
 * @return array|void
 */
function wpfdAddonGetListBucket_callBack()
{
    $aws = new WpfdAddonAws();
    $res = $aws->getListBucket(true);

    return $res;
}

/**
 * AWS delete bucket callback
 *
 * @return array|void
 */
function wpfdAddonDeleteBucket_callBack()
{
    $bucketName = Utilities::getInput('bucket', 'POST', 'string');
    $aws = new WpfdAddonAws();
    $res = $aws->getBucketLocation($bucketName);
    if ($res['success']) {
        $aws = new WpfdAddonAws($res['region']);
        $res = $aws->deleteBucket($bucketName);
    }

    return wp_send_json($res);
}

/**
 * Get AWS file info
 *
 * @param string  $idFile     File id
 * @param integer $idCategory Term id
 *
 * @return boolean|array array File if success
 *                       false if error
 */
function wpfdAddonAwsGetFileInfo($idFile, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $awsFile = Model::getInstance('Awsfiles');
    /* @var $awsFile wpfdAddonModelAwsFiles */
    return $awsFile->getAwsFileInfo($idFile, $idCategory);
}
/**
 * Save AWS file Info
 *
 * @param array   $data       File data
 * @param integer $idCategory Term id
 *
 * @return string File id
 */
function wpfdAddonSaveAwsFileInfo($data, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $awsFile = Model::getInstance('Awsfiles');
    return $awsFile->saveAwsFileInfo($data, $idCategory);
}

/**
 * Upload AWS file
 *
 * @param array   $file         File info
 * @param string  $file_current File path
 * @param integer $id_category  Term id
 *
 * @return void
 */
function wpfdAddonUploadAwsFile($file, $file_current, $id_category)
{
    $aws = new WpfdAddonAws();
    $pic = array();
    $pic['error'] = 0;
    $pic['name'] = stripslashes($file['title']) . '.' . $file['ext'];
    $pic['type'] = '';
    $pic['size'] = $file['size'];

    $params = array(
        'type'          => 'file',
        'file_path'     => $file_current,
        'file_name'     => stripslashes($file['title']) . '.' . $file['ext'],
        'id_category'   => $id_category
    );
    $result = $aws->uploadObject($params);

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
            $categoryAws       = $categoryModel->getCategory($id_category);
            if (wpfd_user_is_owner_of_category($categoryAws)) {
                $AwsCategoryInfo = get_term($id_category, 'wpfd-category');
                if (!empty($AwsCategoryInfo) && !is_wp_error($AwsCategoryInfo)) {
                    if ($AwsCategoryInfo->description !== 'null' && $AwsCategoryInfo->description !== '') {
                        $cateParams = json_decode($AwsCategoryInfo->description, true);
                        if (isset($cateParams['category_own'])
                            && (int)$cateParams['category_own'] === (int)$categoryAws->params['category_own']) {
                            $publishFile = true;
                        }
                    }
                }
            }
        }
    }

    // Hold upload folders and files
    if (isset($result['id']) && isset($result['aws_path'])) {
        $uploadedFileData                = array();
        $uploadedFileData['file_id']     = $result['id'];
        $uploadedFileData['file_path']   = isset($file['file_path'])? $file['file_path'] : '' ;
        $uploadedFileData['aws_path']    = $result['aws_path'];
        $uploadedFileData['category_id'] = $id_category;

        update_option('_wpfd_aws_upload_folders_and_files_' . $result['id'], $uploadedFileData);
    }

    if (isset($result['id']) && $uploadFrom === 'front' && $publishFile === false) {
        // Save values to set pending files
        $fileId                  = $result['id'];
        $pendingFiles            = get_option('_wpfdAddon_upload_pending_files') ? get_option('_wpfdAddon_upload_pending_files') : array();
        $pendingFiles[$fileId]   = get_current_user_id();
        update_option('_wpfdAddon_upload_pending_files', $pendingFiles, false);
    }

    add_filter('wpfd_addon_aws_uploaded_result', function () use ($result) {
        return $result;
    });

    /**
     * Action fire after AWS file uploaded
     *
     * @param integer|object|mixed The file ID on success. The value 0 or WP_Error on failure.
     */
    do_action('wpfd_file_uploaded', $result['id'], $id_category, array('source' => 'aws'));
}
/**
 * Copy AWS
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonCopyAws($id_file, $id_category)
{
    $aws = new WpfdAddonAws();
    $result = $aws->copyFile($id_file, WpfdAddonHelper::getAwsIdByTermId($id_category));

    add_filter('wpfd_addon_aws_uploaded_result', function () use ($result) {
        return $result;
    });
}

/**
 * Copy AWS
 *
 * @param string  $id_file     File id
 * @param integer $id_category Term id
 *
 * @return void
 */
function wpfdAddonMoveAws($id_file, $id_category)
{
    $aws = new WpfdAddonAws();
    $result = $aws->moveFile($id_file, WpfdAddonHelper::getAwsIdByTermId($id_category));

    add_filter('wpfd_addon_aws_uploaded_result', function () use ($result) {
        return $result;
    });
}

/**
 * Update Version and Description of AWS on copy
 *
 * @param string  $version     Version
 * @param string  $description Description
 * @param integer $catId       Category term id
 *
 * @return void
 */
function wpfdAddonAwsUpdateVersionDescription($version, $description, $catId)
{
    $uploadedFile = array();
    /**
     * Filter to get uploaded AWS file params
     *
     * @param array
     */
    $uploadedFile = apply_filters('wpfd_addon_aws_uploaded_result', $uploadedFile);

    if (empty($uploadedFile) || !isset($uploadedFile['id'])) {
        return;
    }

    $new_file_id = $uploadedFile['id'];
    // Update file version and description
    $fileInfos = WpfdAddonHelper::getAwsFileInfos();

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

    WpfdAddonHelper::setAwsFileInfos($fileInfos);
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
function wpfdAddonDeleteAwsFiles($idCategory, $idFile)
{
    Application::getInstance('WpfdAddon');
    $awsFile = Model::getInstance('Awsfiles');
    return $awsFile->deleteAwsFile($idCategory, $idFile);
}

/**
 * Download AWS info
 *
 * @param string $id_file File id
 *
 * @return boolean|stdClass|WP_Error false on response code other than 200
 *                             stdClass File info when success
 *                             WP_Error on Exception
 */
function wpfdAddonDownloadAwsInfo($id_file)
{
    $awsFile = new WpfdAddonAws();
    return $awsFile->downloadFile($id_file);
}

/**
 * AWS change order
 *
 * @param integer $pk  Term move order id
 * @param integer $ref Ref term id
 *
 * @return void
 */
function wpfdAddonAwsChangeOrder($pk, $ref)
{
    Application::getInstance('WpfdAddon');
    $awsCat = Model::getInstance('Awscategory');
    $awsCat->changeAwsOrder($pk, $ref);
}
/**
 * Set category AWS title
 *
 * @param integer $termId Term id
 * @param string  $title  Title
 *
 * @return boolean true if success
 *                 false if error
 */
function wpfdAddonSetCategoryAwsTitle($termId, $title)
{
    Application::getInstance('WpfdAddon');
    $awsCategory = Model::getInstance('awscategory');
    return $awsCategory->changeAwsCategoryName(WpfdAddonHelper::getAwsPathByTermId($termId), $title, $termId);
}

/**
 * AWS Sync callback
 *
 * @return void
 */
function wpfdAddonAwsSync_callback()
{
    wpfdAddon_call('aws.awsSync');
}

/**
 * Uplaod AWS version
 *
 * @param array   $data   File data
 * @param integer $termId Term id
 *
 * @return boolean
 */
function wpfdAddonUploadAwsVersion($data, $termId)
{
    Application::getInstance('WpfdAddon');
    $awsFile = Model::getInstance('Awsfiles');
    return $awsFile->uploadAwsVersion($data, $termId);
}

/**
 * Toggle watch changes
 *
 * @return void
 */
function wpfdAddonAwsWatchChanges_callback()
{
    wpfdAddon_call('aws.awsStopWatchChanges');
}

/**
 * Webhook to listen changes form AWS
 *
 * @return void
 */
function wpfdAddonAwsPushListener_callback()
{
    wpfdAddon_call('aws.listener');
}

/**
 * WpfdAddonAwsPendingUploadFiles
 *
 * @param integer|string $categoryId   Category id
 * @param array          $pendingFiles Upload file list
 *
 * @throws Exception                        Fire message if errors
 *
 * @return void
 */
function wpfdAddonAwsPendingUploadFiles($categoryId, $pendingFiles)
{
    Application::getInstance('WpfdAddon');
    $awsFile   = Model::getInstance('Awsfiles');
    $uploadedUsers  = get_option('_wpfdAddon_aws_file_user_upload') ? get_option('_wpfdAddon_aws_file_user_upload') : array();
    if (!empty($pendingFiles)) {
        foreach ($pendingFiles as $key => $value) {
            $fileId     = $key;
            $fileInfo   = $awsFile->getAwsFileInfo($fileId, $categoryId);
            if (!empty($fileInfo)) {
                $fileInfo['state'] = '0';
                $awsFile->saveAwsFileInfo($fileInfo, $categoryId);
            }
            $uploadedUsers[$fileId] = get_current_user_id();
        }

        delete_option('_wpfdAddon_upload_pending_files');
        update_option('_wpfdAddon_aws_file_user_upload', $uploadedUsers, false);
    }

    wp_send_json(array('success' => true, 'data' => $pendingFiles));
}

/**
 * Publish Aws files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonAwsPublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $AwsFile   = Model::getInstance('Awsfiles');
    $publishedFileIds       = array();
    foreach ($fileIds as $fileId) {
        $fileInfo   = $AwsFile->getAwsFileInfo($fileId, $categoryId);
        if (!empty($fileInfo)) {
            $fileInfo['state'] = '1';
            $AwsFile->saveAwsFileInfo($fileInfo, $categoryId);
        }
        // Remove pending status
        $uploadUsers = get_option('_wpfdAddon_aws_file_user_upload');
        if (is_array($uploadUsers) && !empty($uploadUsers)) {
            if (array_key_exists((string) $fileId, $uploadUsers)) {
                update_option('_wpfdAddon_aws_file_user_upload', $uploadUsers, false);
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
 * Unpublish Aws files
 *
 * @param array   $fileIds    File list info
 * @param integer $categoryId Term id
 *
 * @throws Exception               Fire if errors
 *
 * @return void
 */
function wpfdAddonAwsUnpublishFile($fileIds, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $AwsFile     = Model::getInstance('Awsfiles');
    $unpublishedFileIds       = array();
    foreach ($fileIds as $fileId) {
        $fileInfo   = $AwsFile->getAwsFileInfo($fileId, $categoryId);
        if (!empty($fileInfo)) {
            $fileInfo['state'] = '0';
            $AwsFile->saveAwsFileInfo($fileInfo, $categoryId);
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
 * Get AWS versions
 *
 * @param string  $fileId File id
 * @param integer $termId Term id
 *
 * @return mixed
 */
function wpfdAddonAwsGetListVersions($fileId, $termId)
{
    Application::getInstance('WpfdAddon');
    $awsFile = Model::getInstance('Awsfiles');
    $results = $awsFile->getListVersions($fileId, $termId);

    if (is_array($results) && count($results)) {
        $revs = array();
        foreach ($results as $version) {
            $rev                 = array();
            $rev['id']           = rawurldecode($fileId);
            $rev['ext']          = '';
            $rev['meta_id']      = $version['VersionId'];
            $rev['catid']        = $termId;
            $rev['size']         = $version['Size'];
            $rev['created_time'] = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($version['LastModified'])));
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
function wpfdAddonAwsDownloadVersion($id_file, $vid, $categoryId)
{
    Application::getInstance('WpfdAddon');
    $awsFile = Model::getInstance('Awsfiles');
    return $awsFile->downloadVersion($id_file, $vid, $categoryId);
}

/**
 * Delete version
 *
 * @param string $id_file File id
 * @param string $vid     Version Id
 *
 * @return boolean
 */
function wpfdAddonAwsDeleteVersion($id_file, $vid)
{
    Application::getInstance('WpfdAddon');
    $awsFile = Model::getInstance('awsfiles');
    return $awsFile->deleteVersion($id_file, $vid);
}

/**
 * WpfdAddonUploadAwsFoldersAndFiles
 *
 * @param string|integer $id_Category Category id
 *
 * @throws Exception                  Fire message if errors
 *
 * @return void|mixed
 */
function wpfdAddonUploadAwsFoldersAndFiles($id_Category)
{
    $app                = Application::getInstance('WpfdAddon');
    $obCategory         = Model::getInstance('Awscategory');
    $Aws   = new WpfdAddonAws();
    $uploads            = array();
    $createdCategories  = array();
    $processedDirects   = array();
    $processedIds       = array();
    $newCategoryId      = WpfdAddonHelper::getAwsIdByTermId($id_Category);
    $odbFiles           = $Aws->listFiles($newCategoryId, $id_Category, 'ordering', 'asc');

    if (!empty($odbFiles)) {
        foreach ($odbFiles as $odbFile) {
            $fileId = $odbFile->id;
            $fileUploadInfos = get_option('_wpfd_aws_upload_folders_and_files_' . $fileId, array());
            if (!empty($fileUploadInfos)) {
                $newFile = new stdClass;
                $newFile->file_id = $fileId;
                $newFile->file_title = $odbFile->title;
                $newFile->file_type = $odbFile->ext;
                $newFile->file_path = $fileUploadInfos['file_path'];
                $uploads[$newCategoryId][] = $newFile;

                // Remove unused data
                delete_option('_wpfd_aws_upload_folders_and_files_' . $fileId);
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

                // Insert AWS categories
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

                // Move AWS files
                if (!$fileInfo->file_id) {
                    continue;
                }

                $newFilePath = str_replace($fileInfo->file_title . '.' . $fileInfo->file_type, '', $fileInfo->file_path);
                $newFilePath = str_replace('/', '*****', $newFilePath);
                $targetCategory = isset($createdCategories[$newFilePath]['category_id']) ? $createdCategories[$newFilePath]['category_id'] : $id_Category;

                if ((int) $targetCategory === (int) $id_Category) {
                    continue;
                }

                wpfdAddonMoveAws($fileInfo->file_id, $targetCategory);
            }
        }
    }
}

/**
 * AWS auto sync callback
 *
 * @return void
 */
function wpfdAddonAwsSync()
{
    $aws = new WpfdAddonAwsAdmin();
    if ($aws->checkConnectAws()) {
        $awsConfig = WpfdAddonHelper::getAllAwsConfigs();
        $sync_time   = isset($awsConfig['awsSyncTime']) ? (int)$awsConfig['awsSyncTime'] : 0;
        $sync_method = isset($awsConfig['awsSyncMethod']) ? (string) $awsConfig['awsSyncMethod'] : 'sync_page_curl_ajax';

        if ($sync_time > 0 && $sync_method !== 'setup_on_server') {
            $curSyncInterval = WpfdAddonHelper::curSyncIntervalAws();
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl') {
                wp_remote_get(
                    admin_url('admin-ajax.php') . '?action=awsSync',
                    array(
                        'timeout' => 1,
                        'blocking' => false,
                        'sslverify' => false,
                    )
                );
            }
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl_ajax') {
                wpfdAddonAwsSync_callback();
            }
        }
    }
}

/**
 * Save AWS file multi categories info
 *
 * @param integer|string $id_file             File id
 * @param array          $file_multi_category File multi categories
 * @param integer        $idCategory          Category term id
 *
 * @return boolean|string|mixed
 */
function wpfdAddonSetAwsFileMultiCategories($id_file, $file_multi_category, $idCategory)
{
    Application::getInstance('WpfdAddon');
    $aws = Model::getInstance('awsfiles');
    $result = array(
        'success' => false,
        'file_multi_category_old' => array()
    );

    if (!isset($id_file)) {
        return false;
    }

    $awsInfo = $aws->getAwsFileInfo($id_file, $idCategory);

    $fileMultiCategoryOld = isset($awsInfo['file_multi_category_old']) ? explode(',', $awsInfo['file_multi_category_old']) : array();
    $awsInfo['file_multi_category'] = $file_multi_category;
    $awsInfo['file_multi_category_old'] = implode(',', $file_multi_category);

    if ($aws->saveAwsFileInfo($awsInfo, $idCategory)) {
        $result['success'] = true;
        $result['file_multi_category_old'] = $fileMultiCategoryOld;
    }

    return $result;
}
