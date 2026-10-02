# File Number Reservation System - Implementation Guide

## Overview
This document describes the file number reservation system implemented to prevent race conditions and duplicate file number assignments in draft applications.

## Problem Statement
Previously, when multiple users created draft applications simultaneously, they could receive the same file number (e.g., `NPFN-2025-RES-00001`), causing conflicts when drafts were submitted. The system lacked a mechanism to:
- Reserve file numbers for draft applications
- Track reserved vs. used numbers
- Release unused numbers after draft expiry/deletion
- Maintain sequential numbering for submitted applications

## Solution Architecture

### Components

#### 1. **Database Table: `file_number_reservations`**
Tracks all file number reservations with comprehensive metadata.

**Key Columns:**
- `file_number` (unique) - Complete file number (e.g., NPFN-2025-RES-00001)
- `land_use_type` - COMMERCIAL, RESIDENTIAL, INDUSTRIAL, MIXED
- `serial_number` - The serial number reserved
- `year` - Year of reservation
- `status` - reserved | used | released | expired
- `draft_id` - Associated draft UUID
- `application_id` - Final application ID when submitted
- `reserved_at` / `expires_at` / `used_at` / `released_at` - Timestamps
- `reserved_by` - User who reserved the number
- `metadata` - Additional JSON metadata

**Indexes:**
- Unique on `file_number`
- Index on `[land_use_type, year, serial_number]`
- Index on `[status, expires_at]` for cleanup queries
- Foreign keys to `users`, `mother_application_draft`, `mother_applications`

#### 2. **Model: `FileNumberReservation`**
Location: `app/Models/FileNumberReservation.php`

**Features:**
- Status constants: `STATUS_RESERVED`, `STATUS_USED`, `STATUS_RELEASED`, `STATUS_EXPIRED`
- Scopes: `reserved()`, `expired()`, `forLandUseYear()`, `used()`
- Helper methods: `markAsUsed()`, `markAsReleased()`, `markAsExpired()`, `extendExpiry()`
- Auto-sets defaults: `reserved_at`, `expires_at` (3 days), `reserved_by`, `client_ip`, `user_agent`

#### 3. **Service: `FileNumberReservationService`**
Location: `app/Services/FileNumberReservationService.php`

**Core Methods:**

##### `reserveFileNumber(string $landUse, int $year, ?string $draftId): array`
Reserves the next available file number for a draft.
- Calculates next available serial considering existing reservations and used numbers
- Creates reservation record with 3-day expiry
- Returns: `['success' => bool, 'file_number' => string, 'serial' => int, 'reservation_id' => int]`

##### `markAsUsed(string $fileNumber, int $applicationId): bool`
Marks a reservation as used when application is submitted.

##### `releaseReservation(string $fileNumber): bool`
Releases a reservation (e.g., when draft is deleted).

##### `cleanupExpiredReservations(): int`
Marks expired reservations as expired (run via cron/command).

##### `getReservationStats(): array`
Returns statistics about reservations (total, reserved, used, expired, etc.).

**Private Helper Methods:**
- `getNextAvailableSerial()` - Calculates next serial considering all sources
- `normalizeLandUse()` - Normalizes land use to standard format
- `getLandUsePrefix()` / `getLandUseCode()` - Get prefix/code for land use
- `generateFileNumber()` - Generates complete file number

#### 4. **Command: `CleanupExpiredFileReservations`**
Location: `app/Console/Commands/CleanupExpiredFileReservations.php`

**Usage:**
```bash
# Show statistics
php artisan filenumbers:cleanup --stats

# Dry run (see what would be cleaned)
php artisan filenumbers:cleanup --dry-run

# Interactive cleanup (with confirmation)
php artisan filenumbers:cleanup

# Force cleanup without confirmation
php artisan filenumbers:cleanup --force

# Verbose output
php artisan filenumbers:cleanup -v
```

