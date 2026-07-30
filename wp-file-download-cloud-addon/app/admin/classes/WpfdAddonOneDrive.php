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

require_once WPFDA_PLUGIN_DIR_PATH  . 'lib/prod_vendor/autoload.php';

use Joomunited\WPFramework\v1_0_6\Utilities;
use GuzzleHttp\Client as GuzzleHttpClient;
use Krizalys\Onedrive\Client;
use Krizalys\Onedrive\Exception\ConflictException;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Krizalys\Onedrive\Onedrive;
use Krizalys\Onedrive\Proxy\FileProxy;
use Microsoft\Graph\Model\DriveItem;
use Microsoft\Graph\Model;
use Microsoft\Graph\Model\UploadSession;
use Krizalys\Onedrive\Constant\ConflictBehavior;
use Krizalys\Onedrive\Constant\AccessTokenStatus;

/**
 * Class WpfdAddonOneDrive
 */
class WpfdAddonOneDrive
{
    /**
     * Parameters
     *
     * @var $param
     */
    protected $params;

    /**
     * OneDrive Client
     *
     * @var OneDrive_Client
     */
    private $client = null;

    /**
     * Last error
     *
     * @var $lastError
     */
    protected $lastError;
    /**
     * Api file fields
     *
     * @var string
     */
    protected $apifilefields = 'thumbnails,children(top=1000;expand=thumbnails(select=medium,large,mediumSquare,c1500x1500))';
    /**
     * Api list files fields
     *
     * @var string
     */
    protected $apilistfilesfields = 'thumbnails(select=medium,large,mediumSquare,c1500x1500)';
    /**
     * Breadcrumb
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
     * Drive type
     *
     * @var string
     */
    protected $type = 'onedrive';

    /**
     * Max number of file/folder list
     *
     * @var integer
     */
    private $maxItemNumbers = 3000;

    /**
     * WpfdAddonOneDrive class construct
     */
    public function __construct()
    {
        $this->loadParams();

        /**
         * Filter to change the length of onedrive item list
         *
         * @param integer
         */
        $this->maxItemNumbers = apply_filters('wpfda_onedrive_max_items', 3000);
    }

    /**
     * Get all onedrive config
     *
     * @return array
     */
    public function getAllOneDriveConfigs()
    {
        return WpfdAddonHelper::getAllOneDriveConfigs();
    }

    /**
     * Get all onedrive config Old
     *
     * @return array
     */
    public function getAllOneDriveConfigsOld()
    {
        return WpfdAddonHelper::getAllOneDriveConfigsOld();
    }

    /**
     * Save onedrive config
     *
     * @param array $data Config data
     *
     * @return boolean false if value was not updated
     *                 true if value was updated.
     */
    public function saveOneDriveConfigs($data)
    {
        return WpfdAddonHelper::saveOneDriveConfigs($data);
    }

