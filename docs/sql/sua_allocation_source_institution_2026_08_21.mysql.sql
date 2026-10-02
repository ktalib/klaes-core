-- ============================================================================
-- SuA "Allocation Source" = the allocating institution — migration ledger
-- (MYSQL / default connection)
--
-- artisan's `migrations` ledger lives in MySQL while the table this migration
-- touches lives in SQL Server. Without this row, the next `php artisan migrate`
-- on production would try to re-run it.
--
-- Run this ONLY AFTER sua_allocation_source_institution_2026_08_21.sqlsrv.sql
-- has been applied successfully. Re-running is safe: nothing is inserted twice.
-- ============================================================================

-- Read before the insert. The derived tables are what keep MySQL from refusing
-- to read `migrations` while inserting into it (1093).
SET @batch := (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS b);

INSERT INTO migrations (migration, batch)
SELECT t.migration, @batch
FROM (
    SELECT '2026_08_21_110000_add_institution_fields_to_st_file_numbers_table' AS migration
) AS t
LEFT JOIN (SELECT migration FROM migrations) AS existing
       ON existing.migration = t.migration
WHERE existing.migration IS NULL;

-- Verify: expect exactly one row.
SELECT id, migration, batch
FROM   migrations
WHERE  migration = '2026_08_21_110000_add_institution_fields_to_st_file_numbers_table';
