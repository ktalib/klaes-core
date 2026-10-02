/* ============================================================================
   Register the sltr_recommendations.date_issued migration in the MySQL ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_08_26_add_date_issued_to_sltr_recommendations.sql
   — RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   config('database.default') is `mysql`, so `php artisan migrate` keeps its ledger
   in the MySQL `klas` database while sltr_recommendations lives on SQL Server. The
   SQL Server `migrations` table is a legacy copy artisan no longer writes to;
   inserting there registers nothing.

   Re-runnable (guarded by NOT EXISTS), and touches nothing but one row. Skip it
   entirely if you deploy with `php artisan migrate` — artisan writes this row.
   ============================================================================ */

/* ----------------------------------------------------------------------------
   WRONG-SERVER CHECK — keep this first.

   Run against SQL Server, this file used to fail four statements in with
   "Invalid object name 'DUAL'", which says nothing about the real mistake.
   VERSION() is MySQL's; SQL Server answers "VERSION is not a recognized built-in
   function name", at the very first statement, before anything else is attempted.

   If you see that error: you are on the wrong connection. This file belongs to
   MySQL. The SQL Server half is the companion .sql file, and it is the one that
   adds the column.
   ---------------------------------------------------------------------------- */
SELECT VERSION() AS mysql_version_this_file_requires;

/* Preview */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration = '2026_08_26_090000_add_date_issued_to_sltr_recommendations') AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                                              AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_26_090000_add_date_issued_to_sltr_recommendations',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_26_090000_add_date_issued_to_sltr_recommendations'
 );

/* Verify — expect exactly 1 */
SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration = '2026_08_26_090000_add_date_issued_to_sltr_recommendations';
