/* ============================================================================
   Register the SMS phone-column migrations in the MySQL ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_09_06_add_sms_phone_columns.sql --
   RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   WHY TWO FILES
   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database, while the columns themselves live on SQL
   Server (both migrations pin ->connection('sqlsrv')). SQL Server has its own
   `migrations` table, but it is a legacy copy artisan no longer writes to.

   SAFETY
     - Re-runnable: each INSERT is guarded by NOT EXISTS.
     - Touches nothing but two rows in `migrations`.
     - Skip this file entirely if you deploy by running `php artisan migrate`;
       artisan writes these rows itself.
   ============================================================================ */

/* Preview */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration IN ('2026_09_06_000200_add_applicant_phone_to_st_file_numbers_table',
                          '2026_09_06_000300_add_petitioner_phone_to_caveats_table')) AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                                               AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_09_06_000200_add_applicant_phone_to_st_file_numbers_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_09_06_000200_add_applicant_phone_to_st_file_numbers_table'
 );

INSERT INTO migrations (migration, batch)
SELECT '2026_09_06_000300_add_petitioner_phone_to_caveats_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0)
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_09_06_000300_add_petitioner_phone_to_caveats_table'
 );

/* Verify -- expect exactly 2 */
SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration IN ('2026_09_06_000200_add_applicant_phone_to_st_file_numbers_table',
                     '2026_09_06_000300_add_petitioner_phone_to_caveats_table');
