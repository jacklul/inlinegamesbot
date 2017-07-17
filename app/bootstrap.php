<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2017 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use Bot\Helper\Debug;
use Dotenv\Dotenv;
use Gettext\Translator;

/**
 * Make sure root path is defined
 */
if (!defined('ROOT_PATH')) {
    define("ROOT_PATH", realpath(__DIR__ . '/../'));
}

/**
 * Define application paths
 */
define("BIN_PATH", ROOT_PATH . '/bin');
define("APP_PATH", ROOT_PATH . '/app');
define("SRC_PATH", ROOT_PATH . '/src');
define("VAR_PATH", ROOT_PATH . '/var');

/**
 * Load Composer's autoloader
 */
require_once ROOT_PATH . '/vendor/autoload.php';

/**
 * Load environment variables with Dotenv
 */
if (file_exists(ROOT_PATH . '/.env')) {
    $env = new Dotenv(ROOT_PATH);
    $env->load();
    $env->required(['BOT_TOKEN', 'BOT_USERNAME']);
}

/**
 * Default configuration (Telegram Bot Manager)
 */
$config = [
    'api_key'      => getenv('BOT_TOKEN'),
    'bot_username' => getenv('BOT_USERNAME'),
    'secret'       => getenv('BOT_SECRET'),
    'commands' => [
        'paths' => [
            SRC_PATH . '/Command',
        ],
        'configs' => [
            'report' => [
                'dirs_to_report' => [
                    VAR_PATH . '/logs',
                    VAR_PATH . '/crashdumps'
                ]
            ],
            'clean' => [
                'clean_interval' => 86400,
            ],
        ],
    ],
    'webhook' => [
        'url'             => getenv('BOT_WEBHOOK'),
        'max_connections' => 20,
        'allowed_updates' => [
            'message',
            'inline_query',
            'chosen_inline_result',
            'callback_query',
        ],
    ],
    'logging' => [
        'error'  => VAR_PATH . '/logs/Error.log',
    ],
    'validate_request' => true,
    'limiter' => [
        'enabled' => true,
        'options' => [
            'interval' => 0.33,
        ],
    ],
    'botan' => [
        'token'   => getenv('BOTAN_TOKEN'),
        'options' => [
            'timeout' => 5,
        ],
    ],
    'cron' => [
        'groups' => [
            'default' => [
                '/report',
                '/clean',
            ],
        ],
    ],
];

if (!empty($admin = (integer)getenv('BOT_ADMIN'))) {
    $config['admins'] = [$admin];
}

if (!empty(getenv('DB_HOST'))) {
    $config['mysql'] = [
        'host' => getenv('DB_HOST'),
        'user' => getenv('DB_USER'),
        'password' => getenv('DB_PASS'),
        'database' => getenv('DB_NAME'),
    ];
}

/**
 * Load user configuration and merge it with the default one
 */
if (file_exists(APP_PATH . '/config/config.php')) {
    $default_config = $config;

    include_once APP_PATH . '/config/config.php';

    if (isset($config) && is_array($config)) {
        $config = array_merge($default_config, $config);
    }
}

/**
 * Enable PHP error logging to file
 */
ini_set('log_errors', 1);
ini_set('display_errors', 1);
ini_set('error_log', (isset($config['logging']['error']) ? $config['logging']['error'] : ROOT_PATH . '/error.log'));

/**
 * Register '__()' function (gettext-like locale system)
 */
(new Translator())->register();

/**
 * Print notice about being in Debug mode
 */
Debug::log('RUNNING IN DEBUG MODE');
