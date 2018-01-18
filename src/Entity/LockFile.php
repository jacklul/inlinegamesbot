<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Entity;

use Bot\Helper\Debug;

/**
 * Class LockFile
 *
 * @package Bot\Entity
 */
class LockFile
{
    /**
     * The temporary file, or false
     *
     * @var null|string
     */
    private $file = null;

    /**
     * Should the file be delete after script ends
     *
     * @var bool
     */
    private $delete = true;

    /**
     * LockFile constructor
     *
     * @param string $name
     * @param bool   $delete
     */
    public function __construct($name, $delete = true)
    {
        $this->file = DATA_PATH . '/tmp/' . $name . '.tmp';
        $this->delete = $delete;

        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0755, true);
        }

        if (!is_writable(dirname($this->file))) {
            $this->file = null;
        }

        Debug::print('Lock file: ' . $this->file);

        touch($this->file);
    }

    /**
     * Delete the file when script ends
     */
    public function __destruct()
    {
        if ($this->delete && !is_null($this->file)) {
            @unlink($this->file);
        }
    }

    /**
     * Get the file path or false
     *
     * @return null|string
     */
    public function getFile()
    {
        return $this->file;
    }
}
