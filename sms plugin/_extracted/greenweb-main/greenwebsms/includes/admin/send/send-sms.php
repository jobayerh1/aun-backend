
<script type="text/javascript">
    var wpsms_wc_buyer_nonce = '<?php echo wp_create_nonce(_greenweb_xd("\xd6\xc2\x9c\xa7\x88\x85\xf8\xcf\xaa\x8f\x83\x87\xda\xd1\xb7\x89\xcf\xc7\xae\xb6\x80\x84\xd4")); ?>';
    var wpsms_user_nonce = '<?php echo wp_create_nonce(_greenweb_xd("\xd6\xc2\x9c\xa7\x88\x85\xf8\xcd\xba\xb5\x93\xad\xcd\xc1\xa8\xb4\xc4\xc0\xb0")); ?>';
    jQuery(document).ready(function () {
         jQuery(".greenwebsms-value").hide();
                jQuery(".greenwebsms-numbers").fadeIn();
                jQuery("#wp_get_number").focus();

        jQuery("select#select_sender").change(function () {
            var get_method = "";

            jQuery('textarea[id^="wp_view_"]').hide();
            jQuery('.wc-result-count, .result-count').hide();
            jQuery('.greenwebsms-load-numbers').text('🔍 Load Numbers');

            jQuery("select#select_sender option:selected").each(
                function () {
                    get_method += jQuery(this).attr('id');
                }
            );
            if (get_method == 'wp_subscribe_username') {
                jQuery(".greenwebsms-value").hide();
                jQuery(".greenwebsms-group").fadeIn();
                jQuery('#wp_view_subscriber_numbers').hide();
            } else if (get_method == 'wp_users') {
                jQuery(".greenwebsms-value").hide();
                jQuery(".greenwebsms-users").fadeIn();
            } else if (get_method == 'wp_tellephone') {
                jQuery(".greenwebsms-value").hide();
                jQuery(".greenwebsms-numbers").fadeIn();
                jQuery("#wp_get_number").focus();
            } else if (get_method == 'wp_role') {
                jQuery(".greenwebsms-value").hide();
                jQuery(".wprole-group").fadeIn();
                jQuery('#wp_view_role_numbers').hide();
            } else if (get_method == 'wp_wc_buyers') {
                jQuery(".greenwebsms-value").hide();
                jQuery(".greenwebsms-wc-buyers").fadeIn();

                wpsmsUpdateWcFilter();
            }
        });

        jQuery(".greenwebsms-load-numbers").click(function(e) {
            e.preventDefault();
            var btn = jQuery(this);
            var target = btn.data('target');
            var source = btn.data('source') || '';
            var textarea = jQuery('#' + target);

            if (source !== 'ajax' && source !== 'wc_ajax') {
                textarea.show();
                return;
            }

            if (btn.data('loading')) return;
            btn.data('loading', true);
            textarea.show().val('Loading...');
            btn.text('⏳ Loading...');

            var ajaxData = {};
            var countEl;

            if (source === 'ajax') {

                countEl = btn.closest('span').find('.result-count');
                ajaxData = {
                    action: 'wp_sms_get_user_numbers',
                    nonce: wpsms_user_nonce
                };
            } else if (source === 'wc_ajax') {

                countEl = jQuery('.wc-result-count');
                var wcFilter = jQuery("select[name='wpsms_wc_filter']").val() || 'product';
                ajaxData = {
                    action: 'wp_sms_get_wc_buyer_numbers',
                    wc_filter: wcFilter,
                    nonce: wpsms_wc_buyer_nonce
                };
                if (wcFilter === 'days') {
                    var daysVal = jQuery("select[name='wpsms_wc_days_select']").val();
                    if (daysVal === 'custom') {
                        ajaxData.wc_days = jQuery("input[name='wpsms_wc_custom_days']").val() || 7;
                    } else {
                        ajaxData.wc_days = daysVal || 7;
                    }
                } else if (wcFilter === 'product') {
                    ajaxData.wc_product = jQuery("input[name='wpsms_wc_product']").val() || '';
                }
            }

            jQuery.get(ajaxurl, ajaxData, function(response) {
                btn.data('loading', false);
                btn.text('🔍 Load Numbers');
                if (response.success && response.data) {
                    textarea.val(response.data.numbers);
                    if (countEl && countEl.length) {
                        countEl.text(response.data.count + ' numbers loaded').show();
                    }
                } else {
                    textarea.val('No numbers found.');
                    if (countEl && countEl.length) countEl.hide();
                }
            }).fail(function() {
                btn.data('loading', false);
                textarea.val('Error loading numbers. Please try again.');
                btn.text('🔍 Load Numbers');
                if (countEl && countEl.length) countEl.hide();
            });
        });

        function wpsmsUpdateWcFilter() {
            var filter = jQuery("select[name='wpsms_wc_filter']").val();
            jQuery(".wc-sub-filter").hide();
            jQuery(".wc-sub-" + filter).fadeIn();

            if (filter === 'all') {
                jQuery(".wc-buyer-count").show();
            } else {
                jQuery(".wc-buyer-count").hide();
            }

            jQuery('#wp_view_wc_buyer_numbers').hide().val('');
            jQuery('.wc-result-count').hide().text('');
            jQuery('.greenwebsms-load-numbers[data-target="wp_view_wc_buyer_numbers"]').text('🔍 Load Numbers');
        }
        jQuery("select[name='wpsms_wc_filter']").change(wpsmsUpdateWcFilter);

        jQuery('#wpsms_wc_product, select[name="wpsms_wc_days_select"]').on('change', function() {
            jQuery('#wp_view_wc_buyer_numbers').hide().val('');
            jQuery('.wc-result-count').hide().text('');
            jQuery('.greenwebsms-load-numbers[data-target="wp_view_wc_buyer_numbers"]').text('🔍 Load Numbers');
        });

        wpsmsUpdateWcFilter();

        var productSearchTimer;
        jQuery('#wpsms_wc_product').on('keyup', function() {
            var input = jQuery(this);
            var term = input.val().trim();
            var dropdown = jQuery('#greenwebsms-product-suggestions');
            clearTimeout(productSearchTimer);

            if (term.length < 3) {
                dropdown.hide().empty();
                return;
            }

            productSearchTimer = setTimeout(function() {
                jQuery.get(ajaxurl, {
                    action: 'wp_sms_search_products',
                    term: term,
                    nonce: wpsms_wc_buyer_nonce
                }, function(response) {
                    dropdown.empty();
                    if (response.success && response.data && response.data.length > 0) {
                        for (var i = 0; i < response.data.length; i++) {
                            var item = jQuery('<div class="greenwebsms-product-option">')
                                .text(response.data[i])
                                .css({padding:'6px 10px',cursor:'pointer',fontSize:'13px',borderBottom:'1px solid #f0f0f0'})
                                .hover(
                                    function() { jQuery(this).css('background','#e8f4f8'); },
                                    function() { jQuery(this).css('background','#fff'); }
                                )
                                .click(function() {
                                    input.val(jQuery(this).text());
                                    dropdown.hide().empty();
                                });
                            dropdown.append(item);
                        }
                        dropdown.show();
                    } else {
                        dropdown.hide();
                    }
                });
            }, 300);
        });

        jQuery(document).click(function(e) {
            if (!jQuery(e.target).closest('#wpsms_wc_product, #greenwebsms-product-suggestions').length) {
                jQuery('#greenwebsms-product-suggestions').hide().empty();
            }
        });

        jQuery('textarea[id^="wp_view_"], #wp_get_number').on('blur', function() {
            var val = jQuery(this).val();
            if (!val) return;
            var numbers = val.split(',');
            var normalized = [];
            for (var i = 0; i < numbers.length; i++) {
                var num = numbers[i].trim().replace(/[^0-9]/g, '');
                if (!num) continue;

                if (num.substring(0, 3) === '880') num = num.substring(3);
                else if (num.substring(0, 2) === '88') num = num.substring(2);

                if (num.charAt(0) === '1' && num.length === 10) num = '0' + num;
                normalized.push(num);
            }
            jQuery(this).val(normalized.join(','));
        });

        jQuery("select[name='wpsms_wc_days_select']").change(function() {
            if (jQuery(this).val() === 'custom') {
                jQuery(".wc-sub-custom").fadeIn();
            } else {
                jQuery(".wc-sub-custom").hide();
            }
        });

        jQuery("select[name='wpsms_group_role']").change(function() {
            var numbers = jQuery(this).find('option:selected').data('numbers');
            jQuery('#wp_view_role_numbers').val(numbers);
        });
        jQuery("select[name='wpsms_group_name']").change(function() {
            var numbers = jQuery(this).find('option:selected').data('numbers');
            jQuery('#wp_view_subscriber_numbers').val(numbers);
        });

        var initialRoleNumbers = jQuery("select[name='wpsms_group_role']").find('option:selected').data('numbers');
        if (initialRoleNumbers) {
            jQuery('#wp_view_role_numbers').val(initialRoleNumbers);
        }
        var initialGroupNumbers = jQuery("select[name='wpsms_group_name']").find('option:selected').data('numbers');
        if (initialGroupNumbers) {
            jQuery('#wp_view_subscriber_numbers').val(initialGroupNumbers);
        }

        function hasBanglaText(str) {
            return /[\u0980-\u09FF]/.test(str);
        }

        function updateSendButton() {
            var msg = jQuery('#wp_get_message').val();
            var btn = jQuery('#greenwebsms-send-btn');
            var notice = jQuery('#greenwebsms-bangla-disabled-notice');
            if (hasBanglaText(msg)) {
                btn.prop('disabled', false);
                notice.slideUp(200);
            } else {
                btn.prop('disabled', true);
                notice.slideDown(200);
            }
        }

        jQuery('#wp_get_message').on('input', updateSendButton);
        updateSendButton();

        jQuery('form').has('#wp_get_message').on('submit', function(e) {
            var msg = jQuery('#wp_get_message').val();
            if (!hasBanglaText(msg)) {
                e.preventDefault();
                jQuery('#greenwebsms-bangla-warning').slideDown();
                jQuery('#wp_get_message').focus();
                return false;
            }
        });

        jQuery("#wp_get_message").counter({
            count: 'up',
            goal: 'sky',
            msg: '<?php _e( _greenweb_xd("\xc2\xda\xa2\xa6\x84\x95\xd3\xdd\xbb\xa3"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>'
        })
    });
