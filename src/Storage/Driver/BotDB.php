<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Storage\Driver;

use Longman\TelegramBot\DB;

/**
 * Class BotDB
 *
 * Uses database from Longman\TelegramBot\DB
 *
 * @package Bot\Storage\Driver
 */
class BotDB extends DB
{
    /**
     * Initialize PDO connection
     *
     * @return bool
     */
    public static function initializeStorage()
    {
        return MySQL::initializeStorage(self::$pdo);
    }

    /**
     * Create table structure
     *
     * @return bool
     */
    public static function createStructure()
    {
        self::initializeStorage();
        return MySQL::createStructure();
    }

    /**
     * Select data from database
     *
     * @param $id
     *
     * @return array|bool
     */
    public static function selectFromGame($id)
    {
        return MySQL::selectFromGame($id);
    }

    /**
     * Insert data to database
     *
     * @param $id
     * @param $data
     *
     * @return bool
     */
    public static function insertToGame($id, $data)
    {
        return MySQL::insertToGame($id, $data);
    }

    /**
     * Delete data from storage
     *
     * @param $id
     *
     * @return bool
     */
    public static function deleteFromGame($id)
    {
        return MySQL::deleteFromGame($id);
    }

    /**
     * Lock the row to prevent another process modifying it
     *
     * @param $id
     *
     * @return bool
     */
    public static function lockGame($id)
    {
        return MySQL::lockGame($id);
    }

    /**
     * Unlock the row after
     *
     * @param $id
     *
     * @return bool
     */
    public static function unlockGame($id)
    {
        return MySQL::unlockGame($id);
    }

    /**
     * Select multiple data from the database
     *
     * @param $time
     *
     * @return array|bool
     */
    public static function listFromGame($time = 0)
    {
        return MySQL::listFromGame($time);
    }
}
