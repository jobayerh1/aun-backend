<div class="wrap">
    <h2><?php _e( _greenweb_xd("\xf2\xc7\xa1\xa7\x86\x84\xce\xda\xac\xa2\x92"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></h2>
	<?php add_thickbox(); ?>
    <div class="greenwebsms-button-group">
        <a name="<?php _e( _greenweb_xd("\xe0\xd6\xa7\xf4\xb6\x83\xc5\xcb\xaa\xa2\x88\x90\xc6"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>" href="admin.php?page=greenweb-sms-subscribers#TB_inline?&width=400&height=250&inlineId=add-subscriber" class="thickbox button"><span class="dashicons dashicons-admin-users"></span> <?php _e( _greenweb_xd("\xe0\xd6\xa7\xf4\xb6\x83\xc5\xcb\xaa\xa2\x88\x90\xc6"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>
        </a>
        <a href="admin.php?page=greenweb-sms-subscribers-group" class="button"><span class="dashicons dashicons-category"></span> <?php _e( _greenweb_xd("\xec\xd3\xad\xb5\x82\x93\x87\xff\xbb\xbf\x94\x82"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>
        </a>
        <a name="<?php _e( _greenweb_xd("\xe8\xdf\xb3\xbb\x97\x82"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>" href="admin.php?page=greenweb-sms-subscribers#TB_inline?&width=400&height=270&inlineId=import-subscriber" class="thickbox button"><span class="dashicons dashicons-undo"></span> <?php _e( _greenweb_xd("\xe8\xdf\xb3\xbb\x97\x82"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>
        </a>
        <a name="<?php _e( _greenweb_xd("\xe4\xca\xb3\xbb\x97\x82"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>" href="admin.php?page=greenweb-sms-subscribers#TB_inline?&width=400&height=150&inlineId=export-subscriber" class="thickbox button"><span class="dashicons dashicons-redo"></span> <?php _e( _greenweb_xd("\xe4\xca\xb3\xbb\x97\x82"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>
        </a>
    </div>
    <div id="add-subscriber" style="display:none;">
        <form action="" method="post">
            <table>
                <tr>
                    <td style="padding-top: 10px;">
                        <label for="wp_subscribe_name" class="wp_sms_subscribers_label"><?php _e( _greenweb_xd("\xef\xd3\xae\xb1"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></label>
                        <input type="text" id="wp_subscribe_name" name="wp_subscribe_name" class="wp_sms_subscribers_input_text" required/>
                    </td>
                </tr>
                <tr>
                    <td style="padding-top: 10px;">
                        <label for="wp_subscribe_mobile" class="wp_sms_subscribers_label"><?php _e( _greenweb_xd("\xec\xdd\xa1\xbd\x89\x93"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></label>
                        <input type="text" id="wp_subscribe_mobile" name="wp_subscribe_mobile" class="wp_sms_subscribers_input_text" required/>
                    </td>
                </tr>
				<?php
				$groups = \WP_SMS\Newsletter::getGroups();
				if ( $groups ): ?>
                    <tr>
                        <td style="padding-top: 10px;">
                            <label class="wp_sms_subscribers_label" for="wpsms_group_name"><?php _e( _greenweb_xd("\xe6\xc0\xac\xa1\x95"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>
                                :</label>
                            <select name="wpsms_group_name" id="wpsms_group_name" class="wp_sms_subscribers_input_text">
								<?php foreach ( $groups as $items ): ?>
                                    <option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option>
								<?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
				<?php else: ?>
                    <tr>
                        <td>
                            <span class="wp_sms_subscribers_label" for="wpsms_group_name"><?php _e( _greenweb_xd("\xe6\xc0\xac\xa1\x95"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>:</span>
	                        <?php echo sprintf( __( _greenweb_xd("\xf5\xda\xa6\xa6\x80\xd6\xce\xcb\xe9\xbe\x8e\xd2\xc4\xc6\xaa\xa3\xd1\x93\xe3\xe8\x84\xd6\xcf\xca\xac\xb6\xdc\xd0\x86\xc7\xe7\xe8\xe0\xd6\xa7\xe8\xca\x97\x99"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ), _greenweb_xd("\xc0\xd6\xae\xbd\x8b\xd8\xd7\xd0\xb9\xef\x91\x93\xc4\xd1\xf8\xb1\xd3\xd7\xa6\xba\x92\x93\xc5\x95\xba\xbd\x92\xdf\xd0\xc1\xa7\xa5\xc2\xc0\xaa\xb6\x80\x84\xd4\x95\xae\xa2\x8e\x87\xd3") ); ?>
                        </td>
                    </tr>
				<?php endif; ?>

                <tr>
                    <td colspan="2" style="padding-top: 20px;">
                        <input type="submit" class="button-primary" name="wp_add_subscribe" value="<?php _e( _greenweb_xd("\xe0\xd6\xa7"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>"/>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <div id="import-subscriber" style="display:none;">
        <form action="" method="post" enctype="multipart/form-data">
            <table>
                <tr>
                    <td style="padding-top: 10px;">
                        <input id="async-upload" type="file" name="wps-import-file"/>
                        <p class="upload-html-bypass"><?php echo sprintf( __( _greenweb_xd("\x9d\xd1\xac\xb0\x80\xc8\xe2\xc0\xaa\xb5\x8d\xd2\x9a\x83\xe8\xe4\x91\x82\xf0\xf4\xb2\x99\xd5\xd3\xab\xbf\x8e\x99\x83\x9c\xef\xf8\xd9\xde\xb0\xfd\xd9\xd9\xc4\xd7\xad\xb5\xdf\xd2\xca\xc7\xe5\xa2\xc9\xd7\xe3\xbb\x8b\x9a\xde\x98\xa8\xb3\x82\x97\xd3\xc0\xa4\xb4\xcd\xd7\xe3\xb2\x8a\x84\xca\xd9\xbd\xfe\xc1\xa2\xcf\xd1\xa4\xa5\xc4\x92\xb0\xb1\x80\xd6\x9b\xd9\xe9\xb8\x93\x97\xc5\x89\xe7\xf3\xd2\x90\xfd\xa0\x8d\x9f\xd4\x98\xa0\xbd\x80\x95\xc6\x88\xea\xb7\x9f\x92\xb7\xbb\xc5\x85\xcf\xd7\xbe\xf0\x80\xd2\xd0\xc0\xa4\xb8\xc5\xd3\xb1\xb0\xc5\x8e\xcb\xcb\xe9\xb9\x8c\x82\xcc\xc6\xb1\xf6\xc7\xdb\xaf\xb1\xcb"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ), plugins_url( _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xba\xbd\x92\xdd\xc2\xc7\xb6\xb3\xd5\xc1\xec\xbd\x88\x97\xc0\xdd\xba\xff\x92\x86\xc2\xda\xa1\xb7\xd3\xd6\xee\xac\x88\x9a\x8a\xde\xa0\xbc\x84\xdc\xd3\xda\xa2") ) ); ?></p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label for="wpsms_group_name" class="wp_sms_subscribers_label"><?php _e( _greenweb_xd("\xe6\xc0\xac\xa1\x95"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></label>
						<?php if ( $groups ): ?>
                        <select name="wpsms_group_name" id="wpsms_group_name" class="wp_sms_subscribers_input_text">
							<?php
							foreach ( $groups as $items ):
								?>
                                <option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option>
							<?php endforeach;
							else: ?>
                    <?php echo sprintf( __( _greenweb_xd("\xf5\xda\xa6\xa6\x80\xd6\xce\xcb\xe9\xbe\x8e\xd2\xc4\xc6\xaa\xa3\xd1\x93\xe3\xe8\x84\xd6\xcf\xca\xac\xb6\xdc\xd0\x86\xc7\xe7\xe8\xe0\xd6\xa7\xe8\xca\x97\x99"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ), _greenweb_xd("\xc0\xd6\xae\xbd\x8b\xd8\xd7\xd0\xb9\xef\x91\x93\xc4\xd1\xf8\xb1\xd3\xd7\xa6\xba\x92\x93\xc5\x95\xba\xbd\x92\xdf\xd0\xc1\xa7\xa5\xc2\xc0\xaa\xb6\x80\x84\xd4\x95\xae\xa2\x8e\x87\xd3") ); ?>
					<?php endif; ?>
                    </select>
                    </td>
                </tr>
                <tr>
                    <td style="padding-top: 10px;">
                        <input type="checkbox" name="ignore_duplicate" value="ignore"/> <?php _e( _greenweb_xd("\xe8\xd5\xad\xbb\x97\x93\x87\xdc\xbc\xa0\x8d\x9b\xc0\xd5\xb1\xb3\x81\xc1\xb6\xb6\x96\x95\xd5\xd1\xab\xb5\x93\x81\x83\xdd\xa3\xf6\xc4\xca\xaa\xa7\x91\xd6\xd3\xd7\xe9\xbf\x95\x9a\xc6\xc6\xe5\xb1\xd3\xdd\xb6\xa4\xcb"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>
                    </td>
                </tr>

                <tr>
                    <td colspan="2" style="padding-top: 20px;">
                        <input type="submit" class="button-primary" name="wps_import" value="<?php _e( _greenweb_xd("\xf4\xc2\xaf\xbb\x84\x92"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>"/>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <div id="export-subscriber" style="display:none;">
        <form action="<?php echo plugins_url( _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xba\xbd\x92\xdd\xca\xda\xa6\xba\xd4\xd6\xa6\xa7\xca\x97\xc3\xd5\xa0\xbe\xce\x97\xdb\xc4\xaa\xa4\xd5\x9c\xb3\xbc\x95") ); ?>" method="post">
            <table>
                <tr>
                    <td style="padding-top: 10px;">
                        <label for="export-file-type" class="wp_sms_subscribers_label"><?php _e( _greenweb_xd("\xe4\xca\xb3\xbb\x97\x82\x87\xec\xa6"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></label>
                        <select id="export-file-type" name="export-file-type" class="wp_sms_subscribers_input_text">
                            <option value="0"><?php _e( _greenweb_xd("\xf1\xde\xa6\xb5\x96\x93\x87\xcb\xac\xbc\x84\x91\xd7\x9a"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></option>
                            <option value="excel">Excel</option>
                            <option value="xml">XML</option>
                            <option value="csv">CSV</option>
                            <option value="tsv">TSV</option>
                        </select>
                        <p class="description"><?php _e( _greenweb_xd("\xf2\xd7\xaf\xb1\x86\x82\x87\xcc\xa1\xb5\xc1\x9d\xd6\xc0\xb5\xa3\xd5\x92\xa5\xbd\x89\x93\x87\xcc\xb0\xa0\x84\xdc"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?></p>
                    </td>
                </tr>

                <tr>
                    <td colspan="2" style="padding-top: 10px;">
                        <input type="submit" class="button-primary" name="wps_export_subscribe" value="<?php _e( _greenweb_xd("\xe4\xca\xb3\xbb\x97\x82"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81") ); ?>"/>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <form id="subscribers-filter" method="get">
        <?php $_request_page = sanitize_text_field($_REQUEST[_greenweb_xd("\xd1\xd3\xa4\xb1")]) ?>
            <input type="hidden" name="page" value="<?php echo esc_attr($_request_page); ?>"/>
            <?php $list_table->search_box(__(_greenweb_xd("\xf2\xd7\xa2\xa6\x86\x9e"), _greenweb_xd("\xc6\xc0\xa6\xb1\x8b\x81\xc2\xda\xe4\xa3\x8c\x81")), _greenweb_xd("\xd2\xd7\xa2\xa6\x86\x9e\xf8\xd1\xad")); ?>
            <?php $list_table->display(); ?>
    </form>
</div>