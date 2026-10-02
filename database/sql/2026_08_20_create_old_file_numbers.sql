/* ============================================================================
   Old file numbers — ledger + the file_indexings mirror column
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_08_20_create_old_file_numbers_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark both migrations applied.

   WHAT THIS DOES
   1. Creates dbo.old_file_numbers.
      An old file number is picked in two places on the MLPP File Number
      Generator: "Old FileNo (Duplicate)" on a Re-Issuance of FileNo, and the
      "Old File Number" checkbox in the list's Edit modal. Both wrote a single
      string to mls_file_no.old_fileno, which holds one value and is silently
      overwritten on the next edit. This table keeps one row per
      (current number, old number) pair, so the history survives.

   2. Adds file_indexings.old_fileno.
      related_fileno on file_indexings is a JSON array of SIBLING files. An old
      number is a different relationship — the same physical file under a
      previous number — so it gets its own column, mirroring the split the Edit
      modal's checkbox already makes.

   mls_file_no.old_fileno and file_indexings.old_fileno remain the "current
   value" mirrors the screens read; App\Services\OldFileNumberService is the
   only writer and keeps all three in step.

   SAFETY
     - Re-runnable: guarded by OBJECT_ID / COL_LENGTH checks.
     - Creates one new table and one nullable column. Touches no existing data.
   ============================================================================ */

IF OBJECT_ID('dbo.old_file_numbers', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.old_file_numbers (
        id               BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,

        -- The live registry number the file is known by today
        -- (mls_file_no.full_file_number / fileNumber.mlsfNo / file_indexings.file_number).
        file_number      NVARCHAR(100) NOT NULL,

        -- The legacy / duplicated number this file used to carry.
        old_file_number  NVARCHAR(100) NOT NULL,

        -- Title as it stood on the old file, when the picker supplied one.
        old_file_title   NVARCHAR(500) NULL,

        -- reissuance | edit | manual | import
        source           NVARCHAR(30)  NOT NULL CONSTRAINT DF_ofn_source DEFAULT ('manual'),

        -- Resolved once at write time so the mapping is auditable rather than
        -- re-derived on every read. NULL is valid: the file may not be indexed yet.
        file_indexing_id BIGINT        NULL,

        created_by       BIGINT        NULL,
        created_at       DATETIME      NULL,
        updated_at       DATETIME      NULL
    );

    -- One row per (file, old number) pair; re-saving the same pair updates it.
    CREATE UNIQUE INDEX old_file_numbers_pair_unique
        ON dbo.old_file_numbers (file_number, old_file_number);

    CREATE INDEX old_file_numbers_old_idx
        ON dbo.old_file_numbers (old_file_number);

    CREATE INDEX old_file_numbers_indexing_idx
        ON dbo.old_file_numbers (file_indexing_id);
END;
GO

IF COL_LENGTH('dbo.file_indexings', 'old_fileno') IS NULL
BEGIN
    ALTER TABLE dbo.file_indexings ADD old_fileno NVARCHAR(100) NULL;
END;
GO

/* ----------------------------------------------------------------------------
   Backfill — seed the ledger and the indexing mirror from the values already
   sitting in mls_file_no.old_fileno. Re-runnable: the INSERT is guarded by
   NOT EXISTS and the UPDATE is idempotent.
   ---------------------------------------------------------------------------- */

INSERT INTO dbo.old_file_numbers
    (file_number, old_file_number, old_file_title, source, file_indexing_id, created_at, updated_at)
SELECT
    LTRIM(RTRIM(m.full_file_number)),
    LTRIM(RTRIM(m.old_fileno)),
    NULL,
    'import',
    (SELECT MIN(fi.id)
       FROM dbo.file_indexings fi
      WHERE UPPER(LTRIM(RTRIM(fi.file_number))) = UPPER(LTRIM(RTRIM(m.full_file_number)))),
    GETDATE(),
    GETDATE()
  FROM dbo.mls_file_no m
 WHERE m.old_fileno IS NOT NULL
   AND LTRIM(RTRIM(m.old_fileno)) <> ''
   AND m.full_file_number IS NOT NULL
   AND LTRIM(RTRIM(m.full_file_number)) <> ''
   AND UPPER(LTRIM(RTRIM(m.old_fileno))) <> UPPER(LTRIM(RTRIM(m.full_file_number)))
   AND NOT EXISTS (
         SELECT 1
           FROM dbo.old_file_numbers o
          WHERE o.file_number     = LTRIM(RTRIM(m.full_file_number))
            AND o.old_file_number = LTRIM(RTRIM(m.old_fileno))
       );
GO

UPDATE fi
   SET fi.old_fileno = LTRIM(RTRIM(m.old_fileno))
  FROM dbo.file_indexings fi
  JOIN dbo.mls_file_no m
    ON UPPER(LTRIM(RTRIM(fi.file_number))) = UPPER(LTRIM(RTRIM(m.full_file_number)))
 WHERE m.old_fileno IS NOT NULL
   AND LTRIM(RTRIM(m.old_fileno)) <> ''
   AND (fi.old_fileno IS NULL OR LTRIM(RTRIM(fi.old_fileno)) <> LTRIM(RTRIM(m.old_fileno)));
GO

/* Verify */
SELECT COUNT(*) AS ledger_rows        FROM dbo.old_file_numbers;
SELECT COUNT(*) AS indexed_old_filenos FROM dbo.file_indexings WHERE old_fileno IS NOT NULL;
GO
