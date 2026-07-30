<?php
/**
 * WP File Download Addon
 *
 * @package WP File Download Addon
 * @author  Joomunited
 * @version 1.0
 */
defined('ABSPATH') || die();

/**
 * Class WpfdAAssetsManager
 */
class WpfdAAssetsManager
{

    /**
     * Requested
     *
     * @var array
     */
    private static $requested = array();

    /**
     * Css Options
     *
     * @var array
     */
    private static $cssOptionsToPrint = array();

    /**
     * Init assets manager
     *
     * @return void
     */
    public static function init()
    {
        self::handlePasscode();
        self::userTracker();
    }

    /**
     * Cookie pass code
     *
     * @var null
     */
    private static $cookiesPassCode = null;

    /**
     * Cookie set pass code
     *
     * @var boolean
     */
    private static $passcodeCookieSet = false;

    /**
     * Auto unlock key
     *
     * @var boolean
     */
    private static $autoUnlock = false;

    /**
     * Auto unlock
     *
     * @param string $itemId Item id
     *
     * @return mixed
     */
    public static function autoUnlock($itemId)
    {
        if (isset(self::$autoUnlock[$itemId])) {
            return self::$autoUnlock[$itemId];
        }
        self::$autoUnlock[$itemId] = self::isAutoUnlock();
        return self::$autoUnlock[$itemId];
    }

    /**
     * Check auto unlock
     *
     * @return boolean
     */
    public static function isAutoUnlock()
    {
        return self::handlePasscode();
    }

    /**
     * Handle passcode
     *
     * @return boolean
     */
    public static function handlePasscode()
    {
        $passcode = get_option('wpfda_passcode', false);
        if (empty($passcode)) {
            return false;
        }
        $permanentPasscode = get_option('wpfda_permanent_passcode', false);
        if ($permanentPasscode) {
            if (filter_input(INPUT_GET, $passcode, FILTER_SANITIZE_ENCODED)) {
                if (empty(self::$cookiesPassCode)) {
                    self::$cookiesPassCode = 'opanda_' . wp_create_nonce('passcode');
                }
                if (isset($_COOKIE[self::$cookiesPassCode]) || self::$passcodeCookieSet) {
                    return true;
                }
                if (!headers_sent()) {
                    setcookie(self::$cookiesPassCode, 1, time() + 60 * 60 * 24 * 5000, '/');
                    self::$passcodeCookieSet = true;
                }
                return true;
            }
        } elseif (filter_input(INPUT_GET, $passcode, FILTER_SANITIZE_ENCODED)) {
            return true;
        }
        return false;
    }

    /**
     * Items types to load assets.
     *
     * @var array
     */
    public static $connectedItems = array();

    /**
     * From body
     *
     * @var boolean
     */
    public static $fromBody = false;

    /**
     * From header
     *
     * @var boolean
     */
    public static $fromHeader = false;

    /**
     * Requests adding assets for a given item type on a current page.
     *
     * @param string  $itemId     Item id
     * @param boolean $fromBody   From body
     * @param boolean $fromHeader From header
     *
     * @return void
     */
    public static function requestAssets($itemId, $fromBody = false, $fromHeader = false)
    {
        if (self::autoUnlock($itemId)) {
            return;
        }
        self::$fromBody = $fromBody;
        self::$fromHeader = $fromHeader;
        $options = self::getLockerOptions();
        do_action('wpfda_request_assets_for_social-locker', $options);
    }

    /**
     * Requested Text Res
     *
     * @var array
     */
    private static $requestedTextRes = array();

    /**
     * Requests text resources to print.
     *
     * @param array $res Res
     *
     * @return void
     */
    public static function requestTextRes($res = array())
    {
        self::$requestedTextRes = array_merge(self::$requestedTextRes, $res);
    }

    /**
     * Requests loading assets for lockers.
     *
     * @return void
     */
    public static function requestLockerAssets()
    {
        if (isset(self::$requested['locker-assets'])) {
            return;
        }
        self::$requested['locker-assets'] = true;
        if (self::$fromBody || self::$fromHeader) {
            self::connectLockerAssets();
        } else {
            add_action('wp_enqueue_scripts', 'WpfdAAssetsManager::connectLockerAssets');
        }
        add_action('wp_footer', 'WpfdAAssetsManager::printLockerScriptVars', 1);
    }

    /**
     * Defined Visibility Vars
     *
     * @var array
     */
    private static $definedVisibilityVars = array();

