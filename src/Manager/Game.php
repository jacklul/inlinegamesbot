<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot\Manager;

use Bot\Entity\Game as GameEntity;
use Bot\Exception\BotException;
use Bot\Helper\Botan;
use Bot\Helper\Debug;
use Bot\Storage\Driver;
use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;

/**
 * Class Game
 *
 * @package Bot\Manager
 */
class Game
{
    /**
     * Game ID (inline_message_id)
     *
     * @var
     */
    private $id;

    /**
     * Game object
     *
     * @var
     */
    private $game;

    /**
     * Currently used storage class
     *
     * @var string
     */
    private $storage;

    /**
     * Update object
     *
     * @var \Longman\TelegramBot\Entities\Update
     */
    private $update;

    /**
     * Telegram object
     *
     * @var \Longman\TelegramBot\Telegram
     */
    private $telegram;

    /**
     * Game Manager constructor
     *
     * @param $id
     * @param $game_code
     * @param Command   $command
     *
     * @throws BotException
     */
    public function __construct($id, $game_code, Command $command)
    {
        if (empty($id)) {
            throw new BotException('Id is empty!');
        }

        if (empty($game_code)) {
            throw new BotException('Game code is empty!');
        }

        Debug::print('ID: ' .  $id);

        $this->id = $id;
        $this->update = $command->getUpdate();
        $this->telegram = $command->getTelegram();
        $this->storage = Driver::getStorageClass();

        if (!$this->storage::initializeStorage()) {
            Debug::print('Storage initialization failed: \'' . $this->storage . '\'');

            $this->storage = null;
        }

        if ($game = $this->findGame($game_code)) {
            $this->game = $game;
            $class = get_class($this->game);
            Debug::print('Game: ' . $class::getTitle());
        } else {
            Debug::print('Game not found!');
        }

        return $this;
    }

    /**
     * Find game class based on game code
     *
     * @param $game_code
     *
     * @return mixed
     */
    private function findGame($game_code)
    {
        if (is_dir(SRC_PATH . '/Entity/Game')) {
            foreach (new \DirectoryIterator(SRC_PATH . '/Entity/Game') as $file) {
                if (!$file->isDir() && !$file->isDot()) {
                    $game_class = '\Bot\Entity\Game\\' . basename($file->getFilename(), '.php');
                    if ($game_class::getCode() == $game_code) {
                        $game = new $game_class($this);
                        return $game;
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
        if ($this->game instanceof GameEntity) {
            return true;
        }

        return false;
    }

    /**
     * Run the game class
     *
     * @return ServerResponse
     * @throws BotException
     */
    public function run()
    {
        $callback_query = $this->getUpdate()->getCallbackQuery();
        $chosen_inline_result = $this->getUpdate()->getChosenInlineResult();

        if (!$this->storage) {
            Debug::print('Storage failure');

            if ($callback_query = $this->update->getCallbackQuery()) {
                return Request::answerCallbackQuery(
                    [
                        'callback_query_id' => $callback_query->getId(),
                        'text' => __('Database failure!') . PHP_EOL . PHP_EOL . __("Try again in a few seconds."),
                        'show_alert' => true
                    ]
                );
            }

            return Request::emptyResponse();
        }

        if (!$this->storage::lockGame($this->id)) {
            Debug::print('Storage for this game is locked');

            if ($callback_query = $this->update->getCallbackQuery()) {
                return Request::answerCallbackQuery(
                    [
                        'callback_query_id' => $callback_query->getId(),
                        'text' => __('Process for this game is busy!') . PHP_EOL . PHP_EOL . __("Try again in a few seconds."),
                        'show_alert' => true
                    ]
                );
            }

            return Request::emptyResponse();
        }

        Debug::print('BEGIN HANDLING THE GAME');

        if ($callback_query) {
            $result = $this->game->handleAction(explode(';', $callback_query->getData())[1]);

            $this->storage::unlockGame($this->id);

            Botan::track($this->getUpdate(), $this->getGame()::getTitle());  // track game traffic
        } elseif ($chosen_inline_result) {
            $result =  $this->game->handleAction('new');

            $this->storage::unlockGame($this->id);

            Botan::track($this->getUpdate(), $this->getGame()::getTitle() . ' (new session)');  // track new game initialized event
        } else {
            throw new BotException('Unknown update received!');
        }

        Debug::print('GAME HANDLED');

        return $result;
    }

    /**
     * Get game id
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get game object
     *
     * @return mixed
     */
    public function getGame()
    {
        return $this->game;
    }

    /**
     * Get storage class
     *
     * @return string
     */
    public function getStorage()
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

    /**
     * Save game data
     *
     * @param  $data
     * @return bool
     */
    public function saveData($data): bool
    {
        $data['game_code'] = $this->game::getCode();    // make sure we have the game code in the data array for /clean command!
        return $this->storage::insertToGame($this->id, $data);
    }
}
