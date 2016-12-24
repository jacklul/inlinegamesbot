<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * Copyright (c) 2016 Jack'lul <https://jacklul.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\State;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\DBHelper;
use Longman\TelegramBot\Entities\InlineKeyboardMarkup;
use Longman\TelegramBot\Entities\InlineKeyboardButton;

class ReplyCommand extends AdminCommand
{
    protected $name = 'reply';
    protected $description = 'Reply to contact message';
    protected $usage = '/reply <chat id>';
    protected $need_mysql = true;

    /*
     Callback Query:
    'contact' => 'contact',
    'reply' => 'contact',
    'sendto' => 'reply',
    'cancel' => 'cancel',
     */

    private $state;
    private $msg_emoji = '📃 '; // Dirty workaround for PHPStorm marking emoji in long strings are errors
    
    public function execute()
    {
        $message = $this->getMessage();
        $callback_query = $this->getUpdate()->getCallbackQuery();

        if ($message) {
            $chat_id = $message->getChat()->getId();
            $user_id = $message->getFrom()->getId();

            $text = trim($message->getText(true));
            $command = trim($message->getCommand());

            $testtext = explode('reply', $command);
            if (is_numeric($testtext[1])) {
                $text = $testtext[1];
            }

            $type = null;
            $object = null;

            $photo = $message->getPhoto();
            if ($photo) {
                $type = 'photo';
                $object = $photo[count($photo) - 1]->getFileId();
                $caption = $message->getCaption();
            }
        } elseif ($callback_query) {
            $chat_id = $callback_query->getMessage()->getChat()->getId();
            $user_id = $callback_query->getFrom()->getId();

            $text = '';
            $command = $callback_query->getData();

            $testtext = explode('_', $command);
            if (is_numeric($testtext[1])) {
                $text = $testtext[1];
            }

            $data_query = [];
            $data_query['callback_query_id'] = $callback_query->getId();
            Request::answerCallbackQuery($data_query);
        }

        if (strpos($text, 'Press \'Start\' to start a new session.')) {
            return Request::emptyResponse();
        }

        $data = [];
        $data['chat_id'] = $chat_id;

        $this->state = new State($user_id, $this->getName());

        if (!isset($this->state->notes['state'])) {
            $state = 'main';
        } else {
            $state = $this->state->notes['state'];
        }

        if ($text === '' && !$object && $state != 'input_message') {
            $text = "Usage:\n /".$this->name." &lt;chat id&gt;\n";
        } else {
            if (empty($this->state->notes['target'])) {
                $text = explode(' ', $text);

                if (is_numeric($text[0])) {
                    $this->state->notes['target'] = $text[0];
                    $this->state->update();
                } else {
                    $text = "Invalid chat id!";
                }
            }

            if ($this->state->notes['target'] == $text) {
                $this->state->notes['state'] = 'ask_for_input';
                $state = 'ask_for_input';
                $this->state->update();
            }

            if (is_numeric($this->state->notes['target'])) {
                $query = DBHelper::query('SELECT * FROM `user` WHERE `id` = ' . $this->state->notes['target']);

                if ($query) {
                    if (empty($query[0]['username'])) {
                        $owner_name = $query[0]['first_name'];

                        if (!empty($query[0]['last_name'])) {
                            $owner_name .= ' ' . $query[0]['last_name'];
                        }

                        $owner_name = '<b>' . $owner_name . '</b>';
                    } else {
                        $owner_name = '@' . $query[0]['username'];
                    }
                }

                switch ($state) {
                    case "main":
                        $this->state->notes['state'] = 'ask_for_input';
                        $this->state->update();
                    // no break;
                    case "ask_for_input":
                        $text = "✏️ <b>Please send your message now.</b>\n (Sending to: " . $owner_name . ")\n\nClick '<b>Cancel</b>' button to abort.";

                        $inline_keyboard = [
                            [
                                new InlineKeyboardButton(
                                    [
                                        'text' => "Cancel 🚫",
                                        'callback_data' => 'cancel'
                                    ]
                                )
                            ]
                        ];

                        $data['reply_markup'] = new InlineKeyboardMarkup(['inline_keyboard' => $inline_keyboard]);

                        $this->state->notes['state'] = 'input_message';
                        $this->state->update();
                        break;
                    case "input_message":
                        if ($text != '' || $object) {
                            $target = $this->state->notes['target'];

                            $inline_keyboard = [
                                [
                                    new InlineKeyboardButton(
                                        [
                                            'text' => "Reply 📝",
                                            'callback_data' => 'reply'
                                        ]
                                    )
                                ]
                            ];

                            $data_send = [];
                            $data_send['chat_id'] = $target;

                            if ($type == 'photo') {
                                $data_send['photo'] = $object;
                                $data_send['caption'] = $this->msg_emoji . "Message from the developer\n\n" . strip_tags($caption);
                                $data_send['reply_markup'] = new InlineKeyboardMarkup(['inline_keyboard' => $inline_keyboard]);
                                $result = Request::sendPhoto($data_send);
                            } else {
                                $data_send['text'] = $this->msg_emoji . "<b>Message from the developer</b>\n\n" . strip_tags($text);
                                $data_send['parse_mode'] = 'HTML';
                                $data_send['reply_markup'] = new InlineKeyboardMarkup(['inline_keyboard' => $inline_keyboard]);
                                $result = Request::sendMessage($data_send);
                            }

                            if ($result && $result->isOk()) {
                                $text = '<b>Message sent successfully to:</b> ' . $owner_name . "\n";
                                $this->state->stop();
                            } else {
                                $text = '<b>Couldn\'t send message to:</b> ' . $owner_name . "\n";
                                $this->state->cancel();
                            }
                        } else {
                            $text = '<b>Invalid message content!</b>'."\n".'Operation cancelled!';
                            $this->state->cancel();
                        }
                        break;
                }
            }
        }

        $data['text'] = $text;
        $data['parse_mode'] = 'HTML';
        $data['disable_web_page_preview'] = true;

        return Request::sendMessage($data);
    }
}
