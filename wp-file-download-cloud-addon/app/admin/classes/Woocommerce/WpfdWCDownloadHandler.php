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
use Joomunited\WPFramework\v1_0_6\Utilities;

/**
 * Class WpfdWCDownloadHandler
 */
class WpfdWCDownloadHandler
{
    /**
     * Init download handler
     *
     * @return void
     */
    public static function init()
    {
        if (isset($_GET['wpfd_download_file'], $_GET['order']) && (isset($_GET['email']) || isset($_GET['uid']))) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- It's OK
            add_action('init', array(__CLASS__, 'downloadFile'));
        }

        add_action('wpfd_wc_download_file_redirect', array(__CLASS__, 'downloadFileRedirect'), 10, 2);
        add_action('wpfd_wc_download_file_xsendfile', array(__CLASS__, 'downloadFileXsendfile'), 10, 2);
        add_action('wpfd_wc_download_file_force', array(__CLASS__, 'downloadFileForce'), 10, 2);
    }

    /**
     * Download file
     *
     * @return void
     *
     * @throws \Dropbox\Exception_BadResponseCode Exception_BadResponseCode
     * @throws \Dropbox\Exception_InvalidAccessToken Exception_InvalidAccessToken
     * @throws \Dropbox\Exception_RetryLater Exception_RetryLater
     * @throws \Dropbox\Exception_ServerError Exception_ServerError
     * @throws \Dropbox\Exception_BadRequest Exception_BadRequest
     */
    public static function downloadFile()
    {
        $product_id = Utilities::getInput('wpfd_download_file', 'GET', 'string');
        $product    = wc_get_product($product_id);

        if (!$product || empty($_GET['key']) || empty($_GET['order'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- input var ok, CSRF ok.
            self::downloadError(esc_html__('Invalid download link.', 'wpfdAddon'));
        }

        // Fallback, accept email address if it's passed.
        if (empty($_GET['email']) && empty($_GET['uid'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- input var ok, CSRF ok.
            self::downloadError(esc_html__('Invalid download link.', 'wpfdAddon'));
        }

        // Get email address from order to verify hash.
        $order_id      = wc_get_order_id_by_order_key(wc_clean(wp_unslash($_GET['order']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- input var ok, CSRF ok.
        $order         = wc_get_order($order_id);
        $email_address = is_a($order, 'WC_Order') ? $order->get_billing_email() : null;

        // Prepare email address hash.
        $email_hash = function_exists('hash') ? hash('sha256', $email_address) : sha1($email_address);

        if (is_null($email_address) || !hash_equals(wp_unslash($_GET['uid']), $email_hash)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,PHPCompatibility.FunctionUse.NewFunctions.hash_equalsFound -- input var ok, CSRF ok, sanitization ok.
            self::downloadError(esc_html__('Invalid download link.', 'wpfdAddon'));
        }

        /**
         * Filter to verify user order
         *
         * @param boolean
         */
        $verifyUserOrder = apply_filters('wpfd_wc_verify_user_order', false);

        // Check valid order's user for downloading
        if ($verifyUserOrder) {
            $userOrderId = is_a($order, 'WC_Order') ? $order->get_user_id() : null;
            $dlUserId = isset(wp_get_current_user()->ID) ? wp_get_current_user()->ID : null;

            if (!is_null($userOrderId) && !is_null($dlUserId) && intval($userOrderId) !== intval($dlUserId)) {
                self::downloadError(esc_html__('Invalid download user.', 'wpfdAddon'));
            }
        }

        $download_ids = get_post_meta($order_id, '_wpfd_order_data', true);
        $download_id  = wc_clean(preg_replace('/\s+/', ' ', wp_unslash($_GET['key']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- It's OK

        if (empty($download_ids) || !isset($download_ids[$download_id])) {
            self::downloadError(__('Invalid download link.', 'wpfdAddon'));
        }

        $download = $download_ids[$download_id];
        $fileData = $download['file_data'];
        $categoryFrom = apply_filters('wpfdAddonCategoryFrom', $fileData['catid']);
        if (!class_exists('WpfdBase')) {
            include_once WPFD_PLUGIN_DIR_PATH . '/app/admin/classes/WpfdBase.php';
        }
        switch ($categoryFrom) {
            case 'dropbox':
                $dropCate = new WpfdAddonDropbox;
                try {
                    $dropbox = $dropCate->getAccount();
                    $fileInfo       = $dropbox->getFileMetadata($fileData['id']);
                    $temperateLink = $dropbox->createTemporaryDirectLink($fileInfo['path_lower']);
                    // Redirect user to direct link
                    self::checkDownloadsRemaining($download);
                    self::checkDownloadExpiry($download);
                    self::checkDownloadLoginRequired($download);
                    self::trackDownload($order, $download);
                    header('Location: ' . $temperateLink);
                    exit;
                } catch (Exception $ex) {
                    wp_exit(esc_html__('File not found!', 'wpfdAddon'));
                }

                break;
            case 'googleDrive':
                /**
                 * Filters to get google file info
                 *
                 * @param string File id
                 *
                 * @internal
                 *
                 * @return object
                 */
                $file = apply_filters('wpfdAddonDownloadGoogleDriveFile', $fileData['id']);
                $googleCate = new wpfdAddonGoogleDrive;
                // Serve download for google document
                if (strpos($file->mimeType, 'vnd.google-apps') !== false) { // Is google file
                    /* @var $fileData GuzzleHttp\Psr7\Response */
                    $fileData      = $googleCate->downloadGoogleDocument($file->id, $file->exportMineType);
                    $contentLength = $fileData->getHeaderLine('Content-Length');
                    $contentType   = $fileData->getHeaderLine('Content-Type');
                    self::checkDownloadsRemaining($download);
                    self::checkDownloadExpiry($download);
                    self::checkDownloadLoginRequired($download);
                    self::trackDownload($order, $download);
                    if ($fileData->getStatusCode() === 200) {
                        header('Content-Disposition: attachment; filename="' . esc_html($file->title . '.' . $file->ext) . '"');
                        header('Content-Type: ' . $contentType);
                        header('Content-Description: File Transfer');
                        header('Content-Transfer-Encoding: binary');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                        header('Pragma: public');
                        header('Content-Length: ' . $contentLength);
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file content output
                        echo $fileData->getBody();
                        exit();
                    }
                } else {
                    self::checkDownloadsRemaining($download);
                    self::checkDownloadExpiry($download);
                    self::checkDownloadLoginRequired($download);
                    self::trackDownload($order, $download);
                    $googleCate->downloadLargeFile($file, 'application/octet-stream', false, 0);
                }
                break;
            case 'googleTeamDrive':
                /**
                 * Filters to get google team drive file information
                 *
                 * @param string File id
                 *
                 * @internal
                 *
                 * @return object
                 */
                $file = apply_filters('wpfdAddonDownloadGoogleTeamDriveFile', $fileData['id']);
                $googleTeamDriveCategory = new wpfdAddonGoogleTeamDrive();
                // Server download for google team drive document
                if (strpos($file->mimeType, 'vnd.google-apps') !== false) { // This is a google team drive file
                    /* @var $fileData GuzzleHttp\Psr7\Response */
                    $fileData      = $googleTeamDriveCategory->downloadGoogleDocument($file->id, $file->exportMineType);
                    $contentLength = $fileData->getHeaderLine('Content-Length');
                    $contentType   = $fileData->getHeaderLine('Content-Type');
                    self::checkDownloadsRemaining($download);
                    self::checkDownloadExpiry($download);
                    self::checkDownloadLoginRequired($download);
                    self::trackDownload($order, $download);
                    if ($fileData->getStatusCode() === 200) {
                        header('Content-Disposition: attachment; filename="' . esc_html($file->title . '.' . $file->ext) . '"');
                        header('Content-Type: ' . $contentType);
                        header('Content-Description: File Transfer');
                        header('Content-Transfer-Encoding: binary');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                        header('Pragma: public');
                        header('Content-Length: ' . $contentLength);
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file content output
                        echo $fileData->getBody();
                        exit();
                    }
                } else {
                    self::checkDownloadsRemaining($download);
                    self::checkDownloadExpiry($download);
                    self::checkDownloadLoginRequired($download);
                    self::trackDownload($order, $download);
                    $googleTeamDriveCategory->downloadLargeFile($file, 'application/octet-stream', false, 0);
                }
                break;
            case 'onedrive':
                self::checkDownloadsRemaining($download);
                self::checkDownloadExpiry($download);
                self::checkDownloadLoginRequired($download);
                self::trackDownload($order, $download);
                if (defined('WPFD_ONEDRIVE_DIRECT') && WPFD_ONEDRIVE_DIRECT) {
                    $onedrive = new WpfdAddonOneDrive;
                    $link = $onedrive->createSharedLink($fileData['id']);
                    if (!$link) {
                        wp_exit(esc_html__('File not found!', 'wpfdAddon'));
                    }

                    // Redirect user to direct link
                    header('Location: ' . $link);
                    exit();
                } else {
                    $file = apply_filters('wpfdAddonDownloadOneDriveFile', $fileData['id']);
                    if (!empty($file)) {
                        $contentLength = (int) $file->size;
                        header('Content-Disposition: attachment; filename="' . esc_html($file->title . '.' . $file->ext) . '"');
                        header('Content-Type: application/octet-stream');
                        header('Content-Description: File Transfer');
                        header('Content-Transfer-Encoding: binary');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                        header('Pragma: public');
                        header('Content-Length: ' . $contentLength);
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file content output
                        echo $file->datas;
                        exit();
                    }
                }
                
                break; // phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable -- It's OK
            case 'onedrive_business':
                $onedriveBusiness = new WpfdAddonOneDriveBusiness;
                $link = $onedriveBusiness->createSharedLink($fileData['id']);
                if (!$link) {
                    wp_exit(esc_html__('File not found!', 'wpfdAddon'));
                }
                // Redirect user to direct link
                self::checkDownloadsRemaining($download);
                self::checkDownloadExpiry($download);
                self::checkDownloadLoginRequired($download);
                self::trackDownload($order, $download);
                header('Location: ' . $link);
                exit();
                break; // phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable -- It's OK
            case 'aws':
                $aws = new WpfdAddonAws;
                $aws->downloadAws($fileData['id'], $fileData['catid']);
                break;
            default:
                break;
        }
        Application::getInstance('Wpfd');
        $fileModel = Model::getInstance('filefront');
        $file      = $fileModel->getFullFile($fileData['id']);
        $file_meta = get_post_meta($fileData['id'], '_wpfd_file_metadata', true);
        if (!$file) {
            self::downloadError(__('Invalid download link.', 'wpfdAddon'));
        }

        $file_path = isset($file->file) ? WpfdBase::getFilesPath($file->catid) . '/' . $file->file : '';
        if (true !== $file->remote_url && ($file_path === '' || !file_exists($file_path))) {
            self::downloadError(__('File not found.', 'wpfdAddon'));
        }
        $download_range = self::getDownloadRange(@filesize($file_path));  // @codingStandardsIgnoreLine.

        self::checkOrderIsValid($download);
        if (!$download_range['is_range_request']) {
            // If the remaining download count goes to 0, allow range requests to be able to finish streaming from iOS devices.
            self::checkDownloadsRemaining($download);
        }
        self::checkDownloadExpiry($download);
        self::checkDownloadLoginRequired($download);

        // Change remaining/counts.
        self::trackDownload($order, $download);
        $remote_url = isset($file_meta['remote_url']) ? $file_meta['remote_url'] : false;
        if ($remote_url) {
            $url = $file_meta['file'];
            header('Location: ' . $url);
            exit();
        } else {
            $downloadFileName = $fileData['name'] . '.' . $file->ext;
            self::download($file_path, $download['product_id'], $downloadFileName);
        }
    }

    /**
     * Direct download file
     *
     * @param string $file_path File path
     * @param string $filename  File name
     *
     * @return void
     */
    public static function downloadFileRedirect($file_path, $filename = '')
    {
        header('Location: ' . $file_path);
        exit;
    }

    /**
     * X-Send-File download
     *
     * @param string $file_path File path
     * @param string $filename  File name
     *
     * @return void
     */
    public static function downloadFileXsendfile($file_path, $filename)
    {
        if (function_exists('apache_get_modules') && in_array('mod_xsendfile', apache_get_modules(), true)) {
            self::downloadHeaders($file_path, $filename);
            $filepath = apply_filters('wpfd_wc_download_file_xsendfile_file_path', $file_path, $file_path, $filename);
            header('X-Sendfile: ' . $filepath);
            exit;
        } elseif (stristr(getenv('SERVER_SOFTWARE'), 'lighttpd')) {
            self::downloadHeaders($file_path, $filename);
            $filepath = apply_filters('wpfd_wc_download_file_xsendfile_lighttpd_file_path', $file_path, $file_path, $filename);
            header('X-Lighttpd-Sendfile: ' . $filepath);
            exit;
        } elseif (stristr(getenv('SERVER_SOFTWARE'), 'nginx') || stristr(getenv('SERVER_SOFTWARE'), 'cherokee')) {
            self::downloadHeaders($file_path, $filename);
            $xsendfile_path = trim(preg_replace('`^' . str_replace('\\', '/', getcwd()) . '`', '', $file_path), '/');
            $xsendfile_path = apply_filters('wpfd_wc_download_file_xsendfile_x_accel_redirect_file_path', $xsendfile_path, $file_path, $filename);
            header('X-Accel-Redirect: /' . $xsendfile_path);
            exit;
        }

        // Fallback.
        self::downloadFileForce($file_path, $filename);
    }

    /**
     * Force download file
     *
     * @param string $file_path File path
     * @param string $filename  File name
     *
     * @return void
     */
    public static function downloadFileForce($file_path, $filename)
    {
        $download_range = self::getDownloadRange(@filesize($file_path)); // @codingStandardsIgnoreLine.

        self::downloadHeaders($file_path, $filename, $download_range);

        $start  = isset($download_range['start']) ? $download_range['start'] : 0;
        $length = isset($download_range['length']) ? $download_range['length'] : 0;
        if (!self::readfileChunked($file_path, $start, $length)) {
            self::downloadError(__('File not found', 'wpfdAddon'));
        }

        exit;
    }

    /**
     * Track download
     *
     * @param WC_Order $order    Order object
     * @param array    $download Download instance
     *
     * @return boolean
     */
    public static function trackDownload($order, $download)
    {
        if (!isset($download)) {
            return false;
        }
        // Increase download count and decrease downloads_remaining, made sure downloads_remaining not less than zero
        // If downloads_remaining = '' keep it Unlimited
        $downloads_remaining = '';
        if ($download['downloads_remaining'] !== '') {
            $downloads_remaining = intval($download['downloads_remaining']) - 1;

            if ($downloads_remaining < 0) {
                $downloads_remaining = 0;
            }
        }
        $download['downloads_remaining'] = $downloads_remaining;

        // Set download_count
        $download['download_count'] = intval($download['download_count']) + 1;
        $downloads = get_post_meta($order->get_id(), '_wpfd_order_data', true);
        $downloads[$download['download_id']] = $download;

        // Update
        update_post_meta($order->get_id(), '_wpfd_order_data', $downloads);
    }
    /**
     * Set headers for the download.
     *
     * @param string $file_path      File path.
     * @param string $filename       File name.
     * @param array  $download_range Array containing info about range download request (see {@see getDownloadRange} for structure).
     *
     * @return void
     */
    private static function downloadHeaders($file_path, $filename, $download_range = array())
    {
        self::checkServerConfig();
        self::cleanBuffers();
        wc_nocache_headers();

        header('X-Robots-Tag: noindex, nofollow', true);
        header('Content-Type: ' . self::getDownloadContentType($file_path));
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename="' . $filename . '";');
        header('Content-Transfer-Encoding: binary');

        $file_size = @filesize($file_path); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        if (!$file_size) {
            return;
        }

        if (isset($download_range['is_range_request']) && true === $download_range['is_range_request']) {
            if (false === $download_range['is_range_valid']) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header('Content-Range: bytes 0-' . ($file_size - 1) . '/' . $file_size);
                exit;
            }

            $start  = $download_range['start'];
            $end    = $download_range['start'] + $download_range['length'] - 1;
            $length = $download_range['length'];

            header('HTTP/1.1 206 Partial Content');
            header('Accept-Ranges: 0-' . $file_size);
            header('Content-Range: bytes ' . $start. '-' . $end . '/' . $file_size);
            header('Content-Length: ' . $length);
        } else {
            header('Content-Length: ' . $file_size);
        }
    }

    /**
     * Get content type of a download.
     *
     * @param string $file_path File path.
     *
     * @return string
     */
    private static function getDownloadContentType($file_path)
    {
        $file_extension = strtolower(substr(strrchr($file_path, '.'), 1));
        $ctype          = 'application/force-download';

        foreach (get_allowed_mime_types() as $mime => $type) {
            $mimes = explode('|', $mime);
            if (in_array($file_extension, $mimes, true)) {
                $ctype = $type;
                break;
            }
        }

        return $ctype;
    }

    /**
     * Check and set certain server config variables to ensure downloads work as intended.
     *
     * @return void
     */
    private static function checkServerConfig()
    {
        wc_set_time_limit(0);
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv
        }
        @ini_set('zlib.output_compression', 'Off'); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_set
        @session_write_close(); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.VIP.SessionFunctionsUsage.session_session_write_close
    }

    /**
     * Clean all output buffers.
     *
     * Can prevent errors, for example: transfer closed with 3 bytes remaining to read.
     *
     * @return void
     */
    private static function cleanBuffers()
    {
        if (ob_get_level()) {
            $levels = ob_get_level();
            for ($i = 0; $i < $levels; $i ++) {
                @ob_end_clean(); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            }
        } else {
            @ob_end_clean(); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }
    }

    /**
     * Send download
     *
     * @param string      $file_path  File path
     * @param integer     $product_id Product Id
     * @param string|null $filename   File name
     *
     * @return void
     */
    public static function download($file_path, $product_id, $filename = null)
    {
        if (!$file_path) {
            self::downloadError(__('No file defined', 'wpfdAddon'));
        }
        if (is_null($filename)) {
            $filename = basename($file_path);

            if (strstr($filename, '?')) {
                $filename = current(explode('?', $filename));
            }
        }

        $filename             = apply_filters('wpfd_wc_file_download_filename', $filename, $product_id);
        $file_download_method = apply_filters('wpfd_wc_file_download_method', get_option('wpfd_wc_file_download_method', 'force'), $product_id);

        // Add action to prevent issues in IE.
        add_action('nocache_headers', array(__CLASS__, 'ieNocacheHeadersFix'));

        // Trigger download via one of the methods.
        do_action('wpfd_wc_download_file_' . $file_download_method, $file_path, $filename);
    }

    /**
     * Check order is valid
     *
     * @param array $download Download instance
     *
     * @return void
     */
    private static function checkOrderIsValid($download)
    {
        $order_id = wc_get_order_id_by_order_key(wc_clean(wp_unslash($download['order_key'])));
        if ($order_id) {
            $order = wc_get_order($order_id);

            if ($order && !$order->is_download_permitted()) {
                self::downloadError(__('Invalid order.', 'wpfdAddon'), '', 403);
            }
        }
    }

    /**
     * Check download expiry
     *
     * @param array $download Download instance
     *
     * @return void
     */
    private static function checkDownloadExpiry($download)
    {
        if (!is_null($download['access_expires']) && '' !== $download['access_expires'] && intval($download['access_expires']) < strtotime('midnight', current_time('timestamp', true))) {
            self::downloadError(__('Sorry, this download has expired', 'wpfdAddon'), '', 403);
        }
    }

    /**
     * Check download login required
     *
     * @param array $download Download instance
     *
     * @return void
     */
    private static function checkDownloadLoginRequired($download)
    {
        if ($download['user_id'] && 'yes' === get_option('woocommerce_downloads_require_login')) {
            if (!is_user_logged_in()) {
                if (wc_get_page_id('myaccount')) {
                    wp_safe_redirect(add_query_arg('wc_error', rawurlencode(__('You must be logged in to download files.', 'wpfdAddon')), wc_get_page_permalink('myaccount')));
                    exit;
                } else {
                    self::downloadError(__('You must be logged in to download files.', 'wpfdAddon') . ' <a href="' . esc_url(wp_login_url(wc_get_page_permalink('myaccount'))) . '" class="wc-forward">' . __('Login', 'wpfdAddon') . '</a>', __('Log in to Download Files', 'wpfdAddon'), 403);
                }
            }
        }
    }

    /**
     * Check downloads remaining
     *
     * @param array $download Download instance
     *
     * @return void
     */
    private static function checkDownloadsRemaining($download)
    {
        if ('' !== $download['downloads_remaining'] && 0 >= $download['downloads_remaining']) {
            self::downloadError(__('Sorry, you have reached your download limit for this file', 'wpfdAddon'), '', 403);
        }
    }
    /**
     * Filter headers for IE to fix issues over SSL.
     *
     * IE bug prevents download via SSL when Cache Control and Pragma no-cache headers set.
     *
     * @param array $headers HTTP headers.
     *
     * @return array
     */
    public static function ieNocacheHeadersFix($headers)
    {
        if (is_ssl() && ! empty($GLOBALS['is_IE'])) {
            $headers['Cache-Control'] = 'private';
            unset($headers['Pragma']);
        }
        return $headers;
    }
    /**
     * Read file chunked.
     *
     * Reads file in chunks so big downloads are possible without changing PHP.INI - http://codeigniter.com/wiki/Download_helper_for_large_files/.
     *
     * @param string  $file   File.
     * @param integer $start  Byte offset/position of the beginning from which to read from the file.
     * @param integer $length Length of the chunk to be read from the file in bytes, 0 means full file.
     *
     * @return boolean Success or fail
     */
    public static function readfileChunked($file, $start = 0, $length = 0)
    {
        if (!defined('WC_CHUNK_SIZE')) {
            define('WC_CHUNK_SIZE', 1024 * 1024);
        }
        $handle = @fopen($file, 'r'); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fopen

        if (false === $handle) {
            return false;
        }

        if (!$length) {
            $length = @filesize($file); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }

        $read_length = (int) WC_CHUNK_SIZE;

        if ($length) {
            $end = $start + $length - 1;

            @fseek($handle, $start); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $p = @ftell($handle); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

            while (!@feof($handle) && $p <= $end) { // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
                // Don't run past the end of file.
                if ($p + $read_length > $end) {
                    $read_length = $end - $p + 1;
                }

                echo @fread($handle, $read_length); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_read_fread
                $p = @ftell($handle); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

                if (ob_get_length()) {
                    ob_flush();
                    flush();
                }
            }
        } else {
            while (!@feof($handle)) { // @codingStandardsIgnoreLine.
                echo @fread($handle, $read_length); // @codingStandardsIgnoreLine.
                if (ob_get_length()) {
                    ob_flush();
                    flush();
                }
            }
        }

        return @fclose($handle); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fclose
    }

    /**
     * Parse the HTTP_RANGE request from iOS devices.
     * Does not support multi-range requests.
     *
     * @param integer $file_size Size of file in bytes.
     *
     * @return array {
     *     Information about range download request: beginning and length of
     *     file chunk, whether the range is valid/supported and whether the request is a range request.
     *
     * @type int  $start            Byte offset of the beginning of the range. Default 0.
     * @type int  $length           Length of the requested file chunk in bytes. Optional.
     * @type bool $is_range_valid   Whether the requested range is a valid and supported range.
     * @type bool $is_range_request Whether the request is a range request.
     * }
     */
    protected static function getDownloadRange($file_size)
    {
        $start          = 0;
        $download_range = array(
            'start'            => $start,
            'is_range_valid'   => false,
            'is_range_request' => false,
        );

        if (!$file_size) {
            return $download_range;
        }

        $end                      = $file_size - 1;
        $download_range['length'] = $file_size;

        if (isset($_SERVER['HTTP_RANGE'])) { // @codingStandardsIgnoreLine.
            $http_range                         = sanitize_text_field(wp_unslash($_SERVER['HTTP_RANGE'])); // WPCS: input var ok.
            $download_range['is_range_request'] = true;

            $c_start = $start;
            $c_end   = $end;
            // Extract the range string.
            list(, $range) = explode('=', $http_range, 2);
            // Make sure the client hasn't sent us a multibyte range.
            if (strpos($range, ',') !== false) {
                return $download_range;
            }

            /*
             * If the range starts with an '-' we start from the beginning.
             * If not, we forward the file pointer
             * and make sure to get the end byte if specified.
             */
            if ('-' === $range[0]) {
                // The n-number of the last bytes is requested.
                $c_start = $file_size - substr($range, 1);
            } else {
                $range   = explode('-', $range);
                $c_start = (isset($range[0]) && is_numeric($range[0])) ? (int) $range[0] : 0;
                $c_end   = (isset($range[1]) && is_numeric($range[1])) ? (int) $range[1] : $file_size;
            }

            /*
             * Check the range and make sure it's treated according to the specs: http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html.
             * End bytes can not be larger than $end.
             */
            $c_end = ($c_end > $end) ? $end : $c_end;
            // Validate the requested range and return an error if it's not correct.
            if ($c_start > $c_end || $c_start > $file_size - 1 || $c_end >= $file_size) {
                return $download_range;
            }
            $start  = $c_start;
            $end    = $c_end;
            $length = $end - $start + 1;

            $download_range['start']          = $start;
            $download_range['length']         = $length;
            $download_range['is_range_valid'] = true;
        }

        return $download_range;
    }

    /**
     * Throw download error
     *
     * @param string  $message Message
     * @param string  $title   Message title
     * @param integer $status  Status
     *
     * @return void
     */
    private static function downloadError($message, $title = '', $status = 404)
    {
        if (!strstr($message, '<a ')) {
            $message .= ' <a href="' . esc_url(wc_get_page_permalink('shop')) . '" class="wc-forward">' . esc_html__('Go to shop', 'wpfdAddon') . '</a>';
        }
        wp_die($message, $title, array('response' => $status)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- xss ok
    }
}
