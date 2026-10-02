# User-Visible Gap-Filling Implementation

## Overview

The file number reservation system now displays **visible notifications** to users when they receive a gap-filled file number (one that was previously reserved but expired or released).

## User Experience

### When Opening a New Draft

**Scenario 1: Regular Sequential Number**
```
User opens form → Saves draft
System assigns: ST-RES-2025-8
(No special notification - this is the next sequential number)
```

**Scenario 2: Gap-Filled Number**
```
User opens form → Saves draft
System detects gap at serial 5
System assigns: ST-RES-2025-5

User sees:
┌──────────────────────────────────────────────────────────┐
│ ℹ️ 📋 File Number Assigned: ST-RES-2025-5               │
│                                                           │
│ 🔄 Gap-Filled                                            │
│ This file number was previously reserved but expired     │
│ after 3 days of inactivity.                             │
│                                                           │
│ ℹ️ You are filling a gap to maintain sequential         │
│    numbering. The next new file number will be          │
│    ST-RES-2025-8.                                        │
│                                                           │
│ ✓ This ensures no gaps in the final file number         │
│   sequence!                                              │
│                                                           │
│ [X Dismiss]                                              │
└──────────────────────────────────────────────────────────┘
```

File Number Field:
```
File Number: [ST-RES-2025-5] [🔄 Gap-Filled]
```

---

## Implementation Details

### Backend Changes

#### 1. `FileNumberReservationService.php`

**Modified `getNextAvailableSerial()` to return gap metadata:**

```php
private function getNextAvailableSerial(string $landUse, int $year): array
{
    // Check for gaps first
    $availableGap = FileNumberReservation::forLandUseYear($landUse, $year)
        ->whereIn('status', ['released', 'expired'])
        ->orderBy('serial_number', 'asc')
        ->first();
    
    if ($availableGap) {
        return [
            'serial' => $availableGap->serial_number,
            'is_gap_filled' => true,
            'gap_reason' => '...',
            'next_new_serial' => $this->calculateNextNewSerial(...),
        ];
    }
    
    // No gaps, return next sequential
    return [
        'serial' => $nextSerial,
        'is_gap_filled' => false,
        'gap_reason' => null,
        'next_new_serial' => null,
    ];
}
```

**Added `calculateNextNewSerial()` helper:**
```php
private function calculateNextNewSerial(string $landUse, int $year): int
{
    // Returns what the next NEW number would be (ignoring gaps)
    // Used to show users: "Next new number will be ST-RES-2025-8"
}
```

**Modified `reserveFileNumber()` to return gap info:**
```php
return [
    'success' => true,
    'file_number' => $fileNumber,
    'serial' => $nextSerial,
    'is_gap_filled' => $isGapFilled,      // NEW
    'gap_reason' => $gapReason,            // NEW
    'next_new_serial' => $nextNewSerial,   // NEW
];
```

#### 2. `PrimaryFormDraftController.php`

**Modified `saveDraft()` to include gap info in response:**

```php
// Reserve file number if new draft
if ($isNewDraft && $npFileNo === '') {
    $reservation = $this->reservationService->reserveFileNumber(...);
    
    if ($reservation['success']) {
        if ($reservation['is_gap_filled'] ?? false) {
            $gapFillingInfo = [
                'is_gap_filled' => true,
                'file_number' => $npFileNo,
                'reason' => $reservation['gap_reason'],
                'next_new_number' => 'ST-...-' . $reservation['next_new_serial'],
            ];
        }
    }
}

// Include in response
$response = [
    'success' => true,
    // ... other fields
];

if ($gapFillingInfo) {
    $response['gap_filling_info'] = $gapFillingInfo;  // NEW
}

return response()->json($response);
```

---

### Frontend Changes

#### 1. `draft-autosave.js`

**Added gap-filling notification handling:**

```javascript
.then((data) => {
    console.log('[DraftAutosave] Draft saved successfully', {
        draft_id: data.draft_id,
        version: data.version,
        np_file_no: data.np_file_no,
        is_gap_filled: data.gap_filling_info?.is_gap_filled || false,  // NEW
    });

    // Show notification if gap-filled
    if (data.gap_filling_info && data.gap_filling_info.is_gap_filled) {
        this.showGapFillingNotification(data.gap_filling_info);  // NEW
    }
    
    // ... rest of save handling
});
```

**Added notification methods:**

```javascript
showGapFillingNotification(gapInfo) {
    // Creates and displays blue notification banner
    // Shows: file number, reason, next new number
    // Auto-dismisses after 15 seconds
    // Has manual dismiss button
}

addGapFillingIndicator(fileNumber) {
    // Adds "🔄 Gap-Filled" badge next to file number input
}

dismissNotification(notification) {
    // Animated dismissal (fade out + slide up)
}

escapeHtml(text) {
    // XSS protection for user-facing text
}
```

#### 2. `gap-filling-notification.js` (Standalone Module)

Provides reusable notification component:

```javascript
export function showGapFillingNotification(gapInfo) {
    // Can be imported and used anywhere
}

export function showGapFillingIndicator(fileNumber) {
    // Standalone indicator for file number fields
}

// jQuery plugin for backward compatibility
$.fn.showGapFillingNotification = function(gapInfo) { ... };
```

---

## Notification Design

### Visual Design

```
┌─────────────────────────────────────────────────────────────┐
│ [i] 📋 File Number Assigned: ST-RES-2025-5            [X]  │
│                                                              │
│     [🔄 Gap-Filled] This file number was previously         │
│     reserved but expired after 3 days of inactivity.        │
│                                                              │
│     ℹ️ You are filling a gap to maintain sequential        │
│        numbering. The next new file number will be          │
│        ST-RES-2025-8.                                        │
│                                                              │
│     ✓ This ensures no gaps in the final file number        │
│       sequence!                                              │
└─────────────────────────────────────────────────────────────┘
```

