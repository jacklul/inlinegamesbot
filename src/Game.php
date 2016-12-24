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

namespace Longman\TelegramBot;

class Game
{
    public static $version = "1.0.0";

    private static $game_to_id = [
        'tictactoe' => 'g1',
        'connectfour' => 'g2',
        'checkers' => 'g3',
        'rockpaperscissors' => 'g4',
        'poolcheckers' => 'g5',
        'russianroulette' => 'g6',

        'testgame' => 'g99',
    ];

    private static $id_to_game = [
        'g1' => 'tictactoe',
        'g2' => 'connectfour',
        'g3' => 'checkers',
        'g4' => 'rockpaperscissors',
        'g5' => 'poolcheckers',
        'g6' => 'russianroulette',

        'g99' => 'testgame',
    ];

    private static $id_to_name = [
        'g1' => 'Tic-Tac-Toe',
        'g2' => 'Connect Four',
        'g3' => 'Checkers',
        'g4' => 'Rock-Paper-Scissors',
        'g5' => 'Pool Checkers',
        'g6' => 'Russian Roulette',
    ];

    public static function getVersion()
    {
        return self::$version;
    }

    public static function gameToId()
    {
        return self::$game_to_id;
    }

    public static function idToGame()
    {
        return self::$id_to_game;
    }

    public static function idToName()
    {
        return self::$id_to_name;
    }

    public static function getGame($gameId)
    {
        return GameDB::selectGame($gameId);
    }

    public static function setHost($gameId, $user_id)
    {
        return GameDB::updateGame(['host_id' => $user_id], ['id' => $gameId]);
    }

    public static function setGuest($gameId, $user_id)
    {
        return GameDB::updateGame(['guest_id' => $user_id], ['id' => $gameId]);
    }

    public static function migrateHost($gameId, $user_id)
    {
        return GameDB::updateGame(['host_id' => $user_id, 'guest_id' => null], ['id' => $gameId]);
    }

    public static function newGame($user_id, $game_name, $message_id)
    {
        return GameDB::insertGame($user_id, $game_name, $message_id);
    }

    public static function updateGame($gameId, $data)
    {
        return GameDB::updateGame(['data' => $data], ['id' => $gameId]);
    }

    public static function getUser($user_id)
    {
        $user = GameDB::selectUser($user_id);

        if ($user) {
            $user_fullname = $user[0]['first_name'];

            if (!empty($user[0]['last_name'])) {
                $user_fullname .= ' ' . $user[0]['last_name'];
            }

            if (!empty($user[0]['username'])) {
                $user_username = '@' . $user[0]['username'];
            } else {
                $user_username = '@' . $user_fullname;
            }

            return [
                'fullname' => $user_fullname,
                'mention' => $user_username
            ];
        }

        return [
            'fullname' => '',
            'mention' => ''
        ];
    }

    public static function getCallbackQueryCount($user_id, $data, $date)
    {
        return GameDB::getCallbackQueryCount($user_id, $data, $date);
    }
}
