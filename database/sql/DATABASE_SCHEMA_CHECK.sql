-- EDMS Database Schema Check and Update Script
-- Run this script to ensure all required tables and columns exist

-- =============================================================================
-- 1. CHECK AND UPDATE file_indexings TABLE
-- =============================================================================

-- Check if is_updated column exists, if not add it
IF COL_LENGTH('file_indexings','is_updated') IS NULL
BEGIN
    ALTER TABLE file_indexings ADD is_updated BIT NOT NULL CONSTRAINT DF_file_indexings_is_updated DEFAULT(0);
    PRINT 'Added is_updated column to file_indexings table';
END
ELSE
    PRINT 'is_updated column already exists in file_indexings table';

-- Check if batch_id column exists, if not add it
IF COL_LENGTH('file_indexings','batch_id') IS NULL
BEGIN
    ALTER TABLE file_indexings ADD batch_id BIGINT NULL;
    PRINT 'Added batch_id column to file_indexings table';
END
ELSE
    PRINT 'batch_id column already exists in file_indexings table';

-- Check if has_qc_issues column exists, if not add it
IF COL_LENGTH('file_indexings','has_qc_issues') IS NULL
BEGIN
    ALTER TABLE file_indexings ADD has_qc_issues BIT NOT NULL CONSTRAINT DF_file_indexings_has_qc_issues DEFAULT(0);
    PRINT 'Added has_qc_issues column to file_indexings table';
END
ELSE
    PRINT 'has_qc_issues column already exists in file_indexings table';

-- Check if workflow_status column exists, if not add it
IF COL_LENGTH('file_indexings','workflow_status') IS NULL
BEGIN
    ALTER TABLE file_indexings ADD workflow_status NVARCHAR(50) NOT NULL CONSTRAINT DF_file_indexings_workflow_status DEFAULT('indexed');
    PRINT 'Added workflow_status column to file_indexings table';
END
ELSE
    PRINT 'workflow_status column already exists in file_indexings table';

-- Check if archived_at column exists, if not add it
IF COL_LENGTH('file_indexings','archived_at') IS NULL
BEGIN
    ALTER TABLE file_indexings ADD archived_at DATETIME2 NULL;
    PRINT 'Added archived_at column to file_indexings table';
END
ELSE
    PRINT 'archived_at column already exists in file_indexings table';

-- =============================================================================
-- 2. CHECK AND UPDATE file_trackings TABLE
-- =============================================================================

-- Check if file_indexing_id column exists, if not add it
IF COL_LENGTH('file_trackings','file_indexing_id') IS NULL
BEGIN
    ALTER TABLE file_trackings ADD file_indexing_id BIGINT NULL;
    PRINT 'Added file_indexing_id column to file_trackings table';
END
ELSE
    PRINT 'file_indexing_id column already exists in file_trackings table';

-- =============================================================================
-- 3. CHECK AND CREATE pagetypings TABLE (if missing)
-- =============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'pagetypings')
BEGIN
    CREATE TABLE pagetypings (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        file_indexing_id BIGINT NOT NULL,
        scanning_id BIGINT NULL,
        page_number INT NOT NULL,
        page_type NVARCHAR(100) NOT NULL,
        page_subtype NVARCHAR(100) NULL,
        serial_number NVARCHAR(10) NOT NULL,
        page_code NVARCHAR(100) NULL,
        file_path NVARCHAR(255) NOT NULL,
        typed_by BIGINT NULL,
        source NVARCHAR(50) NOT NULL CONSTRAINT DF_pagetypings_source DEFAULT('initial'),
        qc_status NVARCHAR(20) NOT NULL CONSTRAINT DF_pagetypings_qc_status DEFAULT('pending'),
        qc_reviewed_by BIGINT NULL,
        qc_reviewed_at DATETIME2 NULL,
        qc_overridden BIT NOT NULL CONSTRAINT DF_pagetypings_qc_overridden DEFAULT(0),
        qc_override_note NVARCHAR(1000) NULL,
        has_qc_issues BIT NOT NULL CONSTRAINT DF_pagetypings_has_qc_issues DEFAULT(0),
        created_at DATETIME2 NOT NULL CONSTRAINT DF_pagetypings_created_at DEFAULT (GETDATE()),
        updated_at DATETIME2 NOT NULL CONSTRAINT DF_pagetypings_updated_at DEFAULT (GETDATE()),
        deleted_at DATETIME2 NULL
    );
    
    CREATE INDEX IX_pagetypings_file_idx ON pagetypings(file_indexing_id);
    CREATE INDEX IX_pagetypings_scanning_id ON pagetypings(scanning_id);
    CREATE INDEX IX_pagetypings_qc_status ON pagetypings(qc_status);
    
    PRINT 'Created pagetypings table with all required columns and indexes';
