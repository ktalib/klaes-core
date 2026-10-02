/* ============================================================================
   Add pra.op_category
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_09_02_100000_add_op_category_to_pra_table.php

   WHY
   An Occupancy Permit now records which GENERATION of permit the paper is —
   Old OP or New OP — alongside op_type, which records HOW it was granted
   (Resettlement / Direct Allocation / LGA). The two are independent facts about
   the same permit, so op_type cannot carry this and a second column is needed.

   The question is asked only for Resettlement and Direct Allocation. The two LGA
   kinds are outside it: a Local Government registers nothing in the State deeds
   registry, so an LGA permit has no generation to be placed in.

   WHAT IT CHANGES
   An Old OP predates the registry practice that produced a serial, page and
   volume, so its registration particulars are OPTIONAL. On a New OP they stay
   required, exactly as they are today.

   COLUMN
     op_category  nvarchar(50) NULL      -- 'Old OP' | 'New OP' | NULL

   WHY NULL, AND WHY BLANK IS MEANINGFUL
   About 4,500 Occupancy Permit rows predate the field and nobody has read the
   paper to say which generation they are. Blank behaves exactly as a New OP does
   today (registration particulars still required), so no historic row silently
   loses the rule it was captured under.

   NO BACKFILL. Inferring a generation from a transaction date or a serial number
   would write an unverified fact onto the register.

   SAFETY
     - Re-runnable: the ALTER is guarded by COL_LENGTH.
     - Adding a NULL column with no default is a metadata-only change; it does not
       rewrite existing rows or hold a long lock on the table.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MYSQL:
        database/sql/2026_09_02_add_op_category_to_pra_ledger.mysql.sql
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
    CASE WHEN COL_LENGTH('dbo.pra', 'op_category') IS NULL
         THEN 'op_category does NOT exist - it will be added'
         ELSE 'op_category already exists - nothing to do' END AS op_category_state;

/* How many rows the new column will apply to, for scale. */
SELECT COUNT(*) AS occupancy_permit_rows
FROM dbo.pra
WHERE instrument_type LIKE '%Occupancy Permit%';

/* ---------------------------------------------------------------------------
   STEP 1 — Add the column.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add column ===';

IF COL_LENGTH('dbo.pra', 'op_category') IS NULL
BEGIN
    /* 'Old OP' or 'New OP', as read off the physical permit. NULL = never asked. */
    ALTER TABLE dbo.pra ADD op_category nvarchar(50) NULL;
    PRINT '  op_category added.';
END
ELSE
    PRINT '  op_category already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.pra')
  AND c.name IN ('op_type', 'op_category')
ORDER BY c.name;

/* Expected: two rows. op_category — nvarchar, max_length 100 (nvarchar stores 2
   bytes per character, so 50 characters = 100), is_nullable 1. op_type is listed
   beside it only to confirm the pair now sits together on the table.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
