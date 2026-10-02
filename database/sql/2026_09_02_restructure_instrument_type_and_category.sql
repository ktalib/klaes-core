/* ============================================================================
   One Type and one Category per captured instrument
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_09_02_120000_restructure_instrument_type_and_category.php

   RUN THE INSTRUMENT TYPE SCRIPT FIRST:
     database/sql/2026_09_02_add_plot_allocation_letter_instrument_type.sql

   THE MODEL
     Instrument Type           Type                                 | Category
     ------------------------  -----------------------------------  | --------------
     Plot Allocation Letter    Land, LGA, Urban Development Board   | —
     Occupancy Permit          Resettlement, Direct Allocation, LGA | Old, New
     Certificate of Occupancy  Land, Old KANGIS, New KANGIS, LGA    | Old, New
     Right of Occupancy        Land, LGA                            | Old, New

   app/Services/InstrumentTypeCatalog.php holds that table and is its only copy.

   WHAT THIS ADDS
     instrument_category  nvarchar(100) NULL   the Category answer, every instrument
     instrument_subtype   nvarchar(100) NULL   the Type answer, ONLY for instruments
                                               with no column of their own — Right of
                                               Occupancy and Plot Allocation Letter

   Type otherwise keeps the column its instrument has always used, so nothing moves
   and no reader downstream changes:
     op_type    Occupancy Permit          ~34,000 rows
     cofo_type  Certificate of Occupancy  ~15,500 rows

   WHAT THIS DROPS
     pra.op_category — added earlier the same day for an Old OP / New OP question on
     Occupancy Permits alone. Category is now a question for three instruments and is
     answered by instrument_category instead. op_category never held a row, so
     nothing is lost. If this database never received that column, the DROP is
     skipped. Its now-superseded scripts have been removed from database/sql.

   WHY FOUR TABLES
   A capture screen picks its destination from the instrument:
     file_history_staging  anything that is neither a CofO nor an Occupancy Permit —
                           the Plot Allocation Letter and Right of Occupancy
     CofO_staging          Certificate of Occupancy, and its ST/SLTR variants
     pra                   Occupancy Permits, and everything the PRA card writes
     pic                   the PRA card's target in record_mode=index

   SAFETY
     - Re-runnable: every ALTER is guarded by COL_LENGTH.
     - Adding a NULL column with no default is a metadata-only change; it does not
       rewrite rows or hold a long lock, even on file_history_staging.
     - The DROP removes a column that has never been written to. Verify STEP 0
       reports 0 rows before committing.
     - NO BACKFILL. No existing row was captured with either question put, and
       neither answer can be inferred from what is stored.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MYSQL:
        database/sql/2026_09_02_restructure_instrument_type_and_category_ledger.mysql.sql
        (the migrations ledger lives in MySQL, not here — see that file.)
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* ---------------------------------------------------------------------------
   STEP 0 — Preview. The op_category row count MUST be 0 before you commit.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: current state ===';

SELECT t.name AS table_name,
       CASE WHEN COL_LENGTH('dbo.' + t.name, 'instrument_category') IS NULL
            THEN 'will be added' ELSE 'already exists' END AS instrument_category,
       CASE WHEN COL_LENGTH('dbo.' + t.name, 'instrument_subtype') IS NULL
            THEN 'will be added' ELSE 'already exists' END AS instrument_subtype
FROM (VALUES ('pra'), ('file_history_staging'), ('CofO_staging'), ('pic')) AS t(name);

IF COL_LENGTH('dbo.pra', 'op_category') IS NULL
    SELECT 'pra.op_category not present - the DROP will be skipped' AS op_category_state,
           0 AS rows_with_a_value;
ELSE
    EXEC sp_executesql N'
        SELECT ''pra.op_category present - it will be dropped'' AS op_category_state,
               COUNT(*) AS rows_with_a_value
          FROM dbo.pra
         WHERE op_category IS NOT NULL AND LTRIM(RTRIM(op_category)) <> '''';';

/* ---------------------------------------------------------------------------
   STEP 1 — Add the two columns to each capture table.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add columns ===';

IF COL_LENGTH('dbo.pra', 'instrument_category') IS NULL
BEGIN ALTER TABLE dbo.pra ADD instrument_category nvarchar(100) NULL;
      PRINT '  pra.instrument_category added.'; END
ELSE PRINT '  pra.instrument_category already present - skipped.';

IF COL_LENGTH('dbo.pra', 'instrument_subtype') IS NULL
BEGIN ALTER TABLE dbo.pra ADD instrument_subtype nvarchar(100) NULL;
      PRINT '  pra.instrument_subtype added.'; END
ELSE PRINT '  pra.instrument_subtype already present - skipped.';

IF COL_LENGTH('dbo.file_history_staging', 'instrument_category') IS NULL
BEGIN ALTER TABLE dbo.file_history_staging ADD instrument_category nvarchar(100) NULL;
      PRINT '  file_history_staging.instrument_category added.'; END
ELSE PRINT '  file_history_staging.instrument_category already present - skipped.';

IF COL_LENGTH('dbo.file_history_staging', 'instrument_subtype') IS NULL
BEGIN ALTER TABLE dbo.file_history_staging ADD instrument_subtype nvarchar(100) NULL;
      PRINT '  file_history_staging.instrument_subtype added.'; END
ELSE PRINT '  file_history_staging.instrument_subtype already present - skipped.';

IF COL_LENGTH('dbo.CofO_staging', 'instrument_category') IS NULL
BEGIN ALTER TABLE dbo.CofO_staging ADD instrument_category nvarchar(100) NULL;
      PRINT '  CofO_staging.instrument_category added.'; END
ELSE PRINT '  CofO_staging.instrument_category already present - skipped.';

IF COL_LENGTH('dbo.CofO_staging', 'instrument_subtype') IS NULL
BEGIN ALTER TABLE dbo.CofO_staging ADD instrument_subtype nvarchar(100) NULL;
      PRINT '  CofO_staging.instrument_subtype added.'; END
ELSE PRINT '  CofO_staging.instrument_subtype already present - skipped.';

IF COL_LENGTH('dbo.pic', 'instrument_category') IS NULL
BEGIN ALTER TABLE dbo.pic ADD instrument_category nvarchar(100) NULL;
      PRINT '  pic.instrument_category added.'; END
ELSE PRINT '  pic.instrument_category already present - skipped.';

IF COL_LENGTH('dbo.pic', 'instrument_subtype') IS NULL
BEGIN ALTER TABLE dbo.pic ADD instrument_subtype nvarchar(100) NULL;
      PRINT '  pic.instrument_subtype added.'; END
ELSE PRINT '  pic.instrument_subtype already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 1b — Drop the superseded pra.op_category.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1b: drop op_category ===';

IF COL_LENGTH('dbo.pra', 'op_category') IS NOT NULL
BEGIN
    ALTER TABLE dbo.pra DROP COLUMN op_category;
    PRINT '  pra.op_category dropped.';
END
ELSE PRINT '  pra.op_category not present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT OBJECT_NAME(c.object_id) AS table_name, c.name, t.name AS type,
       c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.name IN ('instrument_category', 'instrument_subtype', 'op_category')
  AND c.object_id IN (OBJECT_ID('dbo.pra'), OBJECT_ID('dbo.file_history_staging'),
                      OBJECT_ID('dbo.CofO_staging'), OBJECT_ID('dbo.pic'))
ORDER BY table_name, c.name;

/* Expected: EIGHT rows — instrument_category and instrument_subtype on each of the
   four tables, nvarchar, max_length 200 (nvarchar stores 2 bytes per character, so
   100 characters = 200), is_nullable 1 — and NO op_category row at all.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
