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

set_time_limit(5);

$TEMP_DIR = __DIR__ . '/tmp/';
$MESSAGE_LIMITS = 'Telegram API limits reached - unable to handle this request!';

if (defined("STDIN")) {
    unset($argv[0]);
    $POST = implode(' ', $argv);
} else {
    exit(0);
}

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

if (!is_null($user_id) && file_exists($TEMP_DIR . '/' . $user_id . '.block') && time() < (filemtime($TEMP_DIR . '/' . $user_id . '.block') + 60)) {
    exit(0);
}

if (!is_null($user_id)) {
    if (file_exists($TEMP_DIR . '/' . $user_id . '.limit')) {
        $reply = file_get_contents($TEMP_DIR . '/' . $user_id . '.limit');

        if (preg_match("/retry_after\"\:(.*)\}\}/", $reply, $matches)) {
            $time = $matches[1] - (time() - filemtime($TEMP_DIR . '/' . $user_id . '.limit')) + 1;
        }

        if ((!empty($time) && $time > 0) || (time() < (filemtime($TEMP_DIR . '/' . $user_id . '.limit') + 60))) {
            if (isset($POST['callback_query']['id'])) {
                $REPLY = [
                    'method' => 'answerCallbackQuery',
                    'callback_query_id' => $POST['callback_query']['id'],
                    'text' => $MESSAGE_LIMITS . (!empty($time) ? "\n\n" . "Wait $time seconds!":''),
                    'show_alert' => true,
                ];
            } elseif (isset($POST['inline_query']['id'])) {
                $REPLY = [
                    'method' => 'answerInlineQuery',
                    'inline_query_id' => $POST['inline_query']['id'],
                    'switch_pm_text' => $MESSAGE_LIMITS . (!empty($time) ? "\n\n" . "Wait $time seconds!":''),
                    'inline_query_offset' => null,
                    'cache_time' => 60,
                    'results' => [],
                ];
            } elseif (isset($POST['message']['chat']['id'])) {
                $REPLY = [
                    'method' => 'sendMessage',
                    'chat_id' => $POST['message']['chat']['id'],
                    'text' => $MESSAGE_LIMITS . (!empty($time) ? "\n\n" . "Wait $time seconds!":''),
                ];
            }

            if (!empty($REPLY)) {
                echo json_encode($REPLY, true);
            }

            exit(0);
        }
    }
}

exit(1);