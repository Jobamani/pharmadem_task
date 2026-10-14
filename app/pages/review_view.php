<?php
$user = require_login();
$id = (int) input('id');

$stmt = db()->prepare(
    'SELECT r.*, e.name AS employee_name, e.designation, e.manager_id, rv.name AS reviewer_name
     FROM performance_reviews r
     JOIN users e ON e.id = r.employee_id
     JOIN users rv ON rv.id = r.reviewer_id
     WHERE r.id = ?'
);
$stmt->execute([$id]);
$r = $stmt->fetch();

$allowed = $r && ($user['role'] === 'manager'
    ? (int) $r['manager_id'] === (int) $user['id']
    : (int) $r['employee_id'] === (int) $user['id']);
if (!$allowed) {
    not_found('Review not found.');
}

$managerAvg = ($r['quality_rating'] + $r['teamwork_rating'] + $r['communication_rating']) / 3;
$stars = fn(int $n) => str_repeat('<i class="bi bi-star-fill text-warning"></i>', $n) . str_repeat('<i class="bi bi-star text-warning"></i>', 5 - $n);

render_header('Performance Review');
?>
<nav class="mb-2 d-print-none">
    <a href="<?= url('performance', $user['role'] === 'manager' ? ['employee' => $r['employee_id']] : []) ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Performance</a>
</nav>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0">Performance Review · <?= e($r['employee_name']) ?></h1>
        <span class="text-muted"><?= e($r['designation']) ?> · <?= fmt_date($r['period_start']) ?> – <?= fmt_date($r['period_end']) ?></span>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary d-print-none"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card text-center h-100">
            <div class="card-body d-flex flex-column justify-content-center">
                <div class="text-muted text-uppercase small">Final score</div>
                <div class="display-4 fw-bold"><?= number_format((float) $r['final_score'], 1) ?></div>
                <div class="fs-5"><?= grade_badge($r['grade']) ?></div>
                <div class="small text-muted mt-3">Reviewed by <?= e($r['reviewer_name']) ?><br><?= fmt_datetime($r['created_at']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span>System metrics (60%)</span><span><?= number_format((float) $r['system_score'], 1) ?></span>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><div class="d-flex justify-content-between small"><span>Completion rate</span><span class="text-muted"><?= $r['tasks_completed'] ?> of <?= $r['tasks_assigned'] ?> tasks</span></div><?= score_bar((float) $r['completion_rate']) ?></li>
                <li class="list-group-item"><div class="d-flex justify-content-between small"><span>On-time rate</span><span class="text-muted"><?= $r['completed_on_time'] ?> on time</span></div><?= score_bar((float) $r['on_time_rate']) ?></li>
                <li class="list-group-item"><div class="small">Daily update compliance</div><?= score_bar((float) $r['update_compliance']) ?></li>
                <li class="list-group-item d-flex justify-content-between small"><span>Overdue tasks open at review time</span><strong><?= $r['overdue_open'] ?></strong></li>
            </ul>
        </div>
        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span>Manager rating (40%)</span><span><?= number_format($managerAvg, 2) ?> / 5</span>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>Quality of work</span><span><?= $stars((int) $r['quality_rating']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Teamwork &amp; collaboration</span><span><?= $stars((int) $r['teamwork_rating']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Communication &amp; reporting</span><span><?= $stars((int) $r['communication_rating']) ?></span></li>
            </ul>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Comments</div>
            <div class="card-body pre-wrap"><?= $r['comments'] ? e($r['comments']) : '<span class="text-muted">No comments.</span>' ?></div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
