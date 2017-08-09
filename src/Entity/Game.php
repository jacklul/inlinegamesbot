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
use Bot\Helper\Language;
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
     * List of languages
     *
     * @var mixed
     */
    protected $languages;

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
        if (class_exists($storage = $this->manager->getStorage()) && empty($this->data)) {
            Debug::print('Reading game data from database');
            $this->data = $storage::selectFromGame($this->manager->getId());
        }

        if (!$this->data && !is_array($this->data)) {
            return $this->returnStorageFailure();
        }

        $action = strtolower(preg_replace("/[^a-zA-Z]+/", "", $action));
        $action = $action . 'Action';

        if (!method_exists($this, $action)) {
            Debug::print('Method \'' . $action . '\ doesn\'t exist');
            return $this->answerCallbackQuery();
        }

        $this->languages = Language::list();

        if (isset($this->data['settings']['language']) && $language = $this->data['settings']['language']) {
            Language::set($language);
            Debug::print('Set language: ' . $language);
        } else {
            Language::set(Language::getDefaultLanguage());
        }

        Debug::print('Executing: ' . $action);

        $result = $this->$action();
        $data_before = $this->data;

        if ($result instanceof ServerResponse) {
            if ($result->isOk() || strpos($result->getDescription(), 'message is not modified') !== false) {
                Debug::print('Server response is ok');
                $this->answerCallbackQuery();
                return $result;
            }

            Debug::print('Server response is not ok');
            Debug::print($result->getErrorCode() . ': ' . $result->getDescription());
            return $this->answerCallbackQuery(__('Telegram API error!') . PHP_EOL . PHP_EOL . __("Try again in a few seconds."), true);
        }

        Debug::print('CRASHED');

        TelegramLog::error($this->crashDump([
            'Game' => $this->manager->getGame()::getTitle(),
            'Game data (before)' => json_encode($data_before),
            'Game data (after)' => json_encode($this->data),
            'Callback data' => $this->manager->getUpdate()->getCallbackQuery() ? $this->manager->getUpdate()->getCallbackQuery()->getData() : '<not a callback query>',
            'Result' => $result,
        ]));

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
        Debug::print('Storage failure');
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
        Debug::print($user . ' (as_json: ' . ($as_json ? 'true':'false') . ')');

        if ($as_json) {
            $result = isset($this->data['players'][$user]['id']) ? $this->data['players'][$user] : false;

            Debug::print('JSON: ' . json_encode($result));

            return $result;
        }

        $result = isset($this->data['players'][$user]['id']) ? new User($this->data['players'][$user]) : false;

        Debug::print((($result instanceof User) ? 'OBJ->JSON: ' . $result->toJson() : 'false'));

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
        Debug::print('(as_json: ' . ($as_json ? 'true':'false') . ')');

        if ($callback_query = $this->manager->getUpdate()->getCallbackQuery()) {
            $update_object = $callback_query;
        } elseif ($chosen_inline_result = $this->manager->getUpdate()->getChosenInlineResult()) {
            $update_object = $chosen_inline_result;
        } else {
            throw new BotException('No current user found!?');
        }

        if ($as_json) {
            $json = $update_object->getFrom();

            Debug::print('JSON: ' . $json);

            return json_decode($json, true);
        }

        $result = $update_object->getFrom();

        Debug::print((($result instanceof User) ? 'OBJ->JSON: ' . $result->toJson() : 'false'));

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
        if ($this->getUser('host') && $this->getCurrentUserId() != $this->getCurrentUserId('host')) {
            return $this->answerCallbackQuery(__('This game is already created!'), true);
        }

        $this->data['players']['host'] = $this->getCurrentUser(true);
        $this->data['players']['guest'] = null;
        $this->data['data'] = null;

        if ($this->manager->saveData($this->data)) {
            return $this->editMessage(__('{PLAYER_HOST} is waiting for opponent to join...', ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
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
            Debug::print('Host:' . $this->getCurrentUserMention());

            $this->data['players']['host'] = $this->getCurrentUser(true);

            if ($this->manager->saveData($this->data)) {
                return $this->editMessage(__('{PLAYER_HOST} is waiting for opponent to join...', ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
            } else {
                return $this->returnStorageFailure();
            }
        } elseif (!$this->getUser('guest')) {
            if ($this->getCurrentUserId() != $this->getUserId('host') || (getenv('Debug') && $this->getCurrentUserId() == getenv('BOT_ADMIN'))) {
                Debug::print('Guest:' . $this->getCurrentUserMention());

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
                Debug::print('Quit, host migration: ' . $this->getCurrentUserMention() . ' => ' . $this->getUserMention('guest'));

                $this->data['players']['host'] = $this->data['players']['guest'];
                $this->data['players']['guest'] = null;

                if ($this->manager->saveData($this->data)) {
                    return $this->editMessage(__('{PLAYER} quit...', ['{PLAYER}' => $this->getCurrentUserMention()]) . PHP_EOL . __("{PLAYER_HOST} is waiting for opponent to join...", ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __("Press {BUTTON} button to join.", ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
                } else {
                    return $this->returnStorageFailure();
                }
            } else {
                Debug::print('Quit (host): ' . $this->getCurrentUserMention());

                $this->data['players']['host'] = null;

                if ($this->manager->saveData($this->data)) {
                    return $this->editMessage('<i>' . __("This game session is empty.") . '</i>', $this->getReplyMarkup('empty'));
                } else {
                    return $this->returnStorageFailure();
                }
            }
        } elseif ($this->getUser('guest') && $this->getCurrentUserId() == $this->getUserId('guest')) {
            Debug::print('Quit (guest): ' . $this->getCurrentUserMention());

            $this->data['players']['guest'] = null;

            if ($this->manager->saveData($this->data)) {
                return $this->editMessage('<i>' . __("This game session is empty.") . '</i>', $this->getReplyMarkup('empty'));
            } else {
                return $this->returnStorageFailure();
            }
        } else {
            Debug::print('User quitting an empty game?');
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
        if ($this->getCurrentUserId() !== $this->getUserId('host')) {
            return $this->answerCallbackQuery(__("You're not the host!"), true);
        }

        if ($this->getUserId('host')) {
            Debug::print($this->getCurrentUserMention() . ' kicked ' . $this->getUserMention('guest'));

            $user = $this->getUserMention('guest');
            $this->data['players']['guest'] = null;

            if ($this->manager->saveData($this->data)) {
                return $this->editMessage(__('{PLAYER_GUEST} was kicked...', ['{PLAYER_GUEST}' => $user]) . PHP_EOL . __("{PLAYER_HOST} is waiting for opponent to join...", ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __("Press {BUTTON} button to join.", ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
            } else {
                return $this->returnStorageFailure();
            }
        } else {
            Debug::print('Kick executed on a game without a host');
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

        if ($this->getCurrentUserId() !== $this->getUserId('host')) {
            return $this->answerCallbackQuery(__("You're not the host!"), true);
        }

        if (!$this->getUser('host') || !$this->getUser('guest')) {
            Debug::print('Received request to start the game but one of the players wasn\'t in the game');
            return $this->answerCallbackQuery();
        }

        $this->data['data'] = [];

        Debug::print($this->getCurrentUserMention());

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
     * Change language
     *
     * @return bool|ServerResponse|mixed
     */
    protected function languageAction()
    {
        $current_languge = Language::getCurrentLanguage();

        $this->languages;

        $selected_language = $this->languages[0];

        $picknext = false;
        foreach ($this->languages as $language) {
            if ($picknext) {
                $selected_language = $language;
                break;
            }

            if ($language == $current_languge) {
                $picknext = true;
            }
        }

        $this->data['settings']['language'] = $selected_language;

        if ($this->manager->saveData($this->data)) {
            Debug::log('Set language: ' . $selected_language);
            Language::set($selected_language);
        }

        if ($this->getUser('host') && !$this->getUser('guest')) {
            $this->editMessage(__('{PLAYER_HOST} is waiting for opponent to join...', ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
        } elseif ($this->getUser('host') && $this->getUser('guest')) {
            $this->editMessage(__('{PLAYER_GUEST} joined...', ['{PLAYER_GUEST}' => $this->getUserMention('guest')]) . PHP_EOL . __('Waiting for {PLAYER} to start...', ['{PLAYER}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to start.', ['{BUTTON}' => '<b>\'' . __('Play') . '\'</b>']), $this->getReplyMarkup('pregame'));
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

        Debug::print($keyboard);

        if (!method_exists($this, $keyboard)) {
            Debug::print('Method \'' . $keyboard . '\ doesn\'t exist');
            $keyboard = 'emptyKeyboard';
        }

        $keyboard = $this->$keyboard();

        if (getenv('Debug')) {
            $keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => 'DEBUG: ' . 'CRASH',
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
        $inline_keyboard = [];

        if (count($this->languages) > 1) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => ucfirst(locale_get_display_language(Language::getCurrentLanguage(), Language::getCurrentLanguage())),
                        'callback_data' => $this->manager->getGame()::getCode() . ";language"
                    ]
                )
            ];
        }

        $inline_keyboard[] = [
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
        ];

        return $inline_keyboard;
    }

    /**
     * Game in lobby with guest keyboard
     *
     * @return array
     */
    protected function pregameKeyboard()
    {
        $inline_keyboard = [];

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text' => __('Play'),
                    'callback_data' => $this->manager->getGame()::getCode() . ";start"
                ]
            )
        ];

        if (count($this->languages) > 1) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => ucfirst(locale_get_display_language(Language::getCurrentLanguage(), Language::getCurrentLanguage())),
                        'callback_data' => $this->manager->getGame()::getCode() . ";language"
                    ]
                )
            ];
        }

        $inline_keyboard[] = [
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
        ];

        return $inline_keyboard;
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

        if (getenv('DEBUG')) {
            $this->boardPrint($board);

            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text' => 'DEBUG: ' . 'Restart',
                        'callback_data' => $this->manager->getGame()::getCode() . ';start'
                    ]
                )
            ];
        }

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }

    /**
     * Debug print of game board
     *
     * @param $board
     */
    protected function boardPrint($board)
    {
        if (!empty($board) && is_array($board) && isset($this->max_y) && isset($this->max_x)) {
            $board_out = str_repeat(' ---', $this->max_x) . PHP_EOL;

            for ($x = 0; $x < $this->max_x; $x++) {
                $line = '';

                for ($y = 0; $y < $this->max_y; $y++) {
                    $line .= '|' . (!empty($board[$x][$y]) ? ' ' . $board[$x][$y] . ' ' : '   ');
                }

                $board_out .= $line . '|' . PHP_EOL;
                $board_out .= str_repeat(' ---', $this->max_x) . PHP_EOL;
            }

            Debug::print('CURRENT BOARD:' . PHP_EOL . $board_out);
        }
    }

    /**
     * Make a debug dump of crashed game session
     *
     * @param array $data
     *
     * @param string $id
     * @return string
     */
    private function crashDump($data = [], $id = '')
    {
        $output = 'CRASH' . (isset($id) ? ' (ID: ' . $id . ')' : '' ) . ':' . PHP_EOL;
        foreach ($data as $var => $val) {
            $output .= $var . ': ' . (is_array($val) ? print_r($val, true) : (is_bool($val) ? ($val ? 'true' : 'false') : $val)) . PHP_EOL;
        }

        return $output;
    }

    /**
     * Handle a case when game data is empty but received a game action request
     *
     * @return ServerResponse|mixed
     */
    protected function handleEmptyData()
    {
        Debug::print('Empty game data');

        if ($this->getUser('host') && !$this->getUser('guest')) {
            $this->editMessage(__('{PLAYER_HOST} is waiting for opponent to join...', ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
        } elseif ($this->getUser('host') && $this->getUser('guest')) {
            $this->editMessage(__('{PLAYER_GUEST} joined...', ['{PLAYER_GUEST}' => $this->getUserMention('guest')]) . PHP_EOL . __('Waiting for {PLAYER} to start...', ['{PLAYER}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to start.', ['{BUTTON}' => '<b>\'' . __('Play') . '\'</b>']), $this->getReplyMarkup('pregame'));
        } else {
            $this->editMessage('<i>' . __("This game session is empty.") . '</i>', $this->getReplyMarkup('empty'));
        }

        return $this->answerCallbackQuery(__('Error!'), true);
    }
}
