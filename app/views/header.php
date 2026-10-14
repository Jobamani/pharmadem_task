<?php
/** @var string $pageTitle */
$user = current_user();
$active = $_GET['page'] ?? 'dashboard';
$nav = [
    'dashboard'   => ['Dashboard', 'speedometer2'],
    'tasks'       => ['Tasks', 'list-check'],
    'updates'     => ['Daily Updates', 'journal-text'],
    'performance' => [$user && $user['role'] === 'manager' ? 'Performance' : 'My Performance', 'graph-up-arrow'],
];
if ($user && $user['role'] === 'manager') {
    $nav['reports']   = ['Reports', 'bar-chart'];
    $nav['employees'] = ['Team', 'people'];
}
// Sub-pages highlight their parent menu item.
$parents = ['task_form' => 'tasks', 'task_view' => 'tasks', 'update_form' => 'updates', 'update_view' => 'updates',
            'review_form' => 'performance', 'review_view' => 'performance', 'employee_form' => 'employees'];
$active = $parents[$active] ?? $active;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body>
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 d-print-none">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="<?= url('dashboard') ?>"><i class="bi bi-clipboard2-check"></i> <?= e(APP_NAME) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($nav as $page => [$label, $icon]): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === $page ? 'active' : '' ?>" href="<?= url($page) ?>"><i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-3 text-light">
                <span><i class="bi bi-person-circle"></i> <?= e($user['name']) ?>
                    <span class="badge bg-secondary text-capitalize"><?= e($user['role']) ?></span></span>
                <form method="post" action="<?= url('logout') ?>" class="m-0">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container-xl pb-5">
    <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show">
            <?= e($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>
