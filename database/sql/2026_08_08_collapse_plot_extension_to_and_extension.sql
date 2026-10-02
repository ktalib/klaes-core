/* ============================================================================
   Collapse "Plot Extension" into the single "… AND EXTENSION" extension type
   ----------------------------------------------------------------------------
   Sheet item 8, the data half of item 7 (which removed the File-vs-Plot chooser
   from the Commission New File Number modal).

   BACKGROUND
   A Plot Extension RETAINED the original file number and was stored only in the
   isolated `plot_extensions` table — MlsPlotExtensionController::store() writes
   no row to mls_file_no and none to fileNumber, so these files were never
   actually commissioned. A File Extension, by contrast, commissions a new
   number "<original> AND EXTENSION" into BOTH tables.

   This script converts every surviving Plot Extension into a proper commissioned
   File Extension, mirroring the shape of a real one (verified against
   mls_file_no id=37541 / fileNumber id=138635, "IND-2026-800 AND EXTENSION").

   TARGET DATA (verified 2026-08-08 on the working DB)
     plot_extensions: 6 rows, all created in 2026, none soft-deleted.
       CON-AG-2002-56, CON-IND-2024-23, CON-COM-RC-1982-68,
       CON-RES-2016-202, CON-AG-2015-74, CON-COM-2025-577
     None of the six has an "<original> AND EXTENSION" row in mls_file_no yet,
     and none of the six ORIGINAL numbers is in mls_file_no either.

   NOTES ON THE COLUMN VALUES
     - year           = the COMMISSIONING year, not the base number's year. Both
                        existing extension rows carry 2026, including
                        "CON-COM-2025-495 AND EXTENSION". Taken from created_at.
     - serial_number  = 0. An extension consumes no serial.
     - file_option    = 'extension'; source = 'Extension File'.
     - fileNumber.SOURCE = 'MLS_Commissioned' — this is what makes the file read
                        as KLAES-commissioned in Legal Search. Without it the
                        File Commissioning row shows a bare year, not a date.
     - commissioning_date/time are taken from the plot extension's created_at, so
                        the timeline keeps the date the work was actually done.

   SAFETY
     - Wrapped in a transaction; re-runnable (each INSERT is guarded by NOT EXISTS).
     - STEP 4 (retiring the plot_extensions rows) is COMMENTED OUT by default —
       read the note above it before enabling.
     - Verify STEP 0 output before committing.

   USAGE
     Review STEP 0, run the whole file, check STEP 5's output, then COMMIT
     (or ROLLBACK to abort — the transaction is left open deliberately).

   VERIFIED
     Dry-run against the working DB on 2026-08-08: 6 mls_file_no + 6 fileNumber
     rows inserted, STEP 5 clean, then rolled back.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* ---------------------------------------------------------------------------
   STEP 0 — Preview. What will be converted, and what already exists.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: rows in scope ===';

SELECT
    pe.id                                   AS plot_extension_id,
    pe.original_file_no,
    pe.original_file_no + ' AND EXTENSION'  AS new_file_number,
    pe.land_use,
    YEAR(pe.created_at)                     AS commissioning_year,
    pe.tracking_id,
    pe.created_at,
    pe.created_by,
    CASE WHEN EXISTS (
        SELECT 1 FROM mls_file_no m
        WHERE m.full_file_number = pe.original_file_no + ' AND EXTENSION'
          AND ISNULL(m.is_deleted, 0) = 0
    ) THEN 'ALREADY PRESENT - will be skipped' ELSE 'will be created' END AS mls_file_no_status,
    CASE WHEN EXISTS (
        SELECT 1 FROM fileNumber f
        WHERE f.mlsfNo = pe.original_file_no + ' AND EXTENSION'
          AND ISNULL(f.is_deleted, 0) = 0
    ) THEN 'ALREADY PRESENT - will be skipped' ELSE 'will be created' END AS fileNumber_status
FROM plot_extensions pe
WHERE ISNULL(pe.is_deleted, 0) = 0
ORDER BY pe.id;

BEGIN TRANSACTION;

/* ---------------------------------------------------------------------------
   STEP 1 — Commission the new file number into mls_file_no.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: mls_file_no ===';

INSERT INTO mls_file_no (
    land_use, year, serial_number, full_file_number,
    file_name, plot_no, tp_no, location, lga, district,
    tracking_id, customer_type, purpose_id,
    file_option, source, system_sub_type,
    commissioning_date, commissioning_time,
    created_by, created_at, updated_at, is_deleted, title_status
)
SELECT
    pe.land_use,
    YEAR(pe.created_at),
    0,
    pe.original_file_no + ' AND EXTENSION',
    pe.file_name,
    pe.plot_no,
    pe.tp_no,
    pe.location,
    pe.lga,
    pe.district,
    pe.tracking_id,
    pe.customer_type,
    pe.purpose_id,
    'extension',
    'Extension File',
    'MLS',
    CAST(pe.created_at AS DATE),
    CONVERT(NVARCHAR(16), pe.created_at, 108),
    pe.created_by,
    pe.created_at,
    pe.created_at,
    0,
    0
FROM plot_extensions pe
WHERE ISNULL(pe.is_deleted, 0) = 0
  AND NOT EXISTS (
      SELECT 1 FROM mls_file_no m
      WHERE m.full_file_number = pe.original_file_no + ' AND EXTENSION'
        AND ISNULL(m.is_deleted, 0) = 0
  );

PRINT '  mls_file_no rows inserted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

/* ---------------------------------------------------------------------------
   STEP 2 — Mirror into the legacy fileNumber table.
   SOURCE='MLS_Commissioned' is what marks the file as KLAES-commissioned.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: fileNumber ===';

INSERT INTO fileNumber (
    mlsfNo, FileName, location, plot_no, tp_no, lga, district,
    tracking_id, type, SOURCE,
    created_by, created_at, updated_at,
    is_deleted, is_decommissioned, title_status,
    cad_lands_matching, cad_st_matching, cad_sltr_matching,
    pp_lands_matching, pp_st_matching, pp_sltr_matching, has_temp_file
)
SELECT
    pe.original_file_no + ' AND EXTENSION',
    pe.file_name,
    pe.location,
    pe.plot_no,
    pe.tp_no,
    pe.lga,
    pe.district,
    pe.tracking_id,
    'MlsFileNO',
    'MLS_Commissioned',
    pe.created_by,
    pe.created_at,
    pe.created_at,
    0, 0, 0,          -- is_deleted, is_decommissioned, title_status
    0, 0, 0,          -- cad_lands_matching, cad_st_matching, cad_sltr_matching
    0, 0, 0, 0        -- pp_lands_matching, pp_st_matching, pp_sltr_matching, has_temp_file
FROM plot_extensions pe
WHERE ISNULL(pe.is_deleted, 0) = 0
  AND NOT EXISTS (
      SELECT 1 FROM fileNumber f
      WHERE f.mlsfNo = pe.original_file_no + ' AND EXTENSION'
        AND ISNULL(f.is_deleted, 0) = 0
  );

PRINT '  fileNumber rows inserted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

/* ---------------------------------------------------------------------------
   STEP 3 — Linkage back to the extended file: DELIBERATELY NOT DONE.

   related_file_number is fed by file indexing (7,794 of its rows) and by related
   files the user TYPES into the commission form — MlsFileNoController only writes
   it for those typed entries. A real File Extension gets no automatic link row
   either, so creating one here would invent history the normal flow never makes.

   It also cannot be done safely: source_table and source_id are NOT NULL with no
   default, and there is no source row to point at for a converted extension.

   If the client wants "<no> AND EXTENSION" linked to its base file, that needs a
   decision on what source_table/source_id should carry, and it should then be
   added to the live extension flow too — not just backfilled here.
   --------------------------------------------------------------------------- */