    /**
     * Get data config by onedrive
     *
     * @param string $name Config name
     *
     * @return array|null
     */
    public function getDataConfigByOneDrive($name)
    {
        return WpfdAddonHelper::getDataConfigByOneDrive($name);
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
     * Load param onedrive
     *
     * @return void
     */
    protected function loadParams()
    {
        $params       = $this->getDataConfigByOneDrive('onedrive');
        $this->params = new stdClass();

        $this->params->onedrive_client_id     = isset($params['onedriveKey']) ? $params['onedriveKey'] : '';
        $this->params->onedrive_client_secret = isset($params['onedriveSecret']) ? $params['onedriveSecret'] : '';
        $this->params->onedrive_credentials   = isset($params['onedriveCredentials']) ? $params['onedriveCredentials'] : '';
        $this->params->onedrive_state         = isset($params['onedriveState']) ? $params['onedriveState'] : array();
        $this->params->access_token = '';
        if (is_array($this->params->onedrive_state)) {
            $this->params->access_token = isset($this->params->onedrive_state['token']['data']['access_token']) ? $this->params->onedrive_state['token']['data']['access_token'] : '';
        } else {
            $this->params->access_token = isset($this->params->onedrive_state->token->data->access_token) ? $this->params->onedrive_state->token->data->access_token : '';
        }
    }

    /**
     * Read OneDrive app key and secret
     *
     * @return Krizalys\Onedrive\Client|OneDrive_Client|boolean
     */
    public function getClient()
    {
        $config = WpfdAddonHelper::getAllOneDriveConfigs();
        if (empty($config['onedriveKey']) && empty($config['onedriveSecret'])) {
            return false;
        }

        try {
            if (isset($config['onedriveState']) && !empty($this->params->access_token)) {
                $graph = new Graph();
                $graph->setAccessToken($this->params->access_token);
                $client = new Client(
                    $config['onedriveKey'],
                    $graph,
                    new GuzzleHttpClient(),
                    Onedrive::buildServiceDefinition(),
                    array(
                        'state' => json_decode(json_encode($config['onedriveState']))
                    )
                );

                if ($client->getAccessTokenStatus() === -2) {
                    $client = $this->renewAccessToken($client, $config);
                }
            } else {
                $client = new Client(
                    $config['onedriveKey'],
                    new Graph(),
                    new GuzzleHttpClient(),
                    Onedrive::buildServiceDefinition()
                );
            }

            $this->client = $client;
            return $this->client;
        } catch (Exception $ex) {
            $this->lastError = 'Onedrive Error: ' . $ex->getMessage();
            return false;
        }
    }
    /**
     * Renews the access token from OAuth. This token is valid for one hour.
     *
     * @param object $client Client
     * @param array  $config Setings
     *
     * @return Client
     */
    public function renewAccessToken($client, $config)
    {
        $client->renewAccessToken($config['onedriveSecret']);
        $config['onedriveState'] = $client->getState();
        WpfdAddonHelper::saveOneDriveConfigs($config);
        $graph = new Graph();
        $graph->setAccessToken($client->getState()->token->data->access_token);
        $client = new Client(
            $config['onedriveKey'],
            $graph,
            new GuzzleHttpClient(),
            Onedrive::buildServiceDefinition(),
            array(
                'state' => $client->getState()
            )
        );
        $config['onedriveState'] = $client->getState();
        WpfdAddonHelper::saveOneDriveConfigs($config);
        return $client;
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
            $onedriveconfig = $this->getAllOneDriveConfigs();

            $client = new Client(
                $onedriveconfig['onedriveKey'],
                new Graph(),
                new GuzzleHttpClient(),
                Onedrive::buildServiceDefinition(),
                array(
                    'state' => isset($onedriveconfig['onedriveState']) && !empty($onedriveconfig['onedriveState']) ? json_decode(json_encode($onedriveconfig['onedriveState'])) : array()
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
            $client->obtainAccessToken($onedriveconfig['onedriveSecret'], $code);
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
            $onedriveconfig['onedriveConnected'] = 1;
            $onedriveconfig['onedriveState'] = $client->getState();
            // update _wpmfAddon_onedrive_business_config option and redirect page
            WpfdAddonHelper::saveOnedriveConfigs($onedriveconfig);
            $this->redirect(admin_url('admin.php?page=wpfdAddon-onedrive&task=onedrive.authenticated'));
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
     * Get Authorisation Url
     *
     * @return string
     */
    public function getAuthorisationUrl()
    {
        try {
            $client = new Client(
                $this->params->onedrive_client_id,
                new Graph(),
                new GuzzleHttpClient(),
                Onedrive::buildServiceDefinition()
            );
            $authorizeUrl = $client->getLogInUrl(
                array(
                    'files.read',
                    'files.read.all',
                    'files.readwrite',
                    'files.readwrite.all',
                    'offline_access',
                ),
                get_admin_url() . 'admin.php',
                'wpfd-onedrive'
            );
            $this->params->onedrive_state = $client->getState();
            $this->saveParams();

            return $authorizeUrl;
        } catch (Exception $ex) {
            return new WP_Error('broke', __('Could not start authorization: ', 'wpfdAddon') . $ex->getMessage());
        }
    }
    /**
     * Save param onedrive
     *
     * @return void
     */
    protected function saveParams()
    {
        $params                        = $this->getAllOneDriveConfigs();
        $params['onedriveKey']         = $this->params->onedrive_client_id;
        $params['onedriveSecret']      = $this->params->onedrive_client_secret;
        $params['onedriveCredentials'] = $this->params->onedrive_credentials;
        $params['onedriveConnectedBy'] = get_current_user_id();
        $params['onedriveState']       = $this->params->onedrive_state;
        $this->saveOneDriveConfigs($params);
    }

    /**
     * Save param old onedrive
     *
     * @return void
     */
    protected function saveParamsOld()
    {
        $params                        = $this->getAllOneDriveConfigsOld();
        $params['onedriveKey']         = $this->params->onedrive_client_id;
        $params['onedriveSecret']      = $this->params->onedrive_client_secret;
        $params['onedriveCredentials'] = $this->params->onedrive_credentials;
        $this->saveOneDriveConfigs($params);
    }


    /**
     * Onedrive authenticate
     *
     * @return string
     */
    public function authenticate()
    {
        $code   = Utilities::getInput('code', 'GET', 'none');
        return $this->createToken($code);
    }

    /**
     * Log out
     *
     * @return boolean
     */
    public function logout()
    {
        $config = WpfdAddonHelper::getAllOneDriveConfigs();
        $config['onedriveConnected'] = '0';
        unset($config['onedriveState']);
        WpfdAddonHelper::saveOneDriveConfigs($config);
//        $this->redirect('https://login.microsoftonline.com/common/oauth2/v2.0/logout?post_logout_redirect_uri=' . urlencode(get_admin_url() . 'admin.php?page=wpfd-config'));
        return true;
    }

    /**
     * Store credentials
     *
     * @param string $credentials Credentials
     *
     * @return void
     */
    public function storeCredentials($credentials)
    {
        $this->params->onedrive_credentials = $credentials;
        $this->saveParams();
    }

    /**
     * Get credentials
     *
     * @return string
     */
    public function getCredentials()
    {
        return $this->params->onedrive_credentials;
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
     * Create new folder in onedrive
     *
     * @param strin       $title    Title
     * @param null|string $parentId Parrent id
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
            return new WP_Error('broke', __('Can not create Onedrive folder: ', 'wpfdAddon') . $ex->getMessage());
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
                    $file->catid            = WpfdAddonHelper::getTermIdOneDriveByOneDriveId($folder_id);
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

                    $fileInfos = WpfdAddonHelper::getOneDriveFileInfos();
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
                        $file->file_password   = isset($fileInfos[$file->catid][$idItem]['file_password']) ?
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
    public function getAllFileInFolder($folderId)
    {
        try {
            $folderId = WpfdAddonHelper::replaceIdOneDrive($folderId, false);
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
        if (!empty($this->params->access_token)) {
            $graph = new Graph();
            $graph->setAccessToken($this->params->access_token);
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
                    return $url;
                } else {
                    $fileContent = file_get_contents($url);

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
     * Upload file to onedrive
     *
     * @param string  $filename    File name
     * @param array   $file        File info
     * @param string  $fileContent File path
     * @param string  $id_folder   Upload category id
     * @param boolean $replace     Overwrite file name
     *
     * @throws Exception Fire if errors
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
        $client    = $this->getClient();
        $folder    = $client->getDriveItemById($id_folder);

        if (!file_exists($fileContent)) {
            $file['error'] = esc_html__('File not exists! Upload failed!', 'wpfdAddon');
            return array(
                'file' => $file
            );
        }

        $stream = fopen($fileContent, 'rb');
        try {
            $uploadFile   = $folder->upload($filename, $stream, array('conflictBehavior' => $conflictBehavior));
            $file['name'] = WpfdAddonHelper::stripExt($uploadFile->name);
            $file['id']   = WpfdAddonHelper::replaceIdOneDrive($uploadFile->id);

            return array(
                'file' => $file
            );
        } catch (ConflictException $e) { // File name already exists
            $file['error'] = esc_html__('Upload failed!', 'wpfdAddon') . ': ' . $e->getMessage();
        } catch (Exception $e) {
            $file['error'] = esc_html__('Upload failed! Unknown exception', 'wpfdAddon') . ': ' . $e->getMessage();
        }

        if (isset($file['error'])) {
            return array(
                'file' => $file
            );
        }

        $file['error'] = esc_html__('Upload failed!', 'wpfdAddon');
        return array(
            'file' => $file
        );
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
        $idFile = WpfdAddonHelper::replaceIdOneDrive($folderId, false);
        $client = $this->getClient();
        try {
            $file = $client->getDriveItemById($idFile);
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
     * Get onedrive file info
     *
     * @param string  $idFile     File id
     * @param integer $idCategory Category id
     * @param string  $token      Token key
     *
     * @return array|booleab
     */
    public function getOneDriveFileInfos($idFile, $idCategory, $token = '')
    {
        if (!class_exists('WpfdBase')) {
            include_once WPFD_PLUGIN_DIR_PATH . '/app/admin/classes/WpfdBase.php';
        }

        $idFile         = WpfdAddonHelper::replaceIdOneDrive($idFile, false);
        $client = $this->getClient();
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
                    $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $data['catid'] . '/';
                    $linkdownload .= $category->slug . '/' . $data['id'] . '/' . $data['title'];
                } else {
                    $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $data['catid'] . '/';
                    $linkdownload .= $category->slug . '/' . $data['id'] . '/' . $data['title'];
                }
                $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $data['ext'] : '');
                $data['linkdownload'] = $linkdownload;
            } else {
                $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=file.download';
                $linkdownload .= '&wpfd_category_id=' . $data['catid'] . '&wpfd_file_id=' . $data['id'];
                $data['linkdownload'] = $linkdownload;
            }
            $config = WpfdAddonHelper::getOneDriveFileInfos();
            if (!empty($config) && isset($config[$idCategory]) && isset($config[$idCategory][$data['id']])) {
                $data['description']             = isset($config[$idCategory][$data['id']]['description']) ?
                    $config[$idCategory][$data['id']]['description'] : '';
                $data['version']                 = isset($config[$idCategory][$data['id']]['version']) ?
                    $config[$idCategory][$data['id']]['version'] : '';
                $data['versionNumber']           = $data['version'];
                $data['latestVersionNumber']     = isset($config[$idCategory][$data['id']]['latestVersionNumber']) ?
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
     * Change title
     *
     * @param array $datas File info
     *
     * @return boolean
     */
    public function saveOnDriveFileInfos($datas)
    {
        $id     = WpfdAddonHelper::replaceIdOneDrive($datas['id'], false);
        $client = $this->getClient();
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
            $client = $this->getClient();
            $file    = $client->getDriveItemById($id);
            $file->rename($filename);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return true;
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
     * Delete file in onedrive
     *
     * @param string      $id       File id
     * @param null|string $cloud_id Cloud category id
     *
     * @return boolean
     */
    public function delete($id, $cloud_id = null)
    {
        $onedriveUpload = get_option('_wpfdAddon_onedrive_file_user_upload') ? get_option('_wpfdAddon_onedrive_file_user_upload') : array();
        if (!empty($onedriveUpload)) {
            if (array_key_exists($id, $onedriveUpload)) {
                unset($onedriveUpload[$id]);
                update_option('_wpfdAddon_onedrive_file_user_upload', $onedriveUpload);
            }
        }

        $id = WpfdAddonHelper::replaceIdOneDrive($id, false);
        if ($cloud_id !== null) {
            $cloud_id = WpfdAddonHelper::replaceIdOneDrive($cloud_id, false);
        }

        try {
            $client = $this->getClient();
            $file = $client->getDriveItemById($id);

            if ($cloud_id !== null) {
                $found  = false;
                if ($file->parentReference->id === $cloud_id) {
                    $found = true;
                }
                if (!$found) {
                    return false;
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
        $client = $this->getClient();
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
     * Get files in folder
     *
     * @param string  $folderId  Folder id
     * @param array   $datas     Data return
     * @param boolean $recursive Recursive
     *
     * @return void
     * @throws Exception Error
     */
    public function getFilesInFolder($folderId, &$datas, $recursive = true)
    {
        $folderId    = WpfdAddonHelper::replaceIdOneDrive($folderId, false);
        $client      = $this->getClient();
        $folder      = $client->getDriveItemById($folderId);
        $params      = $this->getDataConfigByOneDrive('onedrive');
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
                            if ($recursive) {
                                $this->getFilesInFolder($idItem, $datas);
                            }
                        }
                    }
                }
                // $pageToken = $children->getNextPageToken();
            } catch (Exception $e) {
                $datas     = false;
                $pageToken = null;
                throw new Exception('getFilesInFolder - Google_Http_REST error ' . $e->getCode());
            }
        } while ($pageToken);
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
        $client      = $this->getClient();

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
     * Get files on search
     *
     * @param array $filters Filters
     *
     * @return array
     */
//    public function getFiles($filters)
//    {
//        $listfiles = $this->getAllFilesInAppFolder($filters);
//        return $this->displayFileSearch($listfiles);
//    }

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
        $onedriveBase    = $this->getAllOneDriveConfigs();
        $onedriverBaseId = WpfdAddonHelper::replaceIdOneDrive($onedriveBase['onedriveBaseFolder']['id'], false);
        $listfiles       = array();
        if (!isset($filters['catid'])) {
            $id = $onedriverBaseId;
        } else {
            if ($filters['catid'] === 0) {
                $id = $onedriverBaseId;
            } else {
                $id = WpfdAddonHelper::replaceIdOneDrive($filters['catid'], false);
            }
        }
        if (isset($filters['catid'])) {
            $arrayResults = $this->getFolder($id, '', $filters);
            $fileFolder   = $arrayResults['contents'];
            $listFileName = array();
            if (isset($filters['q']) && strpos($filters['q'], '\'') === false) {
                $arraySearch = $this->getFolder($id, $filters['q']);
                $fileSearch  = $arraySearch['contents'];
                foreach ($fileSearch as $child) {
                    $is_dir = ($child->folder !== null) ? true : false;
                    if (!$is_dir) {
                        $listFileName[] = $child->name;
                        if ($this->checkTimeCreate($child, $filters)) {
                            $listfiles[] = $child;
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
                if (isset($filters['ftags']) && $filters['ftags'] !== '') {
                    $client = $this->getClient();
                    $onedriveFileInfos = get_option('_wpfdAddon_onedrive_fileInfo');
                    if (!empty($onedriveFileInfos)) {
                        $filterByTime = false;
                        if ((isset($filters['cfrom']) && !empty($filters['cfrom'])) || (isset($filters['cto']) && !empty($filters['cto']))) {
                            $filterByTime = true;
                        } elseif ((isset($filters['ufrom']) && !empty($filters['ufrom'])) || (isset($filters['uto']) && !empty($filters['uto']))) {
                            $filterByTime = true;
                        }

                        foreach ($onedriveFileInfos as $odTermId => $odFileInfos) {
                            foreach ($odFileInfos as $odFileId => $odFileInfo) {
                                if (isset($odFileInfo['file_tags']) && $odFileInfo['file_tags'] !== '') {
                                    $idFile = WpfdAddonHelper::replaceIdOneDrive($odFileId, false);
                                    try {
                                        $file = $client->getDriveItemById($idFile);
                                        if (!$filterByTime || $this->checkTimeCreate($file, $filters)) {
                                            $listfiles[] = $file;
                                        }
                                    } catch (Exception $ex) {
                                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log for debug
                                        error_log($ex->getMessage());
                                    }
                                }
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
        $fCreatedTime = date('Y-m-d', $f->createdDateTime->getTimestamp());
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
     * Check string in string
     *
     * @param string $str    String
     * @param string $substr String to search
     *
     * @return boolean
     */
    private function strExists($str, $substr)
    {
        if (($str !== null && $substr !== null && strpos(strtolower($str), strtolower($substr)) !== false)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get child category of category
     *
     * @param string $id Category id
     *
     * @return array
     */
    public function getChildrenOfCategoryId($id)
    {
        $categoryChildren = get_option('wpfd-category_children');
        $termId           = WpfdAddonHelper::getTermIdOneDriveByOneDriveId($id);
        $parents          = array($termId);
        if (!empty($categoryChildren)) {
            foreach ($categoryChildren as $categoryId => $child) {
                if (in_array($categoryId, $parents)) {
                    $parents = array_merge($child, $parents);
                }
            }
        }

        return $parents;
    }

    /**
     * Get all Onedrive files in data
     *
     * @param array $filters Filters
     *
     * @return array
     */
    public function getAllFileInDatabase($filters)
    {
        $onedriveFileInfos = get_option('_wpfdAddon_onedrive_fileInfo');
        $foundIds          = array();
        if (!empty($onedriveFileInfos)) {
            // Have parent category id
            if (isset($filters['catid'])) {
                $categoriesId = $this->getChildrenOfCategoryId($filters['catid']);

                // Have Keyword to search
                if (isset($filters['q'])) {
                    foreach ($onedriveFileInfos as $term_id => $fileInfos) {
                        if (in_array($term_id, $categoriesId)) {
                            if (!empty($fileInfos)) {
                                foreach ($fileInfos as $fileId => $file) {
                                    if ($this->strExists($file['title'], $filters['q'])) {
                                        $foundIds[] = $fileId;
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // Not have keyword
                    foreach ($onedriveFileInfos as $term_id => $fileInfos) {
                        if (in_array((int) $term_id, $categoriesId)) {
                            if (!empty($fileInfos)) {
                                foreach ($fileInfos as $fileId => $file) {
                                    $foundIds[] = $fileId;
                                }
                            }
                        }
                    }
                }
            } else { // Not Have parent
                // Have Keyword to search
                if (isset($filters['q'])) {
                    foreach ($onedriveFileInfos as $term_id => $fileInfos) {
                        if (!empty($fileInfos)) {
                            foreach ($fileInfos as $fileId => $file) {
                                if ($this->strExists($file['title'], $filters['q'])) {
                                    $foundIds[] = $fileId;
                                }
                            }
                        }
                    }
                } else {
                    // Not have keyword return all files
                    foreach ($onedriveFileInfos as $term_id => $fileInfos) {
                        if (!empty($fileInfos)) {
                            foreach ($fileInfos as $fileId => $file) {
                                $foundIds[] = $fileId;
                            }
                        }
                    }
                }
            }

            return $foundIds;
        }
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
                $termID                 = WpfdAddonHelper::getTermIdOneDriveByOneDriveId($idItem);
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

                $config = WpfdAddonHelper::getOneDriveFileInfos();

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
     * @param boolean    $folderid       Folder id
     * @param string     $searchfilename Search file name
     * @param null|array $filters        Filters
     *
     * @return array|boolean
     */
    public function getFolder($folderid, $searchfilename, $filters = null)
    {
        $folderid = WpfdAddonHelper::replaceIdOneDrive($folderid, false);

        try {
            $client = $this->getclient();
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
                $results        = $client->getDriveItemById($folderid);
                $parents        = $results->parentReference;
                $contents       = $this->getAllFilesInFolder($folderid, $client, $filters);
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
        if (!empty($this->params->access_token)) {
            $client = $this->getclient();
            $contents = $this->getAllFilesInFolder($folderId, $client, null);

            if (!is_array($contents) || empty($contents)) {
                return array();
            }

            foreach ($contents as $indexContent => $content) {
                if (gettype($content->name) !== 'undefined' && strpos((string) strtolower($content->name), strtolower($keyword)) === false) {
                    unset($contents[$indexContent]);
                }
            }

            return $contents;
        }

        return array();
    }

    /**
     * Recursive function to get all files within a folder and its child folders.
     *
     * @param string     $folderId The ID of the folder.
     * @param Client     $client   The Krizalys OneDrive client.
     * @param null|array $filters  Filters
     *
     * @return array The array of file items.
     */
    public function getAllFilesInFolder($folderId, $client, $filters)
    {
        $files = array();
        $results = $client->getDriveItemById($folderId);
        $parents = $results->parentReference;
        $children = $results->getChildren(
            array(
                'top' => $this->maxItemNumbers
            )
        );

        $filterByTime = false;
        if ((isset($filters['cfrom']) && !empty($filters['cfrom'])) || (isset($filters['cto']) && !empty($filters['cto']))) {
            $filterByTime = true;
        } elseif ((isset($filters['ufrom']) && !empty($filters['ufrom'])) || (isset($filters['uto']) && !empty($filters['uto']))) {
            $filterByTime = true;
        }
        foreach ($children as $child) {
            $is_dir      = ($child->folder !== null) ? true : false;
            if (!$is_dir) {
                if (!$filterByTime || $this->checkTimeCreate($child, $filters)) {
                    $files[] = $child;
                }
            } else {
                // It's a folder, recursively process it
                $childFolderId = $child->id;
                $childFolderFiles = $this->getAllFilesInFolder($childFolderId, $client, $filters);
                $files = array_merge($files, $childFolderFiles);
            }
        }

        return $files;
    }

    /**
     * Download file
     *
     * @param string $fileId File id
     *
     * @return boolean|stdClass|WP_Error
     */
    public function downloadFile($fileId)
    {
        $fileId = WpfdAddonHelper::replaceIdOneDrive($fileId, false);
        $client = $this->getClient();
        try {
            /* @var \Krizalys\Onedrive\Proxy\DriveItemProxy $item */
            $item = $client->getDriveItemById($fileId);

            $ret        = new stdClass();
            $ret->id    = $item->id;

            if (defined('WPFD_ONEDRIVE_DIRECT') && WPFD_ONEDRIVE_DIRECT) {
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
        if (!empty($this->params->access_token)) {
            try {
                $graph = new Graph();
                $graph->setAccessToken($this->params->access_token);
                $url = '/me/drive/items/' . $fileId . '/createLink';
                $response = $graph
                    ->createRequest('POST', $url)
                    ->attachBody(array('type' => 'view', 'scope' => 'anonymous'))
                    ->execute();

                $body = $response->getBody();
                if (isset($body['link']['webUrl'])) {
                    return $body['link']['webUrl'];
                }
                
                return false;
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
        $fileInfos = WpfdAddonHelper::getOneDriveFileInfos();
        if (!empty($fileInfos)) {
            $hits = isset($fileInfos[$catId][$idItem]['hits']) ? intval($fileInfos[$catId][$idItem]['hits']) : 0;
            $fileInfos[$catId][$idItem]['hits'] = $hits + 1;
        } else {
            $fileInfos = array();
            $fileInfos[$catId][$idItem]['hits'] = 1;
        }
        WpfdAddonHelper::setOneDriveFileInfos($fileInfos);
    }
    /**
     * Delete all local folder and OneDrive folder for testing purpose
     *
     * @return void
     *
     * @throws Exception Fire message if errors
     */
    public function deleteAllFolder()
    {
        $onedriveconfig = $this->getAllOneDriveConfigs();
        $base_folder = isset($onedriveconfig['onedriveBaseFolder']) ? $onedriveconfig['onedriveBaseFolder'] : false;

        if (is_array($base_folder) && isset($base_folder['id'])) {
            $folders = array();
            try {
                $this->getFilesInFolder($base_folder['id'], $folders, false);

                foreach ($folders as $folderId => $folder) {
                    $termId = WpfdAddonHelper::getTermIdOnedriveByOneDriveId($folderId);
                    wp_delete_term($termId, 'wpfd-category');
                    $this->delete($folderId);
                }
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
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
        $fileInfo = WpfdAddonHelper::getOneDriveFileInfos();
        $fileInfo[$file['catid']][$file['ID']]['latestVersionNumber'] = $latestVersionNumber;

        return WpfdAddonHelper::setOneDriveFileInfos($fileInfo);
    }

    /**
     * Create feature image for Woo product
     *
     * @param string $fileId       File ID
     * @param string $previewsPath Previews folder path on server
     *
     * @return boolean|string
     */
    public function getOneDriveImg($fileId, $previewsPath)
    {
        try {
            $fileId = WpfdAddonHelper::replaceIdOneDrive($fileId, false);
            $graph = new Graph();
            $graph->setAccessToken($this->params->access_token);
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
                    $filePath = $previewsPath . 'onedrive_' . strval($fileName) . '.png';

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
