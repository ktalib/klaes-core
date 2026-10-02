/* ============================================================================
   Batch wording, and room for the file numbers one message covers
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the `klas` database on the sqlsrv connection).
   Companion: 2026_09_06_add_batch_wording_to_sms_tables_ledger.mysql.sql (MySQL).

   WHY
   A batch commissioning now sends ONE message per phone number rather than one
   per file, so a single message can be about five files -- or two hundred.

     sms_settings.plural_template
       "A New File with FileNo X" does not work for five files. The plural form
       ("New Files with FileNos RES-2026-3029-3033 have been commissioned...")
       is editable in the SMS Control Centre beside the singular so the two stay
       in step. NULL means "use the plural form shipped in config/klaes_sms.php".

     sms_dispatch_logs.file_number  100 -> 1000
       That column answers "was this file's applicant told?". One row now covers
       several files, so it holds the rendered range, or a joined list when the
       serials are not contiguous. 100 characters is not enough for that.

   SAFETY
     - Re-runnable: the ADD is guarded by COL_LENGTH; the widening ALTER is
       idempotent and only ever grows the column.
     - Widening NVARCHAR cannot truncate existing data.
     - No row is rewritten.
   ============================================================================ */

IF OBJECT_ID('dbo.sms_settings', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.sms_settings', 'plural_template') IS NULL
BEGIN
    ALTER TABLE dbo.sms_settings ADD plural_template NVARCHAR(MAX) NULL;
    PRINT 'Added dbo.sms_settings.plural_template';
END
ELSE
    PRINT 'dbo.sms_settings.plural_template already present (or table missing) - skipped';
GO

IF OBJECT_ID('dbo.sms_dispatch_logs', 'U') IS NOT NULL
BEGIN
    ALTER TABLE dbo.sms_dispatch_logs ALTER COLUMN file_number NVARCHAR(1000) NULL;
    PRINT 'Widened dbo.sms_dispatch_logs.file_number to NVARCHAR(1000)';
END
GO

/* Verify -- expect 1 and 1000 */
SELECT
    CASE WHEN COL_LENGTH('dbo.sms_settings', 'plural_template') IS NULL THEN 0 ELSE 1 END AS plural_template_present,
    COL_LENGTH('dbo.sms_dispatch_logs', 'file_number') / 2 AS file_number_chars;
GO
