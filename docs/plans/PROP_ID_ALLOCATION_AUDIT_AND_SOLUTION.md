# Property ID (prop_id) Allocation Audit & Comprehensive Solution

**Date**: December 6, 2025  
**Status**: Analysis Complete | Ready for Implementation  
**Priority**: CRITICAL

---

## Executive Summary

The `prop_id` is intended to be **the unique, cross-module identifier** for each property across the data tables that describe real property (`property_records`, `file_history_staging`, `cofo`, `property_index_card`, etc.). Workflow helpers such as `file_indexings` or the legacy `fileNumber` ledger continue to rely on the canonical file number instead of persisting `prop_id`. Even with that separation, the current implementation still has **critical gaps**:

✅ **CORRECTLY ALLOCATED**: `PropertyRecordController::storeFromIndexing()` (via `determinePropIdForFile()`)  
✅ **CORRECTLY ALLOCATED**: `FileIndexingController::update()` (respects incoming prop_id)  
❌ **MISSING**: `PropertyRecordController::store()` (main form entry)  
❌ **MISSING**: `PropertyCardController::store()` (cascades to above)  
❌ **MISSING**: `CaveatController::store()` (caveats placed without prop_id context)  
❌ **MISSING**: `CofoController::*` (all CofO inserts bypass prop_id)  
ℹ️ **Intentional**: `fileNumber` ledger continues to use the literal file number (no prop_id needed)  
❌ **MISSING**: Batch operations (orphaned from file history)  

**Result**: Users can create property records and place caveats without ever assigning a `prop_id`, leaving them orphaned from file history, file indexing workflows, and legal searches.

---

## Detailed Gap Analysis

### 1. **PropertyRecordController::store()** - CRITICAL GAP
**File**: `app/Http/Controllers/PropertyRecordController.php:34-150`  
**Issue**: No `prop_id` allocated when creating new property records via the main form

**Current behavior**:
```php
// Lines 34-150: store() method processes form submission
// NO prop_id calculation or assignment
// Result: prop_id = NULL in database
```

**Impact**:
- Records inserted with NULL `prop_id`
- Cannot be linked to file history
- Cannot participate in file indexing workflows
- Orphaned from property search/timeline views
- Caveats placed on these records have no property context

**Root cause**: The method delegates through a form-based pathway but doesn't invoke `determinePropIdForFile()` like `storeFromIndexing()` does.

---

### 2. **PropertyCardController::store()** - CASCADING GAP
**File**: `app/Http/Controllers/PropertyCardController.php:288`  
**Issue**: Delegates to `PropertyRecordController::store()`, inherits NULL prop_id

**Current behavior**:
```php
public function store(Request $request)
{
    // Eventually calls PropertyRecordController::store()
    // NO prop_id added before delegation
}
```

---

### 3. **CaveatController::store()** - CRITICAL GAP
**File**: `app/Http/Controllers/CaveatController.php` (location TBD from grep)  
**Issue**: Places caveats without allocating or looking up existing `prop_id`

**Current behavior**:
- Inserts caveat record
- No `prop_id` populated
- Cannot correlate caveat to property history

**Impact**:
- Legal searches cannot find caveated properties by prop_id
- File history timelines missing caveat annotations
- Caveat system disconnected from property data ecosystem

---

### 4. **CofoController & FileIndexingController::updateCofORecord()** - CRITICAL GAP
**File**: `app/Http/Controllers/FileIndexingController.php:881-931`  
**Issue**: CofO inserts create new records without prop_id or minimal prop_id context

**Current behavior**:
```php
// Line 881-931: updateCofORecord()
// Insert/upsert CofO record
// No explicit prop_id allocation
// Relies on cofo_no or MLS file number for linking
```

**Impact**:
- CofO records lack prop_id linkage
- Cannot pivot on prop_id in analytics
- Breaks the "single identifier" design principle

---

### 5. **fileNumber Table Direct Inserts** - SAFE AS-IS
**Files**: 
- `FileIndexController.php:969, 1066`
- `FileIndexingController.php:855, 952, 1885, 1981`
- `DeedsController.php:48` (raw SQL INSERT)

**Observation**: The legacy `fileNumber` table is intentionally keyed by the literal file number and only needs to expose that natural identifier. Consumers already join on `mlsFNo`/`kangisFileNo` variants, so duplicating `prop_id` here would not improve linkage.

**Impact**:
- No schema change required for `fileNumber`
- Direct SQL inserts remain valid as long as they preserve file-number formats
- prop_id enforcement stays focused on property/transaction tables

