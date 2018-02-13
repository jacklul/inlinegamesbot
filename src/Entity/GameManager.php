<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Entity;

use Bot\Entity\Game;
use Bot\Exception\BotException;
use Bot\Exception\StorageException;
use Bot\Helper\Botan;
use Bot\Helper\Debug;
use Bot\Helper\Language;
use Bot\Helper\Storage;
use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;

/**
 * Class GameManager
 *
 * This the 'manager', it does everything what's required before running a game
 *
 * @package Bot\Manager
 */
class GameManager
{
    /**
     * Game ID (inline_message_id)
     *
     * @var string
     */
    private $id;

    /**
     * Game object
     *
     * @var Game
     */
    private $game;

    /**
     * Currently used storage class name
     *
     * @var string|\Bot\Storage\Database\MySQL
     */
    private $storage;

    /**
     * Update object
     *
     * @var Update
     */
    private $update;

    /**
     * Game Manager constructor
     *
     * @param string  $id
     * @param string  $game_code
     * @param Command $command
     *
     * @throws BotException
     * @throws StorageException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function __construct($id, $game_code, Command $command)
    {
        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        if (empty($game_code)) {
            throw new BotException('Game code is empty!');
        }

        Debug::isEnabled() && Debug::print('ID: ' . $id);

        $this->id = $id;
        $this->update = $command->getUpdate();

        try {
            $this->storage = Storage::getClass();
            $this->storage::initializeStorage();
        } catch (StorageException $e) {
            $this->notifyAboutStorageFailure();
            throw $e;
        }

        if ($game = $this->findGame($game_code)) {
            $this->game = $game;

            /** @var \Bot\Entity\Game\Tictactoe $class */
            $class = get_class($this->game);
            Debug::isEnabled() && Debug::print('Game: ' . $class::getTitle());
        } else {
            Debug::isEnabled() && Debug::print('Game not found');
        }

        return $this;
    }

    /**
     * Find game class based on game code
     *
     * @param $game_code
     *
     * @return Game|bool
     */
    private function findGame($game_code)
    {
        if (is_dir(SRC_PATH . '/Entity/Game')) {
            foreach (new \DirectoryIterator(SRC_PATH . '/Entity/Game') as $file) {
                if (!$file->isDir() && !$file->isDot()) {
                    $game_class = '\Bot\Entity\Game\\' . basename($file->getFilename(), '.php');

                    /** @var \Bot\Entity\Game\Tictactoe $game_class */
                    if ($game_class::getCode() == $game_code) {
                        return new $game_class($this);
                    }
                }
            }
        }

        return false;
    }

    /**
     * Returns whenever the game can run (game is valid and loaded)
     *
     * @return bool
     */
    public function canRun(): bool
    {
        if ($this->game instanceof Game) {
            return true;
        }

        return false;
    }

    /**
     * Run the game class
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws StorageException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function run()
    {
        $callback_query = $this->getUpdate()->getCallbackQuery();
        $chosen_inline_result = $this->getUpdate()->getChosenInlineResult();

        if (!$this->storage) {
            return $this->notifyAboutStorageFailure();
        }

        if (!$this->storage::lockGame($this->id)) {
            return $this->notifyAboutStorageLock();
        }

        Debug::isEnabled() && Debug::print('BEGIN HANDLING THE GAME');

        try {
            if ($callback_query) {
                $result = $this->game->handleAction(explode(';', $callback_query->getData())[1]);

                $this->storage::unlockGame($this->id);

                Botan::track($this->getUpdate(), $this->getGame()::getTitle());  // track game traffic
            } elseif ($chosen_inline_result) {
                $result = $this->game->handleAction('new');

                $this->storage::unlockGame($this->id);

                Botan::track($this->getUpdate(), $this->getGame()::getTitle() . ' (new session)');  // track new game initialized event
            } else {
                throw new BotException('Unknown update received!');
            }
        } catch (BotException $e) {
            $this->notifyAboutBotFailure();
            throw $e;
        } catch (StorageException $e) {
            $this->notifyAboutStorageFailure();
            throw $e;
        }

        Debug::isEnabled() && Debug::print('GAME HANDLED');

        Language::set(Language::getDefaultLanguage());      // reset language in case running in getUpdates loop

        return $result;
    }

    /**
     * Show information to user that storage is not accessible
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function notifyAboutStorageFailure()
    {
        Debug::isEnabled() && Debug::print('Storage failure');

        if ($callback_query = $this->update->getCallbackQuery()) {
            return Request::answerCallbackQuery(
                [
                    'callback_query_id' => $callback_query->getId(),
                    'text'              => __('Database error!') . PHP_EOL . PHP_EOL . __("Try again in a few seconds."),
                    'show_alert'        => true,
                ]
            );
        }

        return Request::emptyResponse();
    }

    /**
     * Returns notice about storage lock
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    protected function notifyAboutStorageLock()
    {
        Debug::isEnabled() && Debug::print('Storage for this game is locked');

        if ($callback_query = $this->update->getCallbackQuery()) {
            return Request::answerCallbackQuery(
                [
                    'callback_query_id' => $callback_query->getId(),
                    'text'              => __('Process for this game is busy!') . PHP_EOL . PHP_EOL . __("Try again in a few seconds."),
                    'show_alert'        => true,
                ]
            );
        }

        return Request::emptyResponse();
    }

    /**
     * Returns notice about bot failure
     *
     * @return ServerResponse
     *
     * @throws BotException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    protected function notifyAboutBotFailure()
    {
        Debug::isEnabled() && Debug::print('Bot failure');

        if ($callback_query = $this->update->getCallbackQuery()) {
            return Request::answerCallbackQuery(
                [
                    'callback_query_id' => $callback_query->getId(),
                    'text'              => __('Bot error!') . PHP_EOL . PHP_EOL . __("Try again in a few seconds."),
                    'show_alert'        => true,
                ]
            );
        }

        return Request::emptyResponse();
    }

    /**
     * Get game id
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get game object
     *
     * @return Game|mixed
     */
    public function getGame(): Game
    {
        return $this->game;
    }

    /**
     * Get storage class
     *
     * @return string
     */
    public function getStorage(): string
    {
        return $this->storage;
    }

    /**
     * Return Update object
     *
     * @return Update
     */
    public function getUpdate(): Update
    {
        return $this->update;
    }
}
