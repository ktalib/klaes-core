# Sectional Title Application System

## Overview

The **Sectional Title Application System** is a comprehensive Laravel-based web application designed for the Ministry of Land and Physical Planning to manage sectional title applications. This system handles the complete workflow from initial application submission through document management, buyer registration, and final conveyance processing.

## Features

### Core Functionality
- **Multi-step Application Form**: 5-step wizard for comprehensive data collection
- **Multiple Applicant Types**: Support for Individual, Corporate, and Multiple Owners
- **Document Management**: Secure file upload and storage for required documents
- **CSV Buyer Import**: Bulk import of buyer information via CSV files
- **Real-time Address Validation**: Integration with Nigerian States and LGAs API
- **Automated File Number Generation**: Smart file numbering system with multiple formats
- **Billing Integration**: Automatic fee calculation and billing record creation

### Document Types Supported
- Application Letter
- Building Plans
- Architectural Designs
- Ownership Documents
- Survey Plans
- Identity Documents (ID Cards, Driver's License, Voter's Card, etc.)
- Passport Photographs

### Data Management
- **Dual Database Architecture**: SQL Server primary, MySQL secondary
- **JSON Storage**: Final conveyance data stored as JSON for flexibility
- **Normalized Relations**: Buyer data stored in relational tables for queries
- **File Indexing**: Integration with EDMS (Electronic Document Management System)

## System Architecture

### Database Connections
```php
// Primary Database (SQL Server)
'sqlsrv' => [
    'driver' => 'sqlsrv',
    'host' => env('DB_HOST_SQLSRV'),
    'port' => env('DB_PORT_SQLSRV', '1433'),
    'database' => env('DB_DATABASE_SQLSRV'),
    'username' => env('DB_USERNAME_SQLSRV'),
    'password' => env('DB_PASSWORD_SQLSRV'),
],

// Secondary Database (MySQL)
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST_MYSQL'),
    'port' => env('DB_PORT_MYSQL', '3306'),
    'database' => env('DB_DATABASE_MYSQL'),
    'username' => env('DB_USERNAME_MYSQL'),
    'password' => env('DB_PASSWORD_MYSQL'),
]
```

### Key Models
All models use SQL Server connection by default:
```php
protected $connection = 'sqlsrv';
```

## Installation & Setup

### Prerequisites
- PHP 8.1+
- Laravel 10.x
- SQL Server 2019+
- MySQL 8.0+ (optional)
- Node.js & NPM

### Environment Configuration
```bash
# Database Configuration
DB_CONNECTION_SQLSRV=sqlsrv
DB_HOST_SQLSRV=your_sqlserver_host
DB_PORT_SQLSRV=1433
DB_DATABASE_SQLSRV=your_database_name
DB_USERNAME_SQLSRV=your_username
DB_PASSWORD_SQLSRV=your_password

# File Storage
FILESYSTEM_DISK=public
```

### Installation Steps
```bash
# Clone repository
git clone <repository-url>
cd sectional-title-app

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate --database=sqlsrv

# File storage setup
php artisan storage:link

# Asset compilation
npm run build
```

## Application Form Structure

### Step 1: Basic Information
- Applicant type selection (Individual/Corporate/Multiple Owners)
- Date information and land use classification
- File number selection and validation
- Applicant details (name, contact information)
- Property details (units, blocks, sections, plot size)
- Property address with real-time validation

### Step 2: Shared Areas
- Selection of common/shared areas
- Custom area specification

### Step 3: Document Upload
- Required document uploads with validation
- File type and size restrictions (5MB max)
- Preview functionality for uploaded files

### Step 4: Buyer Information
- **Manual Entry**: Individual buyer registration
- **CSV Import**: Bulk buyer data upload
- Real-time validation and preview

### Step 5: Summary & Submission
- Complete application review
- Final validation before submission
- Print-ready summary generation

## CSV Buyer Import Format

The system supports CSV files with the following headers:

```csv
Title,First Name,Middle Name,Surname,Address,Email,Phone,Unit Number,Unit Type,Unit Measurement
Mr.,John,Michael,Doe,123 Main St,john.doe@email.com,1234567890,A001,Apartment,50sqm
Mrs.,Jane,Elizabeth,Smith,456 Oak Ave,jane.smith@email.com,0987654321,B002,Condo,75sqm
```

### Supported CSV Headers (Case-insensitive)
- **Title**: Mr., Mrs., Dr., Prof, Chief, Miss
- **Names**: First Name/First_Name, Middle Name/Middle_Name, Surname/Last Name
- **Contact**: Address, Email, Phone
- **Unit Info**: Unit Number/Unit_Number/Unit No, Unit Type, Unit Measurement/Measurement

## Recommendations for Enhancement

### 1. AJAX Form Submission
**Current Issue**: The form uses traditional POST submission, which can be slow and doesn't provide real-time feedback.

**Recommended Implementation**:
```javascript
// Convert to AJAX submission
$('#primaryForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    Swal.fire({
        title: 'Submitting Application...',
        html: 'Processing your application...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    $.ajax({
        url: $(this).attr('action'),
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Application Submitted!',
                    text: 'Your application has been submitted successfully.',
                    confirmButtonText: 'Continue'
                }).then(() => {
                    window.location.href = response.redirect_url;
                });
            }
        },
        error: function(xhr) {
            const errors = xhr.responseJSON?.errors || {};
            let errorMessage = 'Please correct the following errors:\n';
            
            Object.keys(errors).forEach(field => {
                errorMessage += `- ${errors[field][0]}\n`;
            });
            
            Swal.fire({
                icon: 'error',
                title: 'Submission Failed',
                text: errorMessage,
                confirmButtonText: 'Fix Errors'
            });
        }
    });
});
```

