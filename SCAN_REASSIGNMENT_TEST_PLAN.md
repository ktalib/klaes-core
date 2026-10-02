# Scan Document Reassignment - Test Plan

## Test Scenarios

### ✅ Core Functionality

#### Test 1: Single Scan Reassignment (Indexed → Indexed, Same Registry)
**Setup:**
- Scan #101 exists under file number KANGIS-2024-RES-0041
- Target file number: KANGIS-2024-RES-0042 (both exist in file_indexings, same registry)

**Steps:**
1. Open scan #101 in preview
2. Click "Reassign" button
3. Enter "KANGIS-2024-RES-0042" in target field
4. Verify destination preview shows: `SCAN_UPLOAD / Lands_Registry / KANGIS-2024-RES-0042 / A4`
5. Click "Confirm Reassignment"

**Expected:**
- File physically moves from `SCAN_UPLOAD/Lands_Registry/0041/A4/...` → `SCAN_UPLOAD/Lands_Registry/0042/A4/...`
- Scanning record updated: file_indexing_id, document_path, registry
- Audit log entry created
- Modal closes, view refreshes
- Source directory cleaned if empty

---

#### Test 2: Single Scan Reassignment (Indexed → Indexed, Different Registry)
**Setup:**
- Scan #102 under LANDS-2024-001 (Lands_Registry)
- Target: DCIV-2024-0512 (DCIV_Registry, indexed)

**Steps:**
1. Open scan #102, click "Reassign"
2. Enter "DCIV-2024-0512"
3. Verify preview: `SCAN_UPLOAD / DCIV_Registry / DCIV-2024-0512 / A4`
4. Confirm

**Expected:**
- File moves between registry folders
- Registry field updates in scanning record
- Audit trail shows registry change

---

#### Test 3: Scan Reassignment to Non-Indexed File Number
**Setup:**
- Scan #103 under KANGIS-2024-RES-0001
- Target: KANGIS-2024-NEWFILE-9999 (does NOT exist in file_indexings)

**Steps:**
1. Open scan #103, click "Reassign"
2. Enter "KANGIS-2024-NEWFILE-9999"
3. Verify preview: `BLIND_SCAN / KANGIS-2024-NEWFILE-9999`
4. Notice warning: "File not yet indexed. Scans will be stored in BLIND_SCAN..."
5. Confirm

**Expected:**
- File moves to `BLIND_SCAN/KANGIS-2024-NEWFILE-9999/{paper_size}/`
- file_indexing_id set to NULL in scanning record
- Audit log shows blind_scan destination
- When file later indexed, blind scan → scan upload transfer picks it up

---

#### Test 4: Batch Reassignment (Multiple Scans)
**Setup:**
- Scans #104, #105, #106 all under LANDS-2024-001 (different paper sizes: A4, A5, A3)
- Target: LANDS-2024-002 (same registry, indexed)

**Steps:**
1. Multi-select scans #104, #105, #106 (if supported) OR
2. Open preview for one, manually open reassign modal and select all
3. Enter "LANDS-2024-002"
4. Confirm

**Expected:**
- All 3 files move to target
- Each ends up in correct paper_size subfolder (A4, A5, A3, not mixed)
- Audit log has 3 entries (one per scan)
- Batch response shows: moved_count=3, failed_count=0

---

### ⚠️ Constraint & Error Handling

#### Test 5: Reassignment Blocked - PageTyping In Progress
**Setup:**
- Scan #107 has associated PageTyping records (page_typings.scanning_id = 107)

**Steps:**
1. Open scan #107, click "Reassign"
2. Enter any target file number
3. Verify constraint warning appears

**Expected:**
- Modal shows orange warning: "Cannot reassign — page typing in progress"
- Confirm button is disabled
- No reassignment attempted

---

#### Test 6: Reassignment to Same File Number (Rejected)
**Setup:**
- Scan #108 already under KANGIS-2024-RES-0050

