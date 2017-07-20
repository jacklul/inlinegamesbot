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

use Bot\Storage\Driver;
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

        $storage = Driver::getStorageClass();
        $storage::initializeStorage();

        $active = $storage::listFromGame((time() - strtotime('-5 minutes')) * -1);
        $all = $storage::listFromGame(0);

        $data = [];
        $data['chat_id'] = $chat_id;
        $data['text'] = '*Stats:*' . PHP_EOL . ' All games: ' . count($all) . PHP_EOL . ' Active (5 minutes): ' . count($active);
        $data['reply_markup'] = $this->createInlineKeyboard();
        $data['parse_mode'] = 'Markdown';

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
