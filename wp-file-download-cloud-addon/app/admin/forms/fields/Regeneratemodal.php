<?php
/**
 * WP Framework
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\WP_File_Download_Cloud_Addon\Admin\Fields;

use Joomunited\WPFramework\v1_0_6\Field;
use Joomunited\WPFramework\v1_0_6\Factory;

defined('ABSPATH') || die();

/**
 * Class Regeneratemodal
 */
class Regeneratemodal extends Field
{
    /**
     * Get field
     *
     * @param array $field Field array
     * @param array $data  Attribute data
     *
     * @return string
     */
    public function getfield($field, $data)
    {
        // Keep the style attribute cause the id will be change
        $html = '<div id="wpfd_wm_modal_container" style="position: absolute;top:0;left:0;right:0;bottom:0;display:none;">';
        $html .= '<div class="wpfd-modal-backdrop fade in"></div>';
        $html .= '<div id="wpfd-modal-wrapper" class="wpfd-modal">';
        $html .= '<div class="wpfd-modal-header"><h4>' . esc_html__('Regenerate Watermark', 'wpfdAddon') . '</h4></div>';
        $html .= '<div class="wpfd-modal-body"></div>';
        $html .= '<div class="wpfd-modal-footer"><button class="ju-button ju-rect-button ju-link-button js-modalCancel">' . esc_html__('Close', 'wpfdAddon') . '</button><button class="ju-button ju-rect-button ju-material-button js-regenerateButton">' . esc_html__('Regenerate', 'wpfdAddon') . '</button></div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
