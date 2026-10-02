/* ============================================================================
   Add pra.cofo_type
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_11_170000_add_cofo_type_to_pra_table.php

   Backs the new "CofO Type" dropdown on the Occupancy Permit Details card in
   File Indexing (/fileindexing/create). Occupancy Permit rows live in `pra`
   alongside RofO rows (distinguished by instrument_type), and `pra` had no
   cofo_type column, so the dropdown had nowhere to persist.

   Canonical values — resources/views/partials/cofo_type_options.blade.php:
     Land CofO | KANGIS CofO - Old | KANGIS CofO - New | SLTR CofO | ST CofO
   Free text, no constraint: CofO_staging.cofo_type already carries legacy values
   ("Old CofO (Ministry)", "Legacy CofO", …) and the UI passes an unrecognised
   stored value through as a "(legacy)" option rather than blanking it.

   COLUMN
     cofo_type nvarchar(100) NULL.
     (Laravel's sqlsrv grammar cannot position a column; it lands at the end,
     same as this file.)

   SAFETY
     - Re-runnable: guarded by COL_LENGTH.
     - Adding a NULL column with no default is a metadata-only change; it does not
       rewrite the pra rows or hold a long lock.
     - Wrapped in a transaction, left open for review as per house convention.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MySQL:
        database/sql/2026_08_11_add_cofo_type_to_pra_ledger.mysql.sql
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
    CASE WHEN COL_LENGTH('dbo.pra', 'cofo_type') IS NULL
         THEN 'cofo_type does NOT exist - it will be added'
         ELSE 'cofo_type already exists - nothing to do'
    END AS column_state;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the column.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add column ===';

IF COL_LENGTH('dbo.pra', 'cofo_type') IS NULL
BEGIN
    /* Land CofO | KANGIS CofO - Old | KANGIS CofO - New | SLTR CofO | ST CofO */
    ALTER TABLE dbo.pra ADD cofo_type nvarchar(100) NULL;
    PRINT '  cofo_type added.';
END
ELSE
    PRINT '  cofo_type already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.pra')
  AND c.name = 'cofo_type';

/* Expected: one row, nvarchar, max_length 200 (= 100 chars), is_nullable 1.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
