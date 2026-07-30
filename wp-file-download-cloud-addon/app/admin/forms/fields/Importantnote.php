<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\WP_File_Download_Cloud_Addon\Admin\Fields;

use Joomunited\WPFramework\v1_0_6\Field;

defined('ABSPATH') || die();

/**
 * Class Header
 */
class Importantnote extends Field
{
    /**
     * Diplay note
     *
     * @param array $field Field data
     * @param array $data  Data
     *
     * @return string
     */
    public function getfield($field, $data)
    {
        $attributes = $field['@attributes'];
        $html       = '<div class="ju-settings-option full-width wpfd-important-note">';
        if (!empty($attributes['note']) && $attributes['note'] !== '') {
            $html .= '<div class="ju-settings-important-note">';
            $html .= '<h3>'.esc_html__('Important note', 'wpfdAddon').'</h3>';
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Possibility to translate by our deployment script
            $html .= '<p>'.__($attributes['note'], 'wpfd').'</p>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}
