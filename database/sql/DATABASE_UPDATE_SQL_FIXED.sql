-- =====================================================
-- EDMS Blind Scanning & PTQ Control Database Updates - FIXED VERSION
-- Execute these SQL statements in your SQL Server database
-- =====================================================

-- 1. CREATE BLIND_SCANNINGS TABLE
-- =====================================================
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='blind_scannings' AND xtype='U')
BEGIN
    CREATE TABLE blind_scannings (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        temp_file_id NVARCHAR(255) NOT NULL,
        original_filename NVARCHAR(255) NOT NULL,
        document_path NVARCHAR(500) NOT NULL,
        paper_size NVARCHAR(50) NULL,
        document_type NVARCHAR(100) NULL,
        notes NVARCHAR(MAX) NULL,
        status NVARCHAR(50) NOT NULL DEFAULT 'pending',
        uploaded_by BIGINT NOT NULL,
        file_indexing_id BIGINT NULL,
        converted_at DATETIME2 NULL,
        created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME2 NOT NULL DEFAULT GETDATE()
    );
    
    -- Create unique constraint on temp_file_id
    ALTER TABLE blind_scannings ADD CONSTRAINT UQ_blind_scannings_temp_file_id UNIQUE (temp_file_id);
    
    -- Create indexes for better performance
    CREATE INDEX IX_blind_scannings_status_uploaded_by ON blind_scannings (status, uploaded_by);
    CREATE INDEX IX_blind_scannings_temp_file_id ON blind_scannings (temp_file_id);
    CREATE INDEX IX_blind_scannings_created_at ON blind_scannings (created_at);
    
    PRINT 'Created blind_scannings table successfully';
END
ELSE
BEGIN
    PRINT 'blind_scannings table already exists';
END

-- 2. ADD QC FIELDS TO PAGETYPINGS TABLE
-- =====================================================
PRINT 'Adding QC fields to pagetypings table...';

-- Check and add qc_status column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_status')
BEGIN
    ALTER TABLE pagetypings ADD qc_status NVARCHAR(50) NULL;
    PRINT 'Added qc_status column to pagetypings table';
END
ELSE
BEGIN
    PRINT 'qc_status column already exists in pagetypings table';
END

-- Check and add qc_reviewed_by column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_reviewed_by')
BEGIN
    ALTER TABLE pagetypings ADD qc_reviewed_by BIGINT NULL;
    PRINT 'Added qc_reviewed_by column to pagetypings table';
END
ELSE
BEGIN
    PRINT 'qc_reviewed_by column already exists in pagetypings table';
END

-- Check and add qc_reviewed_at column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_reviewed_at')
BEGIN
    ALTER TABLE pagetypings ADD qc_reviewed_at DATETIME2 NULL;
    PRINT 'Added qc_reviewed_at column to pagetypings table';
END
ELSE
BEGIN
    PRINT 'qc_reviewed_at column already exists in pagetypings table';
END

-- Check and add qc_overridden column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_overridden')
BEGIN
    ALTER TABLE pagetypings ADD qc_overridden BIT NULL;
    PRINT 'Added qc_overridden column to pagetypings table';
END
ELSE
BEGIN
    PRINT 'qc_overridden column already exists in pagetypings table';
END

-- Check and add qc_override_note column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'qc_override_note' AND COLUMN_NAME = 'qc_override_note')
BEGIN
    ALTER TABLE pagetypings ADD qc_override_note NVARCHAR(MAX) NULL;
    PRINT 'Added qc_override_note column to pagetypings table';
END
ELSE
BEGIN
    PRINT 'qc_override_note column already exists in pagetypings table';
END

-- Check and add has_qc_issues column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'has_qc_issues')
BEGIN
    ALTER TABLE pagetypings ADD has_qc_issues BIT NULL;
    PRINT 'Added has_qc_issues column to pagetypings table';
END
ELSE
BEGIN
    PRINT 'has_qc_issues column already exists in pagetypings table';
END

