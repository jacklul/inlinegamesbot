<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2018 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\inlinegamesbot;

use jacklul\inlinegamesbot\Entity\TempFile;
use jacklul\inlinegamesbot\Exception\BotException;
use jacklul\inlinegamesbot\Exception\StorageException;
use jacklul\inlinegamesbot\Helper\Utilities;
use Dotenv\Dotenv;
use Gettext\Translator;
use GuzzleHttp\Client;
use jacklul\MonologTelegramHandler\TelegramFormatter;
use jacklul\MonologTelegramHandler\TelegramHandler;
use Longman\IPTools\Ip;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\TelegramLog;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Logger;

define("ROOT_PATH", realpath(dirname(__DIR__)));
define("APP_PATH", ROOT_PATH . '/bot');
define("SRC_PATH", ROOT_PATH . '/src');

/**
 * Class BotCore
 *
 * This is the master loader class, contains console commands and essential code for bootstrapping the bot
 *
 * @package jacklul\inlinegamesbot
 */
class BotCore
{
    /**
     * Config array
     *
     * @var array
     */
    private $config = [];

    /**
     * Commands
     *
     * @var array
     */
    private $commands = [
        'help'       => [
            'function'    => 'showHelp',
            'description' => 'Shows this help message',
        ],
        'set'        => [
            'function'    => 'setWebhook',
            'description' => 'Set the webhook',
        ],
        'unset'      => [
            'function'    => 'deleteWebhook',
            'description' => 'Delete the webhook',
        ],
        'info'       => [
            'function'    => 'webhookInfo',
            'description' => 'Print webhookInfo request result',
        ],
        'install'    => [
            'function'    => 'installDb',
            'description' => 'Execute database creation script',
        ],
        'handle'     => [
            'function'    => 'handleWebhook',
            'description' => 'Handle incoming webhook update',
        ],
        'cron'       => [
            'function'    => 'handleCron',
            'description' => 'Run scheduled commands once',
        ],
        'loop'       => [
            'function'    => 'handleLongPolling',
            'description' => 'Run the bot using getUpdates in a loop',
        ],
        'worker'     => [
            'function'    => 'handleWorker',
            'description' => 'Run scheduled commands every minute',
        ],
    ];

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
        if (class_exists('Dotenv\Dotenv') && file_exists(ROOT_PATH . '/.env')) {
            $env = new Dotenv(ROOT_PATH, file_exists(ROOT_PATH . '/.env_dev') ? '.env_dev' : '.env');
            $env->load();
        }

        // Debug mode
        if (getenv('DEBUG')) {
            Utilities::setDebugPrint(true);
        }

