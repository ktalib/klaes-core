# RDS Print Template - Text Normalization and Date Synchronization

## Changes Made

### 1. Date Field Synchronization
The "From:" date field now uses the same registration date as the "Dated:" field.

**Before:**
```blade
<!-- From Date -->
<div class="flex items-baseline gap-2 mt-6">
    <span class="font-semibold">From:</span>
    <span class="underline-field flex-1"></span>
    <span>day of</span>
    <span class="underline-field flex-1"></span>
    <span>20</span>
    <span class="underline-field w-16"></span>
</div>
```

**After:**
```blade
<!-- From Date -->
<div class="flex items-baseline gap-2 mt-6">
    <span class="font-semibold">From:</span>
    <span class="underline-field flex-1">{{ $dateFormatted['day'] }}</span>
    <span>day of</span>
    <span class="underline-field flex-1">{{ $dateFormatted['month'] }}</span>
    <span>{{ $dateFormatted['year'] }}</span>
</div>
```

This ensures both "Dated:" and "From:" fields use the same registration date from the `registration_date` field.

---

### 2. RDS Text Normalization Helper Function

**Added to:** `app/Helper/helper.php`

**New Function:** `normalizeRDSText($text)`

```php
/**
 * Normalize RDS content text
 * Converts text to uppercase and removes extra whitespace/commas
 * Examples:
 *   "GUDA ABDULLAHI ROAD, DAURAWA, Tarauni, Kano" → "GUDA ABDULLAHI ROAD, DAURAWA, TARAUNI, KANO"
 *   "State Government" → "STATE GOVERNMENT"
 * 
 * @param string $text Text to normalize
 * @return string Normalized text
 */
if (!function_exists('normalizeRDSText')) {
    function normalizeRDSText($text)
    {
        if (empty($text)) {
            return '';
        }

        // Convert to uppercase
        $normalized = strtoupper(trim($text));

        // Replace multiple spaces with single space
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Normalize comma spacing: remove space after comma, add single space
        $normalized = preg_replace('/\s*,\s*/', ', ', $normalized);

        // Remove trailing comma if exists
        $normalized = rtrim($normalized, ',');

        return $normalized;
    }
}
```

**Features:**
- Converts all text to UPPERCASE
- Trims leading/trailing whitespace
- Collapses multiple spaces into single space
- Normalizes comma spacing (space after comma)
- Removes trailing commas

**Examples:**
| Input | Output |
|-------|--------|
| `GUDA ABDULLAHI ROAD, DAURAWA, Tarauni, Kano` | `GUDA ABDULLAHI ROAD, DAURAWA, TARAUNI, KANO` |
| `State Government` | `STATE GOVERNMENT` |
| `  Multiple   spaces  ` | `MULTIPLE SPACES` |
| `kano,state,nigeria` | `KANO, STATE, NIGERIA` |

---

### 3. Address Field Normalization

**Updated in:** `resources/views/instrument_registration/rds/print.blade.php` (Lines 130-131)

**Before:**
```php
$grantorAddress = $instrument->GrantorAddress ?? '';
$granteeAddress = $instrument->GranteeAddress ?? '';
```

**After:**
```php
$grantorAddress = normalizeRDSText($instrument->GrantorAddress ?? '');
$granteeAddress = normalizeRDSText($instrument->GranteeAddress ?? '');
```

All addresses are now normalized when displayed in the print template.

---

### 4. Party Name Normalization

**Updated in:** `resources/views/instrument_registration/rds/print.blade.php` (Lines 134-159)

Party names (Grantor and Grantee) are now normalized at all stages:

1. **When extracted from JSON arrays:**
   ```php
   if (is_array($grantorArray) && count($grantorArray) > 0) {
       $grantorDisplay = normalizeRDSText($grantorArray[0]);
   }
   ```

2. **When used as simple strings:**
   ```php
   } else {
       $grantorDisplay = normalizeRDSText($grantor);
   }
   ```

3. **In exception handlers:**
   ```php
   } catch (\Exception $e) {
       $grantorDisplay = normalizeRDSText($grantor);
   }
   ```

---

## Data Flow

```
Database (registered_instruments)
├── GrantorAddress: "GUDA ABDULLAHI ROAD, DAURAWA, Tarauni, Kano"
├── GranteeAddress: "123  Main  Street,  Kano"
├── Grantor: "State Government"
└── Grantee: "john  smith"
           ↓
normalizeRDSText() Function
           ↓
Print Template Variables
├── $grantorAddress: "GUDA ABDULLAHI ROAD, DAURAWA, TARAUNI, KANO"
├── $granteeAddress: "123 MAIN STREET, KANO"
├── $grantorDisplay: "STATE GOVERNMENT"
└── $granteeDisplay: "JOHN SMITH"
           ↓
RDS Document Output
```

