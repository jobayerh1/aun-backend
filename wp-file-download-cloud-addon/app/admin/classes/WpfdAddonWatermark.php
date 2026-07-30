<?php
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Form;
use Joomunited\WPFramework\v1_0_6\Utilities;

/**
 * Class WpfdAddonWatermark
 */
class WpfdAddonWatermark
{
    const WM_TYPE_PRODUCT = 'product';
    const WM_TYPE_ALL = 'all';

    /**
     * Enable debuging
     *
     * @var boolean
     */
    protected static $debug = false;

    /**
     * Support image type
     *
     * @var string[]
     */
    protected static $imageExt = array('jpg', 'jpeg', 'png', 'gif');

    /**
     * Constructor
     */
    public function __construct()
    {
        if (!class_exists('WpfdHelperFolder')) {
            require_once WPFD_PLUGIN_DIR_PATH . 'app/admin/helpers/WpfdHelperFolder.php';
        }

        $this->init();
        $this->generatorHooks();
    }

    /**
     * Initial
     *
     * @return void
     */
    public function init()
    {
        add_filter('wpfd_admin_ui_configuration_menu_get_items', function ($items) {
            $items['woocommerce-addon'] = array(
                esc_html__('WooCommerce', 'wpfdAddon'),
                '',
                54,
                WPFDA_PLUGIN_DIR_URL . 'app/admin/assets/images/icon-woocommerce-addon.svg',
            );

            return $items;
        });
        add_action('wpfd_admin_ui_configuration_content', function () {
            // woocommerce-addon
            $name = 'woocommerce-addon';
            $html = '';
            ob_start();
            $this->displayWatermarkConfigurationContent(); // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewClosure.ThisFound -- It's OK.
            $watermarkHtml = ob_get_contents();
            ob_end_clean();

            ob_start();
            $this->displayWooCommerceConfigurationContent(); // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewClosure.ThisFound -- It's OK.
            $woocommerceHtml = ob_get_contents();
            ob_end_clean();

            $tabs = array(
                'watermark' => $watermarkHtml,
                'eshop' => $woocommerceHtml,
            );
            $html .= wpfd_admin_ui_configuration_build_tabs($tabs);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- print html content
            echo wpfd_admin_ui_configuration_build_content($name, $html);
        }, 30);

        add_filter('wpfd_addon_watermark_get_files_linked', array(__CLASS__, 'getFilesLinked'));
    }

    /**
     * Show woocommerce configuration content
     *
     * @return void
     */
    public function displayWooCommerceConfigurationContent()
    {
        Application::getInstance('WpfdAddon');
        /* @var WpfdAddonModelConfig $modelConf */
        $modelConf = Model::getInstance('config');
        $file_form = new Form();
        $formFile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
        $formFile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'woocommerce_config.xml';
        $configform = null;
        if ($file_form->load($formFile, $modelConf->getWoocommerceConfig())) {
            $configform = $file_form->render();
        }
        echo '<div id="wpfd-woocommerce-addon" class="tab-pane ">';
        echo $configform; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- It's OK.
        echo '</div>';
    }

    /**
     * Show watermark configuration content
     *
     * @return void
     */
    public function displayWatermarkConfigurationContent()
    {
        Application::getInstance('WpfdAddon');
        /* @var WpfdAddonModelConfig $modelConf */
        $modelConf = Model::getInstance('config');
        $file_form = new Form();
        $formFile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
        $formFile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'watermark_config.xml';
        $configform = null;
        if ($file_form->load($formFile, $modelConf->getWatermarkConfig())) {
            $configform = $file_form->render();
        }
        echo '<div id="wpfd-watermark-addon" class="tab-pane ">';
        echo $configform; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- It's OK.
        echo '</div>';
    }

