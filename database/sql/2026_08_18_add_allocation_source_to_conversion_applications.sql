/* ============================================================================
   Add conversion_applications.allocation_source / .allocation_entity / .allocation_address
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_18_110000_add_allocation_source_to_conversion_applications.php

   The Confirmation Sheet (CS) print card now confirms the Allocation Source side
   by side with the Property Acquisition Method, because the source decides who
   the sheet is addressed to: a Local Government source addresses its Chairman, a
   State Government source addresses the allocating entity (KSIP / HOUSING /
   KUNPDA / a typed one). `conversion_applications` already holds one row per
   sheet — it is where the serial_no and the acquisition answer live — so the
   confirmed source belongs beside them, and a reprint cannot contradict the copy
   already issued.

   COLUMNS
     allocation_source nvarchar(100) NULL  -- 'State Government' | 'Local Government'
     allocation_entity nvarchar(100) NULL  -- the LGA, or the state entity addressed
     allocation_address nvarchar(255) NULL -- address block printed under a state entity
     (Laravel's sqlsrv grammar cannot position a column; they land at the end,
      same as this file.)

   SAFETY
     - Re-runnable: each ALTER is guarded by COL_LENGTH.
     - Adding NULL columns with no default is a metadata-only change; it does not
       rewrite existing rows or hold a long lock.
     - Existing sheets stay NULL, which reads as "never confirmed" — the first
       print after this deploy asks once (pre-filled from the file's own
       allocation info or its LGA), then stores.
     - Wrapped in a transaction, left open for review as per house convention.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MySQL:
        database/sql/2026_08_18_add_allocation_source_to_conversion_applications_ledger.mysql.sql
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
    CASE WHEN COL_LENGTH('dbo.conversion_applications', 'allocation_source') IS NULL
         THEN 'allocation_source does NOT exist - it will be added'
         ELSE 'allocation_source already exists - nothing to do' END AS allocation_source_state,
    CASE WHEN COL_LENGTH('dbo.conversion_applications', 'allocation_entity') IS NULL
         THEN 'allocation_entity does NOT exist - it will be added'
         ELSE 'allocation_entity already exists - nothing to do' END AS allocation_entity_state,
    CASE WHEN COL_LENGTH('dbo.conversion_applications', 'allocation_address') IS NULL
         THEN 'allocation_address does NOT exist - it will be added'
         ELSE 'allocation_address already exists - nothing to do' END AS allocation_address_state;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the columns.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add columns ===';

IF COL_LENGTH('dbo.conversion_applications', 'allocation_source') IS NULL
BEGIN
    /* 'State Government' | 'Local Government' */
    ALTER TABLE dbo.conversion_applications ADD allocation_source nvarchar(100) NULL;
    PRINT '  allocation_source added.';
END
ELSE
    PRINT '  allocation_source already present - skipped.';

IF COL_LENGTH('dbo.conversion_applications', 'allocation_entity') IS NULL
BEGIN
    /* The LGA, or the state entity the sheet is addressed to */
    ALTER TABLE dbo.conversion_applications ADD allocation_entity nvarchar(100) NULL;
    PRINT '  allocation_entity added.';
END
ELSE
    PRINT '  allocation_entity already present - skipped.';

IF COL_LENGTH('dbo.conversion_applications', 'allocation_address') IS NULL
BEGIN
    /* The address block printed under a State Government entity */
    ALTER TABLE dbo.conversion_applications ADD allocation_address nvarchar(255) NULL;
    PRINT '  allocation_address added.';
END
ELSE
    PRINT '  allocation_address already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.conversion_applications')
  AND c.name IN ('allocation_source', 'allocation_entity', 'allocation_address')
ORDER BY c.name;

/* Expected: three rows, nvarchar, is_nullable 1 — max_length 200 (= 100 chars)
   for the source/entity, 510 (= 255 chars) for the address.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
