<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * Copyright (c) 2016 Jack'lul <https://jacklul.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

set_time_limit(15);

ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// ------------ Config START ------------
$PROJECT_DIR = __DIR__ . '/src/';
$TEMP_DIR = __DIR__ . '/tmp/';

$API_KEY = '';
$BOT_USERNAME = '';

$MYSQL_CREDENTIALS = [
    'host'     => 'localhost',
    'user'     => '',
    'password' => '',
    'database' => '',
];

$QUERY_STRING_AUTH = '';

$MESSAGE_LIMITS = 'Telegram API limits reached - unable to handle this request!';

// ------------- Config END -------------

if(!defined('STDIN') && !isset($_GET[$QUERY_STRING_AUTH])) {
    header("Status: 403 Forbidden");
    exit;
} elseif (defined("STDIN")) {
    unset($argv[0]);
    $POST = implode(' ', $argv);
}

$POST_DATA = json_decode(file_get_contents("php://input"), true);

$user_id = null;
if (isset($POST_DATA['callback_query']['from']['id'])) {
    $user_id = $POST_DATA['callback_query']['from']['id'];
} elseif (isset($POST_DATA['inline_query']['from']['id'])) {
    $user_id = $POST_DATA['inline_query']['from']['id'];
} elseif (isset($POST_DATA['chosen_inline_result']['from']['id'])) {
    $user_id = $POST_DATA['chosen_inline_result']['from']['id'];
} elseif (isset($POST_DATA['message']['from']['id'])) {
    $user_id = $POST_DATA['message']['from']['id'];
} elseif (isset($POST_DATA['edited_message']['from']['id'])) {
    $user_id = $POST_DATA['edited_message']['from']['id'];
} elseif (isset($POST_DATA['channel_post']['from']['id'])) {
    $user_id = $POST_DATA['channel_post']['from']['id'];
} elseif (isset($POST_DATA['edited_channel_post']['from']['id'])) {
    $user_id = $POST_DATA['edited_channel_post']['from']['id'];
}

if (!is_null($user_id) && file_exists($TEMP_DIR . '/' . $user_id . '.block') && time() < (filemtime($TEMP_DIR . '/' . $user_id . '.block') + 60)) {
    exit;
}

if (!is_null($user_id)) {
    if (file_exists($TEMP_DIR . '/' . $user_id . '.limit')) {
        $reply = file_get_contents($TEMP_DIR . '/' . $user_id . '.limit');

        if (preg_match("/retry_after\"\:(.*)\}\}/", $reply, $matches)) {
            $time = $matches[1] - (time() - filemtime($TEMP_DIR . '/' . $user_id . '.limit')) + 1;
        }

        if ((!empty($time) && $time > 0) || (time() < (filemtime($TEMP_DIR . '/' . $user_id . '.limit') + 60))) {
            if (isset($POST_DATA['callback_query']['id'])) {
                $REPLY = [
                    'method' => 'answerCallbackQuery',
                    'callback_query_id' => $POST['callback_query']['id'],
                    'text' => $MESSAGE_LIMITS . (!empty($time) ? "\n\n" . "Wait $time seconds!":''),
                    'show_alert' => true,
                ];
            } elseif (isset($POST_DATA['inline_query']['id'])) {
                $REPLY = [
                    'method' => 'answerInlineQuery',
                    'inline_query_id' => $POST['inline_query']['id'],
                    'switch_pm_text' => $MESSAGE_LIMITS . (!empty($time) ? "\n\n" . "Wait $time seconds!":''),
                    'inline_query_offset' => null,
                    'cache_time' => 60,
                    'results' => [],
                ];
            } elseif (isset($POST_DATA['message']['chat']['id'])) {
                $REPLY = [
                    'method' => 'sendMessage',
                    'chat_id' => $POST['message']['chat']['id'],
                    'text' => $MESSAGE_LIMITS . (!empty($time) ? "\n\n" . "Wait $time seconds!":''),
                ];
            }

            if (!empty($REPLY)) {
                header("Status: 200 OK");
                header("Content-Type: application/json");
                echo json_encode($REPLY, true);
            }

            exit;
        }
    }
}

require __DIR__ . '/vendor/autoload.php';

$COMMANDS_DIR = $PROJECT_DIR . '/Commands/';
foreach(scandir($PROJECT_DIR) as $class) {
    if (is_file($PROJECT_DIR . '/' . $class) && file_exists($PROJECT_DIR . '/' . $class)) {
        $file_parts = pathinfo($PROJECT_DIR . '/' . $class);
        if($file_parts['extension'] == 'php') {
            require_once($PROJECT_DIR . '/' . $class);
        }
    }
}

if (!is_dir($TEMP_DIR)) {
    mkdir($TEMP_DIR);
}

try {
    $telegram = new Longman\TelegramBot\Telegram($API_KEY, $BOT_USERNAME);

    \Longman\TelegramBot\TelegramLog::initErrorLog('exception.log');

    if (!empty($POST)) {
        $telegram->setCustomInput($POST);
    }

    $telegram->enableMySQL($MYSQL_CREDENTIALS);

    $telegram->addCommandsPath($COMMANDS_DIR);

    $telegram->setDownloadPath($TEMP_DIR);
    $telegram->setUploadPath($TEMP_DIR);

    //$telegram->enableAdmins([]);

    //$telegram->enableBotan('');

    $telegram->handle();
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    if (strpos($e, 'Connection timed out') !== false || strpos($e, 'Too Many Requests') !== false) {
        $POST = json_decode($POST, true);

        $user_id = null;
        if (isset($POST['callback_query']['from']['id'])) {
            $user_id = $POST['callback_query']['from']['id'];
        } elseif (isset($POST['inline_query']['from']['id'])) {
            $user_id = $POST['inline_query']['from']['id'];
        } elseif (isset($POST['chosen_inline_result']['from']['id'])) {
            $user_id = $POST['chosen_inline_result']['from']['id'];
        } elseif (isset($POST['message']['from']['id'])) {
            $user_id = $POST['message']['from']['id'];
        } elseif (isset($POST['edited_message']['from']['id'])) {
            $user_id = $POST['edited_message']['from']['id'];
        } elseif (isset($POST['channel_post']['from']['id'])) {
            $user_id = $POST['channel_post']['from']['id'];
        } elseif (isset($POST['edited_channel_post']['from']['id'])) {
            $user_id = $POST['edited_channel_post']['from']['id'];
        }

        file_put_contents($TEMP_DIR . '/' . $user_id . '.limit', $e);
        exit;
    }

    \Longman\TelegramBot\TelegramLog::error($e);
} catch (Longman\TelegramBot\Exception\TelegramLogException $e) {
    file_put_contents(
        'exception.log',
        '[' . date('Y-m-d H:i:s', time()) . ']' . "\n" . $e . "\n",
        FILE_APPEND
    );
}
