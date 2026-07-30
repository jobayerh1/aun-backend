<?php
namespace Joomunited\Cloud\WPFD;

defined('ABSPATH') || die('No direct script access allowed!');

use Joomunited\WPFramework\v1_0_6\Utilities;
use GuzzleHttp\Client as GuzzleHttpClient;
use Krizalys\Onedrive\Client as Client;
use Krizalys\Onedrive\Exception\ConflictException;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Krizalys\Onedrive\Proxy\FileProxy;
use Microsoft\Graph\Model\DriveItem;
use Microsoft\Graph\Model;
use Microsoft\Graph\Model\UploadSession;
use Krizalys\Onedrive\Constant\ConflictBehavior;
use Krizalys\Onedrive\Constant\AccessTokenStatus;

/**
 * Onedrive business connector class
 */
class OneDriveBusiness extends CloudConnector
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
    private static $option_config = '_wpfdAddon_onedrive_business_config';
    /**
     * Init connect mode option variable
     *
     * @var string
     */
    private static $connect_mode_option = 'joom_cloudconnector_onedrive_business_connect_mode';
    /**
     * Init network variable
     *
     * @var string
     */
    private $network = 'one-drive-business';
    /**
     * Init id button variable
     *
     * @var string
     */
    private $id_button = 'onedrive-business-connect';

    /**
     * Onedrive business constructor.
     */
    public function __construct()
    {
        self::$params = parent::$instance;
        add_action('cloudconnector_display_onedrive_business_settings', array($this,'displayODBSettings'));
        add_action('wp_ajax_cloudconnector_onedrive_business_changemode', array($this, 'onedriveBusinessChangeMode'));
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

        if (empty($bundle->onedriveBusinessKey) || empty($bundle->onedriveBusinessSecret)) {
            return false;
        }
        $option = get_option(self::$option_config);
        if (!$option) {
            $option = array(
                'onedriveBusinessKey'         => '',
                'onedriveBusinessSecret'      => '',
                'onedriveBusinessSyncTime'    => '30',
                'onedriveBusinessSyncMethod'  => 'sync_page_curl',
                'onedriveBusinessConnectedBy' => get_current_user_id(),
                'state'                       => array()
            );
        }

        $option['onedriveBusinessKey'] = $bundle->onedriveBusinessKey;
        $option['onedriveBusinessSecret'] = $bundle->onedriveBusinessSecret;
        $option['connected'] = 1;
        $option['onedriveBusinessConnectedBy'] = get_current_user_id();
        $option['state'] = (!empty($bundle->onedriveBusinessState) ? $bundle->onedriveBusinessState : array());
        $option['onedriveBaseFolder'] = self::getBasefolder($option);

        update_option(self::$option_config, $option);
        // phpcs:enable
    }

    /**
     * Display connect mode checkbox
     *
     * @return void
     */
    public function displayODBSettings()
    {
        // phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain -- It is string from object
        $connect_mode_list = array(
            'automatic' => esc_html__('Automatic', self::$params->text_domain),
            'manual' => esc_html__('Manual', self::$params->text_domain)
        );

        $onedrive_config = get_option(self::$option_config);
        $config_mode = get_option(self::$connect_mode_option, 'manual');

        if ($config_mode && $config_mode === 'automatic') {
            echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'input[name="onedriveBusinessKey"]\').parents(\'.ju-settings-option\').hide();
                        $(\'input[name="onedriveBusinessSecret"]\').parents(\'.ju-settings-option\').hide();
                        $(\'#onedriver_business_setup\').hide();
                        $(\'#onedrive_business_connect\').hide();
                        $(\'#onedrive_business_disconnect\').hide();
                        $(\'.odb-ju-connect-message\').show();
                    });
                </script>';

            if (!$onedrive_config || empty($onedrive_config['connected'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-onedrive-business\').addClass(\'ju-visibled\').show();
                        $(\'#wpfd-btn-automaticdisconnect-onedrive-business\').removeClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            if ($onedrive_config && !empty($onedrive_config['connected'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-onedrive-business\').removeClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-onedrive-business\').addClass(\'ju-visibled\').show();
                    });
                </script>';
            }
        } else {
            if (!$onedrive_config || empty($onedrive_config['connected'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-onedrive-business\').addClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-onedrive-business\').removeClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            if ($onedrive_config && !empty($onedrive_config['connected'])) {
                echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'#wpfd-btn-automaticconnect-onedrive-business\').removeClass(\'ju-visibled\').hide();
                        $(\'#wpfd-btn-automaticdisconnect-onedrive-business\').addClass(\'ju-visibled\').hide();
                    });
                </script>';
            }

            echo '<script async type="text/javascript">
                    jQuery(document).ready(function($) {
                        $(\'.odb-ju-connect-message\').hide();
                    });
                </script>';
        }

        if ($this->checkJoomunitedConnected()) {
            $juChecked = true;
            $message = '<p>'.esc_html__('The automatic connection mode to OneDrive Business uses a validated Microsoft app, meaning that you just need a single login to connect your drive.', self::$params->text_domain).'</p>';
            $message .= '<p>'.esc_html__('On the other hand, the manual connection requires that you create your own app on the OneDrive Business Developer Console.', self::$params->text_domain).'</p>';
        } else {
            $juChecked = false;
            $message = '<p>'.esc_html__('The automatic connection mode to OneDrive Business uses a validated Microsoft app, meaning that you just need a single login to connect your drive.', self::$params->text_domain);
            $message .= '<strong>'.esc_html__(' However, please login first to your JoomUnited account to use this feature.', self::$params->text_domain).'</strong>';
            $message .= esc_html(' You can do that from', self::$params->text_domain).' <a href="'.esc_url(admin_url('options-general.php')).'"> the WordPress settings</a> '.esc_html__('using the same username and password as on the JoomUnited website.', self::$params->text_domain).'</p>';
            $message .= '<p>'.esc_html__('On the other hand, the manual connection requires that you create your own app on the OneDrive Business Developer Console.', self::$params->text_domain).'</p>';
        }

        echo '<div id="onedrive_business_connect_mode">';
        echo '<div class="ju-settings-option full-width">';
        echo '<label class="ju-setting-label" for="">'.esc_html__('Connecting mode', self::$params->text_domain).'</label>';
        echo '<div class="odb-mode-radio-field automatic-radio-group">';
        echo '<div class="ju-radio-group">';
        foreach ($connect_mode_list as $k => $v) {
            $checked = (!empty($config_mode) && $config_mode === $k) ? 'checked' : '';
            echo '<label><input type="radio" class="ju-radiobox" name="onedriveBusinessConnectMethod" value="'.esc_html($k).'" '.esc_html($checked).'><span>'.esc_html($v).'</span></label>';
        }
        echo '</div>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- String is escaped
        echo '<div class="odb-ju-connect-message">'.$message.'</div>';
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
        $link = str_replace('https', 'http', $link);

        echo '<div id="wpfd-btn-automaticconnect-onedrive-business" class="cloud-connector-title" title="'.esc_html($juChecked ? '' : __('Please login first to your JoomUnited account to use this feature', self::$params->text_domain)).'">';
        echo '<a class="ju-button onedrive-business-automatic-connect '.($juChecked ? '' : 'ju-disconnected-autoconnect').'" href="#"
                name="' . esc_html(self::$params->prefix . '_' . $id_button) . '" 
                id="' . esc_html(self::$params->prefix . '_' . $id_button) . '" 
                data-network="' . esc_html($network) . '" 
                data-link="' . esc_html(self::urlsafeB64Encode($link)) . '" >';
        echo '<img class="automatic-connect-icon" src="'.esc_url(WPFDA_PLUGIN_URL. 'app/admin/assets/images/onedrive_white.png').'" alt=""/>';
        echo esc_html__('Connect OneDrive Business', self::$params->text_domain).'</a>';
        echo '</div>';

        echo '<div id="wpfd-btn-automaticdisconnect-onedrive-business" class="cloud-connector-title" title="'.esc_html($juChecked ? '' : __('Please login first to your JoomUnited account to use this feature', self::$params->text_domain)).'">';
        echo '<a class="ju-button onedrive-business-automatic-disconnect '.($juChecked ? '' : 'ju-disconnected-autoconnect').'" href="admin.php?page=wpfdAddon-onedrive&task=onedrivebusiness.logout" data-network="' . esc_html($network) . '">';
        echo '<img class="automatic-connect-icon" src="'.esc_url(WPFDA_PLUGIN_URL. 'app/admin/assets/images/onedrive.png').'" alt=""/>';
        echo esc_html__('Disconnect OneDrive Business', self::$params->text_domain).'</a>';
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
    public static function onedriveBusinessChangeMode()
    {
        check_ajax_referer('_cloudconnector_nonce', 'cloudconnect_nonce');

        if (isset($_POST['value'])) {
            update_option(self::$connect_mode_option, $_POST['value']);
        }
    }

    /**
     * Get base folder id
     *
     * @param array $option Option config
     *
     * @return array
     */
    public static function getBasefolder($option)
    {
        try {
            require_once WPFDA_PLUGIN_DIR_PATH  . 'lib/prod_vendor/autoload.php';
            $client = new Client(
                $option['onedriveBusinessKey'],
                new Graph(),
                new GuzzleHttpClient(),
                \Krizalys\Onedrive\Onedrive::buildServiceDefinition(),
                array(
                    'state' => isset($option['state']) && !empty($option['state']) ? $option['state'] : array()
                )
            );

            $blogname = trim(str_replace(array(':', '~', '"', '%', '&', '*', '<', '>', '?', '/', '\\', '{', '|', '}'), '', get_bloginfo('name')));
            // Fix onedrive bug, last folder name can not be a dot
            if (substr($blogname, -1) === '.') {
                $blogname = substr($blogname, 0, strlen($blogname) - 1);
            }

            $graph = new Graph();
            $graph->setAccessToken($client->getState()->token->data->access_token);

            $basefolder = array();
            if (empty($option['onedriveBaseFolder'])) {
                $folderName = 'WP File Download Automatic - ' . $blogname;
                $folderName = preg_replace('@["*:<>?/\\|]@', '', $folderName);

                try {
                    $root = $client->getRoot()->createFolder($folderName);
                    $basefolder = array(
                        'id' => $root->id,
                        'name' => $root->name
                    );
                } catch (ConflictException $e) {
                    $root = $client->getDriveItemByPath('/' . $folderName);
                    $basefolder = array(
                        'id' => $root->id,
                        'name' => $root->name
                    );
                }
            } else {
                try {
                    $root = $graph
                        ->createRequest('GET', '/me/drive/items/' . $option['onedriveBaseFolder']['id'])
                        ->setReturnType(Model\DriveItem::class) // phpcs:ignore PHPCompatibility.Constants.NewMagicClassConstant.Found -- Use to sets the return type of the response object
                        ->execute();
                    $basefolder = array(
                        'id' => $root->getId(),
                        'name' => $root->getName()
                    );
                } catch (\Exception $ex) {
                    $folderName = 'WP File Download Automatic - ' . $blogname;
                    $folderName = preg_replace('@["*:<>?/\\|]@', '', $folderName);
                    $folderName = rtrim($folderName);
                    $results = $graph->createRequest('GET', '/me/drive/search(q=\'' . $folderName . '\')')
                        ->setReturnType(Model\DriveItem::class)
                        ->execute();
                    if (isset($results[0])) {
                        $root = new \stdClass;
                        $root->id = $results[0]->getId();
                        $root->name = $results[0]->getName();
                    } else {
                        $root = $client->getRoot()->createFolder($folderName);
                    }

                    $basefolder = array(
                        'id' => $root->id,
                        'name' => $root->name
                    );
                }
            }

            return $basefolder;
        } catch (\Exception $ex) {
            return array();
        }
    }
}
