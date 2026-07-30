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
 * Class Model
 */
class Model
{

    /**
     * Database reference
     *
     * @var \wpdb
     */
    protected $db;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = &$wpdb;
    }

    /**
     * Get class instance
     *
     * @param string $name Name of the model
     * @param null   $type Site or admin model
     *
     * @return boolean
     */
    public static function getInstance($name = '', $type = null)
    {
        $app = Application::getInstance();

        $name = preg_replace('/[^A-Z0-9_\.-]/i', '', $name);
        if ($type === null) {
            $type = $app->getType();
        } elseif ($type !== 'admin' && $type !== 'site') {
            return false;
        }

        $className = $app->getName() . 'Model' . ucfirst(strtolower($name));
        $classFile = $app->getPath() . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . strtolower($name) . '.php';
        if (!file_exists($classFile)) {
            return false;
        }
        require_once $classFile;
        if (class_exists($className)) {
            return new $className();
        }
        return false;
    }

    /**
     * Get database prefix
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->db->prefix;
    }

    /**
     * Update a row in table
     *
     * @param string       $table        Table name
     * @param array        $data         Datas to update
     * @param array        $where        Where clause
     * @param array|string $format       An array of formats to be mapped to each of the values in $data.
     * @param array|string $where_format An array of formats to be mapped to each of the values in $where.
     *
     * @return false|integer
     */
    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        return $this->db->update($table, $data, $where, $format, $where_format);
    }
}
