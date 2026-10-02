# Property Data Allocation Audit: `prop_id` Missing Endpoints & Controllers

**Date**: December 6, 2025  
**Scope**: Comprehensive search for endpoints/controllers creating/updating property-related tables without prop_id allocation  
**Key Tables Analyzed**: `property_records`, `file_history_staging`, `CofO_staging` / `CofO`, `file_indexings`, `cofo_*` variants

---

## Executive Summary

Based on a comprehensive codebase search, **`prop_id` allocation is correctly implemented in primary data flows**, but **critical gaps exist in form-based and caveat workflows** where prop_id is never allocated.

### Key Schema Facts
✅ **Both `pra` and `pic` tables HAVE `prop_id` column**
- `pra` = Legacy property records table (has prop_id)
- `pic` = Property index card table (has prop_id)
- `file_history_staging` = Main hub table (has prop_id)
- The code correctly searches both `pra` and `file_history_staging` for existing prop_id (Lines 1400-1437)

### Critical Findings

**GAPS IDENTIFIED** (prop_id NOT allocated):
1. **PropertyRecordController::store()** - Form-based entry path (main form) does NOT allocate prop_id ⚠️ CRITICAL
2. **PropertyCardController::store()** - Delegates to PropertyRecordController, inherits the NULL prop_id issue
3. **CaveatController::store()** - Creates caveat records WITHOUT allocating prop_id to related property tables
4. **CofoController** - Inserts CofO records without prop_id propagation  
5. **EdmsController** - Scanning and PageTyping creation may bypass prop_id
6. **Batch operations** - Any bulk updates (registration indexing, bulk movement updates)
7. **Direct SQL inserts** - FileIndexController, DeedsController bypass prop_id context

**CORRECTLY IMPLEMENTED** (prop_id allocated):
1. ✅ **PropertyRecordController::storeFromIndexing()** - Allocates prop_id via `determinePropIdForFile()` (Lines 1403-1438)
   - Searches `file_history_staging` table first
   - Falls back to `pra` table if not found
   - Calculates MAX(prop_id) from both tables and increments
2. ✅ **FileIndexingController::update()** - Receives prop_id from form, correctly persists to file_history_staging (NOT file_indexings)
   - Form hidden input submits prop_id
   - Backend extracts and normalizes prop_id
   - Calls updateFileHistoryPropId() to update file_history_staging
   - Removed prop_id from FileIndexing model columnWhitelist (Dec 6, 2025)
   - Deleted prop_id column from file_indexings table (user action, Dec 6, 2025)
   - **See**: FILEINDEXING_PROP_ID_FIX_COMPLETE.md
3. ✅ **FileIndexingController.store()** - Does NOT receive prop_id (correct - only edit form sends it)
4. ✅ **FileIndexController::update()** - Updates file_indexings with prop_id correctly
5. ✅ **prop_id lookup logic** - Correctly checks both `pra` and `file_history_staging` tables

---

## Detailed Audit Results

### 1. PropertyRecordController

**File**: `app/Http/Controllers/PropertyRecordController.php`

#### ✅ CORRECT: `store()` method (Lines 38-420)
- **Status**: DOES NOT ALLOCATE prop_id for form-based entry ❌ CRITICAL GAP
- **Flow**:
  - User creates property record via main form
  - Controller validates all fields including title_type
  - Routes to either `CofO_staging` (CofO records) or `pra`/`pic` tables (property records)
  - **CofO branch**: Inserts CofO-specific data but **NO prop_id allocation**
  - **Property record branch**: Inserts base data + optional transaction entries
  - **CRITICAL GAP**: No prop_id calculation or allocation in this method
- **Risk**: New property records created via the main form have NULL prop_id, while `storeFromIndexing()` correctly allocates prop_id
- **Root Cause**: This method (form entry) does NOT call `determinePropIdForFile()`, unlike `storeFromIndexing()` which does (Lines 1403-1438)
- **Impact**: 
  - Property records created via form entry have NULL prop_id
  - Records stay orphaned from file history ecosystem
  - Cascades to PropertyCardController::store() which delegates to this method
