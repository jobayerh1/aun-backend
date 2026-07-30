<?php
namespace Joomunited\Cloud\WPFD;

defined('ABSPATH') || die('No direct script access allowed!');

/**
 * Google drive class
 */
class GoogleDrive extends CloudConnector
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
    private static $option_config = '_wpfdAddon_cloud_config';
    /**
     * Init old option configuration variable
     *
     * @var string
     */
    private static $old_option_config = '_wpfdAddon_cloud_config_old';
    /**
     * Init connect mode option variable
     *
     * @var string
     */
    private static $connect_mode_option = 'joom_cloudconnector_ggd_connect_mode';
    /**
     * Init google type configuration
     *
     * @var string
     */
    private static $google_type_option = 'joom_cloudconnector_google_type';
    /**
     * Init network variable
     *
     * @var string
     */
    private $network = 'google-drive';
    /**
     * Init id button variable
     *
     * @var string
     */
    private $id_button = 'ggdrive-connect';
    /**
     * Default redirect uri
     *
     * @var string
     */
    private static $default_redirect_uri = 'https://connector.joomunited.com/cloudconnector/google-drive/auth-callback';
    /**
     * Googledrive constructor.
     */
    public function __construct()
    {
        self::$params = parent::$instance;
        add_action('cloudconnector_display_ggd_settings', array($this,'displayGGDSettings'));
        add_action('wp_ajax_cloudconnector_ggd_changemode', array($this, 'ggdChangeMode'));
        add_action('wp_ajax_cloudconnector_google_type', array($this, 'googleTypes'));
        add_filter('cloudconnector_ggd_filter_connect_mode', array($this, 'filterConnectMode'));
    }

    /**
     * Get old cloud config function
     *
     * @return array
     */
    public static function getCloudConfigsOld()
    {
        return get_option(self::$old_option_config, array());
    }

    /**
     * Connect function
     *
     * @return mixed
     */
    public static function connect()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verification is made in before function
        $bundle = isset($_GET['bundle']) ? json_decode(self::urlsafeB64Decode($_GET['bundle']), true) : array();

        if (!$bundle || empty($bundle['client_id']) || empty($bundle['client_secret'])) {
            return false;
        }

        $googleType      = get_option(self::$google_type_option, 'google_drive');
        $googleTeamDrive = (isset($googleType) && !empty($googleType) && $googleType === 'google_team_drive') ? true : false;
        $google_select_drive_option = '_wpfdAddon_cloud_team_drive_select_drive';
        update_option($google_select_drive_option, false);

        if ($googleTeamDrive) {
            if (!class_exists('GoogleTeamDrive')) {
                include_once WPFDA_PLUGIN_DIR_PATH . '/cloud-connector/cloud/GoogleTeamDrive.php';
            }

            $googleTeamDriveConnector = new GoogleTeamDrive();
            $googleTeamDriveConnector::connect($bundle);
        } else {
            $option = get_option(self::$option_config);
            if (!$option) {
                $option = array(
                    'googleClientId'     => '',
                    'googleClientSecret' => '',
                    'googleSyncTime'     => '30',
                    'googleSyncMethod'   => 'sync_page_curl',
                    'googleConnectedBy'  => 1,
                    'googleBaseFolder'   => '',
                    'googleCredentials'  => '',
                    'googleRedirectUri'  => ''
                );
            }

            $option['googleClientId']     = $bundle['client_id'];
            $option['googleClientSecret'] = $bundle['client_secret'];
            $option['googleBaseFolder']   = self::getBasefolder($bundle);
            $option['googleCredentials']  = json_encode($bundle);
            $option['googleRedirectUri']  = isset($bundle['redirect_uri']) ? $bundle['redirect_uri'] : '';
            update_option(self::$option_config, $option);
            update_option(self::$old_option_config, $option);
        }
    }

    /**
     * Display connect mode checkbox
     *
     * @return void
     */
    public function displayGGDSettings()
    {
        // phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain -- It is string from object
        $connect_mode_list = array(
            'automatic' => esc_html__('Automatic', self::$params->text_domain),
            'manual' => esc_html__('Manual', self::$params->text_domain)
        );
        $ggd_config = get_option(self::$option_config);
        $config_mode = get_option(self::$connect_mode_option, 'manual');
        if ($config_mode && $config_mode === 'automatic') {
            echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'input[name="googleClientId"]\').parents(\'.ju-settings-option\').hide();
                        $(\'input[name="googleClientSecret"]\').parents(\'.ju-settings-option\').hide();
                        $(\'#gg_setup\').hide();
                        $(\'#gg_disconnect\').hide();
                        $(\'#ggconnect\').hide();
                        $(\'.ggd-ju-connect-message\').show();
                    });
                </script>';

            if (!$ggd_config || empty($ggd_config['googleCredentials'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-ggd\').addClass(\'ju-visibled\').show();
                        $(\'#wpfd-btn-automaticdisconnect-ggd\').removeClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            if ($ggd_config && !empty($ggd_config['googleCredentials'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-ggd\').removeClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-ggd\').addClass(\'ju-visibled\').show();
                    });
                </script>';
            }
        } else {
            if (!$ggd_config || empty($ggd_config['googleCredentials'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-ggd\').addClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-ggd\').removeClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            if ($ggd_config && !empty($ggd_config['googleCredentials'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-ggd\').removeClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-ggd\').addClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'.ggd-ju-connect-message\').hide();
                    });
                </script>';
        }

        if ($this->checkJoomunitedConnected()) {
            $juChecked = true;
            $message = '<p>'.esc_html__('The automatic connection mode to Google Drive uses a validated Google app, meaning that you just need a single login to connect your drive.', self::$params->text_domain).'</p>';
            $message .= '<p>'.esc_html__('On the other hand, the manual connection requires that you create your own app on the Google Developer Console.', self::$params->text_domain).'</p>';
        } else {
            $juChecked = false;
            $message = '<p>'.esc_html__('The automatic connection mode to Google Drive uses a validated Google app, meaning that you just need a single login to connect your drive.', self::$params->text_domain);
            $message .= '<strong>'.esc_html__(' However, please login first to your JoomUnited account to use this feature.', self::$params->text_domain).'</strong>';
            $message .= esc_html(' You can do that from', self::$params->text_domain).' <a href="'.esc_url(admin_url('options-general.php')).'"> the WordPress settings</a> '.esc_html__('using the same username and password as on the JoomUnited website.', self::$params->text_domain).'</p>';
            $message .= '<p>'.esc_html__('On the other hand, the manual connection requires that you create your own app on the Google Developer Console.', self::$params->text_domain).'</p>';
        }

        echo '<div id="ggd_connect_mode">';
        echo '<div class="ju-settings-option full-width">';
        echo '<label class="ju-setting-label" for="">'.esc_html__('Connecting mode', self::$params->text_domain).'</label>';
        echo '<div class="ggd-mode-radio-field automatic-radio-group">';
        echo '<div class="ju-radio-group">';
        foreach ($connect_mode_list as $k => $v) {
            $checked = (!empty($config_mode) && $config_mode === $k) ? 'checked' : '';
            echo '<label><input type="radio" class="ju-radiobox" name="googleConnectMethod" value="'.esc_html($k).'" '.esc_html($checked).'><span>'.esc_html($v).'</span></label>';
        }
        echo '</div>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- String is escaped
        echo '<div class="ggd-ju-connect-message">'.$message.'</div>';
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

        echo '<div id="wpfd-btn-automaticconnect-ggd" class="cloud-connector-title" title="'.esc_html($juChecked ? '' : __('Please login first to your JoomUnited account to use this feature', self::$params->text_domain)).'">';
        echo '<a class="ju-button orange-outline-button ggd-automatic-connect '.($juChecked ? '' : 'ju-disconnected-autoconnect').'" href="#"
                name="' . esc_html(self::$params->prefix . '_' . $id_button) . '" 
                id="' . esc_html(self::$params->prefix . '_' . $id_button) . '" 
                data-network="' . esc_html($network) . '" 
                data-link="' . esc_html(self::urlsafeB64Encode($link)) . '" >';
        echo '<img class="automatic-connect-icon" src="'.esc_url(WPFDA_PLUGIN_URL. 'app/admin/assets/images/drive-icon-colored.png').'" alt=""/>';
        echo '<label>' . esc_html__('Connect Google Drive', self::$params->text_domain).'</label></a>';
        echo '</div>';

        echo '<div id="wpfd-btn-automaticdisconnect-ggd" class="cloud-connector-title" title="'.esc_html($juChecked ? '' : __('Please login first to your JoomUnited account to use this feature', self::$params->text_domain)).'">';
        echo '<a class="ju-button ggd-automatic-disconnect '.($juChecked ? '' : 'ju-disconnected-autoconnect').'" href="admin.php?page=wpfdAddon-cloud&task=googledrive.logout" data-network="' . esc_html($network) . '">';
        echo '<img class="automatic-connect-icon" src="'.esc_url(WPFDA_PLUGIN_URL. 'app/admin/assets/images/drive-icon-colored.png').'" alt=""/>';
        echo esc_html__('Disconnect Google Drive', self::$params->text_domain).'</a>';
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
     * Get google drive connect mode
     *
     * @return mixed
     */
    public static function filterConnectMode()
    {
        $option = get_option(self::$option_config, false);
        $connect_mode = get_option(self::$connect_mode_option, false);

        if ($connect_mode === 'automatic' ||
            (isset($option['googleRedirectUri']) && $option['googleRedirectUri'] === self::$default_redirect_uri)) {
            return true;
        }
        return false;
    }

    /**
     * Change connect mode
     *
     * @return void
     */
    public static function ggdChangeMode()
    {
        check_ajax_referer('_cloudconnector_nonce', 'cloudconnect_nonce');

        if (isset($_POST['value'])) {
            update_option(self::$connect_mode_option, $_POST['value']);
        }
    }

    /**
     * Google type for connecting
     *
     * @return void
     */
    public static function googleTypes()
    {
        check_ajax_referer('_cloudconnector_nonce', 'cloudconnect_nonce');

        if (isset($_POST['google_type'])) {
            update_option(self::$google_type_option, $_POST['google_type']);
        }
    }

    /**
     * Get base folder id
     *
     * @param array $authenticate Author
     *
     * @return string
     */
    public static function getBasefolder($authenticate)
    {
        require_once WPFDA_PLUGIN_DIR_PATH  . 'lib/prod_vendor/autoload.php';
        $google_client = new \Google_Client();
        $google_client->setClientId($authenticate['client_id']);
        $google_client->setClientSecret($authenticate['client_secret']);
        $google_client->setAccessToken($authenticate);

        $data_old = self::getCloudConfigsOld();

        $check_root_folder = false;
        if (!empty($data_old['googleBaseFolder'])) {
            $check_root_folder = self::folderExists($google_client, $data_old['googleBaseFolder']);
        }

        if ($check_root_folder && !empty($data_old['googleClientId']) && $authenticate['client_id'] === $data_old['googleClientId']) {
            $googleBaseFolder = $data_old['googleBaseFolder'];
        } else {
            $googleBaseFolder = self::createFolder($google_client);
        }

        return $googleBaseFolder;
    }

    /**
     * Folder exists
     *
     * @param object $client Googledrive client
     * @param string $id     Folder id
     *
     * @return boolean
     */
    public static function folderExists($client, $id)
    {
        try {
            $service = new \Google_Service_Drive($client);
            $service->files->get($id);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create new folder google drive
     *
     * @param object $client Service
     *
     * @return mixed
     */
    public static function createFolder($client)
    {
        $title = self::$params->name . ' - ' . get_bloginfo('name') . ' - Automatic connect';
        $file = new \Google_Service_Drive_DriveFile();
        $file->setName($title);
        $file->setMimeType('application/vnd.google-apps.folder');

        try {
            $service = new \Google_Service_Drive($client);
            $fileId = $service->files->create($file, array('fields' => 'id, name'));

            return $fileId->id;
        } catch (\Exception $e) {
            throw new \Exception('Something went wrong when get google base folder id');
        }
    }
}
