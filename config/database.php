<?php
/**
 * Application configuration.
 *
 * Values can be overridden with environment variables (useful on a hosting
 * platform), or locally by creating config/database.local.php (git-ignored)
 * that returns e.g. ['db' => ['password' => 'secret']].
 */
$config = [
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'name'     => getenv('DB_NAME') ?: 'task_system',
        'user'     => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        'name'     => 'WorkTrack',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Kolkata',
    ],
];

if (is_file(__DIR__ . '/database.local.php')) {
    $config = array_replace_recursive($config, require __DIR__ . '/database.local.php');
}

return $config;
