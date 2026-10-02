# File Number Selection Modal Plugin

This document describes the usage and functionality of the **File Number Selection Modal Plugin** - a jQuery-based reusable UI component for selecting file numbers across the system.

## Overview

The File Number Selection Modal is a jQuery plugin designed to provide a consistent way to search for and select file numbers throughout the application. It can be used on any page and automatically handles various file number field naming conventions.

## Features

- **jQuery-based Plugin:** No Alpine.js dependency, uses pure jQuery
- **Search Functionality:** Users can search for file numbers from a database table
- **Manual Entry:** Users can manually type in file numbers with categorized input tabs (MLS, KANGIS, New KANGIS)
- **Preview Section:** Shows selected/typed file number before applying
- **Auto-Detection:** Automatically applies to common field names (`file_number`, `fileno`, `file_no`, `fileNumber`, etc.)
- **Flexible Targeting:** Can target specific input fields or auto-apply to multiple fields
- **Global Access:** Sets `window.SelectedFileNumber` for global access
- **Event System:** Triggers custom events when file numbers are selected

## Files

- **Plugin:** `public/js/file-number-modal-plugin.js`
- **Modal Template:** `resources/views/partials/file_number_modal.blade.php`
- **Test Page:** `public/test-file-number-modal.html`

## Usage

### 1. Include the Modal in Your Blade Template

```php
@include('partials.file_number_modal')
```

### 2. Basic Usage - Target Specific Field

```html
<input type="text" id="fileNumber" name="file_number" class="form-control">
<button onclick="openFileNumberModal('#fileNumber')">Select File Number</button>
```

### 3. Auto-Apply to All Common Fields

```html
<button onclick="openFileNumberModal()">Select File Number</button>
```

### 4. Listen for Selection Events

```javascript
$(document).on('fileNumberSelected', function(event, fileNumber, targetSelector) {
    console.log('Selected:', fileNumber, 'Applied to:', targetSelector);
    // Your custom logic here
});
```

## Plugin API

### Global Functions

- `openFileNumberModal(targetSelector)` - Opens the modal
  - `targetSelector` (optional) - jQuery selector for target input field
  - If not provided, applies to all common field names

### Global Variables

- `window.SelectedFileNumber` - Contains the last selected file number

### Events

- `fileNumberSelected` - Fired when a file number is applied
  - Parameters: `fileNumber`, `targetSelector`

## Auto-Detection Field Names

The plugin automatically detects and applies file numbers to inputs with these names/IDs:

- `file_number`
- `fileno`
- `file_no`
- `filnumber`
- `fileNumber`
- `.file-number-input` (class)

## Modal Features

### Selection Tab
- **Search:** Real-time search through file numbers
- **Table View:** Shows File Number, File Name, and Type
- **Click to Select:** Click any row to select that file number

### Manual Entry Tab
- **MLS Tab:** For MLS file numbers
- **KANGIS Tab:** For KANGIS file numbers  
- **New KANGIS Tab:** For New KANGIS file numbers
- **Real-time Preview:** Shows typed value in preview section

### Preview Section
- Shows currently selected/typed file number
- Visual feedback with color coding
- Must have a value to enable "Apply" button

### Action Buttons
- **Clear:** Clears all selections and inputs
- **Close:** Closes modal without applying
- **Apply:** Applies selected file number to target field(s)

## Configuration

The plugin can be configured by modifying `file-number-modal-plugin.js`:

```javascript
// Customize common field selectors
const commonSelectors = [
    'input[name="file_number"]',
    'input[name="your_custom_name"]',
    // Add your field names here
];
```

## Testing

Use the test page at `public/test-file-number-modal.html` to verify functionality:

1. Copy `public/js/file-number-modal-plugin.js` to the same directory as the test file
2. Open `test-file-number-modal.html` in a web browser
3. Test various scenarios (specific targeting, auto-apply, manual entry)

## Integration Examples

### Laravel Form
```php
<div class="form-group">
    <label for="file_number">File Number</label>
    <div class="input-group">
        <input type="text" name="file_number" id="file_number" class="form-control" value="{{ old('file_number') }}">
        <div class="input-group-append">
            <button type="button" class="btn btn-outline-secondary" onclick="openFileNumberModal('#file_number')">
                Select
            </button>
        </div>
    </div>
</div>

@include('partials.file_number_modal')
```

### Multiple Fields
```html
<!-- These will all be populated when using openFileNumberModal() without parameters -->
<input name="file_number" placeholder="Main file number">
<input name="fileno" placeholder="Alternative file number">
<input name="backup_file_number" id="backup_file_number" placeholder="Backup">

<button onclick="openFileNumberModal()">Select File Number for All</button>
<button onclick="openFileNumberModal('#backup_file_number')">Select for Backup Only</button>
```

## Troubleshooting

### Modal Not Opening
- Ensure jQuery and Bootstrap are loaded before the plugin
- Check console for JavaScript errors
- Verify the modal include is present on the page

### File Numbers Not Applying
- Check that target input elements exist in the DOM
- Verify field name matches common selectors or use specific targeting
- Check browser console for error messages

### Database Integration
- Update the AJAX URL in the plugin if using server-side data
- Modify the Blade template to connect to your file number database table
- Ensure proper route exists for file number data endpoints

## Support

For questions or issues, refer to the main project documentation or contact the development team.
