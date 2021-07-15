<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2021 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bot;

use Bot\Entity\TempFile;
use Bot\Exception\BotException;
use Bot\Exception\StorageException;
use Bot\Helper\Utilities;
use Bot\Storage\Driver\File;
use Bot\Storage\Storage;
use Dotenv\Dotenv;
use Exception;
use Gettext\Translator;
use GuzzleHttp\Client;
use InvalidArgumentException;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Exception\TelegramLogException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\TelegramLog;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Throwable;
use jacklul\MonologTelegramHandler\TelegramFormatter;
use jacklul\MonologTelegramHandler\TelegramHandler;

define("ROOT_PATH", realpath(dirname(__DIR__)));
define("APP_PATH", ROOT_PATH . '/bot');
define("SRC_PATH", ROOT_PATH . '/src');

/**
 * This is the master loader class, contains console commands and essential code for bootstrapping the bot
 */
class BotCore
{
    /**
     * Commands
     *
     * @var array
     */
    private static $commands = [
        'help'    => [
            'function'    => 'showHelp',
            'description' => 'Shows this help message',
        ],
        'set'     => [
            'function'    => 'setWebhook',
            'description' => 'Set the webhook',
        ],
        'unset'   => [
            'function'    => 'deleteWebhook',
            'description' => 'Delete the webhook',
        ],
        'info'    => [
            'function'    => 'webhookInfo',
            'description' => 'Print webhookInfo request result',
        ],
        'install' => [
            'function'    => 'installDb',
            'description' => 'Execute database creation script',
        ],
        'handle'  => [
            'function'    => 'handleWebhook',
            'description' => 'Handle incoming webhook update',
        ],
        'run'    => [
            'function'    => 'handleLongPolling',
            'description' => 'Run the bot using getUpdates in a loop',
        ],
        'cron'    => [
            'function'    => 'handleCron',
            'description' => 'Run scheduled commands once',
        ],
        'worker'  => [
            'function'    => 'handleWorker',
            'description' => 'Run scheduled commands every minute',
        ],
    ];
    /**
     * Config array
     *
     * @var array
     */
    private $config = [];
    /**
     * Telegram object
     *
     * @var Telegram
     */
    private $telegram;

    /**
     * Bot constructor
     *
     * @throws BotException
     */
    public function __construct()
    {
        if (!defined('ROOT_PATH')) {
            throw new BotException('Root path not defined!');
        }

        // Load environment variables from file if it exists
        if (class_exists(Dotenv::class) && file_exists(ROOT_PATH . '/.env')) {
            $dotenv = Dotenv::createUnsafeImmutable(ROOT_PATH);
            $dotenv->load();
        }

        // Debug mode
        if (getenv('DEBUG')) {
            Utilities::setDebugPrint();

            /*$logger = new Logger('console');
            $logger->pushHandler((new ErrorLogHandler())->setFormatter(new LineFormatter('[%datetime%] %message% %context% %extra%', 'Y-m-d H:i:s', false, true)));
            
            Utilities::setDebugPrintLogger($logger);*/
        }

        // Do not display errors by default
        ini_set('display_errors', (getenv('DEBUG') ? 1 : 0));

        // Set timezone
        date_default_timezone_set(getenv('TIMEZONE') ?: 'UTC');

        // Set custom data path if variable exists, otherwise do not use anything
        if (!empty($data_path = getenv('DATA_PATH'))) {
            define('DATA_PATH', str_replace('"', '', str_replace('./', ROOT_PATH . '/', $data_path)));
        }

        // gettext '__()' function must be initialized as all public messages are using it
        (new Translator())->register();

        // Load DEFAULT config
        $this->loadDefaultConfig();

        // Merge default config with user config
        $config_file = ROOT_PATH . '/config.php';
        if (file_exists($config_file)) {
            /** @noinspection PhpIncludeInspection */
            $config = include $config_file;

            if (is_array($config)) {
                $this->config = array_replace_recursive($this->config, $config);
            }
        }
    }

