<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Controller;
use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Utilities;
use Joomunited\WPFramework\v1_0_6\Form;

defined('ABSPATH') || die();
/**
 * Class WpfdAddonControllerOneDriveBusiness
 */
class WpfdAddonControllerOneDriveBusiness extends Controller
{
    /**
     * WpfdAddonControllerOneDriveBusiness constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $path_wpfdhelper;

        $path_wpfdaddononedrive = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddononedrive .= DIRECTORY_SEPARATOR . 'WpfdAddonOneDriveBusiness.php';
        require_once $path_wpfdaddononedrive;
    }

    /**
     * Authenticated
     *
     * @return void
     */
    public function authenticated()
    {
        $view = $this->loadView();
        $view->render('redirect');
    }

    /**
     * Logout
     *
     * @return void
     */
    public function logout()
    {
        Application::getInstance('WpfdAddon');
        $onedriveCategory = Model::getInstance('onedrivebusinesscategory');
        /**
         * Filter to delete cloud category
         *
         * @param boolean
         */
        $deleteCloudCategory = apply_filters('wpfd_delete_cloud_category_when_disconnecting', true);

        if ($deleteCloudCategory) {
            $onedriveCategory->deleteCategoryWhenDisconnect();
        }

        $onedrive = new WpfdAddonOneDriveBusiness();
        $onedrive->logout();
        $data                      = $this->getParams();
        $data['onedriveConnected'] = 0;
        $this->setParams($data);
        $this->redirect('admin.php?page=wpfd-config');
    }
    /**
     * Get params config
     *
     * @return array
     */
    public function getParams()
    {
        return WpfdAddonHelper::getAllOneDriveBusinessConfigs();
    }
    /**
     * Set params onedrive business
     *
     * @param array $data Config data
     *
     * @return void
     */
    public function setParams($data)
    {
        WpfdAddonHelper::saveOneDriveBusinessConfigs($data);
    }
    /**
     * Save OneDrive business params
     *
     * @return void
     */
    public function saveOneDriveBusinessParams()
    {
        if (!class_exists('WpfdAddonHelper')) {
            require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/helpers/WpfdHelper.php';
        }

        $form = new Form();
        $formfile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
        $formfile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'onedrive_business_config.xml';
        if (!$form->load($formfile)) {
            $this->redirect('admin.php?page=wpfd-config&error=1');
        }
        if (!$form->validate()) {
            $this->redirect('admin.php?page=wpfd-config&error=2');
        }
        $data = $form->sanitize();
        $data = array_map('trim', $data);
        if (!WpfdAddonHelper::saveOneDriveBusinessConfigs($data)) {
            $this->redirect('admin.php?page=wpfd-config&error=3');
        }
        $this->redirect('admin.php?page=wpfd-config');
    }


    /**
     * Sync Onedrive Business using new queue
     *
     * @return void
     */
    public function onedrivebusinesssync()
    {
        $wpfdTasks = new \Joomunited\BackgroundTasks\WpfdTasks;

        $wpfdTasks->addOnedriveBusinessRootToQueue(true);
        $wpfdTasks->runQueue();
        $this->exitStatus(true);
    }

