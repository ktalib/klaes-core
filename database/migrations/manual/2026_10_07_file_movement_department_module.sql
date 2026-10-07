-- File Movement (Department) — rename the module registered as "Secretariat File Log"
-- by 2026_10_07_secretariat_file_log_module.sql (user_roles id 10112).
-- Target: sqlsrv (klas @ 10.50.1.1). Run 2026-10-07; no user held the old name.

UPDATE user_roles
SET name = 'File Movement (Department)',
    description = 'File Tracking: in/out file register for the HC, PS and Directors'' offices — scan any KLAES QR or log manually, receive, and track to the next office',
    updated_at = GETDATE()
WHERE name = 'Secretariat File Log';
