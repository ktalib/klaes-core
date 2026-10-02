# File Decommissioning Database Installation Guide

## Quick Start

### 1. Run Database Scripts
Execute these scripts in SQL Server Management Studio or your preferred SQL client **in this exact order**:

```sql
-- Script 1: Add fields to fileNumber table
-- File: 01_add_decommissioning_fields_to_fileNumber.sql
USE [klas];
-- (Copy and paste the entire content of script 1)

-- Script 2: Create decommissioned_files table  
-- File: 02_create_decommissioned_files_table.sql
-- (Copy and paste the entire content of script 2)

-- Script 3: Create views (optional but recommended)
-- File: 03_create_decommissioning_views.sql
-- (Copy and paste the entire content of script 3)

-- Script 4: Create stored procedures (optional but recommended)
-- File: 04_create_decommissioning_procedures.sql
-- (Copy and paste the entire content of script 4)

-- Script 5: Run tests (optional)
-- File: 05_sample_data_and_tests.sql
-- (Copy and paste the entire content of script 5)
```

### 2. Update Laravel Models
After running the database scripts, update the Laravel models to use the database instead of file storage:

**Option A: Using Command Line**
```bash
cd c:\xampp\htdocs\kangi.com.ng

# Backup current models
copy app\Models\FileNumber.php app\Models\FileNumber_file_based.php
copy app\Models\DecommissionedFiles.php app\Models\DecommissionedFiles_file_based.php

# Replace with database versions
copy app\Models\FileNumber_database.php app\Models\FileNumber.php
copy app\Models\DecommissionedFiles_database.php app\Models\DecommissionedFiles.php
```

**Option B: Manual File Replacement**
1. Rename `app\Models\FileNumber.php` to `app\Models\FileNumber_file_based.php`
2. Rename `app\Models\DecommissionedFiles.php` to `app\Models\DecommissionedFiles_file_based.php`
3. Rename `app\Models\FileNumber_database.php` to `app\Models\FileNumber.php`
4. Rename `app\Models\DecommissionedFiles_database.php` to `app\Models\DecommissionedFiles.php`

### 3. Test the System
1. Open your browser and navigate to: `http://localhost/kangi.com.ng/file-decommissioning`
2. Try decommissioning a test file
3. Check the decommissioned files list
4. Verify statistics are showing correctly

## Detailed Installation Steps

### Prerequisites
- SQL Server with appropriate permissions
- Access to the `klas` database (or your database name)
- Laravel application already running

### Step 1: Database Schema Updates

#### 1.1 Add Fields to fileNumber Table
Run script `01_add_decommissioning_fields_to_fileNumber.sql`:

This adds:
- `commissioning_date` - When file was commissioned (optional)
- `decommissioning_date` - When file was decommissioned (required)
- `decommissioning_reason` - Why file was decommissioned (required)
- `is_decommissioned` - Boolean flag for decommissioned status

#### 1.2 Create decommissioned_files Table
Run script `02_create_decommissioned_files_table.sql`:

This creates a dedicated table to track all decommissioned files with full audit trail.

#### 1.3 Create Views (Optional)
Run script `03_create_decommissioning_views.sql`:

Creates helpful views:
- `vw_active_files` - All active files
- `vw_decommissioned_files` - All decommissioned files with details
- `vw_decommissioning_stats` - Statistics for dashboard

#### 1.4 Create Stored Procedures (Optional)
Run script `04_create_decommissioning_procedures.sql`:

Creates procedures:
- `sp_DecommissionFile` - Safe file decommissioning with transactions
- `sp_GetDecommissioningStats` - Dashboard statistics
- `sp_SearchActiveFiles` - Optimized file search

#### 1.5 Test Installation (Optional)
Run script `05_sample_data_and_tests.sql`:

This will test the installation and verify everything works.

### Step 2: Update Application Code

The Laravel models need to be updated to use the database tables instead of file storage.

#### 2.1 Backup Current Models
```bash
copy app\Models\FileNumber.php app\Models\FileNumber_file_based.php
copy app\Models\DecommissionedFiles.php app\Models\DecommissionedFiles_file_based.php
```

#### 2.2 Replace Models
```bash
copy app\Models\FileNumber_database.php app\Models\FileNumber.php
copy app\Models\DecommissionedFiles_database.php app\Models\DecommissionedFiles.php
```

### Step 3: Verification

