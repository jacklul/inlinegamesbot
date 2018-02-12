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
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Request;

/**
 * Class GenericmessageCommand
 *
 * Handle text messages
 *
 * @package Longman\TelegramBot\Commands\SystemCommands
 */
class GenericmessageCommand extends SystemCommand
{
    /**
     * @return mixed
     *
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function executeNoDb()
    {
        $this->leaveGroupChat();

        return $this->getTelegram()->executeCommand('help');
    }

    /**
     * @return mixed
     *
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $this->leaveGroupChat();

        $conversation = new Conversation(
            $this->getMessage()->getFrom()->getId(),
            $this->getMessage()->getChat()->getId()
        );

        if ($conversation->exists() && ($command = $conversation->getCommand())) {
            return $this->telegram->executeCommand($command);
        }

        if (strpos($this->getMessage()->getText(true), 'This game session is empty.') !== false) {
            return Request::emptyResponse();
        }

        return $this->getTelegram()->executeCommand('help');
    }

    /**
     * Leave group chats
     *
     * @return bool|\Longman\TelegramBot\Entities\ServerResponse
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