    /**
     * Definces visibility vars.
     *
     * @param array $options Options
     *
     * @return void
     */
    public static function defineVisibilityVars($options)
    {
        if (empty($options['wpfda_visibility_filters'])) {
            return;
        }
        $visibility = json_decode($options['wpfda_visibility_filters'], true);
        $params = array();
        foreach ($visibility as $filter) {
            if (empty($filter['conditions'])) {
                continue;
            }
            foreach ($filter['conditions'] as $scope) {
                if (empty($scope['conditions'])) {
                    continue;
                }
                foreach ($scope['conditions'] as $condition) {
                    $params[] = $condition['param'];
                }
            }
        }
        foreach ($params as $param) {
            $value = apply_filters('wpfda_visibility_param_' . $param, null);
            $value = apply_filters('wpfda_visibility_param', $value, $param);

            self::$definedVisibilityVars[$param] = $value;
        }
    }

    /**
     * Connects scripts and styles
     *
     * @return void
     */
    public static function connectLockerAssets()
    {
        wp_enqueue_style(
            'wpfda-lockers',
            plugins_url('app/site/assets/css/lockers.010210.min.css', WPFDA_PLUGIN_FILE)
        );
        wp_enqueue_script(
            'wpfda-lockers',
            plugins_url('app/site/assets/js/lockers.010210.min.js', WPFDA_PLUGIN_FILE),
            array('jquery', 'jquery-effects-core', 'jquery-effects-highlight'),
            false,
            true
        );
        $facebookSDK = array(
            'appId' => get_option('facebook_appid'),
            'lang' => get_option('facebook_lang', 'en_US')
        );
        wp_localize_script('wpfda-lockers', 'facebookSDK', $facebookSDK);
    }

    /**
     * Prints variables required for the locker script.
     *
     * @return void
     */
    public static function printLockerScriptVars()
    {
        $resToPrint = array();
        foreach (self::$requestedTextRes as $res) {
            $value = get_option('wpfda_res_' . $res, false);
            if (false === $value) {
                continue;
            }
            $resToPrint[$res] = $value;
        }
        wp_localize_script('wpfda-lockers', '__pandalockers', array(
            'lang' => $resToPrint,
            'visibility' => self::$definedVisibilityVars,
            'managedInitHook' => get_option('wpfda_managed_hook', false)
        ));
    }

    /**
     * Requests loading Facebook SDK.
     *
     * @return void
     */
    public static function requestFacebookSDK()
    {
        if (isset(self::$requested['facebook-sdk'])) {
            return;
        }
        self::$requested['facebook-sdk'] = true;
        if (!self::$fromBody) {
            add_action('wp_head', 'WpfdAAssetsManager::connectFacebookSDK');
        } else {
            add_action('wp_footer', 'WpfdAAssetsManager::connectFacebookSDK', 1);
        }
    }

    /**
     * Connects scripts and styles of Opt-In .
     *
     * @return void
     */
    public static function connectFacebookSDK()
    {
        $fb_appId = get_option('wpfda_facebook_appid');
        $fb_version = get_option('wpfda_facebook_version', 'v2.0');
        $fb_lang = get_option('wpfda_facebook_lang', 'en_US');
        $url = ($fb_version === 'v1.0')
            ? '//connect.facebook.net/' . $fb_lang . '/all.js'
            : '//connect.facebook.net/' . $fb_lang . '/sdk.js?';
        ?>
        <script>
            window.fbAsyncInitPredefined = window.fbAsyncInit;
            window.fbAsyncInit = function () {
                window.FB.init({
                    appId: <?php echo esc_html($fb_appId); ?>,
                    status: true,
                    cookie: true,
                    xfbml: true,
                    version: '<?php echo esc_html($fb_version); ?>'
                });
                window.FB.init = function () {
                };
                window.fbAsyncInitPredefined && window.fbAsyncInitPredefined();
            };
            (function (d, s, id) {
                var js, fjs = d.getElementsByTagName(s)[0];
                if (d.getElementById(id)) return;
                js = d.createElement(s);
                js.id = id;
                js.src = "<?php echo esc_url($url); ?>";
                fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));
        </script>
        <?php
    }

