-- =====================================================
-- EDMS Database Updates - SIMPLE STEP-BY-STEP VERSION
-- Execute these statements ONE BY ONE or in small batches
-- =====================================================

-- STEP 1: CREATE BLIND_SCANNINGS TABLE
-- =====================================================
PRINT 'STEP 1: Creating blind_scannings table...';

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
    
    ALTER TABLE blind_scannings ADD CONSTRAINT UQ_blind_scannings_temp_file_id UNIQUE (temp_file_id);
    
    PRINT '✓ Created blind_scannings table successfully';
END
ELSE
BEGIN
    PRINT '✓ blind_scannings table already exists';
END

-- STEP 2: ADD QC COLUMNS TO PAGETYPINGS TABLE
-- =====================================================
PRINT 'STEP 2: Adding QC columns to pagetypings table...';

-- Add qc_status column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_status')
BEGIN
    ALTER TABLE pagetypings ADD qc_status NVARCHAR(50) NULL;
    PRINT '✓ Added qc_status column';
END

-- Add qc_reviewed_by column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_reviewed_by')
BEGIN
    ALTER TABLE pagetypings ADD qc_reviewed_by BIGINT NULL;
    PRINT '✓ Added qc_reviewed_by column';
END

-- Add qc_reviewed_at column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_reviewed_at')
BEGIN
    ALTER TABLE pagetypings ADD qc_reviewed_at DATETIME2 NULL;
    PRINT '✓ Added qc_reviewed_at column';
END

-- Add qc_overridden column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_overridden')
BEGIN
    ALTER TABLE pagetypings ADD qc_overridden BIT NULL;
    PRINT '✓ Added qc_overridden column';
END

-- Add qc_override_note column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'qc_override_note')
BEGIN
    ALTER TABLE pagetypings ADD qc_override_note NVARCHAR(MAX) NULL;
    PRINT '✓ Added qc_override_note column';
END

-- Add has_qc_issues column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'has_qc_issues')
BEGIN
    ALTER TABLE pagetypings ADD has_qc_issues BIT NULL;
    PRINT '✓ Added has_qc_issues column';
END

-- STEP 3: ADD WORKFLOW COLUMNS TO FILE_INDEXINGS TABLE
-- =====================================================
PRINT 'STEP 3: Adding workflow columns to file_indexings table...';

-- Add is_updated column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'is_updated')
BEGIN
    ALTER TABLE file_indexings ADD is_updated BIT NULL;
    PRINT '✓ Added is_updated column';
END

-- Add batch_id column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'batch_id')
BEGIN
    ALTER TABLE file_indexings ADD batch_id NVARCHAR(255) NULL;
    PRINT '✓ Added batch_id column';
END

-- Add has_qc_issues column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'has_qc_issues')
BEGIN
    ALTER TABLE file_indexings ADD has_qc_issues BIT NULL;
    PRINT '✓ Added has_qc_issues column';
END

-- Add workflow_status column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'workflow_status')
BEGIN
    ALTER TABLE file_indexings ADD workflow_status NVARCHAR(50) NULL;
    PRINT '✓ Added workflow_status column';
END

-- Add archived_at column
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'file_indexings' AND COLUMN_NAME = 'archived_at')
BEGIN
    ALTER TABLE file_indexings ADD archived_at DATETIME2 NULL;
    PRINT '✓ Added archived_at column';
END

PRINT 'STEP 3 COMPLETED: All columns added successfully';
PRINT '';
PRINT '=== NEXT: RUN THE UPDATE SCRIPT ===';
PRINT 'Now run DATABASE_UPDATE_DEFAULTS.sql to set default values';