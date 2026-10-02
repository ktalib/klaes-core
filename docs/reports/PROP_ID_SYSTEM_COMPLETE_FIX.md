# PROP_ID Allocation - Complete System Fix

**Date**: December 6, 2025  
**Status**: 1 ISSUE FIXED + 2 CRITICAL GAPS IDENTIFIED  
**Scope**: Comprehensive prop_id allocation across entire KLAES system

---

## Executive Summary

Three major issues with `prop_id` allocation across the system:

1. ✅ **FileIndexing** - FIXED (Dec 6, 2025)
   - Problem: Saved prop_id to wrong table (file_indexings)
   - Fix: Removed prop_id from FileIndexing model whitelist
   - Impact: prop_id now correctly goes to file_history_staging only

2. ⚠️ **PropertyRecordController** - CRITICAL GAP
   - Problem: Form-based entry doesn't allocate prop_id at all
   - Fix: Need to add determinePropIdForFile() call
   - Impact: All property records via main form have NULL prop_id

3. ⚠️ **CaveatController** - CRITICAL GAP
   - Problem: Caveat creation missing prop_id allocation
   - Fix: Need to add prop_id lookup before caveat insertion
   - Impact: Caveats can't be linked to file history

---

## Issue 1: FileIndexing ✅ FIXED

### Problem
FileIndexing workflow was saving `prop_id` to **both**:
- ❌ `file_indexings` table (deleted column - wrong)
- ✅ `file_history_staging` table (correct)

### Root Cause
`FileIndexing` model had `'prop_id'` in `columnWhitelist()` array (line 112).

When `FileIndexingController.update()` created updatePayload:
```php
$updatePayload = Arr::only($validated, FileIndexing::columnWhitelist());
```
The `prop_id` was included and saved to file_indexings table.

### Why This Was Wrong

**file_indexings table purpose:**
- Tracks indexing workflow (status: indexed, scanned, etc.)
- Workflow metadata (created_by, updated_by, tracking_id)
- NOT a property data table

**prop_id purpose:**
- Groups property-related data across modules
- Used in `pra`, `pic`, `file_history_staging`, `CofO_staging`
- Should ONLY be in property/transaction tables, NOT workflow tables

**The Fix Ensures:**
- prop_id stays with property data (file_history_staging)
- prop_id is removed from workflow metadata (file_indexings)
- Clear separation of concerns: workflow vs. property data

### Solution Applied
**File**: `app/Models/FileIndexing.php`  
**Line**: 112  
**Change**: Removed `'prop_id'` from columnWhitelist array

**Code Change**:
```php
// BEFORE
public static function columnWhitelist(): array
{
    return [
        // ... other fields
        'prop_id',           // ← REMOVED THIS
        'test_control',
    ];
}

// AFTER
public static function columnWhitelist(): array
{
    return [
        // ... other fields
        'test_control',      // prop_id removed
    ];
}
```

### How It Works Now
```
FileIndexing Form
    ↓
FileIndexingController.update()
    ├─ Extract prop_id from request
    ├─ Create updatePayload (prop_id NOT in whitelist)
    ├─ Update file_indexings table (WITHOUT prop_id) ✅
    └─ Call updateFileHistoryPropId()
            ↓
            Update file_history_staging with prop_id ✅
```

### Verification
- ✅ file_indexings table no longer has prop_id column (deleted)
- ✅ file_history_staging correctly receives prop_id
- ✅ File history transactions display correctly
- ✅ No changes needed to UI forms

---

## Issue 2: PropertyRecordController - Dual Path Problem ⚠️ CRITICAL

### Problem
Two different code paths for creating property records:

**PATH 1: File Indexing Workflow** (WORKS ✅)
```
FileIndexing → PropertyRecordController::storeFromIndexing()
    → calls determinePropIdForFile()
    → allocates prop_id ✅
```

**PATH 2: Main Form Entry** (BROKEN ❌)
```
Property Form → PropertyRecordController::store()
    → does NOT call determinePropIdForFile()
    → prop_id = NULL ❌
```

### Impact
- All property records created via main form have NULL prop_id
- PropertyCardController delegates to store() - inherits NULL prop_id
- Breaks cross-module linking (caveats, CofO, file history)

### Root Cause
**File**: `app/Http/Controllers/PropertyRecordController.php`  
**Method**: `store()` (Lines 38-420)

The method is missing the `determinePropIdForFile()` call that `storeFromIndexing()` (Lines 1403-1438) correctly uses.

### Solution Required
Add prop_id allocation before inserting property record:

```php
// In PropertyRecordController::store() method, before inserting:

// Calculate prop_id
$propId = $this->determinePropIdForFile(
    $fileNumber,
    $mlsFNo ?? null,
    $kangisFileNo ?? null,
    $newKangisFileNo ?? null
);

// Then include in insert data:
$data['prop_id'] = $propId;
```

