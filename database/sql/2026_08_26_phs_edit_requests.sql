-- PHS edit-request / free-re-run authorisation table (SQL Server).
--
-- Paired with database/migrations/2026_08_26_170000_create_phs_edit_requests_table.php.
-- Run this on the sqlsrv box: artisan's migrations ledger lives in MySQL, so the
-- migration may be recorded as "run" without this table ever being created.
--
-- Idempotent: safe to run more than once.

IF OBJECT_ID('dbo.phs_edit_requests', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.phs_edit_requests (
        id                    BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,

        phs_institution_id    BIGINT           NULL,
        phs_member_id         BIGINT           NULL,
        requester_name        NVARCHAR(190)    NULL,
        requester_email       NVARCHAR(190)    NULL,

        search_log_id         BIGINT           NULL,
        reference_no          NVARCHAR(60)     NULL,
        file_number           NVARCHAR(100)    NULL,
        reason_category       NVARCHAR(40)     NULL,
        reason                NVARCHAR(MAX)    NULL,
        original_result       NVARCHAR(MAX)    NULL,

        status                NVARCHAR(30)     NOT NULL CONSTRAINT df_per_status DEFAULT ('edit_requested'),
        requested_at          DATETIME         NULL,

        reviewed_by           BIGINT           NULL,
        reviewer_name         NVARCHAR(190)    NULL,
        admin_response        NVARCHAR(MAX)    NULL,
        corrected_at          DATETIME         NULL,

        rerun_search_log_id   BIGINT           NULL,
        rerun_at              DATETIME         NULL,
        rerun_by              BIGINT           NULL,

        ip_address            NVARCHAR(45)     NULL,
        created_at            DATETIME         NULL,
        updated_at            DATETIME         NULL
    );

    CREATE INDEX ix_per_institution  ON dbo.phs_edit_requests (phs_institution_id);
    CREATE INDEX ix_per_member       ON dbo.phs_edit_requests (phs_member_id);
    CREATE INDEX ix_per_status       ON dbo.phs_edit_requests (status);
    CREATE INDEX ix_per_file_number  ON dbo.phs_edit_requests (file_number);
    CREATE INDEX ix_per_reference    ON dbo.phs_edit_requests (reference_no);
END
GO

-- One OPEN (unconsumed) edit request per member + file, so a member cannot bank
-- several free re-runs against the same file. Filtered, so closed/consumed rows
-- never block a legitimate new request.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'ux_per_open_per_member_file'
      AND object_id = OBJECT_ID('dbo.phs_edit_requests')
)
BEGIN
    CREATE UNIQUE INDEX ux_per_open_per_member_file
        ON dbo.phs_edit_requests (phs_member_id, file_number)
        WHERE rerun_search_log_id IS NULL
          AND status IN ('edit_requested', 'ready_for_rerun');
END
GO