- **Code Comparison**:
  ```php
  // storeFromIndexing() DOES THIS (Line 1403+):
  $propId = $this->determinePropIdForFile($fileNumber, $mlsFNo, $kangisFileNo, $newKangisFileNo);
  
  // store() DOES NOT DO THIS - MISSING! ❌
  ```

#### ✅ CORRECT: `storeFromIndexing()` method (Lines 1159-1340)
- **Status**: ALLOCATES prop_id correctly  
- **Flow**:
  1. Validates file number + transaction array
  2. Parses file number format (MLKN, KN, RES-, ST-, etc.)
  3. **Calls `determinePropIdForFile()`** (Lines 1403-1438) to calculate prop_id
  4. For each transaction, inserts/updates property record with `prop_id` set
  5. Updates `fileNumber` table (no prop_id needed here)
  6. Calls `syncFileHistoryRecord()` which includes prop_id in payload
- **Implementation Details**:
  - `determinePropIdForFile()` searches existing property records for matching file numbers
  - If found, reuses existing prop_id
  - If not found, increments max(prop_id) from both `file_history_staging` and `pra` tables
  - Returns calculated prop_id (minimum 1)
- **Validation**: ✅ All transaction rows receive prop_id before insertion

---

## ⚠️ CRITICAL INSIGHT: The Dual-Path Problem

### Two Different Code Paths for Property Records

**PATH 1: File Indexing Workflow (WORKS ✅)**
```
File Indexing Form
  ↓
FileIndexingController receives form + prop_id
  ↓
PropertyRecordController::storeFromIndexing() called with prop_id
  ↓
Calls determinePropIdForFile() to verify/allocate prop_id
  ↓
Inserts property_records with prop_id ✅
  ↓
Syncs to file_history_staging with prop_id ✅
  ↓
Result: Complete prop_id linkage across tables
```

**PATH 2: Direct Form Entry (BROKEN ❌)**
```
Property Card Form (Main UI entry point)
  ↓
PropertyCardController::store() called
  ↓
Delegates to PropertyRecordController::store()
  ↓
Does NOT call determinePropIdForFile()
  ↓
Inserts into pra/pic/CofO_staging with NULL prop_id ❌
  ↓
Does NOT sync to file_history_staging
  ↓
Result: Orphaned record, no linkage to file history
```

### Why This Matters

**The code ALREADY HAS the solution** in `determinePropIdForFile()` (Lines 1403-1438), which:
1. Searches `file_history_staging` for existing prop_id
2. Falls back to `pra` table if not found in file_history_staging
3. Calculates MAX(prop_id) from both tables
4. Returns new prop_id if needed

**The problem**: This logic is ONLY called in `storeFromIndexing()`, NOT in the main `store()` method used by form entries.

**The solution**: Reuse `determinePropIdForFile()` in the `store()` method before inserting records.

---
- **Status**: DOES NOT ALLOCATE or UPDATE prop_id
- **Issue**: Updates to property records preserve existing values but don't set prop_id if NULL
- **Risk**: Existing records with NULL prop_id remain orphaned when updated
- **Recommendation**: Add logic to allocate prop_id if current record has NULL

---

### 2. PropertyCardController

**File**: `app/Http/Controllers/PropertyCardController.php`

#### ✅ DELEGATES: `store()` method (Line 665)
- **Status**: Delegates to PropertyRecordController::store()
- **Behavior**: Transforms AI form data into PropertyRecordController format and calls store()
- **Issue**: Delegates to PropertyRecordController::store() which **does NOT allocate prop_id**
- **Risk**: Records created via PropertyCardController have NULL prop_id
- **Code**: 
  ```php
  $propertyRecordController = new \App\Http\Controllers\PropertyRecordController();
  return $propertyRecordController->store($transformedRequest);
  ```

---

### 3. CaveatController

**File**: `app/Http/Controllers/CaveatController.php`

#### ❌ MISSING: `store()` method (Lines 32-133)
- **Status**: Creates caveat records WITHOUT prop_id allocation
- **Flow**:
  1. Validates caveat fields (file_number, petitioner, encumbrance_type, etc.)
  2. Checks if record exists in property_records/registered_instruments/CofO tables
  3. **Calls `setCaveatedFlags()`** (Lines 337-404) to update related tables with caveat_id
  4. **Saves caveat record** with caveat_id/is_caveated flags on related tables
  5. **MISSING**: Never allocates or looks up prop_id
