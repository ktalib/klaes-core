/* ============================================================================
   Register the land_recommendation_batch_documents migration in the MySQL ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_08_28_land_recommendation_batch_documents.sql
   — RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database while the table itself lives on SQL
   Server. The SQL Server `migrations` table is a legacy copy artisan no longer
   writes to; inserting there registers nothing — and a migration left
   unregistered here will be re-attempted on the next deploy.

   Re-runnable (guarded by NOT EXISTS). Skip it entirely if you deploy with
   `php artisan migrate` — artisan writes this row itself.
   ============================================================================ */

/* Preview */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration = '2026_08_28_140000_create_land_recommendation_batch_documents_table') AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                                                       AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_28_140000_create_land_recommendation_batch_documents_table',
       (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m2)
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM migrations m
        WHERE m.migration = '2026_08_28_140000_create_land_recommendation_batch_documents_table'
 );

/* Verify */
SELECT migration, batch
  FROM migrations
 WHERE migration = '2026_08_28_140000_create_land_recommendation_batch_documents_table';

/* ----------------------------------------------------------------------------
   AFTER BOTH FILES HAVE RUN, confirm on the application box:

       php artisan tinker --execute="dump(Schema::connection('sqlsrv')->hasTable('land_recommendation_batch_documents'));"

   The uploads themselves land in storage/app/public/land_recommendations/batch_documents,
   which is reached through the public/storage symlink — so also confirm:

       php artisan storage:link

   Then open a subdivision batch on /land-recommendations?tab=batches, upload the
   mother's letter from the batch menu, and confirm every child row switches from
   "Upload" to "View".
   ---------------------------------------------------------------------------- */