### 2. Real-time Draft Saving
**Current Issue**: Users can lose data if they navigate away or encounter issues during the long form completion process.

**Recommended Implementation**:

#### Backend Route
```php
// routes/web.php
Route::post('/sectional-title/save-draft', [PrimaryFormController::class, 'saveDraft'])->name('sectional-title.save-draft');
```

#### Controller Method
```php
public function saveDraft(Request $request)
{
    $userId = Auth::id();
    $draftKey = 'sectional_title_draft_' . $userId;
    
    // Save to session or database
    session([$draftKey => $request->all()]);
    
    return response()->json([
        'success' => true,
        'message' => 'Draft saved successfully',
        'timestamp' => now()->toDateTimeString()
    ]);
}
```

#### Frontend Auto-save
```javascript
// Auto-save functionality
let draftTimer;
const AUTOSAVE_INTERVAL = 30000; // 30 seconds

function saveDraft() {
    const formData = new FormData($('#primaryForm')[0]);
    
    $.ajax({
        url: "{{ route('sectional-title.save-draft') }}",
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            // Show subtle success indicator
            showDraftSavedIndicator(response.timestamp);
        },
        error: function(xhr) {
            console.error('Draft save failed:', xhr);
        }
    });
}

// Auto-save on form changes
$('#primaryForm').on('input change', 'input, select, textarea', function() {
    clearTimeout(draftTimer);
    draftTimer = setTimeout(saveDraft, AUTOSAVE_INTERVAL);
});

// Save draft when navigating between steps
function goToStep(step) {
    saveDraft(); // Save current progress
    // ... existing step navigation code
}

// Draft recovery on page load
$(document).ready(function() {
    checkForExistingDraft();
});

function checkForExistingDraft() {
    $.get("{{ route('sectional-title.get-draft') }}", function(response) {
        if (response.has_draft) {
            Swal.fire({
                title: 'Draft Found',
                text: 'We found a previously saved draft. Would you like to restore it?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Restore Draft',
                cancelButtonText: 'Start Fresh'
            }).then((result) => {
                if (result.isConfirmed) {
                    restoreDraft(response.draft_data);
                }
            });
        }
    });
}
```

#### Draft Status Indicator
```html
<!-- Add to form header -->
<div id="draft-status" class="fixed top-4 right-4 z-50 hidden">
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded shadow-lg">
        <i class="fas fa-check-circle mr-2"></i>
        <span>Draft saved at <span id="save-timestamp"></span></span>
    </div>
</div>
```

### 3. Progressive Form Validation
```javascript
// Real-time validation per step
function validateStep(stepNumber) {
    const step = $(`#step${stepNumber}`);
    const requiredFields = step.find('[required]');
    let isValid = true;
    let errors = [];
    
    requiredFields.each(function() {
        const field = $(this);
        if (!field.val().trim()) {
            isValid = false;
            errors.push(`${field.attr('name')} is required`);
            field.addClass('border-red-500');
        } else {
            field.removeClass('border-red-500');
        }
    });
    
    return { isValid, errors };
}

// Validate before step navigation
function goToStep(step) {
    const currentStep = $('.form-section:visible').attr('id').replace('step', '');
    const validation = validateStep(currentStep);
    
    if (!validation.isValid && step > currentStep) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            html: 'Please fix the following errors:<br>' + validation.errors.join('<br>')
        });
        return;
    }
    
    // Save draft and proceed
    saveDraft();
    // ... continue with step navigation
}
```

### 4. File Upload with Progress
```javascript
// Enhanced file upload with progress tracking
function uploadFileWithProgress(file, progressCallback, successCallback, errorCallback) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    
    $.ajax({
        url: '/upload-document',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function() {
            const xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressCallback(percentComplete);
                }
            });
            return xhr;
        },
        success: successCallback,
        error: errorCallback
    });
}
```

## Security Considerations

- **File Upload Validation**: Strict MIME type checking and virus scanning
- **CSRF Protection**: All forms include CSRF tokens
- **XSS Prevention**: Input sanitization middleware
- **SQL Injection Prevention**: Parameterized queries through Eloquent ORM
- **Role-Based Access Control**: Permission-based access to different sections

## Performance Optimizations

- **Database Indexing**: Proper indexes on frequently queried columns
- **File Storage**: Use cloud storage for production environments
- **Caching**: Implement Redis for session and form data caching
- **Image Optimization**: Automatic image compression for uploaded files

## Troubleshooting

### Common Issues

1. **CSV Import Fails**
   - Verify CSV format matches expected headers
   - Check file encoding (UTF-8 recommended)
   - Ensure file size is under 5MB

2. **File Upload Issues**
   - Check PHP upload_max_filesize and post_max_size
   - Verify storage directory permissions
   - Confirm file types are allowed

3. **Database Connection Errors**
   - Verify SQL Server connection settings
   - Check firewall rules for database port
   - Ensure proper authentication credentials

## API Integration

The system integrates with:
- **Nigerian States & LGAs API**: Real-time address validation
- **File Management System**: Document indexing and retrieval
- **Billing System**: Automated fee calculation

## Contributing

1. Fork the repository
2. Create a feature branch
3. Follow PSR-12 coding standards
4. Write comprehensive tests
5. Submit a pull request

## License

This project is proprietary software developed for the Ministry of Land and Physical Planning.

## Support

For technical support or feature requests, contact the development team at [support@ministry.gov].

---

*Last updated: September 2025*