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

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\InlineQueryResultArticle;
use Longman\TelegramBot\Entities\InlineKeyboardMarkup;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\InputTextMessageContent;

class InlinequeryCommand extends SystemCommand
{
    protected $name = 'inlinequery';
    protected $description = 'Reply to inline query';

    private $strings = [
        'press_button_to_start' => '<i>Press \'Start\' to start a new session.</i>',
    ];

    public function execute()
    {
        $update = $this->getUpdate();
        $inline_query = $update->getInlineQuery();
        $query = trim($inline_query->getQuery());
        $data = ['inline_query_id' => $inline_query->getId(), 'cache_time' => 300];

        $articles = [];
        $articles[] = [
            'id' => 'g1',
            'title' => 'Tic-Tac-Toe',
            'description' => 'Tic-tac-toe is a game for two players, X and O, who take turns marking the spaces in a 3×3 grid.',
            'input_message_content' => new InputTextMessageContent(
                [
                    'message_text' => '<b>Tic-Tac-Toe</b>' . "\n\n" . $this->strings['press_button_to_start'],
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]
            ),
            'reply_markup' => $this->createInlineKeyboard('g1'),
            'thumb_url' => 'http://i.imgur.com/yU2uexr.png',
        ];

        $articles[] = [
            'id' => 'g2',
            'title' => 'Connect Four',
            'description' => 'Connect Four is a connection game in which the players take turns dropping colored discs from the top into a seven-column, six-row vertically suspended grid.',
            'input_message_content' => new InputTextMessageContent(
                [
                    'message_text' => '<b>Connect Four</b>' . "\n\n" . $this->strings['press_button_to_start'],
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]
            ),
            'reply_markup' => $this->createInlineKeyboard('g2'),
            'thumb_url' => 'http://i.imgur.com/KgH8blx.jpg',
        ];

        $articles[] = [
            'id' => 'g4',
            'title' => 'Rock-Paper-Scissors',
            'description' => 'Rock-paper-scissors is game in which each player simultaneously forms one of three shapes with an outstretched hand.',
            'input_message_content' => new InputTextMessageContent(
                [
                    'message_text' => '<b>Rock-Paper-Scissors</b>' . "\n\n" . $this->strings['press_button_to_start'],
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]
            ),
            'reply_markup' => $this->createInlineKeyboard('g4'),
            'thumb_url' => 'https://i.imgur.com/1H8HI7n.png',
        ];

        $articles[] = [
            'id' => 'g3',
            'title' => 'Checkers  (no flying kings, men cannot capture backwards)',
            'description' => 'Checkers is game in which the goal is to capture the other player\'s checkers or make them impossible to move.',
            'input_message_content' => new InputTextMessageContent(
                [
                    'message_text' => '<b>Checkers</b>' . "\n\n" . $this->strings['press_button_to_start'],
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]
            ),
            'reply_markup' => $this->createInlineKeyboard('g3'),
            'thumb_url' => 'https://i.imgur.com/mYuCwKA.jpg',
        ];

        $articles[] = [
            'id' => 'g5',
            'title' => 'Pool Checkers  (flying kings, men can capture backwards)',
            'description' => 'Checkers is game in which the goal is to capture the other player\'s checkers or make them impossible to move.',
            'input_message_content' => new InputTextMessageContent(
                [
                    'message_text' => '<b>Pool Checkers</b>' . "\n\n" . $this->strings['press_button_to_start'],
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]
            ),
            'reply_markup' => $this->createInlineKeyboard('g5'),
            'thumb_url' => 'https://i.imgur.com/mYuCwKA.jpg',
        ];

        $articles[] = [
            'id' => 'g6',
            'title' => 'Russian Roulette',
            'description' => 'Russian roulette is a game of chance in which a player places a single round in a revolver, spins the cylinder, places the muzzle against their head, and pulls the trigger.',
            'input_message_content' => new InputTextMessageContent(
                [
                    'message_text' => '<b>Russian Roulette</b>' . "\n\n" . $this->strings['press_button_to_start'],
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]
            ),
            'reply_markup' => $this->createInlineKeyboard('g6'),
            'thumb_url' => 'https://i.imgur.com/LffxQLK.jpg',
        ];

        // ----------------------------------------------
        if ($query == 'test') {
            $data['cache_time'] = 10;
            $data['is_personal'] = true;
        }
        
        if ($query == 'dev' && $this->getTelegram()->isAdmin()) {
            $articles[] = [
                'id' => 'g99',
                'title' => 'Dev Test Game',
                'description' => 'Only developer can see this.',
                'input_message_content' => new InputTextMessageContent(
                    [
                        'message_text' => 'Dev',
                    ]
                ),
                'reply_markup' => $this->createInlineKeyboard('g99'),
            ];

            $data['cache_time'] = 10;
            $data['is_personal'] = true;
        }
        // ----------------------------------------------

        $array_article = [];
        foreach ($articles as $article) {
            $array_article[] = new InlineQueryResultArticle($article);
        }
        $data['results'] = '[' . implode(',', $array_article) . ']';

        return Request::answerInlineQuery($data);
    }

    private function createInlineKeyboard($prefix)
    {
        $inline_keyboard = [
            [
                new InlineKeyboardButton(
                    [
                        'text' => "Start",
                        'callback_data' => $prefix . "_new"
                    ]
                )
            ]
        ];

        $inline_keyboard_markup = new InlineKeyboardMarkup(['inline_keyboard' => $inline_keyboard]);

        return $inline_keyboard_markup;
    }
}
