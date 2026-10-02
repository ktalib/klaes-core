/* ============================================================================
   Online Legal Search — applicant identification & ID name verification
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_09_01_create_legal_search_online_verifications_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark the migration as applied.

   WHAT THIS DOES
   Creates legal_search_online_verifications. Before a public Online Legal Search
   applicant may pay, they now supply their identification details and images of a
   government-issued ID. The server OCRs the document and compares the name read
   off it against the name typed. Only a `verified` result opens the Paystack
   checkout.

   WHY A SEPARATE TABLE
   The applicant is verified BEFORE any payment is attempted, so at write time
   neither legal_search_online_payments nor legal_search_online_requests has a row
   yet. This row is created first and linked forward (payment_id / request_id)
   once the payment clears.

   SCOPE — id_verification_status records only that the typed name matched text
   found on the uploaded document. It is NOT a statement that the document is
   genuine, unaltered, or presented by its rightful holder.

   SAFETY
     - Re-runnable: guarded by an OBJECT_ID check.
     - Creates one new table. Touches no existing data.
   ============================================================================ */

IF OBJECT_ID('dbo.legal_search_online_verifications', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.legal_search_online_verifications (
        id                        BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,

        /* Which search this identification was submitted for. */
        file_number               NVARCHAR(100)  NULL,
        requester_email           NVARCHAR(255)  NULL,

        /* Bound to the submitting browser session, so one applicant cannot
           present another applicant's verification at payment time. */
        session_token             NVARCHAR(64)   NULL,

        /* Applicant identification, as typed. */
        applicant_full_name       NVARCHAR(200)  NOT NULL,
        applicant_phone           NVARCHAR(30)   NOT NULL,
        applicant_address         NVARCHAR(500)  NOT NULL,

        identification_type       NVARCHAR(50)   NOT NULL,
        identification_type_other NVARCHAR(120)  NULL,

        /* Paths on the private disk. Never rendered into HTML or handed to JS. */
        id_front_path             NVARCHAR(255)  NULL,
        id_back_path              NVARCHAR(255)  NULL,

        /* Only populated when config('id_verification.store_raw_text') is on. */
        id_ocr_text               NVARCHAR(MAX)  NULL,

        id_name_match_score       DECIMAL(5,2)   NOT NULL CONSTRAINT df_lsov_score DEFAULT (0),

        id_verification_status    NVARCHAR(20)   NOT NULL CONSTRAINT df_lsov_status DEFAULT ('pending'),
        id_verified_at            DATETIME       NULL,

        /* Set once the applicant pays; until then this row is unattached. */
        payment_id                BIGINT         NULL,
        request_id                BIGINT         NULL,

        ip_address                NVARCHAR(45)   NULL,
        created_at                DATETIME       NULL,
        updated_at                DATETIME       NULL,

        CONSTRAINT chk_lsov_status CHECK (id_verification_status IN ('pending','verified','review','failed'))
    );

    CREATE UNIQUE INDEX ux_lsov_session_token
        ON dbo.legal_search_online_verifications (session_token)
        WHERE session_token IS NOT NULL;

    CREATE INDEX ix_lsov_file_number     ON dbo.legal_search_online_verifications (file_number);
    CREATE INDEX ix_lsov_email           ON dbo.legal_search_online_verifications (requester_email);
    CREATE INDEX ix_lsov_status          ON dbo.legal_search_online_verifications (id_verification_status);
    CREATE INDEX ix_lsov_payment_id      ON dbo.legal_search_online_verifications (payment_id);

    PRINT 'Created dbo.legal_search_online_verifications.';
END
ELSE
BEGIN
    PRINT 'dbo.legal_search_online_verifications already exists - nothing to do.';
END
GO

/* Verify — expect one row describing the new table */
SELECT COUNT(*) AS table_exists
  FROM sys.tables
 WHERE name = 'legal_search_online_verifications';
GO
