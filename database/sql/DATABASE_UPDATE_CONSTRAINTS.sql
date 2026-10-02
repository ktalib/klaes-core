-- =====================================================
-- EDMS Database Updates - ADD CONSTRAINTS AND INDEXES
-- Run this AFTER DATABASE_UPDATE_DEFAULTS.sql
-- =====================================================

PRINT 'Adding constraints and indexes...';

-- STEP 5: ADD NOT NULL CONSTRAINTS AND DEFAULTS
-- =====================================================

PRINT 'Adding NOT NULL constraints...';

-- Update pagetypings columns to NOT NULL with defaults
BEGIN TRY
    ALTER TABLE pagetypings ALTER COLUMN qc_status NVARCHAR(50) NOT NULL;
    PRINT '✓ Added NOT NULL constraint for qc_status';
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add NOT NULL constraint for qc_status - may have NULL values';
END CATCH

BEGIN TRY
    ALTER TABLE pagetypings ALTER COLUMN qc_overridden BIT NOT NULL;
    PRINT '✓ Added NOT NULL constraint for qc_overridden';
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add NOT NULL constraint for qc_overridden - may have NULL values';
END CATCH

BEGIN TRY
    ALTER TABLE pagetypings ALTER COLUMN has_qc_issues BIT NOT NULL;
    PRINT '✓ Added NOT NULL constraint for has_qc_issues';
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add NOT NULL constraint for has_qc_issues - may have NULL values';
END CATCH

-- Update file_indexings columns to NOT NULL with defaults
BEGIN TRY
    ALTER TABLE file_indexings ALTER COLUMN is_updated BIT NOT NULL;
    PRINT '✓ Added NOT NULL constraint for is_updated';
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add NOT NULL constraint for is_updated - may have NULL values';
END CATCH

BEGIN TRY
    ALTER TABLE file_indexings ALTER COLUMN has_qc_issues BIT NOT NULL;
    PRINT '✓ Added NOT NULL constraint for has_qc_issues';
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add NOT NULL constraint for has_qc_issues - may have NULL values';
END CATCH

BEGIN TRY
    ALTER TABLE file_indexings ALTER COLUMN workflow_status NVARCHAR(50) NOT NULL;
    PRINT '✓ Added NOT NULL constraint for workflow_status';
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add NOT NULL constraint for workflow_status - may have NULL values';
END CATCH

-- STEP 6: ADD DEFAULT CONSTRAINTS
-- =====================================================

PRINT 'Adding default constraints...';

-- Add default constraints for pagetypings
BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.default_constraints WHERE name = 'DF_pagetypings_qc_status')
    BEGIN
        ALTER TABLE pagetypings ADD CONSTRAINT DF_pagetypings_qc_status DEFAULT 'pending' FOR qc_status;
        PRINT '✓ Added default constraint for qc_status';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add default constraint for qc_status';
END CATCH

BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.default_constraints WHERE name = 'DF_pagetypings_qc_overridden')
    BEGIN
        ALTER TABLE pagetypings ADD CONSTRAINT DF_pagetypings_qc_overridden DEFAULT 0 FOR qc_overridden;
        PRINT '✓ Added default constraint for qc_overridden';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add default constraint for qc_overridden';
END CATCH

BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.default_constraints WHERE name = 'DF_pagetypings_has_qc_issues')
    BEGIN
        ALTER TABLE pagetypings ADD CONSTRAINT DF_pagetypings_has_qc_issues DEFAULT 0 FOR has_qc_issues;
        PRINT '✓ Added default constraint for has_qc_issues';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add default constraint for has_qc_issues';
END CATCH

-- Add default constraints for file_indexings
BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.default_constraints WHERE name = 'DF_file_indexings_is_updated')
    BEGIN
        ALTER TABLE file_indexings ADD CONSTRAINT DF_file_indexings_is_updated DEFAULT 0 FOR is_updated;
        PRINT '✓ Added default constraint for is_updated';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add default constraint for is_updated';
END CATCH

BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.default_constraints WHERE name = 'DF_file_indexings_has_qc_issues')
    BEGIN
        ALTER TABLE file_indexings ADD CONSTRAINT DF_file_indexings_has_qc_issues DEFAULT 0 FOR has_qc_issues;
        PRINT '✓ Added default constraint for has_qc_issues';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add default constraint for has_qc_issues';
END CATCH

BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.default_constraints WHERE name = 'DF_file_indexings_workflow_status')
    BEGIN
        ALTER TABLE file_indexings ADD CONSTRAINT DF_file_indexings_workflow_status DEFAULT 'indexed' FOR workflow_status;
        PRINT '✓ Added default constraint for workflow_status';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not add default constraint for workflow_status';
END CATCH

-- STEP 7: CREATE INDEXES
-- =====================================================

PRINT 'Creating performance indexes...';

-- Create indexes for blind_scannings
BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_blind_scannings_status_uploaded_by' AND object_id = OBJECT_ID('blind_scannings'))
    BEGIN
        CREATE INDEX IX_blind_scannings_status_uploaded_by ON blind_scannings (status, uploaded_by);
        PRINT '✓ Created index IX_blind_scannings_status_uploaded_by';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not create index IX_blind_scannings_status_uploaded_by';
END CATCH

BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_blind_scannings_created_at' AND object_id = OBJECT_ID('blind_scannings'))
    BEGIN
        CREATE INDEX IX_blind_scannings_created_at ON blind_scannings (created_at);
        PRINT '✓ Created index IX_blind_scannings_created_at';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not create index IX_blind_scannings_created_at';
END CATCH

-- Create indexes for QC fields in pagetypings
BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pagetypings_qc_status' AND object_id = OBJECT_ID('pagetypings'))
    BEGIN
        CREATE INDEX IX_pagetypings_qc_status ON pagetypings (qc_status);
        PRINT '✓ Created index IX_pagetypings_qc_status';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not create index IX_pagetypings_qc_status';
END CATCH

BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pagetypings_qc_reviewed_at' AND object_id = OBJECT_ID('pagetypings'))
    BEGIN
        CREATE INDEX IX_pagetypings_qc_reviewed_at ON pagetypings (qc_reviewed_at);
        PRINT '✓ Created index IX_pagetypings_qc_reviewed_at';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not create index IX_pagetypings_qc_reviewed_at';
END CATCH

-- Create indexes for workflow fields in file_indexings
BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_indexings_workflow_status' AND object_id = OBJECT_ID('file_indexings'))
    BEGIN
        CREATE INDEX IX_file_indexings_workflow_status ON file_indexings (workflow_status);
        PRINT '✓ Created index IX_file_indexings_workflow_status';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not create index IX_file_indexings_workflow_status';
END CATCH

BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_indexings_batch_id' AND object_id = OBJECT_ID('file_indexings'))
    BEGIN
        CREATE INDEX IX_file_indexings_batch_id ON file_indexings (batch_id);
        PRINT '✓ Created index IX_file_indexings_batch_id';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not create index IX_file_indexings_batch_id';
END CATCH

BEGIN TRY
    IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_indexings_has_qc_issues' AND object_id = OBJECT_ID('file_indexings'))
    BEGIN
        CREATE INDEX IX_file_indexings_has_qc_issues ON file_indexings (has_qc_issues);
        PRINT '✓ Created index IX_file_indexings_has_qc_issues';
    END
END TRY
BEGIN CATCH
    PRINT '⚠ Could not create index IX_file_indexings_has_qc_issues';
END CATCH

PRINT '';
PRINT '=== NEXT: RUN THE VERIFICATION SCRIPT ===';
PRINT 'Now run DATABASE_VERIFY.sql to verify all changes';