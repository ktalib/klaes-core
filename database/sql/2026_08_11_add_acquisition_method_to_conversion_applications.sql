/* ============================================================================
   Add conversion_applications.acquisition_method / .acquisition_other
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_11_190000_add_acquisition_method_to_conversion_applications.php

   Remembers the Property Acquisition Method answered for an LGA Confirmation
   Sheet (LCS). The answer used to travel only in the print URL, so every reprint
   re-opened the "Property Acquisition Method" card and nothing stopped a second
   print from answering it differently. `conversion_applications` already holds
   one row per LCS — it is where the sheet's serial_no is kept and reused on
   reprint — so the answer belongs beside it.

   COLUMNS
     acquisition_method nvarchar(10)  NULL  -- 'a'..'e', the letters on the sheet
     acquisition_other  nvarchar(255) NULL  -- free text under "e. Any other (Specify)"
     (Laravel's sqlsrv grammar cannot position a column; they land at the end,
      same as this file.)

   SAFETY
     - Re-runnable: each ALTER is guarded by COL_LENGTH.
     - Adding NULL columns with no default is a metadata-only change; it does not
       rewrite the 32 existing rows or hold a long lock.
     - Existing rows stay NULL, which reads as "never answered" — the first print
       after this deploy asks once, then stores.
     - Wrapped in a transaction, left open for review as per house convention.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MySQL:
        database/sql/2026_08_11_add_acquisition_method_to_conversion_applications_ledger.mysql.sql
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
    CASE WHEN COL_LENGTH('dbo.conversion_applications', 'acquisition_method') IS NULL
         THEN 'acquisition_method does NOT exist - it will be added'
         ELSE 'acquisition_method already exists - nothing to do'
    END AS acquisition_method_state,
    CASE WHEN COL_LENGTH('dbo.conversion_applications', 'acquisition_other') IS NULL
         THEN 'acquisition_other does NOT exist - it will be added'
         ELSE 'acquisition_other already exists - nothing to do'
    END AS acquisition_other_state,
    (SELECT COUNT(*) FROM dbo.conversion_applications) AS existing_rows;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the columns.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add columns ===';

IF COL_LENGTH('dbo.conversion_applications', 'acquisition_method') IS NULL
BEGIN
    /* 'a' By Purchase | 'b' By Inheritance | 'c' By Gift
       'd' Direct Local Government Allocation | 'e' Any other (Specify) */
    ALTER TABLE dbo.conversion_applications ADD acquisition_method nvarchar(10) NULL;
    PRINT '  acquisition_method added.';
END
ELSE
    PRINT '  acquisition_method already present - skipped.';

IF COL_LENGTH('dbo.conversion_applications', 'acquisition_other') IS NULL
BEGIN
    ALTER TABLE dbo.conversion_applications ADD acquisition_other nvarchar(255) NULL;
    PRINT '  acquisition_other added.';
END
ELSE
    PRINT '  acquisition_other already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.conversion_applications')
  AND c.name IN ('acquisition_method', 'acquisition_other')
ORDER BY c.name;

/* Expected: two rows, both nvarchar and is_nullable 1 —
     acquisition_method max_length 20  (= 10 chars)
     acquisition_other  max_length 510 (= 255 chars)
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
