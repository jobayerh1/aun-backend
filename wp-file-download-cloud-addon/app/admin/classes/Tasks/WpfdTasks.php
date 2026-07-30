<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\BackgroundTasks;

use Google\Exception;
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Utilities;

// no direct access

defined('ABSPATH') || die();

/**
 * Class WpfdTasks
 */
class WpfdTasks extends WpfdTasksAbstract
{
    /**
     * Google drive instance
     *
     * @var \WpfdAddonGoogleDrive
     */
    protected $googleDrive = null;

    /**
     * Google team drive instance
     *
     * @var \WpfdAddonGoogleTeamDrive
     */
    protected $googleTeamDrive = null;

    /**
     * Onedrive instance
     *
     * @var \WpfdAddonOneDrive
     */
    protected $oneDrive = null;

    /**
     * Onedrive Business instance
     *
     * @var \WpfdAddonOneDriveBusiness
     */
    protected $oneDriveBusiness = null;

    /**
     * Dropbox instance
     *
     * @var \WpfdAddonDropbox
     */
    protected $dropbox = null;

    /**
     * AWS instance
     *
     * @var \WpfdAddonAws
     */
    protected $aws = null;

    /**
     * Nextcloud instance
     *
     * @var \WpfdAddonNextcloud
     */
    protected $nextcloud = null;

    /**
     * Google config name
     *
     * @var string
     */
    private $googleOptionName = '_wpfdAddon_cloud_config';

    /**
     * Google team drive config name
     *
     * @var string
     */
    private $googleTeamDriveOptionName = '_wpfdAddon_cloud_team_drive_config';

    /**
     * Onedrive config name
     *
     * @var string
     */
    private $oneDriveOptionName = '_wpfdAddon_onedrive_config';

    /**
     * Onedrive Business config name
     *
     * @var string
     */
    private $oneDriveBusinessOptionName = '_wpfdAddon_onedrive_business_config';

    /**
     * Dropbox config name
     *
     * @var string
     */
    private $dropboxOptionName = '_wpfdAddon_dropbox_config';

    /**
     * AWS config name
     *
     * @var string
     */
    private $awsOptionName = '_wpfdAddon_aws_config';

    /**
     * Nextcloud config name
     *
     * @var string
     */
    private $nextcloudOptionName = '_wpfdAddon_nextcloud_config';

    /**
     * Google base folder id
     *
     * @var string
     */
    private $googleBaseFolderId = '';

    /**
     * Google team base folder id
     *
     * @var string
     */
    private $googleTeamDriveBaseFolderId = '';

    /**
     * Onedrive base folder id
     *
     * @var string
     */
    private $oneDriveBaseFolderId = '';

    /**
     * Onedrive Business base folder id
     *
     * @var string
     */
    private $oneDriveBusinessBaseFolderId = '';

    /**
     * Dropbox base folder id
     *
     * @var string
     */
    private $dropboxBaseFolderId = '';

    /**
     * AWS base folder id
     *
     * @var string
     */
    private $awsBaseFolderId = '';

    /**
     * Nextcloud base folder id
     *
     * @var string
     */
    private $nextcloudBaseFolderId = '';

    /**
     * Is generate preview turn on?
     *
     * @var boolean
     */
    private $generatePreview = false;

    /**
     * Preview generator whitelist extensions
     *
     * @var array
     */
    private $previewAllowed = array('ai', 'csv', 'doc', 'docx', 'html', 'json', 'odp', 'ods', 'pdf', 'ppt', 'pptx', 'rtf', 'sketch', 'xd', 'xls', 'xlsx', 'xml');
    /**
     * WpfdTasks constructor.
     */
    public function __construct()
    {
        $config = get_option('_wpfd_global_config', false);
        $this->generatePreview = $config && isset($config['auto_generate_preview']) && intval($config['auto_generate_preview']) === 1;

        /**
         * The preview file extensions whitelist for cloud files
         *
         * @param array
         */
        $this->previewAllowed = apply_filters('wpfd_addon_preview_generator_whitelist_extensions', $this->previewAllowed);
        // Require addon files
        $this->loadAddons();
        $this->addActions();
        $this->addFilters();
        $this->scheduleCronJob();
    }

    /**
     * Load clouds
     *
     * @return void
     */
    public function loadAddons()
    {
        if (!class_exists('\WpfdAddonHelper')) {
            require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/helpers/WpfdHelper.php';
        }
        $googleOption = get_option($this->googleOptionName, false);
        if (false !== $googleOption) {
            if (isset($googleOption['googleCredentials']) && $googleOption['googleCredentials'] !== null && $googleOption['googleCredentials'] !== '') {
                // Google Drive activated
                if (!class_exists('WpfdAddonGoogleDrive')) {
                    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonGoogle.php';
                }
                // Initialization Google Drive
                $this->googleDrive = new \WpfdAddonGoogleDrive;
                $this->googleBaseFolderId = $googleOption['googleBaseFolder'];
                // Run this once to migrate to new sync method
                $this->mapGoogleDriveParamsToMetaData();
            }
        }

        $googleTeamDriveOption = get_option($this->googleTeamDriveOptionName, false);
        if (false !== $googleTeamDriveOption) {
            if (isset($googleTeamDriveOption['googleTeamDriveCredentials'])
                && $googleTeamDriveOption['googleTeamDriveCredentials'] !== null && $googleTeamDriveOption['googleTeamDriveCredentials'] !== '') {
                // Google Team Drive activated
                if (!class_exists('WpfdAddonGoogleTeamDrive')) {
                    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonGoogleTeamDrive.php';
                }
                // Initialization Google Team Drive
                $this->googleTeamDrive = new \WpfdAddonGoogleTeamDrive;
                $this->googleTeamDriveBaseFolderId = $googleTeamDriveOption['googleTeamDriveBaseFolder'];
                // Run this once to migrate to new sync method
                $this->mapGoogleTeamDriveParamsToMetaData();
            }
        }

        $onedriveOption = get_option($this->oneDriveOptionName, false);
        if (false !== $onedriveOption) {
            if (isset($onedriveOption['onedriveConnected']) && $onedriveOption['onedriveConnected'] === 1) {
                // Google Drive activated
                if (!class_exists('WpfdAddonOneDrive')) {
                    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonOneDrive.php';
                }

                // Initialization Google Drive
                $this->oneDrive = new \WpfdAddonOneDrive;
                $this->oneDriveBaseFolderId = \WpfdAddonHelper::replaceIdOneDrive($onedriveOption['onedriveBaseFolder']['id']);

                // Run this once to migrate to new sync method
                $this->mapOneDriveParamsToMetaData();
            }
        }

        $onedriveBusinessOption = get_option($this->oneDriveBusinessOptionName, false);
        if (false !== $onedriveBusinessOption) {
            if (isset($onedriveBusinessOption['connected']) && $onedriveBusinessOption['connected'] === 1) {
                // OneDrive Business activated
                if (!class_exists('WpfdAddonOneDriveBusiness')) {
                    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonOneDriveBusiness.php';
                }
                // Initialization OneDrive Business
                $this->oneDriveBusiness = new \WpfdAddonOneDriveBusiness;
                $this->oneDriveBusinessBaseFolderId = $onedriveBusinessOption['onedriveBaseFolder']['id'];
                // Run this once to migrate to new sync method
                $this->mapOneDriveBusinessParamsToMetaData();
            }
        }

        $dropboxOption = get_option($this->dropboxOptionName, false);
        if (false !== $dropboxOption) {
            if (isset($dropboxOption['dropboxAccessToken']) && !empty($dropboxOption['dropboxAccessToken'])) {
                // Dropbox activated
                if (!class_exists('WpfdAddonDropbox')) {
                    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonDropbox.php';
                }
                // Initialization OneDrive Business
                $this->dropbox = new \WpfdAddonDropbox;
                $this->dropboxBaseFolderId = $dropboxOption['dropboxBaseFolderId'];
                // Run this once to migrate to new sync method
                $this->mapDropboxParamsToMetaData();
            }
        }

        $awsOption = get_option($this->awsOptionName, false);
        if (false !== $awsOption) {
            if (isset($awsOption['awsConnected']) && $awsOption['awsConnected']) {
                // AWS activated
                if (!class_exists('WpfdAddonAws')) {
                    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonAws.php';
                }
                // Initialization AWS
                $this->aws = new \WpfdAddonAws;
                $this->awsBaseFolderId = $awsOption['awsBucketName'];
                // Run this once to migrate to new sync method
                $this->mapAwsParamsToMetaData();
            }
        }

        $nextcloudOption = get_option($this->nextcloudOptionName, false);
        if (false !== $nextcloudOption) {
            if (isset($nextcloudOption['nextcloudState']) && $nextcloudOption['nextcloudState']) {
                // Nextcloud activated
                if (!class_exists('WpfdAddonNextcloud')) {
                    require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonNextcloud.php';
                }
                // Initialization Nextcloud
                $this->nextcloud = new \WpfdAddonNextcloud;
                $this->nextcloudBaseFolderId = $nextcloudOption['nextcloudRootFolder'];
            }
        }
    }

    /**
     * Add actions
     *
     * @return void
     */
    public function addActions()
    {
        // Google Drive
        add_action('admin_init', array($this, 'addGoogleDriveRootToQueue'));
        add_action('wp_ajax_nopriv_google_sync_status', array($this, 'getGoogleSyncStatus'));
        add_action('wp_ajax_google_sync_status', array($this, 'getGoogleSyncStatus'));
        // Onedrive
        add_action('admin_init', array($this, 'addOnedriveRootToQueue'));
        add_action('wp_ajax_nopriv_onedrive_sync_status', array($this, 'getOnedriveSyncStatus'));
        add_action('wp_ajax_onedrive_sync_status', array($this, 'getOnedriveSyncStatus'));
        // Onedrive Business
        add_action('admin_init', array($this, 'addOnedriveBusinessRootToQueue'));
        add_action('wp_ajax_nopriv_onedrive_business_sync_status', array($this, 'getOnedriveBusinessSyncStatus'));
        add_action('wp_ajax_onedrive_business_sync_status', array($this, 'getOnedriveBusinessSyncStatus'));
        // Dropbox
        add_action('admin_init', array($this, 'addDropboxRootToQueue'));
        add_action('wp_ajax_nopriv_dropbox_sync_status', array($this, 'getDropboxSyncStatus'));
        add_action('wp_ajax_dropbox_sync_status', array($this, 'getDropboxSyncStatus'));
        // AWS
        add_action('admin_init', array($this, 'addAwsRootToQueue'));
        add_action('wp_ajax_nopriv_aws_sync_status', array($this, 'getAwsSyncStatus'));
        add_action('wp_ajax_aws_sync_status', array($this, 'getAwsSyncStatus'));
        // Google Team Drive
        add_action('admin_init', array($this, 'addGoogleTeamDriveRootToQueue'));
        add_action('wp_ajax_nopriv_google_team_drive_sync_status', array($this, 'getGoogleTeamDriveSyncStatus'));
        add_action('wp_ajax_google_team_drive_sync_status', array($this, 'getGoogleTeamDriveSyncStatus'));
        // Nextcloud
        add_action('admin_init', array($this, 'addNextcloudRootToQueue'));
        add_action('wp_ajax_nopriv_nextcloud_sync_status', array($this, 'getNextcloudSyncStatus'));
        add_action('wp_ajax_nextcloud_sync_status', array($this, 'getNextcloudSyncStatus'));
    }


