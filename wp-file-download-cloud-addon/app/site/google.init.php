<?php
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;

if (!class_exists('WpfdAddonHelper')) {
    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/helpers/WpfdHelper.php';
}
if (!class_exists('WpfdAddonGoogle')) {
    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonGoogle.php';
}
// Check if google drive connected
$cloudConfig = WpfdAddonHelper::getAllCloudConfigs();
$googleDrive = new WpfdAddonGoogleDrive();
$isAuthenticated = $googleDrive->checkAuth();
if (isset($cloudConfig['googleCredentials']) && (!empty($cloudConfig['googleCredentials'])) && $isAuthenticated) {
    add_filter('wpfdAddonCategoryFrom', 'wpfdAddonCategoryFrom', 10, 1);
    add_filter('wpfdAddonGetListGoogleDriveFile', 'wpfdAddonGetListGoogleDriveFile', 10, 5);
    add_filter('wpfdAddonGetGoogleDriveFile', 'wpfdAddonGetGoogleDriveFile', 10, 3);
    add_filter('wpfdAddonDownloadGoogleDriveFile', 'wpfdAddonDownloadGoogleDriveFile', 10, 1);
    add_filter('wpfd_level_category', 'wpfdAddonLevelCategory', 9, 1);
    add_filter('wpfdAddonSearchCloud', 'wpfdAddonSearchCloud', 10, 2);
    add_filter('wpfdAddonDownloadCheckGoogleDriveCategory', 'wpfdAddonDownloadCheckGoogleDriveCategory', 10, 2);
    add_filter('wpfd_addon_google_drive_connected', '__return_true');
    add_filter('wpfdAddonSaveGoogleDriveImg', 'wpfdAddonSaveGoogleDriveImg', 10, 2);
}

/**
 * Get list google drive files
 *
 * @param integer $termId       Term id
 * @param string  $ordering     Ordering
 * @param string  $orderingdir  Ordering direction
 * @param string  $categorySlug Category slug
 * @param string  $token        Security token
 *
 * @return mixed
 */
function wpfdAddonGetListGoogleDriveFile($termId, $ordering = 'title', $orderingdir = 'asc', $categorySlug = '', $token = '')
{
    $ordering_array = array('ordering', 'ext', 'title', 'description', 'created_time', 'size', 'version', 'hits');
    if (!in_array($ordering, $ordering_array)) {
        $ordering = 'ordering';
    }
    if ($orderingdir !== 'desc') {
        $orderingdir = 'asc';
    }
    $googleCate = new WpfdAddonGoogleDrive;
    $listFiles = $googleCate->listFiles(WpfdAddonHelper::getGoogleDriveIdByTermId($termId), $ordering, $orderingdir);
    return wpfdAddonAddInfosToFiles($listFiles, $termId, $categorySlug, $token);
}


/**
 * Get a file in google drive
 *
 * @param string  $fileId     Google file id
 * @param integer $categoryId Term id
 * @param string  $token      Security token
 *
 * @return array|boolean
 */
function wpfdAddonGetGoogleDriveFile($fileId, $categoryId, $token)
{
    $googleCate = new WpfdAddonGoogleDrive;
    return $googleCate->getFileInfos($fileId, null, $categoryId, $token);
}

/**
 * Save a file image
 *
 * @param string $fileId       File id
 * @param string $previewsPath Previews folder path on server
 *
 * @return boolean|string
 */
function wpfdAddonSaveGoogleDriveImg($fileId, $previewsPath)
{
    $googleCate = new WpfdAddonGoogleDrive;
    return $googleCate->getGooglePreviewImg(
        $fileId,
        $previewsPath
    );
}

/**
 * Download googleDrive file
 *
 * @param string $fileId Google file id
 *
 * @return boolean|stdClass
 */
function wpfdAddonDownloadGoogleDriveFile($fileId)
{
    $googleCate = new WpfdAddonGoogleDrive;
    $googleCate->incrHits($fileId);
    $result = $googleCate->download($fileId);

    return $result;
}


/**
 * Get category in dropbox
 *
 * @param WP_Term $category Category
 *
 * @return mixed
 */
function wpfdAddonLevelCategoryDropbox($category)
{
    $drop_id = WpfdAddonHelper::getDropboxIdByTermId($category->term_id);
    if ($drop_id) {
        $category->wp_term_id = $category->term_id;
        $category->wp_parent = $category->parent;
        $category->term_id = $drop_id;
        $category->parent = WpfdAddonHelper::getDropboxIdByTermId($category->parent);
    }
    return $category;
}


/**
 * Search cloud
 *
 * @param array $cloud_cond Cloud condition to search
 * @param array $filters    Filters
 *
 * @return array
 */
