<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Bot\Helper\Debug;
use Bot\Manager\Game;
use Longman\TelegramBot\Commands\SystemCommand;

/**
 * Class ChoseninlineresultCommand
 *
 * @package Longman\TelegramBot\Commands\SystemCommands
 */
class ChoseninlineresultCommand extends SystemCommand
{
    /**
     * @return bool|\Longman\TelegramBot\Entities\ServerResponse
     */
    public function execute()
    {
        $chosen_inline_result = $this->getUpdate()->getChosenInlineResult();

        Debug::log('Data: ' . $chosen_inline_result->getResultId());

        $game = new Game($chosen_inline_result->getInlineMessageId(), $chosen_inline_result->getResultId(), $this);

        if ($game->canRun()) {
            return $game->run();
        }

        return parent::execute();
    }
}
