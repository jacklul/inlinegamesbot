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
use Longman\TelegramBot\State;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DBHelper;
use Longman\TelegramBot\Entities\InlineKeyboardMarkup;
use Longman\TelegramBot\Entities\InlineKeyboardButton;

class AnnouncementCommand extends AdminCommand
{
    protected $name = 'announcement';
    protected $description = 'Write announcement';
    protected $usage = '/announcement <text>';

    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $text = trim($message->getText(true));

        if ($text === '') {
            $text = "Current:\n\n" . file_get_contents("app/src/Commands/User/announcements.txt");
        } elseif ($text == 'del' || $text == 'delete') {
            file_put_contents("app/src/Commands/User/announcements.txt", "");
            $text = "Announcement deleted!";
        } else {
            file_put_contents("app/src/Commands/User/announcements.txt", $text);
            $text = "New announcement set!";
        }

        $data = [];
        $data['chat_id'] = $chat_id;
        $data['text'] = $text;
        $data['parse_mode'] = 'HTML';
        $data['disable_web_page_preview'] = true;

        return Request::sendMessage($data);
    }
}
