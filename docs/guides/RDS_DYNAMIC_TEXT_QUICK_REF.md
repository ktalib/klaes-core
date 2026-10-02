# RDS Dynamic Text - Quick Reference

## What Was Changed?

The hardcoded text "THIS IS A DEED OF ASSIGNMENT" in the RDS print template is now **dynamically generated** based on the instrument type from the database.

## Where Did It Change?

### File 1: `app/Http/Controllers/RDSController.php`
- **Method:** `printRDS($id)` (Lines 199-265)
- **What:** Added logic to read `instrument_type` from the database and generate appropriate title/subtitle
- **Passes to view:** 
  - `$documentTitle` - e.g., "ASSIGNMENT" or "MORTGAGE"
  - `$documentSubtitle` - e.g., "THIS IS A DEED OF ASSIGNMENT" or "THIS IS A DEED OF MORTGAGE"
  - `$assignmentMortgageText` - e.g., "is Assigned" or "is Mortgaged"

### File 2: `resources/views/instrument_registration/rds/print.blade.php`
- **Line 63:** Title now uses `{{ $documentTitle ?? 'ASSIGNMENT' }}`
- **Line 67:** Subtitle now uses `{{ $documentSubtitle ?? 'THIS IS A DEED OF ASSIGNMENT' }}`
- **Line 100:** Action text now uses `{{ $assignmentMortgageText ?? 'is Assigned' }}`
- **Lines 155-200:** JavaScript initialization updated to use server values

## How It Works

```
Database (instrument_type)
           ↓
RDSController reads instrument_type
           ↓
Determines appropriate title/subtitle based on type
           ↓
Passes values to Blade template
           ↓
Blade template displays dynamic text instead of hardcoded
```

## Example Outputs

### For "ST Assignment (Transfer of Title)" instrument:
- Title: **ASSIGNMENT**
- Subtitle: **THIS IS A DEED OF ASSIGNMENT**
- Action: **is Assigned**

### For "Sectional Titling Mortgage" instrument:
- Title: **MORTGAGE**
- Subtitle: **THIS IS A DEED OF MORTGAGE**
- Action: **is Mortgaged**

### For "ST Fragmentation" instrument:
- Title: **FRAGMENTATION**
- Subtitle: **THIS IS A DEED OF FRAGMENTATION**
- Action: **is FRAGMENTATION**

## Key Code Changes

### In RDSController:
```php
// Determine based on instrument type
if (strpos($type, 'MORTGAGE') !== false) {
    $documentTitle = 'MORTGAGE';
    $documentSubtitle = 'THIS IS A DEED OF MORTGAGE';
    $assignmentMortgageText = 'is Mortgaged';
} elseif (strpos($type, 'ASSIGNMENT') !== false) {
    $documentTitle = 'ASSIGNMENT';
    $documentSubtitle = 'THIS IS A DEED OF ASSIGNMENT';
    $assignmentMortgageText = 'is Assigned';
}
```

### In Blade Template:
```blade
<h1>{{ $documentTitle ?? 'ASSIGNMENT' }}</h1>
<p>{{ $documentSubtitle ?? 'THIS IS A DEED OF ASSIGNMENT' }}</p>
<span>{{ $assignmentMortgageText ?? 'is Assigned' }}</span>
```

## Testing the Changes

1. Access the RDS print route: `/instrument_registration/print-rds/{id}`
2. The document should display the instrument type-specific text
3. The document can still be edited and printed normally
4. All previous functionality is preserved

## Backward Compatibility

✅ Yes! If the variables are not passed, fallback values are used via the `??` operator in Blade.
