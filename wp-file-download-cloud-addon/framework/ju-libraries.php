<?php
/*
JUPlugin Name: Joomunited WP Framework
Description: WP Framework for Joomunited extensions
Author: Joomunited
Version: 1.0.6
Author URI: http://www.joomunited.com
*/
defined('ABSPATH') || die();

// Prevent from loading julibraries twice
if (!defined('JU_LIBRARY_V1_0_6')) {
    /**
     * Julibrary autoloader method
     *
     * @param string $className Class name that should be loaded
     *
     * @return void
     */
    function juLibrariesAutoload_v1_0_6($className)
    {
        $className = ltrim($className, '\\');

        //Return if it's not a Joomunited's class
        if (strpos($className, 'Joomunited') !== 0) {
            return;
        }

        //Change Joomunited to the mu-plugin junited-libraries directory
        $fileName = '';

        $lastNsPos = strripos($className, '\\');
        if ($lastNsPos) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        $fileName = 'ju-libraries' . substr($fileName, 10);
        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

        if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . $fileName)) {
            require dirname(__FILE__) . DIRECTORY_SEPARATOR . $fileName;
        }
    }

    spl_autoload_register('juLibrariesAutoload_v1_0_6');

    define('JU_LIBRARY_V1_0_6', true);
}
