<?php
$user = require_manager();
$me = (int) $user['id'];
$id = (int) input('id');

$employee = null;
if ($id) {
    $stmt = db()->prepare("SELECT id, name, email, designation FROM users WHERE id = ? AND manager_id = ? AND role = 'employee'");
    $stmt->execute([$id, $me]);
    $employee = $stmt->fetch() ?: not_found('Employee not found on your team.');
}

$form = $employee ?? ['name' => '', 'email' => '', 'designation' => ''];
$errors = [];

if (is_post()) {
    verify_csrf();
    $form = ['name' => input('name'), 'email' => strtolower(input('email')), 'designation' => input('designation')];
    $password = (string) ($_POST['password'] ?? '');

    if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
        $errors['name'] = 'Name is required (max 100 characters).';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
        $stmt->execute([$form['email'], $id]);
        if ($stmt->fetchColumn()) {
            $errors['email'] = 'This email is already in use.';
        }
    }
    if ((!$employee || $password !== '') && strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
        if ($employee) {
            $stmt = db()->prepare('UPDATE users SET name = ?, email = ?, designation = ? WHERE id = ?');
            $stmt->execute([$form['name'], $form['email'], $form['designation'] ?: null, $id]);
            if ($password !== '') {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            flash('success', 'Employee updated.');
        } else {
            $stmt = db()->prepare("INSERT INTO users (name, email, password_hash, role, designation, manager_id) VALUES (?, ?, ?, 'employee', ?, ?)");
            $stmt->execute([$form['name'], $form['email'], password_hash($password, PASSWORD_DEFAULT), $form['designation'] ?: null, $me]);
            flash('success', 'Employee added. Share their login email and password with them.');
        }
        redirect('employees');
    }
}

render_header($employee ? 'Edit Employee' : 'Add Employee');
?>
<nav class="mb-2"><a href="<?= url('employees') ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Team</a></nav>
<h1 class="h3 mb-3"><?= $employee ? 'Edit Employee' : 'Add Employee' ?></h1>

<form method="post" class="card card-body" style="max-width: 560px" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label">Full name <span class="text-danger">*</span></label>
        <input name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= e($form['name']) ?>" required>
        <?= field_error($errors, 'name') ?>
    </div>
    <div class="mb-3">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= e($form['email']) ?>" required>
        <?= field_error($errors, 'email') ?>
    </div>
    <div class="mb-3">
        <label class="form-label">Designation</label>
        <input name="designation" class="form-control" value="<?= e($form['designation']) ?>" maxlength="100">
    </div>
    <div class="mb-4">
        <label class="form-label"><?= $employee ? 'New password' : 'Password <span class="text-danger">*</span>' ?></label>
        <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" autocomplete="new-password"
               placeholder="<?= $employee ? 'Leave blank to keep the current password' : 'At least 8 characters' ?>">
        <?= field_error($errors, 'password') ?>
    </div>
    <div>
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $employee ? 'Save changes' : 'Add employee' ?></button>
        <a href="<?= url('employees') ?>" class="btn btn-link">Cancel</a>
    </div>
</form>
<?php render_footer(); ?>
