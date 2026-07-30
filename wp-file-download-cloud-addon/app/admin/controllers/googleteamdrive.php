<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

// No direct access
defined('ABSPATH') || die();

use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Controller;
use Joomunited\WPFramework\v1_0_6\Model;

/**
 * Class WpfdAddonControllerGoogleTeamdrive
 */
class WpfdAddonControllerGoogleTeamdrive extends Controller
{
    /**
     * Google Drive Category Instance
     *
     * @var boolean
     */
    protected $cloudTeamDriveCategory;

    /**
     * WpfdAddonControllerGoogleTeamdrive constructor.
     */
    public function __construct()
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdaddongoogle = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddongoogle .= DIRECTORY_SEPARATOR . 'WpfdAddonGoogleTeamDrive.php';
        require_once $path_wpfdaddongoogle;

        $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $path_wpfdhelper;
        Application::getInstance('WpfdAddon');
        $this->cloudTeamDriveCategory = Model::getInstance('cloudteamdrivecategory');
    }

    /**
     * Get param google drive
     *
     * @return array
     */
    public function getParams()
    {
        return WpfdAddonHelper::getAllCloudTeamDriveConfigs();
    }

    /**
     * Get param old google drive
     *
     * @return array
     */
    public function getParamsOld()
    {
        return WpfdAddonHelper::getAllCloudTeamDriveConfigsOld();
    }

    /**
     * Set param config google drive
     *
     * @param array $data Config data
     *
     * @return void
     */
    public function setParams($data)
    {
        WpfdAddonHelper::saveCloudTeamDriveConfigs($data);
    }

    /**
     * Set param old config google drive
     *
     * @param array $data Config data
     *
     * @return void
     */
    public function setParamsOld($data)
    {
        WpfdAddonHelper::saveCloudTeamDriveConfigsOld($data);
    }

    /**
     * Get Authorize Url
     *
     * @return void
     */
    public function getAuthorizeUrl()
    {
        $google = new WpfdAddonGoogleTeamDrive();
        $url    = $google->getAuthorisationUrl();
        $this->redirect($url);
    }

    /**
     * Get authenticate
     *
     * @return void
     */
    public function authenticate()
    {
        $google = new WpfdAddonGoogleTeamDrive();
        $credentials = $google->authenticate();
        $google->storeCredentials($credentials);
        // Check if WPFD folder exists and create if not
        if (!$google->folderExists(WpfdAddonHelper::getDataConfigByGoogleTeamDriveSever('googleTeamDrive'))) {
            $dataOld = $this->getParamsOld();
            $check_root_folder = (!is_null($dataOld) && isset($dataOld['googleTeamDriveBaseFolder'])) ? $google->folderExists($dataOld['googleTeamDriveBaseFolder']) : '';
            $googleTeamDriveClientIdOld = isset($dataOld['googleTeamDriveClientId']) ? $dataOld['googleTeamDriveClientId'] : 0;
            $data = $this->getParams();
            $googleTeamDriveClientId = isset($data['googleTeamDriveClientId']) ? $data['googleTeamDriveClientId'] : -1;
            if ($dataOld && $check_root_folder && $googleTeamDriveClientId === $googleTeamDriveClientIdOld) {
                $data['googleTeamDriveBaseFolder'] = $dataOld['googleTeamDriveBaseFolder'];
                $this->redirect('admin.php?page=wpfdAddon-cloud&task=googleteamdrive.authenticated');
            } else {
                $drives    = $google->getAllRootDrives();
                $canCreate = $google->canCreateDrive();

                if (is_array($drives) && !empty($drives)) {
                    $content = '<form class="wpfdparams" method="POST" style="padding: 20px 10px; width: 100%; display: block; box-sizing: border-box;" action="admin.php?page=wpfdAddon-cloud&amp;task=googleteamdrive.selectDriveForConnecting">';
                    $content .= '<h3 class="ju-setting-label" style="text-align: left; margin: 20px 0 30px 0; font-size: 25px;">' . esc_html__('Select your root drive for connecting', 'wpfdAddon') . '</h3>';
                    $content .= '<label class="ju-setting-label" style="margin-right: 10px; vertical-align: middle;">' . esc_html__('Select root drive', 'wpfdAddon') . '</label>';
                    $content .= '<select class="ju-input ju-large-text wpfd_googleTeamDriveBaseFolderId" name="rootGoogleTeamDriveBaseFolderId" 
                    onchange="this.form.submit();">';

                    if ($canCreate) {
                        $content .= '<option value ="create_new_drive_manual">' . esc_html__('Create a new drive', 'wpfdAddon') . '</option>';
                    }

                    $content .= '<option value ="" selected="selected">— ' . esc_html__('Select an existing drive', 'wpfdAddon') . ' —</option>';

                    foreach ($drives as $drive) {
                        $content .= '<option value = ' . esc_attr($drive['id']) . ' > ' . esc_html($drive['name']) . '</option>';
                    }

                    $content .= '</select>';
                    $content .= '</form>';
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escape above
                    echo $content;
                } else {
                    $folderId = $google->createFolder('WP File Download - ' . get_bloginfo('name'));

                    if (!is_null($folderId) && $folderId && !empty($folderId)) {
                        $data['googleTeamDriveBaseFolder'] = $folderId;
                        $this->setParamsOld($data);
                    } else {
                        $data['googleTeamDriveCredentials'] = '';
                        $this->setParams($data);
                    }
                    $this->redirect('admin.php?page=wpfdAddon-cloud&task=googleteamdrive.authenticated');
                }
            }
            $this->setParams($data);
        }
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
     * Check auth
     *
     * @return boolean
     */
    public function checkauth()
    {
        $google = new WpfdAddonGoogleTeamDrive();
        return $google->checkAuth();
    }

    /**
     * Log out google drive
     *
     * @return void
     */
    public function logout()
    {
        /**
         * Filter to delete cloud category
         *
         * @param boolean
         */
        $deleteCloudCategory = apply_filters('wpfd_delete_cloud_category_when_disconnecting', true);

        if ($deleteCloudCategory) {
            $this->cloudTeamDriveCategory->deleteCategoryWhenDisconnect();
        }

        $google = new WpfdAddonGoogleTeamDrive();
        $google->logout();
        $data = $this->getParams();
        $data['googleTeamDriveCredentials'] = '';
        $this->setParams($data);

        $this->redirect('admin.php?page=wpfd-config');
    }

    /**
     * Folders google team drive in wordpress file download
     *
     * @return array
     */
    public function foldersCloudTeamDriveInWPFD()
    {
        Application::getInstance('Wpfd');
        $catCloud = array();
        $cloudId  = WpfdAddonHelper::getAllCloudTeamDriveParams();
        if ($cloudId['google_team_drive']) {
            $category = Model::getInstance('category');
            foreach ($cloudId['google_team_drive'] as $key) {
                $categoryGoogleTeamDrive = $category->getCategory($key['termId']);
                if (!$categoryGoogleTeamDrive) {
                    continue;
                }
                $idCloud            = $key['idCloud'];
                $catCloud[$idCloud] = array('title' => $categoryGoogleTeamDrive->name, 'parent_id' => $categoryGoogleTeamDrive->parent);
            }
        } else {
            return $catCloud;
        }

        return $catCloud;
    }

    /**
     * Folders cloud in google team drive
     *
     * @throws Exception Fire if errors
     *
     * @return array
     */
    public function foldersCloudInGoogleTeamDrive()
    {
        $data = $this->getParams();
        $googleTeamDrive = new WpfdAddonGoogleTeamDrive();
        return $googleTeamDrive->getListFolder($data['googleTeamDriveBaseFolder']);
    }

    /**
     * Get category by cloud id
     *
     * @param string $cloudId Google category id
     *
     * @return mixed
     */
    protected function getCategoryByCloudTeamDriveId($cloudId)
    {
        $termId = WpfdAddonHelper::getTermIdByGoogleTeamDriveId($cloudId);
        Application::getInstance('Wpfd');
        $category = Model::getInstance('category');

        return $category->getCategory($termId);
    }

    /**
     * Delete category google team drive
     *
     * @param string $cloudId Google category id
     *
     * @return mixed
     */
    protected function deleteCategoryBycloudTeamDriveId($cloudId)
    {
        Application::getInstance('WpfdAddon');
        $cloudCategory = Model::getInstance('cloudteamdrivecategory');
        $termId = WpfdAddonHelper::getTermIdByGoogleTeamDriveId($cloudId);

        return $cloudCategory->deleteCategory($termId);
    }

    /**
     * Get list google team drive id
     *
     * @return array
     */
    protected function getArrayGoogleTeamDriveId()
    {
        return WpfdAddonHelper::getAllGoogleTeamDriveId();
    }

    /**
     * Create category by google team drive
     *
     * @param string  $title   Title
     * @param string  $cloudId Cloud category id
     * @param integer $parent  Parent term id
     *
     * @return void
     */
    protected function createCategoryByGoogleTeamDrive($title, $cloudId, $parent)
    {
        Application::getInstance('WpfdAddon');
        $cloudTeamDriveCategory = Model::getInstance('cloudteamdrivecategory');
        $cloudTeamDriveCategory->addCategoryFromGoogleTeamDrive($title, $cloudId, $parent);
    }

    /**
     * Google team drive sync
     *
     * @throws Exception Fire if errors
     *
     * @return void
     */
    public function googleTeamDriveSyncOld()
    {
        $params = $this->getParams();
        if (isset($params['googleTeamDriveCredentials']) && $params['googleTeamDriveCredentials'] !== null && $params['googleTeamDriveCredentials'] !== '') {
            Application::getInstance('Wpfd');
            $category = Model::getInstance('category');
            // Folders in wordpress file download
            $folderCloudTeamDriveInWPFD = $this->foldersCloudTeamDriveInWPFD();
            // Folders in Google Team Drive
            $folderCloudInGoogleTeamDrive = $this->foldersCloudInGoogleTeamDrive();
            // Folders created in Google Team Drive without have in wordpress file download
            $folders_diff = array();
            // Folders will be deleted
            $folders_diff_del = array();
            if ($folderCloudInGoogleTeamDrive !== false) {
                // To ensure there isn't error when connecting with GD
                if (count($folderCloudTeamDriveInWPFD) > 0) {
                    $folders_diff = array_diff_key($folderCloudInGoogleTeamDrive, $folderCloudTeamDriveInWPFD);
                    $folders_diff_del = array_diff_key($folderCloudTeamDriveInWPFD, $folderCloudInGoogleTeamDrive);
                    // Change folders title
                    foreach ($folderCloudTeamDriveInWPFD as $k => $v) {
                        if (isset($folderCloudTeamDriveInWPFD[$k]['title']) &&
                            isset($folderCloudInGoogleTeamDrive[$k]['title']) &&
                            $folderCloudTeamDriveInWPFD[$k]['title'] !== $folderCloudInGoogleTeamDrive[$k]['title']
                        ) {
                            $objectCurrent = $this->getCategoryByCloudTeamDriveId($k);
                            if (is_wp_error($objectCurrent)) {
                                continue;
                            }
                            try {
                                $category->saveTitle($objectCurrent->term_id, $folderCloudInGoogleTeamDrive[$k]['title']);
                            } catch (Exception $e) {
                                $this->exitStatus('updateTitleById-Exception: ' . $e->getMessage());
                            }
                        }
                    }
                } else {
                    $folders_diff = $folderCloudInGoogleTeamDrive;
                }
            }
            // Delete folders in wordpress file download
            if (count($folders_diff_del) > 0) {
                foreach ($folders_diff_del as $CloudIdDel => $folderDataDel) {
                    $this->deleteCategoryBycloudTeamDriveId($CloudIdDel);
                }
            }
            // If exists diff key array
            if (count($folders_diff) > 0) {
                $lstCloudIdOnWPFD = $this->getArrayGoogleTeamDriveId();
                foreach ($folders_diff as $CloudId => $folderData) {
                    try {
                        // If has parent_id.
                        if ($folderData['parent_id'] === 0) {
                            // Create Folder New
                            $this->createCategoryByGoogleTeamDrive($folderData['title'], $CloudId, 0);
                            $lstCloudIdOnWPFD[] = $CloudId;
                        } else {
                            $check = in_array($folderData['parent_id'], $lstCloudIdOnWPFD);
                            if (!$check) {
                                // Create Parent New
                                $ParentCloudInfo = $folderCloudInGoogleTeamDrive[$folderData['parent_id']];
                                $this->createCategoryByGoogleTeamDrive(
                                    $ParentCloudInfo['title'],
                                    $folderData['parent_id'],
                                    0
                                );
                                $lstCloudIdOnWPFD[] = $folderData['parent_id'];
                                // Create Children New with parent_id in wordpress file download
                                if ($this->getCategoryByCloudTeamDriveId($folderData['parent_id'])) {
                                    $catRecentCreate = $this->getCategoryByCloudTeamDriveId($folderData['parent_id']);
                                    $this->createCategoryByGoogleTeamDrive(
                                        $folderData['title'],
                                        $CloudId,
                                        $catRecentCreate->term_id
                                    );
                                }
                            } else {
                                // Create Children New with parent_id in wordpress file download
                                $catOldInfo = $this->getCategoryByCloudTeamDriveId($folderData['parent_id']);
                                $this->createCategoryByGoogleTeamDrive(
                                    $folderData['title'],
                                    $CloudId,
                                    $catOldInfo->term_id
                                );
                                $lstCloudIdOnWPFD[] = $CloudId;
                            }
                        }
                    } catch (Exception $e) {
                        $erros = $e->getMessage();
                        WpfdAddonHelper::writeLog($erros);
                        break;
                    }
                }
            }
            WpfdAddonHelper::setCloudTeamDriveParam('google_team_drive_last_log', date('Y-m-d H:i:s'));

            $this->exitStatus(true);
        }
    }

    /**
     * Sync Google Team Drive using queue
     *
     * @return void
     */
    public function googleteamdrivesync()
    {
        $wpfdTasks = new \Joomunited\BackgroundTasks\WpfdTasks;
        $wpfdTasks->addGoogleTeamDriveRootToQueue(true);
        $wpfdTasks->runQueue();

        $this->exitStatus(true);
    }

    /**
     * Watch changes from google team drive
     *
     * @throws Exception Throws when application can not start
     * @return void
     */
    public function listener()
    {
        $data = $this->extractHeader($_SERVER);

        $status = 406;
        if (isset($data['HTTP_X_GOOG_RESOURCE_STATE']) && isset($data['HTTP_X_GOOG_CHANNEL_ID'])) {
            switch ($data['HTTP_X_GOOG_RESOURCE_STATE']) {
                case 'sync':
                    // Do something cool in sync step or do nothing
                    // Got this state when new watch change was made
                    // Check other resource id and remove them
                    $flag = 'synced';
                    break;
                case 'change':
                    // Oh we have a changes
                    $watchData = get_option('_wpfd_google_watch_data', '');
                    if ($watchData === '') {
                        break;
                    }
                    $watchData = json_decode($watchData, true);
                    if (is_array($watchData) && isset($watchData['error'])) {
                        break;
                    }
                    if (!is_array($watchData) ||
                        !isset($watchData['id']) ||
                        $data['HTTP_X_GOOG_CHANNEL_ID'] !== $watchData['id']
                    ) {
                        break;
                    }
                    $lastSyncChanges = (int) get_option('_wpfd_google_last_sync_changes', 0);
                    $timeout = 5 * 60; // 5 minutes
                    $isTimeout = (time() - $lastSyncChanges) > $timeout;
                    $onSyncChange = (int) get_option('_wpfd_google_on_sync', 0);

                    // Check other changes progress is running or timeout
                    if ($onSyncChange === 0 || ($onSyncChange === 1 && ($lastSyncChanges === 0 || $isTimeout))) {
                        $this->onChangesReceive();
                        $status = 202;
                    } else {
                        // Send header Drive API will retry with exponential backoff
                        // Document here: https://developers.google.com/drive/api/v3/push#responding-to-notifications
                        $status = 503;
                    }
                    break;
                default:
                    break;
            }
        }
        header('X-PHP-Response-Code: ' . $status);
        header('Status: ' . $status);
        exit;
    }

    /**
     * On change receive
     *
     * @return boolean
     */
    private function onChangesReceive()
    {
        // Check with previous sync time and is there any sync step is running?
        if (self::isGoogleWatchExpiry()) {
            // Renew watch changes
            self::watchChanges();
        }
        // Then get changes list and do the sync progress
        $lastPageToken = get_option('_wpfd_google_last_changes_token', '');

        if ($lastPageToken === '') {
            return false;
        }

        $changes = array();
        $newPageToken = '';
        $this->getChanges($lastPageToken, $changes, $newPageToken);
        if (empty($changes)) {
            return false;
        }
        update_option('_wpfd_google_on_sync', 1);
        $this->doSyncByChanges($changes, $newPageToken);
        // Update last sync time and save newPageToken
        update_option('_wpfd_google_last_sync_changes', time());
        update_option('_wpfd_google_on_sync', 0);
        // When we check watch expiry time to renew?
    }

    /**
     * Do sync by changes
     *
     * @param array  $changes      Changes
     * @param string $newPageToken New page token for next sync
     *
     * @return boolean
     * @throws Exception Throws when application can not start
     * @since  5.2
     */
    private function doSyncByChanges($changes, $newPageToken)
    {
        if (empty($changes)) {
            return false;
        }

        Application::getInstance('WpfdAddon');
        $googleFileModel = $this->getModel('cloudfiles');
        if (!$googleFileModel instanceof WpfdAddonModelCloudFiles) {
            return false;
        }

        Application::getInstance('Wpfd');
        $categoriesModel = $this->getModel('categories');
        if (!$categoriesModel instanceof WpfdModelCategories) {
            return false;
        }

        Application::getInstance('Wpfd');
        $categoryModel = $this->getModel('category');
        if (!$categoryModel instanceof WpfdModelCategory) {
            return false;
        }
        Application::getInstance('WpfdAddon');
        $cloudCategory = $this->getModel('cloudteamdrivecategory');
        if (!$cloudCategory instanceof WpfdAddonModelCloudcategory) {
            return false;
        }

        $google_config = WpfdAddonHelper::getAllCloudTeamDriveConfigs();
        $baseFolderId = $google_config['googleTeamDriveBaseFolder'];

        foreach ($changes as $change) {
            // Progress sync by each change
            if ($change->getChangeType() === 'file') {
                $file = $change->getFile();
                if (!$file instanceof Google_Service_Drive_DriveFile) {
                    continue;
                }

                // Check file parents. If this is shared documents from other user it does not provided
                $parents = $file->getParents();
                if ($parents === null || (is_array($parents) && empty($parents))) {
                    continue;
                }

                $parent = $parents[0];
                $action = $this->getChangeAction($file, $parent);
                // Clear cache for parent
                WpfdCloudCache::deleteTransient($parent, 'googleDrive');
                if (!$action) {
                    continue;
                }

                switch ($action) {
                    case 'file_created':
//                        try {
//                            //$googleFileModel->createFile($file, $parent);
//                        } catch (Exception $e) {
//                            break;
//                        }
                        break;
                    case 'file_moved':
//                        try {
//                            $googleFileModel->moveFile($file, $parent);
//                        } catch (Exception $e) {
//                            break;
//                        }
                        break;
                    case 'file_modified':
//                        try {
//                            $googleFileModel->updateFile($file, $parent);
//                        } catch (Exception $e) {
//                            break;
//                        }
                        break;
                    case 'file_removed':
//                        try {
//                            $googleFileModel->deleteFile($file->getId());
//                        } catch (Exception $e) {
//                            break;
//                        }
                        break;
                    case 'folder_created':
                        try {
                            // Single folder created
                            $termId = WpfdAddonHelper::getTermIdGoogleDriveByGoogleId($file->getId());
                            // if the cloud folder exist (synced) then ignore
                            if (!$termId) {
                                $parentCat = $this->getCategoryByCloudTeamDriveId($parent);
                                $parentCatId = isset($parentCat->term_id) ? $parentCat->term_id : 0;
                                $newCatId = $cloudCategory->addCategoryFromGoogleTeamDrive($file->getName(), $file->getId(), $parentCatId);

                                // Sync files in this folder
                                $this->syncGoogleToLocalFolder($file->getId());
                            }
                        } catch (Exception $e) {
                            break;
                        }
                        break;
                    case 'folder_moved':
                        try {
                            if ($baseFolderId === $parent) {
                                $parentCatId = 0;
                            } else {
                                $parentCat  = $this->getCategoryByCloudTeamDriveId($parent);
                                $parentCatId = (int) $parentCat->term_id;
                            }

                            $currentCat = $this->getCategoryByCloudTeamDriveId($file->getId());

                            $pk       = $currentCat->term_id; // Catid
                            $ref      = $parentCatId; // Parent
                            $position = 'first-child';

                            $categoryModel->changeOrder($pk, $ref, $position);
                        } catch (Exception $e) {
                            break;
                        }
                        break;
                    case 'folder_modified': // Folder rename or folder move
                        try {
                            $newName = $file->getName();
                            $currentCat = $this->getCategoryByCloudTeamDriveId($file->getId());
                            // Rename local category
                            if ($currentCat && $currentCat->name !== $newName) {
                                $categoryModel->saveTitle($currentCat->term_id, $newName);
                            }
                            // Change order
                            // Check is folder parent changed for root category
                            if ($baseFolderId === $parent) {
                                $parentCatId = 0;
                            } else {
                                $parentCat  = $this->getCategoryByCloudTeamDriveId($parent);
                                if (!isset($parentCat->term_id)) {
                                    break;
                                }
                                $parentCatId = (int) $parentCat->term_id;
                            }

                            $currentCat = $this->getCategoryByCloudTeamDriveId($file->getId());
                            if (!isset($currentCat->parent)) {
                                break;
                            }
                            if ($currentCat->parent !== $parentCatId) {
                                $pk       = $currentCat->term_id; // Catid
                                $ref      = $parentCatId; // Parent
                                $position = 'first-child';

                                $categoryModel->changeOrder($pk, $ref, $position);
                            }
                        } catch (Exception $e) {
                            break;
                        }
                        break;
                    case 'folder_removed':
                        try {
                            // Remove in categories
                            $localFolder = $this->getCategoryByCloudTeamDriveId($file->getId());
                            if (!isset($localFolder->term_id)) {
                                // Folder removed
                                break;
                            }
                            $cid = (int) $localFolder->term_id;

                            $children = $categoryModel->getChildren($cid);


                            $deletedTermIds = array();
                            if ($categoryModel->delete($cid)) {
                                $deletedTermIds[] = $cid;
                                $children[] = $cid;
                                foreach ($children as $child) {
                                    if ($child === $cid) {
                                        continue;
                                    }
                                    $categoryModel->delete($child);
                                    $deletedTermIds[] = $child;
                                }
                            }

                            $deletedTermIds = array_map('intval', $deletedTermIds);
                            $dataCloudCategory  = get_option('_wpfdAddon_cloud_category_params');
                            $dataGoogleCategory = $dataCloudCategory['googledrive'];
                            $deletedCloudIds = array();
                            if ($dataGoogleCategory) {
                                foreach ($dataGoogleCategory as $key => $value) {
                                    if ((int) $value['termId'] === (int) $cid && in_array((int) $cid, $deletedTermIds)) {
                                        // Delete params
                                        $deletedCloudIds[] = $value['idCloud'];
                                        unset($dataGoogleCategory[$key]);
                                    }
                                }
                                $dataCloudCategory['googledrive'] = $dataGoogleCategory;
                                update_option('_wpfdAddon_cloud_category_params', $dataCloudCategory);

                                // Delete cache
                                foreach ($deletedCloudIds as $objectId) {
                                    WpfdCloudCache::deleteTransient($objectId, 'googleDrive');
                                }
                            }
                        } catch (Exception $e) {
                            break;
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        // Update latest page token
        update_option('_wpfd_google_last_changes_token', $newPageToken);

        return false;
    }

    /**
     * Get change action
     *
     * @param Google_Service_Drive_DriveFile $file   Google file object
     * @param string                         $parent Parent id
     *
     * @return string
     * @since  4.1.5
     */
    private function getChangeAction($file, $parent)
    {
        if (!$file instanceof Google_Service_Drive_DriveFile) {
            return false;
        }
        Application::getInstance('WpfdAddon');
        $model         = $this->getModel('cloudteamdrivecategory');
        $localList     = $model->getArrayGoogleTeamDriveId();

        // Add google base folder to folder list
        $google_config = WpfdAddonHelper::getAllCloudTeamDriveConfigs();
        $baseFolderId = $google_config['googleTeamDriveBaseFolder'];
        if ($baseFolderId !== '') {
            $baseFolderArray = array('idCloud' => $baseFolderId);
            $localList[]     = $baseFolderArray;
        }
        $id            = $file->getId();
        $mimeType      = $file->getMimeType();
        $trashed       = $file->getTrashed();
        if ($id === $baseFolderId) {
            return false;
        }
        if (!$this->inFolderList($parent, $localList)) {
            if ($mimeType === 'application/vnd.google-apps.folder' && $this->inFolderList($id, $localList) && $this->isParentFolderChanged($id, $parent, $baseFolderId) && $trashed === false) {
                return 'folder_removed'; // Folder move out of dropfiles categories
            }

            return false;
        }
        if ($mimeType === 'application/vnd.google-apps.folder+shared') {
            return false;
        }

        if ($mimeType === 'application/vnd.google-apps.folder') {
            // Is folder
            if (!$this->inFolderList($id, $localList) && !$this->isParentFolderChanged($id, $parent, $baseFolderId) && $trashed === false) {
                return 'folder_created';
            } elseif ($this->inFolderList($id, $localList) && $this->isParentFolderChanged($id, $parent, $baseFolderId) && $parent !== $baseFolderId && $trashed === false) {
                return 'folder_moved';
            } elseif ($trashed) {
                return 'folder_removed';
            } else {
                return 'folder_modified';
            }
        }
    }

    /**
     * Is Parent folder changed
     *
     * @param string $folderId     Folder id
     * @param string $newParentId  New parent id
     * @param string $baseFolderId Base folder id
     *
     * @return boolean
     * @since  4.1.5
     */
    private function isParentFolderChanged($folderId, $newParentId, $baseFolderId)
    {
        $localCat         = $this->getCategoryByCloudTeamDriveId($folderId);

        $localCatParentId = isset($localCat->parent) ? (int) $localCat->parent : 0;

        if ($newParentId === $baseFolderId) {
            $newCatParentId = 0;
        } else {
            $parentCat = $this->getCategoryByCloudTeamDriveId($newParentId);
            if (!isset($parentCat->term_id)) {
                return false;
            }
            $newCatParentId = (int) $parentCat->term_id;
        }

        if ($localCatParentId) {
            if ($localCatParentId !== $newCatParentId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check is folder in local list
     *
     * @param string $id   Folder id
     * @param array  $list Local list
     *
     * @return boolean
     * @since  4.1.5
     */
    private function inFolderList($id, $list)
    {
        foreach ($list as $cat) {
            if (isset($cat['idCloud']) && $id === $cat['idCloud']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get changes from google team drive
     *
     * @param string $pageToken    Page token
     * @param array  $changes      Changes list
     * @param string $newPageToken New page token to save to database
     *
     * @return void
     * @since  4.1.5
     */
    private function getChanges($pageToken, &$changes = array(), &$newPageToken = '')
    {
        $google      = new WpfdAddonGoogleTeamDrive();
        $nextChanges = $google->listChanges($pageToken);

        if ($nextChanges !== false && $nextChanges instanceof Google_Service_Drive_ChangeList) {
            $changes = array_merge($changes, $nextChanges->getChanges());

            // Get next page token if provided
            if ($nextChanges->getNextPageToken()) {
                $newChanges = array();
                $aNewPageToken = $newPageToken;
                $this->getChanges($nextChanges->getNextPageToken(), $newChanges, $aNewPageToken);
                $changes = array_merge($changes, $newChanges);
                $newPageToken = $aNewPageToken;
            }

            // Set new page token if provided
            if ($nextChanges->getNewStartPageToken()) {
                $newPageToken = $nextChanges->getNewStartPageToken();
            }
        }
    }

    /**
     * Extract Google Header from request
     *
     * @param array $headers Headers array
     *
     * @return array
     * @since  5.2
     */
    private function extractHeader($headers)
    {
        $data = array();
        foreach ($headers as $key => $value) {
            if (strpos(strtoupper($key), 'HTTP_X_GOOG') === 0) {
                $data[strtoupper($key)] = $value;
            }
        }

        return $data;
    }

    /**
     * Sync A google team drive Category with local
     *
     * @param string $cloudId Cloud category id
     *
     * @return boolean
     * @since  5.2.0
     */
    private function syncGoogleToLocalFolder($cloudId)
    {
        // Step 1: get category children
        $google = new WpfdAddonGoogleTeamDrive();
        try {
            $newCategories = $google->getListFolder($cloudId);
            // Step 2: sync with local
            if (count($newCategories) > 0) {
                $lstCloudIdOnWPFD = $this->getArrayGoogleTeamDriveId();
                foreach ($newCategories as $CloudId => $folderData) {
                    // If has parent_id.
                    if ($folderData['parent_id'] === 0) {
                        // Create Folder New
                        $this->createCategoryByGoogleTeamDrive($folderData['title'], $CloudId, 0);
                        $lstCloudIdOnWPFD[] = $CloudId;
                    } else {
                        $check = in_array($folderData['parent_id'], $lstCloudIdOnWPFD);
                        if (!$check) {
                            // Create Parent New
                            $ParentCloudInfo = $lstCloudIdOnWPFD[$folderData['parent_id']];
                            $this->createCategoryByGoogleTeamDrive(
                                $ParentCloudInfo['title'],
                                $folderData['parent_id'],
                                0
                            );
                            $lstCloudIdOnWPFD[] = $folderData['parent_id'];
                            // Create Children New with parent_id in WPFD
                            if ($this->getCategoryByCloudTeamDriveId($folderData['parent_id'])) {
                                $catRecentCreate = $this->getCategoryByCloudTeamDriveId($folderData['parent_id']);
                                $this->createCategoryByGoogleTeamDrive(
                                    $folderData['title'],
                                    $CloudId,
                                    $catRecentCreate->term_id
                                );
                            }
                        } else {
                            // Create Children New with parent_id in WPFD
                            $catOldInfo = $this->getCategoryByCloudTeamDriveId($folderData['parent_id']);
                            $this->createCategoryByGoogleTeamDrive(
                                $folderData['title'],
                                $CloudId,
                                $catOldInfo->term_id
                            );
                            $lstCloudIdOnWPFD[] = $CloudId;
                        }
                    }

                    // Step 3: Sync file with local
                    // $this->syncFileByCloudId($CloudId);
                }
            }
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Generate uuid v4
     * https://www.php.net/manual/en/function.uniqid.php
     *
     * @return string
     * @since  5.2
     */
    public static function uniqidv4()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Is google watch changes expired
     *
     * @return boolean
     */
    public static function isGoogleWatchExpiry()
    {
        $watchData = get_option('_wpfd_google_watch_data', '');

        if ($watchData === '') {
            return true;
        }
        $watchData = json_decode($watchData, true);
        if (!is_array($watchData) || !isset($watchData['expiration'])) {
            return true;
        }

        $expiration = (int) $watchData['expiration']; // Expiration time of watch change in milliseconds

        // todo: Time? UTC compare with what?
        if (time() < ($expiration/1000 - 3600)) { // Return expiry before 3600s
            return false;
        }

        return true;
    }

    /**
     * Cancel watch changes
     *
     * @return boolean
     * @since  5.2
     */
    public static function cancelWatchChanges()
    {
        if (!current_user_can('manage_options') || !class_exists('WpfdAddonGoogleTeamDrive')) {
            return false;
        }

        $watchData = get_option('_wpfd_google_watch_data', '');
        if ($watchData === '') {
            return false;
        }

        $watchData = json_decode($watchData, true);
        if (!is_array($watchData)) {
            return false;
        }

        if (!isset($watchData['id']) || !isset($watchData['resourceId'])) {
            return false;
        }

        $google = new WpfdAddonGoogleTeamDrive();

        $google->stopWatch($watchData['id'], $watchData['resourceId']);
        update_option('_wpfd_google_watch_data', '');
        update_option('_wpfd_google_last_changes_token', '');

        return true;
    }

    /**
     * Watch changes from google team drive
     *
     * @return boolean
     * @since  5.2
     */
    public static function watchChanges()
    {
        if (!current_user_can('manage_options') || !class_exists('WpfdAddonGoogleTeamDrive')) {
            return false;
        }

        // Cancel any current watch
        self::cancelWatchChanges();

        $newGoogle = new WpfdAddonGoogleTeamDrive();
        $startPageToken = $newGoogle->getStartPageToken();

        if (!$startPageToken) {
            return false;
        }

        $uuid = self::uniqidv4();
        $watchResponse = $newGoogle->watchChanges($startPageToken, $uuid);

        if (!is_array($watchResponse)) {
            return false;
        }

        update_option('_wpfd_google_watch_data', json_encode($watchResponse));
        update_option('_wpfd_google_last_changes_token', $startPageToken);

        if (isset($watchResponse['error'])) {
            update_option('_wpfd_google_display_push_error', 1);
            return false;
        }

        return true;
    }
    /**
     * Google Stop watch changes
     *
     * @return void
     */
    public function googleStopWatchChanges()
    {
        check_ajax_referer('wpfd-google-push', 'security');

        $google_watch_changes = (int) get_option('_wpfd_google_watch_changes', 1);

        if (!$google_watch_changes) {
            // Watch changes
            if (self::watchChanges()) {
                update_option('_wpfd_google_watch_changes', 1);
            } else {
                update_option('_wpfd_google_watch_changes', 0);
            }
        } else {
            // Cancel watch changes
            self::cancelWatchChanges();
            update_option('_wpfd_google_watch_changes', 0);
        }
        WpfdCloudCache::flushCache('googleDrive');
        wp_send_json_success(array('status' => true));
    }

    /**
     * Delete all google team drive folder
     *
     * @return void
     *
     * @throws Exception Fire message if errors
     */
    public function deleteAllFolder()
    {
        $google = new WpfdAddonGoogleTeamDrive();
        $google->deleteAllFolder();

        wp_send_json_success('ALL_FOLDER_DELETED');
    }

    /**
     * Select a drive for connecting
     *
     * @return void
     *
     * @throws Exception Fire message if errors
     */
    public function selectDriveForConnecting()
    {
        $googleTeamDrive      = new WpfdAddonGoogleTeamDrive();
        // phpcs:ignore
        $selectedBaseFolderId = isset($_POST['rootGoogleTeamDriveBaseFolderId']) ? $_POST['rootGoogleTeamDriveBaseFolderId'] : '';
        $register             = $googleTeamDrive->registerDrive($selectedBaseFolderId);

        if ($register) {
            $this->redirect('admin.php?page=wpfdAddon-cloud&task=googleteamdrive.authenticated');
        }
    }
}
