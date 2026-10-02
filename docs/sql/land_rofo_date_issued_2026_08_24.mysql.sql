-- ============================================================================
-- Land RofO date_issued — migration ledger (MYSQL / default connection)
--
-- artisan's `migrations` ledger lives in MySQL while the table this migration
-- touches lives in SQL Server. Without this row, the next `php artisan migrate`
-- on production would try to re-run it.
--
-- Run this ONLY AFTER land_rofo_date_issued_2026_08_24.sqlsrv.sql has been
-- applied successfully. Re-running is safe: nothing is inserted twice.
-- ============================================================================

-- Read before the insert. The derived tables are what keep MySQL from refusing
-- to read `migrations` while inserting into it (1093).
SET @batch := (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS b);

INSERT INTO migrations (migration, batch)
SELECT t.migration, @batch
FROM (
    SELECT '2026_08_24_090000_add_date_issued_to_land_recommendations' AS migration
) AS t
LEFT JOIN (SELECT migration FROM migrations) AS existing
       ON existing.migration = t.migration
WHERE existing.migration IS NULL;

-- Verify: expect exactly one row.
SELECT id, migration, batch
FROM   migrations
WHERE  migration = '2026_08_24_090000_add_date_issued_to_land_recommendations';
