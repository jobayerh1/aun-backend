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
 * Class WpfdAddonModelDropboxFiles
 */
class WpfdAddonModelDropboxFiles extends Model
{
    /**
     * Allowed file extension
     *
     * @var array
     */
    private $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'tar', 'rar',
        'odt', 'ppt', 'pps', 'txt');

    /**
     * Dropbox category instance
     *
     * @var WpfdAddonGoogleDrive
     */
    protected $dropCate;


    /**
     * WpfdAddonModelDropboxFiles constructor.
     */
    public function __construct()
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdaddondropbox = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddondropbox .= DIRECTORY_SEPARATOR . 'WpfdAddonDropbox.php';
        require_once $path_wpfdaddondropbox;
        $this->dropCate = new WpfdAddonDropbox;
    }

    /**
     * Dropbox upload
     *
     * @param integer $termId Term id
     * @param array   $file   Upload file
     *
     * @return array
     */
    public function dropboxUpload($termId, $file)
    {
        if (array_key_exists('pic', $file) && (int) $file['pic']['error'] === 0) {
            $pic       = $file['pic'];
            $ext       = strtolower(pathinfo($pic['name'], PATHINFO_EXTENSION));
            $fileTitle = base64_decode(pathinfo($pic['name'], PATHINFO_FILENAME));
            $result    = $this->dropCate->uploadFile(
                $fileTitle . '.' . $ext,
                $pic['tmp_name'],
                $pic['size'],
                WpfdAddonHelper::getDropboxPathByTermId($termId)
            );
            //create description and property
            $fpath    = pathinfo($result['path_lower']);
            $fileInfo = WpfdAddonHelper::getDropboxFileInfos();
            if (empty($fileInfo) || !isset($fileInfo[$termId])) {
                $fileInfo[$termId] = array();
            }
            $fileInfo[$termId][$result['id']] = array(
                'title'       => $fpath['filename'],
                'id'          => $result['id'],
                'description' => '',
                'version'     => '',
                'hits'        => 0,
                'file_tags'   => ''
            );
            WpfdAddonHelper::setDropboxFileInfos($fileInfo);
            if (!$result) {
                return array('status' => $this->dropCate->getLastError());
            }
        }

        return array('status' => true, 'data' => array('id_file' => '', 'name' => ''));
    }

    /**
     * Get list dropbox files
     *
     * @param integer $termId        Term id
     * @param boolean $list_id_files List id file
     *
     * @return array|boolean
     */
    public function getListDropboxFiles($termId, $list_id_files)
    {
        $dropboxId = WpfdAddonHelper::getDropboxPathByTermId($termId);
        //get version dropbox ... not work
        //$files = $this->dropCate->listVersions($file);
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
            $files = $this->dropCate->listDropboxFiles($dropboxId, $termId, $ordering, $direction, $list_id_files);
        }

        return $files;
    }

    /**
     * Delete dropbox file
     *
     * @param integer $idCategory Term id
     * @param string  $idFile     Dropbox file id
     *
     * @return boolean
     */
    public function deleteDropboxFile($idCategory, $idFile)
    {
        $fileInfo = WpfdAddonHelper::getDropboxFileInfos();
        unset($fileInfo[$idCategory][$idFile]);
        WpfdAddonHelper::setDropboxFileInfos($fileInfo);

        return $this->dropCate->deleteFileDropbox($idFile);
    }

    /**
     * Get file info
     *
     * @param string  $idFile     File id
     * @param integer $idCategory Term id
     *
     * @return array|boolean
     */
    public function getDropboxFileInfo($idFile, $idCategory)
    {
        return $this->dropCate->getDropboxFileInfos(
            $idFile,
            WpfdAddonHelper::getDropboxPathByTermId($idCategory),
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
     * @return string
     */
    public function saveDropboxFileInfo($data, $idCategory)
    {
        $this->dropCate->saveDropboxFileInfos($data);
        $fileInfo                           = WpfdAddonHelper::getDropboxFileInfos();
        $fileInfo[$idCategory][$data['id']] = array(
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
        $fileInfo[$idCategory][$data['id']] = apply_filters('wpfda_dropbox_before_save_file_info', $fileInfo[$idCategory][$data['id']], $data);
        //save custom icon for dropbox file
        $config = get_option('_wpfd_global_config');
        if (!empty($config['custom_icon'])) {
            $fileInfo[$idCategory][$data['id']]['file_custom_icon'] = $data['file_custom_icon'];
        }

        WpfdAddonHelper::setDropboxFileInfos($fileInfo);

        if (isset($data['state']) && (int)$data['state'] === 1 && isset($data['id'])) {
            $uploadUsers = get_option('_wpfdAddon_dropbox_file_user_upload');
            if (!empty($uploadUsers)) {
                if (array_key_exists($data['id'], $uploadUsers)) {
                    unset($uploadUsers[$data['id']]);
                    update_option('_wpfdAddon_dropbox_file_user_upload', $uploadUsers);
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
     * Upload dropbox version
     *
     * @param array   $data       Version info
     * @param integer $idCategory Term id
     *
     * @return boolean
     */
    public function uploadDropboxVersion($data, $idCategory)
    {
        $app = Application::getInstance('WpfdAddon');

        if (!class_exists('WpfdAddonDropbox')) {
            $path_wpfdaddondropbox = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
            $path_wpfdaddondropbox .= DIRECTORY_SEPARATOR . 'WpfdAddonDropbox.php';
            require_once $path_wpfdaddondropbox;
        }

        if (!class_exists('WpfdAddonHelper')) {
            $helperPath = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
            $helperPath .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
            require_once $helperPath;
        }

        // Save dropbox file infos and generate new assets immediately
        $fileInfo = WpfdAddonHelper::getDropboxFileInfos();
        $version  = $this->dropCate->saveDropboxVersion($data);

        if ($version) {
            $fileInfo[$idCategory][$version['id']]['version'] = isset($data['version']) && !empty($data['version']) ? $data['version'] : (string) $version['rev'];
        } else {
            $fileInfo[$idCategory][$version['id']]['version'] = '';
        }

        WpfdAddonHelper::setDropboxFileInfos($fileInfo);
        $dropbox = new WpfdAddonDropbox();
        if (!$dropbox->checkAuth()) {
            wp_remote_get(
                admin_url('admin-ajax.php') . '?action=dropboxSync',
                array(
                    'timeout' => 1,
                    'blocking' => false,
                    'sslverify' => false,
                )
            );
        }

        return true;
    }

    /**
     * Display dropbox version info
     *
     * @param string  $idFile     Dropbox file id
     * @param integer $idCategory Category id
     *
     * @return array|boolean
     */
    public function displayDropboxVersionInfo($idFile, $idCategory)
    {
        return $this->dropCate->displayDropboxVersionInfo($idFile, $idCategory);
    }

    /**
     * Restore version
     *
     * @param string $id_file Dropbox file id
     * @param string $vid     Dropbox version id
     *
     * @return boolean
     */
    public function restoreVersion($id_file, $vid)
    {
        return $this->dropCate->restoreVersion($id_file, $vid);
    }

    /**
     * Download version
     *
     * @param string $id_file Dropbox file id
     * @param string $vid     Dropbox version id
     *
     * @return boolean
     */
    public function downloadVersion($id_file, $vid)
    {
        return $this->dropCate->downloadVersion($id_file, $vid);
    }
}