-- 3. ADD WORKFLOW FIELDS TO FILE_INDEXINGS TABLE
-- =====================================================
PRINT 'Adding workflow fields to file_indexings table...';

-- Check and add is_updated column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'is_updated')
BEGIN
    ALTER TABLE file_indexings ADD is_updated BIT NULL;
    PRINT 'Added is_updated column to file_indexings table';
END
ELSE
BEGIN
    PRINT 'is_updated column already exists in file_indexings table';
END

-- Check and add batch_id column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'batch_id')
BEGIN
    ALTER TABLE file_indexings ADD batch_id NVARCHAR(255) NULL;
    PRINT 'Added batch_id column to file_indexings table';
END
ELSE
BEGIN
    PRINT 'batch_id column already exists in file_indexings table';
END

-- Check and add has_qc_issues column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'has_qc_issues')
BEGIN
    ALTER TABLE file_indexings ADD has_qc_issues BIT NULL;
    PRINT 'Added has_qc_issues column to file_indexings table';
END
ELSE
BEGIN
    PRINT 'has_qc_issues column already exists in file_indexings table';
END

-- Check and add workflow_status column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'workflow_status')
BEGIN
    ALTER TABLE file_indexings ADD workflow_status NVARCHAR(50) NULL;
    PRINT 'Added workflow_status column to file_indexings table';
END
ELSE
BEGIN
    PRINT 'workflow_status column already exists in file_indexings table';
END

-- Check and add archived_at column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'archived_at')
BEGIN
    ALTER TABLE file_indexings ADD archived_at DATETIME2 NULL;
    PRINT 'Added archived_at column to file_indexings table';
END
ELSE
BEGIN
    PRINT 'archived_at column already exists in file_indexings table';
END

-- 4. UPDATE EXISTING DATA AND SET DEFAULTS
-- =====================================================
PRINT 'Setting default values for new columns...';

-- Set default QC status for existing page typings (only if column exists and is null)
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_status')
BEGIN
    UPDATE pagetypings 
    SET qc_status = 'pending' 
    WHERE qc_status IS NULL;
    PRINT 'Set default qc_status values';
END

-- Set default qc_overridden values
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_overridden')
BEGIN
    UPDATE pagetypings 
    SET qc_overridden = 0 
    WHERE qc_overridden IS NULL;
    PRINT 'Set default qc_overridden values';
END

-- Set default has_qc_issues values for pagetypings
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'has_qc_issues')
BEGIN
    UPDATE pagetypings 
    SET has_qc_issues = 0 
    WHERE has_qc_issues IS NULL;
    PRINT 'Set default has_qc_issues values for pagetypings';
END

-- Set default is_updated values
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'is_updated')
BEGIN
    UPDATE file_indexings 
    SET is_updated = 0 
    WHERE is_updated IS NULL;
    PRINT 'Set default is_updated values';
END

-- Set default has_qc_issues values for file_indexings
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'has_qc_issues')
BEGIN
    UPDATE file_indexings 
    SET has_qc_issues = 0 
    WHERE has_qc_issues IS NULL;
    PRINT 'Set default has_qc_issues values for file_indexings';
END

-- Set default workflow status for existing file indexings
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'workflow_status')
BEGIN
    UPDATE file_indexings 
    SET workflow_status = CASE 
        WHEN EXISTS (SELECT 1 FROM pagetypings p WHERE p.file_indexing_id = file_indexings.id) THEN 'pagetyped'
        WHEN EXISTS (SELECT 1 FROM scannings s WHERE s.file_indexing_id = file_indexings.id) THEN 'uploaded'
        ELSE 'indexed'
    END
    WHERE workflow_status IS NULL;
    PRINT 'Set default workflow_status values';
END

-- 5. ALTER COLUMNS TO ADD NOT NULL CONSTRAINTS AND DEFAULTS
-- =====================================================
PRINT 'Adding NOT NULL constraints and defaults...';

