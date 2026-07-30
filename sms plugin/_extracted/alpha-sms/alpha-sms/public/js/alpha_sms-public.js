/* For Woocommerce page login and registration code */

window.$ = jQuery;

let form,
   wc_reg_form,
   alert_wrapper,
   checkout_form,
   checkout_otp,
   otp_input,
   otp_input_reg,
   checkout_submit_button,
   checkout_proxy_button;

// fill variables with appropriate selectors and attach event handlers
$(function () {
   alert_wrapper = $('.woocommerce-notices-wrapper').eq(0);

   checkout_otp = $('#alpha_sms_otp_checkout');
   otp_input = $('#alpha_sms_otp');
   otp_input_reg = $('#alpha_sms_otp_reg');

   // Perform AJAX login on form submit
   if (otp_input.length) {
      form = otp_input.parent('form.woocommerce-form-login.login');
      form.find(':submit').on('click', WC_Login_SendOtp);
   }

   if (otp_input_reg.length) {
      wc_reg_form = otp_input_reg.parent('form.woocommerce-form-register.register');
      wc_reg_form.find(':submit').on('click', WC_Reg_SendOtp);
   }


   if(alpha_sms_object.checkout_otp == 'yes'){
      initializeCheckoutSubmitProxy();
   }
   $(document.body).on('updated_checkout', initializeCheckoutSubmitProxy);
});

// Error template
function showError(msg) {
   return `<ul class="woocommerce-error" role="alert"><li>${msg}</li></ul></div>`;
}

// Error template
function showSuccess(msg) {
   return `<ul class="woocommerce-message" role="alert" style="border-left: 3px solid #00a32a"><li>${msg}</li></ul></div>`;
}

// ajax send otp for woocommerce login
function WC_Login_SendOtp(e) {
   if (e) e.preventDefault();
   alert_wrapper.html('');

   let username = form.find('#username').val();
   let password = form.find('#password').val();

   if (!username || !password) {
      alert_wrapper.html(showError('Fill in the required fields.'));
      $('html,body').animate({ scrollTop: 0 }, 'slow');
      return;
   }

   form
      .find(':submit')
      .prop('disabled', true)
      .val('Processing')
      .text('Processing');

   let data = {
      action: 'alpha_sms_to_save_and_send_otp_login', //calls wp_ajax_nopriv_alpha_sms_to_save_and_send_otp_login
      log: form.find('#username').val(),
      pwd: form.find('#password').val(),
      rememberme: form.find('#rememberme').val(),
      alpha_sms: form.find('#alpha_sms').val(),
   };

   $.post(
      alpha_sms_object.ajaxurl,
      data,
      function (resp) {
         if (resp.status === 200) {
            form.find(':submit').off('click');
            $('#alpha_sms_otp').fadeIn().prevAll().hide();
            alert_wrapper.html(showSuccess(resp.message));
            timer(
               'resend_otp',
               120,
               `<a href="javascript:WC_Login_SendOtp()">Resend OTP</a>`
            );
         } else if (resp.status === 402) {
            // no phone number found
            form.find(':submit').off('click');
            form.find(':submit').prop('disabled', false).val('Log In').trigger('click');

         } else {
            // wrong user name pass/sms api error
            alert_wrapper.html(showError(resp.message));
         }
      },
      'json'
   )
      .fail(() =>
         alert_wrapper.html(
            showError('OTP verification request failed. Please try again later')
         )
      )
      .done(() =>
         form
            .find(':submit')
            .prop('disabled', false)
            .val('Log In')
            .text('Log In')
      );
}

