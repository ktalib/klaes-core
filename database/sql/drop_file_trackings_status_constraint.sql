-- Drop the CK_file_trackings_status constraint so the application can insert statuses as selected
-- Requires sufficient privileges (ALTER on dbo.file_trackings)

USE [klas];
GO

-- Check if the constraint exists on dbo.file_trackings
IF EXISTS (
    SELECT 1
    FROM sys.check_constraints cc
    INNER JOIN sys.tables t ON cc.parent_object_id = t.object_id
    INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
    WHERE cc.name = 'CK_file_trackings_status'
      AND t.name = 'file_trackings'
      AND s.name = 'dbo'
)
BEGIN
    ALTER TABLE [dbo].[file_trackings] DROP CONSTRAINT [CK_file_trackings_status];
    PRINT 'Constraint CK_file_trackings_status dropped successfully.';
END
ELSE
BEGIN
    PRINT 'Constraint CK_file_trackings_status not found on dbo.file_trackings.';
END
GO

-- Optional: Verify there is no remaining check constraint on the status column
SELECT cc.name AS constraint_name, cc.definition
FROM sys.check_constraints cc
INNER JOIN sys.tables t ON cc.parent_object_id = t.object_id
INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
WHERE t.name = 'file_trackings' AND s.name = 'dbo';
GO