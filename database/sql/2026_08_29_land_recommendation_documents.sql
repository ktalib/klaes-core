/* ============================================================================
   land_recommendation_documents — the already-approved recommendation letter
   ----------------------------------------------------------------------------
   TARGET: SQL SERVER (the `klas` database on the sqlsrv connection).

   Companion to:
     database/migrations/2026_08_29_090000_create_land_recommendation_documents_table.php
     database/sql/2026_08_29_land_recommendation_documents_ledger.mysql.sql  <- RUN THAT SECOND

   Run this file FIRST. It creates the table and the two columns; the MySQL ledger
   file then tells artisan the migration is already applied so the next deploy does
   not retry it.

   WHY: a file whose Occupancy Permit holder differs from the File Indexing name has
   already been through recommendation once. That letter is approved and will not be
   submitted for approval again, so the capture form stops generating a new one and
   asks for the existing letter to be uploaded; approval waits until it is on file.
   The RofO is still generated and printed from the record.

   WHY THE FLAG IS STORED, not derived: pressing Match writes the missing Transfer
   of Title — the very row whose absence made the file qualify. A gate that re-asked
   the question at approval time would find the file no longer qualifying and would
   let the approval through without the letter.

   Re-runnable: every statement is guarded.
   ============================================================================ */

IF OBJECT_ID('dbo.land_recommendation_documents', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.land_recommendation_documents (
        id                      BIGINT IDENTITY(1,1) NOT NULL,

        /* The recommendation this letter belongs to. One current letter each —
           enforced below. */
        land_recommendation_id  BIGINT           NOT NULL,

        /* Denormalised so the row still reads on its own if the recommendation is
           later renumbered or removed. */
        file_number             NVARCHAR(100)        NULL,

        /* Relative to the 'public' disk (storage/app/public). */
        path                    NVARCHAR(500)    NOT NULL,
        original_name           NVARCHAR(255)        NULL,
        mime_type               NVARCHAR(120)        NULL,
        size_bytes              BIGINT               NULL,

        uploaded_by             BIGINT               NULL,
        uploaded_at             DATETIME             NULL,

        created_at              DATETIME             NULL,
        updated_at              DATETIME             NULL,

        CONSTRAINT PK_land_rec_documents PRIMARY KEY CLUSTERED (id)
    );
END
GO

/* One document per recommendation. A re-upload UPDATEs this row rather than
   inserting a second, so "which is the current letter" is never a question. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
     WHERE name = 'UQ_land_rec_documents_recommendation'
       AND object_id = OBJECT_ID('dbo.land_recommendation_documents')
)
BEGIN
    CREATE UNIQUE INDEX UQ_land_rec_documents_recommendation
        ON dbo.land_recommendation_documents (land_recommendation_id);
END
GO

/* The record needs an uploaded letter before it can be approved. */
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
     WHERE object_id = OBJECT_ID('dbo.land_recommendations')
       AND name = 'is_existing_recommendation'
)
BEGIN
    ALTER TABLE dbo.land_recommendations
        ADD is_existing_recommendation BIT NOT NULL CONSTRAINT DF_land_rec_is_existing DEFAULT (0);
END
GO

/* The transfer that Match wrote on this file's behalf, for provenance. */
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
     WHERE object_id = OBJECT_ID('dbo.land_recommendations')
       AND name = 'op_match_tot_pra_id'
)
BEGIN
    ALTER TABLE dbo.land_recommendations
        ADD op_match_tot_pra_id BIGINT NULL;
END
GO

/* Verify */
SELECT
    (SELECT COUNT(*) FROM sys.tables  WHERE name = 'land_recommendation_documents')                         AS documents_table,
    (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('dbo.land_recommendations')
                                        AND name = 'is_existing_recommendation')                            AS flag_column,
    (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('dbo.land_recommendations')
                                        AND name = 'op_match_tot_pra_id')                                   AS tot_column;
GO
