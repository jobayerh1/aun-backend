<?php
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Utilities;

include_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/aws.init.php';

$aws = new WpfdAddonAws();
$list_buckets = $aws->getListBucket();
$aws3config = $aws->config;
?>
<div id="manage-bucket" class="white-popup mfp-hide">
    <div class="table-list-buckets m-b-40">
        <h3><?php esc_html_e('Select an existing Bucket', 'wpfdAddon'); ?></h3>
        <table class="wpfd_width_100">
            <thead>
                <tr>
                    <th style="width: 30%"><?php esc_html_e('Bucket name', 'wpfdAddon') ?></th>
                    <th style="width: 30%"><?php esc_html_e('Date created', 'wpfdAddon') ?></th>
                    <th style="width: 30%"></th>
                    <th style="width: 10%"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($list_buckets)) {
                    foreach ($list_buckets as $bucket) {
                        ?>
                        <tr class="row_bucket <?php echo (isset($aws3config['awsBucketName']) && $aws3config['awsBucketName'] === $bucket['Name']) ? 'bucket-selected' : 'wpfd-aws3-select-bucket' ?>"
                            data-bucket="<?php echo esc_attr($bucket['Name']) ?>">
                            <td style="width: 30%"><?php echo esc_html($bucket['Name']) ?></td>
                            <td style="width: 30%"><?php echo esc_html($bucket['CreationDate']) ?></td>
                            <td style="width: 30%">
                                <?php if (isset($aws3config['awsBucketName']) && $aws3config['awsBucketName'] === $bucket['Name']) : ?>
                                    <label class="btn-select-bucket">
                                        <?php esc_html_e('Selected Bucket', 'wpfdAddon') ?>
                                    </label>
                                <?php else : ?>
                                    <label class="btn-select-bucket">
                                        <?php esc_html_e('Select Bucket', 'wpfdAddon') ?>
                                    </label>
                                <?php endif; ?>
                            </td>
                            <td style="width: 10%">
                                <a class="delete-bucket wpfdtippy"
                                data-wpfdtippy="<?php esc_html_e('Delete Bucket', 'wpfdAddon') ?>"
                                data-bucket="<?php echo esc_attr($bucket['Name']) ?>"><i class="material-icons">delete_outline</i></a>
                                <img src="<?php echo esc_url(plugins_url('app/admin/assets/images/spinner.gif', WPFDA_PLUGIN_FILE)) ?>"
                                class="spinner-delete-bucket">
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="wpfd-create-bucket-wrap">
        <div>
            <h3><?php esc_html_e('Create a new bucket', 'wpfdAddon') ?></h3>
            <div>
                <label>
                    <input type="text" class="wpfd_width_100 new-bucket-name"
                    placeholder="<?php esc_html_e('New bucket name', 'wpfdAddon') ?>">
                    <p class="ju-settings-error-message"></p>
                </label>
            </div>
        </div>

        <div>
            <h3><?php esc_html_e('Region', 'wpfdAddon') ?></h3>
            <div>
                <label>
                    <select class="new-bucket-region">
                        <?php
                        if (!empty($aws->regions)) :
                            foreach ($aws->regions as $k_regions => $v_region) : ?>
                                <option value="<?php echo esc_attr($k_regions); ?>"><?php echo esc_html($v_region); ?></option>
                            <?php endforeach;
                        endif;
                        ?>
                    </select>
                </label>
            </div>
        </div>

        <div class="wpfd_width_100 m-t-20 action-aws-btn">
            <button type="button"
            class="ju-button wpfd-small-btn cancel-bucket-btn"><?php esc_html_e('Cancel', 'wpfdAddon') ?></button>
            <button type="button"
            class="ju-button orange-button wpfd-small-btn create-bucket-btn"><?php esc_html_e('Create', 'wpfdAddon') ?></button>
            <span class="spinner create-bucket-spinner"></span>
        </div>
    </div>
</div>