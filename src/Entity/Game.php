<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Entity;

use Bot\Exception\BotException;
use Bot\Helper\Debug;
use Bot\Manager\Game as GameManager;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\User;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;

/**
 * Class Game
 *
 * @package Bot\Entity
 */
class Game
{
    /**
     * Game data
     *
     * @var mixed
     */
    protected $data;

    /**
     * Game Manager object
     *
     * @var GameManager
     */
    protected $manager;

    /**
     * Game constructor.
     *
     * @param GameManager $manager
     */
    public function __construct(GameManager $manager)
    {
        $this->manager = $manager;

        if (class_exists($storage = $manager->getStorage())) {
            $this->data = $storage::selectFromStorage($manager->getId());
        }
    }

    /**
     * Handle game action
     *
     * @param $action
     *
     * @return ServerResponse|mixed|string
     */
    public function handleAction($action)
    {
        $action = strtolower(preg_replace("/[^a-zA-Z]+/", "", $action));
        $action = $action . 'Action';

        if (!method_exists($this, $action)) {
            Debug::log('Method \'' . $action . '\ doesn\'t exist!');
            return $this->answerCallbackQuery();
        }

        Debug::log('Executing: ' . $action);
        Debug::memoryUsage();

        $result = $this->$action();

        if ($result instanceof ServerResponse) {
            if ($result->isOk() || strpos($result->getDescription(), 'message is not modified') !== false) {
                Debug::log('Server response is ok');
                Debug::memoryUsage();
                $this->answerCallbackQuery();
                return $result;
            }

            Debug::log('Server response is not ok');
            Debug::log($result->getErrorCode() . ': ' . $result->getDescription());
            Debug::memoryUsage();
            return $this->answerCallbackQuery(__('Telegram API error!') . PHP_EOL . PHP_EOL . __("Try again in a few seconds."), true);
        }

        Debug::log('CRASHED');
        Debug::memoryUsage();

        Debug::dump(
            $this->manager->getId(),
            [
                'Result' => $result,
                'Game' => $this->manager->getGame()::getTitle(),
                'Data' => json_encode($this->data),
            ]
        );

        if ($this->manager->saveData([])) {
            $this->editMessage('<i>' . __("This game session has crashed.") . '</i>' . PHP_EOL . '(ID: ' . $this->manager->getId() . ')', $this->getReplyMarkup('empty'));
        }

        return $this->answerCallbackQuery(__('Critical error!', true));
    }

    /**
     * Answer to callback query helper
     *
     * @param string $text
     * @param bool   $alert
     *
     * @return ServerResponse|mixed
     */
    protected function answerCallbackQuery($text = '', $alert = false)
    {
        if ($callback_query = $this->manager->getUpdate()->getCallbackQuery()) {
            return Request::answerCallbackQuery(
                [
                    'callback_query_id' => $callback_query->getId(),
                    'text' => $text,
                    'show_alert' => $alert
                ]
            );
        }

        return Request::emptyResponse();
    }

    /**
     * Edit message helper
     *
     * @param $text
     * @param $reply_markup
     *
     * @return ServerResponse|mixed
     */
    protected function editMessage($text, $reply_markup)
    {
        return Request::editMessageText(
            [
                'inline_message_id' => $this->manager->getId(),
                'text' => '<b>' . $this->manager->getGame()::getTitle() . '</b>' . PHP_EOL . PHP_EOL . $text,
                'reply_markup' => $reply_markup,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]
        );
    }

    /**
     * Returns notice about storage failure
     *
     * @return ServerResponse|mixed
     */
    protected function returnStorageFailure()
    {
        return $this->answerCallbackQuery(__('Database failure!') . PHP_EOL . PHP_EOL . __("Try again in a few seconds."), true);
    }

    /**
     * Get player user object
     *
     * @param $user
     * @param bool $as_json
     *
     * @return bool|User
     */
    protected function getUser($user, $as_json = false)
    {
        Debug::log($user . ' (as_json: ' . ($as_json ? 'true':'false') . ')');

        if ($as_json) {
            $result = isset($this->data['players'][$user]['id']) ? $this->data['players'][$user] : false;

            Debug::log('JSON: ' . json_encode($result));

            return $result;
        }

        $result = isset($this->data['players'][$user]['id']) ? new User($this->data['players'][$user]) : false;

        Debug::log((($result instanceof User) ? 'OBJ->JSON: ' . $result->toJson() : 'false'));

        return $result;
    }

