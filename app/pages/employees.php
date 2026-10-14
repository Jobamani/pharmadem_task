<?php
$user = require_manager();
$me = (int) $user['id'];

if (is_post()) {
    verify_csrf();
    $id = (int) input('id');
    if (input('action') === 'toggle') {
        $stmt = db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ? AND manager_id = ? AND role = 'employee'");
        $stmt->execute([$id, $me]);
        flash('success', 'Employee status updated.');
    }
    redirect('employees');
}

$stmt = db()->prepare(
    "SELECT u.id, u.name, u.email, u.designation, u.is_active, u.created_at,
            COUNT(t.id) AS total_tasks,
            COALESCE(SUM(t.status <> 'completed'), 0) AS open_tasks,
            (SELECT MAX(update_date) FROM daily_updates d WHERE d.user_id = u.id) AS last_update
     FROM users u LEFT JOIN tasks t ON t.assigned_to = u.id
     WHERE u.manager_id = ? AND u.role = 'employee'
     GROUP BY u.id, u.name, u.email, u.designation, u.is_active, u.created_at
     ORDER BY u.is_active DESC, u.name"
);
$stmt->execute([$me]);
$employees = $stmt->fetchAll();

render_header('Team');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">My Team</h1>
    <a href="<?= url('employee_form') ?>" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add employee</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr><th>Name</th><th>Email</th><th class="text-center">Open / total tasks</th><th>Last update</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $emp): ?>
                <tr class="<?= $emp['is_active'] ? '' : 'text-muted' ?>">
                    <td><strong><?= e($emp['name']) ?></strong><div class="small text-muted"><?= e($emp['designation']) ?></div></td>
                    <td><?= e($emp['email']) ?></td>
                    <td class="text-center"><a href="<?= url('tasks', ['employee' => $emp['id'], 'status' => 'all']) ?>"><?= (int) $emp['open_tasks'] ?> / <?= (int) $emp['total_tasks'] ?></a></td>
                    <td><?= fmt_date($emp['last_update']) ?></td>
                    <td><?= $emp['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= url('task_form', ['employee' => $emp['id']]) ?>" class="btn btn-sm btn-outline-primary <?= $emp['is_active'] ? '' : 'disabled' ?>" title="Assign task"><i class="bi bi-plus-lg"></i></a>
                        <a href="<?= url('employee_form', ['id' => $emp['id']]) ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline" onsubmit="return confirm('<?= $emp['is_active'] ? 'Deactivate' : 'Reactivate' ?> this employee?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                            <button class="btn btn-sm <?= $emp['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="<?= $emp['is_active'] ? 'Deactivate' : 'Reactivate' ?>">
                                <i class="bi bi-<?= $emp['is_active'] ? 'person-x' : 'person-check' ?>"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$employees): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No employees yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_footer(); ?>