function wpfdAddonSearchCloud($cloud_cond, $filters)
{
    if (!class_exists('WpfdAddonHelper')) {
        require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/helpers/WpfdHelper.php';
    }

    $googleCate  = new WpfdAddonGoogleDrive;
    $cloudConfig = WpfdAddonHelper::getAllCloudConfigs();
    $excludes = isset($filters['exclude']) ? explode(',', trim($filters['exclude'])) : array();
    $dateFilterResults  = array();
    $isCreateDateFilter = false;
    $isUpdateDateFilter = false;

    if ((isset($filters['cfrom']) && $filters['cfrom'] !== '')
        || (isset($filters['cto']) && $filters['cto'] !== '')) {
        $isCreateDateFilter = true;
    }

    if ((isset($filters['ufrom']) && $filters['ufrom'] !== '')
        || (isset($filters['uto']) && $filters['uto'] !== '')) {
        $isUpdateDateFilter = true;
    }

    $isDateFilters = ($isCreateDateFilter || $isUpdateDateFilter) ? true : false;

    if (isset($filters['catid']) && $filters['catid'] !== '' && is_string($filters['catid'])) {
        $q_tmp   = " '" . $filters['catid'] . "' in parents";
        $folders = $googleCate->getListFolder($filters['catid']);
        if (is_array($folders) && count($folders)) {
            $q_tmp = '(' . $q_tmp;
            foreach ($folders as $id => $folder) {
                $q_tmp .= " or '" . $id . "' in parents ";
            }
            $q_tmp .= ')';
        }
    } else {
        $foldersQuery = $googleCate->searchcondition($cloudConfig);
        if ($foldersQuery !== false) {
            $q_tmp = '(' . $foldersQuery . ')';
        } else {
            $q_tmp = '( "' . $cloudConfig['googleBaseFolder'] . '" in parents)';
        }
    }

    $cloud_cond[] = $q_tmp;

    foreach ($cloud_cond as $i => $cond) {
        if (strpos($cond, 'modifiedDate')) {
            unset($cloud_cond[$i]);
        }
    }

    $q            = implode(' and ', $cloud_cond);
    $results      = $googleCate->getAllFilesInAppFolder($q);

    Application::getInstance('Wpfd');
    $modelConfig = Model::getInstance('configfront');
    $modelToken = Model::getInstance('tokens');
    $params      = $modelConfig->getGlobalConfig();
    $config      = get_option('_wpfd_global_config');
    if (empty($config) || empty($config['uri'])) {
        $seo_uri = 'download';
    } else {
        $seo_uri = rawurlencode($config['uri']);
    }

    if (empty($config) || !isset($config['rmdownloadext'])) {
        $rmdownloadext = false;
    } else {
        $rmdownloadext = ((int) $config['rmdownloadext'] === 1) ? true : false;
    }

    $viewer_type      = WpfdBase::loadValue($params, 'use_google_viewer', 'lightbox');
    $perlink          = get_option('permalink_structure');
    $rewrite_rules    = get_option('rewrite_rules');
    $extension_list   = 'png,jpg,pdf,ppt,pptx,doc,docx,xls,xlsx,dxf,ps,eps,xps,psd,tif,tiff,bmp,svg,pages,ai,dxf,ttf,txt,mp3,mp4';
    $extension_viewer = explode(',', WpfdBase::loadValue($params, 'extension_viewer', $extension_list));
    $extension_viewer = array_map('trim', $extension_viewer);

    /**
     * Filter to replace search hyphen
     *
     * @param boolean Replace
     *
     * @return boolean
     *
     * @ignore Hook already documented
     */
    $replaceHyphen = apply_filters('wpfdSearchReplaceHyphen', false);
    $isTypeFilter  = (isset($filters['ext']) && $filters['ext'] !== '') ? true : false;
    $fileTypes     = $isTypeFilter ? explode(',', $filters['ext']) : array();
    $types         = array_map(function ($fType) {
        return trim($fType);
    }, $fileTypes);

    if (is_array($results)) {
        foreach ($results as $key => $value) {
            if ($value->parent) {
                $value->catid    = WpfdAddonHelper::getCatIdByCloudId($value->parent);
            } else {
                $parentInfo      = $googleCate->getParentInfo($value->id);
                if ($parentInfo) {
                    $value->cattitle = $parentInfo['title'];
                    $value->catname  = $parentInfo['title'];
                    $value->catid    = WpfdAddonHelper::getCatIdByCloudId($parentInfo['id']);
                }
            }
            $size = isset($value->size) ? floatval($value->size) : 0;

            if (isset($filters['isAdminSearch']) && $filters['isAdminSearch'] === true) {
                if (isset($filters['waitingForApproval']) && $filters['waitingForApproval'] === true) {
                    $isPending = apply_filters('wpfd_file_upload_pending', $value->ID, $value->catid);
                    if (!$isPending) {
                        unset($results[$key]);
                        continue;
                    }
                }
            } else {
                // Remove unpublished file from search results
                if (isset($value->state) && intval($value->state) === 0) {
                    unset($results[$key]);
                    continue;
                }
            }

            // Do not search file from exclude categories
            if (isset($value->parent->id) && in_array((string) $value->parent->id, $excludes) && !isset($filters['isAdminSearch'])) {
                unset($results[$key]);
                continue;
            }

            // Filter by extension
            if (isset($value->ext) && $isTypeFilter && !in_array($value->ext, $types)) {
                unset($results[$key]);
                continue;
            }

            // Filter by weight
            if (!empty($filters['wfrom']) && !empty($filters['wto'])) {
                $size = WpfdAddonHelper::getFileSizeHandle($size);
                $fromSize = floatval($filters['wfrom']);
                $toSize = floatval($filters['wto']);
                if ($size < $fromSize || $size > $toSize) {
                    unset($results[$key]);
                    continue;
                }
            } elseif (!empty($filters['wfrom']) && empty($filters['wto'])) {
                $size = WpfdAddonHelper::getFileSizeHandle($size);
                $fromSize = floatval($filters['wfrom']);
                if ($size < $fromSize) {
                    unset($results[$key]);
                    continue;
                }
            } elseif (empty($filters['wfrom']) && !empty($filters['wto'])) {
                $size = WpfdAddonHelper::getFileSizeHandle($size);
                $toSize = floatval($filters['wto']);
                if ($size > $toSize) {
                    unset($results[$key]);
                    continue;
                }
            }

            // Clean keyword filtering
            $fileTitle = isset($value->title) ? $value->title : '';
            if ($replaceHyphen) {
                $fileTitle = preg_replace('/[-_]/', ' ', $fileTitle);
            }

            if (isset($filters['isAdminSearch']) && strpos($filters['q'], '&') !== false) {
                $fileTitle = str_replace('&amp;', '&', $fileTitle);
            }

            if (isset($filters['q']) && $filters['q'] !== '') {
                $filters['q'] = str_replace('\\', '', str_replace('\'', '', $filters['q']));
            }

            $fileTitle = str_replace('&#039;', '', $fileTitle);

            if (isset($filters['q']) && $filters['q'] !== ''
                && $fileTitle !== '' && strpos(strtolower($fileTitle), strtolower($filters['q'])) === false) {
                unset($results[$key]);
                continue;
            }

            $value->created  = mysql2date(
                WpfdBase::loadValue($params, 'date_format', get_option('date_format')),
                $value->created_time
            );
            $value->modified = mysql2date(
                WpfdBase::loadValue($params, 'date_format', get_option('date_format')),
                $value->modified_time
            );

            $category = get_term($value->catid, 'wpfd-category');

            if (!empty($rewrite_rules)) {
                if (strpos($perlink, 'index.php')) {
                    $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $value->catid . '/';
                    $linkdownload .= $category->slug . '/' . $value->id . '/' . $value->post_title;
                    $value->linkdownload = $linkdownload;
                } else {
                    $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $value->catid . '/' . $category->slug;
                    $linkdownload .= '/' . $value->id . '/' . $value->post_title;
                    $value->linkdownload = $linkdownload;
                }

                if ($value->ext && !$rmdownloadext) {
                    $value->linkdownload .= '.' . $value->ext;
                }
            } else {
                $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=file.download';
                $linkdownload .= '&wpfd_category_id=' . $value->catid . '&wpfd_file_id=' . $value->id;
                $value->linkdownload = $linkdownload;
            }

            $remote_url = (isset($file_meta) && isset($file_meta['remote_url']) && ((int) $file_meta['remote_url'] === 1)) ? true : false;
            if ($viewer_type !== 'no' &&
                in_array($value->ext, $extension_viewer) &&
                ($remote_url === false)) {
                $value->viewer_type = $viewer_type;
                $token = $modelToken->getOrCreateNew();
                $value->viewerlink = WpfdHelperFile::isMediaFile($value->ext) ?
                    WpfdHelperFile::getMediaViewerUrl($value->id, $value->catid, $value->ext, $token)
                    : WpfdHelperFile::getViewerUrl($value->id, $value->catid, $token);
            }

            // Google correct date filtering
            if ($isDateFilters) {
                if (isset($filters['cfrom']) && $filters['cfrom'] !== '') {
                    if (strtotime($value->created) < strtotime($filters['cfrom'])) {
                        continue;
                    }
                }
                if (isset($filters['cto']) && $filters['cto'] !== '') {
                    if (strtotime($value->created) > strtotime($filters['cto'])) {
                        continue;
                    }
                }
                if (isset($filters['ufrom']) && $filters['ufrom'] !== '') {
                    if (strtotime($value->modified) < strtotime($filters['ufrom'])) {
                        continue;
                    }
                }
                if (isset($filters['uto']) && $filters['uto'] !== '') {
                    if (strtotime($value->modified) > strtotime($filters['uto'])) {
                        continue;
                    }
                }
                $dateFilterResults[] = $value;
            }
        }
    } else {
        $results = array();
    }

    return $isDateFilters ? $dateFilterResults : $results;
}


