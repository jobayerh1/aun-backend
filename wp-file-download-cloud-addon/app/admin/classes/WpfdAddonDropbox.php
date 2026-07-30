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
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

/**
 * Class WpfdAddonDropbox
 */
class WpfdAddonDropbox
{
    /**
     * Parameters
     *
     * @var array
     */
    protected $params;

    /**
     * Application name
     *
     * @var string
     */
    protected $appName = 'JoomUnited/1.0';
    /**
     * Last error
     *
     * @var string
     */
    protected $lastError;

    /**
     * Dropbox client
     *
     * @var Dropbox\Client
     */
    protected $client;

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
    protected $cloudName = 'dropbox';

    /**
     * Drive type
     *
     * @var string
     */
    protected $type = 'dropbox';

    /**
     * Debug
     *
     * @var boolean
     */
    private $debug = false;

    /**
     * WpfdAddonDropbox constructor.
     */
    public function __construct()
    {
        $app = Application::getInstance('Wpfd');
        $path_wpfdhelperfile = $app->getPath() . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelperfile .= DIRECTORY_SEPARATOR . 'WpfdHelperFile.php';
        if (file_exists($path_wpfdhelperfile)) {
            require_once $path_wpfdhelperfile;
        }
        set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
        require_once WPFDA_PLUGIN_DIR_PATH  . 'lib/prod_vendor/autoload.php';
        require_once 'Dropbox/autoload.php';
        $this->loadParams();
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
     * Get last error
     *
     * @return mixed
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Get data config by dropbox
     *
     * @param string $name Option name
     *
     * @return array|null
     */
    public function getDataConfigByDropbox($name)
    {
        return WpfdAddonHelper::getDataConfigByDropbox($name);
    }

    /**
     * Get all dropbox config
     *
     * @return array
     */
    public function getAllDropboxConfigs()
    {
        return WpfdAddonHelper::getAllDropboxConfigs();
    }

    /**
     * Save database
     *
     * @param array $data Config data
     *
     * @return boolean
     */
    public function saveDropboxConfigs($data)
    {
        return WpfdAddonHelper::saveDropboxConfigs($data);
    }

    /**
     * Load parameters
     *
     * @return void
     */
    protected function loadParams()
    {
        $params       = $this->getDataConfigByDropbox('dropbox');
        $this->params = new stdClass();
        $this->params->dropboxKey          = isset($params['dropboxKey']) ? $params['dropboxKey'] : '';
        $this->params->dropboxSecret       = isset($params['dropboxSecret']) ? $params['dropboxSecret'] : '';
        $this->params->dropboxBaseFolderId = isset($params['dropboxBaseFolderId']) ? $params['dropboxBaseFolderId'] : '';
        $this->params->dropboxAccessToken  = isset($params['dropboxAccessToken']) ? $params['dropboxAccessToken'] : '';
    }

    /**
     * Save parameters
     *
     * @return void
     */
    protected function saveParams()
    {
        $params                       = $this->getAllDropboxConfigs();
        $params['dropboxKey']         = $this->params->dropboxKey;
        $params['dropboxSecret']      = $this->params->dropboxSecret;
        $params['dropboxConnectedBy'] = get_current_user_id();
        $params['dropboxAccessToken'] = $this->params->dropboxAccessToken;
        $this->saveDropboxConfigs($params);
    }

    /**
     * Get Redirect URL
     *
     * @return string|void
     */
    public function getRedirectUrl()
    {
        return admin_url('admin.php?page=wpfdAddon-dropbox&task=dropbox.authenticate');
    }

    /**
     * Generate Dropbox Authorization Provider
     *
     * @return \League\OAuth2\Client\Provider\GenericProvider
     */
    public function getAuthorizationProvider()
    {
        $dropboxKey = '';
        $dropboxSecret = 'dropboxSecret';

        if (!empty($this->params->dropboxKey)) {
            $dropboxKey = $this->params->dropboxKey;
        }
        if (!empty($this->params->dropboxSecret)) {
            $dropboxSecret = $this->params->dropboxSecret;
        }
        $provider = new League\OAuth2\Client\Provider\GenericProvider(array(
            'clientId'                => $dropboxKey,    // The client ID assigned to you by the provider
            'clientSecret'            => $dropboxSecret,    // The client password assigned to you by the provider
            'redirectUri'             => $this->getRedirectUrl(),
            'urlAuthorize'            => 'https://www.dropbox.com/oauth2/authorize',
            'urlAccessToken'          => 'https://api.dropboxapi.com/oauth2/token',
            'urlResourceOwnerDetails' => 'https://api.dropboxapi.com/2/check/user'
        ));

        return $provider;
    }

    /**
     * Generate authenticate URL for Dropbox
     *
     * @return string
     */
    public function getAuth2Url()
    {
        $provider = $this->getAuthorizationProvider();
        // Fetch the authorization URL from the provider; this returns the
        // urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $provider->getAuthorizationUrl(array(
            'token_access_type' => 'offline',
        ));

        // Get the state generated for you and store it to the session.
        update_option('wpfd_addon_dropbox_auth_state', $provider->getState());

