# Quick Reference - CSV Import with Preview

## Files Created/Modified

### NEW: `resources/views/user/import-preview.blade.php`
Complete preview table component with editing and validation.

```blade
<!-- Include in any blade file with: -->
@include('user.import-preview')

<!-- Already included in: -->
resources/views/user/import-modal.blade.php
```

**Features:**
- CSV data preview in table format
- Inline cell editing
- Row management (add/delete)
- Real-time validation
- Error highlighting
- Statistics dashboard

### MODIFIED: `resources/views/user/import-modal.blade.php`
Changes:
1. Width: `max-w-2xl` → `max-w-6xl`
2. Added: `@include('user.import-preview')`
3. Updated: JavaScript logic for preview workflow

---

## Key Functions

### Preview Component Functions
```javascript
// CSV parsing
parseCSVAndShowPreview(file)         // Parse file, show preview
parseCSVLine(line)                   // Parse single CSV line

// Validation
validateRow(row, lineNumber)         // Check required fields

// Table management
updatePreviewTable()                 // Render table
updatePreviewStats(errors)           // Update stats
updateCell(rowId, field, value)      // Update cell, revalidate
deleteRow(rowId)                     // Delete row
deleteAllRows()                      // Delete all rows
addNewRow()                          // Add empty row
toggleSelectAll()                    // Select/deselect all
updateRowSelection(rowId)            // Toggle row selection
escapeHtml(text)                     // Escape HTML entities

// Export
getPreviewData()                     // Get valid records only
```

### Modal Functions
```javascript
// File handling
updateFileName()                     // Show file name, trigger parsing

// Form submission
submitImport()                       // Submit validated preview data
updateProgress(percent)              // Update progress bar
showStatusMessage(message, type)     // Show message (success/error)
appendErrorList(errors)              // Show error list
```

---

## Data Structures

### Preview Data Object
```javascript
previewData = [
    {
        // User fields (from CSV)
        first_name: "John",
        last_name: "Doe",
        username: "john.doe",
        type: "user",
        department_id: "1",
        user_level: "High",
        assign_role: "Dashboard; GIS",
        
        // Internal fields (prefixed with _)
        _errors: [],           // Array of validation error strings
        _id: 1234567890,       // Unique row identifier
        _selected: false       // Selection state
    }
]
```

### Submission Payload
```javascript
{
    environment: "TEST",
    records: [
        {
            first_name: "John",
            last_name: "Doe",
            username: "john.doe",
            type: "user",
            department_id: "1",
            user_level: "High",
            assign_role: "Dashboard; GIS"
        }
    ],
    _token: "csrf-token-value"
}
```

---

## Validation Rules

| Field | Required | Rules | Example |
|-------|----------|-------|---------|
| first_name | YES | Not empty | "John" |
| last_name | YES | Not empty | "Doe" |
| username | YES | 3-50 chars, alphanumeric + . _ - | "john.doe" |
| type | YES | Not empty | "User" |
| department_id | NO | Any value | "1" |
| user_level | NO | Any value | "Low" |
| assign_role | NO | Semicolon-separated | "Dashboard; GIS - Records" |

---

## Styling Classes (Tailwind)

### Colors Used
- **Success**: Green (bg-green-50, text-green-700)
- **Error**: Red (bg-red-50, text-red-700)
- **Warning**: Amber (bg-amber-50, text-amber-700)
- **Info**: Blue (bg-blue-50, text-blue-700)
- **Preview Header**: Emerald → Teal gradient

### Key Selectors
```css
#previewContainer        /* Main preview div (hidden by default) */
#previewTableBody        /* Table body rows */
#emptyState              /* Empty state message */
#errorSummary            /* Error list section */
#statusMessages          /* Form status messages */
#progressContainer       /* Progress bar section */
.editable-cell           /* Table cell inputs */
.row-select              /* Row selection checkboxes */
```

---

## CSV Format Requirements

```csv
first_name,last_name,username,type,department_id,user_level,assign_role
John,Doe,john.doe,user,1,High,Dashboard; GIS - Records
Jane,Smith,jane.smith,user,5,Low,ST - Overview
Bob,Johnson,bob.johnson,admin,2,High,Dashboard
```

**Required Columns**: first_name, last_name, username, type  
**Optional Columns**: department_id, user_level, assign_role  
**Max Records per Upload**: 50  
**Max File Size**: 1MB

---

## Common Usage Patterns

### Pattern 1: Upload & Import
```javascript
1. User selects CSV
2. parseCSVAndShowPreview() auto-triggers
3. previewData populated
4. Table renders with updatePreviewTable()
5. Stats update with updatePreviewStats()
6. User clicks Import
7. submitImport() validates and sends
```