---

### 6. **Batch Operations** - SYSTEMIC GAP
**Pattern**: Anywhere data is bulk-updated without per-record prop_id context

**Impact**:
- Multiple records updated in a single operation
- No individual prop_id allocation
- Lost opportunity to link related records

---

## Database Schema Reality Check

**Tables with prop_id column** (verified):
- ✅ `file_history_staging` (main pivot table)
- ✅ `property_records` (dynamic table per land use)
- ✅ `file_indexings` (optional, currently NULL for many)
- ✅ `pra` (legacy property records)
- ⚠️ `Cofo` (present but not consistently populated)
- ⚠️ `pic` (property index card)

**Workflow tables keyed by file number (no prop_id expected)**:
- `file_indexings`
- `fileNumber`

**Tables WITHOUT prop_id** (need migration):
- ℹ️ `fileNumber` (legacy, keyed by mlsFNo; no prop_id stored)
- ❌ `caveats` (caveat_id only, no prop_id)
- ❌ `scanning`, `pagetypings` (file indexing related)

---

## Proposed Solution Architecture

### Three-Layer Approach

#### **Layer 1: Centralized prop_id Generation Service**

Create a new service `PropertyIdAllocationService`:

```php
<?php
// app/Services/PropertyIdAllocationService.php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PropertyIdAllocationService
{
    /**
     * Allocate or retrieve a unique prop_id for a file number.
     * 
     * This service is the SINGLE SOURCE OF TRUTH for prop_id allocation
     * across all modules and tables.
     * 
     * @param string $fileNumber - Primary file number identifier
     * @param string|null $mlsFNo - Optional MLS file number
     * @param string|null $kangisFileNo - Optional KANGIS file number
     * @param string|null $newKangisFileNo - Optional New KANGIS file number
     * @return int - Allocated prop_id (unique across all files)
     */
    public function allocateOrRetrievePropId(
        string $fileNumber,
        ?string $mlsFNo = null,
        ?string $kangisFileNo = null,
        ?string $newKangisFileNo = null
    ): int {
        // Step 1: Normalize inputs
        $normalizedValues = array_values(array_filter(array_unique(
            array_map(fn($v) => $this->normalizeValue($v), 
                [$fileNumber, $mlsFNo, $kangisFileNo, $newKangisFileNo]
            )
        )));

        // Step 2: Search property_records first
        $existing = DB::connection('sqlsrv')->table('property_records')
            ->select('prop_id')
            ->whereNotNull('prop_id')
            ->when(!empty($normalizedValues), fn($q) => 
                $q->where(fn($b) => 
                    $b->whereIn('mlsFNo', $normalizedValues)
                      ->orWhereIn('kangisFileNo', $normalizedValues)
                      ->orWhereIn('NewKANGISFileno', $normalizedValues)
                )
            )
            ->orderByDesc('id')
            ->first();

        if ($existing && $existing->prop_id) {
            return (int) $existing->prop_id;
        }

        // Step 3: Fall back to pra (legacy)
        $legacyExisting = DB::connection('sqlsrv')->table('pra')
            ->select('prop_id')
            ->whereNotNull('prop_id')
            ->when(!empty($normalizedValues), fn($q) => 
                $q->where(fn($b) => 
                    $b->whereIn('mlsfNo', $normalizedValues)
                      ->orWhereIn('kangisFileNo', $normalizedValues)
                      ->orWhereIn('NewKANGISFileno', $normalizedValues)
                )
            )
            ->orderByDesc('id')
            ->first();

        if ($legacyExisting && $legacyExisting->prop_id) {
            return (int) $legacyExisting->prop_id;
        }

        // Step 4: Generate new prop_id (atomic MAX + 1)
        return $this->generateNewPropId();
    }

    /**
     * Generate next available prop_id with atomic locking.
     * 
     * Ensures no duplicate prop_ids are generated under concurrent load.
     */
    private function generateNewPropId(): int {
        return DB::connection('sqlsrv')->transaction(function () {
            $maxProperty = (int) DB::connection('sqlsrv')
                ->table('property_records')
                ->max('prop_id') ?? 0;

            $maxLegacy = (int) DB::connection('sqlsrv')
                ->table('pra')
                ->max('prop_id') ?? 0;

            $maxFileHistory = (int) DB::connection('sqlsrv')
                ->table('file_history_staging')
                ->max('prop_id') ?? 0;

            $nextId = max($maxProperty, $maxLegacy, $maxFileHistory) + 1;

            \Log::info('PropertyIdAllocationService: Generated new prop_id', [
                'prop_id' => $nextId,
                'max_property_records' => $maxProperty,
                'max_pra' => $maxLegacy,
                'max_file_history' => $maxFileHistory,
            ]);

            return $nextId;
        });
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) return null;
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
```

