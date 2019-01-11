<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2019 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;
use Spatie\Emoji\Emoji;

/**
 * Shows the starting "help" message
 */
class HelpCommand extends UserCommand
{
    /**
     * @return \Longman\TelegramBot\Entities\ServerResponse
     *
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $message = $this->getUpdate()->getMessage();
        $edited_message = $this->getUpdate()->getEditedMessage();
        $callback_query = $this->getUpdate()->getCallbackQuery();

        if ($edited_message) {
            $message = $edited_message;
        }

        $chat_id = null;
        $data_query = null;

        if ($message) {
            if (!$message->getChat()->isPrivateChat()) {
                return Request::emptyResponse();
            }

            $chat_id = $message->getChat()->getId();
        } elseif ($callback_query) {
            $chat_id = $callback_query->getMessage()->getChat()->getId();

            $data_query = [];
            $data_query['callback_query_id'] = $callback_query->getId();
        }

        $text = Emoji::wavingHandSign() . ' ';
        $text .= '<b>' . __('Hi!') . '</b>' . PHP_EOL;
        $text .= __('To begin, start a message with {USAGE} in any of your chats or click the {BUTTON} button and then select a chat.', ['{USAGE}' => '<b>\'@' . $this->getTelegram()->getBotUsername() . ' ...\'</b>', '{BUTTON}' => '<b>\'' . __('Play') . '\'</b>']) . PHP_EOL . PHP_EOL;
        $text .= __('This bot is open source - click the {BUTTON} button to go to the repository.', ['{BUTTON}' => '<b>\'Github\'</b>']) . ' <b>' . __('Please report all issues there!') . '</b>';

        $data = [
            'chat_id'                  => $chat_id,
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup'             => $this->createInlineKeyboard(),
        ];

        if ($message) {
            return Request::sendMessage($data);
        } elseif ($callback_query) {
            $data['message_id'] = $callback_query->getMessage()->getMessageId();
            $result = Request::editMessageText($data);
            Request::answerCallbackQuery($data_query);

            return $result;
        }

        return Request::emptyResponse();
    }

    /**
     * Create inline keyboard
     *
     * @return InlineKeyboard
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function createInlineKeyboard()
    {
        $inline_keyboard = [
            [
                new InlineKeyboardButton(
                    [
                        'text'                => __('Play') . ' ' . Emoji::gameDie(),
                        'switch_inline_query' => Emoji::gameDie(),
                    ]
                ),
            ],
            [
                new InlineKeyboardButton(
                    [
                        'text' => __('Rate') . ' ' . Emoji::whiteMediumStar(),
                        'url'  => 'https://telegram.me/storebot?start=' . $this->getTelegram()->getBotUsername(),
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => 'Github ' . Emoji::pageWithCurl(),
                        'url'  => 'https://github.com/jacklul/inlinegamesbot/',
                    ]
                ),
            ],
        ];

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }
}
