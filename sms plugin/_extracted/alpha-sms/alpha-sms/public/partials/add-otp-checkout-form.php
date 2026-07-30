<?php
// If this file is called directly, abort.
if (! defined('WPINC')) {
  die;
}
?>

<div id="alpha_sms_otp_checkout" class="mb-3" style="display:none;">
  <div class="alpha_sms-generate-otp">
    <label for="otp_code" class="d-inline-block">OTP Code</label>
    <div id="wc_checkout_resend_otp" class="float-right"></div>
    <input type="number" class="input-text" id="otp_code" name="otp_code" />
  </div>
</div>

