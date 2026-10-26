-- Cadastral Central Admin role (CADASTRAL UPDATE, 2026-10-26) — register the module so it can be granted.
-- Target: sqlsrv (klas @ 10.50.1.1). Idempotent: inserts only when the name is missing.
-- Name matches config/cadastral_dept.php (central_admin_role). A holder works every Cadastral desk
-- and stands in for every unit head from Admin > Central Admin.
-- Grant per user by adding the name to users.assign_role (User Account screen).
-- Undo: DELETE FROM user_roles WHERE department_id = 10 AND name = 'Cad - Central Admin'
--       after removing it from every users.assign_role.

INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT 'Cad - Central Admin', 'web',
       'Cadastral Department: Central Admin - acts on any file at any desk and approves for any unit head',
       10, 'Highest', 'Management', 1, GETDATE(), GETDATE()
WHERE NOT EXISTS (SELECT 1 FROM user_roles u WHERE u.name = 'Cad - Central Admin');
