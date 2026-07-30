<?php
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Form;

/**
 * Class WpfdAddonOneDriveBusinessAdmin
 */
class WpfdAddonOneDriveBusinessAdmin extends WpfdAddonOneDriveBusiness
{
    /**
     * WpfdAddonOneDriveBusinessAdmin constructor.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->addActions();
    }

    /**
     * Add actions
     *
     * @return void
     */
    public function addActions()
    {
        add_filter('wpfd_addon_onedrive_business_connected', 'wpfda_onedrive_business_connected');
        add_filter('wpfda_after_cloud_configuration_content', array($this, 'renderAdminConfigForm'));

        add_action('wpfd_addon_dropdown', array($this, 'displayCreateDropDown'), 40);

        add_action('wp_ajax_wpfdAddonAddOneDriveBusinessCategory', array($this, 'addCategory'));
        add_action('wp_ajax_wpfdAddonDeleteOneDriveBusinessCategory', array($this, 'deleteCategory'));
        add_action('wp_ajax_wpfdAddonDuplicateOneDriveBusinessCategory', array($this, 'duplicateCategory'));
    }

    /**
     * Render admin config form
     *
     * @param array $tabs Tabs
     *
     * @return array
     */
    public function renderAdminConfigForm($tabs)
    {
        $html = '';
        // Onedrive Business
        $formPath = WPFDA_PLUGIN_DIR_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'admin';
        $formPath .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR;
        $onedriveBusinessConfig = $this->config;
        $onedriveBusinessForm = new Form();
        $formFile = $formPath . 'onedrive_business_config.xml';
        $this->wpfdOnedriveBusinessShowPushNotification();
        $html .= '<div id="wpfd-theme-onedrive-business" class="">';
        $html .= '<div id="wpfd-btnconnect-onedrive-business">';
        ob_start();
        do_action('cloudconnector_display_onedrive_business_settings');
        $this->connectButton();
        $html .= ob_get_contents();
        ob_end_clean();
        $html .= '</div>';
        if ($onedriveBusinessForm->load($formFile, $onedriveBusinessConfig)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form render we can not use esc here
            $html .= $onedriveBusinessForm->render();
        }

        $html .= '</div><div style="clear:both">&nbsp;</div>';

        $tabs['onedrive_business'] = $html;

        return $tabs;
    }

    /**
     * Show connect button
     *
     * @return void
     */
    public function connectButton()
    {
        $app = Application::getInstance('WpfdAddon');

        if ($this->hasOneDriveButton()) {
            if (!$this->checkConnectOnedrive()) {
                $url = $this->getAuthorisationUrl();
                ?>
                <a id="onedrive_business_connect" class="ju-button" href="#"
                   onclick="window.open('<?php echo esc_url($url); ?>','foo','width=600,height=600');return false;">
                    <img style="vertical-align: middle;"
                         src="<?php echo esc_url(plugins_url('app/admin/assets/images/onedrive_white.png', WPFDA_PLUGIN_FILE)); ?>" alt=""/>
                    <?php esc_html_e('Connect OneDrive Business', 'wpfdAddon') ?></a>
                <?php
            } else { ?>
                <a id="onedrive_business_disconnect" class="ju-button" href="admin.php?page=wpfdAddon-onedrive&task=onedrivebusiness.logout">
                    <img style="vertical-align: middle;"
                         src="<?php echo esc_url(plugins_url('app/admin/assets/images/onedrive.png', WPFDA_PLUGIN_FILE)); ?>" alt=""/>
                    <?php esc_html_e('Disconnect OneDrive Business', 'wpfdAddon') ?></a>
                <?php
            }
        } ?>
        <?php ?>
        <div id="onedriver_business_setup">
            <div class="ju-settings-option full-width">
                <label class="ju-setting-label" for=""><?php esc_html_e('Redirect URIs Server', 'wpfdAddon') ?></label>

                <input title="" class="ju-input ju-large-text" style="width:100%" type="text" readonly="true"
                       value="<?php echo esc_url(get_admin_url()); ?>admin.php"/>
            </div>
            <div class="ju-settings-option full-width">
                <label class="ju-setting-label" for=""><?php esc_html_e('Redirect URIs', 'wpfdAddon') ?></label>

                <?php
                $url_onedriveAuth = '/wp-admin/admin.php?page=wpfdAddon-onedrive&task=onedrivebusiness.authenticate';
                ?>
                <input title="" class="ju-input ju-large-text" style="width:100%" type="text" readonly="true"
                       value="<?php echo esc_url(site_url() . $url_onedriveAuth); ?>"/>
            </div>
            <a class="ju-button orange-outline-button ju-button-inline onedrive-business-documentation" href="https://www.joomunited.com/wordpress-documentation/wp-file-download/298-wp-file-download-addon-onedrive-onedrive-business-integration"
               target="_blank"><?php esc_html_e('Read the online support documentation', 'wpfdAddon') ?></span></a>

        </div>
        <?php
    }