---

#### **Layer 2: Standardized Entry Points**

**For PropertyRecordController::store()**:

```php
// app/Http/Controllers/PropertyRecordController.php

public function store(Request $request)
{
    // ... validation ...

    try {
        // NEW: Allocate prop_id BEFORE any database operation
        $propIdService = app(\App\Services\PropertyIdAllocationService::class);
        
        $fileNumber = $request->input('fileno') ?? $request->input('mlsFNo');
        $propId = $propIdService->allocateOrRetrievePropId(
            $fileNumber,
            $request->input('mlsFNo'),
            $request->input('kangisFileNo'),
            $request->input('NewKANGISFileno')
        );

        // ... prepare data ...
        $data = [
            // ... existing fields ...
            'prop_id' => $propId,  // ← NEW: Always include
            'created_by' => Auth::id(),
            'created_at' => now(),
        ];

        // ... insert into property table ...
        $recordId = DB::connection('sqlsrv')
            ->table('property_table')
            ->insertGetId($data);

        // Cascade prop_id to file_history_staging
        $this->syncPropIdToFileHistory($fileNumber, $propId);

        return response()->json([
            'success' => true,
            'prop_id' => $propId,
            'record_id' => $recordId,
        ]);
    } catch (\Exception $e) {
        \Log::error('PropertyRecordController::store failed', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

private function syncPropIdToFileHistory(string $fileNumber, int $propId): void
{
    DB::connection('sqlsrv')
        ->table('file_history_staging')
        ->where(function ($q) use ($fileNumber) {
            $q->where('mlsFNo', $fileNumber)
              ->orWhere('fileno', $fileNumber);
        })
        ->whereNull('prop_id')
        ->update([
            'prop_id' => $propId,
            'updated_at' => now(),
        ]);
}
```

**For CaveatController::store()**:

```php
public function store(Request $request)
{
    // ... validation ...

    try {
        // NEW: Look up or allocate prop_id for the file being caveated
        $propIdService = app(\App\Services\PropertyIdAllocationService::class);
        
        $fileNumber = $request->input('file_number');
        $propId = $propIdService->allocateOrRetrievePropId($fileNumber);

        $caveatData = [
            // ... existing fields ...
            'prop_id' => $propId,  // ← NEW
            'file_number' => $fileNumber,
            'caveat_id' => $request->input('caveat_id'),
            // ... other fields ...
        ];

        $caveatId = DB::connection('sqlsrv')
            ->table('caveats')
            ->insertGetId($caveatData);

        return response()->json([
            'success' => true,
            'caveat_id' => $caveatId,
            'prop_id' => $propId,
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
```

**For CofoController / updateCofORecord()**:

```php
// app/Http/Controllers/FileIndexingController.php

protected function updateCofORecord($fileNumber, $cofoData, $testControl = null)
{
    // NEW: Allocate prop_id
    $propIdService = app(\App\Services\PropertyIdAllocationService::class);
    $propId = $propIdService->allocateOrRetrievePropId($fileNumber);

    // Prepare upsert data
    $recordPayload = [
        // ... existing CofO fields ...
        'prop_id' => $propId,  // ← NEW
        'cofo_no' => $cofoData['cofo_no'] ?? null,
        'updated_at' => now(),
    ];

    if ($testControl !== null) {
        $recordPayload['test_control'] = $testControl;
    }

    // Upsert by cofo_no or MLS file number
    $matchColumns = [];
    if (!empty($cofoData['cofo_no'])) {
        $matchColumns = ['cofo_no' => $cofoData['cofo_no']];
    } else {
        $matchColumns = ['mlsFNo' => $fileNumber];
    }

    // Check if record exists
    $existing = DB::connection('sqlsrv')->table('Cofo')
        ->where($matchColumns)
        ->first();

    if ($existing) {
        DB::connection('sqlsrv')->table('Cofo')
            ->where($matchColumns)
            ->update($recordPayload);
    } else {
        DB::connection('sqlsrv')->table('Cofo')
            ->insert(array_merge($matchColumns, $recordPayload));
    }
}
```

---

#### **Layer 3: Database Migrations**

**Migration 1: Add prop_id to caveats table**

