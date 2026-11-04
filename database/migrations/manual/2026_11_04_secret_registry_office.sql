-- sqlsrv (klas): add the Secret Registry office under Admin and General Services Department.
-- offices = Office dropdowns (File Movement, File Tracker, Digital File Request ...); office.department names its department.
-- Undo: DELETE FROM offices WHERE office_code = 'SR' AND office_name = 'Secret Registry';
IF NOT EXISTS (SELECT 1 FROM offices WHERE office_code = 'SR' OR UPPER(LTRIM(RTRIM(office_name))) = 'SECRET REGISTRY')
    INSERT INTO offices (office_code, office_name, office_abbreviation, department, is_active, created_at, updated_at)
    VALUES ('SR', 'Secret Registry', 'SR', 'Admin and General Services Department', 1, GETDATE(), GETDATE());
