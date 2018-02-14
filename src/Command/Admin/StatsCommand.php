<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use Bot\Entity\GameManager;
use Bot\Helper\Storage;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;

/**
 * Class StatsCommand
 *
 * Extremely simple stats command
 *
 * @package Longman\TelegramBot\Commands\AdminCommands
 */
class StatsCommand extends AdminCommand
{
    protected $name = 'stats';
    protected $description = 'Display stats';
    protected $usage = '/stats';
    protected $private_only = true;

    /**
     * @return \Longman\TelegramBot\Entities\ServerResponse
     *
     * @throws \Bot\Exception\BotException
     * @throws \Bot\Exception\StorageException
     * @throws \Longman\TelegramBot\Exception\TelegramException
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

        /** @var \Bot\Storage\File $storage_class */
        $storage_class = Utilities::getStorageClass();
        $storage_class::initializeStorage();

        $games = $storage_class::listFromGame(0);
        $stats = [
            'games'      => [],
            'games_5min' => [],
            'total'      => 0,
            '5min'       => 0,
        ];

        foreach ($games as $game) {
            $data = json_decode($game['data'], true);
            $game_obj = new GameManager($game['id'], $data['game_code'], $this);
            $game_class = $game->getGame();
            $game_title = $game_class::getTitle();

            if (isset($stats['games'][$game_title])) {
                $stats['games'][$game_title] = $stats['games'][$game_title] + 1;
            } else {
                $stats['games'][$game_title] = 1;
            }

            if (!isset($stats['games_5min'][$game_title])) {
                $stats['games_5min'][$game_title] = 0;
            }

            if (strtotime($game['updated_at']) >= strtotime('-5 minutes')) {
                $stats['games_5min'][$game_title] = $stats['games_5min'][$game_title] + 1;
                $stats['5min']++;
            }

            $stats['total']++;
        }

        $stats['games']['All'] = $stats['total'];
        $stats['games_5min']['All'] = $stats['5min'];

        $output = '*Active sessions:*' . PHP_EOL;

        arsort($stats['games_5min']);

        foreach ($stats['games_5min'] as $game => $value) {
            $output .= ' ' . $game . ' - *' . (isset($stats['games_5min'][$game]) ? $stats['games_5min'][$game] : 0) . '* (*' . $stats['games'][$game] . '* total)' . PHP_EOL;
        }

        $data = [];
        $data['chat_id'] = $chat_id;
        $data['text'] = $output;
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
                        'text'          => 'Refresh',
                        'callback_data' => 'stats;refresh',
                    ]
                ),
            ],
        ];

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }
}
