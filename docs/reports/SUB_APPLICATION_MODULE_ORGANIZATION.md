# Sub Application Module - File Organization Documentation

## 📁 Directory Structure

```
app/
├── public/
│   ├── css/
│   │   └── sub-application/
│   │       └── applicant-type.css          # Applicant type selection styles
│   └── js/
│       └── sub-application/
│           ├── applicant-type.js           # Applicant type selector logic
│           ├── identification-preview.js   # ID document preview
│           ├── property-location.js        # Property location auto-generator
│           ├── states-lga.js              # Nigeria states & LGAs API
│           └── sua-land-use.js            # SUA land use handler
│
└── resources/
    └── views/
        └── sectionaltitling/
            ├── sub_application.blade.php           # Main form template
            └── partials/
                └── subapplication/
                    ├── scripts.blade.php          # JavaScript includes
                    └── styles.blade.php           # CSS includes
```

## 🎯 Module Purposes

### JavaScript Modules

#### 1. **applicant-type.js**
- **Purpose**: Handles applicant type selection (Individual, Corporate, Multiple Owners)
- **Functions**: 
  - `setApplicantType(type)` - Set and visually update applicant type
  - `updateApplicantTypeVisuals(type)` - Update UI visual feedback
- **Dependencies**: None
- **Events**: Listens to radio button changes

#### 2. **states-lga.js**
- **Purpose**: Fetches Nigerian states and LGAs from external API
- **Functions**:
  - `selectLGA(target)` - Global function to load LGAs for selected state
  - `loadStates()` - Load all states on init
- **API**: `https://nga-states-lga.onrender.com`
- **Dependencies**: Fetch API
- **Elements**: 
  - `#ownerState` - State select dropdown
  - `#ownerLga` - LGA select dropdown

#### 3. **property-location.js**
- **Purpose**: Auto-generates property location from unit details
- **Functions**:
  - `updatePropertyLocation()` - Global function to rebuild property location
  - `loadKanoLGAs()` - Load Kano-specific LGAs
- **Triggers**: Block No, Floor No, Unit No, District, LGA changes
- **Elements**:
  - `#block_number`, `#floor_number`, `#unit_number`
  - `#unit_district`, `#unit_lga`, `#unit_state`
  - `#property_location` - Output field

#### 4. **identification-preview.js**
- **Purpose**: Preview uploaded identification documents
- **Supported Formats**: 
  - Images (jpg, png, etc.)
  - PDF documents
- **Elements**:
  - `#identification_image` - File input
  - `#identification_preview` - Preview container

#### 5. **sua-land-use.js**
- **Purpose**: Handles land use field for Standalone Unit Applications
- **Functions**: Form submission handler for land_use field
- **Elements**:
  - `#sua_land_use` - Select dropdown
  - `#sua_land_use_hidden` - Hidden input
- **Note**: Only active for SUA forms

### CSS Modules

#### 1. **applicant-type.css**
- **Purpose**: Visual feedback styles for applicant type selection
- **Features**:
  - Checked state styling
  - Hover effects
  - Focus states for accessibility
  - Radio button custom styling

### Blade Partials

#### 1. **scripts.blade.php**
- **Purpose**: Centralized JavaScript asset loading
- **Load Order**:
  1. Third-party libraries (SweetAlert, jQuery, Select2)
  2. Custom modules (alphabetically)
  3. Draft autosave functionality
- **Usage**: `@include('sectionaltitling.partials.subapplication.scripts')`

#### 2. **styles.blade.php**
- **Purpose**: Centralized CSS asset loading
- **Includes**:
  - External CSS libraries
  - Custom CSS modules
  - Critical inline styles
- **Usage**: `@include('sectionaltitling.partials.subapplication.styles')`

## 🔧 Implementation Guide

### Step 1: Replace Inline Scripts in sub_application.blade.php

**Before:**
```blade
<script>
// Inline JavaScript here
</script>
```

**After:**
```blade
@include('sectionaltitling.partials.subapplication.scripts')
```

### Step 2: Replace Inline Styles

**Before:**
```blade
<style>
/* Inline CSS here */
</style>
```

**After:**
```blade
@include('sectionaltitling.partials.subapplication.styles')
```

### Step 3: Remove Duplicate Script Tags

**Remove these from sub_application.blade.php:**
- SUA land use script (line ~556-595)
- Applicant type script (line ~903-1095)
- States/LGA script (line ~1154-1200)
- Identification preview script (line ~1273-1300)
- Property location script (line ~1348-1400)

