/* ============================================================================
   Link a captured instrument to the consent it was registered against
   — SQL SERVER schema change
   ----------------------------------------------------------------------------
   RUN THIS FIRST, against SQL SERVER (`klas`). Then run the companion
   database/sql/2026_08_13_add_consent_application_id_to_instrument_capture_ledger.mysql.sql
   against MYSQL to register the migration.

   WHY
   A file legitimately carries several consents over its life — an Assignment
   consent to one assignee this year, another to a different assignee later —
   and the same instrument type is captured against each in turn. The duplicate
   registration warning now lists every consent on the file and greys out the
   ones already used, which is only knowable if each capture records the consent
   it consumed.

   Legacy captures (before this column) keep NULL; the warning infers those by
   matching consent type + party names, so nothing needs backfilling.

   SAFETY
     - Re-runnable: guarded by COL_LENGTH / sys.indexes checks.
     - Additive only; no existing column or row is modified.
   ============================================================================ */

/* Preview — expect 0 before the first run */
SELECT
    CASE WHEN COL_LENGTH('dbo.instrument_capture', 'consent_application_id') IS NULL THEN 0 ELSE 1 END AS has_column;

IF COL_LENGTH('dbo.instrument_capture', 'consent_application_id') IS NULL
    ALTER TABLE dbo.instrument_capture ADD consent_application_id BIGINT NULL;
GO

IF NOT EXISTS (
        SELECT 1 FROM sys.indexes
         WHERE name = 'ix_instrument_capture_consent_application_id'
           AND object_id = OBJECT_ID('dbo.instrument_capture')
    )
    CREATE NONCLUSTERED INDEX ix_instrument_capture_consent_application_id
        ON dbo.instrument_capture(consent_application_id);
GO

/* Verify — expect 1 and 1 */
SELECT
    CASE WHEN COL_LENGTH('dbo.instrument_capture', 'consent_application_id') IS NULL THEN 0 ELSE 1 END AS has_column,
    (SELECT COUNT(*) FROM sys.indexes
      WHERE name = 'ix_instrument_capture_consent_application_id'
        AND object_id = OBJECT_ID('dbo.instrument_capture'))                                          AS has_index;
