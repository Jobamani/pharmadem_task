-- Demo data. Run after schema.sql.
-- Dates are relative to CURDATE() so the demo always shows a realistic mix of
-- upcoming, overdue and completed work whenever it is imported.
--
-- All demo accounts use the password: password123

INSERT INTO users (id, name, email, password_hash, role, designation, manager_id) VALUES
(1, 'Rahul Mehta',  'manager@demo.com', '$2y$10$mAVVo77KNPKofeMjbB0MvO5xltP74fICcLXS9GbEU7RbFHTE8dYSS', 'manager',  'Operations Manager',    NULL),
(2, 'Sneha Iyer',   'sneha@demo.com',   '$2y$10$mAVVo77KNPKofeMjbB0MvO5xltP74fICcLXS9GbEU7RbFHTE8dYSS', 'employee', 'QA Analyst',            1),
(3, 'Arjun Nair',   'arjun@demo.com',   '$2y$10$mAVVo77KNPKofeMjbB0MvO5xltP74fICcLXS9GbEU7RbFHTE8dYSS', 'employee', 'Production Executive',  1),
(4, 'Kavya Reddy',  'kavya@demo.com',   '$2y$10$mAVVo77KNPKofeMjbB0MvO5xltP74fICcLXS9GbEU7RbFHTE8dYSS', 'employee', 'Regulatory Associate',  1),
(5, 'Vikram Singh', 'vikram@demo.com',  '$2y$10$mAVVo77KNPKofeMjbB0MvO5xltP74fICcLXS9GbEU7RbFHTE8dYSS', 'employee', 'Lab Technician',        1);

INSERT INTO tasks (id, title, description, assigned_to, assigned_by, priority, status, start_date, due_date, completed_at, created_at) VALUES
(1,  'Prepare monthly QC summary report',           'Consolidate QC results for all batches released last month.',           2, 1, 'high',     'completed',   CURDATE() - INTERVAL 10 DAY, CURDATE() - INTERVAL 5 DAY, NOW() - INTERVAL 6 DAY,  NOW() - INTERVAL 10 DAY),
(2,  'Review batch records for Batch B-1042',       'Verify entries, signatures and deviations in the BMR.',                   2, 1, 'critical', 'in_progress', CURDATE() - INTERVAL 3 DAY,  CURDATE() + INTERVAL 2 DAY, NULL,                    NOW() - INTERVAL 3 DAY),
(3,  'Update HPLC calibration logs',                'Enter last week''s calibration results and attach certificates.',         2, 1, 'medium',   'todo',        CURDATE(),                   CURDATE() + INTERVAL 5 DAY, NULL,                    NOW()),
(4,  'Revise SOP for raw material sampling',        'Incorporate the new sampling plan approved by QA.',                       3, 1, 'high',     'pending',     CURDATE() - INTERVAL 8 DAY,  CURDATE() - INTERVAL 2 DAY, NULL,                    NOW() - INTERVAL 8 DAY),
(5,  'Production line checklist audit',             'Audit the daily checklists for lines 1-3.',                               3, 1, 'medium',   'completed',   CURDATE() - INTERVAL 7 DAY,  CURDATE() - INTERVAL 3 DAY, NOW() - INTERVAL 1 DAY,  NOW() - INTERVAL 7 DAY),
(6,  'Packaging material inventory reconciliation', 'Reconcile physical stock against ERP for cartons and labels.',             3, 1, 'low',      'in_progress', CURDATE() - INTERVAL 2 DAY,  CURDATE() + INTERVAL 3 DAY, NULL,                    NOW() - INTERVAL 2 DAY),
(7,  'Compile stability study data',                'Compile 6-month stability data for product line A.',                      4, 1, 'high',     'completed',   CURDATE() - INTERVAL 12 DAY, CURDATE() - INTERVAL 4 DAY, NOW() - INTERVAL 4 DAY,  NOW() - INTERVAL 12 DAY),
(8,  'Draft regulatory submission cover letter',    'Prepare the cover letter and checklist for the dossier submission.',      4, 1, 'critical', 'in_progress', CURDATE() - INTERVAL 4 DAY,  CURDATE() - INTERVAL 1 DAY, NULL,                    NOW() - INTERVAL 4 DAY),
(9,  'Archive validation documents',                'Scan and archive closed validation protocols.',                          4, 1, 'low',      'todo',        CURDATE(),                   CURDATE() + INTERVAL 7 DAY, NULL,                    NOW()),
(10, 'Prepare reagent stock list',                  'List reagents below reorder level and raise indent.',                     5, 1, 'medium',   'completed',   CURDATE() - INTERVAL 6 DAY,  CURDATE() - INTERVAL 2 DAY, NOW() - INTERVAL 3 DAY,  NOW() - INTERVAL 6 DAY),
(11, 'Equipment cleaning log verification',         'Verify cleaning logs for all lab equipment for the last two weeks.',      5, 1, 'high',     'todo',        CURDATE() - INTERVAL 5 DAY,  CURDATE() - INTERVAL 3 DAY, NULL,                    NOW() - INTERVAL 5 DAY),
(12, 'Complete new safety protocol training',       'Finish the online module and submit the assessment.',                     5, 1, 'medium',   'completed',   CURDATE() - INTERVAL 9 DAY,  CURDATE() - INTERVAL 6 DAY, NOW() - INTERVAL 6 DAY,  NOW() - INTERVAL 9 DAY),
(13, 'Investigate deviation report DR-221',         'Root cause analysis and CAPA proposal for DR-221.',                       2, 1, 'high',     'completed',   CURDATE() - INTERVAL 5 DAY,  CURDATE() - INTERVAL 1 DAY, NOW() - INTERVAL 2 DAY,  NOW() - INTERVAL 5 DAY);

