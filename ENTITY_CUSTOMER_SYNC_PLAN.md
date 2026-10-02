# Entity & Customer Staging Sync Plan

## Overview
This document outlines a strategy to identify and backfill missing entity and customer records in staging tables based on successfully indexed file numbers, while excluding DCIV- and LPPC- prefixed file numbers.

---

## Understanding the Current System

### Key Tables
- **file_indexings**: Main file indexing records (source of truth for what should exist)
- **entities_staging**: Entity/owner records linked to files
- **customers_staging**: Customer records linked to entities
- **fileNumber** (legacy): Legacy file tracking table

### Key Functions in FileIndexingController

#### `syncEntityAndCustomer()` (Line 2678-2797)
This is the primary function that creates/updates entity and customer records during file indexing creation.

**Process:**
1. Reads entity details from request input
2. Creates or updates `Entity` record with:
   - `entity_name` (defaults to `file_title` if not provided)
   - `entity_type` (defaults to 'Individual')
   - `file_number` (from the indexed file)
3. Creates or updates `Customer` record with:
   - `customer_name` (defaults to entity_name or file_title)
   - `customer_type` (defaults to 'Individual')
   - `file_number`
   - `property_address` (from indexing data)
   - Links to the created/found entity via `entity_id`

#### `store()` (Line 1670)
Main method that creates file indexing records. It:
- Validates file_number against grouping records
- Creates FileIndexing record
- Calls `syncEntityAndCustomer()` UNLESS:
  - Indexing type is 'Block' (block indexing has different logic)
  - Registry is 'DCIV Registry' (explicitly excluded)

---

## Plan to Sync Missing Records

### Phase 1: Analysis & Reporting
**Objective**: Identify which files should have entities/customers but don't

**SQL Approach:**
```sql
1. Query file_indexings for records where:
   - workflow_status = 'indexed'
   - file_number NOT LIKE 'DCIV-%' and NOT LIKE 'LPPC-%'
   - indexing_type != 'Block'
   - created_at >= [cutoff date, e.g., 90 days ago]

2. Left join to entities_staging on file_number
3. Left join to customers_staging on file_number
4. Identify rows where entity or customer records are missing

5. Generate report showing:
   - Total files analyzed
   - Files missing entities
   - Files missing customers
   - Files missing both
```

### Phase 2: Create Missing Entities
**Objective**: Backfill missing entity records

**Logic:**
1. For each file_indexing record missing an entity:
   - Create entity_staging record with:
     - `entity_name`: Use `file_title` from file_indexing
     - `entity_type`: Use `entity_type` from file_indexing (or default to 'Individual')
     - `file_number`: Match from file_indexing
     - `created_at`: Current timestamp
     - `updated_at`: Current timestamp

