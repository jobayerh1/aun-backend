<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Utilities;
use Joomunited\WPFramework\v1_0_6\Controller;
use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Filesystem;

/**
 * Class WpfdAddonControllerCategory
 */
class WpfdAddonControllerCategory extends Controller
{
    /**
     * Google Drive category instance
     *
     * @var boolean
     */
    protected $cloudCategory;
    /**
     * Google Team Drive category instance
     *
     * @var boolean
     */
    protected $cloudTeamDriveCategory;
    /**
     * Dropbox category instance
     *
     * @var boolean
     */
    protected $dropboxCategory;
    /**
     * Onedrive category instance
     *
     * @var boolean
     */
    protected $onedriveCategory;

    /**
     * Onedrive Business category instance
     *
     * @var boolean
     */
    protected $onedriveBusinessCategory;

    /**
     * AWS category instance
     *
     * @var boolean
     */
    protected $awsCategory;

    /**
     * Nextcloud category instance
     *
     * @var boolean
     */
    protected $nextcloudCategory;

    /**
     * WpfdAddonControllerCategory constructor.
     */
    public function __construct()
    {
        Application::getInstance('WpfdAddon');
        $this->cloudCategory = Model::getInstance('cloudcategory');
        $this->cloudTeamDriveCategory = Model::getInstance('cloudteamdrivecategory');
        $this->dropboxCategory = Model::getInstance('dropboxcategory');
        Application::getInstance('WpfdAddon');
        $this->onedriveCategory = Model::getInstance('onedrivecategory');
        Application::getInstance('WpfdAddon');
        $this->onedriveBusinessCategory = Model::getInstance('onedrivebusinesscategory');
        $this->awsCategory = Model::getInstance('awscategory');
        $this->nextcloudCategory = Model::getInstance('nextcloudcategory');
    }

    /**
     * Add category
     *
     * @return void
     */
    public function addCategory()
    {
        $categoryName =  Utilities::getInput('name', 'POST', 'string');
        if (is_null($categoryName) || empty($categoryName)) {
            $categoryName = esc_html__('New Google Drive', 'wpfdAddon');
        }
        $parentId = Utilities::getInput('parentId', 'POST', 'int');
        if (is_null($parentId)) {
            $parentId = 0;
        }
        $results = $this->createGoogleDriveCategory($categoryName, $parentId);

        if (is_array($results)) {
            $this->exitStatus(true, $results);
        }

        $this->exitStatus(esc_html__('Error while creating category', 'wpfdAddon'));
    }

    /**
     * Add category
     *
     * @return void
     */
    public function addGoogleTeamDriveCategory()
    {
        $categoryName =  Utilities::getInput('name', 'POST', 'string');
        if (is_null($categoryName) || empty($categoryName)) {
            $categoryName = esc_html__('New Google Team Drive', 'wpfdAddon');
        }
        $parentId = Utilities::getInput('parentId', 'POST', 'int');
        if (is_null($parentId)) {
            $parentId = 0;
        }
        $results = $this->createGoogleTeamDriveCategory($categoryName, $parentId);

        if (is_array($results)) {
            $this->exitStatus(true, $results);
        }

        $this->exitStatus(esc_html__('Error while creating category', 'wpfdAddon'));
    }


    /**
     * Delete category
     *
     * @return void
     */
    public function deleteCategory()
    {
        $category = Utilities::getInt('id_category');
        $children = $this->cloudCategory->getChildren($category);
        if ($this->cloudCategory->deleteCategory($category)) {
            $children[] = $category;
            foreach ($children as $child) {
                $dir = WpfdBase::getFilesPath($child);
                Filesystem::rmdir($dir);
                if ((int) $child === $category) {
                    continue;
                }
                $this->cloudCategory->deleteCategory($child);
            }
            $this->exitStatus(true);
        }
        $this->exitStatus('error while deletting category');
    }

    /**
     * Delete google team drive category
     *
     * @return void
     */
    public function deleteGoogleTeamDriveCategory()
    {
        $category = Utilities::getInt('id_category');
        $children = $this->cloudTeamDriveCategory->getChildren($category);
        if ($this->cloudTeamDriveCategory->deleteCategory($category)) {
            $children[] = $category;
            foreach ($children as $child) {
                $dir = WpfdBase::getFilesPath($child);
                Filesystem::rmdir($dir);
                if ((int) $child === $category) {
                    continue;
                }
                $this->cloudTeamDriveCategory->deleteCategory($child);
            }
            $this->exitStatus(true);
        }
        $this->exitStatus('Error while deleting category');
    }

    /**
     * Create drop category
     *
     * @return void
     */
    public function addDropCategory()
    {
        $categoryName =  Utilities::getInput('name', 'POST', 'string');
        if (is_null($categoryName) || empty($categoryName)) {
            $categoryName = esc_html__('New Dropbox', 'wpfdAddon');
        }
        $parentId = Utilities::getInput('parentId', 'POST', 'int');
        if (is_null($parentId)) {
            $parentId = 0;
        }
        $results = $this->createDropboxCategory($categoryName, $parentId);

        if (is_array($results)) {
            $this->exitStatus(true, $results);
        }
        $this->exitStatus(esc_html__('Error while creating category', 'wpfdAddon'));
    }

    /**
     * Delete dropbox category
     *
     * @return   void
     * @internal param dropbox $delete
     * @internal param array $datas
     */
    public function deleteDropboxCategory()
    {
        $category = Utilities::getInt('id_category');
        $this->dropboxCategory->deleteDropboxCategory($category);
        $this->exitStatus(true);
    }

    /**
     * Retun status
     *
     * @param mixed $status Status to response
     * @param array $datas  Data include to response
     *
     * @return void
     */
    protected function exitStatus($status = '', $datas = array())
    {
        $response = array('response' => $status, 'datas' => $datas);
        echo json_encode($response);
        die();
    }

    /**
     * Create onedrive category
     *
     * @return void
     */
    public function addOneDriveCategory()
    {
        $categoryName =  Utilities::getInput('name', 'POST', 'string');
        if (is_null($categoryName) || empty($categoryName)) {
            $categoryName = esc_html__('New OneDrive', 'wpfdAddon');
        }
        $parentId = Utilities::getInput('parentId', 'POST', 'int');
        if (is_null($parentId)) {
            $parentId = 0;
        }
        $results = $this->createOnedriveCategory($categoryName, $parentId);

        if (is_array($results)) {
            $this->exitStatus(true, $results);
        }

        $this->exitStatus(esc_html__('Error while adding OneDrive Category', 'wpfdAddon'));
    }

    /**
     * Delete onedrive category
     *
     * @return   void
     * @internal param OneDrive $delete
     * @internal param array $datas
     */
    public function deleteOneDriveCategory()
    {
        $category = Utilities::getInt('id_category');
        $children = $this->onedriveCategory->getChildren($category);
        if ($this->onedriveCategory->deleteOneDriveCategory($category)) {
            $children[] = $category;
            foreach ($children as $child) {
                $dir = WpfdBase::getFilesPath($child);
                Filesystem::rmdir($dir);
                if ((int) $child === $category) {
                    continue;
                }
                $this->onedriveCategory->deleteOneDriveCategory($child);
            }
            $this->exitStatus(true);
        }
        $this->exitStatus('error while deletting category');
    }

