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
 * Class WpfdAddonModelCloudcategory
 */
class WpfdAddonModelDropboxcategory extends Model
{
    /**
     * Dropbox category instance
     *
     * @var WpfdAddonGoogleDrive
     */
    protected $dropboxCate;

    /**
     * WpfdAddonModelDropboxcategory constructor.
     */
    public function __construct()
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdaddondropbox = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddondropbox .= DIRECTORY_SEPARATOR . 'WpfdAddonDropbox.php';
        require_once $path_wpfdaddondropbox;
        $this->dropboxCate = new WpfdAddonDropbox;
    }

    /**
     * Add dropbox category
     *
     * @param string  $title    New category title
     * @param integer $parent   Category parent id
     * @param string  $position New category position
     *
     * @return boolean
     */
    public function addDropCategory($title, $parent = 0, $position = 'end')
    {
        $title = trim(sanitize_text_field($title));
        if ($title === '') {
            return false;
        }
        if ($position === 'end') {
            $inserted = wp_insert_term($title, 'wpfd-category', array('slug'   => sanitize_title($title), 'parent' => $parent));

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

        $result        = $this->addFolderOnDropbox($title, $parent);
        if (is_array($result)) {
            update_term_meta($inserted['term_id'], 'wpfd_drive_id', $result['id']);
            update_term_meta($inserted['term_id'], 'wpfd_drive_path', $result['path_lower']);
            update_term_meta($inserted['term_id'], 'wpfd_drive_path_display', $result['path_display']);
            update_term_meta($inserted['term_id'], 'wpfd_drive_type', 'dropbox');
        }

        return $inserted['term_id'];
    }

    /**
     * Add folder on Dropbox
     *
     * @param string  $title  Category title
     * @param integer $parent Category parent id
     *
     * @return array|boolean|null
     */
    public function addFolderOnDropbox($title, $parent)
    {
        return $this->dropboxCate->createDropFolder($title, $parent);
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
                'UPDATE ' . $wpdb->terms . ' SET term_group = %d WHERE term_id =%d',
                (int) $order,
                (int) $term_id
            )
        );
    }

    /**
     * Delete dropbox category
     *
     * @param integer $id_category Category id
     *
     * @return boolean|integer|WP_Error
     */
    public function deleteDropboxCategory($id_category)
    {
        // Delete term
        $fileInfo = WpfdAddonHelper::getDropboxFileInfos();
        $termChilds   = get_term_children($id_category, 'wpfd-category');
        $termChilds[] = $id_category;
        // Before delete term
        $result_term       = get_term($id_category, 'wpfd-category');
        $list_category_own = array();
        if ($result_term && !empty($result_term)) {
            $description = json_decode($result_term->description, true);
        }
        $list_category_own[] = isset($description['category_own']) ? $description['category_own'] : 0;
        $list_category_own[] = isset($description['category_own_old']) ? $description['category_own_old'] : 0;
        if ($this->dropboxCate->deleteDropbox(WpfdAddonHelper::getDropboxPathByTermId($id_category))) {
            foreach ($termChilds as $termChild) {
                // Remove files info
                if (!empty($fileInfo[$termChild])) {
                    unset($fileInfo[$termChild]);
                }
                wp_delete_term($termChild, 'wpfd-category');
                WpfdAddonHelper::delCatInUserMeta($termChild, $list_category_own);
            }
        }

        // Update fileinfos
        WpfdAddonHelper::setDropboxFileInfos($fileInfo);

        return true;
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
     * Rename Dropbox category
     *
     * @param string  $dropboxId Dropbox category id
     * @param string  $title     New category name
     * @param integer $termId    Term id
     *
     * @return boolean
     */
    public function changeDropboxCategoryName($dropboxId, $title, $termId)
    {
        if (empty($dropboxId)) {
            return false;
        }
        $f = pathinfo($dropboxId);

        if (strlen($f['dirname']) === 1) {
            $f['dirname'] = '/';
            $ntitle = $f['dirname'] . $title;
        } else {
            $ntitle = $f['dirname'] . '/' . $title;
        }
        $result = $this->dropboxCate->changeDropboxFilename($dropboxId, $ntitle);
        if ($result) {
//            $dataDropboxCategory                           = get_option('_wpfdAddon_dropbox_category_params');
//            $dataDropboxCategory[$termId]['idDropbox']     = strtolower($ntitle);
//            $dataDropboxCategory[$termId]['idDropboxSlug'] = $title;
//            update_option('_wpfdAddon_dropbox_category_params', $dataDropboxCategory);
            $oldDrivePath = get_term_meta($termId, 'wpfd_drive_path', true);
            update_term_meta($termId, 'wpfd_drive_path', $result['path_lower']);

            $this->updateTermchildren($termId, $oldDrivePath, $result['path_lower']);
//            update_option('_wpfdAddon_dropbox_category_params', $dataDropboxCategory);
            return true;
        }
        return false;
    }

    /**
     * Update term children
     *
     * @param string $termId        Term id
     * @param string $oldParentPath Old parent path
     * @param array  $newParentPath New parent path
     *
     * @return void
     */
    public function updateTermchildren($termId, $oldParentPath, $newParentPath)
    {
        $children = get_terms('wpfd-category', array('parent' => $termId, 'hide_empty' => false));
        if (is_array($children)) {
            foreach ($children as $child) {
//                $k                                       = $child->term_id;
//                $slug                                    = $dataDropboxCategory[$k]['idDropboxSlug'];
//                $dataDropboxCategory[$k]['idDropboxDir'] = $ntitle;
//                $dataDropboxCategory[$k]['idDropbox']    = $ntitle . '/' . $slug;
//                $this->updateTermchildren($child->term_id, $ntitle . '/' . $slug, $dataDropboxCategory);
                $oldDrivePath = get_term_meta($child->term_id, 'wpfd_drive_path', true);
                if (!$oldDrivePath) {
                    continue;
                }
                $newDrivePath = str_replace($oldParentPath, $newParentPath, $oldDrivePath);
                update_term_meta($child->term_id, 'wpfd_drive_path', $newDrivePath);
                $this->updateTermchildren($child->term_id, $oldDrivePath, $newDrivePath);
            }
        }
    }

    /**
     * Change dropbox order
     *
     * @param integer $moveId     Term id
     * @param integer $locationId Target term id
     *
     * @return void
     */
    public function changeDropboxOrder($moveId, $locationId)
    {
        Application::getInstance('Wpfd');
        $category = Model::getInstance('category');
        $params   = WpfdAddonHelper::getAllDropboxConfigs();
        if ($params['dropboxAccessToken']) {
            $itemInfo = $category->getCategory($moveId);
            $move     = WpfdAddonHelper::getDropboxPathDisplayByTermId($moveId) ? WpfdAddonHelper::getDropboxPathDisplayByTermId($moveId)
                : WpfdAddonHelper::getDropboxPathByTermId($moveId);
            $location = WpfdAddonHelper::getDropboxPathDisplayByTermId($locationId) ? WpfdAddonHelper::getDropboxPathDisplayByTermId($locationId)
                : WpfdAddonHelper::getDropboxPathByTermId($locationId);

            if (WpfdAddonHelper::getDropboxPathByTermId($itemInfo->parent)) {
                $order = $this->dropboxCate->changeDropboxOrder(
                    $move,
                    WpfdAddonHelper::getDropboxPathByTermId($itemInfo->parent),
                    $itemInfo->parent
                );
            } else {
                $order = $this->dropboxCate->changeDropboxOrder($move, $location, $itemInfo->parent);
            }

            if ($order) {
                update_term_meta($moveId, 'wpfd_drive_path', $order['path_lower']);
                update_term_meta($moveId, 'wpfd_drive_path_display', $order['path_display']);
            }
        }
    }

    /**
     * Delete dropbox category
     *
     * @return boolean
     */
    public function deleteDropboxCategoryWhenDisconnect()
    {
        global $wpdb;
        $results = $wpdb->get_results(
            $wpdb->prepare('SELECT term_id FROM ' . $wpdb->termmeta . ' WHERE meta_value = %s GROUP BY term_id', 'dropbox')
        );

        foreach ($results as $term) {
            wp_delete_term($term->term_id, 'wpfd-category');
        }
        WpfdAddonHelper::setDropboxFileInfos(array());
        return true;
    }

    /**
     * Add category from dropbox
     *
     * @param string|array $folder   Folder information
     * @param integer      $parent   Category parent id
     * @param string       $position New category position
     *
     * @return boolean
     */
    public function addCategoryFromDropbox($folder, $parent = 0, $position = 'end')
    {
        if (empty($folder)) {
            return false;
        }

        $title       = isset($folder['name']) ? $folder['name'] : '';
        $title       = trim(sanitize_text_field($title));
        $dropboxId   = isset($folder['id']) ? $folder['id'] : '';
        $dropboxPath = isset($folder['path']) ? $folder['path'] : '';
        $dropboxPathDisplay = isset($folder['path_display']) ? $folder['path_display'] : '';

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

        update_term_meta($inserted['term_id'], 'wpfd_drive_id', $dropboxId);
        update_term_meta($inserted['term_id'], 'wpfd_drive_path', $dropboxPath);
        update_term_meta($inserted['term_id'], 'wpfd_drive_path_display', $dropboxPathDisplay);
        update_term_meta($inserted['term_id'], 'wpfd_drive_type', 'dropbox');

        return $inserted['term_id'];
    }

    /**
     * Delete category from Dropbox
     *
     * @param integer $id_category Category id
     *
     * @return boolean|integer|WP_Error
     */
    public function deleteCategoryFromDropbox($id_category)
    {
        // Delete term and childs
        $fileInfo          = WpfdAddonHelper::getDropboxFileInfos();
        $termChilds        = get_term_children($id_category, 'wpfd-category');
        $termChilds[]      = $id_category;
        $result_term       = get_term($id_category, 'wpfd-category');
        $list_category_own = array();
        $description       = array();

        if ($result_term && !empty($result_term)) {
            $description = json_decode($result_term->description, true);
        }

        $list_category_own[] = isset($description['category_own']) ? $description['category_own'] : 0;
        $list_category_own[] = isset($description['category_own_old']) ? $description['category_own_old'] : 0;

        foreach ($termChilds as $termChild) {
            // Remove files info
            if (!empty($fileInfo[$termChild])) {
                unset($fileInfo[$termChild]);
            }
            wp_delete_term($termChild, 'wpfd-category');
            WpfdAddonHelper::delCatInUserMeta($termChild, $list_category_own);
        }

        // Update fileinfos
        WpfdAddonHelper::setDropboxFileInfos($fileInfo);

        return true;
    }
}
