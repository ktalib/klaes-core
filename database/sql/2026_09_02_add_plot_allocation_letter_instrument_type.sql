/* ============================================================================
   Add the "Plot Allocation Letter" instrument type
   ----------------------------------------------------------------------------
   WHY
   The Instrument Type dropdown on every capture screen is built from
   dbo.InstrumentTypes:

     resources/views/fileindexing/partial/property_transaction_modal.blade.php
       SELECT DISTINCT RTRIM(LTRIM(InstrumentName)) FROM InstrumentTypes
        WHERE InstrumentName IS NOT NULL ORDER BY InstrumentName

   So a new instrument type is a ROW, not a code change. There is no migration for
   it: the table holds reference data, not schema.

   WHAT
     InstrumentName = 'Plot Allocation Letter'

   Its Type list says who issued the letter, and it has no Category:

     Instrument Type           Type                                 | Category
     ------------------------  -----------------------------------  | ---------
     Plot Allocation Letter    Land, LGA, Urban Development Board   | —
     Occupancy Permit          Resettlement, Direct Allocation, LGA | Old, New
     Certificate of Occupancy  Land, Old KANGIS, New KANGIS, LGA    | Old, New
     Right of Occupancy        Land, LGA                            | Old, New

   app/Services/InstrumentTypeCatalog.php holds that table and is its only copy.

   THERE IS NO 'LGA Allocation Letter' INSTRUMENT TYPE
   A letter issued by a Local Government is a Plot Allocation Letter with
   Type = 'LGA'. It briefly existed both as an Occupancy Permit Type and as an
   instrument type of its own; it is neither. If a previous run of this script
   created that row, STEP 1b removes it — no captured record can reference it,
   because nothing was ever saved against it.

   InstrumentTypeID is an IDENTITY column, so it is not supplied here. IsActive is
   set to 1, matching every existing row.

   PARTY LABELS
   The capture form falls back to Grantor / Grantee for any type it has no entry
   for, which is what this one uses. Nothing further is needed.

   SAFETY
     - Re-runnable: the INSERT is guarded by NOT EXISTS, the DELETE by a row count.
     - Adds at most one row and removes at most one. Touches no schema.
     - STEP 0 proves the removal is safe before you commit: it counts every captured
       row that names an allocation letter. Do not commit unless that count is 0.

   USAGE
     Run against the SQL Server `klas` database, check STEP 2, COMMIT.
     There is NO MySQL ledger companion — this is reference data, not a migration,
     so `php artisan migrate` has nothing to record.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* ---------------------------------------------------------------------------
   STEP 0 — Preview. The captured-rows count MUST be 0 before you commit.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: current state ===';

SELECT COUNT(*) AS instrument_types_before FROM dbo.InstrumentTypes;

SELECT
    CASE WHEN EXISTS (SELECT 1 FROM dbo.InstrumentTypes
                       WHERE LTRIM(RTRIM(InstrumentName)) = 'Plot Allocation Letter')
         THEN 'Plot Allocation Letter already exists' ELSE 'Plot Allocation Letter will be added' END AS add_state,
    CASE WHEN EXISTS (SELECT 1 FROM dbo.InstrumentTypes
                       WHERE LTRIM(RTRIM(InstrumentName)) = 'LGA Allocation Letter')
         THEN 'LGA Allocation Letter present - it will be removed' ELSE 'LGA Allocation Letter not present' END AS remove_state;

SELECT
    (SELECT COUNT(*) FROM dbo.pra WHERE transaction_type LIKE '%Allocation Letter%')
  + (SELECT COUNT(*) FROM dbo.pra WHERE instrument_type LIKE '%Allocation Letter%')
  + (SELECT COUNT(*) FROM dbo.file_history_staging WHERE transaction_type LIKE '%Allocation Letter%')
  + (SELECT COUNT(*) FROM dbo.pra WHERE op_type LIKE '%Allocation Letter%')
    AS captured_rows_naming_an_allocation_letter;

/* ---------------------------------------------------------------------------
   STEP 1 — Add Plot Allocation Letter.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: insert ===';

INSERT INTO dbo.InstrumentTypes (InstrumentName, IsActive)
SELECT 'Plot Allocation Letter', 1
 WHERE NOT EXISTS (
       SELECT 1 FROM dbo.InstrumentTypes
        WHERE LTRIM(RTRIM(InstrumentName)) = 'Plot Allocation Letter');

PRINT '  rows inserted: ' + CAST(@@ROWCOUNT AS varchar(10));

/* ---------------------------------------------------------------------------
   STEP 1b — Remove 'LGA Allocation Letter' if an earlier run created it.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1b: remove LGA Allocation Letter ===';

DELETE FROM dbo.InstrumentTypes
 WHERE LTRIM(RTRIM(InstrumentName)) = 'LGA Allocation Letter';

PRINT '  rows removed: ' + CAST(@@ROWCOUNT AS varchar(10));

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT InstrumentTypeID, InstrumentName, IsActive
  FROM dbo.InstrumentTypes
 WHERE InstrumentName LIKE '%Allocation Letter%'
 ORDER BY InstrumentName;

SELECT COUNT(*) AS instrument_types_after FROM dbo.InstrumentTypes;

/* Expected: exactly ONE row — Plot Allocation Letter, IsActive 1 — and no
   'LGA Allocation Letter' row at all.
   If so, and STEP 0 reported 0 captured rows:  COMMIT TRANSACTION;
   If anything is off:                          ROLLBACK TRANSACTION;         */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
