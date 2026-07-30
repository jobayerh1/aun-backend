<?php
namespace Joomunited\Cloud\WPFD;

use Google\Exception;

defined('ABSPATH') || die('No direct script access allowed!');

/**
 * Google team drive class
 */
class GoogleTeamDrive extends CloudConnector
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
    private static $option_config = '_wpfdAddon_cloud_team_drive_config';

    /**
     * Init old option configuration variable
     *
     * @var string
     */
    private static $old_option_config = '_wpfdAddon_cloud_team_drive_config_old';

    /**
     * Init connect mode option variable
     *
     * @var string
     */
    private static $connect_mode_option = 'joom_cloudconnector_google_team_drive_connect_mode';

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
    private $id_button = 'google-team-drive-connect';

    /**
     * Default redirect uri
     *
     * @var string
     */
    private static $default_redirect_uri = 'https://connector.joomunited.com/cloudconnector/google-drive/auth-callback';

    /**
     * GoogleTeamDrive constructor.
     */
    public function __construct()
    {
        self::$params = parent::$instance;
        add_action('cloudconnector_display_google_team_drive_settings', array($this,'displayGoogleTeamDriveSettings'));
        add_action('wp_ajax_cloudconnector_google_team_drive_changemode', array($this, 'googleTeamDriveChangeMode'));
        add_filter('cloudconnector_google_team_drive_filter_connect_mode', array($this, 'filterConnectMode'));
    }

    /**
     * Get old cloud config function
     *
     * @return array
     */
    public static function getCloudTeamDriveConfigsOld()
    {
        return get_option(self::$old_option_config, array());
    }

    /**
     * Connect function
     *
     * @param array $bundle Bundle
     *
     * @throws \Exception Fire if errors
     *
     * @return mixed
     */
    public static function connect($bundle = array())
    {
        if (empty($bundle)) {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verification is made in before function
            $bundle = isset($_GET['bundle']) ? json_decode(self::urlsafeB64Decode($_GET['bundle']), true) : array();
        }

        if (!$bundle || empty($bundle['client_id']) || empty($bundle['client_secret'])) {
            return false;
        }

        $option = get_option(self::$option_config);
        if (!$option) {
            $option = array(
                'googleTeamDriveClientId'     => '',
                'googleTeamDriveClientSecret' => '',
                'googleTeamDriveSyncTime'     => '30',
                'googleTeamDriveSyncMethod'   => 'sync_page_curl',
                'googleTeamDriveConnectedBy'  => 1,
                'googleTeamDriveBaseFolder'   => '',
                'googleTeamDriveCredentials'  => '',
                'googleTeamDriveRedirectUri'  => ''
            );
        }

        $option['googleTeamDriveClientId']     = $bundle['client_id'];
        $option['googleTeamDriveClientSecret'] = $bundle['client_secret'];
        $option['googleTeamDriveCredentials']  = json_encode($bundle);
        $option['googleTeamDriveRedirectUri']  = isset($bundle['redirect_uri']) ? $bundle['redirect_uri'] : '';
        update_option(self::$option_config, $option);
        update_option(self::$old_option_config, $option);

        $drive = self::selectDriveForConnecting($bundle);
        if (is_array($drive) && !empty($drive)
            && isset($drive['googleTeamDriveBaseFolder']) && !empty($drive['googleTeamDriveBaseFolder'])) {
            $newOption = get_option(self::$option_config);
            $newOption['googleTeamDriveBaseFolder'] = $drive['googleTeamDriveBaseFolder'];

            update_option(self::$option_config, $newOption);
            update_option(self::$old_option_config, $newOption);

            // Close app
            self::closeGoogleApplication();
        } else {
            echo  esc_html__('Some thing went wrong. Ensure your Google account can access the Google Team Drive.', 'wpfdAddon');
        }
    }

    /**
     * Display connect mode checkbox
     *
     * @return void
     */
    public function displayGoogleTeamDriveSettings()
    {
        // phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain -- It is string from object
        $connect_mode_list = array(
            'automatic' => esc_html__('Automatic', self::$params->text_domain),
            'manual'    => esc_html__('Manual', self::$params->text_domain)
        );
        $google_team_drive_config = get_option(self::$option_config);
        $config_mode              = get_option(self::$connect_mode_option, 'manual');

        if ($config_mode && $config_mode === 'automatic') {
            echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'input[name="googleTeamDriveClientId"]\').parents(\'.ju-settings-option\').hide();
                        $(\'input[name="googleTeamDriveClientSecret"]\').parents(\'.ju-settings-option\').hide();
                        $(\'#ggtd_setup .links\').hide();
                        $(\'#ggtd_disconnect\').hide();
                        $(\'#ggtdconnect\').hide();
                        $(\'.ggtd-ju-connect-message\').show();
                    });
                </script>';

            if (!$google_team_drive_config || empty($google_team_drive_config['googleTeamDriveCredentials'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-ggtd\').addClass(\'ju-visibled\').show();
                        $(\'#wpfd-btn-automaticdisconnect-ggtd\').removeClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            if ($google_team_drive_config && !empty($google_team_drive_config['googleTeamDriveCredentials'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-ggtd\').removeClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-ggtd\').addClass(\'ju-visibled\').show();
                    });
                </script>';
            }
        } else {
            if (!$google_team_drive_config || empty($google_team_drive_config['googleTeamDriveCredentials'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-ggtd\').addClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-ggtd\').removeClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            if ($google_team_drive_config && !empty($google_team_drive_config['googleTeamDriveCredentials'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-ggtd\').removeClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-ggtd\').addClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'.ggtd-ju-connect-message\').hide();
                    });
                </script>';
        }

        if ($this->checkJoomunitedConnected()) {
            $juChecked = true;
            $message = '<p>'.esc_html__('The automatic connection mode to Google Team Drive uses a validated Google app, meaning that you just need a single login to connect your drive.', self::$params->text_domain).'</p>';
            $message .= '<p>'.esc_html__('On the other hand, the manual connection requires that you create your own app on the Google Developer Console.', self::$params->text_domain).'</p>';
        } else {
            $juChecked = false;
            $message = '<p>'.esc_html__('The automatic connection mode to Google Team Drive uses a validated Google app, meaning that you just need a single login to connect your drive.', self::$params->text_domain);
            $message .= '<strong>'.esc_html__(' However, please login first to your JoomUnited account to use this feature.', self::$params->text_domain).'</strong>';
            $message .= esc_html(' You can do that from', self::$params->text_domain).' <a href="'.esc_url(admin_url('options-general.php')).'"> the WordPress settings</a> '.esc_html__('using the same username and password as on the JoomUnited website.', self::$params->text_domain).'</p>';
            $message .= '<p>'.esc_html__('On the other hand, the manual connection requires that you create your own app on the Google Developer Console.', self::$params->text_domain).'</p>';
        }

        echo '<div id="google_team_drive_connect_mode">';
        echo '<div class="ju-settings-option full-width">';
        echo '<label class="ju-setting-label" for="">'.esc_html__('Connecting mode', self::$params->text_domain).'</label>';
        echo '<div class="google-team-drive-mode-radio-field automatic-radio-group">';
        echo '<div class="ju-radio-group">';
        foreach ($connect_mode_list as $k => $v) {
            $checked = (!empty($config_mode) && $config_mode === $k) ? 'checked' : '';
            echo '<label><input type="radio" class="ju-radiobox" name="googleTeamDriveConnectMethod" value="'.esc_html($k).'" '.esc_html($checked).'><span>'.esc_html($v).'</span></label>';
        }
        echo '</div>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- String is escaped
        echo '<div class="ggtd-ju-connect-message">'.$message.'</div>';
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

        echo '<div id="wpfd-btn-automaticconnect-ggtd" style="float: right; padding: 0 35px; margin: 0 0 -40px 0;" class="cloud-connector-title" title="'.esc_html($juChecked ? '' : __('Please login first to your JoomUnited account to use this feature', self::$params->text_domain)).'">';
        echo '<a class="ju-button ggtd-automatic-connect '.($juChecked ? '' : 'ju-disconnected-autoconnect').'" href="#"
                name="' . esc_html(self::$params->prefix . '_' . $id_button) . '" 
                id="' . esc_html(self::$params->prefix . '_' . $id_button) . '" 
                data-network="' . esc_html($network) . '" 
                data-link="' . esc_html(self::urlsafeB64Encode($link)) . '" >';
        echo '<img class="automatic-connect-icon" src="'.esc_url(WPFDA_PLUGIN_URL. 'app/admin/assets/images/drive-icon-colored.png').'" alt=""/>';
        echo esc_html__('Connect Google Team Drive', self::$params->text_domain).'</a>';
        echo '</div>';

        echo '<div id="wpfd-btn-automaticdisconnect-ggtd" style="float: right; padding: 0 35px; margin: 0 0 -40px 0;" class="cloud-connector-title" title="'.esc_html($juChecked ? '' : __('Please login first to your JoomUnited account to use this feature', self::$params->text_domain)).'">';
        echo '<a class="ju-button ggtd-automatic-disconnect '.($juChecked ? '' : 'ju-disconnected-autoconnect').'" href="admin.php?page=wpfdAddon-cloud&task=GoogleTeamDrive.logout" data-network="' . esc_html($network) . '">';
        echo '<img class="automatic-connect-icon" src="'.esc_url(WPFDA_PLUGIN_URL. 'app/admin/assets/images/drive-icon-colored.png').'" alt=""/>';
        echo esc_html__('Disconnect Google Team Drive', self::$params->text_domain).'</a>';
        echo '</div>';
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
     * Get google team drive connect mode
     *
     * @return mixed
     */
    public static function filterConnectMode()
    {
        $option = get_option(self::$option_config, false);
        $connect_mode = get_option(self::$connect_mode_option, false);

        if ($connect_mode === 'automatic' ||
            (isset($option['googleTeamDriveRedirectUri']) && $option['googleTeamDriveRedirectUri'] === self::$default_redirect_uri)) {
            return true;
        }
        return false;
    }

    /**
     * Change connect mode
     *
     * @return void
     */
    public static function googleTeamDriveChangeMode()
    {
        check_ajax_referer('_cloudconnector_nonce', 'cloudconnect_nonce');

        if (isset($_POST['value'])) {
            update_option(self::$connect_mode_option, $_POST['value']);
        }
    }

    /**
     * Get base folder id
     *
     * @param array $authenticate Author
     *
     * @throws \Exception Fire if errors
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

        $data_old          = self::getCloudTeamDriveConfigsOld();
        $check_root_folder = false;

        if (!empty($data_old['googleTeamDriveBaseFolder'])) {
            $check_root_folder = self::folderExists($google_client, $data_old['googleTeamDriveBaseFolder']);
        }

        if ($check_root_folder && !empty($data_old['googleTeamDriveClientId']) && $authenticate['client_id'] === $data_old['googleTeamDriveClientId']) {
            $googleTeamDriveBaseFolder = $data_old['googleTeamDriveBaseFolder'];
        } else {
            $googleTeamDriveBaseFolder = self::createFolder($google_client);
        }

        return $googleTeamDriveBaseFolder;
    }

    /**
     * Folder exists
     *
     * @param object $client GoogleTeamDrive client
     * @param string $id     Folder id
     *
     * @return boolean
     */
    public static function folderExists($client, $id)
    {
        try {
            $service = new \Google_Service_Drive($client);
            $service->files->get($id, array(
                'supportsTeamDrives' => true
            ));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create new folder google team drive
     *
     * @param object $client Service
     *
     * @throws \Exception Fire if errors
     *
     * @return mixed
     */
    public static function createFolder($client)
    {
        $title        = self::$params->name . ' - ' . get_bloginfo('name') . ' - Automatic connect';

        try {
            $service = new \Google_Service_Drive($client);

            // Try to create root google team drive drive on google business
            $ggTeamDriveFolder       = new \Google_Service_Drive_Drive();
            $ggTeamDriveFolder->name = $title;
            $baseFolder              = $service->drives->create(time(), $ggTeamDriveFolder);

            return $baseFolder->id;
        } catch (\Exception $e) {
            throw new \Exception('Something went wrong when getting google team drive base folder id');
        }
    }

    /**
     * Show google team drives
     *
     * @param object $authenticate Author
     *
     * @throws \Exception Fire if errors
     *
     * @return mixed
     */
    public static function showRootDrives($authenticate)
    {
        require_once WPFDA_PLUGIN_DIR_PATH  . 'lib/prod_vendor/autoload.php';
        $google_client = new \Google_Client();
        $google_client->setClientId($authenticate['client_id']);
        $google_client->setClientSecret($authenticate['client_secret']);
        $google_client->setAccessToken($authenticate);

        $data_old          = self::getCloudTeamDriveConfigsOld();
        $check_root_folder = false;

        if (!empty($data_old['googleTeamDriveBaseFolder'])) {
            $check_root_folder = self::folderExists($google_client, $data_old['googleTeamDriveBaseFolder']);
        }

        if ($check_root_folder && !empty($data_old['googleTeamDriveClientId']) && $authenticate['client_id'] === $data_old['googleTeamDriveClientId']) {
            $googleTeamDriveBaseFolder = $data_old['googleTeamDriveBaseFolder'];
        } else {
            $googleTeamDriveBaseFolder = self::createFolder($google_client);
        }

        return $googleTeamDriveBaseFolder;
    }

    /**
     * Select a google team drive for connecting
     *
     * @param array $authenticate Author
     *
     * @throws \Exception Fire if errors
     *
     * @return mixed
     */
    public static function selectDriveForConnecting($authenticate)
    {
        require_once WPFDA_PLUGIN_DIR_PATH  . 'lib/prod_vendor/autoload.php';

        if (!class_exists('WpfdAddonHelper')) {
            require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/helpers/WpfdHelper.php';
        }

        if (!class_exists('WpfdAddonGoogleTeamDrive')) {
            require_once WPFDA_PLUGIN_DIR_PATH . 'app/admin/classes/WpfdAddonGoogleTeamDrive.php';
        }

        $google_client = new \Google_Client();
        $google_client->setClientId($authenticate['client_id']);
        $google_client->setClientSecret($authenticate['client_secret']);
        $google_client->setAccessToken($authenticate);
        $google_select_drive_option = '_wpfdAddon_cloud_team_drive_select_drive';
        update_option($google_select_drive_option, false);

        $option = get_option(self::$option_config, false);

        if (!$option) {
            $option = array();
        }

        $data_old          = self::getCloudTeamDriveConfigsOld();
        $check_root_folder = false;
        $result            = array();
        $retrieve          = false;

        if (!empty($data_old['googleTeamDriveBaseFolder'])) {
            $check_root_folder = self::folderExists($google_client, $data_old['googleTeamDriveBaseFolder']);
        }

        if ($check_root_folder && !empty($data_old['googleTeamDriveClientId'])
            && $authenticate['client_id'] === $data_old['googleTeamDriveClientId']) {
            $googleTeamDriveBaseFolder           = $data_old['googleTeamDriveBaseFolder'];
            $result['reconnect']                 = true;
            $result['selectDrive']               = false;
            $result['googleTeamDriveBaseFolder'] = $googleTeamDriveBaseFolder;
            $retrieve                            = true;
        } else {
            // Select drives
            $googleTeamDrive = new \WpfdAddonGoogleTeamDrive();
            $drives          = $googleTeamDrive->getAllRootDrives();
            $canCreate       = $googleTeamDrive->canCreateDrive();

            if (is_array($drives) && !empty($drives)) {
                update_option($google_select_drive_option, true);
                $content  = '<form class="wpfdparams" method="POST" style="padding: 20px 10px; width: 100%; display: block; box-sizing: border-box;" action="admin.php?page=wpfdAddon-cloud&amp;task=googleteamdrive.selectDriveForConnecting">';
                $content .= '<h3 class="ju-setting-label" style="text-align: left; margin: 20px 0 30px 0; font-size: 25px;">' . esc_html__('Select your root drive for connecting', 'wpfdAddon') . '</h3>';
                $content .= '<label class="ju-setting-label" style="margin-right: 10px; vertical-align: middle;">' . esc_html__('Select root drive', 'wpfdAddon') . '</label>';
                $content .= '<select class="ju-input ju-large-text wpfd_googleTeamDriveBaseFolderId" name="rootGoogleTeamDriveBaseFolderId" 
                        onchange="this.form.submit();" style="font-size: 14px; padding: 15px; border: 1px solid #ccc; border-radius: 4px">';

                if ($canCreate) {
                    $content .= '<option value ="create_new_drive_automatic">' . esc_html__('Create a new drive', 'wpfdAddon') . '</option>';
                }

                $content .= '<option value ="" selected="selected">— ' . esc_html__('Select an existing drive', 'wpfdAddon') . ' —</option>';

                foreach ($drives as $drive) {
                    $content .= '<option value = ' . esc_attr($drive['id']) . ' > ' . esc_html($drive['name']) . '</option>';
                }

                $content .= '</select>';
                $content .= '</form>';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escape above
                echo $content;
                die();
            } else {
                if ($canCreate) {
                    $googleTeamDriveBaseFolder = self::createFolder($google_client);

                    if (!is_null($googleTeamDriveBaseFolder) && !empty($googleTeamDriveBaseFolder)) {
                        $result['reconnect']                 = false;
                        $result['selectDrive']               = true;
                        $result['googleTeamDriveBaseFolder'] = $googleTeamDriveBaseFolder;
                        $retrieve                            = true;
                    }
                } else {
                    $option['googleTeamDriveBaseFolder']    = '';
                    $option['googleTeamDriveCredentials']   = '';
                    $option['googleTeamDriveRedirectUri']   = '';
                    $data_old['googleTeamDriveBaseFolder']  = '';
                    $data_old['googleTeamDriveCredentials'] = '';
                    $data_old['googleTeamDriveRedirectUri'] = '';

                    update_option(self::$option_config, $option);
                    update_option(self::$old_option_config, $data_old);
                }
            }
        }

        if ($retrieve) {
            return $result;
        }
    }

    /**
     * Close google application function
     *
     * @return void
     */
    public static function closeGoogleApplication()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verification is made in before function
        $script_reload = '';
        if (isset($_GET['current_backlink'])) {
            $script_reload = 'window.opener.location.href = "' . self::urlsafeB64Decode($_GET['current_backlink']) . '";';
        }
        // phpcs:enable
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Keep the script text
        echo "<script type='text/javascript'>" . $script_reload . 'window.close();</script>';
    }
}
