-- Survey page-area roles (user, 2026-10-30: "Desktop page roles").
-- Target: sqlsrv (klas @ 10.50.1.1). Idempotent: inserts only when missing. Department 4 = Survey.
--   Survey - Compensation   : the Compensation section (CO files, projects, lands, farmers, trees,
--                             Capture Compensation List, calculators, resettlement plot allocation,
--                             OP (Resettlement), Letter of Grant, plot confirmation, complaints)
--                             and the Plot Allocation register.
--   Survey - Public Survey  : Public Survey (Public Layout Plans, Private Layout, Layout Records).
-- Survey - Records keeps every Survey page. Names match config/module_permissions.php
-- ('alternatives') and the sidebar gate in resources/views/admin/menu/partials/modules/survey.blade.php.
-- Grant per user by adding the name to users.assign_role (User Account screen). Holders can view,
-- create and edit; delete needs the Delete tick in the user's permission grid.
-- Undo: DELETE FROM user_roles WHERE department_id = 4 AND name IN ('Survey - Compensation', 'Survey - Public Survey')
--       after removing them from every users.assign_role.

INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT 'Survey - Compensation', 'web', 'Survey: Compensation (CO files, projects, lands, farmers, capture lists, resettlement, Letter of Grant)', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()
WHERE NOT EXISTS (SELECT 1 FROM user_roles u WHERE u.name = 'Survey - Compensation');

INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT 'Survey - Public Survey', 'web', 'Survey: Public Survey (Public Layout Plans, Private Layout, Layout Records)', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()
WHERE NOT EXISTS (SELECT 1 FROM user_roles u WHERE u.name = 'Survey - Public Survey');
