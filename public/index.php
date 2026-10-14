<?php
/**
 * Front controller: every request goes through index.php?page=<name>.
 * Only pages listed here can be loaded.
 */
require __DIR__ . '/../app/bootstrap.php';

$routes = [
    'login', 'logout', 'dashboard',
    'tasks', 'task_form', 'task_view',
    'updates', 'update_form', 'update_view',
    'reports', 'performance', 'review_form', 'review_view',
    'employees', 'employee_form',
];

$page = $_GET['page'] ?? (current_user() ? 'dashboard' : 'login');

if (!in_array($page, $routes, true)) {
    not_found();
}

require APP_PATH . '/pages/' . $page . '.php';
