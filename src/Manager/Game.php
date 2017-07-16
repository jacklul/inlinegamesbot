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
use Bot\Helper\DebugLog;
use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\ServerResponse;
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

        DebugLog::log($id);

        if (DB::isDbConnected()) {
            $this->storage = 'Bot\Storage\BotDB';
        } elseif (getenv('DATABASE_URL')) {
            $this->storage = 'Bot\Storage\DB';
        } else {
            $this->storage = 'Bot\Storage\JsonFile';
        }

        $this->storage = 'Bot\Storage\DB';

        DebugLog::log('Using \'' . $this->storage . '\' storage');

        $this->id = $id;
        $this->update = $command->getUpdate();
        $this->telegram = $command->getTelegram();

        if ($game = $this->findGame($game_code)) {
            $this->game = $game;
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

                        DebugLog::log('Game: ' . $game_class);

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
     * @return ServerResponse|bool
     */
    public function run()
    {
        $callback_query = $this->getUpdate()->getCallbackQuery();
        $chosen_inline_result = $this->getUpdate()->getChosenInlineResult();

        if (!$this->storage::action('lock', $this->id)) {
            if ($callback_query = $this->update->getCallbackQuery()) {
                return Request::answerCallbackQuery(
                    [
                        'callback_query_id' => $callback_query->getId(),
                        'text' => __('Process for this game is busy!'),
                        'show_alert' => true
                    ]
                );
            }

            return Request::emptyResponse();
        }

        DebugLog::log('BEGIN HANDLE');

        $result = false;
        if ($callback_query) {
            $result = $this->game->handleAction(explode(';', $callback_query->getData())[1]);

            $this->storage::action('unlock', $this->id);

            Botan::track($this->getUpdate(), $this->getGame()::getTitle());  // track game traffic
        } elseif ($chosen_inline_result) {
            $result =  $this->game->handleAction('new');

            $this->storage::action('unlock', $this->id);

            Botan::track($this->getUpdate(), $this->getGame()::getTitle() . ' (new session)');  // track new game initialized event
        }

        DebugLog::log('END HANDLE');

        $this->runScheduledCommands();

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
     * Get storage object
     *
     * @return mixed
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * Load gane data
     *
     * @return mixed
     */
    public function getData()
    {
        DebugLog::log($this->id);
        return $this->storage::action('read', $this->id);
    }

    /**
     * Save game data
     *
     * @param  $data
     * @return mixed
     */
    public function setData($data)
    {
        DebugLog::log($this->id);

        $data['game_code'] = $this->game::getCode();    // make sure we have the game code in the data array for /clean command!

        return $this->storage::action('save', $this->id, $data);
    }

    /**
     * Return Update object
     *
     * @return \Longman\TelegramBot\Entities\Update
     */
    public function getUpdate()
    {
        return $this->update;
    }

    /**
     * Build-in scheduler
     *
     * @TODO redesign this for multi-dyno setup or move to heroku-scheduler
     *
     * @return mixed
     */
    private function runScheduledCommands(): void
    {
        $cron_check_file = VAR_PATH . '/cron';

        if (!file_exists($cron_check_file) || filemtime($cron_check_file) < strtotime('-5 minutes')) {
            if (flock(fopen($cron_check_file, "a+"), LOCK_EX)) {
                touch($cron_check_file);

                DebugLog::log('Running scheduled commands!');

                $this->telegram->runCommands(['/report', '/clean']);
            }
        }
    }
}
