<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

// no direct access
defined('ABSPATH') || die();

use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Utilities;

/**
 * Class WpfdAddonGoogleDrive
 */
class WpfdAddonGoogleDrive
{
    /**
     * Params
     *
     * @var $param
     */
    protected $params;

    /**
     * Cache some query
     *
     * @var boolean
     */
    protected $cache = false;

    /**
     * Cloud name
     *
     * @var string
     */
    protected $cloudName = 'googleDrive';

    /**
     * Full file resources params
     *
     * @var string $fullFileParams
     */
    protected $fullFileParams = 'id,name,size,fileExtension,description,originalFilename,createdTime,modifiedTime,appProperties,exportLinks,webContentLink';

    /**
     * Simple file resources params
     *
     * @var string
     */
    protected $fileParams = 'id,name,size,fileExtension';

    /**
     * Last error
     *
     * @var $lastError
     */
    protected $lastError;

    /**
     * Drive type
     *
     * @var string
     */
    protected $type = 'googleDrive';

    /**
     *  WpfdAddonGoogleDrive construct
     */
    public function __construct()
    {
        set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
        require_once WPFDA_PLUGIN_DIR_PATH  . 'lib/prod_vendor/autoload.php';
        $this->loadParams();

        $googlePush = (int) get_option('_wpfd_google_watch_changes', 0);
        if ($googlePush === 1) {
            $this->cache = true;
        }
    }

    /**
     * Cache Defer
     *
     * @param boolean $value True enable cache. False disable cache
     *
     * @return void
     */
    public function cacheDefer($value = false)
    {
        $this->cache = $value;
    }

    /**
     * Get cloud name
     *
     * @return string
     */
    public function getCloudName()
    {
        return $this->cloudName;
    }
    /**
     * Get all google drive configs
     *
     * @return array
     */
    public function getAllCloudConfigs()
    {
        return WpfdAddonHelper::getAllCloudConfigs();
    }

    /**
     * Save google drive configs
     *
     * @param array $data Config data
     *
     * @return boolean false if value was not updated
     *                 true if value was updated.
     */
    public function saveCloudConfigs($data)
    {
        return WpfdAddonHelper::saveCloudConfigs($data);
    }

    /**
     * Get data config by server name
     *
     * @param string $name Data name to get
     *
     * @return array|null
     */
    public function getDataConfigBySeverName($name)
    {
        return WpfdAddonHelper::getDataConfigBySeverName($name);
    }

    /**
     * Get last error
     *
     * @return mixed
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Load params
     *
     * @return void
     */
    protected function loadParams()
    {
        $params                             = $this->getDataConfigBySeverName('google');
        $this->params                       = new stdClass();
        $this->params->google_client_id     = isset($params['googleClientId']) ? $params['googleClientId'] : '';
        $this->params->google_client_secret = isset($params['googleClientSecret']) ? $params['googleClientSecret'] : '';
        $this->params->google_credentials   = isset($params['googleCredentials']) ? $params['googleCredentials'] : '';
    }

    /**
     * Save params config
     *
     * @return void
     */
    protected function saveParams()
    {
        $params                       = $this->getAllCloudConfigs();
        $params['googleClientId']     = $this->params->google_client_id;
        $params['googleClientSecret'] = $this->params->google_client_secret;
        $params['googleCredentials']  = $this->params->google_credentials;
        $params['googleConnectedBy']  = get_current_user_id();
        $this->saveCloudConfigs($params);
    }

    /**
     * Get Authorisation Url
     *
     * @return string
     */
    public function getAuthorisationUrl()
    {
        $client = new Google\Client();
        $client->setClientId($this->params->google_client_id);
        $redirecturi = get_admin_url() . 'admin.php?page=wpfdAddon-cloud&task=googledrive.authenticate';
        $client->setRedirectUri($redirecturi);
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');
        $client->setState('');
        $client->setScopes(array(
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile'
        ));
        $tmpUrl = parse_url($client->createAuthUrl());
        $port   = isset($tmpUrl['port']) ? $tmpUrl['port'] : '';
        $query  = explode('&', $tmpUrl['query']);
        $return = $tmpUrl['scheme'] . '://' . $tmpUrl['host'] . $port;
        $return .= $tmpUrl['path'] . '?' . implode('&', $query);

        return $return;
    }

    /**
     * Authenticate
     *
     * @return string
     */
    public function authenticate()
    {
        $code   = Utilities::getInput('code', 'GET', 'none');
        $client = new Google\Client();
        $client->setClientId($this->params->google_client_id);
        $client->setClientSecret($this->params->google_client_secret);
        $redirecturi = get_admin_url() . 'admin.php?page=wpfdAddon-cloud&task=googledrive.authenticate';
        $client->setRedirectUri($redirecturi);

        $client->fetchAccessTokenWithAuthCode($code);
        return json_encode($client->getAccessToken());
    }

    /**
     * Log out
     *
     * @return void
     */
    public function logout()
    {
        $client = new Google\Client();
        $client->setClientId($this->params->google_client_id);
        $client->setClientSecret($this->params->google_client_secret);
        $client->setAccessToken($this->params->google_credentials);
        $client->revokeToken();
    }

    /**
     * Store credential
     *
     * @param string $credentials Credentials tring
     *
     * @return void
     */
    public function storeCredentials($credentials)
    {
        $this->params->google_credentials = $credentials;
        $this->saveParams();
    }

    /**
     * Get credentials
     *
     * @return string
     */
    public function getCredentials()
    {
        return $this->params->google_credentials;
    }

