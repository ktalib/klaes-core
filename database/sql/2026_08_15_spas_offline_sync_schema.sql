/* ============================================================================
   SPAS — schema fixes from the offline-sync schema audit
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database). No companion file —
   there is no PHP migration for this change, so there is no ledger row to write.

   WHY A SCRIPT AND NOT `php artisan migrate`
   This is the exact failure this script also repairs. Migration
   2026_06_18_000002_make_spa_application_id_nullable_on_spa_notices is recorded
   as run, yet its ALTER never reached SQL Server — spa_notices.spa_application_id
   is still NOT NULL in the live database, and artisan will never retry it because
   the ledger says it is done. Apply schema changes to sqlsrv directly and verify
   with INFORMATION_SCHEMA afterwards; do not trust the ledger.
   Reference: docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md
   §14.3 (findings) and §4.1 (the deployment trap).

   WHAT THIS DOES
     1. spa_notices.spa_application_id      -> NULL   (fixes a live 500)
     2. spa_field_data.spa_application_id   -> NULL   (offline push ordering)
     3. + spa_field_data.spa_application_client_uuid  (parent link before sync)
     4. + client_uuid on both tables, each UNIQUE      (idempotent create)
     5. + filtered UNIQUE index on spa_field_data.file_number

   SAFETY
     - Re-runnable: every statement is guarded by a COL_LENGTH / sys.indexes check.
     - Only widens nullability and adds nullable columns. No existing data is
       read, rewritten, or deleted.
     - Both ALTER COLUMN statements were verified against the live indexes
       (IX_spa_notices_app_type, IX_spa_field_data_app_id) inside a rolled-back
       transaction — SQL Server permits a nullability widening on those columns.
     - STEP 0 must return zero rows before the UNIQUE indexes in STEP 5 can be
       created. Run it first on every environment; a duplicate aborts that step
       only, leaving steps 1-4 applied.
   ============================================================================ */

/* ---------------------------------------------------------------------------
   STEP 0 — PRECONDITION. Run this on its own first.
   Both queries must return ZERO rows. If either returns rows, resolve the
   duplicates before running STEP 5; steps 1-4 are safe regardless.
   (Verified zero on the development database, 2026-08-15. Production is a
   different database and must be checked separately.)
   --------------------------------------------------------------------------- */
SELECT 'spa_field_data' AS tbl, file_number, COUNT(*) AS occurrences
  FROM dbo.spa_field_data
 WHERE file_number IS NOT NULL
 GROUP BY file_number
HAVING COUNT(*) > 1;
GO

SELECT 'spa_applications' AS tbl, file_number, COUNT(*) AS occurrences
  FROM dbo.spa_applications
 WHERE file_number IS NOT NULL
 GROUP BY file_number
HAVING COUNT(*) > 1;
GO


/* ---------------------------------------------------------------------------
   STEP 1 — spa_notices.spa_application_id becomes nullable.

   FIXES A LIVE BUG. storeNotice() deliberately supports notices against any
   file number, indexed or not, and passes NULL when no SPAS application exists
   for it. Against a NOT NULL column that insert throws, so issuing a notice on
   a free-style file number 500s today. This is migration 2026_06_18_000002
   finally reaching the database it was written for.
   --------------------------------------------------------------------------- */
IF EXISTS (SELECT 1 FROM sys.columns
            WHERE object_id = OBJECT_ID('dbo.spa_notices')
              AND name = 'spa_application_id'
              AND is_nullable = 0)
BEGIN
    ALTER TABLE dbo.spa_notices ALTER COLUMN spa_application_id BIGINT NULL;
END;
GO


/* ---------------------------------------------------------------------------
   STEP 2 — spa_field_data.spa_application_id becomes nullable.

   Required by the offline sync design. A field inspection captured offline is
   created before its parent application has a server id, so the child cannot
   carry spa_application_id at push time. Keeping the column NOT NULL forces
   strict parent-first ordering in the sync outbox, where a parent that fails
   to push blocks its child indefinitely — with the surveyor long gone from the
   site. See the plan §6.1.
   --------------------------------------------------------------------------- */
IF EXISTS (SELECT 1 FROM sys.columns
            WHERE object_id = OBJECT_ID('dbo.spa_field_data')
              AND name = 'spa_application_id'
              AND is_nullable = 0)
BEGIN
    ALTER TABLE dbo.spa_field_data ALTER COLUMN spa_application_id BIGINT NULL;
END;
GO


