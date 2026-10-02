# Scan Uploads Implementation Plan

## Overview
Translating the PHP reference (`C:\xampp\htdocs\test\scan-upload.php`) to Laravel for `ScanUploadsController` and integrating with existing `ScanningController` patterns and the `scannings` table schema.

## Current State
- ✅ Routes defined (`/scan-uploads` group with log/upload/destroy/debug endpoints)
- ✅ Controller scaffold with basic methods
- ✅ Model `Scanning` has required columns: `file_size`, `is_pdf_converted`, `parent_scan_id`
- ✅ Asset templates and scripts for frontend

## Core Translation Tasks

### 1. **File Upload Handler** (`POST /scan-uploads/upload`)
**Reference**: Lines 72–244 (scan-upload.php)

**Translation Map:**
```
PHP Reference              →  Laravel Implementation
────────────────────────────────────────────────────────
$_FILES['file']           →  $request->file('file')
move_uploaded_file()      →  Storage::disk('public')->storeAs()
uniqid()                  →  Str::random(6) + timestamp
preg_replace sanitize     →  Str::slug() or sanitization helper
mkdir()                   →  Storage handles directory creation
realpath() security check →  Path validation in Laravel
────────────────────────────────────────────────────────
```

**Key Features to Implement:**
- ✅ Accept POST with single `file` + metadata (file_number/file_indexing_id, paper_size, document_type, notes, etc.)
- ✅ Validate file size (50MB max)
- ✅ Validate file types (jpg, jpeg, png, gif, bmp, tiff, webp, pdf)
- ✅ Resolve file indexing (find by file_indexing_id or file_number)
- ✅ Generate unique filename (timestamp + random)
- ✅ Store in `EDMS/SCAN_UPLOAD/{file_number}/` directory
- ✅ Create Scanning record with metadata
- ✅ Return normalized JSON response

**Scanning Record Fields:**
```php
[
  'file_indexing_id',      // FK to file_indexings
  'document_path',         // Relative path in storage
  'uploaded_by',           // Auth::id()
  'status',                // 'pending'
  'original_filename',     // Client filename
  'paper_size',            // From request
  'document_type',         // From request or detected
  'notes',                 // From request
  'file_size',             // $file->getSize()
  'is_pdf_converted',      // From request (PDF conversion flag)
  'parent_scan_id',        // For multi-page PDFs or related scans
]
```

### 2. **File Deletion Handler** (`DELETE /scan-uploads/{scan}`)
**Reference**: Lines 247–390 (scan-upload.php)

**Translation Map:**
```
PHP Reference                    →  Laravel Implementation
──────────────────────────────────────────────────────────
$_POST['action'] === 'delete'   →  Route model binding + DELETE
file path validation             →  FileIndexing relationship check
realpath() security              →  Built-in permission checks
unlink()                         →  Storage::disk('public')->delete()
rmdir() on empty dirs            →  Cleanup with directory check
──────────────────────────────────────────────────────────
```

**Key Features to Implement:**
- ✅ Route model binding: `DELETE /scan-uploads/{scan}` auto-resolves Scanning record
- ✅ Check if scan has associated PageTyping records (prevent deletion)
- ✅ Delete physical file from storage
- ✅ Delete database record
- ✅ Optionally remove empty directory
- ✅ Return success/error JSON

### 3. **Log Endpoint** (`GET /scan-uploads/log`)
**Reference**: Lines 641–650 (scan-upload.php)

**Translation Map:**
```
PHP Reference                      →  Laravel Implementation
───────────────────────────────────────────────────────────
getUploadData() scandir loop       →  Scanning::with('fileIndexing')
Group by file number               →  Collection->groupBy()
Build response array               →  Map to normalized payload
────────────────────────────────────────────────────────────
```

**Key Features to Implement:**
- ✅ Query Scanning records with FileIndexing relation
- ✅ Group by file_number
- ✅ Return paginated/limited response (250 default)
- ✅ Include filter by file_number if provided
- ✅ Return normalized JSON with grouped structure

### 4. **Debug Endpoint** (`GET /scan-uploads/debug`)
**Reference**: Lines 75–114 (scan-upload.php)

**Translation Map:**
```
PHP Reference                  →  Laravel Implementation
─────────────────────────────────────────────────────────
scandir() tree walk           →  Storage filesystem traversal
realpath() validation         →  Laravel storage helper paths
Directory structure inspect   →  Collect directory tree
──────────────────────────────────────────────────────────
```

**Key Features to Implement:**
- ✅ Check EDMS/SCAN_UPLOAD path exists and is writable
- ✅ List directory structure
- ✅ Return filesystem diagnostics

### 5. **Dashboard Stats** (Supporting Method)

**Stats to Calculate:**
- Uploads today: `Scanning::whereDate('created_at', today())->count()`
- Pending page typing: `Scanning::whereDoesntHave('fileIndexing.pagetypings')->count()`
- Total scanned: `Scanning::count()`

### 6. **Document Payload Formatting**

