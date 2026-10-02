/* ============================================================================
   Create and seed the `genders` lookup table
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_09_160000_create_genders_table.php
     database/seeders/GenderSeeder.php

   BACKGROUND
   Every gender dropdown in the app used to hard-code its own <option> list, and
   the lists had drifted: the MLS generator, ST commissioning and file-indexing
   edit offered Male/Female/Corporate/Joint, while the commission-fileno modal
   and file-indexing create offered only Male/Female. They now all render from
   resources/views/components/gender-select.blade.php, which reads this table via
   App\Models\Gender::options().

   THE STORED VALUE IS THE NAME, NOT THE ID
   file_indexings.gender, mls_file_no.gender and st_file_numbers.gender are
   varchars validated against App\Services\GenderNormalizer::CANON. This table
   only decides what a user is OFFERED and in what order — nothing references
   genders.id, and no foreign key is added. `code` is display shorthand (M/F/C/J)
   and is never stored as a gender value.

   Government bodies fold into Corporate; there is deliberately no fifth value.

   SCHEMA
   Mirrors exactly what the Laravel migration produced on the working DB
   (verified 2026-08-09 against sys.columns / sys.indexes):
     id          bigint IDENTITY, clustered PK (auto-named, as Laravel emits)
     name        nvarchar(50)  NOT NULL, unique
     code        nvarchar(5)   NULL
     sort_order  int           NOT NULL DEFAULT 0
     is_active   bit           NOT NULL DEFAULT 1
     created_at  datetime      NULL
     updated_at  datetime      NULL
     genders_name_unique                  (name)
     genders_is_active_sort_order_index   (is_active, sort_order)
   The migration's ->comment() calls produce no extended properties on SQL Server
   (the Laravel sqlsrv grammar drops them), so none are created here either —
   prod stays byte-identical to dev.

   SAFETY
     - Wrapped in a transaction; fully re-runnable. The CREATE is guarded by
       OBJECT_ID, each seed row by NOT EXISTS, and STEP 4 by name.
     - Running it against a database that already has the table only tops up the
       four rows and the migrations ledger entry.
     - No UPDATE or DELETE touches any table other than `genders`.

   AFTER RUNNING
     Clear the option cache on the web server, or the four values will not appear
     until the cache expires — it is a rememberForever entry:
         php artisan cache:clear
         php artisan view:clear
     (view:clear is only needed if compiled views were deployed pre-built.)

   USAGE
     1. Run THIS file against the SQL Server `klas` database. Review STEP 0, check
        STEP 6's output, then COMMIT (or ROLLBACK to abort — the transaction is
        left open deliberately).
     2. Run database/sql/2026_08_09_create_genders_lookup_ledger.mysql.sql against
        the MySQL `klas` database. That is where the migrations ledger lives — see
        STEP 5.
     3. php artisan cache:clear   (Gender::options() is a rememberForever entry)

   VERIFIED
     Dry-run against the working DB on 2026-08-09, both paths, then rolled back:
       - table already present  -> nothing re-created, no duplicate rows
       - table dropped first    -> table + both indexes created, 4 rows seeded
                                   with ids 1-4 in sort order
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* ---------------------------------------------------------------------------
   STEP 0 — Preview. Does the table already exist, and what is in it?
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: current state ===';

SELECT
    CASE WHEN OBJECT_ID('dbo.genders', 'U') IS NULL
         THEN 'genders table does NOT exist - it will be created'
         ELSE 'genders table already exists - only missing rows will be added'
    END AS table_state;

IF OBJECT_ID('dbo.genders', 'U') IS NOT NULL
    SELECT id, name, code, sort_order, is_active FROM dbo.genders ORDER BY sort_order, name;

/* ---------------------------------------------------------------------------
   STEP 1 — Create the table.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: create table ===';

IF OBJECT_ID('dbo.genders', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.genders (
        id          bigint       IDENTITY(1,1) NOT NULL PRIMARY KEY,
        name        nvarchar(50) NOT NULL,      -- the value stored on the consuming tables
        code        nvarchar(5)  NULL,          -- display shorthand only; never stored
        sort_order  int          NOT NULL CONSTRAINT DF_genders_sort_order DEFAULT (0),
        is_active   bit          NOT NULL CONSTRAINT DF_genders_is_active  DEFAULT (1),
        created_at  datetime     NULL,
        updated_at  datetime     NULL
    );
    PRINT '  dbo.genders created.';
END
ELSE
    PRINT '  dbo.genders already exists - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Indexes.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: indexes ===';

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE object_id = OBJECT_ID('dbo.genders')
                  AND name = 'genders_name_unique')
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX genders_name_unique ON dbo.genders (name);
    PRINT '  genders_name_unique created.';
END
ELSE
    PRINT '  genders_name_unique already exists - skipped.';

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE object_id = OBJECT_ID('dbo.genders')
                  AND name = 'genders_is_active_sort_order_index')
BEGIN
    CREATE NONCLUSTERED INDEX genders_is_active_sort_order_index
        ON dbo.genders (is_active, sort_order);
    PRINT '  genders_is_active_sort_order_index created.';
END
ELSE
    PRINT '  genders_is_active_sort_order_index already exists - skipped.';

/* ---------------------------------------------------------------------------
   STEP 3 — Seed the four canonical values (GenderNormalizer::CANON).
            Guarded by name, so re-running adds nothing and edits nothing.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 3: seed rows ===';

DECLARE @now datetime = GETDATE();

INSERT INTO dbo.genders (name, code, sort_order, is_active, created_at, updated_at)
SELECT v.name, v.code, v.sort_order, 1, @now, @now
FROM (VALUES
        ('Male',      'M', 1),
        ('Female',    'F', 2),
        ('Corporate', 'C', 3),
        ('Joint',     'J', 4)
     ) AS v(name, code, sort_order)
WHERE NOT EXISTS (SELECT 1 FROM dbo.genders g WHERE g.name = v.name);

PRINT '  rows inserted: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

/* ---------------------------------------------------------------------------
   STEP 4 — Retire anything non-canonical. Deactivated, never deleted: an id may
            already be referenced by a report or an export.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 4: deactivate non-canonical values ===';

UPDATE dbo.genders
   SET is_active  = 0,
       updated_at = GETDATE()
 WHERE is_active = 1
   AND name NOT IN ('Male', 'Female', 'Corporate', 'Joint');

PRINT '  rows deactivated: ' + CAST(@@ROWCOUNT AS VARCHAR(10));

/* ---------------------------------------------------------------------------
   STEP 5 — The migrations ledger is NOT in this database.

   config('database.default') is `mysql`, so `php artisan migrate` records its
   ledger in the MySQL `klas` DB, while these lookup/registry tables are created
   on SQL Server (the migration and model both pin ->connection('sqlsrv')).
   SQL Server also has a `migrations` table, but it is a legacy copy that artisan
   no longer writes to — inserting here would register nothing.

   Run the companion file against MySQL instead:
       database/sql/2026_08_09_create_genders_lookup_ledger.mysql.sql
   --------------------------------------------------------------------------- */
PRINT '=== STEP 5: ledger row belongs in MySQL - see ..._ledger.mysql.sql ===';

/* ---------------------------------------------------------------------------
   STEP 6 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 6: result ===';

SELECT id, name, code, sort_order, is_active, created_at, updated_at
FROM dbo.genders
ORDER BY sort_order, name;

SELECT
    (SELECT COUNT(*) FROM dbo.genders WHERE is_active = 1)  AS active_count,
    (SELECT COUNT(*) FROM dbo.genders)                      AS total_count;

/* Expected: exactly 4 active rows - Male, Female, Corporate, Joint, in that
   sort order.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 6, then COMMIT or ROLLBACK. ===';
PRINT '=== After COMMIT, run: php artisan cache:clear  (Gender::options is cached forever) ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
