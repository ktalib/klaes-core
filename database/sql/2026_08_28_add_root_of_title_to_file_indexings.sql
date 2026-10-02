/* ============================================================================
   Add file_indexings.root_of_title
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_28_100000_add_root_of_title_to_file_indexings_table.php

   WHY
   TitleHolderResolver prints three lines — Root of Title / Original Holder /
   Current Holder — but it can only DERIVE a root when the chain actually holds a
   pre-grant dealing. On every other file the line prints a dash and there was no
   way to supply the answer. The indexer has the physical file in hand, so the
   File Indexing form now asks for it (required on create and on edit, KANGIS
   update included) and this column stores it.

   COLUMN
     root_of_title  nvarchar(255) NULL

   Why not the JSON shape of current_holder / original_holder: those hold a LIST
   of names because block indexing captures several owners. A file has exactly one
   root of title, so this is a plain string. FileIndexing::formattedHolder() reads
   plain strings as well as JSON, so it works through the same accessor.

   Why NULL when the form calls it required: the rule is a data-entry rule. About
   133k rows predate the field, and the non-form write paths (commissioning
   auto-indexing, batch imports, source='scanning_upload') have no value to give.
   A NOT NULL column would break all of them. NULL here means "never captured".

   SAFETY
     - Re-runnable: the ALTER is guarded by COL_LENGTH.
     - Adding a NULL column with no default is a metadata-only change; it does not
       rewrite existing rows or hold a long lock on a 133k-row table.
     - NO BACKFILL. Copying original_holder in would put a name nobody verified on
       the Root line of every historic file, and it would read as captured fact.

   USAGE
     1. Run this file against the SQL Server `klas` database, check STEP 2, COMMIT.
     2. Run the companion ledger file against MYSQL:
        database/sql/2026_08_28_add_root_of_title_to_file_indexings_ledger.mysql.sql
        (the migrations ledger lives in MySQL, not here — see that file.)
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

/* ---------------------------------------------------------------------------
   STEP 0 — Preview.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 0: current state ===';

SELECT
    CASE WHEN COL_LENGTH('dbo.file_indexings', 'root_of_title') IS NULL
         THEN 'root_of_title does NOT exist - it will be added'
         ELSE 'root_of_title already exists - nothing to do' END AS root_of_title_state;

/* ---------------------------------------------------------------------------
   STEP 1 — Add the column.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 1: add column ===';

IF COL_LENGTH('dbo.file_indexings', 'root_of_title') IS NULL
BEGIN
    /* The pre-grant instrument/holder the title springs from, as read off the
       physical file by the indexer. */
    ALTER TABLE dbo.file_indexings ADD root_of_title nvarchar(255) NULL;
    PRINT '  root_of_title added.';
END
ELSE
    PRINT '  root_of_title already present - skipped.';

/* ---------------------------------------------------------------------------
   STEP 2 — Verify, then COMMIT or ROLLBACK.
   --------------------------------------------------------------------------- */
PRINT '=== STEP 2: result ===';

SELECT c.name, t.name AS type, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.file_indexings')
  AND c.name = 'root_of_title';

/* Expected: one row — name root_of_title, type nvarchar, max_length 510
   (nvarchar stores 2 bytes per character, so 255 characters = 510), is_nullable 1.
   If so:              COMMIT TRANSACTION;
   If anything is off: ROLLBACK TRANSACTION;                                   */

PRINT '=== Transaction left OPEN. Review STEP 2, then COMMIT or ROLLBACK. ===';

-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
