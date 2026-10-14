<?php
$user = require_login();
$me = (int) $user['id'];
$today = today();
$db = db();

if ($user['role'] === 'manager') {
    $weekStart = date('Y-m-d', strtotime('monday this week'));

    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(t.status <> 'completed'), 0) AS open_tasks,
                COALESCE(SUM(t.status <> 'completed' AND t.due_date < :today), 0) AS overdue,
                COALESCE(SUM(t.status <> 'completed' AND t.due_date = :today2), 0) AS due_today,
                COALESCE(SUM(t.status = 'pending'), 0) AS pending,
                COALESCE(SUM(t.status = 'completed' AND t.completed_at >= :week), 0) AS completed_week
         FROM tasks t JOIN users u ON u.id = t.assigned_to
         WHERE u.manager_id = :mid AND u.is_active = 1"
    );
    $stmt->execute([':today' => $today, ':today2' => $today, ':week' => $weekStart, ':mid' => $me]);
    $stats = $stmt->fetch();

    $stmt = $db->prepare(
        "SELECT u.id, u.name, u.designation,
                COALESCE(SUM(t.status = 'todo'), 0) AS todo,
                COALESCE(SUM(t.status = 'in_progress'), 0) AS in_progress,
                COALESCE(SUM(t.status = 'pending'), 0) AS pending,
                COALESCE(SUM(t.status <> 'completed' AND t.due_date < :today), 0) AS overdue,
                COALESCE(SUM(t.status = 'completed' AND t.completed_at >= :since), 0) AS completed_recent,
                EXISTS(SELECT 1 FROM daily_updates d WHERE d.user_id = u.id AND d.update_date = :today2) AS updated_today
         FROM users u LEFT JOIN tasks t ON t.assigned_to = u.id
         WHERE u.manager_id = :mid AND u.role = 'employee' AND u.is_active = 1
         GROUP BY u.id, u.name, u.designation
         ORDER BY u.name"
    );
    $stmt->execute([':today' => $today, ':since' => date('Y-m-d', strtotime('-30 days')), ':today2' => $today, ':mid' => $me]);
    $team = $stmt->fetchAll();
    $updatedToday = count(array_filter($team, fn($r) => $r['updated_today']));

    $stmt = $db->prepare(
        "SELECT t.id, t.title, t.status, t.priority, t.due_date, u.name AS assignee
         FROM tasks t JOIN users u ON u.id = t.assigned_to
         WHERE u.manager_id = ? AND t.status <> 'completed' AND t.due_date < ?
         ORDER BY t.due_date LIMIT 8"
    );
    $stmt->execute([$me, $today]);
    $overdueTasks = $stmt->fetchAll();

    $stmt = $db->prepare(
        'SELECT d.id, d.update_date, d.summary, d.hours_worked, u.name
         FROM daily_updates d JOIN users u ON u.id = d.user_id
         WHERE u.manager_id = ?
         ORDER BY d.update_date DESC, d.created_at DESC LIMIT 6'
    );
    $stmt->execute([$me]);
    $recentUpdates = $stmt->fetchAll();

    render_header('Manager Dashboard');
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0">Team Dashboard</h1>
            <span class="text-muted"><?= date('l, d F Y') ?></span>
        </div>
        <a href="<?= url('task_form') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Assign Task</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg"><?= stat_card('Open tasks', $stats['open_tasks'], 'primary', 'list-task', url('tasks', ['status' => 'open'])) ?></div>
        <div class="col-6 col-lg"><?= stat_card('Overdue', $stats['overdue'], 'danger', 'exclamation-triangle', url('tasks', ['status' => 'overdue'])) ?></div>
        <div class="col-6 col-lg"><?= stat_card('Due today', $stats['due_today'], 'warning', 'calendar-event', url('tasks', ['status' => 'due_today'])) ?></div>
        <div class="col-6 col-lg"><?= stat_card('Done this week', $stats['completed_week'], 'success', 'check2-circle', url('tasks', ['status' => 'completed'])) ?></div>
        <div class="col-12 col-lg"><?= stat_card('Updates today', $updatedToday . ' / ' . count($team), 'info', 'journal-check', url('updates', ['date' => $today])) ?></div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white fw-semibold">Team overview</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Employee</th><th class="text-center">To Do</th><th class="text-center">In Progress</th>
                    <th class="text-center">Pending</th><th class="text-center">Overdue</th>
                    <th class="text-center">Completed (30d)</th><th class="text-center">Today's update</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($team as $row): ?>
                    <tr>
                        <td><a href="<?= url('tasks', ['employee' => $row['id']]) ?>"><?= e($row['name']) ?></a>
                            <div class="small text-muted"><?= e($row['designation']) ?></div></td>
                        <td class="text-center"><?= (int) $row['todo'] ?></td>
                        <td class="text-center"><?= (int) $row['in_progress'] ?></td>
                        <td class="text-center"><?= (int) $row['pending'] ?></td>
                        <td class="text-center <?= $row['overdue'] ? 'text-danger fw-bold' : '' ?>"><?= (int) $row['overdue'] ?></td>
                        <td class="text-center"><?= (int) $row['completed_recent'] ?></td>
                        <td class="text-center">
                            <?= $row['updated_today']
                                ? '<i class="bi bi-check-circle-fill text-success" title="Submitted"></i>'
                                : '<i class="bi bi-x-circle text-danger" title="Not submitted"></i>' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$team): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No employees on your team yet. <a href="<?= url('employee_form') ?>">Add one</a>.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold text-danger"><i class="bi bi-exclamation-triangle"></i> Overdue tasks</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($overdueTasks as $t): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <a href="<?= url('task_view', ['id' => $t['id']]) ?>"><?= e($t['title']) ?></a>
                                <div class="small text-muted"><?= e($t['assignee']) ?> · due <?= fmt_date($t['due_date']) ?></div>
                            </div>
                            <?= priority_badge($t['priority']) ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$overdueTasks): ?>
                        <li class="list-group-item text-muted">No overdue tasks. 🎉</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-journal-text"></i> Latest daily updates</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentUpdates as $u): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <a href="<?= url('update_view', ['id' => $u['id']]) ?>"><?= e($u['name']) ?></a>
                                <small class="text-muted"><?= fmt_date($u['update_date']) ?> · <?= e($u['hours_worked']) ?>h</small>
                            </div>
                            <div class="small text-truncate"><?= e($u['summary']) ?></div>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$recentUpdates): ?>
                        <li class="list-group-item text-muted">No updates submitted yet.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php
    render_footer();
    return;
}

