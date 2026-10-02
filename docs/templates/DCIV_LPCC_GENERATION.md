# DCIV and LPCC Generation Script (dciv_grouping)

This document provides a ready-to-run SQL Server script to generate two sets of file numbers into [dbo].[dciv_grouping]:

- LPCC: years 1981–2022 inclusive, 1,000 files per year, format: `LPCC-<year>-<n>`
- DCIV: years 2020–2026 inclusive, 1,000 files per year, format: `DCIV-<year>-<n>`

Column mapping (per request):
- [id] — identity (not supplied by script)
- [dciv_awaiting_fileno] — stores both LPCC and DCIV file numbers
- [date] — NULL
- [created_by] — 'Generated'
- [year] — year component of the file number
- [landuse] — NULL (not used)
- [created_at] — GETDATE()
- [number] — global sequential number across all inserted rows (per run)
- [registry] — 'DCIV' (as requested)
- [group] — floor((number - 1)/100) + 1
- [sys_batch_no] — same as [group]
- [registry_batch_no] — same as [group]
- [tracking_id] — TRK-XXXXXXXX-YYYYY generated safely from NEWID()

Idempotency:
- The script skips rows whose [dciv_awaiting_fileno] already exists in [dbo].[dciv_grouping].

Adjustable parameters:
- Year ranges
- Files per year
- Records per group (default 100)

---

## SQL Server Script

