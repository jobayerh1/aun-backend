<?php

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Alpha_sms
 * @subpackage Alpha_sms/public
 * @author     Alpha Net Developer Team <support@alpha.net.bd>
 */
class Alpha_sms_Public
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
    private $options;
    /**
     * @var false
     */
    private $pluginActive;

    /**
     * Cached OTP storage key for the current visitor.
     *
     * @var string|null
     */
    private $otpTransientKey = null;

    /**
     * Maximum number of OTP requests allowed for guest checkout within the rate limit window.
     *
     * @var int
     */
    private $checkoutOtpRateLimit = 4;

    /**
     * Time window in seconds for guest checkout OTP rate limiting.
     *
     * @var int
     */
    private $checkoutOtpRateWindow = 900;

    /**
     * Initialize the class and set its properties.
     *
     * @param  string  $plugin_name  The name of the plugin.
     * @param  string  $version      The version of this plugin.
     *
     * @since    1.0.0
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name  = $plugin_name;
        $this->version      = $version;
        $this->options      = get_option($this->plugin_name);
        $this->pluginActive = ! empty($this->options['api_key']);
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

    private function get_order_status_label($status_key)
    {
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : $this->get_default_wc_order_statuses();

        if (isset($statuses[$status_key])) {
            return wp_strip_all_tags($statuses[$status_key]);
        }

        $normalized = $this->normalize_order_status_key($status_key);

        return $normalized !== '' ? ucwords(str_replace('_', ' ', $normalized)) : $status_key;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
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

        if ($this->pluginActive) {
            wp_enqueue_style(
                $this->plugin_name,
                plugin_dir_url(__FILE__) . 'css/alpha_sms-public.css',
                [],
                $this->version,
                'all'
            );
        }
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
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

        if ($this->pluginActive) {
            wp_enqueue_script(
                $this->plugin_name,
                plugin_dir_url(__FILE__) . 'js/alpha_sms-public.js',
                ['jquery'],
                $this->version,
                false
            );

            // adding a js variable for ajax form submit url
            wp_localize_script(
                $this->plugin_name,
                $this->plugin_name . '_object',
                [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    $this->plugin_name . '_checkout_nonce' => wp_create_nonce('alpha_sms_checkout_otp'),
                    'checkout_otp' => ! empty($this->options['otp_checkout']) ? 'yes' : 'no',
                ]
            );
        }
    }

    /**
     * Woocommerce
     * show phone number on register page and my account
     */
    public function wc_phone_on_register()
    {
        if (! $this->pluginActive || ! $this->options['wc_reg']) {
            return;
        }

        // Nonce verification for form submission
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['billing_phone'])) {
            $wc_reg_phone_nonce = isset($_POST['wc_reg_phone_nonce']) ? sanitize_text_field(wp_unslash($_POST['wc_reg_phone_nonce'])) : '';
            if (empty($wc_reg_phone_nonce) || ! wp_verify_nonce($wc_reg_phone_nonce, 'wc_reg_phone_action')) {
                // Optionally, you can show an error message here
                return;
            }
        }

        $user  = wp_get_current_user();
        $value = isset($_POST['billing_phone']) ? sanitize_text_field(wp_unslash($_POST['billing_phone'])) : $user->billing_phone;

        wp_nonce_field('wc_reg_phone_action', 'wc_reg_phone_nonce');
