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
use Joomunited\WPFramework\v1_0_6\Form;

/**
 * Class WpfdAddonNextcloud
 */
class WpfdAddonNextcloud
{
    /**
     * Nextcloud connection config
     *
     * @var array $config
     */
    public $config;

    /**
     * Cloud type
     *
     * @var string
     */
    protected $type = 'nextcloud';

    /**
     * Dav URL
     *
     * @var string
     */
    public $davUrl = '';

    /**
     * Last error
     *
     * @var string
     */
    protected $lastError;

    /**
     * WpfdAddonNextcloud constructor
     */
    public function __construct()
    {
        $this->config = $this->getConfig();
        if (!empty($this->config['nextcloudUsername']) && !empty($this->config['nextcloudURL'])) {
            $this->davUrl = trim($this->config['nextcloudURL'], '/') . '/remote.php/dav/files/'. $this->config['nextcloudUsername'] .'/';
        }
        add_filter('wpfda_nextcloud_configuration_content', array($this, 'renderAdminConfigForm'));

        if ($this->checkConnectNextcloud()) {
            $this->createRootFolder();
        }
    }

    /**
     * Render admin config form
     *
     * @param array $tabs Tabs
     *
     * @return array
     */
    public function renderAdminConfigForm($tabs)
    {
        $html = '';
        $formPath = WPFDA_PLUGIN_DIR_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'admin';
        $formPath .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR;
        $nextcloudConfig = $this->config;
        $nextcloudForm = new Form();
        $formFile = $formPath . 'nextcloud_config.xml';
        $html .= '<div id="wpfd-theme-nextcloud" class="">';
        $html .= '<div id="wpfd-btnconnect-nextcloud">';
        $html .= '<div class="wpfd-nextcloud-error-message">
                    <div class="wpfd-alert-message"><strong>'.esc_html__('Connection failed: ', 'wpfdAddon').'</strong>'.esc_html__('Please check that URL, Username, Password are correct.', 'wpfdAddon').'</div>
                </div>';
        ob_start();
        $this->showConnectButton();
        $html .= ob_get_contents();
        ob_end_clean();
        $html .= '</div>';
        if ($nextcloudForm->load($formFile, $nextcloudConfig)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form render we can not use esc here
            $html .= $nextcloudForm->render();
        }
        $html .= '<a class="ju-button orange-outline-button ju-button-inline nextcloud-documentation" href="https://www.joomunited.com/wordpress-documentation/wp-file-download/686-wp-file-download-addon-nextcloud-integration" target="_blank">'.  esc_html__('Read the online support documentation', 'wpfdAddon') . '</a>';
        $html .= '</div><div style="clear:both">&nbsp;</div>';

        $tabs['nextcloud'] = $html;

        return $tabs;
    }

    /**
     * Get config
     *
     * @return mixed|void
     */
    public function getConfig()
    {
        return WpfdAddonHelper::getAllNextcloudConfigs();
    }

    /**
     * Show connect button
     *
     * @return boolean
     */
    private function showConnectButton()
    {
        if (isset($this->config) && !empty($this->config)) {
            if (!empty($this->config['nextcloudUsername']) && !empty($this->config['nextcloudPassword'])) {
                if (!$this->checkConnectNextcloud()) {
                    ?>
                    <span id="nextcloud_connect" class="ju-button">
                        <img class="automatic-connect-icon wpfd-nextcloud-icon" style="vertical-align: middle;" src="<?php echo esc_url(plugins_url('app/admin/assets/images/nextcloud-icon-color.svg', WPFDA_PLUGIN_FILE)); ?>" alt=""/>
                        <img class="automatic-connect-icon wpfd-nextcloud-spinner-icon" style="vertical-align: middle;" src="<?php echo esc_url(plugins_url('app/admin/assets/images/spinner.gif', WPFDA_PLUGIN_FILE)); ?>" alt=""/>
                        <?php esc_html_e('Connect Nextcloud', 'wpfdAddon') ?></span>
                    <?php
                } else { ?>
                    <a id="nextcloud_disconnect" class="ju-button" href="admin.php?page=wpfdAddon-nextcloud&task=nextcloud.disconnect">
                        <img class="automatic-connect-icon" style="vertical-align: middle;" src="<?php echo esc_url(plugins_url('app/admin/assets/images/nextcloud-icon-color.svg', WPFDA_PLUGIN_FILE)); ?>" alt=""/>
                        <?php esc_html_e('Disconnect Nextcloud', 'wpfdAddon') ?></a>
                    <?php
                }
            }
        }

        return false;
    }