    /**
     * Print css select option
     *
     * @return void
     */
    public static function printCssSelectorOptions()
    {
        ?>
        <script>
            if (!window.bizpanda) window.bizpanda = {};
            window.bizpanda.bulkCssSelectors = [];
            <?php foreach (self::$cssOptionsToPrint as $options) { ?>
            window.bizpanda.bulkCssSelectors.push({
                lockId: '<?php echo esc_html($options['locker-options-id']); ?>',
                selector: '<?php echo esc_html($options['css-selector']); ?>'
            });
            <?php } ?>
        </script>
        <style>
            <?php foreach (self::$cssOptionsToPrint as $options) { ?>
                <?php if ($options['overlap-mode'] === 'full') { ?>
                    <?php echo esc_html($options['css-selector']) ?>
            {
                display: none
            ;
            }
                <?php } ?>
            <?php } ?>
        </style>
        <!-- / -->
        <?php
        self::$cssOptionsToPrint = array();
    }

    // -----------------------------------------------
    // Working with locker options.
    // -----------------------------------------------

    /**
     * Locker options
     *
     * @var array
     */
    private static $lockerOptions = array();

    /**
     * Locker options to print
     *
     * @var array
     */
    private static $lockerOptionsToPrint = array();

    /**
     * Prints locker options.
     *
     * @return void
     */
    public static function printLockerOptions()
    {
        $data = array();
        foreach (self::$lockerOptionsToPrint as $name => $id) {
            if (self::autoUnlock($id)) {
                continue;
            }
            $lockData = self::getLockerDataToPrint($id);
            $data[$id] = array(
                'name' => $name,
                'options' => $lockData
            );
        }
        ?>
        <script>
            if (!window.bizpanda) window.bizpanda = {};
            if (!window.bizpanda.lockerOptions) window.bizpanda.lockerOptions = {};
            <?php foreach ($data as $item) { ?>
            window.bizpanda.lockerOptions['<?php echo esc_html($item['name']); ?>'] = <?php echo esc_html(json_encode($item['options'])); ?>;
            <?php } ?>
        </script>
        <?php foreach ($data as $id => $item) { ?>
            <?php do_action('wpfda_print_batch_locker_assets', $id, $item['options'], $item['name']); ?>
        <?php } ?>
        <?php
        self::$lockerOptionsToPrint = array();
    }

    /**
     * Returns base options for all Items.
     *
     * @return array
     */
    public static function getBaseOptions()
    {
        $hasScope = get_option('wpfda_interrelation', false);
        $params = array(
            'demo' => self::getLockerOption('always', false, false),
            'actualUrls' => get_option('wpfda_actual_urls', false),
            'text' => array(
                'header' => self::getLockerOption('header'),
                'message' => self::getLockerOption('message')
            ),
            'theme' => self::getLockerOption('style'),
            'lang' => get_option('wpfda_lang', 'en_US'),
            'overlap' => array(
                'mode' => self::getLockerOption('overlap', false, 'full'),
                'position' => self::getLockerOption('overlap_position', false, 'middle'),
                'altMode' => get_option('wpfda_alt_overlap_mode', 'transparence')
            ),
            'highlight' => self::getLockerOption('highlight'),
            'googleAnalytics' => get_option('wpfda_google_analytics', 1),
            'locker' => array(
                'scope' => $hasScope ? 'global' : '',
                'counter' => self::getLockerOption('show_counters', false, 1),
                'loadingTimeout' => get_option('wpfda_timeout', 20000),
                'tumbler' => get_option('wpfda_tumbler', false),
                'naMode' => get_option('wpfda_na_mode', 'show-error')
            )
        );
        if ('blurring' === $params['overlap']['mode']) {
            $options['overlap']['mode'] = 'transparence';
        }
        $params['proxy'] = false;
        // - Replaces shortcodes in the locker message
        global $post;
        $postTitle = $post !== null ? $post->post_title : '';
        $postUrl = $post !== null ? get_permalink($post->ID) : '';
        if (!empty($params['text']['message'])) {
            $params['text']['message'] = str_replace('[post_title]', $postTitle, $params['text']['message']);
            $params['text']['message'] = str_replace('[post_url]', $postUrl, $params['text']['message']);
        }
        return $params;
    }

