<div class="wrap">
    <h2><?php _e( _greenweb_xd("\xe6\xc0\xac\xa1\x95\x85"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></h2>

    <div class="greenwebsms-button-group">
		<?php add_thickbox(); ?>
        <a name="<?php _e( _greenweb_xd("\xe0\xd6\xa7\xf4\xa2\x84\xc8\xcd\xb9"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>"
           href="admin.php?page=greenweb-sms-subscribers-group#TB_inline?&width=400&height=125&inlineId=add-group"
           class="thickbox button"><span
                    class="dashicons dashicons-groups"></span> <?php _e( _greenweb_xd("\xe0\xd6\xa7\xf4\xa2\x84\xc8\xcd\xb9"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></a>
        <div id="add-group" style="display:none;">
            <form action="" method="post">
                <table>
                    <tr>
                        <td style="padding-top: 10px;">
                            <label for="wp_group_name"
                                   class="wp_sms_subscribers_label"><?php _e( _greenweb_xd("\xef\xd3\xae\xb1"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></label>
                            <input type="text" id="wp_group_name" name="wp_group_name"
                                   class="wp_sms_subscribers_input_text" required/>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2" style="padding-top: 20px;">
                            <input type="submit" class="button-primary" name="wp_add_group"
                                   value="<?php _e( _greenweb_xd("\xe0\xd6\xa7"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>"/>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
    </div>

    <form id="subscribers-filter" method="get">
          <?php $_request_page = sanitize_text_field($_REQUEST[_greenweb_xd("\xd1\xd3\xa4\xb1")]) ?>
            <input type="hidden" name="page" value="<?php echo esc_attr($_request_page); ?>"/>
            <?php $list_table->search_box(__(_greenweb_xd("\xf2\xd7\xa2\xa6\x86\x9e"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")), _greenweb_xd("\xd2\xd7\xa2\xa6\x86\x9e\xf8\xd1\xad")); ?>
            <?php $list_table->display(); ?>
    </form>
</div>