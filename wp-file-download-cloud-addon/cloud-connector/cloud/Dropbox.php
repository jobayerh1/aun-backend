<?php
namespace Joomunited\Cloud\WPFD;

defined('ABSPATH') || die('No direct script access allowed!');

/**
 * Dropbox connector class
 */
class Dropbox extends CloudConnector
{
    /**
     * Init params variable
     *
     * @var array
     */
    private static $params = null;
    /**
     * Init option configuration variable
     *
     * @var string
     */
    private static $option_config = '_wpfdAddon_dropbox_config';
    /**
     * Init connect mode option variable
     *
     * @var string
     */
    private static $connect_mode_option = 'joom_cloudconnector_dropbox_connect_mode';
    /**
     * Init network variable
     *
     * @var string
     */
    private $network = 'dropbox';
    /**
     * Init id button variable
     *
     * @var string
     */
    private $id_button = 'dropbox-connect';

    /**
     * Dropbox constructor.
     */
    public function __construct()
    {
        self::$params = parent::$instance;
        add_action('cloudconnector_display_dropbox_settings', array($this,'displayDropboxSettings'));
        add_action('wp_ajax_cloudconnector_dropbox_changemode', array($this, 'dropboxChangeMode'));
    }

    /**
     * Connect function
     *
     * @return mixed
     */
    public static function connect()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verification is made in before function
        $bundle = isset($_GET['bundle']) ? json_decode(self::urlsafeB64Decode($_GET['bundle'])) : array();

        if (empty($bundle->app_key) || empty($bundle->app_secret)) {
            return false;
        }
        $option = get_option(self::$option_config);
        if (!$option) {
            $option = array(
                'dropboxKey' => '',
                'dropboxSecret' => '',
                'dropboxAccessToken' => '',
                'dropboxSyncMethod' => 'sync_page_curl',
                'dropboxSyncTime' => '30',
                'last_log' => date('Y-m-d H:i:s'),
                'dropboxBaseFolderId' => '',
                'dropboxConnectedBy' => get_current_user_id()
            );
        }

        $option['dropboxKey'] = $bundle->app_key;
        $option['dropboxSecret'] = $bundle->app_secret;
        $option['dropboxConnectedBy'] = get_current_user_id();
        $option['dropboxAccessToken'] = !empty($bundle->accessToken) ? $bundle->accessToken : '';
        $option['last_log'] = date('Y-m-d H:i:s');

