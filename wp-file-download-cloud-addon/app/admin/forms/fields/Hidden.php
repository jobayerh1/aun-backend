<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\WP_File_Download_Cloud_Addon\Admin\Fields;

use Joomunited\WPFramework\v1_0_6\Fields\Text;

defined('ABSPATH') || die();
// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- nonce verified on $form->validate()
/**
 * Class Hidden
 */
class Hidden extends Text
{
    /**
     * Add field
     *
     * @param array $field Field data
     * @param array $data  Data
     *
     * @return string
     */
    public function getfield($field, $data)
    {
        $attributes = $field['@attributes'];
        $attributes['type'] = 'hidden';
        $html = '<input';
        if (!empty($attributes)) {
            foreach ($attributes as $attribute => $value) {
                $attribute_array = array('type', 'id', 'class', 'placeholder', 'name', 'value');
                if (in_array($attribute, $attribute_array) && isset($value)) {
                    $html .= ' ' . $attribute . "='" . $value . "'";
                }
            }
        }
        $html .= ' />';
        return $html;
    }

    /**
     * Sanitize
     *
     * @param array $field Field data
     *
     * @return string
     */
    public function sanitize($field)
    {
        $value = null;
        if (isset($_POST[$field['name']])) {
            $value = $_POST[$field['name']];
        } elseif (isset($_GET[$field['name']])) {
            $value = $_GET[$field['name']];
        }
        return stripslashes($value);
    }
}
