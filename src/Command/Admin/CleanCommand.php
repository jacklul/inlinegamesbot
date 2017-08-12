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

use Bot\Helper\Debug;
use Bot\Helper\Storage;
use Bot\Manager\Game as GameManager;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;

/**
 * Class CleanCommand
 *
 * @package Longman\TelegramBot\Commands\AdminCommands
 */
class CleanCommand extends AdminCommand
{
    protected $name = 'clean';
    protected $description = 'Clean old game messages and set them as empty';
    protected $usage = '/clean';
    protected $private_only = true;

    /**
     * @return \Longman\TelegramBot\Entities\ServerResponse
     */
    public function execute()
    {
        $message = $this->getMessage();
        $edited_message = $this->getUpdate()->getEditedMessage();

        if ($edited_message) {
            $message = $edited_message;
        }

        $chat_id = $message->getFrom()->getId();
        $bot_id = $this->getTelegram()->getBotId();
        $text = trim($message->getText(true));

        $data = [];
        $data['chat_id'] = $chat_id;

        $cleanInterval = $this->getConfig('clean_interval');

        if (isset($text) && is_numeric($text) && $text > 0) {
            $cleanInterval = $text;
        }

        if (empty($cleanInterval)) {
            $cleanInterval = 86400;  // 86400 seconds = 1 day
        }

        // Bug workaround: When run from the webhook and script just keeps going for too long Bot API will resend the update triggering the command again... and again...
        if (!defined("STDIN")) {
            set_time_limit(10);
        }

        $storage = Storage::getClass();

        if (class_exists($storage)) {
            $storage::initializeStorage();
            $inactive = $storage::listFromGame($cleanInterval);

            if (is_array($inactive) && count($inactive) > 0) {
                $chat_action_start = 0;
                $last_request_time = 0;
                $timelimit = ini_get('max_execution_time') > 0 ?: 60;
                $start_time = time();

                $data['text'] = 'Cleaning games older than ' . gmdate("H\h i\m s\s", $cleanInterval) . '... (time limit: ' . $timelimit . ' seconds)';

                if ($chat_id != $bot_id) {
                    Request::sendMessage($data);
                } elseif (defined("STDIN")) {
                    print $data['text'] . PHP_EOL;
                }

                $cleaned = 0;
                $edited = 0;

                foreach ($inactive as $inactive_game) {
                    if (time() >= $start_time + $timelimit - 1) {
                        Debug::print('Time limit reached');
                        break;
                    }

                    if ($chat_id != $bot_id && $chat_action_start < strtotime('-5 seconds')) {
                        Request::sendChatAction(['chat_id' => $chat_id, 'action' => 'typing']);
                        $chat_action_start = time();
                    }

                    $inactive_game['id'] = trim($inactive_game['id']);

                    if (defined("STDIN") && $chat_id == $bot_id) {
                        print 'Cleaning: \'' . $inactive_game['id'] . '\'' . PHP_EOL;
                    }

                    $game_data = $storage::selectFromGame($inactive_game['id']);

                    if (isset($game_data['game_code'])) {
                        $game = new GameManager($inactive_game['id'], $game_data['game_code'], $this);

                        if ($game->canRun()) {
                            while (time() <= $last_request_time) {
                                Debug::print('Delaying next request');
                                sleep(1);
                            }

                            $result = Request::editMessageText(
                                [
                                    'inline_message_id'        => $inactive_game['id'],
                                    'text'                     => '<b>' . $game->getGame()::getTitle() . '</b>' . PHP_EOL . PHP_EOL . '<i>' . __("This game session has expired.") . '</i>',
                                    'reply_markup'             => $this->createInlineKeyboard($game_data['game_code']),
                                    'parse_mode'               => 'HTML',
                                    'disable_web_page_preview' => true,
                                ]
                            );

                            $last_request_time = time();

                            if (isset($result) && $result->isOk()) {
                                $edited++;
                                Debug::print('Message edited successfully');
                            } else {
                                Debug::print('Failed to edit message: ' . (isset($result) ? $result->getDescription() : '<unknown error>'));
                            }
                        }
                    }

                    if ($storage::deleteFromGame($inactive_game['id'])) {
                        $cleaned++;
                        Debug::print('Record removed from the database');
                    }
                }

                $data['text'] = 'Cleaned ' . $cleaned . ' games (edited ' . $edited . ' messages).';

                if (defined("STDIN")) {
                    print $data['text'] . PHP_EOL;
                }
            } else {
                $data['text'] = 'Nothing to clean!';
            }
        } else {
            $data['text'] = 'Error!';
        }

        if ($chat_id != $bot_id) {
            return Request::sendMessage($data);
        }

        return Request::emptyResponse();
    }

    /**
     * Create inline keyboard with button that creates the game session
     *
     * @param $game_code
     *
     * @return InlineKeyboard
     */
    private function createInlineKeyboard($game_code)
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

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }
}
