/* For Default Wordpress page login and registration code */

window.$ = jQuery;

let wp_login_form, wp_reg_form;

// fill variables with appropriate selectors and attach event handlers
$(function () {
    if ($('#alpha_sms_otp').length) {
        wp_login_form = $('#alpha_sms_otp').parent('form#loginform').eq(0);
        wp_reg_form = $('#alpha_sms_otp').parent('form#registerform').eq(0);
        // Perform AJAX login on form submit
        wp_login_form.find(':submit').on('click', WP_Login_SendOtp);
        wp_reg_form.find(':submit').on('click', WP_Reg_SendOtp);
    }
});

// Error template
function showError(msg) {
    return `<div id="login_error"><strong>Error</strong>: ${msg}<br></div>`;
}

// Error template
function showSuccess(msg) {
    return `<div class="success"><strong>Success</strong>: ${msg}<br></div>`;
}

// ajax send otp for
function WP_Login_SendOtp(e) {
    if (e) e.preventDefault();
    console.log('somthing');
    wp_login_form.prev('#login_error, .success, .message').remove();
    wp_login_form.find(':submit').prop('disabled', true).val('Processing');

    let data = {
        action: 'alpha_sms_to_save_and_send_otp_login', //calls wp_ajax_nopriv_alpha_sms_to_save_and_send_otp_login
        log: $('form#loginform #user_login').val(),
        pwd: $('form#loginform #user_pass').val(),
        rememberme: $('form#loginform #rememberme').val(),
        alpha_sms: $('form#loginform #alpha_sms').val(),
    };

    $.post(
        alpha_sms_object.ajaxurl,
        data,
        function (resp) {
            if (resp.status === 200) {
                wp_login_form.find(':submit').off('click');
                wp_login_form
                    .find('> p:first, .user-pass-wrap, .forgetmenot')
                    .hide();
                $('#alpha_sms_otp').fadeIn();
                $(showSuccess(resp.message)).insertBefore(wp_login_form);
                timer(
                    'resend_otp',
                    120,
                    `<a href="javascript:WP_Login_SendOtp()">Resend OTP</a>`
                );
            } else if (resp.status === 402) {
                // no phone number found
                wp_login_form.find(':submit').off('click');
                wp_login_form.find(':submit').prop('disabled', false).val('Log In').trigger('click');
            } else {
                // wrong user name pass/sms api error
                $(showError(resp.message)).insertBefore(wp_login_form);
            }
        },
        'json'
    )
        .fail(() =>
            $(
                showError('Something went wrong. Please try again later')
            ).insertBefore(wp_login_form)
        )
        .done(() =>
            wp_login_form.find(':submit').prop('disabled', false).val('Log In')
        );
}

// ajax send otp for wordpress registration
function WP_Reg_SendOtp(e) {
    if (e) e.preventDefault();

    wp_reg_form.prev('#login_error, .success, .message').remove();
    wp_reg_form
        .find(':submit')
        .prop('disabled', true)
        .val('Processing')
        .text('Processing');

    let data = {
        action: 'wc_send_otp', //calls wp_ajax_nopriv_wc_send_otp
        billing_phone: wp_reg_form.find('#reg_billing_phone').val(),
        email: wp_reg_form.find('#user_email').val(),
    };

    $.post(
        alpha_sms_object.ajaxurl,
        data,
        function (resp) {
            if (resp.status === 200) {
                wp_reg_form.find(':submit').off('click');
                $('#alpha_sms_otp').fadeIn().prevAll().hide();
                $(showSuccess(resp.message)).insertBefore(wp_reg_form);
                timer(
                    'resend_otp',
                    120,
                    `<a href="javascript:WP_Reg_SendOtp()">Resend OTP</a>`
                );
            } else {
                // wrong user name pass/sms api error
                $(showError(resp.message)).insertBefore(wp_reg_form);
            }
        },
        'json'
    )
        .fail(() =>
            $(
                showError('Something went wrong. Please try again later')
            ).insertBefore(wp_reg_form)
        )
        .done(() =>
            wp_reg_form
                .find(':submit')
                .prop('disabled', false)
                .val('Register')
                .text('Register')
        );
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