    /**
     * Create onedrive business category
     *
     * @return void
     */
    public function addOneDriveBusinessCategory()
    {
        $categoryName =  Utilities::getInput('name', 'POST', 'string');
        if (is_null($categoryName) || empty($categoryName)) {
            $categoryName = esc_html__('New OneDrive Business', 'wpfdAddon');
        }
        $parentId = Utilities::getInput('parentId', 'POST', 'int');
        if (is_null($parentId)) {
            $parentId = 0;
        }
        $results = $this->createOnedriveBusinessCategory($categoryName, $parentId);

        if (is_array($results)) {
            $this->exitStatus(true, $results);
        }

        $this->exitStatus(esc_html__('Error while adding OneDrive Business Category', 'wpfdAddon'));
    }

    /**
     * Delete onedrive Business category
     *
     * @return   void
     * @internal param OneDrive $delete
     * @internal param array $datas
     */
    public function deleteOneDriveBusinessCategory()
    {
        $category = Utilities::getInt('id_category');
        $children = $this->onedriveBusinessCategory->getChildren($category);
        if ($this->onedriveBusinessCategory->deleteOneDriveBusinessCategory($category)) {
            $children[] = $category;
            foreach ($children as $child) {
                $dir = WpfdBase::getFilesPath($child);
                Filesystem::rmdir($dir);
                if ((int) $child === $category) {
                    continue;
                }
                $this->onedriveBusinessCategory->deleteOneDriveBusinessCategory($child);
            }
            $this->exitStatus(true);
        }
        $this->exitStatus('error while deleting category');
    }

    /**
     * Add Google Drive Category
     *
     * @param string  $categoryName New category name
     * @param integer $parentId     New category parent term id
     *
     * @return array|boolean
     */
    public function createGoogleDriveCategory(string $categoryName, int $parentId) // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewParamTypeDeclarations.stringFound, PHPCompatibility.FunctionDeclarations.NewParamTypeDeclarations.intFound -- Type params only
    {
        $categoryName = $this->getNewCategoryName($categoryName, $parentId);
        $id = $this->cloudCategory->addCategory($categoryName, $parentId, get_new_category_position());
        if ($id) {
            assign_user_categories($id);

            return array('id_category' => $id, 'name' => $categoryName);
        }

        return false;
    }

    /**
     * Add Google Team Drive Category
     *
     * @param string  $categoryName New category name
     * @param integer $parentId     New category parent term id
     *
     * @return array|boolean
     */
    public function createGoogleTeamDriveCategory(string $categoryName, int $parentId) // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewParamTypeDeclarations.stringFound, PHPCompatibility.FunctionDeclarations.NewParamTypeDeclarations.intFound -- Type params only
    {
        $categoryName = $this->getNewCategoryName($categoryName, $parentId);
        $id = $this->cloudTeamDriveCategory->addCategory($categoryName, $parentId, get_new_category_position());
        if ($id) {
            assign_user_categories($id);

            return array('id_category' => $id, 'name' => $categoryName);
        }

        return false;
    }

    /**
     * Create dropbox category
     *
     * @param string  $categoryName Category name
     * @param integer $parentId     Category parent term id
     *
     * @return array|boolean
     */
    public function createDropboxCategory($categoryName, $parentId = 0)
    {
        $categoryName = $this->getNewCategoryName($categoryName, $parentId);
        $id = $this->dropboxCategory->addDropCategory($categoryName, $parentId, get_new_category_position());
        if ($id) {
            assign_user_categories($id);

            return array('id_category' => $id, 'name' => $categoryName);
        }

        return false;
    }

    /**
     * Create onedrive category
     *
     * @param string  $categoryName Category name
     * @param integer $parentId     Category parent term id
     *
     * @return array|boolean
     */
    public function createOnedriveCategory($categoryName, $parentId = 0)
    {
        $categoryName = $this->getNewCategoryName($categoryName, $parentId);
        $return = $this->onedriveCategory->addOneDriveCategory($categoryName, $parentId, get_new_category_position());

        if (is_array($return) && isset($return['success']) && $return['success']) {
            assign_user_categories($return['id']);

            return array('id_category' => $return['id'], 'name' => $categoryName);
        }

        return false;
    }

    /**
     * Create onedrive business category
     *
     * @param string  $categoryName Category name
     * @param integer $parentId     Category parent term id
     *
     * @return array|boolean
     */
    public function createOnedriveBusinessCategory($categoryName, $parentId)
    {
        $categoryName = $this->getNewCategoryName($categoryName, $parentId);
        $return = $this->onedriveBusinessCategory->addCategory($categoryName, $parentId, get_new_category_position());

        if (is_array($return) && isset($return['success']) && $return['success']) {
            assign_user_categories($return['id']);

            return array('id_category' => $return['id'], 'name' => $categoryName);
        }

        return false;
    }

    /**
     * Create AWS category
     *
     * @return void
     */
    public function addAwsCategory()
    {
        $categoryName =  Utilities::getInput('name', 'POST', 'string');
        if (is_null($categoryName) || empty($categoryName)) {
            $categoryName = esc_html__('New Amazon S3 Category', 'wpfdAddon');
        }
        $parentId = Utilities::getInput('parentId', 'POST', 'int');
        if (is_null($parentId)) {
            $parentId = 0;
        }
        $results = $this->createAwsCategory($categoryName, $parentId);

        if (is_array($results)) {
            $this->exitStatus(true, $results);
        }
        $this->exitStatus(esc_html__('Error while creating category', 'wpfdAddon'));
    }

    /**
     * Delete AWS category
     *
     * @return   void
     * @internal param AWS $delete
     * @internal param array $datas
     */
    public function deleteAwsCategory()
    {
        $category = Utilities::getInt('id_category');
        $this->awsCategory->deleteAwsCategory($category);
        $this->exitStatus(true);
    }

    /**
     * Create AWS category
     *
     * @param string  $categoryName Category name
     * @param integer $parentId     Category parent term id
     *
     * @return array|boolean
     */
    public function createAwsCategory($categoryName, $parentId = 0)
    {
        $categoryName = $this->getNewCategoryName($categoryName, $parentId);
        $id = $this->awsCategory->addAwsCategory($categoryName, $parentId, get_new_category_position());
        if ($id) {
            assign_user_categories($id);

            return array('id_category' => $id, 'name' => $categoryName);
        }

        return false;
    }

    /**
     * Update category title
     *
     * @param integer $category Term id
     * @param string  $title    Title
     *
     * @return boolean
     */
    public function updateTitle($category, $title)
    {
        $result = wp_update_term($category, 'wpfd-category', array(
            'name' => $title,
            'slug' => sanitize_title($title),
        ));
        if (is_wp_error($result)) { //try again with other slug
            $result = wp_update_term($category, 'wpfd-category', array(
                'name' => $title,
                'slug' => sanitize_title($title) . '-' . time(),
            ));
        }
        if (is_wp_error($result)) {
            return false;
        }

        return true;
    }

