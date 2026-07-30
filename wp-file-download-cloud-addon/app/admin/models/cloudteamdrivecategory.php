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
 * Class WpfdAddonModelCloudTeamDrivecategory
 */
class WpfdAddonModelCloudTeamDrivecategory extends Model
{
    /**
     * Google team drive category instance
     *
     * @var WpfdAddonGoogleTeamDrive
     */
    protected $googleTeamDriveCate;


    /**
     * WpfdAddonModelCloudTeamDrivecategory constructor.
     */
    public function __construct()
    {
        $app                  = Application::getInstance('WpfdAddon');
        $path_wpfdaddongoogle = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddongoogle .= DIRECTORY_SEPARATOR . 'WpfdAddonGoogleTeamDrive.php';
        require_once $path_wpfdaddongoogle;
        $this->googleTeamDriveCate = new WpfdAddonGoogleTeamDrive();
    }

    /**
     * Get array google drive id
     *
     * @return mixed
     */
    public function getArrayGoogleDriveId()
    {
        global $wpdb;
        $results = $wpdb->get_results('SELECT meta_value from ' . $wpdb->termmeta . ' WHERE meta_key = \'wpfd_drive_id\' AND meta_id IN (SELECT meta_id FROM ' . $wpdb->termmeta . ' WHERE meta_key = \'wpfd_drive_type\' AND meta_value = \'googleTeamDrive\')', ARRAY_A);
        return array_map(function ($result) {
            return array('idCloud' => $result['meta_value']);
        }, $results);
    }

    /**
     * Add category
     *
     * @param string  $title    New category title
     * @param integer $parent   Category parent id
     * @param string  $position New category position
     *
     * @return integer|boolean
     */
    public function addCategory($title, $parent = 0, $position = 'end')
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

        $cloudConfigs = WpfdAddonHelper::getAllCloudTeamDriveConfigs();
        if ($parent === 0) {
            $googleTeamDriveBaseFolder = $cloudConfigs['googleTeamDriveBaseFolder'];
        } else {
            $googleTeamDriveBaseFolder = WpfdAddonHelper::getGoogleTeamDriveIdByTermId($parent);
        }
        $cloud = $this->addFolderOnGoogleTeamDrive($title, $googleTeamDriveBaseFolder);

        if (false === $cloud) {
            // Delete created term
            wp_delete_term($inserted['term_id'], 'wpfd-category');
            return false;
        }

        $cloudId = $cloud->getId();
        update_term_meta($inserted['term_id'], 'wpfd_drive_id', $cloudId);
        update_term_meta($inserted['term_id'], 'wpfd_drive_type', 'googleTeamDrive');

