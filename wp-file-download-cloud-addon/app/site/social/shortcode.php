<?php
/**
 * WP File Download Addon
 *
 * @package WP File Download Addon
 * @author  Joomunited
 * @version 1.0
 */


// No direct access.
defined('ABSPATH') || die();

/**
 * Class WpfdALockerShortcode
 */
class WpfdALockerShortcode extends WpfdAFactoryShortcode
{
    /**
     * Manager
     *
     * @var mixed
     */
    public $manager;

    /**
     * Includes assets
     *
     * @var boolean
     */
    public $assetsInHeader = true;

    /**
     * Defines what assets need to include.
     * The method is called separate from the Render method during shortcode registration.
     *
     * @param array   $attrs    Attributes
     * @param boolean $fromBody From body
     * @param boolean $fromHook From hooks
     *
     * @return boolean
     */
    public function assets($attrs = array(), $fromBody = false, $fromHook = false)
    {
        if (is_admin()) {
            return false;
        }
        WpfdAAssetsManager::requestAssets(0, $fromBody, $fromHook);
    }

    /**
     * Content render
     *
     * @param array  $attr    Attribute
     * @param string $content Content
     *
     * @return void
     */
    public function html($attr, $content)
    {
        global $post;
        global $wp_embed;
        $id = 0;
        // runs nested shortcodes
        $content = $wp_embed->autoembed($content);
        $content = do_shortcode($content);
        // passcode
        if (WpfdAAssetsManager::autoUnlock($id)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Not modify user content for social locker
            echo $content;
            return;
        }
        // if returns:
        // 'content' - shows the locker content
        // 'nothing' - shows nothing (cut content)
        // 'locker' or other values - shows the locker
        $whatToShow = apply_filters('wpfda_what_to_show', 'locker', $id);
        if ('content' === $whatToShow) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Not modify user content for social locker
            echo $content;
            return;
        }
        if ('nothing' === $whatToShow) {
            return;
        }
        $content = preg_replace('/^<br \/>/', '', $content);
        $content = preg_replace('/<br \/>$/', '', $content);
        $lockData = WpfdAAssetsManager::getLockerDataToPrint($id);
        // use the shortcode attrs if specified instead of configured option
        if (isset($attr['url'])) {
            $lockData['options']['facebook']['like']['url']  = esc_url($attr['url']);
            $lockData['options']['facebook']['share']['url'] = esc_url($attr['url']);
            $lockData['options']['twitter']['tweet']['url']  = esc_url($attr['url']);
            $lockData['options']['google']['plus']['url']    = esc_url($attr['url']);
            $lockData['options']['google']['share']['url']   = esc_url($attr['url']);
            $lockData['options']['linkedin']['share']['url'] = esc_url($attr['url']);
        }
        if (isset($attr['title'])) {
            $lockData['options']['text']['title'] = esc_html($attr['title']);
        }
        if (isset($attr['message'])) {
            $lockData['options']['text']['message'] = $attr['message'];
        }
        if (isset($attr['theme'])) {
            $lockData['options']['theme'] = esc_html($attr['theme']);
        }
        $lockData['options']['lazy'] = get_option('lazy', false) ? true : false;
        $isAjax                      = false;
        $lockData['ajax']            = false;
        $dynamicTheme                = get_option('dynamic_theme', 0);
        $lockData['stats']           = get_option('tracking', false) ? true : false;
        $this->lockId                = 'onpLock' . rand(100000, 999999);
        $this->lockData              = $lockData;
        $overlap                     = $lockData['options']['overlap']['mode'];
        $contentVisibility           = get_option('content_visibility', 'auto');
        $hideContent                 = ($overlap === 'full') ? true : false;
        if ($contentVisibility === 'always_hidden') {
            $hideContent = true;
        } elseif ($contentVisibility === 'always_visible') {
            $hideContent = false;
        }
        if ($isAjax) { ?>
            <div class="onp-locker-call" style="display: none;" data-lock-id="<?php echo esc_attr($this->lockId); ?>"></div>
        <?php } else { ?>
            <div class="onp-locker-call" style="<?php echo esc_html(($hideContent) ? 'display: none;' : ''); ?>"
                 data-lock-id="<?php echo esc_attr($this->lockId) ?>">
                <p><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Not modify user content for social locker
                    echo $content
                ?></p>
            </div>
        <?php } ?>
        <?php
        if ($dynamicTheme) { ?>
            <script type="text/bp-data" class="onp-optinpanda-params">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Esc above
                echo json_encode($lockData);
                ?>


            </script>
        <?php } else { ?>
            <?php add_action('wp_footer', array($this, 'wpFooter'), 1); ?>
        <?php } ?>
        <?php
    }

    /**
     * Display footer
     *
     * @return void
     */
    public function wpFooter()
    {
        $dynamicTheme = get_option('dynamic_theme', false);
        if (!$dynamicTheme) {
            $this->printOptions();
        }
    }

    /**
     * Print options
     *
     * @return void
     */
    public function printOptions()
    {
        ?>
        <script>
            if (!window.bizpanda) window.bizpanda = {};
            if (!window.bizpanda.lockerOptions) window.bizpanda.lockerOptions = {};
            window.bizpanda.lockerOptions['<?php echo esc_attr($this->lockId); ?>'] = <?php echo json_encode($this->lockData); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Esc above ?>;
        </script>
        <?php
    }
}
