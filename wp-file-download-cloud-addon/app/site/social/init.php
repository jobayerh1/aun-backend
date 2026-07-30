<?php
/**
 * WP File Download Addon
 *
 * @package WP File Download Addon
 * @author  Joomunited
 * @version 1.0
 */


/**
 * Social locker assets
 *
 * @param array $options Options
 *
 * @return void
 */
function wpfda_social_locker_assets($options)
{
    WpfdAAssetsManager::requestLockerAssets();
    // Miscellaneous
    WpfdAAssetsManager::requestTextRes(array(
        'misc_close',
        'misc_or_wait',
        'errors_not_signed_in',
        'errors_not_granted'
    ));

    if (isset($options['wpfda_buttons_order']) && strpos($options['wpfda_buttons_order'], 'facebook') !== false) {
        WpfdAAssetsManager::requestFacebookSDK();
    }
}

add_action('wpfda_request_assets_for_social-locker', 'wpfda_social_locker_assets', 10, 1);

/**
 * Adds options to print at the frontend.
 *
 * @param array   $options Options
 * @param integer $id      Id
 *
 * @return mixed
 */
function wpfda_social_locker_options($options, $id)
{
    global $post;
    $options['groups']        = array('social-buttons');
    $options['socialButtons'] = array();
    $buttonOrder              = array();
    if ((int) wpfda_get_option('facebook_locker', 1) === 1) {
        $buttonOrder[] = 'facebook-' . wpfda_get_option('facebook_locker_option', 'share');
    }
    if ((int) wpfda_get_option('twitter_locker', 1) === 1) {
        $buttonOrder[] = 'twitter-' . wpfda_get_option('twitter_locker_option', 'tweet');
    }
    if (!empty($buttonOrder)) {
        $buttonOrder = implode(',', $buttonOrder);
    }
    $actualUrls = get_option('actual_urls', false);
    $postUrl    = !empty($post) ? get_permalink($post->ID) : null;
    $postUrl    = $actualUrls ? null : $postUrl;
    $fbLikeUrl  = wpfda_get_option('facebook_like_url', $postUrl);
    $fbShareUrl = wpfda_get_option('facebook_share_url', $postUrl);
    $twTweetUrl = wpfda_get_option('twitter_tweet_url', $postUrl);
    wpfda_get_option('twitter_follow_url', $postUrl);
    wpfda_get_option('google_plus_url', $postUrl);
    wpfda_get_option('google_share_url', $postUrl);
    wpfda_get_option('linkedin_share_url', $postUrl);

    $options['socialButtons']['counters']          = wpfda_get_option('show_counters', 1);
    $options['socialButtons']['order']             = wpfda_get_option('buttons_order', $buttonOrder);
    $options['socialButtons']['facebook']          = array(
        'appId'   => wpfda_get_option('facebook_appid', WPFDA_FACEBOOK_API),
        'lang'    => wpfda_get_option('facebook_lang', 'en_GB'),
        'version' => wpfda_get_option('facebook_version', 'v15.0'),
        'like'    => array(
            'url'             => $fbLikeUrl,
            'title'           => esc_html__('Facebook', 'wpfdAddon'),
            'theConfirmIssue' => 0
        )
    );
    $options['socialButtons']['facebook']['share'] = array(
        'url'         => $fbShareUrl,
        'shareDialog' => 0,
        'counter'     => 0,
        'title'       => esc_html__('Facebook', 'wpfdAddon'),
        'name'        => null,
        'caption'     => null,
        'description' => null,
        'image'       => null
    );
    $options['socialButtons']['twitter']           = array(
        'lang'  => wpfda_get_option('short_lang', 'en'),
        'tweet' => array(
            'url'         => $twTweetUrl,
            'text'        => wpfda_get_option('twitter_tweet_text', null),
            'doubleCheck' => wpfda_get_option('twitter_tweet_auth', 0),
            'title'       => wpfda_get_option('twitter_tweet_title', 'tweet'),
            'via'         => wpfda_get_option('twitter_tweet_via', null)
        )
    );
    $options['socialButtons']['twitter']['follow'] = array(
        'url'            => wpfda_get_option('twitter_user_url', ''),
        'title'          => esc_html__('Twitter', 'wpfdAddon'),
        'hideScreenName' => false
    );
    if ('blurring' === $options['overlap']['mode']) {
        $options['overlap']['mode'] = 'transparence';
    }
    if (!in_array($options['theme'], array('starter', 'secrets'))) {
        $options['theme'] = 'secrets';
    }
    $allowedButtons = array(
        'facebook-like',
        'facebook-share',
        'twitter-tweet',
        'twitter-follow',
        'google-plus',
        'google-share',
        'youtube-subscribe',
        'linkedin-share'
    );
    $allowedButtons = apply_filters('wpfda_social-locker_allowed_buttons', $allowedButtons);
    if ($options['socialButtons']['order']) {
        $options['socialButtons']['order'] = explode(',', $options['socialButtons']['order']);
    }
    if (!empty($options['socialButtons']['order'])) {
        $filteredButtons = array();
        foreach ($options['socialButtons']['order'] as $buttonName) {
            if (!in_array($buttonName, $allowedButtons)) {
                continue;
            }
            $filteredButtons[] = $buttonName;
        }
        $options['socialButtons']['order'] = $filteredButtons;
    }
    // - Replaces shortcodes in the locker message and twitter text
    $postTitle = $post !== null ? $post->post_title : '';
    //$postUrl = $post != null ? get_permalink($post->ID) : '';
    if (!empty($options['socialButtons']['twitter']['tweet']['text'])) {
        $tweetText                                            = str_replace('[post_title]', $postTitle, $options['socialButtons']['twitter']['tweet']['text']);
        $options['socialButtons']['twitter']['tweet']['text'] = apply_filters(
            'wpfda_twitter_tweet_text',
            $tweetText,
            $id,
            $options
        );
    }

    return $options;
}

add_filter('wpfda_social-locker_item_options', 'wpfda_social_locker_options', 10, 2);
WpfdAFactoryShortcodes::register('WpfdALockerShortcode');
