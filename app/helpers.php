<?php
declare(strict_types=1);

/* ---------- Output & routing ---------- */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $page, array $params = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $params);
}

function redirect(string $page, array $params = []): never
{
    header('Location: ' . url($page, $params));
    exit;
}

function render_header(string $title): void
{
    $pageTitle = $title;
    require APP_PATH . '/views/header.php';
}

function render_footer(): void
{
    require APP_PATH . '/views/footer.php';
}

function stat_card(string $label, int|string $value, string $color, string $icon, ?string $href = null): string
{
    $body = '<div class="card stat-card border-' . $color . ' h-100"><div class="card-body d-flex justify-content-between align-items-center">'
        . '<div><div class="stat-value">' . e($value) . '</div><div class="stat-label">' . e($label) . '</div></div>'
        . '<i class="bi bi-' . $icon . ' fs-2 text-' . $color . ' opacity-50"></i></div></div>';
    return $href ? '<a href="' . e($href) . '" class="text-decoration-none text-reset">' . $body . '</a>' : $body;
}

function not_found(string $message = 'The page you requested was not found.'): never
{
    http_response_code(404);
    render_header('Not found');
    echo '<div class="alert alert-warning">' . e($message) . '</div>';
    render_footer();
    exit;
}

/* ---------- Flash messages ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ---------- Request helpers & CSRF ---------- */

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
}

function verify_csrf(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

/* ---------- Authentication ---------- */

function current_user(): ?array
{
    static $user = false;

    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, name, email, role, designation, manager_id FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
            if ($user === null) {
                unset($_SESSION['user_id']); // account deleted or deactivated
            }
        }
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login');
    }
    return $user;
}

function require_manager(): array
{
    $user = require_login();
    if ($user['role'] !== 'manager') {
        http_response_code(403);
        render_header('Access denied');
        echo '<div class="alert alert-danger">This page is only available to managers.</div>';
        render_footer();
        exit;
    }
    return $user;
}

function is_manager(): bool
{
    return (current_user()['role'] ?? null) === 'manager';
}

/* ---------- Dates ---------- */

function today(): string
{
    return date('Y-m-d');
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function fmt_date(?string $date): string
{
    return $date ? date('d M Y', strtotime($date)) : '—';
}

function fmt_datetime(?string $date): string
{
    return $date ? date('d M Y, h:i A', strtotime($date)) : '—';
}

/** Number of Monday–Friday days between two dates, inclusive. */
function working_days(string $from, string $to): int
{
    $start = new DateTime($from);
    $end   = new DateTime($to);
    $days  = 0;
    while ($start <= $end) {
        if ((int) $start->format('N') <= 5) {
            $days++;
        }
        $start->modify('+1 day');
    }
    return $days;
}

/** Read a valid from/to date range from the request (defaults to this month so far). */
function period_input(): array
{
    $from = input('from', date('Y-m-01'));
    $to   = input('to', today());
    $from = valid_date($from) ? $from : date('Y-m-01');
    $to   = valid_date($to) ? $to : today();
    return $from <= $to ? [$from, $to] : [$to, $from];
}

function period_presets(): array
{
    return [
        'This week'    => [date('Y-m-d', strtotime('monday this week')), today()],
        'This month'   => [date('Y-m-01'), today()],
        'Last month'   => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
        'Last 30 days' => [date('Y-m-d', strtotime('-29 days')), today()],
        'Last 90 days' => [date('Y-m-d', strtotime('-89 days')), today()],
    ];
}

/* ---------- Tasks ---------- */

function is_overdue(array $task): bool
{
    return $task['status'] !== 'completed' && $task['due_date'] < today();
}

/** Status badge; pass the due date to also flag the task as overdue. */
function status_badge(string $status, ?string $dueDate = null): string
{
    $classes = [
        'todo'        => 'secondary',
        'in_progress' => 'primary',
        'pending'     => 'warning text-dark',
        'completed'   => 'success',
    ];
    $html = '<span class="badge bg-' . $classes[$status] . '">' . e(TASK_STATUSES[$status]) . '</span>';
    if ($dueDate !== null && is_overdue(['status' => $status, 'due_date' => $dueDate])) {
        $html .= ' <span class="badge bg-danger">Overdue</span>';
    }
    return $html;
}

function priority_badge(string $priority): string
{
    $classes = ['low' => 'light text-dark border', 'medium' => 'info text-dark', 'high' => 'warning text-dark', 'critical' => 'danger'];
    return '<span class="badge bg-' . $classes[$priority] . '">' . e(TASK_PRIORITIES[$priority]) . '</span>';
}

/** Active employees reporting to the given manager. */
function team_members(int $managerId, bool $activeOnly = true): array
{
    $sql = "SELECT id, name, email, designation, is_active FROM users
            WHERE role = 'employee' AND manager_id = ?" . ($activeOnly ? ' AND is_active = 1' : '') . '
            ORDER BY name';
    $stmt = db()->prepare($sql);
    $stmt->execute([$managerId]);
    return $stmt->fetchAll();
}

/**
 * Load a task the current user is allowed to see: employees see their own
 * tasks, managers see tasks of employees on their team.
 */
function find_task(int $id): ?array
{
    $user = current_user();
    $sql = 'SELECT t.*, a.name AS assignee_name, a.manager_id AS assignee_manager_id, b.name AS assigner_name
            FROM tasks t
            JOIN users a ON a.id = t.assigned_to
            JOIN users b ON b.id = t.assigned_by
            WHERE t.id = ?';
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    $task = $stmt->fetch();

    if (!$task) {
        return null;
    }
    $allowed = $user['role'] === 'manager'
        ? (int) $task['assignee_manager_id'] === (int) $user['id']
        : (int) $task['assigned_to'] === (int) $user['id'];

    return $allowed ? $task : null;
}

function log_task_activity(int $taskId, int $userId, string $action, ?string $old = null, ?string $new = null, ?string $note = null): void
{
    $stmt = db()->prepare('INSERT INTO task_activity (task_id, user_id, action, old_status, new_status, note) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$taskId, $userId, $action, $old, $new, $note !== '' ? $note : null]);
}

/** Change a task's status, keeping completed_at and the activity log in sync. */
function change_task_status(array $task, string $newStatus, int $userId, ?string $note = null): void
{
    if ($task['status'] === $newStatus) {
        return;
    }
    $completedAt = $newStatus === 'completed' ? date('Y-m-d H:i:s') : null;
    $stmt = db()->prepare('UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?');
    $stmt->execute([$newStatus, $completedAt, $task['id']]);
    log_task_activity((int) $task['id'], $userId, 'status_change', $task['status'], $newStatus, $note);
}
