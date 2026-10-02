/* ============================================================================
   Register the LAAS SRO-form migration in the MySQL ledger
   ----------------------------------------------------------------------------
   Runs against MYSQL. Creates and alters nothing.

   Companion to
   database/migrations/2026_08_15_000006_add_sro_form_fields_to_laas_applications_table.php,
   which adds land_type and form_data to `laas_applications` on SQL SERVER —
   run that first.

   config('database.default') is `mysql`, so artisan keeps its ledger in the
   MySQL `klas` database while the table lives on SQL Server. The `migrations`
   table on SQL Server is a legacy copy artisan no longer writes to.

   Re-runnable: the INSERT is guarded by NOT EXISTS.
   ============================================================================ */

SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration = '2026_08_15_000006_add_sro_form_fields_to_laas_applications_table') AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                                                     AS current_max_batch;

INSERT INTO migrations (migration, batch)
SELECT '2026_08_15_000006_add_sro_form_fields_to_laas_applications_table',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_15_000006_add_sro_form_fields_to_laas_applications_table'
 );

SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration = '2026_08_15_000006_add_sro_form_fields_to_laas_applications_table';