    /**
     * Check authenticate
     *
     * @return boolean
     */
    public function checkAuth()
    {
        try {
            $service = $this->getGoogleService();
            $service->files->generateIds(array('count' => 1));

            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Folder exists
     *
     * @param string $id Folder id
     *
     * @return boolean
     */
    public function folderExists($id)
    {
        try {
            $service = $this->getGoogleService();
            $service->files->get($id);

            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Create new folder google drive
     *
     * @param string      $title    Title
     * @param null|string $parentId Parent id
     *
     * @return boolean|Google_Service_Drive_DriveFile
     */
    public function createFolder($title, $parentId = null)
    {
        $file    = new Google_Service_Drive_DriveFile();
        $file->setName($title);
        $file->setMimeType('application/vnd.google-apps.folder');

        if ($parentId !== null) {
            $file->setParents(array($parentId));
        }
        try {
            $service = $this->getGoogleService();

            return $service->files->create($file, array('fields' => 'id, name'));
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * List files google drive
     *
     * @param string                  $folder_id   Folder id
     * @param string                  $ordering    Ordering
     * @param string                  $direction   Order direction
     * @param boolean                 $listIdFiles List id files
     * @param boolean|WpfdModelTokens $tokenModel  Tokens model
     *
     * @return array|boolean
     */
    public function listFiles($folder_id, $ordering = 'ordering', $direction = 'asc', $listIdFiles = false, $tokenModel = false)
    {
        if (!class_exists('WpfdBase')) {
            include_once WPFDA_PLUGIN_DIR_PATH . '/app/admin/classes/WpfdBase.php';
        }

        if (!class_exists('WpfdCloudCache')) {
            include_once WPFDA_PLUGIN_DIR_PATH . '/app/admin/classes/WpfdCloudCache.php';
        }

        try {
            $service = $this->getGoogleService();
            $q             = "'" . $folder_id;
            $q             .= "' in parents and trashed=false and mimeType != 'application/vnd.google-apps.folder' ";
            if ($this->cache) {
                // Cache query
                $fs = WpfdCloudCache::getTransient($folder_id, $this->cloudName);
                if ($fs === false) {
                    $fs = $service->files->listfiles(array('q' => $q, 'fields' => 'files(' . $this->fullFileParams . ')', 'pageSize' => 1000));
                    // Cache it
                    WpfdCloudCache::setTransient($folder_id, $fs, $this->cloudName);
                }
            } else {
                $fs = $service->files->listfiles(array('q' => $q, 'fields' => 'files(' . $this->fullFileParams . ')', 'pageSize' => 1000));
            }

            $perlink       = get_option('permalink_structure');
            $rewrite_rules = get_option('rewrite_rules');
            $config        = get_option('_wpfd_global_config');
            if (!empty($config) && !empty($config['date_format'])) {
                $dateFormat = $config['date_format'];
            } else {
                $dateFormat = 'd-m-Y h:i:s';
            }
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

            $extension_viewer_list = 'png,jpg,pdf,ppt,pptx,doc,docx,xls,xlsx,dxf,ps,eps,xps,psd,tif,tiff,bmp,svg,pages,ai,dxf,ttf,txt,mp3,mp4';
            $extension_viewer      = explode(',', WpfdBase::loadValue($config, 'extension_viewer', $extension_viewer_list));
            $extension_viewer      = array_map('trim', $extension_viewer);
            $viewer_type           = WpfdBase::loadValue($config, 'use_google_viewer', 'lightbox');
            $files = array();
            foreach ($fs as $f) {
                if ($listIdFiles && !in_array($f->getId(), $listIdFiles)) {
                    continue;
                }
                $token = '';
                if (false !== $tokenModel) {
                    $token = $tokenModel->getOrCreateNew();
                }

                if ($f->getMimeType() !== 'application/vnd.google-apps.folder') {
                    if ($f instanceof Google_Service_Drive_DriveFile) {
                        $file                   = new stdClass();
                        $file->id               = $f->getId();
                        $file->ID               = $file->id;
                        $file->title            = $f->getOriginalFilename() ? WpfdAddonHelper::stripExt($f->getName()) : $f->getName();
                        $file->post_title       = stripslashes(str_replace('\\', '', $file->title));
                        $file->post_name        = stripslashes(str_replace('\\', '', $file->title));
                        $file->description      = $f->getDescription();
                        $file->ext              = $f->getFileExtension() ? strtolower($f->getFileExtension()) : strtolower(WpfdAddonHelper::getExt($f->getOriginalFilename()));
                        $file->size             = $f->getSize() ? $f->getSize() : 0;
                        $file->created_time     = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($f->getCreatedTime())));
                        $file->modified_time    = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($f->getModifiedTime())));
                        $file->created          = mysql2date($dateFormat, $file->created_time);
                        $file->modified         = mysql2date($dateFormat, $file->modified_time);
                        $file->versionNumber    = '';
                        $file->version          = '';
                        $file->hits             = 0;
                        $file->ordering         = 0;
                        $file->file_custom_icon = '';
                        $file->file_custom_icon_preview = '';
                        $file->catid            = WpfdAddonHelper::getCatIdByCloudId($folder_id);
                        $category               = get_term($file->catid, 'wpfd-category');

                        if (defined('WPFD_GOOGLE_DRIVE_DIRECT') && WPFD_GOOGLE_DRIVE_DIRECT) {
                            $permission = new Google_Service_Drive_Permission();
                            $permission->setType('anyone');
                            $permission->setRole('reader');
                            $service->permissions->create($f->getId(), $permission);
                        }

                        if (!empty($rewrite_rules)) {
                            $downloadFileTitle = str_replace('\\', '-', str_replace('/', '-', $file->post_title));
                            $downloadFileTitle = str_replace('%', '-', $downloadFileTitle);
                            if (strpos($perlink, 'index.php')) {
                                $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $file->catid . '/';
                                $linkdownload .= $category->slug . '/' . $file->id . '/' . $downloadFileTitle;
                            } else {
                                $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $file->catid . '/';
                                $linkdownload .= $category->slug . '/' . $file->id . '/' . $downloadFileTitle;
                            }
                            $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $file->ext : '');
                            $file->linkdownload = $linkdownload;
                        } else {
                            $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=';
                            $linkdownload .= 'file.download&wpfd_category_id=' . $file->catid . '&wpfd_file_id=' . $file->id;
                            $file->linkdownload = $linkdownload;
                        }

                        $properties      = (object) $f->getAppProperties();
                        $file->file_tags = isset($properties->file_tags) ? $properties->file_tags : '';
                        if ($f->getFileExtension() === null && $f->getId() !== '') {
                            $ExportLinks = $f->getExportLinks();
                            if ($ExportLinks !== null) {
                                uksort($ExportLinks, function ($a, $b) {
                                    return strlen($a) < strlen($b);
                                });
                                $ext_tmp   = explode('=', reset($ExportLinks));
                                $file->ext = end($ext_tmp);
                            }
                            $file->created_time  = $f->getCreatedTime();
                            $file->modified_time = $f->getModifiedTime();
                        }
                        $file->versionNumber = isset($properties->versionNumber) ? $properties->versionNumber : (isset($properties->version) ? $properties->version : '');
                        $file->hits = isset($properties->hits) ? $properties->hits : 0;
                        $file->ordering = isset($properties->ordering) ? $properties->ordering : 'asc';
                        $file->state = isset($properties->state) ? $properties->state : 1;
                        $file->canview = isset($properties->canview) ? $properties->canview : '';
                        $file->file_custom_icon = isset($properties->file_custom_icon) ? $properties->file_custom_icon : '';
                        $file->file_custom_icon_preview = isset($properties->file_custom_icon_preview) ? $properties->file_custom_icon_preview : '';
                        $file->file_password = isset($properties->file_password) ? $properties->file_password : '';
                        if ($viewer_type !== 'no' && in_array($file->ext, $extension_viewer)) {
                            $data['viewer_type'] = $viewer_type;
                            $data['viewerlink']  = WpfdHelperFile::isMediaFile($file->ext) ?
                                WpfdHelperFile::getMediaViewerUrl(
                                    $file->ID,
                                    $file->catid,
                                    $file->ext,
                                    $token
                                ) : WpfdHelperFile::getViewerUrl($file->ID, $file->catid, $token);
                        }
                        $open_pdf_in = WpfdBase::loadValue($config, 'open_pdf_in', 0);

                        if ((int) $open_pdf_in === 1 && strtolower($file->ext) === 'pdf') {
                            $file->openpdflink = WpfdHelperFile::getPdfUrl($file->ID, $file->catid, $token) . '&preview=1';
                        }

                        switch ($f->getMimeType()) {
                            case 'application\/vnd.google-apps.spreadsheet':
                                $file->ext = 'xlsx';
                                break;
                            case 'application\/vnd.google-apps.document':
                                $file->ext = 'docx';
                                break;
                            case 'application\/vnd.google-apps.presentation':
                                $file->ext = 'pptx';
                                break;
                        }

                        $files[] = apply_filters('wpfda_file_info', $file, $this->type);
                        unset($file);
                    }
                }
            }

            $files = $this->subvalSort($files, $ordering, $direction);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $files;
    }

    /**
     * Sub val sort
     *
     * @param array  $a         Array to sort
     * @param string $subkey    Key
     * @param string $direction Order direction
     *
     * @return array
     */
    private function subvalSort($a, $subkey, $direction)
    {
        if (empty($a)) {
            return $a;
        }
        foreach ($a as $k => $v) {
            $b[$k] = strtolower($v->$subkey);
        }
        if ($direction === 'asc') {
            asort($b);
        } else {
            arsort($b);
        }
        $c = array();
        foreach ($b as $key => $val) {
            $c[] = $a[$key];
        }

        return $c;
    }

    /**
     * Upload file google drive
     *
     * @param string $filename    File name
     * @param string $fileContent File content
     * @param string $mime        Mime type
     * @param string $id_folder   Folder id
     *
     * @return boolean|Google_Service_Drive_DriveFile
     */
    public function uploadFile($filename, $fileContent, $mime, $id_folder)
    {
        $file   = new Google_Service_Drive_DriveFile();

        $file->setParents(array($id_folder));
        $file->setName($filename);
        $file->setMimeType($mime);

        try {
            $service = $this->getGoogleService();
            $insertedFile = $service->files->create(
                $file,
                array('data' => $fileContent, 'mimeType' => $mime, 'uploadType' => 'media', 'fields' => 'id,name,size,fileExtension,description')
            );

            if ($this->cache) {
                // Clean cache
                WpfdCloudCache::deleteTransient($id_folder, $this->cloudName);
            }

            return $insertedFile;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Upload large files in chunk mode
     *
     * @param string $fileName File name
     * @param string $filePath File path
     * @param string $mime     File mime
     * @param string $parentId Google parent id
     *
     * @throws Exception Fire if errors
     *
     * @return boolean |false|mixed
     */
    public function uploadLargeFile($fileName, $filePath, $mime, $parentId)
    {
        $client = $this->getGoogleClient();
        $service = new Google_Service_Drive($client);

        $file = new Google_Service_Drive_DriveFile();

        $file->setParents(array($parentId));
        $file->setName($fileName);
        $file->setMimeType($mime);

        $fileSize = filesize($filePath);

        /**
         * Set upload chunk size in Mb when upload large file
         *
         * @param integer Chunk size in Mb
         */
        $chunkSize = apply_filters('wpfd_addon_upload_chunk_size', 5); // 5Mb per chunk
        $chunkSizeBytes = $chunkSize * 1024 * 1024;

        $client->setDefer(true);
        $request = $service->files->create($file, array(
            'uploadType' => 'resumable',
            'fields' => 'id,name,size,fileExtension,description'
        ));

        $media = new Google\Http\MediaFileUpload(
            $client,
            $request,
            $mime,
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize($fileSize);
        $media->setChunkSize($chunkSizeBytes);

        $status = false;
        $handle = fopen($filePath, 'rb');

        while (!$status && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }

        $result = false;
        if ($status !== false) {
            $result = $status;
        }

        fclose($handle);

        $client->setDefer(false);

        if ($this->cache) {
            // Clean cache
            WpfdCloudCache::deleteTransient($parentId, $this->cloudName);
        }

        return is_object($result) && isset($result) ? $result : false;
    }

    /**
     * Get file info
     *
     * @param string  $id         File id
     * @param null    $cloud_id   Cloud id
     * @param integer $categoryId Term id
     * @param string  $token      Token key
     *
     * @return array|boolean
     */
    public function getFileInfos($id, $cloud_id = null, $categoryId = 0, $token = '')
    {
        if (!class_exists('WpfdBase')) {
            include_once WPFD_PLUGIN_DIR_PATH . '/app/admin/classes/WpfdBase.php';
        }

        try {
            $service = $this->getGoogleService();

            if ($this->cache) {
                // Cache query
                $file = WpfdCloudCache::getTransient($id, $this->cloudName);
                if ($file === false) {
                    $file = $service->files->get($id, array('fields' => $this->fullFileParams));
                    // Cache it
                    WpfdCloudCache::setTransient($id, $file, $this->cloudName);
                }
            } else {
                $file = $service->files->get($id, array('fields' => $this->fullFileParams));
            }
            // error_log(json_encode($file));

            if ($file instanceof Google_Service_Drive_FileList) {
                return false;
            }

            $config = get_option('_wpfd_global_config');
            if (!empty($config) && !empty($config['date_format'])) {
                $dateFormat = $config['date_format'];
            } else {
                $dateFormat = 'd-m-Y h:i:s';
            }

            if (empty($config) || empty($config['uri'])) {
                $seo_uri = 'download';
            } else {
                $seo_uri = rawurlencode($config['uri']);
            }
            $perlink       = get_option('permalink_structure');
            $rewrite_rules = get_option('rewrite_rules');

            $fileTitle = $file->getOriginalFilename() ? WpfdAddonHelper::stripExt($file->getName()) : $file->getName();
            $fileExt = $file->getFileExtension() ? $file->getFileExtension() : WpfdAddonHelper::getExt($file->getOriginalFilename());
            if (empty($config) || !isset($config['rmdownloadext'])) {
                $rmdownloadext = false;
            } else {
                $rmdownloadext = ((int) $config['rmdownloadext'] === 1) ? true : false;
            }
            if (!empty($rewrite_rules)) {
                if (strpos($perlink, 'index.php')) {
                    $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $categoryId . '/google-driver/';
                    $linkdownload .= $id . '/' . urlencode($fileTitle);
                } else {
                    $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $categoryId . '/google-driver/' . $id;
                    $linkdownload .= '/' . urlencode($fileTitle);
                }
                $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $fileExt : '');
            } else {
                $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=file.download';
                $linkdownload .= '&wpfd_file_id=' . $id . '&wpfd_category_id=' . $categoryId;
            }

            if (!$this->checkParents($file, $cloud_id)) {
                return false;
            }

            $data                  = array();
            $data['linkdownload']  = $linkdownload;
            $data['ID']            = $id;
            $data['id']            = $data['ID'];
            $data['catid']         = $categoryId;
            $data['title']         = $fileTitle;
            $data['post_title']    = stripslashes(htmlspecialchars_decode(wp_slash_strings_only(str_replace('\\', '', $data['title']))));
            $data['post_name']     = stripslashes(htmlspecialchars_decode(wp_slash_strings_only(str_replace('\\', '', $data['title']))));
            $data['description']   = $file->getDescription();
            $data['file']          = $file->getName();
            $data['ext']           = strtolower($fileExt);
            $data['created_time']  = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($file->getCreatedTime())));
            $data['modified_time'] = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($file->getModifiedTime())));
            $data['created']       = mysql2date($dateFormat, $data['created_time']);
            $data['modified']      = mysql2date($dateFormat, $data['modified_time']);
            $properties            = (object) $file->getAppProperties();
            $data['file_tags']     = isset($properties->file_tags) ? $properties->file_tags : '';
            $viewer_type           = WpfdBase::loadValue($config, 'use_google_viewer', 'lightbox');
            $extension_list        = 'png,jpg,pdf,ppt,pptx,doc,docx,xls,xlsx,dxf,ps,eps,xps,psd,tif,tiff,bmp,svg,pages,ai,dxf,ttf,txt,mp3,mp4';
            $extension_viewer      = explode(',', WpfdBase::loadValue($config, 'extension_viewer', $extension_list));
            $extension_viewer      = array_map('trim', $extension_viewer);

            if ($viewer_type !== 'no' && in_array($data['ext'], $extension_viewer)) {
                $data['viewer_type'] = $viewer_type;
                $data['viewerlink']  = WpfdHelperFile::isMediaFile($data['ext']) ?
                    WpfdHelperFile::getMediaViewerUrl(
                        $data['ID'],
                        $categoryId,
                        $data['ext'],
                        $token
                    ) : WpfdHelperFile::getViewerUrl($data['ID'], $categoryId, $token);
            }
            $open_pdf_in = WpfdBase::loadValue($config, 'open_pdf_in', 0);

            if ((int) $open_pdf_in === 1 && strtolower($data['ext']) === 'pdf') {
                $data['openpdflink'] = WpfdHelperFile::getPdfUrl($data['ID'], $categoryId, $token) . '&preview=1';
            }
            $data['hits']                    = isset($properties->hits) ? $properties->hits : 0;
            $data['size']                    = $file->getSize();
            $version                         = isset($properties->version) ? (string) $properties->version : '';
            $data['versionNumber']           = $version;
            $data['version']                 = $version;
            $data['latestVersionNumber']     = isset($properties->latestVersionNumber) ? (int) $properties->latestVersionNumber : false;
            $data['social']                  = isset($properties->social) ? (string) $properties->social : '';
            $data['ordering']                = isset($properties->order) ? (string) $properties->order : 1;
            $data['state']                   = isset($properties->state) ? (string) $properties->state : 1;
            $data['canview']                 = isset($properties->canview) ? $properties->canview : '';
            $data['file_multi_category']     = isset($properties->file_multi_category) ? $properties->file_multi_category : '';
            $data['file_multi_category_old'] = isset($properties->file_multi_category_old) ? $properties->file_multi_category_old : '';
            $data['file_custom_icon']        = isset($properties->file_custom_icon) ? $properties->file_custom_icon : '';
            $data['file_custom_icon_preview'] = isset($properties->file_custom_icon_preview) ? $properties->file_custom_icon_preview : '';
            $data['file_password']           = isset($properties->file_password) ? $properties->file_password : '';
//            $data['versionNumber']           = isset($properties->versionNumber) ? $properties->versionNumber : '';
            if (isset($properties->woo_permission)) {
                $data['woo_permission'] = $properties->woo_permission;
            }
//            $data['version']                 = $data['versionNumber'];
            if ($file->getFileExtension() === null && $file->getId() !== '') {
                $ExportLinks = $file->getExportLinks();
                if ($ExportLinks !== null) {
                    uksort(
                        $ExportLinks,
                        function ($a, $b) {
                            return strlen($a) < strlen($b);
                        }
                    );
                    $ext_tmp               = explode('=', reset($ExportLinks));
                    $data['ext']           = end($ext_tmp);
//                    $data['size']          = 0;
                }
            }

            switch ($file->getMimeType()) {
                case 'application\/vnd.google-apps.spreadsheet':
                    $data['ext'] = 'xlsx';
                    break;
                case 'application\/vnd.google-apps.document':
                    $data['ext'] = 'docx';
                    break;
                case 'application\/vnd.google-apps.presentation':
                    $data['ext'] = 'pptx';
                    break;
            }

            $data = apply_filters('wpfda_file_info', $data, $this->type);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $data;
    }

    /**
     * Count revisions
     *
     * @param string $fileId File id
     *
     * @return integer
     */
    public function getVersionsCount($fileId)
    {
        try {
            $service = $this->getGoogleService();
            $versions = $service->revisions->listRevisions($fileId, array('fields' => 'id,name'));

            return $versions->count();
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return 0;
        }
    }

    /**
     * Save latest automatic increment version number
     *
     * @param array   $file                Google File array
     * @param integer $latestVersionNumber Latest version number
     *
     * @return boolean
     */
    public function saveLatestVersionNumber($file, $latestVersionNumber)
    {
        try {
            $service = $this->getGoogleService();
            $googleFile    = new Google_Service_Drive_DriveFile();
            $properties = new stdClass();
            $properties->latestVersionNumber = $latestVersionNumber;
            $googleFile->setAppProperties($properties);
            $service->files->update($file['ID'], $googleFile);

            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }
    /**
     * Save file info
     *
     * @param array $datas    File information
     * @param null  $cloud_id Cloud id
     *
     * @return boolean
     */
    public function saveFileInfos($datas, $cloud_id = null)
    {
        try {
            $service = $this->getGoogleService();
            $file    = $service->files->get($datas['id'], array(
                'fields' => 'size,name,description,parents,fileExtension,appProperties'
            ));
            $params  = array('uploadType' => 'multipart');

            if (!$this->checkParents($file, $cloud_id)) {
                return false;
            }

            $updatedFile = new Google_Service_Drive_DriveFile();
            if (isset($datas['title'])) {
                $updatedFile->setName($datas['title'] . '.' . $datas['ext']);
            }
            if (isset($datas['description'])) {
                $updatedFile->setDescription($datas['description']);
            }
            if (isset($datas['data'])) {
                $params['data'] = $datas['data'];
            }

            // Update properties
            $google_file_properties = apply_filters('wpfda_googleDrive_properties', array('version', 'hits', 'social', 'state', 'canview', 'file_tags', 'file_multi_category', 'file_multi_category_old', 'file_custom_icon', 'file_custom_icon_preview', 'file_password'));
            $updateProperties = new \stdClass;
            foreach ($google_file_properties as $key) {
                if (!isset($datas[$key])) {
                    continue;
                }
                $updateProperties->{$key} = $datas[$key];
            }
            if (isset($datas['newRevision'])) {
                $params['keepRevisionForever'] = true;
            } else {
                $params['keepRevisionForever'] = false;
            }
            $updatedFile->setAppProperties($updateProperties);

            $service->files->update($datas['id'], $updatedFile, $params);

            // Remove pending files
            if (isset($datas['id']) && isset($datas['state']) && (int)$datas['state'] === 1) {
                $googleUploadUsers = get_option('_wpfdAddon_google_file_user_upload');
                if (!empty($googleUploadUsers) && array_key_exists($datas['id'], $googleUploadUsers)) {
                    unset($googleUploadUsers[$datas['id']]);
                    update_option('_wpfdAddon_google_file_user_upload', $googleUploadUsers);
                }
            }

            // Update custom preview file
            if (isset($datas['id']) && isset($datas['file_custom_icon_preview']) && !is_null($datas['file_custom_icon_preview'])) {
                update_option('_wpfdAddon_file_custom_icon_preview_' .md5($datas['id']), $datas['file_custom_icon_preview']);
            }

            if ($this->cache) {
                // Delete google file cache
                WpfdCloudCache::deleteTransient($datas['id'], $this->cloudName);
                // Delete parent category id cache
                $parents = $file->getParents();
                if (is_array($parents)) {
                    foreach ($parents as $parentId) {
                        WpfdCloudCache::deleteTransient($parentId, $this->cloudName);
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Change file name
     *
     * @param string $id       File id
     * @param string $filename File name
     *
     * @return boolean
     */
    public function changeFilename($id, $filename)
    {
        try {
            $service = $this->getGoogleService();
            $file = new Google_Service_Drive_DriveFile();
            $file->setName($filename);
            $updated = $service->files->update($id, $file, array('fields' => 'id,name,parents'));
            $parents = $updated->getParents();

            if ($this->cache) {
                // Delete google file cache
                WpfdCloudCache::deleteTransient($id, $this->cloudName);
                foreach ($parents as $parentId) {
                    WpfdCloudCache::deleteTransient($parentId, $this->cloudName);
                }
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * File Hist
     *
     * @param string $id File id
     *
     * @return boolean
     */
    public function incrHits($id)
    {
        if (!class_exists('WpfdCloudCache')) {
            $app = Application::getInstance('WpfdAddon');
            $wpfdClassPath = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR;
            require_once $wpfdClassPath . 'WpfdCloudCache.php';
        }

        try {
            $service = $this->getGoogleService();
            $file = $service->files->get($id, array(
                'fields' => 'id,name,appProperties'
            ));
            $properties = $file->getAppProperties();

            $hits = isset($properties['hits']) ? (int) $properties['hits'] : 0;
        } catch (Exception $e) {
            $hits = 0;
        }

        try {
            $file2 = new Google_Service_Drive_DriveFile();
            $newProperties = new \stdClass;
            $newProperties->hits = $hits + 1;
            $file2->setAppProperties($newProperties);
            $returnFile = $service->files->update($id, $file2);
            if (!$returnFile instanceof Google_Service_Drive_DriveFile) {
                return false;
            }
            if ($this->cache) {
                // Delete google file cache
                WpfdCloudCache::deleteTransient($id, $this->cloudName);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
        return true;
    }

    /**
     * Reorder file
     *
     * @param array $files Files
     *
     * @return boolean
     */
    public function reorder($files)
    {
        try {
            $service = $this->getGoogleService();
            foreach ($files as $key => $file) {
                try {
                    $f = new Google_Service_Drive_DriveFile();
                    $newProperties = new \stdClass;
                    $newProperties->order = $key;
                    $service->files->update($file->getId(), $f, array(
                        'appProperties' => $newProperties
                    ));
                } catch (Exception $e) {
                    continue;
                }
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Check if file id and folder id are correct
     *
     * @param string      $id       File id
     * @param null|string $cloud_id Cloud id
     *
     * @return boolean|stdClass
     */
    public function checkFileFolderValid($id, $cloud_id)
    {
        try {
            $service = $this->getGoogleService();
            $file    = $service->files->get($id, array('fields' => 'parents'));

            if (!empty($file)) {
                return $this->checkParents($file, $cloud_id);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return false;
    }

    /**
     * Get Content downloable link for a shared file
     *
     * @param string $id Google File id
     *
     * @return string
     */
    public function getContentDownloadLink($id)
    {
        return sprintf('https://drive.google.com/u/0/uc?id=%s&export=download', $id);
    }
    /**
     * Download large file by chunks
     *
     * @param object         $file        File object
     * @param string         $contentType Content type
     * @param boolean|string $saveToFile  File path
     * @param integer        $preview     Preview
     *
     * @return boolean|string
     */
    public function downloadLargeFile($file, $contentType, $saveToFile = false, $preview = 0)
    {
        /**
         * Trigger download google drive file for customizing
         *
         * @param array|object|mixed $file File infos
         * @param string $contentType File type
         * @param boolean $saveToFile Save file
         * @param integer|boolean $preview Preview file
         */
        do_action('wpfd_google_drive_download_large_file', $file, $contentType, $saveToFile, $preview);

        if (defined('WPFD_GOOGLE_DRIVE_DIRECT') && WPFD_GOOGLE_DRIVE_DIRECT) {
            header('Location: ' . $this->getContentDownloadLink($file->id));
        } else {
            $chunkSizeBytes = 5 * 1024 * 1024;

            if ($saveToFile === false) {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                ob_start();
                header('Content-Type: ' . $contentType);
                if ($preview === 1) {
                    header('Content-Disposition: inline; filename="' . $file->title . '.' . $file->ext . '"');
                } else {
                    header('Content-Disposition: attachment; filename="' . $file->title . '.' . $file->ext . '"');
                }
                header('Expires: 0');
                header('Pragma: public');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Content-Description: File Transfer');
                //header('Content-Length: ' . $file->size);
                if (isset($_SERVER['HTTP_RANGE'])) {
                    list($sizeUnit, $rangeOrig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
                    if ($sizeUnit === 'bytes') {
                        // multiple ranges could be specified at the same time,
                        // but for simplicity only serve the first range
                        // http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
                        $ranges = explode(',', $rangeOrig, 2);
                        if (is_array($ranges) && count($ranges) === 2) {
                            list($range, $extraRanges) = explode(',', $rangeOrig, 2);
                        } else {
                            $range = '';
                        }
                    } else {
                        $range = '';
                        header('HTTP/1.1 416 Requested Range Not Satisfiable');

                        return false;
                    }
                } else {
                    $range = '';
                }
                // figure out download piece from range (if set)
                $seekingRange = explode('-', $range, 2);
                $seekStart = isset($seekingRange[0]) ? $seekingRange[0] : 0;
                $seekEnd = isset($seekingRange[1]) ? $seekingRange[1] : '';
                // set start and end based on range (if set), else set defaults
                // also check for invalid ranges.
                $seekEnd = (empty($seekEnd)) ? ($file->size - 1) : min(abs(intval($seekEnd)), ($file->size - 1));
                $seekStart = (empty($seekStart) || $seekEnd < abs(intval($seekStart))) ?
                    0 : max(abs(intval($seekStart)), 0);
                // Only send partial content header if downloading a piece of the file (IE workaround)
                if ($seekStart > 0 || $seekEnd < ($file->size - 1)) {
                    header('HTTP/1.1 206 Partial Content');
                    header('Content-Range: bytes ' . $seekStart . '-' . $seekEnd . '/' . $file->size);
                    if (stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') === false) {
                        header('Content-Length: ' . ($seekEnd - $seekStart + 1));
                    }
                } else {
                    if (stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') === false) {
                        header('Content-Length: ' . $file->size);
                    }
                }
                header('Accept-Ranges: bytes');

                header('Content-Transfer-Encoding: chunked');
                header('Connection: keep-alive');
                ob_clean();
                flush();
            }

            $client = new Google\Client();
            $client->setClientId($this->params->google_client_id);
            $client->setClientSecret($this->params->google_client_secret);
            $client->setAccessToken($this->params->google_credentials);

            try {
                // Begin Use of MediaFileDownload
                // Call the API with the media download, defer so it doesn't immediately return.
                $client->setDefer(true);
                $service = new Google_Service_Drive($client);

                $request = $service->files->get($file->id, array('alt' => 'media'));

                $media = new Google\Http\MediaFileDownload(
                    $client,
                    $request,
                    $file->mimeType,
                    null,
                    true,
                    $chunkSizeBytes
                );

                $media->setFileSize($file->size);

                $status = true;
                $progress = 0;
                $previousprogress = 0;

                if ($saveToFile !== false) {
                    $saveToFileHandler = fopen($saveToFile, 'w+');
                }

                if ($preview) {
                    $status = $media->nextChunk();

                    if (!$status) {
                        return;
                    }

                    $response = $status;
                    $range = explode('-', $response->getHeaderLine('content-range'));
                    $range = explode('/', $range[1]);
                    $progress = $range[0];
                    $mediaSize = $range[1];

                    if ($progress > $previousprogress) {
                        if ($saveToFile === false) {
                            // Clean buffer and end buffering
                            while (ob_get_level()) {
                                ob_end_clean();
                            }
                            // Start buffering
                            if (!ob_get_level()) {
                                ob_start();
                            }
                            // Flush the content
                            $contentbody = $response->getBody();
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file content output
                            echo $contentbody;
                            ob_flush();
                            flush();
                        } elseif (isset($saveToFileHandler)) {
                            // Flush the content
                            $contentbody = $response->getBody();
                            fwrite($saveToFileHandler, $contentbody);
                        }

                        $previousprogress = $progress;
                        usleep(200);
                    }
                    if (((int)$mediaSize - 1) === (int)$progress) {
                        ob_end_flush();
                        $client->setDefer(false);
                        $status = false;
                        return;
                    }
                }

                while ($status) {
                    $status = $media->nextChunk();

                    if (!$status) {
                        break;
                    }

                    $response = $status;
                    $range = explode('-', $response->getHeaderLine('content-range'));
                    $range = explode('/', $range[1]);
                    $progress = $range[0];
                    $mediaSize = $range[1];

                    if ($progress > $previousprogress) {
                        if ($saveToFile === false) {
                            // Clean buffer and end buffering
                            while (ob_get_level()) {
                                ob_end_clean();
                            }
                            // Start buffering
                            if (!ob_get_level()) {
                                ob_start();
                            }
                            // Flush the content
                            $contentbody = $response->getBody();
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file content output
                            echo $contentbody;
                            ob_flush();
                            flush();
                        } elseif (isset($saveToFileHandler)) {
                            // Flush the content
                            $contentbody = $response->getBody();
                            fwrite($saveToFileHandler, $contentbody);
                        }

                        $previousprogress = $progress;
                        usleep(200);
                    }
                    if (((int)$mediaSize - 1) === (int)$progress) {
                        ob_end_flush();
                        $client->setDefer(false);
                        $status = false;
                        return;
                    }
                }

                if ($saveToFile !== false && isset($saveToFileHandler)) {
                    fclose($saveToFileHandler);
                }
            } catch (Google_Service_Exception $e) {
                if (defined('WPFD_DEBUG') && WPFD_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only on debug mode
                    error_log('mediadownloadfromgoogledriveincurrentfolder error Google_Service_Exception' . $e->getMessage());
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_var_export -- only on debug mode
                    error_log('mediadownloadfromgoogledriveincurrentfolder error Google_Service_Exception errors ' . var_export($e->getErrors(), true));
                }
            } catch (Exception $e) {
                if (defined('WPFD_DEBUG') && WPFD_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only on debug mode
                    error_log('mediadownloadfromgoogledriveincurrentfolder error ' . $e->getMessage());
                }
            }
            $client->setDefer(false);
        }
    }

    /**
     * Download file
     *
     * @param string      $id       File id
     * @param null|string $cloud_id Cloud id
     * @param boolean     $version  Version
     * @param integer     $preview  Preview or not
     * @param boolean     $getDatas Download data of file
     *
     * @return boolean|stdClass
     */
    public function download($id, $cloud_id = null, $version = false, $preview = 0, $getDatas = false)
    {
        try {
            $service = $this->getGoogleService();
            $file    = $service->files->get($id, array('fields' => $this->fullFileParams . ',parents,mimeType'));

            if (!$this->checkParents($file, $cloud_id)) {
                return false;
            }

            $downloadUrl = $file->getWebContentLink();
            $mineType    = $file->getMimeType();

            if ($downloadUrl === null  && $file->getId() !== '') {
                $ExportLinks = $file->getExportLinks();
                if ($ExportLinks !== null) {
                    if ($preview && isset($ExportLinks['application/pdf']) &&
                        strpos($mineType, 'vnd.google-apps') !== false) {
                        $downloadUrl = $ExportLinks['application/pdf'];
                    } else {
                        uksort($ExportLinks, function ($a, $b) {
                            return strlen($a) < strlen($b);
                        });
                        $ext_tmp     = explode('=', reset($ExportLinks));
                        $exportMineTypeKeys = array_keys($ExportLinks);
                        $downloadUrl = reset($ExportLinks);
                    }
                }
            }

            if ($downloadUrl) {
                $ret        = new stdClass();
                $ret->id = $file->getId();
                $ret->description = $file->getDescription();
                $ret->mimeType = $file->getMimeType();
                if (isset($exportMineTypeKeys)) {
                    $ret->exportMineType = reset($exportMineTypeKeys);
                }
                if ($getDatas) {
                    if ($version !== false) {
                        $revision = $service->revisions->get($id, $version, array('fields' => 'id,name,originalFilename'));
                        $fileRequest = $service->revisions->get($id, $version, array('alt' => 'media'));
                    } else {
                        $fileRequest = $service->files->get($id, array('alt' => 'media'));
                    }
                    if ($fileRequest->getStatusCode() === 200) {
                        $ret->datas = $fileRequest->getBody();
                    }
                } else {
                    if ($version !== false) {
                        $revision = $service->revisions->get($id, $version, array('fields' => 'id,name,originalFilename'));
                    }
                    $ret->downloadUrl = $downloadUrl;
                }

                if ($file->getName()) {
                    $ret->title = $file->getOriginalFilename() ? WpfdAddonHelper::stripExt($file->getName()) : $file->getName();
                } else {
                    $ret->title = JFile::stripExt($file->getOriginalFilename());
                    $ret->title = JFile::stripExt($ret->title);
                }

                $properties = (object) $file->getAppProperties();
                $version = isset($properties->versionNumber) ? $properties->versionNumber : (isset($properties->version) ? $properties->version : '');
                $ret->versionNumber = $version;
                $ret->version       = $version;
                $ret->mimeType = $mineType;
                $ret->file_tags = isset($properties->file_tags) ? $properties->file_tags : '';
                $ret->state = isset($properties->state) ? $properties->state : '1';
                $ext_file_name = WpfdAddonHelper::getExt($file->getOriginalFilename());
                $ret->ext = $file->getFileExtension() ? $file->getFileExtension() : $ext_file_name;
                if (isset($revision) && $revision->getOriginalFilename() !== null) {
                    $ret->size = $revision->getSize();
                } else {
                    $ret->size = $file->getSize();
                }
                if ($file->getFileExtension() === null && isset($ext_tmp)) {
                    $ret->ext = end($ext_tmp);
                }
                if ($preview && strpos($mineType, 'vnd.google-apps') !== false) {
                    $ret->ext = 'pdf';
                }

                switch ($ret->mimeType) {
                    case 'application\/vnd.google-apps.spreadsheet':
                        $ret->ext = 'xlsx';
                        break;
                    case 'application\/vnd.google-apps.document':
                        $ret->ext = 'docx';
                        break;
                    case 'application\/vnd.google-apps.presentation':
                        $ret->ext = 'pptx';
                        break;
                }

                return $ret;
            } else {
                // The file doesn't have any content stored on Drive.
                return false;
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Download google documents
     *
     * @param string $id       Google document file id
     * @param string $fileMine Google document mine type
     *
     * @return boolean|expectedClass|Google_Http_Request
     */
    public function downloadGoogleDocument($id, $fileMine)
    {
        try {
            $service = $this->getGoogleService();

            return $service->files->export($id, $fileMine);
        } catch (Google_Service_Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }
    /**
     * Delete a file
     *
     * @param string      $id       File id
     * @param null|string $cloud_id Cloud id
     *
     * @return boolean
     */
    public function delete($id, $cloud_id = null)
    {
        try {
            $service = $this->getGoogleService();
            $file = $service->files->get($id, array('fields' => 'parents'));
            if (!$this->checkParents($file, $cloud_id)) {
                return false;
            }
            $newFile = new Google_Service_Drive_DriveFile();
            $newFile->setTrashed(true);
            $service->files->update($id, $newFile);

            if ($this->cache) {
                // Delete google file cache
                WpfdCloudCache::deleteTransient($id, $this->cloudName);
            }
        } catch (Exception $e) {
            if ($e->getCode() === 404) { // File already deleted on GD
                return true;
            }

            $this->lastError = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * Copy a file.
     *
     * @param string $fileId      File id
     * @param string $newParentId New parent id
     *
     * @return Google_Service_Drive_DriveFile|boolean
     */
    public function copyFile($fileId, $newParentId)
    {
        try {
            $service = $this->getGoogleService();
            $newFile = new Google_Service_Drive_DriveFile();
            $copiedFile = $service->files->copy($fileId, $newFile, array(
                'fields' => $this->fullFileParams . ', parents',
            ));

            $oldParents = $copiedFile->getParents();
            $updatedFile = $service->files->update($copiedFile->getId(), $newFile, array(
                'removeParents' => $oldParents,
                'addParents' => $newParentId
            ));

            if ($this->cache) {
                // Clean cache for new parent folder
                WpfdCloudCache::deleteTransient($newParentId, $this->cloudName);
            }

            return $updatedFile;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * List version
     *
     * @param string       $id       Version id
     * @param null|string  $cloud_id Cloud id
     * @param null|integer $termId   Term id
     *
     * @return array|boolean
     */
    public function listVersions($id, $cloud_id = null, $termId = null)
    {
        try {
            $service = $this->getGoogleService();
            $file    = $service->files->get($id, array('fields' => 'parents,fileExtension,headRevisionId'));

            if (!$this->checkParents($file, $cloud_id)) {
                return false;
            }

            $revisions = $service->revisions->listRevisions($id, array(
                'fields' => 'revisions(id,size,modifiedTime)'
            ));

            $revs      = array();
            $ext = $file->getFileExtension() ? $file->getFileExtension() : WpfdAddonHelper::getExt($file->getOriginalFilename());
            foreach ($revisions->getRevisions() as $revision) {
                if ($revision instanceof Google_Service_Drive_Revision && $revision->getId() !== $file->getHeadRevisionId()) {
                    $rev                 = array();
                    $rev['id']           = $id;
                    $rev['ext']          = $ext;
                    $rev['meta_id']      = $revision->getId();
                    $rev['catid']        = $termId;
                    $rev['size']         = $revision->getSize();
                    $rev['created_time'] = date('Y-m-d H:i:s', strtotime($revision->getModifiedTime()));
                    $revs[]              = $rev;
                }
            }

            return $revs;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Delete version
     *
     * @param string      $id       Version id
     * @param null|string $revision The ID of the revision
     * @param null|string $cloud_id Cloud id
     *
     * @return boolean
     */
    public function deleteRevision($id, $revision = null, $cloud_id = null)
    {
        try {
            $service = $this->getGoogleService();
            $file    = $service->files->get($id, array('fields' => 'parents'));

            if (!$this->checkParents($file, $cloud_id)) {
                return false;
            }
            $service->revisions->delete($id, $revision);

            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Update version and description on copy file
     *
     * @param string $fileId   File
     * @param string $version  Version
     * @param string $cloud_id Cloud id
     *
     * @return boolean|Google_Service_Drive_Revision
     */
    public function updateRevision($fileId, $version, $cloud_id = null)
    {
        try {
            $service = $this->getGoogleService();
            $file = $service->files->get($fileId, array('fields' => 'parents'));

            if (!$this->checkParents($file, $cloud_id)) {
                return false;
            }
            // Get all revisions
            $revisions = $service->revisions->listRevisions($fileId, array('fields'=>'revisions(id,modifiedTime)'));
            $revisions = $revisions->getRevisions();
            $currentVersion = $service->revisions->get($fileId, $version, array('fields' =>'modifiedTime'));
            $currentRevisionTime = strtotime($currentVersion->getModifiedTime());
            // Compare with current one
            if (!empty($revisions)) {
                foreach ($revisions as $revision) {
                    if (!$revision instanceof Google_Service_Drive_Revision) {
                        continue;
                    }
                    $revisionTime = strtotime($revision->getModifiedTime());
                    // Delete all newer version
                    if ($revisionTime > $currentRevisionTime) {
                        $this->deleteRevision($fileId, $revision->getId());
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return $this->lastError;
        }
    }

    /**
     * Restore revision
     *
     * @param strin       $fileId     File id
     * @param null|string $revisionId Revision id to download
     *
     * @return array|boolean
     */
    public function downloadRevision($fileId, $revisionId = null)
    {
        try {
            $service = $this->getGoogleService();
            $file = $service->files->get($fileId, array('fields' => 'id,name,fileExtension,originalFilename'));
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        try {
            $version    = $service->revisions->get($fileId, $revisionId, array('alt' => 'media', 'fields' => 'id,title'));
            if ($version instanceof \GuzzleHttp\Psr7\Response && $file instanceof Google_Service_Drive_DriveFile) {
                if ($version->getStatusCode() === 200 && strtolower($version->getReasonPhrase()) === 'ok') {
                    $ext = $file->getFileExtension() ? $file->getFileExtension() : WpfdAddonHelper::getExt($file->getOriginalFilename());
                    return array(
                        'filename' => $file->getName() . '.' . $ext,
                        'filesize' => $version->getHeaderLine('Content-Length'),
                        'filetype' => $version->getHeaderLine('Content-Type'),
                        'content' => $version->getBody()
                    );
                }
            }

            return false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Get files in folder
     *
     * @param string  $folderId  Folder id
     * @param array   $datas     Datas
     * @param boolean $recursive Get all child folder
     *
     * @return boolean
     * @throws Exception Error
     */
    public function getFilesInFolder($folderId, &$datas, $recursive = true)
    {
        try {
            $service = $this->getGoogleService();
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
        $params      = $this->getDataConfigBySeverName('google');
        $base_folder = $params['googleBaseFolder'];

        $pageToken   = null;
        if ($datas === false) {
            throw new Exception('getFilesInFolder - datas error ');
        }
        if (!is_array($datas)) {
            $datas = array();
        }
        do {
            try {
                $parameters      = array();
                $parameters['q'] = 'trashed=false';
                if ($pageToken) {
                    $parameters['pageToken'] = $pageToken;
                }
                $q     = "'" . $folderId;
                $q     .= "' in parents and trashed=false and mimeType = 'application/vnd.google-apps.folder' ";
                $fs    = $service->files->listfiles(array('q' => $q, 'fields' => 'files(id,name)', 'pageSize' => 1000));
                $items = $fs->getFiles();

                foreach ($items as $f) {
                    if ($f instanceof Google_Service_Drive_DriveFile) {
                        $idFile = $f->getId();
                        if ($folderId !== $base_folder) {
                            $datas[$idFile] = array('title' => $f->getName(), 'parent_id' => $folderId);
                        } else {
                            $datas[$idFile] = array('title' => $f->getName(), 'parent_id' => 0);
                        }
                        if ($recursive) {
                            $this->getFilesInFolder($idFile, $datas);
                        }
                    }
                }
            } catch (Exception $e) {
                $datas     = false;
                $pageToken = null;
                throw new Exception('getFilesInFolder - Google_Http_REST error ' . $e->getCode());
            }
        } while ($pageToken);
    }

    /**
     * Get List folder on Google Drive
     *
     * @param string $folderId Folder id
     *
     * @return array
     * @throws Exception Error
     */
    public function getListFolder($folderId)
    {
        $datas = array();
        try {
            $this->getFilesInFolder($folderId, $datas);
        } catch (Exception $e) {
            return $datas;
        }

        return $datas;
    }

    /**
     * Move a file.
     *
     * @param string $fileId      File id
     * @param string $newParentId Parent id
     *
     * @return Google_Service_Drive_DriveFile
     */
    public function moveFile($fileId, $newParentId)
    {
        $updatedFile = null;
        try {
            $service = $this->getGoogleService();
            $file = $service->files->get($fileId, array('fields' => 'id,parents'));
            $oldParents = $file->getParents();

            $newFile = new Google_Service_Drive_DriveFile();
            $updatedFile = $service->files->update($fileId, $newFile, array(
                'removeParents' => $oldParents,
                'addParents' => $newParentId
            ));
            if ($this->cache) {
                // Clean cache
                if (is_array($oldParents)) {
                    foreach ($oldParents as $oldParentId) {
                        WpfdCloudCache::deleteTransient($oldParentId, $this->cloudName);
                    }
                }
                WpfdCloudCache::deleteTransient($newParentId, $this->cloudName);
            }

            return $updatedFile;
        } catch (Exception $e) {
            print 'An error occurred: ' . esc_html($e->getMessage());
        }
    }

    /**
     * Retrieve a list of File resources.
     *
     * @param string $q Search query
     *
     * @return   boolean|array
     * @internal param Google_Service_Drive $service Drive API service instance.
     */
    public function getAllFilesInAppFolder($q)
    {
        try {
            $service = $this->getGoogleService();
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
        $result    = array();
        $pageToken = null;
        $listfiles = array();

        $config = get_option('_wpfd_global_config');
        if (!empty($config) && !empty($config['date_format'])) {
            $dateFormat = $config['date_format'];
        } else {
            $dateFormat = 'd-m-Y h:i:s';
        }

        do {
            try {
                $parameters      = array();
                $parameters['q'] = $q;
                $parameters['spaces'] = 'drive';
                $parameters['pageSize'] = 1000;
                if ($pageToken) {
                    $parameters['pageToken'] = $pageToken;
                }
                $parameters['fields'] = 'files(id,name,parents,fileExtension,size,originalFilename,exportLinks,createdTime,modifiedTime,appProperties)';
                $files = $service->files->listFiles($parameters);

                $result    = array_merge($result, $files->getFiles());
                $pageToken = $files->getNextPageToken();
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();

                return false;
            }
        } while ($pageToken);
        foreach ($result as $k => $f) {
            if ($f instanceof Google_Service_Drive_DriveFile) {
                $file                = new stdClass();
                $file->id            = $f->getId();
                $file->ID            = $file->id;
                $file->title         = $f->getOriginalFilename() ? stripslashes(str_replace('\\', '', WpfdAddonHelper::stripExt($f->getName()))) : stripslashes(str_replace('\\', '', $f->getName()));
                $file->post_title    = stripslashes(str_replace('\\', '', $file->title));
                $file->ext           = $f->getFileExtension() ? $f->getFileExtension() : WpfdAddonHelper::getExt($f->getOriginalFilename());
                $file->size          = $f->getSize();
                $file->created_time  = get_gmt_from_date(date('Y-m-d H:i:s', strtotime($f->getCreatedTime())));
                $file->modified_time = get_gmt_from_date(date('Y-m-d H:i:s', strtotime($f->getModifiedTime())));
                $file->created       = mysql2date($dateFormat, $file->created_time);
                $file->modified      = mysql2date($dateFormat, $file->modified_time);
                if (isset($f->parents[0])) {
                    $file->parent = $f->parents[0];
                }
                if ($f->getFileExtension() === null && $f->getId() !== '') {
                    $ExportLinks = $f->getExportLinks();
                    if ($ExportLinks !== null) {
                        uksort($ExportLinks, function ($a, $b) {
                            return strlen($a) < strlen($b);
                        });
                        $ext_tmp           = explode('=', reset($ExportLinks));
                        $file->ext         = end($ext_tmp);
                        $file->urlDownload = reset($ExportLinks);
                    }
                }
                $properties          = (object) $f->getAppProperties();
                $file->file_tags     = isset($properties->file_tags) ? $properties->file_tags : '';
                $file->file_password = isset($properties->file_password) ? $properties->file_password : '';
                $file->version = isset($properties->version) ? $properties->version : '';
                $file->versionNumber = $file->version;
                $file->hits = isset($properties->hits) ? $properties->hits : '';
                $file->state = isset($properties->state) ? $properties->state : '1';

                switch ($f->getMimeType()) {
                    case 'application\/vnd.google-apps.spreadsheet':
                        $file->ext = 'xlsx';
                        break;
                    case 'application\/vnd.google-apps.document':
                        $file->ext = 'docx';
                        break;
                    case 'application\/vnd.google-apps.presentation':
                        $file->ext = 'pptx';
                        break;
                }
                $listfiles[] = $file;
            }
        }

        return $listfiles;
    }

    /**
     * Get all files in a folder
     *
     * @param string $folderId Google Drive folder Id
     *
     * @return array|boolean
     */
    public function getAllFileWithThumbnailInFolder($folderId)
    {
        try {
            $service = $this->getGoogleService();
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        $pageToken = null;
        $results = array();

        do {
            try {
                $parameters      = array();
                $q     = "'" . $folderId;
                $q     .= "' in parents and trashed=false and mimeType != 'application/vnd.google-apps.folder' ";
                $parameters['q'] = $q;
                $parameters['spaces'] = 'drive';
                $parameters['pageSize'] = 1000;
                if ($pageToken) {
                    $parameters['pageToken'] = $pageToken;
                }
                $parameters['fields'] = 'files(id,fileExtension,originalFilename,modifiedTime,hasThumbnail,thumbnailLink)';
                $files = $service->files->listFiles($parameters);

                $results = array_merge($results, $files->getFiles());
                $pageToken = $files->getNextPageToken();
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();

                return false;
            }
        } while ($pageToken);

        return $results;
    }

    /**
     * Print a file's parents.
     *
     * @param string $fileId ID of the file to print parents for.
     *
     * @return   array|boolean
     * @internal param Google_Service_Drive $service Drive API service instance.
     */
    public function getParentInfo($fileId)
    {
        try {
            $service = $this->getGoogleService();
            $file = $service->files->get($fileId, array(
                'fields' => 'id,name,parents'
            ));
            $parents = $file->getParents();
            if (!isset($parents[0])) {
                return false;
            }
            $item_tmp = $this->getFileInfos($parents[0]);

            return $item_tmp;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Search condition
     *
     * @param array $params Parameters
     *
     * @return string|boolean
     * @throws Exception Error
     */
    public function searchcondition($params)
    {
        $q = '';
        $folders = $this->getListFolder($params['googleBaseFolder']);
        if (empty($folders)) {
            return false;
        }
        foreach ($folders as $id => $folder) {
            $q .= " '" . $id . "' in parents or";
        }
        $q = rtrim($q, 'or');

        return $q;
    }

    /**
     * Save Version number
     *
     * @param string $fileId  File id
     * @param string $version Version string
     *
     * @return boolean
     */
    public function saveVersionNumber($fileId, $version)
    {
        try {
            $service = $this->getGoogleService();
            $file    = new Google_Service_Drive_DriveFile();
            $properties = new stdClass();
            $properties->versionNumber = $version;
            $file->setAppProperties($properties);
            $service->files->update($fileId, $file);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }
    /**
     * Update description on copy file
     *
     * @param string $fileId      File
     * @param string $description Description
     *
     * @return boolean
     */
    public function updateDescription($fileId, $description)
    {
        try {
            $service = $this->getGoogleService();
            $file    = new Google_Service_Drive_DriveFile();
            $file->setDescription($description);
            $service->files->update($fileId, $file);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Check parents
     *
     * @param Google_Service_Drive_DriveFile $file     Google Drive File
     * @param string                         $cloud_id Cloud id
     *
     * @return boolean
     * @since  5.1.3
     */
    public function checkParents($file, $cloud_id)
    {
        if ($cloud_id !== null) {
            $parents = $file->getParents();
            // fix: $parents is null in some cases
            if ($parents) {
                foreach ($parents as $parent) {
                    if ($parent === $cloud_id) {
                        return true;
                    }
                }

                return false;
            }
        }

        return true;
    }

    /**
     * Get Google Service
     *
     * @return Google_Service_Drive|boolean
     */
    public function getGoogleService()
    {
        try {
            $client = $this->getGoogleClient();

            return new Google_Service_Drive($client);
        } catch (Exception $ex) {
            $this->lastError = $ex->getMessage();
            throw new Google_Service_Exception($this->lastError);
        }
    }

    /**
     * Get Google Client
     *
     * @return boolean|Google\Client
     */
    public function getGoogleClient()
    {
        try {
            $this->checkCredentials();
            $client = new Google\Client();
            $client->setClientId($this->params->google_client_id);
            $client->setClientSecret($this->params->google_client_secret);
            $client->setAccessToken($this->params->google_credentials);

            return $client;
        } catch (Exception $ex) {
            $this->lastError = $ex->getMessage();
            throw new Google_Service_Exception('Credentials not correct or you are not connected!');
        }
    }

    /**
     * Check google credentials
     *
     * @throws Google_Service_Exception Throw when error
     *
     * @return void
     */
    private function checkCredentials()
    {
        if (is_null($this->params->google_client_id) ||
            is_null($this->params->google_client_secret) ||
            is_null($this->params->google_credentials) ||
            empty($this->params->google_client_id) ||
            empty($this->params->google_client_secret) ||
            empty($this->params->google_credentials)) {
            throw new Google_Service_Exception('Credentials not correct or you are not connected!');
        }
    }
    /**
     * Get start page token for watch changes
     *
     * @return boolean
     */
    public function getStartPageToken()
    {
        try {
            $service = $this->getGoogleService();
            $response = $service->changes->getStartPageToken(array(
                'fields' => '*'
            ));

            if ($response->getStartPageToken()) {
                return $response->getStartPageToken();
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }

        return false;
    }

    /**
     * Watch changes
     *
     * @param string $pageToken Page token
     * @param string $id        Universally Unique IDentifier (UUID). BINARY(16)
     *
     * @return boolean|array
     */
    public function watchChanges($pageToken, $id)
    {
        $ajaxUrl = admin_url('admin-ajax.php');

        $callbackUrl = $ajaxUrl . '?action=googlePushListener';
        $channel = new Google_Service_Drive_Channel();
        $channel->setAddress($callbackUrl);
        $channel->setKind('api#channel');
        $channel->setId($id);
        $channel->setType('web_hook'); // Important
        $channel->setToken($pageToken);
        $channel->setExpiration((time() + 604800) * 1000); // 1 week in milliseconds

        try {
            $service = $this->getGoogleService();
            $response = $service->changes->watch($pageToken, $channel, array(
                'fields'   => '*',
                'restrictToMyDrive' => true, // Listen only on My Drive hierarchy to reduce data
                'pageSize' => 500
            ));

            if ($response->getResourceId()) {
                return array(
                    'kind'       => $response->getKind(),
                    'id'         => $response->getId(),
                    'resourceId' => $response->getResourceId(),
                    'token'      => $response->getToken(),
                    'address'    => $response->getAddress(),
                    'expiration' => $response->getExpiration() // Milliseconds
                );
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageData = json_decode($message, true);
            $message = isset($messageData['error']['message']) ? $messageData['error']['message'] : '';
            $errorCode = $e->getCode();
            $this->lastError = $message;

            return array(
                'error' => $errorCode,
                'message' => $message
            );
        }

        return false;
    }

    /**
     * Get list changes
     *
     * @param string $pageToken Page token
     *
     * @return boolean|Google_Service_Drive_ChangeList
     */
    public function listChanges($pageToken)
    {
        try {
            $service = $this->getGoogleService();
            $response = $service->changes->listChanges($pageToken, array(
                'fields'   => '*', // todo: change fields to reduce response size
                'pageSize' => 1000
            ));

            return $response;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Stop watch a channel
     *
     * @param string $id         Channel id
     * @param string $resourceId Resource Id
     *
     * @return boolean
     */
    public function stopWatch($id, $resourceId)
    {
        try {
            $service = $this->getGoogleService();
            $channel = new Google_Service_Drive_Channel();
            $channel->setKind('api#channel');
            $channel->setId($id);
            $channel->setResourceId($resourceId);

            $service->channels->stop($channel);
            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Delete all local folder and Google Drive folder for testing purpose
     *
     * @return void
     *
     * @throws Exception Fire message if errors
     */
    public function deleteAllFolder()
    {
        $params      = $this->getDataConfigBySeverName('google');
        $base_folder = $params['googleBaseFolder'];

        // Get all folder in base folder
        $folders = array();
        try {
            $this->getFilesInFolder($base_folder, $folders, false);

            foreach ($folders as $folderId => $folder) {
                $termId = WpfdAddonHelper::getTermIdGoogleDriveByGoogleId($folderId);
                wp_delete_term($termId, 'wpfd-category');
                $this->delete($folderId);
            }
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
        }
    }

    /**
     * Preview for img file
     *
     * @param string $fileId       File id
     * @param string $previewsPath Previews folder path
     *
     * @return boolean|string
     */
    public function getGooglePreviewImg($fileId, $previewsPath)
    {
        try {
            $client  = $this->getGoogleClient();
            $service = $this->getGoogleService();
            $file    = $service->files->get($fileId, array('fields' => $this->fullFileParams.',mimeType'));
            if ($file instanceof Google_Service_Drive_DriveFile) {
                $type = $file->getMimeType();
                // Save the image content to the specified file path
                if (strpos($type, 'image/') === 0) {
                    $fileRequest = $service->files->get($fileId, array('alt' => 'media'));
                    $pngContent = $fileRequest->getBody()->getContents();

                    if ($pngContent !== null) {
                        $fileName = md5($fileId);
                        $filePath = $previewsPath . 'googleDrive_' . strval($fileName) . '.png';
                        file_put_contents($filePath, $pngContent);

                        $now = new DateTime();
                        $fileUpdate = $now->format('Y-m-d\TH:i:s.v\Z');
                        $filePath = str_replace(WP_CONTENT_DIR, '', $filePath);
                        update_option('_wpfdAddon_preview_info_' . $fileName, array('updated' => $fileUpdate, 'path' => $filePath));

                        return $filePath;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
        }

        return false;
    }
}
