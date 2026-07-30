<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Application;
use Joomunited\WPFramework\v1_0_6\Controller;
use Joomunited\WPFramework\v1_0_6\Form;

defined('ABSPATH') || die();

/**
 * Class WpfdAddonControllerConfig
 */
class WpfdAddonControllerConfig extends Controller
{
    /**
     * Save config
     *
     * @return void
     */
    public function saveconfig()
    {
        $model = $this->getModel();
        $form  = new Form();
        if (!$form->load('config')) {
            $this->redirect('admin.php?page=wpfd-config&error=1');
        }
        if (!$form->validate()) {
            $this->redirect('admin.php?page=wpfd-config&error=2');
        }
        $datas = $form->sanitize();

        if (!$model->saveConfig($datas)) {
            $this->redirect('admin.php?page=wpfd-config&error=3');
        }
        $this->redirect('admin.php?page=wpfd-config');
    }

    /**
     * Save watermark config
     *
     * @return void
     */
    public function saveWatermarkConfig()
    {
        $model = $this->getModel();
        $form     = new Form();
        $formfile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
        $formfile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'watermark_config.xml';

        if (!$form->load($formfile)) {
            $this->redirect('admin.php?page=wpfd-config&error=1');
        }

        if (!$form->validate()) {
            $this->redirect('admin.php?page=wpfd-config&error=2');
        }

        $data = $form->sanitize();
        $data = array_map('trim', $data);

        if (!$model->saveWatermarkConfig($data)) {
            $this->redirect('admin.php?page=wpfd-config&error=3');
        }

        $this->redirect('admin.php?page=wpfd-config');
    }

    /**
     * Save woocommerce config
     *
     * @return void
     */
    public function saveWooCommerceConfig()
    {
        $model = $this->getModel();
        $form     = new Form();
        $formfile = Application::getInstance('WpfdAddon')->getPath() . DIRECTORY_SEPARATOR . 'admin';
        $formfile .= DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'woocommerce_config.xml';

        if (!$form->load($formfile)) {
            $this->redirect('admin.php?page=wpfd-config&error=1');
        }

        if (!$form->validate()) {
            $this->redirect('admin.php?page=wpfd-config&error=2');
        }

        $data = $form->sanitize();
        $data = array_map('trim', $data);

        if (!$model->saveWoocommerceConfig($data)) {
            $this->redirect('admin.php?page=wpfd-config&error=3');
        }

        $this->redirect('admin.php?page=wpfd-config');
    }
}
