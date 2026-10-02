# RDS Grantee Auto-Population - Quick Reference

## What Changed?

The "to:" field in the RDS print template now automatically displays the **Grantee name** from the database instead of being blank.

## Where Changed?

**File:** `resources/views/instrument_registration/rds/print.blade.php`  
**Lines:** 103-127  
**Section:** "To: Grantee" 

## The Update

### Before:
```
to: ________________ (blank)
```

### After:
```
to: Musa Ali (from database)
```

## How It Works

The template now includes logic that:
1. Reads the `$instrument->Grantee` field from the database
2. Handles both single names and JSON arrays (multiple grantees)
3. Displays the first grantee name (if multiple)
4. Falls back to blank if no grantee exists

## Code Added

```blade
@php
    $granteeDisplay = '';
    if ($instrument && $instrument->Grantee) {
        $grantee = $instrument->Grantee;
        // Decode if JSON array
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
```

## Field Handling

| Field | Display | Notes |
|-------|---------|-------|
| **to:** | Grantee name | ✅ Auto-populated from DB |
| **of:** | Blank | Still requires manual entry (address) |

## Supported Grantee Formats

✅ Single grantee string: `"Musa Ali"`  
✅ JSON array: `["Musa Ali", "Jane Smith"]` (shows first)  
✅ NULL/Empty: Shows blank line  

## Files Modified

- ✅ `resources/views/instrument_registration/rds/print.blade.php`

## Testing

1. Generate RDS for an instrument with a grantee
2. The "to:" field should show the grantee name automatically
3. The "of:" field remains blank (for address entry)
4. Printing should work normally
