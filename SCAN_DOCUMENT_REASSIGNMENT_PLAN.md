# Scan Document Reassignment (Misplaced File Correction)

## Problem Statement

During or after scan upload, a user may discover that one or more scanned pages were uploaded under the **wrong file number**. Currently there is no way to flag and move those pages to the correct file number folder. The user must delete and re-upload manually, losing audit trail and wasting time.

---

## Current System Summary

### Folder Structure (reference)

```
storage/app/public/EDMS/
├── BLIND_SCAN/                         ← staging area (unsorted)
│   ├── {file_number}/
│   │   ├── A3/
│   │   ├── A4/
│   │   ├── A5/
│   │   └── Legal/
│   └── {Registry}_Raw/
│       └── {file_number}/
│
└── SCAN_UPLOAD/                        ← processed, registry-organised
    ├── Lands_Registry/
    │   └── {file_number}/
    │       ├── A3/
    │       ├── A4/
    │       ├── A5/
    │       └── Legal/
    ├── Cadastral_Registry/
    ├── DCIV_Registry/
    ├── Secret_Registry/
    ├── KANGIS_Registry/
    ├── SLTR_Registry/
    ├── ST_Registry/
    └── Deeds_Registry/
```

### Key Entities

| Entity | Table | Role |
|--------|-------|------|
| `FileIndexing` | `file_indexings` | Indexed file record (has `file_number`, `registry`) |
| `Scanning` | `scannings` | Individual scanned page/document (has `file_indexing_id`, `document_path`, `paper_size`, `registry`) |
| `BlindScanning` | `blind_scannings` | Blind scan staging record |
| `PageTyping` | `pagetypings` | Downstream processing — blocks deletion if present |

### Existing Transfer Flow (Blind Scan → Scan Upload)

`BlindScanIngestionService::transfer()` already handles:
1. File discovery under `BLIND_SCAN/{file_number}/`
2. Physical file move to `SCAN_UPLOAD/{registry}/{file_number}/{paper_size}/`
3. `Scanning` record creation in a DB transaction
4. Cleanup of empty source directories

---

## Proposed Feature: "Reassign Scanned Document"

### User Story

> As a scan upload operator, I want to **flag a scanned page as misplaced** and specify the **correct file number** so the system moves it to the right folder automatically — whether that folder is in SCAN_UPLOAD (already indexed) or BLIND_SCAN (not yet indexed).

### Core Logic (Decision Tree)

```
User selects scan(s) → enters correct file_number
                           │
                           ▼
              Does file_number exist in file_indexings?
              ┌─── YES ──────────────────────── NO ───┐
              │                                        │
              ▼                                        ▼
   Get registry from file_indexing         Create folder under BLIND_SCAN/
   Move file to:                           Move file to:
   SCAN_UPLOAD/{registry}/{file_number}/   BLIND_SCAN/{file_number}/
   {paper_size}/{filename}                 {paper_size}/{filename}
              │                                        │
              ▼                                        ▼
   Update scanning record:                 Update scanning record:
   - file_indexing_id → new FI id          - file_indexing_id → NULL
   - document_path → new path              - document_path → new path
   - registry → new registry               - status → 'misplaced_pending'
   - definition/definition_code refresh    - notes → auto-note
              │                                        │
              ▼                                        ▼
   Also check: does SCAN_UPLOAD already    When file_number is later indexed,
   have a folder for the correct           the existing Blind Scan → Scan Upload
   file_number? If not, create it          transfer flow will pick it up naturally.
   with paper size subdirs.
              │                                        │
              └──────────── BOTH ──────────────────────┘
                             │
                             ▼
              Log the reassignment action (audit trail)
              Clean up empty source directories
```

### Also Check: Target File Number Already in SCAN_UPLOAD

Before falling through to BLIND_SCAN, check whether a folder already exists under **any registry** in SCAN_UPLOAD for the target file number (even if `file_indexings` has no record). This handles cases where uploads exist but indexing was deleted/missing.

```
Search order:
1. file_indexings WHERE file_number = ? → get registry → SCAN_UPLOAD/{registry}/{file_number}/
2. Physical folder scan: SCAN_UPLOAD/*/{file_number}/ exists? → use that path
3. Fall through → BLIND_SCAN/{file_number}/
```

---

## Implementation Plan

### Phase 1: Backend Service

#### 1.1 New Service: `ScanReassignmentService`

**File:** `app/Services/ScanUploads/ScanReassignmentService.php`

```
class ScanReassignmentService
{
    reassign(Scanning $scan, string $targetFileNumber): array
    reassignBatch(array $scanIds, string $targetFileNumber): array
    resolveTargetPath(string $fileNumber): array   // returns [type, absolutePath, relativePath, fileIndexingId|null]
    movePhysicalFile(string $source, string $destination): void
    updateScanRecord(Scanning $scan, ...): void
    logReassignment(Scanning $scan, string $fromFileNumber, string $toFileNumber, ...): void
```

**Key methods:**

