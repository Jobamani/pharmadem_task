<?php
$user = require_manager();
$me = (int) $user['id'];
$today = today();
[$from, $to] = period_input();

$team = team_members($me);
$rows = [];
foreach ($team as $member) {
    $rows[] = ['employee' => $member] + performance_metrics((int) $member['id'], $from, $to);
}
usort($rows, fn($a, $b) => $b['system_score'] <=> $a['system_score']);

if (input('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="team-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Employee', 'Designation', 'Tasks', 'Completed', 'On time', 'Late', 'Overdue (open)',
        'Completion %', 'On-time %', 'Updates submitted', 'Working days', 'Update compliance %', 'System score', 'Grade']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['employee']['name'], $r['employee']['designation'], $r['assigned'], $r['completed'], $r['on_time'], $r['late'],
            $r['overdue_open'], round($r['completion_rate'], 1), round($r['on_time_rate'], 1), $r['updates_submitted'],
            $r['updates_expected'], round($r['update_compliance'], 1), round($r['system_score'], 1), grade_for($r['system_score']),
        ]);
    }
    exit;
}

$totals = ['assigned' => 0, 'completed' => 0, 'on_time' => 0, 'overdue_open' => 0, 'updates_submitted' => 0, 'updates_expected' => 0];
foreach ($rows as $r) {
    foreach ($totals as $key => $_) {
        $totals[$key] += $r[$key];
    }
}
$teamCompletion = $totals['assigned'] ? $totals['completed'] / $totals['assigned'] * 100 : 0;
$teamOnTime     = $totals['completed'] ? $totals['on_time'] / $totals['completed'] * 100 : 0;
$teamCompliance = $totals['updates_expected'] ? min(100, $totals['updates_submitted'] / $totals['updates_expected'] * 100) : 100;

// Status breakdown of tasks in the period (same scope as the metrics).
$stmt = db()->prepare(
    "SELECT t.status, COUNT(*) AS n
     FROM tasks t JOIN users u ON u.id = t.assigned_to
     WHERE u.manager_id = ? AND u.is_active = 1
       AND (t.due_date BETWEEN ? AND ? OR DATE(t.completed_at) BETWEEN ? AND ?)
     GROUP BY t.status"
);
$stmt->execute([$me, $from, $to, $from, $to]);
$breakdown = array_fill_keys(array_keys(TASK_STATUSES), 0);
foreach ($stmt->fetchAll() as $row) {
    $breakdown[$row['status']] = (int) $row['n'];
}
$breakdownTotal = array_sum($breakdown);

// All currently overdue tasks for the team, regardless of period.
$stmt = db()->prepare(
    "SELECT t.id, t.title, t.status, t.priority, t.due_date, u.name AS assignee, DATEDIFF(?, t.due_date) AS days_overdue
     FROM tasks t JOIN users u ON u.id = t.assigned_to
     WHERE u.manager_id = ? AND t.status <> 'completed' AND t.due_date < ?
     ORDER BY t.due_date"
);
$stmt->execute([$today, $me, $today]);
$overdue = $stmt->fetchAll();

render_header('Reports');
$colors = ['todo' => 'secondary', 'in_progress' => 'primary', 'pending' => 'warning', 'completed' => 'success'];
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0">Team Report</h1>
        <span class="text-muted"><?= fmt_date($from) ?> – <?= fmt_date($to) ?></span>
    </div>
    <div class="d-flex gap-2 d-print-none">
        <a href="<?= url('reports', ['from' => $from, 'to' => $to, 'export' => 'csv']) ?>" class="btn btn-outline-success"><i class="bi bi-filetype-csv"></i> Export CSV</a>
        <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

