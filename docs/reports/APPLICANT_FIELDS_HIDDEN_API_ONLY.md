# Applicant Fields Hidden - API Auto-fill Only

## Implementation Summary
Modified the applicant section to hide all input fields (title, names, corporate details) since they are auto-populated from the API. Only document upload sections (passport photo and RC document) remain visible and editable.

## Changes Made

### File 1: `resources/views/primaryform/applicant.blade.php`

#### A. **Individual Applicant Section** (Lines ~8-50)

**Before:**
- Visible input fields for: Title (dropdown), First Name, Middle Name, Surname
- Users could manually edit these fields

**After:**
```blade
<!-- API Auto-filled Information (Read-only Display) -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex items-start mb-2">
        <svg>...</svg>
        <div class="flex-1">
            <h3>Applicant Information (Auto-filled from API)</h3>
            <p>The information below was automatically populated from the selected file number.</p>
        </div>
    </div>
    
    <!-- Hidden fields for form submission -->
    <input type="hidden" id="applicantName" name="first_name">
    <input type="hidden" id="applicantMiddleName" name="middle_name">
    <input type="hidden" id="applicantSurname" name="surname">
    
    <!-- Hidden select for title (needed for JavaScript) -->
    <select id="applicantTitle" name="applicant_title" class="hidden">
        <option value="Mr.">Mr.</option>
        <!-- ... all title options ... -->
    </select>
    
    <!-- Read-only display -->
    <div class="mt-3">
        <label>Name of Applicant</label>
        <input type="text" id="applicantNamePreview" 
               class="...cursor-not-allowed" readonly disabled>
    </div>
</div>
```

**Key Changes:**
- ✅ All input fields converted to `type="hidden"`
- ✅ Title dropdown still exists but with `class="hidden"` (needed for JS population)
- ✅ Added blue info banner explaining auto-fill
- ✅ Added read-only `applicantNamePreview` field showing full name
- ✅ Passport photo upload section remains **VISIBLE**

#### B. **Corporate Applicant Section** (Lines ~119-145)

**Before:**
- Visible input fields for: Corporate Name, RC Number
- Users could manually edit these fields

**After:**
```blade
<!-- API Auto-filled Information (Read-only Display) -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex items-start mb-2">
        <svg>...</svg>
        <div class="flex-1">
            <h3>Corporate Information (Auto-filled from API)</h3>
            <p>The information below was automatically populated from the selected file number.</p>
        </div>
    </div>
    
    <!-- Hidden fields for form submission -->
    <input type="hidden" id="corporateName" name="corporate_name">
    <input type="hidden" id="rcNumber" name="rc_number">
    
    <!-- Read-only display -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
        <div>
            <label>Name of Corporate Body</label>
            <div id="corporateNameDisplay" class="...">-</div>
        </div>
        <div>
            <label>RC Number</label>
            <div id="rcNumberDisplay" class="...">-</div>
        </div>
    </div>
</div>

<!-- RC Document Upload Section (VISIBLE) -->
<h3>📄 Upload RC Document *</h3>
```

**Key Changes:**
- ✅ Corporate name and RC number converted to `type="hidden"`
- ✅ Added blue info banner explaining auto-fill
- ✅ Added display-only divs (`corporateNameDisplay`, `rcNumberDisplay`)
- ✅ RC document upload section remains **VISIBLE**

### File 2: `public/js/primaryform/global-file-numbers-autofill.js`

#### Updated `autoFillApplicantFields()` Function

**Added Display Field Updates:**

