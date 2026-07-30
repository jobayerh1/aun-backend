<?php
/**
 * WP File Download Addon
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

use Joomunited\WPFramework\v1_0_6\Application;

// Prohibit direct script loading
defined('ABSPATH') || die('No direct script access allowed!');

/**
 * Method ato load class
 *
 * @param string $className Class name
 *
 * @return void
 */
function wpfdAddonAutoload($className)
{
    $className = ltrim($className, '\\');
    // Return if it's not a Joomunited's class
    if (strpos($className, 'Joomunited\WP_File_Download_Cloud_Addon\Admin\Fields') === 0) {
        $fileName = '';
        $lastNsPos = strripos($className, '\\');
        if ($lastNsPos) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

        $folder = 'admin' . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'fields' . DIRECTORY_SEPARATOR;
        $fileName = '' . DIRECTORY_SEPARATOR . substr($fileName, 53);
        if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . $folder . $fileName)) {
            require dirname(__FILE__) . DIRECTORY_SEPARATOR . $folder . $fileName;
        }
        return;
    }

    // Load background task files
    if (strpos($className, 'Joomunited\BackgroundTasks') === 0) {
        $fileName = '';
        $lastNsPos = strripos($className, '\\');
        if ($lastNsPos) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

        $folder = 'admin' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Tasks' . DIRECTORY_SEPARATOR;
        $fileName = str_replace('Joomunited' . DIRECTORY_SEPARATOR . 'BackgroundTasks' . DIRECTORY_SEPARATOR, '', $fileName);
        if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . $folder . $fileName)) {
            require dirname(__FILE__) . DIRECTORY_SEPARATOR . $folder . $fileName;
        }
        return;
    }

    //don't load any namespace class
    if (strpos($className, '\\') !== false) {
        return;
    }
    $fileName = basename($className) . '.php';
    $app = Application::getInstance('WpfdAddon', WPFDA_PLUGIN_FILE);
    if ($app->isAdmin()) {
        $file = $app->getPath() . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helpers';
        $file .= DIRECTORY_SEPARATOR . $fileName;
    } else {
        $file = $app->getPath() . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'helpers';
        $file .= DIRECTORY_SEPARATOR . $fileName;
    }
    if (file_exists($file)) {
        require_once($file);
    }
}

spl_autoload_register('wpfdAddonAutoload');

$path_languages = dirname(plugin_basename(WPFDA_PLUGIN_FILE)) . DIRECTORY_SEPARATOR . 'app';
$path_languages .= DIRECTORY_SEPARATOR . 'languages';
load_plugin_textdomain('wpfdAddon', null, $path_languages);
