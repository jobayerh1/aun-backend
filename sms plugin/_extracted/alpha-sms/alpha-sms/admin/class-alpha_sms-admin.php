<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://sms.net.bd
 * @since      1.0.0
 *
 * @package    Alpha_sms
 * @subpackage Alpha_sms/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Alpha_sms
 * @subpackage Alpha_sms/admin
 * @author     Alpha Net Developer Team <support@alpha.net.bd>
 */
class Alpha_sms_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Background processor for queued SMS jobs.
     *
     * @var Alpha_SMS_Background|null
     */
    private $background;

    /**
     * Initialize the class and set its properties.
     *
     * @param string                    $plugin_name The name of this plugin.
     * @param string                    $version     The version of this plugin.
     * @param Alpha_SMS_Background|null $background  Optional background processor instance.
     * @since    1.0.0
     */
    public function __construct($plugin_name, $version, $background = null)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->background = $background;
    }

    /**
     * Set the background processor instance.
     *
     * @param Alpha_SMS_Background|null $background Background processor.
     *
     * @return void
     */
    public function set_background_processor($background)
    {
        $this->background = $background;
    }

    /**
     * Retrieve the background processor instance.
     *
     * @return Alpha_SMS_Background|null
     */
    private function get_background_processor()
    {
        if ($this->background instanceof Alpha_SMS_Background) {
            return $this->background;
        }

        return null;
    }

    private function get_default_wc_order_statuses()
    {
        return [
            'wc-pending' => __('Pending payment', 'alpha-sms'),
            'wc-processing' => __('Processing', 'alpha-sms'),
            'wc-on-hold' => __('On hold', 'alpha-sms'),
            'wc-completed' => __('Completed', 'alpha-sms'),
            'wc-cancelled' => __('Cancelled', 'alpha-sms'),
            'wc-refunded' => __('Refunded', 'alpha-sms'),
            'wc-failed' => __('Failed', 'alpha-sms'),
        ];
    }

    private function normalize_order_status_key($status_key)
    {
        $status_key = is_string($status_key) ? $status_key : '';
        $status_key = preg_replace('/^wc-/', '', $status_key);
        $status_key = sanitize_key(str_replace('-', '_', $status_key));

        return str_replace('-', '_', $status_key);
    }

    private function get_customer_order_status_configs()
    {
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : $this->get_default_wc_order_statuses();

        $configs = [];

        foreach ($statuses as $status_key => $label) {
            $normalized = $this->normalize_order_status_key($status_key);

            if ($normalized === '') {
                continue;
            }

            $configs[] = [
                'status_key' => $status_key,
                'label' => wp_strip_all_tags($label),
                'normalized' => $normalized,
                'enabled_key' => 'order_status_' . $normalized,
                'message_key' => 'ORDER_STATUS_' . strtoupper($normalized) . '_SMS',
            ];
        }

        return $configs;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Alpha_sms_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Alpha_sms_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/alpha_sms-admin.css',
            [],
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Alpha_sms_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Alpha_sms_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/alpha_sms-admin.js',
            ['jquery'],
            $this->version,
            false
        );
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_setting_page()
    {
        require_once 'partials/' . $this->plugin_name . '-admin-display_settings.php';
    }

    /**
     * Render the campaign page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_campaign_page()
    {
        require_once 'partials/' . $this->plugin_name . '-admin-display_campaign.php';
    }

    /**
     *  Add the main menu and sub menu of the plugin
     *
     * @since    1.0.0
     */

    public function add_admin_menu()
    {
        // Primary Main menu
        add_menu_page(
            'Alpha SMS',
            'Alpha SMS',
            'manage_options',
            $this->plugin_name,
            [$this, 'display_campaign_page'],
            'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAyNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjxzdmcgdmVyc2lvbj0iMS4xIiBpZD0iTGF5ZXJfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeD0iMHB4IiB5PSIwcHgiDQoJIHZpZXdCb3g9IjAgMCAzMiAzMiIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgMzIgMzI7IiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxzdHlsZSB0eXBlPSJ0ZXh0L2NzcyI+DQoJLnN0MHtmaWxsOiM0QkRFOUQ7fQ0KCS5zdDF7ZmlsbDojMkYzNTNCO30NCjwvc3R5bGU+DQo8Zz4NCgk8cGF0aCBjbGFzcz0ic3QwIiBkPSJNMTEuMiwxYzIuOS0xLjIsNi40LTEsOS4yLDAuNmMyLjYsMS41LDQuNiw0LjIsNS4xLDcuMmMwLjMsMS43LDAuMSwzLjMtMC4zLDVjMC0xLjEsMC4xLTIuMy0wLjItMy40DQoJCWMtMC42LTMtMi42LTUuOC01LjMtNy4yYy0yLjMtMS4yLTUtMS42LTcuNS0wLjlDOS4yLDMuMSw2LjYsNS4yLDUuMyw4Yy0wLjksMi42LTAuNiw1LjYsMC42LDguMWMwLjksMiwyLjUsMy42LDQuMyw0LjgNCgkJYzIuNCwxLjcsNC44LDMuNSw3LjEsNS4yYy0yLjYsMC01LjIsMC03LjgsMGMtMy4yLDAuMS02LjEtMi45LTYtNi4xYzAtNC4zLDAtOC43LDAtMTNjMC0zLjEsMi45LTUuOCw2LTUuNw0KCQlDMTAuMSwxLjIsMTAuNywxLjIsMTEuMiwxeiIvPg0KCTxwYXRoIGNsYXNzPSJzdDAiIGQ9Ik05LjQsMTAuMWMxLjEtMC41LDIuNiwwLjIsMi44LDEuNGMwLjMsMS4yLTAuOCwyLjYtMi4xLDIuNGMtMS4zLDAtMi4yLTEuNC0xLjktMi42DQoJCUM4LjQsMTAuOSw4LjksMTAuNCw5LjQsMTAuMXoiLz4NCgk8cGF0aCBjbGFzcz0ic3QxIiBkPSJNMTUuMSwxMC4xYzEuMS0wLjUsMi41LDAuMSwyLjgsMS4zYzAuMywxLjEtMC40LDIuMy0xLjUsMi41Yy0xLjEsMC4zLTIuNC0wLjYtMi41LTEuOA0KCQlDMTMuOCwxMS4zLDE0LjMsMTAuNSwxNS4xLDEwLjF6Ii8+DQoJPHBhdGggY2xhc3M9InN0MCIgZD0iTTIwLjcsMTAuMWMxLjItMC42LDIuOCwwLjMsMi45LDEuNmMwLjIsMS4yLTEsMi40LTIuMiwyLjJjLTEuMS0wLjEtMi0xLjEtMS45LTIuMg0KCQlDMTkuNiwxMS4xLDIwLDEwLjQsMjAuNywxMC4xeiIvPg0KPC9nPg0KPGc+DQoJPGc+DQoJCTxwYXRoIGNsYXNzPSJzdDAiIGQ9Ik0yMC45LDEuMmMxLjIsMCwyLjQtMC4xLDMuNSwwLjNjMi40LDAuOCw0LjEsMy4yLDQuMSw1LjdjMCwzLjksMCw3LjksMCwxMS44YzAsMS4zLDAsMi42LTAuNiwzLjcNCgkJCWMtMC45LDItMywzLjMtNS4xLDMuNGMwLjEsMS45LDAuMSwzLjgsMC4yLDUuN2MtMS4xLTMuMy0yLjEtNi42LTMuMi05LjljMi44LTEsNS4xLTMsNi40LTUuNmMxLjUtMywxLjUtNi43LDAuMS05LjgNCgkJCUMyNS4xLDQuMiwyMy4xLDIuMywyMC45LDEuMnoiLz4NCgk8L2c+DQo8L2c+DQo8L3N2Zz4NCg==',
            76
        );

        add_submenu_page(
            $this->plugin_name,
            'SMS Campaign',
            'Campaign',
            'manage_options',
            $this->plugin_name,
            [$this, 'display_campaign_page']
        );

        add_submenu_page(
            $this->plugin_name,
            'Alpha SMS Settings',
            'Settings',
            'manage_options',
            $this->plugin_name . '_settings',
            [$this, 'display_setting_page']
        );
    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     */
    public function add_action_links($links)
    {

        /**
         * Documentation : https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
         * The "plugins.php" must match with the previously added add_submenu_page first option.
         * For custom post type you have to change 'plugins.php?page=' to 'edit.php?post_type=your_custom_post_type&page='
         */
        $settings_link = [
            '<a href="' . admin_url('admin.php?page=' . $this->plugin_name . '_settings') . '">' . __(
                'Settings',
                'alpha-sms'
            ) . '</a>',
        ];

        // -- OR --

        // $settings_link = array( '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_name ) . '">' . __( 'Settings', $this->plugin_name ) . '</a>', );

        return array_merge($settings_link, $links);
    }

    /**
     * Validate fields from admin area plugin settings form ('exopite-lazy-load-xt-admin-display.php')
     * @param mixed $input as field form settings form
     * @return mixed as validated fields
     */
    public function validate($input)
    {
        $options = get_option($this->plugin_name);

        if (!empty($input['api_key']) && strpos(esc_attr($input['api_key']), str_repeat('*', 24), '12')) {

            $input['api_key'] = $options['api_key'];
        }

        $sender_id = isset($input['sender_id']) ? trim(wp_unslash($input['sender_id'])) : '';

        $options['api_key'] = (isset($input['api_key']) && !empty($input['api_key'])) ? esc_attr($input['api_key']) : '';
        $options['sender_id'] = $sender_id !== '' ? esc_attr($sender_id) : '';

        $options['order_status'] = (isset($input['order_status']) && !empty($input['order_status'])) ? 1 : 0;
        $options['wp_reg'] = (isset($input['wp_reg']) && !empty($input['wp_reg'])) ? 1 : 0;
        $options['wp_login'] = (isset($input['wp_login']) && !empty($input['wp_login'])) ? 1 : 0;
        $options['wc_reg'] = (isset($input['wc_reg']) && !empty($input['wc_reg'])) ? 1 : 0;
        $options['wc_login'] = (isset($input['wc_login']) && !empty($input['wc_login'])) ? 1 : 0;
        $options['otp_checkout'] = (isset($input['otp_checkout']) && !empty($input['otp_checkout'])) ? 1 : 0;
        $options['admin_phones'] = (isset($input['admin_phones']) && !empty($input['admin_phones'])) ? esc_attr($input['admin_phones']) : '';
        $options['order_status_admin'] = (isset($input['order_status_admin']) && !empty($input['order_status_admin'])) ? 1 : 0;
        $options['ADMIN_STATUS_SMS'] = (isset($input['ADMIN_STATUS_SMS']) && !empty($input['ADMIN_STATUS_SMS'])) ? sanitize_textarea_field(wp_unslash($input['ADMIN_STATUS_SMS'])) : '';

        foreach ($this->get_customer_order_status_configs() as $status_config) {
            $enabled_key = $status_config['enabled_key'];
            $message_key = $status_config['message_key'];

            $options[$enabled_key] = !empty($input[$enabled_key]) ? 1 : 0;

            $message = isset($input[$message_key]) ? trim(wp_unslash($input[$message_key])) : '';
            $options[$message_key] = $message !== '' ? sanitize_textarea_field($message) : '';
        }

        if (!$this->checkAPI($options['api_key'])) {

            $options['wp_reg'] =
                $options['wp_login'] =
                $options['wc_reg'] =
                $options['wc_login'] =
                $options['otp_checkout'] =
                $options['order_status_admin'] = 0;

            foreach ($this->get_customer_order_status_configs() as $status_config) {
                $options[$status_config['enabled_key']] = 0;
            }

            $options['api_key'] = '';

            add_settings_error(
                $this->plugin_name,
                $this->plugin_name,
                __('Please configure a valid SMS API Key.', 'alpha-sms'),
                'error'
            );
        }

        return $options;
    }

    /**
     * Check if entered api key is valid or not
     * @return bool
     */
    private function checkAPI($api_key)
    {
        if (empty($api_key)) {
            return false;
        }

        require_once ALPHA_SMS_PATH . 'includes/sms.class.php';

        $smsPortal = new Alpha_SMS_Class($api_key);

        $response = $smsPortal->getBalance();

        return $response && $response->error === 0;
    }

    /**
     * update all settings
     */
    public function options_update()
    {
        register_setting($this->plugin_name, $this->plugin_name, [
            'sanitize_callback' => [$this, 'validate'],
        ]);
    }

    /**
     * send campaign msg to users
     */
    public function alpha_sms_send_campaign()
    {
        // Nonce verification
        if (!isset($_POST[$this->plugin_name]['_wpnonce'])) {
            $this->add_flash_notice(__('Security check failed. Please try again.', 'alpha-sms'), 'error');
            wp_safe_redirect(wp_get_referer());
            exit();
        }
        $nonce = sanitize_text_field(wp_unslash($_POST[$this->plugin_name]['_wpnonce']));
        if (!wp_verify_nonce($nonce, $this->plugin_name . '_send_campaign')) {
            $this->add_flash_notice(__('Security check failed. Please try again.', 'alpha-sms'), 'error');
            wp_safe_redirect(wp_get_referer());
            exit();
        }

        $numbersArr = [];
        $numbers = (isset($_POST[$this->plugin_name]['numbers']) && !empty($_POST[$this->plugin_name]['numbers'])) ? sanitize_textarea_field(wp_unslash($_POST[$this->plugin_name]['numbers'])) : '';
        $include_all_users = (isset($_POST[$this->plugin_name]['all_users']) && !empty($_POST[$this->plugin_name]['all_users'])) ? 1 : 0;
        $body = (isset($_POST[$this->plugin_name]['body']) && !empty($_POST[$this->plugin_name]['body'])) ? sanitize_textarea_field(wp_unslash($_POST[$this->plugin_name]['body'])) : false;

        //Grab all options
        $options = get_option($this->plugin_name);
        $api_key = !empty($options['api_key']) ? $options['api_key'] : '';
        $sender_id = !empty($options['sender_id']) ? trim($options['sender_id']) : '';

        // Empty body
        if (!$body) {
            $this->add_flash_notice(__("Fill the required fields properly", 'alpha-sms'), "error");
            wp_safe_redirect(wp_get_referer());
            exit();
        }
        if (!$api_key) {
            $this->add_flash_notice(__("No valid API Key is set.", 'alpha-sms'), "error");
            wp_safe_redirect(wp_get_referer());
            exit();
        }

        if ($numbers) {
            // split by new line
            $numbersArr = explode(PHP_EOL, $numbers);
        }

        if ($include_all_users) {
            $woo_numbers = $this->getCustomersPhone();
            $numbersArr = array_merge($numbersArr, $woo_numbers);
        }

        $numbersArr = array_map('trim', $numbersArr);
        $numbersArr = array_filter($numbersArr);
        $numbersArr = array_unique($numbersArr);

        if (empty($numbersArr)) {
            $this->add_flash_notice(__("No valid recipients were provided.", 'alpha-sms'), "error");
            wp_safe_redirect(wp_get_referer());
            exit();
        }

        $background = $this->get_background_processor();

        if (!$background) {
            $this->add_flash_notice(__("Background processing is unavailable. Please try again later.", 'alpha-sms'), "error");
            wp_safe_redirect(wp_get_referer());
            exit();
        }

        $queued = 0;
        $failedQueue = [];

        foreach ($numbersArr as $number) {
            if ($background->dispatch($number, $body, $sender_id, $api_key)) {
                $queued++;
            } else {
                $failedQueue[] = $number;
            }
        }

        if ($queued > 0) {
            /* translators: %d is the number of SMS messages queued for background sending. */
            $notice = sprintf(
                /* translators: %d is the number of SMS messages queued for background sending. */
                _n('Queued %d SMS message for background sending.', 'Queued %d SMS messages for background sending.', $queued, 'alpha-sms'),
                $queued
            );
            $this->add_flash_notice(esc_html($notice), 'success');
        }

        if (!empty($failedQueue)) {
            $failedQueue = array_map('sanitize_text_field', $failedQueue);
            $preview = array_slice($failedQueue, 0, 5);
            $summary = implode(', ', $preview);
            if ('' === trim($summary)) {
                /* translators: This is a summary of unknown recipients that could not be queued. */
                $summary = __('unknown recipients', 'alpha-sms');
            }

            $message = sprintf(
                /* translators: %1$d is the number of recipients that could not be queued, %2$s is a summary of the failure. */
                __('Unable to queue %1$d recipient(s): %2$s', 'alpha-sms'),
                count($failedQueue),
                $summary
            );
            /* translators: %d: The number of additional failed items not shown in the preview. */
            if (count($failedQueue) > count($preview)) {
                /* translators: %d is the number of additional failed items not shown in the preview. */
                $message .= ' ' . sprintf(__('and %d more.', 'alpha-sms'), count($failedQueue) - count($preview));
            }

            $this->add_flash_notice(esc_html($message), 'error');
        }

        // Redirect to plugin page
        wp_safe_redirect(wp_get_referer());
        exit();
    }

    /**
     * Add a flash notice to {prefix}options table until a full page refresh is done
     *
     * @param string $notice our notice message
     * @param string $type This can be "info", "warning", "error" or "success", "warning" as default
     * @param boolean $dismissible set this to TRUE to add is-dismissible functionality to your notice
     * @return void
     */

    public function add_flash_notice($notice = "", $type = "warning", $dismissible = true)
    {
        // Here we return the notices saved on our option, if there are no notices, then an empty array is returned
        $notices = get_option($this->plugin_name . '_notices', []);

        $dismissible_text = ($dismissible) ? "is-dismissible" : "";

        // We add our new notice.
        $notices[] = [
            "notice" => $notice,
            "type" => $type,
            "dismissible" => $dismissible_text,
        ];

        // Then we update the option with our notices array
        update_option($this->plugin_name . '_notices', $notices);
    }

    public function getCustomersPhone()
    {
        global $wpdb;

        $cache_key = 'alpha_sms_customers_phone';
        $phones = wp_cache_get($cache_key, 'alpha_sms');
        if ($phones === false) {
            // Direct database query is required to efficiently filter users by meta value and role.
            // WordPress functions like get_users() do not support this combined filtering in a single query.
            // Caching is implemented to mitigate performance impact and reduce repeated queries.
            $phones = $wpdb->get_col("
                SELECT DISTINCT um.meta_value FROM {$wpdb->prefix}users as u
                INNER JOIN {$wpdb->prefix}usermeta as um ON um.user_id = u.ID
                INNER JOIN {$wpdb->prefix}usermeta as um2 ON um2.user_id = u.ID
                WHERE um.meta_key LIKE 'billing_phone' AND um.meta_value != ''
                AND um2.meta_key LIKE 'wp_capabilities' AND um2.meta_value NOT LIKE '%administrator%'
            ");
            wp_cache_set($cache_key, $phones, 'alpha_sms', HOUR_IN_SECONDS);
        }
        return $phones;
    }

    /**
     * Persist job results as flash notices for display in the admin area.
     *
     * @return void
     */
    private function maybe_add_job_result_notice()
    {
        $results = get_option($this->plugin_name . '_job_results', []);

        if (empty($results) || !is_array($results)) {
            return;
        }

        $defaults = [
            'success'    => 0,
            'failed'     => 0,
            'last_error' => '',
            'failures'   => [],
        ];

        $results = wp_parse_args($results, $defaults);

        if (!empty($results['success'])) {
            $success_notice = sprintf(
                /* translators: %d is the number of SMS messages sent successfully. */
                _n(
                    '%d SMS message was sent successfully.',
                    '%d SMS messages were sent successfully.',
                    (int)$results['success'],
                    'alpha-sms'
                ),
                (int)$results['success']
            );
            $this->add_flash_notice(esc_html($success_notice), 'success');
        }

        if (!empty($results['failed'])) {
            $error_notice = sprintf(
                /* translators: %d is the number of SMS messages that failed to send. */
                _n(
                    '%d SMS message failed to send.',
                    '%d SMS messages failed to send.',
                    (int)$results['failed'],
                    'alpha-sms'
                ),
                (int)$results['failed']
            );

            if (!empty($results['last_error'])) {
                $error_notice .= ' ' . sprintf(
                    /* translators: %s is the last error message. */
                    __('Last error: %s', 'alpha-sms'),
                    $results['last_error']
                );
            } elseif (!empty($results['failures']) && is_array($results['failures'])) {
                $details = [];
                foreach ($results['failures'] as $failure) {
                    $number = isset($failure['number']) ? $failure['number'] : '';
                    $message = isset($failure['message']) ? $failure['message'] : '';

                    $detail_parts = [];
                    $trimmed_number = trim($number);
                    if ($trimmed_number !== '') {
                        $detail_parts[] = $trimmed_number;
                    }
                    if ($message !== '') {
                        $detail_parts[] = $message;
                    }

                    if (!empty($detail_parts)) {
                        $details[] = implode(' - ', $detail_parts);
                    }
                }

                if (!empty($details)) {
                    $error_notice .= ' ' . sprintf(
                        /* translators: %s will be replaced with the error details (single or multiple, separated by semicolons). The singular ('Latest error: %s') and plural ('Latest errors: %s') forms are used depending on the number of errors. */
                        _n('Latest error: %s', 'Latest errors: %s', count($details), 'alpha-sms'),
                        implode('; ', $details)
                    );
                }
            }

            $this->add_flash_notice(esc_html($error_notice), 'error');
        }

        delete_option($this->plugin_name . '_job_results');
    }

    /**
     * Function executed when the 'admin_notices' action is called, here we check if there are notices on
     * our database and display them, after that, we remove the option to prevent notices being displayed forever.
     * @return void
     */

    public function display_flash_notices()
    {
        $this->maybe_add_job_result_notice();

        $notices = get_option($this->plugin_name . '_notices', []);

        // Iterate through our notices to be displayed and print them.
        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>',
                esc_attr($notice['type']),
                esc_attr($notice['dismissible']),
                esc_html($notice['notice'])
            );
        }

        // Now we reset our options to prevent notices being displayed forever.
        if (!empty($notices)) {
            delete_option($this->plugin_name . '_notices', []);
        }
    }
}
