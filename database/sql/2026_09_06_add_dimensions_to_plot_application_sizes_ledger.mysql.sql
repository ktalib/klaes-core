/* ============================================================================
   Register the plot-dimensions migration in the MySQL ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_09_06_add_dimensions_to_plot_application_sizes.sql
   -- RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   WHY TWO FILES
   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database, while the column itself lives on SQL
   Server (the migration pins ->connection('sqlsrv')). SQL Server has its own
   `migrations` table, but it is a legacy copy artisan no longer writes to.

   SAFETY
     - Re-runnable: the INSERT is guarded by NOT EXISTS.
     - Touches nothing but one row in `migrations`.
     - Skip this file entirely if you deploy by running `php artisan migrate`;
       artisan writes this row itself.
   ============================================================================ */

/* Preview */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration = '2026_09_06_000400_add_dimensions_to_plot_application_sizes') AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                                              AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_09_06_000400_add_dimensions_to_plot_application_sizes',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_09_06_000400_add_dimensions_to_plot_application_sizes'
 );

/* Verify -- expect exactly 1 */
SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration = '2026_09_06_000400_add_dimensions_to_plot_application_sizes';
