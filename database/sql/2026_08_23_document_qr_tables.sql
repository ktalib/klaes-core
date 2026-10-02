/* =====================================================================
   KLAES Document QR — global registry + print/scan audit
   Target: SQL Server (sqlsrv connection)

   Pairs with:
     database/migrations/2026_08_23_090000_create_document_qr_tables.php
     database/sql/2026_08_23_document_qr_tables_ledger.mysql.sql   <-- REQUIRED

   The artisan migrations ledger lives in MySQL while these tables are
   created on sqlsrv. The `migrations` table visible on sqlsrv is stale and
   must not be trusted. Run THIS file against SQL Server, then the ledger
   file against MySQL — skipping the ledger is how a migration ends up
   marked as run while its DDL never landed.
   ===================================================================== */

IF OBJECT_ID('document_qr_codes', 'U') IS NULL
BEGIN
    CREATE TABLE document_qr_codes
    (
        id                  BIGINT IDENTITY(1,1) PRIMARY KEY,

        -- what this document is
        document_type       NVARCHAR(40)  NOT NULL,
        source_table        NVARCHAR(128) NULL,
        source_id           BIGINT        NULL,

        -- how it reaches a file
        file_indexing_id    BIGINT        NULL,

        -- Snapshots taken at issue time — cross-check values, NOT truth.
        file_number         NVARCHAR(255) NULL,
        tracking_id         NVARCHAR(255) NULL,

        -- 'grouping' | 'commissioning' | 'file_tracker' | 'none'
        -- ST files have NO grouping table; their tracking ID is generated at
        -- commissioning. Kept as data so the rule stays queryable.
        tracking_id_source  NVARCHAR(40)  NULL,

        -- token identity
        qr_version          SMALLINT      NOT NULL CONSTRAINT df_dqr_version DEFAULT (1),
        key_id              SMALLINT      NOT NULL,
        token_hash          CHAR(64)      NOT NULL,

        -- 'active' | 'superseded'. 'revoked' is RESERVED and NOT yet in use —
        -- document revocation is deferred. (Distinct from TITLE revocation,
        -- which is register data and unrelated to whether the paper is genuine.)
        status              NVARCHAR(20)  NOT NULL CONSTRAINT df_dqr_status DEFAULT ('active'),

        -- Re-issuance on fresh security paper. The superseded token must KEEP
        -- verifying — a dead QR is indistinguishable from a forgery.
        superseded_by_id    BIGINT        NULL,

        issued_at           DATETIME      NULL,
        issued_by           BIGINT        NULL,
        print_count         INT           NOT NULL CONSTRAINT df_dqr_prints DEFAULT (0),
        last_printed_at     DATETIME      NULL,
        last_printed_by     BIGINT        NULL,

        created_at          DATETIME      NULL,
        updated_at          DATETIME      NULL
    );

    CREATE UNIQUE INDEX ux_dqr_token_hash  ON document_qr_codes (token_hash);
    CREATE INDEX        ix_dqr_file_indexing ON document_qr_codes (file_indexing_id);
    CREATE INDEX        ix_dqr_tracking    ON document_qr_codes (tracking_id);
    CREATE INDEX        ix_dqr_file_number ON document_qr_codes (file_number);

    /* Approach A: one QR per document instance; reprints share it. Filtered so
       the rows that carry no source_id do not collide. */
    CREATE UNIQUE INDEX ux_dqr_source ON document_qr_codes (document_type, source_id)
        WHERE source_id IS NOT NULL;
END
GO

IF OBJECT_ID('document_print_logs', 'U') IS NULL
BEGIN
    CREATE TABLE document_print_logs
    (
        id               BIGINT IDENTITY(1,1) PRIMARY KEY,
        document_qr_id   BIGINT         NOT NULL,
        print_number     INT            NOT NULL,          -- 1 = original
        copy_type        NVARCHAR(20)   NOT NULL CONSTRAINT df_dpl_copy DEFAULT ('reprint'),
        printed_by       BIGINT         NULL,
        printed_at       DATETIME       NULL,
        reason           NVARCHAR(255)  NULL,
        batch_reference  NVARCHAR(100)  NULL,
        ip_address       NVARCHAR(64)   NULL,
        user_agent       NVARCHAR(1000) NULL,

        CONSTRAINT fk_dpl_qr FOREIGN KEY (document_qr_id)
            REFERENCES document_qr_codes(id)
    );

    CREATE INDEX ix_dpl_qr ON document_print_logs (document_qr_id, print_number);
END
GO

IF OBJECT_ID('document_scan_logs', 'U') IS NULL
BEGIN
    CREATE TABLE document_scan_logs
    (
        id               BIGINT IDENTITY(1,1) PRIMARY KEY,
        document_qr_id   BIGINT         NULL,   -- NULL when nothing resolved
        qr_version_seen  NVARCHAR(10)   NULL,   -- Q1 | Q0 | REF

        -- Populated ONLY on failure, as evidence. A table of valid plaintext
        -- tokens would be a forgery kit for anyone with read access.
        raw_payload      NVARCHAR(512)  NULL,

        scanned_at       DATETIME       NULL,
        scanned_by       BIGINT         NULL,   -- NULL = public / anonymous
        channel          NVARCHAR(40)   NOT NULL CONSTRAINT df_dsl_channel DEFAULT ('manual'),
        ip_address       NVARCHAR(64)   NULL,
        user_agent       NVARCHAR(1000) NULL,
        result           NVARCHAR(30)   NOT NULL,
        failure_reason   NVARCHAR(500)  NULL
    );

    CREATE INDEX ix_dsl_qr   ON document_scan_logs (document_qr_id, scanned_at);
    CREATE INDEX ix_dsl_when ON document_scan_logs (scanned_at);
END
GO
