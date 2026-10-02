# Blind Scanning - Global File Number Modal Integration

## Overview
Added GlobalFileNoModal integration to the Blind Scanning page to allow users to select or manually enter file numbers using the standardized modal interface.

## Changes Made

### 1. Updated UI (`resources/views/scanning/blind_scans.blade.php`)

#### File Number Input Field
- Changed from simple input to input + button layout
- Added "Select" button with search icon
- Input field remains editable for manual entry
- Button opens the GlobalFileNoModal

```blade
<div class="flex gap-2">
  <input id="fileNo" type="text" class="flex-1 border rounded-md px-3 py-2" 
         placeholder="e.g., ST-COM-2025-0001" />
  <button type="button" id="openFileNoModalBtn" class="btn btn-primary">
    <i class="fa-solid fa-search mr-1"></i>Select
  </button>
</div>
```

### 2. Included Required Components

#### Modal Component
```blade
@include('components.global-fileno-modal')
```

#### JavaScript Files
```blade
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
```

### 3. Integration Script

Added initialization and callback handling:

```javascript
$(document).ready(function() {
  // Initialize the modal
  GlobalFileNoModal.init();

  // Handle the "Select" button click
  $('#openFileNoModalBtn').on('click', function() {
    GlobalFileNoModal.open({
      callback: function(fileData) {
        // Extract file number based on system
        let fileNumber = '';
        
        if (fileData.system === 'mls') {
          fileNumber = fileData.fileno;
        } else if (fileData.system === 'kangis') {
          fileNumber = fileData.fileno;
        } else if (fileData.system === 'newkangis') {
          fileNumber = fileData.np_fileno;
        } else if (fileData.system === 'manual') {
          fileNumber = fileData.manualInput;
        }
        
        // Update the file number input
        if (fileNumber) {
          $('#fileNo').val(fileNumber);
          validateUploadSection(); // Trigger validation
        }
      },
      initialTab: 'mls',
      initialValue: $('#fileNo').val() || ''
    });
  });
});
```

## Features

### Modal Capabilities
1. **MLS Tab**: Browse and search MLS file numbers
2. **KANGIS Tab**: Browse and search KANGIS file numbers
3. **New KANGIS Tab**: Browse and search New KANGIS file numbers
4. **Manual Entry Tab**: Manually type any file number

### Smart Features
- **Auto-validation**: File number is validated after selection
- **Pre-population**: Current value is shown when modal opens
- **Recent selections**: Modal remembers recently used file numbers
- **Search functionality**: Quick search across all file number systems
- **Manual entry**: Users can still type directly in the input field

## User Workflow

1. **Navigate** to `/blind-scanning`
2. **Click** the "Select" button next to File Number field
3. **Choose** a tab (MLS, KANGIS, New KANGIS, or Manual)
4. **Select** a file number from the list OR enter one manually
5. **Click** "Apply" in the modal
6. **File number** appears in the input field
7. **Proceed** with folder selection and migration

## Validation Flow

1. File number is selected/entered via modal
2. Modal callback updates the `#fileNo` input field
3. `validateUploadSection()` function is triggered
4. System validates:
   - File number is not empty
   - Folder is selected
   - Folder name matches file number (if detectable)
5. "Migrate" button is enabled when valid

## Technical Details

### Dependencies
- jQuery (included in layouts.app)
- GlobalFileNoModal plugin (`public/js/global-fileno-modal.js`)
- Modal component (`resources/views/components/global-fileno-modal.blade.php`)

### API Endpoints Used by Modal
- `/api/file-numbers/mls` - MLS file numbers
- `/api/file-numbers/kangis` - KANGIS file numbers
- `/api/file-numbers/newkangis` - New KANGIS file numbers
- `/api/file-numbers/validate` - File number validation

### File Number Formats Supported
- **MLS**: `MLS/LAND/2025/001`
- **KANGIS**: `KN_001`
- **New KANGIS**: `ST-COM-2025-0001`
- **Manual**: Any custom format

## Testing Checklist

- [ ] Click "Select" button opens the modal
- [ ] Modal displays all 4 tabs (MLS, KANGIS, New KANGIS, Manual)
- [ ] Selecting a file number closes modal and populates input
- [ ] Manual entry in modal works correctly
- [ ] File number validation triggers after selection
- [ ] Direct typing in input field still works
- [ ] Folder validation matches file number
- [ ] Migration proceeds with selected file number

## Benefits

✅ **Consistency**: Uses the same file number selection interface as other modules  
✅ **Validation**: Ensures valid file numbers are used  
✅ **Convenience**: Quick access to existing file numbers  
✅ **Flexibility**: Manual entry still available  
✅ **User-friendly**: Visual interface instead of memorizing formats  
✅ **Error prevention**: Reduces typos and format errors

## Files Modified

1. `resources/views/scanning/blind_scans.blade.php`
   - Added Select button
   - Included modal component
   - Added initialization script

## Related Documentation

- [Global File Number Modal Documentation](./GLOBAL_FILENO_MODAL.md) (if exists)
- [Blind Scanning Implementation](./BLIND_SCANNING_IMPLEMENTATION_COMPLETE.md)

---

**Implementation Date**: October 11, 2025  
**Status**: ✅ Complete and Ready for Testing
