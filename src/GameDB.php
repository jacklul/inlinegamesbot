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

use Longman\TelegramBot\Exception\TelegramException;

class GameDB extends DB
{
    public static function initializeGameDb()
    {
        if (!defined('TB_GAME')) {
            define('TB_GAME', self::$table_prefix . 'game');
        }

        self::$pdo->setAttribute(\PDO::ATTR_TIMEOUT, 1);
    }

    public static function selectGame($gameId)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        self::initializeGameDb();

        try {
            $sth = self::$pdo->prepare('SELECT * FROM `' . TB_GAME . '`
                WHERE `id` = :gameId
                ');

            $sth->bindParam(':gameId', $gameId, \PDO::PARAM_INT);

            $result = $sth->execute();
            if ($result) {
                return $sth->fetchAll(\PDO::FETCH_ASSOC);
            }

            return false;
        } catch (\Exception $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function selectUser($user_id)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        self::initializeGameDb();

        try {
            $sth = self::$pdo->prepare('SELECT * FROM `' . TB_USER . '`
                WHERE `id` = :user_id
                ');

            $sth->bindParam(':user_id', $user_id, \PDO::PARAM_INT);

            $result = $sth->execute();
            if ($result) {
                return $sth->fetchAll(\PDO::FETCH_ASSOC);
            }

            return false;
        } catch (\Exception $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function insertGame($user_id, $game_name, $message_id)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        self::initializeGameDb();

        try {
            $sth = self::$pdo->prepare('INSERT INTO `' . TB_GAME . '`
                (
                `host_id`, `game`, `inline_message_id`, `created_at`, `updated_at`
                )
                VALUES (
                :user_id, :game, :message_id, :date, :date
                )
                ');

            $created_at = self::getTimestamp();

            $sth->bindParam(':user_id', $user_id, \PDO::PARAM_INT);
            $sth->bindParam(':game', $game_name, \PDO::PARAM_STR);
            $sth->bindParam(':message_id', $message_id, \PDO::PARAM_STR);
            $sth->bindParam(':date', $created_at, \PDO::PARAM_STR);

            $result = $sth->execute();
            if ($result) {
                return self::$pdo->lastInsertId();
            }

            return false;
        } catch (\Exception $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function updateGame(array $fields_values, array $where_fields_values)
    {
        self::initializeGameDb();
        return self::update(TB_GAME, $fields_values, $where_fields_values);
    }

    public static function getCallbackQueryCount($user_id, $data, $date)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        self::initializeGameDb();

        try {
            $sth = self::$pdo->prepare('SELECT COUNT(*) AS i FROM `' . TB_CALLBACK_QUERY . '`
                WHERE `user_id` = :user_id AND `data` = :data AND `created_at` >= :date
                ORDER BY `created_at` DESC
                ');

            $sth->bindParam(':user_id', $user_id, \PDO::PARAM_INT);
            $sth->bindParam(':data', $data, \PDO::PARAM_STR);
            $sth->bindParam(':date', $date, \PDO::PARAM_STR);

            $result = $sth->execute();
            if ($result) {
                return $sth->fetchAll(\PDO::FETCH_ASSOC);
            }

            return false;
        } catch (\Exception $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function update($table, array $fields_values, array $where_fields_values)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        $fields_values['updated_at'] = self::getTimestamp();

        $update = '';
        $tokens = [];
        $tokens_counter = 0;
        $a = 0;
        foreach ($fields_values as $field => $value) {
            if ($a) {
                $update .= ', ';
            }
            ++$a;
            ++$tokens_counter;
            $update .= '`' . $field . '` = :' . $tokens_counter;
            $tokens[':' . $tokens_counter] = $value;
        }

        $a = 0;
        $where  = '';
        foreach ($where_fields_values as $field => $value) {
            if ($a) {
                $where .= ' AND ';
            } else {
                ++$a;
                $where .= 'WHERE ';
            }
            ++$tokens_counter;
            $where .= '`' . $field .'`= :' . $tokens_counter;
            $tokens[':' . $tokens_counter] = $value;
        }

        $query = 'UPDATE `' . $table . '` SET ' . $update . ' ' . $where;
        try {
            $sth = self::$pdo->prepare($query);

            $status = $sth->execute($tokens);
        } catch (\Exception $e) {
            throw new TelegramException($e->getMessage());
        }

        return $status;
    }
}
