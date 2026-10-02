# File Number Reservation System - Deployment Checklist

## Pre-Deployment Checklist

### 1. Code Review
- [ ] All new files created and in correct locations
- [ ] All modified files have proper integration points
- [ ] No syntax errors in PHP/SQL files
- [ ] Dependencies properly injected in constructors
- [ ] Namespace declarations correct

### 2. Files to Commit
```
New Files:
✓ database/migrations/2025_10_02_000001_create_file_number_reservations_table.php
✓ app/Models/FileNumberReservation.php
✓ app/Services/FileNumberReservationService.php
✓ app/Console/Commands/CleanupExpiredFileReservations.php

Modified Files:
✓ app/Http/Controllers/PrimaryFormController.php
✓ app/Http/Controllers/PrimaryFormDraftController.php

Documentation:
✓ FILE_NUMBER_RESERVATION_SYSTEM.md
✓ FILE_NUMBER_RESERVATION_QUICKSTART.md
✓ FILE_NUMBER_RESERVATION_SUMMARY.md
✓ test_file_number_reservations.sql
✓ This checklist
```

## Deployment Steps

### Step 1: Backup Current System
```powershell
# Backup database
sqlcmd -S your-server -d your-database -Q "BACKUP DATABASE [your-database] TO DISK = 'C:\Backups\pre-reservation-system.bak'"

# Backup code
Copy-Item -Path C:\Users\Administrator\Documents\app -Destination C:\Backups\app-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss') -Recurse

# Backup land_use_serials table
sqlcmd -S your-server -d your-database -Q "SELECT * INTO land_use_serials_backup FROM land_use_serials"
```

### Step 2: Deploy Code Changes
```powershell
# Navigate to application directory
cd C:\Users\Administrator\Documents\app

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Update autoload
composer dump-autoload -o
```

### Step 3: Run Database Migration
```powershell
# Run migration
php artisan migrate

# Expected output:
# Migrating: 2025_10_02_000001_create_file_number_reservations_table
# Migrated:  2025_10_02_000001_create_file_number_reservations_table
```

**Verify:**
```sql
-- Check table exists
SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'file_number_reservations';
-- Should return 1 row

-- Check it's empty
SELECT COUNT(*) FROM file_number_reservations;
-- Should return 0
```

### Step 4: Run Test SQL Script
```powershell
# Run the test script
sqlcmd -S your-server -d your-database -i test_file_number_reservations.sql -o test_results.txt

# Review results
Get-Content test_results.txt
```

**Expected:** All checks pass, test data created successfully

### Step 5: Test Basic Functionality
```powershell
# Test service instantiation
php artisan tinker
```
```php
$service = app(\App\Services\FileNumberReservationService::class);
dd($service); // Should show service instance
exit
```

### Step 6: Test Cleanup Command
```powershell
# Show help
php artisan filenumbers:cleanup --help

# Show statistics
php artisan filenumbers:cleanup --stats

# Dry run
php artisan filenumbers:cleanup --dry-run
# Should find 1 expired test reservation

# Actual cleanup
php artisan filenumbers:cleanup --force
# Should mark 1 reservation as expired
```

### Step 7: Test in Browser
1. Navigate to: `http://your-app/applications/application/primary/new`
2. Open browser console (F12)
3. Select land use: Residential
4. Fill in some fields (scheme number, property address)
5. Wait for autosave (check console for "Draft saved successfully")
6. Note file number displayed (e.g., NPFN-2025-RES-00008)

**Verify in Database:**
```sql
SELECT TOP 1 * FROM file_number_reservations 
WHERE status = 'reserved' 
ORDER BY reserved_at DESC;
-- Should show your new reservation
```

### Step 8: Test Full Workflow

#### A. Create and Submit Application
```
1. Create draft → Note file number (e.g., NPFN-2025-RES-00008)
2. Complete all required fields
3. Add at least 1 buyer
4. Submit application
```

