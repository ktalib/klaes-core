# Primary Form Legacy Archive 📁

This folder contains files that were moved from the main `primaryform` directory to keep the active codebase clean and focused.

## Archived Files and Reasons:

### Backup Files
- `buyer_list.blade.php.backup` - Backup copy of buyer_list.blade.php
- `index_backup.blade.php` - Backup copy of main form (uses older structure)
- `summary.blade copy.php` - Copy of summary file

### Unused/Legacy Components
- `edms_step.blade.php` - Legacy EDMS step component (not referenced anywhere)
- `fileno.blade.php` - Old file number component (replaced by gis_fileno.blade.php)
- `global_fileno.blade.php` - Global file number component (not actively used)
- `js.blade.php` - Legacy JavaScript file (functionality moved to assets/js/)
- `passport.blade.php` - Passport component (not referenced in active forms)

### Alternative Implementations
- `index-organized.blade.php` - Alternative organized version of main form
- `livewire-index.blade.php` - Livewire version of index (minimal usage in routes)

### System Files
- `sync.ffs_db` - File synchronization database file

## Currently Active Files (NOT archived):

### Core Files
- `index.blade.php` - Main primary application form ✅
- `applicant.blade.php` - Applicant information component ✅
- `buyer_list.blade.php` - Buyer list component ✅
- `edms.blade.php` - Active EDMS integration ✅
- `gis_fileno.blade.php` - Active GIS file number component ✅
- `print-page.blade.php` - Print functionality ✅

### Active Folders
- `partials/` - All step components and print functionality ✅
- `assets/` - CSS and JavaScript assets ✅  
- `types/` - Property type components (commercial, residential, etc.) ✅

## Restoration Notes:
If any archived file is needed again, simply move it back to the main `primaryform` directory and update any references as needed.

---
**Archive Date:** September 26, 2025  
**Reason:** Code organization and cleanup to focus on actively used components