<form class="card card-body mb-4 d-print-none">
    <input type="hidden" name="page" value="reports">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1">From</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label small mb-1">To</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-dark w-100">Apply</button></div>
        <div class="col-md-4 d-flex flex-wrap gap-1">
            <?php foreach (period_presets() as $label => [$pf, $pt]): ?>
                <a href="<?= url('reports', ['from' => $pf, 'to' => $pt]) ?>" class="btn btn-sm <?= $pf === $from && $pt === $to ? 'btn-primary' : 'btn-outline-primary' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg"><?= stat_card('Tasks in period', $totals['assigned'], 'primary', 'list-task') ?></div>
    <div class="col-6 col-lg"><?= stat_card('Completed', $totals['completed'], 'success', 'check2-circle') ?></div>
    <div class="col-6 col-lg"><?= stat_card('Completion rate', number_format($teamCompletion, 0) . '%', 'info', 'pie-chart') ?></div>
    <div class="col-6 col-lg"><?= stat_card('On-time rate', number_format($teamOnTime, 0) . '%', 'success', 'alarm') ?></div>
    <div class="col-6 col-lg"><?= stat_card('Overdue (open)', $totals['overdue_open'], 'danger', 'exclamation-triangle') ?></div>
    <div class="col-6 col-lg"><?= stat_card('Update compliance', number_format($teamCompliance, 0) . '%', 'warning', 'journal-check') ?></div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">Task status breakdown</div>
    <div class="card-body">
        <?php if ($breakdownTotal): ?>
            <div class="progress mb-2" style="height: 24px">
                <?php foreach ($breakdown as $status => $n): if (!$n) continue; ?>
                    <div class="progress-bar bg-<?= $colors[$status] ?>" style="width: <?= $n / $breakdownTotal * 100 ?>%" title="<?= TASK_STATUSES[$status] ?>"><?= $n ?></div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex flex-wrap gap-3 small">
                <?php foreach ($breakdown as $status => $n): ?>
                    <span><i class="bi bi-square-fill text-<?= $colors[$status] ?>"></i> <?= TASK_STATUSES[$status] ?>: <strong><?= $n ?></strong></span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <span class="text-muted">No tasks in this period.</span>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">Employee performance</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 small">
            <thead class="table-light">
            <tr>
                <th>Employee</th><th class="text-center">Tasks</th><th class="text-center">Completed</th>
                <th class="text-center">On time</th><th class="text-center">Late</th><th class="text-center">Overdue</th>
                <th class="text-center">Completion</th><th class="text-center">On-time</th><th class="text-center">Updates</th>
                <th style="min-width: 150px">System score</th><th>Grade</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= url('performance', ['employee' => $r['employee']['id'], 'from' => $from, 'to' => $to]) ?>"><?= e($r['employee']['name']) ?></a>
                        <div class="text-muted"><?= e($r['employee']['designation']) ?></div></td>
                    <td class="text-center"><?= $r['assigned'] ?></td>
                    <td class="text-center"><?= $r['completed'] ?></td>
                    <td class="text-center text-success"><?= $r['on_time'] ?></td>
                    <td class="text-center <?= $r['late'] ? 'text-warning' : '' ?>"><?= $r['late'] ?></td>
                    <td class="text-center <?= $r['overdue_open'] ? 'text-danger fw-bold' : '' ?>"><?= $r['overdue_open'] ?></td>
                    <td class="text-center"><?= number_format($r['completion_rate'], 0) ?>%</td>
                    <td class="text-center"><?= number_format($r['on_time_rate'], 0) ?>%</td>
                    <td class="text-center"><?= $r['updates_submitted'] ?>/<?= $r['updates_expected'] ?></td>
                    <td><?= score_bar($r['system_score']) ?></td>
                    <td><?= grade_badge(grade_for($r['system_score'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="11" class="text-center text-muted py-4">No employees on your team.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-muted">
        System score = 40% completion rate + 35% on-time rate + 25% daily-update compliance (working days, Mon–Fri).
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold text-danger"><i class="bi bi-exclamation-triangle"></i> Currently overdue tasks (<?= count($overdue) ?>)</div>
    <div class="table-responsive">
        <table class="table mb-0 small">
            <thead class="table-light"><tr><th>Task</th><th>Assigned to</th><th>Priority</th><th>Status</th><th>Due date</th><th class="text-center">Days overdue</th></tr></thead>
            <tbody>
            <?php foreach ($overdue as $t): ?>
                <tr>
                    <td><a href="<?= url('task_view', ['id' => $t['id']]) ?>"><?= e($t['title']) ?></a></td>
                    <td><?= e($t['assignee']) ?></td>
                    <td><?= priority_badge($t['priority']) ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                    <td><?= fmt_date($t['due_date']) ?></td>
                    <td class="text-center text-danger fw-bold"><?= (int) $t['days_overdue'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$overdue): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No overdue tasks.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_footer(); ?>