?>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="reg_billing_phone"><?php esc_html_e('Phone', 'alpha-sms'); ?> <span class="required">*</span>
            </label>
            <input type="tel" minlength="11" maxlength="11" class="input-text" name="billing_phone" id="reg_billing_phone" value="<?php echo esc_attr($value); ?>" required />
        </p>
        <div class="clear"></div>

    <?php
    }

    /**
     *  Default WordPress
     * show otp form in registration form
     */
    public function add_otp_field_on_wp_reg_form()
    {
        if (! $this->pluginActive || ! $this->options['wp_reg']) {
            return;
        }
        require_once 'partials/add-otp-on-login-form.php';
    }

    /**
     * Woocommerce
     * show otp form in registration form
     */
    public function add_otp_field_on_wc_reg_form()
    {
        if (! $this->pluginActive || ! $this->options['wc_reg']) {
            return;
        }

        require_once 'partials/add-otp-on-wc-reg-form.php';
    ?>
        <input type='hidden' name='action_type' id='action_type' value='wc_reg' />
    <?php
    }

    /**
     * Woocommerce + Default WordPress
     * ajax otp send on post phone number *
     */
    public function send_otp_for_reg()
    {
        $user_phone = '';
        // Require and validate nonce for AJAX requests. Fail early if missing/invalid.
        $action_type = isset($_POST['action_type']) ? sanitize_text_field(wp_unslash($_POST['action_type'])) : '';
        $nonce_ok = false;

        // WC registration nonce
        if ($action_type === 'wc_reg') {
            $wc_reg_phone_nonce = isset($_POST['wc_reg_phone_nonce']) ? sanitize_text_field(wp_unslash($_POST['wc_reg_phone_nonce'])) : '';
            if (empty($wc_reg_phone_nonce) || ! wp_verify_nonce($wc_reg_phone_nonce, 'wc_reg_phone_action')) {
                $response = [
                    'status'  => 403,
                    'message' => __('Security Check failed. Please reload the page and try again.', 'alpha-sms'),
                ];
                echo wp_kses_post(json_encode($response));
                wp_die();
                exit;
            }
            $nonce_ok = true;
        }

        // WP registration nonce
        if ($action_type === 'wp_reg') {
            $wp_reg_phone_nonce = isset($_POST['wp_reg_phone_nonce']) ? sanitize_text_field(wp_unslash($_POST['wp_reg_phone_nonce'])) : '';
            if (empty($wp_reg_phone_nonce) || ! wp_verify_nonce($wp_reg_phone_nonce, 'wp_reg_phone_action')) {
                $response = [
                    'status'  => 403,
                    'message' => __('Security Check failed. Please reload the page and try again.', 'alpha-sms'),
                ];
                echo wp_kses_post(json_encode($response));
                wp_die();
                exit;
            }
            $nonce_ok = true;
        }

        // Guest checkout / other actions that rely on WooCommerce checkout nonce
        if ($action_type === 'wc_checkout') {
            check_ajax_referer('alpha_sms_checkout_otp', 'alpha_sms_checkout_nonce');

            $nonce_ok = true;
        }

        // If action_type is missing or not recognized we cannot safely continue.
        if (! $nonce_ok) {
            $response = [
                'status'  => 403,
                'message' => __('Security Check failed. Missing or invalid action type.', 'alpha-sms'),
            ];
            echo wp_kses_post(json_encode($response));
            wp_die();
            exit;
        }

        if (isset($_POST['billing_phone'])) {
            $user_phone = $this->validateNumber(sanitize_text_field(wp_unslash($_POST['billing_phone'])));
        }

        $password = isset($_POST['password']) ? sanitize_text_field(wp_unslash($_POST['password'])) : '';
        if (! empty($password) && strlen($password) < 8) {
            /* translators: Error message shown when password is too weak. */
            $response = [
                'status' => 400,
                /* translators: Error message shown when password is too weak. */
                'message' => __('Weak - Please enter a stronger password.', 'alpha-sms')
            ];
            echo wp_kses_post(json_encode($response));
            wp_die();
            exit;
        }

        if (! $user_phone) {
            /* translators: Error message shown when phone number is not valid. */
            $response = [
                'status' => 400,
                /* translators: Error message shown when phone number is not valid. */
                'message' => __('The phone number you entered is not valid!', 'alpha-sms')
            ];
            echo wp_kses_post(json_encode($response));
            wp_die();
            exit;
        }

        $is_checkout_request = ! empty($_POST['action_type']) && $_POST['action_type'] === 'wc_checkout';

        if ($is_checkout_request && $this->is_checkout_rate_limited()) {
            $response = [
                'status'  => 429,
                /* translators: Error message shown when user reaches OTP request limit. */
                'message' => __('You have reached the maximum number of OTP requests. Please try again in 15 minutes.', 'alpha-sms'),
            ];
            echo wp_kses_post(json_encode($response));
            wp_die();
            exit;
        }

        // check for already send otp by checking expiration
        $otp_expires = $this->get_otp_store_value('alpha_sms_expires');

        $current_utc    = current_time('timestamp', true);
        $otp_expires_ts = strtotime($otp_expires);
        if (! empty($otp_expires) && $otp_expires_ts > $current_utc) {
            $response = [
                'status'  => 400,
                'message' => 'OTP already sent to a phone number. Please try again after ' . gmdate('i:s', $otp_expires_ts - $current_utc) . ' min',
            ];
            echo wp_kses_post(json_encode($response));
            wp_die();
            exit;
        }

        //we will send sms
        $otp_code = $this->generateOTP();

        $body = 'Your OTP for ' . get_bloginfo() . ' registration is ' . $otp_code . '. Valid for 2 min. Contact us if you need help.';

        if ($is_checkout_request) {
            $body = 'Your OTP for secure order checkout on ' . get_bloginfo() . ' is ' . $otp_code . '. Use it within 2 min to complete the checkout process.';
        }

        $sms_response = $this->SendSMS($user_phone, $body);

        if ($sms_response->error === 0) {
            // save info in database for later verification
            if ($this->log_login_register_action(
                $user_phone,
                $otp_code
            )) {
                if ($is_checkout_request && ! is_user_logged_in()) {
                    $this->record_checkout_otp_request();
                }
                $response = [
                    'status'  => 200,
                    'message' => 'A OTP (One Time Passcode) has been sent. Please enter the OTP in the field below to verify your phone.',
                ];
            } else {
                /* translators: Error message shown when an error occurs while sending OTP. */
                $response = ['status' => 400, 'message' => __('Error occurred while sending OTP. Please try again.', 'alpha-sms')];
            }

            echo wp_kses_post(json_encode($response));
            wp_die();
            $response = ['status' => 403, 'message' => __('Security Check failed. Please reload the page and try again.', 'alpha-sms')];
            /* translators: Error message shown when security check fails during OTP send. */
        }

        $response = ['status' => '400', 'message' => __('Error occurred while sending OTP. Contact Administrator.', 'alpha-sms')];
        /* translators: Error message shown when an error occurs while sending OTP and user should contact admin. */
        /* translators: Error message shown when phone number is not valid. */
        echo wp_kses_post(json_encode($response));
        wp_die();
        exit;
    }

    /*
    * $response = ['status' => 403, 'message' => __('Security Check failed. Please reload the page and try again.', 'alpha-sms')];
     *
     * @param $num
     *
     * @return false|int|string
     */
    public function validateNumber($num)
    {
        if (! $num) {
            return false;
        }

        $num    = ltrim(trim($num), "+88");
        $number = '88' . ltrim($num, "88");

        $ext = ["88017", "88013", "88016", "88015", "88018", "88019", "88014"];
        if (is_numeric($number) && strlen($number) === 13 && in_array(substr($number, 0, 5), $ext, true)) {
            return $number;
        }

        return false;
    }

    /**
     * Generate 6 digit otp code
     * $response = ['status' => 400, 'message' => __('The phone number you entered is not valid!', 'alpha-sms')];
     */
    public function generateOTP()
    {
        $otp = '';

        for ($i = 0; $i < 6; $i++) {
            $otp .= wp_rand(0, 9);
        }
        return $otp;
    }

    /**
     * Send SMS via sms api
     *
     * @param $to
     * @param $body
     *
     * @return false|mixed
     */
    public function SendSMS($to, $body)
    {
        if (! $this->pluginActive) {
            return false;
        }

        $api_key   = ! empty($this->options['api_key']) ? $this->options['api_key'] : '';
        $sender_id = ! empty($this->options['sender_id']) ? trim($this->options['sender_id']) : '';

        require_once ALPHA_SMS_PATH . 'includes/sms.class.php';

        $sms          = new Alpha_SMS_Class($api_key);
        $sms->numbers = $to;
        $sms->body    = $body;

        if ('' !== $sender_id) {
            $sms->sender_id = $sender_id;
        }

        return $sms->Send();
    }

    /**
     * After sending OTP to the user, store the OTP metadata for verification.
     *
     * @param $mobile_phone
     * @param $otp_code
     *
     * @return bool
     */
    public function log_login_register_action(
        $mobile_phone,
        $otp_code
    ) {
        $dateTime = new DateTime('@' . current_time('timestamp', true));
        $dateTime->modify('+3 minutes');
        $expires_at = $dateTime->format('Y-m-d H:i:s');

        $stored = $this->set_transient_otp_data(
            [
                'alpha_sms_otp_phone' => $mobile_phone,
                'alpha_sms_otp_code'  => $otp_code,
                'alpha_sms_expires'   => $expires_at,
            ],
            $expires_at
        );

        if (! $stored) {
            return false;
        }

        $snapshot = $this->get_transient_otp_data();

        return ! empty($snapshot['alpha_sms_otp_code']);
    }

    /**
     * Determine whether the visitor has reached the OTP request limit for guest checkout.
     *
     * @return bool
     */
    private function is_checkout_rate_limited()
    {
        $now      = $this->get_current_timestamp();
        $requests = $this->filter_checkout_otp_requests($now);

        $this->persist_checkout_otp_requests($requests, $now);

        return count($requests) >= $this->get_checkout_rate_limit();
    }

    /**
     * Record a successful OTP request for guest checkout.
     */
    private function record_checkout_otp_request()
    {
        $now      = $this->get_current_timestamp();
        $requests = $this->filter_checkout_otp_requests($now);

        $requests[] = $now;

        $limit = $this->get_checkout_rate_limit();

        if (count($requests) > $limit) {
            $requests = array_slice($requests, -$limit);
        }

        $this->persist_checkout_otp_requests($requests, $now);
    }

    /**
     * Retrieve the maximum number of OTP requests allowed in the rate limit window.
     *
     * @return int
     */
    private function get_checkout_rate_limit()
    {
        return (int) $this->checkoutOtpRateLimit;
    }

    /**
     * Retrieve the guest checkout OTP rate limit window in seconds.
     *
     * @return int
     */
    private function get_checkout_rate_window()
    {
        return (int) $this->checkoutOtpRateWindow;
    }

    /**
     * Fetch stored OTP request timestamps for guest checkout.
     *
     * @return array
     */
    private function get_checkout_otp_requests()
    {
        $data = $this->get_transient_otp_data();

        if (empty($data['alpha_sms_checkout_otp_requests']) || ! is_array($data['alpha_sms_checkout_otp_requests'])) {
            return [];
        }

        return array_map('intval', $data['alpha_sms_checkout_otp_requests']);
    }

    /**
     * Remove OTP request timestamps that fall outside the rate limit window.
     *
     * @param int $now
     *
     * @return array
     */
    private function filter_checkout_otp_requests($now)
    {
        $window   = $this->get_checkout_rate_window();
        $earliest = $now - $window;

        $requests = $this->get_checkout_otp_requests();
        $filtered = [];

        foreach ($requests as $request) {
            if ($request >= $earliest) {
                $filtered[] = $request;
            }
        }

        return array_values($filtered);
    }

    /**
     * Persist filtered OTP request timestamps back to the transient store.
     *
     * @param array $requests
     * @param int   $now
     */
    private function persist_checkout_otp_requests(array $requests, $now)
    {
        $expiryTimestamp = $now + $this->get_checkout_rate_window();
        $expires_at      = gmdate('Y-m-d H:i:s', $expiryTimestamp);

        $data = [
            'alpha_sms_checkout_otp_requests' => $requests,
        ];

        $this->set_transient_otp_data($data, $expires_at);
    }

    /**
     * Resolve the current timestamp for rate limiting operations.
     *
     * @return int
     */
    private function get_current_timestamp()
    {
        $timestamp = current_time('timestamp', true);
        if (! $timestamp) {
            $timestamp = time();
        }

        return $timestamp;
    }

    /**
     * Verify otp and register the user
     *
     * @param $customer_id
     */
    public function register_the_customer($customer_id)
    {
        if (! $this->pluginActive || (! $this->options['wp_reg'] && ! $this->options['wc_reg'])) {
            return;
        }

        $is_post_request = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
        $action_type     = isset($_POST['action_type']) ? sanitize_text_field(wp_unslash($_POST['action_type'])) : '';

        // Nonce validation for WooCommerce registration phone field: require nonce when wc_reg option enabled
        if ($is_post_request && $action_type === 'wc_reg' && ! empty($this->options['wc_reg'])) {
            $wc_reg_phone_nonce = isset($_POST['wc_reg_phone_nonce']) ? sanitize_text_field(wp_unslash($_POST['wc_reg_phone_nonce'])) : '';
            if (empty($wc_reg_phone_nonce) || ! function_exists('wp_verify_nonce') || ! wp_verify_nonce($wc_reg_phone_nonce, 'wc_reg_phone_action')) {
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Security Check failed. Please try again.', 'alpha-sms'), 'error');
                } else {
                    echo esc_html(__('Security Check failed. Please try again.', 'alpha-sms'));
                }
                return;
            }
        }

        // Nonce validation for WP registration phone field: require nonce when wp_reg option enabled
        if ($is_post_request && $action_type === 'wp_reg' && ! empty($this->options['wp_reg'])) {
            $wp_reg_phone_nonce = isset($_POST['wp_reg_phone_nonce']) ? sanitize_text_field(wp_unslash($_POST['wp_reg_phone_nonce'])) : '';
            if (empty($wp_reg_phone_nonce) || ! function_exists('wp_verify_nonce') || ! wp_verify_nonce($wp_reg_phone_nonce, 'wp_reg_phone_action')) {
                if (function_exists('add_filter')) {
                    add_filter('registration_errors', function ($errors) {
                        $errors->add('security_error', __('Security Check failed. Please try again.', 'alpha-sms'));
                        return $errors;
                    });
                } else {
                    echo esc_html(__('Security Check failed. Please try again.', 'alpha-sms'));
                }
                return;
            }
        }

        if (isset($_POST['billing_phone'])) {
            $billing_phone = sanitize_text_field(wp_unslash($_POST['billing_phone']));
            if ($this->validateNumber($billing_phone)) {
                $this->save_verified_phone_to_user_profile($customer_id, $billing_phone);
            }
        }
    }

    /**
     * Save verified phone to user profile meta
     *
     * @param int    $user_id User ID
     * @param string $phone   Phone number
     */
    public function save_verified_phone_to_user_profile($user_id, $phone)
    {
        if (empty($user_id) || empty($phone)) {
            return;
        }

        $validated_phone = $this->validateNumber($phone);
        if (!$validated_phone) {
            return;
        }

        update_user_meta($user_id, 'billing_phone', $validated_phone);
        update_user_meta($user_id, 'mobile_phone', $validated_phone);
    }

    /**
     * Default WordPress
     * show phone number on register page
     */
    public function wp_phone_on_register()
    {
        if (! $this->pluginActive || ! $this->options['wp_reg']) {
            return;
        }


        // Nonce verification for WP registration phone field
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['billing_phone'])) {
            $wp_reg_phone_nonce = isset($_POST['wp_reg_phone_nonce']) ? sanitize_text_field(wp_unslash($_POST['wp_reg_phone_nonce'])) : '';
            if (empty($wp_reg_phone_nonce) || ! wp_verify_nonce($wp_reg_phone_nonce, 'wp_reg_phone_action')) {
                return;
            }
        }

        $billing_phone = (! empty($_POST['billing_phone'])) ? sanitize_text_field(wp_unslash($_POST['billing_phone'])) : '';

        // Add nonce field for WP registration phone
        wp_nonce_field('wp_reg_phone_action', 'wp_reg_phone_nonce');
    ?>
        <p>
            <label for="billing_phone"><?php esc_html_e('Phone', 'alpha-sms'); ?><br />
                <input type="text" name="billing_phone" id="reg_billing_phone" class="input" value="<?php echo esc_attr($billing_phone); ?>" size="25" /></label>
        </p>
    <?php
    }

    /**
     * WordPress validate phone and validate otp
     *
     * @param $errors
     * @param $sanitized_user_login
     * @param $user_email
     *
     * @return mixed
     */
    public function wp_register_form_validation($errors, $sanitized_user_login, $user_email)
    {
        if (
            $this->pluginActive && $this->options['wp_reg'] && ! empty($_POST['action_type']) &&
            $_POST['action_type'] === 'wp_reg'
        ) {
            // Nonce verification for WP registration form
            $wp_reg_phone_nonce = isset($_POST['wp_reg_phone_nonce']) ? sanitize_text_field(wp_unslash($_POST['wp_reg_phone_nonce'])) : '';
            if (empty($wp_reg_phone_nonce) || ! wp_verify_nonce($wp_reg_phone_nonce, 'wp_reg_phone_action')) {
                $errors->add('security_error', __('Security Check failed. Please try again.', 'alpha-sms'));
                return $errors;
            }
            $this->register_form_validation($errors, $sanitized_user_login, $user_email);
        }

        return $errors;
    }

    /**
     * Register Form Validation
     *
     * @param $errors
     * @param $sanitized_user_login
     * @param $user_email
     *
     * @return mixed
     */
    public function register_form_validation($errors, $sanitized_user_login, $user_email)
    {

        $enable_guest_checkout = get_option('woocommerce_enable_guest_checkout');
        $enable_guest_checkout = $enable_guest_checkout === 'yes' ? true : false;

        $action_type = isset($_REQUEST['action_type']) ? sanitize_text_field(wp_unslash($_REQUEST['action_type'])) : '';

        $shouldValidate = $this->pluginActive && (
            (! empty($this->options['otp_checkout']) && ! $enable_guest_checkout) ||
            (! empty($this->options['wc_reg']) && $action_type === 'wc_reg') ||
            (! empty($this->options['wp_reg']) && $action_type === 'wp_reg')
        );

        // Nonce verification for WP registration form
        if (! empty($this->options['wp_reg']) && $action_type === 'wp_reg') {
            $wp_reg_phone_nonce = isset($_POST['wp_reg_phone_nonce']) ? sanitize_text_field(wp_unslash($_POST['wp_reg_phone_nonce'])) : '';
            if (empty($wp_reg_phone_nonce) || ! wp_verify_nonce($wp_reg_phone_nonce, 'wp_reg_phone_action')) {
                $errors->add('security_error', __('Security Check failed. Please try again.', 'alpha-sms'));
                return $errors;
            }
        }

        if (! $shouldValidate) {
            return $errors;
        }

        $billing_phone = isset($_REQUEST['billing_phone']) ? sanitize_text_field(wp_unslash($_REQUEST['billing_phone'])) : '';
        if (
            empty($billing_phone) || ! is_numeric($billing_phone) ||
            ! $this->validateNumber($billing_phone)
        ) {
            /* translators: Error message shown when phone number is not valid. */
            $errors->add('phone_error', __('You phone number is not valid.', 'alpha-sms'));
        }

        $billing_phone_valid = $this->validateNumber($billing_phone);

        $hasPhoneNumber = get_users([
            'meta_key'   => 'billing_phone',
            'meta_value' => $billing_phone_valid,
        ]);

        if (! empty($hasPhoneNumber)) {
            /* translators: Error message shown when mobile number is already used. */
            $errors->add('duplicate_phone_error', __('Mobile number is already used!', 'alpha-sms'));
        }

        if (! empty($_REQUEST['otp_code'])) {
            $otp_code = sanitize_text_field(wp_unslash($_REQUEST['otp_code']));

            $valid_user = $this->authenticate_otp(trim($otp_code));

            if ($valid_user) {
                $this->deletePastData();

                return $errors;
            }
        }

        // otp validation failed or no otp provided
        /* translators: Error message shown when invalid OTP is entered. */
        $errors->add('otp_error', __('Invalid OTP entered!', 'alpha-sms'));
        return $errors;
    }

    /**
     * Validate guest checkout otp
     *
     * @param $errors
     * @param $sanitized_user_login
     * @param $user_email
     *
     * @return mixed
     */
    public function validate_guest_checkout_otp()
    {
        $enable_guest_checkout = get_option('woocommerce_enable_guest_checkout');
        $enable_guest_checkout = $enable_guest_checkout === 'yes' ? true : false;

        if (! $this->pluginActive || ! $this->options['otp_checkout'] || ! $enable_guest_checkout) {
            return;
        }

        if (! empty($_REQUEST['otp_code'])) {
            $otp_code = sanitize_text_field(wp_unslash($_REQUEST['otp_code']));

            $valid_user = $this->authenticate_otp(trim($otp_code));

            if ($valid_user) {
                $this->deletePastData();
            } else {
                /* translators: Error message shown when user must enter a valid OTP. */
                wc_add_notice(__('Please enter a valid OTP.', 'alpha-sms'), 'error');
            }
        } else {
            wc_add_notice(__('Please enter a valid OTP.', 'alpha-sms'), 'error');
        }
    }

    /**
     * Select otp from db and compare
     *
     * @param $otp_code
     *
     * @return bool
     */
    public function authenticate_otp($otp_code)
    {
        $otp_code_session    = $this->get_otp_store_value('alpha_sms_otp_code');
        $otp_expires_session = $this->get_otp_store_value('alpha_sms_expires');

        if (! empty($otp_code_session) && ! empty($otp_expires_session)) {
            $current_utc    = current_time('timestamp', true);
            $otp_expires_ts = strtotime($otp_expires_session);
            if ($otp_expires_ts > $current_utc) {
                if ($otp_code === $otp_code_session) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Clear stored OTP data for the current visitor.
     */
    public function deletePastData()
    {
        $this->clear_transient_otp_data();
    }

    /**
     * Retrieve a stored OTP value from the WordPress transient store.
     *
     * @param string $key
     *
     * @return mixed|string
     */
    private function get_otp_store_value($key)
    {
        $data = $this->get_transient_otp_data();

        return isset($data[$key]) ? $data[$key] : '';
    }

    /**
     * Resolve the transient key used to persist OTP state for the visitor.
     *
     * @return string
     */
    private function get_otp_transient_key()
    {
        if (! empty($this->otpTransientKey)) {
            return $this->otpTransientKey;
        }

        $session_id = '';

        if (isset($_COOKIE['alpha_sms_session'])) {
            $session_id = sanitize_text_field(wp_unslash($_COOKIE['alpha_sms_session']));
        }

        if (empty($session_id)) {
            $session_id = sanitize_key(wp_generate_password(32, false, false));

            if (! headers_sent()) {
                $path   = defined('COOKIEPATH') ? COOKIEPATH : '/';
                $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
                $secure = function_exists('is_ssl') ? is_ssl() : false;
                $ttl    = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;

                setcookie('alpha_sms_session', $session_id, time() + $ttl, $path, $domain, $secure, true);
            }

            $_COOKIE['alpha_sms_session'] = $session_id;
        }

        $this->otpTransientKey = 'alpha_sms_otp_' . $session_id;

        return $this->otpTransientKey;
    }

    /**
     * Fetch the stored OTP payload from WordPress transients.
     *
     * @return array
     */
    private function get_transient_otp_data()
    {
        $transient_key = $this->get_otp_transient_key();

        if (empty($transient_key)) {
            return [];
        }

        $data = get_transient($transient_key);

        return is_array($data) ? $data : [];
    }

    /**
     * Persist OTP data to WordPress transients.
     *
     * @param array  $data
     * @param string $expires_at
     *
     * @return bool
     */
    private function set_transient_otp_data(array $data, $expires_at)
    {
        $transient_key = $this->get_otp_transient_key();

        if (empty($transient_key)) {
            return false;
        }

        $payload    = array_merge($this->get_transient_otp_data(), $data);
        $expiration = $this->calculate_transient_expiration($expires_at);

        return set_transient($transient_key, $payload, $expiration);
    }

    /**
     * Clear any stored OTP data from WordPress transients.
     */
    private function clear_transient_otp_data()
    {
        $transient_key = $this->get_otp_transient_key();

        if (empty($transient_key)) {
            return;
        }

        delete_transient($transient_key);
    }

    /**
     * Determine how long OTP data should live in transients.
     *
     * @param string $expires_at
     *
     * @return int
     */
    private function calculate_transient_expiration($expires_at)
    {
        $minimum  = defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS : 60;
        $fallback = 3 * $minimum;

        if (empty($expires_at)) {
            return $fallback;
        }

        $expires_timestamp = strtotime($expires_at);

        if (! $expires_timestamp) {
            return $fallback;
        }

        $now = current_time('timestamp', true);
        if (! $now) {
            $now = time();
        }

        $ttl = $expires_timestamp - $now;

        if ($ttl <= 0) {
            return $minimum;
        }

        return $ttl;
    }

    /**
     * Woocommerce validate phone and validate otp
     *
     * @param $errors
     * @param $sanitized_user_login
     * @param $user_email
     *
     * @return mixed
     */
    public function wc_register_form_validation($errors, $sanitized_user_login, $user_email)
    {
        if (! $this->pluginActive) {
            return $errors;
        }

        // Nonce verification for WooCommerce registration form
        if (!empty($this->options['wc_reg']) && isset($_POST['action_type']) && $_POST['action_type'] === 'wc_reg') {
            $wc_reg_phone_nonce = isset($_POST['wc_reg_phone_nonce']) ? sanitize_text_field(wp_unslash($_POST['wc_reg_phone_nonce'])) : '';
            if (empty($wc_reg_phone_nonce) || !wp_verify_nonce($wc_reg_phone_nonce, 'wc_reg_phone_action')) {
                $errors->add('security_error', __('Security Check failed. Please try again.', 'alpha-sms'));
                return $errors;
            }
        }

        if ($this->options['otp_checkout'] || ($this->options['wc_reg'] && isset($_POST['action_type']) && $_POST['action_type'] === 'wc_reg')) {
            $this->register_form_validation($errors, $sanitized_user_login, $user_email);
        }

        return $errors;
    }

    /**
     * Alert customer and admins when a new order is placed
     *
     * @param $order_id
     */
    public function wc_new_order_alert($order_id)
    {
        if (! $order_id) {
            return;
        }

        // new order status pending notification for customer
        $this->wc_order_status_change_alert($order_id, 'pending', 'pending');

        // option not enabled
        if (! $this->pluginActive || ! isset($this->options['order_status_admin']) || ! $this->options['order_status_admin']) {
            return;
        }

        // send sms to all admins if enabled
        $order = new WC_Order($order_id);

        $admin_msg = $this->options['ADMIN_STATUS_SMS'];

        $search = [
            '[store_name]',
            '[billing_first_name]',
            '[order_id]',
            '[order_status]',
            '[order_currency]',
            '[order_amount]',
            '[order_date_created]',
            '[order_date_completed]',
        ];

        $order_created   = wp_date('d M Y', strtotime($order->get_date_created()));
        $order_completed = ! empty($order->get_date_completed()) ? wp_date('d M Y', strtotime($order->get_date_completed())) : '';

        $replace = [
            get_bloginfo(),
            $order->get_billing_first_name(),
            $order_id,
            'pending',
            $order->get_currency(),
            $order->get_total(),
            $order_created,
            $order_completed,
        ];

        $admin_msg = str_replace($search, $replace, $admin_msg);

        // if admin phone is not provided then send to all admins
        $admin_phones[] = $this->options['admin_phones'];

        if (empty($admin_phones)) {
            $admin_phones = $this->admin_phones();
        }

        if (! empty($admin_phones)) {
            $numbers = implode(',', $admin_phones);
            $this->SendSMS($numbers, $admin_msg);
        }
    }

    /**
     * Alert customer and user when order status changes
     *
     * @param $order_id
     * @param $old_status
     * @param $new_status
     */

    public function wc_order_status_change_alert($order_id, $old_status, $new_status)
    {
        if (! $order_id) {
            return;
        }

        $order = new WC_Order($order_id);

        // Get the Customer billing phone
        $billing_phone = $order->get_billing_phone();

        $status_source = ! empty($new_status) ? $new_status : $order->get_status();
        $status_key = strpos($status_source, 'wc-') === 0 ? $status_source : 'wc-' . $status_source;
        $status = $this->normalize_order_status_key($status_key);

        // option not enabled
        if (
            ! $this->pluginActive || ! isset($this->options['order_status_' . $status]) || ! $this->options['order_status_' . $status]
        ) {
            return;
        }

        $buyer_msg = ! empty($this->options['ORDER_STATUS_' . strtoupper($status) . '_SMS']) ? $this->options['ORDER_STATUS_' . strtoupper($status) . '_SMS'] : null;

        $search = [
            '[store_name]',
            '[billing_first_name]',
            '[order_id]',
            '[order_status]',
            '[order_currency]',
            '[order_amount]',
            '[order_date_created]',
            '[order_date_completed]',
        ];

        $order_created   = wp_date('d M Y', strtotime($order->get_date_created()));
        $order_completed = ! empty($order->get_date_completed()) ? wp_date('d M Y', strtotime($order->get_date_completed())) : '';

        $replace = [
            get_bloginfo(),
            $order->get_billing_first_name(),
            $order_id,
            $this->get_order_status_label($status_key),
            $order->get_currency(),
            $order->get_total(),
            $order_created,
            $order_completed,
        ];

        $buyer_msg = str_replace($search, $replace, $buyer_msg);

        if (empty($buyer_msg)) {
            $order->add_order_note(__('Alpha SMS : Order message not found.', 'alpha-sms'));

            return;
        }

        $response = $this->SendSMS($billing_phone, $buyer_msg);

        if ($response->error === 0) {
            $order->add_order_note(__('Alpha SMS : Notified customer about order status ' . $this->get_order_status_label($status_key), 'alpha-sms'));
        } else {
            $order->add_order_note('Alpha SMS : ' . $response->msg);
        }
    }

    /**
     * Get all the phone number associated with administration role
     *
     * @return array
     */
    public function admin_phones()
    {
        $admin_ids = get_users(['fields' => 'ID', 'role' => 'administrator']);
        $numbers   = [];
        foreach ($admin_ids as $userid) {
            $number = $this->validateNumber(get_user_meta($userid, 'mobile_phone', true));
            if ($number) {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }

    /**
     * WordPress login with Phone Number methods
     *
     */

    public function login_enqueue_style()
    {
        if ($this->options['wp_login'] || $this->options['wp_reg']) {
            wp_enqueue_style(
                $this->plugin_name,
                plugin_dir_url(__FILE__) . 'css/otp-login-form.css',
                [],
                $this->version,
                'all'
            );
        }
    }

    public function login_enqueue_script()
    {
        if (! $this->pluginActive) {
            return;
        }

        if ($this->options['wp_login'] || $this->options['wp_reg']) {
            wp_enqueue_script(
                $this->plugin_name,
                plugin_dir_url(__FILE__) . 'js/otp-login-form.js',
                ['jquery'],
                $this->version,
                false
            );
            wp_localize_script(
                $this->plugin_name,
                $this->plugin_name . '_object',
                ['ajaxurl' => admin_url('admin-ajax.php')]
            );
        }
    }

    /**
     * Add OTP view in Wp login form
     *
     */
    public function add_otp_field_in_wp_login_form()
    {
        if (! $this->pluginActive || ! $this->options['wp_login']) {
            return;
        }

        require_once 'partials/add-otp-on-login-form.php';
    ?>
        <input type='hidden' name='action_type' id='action_type' value='wp_login' />
    <?php
    }

    /**
     * Add OTP view in Wc login form
     *
     */
    public function add_otp_field_in_wc_login_form()
    {
        if (! $this->pluginActive || ! $this->options['wc_login']) {
            return;
        }
        require_once 'partials/add-otp-on-login-form.php';
    ?>
        <input type='hidden' name='action_type' id='action_type' value='wc_login' />
        <?php
    }

    /**
     * Verify number and send otp
     *
     */
    public function save_and_send_otp_login()
    {
        // First check the nonce, if it fails the function will break
        check_ajax_referer('ajax-login-nonce', $this->plugin_name);

        //Nonce is checked, get the POST data and sign user on
        $info                  = [];
        $info['user_login']    = isset($_POST['log']) ? sanitize_text_field(wp_unslash($_POST['log'])) : '';
        $info['user_password'] = isset($_POST['pwd']) ? sanitize_text_field(wp_unslash($_POST['pwd'])) : '';
        $info['remember']      = isset($_POST['rememberme']) ? sanitize_text_field(wp_unslash($_POST['rememberme'])) : '';

        $userdata = get_user_by('login', $info['user_login']);

        if (! $userdata) {
            $userdata = get_user_by('email', $info['user_login']);
        }
        // wp_authenticate()
        $user_id = $userdata->data->ID;

        $result = wp_check_password($info['user_password'], $userdata->data->user_pass, $user_id);

        if (! $user_id || ! $result) {
            $response = ['status' => 401, 'message' => __('Wrong username or password!', 'alpha-sms')];
            echo wp_kses_post(json_encode($response));
            wp_die();
            exit;
        }

        $user_phone = get_user_meta($user_id, 'mobile_phone', true);

        if (! $user_phone) {
            $user_phone = get_user_meta($user_id, 'billing_phone', true);
        }

        // if user phone number is not valid then login without verification
        if (! $user_phone || ! $this->validateNumber($user_phone)) {
            $response = ['status' => 402, 'message' => __('No phone number found', 'alpha-sms')];
            echo wp_kses_post(json_encode($response));
            wp_die();
            exit;
        }

        //we will send sms
        $otp_code = $this->generateOTP();

        $number = $user_phone;
        $body   = 'Your one time password for ' . get_bloginfo() . ' login is ' . $otp_code . ' . Only valid for 2 min.';

        $sms_response = $this->SendSMS($number, $body);

        if ($sms_response->error === 0) {
            // save info in database for later verification
            $log_info = $this->log_login_register_action($user_phone, $otp_code);

            if ($log_info) {
                $response = ['status' => 200, 'message' => 'Please enter the verification code sent to your phone.'];
            } else {
                $response = ['status' => 500, 'message' => 'Something went wrong. Please try again.'];
            }

            echo wp_kses_post(json_encode($response));
            exit;
        }

        $response = ['status' => '400', 'message' => 'Error sending Otp Code. Please contact administrator.'];
        echo wp_kses_post(json_encode($response));
        wp_die();
        exit;
    }

    /**
     * Login the user verifying otp code
     *
     * @param $user
     * @param $username
     *
     * @return User|WP_Error
     */
    public function login_user($user, $username)
    {
        if (empty($user->data)) {
            return $user;
        }
        if (! $this->pluginActive || (! $this->options['wp_login'] && ! $this->options['wc_login'])) {
            return $user;
        }

        if (empty($_POST['action_type'])) {
            $error = new WP_Error();
            $error->add(
                'empty_password',
                __('<strong>Error</strong>: Authentication Error!', 'alpha-sms')
            );
            return $error;
        }

        // Nonce verification for login form
        check_ajax_referer('ajax-login-nonce', $this->plugin_name);

        $otp_code = isset($_REQUEST['otp_code']) ? sanitize_text_field(wp_unslash($_REQUEST['otp_code'])) : '';

        if (
            ($this->options['wp_login'] && $_POST['action_type'] == 'wp_login') ||
            ($this->options['wc_login'] && $_POST['action_type'] == 'wc_login')
        ) {
            return $this->startOTPChallenge($user, $username, $otp_code);
        }

        return $user;
    }

    /**
     * @param $user
     * @param $username
     * @param $otp_code
     *
     * @return mixed|WP_Error
     */
    public function startOTPChallenge($user, $username, $otp_code)
    {
        $user_phone = get_user_meta($user->data->ID, 'mobile_phone', true);
        if (! $user_phone) {
            $user_phone = get_user_meta($user->data->ID, 'billing_phone', true);
        }

        if (! $user_phone || ! $this->validateNumber($user_phone)) {
            return $user;
        }

        if (empty($otp_code)) {
            $error = new WP_Error();
            $error->add(
                'empty_password',
                __('<strong>Error</strong>: Wrong OTP Code!', 'alpha-sms')
            );
            return $error;
        }

        $valid_user = $this->authenticate_otp($otp_code);

        if ($valid_user) {
            $this->deletePastData();
            return $user;
        }

        return new WP_Error(
            'invalid_password',
            __('OTP is not valid', 'alpha-sms')
        );
    }

    /**
     * Woocommerce otp form in checkout
     */
    public function otp_form_at_checkout()
    {
        if (! $this->pluginActive || ! $this->options['otp_checkout']) {
            return;
        }

        if (! is_user_logged_in()) {
            require_once 'partials/add-otp-checkout-form.php';
        ?>
            <input type='hidden' name='action_type' id='action_type' value='wc_checkout' />
<?php
        }
    }
    
}