### Where to Make Change
**File**: `app/Http/Controllers/PropertyRecordController.php`  
**Method**: `store()`  
**Lines**: Around line 100-150 (after validating file numbers, before first insert)

### Testing After Fix
```sql
-- Verify property records have prop_id
SELECT id, file_number, prop_id 
FROM pra 
WHERE DATEDIFF(DAY, created_at, GETDATE()) <= 1
-- Expected: All recent records have non-NULL prop_id

-- Verify file_history_staging shows records with prop_id
SELECT id, fileno, prop_id 
FROM file_history_staging 
WHERE DATEDIFF(DAY, created_at, GETDATE()) <= 1
-- Expected: All recent records have non-NULL prop_id
```

---

## Issue 3: CaveatController - Missing prop_id ⚠️ CRITICAL

### Problem
When placing caveats on properties, the system doesn't allocate prop_id to the related property records.

### Impact
- Caveat records can't be linked back to file history
- Caveat searches don't find related transactions
- Property history incomplete for caveat-affected properties

### Root Cause
**File**: `app/Http/Controllers/CaveatController.php`  
**Method**: `store()` and related caveat methods

The controller never looks up or allocates prop_id before creating caveat records.

### Solution Required

1. **Lookup/Allocate prop_id** before caveat creation:
```php
// Before inserting caveat:
$propId = $this->determinePropIdForFile(
    $fileNumber,
    null,
    null,
    null
);
```

2. **Store prop_id in caveat record** (if caveat table supports it):
```php
$caveat = Caveat::create([
    'file_number' => $fileNumber,
    'prop_id' => $propId,  // Add this
    // ... other fields
]);
```

3. **Update file_history_staging** with caveat + prop_id:
```php
DB::table('file_history_staging')
    ->where('fileno', $fileNumber)
    ->update([
        'prop_id' => $propId,
        'transaction_type' => 'caveat',
        'updated_at' => now(),
    ]);
```

### Where to Make Changes
**File**: `app/Http/Controllers/CaveatController.php`  
**Methods**: 
- `store()` - Main caveat creation
- Any other caveat placement methods

### Testing After Fix
```sql
-- Verify caveats have prop_id
SELECT id, file_number, prop_id 
FROM caveats 
WHERE DATEDIFF(DAY, created_at, GETDATE()) <= 1
-- Expected: All recent records have non-NULL prop_id

-- Verify file_history_staging shows caveat with prop_id
SELECT id, fileno, transaction_type, prop_id 
FROM file_history_staging 
WHERE transaction_type = 'caveat' 
AND DATEDIFF(DAY, created_at, GETDATE()) <= 1
-- Expected: All caveat transactions have non-NULL prop_id
```

---

## Database Tables Reference

| Table | Has prop_id? | Purpose | Status | Notes |
|-------|:---:|---------|--------|-------|
| `file_indexings` | ❌ NO | Indexing workflow metadata | ✅ Correct | Tracks "was this file indexed?" - Not property data |
| `file_history_staging` | ✅ YES | Central transaction hub | ✅ Correct | Tracks all file transactions - Core for prop_id grouping |
| `pra` | ✅ YES | Legacy property records | ⚠️ Needs Form Fix | Has mlsFNo + prop_id for linking |
| `pic` | ✅ YES | Property index card | ⚠️ Needs Form Fix | Has mlsFNo + prop_id for linking |
| `CofO_staging` | ✅ YES | CofO records | ✅ Correct | Has mlsFNo + prop_id for certificates |
| `caveats` | ❌ NO? | Caveat records | ⚠️ Check Schema | Verify if needs prop_id column |
| `property_records` | ? | Property data variant | ⚠️ Verify | Check if exists and schema |

### Why file_indexings Does NOT Need prop_id

`file_indexings` is purely a **workflow tracking table**, not a **property data table**:
- Records: "Was this file indexed?" "What's the indexing status?" "Which batch processed it?"
- Columns: file_number, file_title, workflow_status, tracking_id, created_by
- Purpose: Track file indexing **process**, not property **data**

**Linking**: `file_indexings.file_number` → `pra/pic/file_history_staging.mlsFNo` (where prop_id actually lives)

The prop_id belongs with **property data** (pra, pic, file_history_staging), **not workflow metadata** (file_indexings).

---

## Complete Action Plan

### ✅ COMPLETED (Dec 6, 2025)

- [x] Identify FileIndexing prop_id misdirection
- [x] Remove 'prop_id' from FileIndexing model columnWhitelist
- [x] Verify updateFileHistoryPropId() works correctly
- [x] Confirm UI forms need no changes

### 🔴 NEXT PRIORITY - PropertyRecordController

