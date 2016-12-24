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
use Longman\TelegramBot\DBHelper;
use Longman\TelegramBot\Game;

class CallbackqueryCommand extends SystemCommand
{
    protected $name = 'callbackquery';
    protected $description = 'Reply to callback query';

    private $commands = [
        'help' => 'help',
        'waitinglist' => 'waitinglist',
        'contact' => 'contact',
        'reply' => 'contact',
        'sendto' => 'reply',
        'cancel' => 'cancel',
        'stats' => 'stats',
    ];

    public function execute()
    {
        $update = $this->getUpdate();
        $callback_query = $update->getCallbackQuery();
        $user_id = $callback_query->getFrom()->getId();
        $data = $callback_query->getData();

        $games = Game::idToGame();
        foreach ($games as $game => $cmd) {
            $this->commands[$game] = $cmd;
        }

        $command = explode('_', $data);
        $command = $command[0];

        $result = DBHelper::query('SELECT COUNT(*) AS i FROM `query` WHERE `user_id` = ' . $user_id .' AND `created_at` >= \'' . date('Y-m-d H:i:s', strtotime("-15 second")) . '\' ORDER BY `created_at` DESC');

        //if (!$this->getTelegram()->isAdmin($user_id) && isset($result[0]['i']) && $result[0]['i'] >= 13) {
        if (isset($result[0]['i']) && $result[0]['i'] == 13) {
            $data = [];
            $data['callback_query_id'] = $callback_query->getId();
            $data['text'] = 'You are sending too many requests in a short period of time!';
            $data['show_alert'] = true;
        } elseif (isset($result[0]['i']) && $result[0]['i'] > 15) {
            touch($this->getTelegram()->getDownloadPath() . '/' . $user_id . '.block');
            $data = [];
            $data['callback_query_id'] = $callback_query->getId();
            $data['text'] = 'You have been temporarily banned for flooding!';
            $data['show_alert'] = true;
        } elseif (isset($this->commands[$command]) && $this->getTelegram()->getCommandObject($this->commands[$command])) {
            return $this->getTelegram()->executeCommand($this->commands[$command], $update);
        } else {
            $data = [];
            $data['callback_query_id'] = $callback_query->getId();
            $data['text'] = 'Invalid request!';
            $data['show_alert'] = true;
        }

        return Request::answerCallbackQuery($data);
    }
}
