<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use \Joomunited\WPFramework\v1_0_6\Controller;
use \Joomunited\WPFramework\v1_0_6\Form;
use \Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;

defined('ABSPATH') || die();

/**
 * Class WpfdAddonControllerAws
 */
class WpfdAddonControllerAws extends Controller
{
    /**
     * WpfdAddonControllerAws constructor.
     */
    public function __construct()
    {
        $app             = Application::getInstance('WpfdAddon');
        $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $path_wpfdhelper;
    }

    /**
     * Connect
     *
     * @return void
     */
    public function connect()
    {
        $data                 = $this->getParams();
        $data['awsState']     = 1;
        $this->setParams($data);

        $aws = new WpfdAddonAwsAdmin();
        $connection = $aws->checkConnectAws();
        echo json_encode($connection);
        die();
    }

    /**
     * Disconnect
     *
     * @return void
     */
    public function disconnect()
    {
        Application::getInstance('WpfdAddon');
        $awsCategory = Model::getInstance('awscategory');
        $deleteCloudCategory = apply_filters('wpfd_delete_cloud_category_when_disconnecting', true);

        if ($deleteCloudCategory) {
            $awsCategory->deleteAwsCategoryWhenDisconnect();
        }

        $data                 = $this->getParams();
        $data['awsConnected'] = 0;
        $data['awsState']     = 0;
        $this->setParams($data);
        $this->redirect('admin.php?page=wpfd-config');
    }

    /**
     * Clear AWS category
     *
     * @return boolean
     */
    public function clearAwsCategory()
    {
        Application::getInstance('WpfdAddon');
        $awsCategory = Model::getInstance('awscategory');
        $awsCategory->deleteAwsCategoryWhenDisconnect();

        return true;
    }

    /**
     * Get params config
     *
     * @return array
     */
    public function getParams()
    {
        return WpfdAddonHelper::getAllAwsConfigs();
    }

    /**
     * Set params AWS
     *
     * @param array $data Config data
     *
     * @return void
     */
    public function setParams($data)
    {
        WpfdAddonHelper::saveAwsConfigs($data);
    }

    /**
     * Save Aws Params
     *
     * @return void
     */
    public function saveAwsParams()
    {
        $form     = new Form();
        $formfile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
        $formfile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'aws_config.xml';

        if (!$form->load($formfile)) {
            $this->redirect('admin.php?page=wpfd-config&error=1');
        }

        if (!$form->validate()) {
            $this->redirect('admin.php?page=wpfd-config&error=2');
        }

        $data = $form->sanitize();
        $data = array_map('trim', $data);

        if (!WpfdAddonHelper::saveAwsConfigs($data)) {
            $this->redirect('admin.php?page=wpfd-config&error=3');
        }

        $this->redirect('admin.php?page=wpfd-config');
    }

    /**
     * Get all Aws prams
     *
     * @return array
     */
    public function getAllAwsParams()
    {
        return WpfdAddonHelper::getAllAwsConfigs();
    }

    /**
     * AWS sync with queue
     *
     * @return void
     */
    public function awsSync()
    {
        $wpfdTasks = new \Joomunited\BackgroundTasks\WpfdTasks;
        $wpfdTasks->addAwsRootToQueue(true);
        $wpfdTasks->runQueue();

        $this->exitStatus(true);
    }
}
