<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2021 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot\Command\System;

use Bot\Exception\BotException;
use Bot\Exception\StorageException;
use Bot\Exception\TelegramApiException;
use Bot\GameCore;
use Bot\Helper\Utilities;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Throwable;

/**
 * Handle button presses
 *
 * @noinspection PhpUndefinedClassInspection
 */
class CallbackqueryCommand extends SystemCommand
{
    /**
     * Callback data before first ';' symbol -> command bind
     *
     * @var array
     */
    private $aliases = [
        'stats' => 'stats',
    ];

    /**
     * @return bool|ServerResponse|mixed
     *
     * @throws TelegramException
     * @throws BotException
     * @throws StorageException
     * @throws TelegramApiException
     * @throws Throwable
     */
    public function execute(): ServerResponse
    {
        $callback_query = $this->getUpdate()->getCallbackQuery();
        $data = $callback_query->getData();

        Utilities::debugPrint('Data: ' . $data);

        $command = explode(';', $data)[0];

        if (isset($this->aliases[$command]) && $this->getTelegram()->getCommandObject($this->aliases[$command])) {
            return $this->getTelegram()->executeCommand($this->aliases[$command]);
        }

        if (($inline_message_id = $callback_query->getInlineMessageId()) && $this->isDataValid($data)) {
            $game = new GameCore($inline_message_id, explode(';', $data)[0], $this);

            if ($game->canRun()) {
                return $game->run();
            }
        }

        return Request::answerCallbackQuery(
            [
                'callback_query_id' => $callback_query->getId(),
                'text'              => __("Bad request!"),
                'show_alert'        => true,
            ]
        );
    }

    /**
     * Validate callback data
     *
     * @param string $data
     *
     * @return bool
     */
    private function isDataValid(string $data): bool
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $data = explode(';', $data);

        if (count($data) >= 2) {
            return true;
        }

        return false;
    }
}