- [ ] Add determinePropIdForFile() call to store() method
- [ ] Test form-based property creation with prop_id
- [ ] Verify file_history_staging receives prop_id
- [ ] Update PropertyCardController if needed
- [ ] Create unit tests for property record creation

### 🟠 SECOND PRIORITY - CaveatController

- [ ] Add prop_id lookup before caveat creation
- [ ] Check if caveats table needs prop_id column
- [ ] Update file_history_staging with caveat + prop_id
- [ ] Test caveat placement with prop_id context
- [ ] Verify caveat searches include file history

### 🟡 VERIFICATION - Other Controllers

- [ ] CofoController - Verify CofO records include prop_id
- [ ] EdmsController - Verify scanning records link to prop_id
- [ ] DeedsController - Verify deed records have prop_id
- [ ] Batch operations - Verify bulk updates preserve prop_id

---

## Data Flow Diagram

```
                    PROPERTY ENTRY POINTS
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
    File Index        Property Form      Caveat
    (Indexing)        (Main Form)        Placement
        │                  │                  │
        │              ❌ BROKEN          ❌ BROKEN
        ├──────────────────┼──────────────────┤
        │                  │                  │
        └─ determinePropIdForFile() ─┘        │
                       │                      │
                       ▼                      ▼
              prop_id allocation         (needs fix)
                       │
    ┌──────────────────┼──────────────────┐
    │                  │                  │
    ▼                  ▼                  ▼
pra/pic tables    CofO_staging      file_history_staging
(property)        (certificates)    (transactions)
    │                  │                  │
    └──────────────────┴──────────────────┘
                       │
                       ▼
              Cross-module linking
         (Search, History, Tracking)
```

---

## Key Methods Reference

### determinePropIdForFile() - The Core Function
**File**: `app/Http/Controllers/PropertyRecordController.php`  
**Lines**: 1403-1438

This method searches for existing prop_id or calculates new one:
```php
protected function determinePropIdForFile($fileNumber, $mlsFNo, $kangisFileNo, $newKangisFileNo): ?int
{
    // 1. Search file_history_staging for matching file number
    // 2. Search pra table for matching file number
    // 3. If found, return existing prop_id
    // 4. If not found, calculate MAX(prop_id) + 1 from both tables
    // 5. Return prop_id (minimum 1)
}
```

**Already Used In**:
- ✅ PropertyRecordController::storeFromIndexing() - Line 1403+
- ❌ PropertyRecordController::store() - NOT USED (needs fix)
- ❌ CaveatController::store() - NOT USED (needs fix)

### updateFileHistoryPropId() - File History Update
**File**: `app/Http/Controllers/FileIndexingController.php`  
**Lines**: 2130-2160

Updates file_history_staging with prop_id:
```php
protected function updateFileHistoryPropId(string $fileNumber, ?int $propId): void
{
    DB::connection('sqlsrv')
        ->table('file_history_staging')
        ->where(function ($query) use ($fileNumber) {
            $query->where('mlsFNo', $fileNumber)
                ->orWhere('fileno', $fileNumber);
        })
        ->update(['prop_id' => $propId]);
}
```

**Already Used In**:
- ✅ FileIndexingController::updateRelatedTables() - Line 702

---

## Summary Table

| Issue | Problem | Solution | Status | Priority |
|-------|---------|----------|--------|----------|
| FileIndexing | Wrong table | Remove from whitelist | ✅ FIXED | — |
| PropertyRecord Form | No prop_id allocation | Add determinePropIdForFile() call | ⏳ TODO | 🔴 CRITICAL |
| Caveat Placement | No prop_id allocation | Add determinePropIdForFile() call | ⏳ TODO | 🟠 HIGH |
| Other Controllers | Validation needed | Verify/Fix as needed | ⏳ TODO | 🟡 MEDIUM |

---

## What Happens When You Fix These Issues

### Current State (Broken)
```
File Index Form → prop_id: 1    → file_history_staging ✅
                               → file_indexings ❌ (deleted)

Property Form → prop_id: NULL  → pra/pic ❌
             → NO file_history_staging entry ❌

Caveat Form → prop_id: NULL   → caveats ❌
            → NO file_history_staging entry ❌
```

### After All Fixes (Complete)
```
File Index Form → prop_id: 1    → file_history_staging ✅

Property Form → prop_id: 2     → pra/pic ✅
             → file_history_staging ✅

Caveat Form → prop_id: 3       → caveats ✅
            → file_history_staging ✅

Result: All property data linked via prop_id across all modules ✅
```

---

## Next Steps

1. **Immediately** (already done): FileIndexing fix is complete
2. **Next**: Fix PropertyRecordController.store() to allocate prop_id
3. **Then**: Fix CaveatController to allocate prop_id
4. **Finally**: Verify other controllers and test end-to-end

Each fix is the same pattern: **Call determinePropIdForFile() before inserting**.
