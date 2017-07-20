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

use AD7six\Dsn\Dsn;
use Bot\Exception\StorageException;
use Bot\Helper\Debug;
use Longman\TelegramBot\DB;

/**
 * Class DB
 *
 * Initializes PDO object based on DSN string from 'DATABASE_URL' environment variable
 *
 * @package Bot\Storage
 */
class Driver
{
    /**
     * Supported DB engines
     */
    private static $drivers = [
        'mysql'     => 'MySQL',
        'pgsql'     => 'PostgreSQL',
        'postgres'  => 'PostgreSQL',
    ];

    /**
     * Return which driver class to use
     *
     * @return string
     * @throws StorageException
     */
    public static function getStorageClass()
    {
        if (DB::isDbConnected() && !getenv('DEBUG_NO_BOTDB')) {
            $storage = 'Bot\Storage\Driver\BotDB';
        } elseif (getenv('DATABASE_URL')) {
            $dsn = Dsn::parse(getenv('DATABASE_URL'));
            $dsn = $dsn->toArray();

            $storage = 'Bot\Storage\Driver\\' . (self::$drivers[$dsn['engine']]) ?: '';
        } else {
            $storage = 'Bot\Storage\Driver\File';
        }

        if ($env_storage = getenv('DEBUG_STORAGE')) {
            $storage = $env_storage;
        }

        if (!class_exists($storage)) {
            throw new StorageException('Storage class doesn\'t exist: ' . $storage);
        }

        Debug::print('Using storage: \'' . $storage . '\'');

        return $storage;
    }
}
