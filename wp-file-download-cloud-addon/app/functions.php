<?php
/**
 * WP File Download Addon
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Model;
use Joomunited\WPFramework\v1_0_6\Application;

/**
 * Get social options
 *
 * @return mixed
 */
function wpfda_get_config()
{
    $config = get_option(
        '_wpfd_social_config',
        array('facebook_lang' => 'en_US', 'facebook_appid' => WPFDA_FACEBOOK_API)
    );

    return $config;
}

/**
 * Method get option
 *
 * @param string $key     Option name
 * @param array  $default Default option to return
 *
 * @return mixed
 */
function wpfda_get_option($key, $default)
{
    $config = wpfda_get_config();
    if (!isset($config[$key])) {
        return $default;
    } else {
        return $config[$key];
    }
}

/**
 * Some schedules task
 * - Renew subscriptions for cloud notifications
 * - Some other else
 *
 * @return void
 */
function wpfda_register_schedules()
{
    if (!function_exists('wpfd_install_job')) {
        return;
    }
    add_filter('cron_schedules', 'wpfd_get_renew_subscriptions_schedule');
    add_action('wpfda_renew_subscriptions_tasks', 'wpfda_renew_onedrive_business_subscriptions');
    wpfd_install_job('wpfda_renew_subscriptions_tasks', 'wpfd_renew_subscriptions');
}
/**
 * Get renew subscriptions schedule
 *
 * @param array $schedule Schedule list
 *
 * @return array
 */
function wpfd_get_renew_subscriptions_schedule($schedule)
{
    $interval = (defined('WPFDA_RENEW_SUBSCRIPTIONS_INTERVAL') && WPFDA_RENEW_SUBSCRIPTIONS_INTERVAL > 0) ? WPFDA_RENEW_SUBSCRIPTIONS_INTERVAL : 14400; // 4 hours
    /**
     * Filter to change renew subscriptions time, this will override WPFDA_RENEW_SUBSCRIPTIONS_INTERVAL constance
     * This value can not large than 3 days in seconds (648000)
     *
     * @param $interval Time in second
     */
    $interval = apply_filters('wpfd_renew_subscriptions_interval', $interval);

    if ($interval > 640000) {
        $interval = 600000;
    }

    $schedule['wpfd_renew_subscriptions'] = array(
        'interval' => $interval,
        'display'  => esc_html__('WPFD Addon Renew Subscriptions', 'wpfdAddon'),
    );
    return $schedule;
}

/**
 * Renew onedrive business subscriptions tasks
 *
 * @return boolean
 */
function wpfda_renew_onedrive_business_subscriptions()
{
    $onedriveBusiness = new WpfdAddonOneDriveBusiness();
    try {
        return $onedriveBusiness->checkSubscriptions();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Assign user categories
 *
 * @param mixed $id Folder Id
 *
 * @return void
 */
function assign_user_categories($id)
{
    $user_id = get_current_user_id();
    if ($user_id) {
        $user_categories = get_user_meta($user_id, 'wpfd_user_categories', true);
        if (is_array($user_categories)) {
            if (!in_array($id, $user_categories)) {
                $user_categories[] = $id;
            }
        } else {
            $user_categories = array($id);
        }
        update_user_meta($user_id, 'wpfd_user_categories', $user_categories);
    }
}

/**
 * Get new category position option
 *
 * @return string
 */
function get_new_category_position()
{
    Application::getInstance('Wpfd');
    $configModel = Model::getInstance('config');
    if (!$configModel) {
        return 'end';
    }

    $config = $configModel->getConfig();

    if (!isset($config['new_category_position'])) {
        return 'end';
    }

    return $config['new_category_position'];
}
