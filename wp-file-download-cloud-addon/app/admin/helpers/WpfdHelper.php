<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

defined('ABSPATH') || die();

use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;

/**
 * Class WpfdAddonHelper
 */
class WpfdAddonHelper
{
    /**
     * Get all cloud config
     *
     * @return mixed
     */
    public static function getAllCloudConfigs()
    {
        $default = array(
            'googleClientId'     => '',
            'googleClientSecret' => '',
            'googleSyncTime'     => '30',
            'googleSyncMethod'   => 'sync_page_curl',
            'googleConnectedBy'  => get_current_user_id()
        );
        return get_option('_wpfdAddon_cloud_config', $default);
    }

    /**
     * Get cloud config old
     *
     * @return mixed
     */
    public static function getAllCloudConfigsOld()
    {
        return get_option('_wpfdAddon_cloud_config_old');
    }

    /**
     * Save cloud config
     *
     * @param array $data Cloud config data
     *
     * @return boolean
     */
    public static function saveCloudConfigs($data)
    {
        $result = update_option('_wpfdAddon_cloud_config', $data);

        return $result;
    }

    /**
     * Save cloud config old
     *
     * @param array $data Cloud config old data
     *
     * @return boolean
     */
    public static function saveCloudConfigsOld($data)
    {
        $result = update_option('_wpfdAddon_cloud_config_old', $data);

        return $result;
    }

    /**
     * Get all cloud team drive config
     *
     * @return mixed
     */
    public static function getAllCloudTeamDriveConfigs()
    {
        $default = array(
            'googleTeamDriveClientId'     => '',
            'googleTeamDriveClientSecret' => '',
            'googleTeamDriveSyncTime'     => '30',
            'googleTeamDriveSyncMethod'   => 'sync_page_curl',
            'googleTeamDriveConnectedBy'  => get_current_user_id()
        );
        return get_option('_wpfdAddon_cloud_team_drive_config', $default);
    }

    /**
     * Get cloud config old
     *
     * @return mixed
     */
    public static function getAllCloudTeamDriveConfigsOld()
    {
        return get_option('_wpfdAddon_cloud_team_drive_config_old');
    }

    /**
     * Save cloud config
     *
     * @param array $data Cloud config data
     *
     * @return boolean
     */
    public static function saveCloudTeamDriveConfigs($data)
    {
        return update_option('_wpfdAddon_cloud_team_drive_config', $data);
    }

    /**
     * Save cloud config old
     *
     * @param array $data Cloud config old data
     *
     * @return boolean
     */
    public static function saveCloudTeamDriveConfigsOld($data)
    {
        $result = update_option('_wpfdAddon_cloud_team_drive_config_old', $data);

        return $result;
    }

    /**
     * Get data config by server name
     *
     * @param string $name Config name
     *
     * @return array|null
     */
    public static function getDataConfigBySeverName($name)
    {
        $googleDriveParams = array();
        $params = self::getAllCloudConfigs();
        if ($params && is_countable($params)) {
            foreach ($params as $key => $val) {
                if (strpos($key, 'google') !== false) {
                    $googleDriveParams[$key] = $val;
                }
            }
            $result = null;
            switch ($name) {
                case 'google':
                    $result = $googleDriveParams;
                    break;
            }

            return $result;
        }

        return array();
    }

    /**
     * Get data config by cloud server name
     *
     * @param string $name Config name
     *
     * @return array|null
     */
    public static function getDataConfigByGoogleTeamDriveSever($name)
    {
        $googleTeamDriveParams = array();
        $params = self::getAllCloudTeamDriveConfigs();
        if ($params && is_countable($params)) {
            foreach ($params as $key => $val) {
                if (strpos($key, 'googleTeamDrive') !== false) {
                    $googleTeamDriveParams[$key] = $val;
                }
            }
            $result = null;
            switch ($name) {
                case 'googleTeamDrive':
                    $result = $googleTeamDriveParams;
                    break;
            }

            return $result;
        }

        return array();
    }

    /**
     * Get all cloud params
     *
     * @return mixed
     */
    public static function getAllCloudParams()
    {
        return get_option('_wpfdAddon_cloud_category_params');
    }

    /**
     * Get all cloud team drive params
     *
     * @return mixed
     */
    public static function getAllCloudTeamDriveParams()
    {
        return get_option('_wpfdAddon_cloud_team_drive_category_params');
    }

    /**
     * Get google drive params
     *
     * @return mixed
     */
    public static function getGoogleDriveParams()
    {
        $params = self::getAllCloudParams();
        return isset($params['googledrive']) ? $params['googledrive'] : false;
    }

    /**
     * Set cloud params
     *
     * @param string $key Option key
     * @param mixed  $val Option value
     *
     * @return mixed
     */
    public static function setCloudParam($key, $val)
    {
        $params       = self::getAllCloudConfigs();
        $params[$key] = $val;
        self::saveCloudConfigs($params);
    }

    /**
     * Set cloud team drive params
     *
     * @param string $key Option key
     * @param mixed  $val Option value
     *
     * @return mixed
     */
    public static function setCloudTeamDriveParam($key, $val)
    {
        $params       = self::getAllCloudTeamDriveConfigs();
        $params[$key] = $val;
        self::saveCloudTeamDriveConfigs($params);
    }


