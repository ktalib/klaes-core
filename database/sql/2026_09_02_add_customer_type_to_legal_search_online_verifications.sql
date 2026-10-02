/* ============================================================================
   Online Legal Search — customer type & Call-to-Bar number
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_09_02_add_customer_type_to_legal_search_online_verifications_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark the migration as applied.

   WHAT THIS DOES
   Adds three columns to legal_search_online_verifications. The IYC card now asks
   whether the searcher is an Individual or a Lawyer / Legal Adviser; a lawyer
   additionally supplies a Call-to-Bar number, which is checked against the text
   OCR reads off their uploaded ID and, if one is ever configured, against a roll
   of legal practitioners.

   WHY bar_number_status MATTERS
     not_applicable — an individual; no number supplied
     matched        — found on the ID, or confirmed by a roll
     unconfirmed    — recorded, but nothing could confirm it. THIS IS THE NORMAL
                      OUTCOME: Nigerian general-purpose IDs (NIN slip, driver's
                      licence, voter's card) do not print a call-to-bar number,
                      and no roll API is wired up. It NEVER blocks payment — if it
                      did, no lawyer could complete a search. The approving
                      officer confirms the number during the existing review.
     rejected       — a roll positively said the number is not valid; a passing
                      name match is downgraded to `review` so a human decides.

   SAFETY
     - Re-runnable: every ADD/CREATE is guarded.
     - Existing rows are individuals; the DEFAULT covers them, so no backfill.
     - Adds columns only. Touches no existing data.
   ============================================================================ */

IF OBJECT_ID('dbo.legal_search_online_verifications', 'U') IS NOT NULL
BEGIN

    IF COL_LENGTH('dbo.legal_search_online_verifications', 'customer_type') IS NULL
    BEGIN
        ALTER TABLE dbo.legal_search_online_verifications
            ADD customer_type NVARCHAR(20) NOT NULL
                CONSTRAINT df_lsov_customer_type DEFAULT ('individual');
        PRINT 'Added customer_type.';
    END

    IF COL_LENGTH('dbo.legal_search_online_verifications', 'call_to_bar_number') IS NULL
    BEGIN
        ALTER TABLE dbo.legal_search_online_verifications
            ADD call_to_bar_number NVARCHAR(60) NULL;
        PRINT 'Added call_to_bar_number.';
    END

    IF COL_LENGTH('dbo.legal_search_online_verifications', 'bar_number_status') IS NULL
    BEGIN
        ALTER TABLE dbo.legal_search_online_verifications
            ADD bar_number_status NVARCHAR(20) NULL;
        PRINT 'Added bar_number_status.';
    END

END
ELSE
BEGIN
    PRINT 'dbo.legal_search_online_verifications does not exist - run 2026_09_01_create_legal_search_online_verifications.sql first.';
END
GO

/* Constraints and index, in a separate batch so the columns above exist. */
IF OBJECT_ID('dbo.legal_search_online_verifications', 'U') IS NOT NULL
BEGIN

    IF OBJECT_ID('chk_lsov_customer_type', 'C') IS NULL
        ALTER TABLE dbo.legal_search_online_verifications
            ADD CONSTRAINT chk_lsov_customer_type
            CHECK (customer_type IN ('individual','lawyer'));

    IF OBJECT_ID('chk_lsov_bar_status', 'C') IS NULL
        ALTER TABLE dbo.legal_search_online_verifications
            ADD CONSTRAINT chk_lsov_bar_status
            CHECK (bar_number_status IS NULL
                   OR bar_number_status IN ('not_applicable','matched','unconfirmed','rejected'));

    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                    WHERE name = 'ix_lsov_customer_type'
                      AND object_id = OBJECT_ID('dbo.legal_search_online_verifications'))
        CREATE INDEX ix_lsov_customer_type
            ON dbo.legal_search_online_verifications (customer_type);

END
GO

/* Verify — expect three rows */
SELECT name AS column_added
  FROM sys.columns
 WHERE object_id = OBJECT_ID('dbo.legal_search_online_verifications')
   AND name IN ('customer_type', 'call_to_bar_number', 'bar_number_status');
GO