    /**
     * Load default config values
     */
    private function loadDefaultConfig(): void
    {
        $this->config = [
            'api_key'      => getenv('BOT_TOKEN'),
            'bot_username' => getenv('BOT_USERNAME'),
            'secret'       => getenv('BOT_SECRET'),
            'admins'       => [(int)getenv('BOT_ADMIN') ?: 0],
            'commands'     => [
                'paths'   => [
                    SRC_PATH . '/Command',
                ],
                'configs' => [
                    'cleansessions' => [
                        'clean_interval' => 86400,
                    ],
                ],
            ],
            'webhook'      => [
                'url'             => getenv('BOT_WEBHOOK'),
                'max_connections' => 100,
                'allowed_updates' => [
                    'message',
                    'inline_query',
                    'chosen_inline_result',
                    'callback_query',
                ],
            ],
            'mysql'        => [
                'host'     => getenv('DB_HOST'),
                'user'     => getenv('DB_USER'),
                'password' => getenv('DB_PASS'),
                'database' => getenv('DB_NAME'),
            ],
            'cron'         => [
                'groups' => [
                    'default' => [
                        '/cleansessions',
                    ],
                ],
            ],
        ];
    }

    /**
     * Run the bot
     *
     * @param bool $webhook
     *
     * @throws Throwable
     */
    public function run(bool $webhook = false): void
    {
        if (is_bool($webhook) && $webhook === true) {
            $arg = 'handle';    // from webspace allow only handling webhook
        } elseif (isset($_SERVER['argv'][1])) {
            $arg = strtolower(trim($_SERVER['argv'][1]));
        }

        try {
            if (!$this->telegram instanceof Telegram) {
                $this->initialize();
            }

            if (!empty($arg) && isset(self::$commands[$arg]['function'])) {
                $function = self::$commands[$arg]['function'];
                $this->$function();
            } else {
                $this->showHelp();

                if (!empty($arg)) {
                    print PHP_EOL . 'Invalid parameter specified!' . PHP_EOL;
                } else {
                    print PHP_EOL . 'No parameter specified!' . PHP_EOL;
                }
            }
        } catch (Throwable $e) {
            TelegramLog::error($e);
            throw $e;
        }
    }

    /**
     * Initialize Telegram object
     *
     * @throws TelegramException
     * @throws TelegramLogException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function initialize(): void
    {
        if ($this->telegram instanceof Telegram) {
            return;
        }

        Utilities::debugPrint('DEBUG MODE ACTIVE');

        $this->telegram = new Telegram($this->config['api_key'], $this->config['bot_username']);
        $monolog = new Logger($this->config['bot_username']);

        if (isset($this->config['logging']['error'])) {
            $monolog->pushHandler((new StreamHandler($this->config['logging']['error'], Logger::ERROR))->setFormatter(new LineFormatter(null, null, true)));
        }

        if (isset($this->config['logging']['debug'])) {
            $monolog->pushHandler((new StreamHandler($this->config['logging']['debug'], Logger::ERROR))->setFormatter(new LineFormatter(null, null, true)));
        }

        if (isset($this->config['logging']['update'])) {
            $update_logger = new Logger(
                $this->config['bot_username'] . '_update',
                [
                    (new StreamHandler($this->config['logging']['update'], Logger::INFO))->setFormatter(new LineFormatter('%message%' . PHP_EOL)),
                ]
            );
        }

        if (file_exists(ROOT_PATH . '/vendor/bin/heroku-php-nginx')) {
            $monolog->pushHandler(
                new FingersCrossedHandler(
                    new StreamHandler('php://stderr'),
                    new ErrorLevelActivationStrategy(Logger::WARNING)
                )
            );
        }

        if (isset($this->config['admins']) && !empty($this->config['admins'][0])) {
            $this->telegram->enableAdmins($this->config['admins']);

            $handler = new TelegramHandler($this->config['api_key'], (int)$this->config['admins'][0], Logger::ERROR);
            $handler->setFormatter(new TelegramFormatter());

            $handler = new DeduplicationHandler($handler, defined('DATA_PATH') ? DATA_PATH . '/monolog-dedup.log' : null);
            $handler->setLevel(Utilities::isDebugPrintEnabled() ? Logger::DEBUG : Logger::ERROR);

            $monolog->pushHandler($handler);
        }

        if (!empty($monolog->getHandlers())) {
            TelegramLog::initialize($monolog, $update_logger ?? null);
        }

        if (isset($this->config['custom_http_client'])) {
            Request::setClient(new Client($this->config['custom_http_client']));
        }

        if (isset($this->config['commands']['paths'])) {
            $this->telegram->addCommandsPaths($this->config['commands']['paths']);
        }

        if (isset($this->config['mysql']['host']) && !empty($this->config['mysql']['host'])) {
            $this->telegram->enableMySql($this->config['mysql']);
        }

        if (isset($this->config['paths']['download'])) {
            $this->telegram->setDownloadPath($this->config['paths']['download']);
        }

        if (isset($this->config['paths']['upload'])) {
            $this->telegram->setDownloadPath($this->config['paths']['upload']);
        }

        if (isset($this->config['commands']['configs'])) {
            foreach ($this->config['commands']['configs'] as $command => $config) {
                $this->telegram->setCommandConfig($command, $config);
            }
        }

        if (!empty($this->config['limiter']['enabled'])) {
            if (!empty($this->config['limiter']['options'])) {
                $this->telegram->enableLimiter($this->config['limiter']['options']);
            } else {
                $this->telegram->enableLimiter();
            }
        }
    }

    /**
     * Display usage help
     */
    private function showHelp(): void
    {
        print 'Bot Console' . ($this->config['bot_username'] ? ' (@' . $this->config['bot_username'] . ')' : '') . PHP_EOL . PHP_EOL;
        print 'Available commands:' . PHP_EOL;

        $commands = '';
        foreach (self::$commands as $command => $data) {
            if (!empty($commands)) {
                $commands .= PHP_EOL;
            }

            if (!isset($data['description'])) {
                $data['description'] = 'No description available';
            }

            $commands .= ' ' . $command . str_repeat(' ', 10 - strlen($command)) . '- ' . trim($data['description']);
        }

        print $commands . PHP_EOL;
    }

