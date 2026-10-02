/*
 * House No and Plot No were one combined entry on every address-builder card.
 * They are now two, so each address builder that persists its sub-fields needs
 * a column for the house number beside the plot number it already had.
 *
 * Only oss_applications is affected: the Change of Purpose, Change of Name,
 * Loss of Document, Change of Ownership and Verification cards keep only the
 * assembled address string, so their split is front-end only.
 *
 * Idempotent: re-running adds nothing. Target: sqlsrv connection (klas).
 */
DECLARE @cols TABLE (name SYSNAME);
INSERT INTO @cols (name) VALUES
    ('res_addr_house'), ('res_corr_house'), ('res_biz_house'),
    ('com_biz_house'),  ('com_corr_house'),
    ('ind_biz_house'),  ('ind_corr_house'),
    ('agr_biz_house'),  ('agr_corr_house');

DECLARE @name SYSNAME, @sql NVARCHAR(MAX);
DECLARE c CURSOR FOR SELECT name FROM @cols;
OPEN c;
FETCH NEXT FROM c INTO @name;
WHILE @@FETCH_STATUS = 0
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'oss_applications' AND COLUMN_NAME = @name
    )
    BEGIN
        SET @sql = N'ALTER TABLE oss_applications ADD [' + @name + N'] NVARCHAR(100) NULL';
        EXEC sp_executesql @sql;
    END
    FETCH NEXT FROM c INTO @name;
END
CLOSE c;
DEALLOCATE c;