    /**
     * Get current user object
     *
     * @param bool $as_json
     *
     * @return User|mixed
     * @throws BotException
     */
    protected function getCurrentUser($as_json = false)
    {
        Debug::log('(as_json: ' . ($as_json ? 'true':'false') . ')');

        if ($callback_query = $this->manager->getUpdate()->getCallbackQuery()) {
            $update_object = $callback_query;
        } elseif ($chosen_inline_result = $this->manager->getUpdate()->getChosenInlineResult()) {
            $update_object = $chosen_inline_result;
        } else {
            throw new BotException('No current user found!?');
        }

        if ($as_json) {
            $json = $update_object->getFrom();

            Debug::log('JSON: ' . $json);

            return json_decode($json, true);
        }

        $result = $update_object->getFrom();

        Debug::log((($result instanceof User) ? 'OBJ->JSON: ' . $result->toJson() : 'false'));

        return $result;
    }

    /**
     * Get user id safely (prevent getId() on null)
     *
     * @param $user
     *
     * @return bool|int
     */
    protected function getUserId($user)
    {
        return $this->getUser($user) ? $this->getUser($user)->getId() : false;
    }

    /**
     * Get current user id safely (prevent getId() on null)
     *
     * @return bool|int
     */
    protected function getCurrentUserId()
    {
        return $this->getCurrentUser() ? $this->getCurrentUser()->getId() : false;
    }

    /**
     * Get user mention safely (prevent tryMention() on null)
     *  Additionally removes HTML tags from the returned string
     *
     * @param $user
     *
     * @return bool|int
     */
    protected function getUserMention($user)
    {
        return $this->getUser($user) ? strip_tags($this->getUser($user)->tryMention()) : false;
    }

    /**
     * Get current user mention safely (prevent tryMention() on null)
     *  Additionally removes HTML tags from the returned string
     *
     * @return bool|int
     */
    protected function getCurrentUserMention()
    {
        return $this->getCurrentUser() ? strip_tags($this->getCurrentUser()->tryMention()) : false;
    }

