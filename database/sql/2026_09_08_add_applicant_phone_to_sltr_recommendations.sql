/* ============================================================================
   sltr_recommendations.applicant_phone  —  RUN THIS AGAINST SQL SERVER
   ----------------------------------------------------------------------------
   Companion: database/sql/2026_09_08_add_applicant_phone_to_sltr_recommendations_ledger.mysql.sql
   RUN THIS ONE FIRST, then that one against MySQL.

   WHY TWO FILES
   `php artisan migrate` keeps its ledger in the MySQL `klas` database, while
   this table lives on SQL Server. The sqlsrv `migrations` table is a legacy
   copy artisan no longer writes to — marking THAT one changes nothing.

   SAFETY
     - Re-runnable: guarded by a COL_LENGTH check.
     - Adds one NULLable column. No row is rewritten, no default is applied.
   ============================================================================ */

/* Preview — expect applicant_phone to be absent (NULL) before the change */
SELECT COL_LENGTH('dbo.sltr_recommendations', 'applicant_phone') AS applicant_phone_before;

IF COL_LENGTH('dbo.sltr_recommendations', 'applicant_phone') IS NULL
BEGIN
    ALTER TABLE dbo.sltr_recommendations
        ADD applicant_phone NVARCHAR(20) NULL;
END
GO

/* Verify — expect a non-NULL length (40 = 20 NVARCHAR characters) */
SELECT COL_LENGTH('dbo.sltr_recommendations', 'applicant_phone') AS applicant_phone_after;