END
ELSE
BEGIN
    PRINT 'pagetypings table already exists';
    
    -- Check and add missing columns to existing pagetypings table
    IF COL_LENGTH('pagetypings','qc_status') IS NULL
    BEGIN
        ALTER TABLE pagetypings ADD qc_status NVARCHAR(20) NOT NULL CONSTRAINT DF_pagetypings_qc_status DEFAULT('pending');
        PRINT 'Added qc_status column to pagetypings table';
    END
    
    IF COL_LENGTH('pagetypings','qc_reviewed_by') IS NULL
    BEGIN
        ALTER TABLE pagetypings ADD qc_reviewed_by BIGINT NULL;
        PRINT 'Added qc_reviewed_by column to pagetypings table';
    END
    
    IF COL_LENGTH('pagetypings','qc_reviewed_at') IS NULL
    BEGIN
        ALTER TABLE pagetypings ADD qc_reviewed_at DATETIME2 NULL;
        PRINT 'Added qc_reviewed_at column to pagetypings table';
    END
    
    IF COL_LENGTH('pagetypings','qc_overridden') IS NULL
    BEGIN
        ALTER TABLE pagetypings ADD qc_overridden BIT NOT NULL CONSTRAINT DF_pagetypings_qc_overridden DEFAULT(0);
        PRINT 'Added qc_overridden column to pagetypings table';
    END
    
    IF COL_LENGTH('pagetypings','qc_override_note') IS NULL
    BEGIN
        ALTER TABLE pagetypings ADD qc_override_note NVARCHAR(1000) NULL;
        PRINT 'Added qc_override_note column to pagetypings table';
    END
    
    IF COL_LENGTH('pagetypings','has_qc_issues') IS NULL
    BEGIN
        ALTER TABLE pagetypings ADD has_qc_issues BIT NOT NULL CONSTRAINT DF_pagetypings_has_qc_issues DEFAULT(0);
        PRINT 'Added has_qc_issues column to pagetypings table';
    END
    
    IF COL_LENGTH('pagetypings','deleted_at') IS NULL
    BEGIN
        ALTER TABLE pagetypings ADD deleted_at DATETIME2 NULL;
        PRINT 'Added deleted_at column to pagetypings table';
    END
END

-- =============================================================================
-- 4. CHECK AND CREATE property_records TABLE (if missing)
-- =============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'property_records')
BEGIN
    CREATE TABLE property_records (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        file_indexing_id BIGINT NOT NULL,
        instrument_type NVARCHAR(100) NOT NULL,
        reg_no NVARCHAR(100) NULL,
        reg_date DATE NULL,
        grantor NVARCHAR(255) NULL,
        grantee NVARCHAR(255) NULL,
        property_description NVARCHAR(MAX) NULL,
        consideration_amount DECIMAL(18,2) NULL,
        extras NVARCHAR(MAX) NULL,
        created_by BIGINT NULL,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_property_records_created_at DEFAULT (GETDATE()),
        updated_at DATETIME2 NOT NULL CONSTRAINT DF_property_records_updated_at DEFAULT (GETDATE())
    );
    
    CREATE INDEX IX_property_records_file_idx ON property_records(file_indexing_id);
    CREATE INDEX IX_property_records_reg_no ON property_records(reg_no);
    CREATE INDEX IX_property_records_instrument_type ON property_records(instrument_type);
    
    PRINT 'Created property_records table with all required columns and indexes';
END
ELSE
    PRINT 'property_records table already exists';

-- =============================================================================
-- 5. CHECK AND CREATE batches TABLE (if missing)
-- =============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'batches')
BEGIN
    CREATE TABLE batches (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        name NVARCHAR(150) NOT NULL,
        size INT NOT NULL CONSTRAINT DF_batches_size DEFAULT(0),
        max_size INT NOT NULL CONSTRAINT DF_batches_max_size DEFAULT(100),
        status NVARCHAR(20) NOT NULL CONSTRAINT DF_batches_status DEFAULT('active'),
        created_by BIGINT NULL,
        notes NVARCHAR(1000) NULL,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_batches_created_at DEFAULT (GETDATE()),
        updated_at DATETIME2 NOT NULL CONSTRAINT DF_batches_updated_at DEFAULT (GETDATE())
    );
    
    CREATE INDEX IX_batches_status ON batches(status);
    CREATE INDEX IX_batches_created_by ON batches(created_by);
    
    PRINT 'Created batches table with all required columns and indexes';
