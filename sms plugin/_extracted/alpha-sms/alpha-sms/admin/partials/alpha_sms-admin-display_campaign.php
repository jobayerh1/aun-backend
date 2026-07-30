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
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">
    <h2>
        <span class="dashicons dashicons-format-status"></span> <?php esc_attr_e('SMS Campaign', 'alpha-sms'); ?>
    </h2>

    <?php
    //Grab all options
    $alpha_sms_options = get_option($this->plugin_name);

    $alpha_sms_balance = '';

    if (!$alpha_sms_options || empty($alpha_sms_options['api_key'])) {
        $alpha_sms_balance = 'Please configure SMS API first.';
    } else {
        require_once ALPHA_SMS_PATH. 'includes/sms.class.php';


        $alpha_sms_smsPortal = new Alpha_SMS_Class($alpha_sms_options['api_key']);


        $alpha_sms_response = $alpha_sms_smsPortal->getBalance();

        if ($alpha_sms_response && $alpha_sms_response->error === 0) {
            $alpha_sms_balance = $alpha_sms_response->data->balance;
        } elseif ($alpha_sms_response && $alpha_sms_response->error === 405) {
            $alpha_sms_balance = 'Please configure SMS API first.';
        } else {
            $alpha_sms_balance = 'Unknown Error, failed to fetch balance';
        }
    }
    ?>

    <?php if (is_numeric($alpha_sms_balance)): ?>
        <p><strong>Balance:</strong> BDT <?php echo esc_html( number_format((float)$alpha_sms_balance, 2, '.', ',') ) ?> </p>
    <?php else: ?>
        <strong class='text-danger'><?php echo esc_html( $alpha_sms_balance ) ?></strong>
    <?php endif; ?>

    <!--   show notice when form submit -->
    <?php settings_errors(); ?>

    <form method="post" name=" <?php echo esc_attr( $this->plugin_name ); ?>"
                    action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr( $this->plugin_name . '_campaign' ) ?>">
                <?php wp_nonce_field($this->plugin_name . '_send_campaign', $this->plugin_name . '[_wpnonce]'); ?>

        <!-- Phone Numbers -->
        <fieldset class="mb-2">
            <p class="mb-2"><strong><?php esc_attr_e('Enter Phone Numbers', 'alpha-sms'); ?></strong></p>
            <legend class="screen-reader-text">
                <span><?php esc_attr_e( 'Enter Phone Numbers', 'alpha-sms' ); ?></span>
            </legend>
            <textarea
                    class="d-block"
                    id="<?php echo esc_attr( $this->plugin_name . '-numbers' ); ?>"
                    name="<?php echo esc_attr( $this->plugin_name . '[numbers]' ); ?>"
                    rows="2"
                    cols="70"></textarea>
            <small>New Line Separated</small>
        </fieldset>

        <!-- Checkbox -->
        <fieldset>
            <legend class="screen-reader-text">
                <span><?php esc_attr_e('Include all customers', 'alpha-sms'); ?></span>
            </legend>
            <label for="<?php echo esc_attr( $this->plugin_name . '-all_users' ); ?>">
                <input type="checkbox" id="<?php echo esc_attr( $this->plugin_name . '-all_users' ); ?>"
                       name="<?php echo esc_attr( $this->plugin_name . '[all_users]' ); ?>" value="1"/>
                <span><?php esc_attr_e( 'Include all customers', 'alpha-sms' ); ?></span>
            </label>
        </fieldset>

        <!-- SMS Body -->
        <fieldset>
            <p class="mb-2"><strong><?php esc_attr_e( 'Enter SMS Content', 'alpha-sms' ); ?></strong></p>
            <legend class="screen-reader-text">
                <span><?php esc_attr_e( 'Enter SMS Content', 'alpha-sms' ); ?></span>
            </legend>
            <textarea
                    class="d-block"
                    id="<?php echo esc_attr( $this->plugin_name . '-body' ); ?>"
                    name="<?php echo esc_attr( $this->plugin_name . '[body]' ); ?>"
                    rows="8"
                    cols="70"
                    required></textarea>
        </fieldset>


        <?php submit_button(__('Send SMS', 'alpha-sms'), 'primary', 'submit', true); ?>
    </form>
</div>