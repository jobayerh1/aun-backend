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
 * Class Document
 */
class Document
{

    /**
     * Document class instance
     *
     * @var Document
     */
    protected static $instance;

    /**
     * Get Document instance
     *
     * @return Document
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new Document();
        }
        return self::$instance;
    }
}