    /**
     * OneDrive business auto sync
     *
     * @return void|mixed
     */
    public function oneDriveBusinessSyncAuto()
    {
        $oneDriveBusinessConfig = $this->getParams();
        $sync_time   = isset($oneDriveBusinessConfig['onedriveBusinessSyncTime']) ? (int)$oneDriveBusinessConfig['onedriveBusinessSyncTime'] : 0;
        $sync_method = isset($oneDriveBusinessConfig['onedriveBusinessSyncMethod']) ? (string) $oneDriveBusinessConfig['onedriveBusinessSyncMethod'] : 'sync_page_curl_ajax';

        if ($sync_time > 0 && $sync_method !== 'setup_on_server') {
            $curSyncInterval = WpfdAddonHelper::curSyncIntervalOneDriveBusiness();
            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl') {
                wp_remote_get(
                    admin_url('admin-ajax.php') . '?action=onedrivebusinesssync',
                    array(
                        'timeout'   => 1,
                        'blocking'  => false,
                        'sslverify' => false,
                    )
                );
            }

            if ($curSyncInterval >= $sync_time && $sync_method === 'sync_page_curl_ajax') {
                $this->onedrivebusinesssync();
            }
        }
    }

    /**
     * Watch changes from Google Drive
     *
     * @throws Exception Throws when application can not start
     * @return void
     */
    public function listener()
    {
        // Weebhook validation requests
        // https://docs.microsoft.com/en-us/onedrive/developer/rest-api/concepts/webhook-receiver-validation-request?view=odsp-graph-online
        $validationToken = Utilities::getInput('validationToken', 'GET', 'string');
        $status = 406;
        if ($validationToken !== '') {
            // Return validation text on response
            header('X-PHP-Response-Code: 200');
            header('Status: 200');
            header('Content-Type: text/plain');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This ok
            echo $validationToken;
            die;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['value']) && !empty($data['value'])) {
            $lastSyncChanges = (int) get_option('_wpfd_onedrive_business_last_sync_changes', 0);
            $timeout = 5 * 60; // 5 minutes
            $isTimeout = (time() - $lastSyncChanges) > $timeout;
            $onSyncChange = (int) get_option('_wpfd_onedrive_business_on_sync', 0);

            // Check other changes progress is running or timeout
            if ($onSyncChange === 0 || ($onSyncChange === 1 && ($lastSyncChanges === 0 || $isTimeout))) {
                // We have a notify about changes
                $onedrive = new WpfdAddonOneDriveBusiness();
                $onedrive->syncChanges($data['value']);
                $status = 202;
            } else {
                // Acknowledge the notification by immediately.
                // See https://docs.microsoft.com/en-us/onedrive/developer/rest-api/concepts/scan-guidance?view=odsp-graph-online#process-changes
                $status = 503;
            }
        }

        // Sending failing
        header('X-PHP-Response-Code: ' . $status);
        header('Status: ' . $status);
        die;
    }

    /**
     * Watch changes
     *
     * @return boolean
     */
    public static function watchChanges()
    {
        if (!current_user_can('manage_options') || !class_exists('WpfdAddonOneDriveBusiness')) {
            return false;
        }
        // Cancel any current watch
        self::cancelWatchChanges();

        $onedrive = new WpfdAddonOneDriveBusiness();
        try {
            if ($onedrive->watchChanges()) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * Cancel watch changes
     *
     * @return boolean
     * @since  5.2
     */
    public static function cancelWatchChanges()
    {
        if (!current_user_can('manage_options') || !class_exists('WpfdAddonOneDriveBusiness')) {
            return false;
        }

        $watchDatas = get_option('_wpfd_onedrive_business_watch_data', '');

        if ($watchDatas === '') {
            return false;
        }

        if (!is_array($watchDatas)) {
            return false;
        }

        $onedrive = new WpfdAddonOneDriveBusiness();
        /* @var \Microsoft\Graph\Model\Subscription[] $watchDatas */
        foreach ($watchDatas as $subscription) {
            try {
                $onedrive->stopWatch($subscription);
            } catch (Exception $e) {
                $onedrive->writeLog(__METHOD__ . ': ' . $e->getMessage());
                continue;
            }
        }

        update_option('_wpfd_onedrive_business_watch_data', '');

        return true;
    }

    /**
     * Onedrive Business Stop watch changes
     *
     * @return void
     */
    public function onedriveBusinessStopWatchChanges()
    {
        check_ajax_referer('wpfd-onedrive-business-push', 'security');

        $onedrive_business_watch_changes = (int) get_option('_wpfd_onedrive_business_watch_changes', 1);

        if (!$onedrive_business_watch_changes) {
            // Watch changes
            if (self::watchChanges()) {
                update_option('_wpfd_onedrive_business_watch_changes', 1);
            } else {
                update_option('_wpfd_onedrive_business_watch_changes', 0);
            }
        } else {
            // Cancel watch changes
            self::cancelWatchChanges();
            update_option('_wpfd_onedrive_business_watch_changes', 0);
        }

        wp_send_json_success(array('status' => true));
    }
    /**
     * Delete all OneDrive Business folder
     *
     * @return void
     *
     * @throws Exception Fire message if errors
     */
    public function deleteAllFolder()
    {
        $onedrive = new WpfdAddonOneDriveBusiness();
        $onedrive->deleteAllFolder();

        wp_send_json_success('ALL_FOLDER_DELETED');
    }
}
