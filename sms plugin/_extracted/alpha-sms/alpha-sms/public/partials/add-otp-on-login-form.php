<?php

/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       https://sms.net.bd
 * @since      1.0.0
 *
 * @package    Alpha_sms
 * @subpackage Alpha_sms/public/partials
 */
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<?php wp_nonce_field( 'ajax-login-nonce', $this->plugin_name ); ?>
<div id="alpha_sms_otp" style="display:none;">
  <div class="alpha_sms-generate-otp form-row">
    <label for="otp_code" class="d-inline-block">OTP Code</label>
    <div id="resend_otp" class="float-right"></div>
    <input type="number" class="input woocommerce-Input woocommerce-Input--text input-text" id="otp_code" name="otp_code" />
  </div>
</div>