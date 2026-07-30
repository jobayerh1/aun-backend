<?php
/**
 * WP File Download Addon
 *
 * @package WP File Download Addon
 * @author  Joomunited
 * @version 1.0
 */

/**
 * Class WpfdAFactoryShortcode
 */
abstract class WpfdAFactoryShortcode
{

    /**
     * Shortcode name.
     *
     * @var string
     */
    public $shortcodeName = 'wpfdasocial';

    /**
     * If true, the assets methods will be called in header.
     *
     * @var boolean
     */
    public $assetsInHeader = false;


    /**
     * Scripts to include on the same page.
     *
     * @var mixed
     */
    public $scripts;

    /**
     * Styles to include on the same page.
     *
     * @var mixed
     */
    public $styles;

    /**
     * If set true, this shortcode will be tracked on altering post content.
     *
     * @var boolean
     */
    public $track = false;

    /**
     * If true, it means that shortcode assets have been connected already.
     *
     * @var boolean
     */
    protected $connected = false;

    /**
     * Creates a new instance of a shortcode objects.
     */
    public function __construct()
    {
    }

    /**
     * Returns a shortcode html markup.
     *
     * @param array  $attr    Attributes
     * @param string $content Content
     *
     * @return string
     */
    public function render($attr, $content)
    {
        if (!$this->connected) {
            $this->assets(array($attr), true, false);
        }
        ob_start();
        $this->html($attr, $content);
        $html = ob_get_clean();

        return $html;
    }

    /**
     * Configures assets (js and css) for the shortcodes.
     *
     * The method should be overwritten in a deferred class.
     *
     * @return void
     */
    public function assets()
    {
    }


    /**
     * Renders shortcode html.
     *
     * @param array  $attr    Attributes
     * @param string $content Content
     *
     * @return void
     */
    abstract public function html($attr, $content);

    /**
     * Post save
     *
     * @param mixed $post Post
     *
     * @return void
     */
    public function onPostSave($post)
    {
    }

    /**
     * On track
     *
     * @param string  $shortcode    Shortcode
     * @param array   $attrs        Attributes
     * @param string  $innerContent Inner content
     * @param integer $postId       Post id
     *
     * @return void
     */
    public function onTrack($shortcode, $attrs, $innerContent, $postId)
    {
    }
}
