<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2021 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot\Storage\Driver;

use Bot\Exception\BotException;
use Bot\Exception\StorageException;
use Longman\TelegramBot\DB;

/**
 * Pulls PDO connection from \Longman\TelegramBot\DB and redirects all calls to \Bot\Storage\MySQL
 */
class BotDB extends DB
{
    /**
     * Create table structure
     *
     * @return bool
     *
     * @throws StorageException
     */
    public static function createStructure(): bool
    {
        self::initializeStorage();

        return MySQL::createStructure();
    }

    /**
     * Initialize PDO connection
     *
     * @return bool
     *
     * @throws StorageException
     */
    public static function initializeStorage(): bool
    {
        return MySQL::initializeStorage(self::$pdo);
    }

    /**
     * Select data from database
     *
     * @param string $id
     *
     * @return array|bool
     *
     * @throws StorageException
     */
    public static function selectFromGame(string $id)
    {
        return MySQL::selectFromGame($id);
    }

    /**
     * Insert data to database
     *
     * @param string $id
     * @param array  $data
     *
     * @return bool
     *
     * @throws StorageException
     */
    public static function insertToGame(string $id, array $data): bool
    {
        return MySQL::insertToGame($id, $data);
    }

    /**
     * Delete data from storage
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws StorageException
     */
    public static function deleteFromGame(string $id): bool
    {
        return MySQL::deleteFromGame($id);
    }

    /**
     * Lock the row to prevent another process modifying it
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws BotException
     * @throws StorageException
     */
    public static function lockGame(string $id): bool
    {
        return MySQL::lockGame($id);
    }

    /**
     * Unlock the row after
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws StorageException
     */
    public static function unlockGame(string $id): bool
    {
        return MySQL::unlockGame($id);
    }

    /**
     * Select multiple data from the database
     *
     * @param int $time
     *
     * @return array|bool
     *
     * @throws StorageException
     */
    public static function listFromGame(int $time = 0)
    {
        return MySQL::listFromGame($time);
    }
}
