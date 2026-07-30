<?php
/**
 * WP File Download Addon
 *
 * @package WP File Download Addon
 * @author  Joomunited
 * @version 1.0
 */

/**
 * Factory Shortcode Manager
 */
class WpfdAShortcodeManager
{
    /**
     * Shortcodes
     *
     * @var array
     */
    private $shortcodes = array();

    /**
     * A set of shortcodes that has assets connects (js and css).
     *
     * @var mixed[]
     */
    public $connected = array();

    /**
     * Manager
     *
     * @var mixed[]
     */
    public $manager;

    /**
     * This method allows to create a new shortcode object for each call.
     *
     * @param string $name      A shortcode name.
     * @param array  $arguments Arguments
     *
     * @return string
     */
    public function __call($name, $arguments)
    {
        list($prefix, $type) = explode('_', $name, 2);
        $blank = new $type();

        return $blank->render($arguments[0], $arguments[1]);
    }

    /**
     * Registers a new shortcode.
     *
     * @param string $className A short code class name.
     *
     * @return void
     */
    public function register($className)
    {
        $shortcode          = new $className();
        $shortcode->manager = $this;
        $this->shortcodes[] = $shortcode;
        if ($shortcode->shortcodeName !== '') {
            $className = get_class($shortcode);
            add_shortcode($shortcode->shortcodeName, array($this, 'wpfda_' . $className));
        }
    }
}
