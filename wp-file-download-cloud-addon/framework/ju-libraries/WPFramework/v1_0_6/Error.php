<?php
/**
 * WP Framework
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\WPFramework\v1_0_6;

defined('ABSPATH') || die();

/**
 * Class Error
 */
class Error
{

    /**
     * Raise an error
     *
     * @param string $type    Error code
     * @param string $message Error message
     * @param string $title   Error title
     *
     * @return void
     */
    public static function raiseError($type = '404', $message = 'An error occurs', $title = 'Error')
    {
        wp_die(esc_html($message), esc_html($title), array('response' => esc_html($type)));
    }
}
