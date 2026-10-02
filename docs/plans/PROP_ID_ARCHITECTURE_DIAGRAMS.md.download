# Property ID (prop_id) System Architecture & Data Flow

**Visual Reference Guide**

---

## Current State: What's Broken 🔴

### Data Flow Diagram (CURRENT)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         USER ACTIONS & WORKFLOWS                            │
└─────────────────────────────────────────────────────────────────────────────┘

╔════════════════════════════════════════╗
║  Path 1: File Indexing (WORKS ✅)     ║
║                                        ║
║  FileIndexing Form                    ║
│    ↓                                   ║
│  FileIndexingController::update()     ║
│    ↓ (has prop_id from request)       ║
│  file_indexings table (workflow meta) ║
│    ↓                                   ║
│  file_history_staging (prop_id=100)   ║
│    ↓                                   ║
│  PropertyRecordController::           ║
│    storeFromIndexing()                ║
│    ↓ (allocates prop_id via           ║
│       determinePropIdForFile())        ║
│  property_records (prop_id=100)       ║
│    ↓                                   ║
│  ✅ All tables have SAME prop_id      ║
╚════════════════════════════════════════╝

╔════════════════════════════════════════╗
║  Path 2: Form Entry (BROKEN ❌)       ║
║                                        ║
║  Property Card Form                   ║
│    ↓                                   ║
│  PropertyCardController::store()      ║
│    ↓                                   ║
│  PropertyRecordController::store()    ║
│    ↓                                   ║
│  🚨 NO prop_id allocation!            ║
│    ↓                                   ║
│  property_records (prop_id=NULL)      ║
│    ↓                                   ║
│  file_history_staging (prop_id=NULL)  ║
│    ↓                                   ║
│  ❌ Record ORPHANED from ecosystem    ║
╚════════════════════════════════════════╝

╔════════════════════════════════════════╗
║  Path 3: Caveat Placement (BROKEN ❌) ║
║                                        ║
║  Caveat Form                          ║
│    ↓                                   ║
│  CaveatController::store()            ║
│    ↓                                   ║
│  🚨 NO prop_id lookup!                ║
│    ↓                                   ║
│  caveats table (prop_id=NULL)         ║
│    ↓                                   ║
│  ❌ Cannot link to property history   ║
│  ❌ Legal search by prop_id fails     ║
╚════════════════════════════════════════╝

╔════════════════════════════════════════╗
║  Path 4: CofO Entry (BROKEN ❌)       ║
║                                        ║
║  CofO Form                            ║
│    ↓                                   ║
│  FileIndexingController::             ║
│    updateCofORecord()                 ║
│    ↓                                   ║
│  🚨 NO prop_id allocation!            ║
│    ↓                                   ║
│  Cofo table (prop_id=NULL or          ║
│             prop_id=⚠️ different)     ║
│    ↓                                   ║
│  ❌ Cannot pivot on prop_id           ║
╚════════════════════════════════════════╝
```

---

## Proposed State: What Will Work ✅

### Three-Layer Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    LAYER 1: CENTRALIZED SERVICE                             │
│                 (Single Source of Truth for prop_id)                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│              PropertyIdAllocationService                                    │
│              ┌────────────────────────────────────┐                        │
│              │ allocateOrRetrievePropId()         │                        │
│              │                                    │                        │
│              │  Input: fileNumber, mlsFNo,        │                        │
│              │         kangisFileNo, ...          │                        │
│              │                                    │                        │
│              │  Process:                          │                        │
│              │  1. Search property_records        │                        │
│              │  2. Search pra (legacy)            │                        │
│              │  3. Generate new if needed         │                        │
│              │     (MAX + 1, atomic)              │                        │
│              │                                    │                        │
│              │  Output: prop_id (int)             │                        │
│              └────────────────────────────────────┘                        │
│                          ↑  ↑  ↑  ↑                                        │
└──────────────────────────┼──┼──┼──┼────────────────────────────────────────┘
                           │  │  │  │
        ┌──────────────────┼──┼──┼──┼──────────────────┐
        │                  │  │  │  │                  │
┌───────┴──────────────────┼──┼──┼──┼─────────────┐  │
│                          │  │  │  │             │  │
│                    ┌─────┴──┴──┴──┴────────┐    │  │
│                    │                       │    │  │
│              ┌─────▼─────────┐    ┌────────▼───┬┴──┴──┐
│              │               │    │            │      │
│    ┌─────────▼────────┐   ┌──┴────▼───────────┘      │
│    │   Property        │   │   │                     │
│    │   RecordController│   │   │                     │
│    │   ::store()       │   │   └──────┬───────────┐  │
│    │                   │   │          │           │  │
│    │ ✅ prop_id        │   │   ┌──────▼──┐  ┌────▼──┴──────┐
│    │    allocated      │   │   │ Caveat  │  │              │
│    │                   │   │   │Cont::   │  │ CofO / File  │
│    │ ✅ injected into  │   │   │store()  │  │ IndexingCont │
│    │    property table │   │   │         │  │              │
│    │                   │   │   │ ✅ prop │  │ ✅ prop_id   │
│    │ ✅ synced to      │   │   │   id    │  │    allocated │
│    │    file_history   │   │   │   allo  │  │              │
│    └───────────────────┘   │   │   cated │  └──────────────┘
│                            │   │         │
│                            │   └─────────┘
└────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│                        LAYER 2: CONTROLLERS                                   │
│                                                                               │
│  All controllers inject PropertyIdAllocationService                          │
│  All controllers call allocateOrRetrievePropId() BEFORE insert               │
│  All controllers include prop_id in insert/update payload                    │
└──────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│                        LAYER 3: DATABASE                                      │
│                                                                               │
│  Tables with prop_id:                                                        │
│  ✅ file_history_staging    (prop_id present, working)                       │
│  ✅ property_records        (prop_id present, needs fix)                     │
│  ✅ pra                     (legacy, working)                                │
│  ⚠️  Cofo                   (prop_id exists, needs allocation)               │
│  🆕 caveats                 (prop_id to add via migration)                   │
│  (file_indexings + fileNumber rely on file_number linkage; no prop_id)      │
│  🆕 scannings/pagetypings   (optional: prop_id to add)                       │
│                                                                               │
│  Foreign Keys:                                                               │
│  caveats.prop_id ──→ file_history_staging.prop_id                           │
│  Cofo.prop_id ──→ file_history_staging.prop_id                              │
│  (fileNumber stays keyed by file_number; joins via that natural key)        │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## Data Flow: After Implementation

```
User Action → Controller → Service → Database