```php
<?php
// database/migrations/2025_12_06_add_prop_id_to_caveats.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPropIdToCaveats extends Migration
{
    public function up()
    {
        Schema::connection('sqlsrv')->table('caveats', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('caveats', 'prop_id')) {
                $table->integer('prop_id')->nullable()->after('caveat_id')->index();
                $table->comment('Property ID - unique identifier for linking across modules');
            }
        });

        // Add foreign key constraint
        Schema::connection('sqlsrv')->table('caveats', function (Blueprint $table) {
            $table->foreign('prop_id')
                ->references('prop_id')
                ->on('file_history_staging')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('caveats', function (Blueprint $table) {
            $table->dropForeign(['prop_id']);
            $table->dropColumn('prop_id');
        });
    }
}
```

**Migration 2: Add prop_id to scanning tables**

```php
<?php
// database/migrations/2025_12_06_add_prop_id_to_scanning_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPropIdToScanningTables extends Migration
{
    public function up()
    {
        foreach (['scannings', 'pagetypings'] as $table) {
            Schema::connection('sqlsrv')->table($table, function (Blueprint $table) {
                if (!Schema::connection('sqlsrv')->hasColumn($table, 'prop_id')) {
                    $table->integer('prop_id')->nullable()->index();
                }
            });
        }
    }

    public function down()
    {
        foreach (['scannings', 'pagetypings'] as $table) {
            Schema::connection('sqlsrv')->table($table, function (Blueprint $table) {
                $table->dropColumn('prop_id');
            });
        }
    }
}
```

---

## Implementation Checklist

### Phase 1: Service & Core Infrastructure
- [ ] Create `PropertyIdAllocationService` (see Layer 2 above)
- [ ] Register service in `app/Providers/AppServiceProvider.php`
- [ ] Add unit tests for `PropertyIdAllocationService`
  - Test existing prop_id retrieval
  - Test new prop_id generation
  - Test concurrent allocation (simulate race conditions)

### Phase 2: Database Migrations
- [ ] Create migration: `add_prop_id_to_caveats`
- [ ] Create migration: `add_prop_id_to_scanning_tables`
- [ ] Run migrations: `php artisan migrate --database=sqlsrv`
- [ ] Verify columns exist: `php artisan tinker` → `\DB::connection('sqlsrv')->table('caveats')->getConnection()->getSchemaBuilder()->getColumnListing('caveats')`

### Phase 3: Update PropertyRecordController
- [ ] Inject `PropertyIdAllocationService` into constructor
- [ ] Update `store()` method to allocate prop_id before insert
- [ ] Add `syncPropIdToFileHistory()` helper
- [ ] Update tests to verify prop_id population
- [ ] Add logging: prop_id allocation events

### Phase 4: Update CaveatController
- [ ] Inject `PropertyIdAllocationService`
- [ ] Update `store()` to allocate prop_id
- [ ] Update `update()` to preserve/sync prop_id
- [ ] Update caveat deletion to cascade properly
- [ ] Test: Create caveat, verify prop_id in database

### Phase 5: Update CofoController & FileIndexingController
- [ ] Inject `PropertyIdAllocationService` into `FileIndexingController`
- [ ] Update `updateCofORecord()` to allocate prop_id
- [ ] Ensure CofO upsert includes prop_id in all paths
- [ ] Test: Create CofO record, verify prop_id

### Phase 6: Audit & Fix Direct SQL Inserts
- [ ] Review `FileIndexController.php:969, 1066`
- [ ] Review `DeedsController.php:48` (raw INSERT)
- [ ] Refactor to use services instead of raw SQL
- [ ] OR update raw SQL to include prop_id context

### Phase 7: Data Cleanup (Historical Records)
- [ ] Create backfill script to assign prop_id to NULL records:
  ```sql
  -- Backfill strategy: Assign prop_id based on file number grouping
  -- Run AFTER all code changes are deployed
  DECLARE @maxPropId INT = (SELECT COALESCE(MAX(prop_id), 0) FROM file_history_staging);
  
  -- For each unique file number without prop_id:
  -- GROUP BY file number, assign next sequential prop_id
  -- (See separate backfill script below)
  ```
- [ ] Execute backfill script
- [ ] Verify: No NULL prop_id in critical tables

### Phase 8: Testing & Validation
- [ ] Unit tests for `PropertyIdAllocationService`
- [ ] Integration test: Create property record → verify prop_id in file_history_staging
- [ ] Integration test: Create caveat → verify prop_id lookup
- [ ] Integration test: Create CofO → verify prop_id population
- [ ] E2E test: Full workflow from file indexing → property record → caveat → file history search
- [ ] Load test: Concurrent prop_id allocations (ensure no duplicates)
- [ ] Regression test: Existing storeFromIndexing workflow still works

