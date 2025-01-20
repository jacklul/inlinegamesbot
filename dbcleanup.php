<?php

require_once __DIR__ . '/vendor/autoload.php';

if (class_exists(Dotenv\Dotenv::class) && file_exists(__DIR__ . '/.env')) {
	$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
	$dotenv->load();
}

$interval = 86400;

if (file_exists(__DIR__ . '/config.php')) {
	$config = include_once __DIR__ . '/config.php';
	$interval = $config['commands']['configs']['cleansessions']['clean_interval'] ?? $interval;
}

$dsn = parse_url(getenv('DATABASE_URL'));

if (isset($dsn['host'])) {
	$pdo = new PDO('mysql:' . 'host=' . $dsn['host'] . ';port=' . $dsn['port'] . ';dbname=' . ltrim($dsn['path'], '/'), $dsn['user'], $dsn['pass']);
	$query = $pdo->query('DELETE FROM game WHERE updated_at < NOW() - INTERVAL ' . $interval . ' SECOND;');
	echo $query->rowCount() . PHP_EOL;
} else {
	die('DATABASE_URL is not set or is not a valid DSN string!' . PHP_EOL);
}
