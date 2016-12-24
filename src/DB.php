<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * Copyright (c) 2016 Jack'lul <https://jacklul.com>
 *
 * This file is based on DB.php from TelegramBot package.
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
 *
 */

namespace Longman\TelegramBot;

use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\Chat;
use Longman\TelegramBot\Entities\ChosenInlineResult;
use Longman\TelegramBot\Entities\InlineQuery;
use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Entities\User;
use Longman\TelegramBot\Exception\TelegramException;

class DB
{
    static protected $mysql_credentials = [];
    static protected $pdo;
    static protected $table_prefix;
    static protected $telegram;

    public static function initialize(array $credentials, Telegram $telegram, $table_prefix = null, $encoding = 'utf8mb4')
    {
        if (empty($credentials)) {
            throw new TelegramException('MySQL credentials not provided!');
        }

        $dsn = 'mysql:host=' . $credentials['host'] . ';dbname=' . $credentials['database'];
        $options = [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $encoding];
        try {
            $pdo = new \PDO($dsn, $credentials['user'], $credentials['password'], $options);

            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
        } catch (\PDOException $e) {
            throw new TelegramException($e->getMessage());
        }

        self::$pdo = $pdo;
        self::$telegram = $telegram;
        self::$mysql_credentials = $credentials;
        self::$table_prefix = $table_prefix;

        self::defineTable();

        return self::$pdo;
    }

    public static function externalInitialize($external_pdo_connection, Telegram $telegram, $table_prefix = null)
    {
        if (empty($external_pdo_connection)) {
            throw new TelegramException('MySQL external connection not provided!');
        }

        self::$pdo = $external_pdo_connection;
        self::$telegram = $telegram;
        self::$mysql_credentials = null;
        self::$table_prefix = $table_prefix;

        self::defineTable();

        return self::$pdo;
    }

    protected static function defineTable()
    {
        // Set this in current session so that we can set foreign keys to NULL //DBHelper::query('SET FOREIGN_KEY_CHECKS=0');
        $no_key_checks = self::$pdo->prepare('SET FOREIGN_KEY_CHECKS=0');
        $no_key_checks->execute();

        if (!defined('TB_USER')) {
            define('TB_USER', self::$table_prefix.'user');
        }

        if (!defined('TB_CALLBACK_QUERY')) {
            define('TB_CALLBACK_QUERY', self::$table_prefix.'query');
        }

        if (!defined('TB_REQUEST')) {
            define('TB_REQUEST', self::$table_prefix.'request');
        }
    }

    public static function isDbConnected()
    {
        if (empty(self::$pdo)) {
            return false;
        } else {
            return true;
        }
    }

