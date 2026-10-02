-- ============================================================================
-- RB-20260810191201-D510 — put the batch at "Originals printed, office copies
-- outstanding"                                                    (SQL SERVER)
--
-- Option A was run, so all four print columns on these 184 rows are now cleared
-- and the batch reads "184 letters ready — nothing printed yet". The intended
-- state was Option B: Originals still printed, Duplicate & Triplicate reopened.
--
-- The cleared timestamps are recoverable because print_logs was deliberately left
-- alone. Every batch run writes ONE ROW PER COPY there:
--
--     reference_number = file_number
--     document_type    = 'Land ROFO'
--     print_type       = 'LandRofoBatch'      (re-issuances use LandRofoReissuance
--                                              and are excluded below — they never
--                                              stamped these columns to begin with)
--     status           = 'Original' | 'Duplicate' | 'Triplicate'
--     created_at       = when that run happened
--
-- So rofo_originals_printed_at is rebuilt from the 'Original' rows, and
-- rofo_office_copies_printed_at is deliberately left NULL, which is exactly the
-- "run 2 outstanding" state.
--
-- Run section 1 first. If it shows any letter with no Original log row, stop and
-- read section 4 before going on.
-- ============================================================================

DECLARE @batch nvarchar(100) = 'RB-20260810191201-D510';


/* -- 1. PREVIEW: what print_logs can give back ---------------------------- */
SELECT
    COUNT(*)                                                          AS letters,
    SUM(CASE WHEN lg.original_at IS NOT NULL THEN 1 ELSE 0 END)       AS recoverable,
    SUM(CASE WHEN lg.original_at IS NULL     THEN 1 ELSE 0 END)       AS no_original_log,
    MIN(lg.original_at)                                               AS earliest_original,
    MAX(lg.original_at)                                               AS latest_original
FROM land_recommendations lr
OUTER APPLY (
    SELECT MAX(p.created_at) AS original_at
    FROM print_logs p
    WHERE p.document_type = 'Land ROFO'
      AND p.print_type    = 'LandRofoBatch'
      AND p.status        = 'Original'
      AND UPPER(LTRIM(RTRIM(p.reference_number))) = UPPER(LTRIM(RTRIM(lr.file_number)))
) lg
WHERE lr.rofo_batch_id = @batch;

-- Row by row, so a letter missing its log is visible by name.
SELECT
    lr.id,
    lr.batch_seq,
    lr.file_number,
    lr.rofo_print_count                       AS print_count_now,
    lr.rofo_originals_printed_at              AS originals_now,
    lr.rofo_office_copies_printed_at          AS office_now,
    lg.original_at                            AS original_from_logs,
    lg.original_runs,
    lg.office_runs
FROM land_recommendations lr
OUTER APPLY (
    SELECT
        MAX(CASE WHEN p.status = 'Original' THEN p.created_at END)                  AS original_at,
        COUNT(CASE WHEN p.status = 'Original' THEN 1 END)                           AS original_runs,
        COUNT(CASE WHEN p.status IN ('Duplicate', 'Triplicate') THEN 1 END)         AS office_runs
    FROM print_logs p
    WHERE p.document_type = 'Land ROFO'
      AND p.print_type    = 'LandRofoBatch'
      AND UPPER(LTRIM(RTRIM(p.reference_number))) = UPPER(LTRIM(RTRIM(lr.file_number)))
) lg
WHERE lr.rofo_batch_id = @batch
ORDER BY lr.batch_seq, lr.id;


/* -- 2. Safety count ------------------------------------------------------ */
SELECT COUNT(*) AS rows_in_batch
FROM land_recommendations
WHERE rofo_batch_id = @batch;


/* ===========================================================================
   3. THE RESTORE — Originals back, office copies outstanding
   ---------------------------------------------------------------------------
   For every letter that has an 'Original' row in print_logs:
     rofo_originals_printed_at     <- when that run actually happened
     rofo_office_copies_printed_at <- NULL          (run 2 is owed)
     rofo_print_count              <- 1            (>0 is all this flag means:
                                                    the letter has been printed)
     rofo_print_run_mode           <- 'split'      (it is now a two-run batch)

   A letter with no Original log row is left untouched — see section 4.
   =========================================================================== */
BEGIN TRANSACTION;

UPDATE lr
   SET lr.rofo_originals_printed_at     = lg.original_at,
       lr.rofo_office_copies_printed_at = NULL,
       lr.rofo_print_count              = 1,
       lr.rofo_print_run_mode           = 'split',
       lr.updated_at                    = GETDATE()
FROM land_recommendations lr
CROSS APPLY (
    SELECT MAX(p.created_at) AS original_at
    FROM print_logs p
    WHERE p.document_type = 'Land ROFO'
      AND p.print_type    = 'LandRofoBatch'
      AND p.status        = 'Original'
      AND UPPER(LTRIM(RTRIM(p.reference_number))) = UPPER(LTRIM(RTRIM(lr.file_number)))
) lg
WHERE lr.rofo_batch_id = @batch
  AND lg.original_at IS NOT NULL;

-- Expect the "recoverable" figure from section 1. Anything else: ROLLBACK.
COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;


/* ===========================================================================
   4. ONLY IF section 1 showed letters with no Original log row
   ---------------------------------------------------------------------------
   Those letters have no evidence of when their Originals were printed. Section 3
   leaves them cleared, so they sit at "not printed" and the next full run would
   put a fresh Original on security paper for each.

   If the paper IS in the file and they should join the others as "Originals
   done", this stamps them with the batch's own run time — the same time the rest
   of the batch carries. Uncomment deliberately: it asserts something the logs do
   not prove.
   =========================================================================== */
-- UPDATE land_recommendations
--    SET rofo_originals_printed_at     = (SELECT MAX(rofo_originals_printed_at)
--                                           FROM land_recommendations
--                                          WHERE rofo_batch_id = @batch),
--        rofo_office_copies_printed_at = NULL,
--        rofo_print_count              = 1,
--        rofo_print_run_mode           = 'split',
--        updated_at                    = GETDATE()
--  WHERE rofo_batch_id = @batch
--    AND rofo_originals_printed_at IS NULL;


/* -- 5. AFTER: the batch should read "184 owe their Duplicate & Triplicate" - */
SELECT
    COUNT(*)                                                                   AS letters,
    SUM(CASE WHEN rofo_originals_printed_at     IS NOT NULL THEN 1 ELSE 0 END) AS originals_done,
    SUM(CASE WHEN rofo_office_copies_printed_at IS NOT NULL THEN 1 ELSE 0 END) AS office_copies_done,
    SUM(CASE WHEN ISNULL(rofo_print_count, 0) > 0 THEN 1 ELSE 0 END)           AS counted_as_printed
FROM land_recommendations
WHERE rofo_batch_id = @batch;
-- Expect: originals_done = 184, office_copies_done = 0, counted_as_printed = 184.
-- In the Print Manager: "Original Only" ticked, "Duplicate & Triplicate" not,
-- and the status line reading "184 of 184 had Originals printed and still owe
-- their Duplicate & Triplicate."
