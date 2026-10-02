# RDS Grantee Auto-Population - Implementation Update

## Problem
The "to:" field (grantee) in the RDS print template was blank and required manual entry, even though the grantee information was already available in the database.

## Solution
Updated the print template to automatically populate the "to:" field with the Grantee name from the database.

## Changes Made

### File: `resources/views/instrument_registration/rds/print.blade.php`
**Lines 95-127** - Updated the "To: Grantee" section

**Before:**
```blade
<!-- To -->
<div class="flex items-baseline gap-2">
    <span class="font-semibold">to:</span>
    <span class="form-line flex-1"></span>
</div>
```

**After:**
```blade
<!-- To: Grantee -->
<div class="flex items-baseline gap-2">
    <span class="font-semibold">to:</span>
    <span class="form-line flex-1">
        @php
            // Handle Grantee field - could be JSON array or string
            $granteeDisplay = '';
            if ($instrument && $instrument->Grantee) {
                $grantee = $instrument->Grantee;
                // Try to decode if it's JSON
                if (is_string($grantee) && str_starts_with(trim($grantee), '[')) {
                    $granteeArray = json_decode($grantee, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($granteeArray) && count($granteeArray) > 0) {
                        $granteeDisplay = $granteeArray[0];
                    } else {
                        $granteeDisplay = $grantee;
                    }
                } else {
                    $granteeDisplay = $grantee;
                }
            }
        @endphp
        {{ $granteeDisplay }}
    </span>
</div>
```

## How It Works

1. The `$instrument` object from the controller contains the `Grantee` field
2. The Grantee could be stored as:
   - A simple string: `"Musa Ali"`
   - A JSON array: `["Musa Ali", "Jane Smith"]` (for multiple grantees)
3. The template:
   - Checks if the Grantee field exists
   - Attempts to decode it as JSON (in case of multiple grantees)
   - If JSON decoding succeeds and it's an array, displays the first grantee
   - If it's a plain string or JSON fails, displays the value as-is
   - Displays the grantee name in the "to:" field

## Display Flow

```
Database: $instrument->Grantee
    ↓
Check if Grantee exists
    ↓
Try to decode as JSON array
    ↓
If successful and is array → Get first element
If not JSON or decode fails → Use as string
    ↓
Display in "to:" field with underline
```

## Examples

### Example 1: Single Grantee (String)
```
Database: "Musa Ali"
Display: "to: Musa Ali"
```

### Example 2: Multiple Grantees (JSON Array)
```
Database: ["Musa Ali", "Jane Smith"]
Display: "to: Musa Ali" (first grantee)
```

### Example 3: No Grantee
```
Database: NULL or empty
Display: "to: " (blank line)
```

## Benefits

✅ Automatically populates grantee name from database
✅ No manual data entry needed for the "to:" field
✅ Handles both single and multiple grantees
✅ Maintains blank line for "of:" field (address still requires manual entry)
✅ Preserves print functionality

## Template Structure After Update

The complete document now displays:

```
Dated: ________________
Executed by: ________________
Of: ________________
The Right of Occupancy No. ________________ is Assigned
to: Musa Ali (auto-populated)
of: ________________
From: ________________
```

## Notes

- Only the first grantee is displayed (if multiple)
- The grantee field retains the underline styling for visual consistency
- The "of:" field remains blank for address information (typically still manual entry)
- If the Grantee field is empty in the database, the field displays blank
