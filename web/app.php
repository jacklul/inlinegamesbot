<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

//We do not want to unnecessarily run the bot when this is not a web hook request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    define("APP_BINARY", realpath(__DIR__ . '/../bin/bot'));

    if (file_exists(APP_BINARY)) {
        require_once APP_BINARY;
    }
}
