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

use AD7six\Dsn\Dsn;
use jacklul\inlinegamesbot\Exception\BotException;
use jacklul\inlinegamesbot\Exception\StorageException;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\TelegramLog;

/**
 * Utilities
 *
 * Extra functions
 *
 * @package jacklul\inlinegamesbot\Helper
 */
class Utilities
{
    /**
     * Supported database engines
     */
    private static $storage_drivers = [
        'mysql'    => 'MySQL',
        'pgsql'    => 'PostgreSQL',
        'postgres' => 'PostgreSQL',
    ];

    /**
     * Return which driver class to use
     *
     * @return string
     *
     * @throws StorageException
     */
    public static function getStorageClass(): string
    {
        if (!empty($debug_storage = getenv('STORAGE_CLASS'))) {
            $storage = str_replace('"', '', $debug_storage);
            self::isDebugPrintEnabled() && self::debugPrint('Forcing storage: \'' . $storage . '\'');
        } elseif (DB::isDbConnected()) {
            $storage = 'jacklul\inlinegamesbot\Storage\BotDB';
        } elseif (getenv('DATABASE_URL')) {
            try {
                $dsn = Dsn::parse(getenv('DATABASE_URL'));
                $dsn = $dsn->toArray();
            } catch (\Exception $e) {
                throw new StorageException($e);
            }

            if (!isset(self::$storage_drivers[$dsn['engine']])) {
                throw new StorageException('Unsupported database type!');
            }

            $storage = 'jacklul\inlinegamesbot\Storage\Database\\' . self::$storage_drivers[$dsn['engine']] ?: '';
        } elseif (defined('DATA_PATH')) {
            $storage = 'jacklul\inlinegamesbot\Storage\File';
        }

        if (empty($storage) || !class_exists($storage)) {
            throw new StorageException('Storage class doesn\'t exist: ' . $storage);
        }

        self::isDebugPrintEnabled() && self::debugPrint('Using storage: \'' . $storage . '\'');

        return $storage;
    }

    /**
     * Is debug print enabled?
     *
     * @var bool
     */
    private static $debug_print_enabled = false;

    /**
     * Show debug message and (if enabled) write to debug log
     *
     * @param string $text
     */
    public static function debugPrint(string $text): void
    {
        if (self::$debug_print_enabled) {
            if ($text === '') {
                return;
            }

            if (TelegramLog::isDebugLogActive()) {
                TelegramLog::debug($text);
            }

            $prefix = '';
            $backtrace = debug_backtrace();

            if (isset($backtrace[1]['class'])) {
                $prefix = $backtrace[1]['class'] . '\\' . $backtrace[1]['function'] . ': ';
            }

            $message = $prefix . trim($text);
            $message = preg_replace('~[\r\n]+~', PHP_EOL . $prefix, $message);

            print $message . PHP_EOL;
        }
    }

    /**
     * Enable/disable debug print
     *
     * @param bool $enabled
     */
    public static function setDebugPrint(bool $enabled = true): void
    {
        self::$debug_print_enabled = $enabled;
    }

    /**
     * Check if debug print is enabled
     */
    public static function isDebugPrintEnabled(): bool
    {
        if ('cli' === PHP_SAPI) {
            return self::$debug_print_enabled;
        }

        return false;
    }

    /**
     * Make a debug dump from array of debug data
     *
     * @param string $message
     * @param array $data
     *
     * @return string
     */
    public static function debugDump(string $message = '', array $data = [])
    {
        if (!empty($message)) {
            $output = $message . PHP_EOL;
        } else {
            $output = PHP_EOL;
        }

        foreach ($data as $var => $val) {
            $output .= $var . ': ' . (is_array($val) ? print_r($val, true) : (is_bool($val) ? ($val ? 'true' : 'false') : $val)) . PHP_EOL;
        }

        return $output;
    }

    /**
     * strpos() with array needle
     * https://stackoverflow.com/a/9220624
     *
     * @param string $haystack
     * @param array $needle
     * @param int $offset
     *
     * @return bool|mixed
     */
    public static function strposa(string $haystack, array $needle, int $offset = 0)
    {
        if (!is_array($needle)) {
            $needle = [$needle];
        }
        foreach ($needle as $query) {
            if (strpos($haystack, $query, $offset) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert update object to array then remove 'raw_data' and 'bot_username' from it
     *
     * @param Update $update
     *
     * @return Update
     */
    public static function updateToArray(Update $update)
    {
        $update_array = json_decode(json_encode($update), true);
        unset($update_array['raw_data']);
        unset($update_array['bot_username']);

        return $update_array;
    }

    /**
     * Formats bytes to human readable format
     * https://stackoverflow.com/a/2510459
     *
     * @param int $bytes
     * @param int $precision
     *
     * @return string
     */
    public static function formatBytes(int $bytes, int $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
