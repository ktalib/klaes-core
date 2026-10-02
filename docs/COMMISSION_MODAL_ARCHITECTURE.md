# Commission File Number Modal - System Architecture

## Visual Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        USER OPENS MODAL                                 │
│                                                                         │
│  Browser Tab 1                        Browser Tab 2                    │
│  ┌──────────────┐                    ┌──────────────┐                  │
│  │ User: Alice  │                    │ User: Bob    │                  │
│  └──────┬───────┘                    └──────┬───────┘                  │
│         │                                   │                          │
└─────────┼───────────────────────────────────┼──────────────────────────┘
          │                                   │
          │                                   │
          ▼                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                     FRONTEND (Alpine.js + Reservation JS)               │
│                                                                         │
│  Tab 1: Selects COM-2026           Tab 2: Selects COM-2026            │
│  ┌────────────────────┐             ┌────────────────────┐            │
│  │ reserve('COM',     │             │ reserve('COM',     │            │
│  │   'COM', 2026) ────┼─────────────┤   'COM', 2026)    │            │
│  └────────┬───────────┘      ▲      └────────┬───────────┘            │
│           │                  │               │                        │
│           │              PARALLEL            │                        │
│           │              REQUESTS            │                        │
│           │                  │               │                        │
└───────────┼──────────────────┘───────────────┼─────────────────────────┘
            │                                  │
            │                                  │
            ▼                                  ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    API: /api/file-numbers/reserve                       │
│                                                                         │
│  Request 1 arrives                    Request 2 arrives                │
│  ┌────────────────────┐              ┌────────────────────┐           │
│  │ POST /reserve      │              │ POST /reserve      │           │
│  │ {prefix: 'COM',    │              │ {prefix: 'COM',    │           │
│  │  land_use: 'COM',  │              │  land_use: 'COM',  │           │
│  │  year: 2026}       │              │  year: 2026}       │           │
│  └────────┬───────────┘              └────────┬───────────┘           │
│           │                                   │                       │
└───────────┼───────────────────────────────────┼───────────────────────┘
            │                                   │
            ▼                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│            FileNumberReservationService::reserveNextSerial()            │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐  │
│  │                    DB::transaction()                            │  │
│  │                                                                 │  │
│  │  Request 1 (Alice) enters FIRST                                │  │
│  │  ╔════════════════════════════════════╗                        │  │
│  │  ║ LOCK row: COM-2026                 ║                        │  │
│  │  ║ Last serial: 122                   ║                        │  │
│  │  ║ Next serial: 123                   ║                        │  │
│  │  ║ CREATE reservation (serial=123)    ║                        │  │
│  │  ║ UUID: abc-123                      ║                        │  │
│  │  ║ Status: pending                    ║                        │  │
│  │  ║ Expires: 2026-02-28 15:15:00       ║                        │  │
│  │  ╚════════════════════════════════════╝                        │  │
│  │                                                                 │  │
│  │  Request 2 (Bob) WAITS for lock...                             │  │
│  │                  │                                              │  │
│  │                  │ Lock released after Request 1 commits       │  │
│  │                  ▼                                              │  │
│  │  Request 2 enters                                               │  │
│  │  ╔════════════════════════════════════╗                        │  │
│  │  ║ LOCK row: COM-2026                 ║                        │  │
│  │  ║ Last serial: 123 (now reserved)    ║                        │  │
│  │  ║ Next serial: 124                   ║                        │  │
│  │  ║ CREATE reservation (serial=124)    ║                        │  │
│  │  ║ UUID: def-456                      ║                        │  │
│  │  ║ Status: pending                    ║                        │  │
│  │  ║ Expires: 2026-02-28 15:15:00       ║                        │  │
│  │  ╚════════════════════════════════════╝                        │  │
│  │                                                                 │  │
│  └─────────────────────────────────────────────────────────────────┘  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
            │                                   │
            │                                   │
            ▼                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                      RESPONSE BACK TO FRONTEND                          │
