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
 * Class Filesystem
 */
class Filesystem
{

    /**
     * Remove recursively a directory
     *
     * @param string $directory Directory path to remove
     *
     * @return void
     */
    public static function rmdir($directory)
    {
        foreach (glob('$directory/{,.}*', GLOB_BRACE) as $file) {
            $filename = explode('/', $file);
            $filename = $filename[count($filename) - 1];
            if ($filename !== '.' && $filename !== '..') {
                if (is_dir($file)) {
                    self::rmdir($file);
                } else {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
        }
        if (file_exists($directory)) {
            rmdir($directory);
        }
    }
}
