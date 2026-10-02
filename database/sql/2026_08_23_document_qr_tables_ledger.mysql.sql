/* ============================================================================
   Register the document QR migration in the MySQL migrations ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_08_23_document_qr_tables.sql
   — RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database while document_qr_codes and its audit
   tables live on SQL Server. The SQL Server `migrations` table is a legacy copy
   artisan no longer writes to; inserting there registers nothing — and a
   migration left unregistered here will be re-attempted on the next deploy.

   Re-runnable (guarded by NOT EXISTS). Skip it entirely if you deploy with
   `php artisan migrate` — artisan writes this row itself.
   ============================================================================ */

/* Preview */
SELECT
    (SELECT COUNT(*) FROM migrations
      WHERE migration = '2026_08_23_090000_create_document_qr_tables') AS ledger_rows_before,
    (SELECT MAX(batch) FROM migrations)                                AS current_max_batch;

/* Insert, guarded */
INSERT INTO migrations (migration, batch)
SELECT '2026_08_23_090000_create_document_qr_tables', (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m2)
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM migrations m
        WHERE m.migration = '2026_08_23_090000_create_document_qr_tables'
 );

/* Verify */
SELECT migration, batch
  FROM migrations
 WHERE migration = '2026_08_23_090000_create_document_qr_tables';

/* ----------------------------------------------------------------------------
   AFTER BOTH FILES HAVE RUN, confirm on the application box:

       php artisan qr:doctor

   It reports whether the three tables actually exist on sqlsrv, whether the
   signing key is present (.env is gitignored, so it does NOT travel with a code
   upload), and whether mint/verify round-trips. Do not print Q1 QR codes until
   it passes.
   ---------------------------------------------------------------------------- */
