# WorkTrack — Employee Daily Work Update & Performance Evaluation System

A PHP + MySQL web application for allocating tasks to employees, collecting daily work updates,
tracking completed / pending / overdue work, and evaluating employee performance.

**Stack:** PHP 8.1+ (no framework, PDO), MySQL 5.7+/8.x, Bootstrap 5 (CDN).

---

## Features

| Requirement | Implementation |
|---|---|
| **Task allocation & tracking** | Managers assign tasks (title, description, priority, start/due date) to team members, edit, reassign or delete them. Each task has a status (To Do → In Progress → Pending → Completed) and a full activity log (who changed what, when, with notes). |
| **Daily work updates (to-do & pending)** | Employees submit one update per day: tasks worked on (status, progress note, hours each), summary of work done, **to-do for the next day**, and **pending items / blockers**. Submitting an update also updates the task statuses. Updates can be edited the same day. |
| **Completion & overdue tracking** | A task is *overdue* when its due date has passed and it isn't completed. Completion time is recorded, so every completed task is classed as *on time* or *late*. Overdue tasks are highlighted everywhere. |
| **Manager dashboard & reports** | Dashboard: open, overdue, due-today and completed-this-week counts; a per-employee overview; today's update submission status; overdue list; latest updates. Reports: any date range (presets included), team KPIs, status breakdown, a per-employee performance table, the overdue task list, **CSV export** and print view. |
| **Performance evaluation** | Live system score per employee for any period, plus formal reviews where the manager adds 1–5 ratings and comments. Reviews snapshot the metrics at the time of review. Employees can see their own score and review history. |

### Roles
- **Manager**: manages their own team (employees whose `manager_id` is the manager), assigns tasks, views team updates and reports, and evaluates performance.
- **Employee**: sees only their own tasks, updates task status, submits daily updates, and views their own performance.

### Performance scoring

The tasks counted for a period are those **due** in the period plus those **completed** in it.

```
System score  = 40% × completion rate     (completed / tasks in period)
              + 35% × on-time rate        (completed on/before due date / completed)
              + 25% × update compliance   (daily updates submitted / working days Mon–Fri elapsed)

Final score   = 60% × system score + 40% × manager rating
                (average of Quality, Teamwork, Communication, each 1–5, scaled to 100)

Grade: ≥ 85 Outstanding · ≥ 70 Good · ≥ 50 Satisfactory · < 50 Needs Improvement
```
If an employee had no tasks in the period, the system score is the update compliance alone.
The weights are constants at the top of `app/performance.php`.

---

## Setup

### 1. Create the database
```bash
mysql -u root -p -e "CREATE DATABASE task_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p task_system < database/schema.sql
mysql -u root -p task_system < database/seed.sql     # optional demo data
```
(Or, in phpMyAdmin, create `task_system` and import `schema.sql` and then `seed.sql`.)

### 2. Configure the connection
Edit the defaults in `config/database.php` (default user `root`, empty password), or create a
git-ignored `config/database.local.php` that returns only the values to override:
```php
<?php return ['db' => ['user' => 'root', 'password' => 'your-password']];
```
You can also set the environment variables
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` (and optionally `APP_TIMEZONE`, default `Asia/Kolkata`).

### 3. Run
```bash
php -S localhost:8000 -t public
```
Open http://localhost:8000. With Apache/XAMPP, point the virtual host's document root at the `public/` folder.

### Demo accounts
All use the password `password123`.

| Role | Email |
|---|---|
| Manager | manager@demo.com |
| Employee | sneha@demo.com, arjun@demo.com, kavya@demo.com, vikram@demo.com |

The seed data uses dates relative to the import date, so it always contains a mix of upcoming, overdue,
on-time and late tasks.

---

## Project structure
```
config/database.php     DB + app settings (env vars override defaults)
database/schema.sql     Tables, keys and indexes
database/seed.sql       Demo data
public/index.php        Front controller (whitelisted ?page= routes); the only web root
public/assets/app.css   Styles
app/bootstrap.php       Config, session, PDO connection, constants
app/helpers.php         Auth, CSRF, routing, formatting and task helpers
app/performance.php     Metrics and scoring logic
app/views/              Layout header/footer
app/pages/              One file per page (dashboard, tasks, updates, reports, performance, team…)
```

## Database design
- `users`: managers and employees (`role`, `manager_id` builds the team hierarchy, `is_active` for soft deactivation)
- `tasks`: allocated work. *Overdue* is derived, not stored, so it is always correct.
- `task_activity`: audit trail of creation, status changes, edits and comments
- `daily_updates`: one row per employee per day (unique key on `user_id, update_date`)
- `daily_update_tasks`: tasks reported in each daily update, with progress and hours
- `performance_reviews`: manager evaluations with a snapshot of the system metrics

## Security
- Passwords hashed with `password_hash()` (bcrypt); session ID regenerated on login
- All queries use PDO prepared statements
- CSRF token on every POST form; all output escaped with `htmlspecialchars`
- Role checks on every manager page; row-level checks so employees only see their own data and managers only see their own team
- Only `public/` is web-accessible; routing is a whitelist
