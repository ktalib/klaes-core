/* ============================================================================
   Register the sms_settings.sender migration in the MySQL ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_09_06_add_sender_to_sms_settings.sql --
   RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   config('database.default') is `mysql`, so artisan keeps its ledger there while
   the column lives on SQL Server. Re-runnable; touches one row in `migrations`.
   Skip it entirely if you deploy with `php artisan migrate`.
   ============================================================================ */

SELECT (SELECT COUNT(*) FROM migrations
         WHERE migration = '2026_09_06_000400_add_sender_to_sms_settings_table') AS ledger_rows_before,
       (SELECT MAX(batch) FROM migrations)                                       AS current_max_batch;

INSERT INTO migrations (migration, batch)
SELECT '2026_09_06_000400_add_sender_to_sms_settings_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_09_06_000400_add_sender_to_sms_settings_table'
 );

SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration = '2026_09_06_000400_add_sender_to_sms_settings_table';
