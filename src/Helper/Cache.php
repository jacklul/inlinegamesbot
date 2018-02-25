<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2018 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\inlinegamesbot\Helper;

use Longman\TelegramBot\TelegramLog;
use Memcache;
use Memcached;

/**
 * Class Cache
 *
 * @package jacklul\inlinegamesbot\Helper
 */
class Cache
{
    /**
     * @var Memcached|Memcache|null
     */
    private static $cache = null;

    /**
     * Cache constructor
     */
    public static function initialize()
    {
        if (self::$cache === null && class_exists('Memcached')) {
            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Initializing: Memcached');

            try {
                self::$cache = new Memcached;

                if (!empty($host = getenv('MEMCACHED_HOST')) && !empty($port = getenv('MEMCACHED_PORT'))) {
                    Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Using server: ' . $host . ':' . $port);
                    self::$cache->addServer($host, $port);
                }

                if (method_exists(self::$cache, 'getStats') && !@self::$cache->getStats()) {
                    Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Initialization failed!');
                    self::$cache = null;
                }
            } catch (\Throwable $e) {
                TelegramLog::error($e);
                self::$cache = null;
            }
        }

        if (self::$cache === null && class_exists('Memcache')) {
            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Initializing: Memcache');

            try {
                self::$cache = new Memcache;

                if (!empty($host = getenv('MEMCACHE_HOST')) && !empty($port = getenv('MEMCACHE_PORT'))) {
                    Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Using server: ' . $host . ':' . $port);
                    self::$cache->addServer($host, $port);
                }

                if (method_exists(self::$cache, 'getStats') && !@self::$cache->getStats()) {
                    Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Initialization failed!');
                    self::$cache = null;
                }
            } catch (\Throwable $e) {
                TelegramLog::error($e);
                self::$cache = null;
            }
        }
    }

    /**
     * Get data from cache
     *
     * @param $key
     * @return array|mixed|null|string
     */
    public static function get($key)
    {
        return self::$cache !== null ? self::$cache->get($key) : null;
    }

    /**
     * Insert data to cache
     *
     * @param $key
     * @param $val
     *
     * @return bool|null
     */
    public static function set($key, $val)
    {
        return self::$cache !== null ?  self::$cache->set($key, $val) : null;
    }
}
