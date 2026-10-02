# File Number Reservation System - Quick Start Guide

## Installation Steps

### Step 1: Run Migration
```powershell
php artisan migrate
```

**Expected Output:**
```
Migrating: 2025_10_02_000001_create_file_number_reservations_table
Migrated:  2025_10_02_000001_create_file_number_reservations_table (XXX.XXms)
```

**Verify:**
```sql
SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'file_number_reservations';
-- Should return 1 row

SELECT COUNT(*) FROM file_number_reservations;
-- Should return 0 (empty table initially)
```

### Step 2: Verify Service Registration
Laravel will auto-discover the service through dependency injection. No manual registration needed.

**Test:**
```powershell
php artisan tinker
```

```php
$service = app(\App\Services\FileNumberReservationService::class);
dd($service); // Should show the service instance
exit
```

### Step 3: Test File Number Reservation

#### Test in Browser
1. Navigate to: `http://your-app-url/applications/application/primary/new`
2. Select land use (e.g., Residential)
3. Fill in some form fields
4. Wait for autosave (check browser console for "Draft saved successfully")
5. Note the file number displayed in UI (e.g., NPFN-2025-RES-00001)

#### Verify in Database
```sql
SELECT TOP 5 
    file_number, 
    land_use_type, 
    status, 
    draft_id,
    reserved_at,
    expires_at
FROM file_number_reservations
ORDER BY reserved_at DESC;
```

**Expected:**
- One row with `status = 'reserved'`
- `expires_at` = 3 days from now
- `draft_id` matches your draft

### Step 4: Test Full Workflow

#### A. Create and Submit Application
```
1. Create new draft → File number reserved (NPFN-2025-RES-00001)
2. Complete form with all required fields
3. Add buyers
4. Submit application
5. Check database:
```

```sql
-- Reservation should be marked as used
SELECT * FROM file_number_reservations WHERE file_number = 'NPFN-2025-RES-00001';
-- Expected: status='used', application_id populated

-- Application should have the file number
SELECT id, applicationID, np_fileno FROM mother_applications WHERE np_fileno = 'NPFN-2025-RES-00001';
-- Expected: 1 row with your application data
```

#### B. Create and Delete Draft
```
1. Create new draft → File number reserved (NPFN-2025-RES-00002)
2. Delete the draft
3. Check database:
```

```sql
SELECT * FROM file_number_reservations WHERE file_number = 'NPFN-2025-RES-00002';
-- Expected: status='released', released_at timestamp
```

### Step 5: Setup Automated Cleanup

#### Option A: Manual Cleanup (On-Demand)
```powershell
# View statistics
php artisan filenumbers:cleanup --stats

# Dry run to see what would be cleaned
php artisan filenumbers:cleanup --dry-run

# Actual cleanup
php artisan filenumbers:cleanup --force
```

#### Option B: Scheduled Cleanup (Recommended)
Add to `app/Console/Kernel.php` in the `schedule()` method:

```php
protected function schedule(Schedule $schedule)
{
    // Clean up expired file number reservations daily at 2 AM
    $schedule->command('filenumbers:cleanup --force')
             ->dailyAt('02:00')
             ->appendOutputTo(storage_path('logs/filenumber-cleanup.log'));
}
```

**Ensure Laravel Scheduler is Running:**

Windows (Task Scheduler):
```powershell
# Create a scheduled task that runs every minute:
$action = New-ScheduledTaskAction -Execute 'php' -Argument 'artisan schedule:run' -WorkingDirectory 'C:\path\to\your\app'
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration ([TimeSpan]::MaxValue)
Register-ScheduledTask -TaskName "Laravel Scheduler" -Action $action -Trigger $trigger
```

Linux (Cron):
```bash
* * * * * cd /path-to-your-app && php artisan schedule:run >> /dev/null 2>&1
```

**Verify Scheduler:**
```powershell
php artisan schedule:list
```

Expected output should include:
```
0 2 * * *  php artisan filenumbers:cleanup --force .... Next Due: 1 day from now
```

## Quick Tests

### Test 1: Check Current Reservations
```powershell
php artisan filenumbers:cleanup --stats
```

**Expected Output:**
```
File Number Reservation Statistics

+-------------------------+-------+
| Metric                  | Value |
+-------------------------+-------+
| Total Reservations      | X     |
| Active Reservations     | X     |
| Used Reservations       | X     |
| Expired Reservations    | 0     |
| Released Reservations   | X     |
| Expiring Soon (< 24h)   | 0     |
+-------------------------+-------+
```

### Test 2: Simulate Expired Reservation
```sql
-- Create a test reservation that's already expired
INSERT INTO file_number_reservations (
    file_number, land_use_type, serial_number, year, status,
    reserved_at, expires_at, reserved_by, created_at, updated_at
) VALUES (
    'NPFN-2025-TEST-99999', 'RESIDENTIAL', 99999, 2025, 'reserved',
    DATEADD(day, -5, GETDATE()), -- 5 days ago
    DATEADD(day, -2, GETDATE()), -- Expired 2 days ago
    1, -- Replace with valid user ID
    GETDATE(),
    GETDATE()
);
```

