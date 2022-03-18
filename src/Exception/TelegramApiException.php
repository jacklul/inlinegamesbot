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

use Longman\TelegramBot\Entities\ServerResponse;

/**
 * Exception class used for Telegram API related exception handling
 */
class TelegramApiException extends BotException
{
    /**
     * @param string              $message
     * @param int                 $code
     * @param ServerResponse|null $result
     */
    public function __construct($message = "", $code = 0, ServerResponse $result = null)
    {
        parent::__construct($message, $code, $result);
    }
}
