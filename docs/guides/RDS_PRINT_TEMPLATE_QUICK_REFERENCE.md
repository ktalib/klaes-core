# RDS Print Template – Quick Reference Guide

**File:** `resources/views/instrument_registration/rds/print.blade.php`  
**Last Updated:** November 13, 2025

---

## Data Flow Diagram

```
RDSController::printRDS($id)
    ↓
    ├─ Get rds_tracking record
    ├─ Get registered_instruments record
    ├─ Prepare data array with $rds, $instrument, $watermark
    ↓
view('instrument_registration.rds.print', $data)
    ↓
print.blade.php
    ├─ Extract instrument_type
    ├─ Format registration date
    ├─ Map party labels based on type
    ├─ Extract party names (JSON-safe)
    ├─ Populate all document fields
    ├─ Apply watermark if needed
    ↓
HTML output for printing/viewing
```

---

## Field Population Reference

### 1. Document Title & Subtitle
```blade
{{ $documentTitle }}          <!-- From instrument_type -->
{{ $documentSubtitle }}       <!-- "THIS IS A DEED OF " . type -->
```

**Source:** `registered_instruments.instrument_type`

---

### 2. Registration Date
```blade
Dated: {{ $dateFormatted['day'] }}
day of {{ $dateFormatted['month'] }}
{{ $dateFormatted['year'] }}
```

**Source:** `rds_tracking.registration_date` or `registered_instruments.instrumentDate`  
**Format:** `d F Y` (e.g., "12 November 2025")

---

### 3. Executed By (Grantor)
```blade
Executed by: {{ $grantorDisplay }}
```

**Sources:**
- Primary: `registered_instruments.Grantor`
- Fallback: `rds_tracking.grantor`

**Processing:** JSON array safe extraction via `extractPartyName()`

---

### 4. Of Address (Grantor Address)
```blade
Of: {{ $grantorAddress }}
```

**Source:** `registered_instruments.GrantorAddress`

---

### 5. Right of Occupancy Number
```blade
The Right of Occupancy No. {{ $fileNumber }}
```

**Sources:**
- Primary: `registered_instruments.StFileNo`
- Secondary: `registered_instruments.fileno`
- Fallback: `rds_tracking.file_number`

---

### 6. Action Text (is Assigned/Mortgaged/Leased)
```blade
<span id="assignment-mortgage-text">{{ $assignmentMortgageText }}</span>
```

**Mapping:**
| Instrument Type | Text |
|---|---|
| *MORTGAGE* | is Mortgaged |
| *ASSIGNMENT* | is Assigned |
| *LEASE* | is Leased |
| *RELEASE* | is Released |
| *SURRENDER* | is Surrendered |
| *OTHER* | is [Type] |

---

### 7. To (Grantee/Mortgagee/Assignee/Lessee)
```blade
to: {{ $granteeDisplay }}
```

**Sources:**
- Primary: `registered_instruments.Grantee`
- Fallback: `rds_tracking.grantee`

**Processing:** JSON array safe extraction

---

### 8. Of Address (Grantee Address)
```blade
of: {{ $granteeAddress }}
```

**Source:** `registered_instruments.GranteeAddress`

---

### 9. Consideration
```blade
CONSIDERATION: {{ $consideration }}
```

**Sources:**
- Primary: `registered_instruments.consideration`
- Fallback: `$details['consideration']`

---

### 10. Stamp Duty
```blade
STAMP DUTY: {{ $stampDuty }}
```

**Sources:**
- Primary: `registered_instruments.stamp_duty`
- Fallback: `$details['stamp_duty']`

---

### 11. Registration Fee
```blade
REGISTRATION FEE: {{ $registrationFee }}
```

**Sources:**
- Primary: `registered_instruments.registration_fee`
- Fallback: `$details['registration_fee']`

---

## Dynamic Party Labels

Based on instrument type, party labels automatically adjust:

```php
if (stripos($upperType, 'MORTGAGE') !== false) {
    // Party 1: Mortgagor  |  Party 2: Mortgagee
} elseif (stripos($upperType, 'LEASE') !== false) {
    // Party 1: Lessor  |  Party 2: Lessee
} elseif (stripos($upperType, 'ASSIGNMENT') !== false) {
    // Party 1: Assignor  |  Party 2: Assignee
} else {
    // Party 1: Grantor  |  Party 2: Grantee
}
```

---

## JSON Array Handling

Some fields may contain JSON-encoded arrays:

```php
function extractPartyName($partyData) {
    if (!$partyData) return '';
    
    if (is_string($partyData) && str_starts_with(trim($partyData), '[')) {
        try {
            $decoded = json_decode($partyData, true);
            if (is_array($decoded) && count($decoded) > 0) {
                return $decoded[0];  // First element
            }
        } catch (\Exception $e) {
            return $partyData;
        }
    }
    
    return $partyData;
}
```

**Example:**
- **Database:** `["Musa Ali", "Jane Doe"]`
- **Display:** `Musa Ali`

---

## Watermarking

```blade
@if(($watermark ?? 'ORIGINAL') === 'COPY')
    <div class="watermark">COPY</div>
@endif
```

**Rules:**
- First print: No watermark (ORIGINAL)
- Subsequent prints: Semi-transparent "COPY" overlay
- Watermark is **hidden during actual printing**

**Logic in Controller:**
```php
'watermark' => $rds->print_count > 1 ? 'COPY' : 'ORIGINAL'
```

---

## Metadata Footer (Non-Printable)

