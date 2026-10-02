/* ============================================================================
   SMS Control Centre -- sms_settings + sms_dispatch_logs
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the `klas` database on the sqlsrv connection).

   Companion file: 2026_09_06_create_sms_tables_ledger.mysql.sql, which runs
   against MYSQL and marks the two migrations as applied. Run this one FIRST.

   WHY A HAND-RUN SCRIPT AT ALL
   config('database.default') is `mysql`, so `php artisan migrate` keeps its
   ledger in the MySQL `klas` database while these tables live on SQL Server
   (both migrations pin ->connection('sqlsrv')). Production is deployed by code
   upload rather than by running artisan, so the tables have to be created by
   hand. SQL Server has its own `migrations` table, but it is a legacy copy
   artisan no longer writes to -- do not trust it.

   SAFETY
     - Re-runnable: every CREATE is guarded by an OBJECT_ID check.
     - Creates two new tables. Touches nothing that already exists.
     - No rows are seeded. An empty sms_settings means every message is OFF,
       which is the state a fresh deployment should be in.
   ============================================================================ */

/* ---------------------------------------------------------------------------
   1. sms_settings -- one row per message: is it on, and what does it say.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.sms_settings', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.sms_settings (
        id           BIGINT IDENTITY(1,1) NOT NULL,
        message_key  NVARCHAR(64)  NOT NULL,
        enabled      BIT           NOT NULL CONSTRAINT DF_sms_settings_enabled DEFAULT (0),
        template     NVARCHAR(MAX) NULL,
        updated_by   NVARCHAR(191) NULL,
        created_at   DATETIME      NULL,
        updated_at   DATETIME      NULL,
        CONSTRAINT PK_sms_settings PRIMARY KEY CLUSTERED (id)
    );

    CREATE UNIQUE INDEX sms_settings_key_unique
        ON dbo.sms_settings (message_key);

    PRINT 'Created dbo.sms_settings';
END
ELSE
    PRINT 'dbo.sms_settings already exists - skipped';
GO

/* ---------------------------------------------------------------------------
   2. sms_dispatch_logs -- every message sent, failed or skipped.

      dedupe_key is the double-send guard: the row is claimed BEFORE the gateway
      is called, so a double-clicked button loses on the unique index instead of
      texting the applicant twice.

      The index is FILTERED (WHERE dedupe_key IS NOT NULL) because SQL Server
      treats two NULLs as duplicates in an ordinary UNIQUE index. Test sends from
      the control page carry no dedupe_key on purpose, and without the filter the
      first test send would block every later one.
   --------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.sms_dispatch_logs', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.sms_dispatch_logs (
        id             BIGINT IDENTITY(1,1) NOT NULL,
        message_key    NVARCHAR(64)  NOT NULL,
        phone          NVARCHAR(20)  NULL,
        message        NVARCHAR(MAX) NULL,
        status         NVARCHAR(20)  NOT NULL CONSTRAINT DF_sms_dispatch_logs_status DEFAULT ('pending'),
        gateway_code   NVARCHAR(20)  NULL,
        failure_reason NVARCHAR(MAX) NULL,
        attempts       TINYINT       NOT NULL CONSTRAINT DF_sms_dispatch_logs_attempts DEFAULT (0),
        file_number    NVARCHAR(100) NULL,
        subject_type   NVARCHAR(64)  NULL,
        subject_id     NVARCHAR(64)  NULL,
        dedupe_key     NVARCHAR(191) NULL,
        event_at       DATETIME      NULL,
        created_by     NVARCHAR(191) NULL,
        created_at     DATETIME      NULL,
        updated_at     DATETIME      NULL,
        CONSTRAINT PK_sms_dispatch_logs PRIMARY KEY CLUSTERED (id)
    );

    CREATE UNIQUE INDEX sms_dispatch_logs_dedupe_unique
        ON dbo.sms_dispatch_logs (dedupe_key)
        WHERE dedupe_key IS NOT NULL;

    CREATE INDEX sms_dispatch_logs_key_status_idx
        ON dbo.sms_dispatch_logs (message_key, status);

    CREATE INDEX sms_dispatch_logs_file_number_idx
        ON dbo.sms_dispatch_logs (file_number);

    CREATE INDEX sms_dispatch_logs_created_idx
        ON dbo.sms_dispatch_logs (created_at);

    PRINT 'Created dbo.sms_dispatch_logs';
END
ELSE
    PRINT 'dbo.sms_dispatch_logs already exists - skipped';
GO

/* ---------------------------------------------------------------------------
   Verify -- expect both to report 1.
   --------------------------------------------------------------------------- */
SELECT
    CASE WHEN OBJECT_ID('dbo.sms_settings', 'U')      IS NULL THEN 0 ELSE 1 END AS sms_settings_present,
    CASE WHEN OBJECT_ID('dbo.sms_dispatch_logs', 'U') IS NULL THEN 0 ELSE 1 END AS sms_dispatch_logs_present;
GO
