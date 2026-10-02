/* ============================================================================
   Add duplex_parcel_updates.site_plan
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_28_110000_add_site_plan_to_duplex_parcel_updates_table.php

   WHY
   A Duplex Parcel Update carries several parcel updates as ONE instruction, and
   the officer recommending it works from ONE drawing — the application plan
   showing every portion the stages act on, and the extension land beside them
   where there is an extension. Merger, Subdivision and Separation each already
   carry a site_plan column; the duplex had none, so the drawing the whole
   recommendation turns on had nowhere to live. The wizard now asks for it on the
   step before Done.

   COLUMN
     site_plan  nvarchar(500) NULL

   Holds a RELATIVE path on the Laravel `public` disk, e.g.
     parcel_documents/duplex/duplex_31_site_plan_1756412345.pdf
   the same convention as PlotMergerController. Not a URL and not an absolute
   path: Storage::url() builds the link and Storage::delete() removes the file, so
   moving the storage root must not require touching any row.

   Why one plan per duplex rather than one per stage: the stages are legs of a
   single instruction over the same parcels. Splitting the drawing across them
   would ask for the same sheet several times and leave the register unable to say
   which copy is the plan of record.

   SAFETY
     - Re-runnable: the ALTER is guarded by COL_LENGTH.
     - Adding a NULL column with no default is a metadata-only change; it rewrites
       no rows and takes no long lock.
     - NO BACKFILL: a duplex captured before this has no plan, and NULL says so.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MYSQL:
        database/sql/2026_08_28_add_site_plan_to_duplex_parcel_updates_ledger.mysql.sql
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
    CASE WHEN COL_LENGTH('dbo.duplex_parcel_updates', 'site_plan') IS NULL
         THEN 'site_plan does NOT exist - it will be added'
         ELSE 'site_plan already exists - nothing to do' END AS site_plan_state;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the column.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add column ===';

IF COL_LENGTH('dbo.duplex_parcel_updates', 'site_plan') IS NULL
BEGIN
    /* Relative path on the `public` disk to the recommended site plan. */
    ALTER TABLE dbo.duplex_parcel_updates ADD site_plan nvarchar(500) NULL;
    PRINT '  site_plan added.';
END
ELSE
    PRINT '  site_plan already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.duplex_parcel_updates')
  AND c.name = 'site_plan';

/* Expected: one row — name site_plan, type nvarchar, max_length 1000
   (nvarchar stores 2 bytes per character, so 500 characters = 1000),
   is_nullable 1.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
