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

use Bot\Helper\Debug;
use Bot\Entity\GameManager;
use Longman\TelegramBot\Commands\SystemCommand;

/**
 * Class ChoseninlineresultCommand
 *
 * Handle event when inline message is pasted into chat, instantly put a player into game
 *
 * @package Longman\TelegramBot\Commands\SystemCommands
 */
class ChoseninlineresultCommand extends SystemCommand
{
    /**
     * @return bool|\Longman\TelegramBot\Entities\ServerResponse
     *
     * @throws \Bot\Exception\BotException
     * @throws \Bot\Exception\StorageException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $chosen_inline_result = $this->getUpdate()->getChosenInlineResult();

        Debug::isEnabled() && Debug::print('Data: ' . $chosen_inline_result->getResultId());

        $game = new GameManager($chosen_inline_result->getInlineMessageId(), $chosen_inline_result->getResultId(), $this);

        if ($game->canRun()) {
            return $game->run();
        }

        return parent::execute();
    }
}