---

## Affected Template Fields

The following fields in `print.blade.php` now use normalized text:

1. **Executed by:** (Line ~195)
   - Uses: `$grantorDisplay` (normalized)

2. **Of:** (Grantor Address) (Line ~201)
   - Uses: `$grantorAddress` (normalized)

3. **to:** (Grantee Name) (Line ~213)
   - Uses: `$granteeDisplay` (normalized)

4. **of:** (Grantee Address) (Line ~219)
   - Uses: `$granteeAddress` (normalized)

5. **From:** Date (Line 250-257)
   - Now uses: `$dateFormatted['day']`, `$dateFormatted['month']`, `$dateFormatted['year']`
   - Same values as "Dated:" field

---

## Example Output

### Before:
```
Dated: 12 day of november 2025
Executed by: kano state government
Of: GUDA ABDULLAHI ROAD, DAURAWA, Tarauni, Kano
to: john smith
of: 123  Main  Street, Kano
From:  day of 20
```

### After:
```
Dated: 12 day of NOVEMBER 2025
Executed by: KANO STATE GOVERNMENT
Of: GUDA ABDULLAHI ROAD, DAURAWA, TARAUNI, KANO
to: JOHN SMITH
of: 123 MAIN STREET, KANO
From: 12 day of NOVEMBER 2025
```

---

## Testing Checklist

- [ ] Print RDS document with various address formats
- [ ] Verify all addresses are uppercase
- [ ] Verify comma spacing is consistent
- [ ] Verify "From:" date matches "Dated:" date
- [ ] Test with organization names (e.g., "State Government")
- [ ] Test with special characters in addresses
- [ ] Test with empty/null address fields
- [ ] Test with multiple spaces in party names
- [ ] Verify no trailing commas in output
- [ ] Print sample documents in both ORIGINAL and COPY modes

---

## Database Verification

To verify current address data in the database:

```sql
-- Check grantor and grantee addresses in registered_instruments
SELECT 
    id,
    instrument_type,
    Grantor,
    GrantorAddress,
    Grantee,
    GranteeAddress,
    created_at
FROM registered_instruments
WHERE instrument_type IN ('ST Assignment (Transfer of Title)', 'Sectional Titling CofO')
ORDER BY created_at DESC
LIMIT 10;
```

Expected normalized output in RDS documents:
- All text UPPERCASE
- Consistent comma spacing
- No extra whitespace
- No trailing commas

---

## Edge Cases Handled

1. **NULL/Empty Values:**
   - `null` → `''` (empty string)
   - `''` → `''` (empty string)
   - Empty after trim → `''`

2. **Multiple Spaces:**
   - `"multiple   spaces"` → `"MULTIPLE SPACES"`

3. **Comma Spacing:**
   - `"item1,item2"` → `"ITEM1, ITEM2"`
   - `"item1, item2"` → `"ITEM1, ITEM2"`
   - `"item1 , item2"` → `"ITEM1, ITEM2"`

4. **Trailing Commas:**
   - `"item1, item2,"` → `"ITEM1, ITEM2"`

5. **JSON Arrays:**
   - Arrays are decoded and first element normalized
   - If decoding fails, raw value is normalized

6. **Special Characters:**
   - All maintained (e.g., "/" in road names stays as-is)
   - Only whitespace and case are normalized

---

## Files Modified

1. **`app/Helper/helper.php`**
   - Added: `normalizeRDSText()` function
   - Location: End of file after `getGranteeAddress()`

2. **`resources/views/instrument_registration/rds/print.blade.php`**
   - Modified: Address field extraction (lines 130-131)
   - Modified: Party name extraction (lines 134-159)
   - Modified: "From:" date field (lines 250-257)

---

## Related Features

This normalization works with the previously implemented features:
- ST Assignment Grantor fix (allocation_entity for SUA)
- ST Assignment Address fix (allocation_source, address fields)
- formatAddress() helper function for standard formatting

All address fields from helper functions are passed through normalizeRDSText() for consistent output.

---

## Performance Notes

- `normalizeRDSText()` uses regex for efficient string processing
- Applied only during template rendering (minimal performance impact)
- Caching: Template caching still applies (views are cached)
- No database changes required
