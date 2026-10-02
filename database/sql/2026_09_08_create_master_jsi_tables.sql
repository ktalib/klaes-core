/* ============================================================================
   Master JSI - schema (SQL SERVER)

   Creates the two tables behind the Master Joint Site Inspection: the Physical
   Planning site observation for a parcel update, covering both categories -

       SPU   the five single workflows (Subdivision, Separation, Merger,
             Extension, Change of Purpose)
       APU   the Duplex, where a JSI may attach to one stage

   An approved Master JSI is what Generate Recommendation and Approve now rest on,
   replacing the KAMMA/Physical Planning handshake. The knupda_* columns on the
   parcel-update tables are NOT dropped by this script: they are history, and
   records already cleared through the handshake keep working through the legacy
   arm of App\Support\MasterJsiGate.

   Run this against the SQL Server 'klas' database.

   IMPORTANT: this file does NOT mark the migration as run. Artisan keeps its
   migrations ledger in MySQL while these tables live on SQL Server, so writing a
   ledger row here would land in the sqlsrv decoy ledger and the next
   'php artisan migrate' on production would re-attempt the migration. Apply the
   companion file after this one:

       2026_09_08_create_master_jsi_tables_ledger.mysql.sql

   Idempotent: every step is guarded, so a re-run is safe.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* ------------------------------------------------------------- report head */
IF OBJECT_ID('dbo.master_jsi_reports', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.master_jsi_reports (
        id                      BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        jsi_ref                 NVARCHAR(40)   NOT NULL,          -- MJSI-2026-0001
        category                NVARCHAR(10)   NOT NULL,          -- SPU | APU
        parcel_update_type      NVARCHAR(40)   NOT NULL,          -- subdivision|separation|merger|extension|change_of_purpose

        -- subject_type is the workflow SLUG, not a model class name.
        subject_type            NVARCHAR(40)   NULL,
        subject_id              BIGINT         NULL,
        duplex_stage_id         BIGINT         NULL,              -- an APU stage, when the JSI covers one

        file_number             NVARCHAR(100)  NULL,
        file_title              NVARCHAR(500)  NULL,

        -- application details
        applicant_name          NVARCHAR(255)  NULL,
        inspection_date         DATE           NULL,
        location                NVARCHAR(500)  NULL,
        plot_number             NVARCHAR(100)  NULL,
        district                NVARCHAR(255)  NULL,
        lga                     NVARCHAR(255)  NULL,
        inspection_officer      NVARCHAR(255)  NULL,
        inspection_officer_id   BIGINT         NULL,

        -- site observation
        available_on_ground     NVARCHAR(50)   NULL,
        boundary_description    NVARCHAR(MAX)  NULL,              -- "On the North: ...; On the East: ..."
        road_reservation        NVARCHAR(700)  NULL,
        conformity              BIT            NULL,
        prevailing_land_use     NVARCHAR(255)  NULL,
        existing_land_use       NVARCHAR(255)  NULL,
        recommended_land_use    NVARCHAR(255)  NULL,
        existing_purpose        NVARCHAR(255)  NULL,
        recommended_purpose     NVARCHAR(255)  NULL,
        number_of_units         INT            NULL,
        average_size            NVARCHAR(100)  NULL,
        narrative_summary       NVARCHAR(MAX)  NULL,
        additional_observations NVARCHAR(MAX)  NULL,

        -- workflow
        status                  NVARCHAR(20)   NOT NULL CONSTRAINT DF_master_jsi_status DEFAULT ('draft'),
        generated_at            DATETIME2      NULL,
        generated_by            BIGINT         NULL,
        submitted_at            DATETIME2      NULL,
        submitted_by            BIGINT         NULL,
        approved_at             DATETIME2      NULL,
        approved_by             BIGINT         NULL,
        rejected_reason         NVARCHAR(MAX)  NULL,
        sent_to_deeds_at        DATETIME2      NULL,
        sent_to_deeds_by        BIGINT         NULL,

        -- audit
        created_by              BIGINT         NULL,
        updated_by              BIGINT         NULL,
        is_deleted              BIT            NOT NULL CONSTRAINT DF_master_jsi_is_deleted DEFAULT (0),
        deleted_by              BIGINT         NULL,
        deleted_at              DATETIME2      NULL,
        created_at              DATETIME2      NULL,
        updated_at              DATETIME2      NULL
    );
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_master_jsi_ref' AND object_id = OBJECT_ID('dbo.master_jsi_reports'))
    CREATE UNIQUE INDEX UX_master_jsi_ref ON dbo.master_jsi_reports (jsi_ref);
GO

/* The gate asks this question on every parcel-update listing row. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_master_jsi_subject' AND object_id = OBJECT_ID('dbo.master_jsi_reports'))
    CREATE INDEX IX_master_jsi_subject ON dbo.master_jsi_reports (subject_type, subject_id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_master_jsi_status' AND object_id = OBJECT_ID('dbo.master_jsi_reports'))
    CREATE INDEX IX_master_jsi_status ON dbo.master_jsi_reports (status);
GO

/* ------------------------------------------------ the S/N table on the sheet */
IF OBJECT_ID('dbo.master_jsi_portions', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.master_jsi_portions (
        id                      BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        master_jsi_report_id    BIGINT         NOT NULL,
        sn                      INT            NULL,
        role                    NVARCHAR(40)   NULL,              -- existing_title|extension_portion|recommended_portion|portion_a|...
        label                   NVARCHAR(255)  NULL,              -- what the PORTION column prints
        dimensions              NVARCHAR(255)  NULL,              -- "60 x 21 x 46"
        area_sqm                DECIMAL(18,2)  NULL,              -- SQUARE METRES; hectares derived for display
        land_use                NVARCHAR(255)  NULL,              -- Change of Purpose rows
        unit_count              INT            NULL,              -- "No of Subdivision"
        sequence                INT            NOT NULL CONSTRAINT DF_master_jsi_portion_seq DEFAULT (0),
        created_at              DATETIME2      NULL,
        updated_at              DATETIME2      NULL
    );
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_master_jsi_portion_report' AND object_id = OBJECT_ID('dbo.master_jsi_portions'))
    CREATE INDEX IX_master_jsi_portion_report ON dbo.master_jsi_portions (master_jsi_report_id);
GO

/* ------------------------------------------------------------------ verify */
SELECT
    t.name AS table_name,
    (SELECT COUNT(*) FROM sys.columns c WHERE c.object_id = t.object_id) AS column_count
FROM sys.tables t
WHERE t.name IN ('master_jsi_reports', 'master_jsi_portions');
GO