**Verify:**
```sql
-- Reservation marked as used
SELECT * FROM file_number_reservations WHERE file_number = 'NPFN-2025-RES-00008';
-- Expected: status='used', application_id populated, used_at timestamp

-- Application has file number
SELECT id, np_fileno, application_status FROM mother_applications 
WHERE np_fileno = 'NPFN-2025-RES-00008';
-- Expected: 1 row with your application
```

#### B. Create and Delete Draft
```
1. Create new draft → Note file number (e.g., NPFN-2025-RES-00009)
2. Delete draft
```

**Verify:**
```sql
SELECT * FROM file_number_reservations WHERE file_number = 'NPFN-2025-RES-00009';
-- Expected: status='released', released_at timestamp
```

### Step 9: Setup Scheduled Cleanup
Edit `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Add this line:
    $schedule->command('filenumbers:cleanup --force')
             ->dailyAt('02:00')
             ->appendOutputTo(storage_path('logs/filenumber-cleanup.log'));
}
```

**Verify Scheduler:**
```powershell
php artisan schedule:list
# Should show cleanup command scheduled for 02:00 daily
```

**Setup Cron/Task Scheduler:**

**Windows (PowerShell as Administrator):**
```powershell
$action = New-ScheduledTaskAction -Execute 'php' -Argument 'artisan schedule:run' -WorkingDirectory 'C:\Users\Administrator\Documents\app'
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration ([TimeSpan]::MaxValue)
Register-ScheduledTask -TaskName "Laravel Scheduler" -Action $action -Trigger $trigger -User "SYSTEM" -RunLevel Highest
```

**Linux:**
```bash
crontab -e
# Add:
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

### Step 10: Monitor Initial Operation
```powershell
# Check logs every hour for first 24 hours
Get-Content storage\logs\laravel.log -Tail 100 | Select-String "reservation"

# Check reservation statistics
php artisan filenumbers:cleanup --stats

# Query database activity
```

```sql
-- Check recent activity
SELECT 
    CAST(reserved_at AS DATE) as date,
    COUNT(*) as count
FROM file_number_reservations
WHERE reserved_at >= CAST(GETDATE() AS DATE)
GROUP BY CAST(reserved_at AS DATE);
```

## Post-Deployment Verification

### Day 1 (Deployment Day)
- [ ] Migration completed successfully
- [ ] Test reservations created/used/released/expired correctly
- [ ] No errors in Laravel logs
- [ ] Users can create drafts normally
- [ ] File numbers are sequential
- [ ] Cleanup command runs without errors

### Day 2-3
- [ ] Monitor reservation count growth
- [ ] Check for any duplicate file numbers
- [ ] Verify scheduler is running cleanup
- [ ] Check cleanup logs for any issues
- [ ] Review user feedback

### Week 1
- [ ] Run full audit:
```sql
-- Ensure no duplicates
SELECT file_number, COUNT(*) as count
FROM file_number_reservations
GROUP BY file_number
HAVING COUNT(*) > 1;
-- Should return 0 rows

-- Check serial alignment
SELECT 
    lus.land_use_type,
    lus.current_serial,
    MAX(fnr.serial_number) as max_reserved
FROM land_use_serials lus
LEFT JOIN file_number_reservations fnr ON 
    fnr.land_use_type = lus.land_use_type AND fnr.year = lus.year
GROUP BY lus.land_use_type, lus.current_serial;
-- Should be aligned (current_serial >= max_reserved)
```

- [ ] Review reservation statistics
- [ ] Check for any anomalies in logs
- [ ] Verify cleanup is running daily

## Rollback Plan

### If Critical Issues Occur

**Step 1: Disable New Reservations**
```php
// In app/Http/Controllers/PrimaryFormDraftController.php
// Comment out lines in saveDraft() method:

