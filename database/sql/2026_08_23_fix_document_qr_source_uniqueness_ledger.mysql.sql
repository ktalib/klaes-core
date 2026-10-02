/* ============================================================================
   Register the document QR uniqueness fix in the MySQL migrations ledger
   ----------------------------------------------------------------------------
   Companion to database/sql/2026_08_23_fix_document_qr_source_uniqueness.sql
   — RUN THAT ONE FIRST, against SQL SERVER. This file runs against MYSQL.

   artisan keeps its ledger in MySQL while document_qr_codes lives on SQL
   Server. The SQL Server `migrations` table is a legacy copy artisan no longer
   writes to; inserting there registers nothing.

   Re-runnable (guarded by NOT EXISTS). Skip it if you deploy with
   `php artisan migrate` — artisan writes this row itself.
   ============================================================================ */

INSERT INTO migrations (migration, batch)
SELECT '2026_08_23_140000_fix_document_qr_source_uniqueness',
       (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m2)
  FROM DUAL
 WHERE NOT EXISTS (
       SELECT 1 FROM migrations m
        WHERE m.migration = '2026_08_23_140000_fix_document_qr_source_uniqueness'
 );

SELECT migration, batch FROM migrations
 WHERE migration = '2026_08_23_140000_fix_document_qr_source_uniqueness';
