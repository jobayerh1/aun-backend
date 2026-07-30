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
 * Class WpfdAddonModelCloudFiles
 */
class WpfdAddonModelCloudFiles extends Model
{
    /**
     * Allowed file extension
     *
     * @var array
     */
    private $allowed_ext = array(
        'jpg',
        'jpeg',
        'png',
        'gif',
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'zip',
        'tar',
        'rar',
        'odt',
        'ppt',
        'pps',
        'txt'
    );

    /**
     * Google category instance
     *
     * @var WpfdAddonGoogleDrive
     */
    protected $googleCate;


    /**
     * WpfdAddonModelCloudFiles constructor.
     */
    public function __construct()
    {
        $app                  = Application::getInstance('WpfdAddon');
        $path_wpfdaddongoogle = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddongoogle .= DIRECTORY_SEPARATOR . 'WpfdAddonGoogle.php';
        require_once $path_wpfdaddongoogle;
        $this->googleCate = new WpfdAddonGoogleDrive;
    }

    /**
     * Google drive upload
     *
     * @param integer $termId Term id
     * @param array   $file   File to upload
     *
     * @return array
     */
    public function googleDriveUpload($termId, $file)
    {
        if (array_key_exists('pic', $file) && (int) $file['pic']['error'] === 0) {
            $pic            = $file['pic'];
            $fileContent    = file_get_contents($pic['tmp_name']);
            $ext            = strtolower(pathinfo($pic['name'], PATHINFO_EXTENSION));
            $fileTitle      = base64_decode(pathinfo($pic['name'], PATHINFO_FILENAME));
            $ggd_uploadFile = $this->googleCate->uploadFile(
                $fileTitle . '.' . $ext,
                $fileContent,
                $pic['type'],
                WpfdAddonHelper::getGoogleDriveIdByTermId($termId)
            );
            if (!$ggd_uploadFile) {
                return array('status' => $this->googleCate->getLastError());
            }
        }

        return array('status' => true, 'data' => array('id_file' => '', 'name' => ''));
    }

    /**
     * Get list google drive files
     *
     * @param integer $termId      Term id
     * @param boolean $listIdFlies Just list ids
     *
     * @return array|boolean
     */
    public function getListGoogleDriveFiles($termId, $listIdFlies)
    {
        $cloudId = WpfdAddonHelper::getGoogleDriveIdByTermId($termId);
        $file    = Utilities::getInput('id_file', 'GET', 'none');
        if (!empty($file)) {
            $files   = $this->googleCate->listVersions($file);
        }

        if (!isset($files) || !$files) {
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
            $tokenModel = $this->getInstance('tokens');
            $files = $this->googleCate->listFiles($cloudId, $ordering, $direction, $listIdFlies, $tokenModel);
        }
        return $files;
    }

    /**
     * Get list version
     *
     * @param string  $fileId  File id
     * @param string  $cloudId Parent id
     * @param integer $termId  Term id
     *
     * @return array|boolean
     */
    public function getListVersions($fileId, $cloudId, $termId)
    {
        return $this->googleCate->listVersions($fileId, $cloudId, $termId);
    }

    /**
     * Get file info
     *
     * @param string  $fileId     File id
     * @param integer $categoryId Category id
     *
     * @return array|boolean
     */
    public function getFileInfo($fileId, $categoryId = null)
    {
        return $this->googleCate->getFileInfos($fileId, null, $categoryId);
    }


    /**
     * Delete google drive file
     *
     * @param integer $idCategory Term id
     * @param string  $idFile     File id
     *
     * @return boolean
     */
    public function deleteGoogleCloudFile($idCategory, $idFile)
    {
        return $this->googleCate->delete($idFile, WpfdAddonHelper::getGoogleDriveIdByTermId($idCategory));
    }

    /**
     * Save file info
     *
     * @param array $data File info
     *
     * @return boolean
     */
    public function saveFileInfo($data)
    {
        $data = apply_filters('wpfda_googleDrive_before_save_file_info', $data);
        return $this->googleCate->saveFileInfos($data);
    }

    /**
     * Upload version
     *
     * @param array  $data    Version data
     * @param string $cloudId Parent
     *
     * @return boolean
     */
    public function uploadVersion($data, $cloudId)
    {
        // Save google file infos and generate new assets immediately
        if ($this->googleCate->saveFileInfos($data, $cloudId)) {
            $cloudConfig = WpfdAddonHelper::getAllCloudConfigs();
            if (isset($cloudConfig['googleCredentials']) && !empty($cloudConfig['googleCredentials'])) {
                wp_remote_get(
                    admin_url('admin-ajax.php') . '?action=googleSync',
                    array(
                        'timeout' => 1,
                        'blocking' => false,
                        'sslverify' => false,
                    )
                );
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Delete version
     *
     * @param string $cloudId   Category cloud id
     * @param string $fileId    File id
     * @param string $versionId Version id
     *
     * @return boolean
     */
    public function deleteVersion($cloudId, $fileId, $versionId)
    {
        return $this->googleCate->deleteRevision($fileId, $versionId, $cloudId);
    }

    /**
     * Download revision
     *
     * @param string $fileId    File id
     * @param string $versionId Version id
     *
     * @return array|boolean
     */
    public function downloadRevision($fileId, $versionId)
    {
        return $this->googleCate->downloadRevision($fileId, $versionId);
    }

    /**
     * Restore revision
     *
     * @param string $fileId    File id
     * @param string $versionId Version id
     *
     * @return boolean
     */
    public function updateRevision($fileId, $versionId)
    {
        return $this->googleCate->updateRevision($fileId, $versionId);
    }
}
