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
 * Class WpfdAddonModelAwsFiles
 */
class WpfdAddonModelAwsFiles extends Model
{
    /**
     * Allowed file extension
     *
     * @var array
     */
    private $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'tar', 'rar',
        'odt', 'ppt', 'pps', 'txt');

    /**
     * AWS category instance
     *
     * @var WpfdAddonAws
     */
    protected $aws;


    /**
     * WpfdAddonModelAwsFiles constructor.
     */
    public function __construct()
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdaddon_aws = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddon_aws .= DIRECTORY_SEPARATOR . 'WpfdAddonAws.php';
        require_once $path_wpfdaddon_aws;
        $this->aws = new WpfdAddonAws;
    }

    /**
     * AWS upload
     *
     * @param integer $termId Term id
     * @param array   $file   Upload file
     *
     * @return array
     */
    public function awsUpload($termId, $file)
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
            $result    = $this->aws->uploadObject(
                $fileTitle . '.' . $ext,
                $pic['tmp_name'],
                $pic['size'],
                WpfdAddonHelper::getAwsPathByTermId($termId)
            );
            //create description and property
            $fpath    = pathinfo($result['path_lower']);
            $fileInfo = WpfdAddonHelper::getAwsFileInfos();
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
            WpfdAddonHelper::setAwsFileInfos($fileInfo);
            if (!$result) {
                return array('status' => $this->aws->getLastError());
            }
        }

        return array('status' => true, 'data' => array('id_file' => '', 'name' => ''));
    }

    /**
     * Get list AWS files
     *
     * @param integer $termId        Term id
     * @param boolean $list_id_files List id file
     *
     * @return array|boolean
     */
    public function getListAwsFiles($termId, $list_id_files)
    {
        $awsPath = WpfdAddonHelper::getAwsPathByTermId($termId);
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
            $files = $this->aws->listAwsFiles($awsPath, $termId, $ordering, $direction, $list_id_files);
        }

        return $files;
    }

    /**
     * Delete AWS file
     *
     * @param integer $idCategory Term id
     * @param string  $idFile     AWS file id
     *
     * @return boolean
     */
    public function deleteAwsFile($idCategory, $idFile)
    {
        $fileInfo = WpfdAddonHelper::getAwsFileInfos();
        unset($fileInfo[$idCategory][$idFile]);
        WpfdAddonHelper::setAwsFileInfos($fileInfo);

        return $this->aws->deleteFileAws($idFile);
    }

    /**
     * Get file info
     *
     * @param string  $idFile     File id
     * @param integer $idCategory Term id
     *
     * @return array|boolean
     */
    public function getAwsFileInfo($idFile, $idCategory)
    {
        return $this->aws->getAwsFileInfos(
            $idFile,
            WpfdAddonHelper::getAwsPathByTermId($idCategory),
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
    public function saveAwsFileInfo($data, $idCategory)
    {
        $data['title'] = stripslashes(html_entity_decode($data['title']));
        $data['id'] = stripslashes(rawurldecode($data['id']));

        if (!$this->aws->saveAwsFileInfos($data, WpfdAddonHelper::getAwsPathByTermId($idCategory))) {
            return false;
        }

        $fileInfo                     = WpfdAddonHelper::getAwsFileInfos();
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

        $fileInfo[$idCategory][$data['id']] = apply_filters('wpfda_aws_before_save_file_info', $fileInfo[$idCategory][$data['id']], $data);
        //save custom icon for aws file
        $config = get_option('_wpfd_global_config');
        if (!empty($config['custom_icon'])) {
            $fileInfo[$idCategory][$data['id']]['file_custom_icon'] = $data['file_custom_icon'];
        }

        WpfdAddonHelper::setAwsFileInfos($fileInfo);

        if (isset($data['state']) && (int)$data['state'] === 1 && isset($data['id'])) {
            $uploadUsers = get_option('_wpfdAddon_aws_file_user_upload');
            if (!empty($uploadUsers)) {
                if (array_key_exists($data['id'], $uploadUsers)) {
                    unset($uploadUsers[$data['id']]);
                    update_option('_wpfdAddon_aws_file_user_upload', $uploadUsers);
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
     * Upload AWS version
     *
     * @param array   $data       Version info
     * @param integer $idCategory Term id
     *
     * @return boolean
     */
    public function uploadAwsVersion($data, $idCategory)
    {
        $app = Application::getInstance('WpfdAddon');

        if (!class_exists('WpfdAddonAws')) {
            $path_wpfdaddon_aws = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
            $path_wpfdaddon_aws .= DIRECTORY_SEPARATOR . 'WpfdAddonAws.php';
            require_once $path_wpfdaddon_aws;
        }

        if (!class_exists('WpfdAddonHelper')) {
            $helperPath = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
            $helperPath .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
            require_once $helperPath;
        }

        $awsConfig = WpfdAddonHelper::getAllAwsConfigs();

        // Save aws file infos and generate new assets immediately
        $fileInfo = WpfdAddonHelper::getAwsFileInfos();
        $version  = $this->aws->saveAwsVersion($data);

        if ($version) {
            $fileInfo[$idCategory][$version['VersionId']]['version'] = isset($data['version']) && !empty($data['version']) ? $data['version'] : '';
        } else {
            $fileInfo[$idCategory][$version['VersionId']]['version'] = '';
        }

        WpfdAddonHelper::setAwsFileInfos($fileInfo);
        if (is_array($awsConfig) && isset($awsConfig['awsConnected']) && intval($awsConfig['awsConnected']) === 1) {
            wp_remote_get(
                admin_url('admin-ajax.php') . '?action=awssync',
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
     * @param string $fileId File id
     * @param string $awsId  AWS id
     *
     * @return array|boolean
     */
    public function getListVersions($fileId, $awsId)
    {
        return $this->aws->getListVersions($fileId);
    }

    /**
     * Display AWS version info
     *
     * @param string  $idFile     AWS file id
     * @param integer $idCategory Category id
     *
     * @return array|boolean
     */
    public function displayAwsVersionInfo($idFile, $idCategory)
    {
        return $this->aws->displayAwsVersionInfo($idFile, $idCategory);
    }

    /**
     * Restore version
     *
     * @param string $id_file AWS file id
     * @param string $vid     AWS version id
     *
     * @return boolean
     */
    public function restoreVersion($id_file, $vid)
    {
        return $this->aws->restoreVersion($id_file, $vid);
    }

    /**
     * Delete version
     *
     * @param string $id_file AWS file id
     * @param string $vid     AWS version id
     *
     * @return boolean
     */
    public function deleteVersion($id_file, $vid)
    {
        return $this->aws->deleteVersion($id_file, $vid);
    }

    /**
     * Download version
     *
     * @param string $id_file AWS file id
     * @param string $vid     AWS version id
     * @param string $catId   AWS cat id
     *
     * @return boolean
     */
    public function downloadVersion($id_file, $vid, $catId)
    {
        return $this->aws->downloadAws($id_file, $catId, $vid);
    }
}