/* ---------------------------------------------------------------------------
   STEP 4 — Retire the old Plot Extension rows.  *** DISABLED BY DEFAULT ***

   Leaving them active means the file-numbers list shows BOTH the old rose
   "Plot Extension" row (original number) and the new "… AND EXTENSION" row —
   the same extension twice.

   Soft-deleting them hides the duplicate, but FileNumberController and
   CommissioningSheetController still read plot_extensions for historical
   display and for `related_file_title = 'Plot Extension'`, so confirm those
   screens behave before enabling this.

   Uncomment only after checking the two screens above.
   --------------------------------------------------------------------------- */
-- PRINT '=== STEP 4: retire plot_extensions ===';
-- UPDATE plot_extensions
--    SET is_deleted = 1,
--        updated_at = GETDATE()
--  WHERE ISNULL(is_deleted, 0) = 0
--    AND EXISTS (
--        SELECT 1 FROM mls_file_no m
--        WHERE m.full_file_number = plot_extensions.original_file_no + ' AND EXTENSION'
--          AND ISNULL(m.is_deleted, 0) = 0
--    );
-- PRINT '  plot_extensions rows retired: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

/* ---------------------------------------------------------------------------
   STEP 5 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 5: result ===';

SELECT
    pe.original_file_no,
    m.id                AS mls_file_no_id,
    m.full_file_number,
    m.year,
    m.serial_number,
    m.file_option,
    m.source,
    m.commissioning_date,
    f.id                AS file_number_id,
    f.SOURCE            AS file_number_source,
    pe.is_deleted       AS plot_extension_is_deleted
FROM plot_extensions pe
LEFT JOIN mls_file_no m
       ON m.full_file_number = pe.original_file_no + ' AND EXTENSION'
      AND ISNULL(m.is_deleted, 0) = 0
LEFT JOIN fileNumber f
       ON f.mlsfNo = pe.original_file_no + ' AND EXTENSION'
      AND ISNULL(f.is_deleted, 0) = 0
ORDER BY pe.id;

/* Every row above must show a non-null mls_file_no_id AND file_number_id.
   If so:            COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 5, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
