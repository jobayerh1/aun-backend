<?php
/**
 * WP File Download
 *
 * @package WP File Download
 * @author  Joomunited
 * @version 1.0
 */

// No direct access
defined('ABSPATH') || die();

/**
 * Class WpfdCloudCache
 */
class WpfdCloudCache
{
    /**
     * Add cache
     *
     * @param string  $objectId  ID of object
     * @param mixed   $value     Value to store
     * @param string  $cloudType Cloud type to store
     * @param integer $ttl       Time to live, default 0 live forever
     *
     * @return boolean False if value was not set and true if value was set.
     */
    public static function setTransient($objectId, $value, $cloudType = '', $ttl = 0)
    {
        if ($cloudType === '' || !isset($value) || $value === null || !isset($objectId) || $objectId === null) {
            return false;
        }

        $cacheKey = self::getCacheKey($objectId, $cloudType);
        $result = set_transient($cacheKey, $value, $ttl);
        self::addCloudTransientKey($cacheKey, $cloudType);

        return $result;
    }

    /**
     * Get cache value
     *
     * @param string $objectId  ID of object
     * @param string $cloudType Cloud type to store
     *
     * @return boolean|mixed False on cache not set
     */
    public static function getTransient($objectId, $cloudType = '')
    {
        if ($cloudType === '' || !isset($objectId) || $objectId === null) {
            return false;
        }
        $cacheKey = self::getCacheKey($objectId, $cloudType);
        self::addCloudTransientKey($cacheKey, $cloudType);
        return get_transient($cacheKey);
    }

    /**
     * Delete cache by key
     *
     * @param string $objectId  ID of object
     * @param string $cloudType Cloud type to store
     *
     * @return boolean|mixed False on error
     */
    public static function deleteTransient($objectId, $cloudType = '')
    {
        $keys = get_option('_wpfdAddon_transient_keys');

        if (!isset($keys[$cloudType])) {
            return false;
        }

        $cacheKey = self::getCacheKey($objectId, $cloudType);
        $key = array_search($cacheKey, $keys[$cloudType]);
        if (isset($keys[$cloudType][$key])) {
            delete_transient($cacheKey);
            unset($keys[$cloudType][$key]);
            // Save option again
            update_option('_wpfdAddon_transient_keys', $keys);

            return true;
        }

        return false;
    }

    /**
     * Generate cache key
     *
     * @param string $objectId  ID of object
     * @param string $cloudType Cloud type to store
     *
     * @return string
     */
    public static function getCacheKey($objectId, $cloudType)
    {
        return 'wpfdAddon_' . $cloudType . '_' . md5($objectId);
    }

    /**
     * Store cache key for manager it
     *
     * @param string $key       Cache key
     * @param string $cloudType Cloud type of cache
     *
     * @return boolean
     */
    public static function addCloudTransientKey($key, $cloudType = '')
    {
        if ($cloudType === '') {
            return false;
        }

        $keys = get_option('_wpfdAddon_transient_keys', array());

        if ($keys === '') {
            $keys = array();
        }

        $found = false;
        if (isset($keys[$cloudType])) {
            foreach ($keys[$cloudType] as $value) {
                if ($value === $key) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $keys[$cloudType][] = $key;
                update_option('_wpfdAddon_transient_keys', $keys);
            }
        } else {
            $keys[$cloudType][] = $key;
            update_option('_wpfdAddon_transient_keys', $keys);
        }
    }

    /**
     * Clean all cache
     *
     * @param string $cloudType Cloud type
     *
     * @return void
     */
    public static function flushCache($cloudType = '')
    {
        $keys = get_option('_wpfdAddon_transient_keys');
        $keysClone = $keys;
        // Flush all cache
        if ($cloudType === '') {
            foreach ($keys as $cloudCache) {
                foreach ($cloudCache as $key => $value) {
                    // todo: find another way much faster like use sql query
                    delete_transient($value);
                    unset($keysClone[$cloudCache][$key]);
                }
            }
        } else {
            if (isset($keys[$cloudType])) {
                foreach ($keys[$cloudType] as $key => $value) {
                    delete_transient($value);
                    unset($keysClone[$cloudType][$key]);
                }
            }
        }

        // Save option again
        update_option('_wpfdAddon_transient_keys', $keysClone);
    }
}
