/* ============================================================================
   Online Legal Search — one payment, several files
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_09_02_add_multi_file_to_legal_search_online_payments_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark the migration as applied.

   WHAT THIS DOES
   Adds two columns to legal_search_online_payments so one payment can cover
   several Legal Searches, charged at the unit price per file.

     file_count   — how many files were charged for; the amount paid is verified
                    against unit price x this number
     file_numbers — JSON array of every file covered, primary first

   `file_number` is UNCHANGED and still holds the PRIMARY (first) file, so every
   existing lookup — the result page, the status page, the admin queue — keeps
   working without modification.

   Each file still gets its own legal_search_online_requests row sharing the same
   payment_id: a Legal Search report is a per-file legal document with its own
   particulars and signature, and a Director must be able to approve one file
   while rejecting another.

   SAFETY
     - Re-runnable: every ADD is guarded.
     - Existing rows are single-file; the DEFAULT of 1 describes them correctly,
       so no backfill is required.
     - Adds columns only. Touches no existing data.
   ============================================================================ */

IF OBJECT_ID('dbo.legal_search_online_payments', 'U') IS NOT NULL
BEGIN

    IF COL_LENGTH('dbo.legal_search_online_payments', 'file_count') IS NULL
    BEGIN
        ALTER TABLE dbo.legal_search_online_payments
            ADD file_count SMALLINT NOT NULL
                CONSTRAINT df_lsop_file_count DEFAULT (1);
        PRINT 'Added file_count.';
    END

    IF COL_LENGTH('dbo.legal_search_online_payments', 'file_numbers') IS NULL
    BEGIN
        ALTER TABLE dbo.legal_search_online_payments
            ADD file_numbers NVARCHAR(MAX) NULL;
        PRINT 'Added file_numbers.';
    END

END
ELSE
BEGIN
    PRINT 'dbo.legal_search_online_payments does not exist - nothing to do.';
END
GO

/* Constraint in its own batch, so the column above exists. */
IF OBJECT_ID('dbo.legal_search_online_payments', 'U') IS NOT NULL
   AND OBJECT_ID('chk_lsop_file_count', 'C') IS NULL
BEGIN
    ALTER TABLE dbo.legal_search_online_payments
        ADD CONSTRAINT chk_lsop_file_count CHECK (file_count >= 1);
END
GO

/* Verify — expect two rows */
SELECT name AS column_added
  FROM sys.columns
 WHERE object_id = OBJECT_ID('dbo.legal_search_online_payments')
   AND name IN ('file_count', 'file_numbers');
GO
