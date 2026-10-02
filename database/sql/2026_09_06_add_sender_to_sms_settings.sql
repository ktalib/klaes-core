/* ============================================================================
   Per-message sender ID
   ----------------------------------------------------------------------------
   RUN THIS AGAINST SQL SERVER (the `klas` database on the sqlsrv connection).
   Companion: 2026_09_06_add_sender_to_sms_settings_ledger.mysql.sql (MySQL).

   WHY
   Bulk-SMS.ng takes the sender ID as a per-request field, so one account can
   send under more than one name. The Ministry uses two, split by who RECEIVES
   the message:

     KLAES     staff traffic  -- sign-in, sign-out, Quick Search requests,
                                 file tracking, verification codes
     KANOMLPP  public traffic -- commissioning, Letters of Grant, deed
                                 registrations, caveats

   The column holds the GROUP KEY ('staff' / 'department'), not the literal
   sender ID, so re-registering under a different name is one edit in
   config/klaes_sms.php rather than an UPDATE across this table.

   NULL means "use the group this message ships under", so leaving every row
   null keeps the shipped split and lets a later code upload correct it.

   SAFETY
     - Re-runnable: guarded by a COL_LENGTH check.
     - Adds one NULLable column. No existing row or column is touched.
   ============================================================================ */

IF OBJECT_ID('dbo.sms_settings', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.sms_settings', 'sender') IS NULL
BEGIN
    ALTER TABLE dbo.sms_settings ADD sender NVARCHAR(32) NULL;
    PRINT 'Added dbo.sms_settings.sender';
END
ELSE
    PRINT 'dbo.sms_settings.sender already present (or table missing) - skipped';
GO

/* Verify -- expect 1 */
SELECT CASE WHEN COL_LENGTH('dbo.sms_settings', 'sender') IS NULL THEN 0 ELSE 1 END AS sender_present;
GO
