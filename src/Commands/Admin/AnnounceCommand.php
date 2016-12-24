<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * Copyright (c) 2016 Jack'lul <https://jacklul.com>
 *
 * This file is based on SendtoallCommand.php from TelegramBot package.
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
use Longman\TelegramBot\DB;

class AnnounceCommand extends AdminCommand
{
    protected $name = 'announce';
    protected $description = 'Send the message to all active bot users';
    protected $usage = '/announce send <message> (/announce <message> shows preview)';
    protected $need_mysql = true;

    public function execute()
    {
        $message = $this->getMessage();

        $chat_id = $message->getChat()->getId();
        $text = $message->getText(true);

        if (!$message->getChat()->isPrivateChat()) {
            $data['text'] = "This command cannot be used in groups.";
            return Request::sendMessage($data);
        }

        if ($text === '') {
            $text = 'Write the message to send: /' . $this->name . ' <message>';
        } else {
            if (substr($text, 0, 4) === "send") {
                set_time_limit(300);

                Request::sendMessage(['chat_id' => $chat_id, 'text' => 'Sending, please wait...']);

                $text = substr($text, 5);

                $results = DB::selectChats(
                    false, //Select groups (group chat)
                    false, //Select supergroups (super group chat)
                    true, //Select users (single chat)
                    date('Y-m-d H:i:s', strtotime("-30 days")), //'yyyy-mm-dd hh:mm:ss' date range from
                    null, //'yyyy-mm-dd hh:mm:ss' date range to
                    null, //Specific chat_id to select
                    null  //Text to search in user/group name
                );

                $i = 0;
                $tot = 0;
                $fail = 0;

                $sentto = '';
                foreach ($results as $result) {
                    $result = Request::sendMessage(['chat_id' => $result['chat_id'], 'text' => $text, 'disable_web_page_preview' => true, 'parse_mode' => 'HTML']);
                    ++$i;

                    if ($result->isOk()) {
                        $ServerResponse = $result->getResult();
                        $chat = $ServerResponse->getChat();

                        if ($chat->isPrivateChat()) {
                            $name = $chat->getFirstName();
                        } else {
                            $name = $chat->getTitle();
                        }

                        if (!empty($sentto)) {
                            $sentto .= ', ';
                        }

                        $sentto .= $name;
                    } else {
                        ++$fail;
                    }

                    ++$tot;

                    if ($i >= 30) {
                        sleep(1);
                        $i = 0;
                    }
                }

                if ($tot > 0) {
                    $text = 'Delivered: ' . ($tot - $fail) . '/' . $tot . "\nRecipients: " . $sentto;
                } else {
                    $text = 'No users or chats found...';
                }
            } else {
                Request::sendMessage(['chat_id' => $chat_id, 'text' => $text, 'disable_web_page_preview' => true, 'parse_mode' => 'HTML']);
                $text = 'To send it use: /' . $this->name . ' send <message>';
            }
        }

        $data = [
            'chat_id' => $chat_id,
            'text'    => $text
        ];

        return Request::sendMessage($data);
    }
}
