# Visual Guide: Entity & Customer Sync Architecture

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    FILE INDEXING WORKFLOW                              │
└─────────────────────────────────────────────────────────────────────────┘

USER INDEXES A FILE
        │
        ▼
┌──────────────────────────────────────────┐
│  FileIndexingController.store()          │
│  - Validates file_number                 │
│  - Creates file_indexings record         │
└──────────────────────────────────────────┘
        │
        ▼
    [Is it Block Indexing?]
    YES → Use FileIndexingLink logic (not part of this sync)
    NO  → Continue
        │
        ▼
    [Is it DCIV Registry?]
    YES → Skip entity/customer sync (handled elsewhere)
    NO  → Continue
        │
        ▼
┌──────────────────────────────────────────┐
│ syncEntityAndCustomer() is called        │
│ - Creates entity_staging record          │
│ - Creates customers_staging record       │
│ - Links customer → entity                │
└──────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          DATABASE RESULT                               │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  file_indexings                 entities_staging    customers_staging   │
│  ┌──────────────────┐          ┌──────────────────┐ ┌──────────────────┐
│  │ id               │◄────────┐│ id               │◄│ id               │
│  │ file_number      │     ┌───┤│ file_number      │ │ file_number      │
│  │ file_title       │     │   │ entity_name      │ │ customer_name    │
│  │ entity_type      │     │   │ entity_type      │ │ customer_type    │
│  │ customer_type    │     │   │ created_at       │ │ account_no       │
│  │ property_address │     │   │ updated_at       │ │ entity_id        │─────┐
│  │ created_at       │     │   │                  │ │ status           │     │
│  └──────────────────┘     │   └──────────────────┘ │ property_address │     │
│                            │                       │ created_by       │     │
│                            │                       │ created_at       │     │
│                            │                       │ updated_at       │     │
│                            │                       └──────────────────┘     │
│                            └───────────────────────────────────────────────┘
│                                    1:1 Relationship
│
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Data Flow for Backfill Operation

```
┌───────────────────────────────────────────────────────────────────────┐
│                     BACKFILL PROCESS                                  │
└───────────────────────────────────────────────────────────────────────┘

STEP 1: IDENTIFY MISSING RECORDS
─────────────────────────────────
    file_indexings (ALL RECORDS)
            │
            ├─ Filter by: NOT DCIV-* and NOT LPPC-*
            ├─ Filter by: workflow_status = 'indexed'
            ├─ Filter by: indexing_type != 'Block'
            │
            ▼
    [Check LEFT JOIN to entities_staging]
    [Check LEFT JOIN to customers_staging]
            │
            ▼
    RESULT: Missing Entity Records
    RESULT: Missing Customer Records


STEP 2: CREATE MISSING ENTITIES
─────────────────────────────────
    Missing Entity List
            │
            ├─ entity_name    ← file_title (required)
            ├─ entity_type    ← entity_type (default: 'Individual')
            ├─ file_number    ← file_number (required)
            ├─ created_at     ← GETDATE()
            └─ updated_at     ← GETDATE()
            │
            ▼
    INSERT INTO entities_staging
            │
            ▼
    entities_staging (UPDATED)


STEP 3: CREATE MISSING CUSTOMERS
──────────────────────────────────
    Missing Customer List
            │
            ├─ customer_name       ← file_title (required)
            ├─ customer_type       ← customer_type (default: 'Individual')
            ├─ file_number         ← file_number (required)
            ├─ account_no          ← file_number
            ├─ property_address    ← property_address OR location
            ├─ entity_id           ← [Link to entity created in STEP 2]
            ├─ status              ← 'Active'
            ├─ created_by          ← created_by (default: 1 for system)
            ├─ created_at          ← GETDATE()
            └─ updated_at          ← GETDATE()
            │
            ▼
    INSERT INTO customers_staging
            │
            ▼
    customers_staging (UPDATED)


STEP 4: VALIDATION
───────────────────
    Count remaining missing entities → Should be 0
    Count remaining missing customers → Should be 0
    Check entity-customer links are valid → Should all exist
    Check DCIV-/LPPC- weren't synced → Should be 0
```

---

## File Number Filtering Logic

