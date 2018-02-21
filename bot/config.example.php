<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2018 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Custom configuration
 *
 * Each setting must be set using "$config['xxx']['xxx'] = 'value'" format
 * Using $config = [] will overwrite essential defaults!
 */

$config['commands']['configs']['clean']['clean_interval'] = 3600;
$config['webhook']['max_connections'] = 5;
$config['logging']['error'] = DATA_PATH . '/logs/Error.log';
