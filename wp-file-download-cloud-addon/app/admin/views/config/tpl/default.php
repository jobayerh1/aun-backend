<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

// No direct access.
defined('ABSPATH') || die();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $msg is not print out
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$lang = array(
    'Facebook locker'        => esc_html__('Facebook locker', 'wpfdAddon'),
    'FB locker option'       => esc_html__('FB locker option', 'wpfdAddon'),
    'Facebook App ID'        => esc_html__('Facebook App ID', 'wpfdAddon'),
    'Force share Url'        => esc_html__('Force share Url', 'wpfdAddon'),
    'Facebook Language'      => esc_html__('Facebook Language', 'wpfdAddon'),
    'Twiter locker'          => esc_html__('Twiter locker', 'wpfdAddon'),
    'Twitter locker option'  => esc_html__('Twitter locker option', 'wpfdAddon'),
    'User to follow'         => esc_html__('User to follow', 'wpfdAddon'),
    'Tweet'                  => esc_html__('Tweet', 'wpfdAddon'),
    'Via'                    => esc_html__('Via', 'wpfdAddon'),
    'Session duration (sec)' => esc_html__('Session duration (sec)', 'wpfdAddon'),
)
?>
<?php if ($msg !== '') { ?>
    <div id="message" class="updated notice notice-success">
        <p><?php esc_html_e('Update success!', 'wpfdAddon'); ?></p>
    </div>
<?php } ?>
<div class="wrap wpfd-config">
    <div id="icon-options-general" class="icon32"></div>
    <h2><?php esc_html_e('Social Options', 'wpfdAddon'); ?></h2>
    <div id="dashboard-widgets-wrap">
        <div id="dashboard-widgets" class="metabox-holder columns-2">
            <div id="postbox-container" class="">
                <div class="metabox-holder">
                    <div id="dashboard_recent_drafts" class="postbox ">
                        <h3 class="hndle"><span><?php esc_html_e('Main parameters', 'wpfdAddon'); ?></span></h3>
                        <div class="inside">
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- print output from render() of framework
                            echo $this->configform;
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
