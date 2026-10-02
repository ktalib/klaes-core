/* ============================================================================
   SPAS payments are recorded per bill item
   — SQL SERVER schema change
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (`klas`). There is no PHP migration and therefore
   no ledger row to write: this project's migration ledger lives in MySQL while
   these tables live in SQL Server, so a migration can be recorded as run while
   its ALTER never reaches the database. Apply directly and verify with the
   VERIFY block at the bottom.

   WHY
   A bill is composed of several items (Application Fee, Processing Fee, Penalty
   Fee ...) but a payment was one lump figure against the bill. An officer
   collecting part of a bill could not say WHICH items had been settled, and a
   receipt could only show a total — so the same bill, half paid, meant
   different things to different officers.

   Payments are now entered item by item. `spa_payments` still holds one row per
   payment event (the total, receipt number, method, date); this table records
   how that payment was split across the bill's items.

   WHY NAME AND AMOUNT ARE COPIED
   Same reason as spa_bill_lines: a receipt printed today must still reconcile
   after somebody edits the tariff or a bill line. The line reference is kept
   for reporting, but the printed record is the copy held here.

   SAFETY
     - Re-runnable: guarded by OBJECT_ID / sys.indexes checks.
     - Additive only. Nothing existing is modified or deleted.
     - Payments recorded before this change simply have no lines; the UI treats
       them as an unallocated payment against the bill, which is what they were.
   ============================================================================ */

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;
GO

/* -------------------------------------------------- spa_payment_lines ----- */
IF OBJECT_ID('dbo.spa_payment_lines', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.spa_payment_lines (
        id                BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        spa_payment_id    BIGINT         NOT NULL,
        /* Nullable on purpose: the bill line may later be removed, and the
           allocation must survive it. The name and amount below are the
           record. */
        spa_bill_line_id  BIGINT         NULL,
        name              NVARCHAR(150)  NOT NULL,
        amount_paid       DECIMAL(18,2)  NOT NULL DEFAULT 0,
        created_at        DATETIME2(0)   NULL,
        updated_at        DATETIME2(0)   NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'IX_spa_payment_lines_payment'
                  AND object_id = OBJECT_ID('dbo.spa_payment_lines'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_spa_payment_lines_payment
        ON dbo.spa_payment_lines (spa_payment_id);
END;
GO

/* Reading "how much has this bill item been paid so far" is done on every open
   of the payment form, filtered by bill line. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
                WHERE name = 'IX_spa_payment_lines_bill_line'
                  AND object_id = OBJECT_ID('dbo.spa_payment_lines'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_spa_payment_lines_bill_line
        ON dbo.spa_payment_lines (spa_bill_line_id);
END;
GO

/* -------------------------------------------------------------- VERIFY ---- */
/* Expect: table = 1, indexes = 2 */
SELECT COUNT(*) AS new_table
  FROM sys.tables WHERE name = 'spa_payment_lines';

SELECT COUNT(*) AS new_indexes
  FROM sys.indexes
 WHERE name IN ('IX_spa_payment_lines_payment', 'IX_spa_payment_lines_bill_line');

/* Names anything missing rather than leaving a count to be interpreted.
   Zero rows = fully applied. */
SELECT expected.index_name,
       CASE WHEN i.name IS NULL THEN 'MISSING' ELSE 'ok' END AS state
  FROM (VALUES ('IX_spa_payment_lines_payment',   'dbo.spa_payment_lines'),
               ('IX_spa_payment_lines_bill_line', 'dbo.spa_payment_lines')
       ) AS expected(index_name, table_name)
  LEFT JOIN sys.indexes i
         ON i.name = expected.index_name
        AND i.object_id = OBJECT_ID(expected.table_name)
 WHERE i.name IS NULL;
GO
