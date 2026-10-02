/* ============================================================================
   Create and seed edms_file_types (the EDMS File Type lookup table)
   ----------------------------------------------------------------------------
   Production equivalent of:
     database/migrations/2026_08_21_120000_create_edms_file_types_table.php

   RUN THIS AGAINST SQL SERVER, then run the companion ledger file against MySQL:
     database/sql/2026_08_21_create_edms_file_types_table_ledger.mysql.sql

   WHY
     The EDMS master-folder catalogue used to be a PHP const, so adding a type —
     which the registry does ask for — meant a deploy. It lives in this table
     now; App\Services\Edms\EdmsFileType reads it (cached) and keeps its const
     only as this seed and as the fallback for a database that has not had this
     script run against it yet.

     One row per selectable end state, described by the three dropdowns that
     pick it on the Document Upload dialog:

       category  regular | parcel_update | title_status
       type      subdivision, merger, extension, regrant, litigation, …
       variant   old | new, or NULL

     Only Regrant and Resettlement split into Old and New, so they hold two rows
     each. Every other type is a single folder and holds one row with a NULL
     variant — the choice is complete after two dropdowns.

     `code` is what file_indexings.edms_file_type (and the same column on
     scannings / pagetypings) stores; `folder` is the path segment on disk:

       EDMS/SCAN_UPLOAD/{Registry}/{folder}/{FILE NUMBER}/{PAPER}/{file}

     Both are stable. A relabelled row must keep its code and folder, or
     documents already filed under it stop resolving.

   SAFE TO RE-RUN
     The table is created only when absent, and the seed inserts only codes that
     are not present, so a row the registry has edited is never overwritten.

   AFTER RUNNING
     Clear the application cache (php artisan cache:clear) so the catalogue is
     re-read, and run `php artisan edms:create-file-type-folders` to create the
     new folder skeleton under each registry.
   ============================================================================ */

