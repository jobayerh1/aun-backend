<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Controller;
use Joomunited\WPFramework\v1_0_6\Form;
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;

defined('ABSPATH') || die();

/**
 * Class WpfdAddonControllerNextcloud
 */
class WpfdAddonControllerNextcloud extends Controller
{
    /**
     * WpfdAddonControllerNextcloud constructor.
     */
    public function __construct()
    {
        $app             = Application::getInstance('WpfdAddon');
        $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $path_wpfdhelper;
    }

    /**
     * Get params config
     *
     * @return array
     */
    public function getParams()
    {
        return WpfdAddonHelper::getAllNextcloudConfigs();
    }

    /**
     * Set params Nextcloud
     *
     * @param array $data Config data
     *
     * @return void
     */
    public function setParams($data)
    {
        WpfdAddonHelper::saveNextcloudConfigs($data);
    }

    /**
     * Save Nextcloud Params
     *
     * @return void
     */
    public function saveNextcloudParams()
    {
        $form     = new Form();
        $formfile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
        $formfile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'nextcloud_config.xml';

        if (!$form->load($formfile)) {
            $this->redirect('admin.php?page=wpfd-config&error=1');
        }

        if (!$form->validate()) {
            $this->redirect('admin.php?page=wpfd-config&error=2');
        }

        $data = $form->sanitize();
        $data = array_map('trim', $data);

        if (!WpfdAddonHelper::saveNextcloudConfigs($data)) {
            $this->redirect('admin.php?page=wpfd-config&error=3');
        }

        $this->redirect('admin.php?page=wpfd-config');
    }

    /**
     * Get all Nextcloud prams
     *
     * @return array
     */
    public function getAllNextcloudParams()
    {
        return WpfdAddonHelper::getAllNextcloudConfigs();
    }

    /**
     * Connecting Nextcloud
     *
     * @return void
     */
    public function connect()
    {
        $data                   = $this->getParams();
        $data['nextcloudState'] = 1;

        $nextcloud = new WpfdAddonNextcloud();
        $connection = $nextcloud->connectNextcloud();
        if ($connection['status']) {
            $this->setParams($data);
        }
        echo json_encode($connection);
        die();
    }

    /**
     * Disconnecting Nextcloud
     *
     * @return void
     */
    public function disconnect()
    {
        Application::getInstance('WpfdAddon');

        $data                   = $this->getParams();
        $data['nextcloudState'] = 0;
        $this->setParams($data);

        if (get_option('_wpfdAddon_nextcloud_create_root') !== false) {
            delete_option('_wpfdAddon_nextcloud_create_root');
        }

        $this->redirect('admin.php?page=wpfd-config');
    }

    /**
     * Nextcloud sync with queue
     *
     * @return void
     */
    public function nextcloudSync()
    {
        $wpfdTasks = new \Joomunited\BackgroundTasks\WpfdTasks;
        $wpfdTasks->addNextcloudRootToQueue(true);
        $wpfdTasks->runQueue();

        $this->exitStatus(true);
    }
}
