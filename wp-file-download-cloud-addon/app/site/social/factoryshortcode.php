<?php
/**
 * WP File Download Addon
 *
 * @package WP File Download Addon
 * @author  Joomunited
 * @version 1.0
 */

/**
 * A helper class to register new shortcodes.
 */
class WpfdAFactoryShortcodes
{
    /**
     * Manager
     *
     * @var boolean
     */
    private static $manager = false;

    /**
     * Registering a new shortcode.
     *
     * @param string $className Classname
     *
     * @return void
     */
    public static function register($className)
    {
        if (!self::$manager) {
            self::$manager = new WpfdAShortcodeManager();
        }
        self::$manager->register($className);
    }
}