**Normalized Response Shape:**
```php
[
  'id'                 => $scanning->id,
  'fileIndexingId'     => $scanning->file_indexing_id,
  'fileNumber'         => $fileIndexing->file_number,
  'fileTitle'          => $fileIndexing->file_title,
  'originalName'       => $scanning->original_filename,
  'storedName'         => basename($scanning->document_path),
  'paperSize'          => $scanning->paper_size,
  'documentType'       => $scanning->document_type,
  'status'             => $scanning->status,
  'notes'              => $scanning->notes,
  'fileSize'           => $scanning->file_size,
  'isPdfConverted'     => (bool) $scanning->is_pdf_converted,
  'parentScanId'       => $scanning->parent_scan_id,
  'uploadedAt'         => $scanning->created_at->toIso8601String(),
  'uploadedBy'         => $scanning->uploader->name,
  'downloadUrl'        => asset('storage/' . $scanning->document_path),
  'displayOrder'       => $scanning->display_order,
]
```

## Validation Rules

```php
$validator = Validator::make($request->all(), [
  'file_indexing_id'  => 'nullable|integer|exists:sqlsrv.file_indexings,id',
  'file_number'       => 'nullable|string|max:255',
  'file'              => 'required|file|max:51200',                    // 50MB
  'paper_size'        => 'nullable|string|in:A4,A5,A3,Letter,Legal',
  'document_type'     => 'nullable|string|max:100',
  'notes'             => 'nullable|string|max:1000',
  'display_order'     => 'nullable|integer|min:0',
  'parent_scan_id'    => 'nullable|integer|exists:sqlsrv.scannings,id',
  'is_pdf_converted'  => 'sometimes|boolean',
  'original_filename' => 'nullable|string|max:255',
]);
```

**File Type Validation:**
```php
'file' => 'required|file|mimes:jpg,jpeg,png,gif,bmp,tiff,webp,pdf|max:51200'
```

## Error Handling

### Upload Errors
| Error | HTTP Code | Message |
|-------|-----------|---------|
| No file | 422 | Validation failed |
| File too large | 422 | File too large (50MB max) |
| Invalid type | 422 | File type not allowed |
| FileIndexing not found | 404 | Unable to find indexed file |
| Storage write failed | 500 | Unable to upload document |

### Deletion Errors
| Error | HTTP Code | Message |
|-------|-----------|---------|
| Already page typed | 409 | Cannot delete document that has been page typed |
| File not found | 404 | Document not found |
| Permission denied | 403 | Access denied |
| Delete failed | 500 | Unable to delete document |

## Frontend Integration

### API Endpoints
```javascript
POST   /scan-uploads/upload           // Single file upload
DELETE /scan-uploads/{id}             // Delete document
GET    /scan-uploads/log              // Fetch upload log
GET    /scan-uploads/debug            // Debug storage
```

### Response Shapes

**Upload Success:**
```json
{
  "success": true,
  "message": "Document uploaded successfully.",
  "data": { /* normalized document payload */ }
}
```

**Log Success:**
```json
{
  "success": true,
  "data": [
    {
      "fileNumber": "FILE-123",
      "fileTitle": "Property A",
      "date": "11/09/2025",
      "documents": [ /* normalized payloads */ ]
    }
  ]
}
```

## Testing Checklist

- [ ] **Upload Tests**
  - [ ] Single file upload with all metadata
  - [ ] Upload without metadata (use defaults)
  - [ ] File size validation (reject 51MB+)
  - [ ] Invalid file type rejection
  - [ ] Directory creation on first upload
  - [ ] FileIndexing resolution by ID and by file_number
  - [ ] Database record creation with all fields
  - [ ] File storage in correct directory structure

- [ ] **Deletion Tests**
  - [ ] Delete document successfully
  - [ ] Prevent deletion if page typed
  - [ ] Handle missing document gracefully
  - [ ] Cleanup empty directories
  - [ ] Verify database record removed

- [ ] **Log Endpoint Tests**
  - [ ] Fetch all scanned documents
  - [ ] Group by file_number correctly
  - [ ] Filter by file_number if provided
  - [ ] Limit response size
  - [ ] Include all normalized fields

- [ ] **Debug Endpoint Tests**
  - [ ] Report directory structure
  - [ ] Show writable status
  - [ ] Detect missing directories

- [ ] **Error Handling**
  - [ ] Validation errors return 422
  - [ ] Not found errors return 404
  - [ ] Server errors return 500
  - [ ] All responses include `success` and `message`

## Security Considerations

✅ **Path Traversal Prevention**
- Use Laravel's `Storage` facade instead of raw file operations
- Validate all file paths within authorized directory
- Use route model binding for Scanning records

✅ **File Type Validation**
- Whitelist allowed MIME types
- Validate extension and content

✅ **File Size Limits**
- Enforce 50MB maximum
- Use Laravel's file upload limits

✅ **Access Control**
- Require authentication (middleware)
- Verify user owns the FileIndexing record (optional)

✅ **Database Security**
- Use parameterized queries (Eloquent)
- Validate FK references before inserting
- Use transactions for multi-step operations

## Rollback Plan

If issues occur:
1. Keep old `ScanningController::upload()` as fallback
2. Feature flag: `config('scanning.use_new_uploads', false)`
3. Revert routes to old endpoint if needed
4. Data already in scannings table is safe (additive only)

## Success Criteria

1. ✅ All PHP logic translated to Laravel idioms
2. ✅ Single-file uploads work end-to-end
3. ✅ Log endpoint reflects database records
4. ✅ Deletion prevents data loss (PageTyping check)
5. ✅ Debug endpoint aids troubleshooting
6. ✅ Frontend script calls new endpoints successfully
7. ✅ All error cases handled gracefully
8. ✅ Unit tests cover core scenarios
