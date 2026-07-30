<?php
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;

if (!class_exists('WpfdAddonHelper')) {
    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/helpers/WpfdHelper.php';
}
if (!class_exists('WpfdAddonOneDrive')) {
    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonOneDrive.php';
}
// Check if onedrive is connected
$onedriveConfig = WpfdAddonHelper::getAllOneDriveConfigs();
$onedrive = new WpfdAddonOneDrive();
$isOnedriveAuthenticated = $onedrive->checkAuth();
if (isset($onedriveConfig['onedriveConnected']) && $onedriveConfig['onedriveConnected'] === 1 && $isOnedriveAuthenticated) {
    add_filter('wpfdAddonCategoryFrom', 'wpfdAddonCategoryFrom', 10, 1);
    add_filter('wpfdAddonGetListOneDriveFile', 'wpfdAddonGetListOneDriveFile', 10, 5);
    add_filter('wpfdAddonGetOneDriveFile', 'wpfdAddonGetOneDriveFile', 10, 3);
    add_filter('wpfdAddonDownloadOneDriveFile', 'wpfdAddonDownloadOneDriveFile', 10, 2);
    add_filter('wpfd_level_category_onedrive', 'wpfdAddonLevelCategoryOneDrive', 9, 1);
    add_filter('wpfdAddonSearchOneDrive', 'wpfdAddonSearchOneDrive', 10, 1);
    add_filter('wpfdAddonDownloadCheckOneDriveCategory', 'wpfdAddonDownloadCheckOneDriveCategory', 10, 2);
    add_filter('wpfd_addon_onedrive_connected', '__return_true');
    add_filter('wpfdAddonSaveOneDriveImg', 'wpfdAddonSaveOneDriveImg', 10, 2);
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
function wpfdAddonGetListOneDriveFile($termId, $ordering, $orderingdir, $categorySlug, $token)
{
    $onedriveCate = new WpfdAddonOneDrive();
    $listFiles = $onedriveCate->listFiles(WpfdAddonHelper::getOneDriveIdByTermId($termId), $ordering, $orderingdir);
    return wpfdAddonOneDriveAddInfosToFiles($listFiles, $termId, $categorySlug, $token);
}


/**
 * Get a file OneDrive
 *
 * @param string  $fileId     File id
 * @param integer $categoryId Term id
 * @param string  $token      Security token
 *
 * @return array|boolean
 */
function wpfdAddonGetOneDriveFile($fileId, $categoryId, $token)
{
    $onedriveCate = new WpfdAddonOneDrive;
    return $onedriveCate->getOneDriveFileInfos($fileId, $categoryId, $token);
}

/**
 * Save a file image
 *
 * @param string $fileId       File id
 * @param string $previewsPath Previews folder path on server
 *
 * @return boolean|string
 */
function wpfdAddonSaveOneDriveImg($fileId, $previewsPath)
{
    $onedriveCate = new WpfdAddonOneDrive;
    return $onedriveCate->getOneDriveImg(
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
function wpfdAddonDownloadOneDriveFile($fileId)
{
    $onedriveCate = new WpfdAddonOneDrive();
    return $onedriveCate->downloadFile($fileId);
}

/**
 * Get level category OneDrive
 *
 * @param WP_Term $category Category
 *
 * @return mixed
 */
function wpfdAddonLevelCategoryOneDrive($category)
{
    $onedrive_id = WpfdAddonHelper::getOneDriveIdByTermId($category->term_id);
    if ($onedrive_id) {
        $category->wp_term_id = $category->term_id;
        $category->wp_parent = $category->parent;
        $category->term_id = $onedrive_id;
        $category->parent = WpfdAddonHelper::getOneDriveIdByTermId($category->parent);
    }
    return $category;
}

/**
 * Get onedrive versions
 *
 * @param string  $fileId File id
 * @param integer $termId Term id
 *
 * @return mixed
 */
function wpfdAddonOneDriveGetListVersions($fileId, $termId)
{
    Application::getInstance('WpfdAddon');
    $cloudFile = Model::getInstance('onedrivefiles');
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
 * Search query OneDrive
 *
 * @param array $filters Filters
 *
 * @return array
 */
function wpfdAddonSearchOneDrive($filters)
{
    $onedriveCate = new WpfdAddonOneDrive();
    $results = $onedriveCate->getAllFilesInAppFolder($filters);
    $excludes = isset($filters['exclude']) ? explode(',', trim($filters['exclude'])) : array();
    Application::getInstance('Wpfd');
    $modelConfig = Model::getInstance('configfront');
    $modelToken = Model::getInstance('tokens');
    if (method_exists($modelConfig, 'getGlobalConfig')) {
        $params = $modelConfig->getGlobalConfig();
    } else {
        $params = $modelConfig->getConfig();
    }
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

    if ((isset($filters['cfrom']) && $filters['cfrom'] !== '')
        || (isset($filters['cto']) && $filters['cto'] !== '')) {
        $isCreateDateFilter = true;
    }

    if ((isset($filters['ufrom']) && $filters['ufrom'] !== '')
        || (isset($filters['uto']) && $filters['uto'] !== '')) {
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
    $onedriveFileInfos = get_option('_wpfdAddon_onedrive_fileInfo', array());
    $onedriveUnpublishedFiles = array();

    if (is_array($results)) {
        $odTags = array();
        $inserted = array();
        if (!empty($onedriveFileInfos)) {
            foreach ($onedriveFileInfos as $odTermId => $odFileInfos) {
                foreach ($odFileInfos as $odFileId => $odFileInfo) {
                    if (isset($odFileInfo['state']) && intval($odFileInfo['state']) === 0) {
                        $onedriveUnpublishedFiles[] = $odFileId;
                    }

                    if (!isset($odFileInfo['file_tags'])) {
                        continue;
                    }

                    $odTags[$odFileId] = $odFileInfo['file_tags'];
                }
            }
        }

        foreach ($results as $key => $value) {
            $value->cattitle = $value->parentRefName;
            $value->catname = $value->parentRefName;
            $value->catid = WpfdAddonHelper::getTermIdOneDriveByOneDriveId($value->parentRefId);
            $size = isset($value->size) ? floatval($value->size) : 0;

            if (!empty($inserted) && in_array($value->id, $inserted)) {
                unset($results[$key]);
                continue;
            }

            if (isset($filters['isAdminSearch']) && $filters['isAdminSearch'] === true) {
                if (isset($filters['waitingForApproval']) && $filters['waitingForApproval'] === true) {
                    $isPending = apply_filters('wpfd_file_upload_pending', $value->ID, $value->catid);
                    if (!$isPending) {
                        unset($results[$key]);
                        continue;
                    }
                }

                // Set file state for searching
                if (!empty($onedriveUnpublishedFiles) && in_array($value->id, $onedriveUnpublishedFiles)) {
                    $value->state = 0;
                } else {
                    $value->state = 1;
                }
            } else {
                // Remove unpublished file from search results
                if (!empty($onedriveUnpublishedFiles) && in_array($value->id, $onedriveUnpublishedFiles)) {
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
            $value->file_custom_icon = (isset($value->file_custom_icon) && !empty($value->file_custom_icon)) ? site_url() . $value->file_custom_icon : '';

            // Tags
            $value->file_tags = (!empty($odTags) && isset($odTags[$value->id])) ? $odTags[$value->id] : '';

            // OneDrive correct date filtering
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

            $inserted[] = $value->id;
        }
    } else {
        $results = array();
    }

    return $isDateFilters ? $dateFilterResults : $results;
}

/**
 * Check if category and file id are correct
 *
 * @param integer $termId Term id
 * @param string  $fileId File id
 *
 * @return boolean
 */
function wpfdAddonDownloadCheckOneDriveCategory($termId, $fileId)
{

    $onderive_id = WpfdAddonHelper::getOneDriveIdByTermId($termId);
    $onedriveCate = new WpfdAddonOneDrive();
    if ($onedriveCate->checkFileFolderValid($fileId, $onderive_id)) {
        return $termId;
    }
    return false;
}