/* ---------------------------------------------------------------------------
   STEP 3 — spa_field_data.spa_application_client_uuid

   The offline linkage key. A child pushed before (or alongside) its parent
   names the parent by the UUID the device generated, and the server resolves
   it to spa_application_id once the parent lands. Lets the outbox drain as a
   flat FIFO instead of a dependency graph.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.spa_field_data', 'spa_application_client_uuid') IS NULL
BEGIN
    ALTER TABLE dbo.spa_field_data ADD spa_application_client_uuid NVARCHAR(36) NULL;
END;
GO


/* ---------------------------------------------------------------------------
   STEP 4 — client_uuid on both tables, each UNIQUE.

   Device-generated id that makes a create idempotent: the app retrying a push
   it already made (dropped connection, app restart) inserts once, not twice.
   The UNIQUE index is what makes that a guarantee rather than a convention —
   filtered so the existing rows, which have no client_uuid, do not collide.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.spa_applications', 'client_uuid') IS NULL
BEGIN
    ALTER TABLE dbo.spa_applications ADD client_uuid NVARCHAR(36) NULL;
END;
GO

IF COL_LENGTH('dbo.spa_field_data', 'client_uuid') IS NULL
BEGIN
    ALTER TABLE dbo.spa_field_data ADD client_uuid NVARCHAR(36) NULL;
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'UQ_spa_applications_client_uuid'
                  AND object_id = OBJECT_ID('dbo.spa_applications'))
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UQ_spa_applications_client_uuid
        ON dbo.spa_applications (client_uuid)
     WHERE client_uuid IS NOT NULL;
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'UQ_spa_field_data_client_uuid'
                  AND object_id = OBJECT_ID('dbo.spa_field_data'))
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UQ_spa_field_data_client_uuid
        ON dbo.spa_field_data (client_uuid)
     WHERE client_uuid IS NOT NULL;
END;
GO


/* ---------------------------------------------------------------------------
   STEP 5 — one inspection per file number, enforced by the database.

   storeFieldData() already rejects a second inspection for a file number, but
   that check is app-level only: two devices pushing the same inspection
   concurrently can both pass it and both insert. client_uuid stops a single
   device double-pushing; only this index stops two devices colliding.

   Requires STEP 0 to have returned zero rows.
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'UQ_spa_field_data_file_number'
                  AND object_id = OBJECT_ID('dbo.spa_field_data'))
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UQ_spa_field_data_file_number
        ON dbo.spa_field_data (file_number)
     WHERE file_number IS NOT NULL;
END;
GO


/* ---------------------------------------------------------------------------
   STEP 6 — one SPAS application per file number.

   Audit finding 4. Confirmed as the intended rule (2026-08-15), so it is now
   enforced by the database. Until this index existed nothing prevented a second
   application against the same file number — not the schema and not
   storeLandRecord(), which had no duplicate check at all.

   storeLandRecord() now rejects the duplicate first with a 422 and a readable
   message; this index is the backstop for concurrent inserts that both pass
   that check, and for anything writing outside the controller.

   Requires STEP 0's second query to have returned zero rows.
   --------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'UQ_spa_applications_file_number'
                  AND object_id = OBJECT_ID('dbo.spa_applications'))
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UQ_spa_applications_file_number
        ON dbo.spa_applications (file_number)
     WHERE file_number IS NOT NULL;
END;
GO


/* ---------------------------------------------------------------------------
   VERIFY — the audit's own checks, re-run. Expect:
     nullable columns  = 2   (spa_notices, spa_field_data)
     new columns       = 3   (2x client_uuid, 1x spa_application_client_uuid)
     new unique indexes= 4

   CORRECTED 2026-08-16. This block previously expected 3 indexes and listed
   only 3 names — it omitted UQ_spa_applications_file_number from STEP 6. That
   made the verification unable to detect the one step most likely to fail:
   STEP 6 aborts on pre-existing duplicate file_number rows, and the old block
   would still have reported a clean pass. All four are now checked, and the
   fourth query names any index that is actually missing rather than leaving a
   count to be interpreted.
   --------------------------------------------------------------------------- */
SELECT COUNT(*) AS nullable_columns
  FROM sys.columns
 WHERE name = 'spa_application_id'
   AND is_nullable = 1
   AND object_id IN (OBJECT_ID('dbo.spa_notices'), OBJECT_ID('dbo.spa_field_data'));
GO

SELECT COUNT(*) AS new_columns
  FROM sys.columns
 WHERE (object_id = OBJECT_ID('dbo.spa_applications') AND name = 'client_uuid')
    OR (object_id = OBJECT_ID('dbo.spa_field_data')   AND name IN ('client_uuid', 'spa_application_client_uuid'));
GO

SELECT COUNT(*) AS new_unique_indexes
  FROM sys.indexes
 WHERE name IN ('UQ_spa_applications_client_uuid',
                'UQ_spa_field_data_client_uuid',
                'UQ_spa_field_data_file_number',
                'UQ_spa_applications_file_number');
GO

/* Names the missing index instead of reporting a number to be decoded.
   Zero rows = fully applied. */
SELECT expected.index_name,
       CASE WHEN i.name IS NULL THEN 'MISSING' ELSE 'ok' END AS state
  FROM (VALUES ('UQ_spa_applications_client_uuid',  'dbo.spa_applications'),
               ('UQ_spa_field_data_client_uuid',    'dbo.spa_field_data'),
               ('UQ_spa_field_data_file_number',    'dbo.spa_field_data'),
               ('UQ_spa_applications_file_number',  'dbo.spa_applications')
       ) AS expected(index_name, table_name)
  LEFT JOIN sys.indexes i
         ON i.name = expected.index_name
        AND i.object_id = OBJECT_ID(expected.table_name)
 WHERE i.name IS NULL;
GO
