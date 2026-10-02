/* ============================================================================
   Backfill fileNumber.location for the ST conversion files
   ----------------------------------------------------------------------------
   Fixes the LOCATION column showing N/A on the MLS File Commissioning table
   (/file-numbers) for:
       id 153269  CON-RES-2026-2817  (ST-CON-RES-2026-1)  -> 'BOMPAI, NASSARAWA'
       id 153281  CON-RES-2026-2818  (ST-CON-RES-2026-2)  -> 'BOMPAI, NASSARAWA'

   WHY
     That table reads its Location from `fileNumber`, not from mls_file_no
     (FileNumberController::getData selects fn.location). ST commissioning mirrored
     into fileNumber without that field, so the row landed with location NULL and
     rendered 'N/A' — even though file_indexings and mls_file_no both had it.

     The code now passes it (CommissionNewSTController::commission → the
     mirrorStToFileNumber payload), so this script is only for the two files
     commissioned BEFORE that change. No migration accompanies it — data only.

   SCOPE
     Deliberately limited to the ids in @targets below. A survey of ST rows with a
     blank location found 11, but the other 9 have no location, district, LGA or
     street on their indexing row either — there is nothing to copy for them, so
     they are left alone rather than written with a guessed value. Add ids to
     @targets if more files need it later.

   WHAT IT SETS
     location : file_indexings.location, else 'DISTRICT, LGA' composed the same way
                the app does (CommissionNewSTController::composeLocation — district
                first, LGA upper-cased). For these two that yields
                'BOMPAI, NASSARAWA'.

     plot_no is NOT touched. file_indexings.plot_number holds 'PIECE OF LAND' for
     both, which is a description rather than a plot number; STEP 1b below is
     commented out — uncomment only if you want that copied across.

   SAFETY
     - Re-runnable: only rows still blank are written.
     - No deletes, no inserts; one UPDATE against fileNumber, two rows at most.
     - Wrapped in a transaction, left open for review as per house convention.

   USAGE
     Run against the SQL Server `klas` database, check STEP 2, then COMMIT.
     There is no MySQL ledger companion — this is a data fix, not a migration.

   VERIFIED
     The same statement ran against the working DB on 2026-08-10 (rolled back):
     one row matched and came out location = 'ABBA ABDULLAHI AV, FAGGE'; a second
     run affected 0 rows.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* The fileNumber rows to fix. */
DECLARE @targets TABLE (id bigint PRIMARY KEY);
INSERT INTO @targets (id) VALUES (153269), (153281);

/* ---------------------------------------------------------------------------
   STEP 0 — Preview: what will change, and to what.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: rows that will be updated ===';

SELECT
    fn.id,
    fn.mlsfNo,
    fn.st_file_no,
    fn.location AS location_before,
    COALESCE(
        NULLIF(LTRIM(RTRIM(ix.location)), ''),
        NULLIF(
            LTRIM(RTRIM(
                COALESCE(NULLIF(LTRIM(RTRIM(ix.district)), '') + ', ', '')
                + UPPER(COALESCE(NULLIF(LTRIM(RTRIM(ix.lga)), ''), ''))
            )),
            ''
        ),
        NULLIF(LTRIM(RTRIM(ix.street_name)), '')
    ) AS location_after
FROM dbo.fileNumber fn
JOIN @targets t ON t.id = fn.id
JOIN dbo.file_indexings ix
      ON ix.file_number = fn.mlsfNo
     AND (ix.is_deleted IS NULL OR ix.is_deleted = 0)
WHERE fn.location IS NULL OR LTRIM(RTRIM(fn.location)) = ''
ORDER BY fn.id;

/* ---------------------------------------------------------------------------
   STEP 1 — Backfill the location.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: update fileNumber.location ===';

UPDATE fn
   SET fn.location = COALESCE(
           NULLIF(LTRIM(RTRIM(ix.location)), ''),
           NULLIF(
               LTRIM(RTRIM(
                   COALESCE(NULLIF(LTRIM(RTRIM(ix.district)), '') + ', ', '')
                   + UPPER(COALESCE(NULLIF(LTRIM(RTRIM(ix.lga)), ''), ''))
               )),
               ''
           ),
           NULLIF(LTRIM(RTRIM(ix.street_name)), '')
       ),
       fn.updated_at = SYSDATETIME()
  FROM dbo.fileNumber fn
  JOIN @targets t ON t.id = fn.id
  JOIN dbo.file_indexings ix
        ON ix.file_number = fn.mlsfNo
       AND (ix.is_deleted IS NULL OR ix.is_deleted = 0)
 WHERE (fn.location IS NULL OR LTRIM(RTRIM(fn.location)) = '')
   AND COALESCE(
           NULLIF(LTRIM(RTRIM(ix.location)), ''),
           NULLIF(LTRIM(RTRIM(ix.district)), ''),
           NULLIF(LTRIM(RTRIM(ix.lga)), ''),
           NULLIF(LTRIM(RTRIM(ix.street_name)), '')
       ) IS NOT NULL;

PRINT '  location rows updated: ' + CAST(@@ROWCOUNT AS varchar(10));

/* ---------------------------------------------------------------------------
   STEP 1b — OPTIONAL: copy plot_no as well.
   file_indexings.plot_number is 'PIECE OF LAND' for both files — a description,
   not a plot number. Uncomment only if that is wanted on the table.
   --------------------------------------------------------------------------- */
-- UPDATE fn
--    SET fn.plot_no = LTRIM(RTRIM(ix.plot_number)),
--        fn.updated_at = SYSDATETIME()
--   FROM dbo.fileNumber fn
--   JOIN @targets t ON t.id = fn.id
--   JOIN dbo.file_indexings ix
--         ON ix.file_number = fn.mlsfNo
--        AND (ix.is_deleted IS NULL OR ix.is_deleted = 0)
--  WHERE (fn.plot_no IS NULL OR LTRIM(RTRIM(fn.plot_no)) = '')
--    AND ix.plot_number IS NOT NULL
--    AND LTRIM(RTRIM(ix.plot_number)) <> '';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT fn.id, fn.mlsfNo, fn.st_file_no, fn.location, fn.plot_no
  FROM dbo.fileNumber fn
  JOIN @targets t ON t.id = fn.id
 ORDER BY fn.id;

/* Expected: two rows, both location = 'BOMPAI, NASSARAWA'.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
