/* ============================================================================
   Duplex Parcel Update - READ-ONLY verifier (SQL SERVER)

   Emits one PASS/FAIL row per object the schema script is supposed to create,
   naming the missing object and what breaks without it. Changes nothing.

   Run against the SQL Server 'klas' database after applying
   2026_08_19_create_duplex_parcel_update_tables.sql.

   Note: never verify a schema change by asking the migrations ledger - the ledger
   can say "done" while the DDL never landed. This checks the catalog directly.
   ============================================================================ */

SET NOCOUNT ON;

;WITH checks AS (

    SELECT 1 AS ord,
           'TABLE duplex_parcel_updates' AS object_name,
           CASE WHEN OBJECT_ID('dbo.duplex_parcel_updates','U') IS NOT NULL THEN 'PASS' ELSE 'FAIL' END AS result,
           'No duplex can be created at all - the page 500s on load.' AS consequence_if_missing

    UNION ALL SELECT 2,
           'TABLE duplex_parcel_update_stages',
           CASE WHEN OBJECT_ID('dbo.duplex_parcel_update_stages','U') IS NOT NULL THEN 'PASS' ELSE 'FAIL' END,
           'Stages cannot be stored - the wizard fails after Step 2.'

    UNION ALL SELECT 3,
           'TABLE duplex_parcel_update_files',
           CASE WHEN OBJECT_ID('dbo.duplex_parcel_update_files','U') IS NOT NULL THEN 'PASS' ELSE 'FAIL' END,
           'Holding numbers cannot be minted - no stage can be saved and nothing can be commissioned.'

    UNION ALL SELECT 4,
           'UNIQUE INDEX UX_duplex_parcel_updates_duplex_id',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_duplex_parcel_updates_duplex_id') THEN 'PASS' ELSE 'FAIL' END,
           'Two duplexes could share one DPX id, so holding numbers would collide across them.'

    UNION ALL SELECT 5,
           'UNIQUE INDEX UX_duplex_stage_rank',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_duplex_stage_rank') THEN 'PASS' ELSE 'FAIL' END,
           'Two stages could share a rank - the runner could not tell which stage feeds which, and the commit order would be undefined.'

    UNION ALL SELECT 6,
           'INDEX IX_duplex_parcel_updates_status',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_duplex_parcel_updates_status') THEN 'PASS' ELSE 'FAIL' END,
           'Register listing and the six stat tiles scan the whole table.'

    UNION ALL SELECT 7,
           'INDEX IX_duplex_files_duplex',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_duplex_files_duplex') THEN 'PASS' ELSE 'FAIL' END,
           'Loading a duplex scans every file row ever created.'

    UNION ALL SELECT 8,
           'INDEX IX_duplex_files_holding_no',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_duplex_files_holding_no') THEN 'PASS' ELSE 'FAIL' END,
           'Holding-number allocation and the pre-commit registry guard both slow to a table scan.'

    /* Columns the commit path reads by name - a table that exists with the wrong
       shape fails just as hard as a missing one. */
    UNION ALL SELECT 9,
           'COLUMN duplex_parcel_update_stages.rank',
           CASE WHEN COL_LENGTH('dbo.duplex_parcel_update_stages','rank') IS NOT NULL THEN 'PASS' ELSE 'FAIL' END,
           'Execution order is lost - the commit would run stages in an arbitrary order.'

    UNION ALL SELECT 10,
           'COLUMN duplex_parcel_update_stages.payload',
           CASE WHEN COL_LENGTH('dbo.duplex_parcel_update_stages','payload') IS NOT NULL THEN 'PASS' ELSE 'FAIL' END,
           'Plot sizes, holders and the new land use are dropped - stages commission with empty data.'

    UNION ALL SELECT 11,
           'COLUMN duplex_parcel_update_files.holding_no',
           CASE WHEN COL_LENGTH('dbo.duplex_parcel_update_files','holding_no') IS NOT NULL THEN 'PASS' ELSE 'FAIL' END,
           'The whole holding-number mechanism is gone - stages cannot chain.'

    UNION ALL SELECT 12,
           'COLUMN duplex_parcel_update_files.final_file_no',
           CASE WHEN COL_LENGTH('dbo.duplex_parcel_update_files','final_file_no') IS NOT NULL THEN 'PASS' ELSE 'FAIL' END,
           'Commissioned file numbers are not recorded against their holding - the Land screen and the conveyance show blanks.'
)
SELECT object_name, result, consequence_if_missing
FROM checks
ORDER BY CASE result WHEN 'FAIL' THEN 0 ELSE 1 END, ord;

/* Overall verdict */
SELECT CASE WHEN
    OBJECT_ID('dbo.duplex_parcel_updates','U') IS NOT NULL
    AND OBJECT_ID('dbo.duplex_parcel_update_stages','U') IS NOT NULL
    AND OBJECT_ID('dbo.duplex_parcel_update_files','U') IS NOT NULL
    AND EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_duplex_parcel_updates_duplex_id')
    AND EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_duplex_stage_rank')
    AND EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_duplex_parcel_updates_status')
    AND EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_duplex_files_duplex')
    AND EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_duplex_files_holding_no')
    AND COL_LENGTH('dbo.duplex_parcel_update_stages','rank') IS NOT NULL
    AND COL_LENGTH('dbo.duplex_parcel_update_stages','payload') IS NOT NULL
    AND COL_LENGTH('dbo.duplex_parcel_update_files','holding_no') IS NOT NULL
    AND COL_LENGTH('dbo.duplex_parcel_update_files','final_file_no') IS NOT NULL
    THEN 'ALL CHECKS PASSED' ELSE 'ONE OR MORE CHECKS FAILED - see the rows above' END AS verdict;
