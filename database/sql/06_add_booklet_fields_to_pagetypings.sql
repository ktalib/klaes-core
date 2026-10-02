-- Add Booklet Management Fields to PageTypings Table
-- Execute this script in SQL Server Management Studio or equivalent

USE [your_database_name]
GO

-- Add booklet_id field (nullable string to store unique booklet identifier)
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'booklet_id')
BEGIN
    ALTER TABLE pagetypings 
    ADD booklet_id NVARCHAR(50) NULL
END
GO

-- Add is_booklet_page field (boolean to indicate if page is part of a booklet)
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'is_booklet_page')
BEGIN
    ALTER TABLE pagetypings 
    ADD is_booklet_page BIT NULL DEFAULT 0
END
GO

-- Add booklet_sequence field (to track the alphabetic sequence within a booklet)
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'pagetypings' AND COLUMN_NAME = 'booklet_sequence')
BEGIN
    ALTER TABLE pagetypings 
    ADD booklet_sequence NVARCHAR(5) NULL
END
GO

-- Add index on booklet_id for better performance when querying booklet pages
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pagetypings_booklet_id')
BEGIN
    CREATE INDEX IX_pagetypings_booklet_id ON pagetypings(booklet_id)
END
GO

-- Add composite index for booklet queries
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_pagetypings_booklet_composite')
BEGIN
    CREATE INDEX IX_pagetypings_booklet_composite ON pagetypings(is_booklet_page, booklet_id, booklet_sequence)
END
GO

PRINT 'Booklet management fields added successfully to pagetypings table!'
PRINT 'New fields:'
PRINT '- booklet_id: NVARCHAR(50) NULL (stores unique booklet identifier like "booklet_1693747200000")'
PRINT '- is_booklet_page: BIT NULL DEFAULT 0 (indicates if page is part of a booklet)'
PRINT '- booklet_sequence: NVARCHAR(5) NULL (stores alphabetic sequence like "a", "b", "c")'
PRINT 'Indexes created for better performance'

-- Verify the new columns were added
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'pagetypings' 
    AND COLUMN_NAME IN ('booklet_id', 'is_booklet_page', 'booklet_sequence')
ORDER BY COLUMN_NAME
