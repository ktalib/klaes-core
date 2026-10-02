# Reservation System - Code Snippets for Integration

## Overview
This document contains exact code snippets to integrate the Serial Number Reservation System with `mls_js.blade.php`. The reservation system is already complete - you just need to wire up a few function calls.

---

## Step 1: Add UI Elements to Modal HTML

### Where: `resources/views/generate_fileno/mlsfno.blade.php` (or commission-fileno-modal-html.blade.php if extracted)

**Find the "Application Type" section** (around line 310-350) and add these elements AFTER it:

```html
<!-- RESERVATION SYSTEM UI ELEMENTS -->
<!-- Add this right after the Application Type section closes -->

<!-- Reservation Status Indicator (hidden by default) -->
<div id="reservationIndicator" class="hidden mb-4"></div>

<!-- Reservation Warning Messages (hidden by default) -->
<div id="reservationWarning" class="hidden mb-4"></div>

<!-- Continue with Serial Number or other fields... -->
```

---

## Step 2: Integrate Reservation with Serial Number Auto-fetch

### Where: `resources/views/generate_fileno/mls_js.blade.php`

### Find: The `fetchNextSerial()` function call (around line 900-1000)

Look for code that fetches the next serial number from the server.

### Replace: Add reservation API call BEFORE fetching serial

**Original code** (example - yours may look different):
```javascript
// Somewhere in fetchNextSerial or similar function
const response = await fetch(`/api/file-numbers/next-serial?prefix=${prefix}&year=${year}&land_use=${landUse}`);
const data = await response.json();
serialNo = data.serial;
```

**New code with reservation:**
```javascript
// TRY to reserve serial first (prevents conflicts)
if (typeof commissionModalReservation !== 'undefined') {
    const reservation = await commissionModalReservation.reserve(prefix, landUse, year);
    
    if (reservation.success) {
        serialNo = reservation.serialNumber;
        console.log('✓ Serial reserved:', serialNo);
    } else {
        // Fallback: fetch without reservation (old method)
        console.warn('Reservation failed, using fallback method');
        const response = await fetch(`/api/file-numbers/next-serial?prefix=${prefix}&year=${year}&land_use=${landUse}`);
        const data = await response.json();
        serialNo = data.serial;
    }
} else {
    // Reservation system not loaded, use old method
    const response = await fetch(`/api/file-numbers/next-serial?prefix=${prefix}&year=${year}&land_use=${landUse}`);
    const data = await response.json();
    serialNo = data.serial;
}
```

---

## Step 3: Confirm Reservation on Successful Submission

### Where: `resources/views/generate_fileno/mls_js.blade.php` 

### Find: The form submission success handler (look for `fetch` call with POST method and `/api/generate-file-number` or similar)

**Original code** (example):
```javascript
const response = await fetch(formAction, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(formData)
});

const result = await response.json();

if (result.success) {
    // Show success message
    alert('File number generated successfully!');
    
    // Close modal
    closeGenerateModal();
    
    // Refresh table or callback
    if (typeof window.commissionFileNoSuccessCallback === 'function') {
        window.commissionFileNoSuccessCallback(result);
    }
}
```

**Add AFTER `if (result.success)` block:**
```javascript
if (result.success) {
    // CONFIRM the reservation so it's marked as "used"
    if (typeof commissionModalReservation !== 'undefined') {
        await commissionModalReservation.confirm();
        console.log('✓ Reservation confirmed');
    }
    
    // Show success message
    alert('File number generated successfully!');
    
    // Close modal
    closeGenerateModal();
    
    // Refresh table or callback
    if (typeof window.commissionFileNoSuccessCallback === 'function') {
        window.commissionFileNoSuccessCallback(result);
    }
}
```

---

## Step 4: Handle Batch Mode (if applicable)

### Where: `resources/views/generate_fileno/mls_js.blade.php` 

### Find: Batch mode handling (look for `batchQuantity` or similar)

**Original code** (example):
```javascript
if (batchMode && batchQuantity > 1) {
    // Calculate serial range
    const startSerial = parseInt(serialNo);
    const endSerial = startSerial + batchQuantity - 1;
    
    previewText = `${code}-${year}-${startSerial} to ${code}-${year}-${endSerial}`;
}
```