┌──────────────────┐
│ Create Property  │
│ Record (Form)    │
└────────┬─────────┘
         │
         ▼
┌──────────────────────────────────────────┐
│ PropertyRecordController::store()         │
│ 1. Validate form                         │
│ 2. Call propIdService.allocate...()      │
│    ↓                                     │
│    service returns prop_id=101           │
│ 3. Include prop_id in data payload       │
│ 4. Insert into property_records          │
│    ↓                                     │
│    ✅ prop_id=101 saved                  │
│ 5. Call syncPropIdToFileHistory()        │
│    ↓                                     │
│    ✅ file_history updated with 101     │
└───────────┬────────────────────────────┘
            │
            ▼
        Database State:
        ┌─────────────────────────────────┐
        │ property_records table:         │
        │  id=42, prop_id=101 ✅          │
        │                                 │
        │ file_history_staging table:     │
        │  id=1, prop_id=101 ✅           │
        │  id=2, prop_id=101 ✅           │
        │  (all related to same file)     │
        └─────────────────────────────────┘


┌──────────────────────────────────┐
│ Place Caveat on Property         │
└────────┬─────────────────────────┘
         │
         ▼
┌────────────────────────────────────────────┐
│ CaveatController::store()                  │
│ 1. Validate caveat data                    │
│ 2. Call propIdService.allocate...()        │
│    (looks up existing prop_id for file)    │
│    ↓                                       │
│    service returns prop_id=101             │
│ 3. Include prop_id in caveat data          │
│ 4. Insert into caveats table               │
│    ↓                                       │
│    ✅ prop_id=101 saved in caveat          │
└───────────┬────────────────────────────────┘
            │
            ▼
        Database State:
        ┌─────────────────────────────────┐
        │ caveats table:                  │
        │  caveat_id=500, prop_id=101 ✅  │
        │                                 │
        │ Can now link:                   │
        │  caveat + property history via  │
        │  prop_id=101                    │
        └─────────────────────────────────┘
```

---

## Table Relationships (After Implementation)

```
                    file_history_staging
                         (hub table)
                             │
                    prop_id (primary key)
                             │
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              ▼              ▼
        property_records   caveats        Cofo
        ┌──────────────┐  ┌────────┐  ┌──────────┐
        │ prop_id → ✅│  │prop_id→✅  │prop_id→✅│
        │ (foreign key)│  │ (fk)  │  │ (fk)  │
        └──────────────┘  └────────┘  └──────────┘
              │
              ▼
        file_indexings
        ┌──────────────┐
        │ prop_id → ✅ │
        │ (indexed)  │
        └──────────────┘


