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

namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DBHelper;
use Longman\TelegramBot\Game;
use Longman\TelegramBot\Strings;
use Longman\TelegramBot\Entities\InlineKeyboardMarkup;
use Longman\TelegramBot\Entities\InlineKeyboardButton;

class Cleangamescommand extends AdminCommand
{
    protected $name = 'cleangames';
    protected $description = 'Clean old games in DB';

    private $strings = [];

    /**
     This command needs to be run like every 1-5 minutes to edit inactive game's messages

     This can be simply done by copying webhook.php and adding
     '$telegram->setCustomInput($post);' in there

     with $post:

     $post = '{"update_id":1, "message":{"message_id":1,"from":{"id":1,"first_name":"Crontab","last_name":"","username":"'.$BOT_USERNAME.'"},"chat":{"id":1,"first_name":"Crontab","last_name":"","username":"'.$BOT_USERNAME.'","type":"private"},"date":'.time().',"text":"\/cleangames"}}';

     */

    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $text = trim($message->getText(true));

        $file = $this->getTelegram()->getDownloadPath() . '/' . $chat_id . '.lock_cleangames';
        $fh = fopen($file, 'w');
        if (!flock($fh, LOCK_EX | LOCK_NB) && time() <= filemtime($file) + 60) {
            return Request::emptyResponse();
        }

        if (is_numeric($text)) {
            $timelimit = $text;
        } else {
            $timelimit = 30;
        }

        set_time_limit($timelimit + 1);

        $starttime = time();

        $data = [];
        $data['chat_id'] = $chat_id;
        $data['parse_mode'] = 'HTML';
        $data['disable_web_page_preview'] = true;

        $gamesToClean = DBHelper::query('SELECT * FROM `game`
          WHERE `updated_at` < \'' . date('Y-m-d H:i:s', strtotime("-6 hours")) . '\'
           OR
          (`updated_at` < \'' . date('Y-m-d H:i:s', strtotime("-1 hours")) . '\' AND (`guest_id` IS NULL OR `data` IS NULL OR (`data` LIKE \'{\"language\":\"%\"}\' AND `data` NOT LIKE \'{\"language\":\"%\",%\')))
          ORDER BY `updated_at` ASC
          ');

        $data['text'] = 'Cleaning <b>'.count($gamesToClean).'</b> games now... (time limit: <b>'.$timelimit.'</b> seconds)';

        if ($chat_id != 201653790) {
            Request::sendMessage($data);
        }

        $games = Game::gameToId();
        foreach ($games as $cmd => $id) {
            $game_ids[$cmd] = $id;
        }

        $text = '';
        $requests = 0;
        $total = 0;
        $edited = 0;
        $counter = 0;

        if ($gamesToClean) {
            foreach ($gamesToClean as $game) {
                if (!empty($game_ids[$game['game']])) {
                    $language = json_decode($game['data'], true);
                    if (isset($language['language'])) {
                        $this->strings = Strings::load($game['game'], $language['language']);
                    } else {
                        $this->strings = Strings::load($game['game']);
                    }

                    $result = $this->editMessage($game['inline_message_id'], $game_ids[$game['game']]);
                    $requests++;

                    if ($result->isOk()) {
                        $edited++;
                        $counter++;
                    }

                    DBHelper::query('DELETE FROM `game` WHERE `id` = ' . $game['id']);

                    if ($counter >= 25) {
                        $counter = 0;
                        sleep(1);
                    }
                }

                $total++;

                if (time() > ($starttime + $timelimit)) {
                    $text .= 'Execution time limit reached!'."\n";
                    break;
                }
            }
        }

        $data['text'] = $text.'Cleaned <b>'.$total.'/'.count($gamesToClean).'</b> games, edited <b>'.$edited.'</b> messages.';

        if (defined("STDIN")) {
            print (strip_tags($data['text']) . "\n");
        }

        if ($chat_id != 201653790) {
            return Request::sendMessage($data);
        } else {
            return Request::emptyResponse();
        }
    }

    private function editMessage($message_id, $gameScriptId)
    {
        $inline_keyboard = [
            [
                new InlineKeyboardButton(
                    [
                        'text' => $this->strings['button_create'],
                        'callback_data' => $gameScriptId . "_new"
                    ]
                )
            ]
        ];

        $data = [];
        $data['inline_message_id'] = $message_id;
        $data['text'] = '<b>' . $this->strings['message_prefix'] . '</b>' . "\n\n" . '<i>' . $this->strings['state_session_not_available'] . '</i>';
        $data['parse_mode'] = 'HTML';
        $data['disable_web_page_preview'] = true;
        $data['reply_markup'] = new InlineKeyboardMarkup(['inline_keyboard' => $inline_keyboard]);

        return Request::editMessageText($data);
    }
}
