<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Bot\Bot;

/**
 * Define root path
*/
define("ROOT_PATH", realpath(dirname(__DIR__)));

/**
 * @var Composer\Autoload\ClassLoader
 */
$loader = require __DIR__ . ' /../vendor/autoload.php';

//We do not want to unnecessarily run the bot when this is not a web hook request
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('location: /');
}

try {
    $app = new Bot();
    $app->run();
} catch (\Throwable $e) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
}
