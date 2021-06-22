<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2021 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot\Entity;

use Bot\Exception\BotException;
use Bot\Exception\StorageException;
use Bot\Exception\TelegramApiException;
use Bot\GameCore;
use Bot\Helper\Language;
use Bot\Helper\Utilities;
use Bot\Storage\Driver\File;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\User;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

/**
 * The "master" class for all games, contains shared methods
 */
class Game
{
    /**
     * Game unique ID
     *
     * @var string
     */
    protected static $code;

    /**
     * Game name / title
     *
     * @var string
     */
    protected static $title;

    /**
     * Game description
     *
     * @var string
     */
    protected static $description;

    /**
     * Game thumbnail image
     *
     * @var string
     */
    protected static $image;

    /**
     * Order on the games list
     *
     * @var int
     */
    protected static $order;

    /**
     * Current game data
     *
     * @var array|null|mixed
     */
    protected $data;

    /**
     * Old game data
     *
     * @var array|null|mixed
     */
    protected $data_old;

    /**
     * Game Manager object
     *
     * @var GameCore
     */
    protected $manager;

    /**
     * List of available languages
     *
     * @var array
     */
    protected $languages;

    /**
     * Was the reply to callback query already sent?
     *
     * @var bool
     */
    protected $query_answered = false;

    /**
     * Game related variables
     */
    protected $max_x;
    protected $max_y;
    protected $symbols = [];

