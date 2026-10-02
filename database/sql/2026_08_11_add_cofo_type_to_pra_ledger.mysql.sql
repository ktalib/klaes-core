/* ============================================================================
   Register the pra.cofo_type migration in the MySQL migrations ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_08_11_add_cofo_type_to_pra.sql
   — RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   config('database.default') is `mysql`, so `php artisan migrate` keeps its ledger
   in the MySQL `klas` database while `pra` lives on SQL Server. The SQL Server
   `migrations` table is a legacy copy artisan no longer writes to; inserting there
   registers nothing.

   Re-runnable (guarded by NOT EXISTS), and touches nothing but one row. Skip it
   entirely if you deploy with `php artisan migrate` — artisan writes this row.
   ============================================================================ */

/* Preview */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration = '2026_08_11_170000_add_cofo_type_to_pra_table') AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                                 AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_11_170000_add_cofo_type_to_pra_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_11_170000_add_cofo_type_to_pra_table'
 );

/* Verify — expect exactly 1 */
SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration = '2026_08_11_170000_add_cofo_type_to_pra_table';
