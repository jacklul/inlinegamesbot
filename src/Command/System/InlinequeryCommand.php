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

use Bot\Entity\Game;
use DirectoryIterator;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultArticle;
use Longman\TelegramBot\Entities\InputMessageContent\InputTextMessageContent;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

/**
 * Handle incoming inline queries, shows game list no matter what user enters
 *
 * @noinspection PhpUndefinedClassInspection
 */
class InlinequeryCommand extends SystemCommand
{
    /**
     * @return ServerResponse
     *
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        $articles = [];

        foreach ($this->getGamesList() as $game) {
            /** @var Game $game_class */
            if (class_exists($game_class = $game['class'])) {
                $articles[] = [
                    'id'                    => $game_class::getCode(),
                    'title'                 => $game_class::getTitle() . (method_exists($game_class, 'getTitleExtra') ? ' ' . $game_class::getTitleExtra() : ''),
                    'description'           => $game_class::getDescription(),
                    'input_message_content' => new InputTextMessageContent(
                        [
                            'message_text'             => '<b>' . $game_class::getTitle() . '</b>' . PHP_EOL . PHP_EOL . '<i>' . __('This game session is empty.') . '</i>',
                            'parse_mode'               => 'HTML',
                            'disable_web_page_preview' => true,
                        ]
                    ),
                    'reply_markup'          => $this->createInlineKeyboard($game_class::getCode()),
                    'thumb_url'             => $game_class::getImage(),
                ];
            }
        }

        $array_article = [];
        foreach ($articles as $article) {
            $array_article[] = new InlineQueryResultArticle($article);
        }

        $result = Request::answerInlineQuery(
            [
                'inline_query_id'     => $this->getUpdate()->getInlineQuery()->getId(),
                'cache_time'          => 60,
                'results'             => '[' . implode(',', $array_article) . ']',
                'switch_pm_text'      => 'Help',
                'switch_pm_parameter' => 'start',
            ]
        );

        return $result;
    }

    /**
     * Get games list
     *
     * @return array
     */
    private function getGamesList(): array
    {
        $games = [];
        if (is_dir(SRC_PATH . '/Entity/Game')) {
            foreach (new DirectoryIterator(SRC_PATH . '/Entity/Game') as $file) {
                if (!$file->isDir() && !$file->isDot() && $file->getExtension() === 'php') {
                    /** @var Game $game_class */
                    $game_class = '\Bot\Entity\Game\\' . basename($file->getFilename(), '.php');

                    $games[] = [
                        'class' => $game_class,
                        'order' => $game_class::getOrder(),
                    ];
                }
            }
        }

        usort(
            $games,
            static function ($item1, $item2) {
                return $item1['order'] <=> $item2['order'];
            }
        );

        return $games;
    }

    /**
     * Create inline keyboard with button that creates the game session
     *
     * @param string $game_code
     *
     * @return InlineKeyboard
     * @throws TelegramException
     */
    private function createInlineKeyboard(string $game_code): InlineKeyboard
    {
        $inline_keyboard = [
            [
                new InlineKeyboardButton(
                    [
                        'text'          => __('Create'),
                        'callback_data' => $game_code . ';new',
                    ]
                ),
            ],
        ];

        return new InlineKeyboard(...$inline_keyboard);
    }
}
