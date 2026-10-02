-- ============================================================================
-- SuA "Allocation Source" = the allocating institution (SQL SERVER / sqlsrv)
--
-- Run this FIRST, then run sua_allocation_source_institution_2026_08_21.mysql.sql
-- against the MySQL database that holds artisan's `migrations` ledger.
--
-- Mirrors:
--   database/migrations/2026_08_21_110000_add_institution_fields_to_st_file_numbers_table.php
--
-- Safe to re-run: every statement checks before it changes anything.
-- ============================================================================

/* ---------------------------------------------------------------------------
   1. st_file_numbers — the SuA commissioning form now answers "Allocation
      Source" with the name of the allocating institution, picked from
      allocation_source_lookups, instead of the binary State/Local Government
      question.

      allocation_source / allocation_entity are still written alongside these
      (derived by AllocationSourceResolver::toLegacy), because the Standalone
      Unit Application form, instrument registration and the LGA sheet all still
      read the old pair. Only these two columns can hold the institution name,
      and it is what the Confirmation Sheet shows back as its Allocation Source.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.st_file_numbers', 'institution_category') IS NULL
    ALTER TABLE dbo.st_file_numbers ADD institution_category NVARCHAR(20) NULL;
GO
IF COL_LENGTH('dbo.st_file_numbers', 'institution_name') IS NULL
    ALTER TABLE dbo.st_file_numbers ADD institution_name NVARCHAR(255) NULL;
GO

/* ---------------------------------------------------------------------------
   2. Verify — expect both columns.

      Nothing is back-filled on purpose: every reader goes through
      AllocationSourceResolver::resolve(), which reads the institution off the
      legacy pair for files commissioned before this change.
   --------------------------------------------------------------------------- */
SELECT  c.name AS [column], t.name AS [type], c.max_length
FROM    sys.columns c
JOIN    sys.types t ON t.user_type_id = c.user_type_id
WHERE   c.object_id = OBJECT_ID('dbo.st_file_numbers')
  AND   c.name IN ('institution_category', 'institution_name')
ORDER   BY c.name;
GO