// ajax send otp for woocommerce registration
function WC_Reg_SendOtp(e) {
   if (e) e.preventDefault();
   alert_wrapper.html('');

   let phone = wc_reg_form.find('#reg_billing_phone').val();
   let email = wc_reg_form.find('#reg_email').val() || '';
   let password = wc_reg_form.find('#reg_password').val();
   let wc_reg_phone_nonce = wc_reg_form.find('#wc_reg_phone_nonce').val();
   let action_type = wc_reg_form.find('#action_type').val();

   if (!phone) {
      alert_wrapper.html(showError('Fill in the required fields.'));
      $('html,body').animate({ scrollTop: 0 }, 'slow');
      return;
   }

   wc_reg_form
      .find(':submit')
      .prop('disabled', true)
      .val('Processing')
      .text('Processing');

   let data = {
      action: 'wc_send_otp', //calls wp_ajax_nopriv_wc_send_otp
      billing_phone: phone,
      email: email,
      wc_reg_phone_nonce: wc_reg_phone_nonce,
      action_type: action_type,
   };

   if (password) {
      data.password = password;
   }

   $.post(
      alpha_sms_object.ajaxurl,
      data,
      function (resp) {
         if (resp.status === 200) {
            wc_reg_form.find(':submit').off('click');
            $('#alpha_sms_otp_reg').fadeIn().prevAll().hide();
            alert_wrapper.html(showSuccess(resp.message));
            timer(
               'wc_resend_otp',
               120,
               `<a href="javascript:WC_Reg_SendOtp()">Resend OTP</a>`
            );
         } else {
            // wrong user name pass/sms api error
            alert_wrapper.html(showError(resp.message));
         }
      },
      'json'
   )
      .fail(() =>
         alert_wrapper.html(
            showError('Something went wrong. Please try again later')
         )
      )
      .done(() =>
         wc_reg_form
            .find(':submit')
            .prop('disabled', false)
            .val('Register')
            .text('Register')
      );
}

// ajax send otp if checkout account creation is enabled
function WC_Checkout_SendOtp(e) {
   if (e) e.preventDefault();
   alert_wrapper.html('');

   checkout_form = getCheckoutForm();

   if (!checkout_form || !checkout_form.length) {
      return;
   }

   const phoneField = checkout_form.find('#billing_phone');
   const phone = phoneField.val();

   if (!phone) {
      alert_wrapper.html(showError('Fill in the required fields.'));
      $('html,body').animate({ scrollTop: checkout_form.offset().top }, 'slow');
      return;
   }

   if (checkout_proxy_button && checkout_proxy_button.length) {
      checkout_proxy_button.prop('disabled', true);
      setCheckoutButtonLabel(checkout_proxy_button, 'Processing');
   }

   const data = {
      action: 'wc_send_otp',
      billing_phone: phone,
      action_type: checkout_form.find('#action_type').val(),
      alpha_sms_checkout_nonce: alpha_sms_object.alpha_sms_checkout_nonce
   };


   $.post(
      alpha_sms_object.ajaxurl,
      data,
      function (resp) {
         if (resp.status === 200) {
            restoreCheckoutSubmitButton();
            $('#alpha_sms_otp_checkout').fadeIn();
            alert_wrapper.html(showSuccess(resp.message));
            timer(
               'wc_checkout_resend_otp',
               120,
               `<a href="javascript:WC_Checkout_SendOtp()">Resend OTP</a>`
            );
         } else {
            alert_wrapper.html(showError(resp.message));
         }
      },
      'json'
   )
      .fail(function () {
         alert_wrapper.html(
            showError('Something went wrong. Please try again later')
         );
      })
      .always(function () {
         if (checkout_form && checkout_form.length) {
            $('html,body').animate(
               { scrollTop: checkout_form.offset().top },
               'slow'
            );
         }

         if (checkout_proxy_button && checkout_proxy_button.length) {
            checkout_proxy_button.prop('disabled', false);
            const defaultLabel =
               checkout_proxy_button.data('alphaSmsOriginalLabel') ||
               getCheckoutButtonLabel(checkout_submit_button);
            setCheckoutButtonLabel(checkout_proxy_button, defaultLabel);
         }
      });
}

function getCheckoutForm() {
   if (checkout_form && checkout_form.length) {
      return checkout_form;
   }

   checkout_otp = $('#alpha_sms_otp_checkout');

   if (!checkout_otp.length) {
      return $();
   }

   checkout_form = checkout_otp
      .parents('form.checkout.woocommerce-checkout')
      .eq(0);

   if (!checkout_form.length) {
      checkout_form = checkout_otp.closest('form');
   }

   if (!checkout_form.length) {
      checkout_form = $();
   }

   return checkout_form;
}

function findCheckoutSubmitButton(form) {
   if (!form || !form.length) {
      return $();
   }

   let button = form
      .find('[name="woocommerce_checkout_place_order"][type="submit"]')
      .last();

   if (!button.length) {
      button = form.find('button[type="submit"], input[type="submit"]').last();
   }

   return button;
}

function getCheckoutButtonLabel(button) {
   if (!button || !button.length) {
      return '';
   }

   if (button.is('input')) {
      return button.val();
   }

   return button.html();
}

function setCheckoutButtonLabel(button, label) {
   if (!button || !button.length) {
      return;
   }

   const safeLabel = label !== undefined && label !== null ? label : '';

   if (button.is('input')) {
      button.val(safeLabel);
      return;
   }

   button.html(safeLabel);
}

