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
class Header extends Field
{
    /**
     * Diplay header
     *
     * @param array $field Field
     * @param array $data  Data
     *
     * @return string
     */
    public function getfield($field, $data)
    {
        $attributes = $field['@attributes'];
        $html = '';
        $html .= '<div class="control-group">';
        if (!empty($attributes['label'])) {
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Dynamic label
            $html .= '<h3>' . esc_html__($attributes['label'], 'wpfdAddon') . '</label>';
        }
        $html .= '</div>';

        return $html;
    }
}