| Method | Purpose |
|--------|---------|
| `resolveTargetPath($fileNumber)` | Determine destination: SCAN_UPLOAD (indexed) or BLIND_SCAN (not indexed). Respects paper size subdirectories (A3, A4, A5, Legal). |
| `reassign($scan, $targetFileNumber)` | Single-document move. Wraps file move + DB update in a transaction. |
| `reassignBatch($scanIds, $targetFileNumber)` | Multi-select: move several misplaced pages at once. |
| `logReassignment(...)` | Write to `scan_reassignment_log` table and Laravel log channel. |

#### 1.2 New Migration: `scan_reassignment_logs`

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Auto increment |
| `scanning_id` | int FK → scannings | Which scan was moved |
| `from_file_number` | varchar(100) | Original (wrong) file number |
| `to_file_number` | varchar(100) | Correct file number |
| `from_file_indexing_id` | int nullable | Old FI link |
| `to_file_indexing_id` | int nullable | New FI link (NULL if sent to blind scan) |
| `from_path` | varchar(500) | Old document_path |
| `to_path` | varchar(500) | New document_path |
| `reason` | varchar(500) nullable | User-supplied reason |
| `reassigned_by` | int FK → users | Who performed the action |
| `created_at` | datetime | When |

#### 1.3 New Model: `ScanReassignmentLog`

**File:** `app/Models/ScanReassignmentLog.php`

Standard Eloquent model with `$connection = 'sqlsrv'`.

### Phase 2: Controller Endpoints

**File:** `app/Http/Controllers/ScanUploadsController.php` (extend existing)

#### New Endpoints

| Verb | URI | Method | Purpose |
|------|-----|--------|---------|
| `POST` | `/scan-uploads/reassign` | `reassign()` | Move one or more scans to correct file number |
| `POST` | `/scan-uploads/reassign/check` | `reassignCheck()` | Validate target file number, return destination info for UI preview |

#### `reassignCheck` Request/Response

**Request:**
```json
{
    "target_file_number": "KANGIS-2024-RES-0042"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "file_number": "KANGIS-2024-RES-0042",
        "destination_type": "scan_upload",       // or "blind_scan"
        "registry": "Lands Registry",            // null if blind_scan
        "file_indexing_id": 4523,                // null if blind_scan
        "folder_exists": true,
        "existing_scan_count": 12,
        "destination_path": "EDMS/SCAN_UPLOAD/Lands_Registry/KANGIS-2024-RES-0042/"
    }
}
```

#### `reassign` Request/Response

**Request:**
```json
{
    "scan_ids": [101, 102, 105],
    "target_file_number": "KANGIS-2024-RES-0042",
    "reason": "Pages belong to file KANGIS-2024-RES-0042, not 0041"
}
```

**Response:**
```json
{
    "success": true,
    "message": "3 document(s) reassigned to KANGIS-2024-RES-0042.",
    "data": {
        "destination_type": "scan_upload",
        "moved_count": 3,
        "documents": [ /* updated scan payloads */ ]
    }
}
```

#### Routes (add to `routes/app3.php`)

```php
// Scan reassignment (misplaced file correction)
Route::post('/scan-uploads/reassign/check', [ScanUploadsController::class, 'reassignCheck']);
Route::post('/scan-uploads/reassign', [ScanUploadsController::class, 'reassign']);
```

Place these **above** the existing `{scan}` wildcard routes to avoid capture.

### Phase 3: Frontend (Blade + JS)

#### 3.1 UI Entry Point — "Reassign" Button

Add a **"Reassign"** action to each scan document card/row in the scan uploads view (next to existing Edit / Delete actions).

**Trigger:** `<button data-action="reassign" data-scan-id="{{$scan->id}}">`

#### 3.2 Reassign Modal

**File:** `resources/views/scan_uploads/partials/reassign_modal.blade.php`

Modal contents:
1. **Selected documents** — thumbnail + filename list of selected scans
2. **Target file number** input — with live lookup (debounced AJAX to `/reassign/check`)
3. **Destination preview** — shows:
   - "Will move to: `SCAN_UPLOAD / Lands_Registry / KANGIS-2024-RES-0042 / A4/`" (if indexed)
   - "Will move to: `BLIND_SCAN / KANGIS-2024-RES-0042 / A4/`" (if not indexed, with info message)
4. **Reason** textarea (optional)
5. **Confirm** button — calls `/scan-uploads/reassign`
6. **PageTyping guard** — if any selected scan has page typing records, show warning and block reassignment (same constraint as delete)

#### 3.3 JavaScript Module

**File:** `public/js/scan-reassignment.js`

```
class ScanReassignmentManager {
    constructor(modalEl)
    openModal(scanIds)                // populate modal, fetch current data
    checkTargetFileNumber(fileNumber) // debounced AJAX to /reassign/check
    renderDestinationPreview(data)    // show where files will go
    confirmReassignment()             // POST to /reassign, handle response
    refreshParentView()               // reload scan list after successful move
}
```