**Steps:**
1. Open scan #108, click "Reassign"
2. Enter "KANGIS-2024-RES-0050" (same)
3. Click confirm

**Expected:**
- Validation error returned: "Target file number cannot be same as current"
- Reassignment blocked
- Error message displayed in modal

---

#### Test 7: Empty Directory Cleanup
**Setup:**
- Scan #109 is the ONLY file in directory `SCAN_UPLOAD/Lands_Registry/OLDFILE/A4/`
- Target: NEWFILE

**Steps:**
1. Reassign scan #109 to NEWFILE

**Expected:**
- Source directory `SCAN_UPLOAD/Lands_Registry/OLDFILE/A4/` is deleted
- Parent `SCAN_UPLOAD/Lands_Registry/OLDFILE/` is deleted if empty
- Root `SCAN_UPLOAD` remains untouched

---

### 🔍 UI & UX

#### Test 8: Modal - Selected Documents Display
**Setup:**
- Multiple scans selected in preview

**Steps:**
1. Click "Reassign"
2. Observe modal opens with list of selected documents

**Expected:**
- Each document shows filename and current file number
- Clear, readable format
- File icon and organize layout

---

#### Test 9: Modal - Live File Number Lookup (Debounce)
**Setup:**
- Modal open

**Steps:**
1. Type "K" in target field → no lookup yet (debouncing)
2. Type "A" → still debouncing
3. Type "NGIS-2024" → still debouncing
4. Wait 500ms after stopping → lookup fires
5. Observe spinner during lookup

**Expected:**
- Input loading spinner appears
- After 500ms idle, AJAX request sent
- Preview rendered with destination info
- No lag, no excessive requests

---

#### Test 10: Modal - Destination Preview (Indexed)
**Setup:**
- Enter indexed file number

**Steps:**
- Observe preview section

**Expected:**
- Shows: Registry, File Number, Paper Size path pattern
- Shows count of existing scans in destination
- Shows "File indexed in system" message
- Blue info box styling

---

#### Test 11: Modal - Destination Preview (Blind Scan)
**Setup:**
- Enter non-indexed file number

**Steps:**
- Observe preview section

**Expected:**
- Shows: `BLIND_SCAN / FileNumber`
- Shows amber warning: "File not yet indexed. Scans will be stored in BLIND_SCAN..."
- Indicates it will be picked up during indexing transfer

---

### 📊 Audit & Logging

#### Test 12: Audit Log Entry Creation
**Setup:**
- Complete a reassignment (Test 1)

**Steps:**
1. Query database: `SELECT * FROM scan_reassignment_logs WHERE scanning_id = 101`

**Expected:**
- Row exists with:
  - scanning_id = 101
  - from_file_number = KANGIS-2024-RES-0041
  - to_file_number = KANGIS-2024-RES-0042
  - from_path = old path
  - to_path = new path
  - reassigned_by = current auth user id
  - created_at = recent timestamp

---

#### Test 13: Audit Log with Reason
**Setup:**
- Reassignment with reason entered

**Steps:**
1. Enter reason: "Pages belong to 0042 not 0041" before confirming
2. Check audit log

**Expected:**
- reason field populated in database
- Laravel log file shows reason in log entry

---

### 🌐 API Level

#### Test 14: /scan-uploads/reassign/check Endpoint
**Request:**
```
POST /scan-uploads/reassign/check
{ "target_file_number": "KANGIS-2024-RES-0042" }
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "file_number": "KANGIS-2024-RES-0042",
    "destination_type": "scan_upload",
    "registry": "Lands Registry",
    "file_indexing_id": 4523,
    "folder_exists": true,
    "existing_scan_count": 12,
    "destination_path": "EDMS/SCAN_UPLOAD/Lands_Registry/KANGIS-2024-RES-0042"
  }
}
```

---

#### Test 15: /scan-uploads/reassign Endpoint - Success
**Request:**
```
POST /scan-uploads/reassign
{
  "scan_ids": [101],
  "target_file_number": "KANGIS-2024-RES-0042",
  "reason": "Correcting misplaced page"
}
```

