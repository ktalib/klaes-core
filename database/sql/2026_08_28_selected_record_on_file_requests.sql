-- SQL Server (sqlsrv) — the tables live here.
-- Records WHICH physical file a Quick Search duplicate-selection request asked
-- for; the shared file number cannot identify it on its own.
IF COL_LENGTH('digital_file_tracking_requests', 'selected_record_id') IS NULL
    ALTER TABLE digital_file_tracking_requests ADD selected_record_id BIGINT NULL;
GO
IF COL_LENGTH('digital_file_tracking_requests', 'selected_record_source') IS NULL
    ALTER TABLE digital_file_tracking_requests ADD selected_record_source NVARCHAR(32) NULL;
GO
