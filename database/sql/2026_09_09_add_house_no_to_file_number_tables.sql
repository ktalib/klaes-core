/* ============================================================================
   mls_file_no.house_no / fileNumber.house_no  —  RUN THIS AGAINST SQL SERVER
   ----------------------------------------------------------------------------
   Companion: database/sql/2026_09_09_add_house_no_to_file_number_tables_ledger.mysql.sql
   RUN THIS ONE FIRST, then that one against MySQL.

   WHY TWO FILES
   `php artisan migrate` keeps its ledger in the MySQL `klas` database, while
   these tables live on SQL Server. The sqlsrv `migrations` table is a legacy
   copy artisan no longer writes to — marking THAT one changes nothing.

   WHAT THIS DOES
   Adds the House No captured on the MLS file-number generator's Location
   Details card. It is separate from plot_no: the plot is the survey parcel,
   the house number is what is on the building.

   SAFETY
     - Re-runnable: each ALTER is guarded by a COL_LENGTH check.
     - Two NULLable columns. No row is rewritten, no default is applied.
   ============================================================================ */

/* Preview — expect both to be absent (NULL) before the change */
SELECT COL_LENGTH('dbo.mls_file_no', 'house_no') AS mls_file_no_house_no_before,
       COL_LENGTH('dbo.fileNumber',  'house_no') AS fileNumber_house_no_before;

IF COL_LENGTH('dbo.mls_file_no', 'house_no') IS NULL
BEGIN
    ALTER TABLE dbo.mls_file_no ADD house_no NVARCHAR(100) NULL;
END
GO

IF COL_LENGTH('dbo.fileNumber', 'house_no') IS NULL
BEGIN
    ALTER TABLE dbo.fileNumber ADD house_no NVARCHAR(100) NULL;
END
GO

/* Verify — expect non-NULL lengths (200 = 100 NVARCHAR characters) */
SELECT COL_LENGTH('dbo.mls_file_no', 'house_no') AS mls_file_no_house_no_after,
       COL_LENGTH('dbo.fileNumber',  'house_no') AS fileNumber_house_no_after;
