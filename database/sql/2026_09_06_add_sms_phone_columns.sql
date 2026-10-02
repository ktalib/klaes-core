/* ============================================================================
   Somewhere to keep the applicant's phone number, for the transactional SMS
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the `klas` database on the sqlsrv connection).

   Companion: 2026_09_06_add_sms_phone_columns_ledger.mysql.sql, which runs
   against MYSQL and marks these two migrations applied. Run this one FIRST.

   WHY THESE COLUMNS
   Two of the records the new SMS report on had nowhere to keep a phone number:

     st_file_numbers  records applicant_title, first_name, surname,
                      corporate_name and rc_number -- and no way to contact the
                      person any of that describes.
     caveats          records petitioner and petitioner_address, and likewise
                      no way to reach them.

   Without these, both messages would fall back to file_indexings.phone, which
   is shared: one number in it sits on 1,211 different files. See
   App\Services\Sms\ApplicantPhoneResolver.

   NOTE ON `caveats`: that table was never created by a Laravel migration -- it
   comes from database/sql/caveat_system_database_setup.sql -- so on production
   this script, not artisan, is what actually adds the column.

   SAFETY
     - Re-runnable: each ALTER is guarded by a COL_LENGTH check.
     - Both columns are NULLable. Existing rows keep no number, which is
       correct: back-filling one would be inventing data.
     - No existing column or row is touched.
   ============================================================================ */

/* ---------------------------------------------------------------------------
   1. st_file_numbers.applicant_phone
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.st_file_numbers', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.st_file_numbers', 'applicant_phone') IS NULL
BEGIN
    ALTER TABLE dbo.st_file_numbers ADD applicant_phone NVARCHAR(100) NULL;
    PRINT 'Added dbo.st_file_numbers.applicant_phone';
END
ELSE
    PRINT 'dbo.st_file_numbers.applicant_phone already present (or table missing) - skipped';
GO

/* ---------------------------------------------------------------------------
   2. caveats.petitioner_phone
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.caveats', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.caveats', 'petitioner_phone') IS NULL
BEGIN
    ALTER TABLE dbo.caveats ADD petitioner_phone NVARCHAR(100) NULL;
    PRINT 'Added dbo.caveats.petitioner_phone';
END
ELSE
    PRINT 'dbo.caveats.petitioner_phone already present (or table missing) - skipped';
GO

/* ---------------------------------------------------------------------------
   Verify -- expect both to report 1.
   --------------------------------------------------------------------------- */
SELECT
    CASE WHEN COL_LENGTH('dbo.st_file_numbers', 'applicant_phone')  IS NULL THEN 0 ELSE 1 END AS st_applicant_phone,
    CASE WHEN COL_LENGTH('dbo.caveats', 'petitioner_phone')         IS NULL THEN 0 ELSE 1 END AS caveat_petitioner_phone;
GO