```powershell
# Check if it's detected
php artisan filenumbers:cleanup --dry-run
```

**Expected:** Shows 1 expired reservation

```powershell
# Clean it up
php artisan filenumbers:cleanup --force
```

**Verify:**
```sql
SELECT status FROM file_number_reservations WHERE file_number = 'NPFN-2025-TEST-99999';
-- Expected: status='expired'
```

### Test 3: Sequential Numbering
Create 3 drafts in sequence and verify they get sequential numbers:

```sql
-- After creating 3 drafts, check:
SELECT file_number, serial_number, reserved_at
FROM file_number_reservations
WHERE land_use_type = 'RESIDENTIAL' AND year = 2025
ORDER BY serial_number DESC;
```

**Expected:**
```
NPFN-2025-RES-00003  |  3  |  2025-10-02 10:30:00
NPFN-2025-RES-00002  |  2  |  2025-10-02 10:25:00
NPFN-2025-RES-00001  |  1  |  2025-10-02 10:20:00
```

## Common Issues & Quick Fixes

### Issue: Migration fails with "table already exists"
```powershell
# Check if table exists
php artisan tinker
```
```php
Schema::connection('sqlsrv')->hasTable('file_number_reservations');
exit
```

**Fix:** If true, the table exists. Migration already ran or manual creation occurred. Skip migration or rollback first:
```powershell
php artisan migrate:rollback --step=1
php artisan migrate
```

### Issue: "Class FileNumberReservationService not found"
**Fix:** Clear autoload cache:
```powershell
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Issue: Reservations not being created
**Check logs:**
```powershell
Get-Content storage\logs\laravel.log -Tail 50 | Select-String "reservation"
```

**Debug in controller:**
```php
// Add to PrimaryFormDraftController@saveDraft after reservation attempt
Log::info('Reservation result', ['reservation' => $reservation]);
```

### Issue: File numbers not sequential
**Check current serials:**
```sql
SELECT 
    lus.land_use_type,
    lus.current_serial,
    MAX(fnr.serial_number) as max_reserved
FROM land_use_serials lus
LEFT JOIN file_number_reservations fnr ON 
    fnr.land_use_type = lus.land_use_type AND 
    fnr.year = lus.year
WHERE lus.year = 2025
GROUP BY lus.land_use_type, lus.current_serial;
```

**Fix if mismatch:**
```sql
UPDATE land_use_serials
SET current_serial = (
    SELECT ISNULL(MAX(serial_number), 0)
    FROM file_number_reservations
    WHERE land_use_type = land_use_serials.land_use_type
    AND year = land_use_serials.year
)
WHERE year = 2025;
```

## Monitoring Commands

```powershell
# Quick stats
php artisan filenumbers:cleanup --stats

# View recent reservations
php artisan tinker
```
```php
\App\Models\FileNumberReservation::latest('reserved_at')->take(10)->get(['file_number', 'status', 'reserved_at']);
exit
```

```sql
-- Recent activity
SELECT TOP 10 
    file_number, 
    land_use_type, 
    status, 
    reserved_at
FROM file_number_reservations
ORDER BY reserved_at DESC;

-- Status breakdown
SELECT status, COUNT(*) as count
FROM file_number_reservations
GROUP BY status;
```

## Next Steps

1. **Run Migration** ✓
2. **Create a test draft** to verify reservation system works
3. **Submit a test application** to verify "used" status
4. **Delete a test draft** to verify "released" status
5. **Setup scheduled cleanup** in Kernel.php
6. **Monitor for 24 hours** to ensure no issues
7. **Review logs** for any errors or warnings

## Support Checklist

If you encounter issues:

- [ ] Check Laravel logs: `storage/logs/laravel.log`
- [ ] Check browser console for JavaScript errors
- [ ] Verify migration ran successfully
- [ ] Check database connection in `.env`
- [ ] Verify `FileNumberReservationService` can be instantiated
- [ ] Check file permissions on `storage/` and `bootstrap/cache/`
- [ ] Run `composer dump-autoload`
- [ ] Clear all caches: `php artisan cache:clear && php artisan config:clear`

## Success Indicators

✅ Migration completed without errors  
✅ New drafts automatically get file numbers  
✅ File numbers are sequential (1, 2, 3...)  
✅ Submitted applications have `status='used'` in reservations  
✅ Deleted drafts have `status='released'` in reservations  
✅ Cleanup command runs without errors  
✅ No duplicate file numbers in production use  

---

**Documentation:** See `FILE_NUMBER_RESERVATION_SYSTEM.md` for comprehensive details.

**Testing:** Use the diagnostic queries and commands above to verify everything works correctly.