-- Update pagetypings columns to NOT NULL with defaults
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_status')
BEGIN
    ALTER TABLE pagetypings ALTER COLUMN qc_status NVARCHAR(50) NOT NULL;
    ALTER TABLE pagetypings ADD CONSTRAINT DF_pagetypings_qc_status DEFAULT 'pending' FOR qc_status;
    PRINT 'Added NOT NULL constraint and default for qc_status';
END

IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_overridden')
BEGIN
    ALTER TABLE pagetypings ALTER COLUMN qc_overridden BIT NOT NULL;
    ALTER TABLE pagetypings ADD CONSTRAINT DF_pagetypings_qc_overridden DEFAULT 0 FOR qc_overridden;
    PRINT 'Added NOT NULL constraint and default for qc_overridden';
END

IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'has_qc_issues')
BEGIN
    ALTER TABLE pagetypings ALTER COLUMN has_qc_issues BIT NOT NULL;
    ALTER TABLE pagetypings ADD CONSTRAINT DF_pagetypings_has_qc_issues DEFAULT 0 FOR has_qc_issues;
    PRINT 'Added NOT NULL constraint and default for has_qc_issues';
END

-- Update file_indexings columns to NOT NULL with defaults
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'is_updated')
BEGIN
    ALTER TABLE file_indexings ALTER COLUMN is_updated BIT NOT NULL;
    ALTER TABLE file_indexings ADD CONSTRAINT DF_file_indexings_is_updated DEFAULT 0 FOR is_updated;
    PRINT 'Added NOT NULL constraint and default for is_updated';
END

IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'has_qc_issues')
BEGIN
    ALTER TABLE file_indexings ALTER COLUMN has_qc_issues BIT NOT NULL;
    ALTER TABLE file_indexings ADD CONSTRAINT DF_file_indexings_has_qc_issues DEFAULT 0 FOR has_qc_issues;
    PRINT 'Added NOT NULL constraint and default for has_qc_issues';
END

IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'workflow_status')
BEGIN
    ALTER TABLE file_indexings ALTER COLUMN workflow_status NVARCHAR(50) NOT NULL;
    ALTER TABLE file_indexings ADD CONSTRAINT DF_file_indexings_workflow_status DEFAULT 'indexed' FOR workflow_status;
    PRINT 'Added NOT NULL constraint and default for workflow_status';
END

-- 6. CREATE INDEXES
-- =====================================================
PRINT 'Creating indexes...';

-- Create indexes for QC fields in pagetypings
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pagetypings_qc_status' AND object_id = OBJECT_ID('pagetypings'))
BEGIN
    CREATE INDEX IX_pagetypings_qc_status ON pagetypings (qc_status);
    PRINT 'Created index IX_pagetypings_qc_status';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pagetypings_qc_reviewed_at' AND object_id = OBJECT_ID('pagetypings'))
BEGIN
    CREATE INDEX IX_pagetypings_qc_reviewed_at ON pagetypings (qc_reviewed_at);
    PRINT 'Created index IX_pagetypings_qc_reviewed_at';
END

-- Create indexes for workflow fields in file_indexings
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_indexings_workflow_status' AND object_id = OBJECT_ID('file_indexings'))
BEGIN
    CREATE INDEX IX_file_indexings_workflow_status ON file_indexings (workflow_status);
    PRINT 'Created index IX_file_indexings_workflow_status';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_indexings_batch_id' AND object_id = OBJECT_ID('file_indexings'))
BEGIN
    CREATE INDEX IX_file_indexings_batch_id ON file_indexings (batch_id);
    PRINT 'Created index IX_file_indexings_batch_id';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_indexings_has_qc_issues' AND object_id = OBJECT_ID('file_indexings'))
BEGIN
    CREATE INDEX IX_file_indexings_has_qc_issues ON file_indexings (has_qc_issues);
    PRINT 'Created index IX_file_indexings_has_qc_issues';
END

-- 7. CREATE CONSTRAINTS (OPTIONAL - SKIP IF CAUSES ISSUES)
-- =====================================================
PRINT 'Creating check constraints...';

