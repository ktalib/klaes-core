# Summary: Entity & Customer Staging Sync

## What Was Delivered

I've analyzed the file indexing system and created a complete plan to sync missing entity and customer records. Here's what you received:

### 📋 Documentation Files

1. **ENTITY_CUSTOMER_SYNC_PLAN.md** - High-level strategy and planning document
2. **TECHNICAL_IMPLEMENTATION_GUIDE.md** - Deep technical details and code analysis
3. **This summary** - Quick reference

### 💾 SQL Implementation Files

1. **sql_backfill_entity_customer.sql** - Complete 5-part script with full reporting
2. **QUICK_SYNC_SCRIPT.sql** - Simplified step-by-step version for gradual execution

---

## Quick Start

### Step 1: Run Check Queries (Safe - No Changes)
Execute the first section of `QUICK_SYNC_SCRIPT.sql`:
```sql
-- How many files are missing entities?
SELECT COUNT(DISTINCT fi.file_number) AS files_missing_entities
FROM file_indexings fi
LEFT JOIN entities_staging e ON fi.file_number = e.file_number
WHERE e.id IS NULL
  AND fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%';
```

### Step 2: Review the Results
- How many files need entity records?
- How many files need customer records?
- Are the sample records what you expected?

### Step 3: Execute Backfill
Once satisfied, run the INSERT statements from the same script:
```sql
-- Create missing entities
INSERT INTO entities_staging (...) 
SELECT DISTINCT ... FROM file_indexings ...

-- Create missing customers  
INSERT INTO customers_staging (...)
SELECT ... FROM file_indexings fi
INNER JOIN entities_staging e ON fi.file_number = e.file_number ...
```

### Step 4: Validate Results
Run the verification queries to confirm success.

---

## How It Works

### The Sync Logic

**When you index a file in the application:**
1. FileIndexingController.store() is called
2. A new file_indexings record is created
3. Unless it's:
   - Block indexing (`indexing_type = 'Block'`), OR
   - DCIV Registry (`general_registry = 'DCIV Registry'`)
4. Then syncEntityAndCustomer() is called, which creates:
   - An Entity record in `entities_staging`
   - A Customer record in `customers_staging` linked to that entity

**What we're backfilling:**
- Files that were indexed but the sync didn't happen (for whatever reason)
- We use the file_indexings record as the source of truth
- We create the missing entity and customer records with sensible defaults

### File Number Filtering

**We INCLUDE (backfill):**
- GKN-2026-001
- LPKN-2025-042
- MLS-12345
- Any regular file numbers

