jQuery(document).ready(function($){
    var $modalOverlay = null;
    var $modalContent = null;

    function buildLoginContent() {
        return '' +
            '<div id="wps-login-sms">' +
            '<div data-login-alert></div>' +
            '<div class="login-mobile-step">' +
            '<p>' + wp_sms_login.lang.username + ' : </p>' +
            '<input type="text" name="wp_sms_username" class="input" placeholder="' + wp_sms_login.lang.username + '"/>' +
            '<button type="submit" name="login-submit-username" class="button"><span>' + wp_sms_login.lang.submit + '</span></button>' +
            '</div>' +
            '<div class="loading_login">' + wp_sms_login.lang.wait + '</div>' +
            '<div id="powerby">Powered by: <a href="https://sms.greenweb.com.bd/" target="_blank">Greenweb SMS</a></div>' +
            '</div>';
    }

    function openLoginModal(cb) {
        cb = cb || function(){};
        if (!$modalOverlay) {
            $modalOverlay = $(
                '<div id="wps-login-modal-overlay" class="gweb-login-overlay">' +
                    '<div id="wps-login-modal-box" class="gweb-login-box">' +
                        '<button type="button" id="wps-login-modal-close" class="gweb-login-close">&times;</button>' +
                        '<div id="wps-login-modal-body" class="gweb-login-body"></div>' +
                    '</div>' +
                '</div>'
            ).appendTo('body');

            $modalContent = $modalOverlay.find('#wps-login-modal-body');

            $modalOverlay.on('click', function(e) {
                if (e.target === this) closeLoginModal();
            });

            $modalOverlay.find('#wps-login-modal-close').on('click', closeLoginModal);

            $(document).on('keydown.wpsLoginModal', function(e) {
                if (e.key === 'Escape') closeLoginModal();
            });

            var isRtl = parseInt(wp_sms_login.is_rtl);
            if (isRtl) {
                $modalOverlay.find('#wps-login-modal-box').css({ 'text-align': 'right', 'direction': 'rtl' });
            }
        }

        // Always reset content to fresh initial state
        $modalContent.html(buildLoginContent());
        $modalOverlay.fadeIn(200, cb);
    }

    function closeLoginModal() {
        if ($modalOverlay) {
            $modalOverlay.fadeOut(200);
        }
        $(document).off('keydown.wpsLoginModal');
    }

    $(document).on("click", "a#show_popup_login", function(e){
        e.preventDefault();
        var $btn = $(this).addClass('gweb-login-loading');
        openLoginModal(function() {
            $btn.removeClass('gweb-login-loading');
        });
    });

    /* Send User name Login with Mobile Step 1 */
    $(document).on("click", "button[name=login-submit-username]", function (e) {
        e.preventDefault();
        var $btn = $(this).addClass('gweb-btn-loading');

        $(".loading_login").show();
        $("[data-login-alert]").html("");

        $.ajax({
            url: wp_sms_login.ajax,
            type: 'GET',
            dataType: "json",
            data: {
                action: 'wp_sms_login_mobile_ajax',
                mobile_login_key: wp_sms_login.nonce,
                step: 1,
                wp_sms_username: $("input[name=wp_sms_username]").val()
            },
            success: function (data) {
                if (data.error == "yes") {
                    $("[data-login-alert]").html('<div class="alert-box error"><span>' + wp_sms_login.lang.error + ' : </span>' + data.text + '</div>');
                } else {
                    $("[data-login-alert]").html('<div class="alert-box success">' + data.text + '</div>');
                    setTimeout(function(){ $("[data-login-alert]").html(""); }, 7000);
                    $(".login-mobile-step").html('<input type="text" name="wp_sms_code" class="input" placeholder="' + wp_sms_login.lang.code + '"/><button type="submit" name="login-submit-code" class="button"><span>' + wp_sms_login.lang.submit_code + '</span></button>');
                }
                $btn.removeClass('gweb-btn-loading');
                $(".loading_login").hide();
            },
            error: function () {
                $btn.removeClass('gweb-btn-loading');
                alert(wp_sms_login.ajax_error);
            }
        });
    });

    /* Step 2 Check User code */
    $(document).on("click", "button[name=login-submit-code]", function (e) {
        e.preventDefault();
        var $btn = $(this).addClass('gweb-btn-loading');

        $(".loading_login").show();
        $("[data-login-alert]").html("");

        $.ajax({
            url: wp_sms_login.ajax,
            type: 'GET',
            dataType: "json",
            data: {
                action: 'wp_sms_login_mobile_ajax',
                mobile_login_key: wp_sms_login.nonce,
                step: 2,
                wp_sms_code: $("input[name=wp_sms_code]").val()
            },
            success: function (data) {
                if (data.error == "yes") {
                    $("[data-login-alert]").html('<div class="alert-box error"><span>' + wp_sms_login.lang.error + ' : </span>' + data.text + '</div>');
                } else {
                    window.location.href = data.text;
                }
                $btn.removeClass('gweb-btn-loading');
                $(".loading_login").hide();
            },
            error: function () {
                $btn.removeClass('gweb-btn-loading');
                alert(wp_sms_login.ajax_error);
            }
        });
    });

});