**Expected Behavior:**
- One entity per file_number
- Deduplicates automatically (same file_number won't create multiple entities)

### Phase 3: Create Missing Customers
**Objective**: Backfill missing customer records

**Logic:**
1. For each file_indexing record missing a customer:
   - Create customer_staging record with:
     - `customer_name`: Use `file_title` from file_indexing
     - `customer_type`: Use `customer_type` from file_indexing (or default to 'Individual')
     - `file_number`: Match from file_indexing
     - `account_no`: Set to file_number
     - `property_address`: Use `property_address` or fallback to `location` from file_indexing
     - `entity_id`: Link to the entity we just created (from Phase 2)
     - `status`: Set to 'Active'
     - `created_by`: Use the file_indexing's `created_by` value (or default system user)

**Expected Behavior:**
- One customer per file_number
- Automatically links to the corresponding entity
- Maintains referential integrity

### Phase 4: Validation
**Objective**: Verify backfill completeness

**Checks:**
1. Count of still-missing entities (should be zero)
2. Count of still-missing customers (should be zero)
3. Entity-Customer linkage integrity (all customers have entity_id)
4. File number filter validation (no DCIV-/LPPC- records were backfilled)

---

## Data Mapping

### Entity Record Mapping
| Source Field | Destination Table | Destination Field | Default |
|---|---|---|---|
| file_title | entities_staging | entity_name | (required) |
| entity_type | entities_staging | entity_type | 'Individual' |
| file_number | entities_staging | file_number | (required) |

### Customer Record Mapping
| Source Field | Destination Table | Destination Field | Default |
|---|---|---|---|
| file_title | customers_staging | customer_name | (required) |
| customer_type | customers_staging | customer_type | 'Individual' |
| file_number | customers_staging | file_number | (required) |
| file_number | customers_staging | account_no | file_number |
| property_address OR location | customers_staging | property_address | NULL |
| created_by | customers_staging | created_by | system user (1) |

---

## File Number Filtering

### What to INCLUDE
- Regular file numbers (not starting with DCIV- or LPPC-)
- Examples:
  - GKN-2026-001
  - LPKN-2025-042
  - MLS-12345
  - Custom-ABC-123

### What to EXCLUDE
- **DCIV- prefix**: All DCIV-YYYY-SERIAL format numbers
- **LPPC- prefix**: All LPPC-YYYY-SERIAL format numbers

**Rationale:**
- DCIV Registry files have special handling (explicitly excluded in `syncEntityAndCustomer()` call at line 2328)
- LPPC files follow the DCIV system and are also excluded

**Implementation:**
```sql
WHERE file_number NOT LIKE 'DCIV-%' 
  AND file_number NOT LIKE 'LPPC-%'
```

---

## Parameters & Configuration

### Scope Constraints
These can be adjusted based on requirements:

| Parameter | Default | Purpose | Note |
|---|---|---|---|
| Date Range | 90 days back | Limit backfill to recent files | Adjust DATEADD value |
| Workflow Status | 'indexed' | Only sync fully indexed files | Prevents incomplete indexing |
| Indexing Type | NOT 'Block' | Exclude block indexing | Different sync logic |
| Registry Exclusion | 'DCIV Registry' | Exclude DCIV-specific files | Already excluded by prefix |

### Adjustments for Different Scenarios

**Scenario 1: Backfill ALL missing records (no date limit)**
```sql
-- Remove or modify:
AND fi.created_at >= DATEADD(DAY, -90, CAST(GETDATE() AS DATE))
```

**Scenario 2: Only sync files from specific registry**
```sql
-- Add filter:
AND fi.general_registry = 'Lands Registry'
```

**Scenario 3: Only files with specific statuses**
```sql
-- Modify workflow_status condition:
AND fi.workflow_status IN ('indexed', 'verified')
```

---

## Risk Mitigation

### Pre-Backfill Checks
1. **Backup**: Ensure databases are backed up before running
2. **Dry Run**: SQL script first generates reports without making changes (PART 1)
3. **Validation**: PART 4 validates results after backfill

### Potential Issues & Solutions

| Issue | Root Cause | Solution |
|---|---|---|
| Duplicate records created | Race condition during concurrent indexing | Use transaction and UNIQUE constraints |
| Wrong entity linked to customer | File number mismatch | Validate file_number matches in both tables |
| Missing metadata | Source file_indexing incomplete | Fallback to defaults in mapping logic |
| DCIV/LPPC records included | Filter logic error | Double-check WHERE clause in SQL |

---

## Execution Steps

### Step 1: Run Check-Only Report
Execute SQL script up to **PART 1** to see how many records are missing.

### Step 2: Review Results
- Check the "DETAILED MISSING RECORDS" section
- Verify DCIV-/LPPC- numbers are correctly excluded
- Confirm dates and counts match expectations

### Step 3: Execute Backfill
If results look correct, execute **PARTS 2-3** to create missing records.

### Step 4: Validate
Run **PART 4** to confirm backfill was successful.

### Step 5: Monitor
- Check application logs for any errors
- Verify entity-customer relationships in application UI
- Run periodic audits to catch future gaps

---

## Success Criteria

✓ All indexed file numbers (excluding DCIV-/LPPC-) have corresponding entity records  
✓ All indexed file numbers (excluding DCIV-/LPPC-) have corresponding customer records  
✓ Every customer record has a valid entity_id pointing to existing entity  
✓ No DCIV- or LPPC- prefixed file numbers were backfilled  
✓ All created records have proper timestamps and user attribution  

---

## SQL Files

The following SQL file implements this plan:

**`sql_backfill_entity_customer.sql`**
- Contains all 5 parts (Check, Backfill Entities, Backfill Customers, Summary, Validation)
- Can be run in segments or as a complete batch
- Includes detailed logging and reporting

---

## Questions & Clarifications Needed

1. **Date Range**: Should we backfill ALL missing records, or only recent ones? (Current: 90 days)
2. **Default Values**: What default `created_by` user should be assigned? (Current: system user ID 1)
3. **Test Mode**: Should we create a test run first with a subset of data?
4. **Notification**: Should administrators be notified after backfill completes?

---

**Generated**: March 2, 2026  
**Status**: Ready for Review and Execution
