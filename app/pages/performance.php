<?php
$user = require_login();
$me = (int) $user['id'];
$manager = $user['role'] === 'manager';
[$from, $to] = period_input();

$team = $manager ? team_members($me, false) : [];
$employeeId = $manager ? (int) input('employee') : $me;
if ($manager && $employeeId && !in_array($employeeId, array_map(fn($m) => (int) $m['id'], $team), true)) {
    not_found('Employee not found on your team.');
}

/* ---------- Manager: team overview ---------- */
if ($manager && !$employeeId) {
    $stmt = db()->prepare(
        'SELECT r.employee_id, r.final_score, r.grade, r.period_start, r.period_end, r.id
         FROM performance_reviews r
         JOIN (SELECT employee_id, MAX(id) AS id FROM performance_reviews GROUP BY employee_id) latest ON latest.id = r.id
         JOIN users u ON u.id = r.employee_id
         WHERE u.manager_id = ?'
    );
    $stmt->execute([$me]);
    $latest = [];
    foreach ($stmt->fetchAll() as $r) {
        $latest[(int) $r['employee_id']] = $r;
    }

    render_header('Performance');
    ?>
    <h1 class="h3 mb-1">Team Performance</h1>
    <p class="text-muted">Live system scores for <?= fmt_date($from) ?> – <?= fmt_date($to) ?>, and each employee's latest formal review.</p>

    <div class="d-flex flex-wrap gap-1 mb-3">
        <?php foreach (period_presets() as $label => [$pf, $pt]): ?>
            <a href="<?= url('performance', ['from' => $pf, 'to' => $pt]) ?>" class="btn btn-sm <?= $pf === $from && $pt === $to ? 'btn-primary' : 'btn-outline-primary' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                <tr><th>Employee</th><th style="min-width: 160px">System score (period)</th><th>Grade</th><th>Latest review</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($team as $member): if (!$member['is_active']) continue;
                    $m = performance_metrics((int) $member['id'], $from, $to);
                    $review = $latest[(int) $member['id']] ?? null; ?>
                    <tr>
                        <td><a href="<?= url('performance', ['employee' => $member['id'], 'from' => $from, 'to' => $to]) ?>" class="fw-semibold"><?= e($member['name']) ?></a>
                            <div class="small text-muted"><?= e($member['designation']) ?></div></td>
                        <td><?= score_bar($m['system_score']) ?></td>
                        <td><?= grade_badge(grade_for($m['system_score'])) ?></td>
                        <td>
                            <?php if ($review): ?>
                                <a href="<?= url('review_view', ['id' => $review['id']]) ?>"><?= number_format((float) $review['final_score'], 1) ?></a>
                                <?= grade_badge($review['grade']) ?>
                                <div class="small text-muted"><?= fmt_date($review['period_start']) ?> – <?= fmt_date($review['period_end']) ?></div>
                            <?php else: ?>
                                <span class="text-muted small">Not reviewed yet</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= url('review_form', ['employee' => $member['id'], 'from' => $from, 'to' => $to]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-star"></i> Evaluate</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$team): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No employees on your team.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    render_footer();
    return;
}

/* ---------- Single employee (manager drill-down, or employee's own view) ---------- */
$stmt = db()->prepare('SELECT id, name, designation FROM users WHERE id = ?');
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();
$m = performance_metrics($employeeId, $from, $to);

$stmt = db()->prepare(
    'SELECT r.*, u.name AS reviewer FROM performance_reviews r JOIN users u ON u.id = r.reviewer_id
     WHERE r.employee_id = ? ORDER BY r.period_end DESC, r.id DESC'
);
$stmt->execute([$employeeId]);
$reviews = $stmt->fetchAll();

$linkParams = $manager ? ['employee' => $employeeId] : [];

render_header('Performance · ' . $employee['name']);
?>
<?php if ($manager): ?>
    <nav class="mb-2"><a href="<?= url('performance', ['from' => $from, 'to' => $to]) ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Team performance</a></nav>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0"><?= $manager ? e($employee['name']) : 'My Performance' ?></h1>
        <span class="text-muted"><?= e($employee['designation']) ?> · <?= fmt_date($from) ?> – <?= fmt_date($to) ?></span>
    </div>
    <?php if ($manager): ?>
        <a href="<?= url('review_form', ['employee' => $employeeId, 'from' => $from, 'to' => $to]) ?>" class="btn btn-primary"><i class="bi bi-star"></i> Evaluate for this period</a>
    <?php endif; ?>
</div>

<form class="card card-body mb-4">
    <input type="hidden" name="page" value="performance">
    <?php if ($manager): ?><input type="hidden" name="employee" value="<?= $employeeId ?>"><?php endif; ?>
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1">From</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label small mb-1">To</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-dark w-100">Apply</button></div>
        <div class="col-md-4 d-flex flex-wrap gap-1">
            <?php foreach (period_presets() as $label => [$pf, $pt]): ?>
                <a href="<?= url('performance', $linkParams + ['from' => $pf, 'to' => $pt]) ?>" class="btn btn-sm <?= $pf === $from && $pt === $to ? 'btn-primary' : 'btn-outline-primary' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</form>

<div class="row g-4">
    <div class="col-lg-5"><?= metrics_panel($m) ?></div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Performance reviews</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Period</th><th>Final score</th><th>Grade</th><th>Reviewer</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td class="small"><?= fmt_date($r['period_start']) ?> – <?= fmt_date($r['period_end']) ?></td>
                            <td style="min-width: 120px"><?= score_bar((float) $r['final_score']) ?></td>
                            <td><?= grade_badge($r['grade']) ?></td>
                            <td class="small"><?= e($r['reviewer']) ?></td>
                            <td class="text-end"><a href="<?= url('review_view', ['id' => $r['id']]) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$reviews): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No formal reviews yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
