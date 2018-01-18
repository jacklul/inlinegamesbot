<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;

/**
 * Class StartCommand
 *
 * @package Longman\TelegramBot\Commands\SystemCommands
 */
class StartCommand extends SystemCommand
{
    /**
     * @return mixed
     */
    public function execute()
    {
        return $this->getTelegram()->executeCommand("help");    //redirect to /help
    }
}
