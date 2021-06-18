<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2021 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot\Command\Admin;

use Bot\Exception\BotException;
use Bot\Exception\StorageException;
use Bot\GameCore;
use Bot\Storage\Driver\File;
use Bot\Storage\Storage;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

/**
 * Extremely simple stats command
 */
class StatsCommand extends AdminCommand
{
    protected $name = 'stats';
    protected $description = 'Display stats';
    protected $usage = '/stats';

    /**
     * @return ServerResponse
     *
     * @throws BotException
     * @throws StorageException
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $edited_message = $this->getUpdate()->getEditedMessage();
        $callback_query = $this->getUpdate()->getCallbackQuery();

        if ($edited_message) {
            $message = $edited_message;
        }

        $chat_id = null;
        $data_query = null;

        if ($message) {
            $chat_id = $message->getChat()->getId();
        } elseif ($callback_query) {
            $chat_id = $callback_query->getMessage()->getChat()->getId();

            $data_query = [];
            $data_query['callback_query_id'] = $callback_query->getId();
        }

        /** @var File $storage_class */
        $storage_class = Storage::getClass();
        $storage_class::initializeStorage();

        $games = $storage_class::listFromGame();
        $stats = [
            'games'      => [],
            'games_5min' => [],
            'total'      => 0,
            '5min'       => 0,
        ];

        foreach ($games as $game) {
            $data = json_decode($game['data'], true);
            $game_obj = new GameCore($game['id'], $data['game_code'], $this);
            $game_class = $game_obj->getGame();
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
            $output .= ' ' . $game . ' – *' . ($stats['games_5min'][$game] ?? 0) . '* (*' . $stats['games'][$game] . '* total)' . PHP_EOL;
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
     * @throws TelegramException
     */
    private function createInlineKeyboard(): InlineKeyboard
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

        return new InlineKeyboard(...$inline_keyboard);
    }
}
