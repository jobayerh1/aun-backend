<?php
// no direct access
defined('ABSPATH') || die('No direct script access allowed!');
require_once WPFDA_PLUGIN_DIR_PATH  . 'lib/prod_vendor/autoload.php';
use Joomunited\WPFramework\v1_0_6\Utilities;
use Joomunited\WPFramework\v1_0_6\Model as WpfdModel;
use Joomunited\WPFramework\v1_0_6\Application;
use GuzzleHttp\Client as GuzzleHttpClient;
use Krizalys\Onedrive\Client;
use Krizalys\Onedrive\Exception\ConflictException;
use Microsoft\Graph\Graph;
use Krizalys\Onedrive\Onedrive;
use Krizalys\Onedrive\Proxy\FileProxy;
use Microsoft\Graph\Http\GraphResponse;
use Microsoft\Graph\Model\DriveItem;
use Microsoft\Graph\Model\Subscription;
use Microsoft\Graph\Model;
use Microsoft\Graph\Model\UploadSession;
use Krizalys\Onedrive\Constant\ConflictBehavior;
use Krizalys\Onedrive\Constant\AccessTokenStatus;

/**
 * Class WpfdAddonOneDriveBusiness
 */
class WpfdAddonOneDriveBusiness
{
    /**
     * Onedrive connection config
     *
     * @var array $config
     */
    public $config;
    /**
     * OneDrive Client
     *
     * @var OneDrive_Client
     */
    private $client = null;

    /**
     * File fields
     *
     * @var string
     */
    protected $apifilefields = 'thumbnails,children(top=1000;expand=thumbnails(select=medium,large,mediumSquare,c1500x1500))';

    /**
     * List files fields
     *
     * @var string
     */
    protected $apilistfilesfields = 'thumbnails(select=medium,large,mediumSquare,c1500x1500)';

    /**
     * BreadCrumb
     *
     * @var string
     */
    public $breadcrumb = '';

    /**
     * AccessToken
     *
     * @var string
     */
    private $accessToken;

    /**
     * Refresh token
     *
     * @var string
     */
    private $refreshToken;

    /**
     * Last error
     *
     * @var $lastError
     */
    protected $lastError;

    /**
     * Cloud type
     *
     * @var string
     */
    protected $type = 'onedrive_business';

    /**
     * Debug
     *
     * @var boolean
     */
    private $debug = false;

    /**
     * Access token
     *
     * @var string
     */
    private $access_token = '';

    /**
     * Max number of file/folder list
     *
     * @var integer
     */
    private $maxItemNumbers = 3000;

    /**
     * WpfdAddonOneDriveBusiness constructor.
     */
    public function __construct()
    {
        add_filter('pre_update_option__wpfdAddon_onedrive_business_category_params', function ($value, $oldValue, $option) {
            if (is_array($value)) {
                foreach ($value as $k => &$v) {
                    if (strpos($v['oneDriveId'], '!') !== -1) {
                        $v['oneDriveId'] = str_replace('!', '-', $v['oneDriveId']);
                    }
                }
            }
            return $value;
        }, 10, 3);
        $this->getConfig();
        $this->loadAccessToken();
        if ($this->client === null && !empty($this->access_token)) {
            $this->getClient();
        }

        /**
         * Filter to change the length of onedrive business item list
         *
         * @param integer
         */
        $this->maxItemNumbers = apply_filters('wpfda_onedrive_business_max_items', 3000);
    }