    /**
     * Get google drive by google id
     *
     * @param string $googleDriveId Google Drive Id
     *
     * @return boolean
     */
    public static function getTermIdGoogleDriveByGoogleId($googleDriveId)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_id', $googleDriveId)));
        if ($row) {
            $driveType =  get_term_meta($row->term_id, 'wpfd_drive_type', true);
            if (!is_wp_error($driveType) && $driveType === 'googleDrive') {
                return isset($row->term_id)? (int) $row->term_id : false;
            }
        }

        return false;
    }

    /**
     * Get term id by google team id
     *
     * @param string $googleTeamDriveId Google team drive id
     *
     * @return boolean
     */
    public static function getTermIdByGoogleTeamDriveId($googleTeamDriveId)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_id', $googleTeamDriveId)));
        if ($row) {
            $driveType =  get_term_meta($row->term_id, 'wpfd_drive_type', true);
            if (!is_wp_error($driveType) && $driveType === 'googleTeamDrive') {
                return isset($row->term_id)? (int) $row->term_id : false;
            }
        }

        return false;
    }

    /**
     * Get google drive id by term id
     *
     * @param integer $termId Term id
     *
     * @return boolean
     */
    public static function getGoogleDriveIdByTermId($termId)
    {
        $result = get_term_meta($termId, 'wpfd_drive_id', true);
        $type = get_term_meta($termId, 'wpfd_drive_type', true);

        if ($result && $type === 'googleDrive') {
            return $result;
        }

        return false;
    }

    /**
     * Get google team drive id by term id
     *
     * @param integer $termId Term id
     *
     * @return boolean
     */
    public static function getGoogleTeamDriveIdByTermId($termId)
    {
        $result = get_term_meta($termId, 'wpfd_drive_id', true);
        $type = get_term_meta($termId, 'wpfd_drive_type', true);

        if ($result && $type === 'googleTeamDrive') {
            return $result;
        }

        return false;
    }

    /**
     * Get cat id by cloud
     *
     * @param string $cloud_id Cloud
     *
     * @return boolean
     */
    public static function getCatIdByCloudId($cloud_id)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_id', $cloud_id)));
        if ($row) {
            return isset($row->term_id)? (int) $row->term_id : false;
        }

        return false;
    }

    /**
     * Get all google drive
     *
     * @return array
     */
    public static function getAllGoogleDriveId()
    {
        global $wpdb;
        $results = $wpdb->get_results('SELECT meta_value from ' . $wpdb->termmeta . ' WHERE meta_key = \'wpfd_drive_id\' AND meta_id IN (SELECT meta_id FROM ' . $wpdb->termmeta . ' WHERE meta_key = \'wpfd_drive_type\' AND meta_value = \'googleDrive\')', ARRAY_A);
        return array_map(function ($result) {
            return array('idCloud' => $result['meta_value']);
        }, $results);
    }

    /**
     * Get all google team drive
     *
     * @return array
     */
    public static function getAllGoogleTeamDriveId()
    {
        global $wpdb;
        $results = $wpdb->get_results('SELECT meta_value from ' . $wpdb->termmeta . ' WHERE meta_key = \'wpfd_drive_id\' AND meta_id IN (SELECT meta_id FROM ' . $wpdb->termmeta . ' WHERE meta_key = \'wpfd_drive_type\' AND meta_value = \'googleTeamDrive\')', ARRAY_A);
        return array_map(function ($result) {
            return array('idCloud' => $result['meta_value']);
        }, $results);
    }

    /**
     * Curl sync Interval
     *
     * @return float|integer
     */
    public static function curSyncInterval()
    {
        //get last_log param
        $config = self::getAllCloudConfigs();
        if (isset($config['last_log']) && !empty($config['last_log'])) {
            $last_log = $config['last_log'];
            $last_sync = (int)strtotime($last_log);
        } else {
            $last_sync = 0;
        }
        $time_new = (int)strtotime(date('Y-m-d H:i:s'));
        $timeInterval = $time_new - $last_sync;
        $curtime = $timeInterval / 60;
        return $curtime;
    }

    /**
     * Curl sync Interval
     *
     * @return float|integer
     */
    public static function curGoogleTeamDriveSyncInterval()
    {
        // Get last_log sync time param
        $config = self::getAllCloudTeamDriveConfigs();

        if (isset($config['google_team_drive_last_log']) && !empty($config['google_team_drive_last_log'])) {
            $last_log  = $config['google_team_drive_last_log'];
            $last_sync = (int)strtotime($last_log);
        } else {
            $last_sync = 0;
        }

        $time_new     = (int)strtotime(date('Y-m-d H:i:s'));
        $timeInterval = $time_new - $last_sync;
        $curtime      = $timeInterval / 60;

        return $curtime;
    }

    /**
     * Get ext
     *
     * @param string $file File name
     *
     * @return boolean|string
     */
    public static function getExt($file)
    {
        if (empty($file)) {
            return '';
        }

        $dot = strrpos($file, '.') + 1;
        return substr($file, $dot);
    }

    /**
     * Strips the last extension off of a file name
     *
     * @param string $file The file name
     *
     * @return string The file name without the extension
     *
     * @since 11.1
     */
    public static function stripExt($file)
    {
        return preg_replace('#\.[^.]*$#', '', $file);
    }

    /**
     * Write log only in debug mode
     *
     * @param mixed $log Log data
     *
     * @return void
     */
    public static function writeLog($log)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_print_r, WordPress.PHP.DevelopmentFunctions.error_log_error_log -- This is login function
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
            // phpcs:enable
        }
    }

    /*----------- Dropbox -----------------*/
    /**
     * Get all dropbox config
     *
     * @return mixed
     */
    public static function getAllDropboxConfigs()
    {
        $default = array(
            'dropboxKey'          => '',
            'dropboxSecret'       => '',
            'dropboxBaseFolderId' => '',
            'dropboxSyncTime'     => '30',
            'last_log'            => '0',
            'dropboxSyncMethod'   => 'sync_page_curl',
            'dropboxConnectedBy'  => get_current_user_id()
        );
        return get_option('_wpfdAddon_dropbox_config', $default);
    }

    /**
     * Set dropbox param
     *
     * @param string $key Param key
     * @param mixed  $val Param Value
     *
     * @return void
     */
    public static function setDropboxParam($key, $val)
    {
        $params = self::getAllDropboxConfigs();
        $params[$key] = $val;
        self::saveDropboxConfigs($params);
    }
    /**
     * Save dropbox config
     *
     * @param array $data Config data
     *
     * @return boolean
     */
    public static function saveDropboxConfigs($data)
    {
        $result = update_option('_wpfdAddon_dropbox_config', $data);

        return $result;
    }

    /**
     * Get data config by dropbox
     *
     * @param string $name Data name
     *
     * @return array|null
     */
    public static function getDataConfigByDropbox($name)
    {
        $DropboxParams = array();
        if (self::getAllDropboxConfigs()) {
            foreach (self::getAllDropboxConfigs() as $key => $val) {
                if (strpos($key, 'dropbox') !== false) {
                    $DropboxParams[$key] = $val;
                }
            }
            $result = null;
            switch ($name) {
                case 'dropbox':
                    $result = $DropboxParams;
                    break;
            }

            return $result;
        }

        return null;
    }


    /**
     * Get id by termID
     *
     * @param integer $termId Term id
     *
     * @return boolean|string
     */
    public static function getDropboxIdByTermId($termId)
    {
        $result = get_term_meta($termId, 'wpfd_drive_id', true);
        $type = get_term_meta($termId, 'wpfd_drive_type', true);

        if ($result && $type === 'dropbox') {
            return $result;
        }

        return false;
    }
    /**
     * Get path by termID
     *
     * @param integer $termId Term id
     *
     * @return boolean|string
     */
    public static function getDropboxPathByTermId($termId)
    {
        return get_term_meta($termId, 'wpfd_drive_path', true);
    }

    /**
     * Get path display by termID
     *
     * @param integer $termId Term id
     *
     * @return boolean|string
     */
    public static function getDropboxPathDisplayByTermId($termId)
    {
        return get_term_meta($termId, 'wpfd_drive_path_display', true);
    }

    /**
     * Get term id by Path
     *
     * @param string $path Dropbox path
     *
     * @return boolean|integer|string
     */
    public static function getTermIdByDropboxPath($path)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_path', $path)));

        if ($row) {
            $driveType =  get_term_meta($row->term_id, 'wpfd_drive_type', true);
            if (!is_wp_error($driveType) && $driveType === 'dropbox') {
                return isset($row->term_id)? (int) $row->term_id : false;
            }
        }

        return false;
    }

    /**
     * Get path by id
     *
     * @param string $id Dropbox item id
     *
     * @return string|boolean
     */
    public static function getPathByDropboxId($id)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_id', $id)));

        if ($row) {
            $termId = isset($row->term_id) ? $row->term_id : false;
            if ($termId) {
                return get_term_meta($termId, 'wpfd_drive_path', true);
            }
        }
        return false;
    }

    /**
     * Set dropbox file info
     *
     * @param array $params Dropbox file info
     *
     * @return boolean
     */
    public static function setDropboxFileInfos($params)
    {
        $result = update_option('_wpfdAddon_dropbox_fileInfo', $params, false);

        return $result;
    }

    /**
     * Get Dropbox file info
     *
     * @return mixed
     */
    public static function getDropboxFileInfos()
    {
        return get_option('_wpfdAddon_dropbox_fileInfo');
    }

    /**
     * Get Dropbox file info
     *
     * @param string $idFile     Dropbox file ID
     * @param string $idCategory Dropbox category ID
     *
     * @return mixed
     */
    public static function getDropboxFileInfo($idFile, $idCategory)
    {
        $allFileInfos =  get_option('_wpfdAddon_dropbox_fileInfo');
        if (isset($allFileInfos[$idCategory]) && isset($allFileInfos[$idCategory][$idFile])) {
            return $allFileInfos[$idCategory][$idFile];
        } else {
            return array();
        }
    }

    /**
     * Curl sync interval dropbox
     *
     * @return float|integer
     */
    public static function curSyncIntervalDropbox()
    {
        // Get last_log param
        $config = self::getAllDropboxConfigs();
        if (isset($config['last_log']) && !empty($config['last_log'])) {
            $last_log = $config['last_log'];
            $last_sync = (int)strtotime($last_log);
        } else {
            $last_sync = 0;
        }
        $time_new = (int)strtotime(date('Y-m-d H:i:s'));
        $timeInterval = $time_new - $last_sync;
        $curtime = $timeInterval / 60;

        return $curtime;
    }
    /*----------- OneDrive Business --------*/
    /**
     * Get all OneDrive Business config
     *
     * @return array
     */
    public static function getAllOneDriveBusinessConfigs()
    {
        $default = array(
            'onedriveBusinessKey'         => '',
            'onedriveBusinessSecret'      => '',
            'onedriveBusinessSyncTime'    => '30',
            'onedriveBusinessSyncMethod'  => 'sync_page_curl',
            'onedriveBusinessConnectedBy' => get_current_user_id(),
            'state'                       => array()
        );

        return get_option('_wpfdAddon_onedrive_business_config', $default);
    }
    /**
     * Save OneDrive business config
     *
     * @param array $data Onedrive config data
     *
     * @return boolean
     */
    public static function saveOneDriveBusinessConfigs($data)
    {
        $config = self::getAllOneDriveBusinessConfigs();
        $config['onedriveBusinessKey'] = isset($data['onedriveBusinessKey']) ? $data['onedriveBusinessKey'] : '';
        $config['onedriveBusinessSecret'] = isset($data['onedriveBusinessSecret']) ? $data['onedriveBusinessSecret'] : '';
        $config['onedriveBusinessSyncTime'] = isset($data['onedriveBusinessSyncTime']) ? $data['onedriveBusinessSyncTime'] : '30';
        $config['onedriveBusinessSyncMethod'] = isset($data['onedriveBusinessSyncMethod']) ? $data['onedriveBusinessSyncMethod'] : 'sync_page_curl';
        if (isset($data['onedriveBaseFolder'])) {
            $config['onedriveBaseFolder'] = $data['onedriveBaseFolder'];
        }
        return update_option('_wpfdAddon_onedrive_business_config', $config);
    }

    /**
     * Get onedrive by term id
     *
     * @param integer $termId Term id
     *
     * @return boolean
     */
    public static function getOneDriveBusinessIdByTermId($termId)
    {
        $result = get_term_meta($termId, 'wpfd_drive_id', true);
        $type = get_term_meta($termId, 'wpfd_drive_type', true);

        if ($result && $type === 'onedrive_business') {
            return $result;
        }

        return false;
    }
    /**
     * Get onedrive business file info
     *
     * @return mixed
     */
    public static function getOneDriveBusinessFileInfos()
    {
        return get_option('_wpfdAddon_onedrive_business_fileInfo');
    }
    /**
     * Set onedrive business file info
     *
     * @param array $params Onedrive file info
     *
     * @return boolean
     */
    public static function setOneDriveBusinessFileInfos($params)
    {
        return update_option('_wpfdAddon_onedrive_business_fileInfo', $params);
    }
    /**
     * Set onedrive param
     *
     * @param string $key Option key to save
     * @param mixed  $val Option value to save
     *
     * @return void
     */
    public static function setOneDriveBusinessParam($key, $val)
    {
        $params       = self::getAllOneDriveBusinessConfigs();
        $params[$key] = $val;
        self::saveOneDriveBusinessConfigs($params);
    }

    /**
     * Get term id onedrive business by onedrive
     *
     * @param string $oneDriveId Onedrive item id
     *
     * @return boolean|integer
     */
    public static function getTermIdOneDriveBusinessByOneDriveId($oneDriveId)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_id', $oneDriveId)));
        if ($row) {
            $driveType =  get_term_meta($row->term_id, 'wpfd_drive_type', true);
            if (!is_wp_error($driveType) && $driveType === 'onedrive_business') {
                return isset($row->term_id)? (int) $row->term_id : false;
            }
        }

        return false;
    }
    /*----------- OneDrive -----------------*/

    /**
     * Get all onedrive config
     *
     * @return mixed
     */
    public static function getAllOneDriveConfigs()
    {
        $default = array(
            'onedriveKey'         => '',
            'onedriveSecret'      => '',
            'onedriveSyncTime'    => '30',
            'onedriveSyncMethod'  => 'sync_page_curl',
            'onedriveConnectedBy' => get_current_user_id(),
            'onedriveState'       => array(),
        );
        return get_option('_wpfdAddon_onedrive_config', $default);
    }

    /**
     * Get alll onedrive config old
     *
     * @return mixed
     */
    public static function getAllOneDriveConfigsOld()
    {
        return get_option('_wpfdAddon_onedrive_config_old');
    }


    /**
     * Save onedrive config
     *
     * @param array $data Onedrive config data
     *
     * @return boolean
     */
    public static function saveOneDriveConfigs($data)
    {
        $config                               = self::getAllOneDriveConfigs();

        $config['onedriveKey']        = $data['onedriveKey'];
        $config['onedriveSecret']     = $data['onedriveSecret'];
        $config['onedriveSyncTime']   = $data['onedriveSyncTime'];
        $config['onedriveSyncMethod'] = $data['onedriveSyncMethod'];
        $config['last_log'] = $data['last_log'];
        $config['onedriveConnectedBy'] = $data['onedriveConnectedBy'];

        if (isset($data['onedriveBaseFolder'])) {
            $config['onedriveBaseFolder']  = $data['onedriveBaseFolder'];
        }
        if (isset($data['onedriveConnected'])) {
            $config['onedriveConnected']  = $data['onedriveConnected'];
        }
        if (isset($data['onedriveState'])) {
            $config['onedriveState']  = $data['onedriveState'];
        }

        $result = update_option('_wpfdAddon_onedrive_config', $config);

        return $result;
    }

    /**
     * Save onedrive config old
     *
     * @param array $data Onedrive config data old
     *
     * @return boolean
     */
    public static function saveOneDriveConfigsOld($data)
    {
        $result = update_option('_wpfdAddon_onedrive_config_old', $data);

        return $result;
    }

    /**
     * Get all onedrive params
     *
     * @return mixed
     */
    public static function getAllOneDriveParams()
    {
        return get_option('_wpfdAddon_onedrive_category_params');
    }

    /**
     * Get onedrive param
     *
     * @return mixed
     */
    public static function getOneDriveParams()
    {
        $params = self::getAllOneDriveParams();
        return isset($params['onedrive']) ? $params['onedrive'] : false;
    }

    /**
     * Get data config by onedrive
     *
     * @param string $name Get data by key name
     *
     * @return array|null
     */
    public static function getDataConfigByOneDrive($name)
    {
        $OneDriveParams = array();
        if (self::getAllOneDriveConfigs()) {
            foreach (self::getAllOneDriveConfigs() as $key => $val) {
                if (strpos($key, 'onedrive') !== false) {
                    $OneDriveParams[$key] = $val;
                }
            }
            $result = null;
            switch ($name) {
                case 'onedrive':
                    $result = $OneDriveParams;
                    break;
            }

            return $result;
        }

        return null;
    }

    /**
     * Get onedrive by term id
     *
     * @param integer $termId Term id
     *
     * @return boolean
     */
    public static function getOneDriveIdByTermId($termId)
    {
        $result = get_term_meta($termId, 'wpfd_drive_id', true);
        $type = get_term_meta($termId, 'wpfd_drive_type', true);

        if ($result && $type === 'onedrive') {
            $result = self::replaceIdOneDrive($result, false);

            return $result;
        }

        return false;
    }

    /**
     * Get term id onedrive by onedrive
     *
     * @param string $oneDriveId Onedrive item id
     *
     * @return boolean
     */
    public static function getTermIdOneDriveByOneDriveId($oneDriveId)
    {
//        $returnData = false;
        global $wpdb;
        $oneDriveId = self::replaceIdOneDrive($oneDriveId);

        $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_id', $oneDriveId)));
        if ($row) {
            $driveType =  get_term_meta($row->term_id, 'wpfd_drive_type', true);
            if (!is_wp_error($driveType) && $driveType === 'onedrive') {
                return isset($row->term_id)? (int) $row->term_id : false;
            }
        }

        return false;
//        $onedriveParams = self::getOneDriveParams();
//        if ($onedriveParams) {
//            foreach ($onedriveParams as $key => $val) {
//                if ($val['oneDriveId'] === $oneDriveId) {
//                    $returnData = $val['termId'];
//                }
//            }
//        }
//
//        return $returnData;
    }

    /**
     * Get DropBox term id by DropBox id
     *
     * @param string $dropboxId DropBox id
     *
     * @return boolean
     */
    public static function getTermIdDropBoxByDropBoxId($dropboxId)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_id', $dropboxId)));
        if ($row) {
            $driveType = get_term_meta($row->term_id, 'wpfd_drive_type', true);
            if (!is_wp_error($driveType) && $driveType === 'dropbox') {
                return isset($row->term_id)? (int) $row->term_id : false;
            }
        }

        return false;
    }

    /**
     * Get all onedrive id
     *
     * @return array
     */
    public static function getAllOneDriveId()
    {
        $returnData     = array();
        $onedriveParams = self::getOneDriveParams();
        if ($onedriveParams) {
            foreach ($onedriveParams as $key => $val) {
                $returnData[] = $val['oneDriveId'];
            }
        }

        return $returnData;
    }

    /**
     * Set onedrive param
     *
     * @param string $key Option key to save
     * @param mixed  $val Option value to save
     *
     * @return void
     */
    public static function setOneDriveParam($key, $val)
    {
        $params       = self::getAllOneDriveConfigs();
        $params[$key] = $val;
        self::saveOneDriveConfigs($params);
    }

    /**
     * Set onedrive file info
     *
     * @param array $params Onedrive file info
     *
     * @return boolean
     */
    public static function setOneDriveFileInfos($params)
    {
        $result = update_option('_wpfdAddon_onedrive_fileInfo', $params);

        return $result;
    }

    /**
     * Get onedrive file info
     *
     * @return mixed
     */
    public static function getOneDriveFileInfos()
    {
        return get_option('_wpfdAddon_onedrive_fileInfo');
    }

    /**
     * Get Onedrive term id by file id
     *
     * @param string $fileId File Id
     *
     * @return boolean|string
     */
    public static function getOneDriveTermIdByCloudId($fileId)
    {
        $fileInfos = self::getOneDriveFileInfos();
        if (!empty($fileInfos)) {
            foreach ($fileInfos as $termId => $fileInfo) {
                foreach ($fileInfo as $idFile => $fileParam) {
                    if ((string) $fileId === (string) $idFile) {
                        return $termId;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Replace replace id special characters
     *
     * @param string  $id         Item id
     * @param boolean $rplSpecial Replace from special to -
     *
     * @return string
     */
    public static function replaceIdOneDrive($id, $rplSpecial = true)
    {
        if ($rplSpecial) {
            return str_replace('!', '-', $id);
        } else {
            return str_replace('-', '!', $id);
        }
    }

    /**
     * Curl sync interval onedrive
     *
     * @return float|integer
     */
    public static function curSyncIntervalOneDrive()
    {
        //get last_log param
        $config = self::getAllOneDriveConfigs();
        if (isset($config['last_log']) && !empty($config['last_log'])) {
            $last_log = $config['last_log'];
            $last_sync = (int)strtotime($last_log);
        } else {
            $last_sync = 0;
        }

        $time_new = (int)strtotime(date('Y-m-d H:i:s'));
        $timeInterval = $time_new - $last_sync;
        $curtime = $timeInterval / 60;

        return $curtime;
    }

    /**
     * Curl sync interval onedrive business
     *
     * @return float|integer
     */
    public static function curSyncIntervalOneDriveBusiness()
    {
        //get last_log param
        $config = self::getAllOneDriveBusinessConfigs();
        if (isset($config['last_log']) && !empty($config['last_log'])) {
            $last_log = $config['last_log'];
            $last_sync = (int)strtotime($last_log);
        } else {
            $last_sync = 0;
        }

        $time_new = (int)strtotime(date('Y-m-d H:i:s'));
        $timeInterval = $time_new - $last_sync;
        $curtime = $timeInterval / 60;

        return $curtime;
    }

    /**
     * Save category param
     *
     * @param integer $id         Term id
     * @param string  $type_cloud Cloud type
     *
     * @return boolean
     */
    public static function updateDefaultCategoryParams($id, $type_cloud)
    {
        Application::getInstance('Wpfd');
        $wpfd_config   = Model::getInstance('config');
        $defult_params = $wpfd_config->getThemeParams('default');

        Application::getInstance('WpfdAddon');
        $google_config   = self::getAllCloudConfigs();
        $dropbox_config  = self::getAllDropboxConfigs();
        $onedrive_config = self::getAllOneDriveConfigs();
        $onedrive_business_config = self::getAllOneDriveBusinessConfigs();

        switch ($type_cloud) {
            case 'google':
                $category_own = $google_config['googleConnectedBy'];
                break;
            case 'dropbox':
                $category_own = $dropbox_config['dropboxConnectedBy'];
                break;
            case 'onedrive':
                $category_own = $onedrive_config['onedriveConnectedBy'];
                break;
            case 'onedrive_business':
                $category_own = $onedrive_business_config['onedriveBusinessConnectedBy'];
                break;
            default:
                $category_own = get_current_user_id();
        }

        $params = array(
            'theme'            => 'default',
            'canview'          => 0,
            'social'           => 0,
            'category_own'     => $category_own,
            'category_own_old' => $category_own
        );
        $params = array_merge($params, $defult_params);

        $datas = json_encode($params);
        $updated = wp_update_term($id, 'wpfd-category', array('description' => $datas));
        if (is_wp_error($updated)) {
            return false;
        }

        return true;
    }

    /**
     * Delete category in user meta
     *
     * @param integer $cat_id            Term id
     * @param array   $list_category_own Own categories
     *
     * @return void
     */
    public static function delCatInUserMeta($cat_id, $list_category_own)
    {
        $user_id = get_current_user_id();
        if (!in_array($user_id, $list_category_own)) {
            $list_category_own[] = $user_id;
        }

        foreach ($list_category_own as $key => $own) {
            $user_categories = (array)get_user_meta($own, 'wpfd_user_categories', true);
            if (is_array($user_categories)) {
                foreach ($user_categories as $uc_key => $uc_cat_id) {
                    if ((int)$cat_id === (int)$uc_cat_id) {
                        unset($user_categories[$uc_key]);
                    }
                }
                update_user_meta($own, 'wpfd_user_categories', $user_categories);
            }
        }
    }

    /**
     * Get support file measure
     *
     * @return array
     */
    public static function getSupportFileMeasure()
    {
        return array(
            esc_html__('B', 'wpfdAddon'),
            esc_html__('KB', 'wpfdAddon'),
            esc_html__('MB', 'wpfdAddon'),
            esc_html__('GB', 'wpfdAddon'),
            esc_html__('TB', 'wpfdAddon'),
            esc_html__('PB', 'wpfdAddon')
        );
    }

    /**
     * Get correct file size for handle
     *
     * @param string|integer $size File size for handle
     *
     * @return mixed
     */
    public static function getFileSizeHandle($size = 0)
    {
        if (!$size) {
            return 0;
        }

        $factor = floor((strlen($size) -1) / 3);
        $echoSize = sprintf('%.' . 2 . 'f', $size / pow(1024, $factor));
        $size = floatval($echoSize);
        $sz = self::getSupportFileMeasure();
        $sizeUnit = strtolower($sz[$factor]);
        switch ($sizeUnit) {
            case 'kb':
                $size = $size * 1024;
                break;
            case 'mb':
                $size = $size * 1024 * 1024;
                break;
            case 'gb':
                $size = $size * 1024 * 1024 * 1024;
                break;
            default:
                break;
        }

        return $size;
    }

    /*----------- Amazon S3 -----------------*/

    /**
     * Get all AWS config
     *
     * @return mixed
     */
    public static function getAllAwsConfigs()
    {
        $blogName = get_bloginfo('name');
        $blogName = trim(preg_replace('/[^\w\s]/', '', $blogName));
        $awsBucketName = 'wp-file-download-'.$blogName;
        $awsBucketName = strtolower(str_replace(' ', '-', $awsBucketName));

        $default = array(
            'awsKey'         => '',
            'awsSecret'      => '',
            'awsRegion'      => 'us-east-1',
            'awsBucketName'  => $awsBucketName,
            'awsSyncTime'    => '30',
            'awsSyncMethod'  => 'sync_page_curl',
            'awsConnectedBy' => get_current_user_id(),
            'awsState'       => array(),
        );
        return get_option('_wpfdAddon_aws_config', $default);
    }

    /**
     * Set AWS param
     *
     * @param string $key Param key
     * @param mixed  $val Param Value
     *
     * @return void
     */
    public static function setAwsParam($key, $val)
    {
        $params = self::getAllAwsConfigs();
        $params[$key] = $val;
        self::saveAwsConfigs($params);
    }

    /**
     * Save AWS config
     *
     * @param array $data AWS config data
     *
     * @return boolean
     */
    public static function saveAwsConfigs($data)
    {
        $config = self::getAllAwsConfigs();
        $blogName = get_bloginfo('name');
        $blogName = trim(preg_replace('/[^\w\s]/', '', $blogName));
        $awsBucketName = 'wp-file-download-'.$blogName;
        $awsBucketName = strtolower(str_replace(' ', '-', $awsBucketName));

        $config['awsKey']         = $data['awsKey'];
        $config['awsSecret']      = $data['awsSecret'];
        $config['awsRegion']      = $data['awsRegion'];
        if (isset($data['awsBucketName']) && $data['awsBucketName'] !== '') {
            $config['awsBucketName']  = $data['awsBucketName'];
        } else {
            $config['awsBucketName']  = $awsBucketName;
        }
        $config['awsSyncTime']    = $data['awsSyncTime'];
        $config['awsSyncMethod']  = $data['awsSyncMethod'];
        $config['last_log']       = $data['last_log'];
        $config['awsConnectedBy'] = $data['awsConnectedBy'];

        if (isset($data['awsConnected'])) {
            $config['awsConnected'] = $data['awsConnected'];
        }
        if (isset($data['awsState'])) {
            $config['awsState'] = $data['awsState'];
        }

        $result = update_option('_wpfdAddon_aws_config', $config);

        return $result;
    }

    /**
     * Get AWS path by id
     *
     * @param string $id Term id
     *
     * @return string|boolean
     */
    public static function getPathByAwsId($id)
    {
        $wpfd_drive_path = get_term_meta($id, 'wpfd_drive_path', true);
        if (!empty($wpfd_drive_path)) {
            return $wpfd_drive_path;
        }
        return false;
    }

    /**
     * Get AWS id by term id
     *
     * @param integer $termId Term id
     *
     * @return boolean
     */
    public static function getAwsIdByTermId($termId)
    {
        $result = get_term_meta($termId, 'wpfd_drive_path', true);
        $type = get_term_meta($termId, 'wpfd_drive_type', true);

        if ($result && $type === 'aws') {
            return $result;
        }

        return false;
    }

    /**
     * Get AWS path by termID
     *
     * @param integer $termId Term id
     *
     * @return boolean|string
     */
    public static function getAwsPathByTermId($termId)
    {
        $result = get_term_meta($termId, 'wpfd_drive_path', true);
        $type = get_term_meta($termId, 'wpfd_drive_type', true);

        if ($result && $type === 'aws') {
            return $result;
        }

        return false;
    }


    /**
     * Get term id by AWS path
     *
     * @param string $path AWS path
     *
     * @return boolean|integer|string
     */
    public static function getTermIdByAwsPath($path)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_path', $path)));

        if ($row) {
            $driveType =  get_term_meta($row->term_id, 'wpfd_drive_type', true);
            if (!is_wp_error($driveType) && $driveType === 'aws') {
                return isset($row->term_id)? (int) $row->term_id : false;
            }
        }

        return false;
    }

    /**
     * Set AWS file info
     *
     * @param array $params AWS file info
     *
     * @return boolean
     */
    public static function setAwsFileInfos($params)
    {
        $result = update_option('_wpfdAddon_aws_fileInfo', $params, false);

        return $result;
    }

    /**
     * Get AWS file info
     *
     * @return mixed
     */
    public static function getAwsFileInfos()
    {
        return get_option('_wpfdAddon_aws_fileInfo');
    }

    /**
     * Get AWS file info
     *
     * @param string $idFile     AWS file ID
     * @param string $idCategory AWS category ID
     *
     * @return mixed
     */
    public static function getAwsFileInfo($idFile, $idCategory)
    {
        $allFileInfos =  get_option('_wpfdAddon_aws_fileInfo');
        if (isset($allFileInfos[$idCategory]) && isset($allFileInfos[$idCategory][$idFile])) {
            return $allFileInfos[$idCategory][$idFile];
        } else {
            return array();
        }
    }

    /**
     * Curl sync Interval AWS
     *
     * @return float|integer
     */
    public static function curSyncIntervalAws()
    {
        //get last_log param
        $config = self::getAllAwsConfigs();
        if (isset($config['last_log']) && !empty($config['last_log'])) {
            $last_log = $config['last_log'];
            $last_sync = (int)strtotime($last_log);
        } else {
            $last_sync = 0;
        }
        $time_new = (int)strtotime(date('Y-m-d H:i:s'));
        $timeInterval = $time_new - $last_sync;
        $curtime = $timeInterval / 60;
        return $curtime;
    }

    /*----------- Nextcloud -----------------*/

    /**
     * Get all Nextcloud config
     *
     * @return mixed
     */
    public static function getAllNextcloudConfigs()
    {
        $default = array(
            'nextcloudUsername'     => '',
            'nextcloudPassword'     => '',
            'nextcloudURL'          => '',
            'nextcloudRootFolder'   => '',
            'nextcloudSyncTime'     => '30',
            'nextcloudSyncMethod'   => 'sync_page_curl',
            'nextcloudConnectedBy'  => get_current_user_id(),
            'nextcloudState'        => 0
        );
        return get_option('_wpfdAddon_nextcloud_config', $default);
    }

    /**
     * Set Nextcloud param
     *
     * @param string $key Param key
     * @param mixed  $val Param Value
     *
     * @return void
     */
    public static function setNextcloudParam($key, $val)
    {
        $params = self::getAllNextcloudConfigs();
        $params[$key] = $val;
        self::saveNextcloudConfigs($params);
    }

    /**
     * Save Nextcloud config
     *
     * @param array $data Nextcloud config data
     *
     * @return boolean
     */
    public static function saveNextcloudConfigs($data)
    {
        $config = self::getAllNextcloudConfigs();
        $config['nextcloudUsername']    = $data['nextcloudUsername'];
        $config['nextcloudPassword']    = $data['nextcloudPassword'];
        $config['nextcloudURL']         = $data['nextcloudURL'];
        $new_root_foldername = $data['nextcloudRootFolder'];
        if (!empty($new_root_foldername) && $new_root_foldername !== $config['nextcloudRootFolder']) {
            $config['nextcloudRootFolder']  = $data['nextcloudRootFolder'];
            delete_option('_wpfdAddon_nextcloud_create_root');
        }
        $config['nextcloudSyncTime']    = $data['nextcloudSyncTime'];
        $config['nextcloudSyncMethod']  = $data['nextcloudSyncMethod'];
        $config['last_log']             = $data['last_log'];
        $config['nextcloudConnectedBy'] = $data['nextcloudConnectedBy'];
        if (isset($data['nextcloudState'])) {
            $config['nextcloudState'] = $data['nextcloudState'];
        }

        $result = update_option('_wpfdAddon_nextcloud_config', $config);

        return $result;
    }

    /**
     * Get Nextcloud path by termID
     *
     * @param integer $termId Term id
     *
     * @return boolean|string
     */
    public static function getNextcloudPathByTermId($termId)
    {
        $result = get_term_meta($termId, 'wpfd_drive_path', true);
        $type = get_term_meta($termId, 'wpfd_drive_type', true);

        if ($result && $type === 'nextcloud') {
            return $result;
        }

        return false;
    }

    /**
     * Get folder path
     *
     * @param integer $folder_id Folder ID
     *
     * @return mixed|string
     */
    public static function getFolderPathOnNextcloud($folder_id)
    {
        if ((int) $folder_id === 0) {
            $config = self::getAllNextcloudConfigs();
            $path = $config['nextcloudRootFolder'];
        } else {
            $path = get_term_meta($folder_id, 'wpfd_drive_path', true);
        }

        $path = self::getValidPath($path);
        return $path;
    }

    /**
     * Get term id by Nextcloud path
     *
     * @param string $path Nextcloud path
     *
     * @return boolean|integer|string
     */
    public static function getTermIdByNextcloudPath($path)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT term_id, meta_value FROM ' . $wpdb->termmeta . ' WHERE meta_key = %s AND meta_value = %s', array('wpfd_drive_path', $path)));

        if ($row) {
            $driveType =  get_term_meta($row->term_id, 'wpfd_drive_type', true);
            if (!is_wp_error($driveType) && $driveType === 'nextcloud') {
                return isset($row->term_id)? (int) $row->term_id : false;
            }
        }

        return false;
    }

    /**
     * Set Nextcloud file info
     *
     * @param array $params Nextcloud file info
     *
     * @return boolean
     */
    public static function setNextcloudFileInfos($params)
    {
        $result = update_option('_wpfdAddon_nextcloud_fileInfo', $params, false);
        return $result;
    }

    /**
     * Get Nextcloud file info
     *
     * @return mixed
     */
    public static function getNextcloudFileInfos()
    {
        return get_option('_wpfdAddon_nextcloud_fileInfo');
    }

    /**
     * Get valid path
     *
     * @param string $path Path
     *
     * @return string
     */
    public static function getValidPath($path)
    {
        $path = rawurldecode($path);
        $sub_paths = explode('/', $path);
        $valid_subpaths = array();
        foreach ($sub_paths as $sub_path) {
            $valid_subpaths[] = rawurlencode($sub_path);
        }

        $valid_path = implode('/', $valid_subpaths);
        return trim($valid_path);
    }

    /**
     * Curl sync Interval Nextcloud
     *
     * @return float|integer
     */
    public static function curSyncIntervalNextcloud()
    {
        //get last_log param
        $config = self::getAllNextcloudConfigs();
        if (isset($config['last_log']) && !empty($config['last_log'])) {
            $last_log = $config['last_log'];
            $last_sync = (int)strtotime($last_log);
        } else {
            $last_sync = 0;
        }
        $time_new = (int)strtotime(date('Y-m-d H:i:s'));
        $timeInterval = $time_new - $last_sync;
        $curtime = $timeInterval / 60;
        return $curtime;
    }
}
