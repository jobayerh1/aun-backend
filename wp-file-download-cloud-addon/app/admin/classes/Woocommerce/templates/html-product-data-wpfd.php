<?php
if (!defined('ABSPATH')) {
    exit;
}
global $post, $thepostid, $product_object;

?>
<div id="wpfd_product_data" class="panel woocommerce_options_panel hidden">
    <div class="options_group show_if_downloadable hidden">
        <div class="form-field downloadable_files">
            <table class="widefat">
                <thead>
                <tr>
                    <th class="sort">&nbsp;</th>
                    <th><?php esc_html_e('Name', 'wpfdAddon'); ?><?php echo wc_help_tip(__('This is the name of the download shown to the customer.', 'wpfdAddon')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip strip html by default ?></th>
                    <th colspan="2"><?php esc_html_e('File Details', 'wpfdAddon'); ?><?php echo wc_help_tip(__('This is file information or file ID', 'wpfdAddon')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip strip html by default ?></th>
                    <th>&nbsp;</th>
                </tr>
                </thead>
                <tbody>
                <?php
                if ($product_object instanceof WC_Product) {
                    $wpfdFiles = $product_object->get_meta('_wpfd_wc_files');

                    if ($wpfdFiles) {
                        foreach ($wpfdFiles as $key => $file) {
                            include 'html-product-file-row.php';
                        }
                    }
                }
                ?>
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="5">
                        <a href="#wpfdWoocommerceModal" data-wpfd-woo-add class="button openWpfdWooModal"
                           data-row="
                            <?php
                            $details = '';
                            $file    = array(
                               'name'  => '',
                               'id'    => '',
                               'catid' => '',
                            );
                            ob_start();
                            require 'html-product-file-row.php';
                            echo esc_attr(ob_get_clean());
                            ?>
                            "><?php esc_html_e('Add File', 'wpfdAddon'); ?></a>
                    </th>
                </tr>
                </tfoot>
            </table>
        </div>
        <?php
            $_wpfd_download_limit = $product_object->get_meta('_wpfd_download_limit');
            $_wpfd_download_expiry = $product_object->get_meta('_wpfd_download_expiry');
            woocommerce_wp_text_input(
                array(
                    'id'                => '_wpfd_download_limit',
                    'value'             => -1 === $_wpfd_download_limit ? '' : $_wpfd_download_limit,
                    'label'             => __('Download limit', 'wpfdAddon'),
                    'placeholder'       => __('Unlimited', 'wpfdAddon'),
                    'description'       => __('Leave blank for unlimited re-downloads.', 'wpfdAddon'),
                    'type'              => 'number',
                    'custom_attributes' => array(
                        'step' => '1',
                        'min'  => '0',
                    ),
                )
            );

            woocommerce_wp_text_input(
                array(
                    'id'                => '_wpfd_download_expiry',
                    'value'             => -1 === $_wpfd_download_expiry ? '' : $_wpfd_download_expiry,
                    'label'             => __('Download expiry', 'wpfdAddon'),
                    'placeholder'       => __('Never', 'wpfdAddon'),
                    'description'       => __('Enter the number of days before a download link expires, or leave blank.', 'wpfdAddon'),
                    'type'              => 'number',
                    'custom_attributes' => array(
                        'step' => '1',
                        'min'  => '0',
                    ),
                )
            );

            do_action('wpfd_product_options_downloads');
            ?>
    </div>
    <?php do_action('wpfd_product_options_wpfd'); ?>
</div>