    /**
     * Handle webhook method request
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @throws TelegramException
     */
    private function handleWebhook(): void
    {
        if ($this->validateRequest()) {
            try {
                $this->telegram->handle();
            } catch (TelegramException $e) {
                if (strpos($e->getMessage(), 'Telegram returned an invalid response') === false) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Validate request to check if it comes from the Telegram servers
     * and also does it contain a secret string
     *
     * @return bool
     */
    private function validateRequest(): bool
    {
        if (PHP_SAPI !== 'cli') {
            $secret = getenv('BOT_SECRET');
            $secret_get = $_GET['s'] ?? '';

            if (!isset($secret, $secret_get) || $secret !== $secret_get) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set webhook
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @throws BotException
     * @throws TelegramException
     */
    private function setWebhook(): void
    {
        if (empty($this->config['webhook']['url'])) {
            throw new BotException('Webhook URL is empty!');
        }

        $options = [];

        if (!empty($this->config['webhook']['max_connections'])) {
            $options['max_connections'] = $this->config['webhook']['max_connections'];
        }

        if (!empty($this->config['webhook']['allowed_updates'])) {
            $options['allowed_updates'] = $this->config['webhook']['allowed_updates'];
        }

        if (!empty($this->config['webhook']['certificate'])) {
            $options['certificate'] = $this->config['webhook']['certificate'];
        }

        $url = $this->config['webhook']['url'];
        if (!empty($this->config['secret'])) {
            $query_string_char = '?';

            if (strpos($url, '?') !== false) {
                $query_string_char = '&';
            } elseif (substr($url, -1) !== '/') {
                $url = $url . '/';
            }

            $url = $url . $query_string_char . 'a=handle&s=' . $this->config['secret'];
        } else {
            throw new BotException('Secret is empty!');
        }

        $result = $this->telegram->setWebhook($url, $options);

        if ($result->isOk()) {
            print 'Webhook URL: ' . $this->config['webhook']['url'] . PHP_EOL;
            print $result->getDescription() . PHP_EOL;
        } else {
            print 'Request failed: ' . $result->getDescription() . PHP_EOL;
        }
    }

    /**
     * Delete webhook
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @throws TelegramException
     */
    private function deleteWebhook(): void
    {
        $result = $this->telegram->deleteWebhook();

        if ($result->isOk()) {
            print $result->getDescription();
        } else {
            print 'Request failed: ' . $result->getDescription();
        }
    }

    /**
     * Get webhook info
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function webhookInfo(): void
    {
        $result = Request::getWebhookInfo();

        if ($result->isOk()) {
            if (PHP_SAPI !== 'cli') {
                print '<pre>' . print_r($result->getResult(), true) . '</pre>' . PHP_EOL;
            } else {
                print print_r($result->getResult(), true) . PHP_EOL;
            }
        } else {
            print 'Request failed: ' . $result->getDescription() . PHP_EOL;
        }
    }

    /**
     * Handle getUpdates method
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @throws TelegramException
     */
    private function handleLongPolling(): void
    {
        if (PHP_SAPI !== 'cli') {
            print 'Cannot run this from the webspace!' . PHP_EOL;

            return;
        }

        if (!isset($this->config['mysql']['host']) || empty($this->config['mysql']['host'])) {
            $this->telegram->useGetUpdatesWithoutDatabase();
        }

        print '[' . date('Y-m-d H:i:s') . '] Running with getUpdates method...' . PHP_EOL;
        while (true) {
            set_time_limit(0);

            try {
                $server_response = $this->telegram->handleGetUpdates();
            } catch (TelegramException $e) {
                if (strpos($e->getMessage(), 'Telegram returned an invalid response') !== false) {
                    $server_response = new ServerResponse(['ok' => false, 'description' => 'Telegram returned an invalid response'], '');
                } else {
                    throw $e;
                }
            }

            if ($server_response->isOk()) {
                $update_count = count($server_response->getResult());

                if ($update_count > 0) {
                    print '[' . date('Y-m-d H:i:s') . '] Processed ' . $update_count . ' updates!' . ' (peak memory usage: ' . Utilities::formatBytes(memory_get_peak_usage()) . ')' . PHP_EOL;
                }
            } else {
                print '[' . date('Y-m-d H:i:s') . '] Failed to process updates!' . PHP_EOL;
                print 'Error: ' . $server_response->getDescription() . PHP_EOL;
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            usleep(333333);
        }
    }

    /**
     * Handle worker process
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @throws BotException
     * @throws TelegramException
     */
    private function handleWorker(): void
    {
        if (PHP_SAPI !== 'cli') {
            print 'Cannot run this from the webspace!' . PHP_EOL;

            return;
        }

        print '[' . date('Y-m-d H:i:s') . '] Initializing worker...' . PHP_EOL;

        $interval = 60;
        $sleep_time = 10;
        $last_run = time();

        if (getenv('DEBUG')) {
            $interval = 3;
        }

        while (true) {
            set_time_limit(0);

            if (time() < $last_run + $interval) {
                $next_run = $last_run + $interval - time();
                $sleep_time_this = $sleep_time;

                if ($next_run < $sleep_time_this) {
                    $sleep_time_this = $next_run;
                }

                print '[' . date('Y-m-d H:i:s') . '] Next run in ' . $next_run . ' seconds, sleeping for ' . $sleep_time_this . ' seconds...' . PHP_EOL;

                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                sleep($sleep_time_this);

                continue;
            }

            print '[' . date('Y-m-d H:i:s') . '] Running...' . PHP_EOL;

            $last_run = time();

            $this->handleCron();

            print '[' . date('Y-m-d H:i:s') . '] Finished!' . PHP_EOL;
        }
    }

    /**
     * Run scheduled commands
     *
     * @throws BotException
     * @throws TelegramException
     */
    private function handleCron(): void
    {
        $commands = [];

        $cronlock = new TempFile('cron');
        if ($cronlock->getFile() === null) {
            exit("Couldn't obtain lockfile!" . PHP_EOL);
        }

        $file = $cronlock->getFile()->getPathname();

        $fh = fopen($file, 'wb');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
            if (PHP_SAPI === 'cli') {
                print "There is already another cron task running in the background!" . PHP_EOL;
            }

            exit;
        }

        if (!empty($this->config['cron']['groups'])) {
            foreach ($this->config['cron']['groups'] as $command_group => $commands_in_group) {
                foreach ($commands_in_group as $command) {
                    $commands[] = $command;
                }
            }
        }

        if (empty($commands)) {
            throw new BotException('No commands to run!');
        }

        $this->telegram->runCommands($commands);

        if (flock($fh, LOCK_UN)) {
            fclose($fh);
            unlink($file);
        }
    }

    /**
     * Handle installing database structure
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     *
     * @throws StorageException
     */
    private function installDb(): void
    {
        /** @var File $storage_class */
        $storage_class = Storage::getClass();
        $storage_class_name = explode('\\', (string) $storage_class);

        print 'Installing storage structure (' . end($storage_class_name) . ')...' . PHP_EOL;

        if ($storage_class::createStructure()) {
            print 'Ok!' . PHP_EOL;
        } else {
            print 'Error!' . PHP_EOL;
        }
    }
}
