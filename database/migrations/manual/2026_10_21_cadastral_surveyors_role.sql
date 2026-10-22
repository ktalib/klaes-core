-- Cadastral: Surveyors (Cadastral Information > Surveyors) role (user, 2026-10-21).
-- Target: sqlsrv (klas @ 10.50.1.1). Idempotent: inserts only when missing.
-- Name matches the sidebar gate in resources/views/admin/menu/partials/modules/cadastral_workflow.blade.php.
-- The Cadastral Information desk role (Cad - Information), Cad - Records and Supper Admin keep the entry.
-- Grant per user by adding the name to users.assign_role (User Account screen).
-- Undo: DELETE FROM user_roles WHERE department_id = 10 AND name = 'Cad - Surveyors'
--       after removing it from every users.assign_role.

INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT 'Cad - Surveyors', 'web', 'Cadastral: Surveyors register (Cadastral Information > Surveyors)', 10, 'High', 'Operations', 1, GETDATE(), GETDATE()
WHERE NOT EXISTS (SELECT 1 FROM user_roles u WHERE u.name = 'Cad - Surveyors');
