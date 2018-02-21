<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2018 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\inlinegamesbot\Entity;

use jacklul\inlinegamesbot\Exception\BotException;
use jacklul\inlinegamesbot\Helper\Utilities;

/**
 * Class TempFile
 *
 * A temporary file handler, with removal after it's not used
 *
 * @package jacklul\inlinegamesbot\Entity
 */
class TempFile
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
     * TempFile constructor
     *
     * @param string $name
     * @param bool $delete
     *
     * @throws \jacklul\inlinegamesbot\Exception\BotException
     */
    public function __construct($name, $delete = true)
    {
        $this->file = DATA_PATH . '/tmp/' . $name . '.tmp';
        $this->delete = $delete;

        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0755, true);
        }

        if (!is_writable(dirname($this->file))) {
            throw new BotException('Couldn\'t create file: ' . $this->file);
        }

        touch($this->file);

        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('File: ' . realpath($this->file));
    }

    /**
     * Delete the file when script ends
     */
    public function __destruct()
    {
        if ($this->delete && !is_null($this->file) && file_exists($this->file)) {
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
