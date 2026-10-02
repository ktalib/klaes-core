/* ---------------------------------------------------------------------------
   Why does a file show no shelf/rack chip?

   The chip is blank only when BOTH sources are empty:
     1. file_indexings.shelf_location — the recorded shelf, and
     2. the shelf_rack_ranges workbook map, which ShelfRackLocator falls back to.

   The fallback needs three things to line up: the file number must end in a
   serial, the registry must resolve to a numeric id, and shelf_rack_ranges must
   hold a row for THAT registry_id + series whose serial_from..serial_to
   brackets the serial. The registry has to match — a workbook row for the same
   series filed under another registry is deliberately not used.

   Set @file_number and run the whole script. Section 6 is the verdict.
--------------------------------------------------------------------------- */

SET NOCOUNT ON;

DECLARE @file_number varchar(100) = 'RES-1996-73';   -- <== the file to check

/* Parse "<series>-<serial>": series is everything before the last dash. */
DECLARE @series varchar(100), @serial int, @reg varchar(100), @reg_eff varchar(50);

SELECT
    @series = UPPER(LTRIM(RTRIM(LEFT(@file_number, LEN(@file_number) - CHARINDEX('-', REVERSE(@file_number)))))),
    @serial = TRY_CAST(RIGHT(@file_number, CHARINDEX('-', REVERSE(@file_number)) - 1) AS int)
WHERE CHARINDEX('-', REVERSE(@file_number)) > 0;

SELECT @reg = LTRIM(RTRIM(ISNULL(CAST(registry AS varchar(100)), '')))
FROM dbo.file_indexings WHERE file_number = @file_number;

/* The numeric registry id the map is keyed by. Most rows hold it already; rows
   holding the NAME are recovered only for the Lands Registry set, and only via
   the series (CON-RES -> 3, RES/COM/IND/AG -> 1). Anything else dead-ends. */
SET @reg_eff =
    CASE
        WHEN @reg = ''                                       THEN NULL
        WHEN @reg NOT LIKE '%[^0-9]%'                        THEN @reg
        WHEN LOWER(@reg) NOT IN ('lands registry', 'lands')  THEN NULL
        WHEN @series LIKE 'CON-RES-%'                        THEN '3'
        WHEN @series LIKE 'RES-%' OR @series LIKE 'COM-%'
          OR @series LIKE 'IND-%' OR @series LIKE 'AG-%'     THEN '1'
        ELSE NULL
    END;

/* ---- 1. what the file itself records ---------------------------------- */
SELECT
    f.id,
    f.file_number,
    f.registry,
    f.shelf_location,
    CASE
        WHEN LTRIM(RTRIM(ISNULL(f.shelf_location, ''))) <> '' THEN 'RECORDED — the chip should be showing this'
        ELSE 'no recorded shelf — falls back to the workbook map'
    END AS recorded_status
FROM dbo.file_indexings f
WHERE f.file_number = @file_number;

/* ---- 2. how the lookup key is built ----------------------------------- */
SELECT @file_number AS file_number, @series AS series, @serial AS serial,
       @reg AS registry_raw, @reg_eff AS effective_registry_id;

/* ---- 3. the row the locator would actually use ------------------------ */
/* Same registry AND same series AND serial in range. Empty = no shelf derived. */
SELECT TOP 1
    r.registry_id, r.file_no, r.serial_from, r.serial_to, r.rack_shelf, r.set_version
FROM dbo.shelf_rack_ranges r
WHERE CAST(r.registry_id AS varchar(50)) = @reg_eff
  AND UPPER(LTRIM(RTRIM(r.file_no))) = @series
  AND @serial BETWEEN r.serial_from AND r.serial_to
ORDER BY r.set_version, r.rack, r.shelf;   -- lowest rack wins, as in the locator

/* ---- 4. every workbook row for this series, any registry -------------- */
/* Context for section 3. A row here whose registry_id differs from the
   effective id is coverage the locator will NOT use — the workbooks relabel
   some CON-RES-* ranges as RES-* under registry 3, and honouring those would
   report a shelf the file is not on. */
SELECT
    r.registry_id, r.file_no, r.serial_from, r.serial_to, r.rack_shelf, r.set_version,
    CASE WHEN @serial BETWEEN r.serial_from AND r.serial_to THEN 'yes' ELSE '' END AS brackets_serial,
    CASE WHEN CAST(r.registry_id AS varchar(50)) = @reg_eff THEN 'yes' ELSE 'NO — wrong registry' END AS registry_matches
FROM dbo.shelf_rack_ranges r
WHERE UPPER(LTRIM(RTRIM(r.file_no))) IN (@series, 'CON-' + @series)
ORDER BY r.registry_id, r.set_version, r.serial_from;

/* ---- 5. map coverage overall ------------------------------------------ */
SELECT registry_id, COUNT(*) AS ranges, COUNT(DISTINCT file_no) AS series_covered
FROM dbo.shelf_rack_ranges
GROUP BY registry_id ORDER BY registry_id;
-- Expect ~1,680 rows across registries 1 and 3 only. Zero rows means the
-- import never ran on this server:  php artisan shelf-racks:import

/* ---- 6. verdict -------------------------------------------------------- */
SELECT CASE
    WHEN NOT EXISTS (SELECT 1 FROM dbo.file_indexings WHERE file_number = @file_number)
        THEN 'no such file_number in file_indexings'
    WHEN EXISTS (SELECT 1 FROM dbo.file_indexings
                 WHERE file_number = @file_number
                   AND LTRIM(RTRIM(ISNULL(shelf_location, ''))) <> '')
        THEN 'SHELF IS RECORDED — if the chip is blank the bug is in the view, not the data'
    WHEN @serial IS NULL
        THEN 'TRULY MISSING — file number has no trailing serial, so it cannot be placed on the map'
    WHEN @reg_eff IS NULL
        THEN 'TRULY MISSING — registry (' + ISNULL(NULLIF(@reg, ''), 'blank') + ') does not resolve to a map registry id'
    WHEN NOT EXISTS (SELECT 1 FROM dbo.shelf_rack_ranges
                     WHERE CAST(registry_id AS varchar(50)) = @reg_eff
                       AND UPPER(LTRIM(RTRIM(file_no))) = @series)
        THEN 'TRULY MISSING — the workbooks do not cover series ' + @series + ' under registry ' + @reg_eff
    WHEN NOT EXISTS (SELECT 1 FROM dbo.shelf_rack_ranges
                     WHERE CAST(registry_id AS varchar(50)) = @reg_eff
                       AND UPPER(LTRIM(RTRIM(file_no))) = @series
                       AND @serial BETWEEN serial_from AND serial_to)
        THEN 'TRULY MISSING — series is covered but serial ' + CAST(@serial AS varchar(20)) + ' falls outside every range'
    ELSE 'DERIVABLE — section 3 has the shelf; a blank chip here is a bug'
END AS verdict;

/* ---- 7. how widespread, across the archive ---------------------------- */
SELECT
    ISNULL(CAST(f.registry AS varchar(50)), '(null)') AS registry,
    COUNT(*) AS files,
    SUM(CASE WHEN LTRIM(RTRIM(ISNULL(f.shelf_location, ''))) <> '' THEN 1 ELSE 0 END) AS with_recorded_shelf,
    SUM(CASE WHEN LTRIM(RTRIM(ISNULL(f.shelf_location, ''))) =  '' THEN 1 ELSE 0 END) AS blank_shelf
FROM dbo.file_indexings f
GROUP BY CAST(f.registry AS varchar(50))
ORDER BY files DESC;
