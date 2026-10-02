-- ============================================================================
-- SuA Allocation Reference No (SQL SERVER / sqlsrv)
--
-- Run this FIRST, then run sua_allocation_reference_no_2026_08_22.mysql.sql
-- against the MySQL database that holds artisan's `migrations` ledger.
--
-- Mirrors:
--   database/migrations/2026_08_22_100000_add_allocation_reference_no_to_st_file_numbers_table.php
--
-- Safe to re-run: the statement checks before it changes anything.
-- ============================================================================

/* ---------------------------------------------------------------------------
   1. st_file_numbers — the SuA commissioning form asks for two distinct
      numbers now:

        allocation_ref_no        the slip the allocation was raised under.
                                 Already present. Printed on the Confirmation
                                 Sheet, and required from this release on.

        allocation_reference_no  the allocation's own reference. Added here.
                                 Optional, and recorded against the file only —
                                 it is deliberately never printed on the sheet.

      allocation_ref_no is left exactly as it is: the Standalone Unit
      Application form labels that same column "Allocation Reference No", and
      re-pointing it would silently move every value already captured there.
   --------------------------------------------------------------------------- */
IF COL_LENGTH('dbo.st_file_numbers', 'allocation_reference_no') IS NULL
    ALTER TABLE dbo.st_file_numbers ADD allocation_reference_no NVARCHAR(100) NULL;
GO

/* ---------------------------------------------------------------------------
   2. Verify — expect one row.
   --------------------------------------------------------------------------- */
SELECT  c.name AS [column], t.name AS [type], c.max_length
FROM    sys.columns c
JOIN    sys.types t ON t.user_type_id = c.user_type_id
WHERE   c.object_id = OBJECT_ID('dbo.st_file_numbers')
  AND   c.name = 'allocation_reference_no';
GO
