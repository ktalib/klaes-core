# Primary Form Testing Guide

## Quick Test Procedure

### 1. Open the Form
Navigate to: `/primaryform` or the primary application form page

### 2. Open Browser Console
- **Chrome/Edge:** Press `F12` or `Ctrl+Shift+I`
- **Firefox:** Press `F12` or `Ctrl+Shift+K`
- Go to the **Console** tab

### 3. Select a File Number
- Click on the "Select Primary File Number" dropdown at the top
- Choose any file from the list
- **Watch the console** - you should see logs like:
  ```
  ✓ File selected: [file number]
  ✓ Populated hidden fields
  ✓ Updated applicant information
  ```

### 4. Verify Hidden Fields
In the console, type:
```javascript
console.log({
  np_fileno: document.getElementById('np_fileno').value,
  fileno: document.getElementById('fileno').value,
  land_use: document.getElementById('land_use').value,
  applicant_type: document.getElementById('applicant_type').value,
  tracking_id: document.getElementById('tracking_id').value,
  primary_file_id: document.getElementById('primary_file_id').value
});
```

**All values should be populated** (not empty strings).

### 5. Complete the Form
- **Step 1:** Verify applicant info is auto-filled, complete address fields
- **Step 2:** Add shared areas (optional)
- **Step 3:** Upload documents (optional)
- **Step 4:** Add at least ONE buyer with:
  - Title (Mr., Mrs., etc.)
  - First Name
  - Surname
  - Unit Number
  - Land Use (should auto-populate from main form)
- **Step 5:** Review summary and click Submit

### 6. Check Submission
After clicking Submit, you should see:
- Loading indicator
- Success message: "Primary application submitted successfully"
- Redirect or confirmation

### 7. Verify in Database

Run this PHP script:
```bash
php c:\Users\Administrator\Documents\app\comprehensive_form_check.php
```

Or run these SQL queries directly:

```sql
-- Get the latest application
SELECT TOP 1 
    id, 
    np_fileno, 
    fileno, 
    land_use, 
    applicant_type,
    tracking_id,
    primary_file_id,
    first_name,
    surname,
    corporate_name,
    scheme_no,
    property_street_name,
    NoOfUnits,
    created_at
FROM mother_applications 
ORDER BY id DESC;

-- Get buyers for that application (replace [ID] with the application id)
SELECT 
    id,
    buyer_name, 
    unit_no, 
    land_use,
    buyer_title,
    created_at
FROM buyer_list 
WHERE application_id = [ID];

-- Get file indexing (replace [ID] with the application id)
SELECT 
    id,
    main_application_id,
    file_number,
    tracking_id,
    land_use_type,
    file_title,
    created_at
FROM file_indexings 
WHERE main_application_id = [ID];
```

---

## Expected Results

### ✅ Success Indicators:
1. **mother_applications table:**
   - New record created
   - `tracking_id` is NOT NULL
   - `primary_file_id` is NOT NULL
   - `np_fileno` matches selected file
   - `fileno` matches selected file
   - `land_use` is populated
   - `applicant_type` is populated
   - Address fields populated
   - Property fields populated
   - `NoOfUnits`, `NoOfBlocks`, `NoOfSections` have values

2. **buyer_list table:**
   - One or more buyer records created
   - `application_id` matches mother_applications.id
   - Each buyer has `buyer_name`, `unit_no`, and `land_use`

3. **file_indexings table:**
   - One record created
   - `main_application_id` matches mother_applications.id
   - `file_number` matches the selected file
   - `tracking_id` is populated
   - `land_use_type` matches application land use

---

## Troubleshooting

### Problem: Hidden fields are empty
**Solution:**
1. Make sure you selected a file from the dropdown
2. Check browser console for JavaScript errors
3. Verify `global-file-numbers-autofill.js` is loaded
4. Try refreshing the page and selecting again

### Problem: Validation errors on submit
**Solution:**
1. Ensure file number is selected first
2. Complete all required fields (marked with *)
3. Add at least one buyer with all required fields
4. Check browser console for specific validation messages

### Problem: tracking_id is NULL in database
**Solution:**
1. Check if the ST API response includes tracking_id
2. Verify JavaScript is extracting it correctly
3. Check the `selected_file_data` hidden field value
4. May need to update `global-file-numbers-autofill.js` to extract tracking_id

### Problem: No buyers saved
**Solution:**
1. Verify buyer records in browser console before submit:
   ```javascript
   console.log(document.querySelectorAll('.buyer-row').length);
   ```
2. Check that each buyer has required fields filled
3. Review Laravel logs for buyer insertion errors
4. Ensure `land_use` is populated for each buyer

### Problem: No file indexing created
**Solution:**
1. Check Laravel logs: `storage/logs/laravel.log`
2. Look for "Failed to create file indexing" error
3. Verify file_indexings table exists and is accessible
4. Check database permissions

---

## Quick Diagnostic Commands

### Check if columns exist:
```bash
php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); \$cols = DB::connection('sqlsrv')->select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ''mother_applications'' AND COLUMN_NAME IN (''tracking_id'', ''primary_file_id'')'); foreach(\$cols as \$c) echo \$c->COLUMN_NAME . PHP_EOL;"
```

### Check latest application:
```bash
php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); \$app = DB::connection('sqlsrv')->table('mother_applications')->orderBy('id', 'desc')->first(); echo 'ID: ' . \$app->id . ', NP: ' . \$app->np_fileno . ', Tracking: ' . (\$app->tracking_id ?? 'NULL') . PHP_EOL;"
```

### Check buyer count:
```bash
php -r "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); \$count = DB::connection('sqlsrv')->table('buyer_list')->count(); echo 'Total buyers: ' . \$count . PHP_EOL;"
```

---

## Test Data Example

Use this as a reference for a complete test submission:

**File Selection:**
- Select any file from dropdown (e.g., "ST-RES-2025-1 | RES-1992-4131")

**Step 1 - Basic Info:**
- Applicant info should auto-fill
- House No: 123
- Street: TEST STREET
- District: TEST DISTRICT
- LGA: Select any
- State: Select any
- Phone: 08012345678
- Email: test@example.com
- Scheme Number: TEST-001
- Property Street: PROPERTY STREET
- Property LGA: Select any
- Property State: Select any
- Units: 10
- Blocks: 2
- Sections: 5

**Step 4 - Buyers:**
- Title: Mr.
- First Name: John
- Surname: Doe
- Unit Number: A-101
- Land Use: Should auto-populate (e.g., Residential)

**Submit and verify all three tables are updated.**

---

## Success Checklist

Before marking as complete, verify:

- [ ] Form loads without errors
- [ ] File dropdown populates with options
- [ ] Selecting file auto-fills applicant info
- [ ] All hidden fields populated (check console)
- [ ] Can navigate through all 5 steps
- [ ] Can add buyers in step 4
- [ ] Submit button works
- [ ] Success message appears
- [ ] New record in mother_applications
- [ ] tracking_id and primary_file_id NOT NULL
- [ ] Buyers saved to buyer_list
- [ ] File indexing created
- [ ] No errors in Laravel logs
- [ ] No errors in browser console

---

## Contact for Issues

If you encounter persistent issues:
1. Capture browser console output
2. Check Laravel logs
3. Run comprehensive_form_check.php
4. Document the exact steps that cause the issue
5. Note any error messages
