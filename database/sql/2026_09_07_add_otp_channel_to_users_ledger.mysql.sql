/* ============================================================================
   Register the users.otp_channel migration in the MySQL ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_09_07_add_otp_channel_to_users.sql —
   RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   WHY TWO FILES
   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database, while the column itself lives on SQL
   Server (the migration pins ->connection('sqlsrv')). SQL Server has its own
   `migrations` table, but it is a legacy copy artisan no longer writes to —
   marking THAT one changes nothing.

   WHAT THIS DOES
   Marks 2026_09_07_000000_add_otp_channel_to_users_table as run, so a later
   `php artisan migrate` does not attempt it. The migration is guarded by
   hasColumn() and would be a harmless no-op either way — this only keeps the
   ledger honest about what is already deployed.

   SAFETY
     - Re-runnable: the INSERT is guarded by NOT EXISTS.
     - Touches nothing but one row in `migrations`.
     - Skip this file entirely if you deploy with `php artisan migrate`, which
       writes this row itself.
   ============================================================================ */

/* Preview */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration = '2026_09_07_000000_add_otp_channel_to_users_table') AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                                     AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_09_07_000000_add_otp_channel_to_users_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_09_07_000000_add_otp_channel_to_users_table'
 );

/* Verify — expect exactly 1 */
SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration = '2026_09_07_000000_add_otp_channel_to_users_table';
