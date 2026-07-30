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
 * Class WpfdAddonControllerCloudTeamDrive
 */
class WpfdAddonControllerCloudTeamDrive extends Controller
{
    /**
     * WpfdAddonControllerCloudTeamDrive constructor.
     */
    public function __construct()
    {
        $app             = Application::getInstance('WpfdAddon');
        $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $path_wpfdhelper;
    }

    /**
     * Save Cloud Team Drive Params
     *
     * @return void
     */
    public function saveCloudTeamDriveParams()
    {
        $form     = new Form();
        $formfile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
        $formfile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'cloud_team_drive_config.xml';
        if (!$form->load($formfile)) {
            $this->redirect('admin.php?page=wpfd-config&error=1');
        }
        if (!$form->validate()) {
            $this->redirect('admin.php?page=wpfd-config&error=2');
        }
        $data               = $form->sanitize();
        $params             = WpfdAddonHelper::getDataConfigByGoogleTeamDriveSever('googleTeamDrive');
        $googleBaseFolderId = isset($params['googleTeamDriveBaseFolder']) ? $params['googleTeamDriveBaseFolder'] : '';
        $newDrive           = false;

        if (is_array($data) && isset($data['selectedGoogleTeamDriveBaseFolder'])
            && $data['selectedGoogleTeamDriveBaseFolder'] !== '' && $data['selectedGoogleTeamDriveBaseFolder'] !== $googleBaseFolderId) {
            $data['googleTeamDriveBaseFolder'] = $data['selectedGoogleTeamDriveBaseFolder'];
            unset($data['selectedGoogleTeamDriveBaseFolder']);
            $newDrive = true;
        }

        if (!WpfdAddonHelper::saveCloudTeamDriveConfigs($data)) {
            $this->redirect('admin.php?page=wpfd-config&error=3');
        }

        if ($newDrive) {
            wp_remote_get(
                admin_url('admin-ajax.php') . '?action=googleTeamDriveSync',
                array(
                    'timeout'   => 1,
                    'blocking'  => false,
                    'sslverify' => false,
                )
            );
        }

        $this->redirect('admin.php?page=wpfd-config');
    }

    /**
     * Get all cloud team drive prams
     *
     * @return array
     */
    public function getAllCloudTeamDriveParams()
    {
        return WpfdAddonHelper::getAllCloudTeamDriveConfigs();
    }
}
