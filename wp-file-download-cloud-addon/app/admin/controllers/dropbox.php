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
class WpfdAddonControllerDropbox extends Controller
{
    /**
     * Dropbox category instance
     *
     * @var boolean
     */
    protected $dropboxCategory;

    /**
     * Debug mode
     *
     * @var boolean
     */
    protected $debug = false;

    /**
     * Type
     *
     * @var boolean
     */
    protected $type = 'dropbox_controller';

    /**
     * WpfdAddonControllerDropbox constructor.
     */
    public function __construct()
    {
        $app             = Application::getInstance('WpfdAddon');
        $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $path_wpfdhelper;

        $path_wpfdaddondropbox = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddondropbox .= DIRECTORY_SEPARATOR . 'WpfdAddonDropbox.php';
        require_once $path_wpfdaddondropbox;

        Application::getInstance('WpfdAddon');
        $this->dropboxCategory = Model::getInstance('dropboxcategory');
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
     * Save dropbox Params
     *
     * @return void
     */
    public function saveDropboxParams()
    {
        $form     = new Form();
        $formfile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
        $formfile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'dropbox_config.xml';

        if (!$form->load($formfile)) {
            $this->redirect('admin.php?page=wpfd-config&error=1');
        }

        if (!$form->validate()) {
            $this->redirect('admin.php?page=wpfd-config&error=2');
        }

        $data = $form->sanitize();
        $data = array_map('trim', $data);

        if (!WpfdAddonHelper::saveDropboxConfigs($data)) {
            $this->redirect('admin.php?page=wpfd-config&error=3');
        }

        $this->redirect('admin.php?page=wpfd-config');
    }


    /**
     * Log out
     *
     * @return void
     */
    public function logout()
    {
        $deleteCloudCategory = apply_filters('wpfd_delete_cloud_category_when_disconnecting', true);

        if ($deleteCloudCategory) {
            $this->dropboxCategory->deleteDropboxCategoryWhenDisconnect();
        }

        $dropbox = new WpfdAddonDropbox();
        $dropbox->logout();
        WpfdCloudCache::flushCache('dropbox');
        $this->redirect('admin.php?page=wpfd-config');
    }
    /**
     * Dropbox sync with queue
     *
     * @return void
     */
    public function dropboxsync()
    {
        $wpfdTasks = new \Joomunited\BackgroundTasks\WpfdTasks;
        WpfdCloudCache::flushCache('dropbox');
        $wpfdTasks->addDropboxRootToQueue(true);
        $wpfdTasks->runQueue();

        $this->exitStatus(true);
    }
    /**
     * Dropbox sync
     *
     * @return void
     */
    public function dropboxsyncold()
    {
        $dropbox = new WpfdAddonDropbox();
        if (!$dropbox->checkAuth()) {
            $dropboxFolders = $dropbox->listAllFolders();

            if ($dropboxFolders === false) {
                $this->exitStatus(false, 'error during get Dropbox folder list');
            }
            $dataDropboxCategory = get_option('_wpfdAddon_dropbox_category_params');
            $listcates           = array();
            foreach ($dataDropboxCategory as $k => $v) {
                $listcates[$v['id']] = array(
                    'term_id'   => $k,
                    'idDropbox' => $v['idDropbox'],
                    'title'     => $v['idDropboxSlug']
                );
            }

            $diff_del = array_diff_key($listcates, $dropboxFolders);
            $diff_add = array_diff_key($dropboxFolders, $listcates);
            // Check if rename on Dropbox
            if (count($listcates) > 0) {
                $updateName   = false;
                $term_updated = false;
                foreach ($listcates as $folderId => $v) {
                    $termId = $v['term_id'];
                    $term   = get_term($termId, 'wpfd-category');
                    if (is_wp_error($term) || $term === false) {
                        continue;
                    }
                    $newParent    = 0;
                    $fpath        = pathinfo($dropboxFolders[$folderId]['path_lower']);
                    $idDropboxDir = $fpath['dirname'];
                    if (empty($idDropboxDir)) {
                        $idDropboxDir = '/';
                    }
                    if ($idDropboxDir !== '/') {
                        //find parent term
                        $newParent = WpfdAddonHelper::getTermIdByDropboxPath($idDropboxDir);
                    }
                    if ($term && $term->parent !== (int) $newParent) {
                        wp_update_term(
                            $termId,
                            'wpfd-category',
                            array(
                                'parent' => $newParent,
                                'name'   => $dropboxFolders[$folderId]['name']
                            )
                        );
                        $term_updated = true;
                    }
                    if ($term->name !== $dropboxFolders[$folderId]['name']) {
                        wp_update_term(
                            $termId,
                            'wpfd-category',
                            array(
                                'parent' => $newParent,
                                'name'   => $dropboxFolders[$folderId]['name']
                            )
                        );
                        $term_updated = true;
                    }
                    if ($v['title'] !== $dropboxFolders[$folderId]['name']) {
                        $updateName = true;
                        if (!$term_updated) {
                            wp_update_term(
                                $termId,
                                'wpfd-category',
                                array(
                                    'parent' => $newParent,
                                    'name'   => $dropboxFolders[$folderId]['name']
                                )
                            );
                            $term_updated = true;
                        }
                        $dataDropboxCategory[$termId]['idDropbox']     = $dropboxFolders[$folderId]['path_lower'];
                        $dataDropboxCategory[$termId]['idDropboxSlug'] = $dropboxFolders[$folderId]['name'];
                    }
                    if ($v['idDropbox'] !== $dropboxFolders[$folderId]['path_lower']) {
                        $updateName = true;
                        if (!$term_updated) {
                            wp_update_term(
                                $termId,
                                'wpfd-category',
                                array(
                                    'parent' => $newParent,
                                    'name'   => $dropboxFolders[$folderId]['name']
                                )
                            );
                            $term_updated = true;
                        }
                        $dataDropboxCategory[$termId]['idDropbox']     = $dropboxFolders[$folderId]['path_lower'];
                        $dataDropboxCategory[$termId]['idDropboxSlug'] = $dropboxFolders[$folderId]['name'];
                    }
                }
                if ($updateName) {
                    update_option('_wpfdAddon_dropbox_category_params', $dataDropboxCategory, false);
                }
            }

            // Delete categories in WPFD
            if (!empty($diff_del)) {
                $dataDropboxCategory = get_option('_wpfdAddon_dropbox_category_params');
                foreach ($diff_del as $folderId => $v) {
                    $termId = $v['term_id'];
                    //delete term and child term
                    wp_delete_term($termId, 'wpfd-category');
                    //delete option
                    if ($dataDropboxCategory && isset($dataDropboxCategory[$termId])) {
                        unset($dataDropboxCategory[$termId]);
                    }
                }
                update_option('_wpfdAddon_dropbox_category_params', $dataDropboxCategory, false);
            }

            //add new categories
            if (!empty($diff_add)) {
                foreach ($diff_add as $folderId => $folderData) {
                    $fpath        = pathinfo($folderData['path_lower']);
                    $idDropboxDir = $fpath['dirname'];
                    $title        = $folderData['name'];
                    if (empty($idDropboxDir)) {
                        $idDropboxDir = '/';
                    }
                    if ($idDropboxDir !== '/') {
                        //find parent term
                        $parent = (int) WpfdAddonHelper::getTermIdByDropboxPath($idDropboxDir);
                    } else {
                        $parent = 0;
                    }
                    $inserted = wp_insert_term(
                        $title,
                        'wpfd-category',
                        array('parent' => $parent, 'slug' => sanitize_title($title))
                    );
                    if (is_wp_error($inserted)) {
                        // Try again
                        $inserted = wp_insert_term(
                            $title,
                            'wpfd-category',
                            array('parent' => $parent, 'slug' => sanitize_title($title) . '-' . time())
                        );
                        if (is_wp_error($inserted)) {
                            wp_send_json($inserted->get_error_message());
                        }
                    }
                    $lastCats = get_terms(
                        'wpfd-category',
                        'orderby=term_group&order=DESC&hierarchical=0&hide_empty=0&parent=' . $parent . '&number=1'
                    );
                    get_term($inserted['term_id'], 'wpfd-category');
                    if (is_array($lastCats) && count($lastCats)) {
                        $this->updateTermOrder($inserted['term_id'], $lastCats[0]->term_group + 1);
                    }
                    WpfdAddonHelper::updateDefaultCategoryParams($inserted['term_id'], 'dropbox');
                    // Create dropbox category params
                    $dropboxParams = get_option('_wpfdAddon_dropbox_category_params');
                    if (!is_array($dropboxParams)) {
                        $dropboxParams = array();
                    }
                    $dropParams                          = array(
                        'idDropbox'     => $folderData['path_lower'],
                        'termId'        => $inserted['term_id'],
                        'id'            => $folderId,
                        'idDropboxDir'  => $idDropboxDir,
                        'idDropboxSlug' => $fpath['basename']
                    );
                    $dropboxParams[$inserted['term_id']] = $dropParams;
                    update_option('_wpfdAddon_dropbox_category_params', $dropboxParams, false);
                }
            }
            WpfdAddonHelper::setDropboxParam('last_log', date('Y-m-d H:i:s'));
            $this->exitStatus(true);
        }
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
        $onedrive = new WpfdAddonDropbox();
        $onedrive->deleteAllFolder();

        wp_send_json_success('ALL_FOLDER_DELETED');
    }

    /**
     * Authenticate Dropbox
     *
     * @return void
     */
    public function authenticate()
    {
        $dropbox = new WpfdAddonDropbox();
        $result = $dropbox->authorization();
        if ($result) {
            $this->authenticated();
        }
    }
    /**
     * Authenticated
     *
     * @return void
     */
    public function authenticated()
    {
        Application::getInstance('WpfdAddon');
        $view = $this->loadView();
        $view->render('redirect');
    }

    /**
     * Dropbox watch changes
     *
     * @return boolean
     *
     * @throws Exception Fire if error
     */
    public function watchChanges()
    {
        if (!current_user_can('manage_options') || !class_exists('WpfdAddonDropbox')) {
            return false;
        }

        $dropbox = new WpfdAddonDropbox();

        try {
            $dropbox->watchChanges();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Cancel watch changes
     *
     * @return boolean
     */
    public function cancelWatchChanges()
    {
        if (!current_user_can('manage_options') || !class_exists('WpfdAddonDropbox')) {
            return false;
        }

        $watchData = get_option('_wpfd_dropbox_watch_change_information', '');
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

        $dropbox = new wpfdAddonDropbox();

        update_option('_wpfd_dropbox_watch_change_information', '');
        update_option('_wpfd_dropbox_last_changes_token', '');

        return true;
    }

    /**
     * Stop watch changes
     *
     * @throws Exception Fire if error
     *
     * @return void
     */
    public function dropboxStopWatchChanges()
    {
        check_ajax_referer('wpfd-dropbox-push', 'security');
        $dropbox_watch_changes = (int) get_option('_wpfd_dropbox_watch_changes', 1);

        if (!$dropbox_watch_changes) {
            // Watch changes
            if ($this->watchChanges()) {
                update_option('_wpfd_dropbox_watch_changes', 1);
            } else {
                update_option('_wpfd_dropbox_watch_changes', 0);
            }
        } else {
            // Cancel watch changes
            $this->cancelWatchChanges();
            update_option('_wpfd_dropbox_watch_changes', 0);
        }
        wp_send_json_success(array('status' => true));
    }

    /**
     * Retrieve changes from Dropbox webhook notifications
     *
     * @throws Exception Throws when application can not start
     * @return void
     */
    public function listener()
    {
        // Webhook document link: https://www.dropbox.com/developers/reference/webhooks#documentation
        // Verify callback Url
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['challenge'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This run from webhook
            echo esc_attr($_GET['challenge']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This run from webhook
            exit();
        }

        $watchChange = get_option('_wpfd_dropbox_watch_changes', false);

        if (is_null($watchChange) || !$watchChange) {
            $this->writeLog('Watch change is disabled!');
        } else {
            // Get data from Webhook notifications
            $request_body = file_get_contents('php://input');
            $data         = json_decode($request_body, true);
            $params       = get_option('_wpfdAddon_dropbox_config', array());
            $secret       = isset($params['dropboxSecret']) ? $params['dropboxSecret'] : '';
            $sign         = 'X-Dropbox-Signature'; // Verify signature

            if (function_exists('getallheaders')) {
                $headers   = getallheaders();
                $signature = $headers[$sign];
            } else {
                $signature = isset($_SERVER['HTTP_' . $sign]) ? $_SERVER['HTTP_' . $sign] : '';
            }

            if (!hash_equals($signature, hash_hmac('sha256', $request_body, $secret))) {
                http_response_code(403);
                $this->writeLog('Invalid signature');
                exit();
            }

            $dropbox     = new WpfdAddonDropbox();
            $accessToken = $dropbox->checkAndRefreshToken();

            if ($accessToken === false) {
                $this->writeLog('Failed to get access token');
                exit();
            }

            $accessToken     = $accessToken->getToken();
            $lastSyncChanges = (int) get_option('_wpfd_dropbox_last_sync_changes', 0);
            $timeout         = 15; // 15 seconds
            $isTimeout       = (time() - $lastSyncChanges) > $timeout;
            $onSyncChange    = (int) get_option('_wpfd_dropbox_on_sync', 0);
            $processedAccounts = array();

            // Check other changes progress is running or timeout
            if ($onSyncChange === 0 || ($onSyncChange === 1 && ($lastSyncChanges === 0 || $isTimeout))) {
                // Process the notifications
                if (isset($data['list_folder']['accounts'])) {
                    update_option('_wpfd_dropbox_on_sync', 1);
                    foreach ($data['list_folder']['accounts'] as $account_id) {
                        if (in_array($account_id, $processedAccounts)) {
                            continue;
                        }

                        // Process changes for this account
                        $this->processChanges($account_id, $accessToken);
                        $processedAccounts[] = $account_id;
                    }
                    update_option('_wpfd_dropbox_last_sync_changes', time());
                    update_option('_wpfd_dropbox_on_sync', 0);
                } else {
                    $this->writeLog('There is no accounts in the changes');
                    update_option('_wpfd_dropbox_last_sync_changes', 0);
                    update_option('_wpfd_dropbox_on_sync', 0);
                }
            } else {
                $this->writeLog('The watchChange is processing');
                update_option('_wpfd_dropbox_last_sync_changes', 0);
                update_option('_wpfd_dropbox_on_sync', 0);
            }
        }
        die();
    }

    /**
     * Process the change from webhook notifications
     *
     * @param string $account_id  Account id
     * @param string $accessToken Access token
     *
     * @throws Exception Throws when application can not start
     * @return mixed
     */
    public function processChanges($account_id, $accessToken)
    {
        // Get the latest cursor
        $cursor = $this->getLatestCursor($account_id, $accessToken);

        // Fetch and process changes
        $this->fetchAndProcessChanges($accessToken, $cursor);
    }

    /**
     * Retrieve the latest cursor
     *
     * @param string $account_id  Account id
     * @param string $accessToken Access token
     *
     * @throws Exception Throws when application can not start
     * @return mixed
     */
    public function getLatestCursor($account_id, $accessToken)
    {
        $url  = 'https://api.dropboxapi.com/2/files/list_folder/get_latest_cursor';
        $data = array(
            'include_deleted' => false,
            'include_has_explicit_shared_members' => false,
            'include_media_info' => false,
            'include_mounted_folders' => true,
            'include_non_downloadable_files' => true,
            'path' => '',
            'recursive' => true
        );

        $header = array(
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        $prevCursor = get_option('_wpfdAddon_dropbox_latest_cursor_' . $account_id, null);

        if (!is_null($prevCursor)) {
            if (isset($result['cursor'])) {
                update_option('_wpfdAddon_dropbox_latest_cursor_' . $account_id, $result['cursor']);
            } else {
                update_option('_wpfdAddon_dropbox_latest_cursor_' . $account_id, null);
            }
            return $prevCursor;
        } else {
            if (isset($result['cursor'])) {
                update_option('_wpfdAddon_dropbox_latest_cursor_' . $account_id, $result['cursor']);
                return $result['cursor'];
            } else {
                update_option('_wpfdAddon_dropbox_latest_cursor_' . $account_id, null);
                return null;
            }
        }
    }

    /**
     * Fetch and precess changes
     *
     * @param string $accessToken Access token
     * @param string $cursor      Latest cursor
     *
     * @throws Exception Throws when application can not start
     * @return mixed
     */
    public function fetchAndProcessChanges($accessToken, $cursor)
    {
        $url             = 'https://api.dropboxapi.com/2/files/list_folder/continue';
        $params          = get_option('_wpfdAddon_dropbox_config', array());
        $dropboxKey      = isset($params['dropboxKey']) ? $params['dropboxKey'] : '';
        $dropboxSecret   = isset($params['dropboxSecret']) ? $params['dropboxSecret'] : '';
        $basicAuthString = base64_encode($dropboxKey . ':' . $dropboxSecret);
        $data            = array('cursor' => $cursor);
        $dropbox         = new WpfdAddonDropbox();
        $settings        = $dropbox->getAllDropboxConfigs();
        $baseFolderId    = (is_array($settings) && isset($settings['dropboxBaseFolderId'])) ? $settings['dropboxBaseFolderId'] : '';
        $header = array(
//          'Authorization: Basic ' . $basicAuthString,
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $result  = json_decode($response, true);

        if (isset($result['entries']) && is_array($result['entries']) && !empty($result['entries'])) {
            $count   = count($result['entries']);
            $folders = array();

            if ($count === 1) {
                // Process single change
                foreach ($result['entries'] as $entry) {
                    $this->doSyncByChanges($entry);
                }
            } else {
                // Process multiple changes
                $actions  = array();
                $supports = array('folder', 'deleted');

                foreach ($result['entries'] as $entry) {
                    if (!isset($entry['.tag'])) {
                        continue;
                    }

                    if (!in_array($entry['.tag'], $supports)) {
                        continue;
                    }

                    if ($entry['.tag'] === 'folder') {
                        $folders[] = $entry;
                    }

                    if (!in_array($entry['.tag'], $actions)) {
                        $actions[] = $entry['.tag'];
                    }
                }

                if (empty($actions)) {
                    $this->writeLog('Action is empty');
                    die();
                }

                if (count($actions) === 1) {
                    // Copy or delete actions
                    if (in_array('deleted', $actions)) {
                        $this->processMultipleSyncByChanges($result['entries'], 'deleted');
                    } else {
                        $this->processMultipleSyncByChanges($result['entries'], 'copied');
                    }
                } else {
                    // Move or rename actions
                    $entries   = array();
                    $processed = false;
                    foreach ($result['entries'] as $entry) {
                        if ($entry['.tag'] === 'deleted') {
                            continue;
                        }

                        if ($entry['.tag'] === 'folder') {
                            $entries[]  = $entry;
                            $folderId   = isset($entry['id']) ? $entry['id'] : '';
                            $folderPath = isset($entry['path_lower']) ? $entry['path_lower'] : '';
                            $termId     = WpfdAddonHelper::getTermIdDropBoxByDropBoxId($folderId);

                            if ($termId && $processed === false) {
                                $term                      = get_term_by('id', $termId, 'wpfd-category');
                                $parentTermId              = isset($term->parent) ? $term->parent : 0;
                                $parentFolderIdFromDropbox = $dropbox->getDropboxParentFolderId($folderPath);

                                if (is_null($parentFolderIdFromDropbox) || $parentFolderIdFromDropbox === $baseFolderId) {
                                    $newParentTermId = 0;
                                } else {
                                    $newParentTermId = WpfdAddonHelper::getTermIdDropBoxByDropBoxId($parentFolderIdFromDropbox);
                                }

                                if (intval($parentTermId) === intval($newParentTermId)) {
                                    // Modified
                                    $this->processMultipleSyncByChanges($entries, 'modified');
                                } else {
                                    // Move
                                    $this->processMultipleSyncByChanges($entries, 'move', $folders);
                                }

                                $processed = true;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Process the change to client side
     *
     * @param object|mixed $folder          Folder change
     * @param object|mixed $overwriteChange Overwrite change
     * @param array        $folders         Folders change
     *
     * @throws Exception Throws when application can not start
     * @return mixed|void
     */
    public function doSyncByChanges($folder, $overwriteChange = null, $folders = array())
    {
        if (is_null($folder) || empty($folder)) {
            $this->writeLog('Folder is empty from Webhook notifications');
            exit();
        }

        if (!isset($folder['.tag']) || empty($folder['.tag'])) {
            $this->writeLog('Folder type in not defined');
            exit();
        }

        if (isset($folder['.tag']) && $folder['.tag'] === 'file') {
            $this->writeLog('File type is not support');
            exit();
        }

        if (!isset($folder['name'])) {
            $this->writeLog('Folder name is empty');
            exit();
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

        Application::getInstance('Wpfd');
        $configurationModel = $this->getModel('config');
        if (!$configurationModel instanceof WpfdModelConfig) {
            return false;
        }

        Application::getInstance('WpfdAddon');
        $dropboxCategory = $this->getModel('dropboxcategory');
        if (!$dropboxCategory instanceof WpfdAddonModelDropboxcategory) {
            return false;
        }

        $change       = !is_null($overwriteChange) ? $overwriteChange : $folder['.tag'];
        $folderName   = $folder['name'];
        $dropbox      = new WpfdAddonDropbox();
        $settings     = $dropbox->getAllDropboxConfigs();
        $baseFolderId = (is_array($settings) && isset($settings['dropboxBaseFolderId'])) ? $settings['dropboxBaseFolderId'] : '';

        switch ($change) {
            case 'folder':
                // Create or edit category
                $folderId       = isset($folder['id']) ? $folder['id'] : '';
                $folderPath     = isset($folder['path_lower']) ? $folder['path_lower'] : '';
                $folderPathDisplay = isset($folder['path_display']) ? $folder['path_display'] : '';
                $termId         = WpfdAddonHelper::getTermIdDropBoxByDropBoxId($folderId);
                $parentFolderId = $dropbox->getDropboxParentFolderId($folderPath);

                if (is_null($parentFolderId)) {
                    $parentTermId = 0;
                } else {
                    $parentTermId = WpfdAddonHelper::getTermIdDropBoxByDropBoxId($parentFolderId);
                }

                $folderParams = array(
                    'id'   => $folderId,
                    'name' => $folderName,
                    'path' => $folderPath,
                    'path_display' => $folderPathDisplay,
                );

                if ($termId === false) {
                    // Create a new category
                    $insertedCategoryId = $dropboxCategory->addCategoryFromDropbox($folderParams, $parentTermId, 'end');

                    if ($insertedCategoryId) {
                        $mainConfig                              = $configurationModel->getConfig();
                        $categoryParams                          = array();
                        $categoryParams['theme']                 = isset($mainConfig['defaultthemepercategory']) ? $mainConfig['defaultthemepercategory'] : 'default';
                        $categoryParams['ordering']              = isset($mainConfig['global_files_ordering']) ? $mainConfig['global_files_ordering'] : 'title';
                        $categoryParams['orderingdir']           = isset($mainConfig['global_files_ordering_direction']) ? $mainConfig['global_files_ordering_direction'] : 'desc';
                        $categoryParams['subcategoriesordering'] = isset($mainConfig['global_subcategories_ordering']) ? $mainConfig['global_subcategories_ordering'] : 'customorder';
                        $categoryParams['visibility']            = '-1';
                        $categoryParams['social']                = '0';
                        $categoryParams['category_password']     = '';
                        $globalThemeSettings                     = get_option('_wpfd_' . $categoryParams['theme'] . '_config', array());

                        if (is_array($globalThemeSettings) && !empty($globalThemeSettings)) {
                            $categoryParams = array_merge($categoryParams, $globalThemeSettings);
                        }

                        $saved = $categoryModel->saveParams($insertedCategoryId, $categoryParams);

                        if ($saved) {
                            $this->writeLog('Category name: '. $folderName .' created from Dropbox with success');
                        }
                    } else {
                        $this->writeLog('Failed to create category name: ' . $folderName);
                    }
                } else {
                    // Rename category
                    $saved = $categoryModel->saveTitle($termId, $folderName);

                    if ($saved) {
                        $oldDrivePath = get_term_meta($termId, 'wpfd_drive_path', true);
                        update_term_meta($termId, 'wpfd_drive_path', $folderPath);

                        if (isset($folder['path_display'])) {
                            update_term_meta($termId, 'wpfd_drive_path_display', $folder['path_display']);
                        }

                        $dropboxCategory->updateTermchildren($termId, $oldDrivePath, $folderPath);
                    } else {
                        $this->writeLog('Can not rename category: ' . $folderName);
                    }
                }
                break;
            case 'modified':
                $folderId   = isset($folder['id']) ? $folder['id'] : '';
                $folderPath = isset($folder['path_lower']) ? $folder['path_lower'] : '';
                $termId     = WpfdAddonHelper::getTermIdDropBoxByDropBoxId($folderId);

                if ($termId) {
                    $saved = $categoryModel->saveTitle($termId, $folderName);

                    if ($saved) {
                        $oldDrivePath = get_term_meta($termId, 'wpfd_drive_path', true);
                        update_term_meta($termId, 'wpfd_drive_path', $folderPath);

                        if (isset($folder['path_display'])) {
                            update_term_meta($termId, 'wpfd_drive_path_display', $folder['path_display']);
                        }

                        $dropboxCategory->updateTermchildren($termId, $oldDrivePath, $folderPath);
                    } else {
                        $this->writeLog('Can not update category name: ' . $folderName);
                    }
                }
                break;
            case 'move':
                // Move categories on WP
                $folderId   = isset($folder['id']) ? $folder['id'] : '';
                $folderPath = isset($folder['path_lower']) ? $folder['path_lower'] : '';
                $termId     = WpfdAddonHelper::getTermIdDropBoxByDropBoxId($folderId);

                if ($termId) {
                    $parentFolderIdFromDropbox = $dropbox->getDropboxParentFolderId($folderPath);

                    if (is_null($parentFolderIdFromDropbox) || $parentFolderIdFromDropbox === $baseFolderId) {
                        $newParentTermId = 0;
                    } else {
                        $newParentTermId = WpfdAddonHelper::getTermIdDropBoxByDropBoxId($parentFolderIdFromDropbox);
                    }

                    if ($categoryModel->changeOrder($termId, $newParentTermId, 'first-child')) {
                        update_term_meta($termId, 'wpfd_drive_path', $folderPath);

                        if (isset($folder['path_display'])) {
                            update_term_meta($termId, 'wpfd_drive_path_display', $folder['path_display']);
                        }

                        // Process all child related the moving on site
                        if (!empty($folders)) {
                            foreach ($folders as $child) {
                                if (!isset($child['id'])) {
                                    continue;
                                }

                                if (!isset($child['path_lower'])) {
                                    continue;
                                }

                                if ($child['id'] === $folderId) {
                                    continue;
                                }

                                $childTermId = WpfdAddonHelper::getTermIdDropBoxByDropBoxId($child['id']);

                                if ($childTermId) {
                                    $childFolderPath = $child['path_lower'];
                                    update_term_meta($childTermId, 'wpfd_drive_path', $childFolderPath);
                                    if (isset($child['path_display'])) {
                                        update_term_meta($childTermId, 'wpfd_drive_path_display', $child['path_display']);
                                    }
                                }
                            }
                        }
                    }
                }
                break;
            case 'deleted':
                // Delete categories
                if (!pathinfo($folderName, PATHINFO_EXTENSION)) {
                    $folderPath = isset($folder['path_lower']) ? $folder['path_lower'] : '';
                    $termId     = WpfdAddonHelper::getTermIdByDropboxPath($folderPath);

                    if ($termId) {
                        $deleted = $dropboxCategory->deleteCategoryFromDropbox($termId);

                        if (!$deleted) {
                            $this->writeLog('Can not delete category name ' . $folderName);
                        }
                    }
                }
                break;
            default:
                break;
        }
    }

    /**
     * Process multiple changes
     *
     * @param array  $entries Changed items
     * @param string $action  Action
     * @param array  $folders Folders change
     *
     * @throws Exception Fire if errors
     *
     * @return void
     */
    public function processMultipleSyncByChanges($entries, $action, $folders = array())
    {
        if (empty($entries)) {
            $this->writeLog('Entries is empty');
            exit();
        }

        if (empty($action)) {
            $this->writeLog('Action is empty');
            exit();
        }

        switch ($action) {
            case 'copied':
            case 'deleted':
                foreach ($entries as $entry) {
                    $this->doSyncByChanges($entry);
                }
                break;
            case 'modified':
                foreach ($entries as $entry) {
                    $this->doSyncByChanges($entry, 'modified');
                }
                break;
            case 'move':
                foreach ($entries as $entry) {
                    $this->doSyncByChanges($entry, 'move', $folders);
                }
                break;
            default:
                break;
        }
    }

    /**
     * Write error log
     *
     * @param string     $message Message
     * @param null|mixed $data    Log data
     *
     * @return void
     */
    public function writeLog($message, $data = null)
    {
        $prefix = '[' . strtoupper(str_replace('_', ' ', $this->type)) . ']';
        if ($this->debug) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug purposed
            error_log($prefix . $message);
            if ($data !== null) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r,WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug purposed
                error_log($prefix . print_r($data, true));
            }
        }
    }
}
