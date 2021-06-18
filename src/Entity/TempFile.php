<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2021 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot\Entity;

use Bot\Exception\BotException;
use Bot\Helper\Utilities;
use RuntimeException;
use SplFileInfo;

/**
 * A temporary file handler, with removal after it's not used
 */
class TempFile
{
    /**
     * The temporary file, or false
     *
     * @var null|SplFileInfo
     */
    private $file;

    /**
     * Should the file be delete after script ends
     *
     * @var bool
     */
    private $delete;

    /**
     * TempFile constructor
     *
     * @param string $name
     * @param bool   $delete
     *
     * @throws BotException
     */
    public function __construct($name, $delete = true)
    {
        $this->delete = $delete;

        if (defined('DATA_PATH')) {
            $this->file = DATA_PATH . '/tmp/' . $name . '.tmp';
        } else {
            $this->file = sys_get_temp_dir() . '/' . md5(__DIR__) . '/' . $name . '.tmp';
        }

        if (!is_dir(dirname($this->file)) && !mkdir($concurrentDirectory = dirname($this->file), 0755, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        if (!is_writable(dirname($this->file))) {
            throw new BotException('Destination path is not writable: ' . dirname($this->file));
        }

        touch($this->file);
        $this->file = new SplFileInfo($this->file);

        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('File: ' . realpath($this->file));
    }

    /**
     * Delete the file when script ends (unless specified to not)
     */
    public function __destruct()
    {
        if ($this->delete && $this->file !== null && file_exists($this->file)) {
            @unlink($this->file);
        }
    }

    /**
     * Get the file path or false
     *
     * @return null|SplFileInfo
     */
    public function getFile(): ?SplFileInfo
    {
        return $this->file;
    }
}