        // Do not display errors by default
        ini_set('display_errors', (getenv("DEBUG") ? 1 : 0));

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
        $config_file = APP_PATH . '/config.php';
        if (file_exists($config_file)) {
            $config = $this->config;
            /** @noinspection PhpIncludeInspection */
            include $config_file;

            if (isset($config) && is_array($config)) {
                $this->config = $config;
            }
        }
    }

    /**
     * Load default config values
     */
    private function loadDefaultConfig()
    {
        $this->config = [
            'api_key'          => getenv('BOT_TOKEN'),
            'bot_username'     => getenv('BOT_USERNAME'),
            'secret'           => getenv('BOT_SECRET'),
            'admins'           => [(int)getenv('BOT_ADMIN') ?: 0],
            'commands'         => [
                'paths'   => [
                    SRC_PATH . '/Command',
                ],
                'configs' => [
                    'cleansessions' => [
                        'clean_interval' => 86400,
                    ],
                ],
            ],
            'webhook'          => [
                'url'             => getenv('BOT_WEBHOOK'),
                'max_connections' => 100,
                'allowed_updates' => [
                    'message',
                    'inline_query',
                    'chosen_inline_result',
                    'callback_query',
                ],
            ],
            'mysql'            => [
                'host'     => getenv('DB_HOST'),
                'user'     => getenv('DB_USER'),
                'password' => getenv('DB_PASS'),
                'database' => getenv('DB_NAME'),
            ],
            'validate_request' => true,
            'valid_ips'        => [
                '149.154.167.197-149.154.167.233',
            ],
            'botan'            => [
                'token'   => getenv('BOTAN_TOKEN'),
                'options' => [
                    'timeout' => 3,
                ],
            ],
            'cron'             => [
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
     * @throws \Throwable
     */
    public function run(bool $webhook = false)
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

            if (!empty($arg) && isset($this->commands[$arg]['function'])) {
                $function = $this->commands[$arg]['function'];
                $this->$function();
            } else {
                $this->showHelp();

                if (!empty($arg)) {
                    print PHP_EOL . 'Invalid parameter specified!' . PHP_EOL;
                } else {
                    print PHP_EOL . 'No parameter specified!' . PHP_EOL;
                }
            }
        } catch (\Throwable $e) {
            TelegramLog::error($e);
            throw $e;
        }
    }

    /**
     * Initialize Telegram object
     *
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \Longman\TelegramBot\Exception\TelegramLogException
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    private function initialize(): void
    {
        if ($this->telegram instanceof Telegram) {
            return;
        }

        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('DEBUG MODE');

        $this->telegram = new Telegram($this->config['api_key'], $this->config['bot_username']);

        if (isset($this->config['logging']['error'])) {
            TelegramLog::initErrorLog($this->config['logging']['error']);
        }

        if (isset($this->config['logging']['debug'])) {
            TelegramLog::initDebugLog($this->config['logging']['debug']);
        }

        if (isset($this->config['logging']['update'])) {
            TelegramLog::initUpdateLog($this->config['logging']['update']);
        }

        if (isset($this->config['admins']) && !empty($this->config['admins'][0])) {
            $this->telegram->enableAdmins($this->config['admins']);

            $monolog = new Logger($this->config['bot_username']);

            $handler = new TelegramHandler($this->config['api_key'], (int)$this->config['admins'][0], Logger::ERROR);
            $handler->setFormatter(new TelegramFormatter());

            $handler = new DeduplicationHandler($handler, defined('DATA_PATH') ? DATA_PATH . '/monolog-dedup.log' : null);
            $handler->setLevel(Logger::ERROR);

            $monolog->pushHandler($handler);
            TelegramLog::initialize($monolog);
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

        if (!empty($this->config['botan']['token'])) {
            if (!empty($this->config['botan']['options'])) {
                $this->telegram->enableBotan($this->config['botan']['token'], $this->config['botan']['options']);
            } else {
                $this->telegram->enableBotan($this->config['botan']['token']);
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
    private function showHelp()
    {
        print 'Bot Console' . ($this->config['bot_username'] ? ' (@' . $this->config['bot_username'] . ')' : '') . PHP_EOL . PHP_EOL;
        print 'Available commands:' . PHP_EOL;

        $commands = '';
        foreach ($this->commands as $command => $data) {
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
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function handleWebhook(): void
    {
        if ($this->validateRequest()) {
            $this->telegram->handle();
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
            $secret_get = isset($_GET['s']) ? $_GET['s'] : '';

            if (!isset($secret, $secret_get) || $secret !== $secret_get) {
                return false;
            }

            if (isset($this->config['valid_ip']) && !empty($this->config['valid_ip'] && is_array($this->config['valid_ip']))) {
                if ($this->config['validate_request'] && PHP_SAPI !== 'cli') {
                    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
                    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
                        if (filter_var(isset($_SERVER[$key]) ? $_SERVER[$key] : null, FILTER_VALIDATE_IP)) {
                            $ip = $_SERVER[$key];
                            break;
                        }
                    }

                    return Ip::match($ip, $this->config['valid_ips']);
                }
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
     * @throws \Longman\TelegramBot\Exception\TelegramException
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
     * @throws \Longman\TelegramBot\Exception\TelegramException
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
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function handleLongPolling(): void
    {
        if (PHP_SAPI !== 'cli') {
            print 'Cannot run this from the webspace!' . PHP_EOL;
            return;
        }

        print '[' . date('Y-m-d H:i:s', time()) . '] Running with getUpdates method...' . PHP_EOL;

        if (!isset($this->config['mysql']['host']) || empty($this->config['mysql']['host'])) {
            $this->telegram->useGetUpdatesWithoutDatabase(true);
        }

        while (true) {
            set_time_limit(0);

            $server_response = $this->telegram->handleGetUpdates();

            if ($server_response->isOk()) {
                $update_count = count($server_response->getResult());

                if ($update_count > 0) {
                    print '[' . date('Y-m-d H:i:s', time()) . '] Processed ' . $update_count . ' updates!' . ' (peak memory usage: ' . Utilities::formatBytes(memory_get_peak_usage()) . ')' . PHP_EOL;
                }
            } else {
                print '[' . date('Y-m-d H:i:s', time()) . '] Failed to process updates!' . PHP_EOL;
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
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function handleWorker(): void
    {
        if (PHP_SAPI !== 'cli') {
            print 'Cannot run this from the webspace!' . PHP_EOL;
            return;
        }

        print '[' . date('Y-m-d H:i:s', time()) . '] Initializing worker...' . PHP_EOL;

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

                print '[' . date('Y-m-d H:i:s', time()) . '] Next run in ' . $next_run . ' seconds, sleeping for ' . $sleep_time_this . ' seconds...' . PHP_EOL;

                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                sleep($sleep_time_this);

                continue;
            }

            print '[' . date('Y-m-d H:i:s', time()) . '] Running...' . PHP_EOL;

            $last_run = time();

            $this->handleCron();

            print '[' . date('Y-m-d H:i:s', time()) . '] Finished!' . PHP_EOL;
        }
    }

    /**
     * Run scheduled commands
     *
     * @throws BotException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function handleCron(): void
    {
        $commands = [];

        $cronlock = new TempFile('cron');
        $file = $cronlock->getFile()->getPathname();

        if ($file === null) {
            exit("Couldn't obtain lockfile!" . PHP_EOL);
        }

        $fh = fopen($file, 'w');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
            if (PHP_SAPI === 'cli') {
                echo "There is already another cron task running in the background!" . PHP_EOL;
            }

            exit;
        }

        if (!empty($this->config['cron']['groups'])) {
            foreach ($this->config['cron']['groups'] as $command_group => $commands_in_group) {
                foreach ($commands_in_group as $command) {
                    array_push($commands, $command);
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
    private function installDb()
    {
        /** @var \jacklul\inlinegamesbot\Storage\File $storage_class */
        $storage_class = Utilities::getStorageClass();

        print 'Installing storage structure (' . end(explode('\\', $storage_class)) . ')...' . PHP_EOL;

        if ($storage_class::createStructure()) {
            print 'Ok!' . PHP_EOL;
        } else {
            print 'Error!' . PHP_EOL;
        }
    }
}