**Features:**
- Shows current reservation status breakdown
- Lists expired reservations with details
- Progress bar during cleanup
- Statistics before and after cleanup
- Breakdown by land use type

## Integration Points

### 1. **PrimaryFormController**
Location: `app/Http/Controllers/PrimaryFormController.php`

**Changes:**
- Added constructor dependency injection for `FileNumberReservationService`
- In `store()` method: After successful application creation, marks reservation as used:
  ```php
  if ($npFileNo) {
      $this->reservationService->markAsUsed($npFileNo, $applicationId);
  }
  ```

### 2. **PrimaryFormDraftController**
Location: `app/Http/Controllers/PrimaryFormDraftController.php`

**Changes:**
- Added constructor dependency injection for `FileNumberReservationService`
- In `saveDraft()` method: For new drafts without npFileNo, reserves a file number:
  ```php
  if ($isNewDraft && $npFileNo === '') {
      $reservation = $this->reservationService->reserveFileNumber($landUse, $currentYear, $draft->draft_id);
      if ($reservation['success']) {
          $npFileNo = $reservation['file_number'];
          $formState['np_fileno'] = $npFileNo;
      }
  }
  ```
- In `finalizeDraft()` method: Releases reservation when draft is deleted:
  ```php
  if ($draft->np_file_no) {
      $this->reservationService->releaseReservation($draft->np_file_no);
  }
  ```

## Workflow

### New Draft Creation
1. User starts filling primary application form
2. On first autosave, `PrimaryFormDraftController@saveDraft` is called
3. If new draft and no file number provided:
   - Service reserves next available file number
   - Reservation created with 3-day expiry
   - File number stored in draft's `form_state` and `np_file_no` column
4. Subsequent autosaves update the same draft (no new reservation)

### Draft Submission
1. User submits completed application
2. `PrimaryFormController@store` creates final application record
3. After successful save:
   - Reservation marked as "used"
   - `application_id` linked to reservation
   - `used_at` timestamp recorded
4. Draft finalized (deleted) but reservation remains for audit trail

### Draft Deletion
1. User manually deletes draft
2. `PrimaryFormDraftController@deleteDraft` calls `finalizeDraft()`
3. Reservation released (status → "released")
4. File number becomes available for future use

### Expired Reservation Cleanup
1. Cron job runs daily: `php artisan filenumbers:cleanup --force`
2. Finds reservations where:
   - `status = 'reserved'`
   - `expires_at < now()`
3. Marks them as "expired"
4. Numbers become available for reuse

## Serial Number Calculation

The `getNextAvailableSerial()` method ensures no conflicts by checking:

1. **`land_use_serials` table** - Current highest serial
2. **`file_number_reservations` table** - Highest reserved/used serial
3. **`mother_applications` table** - Highest serial from actual applications (fallback)

Takes `MAX()` of all three sources + 1 as next serial.

Updates `land_use_serials.current_serial` if calculated serial is higher.

## Configuration

### Reservation Expiry
Default: **3 days** (72 hours)

Configured in `FileNumberReservation::EXPIRY_DAYS` constant.

To change:
```php
// app/Models/FileNumberReservation.php
const EXPIRY_DAYS = 5; // Change to 5 days
```

### Cron Schedule
Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Run cleanup daily at 2 AM
    $schedule->command('filenumbers:cleanup --force')
             ->dailyAt('02:00')
             ->appendOutputTo(storage_path('logs/filenumber-cleanup.log'));
}
```

## Database Migration

### Run Migration
```bash
php artisan migrate
```

### Rollback (if needed)
```bash
php artisan migrate:rollback --step=1
```

### Migration File
`database/migrations/2025_10_02_000001_create_file_number_reservations_table.php`

**Creates:**
- `file_number_reservations` table with all columns and indexes
- Unique constraint on `land_use_serials` table: `[land_use_type, year]`

## Testing

### Manual Testing

#### Test 1: New Draft Reservation
```bash
# 1. Navigate to primary application form
# 2. Open browser console
# 3. Fill in land use and other fields
# 4. Wait for autosave
# 5. Check logs:
php artisan tail

