<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * Copyright (c) 2016 Jack'lul <https://jacklul.com>
 *
 * This file is based on Botan.php from TelegramBot package.
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

use Longman\TelegramBot\Exception\TelegramException;

class Botan
{
    protected static $track_url = 'https://api.botan.io/track?token=#TOKEN&uid=#UID&name=#NAME';
    protected static $shortener_url = 'https://api.botan.io/s/?token=#TOKEN&user_ids=#UID&url=#URL';
    protected static $token = '';
    public static $command = '';

    public static function initializeBotan($token)
    {
        if (empty($token) || !is_string($token)) {
            throw new TelegramException('Botan token should be a string!');
        }

        self::$token = $token;
        BotanDB::initializeBotanDb();
    }

    public static function lock($command = '')
    {
        if (empty(self::$command)) {
            self::$command = $command;
        }
    }

    public static function track($input, $command = '')
    {
        if (empty(self::$token) || $command != self::$command) {
            return false;
        }

        if (empty($input)) {
            throw new TelegramException('Input is empty!');
        }

        self::$command = '';

        $obj = json_decode($input, true);
        if (isset($obj['callback_query'])) {
            $data = $obj['callback_query'];
            $id_to_name = Game::idToName();

            $callback_data = explode('_', $obj['callback_query']['data']);
            if (!empty($id_to_name[$callback_data[0]]) && ($callback_data[1] == 'start')) {
                $event_name = $id_to_name[$callback_data[0]];
            }
        }

        if (empty($event_name)) {
            return false;
        }

        $uid = $data['from']['id'];
        $request = str_replace(
            ['#TOKEN', '#UID', '#NAME'],
            [self::$token, $uid, urlencode($event_name)],
            self::$track_url
        );

        $options = [
            'http' => [
                'header'  => 'Content-Type: application/json',
                'method'  => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($request, false, $context);
        $responseData = json_decode($response, true);

        if ($responseData['status'] !== 'accepted') {
            TelegramLog::debug('Botan.io API replied with error: ' . $response);
        }

        return $responseData;
    }

    public static function shortenUrl($url, $user_id)
    {
        if (empty(self::$token)) {
            return $url;
        }

        if (empty($user_id)) {
            throw new TelegramException('User id is empty!');
        }

        return $url;
    }
}