│                                                                         │
│  Tab 1 receives:                     Tab 2 receives:                   │
│  ┌────────────────────┐              ┌────────────────────┐            │
│  │ {success: true,    │              │ {success: true,    │            │
│  │  serialNumber: 123,│              │  serialNumber: 124,│            │
│  │  uuid: 'abc-123',  │              │  uuid: 'def-456',  │            │
│  │  expiresIn: 900}   │              │  expiresIn: 900}   │            │
│  └────────┬───────────┘              └────────┬───────────┘            │
│           │                                   │                        │
└───────────┼───────────────────────────────────┼─────────────────────────┘
            │                                   │
            ▼                                   ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          UI UPDATES                                     │
│                                                                         │
│  Tab 1 shows:                        Tab 2 shows:                      │
│  ┌────────────────────┐              ┌────────────────────┐            │
│  │ Preview:           │              │ Preview:           │            │
│  │ COM-2026-123       │              │ COM-2026-124       │            │
│  │                    │              │                    │            │
│  │ ⏱️  Expires in      │              │ ⏱️  Expires in      │            │
│  │    15:00           │              │    15:00           │            │
│  └────────────────────┘              └────────────────────┘            │
│                                                                         │
│           NO CONFLICT!            DIFFERENT SERIALS!                   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Data Flow: Successful Submission

```
Alice submits form with serial 123
           │
           ▼
┌─────────────────────────────────────────┐
│  Frontend: form submission              │
│  ┌───────────────────────────────────┐  │
│  │ 1. POST /api/generate-file-number │  │
│  │ 2. Wait for response              │  │
│  │ 3. If success:                    │  │
│  │    confirm('abc-123')             │  │
│  └───────────┬───────────────────────┘  │
└──────────────┼──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  API: /api/file-numbers/confirm        │
│  ┌───────────────────────────────────┐  │
│  │ UPDATE file_number_reservations   │  │
│  │ SET status = 'confirmed'          │  │
│  │     confirmed_at = NOW()          │  │
│  │ WHERE uuid = 'abc-123'            │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  Database: Reservation marked as USED   │
│                                         │
│  file_number_reservations               │
│  ┌───────────────────────────────────┐  │
│  │ uuid: abc-123                     │  │
│  │ serial_number: 123                │  │
│  │ status: 'confirmed' ✓             │  │
│  │ confirmed_at: 2026-02-28 15:05    │  │
│  └───────────────────────────────────┘  │
│                                         │
│  Serial 123 is now PERMANENTLY USED     │
└─────────────────────────────────────────┘
```

---

## Data Flow: Modal Closed Without Submission

```
Bob closes modal without submitting (serial 124 reserved)
           │
           ▼
┌─────────────────────────────────────────┐
│  Frontend: modal close event            │
│  ┌───────────────────────────────────┐  │
│  │ closeCommissionFileNoModal()      │  │
│  │   └─> release('def-456')          │  │
│  └───────────┬───────────────────────┘  │
└──────────────┼──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  API: /api/file-numbers/release        │
│  ┌───────────────────────────────────┐  │
│  │ UPDATE file_number_reservations   │  │
│  │ SET status = 'released'           │  │
│  │     released_at = NOW()           │  │
│  │ WHERE uuid = 'def-456'            │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  Database: Reservation FREED            │
│                                         │
│  file_number_reservations               │
│  ┌───────────────────────────────────┐  │
│  │ uuid: def-456                     │  │
│  │ serial_number: 124                │  │
│  │ status: 'released' ✓              │  │
│  │ released_at: 2026-02-28 15:10     │  │
│  └───────────────────────────────────┘  │
│                                         │
│  Serial 124 is now AVAILABLE for reuse │
└─────────────────────────────────────────┘
```

---

## Database Schema

