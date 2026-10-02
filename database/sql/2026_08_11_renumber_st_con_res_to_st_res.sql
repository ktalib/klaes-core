/*
    Renumber ST conversion primaries commissioned under the old ST-CON-{CODE} format
    onto the plain ST-{CODE} series.

        ST-CON-RES-2026-1  ->  ST-RES-2026-{next free}
        ST-CON-RES-2026-2  ->  ST-RES-2026-{next free}

    The CON mother file numbers (CON-RES-2026-2817 / 2818) are NOT touched — only the
    ST primary changes. These files were commissioned and nothing else: no application,
    no unit (PuA/SuA), no instrument. The script still updates every table an ST
    commissioning writes to, so it stays correct if that assumption is off.

    New serials are taken from the ST-{CODE} pool for the same year: MAX(serial_no) for
    that land_use_code, then the first number whose np_fileno is not already issued.

    Run once. Re-running is a no-op: the WHERE clauses key on the old numbers.

    ---------------------------------------------------------------------------
    NOT covered by this script (filesystem, do by hand after running):
        storage/app/public/EDMS/SCAN_UPLOAD/ST_Registry/ST-CON-RES-2026-1
        storage/app/public/EDMS/SCAN_UPLOAD/ST_Registry/ST-CON-RES-2026-2
    rename each to its new ST-RES-2026-{n} name, or leave them — the next scan
    upload recreates the folder under the new number.
    ---------------------------------------------------------------------------
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Year INT = 2026;
DECLARE @Code VARCHAR(20) = 'RES';          -- ST land-use code of the files being renumbered
DECLARE @OldCode VARCHAR(20) = 'CON-RES';   -- land_use_code currently stored on those rows

/* ---------- 1. The files to renumber, oldest first ---------- */
DECLARE @Map TABLE (
    seq          INT IDENTITY(1,1),
    old_fileno   VARCHAR(100),
    new_fileno   VARCHAR(100) NULL,
    new_serial   INT NULL
);

INSERT INTO @Map (old_fileno)
SELECT np_fileno
FROM   st_file_numbers
WHERE  np_fileno IN ('ST-CON-RES-2026-1', 'ST-CON-RES-2026-2')
GROUP BY np_fileno
ORDER BY MIN(id);          -- keep commissioning order

IF NOT EXISTS (SELECT 1 FROM @Map)
BEGIN
    PRINT 'Nothing to do: neither file number was found.';
    RETURN;
END

/* ---------- 2. Allocate the next free serials in the ST-{CODE} pool ---------- */
DECLARE @Serial INT = (
    SELECT ISNULL(MAX(serial_no), 0)
    FROM   st_file_numbers
    WHERE  land_use_code = @Code AND [year] = @Year
);

DECLARE @Seq INT = 1, @MaxSeq INT = (SELECT MAX(seq) FROM @Map), @Candidate VARCHAR(100);

WHILE @Seq <= @MaxSeq
BEGIN
    SET @Serial = @Serial + 1;
    SET @Candidate = 'ST-' + @Code + '-' + CAST(@Year AS VARCHAR(4)) + '-' + CAST(@Serial AS VARCHAR(10));

    -- Skip a serial whose number is already issued (the pools were shared for a while).
    IF EXISTS (SELECT 1 FROM st_file_numbers WHERE np_fileno = @Candidate)
        CONTINUE;

    UPDATE @Map SET new_fileno = @Candidate, new_serial = @Serial WHERE seq = @Seq;
    SET @Seq = @Seq + 1;
END

PRINT 'Planned renumbering:';
SELECT old_fileno AS [from], new_fileno AS [to], new_serial FROM @Map ORDER BY seq;