        update_option(self::$option_config, $option);
        // phpcs:enable
    }

    /**
     * Display connect mode checkbox
     *
     * @return void
     */
    public function displayDropboxSettings()
    {
        // phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain -- It is string from object
        $connect_mode_list = array(
            'automatic' => esc_html__('Automatic', self::$params->text_domain),
            'manual' => esc_html__('Manual', self::$params->text_domain)
        );
        $dropbox_config = get_option(self::$option_config);
        $config_mode = get_option(self::$connect_mode_option, 'manual');

        if ($config_mode && $config_mode === 'automatic') {
            echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'input[name="dropboxKey"]\').parents(\'.ju-settings-option\').hide();
                        $(\'input[name="dropboxSecret"]\').parents(\'.ju-settings-option\').hide();
                        $(\'#drop_connect\').hide();
                        $(\'#drop_disconnect\').hide();
                        $(\'.dropbox-ju-connect-message\').show();
                    });
                </script>';

            if (!$dropbox_config || empty($dropbox_config['dropboxAccessToken'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-dropbox\').addClass(\'ju-visibled\').show();
                        $(\'#wpfd-btn-automaticdisconnect-dropbox\').removeClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            if ($dropbox_config && !empty($dropbox_config['dropboxAccessToken'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-dropbox\').removeClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-dropbox\').addClass(\'ju-visibled\').show();
                    });
                </script>';
            }
        } else {
            if (!$dropbox_config || empty($dropbox_config['dropboxAccessToken'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-dropbox\').addClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-dropbox\').removeClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            if ($dropbox_config && !empty($dropbox_config['dropboxAccessToken'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-dropbox\').removeClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-dropbox\').addClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'.dropbox-ju-connect-message\').hide();
                    });
                </script>';
        }

        if ($this->checkJoomunitedConnected()) {
            $juChecked = true;
            $message = '<p>'.esc_html__('The automatic connection mode to Dropbox uses a validated Dropbox app, meaning that you just need a single login to connect your drive.', self::$params->text_domain).'</p>';
            $message .= '<p>'.esc_html__('On the other hand, the manual connection requires that you create your own app on the Dropbox Developer Console.', self::$params->text_domain).'</p>';
        } else {
            $juChecked = false;
            $message = '<p>'.esc_html__('The automatic connection mode to Dropbox uses a validated Dropbox app, meaning that you just need a single login to connect your dropbox.', self::$params->text_domain);
            $message .= '<strong>'.esc_html__(' However, please login first to your JoomUnited account to use this feature.', self::$params->text_domain).'</strong>';
            $message .= esc_html(' You can do that from', self::$params->text_domain).' <a href="'.esc_url(admin_url('options-general.php')).'"> the WordPress settings</a> '.esc_html__('using the same username and password as on the JoomUnited website.', self::$params->text_domain).'</p>';
            $message .= '<p>'.esc_html__('On the other hand, the manual connection requires that you create your own app on the Dropbox Developer Console.', self::$params->text_domain).'</p>';
        }

        echo '<div id="dropbox_connect_mode">';
        echo '<div class="ju-settings-option full-width">';
        echo '<label class="ju-setting-label" for="">'.esc_html__('Connecting mode', self::$params->text_domain).'</label>';
        echo '<div class="dropbox-mode-radio-field automatic-radio-group">';
        echo '<div class="ju-radio-group">';
        foreach ($connect_mode_list as $k => $v) {
            $checked = (!empty($config_mode) && $config_mode === $k) ? 'checked' : '';
            echo '<label><input type="radio" class="ju-radiobox" name="dropboxConnectMethod" value="'.esc_html($k).'" '.esc_attr($checked).'><span>'.esc_html($v).'</span></label>';
        }
        echo '</div>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- String is escaped
        echo '<div class="dropbox-ju-connect-message">'.$message.'</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        $this->connectButton($this->network, $this->id_button, $juChecked);
    }

    /**
     * Display button connect
     *
     * @param string $network   Network type
     * @param string $id_button Id of button
     * @param string $juChecked Junited connect checked
     *
     * @return void
     */
    public function connectButton($network, $id_button, $juChecked)
    {
        $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $link = admin_url('admin-ajax.php') . '?cloudconnector=1&task=connect';
        $link .= '&network=' . esc_html($network);
        $link .= '&plugin_type=' . self::$params->prefix;
        $link .= '&current_backlink=' . self::urlsafeB64Encode($current_url);
        $link .= '&cloudconnect_nonce=' . hash('md5', '_cloudconnect_nonce');

        echo '<div id="wpfd-btn-automaticconnect-dropbox" class="cloud-connector-title" title="'.esc_html($juChecked ? '' : __('Please login first to your JoomUnited account to use this feature', self::$params->text_domain)).'">';
        echo '<a class="ju-button dropbox-automatic-connect '.($juChecked ? '' : 'ju-disconnected-autoconnect').'" href="#"
                name="' . esc_html(self::$params->prefix . '_' . $id_button) . '" 
                id="' . esc_html(self::$params->prefix . '_' . $id_button) . '" 
                data-network="' . esc_html($network) . '" 
                data-link="' . esc_html(self::urlsafeB64Encode($link)) . '" >';
        echo '<img class="automatic-connect-icon" src="'.esc_url(WPFDA_PLUGIN_URL. 'app/admin/assets/images/dropbox_icon_colored.png').'" alt=""/>';
        echo esc_html__('Connect Dropbox', self::$params->text_domain).'</a>';
        echo '</div>';

        echo '<div id="wpfd-btn-automaticdisconnect-dropbox" class="cloud-connector-title" title="'.esc_html($juChecked ? '' : __('Please login first to your JoomUnited account to use this feature', self::$params->text_domain)).'">';
        echo '<a class="ju-button dropbox-automatic-disconnect '.($juChecked ? '' : 'ju-disconnected-autoconnect').'" href="admin.php?page=wpfdAddon-dropbox&task=dropbox.logout" data-network="' . esc_html($network) . '">';
        echo '<img class="automatic-connect-icon" src="'.esc_url(WPFDA_PLUGIN_URL. 'app/admin/assets/images/dropbox_icon_colored.png').'" alt=""/>';
        echo esc_html__('Disconnect Dropbox', self::$params->text_domain).'</a>';
        echo '</div>';
        // phpcs:enable
    }

    /**
     * Set default connect mode when installing
     *
     * @return void
     */
    public static function setDefaultMode()
    {
        if (!get_option(self::$connect_mode_option)) {
            update_option(self::$connect_mode_option, 'automatic');
        }
    }

    /**
     * Change connect mode
     *
     * @return void
     */
    public static function dropboxChangeMode()
    {
        check_ajax_referer('_cloudconnector_nonce', 'cloudconnect_nonce');

        if (isset($_POST['value'])) {
            update_option(self::$connect_mode_option, $_POST['value']);
        }
    }
}