**We EXCLUDE (don't backfill):**
- ❌ DCIV-2026-001 (DCIV prefix)
- ❌ LPPC-2025-042 (LPPC prefix)

**Reason:** These file numbers have special handling and their entity/customer records are managed through a different process (DcivGenerationController, etc.)

---

## Data Mapping

### Entity Creation
```
file_indexings.file_title        → entities_staging.entity_name
file_indexings.entity_type       → entities_staging.entity_type (default: 'Individual')
file_indexings.file_number       → entities_staging.file_number
```

### Customer Creation
```
file_indexings.file_title        → customers_staging.customer_name
file_indexings.customer_type     → customers_staging.customer_type (default: 'Individual')
file_indexings.file_number       → customers_staging.file_number
file_indexings.file_number       → customers_staging.account_no
file_indexings.property_address  → customers_staging.property_address
↑ Entity from above              → customers_staging.entity_id (FK link)
'Active'                         → customers_staging.status
file_indexings.created_by        → customers_staging.created_by
```

---

## Key Features of the Solution

✅ **Safe** - Check queries don't modify anything  
✅ **Idempotent** - Can run multiple times without creating duplicates  
✅ **Filtered** - Automatically excludes DCIV-/LPPC- prefixes  
✅ **Linked** - Customers are automatically linked to their entities  
✅ **Auditable** - All records include created_by, created_at, updated_at  
✅ **Validated** - Includes post-execution verification  
✅ **Documented** - Full technical analysis included  

---

## Execution Plan

### Recommended Approach

**Day 1: Analyze**
- Run the CHECK queries from QUICK_SYNC_SCRIPT.sql
- Review counts and sample records
- Verify DCIV-/LPPC- numbers are correctly excluded
- Get stakeholder approval

**Day 2: Execute** (with backup)
- Backup the database
- Run entity backfill
- Verify entity records created
- Run customer backfill
- Verify customer records created with correct entity_id links
- Test in application UI

**Day 3: Monitor**
- Watch application logs
- Verify entity/customer dropdowns show new records
- Run periodic audits

---

## Parameters You Can Adjust

### Date Range Filter
Currently: Last 90 days  
If you want ALL records:
```sql
-- Remove or comment out:
AND fi.created_at >= DATEADD(DAY, -90, CAST(GETDATE() AS DATE))
```

### Default created_by User
Currently: System user ID 1  
To use a different user:
```sql
-- Change:
ISNULL(fi.created_by, 1)
-- To:
ISNULL(fi.created_by, YOUR_USER_ID)
```

### Specific Registry Filter
To only sync specific registries:
```sql
AND fi.general_registry = 'Lands Registry'
-- or
AND fi.general_registry IN ('Lands Registry', 'Other Registry')
```

---

## Risk Mitigation

### Before You Start
1. ✅ **Backup your database**
   ```sql
   BACKUP DATABASE [your_db] TO DISK = 'C:\backups\backup_before_sync.bak';
   ```

2. ✅ **Run in test environment first** (if available)
   - Verify behavior matches expectations
   - Check data mapping is correct

3. ✅ **Review the CHECK queries**
   - Confirm counts make sense
   - Spot-check sample records

### During Execution
1. ✅ **Use transactions**
   ```sql
   BEGIN TRANSACTION;
   -- ... run backfill ...
   COMMIT;  -- or ROLLBACK;
   ```

2. ✅ **Monitor @@ROWCOUNT**
   - Confirms records were actually inserted
   - Can catch unexpected zero counts

### After Execution
1. ✅ **Run validation queries**
   - Check no orphaned records
   - Verify entity-customer links
   - Confirm DCIV/LPPC were excluded

2. ✅ **Test in application**
   - Load file indexing pages
   - Verify entities/customers appear in dropdowns
   - Check relationship integrity

---

## Troubleshooting

### If no records are created
- Check the WHERE clause filters
- Verify file_indexings actually has records matching criteria
- Ensure workflow_status = 'indexed'
- Look for DCIV-/LPPC- patterns that shouldn't match

### If too many records are created
- Check date range filter
- Verify exclusions are working (DCIV-/LPPC-)
- Look for duplicate file_numbers in source data

### If customers created without entities
- Run the "Fix orphaned customers" query from TECHNICAL_IMPLEMENTATION_GUIDE.md
- This usually only happens if entity insert failed silently

### If validation shows DCIV/LPPC records were created
- This shouldn't happen with provided scripts
- If it does, clean up with the DELETE queries in troubleshooting section

---

## Files Location

All files are in: `c:\xampp\htdocs\klas\`

- `sql_backfill_entity_customer.sql` - Full comprehensive script
- `QUICK_SYNC_SCRIPT.sql` - Simple step-by-step version
- `ENTITY_CUSTOMER_SYNC_PLAN.md` - Planning document
- `TECHNICAL_IMPLEMENTATION_GUIDE.md` - Deep technical analysis
- `SUMMARY.md` - This file

---

## Next Steps

1. **Review** the ENTITY_CUSTOMER_SYNC_PLAN.md
2. **Run** the CHECK queries from QUICK_SYNC_SCRIPT.sql
3. **Decide** on scope (all records vs. last 90 days, etc.)
4. **Backup** the database
5. **Execute** the backfill queries
6. **Validate** with the verification queries
7. **Test** in the application
8. **Monitor** for any issues

---

## Questions?

Refer to:
- **"How does it work?"** → See TECHNICAL_IMPLEMENTATION_GUIDE.md
- **"What will it do?"** → See ENTITY_CUSTOMER_SYNC_PLAN.md
- **"How do I run it?"** → See QUICK_SYNC_SCRIPT.sql
- **"What went wrong?"** → See Troubleshooting section

---

**Prepared**: March 2, 2026  
**Status**: ✅ Ready for Review & Execution  
**Estimated Execution Time**: 5-15 minutes  
**Rollback Capability**: Yes (via database backup)
