<?php
$user = require_login();
if ($user['role'] !== 'employee') {
    flash('info', 'Daily updates are submitted by employees. Here are your team\'s updates.');
    redirect('updates');
}
$me = (int) $user['id'];
$today = today();
$db = db();

$stmt = $db->prepare('SELECT * FROM daily_updates WHERE user_id = ? AND update_date = ?');
$stmt->execute([$me, $today]);
$existing = $stmt->fetch() ?: null;

$items = [];
if ($existing) {
    $stmt = $db->prepare('SELECT task_id, status_after, progress_note, hours_spent FROM daily_update_tasks WHERE daily_update_id = ?');
    $stmt->execute([$existing['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $items[(int) $row['task_id']] = $row;
    }
}

// Open tasks, plus anything completed today so it can still be reported.
$stmt = $db->prepare(
    "SELECT id, title, status, priority, due_date FROM tasks
     WHERE assigned_to = ? AND (status <> 'completed' OR DATE(completed_at) = ?)
     ORDER BY status = 'completed', due_date, FIELD(priority, 'critical', 'high', 'medium', 'low')"
);
$stmt->execute([$me, $today]);
$tasks = [];
foreach ($stmt->fetchAll() as $t) {
    $tasks[(int) $t['id']] = $t;
}

$form = [
    'summary'      => $existing['summary'] ?? '',
    'plan_next'    => $existing['plan_next'] ?? '',
    'blockers'     => $existing['blockers'] ?? '',
    'hours_worked' => $existing['hours_worked'] ?? '',
];
$errors = [];

if (is_post()) {
    verify_csrf();
    $form = [
        'summary'      => input('summary'),
        'plan_next'    => input('plan_next'),
        'blockers'     => input('blockers'),
        'hours_worked' => input('hours_worked'),
    ];
    $worked = (array) ($_POST['worked'] ?? []);
    $items = [];
    foreach ($tasks as $taskId => $t) {
        if (!isset($worked[$taskId])) {
            continue;
        }
        $status = (string) ($_POST['status'][$taskId] ?? $t['status']);
        $hours  = (string) ($_POST['hours'][$taskId] ?? '0');
        $items[$taskId] = [
            'task_id'       => $taskId,
            'status_after'  => isset(TASK_STATUSES[$status]) ? $status : $t['status'],
            'progress_note' => mb_substr(trim((string) ($_POST['note'][$taskId] ?? '')), 0, 500),
            'hours_spent'   => $hours === '' ? '0' : $hours,
        ];
        if (!is_numeric($items[$taskId]['hours_spent']) || $items[$taskId]['hours_spent'] < 0 || $items[$taskId]['hours_spent'] > 24) {
            $errors['items'] = 'Hours per task must be a number between 0 and 24.';
        }
    }

    if ($form['summary'] === '') {
        $errors['summary'] = 'Describe the work you did today.';
    }
    if ($form['hours_worked'] === '') {
        $form['hours_worked'] = (string) array_sum(array_column($items, 'hours_spent'));
    }
    if (!is_numeric($form['hours_worked']) || $form['hours_worked'] < 0 || $form['hours_worked'] > 24) {
        $errors['hours_worked'] = 'Total hours must be between 0 and 24.';
    }

    if (!$errors) {
        $db->beginTransaction();
        $stmt = $db->prepare(
            'INSERT INTO daily_updates (user_id, update_date, summary, plan_next, blockers, hours_worked)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE summary = VALUES(summary), plan_next = VALUES(plan_next),
                                     blockers = VALUES(blockers), hours_worked = VALUES(hours_worked)'
        );
        $stmt->execute([$me, $today, $form['summary'], $form['plan_next'] ?: null, $form['blockers'] ?: null, $form['hours_worked']]);

        $stmt = $db->prepare('SELECT id FROM daily_updates WHERE user_id = ? AND update_date = ?');
        $stmt->execute([$me, $today]);
        $updateId = (int) $stmt->fetchColumn();

        $db->prepare('DELETE FROM daily_update_tasks WHERE daily_update_id = ?')->execute([$updateId]);
        $insert = $db->prepare('INSERT INTO daily_update_tasks (daily_update_id, task_id, status_after, progress_note, hours_spent) VALUES (?, ?, ?, ?, ?)');
        foreach ($items as $taskId => $item) {
            $insert->execute([$updateId, $taskId, $item['status_after'], $item['progress_note'] ?: null, $item['hours_spent']]);
            $note = 'Via daily update' . ($item['progress_note'] !== '' ? ': ' . $item['progress_note'] : '');
            change_task_status($tasks[$taskId], $item['status_after'], $me, $note);
        }
        $db->commit();

        flash('success', $existing ? "Today's update has been updated." : "Today's update has been submitted.");
        redirect('update_view', ['id' => $updateId]);
    }
}

render_header('Daily Update');
?>
<nav class="mb-2"><a href="<?= url('updates') ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> My updates</a></nav>
<h1 class="h3 mb-0">Daily Work Update</h1>
<p class="text-muted"><?= date('l, d F Y') ?><?= $existing ? ' · editing your submitted update' : '' ?></p>

<form method="post" novalidate>
    <?= csrf_field() ?>

    <div class="card mb-4">
        <div class="card-header bg-white fw-semibold">1. Tasks worked on today</div>
        <div class="card-body pb-0">
            <p class="small text-muted">Tick each task you worked on, set its current status and add a short progress note. Task statuses are updated automatically.</p>
            <?php if (isset($errors['items'])): ?><div class="alert alert-danger py-2"><?= e($errors['items']) ?></div><?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                <tr><th style="width: 40px"></th><th>Task</th><th style="width: 160px">Status</th><th>Progress note</th><th style="width: 100px">Hours</th></tr>
                </thead>
                <tbody>
                <?php foreach ($tasks as $taskId => $t): $item = $items[$taskId] ?? null; ?>
                    <tr class="<?= is_overdue($t) ? 'table-overdue' : '' ?>">
                        <td><input type="checkbox" class="form-check-input worked-toggle" name="worked[<?= $taskId ?>]" value="1" <?= $item ? 'checked' : '' ?>></td>
                        <td>
                            <a href="<?= url('task_view', ['id' => $taskId]) ?>" target="_blank"><?= e($t['title']) ?></a>
                            <div class="small text-muted">Due <?= fmt_date($t['due_date']) ?> <?= is_overdue($t) ? '<span class="badge bg-danger">Overdue</span>' : '' ?></div>
                        </td>
                        <td>
                            <select name="status[<?= $taskId ?>]" class="form-select form-select-sm">
                                <?php foreach (TASK_STATUSES as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= ($item['status_after'] ?? $t['status']) === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input name="note[<?= $taskId ?>]" class="form-control form-control-sm" maxlength="500" value="<?= e($item['progress_note'] ?? '') ?>" placeholder="What did you do?"></td>
                        <td><input name="hours[<?= $taskId ?>]" type="number" step="0.25" min="0" max="24" class="form-control form-control-sm task-hours" value="<?= e($item['hours_spent'] ?? '') ?>"></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tasks): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">You have no open tasks. Describe your work in the summary below.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white fw-semibold">2. Summary</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Work done today <span class="text-danger">*</span></label>
                <textarea name="summary" rows="4" class="form-control <?= isset($errors['summary']) ? 'is-invalid' : '' ?>" required><?= e($form['summary']) ?></textarea>
                <?= field_error($errors, 'summary') ?>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">To-do for the next working day</label>
                    <textarea name="plan_next" rows="3" class="form-control" placeholder="Planned work for tomorrow"><?= e($form['plan_next']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Pending items / blockers</label>
                    <textarea name="blockers" rows="3" class="form-control" placeholder="Anything waiting on someone else?"><?= e($form['blockers']) ?></textarea>
                </div>
            </div>
            <div class="mt-3" style="max-width: 220px">
                <label class="form-label">Total hours worked</label>
                <input name="hours_worked" id="hours_worked" type="number" step="0.25" min="0" max="24" class="form-control <?= isset($errors['hours_worked']) ? 'is-invalid' : '' ?>" value="<?= e($form['hours_worked']) ?>" placeholder="Auto from tasks">
                <?= field_error($errors, 'hours_worked') ?>
            </div>
        </div>
    </div>

    <button class="btn btn-primary btn-lg"><i class="bi bi-send"></i> <?= $existing ? 'Update' : 'Submit' ?> daily update</button>
</form>

<script>
// Tick the task row automatically when the employee starts filling it in,
// and keep the total hours in sync with per-task hours until it is edited by hand.
document.querySelectorAll('tbody tr').forEach(function (row) {
    var box = row.querySelector('.worked-toggle');
    if (!box) return;
    row.querySelectorAll('input:not(.worked-toggle), select').forEach(function (el) {
        el.addEventListener('change', function () { box.checked = true; });
    });
});
var total = document.getElementById('hours_worked');
var totalTouched = total.value !== '';
total.addEventListener('input', function () { totalTouched = true; });
document.querySelectorAll('.task-hours').forEach(function (el) {
    el.addEventListener('input', function () {
        if (totalTouched) return;
        var sum = 0;
        document.querySelectorAll('.task-hours').forEach(function (h) { sum += parseFloat(h.value) || 0; });
        total.value = sum ? sum : '';
    });
});
</script>
<?php render_footer(); ?>
