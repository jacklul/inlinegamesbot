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

class ErrorreportCommand extends AdminCommand
{
    protected $name = 'errorreport';
    protected $description = 'Send error report to admins';
    protected $usage = '/errorreport';

    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getFrom()->getId();

        $failed = false;
        $botId = Request::getMe()->getResult()->getId();

        $data = [];
        $data['chat_id'] = $chat_id;

        foreach ($this->getTelegram()->getAdminList() as $admin) {
            if ($admin != $botId) {
                if (file_exists('logs/error.log') && time() >= (filemtime('logs/error.log') + 60)) {
                    if (!empty($result_php_error_id)) {
                        $data_admin = [
                            'chat_id' => $admin,
                            'caption' => 'PHP Error (' . date('Y-m-d', filemtime('logs/error.log')) . ' at ' . date('H:i:s', filemtime('logs/error.log')) . ')',
                            'document' => $result_php_error_id,
                        ];

                        $result_php_error = Request::sendDocument($data_admin);
                    } else {
                        $data_admin = [
                            'chat_id' => $admin,
                            'caption' => 'PHP Error (' . date('Y-m-d', filemtime('logs/error.log')) . ' at ' . date('H:i:s', filemtime('logs/error.log')) . ')',
                        ];

                        $result_php_error = Request::sendDocument($data_admin, realpath('logs/error.log'));
                        if ($result_php_error && $result_php_error->getResult() && $result_php_error->getResult()->getDocument() && empty($result_php_error_id)) {
                            $result_php_error_id = $result_php_error->getResult()->getDocument()->getFileId();
                        }
                    }
                }

                if (file_exists('logs/exception.log') && time() >= (filemtime('logs/exception.log') + 60)) {
                    if (!empty($result_library_error_id)) {
                        $data_admin = [
                            'chat_id' => $admin,
                            'caption' => 'Library Error (' . date('Y-m-d', filemtime('logs/exception.log')) . ' at ' . date('H:i:s', filemtime('logs/exception.log')) . ')',
                            'document' => $result_library_error_id,
                        ];

                        $result_library_error = Request::sendDocument($data_admin);
                    } else {
                        $data_admin = [
                            'chat_id' => $admin,
                            'caption' => 'Library Error (' . date('Y-m-d', filemtime('logs/exception.log')) . ' at ' . date('H:i:s', filemtime('logs/exception.log')) . ')',
                        ];

                        $result_library_error = Request::sendDocument($data_admin, realpath('logs/exception.log'));
                        if ($result_library_error && $result_library_error->getResult() && $result_library_error->getResult()->getDocument() && empty($result_library_error_id)) {
                            $result_library_error_id = $result_library_error->getResult()->getDocument()->getFileId();
                        }
                    }
                }
            }
        }

        if ($result_php_error && file_exists('logs/error.log')) {
            unlink('logs/error.log');
        } elseif (file_exists('logs/error.log') && time() >= (filemtime('logs/error.log') + 60)) {
            rename('logs/error.log', 'error_' . time() . '.log');
            $failed = true;
        }

        if ($result_library_error && file_exists('logs/exception.log')) {
            unlink('logs/exception.log');
        } elseif (file_exists('logs/exception.log') && time() >= (filemtime('logs/exception.log') + 60)) {
            rename('logs/exception.log', 'exception_' . time() . '.log');
            $failed = true;
        }

        if ($failed) {
            foreach ($this->getTelegram()->getAdminList() as $admin) {
                if ($admin != $botId) {
                    $data_admin = [
                        'chat_id' => $admin,
                        'text' => 'One of the error reports failed to send, log file was renamed - check bot directory!',
                    ];

                    Request::sendMessage($data_admin);
                }
            }
        }

        if ($result_php_error || $result_library_error) {
            $data['text'] = 'Logs were sent!';
        } else {
            $data['text'] = 'Logs were not sent!';
        }

        if (defined("STDIN")) {
            print ($data['text'] . "\n");
        }

        if ($chat_id != $botId) {
            return Request::sendMessage($data);
        } else {
            return Request::emptyResponse();
        }
    }
}
