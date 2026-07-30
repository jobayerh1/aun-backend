<?php
use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Form;

/**
 * Class WpfdAddonAwsAdmin
 */
class WpfdAddonAwsAdmin extends WpfdAddonAws
{
    /**
     * WpfdAddonAwsAdmin constructor.
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
        add_filter('wpfda_aws_configuration_content', array($this, 'renderAdminConfigForm'));
        if ($this->checkConnectAws()) {
            add_action('wpfd_addon_dropdown', array($this, 'displayCreateDropDown'), 50);
        }
        add_filter('wpfd_addon_aws_connected', array($this, 'checkConnectAws'));

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
        $formPath = WPFDA_PLUGIN_DIR_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'admin';
        $formPath .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR;
        $awsConfig = $this->config;
        $awsForm = new Form();
        $formFile = $formPath . 'aws_config.xml';
        $html .= '<div id="wpfd-theme-aws" class="">';
        $html .= '<div id="wpfd-btnconnect-aws">';
        if (!$this->checkConnectAws()) {
            if ($this->getLastError()) {
                $html .= '<div class="wpfd-aws-error-message">
                    <div class="wpfd-alert-message"><strong>'.esc_html__('Connection failed: ', 'wpfdAddon').'</strong>'.esc_html__('The AWS Access Key Id or Secret Access Key you provided does not exist.', 'wpfdAddon').'</div>
                </div>';
            }
        } else {
            if ($this->getLastError()) {
                $html .= '<div class="wpfd-aws-error-message">
                    <div class="wpfd-alert-message"><strong>'.esc_html__('Connection failed: ', 'wpfdAddon').'</strong>'.$this->getLastError().'</div>
                </div>';
            }
        }
        ob_start();
        $this->connectButton();
        require_once WPFDA_PLUGIN_DIR_PATH.'/app/admin/templates/settings_aws3.php';
        $html .= ob_get_contents();
        ob_end_clean();
        $html .= '</div>';
        if ($awsForm->load($formFile, $awsConfig)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form render we can not use esc here
            $html .= $awsForm->render();
        }
        $html .= '<a class="ju-button orange-outline-button ju-button-inline aws-documentation" href="https://www.joomunited.com/wordpress-documentation/wp-file-download/674-wp-file-download-addon-amazon-s3-integration" target="_blank">'.  esc_html__('Read the online support documentation', 'wpfdAddon') . '</a>';
        $html .= '</div><div style="clear:both">&nbsp;</div>';

        $tabs['amazon_s3'] = $html;

        return $tabs;
    }

    /**
     * Show connect button AWS
     *
     * @return void
     */
    public function connectButton()
    {
        $app = Application::getInstance('WpfdAddon');

        if ($this->hasAwsButton()) {
            if (!$this->checkConnectAws()) {
                ?>
                <!-- href="admin.php?page=wpfdAddon-aws&task=aws.connect" -->
                <span id="aws_connect" class="ju-button">
                    <img class="automatic-connect-icon" style="vertical-align: middle;" src="<?php echo esc_url(plugins_url('app/admin/assets/images/aws-icon-color.svg', WPFDA_PLUGIN_FILE)); ?>" alt=""/>
                    <?php esc_html_e('Connect Amazon S3', 'wpfdAddon') ?></span>
                <?php
            } else { ?>
                <a id="aws_disconnect" class="ju-button" href="admin.php?page=wpfdAddon-aws&task=aws.disconnect">
                    <img class="automatic-connect-icon" style="vertical-align: middle;" src="<?php echo esc_url(plugins_url('app/admin/assets/images/aws-icon-color.svg', WPFDA_PLUGIN_FILE)); ?>" alt=""/>
                    <?php esc_html_e('Disconnect Amazon S3', 'wpfdAddon') ?></a>
                <?php
            }
        }
    }

    /**
     * Check AWS show connect button
     *
     * @return boolean
     */
    private function hasAwsButton()
    {
        if (isset($this->config) && (!empty($this->config))) {
            if (!empty($this->config['awsKey']) &&
                !empty($this->config['awsSecret'])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check AWS connect
     *
     * @return boolean
     */
    public function checkConnectAws()
    {
        if (isset($this->config) && (!empty($this->config))) {
            if (!empty($this->config['awsKey']) &&
                !empty($this->config['awsSecret']) &&
                isset($this->config['awsConnected']) &&
                (int) $this->config['awsConnected'] === 1
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
        if ($this->checkConnectAws()) {
            echo '<li><a href="#" class="button awsCreate">  
                <span class="wpfd-folder wpfd-liga wpfd-aws-icon"></span> '
                 . esc_html__('Amazon S3', 'wpfdAddon') . '</a>
              </li>';
        }
    }

    /**
     * Duplicate AWS category callback
     *
     * @return void
     */
    public function duplicateCategory()
    {
        wpfdAddon_call('category.duplicateAwsCategory');
    }
}