        return $authorizationUrl;
    }

    /**
     * Authorization dropbox request
     *
     * @return boolean
     */
    public function authorization()
    {
        $provider = $this->getAuthorizationProvider();
        $authState = get_option('wpfd_addon_dropbox_auth_state', false);

        if (!$authState) {
            // No valid authstate
            return false;
        }

        // Get code
        if (!isset($_GET['code'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- It's OK
            // Authorization failed
            return false;
        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($_GET['state']) || ($authState && $_GET['state'] !== $authState)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- It's OK
            if ($authState) {
                delete_option('wpfd_addon_dropbox_auth_state');
            }

            $this->lastError = 'Invalid state';

            return false;
        } else {
            try {
                // Try to get an access token using the authorization code grant.
                $accessToken = $provider->getAccessToken('authorization_code', array(
                    'code' => $_GET['code'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- It's OK
                ));

                // Save the access token
                $this->params->dropboxAccessToken = json_encode($accessToken->jsonSerialize());
                $this->saveParams();

                return true;
            } catch (IdentityProviderException $e) {
                // Failed to get the access token or user details.
                $this->lastError = $e->getMessage();

                return false;
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();

                return false;
            }
        }
    }
    /**
     * Get web authenticate
     *
     * @return \Dropbox\WebAuthNoRedirect
     */
    public function getWebAuth()
    {
        $dropboxKey = '';
        $dropboxSecret = 'dropboxSecret';

        if (!empty($this->params->dropboxKey)) {
            $dropboxKey = $this->params->dropboxKey;
        }
        if (!empty($this->params->dropboxSecret)) {
            $dropboxSecret = $this->params->dropboxSecret;
        }

        $appInfo = new Dropbox\AppInfo($dropboxKey, $dropboxSecret);
        $webAuth = new Dropbox\WebAuthNoRedirect($appInfo, $this->appName);

        return $webAuth;
    }

    /**
     * Get authorize Url allow user
     *
     * @return string
     */
    public function getAuthorizeDropboxUrl()
    {
        return $this->getAuth2Url();
    }

    /**
     * Check Dropbox Token
     *
     * @return boolean
     */
    public function checkAuth()
    {
        $dropboxToken = $this->params->dropboxAccessToken;
        if (!empty($dropboxToken)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Log out dropbox
     *
     * @return void
     */
    public function logout()
    {
        $params                  = $this->getAllDropboxConfigs();
        $params['dropboxKey']    = $this->params->dropboxKey;
        $params['dropboxSecret'] = $this->params->dropboxSecret;
        unset($params['dropboxAccessToken']);
        // todo: Revoke the token from dropbox
        $this->saveDropboxConfigs($params);
    }

    /**
     * Get Dropbox account
     *
     * @return \Dropbox\Client
     */
    public function getAccount()
    {
        $accessToken = $this->checkAndRefreshToken();

        if (false === $accessToken) {
            throw new Exception('CheckAndRefreshToken error');
        }

        if (!$this->client) {
            $this->client = new Dropbox\Client($accessToken->getToken(), $this->appName);
        }

        return $this->client;
    }

    /**
     * Check and refresh accessToken
     *
     * @return boolean|\League\OAuth2\Client\Token\AccessToken
     */
    public function checkAndRefreshToken()
    {
        try {
            $storedAccessToken = json_decode($this->params->dropboxAccessToken, true);
            if (is_null($storedAccessToken)) {
                throw new \Exception('Store Access Token not vaild');
            }
            $existingAccessToken = new League\OAuth2\Client\Token\AccessToken($storedAccessToken);

            if ($existingAccessToken->hasExpired()) {
                $newAccessToken = $this->refreshDropboxToken($existingAccessToken->getRefreshToken());
                $storedAccessToken['access_token'] = $newAccessToken->getToken();
                $storedAccessToken['expires'] = $newAccessToken->getExpires();
                $renewedAccessToken = new League\OAuth2\Client\Token\AccessToken($storedAccessToken);
                $this->params->dropboxAccessToken = json_encode($renewedAccessToken->jsonSerialize());
                $this->saveParams();

                return $renewedAccessToken;
            }

            return $existingAccessToken;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Refresh the Dropbox token
     *
     * @param string $refreshToken Refresh token
     *
     * @return \League\OAuth2\Client\Token\AccessToken Access token object
     *
     * @throws Exception Throw exception on error
     */
    public function refreshDropboxToken($refreshToken)
    {
        $curl = curl_init();
        $basicAuthString = base64_encode($this->params->dropboxKey . ':' . $this->params->dropboxSecret);
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.dropbox.com/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'grant_type=refresh_token&refresh_token=' . $refreshToken,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . $basicAuthString,
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);
        $info = curl_getinfo($curl);
        if (curl_errno($curl) || intval($info['http_code']) !== 200) {
            /*
             * https://www.dropbox.com/developers/documentation/http/documentation#error-handling
             *
             * 400  Bad input parameter. The response body is a plaintext message with more information.
             * 401  Bad or expired token. This can happen if the access token is expired or if the access token has been revoked by Dropbox or the user. To fix this, you should re-authenticate the user.
             *      The Content-Type of the response is JSON of typeAuthError
             * 403  The user or team account doesn't have access to the endpoint or feature.
             *      The Content-Type of the response is JSON of typeAccessError
             * 409  Endpoint-specific error. Look to the JSON response body for the specifics of the error.
             * 429  Your app is making too many requests for the given user or team and is being rate limited. Your app should wait for the number of seconds specified in the "Retry-After" response header before trying again.
             *      The Content-Type of the response can be JSON or plaintext. If it is JSON, it will be typeRateLimitErrorYou can find more information in the data ingress guide.
             * 5xx  An error occurred on the Dropbox servers. Check status.dropbox.com for announcements about Dropbox service issues.
            */
            throw new Exception('Failed to refresh the Access Token! Error code: ' . $info['http_code']);
        }
        curl_close($curl);

        $accessTokenArray = $this->parseJson($response);

        return new League\OAuth2\Client\Token\AccessToken($accessTokenArray);
    }

    /**
     * Attempts to parse a JSON response.
     *
     * @param string $content JSON content from response body
     *
     * @return array Parsed JSON data
     *
     * @throws UnexpectedValueException If the content could not be parsed
     */
    protected function parseJson($content)
    {
        $content = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new UnexpectedValueException(sprintf(
                'Failed to parse JSON response: %s',
                json_last_error_msg() // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.json_last_error_msgFound -- Minimum php version is 5.6
            ));
        }

        return $content;
    }
    /**
     * Create Folder to Dropbox
     *
     * @param string  $title  Title
     * @param integer $parent Category parent id
     *
     * @return array|boolean|null
     */
    public function createDropFolder($title, $parent)
    {
        try {
            $dropbox = $this->getAccount();
            $pathFolder = '';
            if ($parent === 0) {
                $baseFolderId = $this->params->dropboxBaseFolderId;

                if (!empty($baseFolderId)) {
                    $baseFolderMeta = $dropbox->getMetadata($baseFolderId);
                    $pathFolder = $baseFolderMeta['path_lower'];
                }
            } else {
                $baseFolderId = WpfdAddonHelper::getDropboxIdByTermId($parent);
                $pathFolder   = WpfdAddonHelper::getPathByDropboxId($baseFolderId);
            }

            $path   = $pathFolder . '/' . $title;
            $result = $dropbox->createFolder($path);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $result;
    }

    /**
     * Delete Folder to dropbox
     *
     * @param string $id Folder id
     *
     * @return boolean
     */
    public function deleteDropbox($id)
    {
        try {
            $dropbox = $this->getAccount();
            $dropbox->delete($id);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Upload file to Folder Dropbox
     *
     * @param string  $filename  File name
     * @param string  $fileTemp  Temp file path
     * @param integer $size      File size
     * @param string  $id_folder Folder id
     *
     * @return boolean|mixed
     */
    public function uploadFile($filename, $fileTemp, $size, $id_folder)
    {
        $f    = fopen($fileTemp, 'rb');
        $path = $id_folder . '/' . $filename;
        try {
            $dropbox = $this->getAccount();
            $result  = $dropbox->uploadFile($path, 'add', $f, $size);

            if ($this->cache) {
                // Clean cache
                WpfdCloudCache::deleteTransient($id_folder, $this->cloudName);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $result;
    }

    /**
     * Rename Folder Dropbox
     *
     * @param string $id       File id
     * @param string $filename File name
     *
     * @return boolean
     */
    public function changeDropboxFilename($id, $filename)
    {
        $tempPath = $filename.'_'. time();
        if (strtolower($id) === strtolower($filename)) {
            try {
                $dropbox = $this->getAccount();

                if ($this->cache) {
                    // Delete dropbox file cache
                    WpfdCloudCache::deleteTransient($id, $this->cloudName);
                }

                $dropbox->move($id, $tempPath);
                $id = $tempPath;
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
        }
        try {
            $dropbox = $this->getAccount();

            if ($this->cache) {
                // Delete dropbox file cache
                WpfdCloudCache::deleteTransient($id, $this->cloudName);
            }

            return $dropbox->move($id, $filename);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Get all files in dropbox
     *
     * @param string $idFolder Folder id
     *
     * @return mixed
     */
    public function getAllFiles($idFolder)
    {
        try {
            $dropbox = $this->getAccount();
            $fs = $dropbox->getMetadataWithChildren($idFolder);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return array();
        }

        return $fs['entries'];
    }

    /**
     * Download thumbnail for file
     *
     * @param string $idFile File id
     * @param string $path   Save path
     *
     * @return boolean
     */
    public function downloadThumbnail($idFile, $path)
    {
        try {
            $dropbox = $this->getAccount();
            $fd = fopen($path, 'wb');
            $dropbox->getThumbnailV2($idFile, $fd, 'png', 'w1024h768');
            if (file_exists($path)) {
                return $path;
            }
            return false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            fclose($fd);
            unlink($path);
            return false;
        }
        return false;
    }
    /**
     * Sub val Sort
     *
     * @param array  $a         Array to sort
     * @param string $subkey    Key to sort
     * @param string $direction Sort direction
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
        if ($direction !== null) {
            if (strtolower($direction) === 'asc') {
                asort($b);
            } else {
                arsort($b);
            }
        }
        $c = array();
        foreach ($b as $key => $val) {
            $c[] = $a[$key];
        }

        return $c;
    }

    /**
     * List dropbox files
     *
     * @param string  $folder_id     Folder id
     * @param integer $idCategory    Category id
     * @param string  $ordering      Ordering
     * @param string  $direction     Ordering direction
     * @param boolean $list_id_files List id files
     *
     * @return array|boolean
     */
    public function listDropboxFiles(
        $folder_id,
        $idCategory,
        $ordering = 'ordering',
        $direction = 'asc',
        $list_id_files = false
    ) {
        try {
            $dropbox = $this->getAccount();

            if ($this->cache) {
                // Cache query
                $fs = WpfdCloudCache::getTransient($folder_id, $this->cloudName);
                if ($fs === false) {
                    $fs = $dropbox->getMetadataWithChildren($folder_id);
                    // Cache it
                    WpfdCloudCache::setTransient($folder_id, $fs, $this->cloudName);
                }
            } else {
                $fs = $dropbox->getMetadataWithChildren($folder_id);
            }

            if (empty($fs)) {
                return false;
            }
            $files = array();
            //to fix special characters in file name like german "Umlaute"
            setlocale(LC_ALL, 'en_US.UTF-8');
            foreach ($fs['entries'] as $f) {
                if (is_array($list_id_files) && !in_array($f['id'], $list_id_files)) {
                    continue;
                }
                if ($f['.tag'] === 'file') {
                    $info  = pathinfo($f['path_display']);
                    $fsize = $f['size'];

                    //link download && linkview
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
                    if (empty($config) || !isset($config['rmdownloadext'])) {
                        $rmdownloadext = false;
                    } else {
                        $rmdownloadext = ((int) $config['rmdownloadext'] === 1) ? true : false;
                    }
                    $perlink       = get_option('permalink_structure');
                    $rewrite_rules = get_option('rewrite_rules');
                    $idFolderTitle = substr($folder_id, 1);

                    if (!empty($rewrite_rules)) {
                        $downloadFileTitle = str_replace('#', '-', $info['filename']);
                        if (strrpos($idFolderTitle, '/') !== false) {
                            $idFolderTitle = substr($idFolderTitle, strrpos($idFolderTitle, '/') + 1);
                        }
                        if (strpos($perlink, 'index.php')) {
                            $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $idCategory . '/';
                            $linkdownload .= $idFolderTitle . '/' . $f['id'] . '/' . $downloadFileTitle;
                        } else {
                            $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $idCategory . '/';
                            $linkdownload .= $idFolderTitle . '/' . $f['id'] . '/' . $downloadFileTitle;
                        }
                        $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $info['extension'] : '');
                    } else {
                        $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task';
                        $linkdownload .= '=file.download&wpfd_category_id=' . $idCategory . '&wpfd_file_id=' . $f['id'];
                    }

                    if (defined('WPFD_DROPBOX_DIRECT') && WPFD_DROPBOX_DIRECT) {
                        // Generate temporary download, this link live for 4 hour
                        $linkdownload = $dropbox->createTemporaryDirectLink($f['path_lower']);
                    }
                    $file             = new stdClass();
                    $file->id         = $f['id'];
                    $file->ID         = $file->id;
                    $file->title      = $info['filename'];
                    $file->post_title = $file->title;
                    $file->post_name  = $file->title;
                    if (!empty($info['extension'])) {
                        $file->ext = strtolower($info['extension']);
                    } else {
                        $file->ext = '';
                    }
                    $file->size = $fsize;
                    if (!empty($f['client_modified'])) {
                        $file->created_time = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($f['client_modified'])));
                        $file->created      = mysql2date($dateFormat, $file->created_time);
                    } else {
                        $file->created_time = '';
                        $file->created = '';
                    }

                    $file->modified_time    = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($f['server_modified'])));
                    $file->modified         = mysql2date($dateFormat, $file->modified_time);
                    $file->ordering         = 0;
                    $file->file_tags        = '';
                    $file->linkdownload     = $linkdownload;
                    $file->hits             = 0;
                    $file->version          = '';
                    $file->versionNumber    = '';
                    $file->description      = '';
                    $file->file_tags        = '';
                    $file->file_custom_icon = '';
                    $config                 = get_option('_wpfdAddon_dropbox_fileInfo');
                    if (!empty($config) && isset($config[$idCategory]) && isset($config[$idCategory][$f['id']])) {
                        $file->description   = isset($config[$idCategory][$f['id']]['description']) ?
                            $config[$idCategory][$f['id']]['description'] : '';
                        $file->version       = isset($config[$idCategory][$f['id']]['version']) ?
                            $config[$idCategory][$f['id']]['version'] : '';
                        $file->versionNumber = $file->version;
                        $file->hits          = isset($config[$idCategory][$f['id']]['hits']) ?
                            $config[$idCategory][$f['id']]['hits'] : 0;
                        $file->file_tags     = isset($config[$idCategory][$f['id']]['file_tags']) ?
                            $config[$idCategory][$f['id']]['file_tags'] : '';
                        $file->state = isset($config[$idCategory][$f['id']]['state']) ?
                            $config[$idCategory][$f['id']]['state'] : '1';
                        $file->canview = isset($config[$idCategory][$f['id']]['canview']) ?
                            $config[$idCategory][$f['id']]['canview'] : '';
                        $file->file_password = isset($config[$idCategory][$f['id']]['file_password']) ?
                            $config[$idCategory][$f['id']]['file_password'] : '';
                        $file->file_custom_icon = isset($config[$idCategory][$f['id']]['file_custom_icon']) ?
                            $config[$idCategory][$f['id']]['file_custom_icon'] : '';
                    }

                    $file->catid = $idCategory;
                    $files[]     = apply_filters('wpfda_file_info', $file, $this->type);
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
     * Create temporary directory link
     *
     * @param string $lowerPath Lower path
     *
     * @return false|string|null
     */
    public function createTemporaryDirectLink($lowerPath)
    {
        try {
            $dropbox = $this->getAccount();
            return $dropbox->createTemporaryDirectLink($lowerPath);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }
    /**
     * Delete a file Dropbox
     *
     * @param string $id_file File id
     *
     * @return   boolean
     * @internal param $id
     * @internal param null $cloud_id
     */
    public function deleteFileDropbox($id_file)
    {
        try {
            $dropbox = $this->getAccount();
            $fs      = $dropbox->getMetadata($id_file);
            $dropbox->delete($fs['path_lower']);
            $dropboxUpload = get_option('_wpfdAddon_dropbox_file_user_upload') ? get_option('_wpfdAddon_dropbox_file_user_upload') : array();
            if (!empty($dropboxUpload)) {
                if (array_key_exists($id_file, $dropboxUpload)) {
                    unset($dropboxUpload[$id_file]);
                    update_option('_wpfdAddon_dropbox_file_user_upload', $dropboxUpload);
                }
            }

            if ($this->cache) {
                // Delete dropbox file cache
                if (strpos($fs['name'], $fs['path_lower']) !== false) {
                    $folderId = str_replace('/' . $fs['name'], '', $fs['path_lower']);
                } else {
                    $fpath = pathinfo($fs['path_display']);
                    $termID = WpfdAddonHelper::getTermIdByDropboxPath($fpath['dirname']);
                    $folderId = WpfdAddonHelper::getDropboxPathByTermId($termID);
                }
                WpfdCloudCache::deleteTransient($folderId, $this->cloudName);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * Copy file dropbox
     *
     * @param string $path_from File path
     * @param string $path_to   Destination file path
     *
     * @return mixed|string
     */
    public function copyFileDropbox($path_from, $path_to)
    {
        try {
            $dropbox = $this->getAccount();
            return $dropbox->copy($path_from, $path_to);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Rename Folder Dropbox
     *
     * @param string $from From path
     * @param string $to   Destination path
     *
     * @return boolean|mixed
     */
    public function moveFile($from, $to)
    {
        try {
            $dropbox = $this->getAccount();

            return $dropbox->move($from, $to);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Get a file in dropbox
     *
     * @param string $idFile File id
     *
     * @return   array|boolean
     * @internal param $id
     * @internal param null $cloud_id
     */
    public function getDropboxFile($idFile)
    {
        try {
            $dropbox = $this->getAccount();
            $v       = $dropbox->getFileMetadata($idFile);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $v;
    }

    /**
     * Get dropbox file info
     *
     * @param string  $idFile     File id
     * @param string  $idFolder   Folder name
     * @param integer $idCategory Term id
     * @param string  $token      Token key
     *
     * @return   array|boolean
     * @internal param $id
     * @internal param null $cloud_id
     */
    public function getDropboxFileInfos($idFile, $idFolder, $idCategory, $token = '')
    {
        if (!class_exists('WpfdBase')) {
            include_once WPFD_PLUGIN_DIR_PATH . '/app/admin/classes/WpfdBase.php';
        }

        try {
            $dropbox = $this->getAccount();

            if ($this->cache) {
                // Cache query
                $v = WpfdCloudCache::getTransient($idFile, $this->cloudName);
                if ($v === false) {
                    $v = $dropbox->getFileMetadata($idFile);
                    // Cache it
                    WpfdCloudCache::setTransient($idFile, $v, $this->cloudName);
                }
            } else {
                $v = $dropbox->getFileMetadata($idFile);
            }

            //to fix special characters in file name like german "Umlaute"
            setlocale(LC_ALL, 'en_US.UTF-8');
            $info  = pathinfo($v['path_display']);
            $fsize = $v['size'];

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
            $params           = $this->getGlobalConfig();
            $viewer_type      = WpfdBase::loadValue($params, 'use_google_viewer', 'lightbox');
            $extension_list   = 'png,jpg,pdf,ppt,pptx,doc,docx,xls,xlsx,dxf,ps,eps,xps,psd,tif,tiff,bmp,svg,pages,ai,dxf,ttf,txt,mp3,mp4';
            $extension_viewer = explode(',', WpfdBase::loadValue($params, 'extension_viewer', $extension_list));
            $extension_viewer = array_map('trim', $extension_viewer);
            $perlink          = get_option('permalink_structure');
            $rewrite_rules    = get_option('rewrite_rules');
            $idFolderTitle    = substr($idFolder, 1);
            if (empty($config) || !isset($config['rmdownloadext'])) {
                $rmdownloadext = false;
            } else {
                $rmdownloadext = ((int) $config['rmdownloadext'] === 1) ? true : false;
            }
            if (!empty($rewrite_rules)) {
                $downloadFileTitle = str_replace('#', '-', $info['filename']);
                if (strrpos($idFolderTitle, '/') !== false) {
                    $idFolderTitle = substr($idFolderTitle, strrpos($idFolderTitle, '/') + 1);
                }
                if (strpos($perlink, 'index.php')) {
                    $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $idCategory . '/';
                    $linkdownload .= $idFolderTitle . '/' . $idFile . '/' . $downloadFileTitle;
                } else {
                    $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $idCategory . '/' . $idFolderTitle;
                    $linkdownload .= '/' . $idFile . '/' . $downloadFileTitle;
                }
                $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $info['extension'] : '');
            } else {
                $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=file.download';
                $linkdownload .= '&wpfd_category_id=' . $idCategory . '&wpfd_file_id=' . $idFile;
            }
            if (defined('WPFD_DROPBOX_DIRECT') && WPFD_DROPBOX_DIRECT) {
                // Generate temporary download, this link live for 4 hour
                $t = $dropbox->createTemporaryDirectLink($v['path_lower']);
                $linkdownload = $t;
            }
            $data                  = array();
            $data['linkdownload']  = $linkdownload;
            $data['ID']            = $v['id'];
            $data['id']            = $data['ID'];
            $data['state']         = isset($v['state']) ? (string) $v['state'] : '1';
            $data['catid']         = $idCategory;
            $data['title']         = $info['filename'];
            $data['post_title']    = $data['title'];
            $data['file']          = $info['basename'];
            $data['ext']           = strtolower($info['extension']);
            $data['created_time']  = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($v['client_modified'])));
            $data['modified_time'] = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($v['server_modified'])));
            $data['created']       = mysql2date($dateFormat, $data['created_time']);
            $data['modified']      = mysql2date($dateFormat, $data['modified_time']);
            $data['file_tags']     = '';
            $data['size']          = $fsize;
            $data['ordering']      = 1;
            $remote_url            = (isset($file_meta) && isset($file_meta['remote_url'])) ? $file_meta['remote_url'] : false;
            if ($viewer_type !== 'no' && in_array($info['extension'], $extension_viewer) && ($remote_url === false)) {
                $data['viewer_type'] = $viewer_type;
                $data['viewerlink']  = WpfdHelperFile::isMediaFile($info['extension']) ?
                    WpfdHelperFile::getMediaViewerUrl(
                        $v['id'],
                        $idCategory,
                        $info['extension'],
                        $token
                    ) : WpfdHelperFile::getViewerUrl($v['id'], $idCategory, $token);
            }
            $config = get_option('_wpfdAddon_dropbox_fileInfo');
            if (!empty($config) && isset($config[$idCategory]) && isset($config[$idCategory][$v['id']])) {
                $data['description']             = isset($config[$idCategory][$v['id']]['description']) ?
                    $config[$idCategory][$v['id']]['description'] : '';
                $data['version']                 = isset($config[$idCategory][$v['id']]['version']) ?
                    $config[$idCategory][$v['id']]['version'] : '';
                $data['versionNumber']           = $data['version'];
                $data['latestVersionNumber'] = isset($config[$idCategory][$v['id']]['latestVersionNumber']) ?
                    $config[$idCategory][$v['id']]['latestVersionNumber'] : false;
                $data['hits']                    = isset($config[$idCategory][$v['id']]['hits']) ?
                    $config[$idCategory][$v['id']]['hits'] : 0;
                $data['social']                  = isset($config[$idCategory][$v['id']]['social']) ?
                    $config[$idCategory][$v['id']]['social'] : 0;
                $data['file_tags']               = isset($config[$idCategory][$v['id']]['file_tags']) ?
                    $config[$idCategory][$v['id']]['file_tags'] : '';
                $data['file_multi_category']     = isset($config[$idCategory][$v['id']]['file_multi_category']) ?
                    $config[$idCategory][$v['id']]['file_multi_category'] : '';
                $data['file_multi_category_old'] = isset($config[$idCategory][$v['id']]['file_multi_category_old']) ?
                    $config[$idCategory][$v['id']]['file_multi_category_old'] : '';
                $data['file_custom_icon'] = isset($config[$idCategory][$v['id']]['file_custom_icon']) ?
                    $config[$idCategory][$v['id']]['file_custom_icon'] : '';
                if (isset($config[$idCategory][$v['id']]['canview'])) {
                    $data['canview'] = $config[$idCategory][$v['id']]['canview'];
                }
                if (isset($config[$idCategory][$v['id']]['file_password'])) {
                    $data['file_password'] = $config[$idCategory][$v['id']]['file_password'];
                }
            }
            if (isset($config[$idCategory][$v['id']]['state'])) {
                $data['state']         = (string) $config[$idCategory][$v['id']]['state'] ;
            }
            $data = apply_filters('wpfda_file_info', $data, $this->type);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $data;
    }

    /**
     * Change file title
     *
     * @param array $datas File info
     *
     * @return boolean
     */
    public function saveDropboxFileInfos($datas)
    {
        try {
            $dropbox = $this->getAccount();
            $getFile = $dropbox->getMetadata($datas['id']);
            $fpath   = pathinfo($getFile['path_lower']);
            $newpath = $fpath['dirname'] . '/' . $datas['title'] . '.' . $fpath['extension'];
            $dropbox->move($getFile['path_lower'], $newpath);

            if ($this->cache) {
                // Delete dropbox file cache
                WpfdCloudCache::deleteTransient($datas['id'], $this->cloudName);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * Change version
     *
     * @param array $datas Version data
     *
     * @return boolean|mixed
     */
    public function saveDropboxVersion($datas)
    {
        try {
            $dropbox = $this->getAccount();
            $getFile = $dropbox->getMetadata($datas['old_file']);
            $f       = fopen($datas['new_tmp_name'], 'rb');
            $result  = $dropbox->updateFile(
                $getFile['path_lower'],
                $getFile['rev'],
                'update',
                $f,
                $datas['new_file_size']
            );
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $result;
    }

    /**
     * Get version info
     *
     * @param string  $idFile     File id
     * @param integer $idCategory Term id
     *
     * @return   array|boolean
     * @internal param $dropboxId
     */
    public function displayDropboxVersionInfo($idFile, $idCategory)
    {
        try {
            $dropbox  = $this->getAccount();
            $getFile  = $dropbox->getMetadata($idFile);
            $result   = $dropbox->listRevisions($idFile, 10);
            $versions = array();
            foreach ($result['entries'] as $v) {
                if ($getFile['rev'] !== $v['rev']) {
                    $fpath                   = pathinfo($v['path_lower']);
                    $version['ext']          = $fpath['extension'];
                    $version['size']         = $v['size'];
                    $version['catid']        = $idCategory;
                    $version['id']           = $v['id'];
                    $version['created_time'] = $v['client_modified'];
                    $version['meta_id']      = $v['rev'];
                    $versions[]              = $version;
                }
            }

            return $versions;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Restore version
     *
     * @param string $id_file File id
     * @param string $vid     Version id
     *
     * @return boolean
     */
    public function restoreVersion($id_file, $vid)
    {
        try {
            $dropbox = $this->getAccount();
            $getFile = $dropbox->getMetadata($id_file);
            $dropbox->restoreFile($getFile['path_lower'], $vid);

            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Download version
     *
     * @param string $id_file File id
     * @param string $vid     Version id
     *
     * @return boolean
     */
    public function downloadVersion($id_file, $vid)
    {
        try {
            $dropbox  = $this->getAccount();
            $getFile  = $dropbox->getMetadata($id_file);
            $pinfo    = pathinfo($getFile['path_lower']);
            $tempfile = $pinfo['basename'];
            $fd       = fopen($tempfile, 'wb');
            $dropbox->getFile($getFile['path_lower'], $fd, $vid);
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($tempfile) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($tempfile));
            readfile($tempfile);
            unlink($tempfile);
            exit;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Change dropbox order
     *
     * @param string $move     Move path
     * @param string $location New location path
     * @param string $parent   Parent
     *
     * @return boolean|mixed
     */
    public function changeDropboxOrder($move, $location, $parent)
    {
        try {
            $dropbox     = $this->getAccount();
            if ($parent !== 0) {
                $fpath       = pathinfo($move);
                $baseMove    = '/' . $fpath['basename'];
                $newlocation = $location . $baseMove;
                $result      = $dropbox->move($move, $newlocation);
            } else {
                $pinfo    = pathinfo($move);
                $basemove = '/' . $pinfo['basename'];
                $result   = $dropbox->move($move, $basemove);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $result;
    }

    /**
     * Get list children folder
     *
     * @param string $baseFolderId Folder id
     *
     * @return array|boolean
     */
    public function listChildrenFolders($baseFolderId = '')
    {
        try {
            $dropbox    = $this->getAccount();
            $listfolder = array();
            $folderMetadatas = $dropbox->getMetadataWithChildren($baseFolderId, false);
            if (count($folderMetadatas['entries']) > 0) {
                foreach ($folderMetadatas['entries'] as $f) {
                    if ($f['.tag'] === 'folder') {
                        $listfolder[$f['id']] = $f;
                    }
                }
            }
        } catch (Exception $ex) {
            $this->lastError = $ex->getMessage();

            return false;
        }

        return $listfolder;
    }

    /**
     * Get all folder
     *
     * @return array|boolean
     */
    public function listAllFolders()
    {
        try {
            $dropbox      = $this->getAccount();
            $listfolder   = array();
            $baseFolderId = $this->params->dropboxBaseFolderId;
            $folderMetadatas = $dropbox->getMetadataWithChildren($baseFolderId, true);

            if (count($folderMetadatas['entries']) > 0) {
                foreach ($folderMetadatas['entries'] as $f) {
                    if ($f['.tag'] === 'folder') {
                        $listfolder[$f['id']] = $f;
                    }
                }
            }
        } catch (Exception $ex) {
            $this->lastError = $ex->getMessage();

            return false;
        }

        return $listfolder;
    }

    /**
     * Get path dropbox
     *
     * @param array $diff_add Different
     *
     * @return array|boolean
     */
    public function getPathById($diff_add)
    {
        try {
            $dropbox   = $this->getAccount();
            $listPaths = array();
            foreach ($diff_add as $v) {
                $content                           = $dropbox->getMetadata($v);
                $listPaths[$content['path_lower']] = array(
                    'path' => $content['path_lower'],
                    'id'   => $content['id'],
                    'name' => $content['name'],
                );
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $listPaths;
    }

    /**
     * Method download a file
     *
     * @param string $id_file File id
     *
     * @return array
     */
    public function downloadDropbox($id_file)
    {
        try {
            $dropbox = $this->getAccount();
            //download file
            $tempfile = tempnam(sys_get_temp_dir(), 'wpfd');
            $fd = fopen($tempfile, 'wb');
            $fMeta = $dropbox->getFile($id_file, $fd);

            return array($tempfile, $fMeta);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return array();
        }
    }

    /**
     * Get global config
     *
     * @return array
     */
    public function getGlobalConfig()
    {
        $allowedext_list                          = '7z,ace,bz2,dmg,gz,rar,tgz,zip,csv,doc,docx,html,key,keynote,odp,ods,odt,pages,pdf,pps,ppt,"
                                                    . "pptx,rtf,tex,txt,xls,xlsx,xml,bmp,exif,gif,ico,jpeg,jpg,png,psd,tif,tiff,aac,aif,aiff,alac,amr,au,cdda,"
                                                    . "flac,m3u,m4a,m4p,mid,mp3,mp4,mpa,ogg,pac,ra,wav,wma,3gp,asf,avi,flv,m4v,mkv,mov,mpeg,mpg,rm,swf,"
                                                    . "vob,wmv,css,img';
        $extension_list                           = 'png,jpg,pdf,ppt,doc,xls,dxf,ps,eps,xps,psd,tif,tiff,bmp,svg,pages,ai,dxf,ttf,txt,mp3,mp4';
        $defaultConfig                            = array('allowedext' => $allowedext_list);
        $defaultConfig['deletefiles']             = 0;
        $defaultConfig['catparameters']           = 1;
        $defaultConfig['defaultthemepercategory'] = 'default';
        $defaultConfig['date_format']             = 'd-m-Y';
        $defaultConfig['use_google_viewer']       = 'lightbox';
        $defaultConfig['extension_viewer']        = $extension_list;
        $defaultConfig['show_file_import']        = 0;
        $defaultConfig['uri']                     = 'download';
        $defaultConfig['ga_download_tracking']    = 0;
        $config                                   = get_option('_wpfd_global_config', $defaultConfig);
        $config                                   = array_merge($defaultConfig, $config);

        return (array) $config;
    }

    /**
     * Get all files in app folder
     *
     * @param array $filters Filters
     *
     * @return array
     */
    public function getAllFilesInAppFolder($filters)
    {
        try {
            $dropbox = $this->getAccount();
            $listfiles = array();
            if (isset($filters['catid'])) {
                $pathFolder = WpfdAddonHelper::getPathByDropboxId($filters['catid']);
                $fileFolder = $dropbox->getMetadataWithChildren($pathFolder, true);

                $listFileName = array();
                if (isset($filters['q'])) {
                    $keywords = explode(',', $filters['q']);
                    if (count($keywords)) {
                        foreach ($keywords as $keyword) {
                            $fileSearch = $dropbox->searchFileNames('', trim($keyword));
                            foreach ($fileSearch['matches'] as $k => $v) {
                                if ($v['metadata']['.tag'] === 'file') {
                                    $listFileName[] = $v['metadata']['name'];
                                }
                            }
                        }
                    }

                    foreach ($fileFolder['entries'] as $k => $f) {
                        if (!empty($listFileName)) {
                            if (in_array($f['name'], $listFileName)) {
                                if ($this->checkTimeCreate($f, $filters)) {
                                    $listfiles[] = $f;
                                }
                            }
                        }
                    }
                } else {
                    foreach ($fileFolder['entries'] as $k => $f) {
                        if ($this->checkTimeCreate($f, $filters)) {
                            $listfiles[] = $f;
                        }
                    }
                }
            } else {
                if (isset($filters['q'])) {
                    $keywords = explode(',', $filters['q']);
                    if (count($keywords)) {
                        foreach ($keywords as $keyword) {
                            $fileSearch = $dropbox->searchFileNames('', trim($keyword));
                            foreach ($fileSearch['matches'] as $k => $v) {
                                if ($this->checkTimeCreate($v['metadata'], $filters)) {
                                    $listfiles[] = $v['metadata'];
                                }
                            }
                        }
                    }
                } else {
                    $allFile = $dropbox->getMetadataWithChildren('', true);
                    foreach ($allFile['entries'] as $k => $v) {
                        if ($v['.tag'] === 'file') {
                            if ($this->checkTimeCreate($v, $filters)) {
                                $listfiles[] = $v;
                            }
                        }
                    }
                }
            }

            $result = $this->displayFileSearch($listfiles);

            return $result;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return array();
        }
    }

    /**
     * Check Time Create
     *
     * @param array      $file    File
     * @param null|array $filters Filters
     *
     * @return boolean
     */
    private function checkTimeCreate($file, $filters = null)
    {
        $result = false;

        if (!isset($file['client_modified'])) {
            return $result;
        }

        $ftime  = date('Y-m-d', strtotime($file['client_modified']));
        if (isset($filters['cfrom']) && isset($filters['cto'])) {
            if (strtotime($filters['cfrom']) <= strtotime($ftime) && strtotime($ftime) <= strtotime($filters['cto'])) {
                $result = true;
            }
        } elseif (isset($filters['ufrom']) && isset($filters['uto'])) {
            if (strtotime($filters['ufrom']) <= strtotime($ftime) && strtotime($ftime) <= strtotime($filters['uto'])) {
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
     * @param array $listFiles List files
     *
     * @return array
     */
    private function displayFileSearch($listFiles)
    {
        $result = array();
        $config = get_option('_wpfd_global_config');
        if (!empty($config) && !empty($config['date_format'])) {
            $dateFormat = $config['date_format'];
        } else {
            $dateFormat = 'd-m-Y h:i:s';
        }

        if (!empty($listFiles)) {
            foreach ($listFiles as $f) {
                if ($f['.tag'] === 'file') {
                    $fpath               = pathinfo($f['path_display']);
                    $lpath               = pathinfo($f['path_lower']);
                    $termID              = WpfdAddonHelper::getTermIdByDropboxPath($fpath['dirname']);
                    $file                = new stdClass();
                    $file->id            = $f['id'];
                    $file->ID            = $file->id;
                    $file->lpath         = $lpath['dirname'];
                    $file->rawlpath      = $f['path_lower'];
                    $file->post_title    = WpfdAddonHelper::stripExt($lpath['basename']);
                    $file->title         = $fpath['filename'];
                    $file->ext           = $fpath['extension'];
                    $file->size          = (string) $f['size'];
                    $file->created_time  = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($f['client_modified'])));
                    $file->modified_time = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($f['server_modified'])));
                    $file->created       = mysql2date($dateFormat, $file->created_time);
                    $file->modified      = mysql2date($dateFormat, $file->modified_time);

                    $config = WpfdAddonHelper::getDropboxFileInfos();
                    if (!empty($config) && isset($config[$termID]) && isset($config[$termID][$f['id']])) {
                        $file->file_tags     = isset($config[$termID][$f['id']]['file_tags']) ?
                            $config[$termID][$f['id']]['file_tags'] : '';
                        $file->file_password = isset($config[$termID][$f['id']]['file_password']) ?
                            $config[$termID][$f['id']]['file_password'] : '';
                        $file->state = isset($config[$termID][$f['id']]['state']) ?
                            $config[$termID][$f['id']]['state'] : '1';
                    }
                    $result[] = $file;
                }
            }
        }

        return $result;
    }

    /**
     * Get path file
     *
     * @param string $id File id
     *
     * @return mixed
     */
    public function getPathFile($id)
    {
        try {
            $dropbox = $this->getAccount();
            $meta = $dropbox->getMetadata($id);
            $fpath = pathinfo($meta['path_lower']);

            return $fpath['dirname'];
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return '';
        }
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
        $dropbox = $this->getAllDropboxConfigs();
        $base_folder = isset($dropbox['dropboxBaseFolderId']) ? $dropbox['dropboxBaseFolderId'] : false;

        if ($base_folder) {
            try {
                $folders = $this->listChildrenFolders($base_folder);

                foreach ($folders as $folderId => $folder) {
                    $termId = WpfdAddonHelper::getTermIdByDropboxPath($folderId);
                    wp_delete_term($termId, 'wpfd-category');
                    $this->deleteDropbox($folderId);
                }
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
            }
        }
    }

    /**
     * Get version count
     *
     * @param string $fileId Dropbox file id
     *
     * @return integer|void
     */
    public function getVersionsCount($fileId)
    {
        try {
            $client = $this->getAccount();
            $revisionsResponse = $client->listRevisions($fileId);
            if (!array_key_exists('entries', $revisionsResponse) || is_null($revisionsResponse)) {
                // File deleted or entries is missing
                return 0;
            }

            return count($revisionsResponse['entries']);
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
        $fileInfo = WpfdAddonHelper::getDropboxFileInfos();
        $fileInfo[$file['catid']][$file['ID']]['latestVersionNumber'] = $latestVersionNumber;

        return WpfdAddonHelper::setDropboxFileInfos($fileInfo);
    }

    /**
     * Create feature image for Woo product
     *
     * @param string $fileId       File ID
     * @param string $previewsPath Previews folder path on server
     *
     * @return boolean|string
     */
    public function getDropboxImg($fileId, $previewsPath)
    {
        try {
            $dropbox = $this->getAccount();
            $fileMetadata = $dropbox->getFileMetadata($fileId);
            $fileName = md5($fileId);
            $filePath = $previewsPath . 'dropbox_' . strval($fileName) . '.png';
            $fd = fopen($filePath, 'wb');
            $dropbox->getThumbnailV2($fileId, $fd, 'png', 'w1024h768');
            if (file_exists($filePath)) {
                $now = new DateTime();
                $fileUpdate = $now->format('Y-m-d\TH:i:s.v\Z');
                $filePath = str_replace(WP_CONTENT_DIR, '', $filePath);
                update_option('_wpfdAddon_preview_info_' . $fileName, array('updated' => $fileUpdate, 'path' => $filePath));
                return $filePath;
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            fclose($fd);
            unlink($filePath);
        }

        return false;
    }

    /**
     * Watch changes
     *
     * @return boolean|array
     * @throws Exception Throw when failed
     */
    public function watchChanges()
    {
        // Try to add webhook callback URL and then saving the flag listening
        $ajaxUrl     = admin_url('admin-ajax.php');
        $webhookUrl  = $ajaxUrl . '?action=dropboxPushListener';
        $accessToken = $this->checkAndRefreshToken();

        if (false === $accessToken) {
            return false;
        }

        $accessToken     = $accessToken->getToken();
        $url             = 'https://api.dropboxapi.com/2/files/webhooks/add';
        $basicAuthString = base64_encode($this->params->dropboxKey . ':' . $this->params->dropboxSecret);

        $headers = array(
//          'Authorization: Basic ' . $basicAuthString,
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        );

        $postFields = json_encode(array('url' => $webhookUrl));
        $ch         = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        // Execute cURL request
        $response = curl_exec($ch);

        curl_close($ch);

        $watchChange = get_option('_wpfd_dropbox_watch_changes', false);
        $watchChange = (is_null($watchChange) || !$watchChange) ? true : false;
        update_option('_wpfd_dropbox_watch_changes', $watchChange);

        return true;
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
     * Get parent folder id from Dropbox
     *
     * @param string $folderPath Current folder path
     *
     * @throws Exception Fire if errors
     *
     * @return mixed
     */
    public function getDropboxParentFolderId($folderPath)
    {
        if (empty($folderPath)) {
            return null;
        }

        // Initialize client
        $client = $this->getAccount();

        try {
            $parentFolderPath = substr($folderPath, 0, strrpos($folderPath, '/'));

            if (!empty($parentFolderPath)) {
                // Get metadata for the specified folder
                $parentFolderMetadata = $client->getMetadata($parentFolderPath);
                return isset($parentFolderMetadata['id']) ? $parentFolderMetadata['id'] : null;
            } else {
                return null;
            }
        } catch (Exception $e) {
            // Handle any errors
            return null;
        }
    }
}
