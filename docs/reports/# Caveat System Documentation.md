# Caveat System Documentation

## Overview

The Caveat system is a comprehensive property registration management application built with Laravel and modern JavaScript. It provides functionality to place, lift, and manage caveats on property files with integrated file number selection and document tracking capabilities.

## System Architecture

### Backend Components
- **Framework**: Laravel (PHP)
- **Database**: SQL Server with `sqlsrv` connection
- **Authentication**: Laravel Auth middleware
- **File Storage**: Laravel Storage (public disk)

### Frontend Components
- **CSS Framework**: TailwindCSS
- **JavaScript**: Vanilla JS with modular architecture
- **Icons**: FontAwesome 6.5.2
- **PDF Generation**: jsPDF
- **Interactive Features**: Alpine.js for modal components

## Core Features

### 1. Caveat Management
- **Place New Caveat**: Create new property caveats with comprehensive form validation
- **Lift Existing Caveat**: Remove or modify existing caveats
- **Caveat Logs**: View and search historical caveat records

### 2. File Number Integration
- **Smart Search**: API-powered file number search with real-time results
- **Manual Entry**: Advanced modal for creating custom file numbers
- **Multiple Formats**: Support for MLSF, KANGIS, and New KANGIS file number formats

### 3. Registration System
- **Auto-generation**: Automatic registration number creation in format `[Serial]/[Page]/[Volume]`
- **Real-time Updates**: Dynamic form updates as user inputs data
- **Validation**: Comprehensive form validation with visual feedback

## File Structure

```
app/
├── resources/views/caveat/
│   ├── index.blade.php                 # Main caveat interface
│   ├── assets/js_dynamic.blade.php     # Main JavaScript coordinator
│   └── partials/
│       └── manual_file_number_modal.blade.php  # File number modal
├── public/js/
│   ├── caveat-data.js                  # Core data and state management
│   ├── caveat-rendering.js             # UI rendering functions
│   ├── caveat-events.js                # Event handlers and form logic
│   ├── file-number-selector.js         # File number search functionality
│   └── manual-file-number-modal.js     # Advanced file number entry
├── app/Http/Controllers/
│   ├── CaveatController.php            # Main caveat operations
│   └── FileNumberController.php        # File number API endpoints
└── app/Models/
    ├── Caveat.php                      # Caveat model
    ├── FileNumber.php                  # File number model
    └── InstrumentType.php              # Instrument type lookup
```

## Database Schema

### Key Tables

#### `caveats`
```sql
- id (Primary Key)
- caveat_number (Unique identifier)
- file_number_id (Foreign key to file_numbers)
- instrument_type_id (Foreign key to instrument_types)
- encumbrance_type (Enum: Mortgage, Lien, Charge, etc.)
- serial_no
- page_no (Auto-filled from serial_no)
- volume_no
- registration_number (Generated: serial/page/volume)
- start_date
- end_date
- caveator_name
- caveator_address
- property_description
- status (active/lifted/expired)
- created_at
- updated_at
```

#### `file_numbers`
```sql
- id (Primary Key)
- kangis_file_no
- mlsf_no
- new_kangis_file_no
- file_name
- description
- is_decommissioned (0/1)
- created_at
- updated_at
```

#### `instrument_types`
```sql
- InstrumentTypeID (Primary Key)
- InstrumentName
- Description
- IsActive (0/1)
```

## API Endpoints

### File Number API
```
GET /file-numbers/api/search?query={term}&limit={num}
GET /file-numbers/api/top
GET /file-numbers/api/details/{id}
```

### Caveat API
```
POST /caveat/store
PUT /caveat/{id}/update
DELETE /caveat/{id}
GET /caveat/{id}
```

## JavaScript Modules

### 1. Core Data (`caveat-data.js`)
- State management for caveats and filters
- Mock data for development
- Utility functions for date/time handling
- Tab management functionality

### 2. Rendering (`caveat-rendering.js`)
- Dynamic list and table rendering
- Statistics calculation and display
- Status badge generation
- Search result filtering

### 3. Events (`caveat-events.js`)
- Form input event handlers
- Search functionality
- Auto-fill logic for registration numbers
- Form validation and submission

### 4. File Number Selector (`file-number-selector.js`)
- Real-time file number search
- API integration with debouncing
- Dropdown/popover management
- Selection state handling

### 5. Manual File Number Modal (`manual-file-number-modal.js`)
- Advanced file number creation
- Support for multiple file number formats
- Alpine.js integration
- Real-time preview and validation

## Key Features Detail

