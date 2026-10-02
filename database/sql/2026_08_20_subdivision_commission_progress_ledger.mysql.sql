/* ============================================================================
   Register the subdivision commission-progress migration  (MYSQL)
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_08_20_subdivision_commission_progress.sql -
   RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   WHY TWO FILES
   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database, while plot_subdivision_applications
   lives on SQL Server (the migration and the model both pin
   ->connection('sqlsrv')). SQL Server has its own `migrations` table, but it is
   a legacy copy artisan no longer writes to - inserting the ledger row there
   registers nothing.

   WHAT THIS DOES
   Marks 2026_08_20_140000_add_commission_progress_to_plot_subdivision_applications
   as run so a later `php artisan migrate` does not attempt it. The migration is
   guarded by hasColumn() and would be a harmless no-op either way; this only
   keeps the ledger honest about what is deployed.
   ========================================================================== */

USE klas;

INSERT INTO migrations (migration, batch)
SELECT '2026_08_20_140000_add_commission_progress_to_plot_subdivision_applications',
       (SELECT COALESCE(MAX(batch), 0) FROM migrations m)
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT migration FROM migrations) x
     WHERE x.migration = '2026_08_20_140000_add_commission_progress_to_plot_subdivision_applications'
);

/* VERIFY - must return exactly one row */
SELECT migration, batch
  FROM migrations
 WHERE migration = '2026_08_20_140000_add_commission_progress_to_plot_subdivision_applications';
