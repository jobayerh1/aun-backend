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
 * Class Textarea2
 */
class Textarea2 extends Field
{

    /**
     * Render <input> tag
     *
     * @param array $field Field data
     * @param array $data  Data
     *
     * @return string
     */
    public function getfield($field, $data)
    {
        $attributes = $field['@attributes'];
        $html = '<div class="ju-settings-option full-width">';
        $html .= '<div class="wpfd_row_full">';
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Possibility to translate by our deployment script
        $tooltip = isset($attributes['tooltip']) ? __($attributes['tooltip'], 'wpfdAddon') : '';
        if (!empty($attributes['type']) || (!empty($attributes['hidden']) && $attributes['hidden'] !== 'true')) {
            if (!empty($attributes['label']) && $attributes['label'] !== '' &&
                !empty($attributes['name']) && $attributes['name'] !== '') {
                $html .= '<label title="' . $tooltip . '"  class="ju-setting-label" for="' . $attributes['name'] . '">';
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Dynamic label
                $html .= esc_html__($attributes['label'], 'wpfdAddon') . '</label>';
            }
        }

        $html .= '<textarea ';
        if (!empty($attributes)) {
            foreach ($attributes as $attribute => $value) {
                $attribute_array = array('id', 'class', 'placeholder', 'name', 'rows', 'cols');
                if (in_array($attribute, $attribute_array) && isset($value)) {
                    $html .= ' ' . $attribute . '="' . $value . '"';
                }
            }
        }
        $html .= ' >';
        if (!empty($attributes['value'])) {
            $html .= $attributes['value'];
        }
        $html .= ' </textarea>';
        if (!empty($attributes['help']) && $attributes['help'] !== '') {
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Possibility to translate by our deployment script
            $html .= '<div class="ju-settings-help">' . __($attributes['help'], 'wpfdAddon') . '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
}
