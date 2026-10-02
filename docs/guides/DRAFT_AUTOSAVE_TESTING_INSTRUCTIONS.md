# Draft Autosave Testing Instructions

## Changes Made

### 1. Enhanced Field Name Mapping
Added automatic field name translation to ensure draft serialization matches what `PrimaryFormController->store()` expects:

**Mappings Added:**
- `applicant_type` → `applicantType` (form field → database field)
- `residential_type` → `residenceType` 
- `identification_type` → `idType`

**Reverse Mappings for Restoration:**
- `applicantType` → `applicant_type` (database field → form field)
- `residenceType` → `residential_type`
- `idType` → `identification_type`

### 2. Enhanced Logging
Added comprehensive console logging throughout serialization and restoration:
- Total elements being serialized
- Field name mappings applied
- Which fields were captured and their types (array, file, etc.)
- Sample data snapshot
- Restoration progress for each field
- Elements found/not found during restoration

### 3. Improved Element Lookup
Enhanced restoration logic to:
1. Try mapped form field name first
2. Try with array notation `[]` if value is array
3. Fallback to original key name
4. Handle bracket notation for nested arrays

## Testing Steps

### Step 1: Open Form with Console
1. Navigate to: http://your-app-url/applications/application/primary/new
2. Open Browser Developer Tools (F12)
3. Go to **Console** tab
4. Refresh the page

### Step 2: Verify Autosave Initialization
Look for these console messages:
```
[DraftAutosave] Initializing draft autosave system
[DraftAutosave] Bootstrap loaded: {identifier: "...", endpoints: {...}}
```

### Step 3: Fill Out Form Fields
Fill in various fields across all steps:

**Step 1 - Basic Info:**
- Select Applicant Type (Individual/Corporate/Multiple Owners)
- Fill in name fields
- Fill in owner address fields
- Fill in property details (units, blocks, sections, plot size, scheme number)
- Fill in property address
- Select payment details

**Step 2 - Shared Areas:**
- Check some shared area checkboxes
- If you check "Other", fill in the detail textarea

**Step 3 - Documents:**
- Upload at least one file to test file metadata capture

**Step 4 - Buyers:**
- Add 2-3 buyer records manually
- Fill in all buyer fields (title, names, unit number, land use, measurement)

### Step 4: Watch Console During Field Changes
After typing in each field, you should see:
```
[DraftAutosave] Field changed: {fieldName: "first_name", value: "John"}
[DraftAutosave] Queued debounced save (will fire in 2000ms)
```

After 2 seconds of inactivity:
```
[DraftAutosave] Starting form serialization
[DraftAutosave] Total form elements: 150
[DraftAutosave] Serialization complete: {totalKeys: 45, keys: [...], ...}
[DraftAutosave] Attempting to save draft to: /draft/save
[DraftAutosave] Payload: {draft_id: null, identifier: "NPFN-2025-...", ...}
```

Then watch for the successful response:
```
[DraftAutosave] Draft saved successfully! {draft_id: 123, identifier: "..."}
```

### Step 5: Check Laravel Logs
In PowerShell/Terminal, run:
```powershell
Get-Content storage\logs\laravel.log -Tail 50 | Select-String "DraftAutosave"
```

You should see:
```
[2025-XX-XX XX:XX:XX] local.INFO: [DraftAutosave::saveDraft] Received save request
[2025-XX-XX XX:XX:XX] local.INFO: [DraftAutosave::saveDraft] Form state keys: ["applicantType","first_name",...]
[2025-XX-XX XX:XX:XX] local.INFO: [DraftAutosave::saveDraft] Payload size: 5432 bytes
[2025-XX-XX XX:XX:XX] local.INFO: [DraftAutosave::saveDraft] Draft saved successfully
```

### Step 6: Check Database
Query the database to confirm records are saved:

```sql
SELECT TOP 5 
    id, 
    draft_id,
    identifier,
    LEN(form_state) as form_state_size,
    last_saved_at,
    version
FROM mother_application_draft 
ORDER BY last_saved_at DESC;
```

Verify:
- `draft_id` contains a unique draft identifier
- `identifier` contains the NPFN (e.g., "NPFN-2025-RES-00001")
- `form_state_size` is > 0 (indicates JSON data is saved)
- `last_saved_at` is recent

To inspect actual form data:
```sql
SELECT TOP 1 
    identifier,
    JSON_QUERY(form_state) as form_data
FROM mother_application_draft 
ORDER BY last_saved_at DESC;
```

### Step 7: Test Draft Restoration
1. Note the **Draft ID** shown in the top-right corner (e.g., "NPFN-2025-RES-00001")
2. Open a new browser tab or clear the form
3. Navigate back to: http://your-app-url/applications/application/primary/new
4. In the "Load Draft" section, enter the Draft ID
5. Click **Load Draft**

