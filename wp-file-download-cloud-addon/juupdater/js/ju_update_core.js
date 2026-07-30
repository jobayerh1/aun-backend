(function ($) {
    if (typeof ajaxurl === "undefined") {
        ajaxurl = updaterV2params.ajaxurl;
    }
    $(document).ready(function () {
        if (typeof updaterV2params.ju_base !== "undefined" && typeof updaterV2params.ju_content_url !== "undefined") {
            if (typeof updaterV2params.token !== "undefined" && updaterV2params.token !== '') {

            } else {
                var listplugins = [
                    "wp-media-folder",
                    "wp-media-folder-addon",
                    "wp-media-folder-gallery-addon",
                    "wp-file-download",
                    "wp-file-download-addon",
                    "wp-team-display",
                    "wp-table-manager",
                    "wp-latest-post",
                    "wp-latest-posts-addon",
                    "wp-meta-seo-addon",
                    "wp-speed-of-light-addon"
                ];

                $('#update-plugins-table').find('tr input[type="checkbox"][name="checked[]"]').each(function () {
                    var ju_plugin_file = $(this).val();
                    var slug = ju_plugin_file.substr(ju_plugin_file.indexOf('/') + 1, ju_plugin_file.indexOf('.') - ju_plugin_file.indexOf('/') - 1);
                    if($.inArray(slug,listplugins) !== -1 && !juTokens[slug]){
                        var link = updaterV2params.ju_base + "index.php?option=com_juupdater&view=connect&ext_name="+ slug+"&tmpl=component&site=" + updaterV2params.site_url + "&TB_iframe=true&width=300&height=305";
                        $(this).closest('tr').find('td.plugin-title').append('<p style="font-weight: bold; color: #ff6200;">In order to access updates please link your account : <a class="thickbox ju_update" href="' + link + '">JoomUnited account</a></p>');
                    }
                });
            }
        }

        var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
        var eventer = window[eventMethod];
        var messageEvent = eventMethod === "attachEvent" ? "onmessage" : "message";

        // Listen to message from child window
        eventer(messageEvent, function (e) {

            var listUpdateLicenseActionss = {};
            listUpdateLicenseActionss["wp-ai-assistant"] = 'waa';
            listUpdateLicenseActionss["wp-ultra-filter"] = 'wpuf';
            listUpdateLicenseActionss["wp-location-finder"] = 'wplf';
            listUpdateLicenseActionss["wp-media-folder"] = 'wpmf';
            listUpdateLicenseActionss["wp-media-folder-addon"] = 'wpmf';
            listUpdateLicenseActionss["wp-media-folder-gallery-addon"] = 'wpmf';
            listUpdateLicenseActionss["wp-file-download"] = 'wpfd';
            listUpdateLicenseActionss["wp-file-download-addon"] = 'wpfd';

            var res = e.data;
            if (typeof res !== "undefined" && typeof res.type !== "undefined" && res.type === "joomunited_connect") {
                var updateLicenseAction = "";
                if (res.extName in listUpdateLicenseActionss) {
                    updateLicenseAction = listUpdateLicenseActionss[res.extName];
                }
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        'action':  updateLicenseAction+'ju_update_license',
                        'token': res.token,
                        'ju_updater_nonce': updaterV2params.ju_updater_nonce
                    },
                    success: function () {
                        window.location.assign(document.URL);
                    }
                });
            }
        }, false);
    });
}(jQuery));