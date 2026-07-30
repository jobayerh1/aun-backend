<?php
if (! defined('ABSPATH')) {
    exit;
}
?>

<div class="control-group"><label title="<?php esc_html_e('The file access limitation can be managed by WooCommerce only (if the user have paid for example) or you can also apply file access limitation using WP File Download user roles management', 'wpfdAddon'); ?>" class="control-label" for="woo_permission"><?php esc_html_e('Woocommerce product permission', 'wpfdAddon'); ?></label>
    <div class="controls"><select name="woo_permission" id="woo_permission" class="ju-input">
            <option value="only_woo_permission" <?php echo (isset($value) && $value === 'only_woo_permission') ? 'selected="selected"' : '';?>><?php esc_html_e('Apply only WooCommerce permission', 'wpfdAddon'); ?></option>
            <option value="both_woo_and_wpfd_permission" <?php echo (isset($value) && $value === 'both_woo_and_wpfd_permission') ? 'selected="selected"' : '';?>><?php esc_html_e('Apply WooCommerce and WPFD permission', 'wpfdAddon'); ?></option>
        </select></div>
</div>
