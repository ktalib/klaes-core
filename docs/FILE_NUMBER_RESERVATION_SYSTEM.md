# File Number Reservation System - Race Condition Prevention

## Problem Statement

When multiple users open the Commission File Number modal simultaneously, they could see the same serial number in the real-time preview, leading to:

1. **Duplicate File Numbers**: Both users try to submit with the same serial
2. **Serial Number Gaps**: When one submission fails, the serial is skipped permanently
3. **Database Conflicts**: Concurrent INSERT operations causing errors

## Solution: Serial Number Reservation System

A robust reservation system that pre-allocates serial numbers to individual users, ensuring each user gets a unique serial.

---

## How It Works

### 1. **Modal Opens → Reserve Serial**

When a user opens the Commission modal:

```javascript
// JavaScript (automatically handled)
const response = await fetch('/api/file-numbers/reserve', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
    },
    body: JSON.stringify({
        prefix: 'RES',
        land_use: 'RESIDENTIAL',
        year: 2026
    })
});

const data = await response.json();
// data.reservation_uuid = "550e8400-e29b-41d4-a716-446655440000"
// data.serial_number = 1234
// data.expires_in_seconds = 900 (15 minutes)
```

### 2. **Serial is Reserved Temporarily**

- The serial is locked to this user for 15 minutes
- Other users requesting at the same time get different serials
- Database transaction with locking ensures no race conditions

### 3. **User Submits → Confirm Reservation**

When the form is submitted:

```javascript
// Confirm the reservation
await fetch('/api/file-numbers/confirm-reservation', {
    method: 'POST',
    body: JSON.stringify({
        reservation_uuid: reservationUuid,
        generated_file_number: 'RES-2026-1234'
    })
});
```

### 4. **User Cancels → Release Reservation**

If the user closes the modal or cancels:

```javascript
// Release back to the pool
await fetch('/api/file-numbers/release-reservation', {
    method: 'POST',
    body: JSON.stringify({
        reservation_uuid: reservationUuid
    })
});
```

---

## Architecture

### Database Table: `file_number_reservations`

```sql
CREATE TABLE file_number_reservations (
    id BIGINT PRIMARY KEY IDENTITY(1,1),
    reservation_uuid VARCHAR(36) UNIQUE NOT NULL,
    prefix VARCHAR(20) NOT NULL,
    land_use VARCHAR(20) NOT NULL,
    year INT NOT NULL,
    serial_number INT NOT NULL,
    session_id VARCHAR(100),
    reserved_by BIGINT,  -- user_id
    reserved_by_name VARCHAR(255),
    reserved_by_ip VARCHAR(50),
    reserved_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'reserved',
    confirmed_at DATETIME,
    generated_file_number VARCHAR(255),
    metadata TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    
    INDEX idx_prefix_year_serial (prefix, year, serial_number),
    INDEX idx_status_expires (status, expires_at),
    INDEX idx_expires_at (expires_at)
);
```

### Reservation States

| Status | Description |
|--------|-------------|
| `reserved` | Serial is temporarily allocated to a user |
| `confirmed` | User successfully generated the file number |
| `released` | User cancelled; serial returned to pool |
| `expired` | Reservation timed out; serial returned to pool |

---

## API Endpoints

### Reserve Single Serial
```http
POST /api/file-numbers/reserve
Content-Type: application/json

{
    "prefix": "RES",
    "land_use": "RESIDENTIAL",
    "year": 2026
}

Response:
{
    "success": true,
    "data": {
        "reservation_uuid": "550e8400-...",
        "serial_number": 1234,
        "expires_at": "2026-02-28T15:30:00Z",
        "expires_in_seconds": 900
    }
}
```

### Reserve Batch Serials
```http
POST /api/file-numbers/reserve-batch
Content-Type: application/json

{
    "prefix": "RES",
    "land_use": "RESIDENTIAL",
    "year": 2026,
    "quantity": 10
}

Response:
{
    "success": true,
    "data": {
        "reservation_uuids": ["uuid1", "uuid2", ...],
        "serial_numbers": [1234, 1235, 1236, ...],
        "start_serial": 1234,
        "end_serial": 1243,
        "quantity": 10
    }
}
```

### Confirm Reservation
```http
POST /api/file-numbers/confirm-reservation
Content-Type: application/json

{
    "reservation_uuid": "550e8400-...",
    "generated_file_number": "RES-2026-1234"
}
```