### Step 4: Add Includes at Appropriate Locations

**In the `<head>` section:**
```blade
@include('sectionaltitling.partials.subapplication.styles')
```

**Before closing `</body>` tag:**
```blade
@include('sectionaltitling.partials.subapplication.scripts')
```

## 🐛 Debugging Guide

### Verify Module Loading

Open browser console and check for:
```
✅ Sub Application Scripts Loaded
[Sub App] All modules initialized
[Applicant Type] Module initialized
[States-LGA] Module initialized
[Property Location] Module initialized
[ID Preview] Module initialized (if elements found)
[SUA Land Use] Module initialized (if elements found)
```

### Check Function Availability

Test in console:
```javascript
// Should be defined globally
typeof setApplicantType === 'function'  // Should return true
typeof selectLGA === 'function'          // Should return true
typeof updatePropertyLocation === 'function'  // Should return true
```

### Common Issues

#### 1. Functions not defined
**Symptom**: `Uncaught ReferenceError: setApplicantType is not defined`
**Solution**: Check that `scripts.blade.php` is included and scripts load in order

#### 2. Elements not found
**Symptom**: Console shows "Elements not found, skipping initialization"
**Solution**: This is normal if elements don't exist on current page variant (SUA vs regular)

#### 3. States/LGAs not loading
**Symptom**: Empty dropdown after page load
**Solution**: Check network tab for API calls, verify API is accessible

#### 4. Styling not applied
**Symptom**: Visual feedback not working
**Solution**: Verify `applicant-type.css` is loaded, check for CSS conflicts

## 📊 Performance Benefits

### Before Cleanup:
- **File Size**: ~3032 lines in single file
- **Inline Scripts**: 7+ separate script blocks
- **Inline Styles**: 3+ style blocks
- **Maintainability**: Low (hard to debug, find, and modify)
- **Caching**: Poor (scripts reload on every page view)
- **Code Reuse**: None

### After Cleanup:
- **File Size**: ~2400 lines (reduced by ~20%)
- **Inline Scripts**: 0 (all externalized)
- **Inline Styles**: Minimal critical CSS only
- **Maintainability**: High (modular, organized by feature)
- **Caching**: Excellent (browser caches external JS/CSS)
- **Code Reuse**: Possible across multiple forms

## 🔄 Migration Checklist

- [ ] Create all JavaScript module files
- [ ] Create all CSS module files
- [ ] Create Blade partial files (scripts.blade.php, styles.blade.php)
- [ ] Add style includes to `<head>` section
- [ ] Add script includes before `</body>`
- [ ] Remove inline `<script>` blocks from main file
- [ ] Remove inline `<style>` blocks from main file
- [ ] Test all functionality:
  - [ ] Applicant type selection
  - [ ] States and LGAs loading
  - [ ] Property location auto-generation
  - [ ] Identification preview
  - [ ] SUA land use handling
- [ ] Clear Laravel cache
- [ ] Clear browser cache
- [ ] Test in different browsers
- [ ] Verify console shows no errors

## 🎓 Best Practices Going Forward

1. **Keep JavaScript Modular**: Each feature in its own file
2. **Use IIFE Pattern**: Wrap code in `(function() { ... })()` to avoid global pollution
3. **Console Logging**: Prefix logs with `[ModuleName]` for easy debugging
4. **Error Handling**: Always catch API errors and show user-friendly messages
5. **Event Listeners**: Always check element existence before adding listeners
6. **Documentation**: Update this file when adding new modules

## 📝 Adding New Modules

To add a new feature module:

1. Create new file: `public/js/sub-application/feature-name.js`
2. Use the IIFE pattern
3. Add initialization in `DOMContentLoaded`
4. Export global functions to `window` if needed
5. Add to `scripts.blade.php` in correct load order
6. Update this documentation

Example template:
```javascript
/**
 * Feature Name
 * Description of what it does
 */
(function() {
    'use strict';

    // Private functions
    function initFeature() {
        console.log('[Feature] Initialized');
        // Your code here
    }

    // Public functions (if needed)
    window.publicFunction = function() {
        // Accessible globally
    };

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        initFeature();
    });
})();
```

## 🚀 Next Steps

1. Apply these changes to `sub_application.blade.php`
2. Test thoroughly in development
3. Monitor console for errors
4. Verify all form functionality works
5. Deploy to staging for QA testing
6. Document any issues found
7. Deploy to production

---

**Last Updated**: October 6, 2025  
**Maintained By**: Development Team  
**Version**: 1.0.0
