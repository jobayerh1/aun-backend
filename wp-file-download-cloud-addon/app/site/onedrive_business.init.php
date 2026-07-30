<?php
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;

if (!class_exists('WpfdAddonHelper')) {
    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/helpers/WpfdHelper.php';
}
if (!class_exists('WpfdAddonOneDriveBusiness')) {
    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonOneDriveBusiness.php';
}
// Check if onedrive is connected
$onedriveConfig = WpfdAddonHelper::getAllOneDriveBusinessConfigs();
$onedrive = new WpfdAddonOneDriveBusiness;
$isOnedriveAuthenticated = $onedrive->checkAuth();
if ($isOnedriveAuthenticated) {
    add_filter('wpfdAddonCategoryFrom', 'wpfdAddonCategoryFrom', 10, 1);
    add_filter('wpfdAddonDownloadCheckOneDriveBusinessCategory', 'wpfdAddonDownloadCheckOneDriveBusinessCategory', 10, 2);
    add_filter('wpfdAddonDownloadOneDriveBusinessFile', 'wpfdAddonDownloadOneDriveBusinessFile', 10, 2);
    add_filter('wpfdAddonGetListOneDriveBusinessFile', 'wpfdAddonGetListOneDriveBusinessFile', 10, 5);
    add_filter('wpfdAddonGetOneDriveBusinessFile', 'wpfdAddonGetOneDriveBusinessFile', 10, 3);
    add_filter('wpfd_level_category_onedrive_business', 'wpfdAddonLevelCategoryOneDriveBusiness', 9, 1);
    add_filter('wpfdAddonSearchOneDriveBusiness', 'wpfdAddonSearchOneDriveBusiness', 10, 1);
    add_filter('wpfd_addon_onedrive_business_connected', '__return_true');
    add_filter('wpfdAddonSaveOneDriveBusinessImg', 'wpfdAddonSaveOneDriveBusinessImg', 10, 2);
}
/**
 * Get leve category OneDrive
 *
 * @param WP_Term $category Category
 *
 * @return mixed
 */
function wpfdAddonLevelCategoryOneDriveBusiness($category)
{
    $onedrive_id = WpfdAddonHelper::getOneDriveBusinessIdByTermId($category->term_id);
    if ($onedrive_id) {
        $category->wp_term_id = $category->term_id;
        $category->wp_parent = $category->parent;
        $category->term_id = $onedrive_id;
        $category->parent = WpfdAddonHelper::getOneDriveBusinessIdByTermId($category->parent);
    }
    return $category;
}
/**
 * Check if category and file id are correct
 *
 * @param integer $termId Term id
 * @param string  $fileId File id
 *
 * @return boolean
 */
function wpfdAddonDownloadCheckOneDriveBusinessCategory($termId, $fileId)
{
    $onderive_id = WpfdAddonHelper::getOneDriveBusinessIdByTermId($termId);
    $onedriveCate = new WpfdAddonOneDriveBusiness();
    if ($onedriveCate->checkFileFolderValid($fileId, $onderive_id)) {
        return $termId;
    }
    return false;
}

/**
 * Save a file image
 *
 * @param string $fileId       File id
 * @param string $previewsPath Previews folder path on server
 *
 * @return boolean|string
 */
function wpfdAddonSaveOneDriveBusinessImg($fileId, $previewsPath)
{
    $onedriveCate = new WpfdAddonOneDriveBusiness;
    return $onedriveCate->getOneDriveBusinessImg(
        $fileId,
        $previewsPath
    );
}

/**
 * Download OneDrive file
 *
 * @param string $fileId File id
 *
 * @return boolean|stdClass
 */
function wpfdAddonDownloadOneDriveBusinessFile($fileId)
{
    $onedriveCate = new WpfdAddonOneDriveBusiness();
    return $onedriveCate->downloadFile($fileId);
}

/**
 * Get list OneDrive file
 *
 * @param integer $termId       Term id
 * @param string  $ordering     Ordering
 * @param string  $orderingdir  Ordering direction
 * @param string  $categorySlug Category slug
 * @param string  $token        Secutiry token
 *
 * @return mixed
 */
function wpfdAddonGetListOneDriveBusinessFile($termId, $ordering, $orderingdir, $categorySlug, $token)
{
    $onedriveCate = new WpfdAddonOneDriveBusiness();
    $listFiles = $onedriveCate->listFiles(WpfdAddonHelper::getOneDriveBusinessIdByTermId($termId), $ordering, $orderingdir);
    return wpfdAddonOneDriveAddInfosToFiles($listFiles, $termId, $categorySlug, $token);
}
/**
 * Get a file OneDrive Business
 *
 * @param string  $fileId     File id
 * @param integer $categoryId Term id
 * @param string  $token      Security token
 *
 * @return array|boolean
 */