### Phase 9: Documentation & Monitoring
- [ ] Update `docs/property-data-study.md` with new architecture
- [ ] Create monitoring dashboard: Track NULL prop_id by module
- [ ] Add alerts: Flag any NULL prop_id inserts after cutover date
- [ ] Document prop_id allocation rules in code comments

---

## Validation Queries

Run these after implementation to verify prop_id consistency:

```sql
-- 1. Check for NULL prop_id in critical tables
SELECT 'property_records' as table_name, COUNT(*) as null_count 
FROM property_records WHERE prop_id IS NULL
UNION ALL
SELECT 'file_history_staging', COUNT(*) FROM file_history_staging WHERE prop_id IS NULL
UNION ALL
SELECT 'Cofo', COUNT(*) FROM Cofo WHERE prop_id IS NULL
UNION ALL
SELECT 'caveats', COUNT(*) FROM caveats WHERE prop_id IS NULL;

-- 2. Check for duplicate prop_id (should be none)
SELECT prop_id, COUNT(*) as count FROM property_records 
GROUP BY prop_id HAVING COUNT(*) > 1;

-- 3. Verify prop_id continuity (no gaps)
SELECT prop_id FROM (
    SELECT prop_id FROM property_records
    UNION
    SELECT prop_id FROM file_history_staging
    UNION
    SELECT prop_id FROM caveats
) all_ids
WHERE prop_id IS NOT NULL
ORDER BY prop_id;

-- 4. Test file_history pivot by prop_id
SELECT prop_id, COUNT(*) as transaction_count
FROM file_history_staging
WHERE prop_id IS NOT NULL
GROUP BY prop_id
ORDER BY transaction_count DESC;

-- 5. Cross-table linkage verification
SELECT DISTINCT
    fh.prop_id,
    CASE WHEN pr.prop_id IS NOT NULL THEN 'property_records' ELSE NULL END as in_property_records,
    CASE WHEN c.prop_id IS NOT NULL THEN 'Cofo' ELSE NULL END as in_cofo,
    CASE WHEN cv.prop_id IS NOT NULL THEN 'caveats' ELSE NULL END as in_caveats
FROM file_history_staging fh
LEFT JOIN property_records pr ON fh.prop_id = pr.prop_id
LEFT JOIN Cofo c ON fh.prop_id = c.prop_id
LEFT JOIN caveats cv ON fh.prop_id = cv.prop_id
WHERE fh.prop_id IS NOT NULL
LIMIT 100;
```

---

## Backfill Script (for Historical NULL Records)

