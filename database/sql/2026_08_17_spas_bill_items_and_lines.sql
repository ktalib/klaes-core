/* ============================================================================
   SPAS billing becomes automatic on contravention
   — SQL SERVER schema change
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (`klas`). There is no PHP migration and therefore
   no ledger row to write: this project's migration ledger lives in MySQL while
   these tables live in SQL Server, so a migration can be recorded as run while
   its ALTER never reaches the database. Apply directly and verify with the
   VERIFY block at the bottom.

   WHY
   Bills were raised by hand: an officer opened "Add Bill", picked a bill type
   and typed an amount. That put the tariff in a person's memory, made two
   officers bill the same contravention differently, and meant a contravention
   with nobody watching was never billed at all.

   The tariff now lives in `spa_bill_items` — a small settings table an officer
   edits — and a bill is raised automatically the moment a record is found in
   contravention (approved land use != prevailing land use), composed of every
   active item.

   TABLES
     spa_bill_items  the tariff. name + amount, editable in the UI.
     spa_bill_lines  what a generated bill was actually composed of.

   WHY LINES ARE STORED, NOT RECOMPUTED
   `spa_bills.amount` is a single figure. Without lines, a bill raised last year
   at last year's tariff becomes unexplainable the moment someone edits an
   amount — the total no longer matches anything. Lines copy the name and amount
   AS AT the moment of billing, so an old bill still reconciles after the tariff
   changes.

   SAFETY
     - Re-runnable: guarded by OBJECT_ID / COL_LENGTH / sys.indexes checks.
     - Additive only. Nothing existing is modified or deleted.
     - spa_bills is untouched; generated bills are ordinary rows in it.
   ============================================================================ */

/* REQUIRED, do not remove.
   The filtered index in STEP "one auto bill per application" will not be
   created unless QUOTED_IDENTIFIER and ANSI_NULLS are ON. sqlcmd runs with
   QUOTED_IDENTIFIER OFF by default, so without these the index fails with
   Msg 1934 while every other statement succeeds — the script looks like it
   worked and the duplicate-bill guard is quietly absent. (Observed on dev,
   2026-08-17.) SSMS sets them ON already; this makes the script correct
   whichever tool runs it. */
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;
GO

/* ----------------------------------------------------- spa_bill_items ----- */
IF OBJECT_ID('dbo.spa_bill_items', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.spa_bill_items (
        id              BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        name            NVARCHAR(150)  NOT NULL,
        description     NVARCHAR(500)  NULL,
        amount          DECIMAL(18,2)  NOT NULL DEFAULT 0,
        /* Only active items are charged. Deactivating rather than deleting
           keeps historical lines explainable — see spa_bill_lines. */
        is_active       TINYINT        NOT NULL DEFAULT 1,
        sort_order      INT            NOT NULL DEFAULT 0,
        created_by      NVARCHAR(255)  NULL,
        updated_by      NVARCHAR(255)  NULL,
        created_at      DATETIME2(0)   NULL,
        updated_at      DATETIME2(0)   NULL
    );
END;
GO

/* One tariff line per name; stops a double-click creating "Penalty Fee" twice
   and then charging it twice on every bill. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'UQ_spa_bill_items_name'
                  AND object_id = OBJECT_ID('dbo.spa_bill_items'))
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UQ_spa_bill_items_name
        ON dbo.spa_bill_items (name);
END;
GO

/* ----------------------------------------------------- spa_bill_lines ----- */
IF OBJECT_ID('dbo.spa_bill_lines', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.spa_bill_lines (
        id                BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        spa_bill_id       BIGINT         NOT NULL,
        /* Nullable on purpose: the tariff row may later be deleted, and the
           line must survive it. The name and amount below are the record. */
        spa_bill_item_id  BIGINT         NULL,
        name              NVARCHAR(150)  NOT NULL,
        amount            DECIMAL(18,2)  NOT NULL DEFAULT 0,
        created_at        DATETIME2(0)   NULL,
        updated_at        DATETIME2(0)   NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'IX_spa_bill_lines_bill'
                  AND object_id = OBJECT_ID('dbo.spa_bill_lines'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_spa_bill_lines_bill
        ON dbo.spa_bill_lines (spa_bill_id);
END;
GO

/* ------------------------------------------------ spa_bills additions ----- */
/* How the bill came to exist. Without this an auto-raised bill is
   indistinguishable from one an officer typed, so nobody can audit the
   automation or find what it produced. */
IF COL_LENGTH('dbo.spa_bills', 'source') IS NULL
BEGIN
    ALTER TABLE dbo.spa_bills ADD source NVARCHAR(30) NULL;
END;
GO

/* One auto-raised contravention bill per application. A record can be edited
   repeatedly — each save re-checks the contravention — and without this the
   owner would collect a fresh bill every time somebody touched the record. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'UQ_spa_bills_auto_per_application'
                  AND object_id = OBJECT_ID('dbo.spa_bills'))
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UQ_spa_bills_auto_per_application
        ON dbo.spa_bills (spa_application_id)
     WHERE source = 'contravention';
END;
GO

/* ------------------------------------------------------------- seeding ---- */
/* The bill types the old hand-entry dropdown offered, so the tariff starts
   recognisable. Amounts are 0 until an officer sets them — a zero-amount item
   is skipped when billing, so nothing is charged by accident on day one. */
IF NOT EXISTS (SELECT 1 FROM dbo.spa_bill_items)
BEGIN
    INSERT INTO dbo.spa_bill_items (name, amount, is_active, sort_order, created_at, updated_at)
    VALUES
        ('Application Fee',   0, 1, 1, SYSUTCDATETIME(), SYSUTCDATETIME()),
        ('Processing Fee',    0, 1, 2, SYSUTCDATETIME(), SYSUTCDATETIME()),
        ('Change of Use Fee', 0, 1, 3, SYSUTCDATETIME(), SYSUTCDATETIME()),
        ('Penalty Fee',       0, 1, 4, SYSUTCDATETIME(), SYSUTCDATETIME()),
        ('Survey Fee',        0, 1, 5, SYSUTCDATETIME(), SYSUTCDATETIME());
END;
GO

/* -------------------------------------------------------------- VERIFY ---- */
/* Expect: tables = 2, source column = 1, indexes = 3, seeded items = 5 */
SELECT COUNT(*) AS new_tables
  FROM sys.tables WHERE name IN ('spa_bill_items', 'spa_bill_lines');

SELECT CASE WHEN COL_LENGTH('dbo.spa_bills', 'source') IS NULL THEN 0 ELSE 1 END AS source_column;

SELECT COUNT(*) AS new_indexes
  FROM sys.indexes
 WHERE name IN ('UQ_spa_bill_items_name', 'IX_spa_bill_lines_bill', 'UQ_spa_bills_auto_per_application');

SELECT COUNT(*) AS seeded_items FROM dbo.spa_bill_items;

/* Names anything missing rather than leaving a count to be interpreted.
   Zero rows = fully applied. */
SELECT expected.index_name,
       CASE WHEN i.name IS NULL THEN 'MISSING' ELSE 'ok' END AS state
  FROM (VALUES ('UQ_spa_bill_items_name',            'dbo.spa_bill_items'),
               ('IX_spa_bill_lines_bill',            'dbo.spa_bill_lines'),
               ('UQ_spa_bills_auto_per_application', 'dbo.spa_bills')
       ) AS expected(index_name, table_name)
  LEFT JOIN sys.indexes i
         ON i.name = expected.index_name
        AND i.object_id = OBJECT_ID(expected.table_name)
 WHERE i.name IS NULL;
GO