### Encumbrance Types
1. **Mortgage** - Legal interest by lender as security
2. **Lien** - Right to retain property until debt settled
3. **Charge** - Registered financial claim
4. **Leasehold Interest** - Property leased to another party
5. **Sub-Lease/Sub-Under Lease** - Further lease interests
6. **Easement/Right of Way** - Third party usage rights
7. **Court Order/Restraining Order** - Judicial restrictions
8. **Pending Litigation (Lis Pendens)** - Property under court case
9. **Power of Attorney** - Legal authority granted to another party
10. **Caution (General or Specific)** - Warning to restrict dealings
11. **Dispute/Investigation Tag** - Under regulatory review
12. **Deed of Assignment/Transfer Not Completed** - Pending transfer
13. **Probate/Letters of Administration** - Estate administration
14. **Government Acquisition/Reservation** - Government designation
15. **Unpaid Land Charges/Fees** - Outstanding charges

### File Number Formats

#### MLSF Files
- **Regular**: `RES-2024-0001`, `COM-2024-0572`
- **Conversion**: `CON-COM-2019-296`
- **Temporary**: `RES-2024-0001(T)`
- **Extension**: `RES-2024-0001 AND EXTENSION`
- **Miscellaneous**: `MISC-KN-001`
- **Special**: `SIT-2024-001`, `SLTR-001`
- **Old MLS**: `KN 5467`

#### KANGIS Files
- **Format**: `KNML 00001`, `MNKL 02500`
- **Auto-padding**: Numbers padded to 5 digits

#### New KANGIS Files
- **Format**: `KN1586`, `KN2500`
- **Simple concatenation**: Prefix + number

## User Interface

### Main Tabs
1. **Place New Caveat**: Form for creating new caveats
2. **Lift Existing Caveat**: Interface for modifying/removing caveats
3. **Existing Caveats Log**: Historical records with search/filter

### Key UI Components
- **Smart File Number Selector**: Search existing files or create new ones
- **Registration Particulars**: Auto-generating serial/page/volume system
- **Encumbrance Type Selector**: Dropdown with contextual descriptions
- **Date/Time Pickers**: Current date auto-fill with manual override
- **Status Indicators**: Visual badges for caveat status
- **Search & Filter**: Real-time filtering across all caveat data

## Installation & Setup

### Prerequisites
- PHP 8.0+
- Laravel 9.0+
- SQL Server with appropriate drivers
- Node.js for asset compilation

### Configuration
1. **Database**: Configure SQL Server connection in `.env`
2. **Storage**: Set up Laravel storage for document handling
3. **Assets**: Compile CSS/JS assets with Laravel Mix/Vite
4. **Permissions**: Ensure proper file system permissions

### Environment Variables
```env
DB_CONNECTION=sqlsrv
DB_HOST=VMI2583396\SQLEXPRESS
DB_PORT=1433
DB_DATABASE=klas
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## Security Features
- **CSRF Protection**: Laravel CSRF tokens on all forms
- **Authentication**: Required for all caveat operations
- **SQL Injection Prevention**: Eloquent ORM usage
- **XSS Protection**: Proper input sanitization
- **File Upload Validation**: Secure file handling

## Performance Optimizations
- **Debounced Search**: 300ms delay on search inputs
- **Pagination**: API endpoints support pagination
- **Lazy Loading**: Images and large content loads on demand
- **Cached Queries**: Frequently accessed data caching
- **Optimized Database Queries**: Proper indexing recommendations

## Development Guidelines

### Code Standards
- **PSR-4**: PHP autoloading standard
- **Laravel Conventions**: Following Laravel best practices
- **JavaScript ES6+**: Modern JavaScript features
- **Responsive Design**: Mobile-first approach with TailwindCSS

### Testing
- **Unit Tests**: PHPUnit for backend logic
- **Feature Tests**: End-to-end functionality testing
- **Browser Testing**: Cross-browser compatibility
- **API Testing**: Postman/Insomnia collections

## Troubleshooting

### Common Issues
1. **File Number Search Not Working**: Check API routes and database connection
2. **JavaScript Errors**: Verify all JS modules are loading in correct order
3. **Database Connection Issues**: Confirm SQL Server configuration
4. **Permission Denied**: Check Laravel storage permissions

### Debug Tools
- **Laravel Telescope**: Request debugging and profiling
- **Browser Dev Tools**: JavaScript debugging and network monitoring
- **Laravel Logs**: Comprehensive error logging
- **Database Profiler**: Query optimization tools

## Future Enhancements
- **Real-time Notifications**: WebSocket integration for live updates
- **Document Management**: PDF attachment and preview capabilities
- **Workflow Automation**: Automated caveat lifecycle management
- **Reporting Dashboard**: Analytics and reporting features
- **Mobile App**: React Native or Flutter mobile application
- **API Documentation**: Swagger/OpenAPI documentation