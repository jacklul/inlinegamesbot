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

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\State;
use Longman\TelegramBot\DBHelper;
use Longman\TelegramBot\Entities\InlineKeyboardMarkup;
use Longman\TelegramBot\Entities\InlineKeyboardButton;

class ContactCommand extends UserCommand
{
    protected $name = 'contact';
    protected $need_mysql = true;
    protected $enabled = false;

    public function execute()
    {
        $message = $this->getMessage();
        $callback_query = $this->getUpdate()->getCallbackQuery();

        if ($message) {
            $chat_id = $message->getChat()->getId();
            $user_id = $message->getFrom()->getId();
            $mention = $message->getFrom()->tryMention();

            $text = trim($message->getText(true));
            $command = trim($message->getCommand());

            $type = null;
            $object = null;

            $photo = $message->getPhoto();
            if ($photo) {
                $type = 'photo';
                $object = $photo[count($photo) - 1]->getFileId();
                $text = $message->getCaption();
            }
        } elseif ($callback_query) {
            $chat_id = $callback_query->getMessage()->getChat()->getId();
            $user_id = $callback_query->getFrom()->getId();
            $mention = $callback_query->getFrom()->tryMention();

            $text = '';
            $command = $callback_query->getData();

            $data_query = [];
            $data_query['callback_query_id'] = $callback_query->getId();
        }

        // DISABLED
        $disabled_text = "Sorry, but this feature has been disabled!\n\nWhy?\nBecause people kept spamming it with useless things.";

        if ($message) {
            $data = [];
            $data['chat_id'] = $chat_id;
            $data['text'] = $disabled_text;
            return Request::sendMessage($data);
        } elseif ($callback_query) {
            $data_query['text'] = $disabled_text;
            $data_query['show_alert'] = true;
            return Request::answerCallbackQuery($data_query);
        }
        // DISABLED

        $lines = file(__DIR__ . '/contact_banned.txt');
        foreach ($lines as $line) {
            if (trim($line) == $user_id) {
                if ($callback_query) {
                    $data_query['text'] = 'You\'re banned from using this!';
                    $data_query['show_alert'] = true;
                    return Request::answerCallbackQuery($data_query);
                } else {
                    return Request::emptyResponse();
                }
            }
        }

        $alreadySent = DBHelper::query('SELECT * FROM `contact` WHERE `user_id` = ' . $user_id .' AND `created_at` >= \'' . date('Y-m-d H:i:s', strtotime("-90 seconds")) . '\' ORDER BY `created_at` DESC');

        if (count($alreadySent) > 0 && !$this->getTelegram()->isAdmin($user_id)) {
            $timeSent = strtotime($alreadySent[0]['created_at']);
            $towait = 90 - (time() - $timeSent);

            if ($callback_query) {
                $data_query['text'] = 'You\'ve already sent a message recently, please wait '.$towait.' seconds!';
                $data_query['show_alert'] = true;
                return Request::answerCallbackQuery($data_query);
            } else {
                return Request::emptyResponse();
            }
        }

        if ($callback_query) {
            Request::answerCallbackQuery($data_query);
        }

        $data = [];
        $data['chat_id'] = $chat_id;
        $data['parse_mode'] = 'HTML';
        $data['disable_web_page_preview'] = true;

        if ($callback_query) {
            $state = new State($user_id);

            if ($state->exists()) {
                return Request::answerCallbackQuery($data_query);
            }
        }

        $state = new State($user_id, $this->getName());

        if ($text === '' && !$object) {
            if ($command != "reply") {
                $data['text'] = "⚠ <b>This is not a chat - send only questions, suggestions or issues.\n Messages like 'hi', 'hello' and 'play with me' will be ignored!\n Please write your message in English or it won't be read!\n</b>";
                Request::sendMessage($data);
            }

            $text = "✏️ <b>Please send your message now.</b>\n\nClick '<b>Cancel</b>' button to abort.";

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

            $state->notes['state'] = 'input_message';
            $state->update();
        } elseif ($text != '' || $object) {
            $ignored_text = [
                '@inlinegamesbot',
                $mention,
            ];

            foreach ($ignored_text as $to_ignore) {
                if (trim($text) == $to_ignore) {
                    return Request::emptyResponse();
                }
            }

            $ignored_text = [
                'Please send your message now',
                'This is not a chat - send only questions, suggestions or issues.',
                'You must provide a message (as a caption)',
                'You can\'t play with the developer this way',
                'Your message is too short!',
                'Please use English language.',
            ];

            foreach ($ignored_text as $to_ignore) {
                if (strpos($text, $to_ignore) !== false) {
                    return Request::emptyResponse();
                }
            }

            $ignored_text = [
                'Press \'Start\' to start a new session.',
                'is waiting for opponent to join...',
                'This game session is empty',
                'To begin, start a message with',
            ];

            foreach ($ignored_text as $to_ignore) {
                if (strpos($text, $to_ignore) !== false) {
                    $data['text'] = '❗ <b>You can\'t play with the developer this way.</b>';
                    return Request::sendMessage($data);
                }
            }

            if ($object && $text == '') {
                $data['text'] = '❗ <b>You must provide a message (as a caption)!</b>';
                return Request::sendMessage($data);
            }

            $test = preg_replace("/[^a-zA-Z0-9]+/", "", $text);
            if (strlen($test) == 0) {
                $data['text'] = '❗ <b>Please use English language.</b>';
                return Request::sendMessage($data);
            }

            if (strlen($text) <= 10) {
                $data['text'] = '❗ <b>Your message is too short!</b>';
                return Request::sendMessage($data);
            }

            if (!$object) {
                DBHelper::query('INSERT INTO `contact` (`user_id`, `mention`, `text`, `created_at`) VALUES (' . $user_id . ', \'' . $mention . '\', ' . DBHelper::quote(strip_tags($text)) . ', \'' . date('Y-m-d H:i:s', time()) . '\')');
            } else {
                DBHelper::query('INSERT INTO `contact` (`user_id`, `mention`, `text`, `object`, `file_id`, `created_at`) VALUES (' . $user_id . ', \'' . $mention . '\', ' . DBHelper::quote(strip_tags($text)) . ', \'' . $type .'\', \'' . $object .'\', \'' . date('Y-m-d H:i:s', time()) . '\')');
            }

            foreach ($this->getTelegram()->getAdminList() as $admin) {
                $inline_keyboard = [
                    [
                        new InlineKeyboardButton(
                            [
                                'text' => "Reply 📝",
                                'callback_data' => 'sendto_' . $message->getFrom()->getId()
                            ]
                        )
                    ]
                ];

                $data_notifyadmin = [];
                $data_notifyadmin['chat_id'] = $admin;

                if ($type == 'photo') {
                    $data_notifyadmin['photo'] = $object;
                    $data_notifyadmin['caption'] = '📃 Message from ' . $message->getFrom()->tryMention() . "\n\n" . strip_tags($caption);
                    $data_notifyadmin['reply_markup'] = new InlineKeyboardMarkup(['inline_keyboard' => $inline_keyboard]);
                    $this_result = Request::sendPhoto($data_notifyadmin);
                } else {
                    $data_notifyadmin['text'] = '📃 <b>Message from</b> ' . $message->getFrom()->tryMention() . "\n\n" . strip_tags($text);
                    $data_notifyadmin['parse_mode'] = 'HTML';
                    $data_notifyadmin['reply_markup'] = new InlineKeyboardMarkup(['inline_keyboard' => $inline_keyboard]);
                    $this_result = Request::sendMessage($data_notifyadmin);
                }

                if ($this_result) {
                    $result = $this_result;
                }
            }

            if ($result && $result->isOk()) {
                $text = '✅ <b>Your message was sent!</b>';
                $state->stop();
            } else {
                $text = '❗ <b>Couldn\'t send your message, try again later!</b>';
                $state->cancel();
            }
        } else {
            $text = '❓ <b>Invalid message content!</b>'."\n".'Operation cancelled!';
            $state->cancel();
        }

        $data['text'] = $text;

        return Request::sendMessage($data);
    }
}
