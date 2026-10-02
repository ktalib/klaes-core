/* ============================================================================
   land_recommendation_batch_documents — the mother's scanned recommendation
   ----------------------------------------------------------------------------
   TARGET: SQL SERVER (the `klas` database on the sqlsrv connection).

   Companion to:
     database/migrations/2026_08_28_140000_create_land_recommendation_batch_documents_table.php
     database/sql/2026_08_28_land_recommendation_batch_documents_ledger.mysql.sql  <- RUN THAT SECOND

   Run this file FIRST. It creates the table; the MySQL ledger file then tells
   artisan the migration is already applied so the next deploy does not retry it.

   WHY: a subdivision batch's children inherit the mother's recommendation, so no
   letter is printed for a child. The mother's signed letter is scanned once and
   hung off the batch; every child in the batch views that one document.

   Re-runnable: every statement is guarded.
   ============================================================================ */

IF OBJECT_ID('dbo.land_recommendation_batch_documents', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.land_recommendation_batch_documents (
        id              BIGINT IDENTITY(1,1) NOT NULL,

        /* The batch this letter covers. One scan per batch — enforced below. */
        rofo_batch_id   NVARCHAR(60)     NOT NULL,

        /* Denormalised so the record still reads on its own after the batch's
           children have been edited, re-batched or deleted. */
        mother_file_no  NVARCHAR(100)        NULL,

        /* Relative to the 'public' disk (storage/app/public). */
        path            NVARCHAR(500)    NOT NULL,
        original_name   NVARCHAR(255)        NULL,
        mime_type       NVARCHAR(120)        NULL,
        size_bytes      BIGINT               NULL,

        uploaded_by     BIGINT               NULL,
        uploaded_at     DATETIME             NULL,

        created_at      DATETIME             NULL,
        updated_at      DATETIME             NULL,

        CONSTRAINT PK_land_rec_batch_documents PRIMARY KEY CLUSTERED (id)
    );
END
GO

/* One document per batch. A re-upload UPDATEs this row rather than inserting a
   second, so "which is the current letter" is never a question. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
     WHERE name = 'UQ_land_rec_batch_documents_batch'
       AND object_id = OBJECT_ID('dbo.land_recommendation_batch_documents')
)
BEGIN
    CREATE UNIQUE INDEX UQ_land_rec_batch_documents_batch
        ON dbo.land_recommendation_batch_documents (rofo_batch_id);
END
GO

/* Verify */
SELECT COUNT(*) AS rows_now FROM dbo.land_recommendation_batch_documents;
GO
