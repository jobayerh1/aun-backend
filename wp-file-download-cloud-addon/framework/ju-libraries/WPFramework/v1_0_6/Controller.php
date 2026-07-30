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
 * Class Controller
 */
class Controller
{

    /**
     * Default controller task
     *
     * @var string
     */
    public $default_task = 'display';

    /**
     * Render a view
     *
     * @return void
     */
    public function display()
    {
        $view = $this->loadView();
        $view->render();
    }

    /**
     * Load view content
     *
     * @return mixed
     */
    protected function loadView()
    {
        $viewname = Utilities::getInput('view');
        if (empty($viewname)) {
            $viewname = get_class($this);
            $viewname = strtolower(str_replace(Factory::getApplication()->getName() . 'Controller', '', $viewname));
        }
        $viewname = preg_replace('/[^A-Z0-9_-]/i', '', $viewname);
        $filepath = Factory::getApplication()->getPath() . DIRECTORY_SEPARATOR . Factory::getApplication()->getType() . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $viewname . DIRECTORY_SEPARATOR;

        if (file_exists($filepath . 'view.php')) {
            require_once $filepath . 'view.php';
        } else {
            Error::raiseError('404', 'View file not found');
        }

        $class = Factory::getApplication()->getname() . 'View' . ucfirst($viewname);
        $view = new $class();
        $view->setPath($filepath);
        return $view;
    }

    /**
     * Get the model
     *
     * @param string $modelname Model name
     *
     * @return boolean
     */
    public function getModel($modelname = null)
    {

        if (empty($modelname)) {
            $modelname = get_class($this);
            $modelname = str_replace(Factory::getApplication()->getName() . 'Controller', '', $modelname);
        }
        $modelname = preg_replace('/[^A-Z0-9_-]/i', '', $modelname);
        $filepath = Factory::getApplication()->getPath() . DIRECTORY_SEPARATOR . Factory::getApplication()->getType() . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . strtolower($modelname) . '.php';
        if (!file_exists($filepath)) {
            return false;
        }
        include_once $filepath;
        $class = Factory::getApplication()->getName() . 'Model' . $modelname;
        $model = new $class();
        return $model;
    }

    /**
     * Redirect to an url
     *
     * @param string $location Location to redirect to
     *
     * @return void
     */
    public function redirect($location)
    {
        if (!headers_sent()) {
            wp_safe_redirect($location, 303);
        } else {
            echo '<script>document.location.href="' . esc_url($location, null, '') . '";</script>\n';
        }
        exit;
    }

    /**
     * Exit a request serving a json result
     *
     * @param string $status Exit status
     * @param array  $datas  Echoed datas
     *
     * @since 1.0.3
     *
     * @return void
     */
    protected function exitStatus($status = '', $datas = array())
    {
        $response = array('response' => $status, 'datas' => $datas);
        echo json_encode($response);
        die();
    }
}