- **Issue**: When caveat is placed:
  - property_records table gets `is_caveated=1, caveat_id=<caveat_id>` ✅
  - **But still has NULL prop_id if it was missing** ❌
- **Code**:
  ```php
  private function setCaveatedFlags(string $fileNo, bool $isCaveated, string $comment, ?int $caveatId = null): void
  {
      // Updates property_records with caveat flags
      $query->update([
          'is_caveated' => $isCaveated ? 1 : 0,
          'caveat_id' => $caveatId,
          'caveated_comment' => $comment,
          // MISSING: 'prop_id' => $propId,
      ]);
  }
  ```
- **Recommendation**:
  - Before updating property_records, calculate prop_id using same logic as PropertyRecordController::determinePropIdForFile()
  - Include `'prop_id' => $propId` in the update payload

---

### 4. CofoController

**File**: `app/Http/Controllers/CofoController.php`

#### ❌ MISSING: CofO creation endpoints (Line 364)
- **Status**: Inserts CofO records WITHOUT prop_id allocation
- **Code**:
  ```php
  $id = DB::connection('sqlsrv')->table('st_cofo')->insertGetId([
      // Many CofO fields...
      // MISSING: 'prop_id' => $propId,
  ]);
  ```
- **Issue**: CofO records inserted without prop_id, orphaning them from file history
- **Impact**: CofO records cannot be linked to property cards or file timelines

---

### 5. FileIndexController

**File**: `app/Http/Controllers/FileIndexController.php`

#### ✅ CORRECT: Updates to file_indexings with prop_id (Line 824)
- **Status**: Receives and updates prop_id correctly
- **Code**: When updating file_indexings, includes prop_id in payload
- **However**: Creates new CofO records without prop_id (Line 1066 in updateCofORecord)

#### ⚠️ PARTIAL: `updateCofORecord()` method (Lines 1050-1070)
- **Status**: Updates existing CofO records but inserts new ones WITHOUT prop_id
- **Code**:
  ```php
  if ($existing) {
      $table->where('id', $existing->id)->update($recordPayload);
  } else {
      // Inserts new CofO record
      $table->insert(array_merge($matchColumns, $recordPayload));
      // MISSING: 'prop_id' in payload
  }
  ```

---

### 6. EdmsController

**File**: `app/Http/Controllers/EdmsController.php`

#### ⚠️ UNCERTAIN: Scanning and PageTyping creation
- **Status**: Creates scanning and page typing records, prop_id handling unclear
- **Code** (Lines 185, 260, 305):
  ```php
  $fileIndexing = FileIndexing::on('sqlsrv')->create([
      'main_application_id' => $applicationId,
      // ... many fields
      // Unclear if prop_id included
  ]);
  ```
- **Recommendation**: Verify if FileIndexing model includes prop_id in fillable/guarded arrays

---

### 7. File History Staging Operations

#### ✅ CORRECT: `file_history_staging` table updates
- **Status**: FileIndexingController correctly updates file_history_staging with prop_id ✅
- **Implementation**: 
  - FileIndexingController::update() (Line 703) calls `updateFileHistoryPropId()` 
  - updateFileHistoryPropId() (Lines 2130-2160) correctly updates file_history_staging table (NOT file_indexings)
  - Matches records by mlsFNo or fileno and updates prop_id column
- **Database Fix** (Dec 6, 2025):
  - Removed prop_id column from file_indexings table (user deleted it)
  - Removed 'prop_id' from FileIndexing model columnWhitelist (line 112)
  - Now prop_id is passed to file_history_staging ONLY (not file_indexings)
- **See**: FILEINDEXING_PROP_ID_FIX_COMPLETE.md for full implementation details
- **Gap**: Direct SQL inserts to file_history_staging (PropertyRecordController Line 1066) may not include prop_id if not explicitly added

---

### 8. Batch Operations & APIs

