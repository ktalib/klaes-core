/* ============================================================================
   Online Legal Search — Director / Deputy Director approval queue
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_08_14_create_legal_search_online_requests_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark the migration as applied.

   WHAT THIS DOES
   Creates legal_search_online_requests. The public Online Legal Search portal
   no longer releases a report the moment a Paystack payment clears; the payment
   opens a request row here, a Director / Deputy Director approves it, and the
   report is emailed to the requester as a PDF.

   SAFETY
     - Re-runnable: guarded by an OBJECT_ID check.
     - Creates one new table. Touches no existing data.
   ============================================================================ */

IF OBJECT_ID('dbo.legal_search_online_requests', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.legal_search_online_requests (
        id                BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        request_no        NVARCHAR(30)  NULL,

        payment_id        BIGINT        NULL,
        reference         NVARCHAR(60)  NULL,
        tracking_id       NVARCHAR(30)  NULL,

        requester_email   NVARCHAR(255) NOT NULL,
        requester_name    NVARCHAR(150) NULL,
        requester_phone   NVARCHAR(30)  NULL,

        file_number       NVARCHAR(100) NULL,
        search_params     NVARCHAR(MAX) NULL,
        ip_address        NVARCHAR(45)  NULL,

        status            NVARCHAR(20)  NOT NULL CONSTRAINT DF_lsor_status DEFAULT ('pending'),
        submitted_at      DATETIME      NULL,
        reviewed_by       BIGINT        NULL,
        reviewer_name     NVARCHAR(150) NULL,
        reviewer_rank     NVARCHAR(100) NULL,
        reviewed_at       DATETIME      NULL,
        review_note       NVARCHAR(MAX) NULL,
        rejection_reason  NVARCHAR(MAX) NULL,

        emailed_at        DATETIME      NULL,
        email_error       NVARCHAR(MAX) NULL,

        created_at        DATETIME      NULL,
        updated_at        DATETIME      NULL
    );

    CREATE UNIQUE INDEX UQ_lsor_request_no
        ON dbo.legal_search_online_requests (request_no)
        WHERE request_no IS NOT NULL;

    CREATE INDEX IX_lsor_status          ON dbo.legal_search_online_requests (status);
    CREATE INDEX IX_lsor_payment_id      ON dbo.legal_search_online_requests (payment_id);
    CREATE INDEX IX_lsor_file_number     ON dbo.legal_search_online_requests (file_number);
    CREATE INDEX IX_lsor_requester_email ON dbo.legal_search_online_requests (requester_email);
    CREATE INDEX IX_lsor_status_created  ON dbo.legal_search_online_requests (status, created_at);
END;
GO

/* Verify — expect 1 */
SELECT COUNT(*) AS table_exists
  FROM sys.tables
 WHERE name = 'legal_search_online_requests';
GO