    /**
     * Check new category name and span it with number
     *
     * @param string  $categoryName New category name
     * @param integer $parentId     Parent Id
     *
     * @return string
     */
    public function getNewCategoryName($categoryName, $parentId = 0)
    {
        // Check term exists
        $termSpan = 0;
        $checkTitle = $categoryName;
        if (function_exists('term_exists')) {
            while (is_array(term_exists($checkTitle, 'wpfd-category', $parentId))) {
                $termSpan++;
                $checkTitle = $categoryName . ' ' . (string) $termSpan;
            }
        }
        if ($termSpan > 0) {
            $categoryName .= ' ' . (string) $termSpan;
        }

        return $categoryName;
    }

    /**
     * Duplicate google category structure
     *
     * @return void
     */
    public function duplicateCategory()
    {
        $categoryId = Utilities::getInt('id_category');
        $title      = Utilities::getInput('title_category', 'GET', 'string');

        if (!$categoryId || !$title || $title === '') {
            exit();
        }

        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        $category      = $categoryModel->getCategory($categoryId);
        $parent        = isset($category->parent) ? $category->parent : 0;
        $subCategories = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $categoryId . '&number=1');
        $lastCats      = get_terms('wpfd-category', 'orderby=term_group&order=DESC&hierarchical=0&hide_empty=0&parent=' . $parent . '&number=1');
        $params        = isset($category->description) ? (array) json_decode($category->description) : array();
        $term_meta     = get_option('taxonomy_' . $categoryId);
        $categoryDesc  = get_term_meta($categoryId, '_wpfd_description', true);
        $categoryColor = get_term_meta($categoryId, '_wpfd_color', true);
        $categoryName  = $this->getNewCategoryName($title, $parent);
        $newInsertedId = $this->cloudCategory->addCategory($categoryName, $parent, 'end');

