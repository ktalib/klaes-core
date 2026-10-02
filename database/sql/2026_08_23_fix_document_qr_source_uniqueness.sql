/* =====================================================================
   Allow a re-issuance to mint a new document instance
   Target: SQL Server (sqlsrv connection)

   Pairs with:
     database/migrations/2026_08_23_140000_fix_document_qr_source_uniqueness.php
     database/sql/2026_08_23_fix_document_qr_source_uniqueness_ledger.mysql.sql

   The original index allowed ONE QR per source row forever, which blocked the
   re-issuance rule: a re-issuance on fresh security paper is a NEW document
   instance, with the previous one marked 'superseded' and BOTH left resolvable.

   The constraint that expresses the intent is "one ACTIVE QR per source":
   reprints share the live row, superseded generations accumulate behind it and
   keep verifying.
   ===================================================================== */

DROP INDEX IF EXISTS ux_dqr_source ON document_qr_codes;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'ux_dqr_source_active'
                 AND object_id = OBJECT_ID('document_qr_codes'))
BEGIN
    CREATE UNIQUE INDEX ux_dqr_source_active ON document_qr_codes (document_type, source_id)
        WHERE source_id IS NOT NULL AND status = 'active';
END
GO
