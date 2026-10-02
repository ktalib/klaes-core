# File Decommissioning Database Scripts

This folder contains MS SQL Server scripts to set up the database structure for the File Decommissioning Sub Module.

## Scripts Overview

### 1. `01_add_decommissioning_fields_to_fileNumber.sql`
**Purpose**: Adds the required decommissioning fields to the existing `fileNumber` table.

**Fields Added**:
- `commissioning_date` (DATETIME NULL) - When the file was commissioned
- `decommissioning_date` (DATETIME NULL) - When the file was decommissioned  
- `decommissioning_reason` (NVARCHAR(MAX) NULL) - Reason for decommissioning
- `is_decommissioned` (BIT NOT NULL DEFAULT 0) - Flag indicating if file is decommissioned

**Indexes Created**:
- `IX_fileNumber_is_decommissioned` - For filtering active/decommissioned files
- `IX_fileNumber_decommissioning_date` - For date-based queries

### 2. `02_create_decommissioned_files_table.sql`
**Purpose**: Creates the `decommissioned_files` table to track all decommissioned files.

**Table Structure**:
```sql
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
    
    CONSTRAINT FK_decommissioned_files_fileNumber 
        FOREIGN KEY (file_number_id) 
        REFERENCES fileNumber(id) 
        ON DELETE CASCADE
);
```

### 3. `03_create_decommissioning_views.sql`
**Purpose**: Creates helpful views for reporting and data access.

**Views Created**:
- `vw_active_files` - Shows all active (non-decommissioned) files
- `vw_decommissioned_files` - Shows all decommissioned files with details
- `vw_decommissioning_stats` - Provides statistics for dashboard

### 4. `04_create_decommissioning_procedures.sql`
**Purpose**: Creates stored procedures for common operations.

**Procedures Created**:
- `sp_DecommissionFile` - Safely decommission a file with transaction handling
- `sp_GetDecommissioningStats` - Get statistics for dashboard
- `sp_SearchActiveFiles` - Search active files with ranking

### 5. `05_sample_data_and_tests.sql`
**Purpose**: Tests the system and verifies everything works correctly.

**What it does**:
- Tests the decommissioning procedure
- Verifies table structures
- Checks indexes
- Runs sample queries

## Installation Instructions

### Step 1: Run the Scripts in Order
Execute the scripts in the following order:

```sql
-- 1. Add fields to existing table
\i 01_add_decommissioning_fields_to_fileNumber.sql

-- 2. Create decommissioned files table
\i 02_create_decommissioned_files_table.sql

-- 3. Create views
\i 03_create_decommissioning_views.sql

-- 4. Create stored procedures
\i 04_create_decommissioning_procedures.sql

-- 5. Run tests (optional)
\i 05_sample_data_and_tests.sql
```

### Step 2: Update Laravel Models
After running the database scripts, replace the current models with the database versions:

```bash
# Backup current models
cp app/Models/FileNumber.php app/Models/FileNumber_file_based.php
cp app/Models/DecommissionedFiles.php app/Models/DecommissionedFiles_file_based.php

# Replace with database versions
cp app/Models/FileNumber_database.php app/Models/FileNumber.php
cp app/Models/DecommissionedFiles_database.php app/Models/DecommissionedFiles.php
```

### Step 3: Verify Installation
1. Check that all tables and fields exist:
```sql
-- Check fileNumber table has new fields
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'fileNumber' 
AND COLUMN_NAME IN ('commissioning_date', 'decommissioning_date', 'decommissioning_reason', 'is_decommissioned');

-- Check decommissioned_files table exists
SELECT COUNT(*) FROM decommissioned_files;
```

2. Test the web interface:
   - Navigate to `/file-decommissioning`
   - Try decommissioning a test file
   - Check the decommissioned files list

## Database Schema Changes

### Before (File-based)
- Data stored in `storage/app/decommissioned_files.json`
- No database constraints
- Manual file management

### After (Database-based)
- Data stored in proper database tables
- Foreign key constraints
- Automatic transaction handling
- Better performance with indexes
- Stored procedures for complex operations

## Performance Considerations

### Indexes Created
- `IX_fileNumber_is_decommissioned` - Fast filtering of active vs decommissioned
- `IX_fileNumber_decommissioning_date` - Fast date-based queries
- `IX_decommissioned_files_file_number_id` - Fast joins between tables
- `IX_decommissioned_files_mls_file_no` - Fast searches by MLS number
- `IX_decommissioned_files_decommissioning_date` - Fast date filtering
- `IX_decommissioned_files_decommissioned_by` - Fast user-based filtering

### Query Optimization
- Views provide pre-optimized queries
- Stored procedures reduce network round trips
- Proper indexing ensures fast searches

## Backup and Recovery

### Before Running Scripts
```sql
-- Backup existing data
SELECT * INTO fileNumber_backup FROM fileNumber;
```

### After Installation
```sql
-- Regular backup of decommissioned files
BACKUP DATABASE [klas] TO DISK = 'C:\Backup\klas_with_decommissioning.bak';
```

## Troubleshooting

### Common Issues

1. **Permission Errors**
   - Ensure you have ALTER TABLE permissions
   - Ensure you have CREATE TABLE permissions
   - Run as database administrator if needed

2. **Foreign Key Constraint Errors**
   - Ensure fileNumber table exists
   - Ensure fileNumber.id is the primary key
   - Check for orphaned records

3. **Stored Procedure Errors**
   - Check SQL Server version compatibility
   - Ensure proper database context (USE statement)
   - Verify parameter types match

### Rollback Instructions
If you need to rollback the changes:

```sql
-- Remove added columns from fileNumber
ALTER TABLE fileNumber DROP COLUMN commissioning_date;
ALTER TABLE fileNumber DROP COLUMN decommissioning_date;
ALTER TABLE fileNumber DROP COLUMN decommissioning_reason;
ALTER TABLE fileNumber DROP COLUMN is_decommissioned;

-- Drop created objects
DROP TABLE decommissioned_files;
DROP VIEW vw_active_files;
DROP VIEW vw_decommissioned_files;
DROP VIEW vw_decommissioning_stats;
DROP PROCEDURE sp_DecommissionFile;
DROP PROCEDURE sp_GetDecommissioningStats;
DROP PROCEDURE sp_SearchActiveFiles;
```

## Support

If you encounter any issues:
1. Check the error messages in the SQL output
2. Verify your database permissions
3. Ensure you're running the scripts in the correct order
4. Check the Laravel logs for any application errors

The system is designed to be backward compatible and will fall back to manual queries if stored procedures are not available.