    /**
     * Returns data to print.
     *
     * @param string $id       Id
     * @param array  $lockData Lock data
     *
     * @return array
     */
    public static function getLockerDataToPrint($id, $lockData = array())
    {
        global $post;
        $lockData['lockerId'] = $id;
        // Options for tracking
        $lockData['tracking'] = get_option('wpfda_tracking', true);
        $lockData['postId'] = !empty($post) ? $post->ID : false;
        $lockData['ajaxUrl'] = admin_url('admin-ajax.php');
        // The panda item option
        $baseOptions = self::getBaseOptions();
        $options = apply_filters('wpfda_social-locker_item_options', $baseOptions, $id);
        $options = apply_filters('wpfda_item_options', $options, $id);
        // Normilize options
        self::normilizeLockerOptions($options);
        if (!isset($options['text']['header'])) {
            $options['text']['header'] = '';
        }
        if (!isset($options['text']['message'])) {
            $options['text']['message'] = '';
        }
        $lockData['options'] = $options;
        $lockData['_theme'] = self::getLockerOption('style');
        $lockData['_style'] = self::getLockerOption('style_profile');
        return $lockData;
    }

    /**
     * Returns locker options.
     *
     * @return array
     */
    public static function getLockerOptions()
    {
        $real = array(
            'factory_shortcodes_assets' => 'a:0:{}',
            'wpfda_item'                => 'social-locker',
            'wpfda_header'              => esc_html__('Support us :) Share to download', 'wpfdAddon'),
            'wpfda_message'             => esc_html__('Share this page on one social network and get access to the download.', 'wpfdAddon'),
            'wpfda_style'               => 'secrets',
            'wpfda_mobile'              => '1',
            'wpfda_highlight'           => '1',
            'wpfda_is_system'           => '1',
            'wpfda_is_default'          => '1',
            '_edit_lock'                => '1477112378:1',
            'wpfda_imperessions'        => '41',
        );
        return $real;
    }

    /**
     * Returns a locker option.
     *
     * @param string  $name    Name
     * @param boolean $isArray Is array?
     * @param mixed   $default Default value
     *
     * @return mixed|null|string
     */
    public static function getLockerOption($name, $isArray = false, $default = null)
    {
        $options = self::getLockerOptions();
        $value   = isset($options['wpfda_' . $name]) ? $options['wpfda_' . $name] : null;
        return ($value === null || $value === '')
            ? $default
            : ($isArray ? maybe_unserialize($value) : stripslashes($value));
    }

    /**
     * Notmilized locker options.
     *
     * @param array $params Params
     *
     * @return void
     */
    private static function normilizeLockerOptions(&$params)
    {
        foreach ($params as $key => &$item) {
            if ($item === '' || $item === null || $item === 0) {
                unset($params[$key]);
                continue;
            }
            if ($item === 'true') {
                $params[$key] = true;
                continue;
            }
            if ($item === '1') {
                $params[$key] = 1;
                continue;
            }
            if ($item === 'false') {
                $params[$key] = false;
                continue;
            }
            if ($item === '0') {
                $params[$key] = 0;
                continue;
            }
            if (gettype($item) === 'array') {
                self::normilizeLockerOptions($params[$key]);
            }
        }
    }

    /**
     * Finds closing and opening shortcodes and html elements without pairs.
     *
     * @param string     $content      Content
     * @param boolean    $closing      Closing
     * @param null|array $allowedNames Allowed names
     *
     * @return array
     */
    public static function findMarkupElements($content, $closing = false, $allowedNames = null)
    {
        $result = array(array(), array());
        $regex = array();
        $regex[] = '(\[(\/)?([^\[\]]*)\])';
        $regex[] = '(<(\/)?\s*([a-z0-9\-\_]+[^<>]*)>)';
        if (!preg_match_all('/' . implode('|', $regex) . '/', $content, $matches, PREG_SET_ORDER)) {
            return $result;
        }
        $elements = array();
        $tags = array();
        $stack = array();
        foreach ($matches as $match) {
            $keyShift = empty($match[3]) ? 3 : 0;
            $attrs = explode(' ', trim($match[3 + $keyShift]));
            $name = trim($attrs[0]);
            $matchClosing = !empty($match[2 + $keyShift]);
            $tag = trim($match[1 + $keyShift]);
            if (!ctype_lower($name)) {
                continue;
            }
            if (in_array($name, array('img', 'intense_hover_box', 'Don'))) {
                continue;
            }
            if (strpos($name, 'locker-bulk-') > 0) {
                continue;
            }
            $lastStack = end($stack);
            if ($lastStack['name'] === $name && $lastStack['closing'] !== $matchClosing) {
                array_pop($stack);
            } else {
                array_push($stack, array('name' => $name, 'closing' => $matchClosing, 'tag' => $tag));
            }
        }
        foreach ($stack as $element) {
            if ($closing !== $element['closing']) {
                continue;
            }
            $elements[] = $element;
            $tags[] = $element['tag'];
        }
        if (!empty($allowedNames)) {
            $filteredElements = array();
            $filteredTags = array();
            $totalElms = count($elements);
            for ($i = 0; $i < $totalElms; $i++) {
                if (!in_array($elements[$i]['name'], $allowedNames)) {
                    continue;
                }
                $filteredElements[] = $elements[$i];
                $filteredTags[] = $elements[$i]['tag'];
            }
            return array($filteredElements, $filteredTags);
        }
        return array($elements, $tags);
    }

