-- Create file_trackings table for KLAES Lands Module File Tracker
-- This table supports real-time tracking of physical land files using RFID and manual updates

USE [your_database_name]; -- Replace with your actual database name

-- Drop table if exists (for development purposes)
IF OBJECT_ID('dbo.file_trackings', 'U') IS NOT NULL
    DROP TABLE dbo.file_trackings;
 
-- Create the file_trackings table
CREATE TABLE dbo.file_trackings (
    [id] INT IDENTITY(1,1) PRIMARY KEY,
    [file_indexing_id] INT NOT NULL,
    [rfid_tag] VARCHAR(100) NULL,
    [qr_code] VARCHAR(100) NULL,
    [current_location] VARCHAR(255) NULL,
    [current_holder] VARCHAR(255) NULL,
    [current_handler] VARCHAR(255) NULL,
    [date_received] DATETIME NULL,
    [due_date] DATETIME NULL,
    [status] VARCHAR(50) NOT NULL DEFAULT 'active',
    [movement_history] TEXT NULL,
    [created_at] DATETIME NOT NULL DEFAULT GETDATE(),
    [updated_at] DATETIME NULL,
    
    -- Foreign key constraint
    CONSTRAINT FK_file_trackings_file_indexing_id 
        FOREIGN KEY ([file_indexing_id]) 
        REFERENCES dbo.file_indexings([id]) 
        ON DELETE CASCADE,
    
    -- Unique constraints
    CONSTRAINT UQ_file_trackings_rfid_tag 
        UNIQUE ([rfid_tag]),
    
    CONSTRAINT UQ_file_trackings_qr_code 
        UNIQUE ([qr_code]),
    
    -- Check constraints
    CONSTRAINT CK_file_trackings_status 
        CHECK ([status] IN ('active', 'checked_out', 'overdue', 'returned', 'lost', 'archived')),
    
    CONSTRAINT CK_file_trackings_due_date_future 
        CHECK ([due_date] IS NULL OR [due_date] > [date_received])
);

-- Create indexes for better performance
CREATE INDEX IX_file_trackings_file_indexing_id ON dbo.file_trackings([file_indexing_id]);
CREATE INDEX IX_file_trackings_rfid_tag ON dbo.file_trackings([rfid_tag]);
CREATE INDEX IX_file_trackings_qr_code ON dbo.file_trackings([qr_code]);
CREATE INDEX IX_file_trackings_status ON dbo.file_trackings([status]);
CREATE INDEX IX_file_trackings_current_location ON dbo.file_trackings([current_location]);
CREATE INDEX IX_file_trackings_current_handler ON dbo.file_trackings([current_handler]);
CREATE INDEX IX_file_trackings_due_date ON dbo.file_trackings([due_date]);
CREATE INDEX IX_file_trackings_created_at ON dbo.file_trackings([created_at]);

PRINT 'file_trackings table created successfully with indexes and constraints.';
GO

-- Create a trigger to automatically update the updated_at timestamp
CREATE TRIGGER TR_file_trackings_updated_at
ON dbo.file_trackings
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    UPDATE dbo.file_trackings
    SET [updated_at] = GETDATE()
    FROM dbo.file_trackings ft
    INNER JOIN inserted i ON ft.[id] = i.[id];
END;
GO

-- Insert some sample data for testing (optional)
/*
INSERT INTO dbo.file_trackings (
    [file_indexing_id], 
    [rfid_tag], 
    [qr_code], 
    [current_location], 
    [current_holder], 
    [current_handler], 
    [date_received], 
    [due_date], 
    [status], 
    [movement_history]
) VALUES 
(1, 'RFID001', 'QR001', 'Archive Room A', 'John Doe', 'Jane Smith', GETDATE(), DATEADD(day, 30, GETDATE()), 'active', '[]'),
(2, 'RFID002', 'QR002', 'Legal Department', 'Mike Johnson', 'Sarah Wilson', GETDATE(), DATEADD(day, 15, GETDATE()), 'checked_out', '[]');
*/

PRINT 'File tracking system setup completed successfully.';