/* ============================================================================
   Add land_recommendations.rofo_originals_printed_at / .rofo_office_copies_printed_at
                            / .rofo_print_run_mode
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_19_120000_add_rofo_print_run_fields_to_land_recommendations_table.php

   A batch RofO print can be run as two passes: the Originals on the colour /
   security stock, then the Duplicate and Triplicate on plain paper, with the tray
   reloaded in between. That gap is where a run gets abandoned — the tab is closed,
   the shift ends, the paper is not there. Nothing recorded which half had been put
   on paper, so coming back meant reprinting the Originals to reach the office
   copies: a second letter on security stock for every file in the batch.

   One timestamp per half is what makes a run resumable. Originals stamped with the
   office copies still NULL is exactly "run 1 done, run 2 outstanding", which is
   what the print dialog now reads before it offers anything.

   COLUMNS
     rofo_originals_printed_at     datetime NULL -- run 1 put the Originals on paper
     rofo_office_copies_printed_at datetime NULL -- run 2 put Duplicate + Triplicate on paper
     rofo_print_run_mode           nvarchar(20) NULL -- 'all' (one pass) | 'split' (two runs)
     (Laravel's sqlsrv grammar cannot position a column; they land at the end,
      same as this file.)

   SAFETY
     - Re-runnable: each ALTER is guarded by COL_LENGTH.
     - Adding NULL columns with no default is a metadata-only change; it does not
       rewrite existing rows or hold a long lock.
     - No backfill, and none is wanted. Existing rows stay NULL, and the code reads
       a NULL pair with rofo_print_count > 0 as an old single-pass print — already
       complete, so no historic batch is offered as resumable.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MYSQL:
        database/sql/2026_08_19_add_rofo_print_run_fields_to_land_recommendations_ledger.mysql.sql
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
    CASE WHEN COL_LENGTH('dbo.land_recommendations', 'rofo_originals_printed_at') IS NULL
         THEN 'rofo_originals_printed_at does NOT exist - it will be added'
         ELSE 'rofo_originals_printed_at already exists - nothing to do' END AS originals_state,
    CASE WHEN COL_LENGTH('dbo.land_recommendations', 'rofo_office_copies_printed_at') IS NULL
         THEN 'rofo_office_copies_printed_at does NOT exist - it will be added'
         ELSE 'rofo_office_copies_printed_at already exists - nothing to do' END AS office_state,
    CASE WHEN COL_LENGTH('dbo.land_recommendations', 'rofo_print_run_mode') IS NULL
         THEN 'rofo_print_run_mode does NOT exist - it will be added'
         ELSE 'rofo_print_run_mode already exists - nothing to do' END AS run_mode_state;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the columns.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add columns ===';

IF COL_LENGTH('dbo.land_recommendations', 'rofo_originals_printed_at') IS NULL
BEGIN
    /* When run 1 (Originals, security paper) was sent to the printer */
    ALTER TABLE dbo.land_recommendations ADD rofo_originals_printed_at datetime NULL;
    PRINT '  rofo_originals_printed_at added.';
END
ELSE
    PRINT '  rofo_originals_printed_at already present - skipped.';

IF COL_LENGTH('dbo.land_recommendations', 'rofo_office_copies_printed_at') IS NULL
BEGIN
    /* When run 2 (Duplicate + Triplicate, plain paper) was sent to the printer */
    ALTER TABLE dbo.land_recommendations ADD rofo_office_copies_printed_at datetime NULL;
    PRINT '  rofo_office_copies_printed_at added.';
END
ELSE
    PRINT '  rofo_office_copies_printed_at already present - skipped.';

IF COL_LENGTH('dbo.land_recommendations', 'rofo_print_run_mode') IS NULL
BEGIN
    /* 'all' = one pass carrying all three copies; 'split' = the two-run print */
    ALTER TABLE dbo.land_recommendations ADD rofo_print_run_mode nvarchar(20) NULL;
    PRINT '  rofo_print_run_mode added.';
END
ELSE
    PRINT '  rofo_print_run_mode already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.land_recommendations')
  AND c.name IN ('rofo_originals_printed_at', 'rofo_office_copies_printed_at', 'rofo_print_run_mode')
ORDER BY c.name;

/* Expected: three rows, is_nullable 1 — two datetime, one nvarchar(20)
   (max_length 40).
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