```
┌────────────────────────────────────────────────────────────┐
│           FILE NUMBER FILTER DECISION TREE                 │
└────────────────────────────────────────────────────────────┘

    START: Check file_number
            │
            ├─ Does it start with 'DCIV-'?
            │  YES → EXCLUDE (skip backfill) ❌
            │  NO  → Continue...
            │
            ├─ Does it start with 'LPPC-'?
            │  YES → EXCLUDE (skip backfill) ❌
            │  NO  → Continue...
            │
            └─ INCLUDE in backfill ✅


EXAMPLE FILTERING:
──────────────────

file_indexings.file_number    │ Include? │ Reason
──────────────────────────────┼──────────┼────────────────────────────────
GKN-2026-001                  │   YES    │ Regular GKN format
LPKN-2025-042                 │   YES    │ Regular LPKN format
MLS-12345                     │   YES    │ Regular MLS format
DCIV-2026-001                 │   NO     │ DCIV prefix excluded
LPPC-2025-042                 │   NO     │ LPPC prefix excluded
Custom-ABC-123               │   YES    │ Custom format allowed
dciv-2026-001                │   NO     │ Lowercase DCIV (LIKE is case-insensitive)
```

---

## Entity-Customer Relationship

```
┌──────────────────────────────────────────────────────────┐
│         RELATIONSHIP STRUCTURE                           │
└──────────────────────────────────────────────────────────┘


ONE FILE_NUMBER → ONE ENTITY → ONE CUSTOMER


Example:
────────

file_indexing.file_number = "GKN-2026-001"
                  │
                  │
    ┌─────────────┴─────────────┐
    │                           │
    ▼                           ▼
entity_staging             customer_staging
┌──────────────────────┐   ┌──────────────────────┐
│ id: 5                │   │ id: 10               │
│ file_number: GKN-... │───│ file_number: GKN-... │
│ entity_name: John    │   │ customer_name: John  │
│ entity_type: Indv    │───│ entity_id: 5         │
└──────────────────────┘   │ status: Active       │
                           └──────────────────────┘
                                    │
                         (FK link: entity_id = 5)


IMPLICATIONS:
─────────────
• Each file_number has AT MOST one entity
• Each file_number has AT MOST one customer
• Customers MUST have an entity_id (no orphans)
• Entity-Customer link is 1:1
• Deletion cascade: If entity deleted, customer becomes orphan
```

---

## Default Values Table

```
┌────────────────────────────────────────────────────────────┐
│              BACKFILL DEFAULT VALUES                       │
└────────────────────────────────────────────────────────────┘

ENTITY FIELDS
─────────────
entity_name:
  ├─ If file_title is provided → Use file_title
  └─ Otherwise → Use file_number (fallback)

entity_type:
  ├─ If entity_type is provided in file_indexing → Use it
  └─ Otherwise → Use 'Individual' (default)

file_number:
  └─ Always → Use file_number from file_indexing


CUSTOMER FIELDS
───────────────
customer_name:
  ├─ If file_title is provided → Use file_title
  ├─ Otherwise, if entity_name is available → Use entity_name
  └─ Otherwise → Use file_number (fallback)

customer_type:
  ├─ If customer_type is provided in file_indexing → Use it
  └─ Otherwise → Use 'Individual' (default)

file_number:
  └─ Always → Use file_number from file_indexing

account_no:
  └─ Always → Set to file_number (no direct mapping, sensible default)

property_address:
  ├─ If property_address is available → Use it
  └─ Otherwise → Use location field (fallback)

entity_id:
  └─ Always → Link to entity created in previous step

status:
  └─ Always → Set to 'Active'

created_by:
  ├─ If created_by is available from file_indexing → Use it
  └─ Otherwise → Use 1 (system user default)

created_at & updated_at:
  └─ Always → Set to GETDATE() (current timestamp)
```

---

## Scope & Constraints

```
┌────────────────────────────────────────────────────────────┐
│            CURRENT SCOPE CONSTRAINTS                       │
└────────────────────────────────────────────────────────────┘

INCLUDED (Will Backfill):
─────────────────────────
✅ Files with workflow_status = 'indexed'
✅ Files with indexing_type != 'Block'
✅ Files created in last 90 days (adjustable)
✅ Any file_number NOT starting with DCIV- or LPPC-
✅ Files where NO corresponding entity_staging record exists
✅ Files where NO corresponding customer_staging record exists


EXCLUDED (Will NOT Backfill):
──────────────────────────────
❌ Files with indexing_type = 'Block' (different logic)
❌ Files with general_registry = 'DCIV Registry' (special handling)
❌ Files with DCIV- prefix (special workflow)
❌ Files with LPPC- prefix (special workflow)
❌ Files with workflow_status != 'indexed' (incomplete indexing)
❌ Files created more than 90 days ago (configurable)
❌ Files that already have entity_staging records (idempotent check)
❌ Files that already have customer_staging records (idempotent check)


ADJUSTABLE PARAMETERS:
──────────────────────
• Date Range: Currently 90 days, can be changed to unlimited
• Default created_by: Currently 1, can use different user ID
• Workflow Status: Currently 'indexed', can include other statuses
• Registry Filter: Can add specific registry filters
```