    /**
     * User Tacker
     *
     * @return void
     */
    public static function userTracker()
    {
        add_action('wp_footer', 'WpfdAAssetsManager::printUserTrackerScript', 1);
    }

    /**
     * Print user tracker
     *
     * @return void
     */
    public static function printUserTrackerScript()
    {
        ?>
        <script>
            window.__bp_session_timeout = '<?php echo esc_html(wpfda_get_option('session_duration', 900)); ?>';
            window.__bp_session_freezing = <?php echo esc_html(wpfda_get_option('session_freezing', 0)); ?>;
            !function () {
                window.bizpanda || (window.bizpanda = {}), window.bizpanda.bp_can_store_localy = function () {
                    return !1
                }, window.bizpanda.bp_ut_get_cookie = function (e) {
                    for (var n = e + "=", i = document.cookie.split(";"), o = 0; o < i.length; o++) {
                        for (var t = i[o]; " " === t.charAt(0);) t = t.substring(1);
                        if (0 === t.indexOf(n)) return decodeURIComponent(t.substring(n.length, t.length))
                    }
                    return !1
                }, window.bizpanda.bp_ut_set_cookie = function (e, n, i) {
                    var o = new Date;
                    o.setTime(o.getTime() + 24 * i * 60 * 60 * 1e3);
                    var t = "expires=" + o.toUTCString();
                    document.cookie = e + "=" + encodeURIComponent(n) + "; " + t + "; path=/"
                }, window.bizpanda.bp_ut_get_obj = function (e) {
                    var n = null;
                    return (n = window.bizpanda.bp_can_store_localy() ? window.localStorage.getItem("bp_ut_session") : window.bizpanda.bp_ut_get_cookie("bp_ut_session")) ? (n = n.replace(/\-c\-/g, ","), n = n.replace(/\-q\-/g, '"'), n = JSON.parse(n), n.started + 1e3 * e < (new Date).getTime() && (n = null), n) : !1
                }, window.bizpanda.bp_ut_set_obj = function (e, n) {
                    e.started && window.__bp_session_freezing || (e.started = (new Date).getTime());
                    var e = JSON.stringify(e);
                    e && (e = e.replace(/\"/g, "-q-"), e = e.replace(/\,/g, "-c-")), window.bizpanda.bp_can_store_localy() ? window.localStorage.setItem("bp_ut_session", e) : window.bizpanda.bp_ut_set_cookie("bp_ut_session", e, 5e3)
                }, window.bizpanda.bp_ut_count_pageview = function () {
                    var e = window.bizpanda.bp_ut_get_obj(window.__bp_session_timeout);
                    e || (e = {}), e.pageviews ||
                    (e.pageviews = 0), 0 === e.pageviews &&
                    (e.referrer = document.referrer, e.landingPage = window.location.href, e.pageviews = 0), e.pageviews++, window.bizpanda.bp_ut_set_obj(e)
                }, window.bizpanda.bp_ut_count_locker_pageview = function () {
                    var e = window.bizpanda.bp_ut_get_obj(window.__bp_timeout);
                    e || (e = {}), e.lockerPageviews ||
                    (e.lockerPageviews = 0), e.lockerPageviews++, window.bizpanda.bp_ut_set_obj(e)
                }, window.bizpanda.bp_ut_count_pageview()
            }();
        </script>
        <?php
    }
}

if (!is_admin()) {
    if (!function_exists('wpfda_get_option')) {
        include plugin_dir_path(__FILE__) . '../../functions.php';
    }
    $facebook_locker = ((int) wpfda_get_option('facebook_locker', 1) === 1) ? true : false;
    $twitter_locker  = ((int) wpfda_get_option('twitter_locker', 1) === 1) ? true : false;
    if ($facebook_locker || $twitter_locker) {
        add_action('template_redirect', 'WpfdAAssetsManager::init');
    }
}
