/* ============================================================================
   Add instrument_capture.party_2_gender
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_10_100000_add_party_2_gender_to_instrument_capture_table.php

   Backs the Party 2 gender dropdown on /instruments/create. Options are rendered
   from the `genders` lookup table (App\Models\Gender::options()), and the STORED
   VALUE IS THE NAME — Male|Female|Corporate|Joint, GenderNormalizer::CANON — not
   a genders.id, matching file_indexings.gender / mls_file_no.gender /
   st_file_numbers.gender. No foreign key.

   Run 2026_08_09_create_genders_lookup.sql FIRST if the `genders` table is not
   there yet, or the dropdown falls back to its hard-coded CANON list.

   COLUMN
     party_2_gender nvarchar(20) NULL, after party_2_name.
     (Laravel's sqlsrv grammar cannot place a column, so ordering is cosmetic and
     the column lands at the end here too — same result as the migration.)

   SAFETY
     - Re-runnable: guarded by COL_LENGTH.
     - Adding a NULL column with no default is a metadata-only change; it does not
       rewrite the ~instrument_capture rows or hold a long lock.
     - Wrapped in a transaction, left open for review as per house convention.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MySQL:
        database/sql/2026_08_10_add_party_2_gender_to_instrument_capture_ledger.mysql.sql
        (the migrations ledger lives in MySQL, not here — see that file.)

   VERIFIED
     The migration it mirrors ran against the working DB on 2026-08-10; the column
     came out nvarchar(20) NULL and captures round-tripped Female/male/Government/
     ''/nonsense to Female/Male/Corporate/NULL/NULL.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* ---------------------------------------------------------------------------
   STEP 0 — Preview.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: current state ===';

SELECT
    CASE WHEN COL_LENGTH('dbo.instrument_capture', 'party_2_gender') IS NULL
         THEN 'party_2_gender does NOT exist - it will be added'
         ELSE 'party_2_gender already exists - nothing to do'
    END AS column_state;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the column.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add column ===';

IF COL_LENGTH('dbo.instrument_capture', 'party_2_gender') IS NULL
BEGIN
    /* Male | Female | Corporate | Joint — GenderNormalizer::CANON */
    ALTER TABLE dbo.instrument_capture ADD party_2_gender nvarchar(20) NULL;
    PRINT '  party_2_gender added.';
END
ELSE
    PRINT '  party_2_gender already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.instrument_capture')
  AND c.name = 'party_2_gender';

/* Expected: one row, nvarchar, max_length 40 (= 20 chars), is_nullable 1.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
