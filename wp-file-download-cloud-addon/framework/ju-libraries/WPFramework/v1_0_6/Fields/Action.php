<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\WPFramework\v1_0_6\Fields;

use Joomunited\WPFramework\v1_0_6\Field;

defined('ABSPATH') || die();

/**
 * Class Action
 */
class Action extends Field
{

    /**
     *  Render <input> tag
     *
     * @param array $field Fields
     * @param array $datas Full data
     *
     * @return string
     */
    public function getfield($field, $datas)
    {
        ob_start();
        do_action($field['@attributes']['name'], $datas);
        return ob_get_clean();
    }
}
