/* ============================================================================
   Allocation List — capture of existing allocations
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_11_150000_add_existing_allocation_fields_to_
     allocation_list_stage.php

   The Allocation List form was re-designed to capture allocations that ALREADY
   exist: the operator selects a file number, the file title and location are
   backfilled from the registries, and the year is read out of the file number
   itself (RES-1982-2081 -> 1982). The table only had name columns, so it has
   nowhere to put any of that.

   COLUMNS ADDED (all NULL, no defaults)
     file_no          nvarchar(100)  the file number the allocation carries
     file_title       nvarchar(255)  backfilled from fileNumber/file_indexings
     allottee_name    nvarchar(255)  the single free-text "Name" field
     location         nvarchar(255)  backfilled district/LGA
     allocation_year  nvarchar(10)   detected from file_no, operator-editable
                                     when the number carries no year

   COLUMNS ALTERED
     first_name, last_name -> nvarchar(100) NULL.
     The new form captures one "Name". It is still split into these columns on a
     best-effort basis (so the MLS generator keeps reading these rows), but a
     single-token name ("DANGOTE") has no surname to store.

   INDEX ADDED
     IX_allocation_list_stage_file_no on (file_no) — the list is looked up by
     file number once existing allocations land in it.

   SAFETY
     - Re-runnable: every step is guarded (COL_LENGTH / sys.indexes / is_nullable).
     - Adding NULL columns with no default is metadata-only; it does not rewrite
       the rows or hold a long lock.
     - Widening NOT NULL -> NULL on an nvarchar(100) is also metadata-only.
     - Existing rows keep NULL in every new column. Nothing reads them as
       required, so no backfill is needed — the pre-existing name-only rows
       simply render "—" for FileNo/Location/Year in the list.
     - Wrapped in a transaction, left open for review as per house convention.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 3, COMMIT.
     2. Run the companion ledger file against MySQL:
        database/sql/2026_08_11_add_existing_allocation_fields_to_allocation_list_stage_ledger.mysql.sql
        (the migrations ledger lives in MySQL, not here — see that file.)

   VERIFIED
     The migration it mirrors ran against the working DB on 2026-08-11 (102 rows
     in the table, none lost). A capture round-tripped through
     AllocationListEntryController::storeExisting(): CON-COM-2026-484 came back
     with file_title 'HAJIA VON SALISU', location 'ABBA ABDULLAHI AV, FAGGE' and
     allocation_year '2026', with first/middle/last split from the name.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* ---------------------------------------------------------------------------
   STEP 0 — Preview.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: current state ===';

SELECT
    CASE WHEN COL_LENGTH('dbo.allocation_list_stage', 'file_no')         IS NULL THEN 'missing' ELSE 'present' END AS file_no,
    CASE WHEN COL_LENGTH('dbo.allocation_list_stage', 'file_title')      IS NULL THEN 'missing' ELSE 'present' END AS file_title,
    CASE WHEN COL_LENGTH('dbo.allocation_list_stage', 'allottee_name')   IS NULL THEN 'missing' ELSE 'present' END AS allottee_name,
    CASE WHEN COL_LENGTH('dbo.allocation_list_stage', 'location')        IS NULL THEN 'missing' ELSE 'present' END AS location,
    CASE WHEN COL_LENGTH('dbo.allocation_list_stage', 'allocation_year') IS NULL THEN 'missing' ELSE 'present' END AS allocation_year,
    (SELECT COUNT(*) FROM dbo.allocation_list_stage) AS existing_rows;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the columns.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add columns ===';

IF COL_LENGTH('dbo.allocation_list_stage', 'file_no') IS NULL
BEGIN
    ALTER TABLE dbo.allocation_list_stage ADD file_no nvarchar(100) NULL;
    PRINT '  file_no added.';
END
ELSE PRINT '  file_no already present - skipped.';

IF COL_LENGTH('dbo.allocation_list_stage', 'file_title') IS NULL
BEGIN
    ALTER TABLE dbo.allocation_list_stage ADD file_title nvarchar(255) NULL;
    PRINT '  file_title added.';
END
ELSE PRINT '  file_title already present - skipped.';

IF COL_LENGTH('dbo.allocation_list_stage', 'allottee_name') IS NULL
BEGIN
    ALTER TABLE dbo.allocation_list_stage ADD allottee_name nvarchar(255) NULL;
    PRINT '  allottee_name added.';
END
ELSE PRINT '  allottee_name already present - skipped.';

IF COL_LENGTH('dbo.allocation_list_stage', 'location') IS NULL
BEGIN
    ALTER TABLE dbo.allocation_list_stage ADD location nvarchar(255) NULL;
    PRINT '  location added.';
END
ELSE PRINT '  location already present - skipped.';

IF COL_LENGTH('dbo.allocation_list_stage', 'allocation_year') IS NULL
BEGIN
    /* Detected from file_no; text rather than an int the UI has to police. */
    ALTER TABLE dbo.allocation_list_stage ADD allocation_year nvarchar(10) NULL;
    PRINT '  allocation_year added.';
END
ELSE PRINT '  allocation_year already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Relax the legacy name columns and index file_no.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: relax name columns, add index ===';

IF EXISTS (SELECT 1 FROM sys.columns
            WHERE object_id = OBJECT_ID('dbo.allocation_list_stage')
              AND name = 'first_name' AND is_nullable = 0)
BEGIN
    ALTER TABLE dbo.allocation_list_stage ALTER COLUMN first_name nvarchar(100) NULL;
    PRINT '  first_name is now NULL-able.';
END
ELSE PRINT '  first_name already NULL-able - skipped.';

IF EXISTS (SELECT 1 FROM sys.columns
            WHERE object_id = OBJECT_ID('dbo.allocation_list_stage')
              AND name = 'last_name' AND is_nullable = 0)
BEGIN
    ALTER TABLE dbo.allocation_list_stage ALTER COLUMN last_name nvarchar(100) NULL;
    PRINT '  last_name is now NULL-able.';
END
ELSE PRINT '  last_name already NULL-able - skipped.';

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'IX_allocation_list_stage_file_no'
                  AND object_id = OBJECT_ID('dbo.allocation_list_stage'))
BEGIN
    CREATE INDEX IX_allocation_list_stage_file_no
        ON dbo.allocation_list_stage (file_no);
    PRINT '  IX_allocation_list_stage_file_no created.';
END
ELSE PRINT '  IX_allocation_list_stage_file_no already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 3 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 3: result ===';

SELECT c.name, t.name AS type, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.allocation_list_stage')
  AND c.name IN ('file_no','file_title','allottee_name','location','allocation_year',
                 'first_name','last_name')
ORDER BY c.name;

SELECT name AS index_name
FROM sys.indexes
WHERE object_id = OBJECT_ID('dbo.allocation_list_stage')
  AND name = 'IX_allocation_list_stage_file_no';

SELECT COUNT(*) AS row_count FROM dbo.allocation_list_stage;

/* Expected:
     7 column rows — the 5 new ones NULL-able, first_name/last_name is_nullable 1
       (max_length is in bytes: 200 = 100 chars, 510 = 255 chars, 20 = 10 chars)
     1 index row
     row_count unchanged from STEP 0
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 3, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