-- Activity log: creation of every task, plus completion events.
INSERT INTO task_activity (task_id, user_id, action, old_status, new_status, note, created_at)
SELECT id, assigned_by, 'created', NULL, 'todo', 'Task assigned', created_at FROM tasks;

INSERT INTO task_activity (task_id, user_id, action, old_status, new_status, note, created_at)
SELECT id, assigned_to, 'status_change', 'in_progress', 'completed', 'Marked as completed', completed_at
FROM tasks WHERE status = 'completed';

INSERT INTO task_activity (task_id, user_id, action, old_status, new_status, note, created_at) VALUES
(4, 3, 'status_change', 'in_progress', 'pending', 'Waiting for QA approval of the revised sampling plan.', NOW() - INTERVAL 3 DAY);

INSERT INTO daily_updates (id, user_id, update_date, summary, plan_next, blockers, hours_worked, created_at) VALUES
(1, 2, CURDATE() - INTERVAL 2 DAY, 'Worked on the DR-221 root cause analysis; interviewed line operators.',          'Finalise CAPA proposal for DR-221.',             NULL,                                         8.00, NOW() - INTERVAL 2 DAY),
(2, 2, CURDATE() - INTERVAL 1 DAY, 'Closed DR-221 investigation. Started review of Batch B-1042 records.',          'Continue BMR review for B-1042.',                NULL,                                         8.50, NOW() - INTERVAL 1 DAY),
(3, 2, CURDATE(),                  'Reviewed sections 1-4 of the B-1042 batch record.',                             'Finish remaining sections and raise queries.',  'Two signatures missing on page 12.',         7.50, NOW()),
(4, 3, CURDATE() - INTERVAL 1 DAY, 'Completed checklist audit for lines 1-3. Began packaging inventory count.',     'Complete carton count and compare with ERP.',   'SOP revision still waiting on QA approval.', 8.00, NOW() - INTERVAL 1 DAY),
(5, 4, CURDATE() - INTERVAL 2 DAY, 'Drafted first version of the submission cover letter.',                         'Incorporate review comments.',                  NULL,                                         7.00, NOW() - INTERVAL 2 DAY),
(6, 4, CURDATE() - INTERVAL 1 DAY, 'Updated the cover letter; checklist 70% complete.',                             'Complete checklist and send for sign-off.',     'Awaiting final data from the stability team.', 8.00, NOW() - INTERVAL 1 DAY),
(7, 5, CURDATE() - INTERVAL 3 DAY, 'Prepared reagent stock list and raised indent for 6 items.',                    'Start cleaning log verification.',              NULL,                                         6.50, NOW() - INTERVAL 3 DAY);

INSERT INTO daily_update_tasks (daily_update_id, task_id, status_after, progress_note, hours_spent) VALUES
(1, 13, 'in_progress', 'Operator interviews done, fishbone diagram drafted.',  6.00),
(2, 13, 'completed',   'RCA and CAPA submitted to QA head.',                   4.00),
(2, 2,  'in_progress', 'Started BMR review.',                                  4.50),
(3, 2,  'in_progress', 'Sections 1-4 reviewed.',                               7.50),
(4, 5,  'completed',   'All three lines audited.',                             5.00),
(4, 6,  'in_progress', 'Label count done, cartons pending.',                   3.00),
(5, 8,  'in_progress', 'First draft ready.',                                   7.00),
(6, 8,  'in_progress', 'Draft revised, checklist in progress.',                8.00),
(7, 10, 'completed',   'Stock list shared with stores.',                       6.50);

-- One sample review from the previous calendar month.
INSERT INTO performance_reviews
(employee_id, reviewer_id, period_start, period_end, tasks_assigned, tasks_completed, completed_on_time, overdue_open,
 completion_rate, on_time_rate, update_compliance, system_score, quality_rating, teamwork_rating, communication_rating,
 final_score, grade, comments, created_at) VALUES
(2, 1, DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'), LAST_DAY(CURDATE() - INTERVAL 1 MONTH),
 8, 7, 6, 0, 87.50, 85.71, 90.00, 87.50, 4, 5, 4, 87.17, 'Outstanding',
 'Consistently thorough reviews. Keep improving turnaround on batch record queries.', NOW() - INTERVAL 10 DAY);