### Pattern 2: Edit & Fix
```javascript
1. User clicks cell
2. Inline input appears
3. User types new value
4. On blur, updateCell() triggers
5. validateRow() checks field
6. Row status updates
7. Statistics recalculate
```

### Pattern 3: Delete Row
```javascript
1. User clicks delete button
2. deleteRow(rowId) removes from array
3. updatePreviewTable() re-renders
4. Statistics update
5. previewData length decreases
```

---

## Modal Width Comparison

| Version | Class | Width | Use Case |
|---------|-------|-------|----------|
| Original | max-w-2xl | 672px | Basic form |
| Updated | max-w-6xl | 1152px | Wide table |

---

## JavaScript Events

### File Input
```javascript
dropZone.addEventListener('click')        // Click to select
dropZone.addEventListener('dragover')     // Drag over highlight
dropZone.addEventListener('dragleave')    // Remove highlight
dropZone.addEventListener('drop')         // Handle drop
csvFile.addEventListener('change')        // File selected
```

### Form
```javascript
onclick="submitImport()"                  // Import button
onclick="deleteRow(rowId)"                // Delete button
onclick="deleteAllRows()"                 // Clear all button
onclick="addNewRow()"                     // Add row button
onclick="toggleSelectAll()"               // Select all checkbox
onchange="updateCell(...)"                // Edit input
onchange="updateRowSelection()"           // Row checkbox
```

---

## Error Messages

```
✓ Successful import
✗ Please select an environment
✗ Please upload and verify a CSV file
✗ No valid records to import. Please fix all errors in the preview table.
✗ first_name is required
✗ last_name is required
✗ username is required
✗ username must be 3-50 chars, alphanumeric with dots, underscores, hyphens
✗ type is required
✗ Upload failed: [error message]
```

---

## Route Expected

```php
// POST request to:
route('users.import.process')

// Request format:
POST /users/import/process HTTP/1.1
Content-Type: application/json

{
    "environment": "TEST",
    "records": [...],
    "_token": "..."
}

// Expected response:
{
    "success": true,
    "message": "10 users imported successfully",
    "errors": []
}
```

---

## Tips for Customization

### Change Modal Width
```blade
<!-- In import-modal.blade.php, line 1 -->
<!-- max-w-6xl = 1152px max -->
<!-- Use: max-w-2xl (672px), max-w-4xl (896px), max-w-7xl (1280px) etc -->
```

### Add Custom Validation
```javascript
// In import-preview.blade.php, validateRow() function
// Add custom checks before return errors array
// Example: check for unique usernames
```

### Change Colors
```blade
<!-- Emerald/Teal gradient in preview header -->
<!-- Change: from-emerald-500 to-teal-600 -->
<!-- Use any Tailwind gradient -->
```

### Add More Columns
```javascript
// In table header, add new <th> with min-w-X class
// Add new column in table body <td> with matching input
// Add field to CSV parsing logic
// Add to validation if required
```

---

## Debugging Tips

### Check Preview Data
```javascript
console.log(previewData)                // View all parsed data
console.log(getPreviewData())           // View only valid records
```

### Watch Validation
```javascript
// Add to validateRow() before return
console.log(`Row ${lineNumber} errors:`, errors)
```

### Monitor Events
```javascript
// Add to submitImport()
console.log('Submitting:', payload)
```

### Check Network
```javascript
// Open browser DevTools → Network tab
// Filter by fetch requests
// Check request payload and response
```

---

## Version Information

- **Created**: November 11, 2025
- **Component**: CSV Import with Preview & Inline Editing
- **Framework**: Laravel Blade + Tailwind CSS
- **Browser Support**: Modern browsers (ES6+)
- **Status**: Production Ready ✅

---

## Documentation Files

1. **CSV_IMPORT_PREVIEW_IMPLEMENTATION.md** - Technical details
2. **CSV_IMPORT_USER_GUIDE.md** - User guide & testing
3. **CSV_IMPORT_COMPLETION_REPORT.md** - Project summary
4. **CSV_IMPORT_QUICK_REFERENCE.md** - This file

---

## Support Resources

- **Check Files**: Look in `resources/views/user/`
- **Check Docs**: Root directory `CSV_IMPORT_*.md`
- **Browser Console**: Check for JavaScript errors
- **Network Tab**: Monitor fetch requests
- **Laravel Logs**: Check `storage/logs/`

---

**Ready to use! 🚀**