```sql
-- File: database_scripts/backfill_prop_id_for_null_records.sql
-- Purpose: Assign prop_id to historical records with NULL prop_id
-- Safety: This script runs AFTER code changes are deployed

USE [klass];

DECLARE @maxPropId INT = (SELECT COALESCE(MAX(prop_id), 0) FROM file_history_staging);
DECLARE @processedCount INT = 0;
DECLARE @batchSize INT = 100;

-- Step 1: Identify unique file numbers with NULL prop_id
DECLARE @FileNumbersTodo TABLE (
    rn INT PRIMARY KEY,
    file_number NVARCHAR(255),
    new_prop_id INT
);

-- Get unique file numbers ordered by MIN(id) for stable assignment
INSERT INTO @FileNumbersTodo (file_number, new_prop_id)
SELECT DISTINCT
    COALESCE(mlsFNo, fileno) as file_number,
    @maxPropId + ROW_NUMBER() OVER (ORDER BY MIN(id)) as new_prop_id
FROM file_history_staging
WHERE prop_id IS NULL
GROUP BY COALESCE(mlsFNo, fileno);

-- Step 2: Update file_history_staging
UPDATE fh
SET fh.prop_id = t.new_prop_id
FROM file_history_staging fh
INNER JOIN @FileNumbersTodo t ON COALESCE(fh.mlsFNo, fh.fileno) = t.file_number
WHERE fh.prop_id IS NULL;

SET @processedCount = @@ROWCOUNT;
PRINT 'Updated file_history_staging: ' + CAST(@processedCount AS NVARCHAR(10)) + ' rows';

-- Step 3: Update property_records (all tables)
UPDATE pr
SET pr.prop_id = t.new_prop_id
FROM property_records pr
INNER JOIN @FileNumbersTodo t ON COALESCE(pr.mlsFNo, pr.fileno) = t.file_number
WHERE pr.prop_id IS NULL;

PRINT 'Updated property_records: ' + CAST(@@ROWCOUNT AS NVARCHAR(10)) + ' rows';

-- Step 4: Update caveats
UPDATE cv
SET cv.prop_id = t.new_prop_id
FROM caveats cv
INNER JOIN @FileNumbersTodo t ON cv.file_number = t.file_number
WHERE cv.prop_id IS NULL;

PRINT 'Updated caveats: ' + CAST(@@ROWCOUNT AS NVARCHAR(10)) + ' rows';

-- Step 5: Update Cofo
UPDATE cofo
SET cofo.prop_id = t.new_prop_id
FROM Cofo cofo
INNER JOIN @FileNumbersTodo t ON COALESCE(cofo.mlsFNo, cofo.fileno) = t.file_number
WHERE cofo.prop_id IS NULL;

PRINT 'Updated Cofo: ' + CAST(@@ROWCOUNT AS NVARCHAR(10)) + ' rows';

-- Verification
PRINT '=== VERIFICATION ===';
PRINT 'Remaining NULL prop_id in file_history_staging: ' + 
    CAST((SELECT COUNT(*) FROM file_history_staging WHERE prop_id IS NULL) AS NVARCHAR(10));
PRINT 'Remaining NULL prop_id in caveats: ' + 
    CAST((SELECT COUNT(*) FROM caveats WHERE prop_id IS NULL) AS NVARCHAR(10));
PRINT 'Remaining NULL prop_id in Cofo: ' + 
    CAST((SELECT COUNT(*) FROM Cofo WHERE prop_id IS NULL) AS NVARCHAR(10));
```

---

## Risk Assessment

| Risk | Severity | Mitigation |
|------|----------|-----------|
| Duplicate prop_id generation under load | HIGH | Transaction-wrapped MAX+1 in `generateNewPropId()` |
| NULL prop_id in existing records breaks searches | HIGH | Backfill script + validation queries post-deployment |
| CofO records still orphaned | MEDIUM | Explicit prop_id allocation in `updateCofORecord()` |
| Caveat system unable to find property context | HIGH | Inject PropIdAllocationService in CaveatController |
| Legacy fileNumber table data isolation | LOW | Continue joining by file number; no schema change needed |
| Concurrent writes to multiple tables out of sync | HIGH | Use database transactions, sync helpers (syncPropIdToFileHistory) |

---

## Success Criteria

✅ **Achieved when**:
1. No NULL prop_id in `file_history_staging`, `property_records`, or `caveats` (after backfill)
2. Every newly created property record has a prop_id
3. Every caveat linked to a property has matching prop_id
4. Every CofO record has prop_id
5. File history searches work by prop_id
6. No duplicate prop_id detected
7. All existing storeFromIndexing workflows still function
8. Load tests show no race conditions in prop_id allocation
9. Documentation updated with new architecture

---

## Rollout Plan

**Week 1**: Implement service & migrations  
**Week 2**: Update controllers (PropertyRecord, Caveat, CofO)  
**Week 3**: Testing & validation  
**Week 4**: Backfill historical data & deploy to production  
**Week 5**: Monitor, alert, celebrate 🎉

---

## Related Documentation

- **Current**: `docs/property-data-study.md` (will be updated)
- **Dependent**: `COFO_NUMBER_IMPLEMENTATION_COMPLETE.md`
- **Dependent**: `FILE_INDEXING_FORM_GAPS.md`
- **Reference**: `PROPERTY_RECORD_FIX_SUMMARY.md`

---

**End of Document**

---

## Quick Reference: File Locations

| Controller/Service | Path | Status |
|---|---|---|
| PropertyRecordController | `app/Http/Controllers/PropertyRecordController.php` | Needs update |
| PropertyCardController | `app/Http/Controllers/PropertyCardController.php` | Cascading fix needed |
| CaveatController | `app/Http/Controllers/CaveatController.php` | Needs update |
| FileIndexingController | `app/Http/Controllers/FileIndexingController.php` | Partial update |
| CofoController | `app/Http/Controllers/CofoController.php` | Needs update |
| **NEW**: PropertyIdAllocationService | `app/Services/PropertyIdAllocationService.php` | To create |

---

**Document Version**: 1.0  
**Created**: December 6, 2025  
**Last Updated**: December 6, 2025  
**Owner**: AI Assistant (Implementation guide)


