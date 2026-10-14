<?php
if (current_user()) {
    redirect('dashboard');
}

$error = '';
$email = '';

if (is_post()) {
    verify_csrf();
    $email = input('email');
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        $error = 'Invalid email or password.';
    } elseif (!$row['is_active']) {
        $error = 'Your account has been deactivated. Please contact your manager.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];
        redirect('dashboard');
    }
}

render_header('Login');
?>
<div class="login-wrap">
    <div class="card p-4" style="width: 100%; max-width: 400px;">
        <div class="text-center mb-4">
            <i class="bi bi-clipboard2-check fs-1 text-primary"></i>
            <h1 class="h4 mt-2 mb-0"><?= e(APP_NAME) ?></h1>
            <p class="text-muted small">Daily Work Update &amp; Performance System</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100">Sign in</button>
        </form>
        <div class="mt-4 small text-muted border-top pt-3">
            <strong>Demo accounts</strong> (password: <code>password123</code>)<br>
            Manager: <code>manager@demo.com</code><br>
            Employee: <code>sneha@demo.com</code>, <code>arjun@demo.com</code>
        </div>
    </div>
</div>
<?php render_footer(); ?>