    public static function selectTelegramUpdate($limit = null)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        return false;
    }

    public static function selectMessages($limit = null)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        return false;
    }

    protected static function getTimestamp($time = null)
    {
        if (is_null($time)) {
            return date('Y-m-d H:i:s', time());
        }
        return date('Y-m-d H:i:s', $time);
    }

    public static function insertTelegramUpdate($id, $chat_id, $message_id, $inline_query_id, $chosen_inline_result_id, $callback_query_id)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        return true;
    }

    public static function insertUser(User $user, $date, Chat $chat = null)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        $user_id = $user->getId();
        $username = $user->getUsername();
        $first_name = $user->getFirstName();
        $last_name = $user->getLastName();

        try {
            //user table
            $sth1 = self::$pdo->prepare('INSERT INTO `' . TB_USER . '`
                (
                `id`, `username`, `first_name`, `last_name`, `created_at`, `updated_at`
                )
                VALUES (
                :id, :username, :first_name, :last_name, :date, :date
                )
                ON DUPLICATE KEY UPDATE `username`=:username, `first_name`=:first_name,
                `last_name`=:last_name, `updated_at`=:date
                ');

            $sth1->bindParam(':id', $user_id, \PDO::PARAM_INT);
            $sth1->bindParam(':username', $username, \PDO::PARAM_STR, 255);
            $sth1->bindParam(':first_name', $first_name, \PDO::PARAM_STR, 255);
            $sth1->bindParam(':last_name', $last_name, \PDO::PARAM_STR, 255);
            $sth1->bindParam(':date', $date, \PDO::PARAM_STR);

            return $sth1->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function insertChat(Chat $chat, $date, $migrate_to_chat_id = null)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        return true;
    }

    public static function insertRequest(Update &$update)
    {
        $update_id = $update->getUpdateId();
        if ($update->getUpdateType() == 'message') {
            $message = $update->getMessage();

            if (self::insertMessageRequest($message)) {
                $message_id = $message->getMessageId();
                $chat_id = $message->getChat()->getId();
                return self::insertTelegramUpdate($update_id, $chat_id, $message_id, null, null, null, null);
            }
        } elseif ($update->getUpdateType() == 'edited_message') {
            $edited_message = $update->getEditedMessage();

            if (self::insertEditedMessageRequest($edited_message)) {
                $chat_id = $edited_message->getChat()->getId();
                $edited_message_local_id = self::$pdo->lastInsertId();
                return self::insertTelegramUpdate($update_id, $chat_id, null, null, null, null, $edited_message_local_id);
            }
        } elseif ($update->getUpdateType() == 'inline_query') {
            $inline_query = $update->getInlineQuery();

            if (self::insertInlineQueryRequest($inline_query)) {
                $inline_query_id = $inline_query->getId();
                return self::insertTelegramUpdate($update_id, null, null, $inline_query_id, null, null, null);
            }
        } elseif ($update->getUpdateType() == 'chosen_inline_result') {
            $chosen_inline_result = $update->getChosenInlineResult();

            if (self::insertChosenInlineResultRequest($chosen_inline_result)) {
                $chosen_inline_result_local_id = self::$pdo->lastInsertId();
                return self::insertTelegramUpdate($update_id, null, null, null, $chosen_inline_result_local_id, null, null);
            }
        } elseif ($update->getUpdateType() == 'callback_query') {
            $callback_query = $update->getCallbackQuery();

            if (self::insertCallbackQueryRequest($callback_query)) {
                $callback_query_id = $callback_query->getId();
                return self::insertTelegramUpdate($update_id, null, null, null, null, $callback_query_id, null);
            }
        }

        return false;
    }

    public static function insertInlineQueryRequest(InlineQuery &$inline_query)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        $date = self::getTimestamp(time());
        $from = $inline_query->getFrom();
        $user_id = null;
        if (is_object($from)) {
            self::insertUser($from, $date);
        }

        return true;
    }

    public static function insertChosenInlineResultRequest(ChosenInlineResult &$chosen_inline_result)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        $date = self::getTimestamp(time());
        $from = $chosen_inline_result->getFrom();
        $user_id = null;
        if (is_object($from)) {
            self::insertUser($from, $date);
        }

        return true;
    }

    public static function insertCallbackQueryRequest(CallbackQuery &$callback_query)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $query = 'INSERT IGNORE INTO `' . TB_CALLBACK_QUERY . '`
                (
                `id`, `user_id`, `inline_message_id`, `data`, `created_at`
                )
                VALUES (
                :callback_query_id, :user_id, :inline_message_id, :data, :created_at
                )';

            $sth_insert_callback_query = self::$pdo->prepare($query);

            $date = self::getTimestamp(time());
            $callback_query_id = (int) $callback_query->getId();
            $from = $callback_query->getFrom();
            $user_id = null;
            if (is_object($from)) {
                $user_id = $from->getId();
                self::insertUser($from, $date);
            }

            $inline_message_id = $callback_query->getInlineMessageId();
            $data = $callback_query->getData();

            $sth_insert_callback_query->bindParam(':callback_query_id', $callback_query_id, \PDO::PARAM_INT);
            $sth_insert_callback_query->bindParam(':user_id', $user_id, \PDO::PARAM_INT);
            $sth_insert_callback_query->bindParam(':inline_message_id', $inline_message_id, \PDO::PARAM_STR);
            $sth_insert_callback_query->bindParam(':data', $data, \PDO::PARAM_STR);
            $sth_insert_callback_query->bindParam(':created_at', $date, \PDO::PARAM_STR);

            return $sth_insert_callback_query->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function insertMessageRequest(Message &$message)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        $from = $message->getFrom();
        $chat = $message->getChat();

        if ($from->getId() == 201653790) {
            return true;
        }

        $date = self::getTimestamp($message->getDate());

        //Insert user and the relation with the chat
        self::insertUser($from, $date, $chat);

        return true;
    }

    public static function insertEditedMessageRequest(Message &$edited_message)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        $date = self::getTimestamp(time());
        $from = $edited_message->getFrom();
        $user_id = null;
        if (is_object($from)) {
            self::insertUser($from, $date);
        }

        return true;
    }

    public static function selectChats(
        $select_groups = false,
        $select_super_groups = false,
        $select_users = true,
        $date_from = null,
        $date_to = null,
        $chat_id = null,
        $text = null
    ) {
        if (!self::isDbConnected()) {
            return false;
        }

        if (!$select_groups & !$select_users & !$select_super_groups) {
            return false;
        }

        try {
            $query = 'SELECT *, `id` AS `chat_id` FROM ' . TB_USER;

            //Building parts of query
            $where = [];
            $tokens = [];

            if (! is_null($date_from)) {
                $where[] = TB_USER . '.`updated_at` >= :date_from';
                $tokens[':date_from'] = $date_from;
            }

            if (! is_null($date_to)) {
                $where[] = TB_USER . '.`updated_at` <= :date_to';
                $tokens[':date_to'] = $date_to;
            }

            if (! is_null($chat_id)) {
                $where[] = TB_USER . '.`id` = :chat_id';
                $tokens[':chat_id'] = $chat_id;
            }

            if (! is_null($text)) {
                if ($select_users) {
                    $where[] = '(LOWER(' . TB_USER . '.`first_name`) LIKE :text OR LOWER(' . TB_USER . '.`last_name`) LIKE :text OR LOWER(' . TB_USER . '.`username`) LIKE :text)';
                }

                $tokens[':text'] = '%'.strtolower($text).'%';
            }

            $a = 0;
            foreach ($where as $part) {
                if ($a) {
                    $query .= ' AND ' . $part;
                } else {
                    $query .= ' WHERE ' . $part;
                    ++$a;
                }
            }

            $query .= ' ORDER BY `updated_at` ASC';

            $sth = self::$pdo->prepare($query);
            $sth->execute($tokens);

            $result = $sth->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new TelegramException($e->getMessage());
        }

        return $result;
    }

    public static function getOutgoingRequestCount()
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sth = self::$pdo->prepare('SELECT COUNT(*) AS i FROM `' . TB_REQUEST . '`
                WHERE `created_at` = :date
                ');

            $date = self::getTimestamp();

            $sth->bindParam(':date', $date, \PDO::PARAM_STR);

            $result = $sth->execute();
            if ($result) {
                $result = $sth->fetchAll(\PDO::FETCH_ASSOC);
                return $result[0]['i'];
            }

            return false;
        } catch (\Exception $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function insertOutgoingRequest($method)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            $sth = self::$pdo->prepare('INSERT INTO `' . TB_REQUEST . '`
                (
                `method`, `created_at`
                )
                VALUES (
                :method, :date
                )
                ');

            $created_at = self::getTimestamp();

            $sth->bindParam(':method', $method, \PDO::PARAM_INT);
            $sth->bindParam(':date', $created_at, \PDO::PARAM_STR);

            return $sth->execute();
        } catch (\Exception $e) {
            throw new TelegramException($e->getMessage());
        }
    }
}
