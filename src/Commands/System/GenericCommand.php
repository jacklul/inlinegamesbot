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

class GenericCommand extends SystemCommand
{
    protected $name = 'Generic';
    protected $description = 'Handles generic commands or is executed by default when a command is not found';

    public function execute()
    {
        $message = $this->getMessage();

        if ($message) {
            $command = trim($message->getCommand());

            if (strtolower(substr($command, 0, 5)) == 'reply') {
                if ($this->getTelegram()->isAdmin()) {
                    return $this->getTelegram()->executeCommand('reply', $this->update);
                } else {
                    return $this->getTelegram()->executeCommand('contact', $this->update);
                }
            }

            return $this->getTelegram()->executeCommand("help");
        }

        return Request::emptyResponse();
    }
}
