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
 * Class Input
 */
class Input extends Field
{

    /**
     * Sanitize input value
     *
     * @param mixed $value Value to sanitize
     *
     * @return mixed
     */
    public function sanitize($value)
    {
        return htmlspecialchars($value);
    }
}
