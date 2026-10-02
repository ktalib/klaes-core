# RDS Print Template - Manual Entry Fields Update

## Change Summary

The CONSIDERATION, STAMP DUTY, and REGISTRATION FEE fields have been updated to be **manual entry fields** instead of data-driven fields. These fields now display as empty underline fields for users to fill in manually when printing the RDS document.

## Files Modified

**File:** `resources/views/instrument_registration/rds/print.blade.php`

### Change 1: Removed Data Retrieval (Lines ~176)

**Before:**
```php
// Get other details
$consideration = $instrument->consideration ?? $details['consideration'] ?? '';
$stampDuty = $instrument->stamp_duty ?? $details['stamp_duty'] ?? '';
$registrationFee = $instrument->registration_fee ?? $details['registration_fee'] ?? '';
```

**After:**
```php
// Get file number
$fileNumber = $instrument->StFileNo ?? $instrument->fileno ?? $rds->file_number ?? '';

// Note: CONSIDERATION, STAMP DUTY, and REGISTRATION FEE are manual entry fields (not data-driven)
```

### Change 2: Updated Form Fields (Lines ~258-276)

**Before:**
```blade
<!-- Right-aligned fee section - Data-Driven from consideration, stamp_duty, registration_fee -->
<div class="mt-8 space-y-2 max-w-md ml-auto">
    <!-- Consideration -->
    <div class="flex items-baseline gap-2">
        <span class="font-semibold uppercase tracking-wide">CONSIDERATION</span>
        <span class="form-line flex-1">{{ $consideration }}</span>
    </div>

    <!-- Stamp Duty -->
    <div class="flex items-baseline gap-2">
        <span class="font-semibold uppercase tracking-wide">STAMP DUTY:</span>
        <span class="form-line flex-1">{{ $stampDuty }}</span>
    </div>

    <!-- Registration Fee -->
    <div class="flex items-baseline gap-2">
        <span class="font-semibold uppercase tracking-wide">REGISTRATION FEE</span>
        <span class="form-line flex-1">{{ $registrationFee }}</span>
    </div>
</div>
```

**After:**
```blade
<!-- Right-aligned fee section - Manual entry fields -->
<div class="mt-8 space-y-2 max-w-md ml-auto">
    <!-- Consideration -->
    <div class="flex items-baseline gap-2">
        <span class="font-semibold uppercase tracking-wide">CONSIDERATION</span>
        <span class="form-line flex-1"></span>
    </div>

    <!-- Stamp Duty -->
    <div class="flex items-baseline gap-2">
        <span class="font-semibold uppercase tracking-wide">STAMP DUTY:</span>
        <span class="form-line flex-1"></span>
    </div>

    <!-- Registration Fee -->
    <div class="flex items-baseline gap-2">
        <span class="font-semibold uppercase tracking-wide">REGISTRATION FEE</span>
        <span class="form-line flex-1"></span>
    </div>
</div>
```

## Field Specifications

### CONSIDERATION
- **Type:** Manual entry field
- **Display:** Empty underline for user input
- **Purpose:** User fills in the consideration amount/value for the transaction
- **Format:** User responsibility (no auto-formatting applied)

### STAMP DUTY
- **Type:** Manual entry field
- **Display:** Empty underline for user input
- **Purpose:** User fills in the stamp duty amount
- **Format:** User responsibility (no auto-formatting applied)

### REGISTRATION FEE
- **Type:** Manual entry field
- **Display:** Empty underline for user input
- **Purpose:** User fills in the registration fee amount
- **Format:** User responsibility (no auto-formatting applied)

## Printing Behavior

When the RDS document is printed:
1. All party information (Grantor, Grantee, addresses) displays as data-driven (auto-populated)
2. All dates (Dated, From) display as data-driven (auto-populated)
3. File number displays as data-driven (auto-populated)
4. CONSIDERATION, STAMP DUTY, and REGISTRATION FEE display as **empty underlines** for manual entry

Users can write these values in when printing, or fill them in electronically before printing.

## Example RDS Output

```
Dated: 12 day of NOVEMBER 2025

Executed by: KANO STATE GOVERNMENT
Of: GOVERNMENT HOUSE, KANO

The Right of Occupancy No. ST-COM-2025-001234 is Assigned

to: JOHN SMITH
of: 123 MAIN STREET, KANO

From: 12 day of NOVEMBER 2025

                    CONSIDERATION         ___________________________
                    
                    STAMP DUTY:           ___________________________
                    
                    REGISTRATION FEE      ___________________________
```

## Database Impact

- **No database changes required**
- **No migrations needed**
- **Existing data remains unaffected**
- Database fields (if they exist) are simply not queried or displayed

## Use Cases

This approach is ideal when:
1. Consideration, stamp duty, and registration fees are calculated/determined at print time
2. Multiple fee structures or variations exist based on transaction specifics
3. User needs flexibility to adjust fees during document preparation
4. Fees are determined by external parties or processes
5. Document serves as a template where fees are filled in manually

## Testing Checklist

- [ ] Print RDS document - verify CONSIDERATION field is empty
- [ ] Print RDS document - verify STAMP DUTY field is empty
- [ ] Print RDS document - verify REGISTRATION FEE field is empty
- [ ] Verify all other fields display correctly (data-driven fields unchanged)
- [ ] Test with different instrument types
- [ ] Test with various party names and addresses
- [ ] Verify underline fields are appropriately sized for user input
- [ ] Verify no PHP errors when rendering template

## Performance Impact

- **Positive:** Slightly reduced template processing (fewer variables to process)
- **Negative:** None
- **Overall:** Minimal performance improvement

## Related Changes

This change complements the previous updates:
- Address normalization still applies
- Date synchronization still applies
- Party name normalization still applies
- File number display still data-driven

Only the fee/consideration fields are now manual entry instead of data-driven.

## Rollback Procedure

If data-driven consideration, stamp duty, or registration fee fields are needed in the future:

1. Add back the variable retrieval:
   ```php
   $consideration = $instrument->consideration ?? $details['consideration'] ?? '';
   $stampDuty = $instrument->stamp_duty ?? $details['stamp_duty'] ?? '';
   $registrationFee = $instrument->registration_fee ?? $details['registration_fee'] ?? '';
   ```

2. Update the template fields back to display variables:
   ```blade
   <span class="form-line flex-1">{{ $consideration }}</span>
   <span class="form-line flex-1">{{ $stampDuty }}</span>
   <span class="form-line flex-1">{{ $registrationFee }}</span>
   ```

## Notes

- These fields use the `.form-line` CSS class which provides an underline for user input
- Fields are positioned right-aligned (`ml-auto` on parent div)
- All labels remain in UPPERCASE for consistency
- No validation is applied to user input in these fields
