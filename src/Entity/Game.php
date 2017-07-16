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

use Exception;
use Bot\Exception\BotException;
use Bot\Helper\CrashDump;
use Bot\Helper\DebugLog;
use Bot\Manager\Game as GameManager;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\User;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;
use MongoDB\Driver\Server;

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
        $this->data = $manager->getData();
    }

    /**
     * Handle game action
     *
     * @param $action
     *
     * @return ServerResponse|mixed|string
     * @throws BotException
     */
    public function handleAction($action)
    {
        $action = strtolower(preg_replace("/[^a-zA-Z]+/", "", $action));
        $action = $action . 'Action';

        if (!method_exists($this, $action)) {
            throw new BotException('Method \'' . $action . '\ doesn\'t exist!');
        }

        DebugLog::log($action);

        try {
            $result = $this->$action();

            if ($result instanceof ServerResponse) {
                if ($result->isOk() || strpos($result->getDescription(), 'message is not modified') !== false) {
                    DebugLog::log('Server response is ok');
                    $this->answerCallbackQuery();
                    return $result;
                }

                DebugLog::log('Server response is not ok');
                DebugLog::log($result->getErrorCode() . ': ' . $result->getDescription());
                return $this->answerCallbackQuery(__('Error!', true));
            }
        } catch (Exception $e) {
            DebugLog::log($result = $e->getMessage());
        }

        DebugLog::log('CRASHED');

        if (!empty($this->data)) {
            CrashDump::dump($this->manager->getId());

            $this->editMessage('<i>' . __("This game session has crashed.") . '</i>' . PHP_EOL . '(ID: ' . $this->manager->getId() . ')', $this->getReplyMarkup('empty'));

            return $this->answerCallbackQuery(__('Critical error!', true));
        }

        $this->editMessage('<i>' . __("This game session is empty.") . '</i>', $this->getReplyMarkup('empty'));

        return $this->answerCallbackQuery();
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
     * Get player user object
     *
     * @param $user
     * @param bool $as_json
     *
     * @return bool|User
     */
    protected function getUser($user, $as_json = false)
    {
        DebugLog::log($user . ' (as_json: ' . ($as_json ? 'true':'false') . ')');

        if ($as_json) {
            $result = isset($this->data['players'][$user]['id']) ? $this->data['players'][$user] : false;

            DebugLog::log('JSON: ' . json_encode($result));

            return $result;
        }

        $result = isset($this->data['players'][$user]['id']) ? new User($this->data['players'][$user]) : false;

        DebugLog::log((($result instanceof User) ? 'OBJ->JSON: ' . $result->toJson() : 'false'));

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
        DebugLog::log('(as_json: ' . ($as_json ? 'true':'false') . ')');

        if ($callback_query = $this->manager->getUpdate()->getCallbackQuery()) {
            $update_object = $callback_query;
        } elseif ($chosen_inline_result = $this->manager->getUpdate()->getChosenInlineResult()) {
            $update_object = $chosen_inline_result;
        } else {
            throw new BotException('No current user found!?');
        }

        if ($as_json) {
            $json = $update_object->getFrom();

            DebugLog::log('JSON: ' . $json);

            return json_decode($json, true);
        }

        $result = $update_object->getFrom();

        DebugLog::log((($result instanceof User) ? 'OBJ->JSON: ' . $result->toJson() : 'false'));

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
        if (!empty($this->data['players']['host']) && $this->getCurrentUserId() != $this->getUserId('host')) {
            return $this->answerCallbackQuery(__('This game is already created!'), true);
        }

        $this->data['players']['host'] = $this->getCurrentUser(true);
        $this->data['players']['guest'] = null;
        $this->data['players']['data'] = null;

        if ($this->manager->setData($this->data)) {
            return $this->editMessage(__('{PLAYER} is waiting for opponent to join...', ['{PLAYER}' => $this->getUser('host')->tryMention()]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
        }

        return false;
    }

    /**
     * Handle 'join' game action
     *
     * @return bool|ServerResponse|mixed
     */
    protected function joinAction()
    {
        if (!$this->getUser('host')) {
            DebugLog::log($this->getCurrentUser()->tryMention());

            $this->data['players']['host'] = $this->getCurrentUser(true);

            if ($this->manager->setData($this->data)) {
                return $this->editMessage(__('{PLAYER} is waiting for opponent to join...', ['{PLAYER}' => $this->getUser('host')->tryMention()]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
            }
        } elseif (!$this->getUser('guest')) {
            if ($this->getCurrentUserId() != $this->getUserId('host') || (getenv('DEBUG') && $this->getCurrentUserId() == getenv('BOT_ADMIN'))) {
                DebugLog::log($this->getCurrentUser()->tryMention());

                $this->data['players']['guest'] = $this->getCurrentUser(true);

                if ($this->manager->setData($this->data)) {
                    return $this->editMessage(__('{PLAYER_GUEST} joined...', ['{PLAYER_GUEST}' => $this->getUser('guest')->tryMention()]) . PHP_EOL . __('Waiting for {PLAYER} to start...', ['{PLAYER}' => $this->getUser('host')->tryMention()]) . PHP_EOL . __('Press {BUTTON} button to start.', ['{BUTTON}' => '<b>\'' . __('Play') . '\'</b>']), $this->getReplyMarkup('pregame'));
                }
            } else {
                return $this->answerCallbackQuery(__("You cannot play with yourself!"), true);
            }
        } else {
            return $this->answerCallbackQuery(__("This game is full!"));
        }

        return false;
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
                DebugLog::log($this->getCurrentUser()->tryMention());

                $this->data['players']['host'] = $this->data['players']['guest'];
                $this->data['players']['guest'] = null;

                if ($this->manager->setData($this->data)) {
                    return $this->editMessage(__('{PLAYER} quit...', ['{PLAYER}' => $this->getCurrentUser()->tryMention()]) . PHP_EOL . __("{PLAYER} is waiting for opponent to join...", ['{PLAYER}' => $this->getUser('host')->tryMention()]) . PHP_EOL . __("Press {BUTTON} button to join.", ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
                }
            } else {
                DebugLog::log($this->getCurrentUser()->tryMention());

                $this->data['players']['host'] = null;

                if ($this->manager->setData($this->data)) {
                    return $this->editMessage('<i>' . __("This game session is empty.") . '</i>', $this->getReplyMarkup('empty'));
                }
            }
        } elseif ($this->getUser('guest') && $this->getCurrentUserId() == $this->getUserId('guest')) {
            DebugLog::log($this->getCurrentUser()->tryMention());

            $this->data['players']['guest'] = null;

            if ($this->manager->setData($this->data)) {
                return $this->editMessage('<i>' . __("This game session is empty.") . '</i>', $this->getReplyMarkup('empty'));
            }
        } else {
            TelegramLog::debug('Quitting an empty game?');
            return $this->answerCallbackQuery();
        }

        return false;
    }

    /**
     * Handle 'kick' game action
     *
     * @return bool|ServerResponse|mixed
     */
    protected function kickAction()
    {
        if ($this->getUserId('host') && $this->getCurrentUserId() == $this->getUserId('host')) {
            DebugLog::log($this->getCurrentUser()->tryMention() . ' kicked ' . $this->getUser('guest')->tryMention());

            $user = $this->getUser('guest')->tryMention();
            $this->data['players']['guest'] = null;

            if ($this->manager->setData($this->data)) {
                return $this->editMessage(__('{PLAYER_GUEST} was kicked...', ['{PLAYER_GUEST}' => $user]) . PHP_EOL . __("{PLAYER} is waiting for opponent to join...", ['{PLAYER}' => $this->getUser('host')->tryMention()]) . PHP_EOL . __("Press {BUTTON} button to join.", ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
            }
        } elseif ($this->getUserId('host')) {
            return $this->answerCallbackQuery(__("You're not the host!"), true);
        } else {
            TelegramLog::debug('Kick executed on a game without a host?');
            return $this->answerCallbackQuery();
        }

        return false;
    }

    /**
     * Handle 'start' game action
     *
     * @return bool|ServerResponse|mixed
     */
    protected function startAction()
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        if ($this->getCurrentUserId() != $this->getUserId('host')) {
            return $this->answerCallbackQuery(__("You're not the host!"), true);
        }

        if (!$this->getUser('host') || !$this->getUser('guest')) {
            TelegramLog::debug('Game was started but one of the players wasn\'t in this game.');
            return $this->answerCallbackQuery();
        }

        if (!isset($this->data['data'])) {
            $this->data['data'] = [];
        }

        DebugLog::log($this->getCurrentUser()->tryMention());

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
     * Get specified reply markup
     *
     * @param string $keyboard
     *
     * @return InlineKeyboard
     * @throws BotException
     */
    protected function getReplyMarkup($keyboard = '')
    {
        if (empty($keyboard)) {
            $keyboard = 'empty';
        }

        $keyboard = strtolower(preg_replace("/[^a-zA-Z]+/", "", $keyboard));
        $keyboard = $keyboard . 'Keyboard';

        DebugLog::log($keyboard);

        if (!method_exists($this, $keyboard)) {
            throw new BotException('Method \'' . $keyboard . '\ doesn\'t exist!');
        }

        $inline_keyboard_markup = new InlineKeyboard(...$this->$keyboard());

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

        if (getenv('DEBUG')) {
            if (class_exists('\Symfony\Component\Console\Helper\Table')) {
                $output = new \Symfony\Component\Console\Output\BufferedOutput();
                $table = new \Symfony\Component\Console\Helper\Table($output);
                $table->setRows($board);
                $table->render();

                DebugLog::log('CURRENT BOARD:' . PHP_EOL . $output->fetch());
            }

            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => 'DEBUG: ' . 'Restart',
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

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }
}