/* ── 1. Table ────────────────────────────────────────────────────────────── */
IF OBJECT_ID('dbo.edms_file_types', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.edms_file_types (
        id             INT IDENTITY(1,1) NOT NULL,
        code           NVARCHAR(64)  NOT NULL,
        category       NVARCHAR(32)  NOT NULL,
        category_label NVARCHAR(64)  NOT NULL,
        [type]         NVARCHAR(64)  NOT NULL,
        type_label     NVARCHAR(64)  NOT NULL,
        variant        NVARCHAR(32)  NULL,
        variant_label  NVARCHAR(64)  NULL,
        label          NVARCHAR(128) NOT NULL,
        folder         NVARCHAR(191) NOT NULL,
        sort_order     INT           NOT NULL CONSTRAINT DF_edms_file_types_sort DEFAULT (0),
        is_active      BIT           NOT NULL CONSTRAINT DF_edms_file_types_active DEFAULT (1),
        created_at     DATETIME      NULL,
        updated_at     DATETIME      NULL,
        CONSTRAINT PK_edms_file_types PRIMARY KEY CLUSTERED (id),
        CONSTRAINT UQ_edms_file_types_code UNIQUE (code)
    );

    /* Every dropdown reads the catalogue in this order. */
    CREATE INDEX IX_edms_file_types_active_order
        ON dbo.edms_file_types (is_active, sort_order);

    CREATE INDEX IX_edms_file_types_category_type
        ON dbo.edms_file_types (category, [type]);
END
GO

/* ── 2. Seed, guarded ────────────────────────────────────────────────────── */
;WITH seed (code, category, category_label, [type], type_label, variant, variant_label, label, folder, sort_order, is_active) AS (
  SELECT * FROM (VALUES
    (N'regular', N'regular', N'Regular', N'regular', N'Regular', NULL, NULL, N'Regular', N'Regular', 10, 1),
    (N'subdivision', N'parcel_update', N'Parcel Update', N'subdivision', N'Subdivision', NULL, NULL, N'Subdivision', N'Parcel_Update/Subdivision', 20, 1),
    (N'merger', N'parcel_update', N'Parcel Update', N'merger', N'Merger', NULL, NULL, N'Merger', N'Parcel_Update/Merger', 30, 1),
    (N'extension', N'parcel_update', N'Parcel Update', N'extension', N'Extension', NULL, NULL, N'Extension', N'Parcel_Update/Extension', 40, 1),
    (N'separation', N'parcel_update', N'Parcel Update', N'separation', N'Separation', NULL, NULL, N'Separation', N'Parcel_Update/Separation', 50, 1),
    (N'temporary', N'parcel_update', N'Parcel Update', N'temporary', N'Temporary File', NULL, NULL, N'Temporary File', N'Parcel_Update/Temporary_File', 60, 1),
    (N'change_of_purpose', N'parcel_update', N'Parcel Update', N'change_of_purpose', N'Change of Purpose', NULL, NULL, N'Change of Purpose', N'Parcel_Update/Change_of_Purpose', 70, 1),
    (N'title_status_regrant_old', N'title_status', N'Title Status', N'regrant', N'Regrant', N'old', N'Old', N'Regrant — Old', N'Title_Status/Regrant/Old', 80, 1),
    (N'title_status_regrant_new', N'title_status', N'Title Status', N'regrant', N'Regrant', N'new', N'New', N'Regrant — New', N'Title_Status/Regrant/New', 90, 1),
    (N'title_status_resettlement_old', N'title_status', N'Title Status', N'resettlement', N'Resettlement', N'old', N'Old', N'Resettlement — Old', N'Title_Status/Resettlement/Old', 100, 1),
    (N'title_status_resettlement_new', N'title_status', N'Title Status', N'resettlement', N'Resettlement', N'new', N'New', N'Resettlement — New', N'Title_Status/Resettlement/New', 110, 1),
    (N'title_status_litigation', N'title_status', N'Title Status', N'litigation', N'Litigation', NULL, NULL, N'Litigation', N'Title_Status/Litigation', 120, 1),
    (N'title_status_amendment', N'title_status', N'Title Status', N'amendment', N'Amendment', NULL, NULL, N'Amendment', N'Title_Status/Amendment', 130, 1),
    (N'title_status_revocation', N'title_status', N'Title Status', N'revocation', N'Revocation', NULL, NULL, N'Revocation', N'Title_Status/Revocation', 140, 1),
    (N'title_status_withdrawal', N'title_status', N'Title Status', N'withdrawal', N'Withdrawal', NULL, NULL, N'Withdrawal', N'Title_Status/Withdrawal', 150, 1),
    (N'title_status_close', N'title_status', N'Title Status', N'close', N'Close', NULL, NULL, N'Close', N'Title_Status/Close', 160, 1),
    (N'title_status_cancellation', N'title_status', N'Title Status', N'cancellation', N'Cancellation', NULL, NULL, N'Cancellation', N'Title_Status/Cancellation', 170, 1),
    (N'title_status_surrender', N'title_status', N'Title Status', N'surrender', N'Surrender', NULL, NULL, N'Surrender', N'Title_Status/Surrender', 180, 1)
  ) v (code, category, category_label, [type], type_label, variant, variant_label, label, folder, sort_order, is_active)
)
INSERT INTO dbo.edms_file_types
      (code, category, category_label, [type], type_label, variant, variant_label, label, folder, sort_order, is_active, created_at, updated_at)
SELECT s.code, s.category, s.category_label, s.[type], s.type_label, s.variant, s.variant_label,
       s.label, s.folder, s.sort_order, s.is_active, SYSDATETIME(), SYSDATETIME()
  FROM seed s
 WHERE NOT EXISTS (SELECT 1 FROM dbo.edms_file_types e WHERE e.code = s.code);
GO

/* ── 3. Verify — expect 18 rows, 3 categories ────────────────────────────── */
SELECT COUNT(*) AS total_rows, COUNT(DISTINCT category) AS categories
  FROM dbo.edms_file_types
 WHERE is_active = 1;

SELECT category_label, type_label, variant_label, code, folder
  FROM dbo.edms_file_types
 WHERE is_active = 1
 ORDER BY sort_order;
GO
