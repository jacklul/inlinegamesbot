<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * Copyright (c) 2016 Jack'lul <https://jacklul.com>
 *
 * This file is based on ConversationDB.php from TelegramBot package.
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

use Longman\TelegramBot\DB;
use Longman\TelegramBot\Exception\TelegramException;

class StateDB extends DB
{
    public static function initializeState()
    {
        if (!defined('TB_STATE')) {
            define('TB_STATE', self::$table_prefix . 'state');
        }
    }

    public static function selectState($user_id, $limit = null)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $query = 'SELECT * FROM `' . TB_STATE . '` ';
            $query .= 'WHERE `status` = :status ';
            $query .= 'AND `user_id` = :user_id ';

            if (!is_null($limit)) {
                $query .= ' LIMIT :limit';
            }

            $sth = self::$pdo->prepare($query);

            $active = 'active';

            $sth->bindParam(':status', $active, \PDO::PARAM_STR);
            $sth->bindParam(':user_id', $user_id, \PDO::PARAM_INT);
            $sth->bindParam(':limit', $limit, \PDO::PARAM_INT);
            $sth->execute();

            $results = $sth->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            throw new TelegramException($e->getMessage());
        }
        return $results;
    }

    public static function insertState($user_id, $command)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sth = self::$pdo->prepare('INSERT INTO `' . TB_STATE . '`
                (
                `status`, `user_id`, `command`, `notes`, `created_at`, `updated_at`
                )
                VALUES (
                :status, :user_id, :command, :notes, :date, :date
                )
               ');

            $active = 'active';
            $notes = '""';
            $created_at = self::getTimestamp();

            $sth->bindParam(':status', $active);
            $sth->bindParam(':command', $command);
            $sth->bindParam(':user_id', $user_id);
            $sth->bindParam(':notes', $notes);
            $sth->bindParam(':date', $created_at);

            $status = $sth->execute();
        } catch (\Exception $e) {
            throw new TelegramException($e->getMessage());
        }
        return $status;
    }

    public static function updateState(array $fields_values, array $where_fields_values)
    {
        return self::update(TB_STATE, $fields_values, $where_fields_values);
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
