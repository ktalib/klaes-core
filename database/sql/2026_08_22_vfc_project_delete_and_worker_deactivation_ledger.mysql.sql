/* ============================================================================
   Register the VFC project-delete / worker-deactivation migrations in the
   MySQL migrations ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_08_22_vfc_project_delete_and_worker_deactivation.sql
   — RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database while vfc_projects / vfc_workers live on
   SQL Server. The SQL Server `migrations` table is a legacy copy artisan no
   longer writes to; inserting there registers nothing.

   Re-runnable (guarded by NOT EXISTS). Skip it entirely if you deploy with
   `php artisan migrate` — artisan writes these rows itself.
   ============================================================================ */

/* Preview */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration IN ('2026_08_22_120000_add_soft_delete_to_vfc_projects_table',
                          '2026_08_22_120100_add_deactivation_fields_to_vfc_workers_table')) AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                                                      AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_22_120000_add_soft_delete_to_vfc_projects_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_22_120000_add_soft_delete_to_vfc_projects_table'
 );

INSERT INTO migrations (migration, batch)
SELECT '2026_08_22_120100_add_deactivation_fields_to_vfc_workers_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0)
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_22_120100_add_deactivation_fields_to_vfc_workers_table'
 );

/* Verify — expect exactly 2 */
SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration IN ('2026_08_22_120000_add_soft_delete_to_vfc_projects_table',
                     '2026_08_22_120100_add_deactivation_fields_to_vfc_workers_table');
