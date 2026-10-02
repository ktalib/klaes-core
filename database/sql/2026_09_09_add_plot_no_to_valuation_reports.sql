/* ============================================================================
   valuation_reports.plot_no  —  RUN THIS AGAINST SQL SERVER
   ----------------------------------------------------------------------------
   Companion: database/sql/2026_09_09_add_plot_no_to_valuation_reports_ledger.mysql.sql
   RUN THIS ONE FIRST, then that one against MySQL.

   WHY TWO FILES
   `php artisan migrate` keeps its ledger in the MySQL `klas` database, while
   this table lives on SQL Server. The sqlsrv `migrations` table is a legacy
   copy artisan no longer writes to — marking THAT one changes nothing.

   WHAT THIS DOES
   Adds the survey parcel number to a valuation report. property_no stays what
   it has always been — the number on the building — so the register can list
   House No and Plot No as separate columns.

   SAFETY
     - Re-runnable: guarded by a COL_LENGTH check.
     - One NULLable column. No row is rewritten, no default is applied.
   ============================================================================ */

/* Preview — expect plot_no to be absent (NULL) before the change */
SELECT COL_LENGTH('dbo.valuation_reports', 'plot_no') AS plot_no_before;

IF COL_LENGTH('dbo.valuation_reports', 'plot_no') IS NULL
BEGIN
    ALTER TABLE dbo.valuation_reports ADD plot_no NVARCHAR(100) NULL;
END
GO

/* Verify — expect a non-NULL length (200 = 100 NVARCHAR characters) */
SELECT COL_LENGTH('dbo.valuation_reports', 'plot_no') AS plot_no_after;
