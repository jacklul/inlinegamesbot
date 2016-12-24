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
use Longman\TelegramBot\Game;

class ChoseninlineresultCommand extends SystemCommand
{
    protected $name = 'choseninlineresult';
    protected $description = 'Chosen inline result';

    public function execute()
    {
        $update = $this->getUpdate();
        $chosen_inline_result = $update->getChosenInlineResult();
        $resultId = $chosen_inline_result->getResultId();

        $commands = [];
        $games = Game::idToGame();
        foreach ($games as $game => $cmd) {
            $commands[$game] = $cmd;
        }

        $command = explode('_', $resultId);
        $command = $command[0];

        if (isset($commands[$command])) {
            return $this->getTelegram()->executeCommand($commands[$command], $update);
        }

        return Request::emptyResponse();
    }
}
