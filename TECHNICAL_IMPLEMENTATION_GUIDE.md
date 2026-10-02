# Technical Implementation Guide: Entity & Customer Sync

## Executive Summary

This guide provides the technical implementation for syncing missing entity and customer records from `file_indexings` to their staging tables, while excluding DCIV- and LPPC- prefixed file numbers.

**Key Takeaways:**
- ✅ Check queries identify exactly which records are missing
- ✅ SQL scripts backfill with proper defaults and relationships
- ✅ DCIV-/LPPC- exclusion is implemented at the WHERE clause level
- ✅ All records include audit trails (created_by, created_at, updated_at)
- ✅ Entity-Customer relationships are automatically established

---

## Code Analysis: How Sync Currently Works

### The `syncEntityAndCustomer()` Method
**Location**: `FileIndexingController.php` lines 2678-2797

This method is called whenever a file is indexed (unless it's Block indexing or DCIV Registry).

**Pseudocode:**
```
1. Get entity details from request
   - If not provided, create from file_title
   
2. Create/Update Entity Record:
   CREATE Entity {
     entity_name: from request or file_title
     entity_type: from request or 'Individual'
     file_number: from indexed file
   }
   
3. Get customer details from request
   - Fallback hierarchy: customer_name → entity_name → file_title
   
4. Create/Update Customer Record:
   CREATE Customer {
     customer_name: from request or fallback
     customer_type: from request or 'Individual'
     file_number: from indexed file
     entity_id: link to entity created in step 2
   }
```

### When `syncEntityAndCustomer()` is NOT Called
**Location**: `FileIndexingController.php` lines 2328-2330

```php
if ($indexingType !== 'Block' && $validated['general_registry'] !== 'DCIV Registry') {
    $this->syncEntityAndCustomer($fileIndexing, $request, $resolvedTestControl);
}
```

**Excluded scenarios:**
1. **Block Indexing**: `indexing_type = 'Block'`
   - Uses different logic with FileIndexingLink records
   - Does NOT create entity/customer records

2. **DCIV Registry**: `general_registry = 'DCIV Registry'`
   - Has special handling for complaint-driven records
   - Entity/Customer creation is skipped intentionally

---

## The Backfill Strategy

### Why Backfill is Needed

**Scenario**: A file was indexed, but:
- User didn't provide entity/customer details in the form, OR
- Sync process failed silently, OR
- Historical records pre-date this sync logic

**Solution**: Backfill using the file_indexing record as source of truth

### Data Flow

```
file_indexings (indexed files)
    ↓
[Check for missing entities/customers]
    ↓
[If missing]
    ├─→ entities_staging (create with file_title as entity_name)
    └─→ customers_staging (create with file_title as customer_name, link to entity)
```

### Field Mapping Logic

**For Entity Creation:**
```
entities_staging.entity_name     ← file_indexings.file_title (required)
entities_staging.entity_type     ← file_indexings.entity_type (default: 'Individual')
entities_staging.file_number     ← file_indexings.file_number
entities_staging.created_at      ← GETDATE()
entities_staging.updated_at      ← GETDATE()
```

**For Customer Creation:**
```
customers_staging.customer_name       ← file_indexings.file_title
customers_staging.customer_type       ← file_indexings.customer_type (default: 'Individual')
customers_staging.file_number         ← file_indexings.file_number
customers_staging.account_no          ← file_indexings.file_number (same as file_number)
customers_staging.property_address    ← file_indexings.property_address OR file_indexings.location
customers_staging.entity_id           ← entities_staging.id (from related entity)
customers_staging.status              ← 'Active'
customers_staging.created_by          ← file_indexings.created_by (default: 1 for system)
customers_staging.created_at          ← GETDATE()
customers_staging.updated_at          ← GETDATE()
```

---

## Implementation Details

### Phase 1: Analysis

**Check 1: Count Missing Entities**
```sql
SELECT COUNT(DISTINCT fi.file_number) AS missing
FROM file_indexings fi
LEFT JOIN entities_staging e ON fi.file_number = e.file_number
WHERE e.id IS NULL
  AND fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%'
```

**Check 2: Count Missing Customers**
```sql
SELECT COUNT(DISTINCT fi.file_number) AS missing
FROM file_indexings fi
LEFT JOIN customers_staging c ON fi.file_number = c.file_number
WHERE c.id IS NULL
  AND fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%'
```

**Check 3: Detailed Missing Records**
```sql
SELECT 
    fi.file_number,
    fi.file_title,
    CASE WHEN e.id IS NULL THEN 'MISSING' ELSE 'EXISTS' END AS entity,
    CASE WHEN c.id IS NULL THEN 'MISSING' ELSE 'EXISTS' END AS customer
FROM file_indexings fi
LEFT JOIN entities_staging e ON fi.file_number = e.file_number
LEFT JOIN customers_staging c ON fi.file_number = c.file_number
WHERE (e.id IS NULL OR c.id IS NULL)
  AND fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%'
```

### Phase 2: Entity Backfill

**Key SQL Pattern:**
```sql
INSERT INTO entities_staging (entity_name, entity_type, file_number, created_at, updated_at)
SELECT DISTINCT
    ISNULL(fi.file_title, fi.file_number),      -- entity_name with fallback
    ISNULL(fi.entity_type, 'Individual'),        -- entity_type with default
    fi.file_number,                              -- file_number (PK part)
    GETDATE(),                                   -- created_at
    GETDATE()                                    -- updated_at
FROM file_indexings fi
WHERE NOT EXISTS (
    SELECT 1 FROM entities_staging e 
    WHERE e.file_number = fi.file_number        -- Prevent duplicates
)
  AND fi.file_number NOT LIKE 'DCIV-%'          -- Exclude DCIV
  AND fi.file_number NOT LIKE 'LPPC-%'          -- Exclude LPPC
  AND fi.workflow_status = 'indexed';            -- Only indexed files
```

**Why DISTINCT?**
- Handles cases where multiple file_indexings have same file_number
- Only creates one entity per file_number

**Why NOT EXISTS?**
- Prevents duplicate inserts if run multiple times
- Safe to re-run without side effects

### Phase 3: Customer Backfill

**Key SQL Pattern:**
```sql
INSERT INTO customers_staging (
    customer_name, customer_type, file_number, account_no, 
    property_address, entity_id, status, created_by, 
    created_at, updated_at
)
SELECT 
    ISNULL(fi.file_title, fi.file_number),      -- customer_name with fallback
    ISNULL(fi.customer_type, 'Individual'),      -- customer_type with default
    fi.file_number,                              -- file_number
    fi.file_number,                              -- account_no = file_number
    ISNULL(fi.property_address, fi.location),    -- property_address with fallback
    e.id,                                        -- entity_id (FK to entities_staging)
    'Active',                                    -- status
    ISNULL(fi.created_by, 1),                    -- created_by with default
    GETDATE(),                                   -- created_at
    GETDATE()                                    -- updated_at
FROM file_indexings fi
INNER JOIN entities_staging e ON fi.file_number = e.file_number  -- Link to entity
WHERE NOT EXISTS (
    SELECT 1 FROM customers_staging c 
    WHERE c.file_number = fi.file_number        -- Prevent duplicates
)
  AND fi.file_number NOT LIKE 'DCIV-%'          -- Exclude DCIV
  AND fi.file_number NOT LIKE 'LPPC-%'          -- Exclude LPPC
  AND fi.workflow_status = 'indexed';            -- Only indexed files
```

**Why INNER JOIN to entities_staging?**
- Ensures we only create customers that have a corresponding entity
- Maintains referential integrity (entity_id always points to real entity)

**Why account_no = file_number?**
- Provides sensible default when no account number was captured
- Creates a unique identifier for each customer record

---

## File Number Filtering Logic

### Pattern Matching

**DCIV Format:**
```
DCIV-YYYY-SERIAL
Example: DCIV-2026-001
Pattern: DCIV-%
```

**LPPC Format:**
```
LPPC-YYYY-SERIAL
Example: LPPC-2025-042
Pattern: LPPC-%
```

**SQL Implementation:**
```sql
WHERE fi.file_number NOT LIKE 'DCIV-%' 
  AND fi.file_number NOT LIKE 'LPPC-%'
```

### Why These Specific Formats Are Excluded

From the code analysis:

1. **Line 2328-2330** in FileIndexingController:
   ```php
   if ($validated['general_registry'] !== 'DCIV Registry') {
       $this->syncEntityAndCustomer($fileIndexing, $request, $resolvedTestControl);
   }
   ```
   - DCIV Registry files don't go through standard entity/customer sync

2. **DcivGenerationController.php** handles DCIV-/LPPC- specially:
   - These are generated through a different workflow
   - Have separate grouping and generation logic
   - Should not be synced with standard entity/customer logic

---

## Database Considerations

### Transaction Safety

**For large backfills, use transactions:**
```sql
BEGIN TRANSACTION;

-- Phase 2: Backfill entities
INSERT INTO entities_staging ...

-- Phase 3: Backfill customers
INSERT INTO customers_staging ...

-- Validation
SELECT COUNT(*) FROM entities_staging WHERE created_at >= DATEADD(HOUR, -1, GETDATE());

-- Commit or rollback
COMMIT;
-- ROLLBACK; -- If something went wrong
```

### Performance Tips

**For large datasets:**
1. Create an index on `file_number` columns before running:
   ```sql
   CREATE INDEX idx_file_indexings_fileno ON file_indexings(file_number);
   CREATE INDEX idx_entities_staging_fileno ON entities_staging(file_number);
   CREATE INDEX idx_customers_staging_fileno ON customers_staging(file_number);
   ```

2. Run in batches rather than all at once:
   ```sql
   DECLARE @batch_size INT = 1000;
   DECLARE @processed INT = 0;
   
   WHILE @processed < (SELECT COUNT(*) FROM file_indexings WHERE ...)
   BEGIN
       -- Insert next batch
       @processed += @@ROWCOUNT;
   END
   ```

### Referential Integrity

**Constraints that should exist:**
```sql
-- Entity-File relationship (optional, but good to have)
ALTER TABLE entities_staging
ADD CONSTRAINT uq_entities_fileno UNIQUE (file_number);

-- Customer-Entity relationship (critical)
ALTER TABLE customers_staging
ADD CONSTRAINT fk_customer_entity 
    FOREIGN KEY (entity_id) 
    REFERENCES entities_staging(id);
```

---

## Monitoring & Auditing

### Track What Was Created

**View recent backfills:**
```sql
SELECT TOP 100 
    id, entity_name, file_number, 
    created_at, updated_at
FROM entities_staging
WHERE created_at >= DATEADD(DAY, -1, GETDATE())
  AND file_number NOT LIKE 'DCIV-%'
  AND file_number NOT LIKE 'LPPC-%'
ORDER BY created_at DESC;
```

### Validate Data Quality

**Check for orphaned customers:**
```sql
SELECT COUNT(*) AS orphaned_customers
FROM customers_staging c
LEFT JOIN entities_staging e ON c.entity_id = e.id
WHERE c.entity_id IS NOT NULL
  AND e.id IS NULL;
```

**Check for null required fields:**
```sql
SELECT 
    COUNT(CASE WHEN entity_name IS NULL THEN 1 END) AS null_entity_names,
    COUNT(CASE WHEN customer_name IS NULL THEN 1 END) AS null_customer_names,
    COUNT(CASE WHEN entity_id IS NULL THEN 1 END) AS null_entity_ids
FROM entities_staging es
FULL OUTER JOIN customers_staging cs ON es.id = cs.entity_id;
```

---

## Troubleshooting

### Issue: "Entity already exists" errors

**Cause**: Constraint violation due to duplicate file_number

**Solution**: 
```sql
-- Check for duplicates in staging
SELECT file_number, COUNT(*) 
FROM entities_staging 
GROUP BY file_number 
HAVING COUNT(*) > 1;

-- Remove duplicates (keep oldest)
DELETE FROM entities_staging 
WHERE id NOT IN (
    SELECT MIN(id) 
    FROM entities_staging 
    GROUP BY file_number
);
```

### Issue: Customers created without entities

**Cause**: Entity creation failed but customer creation succeeded

**Solution**:
```sql
-- Find orphaned customers
SELECT * 
FROM customers_staging c
WHERE c.entity_id IS NULL 
   OR c.entity_id NOT IN (SELECT id FROM entities_staging);

-- Fix by creating missing entities
INSERT INTO entities_staging (entity_name, entity_type, file_number, created_at, updated_at)
SELECT DISTINCT c.customer_name, 'Individual', c.file_number, GETDATE(), GETDATE()
FROM customers_staging c
WHERE NOT EXISTS (SELECT 1 FROM entities_staging e WHERE e.file_number = c.file_number);
```

### Issue: DCIV/LPPC records were backfilled

**Cause**: WHERE clause filter failed or was missing

**Solution**:
```sql
-- Identify incorrect backfills
SELECT * 
FROM entities_staging 
WHERE file_number LIKE 'DCIV-%' OR file_number LIKE 'LPPC-%';

-- Delete them
DELETE FROM customers_staging 
WHERE file_number LIKE 'DCIV-%' OR file_number LIKE 'LPPC-%';

DELETE FROM entities_staging 
WHERE file_number LIKE 'DCIV-%' OR file_number LIKE 'LPPC-%';
```

---

## Verification Checklist

- [ ] Backup database before running scripts
- [ ] Run Phase 1 (Check) queries and review results
- [ ] Verify DCIV-/LPPC- counts are zero in missing records
- [ ] Execute Phase 2 (Entity backfill)
- [ ] Verify entity counts increased appropriately
- [ ] Execute Phase 3 (Customer backfill)
- [ ] Run validation queries to confirm no orphaned records
- [ ] Check application UI to see entities/customers in dropdown menus
- [ ] Review audit logs for created_by and created_at values
- [ ] Create summary report for stakeholders

---

## Files Provided

1. **sql_backfill_entity_customer.sql** - Full 5-part comprehensive script with reporting
2. **QUICK_SYNC_SCRIPT.sql** - Simplified step-by-step version for running independently
3. **ENTITY_CUSTOMER_SYNC_PLAN.md** - High-level planning document
4. **This file** - Technical implementation guide

---

**Last Updated**: March 2, 2026  
**Status**: Ready for Implementation  
**Estimated Runtime**: 5-15 minutes (depends on dataset size)