### Release Reservation
```http
POST /api/file-numbers/release-reservation
Content-Type: application/json

{
    "reservation_uuid": "550e8400-..."
}
```

### Extend Reservation
```http
POST /api/file-numbers/extend-reservation
Content-Type: application/json

{
    "reservation_uuid": "550e8400-...",
    "minutes": 10
}
```

### Check Reservation Status
```http
GET /api/file-numbers/reservation-status/{uuid}

Response:
{
    "success": true,
    "data": {
        "reservation_uuid": "550e8400-...",
        "serial_number": 1234,
        "status": "reserved",
        "is_active": true,
        "is_expired": false,
        "expires_in_seconds": 847
    }
}
```

---

## Gap Prevention

The system actively prevents and fills gaps in serial numbers:

### Finding Gaps
```http
GET /api/file-numbers/find-gaps?prefix=RES&year=2026&limit=10

Response:
{
    "success": true,
    "data": {
        "gaps": [5, 17, 23, 45],
        "count": 4
    }
}
```

### Automatic Gap Filling

When reserving serials, the system:
1. Checks for expired reservations
2. Releases them back to the pool
3. Identifies gaps in the sequence
4. Allocates gaps first before incrementing

This ensures continuous serial sequences without permanent gaps.

---

## Expiration & Cleanup

### Automatic Expiration

Reservations automatically expire after **15 minutes** by default. Expired reservations are released back to the pool.

### Manual Cleanup (Scheduled)

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Clean up expired reservations every 5 minutes
    $schedule->command('reservations:cleanup --force')
             ->everyFiveMinutes()
             ->withoutOverlapping();
}
```

### Manual Cleanup (Command Line)

```bash
# Show what would be cleaned up (dry run)
php artisan reservations:cleanup --dry-run

# Clean up expired reservations
php artisan reservations:cleanup

# Force cleanup without confirmation
php artisan reservations:cleanup --force
```

---

## Race Condition Scenarios Solved

### Scenario 1: Two Users Same Time
**Before Reservation System:**
- ❌ User A opens modal → sees serial 100
- ❌ User B opens modal → sees serial 100  
- ❌ User A submits → gets 100
- ❌ User B submits → ERROR (duplicate) OR gets 101 (gap at 100)

**With Reservation System:**
- ✅ User A opens modal → reserves serial 100
- ✅ User B opens modal → reserves serial 101
- ✅ User A submits → confirms 100
- ✅ User B submits → confirms 101
- ✅ No conflicts, no gaps!

### Scenario 2: User Opens Then Abandons
**Before:**
- ❌ User sees serial 100 but never submits
- ❌ Serial 100 might be skipped permanently (gap)

**With Reservations:**
- ✅ User reserves serial 100
- ✅ User abandons modal (closes without submitting)
- ✅ After 15 minutes, reservation expires
- ✅ Serial 100 is released back to pool
- ✅ Next user can get serial 100 (no gap)

### Scenario 3: Batch Generation
**Before:**
- ❌ User requests 5 file numbers
- ❌ Another user might reserve same range
- ❌ Partial success/failures create gaps

**With Reservations:**
- ✅ User reserves serials 100-104 atomically
- ✅ No other user can get these  
- ✅ If user cancels, ALL 5 are released together
- ✅ No partial gaps

---

## Integration with Existing Code

### FileNumberController Updates

The `store()` method now verifies reservations:

```php
public function store(Request $request)
{
    // Verify reservation if provided
    if ($request->has('reservation_uuid')) {
        $reservation = FileNumberReservation::where('reservation_uuid', $request->reservation_uuid)->first();
        
        if (!$reservation || !$reservation->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Reservation expired or invalid. Please refresh and try again.'
            ], 400);
        }
        
        // Use the reserved serial number
        $serialNo = $reservation->serial_number;
        
        // ... generate file number ...
        
        // Confirm the reservation
        $reservation->confirm($mlsfNo);
    }
    
    // ... rest of the code ...
}
```

### Frontend Integration

The modal JavaScript automatically:
1. Requests a reservation when modal opens
2. Displays the reserved serial in preview
3. Shows countdown timer for expiration
4. Sends reservation UUID with form submission
5. Releases reservation if user cancels

No manual intervention needed!

---

## Monitoring & Debugging

###View Active Reservations

```sql
-- See who has what reserved
SELECT 
    reservation_uuid,
    prefix + '-' + CAST(year AS VARCHAR) + '-' + RIGHT('0000' + CAST(serial_number AS VARCHAR), 4) AS preview,
    reserved_by_name,
    DATEDIFF(SECOND, GETDATE(), expires_at) AS seconds_remaining,
    status
