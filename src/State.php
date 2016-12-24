<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * Copyright (c) 2016 Jack'lul <https://jacklul.com>
 *
 * This file is based on Conversation.php from TelegramBot package.
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

namespace Longman\TelegramBot;

class State
{
    protected $state = null;
    protected $protected_notes = null;
    public $notes = null;
    protected $user_id;
    protected $command;

    public function __construct($user_id, $command = null)
    {
        StateDB::initializeState();

        $this->user_id = $user_id;
        $this->command = $command;

        if (!$this->load() && $command !== null) {
            $this->start();
        }
    }

    protected function clear()
    {
        $this->state = null;
        $this->protected_notes = null;
        $this->notes = null;

        return true;
    }

    protected function load()
    {
        $state = StateDB::selectState($this->user_id, 1);
        if (isset($state[0])) {
            $this->state = $state[0];

            $this->command = $this->command ?: $this->state['command'];

            if ($this->command !== $this->state['command']) {
                $this->cancel();
                return false;
            }

            $this->protected_notes = json_decode($this->state['notes'], true);
            $this->notes = $this->protected_notes;
        }

        return $this->exists();
    }

    public function exists()
    {
        return ($this->state !== null);
    }

    protected function start()
    {
        if (!$this->exists() && $this->command) {
            if (StateDB::insertState($this->user_id, $this->command)) {
                return $this->load();
            }
        }

        return false;
    }

    public function stop()
    {
        return ($this->updateStatus('stopped') && $this->clear());
    }

    public function cancel()
    {
        return ($this->updateStatus('cancelled') && $this->clear());
    }

    protected function updateStatus($status)
    {
        if ($this->exists()) {
            $fields = ['status' => $status];
            $where  = [
                'id'  => $this->state['id'],
                'status'  => 'active',
                'user_id' => $this->user_id,
            ];

            if (StateDB::updateState($fields, $where)) {
                return true;
            }
        }

        return false;
    }

    public function update()
    {
        if ($this->exists()) {
            $fields = ['notes' => json_encode($this->notes)];
            $where = ['id'  => $this->state['id']];

            if (StateDB::updateState($fields, $where)) {
                return true;
            }
        }

        return false;
    }

    public function getCommand()
    {
        return $this->command;
    }
}