function wpfdAddonGetOneDriveBusinessFile($fileId, $categoryId, $token)
{
    $onedriveCate = new WpfdAddonOneDriveBusiness;
    return $onedriveCate->getOneDriveBusinessFileInfos($fileId, $categoryId, $token);
}
/**
 * Get list file from onedriver
 *
 * @param integer $termId      Term Id
 * @param boolean $listIdFlies List id Files?
 *
 * @return array
 */
function wpfdAddonDisplayOneDriveBusinessCategories($termId, $listIdFlies = false)
{
    if (WpfdAddonHelper::getOneDriveBusinessIdByTermId($termId)) {
        Application::getInstance('WpfdAddon');
        $onedriveFile = Model::getInstance('onedrivebusinessfiles');

        return $onedriveFile->getListOneDriveBusinessFiles($termId, $listIdFlies);
    }
}
/**
 * Search query OneDrive
 *
 * @param array $filters Filters
 *
 * @return array
 */
function wpfdAddonSearchOneDriveBusiness($filters)
{
    $onedriveCate = new WpfdAddonOneDriveBusiness();
    $results = $onedriveCate->getAllFilesInAppFolder($filters);
    $excludes = isset($filters['exclude']) ? explode(',', trim($filters['exclude'])) : array();
    Application::getInstance('Wpfd');
    $modelConfig = Model::getInstance('configfront');
    $modelToken = Model::getInstance('tokens');
    $params = $modelConfig->getGlobalConfig();
    $config = get_option('_wpfd_global_config');
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
    $viewer_type = WpfdBase::loadValue($params, 'use_google_viewer', 'lightbox');
    $perlink = get_option('permalink_structure');
    $rewrite_rules = get_option('rewrite_rules');

    $extension_list = 'png,jpg,pdf,ppt,pptx,doc,docx,xls,xlsx,dxf,ps,eps,xps,psd,tif,tiff,bmp,svg,pages,ai,dxf,ttf,txt,mp3,mp4';
    $extension_viewer = explode(',', WpfdBase::loadValue($params, 'extension_viewer', $extension_list));
    $extension_viewer = array_map('trim', $extension_viewer);
    $dateFilterResults  = array();
    $isCreateDateFilter = false;
    $isUpdateDateFilter = false;

    if (isset($filters['cfrom']) && $filters['cfrom'] !== ''
        && isset($filters['cto']) && $filters['cto'] !== '') {
        $isCreateDateFilter = true;
    }

    if (isset($filters['ufrom']) && $filters['ufrom'] !== ''
        && isset($filters['uto']) && $filters['uto'] !== '') {
        $isUpdateDateFilter = true;
    }

    $isDateFilters = ($isCreateDateFilter || $isUpdateDateFilter) ? true : false;

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
    $oneDriveBusinessFileInfos = get_option('_wpfdAddon_onedrive_business_fileInfo', array());
    $onedriveBusinessUnpublishedFiles = array();

    if (is_array($results)) {
        $oneDriveBusinessTags = array();
        if (!empty($oneDriveBusinessFileInfos)) {
            foreach ($oneDriveBusinessFileInfos as $oneDriveBusinessTermId => $oneDriveBusinessFileVal) {
                foreach ($oneDriveBusinessFileVal as $oneDriveBusinessFileId => $odbFileInfo) {
                    if (isset($odbFileInfo['state']) && intval($odbFileInfo['state']) === 0) {
                        $onedriveBusinessUnpublishedFiles[] = $oneDriveBusinessFileId;
                    }

                    if (!isset($odbFileInfo['file_tags'])) {
                        continue;
                    }
                    
                    $oneDriveBusinessTags[$oneDriveBusinessFileId] = $odbFileInfo['file_tags'];
                }
            }
        }

        foreach ($results as $key => $value) {
            $value->cattitle = $value->parentRefName;
            $value->catname = $value->parentRefName;
            $value->catid = WpfdAddonHelper::getTermIdOneDriveBusinessByOneDriveId($value->parentRefId);
            $size = isset($value->size) ? floatval($value->size) : 0;

            if (isset($filters['isAdminSearch']) && $filters['isAdminSearch'] === true) {
                if (isset($filters['waitingForApproval']) && $filters['waitingForApproval'] === true) {
                    $isPending = apply_filters('wpfd_file_upload_pending', $value->ID, $value->catid);
                    if (!$isPending) {
                        unset($results[$key]);
                        continue;
                    }
                }

                // Set file state for searching
                if (!empty($onedriveBusinessUnpublishedFiles) && in_array($value->id, $onedriveBusinessUnpublishedFiles)) {
                    $value->state = 0;
                } else {
                    $value->state = 1;
                }
            } else {
                // Remove unpublished file from search results
                if (!empty($onedriveBusinessUnpublishedFiles) && in_array($value->id, $onedriveBusinessUnpublishedFiles)) {
                    unset($results[$key]);
                    continue;
                }
            }

            // Do not search file from exclude categories
            if (in_array((string) WpfdAddonHelper::replaceIdOneDrive($value->parentRefId), $excludes)) {
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
                $filters['q'] = str_replace('\\', '', $filters['q']);
            }

            if (isset($filters['q']) && $filters['q'] !== ''
                && $fileTitle !== '' && strpos(strtolower($fileTitle), strtolower($filters['q'])) === false) {
                unset($results[$key]);
                continue;
            }

            $value->created = mysql2date(
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
                } else {
                    $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $value->catid . '/' . $category->slug;
                    $linkdownload .= '/' . $value->id . '/' . $value->post_title;
                }

                $value->linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $value->ext : '');
            } else {
                $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=file.download';
                $linkdownload .= '&wpfd_category_id=' . $value->catid . '&wpfd_file_id = ' . $value->id;
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

            // OneDrive Business Tags
            $value->file_tags = (!empty($oneDriveBusinessTags) && isset($oneDriveBusinessTags[$value->id])) ? $oneDriveBusinessTags[$value->id] : '';

            // OneDrive Business correct date filtering
            if ($isCreateDateFilter && $isUpdateDateFilter) {
                if ((strtotime($value->created) >= strtotime($filters['cfrom']))
                    && (strtotime($value->created) <= strtotime($filters['cto']))
                    && (strtotime($value->modified) >= strtotime($filters['ufrom']))
                    && (strtotime($value->modified) <= strtotime($filters['uto']))) {
                    $dateFilterResults[] = $value;
                }
            } elseif ($isCreateDateFilter) {
                if ((strtotime($value->created) >= strtotime($filters['cfrom']))
                    && (strtotime($value->created) <= strtotime($filters['cto']))) {
                    $dateFilterResults[] = $value;
                }
            } elseif ($isUpdateDateFilter) {
                if ((strtotime($value->modified) >= strtotime($filters['ufrom']))
                    && (strtotime($value->modified) <= strtotime($filters['uto']))) {
                    if (!in_array($value, $dateFilterResults)) {
                        $dateFilterResults[] = $value;
                    }
                }
            }
        }
    } else {
        $results = array();
    }

    return $isDateFilters ? $dateFilterResults : $results;
}
/**
 * Add files info OneDrive
 *
 * @param array   $files                 Files
 * @param integer $termId                Term id
 * @param string  $onedriveCategoryTitle Dropbox category title
 * @param string  $token                 Token
 *
 * @return mixed
 */
