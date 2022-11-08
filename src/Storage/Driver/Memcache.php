<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2022 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot\Storage\Driver;

use Bot\Entity\TempFile;
use Bot\Exception\BotException;
use Bot\Exception\StorageException;
use MemCachier\MemcacheSASL;
use Memcache as MemcacheCore;
use Memcached as MemcachedCore;

/**
 * Class Memcache
 */
class Memcache
{
    /**
     * Memcache object
     *
     * @var MemcachedCore|MemcacheCore
     */
    private static $memcache;

    /**
     * Lock file object
     *
     * @var TempFile
     */
    private static $lock;

    /**
     * Create table structure
     *
     * @return bool
     * @throws StorageException
     */
    public static function createStructure(): bool
    {
        return true;
    }

    /**
     * Check if database connection has been created
     *
     * @return bool
     */
    public static function isDbConnected(): bool
    {
        return self::$memcache !== null;
    }

    /**
     * Initialize connection
     *
     * @return bool
     *
     * @throws StorageException
     */
    public static function initializeStorage(): bool
    {
        if (self::isDbConnected()) {
            return true;
        }

        try {
            $dsn = parse_url(getenv('DATABASE_URL'));

            if (class_exists(MemcacheSASL::class) && stripos($dsn['host'], 'memcachier') !== false) {
                $memcache = new MemcacheSASL();

                $memcache->setOption(MemcacheSASL::OPT_COMPRESSION, true);
            } elseif (class_exists(MemcachedCore::class)) {
                $memcache = new MemcachedCore($persistent_id ?? null);

                $memcache->setOption(MemcachedCore::OPT_BINARY_PROTOCOL, true);
                $memcache->setOption(MemcachedCore::OPT_NO_BLOCK, true);
                $memcache->setOption(MemcachedCore::OPT_COMPRESSION, true);
            } elseif (class_exists(MemcacheCore::class)) {
                $memcache = new MemcacheCore($persistent_id ?? null);
            } else {
                throw new StorageException('Unsupported database type!');
            }

            $memcache->addServer($dsn['host'], $dsn['port']);
            
            if (isset($dsn['user'], $dsn['pass'])) {
                if (!method_exists($memcache, 'setSaslAuthData')) {
                    throw new \RuntimeException('Memcached extension was not build with SASL support');
                }

                $memcache->setSaslAuthData($dsn['user'], $dsn['pass']);
            }

            self::$memcache = $memcache;
        } catch (\Exception $e) {
            throw new StorageException('Connection to the memcached server failed: ' . $e->getMessage());
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

        $data = self::$memcache->get('game_' . sha1($id));

        if ($data !== null) {
            return json_decode($data, true) ?? [];
        }

        return [];
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

        return self::$memcache->set('game_' . sha1($id), json_encode($data));
    }

    /**
     * Delete data from storage
     *
     * @param string $id
     *
     * @return bool
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

        return self::$memcache->delete('game_' . sha1($id));
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

        return [];
    }
}
