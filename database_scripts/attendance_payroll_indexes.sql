/*
    Manual Attendance & Payroll Performance Indexes
    ------------------------------------------------
    Apply on SQL Server before deploying application changes.
    Run from SQL Server Management Studio (SSMS) or sqlcmd while
    connected to the primary KLAS database. All statements are
    idempotent and guarded, so the script can be executed multiple
    times safely.
*/
GO

/* Users lookup improvements for manual attendance staff discovery */
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_users_manual_attendance_lookup'
      AND object_id = OBJECT_ID(N'dbo.users')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_users_manual_attendance_lookup
        ON dbo.users (
            is_active,
            is_pc_access,
            staff_type_category,
            department_id,
            work_station,
            shift_code
        )
        INCLUDE (
            first_name,
            last_name,
            username,
            email,
            man_hours_per_day,
            user_level,
            passport_photo_path,
            profile
        )
        WITH (FILLFACTOR = 90, DATA_COMPRESSION = ROW);
END;
GO

/* Manual attendance entries filtered by attendance date */
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_manual_attendance_entries_attendance_date_user'
      AND object_id = OBJECT_ID(N'dbo.manual_attendance_entries')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_manual_attendance_entries_attendance_date_user
        ON dbo.manual_attendance_entries (
            attendance_date,
            user_id
        )
        INCLUDE (
            batch_id,
            is_present,
            check_in_time,
            check_out_time,
            shift_code,
            login_hours,
            overtime_hours,
            status,
            notes
        )
        WITH (FILLFACTOR = 90, DATA_COMPRESSION = ROW);
END;
GO

/* Manual attendance entries grouped by user and ordered by date */
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_manual_attendance_entries_user_date'
      AND object_id = OBJECT_ID(N'dbo.manual_attendance_entries')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_manual_attendance_entries_user_date
        ON dbo.manual_attendance_entries (
            user_id,
            attendance_date
        )
        INCLUDE (
            batch_id,
            is_present,
            check_in_time,
            check_out_time,
            shift_code,
            login_hours,
            overtime_hours,
            status,
            notes
        )
        WITH (FILLFACTOR = 90, DATA_COMPRESSION = ROW);
END;
GO

/* Batch resolution for getOrCreateBatch calls */
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_manual_attendance_batches_period_window'
      AND object_id = OBJECT_ID(N'dbo.manual_attendance_batches')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_manual_attendance_batches_period_window
        ON dbo.manual_attendance_batches (period_start, period_end)
        INCLUDE (status, submitted_by, submitted_at)
        WITH (FILLFACTOR = 90, DATA_COMPRESSION = ROW);
END;
GO

/* Payroll attendance lookups per period & user */
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_payroll_attendance_period_user'
      AND object_id = OBJECT_ID(N'dbo.payroll_attendance')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_payroll_attendance_period_user
        ON dbo.payroll_attendance (period_id, user_id)
        INCLUDE (
            department_id,
            login_days,
            hours_worked,
            overtime_hours,
            source_reference
        )
        WITH (FILLFACTOR = 90, DATA_COMPRESSION = ROW);
END;
GO

/*
    Post-deployment checklist
    -------------------------
    1. Update table statistics: EXEC sp_updatestats;
    2. Review index impact with sys.dm_db_index_usage_stats after a week;
    3. Capture Query Store baselines for manual attendance and payroll pages.
*/