    /**
     * Load access token
     *
     * @return void
     */
    protected function loadAccessToken()
    {
        $this->access_token = '';
        $onedrive_state         = isset($this->config['state']) ? $this->config['state'] : array();
        if (is_array($onedrive_state)) {
            $this->access_token = isset($onedrive_state['token']['data']['access_token']) ? $onedrive_state['token']['data']['access_token'] : '';
        } else {
            $this->access_token = isset($onedrive_state->token->data->access_token) ? $onedrive_state->token->data->access_token : '';
        }
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
     * Save config
     *
     * @param array $config Config
     *
     * @return void
     */
    public function saveConfig($config)
    {
        update_option('_wpfdAddon_' . $this->type . '_config', $config);
    }

    /**
     * Get config
     *
     * @return mixed|void
     */
    public function getConfig()
    {
        $config = array(
            'onedriveBusinessKey'         => '',
            'onedriveBusinessSecret'      => '',
            'onedriveBusinessSyncTime'    => '30',
            'onedriveBusinessSyncMethod'  => 'sync_page_curl',
            'onedriveBusinessConnectedBy' => get_current_user_id(),
            'state'                       => array()
        );
        $this->config = get_option('_wpfdAddon_' . $this->type . '_config', $config);

        return $this->config;
    }

    /**
     * Renew the access token from OAuth. This token is valid for one hour.
     *
     * @param object $client Client
     * @param array  $config Setings
     *
     * @return Client|WP_Error
     */
    public function renewAccessToken($client, $config)
    {
        $client->renewAccessToken($config['onedriveBusinessSecret']);
        $config['state'] = $client->getState();
        $this->saveConfig($config);
        $graph = new Graph();
        $graph->setAccessToken($this->access_token);
        try {
            $client = new Client(
                $config['onedriveBusinessKey'],
                $graph,
                new GuzzleHttpClient(),
                Onedrive::buildServiceDefinition(),
                array(
                    'state' => $client->getState()
                )
            );

            return $client;
        } catch (Exception $ex) {
            return new WP_Error(
                'broke',
                esc_html__('Error communicating with OneDrive API: ', 'wpfdAddon') . $ex->getMessage()
            );
        }
    }

    /**
     * Read OneDrive app key and secret
     *
     * @return Krizalys\Onedrive\Client|OneDrive_Client|boolean
     */
    public function getClient()
    {
        if (empty($this->config['onedriveBusinessKey']) && empty($this->config['onedriveBusinessSecret'])) {
            return false;
        }

        try {
            if (!empty($this->access_token)) {
                $graph = new Graph();
                $graph->setAccessToken($this->access_token);
                $client = new Client(
                    $this->config['onedriveBusinessKey'],
                    $graph,
                    new GuzzleHttpClient(),
                    Onedrive::buildServiceDefinition(),
                    array(
                        'state' => json_decode(json_encode($this->config['state']))
                    )
                );

                if ($client->getAccessTokenStatus() === -2) {
                    $client = $this->renewAccessToken($client, $this->config);
                }
            } else {
                $client = new Client(
                    $this->config['onedriveBusinessKey'],
                    new Graph(),
                    new GuzzleHttpClient(),
                    Onedrive::buildServiceDefinition()
                );
            }

            $this->client = $client;

            return $this->client;
        } catch (Exception $ex) {
            $this->writeLog($ex->getMessage());

            return false;
        }
    }

    /**
     * Create token after connected
     *
     * @param string $code Code to access to onedrive app
     *
     * @return boolean|WP_Error
     */
    public function createToken($code)
    {
        try {
            $onedriveconfig = $this->getConfig();

            $client = new Client(
                $onedriveconfig['onedriveBusinessKey'],
                new Graph(),
                new GuzzleHttpClient(),
                Onedrive::buildServiceDefinition(),
                array(
                    'state' => isset($onedriveconfig['state']) && !empty($onedriveconfig['state']) ? json_decode(json_encode($onedriveconfig['state'])) : array()
                )
            );

            $blogname = trim(str_replace(array(':', '~', '"', '%', '&', '*', '<', '>', '?', '/', '\\', '{', '|', '}'), '', get_bloginfo('name')));
            // Fix onedrive bug, last folder name can not be a dot
            if (substr($blogname, -1) === '.') {
                $blogname = substr($blogname, 0, strlen($blogname) - 1);
            }

            if ($blogname === '') {
                $siteUrl = site_url() ? site_url() : (home_url() ? home_url() : '');
                $blogname = parse_url($siteUrl, PHP_URL_HOST);
                if (!$blogname) {
                    $blogname = '';
                } else {
                    $blogname = trim($blogname);
                }
            }

            // Obtain the token using the code received by the OneDrive API.
            $client->obtainAccessToken($onedriveconfig['onedriveBusinessSecret'], $code);
            $graph = new Graph();
            $graph->setAccessToken($client->getState()->token->data->access_token);

            if (empty($onedriveconfig['onedriveBaseFolder'])) {
                $folderName = 'WP File Download - ' . $blogname;
                $folderName = preg_replace('@["*:<>?/\\|]@', '', $folderName);
                $folderName = rtrim($folderName);
                try {
                    $root = $client->getRoot()->createFolder($folderName);
                    $onedriveconfig['onedriveBaseFolder'] = array(
                        'id' => $root->id,
                        'name' => $root->name
                    );
                } catch (ConflictException $e) {
                    $root = $client->getDriveItemByPath('/' . $folderName);
                    $onedriveconfig['onedriveBaseFolder'] = array(
                        'id' => $root->id,
                        'name' => $root->name
                    );
                }
            } else {
                try {
                    $root = $graph
                        ->createRequest('GET', '/me/drive/items/' . $onedriveconfig['onedriveBaseFolder']['id'])
                        ->setReturnType(Model\DriveItem::class) // phpcs:ignore PHPCompatibility.Constants.NewMagicClassConstant.Found -- Use to sets the return type of the response object
                        ->execute();
                    $onedriveconfig['onedriveBaseFolder'] = array(
                        'id' => $root->getId(),
                        'name' => $root->getName()
                    );
                } catch (Exception $ex) {
                    $folderName = 'WP File Download - ' . $blogname;
                    $folderName = preg_replace('@["*:<>?/\\|]@', '', $folderName);
                    $folderName = rtrim($folderName);
                    $results = $graph->createRequest('GET', '/me/drive/search(q=\'' . $folderName . '\')')
                        ->setReturnType(Model\DriveItem::class)
                        ->execute();
                    if (isset($results[0])) {
                        $root = new stdClass;
                        $root->id = $results[0]->getId();
                        $root->name = $results[0]->getName();
                    } else {
                        $root = $client->getRoot()->createFolder($folderName);
                    }

                    $onedriveconfig['onedriveBaseFolder'] = array(
                        'id' => $root->id,
                        'name' => $root->name
                    );
                }
            }

            $token = $client->getState()->token->data->access_token;
            $this->accessToken = $token;
            $onedriveconfig['connected'] = 1;
            $onedriveconfig['state'] = $client->getState();
            // update _wpmfAddon_onedrive_business_config option and redirect page
            $this->saveConfig($onedriveconfig);
            $this->redirect(admin_url('admin.php?page=wpfdAddon-onedrive&task=onedrivebusiness.authenticated'));
        } catch (Exception $ex) {
            ?>
            <div class="error" id="wpfd_error">
                <p>
                    <?php
                    if ((int)$ex->getCode() === 409) {
                        echo esc_html__('The root folder name already exists on cloud. Please rename or delete that folder before connect', 'wpfdAddon');
                    } else {
                        echo esc_html__('Error communicating with OneDrive API: ', 'wpfdAddon');
                        echo esc_html($ex->getMessage());
                    }
                    ?>
                </p>
            </div>
            <?php
            return new WP_Error(
                'broke',
                esc_html__('Error communicating with OneDrive API: ', 'wpfdAddon') . $ex->getMessage()
            );
        }

        return true;
    }

    /**
     * Start OneDrive API Client with token
     *
     * @return OneDrive_Client|WP_Error
     */
    public function startClient()
    {
        if ($this->accessToken === false) {
            die();
        }

        return $this->client;
    }

    /**
     * Get authorisation url
     *
     * @return string|WP_Error
     */
    public function getAuthorisationUrl()
    {
        try {
            $client = new Client(
                $this->config['onedriveBusinessKey'],
                new Graph(),
                new GuzzleHttpClient(),
                Onedrive::buildServiceDefinition()
            );
            $authorizeUrl = $client->getLogInUrl(
                array(
                    'user.read',
                    'files.read',
                    'files.read.all',
                    'files.readwrite',
                    'files.readwrite.all',
                    'offline_access',
                    'openid',
                    'profile'
                ),
                get_admin_url() . 'admin.php',
                'wpfd-onedrive-business'
            );
            $config = $this->getConfig();
            $config['state'] = $client->getState();
            $this->saveConfig($config);
            return $authorizeUrl;
        } catch (Exception $ex) {
            return new WP_Error('broke', __('Could not start authorization: ', 'wpfdAddon') . $ex->getMessage());
        }
    }

    /**
     * Authenticate
     *
     * @return boolean|WP_Error
     */
    public function authenticate()
    {
        $code   = Utilities::getInput('code', 'GET', 'none');
        return $this->createToken($code);
    }

    /**
     * Logout
     *
     * @return boolean
     */
    public function logout()
    {
        $config = $this->getConfig();
        $config['connected'] = '0';
        unset($config['state']);
        $this->saveConfig($config);
//        $this->redirect('https://login.microsoftonline.com/common/oauth2/v2.0/logout?post_logout_redirect_uri=' . urlencode(get_admin_url() . 'admin.php?page=wpfd-config'));
        return true;
    }
    /**
     * Set redirect URL
     *
     * @param string $location URL
     *
     * @return void
     */
    public function redirect($location)
    {
        if (!headers_sent()) {
            header('Location: ' . $location, true, 303);
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput -- Content already escaped in the method
            echo "<script>document.location.href='" . str_replace("'", '&apos;', $location) . "';</script>\n";
        }
    }
    /**
     * Check Auth
     *
     * @return boolean
     */
    public function checkAuth()
    {
        try {
            $client = $this->getClient();
            if (!$client) {
                return false;
            }
            if ($client->getAccessTokenStatus() === AccessTokenStatus::VALID) {
                return true;
            }
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * Check if file id and folder id are correct
     *
     * @param string      $id       File|Category name
     * @param null|string $cloud_id Cloud id
     *
     * @return boolean
     */
    public function checkFileFolderValid($id, $cloud_id)
    {
        $id       = WpfdAddonHelper::replaceIdOneDrive($id, false);
        $cloud_id = WpfdAddonHelper::replaceIdOneDrive($cloud_id, false);

        try {
            $client = $this->getClient();
            $file   = $client->getDriveItemById($id);

            /* @var $file \Krizalys\Onedrive\File */
            if ($file) {
                $found  = false;
                if ((string) $file->parentReference->id === (string) $cloud_id) {
                    $found = true;
                }
                if (!$found) {
                    return false;
                } else {
                    return true;
                }
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return false;
    }

    /**
     * Create OneDrive Folder
     *
     * @param string $title    New folder name
     * @param string $parentId Parent id
     *
     * @return \Krizalys\Onedrive\Proxy\DriveItemProxy|WP_Error
     */
    public function createFolder($title, $parentId = null)
    {
        $parentId = WpfdAddonHelper::replaceIdOneDrive($parentId, false);
        try {
            $client = $this->getClient();
            $parentFolder = $client->getDriveItemById($parentId);
            return $parentFolder->createFolder($title, array(
                'conflictBehavior' => ConflictBehavior::RENAME
            ));
        } catch (Exception $ex) {
            return new WP_Error('broke', __('Can not create Onedrive Business folder: ', 'wpfdAddon') . $ex->getMessage());
        }
    }

    /**
     * List files onedrive
     *
     * @param string  $folder_id   Category id
     * @param string  $ordering    Ordering
     * @param string  $direction   Ordering direction
     * @param boolean $listIdFlies List id files?
     *
     * @return array|boolean
     */
    public function listFiles($folder_id, $ordering = 'ordering', $direction = 'asc', $listIdFlies = false)
    {
        $folder_idck = WpfdAddonHelper::replaceIdOneDrive($folder_id, false);
        $client = $this->getClient();
        try {
            $folder = $client->getDriveItemById($folder_idck);
            $items = $folder->getChildren(
                array(
                    'top' => $this->maxItemNumbers
                )
            );

            $perlink    = get_option('permalink_structure');
            $rewrite_rules = get_option('rewrite_rules');
            $config     = get_option('_wpfd_global_config');
            if (!empty($config) && !empty($config['date_format'])) {
                $dateFormat = $config['date_format'];
            } else {
                $dateFormat = 'd-m-Y';
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

            $files = array();

            foreach ($items as $f) {
                if ($f->folder) {
                    continue;
                }
                $idItem = WpfdAddonHelper::replaceIdOneDrive($f->id);
                $parentId = $f->parentReference->id;

                if (is_array($listIdFlies) && !in_array($idItem, $listIdFlies)) {
                    continue;
                }
                if ($folder_idck === $parentId) {
                    $file = new stdClass;
                    $file->id = $idItem;
                    $file->ID = $idItem;
                    $file->title       = WpfdAddonHelper::stripExt($f->name);
                    $file->post_title  = $file->title;
                    $file->post_name  = $file->title;
                    $file->description = $f->description;
                    $file->ext         = strtolower(WpfdAddonHelper::getExt($f->name));
                    $file->size        = $f->size;
                    $file->created_time     = get_date_from_gmt($f->createdDateTime->format('Y-m-d H:i:s'));
                    $file->modified_time    = get_date_from_gmt($f->lastModifiedDateTime->format('Y-m-d H:i:s'));
                    $file->created          = mysql2date($dateFormat, $file->created_time);
                    $file->modified         = mysql2date($dateFormat, $file->modified_time);
                    $file->versionNumber    = '';
                    $file->version          = '';
                    $file->hits             = 0;
                    $file->ordering         = 0;
                    $file->file_custom_icon = '';
                    $file->catid            = WpfdAddonHelper::getTermIdOneDriveBusinessByOneDriveId($folder_id);
                    $category               = get_term($file->catid, 'wpfd-category');
                    if (!empty($rewrite_rules)) {
                        $downloadFileTitle = str_replace('\\', '-', str_replace('/', '-', $file->title));
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
                        $linkdownload .= 'file.download&wpfd_category_id=' . $file->catid;
                        $linkdownload .= '&wpfd_file_id=' . $file->id;
                        $file->linkdownload = $linkdownload;
                    }

                    $fileInfos = WpfdAddonHelper::getOneDriveBusinessFileInfos();
                    if (!empty($fileInfos) && isset($fileInfos[$file->catid]) &&
                        isset($fileInfos[$file->catid][$idItem])) {
                        $file->description = isset($fileInfos[$file->catid][$idItem]['description']) ?
                            $fileInfos[$file->catid][$idItem]['description'] : $file->description;
                        $file->version     = isset($fileInfos[$file->catid][$idItem]['version']) ?
                            $fileInfos[$file->catid][$idItem]['version'] : '';
                        $file->versionNumber = $file->version;
                        $file->hits        = isset($fileInfos[$file->catid][$idItem]['hits']) ?
                            $fileInfos[$file->catid][$idItem]['hits'] : 0;
                        $file->file_tags   = isset($fileInfos[$file->catid][$idItem]['file_tags']) ?
                            $fileInfos[$file->catid][$idItem]['file_tags'] : '';
                        $file->state       = isset($fileInfos[$file->catid][$idItem]['state']) ?
                            $fileInfos[$file->catid][$idItem]['state'] : '1';
                        $file->file_custom_icon   = isset($fileInfos[$file->catid][$idItem]['file_custom_icon']) ?
                            $fileInfos[$file->catid][$idItem]['file_custom_icon'] : '';
                        $file->file_password = isset($fileInfos[$file->catid][$idItem]['file_password']) ?
                            $fileInfos[$file->catid][$idItem]['file_password'] : '';
                    }
                    $files[] = apply_filters('wpfda_file_info', $file, $this->type);
                    unset($file);
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
     * Get All files in a folder
     *
     * @param string $folderId Onedrive Folder id
     *
     * @return array|boolean
     */
    /**
     * Get All files in a folder
     *
     * @param string $folderId Onedrive Folder id
     *
     * @return array|boolean
     */
    public function getAllFileInFolder($folderId)
    {
        try {
            $client = $this->getClient();
            $folder = $client->getDriveItemById($folderId);
            /* @var DriveItemProxy[] $items */
            $items = $folder->getChildren(
                array(
                    'top' => $this->maxItemNumbers
                )
            );

            $files = array();
            foreach ($items as $f) {
                if ($f->file) {
                    $files[] = array(
                        'name' => $f->name,
                        'id' => $f->id,
                        'updated' => $f->lastModifiedDateTime->format('Y-m-d H:i:s')
                    );
                }
            }

            return $files;
        } catch (Exception $e) {
            return false;
        }
    }
    /**
     * Download Onedrive Thumbnail
     *
     * @param string $idFile   Onedrive Cloud Id
     * @param string $filePath Path to save the thumbnail file
     *
     * @return boolean|false|string False on failed. Url if $filePath not given else save to $filePath
     *
     * @throws GraphException Throw on create request failed.
     */
    public function downloadThumbnail($idFile, $filePath = '')
    {
        if (!empty($this->access_token)) {
            $graph = new Graph();
            $graph->setAccessToken($this->access_token);
            $folderId    = WpfdAddonHelper::replaceIdOneDrive($idFile, false);
            $url = '/me/drive/items/' . $folderId . '/thumbnails';
            if ($idFile === '') {
                return false;
            }
            $response = $graph->createRequest('GET', $url)->execute();

            $status = $response->getStatus();

            if ($status !== 200) {
                throw new \Exception('Unexpected status code produced by \'GET ' . $url . '\': ' . $status);
            }

            // phpcs:ignore PHPCompatibility.Constants.NewMagicClassConstant.Found -- it's OK, plugin now require php 5.4
            $driveItems = $response->getResponseAsObject(Model\ThumbnailSet::class);

            if (is_array($driveItems) && count($driveItems) >= 1) {
                $url = $driveItems[0]->getLarge()->getUrl();
                if (!$url) {
                    return false;
                }
                if (!$filePath) {
                    return $driveItems[0]->getLarge()->getUrl();
                } else {
                    $fileContent = file_get_contents($driveItems[0]->getLarge()->getUrl());
                    if ($fileContent) {
                        $fs = fopen($filePath, 'wb');
                        $result = fwrite($fs, $fileContent);
                        fclose($fs);
                        if ($result) {
                            return $filePath;
                        }
                    }
                }
            }
        }

        return false;
    }
    /**
     * Get files in folder
     *
     * @param string  $folderId Folder id
     * @param array   $datas    Data return
     * @param boolean $recusive Get all folders
     *
     * @return void
     * @throws Exception Error
     *
     * todo: This only return 200 results
     */
    public function getFilesInFolder($folderId, &$datas, $recusive = true)
    {
        $folderId    = WpfdAddonHelper::replaceIdOneDrive($folderId, false);
        $client      = $this->client;
        try {
            $folder      = $client->getDriveItemById($folderId);
        } catch (Exception $ex) {
            $this->lastError = 'Get drive item false';
        }

        $params      = $this->getConfig();
        $base_folder = $params['onedriveBaseFolder'];
        $pageToken   = null;
        if ($datas === false) {
            throw new Exception('getFilesInFolder - datas error ');
        }

        if (!is_array($datas)) {
            $datas = array();
        }
        do {
            try {
                $childs = $folder->getChildren(
                    array(
                        'top' => $this->maxItemNumbers
                    )
                );
                foreach ($childs as $item) {
                    /* @var \Krizalys\Onedrive\Proxy\DriveItemProxy $item */

                    if ($item->folder) {
                        $parentReference = $item->parentReference;
                        $idItem          = WpfdAddonHelper::replaceIdOneDrive($item->id);
                        $nameItem        = $item->name;
                        if ($idItem !== $base_folder['id']) {
                            $base_folder_id = WpfdAddonHelper::replaceIdOneDrive($base_folder['id'], false);
                            if ((string) $parentReference->id === (string) $base_folder_id) {
                                $datas[$idItem] = array('title' => $nameItem, 'parent_id' => 0);
                            } else {
                                $datas[$idItem] = array(
                                    'title'     => $nameItem,
                                    'parent_id' => WpfdAddonHelper::replaceIdOneDrive($parentReference->id)
                                );
                            }
                            if ($recusive) {
                                $this->getFilesInFolder($idItem, $datas);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $datas     = false;
                $pageToken = null;
                throw new Exception('getFilesInFolder - Onedrive Business error ' . $e->getMessage());
            }
        } while ($pageToken);
    }

    /**
     * Get onedrive business file info
     *
     * @param string  $idFile     File id
     * @param integer $idCategory Category id
     * @param string  $token      Token key
     *
     * @return array|booleab
     */
    public function getOneDriveBusinessFileInfos($idFile, $idCategory, $token = '')
    {
        if (!class_exists('WpfdBase')) {
            include_once WPFD_PLUGIN_DIR_PATH . '/app/admin/classes/WpfdBase.php';
        }

        $idFile         = WpfdAddonHelper::replaceIdOneDrive($idFile, false);
        $client = $this->client;
        $perlink        = get_option('permalink_structure');
        $rewrite_rules = get_option('rewrite_rules');
        $config         = get_option('_wpfd_global_config');
        if (!empty($config) && !empty($config['date_format'])) {
            $dateFormat = $config['date_format'];
        } else {
            $dateFormat = 'd-m-Y h:i:s';
        }
        //$viewer_type = WpfdBase::loadValue($config, 'use_google_viewer', 'lightbox');
        if (empty($config) || empty($config['uri'])) {
            $seo_uri = 'download';
        } else {
            $seo_uri = rawurlencode($config['uri']);
        }
        try {
            $file = $client->getDriveItemById($idFile);
            $data                  = array();
            $data['ID']            = WpfdAddonHelper::replaceIdOneDrive($file->id);
            $data['id']            = $data['ID'];
            $data['catid']         = $idCategory;
            $data['title']         = WpfdAddonHelper::stripExt($file->name);
            $data['post_title']    = $data['title'];
            $data['post_name']     = $data['title'];
            $data['file']          = '';
            $data['ext']           = strtolower(WpfdAddonHelper::getExt($file->name));
            $data['created_time']  = get_date_from_gmt($file->createdDateTime->format('Y-m-d H:i:s'));
            $data['modified_time'] = get_date_from_gmt($file->lastModifiedDateTime->format('Y-m-d H:i:s'));
            $data['created']       = mysql2date($dateFormat, $data['created_time']);
            $data['modified']      = mysql2date($dateFormat, $data['modified_time']);
            $data['file_tags']     = '';
            $data['size']          = $file->size;
            $data['ordering']      = 1;

            $viewer_type      = WpfdBase::loadValue($config, 'use_google_viewer', 'lightbox');
            $extension_list   = 'png,jpg,pdf,ppt,pptx,doc,docx,xls,xlsx,dxf,ps,eps,xps,psd,tif,tiff,bmp,svg,pages,ai,dxf,ttf,txt,mp3,mp4';
            $extension_viewer = explode(',', WpfdBase::loadValue($config, 'extension_viewer', $extension_list));
            $extension_viewer = array_map('trim', $extension_viewer);
            if ($viewer_type !== 'no' &&
                in_array($data['ext'], $extension_viewer)) {
                $data['viewer_type'] = $viewer_type;
                $data['viewerlink']  = WpfdHelperFile::isMediaFile($data['ext']) ?
                    WpfdHelperFile::getMediaViewerUrl($data['ID'], $idCategory, $data['ext'], $token) :
                    WpfdHelperFile::getViewerUrl($data['ID'], $idCategory, $token);
            }

            $category = get_term($data['catid'], 'wpfd-category');

            if (empty($config) || !isset($config['rmdownloadext'])) {
                $rmdownloadext = false;
            } else {
                $rmdownloadext = ((int) $config['rmdownloadext'] === 1) ? true : false;
            }

            if (!empty($rewrite_rules)) {
                if (strpos($perlink, 'index.php')) {
                    $linkdownload         = get_site_url() . '/index.php/' . $seo_uri . '/' . $data['catid'] . '/';
                    $linkdownload         .= $category->slug . '/' . $data['id'] . '/' . $data['title'];
                } else {
                    $linkdownload         = get_site_url() . '/' . $seo_uri . '/' . $data['catid'] . '/';
                    $linkdownload         .= $category->slug . '/' . $data['id'] . '/' . $data['title'];
                }
                $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $data['ext'] : '');
                $data['linkdownload'] = $linkdownload;
            } else {
                $linkdownload         = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=file.download';
                $linkdownload         .= '&wpfd_category_id=' . $data['catid'] . '&wpfd_file_id=' . $data['id'];
                $data['linkdownload'] = $linkdownload;
            }
            $config = WpfdAddonHelper::getOneDriveBusinessFileInfos();
            if (!empty($config) && isset($config[$idCategory]) && isset($config[$idCategory][$data['id']])) {
                $data['description']             = isset($config[$idCategory][$data['id']]['description']) ?
                    $config[$idCategory][$data['id']]['description'] : '';
                $data['version']                 = isset($config[$idCategory][$data['id']]['version']) ?
                    $config[$idCategory][$data['id']]['version'] : '';
                $data['versionNumber']           = $data['version'];
                $data['latestVersionNumber']                 = isset($config[$idCategory][$data['id']]['latestVersionNumber']) ?
                    $config[$idCategory][$data['id']]['latestVersionNumber'] : false;
                $data['hits']                    = isset($config[$idCategory][$data['id']]['hits']) ?
                    $config[$idCategory][$data['id']]['hits'] : 0;
                $data['state']                    = isset($config[$idCategory][$data['id']]['state']) ?
                    (string) $config[$idCategory][$data['id']]['state'] : '1';
                $data['social']                  = isset($config[$idCategory][$data['id']]['social']) ?
                    $config[$idCategory][$data['id']]['social'] : 0;
                $data['file_tags']               = isset($config[$idCategory][$data['id']]['file_tags']) ?
                    $config[$idCategory][$data['id']]['file_tags'] : '';
                $data['file_multi_category']     = isset($config[$idCategory][$data['id']]['file_multi_category']) ?
                    $config[$idCategory][$data['id']]['file_multi_category'] : '';
                $data['file_multi_category_old'] = isset($config[$idCategory][$data['id']]['file_multi_category_old']) ?
                    $config[$idCategory][$data['id']]['file_multi_category_old'] : '';
                $data['file_custom_icon'] = isset($config[$idCategory][$data['id']]['file_custom_icon']) ?
                    $config[$idCategory][$data['id']]['file_custom_icon'] : '';
                $data['file_password'] = isset($config[$idCategory][$data['id']]['file_password']) ?
                    $config[$idCategory][$data['id']]['file_password'] : '';
            }

            $data = apply_filters('wpfda_file_info', $data, $this->type);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $data;
    }

    /**
     * Save file information
     *
     * @param array $datas File info
     *
     * @return boolean
     */
    public function saveOnDriveBusinessFileInfos($datas)
    {
        $id     = WpfdAddonHelper::replaceIdOneDrive($datas['id'], false);
        $client = $this->client;
        try {
            $driveItem = $client->getDriveItemById($id);
            $driveItem->rename(str_replace('\&#039;', '\'', $datas['title']) . '.' . $datas['ext']);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Download onedrive business file
     *
     * @param string $fileId File id
     *
     * @return boolean|stdClass|WP_Error
     */
    public function downloadFile($fileId)
    {
        $fileId = WpfdAddonHelper::replaceIdOneDrive($fileId, false);
        $client = $this->client;
        try {
            /* @var \Krizalys\Onedrive\Proxy\DriveItemProxy $item */
            $item    = $client->getDriveItemById($fileId);
            $ret     = new stdClass();
            $ret->id = $item->id;

            if (defined('WPFD_ONEDRIVE_BUSINESS_DIRECT') && WPFD_ONEDRIVE_BUSINESS_DIRECT) {
                $ret->datas = $this->createSharedLink($fileId);
            } else {
                /* @var GuzzleHttp\Psr7\Stream $httpRequest */
                $httpRequest = $item->download();
                $ret->datas = $httpRequest->getContents();
            }

            $ret->title = WpfdAddonHelper::stripExt($item->name);
            $ret->ext   = WpfdAddonHelper::getExt($item->name);
            $ret->size  = $item->size;

            return $ret;
        } catch (Exception $ex) {
            return new WP_Error('broke', esc_html__('Failed to add folder', 'wpfdAddon'));
        }
    }

    /**
     * Upload file to onedrive business
     *
     * @param string  $filename    File name
     * @param array   $file        File info
     * @param string  $fileContent File path
     * @param string  $id_folder   Upload category id
     * @param boolean $replace     Overwrite file name
     *
     * @return array|boolean|OneDrive_Service_Drive_DriveFile
     */
    public function uploadFile($filename, $file, $fileContent, $id_folder, $replace = false)
    {
        if ($replace) {
            $conflictBehavior = ConflictBehavior::REPLACE;
        } else {
            $conflictBehavior = ConflictBehavior::RENAME;
        }

        $id_folder = WpfdAddonHelper::replaceIdOneDrive($id_folder, false);
        $client    = $this->client;
        try {
            $folder = $client->getDriveItemById($id_folder);
        } catch (Exception $e) {
            $file['error'] = esc_html__('Upload failed! ', 'wpfdAddon') . ': ' . $e->getMessage();

            return $file;
        }

        if (!file_exists($fileContent)) {
            $file['error'] = esc_html__('File not exists! Upload failed!', 'wpfdAddon');

            return array(
                'file'   => $file
            );
        }

        $stream = fopen($fileContent, 'rb');
        try {
            $uploadSession = $folder->startUpload($filename, $stream, array('conflictBehavior' => $conflictBehavior));

            /* @var $uploadedItem \Krizalys\Onedrive\Proxy\DriveItemProxy */
            $uploadedItem = $uploadSession->complete();
            $file['name'] = WpfdAddonHelper::stripExt($uploadedItem->name);
            $file['id']   = WpfdAddonHelper::replaceIdOneDrive($uploadedItem->id);
            return array(
                'file'   => $file
            );
        } catch (ConflictException $e) { // File name already exists
            $file['error'] = esc_html__('Upload failed!', 'wpfdAddon') . ': ' . $e->getMessage();
        } catch (Exception $e) {
            $file['error'] = esc_html__('Upload failed! Unknown exception', 'wpfdAddon') . ': ' . $e->getMessage();
        }

        if (isset($file['error'])) {
            return array(
                'file'   => $file
            );
        }

        $file['error'] = esc_html__('Upload failed!', 'wpfdAddon');
        return array(
            'file'   => $file
        );
    }

    /**
     * Upload large files in chunk mode
     *
     * @param string $fileName File name
     * @param string $filePath File path
     * @param string $folderId Folder id
     *
     * @throws Exception Fire if errors
     *
     * @return boolean |false|mixed
     */
    public function uploadLargeFile($fileName, $filePath, $folderId)
    {
        $client = $this->client;
        $guzzle = new GuzzleHttpClient();
        $graph = new Graph();
        $graph->setAccessToken($this->access_token);

        // Initialize upload session
        $response = $graph->createRequest('POST', '/me/drive/items/'.$folderId.':/'.$fileName.':/createUploadSession')
            ->attachBody([
                'name' => $fileName
            ])
            ->execute();
        $uploadSession = $response->getBody();
        $uploadUrl = $uploadSession['uploadUrl'];


        // Upload the file in chunks
        $fileSize = filesize($filePath);
        $chunkSize = 10 * 1024 * 1024; // 10 MB chunk size

        $fileHandle = fopen($filePath, 'rb');
        $chunkNumber = 0;

        while (!feof($fileHandle)) {
            $chunkNumber++;
            $chunkData = fread($fileHandle, $chunkSize);

            $startByte = ($chunkNumber - 1) * $chunkSize;
            $endByte = min($fileSize, $chunkNumber * $chunkSize) - 1;
            $contentRange = 'bytes '.$startByte.'-'.$endByte.'/'.$fileSize;
            $response = $guzzle->request('PUT', $uploadUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Length' => strlen($chunkData),
                    'Content-Range' => $contentRange
                ],
                'body' => $chunkData
            ]);
        }

        fclose($fileHandle);

        try {
            $fileMetadataResponse = $graph->createRequest('GET', '/me/drive/items/'.$folderId.':/'.$fileName)
            ->execute();
            $uploadedFileId = $fileMetadataResponse->getBody()['id'];
            $uploadedFileName = $fileMetadataResponse->getBody()['name'];

            $file['name'] = WpfdAddonHelper::stripExt($uploadedFileName);
            $file['id']   = WpfdAddonHelper::replaceIdOneDrive($uploadedFileId);
            return array(
                'file'   => $file
            );
        } catch (ConflictException $e) { // File name already exists
            $file['error'] = esc_html__('Upload failed!', 'wpfdAddon') . ': ' . $e->getMessage();
        } catch (Exception $e) {
            $file['error'] = esc_html__('Upload failed! Unknown exception', 'wpfdAddon') . ': ' . $e->getMessage();
        }

        return array(
            'file'   => $file
        );
    }

    /**
     * Copy a file
     *
     * @param string $fileId      File id
     * @param string $newParentId Target category
     *
     * @return array
     */
    public function copyFile($fileId, $newParentId)
    {
        $newParentId = WpfdAddonHelper::replaceIdOneDrive($newParentId, false);
        $fileId      = WpfdAddonHelper::replaceIdOneDrive($fileId, false);
        $client = $this->client;
        try {
            $driveItem = $client->getDriveItemById($fileId);
            $copyTo = $client->getDriveItemById($newParentId);
            $location = $driveItem->copy($copyTo);

            if (!empty($location)) {
                sleep(1);
                $response = wp_remote_get($location);
                $response = json_decode($response['body'], true);

                if ($response['status'] === 'completed') {
                    return array('id' => WpfdAddonHelper::replaceIdOneDrive($response['id']));
                } else {
                    $maxTry = 20;
                    $i = 0;
                    while ($response['status'] !== 'completed') {
                        switch ($response['status']) {
                            case 'completed':
                                return array('id' => WpfdAddonHelper::replaceIdOneDrive($response['id']));
                            case 'failed':
                                return array();
                            default:
                                sleep(1);
                                $response = wp_remote_get($location);
                                $response = json_decode($response['body'], true);
                                break;
                        }

                        $i++;
                        if ($i === $maxTry) {
                            break;
                        }
                    }
                }

                return array();
            }
        } catch (Exception $e) {
            print 'An error occurred: ' . esc_html($e->getMessage());
            return array();
        }
    }

    /**
     * Move a file.
     *
     * @param string $fileId      File id
     * @param string $newParentId Target folder id
     *
     * @return WP_Error|boolean
     */
    public function moveFile($fileId, $newParentId)
    {
        $newParentId = WpfdAddonHelper::replaceIdOneDrive($newParentId, false);
        $fileIds     = explode(',', $fileId);
        $client      = $this->client;

        /* Set new parent for item */
        try {
            $parentItem  = $client->getDriveItemById($newParentId);
            foreach ($fileIds as $id) {
                $id   = WpfdAddonHelper::replaceIdOneDrive($id, false);
                $file = $client->getDriveItemById($id);
                $file->move($parentItem);
            }

            return true;
        } catch (Exception $ex) {
            return new WP_Error('broke', esc_html__('Failed to move entry: ', 'wpfdAddon') . $ex->getMessage());
        }
    }

    /**
     * Delete file in onedrive business
     *
     * @param string      $id       File id
     * @param null|string $cloud_id Cloud category id
     *
     * @return boolean
     */
    public function delete($id, $cloud_id = null)
    {
        $id = WpfdAddonHelper::replaceIdOneDrive($id, false);
        if ($cloud_id !== null) {
            $cloud_id = WpfdAddonHelper::replaceIdOneDrive($cloud_id, false);
        }

        try {
            $client = $this->client;
            $file = $client->getDriveItemById($id);

            if ($cloud_id !== null) {
                $found  = false;
                if ($file->parentReference->id === $cloud_id) {
                    $found = true;
                }
                if (!$found) {
                    return true;
                }
            }
            $file->delete();
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            if (strpos($e->getMessage(), 'itemNotFound') !== false) {
                return true;
            }
            return false;
        }

        return true;
    }

    /**
     * Change file name
     *
     * @param string $id       File id
     * @param string $filename New file name
     *
     * @return boolean
     */
    public function changeFilename($id, $filename)
    {
        $id = WpfdAddonHelper::replaceIdOneDrive($id, false);
        try {
            $client = $this->client;
            $file    = $client->getDriveItemById($id);
            $file->rename($filename);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * Get List folder on OneDrive
     *
     * @param string $folderId Filder id
     *
     * @return array|boolean
     */
    public function getListFolder($folderId)
    {
        $datas = array();
        try {
            $this->getFilesInFolder($folderId, $datas);
        } catch (Exception $ex) {
            return false;
        }

        return $datas;
    }

    /**
     * Sub val sort
     *
     * @param array  $a         Input array
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
     * Get files on search
     *
     * @param array $filters Filters
     *
     * @return array
     */
    public function getFiles($filters)
    {
        $listfiles = $this->getAllFilesInAppFolder($filters);
        return $this->displayFileSearch($listfiles);
    }

    /**
     * Retrieve a list of File resources.
     *
     * @param array $filters Filters
     *
     * @return   array List of OneDrive_Service_Drive_DriveFile resources.
     * @internal param OneDrive_Service_Drive $service Drive API service instance.
     */
    public function getAllFilesInAppFolder($filters)
    {
        $onedriveBase    = WpfdAddonHelper::getAllOneDriveBusinessConfigs();
        $onedriverBaseId = WpfdAddonHelper::replaceIdOneDrive($onedriveBase['onedriveBaseFolder']['id'], false);
        $listfiles       = array();
        if (!isset($filters['catid'])) {
            $id = $onedriverBaseId;
        } else {
            $id = WpfdAddonHelper::replaceIdOneDrive($filters['catid'], false);
        }
        if (isset($filters['catid'])) {
            $arrayResults = $this->getFolder($id, '');
            $fileFolder   = $arrayResults['contents'];
            $listFileName = array();
            if (isset($filters['q'])) {
                $arraySearch = $this->getFolder($id, $filters['q']);
                $fileSearch  = $arraySearch['contents'];
                foreach ($fileSearch as $child) {
                    $is_dir = ($child->folder !== null) ? true : false;
                    if (!$is_dir) {
                        $listFileName[] = $child->name;
                    }
                }
                foreach ($fileFolder as $child) {
                    if (!empty($listFileName)) {
                        if (in_array($child->name, $listFileName)) {
                            if ($this->checkTimeCreate($child, $filters)) {
                                $listfiles[] = $child;
                            }
                        }
                    }
                }
            } else {
                if (!empty($fileFolder)) {
                    foreach ($fileFolder as $child) {
                        $is_dir      = ($child->folder !== null) ? true : false;
                        if (!$is_dir) {
                            if ($this->checkTimeCreate($child, $filters)) {
                                $listfiles[] = $child;
                            }
                        }
                    }
                }
            }
        } else {
            if (isset($filters['q'])) {
                $arraySearch = $this->getFolder($id, $filters['q']);
                $fileSearch  = $arraySearch['contents'];
                foreach ($fileSearch as $child) {
                    $is_dir = ($child->folder !== null) ? true : false;
                    if (!$is_dir) {
                        if ($this->checkTimeCreate($child, $filters)) {
                            $listfiles[] = $child;
                        }
                    }
                }
            } else {
                $arraySearch = $this->getFolder($id, $filters['q']);
                $fileSearch  = $arraySearch['contents'];
                foreach ($fileSearch as $child) {
                    $is_dir = ($child->folder !== null) ? true : false;
                    if (!$is_dir) {
                        if ($this->checkTimeCreate($child, $filters)) {
                            $listfiles[] = $child;
                        }
                    }
                }
            }
        }

        return $this->displayFileSearch($listfiles);
    }

    /**
     * Check time create
     *
     * @param Krizalys\Onedrive\Proxy\DriveItemProxy $f       File object
     * @param null|array                             $filters Filters
     *
     * @return boolean
     */
    private function checkTimeCreate($f, $filters = null)
    {
        $fCreatedTime  = date('Y-m-d', $f->createdDateTime->getTimestamp());
        $fTime  = date('Y-m-d', $f->lastModifiedDateTime->getTimestamp());
        $result = false;
        if (isset($filters['cfrom']) && isset($filters['cto'])) {
            if (strtotime($filters['cfrom']) <= strtotime($fCreatedTime) && strtotime($fCreatedTime) <= strtotime($filters['cto'])) {
                $result = true;
            }
        } elseif (isset($filters['ufrom']) && isset($filters['uto'])) {
            if (strtotime($filters['ufrom']) <= strtotime($fTime) && strtotime($fTime) <= strtotime($filters['uto'])) {
                $result = true;
            }
        } else {
            $result = true;
        }

        return $result;
    }

    /**
     * Display files search
     *
     * @param array $listfiles Files list
     *
     * @return array
     */
    private function displayFileSearch($listfiles)
    {
        $result = array();
        $config = get_option('_wpfd_global_config');
        if (!empty($config) && !empty($config['date_format'])) {
            $dateFormat = $config['date_format'];
        } else {
            $dateFormat = 'd-m-Y h:i:s';
        }
        if (!empty($listfiles)) {
            foreach ($listfiles as $f) {
                /* @var Krizalys\Onedrive\Proxy\DriveItemProxy $f */
                $parentRef              = $f->parentReference;
                $arrayResults           = $this->getFolder($parentRef->id, '');
                $fileSearch             = $arrayResults['folder'];
                $idItem                 = WpfdAddonHelper::replaceIdOneDrive($f->id);
                $termID                 = WpfdAddonHelper::getTermIdOneDriveBusinessByOneDriveId($idItem);
                $file                   = new stdClass();
                $file->id               = $idItem;
                $file->ID               = $file->id;
                $file->title            = WpfdAddonHelper::stripExt($f->name);
                $file->post_title       = $file->title;
                $file->post_name        = $file->title;
                $file->description      = $f->description;
                $file->ext              = WpfdAddonHelper::getExt($f->name);
                $file->size             = $f->size;

                $file->created_time     = get_gmt_from_date($f->createdDateTime->format('Y-m-d H:i:s'));
                $file->modified_time    = get_gmt_from_date($f->lastModifiedDateTime->format('Y-m-d H:i:s'));
                $file->created          = mysql2date($dateFormat, $file->created_time);
                $file->modified         = mysql2date($dateFormat, $file->modified_time);
                $file->versionNumber    = '';
                $file->version          = '';
                $file->hits             = 0;
                $file->ordering         = 0;
                $file->file_custom_icon = '';
                $file->parentRefId      = $fileSearch->id;
                $file->parentRefName    = $fileSearch->name;

                $config = WpfdAddonHelper::getOneDriveBusinessFileInfos();
                if (!empty($config) && isset($config[$termID]) && isset($config[$termID][$f['id']])) {
                    $file->file_tags = isset($config[$termID][$f['id']]['file_tags']) ?
                        $config[$termID][$f['id']]['file_tags'] : '';
                    $file->file_custom_icon = isset($config[$termID][$f['id']]['file_custom_icon']) ?
                        $config[$termID][$f['id']]['file_custom_icon'] : '';
                    $file->state = isset($config[$termID][$f['id']]['state']) ?
                        $config[$termID][$f['id']]['state'] : '1';
                }
                if (!empty($config)) {
                    foreach ($config as $key => $value) {
                        if (!array_key_exists($file->id, $value)) {
                            continue;
                        }
                        $file->file_password = (isset($value[$file->id]['file_password'])) ? $value[$file->id]['file_password'] : '';
                    }
                }
                $result[] = $file;
            }
        }

        return $result;
    }

    /**
     * Get folders and files
     *
     * @param boolean $folderid       Folder id
     * @param string  $searchfilename Search file name
     *
     * @return array|boolean
     */
    public function getFolder($folderid, $searchfilename)
    {
        $folderid = WpfdAddonHelper::replaceIdOneDrive($folderid, false);

        try {
            $client = $this->client;
            if (isset($searchfilename) && $searchfilename !== '') {
                $contents = array();
                $results  = $client->getDriveItemById($folderid);
                $parents  = $results->parentReference;

                // Search by multiple terms
                $keywords = explode(',', $searchfilename);
                if (is_array($keywords) && count($keywords)) {
                    foreach ($keywords as $keyword) {
                        $foundContents = $this->searchInFolder($folderid, stripslashes(trim($keyword)));
                        $contents = array_merge($contents, $foundContents);
                    }
                }
            } else {
                $results  = $client->getDriveItemById($folderid);
                $parents  = $results->parentReference;
                $contents = $results->getChildren(
                    array(
                        'top' => $this->maxItemNumbers
                    )
                );
            }

            return array('folder' => $results, 'contents' => $contents, 'parent' => $parents->id);
        } catch (Exception $ex) {
            $this->lastError = $ex->getMessage();
            return false;
        }
    }

    /**
     * Search file in onedrive business
     *
     * @param string $folderId Folder id
     * @param string $keyword  Keyword
     *
     * @return \Krizalys\Onedrive\Proxy\DriveItemProxy[]|boolean
     * @throws \Microsoft\Graph\Exception\GraphException Request fail
     */
    public function searchInFolder($folderId, $keyword = '')
    {
        if (!empty($this->access_token)) {
            $graph = new Graph();
            $graph->setAccessToken($this->access_token);
            $url = '/me/drive/root/search';
            $response = $graph->createRequest('GET', $url . "(q='" . $keyword . "')")->execute();

            $status = $response->getStatus();

            if ($status !== 200) {
                throw new \Exception('Unexpected status code produced by \'GET ' . $url . '\': ' . $status);
            }
            // phpcs:ignore PHPCompatibility.Constants.NewMagicClassConstant.Found -- it's OK, plugin now require php 5.4
            $driveItems = $response->getResponseAsObject(DriveItem::class);

            if (!is_array($driveItems)) {
                return array();
            }

            return array_map(function (DriveItem $driveItem) use ($graph) {
                return new \Krizalys\Onedrive\Proxy\DriveItemProxy(
                    $graph,
                    $driveItem,
                    new \Krizalys\Onedrive\Definition\ResourceDefinition(array('search'), array('get'))
                );
            }, $driveItems);
        }

        return false;
    }

    /**
     * Watch changes
     *
     * @return boolean|array
     * @throws Exception Throw when failed
     *
     * @see https://docs.microsoft.com/en-us/graph/api/resources/subscription?view=graph-rest-1.0
     */
    public function watchChanges()
    {
        $client = $this->client;
        $drivers = $client->getDrives();
        $watchDatas = array();
        foreach ($drivers as $drive) {
            $this->writeLog('Driver ID: '.  $drive->id);
            $driveId = $drive->id;
            try {
                $subscriptionData = $this->createSubscription($driveId);
            } catch (Exception $e) {
                $this->writeLog(__METHOD__ . ' : DRIVE ID: ' . $driveId . ' Exception: ' . $e->getMessage());
                continue;
            }

            if (!$subscriptionData) {
                $this->writeLog(__METHOD__ . ' : Watch changes failed for DRIVE ID: ' . $driveId);
                continue;
            }
            $watchDatas[$subscriptionData->getId()] = $subscriptionData;
        }

        $this->writeLog(__METHOD__ . ': watch changes success!');
        update_option('_wpfd_onedrive_business_watch_data', $watchDatas);
        try {
            $this->getDeltaLinkData();
        } catch (Exception $e) {
            $this->writeLog(__METHOD__ . ' :' . $e->getMessage());
        }
        // Get the first deltaLink
        return true;
    }

    /**
     * Create subscription
     *
     * @param string $driveId Drive id
     *
     * @return Subscription | boolean
     *
     * @throws Exception Throw when error
     */
    public function createSubscription($driveId)
    {
        $config = $this->getConfig();
        $ajaxUrl = admin_url('admin-ajax.php');
        $callbackUrl = $ajaxUrl . '?action=onedriveBusinessPushListener';
        $subscription = new Subscription(array(
            'changeType'         => 'updated',
            'notificationUrl'    => $callbackUrl,
            'resource'           => 'drives/' . $driveId . '/root', // The relative path of the subscription within the drive. Read-only.
            'expirationDateTime' => gmdate('Y-m-d\TH:i:s\Z', strtotime('+ 2 days')),
            'clientState'        => 'wpfd-addon-onedrive-business-subscription',
        ));

        $graph = new Graph;
        $graph->setAccessToken($this->access_token);
        $graph->setApiVersion('beta');
        try {
            $response = $graph->createRequest('POST', '/subscriptions')
                ->attachBody($subscription)
                ->execute();
        } catch (Exception $e) {
            $this->writeLog(__METHOD__ . ':' . $e->getMessage());
            throw new \Exception('Unexpected status code produced by \'GET /subscriptions\': ' . $e->getMessage());
        }

        $status = $response->getStatus();

        if ($status !== 201) {
            $this->writeLog(__METHOD__ . ': Watch changes failed. Response code: ' . $status);
            throw new \Exception('Unexpected status code produced by \'GET /subscriptions\': ' . $status);
        }

        $data = $response->getResponseAsObject(Subscription::class);

        return $data;
    }

    /**
     * List subscriptions
     *
     * @return mixed
     * @throws Exception Throw if failed
     */
    public function listSubscriptions()
    {
        $config = $this->getConfig();
        $graph = new Graph;
        $graph->setAccessToken($this->access_token);
        try {
            $response = $graph->createRequest('GET', '/subscriptions')
                              ->execute();
        } catch (Exception $e) {
            throw new \Exception('Unexpected status code produced by \'GET /subscriptions\': ' . $e->getMessage());
        }

        $status = $response->getStatus();

        if ($status !== 200) {
            throw new \Exception('Unexpected status code produced by \'GET /subscriptions\': ' . $status);
        }

        $data = $response->getBody();

        if (!is_array($data)) {
            return false;
        }

        return $data;
    }

    /**
     * Check subscription and renew if it expiration soon
     *
     * @return boolean
     * @throws Exception Throw when failed
     */
    public function checkSubscriptions()
    {
        $this->writeLog('Check subscriptions: ' . date('d-m-Y H:i:s'));

        $watchChanges = get_option('_wpfd_onedrive_business_watch_changes', 0);
        if (intval($watchChanges) === 0) {
            $this->writeLog('Watch changes turn off, exit!');
            return false;
        }

        // Get current subscriptions information
        $watchDatas = get_option('_wpfd_onedrive_business_watch_data', '');

        if ($watchDatas === '') {
            return false;
        }

        $this->writeLog('Current subscriptions information', $watchDatas);
        // Check expiry date
        if (is_array($watchDatas) && count($watchDatas) > 0) {
            $errors = array();
            /* @var Subscription[] $watchDatas */
            foreach ($watchDatas as $subscription) {
                $expiredDate = $subscription->getExpirationDateTime()->getTimestamp();

                $checkpoint = strtotime('today + 1 days');
                $this->writeLog('ExpirationDate: ' . $subscription->getExpirationDateTime()->format('d-m-Y H:i:s'));
                $this->writeLog('Current Date: ' . date('d-m-Y H:i:s', $checkpoint));
                if ($expiredDate < $checkpoint) {
                    // Renew it
                    try {
                        $this->renewSubscriptions($subscription);
                    } catch (Exception $e) {
                        $errors[] = true;
                        $this->writeLog($e->getMessage());
                    }
                }
            }
        }

        return false;
    }

    /**
     * Renew subscriptions
     *
     * @param Subscription $subscription Subscription data
     *
     * @return boolean
     *
     * @throws Exception Can not renew subscription
     */
    public function renewSubscriptions(Subscription $subscription)
    {
        $newExpiredTime = $subscription->getExpirationDateTime()->add(new \DateInterval('P2D'));
        $patchData = new Subscription();
        $patchData->setExpirationDateTime($newExpiredTime);
        // Send Patch
        $config = $this->getConfig();
        $graph  = new Graph;
        $graph->setAccessToken($this->access_token);
        try {
            $response = $graph->createRequest('PATCH', '/subscriptions/' . $subscription->getId())
                              ->attachBody($patchData)
                              ->execute();
        } catch (Exception $e) {
            throw new \Exception('Unexpected status code produced by \'PATCH /subscriptions/' . $subscription->getId() .'\': ' . $e->getMessage());
        }

        $status = $response->getStatus();

        if ($status !== 200) {
            throw new \Exception('Unexpected status code produced by \'PATCH /subscriptions/' . $subscription->getId() .'\': ' . $status);
        }

        /* @var Subscription $data */
        $data = $response->getResponseAsObject(Subscription::class);
        $this->writeLog('Subscription renewed. Id: ' . $data->getId() . ' New expiried time: ' . $data->getExpirationDateTime()->format('d-m-Y H:i:s'));
        $this->updateWatchData($data);

        return true;
    }

    /**
     * Update watch changes data
     *
     * @param Subscription $data Subscription data
     *
     * @return void
     */
    public function updateWatchData(Subscription $data)
    {
        $option = '_wpfd_onedrive_business_watch_data';
        $watchDatas = get_option($option, '');

        if ($watchDatas === '' || !is_array($watchDatas)) {
            return;
        }

        if (isset($watchDatas[$data->getId()])) {
            $watchDatas[$data->getId()] = $data;

            update_option($option, $watchDatas);
        }
    }

    /**
     * Get watch changes data
     *
     * @return void|Subscription|string
     */
    public function getWatchDatas()
    {
        return get_option('_wpfd_onedrive_business_watch_data', '');
    }

    /**
     * Stop watch a channel
     *
     * @param Subscription $subscription Subscription
     *
     * @return boolean
     * @throws Exception Throw exception on failed
     */
    public function stopWatch($subscription)
    {
        $config = $this->getConfig();
        $graph = new Graph;
        $graph->setAccessToken($this->access_token);
        try {
            $response = $graph->createRequest('DELETE', '/subscriptions/' . esc_attr($subscription->getId()))->execute();
        } catch (Exception $e) {
            $this->writeLog(__METHOD__ . ': ' . $e->getMessage());
            throw new \Exception('Unexpected status code produced by \'DELETE /subscriptions/'.esc_attr($subscription->getId()).'\': ' . $e->getMessage());
        }

        $status = $response->getStatus();

        if ($status !== 204) {
            $this->writeLog(__METHOD__ . ': ' . $status);
            throw new \Exception('Unexpected status code produced by \'GET /subscriptions/'.esc_attr($subscription->getId()).'\': ' . $status);
        }

        // Clean old delta links
        update_option('_wpfd_onedrive_business_delta_links', '');
        return true;
    }

    /**
     * Get delta link of drive
     *
     * @return DriveItem[]|array
     *
     * @throws Exception Throw when failed
     */
    public function getDeltaLinkData()
    {
        $config = $this->getConfig();

        $graph = new Graph;
        $graph->setAccessToken($this->access_token);
        // Get resource list
        $subscriptions = $this->getWatchDatas();
        if ($subscriptions === '') {
            return array();
        }
        $driveItems = array();
        $deltaLinks = array();
        /* @var Subscription $subscription */
        foreach ($subscriptions as $subscription) {
            $resource = $subscription->getResource();
            $deltaLink = '/' . $resource . '/delta';

            try {
                list($items, $nextDeltaLink) = $this->travelDeltaLink($deltaLink);
            } catch (Exception $e) {
                $this->writeLog(__METHOD__ . ' :' . $e->getMessage());
                continue;
            }
            // Save next deltalink for each subscription
            $deltaLinks[$subscription->getId()] = $nextDeltaLink;
            if (is_array($items)) {
                $driveItems = array_merge($driveItems, $items);
            }
        }
        // Update nextDeltaLink
        update_option('_wpfd_onedrive_business_delta_links', $deltaLinks);
        return $driveItems;
    }

    /**
     * Sync changes by drive items from delta link response
     *
     * @param array $notifications Subscription information from notification
     *
     * @return void
     *
     * @since 4.4.x
     */
    public function syncChanges($notifications)
    {
        $deltaLinks = get_option('_wpfd_onedrive_business_delta_links', '');
        $subscriptions = $this->getWatchDatas();

        if (!is_array($subscriptions) || !is_array($deltaLinks)) {
            $this->writeLog('No subscriptions!');
            return;
        }
        update_option('_wpfd_onedrive_business_on_sync', 1);
        // todo: do we need check watch changes or not?
        foreach ($notifications as $notification) {
            if (!isset($notification['subscriptionId'])) {
                // Stop, what is this notification :lol
                continue;
            }
            $subscriptionId = $notification['subscriptionId'];

            if (!key_exists($subscriptionId, $subscriptions)) {
                // We don't listen for this subscription
                continue;
            }

            if (!key_exists($subscriptionId, $deltaLinks)) {
                // Delta link for this subscription not exists
                // todo: get first delta link for this one, but now just ignore it.
                continue;
            }

            // Get data from delta link
            try {
                list($driveItems, $deltaLink) = $this->travelDeltaLink($deltaLinks[$subscriptionId]);
            } catch (Exception $e) {
                $this->writeLog(__METHOD__ . ' :' . $e->getMessage());
                continue;
            }
            // Do sync changes
            $this->updateChanges($driveItems);
            // Save new delta link
            $this->updateDeltaLink($subscriptionId, $deltaLink);
        }
        update_option('_wpfd_onedrive_business_last_sync_changes', time());
        update_option('_wpfd_onedrive_business_on_sync', 0);
    }

    /**
     * Update changes
     *
     * @param DriveItem $driveItems Onedrive Drive item
     *
     * @return void
     *
     * @since 4.4.x
     */
    public function updateChanges($driveItems)
    {
        if (!is_array($driveItems)) {
            $this->writeLog('This is not a correct drive Item!');
            return;
        }
        Application::getInstance('WpfdAddon');
        /* @var WpfdAddonModelOnedriveBusinessCategory $onedriveCategory */
        $onedriveCategory = WpfdModel::getInstance('onedrivebusinesscategory');
        Application::getInstance('Wpfd');
        /* @var WpfdModelcategory $modelCategory */
        $modelCategory = WpfdModel::getInstance('category');
        $config = $this->getConfig();
        $baseFolder = (isset($config['onedriveBaseFolder']) && is_array($config['onedriveBaseFolder'])) ? $config['onedriveBaseFolder'] : null;
        if (is_null($baseFolder) || !isset($baseFolder['id'])) {
            $this->writeLog('Base folder not found!');
            return;
        }
        /* @var DriveItem $driveItem */
        foreach ($driveItems as $driveItem) {
            // Prevent root folder
            if (is_null($driveItem->getParentReference())) {
                continue;
            }

            // Prevent file
            // todo: clean cache if we use it
            if (is_null($driveItem->getFolder())) {
                continue;
            }

            // Prevent base folder, we don't need to do anything with it
            // todo: maybe base folder rename check
            if (str_replace('!', '-', $driveItem->getId()) === str_replace('!', '-', $baseFolder['id'])) {
                continue;
            }
            // Is file deleted
            if ($driveItem->getDeleted() && $driveItem->getFile()) {
                // todo: maybe clean cache
                continue;
            }

//            if ((!is_null($driveItem->getParentReference()->getDriveType()) && $driveItem->getParentReference()->getDriveType() !== 'business') || is_null($driveItem->getParentReference()->getId())) {
//                // Made sure this is Onedrive Business and not root folder
//                continue;
//            }

            $action = $this->getChangeAction($driveItem);
            $this->writeLog($action);
            // Clear cache for parent
            // WpfdCloudCache::deleteTransient($parent, 'googleDrive');
            if (!$action) {
                continue;
            }

            switch ($action) {
                case 'folder_created':
                    try {
                        // Single folder created
                        $parentCat = $this->getCategoryByOneDriveId($driveItem->getParentReference()->getId());
                        $parentCatId = isset($parentCat->term_id) ? $parentCat->term_id : 0;
                        $onedriveCategory->addCategoryFromOneDriveBusiness($driveItem->getName(), $driveItem->getId(), $parentCatId);

                        // When drag and drop a category tree
                        $this->syncOnedriveToLocalFolder($driveItem->getId());
                    } catch (Exception $e) {
                        break;
                    }
                    break;
                case 'folder_moved':
                    try {
                        if ($baseFolder['id'] === $driveItem->getParentReference()->getId()) {
                            $parentCatId = 0;
                        } else {
                            $parentCat  = $this->getCategoryByOneDriveId($driveItem->getParentReference()->getId());
                            $parentCatId = (int) $parentCat->term_id;
                        }

                        $currentCat = $this->getCategoryByOneDriveId($driveItem->getId());
                        $pk       = $currentCat->term_id; // Catid
                        $ref      = $parentCatId; // Parent
                        $position = 'first-child';

                        $modelCategory->changeOrder($pk, $ref, $position);
                    } catch (Exception $e) {
                        break;
                    }
                    break;
                case 'folder_modified':
                    try {
                        $newName = $driveItem->getName();
                        $currentCat = $this->getCategoryByOneDriveId($driveItem->getId());
                        // Rename local category
                        if ($currentCat && $currentCat->name !== $newName) {
                            $modelCategory->saveTitle($currentCat->term_id, $newName);
                        }
                        // Change order
                        // Check is folder parent changed for root category
                        if ($baseFolder['id'] === $driveItem->getParentReference()->getId()) {
                            $parentCatId = 0;
                        } else {
                            $parentCat  = $this->getCategoryByOneDriveId($driveItem->getParentReference()->getId());
                            if (!isset($parentCat->term_id)) {
                                break;
                            }
                            $parentCatId = (int) $parentCat->term_id;
                        }

                        $currentCat = $this->getCategoryByOneDriveId($driveItem->getId());
                        if (!isset($currentCat->parent)) {
                            break;
                        }
                        if ($currentCat->parent !== $parentCatId) {
                            $pk       = $currentCat->term_id; // Catid
                            $ref      = $parentCatId; // Parent
                            $position = 'first-child';

                            $modelCategory->changeOrder($pk, $ref, $position);
                        }
                    } catch (Exception $e) {
                        break;
                    }
                    break;
                case 'folder_removed':
                    try {
                        // Remove in categories
                        $localFolder = $this->getCategoryByOneDriveId($driveItem->getId());
                        if (!isset($localFolder->term_id)) {
                            // Folder removed
                            break;
                        }
                        $cid = (int) $localFolder->term_id;

                        $children = $modelCategory->getChildren($cid);


                        $deletedTermIds = array();
                        if ($modelCategory->delete($cid)) {
                            $deletedTermIds[] = $cid;
                            $children[] = $cid;
                            foreach ($children as $child) {
                                if ($child === $cid) {
                                    continue;
                                }
                                $modelCategory->delete($child);
                                $deletedTermIds[] = $child;
                            }
                        }

                        $deletedTermIds = array_map('intval', $deletedTermIds);
                        $dataOnedriveBusinessCategory  = get_option('_wpfdAddon_onedrive_business_category_params');

                        $deletedCloudIds = array();
                        if ($dataOnedriveBusinessCategory) {
                            foreach ($dataOnedriveBusinessCategory as $key => $value) {
                                if ((int) $value['termId'] === (int) $cid && in_array((int) $cid, $deletedTermIds)) {
                                    // Delete params
                                    $deletedCloudIds[] = $value['oneDriveId'];
                                    unset($dataOnedriveBusinessCategory[$key]);
                                }
                            }
                            update_option('_wpfdAddon_onedrive_business_category_params', $dataOnedriveBusinessCategory);

                            // Delete cache
//                            foreach ($deletedCloudIds as $objectId) {
//                                WpfdCloudCache::deleteTransient($objectId, 'onedrive_business');
//                            }
                        }
                    } catch (Exception $e) {
                        break;
                    }
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Get change action
     *
     * @param DriveItem $driveItem Onedrive Drive item
     *
     * @return boolean|string
     *
     * @since 4.4.x
     */
    public function getChangeAction($driveItem)
    {
        $this->writeLog(__METHOD__, $driveItem);
        if (!$driveItem instanceof DriveItem) {
            $this->writeLog('This is not a correct drive Item!');
            return false;
        }

        Application::getInstance('WpfdAddon');
        $config = $this->getConfig();
        $baseFolder = $config['onedriveBaseFolder'];
        $baseFolderId = (isset($baseFolder['id']) && $baseFolder['id'] !== '') ? $baseFolder['id'] : null;
        $id = $driveItem->getId();
        $isFolder = !is_null($driveItem->getFolder()) ? true : false;
        $trashed = !is_null($driveItem->getDeleted()) ? true : false;
        $parent = $driveItem->getParentReference()->getId();
        if ($isFolder && $this->inFolderList($id) && $trashed) {
            return 'folder_removed';
        }
//        if ($id === $baseFolderId) {
//            $this->writeLog('Root folder detected! Return!');
//            return false;
//        }
        if (!$this->inFolderList($parent)) {
            if ($isFolder && $this->inFolderList($id) && $trashed === false) {
                return 'folder_removed'; // Folder move out of base folder
            }
            $this->writeLog('This parent not in list!');
            return false;
        }
        if ($isFolder) {
            // Is folder
            if (!$this->inFolderList($id) && $this->inFolderList($parent) && $trashed === false) {
                return 'folder_created';
            } elseif ($this->inFolderList($id) && $this->inFolderList($parent) && $this->isParentFolderChanged($id, $parent, $baseFolderId) && $parent !== $baseFolderId && $trashed === false) {
                return 'folder_moved';
            } elseif ($trashed) {
                return 'folder_removed';
            } else {
                // Check folder renamed
                $localCategory = $this->getCategoryByOneDriveId($id);
                if (isset($localCategory->name) && $localCategory->name !== $driveItem->getName()) {
                    return 'folder_modified';
                }
            }
        }
        $this->writeLog('Nothing else! Return!');
        return false;
    }

    /**
     * Is Parent folder changed
     *
     * @param string $folderId     Folder id
     * @param string $newParentId  New parent id
     * @param string $baseFolderId Base folder id
     *
     * @return boolean
     * @since  4.4.x
     */
    private function isParentFolderChanged($folderId, $newParentId, $baseFolderId)
    {
        $localCat         = $this->getCategoryByOneDriveId($folderId);

        $localCatParentId = isset($localCat->parent) ? (int) $localCat->parent : 0;

        if (str_replace('!', '-', $newParentId) === str_replace('!', '-', $baseFolderId)) {
            $newCatParentId = 0;
        } else {
            $parentCat = $this->getCategoryByOneDriveId($newParentId);
            if (!isset($parentCat->term_id)) {
                return false;
            }
            $newCatParentId = (int) $parentCat->term_id;
        }

        if ($localCatParentId !== $newCatParentId) {
            return true;
        }

        return false;
    }
    /**
     * Check is folder in local list
     *
     * @param string $id Folder id
     *
     * @return boolean
     * @since  4.4.x
     */
    private function inFolderList($id)
    {
        $maybeTermId = WpfdAddonHelper::getTermIdOneDriveBusinessByOneDriveId($id);
        if (!$maybeTermId) {
            // Add onedrive bussiness base folder to folder list
            $config = $this->getConfig();
            $baseFolder = $config['onedriveBaseFolder'];
            $baseFolderId = (isset($baseFolder['id']) && $baseFolder['id'] !== '') ? $baseFolder['id'] : null;

            if ($id === $baseFolderId) {
                return true;
            }
        }

        return $maybeTermId;
    }

    /**
     * Get category by cloud id
     *
     * @param string $cloudId Onedrive Business category id
     *
     * @return mixed
     *
     * @since 4.4.x
     */
    protected function getCategoryByOneDriveId($cloudId)
    {
        $termId = WpfdAddonHelper::getTermIdOneDriveBusinessByOneDriveId($cloudId);
        Application::getInstance('Wpfd');
        $category = WpfdModel::getInstance('category');

        return $category->getCategory($termId);
    }

    /**
     * Sync A Onedrive Business Category with local
     *
     * @param string $cloudId Cloud category id
     *
     * @return boolean
     * @since  4.4.x
     */
    private function syncOnedriveToLocalFolder($cloudId)
    {
        // Step 1: get category children
        try {
            $newCategories = $this->getListFolder($cloudId);
            $onedriveCategory = WpfdModel::getInstance('onedrivebusinesscategory');
            // Step 2: sync with local
            if (count($newCategories) > 0) {
                $lstCloudIdOnWPFD = get_option('_wpfdAddon_onedrive_business_category_params', array());
                foreach ($newCategories as $CloudId => $folderData) {
                    // If has parent_id.
                    if ($folderData['parent_id'] === 0) {
                        // Create Folder New
                        $onedriveCategory->addCategoryFromOneDriveBusiness($folderData['title'], $CloudId, 0);
                        $lstCloudIdOnWPFD = get_option('_wpfdAddon_onedrive_business_category_params', array());
                    } else {
                        $lstCloudIdOnWPFD = get_option('_wpfdAddon_onedrive_business_category_params', array());
                        $check = in_array($folderData['parent_id'], $lstCloudIdOnWPFD);
                        if (!$check) {
                            $lstCloudIdOnWPFD = get_option('_wpfdAddon_onedrive_business_category_params', array());
                            // Create Parent New
                            $ParentCloudInfo = $lstCloudIdOnWPFD[$folderData['parent_id']];
                            $onedriveCategory->addCategoryFromOneDriveBusiness(
                                $ParentCloudInfo['title'],
                                $folderData['parent_id'],
                                0
                            );

                            // Create Children New with parent_id in WPFD
                            if ($this->getCategoryByOneDriveId($folderData['parent_id'])) {
                                $catRecentCreate = $this->getCategoryByOneDriveId($folderData['parent_id']);
                                $onedriveCategory->addCategoryFromOneDriveBusiness(
                                    $folderData['title'],
                                    $CloudId,
                                    $catRecentCreate->term_id
                                );
                            }
                            $lstCloudIdOnWPFD = get_option('_wpfdAddon_onedrive_business_category_params', array());
                        } else {
                            // Create Children New with parent_id in WPFD
                            $catOldInfo = $this->getCategoryByOneDriveId($folderData['parent_id']);
                            $onedriveCategory->addCategoryFromOneDriveBusiness(
                                $folderData['title'],
                                $CloudId,
                                $catOldInfo->term_id
                            );
                            $lstCloudIdOnWPFD = get_option('_wpfdAddon_onedrive_business_category_params', array());
                        }
                    }

                    // Step 3: Sync file with local
                    // $this->syncFileByCloudId($CloudId);
                }
            }
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Update delta link
     *
     * @param string $subscriptionId Subscription id
     * @param string $deltaLink      New delta link
     *
     * @return void
     *
     * @since 4.4.x
     */
    public function updateDeltaLink($subscriptionId, $deltaLink)
    {
        $option = '_wpfd_onedrive_business_delta_links';
        $deltaLinks = get_option($option, '');

        if ($deltaLinks === '' || !is_array($deltaLinks)) {
            return;
        }

        if (isset($deltaLinks[$subscriptionId])) {
            $deltaLinks[$subscriptionId] = $deltaLink;

            update_option($option, $deltaLinks);
        }
    }

    /**
     * Travel delta link
     *
     * @param string $deltaLink Delta link
     *
     * @return array
     *
     * @throws Exception Throw exception when response error
     */
    public function travelDeltaLink($deltaLink)
    {
        $this->writeLog(__METHOD__ . 'Old Delta Link: ' . $deltaLink);
        $config = $this->getConfig();

        $graph = new Graph;
        $graph->setAccessToken($this->access_token);

        try {
            $response = $graph->createRequest('GET', $deltaLink)->execute();
        } catch (Exception $e) {
            $this->writeLog(__METHOD__ . ': ' . $e->getMessage());
            throw new \Exception('Unexpected status code produced by \'GET \'' . $deltaLink . '\'\': ' . $e->getMessage());
        }
        $status = $response->getStatus();

        if ($status !== 200) {
            $this->writeLog(__METHOD__ . ': ' . $status);
            throw new \Exception('Unexpected status code produced by \'GET /me/drive/root/delta/' . $status);
        }
        /* @var GraphResponse $response */
        $deltaLink = $response->getDeltaLink();
        $nextLink = $response->getNextLink();
        $driveItems = $response->getResponseAsObject(DriveItem::class);

        $this->writeLog('Changes data: ', $driveItems);
        while (is_null($deltaLink)) {
            // Collect items
            try {
                // todo: Find better way to replace this
                $endpoint = str_replace('https://graph.microsoft.com/v1.0', '', $nextLink);
                $response = $graph->createRequest('GET', $endpoint)->execute();
            } catch (Exception $e) {
                $this->writeLog(__METHOD__ . ': ' . $e->getMessage());
                throw new \Exception('Unexpected status code produced by \'GET ' . $endpoint . '\': ' . $e->getMessage());
            }
            $status = $response->getStatus();

            if ($status !== 200) {
                $this->writeLog(__METHOD__ . ': ' . $status);
                throw new \Exception('Unexpected status code produced by \'GET ' . $endpoint . $status);
            }

            $items = $response->getResponseAsObject(DriveItem::class);

            if (is_array($items)) {
                $driveItems = array_merge($driveItems, $items);
            }

            $nextLink = $response->getNextLink();
            $deltaLink = $response->getDeltaLink();
        }
        $this->writeLog(__METHOD__ . ' New Delta Link: ' .  $deltaLink);
        return array($driveItems, $deltaLink);
    }
    /**
     * Write error log
     *
     * @param string     $message Message
     * @param null|mixed $data    Log data
     *
     * @return void
     */
    public function writeLog($message, $data = null)
    {
        $prefix = '[' . strtoupper(str_replace('_', ' ', $this->type)) . ']';
        if ($this->debug) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug purposed
            error_log($prefix . $message);
            if ($data !== null) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r,WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug purposed
                error_log($prefix . print_r($data, true));
            }
        }
    }

    /**
     * Create shared link
     *
     * @param string $fileId File id
     *
     * @return boolean|string
     *
     * @throws GraphException If response is invalid
     */
    public function createSharedLink($fileId)
    {
        $fileId = WpfdAddonHelper::replaceIdOneDrive($fileId, false);
        if (!empty($this->access_token)) {
            try {
                $graph = new Graph();
                $graph->setAccessToken($this->access_token);
                $url = '/me/drive/items/' . $fileId . '/createLink';
                $response = $graph
                    ->createRequest('POST', $url)
                    ->attachBody(array('type' => 'view', 'scope' => 'anonymous'))
                    ->setReturnType(Model\Permission::class) // phpcs:ignore PHPCompatibility.Constants.NewMagicClassConstant.Found -- Use to sets the return type of the response object
                    ->execute();

                // Business
                return $response->getLink()->getWebUrl() . '?download=1';
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Increase hits when downoad
     *
     * @param string  $fileId File id
     * @param integer $catId  Category Id
     *
     * @return void
     */
    public function hits($fileId, $catId)
    {
        $idItem = WpfdAddonHelper::replaceIdOneDrive($fileId, true);
        $fileInfos = WpfdAddonHelper::getOneDriveBusinessFileInfos();
        if (!empty($fileInfos)) {
            $hits = isset($fileInfos[$catId][$idItem]['hits']) ? intval($fileInfos[$catId][$idItem]['hits']) : 0;
            $fileInfos[$catId][$idItem]['hits'] = $hits + 1;
        } else {
            $fileInfos = array();
            $fileInfos[$catId][$idItem]['hits'] = 1;
        }
        WpfdAddonHelper::setOneDriveBusinessFileInfos($fileInfos);
    }

    /**
     * Delete all local folder and OneDrive Business folder for testing purpose
     *
     * @return void
     *
     * @throws Exception Fire message if errors
     */
    public function deleteAllFolder()
    {
        $onedriveconfig = $this->getConfig();

        $base_folder = isset($onedriveconfig['onedriveBaseFolder']) ? $onedriveconfig['onedriveBaseFolder'] : false;

        if (is_array($base_folder) && isset($base_folder['id'])) {
            $folders = array();
            try {
                $this->getFilesInFolder($base_folder['id'], $folders, false);

                foreach ($folders as $folderId => $folder) {
                    $termId = WpfdAddonHelper::getTermIdOnedriveBusinessByOneDriveId($folderId);
                    wp_delete_term($termId, 'wpfd-category');
                    $this->delete($folderId);
                }
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }
    }
    /**
     * Get folder information
     *
     * @param string $folderId Folder id
     *
     * @return array|boolean
     */
    public function getFolderInfo($folderId)
    {
        $client = $this->getClient();
        try {
            $file = $client->getDriveItemById($folderId);
            return array(
                'id' => $file->id,
                'name' => $file->name,
                'parent_id' => $file->parentReference->id
            );
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get versions count
     *
     * @param string $fileId Onedrive Business File Id
     *
     * @return false|integer|void
     */
    public function getVersionsCount($fileId)
    {
        $revisions = $this->getListVersions($fileId);

        return count($revisions);
    }

    /**
     * Get list versions
     *
     * @param string $fileId File id
     *
     * @return array
     */
    public function getListVersions($fileId)
    {
        if (empty($fileId)) {
            return array();
        }

        if (empty($this->params->access_token)) {
            return array();
        }

        try {
            $graph = new Graph();
            $graph->setAccessToken($this->params->access_token);
            $url = sprintf('/me/drive/items/%s/versions', $fileId);

            $response = $graph->createRequest('GET', $url)->execute();

            $status = $response->getStatus();

            if ($status !== 200) {
                return array();
            }
            // phpcs:ignore PHPCompatibility.Constants.NewMagicClassConstant.Found -- it's OK, plugin now require php 5.4
            $driveItemVersions = $response->getResponseAsObject(\Microsoft\Graph\Model\DriveItemVersion::class);

            if (!is_array($driveItemVersions)) {
                return array();
            }

            return $driveItemVersions;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return array();
        }
    }

    /**
     * Restore file version
     *
     * @param string $fileId    File Id
     * @param string $versionId Version Id
     *
     * @return boolean
     */
    public function restoreVersion($fileId, $versionId)
    {
        if (empty($fileId) || empty($versionId)) {
            return false;
        }

        if (empty($this->params->access_token)) {
            return false;
        }

        try {
            $graph = new Graph();
            $graph->setAccessToken($this->params->access_token);
            $url = sprintf('/me/drive/items/%s/versions/%s/restoreVersion', $fileId, $versionId);

            $response = $graph->createRequest('POST', $url)->execute();

            $status = $response->getStatus();

            if ($status === 204) {
                return true;
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }


        return false;
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
        $fileInfo = WpfdAddonHelper::getOneDriveBusinessFileInfos();
        $fileInfo[$file['catid']][$file['ID']]['latestVersionNumber'] = $latestVersionNumber;

        return WpfdAddonHelper::setOneDriveBusinessFileInfos($fileInfo);
    }

    /**
     * Create feature image for Woo product
     *
     * @param string $fileId       File ID
     * @param string $previewsPath Previews folder path on server
     *
     * @return boolean|string
     */
    public function getOneDriveBusinessImg($fileId, $previewsPath)
    {
        try {
            $fileId = WpfdAddonHelper::replaceIdOneDrive($fileId, false);
            $graph = new Graph();
            $graph->setAccessToken($this->access_token);
            $response = $graph->createRequest('GET', '/me/drive/items/'.$fileId.'/thumbnails')->execute();
            $status = $response->getStatus();

            if ($status !== 200) {
                return false;
            }

            $thumbnails = $response->getResponseAsObject(Model\ThumbnailSet::class);

            if (is_array($thumbnails) && count($thumbnails) >= 1) {
                $url = $thumbnails[0]->getLarge()->getUrl();
                if (!$url) {
                    return false;
                }

                $fileContent = file_get_contents($url);
                if ($fileContent) {
                    $client = $this->getClient();
                    $file = $client->getDriveItemById($fileId);
                    $fileName = md5($fileId);
                    $filePath = $previewsPath . 'onedrive_business_' . strval($fileName) . '.png';

                    $fs = fopen($filePath, 'wb');
                    $result = fwrite($fs, $fileContent);
                    fclose($fs);
                    if ($result) {
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
