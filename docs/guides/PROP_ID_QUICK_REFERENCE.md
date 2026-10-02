# prop_id Quick Reference Guide

**Purpose**: One-page troubleshooting and implementation guide

---

## 🔴 Current Broken Paths

```
Form Entry (User creates property record)
├─ PropertyRecordController::store()
│  └─ ❌ NO prop_id allocated
│     └─ Result: NULL prop_id in database
│        └─ Cascades to: PropertyCardController::store()
│
Caveat Creation
├─ CaveatController::store()
│  └─ ❌ NO prop_id lookup
│     └─ Result: Orphaned caveat (no link to property history)
│
CofO Creation
├─ FileIndexingController::updateCofORecord()
│  └─ ⚠️ Minimal prop_id context
│     └─ Result: CofO not linkable by prop_id
│
Legacy Paths
├─ Direct SQL inserts (FileIndexController, DeedsController)
│  └─ ❌ Bypass prop_id entirely
│     └─ Result: Legacy fileNumber ledger still keyed by file number (expected)
```

---

## ✅ Currently Working Paths

```
File Indexing → Property Record (storeFromIndexing)
├─ FileIndexingController receives form
├─ Calls PropertyRecordController::storeFromIndexing()
│  └─ ✅ Allocates prop_id via determinePropIdForFile()
│  └─ ✅ Injects into property_records table
│  └─ ✅ Syncs to file_history_staging
│     └─ Result: All tables have matching prop_id
```

---

## 🔧 What You Need to Fix

### 1. Create PropertyIdAllocationService

**File**: `app/Services/PropertyIdAllocationService.php`

**Key Methods**:
- `allocateOrRetrievePropId(fileNumber, mlsFNo, kangisFileNo, newKangisFileNo)` → int
  - Returns existing prop_id if found
  - Generates new prop_id if not
  - Atomic (no race conditions)

**Copy from**: `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` → Layer 1

---

### 2. Update PropertyRecordController::store()

**File**: `app/Http/Controllers/PropertyRecordController.php`

**Changes**:
```php
// ADD AT TOP OF store() METHOD:
$propIdService = app(\App\Services\PropertyIdAllocationService::class);
$propId = $propIdService->allocateOrRetrievePropId(
    $request->input('fileno'),
    $request->input('mlsFNo'),
    $request->input('kangisFileNo'),
    $request->input('NewKANGISFileno')
);

// IN $data ARRAY:
$data = [
    // ... existing fields ...
    'prop_id' => $propId,  // ← ADD THIS LINE
];

// AFTER INSERT:
$this->syncPropIdToFileHistory($fileNumber, $propId);
```

**Copy from**: `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` → Layer 2 → PropertyRecordController::store()

---

### 3. Update CaveatController::store()

**File**: `app/Http/Controllers/CaveatController.php`

**Changes**: Same pattern as PropertyRecordController
```php
$propIdService = app(\App\Services\PropertyIdAllocationService::class);
$propId = $propIdService->allocateOrRetrievePropId($fileNumber);

$caveatData = [
    // ... existing fields ...
    'prop_id' => $propId,  // ← ADD THIS LINE
];
```

**Copy from**: `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` → Layer 2 → CaveatController::store()

---

### 4. Update FileIndexingController::updateCofORecord()

**File**: `app/Http/Controllers/FileIndexingController.php`

**Changes**:
```php
$propIdService = app(\App\Services\PropertyIdAllocationService::class);
$propId = $propIdService->allocateOrRetrievePropId($fileNumber);

$recordPayload = [
    // ... existing CofO fields ...
    'prop_id' => $propId,  // ← ADD THIS LINE
];
```

**Copy from**: `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` → Layer 2 → CofoController / updateCofORecord()

---

### 5. Run Database Migrations

**Need to add prop_id column to**:
- `caveats` table
- `scannings`, `pagetypings` (optional)