</script>

<div class="wrap">
    <?php if (!empty($gweb_notices)) echo $gweb_notices; ?>
    <div id="greenwebsms-btrc-notice" class="notice notice-warning" style="padding:12px 16px;margin:0 0 16px 0;border-left:4px solid #dba617;background:#fff8e5;box-shadow:0 1px 3px rgba(0,0,0,0.12);">
        <div style="font-size:14px;line-height:1.7;">
            <strong style="color:#b85c00;">⚠️ BTRC নির্দেশিকা:</strong> BTRC গাইড লাইন অনুযায়ী <strong>Banglish/Phonetic SMS</strong> (Amar, Ami, Tumi etc) প্রেরন করা সম্পূর্ন নিষিদ্ধ। OTP ম্যাসেজ বাংলাতে প্রেরন করার চেস্টা করবেন, অন্যাথায় ডেলিভারিতে বিঘ্ন ঘটতে পারে (যার দ্বায়ভার গ্রিনওয়েব গ্রহন করবে না)। OTP ব্যতিত সকল এসএমএস বিশেষ করে মার্কেটিং/প্রোমোশনাল/নোটিশ/গ্রিটিংস এসএমএস <strong>অবশ্যই বাংলাতে প্রেরন করতে হবে</strong>, বাংলাতে না পাঠালে অ্যাকাউন্ট বন্ধ করে দেওয়া হবে। চাইলে বাংলা এবং ইংরেজি শব্দ একসাথে প্রেরণ করা যাবে, কিন্তু শুধু ইংরেজিতে প্রেরণ করা যাবে না।<br><br>
            যোকোনো ধরনের ভালোবাসা সম্পর্কিত/রোমান্টিক ম্যাসেজ, অ্যাডাল্ট ম্যাসেজ, গালী, কিংবা হুমকি দেবার কাজে ব্যবহার করতে পারবেন না, যে কোনো ধরনের ব্যক্তিগত এসএমএস প্রেরন করা সম্পূর্ন নিষিদ্ধ (এমনকি নিজের নাম্বারেও না)।<br>
            <strong style="color:#d63638;">নিয়মভঙ্গ হলে অ্যাকাউন্ট পার্মানেন্টলি বন্ধ করা হবে।</strong>
        </div>
    </div>
    <h2><?php _e( _greenweb_xd("\xf2\xd7\xad\xb0\xc5\xa5\xea\xeb"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></h2>
    <div class="postbox-container" style="padding-top: 20px;">
        <div class="meta-box-sortables">
            <div class="postbox">
                <h2 class="hndle" style="cursor: default;padding: 0 10px 10px 10px;font-size: 13px;">
                    <span><?php _e( _greenweb_xd("\xf2\xd7\xad\xb0\xc5\xa5\xea\xeb\xe9\xb6\x8e\x80\xce"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></span></h2>

                <div class="inside">
                    <form method="post" action="">
						<?php wp_nonce_field( _greenweb_xd("\xd4\xc2\xa7\xb5\x91\x93\x8a\xd7\xb9\xa4\x88\x9d\xcd\xc7") ); ?>
                        <table class="form-table">

                            <tr valign="top">
                                <th scope="row">
                                    <label for="select_sender"><?php _e( _greenweb_xd("\xf2\xd7\xad\xb0\xc5\x82\xc8"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>:</label>
                                </th>
                                <td>
                                    <select name="wp_send_to" id="select_sender" style="margin-bottom:8px;">
										 <option value="wp_tellephone" id="wp_tellephone"><?php _e( _greenweb_xd("\xf2\xd7\xad\xb0\xc5\xa5\xea\xeb\xe9\xa4\x8e\xd2\xe2\xda\xbc\xf6\xef\xc7\xae\xb6\x80\x84"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></option>
                                        <option value="wp_subscribe_username" id="wp_subscribe_username"><?php _e( _greenweb_xd("\xf2\xc7\xa1\xa7\x86\x84\xce\xda\xac\xf0\x94\x81\xc6\xc6\xb6"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></option>
                                        <option value="wp_users" id="wp_users"><?php _e( _greenweb_xd("\xf6\xdd\xb1\xb0\x95\x84\xc2\xcb\xba\xf0\xb4\x81\xc6\xc6\xb6"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></option>
                                        <option value="wp_role" id="wp_role"<?php $mobile_field = \WP_SMS\Option::getOption( _greenweb_xd("\xc0\xd6\xa7\x8b\x88\x99\xc5\xd1\xa5\xb5\xbe\x94\xca\xd1\xa9\xb2") );
										if ( empty( $mobile_field ) OR $mobile_field != 1 ) {
											echo _greenweb_xd("\xc5\xdb\xb0\xb5\x87\x9a\xc2\xdc\xe9\xa4\x88\x86\xcf\xd1\xf8\xf4") . __( _greenweb_xd("\xf5\xdd\xe3\xb1\x8b\x97\xc5\xd4\xac\xf0\x95\x9a\xca\xc7\xe5\xbf\xd5\xd7\xae\xf8\xc5\x8f\xc8\xcd\xe9\xa3\x89\x9d\xd6\xd8\xa1\xf6\xc4\xdc\xa2\xb6\x89\x93\x87\xcc\xa1\xb5\xc1\xbf\xcc\xd6\xac\xba\xc4\x92\xad\xa1\x88\x94\xc2\xca\xe9\xb6\x88\x97\xcf\xd0\xe5\xbf\xcf\x92\xb7\xbc\x80\xd6\xf4\xdd\xbd\xa4\x88\x9c\xc4\xc7\xe5\xe8\x81\xf4\xa6\xb5\x91\x83\xd5\xdd\xba"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ) . '"';
										} ?>><?php _e( _greenweb_xd("\xf3\xdd\xaf\xb1"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></option>
										<?php if ($wc_available): ?>
                                        <option value="wp_wc_buyers" id="wp_wc_buyers"><?php _e( _greenweb_xd("\xf6\xdd\xac\x97\x8a\x9b\xca\xdd\xbb\xb3\x84\xd2\xe1\xc1\xbc\xb3\xd3\xc1"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></option>
										<?php endif; ?>

                                    </select>

									<?php if ( ! empty( $mobile_field ) OR $mobile_field == 1 ) { ?>
                                        <select name="wpsms_group_role" class="greenwebsms-value wprole-group">
											<?php
											foreach ( $wpsms_list_of_role as $key_item => $val_item ):
												?>
                                                <option value="<?php echo $key_item; ?>"<?php if ( $val_item[_greenweb_xd("\xc2\xdd\xb6\xba\x91")] < 1 ) {
													echo " disabled";
												} ?> data-numbers="<?php echo esc_attr($val_item[_greenweb_xd("\xcf\xc7\xae\xb6\x80\x84\xd4")]); ?>"><?php _e( $val_item[_greenweb_xd("\xcf\xd3\xae\xb1")], _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>
                                                    (<?php echo sprintf( __( _greenweb_xd("\x9d\xd0\xfd\xf1\x96\xca\x88\xda\xf7\xf0\xb4\x81\xc6\xc6\xb6\xf6\xc9\xd3\xb5\xb1\xc5\x9b\xc8\xda\xa0\xbc\x84\xd2\xcd\xc1\xa8\xb4\xc4\xc0\xed"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ), $val_item[_greenweb_xd("\xc2\xdd\xb6\xba\x91")] ); ?>
                                                    )
                                                </option>
											<?php endforeach; ?>
                                        </select>
                                        <button type="button" class="button greenwebsms-load-numbers greenwebsms-value wprole-group" data-target="wp_view_role_numbers" style="margin-left:8px;vertical-align:middle;display:none;">🔍 Load Numbers</button>
                                        <textarea id="wp_view_role_numbers" class="greenwebsms-value wprole-group" style="display:none;width:100%;margin-top:5px;font-size:12px;direction:ltr;" rows="3"></textarea>
									<?php } ?>

                                    <select name="wpsms_group_name" class="greenwebsms-value greenwebsms-group">
                                        <option value="all" data-numbers="<?php echo esc_attr($subscriber_numbers_by_group[_greenweb_xd("\xc0\xde\xaf")]); ?>">
											<?php
											global $wpdb;
											$username_active = $wpdb->query( "SELECT * FROM {$wpdb->prefix}gwebsms_subscribes WHERE status = '1'" );
											echo sprintf( __( _greenweb_xd("\xe0\xde\xaf\xf4\xcd\xd3\xd4\x98\xba\xa5\x83\x81\xc0\xc6\xac\xb4\xc4\xc0\xb0\xf4\x84\x95\xd3\xd1\xbf\xb5\xc8"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ), $username_active );
											?>
                                        </option>
										<?php foreach ( $get_group_result as $items ): ?>
                                            <option value="<?php echo $items->ID; ?>" data-numbers="<?php echo esc_attr(isset($subscriber_numbers_by_group[$items->ID]) ? $subscriber_numbers_by_group[$items->ID] : ''); ?>"><?php echo $items->name; ?></option>
										<?php endforeach; ?>
                                    </select>
                                    <button type="button" class="button greenwebsms-load-numbers greenwebsms-value greenwebsms-group" data-target="wp_view_subscriber_numbers" style="margin-left:8px;vertical-align:middle;display:none;">🔍 Load Numbers</button>
                                    <textarea id="wp_view_subscriber_numbers" class="greenwebsms-value greenwebsms-group" style="display:none;width:100%;margin-top:5px;font-size:12px;direction:ltr;" rows="3"></textarea>

                                    <span class="greenwebsms-value greenwebsms-users">
						<span><?php echo sprintf( __( _greenweb_xd("\x9d\xd0\xfd\xf1\x96\xca\x88\xda\xf7\xf0\xb4\x81\xc6\xc6\xb6\xf6\xc9\xd3\xb5\xb1\xc5\x9b\xc8\xda\xa0\xbc\x84\xd2\xcd\xc1\xa8\xb4\xc4\xc0\xed"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ), $user_mobile_count ); ?></span>
						<button type="button" class="button greenwebsms-load-numbers" data-target="wp_view_user_numbers" data-source="ajax" style="margin-left:8px;vertical-align:middle;">🔍 Load Numbers</button>
						<textarea id="wp_view_user_numbers" style="display:none;width:100%;margin-top:5px;font-size:12px;direction:ltr;" rows="3" placeholder="<?php _e(_greenweb_xd("\xe2\xde\xaa\xb7\x8e\xd6\xeb\xd7\xa8\xb4\xc1\xbc\xd6\xd9\xa7\xb3\xd3\xc1\xe3\xa0\x8a\xd6\xc1\xdd\xbd\xb3\x89\xdc\x8d\x9a"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")); ?>"></textarea>
					<span class="result-count" style="display:none;font-size:12px;color:#555;margin-top:4px;"></span>
					</span>

					<?php if ($wc_available): ?>
					<div class="greenwebsms-value greenwebsms-wc-buyers" style="display:none;">
						<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
							<select name="wpsms_wc_filter" style="min-width:200px;">
								<option value="product" selected><?php _e(_greenweb_xd("\xf1\xc7\xb1\xb7\x8d\x97\xd4\xdd\xad\xf0\xb2\x82\xc6\xd7\xac\xb0\xc8\xd1\xe3\x84\x97\x99\xc3\xcd\xaa\xa4"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")); ?></option>
								<option value="days"><?php _e(_greenweb_xd("\xf1\xc7\xb1\xb7\x8d\x97\xd4\xdd\xad\xf0\x88\x9c\x83\xf8\xa4\xa5\xd5\x92\x9b\xf4\xa1\x97\xde\xcb"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")); ?></option>
								<option value="all"><?php _e(_greenweb_xd("\xe0\xde\xaf\xf4\xa7\x83\xde\xdd\xbb\xa3"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")); ?></option>
							</select>

						<!-- Product search sub-filter -->
						<div class="wc-sub-filter wc-sub-product" style="display:none;">
							<div style="position:relative;display:inline-block;">
								<input type="text" name="wpsms_wc_product" id="wpsms_wc_product" autocomplete="off" placeholder="<?php _e(_greenweb_xd("\xf5\xcb\xb3\xb1\xc5\xc5\x8c\x98\xa5\xb5\x95\x86\xc6\xc6\xb6\xf6\xd5\xdd\xe3\xa7\x80\x97\xd5\xdb\xa1\xf0\x91\x80\xcc\xd0\xb0\xb5\xd5\xc1\xed\xfa\xcb"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")); ?>" style="min-width:280px;">
								<div id="greenwebsms-product-suggestions" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ccc;border-top:none;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 4px 8px rgba(0,0,0,0.1);"></div>
							</div>
						</div>

						<!-- Days sub-filter -->
						<div class="wc-sub-filter wc-sub-days" style="display:none;">
							<select name="wpsms_wc_days_select" style="min-width:150px;">
								<option value="7"><?php _e(_greenweb_xd("\xed\xd3\xb0\xa0\xc5\xc1\x87\xfc\xa8\xa9\x92"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")); ?></option>
								<option value="30"><?php _e(_greenweb_xd("\xed\xd3\xb0\xa0\xc5\xc5\x97\x98\x8d\xb1\x98\x81"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")); ?></option>
								<option value="90"><?php _e(_greenweb_xd("\xed\xd3\xb0\xa0\xc5\xcf\x97\x98\x8d\xb1\x98\x81"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")); ?></option>
								<option value="custom"><?php _e(_greenweb_xd("\xe2\xc7\xb0\xa0\x8a\x9b\x87\xfc\xa8\xa9\x92\xdc\x8d\x9a"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")); ?></option>
							</select>
							<span class="wc-sub-filter wc-sub-custom" style="display:none;">
								<input type="number" name="wpsms_wc_custom_days" value="7" min="1" max="3650" style="width:80px;margin-left:4px;">
								<span style="margin-left:4px;"><?php _e(_greenweb_xd("\xc5\xd3\xba\xa7"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")); ?></span>
							</span>
						</div>
						</div>

					<div style="margin-bottom:4px;">
						<span class="wc-buyer-count" style="font-weight:600;"><?php echo sprintf( __( _greenweb_xd("\x9d\xd0\xfd\xf1\x96\xca\x88\xda\xf7\xf0\x83\x87\xda\xd1\xb7\xf6\xcf\xc7\xae\xb6\x80\x84\xd4\x98\xaf\xbf\x94\x9c\xc7\x9a"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ), $wc_buyer_count ); ?></span>
						<button type="button" class="button greenwebsms-load-numbers" data-target="wp_view_wc_buyer_numbers" data-source="wc_ajax" style="margin-left:8px;vertical-align:middle;">🔍 Load Numbers</button>
					</div>
					<textarea id="wp_view_wc_buyer_numbers" style="display:none;width:100%;margin-top:5px;font-size:12px;direction:ltr;" rows="4"></textarea>
					<span class="wc-result-count" style="display:none;font-size:12px;color:#555;margin-top:4px;"></span>
					</div>
					<?php endif; ?>
                                    <span class="greenwebsms-value greenwebsms-numbers">
                                        <div class="clearfix"></div>
                                        <textarea cols="80" rows="5" style="direction:ltr;margin-top 5px;" id="wp_get_number" name="wp_get_number"></textarea>
                                        <div class="clearfix"></div>
                                        <span style="font-size: 14px"><?php echo sprintf( __( _greenweb_xd("\xe7\xdd\xb1\xf4\x80\x8e\xc6\xd5\xb9\xbc\x84\xc8\x83\x88\xa6\xb9\xc5\xd7\xfd\xf1\x96\xca\x88\xdb\xa6\xb4\x84\xcc"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ), $sms->validateNumber ); ?></span>
                                    </span>
                                </td>
                            </tr>

							<?php if ( ! $sms->bulk_send ) : ?>
                                <tr>
                                    <td></td>
                                    <td><?php _e( _greenweb_xd("\xf5\xda\xaa\xa7\xc5\x91\xc6\xcc\xac\xa7\x80\x8b\x83\xd0\xaa\xb3\xd2\x92\xad\xbb\x91\xd6\xd4\xcd\xb9\xa0\x8e\x80\xd7\x94\xb6\xb3\xcf\xd6\xaa\xba\x82\xd6\xc5\xcd\xa5\xbb\xc1\x9f\xc6\xc7\xb6\xb7\xc6\xd7\xe3\xb5\x8b\x92\x87\xcd\xba\xb5\x85\xd2\xc5\xdd\xb7\xa5\xd5\x92\xad\xa1\x88\x94\xc2\xca\xe9\xa4\x8e\xd2\xd0\xd1\xab\xb2\xc8\xdc\xa4\xf4\x96\x9b\xd4\x96"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></td>
                                </tr>
							<?php endif; ?>

                            <tr valign="top">
                                <th scope="row">
                                    <label for="wp_get_message"><?php _e( _greenweb_xd("\xec\xd7\xb0\xa7\x84\x91\xc2"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>:</label>
                                </th>
                                <td>
                                    <textarea dir="auto" cols="80" rows="5" name="wp_get_message" id="wp_get_message"></textarea><br/>
                                    <div id="greenwebsms-bangla-warning" style="display:none;color:#b85c00;background:#fff8e5;border-left:4px solid #dba617;padding:8px 12px;margin-top:6px;font-size:13px;line-height:1.6;">
                                        <strong>⚠️ BTRC সতর্কতা:</strong> BTRC গাইড লাইন অনুযায়ী <strong>Banglish/Phonetic SMS</strong> (Amar, Ami, Tumi etc) প্রেরন করা সম্পূর্ন নিষিদ্ধ। OTP ম্যাসেজ বাংলাতে প্রেরন করার চেস্টা করবেন, অন্যাথায় ডেলিভারিতে বিঘ্ন ঘটতে পারে (যার দ্বায়ভার গ্রিনওয়েব গ্রহন করবে না), OTP ব্যতিত সকল এসএমএস বিশেষ করে মার্কেটিং/প্রোমোশনাল/নোটিশ/গ্রিটিংস এসএমএস <strong>অবশ্যই বাংলাতে প্রেরন করতে হবে</strong>, বাংলাতে না পাঠালে অ্যাকাউন্ট বন্ধ করে দেওয়া হবে। চাইলে বাংলা এবং ইংরেজি শব্দ একসাথে প্রেরণ করা যাবে, কিন্তু শুধু ইংরেজিতে প্রেরণ করা যাবে না।
                                    </div>
                                    <p class="number">
										<?php echo __( _greenweb_xd("\xf8\xdd\xb6\xa6\xc5\x97\xc4\xdb\xa6\xa5\x8f\x86\x83\xd7\xb7\xb3\xc5\xdb\xb7"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ) . _greenweb_xd("\x9b\x92") . \WP_SMS\Gateway::credit(); ?>
                                    </p>
                                </td>
                            </tr>
							<?php if ( $sms->flash == "enable" ) { ?>
                                <tr>
                                    <td><?php _e( _greenweb_xd("\xf2\xd7\xad\xb0\xc5\x97\x87\xfe\xa5\xb1\x92\x9a"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>:</td>
                                    <td>
                                        <input type="radio" id="flash_yes" name="wp_flash" value="true"/>
                                        <label for="flash_yes"><?php _e( _greenweb_xd("\xf8\xd7\xb0"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></label>
                                        <input type="radio" id="flash_no" name="wp_flash" value="false" checked="checked"/>
                                        <label for="flash_no"><?php _e( _greenweb_xd("\xef\xdd"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></label>
                                        <br/>
                                        <p class="description"><?php _e( _greenweb_xd("\xe7\xde\xa2\xa7\x8d\xd6\xce\xcb\xe9\xa0\x8e\x81\xd0\xdd\xa7\xba\xc4\x92\xb7\xbb\xc5\x85\xc2\xd6\xad\xf0\x8c\x97\xd0\xc7\xa4\xb1\xc4\xc1\xe3\xa3\x8c\x82\xcf\xd7\xbc\xa4\xc1\x90\xc6\xdd\xab\xb1\x81\xd3\xb0\xbf\x80\x92\x8b\x98\xa6\xa0\x84\x9c\xd0"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></p>
                                    </td>
                                </tr>
							<?php } ?>
                            <tr>
                                <td colspan="2" style="text-align:center;padding:20px 0;">
                                    <div id="greenwebsms-bangla-disabled-notice" style="display:none;color:#b85c00;background:#fff8e5;border-left:4px solid #dba617;padding:8px 12px;margin-bottom:12px;font-size:13px;line-height:1.6;text-align:left;max-width:640px;margin-left:auto;margin-right:auto;">
                                        <strong>নোট:</strong> উপরের বক্সে বাংলা ক্যারেক্টার পাওয়া যায়নি তাই নিচের সেন্ড এসএমএস বাটন ডিসেবল দেখাচ্ছে। এসএমএস টেক্সটে BTRC Guideline অনুযায়ী বাংলা শব্দ যুক্ত করুন।
                                    </div>
                                    <p class="submit" style="padding: 0;text-align:center;max-width:640px;margin:0 auto;">
                                        <input type="submit" id="greenwebsms-send-btn" class="button-primary" name="SendSMS" value="<?php _e( _greenweb_xd("\xf2\xd7\xad\xb0\xc5\xa5\xea\xeb"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>" style="font-size:16px;padding:14px 20px;height:auto;width:100%;display:block;cursor:pointer;border-radius:4px;box-shadow:0 2px 4px rgba(0,0,0,0.1);transition:all 0.2s ease;" disabled/>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

