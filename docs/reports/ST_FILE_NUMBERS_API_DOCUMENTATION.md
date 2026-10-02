# ST File Numbers Global API Documentation

## Overview
This document describes the global API endpoints for retrieving ST file numbers from the `st_file_numbers` table. These APIs are designed to be reusable across the entire system for populating dropdowns, auto-filling forms, or integrating with external systems.

## Base URL
```
/api/file-numbers/
```

## Endpoints

### 1. Get All ST File Numbers
**Endpoint:** `GET /api/file-numbers/st-all`

Retrieves all ST file numbers with optional filtering and search capabilities.

#### Query Parameters
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `search` | string | Search across file numbers, names, corporate names | `?search=ST-RES-2025` |
| `land_use` | string | Filter by land use | `?land_use=Residential` |
| `year` | integer | Filter by year | `?year=2025` |
| `file_no_type` | string | Filter by file type | `?file_no_type=PRIMARY` |
| `status` | string | Filter by status | `?status=active` |
| `applicant_type` | string | Filter by applicant type | `?applicant_type=Individual` |
| `order_by` | string | Order by field | `?order_by=created_at` |
| `order_direction` | string | Order direction (asc/desc) | `?order_direction=desc` |
| `limit` | integer | Limit results | `?limit=50` |

#### Response Format
```json
{
  "status": "success",
  "message": "ST File numbers fetched successfully.",
  "count": 25,
  "data": [
    {
      "id": 11,
      "np_fileno": "ST-RES-2025-1",
      "fileno": "ST-RES-2025-1-001",
      "mls_fileno": "MLS-2025-001",
      "land_use": "Residential",
      "land_use_code": "RES",
      "serial_no": 1,
      "unit_sequence": 1,
      "year": 2025,
      "file_no_type": "PRIMARY",
      "parent_id": null,
      "mother_application_id": null,
      "subapplication_id": null,
      "status": "active",
      "used_at": "2025-10-10T10:00:00.000Z",
      "tra": null,
      "applicant_type": "Individual",
      "applicant_title": "Mr.",
      "first_name": "John",
      "middle_name": "Michael",
      "surname": "Smith",
      "corporate_name": null,
      "rc_number": null,
      "multiple_owners_names": null,
      "created_by": 1,
      "created_at": "2025-10-10T09:00:00.000Z",
      "updated_at": "2025-10-10T09:00:00.000Z",
      "display_name": "John Michael Smith",
      "full_file_number": "MLS-2025-001"
    }
  ]
}
```

#### Example Usage
```javascript
// Fetch all file numbers
fetch('/api/file-numbers/st-all')
  .then(response => response.json())
  .then(data => {
    if (data.status === 'success') {
      console.log(`Loaded ${data.count} file numbers`);
      populateDropdown(data.data);
    }
  });

// Search for specific file numbers
fetch('/api/file-numbers/st-all?search=ST-RES-2025&land_use=Residential')
  .then(response => response.json())
  .then(data => {
    // Handle filtered results
  });
```

### 2. Get ST File Number Statistics
**Endpoint:** `GET /api/file-numbers/st-stats`

Retrieves summary statistics and breakdowns for ST file numbers.

#### Response Format
```json
{
  "status": "success",
  "message": "ST File number statistics fetched successfully.",
  "data": {
    "summary": {
      "total_records": 150,
      "unique_land_uses": 4,
      "unique_years": 3,
      "unique_file_types": 3,
      "active_records": 120,
      "reserved_records": 30
    },
    "land_use_breakdown": [
      {"land_use": "Residential", "count": 85},
      {"land_use": "Commercial", "count": 45},
      {"land_use": "Industrial", "count": 15},
      {"land_use": "Mixed-Use", "count": 5}
    ],
    "year_breakdown": [
      {"year": 2025, "count": 100},
      {"year": 2024, "count": 35},
      {"year": 2023, "count": 15}
    ]
  }
}
```

### 3. Get Dropdown Data
**Endpoint:** `GET /api/file-numbers/st-dropdown-data`

Retrieves unique values for populating dropdown filters.

#### Response Format
```json
{
  "status": "success",
  "message": "ST Dropdown data fetched successfully.",
  "data": {
    "land_uses": ["Residential", "Commercial", "Industrial", "Mixed-Use"],
    "years": [2025, 2024, 2023],
    "file_types": ["PRIMARY", "SUA", "PUA"],
    "statuses": ["active", "reserved", "expired"],
    "applicant_types": ["Individual", "Corporate", "Multiple"]
  }
}
```

## Error Handling

All endpoints return standardized error responses:

```json
{
  "success": false,
  "message": "Failed to load ST File Numbers files: [error details]"
}
```

## Frontend Integration Examples

### 1. Auto-fill Form Fields
```javascript
async function autoFillForm(fileNumberId) {
  try {
    const response = await fetch(`/api/file-numbers/st-all?search=${fileNumberId}`);
    const result = await response.json();
    
    if (result.status === 'success' && result.data.length > 0) {
      const fileData = result.data[0];
      
      // Auto-fill form fields
      document.getElementById('landUse').value = fileData.land_use;
      document.getElementById('year').value = fileData.year;
      document.getElementById('applicantName').value = fileData.display_name;
      document.getElementById('fileType').value = fileData.file_no_type;
      
      Swal.fire({
        icon: 'success',
        title: 'Form Auto-filled',
        text: 'Form fields populated successfully!',
        timer: 2000
      });
    }
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to load file number data'
    });
  }
}
```

### 2. Populate Dropdown with Search
```javascript
async function populateFileNumberDropdown(selectElement) {
  try {
    const response = await fetch('/api/file-numbers/st-all?limit=100');
    const result = await response.json();
    
    if (result.status === 'success') {
      // Clear existing options
      selectElement.innerHTML = '<option value="">Select file number...</option>';
      
      // Add file numbers to dropdown
      result.data.forEach(item => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = `${item.full_file_number} - ${item.display_name}`;
        option.dataset.fileData = JSON.stringify(item);
        selectElement.appendChild(option);
      });
    }
  } catch (error) {
    console.error('Error populating dropdown:', error);
  }
}
```

### 3. Dynamic Search with Debouncing
```javascript
let searchTimeout;

function setupSearchWithDebounce(inputElement, resultsContainer) {
  inputElement.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    
    searchTimeout = setTimeout(async () => {
      const searchTerm = this.value;
      
      if (searchTerm.length < 2) {
        resultsContainer.innerHTML = '';
        return;
      }
      
      try {
        const response = await fetch(`/api/file-numbers/st-all?search=${encodeURIComponent(searchTerm)}&limit=10`);
        const result = await response.json();
        
        if (result.status === 'success') {
          displaySearchResults(result.data, resultsContainer);
        }
      } catch (error) {
        console.error('Search error:', error);
      }
    }, 300); // 300ms debounce
  });
}
```

## Testing

Use the provided test page: `test_st_file_numbers_api.html`

The test page demonstrates:
- ✅ Loading and displaying all file numbers
- ✅ Filtering by multiple criteria
- ✅ Search functionality
- ✅ Statistics display
- ✅ Auto-filling form fields from selected records
- ✅ Error handling with SweetAlert notifications
- ✅ Responsive design with Tailwind CSS

## Performance Notes

- Use the `limit` parameter for large datasets
- Implement pagination for frontend display
- Use search filters to reduce data transfer
- Consider caching dropdown data on the frontend
- The API includes computed fields (`display_name`, `full_file_number`) for easier frontend usage

## Security

- All endpoints are protected by Laravel's middleware
- Input validation and SQL injection protection included
- CORS headers configured for cross-origin requests if needed