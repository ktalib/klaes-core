-- Simplified Database Updates for File Indexing System (No Constraints)
-- Execute these SQL statements to add missing fields to the file_indexings table only

-- Check if file_indexings table exists, if not create it
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='file_indexings' AND xtype='U')
BEGIN
    CREATE TABLE [dbo].[file_indexings] (
        [id] INT IDENTITY(1,1) PRIMARY KEY,
        [main_application_id] INT NULL,
        [subapplication_id] INT NULL,
        [file_number] NVARCHAR(255) NOT NULL,
        [file_number_id] INT NULL,
        [file_title] NVARCHAR(255) NOT NULL,
        [plot_number] NVARCHAR(100) NULL,
        [land_use_type] NVARCHAR(100) NULL DEFAULT 'Residential',
        [district] NVARCHAR(100) NULL,
        [lga] NVARCHAR(100) NULL,
        [has_cofo] BIT NOT NULL DEFAULT 0,
        [is_merged] BIT NOT NULL DEFAULT 0,
        [has_transaction] BIT NOT NULL DEFAULT 0,
        [is_problematic] BIT NOT NULL DEFAULT 0,
        [created_by] INT NULL,
        [updated_by] INT NULL,
        [created_at] DATETIME2 DEFAULT GETDATE(),
        [updated_at] DATETIME2 DEFAULT GETDATE()
    );
    PRINT 'Created file_indexings table';
END

-- Add file_number_id column to store the ID from fileNumber table when selected
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'file_number_id')
BEGIN
    ALTER TABLE [dbo].[file_indexings] ADD [file_number_id] INT NULL;
    PRINT 'Added file_number_id column to file_indexings';
END

-- Add missing columns to existing file_indexings table
IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'st_fillno')
BEGIN
    ALTER TABLE [dbo].[file_indexings] ADD [st_fillno] NVARCHAR(255) NULL;
    PRINT 'Added st_fillno column to file_indexings';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'serial_no')
BEGIN
    ALTER TABLE [dbo].[file_indexings] ADD [serial_no] NVARCHAR(100) NULL;
    PRINT 'Added serial_no column to file_indexings';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'batch_no')
BEGIN
    ALTER TABLE [dbo].[file_indexings] ADD [batch_no] NVARCHAR(100) NULL;
    PRINT 'Added batch_no column to file_indexings';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'shelf_location')
BEGIN
    ALTER TABLE [dbo].[file_indexings] ADD [shelf_location] NVARCHAR(100) NULL;
    PRINT 'Added shelf_location column to file_indexings';
END

IF NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[file_indexings]') AND name = 'is_co_owned_plot')
BEGIN
    ALTER TABLE [dbo].[file_indexings] ADD [is_co_owned_plot] BIT NOT NULL DEFAULT 0;
    PRINT 'Added is_co_owned_plot column to file_indexings';
END

-- Update existing records to set default values for new columns
UPDATE [dbo].[file_indexings] 
SET [is_co_owned_plot] = 0 
WHERE [is_co_owned_plot] IS NULL;

PRINT 'Simple file indexing database updates completed successfully!';