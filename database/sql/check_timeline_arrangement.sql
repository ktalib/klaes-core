/* ===========================================================================
   Timeline row order is pinned by a SAVED ARRANGEMENT
   ---------------------------------------------------------------------------
   Symptom: File Commissioning (weight 12) renders BELOW rows of weight 11 and 1,
   and re-deploying the ordering code changes nothing.

   Cause: legal_search_timeline_arrangements stores a manual drag-and-drop order
   per prop_id. Both LegalSearchService::applyArrangementOrder() and
   sortTimelineByArrangement() in js.blade.php apply it AFTER the weight/date
   sort, so a saved arrangement overrides every ordering rule in the code. That
   is by design — someone deliberately arranged the rows — but it also means a
   stale arrangement outlives any fix.

   KNML 1200 / CON-RES-RC-1982-709 resolves to prop_id 18421.

   STEP 1 tells you whether an arrangement exists. Only run STEP 2 if it does
   AND the pinned order is not one you want to keep: deleting it restores the
   computed order (File Commissioning first on weight 12).
   =========================================================================== */

SET NOCOUNT ON;

DECLARE @propId VARCHAR(50) = '18421';   -- CON-RES-RC-1982-709

/* -- STEP 1: is the order pinned? ----------------------------------------- */
SELECT
    a.id,
    a.prop_id,
    a.source_table,
    a.source_id,
    a.display_order,
    a.arranged_by,
    a.arranged_at
FROM dbo.legal_search_timeline_arrangements AS a
WHERE a.prop_id = @propId
ORDER BY a.display_order;

/* No rows  -> the arrangement is NOT the cause; stop here and tell me.
   Rows     -> this is what is pinning the order. Note arranged_by / arranged_at:
               if a colleague arranged it deliberately, confirm before clearing.  */


/* -- STEP 2: clear it, so the computed order applies again ------------------
   Scoped to this one prop_id. Nothing else is touched.
   Uncomment the transaction to run it.                                        */

-- BEGIN TRANSACTION;
--
--     DELETE FROM dbo.legal_search_timeline_arrangements
--     WHERE prop_id = @propId;
--
--     PRINT 'Arrangement rows deleted: ' + CAST(@@ROWCOUNT AS NVARCHAR(10));
--
--     /* Expect the count you saw in STEP 1. If it matches: */
--     -- COMMIT TRANSACTION;
--     /* If it does not: */
--     -- ROLLBACK TRANSACTION;


/* -- Optional: which other files are pinned? ------------------------------- */
-- SELECT prop_id, COUNT(*) AS rows_pinned, MIN(arranged_at) AS first_arranged,
--        MAX(arranged_at) AS last_arranged
-- FROM dbo.legal_search_timeline_arrangements
-- GROUP BY prop_id
-- ORDER BY MAX(arranged_at) DESC;