/*
if ($isNewDraft && $npFileNo === '') {
    $reservation = $this->reservationService->reserveFileNumber($landUse, $currentYear, $draft->draft_id);
    ...
}
*/
```

**Step 2: Restore Database**
```powershell
# Rollback migration
php artisan migrate:rollback --step=1

# Or restore from backup
sqlcmd -S your-server -Q "RESTORE DATABASE [your-database] FROM DISK = 'C:\Backups\pre-reservation-system.bak' WITH REPLACE"
```

**Step 3: Restore Code**
```powershell
# Revert controllers to previous version
git checkout HEAD~1 app/Http/Controllers/PrimaryFormController.php
git checkout HEAD~1 app/Http/Controllers/PrimaryFormDraftController.php

# Or restore from backup
Copy-Item -Path C:\Backups\app-backup-*/app/Http/Controllers/* -Destination app/Http/Controllers/ -Recurse
```

**Step 4: Clear Caches**
```powershell
php artisan config:clear
php artisan cache:clear
composer dump-autoload
```

## Success Criteria

### Must Pass
- ✅ Zero duplicate file numbers in production
- ✅ All new drafts receive file numbers automatically
- ✅ Sequential numbering maintained
- ✅ Submitted applications mark reservations as "used"
- ✅ Deleted drafts mark reservations as "released"
- ✅ Cleanup command runs daily without errors
- ✅ No errors in Laravel logs related to reservations

### Should Pass
- ✅ Reservation table grows linearly with draft creation
- ✅ Expired reservations cleaned up within 24 hours
- ✅ User experience unchanged (transparent operation)
- ✅ No performance degradation
- ✅ Full audit trail available for all file numbers

## Troubleshooting Guide

### Issue: Migration fails
```
Error: "Table already exists"
```
**Solution:** Table may have been created manually. Check and drop if necessary:
```sql
DROP TABLE IF EXISTS file_number_reservations;
```
Then re-run: `php artisan migrate`

### Issue: Service not found
```
Error: "Class FileNumberReservationService not found"
```
**Solution:**
```powershell
composer dump-autoload -o
php artisan config:clear
php artisan cache:clear
```

### Issue: Reservations not created
**Check logs:**
```powershell
Get-Content storage\logs\laravel.log -Tail 100 | Select-String "Failed to reserve"
```

**Verify service injection:**
```php
// In PrimaryFormDraftController constructor:
dd($this->reservationService); // Should not be null
```

### Issue: Cleanup not running
**Check scheduler:**
```powershell
php artisan schedule:list
```

**Run manually:**
```powershell
php artisan filenumbers:cleanup --force -v
```

**Check cron/task scheduler is active:**
Windows: Task Scheduler → Check "Laravel Scheduler" task exists and runs
Linux: `crontab -l` → Verify entry exists

## Support Contacts

**If you encounter issues:**
1. Check Laravel logs: `storage/logs/laravel.log`
2. Review this checklist
3. Consult documentation: `FILE_NUMBER_RESERVATION_SYSTEM.md`
4. Run diagnostic queries from `test_file_number_reservations.sql`
5. Contact development team with:
   - Error messages from logs
   - Steps to reproduce
   - Database query results
   - Screenshots if applicable

## Documentation Reference

- **Comprehensive Guide:** `FILE_NUMBER_RESERVATION_SYSTEM.md`
- **Quick Start:** `FILE_NUMBER_RESERVATION_QUICKSTART.md`
- **Summary:** `FILE_NUMBER_RESERVATION_SUMMARY.md`
- **This Checklist:** `FILE_NUMBER_RESERVATION_DEPLOYMENT_CHECKLIST.md`
- **Test Script:** `test_file_number_reservations.sql`

---

**Deployment Status:** ⬜ Not Started | ⬜ In Progress | ⬜ Completed | ⬜ Rolled Back

**Deployed By:** _________________ **Date:** _________________ **Time:** _________________

**Notes:**
_______________________________________________________________________________
_______________________________________________________________________________
_______________________________________________________________________________
