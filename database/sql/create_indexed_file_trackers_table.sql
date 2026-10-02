-- Create indexed_file_trackers table for physical file tracking sheets
-- This table stores tracking information for physical files with printed tracking sheets

IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='indexed_file_trackers' AND xtype='U')
BEGIN
    CREATE TABLE indexed_file_trackers (
        id int IDENTITY(1,1) PRIMARY KEY,
        file_indexing_id int NOT NULL,
        tracking_id varchar(50) NOT NULL UNIQUE, -- Format: TRK-YYYY-XXX
        qr_code varchar(255) NULL,
        rfid_tag varchar(50) NULL,
        
        -- Current Location Information
        current_location varchar(255) NULL DEFAULT 'File Indexing Department',
        current_handler varchar(255) NULL DEFAULT 'System User',
        current_department varchar(255) NULL DEFAULT 'File Indexing Department',
        last_location_update datetime2(0) NULL DEFAULT GETDATE(),
        
        -- Status Information
        status varchar(50) NULL DEFAULT 'Active', -- Active, Archived, Lost, Damaged
        priority varchar(20) NULL DEFAULT 'Normal', -- High, Normal, Low
        notes text NULL,
        
        -- Movement History (JSON format for flexibility)
        movement_history text NULL, -- JSON array of movement records
        
        -- Tracking Sheet Information
        sheet_generated_at datetime2(0) NULL DEFAULT GETDATE(),
        sheet_printed_at datetime2(0) NULL,
        sheet_printed_by int NULL, -- user_id who printed the sheet
        total_prints int NULL DEFAULT 0,
        
        -- Physical File Information
        file_location varchar(255) NULL, -- Physical storage location
        shelf_number varchar(50) NULL,
        box_number varchar(50) NULL,
        
        -- Timestamps
        created_at datetime2(0) NULL DEFAULT GETDATE(),
        updated_at datetime2(0) NULL DEFAULT GETDATE(),
        created_by int NULL,
        updated_by int NULL,
        
        -- Foreign key constraint
        CONSTRAINT FK_indexed_file_trackers_file_indexing_id 
            FOREIGN KEY (file_indexing_id) REFERENCES file_indexings(id) ON DELETE CASCADE,
        
        -- Index for performance
        INDEX IX_indexed_file_trackers_file_indexing_id (file_indexing_id),
        INDEX IX_indexed_file_trackers_tracking_id (tracking_id),
        INDEX IX_indexed_file_trackers_status (status),
        INDEX IX_indexed_file_trackers_current_location (current_location)
    );
    
    PRINT 'Table indexed_file_trackers created successfully';
END
ELSE
BEGIN
    PRINT 'Table indexed_file_trackers already exists';
END
GO

-- Create sample movement history JSON structure comment
/*
Movement History JSON Structure:
[
    {
        "date": "2025-08-21",
        "time": "09:30 AM",
        "location": "File Indexing Department", 
        "handler": "System User",
        "action": "File indexed and registered",
        "method": "Digital",
        "notes": ""
    },
    {
        "date": "2025-08-21",
        "time": "10:15 AM",
        "location": "Scanning Department",
        "handler": "Scanner Operator", 
        "action": "Document scanning completed",
        "method": "Digital Scan",
        "notes": "All pages scanned successfully"
    }
]
*/