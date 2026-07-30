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
 * Class WpfdAddonControllerGoogledrive
 */
class WpfdAddonControllerGoogledrive extends Controller
{
    /**
     * Google Drive Category Instance
     *
     * @var boolean
     */
    protected $cloudCategory;

    /**
     * WpfdAddonControllerGoogledrive constructor.
     */
    public function __construct()
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdaddongoogle = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddongoogle .= DIRECTORY_SEPARATOR . 'WpfdAddonGoogle.php';
        require_once $path_wpfdaddongoogle;

        $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $path_wpfdhelper;
        Application::getInstance('WpfdAddon');
        $this->cloudCategory = Model::getInstance('cloudcategory');
    }

    /**
     * Get param google drive
     *
     * @return array
     */
    public function getParams()
    {
        return WpfdAddonHelper::getAllCloudConfigs();
    }

    /**
     * Get param old google drive
     *
     * @return array
     */
    public function getParamsOld()
    {
        return WpfdAddonHelper::getAllCloudConfigsOld();
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
        WpfdAddonHelper::saveCloudConfigs($data);
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
        WpfdAddonHelper::saveCloudConfigsOld($data);
    }

    /**
     * Get Authorize Url
     *
     * @return void
     */
    public function getAuthorizeUrl()
    {
        $google = new WpfdAddonGoogleDrive();
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
        $google = new WpfdAddonGoogleDrive();
        $credentials = $google->authenticate();
        $google->storeCredentials($credentials);
        //Check if WPFD folder exists and create if not
        if (!$google->folderExists(WpfdAddonHelper::getDataConfigBySeverName('google'))) {
            $dataOld = $this->getParamsOld();
            $check_root_folder = $google->folderExists($dataOld['googleBaseFolder']);
            $googleClientIdOld = isset($dataOld['googleClientId']) ? $dataOld['googleClientId'] : 0;
            $data = $this->getParams();
            $googleClientId = isset($data['googleClientId']) ? $data['googleClientId'] : -1;
            if ($dataOld && $check_root_folder && $googleClientId === $googleClientIdOld) {
                $data['googleBaseFolder'] = $dataOld['googleBaseFolder'];
            } else {
                $folder = $google->createFolder('WP File Download - ' . get_bloginfo('name'));
                $data['googleBaseFolder'] = $folder->id;
                $this->setParamsOld($data);
            }
            $this->setParams($data);
        }
        $this->redirect('admin.php?page=wpfdAddon-cloud&task=googledrive.authenticated');
    }

    /**
     * Authenticated
     *
     * @return void
     */
    public function authenticated()
    {
        // Watch changes
        if (self::watchChanges()) {
            update_option('_wpfd_google_watch_changes', 1);
        }
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
        $google = new WpfdAddonGoogleDrive();
        return $google->checkAuth();
    }

    /**
     * Log out google drive
     *
     * @return void
     */
    public function logout()
    {
        $deleteCloudCategory = apply_filters('wpfd_delete_cloud_category_when_disconnecting', true);

        if ($deleteCloudCategory) {
            $this->cloudCategory->deleteCategoryWhenDisconnect();
        }

        $google = new WpfdAddonGoogleDrive();
        $google->logout();
        $data                      = $this->getParams();

        // Stop watch changes
        self::cancelWatchChanges();
        update_option('google_watch_changes', 0);
        WpfdCloudCache::flushCache('googleDrive');
        $data['googleCredentials'] = '';
        $this->setParams($data);

        $this->redirect('admin.php?page=wpfd-config');
    }

    /**
     * Folders google drive in WPFD
     *
     * @return array
     */
    public function foldersCloudInWPFD()
    {
        $catCloud = array();
        $cloudId  = WpfdAddonHelper::getAllCloudParams();
        if ($cloudId['googledrive']) {
            Application::getInstance('Wpfd');
            $category = Model::getInstance('category');
            foreach ($cloudId['googledrive'] as $key) {
                $categoryGoogle = $category->getCategory($key['termId']);
                if (!$categoryGoogle) {
                    continue;
                }
                $idCloud            = $key['idCloud'];
                $catCloud[$idCloud] = array('title' => $categoryGoogle->name, 'parent_id' => $categoryGoogle->parent);
            }
        } else {
            return $catCloud;
        }

        return $catCloud;
    }

    /**
     * Folders Cloud In GoogleDrive
     *
     * @return array
     */
    public function foldersCloudInGoogleDrive()
    {
        $data = $this->getParams();
        $google = new WpfdAddonGoogleDrive();
        $lstFolder = $google->getListFolder($data['googleBaseFolder']);

        return $lstFolder;
    }

    /**
     * Get category by cloud id
     *
     * @param string $cloudId Google category id
     *
     * @return mixed
     */
    protected function getCategoryByCloudId($cloudId)
    {
        $termId = WpfdAddonHelper::getTermIdGoogleDriveByGoogleId($cloudId);
        Application::getInstance('Wpfd');
        $category = Model::getInstance('category');

        return $category->getCategory($termId);
    }

    /**
     * Delete category google drive
     *
     * @param string $cloudId Google category id
     *
     * @return mixed
     */
    protected function deleteCategoryBycloudId($cloudId)
    {
        Application::getInstance('WpfdAddon');
        $cloudCategory = Model::getInstance('cloudcategory');
        $termId = WpfdAddonHelper::getTermIdGoogleDriveByGoogleId($cloudId);

        return $cloudCategory->deleteCategory($termId);
    }

    /**
     * Get list google drive id
     *
     * @return array
     */
    protected function getArrayGoogleDriveId()
    {
        return WpfdAddonHelper::getAllGoogleDriveId();
    }

    /**
     * Create category by google drive
     *
     * @param string  $title   Title
     * @param string  $cloudId Cloud category id
     * @param integer $parent  Parent term id
     *
     * @return void
     */
    protected function createCategoryByGoogleDrive($title, $cloudId, $parent)
    {
        Application::getInstance('WpfdAddon');
        $cloudCategory = Model::getInstance('cloudcategory');
        $cloudCategory->addCategoryFromGoogleDrive($title, $cloudId, $parent);
    }

    /**
     * Google drive sync
     *
     * @return void
     */
    public function googlesyncOld()
    {
        $params = $this->getParams();
        // Clean all cache
        $google = new WpfdAddonGoogleDrive();
        WpfdCloudCache::flushCache($google->getCloudName());
        if (isset($params['googleCredentials']) && $params['googleCredentials'] !== null && $params['googleCredentials'] !== '') {
            Application::getInstance('Wpfd');
            $category = Model::getInstance('category');
            // Folders in WPFD
            $folderCloudInWPFD = $this->foldersCloudInWPFD();
            // Folders in Google Drive
            $folderCloudInGoogleDrive = $this->foldersCloudInGoogleDrive();
            // Folders created in Google Drive without have in WPFD
            $folders_diff = array();
            // Folders will delete
            $folders_diff_del = array();
            if ($folderCloudInGoogleDrive !== false) {
                // To ensure there isn't error when connect with GD
                if (count($folderCloudInWPFD) > 0) {
                    $folders_diff = array_diff_key($folderCloudInGoogleDrive, $folderCloudInWPFD);
                    $folders_diff_del = array_diff_key($folderCloudInWPFD, $folderCloudInGoogleDrive);
                    // Change folders title
                    foreach ($folderCloudInWPFD as $k => $v) {
                        if (isset($folderCloudInWPFD[$k]['title']) &&
                            isset($folderCloudInGoogleDrive[$k]['title']) &&
                            $folderCloudInWPFD[$k]['title'] !== $folderCloudInGoogleDrive[$k]['title']
                        ) {
                            $objectCurrent = $this->getCategoryByCloudId($k);
                            if (is_wp_error($objectCurrent)) {
                                continue;
                            }
                            try {
                                $category->saveTitle($objectCurrent->term_id, $folderCloudInGoogleDrive[$k]['title']);
                            } catch (Exception $e) {
                                $this->exitStatus('updateTitleById-Exception: ' . $e->getMessage());
                            }
                        }
                    }
                } else {
                    $folders_diff = $folderCloudInGoogleDrive;
                }
            }
            // Delete folders in dropfile
            if (count($folders_diff_del) > 0) {
                foreach ($folders_diff_del as $CloudIdDel => $folderDataDel) {
                    $this->deleteCategoryBycloudId($CloudIdDel);
                }
            }
            // If exists diff key array
            if (count($folders_diff) > 0) {
                $lstCloudIdOnWPFD = $this->getArrayGoogleDriveId();
                foreach ($folders_diff as $CloudId => $folderData) {
                    try {
                        // If has parent_id.
                        if ($folderData['parent_id'] === 0) {
                            // Create Folder New
                            $this->createCategoryByGoogleDrive($folderData['title'], $CloudId, 0);
                            $lstCloudIdOnWPFD[] = $CloudId;
                        } else {
                            $check = in_array($folderData['parent_id'], $lstCloudIdOnWPFD);
                            if (!$check) {
                                // Create Parent New
                                $ParentCloudInfo = $folderCloudInGoogleDrive[$folderData['parent_id']];
                                $this->createCategoryByGoogleDrive(
                                    $ParentCloudInfo['title'],
                                    $folderData['parent_id'],
                                    0
                                );
                                $lstCloudIdOnWPFD[] = $folderData['parent_id'];
                                // Create Children New with parent_id in WPFD
                                if ($this->getCategoryByCloudId($folderData['parent_id'])) {
                                    $catRecentCreate = $this->getCategoryByCloudId($folderData['parent_id']);
                                    $this->createCategoryByGoogleDrive(
                                        $folderData['title'],
                                        $CloudId,
                                        $catRecentCreate->term_id
                                    );
                                }
                            } else {
                                // Create Children New with parent_id in WPFD
                                $catOldInfo = $this->getCategoryByCloudId($folderData['parent_id']);
                                $this->createCategoryByGoogleDrive(
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
            WpfdAddonHelper::setCloudParam('last_log', date('Y-m-d H:i:s'));

            $this->exitStatus(true);
        }
    }

    /**
     * Sync Google Drive using queue
     *
     * @return void
     */
    public function googlesync()
    {
        $wpfdTasks = new \Joomunited\BackgroundTasks\WpfdTasks;
        WpfdCloudCache::flushCache('googleDrive');
        $wpfdTasks->addGoogleDriveRootToQueue(true);
        $wpfdTasks->runQueue();

        $this->exitStatus(true);
    }

    /**
     * Watch changes from Google Drive
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
        $cloudCategory = $this->getModel('cloudcategory');
        if (!$cloudCategory instanceof WpfdAddonModelCloudcategory) {
            return false;
        }

        $google_config = WpfdAddonHelper::getAllCloudConfigs();
        $baseFolderId = $google_config['googleBaseFolder'];

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
                                $parentCat = $this->getCategoryByCloudId($parent);
                                $parentCatId = isset($parentCat->term_id) ? $parentCat->term_id : 0;
                                $newCatId = $cloudCategory->addCategoryFromGoogleDrive($file->getName(), $file->getId(), $parentCatId);

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
                                $parentCat  = $this->getCategoryByCloudId($parent);
                                $parentCatId = (int) $parentCat->term_id;
                            }

                            $currentCat = $this->getCategoryByCloudId($file->getId());

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
                            $currentCat = $this->getCategoryByCloudId($file->getId());
                            // Rename local category
                            if ($currentCat && $currentCat->name !== $newName) {
                                $categoryModel->saveTitle($currentCat->term_id, $newName);
                            }
                            // Change order
                            // Check is folder parent changed for root category
                            if ($baseFolderId === $parent) {
                                $parentCatId = 0;
                            } else {
                                $parentCat  = $this->getCategoryByCloudId($parent);
                                if (!isset($parentCat->term_id)) {
                                    break;
                                }
                                $parentCatId = (int) $parentCat->term_id;
                            }

                            $currentCat = $this->getCategoryByCloudId($file->getId());
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
                            $localFolder = $this->getCategoryByCloudId($file->getId());
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
        $model         = $this->getModel('cloudcategory');
        $localList     = $model->getArrayGoogleDriveId();

        // Add google base folder to folder list
        $google_config = WpfdAddonHelper::getAllCloudConfigs();
        $baseFolderId = $google_config['googleBaseFolder'];
        if ($baseFolderId !== '') {
            $baseFolderArray = array('idCloud' => $baseFolderId);
            $localList[]     = $baseFolderArray;
        }
//        $localFileList = $model->getAllGoogleFilesList();
//        $localFileList = array();
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
//            } elseif ($mimeType !== 'application/vnd.google-apps.folder' && $this->inFileList($id, $localFileList) && $this->isParentChanged($id, $parent) && $trashed === false) {
//                return 'file_removed'; // File move out of dropfiles categories
//            }

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
//        } else {
//            // Is file
//            if (!$this->inFileList($id, $localFileList) && !$this->isParentChanged($id, $parent) && $trashed === false) {
//                return 'file_created';
//            } elseif ($this->inFileList($id, $localFileList) && $this->isParentChanged($id, $parent) && $trashed === false) {
//                return 'file_moved';
//            } elseif ($trashed) {
//                return 'file_removed';
//            } else {
//                return 'file_modified';
//            }
//        }
    }

    /**
     * Check is parent changed
     *
     * @param string $fileId      Google file id
     * @param string $newParentId New parent id
     *
     * @return boolean
     * @since  4.1.5
     */
//    private function isParentChanged($fileId, $newParentId)
//    {
//        $modelGoogle = $this->getModel('googlefiles');
//        if (!$modelGoogle instanceof DropfilesModelGooglefiles) {
//            return null;
//        }
//        $file = $modelGoogle->getFile($fileId);
//
//        if (!$file) {
//            return false;
//        }
//
//        if (trim($file->catid) === trim($newParentId)) {
//            return false;
//        }
//
//        return true;
//    }

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
        $localCat         = $this->getCategoryByCloudId($folderId);

        $localCatParentId = isset($localCat->parent) ? (int) $localCat->parent : 0;

        if ($newParentId === $baseFolderId) {
            $newCatParentId = 0;
        } else {
            $parentCat = $this->getCategoryByCloudId($newParentId);
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
     * Check is file in local list
     *
     * @param string $id   File id
     * @param array  $list Local files list
     *
     * @return boolean
     * @since  4.1.5
     */
//    private function inFileList($id, $list)
//    {
//        foreach ($list as $cat) {
//            if (isset($cat->file_id) && $id === $cat->file_id) {
//                return true;
//            }
//        }
//
//        return false;
//    }

    /**
     * Get changes from Google Drive
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
        $google      = new WpfdAddonGoogleDrive();
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
     * Sync A Google Drive Category with local
     *
     * @param string $cloudId Cloud category id
     *
     * @return boolean
     * @since  5.2.0
     */
    private function syncGoogleToLocalFolder($cloudId)
    {
        // Step 1: get category children
        $google = new WpfdAddonGoogleDrive();
        try {
            $newCategories = $google->getListFolder($cloudId);
            // Step 2: sync with local
            if (count($newCategories) > 0) {
                $lstCloudIdOnWPFD = $this->getArrayGoogleDriveId();
                foreach ($newCategories as $CloudId => $folderData) {
                    // If has parent_id.
                    if ($folderData['parent_id'] === 0) {
                        // Create Folder New
                        $this->createCategoryByGoogleDrive($folderData['title'], $CloudId, 0);
                        $lstCloudIdOnWPFD[] = $CloudId;
                    } else {
                        $check = in_array($folderData['parent_id'], $lstCloudIdOnWPFD);
                        if (!$check) {
                            // Create Parent New
                            $ParentCloudInfo = $lstCloudIdOnWPFD[$folderData['parent_id']];
                            $this->createCategoryByGoogleDrive(
                                $ParentCloudInfo['title'],
                                $folderData['parent_id'],
                                0
                            );
                            $lstCloudIdOnWPFD[] = $folderData['parent_id'];
                            // Create Children New with parent_id in WPFD
                            if ($this->getCategoryByCloudId($folderData['parent_id'])) {
                                $catRecentCreate = $this->getCategoryByCloudId($folderData['parent_id']);
                                $this->createCategoryByGoogleDrive(
                                    $folderData['title'],
                                    $CloudId,
                                    $catRecentCreate->term_id
                                );
                            }
                        } else {
                            // Create Children New with parent_id in WPFD
                            $catOldInfo = $this->getCategoryByCloudId($folderData['parent_id']);
                            $this->createCategoryByGoogleDrive(
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
        if (!current_user_can('manage_options') || !class_exists('WpfdAddonGoogleDrive')) {
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

        $google = new WpfdAddonGoogleDrive();

        $google->stopWatch($watchData['id'], $watchData['resourceId']);
        update_option('_wpfd_google_watch_data', '');
        update_option('_wpfd_google_last_changes_token', '');

        return true;
    }

    /**
     * Watch changes from google drive
     *
     * @return boolean
     * @since  5.2
     */
    public static function watchChanges()
    {
        if (!current_user_can('manage_options') || !class_exists('WpfdAddonGoogleDrive')) {
            return false;
        }

        // Cancel any current watch
        self::cancelWatchChanges();

        $newGoogle = new WpfdAddonGoogleDrive();
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
     * Delete all Google Drive folder
     *
     * @return void
     *
     * @throws Exception Fire message if errors
     */
    public function deleteAllFolder()
    {
        $google = new WpfdAddonGoogleDrive();
        $google->deleteAllFolder();

        wp_send_json_success('ALL_FOLDER_DELETED');
    }
}
