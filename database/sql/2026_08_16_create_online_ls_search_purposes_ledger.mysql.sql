/* ============================================================================
   Register the Online Legal Search purpose migrations in the MySQL ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_08_16_create_online_ls_search_purposes.sql —
   RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   WHY TWO FILES
   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database, while the tables live on SQL Server
   (the migrations pin ->connection('sqlsrv')). SQL Server has its own
   `migrations` table, but it is a legacy copy artisan no longer writes to.

   WHAT THIS DOES
   Marks both purpose migrations as run so a later `php artisan migrate` does
   not attempt them. Both are guarded by hasTable()/hasColumn() and would be
   harmless no-ops either way — this only keeps the ledger honest.

   SAFETY
     - Re-runnable: each INSERT is guarded by NOT EXISTS.
     - Touches nothing but two rows in `migrations`.
     - Skip this file entirely if you deploy by running `php artisan migrate`.
   ============================================================================ */

/* Preview */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration IN (
        '2026_08_16_000000_create_online_ls_search_purposes_table',
        '2026_08_16_000001_add_purpose_to_legal_search_online_requests'
      ))                                AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations) AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_16_000000_create_online_ls_search_purposes_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_16_000000_create_online_ls_search_purposes_table'
 );

INSERT INTO migrations (migration, batch)
SELECT '2026_08_16_000001_add_purpose_to_legal_search_online_requests',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0)
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_16_000001_add_purpose_to_legal_search_online_requests'
 );

/* Verify — expect exactly 2 */
SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration IN (
    '2026_08_16_000000_create_online_ls_search_purposes_table',
    '2026_08_16_000001_add_purpose_to_legal_search_online_requests'
 );
