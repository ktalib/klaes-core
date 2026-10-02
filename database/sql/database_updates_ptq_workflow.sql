-- PTQ Workflow Database Updates
-- This script adds the necessary columns for the Page Typing Quality Control (PTQ) workflow

-- Add workflow_status and has_qc_issues columns to file_indexings table
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'workflow_status')
BEGIN
    ALTER TABLE [dbo].[file_indexings] 
    ADD [workflow_status] NVARCHAR(50) DEFAULT 'indexed';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'has_qc_issues')
BEGIN
    ALTER TABLE [dbo].[file_indexings] 
    ADD [has_qc_issues] BIT DEFAULT 0;
END

-- Add QC-related columns to pagetypings table if they don't exist
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[pagetypings]') AND name = 'qc_status')
BEGIN
    ALTER TABLE [dbo].[pagetypings] 
    ADD [qc_status] NVARCHAR(20) DEFAULT 'pending';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[pagetypings]') AND name = 'qc_reviewed_by')
BEGIN
    ALTER TABLE [dbo].[pagetypings] 
    ADD [qc_reviewed_by] INT NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[pagetypings]') AND name = 'qc_reviewed_at')
BEGIN
    ALTER TABLE [dbo].[pagetypings] 
    ADD [qc_reviewed_at] DATETIME2 NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[pagetypings]') AND name = 'qc_overridden')
BEGIN
    ALTER TABLE [dbo].[pagetypings] 
    ADD [qc_overridden] BIT DEFAULT 0;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[pagetypings]') AND name = 'qc_override_note')
BEGIN
    ALTER TABLE [dbo].[pagetypings] 
    ADD [qc_override_note] NVARCHAR(1000) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[pagetypings]') AND name = 'has_qc_issues')
BEGIN
    ALTER TABLE [dbo].[pagetypings] 
    ADD [has_qc_issues] BIT DEFAULT 0;
END

-- Add foreign key constraint for qc_reviewed_by if it doesn't exist
IF NOT EXISTS (SELECT * FROM sys.foreign_keys WHERE name = 'FK_pagetypings_qc_reviewed_by')
BEGIN
    ALTER TABLE [dbo].[pagetypings]
    ADD CONSTRAINT FK_pagetypings_qc_reviewed_by 
    FOREIGN KEY ([qc_reviewed_by]) REFERENCES [dbo].[users]([id]);
END

-- Create indexes for better performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pagetypings_qc_status')
BEGIN
    CREATE INDEX IX_pagetypings_qc_status ON [dbo].[pagetypings] ([qc_status]);
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pagetypings_qc_reviewed_by')
BEGIN
    CREATE INDEX IX_pagetypings_qc_reviewed_by ON [dbo].[pagetypings] ([qc_reviewed_by]);
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pagetypings_qc_reviewed_at')
BEGIN
    CREATE INDEX IX_pagetypings_qc_reviewed_at ON [dbo].[pagetypings] ([qc_reviewed_at]);
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_indexings_workflow_status')
BEGIN
    CREATE INDEX IX_file_indexings_workflow_status ON [dbo].[file_indexings] ([workflow_status]);
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_file_indexings_has_qc_issues')
BEGIN
    CREATE INDEX IX_file_indexings_has_qc_issues ON [dbo].[file_indexings] ([has_qc_issues]);
END

-- Update existing records to set default workflow status based on current state
UPDATE [dbo].[file_indexings] 
SET [workflow_status] = CASE 
    WHEN EXISTS (
        SELECT 1 FROM [dbo].[pagetypings] p 
        WHERE p.file_indexing_id = [file_indexings].id 
        AND p.qc_status = 'passed'
        AND NOT EXISTS (
            SELECT 1 FROM [dbo].[pagetypings] p2 
            WHERE p2.file_indexing_id = [file_indexings].id 
            AND p2.qc_status IN ('pending', 'failed')
        )
    ) THEN 'qc_passed'
    WHEN EXISTS (
        SELECT 1 FROM [dbo].[pagetypings] p 
        WHERE p.file_indexing_id = [file_indexings].id
    ) THEN 'pagetyped'
    WHEN EXISTS (
        SELECT 1 FROM [dbo].[scannings] s 
        WHERE s.file_indexing_id = [file_indexings].id
    ) THEN 'scanned'
    ELSE 'indexed'
END
WHERE [workflow_status] = 'indexed' OR [workflow_status] IS NULL;

-- Update has_qc_issues flag based on existing QC data
UPDATE [dbo].[file_indexings] 
SET [has_qc_issues] = CASE 
    WHEN EXISTS (
        SELECT 1 FROM [dbo].[pagetypings] p 
        WHERE p.file_indexing_id = [file_indexings].id 
        AND (p.qc_status = 'failed' OR p.has_qc_issues = 1)
    ) THEN 1
    ELSE 0
END;

-- Add check constraints for valid values
IF NOT EXISTS (SELECT * FROM sys.check_constraints WHERE name = 'CK_pagetypings_qc_status')
BEGIN
    ALTER TABLE [dbo].[pagetypings]
    ADD CONSTRAINT CK_pagetypings_qc_status 
    CHECK ([qc_status] IN ('pending', 'passed', 'failed'));
END

IF NOT EXISTS (SELECT * FROM sys.check_constraints WHERE name = 'CK_file_indexings_workflow_status')
BEGIN
    ALTER TABLE [dbo].[file_indexings]
    ADD CONSTRAINT CK_file_indexings_workflow_status 
    CHECK ([workflow_status] IN ('indexed', 'scanned', 'pagetyped', 'qc_passed', 'archived'));
END

PRINT 'PTQ Workflow database updates completed successfully.';