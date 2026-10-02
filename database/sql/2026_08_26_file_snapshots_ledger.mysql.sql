/* ============================================================================
   Register the file_snapshots migration in the MySQL migrations ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_08_26_file_snapshots.sql
   — RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database while file_snapshots itself lives on SQL
   Server. The SQL Server `migrations` table is a legacy copy artisan no longer
   writes to; inserting there registers nothing — and a migration left
   unregistered here will be re-attempted on the next deploy.

   Re-runnable (guarded by NOT EXISTS). Skip it entirely if you deploy with
   `php artisan migrate` — artisan writes this row itself.
   ============================================================================ */

/* Preview */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration = '2026_08_26_150000_create_file_snapshots_table') AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                                  AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_26_150000_create_file_snapshots_table',
       (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m2)
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM migrations m
        WHERE m.migration = '2026_08_26_150000_create_file_snapshots_table'
 );

/* Verify */
SELECT migration, batch
  FROM migrations
 WHERE migration = '2026_08_26_150000_create_file_snapshots_table';

/* ----------------------------------------------------------------------------
   AFTER BOTH FILES HAVE RUN, confirm on the application box:

       php artisan tinker --execute="dump(Schema::connection('sqlsrv')->hasTable('file_snapshots'));"

   Then index one file through /fileindexing/create and confirm a row lands with
   version = 1 and event_type = 'indexed'. If the table is missing, snapshot
   capture fails silently by design — the save still succeeds and the two new
   cards are simply skipped, so an absent table shows up as missing cards rather
   than as an error.
   ---------------------------------------------------------------------------- */
