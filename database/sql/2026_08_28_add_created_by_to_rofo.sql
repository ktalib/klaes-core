/* ============================================================================
   Add rofo.created_by
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_28_110000_add_created_by_to_rofo_table.php

   WHY
   The ST RofO listing (Sectional Titling > Certificate > RofO) shows Date Created
   but could not show Created By. `rofo` is a legacy table with no author column,
   and the date the listing printed came from subapplications.created_at — when the
   UNIT APPLICATION was captured, not when the RofO was generated. This column lets
   the generated rows answer "who issued this RofO", and the Master Delete audit
   entry records it.

   COLUMN
     created_by  bigint NULL   -- users.id of whoever generated the RofO

   SAFETY
     - Re-runnable: the ALTER is guarded by COL_LENGTH.
     - Adding a NULL column with no default is a metadata-only change; it does not
       rewrite existing rows.
     - NO BACKFILL, deliberately. Every RofO generated before this ran has no author
       on record. Copying subapplications.created_by in would name the person who
       captured the unit application, who may never have touched the RofO — the
       listing prints a dash instead.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MYSQL:
        database/sql/2026_08_28_add_created_by_to_rofo_ledger.mysql.sql
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
    CASE WHEN COL_LENGTH('dbo.rofo', 'created_by') IS NULL
         THEN 'created_by does NOT exist - it will be added'
         ELSE 'created_by already exists - nothing to do' END AS created_by_state,
    (SELECT COUNT(*) FROM dbo.rofo) AS rofo_rows;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the column.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add column ===';

IF COL_LENGTH('dbo.rofo', 'created_by') IS NULL
BEGIN
    /* users.id of whoever generated the RofO. */
    ALTER TABLE dbo.rofo ADD created_by bigint NULL;
    PRINT '  created_by added.';
END
ELSE
    PRINT '  created_by already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.rofo')
  AND c.name = 'created_by';

/* Expected: one row — name created_by, type bigint, is_nullable 1.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
