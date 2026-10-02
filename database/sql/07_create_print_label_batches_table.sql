-- Create print_label_batches table for tracking label printing
-- This table manages batches of file labels that are printed

USE [klas];
GO

-- Drop tables if they exist (in correct order due to foreign keys)
IF OBJECT_ID('dbo.print_label_batch_items', 'U') IS NOT NULL
    DROP TABLE dbo.print_label_batch_items;
GO

IF OBJECT_ID('dbo.print_label_batches', 'U') IS NOT NULL
    DROP TABLE dbo.print_label_batches;
GO

-- Create the print_label_batches table
CREATE TABLE dbo.print_label_batches (
    id INT IDENTITY(1,1) PRIMARY KEY,
    batch_number NVARCHAR(50) NOT NULL UNIQUE,
    batch_size INT NOT NULL DEFAULT 30,
    generated_count INT NOT NULL DEFAULT 0,
    label_format NVARCHAR(20) NOT NULL DEFAULT 'standard', -- 'standard', 'compact', 'qr_code', '30-in-1'
    orientation NVARCHAR(10) NOT NULL DEFAULT 'portrait', -- 'portrait', 'landscape'
    status NVARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'generated', 'printed', 'completed'
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at DATETIME2(7) NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2(7) NOT NULL DEFAULT GETDATE(),
    printed_at DATETIME2(7) NULL,
    
    -- Constraints
    CONSTRAINT CK_print_label_batches_batch_size CHECK (batch_size > 0 AND batch_size <= 100),
    CONSTRAINT CK_print_label_batches_generated_count CHECK (generated_count >= 0),
    CONSTRAINT CK_print_label_batches_label_format CHECK (label_format IN ('standard', 'compact', 'qr_code', '30-in-1')),
    CONSTRAINT CK_print_label_batches_orientation CHECK (orientation IN ('portrait', 'landscape')),
    CONSTRAINT CK_print_label_batches_status CHECK (status IN ('pending', 'generated', 'printed', 'completed'))
);
GO

-- Create index on batch_number for quick lookups
CREATE UNIQUE NONCLUSTERED INDEX IX_print_label_batches_batch_number
ON dbo.print_label_batches (batch_number);
GO

-- Create index on created_by for user-specific queries
CREATE NONCLUSTERED INDEX IX_print_label_batches_created_by
ON dbo.print_label_batches (created_by);
GO

-- Create index on status and created_at for filtering
CREATE NONCLUSTERED INDEX IX_print_label_batches_status_created_at
ON dbo.print_label_batches (status, created_at DESC);
GO

-- Create print_label_batch_items table for individual file labels in a batch
CREATE TABLE dbo.print_label_batch_items (
    id INT IDENTITY(1,1) PRIMARY KEY,
    batch_id INT NOT NULL,
    file_indexing_id INT NOT NULL,
    file_number NVARCHAR(50) NOT NULL,
    file_title NVARCHAR(255) NULL,
    plot_number NVARCHAR(50) NULL,
    district NVARCHAR(100) NULL,
    lga NVARCHAR(100) NULL,
    land_use_type NVARCHAR(50) NULL,
    shelf_location NVARCHAR(100) NULL,
    qr_code_data NVARCHAR(MAX) NULL,
    barcode_data NVARCHAR(255) NULL,
    label_position INT NULL, -- Position in the batch (1-30 for 30-per-batch)
    is_printed BIT NOT NULL DEFAULT 0,
    printed_at DATETIME2(7) NULL,
    created_at DATETIME2(7) NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2(7) NOT NULL DEFAULT GETDATE(),
    
    -- Foreign key constraints
    CONSTRAINT FK_print_label_batch_items_batch_id 
        FOREIGN KEY (batch_id) REFERENCES dbo.print_label_batches(id) ON DELETE CASCADE,
    CONSTRAINT FK_print_label_batch_items_file_indexing_id 
        FOREIGN KEY (file_indexing_id) REFERENCES dbo.file_indexings(id) ON DELETE CASCADE,
        
    -- Ensure unique file per batch
    CONSTRAINT UK_print_label_batch_items_batch_file 
        UNIQUE (batch_id, file_indexing_id)
);
GO

-- Create indexes for performance
CREATE NONCLUSTERED INDEX IX_print_label_batch_items_batch_id
ON dbo.print_label_batch_items (batch_id);
GO

CREATE NONCLUSTERED INDEX IX_print_label_batch_items_file_indexing_id
ON dbo.print_label_batch_items (file_indexing_id);
GO

CREATE NONCLUSTERED INDEX IX_print_label_batch_items_is_printed
ON dbo.print_label_batch_items (is_printed, batch_id);
GO

-- Add trigger to update batch generated_count when items are added/removed
-- Note: SQL Server uses AFTER triggers, not BEFORE
CREATE TRIGGER TR_print_label_batch_items_update_count
ON dbo.print_label_batch_items
AFTER INSERT, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Update generated_count for affected batches
    UPDATE b
    SET generated_count = (
        SELECT COUNT(*)
        FROM dbo.print_label_batch_items bi
        WHERE bi.batch_id = b.id
    ),
    updated_at = GETDATE()
    FROM dbo.print_label_batches b
    WHERE b.id IN (
        SELECT DISTINCT batch_id FROM inserted
        UNION
        SELECT DISTINCT batch_id FROM deleted
    );
END;
GO

-- Add trigger to update updated_at timestamp for batches
CREATE TRIGGER TR_print_label_batches_update_timestamp
ON dbo.print_label_batches
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE dbo.print_label_batches
    SET updated_at = GETDATE()
    FROM dbo.print_label_batches p
    INNER JOIN inserted i ON p.id = i.id;
END;
GO

-- Add trigger to update updated_at timestamp for batch items
CREATE TRIGGER TR_print_label_batch_items_update_timestamp
ON dbo.print_label_batch_items
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE dbo.print_label_batch_items
    SET updated_at = GETDATE()
    FROM dbo.print_label_batch_items p
    INNER JOIN inserted i ON p.id = i.id;
END;
GO

-- Insert sample data for testing
INSERT INTO dbo.print_label_batches (batch_number, batch_size, label_format, orientation, status, created_by)
VALUES 
    ('PLB-2025-001', 30, 'standard', 'portrait', 'pending', 1),
    ('PLB-2025-002', 30, '30-in-1', 'landscape', 'generated', 1);
GO

PRINT 'Print label batches tables created successfully!';
PRINT 'Tables created:';
PRINT '- dbo.print_label_batches';
PRINT '- dbo.print_label_batch_items';
PRINT 'Indexes and triggers created for optimal performance.';
