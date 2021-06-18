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
use Bot\Helper\Utilities;
use Bot\Storage\Driver\File;
use Bot\Storage\Storage;
use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

/**
 * This command will clean games that were inactive for a longer period and
 * edit their message to indicate that they expired
 */
class CleansessionsCommand extends AdminCommand
{
    protected $name = 'cleansessions';
    protected $description = 'Clean old game messages and set them as empty';
    protected $usage = '/cleansessions';

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
        if (PHP_SAPI !== 'cli') {
            set_time_limit(10);
        }

        /** @var File $storage_class */
        $storage_class = Storage::getClass();

        if (class_exists($storage_class)) {
            $timeout = 10;
            do {
                try {
                    $storage_init = $storage_class::initializeStorage();
                } catch (StorageException $e) {
                    if ($timeout < 0 || strpos($e, 'too many connections') === false) {
                        throw $e;
                    }
                }

                $timeout--;
                sleep(1);
            } while (!isset($storage_init));

            $inactive = $storage_class::listFromGame($cleanInterval);

            if (is_array($inactive) && count($inactive) > 0) {
                $chat_action_start = 0;
                $last_request_time = 0;
                $timelimit = ini_get('max_execution_time') > 0 ? ini_get('max_execution_time') : 59;
                $start_time = time();

                $hours = floor($cleanInterval / 3600);
                $minutes = floor(($cleanInterval / 60) % 60);
                $seconds = $cleanInterval % 60;

                $data['text'] = 'Cleaning games older than ' . $hours . 'h ' . $minutes . 'm ' . $seconds . 's' . '... (time limit: ' . $timelimit . ' seconds)';

                if ($chat_id != $bot_id) {
                    Request::sendMessage($data);
                } elseif (PHP_SAPI === 'cli') {
                    print $data['text'] . PHP_EOL;
                }

                $cleaned = 0;
                $edited = 0;

                foreach ($inactive as $inactive_game) {
                    if (time() >= $start_time + $timelimit - 1) {
                        Utilities::debugPrint('Time limit reached');
                        break;
                    }

                    if ($chat_id != $bot_id && $chat_action_start < strtotime('-5 seconds')) {
                        Request::sendChatAction(['chat_id' => $chat_id, 'action' => 'typing']);
                        $chat_action_start = time();
                    }

                    $inactive_game['id'] = trim($inactive_game['id']);

                    if (PHP_SAPI === 'cli' && $chat_id == $bot_id) {
                        print 'Cleaning: \'' . $inactive_game['id'] . '\'' . PHP_EOL;
                    }

                    $game_data = $storage_class::selectFromGame($inactive_game['id']);

                    if (isset($game_data['game_code'])) {
                        $game = new GameCore($inactive_game['id'], $game_data['game_code'], $this);

                        if ($game->canRun()) {
                            while (time() <= $last_request_time) {
                                Utilities::debugPrint('Delaying next request');
                                sleep(1);
                            }

                            $game_class = $game->getGame();

                            if (isset($game_data['game_data']['current_turn']) && $game_data['game_data']['current_turn'] === 'E') {
                                $result = Request::editMessageReplyMarkup(
                                    [
                                        'inline_message_id'        => $inactive_game['id'],
                                        'reply_markup'             => $this->createInlineKeyboard($game_data['game_code'], __('Create New Session')),
                                    ]
                                );
                            } else {
                                $result = Request::editMessageText(
                                    [
                                        'inline_message_id'        => $inactive_game['id'],
                                        'text'                     => '<b>' . $game_class::getTitle() . '</b>' . PHP_EOL . PHP_EOL . '<i>' . __("This game session has expired.") . '</i>',
                                        'reply_markup'             => $this->createInlineKeyboard($game_data['game_code']),
                                        'parse_mode'               => 'HTML',
                                        'disable_web_page_preview' => true,
                                    ]
                                );
                            }

                            $last_request_time = time();

                            if (isset($result) && $result->isOk()) {
                                $edited++;
                                Utilities::debugPrint('Message edited successfully');
                            } else {
                                Utilities::debugPrint('Failed to edit message: ' . (isset($result) ? $result->getDescription() : '<unknown error>'));
                            }
                        }
                    }

                    if ($storage_class::deleteFromGame($inactive_game['id'])) {
                        $cleaned++;
                        Utilities::debugPrint('Record removed from the database');
                    }
                }

                $data['text'] = 'Cleaned ' . $cleaned . ' games (edited ' . $edited . ' messages).';
            } else {
                $data['text'] = 'Nothing to clean!';
            }
        } else {
            $data['text'] = 'Error!';
        }

        if ($chat_id != $bot_id) {
            return Request::sendMessage($data);
        } elseif (PHP_SAPI === 'cli') {
            print $data['text'] . PHP_EOL;
        }

        return Request::emptyResponse();
    }

    /**
     * Create inline keyboard with button that creates the game session
     *
     * @param string      $game_code
     * @param string|null $text
     *
     * @return InlineKeyboard
     * @throws TelegramException
     */
    private function createInlineKeyboard(string $game_code, $text = null): InlineKeyboard
    {
        $inline_keyboard = [
            [
                new InlineKeyboardButton(
                    [
                        'text'          => $text ?: __('Create'),
                        'callback_data' => $game_code . ';new',
                    ]
                ),
            ],
        ];

        return new InlineKeyboard(...$inline_keyboard);
    }
}
