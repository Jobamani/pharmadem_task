<?php
declare(strict_types=1);

define('APP_PATH', __DIR__);
define('ROOT_PATH', dirname(__DIR__));

$config = require ROOT_PATH . '/config/database.php';
define('APP_NAME', $config['app']['name']);
date_default_timezone_set($config['app']['timezone']);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require APP_PATH . '/helpers.php';
require APP_PATH . '/performance.php';

const TASK_STATUSES = [
    'todo'        => 'To Do',
    'in_progress' => 'In Progress',
    'pending'     => 'Pending',
    'completed'   => 'Completed',
];

const TASK_PRIORITIES = [
    'low'      => 'Low',
    'medium'   => 'Medium',
    'high'     => 'High',
    'critical' => 'Critical',
];

function db(): PDO
{
    static $pdo = null;
    global $config;

    if ($pdo === null) {
        $c = $config['db'];
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
        try {
            $pdo = new PDO($dsn, $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Database connection failed. Check the settings in config/database.php.');
        }
    }
    return $pdo;
}