    /**
     * Check Nextcloud state
     *
     * @return boolean
     */
    public function checkConnectNextcloud()
    {
        if (isset($this->config) && (!empty($this->config))) {
            if (!empty($this->config['nextcloudUsername']) &&
                !empty($this->config['nextcloudPassword']) &&
                isset($this->config['nextcloudState']) &&
                (int) $this->config['nextcloudState'] === 1
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Connecting Nextcloud
     *
     * @return boolean
     */
    public function connectNextcloud()
    {
        if (isset($this->config) && (!empty($this->config))) {
            if (!empty($this->config['nextcloudUsername']) &&
                !empty($this->config['nextcloudPassword']) &&
                isset($this->config['nextcloudState']) &&
                (int) $this->config['nextcloudState'] === 0 &&
                !empty($this->config['nextcloudURL'])
            ) {
                $ch = curl_init($this->davUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, $this->config['nextcloudUsername'] . ':' . $this->config['nextcloudPassword']);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if (curl_errno($ch)) {
                    $msg = curl_error($ch);
                    $status = false;
                } else {
                    if ($httpCode === 200 || $httpCode === 207) {
                        $status = true;
                        $msg = '';
                    } else {
                        $datas = $this->convertXmlToArray($response);
                        if (!empty($datas) && isset($datas['message'])) {
                            $msg = $datas['message'];
                        } else {
                            $msg = $response;
                        }
                        $status = false;
                    }
                }

                curl_close($ch);

                return array('status' => $status, 'msg' => $msg);
            }
        }

        return array('status' => false, 'msg' => '');
    }

    /**
     * Create root folder
     *
     * @return boolean
     */
    public function createRootFolder()
    {
        if (!get_option('_wpfdAddon_nextcloud_create_root', false)) {
            if (!get_option('_wpfdAddon_nextcloud_creating_root', false)) {
                add_option('_wpfdAddon_nextcloud_creating_root', 1, '', 'yes');

                $config = $this->config;
                $nextcloudRootFolder = rawurlencode($config['nextcloudRootFolder']);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->davUrl . trim($nextcloudRootFolder, '/'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
                curl_setopt($ch, CURLOPT_NOBODY, 1);
                curl_exec($ch);

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpCode === 200) {
                    add_option('_wpfdAddon_nextcloud_create_root', 1, '', 'yes');
                } elseif ($httpCode === 404) {
                    $result = $this->createFolder($nextcloudRootFolder);
                    if (!empty($result)) {
                        add_option('_wpfdAddon_nextcloud_create_root', 1, '', 'yes');
                    }
                }

                delete_option('_wpfdAddon_nextcloud_creating_root');
            }
        }

        return true;
    }

    /**
     * Create folder
     *
     * @param string $folder_path Folder path
     *
     * @return array|boolean
     */
    public function createFolder($folder_path)
    {
        $folder_path = WpfdAddonHelper::getValidPath($folder_path);
        $config = $this->config;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->davUrl . trim($folder_path, '/'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MKCOL');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
        }
        curl_close($ch);

        $result['path'] = $folder_path;

        return $result;
    }

    /**
     * Delete folder
     *
     * @param object $folder_path Folder path
     *
     * @return boolean
     */
    public function deleteFolder($folder_path)
    {
        $config = $this->config;
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->davUrl . trim($folder_path, '/'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            $response = curl_exec($ch);
            curl_close($ch);
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Rename folder or file
     *
     * @param integer $oldPath Old folder path
     * @param string  $newPath New folder path
     *
     * @return boolean
     */
    public function updateFolderOrFileName($oldPath, $newPath)
    {
        try {
            $config = $this->config;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->davUrl . trim($oldPath, '/'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MOVE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Destination:' . $this->davUrl . $newPath
            ));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            $response = curl_exec($ch);
            curl_close($ch);
            if ($response === '') {
                return true;
            }
        } catch (Exception $ex) {
            return false;
        }

        return false;
    }

    /**
     * Do upload file
     *
     * @param string $params Params list
     *
     * @return boolean|array
     */
    public function uploadFile($params)
    {
        $path = WpfdAddonHelper::getFolderPathOnNextcloud($params['termId']);
        try {
            $config = $this->config;
            if (file_exists($params['file_path'])) {
                $info = pathinfo($params['file_path']);
                $chunk_size = apply_filters('wpfd_addon_upload_chunk_size', 5); // 5Mb per chunk
                $chunk_size = $chunk_size * 1024 * 1024;

                $davUrl = trim($config['nextcloudURL'], '/') . '/remote.php/dav/';
                $data = file_get_contents($params['file_path']);
                $name = $params['file_basename'];

                $ch = curl_init();
                $fileSize = filesize($params['file_path']);

                $destination = trim($path, '/') . '/' . rawurlencode($name);

                if ($fileSize < $chunk_size) {
                    curl_setopt($ch, CURLOPT_URL, $this->davUrl .  $destination);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                    $response = curl_exec($ch);
                } else { // Chunked large file to upload
                    // folder for chunks
                    curl_setopt($ch, CURLOPT_URL, $davUrl . 'uploads/'. $config['nextcloudUsername'] .'/' . $info['filename']);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MKCOL');
                    curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
                    curl_exec($ch);

                    // upload chunks
                    $chunks = str_split($data, $chunk_size);
                    $start = 0;
                    $end = $chunk_size;

                    foreach ($chunks as $key => $chunk) {
                        curl_reset($ch);
                        curl_setopt($ch, CURLOPT_URL, $davUrl . 'uploads/' . $config['nextcloudUsername'] . '/' . $info['filename'] . '/' . $start . '-' . $end);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                        curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $chunk);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Range: bytes='.$start.'-'.$end));
                        curl_exec($ch);
                        $start = 1 + $end;
                        $end = $chunk_size + $start;
                    }

                    // try to move the chunk and use the folder as destination
                    curl_reset($ch);
                    curl_setopt($ch, CURLOPT_URL, $davUrl . 'uploads/' . $config['nextcloudUsername'] . '/' . $info['filename'] . '/.file');
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MOVE');
                    curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Destination:' . $this->davUrl . $destination));
                    curl_exec($ch);
                }

                curl_close($ch);

                $fileID = $this->getFileIdByPath($destination);
                if (empty($fileID)) {
                    return false;
                }

                $result['id'] = $fileID;
                $result['nextcloud_path'] = $destination;

                return $result;
            }

            return false;
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Move a file
     *
     * @param string $filePath      File path
     * @param string $newFolderPath New folder path
     *
     * @return boolean
     */
    public function moveFile($filePath, $newFolderPath)
    {
        try {
            $config = $this->config;
            $filePath = stripslashes(rawurldecode($filePath));
            $fileName = basename($filePath);
            $newFolderPath = rawurldecode($newFolderPath);
            $destinationPath = trim($newFolderPath, '/') . '/' . $fileName;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->davUrl . rawurlencode($filePath));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MOVE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Destination:' . $this->davUrl . rawurlencode($destinationPath)
            ));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            $response = curl_exec($ch);
            curl_close($ch);

            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Get list files from path
     *
     * @param string $folder_path Path
     *
     * @return array
     */
    public function getFilesInFolder($folder_path)
    {
        try {
            $valid_path = WpfdAddonHelper::getValidPath($folder_path);
            $config = $this->config;
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns">
            <d:prop>
            <oc:fileid />
            <oc:size />
            <d:getcontenttype />
            <d:getlastmodified />
            <d:getetag />   
            <d:resourcetype />
            </d:prop>
            </d:propfind>';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->davUrl . trim($valid_path, '/') . '/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            $response = curl_exec($ch);
            curl_close($ch);

            if (!empty($response)) {
                $response = str_replace('oc:', 'd:', $response);
                $items = $this->convertXmlToArray($response);
                if (!empty($items) && isset($items['response'])) {
                    return $items['response'];
                }
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
        }

        return array();
    }

    /**
     * Get folder or file id from path
     *
     * @param string $path Path
     *
     * @return boolean|integer
     */
    public function getFileIdByPath($path)
    {
        try {
            $valid_path = WpfdAddonHelper::getValidPath($path);
            $config = $this->config;
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns">
                <d:prop>
                    <oc:fileid />
                </d:prop>
            </d:propfind>';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->davUrl . trim($valid_path, '/') . '/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml', 'Depth: 0'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            $response = curl_exec($ch);
            curl_close($ch);
            if (!empty($response)) {
                $pattern = '/<oc:fileid ?.*>(.*)<\/oc:fileid>/';
                preg_match($pattern, $response, $matches);
                if (empty($matches)) {
                    return false;
                }

                return (int)$matches[1];
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
        }
        
        return false;
    }

    /**
     * Get single file from path
     *
     * @param string $path Path
     *
     * @return boolean|array
     */
    public function getSingleFile($path)
    {
        $valid_path = WpfdAddonHelper::getValidPath($path);
        $config = $this->config;
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns">
            <d:prop>
                <oc:fileid />
                <oc:size />
                <d:getcontenttype />
                <d:getlastmodified />
                <d:getetag />   
                <d:resourcetype />
            </d:prop>
        </d:propfind>';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->davUrl . trim($valid_path, '/') . '/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml', 'Depth: 0'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = str_replace('oc:', 'd:', $response);
        $items = $this->convertXmlToArray($response);
        if (!empty($items) && isset($items['response'])) {
            return $items['response'];
        }

        return false;
    }

    /**
     * Get list Nextcloud files
     *
     * @param string  $folder_path Folder path on Nextcloud
     * @param integer $idCategory  Category id
     * @param string  $ordering    Ordering
     * @param string  $direction   Ordering direction
     * @param boolean $listIdFlies List id files
     *
     * @return array|boolean
     */
    public function listNextcloudFiles($folder_path, $idCategory, $ordering = 'ordering', $direction = 'asc', $listIdFlies = false)
    {
        $perlink       = get_option('permalink_structure');
        $rewrite_rules = get_option('rewrite_rules');
        $config        = get_option('_wpfd_global_config');
        if (empty($config) || !isset($config['rmdownloadext'])) {
            $rmdownloadext = false;
        } else {
            $rmdownloadext = ((int) $config['rmdownloadext'] === 1) ? true : false;
        }
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
        $files = array();
        $idFolderTitle = substr($folder_path, 0);

        $items = $this->getFilesInFolder($folder_path);
        if (empty($items)) {
            return false;
        }

        $s = 'remote.php/dav/files/' . $this->config['nextcloudUsername'] . '/';
        foreach ($items as $i => $item) {
            if ((int)$i === 0) {
                continue;
            }
            if (!empty($item['propstat']['prop']['size']) && $item['propstat']['prop']['size'] > 0) {
                $paths = explode($s, $item['href']);
                $path = trim($paths[1], '/');
                $info = pathinfo($path);

                $file             = new stdClass();
                $file->id         = $path;
                $file->ID         = rawurlencode(rawurldecode($path));
                $file->title      = rawurldecode($info['filename']);
                $file->post_title = rawurldecode($info['filename']);
                $file->post_name  = $file->title;
                $file->ext        = $info['extension'];
                $file->size       = $item['propstat']['prop']['size'];
                $file->catid      = $idCategory;
                $category         = get_term($idCategory, 'wpfd-category');
                if (!empty($item['propstat']['prop']['getlastmodified'])) {
                    $file->created_time = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($item['propstat']['prop']['getlastmodified'])));
                    $file->created      = mysql2date($dateFormat, $file->created_time);
                } else {
                    $file->created_time = '';
                    $file->created = '';
                }

                if (!empty($rewrite_rules)) {
                    $downloadFileTitle = str_replace('\\', '-', str_replace('/', '-', $file->title));
                    $downloadFileTitle = str_replace('%', '-', $downloadFileTitle);
                    if (strpos($perlink, 'index.php')) {
                        $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $idCategory . '/';
                        $linkdownload .= $category->slug . '/' . rawurldecode($file->id) . '/' . $downloadFileTitle;
                    } else {
                        $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $idCategory . '/';
                        $linkdownload .= $category->slug . '/' . rawurldecode($file->id) . '/' . $downloadFileTitle;
                    }
                    $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $file->ext : '');
                } else {
                    $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task';
                    $linkdownload .= '=file.download&wpfd_category_id=' . $idCategory . '&wpfd_file_id=' . rawurldecode($file->id);
                }

                $file->modified_time    = $file->created_time;
                $file->modified         = $file->created;
                $file->ordering         = 0;
                $file->file_tags        = '';
                $file->linkdownload     = $linkdownload;
                $file->hits             = 0;
                $file->version          = '';
                $file->versionNumber    = '';
                $file->description      = '';
                $file->file_tags        = '';
                $file->file_custom_icon = '';
                $config                 = get_option('_wpfdAddon_nextcloud_fileInfo');
                $idFile = rawurldecode($file->id);
                $hashID = md5($file->ID);
                if (!empty($config) && isset($config[$idCategory]) && isset($config[$idCategory][$hashID])) {
                    if (isset($config[$idCategory][$hashID]['title'])) {
                        $file->title     = $config[$idCategory][$hashID]['title'];
                        $file->post_title = $file->title;
                        $file->post_name  = $file->title;
                    }
                    $file->description   = isset($config[$idCategory][$hashID]['description']) ?
                        $config[$idCategory][$hashID]['description'] : '';
                    $file->version       = isset($config[$idCategory][$hashID]['version']) ?
                        $config[$idCategory][$hashID]['version'] : '';
                    $file->versionNumber = $file->version;
                    $file->hits          = isset($config[$idCategory][$hashID]['hits']) ?
                        $config[$idCategory][$hashID]['hits'] : 0;
                    $file->file_tags     = isset($config[$idCategory][$hashID]['file_tags']) ?
                        $config[$idCategory][$hashID]['file_tags'] : '';
                    $file->state = isset($config[$idCategory][$hashID]['state']) ?
                        $config[$idCategory][$hashID]['state'] : '1';
                    $file->canview = isset($config[$idCategory][$hashID]['canview']) ?
                        $config[$idCategory][$hashID]['canview'] : '';
                    $file->file_password = isset($config[$idCategory][$hashID]['file_password']) ?
                        $config[$idCategory][$hashID]['file_password'] : '';
                    $file->file_custom_icon = isset($config[$idCategory][$hashID]['file_custom_icon']) ?
                        $config[$idCategory][$hashID]['file_custom_icon'] : '';
                }

                $file->catid = $idCategory;
                $files[]     = apply_filters('wpfda_file_info', $file, $this->type);
                unset($file);
            }
        }
        $files = $this->subvalSort($files, $ordering, $direction);

        return $files;
    }

    /**
     * Get Nextcloud file info
     *
     * @param string  $idFile     File id
     * @param string  $folderPath Folder name
     * @param integer $idCategory Term id
     * @param string  $token      Token key
     *
     * @return array|boolean
     */
    public function getNextcloudFileInfos($idFile, $folderPath, $idCategory, $token = '')
    {
        if (!class_exists('WpfdBase')) {
            include_once WPFD_PLUGIN_DIR_PATH . '/app/admin/classes/WpfdBase.php';
        }

        $idFile = stripcslashes(html_entity_decode($idFile));

        $fileInfo = $this->getSingleFile($idFile);
        if (empty($fileInfo)) {
            return false;
        }

        //to fix special characters in file name like german "Umlaute"
        setlocale(LC_ALL, 'en_US.UTF-8');
        $s = 'remote.php/dav/files/' . $this->config['nextcloudUsername'] . '/';
        $paths = explode($s, $fileInfo['href']);
        $path = trim($paths[1], '/');
        $info = pathinfo($path);

        $fsize = $fileInfo['propstat']['prop']['size'];

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

        $category         = get_term($idCategory, 'wpfd-category');
        if (empty($config) || !isset($config['rmdownloadext'])) {
            $rmdownloadext = false;
        } else {
            $rmdownloadext = ((int) $config['rmdownloadext'] === 1) ? true : false;
        }

        if (!empty($rewrite_rules)) {
            if (strpos($perlink, 'index.php')) {
                $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $idCategory . '/';
                $linkdownload       .= $category->slug . '/' . rawurldecode($idFile) . '/' . $info['filename'];
            } else {
                $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $idCategory . '/';
                $linkdownload       .= $category->slug . '/' . rawurldecode($idFile) . '/' . $info['filename'];
            }
            $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $info['extension'] : '');
        } else {
            $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task';
            $linkdownload .= '=file.download&wpfd_category_id=' . $idCategory . '&wpfd_file_id=' . rawurldecode($idFile);
        }

        $data                  = array();
        $data['linkdownload']  = $linkdownload;
        $data['id']            = $idFile;
        $data['ID']            = rawurlencode(rawurldecode($idFile));
        $data['state']         = isset($fileInfo['state']) ? (string) $fileInfo['state'] : '1';
        $data['catid']         = $idCategory;
        $data['title']         = urldecode($info['filename']);
        $data['post_title']    = $data['title'];
        $data['file']          = $info['basename'];
        $data['ext']           = $info['extension'];
        $data['created_time']  = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($fileInfo['propstat']['prop']['getlastmodified'])));
        $data['modified_time'] = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($fileInfo['propstat']['prop']['getlastmodified'])));
        $data['created']       = mysql2date($dateFormat, $data['created_time']);
        $data['modified']      = mysql2date($dateFormat, $data['modified_time']);
        $data['file_tags']     = '';
        $data['size']          = $fsize;
        $data['ordering']      = 1;
        $data['hits']          = 0;
        $data['version']       = '';
        $data['versionNumber'] = '';
        $data['description']   = '';
        $data['file_custom_icon'] = '';
        $remote_url            = (isset($file_meta) && isset($file_meta['remote_url'])) ? $file_meta['remote_url'] : false;
        if ($viewer_type !== 'no' && in_array($info['extension'], $extension_viewer) && ($remote_url === false)) {
            $data['viewer_type'] = $viewer_type;
            $data['viewerlink']  = WpfdHelperFile::isMediaFile($info['extension']) ?
                WpfdHelperFile::getMediaViewerUrl(
                    $idFile,
                    $idCategory,
                    $info['extension'],
                    $token
                ) : WpfdHelperFile::getViewerUrl($idFile, $idCategory, $token);
        }
        $config = get_option('_wpfdAddon_nextcloud_fileInfo');
        $hashID = md5($data['ID']);
        if (!empty($config) && isset($config[$idCategory]) && isset($config[$idCategory][$hashID])) {
            if (isset($config[$idCategory][$hashID]['title'])) {
                $data['title']               = $config[$idCategory][$hashID]['title'];
                $data['post_title']          = $data['title'];
            }
            $data['description']             = isset($config[$idCategory][$hashID]['description']) ?
                $config[$idCategory][$hashID]['description'] : '';
            $data['version']                 = isset($config[$idCategory][$hashID]['version']) ?
                $config[$idCategory][$hashID]['version'] : '';
            $data['versionNumber']           = $data['version'];
            $data['latestVersionNumber'] = isset($config[$idCategory][$hashID]['latestVersionNumber']) ?
                $config[$idCategory][$hashID]['latestVersionNumber'] : false;
            $data['hits']                    = isset($config[$idCategory][$hashID]['hits']) ?
                $config[$idCategory][$hashID]['hits'] : 0;
            $data['social']                  = isset($config[$idCategory][$hashID]['social']) ?
                $config[$idCategory][$hashID]['social'] : 0;
            $data['file_tags']               = isset($config[$idCategory][$hashID]['file_tags']) ?
                $config[$idCategory][$hashID]['file_tags'] : '';
            $data['file_multi_category']     = isset($config[$idCategory][$hashID]['file_multi_category']) ?
                $config[$idCategory][$hashID]['file_multi_category'] : '';
            $data['file_multi_category_old'] = isset($config[$idCategory][$hashID]['file_multi_category_old']) ?
                $config[$idCategory][$hashID]['file_multi_category_old'] : '';
            $data['file_custom_icon'] = isset($config[$idCategory][$hashID]['file_custom_icon']) ?
                $config[$idCategory][$hashID]['file_custom_icon'] : '';
            if (isset($config[$idCategory][$hashID]['canview'])) {
                $data['canview'] = $config[$idCategory][$hashID]['canview'];
            }
            if (isset($config[$idCategory][$hashID]['file_password'])) {
                $data['file_password'] = $config[$idCategory][$hashID]['file_password'];
            }
        }
        if (isset($config[$idCategory][$hashID]['state'])) {
            $data['state']         = (string) $config[$idCategory][$hashID]['state'] ;
        }
        $data = apply_filters('wpfda_file_info', $data, $this->type);

        return $data;
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
            $listfiles = array();
            $result = array();
            $config = $this->config;

            if (isset($filters['catid'])) {
                if ($filters['catid'] === 0) {
                    $pathFolder = $config['nextcloudRootFolder'];
                    unset($filters['catid']);
                } else {
                    if (is_numeric($filters['catid'])) {
                        $pathFolder = WpfdAddonHelper::getFolderPathOnNextcloud($filters['catid']);
                    } elseif ($filters['catid'] !== '' && is_string($filters['catid'])) {
                        $pathFolder = $filters['catid'];
                    }
                }
                if (isset($filters['q'])) {
                    $keywords = explode(',', $filters['q']);
                    if (count($keywords)) {
                        foreach ($keywords as $keyword) {
                            $keyword = trim($keyword);
                            $listfiles = $this->listAllFileInFolder($keyword, $pathFolder);
                        }
                    }
                } else {
                    $listfiles = $this->listAllFileInFolder('', $pathFolder);
                }
            } else {
                $pathFolder = $config['nextcloudRootFolder'];
                if (isset($filters['q'])) {
                    $keywords = explode(',', $filters['q']);
                    if (count($keywords)) {
                        foreach ($keywords as $keyword) {
                            foreach ($keywords as $keyword) {
                                $keyword = trim($keyword);
                                $listfiles = $this->listAllFileInFolder($keyword, $pathFolder);
                            }
                        }
                    }
                } else {
                    $listfiles = $this->listAllFileInFolder('', $pathFolder);
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
     * Function to list all files in a folder and its subfolders
     *
     * @param string $keyword     The keyword to search for in file names
     * @param string $folder_path The folder path
     *
     * @return array
     */
    public function listAllFileInFolder($keyword, $folder_path)
    {
        $valid_path = WpfdAddonHelper::getValidPath($folder_path);
        $config = $this->config;
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns">
        <d:prop>
        <oc:fileid />
        <oc:size />
        <d:displayname />
        <d:getcontenttype />
        <d:getlastmodified />
        <d:getetag />   
        <d:resourcetype />
        </d:prop>
        </d:propfind>';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->davUrl . trim($valid_path, '/') . '/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml', 'Depth: infinity'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = str_replace('oc:', 'd:', $response);
        $items = $this->convertXmlToArray($response);
        $result = array();
        if (!empty($items) && !empty($items['response'])) {
            foreach ($items['response'] as $i => $item) {
                if ((int)$i === 0) {
                    continue;
                }
                if (!empty($item['propstat']['prop']['size']) && $item['propstat']['prop']['size'] > 0) {
                    if (!empty($keyword)) {
                        if (strpos($item['propstat']['prop']['displayname'], $keyword) !== false) {
                            $result[] = $item;
                        }
                    } else {
                        $result[] = $item;
                    }
                }
            }
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
            $s = 'remote.php/dav/files/' . $this->config['nextcloudUsername'] . '/';
            
            foreach ($listFiles as $item) {
                $paths = explode($s, $item['href']);
                $path = trim($paths[1], '/');
                $info = pathinfo($path);
                $idCategory = WpfdAddonHelper::getTermIdByNextcloudPath($info['dirname']);

                $file             = new stdClass();
                $file->id         = $path;
                $file->ID         = rawurlencode(rawurldecode($path));
                $file->title      = rawurldecode($info['filename']);
                $file->post_title = rawurldecode($info['filename']);
                $file->post_name  = $file->title;
                $file->ext        = $info['extension'];
                $file->size       = $item['propstat']['prop']['size'];
                $file->dirname    = $info['dirname'];
                $file->catid      = $idCategory;
                $category         = get_term($idCategory, 'wpfd-category');
                if (!empty($item['propstat']['prop']['getlastmodified'])) {
                    $file->created_time = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($item['propstat']['prop']['getlastmodified'])));
                    $file->created      = mysql2date($dateFormat, $file->created_time);
                } else {
                    $file->created_time = '';
                    $file->created = '';
                }

                if (!empty($rewrite_rules)) {
                    if (strpos($perlink, 'index.php')) {
                        $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $idCategory . '/';
                        $linkdownload .= $category->slug . '/' . rawurldecode($file->id) . '/' . $file->title;
                    } else {
                        $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $idCategory . '/';
                        $linkdownload .= $category->slug . '/' . rawurldecode($file->id) . '/' . $file->title;
                    }
                    $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $file->ext : '');
                } else {
                    $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task';
                    $linkdownload .= '=file.download&wpfd_category_id=' . $idCategory . '&wpfd_file_id=' . $file->ID;
                }

                $file->modified_time    = $file->created_time;
                $file->modified         = $file->created;
                $file->ordering         = 0;
                $file->file_tags        = '';
                $file->linkdownload     = $linkdownload;
                $file->hits             = 0;
                $file->version          = '';
                $file->versionNumber    = '';
                $file->description      = '';
                $file->file_tags        = '';
                $file->file_custom_icon = '';

                $config = WpfdAddonHelper::getNextcloudFileInfos();
                $idFile = md5($file->ID);
                if (!empty($config) && isset($config[$idCategory]) && isset($config[$idCategory][$idFile])) {
                    if (isset($config[$idCategory][$idFile]['title'])) {
                        $file->title     = $config[$idCategory][$idFile]['title'];
                        $file->post_title = $file->title;
                        $file->post_name  = $file->title;
                    }
                    $file->description   = isset($config[$idCategory][$idFile]['description']) ?
                        $config[$idCategory][$idFile]['description'] : '';
                    $file->version       = isset($config[$idCategory][$idFile]['version']) ?
                        $config[$idCategory][$idFile]['version'] : '';
                    $file->versionNumber = $file->version;
                    $file->hits          = isset($config[$idCategory][$idFile]['hits']) ?
                        $config[$idCategory][$idFile]['hits'] : 0;
                    $file->file_tags     = isset($config[$idCategory][$idFile]['file_tags']) ?
                        $config[$idCategory][$idFile]['file_tags'] : '';
                    $file->state = isset($config[$idCategory][$idFile]['state']) ?
                        $config[$idCategory][$idFile]['state'] : '1';
                    $file->canview = isset($config[$idCategory][$idFile]['canview']) ?
                        $config[$idCategory][$idFile]['canview'] : '';
                    $file->file_password = isset($config[$idCategory][$idFile]['file_password']) ?
                        $config[$idCategory][$idFile]['file_password'] : '';
                    $file->file_custom_icon = isset($config[$idCategory][$idFile]['file_custom_icon']) ?
                        $config[$idCategory][$idFile]['file_custom_icon'] : '';
                }

                $result[] = $file;
            }
        }

        return $result;
    }

    /**
     * Download file Nextcloud
     *
     * @param string  $fileId    File id
     * @param integer $catid     Term ID
     * @param string  $versionId Version ID
     *
     * @return boolean|void
     */
    public function downloadNextcloud($fileId, $catid, $versionId = false)
    {
        try {
            $valid_path = WpfdAddonHelper::getValidPath($fileId);
            $config = $this->config;
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns">
                <d:prop>
                    <oc:fileid />
                    <oc:size />
                    <d:getcontenttype />
                    <d:getlastmodified />
                    <d:getetag />   
                    <d:resourcetype />
                </d:prop>
            </d:propfind>';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->davUrl . trim($valid_path, '/') . '/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                return false;
            }

            $fileSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

            curl_close($ch);

            if (empty($response)) {
                return false;
            }

            $fileName = basename($fileId);
            $fileInfos = get_option('_wpfdAddon_nextcloud_fileInfo');
            $hashID = md5(rawurlencode(rawurldecode($fileId)));
            if (!empty($fileInfos) && isset($fileInfos[$catid]) && isset($fileInfos[$catid][$hashID])) {
                if (isset($fileInfos[$catid][$hashID]['title'])) {
                    $info = pathinfo($fileId);
                    $fileName = $fileInfos[$catid][$hashID]['title'] . '.' . $info['extension'];
                }
            }

            // Set the appropriate headers for the browser to recognize it as a download
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $fileSize);

            // Output the file content to the browser
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Print file content as is
            echo $response;
            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Delete a file Nextcloud
     *
     * @param string $fileId File Id
     *
     * @return boolean
     */
    public function deleteFileNextcloud($fileId)
    {
        try {
            $valid_path = WpfdAddonHelper::getValidPath($fileId);
            $config = $this->config;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->davUrl . trim($valid_path, '/'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            $response = curl_exec($ch);
            curl_close($ch);

            $nextcloudUpload = get_option('_wpfdAddon_nextcloud_file_user_upload') ? get_option('_wpfdAddon_nextcloud_file_user_upload') : array();
            if (!empty($nextcloudUpload)) {
                if (array_key_exists($fileId, $nextcloudUpload)) {
                    unset($nextcloudUpload[$fileId]);
                    update_option('_wpfdAddon_nextcloud_file_user_upload', $nextcloudUpload);
                }
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
     * @param array $params Version data
     *
     * @return boolean|mixed
     */
    public function saveNextcloudVersion($params)
    {
        $path = WpfdAddonHelper::getValidPath($params['fileId']);
        try {
            $config = $this->config;
            if (!empty($params['data'])) {
                $chunk_size = apply_filters('wpfd_addon_upload_chunk_size', 5); // 5Mb per chunk
                $chunk_size = $chunk_size * 1024 * 1024;
                $fileContent = $params['data'];
                $davUrl = trim($config['nextcloudURL'], '/') . '/remote.php/webdav/';
                $fileSize = strlen($fileContent); // Use string length for file content size

                $destination = trim($path, '/');

                $ch = curl_init();

                // Check if file exists (for versioning)
                curl_setopt($ch, CURLOPT_URL, $this->davUrl . $destination);
                curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request to check if file exists
                curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($httpCode === 200 && $params['newRevision'] === true) {
                    // If the file exists and we're uploading a new version
                    if ($fileSize < $chunk_size) {
                        // Direct upload for smaller files
                        $fp = fopen('php://memory', 'rw');
                        fwrite($fp, $fileContent);
                        rewind($fp);

                        curl_setopt($ch, CURLOPT_URL, $davUrl . $destination);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                        curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
                        curl_setopt($ch, CURLOPT_PUT, true);
                        curl_setopt($ch, CURLOPT_INFILE, $fp);
                        curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
                        $response = curl_exec($ch);
                        fclose($fp);
                    } else {
                        // Chunked upload for larger files
                        // Create folder for chunks
                        curl_setopt($ch, CURLOPT_URL, $davUrl . 'uploads/' . $config['nextcloudUsername'] . '/' . $params['title']);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MKCOL');
                        curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
                        curl_exec($ch);

                        // Upload chunks
                        $chunks = str_split($fileContent, $chunk_size);
                        $start = 0;
                        $end = $chunk_size;

                        foreach ($chunks as $key => $chunk) {
                            $tmpFile = tmpfile();
                            fwrite($tmpFile, $chunk);
                            fseek($tmpFile, 0);

                            curl_setopt($ch, CURLOPT_URL, $davUrl . 'uploads/' . $config['nextcloudUsername'] . '/' . $params['title'] . '/' . $start . '-' . $end);
                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                            curl_setopt($ch, CURLOPT_PUT, true);
                            curl_setopt($ch, CURLOPT_INFILE, $tmpFile);
                            curl_setopt($ch, CURLOPT_INFILESIZE, strlen($chunk));
                            $res = curl_exec($ch);

                            fclose($tmpFile);
                            $start = 1 + $end;
                            $end = $chunk_size + $start;
                        }

                        // Move the chunked file to the destination
                        curl_setopt($ch, CURLOPT_URL, $davUrl . 'uploads/' . $config['nextcloudUsername'] . '/' . $params['title'] . '/.file');
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MOVE');
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Destination:' . $davUrl . $destination));
                        $response = curl_exec($ch);
                    }
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($http_code === 201 || $http_code === 204) {
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    // Handle case where file does not exist or new revision is not requested
                    return false;
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Get list versions
     *
     * @param string $filePath File path
     *
     * @return array
     */
    public function getListVersions($filePath)
    {
        try {
            $file_id = $this->getFileIdByPath($filePath);
            $config = $this->config;
            $davUrl = trim($config['nextcloudURL'], '/') . '/remote.php/dav/versions/'.$config['nextcloudUsername'].'/versions/'.$file_id;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $davUrl);
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Depth: 1', 'Content-Type: text/xml'));
            $response = curl_exec($ch);
            curl_close($ch);
            $response = str_replace('oc:', 'd:', $response);
            $items = $this->convertXmlToArray($response);
            $versions = array();
            if (!empty($items['response'])) {
                $total_versions = count($items['response']);
                foreach ($items['response'] as $i => $item) {
                    if ((int)$i === 0) {
                        continue;
                    }
                    $version_info = array(
                        'href'          => $item['href'],
                        'last_modified' => $item['propstat']['prop']['getlastmodified'],
                        'content_length'=> $item['propstat']['prop']['getcontentlength'],
                        'etag'          => $item['propstat']['prop']['getetag'],
                        'content_type'  => $item['propstat']['prop']['getcontenttype'],
                    );
                    $versions[] = $version_info;
                }
            }

            return $versions;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return array();
        }
    }

    /**
     * Restore file version
     *
     * @param string $filePath  File path
     * @param string $versionId Version Id
     *
     * @return boolean
     */
    public function restoreVersion($filePath, $versionId)
    {
        if (empty($filePath) || empty($versionId)) {
            return false;
        }

        try {
            $config = $this->config;
            $file_id = $this->getFileIdByPath($filePath);
            if (empty($file_id)) {
                return false;
            }

            $webdavBase = trim($config['nextcloudURL'], '/') . '/remote.php/dav';
            $restoreUrl = $webdavBase . '/versions/' . $config['nextcloudUsername'] . '/restore/target';
            $versionWebdavPath = $webdavBase . '/versions/'.$config['nextcloudUsername'].'/versions/'.$file_id.'/'.$versionId;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $versionWebdavPath);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MOVE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Destination:' . $restoreUrl));
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($httpCode === 201) {
                return true;
            } else {
                $this->lastError = $response;
                return false;
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }

        return false;
    }

    /**
     * Delete file version
     *
     * @param string $filePath  File path
     * @param string $versionId Version Id
     *
     * @return boolean
     */
    public function deleteVersion($filePath, $versionId)
    {
        if (empty($filePath) || empty($versionId)) {
            return false;
        }

        try {
            $config = $this->config;
            $file_id = $this->getFileIdByPath($filePath);

            // Delete the old version
            $versionUrl = trim($config['nextcloudURL'], '/') . '/remote.php/dav/versions/'. $config['nextcloudUsername'].'/versions/'.$file_id.'/'.$versionId;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $versionUrl);
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $deleteResponse = curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Get current version ID
     *
     * @param string $filePath File path
     *
     * @return boolean|integer
     */
    public function getCurrentVersionId($filePath)
    {
        try {
            $config = $this->config;

            // Delete the old version
            $versionUrl = trim($config['nextcloudURL'], '/') . '/remote.php/dav/versions/'. $config['nextcloudUsername'].'/versions/'.$file_id.'/'.$versionId;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $versionUrl);
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $deleteResponse = curl_exec($ch);
            curl_close($ch);

            // WebDAV path for listing file versions
            $versionWebdavPath = $nextcloudUrl . '/remote.php/dav/versions/' . $config['nextcloudUsername'] . '/' . $filePath;

            // Initialize cURL for PROPFIND request
            $ch = curl_init();
            
            // Set the URL to list versions
            curl_setopt($ch, CURLOPT_URL, $versionWebdavPath);
            
            // Specify PROPFIND method (WebDAV method to list items)
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
            
            // Depth header is needed to list child resources
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Depth: 1', // List only direct child resources (i.e., the versions)
                'Content-Type: text/xml'
            ]);
            
            // Enable basic HTTP authentication
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            
            // Optionally disable SSL verification (for development)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            // Return the response as a string
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            // Execute the request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Close the cURL session
            curl_close($ch);
            
            if ($httpCode === 207) { // 207 Multi-Status is the correct response code for WebDAV PROPFIND
                // Parse the response to extract version IDs (response will be in XML format)
                // You can use a simple XML parser to get the current version ID
                $xml = simplexml_load_string($response);
                $namespaces = $xml->getNamespaces(true);
                
                // Loop through the response to find version IDs
                foreach ($xml->xpath('//d:response') as $response) {
                    // Assuming the version ID is part of the href element
                    $href = (string) $response->xpath('d:href')[0];
                    // Extract version ID from the href
                    if (strpos($href, '/versions/') !== false) {
                        $versionId = basename($href); // The last part of the href is the version ID
                        return $versionId;
                    }
                }
            } else {
                $this->lastError = $response;
                return false;
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }

        return false;
    }

    /**
     * Change Nextcloud order
     *
     * @param string $move       Move path
     * @param string $location   New location path
     * @param string $parent     Parent
     * @param string $folderName Folder name
     *
     * @return boolean|mixed
     */
    public function changeNextcloudOrder($move, $location, $parent, $folderName)
    {
        try {
            $path = trim(rawurldecode($move), '/');
            $config = $this->config;
            if ($parent !== 0) {
                $destinationPath = trim($location, '/') . '/' . $folderName;
            } else {
                $destinationPath = trim($config['nextcloudRootFolder'], '/') . '/' . $folderName;
            }
            $destinationPath = rawurldecode($destinationPath);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->davUrl . rawurlencode($path));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MOVE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Destination:' . $this->davUrl . rawurlencode($destinationPath)
            ));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERPWD, $config['nextcloudUsername'] . ':' . $config['nextcloudPassword']);
            $response = curl_exec($ch);
            curl_close($ch);

            return $destinationPath;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
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
        $fileInfos = WpfdAddonHelper::getNextcloudFileInfos();
        $filePath = stripslashes(rawurldecode($fileId));
        $hashID = md5(rawurlencode($filePath));
        if (!empty($fileInfos)) {
            $hits = isset($fileInfos[$catId][$hashID]['hits']) ? intval($fileInfos[$catId][$hashID]['hits']) : 0;
            $fileInfos[$catId][$hashID]['hits'] = $hits + 1;
        } else {
            $fileInfos = array();
            $fileInfos[$catId][$hashID]['hits'] = 1;
        }

        WpfdAddonHelper::setNextcloudFileInfos($fileInfos);
    }

    /**
     * Convert xml string to array
     *
     * @param string $xml Xml string
     *
     * @return array
     */
    public function convertXmlToArray($xml)
    {
        $content = str_replace(array('\n', '\r', '\t', 'd:', 's:'), '', $xml);
        $content = trim(str_replace('"', "'", $content));
        $simpleXml = simplexml_load_string($content);
        $json = json_encode($simpleXml);
        $return = json_decode($json, true);
        return $return;
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
}
