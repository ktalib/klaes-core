# File Number Search API Documentation

## Overview

This global API provides comprehensive file number search functionality for the KLAES application. It allows you to search and retrieve file number records from the `fileNumber` table with optimized performance and flexible search capabilities.

## Database Table Structure

The API queries the `fileNumber` table with the following key columns:
- `kangisFileNo` - Original KANGIS file number
- `mlsfNo` - Ministry of Land Survey file number  
- `NewKANGISFileNo` - New KANGIS file number format
- `FileName` - Property/file descriptive name
- `decommissioning_reason` - Reason for decommissioning (if applicable)
- `is_decommissioned` - Status flag (NULL or 0 = Active, 1 = Decommissioned)

## API Endpoints

### 1. Search File Numbers
**Endpoint:** `GET /file-numbers/api/search`

**Description:** Search file numbers with pagination. Returns top 10 results by default, supports searching for longer record sets.

**Parameters:**
- `query` (string, optional) - Search term to filter results
- `limit` (integer, optional, default: 10) - Number of results per page
- `page` (integer, optional, default: 1) - Page number for pagination

**Query Logic:**
- Searches across: `kangisFileNo`, `mlsfNo`, `NewKANGISFileNo`, `FileName`
- Only returns active records (`is_decommissioned` = NULL or 0)
- Results ordered by `created_at` DESC (most recent first)

**Example Request:**
```javascript
// Search for specific file number
fetch('/file-numbers/api/search?query=KLA/2024&limit=10&page=1')

// Get first 10 active files (no search query)
fetch('/file-numbers/api/search?limit=10')
```

**Example Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 123,
            "kangis_file_no": "KLA/2024/001",
            "mlsf_no": "MLSF-2024-001",
            "new_kangis_file_no": "NKLA/2024/001",
            "file_name": "Victoria Island Property",
            "display_name": "KLA/2024/001 - Victoria Island Property",
            "search_text": "KLA/2024/001 MLSF-2024-001 NKLA/2024/001 Victoria Island Property",
            "status": "Active",
            "decommissioning_reason": null,
            "created_at": "2024-01-15 10:30:00",
            "updated_at": "2024-01-15 10:30:00"
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 10,
        "total": 150,
        "total_pages": 15,
        "has_more": true
    },
    "query": "KLA/2024",
    "timestamp": "2024-01-15T10:30:00.000Z"
}
```

### 2. Get Top File Numbers
**Endpoint:** `GET /file-numbers/api/top`

**Description:** Get the top 10 most recent active file numbers. Optimized for quick access.

**Parameters:** None

**Example Request:**
```javascript
fetch('/file-numbers/api/top')
```

**Example Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 123,
            "kangis_file_no": "KLA/2024/001",
            "mlsf_no": "MLSF-2024-001",
            "new_kangis_file_no": "NKLA/2024/001",
            "file_name": "Victoria Island Property",
            "display_name": "KLA/2024/001 - Victoria Island Property",
            "search_text": "KLA/2024/001 MLSF-2024-001 NKLA/2024/001 Victoria Island Property",
            "status": "Active",
            "created_at": "2024-01-15 10:30:00",
            "updated_at": "2024-01-15 10:30:00"
        }
    ],
    "count": 10,
    "timestamp": "2024-01-15T10:30:00.000Z"
}
```

### 3. Get File Number Details
**Endpoint:** `GET /file-numbers/api/details/{id}`

**Description:** Get detailed information for a specific file number by ID.

**Parameters:**
- `id` (integer, required) - File number record ID

**Example Request:**
```javascript
fetch('/file-numbers/api/details/123')
```

**Example Response:**
```json
{
    "success": true,
    "data": {
        "id": 123,
        "kangis_file_no": "KLA/2024/001",
        "mlsf_no": "MLSF-2024-001",
        "new_kangis_file_no": "NKLA/2024/001",
        "file_name": "Victoria Island Property",
        "display_name": "KLA/2024/001 - Victoria Island Property",
        "search_text": "KLA/2024/001 MLSF-2024-001 NKLA/2024/001 Victoria Island Property",
        "status": "Active",
        "decommissioning_reason": null,
        "created_by": "john.doe",
        "updated_by": "jane.smith",
        "location": "Lagos Office",
        "source": "Generated",
        "commissioning_date": "2024-01-15 10:30:00",
        "created_at": "2024-01-15 10:30:00",
        "updated_at": "2024-01-15 10:30:00"
    },
    "timestamp": "2024-01-15T10:30:00.000Z"
}
```