/**
 * Check if category and file id are correct
 *
 * @param string $termId Term id
 * @param string $fileId File id
 *
 * @return boolean
 */
function wpfdAddonDownloadCheckGoogleDriveCategory($termId, $fileId)
{
    $cloud_id = WpfdAddonHelper::getGoogleDriveIdByTermId($termId);
    $googleCate = new WpfdAddonGoogleDrive;
    if ($googleCate->checkFileFolderValid($fileId, $cloud_id)) {
        return $termId;
    }
    return false;
}
/**
 * Add info to file
 *
 * @param array   $files                    Files
 * @param integer $termId                   Term id
 * @param string  $googleDriveCategoryTitle Google drive category title
 * @param string  $token                    Security Token
 *
 * @return mixed
 */
function wpfdAddonAddInfosToFiles($files, $termId, $googleDriveCategoryTitle, $token)
{
    Application::getInstance('Wpfd');
    $modelConfig = Model::getInstance('configfront');
    if (method_exists($modelConfig, 'getGlobalConfig')) {
        $params = $modelConfig->getGlobalConfig();
    } else {
        $params = $modelConfig->getConfig();
    }

    $config      = get_option('_wpfd_global_config');
    if (empty($config) || empty($config['uri'])) {
        $seo_uri = 'download';
    } else {
        $seo_uri = rawurlencode($config['uri']);
    }
    $viewer_type   = WpfdBase::loadValue($params, 'use_google_viewer', 'lightbox');
    $perlink       = get_option('permalink_structure');
    $rewrite_rules = get_option('rewrite_rules');

    if (empty($config) || !isset($config['rmdownloadext'])) {
        $rmdownloadext = false;
    } else {
        $rmdownloadext = ((int) $config['rmdownloadext'] === 1) ? true : false;
    }

    $extension_list   = 'png,jpg,pdf,ppt,pptx,doc,docx,xls,xlsx,dxf,ps,eps,xps,psd,tif,tiff,bmp,svg,pages,ai,dxf,ttf,txt,mp3,mp4';
    $extension_viewer = explode(',', WpfdBase::loadValue($params, 'extension_viewer', $extension_list));
    $extension_viewer = array_map('trim', $extension_viewer);
    if (is_array($files)) {
        $filex = array();
        foreach ($files as $file) {
            if (!empty($rewrite_rules)) {
                $downloadFileTitle = str_replace('\\', '-', str_replace('/', '-', $file->post_title));
                $downloadFileTitle = str_replace('%', '-', $downloadFileTitle);
                if (strpos($perlink, 'index.php')) {
                    $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $termId . '/';
                    $linkdownload .= $googleDriveCategoryTitle . '/' . $file->id . '/' . $downloadFileTitle;
                    $file->linkdownload = $linkdownload;
                } else {
                    $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $termId . '/';
                    $linkdownload .= $googleDriveCategoryTitle . '/' . $file->id . '/' . $downloadFileTitle;
                    $file->linkdownload = $linkdownload;
                }
                if ($file->ext && !$rmdownloadext) {
                    $file->linkdownload .= '.' . $file->ext;
                }
            } else {
                $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=file.download';
                $linkdownload .= '&wpfd_category_id=' . $termId . '&wpfd_file_id=' . $file->id;
                $file->linkdownload = $linkdownload;
            }
            $remote_url = (isset($file_meta) && isset($file_meta['remote_url']) && ((int) $file_meta['remote_url'] === 1)) ? true : false;
            if ($viewer_type !== 'no' &&
                in_array($file->ext, $extension_viewer) &&
                ($remote_url === false)) {
                $file->viewer_type = $viewer_type;
                $file->viewerlink  = WpfdHelperFile::isMediaFile($file->ext) ?
                    WpfdHelperFile::getMediaViewerUrl($file->id, $termId, $file->ext, $token)
                    : WpfdHelperFile::getViewerUrl($file->id, $termId, $token);
            }
            $file->ggterm_id = $termId;
            $file->catid     = $termId;
            $filex[] = apply_filters('wpfda_file_info', $file, 'googleDrive');
        }
        return $filex;
    }

    return $files;
}
