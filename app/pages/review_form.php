<?php
$user = require_manager();
$me = (int) $user['id'];
[$from, $to] = period_input();

$team = team_members($me);
$employeeId = (int) input('employee');
$employee = null;
foreach ($team as $member) {
    if ((int) $member['id'] === $employeeId) {
        $employee = $member;
    }
}

$ratings = ['quality' => 'Quality of work', 'teamwork' => 'Teamwork & collaboration', 'communication' => 'Communication & reporting'];
$form = ['quality' => 3, 'teamwork' => 3, 'communication' => 3, 'comments' => ''];
$errors = [];
$m = $employee ? performance_metrics($employeeId, $from, $to) : null;

if (is_post() && $employee) {
    verify_csrf();
    foreach ($ratings as $key => $_) {
        $form[$key] = (int) input($key);
        if ($form[$key] < 1 || $form[$key] > 5) {
            $errors[$key] = 'Rate from 1 to 5.';
        }
    }
    $form['comments'] = input('comments');
    if ($to > today()) {
        $errors['period'] = 'The review period cannot end in the future.';
    }

    if (!$errors) {
        $final = final_score($m['system_score'], $form['quality'], $form['teamwork'], $form['communication']);
        $stmt = db()->prepare(
            'INSERT INTO performance_reviews
             (employee_id, reviewer_id, period_start, period_end, tasks_assigned, tasks_completed, completed_on_time, overdue_open,
              completion_rate, on_time_rate, update_compliance, system_score, quality_rating, teamwork_rating, communication_rating,
              final_score, grade, comments)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $employeeId, $me, $from, $to, $m['assigned'], $m['completed'], $m['on_time'], $m['overdue_open'],
            round($m['completion_rate'], 2), round($m['on_time_rate'], 2), round($m['update_compliance'], 2), round($m['system_score'], 2),
            $form['quality'], $form['teamwork'], $form['communication'], $final, grade_for($final), $form['comments'] ?: null,
        ]);
        flash('success', 'Performance review saved.');
        redirect('review_view', ['id' => db()->lastInsertId()]);
    }
}

render_header('Evaluate Performance');
?>
<nav class="mb-2"><a href="<?= url('performance', ['from' => $from, 'to' => $to]) ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Team performance</a></nav>
<h1 class="h3 mb-3">Performance Evaluation</h1>

<form class="card card-body mb-4">
    <input type="hidden" name="page" value="review_form">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-1">Employee</label>
            <select name="employee" class="form-select" required>
                <option value="">Select employee…</option>
                <?php foreach ($team as $member): ?>
                    <option value="<?= $member['id'] ?>" <?= $employeeId === (int) $member['id'] ? 'selected' : '' ?>><?= e($member['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label small mb-1">Period from</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label small mb-1">Period to</label><input type="date" name="to" value="<?= e($to) ?>" max="<?= today() ?>" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-dark w-100">Load metrics</button></div>
    </div>
</form>

<?php if ($employee): ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <?= metrics_panel($m) ?>
        </div>
        <div class="col-lg-7">
            <form method="post" class="card">
                <?= csrf_field() ?>
                <div class="card-header bg-white fw-semibold">Manager assessment · <?= e($employee['name']) ?></div>
                <div class="card-body">
                    <?php if (isset($errors['period'])): ?><div class="alert alert-danger py-2"><?= e($errors['period']) ?></div><?php endif; ?>
                    <?php foreach ($ratings as $key => $label): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block"><?= e($label) ?></label>
                            <div class="btn-group" role="group">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" class="btn-check rating" name="<?= $key ?>" id="<?= $key . $i ?>" value="<?= $i ?>" <?= $form[$key] === $i ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-warning" for="<?= $key . $i ?>"><?= $i ?> <i class="bi bi-star-fill"></i></label>
                                <?php endfor; ?>
                            </div>
                            <?= field_error($errors, $key) ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="small text-muted mb-3">1 = Poor · 2 = Below expectations · 3 = Meets expectations · 4 = Exceeds · 5 = Exceptional</div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Comments & feedback</label>
                        <textarea name="comments" rows="4" class="form-control" placeholder="Strengths, areas to improve, goals for next period"><?= e($form['comments']) ?></textarea>
                    </div>
                    <div class="alert alert-light border d-flex justify-content-between align-items-center mb-0">
                        <span>Final score = 60% system score + 40% manager rating</span>
                        <strong class="fs-5" id="final-preview">—</strong>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <button class="btn btn-primary"><i class="bi bi-save"></i> Save review</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    (function () {
        var system = <?= json_encode(round($m['system_score'], 2)) ?>;
        function update() {
            var sum = 0;
            ['quality', 'teamwork', 'communication'].forEach(function (k) {
                var el = document.querySelector('input[name="' + k + '"]:checked');
                sum += el ? parseInt(el.value, 10) : 0;
            });
            var score = <?= WEIGHT_SYSTEM ?> * system + <?= WEIGHT_MANAGER ?> * (sum / 3 / 5 * 100);
            document.getElementById('final-preview').textContent = score.toFixed(1) + ' / 100';
        }
        document.querySelectorAll('.rating').forEach(function (el) { el.addEventListener('change', update); });
        update();
    })();
    </script>
<?php elseif ($employeeId): ?>
    <div class="alert alert-warning">That employee is not an active member of your team.</div>
<?php else: ?>
    <div class="alert alert-info">Select an employee and a review period to load their metrics.</div>
<?php endif; ?>
<?php render_footer(); ?>