/* ---------- Employee dashboard ---------- */

$stmt = $db->prepare(
    "SELECT COALESCE(SUM(status = 'todo'), 0) AS todo,
            COALESCE(SUM(status = 'in_progress'), 0) AS in_progress,
            COALESCE(SUM(status = 'pending'), 0) AS pending,
            COALESCE(SUM(status <> 'completed' AND due_date < :today), 0) AS overdue,
            COALESCE(SUM(status = 'completed' AND completed_at >= :month), 0) AS completed_month
     FROM tasks WHERE assigned_to = :me"
);
$stmt->execute([':today' => $today, ':month' => date('Y-m-01'), ':me' => $me]);
$stats = $stmt->fetch();

$stmt = $db->prepare('SELECT id FROM daily_updates WHERE user_id = ? AND update_date = ?');
$stmt->execute([$me, $today]);
$todayUpdateId = $stmt->fetchColumn();

$stmt = $db->prepare(
    "SELECT id, title, status, priority, due_date FROM tasks
     WHERE assigned_to = ? AND status <> 'completed'
     ORDER BY due_date < ? DESC, due_date, FIELD(priority, 'critical', 'high', 'medium', 'low')"
);
$stmt->execute([$me, $today]);
$openTasks = $stmt->fetchAll();

$stmt = $db->prepare('SELECT id, update_date, summary, hours_worked FROM daily_updates WHERE user_id = ? ORDER BY update_date DESC LIMIT 5');
$stmt->execute([$me]);
$recentUpdates = $stmt->fetchAll();

render_header('My Dashboard');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0">Hello, <?= e(strtok($user['name'], ' ')) ?></h1>
        <span class="text-muted"><?= date('l, d F Y') ?></span>
    </div>
    <a href="<?= url('update_form') ?>" class="btn btn-primary">
        <i class="bi bi-journal-plus"></i> <?= $todayUpdateId ? "Edit today's update" : "Submit today's update" ?>
    </a>
</div>

<?php if (!$todayUpdateId): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-bell"></i> You haven't submitted your daily work update for today yet.
        <a href="<?= url('update_form') ?>" class="alert-link ms-1">Submit now</a>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg"><?= stat_card('To do', $stats['todo'], 'secondary', 'circle', url('tasks', ['status' => 'todo'])) ?></div>
    <div class="col-6 col-lg"><?= stat_card('In progress', $stats['in_progress'], 'primary', 'arrow-repeat', url('tasks', ['status' => 'in_progress'])) ?></div>
    <div class="col-6 col-lg"><?= stat_card('Pending', $stats['pending'], 'warning', 'pause-circle', url('tasks', ['status' => 'pending'])) ?></div>
    <div class="col-6 col-lg"><?= stat_card('Overdue', $stats['overdue'], 'danger', 'exclamation-triangle', url('tasks', ['status' => 'overdue'])) ?></div>
    <div class="col-12 col-lg"><?= stat_card('Done this month', $stats['completed_month'], 'success', 'check2-circle', url('tasks', ['status' => 'completed'])) ?></div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white fw-semibold">My open tasks</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>Task</th><th>Priority</th><th>Due</th><th style="width: 190px">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($openTasks as $t): ?>
                        <tr class="<?= is_overdue($t) ? 'table-overdue' : '' ?>">
                            <td><a href="<?= url('task_view', ['id' => $t['id']]) ?>"><?= e($t['title']) ?></a>
                                <?= is_overdue($t) ? '<span class="badge bg-danger ms-1">Overdue</span>' : '' ?></td>
                            <td><?= priority_badge($t['priority']) ?></td>
                            <td><?= fmt_date($t['due_date']) ?></td>
                            <td>
                                <form method="post" action="<?= url('task_view', ['id' => $t['id']]) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="return" value="dashboard">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <?php foreach (TASK_STATUSES as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $t['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$openTasks): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No open tasks. Nice work!</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold">My recent updates</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($recentUpdates as $u): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <a href="<?= url('update_view', ['id' => $u['id']]) ?>"><?= fmt_date($u['update_date']) ?></a>
                            <small class="text-muted"><?= e($u['hours_worked']) ?>h</small>
                        </div>
                        <div class="small text-truncate"><?= e($u['summary']) ?></div>
                    </li>
                <?php endforeach; ?>
                <?php if (!$recentUpdates): ?>
                    <li class="list-group-item text-muted">No updates yet.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php render_footer(); ?>
