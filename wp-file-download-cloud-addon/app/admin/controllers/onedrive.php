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
 * Class WpfdAddonControllerCloud
 */
#[\AllowDynamicProperties]
class WpfdAddonControllerOneDrive extends Controller
{
    /**
     * Onedrive category instance
     *
     * @var boolean
     */
    protected $oneDriveCategory;

    /**
     * WpfdAddonControllerOneDrive constructor.
     */
    public function __construct()
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $path_wpfdhelper;

        $path_wpfdaddononedrive = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddononedrive .= DIRECTORY_SEPARATOR . 'WpfdAddonOneDrive.php';
        require_once $path_wpfdaddononedrive;

        Application::getInstance('WpfdAddon');
        $this->onedriveCategory = Model::getInstance('onedrivecategory');
    }

    /**
     * Get params config
     *
     * @return array
     */
    public function getParams()
    {
        return WpfdAddonHelper::getAllOneDriveConfigs();
    }

    /**
     * Get params old
     *
     * @return array
     */
    public function getParamsOld()
    {
        return WpfdAddonHelper::getAllOneDriveConfigsOld();
    }

    /**
     * Set params onedrive
     *
     * @param array $data Config data
     *
     * @return void
     */
    public function setParams($data)
    {
        WpfdAddonHelper::saveOneDriveConfigs($data);
    }

    /**
     * Set params old onedrive
     *
     * @param array $data Config data
     *
     * @return void
     */
    public function setParamsOld($data)
    {
        WpfdAddonHelper::saveOneDriveConfigsOld($data);
    }

    /**
     * Get category by onedrive id
     *
     * @param string $oneDriveId Onedrive category id
     *
     * @return boolean|array
     */
    protected function getCategoryByOneDriveId($oneDriveId)
    {
        $termId = WpfdAddonHelper::getTermIdOneDriveByOneDriveId($oneDriveId);
        Application::getInstance('Wpfd');
        $category = Model::getInstance('category');

        return $category->getCategory($termId);
    }

    /**
     * Delete category onedrive
     *
     * @param string $oneDriveId Onedrive category id
     *
     * @return boolean|integer|WP_Error true on success, false if term does not exist. Zero on attempted
     *                             deletion of default Category. WP_Error if the taxonomy does not exist.
     */
    protected function deleteCategoryByOneDriveId($oneDriveId)
    {
        Application::getInstance('WpfdAddon');
        $onedriveCategory = Model::getInstance('onedrivecategory');
        $termId = WpfdAddonHelper::getTermIdOneDriveByOneDriveId($oneDriveId);

        return $onedriveCategory->deleteOneDriveCategory($termId);
    }

    /**
     * Get array onedrive id
     *
     * @return array
     */
    protected function getArrayOneDriveId()
    {
        return WpfdAddonHelper::getAllOneDriveId();
    }

    /**
     * Create category by onedrive
     *
     * @param string  $title      Title
     * @param string  $oneDriveId Onedrive category
     * @param integer $parent     Parent term id
     *
     * @return void
     */
    protected function createCategoryByOneDrive($title, $oneDriveId, $parent)
    {
        Application::getInstance('WpfdAddon');
        $cloudCategory = Model::getInstance('onedrivecategory');
        $cloudCategory->addCategoryFromOneDrive($title, $oneDriveId, $parent);
    }


    /**
     * Update term order
     *
     * @param integer $term_id Term id
     * @param integer $order   Order number
     *
     * @return void
     */
    public function updateTermOrder($term_id, $order)
    {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . $wpdb->terms . ' SET term_group = %d WHERE term_id = %d',
                (int) $order,
                (int) $term_id
            )
        );
    }

    /**
     * Save oneDrive params
     *
     * @return void
     */
    public function saveOneDriveParams()
    {

        $form = new Form();
        $formfile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
        $formfile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'onedrive_config.xml';
        if (!$form->load($formfile)) {
            $this->redirect('admin.php?page=wpfd-config&error=1');
        }
        if (!$form->validate()) {
            $this->redirect('admin.php?page=wpfd-config&error=2');
        }
        $data = $form->sanitize();
        $data = array_map('trim', $data);
        if (!WpfdAddonHelper::saveOneDriveConfigs($data)) {
            $this->redirect('admin.php?page=wpfd-config&error=3');
        }
        $this->redirect('admin.php?page=wpfd-config');
    }


    /**
     * Get folder onedrive in WPFD
     *
     * @return array
     */
    public function foldersOneDriveInWPFD()
    {
        $catOneDrive = array();
        $onedriveParams = WpfdAddonHelper::getAllOneDriveParams();
        if ($onedriveParams['onedrive']) {
            Application::getInstance('Wpfd');
            $category = Model::getInstance('category');
            foreach ($onedriveParams['onedrive'] as $key) {
                $categoryOneDrive = $category->getCategory($key['termId']);
                $oneDriveId = $key['oneDriveId'];
                $catOneDrive[$oneDriveId] = array('title' => $categoryOneDrive->name,
                    'parent_id' => $categoryOneDrive->parent);
            }
        } else {
            return $catOneDrive;
        }
        return $catOneDrive;
    }

    /**
     * Get folder in onedrive
     *
     * @return array|boolean
     */
    public function foldersInOneDrive()
    {
        $data = $this->getParams();
        $onedrive = new WpfdAddonOneDrive();
        $lstFolder = $onedrive->getListFolder($data['onedriveBaseFolder']['id']);
        return $lstFolder;
    }

    /**
     * Sync Onedrive using new queue
     *
     * @return void
     */
    public function onedrivesync()
    {
        $wpfdTasks = new \Joomunited\BackgroundTasks\WpfdTasks;

        $wpfdTasks->addOnedriveRootToQueue(true);
        $wpfdTasks->runQueue();
        $this->exitStatus(true);
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
     * Log out
     *
     * @return void
     */
    public function logout()
    {
        Application::getInstance('WpfdAddon');
        $onedriveCategory = Model::getInstance('onedrivecategory');
        $deleteCloudCategory = apply_filters('wpfd_delete_cloud_category_when_disconnecting', true);

        if ($deleteCloudCategory) {
            $onedriveCategory->deleteCategoryWhenDisconnect();
        }

        $onedrive = new WpfdAddonOneDrive();
        $onedrive->logout();
        $data                      = $this->getParams();
        $data['onedriveConnected'] = 0;
        $this->setParams($data);
        $this->redirect('admin.php?page=wpfd-config');
    }

    /**
     * Delete all OneDrive folder
     *
     * @return void
     *
     * @throws Exception Fire message if errors
     */
    public function deleteAllFolder()
    {
        $onedrive = new WpfdAddonOneDrive();
        $onedrive->deleteAllFolder();

        wp_send_json_success('ALL_FOLDER_DELETED');
    }
}
