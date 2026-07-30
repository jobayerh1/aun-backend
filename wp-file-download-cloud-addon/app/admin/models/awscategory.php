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
 * Class WpfdAddonModelAwscategory
 */
class WpfdAddonModelAwscategory extends Model
{
    /**
     * AWS category instance
     *
     * @var WpfdAddonAws
     */
    protected $awsCate;

    /**
     * WpfdAddonModelAwscategory constructor.
     */
    public function __construct()
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdaddon_aws = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        $path_wpfdaddon_aws .= DIRECTORY_SEPARATOR . 'WpfdAddonAws.php';
        require_once $path_wpfdaddon_aws;
        $this->awsCate = new WpfdAddonAws;
    }

    /**
     * Add AWS category
     *
     * @param string  $title    New category title
     * @param integer $parent   Category parent id
     * @param string  $position New category position
     *
     * @return boolean
     */
    public function addAwsCategory($title, $parent = 0, $position = 'end')
    {
        $title = trim(sanitize_text_field($title));
        if ($title === '') {
            return false;
        }

        $result = $this->addFolderOnAws($title, $parent);
        if (is_array($result)) {
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

            update_term_meta($inserted['term_id'], 'wpfd_drive_path', $result['path']);
            update_term_meta($inserted['term_id'], 'wpfd_drive_type', 'aws');
            
            return $inserted['term_id'];
        } else {
            return false;
        }
    }

    /**
     * Add folder on AWS
     *
     * @param string  $title  Category title
     * @param integer $parent Category parent id
     *
     * @return array|boolean|null
     */
    public function addFolderOnAws($title, $parent)
    {
        $params = array(
            'title'     => $title,
            'parent'    => $parent,
            'type'      => 'folder'
        );
        return $this->awsCate->uploadObject($params);
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
     * Delete AWS category
     *
     * @param integer $id_category Category id
     *
     * @return boolean|integer|WP_Error
     */
    public function deleteAwsCategory($id_category)
    {
        // Delete term
        // $fileInfo = WpfdAddonHelper::getAwsFileInfos();
        $termChilds   = get_term_children($id_category, 'wpfd-category');
        $termChilds[] = $id_category;
        foreach ($termChilds as $termChild) {
            // Before delete term
            $result_term       = get_term($id_category, 'wpfd-category');
            $list_category_own = array();
            if ($result_term && !empty($result_term)) {
                $description = json_decode($result_term->description, true);
            }
            $list_category_own[] = isset($description['category_own']) ? $description['category_own'] : 0;
            $list_category_own[] = isset($description['category_own_old']) ? $description['category_own_old'] : 0;

            $awsPath = WpfdAddonHelper::getAwsPathByTermId($id_category);
            $params = array(
                'path' => $awsPath,
                'type' => 'folder'
            );
            if ($this->awsCate->deleteObject($params)) {
                // Remove files info
                // if (!empty($fileInfo[$termChild])) {
                //     unset($fileInfo[$termChild]);
                // }
                wp_delete_term($termChild, 'wpfd-category');
                WpfdAddonHelper::delCatInUserMeta($termChild, $list_category_own);
            }
        }

        // Update fileinfos
        // WpfdAddonHelper::setAwsFileInfos($fileInfo);

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
     * Rename AWS category
     *
     * @param string  $awsObjectKey Aws object key
     * @param string  $title        New category name
     * @param integer $termId       Term id
     *
     * @return boolean
     */
    public function changeAwsCategoryName($awsObjectKey, $title, $termId)
    {
        if (empty($awsObjectKey)) {
            return false;
        }
        $f = pathinfo($awsObjectKey);

        if (strlen($f['dirname']) === 1) {
            $f['dirname'] = '';
            $ntitle = $f['dirname'] . $title;
        } else {
            $ntitle = $f['dirname'] . '/' . $title;
        }
        $result = $this->awsCate->changeAwsFilename($awsObjectKey, $ntitle);
        if ($result) {
            $oldDrivePath = get_term_meta($termId, 'wpfd_drive_path', true);
            update_term_meta($termId, 'wpfd_drive_path', $ntitle);

            $this->updateTermchildren($termId, $oldDrivePath, $ntitle);
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
     * Change AWS order
     *
     * @param integer $moveId     Term id
     * @param integer $locationId Target term id
     *
     * @return void
     */
    public function changeAwsOrder($moveId, $locationId)
    {
        Application::getInstance('Wpfd');
        $category = Model::getInstance('category');
        $params   = WpfdAddonHelper::getAllAwsConfigs();
        if ($params['awsConnected']) {
            $itemInfo = $category->getCategory($moveId);
            $folderName = $itemInfo->name;
            $move     = WpfdAddonHelper::getAwsPathByTermId($moveId);
            $location = WpfdAddonHelper::getAwsPathByTermId($locationId);
            if (WpfdAddonHelper::getAwsPathByTermId($itemInfo->parent)) {
                $newDrivePath = $this->awsCate->changeAwsOrder(
                    $move,
                    WpfdAddonHelper::getAwsPathByTermId($itemInfo->parent),
                    $itemInfo->parent,
                    $folderName
                );
            } else {
                $newDrivePath = $this->awsCate->changeAwsOrder($move, $location, $itemInfo->parent, $folderName);
            }
            if ($newDrivePath) {
                update_term_meta($moveId, 'wpfd_drive_path', $newDrivePath);
            }
        }
    }

    /**
     * Delete AWS category
     *
     * @return boolean
     */
    public function deleteAwsCategoryWhenDisconnect()
    {
        global $wpdb;
        $results = $wpdb->get_results(
            $wpdb->prepare('SELECT term_id FROM ' . $wpdb->termmeta . ' WHERE meta_value = %s GROUP BY term_id', 'aws')
        );

        foreach ($results as $term) {
            wp_delete_term($term->term_id, 'wpfd-category');
        }
        WpfdAddonHelper::setAwsFileInfos(array());
        return true;
    }
}
