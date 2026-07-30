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
 * Class WpfdAddonModelOnedriveBusinessFiles
 */
class WpfdAddonModelOnedriveBusinessFiles extends Model
{
    /**
     * Allow extensions
     *
     * @var array
     */
    private $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'tar', 'rar',
        'odt', 'ppt', 'pps', 'txt');

    /**
     * Onedrive category instance
     *
     * @var WpfdAddonOneDriveBusiness
     */
    protected $onedriveBusiness;


    /**
     * WpfdAddonModelOnedriveFiles constructor.
     */
    public function __construct()
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdaddononedrive = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddononedrive .= DIRECTORY_SEPARATOR . 'WpfdAddonOneDriveBusiness.php';
        require_once $path_wpfdaddononedrive;
        $this->onedriveBusiness = new WpfdAddonOneDriveBusiness;
    }

    /**
     * Onedrive upload
     *
     * @param integer $termId Term id
     * @param array   $file   File info
     *
     * @return array
     */
    public function oneDriveUpload($termId, $file)
    {
        if (array_key_exists('pic', $file) && (int) $file['pic']['error'] === 0) {
            $pic       = $file['pic'];
            $ext       = strtolower(pathinfo($pic['name'], PATHINFO_EXTENSION));
            $fileTitle = base64_decode(pathinfo($pic['name'], PATHINFO_FILENAME));
            $result    = $this->onedriveBusiness->uploadFile(
                $fileTitle . '.' . $ext,
                $pic,
                $pic['tmp_name'],
                WpfdAddonHelper::getOneDriveIdByTermId($termId)
            );
            // Create description and property
            $fileInfo = WpfdAddonHelper::getOneDriveFileInfos();
            if (empty($fileInfo) || !isset($fileInfo[$termId])) {
                $fileInfo[$termId] = array();
            }
            $fileInfo[$termId][$result['file']['id']] = array(
                'title'       => $result['file']['name'],
                'id'          => $result['file']['id'],
                'description' => '',
                'version'     => '',
                'state'       => '1',
                'hits'        => 0,
                'file_tags'   => ''
            );
            WpfdAddonHelper::setOneDriveFileInfos($fileInfo);
            if (!$result) {
                return array('status' => $result['file']['error']);
            }
            return array('status' => true,
                         'data' => array('id_file' => $result['file']['id'], 'name' => $result['file']['name']));
        }
        return array('status' => false, 'data' => esc_html__('Error on upload file, please try again!', 'wpfdAddon'));
    }

    /**
     * Get list files onedrive
     *
     * @param integer $termId      Term id
     * @param boolean $listIdFlies List id?
     *
     * @return array|boolean
     */
    public function getListOneDriveBusinessFiles($termId, $listIdFlies)
    {
        $oneDriveId = WpfdAddonHelper::getOneDriveBusinessIdByTermId($termId);
        $files = null;
        if (!$files) {
            $ordering = Utilities::getInput('orderCol', 'GET', 'none');
            $direction = Utilities::getInput('orderDir', 'GET', 'none');
            $ordering_array = array('ordering', 'ext', 'title', 'description', 'created_time',
                'size', 'version', 'hits');
            if ($ordering !== null) {
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
            $files = $this->onedriveBusiness->listFiles($oneDriveId, $ordering, $direction, $listIdFlies);
        }
        return $files;
    }

    /**
     * Get list version
     *
     * @param string $fileId     File id
     * @param string $oneDriveId Onedrive id
     *
     * @return array|boolean
     */
    public function getListVersions($fileId, $oneDriveId)
    {
        return $this->onedriveBusiness->getListVersions($fileId, $oneDriveId);
    }

    /**
     * Restore Version
     *
     * @param string $fileId    File id
     * @param string $versionId Version Id
     *
     * @return boolean
     */
    public function restoreVersion($fileId, $versionId)
    {
        return $this->oneDriveCate->restoreVersion($fileId, $versionId);
    }

    /**
     * Get file info
     *
     * @param string  $idFile     File id
     * @param integer $idCategory Term id
     *
     * @return array|boolean
     */
    public function getOneDriveBusinessFileInfo($idFile, $idCategory)
    {
        return $this->onedriveBusiness->getOneDriveBusinessFileInfos($idFile, $idCategory, '');
    }


    /**
     * Delete file onedrive
     *
     * @param integer $idCategory Term id
     * @param string  $idFile     File id
     *
     * @return boolean
     */
    public function deleteOneDriveBusinessFile($idCategory, $idFile)
    {
        return $this->onedriveBusiness->delete($idFile, WpfdAddonHelper::getOneDriveBusinessIdByTermId($idCategory));
    }

    /**
     * Save file info
     *
     * @param array   $data       File info
     * @param integer $idCategory Term id
     *
     * @return mixed
     */
    public function saveOneDriveBusinessFileInfo($data, $idCategory)
    {
        if ($this->onedriveBusiness->saveOnDriveBusinessFileInfos($data)) {
            $fileInfo = WpfdAddonHelper::getOneDriveBusinessFileInfos();
            $fileInfo[$idCategory][$data['id']] = array(
                'title' => $data['title'],
                'id' => $data['id'],
                'description' => $data['description'],
                'version' => $data['version'],
                'hits' => $data['hits'],
                'state' => $data['state'],
                'social' => isset($data['social']) ? $data['social'] : 0,
                'file_tags' => $data['file_tags'],
                'file_multi_category' => $data['file_multi_category'],
                'file_multi_category_old' => $data['file_multi_category_old'],
                'file_password' => isset($data['file_password']) ? $data['file_password'] : '',
            );
            $fileInfo[$idCategory][$data['id']] = apply_filters('wpfda_onedrive_business_before_save_file_info', $fileInfo[$idCategory][$data['id']], $data);
            //save custom icon for onedrive file
            $config = get_option('_wpfd_global_config');
            if (!empty($config['custom_icon'])) {
                $fileInfo[$idCategory][$data['id']]['file_custom_icon'] = $data['file_custom_icon'];
            }

            // Remove pending files if file is from pending to publish status
            $fileUploadUsers = get_option('_wpfdAddon_onedrive_business_file_user_upload') ? get_option('_wpfdAddon_onedrive_business_file_user_upload') : array();
            if (isset($fileInfo[$idCategory][$data['id']]['id']) && (int) $fileInfo[$idCategory][$data['id']]['state'] === 1 && !empty($fileUploadUsers)) {
                $key = $fileInfo[$idCategory][$data['id']]['id'];
                if (array_key_exists((string) $key, $fileUploadUsers)) {
                    unset($fileUploadUsers[$key]);
                    update_option('_wpfdAddon_onedrive_business_file_user_upload', $fileUploadUsers, false);
                }
            }

            // Update custom preview file
            if (isset($data['id']) && isset($data['file_custom_icon_preview']) && !is_null($data['file_custom_icon_preview'])) {
                update_option('_wpfdAddon_file_custom_icon_preview_' .md5($data['id']), $data['file_custom_icon_preview']);
            }

            WpfdAddonHelper::setOneDriveBusinessFileInfos($fileInfo);

            return true;
        }

        return false;
    }

    /**
     * Upload version
     *
     * @param array  $data       Version data
     * @param string $oneDriveId Cloud id
     *
     * @return boolean
     */
    public function uploadVersion($data, $oneDriveId)
    {
        return $this->onedriveBusiness->saveFileInfos($data, $oneDriveId);
    }

    /**
     * Delete version
     *
     * @param string $oneDriveId Onedrive id
     * @param string $fileId     File id
     * @param string $versionId  Version id
     *
     * @return boolean
     */
    public function deleteVersion($oneDriveId, $fileId, $versionId)
    {
        return $this->onedriveBusiness->deleteRevision($fileId, $versionId, $oneDriveId);
    }

    /**
     * Get category by onedrive
     *
     * @param string $oneDriveId Onedrive id
     *
     * @return mixed
     */
    protected function getCategoryByOneDriveId($oneDriveId)
    {
        $termId = WpfdAddonHelper::getTermIdOneDriveByOneDriveId($oneDriveId);
        Application::getInstance('Wpfd');
        $category = Model::getInstance('category');
        return $category->getCategory($termId);
    }

    /**
     * Upload version onedrive
     *
     * @param array   $data   Version data
     * @param integer $termId Term id
     *
     * @return boolean
     */
    public function uploadOneDriveBusinessVersion($data, $termId)
    {
        $app = Application::getInstance('WpfdAddon');
        if (!class_exists('WpfdAddonHelper')) {
            $helperPath = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
            $helperPath .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
            require_once $helperPath;
        }
        $oneDriveBusinessConfig = WpfdAddonHelper::getAllOneDriveBusinessConfigs();

        // Save onedrive business file infos and generate new assets immediately
        $file = $this->onedriveBusiness->uploadFile(
            $data['file_name'],
            $data['file_pic'],
            $data['tmp_name'],
            WpfdAddonHelper::getOneDriveBusinessIdByTermId($termId),
            true
        );
        $version = $file['file'];

        if (isset($version['error'])) {
            return false;
        }
        $fileInfo = WpfdAddonHelper::getOneDriveBusinessFileInfos();

        if ($version) {
            $fileInfo[$termId][$version['id']]['version'] = isset($data['version']) ? $data['version'] : '';
        } else {
            $fileInfo[$termId][$version['id']]['version'] = '';
        }

        WpfdAddonHelper::setOneDriveBusinessFileInfos($fileInfo);

        if (is_array($oneDriveBusinessConfig) && isset($oneDriveBusinessConfig['connected']) && intval($oneDriveBusinessConfig['connected']) === 1) {
            wp_remote_get(
                admin_url('admin-ajax.php') . '?action=onedrivebusinesssync',
                array(
                    'timeout'   => 1,
                    'blocking'  => false,
                    'sslverify' => false,
                )
            );
        }

        return true;
    }
}
