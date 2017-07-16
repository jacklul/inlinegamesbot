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
 * Class EnvDB
 *
 * Initializes PDO object using DSN string from 'DATABASE_URL' environment variable (Heroku) [preferred]
 *
 * @TODO redesign this...
 * @TODO better way of handling different database than using almost-compatible structure.sql file...
 *
 * @package Bot\Storage
 */
class External
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
        'mysql' => 'MySQL',
        'pgsql' => 'PostgreSQL',
        'postgres' => 'PostgreSQL',
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
    public static function storage($action, $id, $data = [])
    {
        if (empty($action)) {
            throw new BotException('Action is empty!');
        }

        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        if (!defined('TB_STORAGE')) {
            $dsn = Dsn::parse(getenv('DATABASE_URL'));
            $dsn = $dsn->toArray();

            self::$engine = 'Bot\Storage\Handler\\' . (self::$supported_engines[$dsn['engine']]) ?: '';
        }

        if (!class_exists(self::$engine)) {
            throw new BotException('Database engine class \'' . self::$engine . ' does\'nt exist!');
        }

        self::$engine::initializeStorage();

        switch ($action) {
            default:
            case 'get':
                return self::$engine::selectFromStorage($id);
            case 'put':
                if (empty($data)) {
                    throw new BotException('Data is empty!');
                }

                return self::$engine::insertToStorage($id, $data);
            case 'lock':
                return self::$engine::lockStorage($id);
            case 'unlock':
                return self::$engine::unlockStorage($id);
            case 'list':
                return self::$engine::listFromStorage($id);
            case 'remove':
                return self::$engine::deleteFromStorage($id);
        }
    }
}
