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

/**
 * Class CrashDump
 *
 * @package Bot\Helper
 */
class CrashDump
{
    /**
     * Make a debug dump
     *
     * @TODO this could probably generate more input
     *
     * @param $file_name
     */
    public static function dump($file_name): void
    {
        if (!is_dir(VAR_PATH . '/crashdumps/')) {
            mkdir(VAR_PATH . '/crashdumps/', 0755, true);
        }

        file_put_contents(VAR_PATH . '/crashdumps/' . date('y_m_d-H_i_s') . '-' . $file_name . '.txt', print_r(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT), true) . PHP_EOL);
    }
}