    /**
     * Add filters
     *
     * @return void
     */
    public function addFilters()
    {
        // Google Drive
        add_filter('wpfd_sync_google_drive', array($this, 'doGoogleSync'), 10, 3);
        add_filter('wpfd_google_drive_remove', array($this, 'syncRemoveGoogleItems'), 10, 3);
        // Onedrive
        add_filter('wpfd_sync_onedrive', array($this, 'doOnedriveSync'), 10, 3);
        add_filter('wpfd_onedrive_remove', array($this, 'syncRemoveOnedriveItems'), 10, 3);
        // Onedrive Business
        add_filter('wpfd_sync_onedrive_business', array($this, 'doOnedriveBusinessSync'), 10, 3);
        add_filter('wpfd_onedrive_business_remove', array($this, 'syncRemoveOnedriveBusinessItems'), 10, 3);
        // Dropbox
        add_filter('wpfd_sync_dropbox', array($this, 'doDropboxSync'), 10, 3);
        add_filter('wpfd_sync_dropbox_single', array($this, 'addDropBoxSync'), 10);
        add_filter('wpfd_dropbox_remove', array($this, 'syncRemoveDropboxItems'), 10, 3);
        // AWS
        add_filter('wpfd_sync_aws', array($this, 'doAwsSync'), 10, 3);
        add_filter('wpfd_sync_aws_single', array($this, 'addAwsSync'), 10);
        add_filter('wpfd_aws_remove', array($this, 'syncRemoveAwsItems'), 10, 3);
        // Google Team Drive
        add_filter('wpfd_sync_google_team_drive', array($this, 'doGoogleTeamDriveSync'), 10, 3);
        add_filter('wpfd_google_team_drive_remove', array($this, 'syncRemoveGoogleTeamDriveItems'), 10, 3);
        // Nextcloud
        add_filter('wpfd_sync_nextcloud', array($this, 'doNextcloudSync'), 10, 3);
        add_filter('wpfd_sync_nextcloud_single', array($this, 'addNextcloudSync'), 10);
        add_filter('wpfd_nextcloud_remove', array($this, 'syncRemoveNextcloudItems'), 10, 3);

        // Generate previews
        add_filter('wpfd_download_cloud_thumbnail', array($this, 'wpfdAddonDownloadCloudThumbnail'), 10, 3);
    }
    /**
     * Generate thumbnail for cloud files
     *
     * @param boolean|integer $result     The result of current task
     * @param array           $datas      Data of the task
     * @param integer         $element_id The row id of this task in queue table
     *
     * @return boolean|integer
     */
    public function wpfdAddonDownloadCloudThumbnail($result, $datas, $element_id)
    {
        if (!isset($datas['cloudType'])) {
            return true;
        }
        if (isset($datas['url']) && $datas['url']) {
            /**
             * Filter to prevent generate preview for images/videos files
             *
             * @param array
             */
            if (in_array($datas['ext'], apply_filters('wpfd_addon_preview_generator_blacklist_extensions', array('png', 'jpg', 'jpeg', 'mp4', 'mpeg', 'mp3', 'avi')))) {
                return true;
            }
            $fileName = md5($datas['id']);
            $fileUpdate = isset($datas['updated']) ? $datas['updated'] : false;
            $previewInfo = get_option('_wpfdAddon_preview_info_' . $fileName, false);
            // Check update time
            if (!$fileUpdate || !$previewInfo  || ($previewInfo && is_array($previewInfo) && isset($previewInfo['updated']) && $fileUpdate !== $previewInfo['updated'])) {
                // Download the image
                Application::getInstance('Wpfd');
                $filePath = false;
                $previewsPath = \WpfdHelperFolder::getPreviewsPath();
                switch ($datas['cloudType']) {
                    case 'googleDrive':
                        $prefix = 'googleDrive_';
                        // Remove the size part in the URL to get max image size
                        $urlArr = explode('=s', $datas['url']);
                        $url = isset($urlArr[0]) ? $urlArr[0] : false;
                        $filePath = $previewsPath . $prefix . strval($fileName) . '.png';

                        // Try to using native WordPress download function
                        if (function_exists('download_url')) {
                            $tempFile = download_url($url);
                            if (!is_wp_error($tempFile)) {
                                rename($tempFile, $filePath);
                            }
                        } else {
                            $response = file_get_contents($url);
                            if ($response) {
                                file_put_contents($filePath, $response);
                            }
                        }
                        break;
                    case 'dropbox':
                        $prefix = 'dropbox_';
                        $savePath = $previewsPath . $prefix . strval($fileName) . '.png';
                        $dropbox = new \WpfdAddonDropbox();
                        $filePath = $dropbox->downloadThumbnail($datas['id'], $savePath);
                        break;
                    case 'onedrive':
                        $prefix = 'onedrive_';
                        $savePath = $previewsPath . $prefix . strval($fileName) . '.png';
                        $onedrive = new \WpfdAddonOnedrive;
                        try {
                            $filePath = $onedrive->downloadThumbnail($datas['id'], $savePath);
                        } catch (\Exception $e) {
                            $filePath = false;
                        }
                        break;
                    case 'onedrive_business':
                        $prefix = 'onedrive_business_';
                        $savePath = $previewsPath . $prefix . strval($fileName) . '.png';
                        $onedrive = new \WpfdAddonOnedriveBusiness;
                        try {
                            $filePath = $onedrive->downloadThumbnail($datas['id'], $savePath);
                        } catch (\Exception $e) {
                            $filePath = false;
                        }
                        break;
                    case 'aws':
                        $prefix = 'aws_';
                        $savePath = $previewsPath . $prefix . strval($fileName) . '.png';
                        $aws = new \WpfdAddonAws;
                        try {
                            $filePath = $aws->downloadThumbnail($datas['id'], $savePath);
                        } catch (\Exception $e) {
                            $filePath = false;
                        }
                        break;
                    case 'nextcloud':
                        $prefix = 'nextcloud_';
                        $savePath = $previewsPath . $prefix . strval($fileName) . '.png';
                        $nextcloud = new \WpfdAddonNextcloud;
                        try {
                            $filePath = $nextcloud->downloadThumbnail($datas['id'], $savePath);
                        } catch (\Exception $e) {
                            $filePath = false;
                        }
                        break;
                    default:
                        break;
                }
                if (false !== $filePath) {
                    if (file_exists($filePath)) {
                        /**
                         * Filter allow to do anything with the generated preview file
                         *
                         * @param string
                         *
                         * @ignore
                         */
                        $filePath = apply_filters('wpfd_generated_preview_file_real_path', $filePath);
                        $filePath = apply_filters('wpfd_preview_generated', $filePath, array('file_id' => $datas['id']), null);
                        $filePath = addslashes(str_replace(WP_CONTENT_DIR, '', $filePath));
                    }


                    update_option('_wpfdAddon_preview_info_' . $fileName, array('updated' => $fileUpdate, 'path' => $filePath));
                    return true;
                }
            }
        }

        return true;
    }
    /**
     * Add schedule intervals
     *
     * @param array $schedules Schedules list
     *
     * @return array
     */
    public function getSchedules($schedules)
    {
        if (!is_null($this->googleDrive)) {
            // Google Drive
            $googleConfig = \WpfdAddonHelper::getAllCloudConfigs();
            $googleSyncInterval = isset($googleConfig['googleSyncTime']) ? (int)$googleConfig['googleSyncTime'] : 30;
            if (defined('WPFDADDON_GOOGLE_AUTO_SYNC_TIME_CUSTOM') && WPFDADDON_GOOGLE_AUTO_SYNC_TIME_CUSTOM && defined('WPFDADDON_GOOGLE_AUTO_SYNC_TIME')) {
                $googleSyncInterval = WPFDADDON_GOOGLE_AUTO_SYNC_TIME;
            }
            $googleSyncMethod = isset($googleConfig['googleSyncMethod']) ? (string)$googleConfig['googleSyncMethod'] : 'sync_page_curl_ajax';
            if ($googleSyncMethod === 'setup_on_server') {
                $schedules['wpfd_google_sync_interval'] = array(
                    'interval' => intval($googleSyncInterval) * 60,
                    'display' => 'wpfd_google_sync_interval'
                );
            }
        }
        if (!is_null($this->googleTeamDrive)) {
            // Google Team Drive
            $googleTeamDriveConfig = \WpfdAddonHelper::getAllCloudTeamDriveConfigs();
            $googleTeamDriveSyncInterval = isset($googleTeamDriveConfig['googleTeamDriveSyncTime']) ? (int)$googleTeamDriveConfig['googleTeamDriveSyncTime'] : 30;
            if (defined('WPFDADDON_GOOGLE_TEAM_DRIVE_AUTO_SYNC_TIME_CUSTOM')
                && WPFDADDON_GOOGLE_TEAM_DRIVE_AUTO_SYNC_TIME_CUSTOM && defined('WPFDADDON_GOOGLE_TEAM_DRIVE_AUTO_SYNC_TIME')) {
                $googleTeamDriveSyncInterval = WPFDADDON_GOOGLE_TEAM_DRIVE_AUTO_SYNC_TIME;
            }
            $googleTeamDriveSyncMethod = isset($googleTeamDriveConfig['googleTeamDriveSyncMethod']) ? (string)$googleTeamDriveConfig['googleTeamDriveSyncMethod'] : 'sync_page_curl_ajax';
            if ($googleTeamDriveSyncMethod === 'setup_on_server') {
                $schedules['wpfd_google_team_drive_sync_interval'] = array(
                    'interval' => intval($googleTeamDriveSyncInterval) * 60,
                    'display'  => 'wpfd_google_team_drive_sync_interval'
                );
            }
        }
        if (!is_null($this->oneDrive)) {
            // Onedrive
            $onedriveConfig = \WpfdAddonHelper::getAllOnedriveConfigs();
            $onedriveSyncInterval = isset($onedriveConfig['onedriveSyncTime']) ? (int)$onedriveConfig['onedriveSyncTime'] : 30;
            $onedriveSyncMethod = isset($onedriveConfig['onedriveSyncMethod']) ? (string)$onedriveConfig['onedriveSyncMethod'] : 'sync_page_curl_ajax';
            if ($onedriveSyncMethod === 'setup_on_server') {
                $schedules['wpfd_onedrive_sync_interval'] = array(
                    'interval' => intval($onedriveSyncInterval) * 60,
                    'display' => 'wpfd_onedrive_sync_interval'
                );
            }
        }
        if (!is_null($this->oneDriveBusiness)) {
            // Onedrive Business
            $onedriveBusinessConfig = \WpfdAddonHelper::getAllOnedriveBusinessConfigs();
            $onedriveBusinessSyncInterval = isset($onedriveBusinessConfig['onedriveBusinessSyncTime']) ? (int)$onedriveBusinessConfig['onedriveBusinessSyncTime'] : 30;
            $onedriveBusinessSyncMethod = isset($onedriveBusinessConfig['onedriveBusinessSyncMethod']) ? (string)$onedriveBusinessConfig['onedriveBusinessSyncMethod'] : 'sync_page_curl_ajax';
            if ($onedriveBusinessSyncMethod === 'setup_on_server') {
                $schedules['wpfd_onedrive_business_sync_interval'] = array(
                    'interval' => intval($onedriveBusinessSyncInterval) * 60,
                    'display' => 'wpfd_onedrive_business_sync_interval'
                );
            }
        }
        if (!is_null($this->dropbox)) {
            // Dropbox
            $dropboxConfig = \WpfdAddonHelper::getAllDropboxConfigs();
            $dropboxSyncInterval = isset($dropboxConfig['dropboxSyncTime']) ? (int)$dropboxConfig['dropboxSyncTime'] : 30;
            $dropboxSyncMethod = isset($dropboxConfig['dropboxSyncMethod']) ? (string)$dropboxConfig['dropboxSyncMethod'] : 'sync_page_curl_ajax';
            if ($dropboxSyncMethod === 'setup_on_server') {
                $schedules['wpfd_dropbox_sync_interval'] = array(
                    'interval' => intval($dropboxSyncInterval) * 60,
                    'display' => 'wpfd_dropbox_sync_interval'
                );
            }
        }
        if (!is_null($this->aws)) {
            // AWS
            $awsConfig = \WpfdAddonHelper::getAllAwsConfigs();
            $awsSyncInterval = isset($awsConfig['awsSyncTime']) ? (int)$awsConfig['awsSyncTime'] : 30;
            $awsSyncMethod = isset($awsConfig['awsSyncMethod']) ? (string)$awsConfig['awsSyncMethod'] : 'sync_page_curl_ajax';
            if ($awsSyncMethod === 'setup_on_server') {
                $schedules['wpfd_aws_sync_interval'] = array(
                    'interval' => intval($awsSyncInterval) * 60,
                    'display' => 'wpfd_aws_sync_interval'
                );
            }
        }

        if (!is_null($this->nextcloud)) {
            $nextcloudConfig = \WpfdAddonHelper::getAllNextcloudConfigs();
            $nextcloudSyncInterval = isset($nextcloudConfig['nextcloudSyncTime']) ? (int)$nextcloudConfig['nextcloudSyncTime'] : 30;
            $nextcloudSyncMethod = isset($nextcloudConfig['nextcloudSyncMethod']) ? (string)$nextcloudConfig['nextcloudSyncMethod'] : 'sync_page_curl_ajax';
            if ($nextcloudSyncMethod === 'setup_on_server') {
                $schedules['wpfd_nextcloud_sync_interval'] = array(
                    'interval' => intval($nextcloudSyncInterval) * 60,
                    'display' => 'wpfd_nextcloud_sync_interval'
                );
            }
        }

        return $schedules;
    }