**Expected Response:**
```json
{
  "success": true,
  "message": "1 document(s) reassigned to KANGIS-2024-RES-0042.",
  "data": {
    "moved_count": 1,
    "failed_count": 0,
    "destination_type": "scan_upload",
    "documents": [ { /* scanning object */ } ],
    "failed_scans": []
  }
}
```

---

#### Test 16: /scan-uploads/reassign Endpoint - Validation Error
**Request:**
```
POST /scan-uploads/reassign
{
  "scan_ids": [],  // Invalid: empty array
  "target_file_number": "",  // Invalid: empty
  "reason": null
}
```

**Expected Response (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "scan_ids": ["The scan_ids field is required."],
    "target_file_number": ["The target_file_number field is required."]
  }
}
```

---

### 🔐 Permission & Security

#### Test 17: Permission Check
**Setup:**
- Logged-in as user with scan upload permissions

**Steps:**
1. Attempt reassignment

**Expected:**
- Succeeds (has permission to manage scan uploads)

---

#### Test 18: Non-Existent Scan
**Request:**
```
POST /scan-uploads/reassign
{ "scan_ids": [9999999], "target_file_number": "KANGIS-2024-RES-0042" }
```

**Expected (400):**
- Validation error: "scanning_id doesn't exist"

---

### 📈 Edge Cases

#### Test 19: Filename Collision
**Setup:**
- File with same name already exists at destination
- Target path: `SCAN_UPLOAD/Lands_Registry/0042/A4/document-slug.pdf`
- Incoming: same slug

**Expected:**
- New file gets: `document-slug-{timestamp}-{random}.pdf`
- No overwrite
- Both files preserved

---

#### Test 20: Paper Size Mixed Batch
**Setup:**
- Reassign scans with paper sizes: A4, A5, A3, Legal to same target

**Steps:**
1. Batch reassign all 4

**Expected:**
- All move to target
- A4 → `target/A4/filename`
- A5 → `target/A5/filename`
- A3 → `target/A3/filename`
- Legal → `target/Legal/filename`
- Each in correct subfolder

---

#### Test 21: Very Long Path/Filename
**Setup:**
- Scan with very long original filename
- Target with long file number

**Steps:**
1. Reassign

**Expected:**
- No path length errors
- File successfully moved
- Path stays within OS limits

---

### 🔄 Integration

#### Test 22: Updated Scan Object Response
**Setup:**
- Reassignment complete

**Steps:**
1. Check response documents in `/scan-uploads/reassign`

**Expected:**
- Scanning objects include:
  - Updated file_indexing_id
  - Updated document_path
  - Updated registry
  - Updated definition/definition_code
  - Relationships loaded (fileIndexing, uploader)

---

#### Test 23: Custom Event Triggering
**Setup:**
- Browser console ready
- Reassignment in progress

**Steps:**
1. Monitor for custom event: `window.addEventListener('scans-reassigned', ...)`
2. Complete reassignment

**Expected:**
- Custom event fired with detail payload
- Data includes: movedCount, failedCount, documents

---

## Test Execution Order

1. **Foundation (1-3)** - Basic indexed and blind reassignments
2. **Constraints (5-7)** - Error handling and validation
3. **UI (8-11)** - Modal and preview
4. **API (14-16)** - Direct endpoint testing
5. **Audit (12-13)** - Logging verification
6. **Permissions (17-18)** - Security
7. **Edge Cases (19-21)** - Robustness
8. **Integration (22-23)** - Full feature flow

---

## Success Criteria

✅ All tests pass
✅ No SQL errors
✅ No filesystem errors
✅ Audit logs accurate
✅ UI responsive
✅ API returns correct data
✅ Files correctly organized
✅ Empty directories cleaned
✅ PageTyping constraint enforced
✅ Batch operations resilient