END
ELSE
    PRINT 'batches table already exists';

-- =============================================================================
-- 6. CHECK AND CREATE barcodes TABLE (if missing)
-- =============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'barcodes')
BEGIN
    CREATE TABLE barcodes (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        file_indexing_id BIGINT NOT NULL,
        barcode_value NVARCHAR(150) NOT NULL,
        qr_payload NVARCHAR(MAX) NULL,
        batch_id BIGINT NULL,
        printed_at DATETIME2 NULL,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_barcodes_created_at DEFAULT (GETDATE()),
        updated_at DATETIME2 NOT NULL CONSTRAINT DF_barcodes_updated_at DEFAULT (GETDATE())
    );
    
    CREATE UNIQUE INDEX UX_barcodes_barcode_value ON barcodes(barcode_value);
    CREATE INDEX IX_barcodes_file_idx ON barcodes(file_indexing_id);
    CREATE INDEX IX_barcodes_batch_id ON barcodes(batch_id);
    
    PRINT 'Created barcodes table with all required columns and indexes';
END
ELSE
    PRINT 'barcodes table already exists';

-- =============================================================================
-- 7. CHECK AND CREATE blind_scannings TABLE (if missing)
-- =============================================================================

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'blind_scannings')
BEGIN
    CREATE TABLE blind_scannings (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        temp_file_id NVARCHAR(100) NOT NULL,
        original_filename NVARCHAR(255) NOT NULL,
        file_path NVARCHAR(500) NOT NULL,
        file_size BIGINT NOT NULL,
        mime_type NVARCHAR(100) NOT NULL,
        document_type NVARCHAR(100) NULL,
        paper_size NVARCHAR(50) NULL,
        notes NVARCHAR(1000) NULL,
        status NVARCHAR(20) NOT NULL CONSTRAINT DF_blind_scannings_status DEFAULT('pending'),
        uploaded_by BIGINT NULL,
        converted_to_file_indexing_id BIGINT NULL,
        converted_at DATETIME2 NULL,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_blind_scannings_created_at DEFAULT (GETDATE()),
        updated_at DATETIME2 NOT NULL CONSTRAINT DF_blind_scannings_updated_at DEFAULT (GETDATE())
    );
    
    CREATE UNIQUE INDEX UX_blind_scannings_temp_file_id ON blind_scannings(temp_file_id);
    CREATE INDEX IX_blind_scannings_status ON blind_scannings(status);
    CREATE INDEX IX_blind_scannings_uploaded_by ON blind_scannings(uploaded_by);
    CREATE INDEX IX_blind_scannings_converted_to ON blind_scannings(converted_to_file_indexing_id);
    
    PRINT 'Created blind_scannings table with all required columns and indexes';
END
ELSE
    PRINT 'blind_scannings table already exists';

-- =============================================================================
-- 8. ADD FOREIGN KEY CONSTRAINTS (if they don't exist)
-- =============================================================================

-- Foreign key from file_indexings to batches
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_file_indexings_batches')
BEGIN
    IF COL_LENGTH('file_indexings','batch_id') IS NOT NULL AND EXISTS (SELECT 1 FROM sys.tables t WHERE t.name = 'batches')
    BEGIN
        ALTER TABLE file_indexings
        ADD CONSTRAINT FK_file_indexings_batches FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL;
        PRINT 'Added foreign key constraint FK_file_indexings_batches';
    END
END

-- Foreign key from file_trackings to file_indexings
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_file_trackings_file_indexings')
BEGIN
    IF COL_LENGTH('file_trackings','file_indexing_id') IS NOT NULL AND EXISTS (SELECT 1 FROM sys.tables t WHERE t.name = 'file_indexings')
    BEGIN
        ALTER TABLE file_trackings
        ADD CONSTRAINT FK_file_trackings_file_indexings FOREIGN KEY (file_indexing_id) REFERENCES file_indexings(id) ON DELETE CASCADE;
        PRINT 'Added foreign key constraint FK_file_trackings_file_indexings';
    END
END

-- Foreign key from pagetypings to file_indexings
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_pagetypings_file_indexings')
BEGIN
    IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'pagetypings') AND EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_indexings')
    BEGIN
        ALTER TABLE pagetypings
        ADD CONSTRAINT FK_pagetypings_file_indexings FOREIGN KEY (file_indexing_id) REFERENCES file_indexings(id) ON DELETE CASCADE;
        PRINT 'Added foreign key constraint FK_pagetypings_file_indexings';
    END
