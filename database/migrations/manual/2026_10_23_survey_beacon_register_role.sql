-- Survey: Beacon Register role (Survey > Examination/Verification > Beacon Register), user 2026-10-23.
-- Target: sqlsrv (klas @ 10.50.1.1). Idempotent: inserts only when missing.
-- department_id 4 = Survey, like the other Survey - * roles.
-- Name matches config/module_permissions.php ('survey-module.workflow.beacon-register.*') and the
-- sidebar gate in resources/views/admin/menu/partials/modules/survey.blade.php.
-- Grant per user by adding the name to users.assign_role (User Account screen). Holders can view,
-- create and edit; delete needs the Delete tick in the user's permission grid.
-- Undo: DELETE FROM user_roles WHERE department_id = 4 AND name = 'Survey - Beacon Register'
--       after removing it from every users.assign_role.

INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT 'Survey - Beacon Register', 'web', 'Survey: Beacon Register (Examination/Verification > Beacon Register)', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()
WHERE NOT EXISTS (SELECT 1 FROM user_roles u WHERE u.name = 'Survey - Beacon Register');
