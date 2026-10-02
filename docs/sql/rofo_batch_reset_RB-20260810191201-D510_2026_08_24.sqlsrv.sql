-- ============================================================================
-- Reset the print state of RofO batch RB-20260810191201-D510        (SQL SERVER)
--
-- The batch shows all three passes ticked and "All 184 letters fully printed".
-- What drives that is four columns on land_recommendations — there is no print
-- status table:
--
--   rofo_office_copies_printed_at   set  -> stage "complete"  (run 2 ticked)
--   rofo_originals_printed_at       set  -> stage "originals" (run 1 ticked)
--   rofo_print_count              > 0    -> the Printed tab, and, for a row with
--                                           neither timestamp, "complete" as well
--                                           (legacy single-pass records)
--   rofo_print_run_mode                  -> which way the last run was ordered
--
-- print_logs is the audit trail and is NOT touched by the resets below: the
-- history of what was put on paper stays intact. Section 5 shows how to remove it
-- as well, but read the warning there first.
--
-- Run section 1 and read it before running anything else. Then run EITHER
-- section 3 (whole batch back to unprinted) OR section 4 (office copies only) —
-- not both.
-- ============================================================================

DECLARE @batch nvarchar(100) = 'RB-20260810191201-D510';


/* -- 1. PREVIEW: where this batch stands ---------------------------------- */
SELECT
    COUNT(*)                                                            AS letters,
    SUM(CASE WHEN ISNULL(rofo_print_count, 0) > 0 THEN 1 ELSE 0 END)    AS with_print_count,
    SUM(CASE WHEN rofo_originals_printed_at     IS NOT NULL THEN 1 ELSE 0 END) AS originals_done,
    SUM(CASE WHEN rofo_office_copies_printed_at IS NOT NULL THEN 1 ELSE 0 END) AS office_copies_done,
    SUM(CASE WHEN date_issued IS NOT NULL THEN 1 ELSE 0 END)            AS with_date_issued,
    MIN(rofo_originals_printed_at)                                      AS first_original_at,
    MAX(rofo_office_copies_printed_at)                                  AS last_office_copy_at
FROM land_recommendations
WHERE rofo_batch_id = @batch;

-- Row by row, with the stage the application derives for each.
SELECT
    lr.id,
    lr.batch_seq,
    lr.file_number,
    lr.applicant_name,
    lr.rofo_print_count,
    lr.rofo_originals_printed_at,
    lr.rofo_office_copies_printed_at,
    lr.rofo_print_run_mode,
    CASE
        WHEN lr.rofo_office_copies_printed_at IS NOT NULL THEN 'complete'
        WHEN lr.rofo_originals_printed_at     IS NOT NULL THEN 'originals'
        WHEN ISNULL(lr.rofo_print_count, 0)   > 0         THEN 'complete (legacy count only)'
        ELSE 'none'
    END                                                                 AS stage_now,
    pl.print_runs,
    pl.last_print_at
FROM land_recommendations lr
OUTER APPLY (
    SELECT COUNT(*) AS print_runs, MAX(created_at) AS last_print_at
    FROM print_logs p
    WHERE p.document_type = 'Land ROFO'
      AND UPPER(LTRIM(RTRIM(p.reference_number))) = UPPER(LTRIM(RTRIM(lr.file_number)))
) pl
WHERE lr.rofo_batch_id = @batch
ORDER BY lr.batch_seq, lr.id;


/* -- 2. Safety count: this is the number every UPDATE below must report ---- */
SELECT COUNT(*) AS rows_this_reset_will_touch
FROM land_recommendations
WHERE rofo_batch_id = @batch;


/* ===========================================================================
   3. OPTION A — the whole batch back to NOT PRINTED
   ---------------------------------------------------------------------------
   Every letter in the batch becomes unprinted: it leaves the Printed tab, all
   three passes lose their tick, and the next run prints the full set again.
   Use this when the batch is to be printed from the top.

   date_issued is left alone on purpose. It is the date on the letters already
   in the file, and a reprint has to carry the same one.
   =========================================================================== */
BEGIN TRANSACTION;

UPDATE land_recommendations
   SET rofo_print_count              = 0,
       rofo_originals_printed_at     = NULL,
       rofo_office_copies_printed_at = NULL,
       rofo_print_run_mode           = NULL,
       updated_at                    = GETDATE()
 WHERE rofo_batch_id = @batch;

-- Expect exactly the count from section 2. Anything else: ROLLBACK.
COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;


/* ===========================================================================
   4. OPTION B — office copies only (run 2 outstanding again)
   ---------------------------------------------------------------------------
   The Originals stay printed; only "Duplicate & Triplicate" is reopened, so the
   batch reads as "184 had Originals printed and still owe their Duplicate &
   Triplicate" and run 2 reprints those alone. No security paper is spent.

   Run this INSTEAD of section 3, not after it.

   The second UPDATE matters: a row whose rofo_originals_printed_at is NULL is
   still counted complete while rofo_print_count > 0 (that is the legacy
   single-pass rule), so clearing the office timestamp alone would leave it
   ticked. Stamping the originals timestamp from the batch's own run puts such a
   row honestly at "run 1 done".
   =========================================================================== */
-- BEGIN TRANSACTION;
--
-- UPDATE land_recommendations
--    SET rofo_office_copies_printed_at = NULL,
--        updated_at                    = GETDATE()
--  WHERE rofo_batch_id = @batch;
--
-- UPDATE land_recommendations
--    SET rofo_originals_printed_at = COALESCE(
--            rofo_originals_printed_at,
--            (SELECT MIN(rofo_originals_printed_at)
--               FROM land_recommendations
--              WHERE rofo_batch_id = @batch),
--            GETDATE()),
--        updated_at = GETDATE()
--  WHERE rofo_batch_id = @batch
--    AND rofo_originals_printed_at IS NULL
--    AND ISNULL(rofo_print_count, 0) > 0;
--
-- COMMIT TRANSACTION;
-- -- ROLLBACK TRANSACTION;


/* ===========================================================================
   5. OPTIONAL and DESTRUCTIVE — remove the print history too
   ---------------------------------------------------------------------------
   Neither reset above touches print_logs, so "who printed this, and when" is
   still answerable afterwards. Deleting those rows throws that away permanently
   and cannot be undone. It is only needed if the row-level Print Manager must
   also show these letters as never printed — the Batches view reads the columns
   above, not the logs.

   Left commented. Uncomment deliberately, never as part of a routine reset.
   =========================================================================== */
-- DELETE p
--   FROM print_logs p
--   JOIN land_recommendations lr
--     ON UPPER(LTRIM(RTRIM(p.reference_number))) = UPPER(LTRIM(RTRIM(lr.file_number)))
--  WHERE lr.rofo_batch_id = @batch
--    AND p.document_type  = 'Land ROFO';


/* -- 6. AFTER: confirm the batch reads as intended ------------------------- */
SELECT
    COUNT(*)                                                                   AS letters,
    SUM(CASE WHEN ISNULL(rofo_print_count, 0) > 0 THEN 1 ELSE 0 END)           AS with_print_count,
    SUM(CASE WHEN rofo_originals_printed_at     IS NOT NULL THEN 1 ELSE 0 END) AS originals_done,
    SUM(CASE WHEN rofo_office_copies_printed_at IS NOT NULL THEN 1 ELSE 0 END) AS office_copies_done
FROM land_recommendations
WHERE rofo_batch_id = @batch;
-- Option A expects: with_print_count 0, originals_done 0, office_copies_done 0.
-- Option B expects: originals_done = letters, office_copies_done 0.
