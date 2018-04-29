<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2018 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;

/**
 * Class StartCommand
 *
 * @noinspection PhpUndefinedClassInspection
 *
 * Start command...
 *
 * @package Longman\TelegramBot\Commands\SystemCommands
 */
class StartCommand extends SystemCommand
{
    /**
     * @return mixed
     *
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        return $this->getTelegram()->executeCommand("help");
    }
}
