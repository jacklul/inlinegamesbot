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
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;

/**
 * Class Game
 *
 * @package Bot
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
     * Game Manager constructor.
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

        Debug::log('ID: ' .  $id);

        $this->id = $id;
        $this->update = $command->getUpdate();
        $this->telegram = $command->getTelegram();

        if (DB::isDbConnected() && !getenv('DEBUG_NO_BOTDB')) {
            $this->storage = 'Bot\Storage\BotDB';
        } elseif (getenv('DATABASE_URL')) {
            $this->storage = Driver::getDriver();
        } else {
            $this->storage = 'Bot\Storage\File';
        }

        if ($env_storage = getenv('DEBUG_STORAGE')) {
            $this->storage = $env_storage;
        }

        if (!class_exists($this->storage)) {
            throw new BotException('Storage class doesn\'t exist: ' . $this->storage);
        }

        Debug::log('Storage: \'' . $this->storage . '\'');

        if (!$this->storage::initializeStorage()) {
            $this->storage = null;
        }

        if ($game = $this->findGame($game_code)) {
            $this->game = $game;
            $class = get_class($this->game);
            Debug::log('Game: ' . $class::getTitle());
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
     * @return bool|ServerResponse
     * @throws BotException
     */
    public function run()
    {
        $callback_query = $this->getUpdate()->getCallbackQuery();
        $chosen_inline_result = $this->getUpdate()->getChosenInlineResult();

        if (!$this->storage) {
            Debug::log('Storage failure');

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

        if (!$this->storage::lockStorage($this->id)) {
            Debug::log('Storage for this game is locked');

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

        Debug::log('BEGIN HANDLING THE GAME');

        if ($callback_query) {
            $result = $this->game->handleAction(explode(';', $callback_query->getData())[1]);

            $this->storage::unlockStorage($this->id);

            Botan::track($this->getUpdate(), $this->getGame()::getTitle());  // track game traffic
        } elseif ($chosen_inline_result) {
            $result =  $this->game->handleAction('new');

            $this->storage::unlockStorage($this->id);

            Botan::track($this->getUpdate(), $this->getGame()::getTitle() . ' (new session)');  // track new game initialized event
        } else {
            throw new BotException('Unknown update received!');
        }

        Debug::log('GAME HANDLED');

        $this->reportIssues();

        return $result;
    }

    /**
     * Get game id
     *
     * @return mixed
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
    public function getUpdate()
    {
        return $this->update;
    }

    /**
     * Save game data
     *
     * @param  $data
     * @return mixed
     */
    public function saveData($data)
    {
        $data['game_code'] = $this->game::getCode();    // make sure we have the game code in the data array for /clean command!
        return $this->storage::insertToStorage($this->id, $data);
    }

    /**
     * Scheduled logs/crashdumps reporter
     *
     * @return mixed
     */
    private function reportIssues(): void
    {
        $cron_check_file = VAR_PATH . '/reporter';

        if (!file_exists($cron_check_file) || filemtime($cron_check_file) < strtotime('-5 minutes')) {
            if (flock(fopen($cron_check_file, "a+"), LOCK_EX)) {
                touch($cron_check_file);

                Debug::log('Running /report command!');

                $this->telegram->runCommands(['/report']);
            }
        }
    }
}
