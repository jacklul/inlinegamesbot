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

use Bot\Exception\BotException;
use Bot\Exception\StorageException;
use Bot\Helper\Botan;
use Bot\Helper\Language;
use Bot\Helper\Utilities;
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
     * Game session ID (inline_message_id)
     *
     * @var string
     */
    private $id;

    /**
     * Current game object
     *
     * @var Game
     */
    private $game;

    /**
     * Currently used storage class name
     *
     * @var string|\Bot\Storage\File
     */
    private $storage;

    /**
     * Telegram Update object
     *
     * @var Update
     */
    private $update;

    /**
     * GameManager constructor
     *
     * @param string $id
     * @param string $game_code
     * @param Command $command
     *
     * @throws BotException
     * @throws StorageException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function __construct(string $id, string $game_code, Command $command)
    {
        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        if (empty($game_code)) {
            throw new BotException('Game code is empty!');
        }

        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('ID: ' . $id);

        $this->id = $id;
        $this->update = $command->getUpdate();

        try {
            /** @var \Bot\Storage\File $storage_class */
            $this->storage = $storage_class = Utilities::getStorageClass();
            $storage_class::initializeStorage();
        } catch (StorageException $e) {
            $this->notifyAboutStorageFailure();
            throw $e;
        }

        if ($game = $this->findGame($game_code)) {
            /** @var \Bot\Entity\Game $game_class */
            $this->game = $game_class = $game;

            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Game: ' . $game_class::getTitle());
        } else {
            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Game not found');
        }

        return $this;
    }

    /**
     * Find game class based on game code
     *
     * @param string $game_code
     *
     * @return Game|bool
     */
    private function findGame(string $game_code)
    {
        if (is_dir(SRC_PATH . '/Entity/Game')) {
            foreach (new \DirectoryIterator(SRC_PATH . '/Entity/Game') as $file) {
                if (!$file->isDir() && !$file->isDot() && $file->getExtension() === 'php') {
                    $game_class = '\Bot\Entity\Game\\' . basename($file->getFilename(), '.php');

                    /** @var \Bot\Entity\Game $game_class */
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

        if (!$storage_class = $this->storage) {
            return $this->notifyAboutStorageFailure();
        }

        if (!$storage_class::lockGame($this->id)) {
            return $this->notifyAboutStorageLock();
        }

        $game_class = $this->game;

        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('BEGIN HANDLING THE GAME');

        try {
            if ($callback_query) {
                $result = $this->game->handleAction(explode(';', $callback_query->getData())[1]);
                $storage_class::unlockGame($this->id);

                Botan::track($this->getUpdate(), $game_class::getTitle());  // track game traffic
            } elseif ($chosen_inline_result) {
                $result = $this->game->handleAction('new');
                $storage_class::unlockGame($this->id);

                Botan::track($this->getUpdate(), $game_class::getTitle() . ' (new session)');  // track new game initialized event
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

        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('GAME HANDLED');

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
        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Storage failure');

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
        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Storage is locked');

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
        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Bot failure');

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
