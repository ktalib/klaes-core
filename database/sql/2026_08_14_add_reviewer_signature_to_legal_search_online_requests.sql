/* ============================================================================
   Online Legal Search — approver's digital signature
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the klaes sqlsrv database).

   Companion:
     database/sql/2026_08_14_add_reviewer_signature_to_legal_search_online_requests_ledger.mysql.sql
     — run that one afterwards, against MYSQL, to mark the migration as applied.

   WHAT THIS DOES
   Adds reviewer_signature_path + signed_at to legal_search_online_requests.
   The approver presses "Sign" in the approve dialog; their users.signature path
   is copied here and rendered on the Sign line of the issued report. A request
   approved without signing leaves both columns NULL and the line blank.

   SAFETY
     - Re-runnable: guarded by a COL_LENGTH check.
     - Adds two nullable columns. Touches no existing data.
   ============================================================================ */

IF COL_LENGTH('dbo.legal_search_online_requests', 'reviewer_signature_path') IS NULL
BEGIN
    ALTER TABLE dbo.legal_search_online_requests
        ADD reviewer_signature_path NVARCHAR(255) NULL,
            signed_at               DATETIME     NULL;
END;
GO

/* Verify — expect 2 */
SELECT COUNT(*) AS columns_added
  FROM sys.columns
 WHERE object_id = OBJECT_ID('dbo.legal_search_online_requests')
   AND name IN ('reviewer_signature_path', 'signed_at');
GO
