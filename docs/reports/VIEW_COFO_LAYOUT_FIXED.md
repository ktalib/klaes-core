# View CofO Layout - Fixed Structure

## Issue Identified
The `resources/views/programmes/view_cofo.blade.php` was trying to include `cofo_content.blade.php`, which was a **complete standalone HTML document** with its own `<!DOCTYPE>`, `<html>`, `<head>`, and `<body>` tags.

### Problem
When included in a Laravel Blade template that already extends `layouts.app`, this created **invalid nested HTML** with multiple `<html>` and `<body>` tags, breaking the page structure.

## Solution Applied

### 1. **Removed HTML Structure from cofo_content.blade.php**
   - Removed `<!DOCTYPE html>`
   - Removed `<html>` and closing `</html>`
   - Removed `<head>` section (moved styles to view_cofo)
   - Removed `<body>` and closing `</body>`
   - Now cofo_content.blade.php contains only the style definitions (no longer used directly)

### 2. **Refactored view_cofo.blade.php**
   - **Properly extends** the app layout: `@extends('layouts.app')`
   - **Styles section** (@section('styles'))
     - Includes SweetAlert CSS
     - Contains all certificate styling (print CSS, layout, typography)
   - **Content section** (@section('content'))
     - Includes header partial
     - Flash message display (success/error alerts)
     - Main certificate container with proper structure
     - Dynamic data binding from `$cofo` object
     - Print button with functionality
     - Footer partial
   - **Scripts section** 
     - SweetAlert library
     - Print button event listener
     - Action parameter handler

### 3. **Dynamic Data Integration**
The certificate now displays data from the database `$cofo` object:
- File number: `{{ $cofo->file_no }}`
- Land use: `{{ strtoupper($cofo->land_use) }}`
- Certificate number: `{{ $cofo->certificate_number }}`
- Holder name: `{{ $cofo->holder_name }}`
- Holder address: `{{ $cofo->holder_address }}`
- Total term: `{{ $cofo->total_term }}`
- Start date: `{{ $cofo->start_date }}`
- Signed title: `{{ $cofo->signed_title }}`

## File Structure

```
view_cofo.blade.php
├── @extends('layouts.app')
├── @section('page-title')
├── @section('styles')
│   ├── SweetAlert CSS
│   └── Certificate Styles (print, layout, typography)
├── @section('content')
│   ├── Header partial
│   ├── Flash messages (success/error)
│   ├── Certificate container
│   │   ├── Header with title and file info
│   │   ├── Main content
│   │   │   ├── Holder information
│   │   │   ├── Terms and conditions
│   │   │   ├── Date section
│   │   │   └── Signature section
│   │   └── Controls (print button)
│   └── Footer partial
└── Scripts (SweetAlert, print button, action handlers)
```

## Print Functionality
- **Print button** in the controls section
- **CSS media queries** for print optimization
- Print margins: 45mm top, 20mm sides, 40mm bottom
- Page size: A4 (210mm x 297mm)
- Font: Times New Roman, 10pt base

## Template Variables Required (from CofoController)
The controller must pass the `$cofo` object with these properties:
- `id` - Record ID
- `file_no` - File number
- `land_use` - Land use type
- `certificate_number` - Certificate number
- `holder_name` - Certificate holder name
- `holder_address` - Holder address
- `total_term` - Certificate term in years
- `start_date` - Certificate start date
- `signed_title` - Signatory title
- Plus other supporting fields

## Testing Checklist
- [ ] View displays certificate data correctly
- [ ] Print button works
- [ ] CSS styling renders properly
- [ ] No duplicate HTML tags in page source
- [ ] Page layout fits on single A4 sheet when printed
- [ ] Flash messages display correctly
- [ ] Dynamic data populates all fields
- [ ] Print media queries apply correctly

## Notes
- The old `cofo_content.blade.php` file is now unused and can be archived/deleted
- All CSS is now properly scoped within the view section
- No external file includes (like bg1.jpg) are referenced in current version
- Print optimization uses exact color adjustment for better output
