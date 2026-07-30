<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\WP_File_Download_Cloud_Addon\Admin\Fields;

use Joomunited\WPFramework\v1_0_6\Field;
use Joomunited\WPFramework\v1_0_6\Application;

defined('ABSPATH') || die();

/**
 * Class Header
 */
class Awsbucketname extends Field
{
    /**
     * Render <input> tag
     *
     * @param array $field Fields
     * @param array $data  Data
     *
     * @return string
     */
    public function getfield($field, $data)
    {
        $app = Application::getInstance('WpfdAddon');
        $path_wpfdhelper = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $path_wpfdhelper .= DIRECTORY_SEPARATOR . 'WpfdHelper.php';
        require_once $path_wpfdhelper;

        $path_wpfda_class = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'classes';
        require_once $path_wpfda_class . DIRECTORY_SEPARATOR . 'WpfdAddonAws.php';

        $config = \WpfdAddonHelper::getAllAwsConfigs();
        $html        = '';
        if (isset($config['awsConnected']) && (int) $config['awsConnected'] === 1) {
            $aws = new \WpfdAddonAws;
            $attributes = $field['@attributes'];
            $html       = '<div class="ju-settings-option ju-settings-aws-bucket full-width">';
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Possibility to translate by our deployment script
            $tooltip    = isset($attributes['tooltip']) ? __($attributes['tooltip'], 'wpfd') : '';
            if (!empty($attributes['type']) || (!empty($attributes['hidden']) && $attributes['hidden'] !== 'true')) {
                if (!empty($attributes['label']) && $attributes['label'] !== '' &&
                    !empty($attributes['name']) && $attributes['name'] !== '') {
                    $html .= '<label title="' . $tooltip . '" class="ju-setting-label" for="' . $attributes['name'] . '">';
                    // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Dynamic translate
                    $html .= esc_html__($attributes['label'], 'wpfd') . '</label>';
                }
            }

            $html .= '<input';
            $html .= ' type="hidden"';

            if (!empty($attributes)) {
                $attribute_array = array('id', 'class', 'name', 'value');
                foreach ($attributes as $attribute => $value) {
                    if (in_array($attribute, $attribute_array) && isset($value)) {
                        $html .= ' ' . $attribute . '="' . $value . '"';
                    }
                }
            }
            $html .= ' />';

            $html .= '<div class="buckets_wrap">
                <b class="current_bucket">'.$attributes['value'].'</b>
                <span class="current_bucket_region">' . esc_html($aws->regions[$config['awsRegion']]) . '</span>
                <div class="msg-no-bucket">
                    <label>'.esc_html__('No bucket found, please add a bucket to be able to use this feature', 'wpfdAddon').'</label>
                    <a class="ju-button orange-button wpfd-small-btn wpfd-aws3-manage-bucket" href="#manage-bucket"><span>'.esc_html__('Add bucket', 'wpfdAddon').'</span></a>
                </div>
                <a class="ju-button orange-button wpfd-small-btn wpfd-aws3-manage-bucket" href="#manage-bucket"><span>'.esc_html__('Bucket settings and selection', 'wpfdAddon').'</span></a>
                <a class="ju-button wpfd-small-btn aws3-view-console" href="https://console.aws.amazon.com/s3/buckets" target="_blank"><span>'.esc_html__('Amazon Console', 'wpfdAddon').'</span></a>
            </div>';
            $html .= '</div>';
        }

        return $html;
    }
}
