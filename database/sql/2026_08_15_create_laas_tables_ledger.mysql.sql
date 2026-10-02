/* ============================================================================
   Register the five LAAS Portal migrations in the MySQL ledger
   ----------------------------------------------------------------------------
   This file runs against MYSQL. It creates no tables.

   WHY THIS FILE EXISTS
   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database, while the LAAS tables themselves live on
   SQL Server (every migration pins ->connection('sqlsrv')). SQL Server has its
   own `migrations` table, but it is a legacy copy artisan no longer writes to —
   do not use it to decide what has been deployed.

   WHAT THIS DOES
   Marks the five LAAS migrations as run so a later `php artisan migrate` does
   not attempt them again. All five are guarded by hasTable() and would be
   harmless no-ops either way — this only keeps the ledger honest.

   SAFETY
     - Re-runnable: every INSERT is guarded by NOT EXISTS.
     - Touches nothing but rows in `migrations`.
     - Skip this file entirely if you deploy by running `php artisan migrate`
       against a connection that reaches SQL Server; artisan writes these rows
       itself.
   ============================================================================ */

/* Preview — expect 0 before, and the batch the inserts will land in */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration LIKE '2026_08_15_00000%_laas%'
         OR migration LIKE '2026_08_15_00000%_create_laas%') AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                      AS current_max_batch;


/* ---------------------------------------------------------------------------
   1/5  laas_applicants
   --------------------------------------------------------------------------- */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_15_000000_create_laas_applicants_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_15_000000_create_laas_applicants_table'
 );

/* ---------------------------------------------------------------------------
   2/5  laas_applications
   --------------------------------------------------------------------------- */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_15_000001_create_laas_applications_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0)
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_15_000001_create_laas_applications_table'
 );

/* ---------------------------------------------------------------------------
   3/5  laas_application_events
   --------------------------------------------------------------------------- */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_15_000002_create_laas_application_events_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0)
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_15_000002_create_laas_application_events_table'
 );

/* ---------------------------------------------------------------------------
   4/5  laas_documents
   --------------------------------------------------------------------------- */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_15_000003_create_laas_documents_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0)
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_15_000003_create_laas_documents_table'
 );

/* ---------------------------------------------------------------------------
   5/5  laas_stage_notifications
   --------------------------------------------------------------------------- */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_15_000004_create_laas_stage_notifications_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0)
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_15_000004_create_laas_stage_notifications_table'
 );


/* Verify — expect exactly 5 */
SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration IN (
       '2026_08_15_000000_create_laas_applicants_table',
       '2026_08_15_000001_create_laas_applications_table',
       '2026_08_15_000002_create_laas_application_events_table',
       '2026_08_15_000003_create_laas_documents_table',
       '2026_08_15_000004_create_laas_stage_notifications_table'
 );
