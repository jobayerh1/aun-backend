<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Utilities;

/**
 * Class WpfdAddonModelNextcloudFiles
 */
class WpfdAddonModelNextcloudFiles extends Model
{
    /**
     * Allowed file extension
     *
     * @var array
     */
    private $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'tar', 'rar',
        'odt', 'ppt', 'pps', 'txt');

    /**
     * Nextcloud category instance
     *
     * @var WpfdAddonNextcloud
     */
    protected $nextcloud;


    /**
     * WpfdAddonModelNextcloudFiles constructor.
     */
    public function __construct()
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdaddon_nextcloud = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddon_nextcloud .= DIRECTORY_SEPARATOR . 'WpfdAddonNextcloud.php';
        require_once $path_wpfdaddon_nextcloud;
        $this->nextcloud = new WpfdAddonNextcloud;
    }

    /**
     * Nextcloud upload
     *
     * @param integer $termId Term id
     * @param array   $file   Upload file
     *
     * @return array
     */
    public function nextcloudUpload($termId, $file)
    {
        if (array_key_exists('pic', $file) && (int) $file['pic']['error'] === 0) {
            $pic       = $file['pic'];
            $ext       = strtolower(pathinfo($pic['name'], PATHINFO_EXTENSION));
            $fileTitle = base64_decode(pathinfo($pic['name'], PATHINFO_FILENAME));

            $params = array(
                'title'     => $title,
                'parent'    => $parent,
                'type'      => 'folder'
            );
            $result    = $this->nextcloud->uploadObject(
                $fileTitle . '.' . $ext,
                $pic['tmp_name'],
                $pic['size'],
                WpfdAddonHelper::getNextcloudPathByTermId($termId)
            );
            //create description and property
            $fpath    = pathinfo($result['path_lower']);
            $fileInfo = WpfdAddonHelper::getNextcloudFileInfos();
            if (empty($fileInfo) || !isset($fileInfo[$termId])) {
                $fileInfo[$termId] = array();
            }
            $hashID = md5($result['id']);
            $fileInfo[$termId][$hashID] = array(
                'title'       => $fpath['filename'],
                'id'          => $result['id'],
                'description' => '',
                'version'     => '',
                'hits'        => 0,
                'file_tags'   => ''
            );
            WpfdAddonHelper::setNextcloudFileInfos($fileInfo);
            if (!$result) {
                return array('status' => $this->nextcloud->getLastError());
            }
        }

        return array('status' => true, 'data' => array('id_file' => '', 'name' => ''));
    }

    /**
     * Get list Nextcloud files
     *
     * @param integer $termId        Term id
     * @param boolean $list_id_files List id file
     *
     * @return array|boolean
     */
    public function getListNextcloudFiles($termId, $list_id_files)
    {
        $nextcloudPath = WpfdAddonHelper::getNextcloudPathByTermId($termId);
        $files = null;
        if (!$files) {
            $ordering  = Utilities::getInput('orderCol', 'GET', 'none');
            $direction = Utilities::getInput('orderDir', 'GET', 'none');
            if ($ordering !== null) {
                $ordering_array = array(
                    'ordering',
                    'ext',
                    'title',
                    'description',
                    'created_time',
                    'size',
                    'version',
                    'hits'
                );
                if (!in_array($ordering, $ordering_array)) {
                    $ordering = 'ordering';
                } else {
                    if ($direction !== 'desc') {
                        $direction = 'asc';
                    }
                }
            } else {
                $ordering = 'ordering';
            }
            $files = $this->nextcloud->listNextcloudFiles($nextcloudPath, $termId, $ordering, $direction, $list_id_files);
        }

        return $files;
    }

    /**
     * Delete Nextcloud file
     *
     * @param integer $idCategory Term id
     * @param string  $idFile     Nextcloud file id
     *
     * @return boolean
     */
    public function deleteNextcloudFile($idCategory, $idFile)
    {
        $fileInfo = WpfdAddonHelper::getNextcloudFileInfos();
        if (!empty($fileInfo)) {
            $hashID = md5($idFile);
            unset($fileInfo[$idCategory][$hashID]);
            WpfdAddonHelper::setNextcloudFileInfos($fileInfo);
        }

        return $this->nextcloud->deleteFileNextcloud($idFile);
    }

    /**
     * Get file info
     *
     * @param string  $idFile     File id
     * @param integer $idCategory Term id
     *
     * @return array|boolean
     */
    public function getNextcloudFileInfo($idFile, $idCategory)
    {
        return $this->nextcloud->getNextcloudFileInfos(
            $idFile,
            WpfdAddonHelper::getNextcloudPathByTermId($idCategory),
            $idCategory,
            ''
        );
    }

    /**
     * Save file info
     *
     * @param array   $data       File info
     * @param integer $idCategory Term id
     *
     * @return boolean
     */
    public function saveNextcloudFileInfo($data, $idCategory)
    {
        $data['title'] = stripslashes(html_entity_decode($data['title']));
        $hashID = md5($data['id']);
        $data['id'] = stripslashes(rawurldecode($data['id']));

        $fileInfo = WpfdAddonHelper::getNextcloudFileInfos();
        if (!is_array($fileInfo) || empty($fileInfo)) {
            $fileInfo = array();
        }
        $fileInfo[$idCategory][$hashID] = array(
            'title'                   => str_replace('\&#039;', '\'', $data['title']),
            'state'                   => $data['state'],
            'id'                      => $data['id'],
            'description'             => $data['description'],
            'version'                 => $data['version'],
            'hits'                    => $data['hits'],
            'social'                  => isset($data['social']) ? $data['social'] : 0,
            'file_tags'               => $data['file_tags'],
            'file_multi_category'     => $data['file_multi_category'],
            'file_multi_category_old' => $data['file_multi_category_old'],
            'canview'                 => $data['canview'],
            'file_password'           => isset($data['file_password']) ? $data['file_password'] : '',
        );

        $fileInfo[$idCategory][$hashID] = apply_filters('wpfda_nextcloud_before_save_file_info', $fileInfo[$idCategory][$hashID], $data);
        //save custom icon for nextcloud file
        $config = get_option('_wpfd_global_config');
        if (!empty($config['custom_icon'])) {
            $fileInfo[$idCategory][$hashID]['file_custom_icon'] = $data['file_custom_icon'];
        }

        WpfdAddonHelper::setNextcloudFileInfos($fileInfo);

        if (isset($data['state']) && (int)$data['state'] === 1 && isset($data['id'])) {
            $uploadUsers = get_option('_wpfdAddon_nextcloud_file_user_upload');
            if (!empty($uploadUsers)) {
                if (array_key_exists($data['id'], $uploadUsers)) {
                    unset($uploadUsers[$data['id']]);
                    update_option('_wpfdAddon_nextcloud_file_user_upload', $uploadUsers);
                }
            }
        }

        // Update custom preview file
        if (isset($data['id']) && isset($data['file_custom_icon_preview']) && !is_null($data['file_custom_icon_preview'])) {
            update_option('_wpfdAddon_file_custom_icon_preview_' .md5($data['id']), $data['file_custom_icon_preview']);
        }

        return true;
    }

    /**
     * Upload Nextcloud version
     *
     * @param array   $data       Version info
     * @param integer $idCategory Term id
     *
     * @return boolean
     */
    public function uploadNextcloudVersion($data, $idCategory)
    {
        $app = Application::getInstance('WpfdAddon');

        if (!class_exists('WpfdAddonNextcloud')) {
            $path_wpfdaddon_nextcloud = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
            $path_wpfdaddon_nextcloud .= DIRECTORY_SEPARATOR . 'WpfdAddonNextcloud.php';
            require_once $path_wpfdaddon_nextcloud;
        }

        if (!class_exists('WpfdAddonHelper')) {
            $helperPath = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
            $helperPath .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
            require_once $helperPath;
        }

        $nextcloudConfig = WpfdAddonHelper::getAllNextcloudConfigs();

        // Save nextcloud file infos and generate new assets immediately
        $fileInfo = WpfdAddonHelper::getNextcloudFileInfos();
        $version  = $this->nextcloud->saveNextcloudVersion($data);

        if (is_array($nextcloudConfig) && isset($nextcloudConfig['nextcloudState']) && intval($nextcloudConfig['nextcloudState']) === 1) {
            wp_remote_get(
                admin_url('admin-ajax.php') . '?action=nextcloudsync',
                array(
                    'timeout'   => 1,
                    'blocking'  => false,
                    'sslverify' => false,
                )
            );
        }

        return true;
    }

    /**
     * Get list version
     *
     * @param string $fileId      File id
     * @param string $nextcloudId Nextcloud id
     *
     * @return array|boolean
     */
    public function getListVersions($fileId, $nextcloudId)
    {
        return $this->nextcloud->getListVersions($fileId);
    }

    /**
     * Display Nextcloud version info
     *
     * @param string  $idFile     Nextcloud file id
     * @param integer $idCategory Category id
     *
     * @return array|boolean
     */
    public function displayNextcloudVersionInfo($idFile, $idCategory)
    {
        return $this->nextcloud->displayNextcloudVersionInfo($idFile, $idCategory);
    }

    /**
     * Restore version
     *
     * @param string $id_file Nextcloud file id
     * @param string $vid     Nextcloud version id
     *
     * @return boolean
     */
    public function restoreVersion($id_file, $vid)
    {
        return $this->nextcloud->restoreVersion($id_file, $vid);
    }

    /**
     * Delete version
     *
     * @param string $id_file Nextcloud file id
     * @param string $vid     Nextcloud version id
     *
     * @return boolean
     */
    public function deleteVersion($id_file, $vid)
    {
        return $this->nextcloud->deleteVersion($id_file, $vid);
    }

    /**
     * Download version
     *
     * @param string $id_file Nextcloud file id
     * @param string $vid     Nextcloud version id
     * @param string $catId   Nextcloud cat id
     *
     * @return boolean
     */
    public function downloadVersion($id_file, $vid, $catId)
    {
        return $this->nextcloud->downloadNextcloud($id_file, $catId, $vid);
    }
}
