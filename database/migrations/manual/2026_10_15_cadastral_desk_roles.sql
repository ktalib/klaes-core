-- Cadastral Department desk roles (user, 2026-10-15) — register the modules so they can be granted.
-- Target: sqlsrv (klas @ 10.50.1.1). Idempotent: inserts only names that are missing.
-- Names match config/cadastral_dept.php (desk_roles, verification_role) and the sidebar gates in
-- admin/menu/partials/modules/cadastral_workflow.blade.php. Cad - Digital Archive was already
-- checked by the sidebar but had no row.
-- Grant per user by adding the name to users.assign_role (User Account screen).
-- Undo: DELETE FROM user_roles WHERE department_id = 10 AND name IN (...the names below...)
--       after removing them from every users.assign_role.

DECLARE @roles TABLE (name NVARCHAR(255), description NVARCHAR(500), level NVARCHAR(50), user_type NVARCHAR(50));
INSERT INTO @roles VALUES
    ('Cad - Registry',        'Cadastral Department: Registry desk (intake, attach/detach correspondence, send to Deeds)', 'High',    'Operations'),
    ('Cad - D Cad',           'Cadastral Department: Director Cadastral desk',                                                'Highest', 'Management'),
    ('Cad - DD Cad',          'Cadastral Department: Deputy Director Cadastral desk',                                         'Highest', 'Management'),
    ('Cad - Information',     'Cadastral Department: AD Cadastral Information desk (FMC, 1st charting)',                     'High',    'Operations'),
    ('Cad - Report',          'Cadastral Department: AD Cadastral Report desk (charting, correspondence file)',              'High',    'Operations'),
    ('Cad - P&D',             'Cadastral Department: AD Plans & Descriptions desk (jobs, surveyors, I to S, P&D bill)',      'High',    'Operations'),
    ('Cad - AD Cad',          'Cadastral Department: AD Cadastral desk',                                                      'High',    'Operations'),
    ('Cad - Verification',    'Cadastral Department: Verification logs and file status pages',                               'High',    'Operations'),
    ('Cad - Digital Archive', 'Cadastral: File Digital Library - Doc-WARE and Digital Archive (file tracker)',                'High',    'Operations');

INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT r.name, 'web', r.description, 10, r.level, r.user_type, 1, GETDATE(), GETDATE()
FROM @roles r
WHERE NOT EXISTS (SELECT 1 FROM user_roles u WHERE u.name = r.name);