**Watch Console:**
```
[DraftAutosave] Loading draft by identifier: NPFN-2025-RES-00001
[DraftAutosave] Starting form restoration {stateKeys: 45}
[DraftAutosave] Restoring field: applicantType to 1 elements
[DraftAutosave] Restoring field: first_name to 1 elements
...
[DraftAutosave] Restoration complete
```

**Verify Fields Restored:**
- [ ] Applicant type selected correctly
- [ ] All name fields populated
- [ ] Owner address fields populated
- [ ] Property details populated (units, blocks, scheme, addresses)
- [ ] Payment details populated
- [ ] Shared areas checkboxes checked
- [ ] Buyer records restored (correct number of rows, all fields filled)
- [ ] File upload indicators show previously uploaded files

### Step 8: Test Diagnostic Tool
1. Open: `test_form_diagnostic.html` in browser
2. This tool will run automatically on load and show:
   - ✓ Form found (or error if missing)
   - Total form elements
   - Elements by type (table)
   - All named elements (JSON list)
   - Serialized form state (JSON)
   - Comparison with expected database columns
   - Missing fields highlighted in red
   - Extra fields noted

3. Click **Compare with Database Columns** button
4. Review the "Missing Fields" section - should be empty or minimal
5. Any fields listed as "missing (element exists in form)" indicate naming mismatch

## Troubleshooting

### If Draft Not Saving:

**Check Console for Errors:**
```
[DraftAutosave] Error saving draft: {error message}
```

**Common Issues:**
1. **CSRF Token Missing:** Refresh page to regenerate token
2. **Network Error:** Check `/draft/save` route is registered
3. **Serialization Error:** Check console for "serializeForm" errors

**Check Laravel Logs:**
```powershell
Get-Content storage\logs\laravel.log -Tail 100 | Select-String "ERROR"
```

Look for:
- Database connection errors
- JSON encoding errors
- Column name mismatches

### If Draft Not Loading:

**Check Console:**
```
[DraftAutosave] No elements found for key: someField
```
This indicates field name mismatch between saved state and form HTML.

**Check Database:**
```sql
SELECT identifier, form_state 
FROM mother_application_draft 
WHERE identifier = 'YOUR-DRAFT-ID';
```

Verify `form_state` contains JSON data (not NULL, not empty).

### If Some Fields Not Saving:

**Use Diagnostic Tool:**
1. Open `test_form_diagnostic.html`
2. Click **List All Form Elements**
3. Check if the missing field appears in the elements list
4. Note the exact `name` attribute of the missing field
5. Check if field name matches expected database column name
6. If mismatch, add to FIELD_NAME_MAPPINGS in draft-autosave.js

**Example Fix:**
If form has `<input name="email">` but store() expects `owner_email`:

```javascript
const FIELD_NAME_MAPPINGS = {
    'applicant_type': 'applicantType',
    'residential_type': 'residenceType',
    'identification_type': 'idType',
    'email': 'owner_email', // ADD THIS
};

// Also add reverse mapping:
const REVERSE_FIELD_MAPPINGS = {
    'applicantType': 'applicant_type',
    'residenceType': 'residential_type',
    'idType': 'identification_type',
    'owner_email': 'email', // AND THIS
};
```

## Expected Outcomes

### ✅ Success Indicators:
1. Console shows serialization with 40+ keys captured
2. Console shows successful save response with draft_id
3. Laravel logs show draft saved successfully
4. Database has record in `mother_application_draft` table
5. Draft loads back with all fields populated
6. Buyer rows restore with correct count and data
7. Checkboxes restore checked state
8. File upload section shows "Previously uploaded: filename.pdf"

### ❌ Failure Indicators:
1. Console errors during serialization
2. Network errors (500/404) when saving
3. Laravel logs show SQL errors or JSON encoding errors
4. Database has NULL or empty `form_state`
5. Draft loads but fields are empty
6. Wrong number of buyer rows restored
7. Checkboxes don't restore checked state

## Next Steps After Testing

1. **If all tests pass:** Document any additional field mappings needed and update FIELD_NAME_MAPPINGS
2. **If tests fail:** Share:
   - Console output (copy full log)
   - Laravel log excerpts (last 100 lines)
   - Database query results (form_state sample)
   - Screenshots of form before/after load
3. **If partial success:** Identify specifically which fields work vs don't work and document patterns

## Files Modified

- `public/js/draft-autosave.js` - Added field name mappings and enhanced logging
- `test_form_diagnostic.html` - Diagnostic tool (NEW)
- `DRAFT_SERIALIZATION_FIX_PLAN.md` - Implementation plan (NEW)
- This file - Testing instructions (NEW)

## Contact for Issues

If you encounter issues during testing, provide:
1. Browser console output (full log from page load)
2. Laravel log excerpt: `Get-Content storage\logs\laravel.log -Tail 200`
3. Database query: `SELECT TOP 1 * FROM mother_application_draft ORDER BY last_saved_at DESC`
4. Specific steps to reproduce the issue
