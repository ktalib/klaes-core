-- =============================================
-- Script: Add Decommissioning Fields to fileNumber Table
-- Description: Adds the required fields for file decommissioning functionality
-- Date: 2024-12-19
-- =============================================

USE [klas]; -- Replace with your actual database name if different
GO

-- Check if columns already exist before adding them
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'fileNumber' AND COLUMN_NAME = 'commissioning_date')
BEGIN
    ALTER TABLE fileNumber 
    ADD commissioning_date DATETIME NULL;
    PRINT 'Added commissioning_date column to fileNumber table';
END
ELSE
BEGIN
    PRINT 'commissioning_date column already exists in fileNumber table';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'fileNumber' AND COLUMN_NAME = 'decommissioning_date')
BEGIN
    ALTER TABLE fileNumber 
    ADD decommissioning_date DATETIME NULL;
    PRINT 'Added decommissioning_date column to fileNumber table';
END
ELSE
BEGIN
    PRINT 'decommissioning_date column already exists in fileNumber table';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'fileNumber' AND COLUMN_NAME = 'decommissioning_reason')
BEGIN
    ALTER TABLE fileNumber 
    ADD decommissioning_reason NVARCHAR(MAX) NULL;
    PRINT 'Added decommissioning_reason column to fileNumber table';
END
ELSE
BEGIN
    PRINT 'decommissioning_reason column already exists in fileNumber table';
END

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'fileNumber' AND COLUMN_NAME = 'is_decommissioned')
BEGIN
    ALTER TABLE fileNumber 
    ADD is_decommissioned BIT NOT NULL DEFAULT 0;
    PRINT 'Added is_decommissioned column to fileNumber table';
END
ELSE
BEGIN
    PRINT 'is_decommissioned column already exists in fileNumber table';
END

-- Create indexes for better performance
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fileNumber_is_decommissioned' AND object_id = OBJECT_ID('fileNumber'))
BEGIN
    CREATE INDEX IX_fileNumber_is_decommissioned ON fileNumber(is_decommissioned);
    PRINT 'Created index IX_fileNumber_is_decommissioned';
END
ELSE
BEGIN
    PRINT 'Index IX_fileNumber_is_decommissioned already exists';
END

IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_fileNumber_decommissioning_date' AND object_id = OBJECT_ID('fileNumber'))
BEGIN
    CREATE INDEX IX_fileNumber_decommissioning_date ON fileNumber(decommissioning_date);
    PRINT 'Created index IX_fileNumber_decommissioning_date';
END
ELSE
BEGIN
    PRINT 'Index IX_fileNumber_decommissioning_date already exists';
END

PRINT 'Script completed successfully!';
GO