# Gap-Filling File Number Strategy

## Overview

The file number reservation system uses a **GAP-FILLING STRATEGY** to ensure **NO GAPS** in the final sequential numbering of applications.

## The Problem

Without gap-filling, expired or released reservations create permanent gaps:

```
ST-RES-2025-1  (reserved → expired) ❌ Missing from mother_applications
ST-RES-2025-2  (used) ✓
ST-RES-2025-3  (used) ✓
ST-RES-2025-4  (reserved → released) ❌ Missing from mother_applications
ST-RES-2025-5  (used) ✓
```

**Result**: Gaps in the sequence (1, 4 missing)

## The Solution: Gap-Filling

When a reservation expires or is released, that serial number becomes **available for reuse**:

```
ST-RES-2025-1  (reserved → expired → reused) ✓
ST-RES-2025-2  (used) ✓
ST-RES-2025-3  (used) ✓
ST-RES-2025-4  (reserved → released → reused) ✓
ST-RES-2025-5  (used) ✓
```

**Result**: Complete sequential numbering (1, 2, 3, 4, 5...)

---

## How It Works

### Step 1: Check for Gaps First

When `getNextAvailableSerial()` is called:

```php
// FIRST: Look for expired/released reservations (gaps)
$availableGap = FileNumberReservation::forLandUseYear($landUse, $year)
    ->whereIn('status', ['released', 'expired'])
    ->orderBy('serial_number', 'asc')  // Fill smallest gap first
    ->lockForUpdate()
    ->first();

if ($availableGap) {
    // Reuse this serial number!
    $availableGap->delete();  // Remove old record
    return $availableGap->serial_number;
}
```

### Step 2: If No Gaps, Get Next Sequential

```php
// SECOND: No gaps available, get next number
$nextSerial = max(
    $currentSerial ?? 0,
    $highestReservedSerial ?? 0,
    $highestApplicationSerial ?? 0
) + 1;
```

---

## Example Timeline

### Scenario: Three Users

**T1: User A saves draft**
```
Action: Reserve file number for draft
Result: ST-RES-2025-1 (status: reserved)

file_number_reservations:
  1 | ST-RES-2025-1 | reserved | draft_id: 100
```

**T2: User B submits application (no draft)**
```
Action: Reserve and immediately use file number
Result: ST-RES-2025-2 (status: used)

file_number_reservations:
  1 | ST-RES-2025-1 | reserved | draft_id: 100
  2 | ST-RES-2025-2 | used     | app_id: 500

mother_applications:
  500 | ST-RES-2025-2 ✓
```

**T3: User A's draft expires (3 days later)**
```
Action: Cleanup command marks as expired
Result: ST-RES-2025-1 (status: expired)

file_number_reservations:
  1 | ST-RES-2025-1 | expired  | draft_id: 100  ← Available for reuse!
  2 | ST-RES-2025-2 | used     | app_id: 500

mother_applications:
  500 | ST-RES-2025-2 ✓
```

**T4: User C submits application**
```
Action: System detects gap at serial 1, reuses it
Result: ST-RES-2025-1 (status: used, new reservation)

file_number_reservations:
  1 | ST-RES-2025-1 | used     | app_id: 501  ← Gap filled!
  2 | ST-RES-2025-2 | used     | app_id: 500

mother_applications:
  500 | ST-RES-2025-2 ✓
  501 | ST-RES-2025-1 ✓  ← No gap in sequence!
```

**Final State: NO GAPS**
```
mother_applications (sorted by np_fileno):
  501 | ST-RES-2025-1 ✓
  500 | ST-RES-2025-2 ✓
  502 | ST-RES-2025-3 ✓ (next one)
```

---

## File Number Format

Each file number follows this pattern:

```
ST-{LAND_USE}-{YEAR}-{SERIAL}

Examples:
  ST-RES-2025-1      (Residential, year 2025, serial 1)
  ST-COM-2025-1      (Commercial, year 2025, serial 1)
  ST-IND-2025-1      (Industrial, year 2025, serial 1)
  ST-MIX-2025-1      (Mixed Use, year 2025, serial 1)
```

Each land use type maintains its **own independent sequence**:

```
Residential: ST-RES-2025-1, ST-RES-2025-2, ST-RES-2025-3...
Commercial:  ST-COM-2025-1, ST-COM-2025-2, ST-COM-2025-3...
Industrial:  ST-IND-2025-1, ST-IND-2025-2, ST-IND-2025-3...
```

---

## Reservation Status Flow

```
┌─────────────┐
│   RESERVED  │  ← Draft saved
└──────┬──────┘
       │
       ├──────→ [Draft submitted] ──→ USED ──→ ✓ mother_applications
       │
       ├──────→ [Draft deleted] ───→ RELEASED ──→ Available for reuse
       │
       └──────→ [3 days pass] ────→ EXPIRED ───→ Available for reuse
```

### Status Meanings

