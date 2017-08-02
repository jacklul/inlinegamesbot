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

/**
 * Class LockFile
 *
 * @TODO this is not good for multi-dyno setup on Heroku as filesystem is not shared
 *
 * @package Bot\Helper
 */
class LockFile
{
    /**
     * The temporary file, or false
     *
     * @var bool|string
     */
    private $file;

    /**
     * Should the file be delete after script ends
     *
     * @var bool
     */
    private $delete = true;

    /**
     * LockFile constructor
     *
     * @param $name
     * @param bool $delete
     */
    public function __construct($name, $delete = true)
    {
        $this->file = sys_get_temp_dir() . '/' . $name . '.tmp';

        if (!is_writable($this->file)) {
            if (!is_dir(DATA_PATH . '/tmp')) {
                mkdir(DATA_PATH . '/tmp', 0755, true);
            }

            $this->file = DATA_PATH . '/tmp/' . $name . '.tmp';
        }

        $this->delete = $delete;
    }

    /**
     * Delete the file when script ends
     */
    public function __destruct()
    {
        if ($this->delete) {
            @unlink($this->file);
        }
    }

    /**
     * Get the file path
     *
     * @return bool|string
     */
    public function getFile()
    {
        return $this->file;
    }
}
