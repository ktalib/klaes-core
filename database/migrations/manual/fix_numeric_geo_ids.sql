/* ---------------------------------------------------------------------------
   Replace bare district / lga reference ids with their names.

   Cause: populateSelectFromText() in create-indexing-dialog.js submitted the
   reference-row id ("10173") instead of the name ("GIGINYU"), so the archive
   grid renders a number where a district should be.

   Only values that match a real row in [districts] / [lgas] are touched.
   Numbers that resolve to nothing (the 1-78 space, orphaned by an earlier
   dedup of [districts]) are LEFT ALONE — there is no name to write.

   Run order matters per table: lga BEFORE district. [location] reads
   "PLOT, STREET, DISTRICT, LGA, KANO STATE" and each pass rewrites the
   rightmost token equal to its id, so where a row's district id and lga id
   are the same number the lga pass must consume the trailing token first.

   Section 1 rewrites the columns the screen reads — that alone fixes the
   badges. Section 2 (location strings) is optional and is easier to run as
   `php artisan geo:fix-numeric-ids --apply`, which does both.
--------------------------------------------------------------------------- */

SET NOCOUNT ON;
BEGIN TRANSACTION;

/* ---- 0. rollback safety net ------------------------------------------- */
IF OBJECT_ID('dbo.geo_fix_backup_20260812', 'U') IS NOT NULL
    DROP TABLE dbo.geo_fix_backup_20260812;

CREATE TABLE dbo.geo_fix_backup_20260812 (
    table_name  varchar(128),
    column_name varchar(128),
    row_id      bigint,
    old_value   nvarchar(255),
    new_value   nvarchar(255)
);

/* ---- 1. columns -------------------------------------------------------- */
/* Each block: back up what changes, then change it. `NOT LIKE '%[^0-9]%'`
   means "every character is a digit". TRY_CAST guards the join. */

DECLARE @sql nvarchar(max) = N'';

DECLARE @targets TABLE (ord int IDENTITY, tbl sysname, col sysname, ref sysname);
INSERT INTO @targets (tbl, col, ref) VALUES
    ('file_indexings',                'lga',        'lgas'),
    ('file_indexings',                'district',   'districts'),
    ('fileNumber',                    'lga',        'lgas'),
    ('pra',                           'lgsaOrCity', 'lgas'),
    ('CofO_staging',                  'lgsaOrCity', 'lgas'),
    ('file_history_staging',          'lgsaOrCity', 'lgas'),
    ('pic',                           'lgsaOrCity', 'lgas'),
    ('title_status_applications',     'lga',        'lgas'),
    ('title_status_applications',     'district',   'districts'),
    ('plot_subdivision_applications', 'lga',        'lgas'),
    ('plot_subdivision_applications', 'district',   'districts'),
    ('plot_merger_applications',      'lga',        'lgas'),
    ('plot_merger_applications',      'district',   'districts'),
    ('plot_extension_applications',   'lga',        'lgas'),
    ('plot_extension_applications',   'district',   'districts'),
    ('plot_separation_applications',  'lga',        'lgas'),
    ('deprecated_records',            'lga',        'lgas'),
    ('deprecated_records',            'district',   'districts'),
    ('edms',                          'lga',        'lgas');

DECLARE @i int = 1, @n int = (SELECT MAX(ord) FROM @targets);
DECLARE @tbl sysname, @col sysname, @ref sysname;

WHILE @i <= @n
BEGIN
    SELECT @tbl = tbl, @col = col, @ref = ref FROM @targets WHERE ord = @i;

    IF OBJECT_ID('dbo.' + @tbl, 'U') IS NOT NULL
       AND COL_LENGTH('dbo.' + @tbl, @col) IS NOT NULL
       AND COL_LENGTH('dbo.' + @tbl, 'id') IS NOT NULL
    BEGIN
        SET @sql = N'
            INSERT INTO dbo.geo_fix_backup_20260812 (table_name, column_name, row_id, old_value, new_value)
            SELECT ''' + @tbl + ''', ''' + @col + ''', t.id, t.[' + @col + '], r.name
            FROM dbo.[' + @tbl + '] t
            JOIN dbo.[' + @ref + '] r ON r.id = TRY_CAST(LTRIM(RTRIM(t.[' + @col + '])) AS int)
            WHERE t.[' + @col + '] NOT LIKE ''%[^0-9]%''
              AND LTRIM(RTRIM(t.[' + @col + '])) <> '''';

            UPDATE t SET t.[' + @col + '] = r.name
            FROM dbo.[' + @tbl + '] t
            JOIN dbo.[' + @ref + '] r ON r.id = TRY_CAST(LTRIM(RTRIM(t.[' + @col + '])) AS int)
            WHERE t.[' + @col + '] NOT LIKE ''%[^0-9]%''
              AND LTRIM(RTRIM(t.[' + @col + '])) <> '''';';

        EXEC sp_executesql @sql;
        PRINT @tbl + '.' + @col + ' -> ' + CAST(@@ROWCOUNT AS varchar(10)) + ' rows';
    END
    ELSE
        PRINT 'skip ' + @tbl + '.' + @col + ' (table or column not found)';

    SET @i = @i + 1;
END

/* deed_registrations / deeds_bill_balances_metadata are deliberately NOT in
   the list above: their numeric districts are small legacy codes whose
   accidental collisions with [districts].id (31 -> "Gwala", 38 -> "Kano City")
   would write the wrong name. Review those by hand. */

/* ---- 2. review, then commit ------------------------------------------- */
SELECT table_name, column_name, COUNT(*) AS rows_changed
FROM dbo.geo_fix_backup_20260812
GROUP BY table_name, column_name
ORDER BY table_name, column_name;

-- Inspect the numbers above, then run ONE of these:
--   COMMIT TRANSACTION;
--   ROLLBACK TRANSACTION;

/* ---- rollback after commit, if needed ---------------------------------
UPDATE t SET t.district = b.old_value
FROM dbo.file_indexings t
JOIN dbo.geo_fix_backup_20260812 b
  ON b.row_id = t.id AND b.table_name = 'file_indexings' AND b.column_name = 'district';
-- ...repeat per table/column pair from the backup table.
------------------------------------------------------------------------- */
