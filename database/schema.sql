-- Employee Daily Work Update & Performance Evaluation System
-- MySQL 5.7+ / 8.x schema
--
-- Usage:
--   CREATE DATABASE task_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   USE task_system;
--   SOURCE database/schema.sql;
--   SOURCE database/seed.sql;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS performance_reviews;
DROP TABLE IF EXISTS daily_update_tasks;
DROP TABLE IF EXISTS daily_updates;
DROP TABLE IF EXISTS task_activity;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Managers and employees. An employee belongs to one manager (manager_id),
-- and a manager only sees and evaluates their own team.
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('manager', 'employee') NOT NULL DEFAULT 'employee',
    designation   VARCHAR(100)  NULL,
    manager_id    INT UNSIGNED  NULL,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_manager FOREIGN KEY (manager_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_users_manager (manager_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Tasks allocated by a manager to an employee.
-- "Overdue" is not stored: it is derived as status <> 'completed' AND due_date < today.
CREATE TABLE tasks (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    description  TEXT         NULL,
    assigned_to  INT UNSIGNED NOT NULL,
    assigned_by  INT UNSIGNED NOT NULL,
    priority     ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    status       ENUM('todo', 'in_progress', 'pending', 'completed') NOT NULL DEFAULT 'todo',
    start_date   DATE         NULL,
    due_date     DATE         NOT NULL,
    completed_at DATETIME     NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_tasks_assignee_status (assigned_to, status),
    INDEX idx_tasks_due_date (due_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Audit trail of task changes (created, status changes, edits).
CREATE TABLE task_activity (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    action     VARCHAR(30)  NOT NULL,
    old_status VARCHAR(20)  NULL,
    new_status VARCHAR(20)  NULL,
    note       TEXT         NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_activity_task (task_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- One end-of-day work update per employee per date.
CREATE TABLE daily_updates (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    update_date  DATE         NOT NULL,
    summary      TEXT         NOT NULL,   -- work done today
    plan_next    TEXT         NULL,       -- to-do for the next working day
    blockers     TEXT         NULL,       -- pending items / blockers
    hours_worked DECIMAL(4,2) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_updates_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY uq_updates_user_date (user_id, update_date),
    INDEX idx_updates_date (update_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Tasks worked on within a daily update, with the progress made on each.
CREATE TABLE daily_update_tasks (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    daily_update_id INT UNSIGNED NOT NULL,
    task_id         INT UNSIGNED NOT NULL,
    status_after    ENUM('todo', 'in_progress', 'pending', 'completed') NOT NULL,
    progress_note   VARCHAR(500) NULL,
    hours_spent     DECIMAL(4,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_uptasks_update FOREIGN KEY (daily_update_id) REFERENCES daily_updates (id) ON DELETE CASCADE,
    CONSTRAINT fk_uptasks_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
    UNIQUE KEY uq_uptasks (daily_update_id, task_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Performance evaluations. System metrics are snapshotted at review time so
-- a saved review does not change when task data changes later.
CREATE TABLE performance_reviews (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id          INT UNSIGNED NOT NULL,
    reviewer_id          INT UNSIGNED NOT NULL,
    period_start         DATE         NOT NULL,
    period_end           DATE         NOT NULL,
    tasks_assigned       INT UNSIGNED NOT NULL DEFAULT 0,
    tasks_completed      INT UNSIGNED NOT NULL DEFAULT 0,
    completed_on_time    INT UNSIGNED NOT NULL DEFAULT 0,
    overdue_open         INT UNSIGNED NOT NULL DEFAULT 0,
    completion_rate      DECIMAL(5,2) NOT NULL DEFAULT 0,
    on_time_rate         DECIMAL(5,2) NOT NULL DEFAULT 0,
    update_compliance    DECIMAL(5,2) NOT NULL DEFAULT 0,
    system_score         DECIMAL(5,2) NOT NULL DEFAULT 0,
    quality_rating       TINYINT UNSIGNED NOT NULL,
    teamwork_rating      TINYINT UNSIGNED NOT NULL,
    communication_rating TINYINT UNSIGNED NOT NULL,
    final_score          DECIMAL(5,2) NOT NULL,
    grade                VARCHAR(30)  NOT NULL,
    comments             TEXT         NULL,
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_employee FOREIGN KEY (employee_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_reviews_employee (employee_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
