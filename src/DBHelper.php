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

class DBHelper extends DB
{
    public static function query($query)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        try {
            self::$pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $sth = self::$pdo->prepare($query);

            $results = $sth->execute();
            if (!$results) {
                error_log('Failed query: ' . $query);
            }

            if ($sth->columnCount() > 0) {
                $results = $sth->fetchAll(\PDO::FETCH_ASSOC);
            } elseif ($sth->rowCount() == 0) {
                $results = false;
            }

            return $results;
        } catch (\Exception $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function quote($text)
    {
        return self::$pdo->quote($text);
    }

    public static function lastInsertId()
    {
        return self::$pdo->lastInsertId();
    }
}
