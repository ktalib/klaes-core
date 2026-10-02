-- MySQL — the artisan migrations LEDGER lives here while the tables above live
-- in sqlsrv. Run this so the migration is not re-attempted in production.
INSERT INTO migrations (migration, batch)
SELECT '2026_08_28_090000_add_selected_record_to_digital_file_tracking_requests',
       (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m)
WHERE NOT EXISTS (
    SELECT 1 FROM migrations m2
    WHERE m2.migration = '2026_08_28_090000_add_selected_record_to_digital_file_tracking_requests'
);