FROM file_number_reservations
WHERE status = 'reserved' AND expires_at > GETDATE()
ORDER BY expires_at;
```

### Check for Gaps

```sql
-- Find missing serials for RES-2026
WITH Serials AS (
    SELECT serial_number 
    FROM file_number_reservations
    WHERE prefix = 'RES' AND year = 2026 AND status = 'confirmed'
    UNION
    SELECT CAST(SUBSTRING(mlsfNo, CHARINDEX('-', mlsfNo, CHARINDEX('-', mlsfNo) + 1) + 1, 4) AS INT)
    FROM fileNumber
    WHERE mlsfNo LIKE 'RES-2026-%'
)
SELECT n AS missing_serial
FROM (SELECT TOP 1000 ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n FROM sys.objects) nums
WHERE n NOT IN (SELECT serial_number FROM Serials)
AND n < (SELECT MAX(serial_number) FROM Serials);
```

---

## Performance Considerations

### Database Indexes
- Composite index on `(prefix, year, serial_number)` for fast lookups
- Index on `(status, expires_at)` for cleanup queries
- UUID index for reservation lookups

### Transaction Locking
- Uses `lockForUpdate()` to prevent concurrent reads during serial allocation
- Minimal lock duration (< 100ms typically)
- Automatic retry on deadlock (up to 5 times)

### Cleanup Frequency
- Scheduled cleanup every 5 minutes
- Cleanup takes < 1 second for typical workload
- No impact on active users

---

## Testing the System

### Test Concurrent Reservations

```bash
# Terminal 1
curl -X POST http://localhost/api/file-numbers/reserve \
  -H "Content-Type: application/json" \
  -d '{"prefix":"RES","land_use":"RESIDENTIAL","year":2026}'

# Terminal 2 (run immediately)
curl -X POST http://localhost/api/file-numbers/reserve \
  -H "Content-Type: application/json" \
  -d '{"prefix":"RES","land_use":"RESIDENTIAL","year":2026}'

# Should get different serial numbers!
```

### Test Expiration

```bash
# Reserve a serial
curl -X POST http://localhost/api/file-numbers/reserve ...
# Note the reservation_uuid

# Wait 16 minutes or manually expire in database

# Try to confirm
curl -X POST http://localhost/api/file-numbers/confirm-reservation \
  -d '{"reservation_uuid":"...","generated_file_number":"RES-2026-1234"}'

# Should fail with expiration error
```

---

## Migration Steps

### 1. Run the Migration
```bash
php artisan migrate
```

### 2. Register the Service Provider
Already included in `App\Services\FileNumberReservationService`

### 3. Schedule Cleanup
Add to `app/Console/Kernel.php`:
```php
$schedule->command('reservations:cleanup --force')->everyFiveMinutes();
```

### 4. Test
- Open multiple browsers/incognito windows
- Open Commission modal in each
- Verify each gets a different serial number

---

## Troubleshooting

### Issue: Reservations not expiring
**Solution**: Ensure scheduled task is running
```bash
php artisan schedule:work
# or set up cron job for: * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

### Issue: Gaps still appearing
**Solution**: Run manual cleanup
```bash
php artisan reservations:cleanup --force
```

### Issue: Users getting "expired reservation" errors
**Solution**: Increase `RESERVATION_DURATION_MINUTES` in the model
```php
const RESERVATION_DURATION_MINUTES = 20; // Increase to 20 minutes
```

---

## Summary

✅ **Prevents Race Conditions**: Each user gets unique serial  
✅ **Prevents Gaps**: Expired reservations are recycled  
✅ **Fair Distribution**: First-come-first-served queue  
✅ **Automatic Cleanup**: No manual intervention needed  
✅ **Backwards Compatible**: Works with existing code  
✅ **Minimal Performance Impact**: Fast locks + indexes  
✅ **Audit Trail**: Full history of who reserved what  

**Result**: Rock-solid file number generation with zero conflicts!

---

**Last Updated**: February 28, 2026  
**Version**: 1.0.0  
**Author**: KLAES Development Team
