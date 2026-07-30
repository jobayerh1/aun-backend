jQuery(document).ready(function ($) {

    var metaboxNonce = wp_sms_woocommerce_metabox.nonce;
    var lang = wp_sms_woocommerce_metabox.lang;
    var cachedTemplates = [];

    /* --- Template Toolbar (always visible, above textarea) --- */
    var templateBar =
        '<div class="gweb-tpl-bar">' +
            '<div class="gweb-tpl-toolbar">' +
                '<button type="button" class="gweb-tpl-btn-load">' + lang.load_template + '</button>' +
                '<button type="button" class="gweb-tpl-btn-add">' + lang.add_template + '</button>' +
            '</div>' +
            /* Load Dropdown */
            '<div class="gweb-tpl-dropdown" style="display:none">' +
                '<div class="gweb-tpl-dropdown-list" id="gweb-tpl-dropdown-list"></div>' +
            '</div>' +
            /* Add Form */
            '<div class="gweb-tpl-add-form" style="display:none">' +
                '<input type="text" class="gweb-tpl-add-name" placeholder="' + lang.template_placeholder + '" maxlength="60" />' +
                '<textarea class="gweb-tpl-add-body" placeholder="' + lang.tpl_body_placeholder + '" rows="3"></textarea>' +
                '<div class="gweb-shortcodes-row gweb-tpl-add-sc">' +
                    '<span class="gweb-shortcodes-label">' + lang.insert_label + '</span>' +
                    '<button type="button" class="gweb-sc-btn" data-code="%order_id%">Order ID</button>' +
                    '<button type="button" class="gweb-sc-btn" data-code="%order_number%">Order #</button>' +
                    '<button type="button" class="gweb-sc-btn" data-code="%order_status%">Status</button>' +
                    '<button type="button" class="gweb-sc-btn" data-code="%first_name%">First Name</button>' +
                    '<button type="button" class="gweb-sc-btn" data-code="%last_name%">Last Name</button>' +
                    '<button type="button" class="gweb-sc-btn" data-code="%billing_phone%">Phone</button>' +
                    '<button type="button" class="gweb-sc-btn" data-code="%total_price%">Total</button>' +
                    '<button type="button" class="gweb-sc-btn" data-code="%payment_method%">Payment</button>' +
                    '<button type="button" class="gweb-sc-btn" data-code="%shipping_method%">Shipping</button>' +
                    '<button type="button" class="gweb-sc-btn" data-code="%billing_address%">Address</button>' +
                    '<button type="button" class="gweb-sc-btn" data-code="%shipping_address%">Ship Addr</button>' +
                '</div>' +
                '<div class="gweb-tpl-add-actions">' +
                    '<button type="button" class="gweb-tpl-cancel-btn button">' + lang.cancel + '</button>' +
                    '<button type="button" class="gweb-tpl-save-btn button button-primary">' + lang.save_btn + '</button>' +
                '</div>' +
            '</div>' +
        '</div>';

    $('textarea[name=order_note]').closest('p').before(templateBar);

    /* --- "Send SMS to Customer?" checkbox --- */
    $('#add_order_note').after(
        '<label for="wpsms_note_send" class="gweb-note-sms-label">' +
        '<input type="checkbox" id="wpsms_note_send" name="wpsms_note_send">' +
        lang.checkbox_label +
        '<span class="gweb-help-icon" data-tip="Only send SMS when Note to Customer is selected.">?</span>' +
        '</label>'
    );

    /* --- Collapsible shortcodes (toggled by checkbox) --- */
    var smsTools =
        '<div class="gweb-sms-tools" style="display:none">' +
            '<div class="gweb-shortcodes-row">' +
                '<span class="gweb-shortcodes-label">' + lang.insert_label + '</span>' +
                '<button type="button" class="gweb-sc-btn" data-code="%order_id%">Order ID</button>' +
                '<button type="button" class="gweb-sc-btn" data-code="%order_number%">Order #</button>' +
                '<button type="button" class="gweb-sc-btn" data-code="%order_status%">Status</button>' +
                '<button type="button" class="gweb-sc-btn" data-code="%first_name%">First Name</button>' +
                '<button type="button" class="gweb-sc-btn" data-code="%last_name%">Last Name</button>' +
                '<button type="button" class="gweb-sc-btn" data-code="%billing_phone%">Phone</button>' +
                '<button type="button" class="gweb-sc-btn" data-code="%total_price%">Total</button>' +
                '<button type="button" class="gweb-sc-btn" data-code="%payment_method%">Payment</button>' +
                '<button type="button" class="gweb-sc-btn" data-code="%shipping_method%">Shipping</button>' +
                '<button type="button" class="gweb-sc-btn" data-code="%billing_address%">Address</button>' +
                '<button type="button" class="gweb-sc-btn" data-code="%shipping_address%">Ship Addr</button>' +
            '</div>' +
            '<span id="gweb-send-toast" class="gweb-send-toast"></span>' +
        '</div>';

    $('#add_order_note').closest('p').after(smsTools);
    if ($('#wpsms_note_send').prop('checked')) $('.gweb-sms-tools').show();

    /* --- Toggle shortcodes on checkbox change --- */
    $(document).on('change', '#wpsms_note_send', function () {
        if ($(this).prop('checked')) {
            $('.gweb-sms-tools').slideDown(200);
        } else {
            $('.gweb-sms-tools').slideUp(200);
        }
    });

    /* --- Inline toast --- */
    function showToast(msg, type) {
        var $t = $('#gweb-send-toast');
        $t.text(msg).attr('data-type', type || '').addClass('gweb-toast-show');
        clearTimeout($t.data('tid'));
        $t.data('tid', setTimeout(function () {
            $t.removeClass('gweb-toast-show');
        }, 2200));
    }

    /* --- Shortcode insertion (smart: note textarea or add-form textarea) --- */
    $(document).on('click', '.gweb-sc-btn', function (e) {
        e.preventDefault();
        var code = $(this).data('code');
        var $ta;
        if ($(this).closest('.gweb-tpl-add-form').length) {
            $ta = $('.gweb-tpl-add-body');
        } else {
            $ta = $('textarea[name=order_note]');
        }
        var ta = $ta[0];
        var start = ta.selectionStart;
        var end = ta.selectionEnd;
        var val = $ta.val();
        $ta.val(val.substring(0, start) + code + val.substring(end));
        ta.selectionStart = ta.selectionEnd = start + code.length;
        $ta.focus();
    });

    /* --- Fetch templates (for dropdown) --- */
    function fetchTemplates(cb) {
        $.get(wp_sms_woocommerce_metabox.ajax, {
            action: 'gweb_wc_get_templates',
            _nonce: metaboxNonce
        }, function (res) {
            cachedTemplates = (res.success && res.data) ? res.data : [];
            if (cb) cb(cachedTemplates);
        });
    }

    /* --- Build dropdown list --- */
    function buildDropdown(templates) {
        var html = '';
        if (templates.length) {
            html += '<div class="gweb-tpl-dropdown-header">' + lang.select_template + '</div>';
            $.each(templates, function (i, t) {
                var chars = Array.from(t.body);
                var preview = chars.length > 40 ? chars.slice(0, 40).join('') + '...' : t.body;
                html += '<div class="gweb-tpl-dropdown-item" data-index="' + i + '">' +
                    '<span class="gweb-tpl-dd-name">' + $('<span>').text(t.name).html() + '</span>' +
                    '<span class="gweb-tpl-dd-preview">' + $('<span>').text(preview).html() + '</span>' +
                    '<span class="gweb-tpl-dd-delete" data-index="' + i + '" title="' + lang.delete_confirm + '">&times;</span>' +
                    '</div>';
            });
        } else {
            html = '<div class="gweb-tpl-empty">' + lang.no_templates + '</div>';
        }
        $('#gweb-tpl-dropdown-list').html(html);
    }

    /* --- Toolbar: Load Template button --- */
    $(document).on('click', '.gweb-tpl-btn-load', function () {
        var $dd = $('.gweb-tpl-dropdown');
        var $af = $('.gweb-tpl-add-form');
        if ($dd.is(':visible')) {
            $dd.slideUp(150);
            $('.gweb-tpl-btn-load').removeClass('active');
            return;
        }
        $af.slideUp(150);
        $('.gweb-tpl-btn-add').removeClass('active');
        fetchTemplates(function (templates) {
            buildDropdown(templates);
            $dd.slideDown(150);
            $('.gweb-tpl-btn-load').addClass('active');
        });
    });

    /* --- Toolbar: Add Template button --- */
    $(document).on('click', '.gweb-tpl-btn-add', function () {
        var $af = $('.gweb-tpl-add-form');
        var $dd = $('.gweb-tpl-dropdown');
        if ($af.is(':visible')) {
            $af.slideUp(150);
            $('.gweb-tpl-btn-add').removeClass('active');
            return;
        }
        $dd.slideUp(150);
        $('.gweb-tpl-btn-load').removeClass('active');
        $af.slideDown(150);
        $('.gweb-tpl-btn-add').addClass('active');
        $('.gweb-tpl-add-name').focus();
    });

    /* --- Load template into order note textarea --- */
    $(document).on('click', '.gweb-tpl-dropdown-item', function (e) {
        if ($(e.target).hasClass('gweb-tpl-dd-delete')) return;
        var index = $(this).data('index');
        if (cachedTemplates.length && cachedTemplates[index]) {
            var $ta = $('textarea[name=order_note]');
            $ta.val(cachedTemplates[index].body).focus();
            $ta.addClass('gweb-flash');
            setTimeout(function () { $ta.removeClass('gweb-flash'); }, 400);
            $('.gweb-tpl-dropdown').slideUp(150);
            $('.gweb-tpl-btn-load').removeClass('active');
            showToast(lang.loaded, 'success');
        }
    });

    /* --- Delete template from dropdown --- */
    $(document).on('click', '.gweb-tpl-dd-delete', function (e) {
        e.stopPropagation();
        if (!confirm(lang.delete_confirm)) return;
        var index = $(this).data('index');
        var $btn = $(this);
        $btn.addClass('gweb-tpl-deleting');
        $.post(wp_sms_woocommerce_metabox.ajax, {
            action: 'gweb_wc_delete_template',
            index: index,
            _nonce: metaboxNonce
        }, function (res) {
            $btn.removeClass('gweb-tpl-deleting');
            if (res.success) {
                fetchTemplates(function (templates) {
                    buildDropdown(templates);
                    showToast(lang.deleted, 'success');
                });
            } else {
                showToast(res.data && res.data.text ? res.data.text : lang.error_generic, 'error');
            }
        });
    });

    /* --- Save template from Add form --- */
    $(document).on('click', '.gweb-tpl-save-btn', function () {
        var name = $('.gweb-tpl-add-name').val().trim();
        var body = $('.gweb-tpl-add-body').val().trim();
        if (!name) { showToast(lang.enter_name, 'error'); $('.gweb-tpl-add-name').focus(); return; }
        if (!body) { showToast(lang.enter_message, 'error'); $('.gweb-tpl-add-body').focus(); return; }
        var $btn = $(this);
        $btn.prop('disabled', true).text(lang.saving);
        $.post(wp_sms_woocommerce_metabox.ajax, {
            action: 'gweb_wc_save_template',
            name: name,
            body: body,
            _nonce: metaboxNonce
        }, function (res) {
            $btn.prop('disabled', false).text(lang.save_btn);
            if (res.success) {
                $('.gweb-tpl-add-name').val('');
                $('.gweb-tpl-add-body').val('');
                $('.gweb-tpl-add-form').slideUp(150);
                $('.gweb-tpl-btn-add').removeClass('active');
                fetchTemplates(function () {});
                showToast(lang.saved, 'success');
            } else {
                showToast(res.data && res.data.text ? res.data.text : lang.error_generic, 'error');
            }
        });
    });

    /* --- Cancel Add form --- */
    $(document).on('click', '.gweb-tpl-cancel-btn', function () {
        $('.gweb-tpl-add-name').val('');
        $('.gweb-tpl-add-body').val('');
        $('.gweb-tpl-add-form').slideUp(150);
        $('.gweb-tpl-btn-add').removeClass('active');
    });

    /* --- Add Note button: send SMS if checkbox is checked --- */
    $('#woocommerce-order-notes').on('click', 'button.add_note', function () {
        var note_msg = $('textarea[name=order_note]').val();
        var send_sms = $('input[name=wpsms_note_send]').prop('checked');
        var note_type = $('select[name=order_note_type]').val();

        if (note_msg && send_sms && note_type === 'customer') {
            $.ajax({
                url: wp_sms_woocommerce_metabox.ajax,
                type: 'GET',
                data: {
                    action: 'wp_sms_woocommerce_metabox',
                    wpsms_note_status: 1,
                    wpsms_note_msg: note_msg,
                    wpsms_order_id: wp_sms_woocommerce_metabox.order_id,
                    _nonce: metaboxNonce
                },
                success: function (data) {
                    window.scroll({ top: 0, left: 0, behavior: 'smooth' });
                    $('.notice').remove();
                    if (data.error === 'yes') {
                        $('.wp-header-end').after('<div class="notice notice-error is-dismissible"><p><strong>' + data.text + '</strong></p></div>');
                    } else {
                        $('.wp-header-end').after('<div class="notice notice-success is-dismissible"><p><strong>' + data.text + '</strong></p></div>');
                    }
                },
                error: function () { showToast(lang.ajax_error, 'error'); }
            });
        }
    });

    $(document).on('click', '.notice-dismiss', function () {
        $(this).parent('.notice').remove();
    });
});
