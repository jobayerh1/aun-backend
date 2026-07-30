<?php
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;

if (!class_exists('WpfdAddonHelper')) {
    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/helpers/WpfdHelper.php';
}
if (!class_exists('WpfdAddonAws')) {
    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonAws.php';
}
$awsboxConfig = WpfdAddonHelper::getAllAwsConfigs();
if (isset($awsboxConfig['awsKey']) && !empty($awsboxConfig['awsSecret']) && isset($awsboxConfig['awsConnected']) && $awsboxConfig['awsConnected']) {
    add_filter('wpfdAddonCategoryFrom', 'wpfdAddonCategoryFrom', 10, 1);
    add_filter('wpfdAddonGetAwsPathByTermId', 'wpfdAddonGetAwsPathByTermId', 10, 1);
    add_filter('wpfdAddonGetListAwsFile', 'wpfdAddonGetListAwsFile', 10, 5);
    add_filter('wpfdAddonGetAwsFile', 'wpfdAddonGetAwsFile', 10, 3);
    add_filter('wpfdAddonDownloadAwsFile', 'wpfdAddonDownloadAwsFile', 10, 2);
    add_filter('wpfd_level_category_aws', 'wpfdAddonLevelCategoryAws', 9, 1);
    add_filter('wpfdAddonSearchAws', 'wpfdAddonSearchAws', 10, 1);
    add_filter('wpfdAddonDownloadCheckAwsCategory', 'wpfdAddonDownloadCheckAwsCategory', 10, 2);
    add_filter('wpfd_addon_aws_connected', '__return_true');
    add_filter('wpfdAddonSaveAwsImg', 'wpfdAddonSaveAwsImg', 10, 2);
}



/**
 * Get files in AWS
 *
 * @param integer $termId       Term id
 * @param string  $ordering     Ordering
 * @param string  $orderingdir  Ordering direction
 * @param string  $categorySlug Category slug
 * @param string  $token        Secutiry token
 *
 * @return mixed
 */
function wpfdAddonGetListAwsFile($termId, $ordering, $orderingdir, $categorySlug, $token)
{
    $awsCate = new WpfdAddonAws;
    $listFiles = $awsCate->listAwsFiles(
        WpfdAddonHelper::getAwsPathByTermId($termId),
        $termId,
        $ordering,
        $orderingdir
    );
    return wpfdAddonAwsAddInfosToFiles($listFiles, $termId, $categorySlug, $token);
}


/**
 * Get a file in AWS
 *
 * @param string  $fileId     File id
 * @param integer $categoryId Term id
 * @param string  $token      Security token
 *
 * @return array|boolean
 */
function wpfdAddonGetAwsFile($fileId, $categoryId, $token)
{
    $awsCate = new WpfdAddonAws;
    return $awsCate->getAwsFileInfos(
        $fileId,
        WpfdAddonHelper::getAwsPathByTermId($categoryId),
        $categoryId,
        $token
    );
}

/**
 * Save a file image
 *
 * @param string $fileId       File id
 * @param string $previewsPath Previews folder path on server
 *
 * @return boolean|string
 */
function wpfdAddonSaveAwsImg($fileId, $previewsPath)
{
    $awsCate = new WpfdAddonAws;
    return $awsCate->getAwsImg(
        $fileId,
        $previewsPath
    );
}

/**
 * Download file in AWS
 *
 * @param string  $fileId File id
 * @param integer $catId  Term id
 *
 * @return array
 */
function wpfdAddonDownloadAwsFile($fileId, $catId)
{
    $awsCate = new WpfdAddonAws;
    return $awsCate->downloadAws($fileId, $catId);
}

/**
 * Search query in AWS
 *
 * @param array $filters Filters
 *
 * @return array
 */