    /**
     * Handle 'new' game action
     *
     * @return bool|ServerResponse|mixed
     */
    protected function newAction()
    {
        if ($this->getUser('host')) {
            return $this->answerCallbackQuery(__('This game is already created!'), true);
        }

        $this->data['players']['host'] = $this->getCurrentUser(true);
        $this->data['players']['guest'] = null;
        $this->data['players']['data'] = null;

        if ($this->manager->saveData($this->data)) {
            return $this->editMessage(__('{PLAYER} is waiting for opponent to join...', ['{PLAYER}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
        } else {
            return $this->returnStorageFailure();
        }
    }

    /**
     * Handle 'join' game action
     *
     * @return bool|ServerResponse|mixed
     */
    protected function joinAction()
    {
        if (!$this->getUser('host')) {
            Debug::log('Host:' . $this->getCurrentUserMention());

            $this->data['players']['host'] = $this->getCurrentUser(true);

            if ($this->manager->saveData($this->data)) {
                return $this->editMessage(__('{PLAYER} is waiting for opponent to join...', ['{PLAYER}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
            } else {
                return $this->returnStorageFailure();
            }
        } elseif (!$this->getUser('guest')) {
            if ($this->getCurrentUserId() != $this->getUserId('host') || (getenv('Debug') && $this->getCurrentUserId() == getenv('BOT_ADMIN'))) {
                Debug::log('Guest:' . $this->getCurrentUserMention());

                $this->data['players']['guest'] = $this->getCurrentUser(true);

                if ($this->manager->saveData($this->data)) {
                    return $this->editMessage(__('{PLAYER_GUEST} joined...', ['{PLAYER_GUEST}' => $this->getUserMention('guest')]) . PHP_EOL . __('Waiting for {PLAYER} to start...', ['{PLAYER}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to start.', ['{BUTTON}' => '<b>\'' . __('Play') . '\'</b>']), $this->getReplyMarkup('pregame'));
                } else {
                    return $this->returnStorageFailure();
                }
            } else {
                return $this->answerCallbackQuery(__("You cannot play with yourself!"), true);
            }
        } else {
            return $this->answerCallbackQuery(__("This game is full!"));
        }
    }

    /**
     * Handle 'quit' game action
     *
     * @return bool|ServerResponse|mixed
     */
    protected function quitAction()
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        if ($this->getUser('host') && $this->getCurrentUserId() == $this->getUserId('host')) {
            if ($this->getUser('guest')) {
                Debug::log('Quit, host migration: ' . $this->getCurrentUserMention() . ' => ' . $this->getUserMention('guest'));

                $this->data['players']['host'] = $this->data['players']['guest'];
                $this->data['players']['guest'] = null;

                if ($this->manager->saveData($this->data)) {
                    return $this->editMessage(__('{PLAYER} quit...', ['{PLAYER}' => $this->getCurrentUserMention()]) . PHP_EOL . __("{PLAYER} is waiting for opponent to join...", ['{PLAYER}' => $this->getUserMention('host')]) . PHP_EOL . __("Press {BUTTON} button to join.", ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
                } else {
                    return $this->returnStorageFailure();
                }
            } else {
                Debug::log('Quit (host): ' . $this->getCurrentUserMention());

                $this->data['players']['host'] = null;

                if ($this->manager->saveData($this->data)) {
                    return $this->editMessage('<i>' . __("This game session is empty.") . '</i>', $this->getReplyMarkup('empty'));
                } else {
                    return $this->returnStorageFailure();
                }
            }
        } elseif ($this->getUser('guest') && $this->getCurrentUserId() == $this->getUserId('guest')) {
            Debug::log('Quit (guest): ' . $this->getCurrentUserMention());

            $this->data['players']['guest'] = null;

            if ($this->manager->saveData($this->data)) {
                return $this->editMessage('<i>' . __("This game session is empty.") . '</i>', $this->getReplyMarkup('empty'));
            } else {
                return $this->returnStorageFailure();
            }
        } else {
            $error = 'User quitting an empty game?';
            TelegramLog::error($error);
            Debug::log($error);
            Debug::dump($this->manager->getId());
            return $this->answerCallbackQuery();
        }
    }

    /**
     * Handle 'kick' game action
     *
     * @return bool|ServerResponse|mixed
     */
    protected function kickAction()
    {
        if ($this->getCurrentUserId() != $this->getUserId('host')) {
            return $this->answerCallbackQuery(__("You're not the host!"), true);
        }

        if ($this->getUserId('host')) {
            Debug::log($this->getCurrentUserMention() . ' kicked ' . $this->getUserMention('guest'));

            $user = $this->getUserMention('guest');
            $this->data['players']['guest'] = null;

            if ($this->manager->saveData($this->data)) {
                return $this->editMessage(__('{PLAYER_GUEST} was kicked...', ['{PLAYER_GUEST}' => $user]) . PHP_EOL . __("{PLAYER} is waiting for opponent to join...", ['{PLAYER}' => $this->getUserMention('host')]) . PHP_EOL . __("Press {BUTTON} button to join.", ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
            } else {
                return $this->returnStorageFailure();
            }
        } else {
            $error = 'Kick executed on a game without a host?';
            TelegramLog::error($error);
            Debug::log($error);
            Debug::dump($this->manager->getId());
            return $this->answerCallbackQuery();
        }
    }

    /**
     * Handle 'start' game action
     *
     * @return bool|ServerResponse|mixed
     */
    protected function startAction()
    {
        if (!$this->getUser('host')) {
            $this->editMessage('<i>' . __("This game session is empty.") . '</i>', $this->getReplyMarkup('empty'));
            return $this->answerCallbackQuery();
        }

        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        if ($this->getCurrentUserId() != $this->getUserId('host')) {
            return $this->answerCallbackQuery(__("You're not the host!"), true);
        }

        if (!$this->getUser('host') || !$this->getUser('guest')) {
            Debug::log('Received request to start the game but one of the players wasn\'t in');
            return $this->answerCallbackQuery();
        }

        if (!isset($this->data['data'])) {
            $this->data['data'] = [];
        }

        Debug::log($this->getCurrentUserMention());

        $result = $this->gameAction();

        return $result;
    }

    /**
     * Handle the game action
     *
     * This is just a dummy function.
     *
     * @return bool|ServerResponse|mixed
     */
    protected function gameAction()
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        return $this->answerCallbackQuery();
    }

    /**
     * This will force a crash
     *
     * @return bool|ServerResponse|mixed
     */
    protected function crashAction()
    {
        if (!getenv('DEBUG')) {
            return $this->answerCallbackQuery();
        }

        return '(forced crash)';
    }

    /**
     * Get specified reply markup
     *
     * @param string $keyboard
     *
     * @return InlineKeyboard
     */
    protected function getReplyMarkup($keyboard = '')
    {
        if (empty($keyboard)) {
            $keyboard = 'empty';
        }

        $keyboard = strtolower(preg_replace("/[^a-zA-Z]+/", "", $keyboard));
        $keyboard = $keyboard . 'Keyboard';

        Debug::log($keyboard);

        if (!method_exists($this, $keyboard)) {
            Debug::log('Method \'' . $keyboard . '\ doesn\'t exist!');
            $keyboard = 'emptyKeyboard';
        }

        $keyboard = $this->$keyboard();

        if (getenv('Debug')) {
            $keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => 'Debug: ' . 'CRASH',
                        'callback_data' => $this->manager->getGame()::getCode() . ';crash'
                    ]
                )
            ];
        }

        $inline_keyboard_markup = new InlineKeyboard(...$keyboard);

        return $inline_keyboard_markup;
    }

    /**
     * Game empty keyboard
     *
     * @return array
     */
    protected function emptyKeyboard()
    {
        return [
            [
                new InlineKeyboardButton(
                    [
                        'text' => __('Create'),
                        'callback_data' => $this->manager->getGame()::getCode() . ';new'
                    ]
                )
            ]
        ];
    }

    /**
     * Game in lobby without guest keyboard
     *
     * @return array
     */
    protected function lobbyKeyboard()
    {
        return [
            [
                new InlineKeyboardButton(
                    [
                        'text' => __('Quit'),
                        'callback_data' => $this->manager->getGame()::getCode() . ";quit"
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => __('Join'),
                        'callback_data' => $this->manager->getGame()::getCode() . ";join"
                    ]
                )
            ]
        ];
    }

    /**
     * Game in lobby with guest keyboard
     *
     * @return array
     */
    protected function pregameKeyboard()
    {
        return [
            [
                new InlineKeyboardButton(
                    [
                        'text' => __('Play'),
                        'callback_data' => $this->manager->getGame()::getCode() . ";start"
                    ]
                )
            ],
            [
                new InlineKeyboardButton(
                    [
                        'text' => __('Quit'),
                        'callback_data' => $this->manager->getGame()::getCode() . ";quit"
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text' => __('Kick'),
                        'callback_data' => $this->manager->getGame()::getCode() . ";kick"
                    ]
                )
            ]
        ];
    }

    /**
     * Keyboard for game in progress
     *
     * @param array  $board
     * @param string $winner
     *
     * @return InlineKeyboard
     */
    protected function gameKeyboard($board, $winner = '')
    {
        for ($x = 0; $x <= $this->max_x; $x++) {
            $tmp_array = [];

            for ($y = 0; $y <= $this->max_y; $y++) {
                if (isset($board[$x][$y])) {
                    if ($board[$x][$y] == 'X' || $board[$x][$y] == 'O' || strpos($board[$x][$y], 'won')) {
                        if ($winner == 'X' && $board[$x][$y] == 'O') {
                            $field = $this->symbols[$board[$x][$y] . '_lost'];
                        } elseif ($winner == 'O' && $board[$x][$y] == 'X') {
                            $field = $this->symbols[$board[$x][$y] . '_lost'];
                        } elseif (isset($this->symbols[$board[$x][$y]])) {
                            $field = $this->symbols[$board[$x][$y]];
                        }
                    } else {
                        $field = ($this->symbols['empty']) ?: ' ';
                    }

                    array_push(
                        $tmp_array,
                        new InlineKeyboardButton(
                            [
                                'text'          => $field,
                                'callback_data' => $this->manager->getGame()::getCode() . ';game;' . $x . '-' . $y
                            ]
                        )
                    );
                }
            }

            if (!empty($tmp_array)) {
                $inline_keyboard[] = $tmp_array;
            }
        }

        if (!empty($winner)) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => __('Play again!'),
                        'callback_data' => $this->manager->getGame()::getCode() . ';start'
                    ]
                )
            ];
        }

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text' => __('Quit'),
                    'callback_data' => $this->manager->getGame()::getCode() . ';quit'
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text' => __('Kick'),
                    'callback_data' => $this->manager->getGame()::getCode() . ';kick'
                ]
            )
        ];

        if (getenv('Debug')) {
            if (class_exists('\Symfony\Component\Console\Helper\Table')) {
                $output = new \Symfony\Component\Console\Output\BufferedOutput();
                $table = new \Symfony\Component\Console\Helper\Table($output);
                $table->setRows($board);
                $table->render();

                Debug::log('CURRENT BOARD:' . PHP_EOL . $output->fetch());
            }

            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => 'Debug: ' . 'Restart',
                        'callback_data' => $this->manager->getGame()::getCode() . ';start'
                    ]
                )
            ];
        }

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }
}