        return $inserted['term_id'];
    }

    /**
     * Add folder google drive
     *
     * @param string $title    Category name
     * @param string $parentId Category parent
     *
     * @return boolean|Google
     */
    public function addFolderOnGoogleTeamDrive($title, $parentId)
    {
        return $this->googleTeamDriveCate->createFolder($title, $parentId);
    }

    /**
     * Add category from google drive
     *
     * @param string  $title   Category name
     * @param string  $cloudId Category id on cloud
     * @param integer $parent  Term parent
     *
     * @return boolean
     */
    public function addCategoryFromGoogleTeamDrive($title, $cloudId, $parent)
    {
        if (mb_strlen($title) > 190) {
            $title = mb_substr($title, 0, 190);
        }
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
            // Try again
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
        update_term_meta($inserted['term_id'], 'wpfd_drive_id', $cloudId);
        update_term_meta($inserted['term_id'], 'wpfd_drive_type', 'googleTeamDrive');
        WpfdAddonHelper::updateDefaultCategoryParams($inserted['term_id'], 'google');

        return true;
    }

    /**
     * Change parent
     *
     * @param integer $termId Term id
     * @param string  $parent Parent cloud id
     *
     * @return void
     */
    public function changeParent($termId, $parent)
    {
        $cloudParams = get_option('_wpfdAddon_cloud_category_params');
        foreach ($cloudParams['googleTeamDrive'] as $key => $val) {
            if ($val['idCloud'] === $parent) {
                wp_update_term($termId, 'wpfd-category', array('parent' => $val['termId']));
            }
        }
    }

    /**
     * Update term order
     *
     * @param integer $term_id Term id
     * @param integer $order   Ordering
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
     * Delete category
     *
     * @param integer $id_category Category id to delete
     *
     * @return boolean|integer|WP_Error
     */
    public function deleteCategory($id_category)
    {
        // Before deleting a wp term
        $result_term       = get_term($id_category, 'wpfd-category');
        $list_category_own = array();
        $description       = array();

        if ($result_term && !empty($result_term)) {
            $description = json_decode($result_term->description, true);
        }

        $list_category_own[] = isset($description['category_own']) ? $description['category_own'] : 0;
        $list_category_own[] = isset($description['category_own_old']) ? $description['category_own_old'] : 0;
        // Delete a wp term
        $cloudId   = get_term_meta($id_category, 'wpfd_drive_id', true);
        $cloudType = get_term_meta($id_category, 'wpfd_drive_type', true);
        $result    = false;

        if (!empty($cloudId) && $cloudType === 'googleTeamDrive') {
            if ($this->googleTeamDriveCate->delete($cloudId)) {
                $result = wp_delete_term($id_category, 'wpfd-category');
                // Delete related category user meta
                WpfdAddonHelper::delCatInUserMeta($id_category, $list_category_own);
            }
        }

        return $result;
    }

    /**
     * Get children
     *
     * @param integer $id Term id
     *
     * @return array
     */
    public function getChildren($id)
    {
        $results = array();
        $this->getChildrenRecursive($id, $results);
        return $results;
    }

    /**
     * Get children recursive
     *
     * @param integer $catid   Term id
     * @param array   $results Results
     *
     * @return void
     */
    public function getChildrenRecursive($catid, &$results)
    {
        if (!is_array($results)) {
            $results = array();
        }
        $categories = get_terms('wpfd-category', 'orderby=term_group&hierarchical=1&hide_empty=0&parent=' . (int) $catid);
        if (!is_wp_error($categories) && is_array($categories) && !empty($categories)) {
            foreach ($categories as $category) {
                $results[] = $category->term_id;
                $this->getChildrenRecursive($category->term_id, $results);
            }
        }
    }

    /**
     * Change order
     *
     * @param integer $pk Pk
     *
     * @return void
     */
    public function changeOrder($pk)
    {
        Application::getInstance('Wpfd');
        $category = Model::getInstance('category');
        $params   = WpfdAddonHelper::getAllCloudTeamDriveConfigs();
        if ($params['googleTeamDriveCredentials']) {
            $itemInfo = $category->getCategory($pk);

            if (WpfdAddonHelper::getGoogleTeamDriveIdByTermId($itemInfo->parent)) {
                $this->googleTeamDriveCate->moveFile(
                    WpfdAddonHelper::getGoogleTeamDriveIdByTermId($pk),
                    WpfdAddonHelper::getGoogleTeamDriveIdByTermId($itemInfo->parent)
                );
            } else {
                $this->googleTeamDriveCate->moveFile(
                    WpfdAddonHelper::getGoogleTeamDriveIdByTermId($pk),
                    $params['googleTeamDriveBaseFolder']
                );
            }
        }
    }

    /**
     * Change category name
     *
     * @param string $cloudId Cloud id
     * @param string $title   New category name
     *
     * @return boolean
     */
    public function changeCategoryName($cloudId, $title)
    {
        if (empty($cloudId)) {
            return false;
        }
        return $this->googleTeamDriveCate->changeFilename($cloudId, $title);
    }


    /**
     * Delete category when dis connected
     *
     * @return boolean
     */
    public function deleteCategoryWhenDisconnect()
    {
        global $wpdb;
        $results = $wpdb->get_results(
            $wpdb->prepare('SELECT term_id FROM ' . $wpdb->termmeta . ' WHERE meta_value = %s GROUP BY term_id', 'googleTeamDrive')
        );

        foreach ($results as $term) {
            wp_delete_term($term->term_id, 'wpfd-category');
        }

        return true;
    }
}
