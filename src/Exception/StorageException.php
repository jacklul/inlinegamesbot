<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2022 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot\Exception;

use Bot\Storage\Storage;
use Exception;
use Throwable;

/**
 * Exception class used for bot storage related exception handling
 */
class StorageException extends BotException
{
    /**
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        try {
            parent::__construct($message . ' [' . Storage::getClass() . ']', $code, $previous);
        } catch (Exception $e) {
            parent::__construct($message, $code, $previous);
        }
    }
}
