<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Storage\Handler;

use AD7six\Dsn\Dsn;
use Bot\Exception\BotException;
use Bot\Helper\DebugLog;
use Longman\TelegramBot\Exception\TelegramException;
use PDO;
use PDOException;

/**
 * Class PostgreSQL
 *
 * @package Bot\Storage\Handler
 */
class PostgreSQL
{
    /**
     * PDO object
     *
     * @var PDO
     */
    private static $external_pdo;

    /**
     * Initialize PDO connection and 'storage' table
     */
    public static function initializeStorage()
    {
        if (!defined('TB_STORAGE')) {
            define('TB_STORAGE', 'storage');

            $dsn = Dsn::parse(getenv('DATABASE_URL'));
            $dsn = $dsn->toArray();

            if ($dsn['engine'] === 'postgres') {
                $dsn['engine'] = 'pgsql';
            }

            try {
                self::$external_pdo = new PDO($dsn['engine'] . ':host=' . $dsn['host'] . ';port=' . $dsn['port'] . ';dbname=' . $dsn['database'], $dsn['user'], $dsn['pass']);
                self::$external_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
            } catch (PDOException $e) {
                throw new TelegramException($e->getMessage());
            }
            
            DebugLog::log('Connected to database');

            if (!file_exists($structure_check = VAR_PATH . '/db_structure_created') && file_exists($structure = ROOT_PATH . '/structure.sql')) {
                DebugLog::log('Creating database structure...');

                try {
                    if ($result = self::$external_pdo->query(file_get_contents($structure))) {
                        if (!is_dir(VAR_PATH)) {
                            mkdir(VAR_PATH, 0755, true);
                        }

                        touch($structure_check);
                    }

                    if (!$result) {
                        throw new BotException('Failed to create DB structure!');
                    }
                } catch (BotException $e) {
                    throw new TelegramException($e->getMessage());
                }
            }
        }
    }

    /**
     * Select data from database
     *
     * @param $id
     *
     * @return array|bool|mixed
     * @throws BotException
     */
    public static function selectFromStorage($id)
    {
        try {
            $sth = self::$external_pdo->prepare('
                SELECT * FROM ' . TB_STORAGE . '
                WHERE id = :id
            ');

            $sth->bindParam(':id', $id, PDO::PARAM_STR);

            if ($result = $sth->execute()) {
                $result = $sth->fetchAll(PDO::FETCH_ASSOC);
                return isset($result[0]) ? json_decode($result[0]['data'], true): $result;
            } else {
                return $result;
            }
        } catch (PDOException $e) {
            throw new BotException($e->getMessage());
        }
    }

    /**
     * Insert data to database
     *
     * @param $id
     * @param $data
     *
     * @return bool
     * @throws BotException
     */
    public static function insertToStorage($id, $data)
    {
        try {
            $sth = self::$external_pdo->prepare('
                INSERT INTO ' . TB_STORAGE . '
                (id, data, created_at, updated_at)
                VALUES
                (:id, :data, :date, :date)
                ON CONFLICT (id) DO UPDATE 
                  SET   data       = :data,
                        updated_at = :date
            ');

            $data = json_encode($data);
            $date = date('Y-m-d H:i:s', time());

            $sth->bindParam(':id', $id, PDO::PARAM_STR);
            $sth->bindParam(':data', $data, PDO::PARAM_STR);
            $sth->bindParam(':date', $date, PDO::PARAM_STR);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new BotException($e->getMessage());
        }
    }

    /**
     * Lock the row to prevent another process modifying it
     *
     * @param $id
     *
     * @return bool
     */
    public static function lockStorage($id)
    {
        if (!is_dir(VAR_PATH . '/tmp')) {
            mkdir(VAR_PATH . '/tmp', 0755, true);
        }

        return flock(fopen(VAR_PATH . '/tmp/' . $id .  '.json', "a+"), LOCK_EX);
    }

    /**
     * Unlock the row after

     * @param $id
     *
     * @return bool
     */
    public static function unlockStorage($id)
    {
        return flock(fopen(VAR_PATH . '/tmp/' . $id .  '.json', "a+"), LOCK_UN) && unlink(VAR_PATH . '/tmp/' . $id .  '.json');
    }

    /**
     * Select multiple data from the database
     *
     * @param int $time
     *
     * @return array|bool
     * @throws BotException
     */
    public static function listFromStorage($time = 0)
    {
        if ($time < 0) {
            throw new BotException('Time cannot be a negative number!');
        }

        try {
            $sth = self::$external_pdo->prepare('
                SELECT * FROM ' . TB_STORAGE . '
                WHERE updated_at < :date
            ');

            $date = date('Y-m-d H:i:s', strtotime('-' . $time . ' seconds'));

            $sth->bindParam(':date', $date, PDO::PARAM_STR);

            if ($result = $sth->execute()) {
                return $sth->fetchAll(PDO::FETCH_ASSOC);
            } else {
                return $result;
            }
        } catch (PDOException $e) {
            throw new BotException($e->getMessage());
        }
    }

    /**
     * Delete data from storage
     *
     * @param $id
     *
     * @return array|bool|mixed
     * @throws BotException
     */
    public static function deleteFromStorage($id)
    {
        try {
            $sth = self::$external_pdo->prepare('
                DELETE FROM ' . TB_STORAGE . '
                WHERE id = :id
            ');

            $sth->bindParam(':id', $id, PDO::PARAM_STR);

            return $sth->execute();
        } catch (PDOException $e) {
            throw new BotException($e->getMessage());
        }
    }
}
