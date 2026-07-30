<?php
/**
 * WP Framework
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\WPFramework\v1_0_6\Fields;

use Joomunited\WPFramework\v1_0_6\Field;
use \Joomunited\WPFramework\v1_0_6\Application;

defined('ABSPATH') || die();

/**
 * Class Checkbox
 */
class Checkbox extends Field
{
    /**
     * Get the field
     *
     * @param array $field Field attributes
     * @param array $datas Full datas
     *
     * @return string
     */
    public function getfield($field, $datas)
    {
        $attributes = $field['@attributes'];
        $html = '';
        if ($attributes['hidden'] !== 'true') {
            $html .= '<div class="control-group">';
            if (!empty($attributes['label']) && $attributes['label'] !== '' && !empty($attributes['name']) && $attributes['name'] !== '') {
                $html .= '<label class="control-label" for="' . esc_attr($attributes['name']) . '">' . __($attributes['label'], Factory::getApplication()->getName()) . '</label>';
            }
            $html .= '<div class="controls">';
        }
        $cleanfield = $field;
        unset($cleanfield['@attributes']);
        if (!empty($cleanfield[0])) {
            foreach ($cleanfield[0] as $child) {
                if (!empty($child['option']['@attributes'])) {
                    $html .= '<input type="checkbox" ';
                    foreach ($child['option']['@attributes'] as $childAttribute => $childValue) {
                        if (in_array($childAttribute, array('id', 'class', 'name', 'onchange', 'value')) && isset($childValue)) {
                            $html .= ' ' . $childAttribute . '="' . $childValue . '"';
                            if ($childAttribute === 'value' && isset($attributes['value']) && $attributes['value'] === $childValue) {
                                $html .= ' selected="selected"';
                            }
                        }
                    }
                    $html .= '>';
                    $html .= __($child['option'][0], strtolower(Application::getInstance()->getName()));
                    $html .= '<br/>';
                }
            }
        }

        $html .= '</select>';
        if (!empty($attributes['help']) && $attributes['help'] !== '') {
            $html .= '<p class="help-block">' . $attributes['help'] . '</p>';
        }
        if (!empty($attributes['type']) && $attributes['hidden'] !== 'true') {
            $html .= '</div></div>';
        }
        return $html;
    }
}
