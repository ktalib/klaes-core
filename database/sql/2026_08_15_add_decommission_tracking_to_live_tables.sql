/* ============================================================================
   Decommissioning becomes a flag the live tables carry, not a delete
   — SQL SERVER schema change
   ----------------------------------------------------------------------------
   RUN THIS FIRST, against SQL SERVER (`klas`). Then run the companion
   database/sql/2026_08_15_add_decommission_tracking_to_live_tables_ledger.mysql.sql
   against MYSQL to register the migration.

   WHY
   PlotWorkflowService::decommissionFiles() used to archive a file into
   decommissioned_files + deprecated_records and then HARD DELETE its rows from
   fileNumber, file_indexings, customers_staging, entities_staging and
   kangis_grouping. The archive was the only surviving copy, so a decommissioned
   file permanently lost its indexing detail, its customer/entity parties and its
   grouping provenance — and every screen "knew" the file was decommissioned only
   because its row had vanished.

   Nothing is deleted any more. The rows stay, flagged, and each table becomes
   self-describing: the decommission state is readable without joining back to
   decommissioned_files (which is still written and remains the registry).

   COLUMNS ADDED (to each of the five tables)
     is_decommissioned      0/1 — the flag read paths filter or badge on
     decommissioned_at      when it happened
     decommissioned_by      who did it (display name, matching decommissioned_files)
     decommissioning_reason the workflow that caused it (Merger / Subdivision / ...)
     successor_file_no      the file that replaced it, for lineage without a join

   fileNumber already carries is_decommissioned + decommissioning_date +
   decommissioning_reason from an earlier change, so only its missing columns are
   added. The service keeps decommissioning_date in step with decommissioned_at so
   the existing File Decommissioning screen keeps working unchanged.

   The index is FILTERED on 1: decommissioned rows are the rare minority, so the
   index stays small while still serving "show me the decommissioned ones".

   SAFETY
     - Re-runnable: every ALTER is guarded by COL_LENGTH, every index by sys.indexes.
     - Additive only; no existing column, constraint or row is modified.
     - Deploy the application code at the same time. The code is written to tolerate
       the columns being absent (it checks hasColumn before writing each one), so
       running this script early is harmless — but until it runs, decommissioning
       still cannot record who/why on the live rows.
   ============================================================================ */

/* Preview — expect 0 for everything except the three fileNumber columns that
   already exist (is_decommissioned, decommissioning_date, decommissioning_reason) */
SELECT
    CASE WHEN COL_LENGTH('dbo.fileNumber',        'is_decommissioned') IS NULL THEN 0 ELSE 1 END AS fileNumber_flag,
    CASE WHEN COL_LENGTH('dbo.file_indexings',    'is_decommissioned') IS NULL THEN 0 ELSE 1 END AS file_indexings_flag,
    CASE WHEN COL_LENGTH('dbo.customers_staging', 'is_decommissioned') IS NULL THEN 0 ELSE 1 END AS customers_flag,
    CASE WHEN COL_LENGTH('dbo.entities_staging',  'is_decommissioned') IS NULL THEN 0 ELSE 1 END AS entities_flag,
    CASE WHEN COL_LENGTH('dbo.kangis_grouping',   'is_decommissioned') IS NULL THEN 0 ELSE 1 END AS kangis_grouping_flag;
GO

/* ---------------------------------------------------------------- fileNumber */
IF COL_LENGTH('dbo.fileNumber', 'is_decommissioned') IS NULL
    ALTER TABLE dbo.fileNumber ADD is_decommissioned TINYINT NOT NULL DEFAULT 0;
GO
IF COL_LENGTH('dbo.fileNumber', 'decommissioned_at') IS NULL
    ALTER TABLE dbo.fileNumber ADD decommissioned_at DATETIME NULL;
GO
IF COL_LENGTH('dbo.fileNumber', 'decommissioned_by') IS NULL
    ALTER TABLE dbo.fileNumber ADD decommissioned_by NVARCHAR(255) NULL;
GO
IF COL_LENGTH('dbo.fileNumber', 'decommissioning_reason') IS NULL
    ALTER TABLE dbo.fileNumber ADD decommissioning_reason NVARCHAR(MAX) NULL;
GO
IF COL_LENGTH('dbo.fileNumber', 'successor_file_no') IS NULL
    ALTER TABLE dbo.fileNumber ADD successor_file_no NVARCHAR(MAX) NULL;
GO

/* ------------------------------------------------------------ file_indexings */
IF COL_LENGTH('dbo.file_indexings', 'is_decommissioned') IS NULL
    ALTER TABLE dbo.file_indexings ADD is_decommissioned TINYINT NOT NULL DEFAULT 0;
GO
IF COL_LENGTH('dbo.file_indexings', 'decommissioned_at') IS NULL
    ALTER TABLE dbo.file_indexings ADD decommissioned_at DATETIME NULL;
GO
IF COL_LENGTH('dbo.file_indexings', 'decommissioned_by') IS NULL
    ALTER TABLE dbo.file_indexings ADD decommissioned_by NVARCHAR(255) NULL;
GO
IF COL_LENGTH('dbo.file_indexings', 'decommissioning_reason') IS NULL
    ALTER TABLE dbo.file_indexings ADD decommissioning_reason NVARCHAR(MAX) NULL;
GO
IF COL_LENGTH('dbo.file_indexings', 'successor_file_no') IS NULL
    ALTER TABLE dbo.file_indexings ADD successor_file_no NVARCHAR(MAX) NULL;
GO

