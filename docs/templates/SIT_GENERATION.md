# SIT Generation Script (sit_grouping)

This document provides a ready-to-run SQL Server script to generate SIT file numbers into [dbo].[sit_grouping]:

- Years: 1981–2026 inclusive
- Files per year: 500
- Format: `SIT-<year>-<n>` where n ∈ [1..500]

Column mapping (per request):
- [id] — identity (not supplied by script)
- [sit_awaiting_fileno] — stores SIT file numbers
- [date] — NULL
- [created_by] — 'Generated'
- [year] — year component of the file number
- [landuse] — NULL (not used)
- [created_at] — GETDATE()
- [number] — global sequential number across all inserted rows (per run)
- [registry] — 'SIT'
- [group] — floor((number - 1)/100) + 1
- [sys_batch_no] — same as [group]
- [registry_batch_no] — same as [group]
- [tracking_id] — TRK-XXXXXXXX-YYYYY generated safely from NEWID()

Idempotency:
- The script skips rows whose [sit_awaiting_fileno] already exists in [dbo].[sit_grouping].

Adjustable parameters:
- Year range
- Files per year
- Records per group (default 100)

---

## SQL Server Script

```sql
/*
Generate SIT (1981–2026, 500/year) into [dbo].[sit_grouping].

- [sit_awaiting_fileno] will hold SIT numbers.
- [registry] is set to 'SIT' for all rows (per request).
- [number] is a global running sequence within this insertion run (continues from MAX(number)).
- Duplicate protection via NOT EXISTS on [sit_awaiting_fileno].
*/

SET NOCOUNT ON;

DECLARE @StartYear INT = 1981;
DECLARE @EndYear   INT = 2026;
DECLARE @PerYearCount INT = 500;  -- files per year
DECLARE @GroupSize    INT = 100;  -- records per group
DECLARE @Registry     NVARCHAR(20) = 'SIT';

-- Seeds: continue numbering and grouping globally
DECLARE @GlobalSeed INT = ISNULL((SELECT MAX([number]) FROM [dbo].[sit_grouping]), 0);
DECLARE @RegLocalSeed INT = ISNULL((SELECT COUNT(*) FROM [dbo].[sit_grouping] WHERE [registry] = @Registry), 0);

;WITH Years AS (
    SELECT @StartYear AS y
    UNION ALL SELECT y + 1 FROM Years WHERE y < @EndYear
), PerYear AS (
    SELECT 1 AS n
    UNION ALL SELECT n + 1 FROM PerYear WHERE n < @PerYearCount
), ToInsert AS (
    SELECT
        y AS [year],
        n AS per_year_n,
        CONCAT('SIT-', y, '-', n) AS awaiting_fileno
    FROM Years CROSS JOIN PerYear
), Filtered AS (
    SELECT *
    FROM ToInsert ti
    WHERE NOT EXISTS (
        SELECT 1 FROM [dbo].[sit_grouping] g WHERE g.[sit_awaiting_fileno] = ti.awaiting_fileno
    )
), Numbered AS (
    SELECT f.*, ROW_NUMBER() OVER (ORDER BY f.[year], f.per_year_n) AS rn
    FROM Filtered f
)
INSERT INTO [dbo].[sit_grouping]
    ([sit_awaiting_fileno], [date], [created_by], [year], [landuse], [created_at],
     [number], [registry], [group], [sys_batch_no], [registry_batch_no], [tracking_id])
SELECT
    awaiting_fileno                      AS [sit_awaiting_fileno],
    NULL                                 AS [date],
    'Generated'                          AS [created_by],
    [year]                               AS [year],
    NULL                                 AS [landuse],
    GETDATE()                            AS [created_at],
    @GlobalSeed + rn                     AS [number],
    @Registry                            AS [registry],
    ((@GlobalSeed + rn - 1) / @GroupSize) + 1 AS [group],
    ((@GlobalSeed + rn - 1) / @GroupSize) + 1 AS [sys_batch_no],
    (((@RegLocalSeed + rn) - 1) / @GroupSize) + 1 AS [registry_batch_no],
    'TRK-'
      + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 8)
      + '-'
      + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 5) AS tracking_id
FROM Numbered
OPTION (MAXRECURSION 0);

-- Verification: counts per year
SELECT [year], COUNT(*) AS inserted
FROM [dbo].[sit_grouping]
WHERE [registry] = @Registry
GROUP BY [year]
ORDER BY [year];

-- Sample highest numbers
SELECT TOP (10)
  id, sit_awaiting_fileno, [year], [number], [group], [sys_batch_no], [registry_batch_no], [registry]
FROM [dbo].[sit_grouping]
WHERE [registry] = @Registry
ORDER BY [number] DESC;
```

---

## Notes
- You can rerun the script; duplicates are skipped using the NOT EXISTS check on [sit_awaiting_fileno].
- Grouping is global across the insertion run. If you prefer to reset per year, run per-year inserts with separate seeds.
- If [id] is identity, this script will work as-is. If [id] is not identity and must be provided, adapt to compute it from an offset similarly to [number].
