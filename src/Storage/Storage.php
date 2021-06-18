<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2021 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot\Storage;

use Bot\Exception\StorageException;
use Bot\Helper\Utilities;
use Bot\Storage\Driver\BotDB;
use Bot\Storage\Driver\File;
use Longman\TelegramBot\DB;

/**
 * Picks the best storage driver available
 */
class Storage
{
    /**
     * Supported database engines
     */
    private static $storage_drivers = [
        'mysql'    => 'MySQL',
        'pgsql'    => 'PostgreSQL',
        'postgres' => 'PostgreSQL',
    ];

    /**
     * Return which driver class to use
     *
     * @return string
     *
     * @throws StorageException
     */
    public static function getClass(): string
    {
        if (!empty($debug_storage = getenv('STORAGE_CLASS'))) {
            $storage = str_replace('"', '', $debug_storage);
            Utilities::debugPrint('Forcing storage: \'' . $storage . '\'');
        } elseif (DB::isDbConnected()) {
            $storage = BotDB::class;
        } elseif (getenv('DATABASE_URL')) {
            $dsn = parse_url(getenv('DATABASE_URL'));

            if (!isset(self::$storage_drivers[$dsn['scheme']])) {
                throw new StorageException('Unsupported database type!');
            }

            $storage = 'Bot\Storage\Driver\\' . (self::$storage_drivers[$dsn['scheme']] ?: '');
        } elseif (defined('DATA_PATH')) {
            $storage = File::class;
        }

        if (empty($storage) || !class_exists($storage)) {
            /** @noinspection PhpUndefinedVariableInspection */
            throw new StorageException('Storage class doesn\'t exist: ' . $storage);
        }

        Utilities::debugPrint('Using storage: \'' . $storage . '\'');

        return $storage;
    }
}
