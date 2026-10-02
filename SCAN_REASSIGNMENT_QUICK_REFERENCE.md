# Scan Document Reassignment - Quick Reference

## 🎯 Feature Overview
Allow scan upload operators to flag misplaced pages and move them to the correct file number automatically—whether indexed (SCAN_UPLOAD) or not (BLIND_SCAN).

---

## 📁 File Structure

```
app/
├── Services/ScanUploads/
│   └── ScanReassignmentService.php      ← Core logic
├── Models/
│   └── ScanReassignmentLog.php          ← Audit model
└── Http/Controllers/
    └── ScanUploadsController.php         ← API endpoints (+2 methods)

database/
└── migrations/
    └── 2026_04_07_143500_create_scan_reassignment_logs_table.php

resources/views/scan_uploads/
├── index.blade.php                      ← Modified (+modal include, +script)
├── assets/
│   └── scripts.blade.php                ← Modified (+handlers)
└── partials/
    └── reassign_modal.blade.php         ← New UI modal

public/js/
└── scan-reassignment.js                 ← New JS manager

routes/
└── app3.php                             ← Modified (+2 routes)
```

---

## 🔌 API Endpoints

### Check Target (Preview)
```
POST /scan-uploads/reassign/check
→ Returns destination info for target file number
```

### Execute Reassignment
```
POST /scan-uploads/reassign
→ Moves scans to target file number, returns updated docs
```

---

## 🎛️ UI Components

**Modal:** `#reassign-modal`
- Selected documents display
- Target file number input (auto-lookup)
- Destination preview
- Reason textarea (optional)
- Confirm button

**Button:** `#preview-reassign-btn`
- Located in preview toolbar (between Edit and Delete)
- Icon: git-branch

---

## 🔧 Key Classes & Methods

### ScanReassignmentService
```php
resolveTargetPath(string $fileNumber): array
reassign(Scanning $scan, string $targetFileNumber, ?string $reason): array
reassignBatch(array $scanIds, string $targetFileNumber, ?string $reason): array
```

### ScanReassignmentManager (JS)
```js
openModal(scanIds)
checkTargetFileNumber()
confirmReassignment()
```

### Controller Methods
```php
reassignCheck(Request $request)     // Preview endpoint
reassign(Request $request)          // Execute endpoint
```

---

## 📊 Data Flow

```
User clicks "Reassign"
    ↓
Modal opens with selected scan(s)
    ↓
User enters target file number
    ↓
JS calls reassignCheck() → preview destination
    ↓
User confirms
    ↓
JS calls reassign() → backend moves files
    ↓
Audit log created
    ↓
View refreshes
```

---

## ✅ Constraints
- ✋ Cannot reassign if PageTyping in progress
- ✋ Cannot reassign to same file number
- ✋ Empty directories cleaned up
- ✋ Paper sizes preserved (A3/A4/A5/Legal)

---

## 🚀 Deployment Checklist
- [ ] Run migration: `php artisan migrate`
- [ ] Clear routes cache: `php artisan route:cache`
- [ ] Publish assets if needed
- [ ] Test endpoints with sample data
- [ ] Verify file movements on disk
- [ ] Check audit logs in database

---

## 🔍 Debugging

**Check audit logs:**
```sql
SELECT * FROM scan_reassignment_logs 
WHERE created_at > DATEADD(HOUR, -1, GETDATE());
```

**View file paths:**
```sql
SELECT scanning_id, from_path, to_path 
FROM scan_reassignment_logs 
WHERE scanning_id = ?;
```

**Monitor events:**
```js
window.addEventListener('scans-reassigned', (e) => {
    console.log('Reassignment complete:', e.detail);
});
```

---

## 💡 Tips
1. **Live Lookup** - Modal debounces file number input (500ms)
2. **Batch Safe** - Failures don't block other scans
3. **Indexed Detection** - Auto-detects indexed vs. blind paths
4. **Definition Refresh** - Updates definition/definition_code fields
5. **Cleanup** - Removes empty source directories after move

---

**Last Updated:** April 7, 2026
