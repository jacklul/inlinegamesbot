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

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\State;
use Longman\TelegramBot\Entities\ReplyKeyboardHide;

class CancelCommand extends SystemCommand
{
    protected $name = 'cancel';
    protected $need_mysql = true;

    public function execute()
    {
        $message = $this->getMessage();
        $callback_query = $this->getUpdate()->getCallbackQuery();

        if ($message) {
            $chat_id = $message->getChat()->getId();
            $user_id = $message->getFrom()->getId();
        } elseif ($callback_query) {
            $chat_id = $callback_query->getMessage()->getChat()->getId();
            $user_id = $callback_query->getFrom()->getId();

            $data_query = [];
            $data_query['callback_query_id'] = $callback_query->getId();
        }

        $text = '<b>Nothing to cancel.</b>';

        $state = new State($user_id);

        if ($conversation_command = $state->getCommand()) {
            $state->cancel();
            $text = '<b>Operation cancelled!</b>';

            if ($callback_query) {
                $data_edit = [];
                $data_edit['chat_id'] = $chat_id;
                $data_edit['message_id'] = $callback_query->getMessage()->getMessageId();
                $data_edit['reply_markup'] = false;
                $data_edit['text'] = $text;
                $data_edit['parse_mode'] = 'HTML';
                Request::editMessageText($data_edit);

                $data_query['text'] = 'Operation cancelled!';
                return Request::answerCallbackQuery($data_query);
            }
        } else {
            if ($callback_query) {
                $data_query['text'] = 'Nothing to cancel.';
                $data_query['show_alert'] = true;
                return Request::answerCallbackQuery($data_query);
            }
        }

        return Request::sendMessage([
            'reply_markup' => new ReplyKeyboardHide(['selective' => true]),
            'chat_id'      => $chat_id,
            'text'         => $text,
            'parse_mode'   => 'HTML'
        ]);
    }
}
