<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Model;

/**
 * Class WpfdAddonModelConfig
 */
class WpfdAddonModelConfig extends Model
{
    /**
     * Save file params
     *
     * @param array $datas Config data
     *
     * @return mixed
     */
    public function saveConfig($datas)
    {
        return update_option('_wpfd_social_config', $datas);
    }

    /**
     * Get social config
     *
     * @return mixed
     */
    public function getSocialConfig()
    {
        return get_option('_wpfd_social_config', array('facebook_lang' => 'en_US', 'facebook_appid' => ''));
    }

    /**
     * Set watermark config
     *
     * @param array $data Set watermark config
     *
     * @return void
     */
    public function saveWatermarkConfig($data)
    {
        WpfdAddonWatermark::setConfig($data);
    }

    /**
     * Get watermark config
     *
     * @return array
     */
    public function getWatermarkConfig()
    {
        return WpfdAddonWatermark::getConfig();
    }

    /**
     * Save WooCommerce config
     *
     * @param array $data Data
     *
     * @return boolean
     */
    public function saveWoocommerceConfig($data)
    {
        return update_option('_wpfd_woocommerce_config', $data);
    }

    /**
     * Get WooCommerce config
     *
     * @return array
     */
    public function getWoocommerceConfig()
    {
        $defaultConfig = array(
            'woo_add_to_cart_background' => ''
        );
        $savedOptions = get_option('_wpfd_woocommerce_config', array());

        return array_merge($defaultConfig, $savedOptions);
    }
}
