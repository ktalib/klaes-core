-- ============================================================================
-- SuA Confirmation Sheet — migration ledger (MYSQL / default connection)
--
-- artisan's `migrations` ledger lives in MySQL while the tables these
-- migrations touch live in SQL Server. Without these rows, the next
-- `php artisan migrate` on production would try to re-run all three.
--
-- Run this ONLY AFTER sua_confirmation_sheet_2026_08_21.sqlsrv.sql has been
-- applied successfully. Re-running is safe: nothing is inserted twice.
-- ============================================================================

-- One batch for all three, read before the insert. The derived tables are what
-- keep MySQL from refusing to read `migrations` while inserting into it (1093).
SET @batch := (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS b);

INSERT INTO migrations (migration, batch)
SELECT t.migration, @batch
FROM (
    SELECT '2026_08_21_100000_add_parcel_no_to_st_file_numbers_table' AS migration
    UNION ALL SELECT '2026_08_21_100100_add_institution_fields_to_conversion_applications'
    UNION ALL SELECT '2026_08_21_100200_normalize_allocation_source_lookup_names'
) AS t
LEFT JOIN (SELECT migration FROM migrations) AS existing
       ON existing.migration = t.migration
WHERE existing.migration IS NULL;

-- Verify: expect all three, sharing one batch number.
SELECT id, migration, batch
FROM   migrations
WHERE  migration LIKE '2026_08_21_1%'
ORDER  BY id;