END

-- Foreign key from property_records to file_indexings
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_property_records_file_indexings')
BEGIN
    IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'property_records') AND EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_indexings')
    BEGIN
        ALTER TABLE property_records
        ADD CONSTRAINT FK_property_records_file_indexings FOREIGN KEY (file_indexing_id) REFERENCES file_indexings(id) ON DELETE CASCADE;
        PRINT 'Added foreign key constraint FK_property_records_file_indexings';
    END
END

-- Foreign key from barcodes to file_indexings
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_barcodes_file_indexings')
BEGIN
    IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'barcodes') AND EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_indexings')
    BEGIN
        ALTER TABLE barcodes
        ADD CONSTRAINT FK_barcodes_file_indexings FOREIGN KEY (file_indexing_id) REFERENCES file_indexings(id) ON DELETE CASCADE;
        PRINT 'Added foreign key constraint FK_barcodes_file_indexings';
    END
END

-- Foreign key from barcodes to batches
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_barcodes_batches')
BEGIN
    IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'barcodes') AND EXISTS (SELECT 1 FROM sys.tables WHERE name = 'batches')
    BEGIN
        ALTER TABLE barcodes
        ADD CONSTRAINT FK_barcodes_batches FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL;
        PRINT 'Added foreign key constraint FK_barcodes_batches';
    END
END

-- Foreign key from blind_scannings to file_indexings (for converted scans)
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_blind_scannings_file_indexings')
BEGIN
    IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'blind_scannings') AND EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_indexings')
    BEGIN
        ALTER TABLE blind_scannings
        ADD CONSTRAINT FK_blind_scannings_file_indexings FOREIGN KEY (converted_to_file_indexing_id) REFERENCES file_indexings(id) ON DELETE SET NULL;
        PRINT 'Added foreign key constraint FK_blind_scannings_file_indexings';
    END
END

-- =============================================================================
-- 9. VERIFICATION QUERIES
-- =============================================================================

PRINT '';
PRINT '=============================================================================';
PRINT 'VERIFICATION - Current table structure:';
PRINT '=============================================================================';

-- Check file_indexings columns
PRINT 'file_indexings table columns:';
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'file_indexings' 
    AND COLUMN_NAME IN ('is_updated', 'batch_id', 'has_qc_issues', 'workflow_status', 'archived_at')
ORDER BY COLUMN_NAME;

-- Check pagetypings QC columns
PRINT '';
PRINT 'pagetypings QC-related columns:';
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'pagetypings' 
    AND COLUMN_NAME IN ('qc_status', 'qc_reviewed_by', 'qc_reviewed_at', 'qc_overridden', 'qc_override_note', 'has_qc_issues', 'deleted_at')
ORDER BY COLUMN_NAME;

-- Check if all required tables exist
PRINT '';
PRINT 'Required tables status:';
SELECT 
    'file_indexings' as table_name,
    CASE WHEN EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_indexings') THEN 'EXISTS' ELSE 'MISSING' END as status
UNION ALL
SELECT 
    'file_trackings',
    CASE WHEN EXISTS (SELECT 1 FROM sys.tables WHERE name = 'file_trackings') THEN 'EXISTS' ELSE 'MISSING' END
UNION ALL
SELECT 
    'pagetypings',
    CASE WHEN EXISTS (SELECT 1 FROM sys.tables WHERE name = 'pagetypings') THEN 'EXISTS' ELSE 'MISSING' END
UNION ALL
SELECT 
    'property_records',
    CASE WHEN EXISTS (SELECT 1 FROM sys.tables WHERE name = 'property_records') THEN 'EXISTS' ELSE 'MISSING' END
UNION ALL
SELECT 
    'batches',
    CASE WHEN EXISTS (SELECT 1 FROM sys.tables WHERE name = 'batches') THEN 'EXISTS' ELSE 'MISSING' END
UNION ALL
SELECT 
    'barcodes',
    CASE WHEN EXISTS (SELECT 1 FROM sys.tables WHERE name = 'barcodes') THEN 'EXISTS' ELSE 'MISSING' END
UNION ALL
SELECT 
    'blind_scannings',
    CASE WHEN EXISTS (SELECT 1 FROM sys.tables WHERE name = 'blind_scannings') THEN 'EXISTS' ELSE 'MISSING' END;

PRINT '';
PRINT '=============================================================================';
PRINT 'EDMS Database Schema Update Complete!';
PRINT '=============================================================================';