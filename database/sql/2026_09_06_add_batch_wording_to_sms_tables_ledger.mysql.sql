/* ============================================================================
   Register the batch-wording migration in the MySQL ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_09_06_add_batch_wording_to_sms_tables.sql --
   RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   artisan keeps its ledger in MySQL while these columns live on SQL Server.
   Re-runnable; touches one row in `migrations`. Skip it entirely if you deploy
   with `php artisan migrate`.
   ============================================================================ */

SELECT (SELECT COUNT(*) FROM migrations
         WHERE migration = '2026_09_06_000500_add_batch_wording_to_sms_tables') AS ledger_rows_before,
       (SELECT MAX(batch) FROM migrations)                                      AS current_max_batch;

INSERT INTO migrations (migration, batch)
SELECT '2026_09_06_000500_add_batch_wording_to_sms_tables',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_09_06_000500_add_batch_wording_to_sms_tables'
 );

SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration = '2026_09_06_000500_add_batch_wording_to_sms_tables';
