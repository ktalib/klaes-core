# Primary Form Validation TODO

## Overview
This document tracks the validation rules that were temporarily disabled for testing the AJAX form submission functionality. All validations need to be re-implemented after successful AJAX testing.

## File Location
**Controller:** `app/Http/Controllers/PrimaryFormController.php`  
**Line:** ~165 (in the `store()` method)

## Current Status
- ❌ **DISABLED** - Validation commented out for testing
- 🎯 **Target:** Re-enable after AJAX submission works properly

## Validation Rules to Re-implement

### 1. Basic Application Fields
```php
'applicantType' => 'required',
'applicant_title' => 'nullable',
'first_name' => 'nullable',
'middle_name' => 'nullable',
'surname' => 'nullable',
'corporate_name' => 'nullable',
'rc_number' => 'nullable',
```

### 2. Address Information
```php
'address_house_no' => 'nullable',
'owner_street_name' => 'nullable',
'owner_district' => 'nullable',
'owner_lga' => 'nullable',
'owner_state' => 'nullable',
'phone_number' => 'nullable',
'owner_email' => 'nullable|email',
```

### 3. Property Details
```php
'residenceType' => 'nullable',
'units_count' => 'nullable',
'blocks_count' => 'nullable',
'sections_count' => 'nullable',
'plot_size' => 'nullable|string|max:255',
'scheme_no' => 'required|string|max:255',
'property_house_no' => 'nullable|string|max:255',
'property_plot_no' => 'nullable|string|max:255',
'property_street_name' => 'required|string|max:255',
'property_district' => 'nullable|string|max:255',
'property_lga' => 'required|string|max:255',
'property_state' => 'required|string|max:255',
```

### 4. File Number and References
```php
'applied_file_number' => 'nullable|string|max:255',
'selected_file_id' => 'nullable|string|max:255',
'selected_file_type' => 'nullable|string|max:255',
'selected_file_data' => 'nullable|string',
```

### 5. Fee and Payment Information
```php
'application_fee' => 'nullable',
'processing_fee' => 'nullable',
'site_plan_fee' => 'nullable',
'payment_date' => 'nullable',
'receipt_number' => 'nullable',
'comments' => 'nullable',
'commercial_type' => 'nullable',
```

### 6. Document Uploads (FILE VALIDATION - CRITICAL)
```php
'application_letter' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
'building_plan' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
'architectural_design' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
'ownership_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
'survey_plan' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
'id_document' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
```

### 7. Multiple Owners (Conditional)
```php
'multiple_owners_names' => 'nullable|array',
'multiple_owners_address' => 'nullable|array',
'multiple_owners_passport' => 'nullable|array',
'multiple_owners_passport.*' => 'nullable|image|max:5120',
'multiple_owners_email' => 'nullable|array',
'multiple_owners_email.*' => 'nullable|email',
'multiple_owners_phone' => 'nullable|array',
'multiple_owners_phone.*' => 'nullable|string',
'multiple_owners_identification_type' => 'nullable|array',
'multiple_owners_identification_type.*' => 'nullable|string',
'multiple_owners_identification_image' => 'nullable|array',
'multiple_owners_identification_image.*' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png',
```

### 8. Shared Areas
```php
'shared_areas' => 'nullable|array',
'shared_areas.*' => 'nullable|string',
'other_areas_detail' => 'nullable|string|max:500',
```

### 9. Buyer Records
```php
'records' => 'nullable|array',
'records.*.name' => 'required_with:records|string|max:255',
'records.*.unit_no' => 'required_with:records|string|max:100',
'records.*.buyerTitle' => 'required_with:records|string',
'records.*.firstName' => 'required_with:records|string|max:255',
'records.*.middleName' => 'nullable|string|max:255',
'records.*.surname' => 'required_with:records|string|max:255',
'records.*.unitMeasurement' => 'nullable|string|max:100',
```

### 10. CSV Import (EXCLUDED FROM SUBMISSION)
```php
// NOTE: CSV file should NOT be validated in form submission
// It's only for local browser processing to populate buyers list
'csv_file' => 'nullable|file|max:5120|mimes:csv,txt', // REMOVE THIS
```

## Re-implementation Steps

### Step 1: Test AJAX Submission
- ✅ Ensure AJAX form submission works without validation
- ✅ Verify file uploads work correctly
- ✅ Confirm CSV file exclusion works
- ✅ Test success/error response handling

### Step 2: Implement Smart Validation
1. **Required vs Optional Fields**: Review which fields should actually be required
2. **Conditional Validation**: Implement proper conditional rules based on `applicantType`
3. **File Validation**: Ensure file upload validation works with AJAX FormData
4. **Frontend Integration**: Add client-side validation feedback

### Step 3: Error Handling
1. **AJAX Error Response**: Ensure validation errors return proper JSON format
2. **Frontend Display**: Show validation errors in form without page reload
3. **Field Highlighting**: Highlight invalid fields with error messages

### Step 4: Testing Strategy
1. **Valid Submissions**: Test with complete, valid data
2. **Invalid Data**: Test with missing required fields
3. **File Upload Errors**: Test with invalid file types/sizes
4. **Edge Cases**: Test conditional validation scenarios

## Code to Re-enable Validation

Replace this line in `PrimaryFormController.php`:
```php
// TODO: Re-enable validation after testing (see VALIDATION_TODO.md)
// $validated = $request->validate($rules);
```

With:
```php
$validated = $request->validate($rules);
```

## Important Notes

### 🚨 Security Considerations
- **File Upload Security**: Validate file types and sizes properly
- **SQL Injection**: Ensure all inputs are properly sanitized
- **XSS Protection**: Validate and escape all text inputs

### 🎯 AJAX Compatibility
- Validation errors should return JSON format for AJAX requests
- Consider implementing frontend validation to complement backend validation
- Ensure error messages are user-friendly and actionable

### 📁 File Handling
- **CSV Files**: Should NOT be included in form validation (local processing only)
- **Document Uploads**: Must support multiple file types (PDF, JPG, PNG)
- **File Size**: Current limit is 5MB per file

## Timeline
- **Phase 1** (Current): AJAX submission testing without validation
- **Phase 2** (Next): Re-enable basic required field validation
- **Phase 3** (Later): Implement comprehensive validation with error handling
- **Phase 4** (Final): Add frontend validation and user experience improvements

---

**Last Updated:** September 27, 2025  
**Status:** Validation temporarily disabled for AJAX testing  
**Next Action:** Re-enable validation after successful AJAX implementation