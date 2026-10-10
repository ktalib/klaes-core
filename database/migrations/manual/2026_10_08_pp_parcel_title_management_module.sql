-- Physical Planning > Parcel/Title Management — register the module so it can be granted.
-- Target: sqlsrv (klas @ 10.50.1.1). Idempotent: inserts only if missing.
-- Named to match the Lands / Deeds siblings ("Parcel/Title Management-Land", "-Deeds").
-- Grant it per user by adding "Parcel/Title Management-PP" to users.assign_role
-- (User Account screen). The older "Master JSI" role still opens the same group.

IF NOT EXISTS (SELECT 1 FROM user_roles WHERE name = 'Parcel/Title Management-PP')
BEGIN
    INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
    VALUES (
        'Parcel/Title Management-PP',
        'web',
        'Physical Planning - Parcel/Title Management: Master JSI capture and register',
        9,
        'High',
        'Operations',
        1,
        GETDATE(),
        GETDATE()
    );
END;