/* ---------- 3. Apply ---------- */
BEGIN TRANSACTION;

    -- 3.1 st_file_numbers: the primary row, plus any unit row that hangs off it.
    UPDATE s
       SET s.np_fileno     = m.new_fileno,
           s.land_use_code = @Code,
           s.serial_no     = m.new_serial,
           s.updated_at    = SYSDATETIME()
      FROM st_file_numbers s
      JOIN @Map m ON s.np_fileno = m.old_fileno;

    -- Unit files carry the parent number in fileno: ST-CON-RES-2026-1-001.
    UPDATE s
       SET s.fileno     = m.new_fileno + SUBSTRING(s.fileno, LEN(m.old_fileno) + 1, 50),
           s.updated_at = SYSDATETIME()
      FROM st_file_numbers s
      JOIN @Map m ON s.fileno LIKE m.old_fileno + '-%';

    -- A primary whose fileno held its own number (no CON mother recorded).
    UPDATE s
       SET s.fileno = m.new_fileno, s.updated_at = SYSDATETIME()
      FROM st_file_numbers s JOIN @Map m ON s.fileno = m.old_fileno;

    UPDATE s
       SET s.mls_fileno = m.new_fileno, s.updated_at = SYSDATETIME()
      FROM st_file_numbers s JOIN @Map m ON s.mls_fileno = m.old_fileno;

    -- 3.2 fileNumber mirror
    UPDATE f
       SET f.st_file_no = m.new_fileno, f.updated_at = SYSDATETIME()
      FROM fileNumber f JOIN @Map m ON f.st_file_no = m.old_fileno;

    -- 3.3 file_indexings: the ST row itself, and any row pointing at it
    UPDATE i
       SET i.file_number = m.new_fileno, i.updated_at = SYSDATETIME()
      FROM file_indexings i JOIN @Map m ON i.file_number = m.old_fileno;

    UPDATE i
       SET i.mls_file_no = m.new_fileno, i.updated_at = SYSDATETIME()
      FROM file_indexings i JOIN @Map m ON i.mls_file_no = m.old_fileno;

    -- related_fileno is a JSON array of plain numbers.
    UPDATE i
       SET i.related_fileno = REPLACE(i.related_fileno, m.old_fileno, m.new_fileno),
           i.updated_at = SYSDATETIME()
      FROM file_indexings i JOIN @Map m ON i.related_fileno LIKE '%' + m.old_fileno + '%';

    -- 3.4 related_file_number (typed links, e.g. Mother File)
    UPDATE r
       SET r.file_number = m.new_fileno, r.updated_at = SYSDATETIME()
      FROM related_file_number r JOIN @Map m ON r.file_number = m.old_fileno;

    UPDATE r
       SET r.related_fileno = m.new_fileno,
           r.comment = REPLACE(ISNULL(r.comment, ''), m.old_fileno, m.new_fileno),
           r.updated_at = SYSDATETIME()
      FROM related_file_number r JOIN @Map m ON r.related_fileno = m.old_fileno;

    -- 3.5 decommissioned_files: the ST number is the successor of the mother file
    UPDATE d
       SET d.successor_file_no = m.new_fileno,
           d.decommissioning_reason = REPLACE(ISNULL(d.decommissioning_reason, ''), m.old_fileno, m.new_fileno),
           d.updated_at = SYSDATETIME()
      FROM decommissioned_files d JOIN @Map m ON d.successor_file_no = m.old_fileno;

    UPDATE d
       SET d.file_no = m.new_fileno, d.updated_at = SYSDATETIME()
      FROM decommissioned_files d JOIN @Map m ON d.file_no = m.old_fileno;

    UPDATE d
       SET d.mls_file_no = m.new_fileno, d.updated_at = SYSDATETIME()
      FROM decommissioned_files d JOIN @Map m ON d.mls_file_no = m.old_fileno;

    -- 3.6 staging tables
    UPDATE e
       SET e.file_number = m.new_fileno, e.updated_at = SYSDATETIME()
      FROM entities_staging e JOIN @Map m ON e.file_number = m.old_fileno;

    UPDATE c
       SET c.file_number = m.new_fileno,
           c.account_no  = CASE WHEN c.account_no = m.old_fileno THEN m.new_fileno ELSE c.account_no END,
           c.updated_at  = SYSDATETIME()
      FROM customers_staging c JOIN @Map m ON c.file_number = m.old_fileno;

    -- 3.7 commissioning sheets already printed for these files
    UPDATE s
       SET s.related_file_number = m.new_fileno, s.updated_at = SYSDATETIME()
      FROM file_commissioning_sheets s JOIN @Map m ON s.related_file_number = m.old_fileno;

    UPDATE s
       SET s.file_number = m.new_fileno, s.updated_at = SYSDATETIME()
      FROM file_commissioning_sheets s JOIN @Map m ON s.file_number = m.old_fileno;

COMMIT TRANSACTION;

/* ---------- 4. Verify: nothing should still carry an old number ---------- */
PRINT 'Remaining references to the old numbers (expect 0 rows):';

SELECT 'st_file_numbers' AS [table], np_fileno AS value FROM st_file_numbers s
    JOIN @Map m ON s.np_fileno = m.old_fileno OR s.fileno = m.old_fileno OR s.mls_fileno = m.old_fileno
UNION ALL SELECT 'fileNumber', f.st_file_no FROM fileNumber f JOIN @Map m ON f.st_file_no = m.old_fileno
UNION ALL SELECT 'file_indexings', i.file_number FROM file_indexings i
    JOIN @Map m ON i.file_number = m.old_fileno OR i.mls_file_no = m.old_fileno OR i.related_fileno LIKE '%' + m.old_fileno + '%'
UNION ALL SELECT 'related_file_number', r.file_number FROM related_file_number r
    JOIN @Map m ON r.file_number = m.old_fileno OR r.related_fileno = m.old_fileno
UNION ALL SELECT 'decommissioned_files', d.successor_file_no FROM decommissioned_files d
    JOIN @Map m ON d.successor_file_no = m.old_fileno OR d.file_no = m.old_fileno OR d.mls_file_no = m.old_fileno
UNION ALL SELECT 'entities_staging', e.file_number FROM entities_staging e JOIN @Map m ON e.file_number = m.old_fileno
UNION ALL SELECT 'customers_staging', c.file_number FROM customers_staging c JOIN @Map m ON c.file_number = m.old_fileno
UNION ALL SELECT 'file_commissioning_sheets', s.file_number FROM file_commissioning_sheets s
    JOIN @Map m ON s.file_number = m.old_fileno OR s.related_file_number = m.old_fileno;

PRINT 'Result:';
SELECT m.new_fileno,
       s.land_use_code,
       s.serial_no,
       s.mls_fileno   AS mother_file,
       s.application_type,
       s.file_no_type
FROM   @Map m
JOIN   st_file_numbers s ON s.np_fileno = m.new_fileno
ORDER  BY m.seq;
