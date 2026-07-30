<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://sms.net.bd
 * @since      1.0.0
 *
 * @package    Alpha_sms
 * @subpackage Alpha_sms/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$alpha_sms_has_woocommerce = is_plugin_active('woocommerce/woocommerce.php');
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">
    <h2><span class="dashicons dashicons-admin-tools"></span> Alpha SMS
        <?php esc_attr_e('Options', 'alpha-sms'); ?></h2>
    <p>Here you can set all the options for using the API</p>

    <!--   show admin notice when settings are saved-->
    <?php settings_errors(); ?>

    <form method="post" name="<?php echo esc_attr($this->plugin_name); ?>" action="options.php" id="<?php echo esc_attr($this->plugin_name); ?>">
        <?php
        $alpha_sms_order_alerts =
            [
                /* translators: 1: Store name, 2: Order ID. */
                "DEFAULT_ORDER_STATUS_PENDING_SMS" => __(
                    "[store_name] - Payment required for Order #[order_id]\nYour order #[order_id] at [store_name] is currently pending payment. Please complete payment as soon as possible.",
                    'alpha-sms'
                ),
                /* translators: 1: Store name, 2: Order ID. */
                "DEFAULT_ORDER_STATUS_PROCESSING_SMS" => __(
                    "[store_name] - Order #[order_id] is being processed\nYour order #[order_id] at [store_name] is currently being processed.",
                    'alpha-sms'
                ),
                /* translators: 1: Store name, 2: Order ID. */
                "DEFAULT_ORDER_STATUS_ON_HOLD_SMS" => __(
                    "[store_name] - Order #[order_id] is on hold\nYour order #[order_id] at [store_name] is currently on hold. Our customer service team will be reaching out to you shortly.",
                    'alpha-sms'
                ),
                /* translators: 1: Store name, 2: Order ID. */
                "DEFAULT_ORDER_STATUS_COMPLETED_SMS" => __(
                    "[store_name] - Order #[order_id] has been completed\nYour order #[order_id] at [store_name] has been completed and is on its way to you.",
                    'alpha-sms'
                ),
                /* translators: 1: Store name, 2: Order ID. */
                "DEFAULT_ORDER_STATUS_CANCELLED_SMS" => __(
                    "[store_name] - Order #[order_id] has been cancelled\nYour order #[order_id] at [store_name] has been cancelled. Please contact our customer service team for any questions or concerns.",
                    'alpha-sms'
                ),
                /* translators: 1: Store name, 2: Order ID. */
                "DEFAULT_ORDER_STATUS_REFUNDED_SMS" => __(
                    "[store_name] - Order #[order_id] has been refunded\nYour order #[order_id] at [store_name] has been refunded. Please contact our customer service team for any questions or concerns.",
                    'alpha-sms'
                ),
                /* translators: 1: Store name, 2: Order ID. */
                "DEFAULT_ORDER_STATUS_FAILED_SMS" => __(
                    "[store_name] - Order #[order_id] has failed\nYour order #[order_id] at [store_name] has failed. Please contact our customer service team for any questions or concerns.",
                    'alpha-sms'
                ),
                /* translators: 1: Store name, 2: Order ID, 3: Order currency, 4: Order amount. */
                "DEFAULT_ADMIN_STATUS_SMS" => __(
                    "[store_name] - A new order #[order_id] for value [order_currency] [order_amount] has just been placed. Please check your admin dashboard for complete details.",
                    'alpha-sms'
                )
            ];

        //Grab all options
        $alpha_sms_options = get_option($this->plugin_name);

        $alpha_sms_api_key = (isset($alpha_sms_options['api_key']) && !empty($alpha_sms_options['api_key'])) ? $alpha_sms_options['api_key'] : '';

        if (strlen($alpha_sms_api_key) === 40) {
            $alpha_sms_api_key = substr_replace(esc_attr($alpha_sms_options['api_key']), str_repeat('*', 24), 12, 16);
        }

        $alpha_sms_sender_id = (isset($alpha_sms_options['sender_id']) && !empty($alpha_sms_options['sender_id'])) ? esc_attr($alpha_sms_options['sender_id']) : '';

        $alpha_sms_wp_reg = (isset($alpha_sms_options['wp_reg']) && !empty($alpha_sms_options['wp_reg'])) ? 1 : 0;
        $alpha_sms_wp_login = (isset($alpha_sms_options['wp_login']) && !empty($alpha_sms_options['wp_login'])) ? 1 : 0;
        $alpha_sms_wc_reg = (isset($alpha_sms_options['wc_reg']) && !empty($alpha_sms_options['wc_reg'])) ? 1 : 0;
        $alpha_sms_wc_login = (isset($alpha_sms_options['wc_login']) && !empty($alpha_sms_options['wc_login'])) ? 1 : 0;
        $alpha_sms_otp_checkout = (isset($alpha_sms_options['otp_checkout']) && !empty($alpha_sms_options['otp_checkout'])) ? 1 : 0;
        $alpha_sms_admin_phones = (isset($alpha_sms_options['admin_phones']) && !empty($alpha_sms_options['admin_phones'])) ? esc_attr($alpha_sms_options['admin_phones']) : '';
        $alpha_sms_order_status_admin = (isset($alpha_sms_options['order_status_admin']) && !empty($alpha_sms_options['order_status_admin'])) ? 1 : 0;
        $alpha_sms_admin_status_sms = (isset($alpha_sms_options['ADMIN_STATUS_SMS']) && !empty($alpha_sms_options['ADMIN_STATUS_SMS'])) ? $alpha_sms_options['ADMIN_STATUS_SMS'] : $alpha_sms_order_alerts['DEFAULT_ADMIN_STATUS_SMS'];

        $alpha_sms_customer_statuses = [];
        $alpha_sms_wc_statuses = function_exists('wc_get_order_statuses')
            ? wc_get_order_statuses()
            : [
                'wc-pending' => __('Pending payment', 'alpha-sms'),
                'wc-processing' => __('Processing', 'alpha-sms'),
                'wc-on-hold' => __('On hold', 'alpha-sms'),
                'wc-completed' => __('Completed', 'alpha-sms'),
                'wc-cancelled' => __('Cancelled', 'alpha-sms'),
                'wc-refunded' => __('Refunded', 'alpha-sms'),
                'wc-failed' => __('Failed', 'alpha-sms'),
            ];

        foreach ($alpha_sms_wc_statuses as $alpha_sms_status_key => $alpha_sms_status_label) {
            $alpha_sms_normalized = str_replace('-', '_', preg_replace('/^wc-/', '', $alpha_sms_status_key));
            $alpha_sms_enabled_key = 'order_status_' . $alpha_sms_normalized;
            $alpha_sms_message_key = 'ORDER_STATUS_' . strtoupper($alpha_sms_normalized) . '_SMS';
            $alpha_sms_default_key = 'DEFAULT_' . $alpha_sms_message_key;
            $alpha_sms_default_message = isset($alpha_sms_order_alerts[$alpha_sms_default_key])
                ? $alpha_sms_order_alerts[$alpha_sms_default_key]
                : __("[store_name] - Order #[order_id] status updated\nHello [billing_first_name], your order #[order_id] at [store_name] is now [order_status].", 'alpha-sms');

            $alpha_sms_customer_statuses[] = [
                'label' => wp_strip_all_tags($alpha_sms_status_label),
                'enabled_key' => $alpha_sms_enabled_key,
                'message_key' => $alpha_sms_message_key,
                'enabled' => (isset($alpha_sms_options[$alpha_sms_enabled_key]) && !empty($alpha_sms_options[$alpha_sms_enabled_key])) ? 1 : 0,
                'message' => (isset($alpha_sms_options[$alpha_sms_message_key]) && !empty($alpha_sms_options[$alpha_sms_message_key])) ? $alpha_sms_options[$alpha_sms_message_key] : $alpha_sms_default_message,
            ];
        }

        $alpha_sms_customer_status_token_set = [
            '[store_name]',
            '[billing_first_name]',
            '[order_id]',
            '[order_status]',
            '[order_date_created]',
            '[order_date_completed]',
            '[order_currency]',
            '[order_amount]',
        ];

        if (!empty($alpha_sms_api_key)) {

            require_once ALPHA_SMS_PATH . 'includes/sms.class.php';

            $alpha_sms_smsPortal = new Alpha_SMS_Class($alpha_sms_options['api_key']);

            $alpha_sms_response = $alpha_sms_smsPortal->getBalance();

            if ($alpha_sms_response && $alpha_sms_response->error === 0) {
                $alpha_sms_balance = $alpha_sms_response->data->balance;
            } elseif ($alpha_sms_response && $alpha_sms_response->error === 405) {
                $alpha_sms_balance = 'Authentication Failed. Please enter a valid API Key.';
            } else {
                $alpha_sms_balance = 'Unknown Error, failed to fetch balance.';
            }
        } else {
            $alpha_sms_balance = "empty";
        }

        settings_fields($this->plugin_name);
        do_settings_sections($this->plugin_name);
        ?>

        <!-- API Key -->
        <table class="form-table" aria-label="admin settings form">
            <tr>
                <th scope="row">
                    <label for="<?php echo esc_attr($this->plugin_name . '-api_key'); ?>">
                        <?php esc_html_e('API Key', 'alpha-sms'); ?>
                    </label>
                </th>
                <td>
                    <input id="<?php echo esc_attr($this->plugin_name . '-api_key'); ?>" name="<?php echo esc_attr($this->plugin_name . '[api_key]'); ?>" type="text" size="55" placeholder="Enter API Key" value="<?php if (!empty($alpha_sms_api_key)) { echo esc_attr($alpha_sms_api_key); } ?>" />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="<?php echo esc_attr($this->plugin_name . '-sender_id'); ?>">
                        <?php esc_html_e('Sender ID (Optional)', 'alpha-sms'); ?>
                    </label>
                </th>
                <td>
                    <input id="<?php echo esc_attr($this->plugin_name . '-sender_id'); ?>" name="<?php echo esc_attr($this->plugin_name . '[sender_id]'); ?>" type="text" size="55" value="<?php echo esc_attr($alpha_sms_sender_id); ?>" />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="<?php echo esc_attr($this->plugin_name . '-balance'); ?>"></label>
                </th>
                <td>
                    <span id="<?php echo esc_attr($this->plugin_name . '-balance'); ?>">
                        <?php if ($alpha_sms_balance === 'empty') : ?>
                            <strong>Don't have an account? <a href='https://sms.bd/signup/'>Register Now</a> (Free
                                SMS Credit after Sign-up).</strong>
                        <?php elseif (is_numeric($alpha_sms_balance)) : ?>
                            <strong>Balance:</strong> BDT
                            <?php echo esc_html(number_format((float)$alpha_sms_balance, 2, '.', ',')) ?>
                        <?php else : ?>
                            <strong class="text-danger"><?php echo esc_html($alpha_sms_balance); ?></strong>
                        <?php endif; ?>
                    </span>
                </td>
            </tr>
        </table>

        <hr>

        <h3><?php esc_attr_e('WordPress', 'alpha-sms'); ?></h3>
        <ol class="switches">
            <li>
                <input type="checkbox" id="<?php echo esc_attr($this->plugin_name . '-wp_reg'); ?>" name="<?php echo esc_attr($this->plugin_name . '[wp_reg]'); ?>" <?php checked($alpha_sms_wp_reg, 1); ?> />
                <label for="<?php echo esc_attr($this->plugin_name . '-wp_reg'); ?>">
                    <span class="toggle_btn"></span>
                    <span><?php esc_attr_e('Two Factor OTP Verification For WordPress Register Form', 'alpha-sms'); ?></span>
                </label>
            </li>

            <li>
                <input type="checkbox" id="<?php echo esc_attr($this->plugin_name . '-wp_login'); ?>" name="<?php echo esc_attr($this->plugin_name . '[wp_login]'); ?>" <?php checked($alpha_sms_wp_login, 1); ?> />
                <label for="<?php echo esc_attr($this->plugin_name . '-wp_login'); ?>">
                    <span class="toggle_btn"></span>
                    <span><?php esc_attr_e('Two Factor OTP Verification For WordPress Login Form', 'alpha-sms'); ?></span>
                </label>
            </li>

        </ol>


        <?php
        if ($alpha_sms_has_woocommerce) { ?>
            <h3><?php esc_attr_e('Woocommerce', 'alpha-sms'); ?></h3>

            <ol class="switches">
                <li>
                    <input type="checkbox" id="<?php echo esc_attr($this->plugin_name . '-wc_reg'); ?>" name="<?php echo esc_attr($this->plugin_name . '[wc_reg]'); ?>" <?php checked($alpha_sms_wc_reg, 1); ?> />
                    <label for="<?php echo esc_attr($this->plugin_name . '-wc_reg'); ?>">
                        <span class="toggle_btn"></span>
                        <span><?php esc_attr_e('Two Factor OTP Verification For Woocommerce Register Form', 'alpha-sms'); ?></span>
                    </label>
                </li>

                <li>
                    <input type="checkbox" id="<?php echo esc_attr($this->plugin_name . '-wc_login'); ?>" name="<?php echo esc_attr($this->plugin_name . '[wc_login]'); ?>" <?php checked($alpha_sms_wc_login, 1); ?> />
                    <label for="<?php echo esc_attr($this->plugin_name . '-wc_login'); ?>">
                        <span class="toggle_btn"></span>
                        <span><?php esc_attr_e('Two Factor OTP Verification For Woocommerce Login Form', 'alpha-sms'); ?></span>
                    </label>
                </li>

                <li>
                    <input type="checkbox" id="<?php echo esc_attr($this->plugin_name . '-otp_checkout'); ?>" name="<?php echo esc_attr($this->plugin_name . '[otp_checkout]'); ?>" <?php checked($alpha_sms_otp_checkout, 1); ?> />
                    <label for="<?php echo esc_attr($this->plugin_name . '-otp_checkout'); ?>">
                        <span class="toggle_btn"></span>
                        <span><?php esc_attr_e('OTP Verification For Guest Customer Checkout', 'alpha-sms'); ?></span>
                    </label>
                </li>

                <li>
                    <input class="alpha-collapse" type="checkbox" id="<?php echo esc_attr($this->plugin_name . '-order_status_admin'); ?>" name="<?php echo esc_attr($this->plugin_name . '[order_status_admin]'); ?>" <?php checked($alpha_sms_order_status_admin, 1); ?> />
                    <label for="<?php echo esc_attr($this->plugin_name . '-order_status_admin'); ?>">
                        <span class="toggle_btn"></span>
                        <span><?php esc_attr_e('Notify Admin on New Order', 'alpha-sms'); ?></span>
                    </label>
                    <div class="alpha-collapsable" id="order_status_admin">
                        <fieldset class="notify_template">
                            <legend>
                                <h4 class="mb-2">
                                    <label for="<?php echo esc_attr($this->plugin_name . '-admin_phones'); ?>">
                                        <?php esc_attr_e(
                                            'Admin Phone Numbers (comma separated)',
                                            'alpha-sms'
                                        ); ?>
                                    </label>
                                </h4>
                                <input id="<?php echo esc_attr($this->plugin_name . '-admin_phones'); ?>" name="<?php echo esc_attr($this->plugin_name . '[admin_phones]'); ?>" type="text" size="82" class="mb-2" value="<?php echo esc_attr($alpha_sms_admin_phones, 'alpha-sms'); ?>" />
                                <span class="my-2 d-block sms_tokens"><span>[store_name]</span> |
                                    <span>[billing_first_name]</span> |
                                    <span>[order_id]</span> |
                                    <span>[order_status]</span> |
                                    <span>[order_date_created]</span> |
                                    <span>[order_currency]</span> |
                                    <span>[order_amount]</span>
                                </span>
                            </legend>
                            <textarea id="<?php echo esc_attr($this->plugin_name . '-admin_status_sms'); ?>" name="<?php echo esc_attr($this->plugin_name . '[ADMIN_STATUS_SMS]'); ?>" rows="3" cols="85"><?php echo esc_textarea($alpha_sms_admin_status_sms); ?></textarea>
                        </fieldset>

                    </div>
                </li>


                <h3><?php esc_html_e('Notify Customer', 'alpha-sms'); ?></h3>

                <?php foreach ($alpha_sms_customer_statuses as $alpha_sms_status_config) : ?>
                    <li>
                        <input class="alpha-collapse" type="checkbox" id="<?php echo esc_attr($this->plugin_name . '-' . $alpha_sms_status_config['enabled_key']); ?>" name="<?php echo esc_attr($this->plugin_name . '[' . $alpha_sms_status_config['enabled_key'] . ']'); ?>" <?php checked($alpha_sms_status_config['enabled'], 1); ?> />
                        <label for="<?php echo esc_attr($this->plugin_name . '-' . $alpha_sms_status_config['enabled_key']); ?>">
                            <span class="toggle_btn"></span>
                            <span><?php echo esc_html(sprintf(__('On Order %s', 'alpha-sms'), $alpha_sms_status_config['label'])); ?></span>
                        </label>
                        <div class="alpha-collapsable" id="<?php echo esc_attr($alpha_sms_status_config['enabled_key']); ?>">
                            <fieldset class="notify_template">
                                <legend>
                                    <span class="sms_tokens my-2 d-block"><span>[store_name]</span> |
                                        <span>[billing_first_name]</span> |
                                        <span>[order_id]</span> |
                                        <span>[order_status]</span> |
                                        <span>[order_date_created]</span> |
                                        <span>[order_date_completed]</span> |
                                        <span>[order_currency]</span> |
                                        <span>[order_amount]</span>
                                    </span>
                                </legend>

                                <textarea id="<?php echo esc_attr($this->plugin_name . '-' . strtolower($alpha_sms_status_config['message_key'])); ?>" name="<?php echo esc_attr($this->plugin_name . '[' . $alpha_sms_status_config['message_key'] . ']'); ?>" rows="4" cols="85"><?php echo esc_textarea($alpha_sms_status_config['message']); ?></textarea>
                            </fieldset>
                        </div>
                    </li>
                <?php endforeach; ?>

            </ol>
        <?php }
        ?>

        <?php submit_button(__('Save all changes', 'alpha-sms'), 'primary', 'submit', true); ?>
    </form>
</div>