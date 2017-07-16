<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Helper;

use Longman\TelegramBot\TelegramLog;

/**
 * Class DebugLog
 *
 * @package Bot\Helper
 */
class DebugLog
{
    /**
     * Debug logging
     * Either to TelegramLog's debug log or console
     *
     * @param $text
     */
    public static function log($text): void
    {
        if (TelegramLog::isDebugLogActive() || getenv('DEBUG')) {
            $backtrace = debug_backtrace();

            $prefix = '';
            if (isset($backtrace[1]['class'])) {
                $prefix = $backtrace[1]['class'] . '\\' . $backtrace[1]['function'] . ': ';
            }

            $message = $prefix . trim($text);
            $message = preg_replace('~[\r\n]+~', PHP_EOL . $prefix, $message);
        }

        if (TelegramLog::isDebugLogActive() && !empty($message)) {
            TelegramLog::debug($message);
        }

        if (getenv('DEBUG') && !empty($message)) {
            print($message . PHP_EOL);
        }
    }
}
