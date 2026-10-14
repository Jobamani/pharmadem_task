<?php
$user = require_manager();
$me = (int) $user['id'];
$team = team_members($me);
$teamIds = array_map(fn($m) => (int) $m['id'], $team);

$id = (int) input('id');
$task = null;
if ($id) {
    $task = find_task($id) ?? not_found('Task not found.');
}

$data = $task ?? [
    'title' => '', 'description' => '', 'assigned_to' => (int) input('employee'), 'priority' => 'medium',
    'status' => 'todo', 'start_date' => today(), 'due_date' => '',
];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = [
        'title'       => input('title'),
        'description' => input('description'),
        'assigned_to' => (int) input('assigned_to'),
        'priority'    => input('priority'),
        'status'      => $task ? input('status') : 'todo',
        'start_date'  => input('start_date'),
        'due_date'    => input('due_date'),
    ];

    if ($data['title'] === '' || mb_strlen($data['title']) > 200) {
        $errors['title'] = 'Title is required (max 200 characters).';
    }
    if (!in_array($data['assigned_to'], $teamIds, true)) {
        $errors['assigned_to'] = 'Choose an active employee from your team.';
    }
    if (!isset(TASK_PRIORITIES[$data['priority']])) {
        $errors['priority'] = 'Choose a priority.';
    }
    if (!isset(TASK_STATUSES[$data['status']])) {
        $errors['status'] = 'Choose a status.';
    }
    if ($data['start_date'] !== '' && !valid_date($data['start_date'])) {
        $errors['start_date'] = 'Enter a valid start date.';
    }
    if (!valid_date($data['due_date'])) {
        $errors['due_date'] = 'Enter a valid due date.';
    } elseif ($data['start_date'] !== '' && valid_date($data['start_date']) && $data['due_date'] < $data['start_date']) {
        $errors['due_date'] = 'Due date cannot be before the start date.';
    }

    if (!$errors) {
        $db = db();
        $db->beginTransaction();
        $startDate = $data['start_date'] !== '' ? $data['start_date'] : null;

        if ($task) {
            $stmt = $db->prepare('UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, start_date = ?, due_date = ? WHERE id = ?');
            $stmt->execute([$data['title'], $data['description'], $data['assigned_to'], $data['priority'], $startDate, $data['due_date'], $id]);

            $note = 'Task details updated';
            if ((int) $task['assigned_to'] !== $data['assigned_to']) {
                $names = array_column($team, 'name', 'id');
                $note = 'Reassigned from ' . $task['assignee_name'] . ' to ' . $names[$data['assigned_to']];
            }
            log_task_activity($id, $me, 'updated', null, null, $note);
            change_task_status($task, $data['status'], $me, 'Status changed by manager');
            flash('success', 'Task updated.');
        } else {
            $stmt = $db->prepare('INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, start_date, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$data['title'], $data['description'], $data['assigned_to'], $me, $data['priority'], $startDate, $data['due_date']]);
            $id = (int) $db->lastInsertId();
            log_task_activity($id, $me, 'created', null, 'todo', 'Task assigned');
            flash('success', 'Task assigned.');
        }
        $db->commit();
        redirect('task_view', ['id' => $id]);
    }
}

render_header($task ? 'Edit Task' : 'Assign Task');
?>
<nav class="mb-2"><a href="<?= $task ? url('task_view', ['id' => $id]) : url('tasks') ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back</a></nav>
<h1 class="h3 mb-3"><?= $task ? 'Edit Task' : 'Assign a New Task' ?></h1>

<?php if (!$team): ?>
    <div class="alert alert-info">You need at least one active employee on your team before assigning tasks. <a href="<?= url('employee_form') ?>">Add an employee</a>.</div>
<?php endif; ?>

<form method="post" class="card card-body" style="max-width: 820px" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Title <span class="text-danger">*</span></label>
        <input name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" value="<?= e($data['title']) ?>" maxlength="200" required>
        <?= field_error($errors, 'title') ?>
    </div>
    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" rows="4" class="form-control"><?= e($data['description']) ?></textarea>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Assign to <span class="text-danger">*</span></label>
            <select name="assigned_to" class="form-select <?= isset($errors['assigned_to']) ? 'is-invalid' : '' ?>" required>
                <option value="">Select employee…</option>
                <?php foreach ($team as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= (int) $data['assigned_to'] === (int) $m['id'] ? 'selected' : '' ?>>
                        <?= e($m['name']) ?><?= $m['designation'] ? ' — ' . e($m['designation']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'assigned_to') ?>
        </div>
        <div class="col-md-<?= $task ? 3 : 6 ?>">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-select">
                <?php foreach (TASK_PRIORITIES as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $data['priority'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'priority') ?>
        </div>
        <?php if ($task): ?>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <?php foreach (TASK_STATUSES as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $data['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'status') ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label">Start date</label>
            <input type="date" name="start_date" class="form-control <?= isset($errors['start_date']) ? 'is-invalid' : '' ?>" value="<?= e($data['start_date']) ?>">
            <?= field_error($errors, 'start_date') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label">Due date <span class="text-danger">*</span></label>
            <input type="date" name="due_date" class="form-control <?= isset($errors['due_date']) ? 'is-invalid' : '' ?>" value="<?= e($data['due_date']) ?>" required>
            <?= field_error($errors, 'due_date') ?>
        </div>
    </div>
    <div>
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $task ? 'Save changes' : 'Assign task' ?></button>
        <a href="<?= $task ? url('task_view', ['id' => $id]) : url('tasks') ?>" class="btn btn-link">Cancel</a>
    </div>
</form>
<?php render_footer(); ?>
