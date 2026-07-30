<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wpfd-flex">
    <!-- General -->
    <div id="wpfd_product_general">
        <div class="wpfd_product_files_wrapper" style="float: left;width: 100%">
            <input type="hidden" name="wpfd_product_files" value="" />
            <input type="hidden" name="wpfd_product_catids" value="" />
            <input type="hidden" name="wpfd_product_cat_cloud_types" value="" />
            <input type="hidden" name="wpfd_product_titles" value="" />
            <input type="hidden" name="wpfd_product_file_objects" value="" />
            <input type="hidden" id="wpfd_product_previous_title" name="wpfd_product_previous_title" value="" />
            <div class="wpfd-files-table" id="wpfd-files-table" style="flex: 1;"></div>
        </div>
        <div class="product_general_main">
            <div id="ju_settings_woo_create_method" class="ju-settings-option" style="display: none">
                <label class="ju-setting-label" for="product_type" title="<?php esc_html_e('Product type', 'wpfdAddon'); ?>"><?php esc_html_e('Product type', 'wpfdAddon'); ?></label>
                <select class="ju-select ju-input" name="product_type" id="product_type">
                    <option value="one_product"><?php esc_html_e('Create one WooCommerce product', 'wpfdAddon'); ?></option>
                    <option value="one_product_per_file"><?php esc_html_e('Create one product per file', 'wpfdAddon'); ?></option>
                </select>
            </div>
            <div class="ju-settings-option">
                <label class="ju-setting-label" for="product_title" title="<?php esc_html_e('Product title', 'wpfdAddon'); ?>"><?php esc_html_e('Product title *', 'wpfdAddon'); ?></label>
                <input type="text" name="product_title" id="product_title" class="ju-input"/>
            </div>
            <div class="ju-settings-option">
                <label class="ju-setting-label" for="product_sku" title="<?php esc_html_e('Product SKU', 'wpfdAddon'); ?>"><?php esc_html_e('Product SKU *', 'wpfdAddon'); ?></label>
                <input type="text" name="product_sku" id="product_sku" class="ju-input"/>
            </div>
            <div class="ju-settings-option">
                <label class="ju-setting-label" for="product_price" title="<?php esc_html_e('Product price', 'wpfdAddon'); ?>"><?php esc_html_e('Product price', 'wpfdAddon'); ?></label>
                <input type="number" name="product_price" id="product_price" class="ju-input"/>
            </div>
            <div class="ju-settings-option" style="width: 100%">
                <label class="ju-setting-label" for="product_category" title="<?php esc_html_e('Product Category', 'wpfdAddon'); ?>"><?php esc_html_e('Product Category', 'wpfdAddon'); ?></label>
                <?php
                wp_dropdown_categories(array(
                    'hide_if_empty' => false,
                    'hide_empty' => false,
                    'name' => 'product_category',
                    'taxonomy' => 'product_cat',
                    'id' => 'product_category',
                    'hierarchical' => true,
                    'class' => 'ju-input'
                ));
                ?>
            </div>
            <div id="wpfd_featured_image_wrapper" style="width: 100%">
                <div class="ju-settings-option">
                    <label for="product_featured_image" class="ju-setting-label" title="<?php esc_html_e('Main product image', 'wpfdAddon'); ?>"><?php esc_html_e('Main product image', 'wpfdAddon'); ?></label>
                    <select name="product_featured_image" id="product_featured_image" class="ju-input">
                        <option value=""><?php esc_html_e('-- Select --', 'wpfdAddon');?></option>
                    </select>
                </div>
                <div class="ju-settings-option">
                    <input id="product_other_as_gallery" type="checkbox" class="ju-input" />
                    <input id="product_gallery_images" name="product_gallery_images" type="hidden" value="" />
                    <label for="product_other_as_gallery" class="ju-setting-label" title="<?php esc_html_e('Use other images as product gallery', 'wpfdAddon'); ?>" style="vertical-align: baseline; line-height: 1;"><?php esc_html_e('Use other images as product gallery', 'wpfdAddon'); ?></label>
                </div>
            </div>
<!--            Other fields-->
            <div class="wpfd-woo-accordion wpfd-woo-accordion-hide" data-show-accordion="0">
                <h3 class="ju-accordion-title">
                    <?php esc_html_e('ADDITIONAL OPTIONS', 'wpfdAddon'); ?>
                    <span class="dashicons dashicons-arrow-down"></span>
                    <span class="dashicons dashicons-arrow-up"></span>
                </h3>
                <div class="ju-accordion-content" style="display: none;">
                    <div class="ju-settings-option">
                        <label class="ju-setting-label" for="product_download_limit" title="<?php esc_html_e('Leave blank for unlimited re-downloads', 'wpfdAddon'); ?>"><?php esc_html_e('Download limit', 'wpfdAddon'); ?></label>
                        <input type="number" name="product_download_limit" id="product_download_limit" class="ju-input" min="0" step="1" placeholder="<?php esc_html_e('Unlimited', 'wpfdAddon'); ?>"/>
                    </div>
                    <div class="ju-settings-option">
                        <label class="ju-setting-label" for="product_download_expiry" title="<?php esc_html_e('Enter the number of days before a download link expires, or leave blank', 'wpfdAddon'); ?>"><?php esc_html_e('Download expiry', 'wpfdAddon'); ?></label>
                        <input type="number" name="product_download_expiry" id="product_download_expiry" class="ju-input" min="0" step="1" placeholder="<?php esc_html_e('Never', 'wpfdAddon'); ?>"/>
                    </div>
                    <div class="ju-settings-option">
                        <label class="ju-setting-label" for="product_description" title="<?php esc_html_e('Fill in your product description, or leave blank', 'wpfdAddon'); ?>"><?php esc_html_e('Product description', 'wpfdAddon'); ?></label>
                        <textarea name="product_description" id="product_description" class="ju-input" placeholder="<?php esc_html_e('Product description...', 'wpfdAddon'); ?>" style="width: 100%"></textarea>
                    </div>
                </div>
            </div>
            <div id="ju_woo_messages" style="display: none">
                <p><?php esc_html_e('Required fields are marked with an asterisk (*). Please fill them in.', 'wpfdAddon'); ?></p>
            </div>
        </div>
    </div>
    <!-- .General -->
</div>