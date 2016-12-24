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

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DBHelper;
use Longman\TelegramBot\Game;
use Longman\TelegramBot\Entities\InlineKeyboardMarkup;
use Longman\TelegramBot\Entities\InlineKeyboardButton;

class StatsCommand extends UserCommand
{
    protected $name = 'stats';

    public function execute()
    {
        $message = $this->getMessage();
        $callback_query = $this->getUpdate()->getCallbackQuery();

        if ($message) {
            $chat_id = $message->getChat()->getId();
        } elseif ($callback_query) {
            $chat_id = $callback_query->getMessage()->getChat()->getId();

            $data_query = [];
            $data_query['callback_query_id'] = $callback_query->getId();
        }

        $text = '';
        //$text = '<b>Stats:</b>' . "\n";

        if (!empty($load = $this->getLoad())) {
            $text = $load;
        }

        $text .= ' <i>(1 minute)</i>';

        //---------------------------------------------

        $inline_keyboard = [
            [
                new InlineKeyboardButton(
                    [
                        'text' => 'Refresh 🔄',
                        'callback_data' => 'stats_refresh'
                    ]
                )
            ]
        ];

        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => new InlineKeyboardMarkup(['inline_keyboard' => $inline_keyboard]),
        ];

        if ($message) {
            return Request::sendMessage($data);
        } elseif ($callback_query) {
            Request::answerCallbackQuery($data_query);
            $data['message_id'] = $callback_query->getMessage()->getMessageId();
            return Request::editMessageText($data);
        }
    }

    private function getLoad()
    {
        $result = DBHelper::query('
        SELECT COUNT(*) as queries,
          (SELECT COUNT(*) FROM game WHERE `data` IS NOT NULL AND `updated_at` >= \'' . date('Y-m-d H:i:s', strtotime("-1 minutes")) . '\') as games,
          (SELECT COUNT(*) FROM user WHERE `updated_at` >= \'' . date('Y-m-d H:i:s', strtotime("-1 minutes")) . '\') as users
        FROM query WHERE `created_at` >= \'' . date('Y-m-d H:i:s', strtotime("-1 minutes")) . '\'
        ');

        if (is_numeric($result[0]['queries'])) {
            $requests = $result[0]['queries'];
        }

        if (is_numeric($result[0]['games'])) {
            $games = $result[0]['games'];
        }

        if (is_numeric($result[0]['users'])) {
            $users = $result[0]['users'];
        }

        if (!isset($games)) {
            $games = '?';
        }

        if (!isset($users)) {
            $users = '?';
        }

        $load_text = 'Unknown';

        if (isset($requests)) {
            if ($requests <= 60) {  // x1
                $load_text = 'Minimal';
            } elseif ($requests < 180) {  // x3
                $load_text = 'Low';
            } elseif ($requests < 300) {  // x5
                $load_text = 'Medium';
            } elseif ($requests < 420) {  // x7
                $load_text = 'High';
            } elseif ($requests < 600) {  // x10
                $load_text = 'Very High';
            } else {
                $load_text = 'Extreme';
            }
        } else {
            $requests = '?';
        }

        return 'Load: <b>' . $load_text . "</b>\n" . 'Requests: <b>' . $requests . "</b>\nGames: <b>" . $games . "</b>\nUsers: <b>" . $users . "</b>\n";
    }
}