        if ($newInsertedId) {
            $user_id = get_current_user_id();
            if ($user_id) {
                $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                if (is_array($user_categories)) {
                    if (!in_array($newInsertedId, $user_categories)) {
                        $user_categories[] = $newInsertedId;
                    }
                } else {
                    $user_categories = array($newInsertedId);
                }
                update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
            }

            // Save params
            if (!empty($params)) {
                $categoryModel->saveParams((int) $newInsertedId, $params);
                if (is_array($lastCats) && count($lastCats)) {
                    $categoryModel->updateTermOrder((int) $newInsertedId, $lastCats[0]->term_group + 1);
                }
            }

            // Save category description
            if ($categoryDesc && $categoryDesc !== '') {
                $categoryModel->saveDescription((int) $newInsertedId, $categoryDesc);
            }

            // Save category custom color
            if ($categoryColor && $categoryColor !== '') {
                $custom_color = $categoryModel->saveColor((int) $newInsertedId, $categoryColor);
                if (is_array($custom_color)) {
                    /**
                     * Update category color
                     *
                     * @param integer Term id to change color
                     * @param string  New category color
                     */
                    do_action('wpfd_update_category_color', $newInsertedId, $categoryColor);
                }
            }

            // Update term meta
            if (!empty($term_meta)) {
                update_option('taxonomy_' . $newInsertedId, $term_meta);
            }

            // Duplicate sub google drive categories
            if (!empty($subCategories)) {
                $this->duplicateSubGoogleCategories($newInsertedId, $subCategories);
            }

            wp_send_json(array( 'success' => true, 'data' => array( 'title' => $categoryName, 'id' => $newInsertedId ) ));
        } else {
            wp_send_json(array( 'success' => false, 'data' => array() ));
        }
    }

    /**
     * Duplicate google team drive category structure
     *
     * @return void
     */
    public function duplicateGoogleTeamDriveCategory()
    {
        $categoryId = Utilities::getInt('id_category');
        $title      = Utilities::getInput('title_category', 'GET', 'string');

        if (!$categoryId || !$title || $title === '') {
            exit();
        }

        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        $category      = $categoryModel->getCategory($categoryId);
        $parent        = isset($category->parent) ? $category->parent : 0;
        $subCategories = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $categoryId . '&number=1');
        $lastCats      = get_terms('wpfd-category', 'orderby=term_group&order=DESC&hierarchical=0&hide_empty=0&parent=' . $parent . '&number=1');
        $params        = isset($category->description) ? (array) json_decode($category->description) : array();
        $term_meta     = get_option('taxonomy_' . $categoryId);
        $categoryDesc  = get_term_meta($categoryId, '_wpfd_description', true);
        $categoryColor = get_term_meta($categoryId, '_wpfd_color', true);
        $categoryName  = $this->getNewCategoryName($title, $parent);
        $newInsertedId = $this->cloudTeamDriveCategory->addCategory($categoryName, $parent, 'end');

        if ($newInsertedId) {
            $user_id = get_current_user_id();
            if ($user_id) {
                $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                if (is_array($user_categories)) {
                    if (!in_array($newInsertedId, $user_categories)) {
                        $user_categories[] = $newInsertedId;
                    }
                } else {
                    $user_categories = array($newInsertedId);
                }
                update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
            }

            // Save params
            if (!empty($params)) {
                $categoryModel->saveParams((int) $newInsertedId, $params);
                if (is_array($lastCats) && count($lastCats)) {
                    $categoryModel->updateTermOrder((int) $newInsertedId, $lastCats[0]->term_group + 1);
                }
            }

            // Save category description
            if ($categoryDesc && $categoryDesc !== '') {
                $categoryModel->saveDescription((int) $newInsertedId, $categoryDesc);
            }

            // Save category custom color
            if ($categoryColor && $categoryColor !== '') {
                $custom_color = $categoryModel->saveColor((int) $newInsertedId, $categoryColor);
                if (is_array($custom_color)) {
                    /**
                     * Update category color
                     *
                     * @param integer Term id to change color
                     * @param string  New category color
                     */
                    do_action('wpfd_update_category_color', $newInsertedId, $categoryColor);
                }
            }

            // Update term meta
            if (!empty($term_meta)) {
                update_option('taxonomy_' . $newInsertedId, $term_meta);
            }

            // Duplicate sub google team drive categories
            if (!empty($subCategories)) {
                $this->duplicateSubGoogleTeamDriveCategories($newInsertedId, $subCategories);
            }

            wp_send_json(array( 'success' => true, 'data' => array( 'title' => $categoryName, 'id' => $newInsertedId ) ));
        } else {
            wp_send_json(array( 'success' => false, 'data' => array() ));
        }
    }

    /**
     * Duplicate all sub google categories
     *
     * @param integer $categoryId    Category id
     * @param array   $subCategories Sub categories
     *
     * @return void
     */
    public function duplicateSubGoogleCategories($categoryId, $subCategories = array())
    {
        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        $missDuplicateCategories = array();
        foreach ($subCategories as $category) {
            $id            = $category->term_id;
            $title         = $category->name;
            $parent        = $categoryId;
            $subCate       = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $id . '&number=1');
            $params        = isset($category->description) ? (array) json_decode($category->description) : array();
            $term_meta     = get_option('taxonomy_' . $id);
            $categoryDesc  = get_term_meta($id, '_wpfd_description', true);
            $categoryColor = get_term_meta($id, '_wpfd_color', true);
            $categoryName  = $this->getNewCategoryName($title, $parent);
            $newId         = $this->cloudCategory->addCategory($categoryName, $parent, 'end');

            if ($newId) {
                $user_id = get_current_user_id();
                if ($user_id) {
                    $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                    if (is_array($user_categories)) {
                        if (!in_array($newId, $user_categories)) {
                            $user_categories[] = $newId;
                        }
                    } else {
                        $user_categories = array($newId);
                    }
                    update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                }

                // Save params
                if (!empty($params)) {
                    $categoryModel->saveParams((int) $newId, $params);
                }

                // Save category description
                if ($categoryDesc && $categoryDesc !== '') {
                    $categoryModel->saveDescription((int) $newId, $categoryDesc);
                }

                // Save category custom color
                if ($categoryColor && $categoryColor !== '') {
                    $custom_color = $categoryModel->saveColor((int) $newId, $categoryColor);
                    if (is_array($custom_color)) {
                        /**
                         * Update category color
                         *
                         * @param integer Term id to change color
                         * @param string  New category color
                         */
                        do_action('wpfd_update_category_color', $newId, $categoryColor);
                    }
                }

                // Update term meta
                if (!empty($term_meta)) {
                    update_option('taxonomy_' . $newId, $term_meta);
                }

                // Duplicate sub google categories recursive
                if (!empty($subCate)) {
                    $this->duplicateSubGoogleCategories($newId, $subCate);
                }
            } else {
                $missDuplicateCategories[] = array(
                    'name' => $title,
                    'parent' => $parent,
                    'sub_categories' => $subCate
                );
            }
        }

        if (!empty($missDuplicateCategories)) {
            foreach ($missDuplicateCategories as $mCategory) {
                $mCategoryName = isset($mCategory['name']) ? $mCategory['name'] : '';
                $mParent = isset($mCategory['parent']) ? $mCategory['parent'] : 0;
                $mSubCategories = isset($mCategory['sub_categories']) ? $mCategory['sub_categories'] : array();
                $mCategoryName = $this->getNewCategoryName($mCategoryName, $mParent);
                $mNewId = $this->cloudCategory->addCategory($mCategoryName, $mParent, 'end');

                if ($mNewId) {
                    $mUserId = get_current_user_id();
                    if ($mUserId) {
                        $mUserCategories = get_user_meta($mUserId, 'wpfd_user_categories', true);
                        if (is_array($mUserCategories)) {
                            if (!in_array($mNewId, $mUserCategories)) {
                                $mUserCategories[] = $mNewId;
                            }
                        } else {
                            $mUserCategories = array($mNewId);
                        }
                        update_user_meta($mUserId, 'wpfd_user_categories', $mUserCategories);
                    }

                    // Duplicate sub google drive categories
                    if (!empty($mSubCategories)) {
                        $this->duplicateSubGoogleCategories($mNewId, $mSubCategories);
                    }
                }
            }
        }
    }

    /**
     * Duplicate all sub google team drive categories
     *
     * @param integer $categoryId    Category id
     * @param array   $subCategories Sub categories
     *
     * @return void
     */
    public function duplicateSubGoogleTeamDriveCategories($categoryId, $subCategories = array())
    {
        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        $missDuplicateCategories = array();
        foreach ($subCategories as $category) {
            $id            = $category->term_id;
            $title         = $category->name;
            $parent        = $categoryId;
            $subCate       = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $id . '&number=1');
            $params        = isset($category->description) ? (array) json_decode($category->description) : array();
            $term_meta     = get_option('taxonomy_' . $id);
            $categoryDesc  = get_term_meta($id, '_wpfd_description', true);
            $categoryColor = get_term_meta($id, '_wpfd_color', true);
            $categoryName  = $this->getNewCategoryName($title, $parent);
            $newId         = $this->cloudTeamDriveCategory->addCategory($categoryName, $parent, 'end');

            if ($newId) {
                $user_id = get_current_user_id();
                if ($user_id) {
                    $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                    if (is_array($user_categories)) {
                        if (!in_array($newId, $user_categories)) {
                            $user_categories[] = $newId;
                        }
                    } else {
                        $user_categories = array($newId);
                    }
                    update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                }

                // Save params
                if (!empty($params)) {
                    $categoryModel->saveParams((int) $newId, $params);
                }

                // Save category description
                if ($categoryDesc && $categoryDesc !== '') {
                    $categoryModel->saveDescription((int) $newId, $categoryDesc);
                }

                // Save category custom color
                if ($categoryColor && $categoryColor !== '') {
                    $custom_color = $categoryModel->saveColor((int) $newId, $categoryColor);
                    if (is_array($custom_color)) {
                        /**
                         * Update category color
                         *
                         * @param integer Term id to change color
                         * @param string  New category color
                         */
                        do_action('wpfd_update_category_color', $newId, $categoryColor);
                    }
                }

                // Update term meta
                if (!empty($term_meta)) {
                    update_option('taxonomy_' . $newId, $term_meta);
                }

                // Duplicate sub google team categories recursive
                if (!empty($subCate)) {
                    $this->duplicateSubGoogleTeamDriveCategories($newId, $subCate);
                }
            } else {
                $missDuplicateCategories[] = array(
                    'name' => $title,
                    'parent' => $parent,
                    'sub_categories' => $subCate
                );
            }
        }

        if (!empty($missDuplicateCategories)) {
            foreach ($missDuplicateCategories as $mCategory) {
                $mCategoryName = isset($mCategory['name']) ? $mCategory['name'] : '';
                $mParent = isset($mCategory['parent']) ? $mCategory['parent'] : 0;
                $mSubCategories = isset($mCategory['sub_categories']) ? $mCategory['sub_categories'] : array();
                $mCategoryName = $this->getNewCategoryName($mCategoryName, $mParent);
                $mNewId = $this->cloudTeamDriveCategory->addCategory($mCategoryName, $mParent, 'end');

                if ($mNewId) {
                    $mUserId = get_current_user_id();
                    if ($mUserId) {
                        $mUserCategories = get_user_meta($mUserId, 'wpfd_user_categories', true);
                        if (is_array($mUserCategories)) {
                            if (!in_array($mNewId, $mUserCategories)) {
                                $mUserCategories[] = $mNewId;
                            }
                        } else {
                            $mUserCategories = array($mNewId);
                        }
                        update_user_meta($mUserId, 'wpfd_user_categories', $mUserCategories);
                    }

                    // Duplicate sub google team drive categories
                    if (!empty($mSubCategories)) {
                        $this->duplicateSubGoogleTeamDriveCategories($mNewId, $mSubCategories);
                    }
                }
            }
        }
    }

    /**
     * Duplicate onedrive category structure
     *
     * @return void
     */
    public function duplicateOneDriveCategory()
    {
        $categoryId = Utilities::getInt('id_category');
        $title      = Utilities::getInput('title_category', 'GET', 'string');

        if (!$categoryId || !$title || $title === '') {
            exit();
        }

        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        $category      = $categoryModel->getCategory($categoryId);
        $parent        = isset($category->parent) ? $category->parent : 0;
        $subCategories = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $categoryId . '&number=1');
        $lastCats      = get_terms('wpfd-category', 'orderby=term_group&order=DESC&hierarchical=0&hide_empty=0&parent=' . $parent . '&number=1');
        $params        = isset($category->description) ? (array) json_decode($category->description) : array();
        $term_meta     = get_option('taxonomy_' . $categoryId);
        $categoryDesc  = get_term_meta($categoryId, '_wpfd_description', true);
        $categoryColor = get_term_meta($categoryId, '_wpfd_color', true);
        $categoryName  = $this->getNewCategoryName($title, $parent);
        $newInsertedId = $this->onedriveCategory->addOneDriveCategory($categoryName, $parent, 'end');

        if (is_array($newInsertedId)) {
            if ($newInsertedId['success']) {
                $user_id = get_current_user_id();
                if ($user_id) {
                    $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                    if (is_array($user_categories)) {
                        if (!in_array($newInsertedId['id'], $user_categories)) {
                            $user_categories[] = $newInsertedId['id'];
                        }
                    } else {
                        $user_categories = array($newInsertedId['id']);
                    }
                    update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                }

                // Save params
                if (!empty($params)) {
                    $categoryModel->saveParams((int) $newInsertedId['id'], $params);
                    if (is_array($lastCats) && count($lastCats)) {
                        $categoryModel->updateTermOrder((int) $newInsertedId['id'], $lastCats[0]->term_group + 1);
                    }
                }

                // Save category description
                if ($categoryDesc && $categoryDesc !== '') {
                    $categoryModel->saveDescription((int) $newInsertedId['id'], $categoryDesc);
                }

                // Save category custom color
                if ($categoryColor && $categoryColor !== '') {
                    $custom_color = $categoryModel->saveColor((int) $newInsertedId['id'], $categoryColor);
                    if (is_array($custom_color)) {
                        /**
                         * Update category color
                         *
                         * @param integer Term id to change color
                         * @param string  New category color
                         */
                        do_action('wpfd_update_category_color', $newInsertedId['id'], $categoryColor);
                    }
                }

                // Update term meta
                if (!empty($term_meta)) {
                    update_option('taxonomy_' . $newInsertedId['id'], $term_meta);
                }

                // Duplicate sub onedrive categories
                if (isset($newInsertedId['id']) && !empty($subCategories)) {
                    $this->duplicateSubOnedriveCategories($newInsertedId['id'], $subCategories);
                }

                wp_send_json(array( 'success' => true, 'data' => array( 'title' => $categoryName, 'id' => $newInsertedId['id'] ) ));
            } else {
                wp_send_json(array( 'success' => false, 'data' => array() ));
            }
        } else {
            wp_send_json(array( 'success' => false, 'data' => array() ));
        }
    }

    /**
     * Duplicate all sub onedrive categories
     *
     * @param integer $categoryId    Category id
     * @param array   $subCategories Sub categories
     *
     * @return void
     */
    public function duplicateSubOnedriveCategories($categoryId, $subCategories = array())
    {
        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        foreach ($subCategories as $category) {
            $id            = $category->term_id;
            $title         = $category->name;
            $parent        = $categoryId;
            $subCate       = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $id . '&number=1');
            $params        = isset($category->description) ? (array) json_decode($category->description) : array();
            $term_meta     = get_option('taxonomy_' . $id);
            $categoryDesc  = get_term_meta($id, '_wpfd_description', true);
            $categoryColor = get_term_meta($id, '_wpfd_color', true);
            $categoryName  = $this->getNewCategoryName($title, $parent);
            $newId         = $this->onedriveCategory->addOneDriveCategory($categoryName, $parent, 'end');

            if (is_array($newId)) {
                if ($newId['success']) {
                    $user_id = get_current_user_id();
                    if ($user_id) {
                        $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                        if (is_array($user_categories)) {
                            if (!in_array($newId['id'], $user_categories)) {
                                $user_categories[] = $newId['id'];
                            }
                        } else {
                            $user_categories = array($newId['id']);
                        }
                        update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                    }

                    // Save params
                    if (!empty($params)) {
                        $categoryModel->saveParams((int) $newId['id'], $params);
                    }

                    // Save category description
                    if ($categoryDesc && $categoryDesc !== '') {
                        $categoryModel->saveDescription((int) $newId['id'], $categoryDesc);
                    }

                    // Save category custom color
                    if ($categoryColor && $categoryColor !== '') {
                        $custom_color = $categoryModel->saveColor((int) $newId['id'], $categoryColor);
                        if (is_array($custom_color)) {
                            /**
                             * Update category color
                             *
                             * @param integer Term id to change color
                             * @param string  New category color
                             */
                            do_action('wpfd_update_category_color', $newId['id'], $categoryColor);
                        }
                    }

                    // Update term meta
                    if (!empty($term_meta)) {
                        update_option('taxonomy_' . $newId['id'], $term_meta);
                    }

                    // Duplicate sub onedrive categories recursive
                    if (isset($newId['id']) && !empty($subCate)) {
                        $this->duplicateSubOnedriveCategories($newId['id'], $subCate);
                    }
                }
            }
        }
    }

    /**
     * Duplicate onedrive business category structure
     *
     * @return void
     */
    public function duplicateOneDriveBusinessCategory()
    {
        $categoryId = Utilities::getInt('id_category');
        $title      = Utilities::getInput('title_category', 'GET', 'string');

        if (!$categoryId || !$title || $title === '') {
            exit();
        }

        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        $category      = $categoryModel->getCategory($categoryId);
        $parent        = isset($category->parent) ? $category->parent : 0;
        $subCategories = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $categoryId . '&number=1');
        $lastCats      = get_terms('wpfd-category', 'orderby=term_group&order=DESC&hierarchical=0&hide_empty=0&parent=' . $parent . '&number=1');
        $params        = isset($category->description) ? (array) json_decode($category->description) : array();
        $term_meta     = get_option('taxonomy_' . $categoryId);
        $categoryDesc  = get_term_meta($categoryId, '_wpfd_description', true);
        $categoryColor = get_term_meta($categoryId, '_wpfd_color', true);
        $categoryName  = $this->getNewCategoryName($title, $parent);
        $newInsertedId = $this->onedriveBusinessCategory->addCategory($categoryName, $parent, 'end');

        if (is_array($newInsertedId)) {
            if ($newInsertedId['success']) {
                $user_id = get_current_user_id();
                if ($user_id) {
                    $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                    if (is_array($user_categories)) {
                        if (!in_array($newInsertedId['id'], $user_categories)) {
                            $user_categories[] = $newInsertedId['id'];
                        }
                    } else {
                        $user_categories = array($newInsertedId['id']);
                    }
                    update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                }

                // Save params
                if (!empty($params)) {
                    $categoryModel->saveParams((int) $newInsertedId['id'], $params);
                    if (is_array($lastCats) && count($lastCats)) {
                        $categoryModel->updateTermOrder((int) $newInsertedId['id'], $lastCats[0]->term_group + 1);
                    }
                }

                // Save category description
                if ($categoryDesc && $categoryDesc !== '') {
                    $categoryModel->saveDescription((int) $newInsertedId['id'], $categoryDesc);
                }

                // Save category custom color
                if ($categoryColor && $categoryColor !== '') {
                    $custom_color = $categoryModel->saveColor((int) $newInsertedId['id'], $categoryColor);
                    if (is_array($custom_color)) {
                        /**
                         * Update category color
                         *
                         * @param integer Term id to change color
                         * @param string  New category color
                         */
                        do_action('wpfd_update_category_color', $newInsertedId['id'], $categoryColor);
                    }
                }

                // Update term meta
                if (!empty($term_meta)) {
                    update_option('taxonomy_' . $newInsertedId['id'], $term_meta);
                }

                // Duplicate sub onedrive business categories
                if (isset($newInsertedId['id']) && !empty($subCategories)) {
                    $this->duplicateSubOnedriveBusinessCategories($newInsertedId['id'], $subCategories);
                }

                wp_send_json(array( 'success' => true, 'data' => array( 'title' => $categoryName, 'id' => $newInsertedId['id'] ) ));
            } else {
                wp_send_json(array( 'success' => false, 'data' => array() ));
            }
        } else {
            wp_send_json(array( 'success' => false, 'data' => array() ));
        }
    }

    /**
     * Duplicate all sub onedrive business categories
     *
     * @param integer $categoryId    Category id
     * @param array   $subCategories Sub categories
     *
     * @return void
     */
    public function duplicateSubOnedriveBusinessCategories($categoryId, $subCategories = array())
    {
        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        foreach ($subCategories as $category) {
            $id            = $category->term_id;
            $title         = $category->name;
            $parent        = $categoryId;
            $subCate       = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $id . '&number=1');
            $params        = isset($category->description) ? (array) json_decode($category->description) : array();
            $term_meta     = get_option('taxonomy_' . $id);
            $categoryDesc  = get_term_meta($id, '_wpfd_description', true);
            $categoryColor = get_term_meta($id, '_wpfd_color', true);
            $categoryName  = $this->getNewCategoryName($title, $parent);
            $newId         = $this->onedriveBusinessCategory->addCategory($categoryName, $parent, 'end');

            if (is_array($newId)) {
                if ($newId['success']) {
                    $user_id = get_current_user_id();
                    if ($user_id) {
                        $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                        if (is_array($user_categories)) {
                            if (!in_array($newId['id'], $user_categories)) {
                                $user_categories[] = $newId['id'];
                            }
                        } else {
                            $user_categories = array($newId['id']);
                        }
                        update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                    }

                    // Save params
                    if (!empty($params)) {
                        $categoryModel->saveParams((int) $newId['id'], $params);
                    }

                    // Save category description
                    if ($categoryDesc && $categoryDesc !== '') {
                        $categoryModel->saveDescription((int) $newId['id'], $categoryDesc);
                    }

                    // Save category custom color
                    if ($categoryColor && $categoryColor !== '') {
                        $custom_color = $categoryModel->saveColor((int) $newId['id'], $categoryColor);
                        if (is_array($custom_color)) {
                            /**
                             * Update category color
                             *
                             * @param integer Term id to change color
                             * @param string  New category color
                             */
                            do_action('wpfd_update_category_color', $newId['id'], $categoryColor);
                        }
                    }

                    // Update term meta
                    if (!empty($term_meta)) {
                        update_option('taxonomy_' . $newId['id'], $term_meta);
                    }

                    // Duplicate sub onedrive business categories recursive
                    if (isset($newId['id']) && !empty($subCategories)) {
                        $this->duplicateSubOnedriveBusinessCategories($newId['id'], $subCate);
                    }
                }
            }
        }
    }

    /**
     * Duplicate dropbox category structure
     *
     * @return void
     */
    public function duplicateDropboxCategory()
    {
        $categoryId = Utilities::getInt('id_category');
        $title      = Utilities::getInput('title_category', 'GET', 'string');

        if (!$categoryId || !$title || $title === '') {
            exit();
        }

        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        $category      = $categoryModel->getCategory($categoryId);
        $parent        = isset($category->parent) ? $category->parent : 0;
        $subCategories = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $categoryId . '&number=1');
        $lastCats      = get_terms('wpfd-category', 'orderby=term_group&order=DESC&hierarchical=0&hide_empty=0&parent=' . $parent . '&number=1');
        $params        = isset($category->description) ? (array) json_decode($category->description) : array();
        $term_meta     = get_option('taxonomy_' . $categoryId);
        $categoryDesc  = get_term_meta($categoryId, '_wpfd_description', true);
        $categoryColor = get_term_meta($categoryId, '_wpfd_color', true);
        $categoryName  = $this->getNewCategoryName($title, $parent);
        $newInsertedId = $this->dropboxCategory->addDropCategory($categoryName, $parent, 'end');

        if ($newInsertedId) {
            $user_id = get_current_user_id();
            if ($user_id) {
                $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                if (is_array($user_categories)) {
                    if (!in_array($newInsertedId, $user_categories)) {
                        $user_categories[] = $newInsertedId;
                    }
                } else {
                    $user_categories = array($newInsertedId);
                }
                update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
            }

            // Save params
            if (!empty($params)) {
                $categoryModel->saveParams((int) $newInsertedId, $params);
                if (is_array($lastCats) && count($lastCats)) {
                    $categoryModel->updateTermOrder((int) $newInsertedId, $lastCats[0]->term_group + 1);
                }
            }

            // Save category description
            if ($categoryDesc && $categoryDesc !== '') {
                $categoryModel->saveDescription((int) $newInsertedId, $categoryDesc);
            }

            // Save category custom color
            if ($categoryColor && $categoryColor !== '') {
                $custom_color = $categoryModel->saveColor((int) $newInsertedId, $categoryColor);
                if (is_array($custom_color)) {
                    /**
                     * Update category color
                     *
                     * @param integer Term id to change color
                     * @param string  New category color
                     */
                    do_action('wpfd_update_category_color', $newInsertedId, $categoryColor);
                }
            }

            // Update term meta
            if (!empty($term_meta)) {
                update_option('taxonomy_' . $newInsertedId, $term_meta);
            }

            // Duplicate sub dropbox categories
            if (!empty($subCategories)) {
                $this->duplicateSubDropboxCategories($newInsertedId, $subCategories);
            }

            wp_send_json(array( 'success' => true, 'data' => array( 'title' => $categoryName, 'id' => $newInsertedId ) ));
        } else {
            wp_send_json(array( 'success' => false, 'data' => array() ));
        }
    }

    /**
     * Duplicate all sub dropbox categories
     *
     * @param integer $categoryId    Category id
     * @param array   $subCategories Sub categories
     *
     * @return void
     */
    public function duplicateSubDropboxCategories($categoryId, $subCategories = array())
    {
        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        foreach ($subCategories as $category) {
            $id            = $category->term_id;
            $title         = $category->name;
            $parent        = $categoryId;
            $subCate       = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $id . '&number=1');
            $params        = isset($category->description) ? (array) json_decode($category->description) : array();
            $term_meta     = get_option('taxonomy_' . $id);
            $categoryDesc  = get_term_meta($id, '_wpfd_description', true);
            $categoryColor = get_term_meta($id, '_wpfd_color', true);
            $categoryName  = $this->getNewCategoryName($title, $parent);
            $newId         = $this->dropboxCategory->addDropCategory($categoryName, $parent, 'end');

            if ($newId) {
                $user_id = get_current_user_id();
                if ($user_id) {
                    $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                    if (is_array($user_categories)) {
                        if (!in_array($newId, $user_categories)) {
                            $user_categories[] = $newId;
                        }
                    } else {
                        $user_categories = array($newId);
                    }
                    update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                }

                // Save params
                if (!empty($params)) {
                    $categoryModel->saveParams((int) $newId, $params);
                }

                // Save category description
                if ($categoryDesc && $categoryDesc !== '') {
                    $categoryModel->saveDescription((int) $newId, $categoryDesc);
                }

                // Save category custom color
                if ($categoryColor && $categoryColor !== '') {
                    $custom_color = $categoryModel->saveColor((int) $newId, $categoryColor);
                    if (is_array($custom_color)) {
                        /**
                         * Update category color
                         *
                         * @param integer Term id to change color
                         * @param string  New category color
                         */
                        do_action('wpfd_update_category_color', $newId, $categoryColor);
                    }
                }

                // Update term meta
                if (!empty($term_meta)) {
                    update_option('taxonomy_' . $newId, $term_meta);
                }

                // Duplicate sub dropbox categories recursive
                if (!empty($subCate)) {
                    $this->duplicateSubDropboxCategories($newId, $subCate);
                }
            }
        }
    }

    /**
     * Duplicate AWS category structure
     *
     * @return void
     */
    public function duplicateAwsCategory()
    {
        $categoryId = Utilities::getInt('id_category');
        $title      = Utilities::getInput('title_category', 'GET', 'string');

        if (!$categoryId || !$title || $title === '') {
            exit();
        }

        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        $category      = $categoryModel->getCategory($categoryId);
        $parent        = isset($category->parent) ? $category->parent : 0;
        $subCategories = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $categoryId . '&number=1');
        $lastCats      = get_terms('wpfd-category', 'orderby=term_group&order=DESC&hierarchical=0&hide_empty=0&parent=' . $parent . '&number=1');
        $params        = isset($category->description) ? (array) json_decode($category->description) : array();
        $term_meta     = get_option('taxonomy_' . $categoryId);
        $categoryDesc  = get_term_meta($categoryId, '_wpfd_description', true);
        $categoryColor = get_term_meta($categoryId, '_wpfd_color', true);
        $categoryName  = $this->getNewCategoryName($title, $parent);
        $newInsertedId = $this->awsCategory->addAwsCategory($categoryName, $parent, 'end');

        if ($newInsertedId) {
            $user_id = get_current_user_id();
            if ($user_id) {
                $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                if (is_array($user_categories)) {
                    if (!in_array($newInsertedId, $user_categories)) {
                        $user_categories[] = $newInsertedId;
                    }
                } else {
                    $user_categories = array($newInsertedId);
                }
                update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
            }

            // Save params
            if (!empty($params)) {
                $categoryModel->saveParams((int) $newInsertedId, $params);
                if (is_array($lastCats) && count($lastCats)) {
                    $categoryModel->updateTermOrder((int) $newInsertedId, $lastCats[0]->term_group + 1);
                }
            }

            // Save category description
            if ($categoryDesc && $categoryDesc !== '') {
                $categoryModel->saveDescription((int) $newInsertedId, $categoryDesc);
            }

            // Save category custom color
            if ($categoryColor && $categoryColor !== '') {
                $custom_color = $categoryModel->saveColor((int) $newInsertedId, $categoryColor);
                if (is_array($custom_color)) {
                    /**
                     * Update category color
                     *
                     * @param integer Term id to change color
                     * @param string  New category color
                     */
                    do_action('wpfd_update_category_color', $newInsertedId, $categoryColor);
                }
            }

            // Update term meta
            if (!empty($term_meta)) {
                update_option('taxonomy_' . $newInsertedId, $term_meta);
            }

            // Duplicate sub aws categories
            if (!empty($subCategories)) {
                $this->duplicateSubAwsCategories($newInsertedId, $subCategories);
            }

            wp_send_json(array( 'success' => true, 'data' => array( 'title' => $categoryName, 'id' => $newInsertedId ) ));
        } else {
            wp_send_json(array( 'success' => false, 'data' => array() ));
        }
    }

    /**
     * Duplicate all sub AWS categories
     *
     * @param integer $categoryId    Category id
     * @param array   $subCategories Sub categories
     *
     * @return void
     */
    public function duplicateSubAwsCategories($categoryId, $subCategories = array())
    {
        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        foreach ($subCategories as $category) {
            $id            = $category->term_id;
            $title         = $category->name;
            $parent        = $categoryId;
            $subCate       = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $id . '&number=1');
            $params        = isset($category->description) ? (array) json_decode($category->description) : array();
            $term_meta     = get_option('taxonomy_' . $id);
            $categoryDesc  = get_term_meta($id, '_wpfd_description', true);
            $categoryColor = get_term_meta($id, '_wpfd_color', true);
            $categoryName  = $this->getNewCategoryName($title, $parent);
            $newId         = $this->awsCategory->addAwsCategory($categoryName, $parent, 'end');

            if ($newId) {
                $user_id = get_current_user_id();
                if ($user_id) {
                    $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                    if (is_array($user_categories)) {
                        if (!in_array($newId, $user_categories)) {
                            $user_categories[] = $newId;
                        }
                    } else {
                        $user_categories = array($newId);
                    }
                    update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                }

                // Save params
                if (!empty($params)) {
                    $categoryModel->saveParams((int) $newId, $params);
                }

                // Save category description
                if ($categoryDesc && $categoryDesc !== '') {
                    $categoryModel->saveDescription((int) $newId, $categoryDesc);
                }

                // Save category custom color
                if ($categoryColor && $categoryColor !== '') {
                    $custom_color = $categoryModel->saveColor((int) $newId, $categoryColor);
                    if (is_array($custom_color)) {
                        /**
                         * Update category color
                         *
                         * @param integer Term id to change color
                         * @param string  New category color
                         */
                        do_action('wpfd_update_category_color', $newId, $categoryColor);
                    }
                }

                // Update term meta
                if (!empty($term_meta)) {
                    update_option('taxonomy_' . $newId, $term_meta);
                }

                // Duplicate sub aws categories recursive
                if (!empty($subCate)) {
                    $this->duplicateSubAwsCategories($newId, $subCate);
                }
            }
        }
    }

    /**
     * Create Nextcloud category
     *
     * @return void
     */
    public function addNextcloudCategory()
    {
        $categoryName =  Utilities::getInput('name', 'POST', 'string');
        if (is_null($categoryName) || empty($categoryName)) {
            $categoryName = esc_html__('New Nextcloud Category', 'wpfdAddon');
        }
        $parentId = Utilities::getInput('parentId', 'POST', 'int');
        if (is_null($parentId)) {
            $parentId = 0;
        }
        $results = $this->createNextcloudCategory($categoryName, $parentId);

        if (is_array($results)) {
            $this->exitStatus(true, $results);
        }
        $this->exitStatus(esc_html__('Error while creating category', 'wpfdAddon'));
    }

    /**
     * Create Nextcloud category
     *
     * @param string  $categoryName Category name
     * @param integer $parentId     Category parent term id
     *
     * @return array|boolean
     */
    public function createNextcloudCategory($categoryName, $parentId = 0)
    {
        $categoryName = $this->getNewCategoryName($categoryName, $parentId);
        $id = $this->nextcloudCategory->addNextcloudCategory($categoryName, $parentId, get_new_category_position());
        if ($id) {
            assign_user_categories($id);

            return array('id_category' => $id, 'name' => $categoryName);
        }

        return false;
    }

    /**
     * Delete Nextcloud category
     *
     * @return   void
     * @internal param Nextcloud $delete
     * @internal param array $datas
     */
    public function deleteNextcloudCategory()
    {
        $category = Utilities::getInt('id_category');
        $this->nextcloudCategory->deleteNextcloudCategory($category);
        $this->exitStatus(true);
    }

    /**
     * Duplicate Nextcloud category structure
     *
     * @return void
     */
    public function duplicateNextcloudCategory()
    {
        $categoryId = Utilities::getInt('id_category');
        $title      = Utilities::getInput('title_category', 'GET', 'string');

        if (!$categoryId || !$title || $title === '') {
            exit();
        }

        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        $category      = $categoryModel->getCategory($categoryId);
        $parent        = isset($category->parent) ? $category->parent : 0;
        $subCategories = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $categoryId . '&number=1');
        $lastCats      = get_terms('wpfd-category', 'orderby=term_group&order=DESC&hierarchical=0&hide_empty=0&parent=' . $parent . '&number=1');
        $params        = isset($category->description) ? (array) json_decode($category->description) : array();
        $term_meta     = get_option('taxonomy_' . $categoryId);
        $categoryDesc  = get_term_meta($categoryId, '_wpfd_description', true);
        $categoryColor = get_term_meta($categoryId, '_wpfd_color', true);
        $categoryName  = $this->getNewCategoryName($title, $parent);
        $newInsertedId = $this->nextcloudCategory->addNextcloudCategory($categoryName, $parent, 'end');

        if ($newInsertedId) {
            $user_id = get_current_user_id();
            if ($user_id) {
                $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                if (is_array($user_categories)) {
                    if (!in_array($newInsertedId, $user_categories)) {
                        $user_categories[] = $newInsertedId;
                    }
                } else {
                    $user_categories = array($newInsertedId);
                }
                update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
            }

            // Save params
            if (!empty($params)) {
                $categoryModel->saveParams((int) $newInsertedId, $params);
                if (is_array($lastCats) && count($lastCats)) {
                    $categoryModel->updateTermOrder((int) $newInsertedId, $lastCats[0]->term_group + 1);
                }
            }

            // Save category description
            if ($categoryDesc && $categoryDesc !== '') {
                $categoryModel->saveDescription((int) $newInsertedId, $categoryDesc);
            }

            // Save category custom color
            if ($categoryColor && $categoryColor !== '') {
                $custom_color = $categoryModel->saveColor((int) $newInsertedId, $categoryColor);
                if (is_array($custom_color)) {
                    /**
                     * Update category color
                     *
                     * @param integer Term id to change color
                     * @param string  New category color
                     */
                    do_action('wpfd_update_category_color', $newInsertedId, $categoryColor);
                }
            }

            // Update term meta
            if (!empty($term_meta)) {
                update_option('taxonomy_' . $newInsertedId, $term_meta);
            }

            // Duplicate sub Nextcloud categories
            if (!empty($subCategories)) {
                $this->duplicateSubNextcloudCategories($newInsertedId, $subCategories);
            }

            wp_send_json(array( 'success' => true, 'data' => array( 'title' => $categoryName, 'id' => $newInsertedId ) ));
        } else {
            wp_send_json(array( 'success' => false, 'data' => array() ));
        }
    }

    /**
     * Duplicate all sub Nextcloud categories
     *
     * @param integer $categoryId    Category id
     * @param array   $subCategories Sub categories
     *
     * @return void
     */
    public function duplicateSubNextcloudCategories($categoryId, $subCategories = array())
    {
        Application::getInstance('Wpfd');
        $categoryModel = Model::getInstance('category');
        foreach ($subCategories as $category) {
            $id            = $category->term_id;
            $title         = $category->name;
            $parent        = $categoryId;
            $subCate       = get_terms('wpfd-category', 'orderby=term_group&order=ASC&hierarchical=0&hide_empty=0&parent=' . $id . '&number=1');
            $params        = isset($category->description) ? (array) json_decode($category->description) : array();
            $term_meta     = get_option('taxonomy_' . $id);
            $categoryDesc  = get_term_meta($id, '_wpfd_description', true);
            $categoryColor = get_term_meta($id, '_wpfd_color', true);
            $categoryName  = $this->getNewCategoryName($title, $parent);
            $newId         = $this->nextcloudCategory->addNextcloudCategory($categoryName, $parent, 'end');

            if ($newId) {
                $user_id = get_current_user_id();
                if ($user_id) {
                    $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
                    if (is_array($user_categories)) {
                        if (!in_array($newId, $user_categories)) {
                            $user_categories[] = $newId;
                        }
                    } else {
                        $user_categories = array($newId);
                    }
                    update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
                }

                // Save params
                if (!empty($params)) {
                    $categoryModel->saveParams((int) $newId, $params);
                }

                // Save category description
                if ($categoryDesc && $categoryDesc !== '') {
                    $categoryModel->saveDescription((int) $newId, $categoryDesc);
                }

                // Save category custom color
                if ($categoryColor && $categoryColor !== '') {
                    $custom_color = $categoryModel->saveColor((int) $newId, $categoryColor);
                    if (is_array($custom_color)) {
                        /**
                         * Update category color
                         *
                         * @param integer Term id to change color
                         * @param string  New category color
                         */
                        do_action('wpfd_update_category_color', $newId, $categoryColor);
                    }
                }

                // Update term meta
                if (!empty($term_meta)) {
                    update_option('taxonomy_' . $newId, $term_meta);
                }

                // Duplicate sub Nextcloud categories recursive
                if (!empty($subCate)) {
                    $this->duplicateSubNextcloudCategories($newId, $subCate);
                }
            }
        }
    }
}
