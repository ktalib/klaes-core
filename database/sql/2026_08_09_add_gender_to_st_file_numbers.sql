/* ============================================================================
   Add st_file_numbers.gender
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_09_140000_add_gender_to_st_file_numbers_table.php

   Backs the required Gender dropdown on the ST File Number Commissioning form
   (/commission-new-st, Primary tab). The STORED VALUE IS THE NAME —
   Male|Female|Corporate|Joint, GenderNormalizer::CANON — matching
   file_indexings.gender / mls_file_no.gender / instrument_capture.party_2_gender.
   No foreign key.

   The same commissioning writes gender to file_indexings and (for a conversion)
   mls_file_no; both already have the column, so this is the only schema change
   the ST conversion work needs.

   COLUMN
     gender nvarchar(20) NULL, after applicant_type.
     (Laravel's sqlsrv grammar cannot place a column, so ordering is cosmetic and
     the column lands at the end here too — same result as the migration.)

   SAFETY
     - Re-runnable: guarded by COL_LENGTH.
     - Adding a NULL column with no default is a metadata-only change; it does not
       rewrite the st_file_numbers rows or hold a long lock.
     - Existing rows keep gender NULL. Nothing reads it as required — only new
       commissioning validates it — so no backfill is needed.
     - Wrapped in a transaction, left open for review as per house convention.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MySQL:
        database/sql/2026_08_09_add_gender_to_st_file_numbers_ledger.mysql.sql
        (the migrations ledger lives in MySQL, not here — see that file.)

   VERIFIED
     The migration it mirrors ran against the working DB on 2026-08-09; the column
     came out nvarchar(20) NULL and a commissioned conversion round-tripped
     'Female' into st_file_numbers, file_indexings (both rows) and mls_file_no.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* ---------------------------------------------------------------------------
   STEP 0 — Preview.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: current state ===';

SELECT
    CASE WHEN COL_LENGTH('dbo.st_file_numbers', 'gender') IS NULL
         THEN 'gender does NOT exist - it will be added'
         ELSE 'gender already exists - nothing to do'
    END AS column_state;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the column.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add column ===';

IF COL_LENGTH('dbo.st_file_numbers', 'gender') IS NULL
BEGIN
    /* Male | Female | Corporate | Joint — GenderNormalizer::CANON */
    ALTER TABLE dbo.st_file_numbers ADD gender nvarchar(20) NULL;
    PRINT '  gender added.';
END
ELSE
    PRINT '  gender already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.st_file_numbers')
  AND c.name = 'gender';

/* Expected: one row, nvarchar, max_length 40 (= 20 chars), is_nullable 1.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
