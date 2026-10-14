<?php
$user = require_login();
$me = (int) $user['id'];
$manager = $user['role'] === 'manager';
$id = (int) input('id');
$task = find_task($id) ?? not_found('Task not found.');

if (is_post()) {
    verify_csrf();
    $action = input('action');
    $note = mb_substr(input('note'), 0, 1000);

    if ($action === 'status') {
        $status = input('status');
        if (isset(TASK_STATUSES[$status])) {
            if ($status === $task['status']) {
                if ($note !== '') {
                    log_task_activity($id, $me, 'comment', null, null, $note);
                    flash('success', 'Comment added.');
                }
            } else {
                change_task_status($task, $status, $me, $note);
                flash('success', 'Status changed to ' . TASK_STATUSES[$status] . '.');
            }
        }
        if (input('return') === 'dashboard') {
            redirect('dashboard');
        }
    } elseif ($action === 'delete' && $manager) {
        db()->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
        flash('success', 'Task deleted.');
        redirect('tasks');
    }
    redirect('task_view', ['id' => $id]);
}

$stmt = db()->prepare(
    'SELECT a.*, u.name FROM task_activity a JOIN users u ON u.id = a.user_id
     WHERE a.task_id = ? ORDER BY a.created_at DESC, a.id DESC'
);
$stmt->execute([$id]);
$activity = $stmt->fetchAll();

$stmt = db()->prepare(
    'SELECT d.id, d.update_date, i.status_after, i.progress_note, i.hours_spent
     FROM daily_update_tasks i JOIN daily_updates d ON d.id = i.daily_update_id
     WHERE i.task_id = ? ORDER BY d.update_date DESC'
);
$stmt->execute([$id]);
$progress = $stmt->fetchAll();
$totalHours = array_sum(array_column($progress, 'hours_spent'));

$daysLeft = (int) (new DateTime(today()))->diff(new DateTime($task['due_date']))->format('%r%a');

render_header($task['title']);
?>
<nav class="mb-2"><a href="<?= url('tasks') ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> All tasks</a></nav>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($task['title']) ?></h1>
        <?= status_badge($task['status'], $task['due_date']) ?> <?= priority_badge($task['priority']) ?>
    </div>
    <?php if ($manager): ?>
        <div class="d-flex gap-2">
            <a href="<?= url('task_form', ['id' => $id]) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
            <form method="post" onsubmit="return confirm('Delete this task permanently?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase">Description</h2>
                <p class="pre-wrap mb-0"><?= $task['description'] !== '' && $task['description'] !== null ? e($task['description']) : '<span class="text-muted">No description.</span>' ?></p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold">Update status</div>
            <div class="card-body">
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <?php foreach (TASK_STATUSES as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $task['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input name="note" class="form-control" placeholder="Note or comment (optional)" maxlength="1000">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span>Progress from daily updates</span>
                <span class="text-muted fw-normal"><?= number_format((float) $totalHours, 1) ?> h logged</span>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($progress as $p): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <a href="<?= url('update_view', ['id' => $p['id']]) ?>"><?= fmt_date($p['update_date']) ?></a>
                            <small><?= status_badge($p['status_after']) ?> · <?= e($p['hours_spent']) ?>h</small>
                        </div>
                        <?php if ($p['progress_note']): ?><div class="small"><?= e($p['progress_note']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (!$progress): ?>
                    <li class="list-group-item text-muted">Not reported in any daily update yet.</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">Activity</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($activity as $a): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span>
                                <strong><?= e($a['name']) ?></strong>
                                <?php if ($a['action'] === 'status_change'): ?>
                                    changed status <?= e(TASK_STATUSES[$a['old_status']] ?? $a['old_status']) ?> → <strong><?= e(TASK_STATUSES[$a['new_status']] ?? $a['new_status']) ?></strong>
                                <?php elseif ($a['action'] === 'created'): ?>
                                    created the task
                                <?php elseif ($a['action'] === 'comment'): ?>
                                    commented
                                <?php else: ?>
                                    updated the task
                                <?php endif; ?>
                            </span>
                            <small class="text-muted"><?= fmt_datetime($a['created_at']) ?></small>
                        </div>
                        <?php if ($a['note']): ?><div class="small text-muted pre-wrap"><?= e($a['note']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Assigned to</span><strong><?= e($task['assignee_name']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Assigned by</span><span><?= e($task['assigner_name']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Start date</span><span><?= fmt_date($task['start_date']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Due date</span><span><?= fmt_date($task['due_date']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Timeline</span>
                    <?php if ($task['status'] === 'completed'): ?>
                        <?php $late = substr($task['completed_at'], 0, 10) > $task['due_date']; ?>
                        <span class="<?= $late ? 'text-danger' : 'text-success' ?>"><?= $late ? 'Completed late' : 'Completed on time' ?></span>
                    <?php elseif ($daysLeft < 0): ?>
                        <span class="text-danger fw-semibold"><?= abs($daysLeft) ?> day(s) overdue</span>
                    <?php elseif ($daysLeft === 0): ?>
                        <span class="text-warning fw-semibold">Due today</span>
                    <?php else: ?>
                        <span><?= $daysLeft ?> day(s) left</span>
                    <?php endif; ?>
                </li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Completed at</span><span><?= fmt_datetime($task['completed_at']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">Created</span><span><?= fmt_datetime($task['created_at']) ?></span></li>
            </ul>
        </div>
    </div>
</div>
<?php render_footer(); ?>