#### 3.1 Database Verification
```sql
-- Check new fields exist
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'fileNumber' 
AND COLUMN_NAME IN ('commissioning_date', 'decommissioning_date', 'decommissioning_reason', 'is_decommissioned');

-- Check decommissioned_files table
SELECT COUNT(*) FROM decommissioned_files;

-- Check views exist
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS 
WHERE TABLE_NAME LIKE '%decommission%';

-- Check procedures exist
SELECT ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_TYPE = 'PROCEDURE' AND ROUTINE_NAME LIKE '%Decommission%';
```

#### 3.2 Application Verification
1. Navigate to `/file-decommissioning`
2. Check that statistics load correctly
3. Try searching for files
4. Decommission a test file
5. View decommissioned files list

## Migration from File-Based to Database

If you were using the file-based system and have data in `storage/app/decommissioned_files.json`, you can migrate it:

### Migration Script
```php
<?php
// Run this in Laravel Tinker: php artisan tinker

use App\Models\FileNumber;
use App\Models\DecommissionedFiles;
use Illuminate\Support\Facades\Storage;

// Read existing file data
$fileData = Storage::get('decommissioned_files.json');
$records = json_decode($fileData, true);

foreach ($records as $record) {
    // Create database record
    DecommissionedFiles::create([
        'file_number_id' => $record['file_number_id'],
        'file_no' => $record['file_no'],
        'mls_file_no' => $record['mls_file_no'],
        'kangis_file_no' => $record['kangis_file_no'],
        'new_kangis_file_no' => $record['new_kangis_file_no'],
        'file_name' => $record['file_name'],
        'commissioning_date' => $record['commissioning_date'],
        'decommissioning_date' => $record['decommissioning_date'],
        'decommissioning_reason' => $record['decommissioning_reason'],
        'decommissioned_by' => $record['decommissioned_by'],
        'created_at' => $record['created_at'],
        'updated_at' => $record['updated_at']
    ]);
    
    // Update fileNumber record
    FileNumber::where('id', $record['file_number_id'])->update([
        'commissioning_date' => $record['commissioning_date'],
        'decommissioning_date' => $record['decommissioning_date'],
        'decommissioning_reason' => $record['decommissioning_reason'],
        'is_decommissioned' => true
    ]);
}

echo "Migration completed!";
```

## Troubleshooting

### Common Issues

#### Issue 1: Permission Denied
**Error**: "CREATE TABLE permission denied"
**Solution**: Run scripts as database administrator or request permissions

#### Issue 2: Foreign Key Constraint Error
**Error**: "Could not create constraint"
**Solution**: Ensure fileNumber table exists and has proper primary key

#### Issue 3: Column Already Exists
**Error**: "Column names in each table must be unique"
**Solution**: Scripts include checks for existing columns - this is normal

#### Issue 4: Laravel Model Errors
**Error**: "Class not found" or "Method not found"
**Solution**: Ensure you've replaced the model files correctly

### Rollback Procedure

If you need to rollback:

```sql
-- Remove database changes
DROP TABLE decommissioned_files;
ALTER TABLE fileNumber DROP COLUMN commissioning_date;
ALTER TABLE fileNumber DROP COLUMN decommissioning_date;
ALTER TABLE fileNumber DROP COLUMN decommissioning_reason;
ALTER TABLE fileNumber DROP COLUMN is_decommissioned;

-- Drop views and procedures
DROP VIEW vw_active_files;
DROP VIEW vw_decommissioned_files;
DROP VIEW vw_decommissioning_stats;
DROP PROCEDURE sp_DecommissionFile;
DROP PROCEDURE sp_GetDecommissioningStats;
DROP PROCEDURE sp_SearchActiveFiles;
```

```bash
# Restore original models
copy app\Models\FileNumber_file_based.php app\Models\FileNumber.php
copy app\Models\DecommissionedFiles_file_based.php app\Models\DecommissionedFiles.php
```

## Performance Notes

### Expected Performance Improvements
- **Database queries**: 10-100x faster than file operations
- **Search functionality**: Indexed searches vs full file scans
- **Concurrent access**: Database handles multiple users vs file locking
- **Data integrity**: ACID transactions vs potential file corruption

### Monitoring
- Monitor query performance with SQL Server Profiler
- Check index usage with `sys.dm_db_index_usage_stats`
- Monitor table sizes as data grows

## Security Considerations

### Database Security
- Ensure proper user permissions
- Use parameterized queries (already implemented)
- Regular database backups

### Application Security
- CSRF protection (already implemented)
- Input validation (already implemented)
- Authentication required (already implemented)

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check SQL Server error logs
3. Verify database connectivity
4. Test with sample data

The system is designed to be robust and will fall back to simpler queries if advanced features (stored procedures) are not available.