#### ❌ MISSING: Bulk imports and batch operations
- **Areas**:
  - **GroupingAnalyticsController**: Updates grouping table without prop_id context
  - **FileIndexingController::bulkMovementUpdate()** (Line 2979): Updates file locations but doesn't validate prop_id
  - **FileTrackerController**: Inserts file tracking records without prop_id propagation
  - **Scanning bulk operations**: No prop_id context preserved

#### ⚠️ PARTIAL: File History API (FileHistoryApiController)
- **Code** (Lines 58-60):
  ```php
  if ($request->filled('prop_id')) {
      $propId = trim((string) $request->input('prop_id'));
      $query->whereRaw('LOWER(prop_id) = ?', [strtolower($propId)]);
  }
  ```
- **Issue**: Accepts and filters by prop_id but doesn't force allocation on new records
- **Recommendation**: When creating file history records via API, validate or allocate prop_id

---

## Summary Table: prop_id Allocation Status

| Controller/Method | Table(s) | prop_id Status | Risk Level | Recommendation |
|---|---|---|---|---|
| PropertyRecordController::store() | pra, pic | ❌ NOT ALLOCATED | CRITICAL | Allocate prop_id using determinePropIdForFile() |
| PropertyRecordController::storeFromIndexing() | property_records | ✅ ALLOCATED | SAFE | No action needed |
| PropertyRecordController::update() | pra, pic | ⚠️ PARTIAL | HIGH | Add prop_id allocation if NULL |
| PropertyCardController::store() | pra, pic (delegated) | ❌ NOT ALLOCATED | CRITICAL | Fix PropertyRecordController::store() |
| CaveatController::store() | property_records, caveats | ❌ NOT ALLOCATED | HIGH | Calculate prop_id before setting caveated flags |
| CofoController (all) | st_cofo, CofO_staging | ❌ NOT ALLOCATED | HIGH | Include prop_id in all CofO inserts |
| FileIndexController::update() | file_indexings | ✅ ALLOCATED | SAFE | Monitor CofO insert branch |
| FileIndexController::updateCofORecord() | CofO_staging | ⚠️ PARTIAL | MEDIUM | Add prop_id to insert branch |
| EdmsController | file_indexings | ⚠️ UNCERTAIN | MEDIUM | Verify FileIndexing model fillable |
| FileTrackerController | file_trackings | ❌ NO PROP_ID | LOW | Not applicable (different table) |
| BatchOperations | various | ❌ NO CONTEXT | MEDIUM | Add prop_id context preservation |
| FileHistoryApiController | file_history_staging | ⚠️ PARTIAL | MEDIUM | Validate prop_id on create endpoints |

---

## Implementation Checklist

### IMMEDIATE FIXES (Critical)
- [ ] **PropertyRecordController::store()**: Add prop_id allocation for property_records path
  - Call `determinePropIdForFile()` after parsing file number
  - Include `'prop_id' => $propId` in primaryRecordData and entryData
  
- [ ] **PropertyRecordController::store() CofO branch**: Add prop_id allocation
  - Before inserting into CofO_staging, calculate prop_id
  - Include prop_id in filteredCofOData

- [ ] **CaveatController::store()**: Add prop_id allocation
  - Create helper method `allocatePropIdForFileNumber(fileNumber)`
  - Call before setCaveatedFlags()
  - Include in update payload

### HIGH PRIORITY
- [ ] **CofoController**: Add prop_id allocation to all CofO inserts
  - Implement helper similar to PropertyRecordController::determinePropIdForFile()
  - Include prop_id in all insert/insertGetId() calls

- [ ] **FileIndexController::updateCofORecord()**: Add prop_id to insert path
  - Calculate prop_id from existing FileIndexing context
  - Include in array_merge() for new CofO records

- [ ] **PropertyRecordController::update()**: Add prop_id back-fill
  - If record has NULL prop_id, allocate it
  - Update record with new prop_id value

### MEDIUM PRIORITY
- [ ] **EdmsController**: Verify FileIndexing model includes prop_id
  - Check `protected $fillable` array
  - Add prop_id if missing

- [ ] **FileTrackerController**: Add prop_id to file tracking context
  - Accept prop_id from file_indexings
  - Store reference for future auditing

