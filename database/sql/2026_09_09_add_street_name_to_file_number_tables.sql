/* ============================================================================
   mls_file_no.street_name / fileNumber.street_name  —  RUN THIS AGAINST SQL SERVER
   ----------------------------------------------------------------------------
   Companion: database/sql/2026_09_09_add_street_name_to_file_number_tables_ledger.mysql.sql
   RUN THIS ONE FIRST, then that one against MySQL.

   WHY TWO FILES
   `php artisan migrate` keeps its ledger in the MySQL `klas` database, while
   these tables live on SQL Server. The sqlsrv `migrations` table is a legacy
   copy artisan no longer writes to — marking THAT one changes nothing.

   WHAT THIS DOES
   Adds the Street Name captured on the MLS file-number generator's Location
   Details card, beside the TP Number. It is not the District (a whole quarter)
   and not the Location (the line assembled from the parts), so it gets its own
   column. file_indexings already has a street_name column and is untouched.

   SAFETY
     - Re-runnable: each ALTER is guarded by a COL_LENGTH check.
     - Two NULLable columns. No row is rewritten, no default is applied.
   ============================================================================ */

/* Preview — expect both to be absent (NULL) before the change */
SELECT COL_LENGTH('dbo.mls_file_no', 'street_name') AS mls_file_no_street_name_before,
       COL_LENGTH('dbo.fileNumber',  'street_name') AS fileNumber_street_name_before;

IF COL_LENGTH('dbo.mls_file_no', 'street_name') IS NULL
BEGIN
    ALTER TABLE dbo.mls_file_no ADD street_name NVARCHAR(255) NULL;
END
GO

IF COL_LENGTH('dbo.fileNumber', 'street_name') IS NULL
BEGIN
    ALTER TABLE dbo.fileNumber ADD street_name NVARCHAR(255) NULL;
END
GO

/* Verify — expect non-NULL lengths (510 = 255 NVARCHAR characters) */
SELECT COL_LENGTH('dbo.mls_file_no', 'street_name') AS mls_file_no_street_name_after,
       COL_LENGTH('dbo.fileNumber',  'street_name') AS fileNumber_street_name_after;
