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
 * Class Utilities
 *
 * phpcs:disable WordPress.Security.NonceVerification -- Nonce verification is made in the Form::validate method
 */
class Utilities
{

    /**
     * Get a http variable
     *
     * @param string $name   Name of the variable
     * @param string $type   Request type
     * @param string $filter Type of filter to apply on the request
     *
     * @return null
     */
    public static function getInput($name, $type = 'GET', $filter = 'cmd')
    {
        $input = null;
        switch (strtoupper($type)) {
            case 'GET':
                if (isset($_GET[$name])) {
                    $input = $_GET[$name];
                }
                break;
            case 'POST':
                if (isset($_POST[$name])) {
                    $input = $_POST[$name];
                }
                break;
            case 'FILES':
                if (isset($_FILES[$name])) {
                    $input = $_FILES[$name];
                }
                break;
            case 'COOKIE':
                if (isset($_COOKIE[$name])) {
                    $input = $_COOKIE[$name];
                }
                break;
            case 'ENV':
                if (isset($_ENV[$name])) {
                    $input = $_ENV[$name];
                }
                break;
            case 'SERVER':
                if (isset($_SERVER[$name])) {
                    $input = $_SERVER[$name];
                }
                break;
            default:
                break;
        }

        switch (strtolower($filter)) {
            case 'cmd':
                $input = strval($input);
                $input = preg_replace('/[^a-z\.]+/', '', strtolower($input));
                break;
            case 'int':
                $input = intval($input);
                break;
            case 'bool':
                $input = $input ? 1 : 0;
                break;
            case 'string':
                $input = sanitize_text_field($input);
                break;
            case 'none':
                break;
            default:
                $input = null;
                break;
        }
        return $input;
    }

    /**
     * Get int from request variables
     *
     * @param string $name Request variable name
     * @param string $type Request type
     *
     * @return null
     */
    public static function getInt($name, $type = 'GET')
    {
        return self::getInput($name, $type, 'int');
    }

    /**
     * Set an http variable
     *
     * @param string $name  Request variable name
     * @param string $value Request variable value
     * @param string $type  Request type
     *
     * @return void
     */
    public static function setInput($name, $value, $type = 'GET')
    {
        switch (strtoupper($type)) {
            case 'GET':
                $_GET[$name] = $value;
                break;
            case 'POST':
                $_POST[$name] = $value;
                break;
            case 'FILES':
                $_FILES[$name] = $value;
                break;
            case 'COOKIE':
                $_COOKIE[$name] = $value;
                break;
            case 'ENV':
                $_ENV[$name] = $value;
                break;
            case 'SERVER':
                $_SERVER[$name] = $value;
                break;
            default:
                break;
        }
    }
}