    /**
     * Add hooks
     *
     * @return void
     */
    public function generatorHooks()
    {
        // Ajax
        add_action('wp_ajax_watermark_init', array($this, 'ajaxWatermarkInit'));

        if (!self::enabled()) {
            return;
        }

        $type = self::getWatermarkType();
        $fileIds = null;
        if ($type === self::WM_TYPE_PRODUCT) {
            // Filter product only
            $fileIds = self::getFilesLinked();
        }
        add_action('wpfd_before_generator_restart', array($this, 'pruneWatermark'));

        add_filter('wpfd_preview_generated_path', function ($previewPath, $queue, $generator) use ($type, $fileIds) {
            if ($type === self::WM_TYPE_PRODUCT) { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewClosure.ClassRefFound -- It's OK.
                if (!is_null($fileIds) && isset($queue['file_id']) && in_array($queue['file_id'], $fileIds)) {
                    return self::apply($previewPath); // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewClosure.ClassRefFound -- It's OK.
                }
            } else {
                return self::apply($previewPath); // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewClosure.ClassRefFound -- It's OK.
            }

            return $previewPath;
        }, 10, 3);
        add_filter('wpfd_addon_preview_generator_blacklist_extensions', function ($allowed) {
            return array_diff($allowed, self::$imageExt); // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewClosure.ClassRefFound -- It's OK.
        }, 10);
        add_action('wp_ajax_watermark_fileinfo', array($this, 'ajaxWatermarkFileInfo'));
        add_action('wp_ajax_watermark_prune', array($this, 'ajaxWatermarkPrune'));
        add_action('wp_ajax_watermark_exec', array($this, 'ajaxWatermarkExec'));

        add_action('wpfd_before_download_file', array($this, 'beforeDownload'), 5, 2);
        add_action('wpfd_before_cloud_download_file', array($this, 'beforeCloudDownload'), 5, 3);
    }

    /**
     * AJAX: Init watermark processor
     *
     * @return void
     */
    public function ajaxWatermarkInit()
    {
        if (!self::enabled()) {
            wp_send_json_error(array('content' => __('Watermark was disabled, please enable it before regenerate them!', 'wpfdAddon')));
        }

        $type = self::getWatermarkType();

        self::log(__METHOD__ . ': Type ' . $type);

        switch ($type) {
            case self::WM_TYPE_ALL:
                $ids = self::getAllFiles();
                $total = count($ids);
                break;
            case self::WM_TYPE_PRODUCT:
                $ids = self::getFilesLinked();
                $total = count($ids);
                break;
            default:
                break;
        }

        if (isset($total)) {
            wp_send_json_success(array('content' => sprintf(_n('Found %d file will be add watermark. Do you want to process?', 'Found %d files will be add watermark. Do you want to process?', $total, 'wpfdAddon'), $total), 'ids' => $ids, 'total' => $total));
        }

        wp_send_json_error(array('content' => esc_html__('Something went wrong! Make sure your watermark configuration are set!', 'wpfdAddon')));
    }

    /**
     * AJAX: Prune Watermark
     *
     * @return void
     */
    public function ajaxWatermarkPrune()
    {
        if (!self::enabled()) {
            wp_send_json_error(array('content' => __('Watermark was disabled, please enable it before regenerate them!', 'wpfdAddon')));
        }

        $deleted = $this->pruneWatermark(true);

        wp_send_json_success(array('content' => _n('%d watermark file deleted!', '%d watermark files deleted!', $deleted, 'wpfdAddon')));
    }

    /**
     * AJAX: Get file info before add watermark
     *
     * @return void
     */
    public function ajaxWatermarkFileInfo()
    {
        // Prepare file data before apply watermark
        $fileId = Utilities::getInput('file_id', 'POST', 'string');

        if (!$fileId) {
            wp_send_json_error();
        }
        // Check is cloud path
        if (!is_numeric($fileId) && file_exists($fileId)) {
            wp_send_json_success(array(
                'content' => sprintf(__('Wartermaking for: %s...', 'wpfdAddon'), WpfdHelperFolder::getBaseName($fileId))
            ));
        }

        $file = get_post($fileId);
        if (!$file) {
            wp_send_json_error(array(
                'content' => __('File not exists...', 'wpfdAddon')
            ));
        }
        wp_send_json_success(array(
            'content' => sprintf(__('Wartermaking for: %s...', 'wpfdAddon'), $file->post_title)
        ));
    }

