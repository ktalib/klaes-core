-- =====================================================
-- EDMS Database Updates - SET DEFAULT VALUES
-- Run this AFTER DATABASE_UPDATE_SIMPLE.sql
-- =====================================================

PRINT 'Setting default values for new columns...';

-- STEP 4: SET DEFAULT VALUES USING DYNAMIC SQL
-- =====================================================

-- Set default QC status for existing page typings
DECLARE @sql NVARCHAR(MAX);

PRINT 'Updating pagetypings table defaults...';

-- Update qc_status
SET @sql = 'UPDATE pagetypings SET qc_status = ''pending'' WHERE qc_status IS NULL';
EXEC sp_executesql @sql;
PRINT '✓ Set qc_status defaults';

-- Update qc_overridden
SET @sql = 'UPDATE pagetypings SET qc_overridden = 0 WHERE qc_overridden IS NULL';
EXEC sp_executesql @sql;
PRINT '✓ Set qc_overridden defaults';

-- Update has_qc_issues for pagetypings
SET @sql = 'UPDATE pagetypings SET has_qc_issues = 0 WHERE has_qc_issues IS NULL';
EXEC sp_executesql @sql;
PRINT '✓ Set has_qc_issues defaults for pagetypings';

PRINT 'Updating file_indexings table defaults...';

-- Update is_updated
SET @sql = 'UPDATE file_indexings SET is_updated = 0 WHERE is_updated IS NULL';
EXEC sp_executesql @sql;
PRINT '✓ Set is_updated defaults';

-- Update has_qc_issues for file_indexings
SET @sql = 'UPDATE file_indexings SET has_qc_issues = 0 WHERE has_qc_issues IS NULL';
EXEC sp_executesql @sql;
PRINT '✓ Set has_qc_issues defaults for file_indexings';

-- Update workflow_status based on existing data
SET @sql = '
UPDATE file_indexings 
SET workflow_status = CASE 
    WHEN EXISTS (SELECT 1 FROM pagetypings p WHERE p.file_indexing_id = file_indexings.id) THEN ''pagetyped''
    WHEN EXISTS (SELECT 1 FROM scannings s WHERE s.file_indexing_id = file_indexings.id) THEN ''uploaded''
    ELSE ''indexed''
END
WHERE workflow_status IS NULL';
EXEC sp_executesql @sql;
PRINT '✓ Set workflow_status defaults based on existing data';

PRINT '';
PRINT '=== NEXT: RUN THE CONSTRAINTS SCRIPT ===';
PRINT 'Now run DATABASE_UPDATE_CONSTRAINTS.sql to add constraints and indexes';