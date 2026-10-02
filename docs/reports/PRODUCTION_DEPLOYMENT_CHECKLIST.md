# Production Deployment Checklist - Land Use Update

## Files That Must Be Copied to Production (6 files)

### 1. Backend PHP Files (3 files)
- ✅ `app/Http/Controllers/PrimaryFormController.php` - CSV processing and validation
- ✅ `app/Helpers/SectionalTitleHelper.php` - Database insertion logic  
- ✅ Database: `buyer_list` table (needs `land_use` column)

### 2. Frontend Files (3 files)
- ✅ `resources/views/primaryform/partials/steps/step4-buyers.blade.php` - Form UI
- ✅ `public/js/primaryform/buyers.js` - Main JavaScript file
- ✅ `resources/views/primaryform/assets/js/buyers.js` - Backup JavaScript

## Issue Diagnosis: Land Use Field Disappearing

The problem is likely in the JavaScript CSV processing. Let me check the exact function that handles this.