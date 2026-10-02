-- Remove 26 resolved duplicate files from duplicate_fileno (2026-08-26)
-- Run duplicates_resolved_2026-08-26_backup.sql FIRST (or keep it) — it is the only undo.
-- Scoped to explicit ids only. Expected rowcount: 26

BEGIN TRANSACTION;

DELETE FROM duplicate_fileno
WHERE id IN (
    3140, 3151, 3180, 3227, 3230, 3258, 3274, 3291, 3316, 3317, 3318, 3320, 3322, 3324, 3330, 3335, 3336, 3341, 3344, 3346, 3381, 3395, 3398, 3404, 3420, 3441
);

-- Verify @@ROWCOUNT = 26 before committing.
-- COMMIT TRANSACTION;   -- uncomment to apply
-- ROLLBACK TRANSACTION; -- if the count is wrong