**Add reservation for batch:**
```javascript
if (batchMode && batchQuantity > 1) {
    // RESERVE batch serials
    if (typeof commissionModalReservation !== 'undefined') {
        const batchReservation = await commissionModalReservation.reserveBatch(
            prefix, 
            landUse, 
            year, 
            batchQuantity
        );
        
        if (batchReservation.success) {
            const startSerial = batchReservation.startSerial;
            const endSerial = batchReservation.endSerial;
            
            previewText = `${code}-${year}-${startSerial} to ${code}-${year}-${endSerial}`;
            console.log('✓ Batch reserved:', startSerial, '-', endSerial);
        } else {
            console.error('Batch reservation failed:', batchReservation.message);
            // Fallback to old method...
        }
    } else {
        // Old method: Calculate serial range
        const startSerial = parseInt(serialNo);
        const endSerial = startSerial + batchQuantity - 1;
        
        previewText = `${code}-${year}-${startSerial} to ${code}-${year}-${endSerial}`;
    }
}
```

**For batch submission success handler:**
```javascript
if (result.success && batchMode) {
    // CONFIRM batch reservations
    if (typeof commissionModalReservation !== 'undefined') {
        await commissionModalReservation.confirmBatch();
        console.log('✓ Batch reservations confirmed');
    }
    
    // Continue with success logic...
}
```

---

## Step 5: Add to Scheduler (Backend)

### Where: `app/Console/Kernel.php`

### Add this to the `schedule()` method:

```php
protected function schedule(Schedule $schedule)
{
    // ... existing schedules ...

    // Clean up expired file number reservations every 5 minutes
    $schedule->call(function () {
        $service = app(\App\Services\FileNumberReservationService::class);
        $released = $service->releaseExpiredReservations();
        
        if ($released > 0) {
            \Log::info("Released {$released} expired file number reservations");
        }
    })->everyFiveMinutes()->name('cleanup-file-reservations');
}
```

---

## Step 6: Run Migration

### Terminal command:

```bash
php artisan migrate --database=sqlsrv
```

This creates the `file_number_reservations` table.

---

## Testing Checklist

After implementing the above snippets:

1. **Open modal in two browser tabs/windows simultaneously**
2. **Select same prefix and year in both tabs**
3. **Verify each tab shows a DIFFERENT serial number**
4. **Check browser console** - should see:
   - `✓ Serial reserved: 123`
   - `✓ Reservation confirmed` (after submission)
5. **Close one tab without submitting** - reservation should be released
6. **In database**, check `file_number_reservations` table:
   - Should see active reservations with `status = 'pending'`
   - After submission: `status = 'confirmed'`
   - After release: `status = 'released'`

---

## Quick Testing SQL

Check active reservations:
```sql
SELECT * FROM file_number_reservations 
WHERE status = 'pending' 
  AND expires_at > GETDATE()
ORDER BY created_at DESC;
```

Check confirmed reservations:
```sql
SELECT TOP 20 *
FROM file_number_reservations 
WHERE status = 'confirmed'
ORDER BY confirmed_at DESC;
```

Find reservations that expired:
```sql
SELECT * FROM file_number_reservations 
WHERE status = 'pending' 
  AND expires_at < GETDATE()
ORDER BY expires_at DESC;
```

---

## Troubleshooting

### "commissionModalReservation is not defined"
- Check that `commission-modal-reservation-integration.js` is loaded
- View page source and verify script tag exists
- Check browser console for script loading errors

### Reservations not showing in database
- Run migration: `php artisan migrate --database=sqlsrv`
- Check migration status: `php artisan migrate:status --database=sqlsrv`

### Serial numbers still conflicting
- Verify API routes are registered: `php artisan route:list | findstr reservation`
- Check browser Network tab for failed API calls
- Verify `FileNumberReservationService` exists in `app/Services/`

### "Method reserveNextSerial does not exist"
- The service already exists but may need namespace: `\App\Services\FileNumberReservationService`
- Clear cache: `php artisan config:clear; php artisan cache:clear`

---

## Summary

**What you need to do:**

1. ✅ Backend complete (model, service, controller, routes already exist)
2. ✅ Frontend complete (`file-number-reservation.js` exists)
3. ✅ Integration helper created (`commission-modal-reservation-integration.js`)
4. ⏳ **Add 2 UI divs to modal HTML** (Step 1 above)
5. ⏳ **Add 3 function calls to mls_js.blade.php** (Steps 2-4 above)
6. ⏳ **Add scheduler** (Step 5 above)
7. ⏳ **Run migration** (Step 6 above)

**Total time: 15-30 minutes**

---

Last Updated: February 28, 2026
Status: Ready for implementation