## Usage Examples

### Frontend JavaScript Integration

#### Basic Search Implementation
```javascript
// Initialize file number search
async function searchFileNumbers(query) {
    try {
        const response = await fetch(`/file-numbers/api/search?query=${encodeURIComponent(query)}&limit=10`);
        const data = await response.json();
        
        if (data.success) {
            displayResults(data.data);
            updatePagination(data.pagination);
        } else {
            showError('Search failed');
        }
    } catch (error) {
        console.error('Search error:', error);
        showError('Network error occurred');
    }
}

// Load top file numbers on page load
async function loadTopFileNumbers() {
    try {
        const response = await fetch('/file-numbers/api/top');
        const data = await response.json();
        
        if (data.success) {
            displayResults(data.data);
        }
    } catch (error) {
        console.error('Load error:', error);
    }
}
```

#### Debounced Search (Recommended)
```javascript
let searchTimeout;

function debounceFileNumberSearch(query) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchFileNumbers(query);
    }, 300); // 300ms delay
}

// Usage in input handler
document.getElementById('fileNumberSearch').addEventListener('input', function(e) {
    const query = e.target.value.trim();
    if (query.length >= 2) {
        debounceFileNumberSearch(query);
    } else if (query.length === 0) {
        loadTopFileNumbers(); // Show top 10 when empty
    }
});
```

### Backend Laravel Integration

#### Using in Controllers
```php
use App\Http\Controllers\FileNumberController;

class YourController extends Controller
{
    public function someMethod()
    {
        // Get file number controller instance
        $fileNumberController = new FileNumberController();
        
        // Search file numbers programmatically
        $request = new Request(['query' => 'KLA/2024', 'limit' => 5]);
        $response = $fileNumberController->searchFileNumbers($request);
        $data = json_decode($response->getContent(), true);
        
        if ($data['success']) {
            $fileNumbers = $data['data'];
            // Process file numbers...
        }
    }
}
```

#### Direct Model Usage
```php
use App\Models\FileNumber;

// Search active file numbers
$fileNumbers = FileNumber::active()
    ->where('kangisFileNo', 'LIKE', '%KLA/2024%')
    ->orWhere('mlsfNo', 'LIKE', '%KLA/2024%')
    ->limit(10)
    ->get();

// Get top 10 recent files
$topFiles = FileNumber::active()
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

## Performance Optimizations

### Database Indexes (Recommended)
```sql
-- Recommended indexes for optimal performance
CREATE INDEX idx_filenumber_active ON fileNumber (is_decommissioned, created_at DESC);
CREATE INDEX idx_filenumber_kangis ON fileNumber (kangisFileNo);
CREATE INDEX idx_filenumber_mlsf ON fileNumber (mlsfNo);
CREATE INDEX idx_filenumber_newkangis ON fileNumber (NewKANGISFileNo);
CREATE INDEX idx_filenumber_filename ON fileNumber (FileName);
```

### Caching Strategy
- API responses are not cached by default
- Consider implementing Redis cache for frequently accessed top file numbers
- Search results are real-time to ensure data accuracy

### Query Optimization
- Uses `LIKE` queries with proper indexing
- Limits results to prevent large data transfers
- Orders by `created_at DESC` for recent-first results
- Only queries active records (performance boost)

## Error Handling

### API Error Responses
```json
{
    "success": false,
    "error": "Error description",
    "message": "Detailed error message",
    "timestamp": "2024-01-15T10:30:00.000Z"
}
```

### Common Error Codes
- `404` - File number not found (details endpoint)
- `500` - Database connection or query error
- `422` - Invalid request parameters

### Frontend Error Handling
```javascript
async function handleApiCall() {
    try {
        const response = await fetch('/file-numbers/api/search');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'API call failed');
        }
        
        return data;
    } catch (error) {
        console.error('API Error:', error);
        // Show user-friendly error message
        showErrorToUser('Unable to load file numbers. Please try again.');
        return null;
    }
}
```

## Security Considerations

### Authentication
- All endpoints require authentication (`auth` middleware)
- XSS protection enabled (`XSS` middleware)

### Data Sanitization
- Query parameters are properly escaped
- SQL injection protection via Eloquent ORM
- Output is JSON-encoded for safety

### Access Control
- Only active file numbers are returned
- Decommissioned files are filtered out
- No sensitive internal data exposed

## Integration Examples

### Caveat Form Integration
The API is already integrated into the caveat form file number selector:

```html
<!-- File Number Selector HTML -->
<div class="file-number-selector">
    <input type="text" id="fileNumberSearch" placeholder="Search file numbers...">
    <div id="fileNumberDropdown" class="dropdown hidden">
        <div id="fileNumberLoading">Loading...</div>
        <div id="fileNumberResults"></div>
        <div id="fileNumberNoResults">No results found</div>
    </div>
