-- sqlsrv (klas): Change Request 22102026, item 3.
-- departments = Department dropdowns (User Account, Land Officer settings, Configurable Entries ...).
-- offices     = Office dropdowns (File Tracker, Digital File Request ...); office.department names its department.
IF NOT EXISTS (SELECT 1 FROM departments WHERE UPPER(LTRIM(RTRIM(name))) = 'KLAES PROJECT')
    INSERT INTO departments (name, code, description, parent_id, is_active, created_at, updated_at)
    VALUES ('KLAES Project', 'KLAES PROJECT', 'KLAES Project', NULL, 1, GETDATE(), GETDATE());

IF NOT EXISTS (SELECT 1 FROM offices WHERE UPPER(LTRIM(RTRIM(office_name))) = 'KLAES PROJECT OFFICE')
    INSERT INTO offices (office_code, office_name, office_abbreviation, department, is_active, created_at, updated_at)
    VALUES ('KPO', 'KLAES Project Office', 'KPO', 'KLAES Project', 1, GETDATE(), GETDATE());
