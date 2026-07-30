<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>

<div id="alpha_sms_otp_reg" class="mb-3" style="display:none;">
  <div class="alpha_sms-generate-otp form-row">
    <label for="otp_code" class="d-inline-block">OTP Code</label>
    <div id="wc_resend_otp" class="float-right"></div>
    <input type="number" class="input woocommerce-Input woocommerce-Input--text input-text" id="otp_code" name="otp_code" />
  </div>
</div>