    /**
     * Game constructor
     *
     * @param GameCore $manager
     */
    public function __construct(GameCore $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @return string
     */
    public static function getDescription(): string
    {
        return static::$description;
    }

    /**
     * @return string
     */
    public static function getImage(): string
    {
        return static::$image;
    }

    /**
     * @return string
     */
    public static function getOrder(): string
    {
        return static::$order;
    }

    /**
     * Handle game action
     *
     * @param string $action
     *
     * @return ServerResponse|mixed
     *
     * @throws TelegramApiException
     * @throws StorageException
     * @throws BotException
     * @throws TelegramException
     */
    public function handleAction(string $action)
    {
        $game_id = $this->manager->getId();
        if (class_exists($storage_class = $this->manager->getStorage()) && $this->data === null) {
            Utilities::debugPrint('Reading game data from the database');

            /** @var File $storage_class */
            $this->data = $storage_class::selectFromGame($game_id);
        }

        if (!is_array($this->data)) {
            throw new StorageException();
        }

        $this->data_old = $this->data;
        $action = strtolower(preg_replace("/[^a-zA-Z]+/", "", $action));
        $action = $action . 'Action';

        // Do not throw exception on method not found as users can potentially manipulate callback data
        if (!method_exists($this, $action)) {
            Utilities::debugPrint('Method \'' . $action . '\' doesn\'t exist');

            return $this->answerCallbackQuery();
        }

        $this->languages = Language::list();

        if (isset($this->data['settings']['language']) && $language = $this->data['settings']['language']) {
            Language::set($language);
            Utilities::debugPrint('Set language: ' . $language);
        } else {
            Language::set(Language::getDefaultLanguage());
        }


        if (empty($this->data) && $action !== 'newAction') {
            Utilities::debugPrint('Empty game data found with action expecting it to not be');
            $action = "handleEmptyData";
        }

        Utilities::debugPrint('Executing: ' . $action);

        $result = $this->$action();

        if ($result instanceof ServerResponse) {
            $allowedAPIErrors = [
                'message is not modified',          // Editing a message with exactly same content
                'QUERY_ID_INVALID',                 // Callback query after it expired, or trying to reply to a callback that was already answered
                'query is too old and response timeout expired or query ID is invalid',
                'MESSAGE_ID_INVALID',               // Callback query from deleted message, or chooses inline result on message that is no yet delivered to Telegram servers
                'ENTITY_MENTION_USER_INVALID',      // User mention ended up somehow invalid
                'MESSAGE_AUTHOR_REQUIRED',          // No message author in the object?
                'Too Many Requests',                // Telegram API limit reached
            ];

            if ($result->isOk() || Utilities::strposa($result->getDescription(), $allowedAPIErrors) !== false) {
                Utilities::debugPrint('Server response is ok');

                return $this->answerCallbackQuery();
            }

            Utilities::debugPrint('Server response is not ok: ' . $result->getDescription() . ' (' . $result->getErrorCode() . ')');

            throw new TelegramApiException(
                Utilities::debugDump(
                    'Telegram API error: ' . $result->getDescription() . ' (' . $result->getErrorCode() . ')',
                    [
                        'Game data'     => json_encode($this->data),
                        'Update object' => json_encode(Utilities::updateToArray($this->manager->getUpdate())),
                    ]
                )
            );
        }

        Utilities::debugPrint('CRASHED (Game result is not a ServerResponse object!)');

        $this->saveData([]);
        $this->editMessage('<i>' . __("This game session has crashed.") . '</i>' . PHP_EOL . '(ID: ' . $this->manager->getId() . ')', $this->getReplyMarkup('empty'));

        throw new BotException(
            Utilities::debugDump(
                'CRASH' . ($this->manager->getId() ? ' (Session ID: ' . $this->manager->getId() . ')' : ''),
                [
                    'Game'            => static::getTitle(),
                    'Game data (old)' => json_encode($this->data_old),
                    'Game data (new)' => json_encode($this->data),
                    'Callback data'   => $this->manager->getUpdate()->getCallbackQuery() ? $this->manager->getUpdate()->getCallbackQuery()->getData() : '<not a callback query>',
                    'Update object'   => json_encode(Utilities::updateToArray($this->manager->getUpdate())),
                    'Result'          => $result,
                ]
            )
        );
    }

    /**
     * Answer to callback query helper
     *
     * @param string $text
     * @param bool   $alert
     *
     * @return ServerResponse
     *
     * @throws TelegramException
     */
    protected function answerCallbackQuery(string $text = null, bool $alert = false): ServerResponse
    {
        if (!$this->query_answered && $callback_query = $this->manager->getUpdate()->getCallbackQuery()) {
            $this->query_answered = true;

            return Request::answerCallbackQuery(
                [
                    'callback_query_id' => $callback_query->getId(),
                    'text'              => $text,
                    'show_alert'        => $alert,
                ]
            );
        }

        return Request::emptyResponse();
    }

    /**
     * Save game data
     *
     * @param array $data
     *
     * @return bool
     *
     * @throws StorageException
     */
    protected function saveData(array $data = null): bool
    {
        if ($data == null) {
            $data = [];
        }

        // Make sure we have the game code in the data array for /cleansessions and /stats command!
        $data['game_code'] = static::getCode();

        Utilities::debugPrint('Saving game data to database');

        /** @var File $storage_class */
        $storage_class = $this->manager->getStorage();
        $result = $storage_class::insertToGame($this->manager->getId(), $data);

        if (!$result) {
            throw new StorageException('Data save failed');
        }

        return true;
    }

    /**
     * @return string
     */
    public static function getCode(): string
    {
        return static::$code;
    }

    /**
     * Edit message helper
     *
     * @param string         $text
     * @param InlineKeyboard $reply_markup
     * @param bool           $ignore_mention_error
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws StorageException
     * @throws TelegramException
     */
    protected function editMessage(string $text, InlineKeyboard $reply_markup, bool $ignore_mention_error = false): ServerResponse
    {
        $result = Request::editMessageText(
            [
                'inline_message_id'        => $this->manager->getId(),
                'text'                     => '<b>' . static::getTitle() . '</b>' . PHP_EOL . PHP_EOL . $text,
                'reply_markup'             => $reply_markup,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ]
        );

        // In case mentions in the message fails replace them with regular ones - tryMention()
        if ($ignore_mention_error === false && strpos($result->getDescription(), 'ENTITY_MENTION_USER_INVALID') !== false) {
            $this->data['settings']['use_old_mentions'] = true;

            if ($this->saveData($this->data)) {
                //if (preg_match_all("/<a\shref=\"tg:\/\/user\?id=(.*)\">.*<\/a>/Us", $text, $matches)) {
                if (preg_match_all('/<a\\shref="tg:\\/\\/user\\?id=(.*)">.*<\\/a>/Us', $text, $matches)) {
                    for ($i = 0, $iMax = count($matches[1]); $i < $iMax; $i++) {
                        if ($matches[1][$i] == $this->getUserId('host')) {
                            $text = str_replace($matches[0][$i], $this->getUser('host')->tryMention(), $text);
                        } elseif ($matches[1][$i] == $this->getUserId('guest')) {
                            $text = str_replace($matches[0][$i], $this->getUser('guest')->tryMention(), $text);
                        }
                    }

                    return $this->editMessage($text, $reply_markup, true);
                }
            }
        }

        // If editing fails then revert data to avoid desync
        if (!$result->isOk()) {
            if (isset($this->data['settings']['use_old_mentions']) && $this->data['settings']['use_old_mentions'] === true) {
                $this->data_old['settings']['use_old_mentions'] = true;
            }

            $this->saveData($this->data_old);
        }

        return $result;
    }

    /**
     * @return string
     */
    public static function getTitle(): string
    {
        return static::$title;
    }

    /**
     * Get user id safely (prevent getId() on null)
     *
     * @param string $user
     *
     * @return int|bool
     *
     * @throws TelegramException
     */
    protected function getUserId(string $user = null)
    {
        return $this->getUser($user) ? $this->getUser($user)->getId() : false;
    }

    /**
     * Get player's user object
     *
     * @param string $user
     *
     * @return User|bool
     *
     * @throws TelegramException
     */
    protected function getUser(string $user = null)
    {
        if ($user === null) {
            return false;
        }

        $result = isset($this->data['players'][$user]['id']) ? new User($this->data['players'][$user]) : false;

        if ($result !== false) {
            $object_as_array = (array) $result;
            unset($object_as_array['raw_data'], $object_as_array['bot_username']);

            Utilities::isDebugPrintEnabled() && Utilities::debugPrint($user, $object_as_array);
        } else {
            Utilities::isDebugPrintEnabled() && Utilities::debugPrint($user . ' = false');
        }

        return $result;
    }

    /**
     * Get specified reply markup
     *
     * @param string $inline_keyboard
     *
     * @return InlineKeyboard
     * @throws TelegramException
     */
    protected function getReplyMarkup(string $inline_keyboard = null): InlineKeyboard
    {
        if (empty($inline_keyboard)) {
            $inline_keyboard = 'empty';
        }

        $inline_keyboard = strtolower(preg_replace("/[^a-zA-Z]+/", "", $inline_keyboard));
        $inline_keyboard = $inline_keyboard . 'Keyboard';

        Utilities::debugPrint($inline_keyboard);

        if (!method_exists($this, $inline_keyboard)) {
            Utilities::debugPrint('Method \'' . $inline_keyboard . '\ doesn\'t exist');
            $inline_keyboard = 'emptyKeyboard';
        }

        $inline_keyboard = $this->$inline_keyboard();

        return new InlineKeyboard(...$inline_keyboard);
    }

    /**
     * Handle 'new' game action
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws TelegramException
     * @throws StorageException
     */
    protected function newAction(): ServerResponse
    {
        if ($this->getUser('host')) {
            return $this->answerCallbackQuery(__('This game is already created!'), true);
        }

        $this->data['players']['host'] = (array) $this->getCurrentUser(true);
        $this->data['players']['guest'] = null;

        if (isset($this->data['settings']['use_old_mentions']) && $this->data['settings']['use_old_mentions'] === true) {
            $this->data['settings']['use_old_mentions'] = false;
        }

        $this->data['game_data'] = null;

        if ($this->saveData($this->data)) {
            return $this->editMessage(__('{PLAYER_HOST} is waiting for opponent to join...', ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
        }

        throw new StorageException();
    }

    /**
     * Get current player's user object
     *
     * @return User
     *
     * @throws BotException
     */
    protected function getCurrentUser()
    {
        if ($callback_query = $this->manager->getUpdate()->getCallbackQuery()) {
            $update_object = $callback_query;
        } elseif ($chosen_inline_result = $this->manager->getUpdate()->getChosenInlineResult()) {
            $update_object = $chosen_inline_result;
        } else {
            throw new BotException('No current user found!?');
        }

        $user = $update_object->getFrom();

        $object_as_array = (array) $user;
        unset($object_as_array['raw_data'], $object_as_array['bot_username']);

        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('current', (array) $object_as_array);

        return $user;
    }

    /**
     * Get specific user mention (host or guest)
     *
     * @param string $user
     *
     * @return string|bool
     *
     * @throws TelegramException
     */
    protected function getUserMention(string $user = null)
    {
        if (isset($this->data['settings']['use_old_mentions']) && $this->data['settings']['use_old_mentions'] === true) {
            return $this->getUser($user) ? filter_var($this->getUser($user)->tryMention(), FILTER_SANITIZE_SPECIAL_CHARS) : false;
        }

        return $this->getUser($user) ? '<a href="tg://user?id=' . $this->getUser($user)->getId() . '">' . filter_var($this->getUser($user)->getFirstName(), FILTER_SANITIZE_SPECIAL_CHARS) . '</a>' : false;
    }

    /**
     * Handle 'join' game action
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws TelegramException
     * @throws StorageException
     */
    protected function joinAction(): ServerResponse
    {
        if (!$this->getUser('host')) {
            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Host:' . $this->getCurrentUserMention());

            $this->data['players']['host'] = (array) $this->getCurrentUser(true);

            if ($this->saveData($this->data)) {
                return $this->editMessage(__('{PLAYER_HOST} is waiting for opponent to join...', ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
            }

            throw new StorageException();
        }

        if (!$this->getUser('guest')) {
            if ($this->getCurrentUserId() != $this->getUserId('host') || getenv('DEBUG') || $this->getCurrentUserId() == getenv('BOT_ADMIN')) {
                Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Guest:' . $this->getCurrentUserMention());

                $this->data['players']['guest'] = (array) $this->getCurrentUser(true);

                if ($this->saveData($this->data)) {
                    return $this->editMessage(__('{PLAYER_GUEST} joined...', ['{PLAYER_GUEST}' => $this->getUserMention('guest')]) . PHP_EOL . __('Waiting for {PLAYER} to start...', ['{PLAYER}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to start.', ['{BUTTON}' => '<b>\'' . __('Play') . '\'</b>']), $this->getReplyMarkup('pregame'));
                }

                throw new StorageException();
            }

            return $this->answerCallbackQuery(__("You cannot play with yourself!"), true);
        }

        return $this->answerCallbackQuery(__("This game is full!"));
    }

    /**
     * Get current user mention
     *
     * @return string|bool
     *
     * @throws BotException
     */
    protected function getCurrentUserMention()
    {
        if (isset($this->data['settings']['use_old_mentions']) && $this->data['settings']['use_old_mentions'] === true) {
            return $this->getCurrentUser() ? filter_var($this->getCurrentUser()->tryMention(), FILTER_SANITIZE_SPECIAL_CHARS) : false;
        }

        return $this->getCurrentUser() ? '<a href="tg://user?id=' . $this->getCurrentUser()->getId() . '">' . filter_var($this->getCurrentUser()->getFirstName(), FILTER_SANITIZE_SPECIAL_CHARS) . '</a>' : false;
    }

    /**
     * Get current user id safely (prevent getId() on null)
     *
     * @return int|bool
     *
     * @throws BotException
     */
    protected function getCurrentUserId()
    {
        return $this->getCurrentUser() ? $this->getCurrentUser()->getId() : false;
    }

    /**
     * Handle 'quit' game action
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws TelegramException
     * @throws StorageException
     */
    protected function quitAction(): ServerResponse
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        if ($this->getUser('host') && $this->getCurrentUserId() === $this->getUserId('host')) {
            if ($this->getUser('guest')) {
                Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Quit, host migration: ' . $this->getCurrentUserMention() . ' => ' . $this->getUserMention('guest'));

                $currentUserMention = $this->getCurrentUserMention();

                $this->data['players']['host'] = $this->data['players']['guest'];
                $this->data['players']['guest'] = null;

                if ($this->saveData($this->data)) {
                    return $this->editMessage(__('{PLAYER} quit...', ['{PLAYER}' => $currentUserMention]) . PHP_EOL . __("{PLAYER_HOST} is now the host.", ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __("{PLAYER_HOST} is waiting for opponent to join...", ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __("Press {BUTTON} button to join.", ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
                }

                throw new StorageException();
            }

            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Quit (host): ' . $this->getCurrentUserMention());

            $this->data['players']['host'] = null;

            if ($this->saveData($this->data)) {
                return $this->editMessage('<i>' . __("This game session is empty.") . '</i>', $this->getReplyMarkup('empty'));
            }

            throw new StorageException();
        }

        if ($this->getUser('guest') && $this->getCurrentUserId() === $this->getUserId('guest')) {
            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Quit (guest): ' . $this->getCurrentUserMention());

            $currentUserMention = $this->getCurrentUserMention();

            $this->data['players']['guest'] = null;

            if ($this->saveData($this->data)) {
                return $this->editMessage(__('{PLAYER} quit...', ['{PLAYER}' => $currentUserMention]) . PHP_EOL . __("{PLAYER_HOST} is waiting for opponent to join...", ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __("Press {BUTTON} button to join.", ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
            }

            throw new StorageException();
        }

        Utilities::debugPrint('User quitting an empty game?');

        return $this->answerCallbackQuery();
    }

    /**
     * Handle 'kick' game action
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws TelegramException
     * @throws StorageException
     */
    protected function kickAction(): ServerResponse
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host')) {
            return $this->answerCallbackQuery(__("You're not the host!"), true);
        }

        if (!$this->getUser('guest')) {
            return $this->answerCallbackQuery(__("There is no player to kick!"), true);
        }

        if ($this->getUser('host')) {
            Utilities::isDebugPrintEnabled() && Utilities::debugPrint($this->getCurrentUserMention() . ' kicked ' . $this->getUserMention('guest'));

            $user = $this->getUserMention('guest');
            $this->data['players']['guest'] = null;

            if ($this->saveData($this->data)) {
                return $this->editMessage(__('{PLAYER_GUEST} was kicked...', ['{PLAYER_GUEST}' => $user]) . PHP_EOL . __("{PLAYER_HOST} is waiting for opponent to join...", ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __("Press {BUTTON} button to join.", ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
            }

            throw new StorageException();
        }

        Utilities::debugPrint('Kick executed on a game without a host');

        return $this->answerCallbackQuery();
    }

    /**
     * Handle 'start' game action
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws TelegramException
     * @throws StorageException
     */
    protected function startAction(): ServerResponse
    {
        if (!$this->getUser('host')) {
            return $this->editMessage('<i>' . __("This game session is empty.") . '</i>', $this->getReplyMarkup('empty'));
        }

        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        if ($this->getCurrentUserId() !== $this->getUserId('host')) {
            return $this->answerCallbackQuery(__("You're not the host!"), true);
        }

        if (!$this->getUser('host') || !$this->getUser('guest')) {
            Utilities::debugPrint('Received request to start the game but one of the players wasn\'t in the game');

            return $this->answerCallbackQuery();
        }

        Utilities::isDebugPrintEnabled() && Utilities::debugPrint($this->getCurrentUserMention());

        return $this->gameAction();
    }

    /**
     * Handle the game action
     *
     * This is just a dummy function.
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws TelegramException
     */
    protected function gameAction(): ServerResponse
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        return $this->answerCallbackQuery();
    }

    /**
     * Change language
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws TelegramException
     * @throws StorageException
     */
    protected function languageAction(): ServerResponse
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host')) {
            return $this->answerCallbackQuery(__("You're not the host!"), true);
        }

        $current_languge = Language::getCurrentLanguage();
        $selected_language = $this->languages[0];

        $picknext = false;
        foreach ($this->languages as $language) {
            if ($picknext) {
                $selected_language = $language;
                break;
            }

            if ($language === $current_languge) {
                $picknext = true;
            }
        }

        $this->data['settings']['language'] = $selected_language;

        if ($this->saveData($this->data)) {
            Utilities::debugPrint('Set language: ' . $selected_language);
            Language::set($selected_language);
        }

        if ($this->getUser('host') && $this->getUser('guest')) {
            return $this->editMessage(__('{PLAYER_GUEST} joined...', ['{PLAYER_GUEST}' => $this->getUserMention('guest')]) . PHP_EOL . __('Waiting for {PLAYER} to start...', ['{PLAYER}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to start.', ['{BUTTON}' => '<b>\'' . __('Play') . '\'</b>']), $this->getReplyMarkup('pregame'));
        }

        return $this->editMessage(__('{PLAYER_HOST} is waiting for opponent to join...', ['{PLAYER_HOST}' => $this->getUserMention('host')]) . PHP_EOL . __('Press {BUTTON} button to join.', ['{BUTTON}' => '<b>\'' . __('Join') . '\'</b>']), $this->getReplyMarkup('lobby'));
    }

    /**
     * Game empty keyboard
     *
     * @return array
     * @throws TelegramException
     */
    protected function emptyKeyboard(): array
    {
        return [
            [
                new InlineKeyboardButton(
                    [
                        'text'          => __('Create'),
                        'callback_data' => static::getCode() . ';new',
                    ]
                ),
            ],
        ];
    }

    /**
     * Game in lobby without guest keyboard
     *
     * @return array
     * @throws TelegramException
     */
    protected function lobbyKeyboard(): array
    {
        $inline_keyboard = [];

        if (count($this->languages) > 1) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => ucfirst(locale_get_display_language(Language::getCurrentLanguage(), Language::getCurrentLanguage())),
                        'callback_data' => static::getCode() . ";language",
                    ]
                ),
            ];
        }

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text'          => __('Quit'),
                    'callback_data' => static::getCode() . ";quit",
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => __('Join'),
                    'callback_data' => static::getCode() . ";join",
                ]
            ),
        ];

        return $inline_keyboard;
    }

    /**
     * Game in lobby with guest keyboard
     *
     * @return array
     * @throws TelegramException
     */
    protected function pregameKeyboard(): array
    {
        $inline_keyboard = [];

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text'          => __('Play'),
                    'callback_data' => static::getCode() . ";start",
                ]
            ),
        ];

        if (count($this->languages) > 1) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => ucfirst(locale_get_display_language(Language::getCurrentLanguage(), Language::getCurrentLanguage())),
                        'callback_data' => static::getCode() . ";language",
                    ]
                ),
            ];
        }

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text'          => __('Quit'),
                    'callback_data' => static::getCode() . ";quit",
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => __('Kick'),
                    'callback_data' => static::getCode() . ";kick",
                ]
            ),
        ];

        return $inline_keyboard;
    }

    /**
     * Keyboard for game in progress
     *
     * @param array  $board
     * @param string $winner
     *
     * @return InlineKeyboard|bool
     * @throws TelegramException
     * @throws BotException
     */
    protected function gameKeyboard(array $board, string $winner = null)
    {
        if (!isset($this->max_x) && !isset($this->max_y) && !isset($this->symbols)) {
            return false;
        }

        for ($x = 0; $x <= $this->max_x; $x++) {
            $tmp_array = [];

            for ($y = 0; $y <= $this->max_y; $y++) {
                if (isset($board[$x][$y])) {
                    $field = ($this->symbols['empty']) ?: ' ';

                    if ($board[$x][$y] == 'X' || $board[$x][$y] == 'O' || strpos($board[$x][$y], 'won')) {
                        if ($winner == 'X' && $board[$x][$y] == 'O') {
                            $field = $this->symbols[$board[$x][$y] . '_lost'];
                        } elseif ($winner == 'O' && $board[$x][$y] == 'X') {
                            $field = $this->symbols[$board[$x][$y] . '_lost'];
                        } elseif (isset($this->symbols[$board[$x][$y]])) {
                            $field = $this->symbols[$board[$x][$y]];
                        }
                    }

                    $tmp_array[] = new InlineKeyboardButton(
                        [
                            'text'          => $field,
                            'callback_data' => static::getCode() . ';game;' . $x . '-' . $y,
                        ]
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
                        'text'          => __('Play again!'),
                        'callback_data' => static::getCode() . ';start',
                    ]
                ),
            ];
        }

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text'          => __('Quit'),
                    'callback_data' => static::getCode() . ';quit',
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => __('Kick'),
                    'callback_data' => static::getCode() . ';kick',
                ]
            ),
        ];

        if (getenv('DEBUG') && $this->getCurrentUserId() == getenv('BOT_ADMIN')) {
            $this->boardPrint($board);

            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => 'DEBUG: ' . 'Restart',
                        'callback_data' => static::getCode() . ';start',
                    ]
                ),
            ];
        }

        return new InlineKeyboard(...$inline_keyboard);
    }

    /**
     * Debug print of game board
     *
     * @param array $board
     */
    protected function boardPrint(array $board): void
    {
        if (!empty($board) && is_array($board) && isset($this->max_y) && isset($this->max_x)) {
            $board_out = str_repeat(' ---', $this->max_x) . PHP_EOL;

            for ($x = 0; $x <= $this->max_x; $x++) {
                if (!isset($board[$x])) {
                    continue;
                }

                $line = '';

                for ($y = 0; $y <= $this->max_y; $y++) {
                    if (!isset($board[$x][$y])) {
                        continue;
                    }

                    $line .= '|' . (!empty($board[$x][$y]) ? ' ' . $board[$x][$y] . ' ' : '   ');
                }

                $line = str_replace('_won', '*', $line);
                
                $board_out .= $line . '|' . PHP_EOL;
                $board_out .= str_repeat(' ---', $this->max_x) . PHP_EOL;
            }

            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('CURRENT BOARD:' . PHP_EOL . $board_out);
        }
    }

    /**
     * Handle a case when game data is empty but received a game action request
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws TelegramException
     * @throws StorageException
     */
    protected function handleEmptyData(): ServerResponse
    {
        Utilities::debugPrint('Empty game data');

        $result = $this->editMessage('<i>' . __("Game session not found or expired.") . '</i>', $this->getReplyMarkup('empty'));

        if (!$result->isOk()) {
            return $result;
        }

        return $this->answerCallbackQuery();
    }
}