**Files to create**:
```
database/migrations/2025_12_06_add_prop_id_to_caveats.php
database/migrations/2025_12_06_add_prop_id_to_scanning_tables.php
```

**Copy from**: `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` → Layer 3 → Database Migrations

**Run**:
```bash
php artisan migrate --database=sqlsrv
```

---

### 6. Backfill Historical NULL Records

**File**: Create `database_scripts/backfill_prop_id_for_null_records.sql`

**Copy from**: `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` → Backfill Script

**Run** (AFTER all code changes are deployed):
```sql
-- Execute in SQL Server Management Studio or via php artisan
sqlsrv_query($connection, [contents of backfill script]);
```

---

## 🧪 Validation Checklist

After implementing, run these queries:

```sql
-- 1. Check no NULL prop_id
SELECT 'OK' WHERE (
    SELECT COUNT(*) FROM property_records WHERE prop_id IS NULL
) = 0;

-- 2. Check no duplicates
SELECT 'OK' WHERE (
    SELECT COUNT(*) FROM (
        SELECT prop_id FROM property_records 
        GROUP BY prop_id HAVING COUNT(*) > 1
    ) t
) = 0;

-- 3. Check file_history_staging has prop_id
SELECT COUNT(*) as prop_id_count FROM file_history_staging 
WHERE prop_id IS NOT NULL;

-- 4. Cross-check linkage
SELECT COUNT(DISTINCT fh.prop_id) as total_props
FROM file_history_staging fh
LEFT JOIN property_records pr ON fh.prop_id = pr.prop_id
WHERE fh.prop_id IS NOT NULL;
```

---

## 📋 Implementation Checklist

- [ ] Read `PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` (detailed guide)
- [ ] Create `PropertyIdAllocationService.php` (copy code from audit doc)
- [ ] Register service in `AppServiceProvider.php`
- [ ] Update `PropertyRecordController::store()`
- [ ] Update `CaveatController::store()`
- [ ] Update `FileIndexingController::updateCofORecord()`
- [ ] Create & run database migrations
- [ ] Create & run backfill SQL script
- [ ] Run validation queries
- [ ] Test: Create property record, verify prop_id in file_history_staging
- [ ] Test: Create caveat, verify prop_id in caveats table
- [ ] Test: Create CofO, verify prop_id in Cofo table
- [ ] Deploy to production
- [ ] Monitor: Check for any new NULL prop_id after cutover date

---

## 🚨 Pitfalls to Avoid

| ❌ DON'T | ✅ DO |
|---------|------|
| Call `generateNewPropId()` directly | Use `allocateOrRetrievePropId()` |
| Allocate prop_id after insert | Allocate BEFORE insert |
| Use hardcoded MAX+1 logic | Let the service handle it (atomic) |
| Skip syncing to file_history_staging | Call `syncPropIdToFileHistory()` after insert |
| Insert NULL prop_id intentionally | Always provide a valid integer |
| Forget backfill script | Run it exactly 1 week after code deployment |

---

## 📞 Key Files

| What | Where |
|------|-------|
| Main guide | `/PROP_ID_ALLOCATION_AUDIT_AND_SOLUTION.md` |
| Summary | `/PROP_ID_ANALYSIS_SUMMARY.md` |
| This guide | `/PROP_ID_QUICK_REFERENCE.md` |
| Updated docs | `/docs/property-data-study.md` |
| Service code | `app/Services/PropertyIdAllocationService.php` (to create) |

---

## 🎯 Success = When This Works

1. Create property record via form → prop_id populated ✅
2. Create caveat → caveat has matching prop_id ✅
3. Create CofO → CofO has prop_id ✅
4. Query file_history_staging by prop_id → returns all related transactions ✅
5. Search legal records → finds property by prop_id ✅
6. No NULL prop_id in database ✅
7. No duplicate prop_id ✅

---

**Last Updated**: December 6, 2025  
**For**: KLAES GIS EDMS Dev Team  
**Status**: Ready to Implement
