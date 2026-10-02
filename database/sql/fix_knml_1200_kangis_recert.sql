/* ===========================================================================
   KNML 1200  <->  CON-RES-RC-1982-709   :  make the KANGIS recertification show
   ---------------------------------------------------------------------------
   DIAGNOSIS (from scratch/kangis_recert_trace.php on production)

     related_file_number id=10636
       file_number    = KNML 1200
       related_fileno = CON-RES-RC-1982-709
       transaction_type = 'Related File'      <-- WRONG for a KANGIS alias

     rows BUILT: 0

   Two faults, both visible above:

   1. WRONG TYPE. LegalSearchService::recertDisplayLabel() derives the timeline
      label from related_file_number.transaction_type. 'Related File' is the
      registrar's DEFAULT_TYPE and is treated as a neutral link, so it can never
      become a recertification row. It must read 'KANGIS Recertification'.

      (This is why `related-files:backfill` did not fix it: that command replays
      file_indexings.related_fileno through the registrar using the default type.
      It created the missing link, which was a real gap, but not a typed one.)

   2. ONE DIRECTION ONLY. A working pair carries a row on BOTH sides — see
      ids 1189/4288 for COM-1982-203 <-> MLKN 1718. Searching either number then
      finds a link whose OTHER endpoint is not the searched file. With a single
      row, whichever side is searched can resolve the display endpoint back to
      the searched file itself, which fetchRelatedRecertificationRows() skips as
      redundant — the "rows BUILT: 0" above.

   SAFE TO RE-RUN. Statement 1 is an UPDATE scoped to one id; statement 2 inserts
   only if the reverse row is absent. Nothing is deleted.

   Run against the KLAES SQL Server database, then re-run:
     php scratch\kangis_recert_trace.php "KNML 1200"
   =========================================================================== */

SET NOCOUNT ON;

DECLARE @kangis  NVARCHAR(100) = N'KNML 1200';
DECLARE @land    NVARCHAR(100) = N'CON-RES-RC-1982-709';
DECLARE @type    NVARCHAR(100) = N'KANGIS Recertification';

/* Anchor rows. source_id is NOT NULL and must point at the indexing row that
   owns each side of the link — 173415 (KANGIS) and 173374 (land) per the probe,
   but resolved here rather than hard-coded so this stays correct if ids differ. */
DECLARE @kangisIndexingId BIGINT = (
    SELECT TOP 1 id FROM dbo.file_indexings
    WHERE file_number = @kangis AND deleted_at IS NULL ORDER BY id
);
DECLARE @landIndexingId BIGINT = (
    SELECT TOP 1 id FROM dbo.file_indexings
    WHERE file_number = @land AND deleted_at IS NULL ORDER BY id
);

DECLARE @title    NVARCHAR(255) = (SELECT TOP 1 file_title FROM dbo.file_indexings WHERE id = @landIndexingId);
DECLARE @location NVARCHAR(255) = (SELECT TOP 1 location   FROM dbo.file_indexings WHERE id = @landIndexingId);

IF @kangisIndexingId IS NULL OR @landIndexingId IS NULL
BEGIN
    RAISERROR('Could not resolve both file_indexings rows - check the file numbers.', 16, 1);
    RETURN;
END

BEGIN TRANSACTION;

/* -- 1. Type the existing link correctly ---------------------------------- */
UPDATE dbo.related_file_number
SET transaction_type = @type,
    comment          = COALESCE(NULLIF(comment, N''), N'KANGIS RECERTIFICATION ' + @kangis),
    updated_at       = SYSDATETIME()
WHERE source_table = 'file_indexings'
  AND file_number  = @kangis
  AND related_fileno = @land;

PRINT 'Rows retyped: ' + CAST(@@ROWCOUNT AS NVARCHAR(10));

/* -- 2. Add the reverse direction, if it is missing ------------------------ */
IF NOT EXISTS (
    SELECT 1 FROM dbo.related_file_number
    WHERE source_table = 'file_indexings'
      AND file_number = @land
      AND related_fileno = @kangis
)
BEGIN
    INSERT INTO dbo.related_file_number
        (related_fileno, prop_id, source_table, source_id, file_number,
         file_title, party_2, location, comment, transaction_type, created_at, updated_at)
    VALUES
        (@kangis, NULL, 'file_indexings', @landIndexingId, @land,
         @title, NULL, @location, N'KANGIS RECERTIFICATION ' + @land, @type,
         SYSDATETIME(), SYSDATETIME());

    PRINT 'Reverse row inserted.';
END
ELSE
BEGIN
    /* Present but possibly mistyped by an earlier backfill — correct it too. */
    UPDATE dbo.related_file_number
    SET transaction_type = @type, updated_at = SYSDATETIME()
    WHERE source_table = 'file_indexings'
      AND file_number = @land
      AND related_fileno = @kangis
      AND (transaction_type IS NULL OR transaction_type <> @type);

    PRINT 'Reverse row already present; retyped if needed.';
END

/* -- Verify BEFORE committing --------------------------------------------- */
SELECT id, file_number, related_fileno, transaction_type, source_id, source_table
FROM dbo.related_file_number
WHERE (file_number = @kangis AND related_fileno = @land)
   OR (file_number = @land   AND related_fileno = @kangis)
ORDER BY id;

/* Expect TWO rows, both transaction_type = 'KANGIS Recertification'.
   If that is what you see: */
-- COMMIT TRANSACTION;

/* If it is not: */
-- ROLLBACK TRANSACTION;