    /**
     * Google drive cron task
     *
     * @return void
     */
    public function googleCronTask()
    {
        $this->addGoogleDriveRootToQueue(true);
        $this->runQueue();
    }

    /**
     * Google team drive cron task
     *
     * @return void
     */
    public function googleTeamDriveCronTask()
    {
        $this->addGoogleTeamDriveRootToQueue(true);
        $this->runQueue();
    }

    /**
     * Onedrive cron task
     *
     * @return void
     */
    public function onedriveCronTask()
    {
        $this->addOnedriveRootToQueue(true);
        $this->runQueue();
    }

    /**
     * Onedrive Business cron task
     *
     * @return void
     */
    public function onedriveBusinessCronTask()
    {
        $this->addOnedriveBusinessRootToQueue(true);
        $this->runQueue();
    }

    /**
     * Dropbox cron task
     *
     * @return void
     */
    public function dropboxCronTask()
    {
        $this->addDropboxRootToQueue(true);
        $this->runQueue();
    }

    /**
     * AWS cron task
     *
     * @return void
     */
    public function awsCronTask()
    {
        $this->addAwsRootToQueue(true);
        $this->runQueue();
    }

    /**
     * Nextcloud cron task
     *
     * @return void
     */
    public function nextcloudCronTask()
    {
        $this->addNextcloudRootToQueue(true);
        $this->runQueue();
    }

    /**
     * Schedule sync jobs
     *
     * @return void
     */
    public function scheduleCronJob()
    {
        add_filter('cron_schedules', array($this, 'getSchedules'));

        if (!is_null($this->googleDrive)) {
            // Add hook for cronjob
            add_action('wpfd_addon_google_sync', array($this, 'googleCronTask'));
            // Install cronjob
            wpfd_install_job('wpfd_addon_google_sync', 'wpfd_google_sync_interval');
        }
        if (!is_null($this->googleTeamDrive)) {
            // Add hook for cronjob
            add_action('wpfd_addon_google_team_drive_sync', array($this, 'googleTeamDriveCronTask'));
            // Install cronjob
            wpfd_install_job('wpfd_addon_google_team_drive_sync', 'wpfd_google_team_drive_sync_interval');
        }
        if (!is_null($this->oneDrive)) {
            // Add hook for cronjob
            add_action('wpfd_addon_onedrive_sync', array($this, 'onedriveCronTask'));
            // Install cronjob
            wpfd_install_job('wpfd_addon_onedrive_sync', 'wpfd_onedrive_sync_interval');
        }
        if (!is_null($this->oneDriveBusiness)) {
            // Add hook for cronjob
            add_action('wpfd_addon_onedrive_business_sync', array($this, 'onedriveBusinessCronTask'));
            // Install cronjob
            wpfd_install_job('wpfd_addon_onedrive_business_sync', 'wpfd_onedrive_business_sync_interval');
        }
        if (!is_null($this->dropbox)) {
            // Add hook for cronjob
            add_action('wpfd_addon_dropbox_sync', array($this, 'dropboxCronTask'));
            // Install cronjob
            wpfd_install_job('wpfd_addon_dropbox_sync', 'wpfd_dropbox_sync_interval');
        }
        if (!is_null($this->aws)) {
            // Add hook for cronjob
            add_action('wpfd_addon_aws_sync', array($this, 'awsCronTask'));
            // Install cronjob
            wpfd_install_job('wpfd_addon_aws_sync', 'wpfd_aws_sync_interval');
        }
        if (!is_null($this->nextcloud)) {
            // Add hook for cronjob
            add_action('wpfd_addon_nextcloud_sync', array($this, 'nextcloudCronTask'));
            // Install cronjob
            wpfd_install_job('wpfd_addon_nextcloud_sync', 'wpfd_nextcloud_sync_interval');
        }
    }
    /**
     * Add google drive root to queue
     *
     * @param boolean $newSync Add new sync or not
     *
     * @return void
     */
    public function addGoogleDriveRootToQueue($newSync = false)
    {
        if (!is_null($this->googleDrive)) {
            $googleDatas = array(
                'id' => $this->googleBaseFolderId,
                'folder_parent' => -1,
                'name' => 'Google Drive',
                'action' => 'wpfd_sync_google_drive',
                'type' => 'folder'
            );
            if ($newSync) {
                $this->addOnceToQueue($googleDatas);
            } else {
                $this->addToQueue($googleDatas);
            }
        }
    }

    /**
     * Add google team drive root to queue
     *
     * @param boolean $newSync Add new sync or not
     *
     * @return void
     */
    public function addGoogleTeamDriveRootToQueue($newSync = false)
    {
        if (!is_null($this->googleTeamDrive)) {
            $googleTeamDriveDatas = array(
                'id' => $this->googleTeamDriveBaseFolderId,
                'folder_parent' => -1,
                'name' => 'Google Team Drive',
                'action' => 'wpfd_sync_google_team_drive',
                'type' => 'folder'
            );

            if ($newSync) {
                $this->addOnceToQueue($googleTeamDriveDatas);
            } else {
                $this->addToQueue($googleTeamDriveDatas);
            }
        }
    }

