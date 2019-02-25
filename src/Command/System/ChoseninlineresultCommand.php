<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2019 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use jacklul\inlinegamesbot\GameCore;
use jacklul\inlinegamesbot\Helper\Utilities;
use Longman\TelegramBot\Commands\SystemCommand;

/**
 * Handle event when inline message is pasted into chat, instantly put a player into game
 *
 * @noinspection PhpUndefinedClassInspection
 */
class ChoseninlineresultCommand extends SystemCommand
{
    /**
     * @return bool|\Longman\TelegramBot\Entities\ServerResponse
     *
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \jacklul\inlinegamesbot\Exception\BotException
     * @throws \jacklul\inlinegamesbot\Exception\StorageException
     * @throws \jacklul\inlinegamesbot\Exception\TelegramApiException
     * @throws \Throwable
     */
    public function execute()
    {
        $chosen_inline_result = $this->getUpdate()->getChosenInlineResult();

        Utilities::debugPrint('Data: ' . $chosen_inline_result->getResultId());

        $game = new GameCore($chosen_inline_result->getInlineMessageId(), $chosen_inline_result->getResultId(), $this);

        if ($game->canRun()) {
            return $game->run();
        }

        return parent::execute();
    }
}
