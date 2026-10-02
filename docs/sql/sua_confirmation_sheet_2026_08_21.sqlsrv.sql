-- ============================================================================
-- SuA Confirmation Sheet — schema changes (SQL SERVER / sqlsrv connection)
-- Run this FIRST, then run sua_confirmation_sheet_2026_08_21.mysql.sql against
-- the MySQL database that holds artisan's `migrations` ledger.
--
-- Mirrors:
--   database/migrations/2026_08_21_100000_add_parcel_no_to_st_file_numbers_table.php
--   database/migrations/2026_08_21_100100_add_institution_fields_to_conversion_applications.php
--   database/migrations/2026_08_21_100200_normalize_allocation_source_lookup_names.php
--
-- Safe to re-run: every statement checks before it changes anything.
-- ============================================================================

/* ---------------------------------------------------------------------------
   1. st_file_numbers — the Parcel Number answered on the SuA File Number
      Commissioning form, printed where the conversion sheet prints a plot no.

      Allocation Source / Entity on that form are unchanged. The institution the
      sheet is addressed to is a per-letter choice made on the print card, so it
      lives on conversion_applications (section 2), not here.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.st_file_numbers', 'parcel_no') IS NULL
    ALTER TABLE dbo.st_file_numbers ADD parcel_no NVARCHAR(100) NULL;
GO

/* ---------------------------------------------------------------------------
   2. conversion_applications — answered per sheet on the print card.

      All three are answered on the print card. institution_category /
      institution_name are suggested from the file's Allocation Source but stay
      editable; addressed_to is the officer THIS letter is written to. Storing
      them per sheet is what stops a reprint contradicting the copy already
      issued.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.conversion_applications', 'institution_category') IS NULL
    ALTER TABLE dbo.conversion_applications ADD institution_category NVARCHAR(20) NULL;
GO
IF COL_LENGTH('dbo.conversion_applications', 'institution_name') IS NULL
    ALTER TABLE dbo.conversion_applications ADD institution_name NVARCHAR(255) NULL;
GO
IF COL_LENGTH('dbo.conversion_applications', 'addressed_to') IS NULL
    ALTER TABLE dbo.conversion_applications ADD addressed_to NVARCHAR(255) NULL;
GO

/* ---------------------------------------------------------------------------
   3. allocation_source_lookups — punctuate the judicial title, like every
      other entry in that list. The name is what gets printed on the letter.

      Deletes the unpunctuated row instead of updating it when both already
      exist, so the unique (type, name) index cannot be violated.

      "OTHERS (SPECIFY)" is deliberately NOT seeded — it is a UI sentinel, and
      picking it stores the typed name as a new lookup row.
   --------------------------------------------------------------------------- */
IF EXISTS (SELECT 1 FROM dbo.allocation_source_lookups
           WHERE type = 'addressed_to_other' AND name = 'HON. JUDGE')
    DELETE FROM dbo.allocation_source_lookups
    WHERE type = 'addressed_to_other' AND name = 'HON JUDGE';
ELSE
    UPDATE dbo.allocation_source_lookups
    SET    name = 'HON. JUDGE', updated_at = SYSDATETIME()
    WHERE  type = 'addressed_to_other' AND name = 'HON JUDGE';
GO

/* ---------------------------------------------------------------------------
   4. Verify
   --------------------------------------------------------------------------- */
SELECT  'st_file_numbers'          AS [table], c.name AS [column]
FROM    sys.columns c
WHERE   c.object_id = OBJECT_ID('dbo.st_file_numbers')
  AND   c.name = 'parcel_no'
UNION ALL
SELECT  'conversion_applications', c.name
FROM    sys.columns c
WHERE   c.object_id = OBJECT_ID('dbo.conversion_applications')
  AND   c.name IN ('institution_category', 'institution_name', 'addressed_to')
ORDER BY [table], [column];
GO

-- Expect 4 rows above, and HON. JUDGE (with the period) below.
SELECT type, name, sort_order, is_active
FROM   dbo.allocation_source_lookups
ORDER  BY type, sort_order;
GO
