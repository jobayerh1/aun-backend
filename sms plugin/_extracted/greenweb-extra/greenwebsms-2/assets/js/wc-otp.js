jQuery(document).ready(function ($) {
    // user_meta_filed is a selector string (not a DOM reference), safe to cache
    const user_meta_filed = $("input[name=wps_user_meta_field]").val();
    // NOTE: verified, mobile, mobile_number, checkoutForm are NOT cached here —
    // WooCommerce AJAX updates replace form elements, making references stale.
    // Each handler re-queries them via fresh $(...).
    // NOTE: Inline OTP elements are NOT cached here — WooCommerce AJAX updates
    // replace checkout form DOM elements, making cached references stale.
    // Instead, each handler re-queries the DOM via fresh $("#gw-otp-*") selectors.

    /**
     * Show an alert in the inline OTP area
     */
    function gwShowAlert(message, type) {
        if (!message) {
            $("#gw-otp-alert").hide();
            return;
        }
        var bg = type === 'error' ? '#f8d7da' : '#d4edda';
        var color = type === 'error' ? '#721c24' : '#155724';
        var border = type === 'error' ? '#f5c6cb' : '#c3e6cb';
        $("#gw-otp-alert").css({ background: bg, color: color, 'border-color': border }).html(message).show();
    }

    /**
     * Hide the inline OTP container and reset
     */
    function gwHideOtp() {
        $("#gw-otp-inline").hide();
        $("#gw-otp-step-phone").show();
        $("#gw-otp-step-code").hide();
        $("#gw-otp-alert").hide();
        $("#gw-otp-success-msg").hide();
        $("#gw-otp-loading").hide();
    }

    /**
     * Get and normalize phone from checkout form
     */
    function gwGetNormalizedPhone() {
        var phone = $("input[name=" + user_meta_filed + "]").val();
        phone = phone.replace(/[^0-9+]/g, "");
        if (phone.startsWith("+88")) phone = phone.slice(3);
        if (phone.startsWith("88")) phone = phone.slice(2);
        if (phone.startsWith("1")) phone = "0" + phone;
        return phone;
    }

    // ============================================================
    // Place Order button click — intercept and show OTP inline
    // ============================================================
    $(document).on("click", "[name=woocommerce_checkout_place_order]", function (e) {
        // If OTP is already shown, ignore the click (don't resend OTP)
        if ($("#gw-otp-inline").is(":visible")) {
            e.preventDefault();
            return;
        }

        var wooErrorCounts = $('.woocommerce-error li').length;
        if (wooErrorCounts < 1) {
            wooErrorCounts = $('.wc-block-components-notice-banner__content').length;
        }

        var wpsmsproValidationData = $('.woocommerce-error').find('li[data-wpsmspro]').data('wpsmspro');
        if (wpsmsproValidationData === undefined) {
            var ex = $(".is-error");
            if (ex.text().indexOf("Please verify your mobile number") !== -1) {
                wpsmsproValidationData = 'otp-required';
            }
        }

        const verifiedVal = $("input[name=wps_user_verified]").val();
        const mobile_number_val = $("input[name=wps_user_mobile_number]").val();
        const mobile_val = $("input[name=" + user_meta_filed + "]").val();

        const selectedPaymentMethod = $('.woocommerce-checkout input[name="payment_method"]:checked').attr('value');

        // Country whitelist check
        const whitelist_countries = wp_sms_woocommerce_otp.countries_whitelist;
        let billing_country = false;
        const billing_country_select = $('form.woocommerce-checkout').find("select[name=billing_country]");
        const billing_country_input = $('form.woocommerce-checkout').find("input[name=billing_country]");
        if (billing_country_select.length) billing_country = billing_country_select.val();
        if (billing_country_input.length) billing_country = billing_country_input.val();
        if (whitelist_countries) {
            if (!whitelist_countries.includes(billing_country)) {
                return;
            }
        }

        // Check if OTP is needed
        if (verifiedVal === '0' || mobile_val === "" || mobile_number_val !== mobile_val || (wooErrorCounts > 0 && wpsmsproValidationData == 'otp-required')) {

            // Skip OTP for non-COD when gweb_otp_cod filter is active
            if (wp_sms_woocommerce_otp.checkoutotp_filter === 'gweb_otp_cod' && selectedPaymentMethod !== 'cod') {
                return;
            }

            e.preventDefault();

            // Disable Place Order button to prevent double-submit
            $("#place_order").prop("disabled", true);

            // Show inline OTP (fresh DOM queries — survives WooCommerce AJAX updates)
            $("#gw-otp-inline").show();
            $("#gw-otp-step-phone").show();
            $("#gw-otp-step-code").hide();
            gwShowAlert(null);
            $("#gw-otp-success-msg").hide();
            $("#gw-otp-loading").hide();

            // Set phone number in input
            var normalized = gwGetNormalizedPhone();
            $("#gw-otp-number-input").val(normalized);

            // Initialize intlTelInput if applicable
            const input = document.querySelector("#gw-otp-number-input");
            if (input && window.intlTelInput && typeof wp_sms_intel_tel_input !== 'undefined') {
                // Destroy previous instance if exists
                try { window.intlTelInputGlobals && window.intlTelInputGlobals.getInstance(input) && window.intlTelInputGlobals.getInstance(input).destroy(); } catch(e) {}
                window.intlTelInput(input, {
                    onlyCountries: wp_sms_intel_tel_input.only_countries,
                    preferredCountries: wp_sms_intel_tel_input.preferred_countries,
                    autoHideDialCode: wp_sms_intel_tel_input.auto_hide,
                    nationalMode: wp_sms_intel_tel_input.national_mode,
                    separateDialCode: wp_sms_intel_tel_input.separate_dial,
                    utilsScript: wp_sms_intel_tel_input.util_js,
                    customContainer: 'intel-otp'
                });
                $(".intel-otp #country-listbox").attr('style', 'position: fixed !important;');
            }

            // Auto-send OTP if valid phone number
            if (normalized.length && normalized.startsWith("01")) {
                $("#gw-otp-send-btn").click();
            }
        }
    });

    // ============================================================
    // Send OTP (Step 1)
    // ============================================================
    $(document).on("click", "#gw-otp-send-btn", function (e) {
        e.preventDefault();

        var phone = $("#gw-otp-number-input").val();
        if (!phone) {
            gwShowAlert("Please enter your mobile number.", "error");
            return;
        }

        $("#gw-otp-loading").show();
        gwShowAlert(null);

        const gwebreqverifyc = $("input[name=gwebreqverifyc]").val();

        $.ajax({
            url: wp_sms_woocommerce_otp.ajax,
            type: 'GET',
            dataType: "json",
            data: {
                action: 'wp_sms_woocommerce_otp',
                step: 1,
                wp_sms_otp_number: phone,
                gwebreqverifyc: gwebreqverifyc,
                wc_otp_send_nonce: $("input[name=wc_otp_send_nonce]").val(),
                bdbulksms_payment_method: $('input[name="payment_method"]:checked').val() || "cod",
            },
            success: function (data) {
                $("#gw-otp-loading").hide();

                if (data.error === "yes") {
                    gwShowAlert('<span>' + wp_sms_woocommerce_otp.lang.error + ' : </span>' + data.text, 'error');
                } else if (data.error === "yes-limit") {
                    gwShowAlert('<span>' + wp_sms_woocommerce_otp.lang.error + ' : </span>' + data.text, 'error');
                    // Still show OTP code input so they can retry later
                    $("#gw-otp-step-phone").hide();
                    $("#gw-otp-step-code").show();
                } else if (data.error === "greenwebsms-verified") {
                    // Already verified — update fields and proceed
                    $("#gw-otp-number-input").val(data.phone || phone);
                    $("input[name=" + user_meta_filed + "]").val(data.phone || phone);
                    $("input[name=" + user_meta_filed + "]").attr('readonly', true);
                    $("input[name=wps_user_verified]").val('1');
                    $("input[name=wps_user_mobile_number]").val(data.phone || phone);
                    $('.woocommerce-error').remove();
                    gwHideOtp();
                    $("#place_order").prop("disabled", false);
                    $("#place_order").click();
                } else {
                    // OTP sent successfully — show code input
                    $("#gw-otp-step-phone").hide();
                    $("#gw-otp-step-code").show();
                    $("#gw-otp-success-msg").html(data.text).show();
                }
            },
            error: function () {
                $("#gw-otp-loading").hide();
                alert(wp_sms_woocommerce_otp.lang.ajax_error);
            }
        });
    });

    // ============================================================
    // Verify OTP (Step 2)
    // ============================================================
    $(document).on("click", "#gw-otp-verify-btn", function (e) {
        e.preventDefault();

        // Validate all 4 digits are filled
        const code1 = $("input[name=wp_sms_otp_codeone]").val();
        const code2 = $("input[name=wp_sms_otp_codetwo]").val();
        const code3 = $("input[name=wp_sms_otp_codethree]").val();
        const code4 = $("input[name=wp_sms_otp_codefour]").val();
        if (!code1 || !code2 || !code3 || !code4) {
            gwShowAlert('<span>' + wp_sms_woocommerce_otp.lang.error + ' : </span>Please enter the complete 4-digit OTP code.', 'error');
            return;
        }

        $("#gw-otp-loading").show();
        gwShowAlert(null);

        const phone = $("#gw-otp-number-input").val();
        const gwebreqverifyc = $("input[name=gwebreqverifyc]").val();

        $.ajax({
            url: wp_sms_woocommerce_otp.ajax,
            type: 'GET',
            dataType: "json",
            data: {
                action: 'wp_sms_woocommerce_otp',
                step: 2,
                wp_sms_otp_number: phone,
                wp_sms_otp_codeone: code1,
                wp_sms_otp_codetwo: code2,
                wp_sms_otp_codethree: code3,
                wp_sms_otp_codefour: code4,
                gwebreqverifyc: gwebreqverifyc,
                wc_otp_verify_nonce: $("input[name=wc_otp_verify_nonce]").val(),
                bdbulksms_payment_method: $('input[name="payment_method"]:checked').val() || "cod",
            },
            success: function (data) {
                $("#gw-otp-loading").hide();

                if (data.error === "yes") {
                    gwShowAlert('<span>' + wp_sms_woocommerce_otp.lang.error + ' : </span>' + data.text, 'error');
                } else {
                    // Verified successfully — update fields and place order
                    $("input[name=" + user_meta_filed + "]").val(phone);
                    $("input[name=" + user_meta_filed + "]").attr('readonly', true);
                    $("input[name=wps_user_verified]").val('1');
                    $("input[name=wps_user_mobile_number]").val(phone);
                    $('.woocommerce-error').remove();
                    gwHideOtp();
                    $("#place_order").prop("disabled", false);
                    $("#place_order").click();
                }
            },
            error: function () {
                $("#gw-otp-loading").hide();
                alert(wp_sms_woocommerce_otp.lang.ajax_error);
            }
        });
    });

    // ============================================================
    // Change Number — go back to phone input step
    // ============================================================
    $(document).on("click", "#gw-otp-change-link", function (e) {
        e.preventDefault();
        $("#gw-otp-step-code").hide();
        $("#gw-otp-success-msg").hide();
        gwShowAlert(null);
        $("#gw-otp-loading").hide();

        // Clear any old OTP digits
        $(".gw-otp-digit").val("");

        // Reset phone input with current checkout phone
        $("#gw-otp-number-input").val(gwGetNormalizedPhone());
        $("#gw-otp-step-phone").show();

        // Re-initialize intlTelInput if applicable
        const input = document.querySelector("#gw-otp-number-input");
        if (input && window.intlTelInput) {
            try { window.intlTelInputGlobals && window.intlTelInputGlobals.getInstance(input) && window.intlTelInputGlobals.getInstance(input).destroy(); } catch(e) {}
            window.intlTelInput(input, {
                onlyCountries: wp_sms_intel_tel_input.only_countries,
                preferredCountries: wp_sms_intel_tel_input.preferred_countries,
                autoHideDialCode: wp_sms_intel_tel_input.auto_hide,
                nationalMode: wp_sms_intel_tel_input.national_mode,
                separateDialCode: wp_sms_intel_tel_input.separate_dial,
                utilsScript: wp_sms_intel_tel_input.util_js,
                customContainer: 'intel-otp'
            });
            $(".intel-otp #country-listbox").attr('style', 'position: fixed !important;');
        }
    });

    // ============================================================
    // Cancel — dismiss OTP box and re-enable Place Order
    // ============================================================
    $(document).on("click", "#gw-otp-cancel-link", function (e) {
        e.preventDefault();
        gwHideOtp();
        $("#place_order").prop("disabled", false);
    });

    // ============================================================
    // Resend OTP (Step 3)
    // ============================================================
    $(document).on("click", "#gw-otp-resend-link", function (e) {
        e.preventDefault();

        var phone = $("#gw-otp-number-input").val();
        if (!phone) {
            gwShowAlert("Phone number is missing. Please change number and try again.", "error");
            return;
        }

        $("#gw-otp-loading").show();
        gwShowAlert(null);

        const gwebreqverifyc = $("input[name=gwebreqverifyc]").val();

        $.ajax({
            url: wp_sms_woocommerce_otp.ajax,
            type: 'GET',
            dataType: "json",
            data: {
                action: 'wp_sms_woocommerce_otp',
                step: 3,
                wp_sms_otp_number: phone,
                gwebreqverifyc: gwebreqverifyc,
                wc_otp_resend_nonce: $("input[name=wc_otp_resend_nonce]").val(),
                bdbulksms_payment_method: $('input[name="payment_method"]:checked').val() || "cod",
            },
            success: function (data) {
                $("#gw-otp-loading").hide();
                if (data.error === "yes") {
                    gwShowAlert('<span>' + wp_sms_woocommerce_otp.lang.error + ' : </span>' + data.text, 'error');
                } else {
                    $("#gw-otp-success-msg").html(data.text).show();
                }
            },
            error: function () {
                $("#gw-otp-loading").hide();
                alert(wp_sms_woocommerce_otp.lang.ajax_error);
            }
        });
    });

    // ============================================================
    // OTP digit auto-focus and input handling
    // ============================================================
    $(document).on("input", ".gw-otp-digit", function (e) {
        const value = e.target.value;
        if (isNaN(value)) {
            $(this).val('');
            return;
        }
        if (value.length === 1) {
            const inputs = $(this).closest('div').find('.gw-otp-digit');
            const index = inputs.index(this);
            if (index < inputs.length - 1) {
                inputs.eq(index + 1).focus();
            }
        }
    });

    $(document).on("paste", ".gw-otp-digit", function (e) {
        e.preventDefault();
        const pastedValue = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
        if (/^\d{4}$/.test(pastedValue)) {
            const inputs = $(this).closest('div').find('.gw-otp-digit');
            inputs.each(function (i) {
                $(this).val(pastedValue[i] || '');
            });
        }
    });

    $(document).on("keydown", ".gw-otp-digit", function (e) {
        if (e.key === 'Backspace') {
            const inputs = $(this).closest('div').find('.gw-otp-digit');
            const index = inputs.index(this);
            if ($(this).val().length === 0 && index > 0) {
                inputs.eq(index - 1).focus();
            }
        }
    });
});
