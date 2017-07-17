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

/**
 * Class File
 *
 * Stores data in json formatted text files
 *
 * @package Bot\Storage
 */
class File
{
    /**
     * Initialize
     */
    public static function initializeStorage()
    {
        if (!defined('STORAGE_PATH')) {
            define("STORAGE_PATH", VAR_PATH . '/storage');

            if (!is_dir(STORAGE_PATH)) {
                mkdir(STORAGE_PATH, 0755, true);
            }
        }

        return true;
    }

    /**
     * Read data from the file
     *
     * @param $id
     *
     * @return array|bool|mixed
     * @throws BotException
     */
    public static function selectFromStorage($id)
    {
        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

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
     * @throws BotException
     */
    public static function insertToStorage($id, $data)
    {
        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        $data['updated_at'] = time();

        if (!isset($data['created_at'])) {
            $data['created_at'] = $data['updated_at'];
        }

        if (file_exists(STORAGE_PATH . '/' . $id .  '.json')) {
            return file_put_contents(STORAGE_PATH . '/' . $id . '.json', json_encode($data));
        }

        return false;
    }
    
    /**
     * Remove data file
     *
     * @param $id
     *
     * @return bool
     * @throws BotException
     */
    public static function deleteFromStorage($id)
    {
        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        if (file_exists(STORAGE_PATH . '/' . $id .  '.json')) {
            return unlink(STORAGE_PATH . '/' . $id . '.json');
        }

        return false;
    }

    /**
     * Lock the file to prevent another process modifying it
     *
     * @param $id
     *
     * @return bool
     * @throws BotException
     */
    public static function lockStorage($id)
    {
        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        if (!file_exists(STORAGE_PATH . '/' . $id . '.json')) {
            $timestamp = time();
            file_put_contents(STORAGE_PATH . '/' . $id . '.json', json_encode(['created_at' => $timestamp, 'updated_at' => $timestamp]));
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
     * @throws BotException
     */
    public static function unlockStorage($id)
    {
        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

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
    public static function listFromStorage($time = 0)
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
}