---

## Error Scenarios & Recovery

```
┌────────────────────────────────────────────────────────────┐
│              ERROR SCENARIOS & RECOVERY                    │
└────────────────────────────────────────────────────────────┘


SCENARIO 1: NO RECORDS CREATED
───────────────────────────────
Symptom: @@ROWCOUNT = 0 for both INSERT statements
Cause: 
  • No missing records found (already backfilled)
  • WHERE clause filters too restrictive
  • Date range too narrow
Recovery:
  • Verify records actually exist: SELECT * FROM file_indexings
  • Check date range: Was any file created within 90 days?
  • Loosen filters and try again


SCENARIO 2: DCIV/LPPC RECORDS CREATED
───────────────────────────────────────
Symptom: Records found in entities_staging/customers_staging with DCIV-* or LPPC-*
Cause: 
  • WHERE clause missing or incorrect
  • Not using provided SQL script
Recovery:
  DELETE FROM customers_staging WHERE file_number LIKE 'DCIV-%' OR file_number LIKE 'LPPC-%';
  DELETE FROM entities_staging WHERE file_number LIKE 'DCIV-%' OR file_number LIKE 'LPPC-%';


SCENARIO 3: CUSTOMERS WITHOUT ENTITIES (Orphans)
─────────────────────────────────────────────────
Symptom: customers_staging.entity_id IS NULL or points to non-existent entity
Cause: 
  • Entity creation failed
  • Transaction rolled back partially
Recovery:
  CREATE MISSING ENTITIES first, then:
  UPDATE customers_staging SET entity_id = e.id
  FROM entities_staging e
  WHERE customers_staging.file_number = e.file_number


SCENARIO 4: DUPLICATE FILE_NUMBER CONSTRAINT
──────────────────────────────────────────────
Symptom: "Violation of PRIMARY KEY or UNIQUE KEY constraint"
Cause: 
  • Script already ran once (idempotency check failed)
  • Duplicate source records in file_indexings
Recovery:
  • Rerun script (includes NOT EXISTS check to prevent duplicates)
  • If duplicate source records, clean up file_indexings first
```

---

## Relationship Validation Queries

```
┌────────────────────────────────────────────────────────────┐
│        QUERY TO VERIFY RELATIONSHIP INTEGRITY             │
└────────────────────────────────────────────────────────────┘


CHECK 1: All customers have entities
─────────────────────────────────────
SELECT COUNT(*) AS orphaned_customers
FROM customers_staging c
WHERE c.entity_id IS NULL OR NOT EXISTS (
    SELECT 1 FROM entities_staging e WHERE e.id = c.entity_id
);
→ Should return: 0


CHECK 2: One entity per file_number
────────────────────────────────────
SELECT file_number, COUNT(*) AS duplicate_entities
FROM entities_staging
GROUP BY file_number
HAVING COUNT(*) > 1;
→ Should return: No rows


CHECK 3: Customer linked to correct entity
───────────────────────────────────────────
SELECT c.id, c.file_number, c.entity_id, e.file_number AS entity_fileno
FROM customers_staging c
INNER JOIN entities_staging e ON c.entity_id = e.id
WHERE c.file_number != e.file_number;
→ Should return: No rows (file_numbers should match)


CHECK 4: DCIV/LPPC exclusion verification
──────────────────────────────────────────
SELECT COUNT(*) AS excluded_entities
FROM entities_staging
WHERE file_number LIKE 'DCIV-%' OR file_number LIKE 'LPPC-%';
→ Should return: 0

SELECT COUNT(*) AS excluded_customers
FROM customers_staging
WHERE file_number LIKE 'DCIV-%' OR file_number LIKE 'LPPC-%';
→ Should return: 0
```

---

**Diagram Updated**: March 2, 2026  
**Clarity Level**: Intermediate to Advanced