</div>
```

### Custom Component Integration
```javascript
// Create a reusable file number picker component
class FileNumberPicker {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            placeholder: 'Search file numbers...',
            limit: 10,
            showTop: true,
            ...options
        };
        this.init();
    }
    
    async init() {
        this.render();
        this.attachEvents();
        if (this.options.showTop) {
            await this.loadTopFiles();
        }
    }
    
    async loadTopFiles() {
        // Implementation using the API...
    }
    
    async search(query) {
        // Implementation using the API...
    }
}

// Usage
const filePicker = new FileNumberPicker('myFileSelector', {
    placeholder: 'Select a file number...',
    limit: 15
});
```

## Testing

### API Testing
```bash
# Test search endpoint
curl -X GET "/file-numbers/api/search?query=KLA&limit=5" \
     -H "Authorization: Bearer your-token"

# Test top files endpoint  
curl -X GET "/file-numbers/api/top" \
     -H "Authorization: Bearer your-token"

# Test details endpoint
curl -X GET "/file-numbers/api/details/123" \
     -H "Authorization: Bearer your-token"
```

### Unit Tests
Create tests in `tests/Feature/FileNumberApiTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\FileNumber;

class FileNumberApiTest extends TestCase
{
    public function test_search_file_numbers()
    {
        $response = $this->get('/file-numbers/api/search?query=KLA');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => [
                             'id',
                             'kangis_file_no',
                             'mlsf_no',
                             'display_name',
                             'status'
                         ]
                     ],
                     'pagination'
                 ]);
    }
}
```

## Support and Maintenance

### Monitoring
- Monitor API response times
- Track search query patterns
- Monitor database performance

### Logging
- API calls are logged in Laravel logs
- Database query performance logging
- Error tracking and alerting

### Updates
- API versioning strategy: `/file-numbers/api/v1/...`
- Backward compatibility maintained
- Database migration scripts included

---

## Quick Start Checklist

1. ✅ **Database Setup**: Ensure `fileNumber` table exists with proper structure
2. ✅ **Routes**: API routes are registered in `routes/web.php`
3. ✅ **Controller**: `FileNumberController` contains API methods
4. ✅ **Model**: `FileNumber` model configured for SQL Server connection
5. ✅ **Frontend**: JavaScript integration in caveat form
6. ⚠️ **Indexes**: Add recommended database indexes for performance
7. ⚠️ **Testing**: Run API tests to ensure functionality
8. ⚠️ **Caching**: Consider implementing cache for frequently accessed data

## Troubleshooting

### Common Issues

1. **"Table 'fileNumber' doesn't exist"**
   - Check database connection in `config/database.php`
   - Verify table name case sensitivity in SQL Server

2. **"No results returned"**
   - Check `is_decommissioned` column values
   - Verify data exists in the table
   - Check search query formatting

3. **"500 Internal Server Error"**
   - Check Laravel logs in `storage/logs/`
   - Verify SQL Server connection
   - Check database permissions

4. **"Slow API responses"**
   - Add recommended database indexes
   - Check query execution plans
   - Consider implementing result caching

### Debug Commands
```bash
# Test database connection
php artisan tinker --execute="DB::connection('sqlsrv')->getPdo(); echo 'Connected';"

# Check table structure
php artisan tinker --execute="use Illuminate\Support\Facades\Schema; dd(Schema::connection('sqlsrv')->getColumnListing('fileNumber'));"

# Test model query
php artisan tinker --execute="use App\Models\FileNumber; dd(FileNumber::active()->count());"
```

---

**Last Updated:** September 7, 2025  
**API Version:** 1.0  
**Laravel Version:** 8.x  
**Database:** SQL Server (sqlsrv connection)