    /**
     * Google Drive synchronization
     *
     * @param boolean $result     Result
     * @param array   $datas      Data details
     * @param integer $element_id ID of queue element
     *
     * @return void
     */
    public function doGoogleSync($result, $datas, $element_id)
    {
        // Check Google Drive credentials for sure
        if (is_null($this->googleDrive)) {
            return;
        }

        if (isset($datas['folder_parent']) && $datas['folder_parent'] === -1) {
            // Get all root categories from cloud
            $cloudRootFolders = array();
            try {
                $this->googleDrive->getFilesInFolder($datas['id'], $cloudRootFolders, false);
            } catch (\Exception $e) {
                return;
            }
            $rootCloudParents = array();
            if (is_array($cloudRootFolders) && count($cloudRootFolders) > 0) {
                foreach ($cloudRootFolders as $cloudId => $cloudData) {
                    $googleDatas = array(
                        'id' => $cloudId,
                        'folder_parent' => 0,
                        'name' => $cloudData['title'],
                        'action' => 'wpfd_sync_google_drive',
                        'type' => 'folder'
                    );
                    $rootCloudParents[] = $cloudId;
                    $this->addOnceToQueue($googleDatas);
                    $this->mayBeGeneratePreviewGoogle($googleDatas);
                }
                // then remove the file and folder not exist
                $datas = array(
                    'id' => '',
                    'folder_id' => 0,
                    'cloud_folder_id' => $datas['id'],
                    'action' => 'wpfd_google_drive_remove',
                    'cloud_folders_list' => $rootCloudParents,
                    'time' => time()
                );
                $this->addNewToQueue($datas);
            }
        } else {
            $message = array();
            $name = html_entity_decode($datas['name']);
            $wpfd_category_taxonomy = 'wpfd-category';
            // check folder exists
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_id', $datas['id'])));
            if (!$row) {
                $inserted = wp_insert_term($name, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                if (is_wp_error($inserted)) {
                    $folder_id = (int)$inserted->get_error_data('term_exists');
                    $message[] = 'category exists4';
                } else {
                    $folder_id = (int)$inserted['term_id'];
                    $message[] = 'new folder inserted';
                }

                update_term_meta($folder_id, 'wpfd_drive_id', $datas['id']);
                update_term_meta($folder_id, 'wpfd_drive_type', 'googleDrive');
            } else {
                $folder_id = (int)$row->term_id;
                $exist_folder = get_term($folder_id, $wpfd_category_taxonomy);
                // if folder exists, then update parent and name
                if (!is_wp_error($exist_folder) && !is_null($exist_folder) && !empty($datas['folder_parent']) && (int)$exist_folder->parent !== (int)$datas['folder_parent']) {
                    $parent_exist = get_term((int)$datas['folder_parent'], $wpfd_category_taxonomy);
                    if (!is_wp_error($parent_exist)) {
                        wp_update_term($folder_id, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                        update_term_meta($folder_id, 'wpfd_drive_type', 'googleDrive');
                        $message[] = 'updated parent';
                    }
                }

                if ($name !== $exist_folder->name) {
                    wp_update_term($folder_id, $wpfd_category_taxonomy, array('name' => $name));
                    update_term_meta($folder_id, 'wpfd_drive_type', 'googleDrive');
                    $message[] = 'updated name';
                }
            }

            if (!empty($folder_id)) {
                $responses = array();
                $responses['folder_id'] = (int)$folder_id;
                $responses['message'] = $message;
                $this->updateQueueTermMeta((int)$responses['folder_id'], (int)$element_id);
                $this->updateResponse((int)$element_id, $responses);
                // find childs element to add to queue
                $this->addGoogleChildsToQueue($datas['id'], $folder_id);
            }
        }
        // Update last log
        \WpfdAddonHelper::setCloudParam('last_log', date('Y-m-d H:i:s'));

        return true;
    }

    /**
     * Google team drive synchronization
     *
     * @param boolean $result     Result
     * @param array   $datas      Data details
     * @param integer $element_id ID of queue element
     *
     * @return void
     */
    public function doGoogleTeamDriveSync($result, $datas, $element_id)
    {
        // Check google team drive credentials for sure
        if (is_null($this->googleTeamDrive)) {
            return;
        }

        if (isset($datas['folder_parent']) && $datas['folder_parent'] === -1) {
            // Get all root categories from cloud
            $cloudRootFolders = array();
            try {
                $this->googleTeamDrive->getFilesInFolder($datas['id'], $cloudRootFolders, false);
            } catch (\Exception $e) {
                return;
            }
            $rootCloudTeamDriveParents = array();
            if (is_array($cloudRootFolders) && count($cloudRootFolders) > 0) {
                foreach ($cloudRootFolders as $cloudId => $cloudTeamDriveData) {
                    $googleTeamDriveDatas = array(
                        'id' => $cloudId,
                        'folder_parent' => 0,
                        'name' => $cloudTeamDriveData['title'],
                        'action' => 'wpfd_sync_google_team_drive',
                        'type' => 'folder'
                    );
                    $rootCloudTeamDriveParents[] = $cloudId;
                    $this->addOnceToQueue($googleTeamDriveDatas);
                }

                // Then remove the file and folder not exist
                $datas = array(
                    'id' => '',
                    'folder_id' => 0,
                    'cloud_folder_id' => $datas['id'],
                    'action' => 'wpfd_google_team_drive_remove',
                    'cloud_folders_list' => $rootCloudTeamDriveParents,
                    'time' => time()
                );
                $this->addNewToQueue($datas);
            }
        } else {
            $message = array();
            $name = html_entity_decode($datas['name']);
            $wpfd_category_taxonomy = 'wpfd-category';

            // Check folder exists
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_id', $datas['id'])));
            if (!$row) {
                $inserted = wp_insert_term($name, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                if (is_wp_error($inserted)) {
                    $folder_id = (int)$inserted->get_error_data('term_exists');
                    $message[] = 'category exists4';
                } else {
                    $folder_id = (int)$inserted['term_id'];
                    $message[] = 'new folder inserted';
                }

                update_term_meta($folder_id, 'wpfd_drive_id', $datas['id']);
                update_term_meta($folder_id, 'wpfd_drive_type', 'googleTeamDrive');
            } else {
                $folder_id = (int)$row->term_id;
                $exist_folder = get_term($folder_id, $wpfd_category_taxonomy);

                // If folder exists, then update parent and name
                if (!is_wp_error($exist_folder) && !is_null($exist_folder) && !empty($datas['folder_parent']) && (int)$exist_folder->parent !== (int)$datas['folder_parent']) {
                    $parent_exist = get_term((int)$datas['folder_parent'], $wpfd_category_taxonomy);
                    if (!is_wp_error($parent_exist)) {
                        wp_update_term($folder_id, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                        update_term_meta($folder_id, 'wpfd_drive_type', 'googleTeamDrive');
                        $message[] = 'updated parent';
                    }
                }

                if ($name !== $exist_folder->name) {
                    wp_update_term($folder_id, $wpfd_category_taxonomy, array('name' => $name));
                    update_term_meta($folder_id, 'wpfd_drive_type', 'googleTeamDrive');
                    $message[] = 'updated name';
                }
            }

            if (!empty($folder_id)) {
                $responses = array();
                $responses['folder_id'] = (int)$folder_id;
                $responses['message'] = $message;
                $this->updateQueueTermMeta((int)$responses['folder_id'], (int)$element_id);
                $this->updateResponse((int)$element_id, $responses);

                // Find childs element to add to queue
                $this->addGoogleTeamDriveChildsToQueue($datas['id'], $folder_id);
            }
        }

        // Update the sync time as a last log
        \WpfdAddonHelper::setCloudTeamDriveParam('google_team_drive_last_log', date('Y-m-d H:i:s'));

        return true;
    }

    /**
     * Add single folder sync
     *
     * @return boolean
     */
    public function addGoogleDriveSync()
    {
        // Check Google Drive credentials for sure
        if (is_null($this->googleDrive)) {
            return true;
        }
        $id = Utilities::getInput('id', 'POST', 'string');
        $parent_id = Utilities::getInput('parent_id', 'POST', 'string');
        if ($id !== '') {
            try {
                $cloud_id = \WpfdAddonHelper::getGoogleDriveIdByTermId($id);
                $cloudData = $this->googleDrive->getFileInfos($cloud_id);
            } catch (\Exception $e) {
                return true;
            }
            if (!$cloudData) {
                return false;
            }
            $datas = array(
                'id' => $cloudData['id'],
                'folder_parent' => $parent_id,
                'name' => mb_convert_encoding(strval($cloudData['title']), 'HTML-ENTITIES', 'UTF-8'),
                'action' => 'wpfd_sync_google_drive',
                'type' => 'folder',
                'datas' => $cloudData,
            );
            $rootCloudParents[] = $cloudData['id'];
            $this->addOnceToQueue($datas);
        }
    }
    /**
     * Add Google Child to queue
     *
     * @param string $folderID      Folder id
     * @param string $folder_parent Folder parent
     *
     * @throws \Google_Service_Exception Throw exception on error
     *
     * @return void
     */
    public function addGoogleChildsToQueue($folderID, $folder_parent)
    {
        $pageToken = null;
        $childs = array();
        $error = false;
        do {
            try {
                $params = array(
                    'q' => "'" . $folderID . "' in parents and trashed = false and mimeType = 'application/vnd.google-apps.folder'",
                    'fields' => 'files(id,name)',
                    'pageSize' => 1000
                );

                if ($pageToken) {
                    $params['pageToken'] = $pageToken;
                }

                $service = $this->googleDrive->getGoogleService();
                $files = $service->files->listFiles($params);
                $childs = array_merge($childs, $files->getFiles());
                $pageToken = $files->getNextPageToken();
            } catch (\Google_Service_Exception $e) {
                $error = true;
                $pageToken = null;
            } catch (Exception $e) {
                $error = true;
                $pageToken = null;
            }
        } while ($pageToken);

        if ($error) {
            return;
        }

        // get folder childs list on cloud
        $cloud_folders_list = array();
        // Create files in media library
        foreach ($childs as $child) {
            $datas = array(
                'id' => $child->id,
                'folder_parent' => $folder_parent,
                'name' => mb_convert_encoding($child->name, 'HTML-ENTITIES', 'UTF-8'),
                'action' => 'wpfd_sync_google_drive',
                'cloud_parent' => $folderID
            );

            $cloud_folders_list[] = $child->id;
            $datas['type'] = 'folder';
            $this->addNewToQueue($datas);
            $this->mayBeGeneratePreviewGoogle($datas);
        }

        // then remove the file and folder not exist
        $datas = array(
            'id' => '',
            'folder_id' => $folder_parent,
            'cloud_folder_id' => $folderID,
            'action' => 'wpfd_google_drive_remove',
            'cloud_folders_list' => $cloud_folders_list,
            'time' => time()
        );
        $this->addNewToQueue($datas);
    }

    /**
     * Add google team drive child to queue
     *
     * @param string $folderID      Folder id
     * @param string $folder_parent Folder parent
     *
     * @return void
     */
    public function addGoogleTeamDriveChildsToQueue($folderID, $folder_parent)
    {
        $pageToken = null;
        $childs = array();
        $error = false;
        do {
            try {
                $params = array(
                    'q' => "'" . $folderID . "' in parents and trashed = false and mimeType = 'application/vnd.google-apps.folder'",
                    'fields' => 'files(id,name)',
                    'pageSize' => 1000,
                    'includeItemsFromAllDrives' => true,
                    'supportsTeamDrives' => true
                );

                if ($pageToken) {
                    $params['pageToken'] = $pageToken;
                }

                $service = $this->googleTeamDrive->getGoogleService();
                $files = $service->files->listFiles($params);
                $childs = array_merge($childs, $files->getFiles(array(
                    'includeItemsFromAllDrives' => true,
                    'supportsTeamDrives' => true
                )));
                $pageToken = $files->getNextPageToken();
            } catch (\Google_Service_Exception $e) {
                $error = true;
                $pageToken = null;
            } catch (Exception $e) {
                $error = true;
                $pageToken = null;
            }
        } while ($pageToken);

        if ($error) {
            return;
        }

        // get folder childs list on cloud
        $cloud_folders_list = array();
        // Create files in media library
        foreach ($childs as $child) {
            $datas = array(
                'id' => $child->id,
                'folder_parent' => $folder_parent,
                'name' => mb_convert_encoding($child->name, 'HTML-ENTITIES', 'UTF-8'),
                'action' => 'wpfd_sync_google_team_drive',
                'cloud_parent' => $folderID
            );

            $cloud_folders_list[] = $child->id;
            $datas['type'] = 'folder';
            $this->addNewToQueue($datas);
        }

        // then remove the file and folder not exist
        $datas = array(
            'id' => '',
            'folder_id' => $folder_parent,
            'cloud_folder_id' => $folderID,
            'action' => 'wpfd_google_team_drive_remove',
            'cloud_folders_list' => $cloud_folders_list,
            'time' => time()
        );
        $this->addNewToQueue($datas);
    }


    /**
     * Google Sync remove items
     *
     * @param mixed   $result     Task result
     * @param array   $datas      Task data
     * @param integer $element_id Task id
     *
     * @return boolean
     */
    public function syncRemoveGoogleItems($result, $datas, $element_id)
    {
        // Get media library files in current folder, then remove the folder not exist
        $folders = get_categories(array('hide_empty' => false, 'hierarchical' => false, 'taxonomy' => 'wpfd-category', 'parent' => (int)$datas['folder_id']));
        foreach ($folders as $folder) {
            $drive_id = get_term_meta($folder->term_id, 'wpfd_drive_id', true);
//            $drive_id = \WpfdAddonHelper::getGoogleDriveIdByTermId($folder->term_id);
            if (empty($drive_id) || is_null($drive_id)) {
                continue;
            }
            $driveType = get_term_meta($folder->term_id, 'wpfd_drive_type', true);
            if (empty($driveType) || $driveType !== 'googleDrive') {
                continue;
            }
            if (is_array($datas['cloud_folders_list']) && !empty($drive_id) && !in_array($drive_id, $datas['cloud_folders_list'])) {
                wp_delete_term($folder->term_id, 'wpfd-category');
            }
        }
        return true;
    }

    /**
     * Google team drive sync remove items
     *
     * @param mixed   $result     Task result
     * @param array   $datas      Task data
     * @param integer $element_id Task id
     *
     * @return boolean
     */
    public function syncRemoveGoogleTeamDriveItems($result, $datas, $element_id)
    {
        // Get files in current folder, then remove the folder not exist
        $folders = get_categories(array('hide_empty' => false, 'hierarchical' => false, 'taxonomy' => 'wpfd-category', 'parent' => (int)$datas['folder_id']));
        foreach ($folders as $folder) {
            $drive_id = get_term_meta($folder->term_id, 'wpfd_drive_id', true);
            if (empty($drive_id) || is_null($drive_id)) {
                continue;
            }
            $driveType = get_term_meta($folder->term_id, 'wpfd_drive_type', true);
            if (empty($driveType) || $driveType !== 'googleTeamDrive') {
                continue;
            }
            if (is_array($datas['cloud_folders_list']) && !empty($drive_id) && !in_array($drive_id, $datas['cloud_folders_list'])) {
                wp_delete_term($folder->term_id, 'wpfd-category');
            }
        }
        return true;
    }

    /**
     * Add Onedrive Root to queue
     *
     * @param boolean $newSync Is this is first time in admin
     *
     * @return void
     */
    public function addOnedriveRootToQueue($newSync = false)
    {
        if (!is_null($this->oneDrive)) {
            $onedriveDatas = array(
                'id' => $this->oneDriveBaseFolderId,
                'folder_parent' => -1,
                'name' => 'Onedrive',
                'action' => 'wpfd_sync_onedrive',
                'type' => 'folder'
            );

            if ($newSync) {
                $this->addOnceToQueue($onedriveDatas);
            } else {
                $this->addToQueue($onedriveDatas);
            }
        }
    }

    /**
     * OneDrive synchronization
     *
     * @param boolean $result     Result
     * @param array   $datas      Data details
     * @param integer $element_id ID of queue element
     *
     * @return boolean
     */
    public function doOnedriveSync($result, $datas, $element_id)
    {
        // Check Google Drive credentials for sure
        if (is_null($this->oneDrive)) {
            return true;
        }

        if (isset($datas['folder_parent']) && $datas['folder_parent'] === -1) {
            // Get all root categories from cloud
            $cloudRootFolders = array();
            try {
                $this->oneDrive->getFilesInFolder($datas['id'], $cloudRootFolders, false);
            } catch (\Exception $e) {
                return true;
            }

            if (is_array($cloudRootFolders) && count($cloudRootFolders) > 0) {
                $rootCloudParents = array();
                foreach ($cloudRootFolders as $cloudId => $cloudData) {
                    $onedriveDatas = array(
                        'id' => $cloudId,
                        'folder_parent' => 0,
                        'name' => $cloudData['title'],
                        'action' => 'wpfd_sync_onedrive',
                        'type' => 'folder'
                    );
                    $rootCloudParents[] = $cloudId;
                    $this->addOnceToQueue($onedriveDatas);
                    $this->maybeGeneratePreviewOnedrive($onedriveDatas);
                }
                // then remove the file and folder not exist
                $datas = array(
                    'id' => '',
                    'folder_id' => 0,
                    'cloud_folder_id' => $datas['id'],
                    'action' => 'wpfd_onedrive_remove',
                    'cloud_folders_list' => $rootCloudParents,
                    'time' => time()
                );
                $this->addNewToQueue($datas);
            }
        } else {
            $message = array();
            $name = html_entity_decode($datas['name']);
            $wpfd_category_taxonomy = 'wpfd-category';
            // check folder exists
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_id', $datas['id'])));
//            $row = \WpfdAddonHelper::getTermIdOneDriveByOneDriveId($datas['id']);
            if (!$row) {
                $inserted = wp_insert_term($name, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                if (is_wp_error($inserted)) {
                    $folder_id = (int)$inserted->get_error_data('term_exists');
                    $message[] = 'category exists4';
                } else {
                    $folder_id = (int)$inserted['term_id'];
                    $message[] = 'new folder inserted';
                }

                update_term_meta($folder_id, 'wpfd_drive_id', $datas['id']);
                update_term_meta($folder_id, 'wpfd_drive_type', 'onedrive');
            } else {
                $folder_id = (int) $row->term_id;
                $exist_folder = get_term($folder_id, $wpfd_category_taxonomy);
                // if folder exists, then update parent and name
                if (!is_wp_error($exist_folder) && !is_null($exist_folder) && !empty($datas['folder_parent']) && (int)$exist_folder->parent !== (int)$datas['folder_parent']) {
                    $parent_exist = get_term((int)$datas['folder_parent'], $wpfd_category_taxonomy);
                    if (!is_wp_error($parent_exist) && (int)$datas['folder_parent'] !== $exist_folder->parent) {
                        wp_update_term($folder_id, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                        update_term_meta($folder_id, 'wpfd_drive_type', 'onedrive');
                        $message[] = 'updated parent';
                    }
                }

                if ($exist_folder && $name !== $exist_folder->name) {
                    wp_update_term($folder_id, $wpfd_category_taxonomy, array('name' => $name));
                    update_term_meta($folder_id, 'wpfd_drive_type', 'onedrive');
                    $message[] = 'updated name';
                }
            }

            if (!empty($folder_id)) {
                $responses = array();
                $responses['folder_id'] = (int)$folder_id;
                $responses['message'] = $message;
//                update_term_meta($responses['folder_id'], 'wpfd_drive_type', 'google_drive');
                $this->updateQueueTermMeta((int)$responses['folder_id'], (int)$element_id);
                $this->updateResponse((int)$element_id, $responses);
                // find childs element to add to queue
                $this->addOnedriveChildsToQueue($datas['id'], $folder_id);
            }
        }
        \WpfdAddonHelper::setOneDriveParam('last_log', date('Y-m-d H:i:s'));

        return true;
    }

    /**
     * Add single folder sync
     *
     * @return boolean
     */
    public function addOneDriveSync()
    {
        // Check Google Drive credentials for sure
        if (is_null($this->oneDrive)) {
            return true;
        }
        $id = Utilities::getInput('id', 'POST', 'string');
        $parent_id = Utilities::getInput('parent_id', 'POST', 'string');
        if ($id !== '') {
            try {
                $cloud_id = \WpfdAddonHelper::getOneDriveIdByTermId($id);
                $cloudData = $this->oneDrive->getFolderInfo($cloud_id);
            } catch (\Exception $e) {
                return true;
            }

            $datas = array(
                'id' => \WpfdAddonHelper::replaceIdOneDrive($cloudData['id'], true),
                'folder_parent' => $parent_id,
                'name' => mb_convert_encoding($cloudData['name'], 'HTML-ENTITIES', 'UTF-8'),
                'action' => 'wpfd_sync_onedrive',
                'type' => 'folder',
                'datas' => $cloudData,
            );
            $this->addOnceToQueue($datas);
        }
    }

    /**
     * Add Onedrive  Child to queue
     *
     * @param string $folderID      Folder id
     * @param string $folder_parent Folder parent
     *
     * @return void
     */
    public function addOnedriveChildsToQueue($folderID, $folder_parent)
    {
        $pageToken = null;
        $childs = array();
        try {
            $this->oneDrive->getFilesInFolder($folderID, $childs, false);
        } catch (\Exception $e) {
            return;
        }

        // get folder childs list on cloud
        $cloud_folders_list = array();
        if (!empty($childs)) {
            // Create files in media library
            foreach ($childs as $childId => $child) {
                $datas = array(
                    'id' => $childId,
                    'folder_parent' => $folder_parent,
                    'name' => mb_convert_encoding($child['title'], 'HTML-ENTITIES', 'UTF-8'),
                    'action' => 'wpfd_sync_onedrive',
                    'cloud_parent' => $folderID
                );

                $cloud_folders_list[] = $childId;
                $datas['type'] = 'folder';
                $this->addNewToQueue($datas);
                $this->maybeGeneratePreviewOnedrive($datas);
            }
        }

        // then remove the file and folder not exist
        $datas = array(
            'id' => '',
            'folder_id' => $folder_parent,
            'cloud_folder_id' => $folderID,
            'action' => 'wpfd_onedrive_remove',
            'cloud_folders_list' => $cloud_folders_list,
            'time' => time()
        );
        $this->addNewToQueue($datas);
    }

    /**
     * OneDrive Sync remove items
     *
     * @param mixed   $result     Task result
     * @param array   $datas      Task data
     * @param integer $element_id Task id
     *
     * @return boolean
     */
    public function syncRemoveOnedriveItems($result, $datas, $element_id)
    {
        // Get media library files in current folder, then remove the folder not exist
        $folders = get_categories(array('hide_empty' => false, 'hierarchical' => false, 'taxonomy' => 'wpfd-category', 'parent' => (int)$datas['folder_id']));
        foreach ($folders as $folder) {
            $drive_id = get_term_meta($folder->term_id, 'wpfd_drive_id', true);
            if (empty($drive_id) || is_null($drive_id)) {
                continue;
            }
            $driveType = get_term_meta($folder->term_id, 'wpfd_drive_type', true);
            if (empty($driveType) || $driveType !== 'onedrive') {
                continue;
            }
            if (is_array($datas['cloud_folders_list']) && !empty($drive_id) && !in_array($drive_id, $datas['cloud_folders_list'])) {
                wp_delete_term($folder->term_id, 'wpfd-category');
            }
        }
        return true;
    }

    /**
     * Add Onedrive Business Root to queue
     *
     * @param boolean $newSync Is this is first time in admin
     *
     * @return void
     */
    public function addOnedriveBusinessRootToQueue($newSync = false)
    {
        if (!is_null($this->oneDriveBusiness)) {
            $onedriveDatas = array(
                'id' => $this->oneDriveBusinessBaseFolderId,
                'folder_parent' => -1,
                'name' => 'Onedrive Business',
                'action' => 'wpfd_sync_onedrive_business',
                'type' => 'folder'
            );

            if ($newSync) {
                $this->addOnceToQueue($onedriveDatas);
            } else {
                $this->addToQueue($onedriveDatas);
            }
        }
    }

    /**
     * OneDrive Business synchronization
     *
     * @param boolean $result     Result
     * @param array   $datas      Data details
     * @param integer $element_id ID of queue element
     *
     * @return void
     */
    public function doOnedriveBusinessSync($result, $datas, $element_id)
    {
        // Check Google Drive credentials for sure
        if (is_null($this->oneDriveBusiness)) {
            return;
        }
        if (isset($datas['folder_parent']) && $datas['folder_parent'] === -1) {
            // Get all root categories from cloud
            $cloudRootFolders = array();
            try {
                $this->oneDriveBusiness->getFilesInFolder($datas['id'], $cloudRootFolders, false);
            } catch (\Exception $e) {
                return;
            }
            if (is_array($cloudRootFolders) && count($cloudRootFolders) > 0) {
                foreach ($cloudRootFolders as $cloudId => $cloudData) {
                    $onedriveDatas = array(
                        'id' => $cloudId,
                        'folder_parent' => 0,
                        'name' => $cloudData['title'],
                        'action' => 'wpfd_sync_onedrive_business',
                        'type' => 'folder'
                    );
                    $this->addOnceToQueue($onedriveDatas);
                    $this->maybeGeneratePreviewOnedriveBusiness($onedriveDatas);
                }
            }
        } else {
            $message = array();
            $name = html_entity_decode($datas['name']);
            $wpfd_category_taxonomy = 'wpfd-category';
            // check folder exists
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_id', $datas['id'])));
//            $row = \WpfdAddonHelper::getTermIdOneDriveBusinessByOneDriveId($datas['id']);
            if (!$row) {
                $inserted = wp_insert_term($name, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                if (is_wp_error($inserted)) {
                    $folder_id = (int)$inserted->get_error_data('term_exists');
                    $message[] = 'category exists4';
                } else {
                    $folder_id = (int)$inserted['term_id'];
                    $message[] = 'new folder inserted';
                }

                update_term_meta($folder_id, 'wpfd_drive_id', $datas['id']);
                update_term_meta($folder_id, 'wpfd_drive_type', 'onedrive_business');
            } else {
                $folder_id = (int) $row->term_id;
                $exist_folder = get_term($folder_id, $wpfd_category_taxonomy);
                // if folder exists, then update parent and name
                if (!is_wp_error($exist_folder) && !is_null($exist_folder) && !empty($datas['folder_parent']) && (int)$exist_folder->parent !== (int)$datas['folder_parent']) {
                    $parent_exist = get_term((int)$datas['folder_parent'], $wpfd_category_taxonomy);
                    if (!is_wp_error($parent_exist)) {
                        wp_update_term($folder_id, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                        update_term_meta($folder_id, 'wpfd_drive_type', 'onedrive_business');
                        $message[] = 'updated parent';
                    }
                }

                if ($name !== $exist_folder->name) {
                    wp_update_term($folder_id, $wpfd_category_taxonomy, array('name' => $name));
                    update_term_meta($folder_id, 'wpfd_drive_type', 'onedrive_business');
                    $message[] = 'updated name';
                }
            }

            if (!empty($folder_id)) {
                $responses = array();
                $responses['folder_id'] = (int)$folder_id;
                $responses['message'] = $message;
//                update_term_meta($responses['folder_id'], 'wpfd_drive_type', 'google_drive');
                $this->updateQueueTermMeta((int)$responses['folder_id'], (int)$element_id);
                $this->updateResponse((int)$element_id, $responses);
                // find childs element to add to queue
                $this->addOnedriveBusinessChildsToQueue($datas['id'], $folder_id);
            }
        }
        \WpfdAddonHelper::setOneDriveBusinessParam('last_log', date('Y-m-d H:i:s'));

        return true;
    }

    /**
     * Add single folder sync
     *
     * @return boolean
     */
    public function addOneDriveBusinessSync()
    {
        // Check Google Drive credentials for sure
        if (is_null($this->oneDriveBusiness)) {
            return true;
        }
        $id = Utilities::getInput('id', 'POST', 'string');
        $parent_id = Utilities::getInput('parent_id', 'POST', 'string');
        if ($id !== '') {
            try {
                $cloud_id = \WpfdAddonHelper::getOneDriveBusinessIdByTermId($id);
                $cloudData = $this->oneDriveBusiness->getFolderInfo($cloud_id);
            } catch (\Exception $e) {
                return true;
            }
            if (!$cloudData) {
                return false;
            }
            $datas = array(
                'id' => $cloudData['id'],
                'folder_parent' => $parent_id,
                'name' => mb_convert_encoding($cloudData['name'], 'HTML-ENTITIES', 'UTF-8'),
                'action' => 'wpfd_sync_onedrive_business',
                'type' => 'folder',
                'datas' => $cloudData,
            );
            $rootCloudParents[] = $cloudData['id'];
            $this->addOnceToQueue($datas);
        }
    }

    /**
     * Add Onedrive Business Child to queue
     *
     * @param string $folderID      Folder id
     * @param string $folder_parent Folder parent
     *
     * @return void
     */
    public function addOnedriveBusinessChildsToQueue($folderID, $folder_parent)
    {
        $pageToken = null;
        $childs = array();
        try {
            $this->oneDriveBusiness->getFilesInFolder($folderID, $childs, false);
        } catch (\Exception $ex) {
            return;
        }

        // get folder childs list on cloud
        $cloud_folders_list = array();
        if (!empty($childs)) {
            // Create files in media library
            foreach ($childs as $childId => $child) {
                $datas = array(
                    'id' => $childId,
                    'folder_parent' => $folder_parent,
                    'name' => mb_convert_encoding($child['title'], 'HTML-ENTITIES', 'UTF-8'),
                    'action' => 'wpfd_sync_onedrive_business',
                    'cloud_parent' => $folderID
                );

                $cloud_folders_list[] = $childId;
                $datas['type'] = 'folder';
                $this->addNewToQueue($datas);
                $this->maybeGeneratePreviewOnedriveBusiness($datas);
            }
        }

        // then remove the file and folder not exist
        $datas = array(
            'id' => '',
            'folder_id' => $folder_parent,
            'cloud_folder_id' => $folderID,
            'action' => 'wpfd_onedrive_business_remove',
            'cloud_folders_list' => $cloud_folders_list,
            'time' => time()
        );
        $this->addNewToQueue($datas);
    }

    /**
     * OneDrive Sync remove items
     *
     * @param mixed   $result     Task result
     * @param array   $datas      Task data
     * @param integer $element_id Task id
     *
     * @return boolean
     */
    public function syncRemoveOnedriveBusinessItems($result, $datas, $element_id)
    {
        // Get media library files in current folder, then remove the folder not exist
        $folders = get_categories(array('hide_empty' => false, 'hierarchical' => false, 'taxonomy' => 'wpfd-category', 'parent' => (int)$datas['folder_id']));
        foreach ($folders as $folder) {
            $drive_id = get_term_meta($folder->term_id, 'wpfd_drive_id', true);
//            $drive_id = \WpfdAddonHelper::getOneDriveBusinessIdByTermId($folder->term_id);
            if (empty($drive_id) || is_null($drive_id)) {
                continue;
            }
            $driveType = get_term_meta($folder->term_id, 'wpfd_drive_type', true);
            if (empty($driveType) || $driveType !== 'onedrive_business') {
                continue;
            }
            if (is_array($datas['cloud_folders_list']) && !empty($drive_id) && !in_array($drive_id, $datas['cloud_folders_list'])) {
                wp_delete_term($folder->term_id, 'wpfd-category');
            }
        }
        return true;
    }

    /**
     * Add Dropbox Root to queue
     *
     * @param boolean $newSync Is this is first time in admin
     *
     * @return void
     */
    public function addDropboxRootToQueue($newSync = false)
    {
        if (!is_null($this->dropbox)) {
            $onedriveDatas = array(
                'id' => $this->dropboxBaseFolderId,
                'folder_parent' => -1,
                'name' => 'Dropbox',
                'action' => 'wpfd_sync_dropbox',
                'type' => 'folder'
            );

            if ($newSync) {
                $this->addOnceToQueue($onedriveDatas);
            } else {
                $this->addToQueue($onedriveDatas);
            }
        }
    }

    /**
     * Dropbox synchronization
     *
     * @param boolean $result     Result
     * @param array   $datas      Data details
     * @param integer $element_id ID of queue element
     *
     * @return boolean
     */
    public function doDropboxSync($result, $datas, $element_id)
    {
        // Check Google Drive credentials for sure
        if (is_null($this->dropbox)) {
            return true;
        }

        if (isset($datas['folder_parent']) && $datas['folder_parent'] === -1) {
            if ($datas['id'] !== '') {
                try {
                    $cloudData = $this->dropbox->getDropboxFile($datas['id']);
                } catch (\Exception $e) {
                    return true;
                }

                $dropboxDatas = array(
                    'id' => $cloudData['id'],
                    'folder_parent' => 0,
                    'name' => mb_convert_encoding($cloudData['name'], 'HTML-ENTITIES', 'UTF-8'),
                    'action' => 'wpfd_sync_dropbox',
                    'type' => 'folder',
                    'datas' => $cloudData,
                );
                $rootCloudParents[] = $cloudData['id'];
                $this->addOnceToQueue($dropboxDatas);
            } else {
                // Get all root categories from cloud
                try {
                    $cloudRootFolders = $this->dropbox->getAllFiles($datas['id']);
                } catch (\Exception $e) {
                    return;
                }
                if (is_array($cloudRootFolders) && count($cloudRootFolders) > 0) {
                    $rootCloudParents = array();
                    foreach ($cloudRootFolders as $cloudData) {
                        if ($cloudData['.tag'] === 'folder') {
                            $dropboxDatas = array(
                                'id' => $cloudData['id'],
                                'folder_parent' => 0,
                                'name' => mb_convert_encoding($cloudData['name'], 'HTML-ENTITIES', 'UTF-8'),
                                'action' => 'wpfd_sync_dropbox',
                                'type' => 'folder',
                                'datas' => $cloudData,
                            );
                            $rootCloudParents[] = $cloudData['id'];
                            $this->addOnceToQueue($dropboxDatas);
                        } else {
                            // todo: check support file type to reduce unnecessary request
                            $fpath = pathinfo($cloudData['path_display']);
                            $fileExtension = $fpath['extension'];
                            if ($this->generatePreview && in_array($fileExtension, $this->previewAllowed)) {
                                $datas = array(
                                    'id' => $cloudData['id'],
                                    'action' => 'wpfd_download_cloud_thumbnail',
                                    'url' => $cloudData['path_lower'],
                                    'cloudType' => 'dropbox',
                                    'ext' => $fpath['extension'],
                                    'updated' => $cloudData['server_modified']
                                );
                                $this->addOnceToQueue($datas);
                            }
                        }
                    }
                    // then remove the file and folder not exist
                    $datas = array(
                        'id' => '',
                        'folder_id' => 0,
                        'cloud_folder_id' => $datas['id'],
                        'action' => 'wpfd_dropbox_remove',
                        'cloud_folders_list' => $rootCloudParents,
                        'time' => time()
                    );
                    $this->addNewToQueue($datas);
                }
            }
        } else {
            $message = array();
            $name = html_entity_decode($datas['name']);
            $wpfd_category_taxonomy = 'wpfd-category';
            // check folder exists
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND BINARY meta_value = %s', array('wpfd_drive_id', $datas['id'])));
//            $row = \WpfdAddonHelper::getTermIdByDropboxPath($datas['datas']['path_lower']);
            if (!$row) {
                $inserted = wp_insert_term($name, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                if (is_wp_error($inserted)) {
                    $folder_id = (int) $inserted->get_error_data('term_exists');
                    $message[] = 'category exists4';
                } else {
                    $folder_id = (int)$inserted['term_id'];
                    $message[] = 'new folder inserted';
                }

                update_term_meta($folder_id, 'wpfd_drive_id', $datas['id']);
                update_term_meta($folder_id, 'wpfd_drive_path', $datas['datas']['path_lower']);
                update_term_meta($folder_id, 'wpfd_drive_type', 'dropbox');
//                \WpfdAddonHelper::addDropboxConfigParam($folder_id, $datas['datas']);
            } else {
                $folder_id = (int)$row->term_id;
                $exist_folder = get_term($folder_id, $wpfd_category_taxonomy);
                // if folder exists, then update parent and name
                if (!is_wp_error($exist_folder) && !empty($datas['folder_parent']) && (int)$exist_folder->parent !== (int)$datas['folder_parent']) {
                    $parent_exist = get_term((int)$datas['folder_parent'], $wpfd_category_taxonomy);
                    if (!is_wp_error($parent_exist)) {
                        wp_update_term($folder_id, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                        update_term_meta($folder_id, 'wpfd_drive_path', $datas['datas']['path_lower']);
                        $message[] = 'updated parent';
                    }
                }

                if ($name !== $exist_folder->name) {
                    wp_update_term($folder_id, $wpfd_category_taxonomy, array('name' => $name));
                    update_term_meta($folder_id, 'wpfd_drive_path', $datas['datas']['path_lower']);
                    $message[] = 'updated name';
                }

                update_term_meta($folder_id, 'wpfd_drive_path', $datas['datas']['path_lower']);
                update_term_meta($folder_id, 'wpfd_drive_type', 'dropbox');
            }

            if (!empty($folder_id)) {
                $responses = array();
                $responses['folder_id'] = (int)$folder_id;
                $responses['message'] = $message;
//                update_term_meta($responses['folder_id'], 'wpfd_drive_type', 'google_drive');
                $this->updateQueueTermMeta((int)$responses['folder_id'], (int)$element_id);
                $this->updateResponse((int)$element_id, $responses);
                // find childs element to add to queue
                $this->addDropboxChildsToQueue($datas['id'], $folder_id);
            }
        }
        \WpfdAddonHelper::setDropboxParam('last_log', date('Y-m-d H:i:s'));

        return true;
    }

    /**
     * Add single folder sync
     *
     * @return boolean
     */
    public function addDropBoxSync()
    {
        // Check Google Drive credentials for sure
        if (is_null($this->dropbox)) {
            return true;
        }
        $id = Utilities::getInput('id', 'POST', 'string'); // Number
        $parent_id = Utilities::getInput('parent_id', 'POST', 'string'); // Number
        if ($id !== '') {
            try {
                $id = \WpfdAddonHelper::getDropboxIdByTermId($id);
                $cloudData = $this->dropbox->getDropboxFile($id);
            } catch (\Exception $e) {
                return true;
            }

            $dropboxDatas = array(
                'id' => $cloudData['id'],
                'folder_parent' => $parent_id,
                'name' => mb_convert_encoding($cloudData['name'], 'HTML-ENTITIES', 'UTF-8'),
                'action' => 'wpfd_sync_dropbox',
                'type' => 'folder',
                'datas' => $cloudData,
            );
            $rootCloudParents[] = $cloudData['id'];
            $this->addOnceToQueue($dropboxDatas);
        }
    }

    /**
     * Add Dropbox Child to queue
     *
     * @param string $folderID      Folder id
     * @param string $folder_parent Folder parent
     *
     * @return void
     */
    public function addDropboxChildsToQueue($folderID, $folder_parent)
    {
        $pageToken = null;

        try {
            $childs = $this->dropbox->getAllFiles($folderID);
        } catch (\Exception $ex) {
            return;
        }


        // get folder childs list on cloud
        $cloud_folders_list = array();
        // Create files in media library
        if (!empty($childs)) {
            foreach ($childs as $child) {
                if ($child['.tag'] === 'folder') {
                    $datas = array(
                        'id' => $child['id'],
                        'folder_parent' => $folder_parent,
                        'name' => mb_convert_encoding($child['name'], 'HTML-ENTITIES', 'UTF-8'),
                        'action' => 'wpfd_sync_dropbox',
                        'cloud_parent' => $folderID,
                        'datas' => $child
                    );

                    $cloud_folders_list[] = $child['id'];
                    $datas['type'] = 'folder';
                    $this->addNewToQueue($datas);
                } else {
                    // todo: check support file type to reduce unnecessary request
                    $fpath = pathinfo($child['path_display']);
                    $fileExtension = $fpath['extension'];
                    if ($this->generatePreview && in_array($fileExtension, $this->previewAllowed)) {
                        $datas = array(
                            'id' => $child['id'],
                            'action' => 'wpfd_download_cloud_thumbnail',
                            'url' => $child['path_lower'],
                            'cloudType' => 'dropbox',
                            'ext' => $fpath['extension'],
                            'updated' => $child['server_modified']
                        );
                        $this->addOnceToQueue($datas);
                    }
                }
            }
        }
        // then remove the file and folder not exist
        $datas = array(
            'id' => '',
            'folder_id' => $folder_parent,
            'cloud_folder_id' => $folderID,
            'action' => 'wpfd_dropbox_remove',
            'cloud_folders_list' => $cloud_folders_list,
            'time' => time()
        );
        $this->addNewToQueue($datas);
    }

    /**
     * Dropbox Sync remove items
     *
     * @param mixed   $result     Task result
     * @param array   $datas      Task data
     * @param integer $element_id Task id
     *
     * @return boolean
     */
    public function syncRemoveDropboxItems($result, $datas, $element_id)
    {
        // Get media library files in current folder, then remove the folder not exist
        $folders = get_categories(array('hide_empty' => false, 'hierarchical' => false, 'taxonomy' => 'wpfd-category', 'parent' => (int)$datas['folder_id']));
        foreach ($folders as $folder) {
//            $drive_id = get_term_meta($folder->term_id, 'wpfd_drive_id', true);
            $drive_id = \WpfdAddonHelper::getDropboxIdByTermId($folder->term_id);
            if (empty($drive_id) || is_null($drive_id)) {
                continue;
            }
            $driveType = get_term_meta($folder->term_id, 'wpfd_drive_type', true);
            if (empty($driveType) || $driveType !== 'dropbox') {
                continue;
            }
            if (is_array($datas['cloud_folders_list']) && !empty($drive_id) && !in_array($drive_id, $datas['cloud_folders_list'])) {
                wp_delete_term($folder->term_id, 'wpfd-category');
            }
        }

        return true;
    }

    /**
     * Add AWS Root to queue
     *
     * @param boolean $newSync Is this is first time in admin
     *
     * @return void
     */
    public function addAwsRootToQueue($newSync = false)
    {
        if (!is_null($this->aws)) {
            $awsDatas = array(
                'id' => $this->awsBaseFolderId,
                'folder_parent' => -1,
                'name' => 'aws',
                'action' => 'wpfd_sync_aws',
                'type' => 'folder'
            );

            if ($newSync) {
                $this->addOnceToQueue($awsDatas);
            } else {
                $this->addToQueue($awsDatas);
            }
        }
    }

    /**
     * AWS synchronization
     *
     * @param boolean $result     Result
     * @param array   $datas      Data details
     * @param integer $element_id ID of queue element
     *
     * @return boolean
     */
    public function doAwsSync($result, $datas, $element_id)
    {
        // Check aws connection
        if (is_null($this->aws)) {
            return true;
        }

        $awsOption = get_option($this->awsOptionName, false);

        if (isset($datas['folder_parent']) && $datas['folder_parent'] === -1) {
            // Get all root categories from aws
            $awsRootFolders = array();
            $args = array(
                'Bucket' => $datas['id']
            );

            try {
                $args = array(
                    'Bucket' => $awsOption['awsBucketName'],
                    'Delimiter' => '/'
                );
                $awsRootFolders = $this->aws->getFoldersFilesFromBucket($args);
            } catch (\Exception $e) {
                return;
            }

            if (is_array($awsRootFolders) && count($awsRootFolders) > 0) {
                foreach ($awsRootFolders as $awsObject) {
                    $lastCharacter = substr($awsObject['Prefix'], -1);
                    $offset = strlen($awsObject['Prefix']) - 1;
                    if ($lastCharacter === '/' && strpos($awsObject['Prefix'], '/') === strpos($awsObject['Prefix'], '/', $offset)) {
                        $catName = substr_replace($awsObject['Prefix'], '', -1);
                        $awsDatas = array(
                            'id' => $catName,
                            'folder_parent' => 0,
                            'name' => $catName,
                            'action' => 'wpfd_sync_aws',
                            'type' => 'folder',
                            'Bucket' => $datas['id']
                        );
                        $this->addOnceToQueue($awsDatas);
                    }
                }
            }
        } else {
            $message = array();
            $name = html_entity_decode($datas['name']);
            $wpfd_category_taxonomy = 'wpfd-category';
            // check folder exists
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_path', $datas['id'])));

            if (!$row) {
                $inserted = wp_insert_term($name, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                if (is_wp_error($inserted)) {
                    $folder_id = (int)$inserted->get_error_data('term_exists');
                    $message[] = 'category exists4';
                } else {
                    $folder_id = (int)$inserted['term_id'];
                    $message[] = 'new folder inserted';
                }

                update_term_meta($folder_id, 'wpfd_drive_path', $datas['id']);
                update_term_meta($folder_id, 'wpfd_drive_type', 'aws');
            } else {
                $folder_id = (int)$row->term_id;
                $exist_folder = get_term($folder_id, $wpfd_category_taxonomy);
                // if folder exists, then update parent and name
                if (!is_wp_error($exist_folder) && !is_null($exist_folder) && !empty($datas['folder_parent']) && (int)$exist_folder->parent !== (int)$datas['folder_parent']) {
                    $parent_exist = get_term((int)$datas['folder_parent'], $wpfd_category_taxonomy);
                    if (!is_wp_error($parent_exist)) {
                        wp_update_term($folder_id, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                        update_term_meta($folder_id, 'wpfd_drive_type', 'aws');
                        $message[] = 'updated parent';
                    }
                }

                if ($name !== $exist_folder->name) {
                    wp_update_term($folder_id, $wpfd_category_taxonomy, array('name' => $name));
                    update_term_meta($folder_id, 'wpfd_drive_type', 'aws');
                    $message[] = 'updated name';
                }
            }

            if (!empty($folder_id)) {
                $responses = array();
                $responses['folder_id'] = (int)$folder_id;
                $responses['message'] = $message;
                $this->updateQueueTermMeta((int)$responses['folder_id'], (int)$element_id);
                $this->updateResponse((int)$element_id, $responses);
                // find childs element to add to queue
                $this->addAwsChildsToQueue($datas['id'], $folder_id, $datas['Bucket']);
            }
        }

        // Update last log
        \WpfdAddonHelper::setAwsParam('last_log', date('Y-m-d H:i:s'));

        return true;
    }

    /**
     * Add single folder sync
     *
     * @return boolean
     */
    public function addAwsSync()
    {
        // Check aws connection
        if (is_null($this->aws)) {
            return true;
        }
        $id = Utilities::getInput('id', 'POST', 'string'); // Number
        $parent_id = Utilities::getInput('parent_id', 'POST', 'string'); // Number
        if ($id !== '') {
            try {
                $awsOption = get_option($this->awsOptionName, false);
                $folder_path = \WpfdAddonHelper::getAwsIdByTermId($id);
                $args = array(
                    'Bucket' => $awsOption['awsBucketName'],
                    'Prefix' => $folder_path . '/',
                    'Delimiter' => '/'
                );
                $cloudData = $this->aws->getFoldersFilesFromBucket($args);
            } catch (\Exception $e) {
                return true;
            }

            $parts = explode('/', $folder_path);
            $catName = end($parts);

            $awsDatas = array(
                'id' => $folder_path,
                'folder_parent' => $parent_id,
                'name' => $catName,
                'action' => 'wpfd_sync_aws',
                'cloud_parent' => $folder_path . '/',
                'type' => 'folder',
                'datas' => $cloudData,
                'Bucket' => $awsOption['awsBucketName']
            );

            $this->addOnceToQueue($awsDatas);
        }
    }

    /**
     * Add AWS Child to queue
     *
     * @param string $folderID      Folder id
     * @param string $folder_parent Folder parent
     * @param string $bucketName    Bucket name
     *
     * @return void
     */
    public function addAwsChildsToQueue($folderID, $folder_parent, $bucketName)
    {
        $pageToken = null;
        try {
            $folder_path = $folderID.'/';
            $args = array(
                'Bucket' => $bucketName,
                'Prefix' => $folder_path,
                'Delimiter' => '/'
            );
            $childs = $this->aws->getFoldersFilesFromBucket($args);
        } catch (\Exception $e) {
            return;
        }

        // get folder childs list on cloud
        $cloud_folders_list = array();
        // Create folder
        if (!empty($childs)) {
            foreach ($childs as $child) {
                $lastCharacter = substr($child['Prefix'], -1);
                if ($lastCharacter === '/' && $child['Prefix'] !== $folder_path) {
                    $path = substr_replace($child['Prefix'], '', -1);
                    $parts = explode('/', $path);
                    $catName = end($parts);
                    $cloud_folders_list[] = $path;

                    $datas = array(
                        'id' => $path,
                        'folder_parent' => $folder_parent,
                        'name' => $catName,
                        'action' => 'wpfd_sync_aws',
                        'cloud_parent' => $folderID,
                        'type' => 'folder',
                        'datas' => $child,
                        'Bucket' => $bucketName
                    );

                    $this->addNewToQueue($datas);
                }
            }
        }

        // Then remove the folder not exist
        $datas = array(
            'id' => '',
            'folder_id' => $folder_parent,
            'cloud_folder_id' => $folderID,
            'action' => 'wpfd_aws_remove',
            'cloud_folders_list' => $cloud_folders_list,
            'time' => time()
        );
        $this->addNewToQueue($datas);
    }

    /**
     * AWS Sync remove items
     *
     * @param mixed   $result     Task result
     * @param array   $datas      Task data
     * @param integer $element_id Task id
     *
     * @return boolean
     */
    public function syncRemoveAwsItems($result, $datas, $element_id)
    {
        // Get current folder, then remove the folder not exist
        $folders = get_categories(array('hide_empty' => false, 'hierarchical' => false, 'taxonomy' => 'wpfd-category', 'parent' => (int)$datas['folder_id']));
        foreach ($folders as $folder) {
            $drive_path = get_term_meta($folder->term_id, 'wpfd_drive_path', true);
            if (empty($drive_path) || is_null($drive_path)) {
                continue;
            }
            $driveType = get_term_meta($folder->term_id, 'wpfd_drive_type', true);
            if (empty($driveType) || $driveType !== 'aws') {
                continue;
            }
            if (is_array($datas['cloud_folders_list']) && !empty($drive_path) && !in_array($drive_path, $datas['cloud_folders_list'])) {
                wp_delete_term($folder->term_id, 'wpfd-category');
            }
        }

        return true;
    }

    /**
     * Add Nextcloud Root to queue
     *
     * @param boolean $newSync Is this is first time in admin
     *
     * @return void
     */
    public function addNextcloudRootToQueue($newSync = false)
    {
        if (!is_null($this->nextcloud)) {
            $nextcloudDatas = array(
                'id' => $this->nextcloudBaseFolderId,
                'folder_parent' => -1,
                'name' => 'nextcloud',
                'action' => 'wpfd_sync_nextcloud',
                'type' => 'folder'
            );

            if ($newSync) {
                $this->addOnceToQueue($nextcloudDatas);
            } else {
                $this->addToQueue($nextcloudDatas);
            }
        }
    }

    /**
     * Nextcloud synchronization
     *
     * @param boolean $result     Result
     * @param array   $datas      Data details
     * @param integer $element_id ID of queue element
     *
     * @return boolean
     */
    public function doNextcloudSync($result, $datas, $element_id)
    {
        // Check Nextcloud connection
        if (is_null($this->nextcloud)) {
            return true;
        }

        $nextcloudOption = get_option($this->nextcloudOptionName, false);

        if (isset($datas['folder_parent']) && $datas['folder_parent'] === -1) {
            // Get all root categories from Nextcloud
            $nextcloudRootFolders = array();
            try {
                $nextcloudRootFolders = $this->nextcloud->getFilesInFolder($nextcloudOption['nextcloudRootFolder']);
            } catch (\Exception $e) {
                return;
            }

            if (!empty($nextcloudRootFolders)) {
                foreach ($nextcloudRootFolders as $i => $item) {
                    if ((int)$i === 0) {
                        continue;
                    }
                    if (!empty($item['propstat'][0]['prop']['resourcetype']) && isset($item['propstat'][0]['prop']['resourcetype']['collection']) && !empty($item['href'])) {
                        $s = 'remote.php/dav/files/' . $nextcloudOption['nextcloudUsername'] . '/';
                        $paths = explode($s, $item['href']);
                        $id = trim($paths[1], '/');
                        $catName = basename(urldecode($item['href']));
                        $nextcloudDatas = array(
                            'id' => $id,
                            'folder_parent' => 0,
                            'name' => $catName,
                            'action' => 'wpfd_sync_nextcloud',
                            'type' => 'folder'
                        );
                        $cloud_folders_list[] = $nextcloudDatas['id'];
                        $this->addOnceToQueue($nextcloudDatas);
                    }
                }

                // then remove the file and folder not exist
                $datas = array(
                    'id' => '',
                    'folder_id' => 0,
                    'cloud_folder_id' => $nextcloudOption['nextcloudRootFolder'],
                    'action' => 'wpfd_nextcloud_remove',
                    'cloud_folders_list' => $cloud_folders_list,
                    'time' => time()
                );
                $this->addNewToQueue($datas);
            }
        } else {
            $message = array();
            $name = html_entity_decode($datas['name']);
            $wpfd_category_taxonomy = 'wpfd-category';
            // check folder exists
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_path', $datas['id'])));

            if (!$row) {
                $inserted = wp_insert_term($name, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                if (is_wp_error($inserted)) {
                    $folder_id = (int)$inserted->get_error_data('term_exists');
                    $message[] = 'category exists4';
                } else {
                    $folder_id = (int)$inserted['term_id'];
                    $message[] = 'new folder inserted';
                }

                update_term_meta($folder_id, 'wpfd_drive_path', $datas['id']);
                update_term_meta($folder_id, 'wpfd_drive_type', 'nextcloud');
            } else {
                $folder_id = (int)$row->term_id;
                $exist_folder = get_term($folder_id, $wpfd_category_taxonomy);
                // if folder exists, then update parent and name
                if (!is_wp_error($exist_folder) && !is_null($exist_folder) && !empty($datas['folder_parent']) && (int)$exist_folder->parent !== (int)$datas['folder_parent']) {
                    $parent_exist = get_term((int)$datas['folder_parent'], $wpfd_category_taxonomy);
                    if (!is_wp_error($parent_exist)) {
                        wp_update_term($folder_id, $wpfd_category_taxonomy, array('parent' => (int)$datas['folder_parent']));
                        update_term_meta($folder_id, 'wpfd_drive_type', 'nextcloud');
                        $message[] = 'updated parent';
                    }
                }

                if ($name !== $exist_folder->name) {
                    wp_update_term($folder_id, $wpfd_category_taxonomy, array('name' => $name));
                    update_term_meta($folder_id, 'wpfd_drive_type', 'nextcloud');
                    $message[] = 'updated name';
                }
            }

            if (!empty($folder_id)) {
                $responses = array();
                $responses['folder_id'] = (int)$folder_id;
                $responses['message'] = $message;
                $this->updateQueueTermMeta((int)$responses['folder_id'], (int)$element_id);
                $this->updateResponse((int)$element_id, $responses);
                // find childs element to add to queue
                $this->addNextcloudChildsToQueue($datas['id'], $folder_id);
            }
        }

        // Update last log
        \WpfdAddonHelper::setNextcloudParam('last_log', date('Y-m-d H:i:s'));

        return true;
    }

    /**
     * Add single folder sync
     *
     * @return boolean
     */
    public function addNextcloudSync()
    {
        // Check nextcloud connection
        if (is_null($this->nextcloud)) {
            return true;
        }
        $id = Utilities::getInput('id', 'POST', 'string'); // Number
        $parent_id = Utilities::getInput('parent_id', 'POST', 'string'); // Number
        if ($id !== '') {
            try {
                $folder_path = \WpfdAddonHelper::getNextcloudPathByTermId($id);
            } catch (\Exception $e) {
                return true;
            }

            $parts = explode('/', $folder_path);
            $catName = end($parts);

            $nextcloudDatas = array(
                'id' => $folder_path,
                'folder_parent' => $parent_id,
                'name' => $catName,
                'action' => 'wpfd_sync_nextcloud',
                'cloud_parent' => $folder_path,
                'type' => 'folder'
            );

            $this->addOnceToQueue($nextcloudDatas);
        }
    }

    /**
     * Add Nextcloud Child to queue
     *
     * @param string $folderID      Folder id
     * @param string $folder_parent Folder parent
     *
     * @return void
     */
    public function addNextcloudChildsToQueue($folderID, $folder_parent)
    {
        $pageToken = null;
        try {
            $childs = $this->nextcloud->getFilesInFolder($folderID);
        } catch (\Exception $e) {
            return;
        }

        $nextcloudOption = get_option($this->nextcloudOptionName, false);

        $cloud_folders_list = array();
        if (!empty($childs)) {
            foreach ($childs as $i => $child) {
                if ((int)$i === 0) {
                    continue;
                }
                if (!empty($child['propstat'][0]['prop']['resourcetype']) && isset($child['propstat'][0]['prop']['resourcetype']['collection']) && !empty($child['href'])) {
                    $s = 'remote.php/dav/files/' . $nextcloudOption['nextcloudUsername'] . '/';
                    $paths = explode($s, $child['href']);
                    $id = trim($paths[1], '/');
                    $catName = basename(urldecode($child['href']));
                    $nextcloudDatas = array(
                        'id' => $id,
                        'folder_parent' => $folder_parent,
                        'name' => $catName,
                        'action' => 'wpfd_sync_nextcloud',
                        'cloud_parent' => $folderID,
                        'type' => 'folder'
                    );
                    $cloud_folders_list[] = $id;
                    $this->addOnceToQueue($nextcloudDatas);
                }
            }
        }

        // Then remove the folder not exist
        $datas = array(
            'id' => '',
            'folder_id' => $folder_parent,
            'cloud_folder_id' => $folderID,
            'action' => 'wpfd_nextcloud_remove',
            'cloud_folders_list' => $cloud_folders_list,
            'time' => time()
        );
        $this->addNewToQueue($datas);
    }

    /**
     * Nextcloud Sync remove items
     *
     * @param mixed   $result     Task result
     * @param array   $datas      Task data
     * @param integer $element_id Task id
     *
     * @return boolean
     */
    public function syncRemoveNextcloudItems($result, $datas, $element_id)
    {
        // Get current folder, then remove the folder not exist
        $folders = get_categories(array('hide_empty' => false, 'hierarchical' => false, 'taxonomy' => 'wpfd-category', 'parent' => (int)$datas['folder_id']));
        foreach ($folders as $folder) {
            $drive_path = get_term_meta($folder->term_id, 'wpfd_drive_path', true);
            if (empty($drive_path) || is_null($drive_path)) {
                continue;
            }
            $driveType = get_term_meta($folder->term_id, 'wpfd_drive_type', true);
            if (empty($driveType) || $driveType !== 'nextcloud') {
                continue;
            }
            if (is_array($datas['cloud_folders_list']) && !empty($drive_path) && !in_array($drive_path, $datas['cloud_folders_list'])) {
                $child_terms = get_term_children($folder->term_id, 'wpfd-category');
                if (!is_wp_error($child_terms)) {
                    foreach ($child_terms as $child_term_id) {
                        wp_delete_term($child_term_id, 'wpfd-category');
                    }
                }
                wp_delete_term($folder->term_id, 'wpfd-category');
            }
        }

        return true;
    }

    /**
     * Map Old Google Drive categories params
     *
     * @return void
     */
    private function mapGoogleDriveParamsToMetaData()
    {
        global $wpdb;
        $categoriesParamName = '_wpfdAddon_cloud_category_params';
        // Made sure this function run once
        $params = get_option($categoriesParamName, false);
        if (!$params || !is_array($params)) {
            return;
        }

        if (is_array($params) && !isset($params['googledrive'])) {
            return;
        }

        foreach ($params['googledrive'] as $cloudParam) {
            if (!term_exists($cloudParam['termId'])) {
                continue;
            }

            update_term_meta($cloudParam['termId'], 'wpfd_drive_id', $cloudParam['idCloud']);
            update_term_meta($cloudParam['termId'], 'wpfd_drive_type', 'googleDrive');
        }

        //delete_option($categoriesParamName);
        $wpdb->query($wpdb->prepare('UPDATE ' . $wpdb->options . ' SET option_name = %s WHERE option_name = %s', $categoriesParamName . '_old', $categoriesParamName));
    }

    /**
     * Map Old Google Team Drive categories params
     *
     * @return void
     */
    private function mapGoogleTeamDriveParamsToMetaData()
    {
        global $wpdb;
        $categoriesParamName = '_wpfdAddon_cloud_team_drive_category_params';
        // Made sure this function run once
        $params = get_option($categoriesParamName, false);
        if (!$params || !is_array($params)) {
            return;
        }

        if (is_array($params) && !isset($params['googleteamdrive'])) {
            return;
        }

        foreach ($params['googleteamdrive'] as $cloudParam) {
            if (!term_exists($cloudParam['termId'])) {
                continue;
            }

            update_term_meta($cloudParam['termId'], 'wpfd_drive_id', $cloudParam['idCloud']);
            update_term_meta($cloudParam['termId'], 'wpfd_drive_type', 'googleTeamDrive');
        }

        // Delete_option($categoriesParamName);
        $wpdb->query($wpdb->prepare('UPDATE ' . $wpdb->options . ' SET option_name = %s WHERE option_name = %s', $categoriesParamName . '_old', $categoriesParamName));
    }

    /**
     * Map Old Onedrive categories params
     *
     * @return void
     */
    private function mapOneDriveParamsToMetaData()
    {
        global $wpdb;
        $categoriesParamName = '_wpfdAddon_onedrive_category_params';
        // Made sure this function run once
        $params = get_option($categoriesParamName, false);
        if (!$params || !is_array($params)) {
            return;
        }

        if (is_array($params) && !isset($params['onedrive'])) {
            return;
        }

        foreach ($params['onedrive'] as $cloudParam) {
            if (!term_exists($cloudParam['termId'])) {
                continue;
            }

            update_term_meta($cloudParam['termId'], 'wpfd_drive_id', $cloudParam['oneDriveId']);
            update_term_meta($cloudParam['termId'], 'wpfd_drive_type', 'onedrive');
        }

        //delete_option($this->oneDriveOptionName);
        $wpdb->query($wpdb->prepare('UPDATE ' . $wpdb->options . ' SET option_name = %s WHERE option_name = %s', $categoriesParamName . '_old', $categoriesParamName));
    }

    /**
     * Map Old Onedrive Business categories params
     *
     * @return void
     */
    private function mapOneDriveBusinessParamsToMetaData()
    {
        global $wpdb;
        $categoriesParamName = '_wpfdAddon_onedrive_business_category_params';
        // Made sure this function run once
        $params = get_option($categoriesParamName, false);
        if (!$params || !is_array($params)) {
            return;
        }

        foreach ($params as $cloudParam) {
            if (!term_exists($cloudParam['termId'])) {
                continue;
            }

            update_term_meta($cloudParam['termId'], 'wpfd_drive_id', $cloudParam['oneDriveId']);
            update_term_meta($cloudParam['termId'], 'wpfd_drive_type', 'onedrive_business');
        }

        //delete_option($this->oneDriveBusinessOptionName);
        $wpdb->query($wpdb->prepare('UPDATE ' . $wpdb->options . ' SET option_name = %s WHERE option_name = %s', $categoriesParamName . '_old', $categoriesParamName));
    }

    /**
     * Map Old Dropbox categories params
     *
     * @return void
     */
    private function mapDropboxParamsToMetaData()
    {
        global $wpdb;
        $categoriesParamName = '_wpfdAddon_dropbox_category_params';
        // Made sure this function run once
        $params = get_option($categoriesParamName, false);
        if (!$params || !is_array($params)) {
            return;
        }

        foreach ($params as $cloudParam) {
            if (!term_exists($cloudParam['termId'])) {
                continue;
            }

            update_term_meta($cloudParam['termId'], 'wpfd_drive_id', $cloudParam['id']);
            update_term_meta($cloudParam['termId'], 'wpfd_drive_path', $cloudParam['idDropbox']);
            update_term_meta($cloudParam['termId'], 'wpfd_drive_type', 'dropbox');
        }

        //delete_option($this->dropboxOptionName);
        $wpdb->query($wpdb->prepare('UPDATE ' . $wpdb->options . ' SET option_name = %s WHERE option_name = %s', $categoriesParamName . '_old', $categoriesParamName));
    }

    /**
     * Map Old AWS categories params
     *
     * @return void
     */
    private function mapAwsParamsToMetaData()
    {
        global $wpdb;
        $categoriesParamName = '_wpfdAddon_aws_category_params';
        // Made sure this function run once
        $params = get_option($categoriesParamName, false);
        if (!$params || !is_array($params)) {
            return;
        }

        foreach ($params as $cloudParam) {
            if (!term_exists($cloudParam['termId'])) {
                continue;
            }

            update_term_meta($cloudParam['termId'], 'wpfd_drive_path', $cloudParam['idAws']);
            update_term_meta($cloudParam['termId'], 'wpfd_drive_type', 'aws');
        }

        //delete_option($this->awsOptionName);
        $wpdb->query($wpdb->prepare('UPDATE ' . $wpdb->options . ' SET option_name = %s WHERE option_name = %s', $categoriesParamName . '_old', $categoriesParamName));
    }

    /**
     * Maybe generate Google Drive files
     *
     * @param array $datas File data
     *
     * @return void
     */
    private function mayBeGeneratePreviewGoogle($datas)
    {
        // Get all files and add generate preview to task when modifiedTime changed
        if ($this->generatePreview) {
            $files = $this->googleDrive->getAllFileWithThumbnailInFolder($datas['id']);
            if ($files) {
                /* @var Google_Service_Drive_DriveFile $file */
                foreach ($files as $file) {
                    if ($file->getHasThumbnail()) {
                        $thumbnailLink = $file->getThumbnailLink();
                        $datas = array(
                            'id' => $file->getId(),
                            'action' => 'wpfd_download_cloud_thumbnail',
                            'url' => $thumbnailLink,
                            'cloudType' => 'googleDrive',
                            'ext' => $file->getFileExtension() ? $file->getFileExtension() : \WpfdAddonHelper::getExt($file->getOriginalFilename()),
                            'updated' => $file->getModifiedTime()
                        );

                        $this->addToQueue($datas);
                    }
                }
            }
        }
    }

    /**
     * Maybe generate Onedrive files
     *
     * @param array $datas File data
     *
     * @return boolean
     */
    private function maybeGeneratePreviewOnedrive($datas)
    {
        // Get all files and add generate preview to task when modifiedTime changed
        if ($this->generatePreview) {
            try {
                $files = $this->oneDrive->getAllFileInFolder($datas['id']);
                if (is_array($files) && count($files)) {
                    foreach ($files as $file) {
                        $fileExtension = \WpfdAddonHelper::getExt($file['name']);
                        if (!in_array($fileExtension, $this->previewAllowed)) {
                            continue;
                        }
                        // Onedrive not support thumbnail for Excel yet.
                        if (in_array($fileExtension, array('xls', 'xlsx'))) {
                            continue;
                        }
                        $datas = array(
                            'id' => $file['id'],
                            'action' => 'wpfd_download_cloud_thumbnail',
                            'url' => true,
                            'cloudType' => 'onedrive',
                            'ext' => \WpfdAddonHelper::getExt($file['name']),
                            'updated' => $file['updated']
                        );

                        $this->addToQueue($datas);
                    }
                }
            } catch (Exception $e) {
                return true;
            }
        }
    }

    /**
     * Maybe generate Onedrive Bussinses files
     *
     * @param array $datas File data
     *
     * @return boolean
     */
    private function maybeGeneratePreviewOnedriveBusiness($datas)
    {
        // Get all files and add generate preview to task when modifiedTime changed
        if ($this->generatePreview) {
            try {
                $files = $this->oneDriveBusiness->getAllFileInFolder($datas['id']);
            } catch (\Exception $e) {
                return false;
            }
            if (is_array($files) && count($files)) {
                foreach ($files as $file) {
                    $fileExtension = \WpfdAddonHelper::getExt($file['name']);
                    if (!in_array($fileExtension, $this->previewAllowed)) {
                        continue;
                    }
                    // Onedrive Business not support thumbnail for Excel yet.
                    if (in_array($fileExtension, array('xls', 'xlsx'))) {
                        continue;
                    }
                    $datas = array(
                        'id' => $file['id'],
                        'action' => 'wpfd_download_cloud_thumbnail',
                        'url' => true,
                        'cloudType' => 'onedrive_business',
                        'ext' => \WpfdAddonHelper::getExt($file['name']),
                        'updated' => $file['updated']
                    );
                    $this->addToQueue($datas);
                }
            }
        }
    }
}