```javascript
setTimeout(() => {
    // Fill individual fields
    if (fileData.applicant_type?.toLowerCase() === 'individual') {
        updateFormField('applicantTitle', fileData.applicant_title);
        updateFormField('applicantName', fileData.first_name);
        updateFormField('applicantMiddleName', fileData.middle_name);
        updateFormField('applicantSurname', fileData.surname);
        
        // ✅ NEW: Update the name preview display
        const fullName = [fileData.applicant_title, fileData.first_name, 
                          fileData.middle_name, fileData.surname]
            .filter(Boolean)
            .join(' ')
            .toUpperCase();
        const namePreview = document.getElementById('applicantNamePreview');
        if (namePreview) {
            namePreview.value = fullName;
        }
    }
    
    // Fill corporate fields
    else if (fileData.applicant_type?.toLowerCase() === 'corporate') {
        updateFormField('corporateName', fileData.corporate_name);
        updateFormField('rcNumber', fileData.rc_number);
        
        // ✅ NEW: Update the display fields
        const corporateNameDisplay = document.getElementById('corporateNameDisplay');
        const rcNumberDisplay = document.getElementById('rcNumberDisplay');
        if (corporateNameDisplay) {
            corporateNameDisplay.textContent = fileData.corporate_name || '-';
        }
        if (rcNumberDisplay) {
            rcNumberDisplay.textContent = fileData.rc_number || '-';
        }
    }
}, 100);
```

**Key Additions:**
- ✅ Populates `applicantNamePreview` with full name for individual applicants
- ✅ Populates `corporateNameDisplay` and `rcNumberDisplay` for corporate applicants
- ✅ Uses `.filter(Boolean)` to remove null/undefined values
- ✅ Converts name to uppercase for consistency

## Visual Changes

### Individual Applicant Section

**Before:**
```
┌─────────────────────────────────────────────┐
│ Title: [Dropdown ▼]                        │
│ First Name: [Input Field]                  │
│ Middle Name: [Input Field]                 │
│ Surname: [Input Field]                     │
│ Name of Applicant: [Auto-generated]        │
│ [Photo Upload]                             │
└─────────────────────────────────────────────┘
```

**After:**
```
┌─────────────────────────────────────────────┐
│ ℹ️ Applicant Information (Auto-filled)     │
│ The information below was automatically     │
│ populated from the selected file number.    │
│                                             │
│ Name of Applicant:                          │
│ [MR. JOHN MICHAEL DOE] (read-only)        │
│                                             │
│ 📸 Upload Applicant Passport Photo         │
│ [Upload Interface]                          │
└─────────────────────────────────────────────┘
```

### Corporate Applicant Section

**Before:**
```
┌─────────────────────────────────────────────┐
│ Corporate Name: [Input Field]              │
│ RC Number: [Input Field]                   │
│ Upload RC Document: [Upload]               │
└─────────────────────────────────────────────┘
```

**After:**
```
┌─────────────────────────────────────────────┐
│ ℹ️ Corporate Information (Auto-filled)     │
│ The information below was automatically     │
│ populated from the selected file number.    │
│                                             │
│ Corporate Name    │ RC Number               │
│ [ABC LTD]         │ [RC123456]             │
│                                             │
│ 📄 Upload RC Document *                    │
│ [Upload Interface]                          │
└─────────────────────────────────────────────┘
```

## Form Submission Behavior

### Hidden Fields Submitted:
```php
// Individual
$_POST['applicant_title']  // e.g., "Mr."
$_POST['first_name']       // e.g., "JOHN"
$_POST['middle_name']      // e.g., "MICHAEL"
$_POST['surname']          // e.g., "DOE"

// Corporate
$_POST['corporate_name']   // e.g., "ABC LIMITED"
$_POST['rc_number']        // e.g., "RC123456"
```

### Visible Fields Submitted:
```php
// Individual - Passport Photo
$_FILES['applicant_passport']

// Corporate - RC Document
$_FILES['id_document']
```

## User Workflow

### Step 1: Select File Number
```
User selects: ST-RES-2025-0001
```

### Step 2: API Auto-populates Data
```
Hidden fields populated:
✅ applicantTitle = "Mr."
✅ applicantName = "JOHN"
✅ applicantMiddleName = "MICHAEL"
✅ applicantSurname = "DOE"

Display updated:
✅ applicantNamePreview shows "MR. JOHN MICHAEL DOE"
```

