<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Helper;

use AD7six\Dsn\Dsn;
use Bot\Exception\StorageException;
use Longman\TelegramBot\DB;

/**
 * Class Storage
 *
 * @package Bot\Storage
 */
class Storage
{
    /**
     * Supported database engines
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
    public static function getClass(): string
    {
        if (DB::isDbConnected() && !getenv('DEBUG_NO_BOTDB')) {
            $storage = 'Bot\Storage\BotDB';
        } elseif (getenv('DATABASE_URL')) {
            $dsn = Dsn::parse(getenv('DATABASE_URL'));
            $dsn = $dsn->toArray();

            $storage = 'Bot\Storage\Database\\' . (self::$drivers[$dsn['engine']]) ?: '';
        } else {
            $storage = 'Bot\Storage\File';
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
