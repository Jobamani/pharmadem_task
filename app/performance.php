<?php
declare(strict_types=1);

/*
 * Performance evaluation
 * ----------------------
 * Tasks in scope for a period: tasks due in the period, plus tasks completed
 * in the period (so work finished ahead of a later due date still counts).
 *
 * System score (0-100):
 *   40% completion rate      = completed / assigned
 *   35% on-time rate         = completed on or before due date / completed
 *   25% update compliance    = daily updates submitted / working days elapsed
 * If no tasks were due in the period, the system score is the update compliance alone.
 *
 * Final score (0-100) = 60% system score + 40% manager rating
 * (average of quality, teamwork and communication, each 1-5, scaled to 100).
 */

const WEIGHT_COMPLETION = 0.40;
const WEIGHT_ON_TIME    = 0.35;
const WEIGHT_UPDATES    = 0.25;
const WEIGHT_SYSTEM     = 0.60;
const WEIGHT_MANAGER    = 0.40;

function performance_metrics(int $employeeId, string $from, string $to): array
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS assigned,
                COALESCE(SUM(status = 'completed'), 0) AS completed,
                COALESCE(SUM(status = 'completed' AND DATE(completed_at) <= due_date), 0) AS on_time,
                COALESCE(SUM(status <> 'completed' AND due_date < :today), 0) AS overdue_open,
                COALESCE(SUM(status <> 'completed' AND due_date >= :today2), 0) AS open_not_due
         FROM tasks
         WHERE assigned_to = :emp
           AND (due_date BETWEEN :from AND :to OR DATE(completed_at) BETWEEN :from2 AND :to2)"
    );
    $stmt->execute([
        ':today' => today(), ':today2' => today(), ':emp' => $employeeId,
        ':from' => $from, ':to' => $to, ':from2' => $from, ':to2' => $to,
    ]);
    $m = array_map('intval', $stmt->fetch());

    // Daily update compliance only counts working days that have already happened.
    $end = min($to, today());
    $expectedUpdates = $end >= $from ? working_days($from, $end) : 0;

    $stmt = db()->prepare('SELECT COUNT(*) FROM daily_updates WHERE user_id = ? AND update_date BETWEEN ? AND ?');
    $stmt->execute([$employeeId, $from, $end]);
    $submittedUpdates = (int) $stmt->fetchColumn();

    $m['late']              = $m['completed'] - $m['on_time'];
    $m['updates_submitted'] = $submittedUpdates;
    $m['updates_expected']  = $expectedUpdates;
    $m['completion_rate']   = $m['assigned'] > 0 ? $m['completed'] / $m['assigned'] * 100 : 0.0;
    $m['on_time_rate']      = $m['completed'] > 0 ? $m['on_time'] / $m['completed'] * 100 : 0.0;
    $m['update_compliance'] = $expectedUpdates > 0 ? min(100, $submittedUpdates / $expectedUpdates * 100) : 100.0;

    $m['system_score'] = $m['assigned'] > 0
        ? WEIGHT_COMPLETION * $m['completion_rate'] + WEIGHT_ON_TIME * $m['on_time_rate'] + WEIGHT_UPDATES * $m['update_compliance']
        : $m['update_compliance'];

    return $m;
}

function final_score(float $systemScore, int $quality, int $teamwork, int $communication): float
{
    $managerScore = ($quality + $teamwork + $communication) / 3 / 5 * 100;
    return round(WEIGHT_SYSTEM * $systemScore + WEIGHT_MANAGER * $managerScore, 2);
}

function grade_for(float $score): string
{
    return match (true) {
        $score >= 85 => 'Outstanding',
        $score >= 70 => 'Good',
        $score >= 50 => 'Satisfactory',
        default      => 'Needs Improvement',
    };
}

function grade_badge(string $grade): string
{
    $classes = ['Outstanding' => 'success', 'Good' => 'primary', 'Satisfactory' => 'warning text-dark', 'Needs Improvement' => 'danger'];
    return '<span class="badge bg-' . ($classes[$grade] ?? 'secondary') . '">' . e($grade) . '</span>';
}

/** Card showing how a system score was built up from its metrics. */
function metrics_panel(array $m): string
{
    $rows = [
        ['Completion rate', $m['completion_rate'], WEIGHT_COMPLETION, "{$m['completed']} of {$m['assigned']} tasks completed"],
        ['On-time rate', $m['on_time_rate'], WEIGHT_ON_TIME, "{$m['on_time']} on time, {$m['late']} late"],
        ['Daily update compliance', $m['update_compliance'], WEIGHT_UPDATES, "{$m['updates_submitted']} of {$m['updates_expected']} working days"],
    ];
    $html = '<div class="card"><div class="card-header bg-white fw-semibold d-flex justify-content-between">'
        . '<span>System metrics</span>' . grade_badge(grade_for($m['system_score'])) . '</div><ul class="list-group list-group-flush">';
    foreach ($rows as [$label, $value, $weight, $detail]) {
        $html .= '<li class="list-group-item"><div class="d-flex justify-content-between small"><span class="fw-semibold">' . e($label)
            . ' <span class="text-muted fw-normal">(' . ($weight * 100) . '%)</span></span><span class="text-muted">' . e($detail) . '</span></div>'
            . score_bar($value) . '</li>';
    }
    if ($m['assigned'] === 0) {
        $html .= '<li class="list-group-item small text-muted">No tasks in this period, so the score is based on update compliance only.</li>';
    }
    $html .= '<li class="list-group-item d-flex justify-content-between"><span>Open overdue tasks</span><strong class="'
        . ($m['overdue_open'] ? 'text-danger' : '') . '">' . $m['overdue_open'] . '</strong></li>'
        . '<li class="list-group-item bg-light"><div class="fw-semibold mb-1">System score</div>' . score_bar($m['system_score']) . '</li>'
        . '</ul></div>';
    return $html;
}

function score_bar(float $score): string
{
    $color = $score >= 85 ? 'success' : ($score >= 70 ? 'primary' : ($score >= 50 ? 'warning' : 'danger'));
    $pct = number_format(max(0, min(100, $score)), 0);
    return '<div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px;min-width:60px">'
        . '<div class="progress-bar bg-' . $color . '" style="width:' . $pct . '%"></div></div>'
        . '<small class="fw-semibold">' . number_format($score, 1) . '</small></div>';
}
