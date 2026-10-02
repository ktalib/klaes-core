/* ===========================================================================
   Add/Edit Buyers — autosaved drafts
   Target: SQL Server (the KLAES `sqlsrv` connection)

   Adds one table. Nothing existing is altered, and no data is read or written,
   so this is safe to run on a live database during working hours.

   Re-runnable: every statement is guarded, so running it twice does nothing
   the second time.

   Covers the migration:
     2026_08_12_100000_create_buyer_list_drafts_table

   NOTE ON THE LEDGER: this project keeps Laravel's `migrations` table on the
   MySQL connection, not on SQL Server, so it is not touched here. See the note
   at the bottom.
   =========================================================================== */

SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------------
   1. The drafts table.

   The buyers capture held everything in the browser until the final "Save
   Buyers", so anything that emptied the page — a session timeout, a 419, a
   stray navigation — took every typed row with it. The form now writes itself
   here every few seconds.

   ONE DRAFT PER FILE: draft_key is the normalised file number, so a return
   visit updates the draft that is already there instead of accumulating
   copies. draft_name ("COM-1991-46 - 12 Aug 2026") is refreshed on each save
   and is for reading only — nothing keys off it.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.buyer_list_drafts', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.buyer_list_drafts (
        id             bigint IDENTITY(1,1) NOT NULL,

        -- The normalised file number: upper-cased, inner spacing collapsed.
        -- Applications with no file number yet fall back to 'APP-<id>'.
        draft_key      nvarchar(120)  NOT NULL,

        -- What the resume banner shows.
        draft_name     nvarchar(190)  NULL,

        file_no        nvarchar(120)  NULL,
        application_id bigint         NULL,

        -- Drafts are shared per file rather than per officer, so who touched it
        -- last is named in the resume banner.
        last_saved_by  bigint         NULL,

        -- Every captured row as JSON, valid or not — nothing here is
        -- authoritative. nvarchar(max) because a long buyers list runs well
        -- past the 4000-character default.
        payload        nvarchar(max)  NULL,

        -- Denormalised off the payload so the banner can say "7 buyers in
        -- progress" without decoding it.
        rows_total     int            NOT NULL CONSTRAINT DF_buyer_list_drafts_rows_total  DEFAULT (0),
        rows_filled    int            NOT NULL CONSTRAINT DF_buyer_list_drafts_rows_filled DEFAULT (0),

        -- open      — still being keyed, offered for resume
        -- submitted — the rows reached buyer_list; kept for the audit trail
        -- discarded — the user threw it away
        status         nvarchar(20)   NOT NULL CONSTRAINT DF_buyer_list_drafts_status DEFAULT ('open'),

        -- Separate from updated_at: the moment the BROWSER last had its work
        -- accepted, which is what the "Draft saved 12:04" badge reports.
        last_saved_at  datetime       NULL,

        created_at     datetime       NULL,
        updated_at     datetime       NULL,

        CONSTRAINT PK_buyer_list_drafts PRIMARY KEY CLUSTERED (id)
    );

    PRINT 'Created table buyer_list_drafts.';
END
ELSE
    PRINT 'Table buyer_list_drafts already exists — skipped.';
GO

/* ---------------------------------------------------------------------------
   2. Indexes. The names match what Laravel's migration creates, so a later
      `php artisan migrate:rollback` still finds them.
   --------------------------------------------------------------------------- */

-- The autosave upserts on draft_key on every save, so this one is load-bearing
-- rather than merely a constraint: it is what keeps a file to one draft.
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'buyer_list_drafts_draft_key_unique'
                 AND object_id = OBJECT_ID('dbo.buyer_list_drafts'))
BEGIN
    CREATE UNIQUE INDEX buyer_list_drafts_draft_key_unique
        ON dbo.buyer_list_drafts (draft_key);
    PRINT 'Created index ..._draft_key_unique.';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'buyer_list_drafts_application_id_index'
                 AND object_id = OBJECT_ID('dbo.buyer_list_drafts'))
BEGIN
    CREATE INDEX buyer_list_drafts_application_id_index
        ON dbo.buyer_list_drafts (application_id);
    PRINT 'Created index ..._application_id_index.';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'buyer_list_drafts_last_saved_by_index'
                 AND object_id = OBJECT_ID('dbo.buyer_list_drafts'))
BEGIN
    CREATE INDEX buyer_list_drafts_last_saved_by_index
        ON dbo.buyer_list_drafts (last_saved_by);
    PRINT 'Created index ..._last_saved_by_index.';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'buyer_list_drafts_status_index'
                 AND object_id = OBJECT_ID('dbo.buyer_list_drafts'))
BEGIN
    CREATE INDEX buyer_list_drafts_status_index
        ON dbo.buyer_list_drafts (status);
    PRINT 'Created index ..._status_index.';
END
GO

-- The resume lookup: this application's open draft.
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'idx_buyer_list_drafts_app_status'
                 AND object_id = OBJECT_ID('dbo.buyer_list_drafts'))
BEGIN
    CREATE INDEX idx_buyer_list_drafts_app_status
        ON dbo.buyer_list_drafts (application_id, status);
    PRINT 'Created index idx_buyer_list_drafts_app_status.';
END
GO

/* ---------------------------------------------------------------------------
   3. Verification — run this after the script and check the output.
      Expect: 13 columns and 6 indexes (5 above plus the primary key).
   --------------------------------------------------------------------------- */
SELECT
    (SELECT COUNT(*) FROM sys.columns
      WHERE object_id = OBJECT_ID('dbo.buyer_list_drafts'))  AS column_count,
    (SELECT COUNT(*) FROM sys.indexes
      WHERE object_id = OBJECT_ID('dbo.buyer_list_drafts')
        AND name IS NOT NULL)                                AS index_count;
GO

/* ===========================================================================
   AFTERWARDS — the migration ledger

   This project stores Laravel's `migrations` table on the MySQL connection, so
   the row below cannot be inserted by this SQL Server script. Without it, a
   future `php artisan migrate` will try the migration again — which is
   harmless, because it is guarded by hasTable() and will do nothing, but it
   leaves the ledger untidy.

   Either run this against the MySQL database:

     INSERT INTO migrations (migration, batch) VALUES
       ('2026_08_12_100000_create_buyer_list_drafts_table',
        (SELECT * FROM (SELECT MAX(batch) + 1 FROM migrations) AS b));

   ...or simply run `php artisan migrate` on production after this script: the
   migration will find its table already present, do nothing, and record itself
   in the ledger.

   NOTHING ELSE in this release needs a database change. The buyers log channel
   writes to storage/logs/buyer_list.log, and the client trace is written to
   that file only — no table is involved.
   =========================================================================== */
