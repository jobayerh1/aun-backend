<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Application;

/**
 * Class WpfdAddonModelOnedrivecategory
 */
class WpfdAddonModelOnedrivecategory extends Model
{
    /**
     * Onedrive category instance
     *
     * @var WpfdAddonOneDrive
     */
    protected $onedriveCate;


    /**
     * WpfdAddonModelOnedrivecategory constructor.
     */
    public function __construct()
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdaddononedrive = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddononedrive .= DIRECTORY_SEPARATOR . 'WpfdAddonOneDrive.php';
        require_once $path_wpfdaddononedrive;
        $this->onedriveCate = new WpfdAddonOneDrive();
    }

    /**
     * Get array onedrive id
     *
     * @return mixed
     */
    public function getArrayOneDriveId()
    {
        $onedriveParams = get_option('_wpfdAddon_onedrive_category_params');
        return $onedriveParams['onedrive'];
    }

    /**
     * Add onedrive category
     *
     * @param string  $title    New category title
     * @param integer $parent   Parent category id
     * @param string  $position New category position
     *
     * @return boolean|array
     */
    public function addOneDriveCategory($title, $parent = 0, $position = 'end')
    {

        $title = trim(sanitize_text_field($title));
        if ($title === '') {
            return false;
        }
        if ($position === 'end') {
            $inserted = wp_insert_term($title, 'wpfd-category', array('slug' => sanitize_title($title), 'parent' => $parent));

            $lastCats = get_terms(
                'wpfd-category',
                'orderby=term_group&order=DESC&hierarchical=0&hide_empty=0&parent=' . $parent . '&number=1'
            );
            if (is_array($lastCats) && count($lastCats)) {
                $this->updateTermOrder($inserted['term_id'], $lastCats[0]->term_group + 1);
            }
        } else {
            // Update all terms term_group +1
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    'UPDATE ' . $wpdb->terms . ' as t SET t.term_group = t.term_group + 1 WHERE term_id IN (SELECT tt.term_id from ' . $wpdb->term_taxonomy . ' as tt WHERE tt.parent = %d)',
                    $parent
                )
            );

            $inserted = wp_insert_term($title, 'wpfd-category', array('slug' => sanitize_title($title), 'parent' => $parent));
            $this->updateTermOrder((int) $inserted['term_id'], 0);
        }
        $data = get_option('_wpfdAddon_onedrive_category_params');
        if (!$data) {
            $oneDriveParams = array();
            $data = array('onedrive' => $oneDriveParams);
        }
        $oneDriveConfigs = WpfdAddonHelper::getAllOneDriveConfigs();
        if ($parent === 0) {
            $oneBaseFolderId = $oneDriveConfigs['onedriveBaseFolder']['id'];
        } else {
            $oneBaseFolderId = WpfdAddonHelper::getOneDriveIdByTermId($parent);
        }

        /* @var Krizalys\Onedrive\Proxy\DriveItemProxy $oneDrive */
        $oneDrive = $this->addFolderOnOneDrive($title, $oneBaseFolderId);
        if (!is_wp_error($oneDrive)) {
            $oneDriveId = WpfdAddonHelper::replaceIdOneDrive($oneDrive->id);
            update_term_meta($inserted['term_id'], 'wpfd_drive_id', $oneDriveId);
            update_term_meta($inserted['term_id'], 'wpfd_drive_type', 'onedrive');

            $oneDriveName = $oneDrive->name;

            return array(
                'success' => true,
                'id' => $inserted['term_id'],
                'name' => $oneDriveName
            );
        } else {
            // Create onedrive folder false, remove term
            wp_delete_term($inserted['term_id'], 'wpfd-category');
            return array(
                'success' => false,
                'code' => $oneDrive->get_error_code(),
                'message' => $oneDrive->get_error_message()
            );
        }
    }

    /**
     * Add new folder onedrive
     *
     * @param string $title    New category title
     * @param string $parentId OneDrive parent id
     *
     * @return boolean|Krizalys\Onedrive\Proxy\DriveItemProxy
     */
    public function addFolderOnOneDrive($title, $parentId)
    {
        return $this->onedriveCate->createFolder($title, $parentId);
    }

    /**
     * Add category from onedrive
     *
     * @param string  $title      New category title
     * @param string  $oneDriveId Onedrive id
     * @param integer $parent     Parent term id
     *
     * @return boolean
     */
    public function addCategoryFromOneDrive($title, $oneDriveId, $parent)
    {
        $title = trim(sanitize_text_field($title));
        if ($title === '') {
            return false;
        }
        $inserted = wp_insert_term(
            $title,
            'wpfd-category',
            array('slug' => sanitize_title($title), 'parent' => $parent)
        );
        if (is_wp_error($inserted)) {
            //try again
            $inserted = wp_insert_term(
                $title,
                'wpfd-category',
                array('slug' => sanitize_title($title) . '-' . uniqid(), 'parent' => $parent)
            );
            if (is_wp_error($inserted)) {
                wp_send_json($inserted->get_error_message());
            }
        }
        $lastCats = get_terms(
            'wpfd-category',
            'orderby=term_group&order=DESC&hierarchical=0&hide_empty=0&parent=0&number=1'
        );
        if (is_array($lastCats) && count($lastCats)) {
            $this->updateTermOrder($inserted['term_id'], $lastCats[0]->term_group + 1);
        }
        $oneDriveId = WpfdAddonHelper::replaceIdOneDrive($oneDriveId);
        update_term_meta($inserted['term_id'], 'wpfd_drive_id', $oneDriveId);
        update_term_meta($inserted['term_id'], 'wpfd_drive_type', 'onedrive');

        WpfdAddonHelper::updateDefaultCategoryParams($inserted['term_id'], 'onedrive');

        return true;
    }

    /**
     * Change parent
     *
     * @param integer $termId Term Id
     * @param string  $parent Cloud parent id
     *
     * @return void
     */
    public function changeParent($termId, $parent)
    {
        $onedriveParams = get_option('_wpfdAddon_onedrive_category_params');
        foreach ($onedriveParams['onedrive'] as $key => $val) {
            if ($val['oneDriveId'] === $parent) {
                wp_update_term($termId, 'wpfd-category', array('parent' => $val['termId']));
            }
        }
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
     * Delete onedrive category
     *
     * @param integer $id_category Category id
     *
     * @return boolean|integer|WP_Error
     */
    public function deleteOneDriveCategory($id_category)
    {
        //before delete term
        $result_term = get_term($id_category, 'wpfd-category');
        $list_category_own = array();
        if ($result_term && !empty($result_term)) {
            $description = json_decode($result_term->description, true);
        }
        $list_category_own[] = isset($description['category_own']) ? $description['category_own'] : 0;
        $list_category_own[] = isset($description['category_own_old']) ? $description['category_own_old'] : 0;
        // Delete term
        $cloudId = get_term_meta($id_category, 'wpfd_drive_id', true);
        $cloudType = get_term_meta($id_category, 'wpfd_drive_type', true);
        $result = false;
        if (!empty($cloudId) && $cloudType === 'onedrive') {
            if ($this->onedriveCate->delete($cloudId)) {
                $result = wp_delete_term($id_category, 'wpfd-category');
                // Delete category user meta
                WpfdAddonHelper::delCatInUserMeta($id_category, $list_category_own);
            }
        }

        return $result;
    }

    /**
     * Get children
     *
     * @param integer $term_id Term id
     *
     * @return array
     */
    public function getChildren($term_id)
    {
        $results = array();
        $this->getChildrenRecursive($term_id, $results);
        return $results;
    }

    /**
     * Get children recursive
     *
     * @param integer $term_id Term id
     * @param array   $results Results
     *
     * @return void
     */
    public function getChildrenRecursive($term_id, &$results)
    {
        if (!is_array($results)) {
            $results = array();
        }
        $categories = get_terms('wpfd-category', 'orderby=term_group&hierarchical=1&hide_empty=0&parent=' . (int) $term_id);
        if ($categories) {
            foreach ($categories as $category) {
                $results[] = $category->term_id;
                $this->getChildrenRecursive($category->term_id, $results);
            }
        }
    }

    /**
     * Change order
     *
     * @param integer $pk Target term id
     *
     * @return void
     */
    public function changeOrder($pk)
    {
        Application::getInstance('Wpfd');
        $category = Model::getInstance('category');
        $params = WpfdAddonHelper::getAllOneDriveConfigs();
        if (isset($params['onedriveConnected']) && $params['onedriveConnected'] === 1) {
            $itemInfo = $category->getCategory($pk);
            //$parentInfo=$category->getCategory($itemInfo->parent);
            if (WpfdAddonHelper::getOneDriveIdByTermId($itemInfo->parent)) {
                $this->onedriveCate->moveFile(
                    WpfdAddonHelper::getOneDriveIdByTermId($pk),
                    WpfdAddonHelper::getOneDriveIdByTermId($itemInfo->parent)
                );
            } else {
                $this->onedriveCate->moveFile(
                    WpfdAddonHelper::getOneDriveIdByTermId($pk),
                    $params['onedriveBaseFolder']['id']
                );
            }
        }
    }

    /**
     * Change category name
     *
     * @param string $cloudId Cloud id
     * @param string $title   Title
     *
     * @return boolean
     */
    public function changeCategoryName($cloudId, $title)
    {
        if (empty($cloudId)) {
            return false;
        }

        return $this->onedriveCate->changeFilename($cloudId, $title);
    }

    /**
     * Delete category after disconnect onedrive
     *
     * @return boolean
     */
    public function deleteCategoryWhenDisconnect()
    {
        global $wpdb;
        $results = $wpdb->get_results(
            $wpdb->prepare('SELECT term_id FROM ' . $wpdb->termmeta . ' WHERE meta_value = %s GROUP BY term_id', 'onedrive')
        );

        foreach ($results as $term) {
            wp_delete_term($term->term_id, 'wpfd-category');
        }

        return true;
    }
}
