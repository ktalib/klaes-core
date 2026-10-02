/* ============================================================================
   Add st_file_numbers.allocation_source / .allocation_entity / .allocation_ref_no
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_18_100000_add_allocation_fields_to_st_file_numbers_table.php

   Allocation Information moved off the Standalone Unit Application form and onto
   the SuA File Number Commissioning form: it is answered once, at commissioning,
   and back-filled into the subapplication form when the SuA file number is
   selected. st_file_numbers is the record that exists at commissioning time, so
   the three answers are kept there.

   COLUMNS
     allocation_source nvarchar(100) NULL  -- 'State Government' | 'Local Government'
     allocation_entity nvarchar(100) NULL  -- KSIP/HOUSING/KUNPDA, or an LGA name
     allocation_ref_no nvarchar(100) NULL  -- e.g. ALS/2025/001
     (Laravel's sqlsrv grammar cannot position a column; they land at the end,
      same as this file.)

   SAFETY
     - Re-runnable: each ALTER is guarded by COL_LENGTH.
     - Adding NULL columns with no default is a metadata-only change; it does not
       rewrite existing rows or hold a long lock.
     - Existing SuA files stay NULL, which reads as "commissioned before this
       change" — their subapplication form falls back to the values already on
       the subapplication row.
     - Wrapped in a transaction, left open for review as per house convention.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MySQL:
        database/sql/2026_08_18_add_allocation_fields_to_st_file_numbers_ledger.mysql.sql
        (the migrations ledger lives in MySQL, not here — see that file.)
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* ---------------------------------------------------------------------------
   STEP 0 — Preview.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: current state ===';

SELECT
    CASE WHEN COL_LENGTH('dbo.st_file_numbers', 'allocation_source') IS NULL
         THEN 'allocation_source does NOT exist - it will be added'
         ELSE 'allocation_source already exists - nothing to do' END AS allocation_source_state,
    CASE WHEN COL_LENGTH('dbo.st_file_numbers', 'allocation_entity') IS NULL
         THEN 'allocation_entity does NOT exist - it will be added'
         ELSE 'allocation_entity already exists - nothing to do' END AS allocation_entity_state,
    CASE WHEN COL_LENGTH('dbo.st_file_numbers', 'allocation_ref_no') IS NULL
         THEN 'allocation_ref_no does NOT exist - it will be added'
         ELSE 'allocation_ref_no already exists - nothing to do' END AS allocation_ref_no_state;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the columns.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add columns ===';

IF COL_LENGTH('dbo.st_file_numbers', 'allocation_source') IS NULL
BEGIN
    /* 'State Government' | 'Local Government' */
    ALTER TABLE dbo.st_file_numbers ADD allocation_source nvarchar(100) NULL;
    PRINT '  allocation_source added.';
END
ELSE
    PRINT '  allocation_source already present - skipped.';

IF COL_LENGTH('dbo.st_file_numbers', 'allocation_entity') IS NULL
BEGIN
    /* KSIP/HOUSING/KUNPDA for State Government, an LGA name otherwise */
    ALTER TABLE dbo.st_file_numbers ADD allocation_entity nvarchar(100) NULL;
    PRINT '  allocation_entity added.';
END
ELSE
    PRINT '  allocation_entity already present - skipped.';

IF COL_LENGTH('dbo.st_file_numbers', 'allocation_ref_no') IS NULL
BEGIN
    /* e.g. ALS/2025/001 */
    ALTER TABLE dbo.st_file_numbers ADD allocation_ref_no nvarchar(100) NULL;
    PRINT '  allocation_ref_no added.';
END
ELSE
    PRINT '  allocation_ref_no already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.st_file_numbers')
  AND c.name IN ('allocation_source', 'allocation_entity', 'allocation_ref_no')
ORDER BY c.name;

/* Expected: three rows, nvarchar, max_length 200 (= 100 chars), is_nullable 1.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
