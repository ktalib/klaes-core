-- Cadastral Department: AD Drawing & Charting desk role (updated workflow doc, 2026-10-15).
-- NOT YET RUN. Target: sqlsrv (klas @ 10.50.1.1). Idempotent: inserts only when missing.
-- Name matches config/cadastral_dept.php desk_roles.ad_drawing and the sidebar gate.
-- Until it runs, only Cad - Records / Supper Admin can work the desk.
-- Grant per user by adding the name to users.assign_role (User Account screen).
-- Undo: DELETE FROM user_roles WHERE department_id = 10 AND name = 'Cad - Drawing & Charting'
--       after removing it from every users.assign_role.

INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT 'Cad - Drawing & Charting', 'web', 'Cadastral Department: AD Drawing & Charting desk (final charting)', 10, 'High', 'Operations', 1, GETDATE(), GETDATE()
WHERE NOT EXISTS (SELECT 1 FROM user_roles u WHERE u.name = 'Cad - Drawing & Charting');
