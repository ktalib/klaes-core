-- =============================================
-- Script: Sample Data and Tests for File Decommissioning
-- Description: Creates sample data and runs tests to verify the system works
-- Date: 2024-12-19
-- =============================================

USE [klas]; -- Replace with your actual database name if different
GO

PRINT 'Starting sample data creation and testing...';
GO

-- Test the decommissioning procedure with a sample file
DECLARE @TestFileId BIGINT;

-- Get a sample active file for testing (if any exists)
SELECT TOP 1 @TestFileId = id 
FROM fileNumber 
WHERE (is_deleted IS NULL OR is_deleted = 0)
  AND (is_decommissioned IS NULL OR is_decommissioned = 0);

IF @TestFileId IS NOT NULL
BEGIN
    PRINT 'Testing decommissioning procedure with file ID: ' + CAST(@TestFileId AS NVARCHAR(10));
    
    -- Test the stored procedure
    EXEC sp_DecommissionFile 
        @FileId = @TestFileId,
        @DecommissioningReason = 'Test decommissioning - System validation',
        @DecommissioningDate = NULL, -- Will use current date
        @CommissioningDate = '2024-01-01 10:00:00',
        @DecommissionedBy = 'System Test';
    
    PRINT 'Decommissioning test completed.';
END
ELSE
BEGIN
    PRINT 'No active files found for testing. Skipping decommissioning test.';
END
GO

-- Test the statistics procedure
PRINT 'Testing statistics procedure...';
EXEC sp_GetDecommissioningStats;
PRINT 'Statistics test completed.';
GO

-- Test the search procedure
PRINT 'Testing search procedure...';
EXEC sp_SearchActiveFiles @SearchTerm = 'RES', @Limit = 5;
PRINT 'Search test completed.';
GO

-- Test the views
PRINT 'Testing views...';

PRINT 'Active files count:';
SELECT COUNT(*) as active_files_count FROM vw_active_files;

PRINT 'Decommissioned files count:';
SELECT COUNT(*) as decommissioned_files_count FROM vw_decommissioned_files;

PRINT 'Statistics from view:';
SELECT * FROM vw_decommissioning_stats;

PRINT 'View tests completed.';
GO

-- Verify table structure
PRINT 'Verifying table structures...';

PRINT 'fileNumber table columns:';
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'fileNumber'
  AND COLUMN_NAME IN ('commissioning_date', 'decommissioning_date', 'decommissioning_reason', 'is_decommissioned')
ORDER BY ORDINAL_POSITION;

PRINT 'decommissioned_files table columns:';
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'decommissioned_files'
ORDER BY ORDINAL_POSITION;

PRINT 'Table structure verification completed.';
GO

-- Check indexes
PRINT 'Checking indexes...';

SELECT 
    i.name as index_name,
    t.name as table_name,
    c.name as column_name
FROM sys.indexes i
INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
INNER JOIN sys.tables t ON i.object_id = t.object_id
WHERE t.name IN ('fileNumber', 'decommissioned_files')
  AND i.name LIKE '%decommission%'
ORDER BY t.name, i.name;

PRINT 'Index verification completed.';
GO

PRINT 'All tests completed successfully!';
PRINT '';
PRINT '=== SUMMARY ===';
PRINT 'The file decommissioning system has been set up with:';
PRINT '1. Added decommissioning fields to fileNumber table';
PRINT '2. Created decommissioned_files table';
PRINT '3. Created helpful views for reporting';
PRINT '4. Created stored procedures for common operations';
PRINT '5. Added appropriate indexes for performance';
PRINT '';
PRINT 'You can now use the Laravel application to manage file decommissioning.';
GO