```sql
/*
Generate LPCC (1981–2022, 1000/year) and DCIV (2020–2026, 1000/year)
into [dbo].[dciv_grouping].

- [dciv_awaiting_fileno] will hold both LPCC and DCIV numbers.
- [registry] is set to 'DCIV' for all rows (per request).
- [number] is a global running sequence within this insertion run.
- Duplicate protection via NOT EXISTS on [dciv_awaiting_fileno].
*/

SET NOCOUNT ON;

DECLARE @LPCC_StartYear INT = 1981;
DECLARE @LPCC_EndYear   INT = 2022;
DECLARE @DCIV_StartYear INT = 2020;
DECLARE @DCIV_EndYear   INT = 2026;
DECLARE @PerYearCount   INT = 1000;   -- files per year for both series
DECLARE @GroupSize      INT = 100;    -- records per group

-- Base table
DECLARE @TableName SYSNAME = N'dbo.dciv_grouping';

-- Running global number seed (per run)
DECLARE @Seed INT = 0;

/*
Helper: returns a set of (series, year, n, awaiting_fileno, number) for the requested
prefix and year range, with local per-year n = 1..@PerYearCount. The global number
will be assigned in the outer SELECT by adding to @Seed.
*/

-- LPCC generation
;WITH Years AS (
    SELECT @LPCC_StartYear AS y
    UNION ALL SELECT y + 1 FROM Years WHERE y < @LPCC_EndYear
), PerYear AS (
    SELECT 1 AS n
    UNION ALL SELECT n + 1 FROM PerYear WHERE n < @PerYearCount
), LPCC AS (
    SELECT
        'LPCC' AS series,
        y AS [year],
        n AS per_year_n,
        CONCAT('LPCC-', y, '-', n) AS awaiting_fileno
    FROM Years CROSS JOIN PerYear
)
INSERT INTO [dbo].[dciv_grouping]
    ([dciv_awaiting_fileno], [date], [created_by], [year], [landuse], [created_at],
     [number], [registry], [group], [sys_batch_no], [registry_batch_no], [tracking_id])
SELECT
    L.awaiting_fileno                    AS [dciv_awaiting_fileno],
    NULL                                 AS [date],
    'Generated'                          AS [created_by],
    L.[year]                             AS [year],
    NULL                                 AS [landuse],
    GETDATE()                            AS [created_at],
    ROW_NUMBER() OVER (ORDER BY L.[year], L.per_year_n) + @Seed AS [number],
    'DCIV'                               AS [registry],
    ((ROW_NUMBER() OVER (ORDER BY L.[year], L.per_year_n) + @Seed - 1) / @GroupSize) + 1 AS [group],
    ((ROW_NUMBER() OVER (ORDER BY L.[year], L.per_year_n) + @Seed - 1) / @GroupSize) + 1 AS [sys_batch_no],
    ((ROW_NUMBER() OVER (ORDER BY L.[year], L.per_year_n) + @Seed - 1) / @GroupSize) + 1 AS [registry_batch_no],
    'TRK-'
    + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 8)
    + '-'
    + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 5) AS tracking_id
FROM LPCC AS L
WHERE NOT EXISTS (
    SELECT 1 FROM [dbo].[dciv_grouping] g WHERE g.[dciv_awaiting_fileno] = L.awaiting_fileno
)
OPTION (MAXRECURSION 0);

-- Update @Seed to continue numbering for DCIV after LPCC inserts
SELECT @Seed = ISNULL(MAX([number]), 0) FROM [dbo].[dciv_grouping];

-- DCIV generation
;WITH Years AS (
    SELECT @DCIV_StartYear AS y
    UNION ALL SELECT y + 1 FROM Years WHERE y < @DCIV_EndYear
), PerYear AS (
    SELECT 1 AS n
    UNION ALL SELECT n + 1 FROM PerYear WHERE n < @PerYearCount
), DCIV AS (
    SELECT
        'DCIV' AS series,
        y AS [year],
        n AS per_year_n,
        CONCAT('DCIV-', y, '-', n) AS awaiting_fileno
    FROM Years CROSS JOIN PerYear
)
INSERT INTO [dbo].[dciv_grouping]
    ([dciv_awaiting_fileno], [date], [created_by], [year], [landuse], [created_at],
     [number], [registry], [group], [sys_batch_no], [registry_batch_no], [tracking_id])
SELECT
    D.awaiting_fileno                    AS [dciv_awaiting_fileno],
    NULL                                 AS [date],
    'Generated'                          AS [created_by],
    D.[year]                             AS [year],
    NULL                                 AS [landuse],
    GETDATE()                            AS [created_at],
    ROW_NUMBER() OVER (ORDER BY D.[year], D.per_year_n) + @Seed AS [number],
    'DCIV'                               AS [registry],
    ((ROW_NUMBER() OVER (ORDER BY D.[year], D.per_year_n) + @Seed - 1) / @GroupSize) + 1 AS [group],
    ((ROW_NUMBER() OVER (ORDER BY D.[year], D.per_year_n) + @Seed - 1) / @GroupSize) + 1 AS [sys_batch_no],
    ((ROW_NUMBER() OVER (ORDER BY D.[year], D.per_year_n) + @Seed - 1) / @GroupSize) + 1 AS [registry_batch_no],
    'TRK-'
    + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 8)
    + '-'
    + LEFT(REPLACE(CONVERT(varchar(36), NEWID()), '-', ''), 5) AS tracking_id
FROM DCIV AS D
WHERE NOT EXISTS (
    SELECT 1 FROM [dbo].[dciv_grouping] g WHERE g.[dciv_awaiting_fileno] = D.awaiting_fileno
)
OPTION (MAXRECURSION 0);

-- Verification: sample highest rows
SELECT TOP (10)
    id, dciv_awaiting_fileno, [year], [number], [group], [sys_batch_no], [registry_batch_no], [registry]
FROM [dbo].[dciv_grouping]
ORDER BY [number] DESC;
```

---

## Notes
- You can rerun the script; duplicates are skipped using the NOT EXISTS check on [dciv_awaiting_fileno].
- Grouping is global across the combined insertion run (LPCC first, then DCIV). If you prefer registry-batch to reset per series, split runs and reset @Seed between series.
- If [id] is identity, this script will work as-is. If [id] is not identity and must be provided, adapt to compute it from an offset similarly to [number].