Query Path (WORKS NOW):
┌────────────────────────────────────────────┐
│ Find all history for property ID 101:      │
│                                            │
│ SELECT * FROM file_history_staging        │
│ WHERE prop_id = 101                        │
│   AND (                                    │
│     transaction_type IN (...)  -- filters   │
│   )                                        │
│                                            │
│ Returns:                                   │
│ - All file transfers                       │
│ - Related caveats (via JOIN)               │
│ - Related CofO records (via JOIN)          │
│ - Registration history                     │
│ - All metadata (serial, page, volume)      │
└────────────────────────────────────────────┘
```

---

## Impact Matrix: Gaps vs. Tables

```
                │property_records│file_indexing│file_history │caveats│Cofo │fileNumber (n/a)
                │                │_s           │_staging      │       │     │
────────────────┼────────────────┼─────────────┼──────────────┼───────┼─────┼──────────
PropertyRecord  │❌ NULL         │⚠️ synced    │⚠️ NULL after │  -    │  -  │   -
Controller::    │   (CRITICAL)   │             │  sync        │       │     │
store()         │                │             │              │       │     │
────────────────┼────────────────┼─────────────┼──────────────┼───────┼─────┼──────────
Caveat          │    -           │     -       │     -        │❌NULL │  -  │   -
Controller::    │                │             │              │(CRITI)│     │
store()         │                │             │              │       │     │
────────────────┼────────────────┼─────────────┼──────────────┼───────┼─────┼──────────
Cofo            │    -           │     -       │     -        │  -    │❌NULL│   -
updateCofO      │                │             │              │       │(HIGH)│
Record()        │                │             │              │       │     │
────────────────┼────────────────┼─────────────┼──────────────┼───────┼─────┼──────────
Direct SQL      │    -           │     -       │     -        │  -    │  -  │❌NULL
Inserts         │                │             │              │       │     │(HIGH)
────────────────┴────────────────┴─────────────┴──────────────┴───────┴─────┴──────────

Legend:
❌ NULL = Critical: prop_id not allocated, records orphaned
⚠️ Synced = Working but requires file history sync
✅ OK = Properly allocated and maintained
-  = Not applicable to this path
```

---

## Timeline: Implementation Phases

```
Week 1: Infrastructure
┌─────────────────────────────────────────┐
│ ✅ Create PropertyIdAllocationService   │
│ ✅ Register in AppServiceProvider       │
│ ✅ Create database migrations           │
│ ✅ Unit tests for service              │
└─────────────────────────────────────────┘

Week 2: Controllers
┌─────────────────────────────────────────┐
│ ✅ PropertyRecordController::store()     │
│ ✅ CaveatController::store()             │
│ ✅ FileIndexingController::updateCofO() │
│ ✅ Review direct SQL inserts            │
│ ✅ Integration tests                    │
└─────────────────────────────────────────┘

Week 3: Testing & Validation
┌─────────────────────────────────────────┐
│ ✅ Run validation SQL queries           │
│ ✅ E2E test full workflows              │
│ ✅ Load test concurrent allocation      │
│ ✅ Regression test existing code        │
└─────────────────────────────────────────┘

Week 4: Data & Production
┌─────────────────────────────────────────┐
│ ✅ Run backfill SQL script              │
│ ✅ Deploy code to production            │
│ ✅ Monitor prop_id allocation           │
│ ✅ Document & celebrate 🎉              │
└─────────────────────────────────────────┘
```

---

## Success Indicators

```
BEFORE Implementation:
├─ ❌ Property records with NULL prop_id
├─ ❌ Caveats orphaned from history
├─ ❌ Different modules have different prop_ids
├─ ❌ File history pivots fail on NULL
├─ ❌ Legal searches incomplete
└─ ❌ Data integrity issues

AFTER Implementation:
├─ ✅ Zero NULL prop_id in critical tables
├─ ✅ Caveats linked to property history
├─ ✅ Single identifier across all modules
├─ ✅ File history pivots by prop_id
├─ ✅ Legal searches complete
├─ ✅ Unique prop_id per file/property
├─ ✅ All workflows functional
└─ ✅ Data integrity maintained
```

---

**Visual Guide Created**: December 6, 2025  
**For**: KLAES GIS EDMS Dev Team

