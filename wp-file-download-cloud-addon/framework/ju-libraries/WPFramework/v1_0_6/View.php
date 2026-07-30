<?php
/**
 * WP Framework
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

namespace Joomunited\WPFramework\v1_0_6;

defined('ABSPATH') || die();

/**
 * Class View
 */
class View
{

    /**
     * Default view template
     *
     * @var string
     */
    public $default_tpl = 'default';

    /**
     * View path
     *
     * @var string
     */
    protected $path;


    /**
     * Render a view
     *
     * @param string $tpl Template to render
     *
     * @return void
     */
    public function render($tpl = null)
    {
        $result = $this->loadTemplate($tpl);

        // phpcs:ignore WordPress.Security.EscapeOutput -- Escaping should be done in the template itself
        echo $result;

        $pluginName = Application::getInstance()->getName();
        if (defined(strtoupper($pluginName) . '_AJAX')) {
            die();
        }
    }

    /**
     * Load a template file
     *
     * @param null $tpl Template file
     *
     * @return string
     */
    public function loadTemplate($tpl = null)
    {
        $tpl = isset($tpl) ? $tpl : $this->default_tpl;

        $file = preg_replace('/[^A-Z0-9_\.-]/i', '', $tpl);
        $file = $this->path . 'tpl' . DIRECTORY_SEPARATOR . $file . '.php';

        if (file_exists($file)) {
            ob_start();
            include $file;
            $output = ob_get_contents();
            ob_end_clean();
            return $output;
        }
        return '';
    }

    /**
     * Set path
     *
     * @param string $path Path
     *
     * @return void
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Retrieve model associated to the view
     *
     * @param string $modelname Model name
     *
     * @return Model|false
     */
    public function getModel($modelname = null)
    {

        if (empty($modelname)) {
            $modelname = get_class($this);
            $modelname = str_replace(Factory::getApplication()->getName() . 'View', '', $modelname);
        }
        $modelname = preg_replace('/[^A-Z0-9_-]/i', '', $modelname);
        $filepath = Factory::getApplication()->getPath() . DIRECTORY_SEPARATOR . Factory::getApplication()->getType() . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . strtolower($modelname) . '.php';
        if (!file_exists($filepath)) {
            return false;
        }
        include_once $filepath;
        $class = Factory::getApplication()->getName() . 'Model' . ucfirst($modelname);
        $model = new $class();
        return $model;
    }
}
