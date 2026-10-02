/* ============================================================================
   SPAS offline-sync schema - VERIFICATION (read-only)
   ----------------------------------------------------------------------------
   Run against any environment after 2026_08_15_spas_offline_sync_schema.sql.
   Reads only sys.columns / sys.indexes and the two duplicate-precondition
   queries. Changes nothing.

   WHY A SEPARATE FILE
   The migration ledger for this project lives in MySQL while these tables live
   in SQL Server, so a change can be recorded as applied without ever reaching
   the database (plan §4.1). The only trustworthy answer comes from asking the
   database itself, which is what this does.

   HOW TO READ IT
   Every row of the final result set should say 'PASS'. Anything else names
   exactly what is missing and why it matters.
   ============================================================================ */

SET NOCOUNT ON;

DECLARE @results TABLE (
    seq      INT IDENTITY(1,1),
    check_name VARCHAR(60),
    state    VARCHAR(8),
    detail   NVARCHAR(300)
);

/* --- 1-2. Nullability widenings (STEP 1 and STEP 2) ---------------------- */
INSERT INTO @results (check_name, state, detail)
SELECT 'spa_notices.spa_application_id',
       CASE WHEN c.is_nullable = 1 THEN 'PASS' ELSE 'FAIL' END,
       CASE WHEN c.is_nullable = 1
            THEN 'nullable - a notice on a file with no SPAS application inserts'
            ELSE 'STILL NOT NULL - issuing a notice on a free-style file number 500s' END
  FROM sys.columns c
 WHERE c.object_id = OBJECT_ID('dbo.spa_notices') AND c.name = 'spa_application_id';

INSERT INTO @results (check_name, state, detail)
SELECT 'spa_field_data.spa_application_id',
       CASE WHEN c.is_nullable = 1 THEN 'PASS' ELSE 'FAIL' END,
       CASE WHEN c.is_nullable = 1
            THEN 'nullable - outbox can drain as a flat FIFO'
            ELSE 'STILL NOT NULL - offline push needs strict parent-first ordering' END
  FROM sys.columns c
 WHERE c.object_id = OBJECT_ID('dbo.spa_field_data') AND c.name = 'spa_application_id';

/* --- 3-5. New columns (STEP 3 and STEP 4) -------------------------------- */
INSERT INTO @results (check_name, state, detail)
SELECT x.label,
       CASE WHEN COL_LENGTH(x.tbl, x.col) IS NOT NULL THEN 'PASS' ELSE 'FAIL' END,
       CASE WHEN COL_LENGTH(x.tbl, x.col) IS NOT NULL
            THEN 'column present'
            ELSE 'COLUMN MISSING - the sync API will error on every push' END
  FROM (VALUES
        ('spa_applications.client_uuid',                'dbo.spa_applications', 'client_uuid'),
        ('spa_field_data.client_uuid',                  'dbo.spa_field_data',   'client_uuid'),
        ('spa_field_data.spa_application_client_uuid',  'dbo.spa_field_data',   'spa_application_client_uuid')
       ) AS x(label, tbl, col);

/* --- 6-9. Unique indexes (STEP 4, 5 and 6) ------------------------------- */
INSERT INTO @results (check_name, state, detail)
SELECT x.idx,
       CASE WHEN i.name IS NOT NULL THEN 'PASS' ELSE 'FAIL' END,
       CASE WHEN i.name IS NOT NULL THEN 'index present' ELSE x.why END
  FROM (VALUES
        ('UQ_spa_applications_client_uuid', 'dbo.spa_applications',
         'MISSING - idempotent create is a convention, not a guarantee; a retried push can duplicate'),
        ('UQ_spa_field_data_client_uuid',   'dbo.spa_field_data',
         'MISSING - idempotent create is a convention, not a guarantee; a retried push can duplicate'),
        ('UQ_spa_field_data_file_number',   'dbo.spa_field_data',
         'MISSING - likely duplicate file_number rows blocked it; two devices can both insert an inspection'),
        ('UQ_spa_applications_file_number', 'dbo.spa_applications',
         'MISSING - likely duplicate file_number rows blocked it; one-application-per-file is unenforced')
       ) AS x(idx, tbl, why)
  LEFT JOIN sys.indexes i
         ON i.name = x.idx AND i.object_id = OBJECT_ID(x.tbl);

/* --- 10-11. The STEP 0 preconditions, re-checked ------------------------- */
/* If an index above is missing, this is almost certainly why. Live duplicates
   also mean the business rule has already been violated in production data. */
INSERT INTO @results (check_name, state, detail)
SELECT 'duplicate spa_field_data.file_number',
       CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL' END,
       CASE WHEN COUNT(*) = 0 THEN 'none'
            ELSE CAST(COUNT(*) AS VARCHAR(10)) + ' file number(s) have more than one inspection - resolve, then re-run STEP 5' END
  FROM (SELECT file_number FROM dbo.spa_field_data
         WHERE file_number IS NOT NULL
         GROUP BY file_number HAVING COUNT(*) > 1) d;

INSERT INTO @results (check_name, state, detail)
SELECT 'duplicate spa_applications.file_number',
       CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL' END,
       CASE WHEN COUNT(*) = 0 THEN 'none'
            ELSE CAST(COUNT(*) AS VARCHAR(10)) + ' file number(s) have more than one application - resolve, then re-run STEP 6' END
  FROM (SELECT file_number FROM dbo.spa_applications
         WHERE file_number IS NOT NULL
         GROUP BY file_number HAVING COUNT(*) > 1) d;

/* --- Result -------------------------------------------------------------- */
SELECT seq, check_name, state, detail FROM @results ORDER BY seq;

SELECT CASE WHEN EXISTS (SELECT 1 FROM @results WHERE state <> 'PASS')
            THEN 'INCOMPLETE - see the FAIL rows above'
            ELSE 'ALL PASS - schema is ready for the /api/spas sync endpoints'
       END AS verdict,
       (SELECT COUNT(*) FROM @results WHERE state = 'PASS') AS passed,
       (SELECT COUNT(*) FROM @results WHERE state <> 'PASS') AS failed;

/* If any duplicate row above reported FAIL, list the offenders: */
SELECT 'spa_applications' AS tbl, file_number, COUNT(*) AS occurrences
  FROM dbo.spa_applications WHERE file_number IS NOT NULL
 GROUP BY file_number HAVING COUNT(*) > 1
UNION ALL
SELECT 'spa_field_data', file_number, COUNT(*)
  FROM dbo.spa_field_data WHERE file_number IS NOT NULL
 GROUP BY file_number HAVING COUNT(*) > 1;