```sql
CREATE TABLE file_number_reservations (
    id BIGINT PRIMARY KEY IDENTITY(1,1),
    
    -- Reservation identifier
    reservation_uuid VARCHAR(36) NOT NULL UNIQUE,
    
    -- File number components
    prefix VARCHAR(10) NOT NULL,
    land_use VARCHAR(10) NOT NULL,
    year INT NOT NULL,
    serial_number INT NOT NULL,
    
    -- Tracking
    reserved_by BIGINT NULL,  -- User ID
    session_id VARCHAR(255) NULL,  -- Browser session
    
    -- Status tracking
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
        -- 'pending': Reserved, waiting for confirmation
        -- 'confirmed': Used successfully
        -- 'released': User cancelled/closed modal
        -- 'expired': Timed out
    
    -- Timestamps
    created_at DATETIME2 NOT NULL,
    expires_at DATETIME2 NOT NULL,
    confirmed_at DATETIME2 NULL,
    released_at DATETIME2 NULL,
    
    -- Indexes for performance
    INDEX idx_status_expires (status, expires_at),
    INDEX idx_prefix_year_serial (prefix, year, serial_number),
    
    -- Prevent duplicate reservations
    UNIQUE (prefix, land_use, year, serial_number, status)
        WHERE status = 'pending'
);
```

---

## File Structure

```
klaes/
│
├── app/
│   ├── Models/
│   │   └── FileNumberReservation.php          ✅ EXISTS
│   │
│   ├── Services/
│   │   └── FileNumberReservationService.php   ✅ EXISTS
│   │
│   └── Http/Controllers/Api/
│       └── FileNumberReservationController.php ✅ EXISTS
│
├── database/migrations/
│   └── 2026_02_28_create_file_number_reservations_table.php  ✅ CREATED
│
├── public/js/
│   ├── file-number-reservation.js             ✅ EXISTS
│   └── commission-modal-reservation-integration.js  ✅ CREATED
│
├── resources/views/
│   ├── components/
│   │   ├── commission-fileno-modal-include.blade.php  ✅ CREATED
│   │   └── partials/
│   │       └── reservation-ui-inject.blade.php  ✅ CREATED
│   │
│   └── generate_fileno/
│       ├── mlsfno.blade.php                   ✅ MODIFIED (OP Resettlement)
│       └── mls_js.blade.php                   ⏳ NEEDS INTEGRATION
│
├── routes/
│   └── api.php                                ✅ ROUTES REGISTERED
│
└── docs/
    ├── COMMISSION_FILE_NUMBER_MODAL_USAGE.md           ✅ CREATED
    ├── RESERVATION_SYSTEM_INTEGRATION.md               ✅ CREATED
    ├── RESERVATION_INTEGRATION_CODE_SNIPPETS.md        ✅ CREATED
    ├── COMMISSION_MODAL_IMPLEMENTATION_SUMMARY.md      ✅ CREATED
    └── examples/
        └── commission-modal-usage-example.blade.php    ✅ CREATED
```

---

## Concurrency Protection

### How Database Locking Works:

```php
// FileNumberReservationService.php

DB::transaction(function () {
    // STEP 1: Lock the row (prevents other requests from reading)
    $maxSerial = DB::table('file_number_reservations')
        ->where('prefix', $prefix)
        ->where('year', $year)
        ->lockForUpdate()  // 🔒 ROW-LEVEL LOCK
        ->max('serial_number');
    
    // STEP 2: Calculate next serial (safe - we have the lock)
    $nextSerial = $maxSerial ? $maxSerial + 1 : 1;
    
    // STEP 3: Create reservation (still locked)
    $reservation = FileNumberReservation::create([
        'reservation_uuid' => Str::uuid(),
        'serial_number' => $nextSerial,
        'status' => 'pending',
        // ...
    ]);
    
    // STEP 4: Transaction commits, lock released
    return $reservation;
});

// 🔓 Lock released here - next request can proceed
```

**Timeline:**
```
Time 0ms:  Request A enters transaction, acquires lock
Time 5ms:  Request B enters transaction, WAITS for lock
Time 10ms: Request A finds max=122, reserves 123
Time 15ms: Request A commits, RELEASES lock
Time 16ms: Request B acquires lock
Time 20ms: Request B finds max=123 (includes A's reservation), reserves 124
Time 25ms: Request B commits, releases lock

Result: No conflict! A gets 123, B gets 124.
```

