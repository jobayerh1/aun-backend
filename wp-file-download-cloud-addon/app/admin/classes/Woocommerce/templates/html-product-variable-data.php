<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!$variation instanceof WC_Product_Variation) {
    return;
}
?>
<div class="form-row form-row-full show_if_variation_downloadable" style="display:none">
    <h3><?php esc_html_e('WP File Download', 'wpfdAddon') ?></h3>
</div>
<div id="wpfd_product_data_<?php esc_attr_e($variation->get_id()); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- It's OK ?>" class="form-row form-row-full show_if_variation_downloadable" style="display: none;">
    <div class="form-row form-row-full downloadable_files">
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

            $wpfdFiles = $variation->get_meta('_wpfd_wc_files');

            if ($wpfdFiles) {
                foreach ($wpfdFiles as $key => $file) {
                    include 'html-product-file-row.php';
                }
            }

            ?>
            </tbody>
            <tfoot>
            <tr>
                <th colspan="5">
                    <a href="#wpfdWoocommerceModal" data-variant-id="<?php esc_attr_e($variation->get_id()); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- It's OK ?>" data-wpfd-woo-add class="button openWpfdWooModal"
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
                            $content = ob_get_contents();
                            ob_end_clean();
                            echo esc_attr($content);
                            ?>
                            "><?php esc_html_e('Add File', 'wpfdAddon'); ?></a>
                </th>
            </tr>
            </tfoot>
        </table>
    </div>
</div>
<div class="show_if_variation_downloadable" style="display: none;">
    <?php
    $variable_wpfd_download_limit = $variation->get_meta('_wpfd_download_limit', true, 'edit');
    $variable_wpfd_download_expiry = $variation->get_meta('_wpfd_download_expiry', true, 'edit');

    woocommerce_wp_text_input(
        array(
            'id'                => 'variable_wpfd_download_limit' . $loop,
            'name'                => 'variable_wpfd_download_limit[' . $loop . ']',
            'value'             => -1 === $variable_wpfd_download_limit ? '' : $variable_wpfd_download_limit,
            'label'             => __('Download limit', 'wpfdAddon'),
            'placeholder'       => __('Unlimited', 'wpfdAddon'),
            'desc_tip' => true,
            'description'       => __('Leave blank for unlimited re-downloads.', 'wpfdAddon'),
            'type'              => 'number',
            'wrapper_class' => 'form-field form-row form-row-first',
            'custom_attributes' => array(
                'step' => '1',
                'min'  => '0',
            ),
        )
    );

    woocommerce_wp_text_input(
        array(
            'id'                => 'variable_wpfd_download_expiry' . $loop,
            'name'                => 'variable_wpfd_download_expiry[' . $loop . ']',
            'value'             => -1 === $variable_wpfd_download_expiry ? '' : $variable_wpfd_download_expiry,
            'label'             => __('Download expiry', 'wpfdAddon'),
            'placeholder'       => __('Never', 'wpfdAddon'),
            'desc_tip' => true,
            'description'       => __('Enter the number of days before a download link expires, or leave blank.', 'wpfdAddon'),
            'type'              => 'number',
            'wrapper_class' => 'form-field form-row form-row-last',
            'custom_attributes' => array(
                'step' => '1',
                'min'  => '0',
            ),
        )
    );

    do_action('wpfd_product_variant_options_downloads');
    ?>
</div>