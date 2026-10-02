/* ============================================================================
   deeds_bill_balances_metadata.app_house_number / loc_house_number
   RUN THIS AGAINST SQL SERVER
   ----------------------------------------------------------------------------
   Companion: database/sql/2026_09_09_add_house_number_to_deeds_bill_balances_metadata_ledger.mysql.sql
   RUN THIS ONE FIRST, then that one against MySQL.

   WHY TWO FILES
   `php artisan migrate` keeps its ledger in the MySQL `klas` database, while
   this table lives on SQL Server. The sqlsrv `migrations` table is a legacy
   copy artisan no longer writes to — marking THAT one changes nothing.

   WHAT THIS DOES
   Adds the House No captured on the Generate Bill Balance wizard: one for the
   applicant's address, one for the property location. A house number is not a
   plot number, so it gets its own column on each side.

   SAFETY
     - Re-runnable: each ALTER is guarded by a COL_LENGTH check.
     - Two NULLable columns. No row is rewritten, no default is applied.
   ============================================================================ */

/* Preview — expect both to be absent (NULL) before the change */
SELECT COL_LENGTH('dbo.deeds_bill_balances_metadata', 'app_house_number') AS app_house_number_before,
       COL_LENGTH('dbo.deeds_bill_balances_metadata', 'loc_house_number') AS loc_house_number_before;

IF COL_LENGTH('dbo.deeds_bill_balances_metadata', 'app_house_number') IS NULL
BEGIN
    ALTER TABLE dbo.deeds_bill_balances_metadata
        ADD app_house_number NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.deeds_bill_balances_metadata', 'loc_house_number') IS NULL
BEGIN
    ALTER TABLE dbo.deeds_bill_balances_metadata
        ADD loc_house_number NVARCHAR(50) NULL;
END
GO

/* Verify — expect non-NULL lengths (100 = 50 NVARCHAR characters) */
SELECT COL_LENGTH('dbo.deeds_bill_balances_metadata', 'app_house_number') AS app_house_number_after,
       COL_LENGTH('dbo.deeds_bill_balances_metadata', 'loc_house_number') AS loc_house_number_after;