# Expected: "File number reserved for new draft" with NPFN-2025-XXX-XXXXX

# 6. Check database:
SELECT * FROM file_number_reservations ORDER BY id DESC;
# Expected: New row with status='reserved', expires_at = +3 days
```

#### Test 2: Application Submission
```bash
# 1. Complete a draft application
# 2. Submit the application
# 3. Check logs for "File number reservation marked as used"
# 4. Check database:
SELECT * FROM file_number_reservations WHERE file_number = 'NPFN-2025-XXX-XXXXX';
# Expected: status='used', application_id populated, used_at timestamp
```

#### Test 3: Draft Deletion
```bash
# 1. Create a draft (gets reserved file number)
# 2. Delete the draft
# 3. Check logs for "File number reservation released"
# 4. Check database:
SELECT * FROM file_number_reservations WHERE file_number = 'NPFN-2025-XXX-XXXXX';
# Expected: status='released', released_at timestamp
```

#### Test 4: Expired Reservations
```bash
# 1. Manually set a reservation to expired in database:
UPDATE file_number_reservations 
SET expires_at = DATEADD(day, -1, GETDATE()) 
WHERE id = 1;

# 2. Run cleanup:
php artisan filenumbers:cleanup --dry-run
# Should show 1 expired reservation

# 3. Run actual cleanup:
php artisan filenumbers:cleanup --force

# 4. Check database:
SELECT * FROM file_number_reservations WHERE id = 1;
# Expected: status='expired'
```

### Query Examples

```sql
-- Active reservations
SELECT * FROM file_number_reservations 
WHERE status = 'reserved' AND expires_at >= GETDATE()
ORDER BY reserved_at DESC;

-- Expired reservations needing cleanup
SELECT * FROM file_number_reservations 
WHERE status = 'reserved' AND expires_at < GETDATE()
ORDER BY expires_at;

-- Reservations by land use type
SELECT land_use_type, status, COUNT(*) as count
FROM file_number_reservations
GROUP BY land_use_type, status
ORDER BY land_use_type, status;

-- Recent activity
SELECT TOP 20 
    file_number, 
    land_use_type, 
    status, 
    reserved_at,
    CASE 
        WHEN status = 'used' THEN used_at
        WHEN status = 'released' THEN released_at
        WHEN status = 'expired' THEN expires_at
        ELSE NULL
    END as action_date
FROM file_number_reservations
ORDER BY reserved_at DESC;

-- Audit trail for specific file number
SELECT 
    fnr.file_number,
    fnr.status,
    fnr.reserved_at,
    fnr.expires_at,
    fnr.used_at,
    u.first_name + ' ' + u.surname as reserved_by_name,
    ma.applicationID as final_app_id
FROM file_number_reservations fnr
LEFT JOIN users u ON fnr.reserved_by = u.id
LEFT JOIN mother_applications ma ON fnr.application_id = ma.id
WHERE fnr.file_number = 'NPFN-2025-RES-00001';
```

## Monitoring & Maintenance

### Daily Checks
```bash
# View reservation statistics
php artisan filenumbers:cleanup --stats

# Check for errors in logs
Get-Content storage\logs\laravel.log | Select-String "FileNumber|reservation" | Select-Object -Last 50
```

### Weekly Maintenance
```bash
# Run cleanup
php artisan filenumbers:cleanup --force

# Check for anomalies (reservations older than 7 days still in reserved status)
```

### Monthly Audit
```sql
-- Count reservations by status
SELECT status, COUNT(*) as count, 
       MIN(reserved_at) as oldest, 
       MAX(reserved_at) as newest
FROM file_number_reservations
GROUP BY status;

