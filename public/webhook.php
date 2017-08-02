<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Composer autoloader
 */
require __DIR__ . ' /../lib/autoload.php';

// We do not want to unnecessarily run the bot when this is not a POST request
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $app = new Bot();
        $app->run();
    } catch (\Throwable $e) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    }
} else {
    header("Location: https://github.com/jacklul/inlinegamesbot");
}