-- Add check constraints for valid status values (skip if they cause issues)
BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.check_constraints WHERE name = 'CK_blind_scannings_status')
    BEGIN
        ALTER TABLE blind_scannings 
        ADD CONSTRAINT CK_blind_scannings_status 
        CHECK (status IN ('pending', 'converted', 'archived'));
        PRINT 'Added check constraint for blind_scannings status';
    END
END TRY
BEGIN CATCH
    PRINT 'Skipped check constraint for blind_scannings status (may cause issues with existing data)';
END CATCH

BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.check_constraints WHERE name = 'CK_pagetypings_qc_status')
    BEGIN
        ALTER TABLE pagetypings 
        ADD CONSTRAINT CK_pagetypings_qc_status 
        CHECK (qc_status IN ('pending', 'passed', 'failed'));
        PRINT 'Added check constraint for pagetypings qc_status';
    END
END TRY
BEGIN CATCH
    PRINT 'Skipped check constraint for pagetypings qc_status (may cause issues with existing data)';
END CATCH

BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.check_constraints WHERE name = 'CK_file_indexings_workflow_status')
    BEGIN
        ALTER TABLE file_indexings 
        ADD CONSTRAINT CK_file_indexings_workflow_status 
        CHECK (workflow_status IN ('indexed', 'uploaded', 'pagetyped', 'qc_passed', 'archived'));
        PRINT 'Added check constraint for file_indexings workflow_status';
    END
END TRY
BEGIN CATCH
    PRINT 'Skipped check constraint for file_indexings workflow_status (may cause issues with existing data)';
END CATCH

-- 8. VERIFICATION QUERIES
-- =====================================================
PRINT '=== VERIFICATION RESULTS ===';

-- Check blind_scannings table
IF EXISTS (SELECT * FROM sysobjects WHERE name='blind_scannings' AND xtype='U')
BEGIN
    PRINT '✓ blind_scannings table exists';
    DECLARE @blind_columns INT;
    SELECT @blind_columns = COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'blind_scannings';
    PRINT 'blind_scannings has ' + CAST(@blind_columns AS VARCHAR(10)) + ' columns';
END
ELSE
BEGIN
    PRINT '✗ blind_scannings table missing';
END

-- Check pagetypings QC columns
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
    -- Show which columns exist
    SELECT 'Missing QC columns' as Status, COLUMN_NAME, DATA_TYPE, IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'pagetypings' 
    AND COLUMN_NAME IN ('qc_status', 'qc_reviewed_by', 'qc_reviewed_at', 'qc_overridden', 'qc_override_note', 'has_qc_issues');
END

-- Check file_indexings workflow columns
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
    -- Show which columns exist
    SELECT 'Missing workflow columns' as Status, COLUMN_NAME, DATA_TYPE, IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'file_indexings' 
    AND COLUMN_NAME IN ('is_updated', 'batch_id', 'has_qc_issues', 'workflow_status', 'archived_at');
END

-- Show final table structures
PRINT '=== FINAL TABLE STRUCTURES ===';

-- Show blind_scannings structure
SELECT 'blind_scannings' as TableName, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'blind_scannings'
ORDER BY ORDINAL_POSITION;

-- Show pagetypings QC columns
SELECT 'pagetypings_QC' as TableName, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'pagetypings' 
AND COLUMN_NAME IN ('qc_status', 'qc_reviewed_by', 'qc_reviewed_at', 'qc_overridden', 'qc_override_note', 'has_qc_issues')
ORDER BY ORDINAL_POSITION;

-- Show file_indexings workflow columns
SELECT 'file_indexings_workflow' as TableName, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'file_indexings' 
AND COLUMN_NAME IN ('is_updated', 'batch_id', 'has_qc_issues', 'workflow_status', 'archived_at')
ORDER BY ORDINAL_POSITION;

PRINT '=== DATABASE UPDATE COMPLETED ===';
PRINT 'You can now use the Blind Scanning and PTQ Control features.';
PRINT 'Access URLs:';
PRINT '- Blind Scanning: /blind-scanning';
PRINT '- PTQ Control: /ptq-control';