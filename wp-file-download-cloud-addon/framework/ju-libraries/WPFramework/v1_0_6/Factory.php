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
 * Class Factory
 */
class Factory
{

    /**
     * Application reference
     *
     * @var Application
     */
    protected static $application;

    /**
     * Document reference
     *
     * @var Document
     */
    protected static $document;

    /**
     * Get application instance
     *
     * @return Application
     */
    public static function getApplication()
    {
        return Application::getInstance();
    }
}
