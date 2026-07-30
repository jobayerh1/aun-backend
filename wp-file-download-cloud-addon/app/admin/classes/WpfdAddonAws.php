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
use Aws\S3\S3Client;
use Aws\Sdk;

/**
 * Class WpfdAddonAws
 */
class WpfdAddonAws
{
    /**
     * Aws connection config
     *
     * @var array $config
     */
    public $config;

    /**
     * Params
     *
     * @var $param
     */
    protected $params;

    /**
     * AWS3 client
     *
     * @var S3Client
     */
    private $client = null;

    /**
     * Cloud type
     *
     * @var string
     */
    protected $type = 'aws';

    /**
     * Last error
     *
     * @var string
     */
    protected $lastError;

    /**
     * Regions list
     *
     * @var array
     */
    public $regions = array(
        'us-east-1'      => 'US East (N. Virginia)',
        'us-east-2'      => 'US East (Ohio)',
        'us-west-1'      => 'US West (N. California)',
        'us-west-2'      => 'US West (Oregon)',
        'ca-central-1'   => 'Canada (Central)',
        'ap-south-1'     => 'Asia Pacific (Mumbai)',
        'ap-northeast-2' => 'Asia Pacific (Seoul)',
        'ap-southeast-1' => 'Asia Pacific (Singapore)',
        'ap-southeast-2' => 'Asia Pacific (Sydney)',
        'ap-northeast-1' => 'Asia Pacific (Tokyo)',
        'eu-central-1'   => 'EU (Frankfurt)',
        'eu-west-1'      => 'EU (Ireland)',
        'eu-west-2'      => 'EU (London)',
        'eu-west-3'      => 'EU (Paris)',
        'sa-east-1'      => 'South America (Sao Paulo)'
    );

