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

use Bot\Helper\DebugLog;
use Bot\Manager\Game;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Request;

class CleanCommand extends AdminCommand
{
    protected $name = 'clean';
    protected $description = 'Clean old game messages and set them as empty';
    protected $usage = '/clean';

    public function execute()
    {
        $message = $this->getMessage();
        $edited_message = $this->getUpdate()->getEditedMessage();

        if ($edited_message) {
            $message = $edited_message;
        }

        if ($message) {
            $text = trim($message->getText(true));
        }

        $chat_id = $message->getFrom()->getId();
        $cleanInterval = $this->getConfig('clean_interval');

        if (isset($text) && is_numeric($text) && $text > 0) {
            $cleanInterval = $text;
        }

        if (empty($cleanInterval)) {
            $cleanInterval = 86400;  // 86400 seconds = 1 day
        }

        $game = new Game('_', '_', $this);
        $storage = $game->getStorage();

        $inactive = $storage::action('list', $cleanInterval);

        $cleaned = 0;
        $edited = 0;
        $error = 0;
        foreach ($inactive as $inactive_game) {
            Request::sendChatAction(['chat_id' => $chat_id, 'action' => 'typing']);

            $data = $storage::action('get', $inactive_game['id']);

            if (isset($data['game_code'])) {
                $game = new Game($inactive_game['id'], $data['game_code'], $this);

                if ($game->canRun()) {
                    $result = Request::editMessageText(
                        [
                            'inline_message_id' => $inactive_game['id'],
                            'text' => '<b>' . $game->getGame()::getTitle() . '</b>' . PHP_EOL . PHP_EOL . '<i>' . __("This game session is empty.") . '</i>',
                            'reply_markup' => $this->createInlineKeyboard($data['game_code']),
                            'parse_mode' => 'HTML',
                            'disable_web_page_preview' => true,
                        ]
                    );

                    if ($result->isOk()) {
                        $edited++;
                    } else {
                        $error++;
                        DebugLog::log('Failed to edit message for game ID \'' . $inactive_game['id'] . '\', error: ' . $result->getDescription());
                    }
                }
            }

            DebugLog::log('Cleaned: ' . $inactive_game['id']);

            if ($storage::action('remove', $inactive_game['id'])) {
                $cleaned++;
            }
        }

        $removed = 0;
        if (is_dir($dir = VAR_PATH . '/tmp')) {
            foreach (new \DirectoryIterator($dir) as $file) {
                if (!$file->isDir() && !$file->isDot() && $file->getMTime() < strtotime('-1 minute')) {
                    if (@unlink($dir . '/' . $file->getFilename())) {
                        $removed++;
                    }
                }
            }
        }

        if ($message) {
            $data = [];
            $data['chat_id'] = $chat_id;
            $data['text'] = 'Cleaned ' . $cleaned . ' games, edited ' . $edited . ' messages, ' . $error . ' errored.' . PHP_EOL . 'Removed ' . $removed . ' temporary files!';

            return Request::sendMessage($data);
        }

        return Request::emptyResponse();
    }

    private function createInlineKeyboard($game_code)
    {
        $inline_keyboard = [
            [
                new InlineKeyboardButton(
                    [
                        'text' => __('Create'),
                        'callback_data' => $game_code . ';new'
                    ]
                )
            ]
        ];

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }
}
