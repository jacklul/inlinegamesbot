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

use AD7six\Dsn\Dsn;
use Bot\Exception\StorageException;
use Bot\Helper\Debug;
use Bot\Helper\LockFile;
use Longman\TelegramBot\TelegramLog;
use PDO;
use PDOException;

/**
 * Class MySQL
 *
 * @package Bot\Storage\Driver
 */
class MySQL
{
    /**
     * PDO object
     *
     * @var PDO
     */
    private static $pdo;

    /**
     * Lock file object
     *
     * @var LockFile
     */
    private static $lock;

    /**
     * SQL to create database structure
     *
     * @var string
     */
    private static $structure = 'CREATE TABLE IF NOT EXISTS `game` (
        `id` CHAR(255) COMMENT "Unique identifier for this entry",
        `data` TEXT NOT NULL COMMENT "Stored data",
        `created_at` timestamp NULL DEFAULT NULL COMMENT "Entry creation date",
        `updated_at` timestamp NULL DEFAULT NULL COMMENT "Entry update date",

        PRIMARY KEY (`id`)
    );';

    /**
     * Initialize PDO connection
     */
    public static function initializeStorage($pdo = null)
    {
        if (self::isDbConnected()) {
            return true;
        }

        if (!defined('TB_GAME')) {
            define('TB_GAME', 'game');
        }

        if ($pdo === null) {
            $dsn = Dsn::parse(getenv('DATABASE_URL'));
            $dsn = $dsn->toArray();

            try {
                self::$pdo = new PDO('mysql:' . 'host=' . $dsn['host'] . ';port=' . $dsn['port'] . ';dbname=' . $dsn['database'], $dsn['user'], $dsn['pass']);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
            } catch (PDOException $e) {
                if ($e->getCode() != 7) {   // ignore connection failure
                    TelegramLog::error($e->getMessage());
                }

                Debug::print('Connection to the database failed');
                return false;
            }
        } else {
            self::$pdo = $pdo;
        }

        return true;
    }

    /**
     * Create table structure
     *
     * @return bool
     * @throws StorageException
     */
    public static function createStructure()
    {
        if (!self::isDbConnected()) {
            self::initializeStorage();
        }

        if (!self::$pdo->query(self::$structure)) {
            throw new StorageException('Failed to create DB structure!');
        }

        return true;
    }

    /**
     * Check if database connection has been created
     *
     * @return bool
     */
    public static function isDbConnected()
    {
        return self::$pdo !== null;
    }

    /**
     * Select data from database
     *
     * @param $id
     *
     * @return array|bool
     * @throws StorageException
     */
    public static function selectFromGame($id)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        try {
            $sth = self::$pdo->prepare(
                '
                SELECT * FROM `' . TB_GAME . '`
                WHERE `id` = :id
            '
            );

            $sth->bindParam(':id', $id, PDO::PARAM_STR);

            if ($result = $sth->execute()) {
                $result = $sth->fetchAll(PDO::FETCH_ASSOC);
                return isset($result[0]) ? json_decode($result[0]['data'], true): $result;
            } else {
                return $result;
            }
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }

    /**
     * Insert data to database
     *
     * @param $id
     * @param $data
     *
     * @return bool
     * @throws StorageException
     */
    public static function insertToGame($id, $data)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        if (empty($data)) {
            throw new StorageException('Data is empty!');
        }

        try {
            $sth = self::$pdo->prepare(
                '
                INSERT INTO `' . TB_GAME . '`
                (`id`, `data`, `created_at`, `updated_at`)
                VALUES
                (:id, :data, :date, :date)
                ON DUPLICATE KEY UPDATE
                    `data`       = VALUES(`data`),
                    `updated_at` = VALUES(`updated_at`)
            '
            );

            $data = json_encode($data);
            $date = date('Y-m-d H:i:s');

            $sth->bindParam(':id', $id, PDO::PARAM_STR);
            $sth->bindParam(':data', $data, PDO::PARAM_STR);
            $sth->bindParam(':date', $date, PDO::PARAM_STR);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }

    /**
     * Delete data from storage
     *
     * @param $id
     *
     * @return bool
     * @throws StorageException
     */
    public static function deleteFromGame($id)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        try {
            $sth = self::$pdo->prepare(
                '
                DELETE FROM `' . TB_GAME . '`
                WHERE `id` = :id
            '
            );

            $sth->bindParam(':id', $id, PDO::PARAM_STR);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }

    /**
     * Basic file-power lock to prevent other process accessing same game
     *
     * @param $id
     *
     * @return bool
     * @throws StorageException
     */
    public static function lockGame($id)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        self::$lock = new LockFile($id);

        return flock(fopen(self::$lock->getFile(), "a+"), LOCK_EX);
    }

    /**
     * Unlock the game to allow access from other processes
     *
     * @param $id
     *
     * @return bool
     * @throws StorageException
     */
    public static function unlockGame($id)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        return flock(fopen(self::$lock->getFile(), "a+"), LOCK_UN);
    }

    /**
     * Select multiple data from the database
     *
     * @param int $time
     *
     * @return array|bool
     * @throws StorageException
     */
    public static function listFromGame($time = 0)
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (!is_numeric($time)) {
            throw new StorageException('Time must be a number!');
        }

        if ($time >= 0) {
            $compare_sign = '<=';
        } else {
            $compare_sign = '>';
        }

        try {
            $sth = self::$pdo->prepare(
                '
                SELECT * FROM `' . TB_GAME . '`
                WHERE `updated_at` ' . $compare_sign . ' :date
                ORDER BY `updated_at` ASC
            '
            );

            $date = date('Y-m-d H:i:s', strtotime('-' . abs($time) . ' seconds'));

            $sth->bindParam(':date', $date, PDO::PARAM_STR);

            if ($result = $sth->execute()) {
                return $sth->fetchAll(PDO::FETCH_ASSOC);
            } else {
                return $result;
            }
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }
}
