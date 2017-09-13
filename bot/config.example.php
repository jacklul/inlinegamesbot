<?php
/**
 * Custom configuration
 *
 * This configuration array is compatible with Telegam Bot Manager
 * (https://github.com/php-telegram-bot/telegram-bot-manager)
 */
$config = [
    'commands'         => [
        'configs' => [
            'clean' => [
                'clean_interval' => 3600,
            ],
        ],
    ],
    
    'paths' => [
        'download' => DATA_PATH . '/tmp/',
        'upload'   => DATA_PATH . '/tmp/',
    ],
    
    'logging' => [
        'error'  => DATA_PATH . '/logs/Error.log',
        //'debug'  => DATA_PATH . '/logs/Debug.log',
        //'update' => DATA_PATH . '/logs/Update.log',
    ],
    
    'webhook'          => [
        'max_connections' => 10,
    ],

    'validate_request' => true,
    'valid_ips'        => [
        '149.154.167.197-149.154.167.233',
    ],
    
    'limiter' => [
        'enabled' => true,
        'options' => [
            'interval' => 0.5,
        ],
    ],
    
    'botan'            => [
        'options' => [
            'timeout' => 10,
        ],
    ],
];
