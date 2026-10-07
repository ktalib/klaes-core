-- Secretariat File Log (LoF) — register the module so it can be granted.
-- Target: sqlsrv (klas @ 10.50.1.1). Idempotent: inserts only if missing.
-- Grant it per user by adding "Secretariat File Log" to users.assign_role
-- (User Account screen). Super admins see the page without it.

IF NOT EXISTS (SELECT 1 FROM user_roles WHERE name = 'Secretariat File Log')
BEGIN
    INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
    VALUES (
        'Secretariat File Log',
        'web',
        'File Tracking: in/out file register for the HC, PS and Directors'' secretaries — scan any KLAES QR or log manually, receive, and track to the next office',
        13,
        'High',
        'Management',
        1,
        GETDATE(),
        GETDATE()
    );
END;