| Status | Description | Can Reuse? |
|--------|-------------|------------|
| `reserved` | Active draft, not submitted | No |
| `used` | Application submitted to mother_applications | No |
| `released` | Draft deleted before submission | **Yes** |
| `expired` | Reserved but never submitted within 3 days | **Yes** |

---

## Gap-Filling Priority

When multiple gaps exist, the system fills them in **ascending order**:

```sql
SELECT * FROM file_number_reservations
WHERE status IN ('released', 'expired')
ORDER BY serial_number ASC  -- Smallest first
LIMIT 1;
```

**Example:**
```
Available gaps: 3, 7, 12
Next reservation gets: 3 (smallest)
```

This ensures:
- Gaps are filled in order
- Lower numbers are prioritized
- Sequence remains as compact as possible

---

## Benefits of Gap-Filling

### ✓ Complete Sequential Numbering
- No missing numbers in mother_applications
- File numbers: 1, 2, 3, 4, 5... (no gaps)

### ✓ Efficient Number Usage
- No wasted reservations
- All reserved numbers eventually get used

### ✓ Clean Audit Trail
- Expired/released reservations are deleted after reuse
- Only active reservations remain in table

### ✓ Predictable Behavior
- Users see sequential numbers
- No confusion about skipped numbers

---

## Database Operations

### When Reusing a Gap

```php
// 1. Find the gap
$gap = FileNumberReservation::where('status', 'expired')
    ->orderBy('serial_number', 'asc')
    ->first();

// 2. Delete old reservation
$gap->delete();

// 3. Create new reservation with same serial
FileNumberReservation::create([
    'file_number' => 'ST-RES-2025-' . $gap->serial_number,
    'serial_number' => $gap->serial_number,  // Reuse!
    'status' => 'reserved',
    'reserved_by' => $userId,
    'draft_id' => $draftId,
    // ...
]);
```

### Concurrent Access Protection

```php
// Row-level locking prevents race conditions
->lockForUpdate()  // Only one process can access at a time
```

---

## Testing Gap-Filling

Run the test script to see gap-filling in action:

```bash
sqlcmd -S localhost -d your_database -i test_gap_filling_behavior.sql
```

This will:
1. Create test reservations
2. Simulate expiration
3. Show gap detection
4. Demonstrate gap-filling
5. Verify no gaps in final sequence

---

## Configuration

### Expiration Time

Default: **3 days**

Located in: `config/filenumber.php`

```php
'reservation_expiry_hours' => 72,  // 3 days
```

### Cleanup Schedule

Default: **Daily at 2:00 AM**

Located in: `app/Console/Kernel.php`

```php
$schedule->command('filenumbers:cleanup')
    ->daily()
    ->at('02:00');
```

---

## Monitoring

### Check for Gaps

```sql
-- Find any missing serials in used reservations
WITH SerialSequence AS (
    SELECT MIN(serial_number) as MinSerial,
           MAX(serial_number) as MaxSerial
    FROM file_number_reservations
    WHERE land_use_type = 'Residential' AND year = 2025 AND status = 'used'
)
SELECT Numbers.n as missing_serial
FROM (
    SELECT MinSerial + ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) - 1 as n
    FROM SerialSequence
    CROSS JOIN master..spt_values
    WHERE ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) <= 
          (SELECT MaxSerial - MinSerial + 1 FROM SerialSequence)
) Numbers
WHERE NOT EXISTS (
    SELECT 1 FROM file_number_reservations
    WHERE serial_number = Numbers.n
    AND land_use_type = 'Residential' 
    AND year = 2025 
    AND status = 'used'
);
```

### View Available Gaps

```sql
SELECT 
    file_number,
    serial_number,
    status,
    DATEDIFF(day, updated_at, GETDATE()) as days_since_expired
FROM file_number_reservations
WHERE status IN ('released', 'expired')
AND land_use_type = 'Residential'
AND year = 2025
ORDER BY serial_number;
```

---

## Frequently Asked Questions

### Q: What happens if a gap is never reused?

**A:** Gaps are always prioritized, so they will be filled before new numbers are assigned. However, if no new applications are submitted, the gap remains until the next application.

### Q: Can I disable gap-filling?

**A:** Yes, modify `getNextAvailableSerial()` to skip the gap-checking logic. However, this will create permanent gaps in your sequence.

### Q: What if two users try to get the same gap simultaneously?

**A:** Row-level locking (`lockForUpdate()`) prevents this. Only one transaction can access the gap at a time.

### Q: How do I verify no gaps exist?

**A:** Run the test script or use the monitoring queries above.

---

## Summary

The gap-filling strategy ensures that your file number sequence in `mother_applications` is **always complete and sequential**, with no missing numbers, regardless of draft expirations or deletions.

**Final Result:**
```
ST-RES-2025-1 ✓
ST-RES-2025-2 ✓
ST-RES-2025-3 ✓
ST-RES-2025-4 ✓
ST-RES-2025-5 ✓
...
```

No gaps. Ever. 🎯
