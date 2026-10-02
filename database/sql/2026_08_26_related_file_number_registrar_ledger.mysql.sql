/* ============================================================================
   Register the related_file_number index migration in the MySQL ledger
   ----------------------------------------------------------------------------
   This file runs against MYSQL. There is no SQL Server companion to run first:
   2026_08_26_120000_drop_unique_source_index_on_related_file_number is a no-op
   on the production schema, because the live related_file_number table was built
   from database/migrations/manual/create_related_file_number_table.sql, which
   never created uq_rfn_source. The migration only matters to environments built
   from the Laravel migration instead.

   WHY THIS FILE EXISTS
   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database while the tables live on SQL Server (the
   migration pins ->connection('sqlsrv')). The SQL Server `migrations` table is a
   legacy copy artisan no longer writes to.

   SAFETY
     - Re-runnable: the INSERT is guarded by NOT EXISTS.
     - Touches nothing but one row in `migrations`.
     - Skip this file entirely if you deploy with `php artisan migrate`; artisan
       writes the row itself, and the migration is a guarded no-op regardless.
   ============================================================================ */

/* Preview */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration = '2026_08_26_120000_drop_unique_source_index_on_related_file_number')
                                            AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)     AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_26_120000_drop_unique_source_index_on_related_file_number',
       COALESCE((SELECT MAX(m.batch) FROM (SELECT batch FROM migrations) m), 0) + 1
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT migration FROM migrations) x
        WHERE x.migration = '2026_08_26_120000_drop_unique_source_index_on_related_file_number'
 );

/* Verify — expect exactly 1 */
SELECT COUNT(*) AS ledger_rows_after
  FROM migrations
 WHERE migration = '2026_08_26_120000_drop_unique_source_index_on_related_file_number';

/* ----------------------------------------------------------------------------
   AFTER DEPLOY, on SQL SERVER, replay the register backfill for any rows that
   were saved on production while File Indexing still wrote only the JSON column:

       php artisan related-files:backfill --dry-run
       php artisan related-files:backfill

   Idempotent; it never deletes. Rollback of a backfill run, if ever needed:
       DELETE FROM related_file_number
        WHERE source_table = 'file_indexings'
          AND transaction_type = 'Related File'
          AND CAST(created_at AS DATE) = '<the run date>';
   ---------------------------------------------------------------------------- */
