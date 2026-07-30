<?php
if (! defined('ABSPATH')) {
    exit;
}

?>
<tr>
    <td class="sort"></td>
    <td class="file_name">
        <input type="text" class="input_text" placeholder="<?php esc_attr_e('File name', 'wpfdAddon'); ?>" name="_wpfd_wc_files_name<?php echo isset($variation_id) ? '['.esc_attr($variation_id).']' : ''; ?>[]" value="<?php echo esc_attr($file['name']); ?>" />
        <input type="hidden" name="_wpfd_wc_files_id<?php echo isset($variation_id) ? '['.esc_attr($variation_id).']' : ''; ?>[]" value="<?php echo isset($file['id']) ? esc_attr($file['id']) : ''; ?>" />
        <input type="hidden" name="_wpfd_wc_files_catid<?php echo isset($variation_id) ? '['.esc_attr($variation_id).']' : ''; ?>[]" value="<?php echo isset($file['catid']) ? esc_attr($file['catid']) : ''; ?>" />
    </td>
    <td class="file_details">
       <ul style="list-style: none">
           <li class="file_id"><strong><?php esc_html_e('File Id', 'wpfdAddon'); ?></strong>: <span><?php echo esc_html($file['id']); ?></span></li>
           <li class="file_catid"><strong><?php esc_html_e('Category Id', 'wpfdAddon'); ?></strong>: <span><?php echo esc_html($file['catid']); ?></span></li>
       </ul>
    </td>
    <td class="file_url_choose" width="1%" style="display:none"><a href="#" class="button wpfd_upload_file_button" data-choose="<?php esc_html_e('Choose file', 'wpfdAddon'); ?>" data-update="<?php esc_attr_e('Insert other', 'wpfdAddon'); ?>"><?php echo esc_html__('Choose file', 'wpfdAddon'); ?></a></td>
    <td width="1%"><a href="#" class="delete"><?php esc_html_e('Delete', 'wpfdAddon'); ?></a></td>
</tr>