-- Check for gaps in serial numbers (potential issues)
WITH serials AS (
    SELECT land_use_type, year, serial_number
    FROM file_number_reservations
    WHERE status IN ('reserved', 'used')
    UNION
    SELECT land_use_type, year, current_serial as serial_number
    FROM land_use_serials
)
SELECT land_use_type, year, 
       MIN(serial_number) as min_serial,
       MAX(serial_number) as max_serial,
       COUNT(*) as used_count,
       MAX(serial_number) - MIN(serial_number) + 1 - COUNT(*) as gaps
FROM serials
GROUP BY land_use_type, year
HAVING MAX(serial_number) - MIN(serial_number) + 1 - COUNT(*) > 0;
```

## Troubleshooting

### Issue: Duplicate file numbers assigned
**Symptom:** Two applications with same NPFN

**Solution:**
1. Check if reservations table is being used:
   ```sql
   SELECT * FROM file_number_reservations WHERE file_number = 'NPFN-2025-XXX-XXXXX';
   ```
2. Verify `getNextAvailableSerial()` logic is running
3. Check for transaction conflicts in logs
4. Ensure unique constraint exists on `file_number` column

### Issue: Reservation not created for new draft
**Symptom:** Draft has no file number

**Solution:**
1. Check logs for "Failed to reserve file number"
2. Verify `FileNumberReservationService` is injected in controller
3. Check database connection permissions
4. Verify `file_number_reservations` table exists

### Issue: Cleanup not running automatically
**Symptom:** Old expired reservations accumulate

**Solution:**
1. Verify cron schedule is registered in `Kernel.php`
2. Check if Laravel scheduler is running: `php artisan schedule:list`
3. Ensure cron is configured on server:
   ```bash
   * * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
   ```
4. Run manual cleanup: `php artisan filenumbers:cleanup --force`

### Issue: Sequential numbering broken
**Symptom:** File numbers skip numbers or go backwards

**Solution:**
1. Check `land_use_serials.current_serial` vs actual usage:
   ```sql
   SELECT 
       lus.land_use_type,
       lus.current_serial as table_serial,
       (SELECT MAX(serial_number) FROM file_number_reservations 
        WHERE land_use_type = lus.land_use_type AND year = lus.year) as max_reservation_serial
   FROM land_use_serials lus;
   ```
2. If mismatch, manually correct:
   ```sql
   UPDATE land_use_serials
   SET current_serial = (
       SELECT MAX(serial_number) 
       FROM file_number_reservations 
       WHERE land_use_type = land_use_serials.land_use_type 
       AND year = land_use_serials.year
   )
   WHERE year = 2025;
   ```

## Performance Considerations

- **Database Locks:** `lockForUpdate()` used in `getNextAvailableSerial()` prevents race conditions but may cause brief lock contention
- **Index Usage:** All queries leverage indexes on `[status, expires_at]` and `[land_use_type, year, serial_number]`
- **Cleanup Impact:** Cleanup command processes rows one at a time with progress bar; minimal impact on performance
- **Reservation Lifespan:** 3-day expiry balances user convenience with number availability

## Future Enhancements

1. **Real-time cleanup:** Use database job scheduler or Laravel queue for immediate expired reservation handling
2. **Reservation extension:** Allow users to extend expiry before it expires
3. **Notification system:** Alert users when reservation is about to expire
4. **Dashboard:** Admin panel to view/manage reservations
5. **Audit log:** Detailed history of all reservation state changes
6. **Concurrent draft protection:** Prevent same user from having multiple active reservations

## Related Files

- Migration: `database/migrations/2025_10_02_000001_create_file_number_reservations_table.php`
- Model: `app/Models/FileNumberReservation.php`
- Service: `app/Services/FileNumberReservationService.php`
- Command: `app/Console/Commands/CleanupExpiredFileReservations.php`
- Controller: `app/Http/Controllers/PrimaryFormController.php` (updated)
- Controller: `app/Http/Controllers/PrimaryFormDraftController.php` (updated)

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Review this documentation
3. Query database for reservation state
4. Contact development team with specific error messages and reproduction steps
