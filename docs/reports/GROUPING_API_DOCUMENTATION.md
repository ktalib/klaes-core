# Grouping API Documentation

## Overview
The Grouping API provides comprehensive access to the grouping table data with full CRUD operations, advanced search, filtering, pagination, and statistical analysis capabilities.

**Base URL:** `/api/grouping`

## Authentication
Currently, the API endpoints are public. In production, consider adding authentication middleware.

## Response Format
All API responses follow this standard format:
```json
{
    "success": true|false,
    "message": "Optional message",
    "data": {}, // Response data
    "error": "Error message if success=false"
}
```

## Endpoints

### 1. Get Totals and Statistics
**GET** `/api/grouping/totals`

Returns comprehensive statistics about land use distribution.

**Response Example:**
```json
{
    "success": true,
    "data": {
        "total_records": 2025000,
        "land_use_breakdown": [
            {
                "landuse": "COMMERCIAL",
                "count": 900000,
                "percentage": 44.44
            },
            {
                "landuse": "RESIDENTIAL", 
                "count": 675000,
                "percentage": 33.33
            },
            {
                "landuse": "AGRICULTURE",
                "count": 450000,
                "percentage": 22.22
            }
        ],
        "summary": {
            "most_common": "COMMERCIAL",
            "unique_land_uses": 3,
            "generated_at": "2025-10-25 12:55:27"
        }
    }
}
```

### 2. Get All Records (Paginated)
**GET** `/api/grouping`

Returns paginated list of grouping records with optional filtering.

**Query Parameters:**
- `page` (int): Page number (default: 1)
- `per_page` (int): Records per page (default: 25, max: 1000)
- `landuse` (string): Filter by land use type
- `year` (int): Filter by year
- `batch_no` (string): Filter by batch number
- `mapping` (int): Filter by mapping status (0 or 1)
- `search` (string): Search across multiple fields
- `sort_by` (string): Sort field (id, awaiting_fileno, landuse, year, created_at, updated_at)
- `sort_order` (string): Sort order (asc, desc)

**Example Request:**
```
GET /api/grouping?page=1&per_page=50&landuse=COMMERCIAL&search=CON-COM&sort_by=created_at&sort_order=desc
```

**Response Example:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "awaiting_fileno": "CON-COM-1981-1",
            "mls_fileno": null,
            "landuse": "COMMERCIAL",
            "year": 1981,
            "mapping": 0,
            "batch_no": "BATCH001",
            "shelf_rack": "A-01",
            "created_at": "2025-10-25T10:30:00Z",
            "updated_at": "2025-10-25T12:15:00Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 50,
        "total": 450000,
        "last_page": 9000,
        "from": 1,
        "to": 50,
        "has_more": true
    },
    "filters_applied": {
        "landuse": "COMMERCIAL",
        "search": "CON-COM"
    }
}
```

### 3. Get Records by Land Use
**GET** `/api/grouping/land-use/{landuse}`

Returns records filtered by specific land use type.

**Parameters:**
- `landuse` (string): Land use type (COMMERCIAL, RESIDENTIAL, AGRICULTURE, etc.)
- `per_page` (int): Records per page (default: 25, max: 1000)

**Example Request:**
```
GET /api/grouping/land-use/COMMERCIAL?per_page=100
```

### 4. Search Records
**GET** `/api/grouping/search`

Performs full-text search across multiple fields.

**Query Parameters:**
- `query` (string, required): Search query (minimum 2 characters)
- `per_page` (int): Records per page (default: 25, max: 1000)

**Example Request:**
```
GET /api/grouping/search?query=CON-RES-1981&per_page=25
```

**Response Example:**
```json
{
    "success": true,
    "data": [...],
    "pagination": {...},
    "search_query": "CON-RES-1981",
    "results_found": 15000
}
```

### 5. Get Single Record
**GET** `/api/grouping/{id}`

Returns a specific grouping record by ID.

**Parameters:**
- `id` (int): Record ID

**Response Example:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "awaiting_fileno": "CON-COM-1981-1",
        "mls_fileno": null,
        "landuse": "COMMERCIAL",
        "year": 1981,
        "mapping": 0,
        "batch_no": "BATCH001",
        "shelf_rack": "A-01",
        "date": "1981-01-15",
        "date_index": "2025-10-25",
        "created_by": "admin",
        "indexed_by": null,
        "updated_by": "system",
        "created_at": "2025-10-25T10:30:00Z",
        "updated_at": "2025-10-25T12:15:00Z"
    }
}
```

