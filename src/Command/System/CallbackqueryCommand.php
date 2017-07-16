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

use Bot\Helper\DebugLog;
use Bot\Manager\Game;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Request;

/**
 * Class CallbackqueryCommand
 *
 * @package Longman\TelegramBot\Commands\SystemCommands
 */
class CallbackqueryCommand extends SystemCommand
{
    public function execute()
    {
        $callback_query = $this->getUpdate()->getCallbackQuery();

        DebugLog::log('Data: ' . $callback_query->getData());

        $game = new Game($callback_query->getInlineMessageId(), explode(';', $callback_query->getData())[0], $this);

        if ($game->canRun()) {
            return $game->run();
        }

        return Request::answerCallbackQuery(
            [
            'callback_query_id' => $callback_query->getId(),
            'text' => __("Bad request!"),
            'show_alert' => true,
            ]
        );
    }
}
