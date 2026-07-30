<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\WP_File_Download\Admin\Fields;

use Joomunited\WPFramework\v1_0_6\Field;
use Joomunited\WPFramework\v1_0_6\Factory;

defined('ABSPATH') || die();

/**
 * Class Heading
 */
class Heading extends Field
{

    /**
     * Render <input> tag
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
        if (!empty($attributes['type'])) {
            $html .= '<div class="control-group-heading">';
            if (!empty($attributes['label']) && $attributes['label'] !== '') {
                $html .= '<h3 class="control-heading">';
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Dynamic label
                $html .= esc_html__($attributes['label'], 'wpfdAddon') . '</h3>';
            }
            $html .= '</div>';
        }

        return $html;
    }
}
