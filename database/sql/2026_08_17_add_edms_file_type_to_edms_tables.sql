/* ============================================================================
   Add edms_file_type to file_indexings, scannings and pagetypings
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_17_000000_add_edms_file_type_to_edms_tables.php

   RUN THIS AGAINST SQL SERVER, then run the companion ledger file against MySQL:
     database/sql/2026_08_17_add_edms_file_type_to_edms_tables_ledger.mysql.sql

   WHY
     The EDMS trees gain a master folder between the registry and the file
     number:

       EDMS/SCAN_UPLOAD/{Registry}/{Type}/{FILE NUMBER}/{PAPER}/{file}
       EDMS/PAGETYPING/{Registry}/{Type}/{FILE NUMBER}/{PAPER}/{file}
       EDMS/ARCHIVE_Doc_WARE/{Registry}/{Type}/{FILE NUMBER}/{PAPER}/{file}

     {Type} is one of Regular, Merger/Children, Merger/New_File,
     Subdivision/Mother, Subdivision/Children, Extension/Old, Extension/New,
     Temporary_File, Change_of_Purpose/Old, Change_of_Purpose/New
     (App\Services\Edms\EdmsFileType is the catalogue).

     A document's path therefore depends on this value, so it sits beside
     `registry` on the same three tables, and for the same reason: the scans and
     the typed pages each carry their own copy, so a half-finished move still
     resolves to a real file on disk.

   COLUMNS
     file_indexings.edms_file_type  nvarchar(64) NULL  -- the classification
     scannings.edms_file_type       nvarchar(64) NULL  -- where the original sits
     pagetypings.edms_file_type     nvarchar(64) NULL  -- where the typed copy sits

     Plus IX_file_indexings_edms_file_type, because the EDMS listings filter on it
     and file_indexings is large enough that a scan would be felt.

   SAFETY
     - Re-runnable: every step is guarded by COL_LENGTH / sys.indexes.
     - Adding a NULL column with no default is a metadata-only change; it does not
       rewrite rows or hold a long lock.
     - NO BACKFILL, deliberately. NULL means "unclassified", every existing file
       keeps the legacy layout directly under its registry folder, and nothing on
       disk moves until an operator picks a type in the UI. Guessing a file's
       nature from its number would file documents in the wrong master folder —
       the very problem this feature exists to solve.
     - Wrapped in a transaction, left open for review as per house convention.

   USAGE
     Run in SSMS, review the verification output, then COMMIT (or ROLLBACK).
   ============================================================================ */

SET NOCOUNT ON;
BEGIN TRANSACTION;

/* ---- file_indexings -------------------------------------------------- */
IF COL_LENGTH('dbo.file_indexings', 'edms_file_type') IS NULL
BEGIN
    ALTER TABLE dbo.file_indexings ADD edms_file_type NVARCHAR(64) NULL;
    PRINT 'Added file_indexings.edms_file_type';
END
ELSE
    PRINT 'file_indexings.edms_file_type already present - skipped';

/* ---- scannings ------------------------------------------------------- */
IF COL_LENGTH('dbo.scannings', 'edms_file_type') IS NULL
BEGIN
    ALTER TABLE dbo.scannings ADD edms_file_type NVARCHAR(64) NULL;
    PRINT 'Added scannings.edms_file_type';
END
ELSE
    PRINT 'scannings.edms_file_type already present - skipped';

/* ---- pagetypings ----------------------------------------------------- */
IF COL_LENGTH('dbo.pagetypings', 'edms_file_type') IS NULL
BEGIN
    ALTER TABLE dbo.pagetypings ADD edms_file_type NVARCHAR(64) NULL;
    PRINT 'Added pagetypings.edms_file_type';
END
ELSE
    PRINT 'pagetypings.edms_file_type already present - skipped';

/* ---- index ------------------------------------------------------------ */
IF COL_LENGTH('dbo.file_indexings', 'edms_file_type') IS NOT NULL
   AND NOT EXISTS (
        SELECT 1 FROM sys.indexes
         WHERE name = 'IX_file_indexings_edms_file_type'
           AND object_id = OBJECT_ID('dbo.file_indexings')
   )
BEGIN
    CREATE INDEX IX_file_indexings_edms_file_type
        ON dbo.file_indexings (edms_file_type);
    PRINT 'Created IX_file_indexings_edms_file_type';
END
ELSE
    PRINT 'IX_file_indexings_edms_file_type already present - skipped';

/* ---- verify: expect 3 columns and 1 index ----------------------------- */
SELECT
    (SELECT COUNT(*)
       FROM sys.columns
      WHERE name = 'edms_file_type'
        AND object_id IN (OBJECT_ID('dbo.file_indexings'),
                          OBJECT_ID('dbo.scannings'),
                          OBJECT_ID('dbo.pagetypings')))          AS columns_added,
    (SELECT COUNT(*)
       FROM sys.indexes
      WHERE name = 'IX_file_indexings_edms_file_type'
        AND object_id = OBJECT_ID('dbo.file_indexings'))          AS index_present;

/* Review the output above, then: */
-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
