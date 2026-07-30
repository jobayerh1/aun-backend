<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

// no direct access
defined('ABSPATH') || die();

use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Form;
use Joomunited\WPFramework\v1_0_6\Utilities;

/**
 * Class WpfdAddonWoocommerce
 */
class WpfdAddonWoocommerce
{
    const ONLY_WOO_PERMISSION = 'only_woo_permission';
    const BOTH_PERMISSION = 'both_woo_and_wpfd_permission';
    const STATISTICS_TYPE = 'wc_order';

    /**
     * Set to TRUE to enable debug mode
     *
     * @var boolean
     */
    public static $debug = false;

    /**
     * WpfdAddonWoocommerce constructor.
     */
    public function __construct()
    {
        if (defined('WPFD_DEBUG') && WPFD_DEBUG) {
            self::$debug = true;
        }

        if (!$this->isWoo()) {
            return false;
        }
        add_filter('wpfd_locate_template', array($this, 'wpfdaLocateTemplate'), 10, 2);

        if (is_admin()) {
            if (!class_exists('\WpfdAddonHelper')) {
                require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/helpers/WpfdHelper.php';
            }
            // Load scripts/styles
            add_action('admin_enqueue_scripts', array($this, 'addAdminScripts'));
            // Inject data tab
            add_filter('woocommerce_product_data_tabs', array($this, 'addProductTab'));
            // Inject data panel
            add_action('woocommerce_product_data_panels', array($this, 'addProductDataPanel'));
            // Save product action
            add_action('woocommerce_process_product_meta', array($this, 'wpfdaWooAddFilesSave'));
            // Show woocommerce icon in backend file list
            add_filter('wpfd_admin_files', array($this, 'wpfdaShowLinkedProduct')); // Table layout
            add_filter('wpfd_file_params', array($this, 'wpfdaShowLinkedProduct')); // Alternate layout
            // Add filter to check file has linked to a product
            add_filter('wpfd_addon_has_products', array($this, 'wpfdaFileHasProducts'), 10, 2);
            // Add filter to add product data to file row
            add_filter('wpfd_admin_file_row_data', array($this, 'wpfdaFileRowData'), 10, 2);
            // Add permission option to file fields
            add_action('wpfd_admin_after_file_title_field', array($this, 'wpfdaAddWocommercePermissionField'));
            // Save permission field
            add_filter('wpfd_before_save_file_metadata', array($this, 'wpfdaSaveWooPermissionField'), 10, 2);
            // Statistics Option
            add_filter('wpfd_statistics_filter_by', function ($options) {
                $options['file_orders'] = esc_html__('File orders', 'wpfdAddon');

                return $options;
            });
            add_filter('wpfd_statistics_filter_by_placeholders', function ($placeholders) {
                $placeholders['file_orders'] = esc_html__('Select files', 'wpfdAddon');

                return $placeholders;
            });
            add_filter('wpfd_statistics_get_selection_value', function ($options, $selection) {
                if ($selection === 'file_orders') {
                    global $wpdb;
                    // Get linked files

                    // Get orders
                    $result    = $wpdb->get_col(
                        $wpdb->prepare(
                            'SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s;',
                            '_wpfd_order_data'
                        )
                    );
                    $downloads = array();

                    if (is_array($result) && is_countable($result) && count($result)) {
                        foreach ($result as $order) {
                            $downloads = array_merge(maybe_unserialize($order), $downloads);
                        }
                    }

                    if (is_array($downloads) && is_countable($downloads) && count($downloads)) {
                        foreach ($downloads as $download) {
                            $orderId = wc_get_order_id_by_order_key($download['order_key']);
                            $order   = wc_get_order($orderId);
                            if ($order->has_status('completed')) {
                                // OK, add this to options array
                                $options[$download['file_data']['id']] = $download['file_data']['name'];
                            }
                        }
                    }
                }

                return array_unique($options);
            }, 10, 2);
            add_filter('wpfd_statistics_get_selection_arguments', function ($arguments, $selection, $selection_value) {
                if ($selection === 'file_orders') {
                    add_filter('wpfd_statistics_download_count_text', function ($text) {
                        return esc_html__('Order Count', 'wpfdAddon');
                    });

                    global $wpdb;
                    // Get orders
                    $result    = $wpdb->get_col(
                        $wpdb->prepare(
                            'SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s;',
                            '_wpfd_order_data'
                        )
                    );
                    $downloads = array();

                    if (is_array($result) && is_countable($result) && count($result)) {
                        foreach ($result as $order) {
                            $downloads = array_merge(maybe_unserialize($order), $downloads);
                        }
                    }

                    $fileIds = $selection_value;
                    $postsIn = array();

                    if (empty($selection_value) || is_null($selection_value)) {
                        foreach ($downloads as $download) {
                            $postsIn[] = $download['file_data']['id'];
                        }
                    } else {
                        // Count order for each file
                        foreach ($fileIds as $fileId) {
                            foreach ($downloads as $download) {
                                if ($download['file_data']['id'] === $fileId) {
                                    $postsIn[] = $fileId;
                                }
                            }
                        }
                    }

                    $postsIn               = array_unique($postsIn);
                    $arguments['post__in'] = $postsIn;
                }

                return $arguments;
            }, 10, 3);
            add_filter('wpfd_statistics_type', function ($type, $selection, $selection_value) {
                if ($selection === 'file_orders') {
                    // phpcs:disable PHPCompatibility.FunctionDeclarations.NewClosure.ClassRefFound -- it's ok ( Closures / anonymous functions could not use "self::" in PHP 5.3 or earlier)
                    return self::STATISTICS_TYPE;
                }

                return $type;
            }, 10, 3);

            // Cloud
            add_filter('wpfda_onedrive_before_save_file_info', array($this, 'wpfdaCloudBeforeSaveFileInfo'));
            add_filter('wpfda_onedrive_business_before_save_file_info', array($this, 'wpfdaCloudBeforeSaveFileInfo'));
            add_filter('wpfda_dropbox_before_save_file_info', array($this, 'wpfdaCloudBeforeSaveFileInfo'));
            add_filter('wpfda_googleDrive_before_save_file_info', array($this, 'wpfdaCloudBeforeSaveFileInfo'));
            add_filter('wpfda_googleDrive_properties', array($this, 'wpfdaGoogleDriveProperties'));
            add_filter('wpfda_aws_before_save_file_info', array($this, 'wpfdaCloudBeforeSaveFileInfo'));

            // Add notification text when product have file
            add_action('woocommerce_product_options_general_product_data', function () {
                // Check product have linked to a file
                global $post;
                $showNotification = get_post_meta($post->ID, '_wpfd_hide_notification', true);

                if ($showNotification !== '' && ($showNotification === false || $showNotification !== 1)) {
                    return;
                }

                $files = get_post_meta($post->ID, '_wpfd_wc_files');
                if ($files !== false && $files !== '' && is_array($files) && count($files)) {
                    wpfd_get_template('html-product-notification.php', array('productId' => $post->ID));
                }
            });
            add_action('wp_ajax_dismiss_wpfd_product_notice', function () {
                $productId = Utilities::getInt('product', 'POST');
                if (!$productId) {
                    wp_send_json_error(array('message' => esc_html__('Product id not found!', 'wpfdAddon')));
                }
                update_post_meta($productId, '_wpfd_hide_notification', 1);
                wp_send_json_success(array('message' => esc_html__('Success!', 'wpfdAddon')));
            });

            /* Product variations */

            // Inject variations row
            add_action('woocommerce_product_after_variable_attributes', array($this, 'addVariantProductRow'), 20, 3);
            // Save the variation
            add_action('woocommerce_save_product_variation', array($this, 'saveVariantProduct'), 10, 2);

            /* Product creation */
            add_action('wpfd_after_core', array($this, 'wpfdAddProductCreationForm'));
            add_action('wpfd_after_file_context_menu', array($this, 'wpfdAddFileContextMenu'));
            add_action('wp_ajax_wpfda_create_product', array($this, 'create_product'));
            add_action('wp_ajax_wpfda_get_edit_link', array($this, 'get_edit_post_link'));
        }
        // Progress files when order mark as completed
        add_action('woocommerce_after_order_object_save', array($this, 'wpfdaOrderCompleted'), 10, 1);
        // Frontend
        // Load scripts/styles
        add_action('wp_enqueue_scripts', array($this, 'addFrontendScripts'));
        // Add linked products
        add_filter('wpfd_files_info', array($this, 'wpfdaShowLinkedProduct'), 10);
        add_filter('wpfd_file_info', array($this, 'wpfdaShowLinkedProduct'), 10);
        add_filter('wpfda_file_info', array($this, 'wpfdaShowLinkedProductForCloud'), 10, 2);
        // Change button text and link
        add_filter('wpfd_files_info', array($this, 'wpfdaChangeDownload'), 12);
        add_filter('wpfd_file_info', array($this, 'wpfdaChangeDownload'), 12);
        add_filter('wpfda_file_info', array($this, 'wpfdaChangeFileDownload'), 10, 2);
        $this->themeFilters();

        // Check permission when using wpfd download link
        add_action('wpfd_before_download_file', array($this, 'wpfdaCheckPermissionOnWpfdLink'), 10, 2);
        // Filter files when user download category
        add_filter('wpfd_selected_files', function ($files) {
            foreach ($files as $key => $file) {
                if (isset($file->products) && !empty($file->products)) {
                    // phpcs:disable PHPCompatibility.FunctionDeclarations.NewClosure.ClassRefFound -- it's ok ( Closures / anonymous functions could not use "self::" in PHP 5.3 or earlier)
                    if (!isset($file->woo_permission) || (isset($file->woo_permission) && $file->woo_permission === self::ONLY_WOO_PERMISSION)) {
                        unset($files[$key]);
                    }
                }
            }

            return $files;
        });

        add_filter('wpfd_selected_file', function ($file) {
            if (isset($file->products) && !empty($file->products)) {
                // phpcs:disable PHPCompatibility.FunctionDeclarations.NewClosure.ClassRefFound -- it's ok ( Closures / anonymous functions could not use "self::" in PHP 5.3 or earlier)
                if (!isset($file->woo_permission) || (isset($file->woo_permission) && $file->woo_permission === self::ONLY_WOO_PERMISSION)) {
                    return false;
                }
            }

            return $file;
        });

        // Display multiple products
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Just for check not use data here
        if (isset($_GET['wpfd_page']) && intval($_GET['wpfd_page']) === 1) {
            add_filter('the_title', function ($title, $id) {
                if (isset($_GET['pids']) && $_GET['pids'] !== '') {
                    // phpcs:enable WordPress.Security.NonceVerification.Recommended
                    $p = get_post($id);
                    // Check is main content area
                    if (in_the_loop() && $p->post_type !== 'product') {
                        return '';
                    }
                }

                return $title;
            }, 10, 2);

            add_filter('the_content', function ($content) {
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- security below
                if (isset($_GET['pids']) && $_GET['pids'] !== '') {
                    $pids = esc_attr($_GET['pids']);
                    // phpcs:enable WordPress.Security.NonceVerification.Recommended
                    // Security, accept number only
                    $pids = array_map('intval', explode(',', $pids));
                    $pids = implode(',', $pids);

                    return do_shortcode('[products ids="' . $pids . '" columns="3"]');
                }

                return $content;
            });

            add_action('template_redirect', function () {
                if (file_exists(TEMPLATEPATH . '/page.php')) {
                    include(TEMPLATEPATH . '/page.php');
                } elseif (file_exists(TEMPLATEPATH . '/single.php')) {
                    include(TEMPLATEPATH . '/single.php');
                } elseif (file_exists(TEMPLATEPATH . '/singular.php')) {
                    include(TEMPLATEPATH . '/singular.php');
                }
                exit;
            });
        }
        add_filter('woocommerce_customer_available_downloads', function ($downloads, $customer_id) {
            include_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/Woocommerce/WpfdWCDownloadFiles.php';
            $customer_orders = get_posts(array(
                'numberposts' => - 1,
                'meta_key'    => '_customer_user',
                'meta_value'  => get_current_user_id(),
                'post_type'   => wc_get_order_types(),
                'post_status' => 'wc-completed',
            ));
            foreach ($customer_orders as $order) {
                $orderId       = $order->ID;
                $downloadFiles = new WpfdWCDownloadFiles($orderId);
                $files         = $downloadFiles->getDownloads();
                $downloads = array_merge($files, $downloads);
            }

            return $downloads;
        }, 10, 2);

        add_filter('woocommerce_order_get_downloadable_items', function ($downloads, $order) {
            include_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/Woocommerce/WpfdWCDownloadFiles.php';
            $orderId       = $order->get_id();
            $downloadFiles = new WpfdWCDownloadFiles($orderId);
            $files         = $downloadFiles->getDownloads();
            $downloads     = $files + $downloads;

            return $downloads;
        }, 10, 2);

        add_filter('woocommerce_product_file', array($this, 'woocommerceProductFile'), 99, 3);

        // Load frontend stylesheet
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_style('wpfda_frontend', WPFDA_PLUGIN_URL . '/app/site/assets/css/frontend.css');
        });
    }
    /**
     * Check if woocommerce installed
     *
     * @return boolean
     */
    public function isWoo()
    {
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '3.5', 'ge')) {
            return true;
        }

        return false;
    }

    /**
     * Add styles, scripts to admin area
     *
     * @param string $hook Current page
     *
     * @return void
     */
    public function addAdminScripts($hook)
    {
        global $post;
        if (!in_array($hook, array('post.php', 'post-new.php')) || $post->post_type !== 'product') {
            return;
        }

        wp_enqueue_style('wpfd-woo-addon-style', plugins_url('app/admin/assets/css/style.css', WPFDA_PLUGIN_FILE));
        wp_enqueue_script('wpfd-woo-addon-script', plugins_url('app/admin/assets/js/wpfd_woocommerce_addon.js', WPFDA_PLUGIN_FILE), array('jquery'));
        wp_localize_script('wpfd-woo-addon-script', 'wpfdWooAddonVars', array('adminurl' => admin_url()));
    }

    /**
     * Add front-end scripts
     *
     * @param string $hook Current page hook
     *
     * @return void
     */
    public function addFrontendScripts($hook)
    {
        global $post;
        if (!isset($post)) {
            return;
        }
        //if (has_shortcode($post->post_content, 'wpfd_category') || has_shortcode($post->post_content, 'wpfd_file') || has_shortcode($post->post_content, 'wpfd_search')) {
            wp_enqueue_script('wpfd-woo-script', plugins_url('app/site/assets/js/woocommerce.js', WPFDA_PLUGIN_FILE), array('jquery'));
        //}
    }

    /**
     * Add tab to product page
     *
     * @param array $tabs Woocommerce product tabs array
     *
     * @return array
     */
    public function addProductTab($tabs)
    {
        $tabs['wpfd'] = array(
            'label'    => __('WP File Download', 'wpfdAddon'),
            'target'   => 'wpfd_product_data',
            'class'    => array('show_if_downloadable', 'hidden'),
            'priority' => 80,
        );

        return $tabs;
    }

    /**
     * Add wpfd panel to product page
     *
     * @return void
     */
    public function addProductDataPanel()
    {
        wpfd_get_template('html-product-data-wpfd.php');
    }

    /**
     * Add variant product row
     *
     * @param integer $loop           Variant loop
     * @param array   $variation_data Variant data
     * @param array   $variation      Variant
     *
     * @return void
     */
    public function addVariantProductRow($loop, $variation_data, $variation)
    {
        $variation = new WC_Product_Variation($variation);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- it's OK
        echo wpfd_get_template_html('html-product-variable-data.php', array('loop' => $loop, 'variation_id' => $variation->get_id(), 'variation' => $variation));
    }

    /**
     * Include downloads information to files
     *
     * @param array|object|WP_Post $files Post instance(s)
     *
     * @return array|object
     */
    public function wpfdaChangeDownload($files)
    {
        if ($files instanceof WP_Post) {
            return $this->wpfdaChangeFileDownload($files);
        }
        if (!is_array($files)) {
            return $files;
        }
        // Cloud files
        if (is_array($files) && isset($files['ID'])) {
            return $this->wpfdaChangeFileDownload($files);
        }
        $filex = array();
        foreach ($files as $file) {
            $filex[] = $this->wpfdaChangeFileDownload($file);
        }

        return $filex;
    }

    /**
     * Change file download
     *
     * @param object|array $file       File instance
     * @param string       $returnType Type to return
     *
     * @return object
     */
    public function wpfdaChangeFileDownload($file, $returnType = OBJECT)
    {
        $isObject = is_object($file);
        $file = $this->transform($file, OBJECT);
        if (!$isObject) {
            $returnType = ARRAY_A;
        }
        $categoryFrom = apply_filters('wpfdAddonCategoryFrom', $file->catid);
        switch ($categoryFrom) {
            case 'onedrive':
                $config = get_option('_wpfdAddon_onedrive_fileInfo');
                if (!empty($config) && isset($config[$file->catid]) && isset($config[$file->catid][$file->ID])) {
                    $metaData = $config[$file->catid][$file->ID];
                }
                break;
            case 'onedrive_business':
                $config = get_option('_wpfdAddon_onedrive_business_fileInfo');
                if (!empty($config) && isset($config[$file->catid]) && isset($config[$file->catid][$file->ID])) {
                    $metaData = $config[$file->catid][$file->ID];
                }
                break;
            case 'dropbox':
                $config = get_option('_wpfdAddon_dropbox_fileInfo');
                if (!empty($config) && isset($config[$file->catid]) && isset($config[$file->catid][$file->ID])) {
                    $metaData = $config[$file->catid][$file->ID];
                }
                break;
            case 'googleTeamDrive':
            case 'googleDrive':
                $metaData = array('woo_permission' => isset($file->woo_permission) ? $file->woo_permission : self::ONLY_WOO_PERMISSION);
                break;
            case 'aws':
                $config = get_option('_wpfdAddon_aws_fileInfo');
                if (!empty($config) && isset($config[$file->catid]) && isset($config[$file->catid][$file->ID])) {
                    $metaData = $config[$file->catid][$file->ID];
                }
                break;
            default:
                $metaData = get_post_meta($file->ID, '_wpfd_file_metadata', true);
                break;
        }

        $file->woo_permission = (isset($metaData) && isset($metaData['woo_permission'])) ? esc_attr($metaData['woo_permission']) : self::ONLY_WOO_PERMISSION;

        if (is_object($file) && isset($file->products) && !empty($file->products)) {
            // There is no valid order change linkdownload to add to cart
            $totalProduct = count($file->products);
            if ($totalProduct > 0) {
                $product                = $file->products[0];
                $file->show_add_to_cart = false;

                if ($this->isWooPermission($file) === self::BOTH_PERMISSION) {
                    if (!WpfdHelperFile::checkAccess((array) $file)) {
                        $file->show_add_to_cart = true;
                    }
                } elseif ($this->isWooPermission($file) === self::ONLY_WOO_PERMISSION) {
                    $file->show_add_to_cart = true;
                }

                if ($file->show_add_to_cart === true) {
                    $file->linktitle = '#';
                    $file->linkdownload = '#';
                    if (WpfdHelperFile::isWatermarkEnabled()) {
                        /* @var $product WC_Product_Simple */
                        $file->linktitle = $product->is_visible() ? $product->get_permalink() : '';
                    } else {
                        /* @var $product WC_Product_Simple */
                        $file->viewerlink = $product->is_visible() ? $product->get_permalink() : '';
                    }
                    if ($totalProduct > 1) {
                        $pids = array();
                        foreach ($file->products as $prd) {
                            if ($prd) {
                                /* @var $prd WC_Product_Simple */
                                $pids[] = $prd->get_id();
                            }
                        }
                        if (WpfdHelperFile::isWatermarkEnabled()) {
                            /* @var $product WC_Product_Simple */
                            $file->linktitle = trailingslashit(site_url()) . '?wpfd_page=1&pids=' . implode(',', $pids);
                        } else {
                            /* @var $product WC_Product_Simple */
                            $file->viewerlink = trailingslashit(site_url()) . '?wpfd_page=1&pids=' . implode(',', $pids);
                        }
                    }
                    // Need an mark for check in handlebars template
                    $file->is_product = 1;
                    $file->product_id = $product->get_id();
                }

                $customer_orders = get_posts(array(
                    'numberposts' => - 1,
                    'meta_key'    => '_customer_user',
                    'meta_value'  => get_current_user_id(),
                    'post_type'   => wc_get_order_types(),
                    'post_status' => 'wc-completed',
                ));

                foreach ($customer_orders as $order) {
                    $order         = wc_get_order($order->ID);
                    $items         = $order->get_downloadable_items();
                    $wpfdOrderData = get_post_meta($order->get_id(), '_wpfd_order_data', true);
                    foreach ($items as $item) {
                        if (isset($item['download_id']) && isset($wpfdOrderData[$item['download_id']])) {
                            $orderedFilesId = $wpfdOrderData[$item['download_id']]['file_data']['id'];

                            if ((string) $file->ID === (string) $orderedFilesId) {
                                // Change linkdownload to ordered link file
                                $file->linkdownload         = $item['download_url'];
                                $file->woo_download_granted = 1;

                                return $this->transform($file, $returnType);
                            }
                        }
                    }
                }

                return $this->transform($file, $returnType);
            }
        }

        return $this->transform($file, $returnType);
    }

    /**
     * Transform file instance base on it type
     *
     * @param array|object $object Input object
     * @param string       $type   Output type
     *
     * @return array|stdClass
     */
    private function transform($object, $type)
    {
        if (is_array($object)) {
            if ($type === ARRAY_A) {
                return $object;
            }

            if ($type === OBJECT) {
                $tmpObject = new stdClass;
                foreach ($object as $key => $value) {
                    $tmpObject->{$key} = $value;
                }

                return $tmpObject;
            }
        }

        if (is_object($object)) {
            if ($type === OBJECT) {
                return $object;
            }

            if ($type === ARRAY_A) {
                $tmpObject = array();
                foreach ($object as $key => $value) {
                    $tmpObject[$key] = $value;
                }

                return $tmpObject;
            }
        }

        return $object;
    }

    /**
     * Theme filters
     *
     * @return void
     */
    public function themeFilters()
    {
        $wooConfig = get_option('_wpfd_woocommerce_config', array());
        if (isset($wooConfig['woo_add_to_cart_background']) && !empty($wooConfig['woo_add_to_cart_background'])) {
            add_filter('wpfd_download_button_background_color_handlebars', function ($color) use ($wooConfig) {
                return '{{#if is_product}}' . esc_html($wooConfig['woo_add_to_cart_background']) . '{{else}}' . $color . '{{/if}}';
            });
            add_filter('wpfd_download_button_background_color', function ($color, $file) use ($wooConfig) {
                if (isset($file->is_product) && $file->is_product) {
                    return esc_html($wooConfig['woo_add_to_cart_background']);
                }

                return $color;
            }, 10, 2);
        }
        add_filter('wpfd_download_data_attributes_handlebars', function ($text) {
            $html = '{{#if woo_download_granted}}';
            $html .= $text;
            $html .= '{{else if is_product}}';
            $html .= 'data-product_id="{{product_id}}"';
            $html .= '{{else}}';
            $html .= $text;
            $html .= '{{/if}}';

            return $html;
        });
        add_filter('wpfd_download_data_attributes', function ($attributes, $currentFile) {
            if (isset($currentFile->is_product) && isset($currentFile->product_id) && intval($currentFile->product_id) > 0) {
                $attributes[] = 'data-product_id="' . $currentFile->product_id . '"';

                return $attributes;
            }

            return $attributes;
        }, 10, 2);
        add_filter('wpfd_download_text_handlebars', function ($text) {
            $html = '{{#if woo_download_granted}}';
            $html .= $text;
            $html .= '{{else if is_product}}';
            $html .= esc_html__('Add to cart', 'wpfdAddon');
            $html .= '{{else}}';
            $html .= $text;
            $html .= '{{/if}}';

            return $html;
        });
        add_filter('wpfd_download_text', function ($text, $currentFile) {
            if (isset($currentFile->woo_download_granted) && $currentFile->woo_download_granted === 1) {
                return $text;
            }
            if (isset($currentFile->is_product) && isset($currentFile->product_id) && intval($currentFile->product_id) > 0) {
                return esc_html__('Add to cart', 'wpfdAddon');
            }

            return $text;
        }, 10, 2);
        // Change icon to shopping-cart-plus
        add_filter('wpfd_download_icon_handlebars', function ($text) {
            $html = '{{#if woo_download_granted}}';
            $html .= $text;
            $html .= '{{else if is_product}}';
            $html .= '<i class="zmdi zmdi-shopping-cart-plus wpfd-add-to-cart"></i>';
            $html .= '{{else}}';
            $html .= $text;
            $html .= '{{/if}}';

            return $html;
        });
        if (!WpfdHelperFile::isWatermarkEnabled()) {
            add_filter('wpfd_preview_text_handlebars', function ($text) {
                return '{{#if is_product}}{{ plural productsCount \'' . esc_html__('View Product(s)', 'wpfdAddon') . '\' }}{{else}}' . $text . '{{/if}}';
            });
            add_filter('wpfd_preview_text', function ($text, $currentFile) {
                if (isset($currentFile->is_product) && isset($currentFile->product_id) && intval($currentFile->product_id) > 0) {
                    return _n('View Product', 'View Products', count($currentFile->products), 'wpfdAddon');
                }

                return $text;
            }, 10, 2);
            add_filter('wpfd_preview_classes_handlebars', function ($classes) {
                return str_replace('wpfdlightbox', '{{#unless is_product}}wpfdlightbox{{/unless}}', $classes);
            });
            add_filter('wpfd_preview_classes', function ($classes, $file) {
                if (isset($file->is_product) && $file->is_product) {
                    return str_replace('wpfdlightbox', '', $classes);
                }

                return $classes;
            }, 10, 2);
        }

        add_filter('wpfd_file_info_title_args', function ($template, $file, $config, $params) {
            //if (!isset($currentFile->woo_download_granted) && isset($currentFile->is_product) && isset($currentFile->product_id) && intval($currentFile->product_id) > 0) {
                $template['args']['url'] = isset($file->linktitle) ? esc_url($file->linktitle) : $file->linkdownload;
            //}

            return $template;
        }, 10, 4);
        // Change icon to shopping-cart-plus
        add_filter('wpfd_download_icon', function ($html, $currentFile) {
            if (!isset($currentFile->woo_download_granted) && isset($currentFile->is_product) && isset($currentFile->product_id) && intval($currentFile->product_id) > 0) {
                return '<i class="zmdi zmdi-shopping-cart-plus wpfd-add-to-cart"></i>';
            }

            return $html;
        }, 10, 2);
    }

    /**
     * Check permission
     *
     * @param object $file File instance
     *
     * @return boolean|string True if use WooCommerce permission only
     */
    public function isWooPermission($file)
    {
        if (is_object($file) && isset($file->woo_permission)) {
            if (in_array($file->woo_permission, array(self::ONLY_WOO_PERMISSION, self::BOTH_PERMISSION))) {
                return $file->woo_permission;
            }
        }

        // Default value
        if (isset($file->products) && !empty($file->products)) {
            return self::ONLY_WOO_PERMISSION;
        }

        return false;
    }

    /**
     * Check file has products
     *
     * @param boolean $return True or false
     * @param WP_Post $file   File object
     *
     * @return boolean
     */
    public function wpfdaFileHasProducts($return, $file)
    {
        if (isset($file->products) && !empty($file->products)) {
            return true;
        }

        return false;
    }

    /**
     * File row data
     *
     * @param array  $data Data
     * @param object $file File data
     *
     * @return array
     */
    public function wpfdaFileRowData($data, $file)
    {
        $productIds = array();
        if (isset($file->products) && is_array($file->products)) {
            foreach ($file->products as $product) {
                if ($product) {
                    $productId = $product->get_id();
                    if ($product instanceof WC_Product_Variation) {
                        $productId = $product->get_parent_id();
                    }
                    $productIds[] = $productId;
                }
            }
            if (count($productIds) > 0) {
                $data['products'] = implode(',', $productIds);
            }
        }

        return $data;
    }

    /**
     * Assigin linked products to file object
     *
     * @param WP_Post $files File data
     *
     * @return mixed
     */
    public function wpfdaShowLinkedProduct($files)
    {
        if (!$files || empty($files)) {
            return $files;
        }

        // Check if file is object
        if (is_object($files)) {
            if (!isset($files->ID)) {
                return $files;
            }
            $productIds      = $this->getLinkedProduct($files->ID);
            $files->products = array();
            if (!empty($productIds)) {
                foreach ($productIds as $productId) {
                    $product = wc_get_product($productId);
                    if (!$product) {
                        continue;
                    }
                    $files->products[] = $product;
                }
            }
            $files->productsCount = count($files->products);

            return $files;
        }

        // Check is single file
        if (is_array($files) && isset($files['ID']) && is_numeric($files['ID'])) {
            $productIds        = $this->getLinkedProduct($files['ID']);
            $files['products'] = array();
            if (!empty($productIds)) {
                foreach ($productIds as $productId) {
                    $product = wc_get_product($productId);
                    if (!$product) {
                        continue;
                    }
                    $files['products'][] = $product;
                }
            }
            $files['productsCount'] = count($files['products']);

            return $files;
        }

        foreach ($files as $file) {
            // Valid linked Products
            if (!isset($file->ID) || !is_numeric($file->ID)) {
                continue;
            }

            $productIds     = $this->getLinkedProduct($file->ID);
            $file->products = array();
            if (!empty($productIds)) {
                foreach ($productIds as $productId) {
                    $product = wc_get_product($productId);
                    if (!$product) {
                        continue;
                    }
                    $file->products[] = $product;
                }
            }
            $file->productsCount = count($file->products);
        }

        return $files;
    }

    /**
     * Show linked products for cloud
     *
     * @param array|object $data File data
     * @param string       $type Output type
     *
     * @return array|stdClass
     */
    public function wpfdaShowLinkedProductForCloud($data, $type)
    {
        $isObject = is_object($data);

        $data = $this->transform($data, ARRAY_A);
        if (in_array($type, wpfd_get_support_cloud())) {
            $config = array();
            $linkedProductsData = array();
            switch ($type) {
                case 'onedrive':
                    $config             = get_option('_wpfdAddon_onedrive_fileInfo');
                    $linkedProductsData = get_option('_wpfdAddon_onedrive_products_linked');

                    break;
                case 'onedrive_business':
                    $config             = get_option('_wpfdAddon_onedrive_business_fileInfo');
                    $linkedProductsData = get_option('_wpfdAddon_onedrive_business_products_linked');

                    break;
                case 'dropbox':
                    $config             = get_option('_wpfdAddon_dropbox_fileInfo');
                    $linkedProductsData = get_option('_wpfdAddon_dropbox_products_linked');

                    break;
                case 'googleDrive':
                    $linkedProductsData = get_option('_wpfdAddon_googleDrive_products_linked');

                    break;
                case 'googleTeamDrive':
                    $linkedProductsData = get_option('_wpfdAddon_googleTeamDrive_products_linked');

                    if (!empty($linkedProductsData) && isset($linkedProductsData[$data['catid']]) && isset($linkedProductsData[$data['catid']][$data['id']])) {
                        $productIds = isset($linkedProductsData[$data['catid']][$data['id']]['_wpfd_products_linked']) ?
                            $linkedProductsData[$data['catid']][$data['id']]['_wpfd_products_linked'] : '';
                        $data['products'] = array();
                        if (!empty($productIds)) {
                            foreach ($productIds as $productId) {
                                $data['products'][] = wc_get_product($productId);
                            }
                        }
                        $data['productsCount'] = count($data['products']);
                    }
                    break;
                case 'aws':
                    $config             = get_option('_wpfdAddon_aws_fileInfo');
                    $linkedProductsData = get_option('_wpfdAddon_aws_products_linked');

                    break;
                default:
                    break;
            }

            if (!empty($linkedProductsData) && isset($linkedProductsData[$data['catid']]) && isset($linkedProductsData[$data['catid']][$data['id']])) {
                $productIds       = isset($linkedProductsData[$data['catid']][$data['id']]['_wpfd_products_linked']) ?
                    $linkedProductsData[$data['catid']][$data['id']]['_wpfd_products_linked'] : '';
                $data['products'] = array();
                if (!empty($productIds)) {
                    foreach ($productIds as $productId) {
                        $product = wc_get_product($productId);
                        if ($product) {
                            $data['products'][] = $product;
                        }
                    }
                }
                $data['productsCount'] = count($data['products']);
            }

            if ($type !== 'googleDrive') {
                if (!empty($config) && isset($config[$data['catid']]) && isset($config[$data['catid']][$data['id']])) {
                    $data['woo_permission'] = isset($config[$data['catid']][$data['id']]['woo_permission']) ?
                        $config[$data['catid']][$data['id']]['woo_permission'] : self::ONLY_WOO_PERMISSION;
                }
            }
        }
        if ($isObject) {
            $data = $this->transform($data, OBJECT);
        }

        return $data;
    }

    /**
     * Filter data on save onedrive file
     *
     * @param array $fileInfo File information
     *
     * @return array
     */
    public function wpfdaCloudBeforeSaveFileInfo($fileInfo)
    {
        $permission = Utilities::getInput('woo_permission', 'POST', 'string');
        if (!is_null($permission)) {
            $fileInfo['woo_permission'] = $permission;
        }

        return $fileInfo;
    }

    /**
     * Merge Google Drive properties
     *
     * @param array $properties Properties
     *
     * @return array
     */
    public function wpfdaGoogleDriveProperties($properties)
    {
        return array_merge(array('woo_permission'), $properties);
    }

    /**
     * Add wpfd files on product save
     *
     * @param integer $productId Product id
     *
     * @return void
     */
    public function wpfdaWooAddFilesSave($productId)
    {
        // Text Field
        $_wpfd_wc_files_name   = Utilities::getInput('_wpfd_wc_files_name', 'POST', 'none');
        $_wpfd_wc_files_id     = Utilities::getInput('_wpfd_wc_files_id', 'POST', 'none');
        $_wpfd_wc_files_catid  = Utilities::getInput('_wpfd_wc_files_catid', 'POST', 'none');
        $_wpfd_download_limit  = Utilities::getInput('_wpfd_download_limit', 'POST', 'string');
        $_wpfd_download_expiry = Utilities::getInput('_wpfd_download_expiry', 'POST', 'string');
        if (!empty($_wpfd_wc_files_name) && !empty($_wpfd_wc_files_id) && !empty($_wpfd_wc_files_catid)) {
            $files = $this->wpfdaPrepareDownloads($_wpfd_wc_files_name, $_wpfd_wc_files_id, $_wpfd_wc_files_catid);

            // Unlink removed files from product
            $this->wpfdaUnlinkRemovedProductFromFile($_wpfd_wc_files_id, $productId);
            // Connect file to product
            update_post_meta($productId, '_wpfd_wc_files', $files);
            foreach ($files as $file) {
                $this->wpfdaLinkProductToFile($file['id'], $file['catid'], $productId);
            }
            update_post_meta($productId, '_wpfd_download_limit', $_wpfd_download_limit);
            update_post_meta($productId, '_wpfd_download_expiry', $_wpfd_download_expiry);
        } else { // Empty files
            $this->wpfdaUnlinkAllFiles($productId);
            delete_post_meta($productId, '_wpfd_wc_files');
            delete_post_meta($productId, '_wpfd_download_limit');
            delete_post_meta($productId, '_wpfd_download_expiry');
        }
    }

    /**
     * Unlink all files from a product
     *
     * @param integer $productId Product id
     *
     * @return void
     */
    private function wpfdaUnlinkAllFiles($productId)
    {
        $files = $this->getLinkedFiles($productId);
        if ($files === '' || !is_array($files)) {
            return;
        }
        foreach ($files as $file) {
            $this->wpfdaUnlinkProductFromFile($file['id'], $productId);
        }
    }

    /**
     * Unlink removed files
     *
     * @param array   $fileIds   File ids
     * @param integer $productId Product id
     *
     * @return void
     */
    private function wpfdaUnlinkRemovedProductFromFile($fileIds, $productId)
    {
        $fileIds = array_map('intval', $fileIds);
        $files   = $this->getLinkedFiles($productId);

        if ($files === '' || !is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if (!in_array($file['id'], $fileIds)) {
                $this->wpfdaUnlinkProductFromFile($file['id'], $productId);
            }
        }
    }

    /**
     * Unlink product from file
     *
     * @param array   $fileId    File id
     * @param integer $productId Product id
     *
     * @return void
     */
    private function wpfdaUnlinkProductFromFile($fileId, $productId)
    {
        $linkedProducts = get_post_meta($fileId, '_wpfd_products_linked', true);
        if (is_array($linkedProducts) && count($linkedProducts)) {
            foreach ($linkedProducts as $key => $linkedProductId) {
                if (intval($linkedProductId) === intval($productId)) {
                    unset($linkedProducts[$key]);
                }
            }
            if (empty($linkedProducts)) {
                delete_post_meta($fileId, '_wpfd_products_linked');
            } else {
                update_post_meta($fileId, '_wpfd_products_linked', $linkedProducts);
            }
        }
    }

    /**
     * Link product to file
     *
     * @param string|integer $fileId    File id
     * @param integer        $catId     Category Id
     * @param integer        $productId Product id
     *
     * @return void
     */
    private function wpfdaLinkProductToFile($fileId, $catId, $productId)
    {
        $categoryFrom = apply_filters('wpfdAddonCategoryFrom', $catId);
        switch ($categoryFrom) {
            case 'onedrive':
                $this->linkProductToOnedriveFile($fileId, $catId, $productId);
                break;
            case 'onedrive_business':
                $this->linkProductToOnedriveBusinessFile($fileId, $catId, $productId);
                break;
            case 'dropbox':
                $this->linkProductToDropboxFile($fileId, $catId, $productId);
                break;
            case 'googleDrive':
                $this->linkProductToGoogleDriveFile($fileId, $catId, $productId);
                break;
            case 'googleTeamDrive':
                $this->linkProductToGoogleTeamDriveFile($fileId, $catId, $productId);
                break;
            case 'aws':
                $this->linkProductToAwsFile($fileId, $catId, $productId);
                break;
            default:
                $this->wpfdLinkProductToLocalFile($fileId, $productId);
                break;
        }
    }

    /**
     * Link product to Onedrive file
     *
     * @param string|integer $fileId    File id
     * @param integer        $catId     Category Id
     * @param integer        $productId Product id
     *
     * @return void
     */
    private function linkProductToOnedriveFile($fileId, $catId, $productId)
    {
        $onedriveInfos = get_option('_wpfdAddon_onedrive_products_linked');

        if (false === $onedriveInfos) {
            $onedriveInfos = array(
                $catId => array(
                    $fileId => array(
                        '_wpfd_products_linked' => array($productId)
                    )
                )
            );
        } else {
            $linkedProducts                                          = isset($onedriveInfos[$catId][$fileId]['_wpfd_products_linked']) ? $onedriveInfos[$catId][$fileId]['_wpfd_products_linked'] : array();
            $data                                                    = array_unique(array_merge($linkedProducts, array($productId)));
            $onedriveInfos[$catId][$fileId]['_wpfd_products_linked'] = $data;
        }

        update_option('_wpfdAddon_onedrive_products_linked', $onedriveInfos);
    }
    /**
     * Link product to Onedrive Business file
     *
     * @param string|integer $fileId    File id
     * @param integer        $catId     Category Id
     * @param integer        $productId Product id
     *
     * @return void
     */
    private function linkProductToOnedriveBusinessFile($fileId, $catId, $productId)
    {
        $onedriveInfos = get_option('_wpfdAddon_onedrive_business_products_linked');

        if (false === $onedriveInfos) {
            $onedriveInfos = array(
                $catId => array(
                    $fileId => array(
                        '_wpfd_products_linked' => array($productId)
                    )
                )
            );
        } else {
            $linkedProducts                                          = isset($onedriveInfos[$catId][$fileId]['_wpfd_products_linked']) ? $onedriveInfos[$catId][$fileId]['_wpfd_products_linked'] : array();
            $data                                                    = array_unique(array_merge($linkedProducts, array($productId)));
            $onedriveInfos[$catId][$fileId]['_wpfd_products_linked'] = $data;
        }

        update_option('_wpfdAddon_onedrive_business_products_linked', $onedriveInfos);
    }
    /**
     * Link product to Dropbox file
     *
     * @param string|integer $fileId    File id
     * @param integer        $catId     Category Id
     * @param integer        $productId Product id
     *
     * @return void
     */
    private function linkProductToDropboxFile($fileId, $catId, $productId)
    {
        $dropboxInfos = get_option('_wpfdAddon_dropbox_products_linked');

        if (false === $dropboxInfos) {
            $dropboxInfos = array(
                $catId => array(
                    $fileId => array(
                        '_wpfd_products_linked' => array($productId)
                    )
                )
            );
        } else {
            $linkedProducts                                         = isset($dropboxInfos[$catId][$fileId]['_wpfd_products_linked']) ? $dropboxInfos[$catId][$fileId]['_wpfd_products_linked'] : array();
            $data                                                   = array_unique(array_merge($linkedProducts, array($productId)));
            $dropboxInfos[$catId][$fileId]['_wpfd_products_linked'] = $data;
        }

        update_option('_wpfdAddon_dropbox_products_linked', $dropboxInfos);
    }

    /**
     * Link product to Google Drive file
     *
     * @param string  $fileId    File id
     * @param integer $catId     Category id
     * @param integer $productId Product id
     *
     * @return void
     */
    private function linkProductToGoogleDriveFile($fileId, $catId, $productId)
    {
        $googleDriveInfos = get_option('_wpfdAddon_googleDrive_products_linked');

        if (false === $googleDriveInfos) {
            $googleDriveInfos = array(
                $catId => array(
                    $fileId => array(
                        '_wpfd_products_linked' => array($productId)
                    )
                )
            );
        } else {
            $linkedProducts                                             = isset($googleDriveInfos[$catId][$fileId]['_wpfd_products_linked']) ? $googleDriveInfos[$catId][$fileId]['_wpfd_products_linked'] : array();
            $data                                                       = array_unique(array_merge($linkedProducts, array($productId)));
            $googleDriveInfos[$catId][$fileId]['_wpfd_products_linked'] = $data;
        }

        update_option('_wpfdAddon_googleDrive_products_linked', $googleDriveInfos);
    }

    /**
     * Link product to Google Team Drive file
     *
     * @param string  $fileId    File id
     * @param integer $catId     Category id
     * @param integer $productId Product id
     *
     * @return void
     */
    private function linkProductToGoogleTeamDriveFile($fileId, $catId, $productId)
    {
        $googleTeamDriveInfos = get_option('_wpfdAddon_googleTeamDrive_products_linked');

        if (false === $googleTeamDriveInfos) {
            $googleTeamDriveInfos = array(
                $catId => array(
                    $fileId => array(
                        '_wpfd_products_linked' => array($productId)
                    )
                )
            );
        } else {
            $linkedProducts = isset($googleTeamDriveInfos[$catId][$fileId]['_wpfd_products_linked']) ? $googleTeamDriveInfos[$catId][$fileId]['_wpfd_products_linked'] : array();
            $data = array_unique(array_merge($linkedProducts, array($productId)));
            $googleTeamDriveInfos[$catId][$fileId]['_wpfd_products_linked'] = $data;
        }

        update_option('_wpfdAddon_googleTeamDrive_products_linked', $googleTeamDriveInfos);
    }

    /**
     * Link product to AWS file
     *
     * @param string  $fileId    File id
     * @param integer $catId     Category id
     * @param integer $productId Product id
     *
     * @return void
     */
    private function linkProductToAwsFile($fileId, $catId, $productId)
    {
        $awsInfos = get_option('_wpfdAddon_aws_products_linked');

        if (false === $awsInfos) {
            $awsInfos = array(
                $catId => array(
                    $fileId => array(
                        '_wpfd_products_linked' => array($productId)
                    )
                )
            );
        } else {
            $linkedProducts                                     = isset($awsInfos[$catId][$fileId]['_wpfd_products_linked']) ? $awsInfos[$catId][$fileId]['_wpfd_products_linked'] : array();
            $data                                               = array_unique(array_merge($linkedProducts, array($productId)));
            $awsInfos[$catId][$fileId]['_wpfd_products_linked'] = $data;
        }

        update_option('_wpfdAddon_aws_products_linked', $awsInfos);
    }

    /**
     * Link product to local file
     *
     * @param integer $fileId    File id
     * @param string  $productId Product id
     *
     * @return void
     */
    private function wpfdLinkProductToLocalFile($fileId, $productId)
    {
        $linkedProducts = get_post_meta($fileId, '_wpfd_products_linked', true);
        if ($linkedProducts === '') {
            $linkedProducts = array();
        }

        update_post_meta($fileId, '_wpfd_products_linked', array_unique(array_merge($linkedProducts, array($productId))));
    }

    /**
     * Prepare download file array to store
     *
     * @param array $file_names  List file name
     * @param array $file_ids    List file id
     * @param array $file_catids List file category id
     *
     * @return array
     */
    private function wpfdaPrepareDownloads($file_names, $file_ids, $file_catids)
    {
        $downloads = array();

        if (!empty($file_ids)) {
            $file_ids_size = count($file_ids);

            for ($i = 0; $i < $file_ids_size; $i ++) {
                if (isset($file_ids[$i])) {
                    $downloads[] = array(
                        'name'  => wc_clean($file_names[$i]),
                        'id'    => wc_clean($file_ids[$i]),
                        'catid' => wc_clean($file_catids[$i]),
                    );
                }
            }
        }

        return $downloads;
    }

    /**
     * Get linked file to product
     *
     * @param integer $productId Product id
     *
     * @return array
     */
    private function getLinkedFiles($productId)
    {
        return get_post_meta($productId, '_wpfd_wc_files', true);
    }

    /**
     * Get linked product of file
     *
     * @param integer $fileId File id
     *
     * @return array
     */
    private function getLinkedProduct($fileId)
    {
        return get_post_meta($fileId, '_wpfd_products_linked', true);
    }

    /**
     * Grant download access on order completed
     *
     * @param WC_Order $order WC_Order object
     *
     * @return void
     */
    public function wpfdaOrderCompleted($order)
    {
        if ($order->get_status() !== 'completed') {
            return;
        }

        // Create meta data store current order download status
        $items        = $order->get_items();
        $downloadData = get_post_meta($order->get_id(), '_wpfd_order_data', true);

        if ($downloadData === '') {
            $downloadData = array();
        }

        foreach ($items as $item) {
            // Get downloadable files
            /* @var \WC_Product $product */
            $product = $item->get_product();

            // get_id() always return variant id if this is variant
            $files   = $this->getLinkedFiles($product->get_id());
            if (is_array($files) && count($files)) {
                // Get max download
                $download_limit   = $product->get_meta('_wpfd_download_limit', true);
                $download_expires = $product->get_meta('_wpfd_download_expiry', true);
                // Calculate expiries date
                if ($download_expires > 0) {
                    $from_date      = $order->get_date_completed() ? $order->get_date_completed()->format('Y-m-d') : current_time('mysql', true);
                    $access_expires = strtotime($from_date . ' + ' . $download_expires . ' DAY');
                }
                $downloads_remaining = 0 > $download_limit ? '' : ($download_limit === '' ? $download_limit : $download_limit * $item->get_quantity());
                foreach ($files as $file) {
                    // Check file already granted
                    $break = false;
                    if (is_array($downloadData) && count($downloadData)) {
                        foreach ($downloadData as $download) {
                            if ($download['product_id'] === $item->get_product_id() && $download['file_data']['id'] === $file['id']) {
                                $break = true;
                            }
                        }
                    }
                    if ($break) {
                        continue;
                    }
                    // todo: get data object with real path...should we need this?
                    // $fileData = $this->getFile($file['id']);
                    $download_id                = wp_generate_uuid4();
                    $downloadData[$download_id] = array(
                        'download_id'         => $download_id,
                        'product_id'          => $item->get_product_id(),
                        'order_key'           => $order->get_order_key('edit'),
                        'file_data'           => $file,
                        // array('id','catid','name')
                        'user_id'             => $order->get_customer_id('edit'),
                        'access_granted'      => current_time('timestamp', true),
                        'access_expires'      => isset($access_expires) ? $access_expires : '',
                        'downloads_remaining' => isset($downloads_remaining) ? $downloads_remaining : '',
                        // todo: Add download_remaining support
                        'download_count'      => 0,
                    );

                    // Add order statistics
                    /* @var $fileModel WpfdModelFile */
                    WpfdHelperFile::addStatisticsRow($file['id'], self::STATISTICS_TYPE);
                }
            }
        }
        update_post_meta($order->get_id(), '_wpfd_order_data', $downloadData);
    }

    /**
     * Add woocommerce permission field
     *
     * @param array $data File data
     *
     * @return void
     */
    public function wpfdaAddWocommercePermissionField($data)
    {
        if (isset($data['products']) && !empty($data['products'])) {
            $value = isset($data['woo_permission']) ? esc_html($data['woo_permission']) : self::ONLY_WOO_PERMISSION;
            wpfd_get_template('html-file-permission-field.php', array('value' => $value));
        }
    }

    /**
     * Add woocommerce permission field
     *
     * @param array $metadata Meta data array
     * @param array $data     Field datas
     *
     * @return array
     */
    public function wpfdaSaveWooPermissionField($metadata, $data)
    {
        $permission                 = Utilities::getInput('woo_permission', 'POST', 'string');
        $metadata['woo_permission'] = !is_null($permission) ? esc_html($permission) : self::ONLY_WOO_PERMISSION;

        return $metadata;
    }

    /**
     * Check permission on wpfd download link
     *
     * @param object $file File object
     * @param array  $meta File metadata
     *
     * @return void
     */
    public function wpfdaCheckPermissionOnWpfdLink($file, $meta)
    {
        if (isset($file->products) && !empty($file->products)) {
            if (!isset($file->woo_permission) || (isset($file->woo_permission) && $file->woo_permission === self::ONLY_WOO_PERMISSION)) {
                wpfd_get_template('html-access-denied.php');
                exit();
            }
        }
    }

    /**
     * Locate addon template
     *
     * @param string $template      Template path
     * @param string $template_name Template name
     *
     * @return string
     */
    public function wpfdaLocateTemplate($template, $template_name)
    {
        if (file_exists($template)) {
            return $template;
        }
        $wooAddonTemplatePath = WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/Woocommerce/templates/';
        $templates_name       = scandir($wooAddonTemplatePath);

        if (in_array($template_name, $templates_name)) {
            return $wooAddonTemplatePath . $template_name;
        }
    }

    /**
     * WooCommerce Product File Filter
     *
     * @param mixed      $file        Downloadable file
     * @param WC_Product $product     WooCommerce product instance
     * @param integer    $download_id Download id
     *
     * @return mixed
     */
    public function woocommerceProductFile($file, $product, $download_id)
    {
        $wpfdFiles = $product->get_meta('_wpfd_wc_files');
        if ($wpfdFiles === '') {
            return $file;
        }
        if ('' === $download_id) {
            $file = count($wpfdFiles) ? current($wpfdFiles) : false;
        } elseif (isset($files[$download_id])) {
            $file = $wpfdFiles[$download_id];
        } else {
            $file = false;
        }

        return $file;
    }

    /**
     * Variations Methods
     *
     * @param string  $variation_id Product variant product
     * @param integer $i            Product loop id
     *
     * @return void
     */
    public function saveVariantProduct($variation_id, $i = null)
    {
        // Text Field
        $_wpfd_wc_files_name   = Utilities::getInput('_wpfd_wc_files_name', 'POST', 'none');
        $_wpfd_wc_files_id     = Utilities::getInput('_wpfd_wc_files_id', 'POST', 'none');
        $_wpfd_wc_files_catid  = Utilities::getInput('_wpfd_wc_files_catid', 'POST', 'none');
        $_wpfd_download_limit  = Utilities::getInput('variable_wpfd_download_limit', 'POST', 'none');
        $_wpfd_download_expiry = Utilities::getInput('variable_wpfd_download_expiry', 'POST', 'none');
        $_wpfd_wc_files_name   = isset($_wpfd_wc_files_name[$variation_id]) ? $_wpfd_wc_files_name[$variation_id] : false;
        $_wpfd_wc_files_id     = isset($_wpfd_wc_files_id[$variation_id]) ? $_wpfd_wc_files_id[$variation_id] : false;
        $_wpfd_wc_files_catid  = isset($_wpfd_wc_files_catid[$variation_id]) ? $_wpfd_wc_files_catid[$variation_id] : false;
        $_wpfd_download_limit  = current($_wpfd_download_limit);
        $_wpfd_download_expiry = current($_wpfd_download_expiry);

        if (!empty($_wpfd_wc_files_name) && !empty($_wpfd_wc_files_id) && !empty($_wpfd_wc_files_catid)) {
            $files = $this->wpfdaPrepareDownloads($_wpfd_wc_files_name, $_wpfd_wc_files_id, $_wpfd_wc_files_catid);

            // Unlink removed files from product
            $this->wpfdaUnlinkRemovedProductFromFile($_wpfd_wc_files_id, $variation_id);
            // Connect file to product
            update_post_meta($variation_id, '_wpfd_wc_files', $files);
            foreach ($files as $file) {
                $this->wpfdaLinkProductToFile($file['id'], $file['catid'], $variation_id);
            }
            update_post_meta($variation_id, '_wpfd_download_limit', $_wpfd_download_limit);
            update_post_meta($variation_id, '_wpfd_download_expiry', $_wpfd_download_expiry);
        } else { // Empty files
            $this->wpfdaUnlinkAllFiles($variation_id);
            delete_post_meta($variation_id, '_wpfd_wc_files');
            delete_post_meta($variation_id, '_wpfd_download_limit');
            delete_post_meta($variation_id, '_wpfd_download_expiry');
        }
    }

    /**
     * Inject production creation modal
     *
     * @param object $view Current view instance
     *
     * @return void
     */
    public function wpfdAddProductCreationForm($view)
    {
        add_thickbox();
        wpfd_get_template('html-product-creation.php');
    }

    /**
     * Inject product creation menu
     *
     * @param object $view Current view instance
     *
     * @return void
     */
    public function wpfdAddFileContextMenu($view)
    {
        ?>
        <li data-custom-action="file.create_product">
            <span class="dashicons dashicons-archive"></span>
            <span><?php esc_html_e('Create Woo Product', 'wpfdAddon'); ?></span>
        </li>
        <?php
    }

    /**
     * AJAX: Create product ajax
     *
     * @return void
     */
    public function create_product() // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- ajax method OK
    {
        $type = Utilities::getInput('product_type', 'POST', 'string');
        $title = Utilities::getInput('product_title', 'POST', 'string');
        $sku = Utilities::getInput('product_sku', 'POST', 'string');
        $price = Utilities::getInt('product_price', 'POST');
        $category_id = Utilities::getInt('product_category', 'POST');
        $featured_file_id = Utilities::getInput('product_featured_image', 'POST', 'none');
        $gallery_file_ids = Utilities::getInput('product_gallery_images', 'POST', 'none');

        if (!empty($gallery_file_ids)) {
            $gallery_file_ids = array_map('trim', explode(',', $gallery_file_ids));
        }

        $_wpfd_wc_files_id = Utilities::getInput('product_files', 'POST', 'none');
        $_wpfd_wc_files_name = Utilities::getInput('product_titles', 'POST', 'string');
        $_wpfd_wc_files_catid = Utilities::getInput('product_catids', 'POST', 'string');
        $product_cat_cloud_types = explode('|', Utilities::getInput('product_cat_cloud_types', 'POST', 'string'));
        $download_limit = Utilities::getInput('product_download_limit', 'POST', 'none');
        $download_expiry = Utilities::getInput('product_download_expiry', 'POST', 'none');
        $description = Utilities::getInput('product_description', 'POST', 'none');
        $fileObjects = Utilities::getInput('product_file_objects', 'POST', 'none');
        $fileObjects = explode('|', $fileObjects);
        $_wpfd_wc_files_id = explode('|', $_wpfd_wc_files_id);
        $_wpfd_wc_files_name = explode('|', $_wpfd_wc_files_name);
        $_wpfd_wc_files_catid = explode('|', $_wpfd_wc_files_catid);
        $new_wpfd_wc_files_id = array();
        $wooFileStructures = array();

        if (isset($type) && $type === 'one_product_per_file') {
            foreach ($_wpfd_wc_files_id as $key => $value) {
                if ($featured_file_id === $value && $product_cat_cloud_types[$key] === 'aws') {
                    $featured_file_id = rawurldecode($featured_file_id);
                }

                if (!empty($gallery_file_ids)) {
                    $k = array_search($value, $gallery_file_ids);
                    if ($k !== false && $product_cat_cloud_types[$key] === 'aws') {
                        $gallery_file_ids[$k] = rawurldecode($value);
                    }
                }

                if ($product_cat_cloud_types[$key] === 'aws') {
                    $new_wpfd_wc_files_id[] = rawurldecode($value);
                } else {
                    $new_wpfd_wc_files_id[] = $value;
                }
            }

            // Match correct file object for creating
            if (!empty($fileObjects)) {
                foreach ($fileObjects as $object) {
                    $newObjects = explode(',', $object);
                    $wooFileStructures[$newObjects[0]] = $newObjects[1];
                }
            }

            $newProductId = null;
            $wooSku = array();
            $errors = array();
            $inserted = array();
            $invalids = array();

            try {
                foreach ($new_wpfd_wc_files_id as $key => $val) {
                    $fileTitle  = (!empty($wooFileStructures) && isset($wooFileStructures[$val])) ? $wooFileStructures[$val] : '';
                    $categoryId = (!empty($_wpfd_wc_files_catid) && isset($_wpfd_wc_files_catid[$key])) ? $_wpfd_wc_files_catid[$key] : $_wpfd_wc_files_catid[0];
                    $prefix     = !empty($sku) ? '_' : '';
                    $newSku     = $sku . $prefix . str_replace(' ', '_', $fileTitle);

                    if (in_array($newSku, $wooSku)) {
                        $newSku = $newSku . '_' . $val;
                    }

                    $_wpfd_wc_files_id    = array('0' => $val);
                    $_wpfd_wc_files_name  = array('0' => $fileTitle);
                    $_wpfd_wc_files_catid = array('0' => $categoryId);

                    $datas = array();
                    $datas['title'] = $fileTitle;
                    $datas['sku'] = $newSku;
                    $datas['price'] = $price;
                    $datas['category_id'] = $category_id;
                    $datas['download_limit'] = $download_limit;
                    $datas['download_expiry'] = $download_expiry;
                    $datas['description'] = $description;
                    $datas['product_cat_cloud_types'] = $product_cat_cloud_types;
                    $datas['featured_file_id'] = $featured_file_id;
                    $datas['gallery_file_ids'] = $gallery_file_ids;
                    $datas['_wpfd_wc_files_id'] = $_wpfd_wc_files_id;
                    $datas['_wpfd_wc_files_name'] = $_wpfd_wc_files_name;
                    $datas['_wpfd_wc_files_catid'] = $_wpfd_wc_files_catid;

                    $createdWooCommerceProduct = $this->wpfdCreateWooCommerceProductFromFiles($datas);
                    $wooSku[] = $newSku;

                    if (is_array($createdWooCommerceProduct) && isset($createdWooCommerceProduct['product_id']) && !is_null($createdWooCommerceProduct['product_id'])) {
                        $inserted[] = $createdWooCommerceProduct;
                    } else {
                        $invalids[] = $datas;
                    }

                    if (is_array($createdWooCommerceProduct) && isset($createdWooCommerceProduct['errors']) && is_array($createdWooCommerceProduct['errors'])) {
                        $errors = array_merge($errors, $createdWooCommerceProduct['errors']);
                    }

                    if (intval($key) === (count($new_wpfd_wc_files_id) - 1) && is_array($createdWooCommerceProduct) && isset($createdWooCommerceProduct['product_id'])) {
                        $newProductId = $createdWooCommerceProduct['product_id'];
                    }
                }

                // Log for debugging
                self::log('Invalid files:');
                self::log(json_encode($invalids));
                self::log('Messages on WooCommerce: ');
                self::log(json_encode($errors));

                if (!is_null($newProductId) || !empty($inserted)) {
                    $defaultProductId = (is_array($inserted) && isset($inserted[0]['product_id'])) ? $inserted[0]['product_id'] : 0;
                    $newProductId = !is_null($newProductId) ? $newProductId : $defaultProductId;
//                    wp_send_json_success(array('product_id' => $newProductId, 'edit_link' => get_edit_post_link($newProductId), 'permalink' => get_permalink($newProductId), 'errors' => $errors));
                    wp_send_json_success(array('product_id' => $newProductId, 'edit_link' => get_edit_post_link($newProductId), 'permalink' => get_permalink($newProductId), 'errors' => ''));
                } else {
                    wp_send_json_error(array('message' => __('Failed to create WooCommerce products.', 'wpfdAddon')));
                }
            } catch (\Exceptions $e) {
                wp_send_json_error(array('message' => $e->getMessage()));
            }
        } else {
            $datas = array();
            $datas['title'] = $title;
            $datas['sku'] = $sku;
            $datas['price'] = $price;
            $datas['category_id'] = $category_id;
            $datas['download_limit'] = $download_limit;
            $datas['download_expiry'] = $download_expiry;
            $datas['description'] = $description;
            $datas['_wpfd_wc_files_id'] = $_wpfd_wc_files_id;
            $datas['_wpfd_wc_files_name'] = $_wpfd_wc_files_name;
            $datas['_wpfd_wc_files_catid'] = $_wpfd_wc_files_catid;
            $datas['product_cat_cloud_types'] = $product_cat_cloud_types;
            $datas['featured_file_id'] = $featured_file_id;
            $datas['gallery_file_ids'] = $gallery_file_ids;

            $createdWooCommerceProduct = $this->wpfdCreateWooCommerceProductFromFiles($datas);

            if (is_array($createdWooCommerceProduct) && isset($createdWooCommerceProduct['product_id']) && !is_null($createdWooCommerceProduct['product_id'])) {
                wp_send_json_success(array('product_id' => $createdWooCommerceProduct['product_id'], 'edit_link' => $createdWooCommerceProduct['edit_link'], 'permalink' => $createdWooCommerceProduct['permalink'], 'errors' => $createdWooCommerceProduct['errors']));
            } elseif (is_array($createdWooCommerceProduct) && isset($createdWooCommerceProduct['errors']) && is_array($createdWooCommerceProduct['errors'])) {
                wp_send_json_error(array('message' => $createdWooCommerceProduct['errors'][0]));
            } else {
                wp_send_json_error(array('message' => __('Failed to create WooCommerce product.', 'wpfdAddon')));
            }
        }

        wp_send_json_error();
    }

    /**
     * Prepare product images
     *
     * @param array|string $ids               File id(s)
     * @param array        $_wpfd_wc_files_id List wpfd file id
     * @param array        $current_cat_ids   List current cat id
     *
     * @throws Exception Fire on errors
     *
     * @return boolean|array
     */
    private function prepareProductImage($ids, $_wpfd_wc_files_id = array(), $current_cat_ids = array())
    {
        if (!is_array($ids) && !empty($ids)) {
            $ids = array($ids);
        }

        if (empty($ids)) {
            return false;
        }

        $wpUploadDir = wp_upload_dir();
        $attachmentIds = array();

        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $needResizeOrWatermark = false;
                Application::getInstance('Wpfd');
                $fileModel = Model::getInstance('filefront');
                /* @var WpfdModelFilefront $fileModel */
                $file = $fileModel->getFullFile($id);
                // Check thumbnail exists for this file
                $filePreviewPath = get_post_meta($id, '_wpfd_preview_file_path', true);

                if (!empty($filePreviewPath)) {
                    $sysfile = WP_CONTENT_DIR . $filePreviewPath;
                } else {
                    $sysfile = WpfdBase::getFilesPath($file->catid) . $file->file;
                    $needResizeOrWatermark = true;
                }

                if (!in_array($file->ext, array('jpg', 'jpeg', 'png'))) {
                    continue;
                }
                if (!file_exists($sysfile)) {
                    continue;
                }
                // Resize $sysfile
                if ($needResizeOrWatermark) {
                    $sysfile = $this->resizeImage($sysfile);
                }
                // Watermark
                if (WpfdHelperFile::isWatermarkEnabled() && $needResizeOrWatermark) {
                    WpfdAddonWatermark::watermark($sysfile);
                }
                $ext = $file->ext;
                $fileTitle = $file->title;
            } else {
                // Cloud files
                $previewInfo = get_option('_wpfdAddon_preview_info_' . $id, false);
                if (!empty($previewInfo) && isset($previewInfo['path']) && file_exists($previewInfo['path'])) {
                    $sysfile = $previewInfo['path'];
                    $ext = 'png';
                } else {
                    $key = array_search($id, $_wpfd_wc_files_id);
                    if ($key !== false) {
                        $current_cat_id = $current_cat_ids[$key];
                    } else {
                        $current_cat_id = $current_cat_ids[0];
                    }
                    $categoryFrom = apply_filters('wpfdAddonCategoryFrom', $current_cat_id);
                    $previewPath  = WpfdHelperFolder::getPreviewsPath();
                    $res = false;
                    switch ($categoryFrom) {
                        case 'onedrive':
                            if (!class_exists('WpfdAddonOneDrive')) {
                                require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonOneDrive.php';
                            }

                            $oneDrive = new \WpfdAddonOneDrive;
                            $res = $oneDrive->getOneDriveImg($id, $previewPath);

                            if ($res) {
                                $res = WP_CONTENT_DIR . $res;
                            }
                            break;
                        case 'onedrive_business':
                            if (!class_exists('WpfdAddonOneDriveBusiness')) {
                                require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonOneDriveBusiness.php';
                            }

                            $oneDrive = new \WpfdAddonOneDriveBusiness;
                            $res = $oneDrive->getOneDriveBusinessImg($id, $previewPath);

                            if ($res) {
                                $res = WP_CONTENT_DIR . $res;
                            }
                            break;
                        case 'dropbox':
                            if (!class_exists('WpfdAddonDropbox')) {
                                require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonDropbox.php';
                            }

                            $dropbox = new \WpfdAddonDropbox;
                            $res = $dropbox->getDropboxImg($id, $previewPath);

                            if ($res) {
                                $res = WP_CONTENT_DIR . $res;
                            }
                            break;
                        case 'googleDrive':
                            if (!class_exists('WpfdAddonGoogleDrive')) {
                                require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonGoogle.php';
                            }

                            $googleDrive = new \WpfdAddonGoogleDrive;
                            $res = $googleDrive->getGooglePreviewImg($id, $previewPath);

                            if ($res) {
                                $res = WP_CONTENT_DIR . $res;
                            }
                            break;
                        case 'googleTeamDrive':
                            break;
                        case 'aws':
                            if (!class_exists('WpfdAddonAws')) {
                                require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonAws.php';
                            }

                            $aws = new \WpfdAddonAws;
                            $res = $aws->getAwsImg($id, $previewPath);

                            if ($res) {
                                $res = WP_CONTENT_DIR . $res;
                            }
                            break;
                        default:
                            break;
                    }

                    if ($res !== false) {
                        $sysfile = $res;
                        $ext = 'png';
                    }
                }
                $fileTitle = $id;
            }

            if (isset($sysfile) && !empty($fileTitle) && !empty($ext)) {
                $fileName = sanitize_file_name($fileTitle) . '.' . $ext;
                $mimeType = WpfdHelperFile::mimeType($ext);
                $i = 1;
                $newFilePath = $wpUploadDir['path'] . DIRECTORY_SEPARATOR . $fileName;

                while (file_exists($newFilePath)) {
                    $i++;
                    $newFilePath = $wpUploadDir['path'] . DIRECTORY_SEPARATOR . $i . '_' . $fileName;
                }

                if (WpfdHelperFolder::copy($sysfile, $newFilePath)) {
                    $attachmentId = wp_insert_attachment(array(
                        'guid' => $newFilePath,
                        'post_mime_type' => $mimeType,
                        'post_title' => preg_replace('/\.[^.]+$/', '', $fileTitle),
                        'post_content' => '',
                        'post_status' => 'inherit'
                    ), $newFilePath);

                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata($attachmentId, $newFilePath));
                    $attachmentIds[] = $attachmentId;
                }
            }
        }

        return $attachmentIds;
    }

    /**
     * Resize image
     *
     * @param string $filePath File path need to resize
     *
     * @return false|mixed|string
     */
    public function resizeImage($filePath)
    {
        // Try resize first
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = WpfdHelperFile::mimeType($ext);
        $editor = wp_get_image_editor($filePath, array('mime_type' => $mimeType));

        if (is_wp_error($editor)) {
            return $filePath;
        }

        $quality = $editor->set_quality(apply_filters('wpfd_preview_image_quantity', 90));

        if (!$quality !== true) {
            return $filePath;
        }

        $originSize = $editor->get_size();
        $imageSize = apply_filters('wpfd_preview_image_size', array('w' => 800, 'h' => 800));
        if (empty($originSize) || ($originSize['width'] < $imageSize['w'] && $originSize['height'] <= $imageSize['h'])) {
            // Copy and save
            return $filePath;
        }

        // Do resize
        $resized = $editor->resize($imageSize['w'], $imageSize['h'], false);

        if (is_wp_error($resized)) {
            return $filePath;
        }

        $tempFilePath = tempnam(sys_get_temp_dir(), 'wpfd_');

        if (false === $tempFilePath) {
            return $filePath;
        }

        $saved = $editor->save($tempFilePath);

        if (is_wp_error($saved)) {
            return $filePath;
        }

        return $tempFilePath;
    }

    /**
     * Create WooCommerce product from files
     *
     * @param array $args Product params
     *
     * @throws Exception Fire if errors
     *
     * @return array|mixed
     */
    public function wpfdCreateWooCommerceProductFromFiles($args = array())
    {
        $title                   = isset($args['title']) ? $args['title'] : '';
        $sku                     = isset($args['sku']) ? $args['sku'] : '';
        $price                   = isset($args['price']) ? $args['price'] : '';
        $category_id             = isset($args['category_id']) ? $args['category_id'] : 0;
        $download_limit          = isset($args['download_limit']) ? $args['download_limit'] : '';
        $download_expiry         = isset($args['download_expiry']) ? $args['download_expiry'] : '';
        $description             = isset($args['description']) ? $args['description'] : '';
        $_wpfd_wc_files_id       = isset($args['_wpfd_wc_files_id']) ? $args['_wpfd_wc_files_id'] : array();
        $_wpfd_wc_files_name     = isset($args['_wpfd_wc_files_name']) ? $args['_wpfd_wc_files_name'] : array();
        $_wpfd_wc_files_catid    = isset($args['_wpfd_wc_files_catid']) ? $args['_wpfd_wc_files_catid'] : array();
        $product_cat_cloud_types = isset($args['product_cat_cloud_types']) ? $args['product_cat_cloud_types'] : array();
        $featured_file_id        = isset($args['featured_file_id']) ? $args['featured_file_id'] : '';
        $gallery_file_ids        = isset($args['gallery_file_ids']) ? $args['gallery_file_ids'] : array();

        $results = array(
                'product_id' => null,
                'edit_link' => '#',
                'permalink' => '#',
                'errors' => array()
        );

        foreach ($_wpfd_wc_files_id as $key => $value) {
            if ($featured_file_id === $value && $product_cat_cloud_types[$key] === 'aws') {
                $featured_file_id = rawurldecode($featured_file_id);
            }

            if (!empty($gallery_file_ids)) {
                $k = array_search($value, $gallery_file_ids);
                if ($k !== false && $product_cat_cloud_types[$key] === 'aws') {
                    $gallery_file_ids[$k] = rawurldecode($value);
                }
            }

            if ($product_cat_cloud_types[$key] === 'aws') {
                $new_wpfd_wc_files_id[] = rawurldecode($value);
            } else {
                $new_wpfd_wc_files_id[] = $value;
            }
        }

        /**
         * Filter to modification unique WooCommerce SKU values
         *
         * @param string
         */
        $sku = apply_filters('wpfd_Change_WooCommerce_Product_Sku', $sku);
        $_wpfd_wc_files_id = $new_wpfd_wc_files_id;
        try {
            $product = new WC_Product_Simple();
            $product->set_status('publish');
            $product->set_name($title);
            $product->set_sku($sku);
            $product->set_regular_price($price);
            $product->set_category_ids(explode(',', $category_id));
            $product->set_downloadable('true');

            if (!empty($description)) {
                $product->set_description($description);
            }

            $productId = $product->save();
        } catch (WC_Data_Exception $e) {
            if ($e->getErrorCode() === 'product_invalid_sku') {
                $results['errors'][] = $title . __(', Invalid SKU. Please provide a unique SKU.', 'wpfdAddon');
                self::log($title . ', Invalid SKU. Please provide a unique SKU.');
                self::log(json_encode($args));
            } else {
                $results['errors'][] = $title . ', ' . $e->getMessage();
                self::log($title . ', ' . $e->getMessage());
                self::log(json_encode($args));
            }
        } catch (\Exceptions $e) {
            $results['errors'][] = $title . ', ' . $e->getMessage();
            self::log($title . ', ' . $e->getMessage());
            self::log(json_encode($args));
        }

        // Save product ids
        $_wpfd_download_limit  = strval($download_limit) !== '' ? $download_limit : apply_filters('wpfd_quick_create_product_download_limit', '');
        $_wpfd_download_expiry = strval($download_expiry) !== '' ? $download_expiry : apply_filters('wpfd_quick_create_product_download_expiry', '');

        if (!empty($_wpfd_wc_files_name) && !empty($_wpfd_wc_files_id) && !empty($_wpfd_wc_files_catid)) {
            $files = $this->wpfdaPrepareDownloads($_wpfd_wc_files_name, $_wpfd_wc_files_id, $_wpfd_wc_files_catid);

            // Unlink removed files from product
            $this->wpfdaUnlinkRemovedProductFromFile($_wpfd_wc_files_id, $productId);
            // Connect file to product
            update_post_meta($productId, '_wpfd_wc_files', $files);
            foreach ($files as $file) {
                $this->wpfdaLinkProductToFile($file['id'], $file['catid'], $productId);
            }
            update_post_meta($productId, '_wpfd_download_limit', $_wpfd_download_limit);
            update_post_meta($productId, '_wpfd_download_expiry', $_wpfd_download_expiry);
        } else { // Empty files
            $this->wpfdaUnlinkAllFiles($productId);
            delete_post_meta($productId, '_wpfd_wc_files');
            delete_post_meta($productId, '_wpfd_download_limit');
            delete_post_meta($productId, '_wpfd_download_expiry');
        }

        if ($productId) {
            $errors = [];
            // Attach the image
            if (!empty($featured_file_id)) {
                $featuredAttachmentIds = $this->prepareProductImage($featured_file_id, $_wpfd_wc_files_id, $_wpfd_wc_files_catid);
                if (!empty($featuredAttachmentIds)) {
                    $featuredAttachmentId = $featuredAttachmentIds[0];
                    set_post_thumbnail($productId, $featuredAttachmentId);
                } else {
                    $errors[] = __('Featured image creation failed!', 'wpfdAddon');
                }
            }

            // Attach gallery images
            if (!empty($gallery_file_ids)) {
                $galleryAttachmentIds = $this->prepareProductImage($gallery_file_ids, $_wpfd_wc_files_id, $_wpfd_wc_files_catid);
                if (!empty($galleryAttachmentIds)) {
                    update_post_meta($productId, '_product_image_gallery', implode(',', $galleryAttachmentIds));
                } else {
                    $errors[] = __('Gallery images creation failed!', 'wpfdAddon');
                }
            }

            $results['product_id'] = $productId;
            $results['edit_link']  = get_edit_post_link($productId);
            $results['permalink']  = get_permalink($productId);
            $results['errors']     = $errors;
        }

        return $results;
    }

    /**
     * Log into a debug file
     *
     * @param string $msg Message
     *
     * @return void
     */
    public static function log($msg = '')
    {
        if (defined('WPFD_DEBUG') && WPFD_DEBUG && self::$debug === true) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log if enable debug
            error_log($msg);
        }
    }
}
