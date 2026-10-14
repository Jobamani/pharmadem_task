<?php
$user = require_login();
$me = (int) $user['id'];
$manager = $user['role'] === 'manager';
$today = today();

$from = input('from', date('Y-m-d', strtotime('-13 days')));
$to   = input('to', $today);
$day  = input('date', $today); // date for the manager's submission status card
$employee = (int) input('employee');
$from = valid_date($from) ? $from : $today;
$to   = valid_date($to) ? $to : $today;
$day  = valid_date($day) ? $day : $today;
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$params = [$from, $to];
if ($manager) {
    $scope = 'u.manager_id = ?';
    $params[] = $me;
    if ($employee) {
        $scope .= ' AND u.id = ?';
        $params[] = $employee;
    }
} else {
    $scope = 'u.id = ?';
    $params[] = $me;
}

$stmt = db()->prepare(
    "SELECT d.id, d.update_date, d.summary, d.blockers, d.hours_worked, d.created_at, u.name,
            (SELECT COUNT(*) FROM daily_update_tasks i WHERE i.daily_update_id = d.id) AS task_count
     FROM daily_updates d JOIN users u ON u.id = d.user_id
     WHERE d.update_date BETWEEN ? AND ? AND $scope
     ORDER BY d.update_date DESC, u.name"
);
$stmt->execute($params);
$updates = $stmt->fetchAll();

$team = [];
$submission = [];
if ($manager) {
    $team = team_members($me);
    $stmt = db()->prepare(
        "SELECT u.id, u.name, d.id AS update_id, d.hours_worked, d.created_at
         FROM users u LEFT JOIN daily_updates d ON d.user_id = u.id AND d.update_date = ?
         WHERE u.manager_id = ? AND u.role = 'employee' AND u.is_active = 1
         ORDER BY d.id IS NULL DESC, u.name"
    );
    $stmt->execute([$day, $me]);
    $submission = $stmt->fetchAll();
} else {
    $stmt = db()->prepare('SELECT COUNT(*) FROM daily_updates WHERE user_id = ? AND update_date = ?');
    $stmt->execute([$me, $today]);
    $submittedToday = (bool) $stmt->fetchColumn();
}

render_header('Daily Updates');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= $manager ? 'Team Daily Updates' : 'My Daily Updates' ?></h1>
    <?php if (!$manager): ?>
        <a href="<?= url('update_form') ?>" class="btn btn-primary"><i class="bi bi-journal-plus"></i> <?= $submittedToday ? "Edit today's update" : "Submit today's update" ?></a>
    <?php endif; ?>
</div>

<?php if ($manager): ?>
    <?php $done = count(array_filter($submission, fn($s) => $s['update_id'])); ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold">Submission status · <?= fmt_date($day) ?> <span class="badge bg-secondary"><?= $done ?> / <?= count($submission) ?></span></span>
            <form class="d-flex gap-2">
                <input type="hidden" name="page" value="updates">
                <input type="date" name="date" value="<?= e($day) ?>" max="<?= $today ?>" class="form-control form-control-sm">
                <button class="btn btn-sm btn-outline-dark">Check</button>
            </form>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($submission as $s): ?>
                    <?php if ($s['update_id']): ?>
                        <a href="<?= url('update_view', ['id' => $s['update_id']]) ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-check-circle-fill"></i> <?= e($s['name']) ?> · <?= e($s['hours_worked']) ?>h
                        </a>
                    <?php else: ?>
                        <span class="btn btn-sm btn-outline-danger disabled"><i class="bi bi-x-circle"></i> <?= e($s['name']) ?> · not submitted</span>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$submission): ?><span class="text-muted">No active employees on your team.</span><?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<form class="card card-body mb-3">
    <input type="hidden" name="page" value="updates">
    <input type="hidden" name="date" value="<?= e($day) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">From</label>
            <input type="date" name="from" value="<?= e($from) ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">To</label>
            <input type="date" name="to" value="<?= e($to) ?>" class="form-control">
        </div>
        <?php if ($manager): ?>
            <div class="col-md-3">
                <label class="form-label small mb-1">Employee</label>
                <select name="employee" class="form-select">
                    <option value="">Everyone</option>
                    <?php foreach ($team as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $employee === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-3">
            <button class="btn btn-dark w-100"><i class="bi bi-funnel"></i> Filter</button>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th>Date</th><?php if ($manager): ?><th>Employee</th><?php endif; ?>
                <th>Summary</th><th class="text-center">Tasks</th><th class="text-center">Hours</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($updates as $u): ?>
                <tr>
                    <td class="text-nowrap"><?= fmt_date($u['update_date']) ?></td>
                    <?php if ($manager): ?><td class="text-nowrap"><?= e($u['name']) ?></td><?php endif; ?>
                    <td>
                        <div class="text-truncate" style="max-width: 480px"><?= e($u['summary']) ?></div>
                        <?php if ($u['blockers']): ?><span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle"></i> Has blockers</span><?php endif; ?>
                    </td>
                    <td class="text-center"><?= (int) $u['task_count'] ?></td>
                    <td class="text-center"><?= e($u['hours_worked']) ?></td>
                    <td class="text-end"><a href="<?= url('update_view', ['id' => $u['id']]) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$updates): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No updates in this period.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_footer(); ?>
