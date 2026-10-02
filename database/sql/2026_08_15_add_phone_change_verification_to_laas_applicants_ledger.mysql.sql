/* ============================================================================
   Register the LAAS phone-change verification migration in the MySQL ledger
   ----------------------------------------------------------------------------
   This file runs against MYSQL. It creates and alters nothing.

   Companion to
   database/migrations/2026_08_15_000005_add_phone_change_verification_to_laas_applicants_table.php,
   which adds pending_phone / verification_code / verification_code_expires_at /
   verification_attempts to `laas_applicants` on SQL SERVER — run that first.

   WHY THIS FILE EXISTS
   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database, while the table itself lives on SQL
   Server (the migration pins ->connection('sqlsrv')). SQL Server has its own
   `migrations` table, but it is a legacy copy artisan no longer writes to.

   SAFETY
     - Re-runnable: the INSERT is guarded by NOT EXISTS.
     - Touches nothing but one row in `migrations`.
     - Skip this file entirely if you deploy with `php artisan migrate` against
       a connection that reaches SQL Server; artisan writes this row itself.
   ============================================================================ */

/* Preview — expect 0 before */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration = '2026_08_15_000005_add_phone_change_verification_to_laas_applicants_table') AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                                                             AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_15_000005_add_phone_change_verification_to_laas_applicants_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_15_000005_add_phone_change_verification_to_laas_applicants_table'
 );

/* Verify — expect exactly 1 */
SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration = '2026_08_15_000005_add_phone_change_verification_to_laas_applicants_table';
