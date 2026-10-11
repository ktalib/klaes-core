-- SLTR/First Registration > SLTR Indexing — register the module so it can be granted.
-- Target: sqlsrv (klas @ 10.50.1.1). Idempotent: inserts only if missing.
-- The name matches the sidebar gate in admin/menu/partials/modules/sltr.blade.php.
-- Grant it per user by adding "SLTR - Indexing" to users.assign_role (User Account screen).

IF NOT EXISTS (SELECT 1 FROM user_roles WHERE name = 'SLTR - Indexing')
BEGIN
    INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
    VALUES (
        'SLTR - Indexing',
        'web',
        'SLTR - Indexing: File Indexing Assistant, File History View and File SerialNo Grouping',
        12,
        'High',
        'Operations',
        1,
        GETDATE(),
        GETDATE()
    );
END;