### 6. Create New Record
**POST** `/api/grouping`

Creates a new grouping record.

**Request Body (JSON):**
```json
{
    "awaiting_fileno": "NEW-COM-2025-001", // Required, unique
    "mls_fileno": "MLS-2025-001", // Optional
    "landuse": "COMMERCIAL", // Required
    "year": 2025, // Optional
    "mapping": 0, // Optional (0 or 1)
    "batch_no": "BATCH2025", // Optional
    "shelf_rack": "B-15", // Optional
    "date": "2025-10-25", // Optional
    "date_index": "2025-10-25" // Optional
}
```

**Response Example:**
```json
{
    "success": true,
    "message": "Grouping record created successfully",
    "data": {
        "id": 2025001,
        "awaiting_fileno": "NEW-COM-2025-001",
        ...
    }
}
```

### 7. Update Record
**PUT** `/api/grouping/{id}`

Updates an existing grouping record.

**Parameters:**
- `id` (int): Record ID

**Request Body (JSON):**
```json
{
    "landuse": "MIXED_USE",
    "mapping": 1,
    "shelf_rack": "C-20"
}
```

### 8. Delete Record
**DELETE** `/api/grouping/{id}`

Soft deletes a grouping record.

**Parameters:**
- `id` (int): Record ID

**Response Example:**
```json
{
    "success": true,
    "message": "Grouping record 'CON-COM-1981-1' deleted successfully"
}
```

### 9. Get Land Use Types
**GET** `/api/grouping/land-use-types`

Returns all unique land use types in the system.

**Response Example:**
```json
{
    "success": true,
    "data": [
        "AGRICULTURE",
        "COMMERCIAL", 
        "RESIDENTIAL"
    ]
}
```

### 10. Get Available Years
**GET** `/api/grouping/available-years`

Returns all years that have data in the system.

**Response Example:**
```json
{
    "success": true,
    "data": [
        2025,
        1981,
        1980
    ]
}
```

## Error Responses

### Validation Error (422)
```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "awaiting_fileno": [
            "The awaiting fileno field is required.",
            "The awaiting fileno has already been taken."
        ]
    }
}
```

### Not Found (404)
```json
{
    "success": false,
    "message": "Grouping record not found"
}
```

### Server Error (500)
```json
{
    "success": false,
    "message": "Error retrieving records",
    "error": "Database connection failed"
}
```

## Usage Examples

### Get statistics for dashboard
```javascript
fetch('/api/grouping/totals')
    .then(response => response.json())
    .then(data => {
        console.log('Total records:', data.data.total_records);
        console.log('Land use breakdown:', data.data.land_use_breakdown);
    });
```

### Search with pagination
```javascript
const searchParams = new URLSearchParams({
    query: 'CON-COM',
    per_page: 50,
    page: 1
});

fetch(`/api/grouping/search?${searchParams}`)
    .then(response => response.json())
    .then(data => {
        console.log('Search results:', data.data);
        console.log('Total found:', data.results_found);
    });
```

### Filter by land use and year
```javascript
const filterParams = new URLSearchParams({
    landuse: 'COMMERCIAL',
    year: 1981,
    per_page: 100,
    sort_by: 'awaiting_fileno',
    sort_order: 'asc'
});

fetch(`/api/grouping?${filterParams}`)
    .then(response => response.json())
    .then(data => {
        console.log('Filtered records:', data.data);
        console.log('Pagination:', data.pagination);
    });
```

### Create new record
```javascript
fetch('/api/grouping', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
        awaiting_fileno: 'NEW-RES-2025-001',
        landuse: 'RESIDENTIAL',
        year: 2025,
        mapping: 0
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Record created:', data.data);
    } else {
        console.error('Error:', data.message);
    }
});
```

## Rate Limiting
Consider implementing rate limiting for production use:
- 100 requests per minute for GET endpoints
- 20 requests per minute for POST/PUT/DELETE endpoints

## Caching
Statistical endpoints (`/totals`, `/land-use-types`, `/available-years`) can be cached for better performance.

## Security Considerations
1. Add authentication middleware for production
2. Implement proper input sanitization
3. Add rate limiting
4. Log API access for auditing
5. Validate file permissions for sensitive operations