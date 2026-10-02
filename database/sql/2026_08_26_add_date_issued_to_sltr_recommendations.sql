/* ============================================================================
   Add sltr_recommendations.date_issued
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_26_090000_add_date_issued_to_sltr_recommendations.php

   The SLTR RofO letter prints a DATE OF ISSUE, and until now had nowhere to keep
   one. Two existing columns look like candidates and are not:

     application_date     the applicant's own date, required on the recommendation
                          form and listed with it. Issuing a letter has no business
                          editing it.
     rofo_date_generated  when the RofO was generated in KLAES — a fact about this
                          system, not about the letter in the applicant's hand.

   date_issued is the date the letter is issued and nothing else. It is keyed in on
   the White Copy (the proofing stage) before the letter is proofread, so the date
   on the sheet an officer reads is the date that will print.

   COLUMN
     date_issued  date NULL  -- the date the letter is issued; null until chosen
     (Laravel's sqlsrv grammar cannot position a column, so it lands at the end of
      the table — same as this file. The migration's ->after('application_date') is
      a no-op on SQL Server and the column order does not matter to any query.)

   SAFETY
     - Re-runnable: the ALTER is guarded by COL_LENGTH.
     - Adding a NULL column with no default is a metadata-only change; it does not
       rewrite existing rows or hold a long lock.
     - NO BACKFILL, and none is wanted. Copying application_date or
       rofo_date_generated in would put a date nobody chose onto every historic
       letter, and the proofing stage would then show a date that reads as correct
       and is not. A NULL here means "not issued yet", which is the truth.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MYSQL:
        database/sql/2026_08_26_add_date_issued_to_sltr_recommendations_ledger.mysql.sql
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
    CASE WHEN COL_LENGTH('dbo.sltr_recommendations', 'date_issued') IS NULL
         THEN 'date_issued does NOT exist - it will be added'
         ELSE 'date_issued already exists - nothing to do' END AS date_issued_state;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the column.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add column ===';

IF COL_LENGTH('dbo.sltr_recommendations', 'date_issued') IS NULL
BEGIN
    /* The date the SLTR letter is issued, as printed on it under DATE OF ISSUE */
    ALTER TABLE dbo.sltr_recommendations ADD date_issued date NULL;
    PRINT '  date_issued added.';
END
ELSE
    PRINT '  date_issued already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.sltr_recommendations')
  AND c.name = 'date_issued';

/* Expected: one row — name date_issued, type date, is_nullable 1.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
