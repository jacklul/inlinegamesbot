<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2019 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\inlinegamesbot\Storage;

use AD7six\Dsn\Dsn;
use jacklul\inlinegamesbot\Exception\StorageException;
use jacklul\inlinegamesbot\Helper\Utilities;
use Longman\TelegramBot\DB;
use jacklul\inlinegamesbot\Storage\Driver\File;
use jacklul\inlinegamesbot\Storage\Driver\BotDB;

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
            try {
                $dsn = Dsn::parse(getenv('DATABASE_URL'));
                $dsn = $dsn->toArray();
            } catch (\Exception $e) {
                throw new StorageException('DSN parsing error', 0, $e);
            }

            if (!isset(self::$storage_drivers[$dsn['engine']])) {
                throw new StorageException('Unsupported database type!');
            }

            $storage = 'jacklul\inlinegamesbot\Storage\Driver\\' . (self::$storage_drivers[$dsn['engine']] ?: '');
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