### Colors & Styling (Tailwind CSS)

- **Background**: `bg-blue-50` (light blue)
- **Border**: `border-l-4 border-blue-500` (left blue accent)
- **Text**: `text-blue-800` (dark blue)
- **Badge**: `bg-blue-200 text-blue-800` (blue badge)
- **Icon**: `text-blue-500` (info icon)

### Behavior

- ✅ Appears immediately after draft save
- ✅ Positioned at top of form (before all inputs)
- ✅ Auto-dismisses after 15 seconds
- ✅ Manual dismiss button (X)
- ✅ Smooth fade-in animation
- ✅ Smooth fade-out animation on dismiss
- ✅ Accessible (ARIA labels, role="alert")

---

## User Benefits

### 1. Transparency
Users **see** when they're filling a gap, not just getting a random out-of-sequence number.

### 2. Understanding
Users understand **why** the number might seem "out of order" compared to recent applications.

### 3. Trust
Users know the system is **intentionally** maintaining sequential numbering, not making mistakes.

### 4. Information
Users know what the **next new number** will be, providing context for future applications.

### 5. Assurance
Users are explicitly told that this **prevents gaps** in the final sequence.

---

## Technical Features

### Race Condition Safety
- Row-level locking on gap detection
- Transaction isolation ensures atomic gap assignment
- Only one user can claim a specific gap

### Performance
- Gap check happens first (fast lookup on indexed status column)
- Minimal overhead (single additional query)
- Notification rendering is client-side (no server delay)

### Accessibility
- ARIA live region announces notification
- Keyboard-accessible dismiss button
- High-contrast colors for visibility
- Clear, readable text

### XSS Protection
- All user-facing text is HTML-escaped
- No direct HTML injection
- Safe innerHTML usage with escapeHtml()

---

## Gap Reasons

### Reason 1: Expired Reservation
```
"This file number was previously reserved but expired after 3 days of inactivity."
```

**When**: Draft was saved but never submitted within 3 days.

### Reason 2: Released Reservation
```
"This file number was previously reserved but the draft was deleted."
```

**When**: Draft was explicitly deleted before submission.

---

## Example Scenarios

### Scenario A: User Sees Notification

**Timeline:**
1. User A reserves ST-RES-2025-5 (draft), expires after 3 days
2. User B submits ST-RES-2025-6
3. User C submits ST-RES-2025-7
4. User D opens new draft → Gets ST-RES-2025-5

**User D sees:**
```
📋 File Number Assigned: ST-RES-2025-5
🔄 Gap-Filled
This file number was previously reserved but expired after 3 days.
Next new number: ST-RES-2025-8
```

### Scenario B: No Notification (Sequential)

**Timeline:**
1. User A submits ST-RES-2025-5
2. User B submits ST-RES-2025-6
3. User C opens new draft → Gets ST-RES-2025-7

**User C sees:**
```
(No notification - regular sequential number)
File Number: ST-RES-2025-7
```

---

## Testing

### Manual Testing Steps

1. **Create expired reservation:**
   ```sql
   INSERT INTO file_number_reservations 
   (file_number, land_use_type, year, serial_number, status, expires_at)
   VALUES ('ST-RES-2025-5', 'Residential', 2025, 5, 'expired', GETDATE());
   ```

2. **Open primary form and save draft**
   - Should get ST-RES-2025-5
   - Should see blue notification
   - Should see gap-filled badge

3. **Verify notification dismisses:**
   - Click X button → should fade out
   - Wait 15 seconds → should auto-dismiss

4. **Verify next number:**
   - Open another draft → should get next gap or sequential number

### Automated Testing

Run the test script:
```bash
sqlcmd -S localhost -d your_database -i test_gap_filling_behavior.sql
```

---

## Configuration

### Notification Duration

Default: 15 seconds (configurable in `draft-autosave.js`)

```javascript
// Auto-dismiss after 15 seconds
setTimeout(() => {
    this.dismissNotification(notification);
}, 15000);  // Change this value
```

### Notification Position

Default: Top of form

```javascript
// Insert at top of form
const targetElement = this.form.parentElement || ...;
targetElement.insertBefore(notification, targetElement.firstChild);
```

---

## Browser Compatibility

- ✅ Chrome/Edge (Chromium)
- ✅ Firefox
- ✅ Safari
- ✅ Modern mobile browsers
- ⚠️ IE11 (requires polyfills for some features)

---

## Future Enhancements

### Potential Features:

1. **User Choice:**
   ```
   [Use ST-RES-2025-5]  [Skip to ST-RES-2025-8]
   ```

2. **Gap History:**
   ```
   Show all available gaps: 5, 12, 18
   ```

3. **Reservation Preview:**
   ```
   Before saving draft, show: "You will get ST-RES-2025-5 (gap-filled)"
   ```

4. **Analytics:**
   - Track gap-filling frequency
   - Monitor draft expiration rates
   - Optimize expiration time based on usage

---

## Summary

The user-visible gap-filling system provides:

✅ **Transparency** - Users see what's happening  
✅ **Understanding** - Users know why  
✅ **Trust** - Users have confidence in the system  
✅ **Information** - Users get useful context  
✅ **Assurance** - Users know gaps are being prevented  

This creates a better user experience while maintaining the technical goal of gap-free sequential numbering!