    /**
     * Execution add watermark
     *
     * @return void
     */
    public function ajaxWatermarkExec()
    {
        $fileId = Utilities::getInput('file_id', 'POST', 'string');

        if (!$fileId) {
            wp_send_json_error();
        }
        // Made sure Watermark is enabled
        if (!self::enabled()) {
            wp_send_json_error(array('content' => __('Watermark disabled!', 'wpfdAddon')));
        }
        if (is_numeric($fileId)) {
            $fileMeta = get_post_meta($fileId, '_wpfd_file_metadata', true);
            $ext = isset($fileMeta['ext']) ? $fileMeta['ext'] : false;

            if (!$ext) {
                wp_send_json_error(array('content' => __('File extension not supported!', 'wpfdAddon')));
            }
        }
        self::log('Initial for ' . $fileId);
        // Check generate preview enabled?
        if (self::generatorEnabled()) {
            if (is_numeric($fileId)) {
                // Yes: Use previewed image to add watermark
                $previewPath = get_post_meta($fileId, '_wpfd_preview_file_path', true);
                self::log(__METHOD__ . ': Use previewed image to add watermark: ' . $previewPath);
                if (!empty($previewPath)) {
                    if (strpos($previewPath, 'wp-content') === false) {
                        $previewPath = WP_CONTENT_DIR . $previewPath;
                    }
                    if (strpos($previewPath, WpfdHelperFolder::getWatermarkPath()) !== false) {
                        $previewPath = str_replace(WpfdHelperFolder::getWatermarkPath(), WpfdHelperFolder::getPreviewsPath(), $previewPath);
                    }
                    if (file_exists($previewPath)) {
                        $watermarkedPath = self::apply($previewPath);
                        $watermarkedPath = str_replace(WP_CONTENT_DIR, '', $watermarkedPath);
                        update_post_meta($fileId, '_wpfd_preview_file_path', $watermarkedPath);

                        wp_send_json_success(array('path' => $watermarkedPath));
                    }
                }
            } else {
                // Maybe cloud file
                $clouds = wpfd_get_support_cloud();
                $isPath = false;
                $cloudType = '';
                foreach ($clouds as $cloud) {
                    if (strpos($fileId, $cloud) !== false) {
                        $isPath = true;
                        $cloudType = $cloud;
                        break;
                    }
                }
                if ($isPath) { // $fileId is full path
                    if (file_exists($fileId)) {
                        preg_match('/'.$cloudType.'_([a-z0-9]{32})/', $fileId, $match);
                        $cloudFileId = isset($match[1]) ? $match[1] : false;
                        if (false !== $cloudFileId) {
                            $watermarkedPath = self::apply($fileId);
                            $watermarkedPath = str_replace(WP_CONTENT_DIR, '', $watermarkedPath);
                            $previewInfo = get_option('_wpfdAddon_preview_info_' . $cloudFileId, false);
                            if (false === $previewInfo) {
                                $previewInfo = array(
                                    'updated' => current_datetime()->format('Y-m-d\TH:i:s\Z'),
                                    'path' => $watermarkedPath
                                );
                            }
                            // Backup option for cloud
                            update_option('_wpfdAddon_preview_info_bak_' . $cloudFileId, $previewInfo);
                            // Update current preview path to cloud
                            $previewInfo['path'] = $watermarkedPath;
                            update_option('_wpfdAddon_preview_info_' . $cloudFileId, $previewInfo);

                            wp_send_json_success(array('path' => $watermarkedPath));
                        }
                    }
                } else {
                    $previewInfo = get_option('_wpfdAddon_preview_info_' . md5($fileId), false);
                    self::log('[CLOUD] Preview info: ' . var_export($previewInfo, true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- It's OK
                    $previewPath = isset($previewInfo['path']) ? $previewInfo['path'] : '';
                    if (!empty($previewPath)) {
                        if (strpos($previewPath, 'wp-content') === false && !empty($previewPath)) {
                            $previewPath = WP_CONTENT_DIR . $previewPath;
                        }
                        if (strpos($previewPath, WpfdHelperFolder::getWatermarkPath()) !== false) {
                            $previewPath = str_replace(WpfdHelperFolder::getWatermarkPath(), WpfdHelperFolder::getPreviewsPath(), $previewPath);
                        }
                        if (file_exists($previewPath)) {
                            $watermarkedPath = self::apply($previewPath);
                            $watermarkedPath = str_replace(WP_CONTENT_DIR, '', $watermarkedPath);
                            // Backup option for cloud
                            update_option('_wpfdAddon_preview_info_bak_' . md5($fileId), $previewInfo);
                            // Update current preview path to cloud
                            $previewInfo['path'] = $watermarkedPath;
                            update_option('_wpfdAddon_preview_info_' . md5($fileId), $previewInfo);

                            wp_send_json_success(array('path' => $watermarkedPath));
                        }
                    }
                }
            }

            wp_send_json_error(array('content' => __('Some watermark couldn\'t be generated, make sure the JoomUnited previewer server is properly activated in the plugin Main settings > Frontend.', 'wpfdAddon')));
        }

        // No: Resize image files then watermark it
        if (!in_array(strtolower($ext), self::$imageExt)) {
            wp_send_json_error(array('content' => __('File extension not support', 'wpfdAddon')));
        }
        // Get category id
        $term_list = wp_get_post_terms($fileId, 'wpfd-category', array('fields' => 'ids'));
        if (!is_wp_error($term_list) && count($term_list) > 0) {
            $catId = $term_list[0];
        }
        if (!isset($catId)) {
            wp_send_json_error(array('content' => __('File category not found!', 'wpfdAddon')));
        }
        $filePath = WpfdBase::getFilesPath($catId) . $fileMeta['file'];
        $previewPath = WpfdHelperFolder::getPreviewsPath();

        $previewFileName = $previewPath . strval($fileId) . '_' . strval(md5($filePath)) . '.png';
        if (!file_exists($filePath)) {
            wp_send_json_error(array('message' => __('File not exists!', 'wpfdAddon')));
        }
        // Try resize first
        $editor = wp_get_image_editor($filePath, array('mime_type' => 'image/png'));
        $editor->set_quality(apply_filters('wpfd_preview_image_quantity', 90));
        $tooSmall = false;
        $originSize = $editor->get_size();
        $imageSize = apply_filters('wpfd_preview_image_size', array('w' => 800, 'h' => 800));
        if ($originSize['width'] < $imageSize['w'] && $originSize['height'] <= $imageSize['h']) {
            // Copy and save
            WpfdHelperFolder::getFileSystem()->copy($filePath, $previewFileName, true);
            $tooSmall = true;
        }

        // Do resize
        if (!$tooSmall) {
            $resized = $editor->resize($imageSize['w'], $imageSize['h'], false);

            if (is_wp_error($resized)) {
                wp_send_json_error(array('content' => $resized->get_error_message()));
            }

            $saved = $editor->save($previewFileName);
            if (is_wp_error($saved)) {
                wp_send_json_error(array('content' => $saved->get_error_message()));
            }
        }
        // Add watermark to resized image
        if (file_exists($previewFileName)) {
            $watermarkedPath = self::apply($previewFileName);

            $watermarkedPath = str_replace(WP_CONTENT_DIR, '', $watermarkedPath);
            update_post_meta($fileId, '_wpfd_preview_file_path', $watermarkedPath);

            wp_send_json_success(array('path' => $watermarkedPath));
        }

        wp_send_json_error(array('content' => __('Unknown error!', 'wpfdAddon')));
    }

    /**
     * Prune watermark files
     *
     * @param integer $count Count deleted
     *
     * @return boolean|integer
     */
    public function pruneWatermark($count = false)
    {
        $wmPath = WpfdHelperFolder::getWatermarkPath();
        $filesPath = glob($wmPath . '*.[pPjJ][nNpP][gG]');
        $totalDeleted = 0;
        foreach ($filesPath as $fileName) {
            if (file_exists($fileName)) {
                unlink($fileName);
                $totalDeleted++;
            }
        }

        if ($count) {
            return $totalDeleted;
        }

        return true;
    }

    /**
     * Callback fire before cloud file download
     *
     * @param string $id    File id
     * @param string $type  Cloud type
     * @param string $catId Category id
     *
     * @return void
     */
    public function beforeCloudDownload($id, $type, $catId)
    {
        $preview = Utilities::getInput('preview', 'GET', 'bool');

        if ($preview && WpfdHelperFile::isWatermarkEnabled()) {
            // Generated preview enabled?
            $generatedPreviewUrl = WpfdHelperFile::getGeneratedPreviewUrl($id, $catId, '', true);

            if (false !== $generatedPreviewUrl) {
                $filename = 'preview_' . $id;
                if ($filename === '') {
                    $filename = 'download';
                }
                $filedownload = $filename . '.png';
                WpfdHelperFile::sendDownload(
                    $generatedPreviewUrl,
                    $filedownload,
                    'png',
                    true, // Preview
                    false
                );
                exit();
            }
        }
    }

    /**
     * Callback fire before download file
     *
     * @param object $file File object
     * @param array  $meta File meta
     *
     * @return void
     */
    public function beforeDownload($file, $meta)
    {
        $preview = Utilities::getInput('preview', 'GET', 'bool');

        if ($preview && WpfdHelperFile::isWatermarkEnabled()) {
            // Generated preview enabled?
            $generatedPreviewUrl = WpfdHelperFile::getGeneratedPreviewUrl($file->ID, $meta['catid'], '', true);

            if (false !== $generatedPreviewUrl) {
                $filename = WpfdHelperFile::santizeFileName($file->title);
                if ($filename === '') {
                    $filename = 'download';
                }
                $filedownload = $filename . '.' . $file->ext;
                WpfdHelperFile::sendDownload(
                    $generatedPreviewUrl,
                    $filedownload,
                    $file->ext,
                    true, // Preview
                    false
                );
                exit();
            }
        }
    }

    /**
     * Check generator preview enabled
     *
     * @return boolean
     */
    public static function generatorEnabled()
    {
        $wpfdOptions = get_option('_wpfd_global_config', false);
        if (false === $wpfdOptions) {
            return false;
        }
        $isEnabled = isset($wpfdOptions['auto_generate_preview']) ? $wpfdOptions['auto_generate_preview'] : false;
        if (!$isEnabled) {
            return false;
        }

        return true;
    }

    /**
     * Get files link
     *
     * @return array
     */
    public static function getFilesLinked()
    {
        global $wpdb;

        $ids = $wpdb->get_col('SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key = "_wpfd_products_linked"');
        return self::extractCloudIds($ids);
    }

    /**
     * Get all files
     *
     * @return array
     */
    public static function getAllFiles()
    {
        global $wpdb;

        $ids = $wpdb->get_col('SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type = "wpfd_file" AND post_status = "publish"');

        $cloudPreviewPath = self::getAllCloudPreviewFiles();

        return array_merge($ids, $cloudPreviewPath);
    }


    /**
     * Get watermark config
     *
     * @return array|mixed
     */
    public static function getConfig()
    {
        return WpfdHelperFile::getWatermarkConfig();
    }

    /**
     * Set watermark config data
     *
     * @param array $data Watermark config
     *
     * @return void
     */
    public static function setConfig($data)
    {
        WpfdHelperFile::setWatermarkConfig($data);
    }

    /**
     * Get watermark type
     *
     * @return string
     */
    public static function getWatermarkType()
    {
        $config = self::getConfig();

        switch ($config['wm_apply_on']) {
            case 'all':
                return self::WM_TYPE_ALL;
            case 'product':
            default:
                return self::WM_TYPE_PRODUCT;
        }
    }

    /**
     * Check watermark option is enabled
     *
     * @param array $args Pseudo argument
     *
     * @return boolean
     */
    public static function enabled($args = false)
    {
        return WpfdHelperFile::isWatermarkEnabled();
    }

    /**
     * Apply watermark on $imagePath
     *
     * @param string $imagePath The image path need watermark
     *
     * @return boolean
     */
    public static function apply($imagePath)
    {
        $watermarkPath = WpfdHelperFolder::getWatermarkPath();

        $watermarkFilePath = $watermarkPath . WpfdHelperFolder::getBaseName($imagePath);
        self::log('Watermarking ' . $imagePath . ' then save to ' . $watermarkFilePath);
        // Reapply content path to image path
        if (strpos($imagePath, 'wp-content') === false) {
            $imagePath = WP_CONTENT_DIR . $imagePath;
        }
        // Copy source image to watermark location
        WpfdHelperFolder::copy($imagePath, $watermarkFilePath, true);

        if (self::watermark($watermarkFilePath)) {
            return $watermarkFilePath;
        }

        return $imagePath;
    }

    /**
     * Check image watermarked
     *
     * @param string $imagePath Image path
     *
     * @return boolean
     */
    public static function watermarked($imagePath)
    {
        $watermarkPath = WpfdHelperFolder::getWatermarkPath();
        $watermarkFilePath = $watermarkPath . WpfdHelperFolder::getBaseName($imagePath);

        return file_exists($watermarkFilePath);
    }

    /**
     * Backup image
     *
     * @param string  $imagePath Image path
     * @param boolean $overwrite Override exists image
     *
     * @return void
     */
    public static function backup($imagePath, $overwrite = false)
    {
        $backupPath = WpfdHelperFolder::getOriginalPath();

        // Copy source image to original folder, no overwrite to keep the first one
        WpfdHelperFolder::copy($imagePath, $backupPath . WpfdHelperFolder::getBaseName($imagePath), $overwrite);
    }

    /**
     * Restore original image
     *
     * @param string  $imagePath Image path
     * @param boolean $delete    Delete after restore
     *
     * @return boolean
     */
    public static function restore($imagePath, $delete = false)
    {
        // Check file name exists in original path
        $backupPath = WpfdHelperFolder::getOriginalPath();

        // Get file name to search
        $fileName = basename($imagePath);

        $originalName = $backupPath . $fileName;

        if (!$fileName || !file_exists($originalName)) {
            return false;
        }

        // Restore the original file
        $success = WpfdHelperFolder::copy($originalName, $imagePath, true);

        // Delete original
        if ($delete) {
            WpfdHelperFolder::delete($originalName);
        }

        return $success;
    }

    /**
     * Add watermark to image
     *
     * @param string $imagePath Image path
     * @param array  $config    Watermark config
     *
     * @return boolean
     *
     * @throws ImagickException Throw on exception
     */
    public static function watermark($imagePath, $config = null)
    {
        if (!file_exists($imagePath)) {
            self::log('Image Path not exists! ' . $imagePath);

            return false;
        }

        if (is_null($config)) {
            $config = self::getConfig();
        }

        // Made sure wm_path is absolute path or Imagick can't work
        $config['wm_path'] = WpfdHelperFolder::getAbsolutePath($config['wm_path']);

        if (!isset($config['wm_path']) || !file_exists($config['wm_path'])) {
            self::log('Watermark Path not exists! ' . $config['wm_path']);
            return false;
        }


        self::log('Watermarking ' . $imagePath . ' with ' . $config['wm_path']);

        $success = false;

        if ($success === false && function_exists('imagecreatefrompng')) {
            self::log('Trying add watermark by GD');
            $success = self::watermarkGD($imagePath, $config);
        }

        if ($success === false && class_exists('Imagick')) {
            self::log('Trying add watermark by Imagick');
            // Fix: Uncaught ImagickException
            try {
                $success = self::watermarkImagick($imagePath, $config);
            } catch (Exception $e) {
                self::log('Error: ' . $e->getMessage());
                return false;
            }
        }

        return $success;
    }

    /**
     * Add watermark via GD
     *
     * @param string $imagePath Image path
     * @param array  $config    Watermark config
     *
     * @return boolean
     */
    private static function watermarkGD($imagePath, $config = array())
    {
        try {
            // Find base image size
            $logoPath = $config['wm_path'];

            $image = self::createImage($imagePath);
            $watermarkImage = self::createImage($logoPath);

            if (!$image || !$watermarkImage) {
                return false;
            }

            // Set opacity for watermark image
            if ((int)$config['wm_opacity'] !== 1) {
                $watermarkImage = self::setOpacity($watermarkImage, $config['wm_opacity']);
            }

            $imageInfo = getimagesize($imagePath);
            list($wm_x, $wm_y) = getimagesize($logoPath);
            list($image_x, $image_y) = $imageInfo;

            if (false === $imageInfo) {
                return false;
            }

            $watermark_margin = self::calculateWatermarkMargin($config, $image_x, $image_y);

            // Set image scaling
            list($new_width, $new_height) = self::calculateScaling($config, $wm_x, $wm_y, $image_x);

            // Calc watermark position
            list($watermark_pos_x, $watermark_pos_y) = self::calculateWatermarkPosition($config, $image_x, $image_y, $new_width, $new_height, $watermark_margin);

            $sampled = imagecopyresampled(
                $image,
                $watermarkImage,
                $watermark_pos_x,
                $watermark_pos_y,
                0,
                0,
                $new_width,
                $new_height,
                $wm_x,
                $wm_y
            );

            if (!$sampled) {
                return false;
            }
            // Save image
            switch (strtolower($imageInfo['mime'])) {
                case 'image/jpeg':
                case 'image/pjpeg':
                    return imagejpeg($image, $imagePath);
                case 'image/png':
                    $background = imagecolorallocate($image, 0, 0, 0);
                    // removing the black from the placeholder
                    imagecolortransparent($image, $background);

                    // turning off alpha blending (to ensure alpha channel information
                    // is preserved, rather than removed (blending with the rest of the
                    // image in the form of black))
                    imagealphablending($image, false);

                    // turning on alpha channel information saving (to ensure the full range
                    // of transparency is preserved)
                    imagesavealpha($image, true);

                    return imagepng($image, $imagePath, 9);
                case 'image/gif':
                    return imagegif($image, $imagePath);
                default:
                    return false; // Not support image type
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create image
     *
     * @param string $image Image path
     *
     * @return false|GdImage|resource|void
     */
    private static function createImage($image)
    {
        $size = getimagesize($image);
        // Load image from file
        switch (strtolower($size['mime'])) {
            case 'image/jpeg':
            case 'image/pjpeg':
                return imagecreatefromjpeg($image);
            case 'image/png':
                return imagecreatefrompng($image);
            case 'image/gif':
                return imagecreatefromgif($image);
        }
    }

    /**
     * Set opacity
     *
     * @param string $imageSrc Image path
     * @param float  $opacity  Opacity
     *
     * @return false|GdImage|resource
     */
    private static function setOpacity($imageSrc, $opacity)
    {
        $width = imagesx($imageSrc);
        $height = imagesy($imageSrc);

        // Duplicate image and convert to TrueColor
        $imageDst = imagecreatetruecolor($width, $height);
        imagealphablending($imageDst, false);
        imagefill($imageDst, 0, 0, imagecolortransparent($imageDst));
        imagecopy($imageDst, $imageSrc, 0, 0, 0, 0, $width, $height);

        // Set new opacity to each pixel
        for ($x = 0; $x < $width; ++$x) {
            for ($y = 0; $y < $height; ++$y) {
                $pixelColor = imagecolorat($imageDst, $x, $y);
                $pixelOpacity = 127 - (($pixelColor >> 24) & 0xFF);
                if ($pixelOpacity > 0) {
                    $pixelOpacity = $pixelOpacity * $opacity;
                    $pixelColor = ($pixelColor & 0xFFFFFF) | ((int)round(127 - $pixelOpacity) << 24);
                    imagesetpixel($imageDst, $x, $y, $pixelColor);
                }
            }
        }

        return $imageDst;
    }

    /**
     * Add watermark via Imagick
     *
     * @param string $imagePath Image th
     * @param array  $config    Watermark config
     *
     * @return boolean
     *
     * @throws ImagickException Throw on exception
     */
    private static function watermarkImagick($imagePath, $config = array())
    {
        $watermarkPath = $config['wm_path'];

        if (!file_exists($imagePath)) {
            return false;
        }

        // Open the source image
        $image = new Imagick();
        if (!$image->readImage($imagePath)) { // This must be absolute path to the image
            return false;
        }
        // Open the watermark image
        $watermark = new Imagick();
        if (!$watermark->readImage($watermarkPath)) {
            return false;
        }

        // Retrieve size of the Images to verify how to print the watermark on the image
        $image_x = $image->getImageWidth();
        $image_y = $image->getImageHeight();
        $wm_x = $watermark->getImageWidth();
        $wm_y = $watermark->getImageHeight();

        if ($image_y < $wm_y || $image_x < $wm_x) {
            // Resize the watermark to be of the same size of the image
            $watermark->scaleImage($image_x, $image_y);

            // Update size of the watermark
            $wm_x = $watermark->getImageWidth();
            $wm_y = $watermark->getImageHeight();
        }

        $watermark_margin = self::calculateWatermarkMargin($config, $image_x, $image_y);

        // Set image scaling
        list($new_width, $new_height) = self::calculateScaling($config, $wm_x, $wm_y, $image_x);

        // Calc watermark position
        list($watermark_pos_x, $watermark_pos_y) = self::calculateWatermarkPosition($config, $image_x, $image_y, $new_width, $new_height, $watermark_margin);

        // Draw the watermark on your image
        $image->compositeImage($watermark, Imagick::COMPOSITE_OVER, $watermark_pos_x, $watermark_pos_y);

        return $image->writeImage($imagePath);
    }

    /**
     * Calculate Watermark position
     *
     * @param array   $config           Watermark config
     * @param integer $image_x          Image X axis
     * @param integer $image_y          Image Y axis
     * @param integer $new_width        New watermark width
     * @param integer $new_height       New watermark height
     * @param array   $watermark_margin Watermark margin config
     *
     * @return array
     */
    private static function calculateWatermarkPosition($config, $image_x, $image_y, $new_width, $new_height, array $watermark_margin)
    {
        switch ($config['wm_position']) {
            case 'top_left':
                $watermark_pos_x = (int)$watermark_margin['left'];
                $watermark_pos_y = (int)$watermark_margin['top'];
                break;
            case 'top_center':
                $watermark_pos_x = ($image_x - $new_width) / 2;
                $watermark_pos_y = (int)$watermark_margin['top'];
                break;
            case 'top_right':
                $watermark_pos_x = $image_x - $new_width - (int)$watermark_margin['right'];
                $watermark_pos_y = (int)$watermark_margin['top'];
                break;
            case 'center_left':
                $watermark_pos_x = (int)$watermark_margin['left'];
                $watermark_pos_y = ($image_y - $new_height) / 2;
                break;
            case 'center_right':
                $watermark_pos_x = $image_x - $new_width - (int)$watermark_margin['right'];
                $watermark_pos_y = ($image_y - $new_height) / 2;
                break;
            case 'bottom_left':
                $watermark_pos_x = (int)$watermark_margin['left'];
                $watermark_pos_y = $image_y - $new_height - (int)$watermark_margin['bottom'];
                break;
            case 'bottom_center':
                $watermark_pos_x = ($image_x - $new_width) / 2;
                $watermark_pos_y = $image_y - $new_height - (int)$watermark_margin['bottom'];
                break;
            case 'bottom_right':
                $watermark_pos_x = $image_x - $new_width - (int)$watermark_margin['right'];
                $watermark_pos_y = $image_y - $new_height - (int)$watermark_margin['bottom'];
                break;
            case 'center_center':
            default:
                $watermark_pos_x = ($image_x - $new_width) / 2; // Watermark left
                $watermark_pos_y = ($image_y - $new_height) / 2; // Watermark bottom
                break;
        }

        return array($watermark_pos_x, $watermark_pos_y);
    }

    /**
     * Calculate scaling
     *
     * @param array   $config  Watermark config
     * @param integer $wm_x    Watermark X axis
     * @param integer $wm_y    Watermark Y axis
     * @param integer $image_x Image X axis
     *
     * @return array
     */
    private static function calculateScaling($config, $wm_x, $wm_y, $image_x)
    {
        $r = $wm_x / $wm_y;
        $new_width = $image_x * (int)$config['wm_size'] / 100;
        if ($new_width > $wm_x) {
            $new_width = $wm_x;
        }

        $new_height = $new_width / $r;
        if ($new_height > $wm_y) {
            $new_height = $wm_y;
        }

        return array($new_width, $new_height);
    }

    /**
     * Calculate watermark margin
     *
     * @param array   $config  Image watermark config
     * @param integer $image_x Image x axis
     * @param integer $image_y Image Y axis
     *
     * @return array
     */
    private static function calculateWatermarkMargin($config, $image_x, $image_y)
    {
        $watermark_margin = array(
            'left' => $config['wm_margin_left'],
            'right' => $config['wm_margin_right'],
            'bottom' => $config['wm_margin_bottom'],
            'top' => $config['wm_margin_top'],
        );
        // Get image margin
        if ($config['wm_margin_unit'] === '%') {
            $percent_watermark_margin = array();
            $percent_watermark_margin['left'] = ($image_x / 100) * $config['wm_margin_left'];
            $percent_watermark_margin['right'] = ($image_x / 100) * $config['wm_margin_right'];
            $percent_watermark_margin['top'] = ($image_y / 100) * $config['wm_margin_top'];
            $percent_watermark_margin['bottom'] = ($image_y / 100) * $config['wm_margin_bottom'];
            $watermark_margin = $percent_watermark_margin;
        }

        return $watermark_margin;
    }

    /**
     * Extract cloud ids from linked product
     *
     * @param array $ids File ids
     *
     * @return array
     */
    private static function extractCloudIds($ids)
    {
        // Cloud files
        $clouds = wpfd_get_support_cloud();
        $cloudIds = array();
        foreach ($clouds as $cloud) {
            $productsLinked = get_option('wpfdAddon_' . $cloud . '_products_linked', array());
            if (empty($productsLinked)) {
                continue;
            }
            foreach ($productsLinked as $productLinked) {
                if (!isset($productLinked['id'])) {
                    continue;
                }
                // Fix onedrive file id
                $cloudId = trim($productLinked['id']);
                if ($cloud === 'onedrive') {
                    $cloudId = str_replace('-', '!', $cloudId);
                    $id = $cloudId;
                }
                if (!in_array($cloudId, $cloudIds)) {
                    $cloudIds[] = $cloudId;
                }
            }
        }

        $ids = array_map('intval', $ids);

        if (!empty($cloudIds)) {
            $ids = array_merge($ids, $cloudIds);
        }

        return $ids;
    }

    /**
     * Get all cloud preview files
     *
     * @return array
     */
    private static function getAllCloudPreviewFiles()
    {
        $clouds = wpfd_get_support_cloud();
        $previewPaths = array();
        $previewPath = WpfdHelperFolder::getPreviewsPath();

        foreach ($clouds as $cloud) {
            $files = glob($previewPath . $cloud . '_*');
            $previewPaths = array_merge($previewPaths, $files);
        }

        return array_map('trim', $previewPaths);
    }

    /**
     * Log
     *
     * @param mixed $data Log data
     *
     * @return void
     */
    private static function log($data)
    {
        // Do nothing if not enabled
        if (!self::$debug) {
            return;
        }

        if (is_string($data)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log if enable debug
            error_log($data);
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_var_export  -- Log if enable debug
            error_log(var_export($data, true));
        }
    }
}
