<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */
defined('ABSPATH') || die();

use \Joomunited\WPFramework\v1_0_6\View;

/**
 * Class WpfdAddonViewGoogleTeamdrive
 */
class WpfdAddonViewGoogleTeamdrive extends View
{
    /**
     * Render view google drive
     *
     * @param null $tpl Template
     *
     * @return void
     */
    public function render($tpl = null)
    {
        $this->getModel('CloudConfig');

        parent::render($tpl);
    }
}
