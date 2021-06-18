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

use Bot\Entity\TempFile;
use Bot\Exception\BotException;
use Bot\Exception\StorageException;
use PDO;
use PDOException;

/**
 * Class PostgreSQL
 */
class PostgreSQL
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
     * @var TempFile
     */
    private static $lock;

    /**
     * SQL to create database structure
     *
     * @var string
     */
    private static $structure = 'CREATE TABLE IF NOT EXISTS game (
        id CHAR(255),
        data TEXT NOT NULL,
        created_at timestamp NULL DEFAULT NULL,
        updated_at timestamp NULL DEFAULT NULL,

        PRIMARY KEY (id)
    );';

    /**
     * Create table structure
     *
     * @return bool
     * @throws StorageException
     */
    public static function createStructure(): bool
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
    public static function isDbConnected(): bool
    {
        return self::$pdo !== null;
    }

    /**
     * Initialize PDO connection
     *
     * @throws StorageException
     */
    public static function initializeStorage(): bool
    {
        if (self::isDbConnected()) {
            return true;
        }

        if (!defined('TB_GAME')) {
            define('TB_GAME', 'game');
        }

        try {
            $dsn = parse_url(getenv('DATABASE_URL'));

            self::$pdo = new PDO('pgsql:' . 'host=' . $dsn['host'] . ';port=' . $dsn['port'] . ';dbname=' . ltrim($dsn['path'], '/'), $dsn['user'], $dsn['pass']);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        } catch (PDOException $e) {
            throw new StorageException('Connection to the database failed: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Select data from database
     *
     * @param string $id
     *
     * @return array|bool
     * @throws StorageException
     */
    public static function selectFromGame(string $id)
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
                SELECT * FROM ' . TB_GAME . '
                WHERE id = :id
            '
            );

            $sth->bindParam(':id', $id);

            if ($result = $sth->execute()) {
                $result = $sth->fetchAll(PDO::FETCH_ASSOC);

                return isset($result[0]) ? json_decode($result[0]['data'], true) : [];
            }

            return false;
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }

    /**
     * Insert data to database
     *
     * @param string $id
     * @param array  $data
     *
     * @return bool
     * @throws StorageException
     */
    public static function insertToGame(string $id, array $data): bool
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
                INSERT INTO ' . TB_GAME . '
                (id, data, created_at, updated_at)
                VALUES
                (:id, :data, :date, :date)
                ON CONFLICT (id) DO UPDATE
                  SET   data       = :data,
                        updated_at = :date
            '
            );

            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $data = json_encode($data);
            $date = date('Y-m-d H:i:s');

            $sth->bindParam(':id', $id);
            $sth->bindParam(':data', $data);
            $sth->bindParam(':date', $date);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }

    /**
     * Delete data from storage
     *
     * @param string $id
     *
     * @return array|bool|mixed
     * @throws StorageException
     */
    public static function deleteFromGame(string $id): bool
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
                DELETE FROM ' . TB_GAME . '
                WHERE id = :id
            '
            );

            $sth->bindParam(':id', $id);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }

    /**
     * Basic file-powered lock to prevent other process accessing same game
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws StorageException
     * @throws BotException
     */
    public static function lockGame(string $id): bool
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        self::$lock = new TempFile($id);
        if (self::$lock->getFile() === null) {
            return false;
        }

        return flock(fopen(self::$lock->getFile()->getPathname(), 'ab+'), LOCK_EX);
    }

    /**
     * Unlock the game to allow access from other processes
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws StorageException
     */
    public static function unlockGame(string $id): bool
    {
        if (!self::isDbConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        if (self::$lock === null) {
            throw new StorageException('No lock file object!');
        }

        if (self::$lock->getFile() === null) {
            return false;
        }

        return flock(fopen(self::$lock->getFile()->getPathname(), 'ab+'), LOCK_UN);
    }

    /**
     * Select multiple data from the database
     *
     * @param int $time
     *
     * @return array|bool
     * @throws StorageException
     */
    public static function listFromGame(int $time = 0)
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
                SELECT * FROM ' . TB_GAME . '
                WHERE updated_at ' . $compare_sign . ' :date
                ORDER BY updated_at ASC
            '
            );

            $date = date('Y-m-d H:i:s', strtotime('-' . abs($time) . ' seconds'));
            $sth->bindParam(':date', $date);

            if ($result = $sth->execute()) {
                return $sth->fetchAll(PDO::FETCH_ASSOC);
            }

            return false;
        } catch (PDOException $e) {
            throw new StorageException($e->getMessage());
        }
    }
}