function copyCheckoutButtonAttributes(originalButton, proxyButton) {
   const originalNode = originalButton.get(0);

   if (!originalNode || !originalNode.attributes) {
      return;
   }

   $.each(originalNode.attributes, function () {
      const attributeName = this.name;
      const attributeValue = this.value;

      if (
         !attributeName ||
         attributeName === 'id' ||
         attributeName === 'name' ||
         attributeName === 'type' ||
         attributeName === 'value' ||
         attributeName === 'class'
      ) {
         return;
      }

      proxyButton.attr(attributeName, attributeValue);
   });
}

function copyCheckoutButtonStyles(originalButton, proxyButton) {
   if (
      !window ||
      !window.getComputedStyle ||
      !originalButton ||
      !originalButton.length ||
      !proxyButton ||
      !proxyButton.length
   ) {
      return;
   }

   const originalNode = originalButton.get(0);
   const proxyNode = proxyButton.get(0);

   if (!originalNode || !proxyNode) {
      return;
   }

   const computed = window.getComputedStyle(originalNode);

   proxyNode.style.cssText = '';

   for (let i = 0; i < computed.length; i++) {
      const propertyName = computed[i];

      if (!propertyName) {
         continue;
      }

      const value = computed.getPropertyValue(propertyName);

      if (!value || (propertyName === 'display' && value === 'none')) {
         continue;
      }

      proxyNode.style.setProperty(
         propertyName,
         value,
         computed.getPropertyPriority(propertyName)
      );
   }
}

function createCheckoutProxyButton(originalButton) {
   if (!originalButton || !originalButton.length) {
      return null;
   }

   let proxyButton;

   if (originalButton.is('input')) {
      proxyButton = $('<input type="button" />');
   } else {
      proxyButton = $('<button type="button"></button>');
   }

   copyCheckoutButtonAttributes(originalButton, proxyButton);

   const defaultLabel = getCheckoutButtonLabel(originalButton);

   proxyButton.data('alphaSmsOriginalLabel', defaultLabel);
   setCheckoutButtonLabel(proxyButton, defaultLabel);

   copyCheckoutButtonStyles(originalButton, proxyButton);

   return proxyButton;
}

function restoreCheckoutSubmitButton() {
   if (checkout_submit_button && checkout_submit_button.length) {
      checkout_submit_button.prop('disabled', false);
      checkout_submit_button.show();
   }

   if (checkout_proxy_button && checkout_proxy_button.length) {
      checkout_proxy_button.off('click', WC_Checkout_SendOtp).remove();
      checkout_proxy_button = null;
   }
}

function teardownCheckoutProxy() {
   restoreCheckoutSubmitButton();
   checkout_submit_button = null;
   checkout_form = null;
}

function initializeCheckoutSubmitProxy() {
   checkout_otp = $('#alpha_sms_otp_checkout');

   if (!checkout_otp.length) {
      teardownCheckoutProxy();
      return;
   }

   checkout_form = getCheckoutForm();

   if (!checkout_form.length) {
      return;
   }

   const originalButton = findCheckoutSubmitButton(checkout_form);

   if (!originalButton.length) {
      return;
   }

   if (checkout_proxy_button && checkout_proxy_button.length) {
      checkout_proxy_button.off('click', WC_Checkout_SendOtp).remove();
   }

   checkout_submit_button = originalButton;
   checkout_submit_button.prop('disabled', false);
   checkout_submit_button.show();

   checkout_proxy_button = createCheckoutProxyButton(originalButton);

   if (!checkout_proxy_button || !checkout_proxy_button.length) {
      return;
   }

   checkout_proxy_button.insertAfter(originalButton);
   checkout_proxy_button.on('click', WC_Checkout_SendOtp);

   checkout_submit_button.hide();
}

function timer(displayID, remaining, timeoutEl = '') {
   let m = Math.floor(remaining / 60);
   let s = remaining % 60;

   m = m < 10 ? '0' + m : m;
   s = s < 10 ? '0' + s : s;
   document.getElementById(displayID).innerHTML = m + ':' + s;
   remaining -= 1;

   if (remaining >= 0) {
      setTimeout(function () {
         timer(displayID, remaining, timeoutEl);
      }, 1000);
      return;
   }
   // Do timeout stuff here
   document.getElementById(displayID).innerHTML = timeoutEl;
}