    /**
     * Check Onedrive Business show connect button
     *
     * @return boolean
     */
    private function hasOneDriveButton()
    {
        if (isset($this->config) && (!empty($this->config))) {
            if (!empty($this->config['onedriveBusinessKey']) &&
                !empty($this->config['onedriveBusinessSecret'])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check Onedrive Business connection
     *
     * @return boolean
     */
    private function checkConnectOnedrive()
    {
        if (isset($this->config) && (!empty($this->config))) {
            if (!empty($this->config['onedriveBusinessKey']) &&
                !empty($this->config['onedriveBusinessSecret']) &&
                isset($this->config['connected']) &&
                (int) $this->config['connected'] === 1
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Display drop down create onedrive
     *
     * @return void
     */
    public function displayCreateDropDown()
    {
        if ($this->checkConnectOnedrive()) {
            echo '<li><a href="#" class="button onedriveBusinessCreate">
                <i class="wpfd-folder wpfd-liga">onedrive</i> '
                 . esc_html__('OneDrive Business', 'wpfdAddon') . '</a>
              </li>';
        }
    }

    /**
     * Add new category endpoint
     *
     * @return void
     */
    public function addCategory()
    {
        wpfdAddon_call('category.addOneDriveBusinessCategory');
    }

    /**
     * Delete OneDrive category callback
     *
     * @return void
     */
    public function deleteCategory()
    {
        wpfdAddon_call('category.deleteOneDriveBusinessCategory');
    }

    /**
     * Duplicate OneDrive category callback
     *
     * @return void
     */
    public function duplicateCategory()
    {
        wpfdAddon_call('category.duplicateOneDriveBusinessCategory');
    }

    /**
     * Show push notification elements in onedrice business config tab
     *
     * @return void
     */
    public function wpfdOnedriveBusinessShowPushNotification()
    {
        if (!class_exists('WpfdAddonOneDriveBusiness')) {
            $app = Application::getInstance('WpfdAddon');
            $classesPath = $app->getPath() . DIRECTORY_SEPARATOR . $app->getType() . DIRECTORY_SEPARATOR . 'classes';
            require_once $classesPath . DIRECTORY_SEPARATOR . 'WpfdAddonGoogle.php';
            ;
        }

        $onedrive = new WpfdAddonOneDriveBusiness();
        if (!$onedrive->checkAuth()) {
            return;
        }
        // Display error
        $onedrivePush = get_option('_wpfd_onedrive_business_watch_changes', 0);
        $watchData = get_option('_wpfd_onedrive_business_watch_data', '');
        $errorMessage = '';
//        if ($watchData === '' || !is_array($watchData)) {
//
//        }
        $errorBgColor = '';
        if ($errorMessage !== '') {
            $errorBgColor = ' error';
        }

        if ($onedrivePush) {
            $text = esc_html__('Stop watching changes', 'wpfdAddon');
        } else {
            $text = esc_html__('Watch changes', 'wpfdAddon');
        }

        // Nonce field for ajax request
        $security = wp_create_nonce('wpfd-onedrive-business-push');
        ?>
        <button id="wpfd-btnpush-onedrive-business"
                data-security="<?php echo esc_attr($security); ?>"
                class="ju-button orange-outline-button<?php echo $errorBgColor; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This place dont need to escaped?>"
                style="display: inline-block;float: right;"
                title="<?php esc_html_e('Enable/Disable the push notification for file synchronization. Allows you to make instant file synchronization.', 'wpfdAddon'); ?>"
        >
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above
            echo $text;
            ?>
        </button>
        <?php
        $displayError = get_option('_wpfd_onedrive_business_display_push_error', 1);
        if ($errorMessage !== '' && $displayError) : ?>
            <div class="wpfd-float-message">
                <div class="wpfd-alert-message"><strong>Warning: </strong><?php echo $errorMessage; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above ?></div>
            </div>
        <?php endif;
    }
}
