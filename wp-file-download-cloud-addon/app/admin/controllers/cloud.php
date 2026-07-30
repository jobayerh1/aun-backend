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

defined('ABSPATH') || die();

/**
 * Class WpfdAddonControllerCloud
 */
class WpfdAddonControllerCloud extends Controller
{
    /**
     * WpfdAddonControllerCloud constructor.
     */
    public function __construct()
    {
        $app             = Application::getInstance('WpfdAddon');
        $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $path_wpfdhelper;
    }

    /**
     * Save Cloud Params
     *
     * @return void
     */
    public function saveCloudParams()
    {
        $form     = new Form();
        $formfile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
        $formfile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'cloud_config.xml';
        if (!$form->load($formfile)) {
            $this->redirect('admin.php?page=wpfd-config&error=1');
        }
        if (!$form->validate()) {
            $this->redirect('admin.php?page=wpfd-config&error=2');
        }
        $data = $form->sanitize();
        if (!WpfdAddonHelper::saveCloudConfigs($data)) {
            $this->redirect('admin.php?page=wpfd-config&error=3');
        }
        $this->redirect('admin.php?page=wpfd-config');
    }

    /**
     * Get all cloud prams
     *
     * @return array
     */
    public function getAllCloudParams()
    {
        return WpfdAddonHelper::getAllCloudConfigs();
    }
}
