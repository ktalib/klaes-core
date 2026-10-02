# Draft Serialization Fix Plan

## Problem Analysis
After reviewing the `PrimaryFormController->store()` method and all form step files, identified critical gaps between what the production form submission expects vs what draft autosave captures.

## Missing/Misaligned Fields in Draft Serialization

### Critical Mismatches Found:

1. **Applicant Type Field Name**
   - Store() expects: `applicantType`
   - Form uses: `applicant_type` 
   - **Impact**: Primary field for conditional logic completely broken

2. **Multiple Owners Array Fields**
   - Store() expects arrays directly: `multiple_owners_names[]`, `multiple_owners_address[]`, etc.
   - Draft may not capture these properly as arrays
   - **Impact**: Multiple owner data lost

3. **Buyer Records Array**
   - Store() expects: `records[0][buyerTitle]`, `records[0][firstName]`, etc.
   - Draft needs to serialize entire nested array structure
   - **Impact**: All buyer list data not saved/restored

4. **Shared Areas Checkbox Array**
   - Store() expects: `shared_areas[]` as array
   - Store() checks for `other_areas_detail` when "other" in array
   - **Impact**: Shared facilities checkboxes not persisting

5. **File Upload Metadata**
   - Store() processes actual files but draft needs metadata for UI state
   - Draft should track: file names, sizes, upload status
   - **Impact**: User loses track of uploaded files during draft

6. **Owner Address Fields**
   - Store() maps: `owner_street_name`, `owner_district`, `owner_lga`, `owner_state`, `owner_email`
   - Form uses these exact names in owner address section
   - **Impact**: Owner address data not saved

7. **Residence Type Field**
   - Store() expects: `residenceType`
   - Form may use: `residential_type`
   - **Impact**: Residential type selection lost

8. **ID Type Field**
   - Store() expects: `idType`
   - Form uses: `identification_type` in some places
   - **Impact**: Identification type selection lost

## Fields Store() Expects (Master List)

### Applicant Information
- `applicantType` (NOT applicant_type!)
- `applicant_title`
- `first_name`, `middle_name`, `surname`
- `corporate_name`, `rc_number`
- `passport` (file upload)

### Multiple Owners Arrays
- `multiple_owners_names[]`
- `multiple_owners_address[]`
- `multiple_owners_passport[]` (file uploads)
- `multiple_owners_email[]`
- `multiple_owners_phone[]`
- `multiple_owners_identification_type[]`
- `multiple_owners_identification_image[]` (file uploads)

### Owner Address
- `address_house_no`
- `owner_street_name` (NOT address_street_name!)
- `owner_district`
- `owner_lga`
- `owner_state`
- `phone_number`
- `owner_email` (NOT email!)
- `idType` (NOT identification_type!)
- `id_document` (file upload)

### Property Details
- `residenceType` (NOT residential_type!)
- `units_count`
- `blocks_count`
- `sections_count`
- `plot_size`
- `scheme_no`
- `property_house_no`
- `property_plot_no`
- `property_street_name`
- `property_district`
- `property_lga`
- `property_state`

### File Number Selection
- `applied_file_number`
- `selected_file_id`
- `selected_file_type`
- `selected_file_data` (JSON string)

### Payment Information
- `application_date`
- `application_fee`
- `processing_fee`
- `site_plan_fee`
- `payment_date`
- `receipt_number`
- `comments`

### Land Use Types
- `land_use`
- `commercial_type`
- `industrial_type`

### Documents (File Uploads)
- `application_letter`
- `building_plan`
- `architectural_design`
- `ownership_document`
- `survey_plan`
- `scan_upload_files[]` (array of files)

### Shared Areas
- `shared_areas[]` (checkbox array)
- `other_areas_detail` (textarea, conditional)

### Buyer Records
- `records[0][buyerTitle]`
- `records[0][firstName]`
- `records[0][middleName]`
- `records[0][surname]`
- `records[0][unit_no]`
- `records[0][landUse]`
- `records[0][unitMeasurement]`

### Generated Fields (Backend)
- `np_fileno` (generated or from UI)

## Fix Strategy

### Phase 1: Fix Field Name Mismatches
1. Ensure `applicant_type` → `applicantType` mapping in serializeForm()
2. Ensure `residential_type` → `residenceType` mapping
3. Ensure `identification_type` → `idType` mapping
4. Ensure address fields use `owner_*` prefix

### Phase 2: Enhanced Array Serialization
1. Properly capture `shared_areas[]` as array
2. Properly capture `records[]` nested array structure
3. Properly capture `multiple_owners_*[]` arrays

### Phase 3: File Upload State Preservation
1. Store file metadata (names, sizes) in `__files` section
2. Show "Previously uploaded: filename.pdf" in UI on restore
3. Clear upload inputs but preserve metadata for reference

### Phase 4: Comprehensive Restoration
1. Update `restoreFormState()` to handle field name mappings
2. Ensure array fields restore to correct inputs
3. Trigger proper events for dynamic sections (buyers, multiple owners)

## Implementation Steps

### Step 1: Create Field Name Mapping Helper
Add to draft-autosave.js before serializeForm():

```javascript
// Field name mappings: formFieldName -> databaseFieldName
const FIELD_NAME_MAPPINGS = {
    'applicant_type': 'applicantType',
    'residential_type': 'residenceType',
    'identification_type': 'idType',
    'email': 'owner_email',
    'address_street_name': 'owner_street_name'
};

// Reverse mappings for restoration: databaseFieldName -> formFieldName
const REVERSE_FIELD_MAPPINGS = Object.fromEntries(
    Object.entries(FIELD_NAME_MAPPINGS).map(([k, v]) => [v, k])
);
```

### Step 2: Update serializeForm() to Use Mappings
Modify the field capture section:

```javascript
const fieldName = el.name;
const mappedName = FIELD_NAME_MAPPINGS[fieldName] || fieldName;

// ... rest of serialization logic but use mappedName for storage
state[mappedName] = value;
```

### Step 3: Update restoreFormState() to Use Reverse Mappings
When looking for elements to restore:

```javascript
Object.entries(state).forEach(([key, value]) => {
    const formFieldName = REVERSE_FIELD_MAPPINGS[key] || key;
    const element = form.querySelector(`[name="${formFieldName}"]`);
    // ... restore logic
});
```

### Step 4: Test All Field Types
Use test_form_diagnostic.html to verify:
1. All expected fields are captured
2. Field names match store() expectations
3. Arrays serialize/deserialize correctly
4. File metadata preserved

## Success Criteria
- [ ] Diagnostic tool shows 0 missing fields
- [ ] All form inputs save to draft on change
- [ ] Draft loads back with all fields populated
- [ ] Buyer rows restore correctly
- [ ] Multiple owner rows restore correctly
- [ ] Shared areas checkboxes restore correctly
- [ ] File upload state shows previous uploads
- [ ] Laravel logs confirm database writes
- [ ] Browser console shows no serialization errors
