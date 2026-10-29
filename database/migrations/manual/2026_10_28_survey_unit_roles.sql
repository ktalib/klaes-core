-- Survey Department unit roles for Cadastral files (user, 2026-10-28: Survey sidebar >
-- Other Departments > Cadastral > Compensation Check / DSG Approval / Examination Check / SG Approval).
-- Target: sqlsrv (klas @ 10.50.1.1). Idempotent: inserts only when missing. Department 4 = Survey.
-- Names match config/cadastral_dept.php external_desk_roles and the Survey sidebar gate.
-- A holder sees the unit's page and sends a Cadastral file on while it is with the unit
-- (DeskAccess::canActOn). Grant per user by adding the name to users.assign_role (User Account screen).
-- Undo: DELETE FROM user_roles WHERE department_id = 4 AND name IN (the four names below)
--       after removing them from every users.assign_role.

INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT 'Survey - Compensation Check', 'web', 'Survey: Compensation Unit check on Cadastral files', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()
WHERE NOT EXISTS (SELECT 1 FROM user_roles u WHERE u.name = 'Survey - Compensation Check');

INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT 'Survey - DSG', 'web', 'Survey: Deputy Surveyor General approval of Cadastral files', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()
WHERE NOT EXISTS (SELECT 1 FROM user_roles u WHERE u.name = 'Survey - DSG');

INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT 'Survey - Examination', 'web', 'Survey: Examination Unit check of Cadastral survey plans', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()
WHERE NOT EXISTS (SELECT 1 FROM user_roles u WHERE u.name = 'Survey - Examination');

INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT 'Survey - SG', 'web', 'Survey: Surveyor General approval and Final Survey Plan upload on Cadastral files', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()
WHERE NOT EXISTS (SELECT 1 FROM user_roles u WHERE u.name = 'Survey - SG');
