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

use Bot\Exception\BotException;

define("STORAGE_PATH", VAR_PATH . '/storage');

/**
 * Class JsonFile
 *
 * Stores data in json formatted text files
 *
 * @package Bot\Storage
 */
class JsonFile
{
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

        self::initialize();

        switch ($action) {
            default:
            case 'get':
                return self::get($id);
            case 'put':
                if (empty($data)) {
                    throw new BotException('Data is empty!');
                }

                return self::put($id, $data);
            case 'lock':
                return self::lock($id);
            case 'unlock':
                return self::unlock($id);
            case 'list':
                return self::list($id);
            case 'remove':
                return self::delete($id);
        }
    }

    /**
     * Initialize, make sure storage path exists
     */
    private static function initialize()
    {
        if (!is_dir(STORAGE_PATH)) {
            mkdir(STORAGE_PATH, 0755, true);
        }
    }

    /**
     * Read data from the file
     *
     * @param $id
     *
     * @return array|bool|mixed
     */
    private static function get($id)
    {
        if (file_exists(STORAGE_PATH . '/' . $id . '.json')) {
            return json_decode(file_get_contents(STORAGE_PATH . '/' . $id . '.json'), true);
        }

        return false;
    }

    /**
     * Place data to the file
     *
     * @param $id
     * @param $data
     *
     * @return bool
     */
    private static function put($id, $data)
    {
        if (file_exists(STORAGE_PATH . '/' . $id .  '.json')) {
            return file_put_contents(STORAGE_PATH . '/' . $id . '.json', json_encode($data));
        }

        return false;
    }

    /**
     * Lock the file to prevent another process modifying it
     *
     * @param $id
     *
     * @return bool
     */
    private static function lock($id)
    {
        if (!file_exists(STORAGE_PATH . '/' . $id . '.json')) {
            $timestamp = time();
            file_put_contents(STORAGE_PATH . '/' . $id . '.json', json_encode(['created_at' => $timestamp, 'update_at' => $timestamp]));
        }

        if (flock(fopen(STORAGE_PATH . '/' . $id .  '.json', "a+"), LOCK_EX)) {
            return true;
        }

        return false;
    }

    /**
     * Unlock the file after
     *
     * @param $id
     *
     * @return bool
     */
    private static function unlock($id)
    {
        if (flock(fopen(STORAGE_PATH . '/' . $id .  '.json', "a+"), LOCK_UN)) {
            return true;
        }

        return false;
    }

    /**
     * Select inactive data fields from database
     *
     * @param int $time
     *
     * @return array
     * @throws BotException
     */
    private static function list($time = 0)
    {
        if ($time < 0) {
            throw new BotException('Time cannot be a negative number!');
        }

        $ids = [];
        foreach (new \DirectoryIterator(STORAGE_PATH) as $file) {
            if (!$file->isDir() && !$file->isDot()) {
                if ($file->getMTime() < strtotime('-' . $time . ' seconds')) {
                    $ids[] = $file->getFilename();
                }
            }
        }

        return $ids;
    }

    /**
     * Remove data file
     *
     * @param $id
     *
     * @return bool
     */
    private static function delete($id)
    {
        if (file_exists(STORAGE_PATH . '/' . $id .  '.json')) {
            return unlink(STORAGE_PATH . '/' . $id . '.json');
        }

        return false;
    }
}