- [ ] **Batch operations**: Add prop_id context preservation
  - Review bulkMovementUpdate() and similar methods
  - Ensure prop_id is preserved during batch operations

### VALIDATION & TESTING
- [ ] Create unit tests for prop_id allocation in PropertyRecordController
- [ ] Test CaveatController prop_id allocation flow
- [ ] Test CofoController with and without existing file numbers
- [ ] Verify file_history_staging records always have prop_id set
- [ ] Query check: SELECT COUNT(*) FROM property_records WHERE prop_id IS NULL (should be 0 after fixes)

---

## Code Examples for Fixes

### Fix 1: PropertyRecordController::store() - Add prop_id allocation

```php
// After file number parsing (line ~190)
$propId = $this->determinePropIdForFile(
    $singleFileno ?: $mls ?: $kangis ?: $newKangis,
    $mls,
    $kangis,
    $newKangis
);

// In primaryRecordData array, add:
$primaryRecordData['prop_id'] = $propId;

// In entryData array, add:
$entryData['prop_id'] = $propId;
```

### Fix 2: CaveatController - Add prop_id allocation before setCaveatedFlags()

```php
// After resolveFileNumber() (line ~85)
$propId = $this->allocatePropIdForFileNumber(
    $validated['file_number'],
    $validated['file_number_id'] ?? null
);

// In setCaveatedFlags() call, pass prop_id:
$this->setCaveatedFlags(
    $validated['file_number'],
    true,
    $comment,
    $caveat->id,
    $propId  // New parameter
);

// Update setCaveatedFlags() signature:
private function setCaveatedFlags(
    string $fileNo,
    bool $isCaveated,
    string $comment,
    ?int $caveatId = null,
    ?int $propId = null  // New parameter
): void {
    // Add to update payload:
    if ($propId !== null) {
        $updateData['prop_id'] = $propId;
    }
}
```

### Fix 3: CofoController - Add prop_id to insert

```php
// Add helper method:
private function determinePropIdForCofo(string $fileNumber): int
{
    // Same logic as PropertyRecordController::determinePropIdForFile()
    // Search CofO_staging for existing prop_id
    $existing = DB::connection('sqlsrv')
        ->table('CofO_staging')
        ->where('cofo_no', $fileNumber)
        ->orWhere('np_fileno', $fileNumber)
        ->first();
    
    if ($existing && $existing->prop_id) {
        return (int) $existing->prop_id;
    }
    
    $maxPropId = (int) DB::connection('sqlsrv')
        ->table('CofO_staging')
        ->max('prop_id');
    
    return $maxPropId > 0 ? $maxPropId + 1 : 1;
}

// In store() or create endpoint:
$propId = $this->determinePropIdForCofo($validated['cofo_no'] ?? '');

// In insert call:
$id = DB::connection('sqlsrv')->table('st_cofo')->insertGetId([
    // ... existing fields
    'prop_id' => $propId,
]);
```

---

## Related Documentation

- `docs/property-data-study.md` - Overview of prop_id usage and file history flows
- `Readmes/PROPERTY_RECORD_FIX_SUMMARY.md` - Property record structure requirements
- `COFO_NUMBER_IMPLEMENTATION_COMPLETE.md` - CofO number system design
- `FILE_INDEXING_FORM_GAPS.md` - Known form-to-controller mapping gaps

---

## Questions & Discussion

**Q: Why is prop_id needed in CofO_staging if we have cofo_no?**  
A: `prop_id` links CofO records to the broader property timeline. Without it, you can't easily join CofO data with file history or property cards pivoting on prop_id.

**Q: Should batch operations include prop_id context?**  
A: Yes, especially for file movement/tracking. Batch updates should preserve prop_id from source records and propagate to destination records.

**Q: Is there a race condition in determinePropIdForFile()?**  
A: Potentially. Multiple concurrent requests could generate duplicate prop_ids. Consider using a database sequence or advisory lock for high-concurrency scenarios.

---

**Document Version**: 1.0  
**Status**: READY FOR IMPLEMENTATION  
**Last Updated**: December 6, 2025
