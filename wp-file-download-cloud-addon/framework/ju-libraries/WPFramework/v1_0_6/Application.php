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
 * Class Application
 */
class Application
{

    /**
     * Type of application, could be site or admin
     *
     * @var string
     */
    protected $type = 'site';

    /**
     * Default controller task
     *
     * @var string
     */
    protected $default_task = 'display';

    /**
     * Generated instances of application
     *
     * @var array
     */
    protected static $instances = array();

    /**
     * Database object
     *
     * @var object
     */
    protected $dbo;

    /**
     * Application instance name
     * Generally the slug of the plugin using the framework
     *
     * @var string
     */
    protected $name = '';

    /**
     * Path of the plugin using the framework
     *
     * @var string
     */
    protected $path = '';

    /**
     * Plugin url
     *
     * @var string
     */
    protected $url = '';

    /**
     * Is the application already initialized
     *
     * @var boolean
     */
    protected $isinit = false;

    /**
     * Which last plugin did use the framework
     *
     * @var string
     */
    protected static $lastUse = '';

    /**
     * Retrieve the application instance
     *
     * @param string $name Slug of plugin which requires the instance
     * @param string $path Path of the plugin
     * @param string $type Admin or Site application
     *
     * @return mixed
     */
    public static function getInstance($name = null, $path = __FILE__, $type = null)
    {
        if ($name === null) {
            $name = self::$lastUse;
        }
        if (!array_key_exists($name, self::$instances)) {
            self::$instances[$name] = new Application();
            self::$instances[$name]->name = $name;
            self::$instances[$name]->path = plugin_dir_path($path);
            self::$instances[$name]->url = plugins_url('', $path);

            if ($type !== null) {
                self::$instances[$name]->type = $type;
            } elseif (is_admin()) {
                if (defined('DOING_AJAX')) {
                    if (Utilities::getInput('juwpfisadmin', 'GET', 'string')) {
                        self::$instances[$name]->type = 'site';
                    } else {
                        self::$instances[$name]->type = 'admin';
                    }
                } else {
                    self::$instances[$name]->type = 'admin';
                }
            } else {
                self::$instances[$name]->type = 'site';
            }
        }
        self::$lastUse = $name;
        return self::$instances[$name];
    }

    /**
     * Initialize the application
     *
     * @return void
     */
    public function init()
    {
        if ($this->isinit === true) {
            return;
        }
        //call app init file
        $file = $this->getPath() . DIRECTORY_SEPARATOR . $this->getType() . DIRECTORY_SEPARATOR . 'init.php';
        if (file_exists($file)) {
            include($file);
        }
        //call filters
        $file = $this->getPath() . DIRECTORY_SEPARATOR . $this->getType() . DIRECTORY_SEPARATOR . 'filters.php';
        if (file_exists($file)) {
            include($file);
            $class = $this->getName() . 'Filter';
            if (method_exists($class, 'load')) {
                $filter = new $class();
                $filter->load();
            }
        }
        $this->isinit = true;
    }

    /**
     * Get the name of the plugin using the application
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get path of the plugin using the application
     *
     * @param boolean $rel Retrieve relative path or full path
     *
     * @return string
     */
    public function getPath($rel = false)
    {
        if ($rel) {
            return str_replace(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR, '', $this->path . 'app');
        } else {
            return $this->path . DIRECTORY_SEPARATOR . 'app';
        }
    }


    /**
     * Execute a task
     *
     * @param null $default_task Default task
     *
     * @return void
     */
    public function execute($default_task = null)
    {

        $task = Utilities::getInput('task');

        if (empty($task) && $default_task !== null) {
            $task = $default_task;
        } else {
            $task = strtolower($task);
        }
        $task = strtolower($task);
        $split = explode('.', $task);

        $controllerName = $split[0];
        $taskName = count($split) > 1 ? $split[1] : '';

        $fileController = Factory::getApplication()->getPath() . DIRECTORY_SEPARATOR . $this->type . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . $controllerName . '.php';

        if (!file_exists($fileController)) {
            Error::raiseError('404', 'Controller not found');
        }
        include_once $fileController;

        $controllerClass = strtolower(Factory::getApplication()->getName()) . 'Controller' . ucfirst($controllerName);
        $controller = new $controllerClass();

        if (method_exists($controller, $taskName)) {
            $controller->$taskName();
        } elseif (property_exists($controller, 'default_task') && method_exists($controller, $controller->default_task)) {
            $defaultTask = $controller->default_task;
            $controller->$defaultTask();
        } else {
            Error::raiseError('404', 'Task not found');
        }
    }

    /**
     * Is the application an admin one
     *
     * @return boolean
     */
    public function isAdmin()
    {
        return $this->type === 'admin';
    }

    /**
     * Is the application a site one
     *
     * @return boolean
     */
    public function isSite()
    {
        return $this->type === 'site';
    }

    /**
     * Get type of application
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Is this an ajax query
     *
     * @return boolean
     */
    public function isAjax()
    {
        if (DOING_AJAX === true) {
            return true;
        }
        return false;
    }

    /**
     * Get the ajax url
     *
     * @return mixed
     */
    public function getAjaxUrl()
    {
        if ($this->isAdmin()) {
            return admin_url('admin-ajax.php?action=' . $this->getName() . '&');
        } else {
            return admin_url('admin-ajax.php?juwpfisadmin=false&action=' . $this->getName() . '&');
        }
    }

    /**
     * Get the base url for the plugin
     *
     * @return mixed
     */
    public function getBaseUrl()
    {
        return $this->url;
    }
}