---

## Gap Filling Logic

```php
// Before allocating new serial, check for gaps

// Find any released/expired reservations (gaps)
$gapReservation = FileNumberReservation::where('prefix', $prefix)
    ->where('year', $year)
    ->whereIn('status', ['released', 'expired'])
    ->orderBy('serial_number')
    ->first();

if ($gapReservation) {
    // Reuse the gap!
    return $gapReservation->serial_number;
}

// No gaps found, allocate new serial
$maxSerial = FileNumberReservation::where('prefix', $prefix)
    ->where('year', $year)
    ->max('serial_number');

return $maxSerial + 1;
```

**Example:**
```
Existing: 1, 2, 3, 5, 6  (gap at 4)
          ↑  ↑  ↑  ↑  ↑
          │  │  │  │  └─ confirmed
          │  │  │  └──── confirmed
          │  │  └─────── RELEASED (gap!)
          │  └────────── confirmed
          └───────────── confirmed

Next reservation: Returns 4 (fills the gap)
After that: Returns 7 (no more gaps)
```

---

## Timer & Auto-Extend

```javascript
// When reservation is created:
Reservation expires in: 15:00

// Countdown starts:
15:00
14:59
14:58
...
02:00 ← AUTO-EXTEND TRIGGERS
       │
       └─> POST /api/file-numbers/extend-reservation
           Response: expiresIn = 900 (15 more minutes)
           
15:00  ← Timer resets
14:59
14:58
...
```

**Why 2 minutes threshold?**
- Gives time for slow API calls
- User may be filling form, not abandoned
- Prevents unnecessary reload/re-reserve

---

## Status Lifecycle

```
┌──────────┐
│ PENDING  │  ← Created when user selects prefix/year
└────┬─────┘
     │
     ├─────────────────┐
     │                 │
     ▼                 ▼
┌──────────┐      ┌──────────┐
│CONFIRMED │      │ RELEASED │  ← User closed modal
└──────────┘      └────┬─────┘
                       │
    Form submitted     │  15 min pass
    successfully       │
                       ▼
                  ┌──────────┐
                  │ EXPIRED  │  ← Cleanup job marks as expired
                  └──────────┘

Reusable states: RELEASED, EXPIRED (can be gap-filled)
Final states: CONFIRMED (permanently used)
```

---

## Performance Considerations

### Database Indexes:
```sql
-- Fast lookup of active reservations
INDEX idx_status_expires (status, expires_at)

-- Fast lookup by file number pattern
INDEX idx_prefix_year_serial (prefix, year, serial_number)

-- Unique constraint prevents duplicate pending reservations
UNIQUE (prefix, land_use, year, serial_number, status)
    WHERE status = 'pending'
```

### Query Performance:
```sql
-- Find next serial (uses index):
SELECT MAX(serial_number) 
FROM file_number_reservations 
WHERE prefix = 'COM' 
  AND year = 2026
WITH (UPDLOCK, HOLDLOCK)  -- Lock hint for SQL Server

-- Find gaps (uses index):
SELECT serial_number 
FROM file_number_reservations 
WHERE prefix = 'COM' 
  AND year = 2026 
  AND status IN ('released', 'expired')
ORDER BY serial_number
```

### Cleanup Job:
Runs every 5 minutes to mark expired reservations:
```sql
UPDATE file_number_reservations
SET status = 'expired'
WHERE status = 'pending'
  AND expires_at < GETDATE()
```

---

## Security Considerations

1. **Session-based tracking**: Each browser tab has unique session
2. **UUID-based identification**: Prevents guessing reservation IDs
3. **Expiry enforcement**: Reservations can't be held indefinitely
4. **Status validation**: Can't confirm already-confirmed reservations
5. **User tracking**: All reservations linked to authenticated user

---

End of Architecture Documentation
