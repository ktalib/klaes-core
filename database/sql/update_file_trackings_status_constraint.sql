-- Update file_trackings table status constraint to match the new tab-based status values
-- This script updates the CHECK constraint to allow the new status values

USE klas;
GO

-- Drop the existing constraint
IF EXISTS (SELECT * FROM sys.check_constraints WHERE name = 'CK_file_trackings_status')
BEGIN
    ALTER TABLE [dbo].[file_trackings] DROP CONSTRAINT [CK_file_trackings_status];
    PRINT 'Existing constraint CK_file_trackings_status dropped successfully.';
END

-- Add the new constraint with updated status values
ALTER TABLE [dbo].[file_trackings]
ADD CONSTRAINT [CK_file_trackings_status]
CHECK ([status] IN ('in_process', 'pending', 'on_hold', 'completed'));

PRINT 'New constraint CK_file_trackings_status added successfully with updated status values.';

-- Optional: Update existing records to use new status values (if any exist)
-- Uncomment the following lines if you want to migrate existing data

/*
UPDATE [dbo].[file_trackings] 
SET [status] = CASE 
    WHEN [status] = 'active' THEN 'in_process'
    WHEN [status] = 'checked_out' THEN 'pending'
    WHEN [status] = 'overdue' THEN 'on_hold'
    WHEN [status] = 'returned' THEN 'completed'
    WHEN [status] = 'lost' THEN 'on_hold'
    WHEN [status] = 'archived' THEN 'completed'
    ELSE [status]
END
WHERE [status] IN ('active', 'checked_out', 'overdue', 'returned', 'lost', 'archived');

PRINT 'Existing records updated to use new status values.';
*/

GO