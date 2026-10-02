/* ============================================================================
   Duplex Parcel Update - migrations LEDGER row (MYSQL)

   Run this against the MySQL 'klas' database, AFTER the SQL Server schema file
   2026_08_19_create_duplex_parcel_update_tables.sql has been applied.

   Why a separate file: config('database.default') is mysql, so artisan writes its
   migrations ledger to MySQL even though these tables are created on SQL Server.
   SQL Server also has a `migrations` table, but artisan no longer writes to it -
   a ledger insert placed in the sqlsrv script lands in that decoy and the next
   'php artisan migrate' on production re-attempts the migration.

   Idempotent: the insert is guarded on the migration name.
   ============================================================================ */

INSERT INTO migrations (migration, batch)
SELECT
    '2026_08_19_000000_create_duplex_parcel_update_tables',
    (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m)
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM migrations m2
    WHERE m2.migration = '2026_08_19_000000_create_duplex_parcel_update_tables'
);

SELECT
    migration,
    batch,
    'ledger row present - artisan will not re-run this migration' AS status
FROM migrations
WHERE migration = '2026_08_19_000000_create_duplex_parcel_update_tables';
