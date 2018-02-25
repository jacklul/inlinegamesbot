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
use SplFileInfo;

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
     * @var null|SplFileInfo
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
        $this->file = sys_get_temp_dir() . '/' . md5(__DIR__);
        $this->delete = $delete;

        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0755, true);
        }

        if (!touch($this->file)) {
            throw new BotException('Couldn\'t create file: ' . $this->file);
        }

        $this->file = new SplFileInfo($this->file);

        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('File: ' . realpath($this->file));
    }

    /**
     * Delete the file when script ends (unless specified to not)
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
     * @return null|SplFileInfo
     */
    public function getFile()
    {
        return $this->file;
    }
}
