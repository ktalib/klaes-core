/* ============================================================================
   Online Legal Search — purpose-of-search lookup + request columns
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_08_16_create_online_ls_search_purposes_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark both migrations applied.

   WHAT THIS DOES
   1. Creates online_ls_search_purposes and seeds the four purposes the public
      portal accepts. A search cannot proceed for anything outside this list.
   2. Adds purpose_id + purpose to legal_search_online_requests. Both are kept:
      the id links to the lookup, the name is a snapshot so a historic request
      still reads correctly if the lookup entry is later renamed.

   NOTE: this is deliberately NOT the existing request_purposes table, which is
   the internal file-tracking list and is not meaningful to a public requester.

   SAFETY
     - Re-runnable: guarded by OBJECT_ID / COL_LENGTH / NOT EXISTS checks.
     - Seeds only rows that are missing; never overwrites edited names.
   ============================================================================ */

/* 1. Lookup table -------------------------------------------------------- */
IF OBJECT_ID('dbo.online_ls_search_purposes', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.online_ls_search_purposes (
        id          BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        code        NVARCHAR(60)  NOT NULL,
        name        NVARCHAR(150) NOT NULL,
        description NVARCHAR(255) NULL,
        sort_order  INT           NOT NULL CONSTRAINT DF_olsp_sort DEFAULT (0),
        is_active   BIT           NOT NULL CONSTRAINT DF_olsp_active DEFAULT (1),
        created_at  DATETIME      NULL,
        updated_at  DATETIME      NULL
    );

    CREATE UNIQUE INDEX UQ_olsp_code   ON dbo.online_ls_search_purposes (code);
    CREATE INDEX        IX_olsp_active ON dbo.online_ls_search_purposes (is_active);
    CREATE INDEX        IX_olsp_sort   ON dbo.online_ls_search_purposes (sort_order);
END;
GO

/* 2. Seed the four accepted purposes ------------------------------------- */
MERGE dbo.online_ls_search_purposes AS target
USING (VALUES
    ('verification_confirmation', N'Verification/Confirmation', 1),
    ('bill_balance',              N'Bill Balance',              2),
    ('title_status',              N'Title Status',              3),
    ('encumbrance_verification',  N'Encumbrance Verification',  4)
) AS source (code, name, sort_order)
   ON target.code = source.code
 WHEN NOT MATCHED BY TARGET THEN
      INSERT (code, name, sort_order, is_active, created_at, updated_at)
      VALUES (source.code, source.name, source.sort_order, 1, GETDATE(), GETDATE());
GO

/* 3. Request columns ------------------------------------------------------ */
IF COL_LENGTH('dbo.legal_search_online_requests', 'purpose_id') IS NULL
BEGIN
    ALTER TABLE dbo.legal_search_online_requests ADD purpose_id BIGINT NULL;
END;
GO

IF COL_LENGTH('dbo.legal_search_online_requests', 'purpose') IS NULL
BEGIN
    ALTER TABLE dbo.legal_search_online_requests ADD purpose NVARCHAR(150) NULL;
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_lsor_purpose_id'
                 AND object_id = OBJECT_ID('dbo.legal_search_online_requests'))
BEGIN
    CREATE INDEX IX_lsor_purpose_id ON dbo.legal_search_online_requests (purpose_id);
END;
GO

/* Verify — expect 4 purposes and both columns present */
SELECT (SELECT COUNT(*) FROM dbo.online_ls_search_purposes)                        AS purposes_seeded,
       CASE WHEN COL_LENGTH('dbo.legal_search_online_requests','purpose_id')
            IS NULL THEN 0 ELSE 1 END                                              AS has_purpose_id,
       CASE WHEN COL_LENGTH('dbo.legal_search_online_requests','purpose')
            IS NULL THEN 0 ELSE 1 END                                              AS has_purpose;
GO