### Step 3: User Uploads Documents
```
User action required:
📸 Upload passport photo (JPEG/PNG, max 5MB)
```

### Step 4: Form Submission
```
Backend receives:
✅ All applicant details (from hidden fields)
✅ Uploaded passport photo
✅ Other form data (address, property details, etc.)
```

## Benefits

### 1. **Data Integrity**
- ✅ Prevents manual editing errors
- ✅ Ensures API data is used exactly as stored
- ✅ Reduces duplicate/inconsistent entries

### 2. **User Experience**
- ✅ Cleaner, simpler interface
- ✅ Clear indication data is from API
- ✅ Focuses user attention on required uploads
- ✅ Faster form completion

### 3. **Workflow Efficiency**
- ✅ Eliminates redundant data entry
- ✅ Reduces form validation errors
- ✅ Streamlines document collection process

## Testing Checklist

### ✅ Individual Applicant
- [ ] Select file with `applicant_type='Individual'`
- [ ] Verify hidden fields populated correctly
- [ ] Check `applicantNamePreview` shows full name
- [ ] Confirm passport photo upload works
- [ ] Test form submission includes all hidden fields

### ✅ Corporate Applicant
- [ ] Select file with `applicant_type='Corporate'`
- [ ] Verify hidden fields populated correctly
- [ ] Check display divs show corporate name and RC number
- [ ] Confirm RC document upload works
- [ ] Test form submission includes all hidden fields

### ✅ Multiple Owners
- [ ] Select file with `applicant_type='Multiple'`
- [ ] Verify multiple owners section displays
- [ ] Check owners can still add/remove rows
- [ ] Test document uploads for each owner

### ✅ Edge Cases
- [ ] Test with missing/null applicant data
- [ ] Verify behavior when file selection changes
- [ ] Check form validation with empty passport photo
- [ ] Test backward compatibility with old form submissions

## Browser Console Verification

**Expected Console Output:**
```
📝 Set applicant type: Individual
📝 Updated SELECT applicantTitle: Mr → Mr. (normalized match)
📝 Updated INPUT applicantName: JOHN
📝 Updated INPUT applicantMiddleName: MICHAEL
📝 Updated INPUT applicantSurname: DOE
📝 Populated individual applicant fields - Title: Mr, Name: JOHN
```

**Check Name Preview:**
```javascript
document.getElementById('applicantNamePreview').value
// Should output: "MR. JOHN MICHAEL DOE"
```

**Check Hidden Fields:**
```javascript
document.getElementById('applicantTitle').value    // "Mr."
document.getElementById('applicantName').value     // "JOHN"
document.getElementById('applicantSurname').value  // "DOE"
```

## Related Files
- ✅ `resources/views/primaryform/applicant.blade.php` - Modified
- ✅ `public/js/primaryform/global-file-numbers-autofill.js` - Modified
- 📄 `resources/views/primaryform/partials/steps/step1-basic.blade.php` - Uses applicant partial
- 📄 `app/Http/Controllers/*` - Backend controllers receiving form data

## Rollback Instructions

If needed, revert changes by:
1. Remove `class="hidden"` from title select
2. Change hidden inputs back to visible text inputs
3. Remove the blue info banners
4. Remove display-only divs (`corporateNameDisplay`, `rcNumberDisplay`)
5. Restore original grid layout for input fields

## Version History
- **v1.0** (2025-10-11): Initial implementation
  - Hidden all applicant input fields
  - Kept document uploads visible
  - Added display-only fields for user reference
  - Updated JavaScript to populate display fields

---
**Status:** ✅ COMPLETE  
**Last Updated:** October 11, 2025  
**Files Modified:** 2 (applicant.blade.php, global-file-numbers-autofill.js)  
