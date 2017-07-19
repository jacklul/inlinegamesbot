<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Bot;

use Bot\Exception\BotException;
use Bot\Helper\Debug;
use Dotenv\Dotenv;
use Gettext\Translator;
use Longman\IPTools\Ip;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Exception\TelegramLogException;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\TelegramLog;
use Longman\TelegramBot\Request;

/**
 * Class Kernel
 */
class Kernel
{
    /**
     * Argument passed
     *
     * @var string
     */
    private $arg = '';

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
     * App constructor
     *
     * @throws BotException
     */
    public function __construct()
    {
        if (!defined('ROOT_PATH')) {
            throw new BotException('Root path not defined!');
        }

        define("BIN_PATH", ROOT_PATH . '/bin');
        define("APP_PATH", ROOT_PATH . '/app');
        define("SRC_PATH", ROOT_PATH . '/src');
        define("VAR_PATH", ROOT_PATH . '/var');

        if (file_exists(ROOT_PATH . '/.env')) {
            $env = new Dotenv(ROOT_PATH);
            $env->load();
        }

        (new Translator())->register();

        $bot_config_file = APP_PATH . '/config.php';
        if (!file_exists($bot_config_file)) {
            throw new BotException('Configuration file doesn\'t exist!');
        }

        require_once $bot_config_file;

        if (isset($config) && is_array($config)) {
            $this->config = $config;
        } else {
            throw new BotException('Couldn\'t load configuration!');
        }

        try {
            if (isset($config['logging']['error'])) {
                TelegramLog::initErrorLog($this->config['logging']['error']);
            }

            if (isset($this->config['logging']['debug'])) {
                TelegramLog::initDebugLog($this->config['logging']['debug']);
            }

            if (isset($this->config['logging']['update'])) {
                TelegramLog::initUpdateLog($this->config['logging']['update']);
            }
        } catch (TelegramLogException $e) {
            throw new BotException($e->getMessage());
        }

        if (isset($_SERVER['argv'][1])) {
            $this->arg = strtolower(trim($_SERVER['argv'][1]));
        } elseif (isset($_GET['a']) && $_GET['a'] === 'handle') {   // from webspace allow only handling webhook
            $this->arg = 'handle';
        }

        return $this;
    }

    /**
     * Run the bot
     */
    public function run()
    {
        try {
            if (!$this->telegram instanceof Telegram) {
                $this->initialize();
            }

            switch($this->arg) {
                default:
                case 'handle':
                    $this->handleWebhook();
                    break;
                case 'loop':
                    $this->handleLongPolling();
                    break;
                case 'cron':
                    $this->handleCron();
                    break;
                case 'set':
                    $this->setWebhook();
                    break;
                case 'unset':
                    $this->deleteWebhook();
                    break;
                case 'info':
                    $this->webhookInfo();
                    break;
                case 'worker':
                    $this->handleWorker();
                    break;
            }
        } catch (TelegramException $e) {
            TelegramLog::error($e);
        } catch (BotException $e) {
            TelegramLog::error($e);
        } catch (\Exception $e) {
            TelegramLog::error($e);
        } catch (\Throwable $e) {
            TelegramLog::error($e);
        } catch (\Error $e) {
            TelegramLog::error($e);
        } finally {
            if (isset($e)) {
                throw new BotException($e->getMessage());
            }
        }
    }

    /**
     * Initialize Telegram object
     */
    private function initialize(): void
    {
        Debug::log('DEBUG MODE');

        $this->telegram = new Telegram($this->config['api_key'], $this->config['bot_username']);

        if (isset($this->config['commands']['paths'])) {
            $this->telegram->addCommandsPaths($this->config['commands']['paths']);
        }

        if (isset($this->config['admins']) && !empty($this->config['admins'][0])) {
            $this->telegram->enableAdmins($this->config['admins']);
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
     * Validate request to check if it comes from the Telegram servers
     * and also does it contain a secret string
     *
     * @return bool
     */
    private function validateRequest(): bool
    {
        if (!defined('STDIN')) {
            if (!empty($this->config['secret']) && isset($_GET['s']) && $_GET['s'] !== $this->config['secret']) {
                return false;
            }

            if (!empty($this->config['valid_ip'] && is_array($this->config['valid_ip']))) {
                if ($this->config['validate_request'] && !defined('STDIN')) {
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
                        if (filter_var($_SERVER[$key] ?? null, FILTER_VALIDATE_IP)) {
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
     * Handle webhook method request
     */
    private function handleWebhook(): void
    {
        if ($this->validateRequest()) {
            $this->telegram->handle();
        }

        $this->runAfterWebhook();
    }

    /**
     * Set webhook
     *
     * @throws BotException
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
            }

            $url = $url . $query_string_char . 'a=handle&s=' . $this->config['secret'];
        }

        $result = $this->telegram->setWebhook($url, $options);

        if ($result->isOk()) {
            print $result->getDescription();
        } else {
            print 'Request failed: ' . $result->getDescription();
        }
    }

    /**
     * Delete webhook
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

    private function webhookInfo(): void
    {
        $result = Request::getWebhookInfo();

        if ($result->isOk()) {
            print_r($result->getResult());
        } else {
            print 'Request failed: ' . $result->getDescription();
        }
    }

    /**
     * Run scheduled commands
     *
     * @throws BotException
     */
    private function handleCron(): void
    {
        $commands = [];

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
    }

    /**
     * Handle getUpdates method
     */
    private function handleLongPolling(): void
    {
        while(true) {
            set_time_limit(0);

            $server_response = $this->telegram->handleGetUpdates();

            if ($server_response->isOk()) {
                $update_count = count($server_response->getResult());
                echo ($update_count === 0) ? "\r" : '';
                echo date('Y-m-d H:i:s', time()) . ' - Processed ' . $update_count . ' updates!';
                echo ($update_count > 0) ? PHP_EOL : '';
            } else {
                echo date('Y-m-d H:i:s', time()) . ' - Failed to process updates!' . PHP_EOL;
                echo $server_response->printError() . PHP_EOL;
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            usleep(333333);
        }
    }

    /**
     * Handle worker process
     */
    private function handleWorker(): void
    {
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

                print 'Next run in ' . $next_run . ' seconds, sleeping for ' . $sleep_time_this . ' seconds...' . PHP_EOL;

                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                sleep($sleep_time_this);

                continue;
            }

            print 'Running...' . PHP_EOL;

            $last_run = time();

            $this->handleCron();

            print 'Finished, memory usage: ' . round(memory_get_usage() / 1024 / 1024, 2) . 'M, peak: ' . round(memory_get_peak_usage() / 1024 / 1024, 2) . 'M.' . PHP_EOL;
        }
    }

    /**
     * A task to run after handling webhook
     */
    private function runAfterWebhook()
    {
        $cron_check_file = VAR_PATH . '/reporter';

        if (!file_exists($cron_check_file) || filemtime($cron_check_file) < strtotime('-1 minutes')) {
            if (flock(fopen($cron_check_file, "a+"), LOCK_EX)) {
                touch($cron_check_file);

                $command = $this->config['cron']['groups']['default'][0];

                Debug::log('Running ' . $command . ' command!');

                $this->telegram->runCommands([$command]);

                // Remove temporary files
                if (is_dir($dir = VAR_PATH . '/tmp')) {
                    foreach (new \DirectoryIterator($dir) as $file) {
                        if (!$file->isDir() && !$file->isDot() && $file->getMTime() < strtotime('-1 minutes')) {
                            @unlink($dir . '/' . $file->getFilename());
                        }
                    }
                }

                Debug::memoryUsage();
            }
        }
    }
}
