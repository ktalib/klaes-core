/* ============================================================================
   Reset DPX-2026-0004 to DRAFT
   ----------------------------------------------------------------------------
   Run against the SQL SERVER `klas` database. PRODUCTION — read STEP 0 before
   committing anything.

   WHAT "DRAFT" MEANS HERE
   A duplex at `draft` is the only status whose row menu offers "Continue
   capture", so this is what puts an officer back inside the wizard. The wizard
   reopens at the first stage NOT marked done; with every stage already done it
   opens on the last one, and step 2 is filled from the saved stage payloads.

   *** READ THIS FIRST ***
   This script does NOT undo a commissioning. If STEP 0 reports the duplex as
   `committed`, the file numbers it minted are live in fileNumber / mls_file_no /
   file_indexings / pra, its source files are decommissioned, and setting the
   status back to draft would leave the registry holding those files while the
   duplex claims never to have issued them. STEP 1 refuses in that case.

   To undo a commissioning, use the command that was written for it — it removes
   only the rows keyed to the file numbers THIS duplex created and restores its
   sources:

       php artisan duplex:rollback DPX-2026-0004 --dry-run
       php artisan duplex:rollback DPX-2026-0004

   That already leaves the duplex at `in_land`. Run this script afterwards if you
   also want it back at `draft` for re-capture.

   SAFETY
     - Everything runs inside one transaction, left OPEN for you to COMMIT.
     - Scoped by duplex_id to the single row; no predicate touches anything else.
     - STEP 2 and STEP 3 are OPTIONAL and commented out. Read them and decide.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @duplex_id nvarchar(40) = 'DPX-2026-0004';

BEGIN TRANSACTION;

/* ---------------------------------------------------------------------------
   STEP 0 — What this duplex is, right now.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: current state ===';

SELECT
    d.id,
    d.duplex_id,
    d.status,
    d.knupda_status,
    d.committed_at,
    d.sent_to_land_at,
    d.conveyance_generated_at,
    d.recommendation_generated_at,
    d.site_plan,
    (SELECT COUNT(*) FROM dbo.duplex_parcel_update_stages s
      WHERE s.duplex_parcel_update_id = d.id)                          AS stages_total,
    (SELECT COUNT(*) FROM dbo.duplex_parcel_update_stages s
      WHERE s.duplex_parcel_update_id = d.id AND s.status = 'done')    AS stages_done,
    (SELECT COUNT(*) FROM dbo.duplex_parcel_update_files f
      WHERE f.duplex_parcel_update_id = d.id AND f.final_file_no IS NOT NULL)
                                                                       AS real_file_numbers_issued
FROM dbo.duplex_parcel_updates d
WHERE d.duplex_id = @duplex_id;

/* The file numbers it issued, if any. An empty result here is what you want to
   see: it means nothing was commissioned and the reset is a status change only. */
SELECT f.holding_no, f.final_file_no, f.role, s.rank, s.type
FROM dbo.duplex_parcel_update_files f
LEFT JOIN dbo.duplex_parcel_update_stages s
       ON s.id = f.duplex_parcel_update_stage_id
WHERE f.duplex_parcel_update_id =
      (SELECT id FROM dbo.duplex_parcel_updates WHERE duplex_id = @duplex_id)
  AND f.final_file_no IS NOT NULL
ORDER BY s.rank, f.sequence;

/* ---------------------------------------------------------------------------
   STEP 1 — The reset. Refuses on a commissioned duplex.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: reset status to draft ===';

IF NOT EXISTS (SELECT 1 FROM dbo.duplex_parcel_updates WHERE duplex_id = @duplex_id)
BEGIN
    PRINT '  ** No such duplex. Nothing done. **';
END
ELSE IF EXISTS (SELECT 1 FROM dbo.duplex_parcel_updates
                 WHERE duplex_id = @duplex_id AND status = 'committed')
BEGIN
    /* Deliberately refuses. See the header: a committed duplex has live file
       numbers, and "draft" would hide that fact rather than undo it. */
    PRINT '  ** REFUSED: this duplex is COMMITTED. **';
    PRINT '  ** Run: php artisan duplex:rollback DPX-2026-0004   (then re-run this) **';
END
ELSE
BEGIN
    UPDATE dbo.duplex_parcel_updates
       SET status     = 'draft',
           updated_at = SYSDATETIME()
     WHERE duplex_id = @duplex_id;

    PRINT '  status set to draft.';
END

/* ---------------------------------------------------------------------------
   STEP 2 — OPTIONAL. Clear the approval and the papers.

   Take this if the duplex is going back for re-capture and the approval, memo
   and conveyance should be given again on whatever it becomes. Leave it out if
   you only want the officer back in the wizard and the paperwork already drawn
   is still the paperwork you want.

   Not automatic either way: which of these two you mean is a decision about the
   file, not something a script should assume.
   --------------------------------------------------------------------------- */
-- PRINT '=== STEP 2: clear approval + generated documents ===';
--
-- UPDATE dbo.duplex_parcel_updates
--    SET approved_by                  = NULL,
--        sent_to_land_at              = NULL,
--        application_generated_at     = NULL,
--        conveyance_generated_at      = NULL,
--        recommendation_generated_at  = NULL,
--        updated_at                   = SYSDATETIME()
--  WHERE duplex_id = @duplex_id;

/* ---------------------------------------------------------------------------
   STEP 3 — OPTIONAL. Send the stages back to be captured again.

   Without this the stages stay `done` and the wizard reopens on the LAST one,
   with every answer still in place — usually what you want, because re-saving a
   stage replaces its holding numbers cleanly.

   Take this only to re-capture the duplex from stage 1. It clears each stage's
   completion and DELETES its holding-number rows; saveStage() mints fresh ones
   as each stage is captured again. Never run it on a committed duplex: those
   rows carry final_file_no, which is the only record of what became what.
   --------------------------------------------------------------------------- */
-- PRINT '=== STEP 3: reopen every stage ===';
--
-- DECLARE @id bigint = (SELECT id FROM dbo.duplex_parcel_updates WHERE duplex_id = @duplex_id);
--
-- IF EXISTS (SELECT 1 FROM dbo.duplex_parcel_update_files
--             WHERE duplex_parcel_update_id = @id AND final_file_no IS NOT NULL)
--     PRINT '  ** REFUSED: this duplex has issued real file numbers. Roll it back first. **';
-- ELSE
-- BEGIN
--     DELETE FROM dbo.duplex_parcel_update_files
--      WHERE duplex_parcel_update_id = @id
--        AND holding_no IS NOT NULL;          /* holding rows only; source rows stay */
--
--     UPDATE dbo.duplex_parcel_update_stages
--        SET status       = 'pending',
--            completed_at = NULL,
--            updated_at   = SYSDATETIME()
--      WHERE duplex_parcel_update_id = @id;
--
--     PRINT '  stages reopened and holding numbers cleared.';
-- END

/* ---------------------------------------------------------------------------
   STEP 4 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 4: result ===';

SELECT
    d.duplex_id,
    d.status,
    d.knupda_status,
    d.approved_by,
    d.conveyance_generated_at,
    d.recommendation_generated_at,
    (SELECT COUNT(*) FROM dbo.duplex_parcel_update_stages s
      WHERE s.duplex_parcel_update_id = d.id AND s.status = 'done') AS stages_done,
    (SELECT COUNT(*) FROM dbo.duplex_parcel_update_stages s
      WHERE s.duplex_parcel_update_id = d.id)                       AS stages_total
FROM dbo.duplex_parcel_updates d
WHERE d.duplex_id = @duplex_id;

/* Expected: status = 'draft', and one row only.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 4, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