function wpfdAddonOneDriveAddInfosToFiles($files, $termId, $onedriveCategoryTitle, $token)
{
    Application::getInstance('Wpfd');
    $modelConfig = Model::getInstance('configfront');
    if (method_exists($modelConfig, 'getGlobalConfig')) {
        $params = $modelConfig->getGlobalConfig();
    } else {
        $params = $modelConfig->getConfig();
    }
    $config = get_option('_wpfd_global_config');
    $site_url = site_url();
    if (empty($config) || empty($config['uri'])) {
        $seo_uri = 'download';
    } else {
        $seo_uri = rawurlencode($config['uri']);
    }
    $viewer_type = WpfdBase::loadValue($params, 'use_google_viewer', 'lightbox');
    $perlink = get_option('permalink_structure');
    $rewrite_rules = get_option('rewrite_rules');

    $extension_list = 'png,jpg,pdf,ppt,pptx,doc,docx,xls,xlsx,dxf,ps,eps,xps,psd,tif,tiff,bmp,svg,pages,ai,dxf,ttf,txt,mp3,mp4';
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
                    $linkdownload .= $onedriveCategoryTitle . '/' . $file->id . '/' . $downloadFileTitle;
                    $file->linkdownload = $linkdownload;
                } else {
                    $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $termId . '/';
                    $linkdownload .= $onedriveCategoryTitle . '/' . $file->id . '/' . $downloadFileTitle;
                    $file->linkdownload = $linkdownload;
                }
            } else {
                $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=file.download';
                $linkdownload .= '&wpfd_category_id=' . $termId . '&wpfd_file_id=' . $file->id;
                $file->linkdownload = $linkdownload;
            }
            $remote_url = (isset($file_meta) && isset($file_meta['remote_url']) && ((int) $file_meta['remote_url'] === 1)) ? true : false;

            if ($viewer_type !== 'no' && in_array($file->ext, $extension_viewer) && ($remote_url === false)) {
                $file->viewer_type = $viewer_type;
                $file->viewerlink = WpfdHelperFile::isMediaFile($file->ext) ?
                    WpfdHelperFile::getMediaViewerUrl($file->id, $termId, $file->ext, $token)
                    : WpfdHelperFile::getViewerUrl($file->id, $termId, $token);
            }
            $file->dropterm_id = $termId;
            $file->catid = $termId;
            $file->file_custom_icon = (isset($file->file_custom_icon) && !empty($file->file_custom_icon)) ? $site_url . $file->file_custom_icon : '';
            $filex[] = apply_filters('wpfda_file_info', $file, 'onedrive');
        }
        return $filex;
    }
    return $files;
}