### Phase 4: Constraints & Edge Cases

| Scenario | Handling |
|----------|----------|
| **Scan has PageTyping records** | Block reassignment. Show message: "Cannot reassign — page typing in progress." |
| **Target file number = current file number** | Reject with validation error. |
| **Target folder doesn't exist** | Create it with paper size subdirectories (A3, A4, A5, Legal). |
| **Filename collision at destination** | Append timestamp + random suffix (same pattern as `buildTargetFilename()`). |
| **Target file indexed under different registry** | Use the registry from `file_indexings` record. Move to correct registry subfolder. |
| **File number not indexed AND no BLIND_SCAN folder** | Create `BLIND_SCAN/{file_number}/{paper_size}/` and move there. |
| **Source directory becomes empty after move** | Clean up empty dirs (reuse `cleanupIfEmpty()` pattern). |
| **Multiple scans selected with different paper sizes** | Each file goes to its own paper_size subfolder at destination. |
| **Permission check** | Gate behind `can('manage-scan-uploads')` or `super admin` check. |

### Phase 5: Audit & Logging

1. **Database log** — `scan_reassignment_logs` table (full before/after snapshot).
2. **Laravel log** — `Log::channel('daily')` with context (mirrors `BlindScanIngestionService::logMove()`).
3. **Activity log** — If `ActivityLogService` is integrated, log as user action type `scan_reassigned`.

---

## File Changes Summary

| Action | File | Description |
|--------|------|-------------|
| **Create** | `app/Services/ScanUploads/ScanReassignmentService.php` | Core reassignment logic |
| **Create** | `app/Models/ScanReassignmentLog.php` | Eloquent model for audit logs |
| **Create** | `database/migrations/xxxx_create_scan_reassignment_logs_table.php` | Migration |
| **Create** | `resources/views/scan_uploads/partials/reassign_modal.blade.php` | Reassign modal partial |
| **Create** | `public/js/scan-reassignment.js` | Frontend JS module |
| **Edit** | `app/Http/Controllers/ScanUploadsController.php` | Add `reassign()` and `reassignCheck()` methods |
| **Edit** | `routes/app3.php` | Add two new routes |
| **Edit** | `resources/views/scan_uploads/index.blade.php` | Add reassign button + include modal partial |

---

## Sequence Diagram

```
User                    Frontend JS               Controller               Service                  Filesystem
 │                         │                          │                       │                         │
 ├─ Select scan(s) ───────►│                          │                       │                         │
 ├─ Click "Reassign" ─────►│                          │                       │                         │
 │                         ├─ Open modal              │                       │                         │
 │                         │                          │                       │                         │
 ├─ Enter target file # ──►│                          │                       │                         │
 │                         ├─ POST /reassign/check ──►│                       │                         │
 │                         │                          ├─ resolveTargetPath() ►│                         │
 │                         │                          │                       ├─ Check file_indexings ─►│
 │                         │                          │                       ├─ Check SCAN_UPLOAD dirs►│
 │                         │                          │                       ├─ Check BLIND_SCAN dirs ►│
 │                         │◄─ destination preview ───┤◄──────────────────────┤                         │
 │◄─ Show preview ─────────┤                          │                       │                         │
 │                         │                          │                       │                         │
 ├─ Click "Confirm" ──────►│                          │                       │                         │
 │                         ├─ POST /reassign ─────────►│                       │                         │
 │                         │                          ├─ reassign() ──────────►│                         │
 │                         │                          │                       ├─ Check PageTyping ──────│
 │                         │                          │                       ├─ Move physical file ───►│
 │                         │                          │                       ├─ Update scanning rec ───│
 │                         │                          │                       ├─ Write audit log ───────│
 │                         │                          │                       ├─ Cleanup empty dirs ───►│
 │                         │◄─ success response ──────┤◄──────────────────────┤                         │
 │◄─ Refresh scan list ───┤                          │                       │                         │
```

---

## Testing Checklist

- [ ] Reassign scan from File A → File B (both indexed, same registry) — file moves within `SCAN_UPLOAD/{registry}/`
- [ ] Reassign scan from File A → File B (both indexed, different registries) — file moves across registry folders
- [ ] Reassign scan to un-indexed file number — file goes to `BLIND_SCAN/{file_number}/{paper_size}/`
- [ ] Reassign scan to file number that exists only as a physical folder (no DB record) — file goes to existing folder
- [ ] Reassign multiple scans at once (batch) — all move correctly, each to proper paper_size subfolder
- [ ] Attempt reassign on scan with PageTyping → blocked with error message
- [ ] Attempt reassign to same file number → validation error
- [ ] Verify audit log entry created with correct from/to data
- [ ] Verify source directory cleaned up if empty after move
- [ ] Verify paper size (A3, A4, A5, Legal) preserved during move
- [ ] Verify `definition` and `definition_code` refreshed for moved scans at destination
- [ ] Verify files under `BLIND_SCAN/` are picked up later by normal blind scan transfer when file is indexed
