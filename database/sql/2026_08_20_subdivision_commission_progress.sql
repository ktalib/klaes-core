/* ============================================================================
   Chunked commissioning for plot subdivisions  (SQL SERVER)
   ----------------------------------------------------------------------------
   Companion file: 2026_08_20_subdivision_commission_progress_ledger.mysql.sql
   RUN THIS ONE FIRST, against SQL Server; then the ledger file against MySQL.

   WHY
   The MLS file-number generator mints at most 200 files per batch run
   (MlsFileNoController::generateBatch -> batch_quantity max:200). A subdivision
   approved for more than that - e.g. 500 plots - has to be commissioned across
   several runs: 200 + 200 + 100. These columns are what makes a run resumable.

   WHAT IT ADDS to plot_subdivision_applications
     commissioned_count          INT           fragments minted so far
     commissioned_batches        NVARCHAR(MAX) JSON log, one entry per chunk
     commissioning_completed_at  DATETIME      set when the last plot is minted

   The application only flips status to 'commissioned' when commissioned_count
   reaches num_plots; until then it stays 'approved' so the generator's
   find-by-file lookup keeps returning it for the next chunk.

   NOTHING IS DELETED by this script or by the feature it supports: the mother
   file is decommissioned by flag only and keeps its rows in fileNumber,
   file_indexings, the staging tables and the grouping tables.

   Mirrors migration
   2026_08_20_140000_add_commission_progress_to_plot_subdivision_applications.
   Safe to re-run: every step is guarded.
   ========================================================================== */

USE klas;
GO

/* ---- 1. Columns ---------------------------------------------------------- */
IF COL_LENGTH('dbo.plot_subdivision_applications', 'commissioned_count') IS NULL
BEGIN
    ALTER TABLE dbo.plot_subdivision_applications ADD commissioned_count INT NULL CONSTRAINT DF_psa_commissioned_count DEFAULT (0);
    PRINT 'Added plot_subdivision_applications.commissioned_count';
END
ELSE
    PRINT 'plot_subdivision_applications.commissioned_count already present - skipped';
GO

IF COL_LENGTH('dbo.plot_subdivision_applications', 'commissioned_batches') IS NULL
BEGIN
    ALTER TABLE dbo.plot_subdivision_applications ADD commissioned_batches NVARCHAR(MAX) NULL;
    PRINT 'Added plot_subdivision_applications.commissioned_batches';
END
ELSE
    PRINT 'plot_subdivision_applications.commissioned_batches already present - skipped';
GO

IF COL_LENGTH('dbo.plot_subdivision_applications', 'commissioning_completed_at') IS NULL
BEGIN
    ALTER TABLE dbo.plot_subdivision_applications ADD commissioning_completed_at DATETIME NULL;
    PRINT 'Added plot_subdivision_applications.commissioning_completed_at';
END
ELSE
    PRINT 'plot_subdivision_applications.commissioning_completed_at already present - skipped';
GO

/* ---- 2. Seed existing rows ----------------------------------------------- */
/* Applications commissioned before chunking existed were single-shot: whatever
   num_plots said was minted in one run. Without this, the progress badge would
   read 0 / N for work that is actually finished. Only rows with no chunk log
   are touched, so re-running cannot overwrite real progress.                  */
UPDATE dbo.plot_subdivision_applications
   SET commissioned_count = ISNULL(num_plots, 0)
 WHERE status = 'commissioned'
   AND commissioned_batches IS NULL
   AND ISNULL(commissioned_count, 0) = 0;
PRINT CONCAT('Seeded commissioned_count on ', @@ROWCOUNT, ' legacy commissioned application(s)');
GO

UPDATE dbo.plot_subdivision_applications
   SET commissioned_count = 0
 WHERE commissioned_count IS NULL;
GO

/* ---- 3. VERIFY (read-only) ------------------------------------------------ */
/* One row per checked item. Every line must read PASS.                        */
SELECT 'commissioned_count column' AS item,
       CASE WHEN COL_LENGTH('dbo.plot_subdivision_applications','commissioned_count') IS NULL
            THEN 'FAIL - column missing; chunked commissioning cannot count fragments and every run would restart at 0'
            ELSE 'PASS' END AS result
UNION ALL
SELECT 'commissioned_batches column',
       CASE WHEN COL_LENGTH('dbo.plot_subdivision_applications','commissioned_batches') IS NULL
            THEN 'FAIL - column missing; the per-chunk log is lost and the mother file would be re-archived on every run'
            ELSE 'PASS' END
UNION ALL
SELECT 'commissioning_completed_at column',
       CASE WHEN COL_LENGTH('dbo.plot_subdivision_applications','commissioning_completed_at') IS NULL
            THEN 'FAIL - column missing; completion time of the final chunk is not recorded'
            ELSE 'PASS' END
UNION ALL
SELECT 'legacy commissioned rows seeded',
       CASE WHEN EXISTS (SELECT 1 FROM dbo.plot_subdivision_applications
                          WHERE status = 'commissioned'
                            AND commissioned_batches IS NULL
                            AND ISNULL(commissioned_count,0) = 0
                            AND ISNULL(num_plots,0) > 0)
            THEN 'FAIL - a commissioned application still counts 0 fragments; its progress badge will read 0/N and a re-run would mint duplicate files'
            ELSE 'PASS' END
UNION ALL
SELECT 'no NULL counters',
       CASE WHEN EXISTS (SELECT 1 FROM dbo.plot_subdivision_applications WHERE commissioned_count IS NULL)
            THEN 'FAIL - NULL commissioned_count rows remain; remainingPlots() would treat them as 0 done, which is right, but the progress badge query is noisier'
            ELSE 'PASS' END;
GO
