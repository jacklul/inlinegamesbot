<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2018 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\inlinegamesbot\Storage\Other;

use Google\Auth\HttpHandler\Guzzle6HttpHandler;
use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Key;
use GuzzleHttp\Client;
use jacklul\inlinegamesbot\Entity\TempFile;
use jacklul\inlinegamesbot\Exception\StorageException;

/**
 * Class GoogleCloudDatastore
 *
 * @package jacklul\inlinegamesbot\Storage\Other
 */
class GoogleCloudDatastore
{
    /**
     * DatastoreClient object
     *
     * @var DatastoreClient
     */
    private static $datastore;

    /**
     * Lock file object
     *
     * @var TempFile
     */
    private static $lock;

    /**
     * Initialize datastore object
     *
     * @return bool
     *
     * @throws StorageException
     */
    public static function initializeStorage(): bool
    {
        if (class_exists('Google\Cloud\Datastore\DatastoreClient')) {
            if (empty(getenv('GAE_PROJECT_ID'))) {
                throw new StorageException('Project ID is empty!');
            }

            $config = ['projectId' => getenv('GAE_PROJECT_ID')];
            if (isset($_SERVER['CURRENT_VERSION_ID']) || isset($_SERVER['GAE_VERSION'])) {
                $config['httpHandler'] = new Guzzle6HttpHandler(
                    new Client([
                        'base_uri' => 'https://api.telegram.org',
                        'handler'  => new \GuzzleHttp\Handler\StreamHandler(),
                        'verify'   => false,
                    ])
                );
            }

            self::$datastore = new DatastoreClient($config);
        } else {
            throw new StorageException('DatastoreClient class doesn\'t exist!');
        }

        return true;
    }

    /**
     * Create table structure
     *
     * @return bool
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
    public static function isDatastoreConnected(): bool
    {
        return self::$datastore !== null;
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
        if (!self::isDatastoreConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        $key = self::$datastore->key('Game', $id, ['identifierType' => Key::TYPE_NAME]);
        $entity = self::$datastore->lookup($key);

        if (!is_null($entity)) {
            return json_decode($entity['data'], true);
        }

        return [];
    }

    /**
     * Insert data to database
     *
     * @param string $id
     * @param array $data
     *
     * @return bool
     * @throws StorageException
     */
    public static function insertToGame(string $id, array $data): bool
    {
        if (!self::isDatastoreConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        if (empty($data)) {
            throw new StorageException('Data is empty!');
        }

        $key = self::$datastore->key('Game', $id, ['identifierType' => Key::TYPE_NAME]);
        $entity = self::$datastore->lookup($key);

        if ($entity === null) {
            $entity = self::$datastore->entity($key);
            $entity['data'] = json_encode($data);
            $entity['updated_at'] = time();
            return self::$datastore->insert($entity);
        }

        $entity['data'] = json_encode($data);
        $entity['updated_at'] = time();
        return self::$datastore->update($entity);
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
        if (!self::isDatastoreConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        $key = self::$datastore->key('Game', $id, ['identifierType' => Key::TYPE_NAME]);
        return self::$datastore->delete($key);
    }

    /**
     * Basic file-powered lock to prevent other process accessing same game
     *
     * @param string $id
     *
     * @return bool
     *
     * @throws StorageException
     * @throws \jacklul\inlinegamesbot\Exception\BotException
     */
    public static function lockGame(string $id): bool
    {
        if (!self::isDatastoreConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        self::$lock = new TempFile($id);
        return flock(fopen(self::$lock->getFile()->getPathname(), "a+"), LOCK_EX);
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
        if (!self::isDatastoreConnected()) {
            return false;
        }

        if (empty($id)) {
            throw new StorageException('Id is empty!');
        }

        if (self::$lock === null) {
            throw new StorageException('No lock file object!');
        }

        return flock(fopen(self::$lock->getFile()->getPathname(), "a+"), LOCK_UN);
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
        if (!self::isDatastoreConnected()) {
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

        $return = [];
        $time = strtotime('-' . abs($time) . ' seconds');

        $query = self::$datastore->query();
        $query->kind('Game');
        $query->filter('updated_at', $compare_sign, $time);
        $res = self::$datastore->runQuery($query);

        /** @var \Google\Cloud\Datastore\Entity $entity */
        foreach ($res as $entity) {
            $return[] = ['id' => $entity->key()->pathEndIdentifier(), 'data' => $entity['data'], 'updated_at' => date('Y-m-d H:i:s', $entity['updated_at'])];
        }

        return $return;
    }
}
