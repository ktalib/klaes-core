-- =============================================
-- Script: Create Decommissioned Files Table
-- Description: Creates the decommissioned_files table for tracking decommissioned files
-- Date: 2024-12-19
-- =============================================

USE [klas]; -- Replace with your actual database name if different
GO

-- Check if table already exists
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'decommissioned_files')
BEGIN
    CREATE TABLE decommissioned_files (
        id BIGINT IDENTITY(1,1) PRIMARY KEY,
        file_number_id BIGINT NOT NULL,
        file_no NVARCHAR(255) NULL,
        mls_file_no NVARCHAR(255) NULL,
        kangis_file_no NVARCHAR(255) NULL,
        new_kangis_file_no NVARCHAR(255) NULL,
        file_name NVARCHAR(500) NULL,
        commissioning_date DATETIME NULL,
        decommissioning_date DATETIME NOT NULL,
        decommissioning_reason NVARCHAR(MAX) NOT NULL,
        decommissioned_by NVARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE(),
        
        -- Foreign key constraint
        CONSTRAINT FK_decommissioned_files_fileNumber 
            FOREIGN KEY (file_number_id) 
            REFERENCES fileNumber(id) 
            ON DELETE CASCADE
    );
    
    PRINT 'Created decommissioned_files table';
END
ELSE
BEGIN
    PRINT 'decommissioned_files table already exists';
END

-- Create indexes for better performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_decommissioned_files_file_number_id' AND object_id = OBJECT_ID('decommissioned_files'))
BEGIN
    CREATE INDEX IX_decommissioned_files_file_number_id ON decommissioned_files(file_number_id);
    PRINT 'Created index IX_decommissioned_files_file_number_id';
END
ELSE
BEGIN
    PRINT 'Index IX_decommissioned_files_file_number_id already exists';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_decommissioned_files_mls_file_no' AND object_id = OBJECT_ID('decommissioned_files'))
BEGIN
    CREATE INDEX IX_decommissioned_files_mls_file_no ON decommissioned_files(mls_file_no);
    PRINT 'Created index IX_decommissioned_files_mls_file_no';
END
ELSE
BEGIN
    PRINT 'Index IX_decommissioned_files_mls_file_no already exists';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_decommissioned_files_decommissioning_date' AND object_id = OBJECT_ID('decommissioned_files'))
BEGIN
    CREATE INDEX IX_decommissioned_files_decommissioning_date ON decommissioned_files(decommissioning_date);
    PRINT 'Created index IX_decommissioned_files_decommissioning_date';
END
ELSE
BEGIN
    PRINT 'Index IX_decommissioned_files_decommissioning_date already exists';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_decommissioned_files_decommissioned_by' AND object_id = OBJECT_ID('decommissioned_files'))
BEGIN
    CREATE INDEX IX_decommissioned_files_decommissioned_by ON decommissioned_files(decommissioned_by);
    PRINT 'Created index IX_decommissioned_files_decommissioned_by';
END
ELSE
BEGIN
    PRINT 'Index IX_decommissioned_files_decommissioned_by already exists';
END

PRINT 'Script completed successfully!';
GO