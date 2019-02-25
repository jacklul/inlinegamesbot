<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2019 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\inlinegamesbot\Helper;

use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\TelegramLog;

/**
 * Extra functions
 */
class Utilities
{
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
        if (PHP_SAPI === 'cli' && self::$debug_print_enabled) {
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
        if (PHP_SAPI === 'cli') {
            return self::$debug_print_enabled;
        }

        return false;
    }

    /**
     * Make a debug dump from array of debug data
     *
     * @param string $message
     * @param array  $data
     *
     * @return string
     */
    public static function debugDump(string $message = '', array $data = []): string
    {
        if (!empty($message)) {
            $output = $message . PHP_EOL;
        } else {
            $output = PHP_EOL;
        }

        foreach ($data as $var => $val) {
            /** @noinspection NestedTernaryOperatorInspection */
            $output .= $var . ': ' . (is_array($val) ? print_r($val, true) : (is_bool($val) ? ($val ? 'true' : 'false') : $val)) . PHP_EOL;
        }

        return $output;
    }

    /**
     * strpos() with array needle
     * https://stackoverflow.com/a/9220624
     *
     * @param string $haystack
     * @param array  $needle
     * @param int    $offset
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
    public static function updateToArray(Update $update): Update
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
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1024 ** $pow;

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