```blade
<div class="text-xs text-gray-500 no-print">
    <div><strong>RDS Reference:</strong> {{ $rds->rds_reference }}</div>
    <div><strong>Generated:</strong> {{ formatted date }}</div>
    <div><strong>Print Count:</strong> {{ count }} ({{ watermark }})</div>
    <div><strong>STM Reference:</strong> {{ $rds->stm_ref }}</div>
</div>
```

**Visibility:** Screen only (hidden when printing via `no-print` class)

---

## Error Handling

The template includes multiple layers of error handling:

1. **Missing Fields:** Falls back to empty strings or 'N/A'
2. **Date Parsing:** Try-catch block logs warnings but doesn't crash
3. **JSON Decoding:** Fallback to original string if decode fails
4. **Null Checks:** All database fields checked with `??` operator

---

## CSS Classes

### Printable Elements
- `.document-content` — Main printable container
- `.underline-field` — Fill-in blank with bottom border
- `.form-line` — Content area with bottom border

### Non-Printable Elements
- `.no-print` — Hidden during printing
- `.watermark` — Background watermark (COPY documents)

### Print Media Queries
```css
@media print {
    .no-print { display: none !important; }
    body { print-color-adjust: exact; }
    @page { size: A4 portrait; margin: 0.5in; }
}
```

---

## Passing Data from Controller

```php
// In RDSController::printRDS()
return view('instrument_registration.rds.print', [
    'rds' => $rds,                    // rds_tracking record
    'instrument' => $instrument,       // registered_instruments record
    'details' => $details,             // Computed details
    'documentTitle' => $documentTitle, // Optional (computed in view)
    'watermark' => 'ORIGINAL|COPY',   // Based on print_count
    'printCount' => $rds->print_count // Number of times printed
]);
```

---

## Testing Scenarios

### Scenario 1: ASSIGNMENT Document
```
Input:  instrument_type = 'Assignment (Transfer of Title)'
Output: Title: "Assignment (Transfer of Title)"
        Subtitle: "THIS IS A DEED OF Assignment (Transfer of Title)"
        Action: "is Assigned"
        Party 1: Assignor  |  Party 2: Assignee
```

### Scenario 2: MORTGAGE Document
```
Input:  instrument_type = 'Mortgage'
Output: Title: "Mortgage"
        Subtitle: "THIS IS A DEED OF Mortgage"
        Action: "is Mortgaged"
        Party 1: Mortgagor  |  Party 2: Mortgagee
```

### Scenario 3: LEASE Document
```
Input:  instrument_type = 'Lease Agreement'
Output: Title: "Lease Agreement"
        Subtitle: "THIS IS A DEED OF Lease Agreement"
        Action: "is Leased"
        Party 1: Lessor  |  Party 2: Lessee
```

---

## Common Modifications

### Add New Field
1. Add field to database (`registered_instruments` or `rds_tracking`)
2. Extract in PHP section:
   ```php
   $myField = $instrument->myField ?? '';
   ```
3. Display in template:
   ```blade
   {{ $myField }}
   ```

### Change Date Format
Current format: `d F Y` (12 November 2025)

To change:
```php
$dateFormatted['day'] = $dateObj->format('d');     // 2-digit day
$dateFormatted['month'] = $dateObj->format('m');   // 2-digit month (01-12)
$dateFormatted['year'] = $dateObj->format('Y');    // 4-digit year
```

### Adjust Watermark
Edit `.watermark` CSS:
```css
.watermark {
    font-size: 120px;           /* Size */
    color: rgba(200, 200, 200, 0.3);  /* Opacity */
    transform: rotate(-45deg);  /* Angle */
}
```

---

## SQL Queries for Verification

### Check RDS Data
```sql
SELECT rds_reference, instrument_type, grantor, grantee, 
       file_number, registration_date, print_count 
FROM rds_tracking 
WHERE instrument_id = [ID];
```

### Check Instrument Data
```sql
SELECT instrument_type, Grantor, Grantee, GrantorAddress, GranteeAddress,
       StFileNo, consideration, stamp_duty, registration_fee, instrumentDate
FROM registered_instruments 
WHERE id = [ID];
```

---

## Debugging Tips

1. **View Page Source:** Check browser developer tools for rendered HTML
2. **Laravel Log:** Check `storage/logs/laravel.log` for date parsing errors
3. **Database Query:** Run SQL verification queries above
4. **Print Preview:** Use browser's print preview (Ctrl+Shift+P / Cmd+Shift+P)
5. **Check Variables:** Add debug comments in template:
   ```blade
   <!-- Debug: instrumentType = {{ $upperType }} -->
   ```

---

## Browser Compatibility

- **Chrome/Edge:** Full support, print quality excellent
- **Firefox:** Full support, print quality excellent
- **Safari:** Full support, watermark may appear differently
- **IE 11:** Not recommended (CSS Grid not supported)

---

## Performance Notes

- **No JavaScript:** Template uses server-side rendering only
- **No AJAX:** All data loaded with initial page request
- **Print Optimization:** CSS-based watermark (no JavaScript overhead)
- **Database Queries:** Pre-loaded by controller (no lazy loading)

---

## File Size Reference

- **HTML Output:** ~15-20 KB
- **Print Output:** ~5-8 KB (after removing .no-print elements)
- **PDF Export:** ~50-100 KB (if enabled)

---

## Support Contacts

For issues with:
- **Data Integration:** Check RDSController
- **Styling:** Edit `print.blade.php` `<style>` section
- **Database:** Verify rds_tracking and registered_instruments tables
- **Printing:** Test in different browsers and PDF viewers

---

**Version:** 1.0  
**Status:** Production Ready ✅  
**Last Review:** November 13, 2025
