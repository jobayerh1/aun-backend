(function ($) {
    if (typeof ajaxurl === "undefined") {
        ajaxurl = updaterWPFDparams.ajaxurl;
    }

    var ju_update_new = function (plugin, slug) {
        var $updateRow, $card, $message, message;
        var wp55 = false;
        if (typeof wp.i18n !== "undefined") {
            var __ = wp.i18n.__,
                _x = wp.i18n._x,
                sprintf = wp.i18n.sprintf;
            wp55 = true;
        }

        if ('plugins' === pagenow || 'plugins-network' === pagenow) {
            $updateRow = $('tr[data-plugin="' + plugin + '"]');
            $message = $updateRow.find('.update-message').removeClass('notice-error').addClass('updating-message notice-warning').find('p');
            if (wp55) {
                message = sprintf(
                    /* translators: %s: Plugin name and version. */
                    _x('Updating %s...', 'plugin'),
                    $updateRow.find('.plugin-title strong').text()
                );
            } else {
                message = wp.updates.l10n.updatingLabel.replace('%s', $updateRow.find('.plugin-title strong').text());
            }

        } else if ('plugin-install' === pagenow || 'plugin-install-network' === pagenow) {
            $card = $('.plugin-card-' + slug);
            $message = $card.find('.update-now').addClass('updating-message');
            if (wp55) {
                message = sprintf(
                    /* translators: %s: Plugin name and version. */
                    _x('Updating %s...', 'plugin'),
                    $message.data('name')
                );
            } else {
                message = wp.updates.l10n.updatingLabel.replace('%s', $message.data('name'));
            }

            // Remove previous error messages, if any.
            $card.removeClass('plugin-card-update-failed').find('.notice.notice-error').remove();
        }

        if (wp55) {
            if ($message.html() !== __('Updating...')) {
                $message.data('originaltext', $message.html());
            }

            $message
                .attr('aria-label', message)
                .text(__('Updating...'));
        } else {
            if ($message.html() !== wp.updates.l10n.updating) {
                $message.data('originaltext', $message.html());
            }

            $message
                .attr('aria-label', message)
                .text(wp.updates.l10n.updating);
        }


        var args = {
            plugin: plugin,
            slug: slug
        };

        args = _.extend({
            success: wp.updates.updatePluginSuccess,
            error: wp.updates.updatePluginError
        }, args);
        wp.updates.ajax('update-plugin', args);
    };

    var JuupdatePlugin = function (plugin, slug) {
        var listplugins = [
            "wp-file-download",
            "wp-file-download-addon",
        ];

        if ($.inArray(slug, listplugins) !== -1) {
            if (updaterWPFDparams.token && updaterWPFDparams.token !== '') {
                $('#' + slug + '-update .update-message').append('<a style="margin-left:10px;color: #a00;" class="ju_check">Checking token...</a>');
                let link = updaterWPFDparams.ju_base + 'index.php?option=com_juupdater&task=download.checktokenV2&extension=' + slug + '.zip&token=' + updaterWPFDparams.token;
                $.ajax({
                    url: link,
                    method: 'GET',
                    dataType: 'json',
                    data: {},
                    success: function (response) {
                        $('#' + slug + '-update .update-message .ju_check').remove();
                        if (response.status === true) {
                            ju_update_new(plugin, slug);
                        } else {
                            var r = confirm(response.datas);
                            if (r === true) {
                                window.open(updaterWPFDparams.ju_base, "_blank");
                            }
                            var link = updaterWPFDparams.ju_base + "index.php?option=com_juupdater&view=connect&ext_name="+slug+"&tmpl=component&site=" + updaterWPFDparams.site_url + "&TB_iframe=true&width=600&height=550";
                            $('#' + slug + '-update .update-message').append('<p style="font-weight: bold; color: #ff6200;">In order to update please link your account : <a class="thickbox ju_update" href="' + link + '">JoomUnited account</a></p>');
                        }
                    }
                });
            } else {
                $('tr[data-slug="' + slug + '"] .thickbox.ju_update').click();
            }
        }
    };

    $(document).ready(function () {
        var ju_plugins = ['wp-file-download', 'wp-file-download-addon'];
        $.each(ju_plugins, function (i, slug) {
            if (!updaterWPFDparams.token || updaterWPFDparams.token === '') {
                $('#' + slug + '-update .update-message a.update-link').addClass('ju-update-link').removeClass('update-link').html('Connect your Joomunited account to update');
            } else {
                if ( $('#' + slug + '-update .update-message a.update-link').length ) {
                    $('#' + slug + '-update .update-message a.update-link').addClass('ju-update-link').removeClass('update-link');
                } else {
                    console.log('run wpfd updaterV2');
                    $('#' + slug + '-update .update-message a.ju-update-link').html('Update now');
                }
            }
            $('#' + slug + '-update td.plugin-update').css({
                'border-left': '4px solid #d54e21',
                'background-color': '#fef7f1'
            });
        });

        var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
        var eventer = window[eventMethod];
        var messageEvent = eventMethod === "attachEvent" ? "onmessage" : "message";

        // Listen to message from child window
        eventer(messageEvent, function (e) {

            var res = e.data;
            if (typeof res !== "undefined" && typeof res.type !== "undefined" && res.type === "joomunited_connect") {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        'action': 'wpfdju_update_license',
                        'token': res.token,
                        'ju_updater_nonce': updaterWPFDparams.ju_updater_nonce
                    },
                    success: function () {
                        location.reload();
                    }
                });
            }
        }, false);

        $('.plugin-update-tr').on('click', '.ju-update-link', function (e) {
            e.preventDefault();
            if (wp.updates.shouldRequestFilesystemCredentials && !wp.updates.ajaxLocked) {
                wp.updates.requestFilesystemCredentials(e);
            }
            var updateRow = $(e.target).parents('.plugin-update-tr');
            // Return the user to the input box of the plugin's table row after closing the modal.
            wp.updates.$elToReturnFocusToFromCredentialsModal = $('#' + updateRow.data('slug')).find('.check-column input');
            JuupdatePlugin(updateRow.data('plugin'), updateRow.data('slug'));
        });


    });
}(jQuery));
