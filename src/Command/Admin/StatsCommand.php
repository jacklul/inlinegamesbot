<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use Bot\Manager\Game;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;

/**
 * Class StatsCommand
 *
 * @package Longman\TelegramBot\Commands\AdminCommands
 */
class StatsCommand extends AdminCommand
{
    protected $name = 'stats';
    protected $description = 'Display stats';
    protected $usage = '/stats';

    /**
     * @return \Longman\TelegramBot\Entities\ServerResponse
     */
    public function execute()
    {
        $message = $this->getMessage();
        $edited_message = $this->getUpdate()->getEditedMessage();
        $callback_query = $this->getUpdate()->getCallbackQuery();

        if ($edited_message) {
            $message = $edited_message;
        }

        if ($message) {
            $chat_id = $message->getChat()->getId();
        } elseif ($callback_query) {
            $chat_id = $callback_query->getMessage()->getChat()->getId();

            $data_query = [];
            $data_query['callback_query_id'] = $callback_query->getId();
        }

        $game = new Game('_', '_', $this);
        $storage = $game->getStorage();

        $active = $storage::listFromStorage((time() - strtotime('-5 minutes')) * -1);

        $data = [];
        $data['chat_id'] = $chat_id;
        $data['text'] = 'Stats: (5 minutes)' . PHP_EOL . ' Active games: ' . count($active);
        $data['reply_markup'] = $this->createInlineKeyboard();

        if ($message) {
            return Request::sendMessage($data);
        } elseif ($callback_query) {
            $data['message_id'] = $callback_query->getMessage()->getMessageId();
            Request::editMessageText($data);
            return Request::answerCallbackQuery($data_query);
        }

        return Request::emptyResponse();
    }

    /**
     * Create inline keyboard that will refresh this message
     * 
     * @return InlineKeyboard
     */
    private function createInlineKeyboard()
    {
        $inline_keyboard = [
            [
                new InlineKeyboardButton(
                    [
                        'text' => 'Refresh',
                        'callback_data' => 'stats;refresh'
                    ]
                )
            ]
        ];

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }
}
