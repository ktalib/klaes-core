# Blind Scanning Code Organization

## Overview
The Blind Scanning functionality has been refactored and organized for better maintainability and structure.

## File Structure

### CSS
- `public/css/blind-scanning.css` - Custom styles for blind scanning interface

### JavaScript  
- `public/js/blind-scanning.js` - Main JavaScript class (`BlindScanningManager`)

### Blade Templates
- `resources/views/scanning/blind_scans.blade.php` - Clean main view with includes
- `resources/views/scanning/partials/` - Organized partial templates:
  - `header.blade.php` - Page header section
  - `upload-section.blade.php` - File upload and migration controls
  - `server-section.blade.php` - Server browser wrapper
  - `server-browser.blade.php` - File explorer interface
  - `logs-panel.blade.php` - Migration logs display
  - `records-section.blade.php` - Records table wrapper
  - `records-filters.blade.php` - Filter controls
  - `records-table.blade.php` - Data table with server-side data
  - `records-pagination.blade.php` - Pagination controls
  - `progress-modal.blade.php` - Upload progress modal

## Features Preserved
- ✅ File upload and migration functionality
- ✅ Server file browsing and preview
- ✅ Migration logs tracking
- ✅ Records management with filtering and pagination  
- ✅ Progress tracking and notifications
- ✅ File number modal integration

## Usage
The assets are now directly accessible at:
- `/css/blind-scanning.css`
- `/js/blind-scanning.js`

No build process required - files are ready to use.

## Class Structure
The JavaScript is organized in a `BlindScanningManager` class that:
- Handles file upload validation and migration
- Manages server file browsing
- Controls tab navigation 
- Manages records loading and filtering
- Integrates with the global file number modal

## Initialization
The manager is initialized in the blade file with proper configuration:
```javascript
window.blindScanManager = new BlindScanningManager(config);
```