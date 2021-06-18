<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2021 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Bot\BotCore;

/**
 * Handle webhook request only when it's a POST request
 */
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . ' /../vendor/autoload.php';

    try {
        $app = new BotCore();
        $app->run(true);
    } catch (\Throwable $e) {
        // Prevent Telegram from retrying
    }
} else {
    header("Location: https://github.com/jacklul/inlinegamesbot");    // Redirect non-POST requests to Github repository
}
