# Draft Autosave Debugging Guide

## Changes Made

### Backend Logging (`app/Http/Controllers/PrimaryFormDraftController.php`)
1. Added logging at the start of `saveDraft()` to show when the endpoint is called
2. Added logging after form_state validation to show what data is being processed
3. Added logging after `$draft->save()` to confirm database write
4. Added logging on successful completion
5. Enhanced error logging with line numbers and file paths

### Frontend Logging (`public/js/draft-autosave.js`)
1. Added logging in `handleFieldChange()` to track field changes
2. Added logging in `queueDebouncedSave()` to show when debounce timer starts
3. Added logging in `saveDraft()` to show:
   - When save is initiated
   - The payload being sent
   - Response status codes
   - Success/failure details

## How to Troubleshoot

### 1. Open Browser Developer Tools
- Press F12
- Go to Console tab
- Look for messages starting with `[DraftAutosave]`

### 2. Check if Fields Are Being Detected
When you type in any form field, you should see:
```
[DraftAutosave] Field changed: {name: "first_name", type: "text"}
[DraftAutosave] Queueing debounced save in 3s
```

If you DON'T see this, the event listeners are not attached properly.

### 3. Check if Save is Being Triggered
After 3 seconds of inactivity, you should see:
```
[DraftAutosave] Debounced save timer fired
[DraftAutosave] saveDraft called {trigger: "debounced", hasPendingChanges: true}
[DraftAutosave] Built payload for save {...}
```

If you see "Skipping auto-save - no pending changes", it means `hasPendingChanges` is not being set.

### 4. Check Network Request
- Go to Network tab in DevTools
- Look for a POST request to `/draft/save`
- Check the request payload
- Check the response

### 5. Check Laravel Logs
```powershell
Get-Content storage\logs\laravel.log -Tail 100 | Select-String "DraftAutosave"
```

You should see:
```
[DraftAutosave] saveDraft called
[DraftAutosave] Processing draft save
[DraftAutosave] Draft saved to database
[DraftAutosave] Draft save completed successfully
```

## Common Issues & Solutions

### Issue: No field change events detected
**Solution**: The form element with id `primaryApplicationForm` doesn't exist or hasn't loaded yet.
- Check if the form is rendered
- Check if draft-autosave.js is loaded AFTER the form HTML

### Issue: Endpoint returns 419 (CSRF token mismatch)
**Solution**: 
- Ensure `<meta name="csrf-token">` exists in the page head
- Check if the token is being included in fetch headers

### Issue: Endpoint returns 500
**Solution**: Check Laravel logs for the exact error:
```powershell
Get-Content storage\logs\laravel.log -Tail 50
```

### Issue: Database column errors
**Solution**: Run the SQL upgrade script:
```sql
-- Execute: database_scripts/PRIMARY_DRAFT_AUTOSAVE_UPGRADE.sql
```

### Issue: form_state is empty
**Solution**: 
- Check if `serializeForm()` is finding form elements
- Add this to browser console:
```javascript
document.getElementById('primaryApplicationForm').elements
```

### Issue: Auto-save never triggers
**Solution**:
- Check if `autoSaveFrequency` is set properly
- Check if `startAutoSaveTimer()` is being called
- Look for console message: `[DraftAutosave] Starting auto-save timer`

## Manual Test

Open browser console and run:
```javascript
// Force a manual save
window.PrimaryDraftAutosave.manualSave({flash: true});

// Check current state
console.log('Draft State:', window.PrimaryDraftAutosave.state);
console.log('Endpoints:', window.PrimaryDraftAutosave.endpoints);

// Manually serialize form
const formData = window.PrimaryDraftAutosave.serializeForm();
console.log('Form Data:', formData);
```

## Database Verification

Check if drafts are being saved:
```sql
-- Check latest draft records
SELECT TOP 10
    id,
    draft_id,
    np_file_no,
    progress_percent,
    last_saved_at,
    LEN(CAST(form_state AS NVARCHAR(MAX))) as form_state_size
FROM mother_application_draft
ORDER BY last_saved_at DESC;
```

## Next Steps if Still Not Working

1. **Verify Routes**:
```powershell
php artisan route:list | Select-String "draft"
```

2. **Clear Cache**:
```powershell
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

3. **Check Database Connection**:
```powershell
php artisan tinker
# Then run:
DB::connection('sqlsrv')->getPdo();
```

4. **Test Direct Database Write**:
```powershell
php artisan tinker
# Then run:
use App\Models\MotherApplicationDraft;
$draft = new MotherApplicationDraft();
$draft->form_state = ['test' => 'value'];
$draft->progress_percent = 10;
$draft->last_completed_step = 1;
$draft->save();
```

## Expected Console Output (Working System)

When everything is working, you should see this sequence:

1. **On Page Load**:
```
[DraftAutosave] Draft ready
```

2. **When Typing**:
```
[DraftAutosave] Field changed: {name: "first_name", type: "text"}
[DraftAutosave] Queueing debounced save in 3s
```

3. **3 Seconds Later**:
```
[DraftAutosave] Debounced save timer fired
[DraftAutosave] saveDraft called {trigger: "debounced", hasPendingChanges: true}
[DraftAutosave] Built payload for save {draft_id: null, form_state_keys: [...], payload_size: 2456}
[DraftAutosave] Save response received {status: 200, ok: true}
[DraftAutosave] Draft saved successfully {draft_id: "...", version: 1, np_file_no: "..."}
[DraftAutosave] Save operation completed
```

4. **In Laravel Logs**:
```
[DraftAutosave] saveDraft called
[DraftAutosave] Processing draft save
[DraftAutosave] Draft saved to database {draft_id: "..."}
[DraftAutosave] Draft save completed successfully
```
