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

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\User;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Throwable;

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
     * @throws Throwable
     */
    public function execute(): ServerResponse
    {
        $this->leaveGroupChat();

        if ($this->getMessage()->getViaBot() instanceof User && $this->getMessage()->getViaBot()->getId() === $this->getTelegram()->getBotId()) {
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
     * Leave group chats
     *
     * @return ServerResponse|null
     */
    private function leaveGroupChat(): ?ServerResponse
    {
        if (getenv('DEBUG')) {
            return null;
        }

        if (!$this->getMessage()->getChat()->isPrivateChat()) {
            return Request::leaveChat(
                [
                    'chat_id' => $this->getMessage()->getChat()->getId(),
                ]
            );
        }

        return null;
    }

    /**
     * @return ServerResponse|void
     * @throws TelegramException
     */
    public function executeNoDb(): ServerResponse
    {
        return Request::emptyResponse();
    }
}
