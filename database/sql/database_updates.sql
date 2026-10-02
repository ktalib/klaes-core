-- Database Updates for Dynamic EDMS Workflow
-- Execute these SQL statements to add missing fields to the database tables

-- Update file_indexings table
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'st_fillno')
BEGIN
    ALTER TABLE [dbo].[file_indexings] ADD [st_fillno] NVARCHAR(255) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'serial_no')
BEGIN
    ALTER TABLE [dbo].[file_indexings] ADD [serial_no] NVARCHAR(100) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'batch_no')
BEGIN
    ALTER TABLE [dbo].[file_indexings] ADD [batch_no] NVARCHAR(100) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'shelf_location')
BEGIN
    ALTER TABLE [dbo].[file_indexings] ADD [shelf_location] NVARCHAR(100) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'is_co_owned_plot')
BEGIN
    ALTER TABLE [dbo].[file_indexings] ADD [is_co_owned_plot] BIT NOT NULL DEFAULT 0;
END

-- Update scannings table
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[scannings]') AND name = 'original_filename')
BEGIN
    ALTER TABLE [dbo].[scannings] ADD [original_filename] NVARCHAR(255) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[scannings]') AND name = 'paper_size')
BEGIN
    ALTER TABLE [dbo].[scannings] ADD [paper_size] NVARCHAR(20) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[scannings]') AND name = 'document_type')
BEGIN
    ALTER TABLE [dbo].[scannings] ADD [document_type] NVARCHAR(100) NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[scannings]') AND name = 'notes')
BEGIN
    ALTER TABLE [dbo].[scannings] ADD [notes] NVARCHAR(MAX) NULL;
END

-- Update pagetypings table
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[pagetypings]') AND name = 'page_number')
BEGIN
    ALTER TABLE [dbo].[pagetypings] ADD [page_number] INT NULL;
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[pagetypings]') AND name = 'scanning_id')
BEGIN
    ALTER TABLE [dbo].[pagetypings] ADD [scanning_id] INT NULL;
    
    -- Add foreign key constraint
    ALTER TABLE [dbo].[pagetypings] 
    ADD CONSTRAINT FK_pagetypings_scannings 
    FOREIGN KEY ([scanning_id]) REFERENCES [dbo].[scannings]([id]);
END

-- Create indexes for better performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'IX_file_indexings_main_application_id')
BEGIN
    CREATE INDEX IX_file_indexings_main_application_id ON [dbo].[file_indexings] ([main_application_id]);
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'IX_file_indexings_subapplication_id')
BEGIN
    CREATE INDEX IX_file_indexings_subapplication_id ON [dbo].[file_indexings] ([subapplication_id]);
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[scannings]') AND name = 'IX_scannings_file_indexing_id')
BEGIN
    CREATE INDEX IX_scannings_file_indexing_id ON [dbo].[scannings] ([file_indexing_id]);
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[scannings]') AND name = 'IX_scannings_status')
BEGIN
    CREATE INDEX IX_scannings_status ON [dbo].[scannings] ([status]);
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[pagetypings]') AND name = 'IX_pagetypings_file_indexing_id')
BEGIN
    CREATE INDEX IX_pagetypings_file_indexing_id ON [dbo].[pagetypings] ([file_indexing_id]);
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID(N'[dbo].[pagetypings]') AND name = 'IX_pagetypings_scanning_id')
BEGIN
    CREATE INDEX IX_pagetypings_scanning_id ON [dbo].[pagetypings] ([scanning_id]);
END

-- Update existing records to set default values
UPDATE [dbo].[file_indexings] 
SET [is_co_owned_plot] = 0 
WHERE [is_co_owned_plot] IS NULL;

UPDATE [dbo].[scannings] 
SET [paper_size] = 'A4' 
WHERE [paper_size] IS NULL AND [original_filename] IS NOT NULL;

UPDATE [dbo].[scannings] 
SET [document_type] = 'Other' 
WHERE [document_type] IS NULL AND [original_filename] IS NOT NULL;

-- Add check constraints for data integrity
IF NOT EXISTS (SELECT * FROM sys.check_constraints WHERE name = 'CK_scannings_paper_size')
BEGIN
    ALTER TABLE [dbo].[scannings] 
    ADD CONSTRAINT CK_scannings_paper_size 
    CHECK ([paper_size] IN ('A3', 'A4', 'A5', 'Letter', 'Legal', 'Custom'));
END

IF NOT EXISTS (SELECT * FROM sys.check_constraints WHERE name = 'CK_scannings_status')
BEGIN
    ALTER TABLE [dbo].[scannings] 
    ADD CONSTRAINT CK_scannings_status 
    CHECK ([status] IN ('pending', 'scanned', 'typed'));
END

IF NOT EXISTS (SELECT * FROM sys.check_constraints WHERE name = 'CK_pagetypings_page_number')
BEGIN
    ALTER TABLE [dbo].[pagetypings] 
    ADD CONSTRAINT CK_pagetypings_page_number 
    CHECK ([page_number] > 0);
END

IF NOT EXISTS (SELECT * FROM sys.check_constraints WHERE name = 'CK_pagetypings_serial_number')
BEGIN
    ALTER TABLE [dbo].[pagetypings] 
    ADD CONSTRAINT CK_pagetypings_serial_number 
    CHECK ([serial_number] > 0);
END

PRINT 'Database updates completed successfully!';