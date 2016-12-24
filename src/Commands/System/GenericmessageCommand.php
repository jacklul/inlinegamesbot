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
use Longman\TelegramBot\State;
use Longman\TelegramBot\Request;

class GenericmessageCommand extends SystemCommand
{
    protected $name = 'Genericmessage';
    protected $description = 'Handle generic message';
    protected $need_mysql = true;

    public function executeNoDb()
    {
        return Request::emptyResponse();
    }

    public function execute()
    {
        $message = $this->getMessage();

        if ($message) {
            $state = new State($this->getMessage()->getFrom()->getId());

            if ($state->exists() && ($command = $state->getCommand()) && ((isset($state->notes['no_input']) && $state->notes['no_input'] != true) || !isset($state->notes['no_input']))) {
                return $this->getTelegram()->executeCommand($command);
            }

            if (strpos($message->getText(true), 'start a new session')) {
                return Request::sendMessage(
                    [
                        'chat_id' => $message->getChat()->getId(),
                        'text' => 'I\'m just a bot, I can\'t play with you. 😆'
                    ]
                );
            } else {
                return Request::sendMessage(
                    [
                        'chat_id' => $message->getChat()->getId(),
                        'text' => 'I\'m just a bot, I can\'t chat with you. 😆'
                    ]
                );
            }
        }

        return Request::emptyResponse();
    }
}
