<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Storage;

use Bot\Exception\BotException;
use Bot\Helper\Debug;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\DB;
use PDO;
use PDOException;

/**
 * Class BotDB
 *
 * Uses database from Longman\TelegramBot\DB
 *
 * @package Bot\Storage
 */
class BotDB extends DB
{
    /**
     * SQL to create database structure
     *
     * @var string
     */
    private static $structure = 'CREATE TABLE IF NOT EXISTS `storage` (
        `id` CHAR(100) COMMENT "Unique identifier for this entry",
        `data` TEXT NOT NULL) COMMENT "Stored data",
        `created_at` timestamp NULL DEFAULT NULL) COMMENT "Entry creation date",
        `updated_at` timestamp NULL DEFAULT NULL) COMMENT "Entry update date",

        PRIMARY KEY (`id`)
    );';

    /**
     * Initialize PDO connection and 'storage' table
     */
    public static function initializeStorage()
    {
        if (!defined('TB_STORAGE')) {
            define('TB_STORAGE', self::$table_prefix . 'storage');

            if (!file_exists($structure_check = VAR_PATH . '/db_structure_created')) {
                Debug::log('Creating database structure...');

                try {
                    if ($result = self::$pdo->query(self::$structure)) {
                        if (!is_dir(VAR_PATH)) {
                            mkdir(VAR_PATH, 0755, true);
                        }

                        touch($structure_check);
                    } else {
                        throw new BotException('Failed to create DB structure!');
                    }
                } catch (BotException $e) {
                    throw new TelegramException($e->getMessage());
                }
            }
        }

        return true;
    }

    /**
     * Select data from database
     *
     * @param $id
     *
     * @return array|bool|mixed
     * @throws BotException
     */
    public static function selectFromStorage($id)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        try {
            $sth = self::$pdo->prepare('
                SELECT * FROM `' . TB_STORAGE . '`
                WHERE `id` = :id
            ');

            $sth->bindParam(':id', $id, PDO::PARAM_STR);

            if ($result = $sth->execute()) {
                $result = $sth->fetchAll(PDO::FETCH_ASSOC);
                return isset($result[0]) ? json_decode($result[0]['data'], true): $result;
            } else {
                return $result;
            }
        } catch (PDOException $e) {
            throw new BotException($e->getMessage());
        }
    }

    /**
     * Insert data to database
     *
     * @param $id
     * @param $data
     *
     * @return bool
     * @throws BotException
     */
    public static function insertToStorage($id, $data)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        if (empty($data)) {
            throw new BotException('Data is empty!');
        }

        try {
            $sth = self::$pdo->prepare('
                INSERT INTO `' . TB_STORAGE . '`
                (`id`, `data`, `created_at`, `updated_at`)
                VALUES
                (:id, :data, :date, :date)
                ON DUPLICATE KEY UPDATE
                    `data`       = VALUES(`data`),
                    `updated_at` = VALUES(`updated_at`)
            ');

            $data = json_encode($data);
            $date = self::getTimestamp();

            $sth->bindParam(':id', $id, PDO::PARAM_STR);
            $sth->bindParam(':data', $data, PDO::PARAM_STR);
            $sth->bindParam(':date', $date, PDO::PARAM_STR);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new BotException($e->getMessage());
        }
    }

    /**
     * Delete data from storage
     *
     * @param $id
     *
     * @return array|bool|mixed
     * @throws BotException
     */
    public static function deleteFromStorage($id)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        try {
            $sth = self::$pdo->prepare('
                DELETE FROM `' . TB_STORAGE . '`
                WHERE `id` = :id
            ');

            $sth->bindParam(':id', $id, PDO::PARAM_STR);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new BotException($e->getMessage());
        }
    }

    /**
     * Lock the row to prevent another process modifying it
     *
     * @TODO this is really bad way of doing it currently, forces running this project just on one dyno... (they do not share filesystem)
     * @TODO maybe `self::$pdo->beginTransaction();` could be somehow used here?
     *
     * @param $id
     *
     * @return bool
     * @throws BotException
     */
    public static function lockStorage($id)
    {
        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        if (!is_dir(VAR_PATH . '/tmp')) {
            mkdir(VAR_PATH . '/tmp', 0755, true);
        }

        return flock(fopen(VAR_PATH . '/tmp/' . $id .  '.json', "a+"), LOCK_EX);
    }

    /**
     * Unlock the row after
     *
     * @TODO this is really bad way of doing it currently, forces running this project just on one dyno... (they do not share filesystem)
     * @TODO maybe `self::$pdo->commit();` could be somehow used here?
     *
     * @param $id
     *
     * @return bool
     * @throws BotException
     */
    public static function unlockStorage($id)
    {
        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        return flock(fopen(VAR_PATH . '/tmp/' . $id .  '.json', "a+"), LOCK_UN) && unlink(VAR_PATH . '/tmp/' . $id .  '.json');
    }

    /**
     * Select multiple data from the database
     *
     * @param int $time
     *
     * @return array|bool
     * @throws BotException
     */
    public static function listFromStorage($time = 0)
    {
        if (!is_numeric($time)) {
            throw new BotException('Time must be a number!');
        }

        if ($time >= 0) {
            $compare_sign = '<=';
        } else {
            $compare_sign = '>';
        }

        try {
            $sth = self::$pdo->prepare('
                SELECT * FROM `' . TB_STORAGE . '`
                WHERE `updated_at` ' . $compare_sign . ' :date
            ');

            $date = self::getTimestamp(strtotime('-' . abs($time) . ' seconds'));

            $sth->bindParam(':date', $date, PDO::PARAM_STR);

            if ($result = $sth->execute()) {
                return $sth->fetchAll(PDO::FETCH_ASSOC);
            } else {
                return $result;
            }
        } catch (PDOException $e) {
            throw new BotException($e->getMessage());
        }
    }
}
