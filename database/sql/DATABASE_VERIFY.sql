-- =====================================================
-- EDMS Database Updates - VERIFICATION
-- Run this AFTER all other scripts to verify changes
-- =====================================================

PRINT '=== VERIFYING DATABASE UPDATES ===';
PRINT '';

-- Check blind_scannings table
PRINT '1. Checking blind_scannings table...';
IF EXISTS (SELECT * FROM sysobjects WHERE name='blind_scannings' AND xtype='U')
BEGIN
    DECLARE @blind_columns INT;
    SELECT @blind_columns = COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'blind_scannings';
    PRINT '✓ blind_scannings table exists with ' + CAST(@blind_columns AS VARCHAR(10)) + ' columns';
    
    -- Show structure
    SELECT 'blind_scannings structure' as Info, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'blind_scannings'
    ORDER BY ORDINAL_POSITION;
END
ELSE
BEGIN
    PRINT '✗ blind_scannings table missing';
END

PRINT '';

-- Check pagetypings QC columns
PRINT '2. Checking pagetypings QC columns...';
DECLARE @qc_columns_count INT;
SELECT @qc_columns_count = COUNT(*) 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'pagetypings' 
AND COLUMN_NAME IN ('qc_status', 'qc_reviewed_by', 'qc_reviewed_at', 'qc_overridden', 'qc_override_note', 'has_qc_issues');

PRINT 'Found ' + CAST(@qc_columns_count AS VARCHAR(10)) + '/6 QC columns in pagetypings table';

IF @qc_columns_count = 6
BEGIN
    PRINT '✓ All QC columns added to pagetypings table';
END
ELSE
BEGIN
    PRINT '✗ Missing QC columns in pagetypings table';
END

-- Show QC columns that exist
SELECT 'pagetypings QC columns' as Info, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'pagetypings' 
AND COLUMN_NAME IN ('qc_status', 'qc_reviewed_by', 'qc_reviewed_at', 'qc_overridden', 'qc_override_note', 'has_qc_issues')
ORDER BY COLUMN_NAME;

PRINT '';

-- Check file_indexings workflow columns
PRINT '3. Checking file_indexings workflow columns...';
DECLARE @workflow_columns_count INT;
SELECT @workflow_columns_count = COUNT(*) 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'file_indexings' 
AND COLUMN_NAME IN ('is_updated', 'batch_id', 'has_qc_issues', 'workflow_status', 'archived_at');

PRINT 'Found ' + CAST(@workflow_columns_count AS VARCHAR(10)) + '/5 workflow columns in file_indexings table';

IF @workflow_columns_count = 5
BEGIN
    PRINT '✓ All workflow columns added to file_indexings table';
END
ELSE
BEGIN
    PRINT '✗ Missing workflow columns in file_indexings table';
END

-- Show workflow columns that exist
SELECT 'file_indexings workflow columns' as Info, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'file_indexings' 
AND COLUMN_NAME IN ('is_updated', 'batch_id', 'has_qc_issues', 'workflow_status', 'archived_at')
ORDER BY COLUMN_NAME;

PRINT '';

-- Check indexes
PRINT '4. Checking indexes...';
SELECT 'Database Indexes' as Info, 
       OBJECT_NAME(object_id) as TableName,
       name as IndexName,
       type_desc as IndexType
FROM sys.indexes 
WHERE OBJECT_NAME(object_id) IN ('blind_scannings', 'pagetypings', 'file_indexings')
AND name LIKE 'IX_%'
ORDER BY OBJECT_NAME(object_id), name;

PRINT '';

-- Check constraints
PRINT '5. Checking constraints...';
SELECT 'Database Constraints' as Info,
       OBJECT_NAME(parent_object_id) as TableName,
       name as ConstraintName,
       type_desc as ConstraintType
FROM sys.objects 
WHERE type_desc IN ('DEFAULT_CONSTRAINT', 'CHECK_CONSTRAINT', 'UNIQUE_CONSTRAINT')
AND OBJECT_NAME(parent_object_id) IN ('blind_scannings', 'pagetypings', 'file_indexings')
ORDER BY OBJECT_NAME(parent_object_id), name;

PRINT '';

-- Sample data check
PRINT '6. Checking sample data...';

-- Count records in each table
DECLARE @file_indexings_count INT, @pagetypings_count INT, @scannings_count INT, @blind_scannings_count INT;

SELECT @file_indexings_count = COUNT(*) FROM file_indexings;
SELECT @pagetypings_count = COUNT(*) FROM pagetypings;
SELECT @scannings_count = COUNT(*) FROM scannings;
SELECT @blind_scannings_count = COUNT(*) FROM blind_scannings;

PRINT 'Record counts:';
PRINT '- file_indexings: ' + CAST(@file_indexings_count AS VARCHAR(10));
PRINT '- pagetypings: ' + CAST(@pagetypings_count AS VARCHAR(10));
PRINT '- scannings: ' + CAST(@scannings_count AS VARCHAR(10));
PRINT '- blind_scannings: ' + CAST(@blind_scannings_count AS VARCHAR(10));

-- Check workflow status distribution
IF @file_indexings_count > 0
BEGIN
    PRINT '';
    PRINT 'Workflow status distribution:';
    SELECT workflow_status, COUNT(*) as count
    FROM file_indexings 
    GROUP BY workflow_status
    ORDER BY workflow_status;
END

-- Check QC status distribution
IF @pagetypings_count > 0
BEGIN
    PRINT '';
    PRINT 'QC status distribution:';
    SELECT qc_status, COUNT(*) as count
    FROM pagetypings 
    GROUP BY qc_status
    ORDER BY qc_status;
END

PRINT '';
PRINT '=== VERIFICATION COMPLETED ===';
PRINT '';

-- Final summary
IF @qc_columns_count = 6 AND @workflow_columns_count = 5 AND EXISTS (SELECT * FROM sysobjects WHERE name='blind_scannings' AND xtype='U')
BEGIN
    PRINT '🎉 SUCCESS: All database updates completed successfully!';
    PRINT '';
    PRINT 'You can now use the new features:';
    PRINT '✓ Blind Scanning: /blind-scanning';
    PRINT '✓ PTQ Control: /ptq-control';
    PRINT '';
    PRINT 'Next steps:';
    PRINT '1. Add navigation menu items for the new features';
    PRINT '2. Test the application functionality';
    PRINT '3. Train users on the new workflow';
END
ELSE
BEGIN
    PRINT '⚠ WARNING: Some database updates may be incomplete.';
    PRINT 'Please review the results above and run any missing updates manually.';
END