    /**
     *  WpfdAddonAws construct
     *
     *  @param string $region Region
     */
    public function __construct($region = '')
    {
        $this->config = $this->getConfig();
        if (isset($this->config['awsState']) && $this->config['awsState']) {
            $this->client = $this->getClient($region);
            if ($this->client !== null && $this->client !== false) {
                if (isset($this->config['awsConnected']) && $this->config['awsConnected']) {
                    $bucketName = $this->config['awsBucketName'];
                    try {
                        if (empty($this->config['awsVersion']) || (isset($this->config['awsVersion']) && !$this->config['awsVersion'])) {
                            // Get the versioning configuration for the bucket
                            $awsBucketVersion = $this->client->getBucketVersioning([
                                'Bucket' => $bucketName,
                            ]);
                            // Check the status of versioning
                            $versioningStatus = !empty($awsBucketVersion) && isset($awsBucketVersion['Status']) ? $awsBucketVersion['Status'] : 'Disabled';
                            if ($versioningStatus === 'Disabled') {
                                // Enable versioning for the bucket
                                $this->client->putBucketVersioning([
                                    'Bucket' => $bucketName,
                                    'VersioningConfiguration' => [
                                        'Status' => 'Enabled'
                                    ],
                                ]);
                            }
                            $this->config['awsVersion'] = true;
                            update_option('_wpfdAddon_aws_config', $this->config);
                        }
                    } catch (Exception $e) {
                        try {
                            // Check if the bucket exists
                            $bucketExists = $this->doesBucketExist($bucketName);
                            if (!$bucketExists) {
                                // If the bucket doesn't exist, create it
                                $this->createBucket($bucketName, $this->config['awsRegion']);
                            }
                        } catch (Exception $e) {
                            $this->lastError = $e->getMessage();
                        }
                    }
                }
            }
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
     * Get config
     *
     * @return mixed|void
     */
    public function getConfig()
    {
        return WpfdAddonHelper::getAllAwsConfigs();
    }

    /**
     * Read AWS3 key and secret
     *
     * @param string $region Region
     *
     * @return S3Client|boolean
     */
    public function getClient($region)
    {
        if (empty($this->config['awsKey']) && empty($this->config['awsSecret'])) {
            return false;
        }

        if ($region === '') {
            $region = $this->config['awsRegion'];
        }

        $args = array(
            'version' => 'latest',
            'region'  => $region,
            'credentials' => array(
                'key'    => $this->config['awsKey'],
                'secret' => $this->config['awsSecret']
            )
        );
        
        $S3Client = new Sdk($args);
        if ($args['region'] === 'us-east-1') {
            try {
                $S3Client = $S3Client->createMultiRegionS3($args);
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
        } else {
            try {
                $S3Client = $S3Client->createS3($args);
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
        }
        $config = $this->config;
        try {
            if (empty($config['awsConnected']) || (isset($config['awsConnected']) && !$config['awsConnected'])) {
                $result = $S3Client->listBuckets();
                $config['awsConnected'] = true;
                update_option('_wpfdAddon_aws_config', $config);
            }
        } catch (Exception $e) {
            $config['awsConnected'] = false;
            update_option('_wpfdAddon_aws_config', $config);
            $this->lastError = $e->getMessage();
            return false;
        }
        $this->config = $config;

        return $S3Client;
    }

    /**
     * Create bucket.
     *
     * @param string $bucketName Bucket name
     * @param string $region     Bucket region
     *
     * @return void|boolean
     */
    public function createBucket($bucketName, $region)
    {
        try {
            $args = array(
                'Bucket' => $bucketName,
                'CreateBucketConfiguration' => array('LocationConstraint' => $region)
            );
            $this->client->createBucket($args);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return $e->getMessage();
        }
        return true;
    }

    /**
     * Check whether bucket exists.
     *
     * @param string $bucket Bucket name
     *
     * @return boolean
     */
    public function doesBucketExist($bucket)
    {
        return $this->client->doesBucketExist($bucket);
    }

    /**
     * Get the list of S3 buckets
     *
     * @param string $getHtml Check status
     *
     * @return array|void
     */
    public function getListBucket($getHtml = false)
    {
        $results = array();
        if (!isset($this->config['awsConnected']) || (isset($this->config['awsConnected']) && (int) $this->config['awsConnected'] !== 1)) {
            return $results;
        }
        try {
            $buckets = $this->client->listBuckets();
            $results = $buckets['Buckets'];

            if ($getHtml) {
                $html = '';
                $config = $this->config;
                if (!empty($results)) {
                    foreach ($results as $bucket) {
                        if (isset($config['awsBucketName']) && $config['awsBucketName'] === $bucket['Name']) {
                            $html .= '<tr class="row_bucket bucket-selected" data-bucket="' . esc_attr($bucket['Name']) . '">';
                        } else {
                            $html .= '<tr class="row_bucket wpfd-aws3-select-bucket" data-bucket="' . esc_attr($bucket['Name']) . '">';
                        }

                        $html .= '<td style="width: 30%">' . esc_html($bucket['Name']) . '</td>';
                        $html .= '<td style="width: 30%">' . esc_html($bucket['CreationDate']) . '</td>';
                        if (isset($config['awsBucketName']) && $config['awsBucketName'] === $bucket['Name']) {
                            $html .= '<td style="width: 30%"><label class="btn-select-bucket">' . esc_html__('Selected bucket', 'wpfdAddon') . '</label></td>';
                        } else {
                            $html .= '<td style="width: 30%"><label class="btn-select-bucket">' . esc_html__('Select bucket', 'wpfdAddon') . '</label></td>';
                        }
                        $html .= '<td style="width: 10%"><a class="delete-bucket" data-alt="' . esc_html__('Delete bucket', 'wpfdAddon') . '" data-bucket="' . esc_attr($bucket['Name']) . '"><i class="material-icons"> delete_outline </i></a></td>';
                        $html .= '</tr>';
                    }
                }
                wp_send_json(array('success' => true, 'html' => $html, 'buckets' => $results));
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
        }

        return $results;
    }

    /**
     * Select bucket
     *
     * @param string $bucket Bucket name
     *
     * @return array
     */
    public function selectBucket($bucket)
    {
        $config = $this->config;
        $config['awsBucketName'] = $bucket;
        // Get the region of the bucket
        try {
            $args = array('Bucket' => $bucket);
            $location = $this->client->getBucketLocation($args);
            $region   = empty($location['LocationConstraint']) ? $config['awsRegion'] : $location['LocationConstraint'];
            $region   = strip_tags($region);
            $config['awsRegion'] = $region;

            WpfdAddonHelper::saveAwsConfigs($config);
            
            return array('success' => true, 'region' => $region, 'region_name' => $this->regions[$region]);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return array('success' => false, 'msg' => $e->getMessage());
        }
    }

    /**
     * Delete bucket
     *
     * @param string $bucket Bucket name
     *
     * @return array
     */
    public function deleteBucket($bucket)
    {
        try {
            $this->client->deleteBucket(array(
                'Bucket' => $bucket
            ));

            return array('success' => true);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return array('success' => false, 'msg' => $e->getMessage());
        }
    }

    /**
     * Get bucket location
     *
     * @param string $bucket Bucket name
     *
     * @return array
     */
    public function getBucketLocation($bucket)
    {
        $config = $this->config;
        try {
            $args = array('Bucket' => $bucket);
            $location = $this->client->getBucketLocation($args);
            $region   = empty($location['LocationConstraint']) ? $config['awsRegion'] : $location['LocationConstraint'];
            $region   = strip_tags($region);
            
            return array('success' => true, 'region' => $region);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return array('success' => false, 'msg' => $e->getMessage());
        }
    }

    /**
     * Get all folders and files in a folder
     *
     * @param array $params Params list
     *
     * @return array
     */
    public function getFoldersFilesFromBucket($params)
    {
        $results = array();
        try {
            if (!isset($params['Delimiter'])) {
                $objects = $this->client->listObjectsV2($params);
                if (!empty($objects) && isset($objects['Contents'])) {
                    $results  = $objects['Contents'];
                }
            } else {
                $objects = $this->client->listObjects($params);
                if (!empty($objects) && isset($objects['CommonPrefixes'])) {
                    $results = $objects['CommonPrefixes'];
                }
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
        }

        return $results;
    }

    /**
     * Get information about the specific file using the object key
     *
     * @param array $params Params list
     *
     * @return array
     */
    public function getObjectInfo($params)
    {
        $result = array();
        try {
            $objectInfo = $this->client->getObject($params);
            $uri = $this->client->getObjectUrl($params['Bucket'], $params['Key']);
            $result = array(
                'uri' => $uri,
                'ContentLength' => $objectInfo['ContentLength'],
                'LastModified' => $objectInfo['LastModified'],
                'Key' => $params['Key']
            );
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return $result;
    }

    /**
     * Upload file to bucket
     *
     * @param array $params Params list
     *
     * @return boolean
     */
    public function uploadObject($params)
    {
        $result = array();
        if (!empty($params) && isset($params['type'])) {
            if ($params['type'] === 'folder') {
                try {
                    if ($params['parent'] === 0) {
                        $path = trim($params['title']);
                    } else {
                        $baseFolderId = $params['parent'];
                        $pathFolder   = WpfdAddonHelper::getPathByAwsId($baseFolderId);
                        $path = $pathFolder . '/' . trim($params['title']);
                    }

                    $args = array(
                        'Bucket' => $this->config['awsBucketName'],
                        'Key'    => $path . '/',
                        'Body'   => ''
                    );

                    $this->client->putObject($args);

                    $result['path'] = $path;
                    return $result;
                } catch (Exception $e) {
                    $this->lastError = $e->getMessage();

                    return false;
                }
            } elseif ($params['type'] === 'file') {
                $folderId = $params['id_category'];
                $pathFolder = WpfdAddonHelper::getPathByAwsId($folderId);
                $awsFilePath = $pathFolder . '/' . $params['file_name'];
                $sourceFilePath = $params['file_path'];
                try {
                    $args = array(
                        'Bucket' => $this->config['awsBucketName'],
                        'Key'    => $awsFilePath,
                        'Body'   => fopen($sourceFilePath, 'r')
                    );
                    $this->client->putObject($args);
                    
                    $result['id'] = md5($awsFilePath);
                    $result['aws_path'] = $awsFilePath;

                    return $result;
                } catch (Exception $e) {
                    $this->lastError = $e->getMessage();

                    return false;
                }
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * Delete object from bucket.
     *
     * @param array $params Params list
     *
     * @return boolean
     */
    public function deleteObject($params)
    {
        if (!empty($params) && isset($params['type'])) {
            if ($params['type'] === 'folder') {
                $args = array(
                    'Bucket' => $this->config['awsBucketName'],
                    'Key'    => $params['path'] . '/',
                );
                try {
                    $objects = $this->client->listObjects([
                        'Bucket' => $this->config['awsBucketName'],
                        'Prefix' => $params['path'] . '/',
                    ]);

                    // Delete each object in the folder
                    if (!empty($objects) && !empty($objects['Contents'])) {
                        foreach ($objects['Contents'] as $object) {
                            $this->client->deleteObject([
                                'Bucket' => $this->config['awsBucketName'],
                                'Key'    => $object['Key']
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    $this->lastError = $e->getMessage();

                    return false;
                }
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * Get list AWS files
     *
     * @param string  $folder_path Folder path on AWS
     * @param integer $idCategory  Category id
     * @param string  $ordering    Ordering
     * @param string  $direction   Ordering direction
     * @param boolean $listIdFlies List id files
     *
     * @return array|boolean
     */
    public function listAwsFiles($folder_path, $idCategory, $ordering = 'ordering', $direction = 'asc', $listIdFlies = false)
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
        $folder_path = $folder_path.'/';
        $args = array(
            'Bucket' => $this->config['awsBucketName'],
            'Prefix' => $folder_path
        );
        $objects = $this->getFoldersFilesFromBucket($args);
        if (empty($objects)) {
            return false;
        }

        foreach ($objects as $object) {
            if (strpos($object['Key'], '/', strlen($folder_path)) === false) {
                $key = $object['Key'];
                $hashKey = md5($key);
                $info = pathinfo($key);
                if (!empty($info['extension']) && $object['Size'] > 0) {
                    $file             = new stdClass();
                    $file->id         = $key;
                    $file->ID         = rawurlencode($key);
                    $file->title      = rawurldecode($info['filename']);
                    $file->s3title    = rawurldecode($info['filename']);
                    $file->post_title = $file->title;
                    $file->post_name  = $file->title;
                    if (!empty($info['extension'])) {
                        $file->ext = $info['extension'];
                    } else {
                        $file->ext = '';
                    }
                    $file->size = $object['Size'];
                    if (!empty($object['LastModified'])) {
                        $file->created_time = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($object['LastModified'])));
                        $file->created      = mysql2date($dateFormat, $file->created_time);
                    } else {
                        $file->created_time = '';
                        $file->created = '';
                    }

                    if (!empty($rewrite_rules)) {
                        if (strrpos($idFolderTitle, '/') !== false) {
                            $idFolderTitle = substr($idFolderTitle, strrpos($idFolderTitle, '/') + 1);
                        }
                        if (strpos($perlink, 'index.php')) {
                            $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $idCategory . '/';
                            $linkdownload .= $idFolderTitle . '/' . (!$rmdownloadext ? $hashKey : $info['extension']) . '/' . $info['filename'];
                        } else {
                            $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $idCategory . '/';
                            $linkdownload .= $idFolderTitle . '/' . (!$rmdownloadext ? $hashKey : $info['extension']) . '/' . $info['filename'];
                        }
                        $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $info['extension'] : '');
                    } else {
                        $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task';
                        $linkdownload .= '=file.download&wpfd_category_id=' . $idCategory . '&wpfd_file_id=' . $key;
                    }

                    $file->modified_time    = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($object['LastModified'])));
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
                    $config                 = get_option('_wpfdAddon_aws_fileInfo');
                    if (!empty($config) && isset($config[$idCategory]) && isset($config[$idCategory][$object['Key']])) {
                        if (isset($config[$idCategory][$object['Key']]['title'])) {
                            $file->title     = $config[$idCategory][$object['Key']]['title'];
                            $file->post_title = $file->title;
                            $file->post_name  = $file->title;
                        }
                        $file->description   = isset($config[$idCategory][$object['Key']]['description']) ?
                            $config[$idCategory][$object['Key']]['description'] : '';
                        $file->version       = isset($config[$idCategory][$object['Key']]['version']) ?
                            $config[$idCategory][$object['Key']]['version'] : '';
                        $file->versionNumber = $file->version;
                        $file->hits          = isset($config[$idCategory][$object['Key']]['hits']) ?
                            $config[$idCategory][$object['Key']]['hits'] : 0;
                        $file->file_tags     = isset($config[$idCategory][$object['Key']]['file_tags']) ?
                            $config[$idCategory][$object['Key']]['file_tags'] : '';
                        $file->state = isset($config[$idCategory][$object['Key']]['state']) ?
                            $config[$idCategory][$object['Key']]['state'] : '1';
                        $file->canview = isset($config[$idCategory][$object['Key']]['canview']) ?
                            $config[$idCategory][$object['Key']]['canview'] : '';
                        $file->file_password = isset($config[$idCategory][$object['Key']]['file_password']) ?
                            $config[$idCategory][$object['Key']]['file_password'] : '';
                        $file->file_custom_icon = isset($config[$idCategory][$object['Key']]['file_custom_icon']) ?
                            $config[$idCategory][$object['Key']]['file_custom_icon'] : '';
                    }

                    $file->catid = $idCategory;
                    $files[]     = apply_filters('wpfda_file_info', $file, $this->type);
                    unset($file);
                }
            }
        }
        $files = $this->subvalSort($files, $ordering, $direction);

        return $files;
    }

    /**
     * Get AWS file info
     *
     * @param string  $objectKey  Object key
     * @param string  $folderPath Folder name
     * @param integer $idCategory Term id
     * @param string  $token      Token key
     *
     * @return   array|boolean
     * @internal param $id
     * @internal param null $cloud_id
     */
    public function getAwsFileInfos($objectKey, $folderPath, $idCategory, $token = '')
    {
        if (!class_exists('WpfdBase')) {
            include_once WPFD_PLUGIN_DIR_PATH . '/app/admin/classes/WpfdBase.php';
        }

        $objectKey = stripcslashes(html_entity_decode($objectKey));
        $args = array(
            'Bucket' => $this->config['awsBucketName'],
            'Key'    => $objectKey
        );
        $hashKey = md5($objectKey);
        $objectInfo = $this->getObjectInfo($args);
        if (empty($objectInfo)) {
            return false;
        }

        //to fix special characters in file name like german "Umlaute"
        setlocale(LC_ALL, 'en_US.UTF-8');
        $info  = pathinfo($objectInfo['uri']);
        $fsize = $objectInfo['ContentLength'];

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

        $idFolderTitle    = substr($folderPath, 0);
        if (empty($config) || !isset($config['rmdownloadext'])) {
            $rmdownloadext = false;
        } else {
            $rmdownloadext = ((int) $config['rmdownloadext'] === 1) ? true : false;
        }
        if (!empty($rewrite_rules)) {
            if (strrpos($idFolderTitle, '/') !== false) {
                $idFolderTitle = substr($idFolderTitle, strrpos($idFolderTitle, '/') + 1);
            }
            if (strpos($perlink, 'index.php')) {
                $linkdownload = get_site_url() . '/index.php/' . $seo_uri . '/' . $idCategory . '/';
                $linkdownload .= $idFolderTitle . '/' . (!$rmdownloadext ? $hashKey : $info['extension']) . '/' . $info['filename'];
            } else {
                $linkdownload = get_site_url() . '/' . $seo_uri . '/' . $idCategory . '/' . $idFolderTitle;
                $linkdownload .= '/' . (!$rmdownloadext ? $hashKey : $info['extension']) . '/' . $info['filename'];
            }
            $linkdownload = $linkdownload . (!$rmdownloadext ? '.' . $info['extension'] : '');
        } else {
            $linkdownload = admin_url('admin-ajax.php') . '?juwpfisadmin=false&action=wpfd&task=file.download';
            $linkdownload .= '&wpfd_category_id=' . $idCategory . '&wpfd_file_id=' . $objectKey;
        }

        $data                  = array();
        $data['linkdownload']  = $linkdownload;
        $data['id']            = $objectInfo['Key'];
        $data['ID']            = rawurlencode($objectInfo['Key']);
        $data['state']         = isset($objectInfo['state']) ? (string) $objectInfo['state'] : '1';
        $data['catid']         = $idCategory;
        $data['title']         = urldecode($info['filename']);
        $data['s3title']       = urldecode($info['filename']);
        $data['post_title']    = $data['title'];
        $data['file']          = $info['basename'];
        $data['ext']           = $info['extension'];
        $data['created_time']  = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($objectInfo['LastModified'])));
        $data['modified_time'] = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($objectInfo['LastModified'])));
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
                    $objectInfo['Key'],
                    $idCategory,
                    $info['extension'],
                    $token
                ) : WpfdHelperFile::getViewerUrl($objectInfo['Key'], $idCategory, $token);
        }
        $config = get_option('_wpfdAddon_aws_fileInfo');
        if (!empty($config) && isset($config[$idCategory]) && isset($config[$idCategory][$objectInfo['Key']])) {
            if (isset($config[$idCategory][$objectInfo['Key']]['title'])) {
                $data['title']               = $config[$idCategory][$objectInfo['Key']]['title'];
                $data['post_title']          = $data['title'];
            }
            $data['description']             = isset($config[$idCategory][$objectInfo['Key']]['description']) ?
                $config[$idCategory][$objectInfo['Key']]['description'] : '';
            $data['version']                 = isset($config[$idCategory][$objectInfo['Key']]['version']) ?
                $config[$idCategory][$objectInfo['Key']]['version'] : '';
            $data['versionNumber']           = $data['version'];
            $data['latestVersionNumber'] = isset($config[$idCategory][$objectInfo['Key']]['latestVersionNumber']) ?
                $config[$idCategory][$objectInfo['Key']]['latestVersionNumber'] : false;
            $data['hits']                    = isset($config[$idCategory][$objectInfo['Key']]['hits']) ?
                $config[$idCategory][$objectInfo['Key']]['hits'] : 0;
            $data['social']                  = isset($config[$idCategory][$objectInfo['Key']]['social']) ?
                $config[$idCategory][$objectInfo['Key']]['social'] : 0;
            $data['file_tags']               = isset($config[$idCategory][$objectInfo['Key']]['file_tags']) ?
                $config[$idCategory][$objectInfo['Key']]['file_tags'] : '';
            $data['file_multi_category']     = isset($config[$idCategory][$objectInfo['Key']]['file_multi_category']) ?
                $config[$idCategory][$objectInfo['Key']]['file_multi_category'] : '';
            $data['file_multi_category_old'] = isset($config[$idCategory][$objectInfo['Key']]['file_multi_category_old']) ?
                $config[$idCategory][$objectInfo['Key']]['file_multi_category_old'] : '';
            $data['file_custom_icon'] = isset($config[$idCategory][$objectInfo['Key']]['file_custom_icon']) ?
                $config[$idCategory][$objectInfo['Key']]['file_custom_icon'] : '';
            if (isset($config[$idCategory][$objectInfo['Key']]['canview'])) {
                $data['canview'] = $config[$idCategory][$objectInfo['Key']]['canview'];
            }
            if (isset($config[$idCategory][$objectInfo['Key']]['file_password'])) {
                $data['file_password'] = $config[$idCategory][$objectInfo['Key']]['file_password'];
            }
        }
        if (isset($config[$idCategory][$objectInfo['Key']]['state'])) {
            $data['state']         = (string) $config[$idCategory][$objectInfo['Key']]['state'] ;
        }
        $data = apply_filters('wpfda_file_info', $data, $this->type);

        return $data;
    }

    /**
     * Change file title
     *
     * @param array  $datas         File info
     * @param string $awsFolderPath AWS ID
     *
     * @return boolean
     */
    public function saveAwsFileInfos($datas, $awsFolderPath)
    {
        if (!isset($datas['id'])) {
            return false;
        }
        $objectKey = $datas['id'];

        if (isset($datas['file_tags']) && $datas['file_tags'] !== '') {
            $file_tags = explode(',', $datas['file_tags']);
            $awsTags = array();
            foreach ($file_tags as $key => $value) {
                $awsTags[] = array('Key' => $value, 'Value' => '');
            }

            try {
                // Add tags to the S3 object
                $result = $this->client->putObjectTagging([
                    'Bucket' => $this->config['awsBucketName'],
                    'Key'    => $objectKey,
                    'Tagging' => ['TagSet' => $awsTags]
                ]);
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                return false;
            }
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
    public function saveAwsVersion($datas)
    {
        try {
            $args = array(
                'Bucket' => $this->config['awsBucketName'],
                'Key'    => $datas['id'],
                'Body'   => $datas['data']
            );
            $result = $this->client->putObject($args);

            return $result;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Get list versions
     *
     * @param string $objectKey Object key
     *
     * @return array
     */
    public function getListVersions($objectKey)
    {
        try {
            $result = $this->client->listObjectVersions([
                'Bucket' => $this->config['awsBucketName'],
                'Prefix' => rawurldecode($objectKey),
            ]);

            $versions = array();
            foreach ($result['Versions'] as $version) {
                if (!$version['IsLatest']) {
                    $versions[] = $version;
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
     * @param string $objectKey Object key
     * @param string $versionId Version Id
     *
     * @return boolean
     */
    public function restoreVersion($objectKey, $versionId)
    {
        if (empty($objectKey) || empty($versionId)) {
            return false;
        }

        try {
            // Change current version
            $this->client->copyObject([
                'Bucket'     => $this->config['awsBucketName'],
                'Key'        => $objectKey,
                'CopySource' => $this->config['awsBucketName'] . '/' . $objectKey . '?versionId=' . $versionId
            ]);

            // Delete the old version
            $this->client->deleteObject([
                'Bucket' => $this->config['awsBucketName'],
                'Key'    => $objectKey,
                'VersionId' => $versionId
            ]);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Delete file version
     *
     * @param string $objectKey Object key
     * @param string $versionId Version Id
     *
     * @return boolean
     */
    public function deleteVersion($objectKey, $versionId)
    {
        if (empty($objectKey) || empty($versionId)) {
            return false;
        }

        try {
            // Delete the old version
            $this->client->deleteObject([
                'Bucket' => $this->config['awsBucketName'],
                'Key'    => $objectKey,
                'VersionId' => $versionId
            ]);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Delete a file AWS
     *
     * @param string $objectKey Object key
     *
     * @return boolean
     */
    public function deleteFileAws($objectKey)
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->config['awsBucketName'],
                'Key'    => $objectKey
            ]);

            $awsUpload = get_option('_wpfdAddon_aws_file_user_upload') ? get_option('_wpfdAddon_aws_file_user_upload') : array();
            if (!empty($awsUpload)) {
                if (array_key_exists($objectKey, $awsUpload)) {
                    unset($awsUpload[$objectKey]);
                    update_option('_wpfdAddon_aws_file_user_upload', $awsUpload);
                }
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * Rename Folder AWS
     *
     * @param string $objectKey Object key
     * @param string $newKey    New object key
     *
     * @return boolean
     */
    public function changeAwsFilename($objectKey, $newKey)
    {
        $oldKey = $objectKey;
        try {
            // Copy the object to a new key
            $oldObjects = $this->client->listObjects([
                'Bucket' => $this->config['awsBucketName'],
                'Prefix' => $oldKey . '/',
            ]);

            // Copy each object to the destination folder
            foreach ($oldObjects['Contents'] as $object) {
                $destinationKey = str_replace($oldKey, $newKey, $object['Key']);
                try {
                    $this->client->copyObject([
                        'Bucket'     => $this->config['awsBucketName'],
                        'Key'        => $destinationKey,
                        'CopySource' => $this->config['awsBucketName'] . '/' . rawurlencode($object['Key'])
                    ]);
                } catch (Exception $e) {
                    $this->lastError = $e->getMessage();
                }
            }

            // Delete the old object
            foreach ($oldObjects['Contents'] as $object) {
                try {
                    $this->client->deleteObject([
                        'Bucket' => $this->config['awsBucketName'],
                        'Key'    => $object['Key']
                    ]);
                } catch (Exception $e) {
                    $this->lastError = $e->getMessage();
                }
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Download file AWS
     *
     * @param string  $objectKey Object key
     * @param integer $catid     Term ID
     * @param string  $versionId Version ID
     *
     * @return boolean|void
     */
    public function downloadAws($objectKey, $catid, $versionId = false)
    {
        try {
            $params = [
                'Bucket' => $this->config['awsBucketName'],
                'Key' => $objectKey
            ];

            if ($versionId) {
                $params['VersionId'] = $versionId;
            }

            // Get the object from S3
            $result = $this->client->getObject($params);

            $fileName = basename($objectKey);

            $config = get_option('_wpfdAddon_aws_fileInfo');
            if (!empty($config) && isset($config[$catid]) && isset($config[$catid][$objectKey])) {
                if (isset($config[$catid][$objectKey]['title'])) {
                    $fileTitle = $config[$catid][$objectKey]['title'];
                    $info = pathinfo($objectKey);
                    $fileName = $fileTitle .'.'. $info['extension'];
                }
            }

            // Set the appropriate headers for the browser to recognize it as a download
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . $result['ContentLength']);

            // Output the file content to the browser
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Print file content as is
            echo $result['Body']->getContents();
            return true;
        } catch (AwsException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
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
            $bucketName = $this->config['awsBucketName'];

            if (isset($filters['catid'])) {
                if ($filters['catid'] === 0) {
                    $pathFolder = '';
                    unset($filters['catid']);
                } else {
                    if (is_numeric($filters['catid'])) {
                        $pathFolder = WpfdAddonHelper::getPathByAwsId($filters['catid']) . '/';
                    } elseif ($filters['catid'] !== '' && is_string($filters['catid'])) {
                        $pathFolder = $filters['catid'] . '/';
                    }
                }
            }

            if (isset($filters['catid'])) {
                // Filter objects based on the keyword
                if (isset($filters['q'])) {
                    $keywords = explode(',', $filters['q']);
                    if (count($keywords)) {
                        foreach ($keywords as $keyword) {
                            $keyword = trim($keyword);
                            $this->listObjectsRecursively($keyword, $bucketName, $pathFolder, $listfiles);
                        }
                    }
                } else {
                    try {
                        $args = array(
                            'Bucket' => $bucketName,
                            'Prefix' => $pathFolder
                        );
                        $objects = $this->client->listObjectsV2($args);

                        if (!empty($objects['Contents'])) {
                            foreach ($objects['Contents'] as $object) {
                                if (substr($object['Key'], -1) !== '/') {
                                    $listfiles[] = $object;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $this->lastError = $e->getMessage();
                        return array();
                    }
                }
            } else {
                if (isset($filters['q'])) {
                    $keywords = explode(',', $filters['q']);
                    if (count($keywords)) {
                        foreach ($keywords as $keyword) {
                            foreach ($keywords as $keyword) {
                                $keyword = trim($keyword);
                                $pathFolder = '';
                                $this->listObjectsRecursively($keyword, $bucketName, $pathFolder, $listfiles);
                            }
                        }
                    }
                } else {
                    try {
                        $args = array(
                            'Bucket' => $bucketName
                        );
                        $objects = $this->client->listObjectsV2($args);

                        if (!empty($objects['Contents'])) {
                            foreach ($objects['Contents'] as $object) {
                                if (substr($object['Key'], -1) !== '/') {
                                    $listfiles[] = $object;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $this->lastError = $e->getMessage();
                        return array();
                    }
                }
            }

            $result = $this->displayFileSearch($listfiles);

            return $result;
        } catch (AwsException $e) {
            $this->lastError = $e->getMessage();

            return array();
        }
    }

    /**
     * Recursive function to list objects in a folder and its subfolders
     *
     * @param string $keyword    The keyword to search for in file names.
     * @param string $bucketName The S3 bucket name.
     * @param string $prefix     The folder path prefix.
     * @param array  $objects    Reference to an array to store matching objects.
     *
     * @return array
     */
    public function listObjectsRecursively($keyword, $bucketName, $prefix, &$objects)
    {
        $result = array();
        try {
            $args = array(
                'Bucket' => $bucketName
            );
            if ($prefix !== '') {
                $args['Prefix'] = $prefix;
            }

            do {
                $result = $this->client->listObjectsV2($args);

                foreach ($result['Contents'] as $object) {
                    if (!empty($object['Key']) && substr($object['Key'], -1) !== '/') {
                        // Exclude objects representing folders
                        $infoFile = pathinfo($object['Key']);
                        if (strpos(strtolower($infoFile['filename']), strtolower($keyword)) !== false) {
                            // Check if the keyword is present in the object key
                            $objects[] = $object;
                        }
                    }
                }

                if (!empty($result['CommonPrefixes'])) {
                    foreach ($result['CommonPrefixes'] as $commonPrefix) {
                        $this->listObjectsRecursively($bucketName, $commonPrefix['Prefix'], $objects);
                    }
                }

                // Check if there are more objects to retrieve
                $args['ContinuationToken'] = isset($result['NextContinuationToken']) ? $result['NextContinuationToken'] : null;
            } while (isset($args['ContinuationToken']));
        } catch (AwsException $e) {
            $this->lastError = $e->getMessage();

            return array();
        }
    }

    /**
     * Change AWS order
     *
     * @param string $move       Move path
     * @param string $location   New location path
     * @param string $parent     Parent
     * @param string $folderName Folder name
     *
     * @return boolean|mixed
     */
    public function changeAwsOrder($move, $location, $parent, $folderName)
    {
        try {
            $bucketName = $this->config['awsBucketName'];
            $sourceFolder = $move;
            if ($parent !== 0) {
                $destinationFolder = $location . '/' . $folderName;
            } else {
                $destinationFolder = $folderName;
            }

            // List objects in the source folder
            $objects = $this->client->listObjectsV2([
                'Bucket' => $bucketName,
                'Prefix' => $sourceFolder . '/',
            ]);

            // Copy each object to the destination folder
            foreach ($objects['Contents'] as $object) {
                $sourceKey = $object['Key'];
                $destinationKey = str_replace($sourceFolder, $destinationFolder, $sourceKey);

                // Copy the object
                try {
                    $this->client->copyObject([
                        'Bucket'     => $bucketName,
                        'CopySource' => $bucketName . '/' . rawurlencode($sourceKey),
                        'Key'        => $destinationKey,
                    ]);
                } catch (Exception $e) {
                    $this->lastError = $e->getMessage();
                }

                // Optionally, delete the original object
                try {
                    $this->client->deleteObject([
                        'Bucket' => $bucketName,
                        'Key'    => $sourceKey,
                    ]);
                } catch (Exception $e) {
                    $this->lastError = $e->getMessage();
                }
            }

            return $destinationFolder;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
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
                $infoFile = pathinfo($f['Key']);
                if (!empty($infoFile['extension']) && $f['Size'] > 0) {
                    $termID             = WpfdAddonHelper::getTermIdByAwsPath($infoFile['dirname']);
                    $file               = new stdClass();
                    $file->id           = $f['Key'];
                    $file->ID           = rawurlencode($f['Key']);
                    $file->title        = urldecode($infoFile['filename']);
                    $file->s3title      = urldecode($infoFile['filename']);
                    $file->post_title   = $file->title;
                    $file->post_name    = $file->title;
                    $file->ext          = $infoFile['extension'];
                    $file->size         = $f['Size'];
                    $file->dirname      = $infoFile['dirname'];
                    $file->created_time = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($f['LastModified'])));
                    $file->created      = mysql2date($dateFormat, $file->created_time);
                    $file->modified_time= get_date_from_gmt(date('Y-m-d H:i:s', strtotime($f['LastModified'])));
                    $file->modified     = mysql2date($dateFormat, $file->modified_time);
                    $file->file_tags    = '';
                    $file->hits         = 0;
                    $file->version      = '';
                    $file->versionNumber= '';

                    $config = WpfdAddonHelper::getAwsFileInfos();
                    if (!empty($config) && isset($config[$termID]) && isset($config[$termID][$f['Key']])) {
                        $file->file_tags     = isset($config[$termID][$f['Key']]['file_tags']) ?
                            $config[$termID][$f['Key']]['file_tags'] : '';
                        $file->file_password = isset($config[$termID][$f['Key']]['file_password']) ?
                            $config[$termID][$f['Key']]['file_password'] : '';
                        $file->state         = isset($config[$termID][$f['Key']]['state']) ?
                            $config[$termID][$f['Key']]['state'] : '1';
                        $file->hits          = isset($config[$termID][$f['Key']]['hits']) ?
                            $config[$termID][$f['Key']]['hits'] : 0;
                        $file->version       = isset($config[$termID][$f['Key']]['version']) ?
                            $config[$termID][$f['Key']]['version'] : 0;
                        $file->versionNumber = $file->version;
                    }
                    $result[] = $file;
                }
            }
        }

        return $result;
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
        $fileInfos = WpfdAddonHelper::getAwsFileInfos();
        if (!empty($fileInfos)) {
            $hits = isset($fileInfos[$catId][$fileId]['hits']) ? intval($fileInfos[$catId][$fileId]['hits']) : 0;
            $fileInfos[$catId][$fileId]['hits'] = $hits + 1;
        } else {
            $fileInfos = array();
            $fileInfos[$catId][$fileId]['hits'] = 1;
        }
        WpfdAddonHelper::setAwsFileInfos($fileInfos);
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

    /**
     * Create feature image for Woo product
     *
     * @param string $objectKey    Object key
     * @param string $previewsPath Previews folder path on server
     *
     * @return boolean|string
     */
    public function getAwsImg($objectKey, $previewsPath)
    {
        try {
            $params = array(
                'Bucket' => $this->config['awsBucketName'],
                'Key' => $objectKey,
            );
            $result = $this->client->getObject($params);

            $contentType = $result['ContentType'];
            if (strpos($contentType, 'image/') === 0) {
                $imageContent = $result['Body']->getContents();
                $fileName = md5($objectKey);
                $filePath = $previewsPath . 'aws_' . strval($fileName) . '.png';
                file_put_contents($filePath, $imageContent);
                if (file_exists($filePath)) {
                    $now = new DateTime();
                    $fileUpdate = $now->format('Y-m-d\TH:i:s.v\Z');
                    $filePath = str_replace(WP_CONTENT_DIR, '', $filePath);
                    update_option('_wpfdAddon_preview_info_' . $fileName, array('updated' => $fileUpdate, 'path' => $filePath));
                    return $filePath;
                }
            }
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
        }

        return false;
    }
}
