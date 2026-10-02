# Property Transaction Modal - Debugging Guide

## Quick Tests

### 1. Test the Modal Directly
Open this URL in your browser:
```
http://your-domain/test_property_modal.html
```

Click the buttons:
- **"Open Property Transaction Modal"** - Should show the modal
- **"Check Modal Element"** - Shows if modal exists in DOM

Watch the console output on the page.

### 2. Check Browser Console (F12)

When you click "Add Transaction Details", you should see these logs:

```
Showing SweetAlert with data: {id: 123, file_number: "...", ...}
SweetAlert result: {isConfirmed: true, ...}
User clicked Add Transaction Details
Checking if openPropertyTransactionModal function exists...
Function exists: function
Calling openPropertyTransactionModal with: {id: 123, ...}
Opening property transaction modal with data: {...}
Modal element found: <div id="property-transaction-dialog">
Modal should now be visible
```

### 3. Common Issues & Solutions

#### Issue: "Modal element not found"
**Solution:** Modal partial not included
```bash
# Check if modal is included in index.blade.php
grep -n "property_transaction_modal" resources/views/fileindexing/index.blade.php
```

#### Issue: "openPropertyTransactionModal function not found"
**Solution:** Script not loading properly
- Clear browser cache (Ctrl+Shift+Delete)
- Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)
- Check if script tag is present in modal file

#### Issue: Modal opens but is invisible
**Solution:** CSS z-index conflict
- Open browser DevTools (F12)
- Inspect element #property-transaction-dialog
- Check computed styles for display, position, z-index
- Ensure z-index: 9999 and display: flex

#### Issue: Alpine.js errors
**Solution:** Alpine not loaded
```javascript
// In console, type:
typeof Alpine
// Should return: "object"

// If undefined, check layout file:
// resources/views/layouts/app.blade.php
// Should have: <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

### 4. Manual Test in Console

Open browser console (F12) and run:

```javascript
// 1. Check if modal exists
const modal = document.getElementById('property-transaction-dialog');
console.log('Modal exists:', modal !== null);

// 2. Check if function exists
console.log('Function exists:', typeof openPropertyTransactionModal === 'function');

// 3. Manually open modal
if (modal) {
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    console.log('Modal should be visible now');
}

// 4. Test with fake data
const testData = {
    id: 1,
    file_number: 'TEST-001',
    file_title: 'Test File',
    plot_number: '123',
    lga: 'Kano Municipal',
    location: 'Test Location'
};

if (typeof openPropertyTransactionModal === 'function') {
    openPropertyTransactionModal(testData);
}
```

### 5. Check File Indexing Response

In browser console, after submitting file indexing, check the AJAX response:

```javascript
// The response should look like:
{
    success: true,
    message: "File indexing created successfully!",
    data: {
        id: 123,
        file_number: "KANGIS-2025-001",
        file_title: "Sample File",
        plot_number: "456",
        tp_no: "TP-789",
        lpkn_no: "LPKN-012",
        lga: "Kano Municipal",
        // ... other fields
    }
}
```

If `data` is missing or null, the modal won't have data to display.

### 6. Force Modal to Show (Temporary Test)

Add this to browser console to force modal open:

```javascript
// Force show modal with inline styles
const modal = document.getElementById('property-transaction-dialog');
if (modal) {
    modal.classList.remove('hidden');
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.right = '0';
    modal.style.bottom = '0';
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    modal.style.zIndex = '999999';
    console.log('Modal forced to show');
}
```

### 7. Check for JavaScript Errors

Look for these common errors in console:

- `Uncaught ReferenceError: openPropertyTransactionModal is not defined`
  → Script not loaded, check modal partial inclusion

- `Cannot read property 'classList' of null`
  → Modal element doesn't exist, check modal partial inclusion

- `Alpine is not defined`
  → Alpine.js not loaded, check layout file

- `$ is not defined` or `jQuery is not defined`
  → jQuery not loaded, check layout file

### 8. Network Tab Check

1. Open DevTools (F12)
2. Go to Network tab
3. Submit file indexing form
4. Look for POST request to `/fileindexing/store`
5. Click on the request
6. Check "Response" tab
7. Verify `data` object contains all expected fields

### 9. Laravel Logs

Check server-side errors:

```bash
# View recent logs
tail -f storage/logs/laravel.log

# Or open in editor
code storage/logs/laravel.log
```

Look for:
- File indexing store errors
- Missing fields in response
- Database connection issues

### 10. Quick Fix Checklist

- [ ] Clear browser cache
- [ ] Hard refresh page (Ctrl+Shift+R)
- [ ] Check browser console for errors
- [ ] Verify modal element exists in DOM
- [ ] Verify openPropertyTransactionModal function exists
- [ ] Check if Alpine.js is loaded (type `Alpine` in console)
- [ ] Check if jQuery is loaded (type `$` in console)
- [ ] Verify SweetAlert2 is loaded (type `Swal` in console)
- [ ] Test modal with test page (test_property_modal.html)
- [ ] Check Network tab for AJAX response data

## Expected Behavior

1. User fills file indexing form
2. User clicks "Create File Index"
3. Form submits via AJAX to `/fileindexing/store`
4. Server returns success with `data` object
5. SweetAlert shows with two buttons
6. User clicks "Add Transaction Details"
7. Console logs show function being called
8. Modal appears with file data prefilled

## Still Not Working?

Run this comprehensive diagnostic:

```javascript
// Paste this entire block into browser console
console.log('=== COMPREHENSIVE MODAL DIAGNOSTIC ===');
console.log('1. Modal Element:', document.getElementById('property-transaction-dialog'));
console.log('2. Function Type:', typeof openPropertyTransactionModal);
console.log('3. Alpine Available:', typeof Alpine !== 'undefined');
console.log('4. jQuery Available:', typeof $ !== 'undefined');
console.log('5. SweetAlert Available:', typeof Swal !== 'undefined');
console.log('6. Modal Classes:', document.getElementById('property-transaction-dialog')?.className);
console.log('7. Modal Style Display:', document.getElementById('property-transaction-dialog')?.style.display);
console.log('8. All Scripts with "property":', 
    Array.from(document.scripts)
        .filter(s => s.innerHTML.includes('property'))
        .map(s => s.innerHTML.substring(0, 100) + '...')
);
console.log('=== END DIAGNOSTIC ===');
```

Copy the output and share it for further debugging.

---

**Last Updated:** October 3, 2025
