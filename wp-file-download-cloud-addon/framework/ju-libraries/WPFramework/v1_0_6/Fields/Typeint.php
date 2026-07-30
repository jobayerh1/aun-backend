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

defined('ABSPATH') || die();

/**
 * Class Typeint
 * phpcs:disable WordPress.Security.NonceVerification -- Nonce verification is made in the Form::validate method
 */
class Typeint extends Field
{

    /**
     * Validation regex
     *
     * @var string
     */
    protected $validate = '/[0-9]+/';

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
        $attributes = &$field['@attributes'];
        $attributes['value'] = (int)$attributes['value'];
        $attributes['type'] = 'text';
        return parent::getfield($field, $datas);
    }

    /**
     * Sanitize a value
     *
     * @param array $field Field attributes to sanitize
     *
     * @return integer
     */
    public function sanitize($field)
    {
        $value = null;
        if (!empty($_POST[$field['name']])) {
            $value = $_POST[$field['name']];
        } elseif (!empty($_GET[$field['name']])) {
            $value = $_GET[$field['name']];
        }
        return (int)$value;
    }
}
