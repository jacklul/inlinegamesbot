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
use Bot\Exception\BotException;

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
     * @throws BotException
     */
    public static function getDriver()
    {
        $dsn = Dsn::parse(getenv('DATABASE_URL'));
        $dsn = $dsn->toArray();

        $engine = 'Bot\Storage\Driver\\' . (self::$drivers[$dsn['engine']]) ?: '';

        if (!class_exists($engine)) {
            throw new BotException('Database engine class \'' . $engine . ' doesn\'t exist!');
        }

        return $engine;
    }
}
