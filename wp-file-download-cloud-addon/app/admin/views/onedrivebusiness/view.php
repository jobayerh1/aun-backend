<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */
defined('ABSPATH') || die();

use Joomunited\WPFramework\v1_0_6\View;

/**
 * Class WpfdAddonViewOnedriveBusiness
 */
class WpfdAddonViewOnedriveBusiness extends View
{
    /**
     * Render view onedrive
     *
     * @param null $tpl Template
     *
     * @return void
     */
    public function render($tpl = null)
    {
        $this->getModel('OnedriveConfig');
        parent::render($tpl);
    }
}
