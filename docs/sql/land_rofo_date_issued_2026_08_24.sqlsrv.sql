-- ============================================================================
-- DATE OF ISSUE gets a column of its own — land_recommendations   (SQL SERVER)
--
-- The RofO letter printed land_recommendations.application_date as its DATE OF
-- ISSUE, and the print dialog wrote to it. application_date is the
-- recommendation's own field though — required on the recommendation form,
-- carried in its list and export, and printed on page 2 of the letter as the
-- applicant's acceptance date. Issuing a letter must not edit it.
--
-- date_issued is that value and nothing else. It is deliberately NOT backfilled:
-- a value here means a person keyed it in at the printer. Every existing row
-- stays NULL, and the print dialog asks for the date on the next print.
--
-- Run this FIRST, then land_rofo_date_issued_2026_08_24.mysql.sql to record the
-- migration in artisan's ledger. Safe to re-run: it does nothing if the column
-- is already there.
-- ============================================================================

IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'land_recommendations'
      AND COLUMN_NAME = 'date_issued'
)
BEGIN
    ALTER TABLE land_recommendations ADD date_issued date NULL;
    PRINT 'land_recommendations.date_issued added.';
END
ELSE
BEGIN
    PRINT 'land_recommendations.date_issued already exists — nothing to do.';
END
GO

-- Verify: one row, date, NULLable, and nothing in it yet.
SELECT
    c.COLUMN_NAME,
    c.DATA_TYPE,
    c.IS_NULLABLE,
    (SELECT COUNT(*) FROM land_recommendations WHERE date_issued IS NOT NULL) AS rows_with_a_value
FROM INFORMATION_SCHEMA.COLUMNS c
WHERE c.TABLE_NAME = 'land_recommendations'
  AND c.COLUMN_NAME = 'date_issued';
GO
