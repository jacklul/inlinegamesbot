<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Helper;

use Bot\Exception\BotException;
use Longman\TelegramBot\TelegramLog;

/**
 * Class Debug
 *
 * Class for printing debug information into console
 *
 * @package Bot\Helper
 */
class Debug
{
    /**
     * Is this even enabled?
     *
     * @var bool
     */
    private static $enabled = false;

    /**
     * Show debug message and (if enabled) write to debug log
     *
     * @param $text
     *
     * @throws BotException
     */
    public static function print($text): void
    {
        if (self::$enabled) {
            if ($text === '') {
                throw new BotException('Text cannot be empty!');
            }

            if (TelegramLog::isDebugLogActive()) {
                TelegramLog::debug($text);
            }

            $prefix = '';
            if (!getenv('DEBUG_NO_BACKTRACE')) {
                $backtrace = debug_backtrace();

                if (isset($backtrace[1]['class'])) {
                    $prefix = $backtrace[1]['class'] . '\\' . $backtrace[1]['function'] . ': ';
                }
            }

            $message = $prefix . trim($text);
            $message = preg_replace('~[\r\n]+~', PHP_EOL . $prefix, $message);

            print $message . PHP_EOL;
        }
    }

    /**
     * Enable/disable switch
     *
     * @param bool $enabled
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Check if debug print is enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}
