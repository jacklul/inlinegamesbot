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
use PDO;

/**
 * Class DB
 *
 * Initializes PDO object using DSN string from 'DATABASE_URL' environment variable (Heroku) [preferred]
 *
 * @TODO This might need a little redesign someday...
 *
 * @package Bot\Storage
 */
class DB
{
    /**
     * Which engine to use
     *
     * @var PDO
     */
    private static $engine;

    /**
     * Supported DB engines
     *
     * @var PDO
     */
    private static $supported_engines = [
        'mysql'     => 'MySQL',
        'pgsql'     => 'PostgreSQL',
        'postgres'  => 'PostgreSQL',
    ];

    /**
     * This is 'proxy' function to server actions
     *
     * @param $action
     * @param $id
     * @param array  $data
     *
     * @return array|bool|mixed
     * @throws BotException
     */
    public static function action($action, $id, $data = [])
    {
        if (!defined('TB_STORAGE')) {
            $dsn = Dsn::parse(getenv('DATABASE_URL'));
            $dsn = $dsn->toArray();

            self::$engine = 'Bot\Storage\Driver\\' . (self::$supported_engines[$dsn['engine']]) ?: '';

            if (!class_exists(self::$engine)) {
                throw new BotException('Database engine class \'' . self::$engine . ' doesn\'t exist!');
            }
        }

        return self::$engine::action($action, $id, $data);
    }
}
