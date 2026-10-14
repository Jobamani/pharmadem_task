<?php
$user = require_login();
$manager = $user['role'] === 'manager';
$today = today();

$status   = input('status', 'open');
$priority = input('priority');
$employee = (int) input('employee');
$q        = input('q');
$pageNo   = max(1, (int) input('p', '1'));
$perPage  = 20;

$where  = [];
$params = [];

if ($manager) {
    $where[]  = 'a.manager_id = ?';
    $params[] = $user['id'];
    if ($employee) {
        $where[]  = 't.assigned_to = ?';
        $params[] = $employee;
    }
} else {
    $where[]  = 't.assigned_to = ?';
    $params[] = $user['id'];
}

switch ($status) {
    case 'open':
        $where[] = "t.status <> 'completed'";
        break;
    case 'overdue':
        $where[]  = "t.status <> 'completed' AND t.due_date < ?";
        $params[] = $today;
        break;
    case 'due_today':
        $where[]  = "t.status <> 'completed' AND t.due_date = ?";
        $params[] = $today;
        break;
    case 'all':
        break;
    default:
        if (isset(TASK_STATUSES[$status])) {
            $where[]  = 't.status = ?';
            $params[] = $status;
        } else {
            $status = 'all';
        }
}

if (isset(TASK_PRIORITIES[$priority])) {
    $where[]  = 't.priority = ?';
    $params[] = $priority;
}
if ($q !== '') {
    $where[]  = '(t.title LIKE ? OR t.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$whereSql = implode(' AND ', $where);

$stmt = db()->prepare("SELECT COUNT(*) FROM tasks t JOIN users a ON a.id = t.assigned_to WHERE $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$pageNo = min($pageNo, $pages);
$offset = ($pageNo - 1) * $perPage;

$stmt = db()->prepare(
    "SELECT t.id, t.title, t.status, t.priority, t.start_date, t.due_date, t.completed_at, a.name AS assignee
     FROM tasks t JOIN users a ON a.id = t.assigned_to
     WHERE $whereSql
     ORDER BY t.status = 'completed', t.due_date, FIELD(t.priority, 'critical', 'high', 'medium', 'low')
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$team = $manager ? team_members((int) $user['id'], false) : [];
$filters = ['status' => $status, 'priority' => $priority, 'employee' => $employee ?: '', 'q' => $q];
$statusOptions = ['open' => 'Open (not completed)', 'overdue' => 'Overdue', 'due_today' => 'Due today'] + TASK_STATUSES + ['all' => 'All tasks'];

render_header('Tasks');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= $manager ? 'Team Tasks' : 'My Tasks' ?></h1>
    <?php if ($manager): ?>
        <a href="<?= url('task_form') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Assign Task</a>
    <?php endif; ?>
</div>

<form class="card card-body mb-3">
    <input type="hidden" name="page" value="tasks">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">Search</label>
            <input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Title or description">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select">
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Priority</label>
            <select name="priority" class="form-select">
                <option value="">Any</option>
                <?php foreach (TASK_PRIORITIES as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $priority === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($manager): ?>
            <div class="col-md-2">
                <label class="form-label small mb-1">Employee</label>
                <select name="employee" class="form-select">
                    <option value="">Everyone</option>
                    <?php foreach ($team as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $employee === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-dark flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
            <a href="<?= url('tasks') ?>" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th>Task</th>
                <?php if ($manager): ?><th>Assigned to</th><?php endif; ?>
                <th>Priority</th><th>Status</th><th>Due date</th><th>Completed</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $t): ?>
                <tr class="<?= is_overdue($t) ? 'table-overdue' : '' ?>">
                    <td><a href="<?= url('task_view', ['id' => $t['id']]) ?>" class="fw-semibold"><?= e($t['title']) ?></a></td>
                    <?php if ($manager): ?><td><?= e($t['assignee']) ?></td><?php endif; ?>
                    <td><?= priority_badge($t['priority']) ?></td>
                    <td><?= status_badge($t['status'], $t['due_date']) ?></td>
                    <td><?= fmt_date($t['due_date']) ?></td>
                    <td><?= $t['completed_at'] ? fmt_date($t['completed_at']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tasks): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No tasks match these filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= $total ?> task<?= $total === 1 ? '' : 's' ?></small>
        <?php if ($pages > 1): ?>
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>">
                        <a class="page-link" href="<?= url('tasks', $filters + ['p' => $i]) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
