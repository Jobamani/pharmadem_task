<?php
$user = require_login();
$id = (int) input('id');

$stmt = db()->prepare(
    'SELECT d.*, u.name, u.designation, u.manager_id
     FROM daily_updates d JOIN users u ON u.id = d.user_id
     WHERE d.id = ?'
);
$stmt->execute([$id]);
$update = $stmt->fetch();

$allowed = $update && ($user['role'] === 'manager'
    ? (int) $update['manager_id'] === (int) $user['id']
    : (int) $update['user_id'] === (int) $user['id']);
if (!$allowed) {
    not_found('Daily update not found.');
}

$stmt = db()->prepare(
    'SELECT i.*, t.title, t.status, t.due_date, t.priority
     FROM daily_update_tasks i JOIN tasks t ON t.id = i.task_id
     WHERE i.daily_update_id = ? ORDER BY i.hours_spent DESC'
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$isOwnToday = (int) $update['user_id'] === (int) $user['id'] && $update['update_date'] === today();

render_header('Daily Update · ' . fmt_date($update['update_date']));
?>
<nav class="mb-2"><a href="<?= url('updates') ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Daily updates</a></nav>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><?= e($update['name']) ?> · <?= fmt_date($update['update_date']) ?></h1>
        <span class="text-muted"><?= e($update['designation']) ?> · submitted <?= fmt_datetime($update['created_at']) ?>
            <?= $update['updated_at'] !== $update['created_at'] ? ' · edited ' . fmt_datetime($update['updated_at']) : '' ?></span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-dark fs-6"><i class="bi bi-clock"></i> <?= e($update['hours_worked']) ?> h</span>
        <?php if ($isOwnToday): ?>
            <a href="<?= url('update_form') ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold">Work done</div>
            <div class="card-body pre-wrap"><?= e($update['summary']) ?></div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Tasks worked on</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($items as $i): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <a href="<?= url('task_view', ['id' => $i['task_id']]) ?>"><?= e($i['title']) ?></a>
                            <span><?= status_badge($i['status_after']) ?> <small class="text-muted"><?= e($i['hours_spent']) ?>h</small></span>
                        </div>
                        <?php if ($i['progress_note']): ?><div class="small text-muted"><?= e($i['progress_note']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                    <li class="list-group-item text-muted">No specific tasks were reported.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-4 border-primary">
            <div class="card-header bg-white fw-semibold text-primary"><i class="bi bi-list-ul"></i> To-do for next day</div>
            <div class="card-body pre-wrap"><?= $update['plan_next'] ? e($update['plan_next']) : '<span class="text-muted">—</span>' ?></div>
        </div>
        <div class="card border-warning">
            <div class="card-header bg-white fw-semibold text-warning-emphasis"><i class="bi bi-exclamation-circle"></i> Pending items / blockers</div>
            <div class="card-body pre-wrap"><?= $update['blockers'] ? e($update['blockers']) : '<span class="text-muted">None reported</span>' ?></div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
