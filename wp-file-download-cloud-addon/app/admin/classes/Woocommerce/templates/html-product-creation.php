<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="wpfd_woocommerce_product_creation" style="position: absolute;top:0;left:0;right:0;bottom:0;display:none;">
    <div class="wpfd-modal-backdrop fade in" style="z-index: 10000;"></div>
    <div id="wpfd-modal-wrapper" class="wpfd-modal" style="z-index: 10001;">
        <div class="wpfd-modal-header">
            <ul class="wpfd-tabs">
                <li class="wpfd-tab ju-button ju-rect-button ju-link-button active" style="padding: 5px 0;" title="<?php esc_html_e('Create new product', 'wpfdAddon'); ?>" data-tab-id="wpfd-create-product"">
                    <?php esc_html_e('Create new product', 'wpfdAddon'); ?>
                </li>
            </ul>
        </div>
        <div class="wpfd-modal-body">
            <div class="wpfd-tabs-wrapper">
                <div class="wpfd-tabs-content">
                    <div class="wpfd-tab-content active" id="wpfd-create-product">
                        <?php wpfd_get_template('html-product-create.php'); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="wpfd-modal-footer">
            <button type="button" class="ju-button ju-rect-button ju-link-button js-modalCancel"><?php esc_html_e('Cancel', 'wpfdAddon') ?></button>
            <button type="button" class="ju-button ju-rect-button ju-v3-material js-saveProductOpen"><?php esc_html_e('Save', 'wpfdAddon'); ?></button>
        </div>
    </div>
</div>
