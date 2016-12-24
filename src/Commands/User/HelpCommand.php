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

class HelpCommand extends UserCommand
{
    protected $name = 'help';

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

            $temp_path = $this->getTelegram()->getDownloadPath();

            if (!$this->getTelegram()->isAdmin()) {
                if (file_exists($temp_path . '/' . $chat_id . '.lock_help') && time() < (filemtime($temp_path . '/' . $chat_id . '.lock_help') + 15)) {
                    $data_query['text'] = 'Please wait at least 15 seconds between refreshes!'."\n".'Spamming will result in a temporary block.';
                    $data_query['show_alert'] = true;
                    return Request::answerCallbackQuery($data_query);
                }

                touch($temp_path . '/' . $chat_id . '.lock_help');
            }
        }

        //---------------------------------------------

        $text = '';
        $text .= '👋 ';
        $text .= '<b>Hi!</b>' . "\n";
        $text .= 'To begin, start a message with \'@inlinegamesbot ...\' in any of your chats or click the <b>\'Play!\'</b> button and then select a chat. ' . "\n\n";
        $text .= '<i>This bot is open source, click \'Source Code\' button below to go to the Github page! Contributions are welcome!</i>' . "\n";

        //---------------------------------------------

        /*$text .= "\n";
        $text .= '🎲 ';
        $text .= '<b>Available games:</b>' . "\n";
        $text .= ' · Tic-Tac-Toe  [<a href="https://en.wikipedia.org/wiki/Tic-tac-toe">rules</a>]' . "\n";
        $text .= ' · Connect Four  [<a href="https://en.wikipedia.org/wiki/Connect_Four">rules</a>]' . "\n";
        $text .= ' · Rock-Paper-Scissors  [<a href="https://en.wikipedia.org/wiki/Rock-paper-scissors">rules</a>]' . "\n";
        $text .= ' · Checkers  [<a href="https://en.wikipedia.org/wiki/Straight_checkers">rules</a>]' . "\n";
        $text .= ' · Pool Checkers  [<a href="https://en.wikipedia.org/wiki/Pool_checkers">rules</a>]' . "\n";
        $text .= ' · Russian Roulette  [<a href="https://en.wikipedia.org/wiki/Russian_roulette">rules</a>]' . "\n";*/

        //---------------------------------------------

        /*$text .= "\n";
        $text .= '🕹 ';
        $text .= '<b>If you have noone to play with...</b>' . "\n";
        /*$text .= ' · Click the <b>\'Looking to play\'</b> button and message someone from the list or enter it ';

        if (!empty($mm = $this->getWaitingList())) {
            $text .= '('.$mm.')' . "\n";
        }*/

        /*$text .= ' · Join or create games in @inLineGamers group (unofficial, managed by @inLineGamer)'."\n";*/

        /*if (file_exists(__DIR__ . '/announcements.txt')) {
            $status = file_get_contents(__DIR__ . '/announcements.txt');
            if (!empty($status)) {
                $text .= "\n";
                $text .= '📢 ';
                $text .= '<b>Announcements:</b>' . "\n";
                $text .= $status;
                $text .= "\n";
            }
        }*/

        //$text .= "\n";
        //$text .= '💡 ';
        //$text .= '<b>Version:</b> ' . Game::getVersion() . '';

        /*if (!empty($load = $this->getLoad($this->getTelegram()->isAdmin()))) {
            $text .= "\n";
            $text .= $load;
        }*/

        //---------------------------------------------

        $inline_keyboard = [
            [
                new InlineKeyboardButton(
                    [
                        'text' => "Play! 🎲",
                        'switch_inline_query' => ''
                    ]
                ),
                /*new InlineKeyboardButton(
                    [
                        'text' => "Looking to play 🕹",
                        'callback_data' => 'waitinglist_start'
                    ]
                )*/
            ],
            [
                /*new InlineKeyboardButton(
                    [
                        'text' => 'Rate this bot ⭐',
                        'url' => 'https://telegram.me/storebot?start=' . $this->getTelegram()->getBotName()
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => 'Send feedback 📝',
                        'callback_data' => 'contact'
                    ]
                )*/
                new InlineKeyboardButton(
                    [
                        'text' => 'Rate ⭐ and Review 📝',
                        'url' => 'https://telegram.me/storebot?start=' . $this->getTelegram()->getBotName()
                    ]
                ),
            ],
            /*[
                new InlineKeyboardButton(
                    [
                        'text' => 'Refresh this message 🔄',
                        'callback_data' => 'help_refresh'
                    ]
                )
            ],*/
            [
                new InlineKeyboardButton(
                    [
                        'text' => "Source Code 🗒",
                        'url' => 'https://github.com/jacklul/inlinegamesbot/'
                    ]
                ),
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

    private function getLoad($admin = false)
    {
        if ($admin) {
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

            if (isset($games)) {
                if ($games == 1) {
                    $str = 'game';
                } else {
                    $str = 'games';
                }

                $games = $games . ' ' . $str;
            } else {
                $games = '? games';
            }

            if (isset($users)) {
                if ($users == 1) {
                    $str = 'user';
                } else {
                    $str = 'users';
                }

                $users = $users . ' ' . $str;
            } else {
                $users = '? users';
            }
        } else {
            $result = DBHelper::query('SELECT COUNT(*) AS i FROM `query` WHERE `created_at` >= \'' . date('Y-m-d H:i:s', strtotime("-1 minutes")) . '\'');

            if (is_numeric($result[0]['i'])) {
                $requests = $result[0]['i'];
            }
        }

        $load = '❓';
        $load_text = 'Unknown';
        $load_extra = '';

        if (isset($requests)) {
            if ($requests <= 60) {  // x1
                $load = '💙';
                $load_text = 'Minimal';
            } elseif ($requests < 180) {  // x3
                $load = '💚';
                $load_text = 'Low';
            } elseif ($requests < 300) {  // x5
                $load = '💛️';
                $load_text = 'Medium';
            } elseif ($requests < 420) {  // x7
                $load = '❤️️';
                $load_text = 'High';
            } elseif ($requests < 600) {  // x10
                $load = '❤️️';
                $load_text = 'Very High';
            } else {
                $load = '🔥️️';
                $load_text = 'Extreme';
            }

            if ($requests == 1) {
                $str = 'query';
            } else {
                $str = 'queries';
            }

            $requests = $requests . ' ' . $str;
        } else {
            $requests = '? requests';
        }

        if (!$admin) {
            return '📈 <b>Bot load:</b> ' . $load_text . ' ' . $load . $load_extra . "\n";
        } else {
            return '📈 <b>Bot load:</b> ' . $load_text . ' ' . $load . $load_extra . "\n" . '(' . $requests . ', ' . $games . ' and ' . $users . ')'. "\n";
        }
    }

    private function getWaitingList()
    {
        $result = DBHelper::query('SELECT COUNT(*) AS i FROM `waiting_list`');

        if (is_numeric($result[0]['i'])) {
            $mm_users = $result[0]['i'];
        }

        if (isset($mm_users) && $mm_users > 0) {
            if ($mm_users == 1) {
                $str = 'user';
            } elseif ($mm_users == 0) {
                $str = 'users';
                $mm_users = 'no';
            } else {
                $str = 'users';
            }

            return '<b>' . $mm_users . '</b> ' . $str . ' on the list';
        } else {
            return 'list is empty';
        }
    }
}