function wpfdAddonSearchAws($filters)
{
    $awsCate = new WpfdAddonAws;
    $results = $awsCate->getAllFilesInAppFolder($filters);
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
    $viewer_type = WpfdBase::loadValue($params, 'use_google_viewer', 'lightbox');
    $perlink = get_option('permalink_structure');
    $rewrite_rules = get_option('rewrite_rules');
    if (empty($config) || !isset($config['rmdownloadext'])) {
        $rmdownloadext = false;
    } else {
        $rmdownloadext = ((int) $config['rmdownloadext'] === 1) ? true : false;
    }
    $extension_list = 'png,jpg,pdf,ppt,pptx,doc,docx,xls,xlsx,dxf,ps,eps,xps,psd,tif,tiff,bmp,svg,pages,ai,dxf,ttf,txt,mp3,mp4';
    $extension_viewer = explode(',', WpfdBase::loadValue($params, 'extension_viewer', $extension_list));
    $extension_viewer = array_map('trim', $extension_viewer);
    $dateFilterResults = array();
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

    if (is_array($results)) {
        foreach ($results as $key => $value) {
            $pathFolder          = $value->dirname;
            $fpath               = pathinfo($pathFolder);
            $value->cattitle     = $fpath['basename'];
            $value->catname      = $fpath['basename'];
            $value->catid        = WpfdAddonHelper::getTermIdByAwsPath($pathFolder);
            $value->awsCatId = WpfdAddonHelper::getAwsIdByTermId($value->catid);
            $size                = isset($value->size) ? floatval($value->size) : 0;

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
            if (in_array((string) $value->awsCatId, $excludes)) {
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

            $category = get_term($value->catid, 'wpfd-category');
            if (!empty($rewrite_rules)) {
                $hashKey = md5($value->id);
                if (strpos($perlink, 'index.php')) {
                    $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $value->catid . '/';
                    $linkdownload .= $value->catname . '/' . (!$rmdownloadext ? $hashKey : $value->ext) . '/' . $value->s3title;
                } else {
                    $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $value->catid . '/';
                    $linkdownload .= $value->catname . '/' . (!$rmdownloadext ? $hashKey : $value->ext) . '/' . $value->s3title;
                }

                $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $file->ext : '');
                $value->linkdownload = $linkdownload;
            } else {
                $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=file.download';
                $linkdownload .= '&wpfd_category_id=' . $value->catid . '&wpfd_file_id=' . $value->id;
                $value->linkdownload = $linkdownload;
            }

            $remote_url = (isset($file_meta) && isset($file_meta['remote_url']) && ((int) $file_meta['remote_url'] === 1)) ? true : false;
            if ($viewer_type !== 'no' && in_array($value->ext, $extension_viewer)
                && ($remote_url === false)) {
                $value->viewer_type = $viewer_type;
                $token = $modelToken->getOrCreateNew();
                $value->viewerlink = WpfdHelperFile::isMediaFile($value->ext) ?
                    WpfdHelperFile::getMediaViewerUrl($value->id, $value->catid, $value->ext, $token)
                    : WpfdHelperFile::getViewerUrl($value->id, $value->catid, $token);
            }

            // AWS correct date filtering
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
 * Check if $file key and Category ID are correct or not
 *
 * @param integer $term_id      Term id
 * @param string  $awsObjectKey Object key
 *
 * @return boolean
 */
function wpfdAddonDownloadCheckAwsCategory($term_id, $awsObjectKey)
{
    try {
        $awsCate = new WpfdAddonAws;
        $awsConfig = $awsCate->config;
        $args = array(
            'Bucket' => $awsConfig['awsBucketName'],
            'Key'   => $awsObjectKey
        );
        $fileInfo = $awsCate->getObjectInfo($args);
        if (empty($fileInfo)) {
            return false;
        }
    } catch (Exception $e) {
        return false;
    }
    return $term_id;
}

/**
 * Add file info
 *
 * @param array   $files            Files
 * @param integer $termId           Term id
 * @param string  $awsCategoryTitle AWS category title
 * @param string  $token            Token
 *
 * @return mixed
 */
function wpfdAddonAwsAddInfosToFiles($files, $termId, $awsCategoryTitle, $token)
{
    if (!class_exists('WpfdBase')) {
        include_once WPFDA_PLUGIN_DIR_PATH . '/app/admin/classes/WpfdBase.php';
    }

    Application::getInstance('Wpfd');
    $modelConfig = Model::getInstance('configfront');
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
    $viewer_type = WpfdBase::loadValue($params, 'use_google_viewer', 'lightbox');
    $perlink = get_option('permalink_structure');
    $rewrite_rules = get_option('rewrite_rules');

    if (empty($config) || !isset($config['rmdownloadext'])) {
        $rmdownloadext = false;
    } else {
        $rmdownloadext = ((int) $config['rmdownloadext'] === 1) ? true : false;
    }

    $extension_list = 'png,jpg,pdf,ppt,pptx,doc,docx,xls,xlsx,dxf,ps,eps,xps,psd,tif,tiff,bmp,svg,pages,ai,dxf,ttf,txt,mp3,mp4';
    $extension_viewer = explode(',', WpfdBase::loadValue($params, 'extension_viewer', $extension_list));
    $extension_viewer = array_map('trim', $extension_viewer);
    if (is_array($files)) {
        $filex = array();
        foreach ($files as $file) {
            if (!empty($rewrite_rules)) {
                $hashKey = md5($file->id);
                if (strpos($perlink, 'index.php')) {
                    $linkdownload       = get_site_url() . '/index.php/' . $seo_uri . '/' . $termId . '/';
                    $linkdownload       .= $awsCategoryTitle . '/' . (!$rmdownloadext ? $hashKey : $file->ext) . '/' . $file->s3title;
                    $file->linkdownload = $linkdownload;
                } else {
                    $linkdownload       = get_site_url() . '/' . $seo_uri . '/' . $termId . '/';
                    $linkdownload       .= $awsCategoryTitle . '/' . (!$rmdownloadext ? $hashKey : $file->ext) . '/' . $file->s3title;
                    $file->linkdownload = $linkdownload;
                }
                if ($file->ext && !$rmdownloadext) {
                    $file->linkdownload .= '.' . $file->ext;
                }
            } else {
                $linkdownload       = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=file.download';
                $linkdownload       .= '&wpfd_category_id=' . $termId . '&wpfd_file_id=' . $file->id;
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

            $file->awsterm_id = $termId;
            $file->catid = $termId;
            $filex[] = apply_filters('wpfda_file_info', $file, 'aws');
        }
        return $filex;
    }
    return $files;
}

/**
 * Get list file from AWS
 *
 * @param integer $termId      Term Id
 * @param boolean $listIdFlies List id Files?
 *
 * @return array
 */
function wpfdAddonDisplayAwsCategories($termId, $listIdFlies = false)
{
    if (WpfdAddonHelper::getAwsPathByTermId($termId)) {
        Application::getInstance('WpfdAddon');
        $awsFile = Model::getInstance('awsfiles');
        return $awsFile->getListAwsFiles($termId, $listIdFlies);
    }
}

/**
 * Get AWS path by termID
 *
 * @param integer $termId Term id
 *
 * @return boolean|string
 */
function wpfdAddonGetAwsPathByTermId($termId)
{
    $result = WpfdAddonHelper::getAwsIdByTermId($termId);

    return $result;
}

/**
 * Get category in AWS
 *
 * @param WP_Term $category Category
 *
 * @return mixed
 */
function wpfdAddonLevelCategoryAws($category)
{
    $aws_id = WpfdAddonHelper::getAwsIdByTermId($category->term_id);
    if ($aws_id) {
        $category->wp_term_id = $category->term_id;
        $category->wp_parent = $category->parent;
        $category->term_id = $aws_id;
        $category->parent = WpfdAddonHelper::getAwsIdByTermId($category->parent);
    }
    return $category;
}
