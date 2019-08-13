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

use Bot\GameCore;
use Bot\Helper\Utilities;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

/**
 * Handle text messages
 *
 * @noinspection PhpUndefinedClassInspection
 */
class GenericmessageCommand extends SystemCommand
{
    /**
     * @return mixed
     *
     * @throws TelegramException
     * @throws \Bot\Exception\BotException
     * @throws \Bot\Exception\StorageException
     * @throws \Bot\Exception\TelegramApiException
     * @throws \Throwable
     */
    public function execute()
    {
        if ($chosen_inline_result = $this->getUpdate()->getChosenInlineResult()) {
            Utilities::debugPrint('Data: ' . $chosen_inline_result->getResultId());

            $game = new GameCore($chosen_inline_result->getInlineMessageId(), $chosen_inline_result->getResultId(), $this);

            if ($game->canRun()) {
                return $game->run();
            }

            return Request::emptyResponse();
        }

        $this->leaveGroupChat();

        if (strpos($this->getMessage()->getText(true), 'This game session is empty.') !== false) {
            return Request::emptyResponse();
        }

        $conversation = new Conversation(
            $this->getMessage()->getFrom()->getId(),
            $this->getMessage()->getChat()->getId()
        );

        if ($conversation->exists() && ($command = $conversation->getCommand())) {
            return $this->telegram->executeCommand($command);
        }

        return $this->executeNoDb();
    }

    /**
     * @return ServerResponse|void
     * @throws TelegramException
     */
    public function executeNoDb()
    {
        return Request::emptyResponse();
    }

    /**
     * Leave group chats
     *
     * @return bool|ServerResponse
     */
    private function leaveGroupChat()
    {
        if (getenv('DEBUG')) {
            return false;
        }

        if (!$this->getMessage()->getChat()->isPrivateChat()) {
            return Request::leaveChat(
                [
                    'chat_id' => $this->getMessage()->getChat()->getId(),
                ]
            );
        }

        return false;
    }
}
