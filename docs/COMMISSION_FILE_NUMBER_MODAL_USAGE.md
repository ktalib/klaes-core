# Commission New File Number Modal - Global Usage Guide

## Overview
The Commission New File Number Modal is now a reusable component that can be included in any module of the KLAES system. It provides a complete interface for generating MLS file numbers with all the logic intact.

## Quick Start

### 1. Include the Modal in Your View

Add this single line to any Blade view where you want to use the file number generation modal:

```blade
@include('components.commission-fileno-modal-include')
```

**Important**: Place this include at the end of your view, before the `@endsection` tag.

### 2. Add a Trigger Button

Add a button (or any element) to trigger the modal:

```blade
<button onclick="openCommissionFileNoModal()" 
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
    <i data-lucide="plus" class="w-4 h-4"></i>
    <span>Commission New File Number</span>
</button>
```

### 3. That's It!
The modal is now fully functional and will work exactly as it does in the original MLS File Number Generator page.

---

## Advanced Usage

### Custom Success Callback

If you want to perform specific actions after a file number is successfully generated (e.g., refresh a table, show a custom notification), define a callback function:

```blade
<script>
    window.commissionFileNoSuccessCallback = function(response) {
        console.log('File number generated:', response);
        
        // Example: Refresh a DataTable
        if (typeof myDataTable !== 'undefined') {
            myDataTable.ajax.reload();
        }
        
        // Example: Show custom notification
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: 'File number ' + response.file_number + ' has been generated',
            timer: 3000
        });
        
        // Example: Update a specific field
        document.getElementById('latest_file_number').value = response.file_number;
    };
</script>
```

### Manual Modal Control

Open and close the modal programmatically:

```javascript
// Open the modal
window.openCommissionFileNoModal();

// Close the modal
window.closeCommissionFileNoModal();
```

---

## Complete Example: Using in a Custom Module

Here's a complete example of integrating the modal into a custom module page:

```blade
@extends('layouts.app')

@section('page-title')
    My Custom Module
@endsection

@section('content')
    <!-- Your page header -->
    @include('admin.header', [
        'PageTitle' => 'My Custom Module',
        'PageDescription' => 'Manage your data'])

    <div class="p-6">
        <div class="container mx-auto">
            
            <!-- Add your custom content here -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">My Data Table</h2>
                    
                    <!-- Trigger button for the Commission modal -->
                    <button onclick="openCommissionFileNoModal()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Commission New File Number</span>
                    </button>
                </div>

                <!-- Your DataTable or content -->
                <table id="myDataTable" class="w-full">
                    <!-- ... table content ... -->
                </table>
            </div>
        </div>
    </div>

    @include('admin.footer')

    {{-- Include the Commission File Number Modal - THIS IS THE KEY LINE --}}
    @include('components.commission-fileno-modal-include')

@endsection

@push('scripts')
    <script>
        // Define your custom success callback
        window.commissionFileNoSuccessCallback = function(response) {
            // Refresh the datatable
            $('#myDataTable').DataTable().ajax.reload();
            
            // Show success message
            toastr.success('File number generated: ' + response.file_number);
        };
        
        // Your other page scripts...
    </script>
@endpush
```

---

## Features Available

The modal includes ALL features from the original implementation:

✅ **Application Types**:
- Direct Allocation
- Conversion
- OP Resettlement

✅ **File Type Options**:
- Normal File
- Temporary File
- Extension
- Miscellaneous
- Old MLS
- SLTR
- SIT

✅ **Batch Mode**:
- Generate multiple file numbers at once
- Individual location details for each file
- Serial range preview

✅ **Governor/Commissioner List Integration**:
- Select from pre-allocated entries
- Auto-populate details from allocation list

✅ **File Number Preview**:
- Real-time preview of generated file number
- Tracking ID display
- Land use and purpose indicators

✅ **Validation**:
- Required field validation
- Serial number conflict detection
- Format validation

✅ **Override Capability**:
- Manual year override
- Manual serial number override

---

## Dependencies

The modal automatically loads all required dependencies:

- **CSS**: Select2, global file number modal styles
- **JavaScript**: Alpine.js (from your app.js), Select2, MLS generation logic
- **Icons**: Lucide icons (automatically initialized)

No additional setup required!

---

## Backend Routes

The modal submits to the existing route:
```php
POST /file-numbers/store
```

Ensure your controller (`FileNumberController@store`) is set up to handle the form submission.

---

## Data Requirements

The modal automatically fetches:
- LGAs from the database
- Land Uses
- Prefixes with land use mappings
- Unallocated entries from the allocation list
- Commission sheets for validation

All data is loaded when the component is included - no manual data passing required.

---

## Styling

The modal uses Tailwind CSS classes that match your existing application design. No additional styling configuration needed.

---

## Troubleshooting

### Modal doesn't open
- Ensure `lucide.createIcons()` is called after the modal is shown
- Check browser console for JavaScript errors
- Verify Alpine.js is loaded in your app.js

### Form submission fails
- Verify the route `/file-numbers/store` exists
- Check CSRF token is present
- Ensure `FileNumberController` has the `store` method

### Data not loading
- Check database connection (SQL Server)
- Verify models exist: `LandUse`, `Prefix`, `AllocationListEntry`
- Check table permissions

### Callback not firing
- Ensure callback function name matches exactly: `commissionFileNoSuccessCallback`
- Define callback BEFORE including the modal component
- Check for JavaScript errors in console

---

## File Locations

- **Main Component**: `resources/views/components/commission-fileno-modal-include.blade.php`
- **JavaScript Logic**: Included from `resources/views/generate_fileno/mls_js.blade.php`
- **Controller**: `app/Http/Controllers/FileNumberController.php`
- **Routes**: Defined in your routes files (check `routes/web.php` or `routes/app3.php`)

---

## Support

For issues or questions:
1. Check the original implementation in `resources/views/generate_fileno/mlsfno.blade.php`
2. Review the JavaScript logic in `resources/views/generate_fileno/mls_js.blade.php`
3. Consult the FileNumberController for backend logic

---

## Changelog

### v1.0.0 (February 2026)
- Initial conversion to reusable component
- Added OP Resettlement application type
- Maintained all original functionality
- Added global trigger functions
- Created comprehensive documentation

---

**Last Updated**: February 28, 2026
**Compatible With**: Laravel 9, KLAES GIS EDMS System
**Author**: KLAES Development Team