/* --------------------------------------------------------- customers_staging */
IF COL_LENGTH('dbo.customers_staging', 'is_decommissioned') IS NULL
    ALTER TABLE dbo.customers_staging ADD is_decommissioned TINYINT NOT NULL DEFAULT 0;
GO
IF COL_LENGTH('dbo.customers_staging', 'decommissioned_at') IS NULL
    ALTER TABLE dbo.customers_staging ADD decommissioned_at DATETIME NULL;
GO
IF COL_LENGTH('dbo.customers_staging', 'decommissioned_by') IS NULL
    ALTER TABLE dbo.customers_staging ADD decommissioned_by NVARCHAR(255) NULL;
GO
IF COL_LENGTH('dbo.customers_staging', 'decommissioning_reason') IS NULL
    ALTER TABLE dbo.customers_staging ADD decommissioning_reason NVARCHAR(MAX) NULL;
GO
IF COL_LENGTH('dbo.customers_staging', 'successor_file_no') IS NULL
    ALTER TABLE dbo.customers_staging ADD successor_file_no NVARCHAR(MAX) NULL;
GO

/* ---------------------------------------------------------- entities_staging */
IF COL_LENGTH('dbo.entities_staging', 'is_decommissioned') IS NULL
    ALTER TABLE dbo.entities_staging ADD is_decommissioned TINYINT NOT NULL DEFAULT 0;
GO
IF COL_LENGTH('dbo.entities_staging', 'decommissioned_at') IS NULL
    ALTER TABLE dbo.entities_staging ADD decommissioned_at DATETIME NULL;
GO
IF COL_LENGTH('dbo.entities_staging', 'decommissioned_by') IS NULL
    ALTER TABLE dbo.entities_staging ADD decommissioned_by NVARCHAR(255) NULL;
GO
IF COL_LENGTH('dbo.entities_staging', 'decommissioning_reason') IS NULL
    ALTER TABLE dbo.entities_staging ADD decommissioning_reason NVARCHAR(MAX) NULL;
GO
IF COL_LENGTH('dbo.entities_staging', 'successor_file_no') IS NULL
    ALTER TABLE dbo.entities_staging ADD successor_file_no NVARCHAR(MAX) NULL;
GO

/* ------------------------------------------------------------ kangis_grouping */
/* Grouping rows are flagged, never deleted: removing any row from a grouping
   table during decommissioning is forbidden — the grouping record is the file's
   provenance and outlives the file's active life. */
IF COL_LENGTH('dbo.kangis_grouping', 'is_decommissioned') IS NULL
    ALTER TABLE dbo.kangis_grouping ADD is_decommissioned TINYINT NOT NULL DEFAULT 0;
GO
IF COL_LENGTH('dbo.kangis_grouping', 'decommissioned_at') IS NULL
    ALTER TABLE dbo.kangis_grouping ADD decommissioned_at DATETIME NULL;
GO
IF COL_LENGTH('dbo.kangis_grouping', 'decommissioned_by') IS NULL
    ALTER TABLE dbo.kangis_grouping ADD decommissioned_by NVARCHAR(255) NULL;
GO
IF COL_LENGTH('dbo.kangis_grouping', 'decommissioning_reason') IS NULL
    ALTER TABLE dbo.kangis_grouping ADD decommissioning_reason NVARCHAR(MAX) NULL;
GO
IF COL_LENGTH('dbo.kangis_grouping', 'successor_file_no') IS NULL
    ALTER TABLE dbo.kangis_grouping ADD successor_file_no NVARCHAR(MAX) NULL;
GO

/* ------------------------------------------------------------------- indexes */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'ix_fileNumber_is_decommissioned' AND object_id = OBJECT_ID('dbo.fileNumber'))
    CREATE NONCLUSTERED INDEX ix_fileNumber_is_decommissioned
        ON dbo.fileNumber(is_decommissioned) WHERE is_decommissioned = 1;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'ix_file_indexings_is_decommissioned' AND object_id = OBJECT_ID('dbo.file_indexings'))
    CREATE NONCLUSTERED INDEX ix_file_indexings_is_decommissioned
        ON dbo.file_indexings(is_decommissioned) WHERE is_decommissioned = 1;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'ix_customers_staging_is_decommissioned' AND object_id = OBJECT_ID('dbo.customers_staging'))
    CREATE NONCLUSTERED INDEX ix_customers_staging_is_decommissioned
        ON dbo.customers_staging(is_decommissioned) WHERE is_decommissioned = 1;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'ix_entities_staging_is_decommissioned' AND object_id = OBJECT_ID('dbo.entities_staging'))
    CREATE NONCLUSTERED INDEX ix_entities_staging_is_decommissioned
        ON dbo.entities_staging(is_decommissioned) WHERE is_decommissioned = 1;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'ix_kangis_grouping_is_decommissioned' AND object_id = OBJECT_ID('dbo.kangis_grouping'))
    CREATE NONCLUSTERED INDEX ix_kangis_grouping_is_decommissioned
        ON dbo.kangis_grouping(is_decommissioned) WHERE is_decommissioned = 1;
GO

/* Verify — expect 5 columns on every table, and 5 indexes */
SELECT t.name AS table_name, COUNT(c.name) AS decommission_columns
  FROM sys.tables t
  JOIN sys.columns c ON c.object_id = t.object_id
 WHERE t.name IN ('fileNumber','file_indexings','customers_staging','entities_staging','kangis_grouping')
   AND c.name IN ('is_decommissioned','decommissioned_at','decommissioned_by','decommissioning_reason','successor_file_no')
 GROUP BY t.name
 ORDER BY t.name;

SELECT COUNT(*) AS decommission_indexes
  FROM sys.indexes
 WHERE name LIKE 'ix_%_is_decommissioned';
