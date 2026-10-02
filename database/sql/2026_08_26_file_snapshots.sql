/* ============================================================================
   file_snapshots — append-only audit trail of a file's captured state
   ----------------------------------------------------------------------------
   TARGET: SQL SERVER (the `klas` database on the sqlsrv connection).

   Companion to:
     database/migrations/2026_08_26_150000_create_file_snapshots_table.php
     database/sql/2026_08_26_file_snapshots_ledger.mysql.sql  <- RUN THAT SECOND

   Run this file FIRST. It creates the table; the MySQL ledger file then tells
   artisan the migration is already applied so the next deploy does not retry it.

   Re-runnable: every statement is guarded.
   ============================================================================ */

IF OBJECT_ID('dbo.file_snapshots', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.file_snapshots (
        id                  BIGINT IDENTITY(1,1) NOT NULL,

        file_indexing_id    BIGINT           NULL,

        /* Identity AS AT this version — denormalised, because the file number on
           the row itself can be corrected and the trail must still show what the
           file was called when each snapshot was taken. */
        file_number         NVARCHAR(255)    NULL,
        temp_file_no        NVARCHAR(255)    NULL,
        tracking_id         NVARCHAR(255)    NULL,
        prop_id             BIGINT           NULL,
        parent_prop_id      BIGINT           NULL,

        /* 1-based, per file_indexing_id. */
        version             INT              NOT NULL CONSTRAINT df_fsnap_version DEFAULT (1),

        /* indexed | edited | linked | transaction_added */
        event_type          NVARCHAR(40)     NOT NULL,
        event_label         NVARCHAR(255)    NULL,

        /* The snapshot, and the diff against the previous version.
           changes is NULL on version 1 — nothing to compare against. */
        payload             NVARCHAR(MAX)    NULL,
        changes             NVARCHAR(MAX)    NULL,
        changed_field_count INT              NOT NULL CONSTRAINT df_fsnap_changed DEFAULT (0),

        /* sha256 of payload. An identical hash means the save changed nothing and
           the insert is skipped, so the trail is not padded with no-op versions. */
        payload_hash        CHAR(64)         NULL,

        performed_by        BIGINT           NULL,
        performed_by_name   NVARCHAR(255)    NULL,
        performed_at        DATETIME         NULL,

        /* file_indexing.store | file_indexing.update | property_record.store_from_indexing */
        source              NVARCHAR(60)     NULL,
        ip_address          NVARCHAR(45)     NULL,
        user_agent          NVARCHAR(512)    NULL,

        created_at          DATETIME         NULL,
        updated_at          DATETIME         NULL,

        CONSTRAINT pk_file_snapshots PRIMARY KEY CLUSTERED (id)
    );
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'ix_fsnap_file_version'
                  AND object_id = OBJECT_ID('dbo.file_snapshots'))
    CREATE INDEX ix_fsnap_file_version ON dbo.file_snapshots (file_indexing_id, version);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'ix_fsnap_file_number'
                  AND object_id = OBJECT_ID('dbo.file_snapshots'))
    CREATE INDEX ix_fsnap_file_number ON dbo.file_snapshots (file_number);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'ix_fsnap_event'
                  AND object_id = OBJECT_ID('dbo.file_snapshots'))
    CREATE INDEX ix_fsnap_event ON dbo.file_snapshots (event_type, performed_at);
GO

/* Verify */
SELECT COUNT(*) AS column_count
  FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_NAME = 'file_snapshots';   /* expect 22 */

SELECT name FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.file_snapshots');
GO
