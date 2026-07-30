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
use Joomunited\WPFramework\v1_0_6\Factory;

defined('ABSPATH') || die();

/**
 * Class Password
 */
class Password extends Field
{
    /**
     * Render <input> tag
     *
     * @param array $field Fields
     * @param array $data  Data
     *
     * @return string
     */
    public function getfield($field, $data)
    {
        $attributes = $field['@attributes'];
        $html       = '<div class="ju-settings-option full-width">';
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Possibility to translate by our deployment script
        $tooltip    = isset($attributes['tooltip']) ? __($attributes['tooltip'], 'wpfd') : '';
        if (!empty($attributes['type']) || (!empty($attributes['hidden']) && $attributes['hidden'] !== 'true')) {
            if (!empty($attributes['label']) && $attributes['label'] !== '' &&
                !empty($attributes['name']) && $attributes['name'] !== '') {
                $html .= '<label title="' . $tooltip . '" class="ju-setting-label" for="' . $attributes['name'] . '">';
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Dynamic translate
                $html .= esc_html__($attributes['label'], 'wpfd') . '</label>';
            }
        }

        if (empty($attributes['hidden']) || (!empty($attributes['hidden']) && $attributes['hidden'] !== 'true')) {
            $html .= '<input';
            $html .= ' type="password"';
        } else {
            $html .= '<hidden';
        }

        if (!empty($attributes)) {
            $attribute_array = array('id', 'class', 'placeholder', 'name', 'value', 'placeholder');
            foreach ($attributes as $attribute => $value) {
                if (in_array($attribute, $attribute_array) && isset($value)) {
                    $html .= ' ' . $attribute . '="' . $value . '"';
                }
            }
        }
        $html .= ' />';

        if (!empty($attributes['help']) && $attributes['help'] !== '') {
            $html .= '<div class="ju-settings-help">';
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Possibility to translate by our deployment script
            $html .= __($attributes['help'], 'wpfd');
            $html .= '</div>';
        }
        if (isset($attributes['error_message'])) {
            $html .= '<div class="ju-settings-error-message"></div>';
        }
        $html .= '</div>';

        return $html;
    }
}
