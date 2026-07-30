<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\View;
use Joomunited\WPFramework\v1_0_6\Form;

defined('ABSPATH') || die();

/**
 * Class WpfdAddonViewConfig
 */
class WpfdAddonViewConfig extends View
{
    /**
     * Render view config
     *
     * @param null $tpl Template
     *
     * @return void
     */
    public function render($tpl = null)
    {
        $modelConf = $this->getModel('config');
        $file_form = new Form();
        if ($file_form->load('config', $modelConf->getSocialConfig())) {
            $this->configform = $file_form->render();
        }
        parent::render($tpl);
    }
}
