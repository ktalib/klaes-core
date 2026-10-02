/* ============================================================================
   Duplex Parcel Update - schema (SQL SERVER)

   Creates the three tables behind the Duplex Parcel Update page: several parcel
   updates carried as ONE instruction, held on internal holding numbers until a
   single commissioning pass at the end.

   Run this against the SQL Server 'klas' database.

   IMPORTANT: this file does NOT mark the migration as run. Artisan keeps its
   migrations ledger in MySQL while these tables live on SQL Server, so writing a
   ledger row here would land in the sqlsrv decoy ledger and the next
   'php artisan migrate' on production would re-attempt the migration. Apply the
   companion file after this one:

       2026_08_19_create_duplex_parcel_update_tables_ledger.mysql.sql

   Idempotent: every step is guarded, so a re-run is safe.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------- duplex head */
IF OBJECT_ID('dbo.duplex_parcel_updates', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.duplex_parcel_updates (
        id                          BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        duplex_id                   NVARCHAR(40)   NOT NULL,          -- DPX-2026-0007
        applicant_name              NVARCHAR(255)  NULL,
        file_title                  NVARCHAR(500)  NULL,
        source_file_nos             NVARCHAR(MAX)  NULL,              -- JSON array
        stages                      NVARCHAR(MAX)  NULL,              -- JSON [{type,rank,count}] in TICK order
        status                      NVARCHAR(30)   NOT NULL CONSTRAINT DF_duplex_status DEFAULT ('draft'),
        land_use                    NVARCHAR(50)   NULL,
        plot_no                     NVARCHAR(100)  NULL,
        house_no                    NVARCHAR(100)  NULL,
        street_name                 NVARCHAR(255)  NULL,
        district                    NVARCHAR(255)  NULL,
        lga                         NVARCHAR(255)  NULL,
        state                       NVARCHAR(100)  NULL,
        phone                       NVARCHAR(50)   NULL,
        address                     NVARCHAR(500)  NULL,
        land_value                  DECIMAL(18,2)  NULL,
        knupda_fee                  DECIMAL(18,2)  NULL,
        knupda_status               NVARCHAR(50)   NULL,
        knupda_remarks              NVARCHAR(MAX)  NULL,
        remarks                     NVARCHAR(MAX)  NULL,
        application_generated_at    DATETIME       NULL,
        recommendation_generated_at DATETIME       NULL,
        conveyance_generated_at     DATETIME       NULL,
        sent_to_land_at             DATETIME       NULL,
        committed_at                DATETIME       NULL,
        captured_by                 BIGINT         NULL,
        updated_by                  BIGINT         NULL,
        approved_by                 BIGINT         NULL,
        committed_by                BIGINT         NULL,
        is_deleted                  TINYINT        NOT NULL CONSTRAINT DF_duplex_is_deleted DEFAULT (0),
        deleted_by                  BIGINT         NULL,
        deleted_at                  DATETIME       NULL,
        created_at                  DATETIME       NULL,
        updated_at                  DATETIME       NULL
    );
    PRINT 'Created dbo.duplex_parcel_updates';
END
ELSE
    PRINT 'dbo.duplex_parcel_updates already exists - skipped';
GO

IF OBJECT_ID('dbo.duplex_parcel_updates', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_duplex_parcel_updates_duplex_id')
BEGIN
    CREATE UNIQUE INDEX UX_duplex_parcel_updates_duplex_id
        ON dbo.duplex_parcel_updates (duplex_id);
    PRINT 'Created UX_duplex_parcel_updates_duplex_id';
END
GO

IF OBJECT_ID('dbo.duplex_parcel_updates', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_duplex_parcel_updates_status')
BEGIN
    CREATE INDEX IX_duplex_parcel_updates_status ON dbo.duplex_parcel_updates (status);
    PRINT 'Created IX_duplex_parcel_updates_status';
END
GO

/* ------------------------------------------------------------------- stages */
IF OBJECT_ID('dbo.duplex_parcel_update_stages', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.duplex_parcel_update_stages (
        id                       BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        duplex_parcel_update_id  BIGINT         NOT NULL,
        duplex_id                NVARCHAR(40)   NULL,
        type                     NVARCHAR(40)   NOT NULL,   -- subdivision|merger|extension|separation|change_of_purpose
        rank                     INT            NOT NULL,   -- execution order, from the officer's tick order
        status                   NVARCHAR(20)   NOT NULL CONSTRAINT DF_duplex_stage_status DEFAULT ('pending'),
        input_holding_no         NVARCHAR(60)   NULL,
        plot_count               INT            NULL,
        payload                  NVARCHAR(MAX)  NULL,       -- JSON: sizes, holders, new land use, applies_to
        tracking_id              NVARCHAR(100)  NULL,
        reject_reason            NVARCHAR(MAX)  NULL,
        completed_at             DATETIME       NULL,
        captured_by              BIGINT         NULL,
        updated_by               BIGINT         NULL,
        created_at               DATETIME       NULL,
        updated_at               DATETIME       NULL
    );
    PRINT 'Created dbo.duplex_parcel_update_stages';
END
ELSE
    PRINT 'dbo.duplex_parcel_update_stages already exists - skipped';
GO

/* One rank per duplex. A TYPE may repeat (two subdivisions at different ranks is
   legal); a RANK may not, or the runner cannot tell which stage feeds which. */
IF OBJECT_ID('dbo.duplex_parcel_update_stages', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_duplex_stage_rank')
BEGIN
    CREATE UNIQUE INDEX UX_duplex_stage_rank
        ON dbo.duplex_parcel_update_stages (duplex_parcel_update_id, rank);
    PRINT 'Created UX_duplex_stage_rank';
END
GO

/* -------------------------------------------------------------------- files */
IF OBJECT_ID('dbo.duplex_parcel_update_files', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.duplex_parcel_update_files (
        id                             BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        duplex_parcel_update_id        BIGINT         NOT NULL,
        duplex_parcel_update_stage_id  BIGINT         NULL,
        duplex_id                      NVARCHAR(40)   NULL,
        role                           NVARCHAR(20)   NOT NULL,   -- source | holding | result
        holding_no                     NVARCHAR(60)   NULL,       -- DPX-2026-0007-H03, never a registry number
        source_file_no                 NVARCHAR(100)  NULL,
        final_file_no                  NVARCHAR(100)  NULL,
        file_title                     NVARCHAR(500)  NULL,
        plot_size                      DECIMAL(18,4)  NULL,
        holder_name                    NVARCHAR(255)  NULL,
        prop_id                        NVARCHAR(50)   NULL,
        parent_prop_id                 NVARCHAR(255)  NULL,
        will_decommission              TINYINT        NOT NULL CONSTRAINT DF_duplex_file_willdecom DEFAULT (0),
        decommissioned                 TINYINT        NOT NULL CONSTRAINT DF_duplex_file_decom     DEFAULT (0),
        sequence                       INT            NOT NULL CONSTRAINT DF_duplex_file_sequence  DEFAULT (0),
        created_at                     DATETIME       NULL,
        updated_at                     DATETIME       NULL
    );
    PRINT 'Created dbo.duplex_parcel_update_files';
END
ELSE
    PRINT 'dbo.duplex_parcel_update_files already exists - skipped';
GO

IF OBJECT_ID('dbo.duplex_parcel_update_files', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_duplex_files_duplex')
BEGIN
    CREATE INDEX IX_duplex_files_duplex
        ON dbo.duplex_parcel_update_files (duplex_parcel_update_id);
    PRINT 'Created IX_duplex_files_duplex';
END
GO

IF OBJECT_ID('dbo.duplex_parcel_update_files', 'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_duplex_files_holding_no')
BEGIN
    CREATE INDEX IX_duplex_files_holding_no
        ON dbo.duplex_parcel_update_files (holding_no);
    PRINT 'Created IX_duplex_files_holding_no';
END
GO

PRINT '';
PRINT 'Duplex Parcel Update schema applied. Now run the MySQL ledger companion:';
PRINT '  2026_08_19_create_duplex_parcel_update_tables_ledger.mysql.sql';
PRINT 'Then verify with: verify_duplex_parcel_update_schema.sql';
GO
